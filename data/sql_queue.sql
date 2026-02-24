-- =============================================================================
-- SYSTÈME DE FILE D'ATTENTE - INSCRIPTIONS UAZ
-- =============================================================================
-- Tables pour la gestion des tickets et des appels groupés
-- Date: 2026
-- =============================================================================

-- Table principale des sessions de file d'attente
CREATE TABLE IF NOT EXISTS `t_queue_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_name` VARCHAR(100) NOT NULL COMMENT 'Ex: Inscription S1 2026',
    `session_date` DATE NOT NULL,
    `status` ENUM('active', 'paused', 'closed') DEFAULT 'active',
    `last_ticket_number` INT DEFAULT 0 COMMENT 'Dernier numéro de ticket généré',
    `created_by` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tickets individuels
CREATE TABLE IF NOT EXISTS `t_queue_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `ticket_number` INT NOT NULL COMMENT 'Numéro affiché (001, 002...)',
    `student_name` VARCHAR(100) DEFAULT NULL COMMENT 'Nom optionnel',
    `student_id` VARCHAR(50) DEFAULT NULL COMMENT 'Matricule optionnel',
    `mention` VARCHAR(50) DEFAULT NULL COMMENT 'Mention demandée',
    `status` ENUM('waiting', 'called', 'serving', 'done', 'skipped') DEFAULT 'waiting',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `called_at` DATETIME DEFAULT NULL,
    `served_at` DATETIME DEFAULT NULL,
    `called_by` INT DEFAULT NULL COMMENT 'Agent qui a appelé',
    FOREIGN KEY (`session_id`) REFERENCES `t_queue_sessions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_session_ticket` (`session_id`, `ticket_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_session_status` (`session_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des appels groupés (batch calls)
CREATE TABLE IF NOT EXISTS `t_queue_batch_calls` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `ticket_numbers` JSON NOT NULL COMMENT 'Liste des numéros appelés: [12, 13, 14]',
    `batch_size` INT NOT NULL DEFAULT 1,
    `called_by` INT NOT NULL,
    `called_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `announcement_text` VARCHAR(500) DEFAULT NULL COMMENT 'Texte affiché sur écran public',
    FOREIGN KEY (`session_id`) REFERENCES `t_queue_sessions`(`id`) ON DELETE CASCADE,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_called_at` (`called_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
