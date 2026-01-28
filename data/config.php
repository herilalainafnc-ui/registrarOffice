<?php
/**
 * =============================================================================
 * CONFIGURATION CENTRALISÉE DE LA BASE DE DONNÉES
 * =============================================================================
 * 
 * Ce fichier contient toutes les configurations de connexion à la base de données.
 * Modifiez uniquement ce fichier pour changer les paramètres de connexion.
 * 
 * IMPORTANT: Ne jamais commiter ce fichier avec les vrais identifiants en production!
 * Créez un fichier config.local.php pour les paramètres locaux.
 */

// Charger la configuration locale si elle existe (pour le développement)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
    return;
}

// ============================================================================
// CONFIGURATION PAR DÉFAUT
// ============================================================================

// Paramètres du serveur
define('DB_HOST', 'localhost');
define('DB_USER', 'herilalaina');
define('DB_PASS', 'J8wFF(FOy1KI(nay');
define('DB_CHARSET', 'utf8mb4');

// Nom de la base de données principale
define('DB_NAME', 'registrar_db');

// Mode debug (à désactiver en production)
define('DEBUG_MODE', true);

// Options PDO par défaut
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// DSN (Data Source Name) complet
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

/**
 * Fonction utilitaire pour créer une connexion PDO
 * 
 * @param string|null $dbName Nom de la base (utilise DB_NAME par défaut)
 * @return PDO
 */
function getDbConnection($dbName = null) {
    $dbName = $dbName ?? DB_NAME;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . $dbName . ';charset=' . DB_CHARSET;
    
    return new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
}
