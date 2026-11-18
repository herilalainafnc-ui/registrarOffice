-- =====================================================
-- SQL pour la fonctionnalité de retrait de l'université
-- =====================================================

-- Ajouter les colonnes dans la table tbl_2024_etudiant
-- Exécuter chaque instruction séparément si nécessaire

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `retrait_universite` TINYINT(1) DEFAULT 0 COMMENT '0=actif, 2=retrait momentané, 3=retrait définitif';

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `type_retrait` VARCHAR(20) DEFAULT NULL COMMENT 'momentane ou definitif';

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `date_entree_universite` DATE DEFAULT NULL;

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `date_depart_universite` DATE DEFAULT NULL;

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `date_retour_probable` DATE DEFAULT NULL;

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `cause_depart` VARCHAR(100) DEFAULT NULL;

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `details_cause_depart` TEXT DEFAULT NULL;

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `retrait_by` INT(11) DEFAULT NULL;

ALTER TABLE `tbl_2024_etudiant` ADD COLUMN `retrait_date` DATETIME DEFAULT NULL;


-- Créer la table d'historique des retraits
CREATE TABLE IF NOT EXISTS `t_retrait_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(50) NOT NULL,
  `type_retrait` VARCHAR(20) NOT NULL COMMENT 'momentane ou definitif',
  `date_entree_universite` DATE DEFAULT NULL,
  `date_depart` DATE NOT NULL,
  `date_retour_probable` DATE DEFAULT NULL,
  `cause_depart` VARCHAR(100) NOT NULL,
  `details_cause` TEXT DEFAULT NULL,
  `action_by` INT(11) NOT NULL,
  `action_date` DATETIME NOT NULL,
  `action_type` VARCHAR(50) NOT NULL COMMENT 'retrait, annulation_retrait, retour',
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_action_date` (`action_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
