-- ---------------------------------------------------------------------------
-- Archivage des candidatures.
--
-- L'archivage est VOLONTAIREMENT distinct du statut, et non une valeur
-- supplementaire de l'enumeration `statut`. Une candidature archivee reste
-- « refusee » ou « convertie » : ranger un dossier ne doit pas effacer la
-- decision qui a ete prise dessus. Confondre les deux aurait rendu impossible
-- de savoir, plus tard, ce qu'on avait repondu au candidat.
--
-- `archive_le` NULL = dossier actif. La liste d'instruction n'affiche que
-- ceux-la ; les archives restent consultables par un filtre dedie.
--
-- MySQL n'a pas d'« ADD COLUMN IF NOT EXISTS » : relancer ce fichier sur une
-- base deja migree renvoie « Duplicate column name », ce qui est sans gravite.
-- ---------------------------------------------------------------------------

ALTER TABLE `candidat`
    ADD COLUMN `archive_le`  DATETIME     DEFAULT NULL COMMENT 'Date de mise en archive. NULL = dossier actif' AFTER `date_traitement`,
    ADD COLUMN `archive_par` VARCHAR(100) DEFAULT NULL COMMENT 'Agent ayant archive le dossier'                 AFTER `archive_le`;

-- La liste courante filtre systematiquement sur « archive_le IS NULL » :
-- l'index evite un balayage complet des que le volume grandit.
ALTER TABLE `candidat`
    ADD INDEX `idx_archive` (`archive_le`);
