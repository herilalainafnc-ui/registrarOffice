-- Script SQL pour ajouter les colonnes de suspension à la table tbl_2024_etudiant
-- Exécuter ce script dans phpMyAdmin ou via MySQL

-- Ajouter les colonnes de suspension à la table étudiants
ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `suspended` TINYINT(1) DEFAULT 0 COMMENT 'Statut suspendu: 0=non, 1=oui',
ADD COLUMN `date_debut_suspension` DATE DEFAULT NULL COMMENT 'Date début de suspension',
ADD COLUMN `date_fin_suspension` DATE DEFAULT NULL COMMENT 'Date fin de suspension',
ADD COLUMN `motif_suspension` TEXT DEFAULT NULL COMMENT 'Motif de la suspension',
ADD COLUMN `suspended_by` INT DEFAULT NULL COMMENT 'ID utilisateur qui a suspendu',
ADD COLUMN `suspended_date` DATETIME DEFAULT NULL COMMENT 'Date/heure de l''action de suspension';

-- Créer la table d'historique des suspensions
CREATE TABLE IF NOT EXISTS `t_suspension_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(50) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE DEFAULT NULL,
  `motif` TEXT DEFAULT NULL,
  `action_by` INT NOT NULL COMMENT 'ID utilisateur qui a effectué l''action',
  `action_date` DATETIME NOT NULL,
  `action_type` ENUM('suspension', 'levee', 'modification') NOT NULL DEFAULT 'suspension',
  PRIMARY KEY (`id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_action_date` (`action_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des suspensions étudiants';

-- Index pour optimiser les requêtes de vérification de suspension
ALTER TABLE `tbl_2024_etudiant` ADD INDEX `idx_suspended` (`suspended`);
ALTER TABLE `tbl_2024_etudiant` ADD INDEX `idx_suspension_dates` (`date_debut_suspension`, `date_fin_suspension`);
