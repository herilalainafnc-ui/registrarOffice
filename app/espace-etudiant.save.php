<?php
/* =============================================================================
   Espace étudiant — publication des notes et rubriques. Réponse JSON.

   Superadmin uniquement, comme la page /settings/espace-etudiant : publier les
   notes d'une session avant l'aval du Sénat les divulgue d'un coup à tous les
   étudiants concernés.

   Actions (POST, jeton CSRF) :
     publication  session_id, publiee (1|0)
     rubrique     cle, visible (1|0)
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/espace.etudiant.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function eeRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    eeRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

if ((int) ($_SESSION['user_level'] ?? 99) !== 1) {
    eeRepondre(['ok' => false, 'message' => 'Accès refusé : réservé au superadmin.'], 403);
}

/* 400 et non 403 : src/js/session.js traite les 403 comme un refus de
   privilèges et redirige, ce qui serait faux pour un jeton expiré. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    eeRepondre(['ok' => false, 'csrf_expire' => true,
                'message' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.'], 400);
}

$action = (string) ($_POST['action'] ?? '');
$auteur = espaceEtudiantAuteur();

try {
    if ($action === 'publication') {
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $publiee   = (string) ($_POST['publiee'] ?? '') === '1';

        $r = notesPublier($dtb, $sessionId, $publiee, $auteur);
        if ($r['ok']) {
            $r['trace'] = ($publiee ? 'Publiée ' : 'Masquée ') . espaceEtudiantQuand(date('Y-m-d H:i:s'), $auteur);
        }
        eeRepondre($r, $r['ok'] ? 200 : 422);
    }

    if ($action === 'rubrique') {
        $cle     = (string) ($_POST['cle'] ?? '');
        $visible = (string) ($_POST['visible'] ?? '') === '1';

        $r = espaceEtudiantRubriqueEnregistrer($dtb, $cle, $visible, $auteur);
        if ($r['ok']) {
            $r['trace'] = 'Modifié ' . espaceEtudiantQuand(date('Y-m-d H:i:s'), $auteur);
        }
        eeRepondre($r, $r['ok'] ? 200 : 422);
    }

    eeRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);

} catch (Throwable $e) {
    error_log('espace-etudiant.save: ' . $e->getMessage());
    // Message générique : le détail PDO peut révéler la structure de la base.
    eeRepondre(['ok' => false, 'message' => "L'enregistrement a échoué."], 500);
}
