<?php
/**
 * =============================================================================
 * CONNEXION CENTRALISÉE À LA BASE DE DONNÉES
 * =============================================================================
 * 
 * Ce fichier gère la connexion à la base de données de manière centralisée.
 * La configuration est chargée depuis config.php
 */

require_once __DIR__ . '/config.php';

try {
    // Connexion avec les paramètres centralisés
    $dtb = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    
    // Initialiser la classe DB pour les requêtes sécurisées
    if (class_exists('DB')) {
        DB::init($dtb);
    }
    
    // Lever automatiquement les suspensions expirées
    $dtb->exec("UPDATE tbl_2024_etudiant SET suspended = 0 WHERE suspended = 1 AND date_fin_suspension IS NOT NULL AND date_fin_suspension < CURDATE()");

} catch (PDOException $e) {
    // En mode développement, afficher l'erreur
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Erreur de connexion: " . $e->getMessage());
    }
    // En production, rediriger vers la page de création
    die(header('location:../data/creatdatabase.php'));
}
?>