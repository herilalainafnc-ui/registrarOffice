<?php
/**
 * Le registrariat marque un signalement d'absences (§476) comme traite.
 * Appele par src/presence.php ; reponse JSON.
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();
exigerPrivileges(['superadmin', 'registrar'], 'Seuls le superadmin et le registrariat traitent les signalements.');

require_once(__DIR__ . '/../../data/backdb.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Methode non autorisee.']);
    exit;
}
if (!function_exists('verify_csrf') || !verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Jeton de securite expire. Rechargez la page.']);
    exit;
}

$stmt = $dtb->prepare('UPDATE t_presence_signalement SET traite_le = :le, traite_par = :par
    WHERE id = :id AND traite_le IS NULL');
$stmt->execute(['le' => date('Y-m-d H:i:s'), 'par' => $_SESSION['user_id'] ?? null,
                'id' => (int) ($_POST['signalement_id'] ?? 0)]);

echo json_encode(['ok' => true, 'traites' => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);
