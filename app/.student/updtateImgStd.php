<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // fiches : personnel seul (un compte etudiant les lancait)
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');
	require('student_history_helper.php');

	require_once __DIR__ . '/../../data/photo.php';

	$id = (int) ($_GET['id'] ?? 0);
	$student_id = $_GET['student_id'] ?? '';
	$last_change_user_id = (int) ($_SESSION['user_id'] ?? ($_GET['user_id'] ?? 0));

	$date = date('Y-m-d');

	/* Photo : contenu verifie et reencode, nom fabrique par le serveur
	   (data/photo.php). L'ancien code gardait tel quel un fichier que GD ne
	   savait pas lire, et nommait la photo d'apres le fichier recu. */
	// Fiche inconnue : rien a enregistrer, pas meme le fichier.
	$photo = $id > 0 ? photoEnregistrer($_FILES['image_student'] ?? null, __DIR__ . '/../photosetudiants', (string) $id)
	                 : ['nom' => null, 'erreur' => null];
	if ($photo['erreur'] !== null) {
		header('location:' . $app_base . '/student?id='.$id.'&page=information&error=format');
		exit;
	}

	if ($photo['nom'] !== null && $id > 0) {
		$dbimage = $photo['nom'];

		// Ancienne image, pour l'historique. L'identifiant venu de l'adresse
		// etait colle dans la requete : requete preparee.
		$getOldImage = $dtb->prepare('SELECT image_student FROM tbl_2024_etudiant WHERE id = ?');
		$getOldImage->execute([$id]);
		$oldImage = (string) ($getOldImage->fetchColumn() ?: '');

		$updateNote = $dtb->prepare('UPDATE tbl_2024_etudiant SET 
			image_student=:image_student,
			last_change_user_id=:last_change_user_id,
			last_change_datetime=:last_change_datetime
		 WHERE id=:id');

		$updateNote->bindParam(':image_student',$dbimage,PDO::PARAM_STR);
		$updateNote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		$updateNote->bindParam(':last_change_datetime',$date,PDO::PARAM_STR);
		$updateNote->bindParam(':id',$id,PDO::PARAM_INT);

		$updateNote->execute();
		
		// Enregistrer la modification d'image dans l'historique
		logStudentModification($dtb, $student_id, $id, 'image_student', $oldImage, $dbimage, $last_change_user_id, 'image', 'Modification de la photo');
	}
	header('location:' . $app_base . '/student?id='.$id.'&page=information');
 ?>