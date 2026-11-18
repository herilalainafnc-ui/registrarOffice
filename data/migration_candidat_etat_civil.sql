-- ---------------------------------------------------------------------------
-- Elargissement de la fiche candidat : etat civil, filiation, choix d'etudes.
--
-- Le formulaire public ne demandait que nom complet, email, telephone, mention
-- et annee du bacc. Le bureau des admissions ressaisissait tout le reste a la
-- main au moment de convertir la candidature en etudiant. Ces colonnes portent
-- desormais l'information des la candidature.
--
-- `nom_complet` est CONSERVE et continue d'etre alimente (concatenation de
-- nom et prenom) : la liste d'instruction et la conversion en etudiant s'en
-- servent encore. Le supprimer casserait les deux.
--
-- MySQL n'a pas d'« ADD COLUMN IF NOT EXISTS » : relancer ce fichier sur une
-- base deja migree renvoie « Duplicate column name », ce qui est sans gravite.
-- ---------------------------------------------------------------------------

ALTER TABLE `candidat`
    -- Identite : le formulaire distingue maintenant nom et prenom
    ADD COLUMN `nom`             VARCHAR(100) DEFAULT NULL COMMENT 'Nom de famille'            AFTER `nom_complet`,
    ADD COLUMN `prenom`          VARCHAR(100) DEFAULT NULL COMMENT 'Prenom(s)'                 AFTER `nom`,

    -- Naissance. Deux colonnes distinctes a dessein : beaucoup de candidats
    -- ne connaissent que leur annee de naissance, sans jour ni mois.
    ADD COLUMN `date_naissance`  DATE         DEFAULT NULL COMMENT 'Date complete si connue'   AFTER `prenom`,
    ADD COLUMN `annee_naissance` VARCHAR(4)   DEFAULT NULL COMMENT 'Annee seule si date inconnue' AFTER `date_naissance`,
    ADD COLUMN `lieu_naissance`  VARCHAR(120) DEFAULT NULL                                     AFTER `annee_naissance`,

    ADD COLUMN `genre`           ENUM('M','F') DEFAULT NULL                                    AFTER `lieu_naissance`,
    ADD COLUMN `etat_civil`      VARCHAR(40)  DEFAULT NULL COMMENT 'Celibataire, marie(e)...'  AFTER `genre`,

    -- Origine et residence
    ADD COLUMN `pays_origine`    VARCHAR(80)  DEFAULT NULL                                     AFTER `etat_civil`,
    ADD COLUMN `nationalite`     VARCHAR(80)  DEFAULT NULL                                     AFTER `pays_origine`,
    ADD COLUMN `ville`           VARCHAR(80)  DEFAULT NULL                                     AFTER `nationalite`,
    ADD COLUMN `adresse`         VARCHAR(255) DEFAULT NULL                                     AFTER `ville`,

    -- Filiation et tutelle
    ADD COLUMN `nom_pere`        VARCHAR(150) DEFAULT NULL                                     AFTER `adresse`,
    ADD COLUMN `nom_mere`        VARCHAR(150) DEFAULT NULL                                     AFTER `nom_pere`,
    ADD COLUMN `contact_parent`  VARCHAR(40)  DEFAULT NULL                                     AFTER `nom_mere`,
    ADD COLUMN `tuteur`          VARCHAR(150) DEFAULT NULL                                     AFTER `contact_parent`,
    ADD COLUMN `contact_tuteur`  VARCHAR(40)  DEFAULT NULL                                     AFTER `tuteur`,

    -- Choix d'etudes. `mention` existait deja ; `parcours` en depend.
    ADD COLUMN `diplome_vise`    ENUM('licence','master') DEFAULT NULL                         AFTER `mention_libelle`,
    ADD COLUMN `parcours`        VARCHAR(120) DEFAULT NULL COMMENT 'Parcours au sein de la mention' AFTER `diplome_vise`;
