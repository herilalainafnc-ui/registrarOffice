<?php 
	require('../../data/backdb.php');
	require('student_history_helper.php');

	$id = $_GET['id'];
	$user_id = $_GET['user_id'];
	$student_id = $_GET['student_id'];
	
	$date_debut = $_POST['date_debut_suspension'];
	$date_fin = $_POST['date_fin_suspension'];
	$motif = $_POST['motif_suspension'];
	
	$date_action = date('Y-m-d H:i:s');

	// Mettre à jour le statut de l'étudiant comme suspendu
	$updateStudent = $dtb->prepare('UPDATE tbl_2024_etudiant SET 
		suspended = 1,
		date_debut_suspension = :date_debut,
		date_fin_suspension = :date_fin,
		motif_suspension = :motif,
		suspended_by = :user_id,
		suspended_date = :date_action,
		last_change_user_id = :user_id2,
		last_change_datetime = :date_action2
		WHERE student_id = :student_id');

	$updateStudent->execute(array(
		'date_debut' => $date_debut,
		'date_fin' => $date_fin,
		'motif' => $motif,
		'user_id' => $user_id,
		'date_action' => $date_action,
		'user_id2' => $user_id,
		'date_action2' => $date_action,
		'student_id' => $student_id
	));

	// Enregistrer dans l'historique des modifications étudiantes
	logStudentModification($dtb, $student_id, $id, 'suspended', '0', '1', $user_id, 'suspension', $motif);
	logStudentModification($dtb, $student_id, $id, 'date_debut_suspension', null, $date_debut, $user_id, 'suspension');
	logStudentModification($dtb, $student_id, $id, 'date_fin_suspension', null, $date_fin, $user_id, 'suspension');
	logStudentModification($dtb, $student_id, $id, 'motif_suspension', null, $motif, $user_id, 'suspension');

	// Enregistrer dans l'historique des suspensions
	$insertHistory = $dtb->prepare('INSERT INTO t_suspension_history (
		student_id,
		date_debut,
		date_fin,
		motif,
		action_by,
		action_date,
		action_type
	) VALUES (
		:student_id,
		:date_debut,
		:date_fin,
		:motif,
		:action_by,
		:action_date,
		:action_type
	)');

	$insertHistory->execute(array(
		'student_id' => $student_id,
		'date_debut' => $date_debut,
		'date_fin' => $date_fin,
		'motif' => $motif,
		'action_by' => $user_id,
		'action_date' => $date_action,
		'action_type' => 'suspension'
	));

	header('location:../../src/student.php?id='.$id.'&page=information&msg=suspended');
?>
