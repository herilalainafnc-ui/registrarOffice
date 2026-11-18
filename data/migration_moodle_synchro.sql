-- =============================================================================
-- UAZ Campus (Moodle) : ce que Moodle lit du registrar, en lecture seule
-- (cahier des charges UAZ Campus, section 8 ; decisions du 19/10/2026).
--
-- Moodle (plugin d'inscription « Base de donnees externe », enrol_database) lit
-- ces deux vues avec un compte MySQL qui n'a que le droit de les lire. Il ne
-- touche jamais aux tables du registrar.
--
-- t_moodle_perimetre     les sessions et mentions envoyees a Moodle. Pilote :
--                        Theologie, premier semestre 2026 - 2027 (490). Ajouter
--                        une ligne ouvre une autre mention ou une autre session.
-- v_moodle_cours         un espace de cours par cours ET par session (decision
--                        du 19/10/2026) : les cours offerts de la session
--                        (t_2023_course_sessions), et ceux qui ont des inscrits.
--                        idnumber uaz-c<cours>-s<session> ; categorie : la
--                        mention dans la session (uaz-s<session>-<mention>).
-- v_moodle_inscriptions  qui suit quel cours : les etudiants (notes actives de
--                        la session, remove = 0 ; un retrait passe la note a
--                        remove = 1 et Moodle suspend l'inscription en gardant
--                        les travaux), et l'enseignant de la fiche du cours.
--                        Chacun est reconnu par son adresse @zurcher.edu.mg
--                        (celle de sa connexion Google) ; les autres adresses
--                        sont ignorees.
--
-- A appliquer une fois, a la main (local puis production).
-- Retour arriere :
--   DROP VIEW v_moodle_inscriptions; DROP VIEW v_moodle_cours; DROP TABLE t_moodle_perimetre;
-- =============================================================================

CREATE TABLE IF NOT EXISTS t_moodle_perimetre (
  session_id INT          NOT NULL,
  mention    VARCHAR(10)  NOT NULL COMMENT 'filiere.filiere_sigle (t_2023_cours.dep_desc)',
  ajoute_le  DATETIME     NULL,
  PRIMARY KEY (session_id, mention)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pilote : Theologie, premier semestre 2026 - 2027.
INSERT IGNORE INTO t_moodle_perimetre (session_id, mention, ajoute_le) VALUES (490, 'THEO', NOW());

CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_moodle_cours AS
SELECT x.idnumber,
       -- le nom court doit etre unique dans Moodle : numero de fiche en cas de sigle double
       CASE WHEN x.doublons > 1 THEN CONCAT(x.court, ' #', x.cours_id) ELSE x.court END AS shortname,
       x.fullname, x.categorie, x.categorie_nom, x.session_categorie, x.session_nom,
       x.cours_id, x.session_id, x.mention
  FROM (
    SELECT CONCAT('uaz-c', c.id, '-s', s.session_id) AS idnumber,
           CONCAT(TRIM(COALESCE(NULLIF(TRIM(c.sigle_affiche), ''), c.Sigle)), ' ', c.dep_desc, ' ',
                  CASE s.session_semester WHEN 1 THEN 'S1' WHEN 2 THEN 'S2' WHEN 3 THEN 'Été' WHEN 4 THEN 'Hiver' ELSE s.session_name END,
                  ' ', LEFT(s.session_year, 4), '-', RIGHT(TRIM(s.session_year), 2)) AS court,
           COUNT(*) OVER (PARTITION BY s.session_id, c.dep_desc, TRIM(COALESCE(NULLIF(TRIM(c.sigle_affiche), ''), c.Sigle))) AS doublons,
           CONCAT(TRIM(COALESCE(NULLIF(TRIM(c.sigle_affiche), ''), c.Sigle)), ' - ', TRIM(c.title),
                  ' (', s.session_name, ' ', s.session_year, ')') AS fullname,
           CONCAT('uaz-s', s.session_id, '-', c.dep_desc) AS categorie,
           COALESCE(f.filiere_description, c.dep_desc) AS categorie_nom,
           CONCAT('uaz-s', s.session_id) AS session_categorie,
           CONCAT(s.session_name, ' ', s.session_year) AS session_nom,
           c.id AS cours_id, s.session_id, c.dep_desc AS mention
      FROM t_moodle_perimetre p
      JOIN t_2023_session s ON s.session_id = p.session_id
      JOIN t_2023_cours c ON c.dep_desc = p.mention AND COALESCE(c.`remove`, 0) != 1
      LEFT JOIN filiere f ON f.filiere_sigle = c.dep_desc
     WHERE EXISTS (SELECT 1 FROM t_2023_course_sessions cs
                    WHERE cs.course_id = c.id AND cs.offered = 1
                      AND cs.session_label = s.session_name AND cs.academic_year = s.session_year)
        OR EXISTS (SELECT 1 FROM t_2023_notes n
                    WHERE n.id_cours = c.id AND n.session_id = s.session_id AND n.`remove` = 0)
  ) x;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_moodle_inscriptions AS
SELECT CONCAT('uaz-c', n.id_cours, '-s', n.session_id) AS cours,
       LOWER(TRIM(e.student_email)) AS email,
       'student' AS role
  FROM t_moodle_perimetre p
  JOIN t_2023_notes n ON n.session_id = p.session_id AND n.`remove` = 0
  JOIN t_2023_cours c ON c.id = n.id_cours AND c.dep_desc = p.mention
  -- la fiche active de l'etudiant, comme partout dans le registrar
  JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id
                                       ORDER BY e2.`remove` ASC, e2.id ASC LIMIT 1)
 WHERE LOWER(TRIM(e.student_email)) LIKE '%@zurcher.edu.mg'
   AND EXISTS (SELECT 1 FROM t_2024_inscription_session i
                WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1)
 GROUP BY n.id_cours, n.session_id, LOWER(TRIM(e.student_email))
UNION
SELECT v.idnumber AS cours,
       LOWER(TRIM(t.email)) AS email,
       'editingteacher' AS role
  FROM v_moodle_cours v
  JOIN t_2023_cours c ON c.id = v.cours_id
  JOIN teacher t ON t.uid = c.id_teacher
 WHERE LOWER(TRIM(t.email)) LIKE '%@zurcher.edu.mg' AND COALESCE(t.`remove`, 0) != 1;
