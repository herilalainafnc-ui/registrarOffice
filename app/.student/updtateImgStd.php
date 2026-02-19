<?php 
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');
	require('student_history_helper.php');

	$id = $_GET['id'];
	$student_id = $_GET['student_id'];
	$last_change_user_id = $_GET['user_id'];

	$image = $_FILES['image_student']['name'];
	$image_tmp = $_FILES['image_student']['tmp_name'];
	$allowed_extensions = ['.jpg','.JPG','.png','.PNG','.jpeg','.JPEG'];
	$extension_image = strrchr($image,".");
	$image_dest = '../photosetudiants/';

	$date = date('Y-m-d');

	if(isset($image) AND !empty($image)){

		// Vérifier l'extension
		if(!in_array($extension_image, $allowed_extensions)){
			header('location:' . $app_base . '/student?id='.$id.'&page=information&error=format');
			exit;
		}

		// Récupérer l'ancienne image pour l'historique
		$getOldImage = $dtb->query("SELECT image_student FROM tbl_2024_etudiant WHERE id = '".$id."'");
		$oldImageData = $getOldImage->fetch();
		$oldImage = $oldImageData['image_student'];
		
		// Nom du fichier final (toujours en .jpg)
		$dbimage = $id.'-'.$image;
		$finalPath = $image_dest . $dbimage;

		// Déplacer le fichier uploadé temporairement
		$tempPath = $image_dest . 'tmp_' . $dbimage;
		move_uploaded_file($image_tmp, $tempPath);

		// Vérifier le vrai type MIME du fichier (pas l'extension)
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$realMime = $finfo->file($tempPath);

		// Si c'est un format non supporté par les navigateurs (HEIC, AVIF, RAW, etc.)
		// ou si le MIME ne correspond pas à une image web, tenter une conversion via GD
		$webMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
		
		if (in_array($realMime, $webMimes) && function_exists('imagecreatefromstring')) {
			// C'est une vraie image web — vérifier qu'elle est valide et ré-encoder en JPEG propre
			$imgData = file_get_contents($tempPath);
			$gdImage = @imagecreatefromstring($imgData);
			if ($gdImage) {
				// Ré-encoder en JPEG propre (corrige orientation EXIF, etc.)
				$dbimage = pathinfo($dbimage, PATHINFO_FILENAME) . '.jpg';
				$finalPath = $image_dest . $dbimage;
				imagejpeg($gdImage, $finalPath, 90);
				imagedestroy($gdImage);
				if ($tempPath !== $finalPath) @unlink($tempPath);
			} else {
				// GD ne peut pas lire l'image — garder tel quel
				rename($tempPath, $finalPath);
			}
		} elseif (!in_array($realMime, $webMimes)) {
			// Format non-web (HEIC, HEIF, AVIF, TIFF, etc.)
			// Tenter une conversion avec GD (fonctionne pour certains formats)
			$converted = false;
			if (function_exists('imagecreatefromstring')) {
				$imgData = file_get_contents($tempPath);
				$gdImage = @imagecreatefromstring($imgData);
				if ($gdImage) {
					$dbimage = pathinfo($dbimage, PATHINFO_FILENAME) . '.jpg';
					$finalPath = $image_dest . $dbimage;
					imagejpeg($gdImage, $finalPath, 90);
					imagedestroy($gdImage);
					if ($tempPath !== $finalPath) @unlink($tempPath);
					$converted = true;
				}
			}
			if (!$converted) {
				// Impossible de convertir — supprimer et rediriger avec erreur
				@unlink($tempPath);
				header('location:' . $app_base . '/student?id='.$id.'&page=information&error=heic');
				exit;
			}
		} else {
			// Pas de GD mais format web — garder tel quel
			rename($tempPath, $finalPath);
		}

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