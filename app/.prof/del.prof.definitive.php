<?php
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