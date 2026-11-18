-- =============================================================================
-- Studio UAZ, etape 5 (demande du 20/10/2026) : brouillons, duplication,
-- historique, validation, journal, legende.
--
-- t_studio_visuel   un visuel : son preset, ses champs (JSON), les options de
--                   ses images (JSON ; les fichiers sont dans data/studio/images/),
--                   sa legende, son etat (brouillon, a_valider, valide, refuse) et
--                   la signature de ce qui a ete valide : toute modification
--                   apres validation remet le visuel en brouillon.
-- t_studio_version  l'historique : une version a chaque enregistrement qui
--                   change quelque chose (les 30 dernieres sont gardees).
-- t_studio_journal  qui a exporte quoi, quand, dans quel format.
-- t_studio_role     les validateurs (le superadmin valide et administre toujours).
--
-- A appliquer une fois, a la main (local puis production). Le dossier
-- data/studio/images/ est cree par l'application (www-data doit pouvoir
-- ecrire dans data/studio/ ; data/ est ferme au web par nginx).
-- Retour arriere : DROP TABLE t_studio_role, t_studio_journal, t_studio_version,
-- t_studio_visuel; puis supprimer data/studio/.
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_studio_visuel (
  id            INT           NOT NULL AUTO_INCREMENT,
  preset        VARCHAR(10)   NOT NULL,
  titre         VARCHAR(160)  NOT NULL DEFAULT '',
  format        VARCHAR(5)    NOT NULL DEFAULT '4:5',
  donnees       MEDIUMTEXT    NOT NULL COMMENT 'JSON : les champs, tels que saisis',
  images        MEDIUMTEXT    NOT NULL COMMENT 'JSON : par emplacement, fichier + options',
  legende       TEXT          NULL,
  statut        ENUM('brouillon','a_valider','valide','refuse') NOT NULL DEFAULT 'brouillon',
  motif_refus   VARCHAR(300)  NULL,
  signature     CHAR(40)      NULL COMMENT 'sha1 du contenu au moment de la validation ou de la demande',
  auteur_id     INT           NOT NULL,
  auteur_nom    VARCHAR(120)  NOT NULL DEFAULT '',
  valide_par    INT           NULL,
  valide_nom    VARCHAR(120)  NULL,
  valide_le     DATETIME      NULL,
  exporte_le    DATETIME      NULL,
  cree_le       DATETIME      NOT NULL,
  modifie_le    DATETIME      NOT NULL,
  supprime      TINYINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_studio_visuel_auteur (auteur_id, supprime, modifie_le),
  KEY idx_studio_visuel_statut (statut, supprime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS t_studio_version (
  id            INT           NOT NULL AUTO_INCREMENT,
  visuel_id     INT           NOT NULL,
  numero        INT           NOT NULL,
  format        VARCHAR(5)    NOT NULL DEFAULT '4:5',
  donnees       MEDIUMTEXT    NOT NULL,
  images        MEDIUMTEXT    NOT NULL,
  legende       TEXT          NULL,
  motif         VARCHAR(40)   NOT NULL DEFAULT 'auto' COMMENT 'auto, manuel, restauration, duplication',
  auteur_id     INT           NOT NULL,
  auteur_nom    VARCHAR(120)  NOT NULL DEFAULT '',
  cree_le       DATETIME      NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_studio_version (visuel_id, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS t_studio_journal (
  id            INT           NOT NULL AUTO_INCREMENT,
  visuel_id     INT           NULL,
  preset        VARCHAR(10)   NOT NULL,
  titre         VARCHAR(160)  NOT NULL DEFAULT '',
  format        VARCHAR(5)    NOT NULL DEFAULT '',
  sortie        VARCHAR(10)   NOT NULL COMMENT 'png, jpg, pdf, imprimeur, zip',
  statut_visuel VARCHAR(12)   NOT NULL DEFAULT '' COMMENT 'etat du visuel a l export',
  auteur_id     INT           NOT NULL,
  auteur_nom    VARCHAR(120)  NOT NULL DEFAULT '',
  cree_le       DATETIME      NOT NULL,
  PRIMARY KEY (id),
  KEY idx_studio_journal_date (cree_le),
  KEY idx_studio_journal_visuel (visuel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS t_studio_role (
  user_id       INT           NOT NULL,
  role          VARCHAR(20)   NOT NULL DEFAULT 'validateur',
  cree_par      INT           NOT NULL,
  cree_le       DATETIME      NOT NULL,
  PRIMARY KEY (user_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
