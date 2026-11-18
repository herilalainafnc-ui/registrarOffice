-- =============================================================================
-- Studio UAZ, jalon 3 (demande du 26/10/2026) : la mise en page libre.
--
-- Un element du visuel se deplace et se redimensionne : { format: { id: { dx, dy, l, h, k } } }
-- (pixels du format ; k : echelle du texte). Gardee avec le visuel et avec chaque version de
-- l'historique ; elle fait partie du contenu valide.
--
-- A appliquer une fois, a la main (local puis production), apres data/migration_studio_visuels.sql.
-- Retour arriere : ALTER TABLE t_studio_visuel DROP COLUMN mise_en_page;
--                  ALTER TABLE t_studio_version DROP COLUMN mise_en_page;
-- =============================================================================

ALTER TABLE t_studio_visuel  ADD COLUMN mise_en_page MEDIUMTEXT NULL COMMENT 'JSON : { format: { id: { dx, dy, l, h, k } } }' AFTER images;
ALTER TABLE t_studio_version ADD COLUMN mise_en_page MEDIUMTEXT NULL AFTER images;
