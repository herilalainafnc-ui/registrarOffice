<?php
/* =============================================================================
   Messagerie instantanee — regles communes

   Discussions entre comptes de l'application, a deux ou en groupe, comme
   Messenger : messages ecrits, vocaux, photos, videos, documents, reponses,
   reactions, accuses de lecture, « en train d'ecrire ».

   Qui peut quoi :
     - tout compte actif (etat = 1) ecrit a tout autre compte actif ;
     - le personnel et les enseignants (niveaux 1 a 7) creent des groupes ;
       les administrateurs d'un groupe le renomment, y ajoutent ou en
       retirent des membres ; chacun peut le quitter ;
     - chacun peut bloquer une personne : elle ne peut plus lui ecrire en
       discussion a deux (les groupes ne changent pas, comme sur Messenger) ;
     - chacun peut signaler un message ; le superadmin (niveau 1) traite les
       signalements et peut retirer le message.

   Ce que voit un membre : les messages d'id superieur a debut_message_id
   (arrivee dans le groupe) et a efface_jusqu_a (discussion supprimee pour
   soi), et, s'il est parti, jusqu'a fin_message_id seulement.

   Synchronisation : chaque changement ajoute une ligne a msg_evenement ; le
   navigateur demande les evenements posterieurs au dernier qu'il connait
   (msgEtat). Une seule requete indexee par appel, toutes les deux secondes
   au plus : PHP-FPM n'a que cinq processus en production.

   Fichiers : envoyes par morceaux d'un mega-octet (upload_max_filesize vaut
   2 Mo en production), ranges dans data/messagerie/AAAA/MM/, servis par
   app/messagerie.fichier.php apres controle d'acces. Les photos sont
   reencodees sans leurs metadonnees (position GPS des telephones).

   Tables : data/migration_messagerie.sql. Point d'entree : app/messagerie.api.php.

   Usage :
       require_once __DIR__ . '/messagerie.php';
   ========================================================================== */

if (!defined('MESSAGERIE_LOADED')) {
define('MESSAGERIE_LOADED', true);

require_once __DIR__ . '/push.php';   // notifications quand l'application est fermee

define('MSG_TEXTE_MAX', 4000);                    // caracteres par message
define('MSG_TAILLE_MAX', 100 * 1024 * 1024);      // octets par fichier
define('MSG_QUOTA_JOUR', 1024 * 1024 * 1024);     // octets envoyes par jour et par compte
define('MSG_MORCEAU', 1024 * 1024);               // taille d'un morceau d'envoi
define('MSG_RYTHME', 40);                         // messages par minute et par compte
define('MSG_GROUPE_MAX', 250);                    // membres d'un groupe
define('MSG_PAR_PAGE', 40);                       // messages charges a la fois
define('MSG_EVENEMENTS_MAX', 400);                // au-dela, le navigateur recharge tout
define('MSG_ECRIT_SECONDES', 6);                  // duree de « en train d'ecrire »
define('MSG_EN_LIGNE_SECONDES', 150);             // « en ligne » : vu il y a moins de
define('MSG_IMAGE_COTE', 2560);                   // plus grand cote d'une photo gardee
define('MSG_APERCU_COTE', 720);                   // plus grand cote d'une vignette
define('MSG_IMAGE_PIXELS_MAX', 40000000);         // au-dela, la photo n'est pas retravaillee
define('MSG_REACTIONS', ['👍', '❤️', '😆', '😮', '😢', '😡', '🙏', '🎉']);

/** Extensions acceptees, par genre attendu. Le genre final vient du contenu. */
define('MSG_EXTENSIONS', [
    'image'   => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'],
    'video'   => ['mp4', 'webm', 'mov', 'm4v', '3gp', 'mkv', 'avi'],
    'audio'   => ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav', 'weba', 'amr'],
    'fichier' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
                  'rtf', 'txt', 'csv', 'zip', 'rar', '7z', 'epub'],
]);

/** Types que le navigateur affiche dans la page ; tout le reste se telecharge. */
define('MSG_MIME_AFFICHABLES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/webm', 'video/quicktime', 'video/3gpp',
    'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-wav', 'audio/opus',
    'application/pdf',
]);

/* -----------------------------------------------------------------------------
   Comptes
   -------------------------------------------------------------------------- */

