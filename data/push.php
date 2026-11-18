<?php
/**
 * =============================================================================
 * NOTIFICATIONS « PUSH » DE LA MESSAGERIE
 * =============================================================================
 *
 * Previent d'un nouveau message meme quand l'application est fermee : le
 * telephone (ou l'ordinateur) recoit la notification de son service de push
 * (Google pour Chrome et Android, Apple pour Safari, Mozilla pour Firefox),
 * qui la tient de ce serveur.
 *
 * Trois normes, sans bibliotheque (le serveur n'a ni Composer ni gmp) :
 *   - RFC 8030 : un POST a l'adresse que le navigateur a donnee en s'abonnant ;
 *   - RFC 8291 : le contenu est chiffre pour ce seul navigateur (ECDH P-256,
 *     HKDF, AES-128-GCM) : le service de push ne peut pas le lire ;
 *   - RFC 8292 (VAPID) : chaque envoi est signe par la cle de l'application,
 *     creee une fois et gardee dans msg_push_cle.
 *
 * Quand :
 *   - a chaque message ecrit (msgInsererMessage), APRES la reponse a
 *     l'expediteur (fastcgi_finish_request) : il n'attend pas les envois ;
 *   - a chaque membre de la discussion abonne, sauf l'auteur, les discussions
 *     en sourdine, les personnes qui ont bloque l'auteur, et celles qui ont la
 *     messagerie sous les yeux (msg_presence.regarde_le recent) : la page les
 *     previent deja, avec le son.
 *
 * Abonnements : un par navigateur (msg_push_abonnement), cree par la cloche
 * de la messagerie (src/js/messagerie.js). La deconnexion efface celui de
 * l'appareil (app/logout.php), pour qu'un appareil partage ne recoive plus
 * les messages du compte precedent. Un abonnement que le service de push dit
 * perime (404, 410) est efface.
 *
 * Tables : data/migration_push.sql. Page de reception : /sw.js (push,
 * notificationclick).
 * =============================================================================
 */

const PUSH_TTL = 86400;            // un message non distribue en un jour n'est plus annonce
const PUSH_REGARDE_SECONDES = 25;  // la page interroge au plus toutes les 15 s quand elle est visible
const PUSH_APERCU = 140;           // caracteres du message montres dans la notification
const PUSH_COOKIE = 'registrar_push';

/** Le serveur sait-il chiffrer et envoyer ? (openssl EC, curl) */
function pushDisponible(): bool
{
    return defined('OPENSSL_KEYTYPE_EC') && function_exists('openssl_pkey_derive')
        && function_exists('hash_hkdf') && function_exists('curl_multi_init');
}

