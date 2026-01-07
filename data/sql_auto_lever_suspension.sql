-- =====================================================
-- Script SQL pour lever automatiquement les suspensions expirées
-- À exécuter une fois dans phpMyAdmin
-- =====================================================

-- 1. Activer le Event Scheduler de MySQL (si pas déjà activé)
SET GLOBAL event_scheduler = ON;

-- 2. Supprimer l'événement s'il existe déjà
DROP EVENT IF EXISTS auto_lever_suspension;

-- 3. Créer l'événement qui s'exécute tous les jours à minuit
DELIMITER //
CREATE EVENT auto_lever_suspension
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
BEGIN
    -- Mettre à jour les étudiants dont la suspension a expiré
    UPDATE tbl_2024_etudiant 
    SET suspended = 0 
    WHERE suspended = 1 
    AND date_fin_suspension IS NOT NULL 
    AND date_fin_suspension < CURDATE();
    
    -- Optionnel: Enregistrer dans l'historique
    INSERT INTO t_suspension_history (student_id, action_type, action_date, action_by, comment)
    SELECT student_id, 'levee_auto', NOW(), 'SYSTEM', 'Suspension levée automatiquement (date de fin atteinte)'
    FROM tbl_2024_etudiant 
    WHERE suspended = 0 
    AND date_fin_suspension IS NOT NULL 
    AND date_fin_suspension < CURDATE()
    AND date_fin_suspension >= DATE_SUB(CURDATE(), INTERVAL 1 DAY);
END//
DELIMITER ;

-- 4. Vérifier que l'événement est créé
SHOW EVENTS LIKE 'auto_lever_suspension';

-- =====================================================
-- NOTE: Si l'Event Scheduler n'est pas activé par défaut,
-- ajoutez cette ligne dans my.ini/my.cnf sous [mysqld]:
-- event_scheduler=ON
-- 
-- Puis redémarrez MySQL.
-- =====================================================
