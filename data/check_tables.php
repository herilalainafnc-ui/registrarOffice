<?php
// Script temporaire pour vérifier les tables
try {
    $dtb = new PDO('mysql:host=localhost;dbname=registrar_db','root','');
    
    echo "<h3>Table t_2024_emploi_du_temps:</h3><pre>";
    $r = $dtb->query('DESCRIBE t_2024_emploi_du_temps');
    print_r($r->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    echo "<h3>Table t_2024_salles:</h3><pre>";
    $r2 = $dtb->query('DESCRIBE t_2024_salles');
    print_r($r2->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    echo "<h3>Données emploi du temps:</h3><pre>";
    $r3 = $dtb->query('SELECT * FROM t_2024_emploi_du_temps LIMIT 5');
    print_r($r3->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}
