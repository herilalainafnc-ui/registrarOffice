-- Perimetre d'un compte : le cycle qu'il voit (28/09/2026)
--
-- Les deux secretaires academiques ne doivent voir que leur cycle : la
-- secretaire de Licence tout ce qui concerne la Licence, celle de Master tout
-- ce qui concerne le Master ; l'autre cycle leur est invisible. Les
-- enseignants et les chefs de mention restent communs. La regle elle-meme
-- (quels niveaux font quel cycle) est dans data/perimetre.php.
--
-- 1. compt_utilisateur.perimetre_cycle : 'L', 'M', ou NULL. NULL = tout voir,
--    c'est-a-dire le comportement d'avant pour tous les autres comptes.
-- 2. Les deux comptes concernes, et eux seuls. Chacun est designe par son
--    identifiant ET son pseudo : si l'un des deux ne correspond plus, rien
--    n'est pose (un compte recree autrement doit etre revu a la main).
--    « Secretaire de Licence » (gracia, id 14, inactif) n'est pas concerne.
--
-- Rejouable : la colonne n'est ajoutee que si elle manque (MySQL 8 n'a pas
-- de « ADD COLUMN IF NOT EXISTS »), et les UPDATE reposent la meme valeur.


-- 1. La colonne.
SET @manque := (
  SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compt_utilisateur'
     AND COLUMN_NAME = 'perimetre_cycle'
);
SET @etape := IF(@manque,
  'ALTER TABLE compt_utilisateur ADD COLUMN perimetre_cycle ENUM(''L'',''M'') NULL DEFAULT NULL COMMENT ''Cycle visible : L (Licence), M (Master), NULL = tout (data/perimetre.php)''',
  'DO 0');
PREPARE etape FROM @etape;
EXECUTE etape;
DEALLOCATE PREPARE etape;

-- 2. Les deux secretaires academiques.
UPDATE compt_utilisateur SET perimetre_cycle = 'L' WHERE id = 44 AND pseudo = 'taniah';
UPDATE compt_utilisateur SET perimetre_cycle = 'M' WHERE id = 28 AND pseudo = 'canylo';
