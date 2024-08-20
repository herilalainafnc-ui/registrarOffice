<?php
	require '../../data/backdb.php';

	$id = $_GET['id'];
	$idSupprCours = $_GET['idSupprCours'];
	echo $id_cours = $_GET['id_cours'];
	$delete = $dtb->prepare("DELETE FROM t_2023_notes WHERE id =:idSupprCours");
	$delete->bindvalue(':idSupprCours', $idSupprCours, PDO::PARAM_INT);
	$delete->execute();

	$deleteCoursFinance = $dtb->prepare("DELETE FROM t_2024_cours_finance WHERE cours_id =:idSupprCours");
	
	$deleteCoursFinance->bindvalue(':idSupprCours', $id_cours, PDO::PARAM_INT);
	$deleteCoursFinance->execute();

	header('location:../../src/student.php?id='.$id.'&page=courssupprim');
 ?>