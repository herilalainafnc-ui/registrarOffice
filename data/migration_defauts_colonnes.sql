-- Valeurs par defaut pour les colonnes NOT NULL qui n'en avaient pas.
-- Corrige « Field 'x' doesn't have a default value » sous STRICT_TRANS_TABLES.
-- Genere le 17/08/2026 17:11.
--
-- Aucune donnee existante n'est modifiee : seule la definition des
-- colonnes change. A executer sur une base sauvegardee.

-- ---------- t_2024_etudiant_finace ----------
ALTER TABLE `t_2024_etudiant_finace` MODIFY `student_id` varchar(200) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_etudiant_finace` MODIFY `session_id` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `mention` varchar(150) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_etudiant_finace` MODIFY `level` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `status` varchar(60) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_logement` float NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_fraix_generaux` float NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_fondDepot_dortoir` float NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_abonment` double NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_frais_graduation` float NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_totalCours` float NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_totalLab` float NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_costume` double NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `cout_voyage` double NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `mode_payement` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_etudiant_finace` MODIFY `remove` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `date_entry` date NULL DEFAULT NULL;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `last_change_user_id` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_etudiant_finace` MODIFY `last_change_datetime` date NULL DEFAULT NULL;

-- ---------- t_2024_inscription_session ----------
ALTER TABLE `t_2024_inscription_session` MODIFY `student_id` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `etude_mention` varchar(70) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `status` varchar(10) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `new_student` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `graduated` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `caisse_verification` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `data_completion` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `data_verification` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `cours_selected` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `mode_payement` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `verification_signatures` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `depot_list` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `session_id` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `nbr_semester` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `test_niveau` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `adresse_actuel_std` varchar(200) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `parcours_std` varchar(200) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `niveau_std` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `sponsor_name` varchar(100) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `sponsor_lastName` varchar(100) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `sponsor_contact` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `sponsor_address` varchar(100) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `etat_civil_std` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `conjoint_name` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `nb_enfant` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `abonment_std` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `remove` int NOT NULL DEFAULT 0;
ALTER TABLE `t_2024_inscription_session` MODIFY `annee_scolaire` varchar(100) NOT NULL DEFAULT '';
ALTER TABLE `t_2024_inscription_session` MODIFY `date_entry` date NULL DEFAULT NULL;

