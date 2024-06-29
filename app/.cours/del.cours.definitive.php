<?php
	require '../../data/backdb.php';

	$id = $_GET['id'];

	$delete = $dtb->prepare("DELETE FROM t_2023_cours WHERE id =:id");
	$delete->bindvalue(':id', $id, PDO::PARAM_INT);
	$delete->execute();
	header('location:../../src/accueil.cours.php');
 ?>