<?php 
	require ('../../data/backdb.php');

	$name = $_POST['name'];
	$lastName = $_POST['lastName'];
	$birthday = $_POST['birthday'];
	$lieuN = $_POST['lieuN'];
	$address = $_POST['address'];
	$sex = $_POST['sex'];
	$blood_group = $_POST['blood_group'];
	$phone = $_POST['phone'];
	$email = $_POST['email'];
	$diplome = $_POST['diplome'];
	$religion = $_POST['religion'];
	$position = $_POST['position'];

	/*:::::::::::::::::::: UID GENERATE ::::::::::::::::::::*/

	$uid = rand(1000,9999);

	/*:::::::::::::::::::: IMAGE GENERATE ::::::::::::::::::::*/

	$image = $_FILES['teacher_image']['name'];
	$image_tmp = $_FILES['teacher_image']['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG');
	$extension_image = strrchr($image,".");
	$image_dest = 'photosenseignants/';

	$teacher_image = $uid.''.$image;

	in_array($extension_image, $extension);
	move_uploaded_file($image_tmp, $image_dest.$teacher_image);


	$insertProf = $dtb->prepare('INSERT INTO teacher(
		uid,
		name,
		lastName,
		birthday,
		lieuN,
		address,
		sex,
		blood_group,
		teacher_image,
		phone,
		email,
		diplome,
		religion,
		position

		) VALUES (
		:uid,
		:name,
		:lastName,
		:birthday,
		:lieuN,
		:address,
		:sex,
		:blood_group,
		:teacher_image,
		:phone,
		:email,
		:diplome,
		:religion,
		:position

		)');$insertProf->execute(array(

		'uid' => $uid,
		'name' => $name,
		'lastName' => $lastName,
		'birthday' => $birthday,
		'lieuN' => $lieuN,
		'address' => $address,
		'sex' => $sex,
		'blood_group' => $blood_group,
		'teacher_image' => $teacher_image,
		'phone' => $phone,
		'email' => $email,
		'diplome' => $diplome,
		'religion' => $religion,
		'position' => $position

		));

		header('location:../../src/accueil.prof.php')
 ?>