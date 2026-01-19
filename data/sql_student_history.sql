-- =====================================================
-- TABLE: t_student_modification_history
-- Historique des modifications d'informations étudiantes
-- =====================================================

CREATE TABLE IF NOT EXISTS `t_student_modification_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` VARCHAR(50) NOT NULL COMMENT 'Matricule étudiant',
    `student_db_id` INT(11) NOT NULL COMMENT 'ID dans la table tbl_2024_etudiant',
    `field_name` VARCHAR(100) NOT NULL COMMENT 'Nom du champ modifié',
    `field_label` VARCHAR(150) NOT NULL COMMENT 'Libellé du champ en français',
    `old_value` TEXT DEFAULT NULL COMMENT 'Ancienne valeur',
    `new_value` TEXT DEFAULT NULL COMMENT 'Nouvelle valeur',
    `action_type` ENUM('creation', 'modification', 'suppression', 'suspension', 'levee_suspension', 'retrait', 'annulation_retrait', 'image') NOT NULL DEFAULT 'modification' COMMENT 'Type d action',
    `action_by` INT(11) NOT NULL COMMENT 'ID utilisateur qui a effectué la modification',
    `action_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date et heure de la modification',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'Adresse IP',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT 'Navigateur utilisé',
    `commentaire` TEXT DEFAULT NULL COMMENT 'Commentaire optionnel',
    PRIMARY KEY (`id`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_student_db_id` (`student_db_id`),
    INDEX `idx_action_date` (`action_date`),
    INDEX `idx_action_by` (`action_by`),
    INDEX `idx_action_type` (`action_type`),
    INDEX `idx_field_name` (`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des modifications d informations étudiantes';
