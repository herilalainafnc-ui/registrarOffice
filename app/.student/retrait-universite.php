<?php 
	require('../../data/backdb.php');

	$id = $_GET['id'];
	$user_id = $_GET['user_id'];
	$student_id = $_GET['student_id'];
	
	$type_retrait = $_POST['type_retrait'];
	$date_entree = $_POST['date_entree_universite'];
	$date_depart = $_POST['date_depart'];
	$date_retour_probable = isset($_POST['date_retour_probable']) && !empty($_POST['date_retour_probable']) ? $_POST['date_retour_probable'] : null;
	$cause_depart = $_POST['cause_depart'];
	$details_cause = isset($_POST['details_cause']) ? $_POST['details_cause'] : '';
	
	$date_action = date('Y-m-d H:i:s');

	// Déterminer le statut selon le type de retrait
	// 2 = retrait momentané, 3 = retrait définitif
	$statut_retrait = ($type_retrait == 'momentane') ? 2 : 3;

	// Mettre à jour le statut de l'étudiant comme retiré
	$updateStudent = $dtb->prepare('UPDATE tbl_2024_etudiant SET 
		retrait_universite = :statut_retrait,
		type_retrait = :type_retrait,
		date_entree_universite = :date_entree,
		date_depart_universite = :date_depart,
		date_retour_probable = :date_retour,
		cause_depart = :cause_depart,
		details_cause_depart = :details_cause,
		retrait_by = :user_id,
		retrait_date = :date_action,
		last_change_user_id = :user_id2,
		last_change_datetime = :date_action2
		WHERE student_id = :student_id');

	$updateStudent->execute(array(
		'statut_retrait' => $statut_retrait,
		'type_retrait' => $type_retrait,
		'date_entree' => $date_entree,
		'date_depart' => $date_depart,
		'date_retour' => $date_retour_probable,
		'cause_depart' => $cause_depart,
		'details_cause' => $details_cause,
		'user_id' => $user_id,
		'date_action' => $date_action,
		'user_id2' => $user_id,
		'date_action2' => $date_action,
		'student_id' => $student_id
	));

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
		'type_retrait' => $type_retrait,
		'date_entree' => $date_entree,
		'date_depart' => $date_depart,
		'date_retour' => $date_retour_probable,
		'cause_depart' => $cause_depart,
		'details_cause' => $details_cause,
		'action_by' => $user_id,
		'action_date' => $date_action,
		'action_type' => 'retrait'
	));

	header('location:../../src/student.php?id='.$id.'&page=information&msg=retrait_ok');
?>
