<?php 
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require ('../../data/backdb.php');
	
	$id = $_GET['id'];
	$idcours = $_GET['idcours'];
	$grade =  str_replace(',', '.', $_POST['note']);
	$date_entry = date('Y-m-d');

	$year = $_GET['year'];
	$sigle = $_GET['sigle'];

	$update = $dtb->prepare("UPDATE t_2023_notes SET grade=:grade WHERE id=:idcours");
	$update->bindParam(':grade',$grade,PDO::PARAM_STR);
	$update->bindParam(':idcours',$idcours,PDO::PARAM_INT);
	$update->execute();

	header('location:' . $app_base . '/course?id='.$id.'&page=notes#'.$sigle.$year);

 ?>