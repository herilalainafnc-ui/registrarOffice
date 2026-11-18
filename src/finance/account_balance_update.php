<?php
require('../data/session.php');
require('../data/backdb.php');

header('Content-Type: application/json; charset=UTF-8');

if (!isset($rg_user) || (int)($rg_user['level'] ?? 99) > 3) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'method_not_allowed']);
    exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);
$studentId = trim((string)($_POST['student_id'] ?? ''));
$balanceRaw = trim((string)($_POST['balance_amount'] ?? ''));

if ($sessionId <= 0 || $studentId === '' || $balanceRaw === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'missing_fields']);
    exit;
}

$balanceRaw = str_replace([' ', "\xC2\xA0"], '', $balanceRaw);
$balanceRaw = str_replace(',', '.', $balanceRaw);

if (!is_numeric($balanceRaw)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'invalid_balance']);
    exit;
}

$balanceAmount = (float)$balanceRaw;

$stmt = $dtb->prepare('UPDATE finance SET balance_amount = :balance_amount WHERE session_id = :session_id AND student_id = :student_id');
$stmt->execute([
    'balance_amount' => $balanceAmount,
    'session_id' => $sessionId,
    'student_id' => $studentId,
]);

if ($stmt->rowCount() <= 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'finance_row_not_found']);
    exit;
}

echo json_encode([
    'success' => true,
    'session_id' => $sessionId,
    'student_id' => $studentId,
    'balance_amount' => $balanceAmount,
]);