function pushB64(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

function pushB64Lire(string $texte): string
{
    $o = base64_decode(strtr($texte, '-_', '+/') . str_repeat('=', (4 - strlen($texte) % 4) % 4), true);
    return $o === false ? '' : $o;
}

/* PHP pour Windows ne trouve pas seul son openssl.cnf, sans lequel il ne cree
   pas de cle (poste de developpement) ; sous Linux le reglage par defaut suffit. */
function pushOptionsCle(): array
{
    $o = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
    if (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF')) {
        $cnf = dirname((string) ini_get('extension_dir')) . '/extras/ssl/openssl.cnf';
        if (is_file($cnf)) { $o['config'] = $cnf; }
    }
    return $o;
}

/** Nouvelle paire P-256 : [cle privee, cle publique brute de 65 octets]. */
function pushNouvellePaire(): array
{
    $cle = openssl_pkey_new(pushOptionsCle());
    if ($cle === false) {
        throw new RuntimeException('openssl : ' . openssl_error_string());
    }
    $d = openssl_pkey_get_details($cle);
    $publique = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return [$cle, $publique];
}

/** Cle publique brute (65 octets, 0x04 x y) -> PEM lisible par openssl. */
function pushPemPublique(string $brute): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $brute;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * La cle VAPID de l'application, creee au premier besoin.
 * @return array{publique:string, pem:string}  publique en base64url
 */
function pushCles(PDO $dtb): array
{
    static $cles = null;
    if ($cles !== null) {
        return $cles;
    }
    $lire = function () use ($dtb) {
        return $dtb->query('SELECT publique, privee_pem FROM msg_push_cle WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    };
    $l = $lire();
    if (!$l) {
        [$cle, $publique] = pushNouvellePaire();
        openssl_pkey_export($cle, $pem, null, pushOptionsCle());
        /* INSERT IGNORE : deux premiers envois simultanes gardent la meme cle. */
        $dtb->prepare('INSERT IGNORE INTO msg_push_cle (id, publique, privee_pem, cree_le) VALUES (1, ?, ?, NOW())')
            ->execute([pushB64($publique), $pem]);
        $l = $lire();
    }
    return $cles = ['publique' => $l['publique'], 'pem' => $l['privee_pem']];
}

/** Cle publique a donner au navigateur pour s'abonner ; null si pas d'envoi possible. */
function pushClePublique(PDO $dtb): ?string
{
    try {
        return pushDisponible() ? pushCles($dtb)['publique'] : null;
    } catch (Throwable $e) {
        error_log('push : ' . $e->getMessage());   // migration absente, openssl sans EC
        return null;
    }
}

/**
 * Chiffre le contenu pour un navigateur (RFC 8291, aes128gcm, un seul bloc).
 * $uaPublique : cle p256dh du navigateur (65 octets) ; $auth : son secret (16).
 */
function pushChiffrer(string $contenu, string $uaPublique, string $auth): string
{
    [$ephemere, $asPublique] = pushNouvellePaire();
    $secret = openssl_pkey_derive(openssl_pkey_get_public(pushPemPublique($uaPublique)), $ephemere, 32);
    if ($secret === false) {
        throw new RuntimeException('Cle du navigateur illisible.');
    }
    $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublique . $asPublique, $auth);
    $sel = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sel);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sel);
    $chiffre = openssl_encrypt($contenu . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $etiquette);
    return $sel . pack('N', 4096) . chr(65) . $asPublique . $chiffre . $etiquette;
}

/** Signature ECDSA DER (SEQUENCE de deux INTEGER) -> r||s de 64 octets (JWS ES256). */
function pushSignatureBrute(string $der): string
{
    $o = 2 + ((ord($der[1]) & 0x80) ? (ord($der[1]) & 0x7f) : 0);
    $brut = '';
    for ($i = 0; $i < 2; $i++) {
        $long = ord($der[$o + 1]);
        $brut .= str_pad(ltrim(substr($der, $o + 2, $long), "\0"), 32, "\0", STR_PAD_LEFT);
        $o += 2 + $long;
    }
    return $brut;
}

/** En-tete Authorization VAPID pour le service de push $origine (RFC 8292). */
function pushVapid(array $cles, string $origine, string $contact): string
{
    static $jetons = [];
    if (!isset($jetons[$origine])) {
        $entete = pushB64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $corps = pushB64(json_encode(['aud' => $origine, 'exp' => time() + 12 * 3600, 'sub' => $contact], JSON_UNESCAPED_SLASHES));
        openssl_sign("$entete.$corps", $der, $cles['pem'], OPENSSL_ALGO_SHA256);
        $jetons[$origine] = "vapid t=$entete.$corps." . pushB64(pushSignatureBrute($der)) . ', k=' . $cles['publique'];
    }
    return $jetons[$origine];
}

/* ------------------------------------------------------------- abonnements */

/** Seuls les services de push connus recoivent nos envois (pas d'adresse interne). */
function pushAdresseValide(string $adresse): bool
{
    if (strlen($adresse) > 1000 || !preg_match('~^https://([a-z0-9.-]+)(:443)?/~i', $adresse, $m)) {
        return false;
    }
    $hote = strtolower($m[1]);
    foreach (['.googleapis.com', '.push.apple.com', '.mozilla.com', '.mozaws.net', '.notify.windows.com', '.push.services.mozilla.com'] as $fin) {
        if (str_ends_with($hote, $fin)) { return true; }
    }
    return false;
}

