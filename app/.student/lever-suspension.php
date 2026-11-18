<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul (un compte etudiant les lancait)
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');
	require('student_history_helper.php');

	$id = $_GET['id'];
	$user_id = (int) ($_SESSION['user_id'] ?? 0); // auteur : le compte connecte, pas l'adresse
	$student_id = $_GET['student_id'];
	
	$commentaire = isset($_POST['commentaire_levee']) ? $_POST['commentaire_levee'] : '';
	
	$date_action = date('Y-m-d H:i:s');

	// Lever la suspension de l'étudiant
	$updateStudent = $dtb->prepare('UPDATE tbl_2024_etudiant SET 
		suspended = 0,
		last_change_user_id = :user_id,
		last_change_datetime = :date_action
		WHERE student_id = :student_id');

	$updateStudent->execute(array(
		'user_id' => $user_id,
		'date_action' => $date_action,
		'student_id' => $student_id
	));

	// Enregistrer dans l'historique des modifications étudiantes
	logStudentModification($dtb, $student_id, $id, 'suspended', '1', '0', $user_id, 'levee_suspension', $commentaire);

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
		'date_debut' => $date_action,
		'date_fin' => $date_action,
		'motif' => 'Levée de suspension: ' . $commentaire,
		'action_by' => $user_id,
		'action_date' => $date_action,
		'action_type' => 'levee'
	));

	header('location:' . $app_base . '/student?id='.$id.'&page=information&msg=unsuspended');
?>
