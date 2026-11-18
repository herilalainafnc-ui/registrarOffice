-- =============================================================================
-- Inscriptions tardives : calendriers particuliers (demande du 17/10/2026).
--
-- « Il se peut que chaque mention ait son bareme d'echeance : pour ce
-- semestre, les sciences infirmieres en L2 et L3 ne debuteront que le
-- 2 novembre puisqu'ils sont encore en stage ; de meme pour quelques
-- etudiants en L2 des autres mentions, mais pas tous. »
--
-- t_inscription_calendrier_cas  des dates propres a une mention, un niveau,
--                               les deux, ou un etudiant : dernier jour sans
--                               penalite, date limite des tardives, motif.
--                               La penalite par jour et les jours non
--                               comptes restent ceux de la session
--                               (t_inscription_calendrier). Le plus precis
--                               l'emporte : l'etudiant, puis mention et
--                               niveau, mention seule, niveau seul.
-- Donnees : data/inscriptions.tardives.php (itCas, itAppliquerCas) ; page :
-- /inscriptions/tardives, « Calendriers particuliers ».
--
-- A appliquer une fois, a la main (local puis production), apres
-- data/migration_inscriptions_tardives.sql.
-- Retour arriere : DROP TABLE t_inscription_calendrier_cas; puis, sur la page,
-- enregistrer le calendrier de la session pour recalculer les penalites.
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_inscription_calendrier_cas (
  id             INT          NOT NULL AUTO_INCREMENT,
  session_id     INT          NOT NULL,
  mention        VARCHAR(10)  NULL COMMENT 'filiere.filiere_sigle ; NULL : toutes les mentions',
  niveau         TINYINT      NULL COMMENT '0 (remise a niveau) a 5 (M2) ; NULL : tous les niveaux',
  student_id     VARCHAR(20)  NULL COMMENT 'un etudiant ; prime sur mention et niveau',
  fin_normale    DATE         NOT NULL COMMENT 'dernier jour des inscriptions sans penalite',
  limite_tardive DATE         NOT NULL COMMENT 'dernier jour des inscriptions tardives',
  motif          VARCHAR(255) NOT NULL,
  modifie_par    INT          NULL,
  modifie_le     DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_cas_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Premier semestre 2026 - 2027 (session 490) : Sciences infirmieres, L2 et L3,
-- en stage jusqu'au 1er novembre ; cours a partir du lundi 2 novembre. Comme
-- la session (inscriptions closes le vendredi, tardives jusqu'au lundi deux
-- semaines apres le debut des cours) : sans penalite jusqu'au vendredi
-- 30 octobre, tardives jusqu'au lundi 16 novembre. Modifiable sur la page.
INSERT INTO t_inscription_calendrier_cas (session_id, mention, niveau, fin_normale, limite_tardive, motif, modifie_le)
  SELECT 490, 'NURS', n.niveau, '2026-10-30', '2026-11-16', 'Stage jusqu''au 1er novembre : cours à partir du 2 novembre', NOW()
    FROM (SELECT 2 AS niveau UNION ALL SELECT 3) n
   WHERE EXISTS (SELECT 1 FROM t_inscription_calendrier WHERE session_id = 490)
     AND NOT EXISTS (SELECT 1 FROM t_inscription_calendrier_cas c WHERE c.session_id = 490 AND c.mention = 'NURS' AND c.niveau = n.niveau AND c.student_id IS NULL);
