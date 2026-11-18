<?php
/**
 * Transmission d'une remise au registrariat (enseignant).
 *
 * Fige la feuille et la place dans la file de validation. Aucune note n'entre
 * dans le dossier de l'etudiant a ce stade : c'est remiseValider() qui le fait,
 * depuis app/.remise/traiter.php, et seulement pour le registrariat.
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();
refuserEtudiant();

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

$remiseId = (int) ($_POST['remise_id'] ?? 0);
$remise   = $remiseId ? remiseCharger($dtb, $remiseId) : null;
if (!$remise) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Remise introuvable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!remiseEnseignantPossedeCours($dtb, (int) $remise['cours_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Ce cours ne fait pas partie de vos enseignements.'],
                     JSON_UNESCAPED_UNICODE);
    exit;
}

$resultat = remiseSoumettre($dtb, $remise);

if (!$resultat['ok']) {
    http_response_code(422);
}
echo json_encode($resultat, JSON_UNESCAPED_UNICODE);
