-- =============================================================================
-- MESSAGERIE INSTANTANEE
-- =============================================================================
-- Discussions entre utilisateurs de l'application (compt_utilisateur) : a deux
-- ou en groupe, comme Messenger. Messages ecrits, vocaux, photos, videos et
-- documents.
--
-- Qui ecrit a qui : tout compte actif (etat = 1) a tout autre. Un groupe se
-- cree par le personnel ou un enseignant (niveau 1 a 7) ; ses administrateurs
-- y ajoutent ou en retirent des membres. Chacun peut bloquer une personne et
-- signaler un message ; les signalements vont au superadmin.
--
-- Le temps reel passe par des interrogations courtes et frequentes : le
-- serveur n'a pas de serveur de WebSocket, et PHP-FPM n'a que cinq processus.
-- Chaque changement visible par les membres d'une discussion (message,
-- reaction, retrait, lecture, membres) ajoute une ligne a msg_evenement : le
-- navigateur demande « ce qui a change depuis l'evenement n », une seule
-- requete indexee.
--
-- Les fichiers sont dans data/messagerie/AAAA/MM/ (nginx renvoie 404 sur
-- /data/) et ne se lisent que par app/messagerie.fichier.php, qui verifie que
-- le demandeur est membre d'une discussion ou le fichier a ete envoye.
--
-- Le journal d'activite (data/audit.php) ne copie pas ces tables : c'est une
-- correspondance privee. Les decisions de moderation, elles, y sont notees.
--
-- Regles : data/messagerie.php. Date : 2026-10-05
-- =============================================================================

-- =========================================
-- 1. Discussions
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_conversation` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `type`                ENUM('direct','groupe') NOT NULL,
    `nom`                 VARCHAR(100) DEFAULT NULL COMMENT 'Groupe seulement',
    -- Une seule discussion a deux par paire de comptes : « petit id-grand id ».
    `cle_directe`         VARCHAR(30)  DEFAULT NULL,
    `cree_par`            INT          NOT NULL COMMENT 'compt_utilisateur.id',
    `cree_le`             DATETIME     NOT NULL,
    `dernier_message_id`  INT          DEFAULT NULL,
    `dernier_le`          DATETIME(3)  DEFAULT NULL COMMENT 'Ordre de la liste des discussions',
    UNIQUE KEY `uq_directe` (`cle_directe`),
    KEY `idx_dernier` (`dernier_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : une discussion a deux ou un groupe';

-- =========================================
-- 2. Membres
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_participant` (
    `conversation_id`  INT          NOT NULL,
    `user_id`          INT          NOT NULL COMMENT 'compt_utilisateur.id',
    `role`             ENUM('membre','admin') NOT NULL DEFAULT 'membre',
    `ajoute_par`       INT          DEFAULT NULL,
    `rejoint_le`       DATETIME     NOT NULL,
    -- Un membre ajoute a un groupe voit les messages envoyes apres son arrivee,
    -- parti ou retire, il garde ceux d'avant son depart, et plus rien ensuite.
    `debut_message_id` INT          NOT NULL DEFAULT 0 COMMENT 'Voit les messages d''id superieur',
    `fin_message_id`   INT          DEFAULT NULL COMMENT 'NULL = membre actuel',
    `lu_jusqu_a`       INT          NOT NULL DEFAULT 0 COMMENT 'Dernier message lu (accuses de lecture)',
    `ecrit_le`         DATETIME     DEFAULT NULL COMMENT '« … est en train d''ecrire »',
    `efface_jusqu_a`   INT          NOT NULL DEFAULT 0 COMMENT 'Discussion supprimee pour soi jusqu''a ce message',
    `sourdine`         TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`conversation_id`, `user_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : membres d''une discussion, et ou chacun en est';

