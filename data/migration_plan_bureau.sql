-- =============================================================================
-- Plan du bureau (file d'attente) : le plan montre aux etudiants sur l'ecran
-- public (/queue/display, bouton « Plan du bureau ») et modifie par le
-- superadmin (/queue/plan). Une seule ligne (id = 1) : le plan en JSON, la
-- version qu'il a remplacee (retour en arriere), l'auteur et la date.
-- Sans cette table, l'ecran public montre le plan par defaut (data/plan.bureau.php).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `t_queue_plan` (
    `id`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `plan`           MEDIUMTEXT NOT NULL COMMENT 'Plan en JSON : batiment, entree, pieces, etapes',
    `plan_precedent` MEDIUMTEXT NULL COMMENT 'La version remplacee par le dernier enregistrement',
    `modifie_par`    INT NULL COMMENT 'compt_utilisateur.id',
    `modifie_le`     DATETIME NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
