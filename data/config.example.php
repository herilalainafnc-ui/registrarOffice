<?php
/**
 * =============================================================================
 * CONFIGURATION LOCALE (à ignorer dans .gitignore)
 * =============================================================================
 * 
 * Copiez ce fichier et renommez-le en config.local.php
 * Personnalisez les valeurs selon votre environnement local.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'student_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);
