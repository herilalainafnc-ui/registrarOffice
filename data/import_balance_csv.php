<?php
require __DIR__ . '/backdb.php';

$csvPath = $argv[1] ?? '';
$sessionId = isset($argv[2]) ? (int)$argv[2] : 0;

if ($csvPath === '' || $sessionId <= 0) {
    fwrite(STDERR, "Usage: php data/import_balance_csv.php <csv_path> <session_id>\n");
    exit(1);
}

if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV introuvable: {$csvPath}\n");
    exit(1);
}

$colCheck = $dtb->query("SHOW COLUMNS FROM finance LIKE 'balance_amount'");
if (!$colCheck || !$colCheck->fetch(PDO::FETCH_ASSOC)) {
    fwrite(STDERR, "La colonne finance.balance_amount est absente. Executez data/sql_finance_balance_amount.sql d'abord.\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
if ($fh === false) {
    fwrite(STDERR, "Impossible d'ouvrir le CSV.\n");
    exit(1);
}

$header = fgetcsv($fh);
if (!is_array($header)) {
    fclose($fh);
    fwrite(STDERR, "CSV vide ou invalide.\n");
    exit(1);
}

$headerMap = [];
foreach ($header as $idx => $name) {
    $key = strtolower(trim((string)$name));
    $headerMap[$key] = $idx;
}

if (!isset($headerMap['id']) || !isset($headerMap['amount bal'])) {
    fclose($fh);
    fwrite(STDERR, "Colonnes requises manquantes: ID et AMOUNT BAL.\n");
    exit(1);
}

function parseBalanceValue($raw)
{
    $v = trim((string)$raw);
    if ($v === '') {
        return null;
    }

    $negative = false;
    if (preg_match('/^\(.*\)$/', $v)) {
        $negative = true;
        $v = trim($v, '()');
    }

    $v = str_replace([' ', "\xC2\xA0", ','], '', $v);

    if ($v === '' || !is_numeric($v)) {
        return null;
    }

    $num = (float)$v;
    if ($negative) {
        $num *= -1;
    }
    return $num;
}

$updateStmt = $dtb->prepare('UPDATE finance SET balance_amount = :balance, last_change_datetime = CURRENT_TIMESTAMP WHERE session_id = :session_id AND student_id = :student_id');
$existsStmt = $dtb->prepare('SELECT 1 FROM finance WHERE session_id = :session_id AND student_id = :student_id LIMIT 1');
$insertStmt = $dtb->prepare('INSERT INTO finance (student_id, session_id, total_payment, balance_amount, last_change_datetime) VALUES (:student_id, :session_id, :total_payment, :balance, CURRENT_TIMESTAMP)');

$rowsRead = 0;
$rowsUpdated = 0;
$rowsInserted = 0;
$rowsNotFound = 0;
$rowsInvalid = 0;
$notFoundIds = [];

while (($row = fgetcsv($fh)) !== false) {
    $rowsRead++;

    $studentIdRaw = trim((string)($row[$headerMap['id']] ?? ''));
    $balanceRaw = $row[$headerMap['amount bal']] ?? '';

    $studentDigits = preg_replace('/\D+/', '', $studentIdRaw);
    $studentId = ($studentDigits !== '' && ctype_digit((string)$studentDigits)) ? (string)((int)$studentDigits) : '';

    if ($studentId === '') {
        $rowsInvalid++;
        continue;
    }

    $balance = parseBalanceValue($balanceRaw);
    if ($balance === null) {
        $rowsInvalid++;
        continue;
    }

    $existsStmt->execute([
        'session_id' => $sessionId,
        'student_id' => $studentId,
    ]);

    if ($existsStmt->fetch(PDO::FETCH_ASSOC)) {
        $updateStmt->execute([
            'balance' => $balance,
            'session_id' => $sessionId,
            'student_id' => $studentId,
        ]);
        $rowsUpdated++;
    } else {
        $insertStmt->execute([
            'student_id' => $studentId,
            'session_id' => $sessionId,
            'total_payment' => '0',
            'balance' => $balance,
        ]);
        $rowsInserted++;
    }
}

fclose($fh);

echo "Import termine.\n";
echo "Session: {$sessionId}\n";
echo "Lignes lues: {$rowsRead}\n";
echo "Lignes mises a jour: {$rowsUpdated}\n";
echo "Lignes inserees: {$rowsInserted}\n";
echo "IDs non trouves: {$rowsNotFound}\n";
echo "Lignes invalides: {$rowsInvalid}\n";
if (!empty($notFoundIds)) {
    echo "Exemples IDs non trouves: " . implode(', ', $notFoundIds) . "\n";
}
