-- Index des classements : Dean's List et Top Students (26/09/2026)
--
-- Ces deux pages calculent, pour chaque semestre de l'annee affichee, la
-- moyenne de chaque etudiant : t_2023_notes filtree par session, jointe a
-- tbl_2024_etudiant par matricule. Sans index sur ces colonnes, chaque
-- requete parcourait les deux tables en entier, et une page en lance une
-- par semestre, cycle et mention.
--
-- Mesure, base locale, trois premieres annees :
--   Dean's List    3,4 a 3,8 s   ->  0,16 / 0,40 / 0,44 s
--   Top Students   3,4 a 3,8 s   ->  0,13 / 0,36 / 0,43 s
--
-- 1. t_2023_notes (session_id, student_id) : filtre par session, puis
--    regroupement par etudiant. L'index (student_id, session_id) de
--    migration_index_performance.sql ne sert pas ces requetes : il commence
--    par l'etudiant.
-- 2. tbl_2024_etudiant (student_id) : c'est l'etape 1 de
--    migration_matricule_unique.sql, reprise sous le meme nom pour les bases
--    ou elle n'a pas ete appliquee.
--
-- Rejouable : un index n'est cree que s'il n'en existe aucun qui commence
-- par les memes colonnes (MySQL 8 n'a pas de « ADD INDEX IF NOT EXISTS »).
-- Pose en ligne (InnoDB, ALGORITHM=INPLACE par defaut) : les tables restent
-- lisibles et modifiables pendant l'operation.


-- 1. Notes, par session puis par etudiant.
SET @manque := (
  SELECT COUNT(*) = 0
    FROM information_schema.STATISTICS s1
    JOIN information_schema.STATISTICS s2
      ON s2.TABLE_SCHEMA = s1.TABLE_SCHEMA AND s2.TABLE_NAME = s1.TABLE_NAME
     AND s2.INDEX_NAME = s1.INDEX_NAME AND s2.SEQ_IN_INDEX = 2
     AND s2.COLUMN_NAME = 'student_id'
   WHERE s1.TABLE_SCHEMA = DATABASE() AND s1.TABLE_NAME = 't_2023_notes'
     AND s1.SEQ_IN_INDEX = 1 AND s1.COLUMN_NAME = 'session_id'
);
SET @etape := IF(@manque,
  'ALTER TABLE t_2023_notes ADD INDEX idx_notes_session_etudiant (session_id, student_id)',
  'DO 0');
PREPARE etape FROM @etape;
EXECUTE etape;
DEALLOCATE PREPARE etape;


-- 2. Etudiants, par matricule.
SET @manque := (
  SELECT COUNT(*) = 0
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_2024_etudiant'
     AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'student_id'
);
SET @etape := IF(@manque,
  'ALTER TABLE tbl_2024_etudiant ADD INDEX idx_student_id (student_id)',
  'DO 0');
PREPARE etape FROM @etape;
EXECUTE etape;
DEALLOCATE PREPARE etape;
