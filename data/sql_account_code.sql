-- Migration: Ajouter la colonne account_code
-- Description: Stocke le code compte étudiant séparément de lookup_code
-- Date: 2026-04-13

ALTER TABLE `tbl_2024_etudiant`
ADD COLUMN `account_code` VARCHAR(100) DEFAULT NULL COMMENT 'Code compte étudiant importé' AFTER `lookup_code`;

ALTER TABLE `tbl_2024_etudiant`
ADD INDEX `idx_account_code` (`account_code`);
