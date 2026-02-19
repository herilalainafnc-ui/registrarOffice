<?php 
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require '../../data/backdb.php';

	$id = $_GET['id'];
	$last_change_user_id = $_GET['rg_id'];
	$remove = 1;
	$last_change_datetime = date('Y-m-d');

	$update = $dtb->prepare("UPDATE t_2023_cours SET
		remove=:remove,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	
	WHERE id=:id");
		$update->bindParam(':remove',$remove,PDO::PARAM_INT);
		$update->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		$update->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
		$update->bindParam(':id',$id,PDO::PARAM_INT);

		$update->execute();

	header('location:' . $app_base . '/courses');

 ?>