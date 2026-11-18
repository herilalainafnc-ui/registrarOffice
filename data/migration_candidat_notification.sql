-- =============================================================================
-- Tracabilite des notifications envoyees aux candidats
-- =============================================================================
--
-- Ajoute la date du dernier message envoye au candidat pour l'informer de la
-- decision (acceptation ou refus). Permet de savoir, depuis la liste, si le
-- candidat a bien ete prevenu — et de le constater quand l'envoi a echoue.
--
-- Application :
--   mysql registrar_db < data/migration_candidat_notification.sql
--
-- MySQL n'a pas de « ADD COLUMN IF NOT EXISTS » : relancer cette commande sur
-- une base ou la colonne existe deja renvoie une erreur, sans dommage.

ALTER TABLE `candidat`
    ADD COLUMN `notifie_le` DATETIME DEFAULT NULL COMMENT 'Dernier envoi au candidat (decision)' AFTER `date_traitement`;
