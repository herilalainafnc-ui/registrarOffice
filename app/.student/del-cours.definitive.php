<?php
	require '../../data/backdb.php';

	$id = $_GET['id'];
	echo $idSupprCours = $_GET['idSupprCours'];

	$delete = $dtb->prepare("DELETE FROM t_2023_notes WHERE id =:idSupprCours");
	$delete->bindvalue(':idSupprCours', $idSupprCours, PDO::PARAM_INT);
	$delete->execute();
	header('location:../../src/student.php?id='.$id.'&page=courssupprim');
 ?>