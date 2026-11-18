-- =====================================================================
-- Migration : code de statut dedie "-4" = pas encore note (NN)
-- =====================================================================
-- Avant : un 0 signifiait indifferemment "pas encore note" OU un vrai 0/20.
-- Apres : -4 = pas encore note (exclu des moyennes)
--          0 = vraie note de 0/20 (comptee comme un echec)
--
-- IMPORTANT : la colonne `grade` est de type VARCHAR et contient des valeurs
-- non numeriques (ex. "11-13"). Une comparaison `grade = 0` avec un litteral
-- NUMERIQUE force MySQL a convertir toutes les lignes et echoue avec
-- "Truncated incorrect DOUBLE value". On compare donc en TEXTE.
-- ---------------------------------------------------------------------

-- 1) Combien de lignes seront modifiees (a noter avant / apres)
SELECT COUNT(*) AS a_migrer FROM t_2023_notes WHERE grade IN ("0", "0.00");

-- 2) Sauvegarde ciblee : permet un retour arriere exact
CREATE TABLE bk_notes_zero AS
  SELECT id, grade AS ancienne_note FROM t_2023_notes WHERE grade IN ("0", "0.00");

-- 3) Migration
UPDATE t_2023_notes SET grade = "-4" WHERE grade IN ("0", "0.00");

-- 4) Controles (doivent donner : 0, puis le nombre de l etape 1)
SELECT COUNT(*) AS restant_a_zero FROM t_2023_notes WHERE grade IN ("0", "0.00");
SELECT COUNT(*) AS total_nn        FROM t_2023_notes WHERE grade = "-4";

-- Les valeurs -1 (ancien code herite) ne sont PAS migrees :
-- le code les traite deja comme "non note".

-- ---------------------------------------------------------------------
-- RETOUR ARRIERE si besoin :
--   UPDATE t_2023_notes n JOIN bk_notes_zero b ON b.id = n.id
--      SET n.grade = b.ancienne_note;
--   DROP TABLE bk_notes_zero;
-- ---------------------------------------------------------------------