<?php
require __DIR__ . '/backdb.php';

$row = $dtb->query('SHOW CREATE TABLE finance')->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Table finance introuvable\n";
    exit(1);
}

echo ($row['Create Table'] ?? '') . "\n";
