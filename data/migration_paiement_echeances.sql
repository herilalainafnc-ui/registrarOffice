-- =============================================================================
-- Echeances de paiement : la date limite de chaque tranche, session par session.
--
-- Les dates etaient ecrites en dur dans src/extenssionPrint/fiche_inscription.php,
-- les memes pour toutes les sessions. Elles se reglent maintenant sur
-- /finance/echeances (src/finance/echeances.php, data/paiement.echeances.php).
--
-- Une ligne par session, type de paiement (A a E, comme
-- t_2024_etudiant_finace.mode_payement) et tranche (1 a 4, dans l'ordre des
-- parts du type : B = 50 % puis 50 %, E = 4 x 25 %...).
--
-- A appliquer une fois, a la main (local puis production).
-- Retour arriere : DROP TABLE t_paiement_echeance;
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_paiement_echeance (
  id            INT      NOT NULL AUTO_INCREMENT,
  session_id    INT      NOT NULL,
  type_paiement CHAR(1)  NOT NULL COMMENT 'A, B, C, D ou E',
  tranche       TINYINT  NOT NULL COMMENT '1 a 4, dans l ordre des parts du type',
  date_limite   DATE     NOT NULL,
  modifie_par   INT      NULL,
  modifie_le    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_echeance (session_id, type_paiement, tranche)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Les dates qui etaient en dur, rattachees aux sessions dont elles tombent
-- dans la periode : types A, B et E du Deuxieme semestre 2025 - 2026
-- (session 488, du 9 mars au 2 aout 2026), type D du Premier semestre
-- 2025 - 2026 (session 486, du 29 septembre 2025 au 13 fevrier 2026).
-- Le type C (24 avril 2026 puis 30 janvier 2026 : la 2e tranche avant la 1re)
-- n'est pas repris.
INSERT IGNORE INTO t_paiement_echeance (session_id, type_paiement, tranche, date_limite) VALUES
  (488, 'A', 1, '2026-03-31'),
  (488, 'B', 1, '2026-04-30'), (488, 'B', 2, '2026-07-10'),
  (488, 'E', 1, '2026-03-31'), (488, 'E', 2, '2026-04-30'), (488, 'E', 3, '2026-06-10'), (488, 'E', 4, '2026-07-10'),
  (486, 'D', 1, '2025-10-03'), (486, 'D', 2, '2025-12-19'), (486, 'D', 3, '2026-01-30');
