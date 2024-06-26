<?php 
	require('../data/backdb.php');

	$teacher_id = $_GET['id'];
	$last_change_user_id = $_GET['user_id'];

	$image = $_FILES['teacher_image']['name'];
	$image_tmp = $_FILES['teacher_image']['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG','.NEF','.nef');
	$extension_image = strrchr($image,".");
	$image_dest = 'photosenseignants/';

	$date = date('Y-m-d');

	if(isset($image) AND !empty($image)){
		echo "<br>".$dbimage = $id.'-'.$image;
		in_array($extension_image, $extension);
		move_uploaded_file($image_tmp, $image_dest.$dbimage);


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
	header('location:../src/prof.php?id='.$teacher_id.'&page=information');
 ?>