/** Enregistre (ou rend a ce compte) l'abonnement de ce navigateur. */
function pushAbonner(PDO $dtb, int $userId, string $adresse, string $p256dh, string $auth, string $appareil): void
{
    $cle = pushB64Lire($p256dh);
    $secret = pushB64Lire($auth);
    if (!pushAdresseValide($adresse) || strlen($cle) !== 65 || $cle[0] !== "\x04" || strlen($secret) !== 16) {
        throw new InvalidArgumentException('Abonnement invalide.');
    }
    $empreinte = hash('sha256', $adresse);
    /* Une adresse = un navigateur : si un autre compte s'y etait abonne
       (appareil partage), elle passe au compte connecte. */
    $dtb->prepare('INSERT INTO msg_push_abonnement (empreinte, utilisateur_id, adresse, cle_p256dh, cle_auth, appareil, cree_le, vu_le, echecs)
                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)
                   ON DUPLICATE KEY UPDATE utilisateur_id = VALUES(utilisateur_id), cle_p256dh = VALUES(cle_p256dh),
                                           cle_auth = VALUES(cle_auth), appareil = VALUES(appareil), vu_le = NOW(), echecs = 0')
        ->execute([$empreinte, $userId, $adresse, $p256dh, $auth, mb_substr($appareil, 0, 120)]);
    /* Retrouver l'abonnement de cet appareil a la deconnexion. */
    if (!headers_sent()) {
        setcookie(PUSH_COOKIE, $empreinte, [
            'expires' => time() + 400 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }
}

/* L'adresse n'est connue que du navigateur qui la detient : la donner suffit
   a l'effacer, quel que soit le compte auquel elle etait rattachee (le compte
   connecte n'a pas voulu de notifications, l'appareil recevait encore celles
   du precedent). */
function pushDesabonner(PDO $dtb, string $adresse): void
{
    $dtb->prepare('DELETE FROM msg_push_abonnement WHERE empreinte = ?')->execute([hash('sha256', $adresse)]);
}

/** A la deconnexion : l'appareil ne recoit plus les messages de ce compte. */
function pushOublierAppareil(PDO $dtb): void
{
    $empreinte = (string) ($_COOKIE[PUSH_COOKIE] ?? '');
    if (preg_match('~^[a-f0-9]{64}$~', $empreinte)) {
        try {
            $dtb->prepare('DELETE FROM msg_push_abonnement WHERE empreinte = ?')->execute([$empreinte]);
        } catch (Throwable $e) { /* table absente : rien a oublier */ }
    }
    if (isset($_COOKIE[PUSH_COOKIE]) && !headers_sent()) {
        setcookie(PUSH_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    }
}

/** La messagerie est-elle sous les yeux de ce compte ? (interrogation avec vue=1) */
function pushNoterRegard(PDO $dtb, int $userId): void
{
    $dtb->prepare('INSERT INTO msg_presence (user_id, vu_le, regarde_le) VALUES (?, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE regarde_le = IF(regarde_le IS NULL OR regarde_le < NOW() - INTERVAL 5 SECOND, NOW(), regarde_le)')
        ->execute([$userId]);
}

/* ------------------------------------------------------------------- envoi */

/**
 * Envoie $contenu (tableau, en JSON) a tous les abonnements de ces comptes,
 * en parallele. Retire les abonnements perimes.
 * @return array{envoyes:int, echecs:int, codes:int[]}  codes : reponse HTTP de chaque envoi (0 = pas de reponse)
 */
function pushEnvoyer(PDO $dtb, array $userIds, array $contenu, string $sujet = ''): array
{
    $bilan = ['envoyes' => 0, 'echecs' => 0, 'codes' => []];
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (!$userIds || !pushDisponible()) {
        return $bilan;
    }
    $place = implode(',', array_fill(0, count($userIds), '?'));
    $st = $dtb->prepare("SELECT id, adresse, cle_p256dh, cle_auth FROM msg_push_abonnement WHERE utilisateur_id IN ($place)");
    $st->execute($userIds);
    $abonnements = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$abonnements) {
        return $bilan;
    }

    $cles = pushCles($dtb);
    $contact = defined('PUSH_CONTACT') ? PUSH_CONTACT
        : 'https://' . preg_replace('~[^a-z0-9.-]~i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'registrar.zurcher.edu.mg'));
    $json = json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $multi = curl_multi_init();
    $poignees = [];
    foreach ($abonnements as $a) {
        try {
            $corps = pushChiffrer($json, pushB64Lire($a['cle_p256dh']), pushB64Lire($a['cle_auth']));
        } catch (Throwable $e) {
            $bilan['echecs']++;
            continue;
        }
        $p = parse_url($a['adresse']);
        $entetes = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . PUSH_TTL,
            'Urgency: high',
            'Authorization: ' . pushVapid($cles, $p['scheme'] . '://' . $p['host'], $contact),
        ];
        /* Un seul message en attente par discussion : un telephone eteint
           ne recoit au rallumage que le dernier. */
        if (!empty($contenu['sujet']) && preg_match('~^[A-Za-z0-9_-]{1,32}$~', $contenu['sujet'])) {
            $entetes[] = 'Topic: ' . $contenu['sujet'];
        }
        $c = curl_init($a['adresse']);
        curl_setopt_array($c, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corps, CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        curl_multi_add_handle($multi, $c);
        $poignees[(int) $a['id']] = $c;
    }
    do {
        $etat = curl_multi_exec($multi, $actifs);
        if ($actifs) { curl_multi_select($multi, 1.0); }
    } while ($actifs && $etat === CURLM_OK);

    $perimes = []; $reussis = []; $refuses = [];
    foreach ($poignees as $id => $c) {
        $code = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $bilan['codes'][] = $code;
        if ($code >= 200 && $code < 300) {
            $reussis[] = $id;
            $bilan['envoyes']++;
        } else {
            $bilan['echecs']++;
            if ($code === 404 || $code === 410) { $perimes[] = $id; }
            elseif ($code >= 400 && $code < 500 && $code !== 429) { $refuses[] = $id; }
        }
        curl_multi_remove_handle($multi, $c);
        curl_close($c);
    }
    curl_multi_close($multi);

    $en = fn (array $ids) => implode(',', array_map('intval', $ids));
    if ($reussis) { $dtb->exec('UPDATE msg_push_abonnement SET vu_le = NOW(), echecs = 0 WHERE id IN (' . $en($reussis) . ')'); }
    if ($perimes) { $dtb->exec('DELETE FROM msg_push_abonnement WHERE id IN (' . $en($perimes) . ')'); }
    /* Refus repetes (cle changee, abonnement mal forme) : on abandonne a 3. */
    if ($refuses) {
        $dtb->exec('UPDATE msg_push_abonnement SET echecs = echecs + 1 WHERE id IN (' . $en($refuses) . ')');
        $dtb->exec('DELETE FROM msg_push_abonnement WHERE echecs >= 3');
    }
    return $bilan;
}

/**
 * Ce que montre la notification d'un message : qui, quoi, ou ouvrir.
 * null si le message ne s'annonce pas (systeme, retire).
 */
function pushContenuMessage(PDO $dtb, int $messageId, string $base): ?array
{
    $st = $dtb->prepare('SELECT m.id, m.conversation_id, m.auteur_id, m.type, m.texte, m.retire_le, m.fichier_id,
                                c.type AS conv_type, c.nom AS conv_nom, f.nom AS fichier_nom
                           FROM msg_message m
                           JOIN msg_conversation c ON c.id = m.conversation_id
                      LEFT JOIN msg_fichier f ON f.id = m.fichier_id
                          WHERE m.id = ?');
    $st->execute([$messageId]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m || $m['type'] === 'systeme' || $m['retire_le'] !== null || !$m['auteur_id']) {
        return null;
    }
    $st = $dtb->prepare('SELECT prenom, nom FROM compt_utilisateur WHERE id = ?');
    $st->execute([(int) $m['auteur_id']]);
    $a = $st->fetch(PDO::FETCH_ASSOC) ?: ['prenom' => '', 'nom' => ''];
    $nom = trim(trim((string) $a['prenom']) . ' ' . trim((string) $a['nom'])) ?: 'Quelqu\'un';
    $prenom = trim((string) $a['prenom']) ?: $nom;

    $texte = trim(preg_replace('~\s+~u', ' ', (string) $m['texte']));
    if ($texte === '') {
        $texte = match ($m['type']) {
            'image' => '📷 Photo',
            'video' => '🎬 Vidéo',
            'audio' => '🎤 Message vocal',
            'fichier' => '📎 ' . ($m['fichier_nom'] ?: 'Fichier'),
            default => 'Nouveau message',
        };
    }
    if (mb_strlen($texte) > PUSH_APERCU) {
        $texte = rtrim(mb_substr($texte, 0, PUSH_APERCU - 1)) . '…';
    }
    $groupe = $m['conv_type'] === 'groupe';
    $conv = (int) $m['conversation_id'];
    return [
        'titre' => $groupe ? (trim((string) $m['conv_nom']) ?: 'Discussion de groupe') : $nom,
        'corps' => $groupe ? $prenom . ' : ' . $texte : $texte,
        'url'   => $base . '/messagerie#c' . $conv,
        'tag'   => 'messagerie-' . $conv,
        'sujet' => 'c' . $conv,
        'conversation' => $conv,
    ];
}

/** Destinataires d'une notification : membres abonnes qui ne regardent pas. @return int[] */
function pushDestinataires(PDO $dtb, int $messageId): array
{
    $st = $dtb->prepare('SELECT DISTINCT p.user_id
                           FROM msg_message m
                           JOIN msg_participant p ON p.conversation_id = m.conversation_id
                                                 AND p.user_id <> m.auteur_id
                                                 AND p.fin_message_id IS NULL
                                                 AND p.sourdine = 0
                           JOIN msg_push_abonnement a ON a.utilisateur_id = p.user_id
                           JOIN compt_utilisateur u ON u.id = p.user_id AND u.etat = 1
                      LEFT JOIN msg_presence pr ON pr.user_id = p.user_id
                          WHERE m.id = ?
                            AND (pr.regarde_le IS NULL OR pr.regarde_le < NOW() - INTERVAL ' . PUSH_REGARDE_SECONDES . ' SECOND)
                            AND NOT EXISTS (SELECT 1 FROM msg_blocage b WHERE b.user_id = p.user_id AND b.bloque_id = m.auteur_id)');
    $st->execute([$messageId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Annonce ce message apres la reponse a l'expediteur. Plusieurs messages
 * dans la meme requete (diffusion) partent ensemble a la fin.
 */
function pushMessageApres(PDO $dtb, int $messageId, string $base): void
{
    static $file = null;
    if (!pushDisponible()) {
        return;
    }
    if ($file === null) {
        $file = [];
        register_shutdown_function(function () use ($dtb, &$file, $base) {
            /* La reponse part maintenant ; la suite ne fait plus attendre. */
            if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
            ignore_user_abort(true);
            @set_time_limit(60);
            foreach ($file as $id) {
                try {
                    $contenu = pushContenuMessage($dtb, $id, $base);
                    if ($contenu) {
                        pushEnvoyer($dtb, pushDestinataires($dtb, $id), $contenu);
                    }
                } catch (Throwable $e) {
                    error_log('push : ' . $e->getMessage());
                }
            }
        });
    }
    $file[] = $messageId;
}
