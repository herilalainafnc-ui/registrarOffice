<?php
/* =============================================================================
   Cours offerts — enregistrement

   Ce fichier a ete refait. La version precedente :

     - n'exigeait AUCUN jeton anti-CSRF et ne verifiait pas le role : tout
       compte connecte, un etudiant compris, pouvait reecrire la table entiere
       des cours offerts ;
     - ecrivait un journal de debogage en RACINE du site, en mode ajout, a
       chaque enregistrement — fichier accessible par le web, contenant un
       apercu de la charge ;
     - repondait par une redirection HTTP meme aux appels en arriere-plan, que
       le script suivait pour recevoir une page HTML entiere ;
     - renvoyait au client le message brut de l'exception PDO ;
     - reecrivait les 663 cours x 4 sessions a chaque enregistrement, soit
       2652 ecritures pour une case cochee.

   Il n'applique desormais QUE les entrees recues. C'est ce qui permet a la
   page de ne s'occuper que d'une session a la fois sans effacer les trois
   autres.
   ========================================================================== */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEnseignant(); // l enseignant ne decide pas des cours offerts
require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/cours.offerts.php');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function offRepondre(array $charge, int $code = 200) : void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    offRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

/* Meme regle que AdminController::manageCoursesSessions() : superadmin et
   registraires. Le verrou de la page ne protege que l'affichage — cette
   adresse est joignable directement. */
$privilege = (string) ($_SESSION['user_privilege'] ?? '');
$niveau = (int) ($_SESSION['user_level'] ?? 99);
if (!in_array($privilege, ['superadmin', 'registrar'], true) && $niveau !== 1) {
    offRepondre(['ok' => false,
                 'message' => 'Seuls le registrariat et les superadmins peuvent modifier les cours offerts.'], 403);
}

/* Code 400 et non 403 : src/js/session.js intercepte les 403 pour annoncer un
   refus de privileges et rediriger, ce qui serait faux pour un jeton perime.
   Et 419 n'est pas enregistre a l'IANA — ce serveur le transforme en 500. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    offRepondre(['ok' => false, 'csrf_expire' => true,
                 'message' => 'Session expirée. Rechargez la page et réessayez.'], 400);
}

$annee = trim((string) ($_POST['academic_year'] ?? ''));
if ($annee === '') {
    offRepondre(['ok' => false, 'message' => 'Année scolaire non précisée.'], 422);
}

$charge = json_decode((string) ($_POST['course_sessions_payload'] ?? ''), true);
if (!is_array($charge)) {
    offRepondre(['ok' => false, 'message' => 'Données illisibles.'], 422);
}

$sessionsAdmises = coursSessions();

try {
    $dtb->beginTransaction();

    /* Les identifiants recus sont confrontes a la table : une charge forgee ne
       doit pas creer de lignes pour des cours inexistants. */
    $connus = $dtb->query('SELECT id FROM t_2023_cours')->fetchAll(PDO::FETCH_COLUMN);
    $connus = array_flip(array_map('intval', $connus));

    $ins = $dtb->prepare(
        'INSERT INTO t_2023_course_sessions
            (course_id, session_label, academic_year, offered, date_entry, last_change)
         VALUES (:course_id, :session_label, :academic_year, :offered, NOW(), NOW())
         ON DUPLICATE KEY UPDATE offered = :offered_maj, last_change = NOW()');

    $ecrits = 0;
    $offerts = 0;
    $ignores = 0;

    foreach ($charge as $coursId => $parSession) {
        $coursId = (int) $coursId;
        if (!isset($connus[$coursId]) || !is_array($parSession)) { $ignores++; continue; }

        foreach ($parSession as $label => $valeur) {
            if (!in_array($label, $sessionsAdmises, true)) { $ignores++; continue; }

            $offert = !empty($valeur) ? 1 : 0;
            $ins->execute([
                'course_id' => $coursId,
                'session_label' => $label,
                'academic_year' => $annee,
                'offered' => $offert,
                'offered_maj' => $offert,
            ]);
            $ecrits++;
            if ($offert) { $offerts++; }
        }
    }

    $dtb->commit();

    offRepondre([
        'ok' => true,
        'message' => $offerts . ' cours offert' . ($offerts > 1 ? 's' : '')
                   . ' pour cette session.',
        'ecrits' => $ecrits,
        'offerts' => $offerts,
        'ignores' => $ignores,
    ]);

} catch (Throwable $e) {
    if ($dtb->inTransaction()) { $dtb->rollBack(); }
    /* Le detail part au journal, jamais au client : les messages PDO citent
       les requetes et les noms de colonnes. */
    error_log('Cours offerts, enregistrement : ' . $e->getMessage());
    offRepondre(['ok' => false, 'message' => "L'enregistrement n'a pas abouti."], 500);
}
