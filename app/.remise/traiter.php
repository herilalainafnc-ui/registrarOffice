<?php
/**
 * Validation ou renvoi d'une remise (registrariat et superadmin).
 *
 * C'est le seul point d'entree de l'application qui fait passer des notes
 * d'enseignant dans t_2023_notes. Reserve aux niveaux 1 a 3 : refuserEnseignant()
 * ferme la porte a un compte professeur qui appellerait l'adresse directement.
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerNiveauNotes();

require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/remise.notes.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Methode non autorisee.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('verify_csrf') || !verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'csrf_expire' => true,
                      'message' => 'Jeton de securite expire. Rechargez la page.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!remisePeutValider()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Validation reservee au registrariat.'],
                     JSON_UNESCAPED_UNICODE);
    exit;
}

$remiseId = (int) ($_POST['remise_id'] ?? 0);
$remise   = $remiseId ? remiseCharger($dtb, $remiseId) : null;
if (!$remise) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Remise introuvable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'valider') {
    $resultat = remiseValider($dtb, $remise);
} elseif ($action === 'rejeter') {
    $resultat = remiseRejeter($dtb, $remise, (string) ($_POST['motif'] ?? ''));
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Action attendue : valider ou rejeter.'],
                     JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$resultat['ok']) {
    http_response_code(422);
}
echo json_encode($resultat, JSON_UNESCAPED_UNICODE);
