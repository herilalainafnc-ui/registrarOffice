-- Index de performance (16/09/2026)
--
-- t_2023_notes portait 68 355 lignes et la seule cle primaire. La page d'ajout
-- de cours interroge cette table UNE FOIS PAR COURS OFFERT : en informatique,
-- 71 cours offerts sur les niveaux 1 a 3, donc 71 parcours complets de la
-- table pour un seul affichage. Mesure avant index, base locale : 9 secondes.
--
-- Les trois autres tables du dossier n'avaient elles aussi que leur cle
-- primaire, alors que tout le code les filtre sur (student_id, session_id).
--
-- Pose de l'index en ligne (ALGORITHM=INPLACE par defaut sur InnoDB) : la
-- table reste lisible et modifiable pendant l'operation.

ALTER TABLE t_2023_notes
  ADD KEY idx_notes_etudiant_session (student_id, session_id),
  ADD KEY idx_notes_cours_etudiant (id_cours, student_id),
  ADD KEY idx_notes_sigle_annee (Sigle, annee_scolaire);

ALTER TABLE t_2024_cours_finance
  ADD KEY idx_cours_finance_dossier (student_id, session_id),
  ADD KEY idx_cours_finance_cours (cours_id);

ALTER TABLE t_2024_etudiant_finace
  ADD KEY idx_finance_dossier (student_id, session_id);

ALTER TABLE t_2024_inscription_session
  ADD KEY idx_inscription_dossier (student_id, session_id);
