-- =============================================================================
-- TABLE REMEMBER_TOKENS - Tokens de mémorisation sécurisés
-- =============================================================================
-- Cette table stocke les tokens hashés pour la fonctionnalité "Se souvenir de moi"
-- 
-- À exécuter dans phpMyAdmin ou via la ligne de commande MySQL
-- =============================================================================

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'Token hashé en SHA-256',
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_token` (`user_id`),
    KEY `idx_token_hash` (`token_hash`),
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) 
        REFERENCES `compt_utilisateur`(`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE SECURITY_LOGS - Journal des événements de sécurité (optionnel)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(50) NOT NULL COMMENT 'Type: login, logout, failed_login, csrf_fail, etc.',
    `user_id` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `details` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_ip` (`ip_address`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Nettoyage automatique des tokens expirés (événement MySQL)
-- =============================================================================
-- Exécuter ceci uniquement si vous avez les privilèges EVENT

DELIMITER //

CREATE EVENT IF NOT EXISTS `cleanup_expired_tokens`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    DELETE FROM `remember_tokens` WHERE `expires_at` < NOW();
    DELETE FROM `security_logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
END //

DELIMITER ;

-- Pour activer les événements MySQL (si nécessaire):
-- SET GLOBAL event_scheduler = ON;
