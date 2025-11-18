<?php 
	
	require('../../data/backdb.php');

	$semesterSession = $_POST['semesterSession'];
	$annee_scolaire = $_POST['annee_scolaire'];
	$student_id = $_GET['student_id'];
	$graduated = $_GET['graduated'];

	if ($graduated == "") {
		$graduated = 0;
	}


	$remove = 0;

	$findStudent = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'" LIMIT 1');

	$showStudent = $findStudent->fetch();

	$idS = $showStudent['id'];

	if ($showStudent['annee_etude'] < 3 AND $showStudent['annee_scolaire'] != $annee_scolaire) {

		$annee_etude = $showStudent['annee_etude'] + 1;
		$new_student = 0;
	
	}elseif($showStudent['annee_scolaire'] == $annee_scolaire){
		
		$new_student = 1;
		$annee_etude = 1;
	
	}elseif($showStudent['annee_etude'] == 3 OR ($showStudent['annee_etude'] == 1 AND $showStudent['new_student'] == 1 )) {

		$annee_etude = $showStudent['annee_etude'];

	}
	
	$updateStd = $dtb->prepare('UPDATE tbl_2024_etudiant SET annee_scolaire=:annee_scolaire WHERE id=:id');
	$updateStd->bindParam(':annee_scolaire',$annee_scolaire,PDO::PARAM_STR);
	$updateStd->bindParam(':id',$idS,PDO::PARAM_INT);
	$updateStd->execute();

	
	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description ="'.$showStudent['etude_envisage'].'"');
	$showMention = $findMention->fetch();
	
	$etude_mention = $showMention['filiere_sigle'];

	
	if ($showStudent['status'] == "" OR $showStudent['status'] == "Bungalow") {
		$status = 'Externe';
	}else{
		$status = $showStudent['status'];	
	}

	
	$level = $showStudent['annee_etude'];

	$data_completion = 1;
	
	$date_entry = date('Y-m-d');
	

	$findSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_name ="'.$semesterSession.'" AND session_year ="'.$annee_scolaire.'" LIMIT 1');
	
	$showSession = $findSession->fetch();

	$session_id = $showSession['session_id'];
	$nbr_semester = $showSession['session_semester'];
	$annee_scolaire = $showSession['session_year'];

	$verification_Old_status = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id ="'.$student_id.'" LIMIT 1');

	$show_Old_status = $verification_Old_status->fetch();

	$graduated = $show_Old_status['graduated'];

	echo "<br>status = ".$status;
	echo "<br>etude_mention = ".$etude_mention;
	echo "<br>level = ".$level;
	echo "<br>nbr_semester = ".$nbr_semester;

	$verification_finance_licence = $dtb->query('SELECT * FROM t_2024_finance_detail_licence
	 WHERE 
	 std_status = "'.$status.'" 
	 AND std_mention = "'.$etude_mention.'" 
	 AND level = "'.$level.'" 
	 AND semester = "'.$nbr_semester.'" 
	 ');

	$result_finance = $verification_finance_licence->fetch();

		echo "<br>Frais generaux = ".$cout_fraix_generaux = floatval($result_finance['frais_generaux']);		
		$nbr_day = intval($result_finance['nb_jours_semestre']);
		$cout_costume = $result_finance['frais_costume'];

	if ($graduated == 1) {
	
		echo "<br>frais_graduation = ".$cout_frais_graduation = floatval($result_finance['frais_graduation']);
	
	}else{

		$cout_frais_graduation = 0;
	
	}
	
		$cout_voyage = $result_finance['frais_voyage'];

	if ($show_Old_status['status'] == "Externe" OR $show_Old_status['status'] == "Bungalow") {

		$cout_fondDepot_dortoir = $result_finance['fond_depot'];
	
	}else{

		$cout_fondDepot_dortoir = 0;

	}

		$cout_logement = floatval($result_finance['dortoir']) * $nbr_day;
		
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
			cout_costume,
			cout_voyage,
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
			:cout_costume,
			:cout_voyage,
			:date_entry
		)');

		$creatLineStdToFinance->execute(array(
			'student_id' => $student_id,
			'session_id' => $session_id,
			'mention' => $etude_mention,
			'level' => $level,
			'status' => $status,
			'cout_logement' => $cout_logement,
			'cout_fraix_generaux' => $cout_fraix_generaux,
			'cout_fondDepot_dortoir' => $cout_fondDepot_dortoir,
			'cout_frais_graduation' => $cout_frais_graduation,
			'cout_costume' => $cout_costume,
			'cout_voyage' => $cout_voyage,
			'date_entry' => $date_entry
		));

	$creatLineStdToSession = $dtb->prepare('INSERT INTO t_2024_inscription_session (
		student_id,
		etude_mention,
		status,
		new_student,
		data_completion,
		session_id,
		nbr_semester,
		annee_scolaire,
		date_entry
	) VALUES (
		:student_id,
		:etude_mention,
		:status,
		:new_student,
		:data_completion,
		:session_id,
		:nbr_semester,
		:annee_scolaire,
		:date_entry
	)');
	$creatLineStdToSession->execute(array(
		'student_id' => $student_id,
		'etude_mention' => $etude_mention,
		'status' => $status,
		'new_student' => $new_student,
		'data_completion' => $data_completion,
		'session_id' => $session_id,
		'nbr_semester' => $nbr_semester,
		'annee_scolaire' => $annee_scolaire,
		'date_entry' => $date_entry
	));

 ?>