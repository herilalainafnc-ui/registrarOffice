-- =============================================================================
-- Table `candidat` — candidatures de pre-admission (inscription en ligne)
-- =============================================================================
--
-- Recueille les demandes deposees depuis le formulaire public de la page
-- Admissions (landing/pages/admissions.php). Une candidature n'est PAS un
-- etudiant : c'est une intention, que le bureau des admissions instruit
-- (accepte / refuse) avant, le cas echeant, de creer l'etudiant. Le lien
-- vers l'etudiant cree est conserve dans `student_id` (tracabilite).
--
-- Application :
--   mysql registrar_db < data/migration_candidat.sql
-- ou via php (voir la commande utilisee lors de l'installation).
--
-- Idempotent : CREATE TABLE IF NOT EXISTS.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `candidat` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Renseigne par le candidat (formulaire public)
    `nom_complet`     VARCHAR(150)  NOT NULL,
    `email`           VARCHAR(150)  DEFAULT NULL,        -- facultatif cote formulaire
    `telephone`       VARCHAR(40)   NOT NULL,
    `mention`         VARCHAR(60)   NOT NULL,            -- cle du <select> (ex: informatique)
    `mention_libelle` VARCHAR(120)  DEFAULT NULL,        -- libelle lisible (ex: Informatique)
    `annee_bacc`      VARCHAR(10)   DEFAULT NULL,
    `message`         TEXT          DEFAULT NULL,

    -- Instruction du dossier (bureau des admissions)
    `statut`          ENUM('nouveau','en_cours','accepte','refuse','converti')
                                    NOT NULL DEFAULT 'nouveau',
    `student_id`      VARCHAR(30)   DEFAULT NULL,        -- rempli a la conversion en etudiant
    `traite_par`      VARCHAR(100)  DEFAULT NULL,        -- agent ayant traite la candidature
    `note_interne`    TEXT          DEFAULT NULL,        -- remarque interne, non visible du candidat

    -- Tracabilite
    `ip`              VARCHAR(45)   DEFAULT NULL,         -- audit / anti-abus (IPv4/IPv6)
    `date_creation`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_traitement` DATETIME      DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_statut`  (`statut`),
    KEY `idx_date`    (`date_creation`),
    KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
