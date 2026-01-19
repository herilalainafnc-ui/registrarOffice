<?php
/**
 * Connexion à la base de données
 * Avec initialisation de la classe DB pour requêtes sécurisées
 */

try {
    // $dtb = new PDO('mysql:host=localhost;dbname=registrar_db','herilalaina','J8wFF(FOy1KI(nay');
    $dtb = new PDO('mysql:host=localhost;dbname=registrar_db;charset=utf8mb4','root','');
    $dtb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dtb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Charger et initialiser la classe DB pour requêtes sécurisées
    require_once(__DIR__ . '/database.php');
    DB::init($dtb);
    
} catch(PDOException $e) {
    echo "Connexion DB incorrect ☻ : " . $e->getMessage();
    exit();
}

