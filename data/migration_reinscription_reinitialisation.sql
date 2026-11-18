-- =============================================================================
-- Lots de reinitialisation des autorisations de reinscription
--
-- POURQUOI
-- La page /finance/authorized-reinscription permet desormais de remettre a
-- « non coche » tous les etudiants d'un coup, typiquement au changement
-- d'annee. C'est une action de masse : environ 400 autorisations et 400
-- validations de dossier en 2025-2026, et chaque etudiant concerne se voit
-- ensuite refuser la reinscription (modales « Reinscription non autorisee » et
-- « Dossier non valide » de inscription/inscription.php).
--
-- Une erreur de manipulation ne doit pas etre definitive. Cette table garde,
-- pour chaque lot, l'etat EXACT de chaque drapeau avant sa remise a zero —
-- valeur, auteur et date — ce qui permet :
--   - d'annuler le lot en restaurant ces trois informations a l'identique ;
--   - de ne PAS ecraser une modification faite apres la reinitialisation :
--     l'annulation ne restaure une ligne que si elle porte encore la marque
--     laissee par le lot (valeur 0, meme auteur, meme horodatage).
--
-- Rejouable : la table n'est creee que si elle manque.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `t_reinscription_reinitialisation` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lot`             CHAR(16)     NOT NULL COMMENT 'identifiant du lot, commun a toutes ses lignes',
    `student_db_id`   INT          NOT NULL COMMENT 'tbl_2024_etudiant.id',
    `student_id`      VARCHAR(50)  NOT NULL DEFAULT '' COMMENT 'matricule, pour lecture humaine',
    `colonne`         VARCHAR(40)  NOT NULL COMMENT 'authorized_reinscription ou dossier_ok',
    `ancienne_valeur` TINYINT(1)   NOT NULL DEFAULT 1,
    `ancien_par`      INT          NULL COMMENT 'auteur de la valeur remplacee',
    `ancienne_date`   DATETIME     NULL COMMENT 'date de la valeur remplacee',
    `fait_par`        INT          NOT NULL,
    `fait_le`         DATETIME     NOT NULL,
    `annule_par`      INT          NULL,
    `annule_le`       DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_lot` (`lot`),
    KEY `idx_etudiant` (`student_db_id`),
    KEY `idx_fait_le` (`fait_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Etat des drapeaux de reinscription avant chaque reinitialisation globale';
