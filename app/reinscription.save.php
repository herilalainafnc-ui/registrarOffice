<?php
/* =============================================================================
   Réinitialisation des autorisations de réinscription — réponse JSON

   Point d'entrée de src/finance/authorized_reinscription.php.

   Trois actions :
     apercu         ce que la réinitialisation changerait, sans rien écrire
     reinitialiser  remise à « non coché », tracée et annulable
     annuler        restauration d'un lot, sans écraser ce qui a bougé depuis

   SUPERADMIN UNIQUEMENT, pour les trois actions. La réinitialisation touche
   d'un coup les drapeaux de tous les étudiants : seul le superadmin la voit,
   l'enclenche ou l'annule. La règle est appliquée ICI et non seulement dans la
   page, qui masque le bouton : ce fichier vit hors du routeur et une requête
   peut être forgée.

   Le filtre de colonnes par rôle (rsnFiltrerColonnes) est conservé en défense
   en profondeur, si la règle d'accès venait un jour à être élargie.

   Placé dans app/ : convention du projet pour ce qui répond des données plutôt
   qu'une page, et src/js/transitions.js s'appuie dessus pour ne pas jouer de
   transition sur ces adresses.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/reinscription.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rsnRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rsnRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

/* Superadmin uniquement : la caisse et le registraire accèdent à la page et
   gardent leurs interrupteurs, mais pas à la réinitialisation globale. */
$niveau = (int) ($_SESSION['user_level'] ?? 99);
if ($niveau !== 1) {
    rsnRepondre(['ok' => false,
                 'message' => 'Accès refusé : la réinitialisation globale est réservée au super administrateur.'], 403);
}

/* 400 plutôt que 403 : src/js/session.js intercepte les 403 pour annoncer un
   refus de privilèges et rediriger, ce qui serait faux pour un jeton expiré.
   Et pas 419 : ce code n'est pas enregistré à l'IANA et ce serveur le
   transforme en 500. Le motif voyage dans la charge utile. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    rsnRepondre(['ok' => false, 'csrf_expire' => true,
                 'message' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.'], 400);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

try {
    switch ($action) {

        case 'apercu':
            $colonnes = rsnFiltrerColonnes($_POST['colonnes'] ?? [], $niveau);
            rsnRepondre(['ok' => true, 'permises' => rsnColonnesPermises($niveau)]
                + rsnApercu($dtb, $colonnes));

        case 'reinitialiser':
            /* Phrase de confirmation revérifiée côté serveur : le bouton est
               désactivé tant qu'elle n'est pas saisie, mais une requête forgée
               ne passe pas par le bouton. Accents et casse tolérés. */
            $phrase = strtoupper(strtr(trim((string) ($_POST['confirmation'] ?? '')), ['É' => 'E', 'é' => 'E']));
            if ($phrase !== 'REINITIALISER') {
                rsnRepondre(['ok' => false,
                             'message' => 'Confirmation manquante : saisissez RÉINITIALISER pour valider.'], 422);
            }

            $colonnes = rsnFiltrerColonnes($_POST['colonnes'] ?? [], $niveau);
            if ($colonnes === []) {
                rsnRepondre(['ok' => false,
                             'message' => 'Aucune colonne choisie, ou aucune que votre rôle permet de réinitialiser.'], 422);
            }

            $resultat = rsnReinitialiser($dtb, $colonnes, $userId, $ip, $ua);
            rsnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        case 'annuler':
            $resultat = rsnAnnuler($dtb, (string) ($_POST['lot'] ?? ''), $userId, $niveau, $ip, $ua);
            rsnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        default:
            rsnRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
    }

} catch (Throwable $e) {
    if ($dtb->inTransaction()) {
        $dtb->rollBack();
    }
    error_log('reinscription.save: ' . $e->getMessage());
    /* Message générique : le détail PDO peut révéler la structure de la base. */
    rsnRepondre(['ok' => false, 'message' => "L'opération a échoué, rien n'a été modifié."], 500);
}
