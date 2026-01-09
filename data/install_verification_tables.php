<?php
/**
 * Script d'installation des tables de vérification des documents
 * Exécuter une seule fois : php install_verification_tables.php
 */

require_once('connectdb.php');

echo "=== Installation des tables de vérification ===\n\n";

try {
    // Table principale de vérification
    $sql1 = "CREATE TABLE IF NOT EXISTS `t_document_verification` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Code unique du document',
        `doc_hash` VARCHAR(64) NOT NULL COMMENT 'Hash SHA-256',
        `doc_type` VARCHAR(50) NOT NULL COMMENT 'Type de document',
        `student_id` VARCHAR(50) NOT NULL COMMENT 'Matricule étudiant',
        `student_name` VARCHAR(255) NOT NULL COMMENT 'Nom complet',
        `session_id` VARCHAR(50) DEFAULT NULL,
        `level` VARCHAR(20) DEFAULT NULL,
        `semester` VARCHAR(20) DEFAULT NULL,
        `doc_data` JSON DEFAULT NULL,
        `date_emission` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_expiration` DATETIME DEFAULT NULL,
        `statut` ENUM('valide', 'annule', 'expire', 'suspendu') NOT NULL DEFAULT 'valide',
        `motif_annulation` TEXT DEFAULT NULL,
        `emis_par` VARCHAR(100) DEFAULT NULL,
        `nb_verifications` INT DEFAULT 0,
        `derniere_verification` DATETIME DEFAULT NULL,
        `ip_derniere_verif` VARCHAR(45) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_student` (`student_id`),
        INDEX `idx_doc_code` (`doc_code`),
        INDEX `idx_statut` (`statut`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $dtb->exec($sql1);
    echo "[OK] Table t_document_verification créée\n";

    // Table des logs de vérification
    $sql2 = "CREATE TABLE IF NOT EXISTS `t_verification_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_code` VARCHAR(20) NOT NULL,
        `verification_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `resultat` ENUM('valide', 'invalide', 'annule', 'expire', 'non_trouve') NOT NULL,
        `pays` VARCHAR(100) DEFAULT NULL,
        INDEX `idx_doc_code` (`doc_code`),
        INDEX `idx_date` (`verification_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $dtb->exec($sql2);
    echo "[OK] Table t_verification_log créée\n";

    echo "\n=== Installation terminée avec succès! ===\n";

} catch (PDOException $e) {
    echo "[ERREUR] " . $e->getMessage() . "\n";
}
