<?php
/**
 * Script de sauvegarde automatique de la base de données
 * Exporte registrar_db vers C:\Users\REGISTRAR\Desktop\Backup
 * Format du fichier: registrar_db-DD-MM-YYYY.sql
 */

// Configuration de la base de données
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';  // Mot de passe MySQL (vide par défaut sur XAMPP)
$db_name = 'registrar_db';

// Configuration du backup
$backup_dir = 'C:\\Users\\REGISTRAR\\Desktop\\Backup';
$mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

// Créer le répertoire de backup s'il n'existe pas
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
    echo "Répertoire de backup créé: $backup_dir\n";
}

// Générer le nom du fichier avec la date actuelle (format: DD-MM-YYYY)
$date = date('d-m-Y');
$backup_file = $backup_dir . '\\' . $db_name . '-' . $date . '.sql';

// Construire la commande mysqldump
// Options identiques à phpMyAdmin pour un export complet:
// --single-transaction : évite les locks sur les tables InnoDB
// --routines : inclut les procédures stockées et fonctions
// --triggers : inclut les triggers
// --events : inclut les événements
// --add-drop-table : ajoute DROP TABLE avant CREATE
// --add-drop-database : ajoute DROP DATABASE avant CREATE
// --create-options : inclut toutes les options de création de table
// --skip-extended-insert : un INSERT par ligne (comme phpMyAdmin)
// --complete-insert : INSERT avec noms de colonnes
// --quick : lecture ligne par ligne (moins de mémoire)
// --quote-names : met les noms entre backticks
// --hex-blob : exporte les BLOB en hexadécimal
// --set-charset : ajoute SET NAMES
// --force : continue même en cas d'erreur SQL
$command = sprintf(
    '"%s" --host=%s --user=%s %s --single-transaction --quick --routines --triggers --events --add-drop-table --add-drop-database --create-options --skip-extended-insert --complete-insert --quote-names --hex-blob --set-charset --default-character-set=utf8mb4 --force %s 2>&1',
    $mysqldump_path,
    $db_host,
    $db_user,
    $db_pass ? '--password=' . escapeshellarg($db_pass) : '',
    $db_name
);

// Exécuter mysqldump et capturer la sortie
$backup_content = shell_exec($command);

// Filtrer les lignes d'erreur de definer et écrire dans le fichier
if ($backup_content) {
    // Supprimer les messages d'erreur de la sortie
    $lines = explode("\n", $backup_content);
    $filtered_lines = array_filter($lines, function($line) {
        return strpos($line, 'mysqldump:') !== 0 && strpos($line, 'Warning:') !== 0;
    });
    $backup_content = implode("\n", $filtered_lines);
    file_put_contents($backup_file, $backup_content);
}

// Variable pour compatibilité avec le reste du code
$command_display = "mysqldump $db_name > $backup_file";

// Exécuter la commande
echo "Démarrage de la sauvegarde de la base de données...\n";
echo "Date: " . date('d/m/Y H:i:s') . "\n";
echo "Base de données: $db_name\n";
echo "Fichier de destination: $backup_file\n\n";

// La commande est déjà exécutée ci-dessus via shell_exec

if (file_exists($backup_file) && filesize($backup_file) > 1000) {
    $file_size = filesize($backup_file);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    
    // Vérifier que le fichier contient bien des CREATE TABLE
    $content_check = file_get_contents($backup_file, false, null, 0, 50000);
    $table_count = substr_count($content_check, 'CREATE TABLE');
    
    echo "✓ Sauvegarde réussie!\n";
    echo "Taille du fichier: {$file_size_mb} MB\n";
    echo "Tables détectées (partiel): ~{$table_count}+\n";
    
    // Log de la sauvegarde
    $log_file = $backup_dir . '\\backup_log.txt';
    $log_entry = sprintf(
        "[%s] Sauvegarde réussie - Fichier: %s - Taille: %s MB\n",
        date('d/m/Y H:i:s'),
        basename($backup_file),
        $file_size_mb
    );
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Supprimer les anciennes sauvegardes (garder les 30 derniers jours)
    cleanOldBackups($backup_dir, 30);
    
} else {
    echo "✗ Erreur lors de la sauvegarde!\n";
    if (!empty($output)) {
        echo "Détails: " . implode("\n", $output) . "\n";
    }
    
    // Log de l'erreur
    $log_file = $backup_dir . '\\backup_log.txt';
    $log_entry = sprintf(
        "[%s] ERREUR - %s\n",
        date('d/m/Y H:i:s'),
        implode(' ', $output)
    );
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Supprime les sauvegardes plus anciennes que X jours
 */
function cleanOldBackups($dir, $days) {
    $files = glob($dir . '\\registrar_db-*.sql');
    $now = time();
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $days * 24 * 60 * 60) {
                unlink($file);
                $deleted++;
            }
        }
    }
    
    if ($deleted > 0) {
        echo "\nNettoyage: $deleted ancienne(s) sauvegarde(s) supprimée(s) (> $days jours)\n";
    }
}

echo "\nOpération terminée.\n";
