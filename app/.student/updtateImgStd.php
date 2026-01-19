<?php 
	require('../../data/backdb.php');
	require('student_history_helper.php');

	$id = $_GET['id'];
	$student_id = $_GET['student_id'];
	$last_change_user_id = $_GET['user_id'];

	$image = $_FILES['image_student']['name'];
	$image_tmp = $_FILES['image_student']['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG','.NEF','.nef');
	$extension_image = strrchr($image,".");
	$image_dest = '../photosetudiants/';

	$date = date('Y-m-d');

	if(isset($image) AND !empty($image)){
		// Récupérer l'ancienne image pour l'historique
		$getOldImage = $dtb->query("SELECT image_student FROM tbl_2024_etudiant WHERE id = '".$id."'");
		$oldImageData = $getOldImage->fetch();
		$oldImage = $oldImageData['image_student'];
		
		echo "<br>".$dbimage = $id.'-'.$image;
		in_array($extension_image, $extension);
		move_uploaded_file($image_tmp, $image_dest.$dbimage);


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
	header('location:../../src/student.php?id='.$id.'&page=information');
 ?>