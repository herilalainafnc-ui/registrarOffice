-- ---------------------------------------------------------------------------
-- Espace étudiant : publication des notes par session et rubriques visibles.
--
-- Les notes ne doivent pas être divulguées aux étudiants avant l'aval du
-- Sénat, qui porte sur une session d'examens. Voir data/espace.etudiant.php.
--
-- Ce fichier documente le schéma : data/espace.etudiant.php crée lui-même les
-- tables au premier usage (espaceEtudiantInstaller), avec l'état de départ
-- ci-dessous. Le relancer sur une base déjà migrée est sans effet.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `t_notes_publication` (
    `session_id`  INT          NOT NULL,
    `publiee`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = notes visibles par les étudiants',
    `modifie_le`  DATETIME     DEFAULT NULL,
    `modifie_par` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- État de départ (15/09/2026) : sessions antérieures à 2025-2026 publiées,
-- 2025-2026 et suivantes masquées en attendant le Sénat. Une session sans
-- ligne est masquée : les sessions créées ensuite démarrent masquées.
INSERT IGNORE INTO `t_notes_publication` (`session_id`, `publiee`, `modifie_le`, `modifie_par`)
SELECT `session_id`,
       CASE WHEN CAST(LEFT(TRIM(`session_year`), 4) AS UNSIGNED) < 2025 THEN 1 ELSE 0 END,
       NOW(), 'installation'
  FROM `t_2023_session`;

CREATE TABLE IF NOT EXISTS `t_espace_etudiant_config` (
    `cle`         VARCHAR(80)  NOT NULL,
    `valeur`      VARCHAR(255) NOT NULL,
    `modifie_le`  DATETIME     DEFAULT NULL,
    `modifie_par` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
