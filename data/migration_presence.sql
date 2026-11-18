-- =============================================================================
-- PRESENCES AUX COURS (Bulletin academique, ASSIDUITE §472 a §476)
-- =============================================================================
-- L'enseignant tient le registre de presence de chaque seance de ses cours
-- (§472). L'application en tire, pour chaque etudiant et chaque cours, le
-- compte des absences au sens du §476 :
--   - moins de 5 minutes de retard : a l'heure ;
--   - de 5 a 9 minutes : un retard ; deux retards comptent une absence ;
--   - 10 minutes ou plus : une absence ;
--   - au-dela de 15 % des seances prevues, l'etudiant doit quitter le cours,
--     et le registraire en est informe (signalement automatique).
-- Une seance ou l'enseignant arrive avec plus de 10 minutes de retard sans
-- avoir prevenu ne penalise pas les etudiants partis (§474).
--
-- Quatre tables :
--   t_presence_feuille      un cours, une annee, un semestre (seances prevues)
--   t_presence_seance       une seance tenue : date, heures, retard enseignant
--   t_presence_ligne        un etudiant a une seance : present, retard, absent
--   t_presence_signalement  les depassements des 15 %, pour le registrariat
--
-- Regles : data/presence.php. Date : 2026-09-26
-- =============================================================================

-- =========================================
-- 1. Feuille de presence
-- =========================================
CREATE TABLE IF NOT EXISTS `t_presence_feuille` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `cours_id`         INT          NOT NULL COMMENT 't_2023_cours.id, comme t_remise_notes',
    `sigle`            VARCHAR(150) DEFAULT NULL COMMENT 'Copie : le cours peut etre renomme ensuite',
    `titre_cours`      VARCHAR(200) DEFAULT NULL,
    `teacher_uid`      INT          DEFAULT NULL COMMENT 'teacher.uid, titulaire a l''ouverture',
    `annee_scolaire`   VARCHAR(20)  NOT NULL COMMENT 'Format « 2025 - 2026 »',
    `semestre`         INT          DEFAULT NULL COMMENT '1, 2, 3 (ete), 4 (hiver)',

    -- La base des 15 % (§476) : fixee par l'enseignant pour le semestre, pour
    -- que le seuil soit connu des le debut et ne bouge pas avec les seances.
    -- NULL = pas encore indique : la regle ne peut pas s'appliquer.
    `seances_prevues`  SMALLINT     DEFAULT NULL,

    `cree_par`         INT          DEFAULT NULL COMMENT 'compt_utilisateur.id',
    `cree_le`          DATETIME     DEFAULT NULL,
    `modifie_le`       DATETIME     DEFAULT NULL,

    UNIQUE KEY `uq_feuille` (`cours_id`, `annee_scolaire`, `semestre`),
    KEY `idx_teacher` (`teacher_uid`),
    KEY `idx_annee`   (`annee_scolaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Registre de presence d''un cours pour une annee et un semestre';

-- =========================================
-- 2. Seances
-- =========================================
CREATE TABLE IF NOT EXISTS `t_presence_seance` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `feuille_id`          INT          NOT NULL,
    `date_seance`         DATE         NOT NULL,
    `heure_prevue`        TIME         DEFAULT NULL COMMENT 'Debut prevu (emploi du temps ou saisi)',
    `arrivee_enseignant`  TIME         DEFAULT NULL COMMENT '§474 : heure d''arrivee de l''enseignant',
    `message_retard`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '§474 : l''enseignant a prevenu les etudiants de son retard',
    `commentaire`         VARCHAR(255) DEFAULT NULL,

    `cree_par`            INT          DEFAULT NULL,
    `cree_le`             DATETIME     DEFAULT NULL,
    `modifie_par`         INT          DEFAULT NULL,
    `modifie_le`          DATETIME     DEFAULT NULL,

    KEY `idx_feuille_date` (`feuille_id`, `date_seance`),
    CONSTRAINT `fk_seance_feuille` FOREIGN KEY (`feuille_id`)
        REFERENCES `t_presence_feuille` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Seances tenues d''un cours';

-- =========================================
-- 3. Pointage : un etudiant a une seance
-- =========================================
CREATE TABLE IF NOT EXISTS `t_presence_ligne` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `seance_id`       INT          NOT NULL,
    `student_id`      VARCHAR(200) NOT NULL,

    -- NULL = pas encore pointe : la ligne ne compte ni pour ni contre.
    `statut`          ENUM('present','retard','absent') DEFAULT NULL,
    `minutes_retard`  SMALLINT     DEFAULT NULL COMMENT 'Pour un retard : c''est lui qui decide s''il compte (§476)',

    -- Justificatif presente au retour (§476). Il ne rend pas l'absence
    -- excusee pour les 15 % ; un certificat medical permet de refaire les
    -- tests et examens manques.
    `justificatif`    ENUM('medical','autre') DEFAULT NULL,

    `modifie_par`     INT          DEFAULT NULL,
    `modifie_le`      DATETIME     DEFAULT NULL,

    UNIQUE KEY `uq_ligne` (`seance_id`, `student_id`),
    KEY `idx_student` (`student_id`),
    CONSTRAINT `fk_ligne_seance` FOREIGN KEY (`seance_id`)
        REFERENCES `t_presence_seance` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Presence d''un etudiant a une seance';

-- =========================================
-- 4. Signalements au registraire
-- =========================================
-- Le §476 oblige l'enseignant a informer le registraire de toute absence
-- au-dela des 15 % : l'application le fait pour lui, au pointage qui fait
-- depasser le seuil. Un signalement par etudiant et par cours.
CREATE TABLE IF NOT EXISTS `t_presence_signalement` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `feuille_id`       INT          NOT NULL,
    `student_id`       VARCHAR(200) NOT NULL,
    `absences`         SMALLINT     NOT NULL COMMENT 'Absences comptees (retards convertis) au signalement',
    `seances_prevues`  SMALLINT     NOT NULL,
    `signale_le`       DATETIME     NOT NULL,
    `signale_par`      INT          DEFAULT NULL COMMENT 'Compte qui a pointe l''absence de trop',
    `traite_le`        DATETIME     DEFAULT NULL COMMENT 'Pris en compte par le registrariat',
    `traite_par`       INT          DEFAULT NULL,

    UNIQUE KEY `uq_signalement` (`feuille_id`, `student_id`),
    KEY `idx_traite` (`traite_le`),
    CONSTRAINT `fk_signalement_feuille` FOREIGN KEY (`feuille_id`)
        REFERENCES `t_presence_feuille` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Depassements des 15 % d''absences, a traiter par le registrariat';
