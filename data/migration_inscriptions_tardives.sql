-- =============================================================================
-- Inscriptions tardives (bulletin academique, § 334) : le calendrier des
-- inscriptions de chaque session, et la trace de chaque inscription.
--
-- « Une periode supplementaire de deux semaines a compter du commencement des
-- cours est prevue pour les inscriptions tardives. Durant cette periode,
-- l'etudiant(e) est soumis(e) a une penalite de 10 000 Ariary par jour. »
--
-- t_inscription_calendrier  une ligne par session : dernier jour des
--                           inscriptions normales, date limite des tardives,
--                           penalite par jour ouvrable (lundi a vendredi),
--                           jours non comptes (feries).
-- t_inscription_trace       une ligne par inscription, posee par le guichet a
--                           sa creation (inscription/app/generate.student.php) :
--                           date et heure, agent, jours ouvrables de retard,
--                           penalite, exoneration (montant, motif, qui, quand).
--                           Jamais reecrite par le guichet : la date d'une
--                           inscription (date_entry) l'est a chaque passage.
--
-- La penalite est aussi une ligne des frais de l'etudiant
-- (t_2024_etudiant_frais_perso, frais_id = -1, hors bareme) : elle s'imprime
-- sur la fiche d'inscription et compte dans ses totaux.
-- Page : /inscriptions/tardives (src/inscriptions.tardives.php),
-- donnees : data/inscriptions.tardives.php.
--
-- A appliquer une fois, a la main (local puis production).
-- Retour arriere :
--   UPDATE t_2024_etudiant_frais_perso SET `remove` = 1 WHERE frais_id = -1;
--   DROP TABLE t_inscription_trace; DROP TABLE t_inscription_calendrier;
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_inscription_calendrier (
  session_id     INT           NOT NULL,
  fin_normale    DATE          NOT NULL COMMENT 'dernier jour des inscriptions normales',
  limite_tardive DATE          NOT NULL COMMENT 'dernier jour des inscriptions tardives',
  penalite_jour  INT           NOT NULL DEFAULT 10000 COMMENT 'Ariary par jour ouvrable de retard',
  jours_exclus   VARCHAR(1000) NULL COMMENT 'jours non comptes (feries), AAAA-MM-JJ separes par des virgules',
  modifie_par    INT           NULL,
  modifie_le     DATETIME      NULL,
  PRIMARY KEY (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS t_inscription_trace (
  id                INT          NOT NULL AUTO_INCREMENT,
  session_id        INT          NOT NULL,
  student_id        VARCHAR(20)  NOT NULL,
  inscrit_le        DATETIME     NOT NULL COMMENT 'premiere creation de l inscription au guichet',
  inscrit_par       INT          NULL,
  origine           VARCHAR(10)  NOT NULL DEFAULT 'guichet' COMMENT 'guichet, ou reprise (inscription anterieure au suivi)',
  jours_retard      INT          NOT NULL DEFAULT 0 COMMENT 'jours ouvrables apres la fin des inscriptions normales',
  hors_delai        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'apres la date limite des inscriptions tardives',
  penalite          INT          NOT NULL DEFAULT 0 COMMENT 'jours_retard x penalite_jour',
  exoneration       INT          NOT NULL DEFAULT 0 COMMENT 'part de la penalite remise',
  exoneration_motif VARCHAR(255) NULL,
  exonere_par       INT          NULL,
  exonere_le        DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inscription_trace (session_id, student_id),
  KEY idx_trace_retard (session_id, jours_retard)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Premier semestre 2026 - 2027 (session 490) : inscriptions du lundi 12 au
-- vendredi 16 octobre 2026 ; tardives du lundi 19 au vendredi 30 octobre
-- (deux semaines, 10 jours ouvrables) ; 10 000 Ar par jour ouvrable.
INSERT IGNORE INTO t_inscription_calendrier (session_id, fin_normale, limite_tardive, penalite_jour)
  VALUES (490, '2026-10-16', '2026-10-30', 10000);

-- Les inscriptions deja faites a la session 490 (avant le suivi) : reprises
-- avec leur date, sans retard.
INSERT IGNORE INTO t_inscription_trace (session_id, student_id, inscrit_le, origine)
  SELECT session_id, student_id, CONCAT(COALESCE(MIN(date_entry), CURDATE()), ' 00:00:00'), 'reprise'
    FROM t_2024_inscription_session
   WHERE session_id = 490 AND COALESCE(`remove`, 0) != 1
   GROUP BY session_id, student_id;
