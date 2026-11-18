<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul (un compte etudiant les lancait)
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');

	require_once __DIR__ . '/../../data/photo.php';

	$teacher_id = (int) ($_GET['id'] ?? 0);
	$last_change_user_id = (int) ($_SESSION['user_id'] ?? ($_GET['user_id'] ?? 0));
	$retour = $app_base . '/professor?id=' . $teacher_id . '&page=information';

	$date = date('Y-m-d');

	/* Photo : contenu verifie et reencode, nom fabrique par le serveur
	   (data/photo.php). Le controle d'extension n'etait pas lu — tout fichier
	   passait, un script PHP compris —, et le nom partait d'une variable
	   jamais definie ($id) suivie du nom du fichier recu. */
	// Fiche inconnue : rien a enregistrer, pas meme le fichier.
	$photo = $teacher_id > 0 ? photoEnregistrer($_FILES['teacher_image'] ?? null, __DIR__ . '/../photosenseignants', 't' . $teacher_id)
	                         : ['nom' => null, 'erreur' => null];
	if ($photo['erreur'] !== null) {
		header('location:' . $retour . '&error=photo');
		exit;
	}

	if ($photo['nom'] !== null && $teacher_id > 0) {
		$dbimage = $photo['nom'];

		$updateNote = $dtb->prepare('UPDATE teacher SET 
			teacher_image=:teacher_image,
			last_change_user_id=:last_change_user_id,
			last_change_datetime=:last_change_datetime
		 WHERE teacher_id=:teacher_id');

		$updateNote->bindParam(':teacher_image',$dbimage,PDO::PARAM_STR);
		$updateNote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		$updateNote->bindParam(':last_change_datetime',$date,PDO::PARAM_STR);
		$updateNote->bindParam(':teacher_id',$teacher_id,PDO::PARAM_INT);

		$updateNote->execute();
	}
	header('location:' . $retour);
 ?>