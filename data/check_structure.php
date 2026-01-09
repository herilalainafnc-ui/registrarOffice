<?php
$dtb = new PDO('mysql:host=localhost;dbname=registrar_db;charset=utf8mb4','root','');

echo "<h3>Table t_2023_cours:</h3><pre>";
$r = $dtb->query('DESCRIBE t_2023_cours');
print_r($r->fetchAll(PDO::FETCH_ASSOC));

echo "</pre><h3>Table t_2024_emploi_du_temps:</h3><pre>";
$r = $dtb->query('DESCRIBE t_2024_emploi_du_temps');
print_r($r->fetchAll(PDO::FETCH_ASSOC));

echo "</pre><h3>Table t_2024_salles:</h3><pre>";
$r = $dtb->query('DESCRIBE t_2024_salles');
print_r($r->fetchAll(PDO::FETCH_ASSOC));

echo "</pre><h3>Table teacher:</h3><pre>";
$r = $dtb->query('DESCRIBE teacher');
print_r($r->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
