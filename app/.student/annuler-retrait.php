<?php 
	require('../../data/backdb.php');
	require('student_history_helper.php');

	$id = $_GET['id'];
	$user_id = $_GET['user_id'];
	$student_id = $_GET['student_id'];
	
	$commentaire = isset($_POST['commentaire_retour']) ? $_POST['commentaire_retour'] : '';
	
	$date_action = date('Y-m-d H:i:s');

	// Récupérer les informations actuelles du retrait pour l'historique
	$getStudent = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id');
	$getStudent->execute(array('student_id' => $student_id));
	$student = $getStudent->fetch();
	
	$ancien_statut_retrait = $student['retrait_universite'];

	// Remettre l'étudiant comme actif
	$updateStudent = $dtb->prepare('UPDATE tbl_2024_etudiant SET 
		retrait_universite = 0,
		last_change_user_id = :user_id,
		last_change_datetime = :date_action
		WHERE student_id = :student_id');

	$updateStudent->execute(array(
		'user_id' => $user_id,
		'date_action' => $date_action,
		'student_id' => $student_id
	));

	// Enregistrer dans l'historique des modifications étudiantes
	logStudentModification($dtb, $student_id, $id, 'retrait_universite', $ancien_statut_retrait, '0', $user_id, 'annulation_retrait', $commentaire);

	// Enregistrer dans l'historique des retraits
	$insertHistory = $dtb->prepare('INSERT INTO t_retrait_history (
		student_id,
		type_retrait,
		date_entree_universite,
		date_depart,
		date_retour_probable,
		cause_depart,
		details_cause,
		action_by,
		action_date,
		action_type
	) VALUES (
		:student_id,
		:type_retrait,
		:date_entree,
		:date_depart,
		:date_retour,
		:cause_depart,
		:details_cause,
		:action_by,
		:action_date,
		:action_type
	)');

	$insertHistory->execute(array(
		'student_id' => $student_id,
		'type_retrait' => $student['type_retrait'],
		'date_entree' => $student['date_entree_universite'],
		'date_depart' => $student['date_depart_universite'],
		'date_retour' => date('Y-m-d'),
		'cause_depart' => $student['cause_depart'],
		'details_cause' => $commentaire,
		'action_by' => $user_id,
		'action_date' => $date_action,
		'action_type' => 'retour'
	));

	header('location:../../src/student.php?id='.$id.'&page=information&msg=retour_ok');
?>
