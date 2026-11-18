<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul (un compte etudiant les lancait)
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require '../../data/backdb.php';

	$teacher_id = $_GET['teacher_id'];

	$delete = $dtb->prepare("DELETE FROM teacher WHERE teacher_id =:teacher_id");
	$delete->bindvalue(':teacher_id', $teacher_id, PDO::PARAM_INT);
	$delete->execute();
	header('location:' . $app_base . '/professors');
 ?>