-- =========================================
-- 3. Messages
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_message` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id`  INT          NOT NULL,
    `auteur_id`        INT          DEFAULT NULL COMMENT 'NULL pour un message du systeme',
    `type`             ENUM('texte','image','video','audio','fichier','systeme') NOT NULL,
    `texte`            TEXT         DEFAULT NULL COMMENT 'Message, ou legende du fichier',
    `fichier_id`       INT          DEFAULT NULL,
    `reponse_a`        INT          DEFAULT NULL COMMENT 'Message cite',
    `systeme`          VARCHAR(40)  DEFAULT NULL COMMENT 'groupe_cree, membres_ajoutes, membre_retire, membre_parti, groupe_renomme',
    `donnees`          VARCHAR(1000) DEFAULT NULL COMMENT 'JSON du message systeme',
    `envoye_le`        DATETIME(3)  NOT NULL,
    `retire_le`        DATETIME     DEFAULT NULL COMMENT 'Retire pour tout le monde',
    `retire_par`       INT          DEFAULT NULL COMMENT 'L''auteur, ou le superadmin apres un signalement',
    -- Identifiant pose par le navigateur : un envoi repete apres une coupure
    -- reseau ne cree pas deux messages.
    `cle_client`       CHAR(36)     DEFAULT NULL,
    KEY `idx_conv` (`conversation_id`, `id`),
    KEY `idx_auteur` (`auteur_id`, `envoye_le`),
    UNIQUE KEY `uq_client` (`auteur_id`, `cle_client`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : messages';

-- =========================================
-- 4. Fichiers joints
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_fichier` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `proprietaire_id`  INT          NOT NULL,
    `jeton`            CHAR(32)     NOT NULL COMMENT 'Nom du fichier sur le disque',
    `nom`              VARCHAR(200) NOT NULL COMMENT 'Nom d''origine, nettoye',
    `mime`             VARCHAR(100) NOT NULL,
    `genre`            ENUM('image','video','audio','fichier') NOT NULL,
    `taille`           BIGINT UNSIGNED NOT NULL,
    `recu`             BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Octets deja recus (envoi par morceaux)',
    `chemin`           VARCHAR(120) NOT NULL COMMENT 'Relatif a data/messagerie',
    `apercu`           TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Vignette des images',
    `largeur`          SMALLINT UNSIGNED DEFAULT NULL,
    `hauteur`          SMALLINT UNSIGNED DEFAULT NULL,
    `duree_ms`         INT UNSIGNED DEFAULT NULL COMMENT 'Vocal ou video, mesure par le navigateur',
    `statut`           ENUM('envoi','pret') NOT NULL DEFAULT 'envoi',
    `cree_le`          DATETIME     NOT NULL,
    UNIQUE KEY `uq_jeton` (`jeton`),
    KEY `idx_proprietaire` (`proprietaire_id`, `cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : fichiers envoyes (photos, videos, vocaux, documents)';

-- =========================================
-- 5. Reactions
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_reaction` (
    `message_id`  INT         NOT NULL,
    `user_id`     INT         NOT NULL,
    `emoji`       VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `le`          DATETIME    NOT NULL,
    PRIMARY KEY (`message_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : une reaction par personne et par message';

-- =========================================
-- 6. Journal des changements (synchronisation des navigateurs)
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_evenement` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id`  INT         NOT NULL,
    `type`             ENUM('message','modif','lu','conversation') NOT NULL,
    `message_id`       INT         DEFAULT NULL,
    `user_id`          INT         DEFAULT NULL,
    `le`               DATETIME(3) NOT NULL,
    KEY `idx_conv` (`conversation_id`, `id`),
    -- Chaque interrogation relit les 5 dernieres secondes : un id attribue
    -- dans une transaction encore ouverte peut apparaitre apres un id plus
    -- grand, deja valide.
    KEY `idx_le` (`le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : ce qui a change, pour les navigateurs ouverts';

-- =========================================
-- 7. Blocages
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_blocage` (
    `user_id`    INT      NOT NULL COMMENT 'Celui qui bloque',
    `bloque_id`  INT      NOT NULL COMMENT 'Celui qui est bloque',
    `le`         DATETIME NOT NULL,
    PRIMARY KEY (`user_id`, `bloque_id`),
    KEY `idx_bloque` (`bloque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : personnes bloquees (discussions a deux)';

-- =========================================
-- 8. Signalements
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_signalement` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `message_id`   INT          NOT NULL,
    `signale_par`  INT          NOT NULL,
    `motif`        VARCHAR(500) DEFAULT NULL,
    `le`           DATETIME     NOT NULL,
    `statut`       ENUM('ouvert','retire','rejete') NOT NULL DEFAULT 'ouvert',
    `traite_par`   INT          DEFAULT NULL,
    `traite_le`    DATETIME     DEFAULT NULL,
    UNIQUE KEY `uq_signalement` (`message_id`, `signale_par`),
    KEY `idx_statut` (`statut`, `le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : messages signales au superadmin';

-- =========================================
-- 9. Presence (« en ligne »)
-- =========================================
CREATE TABLE IF NOT EXISTS `msg_presence` (
    `user_id`  INT      NOT NULL PRIMARY KEY,
    `vu_le`    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messagerie : derniere activite, mise a jour au plus une fois par minute';
