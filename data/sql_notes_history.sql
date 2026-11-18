-- Script SQL pour créer la table d'historique des modifications de notes
-- À exécuter dans phpMyAdmin ou via un script PHP

-- Créer la table d'historique des modifications de notes
CREATE TABLE IF NOT EXISTS `t_notes_modification_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL COMMENT 'ID de la note modifiée dans t_2023_notes',
    `student_id` VARCHAR(50) NOT NULL COMMENT 'ID de l étudiant',
    `session_id` INT DEFAULT NULL COMMENT 'ID de la session',
    `cours_sigle` VARCHAR(50) DEFAULT NULL COMMENT 'Sigle du cours',
    `cours_titre` VARCHAR(255) DEFAULT NULL COMMENT 'Titre du cours',
    `old_grade` DECIMAL(5,2) DEFAULT NULL COMMENT 'Ancienne note',
    `new_grade` DECIMAL(5,2) DEFAULT NULL COMMENT 'Nouvelle note',
    `action_type` ENUM('creation', 'modification', 'suppression', 'restauration') NOT NULL DEFAULT 'modification',
    `action_by` INT NOT NULL COMMENT 'ID de l utilisateur qui a fait la modification',
    `action_date` DATETIME NOT NULL COMMENT 'Date et heure de la modification',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'Adresse IP de l utilisateur',
    `commentaire` TEXT DEFAULT NULL COMMENT 'Commentaire optionnel',
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_note_id` (`note_id`),
    INDEX `idx_action_by` (`action_by`),
    INDEX `idx_action_date` (`action_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des modifications de notes étudiants';

-- Ajouter des contraintes de clé étrangère (optionnel, selon votre configuration)
-- ALTER TABLE `t_notes_modification_history` 
--     ADD CONSTRAINT `fk_notes_history_user` FOREIGN KEY (`action_by`) REFERENCES `compt_utilisateur`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
