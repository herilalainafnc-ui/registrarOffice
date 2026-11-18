<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(3); refuserEtudiant(); refuserEnseignant(); // cours de l'étudiant : niveau 3 et moins, comme del-cours.momentanee
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require '../../data/backdb.php';
	require_once __DIR__ . '/../../data/inscription.frais.php'; // inscriptionCoursFinanceSuivre : la facturation du cours suit

	$id = $_GET['id'];
	$idSupprCours = (int) ($_GET['idSupprCours'] ?? 0);

	/* L'effacement definitif d'un cours retire (page « Cours supprimés ») : la note, et
	   la ligne de facturation de CET etudiant pour CETTE session, lues depuis la note.
	   Avant le 20/10/2026, la facturation du cours etait effacee chez TOUS les etudiants
	   (DELETE ... WHERE cours_id seul, avec un id_cours venu de l'adresse). */
	$lire = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :id');
	$lire->execute(['id' => $idSupprCours]);
	$note = $lire->fetch(PDO::FETCH_ASSOC);

	if ($note) {
		$dtb->beginTransaction();
		$delete = $dtb->prepare("DELETE FROM t_2023_notes WHERE id =:idSupprCours");
		$delete->bindValue(':idSupprCours', $idSupprCours, PDO::PARAM_INT);
		$delete->execute();
		inscriptionCoursFinanceSuivre($dtb, $note, 'effacer', (int) ($_SESSION['user_id'] ?? 0));
		$dtb->commit();
	}

	header('location:' . $app_base . '/student?id='.$id.'&page=courssupprim');
 ?>
