-- ---------------------------------------------------------------------------
-- Configuration de la vitrine.
--
-- Les textes, dates, coordonnees et visuels de la page publique etaient ecrits
-- en dur dans les fragments PHP. Chaque changement de date de rentree ou de
-- numero de telephone demandait donc une intervention de developpeur et un
-- deploiement — on l'a constate deux fois en une semaine.
--
-- Magasin cle/valeur volontairement generique : ajouter un reglage se fait par
-- une ligne ici, sans toucher au code de l'interface d'administration. Le
-- `groupe` et l'`ordre` pilotent seuls la mise en page du formulaire.
--
-- `type` gouverne le champ de saisie :
--   texte    -> input text        date  -> input date
--   long     -> textarea          image -> televersement + apercu
--   url      -> input url         liste -> une valeur par ligne (textarea)
--
-- MySQL n'a pas d'« ADD COLUMN IF NOT EXISTS » : relancer ce fichier sur une
-- base deja migree renvoie « Duplicate », ce qui est sans gravite.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vitrine_config` (
    `cle`      VARCHAR(80)  NOT NULL,
    `valeur`   TEXT         DEFAULT NULL,
    `groupe`   VARCHAR(40)  NOT NULL DEFAULT 'general',
    `libelle`  VARCHAR(160) NOT NULL,
    `aide`     VARCHAR(255) DEFAULT NULL COMMENT 'Precision affichee sous le champ',
    `type`     ENUM('texte','long','date','url','image','liste') NOT NULL DEFAULT 'texte',
    `ordre`    SMALLINT     NOT NULL DEFAULT 0,
    `modifie_le`  DATETIME  DEFAULT NULL,
    `modifie_par` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`cle`),
    KEY `idx_groupe` (`groupe`, `ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
