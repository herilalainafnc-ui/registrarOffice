<?php
/* =============================================================================
   Messagerie : point d'entree JSON de src/js/messagerie.js.

   GET  action=
     demarrer       moi, mes discussions, curseur, bloques, reglages
     etat           ce qui a change depuis « curseur » (interrogation reguliere) ;
                    « ouvertes » = discussions affichees, pour « en train d'ecrire »
     non_lus        nombre de discussions non lues (pastille des autres pages)
     messages       une page de messages d'une discussion (« avant » = plus anciens)
     details        membres, roles, lectures, blocage d'une discussion
     medias         photos et videos, ou documents, d'une discussion
     comptes        recherche de personnes a qui ecrire (q=)
     signalements   messages signales (superadmin)
     jeton          nouveau jeton CSRF, quand l'ancien a expire
   POST action= (avec csrf_token)
     envoyer, lu, ecrit, reagir, retirer, creer_groupe, renommer, ajouter,
     exclure, role, quitter, sourdine, effacer, bloquer, signaler, traiter,
     diffuser (superadmin : le meme message a tous, au personnel ou aux etudiants)
   GET diffusion : nombre de destinataires de chaque cible

   La session est lue sans etre verrouillee ni reecrite (msgSessionLecture) :
   les interrogations frequentes ne bloquent pas les autres onglets.
   Regles et requetes : data/messagerie.php.
   ========================================================================== */
require_once __DIR__ . '/../data/messagerie.php';
msgSessionLecture();
require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/middleware.php';
require_once __DIR__ . '/../data/guard.ajax.php';   // estRequeteAjax, refuserRequeteCroisee
refuserRequeteCroisee();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Chemin de base des liens (l'appli peut vivre dans un sous-dossier), comme app/recherche.rapide.php
$_dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

function msgRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$moi = msgMoi($dtb);
if (!$moi) {
    msgRepondre(['ok' => false, 'session_expiree' => true, 'login_url' => $app_base . '/login',
                 'message' => 'Votre session a expiré. Reconnectez-vous pour continuer.'], 401);
}
msgContexte((int) $moi['id'], $app_base);

$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($methode === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? ''));
$entier = static fn ($v) => ($v === null || $v === '') ? null : (int) $v;

if ($methode === 'POST' && $action !== '') {
    /* 400 et non 403 : src/js/session.js transforme les 403 en « Accès refusé ». */
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        msgRepondre(['ok' => false, 'csrf_expire' => true, 'message' => 'Jeton de sécurité expiré.'], 400);
    }
}

