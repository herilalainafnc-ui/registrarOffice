-- =============================================================================
-- Journal d'activité (page Réglages > Journal d'activité)
--
-- t_audit_requete       une ligne par action : connexion, échec de connexion,
--                       déconnexion, modification, action, export, consultation
-- t_audit_modification  une ligne par écriture en base faite pendant l'action,
--                       avec les valeurs avant et après
--
-- Rejouable. data/audit.php crée aussi ces tables d'elle-même si elles
-- manquent, à la première action journalisée.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `t_audit_requete` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `date_action` DATETIME(3) NOT NULL,
  `duree_ms` INT UNSIGNED NULL,
  `type_action` VARCHAR(20) NOT NULL,
  `libelle` VARCHAR(255) NOT NULL,
  `user_id` INT NULL,
  `user_pseudo` VARCHAR(100) NULL,
  `user_nom` VARCHAR(200) NULL,
  `user_niveau` TINYINT NULL,
  `methode` VARCHAR(10) NOT NULL,
  `url` VARCHAR(2000) NOT NULL,
  `chemin` VARCHAR(500) NOT NULL,
  `referent` VARCHAR(1000) NULL,
  `statut_http` SMALLINT NULL,
  `redirection` VARCHAR(1000) NULL,
  `ajax` TINYINT(1) NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NULL,
  `appareil` VARCHAR(120) NULL,
  `user_agent` VARCHAR(500) NULL,
  `session_empreinte` CHAR(16) NULL,
  `donnees` MEDIUMTEXT NULL,
  `fichiers` TEXT NULL,
  `evenements` TEXT NULL,
  `nb_modifications` INT UNSIGNED NOT NULL DEFAULT 0,
  `modifications_ignorees` INT UNSIGNED NOT NULL DEFAULT 0,
  `tables_touchees` VARCHAR(1000) NULL,
  `erreur` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_date` (`date_action`),
  KEY `idx_audit_user_date` (`user_id`, `date_action`),
  KEY `idx_audit_type_date` (`type_action`, `date_action`),
  KEY `idx_audit_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `t_audit_modification` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requete_id` BIGINT UNSIGNED NOT NULL,
  `ordre` SMALLINT UNSIGNED NOT NULL,
  `date_action` DATETIME(3) NOT NULL,
  `operation` VARCHAR(12) NOT NULL,
  `table_nom` VARCHAR(64) NULL,
  `lignes` INT NULL,
  `requete_sql` TEXT NOT NULL,
  `parametres` TEXT NULL,
  `changements` MEDIUMTEXT NULL,
  `transaction_statut` VARCHAR(10) NOT NULL DEFAULT 'hors',
  `erreur` VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_modif_requete` (`requete_id`),
  KEY `idx_audit_modif_table` (`table_nom`, `date_action`),
  CONSTRAINT `fk_audit_modif_requete` FOREIGN KEY (`requete_id`) REFERENCES `t_audit_requete` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
