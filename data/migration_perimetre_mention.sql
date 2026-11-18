-- Mention d'un chef de mention : sa liste des etudiants (07/10/2026)
--
-- Dans l'espace d'un chef de mention, la liste des etudiants ne montre que les
-- etudiants de sa mention. Le reste (fiches, classements, tableau de bord,
-- recherche generale) reste commun : choix de l'utilisateur. La regle est
-- dans data/perimetre.php (perimetreSqlListeMention).
--
-- La mention vient d'un champ pose par le registrariat, et non du « Poste » du
-- compte : chacun modifie son poste lui-meme depuis « Mon compte ».
--
-- 1. compt_utilisateur.perimetre_mention : la mention telle qu'ecrite sur les
--    fiches (tbl_2024_etudiant.etude_envisage), ou NULL. NULL sur un compte de
--    chef de mention : sa liste est vide, avec un message ; sur tout autre
--    compte : aucun effet.
-- 2. Les sept chefs de mention en poste, d'apres leur poste au 07/10/2026.
--    Chacun est designe par son identifiant ET son pseudo : si l'un des deux
--    ne correspond plus, rien n'est pose. Le compte generique « CM » (id 37,
--    poste « Chef de mention » sans mention) reste a NULL.
--
-- Rejouable : la colonne n'est ajoutee que si elle manque (MySQL 8 n'a pas
-- de « ADD COLUMN IF NOT EXISTS »), et les UPDATE reposent la meme valeur.


-- 1. La colonne.
SET @manque := (
  SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compt_utilisateur'
     AND COLUMN_NAME = 'perimetre_mention'
);
SET @etape := IF(@manque,
  'ALTER TABLE compt_utilisateur ADD COLUMN perimetre_mention VARCHAR(100) NULL DEFAULT NULL COMMENT ''Mention d un chef de mention : sa liste des etudiants (data/perimetre.php)''',
  'DO 0');
PREPARE etape FROM @etape;
EXECUTE etape;
DEALLOCATE PREPARE etape;

-- 2. Les chefs de mention.
UPDATE compt_utilisateur SET perimetre_mention = 'Gestion'              WHERE id = 16  AND pseudo = 'CHRISTELLEK';
UPDATE compt_utilisateur SET perimetre_mention = 'Sciences Infirmières' WHERE id = 17  AND pseudo = 'brunel';
UPDATE compt_utilisateur SET perimetre_mention = 'Droit'                WHERE id = 19  AND pseudo = 'Malala';
UPDATE compt_utilisateur SET perimetre_mention = 'Etudes anglophones'   WHERE id = 20  AND pseudo = 'dina';
UPDATE compt_utilisateur SET perimetre_mention = 'Communication'        WHERE id = 40  AND pseudo = 'seth';
UPDATE compt_utilisateur SET perimetre_mention = 'Informatique'         WHERE id = 41  AND pseudo = 'mamitiana';
UPDATE compt_utilisateur SET perimetre_mention = 'Théologie'            WHERE id = 182 AND pseudo = 'pamphile';
