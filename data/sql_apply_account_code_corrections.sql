-- Appliquer des corrections account_code a partir d'un mapping (student_id, account_code)
-- Usage:
-- 1) Remplir data/account_code_corrections_template.tsv
-- 2) Importer ce TSV dans tmp_account_code_corrections (phpMyAdmin) OU inserer via SQL ci-dessous
-- 3) Executer ce script

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_account_code_corrections;
CREATE TEMPORARY TABLE tmp_account_code_corrections (
  student_id VARCHAR(100) NOT NULL,
  account_code VARCHAR(100) NOT NULL,
  PRIMARY KEY (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Option A: inserer manuellement les lignes ci-dessous (decommenter et adapter)
-- INSERT INTO tmp_account_code_corrections (student_id, account_code) VALUES
-- ('2025-00123', 'SAMBJE01'),
-- ('2025-00456', 'SAIMTO01');

-- Option B (phpMyAdmin): importer data/account_code_corrections_template.tsv
-- dans tmp_account_code_corrections avec separateur tabulation.

-- Nettoyage minimal
UPDATE tmp_account_code_corrections
SET student_id = TRIM(student_id),
    account_code = UPPER(TRIM(account_code));

DELETE FROM tmp_account_code_corrections
WHERE student_id = '' OR account_code = '';

-- Application des corrections
UPDATE tbl_2024_etudiant s
JOIN tmp_account_code_corrections c
  ON TRIM(s.student_id) = c.student_id
SET s.account_code = c.account_code;

SELECT ROW_COUNT() AS updated_rows;

-- Lignes corrigees non trouvees dans la table etudiant
SELECT c.student_id, c.account_code
FROM tmp_account_code_corrections c
LEFT JOIN tbl_2024_etudiant s
  ON TRIM(s.student_id) = c.student_id
WHERE s.id IS NULL;

COMMIT;
