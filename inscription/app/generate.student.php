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
		$new_student = $showStudent['new_student'];
		$annee_etude = 1;
	
	}
	
	$updateStd = $dtb->prepare('UPDATE tbl_2024_etudiant SET annee_etude=:annee_etude, annee_scolaire=:annee_scolaire WHERE id=:id');
	$updateStd->bindParam(':annee_etude',$annee_etude,PDO::PARAM_STR);
	$updateStd->bindParam(':annee_scolaire',$annee_scolaire,PDO::PARAM_STR);
	$updateStd->bindParam(':id',$idS,PDO::PARAM_INT);
	$updateStd->execute();

	
	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description ="'.$showStudent['etude_envisage'].'"');
	$showMention = $findMention->fetch();
	
	$etude_mention = $showMention['filiere_sigle'];

	
	if ($showStudent['status'] == '') {
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
	)');$creatLineStdToSession->execute(array(
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

	$verification_finance_licence = $dtb->query('SELECT * FROM t_2024_finance_detail_licence WHERE std_status = "'.$status.'" AND std_mention = "'.$etude_mention.'" LIMIT 1');

	$result_finance = $verification_finance_licence->fetch();

		echo "<br>Frais généraux = ".$cout_fraix_generaux = floatval($result_finance['frais_generaux']);		

		if ($level == 1) {

			$nbr_day = intval($result_finance['nb_jours_semestre']);
		
		}elseif ($level == 2) {
		
			$nbr_day = intval($result_finance['nb_jours_semestre_L2']);
		
		}elseif ($level == 3) {
		
			$nbr_day = intval($result_finance['nb_jours_semestre_L3']);
		
		}


		if ($graduated == 1) {
			
			 echo "<br>Frais graduation = ".$cout_frais_graduation = floatval($result_finance['frais_graduation']);
		
		}else{
		
			 echo "<br>Frais graduation = ".$cout_frais_graduation = 0;
		
		}

		$cout_voyage = $result_finance['frais_voyage'];

		if ($new_student == 1 AND $status == "Interne") {

			echo "<br>Dortoir = ".$cout_fondDepot_dortoir = $result_finance['fond_depot'];	

		}else{

			echo "<br>Dortoir = ".$cout_fondDepot_dortoir = 0;
			
		}
			
		echo "<br>Log = ".$cout_logement = floatval($result_finance['dortoir']) * $nbr_day;
		
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
			'cout_voyage' => $cout_voyage,
			'date_entry' => $date_entry
		));

 ?>