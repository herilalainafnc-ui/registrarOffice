-- =============================================================================
-- Studio UAZ, etape 6 (demande du 26/10/2026) : l'administration.
--
-- t_studio_reglage  les reglages du superadmin : types de visuels desactives (« inactifs »), valeurs
--                   par defaut des champs (« defauts »), listes gerees (« listes » : services
--                   emetteurs, annees, filieres), en JSON, une ligne par cle.
-- t_studio_media    la mediatheque : les images que le superadmin met a disposition de tous les
--                   editeurs (fichiers de data/studio/mediatheque/, nommes d'apres leur contenu).
--
-- A appliquer une fois, a la main (local puis production), apres data/migration_studio_blocs.sql.
-- Retour arriere : DROP TABLE t_studio_media, t_studio_reglage; puis supprimer data/studio/mediatheque/.
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_studio_reglage (
  cle           VARCHAR(40)   NOT NULL,
  valeur        MEDIUMTEXT    NOT NULL,
  modifie_par   INT           NOT NULL,
  modifie_le    DATETIME      NOT NULL,
  PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS t_studio_media (
  id            INT           NOT NULL AUTO_INCREMENT,
  fichier       VARCHAR(60)   NOT NULL,
  type          VARCHAR(12)   NOT NULL,
  titre         VARCHAR(120)  NOT NULL,
  alt           VARCHAR(150)  NOT NULL,
  largeur       INT           NOT NULL,
  hauteur       INT           NOT NULL,
  ajoute_par    INT           NOT NULL,
  ajoute_nom    VARCHAR(120)  NOT NULL DEFAULT '',
  ajoute_le     DATETIME      NOT NULL,
  supprime      TINYINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_studio_media_fichier (fichier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
