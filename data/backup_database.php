<?php
/**
 * Script de sauvegarde automatique de la base de données
 * Exporte registrar_db vers C:\Users\REGISTRAR\Desktop\Backup
 * Format du fichier: registrar_db-DD-MM-YYYY.sql
 * 
 * Post-traitement ligne par ligne pour économiser la mémoire :
 *  - Suppression des clauses DEFINER (évite erreur #1227 à l'import)
 *  - Nettoyage de la syntaxe des EVENTs
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
$raw_file    = $backup_file . '.raw';  // fichier temporaire brut

echo "Démarrage de la sauvegarde de la base de données...\n";
echo "Date: " . date('d/m/Y H:i:s') . "\n";
echo "Base de données: $db_name\n";
echo "Fichier de destination: $backup_file\n\n";

// ─── Étape 1 : mysqldump directement vers fichier (zéro mémoire PHP) ───
$command = sprintf(
    '"%s" --host=%s --user=%s %s --single-transaction --quick --routines --triggers --events --add-drop-table --add-drop-database --create-options --skip-extended-insert --complete-insert --quote-names --hex-blob --set-charset --default-character-set=utf8mb4 --force %s > "%s" 2>nul',
    $mysqldump_path,
    $db_host,
    $db_user,
    $db_pass ? '--password=' . escapeshellarg($db_pass) : '',
    $db_name,
    $raw_file
);

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// Vérifier que le dump brut a été créé
if (!file_exists($raw_file) || filesize($raw_file) < 1000) {
    echo "✗ Erreur lors du dump mysqldump!\n";
    $log_file = $backup_dir . '\\backup_log.txt';
    $log_entry = sprintf("[%s] ERREUR - mysqldump a échoué (code: %d)\n", date('d/m/Y H:i:s'), $return_var);
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    @unlink($raw_file);
    exit(1);
}

// ─── Étape 2 : Post-traitement ligne par ligne (économie mémoire) ───
// Lit le fichier brut ligne par ligne, nettoie et écrit dans le fichier final
$in  = fopen($raw_file, 'r');
$out = fopen($backup_file, 'w');

if (!$in || !$out) {
    echo "✗ Impossible d'ouvrir les fichiers pour le post-traitement!\n";
    exit(1);
}

$eventBuffer = '';
$inEventBlock = false;

while (($line = fgets($in)) !== false) {
    // Ignorer les lignes d'erreur/warning de mysqldump
    if (strpos($line, 'mysqldump:') === 0 || strpos($line, 'Warning:') === 0) {
        continue;
    }

    // Supprimer les clauses DEFINER (commentaires versionnés et SQL pur)
    $line = preg_replace('/\/\*!\d+\s+DEFINER\s*=\s*`[^`]*`\s*@\s*`[^`]*`\s*(SQL\s+SECURITY\s+\w+\s*)?\*\//', '', $line);
    $line = preg_replace('/\bDEFINER\s*=\s*`[^`]*`\s*@\s*`[^`]*`\s*/', '', $line);
    $line = preg_replace('/\bSQL\s+SECURITY\s+DEFINER\b/', '', $line);
    $line = preg_replace('/\bALGORITHM\s*=\s*UNDEFINED\b/', '', $line);

    // Nettoyer les espaces multiples
    $line = preg_replace('/  +/', ' ', $line);

    // Détecter et accumuler les blocs EVENT (multi-ligne)
    if (preg_match('/\/\*!50106\s+CREATE\*\//', $line)) {
        $inEventBlock = true;
        $eventBuffer = $line;
        continue;
    }

    if ($inEventBlock) {
        $eventBuffer .= $line;
        // Un EVENT se termine par */ suivi de ;
        if (preg_match('/\*\/\s*;\s*$/', $line)) {
            $inEventBlock = false;
            // Traiter le bloc EVENT complet
            $cleaned = fixEventBlock($eventBuffer);
            fwrite($out, $cleaned);
            $eventBuffer = '';
        }
        continue;
    }

    fwrite($out, $line);
}

// Flush le buffer EVENT résiduel (par sécurité)
if ($eventBuffer !== '') {
    fwrite($out, fixEventBlock($eventBuffer));
}

fclose($in);
fclose($out);

// Supprimer le fichier brut temporaire
@unlink($raw_file);

// ─── Étape 3 : Vérification et log ───
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
        "[%s] ERREUR - Fichier vide ou trop petit\n",
        date('d/m/Y H:i:s')
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

/**
 * Traite un bloc EVENT complet accumulé depuis le fichier brut.
 * Transforme le format mysqldump versioned-comment en SQL propre.
 */
function fixEventBlock($block) {
    // Pattern avec DEFINER séparé
    $pattern = '/\/\*!50106\s+CREATE\*\/\s*(?:\/\*!50117\s+DEFINER=`[^`]*`@`[^`]*`\s*\*\/\s*)?\/\*!50106\s+(EVENT\s+.*?END)\s*\*\/\s*;/s';

    $result = preg_replace_callback($pattern, 'reformatEvent', $block);
    if ($result !== null && $result !== $block) {
        return $result;
    }

    // Pattern sans le DEFINER séparé
    $pattern2 = '/\/\*!50106\s+CREATE\*\/\s*\/\*!50106\s+(EVENT\s+.*?END)\s*\*\/\s*;/s';
    $result = preg_replace_callback($pattern2, 'reformatEvent', $block);
    if ($result !== null) {
        return $result;
    }

    // Si aucun pattern ne matche, retourner le bloc tel quel
    return $block;
}

/**
 * Reformate un bloc EVENT capturé par regex en SQL lisible
 */
function reformatEvent($matches) {
    $eventBody = trim($matches[1]);

    // Extraire le nom de l'event
    $eventName = '';
    if (preg_match('/EVENT\s+`?(\w+)`?/i', $eventBody, $nameMatch)) {
        $eventName = $nameMatch[1];
    }

    // Normaliser les espaces
    $eventBody = preg_replace('/\s+/', ' ', $eventBody);

    // Reformater les clauses principales
    $eventBody = preg_replace('/\bON\s+SCHEDULE\b/i', "\nON SCHEDULE", $eventBody);
    $eventBody = preg_replace('/\bEVERY\b/i', "\nEVERY", $eventBody);
    $eventBody = preg_replace('/\bSTARTS\b/i', "\nSTARTS", $eventBody);
    $eventBody = preg_replace('/\bON\s+COMPLETION\b/i', "\nON COMPLETION", $eventBody);
    $eventBody = preg_replace('/\bNOT\s+PRESERVE\b/i', "NOT PRESERVE", $eventBody);
    $eventBody = preg_replace('/\bENABLE\s+DO\b/i', "ENABLE\nDO", $eventBody);
    $eventBody = preg_replace('/\bDISABLE\s+DO\b/i', "DISABLE\nDO", $eventBody);
    $eventBody = preg_replace('/\bDO\s+BEGIN\b/i', "DO\nBEGIN\n", $eventBody);

    // Séparer les statements SQL dans le bloc BEGIN...END
    $eventBody = preg_replace('/;\s*(?=\S)(?!;)/', ";\n\n", $eventBody);

    // Assurer un retour à la ligne avant END final
    $eventBody = preg_replace('/;\s*END$/i', ";\n\nEND", $eventBody);

    $result = '';
    if ($eventName) {
        $result .= "DROP EVENT IF EXISTS `$eventName`;\n";
    }
    $result .= "DELIMITER ;;\n";
    $result .= "CREATE " . $eventBody . ";;\n";
    $result .= "DELIMITER ;\n";

    return $result;
}

echo "\nOpération terminée.\n";