try {
    switch ($methode . ' ' . $action) {

        /* ------------------------------------------------------------ lecture */

        case 'GET demarrer':
            $presences = msgPresences($dtb, $moi, true);
            msgRepondre([
                'ok'            => true,
                'moi'           => msgProfils($dtb, [(int) $moi['id']], $app_base)[(int) $moi['id']],
                'conversations' => msgConversations($dtb, $moi, $app_base),
                'curseur'       => msgCurseur($dtb),
                'bloques'       => array_values(msgProfils($dtb, msgBloques($dtb, (int) $moi['id']), $app_base)),
                'presences'     => (object) $presences,
                'reglages'      => [
                    'peut_creer_groupe' => msgPeutCreerGroupe($moi),
                    'moderateur'        => msgEstModerateur($moi),
                    'diffusion'         => msgPeutDiffuser($moi),
                    'reactions'         => MSG_REACTIONS,
                    'texte_max'         => MSG_TEXTE_MAX,
                    'taille_max'        => MSG_TAILLE_MAX,
                    'groupe_max'        => MSG_GROUPE_MAX,
                    'extensions'        => array_merge(...array_values(MSG_EXTENSIONS)),
                    /* Cle publique des notifications push (data/push.php), null si
                       le serveur ne sait pas les envoyer. */
                    'push'              => pushClePublique($dtb),
                ],
            ]);

        case 'GET etat':
            if (!empty($_GET['garder'])) {
                msgSessionEntretenir();
            }
            /* La page est visible : pas de notification push pendant ce temps. */
            if (!empty($_GET['vue'])) {
                try { pushNoterRegard($dtb, (int) $moi['id']); } catch (Throwable $e) { /* migration push absente */ }
            }
            $etat = msgEtat($dtb, $moi, (int) ($_GET['curseur'] ?? 0), explode(',', (string) ($_GET['ouvertes'] ?? '')), $app_base);
            if (!empty($_GET['presences'])) {
                $etat['presences'] = (object) msgPresences($dtb, $moi, true);
            }
            msgRepondre(['ok' => true] + $etat);

        case 'GET non_lus':
            if (!empty($_GET['garder'])) {
                msgSessionEntretenir();
            }
            msgRepondre(['ok' => true, 'non_lus' => msgNonLusTotal($dtb, (int) $moi['id'])]);

        case 'GET messages':
            $page = msgMessages($dtb, $moi, (int) ($_GET['conversation'] ?? 0), $entier($_GET['avant'] ?? null));
            msgRepondre(['ok' => true] + $page);

        case 'GET details':
            msgRepondre(['ok' => true, 'details' => msgDetails($dtb, $moi, (int) ($_GET['conversation'] ?? 0), $app_base)]);

        case 'GET medias':
            msgRepondre(['ok' => true, 'messages' => msgMedias($dtb, $moi, (int) ($_GET['conversation'] ?? 0),
                (string) ($_GET['genre'] ?? 'medias'))]);

        case 'GET comptes':
            $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80));
            msgRepondre(['ok' => true, 'comptes' => mb_strlen($q) < 2 ? [] : msgChercherComptes($dtb, $moi, $q, $app_base)]);

        case 'GET profil':
            /* Lien « ecrire a » (/messagerie#a45) : le profil d'un compte actif. */
            $id = (int) ($_GET['id'] ?? 0);
            $compte = msgCompte($dtb, $id);
            if (!$compte || (int) $compte['etat'] !== 1 || $id === (int) $moi['id']) {
                msgRepondre(['ok' => false, 'message' => 'Ce compte n\'est pas joignable par la messagerie.'], 404);
            }
            msgRepondre(['ok' => true, 'profil' => msgProfils($dtb, [$id], $app_base)[$id]]);

        case 'GET diffusion':
            /* Nombre de destinataires de chaque cible, pour le formulaire « Message a tous ». */
            if (!msgPeutDiffuser($moi)) {
                msgRepondre(['ok' => false, 'message' => 'Réservé à l\'administration de l\'application.'], 400);
            }
            $comptes = [];
            foreach (array_keys(MSG_DIFFUSION_CIBLES) as $cible) {
                $comptes[$cible] = count(msgDiffusionComptes($dtb, $moi, $cible));
            }
            msgRepondre(['ok' => true, 'comptes' => $comptes]);

        case 'GET signalements':
            msgRepondre(['ok' => true, 'signalements' => msgSignalements($dtb, $moi, $app_base)]);

        case 'GET jeton':
            /* Ouverture normale : le jeton se cree ou se renouvelle en session. */
            session_start();
            $jeton = csrf_token();
            session_write_close();
            msgRepondre(['ok' => true, 'jeton' => $jeton]);

        /* --------------------------------------------------------- ecritures */

        case 'POST envoyer':
            $message = msgEnvoyer($dtb, $moi, $entier($_POST['conversation'] ?? null), $entier($_POST['destinataire'] ?? null),
                (string) ($_POST['texte'] ?? ''), $entier($_POST['fichier'] ?? null), $entier($_POST['reponse_a'] ?? null),
                isset($_POST['cle']) ? (string) $_POST['cle'] : null, isset($_POST['onde']) ? (string) $_POST['onde'] : null);
            msgRepondre(['ok' => true, 'message' => $message,
                         'conversation' => msgConversations($dtb, $moi, $app_base, [$message['conversation_id']])[0] ?? null]);

        case 'POST lu':
            msgMarquerLu($dtb, $moi, (int) ($_POST['conversation'] ?? 0), (int) ($_POST['message'] ?? 0));
            msgRepondre(['ok' => true]);

        case 'POST ecrit':
            msgEcrit($dtb, $moi, (int) ($_POST['conversation'] ?? 0), ($_POST['ecrit'] ?? '1') === '1');
            msgRepondre(['ok' => true]);

        case 'POST reagir':
            msgReagir($dtb, $moi, (int) ($_POST['message'] ?? 0), (string) ($_POST['emoji'] ?? ''));
            msgRepondre(['ok' => true]);

        case 'POST retirer':
            msgRetirer($dtb, $moi, (int) ($_POST['message'] ?? 0));
            msgRepondre(['ok' => true]);

        case 'POST creer_groupe':
            $id = msgCreerGroupe($dtb, $moi, (string) ($_POST['nom'] ?? ''), (array) ($_POST['membres'] ?? []));
            msgRepondre(['ok' => true, 'conversation' => msgConversations($dtb, $moi, $app_base, [$id])[0] ?? null]);

        case 'POST renommer':
            msgRenommerGroupe($dtb, $moi, (int) ($_POST['conversation'] ?? 0), (string) ($_POST['nom'] ?? ''));
            msgRepondre(['ok' => true]);

        case 'POST ajouter':
            $ajoutes = msgAjouterMembres($dtb, $moi, (int) ($_POST['conversation'] ?? 0), (array) ($_POST['membres'] ?? []));
            msgRepondre(['ok' => true, 'ajoutes' => $ajoutes]);

        case 'POST exclure':
            msgExclureMembre($dtb, $moi, (int) ($_POST['conversation'] ?? 0), (int) ($_POST['membre'] ?? 0));
            msgRepondre(['ok' => true]);

        case 'POST role':
            msgChangerRole($dtb, $moi, (int) ($_POST['conversation'] ?? 0), (int) ($_POST['membre'] ?? 0), (string) ($_POST['role'] ?? ''));
            msgRepondre(['ok' => true]);

        case 'POST quitter':
            msgQuitterGroupe($dtb, $moi, (int) ($_POST['conversation'] ?? 0));
            msgRepondre(['ok' => true]);

        case 'POST sourdine':
            msgSourdine($dtb, $moi, (int) ($_POST['conversation'] ?? 0), ($_POST['valeur'] ?? '1') === '1');
            msgRepondre(['ok' => true]);

        /* Notifications push de ce navigateur (data/push.php) */
        case 'POST push_abonner':
            try {
                pushAbonner($dtb, (int) $moi['id'], (string) ($_POST['adresse'] ?? ''), (string) ($_POST['p256dh'] ?? ''),
                    (string) ($_POST['auth'] ?? ''), (string) ($_POST['appareil'] ?? ''));
            } catch (InvalidArgumentException $e) {
                throw new MessagerieRefus($e->getMessage());
            }
            msgRepondre(['ok' => true]);

        case 'POST push_desabonner':
            pushDesabonner($dtb, (string) ($_POST['adresse'] ?? ''));
            msgRepondre(['ok' => true]);

        case 'POST effacer':
            msgEffacerPourMoi($dtb, $moi, (int) ($_POST['conversation'] ?? 0));
            msgRepondre(['ok' => true]);

        case 'POST bloquer':
            msgBloquer($dtb, $moi, (int) ($_POST['compte'] ?? 0), ($_POST['valeur'] ?? '1') === '1');
            msgRepondre(['ok' => true, 'bloques' => array_values(msgProfils($dtb, msgBloques($dtb, (int) $moi['id']), $app_base))]);

        case 'POST signaler':
            msgSignaler($dtb, $moi, (int) ($_POST['message'] ?? 0), (string) ($_POST['motif'] ?? ''));
            msgRepondre(['ok' => true]);

        case 'POST diffuser':
            $cible = (string) ($_POST['cible'] ?? '');
            $bilan = msgDiffuser($dtb, $moi, $cible, (string) ($_POST['texte'] ?? ''), (string) ($_POST['cle'] ?? ''));
            /* Une annonce a tous se lit dans le journal d'activite : qui, a quelle cible, combien. */
            if (class_exists('Audit')) {
                Audit::evenement('messagerie_diffusion', ['cible' => $cible, 'envoyes' => $bilan['envoyes'], 'ignores' => $bilan['ignores']]);
            }
            msgRepondre(['ok' => true] + $bilan);

        case 'POST traiter':
            $s = msgTraiterSignalement($dtb, $moi, (int) ($_POST['signalement'] ?? 0), (string) ($_POST['decision'] ?? ''));
            /* La moderation se lit dans le journal d'activite, sans le contenu du message. */
            if (class_exists('Audit')) {
                Audit::evenement('messagerie_moderation', [
                    'signalement' => (int) $s['id'], 'message' => (int) $s['message_id'], 'decision' => (string) ($_POST['decision'] ?? ''),
                ]);
            }
            msgRepondre(['ok' => true]);
    }
    msgRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);

} catch (MessagerieRefus $e) {
    $code = $e->statut === 403 ? 400 : $e->statut;
    $reprise = null;
    if (str_starts_with($e->getMessage(), 'reprendre:')) {
        $reprise = (int) substr($e->getMessage(), 10);
    }
    msgRepondre(['ok' => false, 'message' => $reprise === null ? $e->getMessage() : 'Reprise de l\'envoi.', 'reprendre' => $reprise], $code);
} catch (Throwable $e) {
    error_log('Messagerie : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    msgRepondre(['ok' => false, 'message' => 'Erreur du serveur. Réessayez dans un instant.'], 500);
}
