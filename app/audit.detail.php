<?php
/* Journal d'activité — détail d'une action, en JSON. Superadmin uniquement. */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ((int) ($_SESSION['user_level'] ?? 99) !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Accès refusé : le journal d\'activité est réservé au super administrateur.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once(__DIR__ . '/../data/audit.lecture.php');

$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;

try {
    $detail = $id > 0 && journalDisponible($dtb) ? journalDetail($dtb, $id) : null;
    if ($detail === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Action introuvable dans le journal.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'action' => $detail], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('audit.detail : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Lecture du journal impossible.'], JSON_UNESCAPED_UNICODE);
}
