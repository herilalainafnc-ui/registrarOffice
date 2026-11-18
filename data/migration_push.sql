-- =============================================================================
-- NOTIFICATIONS « PUSH » DE LA MESSAGERIE
-- =============================================================================
-- Previennent d'un nouveau message quand l'application est fermee.
-- Regles et envoi : data/push.php. Reception : /sw.js.
--
-- Prefixe msg_ : le journal d'activite (data/audit.php) ne copie pas ces
-- tables, comme le reste de la messagerie.
--
-- A appliquer une fois, apres data/migration_messagerie.sql. Date : 2026-10-05
-- =============================================================================

-- Un abonnement par navigateur : l'adresse que son service de push lui a
-- donnee, et ses deux cles de chiffrement.
CREATE TABLE IF NOT EXISTS `msg_push_abonnement` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `empreinte`       CHAR(64)      NOT NULL COMMENT 'sha256 de l adresse : une adresse = un navigateur',
    `utilisateur_id`  INT           NOT NULL,
    `adresse`         VARCHAR(1000) NOT NULL COMMENT 'Adresse du service de push (Google, Apple, Mozilla)',
    `cle_p256dh`      VARCHAR(120)  NOT NULL,
    `cle_auth`        VARCHAR(40)   NOT NULL,
    `appareil`        VARCHAR(120)  NOT NULL DEFAULT '',
    `cree_le`         DATETIME      NOT NULL,
    `vu_le`           DATETIME      NOT NULL COMMENT 'Dernier envoi reussi ou dernier renouvellement',
    `echecs`          TINYINT       NOT NULL DEFAULT 0,
    UNIQUE KEY `uniq_empreinte` (`empreinte`),
    KEY `idx_utilisateur` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : abonnements aux notifications push';

-- La cle VAPID de l'application (une seule ligne), creee au premier envoi.
CREATE TABLE IF NOT EXISTS `msg_push_cle` (
    `id`          TINYINT      NOT NULL PRIMARY KEY,
    `publique`    VARCHAR(100) NOT NULL COMMENT 'base64url, 65 octets',
    `privee_pem`  TEXT         NOT NULL,
    `cree_le`     DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : cle de signature des notifications push';

-- La messagerie est-elle sous les yeux ? (interrogation avec vue=1, au plus
-- toutes les 15 s quand la page est visible). Qui la regarde n'a pas de push.
ALTER TABLE `msg_presence`
    ADD COLUMN `regarde_le` DATETIME NULL DEFAULT NULL AFTER `vu_le`;
