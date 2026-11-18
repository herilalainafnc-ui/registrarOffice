-- Migration: Ajouter champ d'autorisation de réinscription
-- Description: Permet aux caissiers d'autoriser ou non les réinscriptions
-- Date: 2026-03-05

ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `authorized_reinscription` TINYINT(1) DEFAULT 0 COMMENT '0=non autorisé, 1=autorisé pour réinscription' AFTER `suspended`;

ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `authorized_by` INT(11) DEFAULT NULL COMMENT 'ID de l\'utilisateur qui a donné l\'autorisation' AFTER `authorized_reinscription`;

ALTER TABLE `tbl_2024_etudiant` 
ADD COLUMN `authorized_date` DATETIME DEFAULT NULL COMMENT 'Date de l\'autorisation de réinscription' AFTER `authorized_by`;

-- Index pour performance
ALTER TABLE `tbl_2024_etudiant` 
ADD INDEX `idx_authorized_reinscription` (`authorized_reinscription`);
