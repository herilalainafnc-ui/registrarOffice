-- Migration: Ajouter une colonne unique de solde dans finance
-- Date: 2026-04-21
-- But: stocker le solde restant par etudiant et par session dans une colonne dediee

ALTER TABLE `finance`
ADD COLUMN `balance_amount` DECIMAL(15,2) DEFAULT NULL COMMENT 'Solde restant = total_payment - sommes des tranches payees' AFTER `amount_pay_5`;

-- Recalcul initial sur l'historique existant
UPDATE `finance`
SET `balance_amount` = (
    COALESCE(`total_payment`, 0)
    - (
        COALESCE(`amount_pay_1`, 0)
      + COALESCE(`amount_pay_2`, 0)
      + COALESCE(`amount_pay_3`, 0)
      + COALESCE(`amount_pay_4`, 0)
      + COALESCE(`amount_pay_5`, 0)
    )
);

-- Index utile pour la lecture par etudiant/session
ALTER TABLE `finance`
ADD INDEX `idx_finance_session_student` (`session_id`, `student_id`);
