<?php 
require '../../data/backdb.php';
	
	$last_change_user_id = $_GET['rg_id'];
	$id = $_GET['id'];
	$retrait_date = date('Y-m-d');
	$type_retrait = 5;


	$delete = $dtb->prepare("UPDATE etudiant_second_semester_23 SET
		type_retrait=:type_retrait,
		retrait_date=:retrait_date,
		last_change_user_id=:last_change_user_id
		WHERE id=:id");
	$delete->bindParam(':type_retrait',$type_retrait,PDO::PARAM_INT);
	$delete->bindParam(':retrait_date',$retrait_date,PDO::PARAM_STR);
	$delete->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_STR);
	$delete->bindParam(':id',$id,PDO::PARAM_INT);

	$delete->execute();

	header('location:../../src/accueil.php');

?>