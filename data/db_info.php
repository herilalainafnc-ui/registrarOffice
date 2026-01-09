<?php
$dtb = new PDO('mysql:host=localhost;dbname=registrar_db;charset=utf8mb4','root','');

echo "<h3>t_2023_cours:</h3><pre>";
$r = $dtb->query('SHOW COLUMNS FROM t_2023_cours');
foreach($r as $row) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "</pre><h3>t_2024_salles:</h3><pre>";
$r = $dtb->query('SHOW COLUMNS FROM t_2024_salles');
foreach($r as $row) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "</pre><h3>teacher:</h3><pre>";
$r = $dtb->query('SHOW COLUMNS FROM teacher');
foreach($r as $row) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "</pre><h3>Exemple cours:</h3><pre>";
$r = $dtb->query('SELECT id, Sigle, title, dep_desc, yearlevel, semester, id_teacher FROM t_2023_cours LIMIT 5');
print_r($r->fetchAll(PDO::FETCH_ASSOC));

echo "</pre><h3>Exemple salles:</h3><pre>";
$r = $dtb->query('SELECT * FROM t_2024_salles LIMIT 5');
print_r($r->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
