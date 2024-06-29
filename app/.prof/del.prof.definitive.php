<?php
	require '../../data/backdb.php';

	$teacher_id = $_GET['teacher_id'];

	$delete = $dtb->prepare("DELETE FROM teacher WHERE teacher_id =:teacher_id");
	$delete->bindvalue(':teacher_id', $teacher_id, PDO::PARAM_INT);
	$delete->execute();
	header('location:../../src/accueil.prof.php');
 ?>