/** Ligne de compt_utilisateur, ou null. */
function msgCompte(PDO $dtb, int $id): ?array
{
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $st = $dtb->prepare('SELECT id, nom, prenom, pseudo, post, level, privilege, photos, etat, student_id
                               FROM compt_utilisateur WHERE id = ?');
        $st->execute([$id]);
        $cache[$id] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return $cache[$id];
}

/** Le compte connecte, s'il est actif. */
function msgMoi(PDO $dtb): ?array
{
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $u = msgCompte($dtb, $id);
    return ($u && (int) $u['etat'] === 1) ? $u : null;
}

function msgPeutCreerGroupe(array $u): bool
{
    $n = (int) $u['level'];
    return $n >= 1 && $n <= 7;
}

function msgEstModerateur(array $u): bool
{
    return (int) $u['level'] === 1 || $u['privilege'] === 'superadmin';
}

/** « Rasoa Hery » : prenom puis nom, comme on s'appelle dans une discussion. */
function msgNomAffiche(array $u): string
{
    $nom = trim(trim((string) $u['prenom']) . ' ' . trim((string) $u['nom']));
    return $nom !== '' ? $nom : (string) $u['pseudo'];
}

function msgInitiales(array $u): string
{
    $i = mb_strtoupper(mb_substr(trim((string) $u['prenom']), 0, 1) . mb_substr(trim((string) $u['nom']), 0, 1));
    return trim($i) !== '' ? $i : mb_strtoupper(mb_substr((string) $u['pseudo'], 0, 2));
}

function msgNiveauCourt($annee): string
{
    if ($annee === null || $annee === '') {
        return '';
    }
    $n = (int) $annee;
    return $n === 0 ? 'Remise à niveau' : ($n <= 3 ? 'L' . $n : 'M' . ($n - 3));
}

/**
 * Profils d'affichage : nom, initiales, photo, role. Une requete pour tous.
 *
 * Photo : celle du compte (fichier present ou adresse https de Google),
 * sinon, pour un etudiant, celle de son dossier.
 *
 * @param int[] $ids
 * @return array<int, array{id:int, nom:string, initiales:string, avatar:?string, role:string, actif:bool, etudiant:bool}>
 */
function msgProfils(PDO $dtb, array $ids, string $appBase): array
{
    static $cache = [];
    $manquants = array_values(array_diff(array_unique(array_map('intval', $ids)), array_keys($cache)));
    if ($manquants) {
        $place = implode(',', array_fill(0, count($manquants), '?'));
        $st = $dtb->prepare("SELECT id, nom, prenom, pseudo, post, level, privilege, photos, etat, student_id
                               FROM compt_utilisateur WHERE id IN ($place)");
        $st->execute($manquants);
        $comptes = $st->fetchAll(PDO::FETCH_ASSOC);

        $sids = array_values(array_filter(array_map(fn ($c) => (string) $c['student_id'], $comptes)));
        $dossiers = [];
        if ($sids) {
            $place = implode(',', array_fill(0, count($sids), '?'));
            $st = $dtb->prepare("SELECT student_id, image_student, etude_envisage, annee_etude
                                   FROM tbl_2024_etudiant WHERE student_id IN ($place) ORDER BY remove DESC, annee_scolaire ASC");
            $st->execute($sids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $dossiers[(string) $d['student_id']] = $d;   // la plus recente gagne
            }
        }

        $racine = dirname(__DIR__);
        foreach ($comptes as $c) {
            $avatar = null;
            $photo = trim((string) $c['photos']);
            if ($photo !== '' && preg_match('#^https://#i', $photo)) {
                $avatar = $photo;
            } elseif ($photo !== '' && !str_contains($photo, '..') && is_file($racine . '/app/photosuser/' . $photo)) {
                $avatar = $appBase . '/app/photosuser/' . rawurlencode($photo) . '?v=' . @filemtime($racine . '/app/photosuser/' . $photo);
            }
            $etudiant = (int) $c['level'] === 8;
            $dossier = $dossiers[(string) $c['student_id']] ?? null;
            if ($avatar === null && $dossier && ($img = trim((string) $dossier['image_student'])) !== ''
                && !str_contains($img, '..') && is_file($racine . '/app/photosetudiants/' . $img)) {
                $avatar = $appBase . '/app/photosetudiants/' . rawurlencode($img);
            }
            if ($etudiant) {
                $role = trim('Étudiant' . ($dossier ? ' · ' . trim($dossier['etude_envisage'] . ' ' . msgNiveauCourt($dossier['annee_etude'])) : ''), ' ·');
            } else {
                $poste = trim((string) $c['post']);
                $role = $poste !== '' ? $poste : (defined('ROLE_LABELS') ? (ROLE_LABELS[(int) $c['level']] ?? '') : '');
                if ((int) $c['level'] <= 2 && $poste === '') { $role = 'Administration'; }
            }
            $cache[(int) $c['id']] = [
                'id'        => (int) $c['id'],
                'nom'       => msgNomAffiche($c),
                'initiales' => msgInitiales($c),
                'avatar'    => $avatar,
                'role'      => $role,
                'actif'     => (int) $c['etat'] === 1,
                'etudiant'  => $etudiant,
            ];
        }
        foreach ($manquants as $id) {
            if (!isset($cache[$id])) {
                $cache[$id] = ['id' => $id, 'nom' => 'Compte supprimé', 'initiales' => '?', 'avatar' => null,
                               'role' => '', 'actif' => false, 'etudiant' => false];
            }
        }
    }
    $r = [];
    foreach ($ids as $id) {
        $r[(int) $id] = $cache[(int) $id];
    }
    return $r;
}

/**
 * Comptes actifs a qui ecrire : nom, prenom, pseudo ou matricule.
 * Tous les mots doivent se trouver, dans n'importe quel ordre.
 */
function msgChercherComptes(PDO $dtb, array $moi, string $q, string $appBase, int $limite = 20): array
{
    $mots = array_slice(array_filter(preg_split('~\s+~u', trim($q))), 0, 5);
    $where = ['etat = 1', 'id <> ?'];
    $params = [(int) $moi['id']];
    foreach ($mots as $m) {
        $where[] = "CONCAT_WS(' ', nom, prenom, pseudo, COALESCE(student_id, ''), post) LIKE ?";
        $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $m) . '%';
    }
    $st = $dtb->prepare('SELECT id FROM compt_utilisateur WHERE ' . implode(' AND ', $where)
        . ' ORDER BY (level = 8), prenom, nom LIMIT ' . (int) $limite);
    $st->execute($params);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    return array_values(msgProfils($dtb, $ids, $appBase));
}

/** L'un bloque-t-il l'autre ? 'moi' : j'ai bloque ; 'lui' : il m'a bloque ; null sinon. */
function msgBlocage(PDO $dtb, int $moi, int $autre): ?string
{
    $st = $dtb->prepare('SELECT user_id FROM msg_blocage WHERE (user_id = ? AND bloque_id = ?) OR (user_id = ? AND bloque_id = ?)');
    $st->execute([$moi, $autre, $autre, $moi]);
    $qui = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (in_array($moi, $qui, true)) { return 'moi'; }
    if (in_array($autre, $qui, true)) { return 'lui'; }
    return null;
}

/** Personnes que j'ai bloquees. @return int[] */
function msgBloques(PDO $dtb, int $moi): array
{
    $st = $dtb->prepare('SELECT bloque_id FROM msg_blocage WHERE user_id = ? ORDER BY le DESC');
    $st->execute([$moi]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Session en lecture seule, pour les points d'entree de la messagerie.
 *
 * Le navigateur interroge le serveur toutes les deux secondes. Une session
 * ouverte normalement verrouille son fichier pendant toute la requete (les
 * autres onglets attendent), s'ecrit a la fin, et regenere son identifiant
 * toutes les 30 minutes (Middleware::startSecureSession) : une regeneration
 * pendant qu'une autre requete est en vol la deconnecte. Lue puis refermee
 * aussitot, la session ne bloque ni ne change rien. Memes reglages de cookie
 * que le middleware.
 */
function msgSessionLecture(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.gc_maxlifetime', defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 36000);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        ini_set('session.cookie_secure', 1);
    }
    session_start(['read_and_close' => true]);
}

/**
 * Entretien de la session, demande par le navigateur toutes les 5 minutes.
 *
 * En production, une session dont le fichier n'a pas ete touche depuis
 * session.gc_maxlifetime (24 minutes dans php.ini, nettoyage par
 * phpsessionclean) est effacee. Lue en seule lecture, elle n'est jamais
 * touchee : quelqu'un qui ne fait que discuter serait deconnecte. Une
 * ouverture normale suivie d'une fermeture met a jour son horodatage, sans
 * regenerer l'identifiant.
 */
function msgSessionEntretenir(): void
{
    if (session_status() === PHP_SESSION_NONE && @session_start()) {
        session_write_close();
    }
}

/* -----------------------------------------------------------------------------
   Erreur metier : un message pour l'utilisateur, un code HTTP
   -------------------------------------------------------------------------- */

class MessagerieRefus extends RuntimeException
{
    public int $statut;

    public function __construct(string $message, int $statut = 400)
    {
        parent::__construct($message);
        $this->statut = $statut;
    }
}

/* -----------------------------------------------------------------------------
   Discussions et membres
   -------------------------------------------------------------------------- */

/** Ma ligne de membre, ou null si je n'ai jamais ete dans cette discussion. */
function msgParticipation(PDO $dtb, int $conversation, int $moi): ?array
{
    $st = $dtb->prepare('SELECT p.*, c.type, c.nom AS conversation_nom, c.cle_directe, c.dernier_message_id, c.cree_par
                           FROM msg_participant p JOIN msg_conversation c ON c.id = p.conversation_id
                          WHERE p.conversation_id = ? AND p.user_id = ?');
    $st->execute([$conversation, $moi]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Membre actuel, sinon refus. */
function msgExigerMembre(PDO $dtb, int $conversation, int $moi): array
{
    $p = msgParticipation($dtb, $conversation, $moi);
    if (!$p) {
        throw new MessagerieRefus('Discussion introuvable.', 404);
    }
    if ($p['fin_message_id'] !== null) {
        throw new MessagerieRefus('Vous ne faites plus partie de ce groupe.', 403);
    }
    return $p;
}

/** Le message d'id donne est-il visible pour ce membre ? */
function msgVisible(array $p, int $messageId): bool
{
    return $messageId > (int) $p['debut_message_id']
        && $messageId > (int) $p['efface_jusqu_a']
        && ($p['fin_message_id'] === null || $messageId <= (int) $p['fin_message_id']);
}

/** Membres actuels d'une discussion. @return int[] */
function msgMembres(PDO $dtb, int $conversation): array
{
    $st = $dtb->prepare('SELECT user_id FROM msg_participant WHERE conversation_id = ? AND fin_message_id IS NULL ORDER BY rejoint_le, user_id');
    $st->execute([$conversation]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Ajoute une ligne au journal des changements. */
function msgEvenement(PDO $dtb, int $conversation, string $type, ?int $message = null, ?int $user = null): void
{
    $dtb->prepare('INSERT INTO msg_evenement (conversation_id, type, message_id, user_id, le) VALUES (?, ?, ?, ?, NOW(3))')
        ->execute([$conversation, $type, $message, $user]);
}

/**
 * La discussion a deux entre moi et l'autre, creee au besoin.
 * La cle unique « petit-grand » empeche d'en creer deux en meme temps.
 */
function msgConversationDirecte(PDO $dtb, array $moi, int $autreId): int
{
    $moiId = (int) $moi['id'];
    if ($autreId === $moiId) {
        throw new MessagerieRefus('Choisissez une autre personne.');
    }
    $autre = msgCompte($dtb, $autreId);
    if (!$autre) {
        throw new MessagerieRefus('Ce compte n\'existe pas.', 404);
    }
    $cle = min($moiId, $autreId) . '-' . max($moiId, $autreId);
    $st = $dtb->prepare('SELECT id FROM msg_conversation WHERE cle_directe = ?');
    $st->execute([$cle]);
    $id = (int) $st->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    if ((int) $autre['etat'] !== 1) {
        throw new MessagerieRefus('Ce compte est désactivé : il ne reçoit plus de messages.');
    }
    $dtb->beginTransaction();
    try {
        $dtb->prepare('INSERT IGNORE INTO msg_conversation (type, cle_directe, cree_par, cree_le) VALUES (\'direct\', ?, ?, NOW())')
            ->execute([$cle, $moiId]);
        $st->execute([$cle]);
        $id = (int) $st->fetchColumn();
        $ajout = $dtb->prepare('INSERT IGNORE INTO msg_participant (conversation_id, user_id, role, rejoint_le) VALUES (?, ?, \'membre\', NOW())');
        $ajout->execute([$id, $moiId]);
        $ajout->execute([$id, $autreId]);
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    return $id;
}

/** Dernier message d'une discussion (0 si vide). */
function msgDernierId(PDO $dtb, int $conversation): int
{
    $st = $dtb->prepare('SELECT COALESCE(MAX(id), 0) FROM msg_message WHERE conversation_id = ?');
    $st->execute([$conversation]);
    return (int) $st->fetchColumn();
}

/** Insere un message du systeme (« X a ajoute Y ») et met la discussion a jour. */
function msgMessageSysteme(PDO $dtb, int $conversation, int $auteur, string $code, array $donnees = []): int
{
    $dtb->prepare('INSERT INTO msg_message (conversation_id, auteur_id, type, systeme, donnees, envoye_le)
                   VALUES (?, ?, \'systeme\', ?, ?, NOW(3))')
        ->execute([$conversation, $auteur, $code, $donnees ? json_encode($donnees, JSON_UNESCAPED_UNICODE) : null]);
    $id = (int) $dtb->lastInsertId();
    $dtb->prepare('UPDATE msg_conversation SET dernier_message_id = ?, dernier_le = NOW(3) WHERE id = ?')->execute([$id, $conversation]);
    $dtb->prepare('UPDATE msg_participant SET lu_jusqu_a = GREATEST(lu_jusqu_a, ?) WHERE conversation_id = ? AND user_id = ?')
        ->execute([$id, $conversation, $auteur]);
    msgEvenement($dtb, $conversation, 'message', $id, $auteur);
    return $id;
}

/** Comptes actifs parmi des identifiants proposes. @return int[] */
function msgComptesActifs(PDO $dtb, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)));
    if (!$ids) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = $dtb->prepare("SELECT id FROM compt_utilisateur WHERE etat = 1 AND id IN ($place)");
    $st->execute($ids);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function msgNomGroupe(string $nom): string
{
    $nom = trim((string) preg_replace('~\s+~u', ' ', $nom));
    if (mb_strlen($nom) > 100) {
        throw new MessagerieRefus('Le nom du groupe ne dépasse pas 100 caractères.');
    }
    return $nom;
}

/** Nouveau groupe : son createur en est administrateur. */
function msgCreerGroupe(PDO $dtb, array $moi, string $nom, array $membres): int
{
    if (!msgPeutCreerGroupe($moi)) {
        throw new MessagerieRefus('Seuls le personnel et les enseignants créent des groupes.', 403);
    }
    $nom = msgNomGroupe($nom);
    $moiId = (int) $moi['id'];
    $membres = array_values(array_diff(msgComptesActifs($dtb, $membres), [$moiId]));
    if (count($membres) < 1) {
        throw new MessagerieRefus('Ajoutez au moins une personne au groupe.');
    }
    if (count($membres) + 1 > MSG_GROUPE_MAX) {
        throw new MessagerieRefus('Un groupe compte au plus ' . MSG_GROUPE_MAX . ' membres.');
    }
    $dtb->beginTransaction();
    try {
        $dtb->prepare('INSERT INTO msg_conversation (type, nom, cree_par, cree_le) VALUES (\'groupe\', ?, ?, NOW())')
            ->execute([$nom !== '' ? $nom : null, $moiId]);
        $id = (int) $dtb->lastInsertId();
        $ajout = $dtb->prepare('INSERT INTO msg_participant (conversation_id, user_id, role, ajoute_par, rejoint_le) VALUES (?, ?, ?, ?, NOW())');
        $ajout->execute([$id, $moiId, 'admin', null]);
        foreach ($membres as $m) {
            $ajout->execute([$id, $m, 'membre', $moiId]);
        }
        msgMessageSysteme($dtb, $id, $moiId, 'groupe_cree', ['nom' => $nom]);
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    return $id;
}

/** Groupe dont je suis administrateur, sinon refus. */
function msgExigerAdmin(PDO $dtb, int $conversation, int $moi): array
{
    $p = msgExigerMembre($dtb, $conversation, $moi);
    if ($p['type'] !== 'groupe') {
        throw new MessagerieRefus('Ce n\'est pas un groupe.');
    }
    if ($p['role'] !== 'admin') {
        throw new MessagerieRefus('Seuls les administrateurs du groupe peuvent le faire.', 403);
    }
    return $p;
}

function msgRenommerGroupe(PDO $dtb, array $moi, int $conversation, string $nom): void
{
    msgExigerAdmin($dtb, $conversation, (int) $moi['id']);
    $nom = msgNomGroupe($nom);
    $dtb->beginTransaction();
    try {
        $dtb->prepare('UPDATE msg_conversation SET nom = ? WHERE id = ?')->execute([$nom !== '' ? $nom : null, $conversation]);
        msgMessageSysteme($dtb, $conversation, (int) $moi['id'], 'groupe_renomme', ['nom' => $nom]);
        msgEvenement($dtb, $conversation, 'conversation');
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
}

function msgAjouterMembres(PDO $dtb, array $moi, int $conversation, array $ids): array
{
    msgExigerAdmin($dtb, $conversation, (int) $moi['id']);
    $actuels = msgMembres($dtb, $conversation);
    $nouveaux = array_values(array_diff(msgComptesActifs($dtb, $ids), $actuels));
    if (!$nouveaux) {
        throw new MessagerieRefus('Ces personnes font déjà partie du groupe.');
    }
    if (count($actuels) + count($nouveaux) > MSG_GROUPE_MAX) {
        throw new MessagerieRefus('Un groupe compte au plus ' . MSG_GROUPE_MAX . ' membres.');
    }
    $dtb->beginTransaction();
    try {
        /* Un ancien membre revient : il ne voit que la suite, comme un nouveau. */
        $debut = msgDernierId($dtb, $conversation);
        $ajout = $dtb->prepare('INSERT INTO msg_participant (conversation_id, user_id, role, ajoute_par, rejoint_le, debut_message_id, lu_jusqu_a)
                                VALUES (?, ?, \'membre\', ?, NOW(), ?, ?)
                                ON DUPLICATE KEY UPDATE role = \'membre\', ajoute_par = VALUES(ajoute_par), rejoint_le = NOW(),
                                    debut_message_id = VALUES(debut_message_id), fin_message_id = NULL,
                                    lu_jusqu_a = VALUES(lu_jusqu_a), efface_jusqu_a = 0, ecrit_le = NULL');
        foreach ($nouveaux as $n) {
            $ajout->execute([$conversation, $n, (int) $moi['id'], $debut, $debut]);
        }
        msgMessageSysteme($dtb, $conversation, (int) $moi['id'], 'membres_ajoutes', ['ids' => $nouveaux]);
        msgEvenement($dtb, $conversation, 'conversation');
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    return $nouveaux;
}

/**
 * Fin de participation (retire par un administrateur, ou parti).
 * Le message du systeme est le dernier que la personne voit.
 */
function msgTerminerParticipation(PDO $dtb, int $conversation, int $qui, int $auteur, string $code): void
{
    $id = msgMessageSysteme($dtb, $conversation, $auteur, $code, ['id' => $qui]);
    $dtb->prepare('UPDATE msg_participant SET fin_message_id = ?, role = \'membre\', ecrit_le = NULL WHERE conversation_id = ? AND user_id = ?')
        ->execute([$id, $conversation, $qui]);
    /* Plus d'administrateur : le plus ancien membre le devient. */
    $st = $dtb->prepare('SELECT COUNT(*) FROM msg_participant WHERE conversation_id = ? AND fin_message_id IS NULL AND role = \'admin\'');
    $st->execute([$conversation]);
    if ((int) $st->fetchColumn() === 0) {
        $dtb->prepare('UPDATE msg_participant SET role = \'admin\' WHERE conversation_id = ? AND fin_message_id IS NULL
                       ORDER BY rejoint_le, user_id LIMIT 1')->execute([$conversation]);
    }
    msgEvenement($dtb, $conversation, 'conversation', null, $qui);
}

function msgExclureMembre(PDO $dtb, array $moi, int $conversation, int $qui): void
{
    msgExigerAdmin($dtb, $conversation, (int) $moi['id']);
    if ($qui === (int) $moi['id']) {
        throw new MessagerieRefus('Pour partir, utilisez « Quitter le groupe ».');
    }
    if (!in_array($qui, msgMembres($dtb, $conversation), true)) {
        throw new MessagerieRefus('Cette personne ne fait pas partie du groupe.');
    }
    $dtb->beginTransaction();
    try {
        msgTerminerParticipation($dtb, $conversation, $qui, (int) $moi['id'], 'membre_retire');
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
}

function msgQuitterGroupe(PDO $dtb, array $moi, int $conversation): void
{
    $p = msgExigerMembre($dtb, $conversation, (int) $moi['id']);
    if ($p['type'] !== 'groupe') {
        throw new MessagerieRefus('On ne quitte qu\'un groupe.');
    }
    $dtb->beginTransaction();
    try {
        msgTerminerParticipation($dtb, $conversation, (int) $moi['id'], (int) $moi['id'], 'membre_parti');
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
}

function msgChangerRole(PDO $dtb, array $moi, int $conversation, int $qui, string $role): void
{
    msgExigerAdmin($dtb, $conversation, (int) $moi['id']);
    if (!in_array($role, ['admin', 'membre'], true)) {
        throw new MessagerieRefus('Rôle inconnu.');
    }
    if (!in_array($qui, msgMembres($dtb, $conversation), true)) {
        throw new MessagerieRefus('Cette personne ne fait pas partie du groupe.');
    }
    if ($role === 'membre') {
        $st = $dtb->prepare('SELECT COUNT(*) FROM msg_participant WHERE conversation_id = ? AND fin_message_id IS NULL AND role = \'admin\' AND user_id <> ?');
        $st->execute([$conversation, $qui]);
        if ((int) $st->fetchColumn() === 0) {
            throw new MessagerieRefus('Le groupe doit garder au moins un administrateur.');
        }
    }
    $dtb->prepare('UPDATE msg_participant SET role = ? WHERE conversation_id = ? AND user_id = ?')->execute([$role, $conversation, $qui]);
    msgEvenement($dtb, $conversation, 'conversation');
}

function msgSourdine(PDO $dtb, array $moi, int $conversation, bool $active): void
{
    msgExigerMembre($dtb, $conversation, (int) $moi['id']);
    $dtb->prepare('UPDATE msg_participant SET sourdine = ? WHERE conversation_id = ? AND user_id = ?')
        ->execute([$active ? 1 : 0, $conversation, (int) $moi['id']]);
}

/** Supprime la discussion pour moi : elle reapparait au prochain message. */
function msgEffacerPourMoi(PDO $dtb, array $moi, int $conversation): void
{
    $p = msgParticipation($dtb, $conversation, (int) $moi['id']);
    if (!$p) {
        throw new MessagerieRefus('Discussion introuvable.', 404);
    }
    $jusqua = $p['fin_message_id'] !== null ? (int) $p['fin_message_id'] : (int) $p['dernier_message_id'];
    $dtb->prepare('UPDATE msg_participant SET efface_jusqu_a = ?, lu_jusqu_a = GREATEST(lu_jusqu_a, ?) WHERE conversation_id = ? AND user_id = ?')
        ->execute([$jusqua, $jusqua, $conversation, (int) $moi['id']]);
}

/* -----------------------------------------------------------------------------
   Messages
   -------------------------------------------------------------------------- */

/**
 * Qui lit, et sous quel chemin de base : pose une fois par le point d'entree.
 * Les messages du systeme s'ecrivent du point de vue du lecteur (« Vous avez
 * ajoute… »), d'ou ce contexte dans msgSerialiser.
 */
function msgContexte(?int $moi = null, ?string $base = null): array
{
    static $ctx = ['moi' => 0, 'base' => ''];
    if ($moi !== null) { $ctx['moi'] = $moi; }
    if ($base !== null) { $ctx['base'] = $base; }
    return $ctx;
}

function msgIso(?string $date): ?string
{
    if ($date === null || $date === '') {
        return null;
    }
    try {
        return (new DateTime($date))->format('Y-m-d\TH:i:s.vP');
    } catch (Throwable $e) {
        return null;
    }
}

/** Texte court d'un message, pour la liste des discussions et les notifications. */
function msgApercu(array $m): string
{
    if ($m['retire_le'] !== null) {
        return 'Message retiré';
    }
    $texte = trim((string) $m['texte']);
    $court = $texte !== '' ? mb_substr(preg_replace('~\s+~u', ' ', $texte), 0, 90) : '';
    switch ($m['type']) {
        case 'image':   return $court !== '' ? '📷 ' . $court : '📷 Photo';
        case 'video':   return $court !== '' ? '🎬 ' . $court : '🎬 Vidéo';
        case 'audio':   return '🎤 Message vocal';
        case 'fichier': return '📎 ' . ($m['fichier_nom'] ?? 'Fichier');
        case 'systeme': return '';
    }
    return $court;
}

/**
 * Messages prets a envoyer au navigateur, dans l'ordre des ids.
 * Un message retire n'emporte ni son texte ni son fichier.
 *
 * @param array $lignes lignes de msg_message
 */
function msgSerialiser(PDO $dtb, array $lignes): array
{
    if (!$lignes) {
        return [];
    }
    $ids = array_map(fn ($m) => (int) $m['id'], $lignes);
    $fichiers = array_filter(array_map(fn ($m) => (int) $m['fichier_id'], $lignes));
    $citations = array_filter(array_map(fn ($m) => (int) $m['reponse_a'], $lignes));

    $parFichier = [];
    if ($fichiers) {
        $place = implode(',', array_fill(0, count($fichiers), '?'));
        $st = $dtb->prepare("SELECT id, nom, mime, genre, taille, largeur, hauteur, duree_ms, apercu FROM msg_fichier WHERE id IN ($place)");
        $st->execute(array_values($fichiers));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $parFichier[(int) $f['id']] = $f;
        }
    }

    $parCitation = [];
    if ($citations) {
        $place = implode(',', array_fill(0, count($citations), '?'));
        $st = $dtb->prepare("SELECT m.id, m.auteur_id, m.type, m.texte, m.retire_le, f.nom AS fichier_nom
                               FROM msg_message m LEFT JOIN msg_fichier f ON f.id = m.fichier_id WHERE m.id IN ($place)");
        $st->execute(array_values($citations));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $parCitation[(int) $c['id']] = [
                'id' => (int) $c['id'], 'auteur_id' => $c['auteur_id'] !== null ? (int) $c['auteur_id'] : null,
                'type' => $c['type'], 'apercu' => msgApercu($c), 'retire' => $c['retire_le'] !== null,
            ];
        }
    }

    $reactions = [];
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = $dtb->prepare("SELECT message_id, user_id, emoji FROM msg_reaction WHERE message_id IN ($place) ORDER BY le");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $reactions[(int) $r['message_id']][] = ['user_id' => (int) $r['user_id'], 'emoji' => $r['emoji']];
    }

    $ctx = msgContexte();
    $sortie = [];
    foreach ($lignes as $m) {
        $retire = $m['retire_le'] !== null;
        $f = (!$retire && $m['fichier_id']) ? ($parFichier[(int) $m['fichier_id']] ?? null) : null;
        $texte = $retire ? null : $m['texte'];
        if ($m['type'] === 'systeme') {
            $texte = msgTexteSysteme($dtb, $m, $ctx['moi'], $ctx['base']);
        }
        $sortie[] = [
            'id'              => (int) $m['id'],
            'conversation_id' => (int) $m['conversation_id'],
            'auteur_id'       => $m['auteur_id'] !== null ? (int) $m['auteur_id'] : null,
            'type'            => $m['type'],
            'texte'           => $texte,
            'fichier'         => $f ? [
                'id' => (int) $f['id'], 'nom' => $f['nom'], 'mime' => $f['mime'], 'genre' => $f['genre'],
                'taille' => (int) $f['taille'], 'largeur' => $f['largeur'] !== null ? (int) $f['largeur'] : null,
                'hauteur' => $f['hauteur'] !== null ? (int) $f['hauteur'] : null,
                'duree_ms' => $f['duree_ms'] !== null ? (int) $f['duree_ms'] : null, 'apercu' => (bool) $f['apercu'],
            ] : null,
            'reponse'         => (!$retire && $m['reponse_a']) ? ($parCitation[(int) $m['reponse_a']] ?? null) : null,
            'reactions'       => $retire ? [] : ($reactions[(int) $m['id']] ?? []),
            'systeme'         => $m['systeme'],
            'donnees'         => $m['donnees'] !== null ? json_decode($m['donnees'], true) : null,
            'envoye_le'       => msgIso($m['envoye_le']),
            'retire'          => $retire,
            'cle_client'      => $m['cle_client'],
        ];
    }
    return $sortie;
}

/** Une page de messages visibles, du plus ancien au plus recent. */
function msgMessages(PDO $dtb, array $moi, int $conversation, ?int $avant = null, int $limite = MSG_PAR_PAGE): array
{
    $p = msgParticipation($dtb, $conversation, (int) $moi['id']);
    if (!$p) {
        throw new MessagerieRefus('Discussion introuvable.', 404);
    }
    $plancher = max((int) $p['debut_message_id'], (int) $p['efface_jusqu_a']);
    $plafond = $p['fin_message_id'] !== null ? (int) $p['fin_message_id'] : PHP_INT_MAX;
    if ($avant !== null) {
        $plafond = min($plafond, $avant - 1);
    }
    $st = $dtb->prepare('SELECT * FROM msg_message WHERE conversation_id = ? AND id > ? AND id <= ? ORDER BY id DESC LIMIT ' . ($limite + 1));
    $st->execute([$conversation, $plancher, min($plafond, 2147483647)]);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
    $encore = count($lignes) > $limite;
    $lignes = array_reverse(array_slice($lignes, 0, $limite));
    $messages = msgSerialiser($dtb, $lignes);
    return ['messages' => $messages, 'encore' => $encore, 'profils' => msgProfilsAuteurs($dtb, $messages)];
}

/**
 * Profils des auteurs de ces messages (et des messages cites) : un ancien
 * membre d'un groupe n'est plus dans la liste des membres, mais ses messages
 * gardent son nom et son visage.
 */
function msgProfilsAuteurs(PDO $dtb, array $messages, array $autres = []): object
{
    $ids = $autres;
    foreach ($messages as $m) {
        if ($m['auteur_id'] !== null) { $ids[] = $m['auteur_id']; }
        if (!empty($m['reponse']['auteur_id'])) { $ids[] = $m['reponse']['auteur_id']; }
    }
    return (object) ($ids ? msgProfils($dtb, $ids, msgContexte()['base']) : []);
}

/** Message visible de moi, sinon refus. */
function msgMessageVisible(PDO $dtb, array $moi, int $messageId): array
{
    $st = $dtb->prepare('SELECT * FROM msg_message WHERE id = ?');
    $st->execute([$messageId]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        throw new MessagerieRefus('Message introuvable.', 404);
    }
    $p = msgParticipation($dtb, (int) $m['conversation_id'], (int) $moi['id']);
    if (!$p || !msgVisible($p, (int) $m['id'])) {
        throw new MessagerieRefus('Message introuvable.', 404);
    }
    return [$m, $p];
}

/**
 * Envoie un message (texte, fichier, ou les deux) et le renvoie tel que les
 * membres le recevront. $destinataire ouvre au besoin la discussion a deux.
 */
function msgEnvoyer(PDO $dtb, array $moi, ?int $conversation, ?int $destinataire, string $texte,
                    ?int $fichierId, ?int $reponseA, ?string $cleClient, ?string $onde = null): array
{
    $moiId = (int) $moi['id'];
    $texte = rtrim(str_replace("\r\n", "\n", $texte));
    $texte = ltrim($texte, "\n");
    if (mb_strlen($texte) > MSG_TEXTE_MAX) {
        throw new MessagerieRefus('Un message compte au plus ' . MSG_TEXTE_MAX . ' caractères.');
    }
    if (trim($texte) === '' && !$fichierId) {
        throw new MessagerieRefus('Le message est vide.');
    }
    if ($cleClient !== null && !preg_match('~^[a-zA-Z0-9-]{8,36}$~', $cleClient)) {
        $cleClient = null;
    }

    /* Le meme envoi, repete apres une coupure : on rend le message deja cree. */
    if ($cleClient !== null) {
        $st = $dtb->prepare('SELECT * FROM msg_message WHERE auteur_id = ? AND cle_client = ?');
        $st->execute([$moiId, $cleClient]);
        if ($deja = $st->fetch(PDO::FETCH_ASSOC)) {
            return msgSerialiser($dtb, [$deja])[0];
        }
    }

    if (!$conversation && $destinataire) {
        $conversation = msgConversationDirecte($dtb, $moi, $destinataire);
    }
    if (!$conversation) {
        throw new MessagerieRefus('Choisissez à qui écrire.');
    }
    $p = msgExigerMembre($dtb, $conversation, $moiId);

    if ($p['type'] === 'direct') {
        $autre = array_values(array_diff(array_map('intval', explode('-', (string) $p['cle_directe'])), [$moiId]))[0] ?? 0;
        $bloc = msgBlocage($dtb, $moiId, $autre);
        if ($bloc === 'moi') {
            throw new MessagerieRefus('Vous avez bloqué cette personne. Débloquez-la pour lui écrire.', 403);
        }
        if ($bloc === 'lui') {
            throw new MessagerieRefus('Vous ne pouvez pas répondre à cette discussion.', 403);
        }
        $compte = msgCompte($dtb, $autre);
        if (!$compte || (int) $compte['etat'] !== 1) {
            throw new MessagerieRefus('Ce compte est désactivé : il ne reçoit plus de messages.');
        }
    }

    $st = $dtb->prepare('SELECT COUNT(*) FROM msg_message WHERE auteur_id = ? AND envoye_le > NOW(3) - INTERVAL 1 MINUTE');
    $st->execute([$moiId]);
    if ((int) $st->fetchColumn() >= MSG_RYTHME) {
        throw new MessagerieRefus('Trop de messages en une minute. Patientez un instant.', 429);
    }

    $type = 'texte';
    if ($fichierId) {
        $st = $dtb->prepare('SELECT * FROM msg_fichier WHERE id = ? AND proprietaire_id = ?');
        $st->execute([$fichierId, $moiId]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        if (!$f || $f['statut'] !== 'pret') {
            throw new MessagerieRefus('Le fichier n\'est pas encore arrivé.');
        }
        $st = $dtb->prepare('SELECT 1 FROM msg_message WHERE fichier_id = ? LIMIT 1');
        $st->execute([$fichierId]);
        if ($st->fetchColumn()) {
            throw new MessagerieRefus('Ce fichier a déjà été envoyé.');
        }
        $type = $f['genre'];
    }

    if ($reponseA) {
        $st = $dtb->prepare('SELECT conversation_id FROM msg_message WHERE id = ?');
        $st->execute([$reponseA]);
        if ((int) $st->fetchColumn() !== $conversation || !msgVisible($p, $reponseA)) {
            $reponseA = null;
        }
    }

    /* Onde d'un vocal, mesuree par le navigateur pendant l'enregistrement :
       une hauteur de 0 a 9 par barre, pour dessiner le message sans le decoder. */
    $donnees = null;
    if ($type === 'audio' && $onde !== null && preg_match('~^[0-9]{8,64}$~', $onde)) {
        $donnees = json_encode(['onde' => $onde]);
    }

    $id = msgInsererMessage($dtb, $conversation, $moiId, $type, trim($texte) !== '' ? $texte : null,
        $fichierId ?: null, $reponseA ?: null, $donnees, $cleClient);
    $st = $dtb->prepare('SELECT * FROM msg_message WHERE id = ?');
    $st->execute([$id]);
    return msgSerialiser($dtb, [$st->fetch(PDO::FETCH_ASSOC)])[0];
}

/**
 * Ecrit un message deja verifie : la ligne, le dernier message de la
 * discussion, la lecture de l'auteur et l'evenement, ensemble ou pas du tout.
 */
function msgInsererMessage(PDO $dtb, int $conversation, int $auteur, string $type, ?string $texte,
                           ?int $fichierId, ?int $reponseA, ?string $donnees, ?string $cleClient): int
{
    $dtb->beginTransaction();
    try {
        $dtb->prepare('INSERT INTO msg_message (conversation_id, auteur_id, type, texte, fichier_id, reponse_a, donnees, envoye_le, cle_client)
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(3), ?)')
            ->execute([$conversation, $auteur, $type, $texte, $fichierId, $reponseA, $donnees, $cleClient]);
        $id = (int) $dtb->lastInsertId();
        $dtb->prepare('UPDATE msg_conversation SET dernier_message_id = ?, dernier_le = NOW(3) WHERE id = ?')->execute([$id, $conversation]);
        $dtb->prepare('UPDATE msg_participant SET lu_jusqu_a = ?, ecrit_le = NULL WHERE conversation_id = ? AND user_id = ?')
            ->execute([$id, $conversation, $auteur]);
        msgEvenement($dtb, $conversation, 'message', $id, $auteur);
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    /* Les membres qui n'ont pas la messagerie sous les yeux recoivent une
       notification, apres la reponse a l'expediteur (data/push.php). */
    if ($type !== 'systeme') {
        pushMessageApres($dtb, $id, msgContexte()['base']);
    }
    return $id;
}

/* -----------------------------------------------------------------------------
   Diffusion : le meme message a beaucoup de monde (superadmin)
   -------------------------------------------------------------------------- */

define('MSG_DIFFUSION_CIBLES', [
    'tous'      => '1 = 1',
    'personnel' => 'level <> 8',   // personnel et enseignants
    'etudiants' => 'level = 8',
]);

function msgPeutDiffuser(array $u): bool
{
    return msgEstModerateur($u);
}

/** Comptes actifs vises, sans l'expediteur. @return int[] */
function msgDiffusionComptes(PDO $dtb, array $moi, string $cible): array
{
    $condition = MSG_DIFFUSION_CIBLES[$cible] ?? null;
    if ($condition === null) {
        throw new MessagerieRefus('Destinataires inconnus.');
    }
    $st = $dtb->prepare("SELECT id FROM compt_utilisateur WHERE etat = 1 AND id <> ? AND $condition ORDER BY id");
    $st->execute([(int) $moi['id']]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Envoie le meme message a chaque compte vise, dans sa discussion a deux avec
 * l'expediteur : les reponses ne reviennent qu'a lui, comme une liste de
 * diffusion. Les personnes qui ont bloque l'expediteur (ou qu'il a bloquees)
 * ne le recoivent pas. La cle du navigateur, declinee par destinataire, rend
 * l'envoi repetable sans doublon apres une coupure.
 *
 * @return array{envoyes:int, ignores:int}
 */
function msgDiffuser(PDO $dtb, array $moi, string $cible, string $texte, string $cle): array
{
    if (!msgPeutDiffuser($moi)) {
        throw new MessagerieRefus('Réservé à l\'administration de l\'application.', 403);
    }
    $texte = trim(str_replace("\r\n", "\n", $texte));
    if ($texte === '') {
        throw new MessagerieRefus('Le message est vide.');
    }
    if (mb_strlen($texte) > MSG_TEXTE_MAX) {
        throw new MessagerieRefus('Un message compte au plus ' . MSG_TEXTE_MAX . ' caractères.');
    }
    if (!preg_match('~^[a-zA-Z0-9-]{8,36}$~', $cle)) {
        throw new MessagerieRefus('Envoi invalide : rechargez la page.');
    }
    @set_time_limit(180);
    $moiId = (int) $moi['id'];
    $deja = $dtb->prepare('SELECT 1 FROM msg_message WHERE auteur_id = ? AND cle_client = ?');
    $envoyes = 0;
    $ignores = 0;
    foreach (msgDiffusionComptes($dtb, $moi, $cible) as $dest) {
        if (msgBlocage($dtb, $moiId, $dest) !== null) {
            $ignores++;
            continue;
        }
        $cleDest = substr(hash('sha256', $cle . ':' . $dest), 0, 32);
        $deja->execute([$moiId, $cleDest]);
        if (!$deja->fetchColumn()) {
            msgInsererMessage($dtb, msgConversationDirecte($dtb, $moi, $dest), $moiId, 'texte', $texte, null, null, null, $cleDest);
        }
        $envoyes++;
    }
    return ['envoyes' => $envoyes, 'ignores' => $ignores];
}

/** Lu jusqu'a ce message : accuse de lecture pour les autres membres. */
function msgMarquerLu(PDO $dtb, array $moi, int $conversation, int $messageId): void
{
    $p = msgParticipation($dtb, $conversation, (int) $moi['id']);
    if (!$p) {
        return;
    }
    $plafond = $p['fin_message_id'] !== null ? (int) $p['fin_message_id'] : (int) $p['dernier_message_id'];
    $cible = min($messageId, $plafond);
    if ($cible <= (int) $p['lu_jusqu_a']) {
        return;
    }
    $dtb->prepare('UPDATE msg_participant SET lu_jusqu_a = ? WHERE conversation_id = ? AND user_id = ? AND lu_jusqu_a < ?')
        ->execute([$cible, $conversation, (int) $moi['id'], $cible]);
    if ($p['fin_message_id'] === null) {
        msgEvenement($dtb, $conversation, 'lu', $cible, (int) $moi['id']);
    }
}

/** « … est en train d'ecrire » : le navigateur l'envoie au plus toutes les 3 s. */
function msgEcrit(PDO $dtb, array $moi, int $conversation, bool $ecrit): void
{
    $dtb->prepare('UPDATE msg_participant SET ecrit_le = ' . ($ecrit ? 'NOW()' : 'NULL')
        . ' WHERE conversation_id = ? AND user_id = ? AND fin_message_id IS NULL')
        ->execute([$conversation, (int) $moi['id']]);
}

/** Une reaction par personne : la meme une seconde fois l'enleve. */
function msgReagir(PDO $dtb, array $moi, int $messageId, string $emoji): void
{
    [$m, $p] = msgMessageVisible($dtb, $moi, $messageId);
    if ($p['fin_message_id'] !== null || $m['retire_le'] !== null || $m['type'] === 'systeme') {
        throw new MessagerieRefus('On ne réagit pas à ce message.');
    }
    $moiId = (int) $moi['id'];
    $st = $dtb->prepare('SELECT emoji FROM msg_reaction WHERE message_id = ? AND user_id = ?');
    $st->execute([$messageId, $moiId]);
    $actuelle = $st->fetchColumn();
    if ($emoji === '' || $emoji === $actuelle) {
        $dtb->prepare('DELETE FROM msg_reaction WHERE message_id = ? AND user_id = ?')->execute([$messageId, $moiId]);
    } elseif (in_array($emoji, MSG_REACTIONS, true)) {
        $dtb->prepare('INSERT INTO msg_reaction (message_id, user_id, emoji, le) VALUES (?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), le = NOW()')->execute([$messageId, $moiId, $emoji]);
    } else {
        throw new MessagerieRefus('Réaction inconnue.');
    }
    msgEvenement($dtb, (int) $m['conversation_id'], 'modif', $messageId, $moiId);
}

/**
 * Retire un message pour tout le monde : son auteur, ou le moderateur.
 * Sans signalement en cours, texte et fichier sont effaces ; signale, le
 * message reste lisible du moderateur seul, comme preuve.
 */
function msgRetirer(PDO $dtb, array $moi, int $messageId, bool $parModerateur = false): array
{
    $st = $dtb->prepare('SELECT * FROM msg_message WHERE id = ?');
    $st->execute([$messageId]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m || $m['type'] === 'systeme') {
        throw new MessagerieRefus('Message introuvable.', 404);
    }
    if ($parModerateur) {
        if (!msgEstModerateur($moi)) {
            throw new MessagerieRefus('Réservé à la modération.', 403);
        }
    } elseif ((int) $m['auteur_id'] !== (int) $moi['id']) {
        throw new MessagerieRefus('Vous ne pouvez retirer que vos propres messages.', 403);
    }
    if ($m['retire_le'] !== null) {
        return $m;
    }
    $st = $dtb->prepare('SELECT COUNT(*) FROM msg_signalement WHERE message_id = ? AND statut = \'ouvert\'');
    $st->execute([$messageId]);
    $preuve = $parModerateur || (int) $st->fetchColumn() > 0;

    $dtb->beginTransaction();
    try {
        $dtb->prepare('UPDATE msg_message SET retire_le = NOW(), retire_par = ?' . ($preuve ? '' : ', texte = NULL') . ' WHERE id = ?')
            ->execute([(int) $moi['id'], $messageId]);
        $dtb->prepare('DELETE FROM msg_reaction WHERE message_id = ?')->execute([$messageId]);
        msgEvenement($dtb, (int) $m['conversation_id'], 'modif', $messageId, (int) $moi['id']);
        if ((int) $m['id'] === msgDernierId($dtb, (int) $m['conversation_id'])) {
            msgEvenement($dtb, (int) $m['conversation_id'], 'conversation');
        }
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    if (!$preuve && $m['fichier_id']) {
        msgFichierEffacer($dtb, (int) $m['fichier_id']);
    }
    return $m;
}

/* -----------------------------------------------------------------------------
   Liste des discussions
   -------------------------------------------------------------------------- */

/**
 * Mes discussions, la plus recente d'abord, avec leur dernier message et le
 * nombre de messages non lus. $ids limite a quelques discussions (mises a
 * jour pendant l'interrogation).
 */
function msgConversations(PDO $dtb, array $moi, string $appBase, ?array $ids = null, int $limite = 200): array
{
    $moiId = (int) $moi['id'];
    $sql = 'SELECT c.id, c.type, c.nom, c.cle_directe, c.dernier_message_id, c.dernier_le, c.cree_par,
                   p.role, p.debut_message_id, p.fin_message_id, p.lu_jusqu_a, p.efface_jusqu_a, p.sourdine
              FROM msg_participant p JOIN msg_conversation c ON c.id = p.conversation_id
             WHERE p.user_id = ? AND c.dernier_message_id IS NOT NULL';
    $params = [$moiId];
    if ($ids !== null) {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $sql .= ' AND c.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = array_merge($params, $ids);
    }
    $sql .= ' ORDER BY c.dernier_le DESC LIMIT ' . (int) $limite;
    $st = $dtb->prepare($sql);
    $st->execute($params);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$lignes) {
        return [];
    }

    /* Dernier message visible de chacune */
    $derniers = [];
    foreach ($lignes as $l) {
        $d = (int) $l['dernier_message_id'];
        if ($l['fin_message_id'] !== null) {
            $d = min($d, (int) $l['fin_message_id']);
        }
        $derniers[(int) $l['id']] = $d;
    }
    $place = implode(',', array_fill(0, count($derniers), '?'));
    $st = $dtb->prepare("SELECT m.*, f.nom AS fichier_nom FROM msg_message m LEFT JOIN msg_fichier f ON f.id = m.fichier_id WHERE m.id IN ($place)");
    $st->execute(array_values($derniers));
    $messages = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $messages[(int) $m['id']] = $m;
    }

    $nonLus = msgNonLusParConversation($dtb, $moiId, array_keys($derniers));

    /* Blocages qui me concernent : la zone de saisie des discussions a deux en depend. */
    $blocages = [];
    $st = $dtb->prepare('SELECT user_id, bloque_id FROM msg_blocage WHERE user_id = ? OR bloque_id = ?');
    $st->execute([$moiId, $moiId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
        if ((int) $b['user_id'] === $moiId) {
            $blocages[(int) $b['bloque_id']] = 'moi';
        } elseif (!isset($blocages[(int) $b['user_id']])) {
            $blocages[(int) $b['user_id']] = 'lui';
        }
    }

    /* Membres, pour les titres et les avatars */
    $convIds = array_keys($derniers);
    $place = implode(',', array_fill(0, count($convIds), '?'));
    $st = $dtb->prepare("SELECT conversation_id, user_id, lu_jusqu_a FROM msg_participant
                          WHERE conversation_id IN ($place) AND fin_message_id IS NULL ORDER BY rejoint_le, user_id");
    $st->execute($convIds);
    $membres = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $membres[(int) $r['conversation_id']][(int) $r['user_id']] = (int) $r['lu_jusqu_a'];
    }
    $tous = [];
    /* Qui a lu mon dernier message, pour la liste (comme Messenger : une
       coche « envoye », puis le visage de ceux qui ont lu). Les ids en
       entier pour suivre les lectures en direct ; trois profils suffisent a
       l'affichage. */
    $vusIds = [];
    foreach ($lignes as $l) {
        $cid = (int) $l['id'];
        $d = $messages[$derniers[$cid]] ?? null;
        if ($d && (int) $d['auteur_id'] === $moiId && $d['type'] !== 'systeme') {
            foreach ($membres[$cid] ?? [] as $uid => $lu) {
                if ($uid !== $moiId && $lu >= (int) $d['id']) { $vusIds[$cid][] = $uid; }
            }
            foreach (array_slice($vusIds[$cid] ?? [], 0, 3) as $u) { $tous[] = $u; }
        }
    }
    foreach ($lignes as $l) {
        if ($l['type'] === 'direct') {
            foreach (explode('-', (string) $l['cle_directe']) as $u) { $tous[] = (int) $u; }
        }
        foreach (array_slice(array_keys($membres[(int) $l['id']] ?? []), 0, 4) as $u) { $tous[] = $u; }
    }
    foreach ($messages as $m) {
        if ($m['auteur_id'] !== null) { $tous[] = (int) $m['auteur_id']; }
    }
    $profils = msgProfils($dtb, $tous, $appBase);

    $sortie = [];
    foreach ($lignes as $l) {
        $cid = (int) $l['id'];
        $dernier = $messages[$derniers[$cid]] ?? null;
        /* Supprimee pour moi et rien de neuf depuis : on ne la montre pas. */
        if ($ids === null && $derniers[$cid] <= (int) $l['efface_jusqu_a']) {
            continue;
        }
        if ($ids === null && $dernier && $derniers[$cid] <= (int) $l['debut_message_id']) {
            continue;
        }
        $autre = null;
        if ($l['type'] === 'direct') {
            $autreId = array_values(array_diff(array_map('intval', explode('-', (string) $l['cle_directe'])), [$moiId]))[0] ?? 0;
            $autre = $profils[$autreId] ?? null;
        }
        $ids4 = array_slice(array_values(array_diff(array_keys($membres[$cid] ?? []), [$moiId])), 0, 3);
        $titre = $l['type'] === 'direct'
            ? ($autre['nom'] ?? 'Discussion')
            : ($l['nom'] !== null && $l['nom'] !== '' ? $l['nom'] : msgTitreGroupe($profils, $ids4, count($membres[$cid] ?? [])));
        $visible = $dernier && $derniers[$cid] > max((int) $l['efface_jusqu_a'], (int) $l['debut_message_id']);
        $sortie[] = [
            'id'        => $cid,
            'type'      => $l['type'],
            'titre'     => $titre,
            'nom'       => $l['nom'],
            'autre'     => $autre,
            'avatars'   => $l['type'] === 'groupe' ? array_values(array_map(fn ($u) => $profils[$u], $ids4)) : [],
            'nb_membres'=> count($membres[$cid] ?? []),
            'role'      => $l['role'],
            'membre'    => $l['fin_message_id'] === null,
            'sourdine'  => (bool) $l['sourdine'],
            'blocage'   => $autre ? ($blocages[$autre['id']] ?? null) : null,
            'non_lus'   => (int) ($nonLus[$cid] ?? 0),
            'lu_jusqu_a'=> (int) $l['lu_jusqu_a'],
            'vus_ids'   => $vusIds[$cid] ?? [],
            'vus'       => array_values(array_map(fn ($u) => $profils[$u], array_slice($vusIds[$cid] ?? [], 0, 3))),
            'dernier'   => $visible ? [
                'id'        => (int) $dernier['id'],
                'auteur_id' => $dernier['auteur_id'] !== null ? (int) $dernier['auteur_id'] : null,
                'auteur'    => $dernier['auteur_id'] !== null ? ($profils[(int) $dernier['auteur_id']]['nom'] ?? '') : '',
                'type'      => $dernier['type'],
                'apercu'    => $dernier['type'] === 'systeme' ? msgTexteSysteme($dtb, $dernier, $moiId, $appBase) : msgApercu($dernier),
                'le'        => msgIso($dernier['envoye_le']),
                'retire'    => $dernier['retire_le'] !== null,
            ] : null,
            'trie_le'   => msgIso($l['dernier_le']),
        ];
    }
    return $sortie;
}

/** « Rasoa, Hery et 3 autres » pour un groupe sans nom. */
function msgTitreGroupe(array $profils, array $ids, int $nb): string
{
    $noms = array_map(fn ($u) => explode(' ', $profils[$u]['nom'] ?? '')[0], array_slice($ids, 0, 2));
    $reste = max(0, $nb - 1 - count($noms));
    if (!$noms) {
        return 'Groupe';
    }
    return implode(', ', $noms) . ($reste > 0 ? ' et ' . $reste . ' autre' . ($reste > 1 ? 's' : '') : '');
}

/** Phrase d'un message du systeme, vue par $moiId. */
function msgTexteSysteme(PDO $dtb, array $m, int $moiId, string $appBase): string
{
    $d = $m['donnees'] !== null ? (json_decode($m['donnees'], true) ?: []) : [];
    $ids = array_filter(array_merge([(int) $m['auteur_id']], array_map('intval', (array) ($d['ids'] ?? [])), isset($d['id']) ? [(int) $d['id']] : []));
    $p = msgProfils($dtb, $ids, $appBase);
    $nom = fn ($u) => $p[(int) $u]['nom'] ?? 'quelqu\'un';
    $parMoi = (int) $m['auteur_id'] === $moiId;
    $qui = $parMoi ? 'Vous' : $nom($m['auteur_id']);
    /* « Vous avez ajouté… », « Rasoa a ajouté… », « Rasoa vous a ajouté… » */
    $a = $parMoi ? 'Vous avez' : $qui . ' a';
    switch ($m['systeme']) {
        case 'groupe_cree':
            return $a . ' créé le groupe' . (!empty($d['nom']) ? ' « ' . $d['nom'] . ' »' : '') . '.';
        case 'groupe_renomme':
            return !empty($d['nom']) ? $a . ' renommé le groupe « ' . $d['nom'] . ' ».' : $a . ' retiré le nom du groupe.';
        case 'membres_ajoutes':
            $ids = array_map('intval', (array) ($d['ids'] ?? []));
            $autres = array_map($nom, array_values(array_diff($ids, [$moiId])));
            if (!$parMoi && in_array($moiId, $ids, true)) {
                return $qui . ' vous a ajouté' . ($autres ? ', avec ' . msgEnumeration($autres) : '') . '.';
            }
            return $a . ' ajouté ' . msgEnumeration($autres) . '.';
        case 'membre_retire':
            if (!$parMoi && (int) ($d['id'] ?? 0) === $moiId) {
                return $qui . ' vous a retiré du groupe.';
            }
            return $a . ' retiré ' . $nom($d['id'] ?? 0) . ' du groupe.';
        case 'membre_parti':
            return $a . ' quitté le groupe.';
    }
    return '';
}

function msgEnumeration(array $noms): string
{
    if (count($noms) <= 1) {
        return (string) ($noms[0] ?? '');
    }
    $dernier = array_pop($noms);
    return implode(', ', $noms) . ' et ' . $dernier;
}

/** @return array<int,int> conversation => non lus (messages des autres, hors systeme) */
function msgNonLusParConversation(PDO $dtb, int $moi, ?array $convIds = null): array
{
    $sql = 'SELECT m.conversation_id, COUNT(*) n
              FROM msg_participant p
              JOIN msg_message m ON m.conversation_id = p.conversation_id
                   AND m.id > GREATEST(p.lu_jusqu_a, p.debut_message_id, p.efface_jusqu_a)
                   AND (p.fin_message_id IS NULL OR m.id <= p.fin_message_id)
             WHERE p.user_id = ? AND m.auteur_id <> ? AND m.type <> \'systeme\' AND m.retire_le IS NULL';
    $params = [$moi, $moi];
    if ($convIds !== null) {
        if (!$convIds) {
            return [];
        }
        $sql .= ' AND p.conversation_id IN (' . implode(',', array_fill(0, count($convIds), '?')) . ')';
        $params = array_merge($params, array_map('intval', $convIds));
    }
    $st = $dtb->prepare($sql . ' GROUP BY m.conversation_id');
    $st->execute($params);
    $r = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $r[(int) $l['conversation_id']] = (int) $l['n'];
    }
    return $r;
}

/** Discussions ayant des messages non lus (hors sourdine) : la pastille du menu. */
function msgNonLusTotal(PDO $dtb, int $moi): int
{
    $st = $dtb->prepare('SELECT COUNT(DISTINCT p.conversation_id)
              FROM msg_participant p
              JOIN msg_conversation c ON c.id = p.conversation_id
             WHERE p.user_id = ? AND p.sourdine = 0
               AND c.dernier_message_id > GREATEST(p.lu_jusqu_a, p.debut_message_id, p.efface_jusqu_a)
               AND EXISTS (SELECT 1 FROM msg_message m
                            WHERE m.conversation_id = p.conversation_id
                              AND m.id > GREATEST(p.lu_jusqu_a, p.debut_message_id, p.efface_jusqu_a)
                              AND (p.fin_message_id IS NULL OR m.id <= p.fin_message_id)
                              AND m.auteur_id <> p.user_id AND m.type <> \'systeme\' AND m.retire_le IS NULL)');
    $st->execute([$moi]);
    return (int) $st->fetchColumn();
}

/** Details d'une discussion : membres, roles, lectures, blocage. */
function msgDetails(PDO $dtb, array $moi, int $conversation, string $appBase): array
{
    $moiId = (int) $moi['id'];
    $p = msgParticipation($dtb, $conversation, $moiId);
    if (!$p) {
        throw new MessagerieRefus('Discussion introuvable.', 404);
    }
    $st = $dtb->prepare('SELECT user_id, role, lu_jusqu_a, ajoute_par, rejoint_le FROM msg_participant
                          WHERE conversation_id = ? AND fin_message_id IS NULL ORDER BY role = \'admin\' DESC, rejoint_le, user_id');
    $st->execute([$conversation]);
    /* Parti ou retire d'un groupe : on ne lui montre plus qui en fait partie. */
    $lignes = ($p['fin_message_id'] === null || $p['type'] === 'direct') ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    $profils = msgProfils($dtb, array_column($lignes, 'user_id'), $appBase);
    $membres = [];
    foreach ($lignes as $l) {
        $membres[] = $profils[(int) $l['user_id']] + [
            'admin' => $l['role'] === 'admin',
            'lu_jusqu_a' => $p['fin_message_id'] === null ? (int) $l['lu_jusqu_a'] : 0,
        ];
    }
    $bloc = null;
    $autreId = null;
    if ($p['type'] === 'direct') {
        $autreId = array_values(array_diff(array_map('intval', explode('-', (string) $p['cle_directe'])), [$moiId]))[0] ?? 0;
        $bloc = msgBlocage($dtb, $moiId, $autreId);
        if (!array_filter($membres, fn ($m) => $m['id'] === $autreId)) {
            $membres[] = msgProfils($dtb, [$autreId], $appBase)[$autreId] + ['admin' => false, 'lu_jusqu_a' => 0];
        }
    }
    return [
        'id'      => $conversation,
        'type'    => $p['type'],
        'nom'     => $p['conversation_nom'],
        'role'    => $p['role'],
        'membre'  => $p['fin_message_id'] === null,
        'sourdine'=> (bool) $p['sourdine'],
        'membres' => $membres,
        'autre_id'=> $autreId,
        'blocage' => $bloc,
    ];
}

/** Photos, videos et documents d'une discussion, pour le panneau d'informations. */
function msgMedias(PDO $dtb, array $moi, int $conversation, string $genre, int $limite = 60): array
{
    $p = msgParticipation($dtb, $conversation, (int) $moi['id']);
    if (!$p) {
        throw new MessagerieRefus('Discussion introuvable.', 404);
    }
    $genres = $genre === 'fichiers' ? ['fichier', 'audio'] : ['image', 'video'];
    $plancher = max((int) $p['debut_message_id'], (int) $p['efface_jusqu_a']);
    $plafond = $p['fin_message_id'] !== null ? (int) $p['fin_message_id'] : 2147483647;
    $st = $dtb->prepare('SELECT * FROM msg_message WHERE conversation_id = ? AND id > ? AND id <= ? AND retire_le IS NULL
                           AND type IN (?, ?) ORDER BY id DESC LIMIT ' . (int) $limite);
    $st->execute([$conversation, $plancher, $plafond, $genres[0], $genres[1]]);
    return msgSerialiser($dtb, $st->fetchAll(PDO::FETCH_ASSOC));
}

/* -----------------------------------------------------------------------------
   Synchronisation
   -------------------------------------------------------------------------- */

/** Dernier evenement : le point de depart d'un navigateur qui vient d'ouvrir la page. */
function msgCurseur(PDO $dtb): int
{
    return (int) $dtb->query('SELECT COALESCE(MAX(id), 0) FROM msg_evenement')->fetchColumn();
}

/**
 * Ce qui a change depuis l'evenement $curseur, pour moi :
 *   messages        nouveaux ou modifies (reactions, retrait), visibles de moi
 *   conversations   resumes a remettre a jour dans la liste
 *   lectures        [conversation, membre, lu_jusqu_a]
 *   ecrivent        qui ecrit dans la discussion ouverte
 * Au-dela de MSG_EVENEMENTS_MAX, « recharger » : le navigateur repart de zero.
 */
function msgEtat(PDO $dtb, array $moi, int $curseur, array $ouvertes, string $appBase): array
{
    $moiId = (int) $moi['id'];

    /* Les ids s'attribuent a l'insertion, mais deviennent visibles a la
       validation de la transaction : l'evenement 10 peut apparaitre apres le
       11. Avancer le curseur a 11 perdrait le 10. On relit donc toujours les
       5 dernieres secondes ; le navigateur ecarte ce qu'il connait deja. */
    $max = msgCurseur($dtb);
    $st = $dtb->query('SELECT MIN(id) FROM msg_evenement WHERE le >= NOW(3) - INTERVAL 5 SECOND');
    $recent = $st->fetchColumn();
    $plancher = $recent !== null && $recent !== false ? min($curseur, (int) $recent - 1) : $curseur;

    $st = $dtb->prepare('SELECT e.id, e.conversation_id, e.type, e.message_id, e.user_id,
                                p.debut_message_id, p.fin_message_id, p.efface_jusqu_a
                           FROM msg_evenement e
                           JOIN msg_participant p ON p.conversation_id = e.conversation_id AND p.user_id = ?
                          WHERE e.id > ? AND e.id <= ?
                            AND (p.fin_message_id IS NULL
                                 OR (e.message_id IS NOT NULL AND e.message_id <= p.fin_message_id AND e.type <> \'lu\')
                                 OR (e.type = \'conversation\' AND e.user_id = ?))
                          ORDER BY e.id LIMIT ' . (MSG_EVENEMENTS_MAX + 1));
    $st->execute([$moiId, $plancher, $max, $moiId]);
    $evenements = $st->fetchAll(PDO::FETCH_ASSOC);

    $sortie = ['curseur' => $max, 'messages' => [], 'conversations' => [], 'lectures' => [], 'ecrivent' => [], 'recharger' => false];
    if (count($evenements) > MSG_EVENEMENTS_MAX) {
        $sortie['recharger'] = true;
        return $sortie;
    }

    $messages = [];
    $conversations = [];
    foreach ($evenements as $e) {
        $cid = (int) $e['conversation_id'];
        if ($e['type'] === 'message' || $e['type'] === 'modif') {
            $mid = (int) $e['message_id'];
            if (msgVisible($e, $mid)) {
                $messages[$mid] = true;
            }
            if ($e['type'] === 'message') {
                $conversations[$cid] = true;
            }
        } elseif ($e['type'] === 'conversation') {
            $conversations[$cid] = true;
        } elseif ($e['type'] === 'lu' && (int) $e['user_id'] !== $moiId) {
            $sortie['lectures'][] = ['conversation_id' => $cid, 'user_id' => (int) $e['user_id'], 'lu_jusqu_a' => (int) $e['message_id']];
        } elseif ($e['type'] === 'lu') {
            $conversations[$cid] = true;   // lu ailleurs (autre onglet) : la pastille change
        }
    }
    if ($messages) {
        $ids = array_keys($messages);
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st = $dtb->prepare("SELECT * FROM msg_message WHERE id IN ($place) ORDER BY id");
        $st->execute($ids);
        $sortie['messages'] = msgSerialiser($dtb, $st->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($conversations) {
        $sortie['conversations'] = msgConversations($dtb, $moi, $appBase, array_keys($conversations));
    }
    /* « En train d'ecrire » dans les discussions affichees (page ou fenetres),
       parmi celles dont je suis membre. */
    $ouvertes = array_slice(array_values(array_filter(array_map('intval', $ouvertes))), 0, 8);
    $ecrivent = [];
    if ($ouvertes) {
        $place = implode(',', array_fill(0, count($ouvertes), '?'));
        $st = $dtb->prepare("SELECT e.conversation_id, e.user_id FROM msg_participant e
                               JOIN msg_participant moi ON moi.conversation_id = e.conversation_id AND moi.user_id = ? AND moi.fin_message_id IS NULL
                              WHERE e.conversation_id IN ($place) AND e.user_id <> ? AND e.fin_message_id IS NULL
                                AND e.ecrit_le > NOW() - INTERVAL " . MSG_ECRIT_SECONDES . ' SECOND');
        $st->execute(array_merge([$moiId], $ouvertes, [$moiId]));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $ecrivent[(int) $l['conversation_id']][] = (int) $l['user_id'];
        }
    }
    $sortie['ecrivent'] = (object) $ecrivent;
    $qui = $ecrivent ? array_merge(...array_values($ecrivent)) : [];
    if ($sortie['messages'] || $qui) {
        $sortie['profils'] = msgProfilsAuteurs($dtb, $sortie['messages'], $qui);
    }
    /* Pastilles : le nombre de discussions non lues, quand quelque chose a bouge. */
    if ($evenements) {
        $sortie['non_lus'] = msgNonLusTotal($dtb, $moiId);
    }
    return $sortie;
}

/** Note mon activite (au plus une fois par minute) et rend celle de mes correspondants. */
function msgPresences(PDO $dtb, array $moi, bool $noter): array
{
    $moiId = (int) $moi['id'];
    if ($noter) {
        $dtb->prepare('INSERT INTO msg_presence (user_id, vu_le) VALUES (?, NOW())
                       ON DUPLICATE KEY UPDATE vu_le = IF(vu_le < NOW() - INTERVAL 50 SECOND, NOW(), vu_le)')->execute([$moiId]);
    }
    $st = $dtb->prepare('SELECT DISTINCT pr.user_id, pr.vu_le, TIMESTAMPDIFF(SECOND, pr.vu_le, NOW()) AS depuis
                           FROM msg_participant p1
                           JOIN msg_participant p2 ON p2.conversation_id = p1.conversation_id AND p2.user_id <> p1.user_id
                           JOIN msg_presence pr ON pr.user_id = p2.user_id AND pr.vu_le > NOW() - INTERVAL 1 DAY
                          WHERE p1.user_id = ? AND p1.fin_message_id IS NULL AND p2.fin_message_id IS NULL');
    $st->execute([$moiId]);
    $r = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $r[(int) $l['user_id']] = ['en_ligne' => (int) $l['depuis'] < MSG_EN_LIGNE_SECONDES, 'vu_le' => msgIso($l['vu_le'])];
    }
    return $r;
}

/* -----------------------------------------------------------------------------
   Blocage et signalement
   -------------------------------------------------------------------------- */

function msgBloquer(PDO $dtb, array $moi, int $qui, bool $bloquer): void
{
    if ($qui === (int) $moi['id'] || !msgCompte($dtb, $qui)) {
        throw new MessagerieRefus('Compte introuvable.', 404);
    }
    if ($bloquer) {
        $dtb->prepare('INSERT IGNORE INTO msg_blocage (user_id, bloque_id, le) VALUES (?, ?, NOW())')->execute([(int) $moi['id'], $qui]);
    } else {
        $dtb->prepare('DELETE FROM msg_blocage WHERE user_id = ? AND bloque_id = ?')->execute([(int) $moi['id'], $qui]);
    }
    /* Les deux navigateurs mettent a jour la zone de saisie. */
    $cle = min((int) $moi['id'], $qui) . '-' . max((int) $moi['id'], $qui);
    $st = $dtb->prepare('SELECT id FROM msg_conversation WHERE cle_directe = ?');
    $st->execute([$cle]);
    if ($cid = (int) $st->fetchColumn()) {
        msgEvenement($dtb, $cid, 'conversation');
    }
}

function msgSignaler(PDO $dtb, array $moi, int $messageId, string $motif): void
{
    [$m] = msgMessageVisible($dtb, $moi, $messageId);
    if ((int) $m['auteur_id'] === (int) $moi['id'] || $m['type'] === 'systeme') {
        throw new MessagerieRefus('Vous ne pouvez pas signaler ce message.');
    }
    $motif = mb_substr(trim((string) preg_replace('~\s+~u', ' ', $motif)), 0, 500);
    $dtb->prepare('INSERT INTO msg_signalement (message_id, signale_par, motif, le) VALUES (?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE motif = VALUES(motif), le = NOW(), statut = \'ouvert\'')
        ->execute([$messageId, (int) $moi['id'], $motif !== '' ? $motif : null]);
}

/** Signalements ouverts (moderateur), avec le message signale et son contexte. */
function msgSignalements(PDO $dtb, array $moi, string $appBase): array
{
    if (!msgEstModerateur($moi)) {
        throw new MessagerieRefus('Réservé à la modération.', 403);
    }
    $st = $dtb->query('SELECT s.id, s.message_id, s.signale_par, s.motif, s.le, s.statut,
                              m.conversation_id, m.auteur_id, m.type, m.texte, m.fichier_id, m.envoye_le, m.retire_le,
                              c.type AS conversation_type, c.nom AS conversation_nom
                         FROM msg_signalement s
                         JOIN msg_message m ON m.id = s.message_id
                         JOIN msg_conversation c ON c.id = m.conversation_id
                        WHERE s.statut = \'ouvert\' ORDER BY s.le DESC LIMIT 200');
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
    $profils = msgProfils($dtb, array_merge(array_column($lignes, 'signale_par'), array_column($lignes, 'auteur_id')), $appBase);
    $fichiers = [];
    $ids = array_filter(array_map('intval', array_column($lignes, 'fichier_id')));
    if ($ids) {
        $st = $dtb->prepare('SELECT id, nom, genre FROM msg_fichier WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute(array_values($ids));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) { $fichiers[(int) $f['id']] = $f; }
    }
    return array_map(fn ($l) => [
        'id'         => (int) $l['id'],
        'message_id' => (int) $l['message_id'],
        'motif'      => $l['motif'],
        'le'         => msgIso($l['le']),
        'par'        => $profils[(int) $l['signale_par']] ?? null,
        'auteur'     => $profils[(int) $l['auteur_id']] ?? null,
        'type'       => $l['type'],
        'texte'      => $l['texte'],
        'fichier'    => $l['fichier_id'] ? ($fichiers[(int) $l['fichier_id']] ?? null) : null,
        'envoye_le'  => msgIso($l['envoye_le']),
        'retire'     => $l['retire_le'] !== null,
        'lieu'       => $l['conversation_type'] === 'groupe' ? 'Groupe' . ($l['conversation_nom'] ? ' « ' . $l['conversation_nom'] . ' »' : '') : 'Discussion à deux',
    ], $lignes);
}

/** Decision du moderateur : retirer le message, ou rejeter le signalement. */
function msgTraiterSignalement(PDO $dtb, array $moi, int $signalement, string $decision): array
{
    if (!msgEstModerateur($moi)) {
        throw new MessagerieRefus('Réservé à la modération.', 403);
    }
    $st = $dtb->prepare('SELECT * FROM msg_signalement WHERE id = ?');
    $st->execute([$signalement]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) {
        throw new MessagerieRefus('Signalement introuvable.', 404);
    }
    if (!in_array($decision, ['retire', 'rejete'], true)) {
        throw new MessagerieRefus('Décision inconnue.');
    }
    if ($decision === 'retire') {
        msgRetirer($dtb, $moi, (int) $s['message_id'], true);
    }
    /* Tous les signalements de ce message sont traites ensemble. */
    $dtb->prepare('UPDATE msg_signalement SET statut = ?, traite_par = ?, traite_le = NOW() WHERE message_id = ? AND statut = \'ouvert\'')
        ->execute([$decision, (int) $moi['id'], (int) $s['message_id']]);
    return $s;
}

/* -----------------------------------------------------------------------------
   Fichiers
   -------------------------------------------------------------------------- */

function msgDossierFichiers(): string
{
    return dirname(__DIR__) . '/data/messagerie';
}

/** Nom d'origine sans chemin ni caracteres de controle. */
function msgNomFichier(string $nom): string
{
    $nom = basename(str_replace('\\', '/', $nom));
    $nom = trim((string) preg_replace('~[\x00-\x1F\x7F<>:"/\\\\|?*]+~u', '_', $nom));
    if ($nom === '' || $nom === '.' || $nom === '..') {
        $nom = 'fichier';
    }
    if (mb_strlen($nom) > 150) {
        $ext = pathinfo($nom, PATHINFO_EXTENSION);
        $nom = mb_substr(pathinfo($nom, PATHINFO_FILENAME), 0, 140) . ($ext !== '' ? '.' . mb_substr($ext, 0, 8) : '');
    }
    return $nom;
}

/**
 * Envois abandonnes depuis plus d'un jour : morceaux jamais completes, ou
 * fichier complet jamais joint a un message (onglet ferme avant l'envoi).
 * Lance de temps en temps par un nouvel envoi, vingt fichiers au plus.
 */
function msgNettoyerEnvois(PDO $dtb): void
{
    $st = $dtb->query('SELECT f.id, f.chemin FROM msg_fichier f
                        WHERE f.cree_le < NOW() - INTERVAL 1 DAY
                          AND (f.statut = \'envoi\' OR NOT EXISTS (SELECT 1 FROM msg_message m WHERE m.fichier_id = f.id))
                        LIMIT 20');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
        if (!str_contains($f['chemin'], '..')) {
            @unlink(msgDossierFichiers() . '/' . $f['chemin']);
            @unlink(msgDossierFichiers() . '/' . $f['chemin'] . '.apercu');
        }
        $dtb->prepare('DELETE FROM msg_fichier WHERE id = ?')->execute([(int) $f['id']]);
    }
}

/** Premier morceau : on verifie nom, taille et quota, on reserve la place. */
function msgFichierDebut(PDO $dtb, array $moi, string $nom, int $taille, ?int $dureeMs): array
{
    if (random_int(1, 30) === 1) {
        msgNettoyerEnvois($dtb);
    }
    $nom = msgNomFichier($nom);
    $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    $acceptee = false;
    foreach (MSG_EXTENSIONS as $liste) {
        if (in_array($ext, $liste, true)) { $acceptee = true; break; }
    }
    if (!$acceptee) {
        throw new MessagerieRefus('Ce type de fichier n\'est pas accepté (' . ($ext !== '' ? '.' . $ext : 'sans extension') . ').');
    }
    if ($taille <= 0) {
        throw new MessagerieRefus('Le fichier est vide.');
    }
    if ($taille > MSG_TAILLE_MAX) {
        throw new MessagerieRefus('Un fichier ne dépasse pas ' . (MSG_TAILLE_MAX / 1024 / 1024) . ' Mo.');
    }
    $st = $dtb->prepare('SELECT COALESCE(SUM(taille), 0) FROM msg_fichier WHERE proprietaire_id = ? AND cree_le > NOW() - INTERVAL 1 DAY');
    $st->execute([(int) $moi['id']]);
    if ((int) $st->fetchColumn() + $taille > MSG_QUOTA_JOUR) {
        throw new MessagerieRefus('Vous avez envoyé plus de ' . (MSG_QUOTA_JOUR / 1024 / 1024 / 1024) . ' Go de fichiers en 24 heures. Réessayez demain.', 429);
    }
    $jeton = bin2hex(random_bytes(16));
    $chemin = date('Y') . '/' . date('m') . '/' . $jeton;
    $dossier = msgDossierFichiers() . '/' . date('Y') . '/' . date('m');
    if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
        throw new MessagerieRefus('Le serveur ne peut pas enregistrer de fichier pour l\'instant.', 500);
    }
    if (@file_put_contents(msgDossierFichiers() . '/' . $chemin, '') === false) {
        throw new MessagerieRefus('Le serveur ne peut pas enregistrer de fichier pour l\'instant.', 500);
    }
    $genre = 'fichier';
    foreach (MSG_EXTENSIONS as $g => $liste) {
        if (in_array($ext, $liste, true)) { $genre = $g; break; }
    }
    $dtb->prepare('INSERT INTO msg_fichier (proprietaire_id, jeton, nom, mime, genre, taille, chemin, duree_ms, cree_le)
                   VALUES (?, ?, ?, \'application/octet-stream\', ?, ?, ?, ?, NOW())')
        ->execute([(int) $moi['id'], $jeton, $nom, $genre, $taille, $chemin, $dureeMs && $dureeMs > 0 ? min($dureeMs, 86400000) : null]);
    return ['id' => (int) $dtb->lastInsertId(), 'jeton' => $jeton, 'morceau' => MSG_MORCEAU];
}

/** Morceau suivant, a sa place exacte : un morceau repete ou manquant est refuse. */
function msgFichierMorceau(PDO $dtb, array $moi, int $id, string $jeton, int $position, string $tmp): array
{
    $st = $dtb->prepare('SELECT * FROM msg_fichier WHERE id = ? AND proprietaire_id = ?');
    $st->execute([$id, (int) $moi['id']]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f || !hash_equals($f['jeton'], $jeton)) {
        throw new MessagerieRefus('Envoi introuvable.', 404);
    }
    if ($f['statut'] === 'pret') {
        return msgFichierResume($f);
    }
    if ($position !== (int) $f['recu']) {
        /* Le navigateur reprend la ou le serveur en est (coupure reseau). */
        throw new MessagerieRefus('reprendre:' . (int) $f['recu'], 409);
    }
    $longueur = (int) @filesize($tmp);
    if ($longueur <= 0 || $longueur > MSG_MORCEAU || $position + $longueur > (int) $f['taille']) {
        throw new MessagerieRefus('Morceau invalide.');
    }
    $cible = msgDossierFichiers() . '/' . $f['chemin'];
    $sortie = @fopen($cible, 'cb');
    $entree = @fopen($tmp, 'rb');
    if (!$sortie || !$entree || fseek($sortie, $position) !== 0 || stream_copy_to_stream($entree, $sortie) !== $longueur) {
        throw new MessagerieRefus('Le serveur n\'a pas pu écrire le fichier.', 500);
    }
    fclose($entree);
    fclose($sortie);
    $recu = $position + $longueur;
    $dtb->prepare('UPDATE msg_fichier SET recu = ? WHERE id = ?')->execute([$recu, $id]);
    $f['recu'] = $recu;
    if ($recu === (int) $f['taille']) {
        $f = msgFichierFinaliser($dtb, $f);
    }
    return msgFichierResume($f);
}

function msgFichierResume(array $f): array
{
    return [
        'id' => (int) $f['id'], 'recu' => (int) $f['recu'], 'taille' => (int) $f['taille'], 'pret' => $f['statut'] === 'pret',
        'genre' => $f['genre'], 'nom' => $f['nom'], 'mime' => $f['mime'], 'apercu' => (bool) $f['apercu'],
        'largeur' => $f['largeur'] !== null ? (int) $f['largeur'] : null, 'hauteur' => $f['hauteur'] !== null ? (int) $f['hauteur'] : null,
    ];
}

/**
 * Fichier complet : type reel (d'apres le contenu), genre, et pour une photo
 * reencodage sans metadonnees, dans le bon sens, plus une vignette.
 */
function msgFichierFinaliser(PDO $dtb, array $f): array
{
    $chemin = msgDossierFichiers() . '/' . $f['chemin'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($chemin) ?: 'application/octet-stream';
    $ext = strtolower(pathinfo($f['nom'], PATHINFO_EXTENSION));
    /* Un vocal webm sans video est lu comme video/webm : c'est l'extension
       choisie par le navigateur qui dit qu'il s'agit d'un son. */
    if ($mime === 'video/webm' && in_array($ext, ['weba', 'webm'], true) && $f['genre'] === 'audio') {
        $mime = 'audio/webm';
    }
    if ($mime === 'video/mp4' && $f['genre'] === 'audio') {
        $mime = 'audio/mp4';
    }
    if ($mime === 'application/ogg') {
        $mime = 'audio/ogg';
    }
    if ($mime === 'audio/x-m4a') {
        $mime = 'audio/mp4';
    }

    $genre = 'fichier';
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'], true)) {
        $genre = 'image';
    } elseif (str_starts_with($mime, 'video/')) {
        $genre = 'video';
    } elseif (str_starts_with($mime, 'audio/')) {
        $genre = 'audio';
    }
    /* Un contenu actif deguise (HTML, SVG, script) reste un fichier a telecharger. */
    if (preg_match('~(html|svg|xml|javascript|x-php|x-sh)~i', $mime)) {
        $genre = 'fichier';
        $mime = 'application/octet-stream';
    }

    $maj = ['mime' => $mime, 'genre' => $genre, 'statut' => 'pret', 'apercu' => 0, 'largeur' => null, 'hauteur' => null];
    if ($genre === 'image') {
        $maj = array_merge($maj, msgImagePreparer($chemin, $mime));
        $taille = @filesize($chemin);
        if ($taille) { $maj['taille'] = $taille; $maj['recu'] = $taille; }
        if (!empty($maj['mime_final'])) {
            $maj['mime'] = $maj['mime_final'];
            /* Une image BMP reencodee en JPEG : le nom suit le contenu. */
            if ($maj['mime'] === 'image/jpeg' && !in_array($ext, ['jpg', 'jpeg'], true)) {
                $maj['nom'] = msgNomFichier(pathinfo($f['nom'], PATHINFO_FILENAME) . '.jpg');
            }
        }
        unset($maj['mime_final']);
    }
    $champs = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($maj)));
    $dtb->prepare("UPDATE msg_fichier SET $champs WHERE id = ?")->execute(array_merge(array_values($maj), [(int) $f['id']]));
    $st = $dtb->prepare('SELECT * FROM msg_fichier WHERE id = ?');
    $st->execute([(int) $f['id']]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

/**
 * Photo : remise dans le bon sens (EXIF), reduite a MSG_IMAGE_COTE, reencodee
 * sans metadonnees ; vignette a cote (chemin + '.apercu'). Un GIF garde son
 * animation. Une image trop grande pour la memoire reste telle quelle.
 */
function msgImagePreparer(string $chemin, string $mime): array
{
    $info = @getimagesize($chemin);
    if (!$info) {
        return [];
    }
    [$l, $h] = $info;
    $r = ['largeur' => min($l, 65535), 'hauteur' => min($h, 65535)];
    if ($l * $h > MSG_IMAGE_PIXELS_MAX || !function_exists('imagecreatefromstring')) {
        return $r;
    }
    @ini_set('memory_limit', '512M');
    $img = @imagecreatefromstring((string) file_get_contents($chemin));
    if (!$img) {
        return $r;
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($chemin);
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle !== 0 && ($tourne = imagerotate($img, $angle, 0))) {
            imagedestroy($img);
            $img = $tourne;
        }
    }
    $l = imagesx($img);
    $h = imagesy($img);
    $transparent = in_array($mime, ['image/png', 'image/gif', 'image/webp'], true);

    $reduire = function ($src, int $cote) use ($transparent) {
        $l = imagesx($src);
        $h = imagesy($src);
        $k = min(1, $cote / max($l, $h));
        $nl = max(1, (int) round($l * $k));
        $nh = max(1, (int) round($h * $k));
        $dst = imagecreatetruecolor($nl, $nh);
        if ($transparent) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nl, $nh, $l, $h);
        return $dst;
    };

    $mimeFinal = $mime;
    if ($mime !== 'image/gif') {
        $grande = $reduire($img, MSG_IMAGE_COTE);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            $ok = $mime === 'image/png' ? imagepng($grande, $chemin, 6) : imagewebp($grande, $chemin, 86);
        } else {
            $ok = imagejpeg($grande, $chemin, 86);
            $mimeFinal = 'image/jpeg';
        }
        if ($ok) {
            $r['largeur'] = imagesx($grande);
            $r['hauteur'] = imagesy($grande);
        }
        imagedestroy($grande);
    }
    $petite = $reduire($img, MSG_APERCU_COTE);
    $ok = $transparent ? imagewebp($petite, $chemin . '.apercu', 80) : imagejpeg($petite, $chemin . '.apercu', 78);
    imagedestroy($petite);
    imagedestroy($img);
    $r['apercu'] = $ok ? 1 : 0;
    $r['mime_final'] = $mimeFinal;
    return $r;
}

/** Retire le fichier du disque ; la ligne reste pour l'historique des envois. */
function msgFichierEffacer(PDO $dtb, int $id): void
{
    $st = $dtb->prepare('SELECT chemin FROM msg_fichier WHERE id = ?');
    $st->execute([$id]);
    $chemin = (string) $st->fetchColumn();
    if ($chemin !== '' && !str_contains($chemin, '..')) {
        @unlink(msgDossierFichiers() . '/' . $chemin);
        @unlink(msgDossierFichiers() . '/' . $chemin . '.apercu');
    }
    $dtb->prepare('UPDATE msg_fichier SET apercu = 0 WHERE id = ?')->execute([$id]);
}

/**
 * Le fichier, si je peux le voir : je l'ai envoye, ou il est joint a un
 * message visible d'une de mes discussions, ou je modere un message signale.
 */
function msgFichierAccessible(PDO $dtb, array $moi, int $id): ?array
{
    $st = $dtb->prepare('SELECT * FROM msg_fichier WHERE id = ?');
    $st->execute([$id]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f) {
        return null;
    }
    $moiId = (int) $moi['id'];
    $st = $dtb->prepare('SELECT m.id, m.retire_le, p.debut_message_id, p.fin_message_id, p.efface_jusqu_a
                           FROM msg_message m JOIN msg_participant p ON p.conversation_id = m.conversation_id AND p.user_id = ?
                          WHERE m.fichier_id = ?');
    $st->execute([$moiId, $id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if ($m['retire_le'] === null && msgVisible($m, (int) $m['id'])) {
            return $f;
        }
    }
    if ((int) $f['proprietaire_id'] === $moiId) {
        $st = $dtb->prepare('SELECT retire_le FROM msg_message WHERE fichier_id = ? LIMIT 1');
        $st->execute([$id]);
        $retire = $st->fetchColumn();
        return ($retire === false || $retire === null) ? $f : null;
    }
    if (msgEstModerateur($moi)) {
        $st = $dtb->prepare('SELECT 1 FROM msg_signalement s JOIN msg_message m ON m.id = s.message_id WHERE m.fichier_id = ? LIMIT 1');
        $st->execute([$id]);
        if ($st->fetchColumn()) {
            return $f;
        }
    }
    return null;
}

} // MESSAGERIE_LOADED
