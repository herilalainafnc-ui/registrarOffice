<?php
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require '../../data/backdb.php';

	$id = $_GET['id'];

	$delete = $dtb->prepare("DELETE FROM t_2023_cours WHERE id =:id");
	$delete->bindvalue(':id', $id, PDO::PARAM_INT);
	$delete->execute();
	header('location:' . $app_base . '/courses');
 ?>