-- =============================================================================
-- REMISE DES NOTES PAR L'ENSEIGNANT
-- =============================================================================
-- Circuit : l'enseignant saisit ses notes dans une « remise », la soumet, puis
-- le registrariat la controle et la valide. La validation seule ecrit dans
-- t_2023_notes : tant qu'une remise n'est pas validee, rien n'apparait dans
-- l'historique des notes ni dans le bulletin de l'etudiant.
--
-- Trois tables :
--   t_remise_notes          en-tete (un cours, une annee, un semestre)
--   t_remise_notes_ligne    une ligne par etudiant, avec le detail des notes
--   t_remise_notes_journal  qui a change quoi, et a quelle heure
--
-- Date: 2026-09-23
-- =============================================================================

-- =========================================
-- 1. En-tete de remise
-- =========================================
CREATE TABLE IF NOT EXISTS `t_remise_notes` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `cours_id`        INT          NOT NULL COMMENT 't_2023_cours.id — jointure fiable avec t_2023_notes.id_cours',
    `sigle`           VARCHAR(150) DEFAULT NULL COMMENT 'Copie au moment de la remise : le cours peut etre renomme ensuite',
    `titre_cours`     VARCHAR(200) DEFAULT NULL,
    `teacher_uid`     INT          DEFAULT NULL COMMENT 'teacher.uid — auteur de la remise',
    `annee_scolaire`  VARCHAR(20)  NOT NULL COMMENT 'Format « 2025 - 2026 », comme t_2023_notes.annee_scolaire',
    `semestre`        INT          DEFAULT NULL COMMENT '1, 2, 3 (ete), 4 (hiver)',

    -- Ponderation des composantes, en pourcentage. Leur somme doit faire 100
    -- pour qu'une remise puisse etre soumise (controle applicatif, pas SQL :
    -- un brouillon a le droit d'etre incomplet).
    `poids_quiz`         DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `poids_recherche`    DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `poids_autres`       DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `poids_mi_semestre`  DECIMAL(5,2) NOT NULL DEFAULT 25.00,
    `poids_final`        DECIMAL(5,2) NOT NULL DEFAULT 35.00,

    -- brouillon : l'enseignant saisit, personne d'autre ne voit
    -- soumise   : en attente du registrariat, l'enseignant ne peut plus ecrire
    -- validee   : les notes ont ete ecrites dans t_2023_notes
    -- rejetee   : renvoyee a l'enseignant avec un motif, il peut resoumettre
    `statut`          ENUM('brouillon','soumise','validee','rejetee') NOT NULL DEFAULT 'brouillon',
    `motif_rejet`     TEXT         DEFAULT NULL,

    `cree_par`        INT          DEFAULT NULL COMMENT 'compt_utilisateur.id',
    `cree_le`         DATETIME     DEFAULT NULL,
    `modifie_le`      DATETIME     DEFAULT NULL COMMENT 'Derniere ecriture, quelle qu''elle soit',
    `soumis_par`      INT          DEFAULT NULL,
    `soumis_le`       DATETIME     DEFAULT NULL,
    `traite_par`      INT          DEFAULT NULL COMMENT 'Registraire ou superadmin qui a valide ou rejete',
    `traite_le`       DATETIME     DEFAULT NULL,

    -- Une seule remise par cours / annee / semestre : deux feuilles
    -- concurrentes pour le meme cours rendraient la validation ambigue.
    UNIQUE KEY `uq_remise` (`cours_id`, `annee_scolaire`, `semestre`),
    KEY `idx_teacher`  (`teacher_uid`),
    KEY `idx_statut`   (`statut`),
    KEY `idx_annee`    (`annee_scolaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Remises de notes des enseignants, en attente de validation du registrariat';

-- =========================================
-- 2. Lignes : une par etudiant
-- =========================================
CREATE TABLE IF NOT EXISTS `t_remise_notes_ligne` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `remise_id`     INT          NOT NULL,
    `note_id`       INT          DEFAULT NULL COMMENT 't_2023_notes.id — cible de l''ecriture a la validation',
    `student_id`    VARCHAR(200) NOT NULL,

    -- Composantes sur 20. NULL = non saisi (different de 0, qui est une vraie
    -- note nulle — voir data/notes.statut.php).
    `quiz`          DECIMAL(5,2) DEFAULT NULL,
    `recherche`     DECIMAL(5,2) DEFAULT NULL,
    `autres`        DECIMAL(5,2) DEFAULT NULL,
    `mi_semestre`   DECIMAL(5,2) DEFAULT NULL,
    `examen_final`  DECIMAL(5,2) DEFAULT NULL,

    `note_finale`   DECIMAL(5,2) DEFAULT NULL COMMENT 'Calculee depuis les composantes, ou saisie si note_forcee = 1',
    `note_forcee`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = l''enseignant a impose la note finale',

    -- Codes de data/notes.statut.php, pour les cas hors bareme :
    -- V (valide sans note), E (echec sans note), NN (pas encore note).
    -- Prime sur note_finale quand il est renseigne.
    `statut_special` VARCHAR(5)  DEFAULT NULL,
    `commentaire`    VARCHAR(255) DEFAULT NULL,

    `modifie_le`    DATETIME     DEFAULT NULL,

    UNIQUE KEY `uq_ligne` (`remise_id`, `student_id`),
    KEY `idx_remise`  (`remise_id`),
    KEY `idx_note`    (`note_id`),
    KEY `idx_student` (`student_id`),
    CONSTRAINT `fk_ligne_remise` FOREIGN KEY (`remise_id`)
        REFERENCES `t_remise_notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Detail des notes par etudiant dans une remise';

-- =========================================
-- 3. Journal : la tracabilite demandee
-- =========================================
-- Une ligne par changement, avec l'ancienne et la nouvelle valeur. C'est ce
-- qui permet de repondre a « qui a mis cette note, et quand ». Distinct de
-- t_notes_modification_history, qui ne trace que t_2023_notes : ici on suit
-- aussi ce qui se passe AVANT que la note n'existe officiellement.
CREATE TABLE IF NOT EXISTS `t_remise_notes_journal` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `remise_id`       INT          NOT NULL,
    `ligne_id`        INT          DEFAULT NULL COMMENT 'NULL pour une action portant sur la remise entiere',
    `student_id`      VARCHAR(200) DEFAULT NULL,

    `action`          ENUM('creation','saisie','ponderation','soumission','validation','rejet','reouverture')
                      NOT NULL DEFAULT 'saisie',
    `champ`           VARCHAR(40)  DEFAULT NULL COMMENT 'quiz, recherche, autres, mi_semestre, examen_final, note_finale, statut_special…',
    `ancienne_valeur` VARCHAR(255) DEFAULT NULL,
    `nouvelle_valeur` VARCHAR(255) DEFAULT NULL,

    `auteur_id`       INT          DEFAULT NULL COMMENT 'compt_utilisateur.id',
    `auteur_nom`      VARCHAR(150) DEFAULT NULL COMMENT 'Copie : un compte peut etre supprime, le journal doit rester lisible',
    `auteur_role`     VARCHAR(30)  DEFAULT NULL,
    `date_action`     DATETIME     NOT NULL,
    `ip`              VARCHAR(45)  DEFAULT NULL,

    KEY `idx_remise`  (`remise_id`),
    KEY `idx_ligne`   (`ligne_id`),
    KEY `idx_date`    (`date_action`),
    KEY `idx_auteur`  (`auteur_id`),
    CONSTRAINT `fk_journal_remise` FOREIGN KEY (`remise_id`)
        REFERENCES `t_remise_notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Journal des modifications d''une remise de notes (auteur, champ, avant/apres, heure)';

-- =============================================================================
-- Alignement de collation
-- =============================================================================
-- Les tables historiques de l'application sont en utf8mb4_general_ci, alors que
-- le defaut du serveur est utf8mb4_0900_ai_ci. Sans cet alignement, la jointure
-- t_remise_notes_ligne.student_id = tbl_2024_etudiant.student_id echoue avec
-- « Illegal mix of collations ». A rejouer si les tables ont ete creees avant
-- cette section.
ALTER TABLE `t_remise_notes`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `t_remise_notes_ligne`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `t_remise_notes_journal` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
