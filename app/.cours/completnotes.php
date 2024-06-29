<?php 
	require ('../../data/backdb.php');
	
	$id = $_GET['id'];
	$idcours = $_GET['idcours'];
	$grade = $_POST['note'];
	$date_entry = date('Y-m-d');

	$update = $dtb->prepare("UPDATE t_2023_notes SET grade=:grade WHERE id=:idcours");
	$update->bindParam(':grade',$grade,PDO::PARAM_STR);
	$update->bindParam(':idcours',$idcours,PDO::PARAM_INT);
	$update->execute();

	header('location:../../src/cours.php?id='.$id.'&page=notes');

 ?>