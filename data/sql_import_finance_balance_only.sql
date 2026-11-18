-- Import de solde unique (sans tranches) dans finance.balance_amount
-- Cas d'usage: fichier Excel contenant uniquement le montant de solde par etudiant.
-- Date: 2026-04-21
--
-- Prerequis:
-- 1) La colonne finance.balance_amount doit exister
--    (executer d'abord: data/sql_finance_balance_amount.sql)
-- 2) Importer votre Excel dans une table temporaire (phpMyAdmin) avec au minimum:
--    - student_id (VARCHAR)
--    - balance_amount (DECIMAL)
--    - session_id (INT, optionnel)

-- Session par defaut si votre fichier n'a pas de colonne session_id
SET @target_session_id = 488;

DROP TEMPORARY TABLE IF EXISTS tmp_finance_balance_import;
CREATE TEMPORARY TABLE tmp_finance_balance_import (
  student_id VARCHAR(100) NOT NULL,
  balance_amount DECIMAL(15,2) NOT NULL,
  session_id INT NULL,
  PRIMARY KEY (student_id, session_id)
);

-- Exemple d'insertion manuelle (optionnel)
-- INSERT INTO tmp_finance_balance_import (student_id, balance_amount, session_id) VALUES
-- ('70086', 150000, 488),
-- ('70087', -25000, 488);

-- Nettoyage minimal
UPDATE tmp_finance_balance_import
SET student_id = TRIM(student_id),
    session_id = NULLIF(session_id, 0);

DELETE FROM tmp_finance_balance_import
WHERE student_id = '';

-- Mise a jour des lignes finance existantes
UPDATE finance f
JOIN tmp_finance_balance_import t
  ON TRIM(f.student_id) = t.student_id
 AND f.session_id = COALESCE(t.session_id, @target_session_id)
SET f.balance_amount = t.balance_amount;

-- Verification: lignes du fichier qui n'ont pas trouve de correspondance dans finance
SELECT t.student_id,
       COALESCE(t.session_id, @target_session_id) AS session_id,
       t.balance_amount
FROM tmp_finance_balance_import t
LEFT JOIN finance f
  ON TRIM(f.student_id) = t.student_id
 AND f.session_id = COALESCE(t.session_id, @target_session_id)
WHERE f.student_id IS NULL;

-- Verification: apercu des soldes apres import
SELECT f.session_id, f.student_id, f.balance_amount
FROM finance f
WHERE f.session_id = @target_session_id
ORDER BY f.student_id;
