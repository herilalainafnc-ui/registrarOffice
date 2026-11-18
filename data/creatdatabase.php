<?php  
/**
 * Script d'installation de la base de données
 * Crée la base de données et les tables initiales
 */
require_once __DIR__ . '/config.php';

try {
    // Connexion sans spécifier de base de données pour la création
    $dtb = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, DB_OPTIONS);
} catch(PDOException $e) {
    die('Erreur de connexion: '. $e->getMessage());
}

// Création de la base de données
$dtb->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Reconnexion à la base nouvellement créée
$dtb = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

// Création de la table utilisateurs
$dtb->exec('CREATE TABLE IF NOT EXISTS rg_user(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(50),
    user_last_name VARCHAR(50),
    user_pseudo VARCHAR(50),
    user_password VARCHAR(255),
    user_photos VARCHAR(100),
    date_entry DATE,
    modification_id INT,
    modification_date DATE
)');

// Vérifier si l'utilisateur par défaut existe déjà
$check = $dtb->query("SELECT COUNT(*) FROM rg_user WHERE user_pseudo = 'Herilalaina'")->fetchColumn();
if ($check == 0) {
    $defaultUser = $dtb->prepare('INSERT INTO rg_user(user_name, user_last_name, user_pseudo, user_password) 
        VALUES (:name, :last_name, :pseudo, :password)');
    $defaultUser->execute([
        'name' => 'Ramahoherilalaina',
        'last_name' => 'Lovasoa Jimmy',
        'pseudo' => 'Herilalaina',
        'password' => password_hash('Herilalaina2804', PASSWORD_DEFAULT)
    ]);
}

echo "Base de données installée avec succès !";
?>