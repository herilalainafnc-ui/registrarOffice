<?php 
	
	require('../../data/backdb.php');

	$semesterSession = $_POST['semesterSession'];
	$annee_scolaire = $_POST['annee_scolaire'];
	$student_id = $_GET['student_id'];

	$findStudent = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'" LIMIT 1');

	$showStudent = $findStudent->fetch();

	
	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description ="'.$showStudent['etude_envisage'].'"');
	$showMention = $findMention->fetch();
	
	$etude_mention = $showMention['filiere_sigle'];

	$status = $showStudent['status'];
	$new_student = $showStudent['new_student'];
	$level = $showStudent['annee_etude'];

	$graduated = 0;
	$caisse_verification = 0;
	$data_completion = 1;
	$data_verification = 0;
	$cours_selected = 0;
	$mode_payement = "";
	$verification_signatures = 0;
	$depot_list = 0;

	$test_niveau = 0;
	$remove = 0;
	
	$date_entry = date('Y-m-d');

	$cout_logement = 0;
	

	$findSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_name ="'.$semesterSession.'" AND session_year ="'.$annee_scolaire.'" LIMIT 1');
	
	$showSession = $findSession->fetch();

	$session_id = $showSession['session_id'];
	$nbr_semester = $showSession['session_semester'];
	$annee_scolaire = $showSession['session_year'];

	$creatLineStdToSession = $dtb->prepare('INSERT INTO t_2024_inscription_session (
		student_id,
		etude_mention,
		status,
		new_student,
		graduated,
		caisse_verification,
		data_completion,
		data_verification,
		cours_selected,
		mode_payement,
		verification_signatures,
		depot_list,
		session_id,
		nbr_semester,
		test_niveau,
		remove,
		annee_scolaire,
		date_entry
	) VALUES (
		:student_id,
		:etude_mention,
		:status,
		:new_student,
		:graduated,
		:caisse_verification,
		:data_completion,
		:data_verification,
		:cours_selected,
		:mode_payement,
		:verification_signatures,
		:depot_list,
		:session_id,
		:nbr_semester,
		:test_niveau,
		:remove,
		:annee_scolaire,
		:date_entry
	)');$creatLineStdToSession->execute(array(
		'student_id' => $student_id,
		'etude_mention' => $etude_mention,
		'status' => $status,
		'new_student' => $new_student,
		'graduated' => $graduated,
		'caisse_verification' => $caisse_verification,
		'data_completion' => $data_completion,
		'data_verification' => $data_verification,
		'cours_selected' => $cours_selected,
		'mode_payement' => $mode_payement,
		'verification_signatures' => $verification_signatures,
		'depot_list' => $depot_list,
		'session_id' => $session_id,
		'nbr_semester' => $nbr_semester,
		'test_niveau' => $test_niveau,
		'remove' => $remove,
		'annee_scolaire' => $annee_scolaire,
		'date_entry' => $date_entry
	));

	$verification_finance_licence = $dtb->query('SELECT * FROM t_2024_finance_detail_licence WHERE std_status = "'.$status.'" AND std_mention = "'.$etude_mention.'" LIMIT 1');

	$result_finance = $verification_finance_licence->fetch();

		if($nbr_semester == 1) {
			$cout_fraix_generaux = $result_finance['frais_generaux'];
		}elseif($nbr_semester == 2){
			$cout_fraix_generaux = $result_finance['frais_generaux'] -20000;
		}


		if ($level == 3 AND $nbr_semester == 2) {
			$cout_frais_graduation = $result_finance['frais_graduation'];
		}else{
			$cout_frais_graduation = 0;
		}


		if($status == 'Interne'){
			$cout_fondDepot_dortoir = $result_finance['fond_depot'];
			$cout_logement = $result_finance['dortoir'] * $result_finance['nb_jours_semestre'];
		}elseif($status == 'Bungalow'){
			$cout_fondDepot_dortoir = 0;
			$cout_logement = $result_finance['dortoir'] * $result_finance['nb_jours_semestre'];
		}else{
			$cout_fondDepot_dortoir = 0;
			$cout_logement = 0;
		}
	
	
	$cout_totalCours = 0;
	$cout_totalLab = 0;
	$mode_payement = 0;


	$creatLineStdToFinance = $dtb->prepare('INSERT INTO t_2024_etudiant_finace(
		student_id,
		session_id,
		mention,
		level,
		status,
		cout_logement,
		cout_fraix_generaux,
		cout_fondDepot_dortoir,
		cout_frais_graduation,
		cout_totalCours,
		cout_totalLab,
		mode_payement,
		remove,
		date_entry
	) VALUES (
		:student_id,
		:session_id,
		:mention,
		:level,
		:status,
		:cout_logement,
		:cout_fraix_generaux,
		:cout_fondDepot_dortoir,
		:cout_frais_graduation,
		:cout_totalCours,
		:cout_totalLab,
		:mode_payement,
		:remove,
		:date_entry
	)');$creatLineStdToFinance->execute(array(
		'student_id' => $student_id,
		'session_id' => $session_id,
		'mention' => $etude_mention,
		'level' => $level,
		'status' => $status,
		'cout_logement' => $cout_logement,
		'cout_fraix_generaux' => $cout_fraix_generaux,
		'cout_fondDepot_dortoir' => $cout_fondDepot_dortoir,
		'cout_frais_graduation' => $cout_frais_graduation,
		'cout_totalCours' => $cout_totalCours,
		'cout_totalLab' => $cout_totalLab,
		'mode_payement' => $mode_payement,
		'remove' => $remove,
		'date_entry' => $date_entry
	));

 ?>