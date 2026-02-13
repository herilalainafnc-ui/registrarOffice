-- =============================================================================
-- TABLE LOGIN_LOCATIONS - Historique des localisations GPS lors des connexions
-- =============================================================================
-- Cette table enregistre les coordonnées GPS de chaque connexion utilisateur
-- pour suivre d'où les utilisateurs se connectent.
--
-- À exécuter dans phpMyAdmin ou via la ligne de commande MySQL
-- =============================================================================

CREATE TABLE IF NOT EXISTS `login_locations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL COMMENT 'Latitude GPS',
    `longitude` DECIMAL(11, 8) DEFAULT NULL COMMENT 'Longitude GPS',
    `accuracy` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Précision en mètres',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'Adresse IP de connexion',
    `user_agent` TEXT DEFAULT NULL COMMENT 'Navigateur utilisé',
    `login_method` VARCHAR(20) NOT NULL DEFAULT 'password' COMMENT 'Méthode: password, google',
    `city` VARCHAR(100) DEFAULT NULL COMMENT 'Ville (optionnel, rempli par géocodage inverse)',
    `country` VARCHAR(100) DEFAULT NULL COMMENT 'Pays (optionnel)',
    `gps_denied` TINYINT(1) DEFAULT 0 COMMENT '1 si l''utilisateur a refusé la géolocalisation',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created` (`created_at`),
    KEY `idx_location` (`latitude`, `longitude`),
    CONSTRAINT `fk_login_location_user` FOREIGN KEY (`user_id`) 
        REFERENCES `compt_utilisateur`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Nettoyage automatique des anciens enregistrements (garder 1 an)
-- =============================================================================

DELIMITER //

CREATE EVENT IF NOT EXISTS `cleanup_old_login_locations`
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    DELETE FROM `login_locations` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 365 DAY);
END //

DELIMITER ;
