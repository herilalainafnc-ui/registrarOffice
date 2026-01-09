-- Table pour le système anti-contrefaçon des documents
-- Exécuter ce script pour créer la table de vérification

CREATE TABLE IF NOT EXISTS `t_document_verification` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doc_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Code unique du document (ex: UAZ-2026-9F3A7X)',
    `doc_hash` VARCHAR(64) NOT NULL COMMENT 'Hash SHA-256 pour validation',
    `doc_type` VARCHAR(50) NOT NULL COMMENT 'Type de document (bulletin, transcript, certificat...)',
    `student_id` VARCHAR(50) NOT NULL COMMENT 'Matricule étudiant',
    `student_name` VARCHAR(255) NOT NULL COMMENT 'Nom complet de l\'étudiant',
    `session_id` VARCHAR(50) DEFAULT NULL COMMENT 'Session concernée (si applicable)',
    `level` VARCHAR(20) DEFAULT NULL COMMENT 'Niveau (L1, L2, L3, M1, M2)',
    `semester` VARCHAR(20) DEFAULT NULL COMMENT 'Semestre',
    `doc_data` JSON DEFAULT NULL COMMENT 'Données supplémentaires du document en JSON',
    `date_emission` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'émission',
    `date_expiration` DATETIME DEFAULT NULL COMMENT 'Date d\'expiration (si applicable)',
    `statut` ENUM('valide', 'annule', 'expire', 'suspendu') NOT NULL DEFAULT 'valide' COMMENT 'Statut du document',
    `motif_annulation` TEXT DEFAULT NULL COMMENT 'Motif si annulé',
    `emis_par` VARCHAR(100) DEFAULT NULL COMMENT 'Utilisateur qui a émis le document',
    `nb_verifications` INT DEFAULT 0 COMMENT 'Nombre de fois vérifié',
    `derniere_verification` DATETIME DEFAULT NULL COMMENT 'Date de dernière vérification',
    `ip_derniere_verif` VARCHAR(45) DEFAULT NULL COMMENT 'IP de dernière vérification',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_student` (`student_id`),
    INDEX `idx_doc_code` (`doc_code`),
    INDEX `idx_doc_hash` (`doc_hash`),
    INDEX `idx_statut` (`statut`),
    INDEX `idx_date_emission` (`date_emission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour l'historique des vérifications
CREATE TABLE IF NOT EXISTS `t_verification_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doc_code` VARCHAR(20) NOT NULL,
    `verification_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `resultat` ENUM('valide', 'invalide', 'annule', 'expire', 'non_trouve') NOT NULL,
    `pays` VARCHAR(100) DEFAULT NULL,
    
    INDEX `idx_doc_code` (`doc_code`),
    INDEX `idx_date` (`verification_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
