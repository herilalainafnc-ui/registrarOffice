-- =============================================================================
-- Studio UAZ, jalon 4 (demande du 26/10/2026) : les blocs ajoutes (texte libre, image, forme).
--
-- [{ id: 'b1', type: 'texte'|'forme'|'image', geo: { format: { x, y, l, h } }, ... }], dans l'ordre des
-- calques. Gardes avec le visuel et chaque version de l'historique ; ils font partie du contenu valide.
-- Les fichiers des blocs image sont rangés comme les autres images du visuel (data/studio/images/).
--
-- A appliquer une fois, a la main (local puis production), apres data/migration_studio_mise_en_page.sql.
-- Retour arriere : ALTER TABLE t_studio_visuel DROP COLUMN blocs; ALTER TABLE t_studio_version DROP COLUMN blocs;
-- =============================================================================

ALTER TABLE t_studio_visuel  ADD COLUMN blocs MEDIUMTEXT NULL COMMENT 'JSON : blocs ajoutes, dans l ordre des calques' AFTER mise_en_page;
ALTER TABLE t_studio_version ADD COLUMN blocs MEDIUMTEXT NULL AFTER mise_en_page;
