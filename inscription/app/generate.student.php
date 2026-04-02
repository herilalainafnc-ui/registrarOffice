<?php 
	header('Content-Type: application/json');
	
	try {
		require('../../data/backdb.php');

		$semesterSession = $_POST['semesterSession'];
		$annee_scolaire = $_POST['annee_scolaire'];
		$student_id = $_GET['student_id'];
		$graduated = $_GET['graduated'] ?? '';

	if ($graduated == "") {
		$graduated = 0;
	}

	$remove = 0;

	$findStudent = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id LIMIT 1');
	$findStudent->execute(['student_id' => $student_id]);
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

	
	$findMention = $dtb->prepare('SELECT * FROM filiere WHERE filiere_description = :etude_envisage');
	$findMention->execute(['etude_envisage' => $showStudent['etude_envisage']]);
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
	

	$findSession = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_name = :session_name AND session_year = :session_year LIMIT 1');
	$findSession->execute(['session_name' => $semesterSession, 'session_year' => $annee_scolaire]);
	$showSession = $findSession->fetch();

	$session_id = $showSession['session_id'];
	$nbr_semester = $showSession['session_semester'];
	$annee_scolaire = $showSession['session_year'];

	$verification_Old_status = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id LIMIT 1');
	$verification_Old_status->execute(['student_id' => $student_id]);
	$show_Old_status = $verification_Old_status->fetch();

	$graduated = $show_Old_status['graduated'];

	// Variables de configuration (pas d'echo pour le JSON)
	$debug_info = [
		'status' => $status,
		'etude_mention' => $etude_mention,
		'level' => $level
	];

	// ============================================================
	// Vérifier si une inscription existe déjà pour ce même semestre
	// Si oui → UPDATE (pas de suppression, on préserve l'historique)
	// Si non → INSERT
	// Les cours (t_2024_cours_finance) et notes (t_2023_notes) ne sont JAMAIS touchés
	// ============================================================

	// Transaction démarrée AVANT les vérifications pour éviter les race conditions
	$dtb->beginTransaction();
	
	try {

	$checkExisting = $dtb->prepare('SELECT id FROM t_2024_inscription_session WHERE student_id = :student_id AND session_id = :session_id FOR UPDATE');
	$checkExisting->execute(['student_id' => $student_id, 'session_id' => $session_id]);
	$existingSession = $checkExisting->fetch();

	$checkExistingFinance = $dtb->prepare('SELECT id FROM t_2024_etudiant_finace WHERE student_id = :student_id AND session_id = :session_id FOR UPDATE');
	$checkExistingFinance->execute(['student_id' => $student_id, 'session_id' => $session_id]);
	$existingFinance = $checkExistingFinance->fetch();

	$verification_finance_licence = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence
	 WHERE std_status = :status AND std_mention = :mention AND level = :level AND semester = :semester');
	$verification_finance_licence->execute([
		'status' => $status,
		'mention' => $etude_mention,
		'level' => $level,
		'semester' => $nbr_semester
	]);
	$result_finance = $verification_finance_licence->fetch();

	if (!$result_finance) {
		$dtb->rollBack();
		echo json_encode([
			'success' => false,
			'message' => 'Configuration financière introuvable pour: ' . $status . ' / ' . $etude_mention . ' / L' . $level . ' / Semestre ' . $nbr_semester
		]);
		exit;
	}

		$cout_fraix_generaux = floatval($result_finance['frais_generaux']);		
		$nbr_day = intval($result_finance['nb_jours_semestre']);
		$cout_costume = $result_finance['frais_costume'];

	if ($graduated == 1) {
	
		$cout_frais_graduation = floatval($result_finance['frais_graduation']);
	
	}else{

		$cout_frais_graduation = 0;
	
	}
	
	/*================= VOYAGE D'ETUDE ===============*/
	if ($nbr_semester == 1) {
		// Semestre 1 → frais de voyage normal
		$cout_voyage = $result_finance['frais_voyage'];
	} else {
		// Semestre 2 → vérifier si l'étudiant était inscrit au premier semestre
		$checkSem1 = $dtb->prepare('SELECT COUNT(*) as cnt FROM t_2024_inscription_session ins 
			INNER JOIN t_2023_session ses ON ins.session_id = ses.session_id 
			WHERE ins.student_id = :student_id AND ses.session_year = :annee_scolaire AND ses.session_semester = 1');
		$checkSem1->execute(['student_id' => $student_id, 'annee_scolaire' => $annee_scolaire]);
		$hasSem1 = $checkSem1->fetch();
		
		if ($hasSem1['cnt'] > 0) {
			// Ancien étudiant (inscrit au sem. 1) → frais de voyage normal (150 000)
			$cout_voyage = $result_finance['frais_voyage'];
		} else {
			// Nouvel étudiant au semestre 2 → frais de voyage doublés (300 000)
			$cout_voyage = floatval($result_finance['frais_voyage']) * 2;
		}
	}

	if ($show_Old_status['status'] == "Externe" OR $show_Old_status['status'] == "Bungalow") {

		$cout_fondDepot_dortoir = $result_finance['fond_depot'];
	
	}else{

		$cout_fondDepot_dortoir = 0;

	}

		$cout_logement = floatval($result_finance['dortoir']) * $nbr_day;

	if ($existingFinance) {
		// UPDATE la ligne finance existante (pas de suppression)
		$updateFinance = $dtb->prepare('UPDATE t_2024_etudiant_finace SET
			mention = :mention,
			level = :level,
			status = :status,
			cout_logement = :cout_logement,
			cout_fraix_generaux = :cout_fraix_generaux,
			cout_fondDepot_dortoir = :cout_fondDepot_dortoir,
			cout_frais_graduation = :cout_frais_graduation,
			cout_costume = :cout_costume,
			cout_voyage = :cout_voyage,
			date_entry = :date_entry
		WHERE student_id = :student_id AND session_id = :session_id');

		$updateFinance->execute(array(
			'mention' => $etude_mention,
			'level' => $level,
			'status' => $status,
			'cout_logement' => $cout_logement,
			'cout_fraix_generaux' => $cout_fraix_generaux,
			'cout_fondDepot_dortoir' => $cout_fondDepot_dortoir,
			'cout_frais_graduation' => $cout_frais_graduation,
			'cout_costume' => $cout_costume,
			'cout_voyage' => $cout_voyage,
			'date_entry' => $date_entry,
			'student_id' => $student_id,
			'session_id' => $session_id
		));
	} else {
		// INSERT nouvelle ligne finance
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
	}

	if ($existingSession) {
		// UPDATE l'inscription session existante (pas de suppression)
		$updateSession = $dtb->prepare('UPDATE t_2024_inscription_session SET
			etude_mention = :etude_mention,
			status = :status,
			new_student = :new_student,
			data_completion = :data_completion,
			nbr_semester = :nbr_semester,
			annee_scolaire = :annee_scolaire,
			date_entry = :date_entry
		WHERE student_id = :student_id AND session_id = :session_id');
		$updateSession->execute(array(
			'etude_mention' => $etude_mention,
			'status' => $status,
			'new_student' => $new_student,
			'data_completion' => $data_completion,
			'nbr_semester' => $nbr_semester,
			'annee_scolaire' => $annee_scolaire,
			'date_entry' => $date_entry,
			'student_id' => $student_id,
			'session_id' => $session_id
		));
	} else {
		// INSERT nouvelle inscription session
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
	}

	$dtb->commit();

	echo json_encode([
		'success' => true,
		'message' => 'Session créée avec succès',
		'session_id' => $session_id,
		'data' => $debug_info
	]);

	} catch (Exception $transactionError) {
		$dtb->rollBack();
		throw $transactionError;
	}

	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'message' => 'Erreur: ' . $e->getMessage()
		]);
	}
?>