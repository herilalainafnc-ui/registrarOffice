-- Tracabilite des SMS envoyes aux candidats.
-- Distingue un candidat reellement prevenu d'un candidat qu'on croit prevenu.
--   mysql registrar_db < data/migration_candidat_sms.sql
ALTER TABLE `candidat`
    ADD COLUMN `notifie_sms_le` DATETIME DEFAULT NULL COMMENT 'Dernier SMS envoye au candidat (decision)' AFTER `notifie_le`;
