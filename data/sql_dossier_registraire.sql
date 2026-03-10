-- Migration: Ajouter champ de vérification dossier registraire
ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `dossier_ok` TINYINT(1) DEFAULT 0 COMMENT '0=dossier non validé, 1=dossier validé par registraire' AFTER `authorized_date`;

ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `dossier_checked_by` INT(11) DEFAULT NULL COMMENT 'ID de l\'utilisateur (registraire) qui a validé le dossier' AFTER `dossier_ok`;

ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `dossier_checked_date` DATETIME DEFAULT NULL COMMENT 'Date de la vérification du dossier' AFTER `dossier_checked_by`;

ALTER TABLE `tbl_2024_etudiant` 
ADD INDEX `idx_dossier_ok` (`dossier_ok`);
