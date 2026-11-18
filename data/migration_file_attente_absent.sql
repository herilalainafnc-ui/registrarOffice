-- ---------------------------------------------------------------------------
-- File d'attente : statut « absent » des tickets.
--
-- L'ENUM de t_queue_tickets.status ne connaissait pas « absent », que
-- l'action « Marquer absent » ecrit pourtant. En mode strict (la production),
-- l'action echouait ; avant, en mode permissif, MySQL avait ecrit une chaine
-- vide et le ticket disparaissait de toutes les listes. Ces chaines vides ne
-- peuvent venir que de la : elles redeviennent « absent ».
--
-- A appliquer a la main, une fois, au deploiement. Tant qu'il ne l'est pas,
-- src/queue/queue.api.php lit les chaines vides comme « absent » et refuse
-- « Marquer absent » avec un message qui renvoie a ce fichier. Le relancer sur
-- une base deja migree est sans effet.
-- ---------------------------------------------------------------------------

ALTER TABLE `t_queue_tickets`
    MODIFY `status` ENUM('waiting','called','serving','done','skipped','absent')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'waiting';

UPDATE `t_queue_tickets` SET `status` = 'absent' WHERE `status` = '';
