<?php 
require '../data/backdb.php';

	$teacher_id = $_GET['id'];
	$name = $_POST['name'];
	$lastName = $_POST['lastName'];
	$birthday = $_POST['birthday'];
    $lieuN = $_POST['lieuN'];
    $sex = $_POST['sex'];
    $religion = $_POST['religion'];
	$diplome = $_POST['diplome'];
	$phone = $_POST['phone'];
	$email = $_POST['email'];

	$last_change_datetime = date('Y-m-d');
	

	$update = $dtb->prepare("UPDATE teacher SET 
		name=:name,
		lastName=:lastName,
		birthday=:birthday,
		lieuN=:lieuN,
		sex=:sex,
		religion=:religion,
		diplome=:diplome,
		phone=:phone,
		email=:email,
		last_change_datetime=:last_change_datetime

		WHERE teacher_id=:teacher_id");

	$update->bindParam(':name',$name,PDO::PARAM_STR);
	$update->bindParam(':lastName',$lastName,PDO::PARAM_STR);
	$update->bindParam(':birthday',$birthday,PDO::PARAM_STR);
	$update->bindParam(':lieuN',$lieuN,PDO::PARAM_STR);
	$update->bindParam(':sex',$sex,PDO::PARAM_INT);
	$update->bindParam(':religion',$religion,PDO::PARAM_STR);
	$update->bindParam(':diplome',$diplome,PDO::PARAM_STR);
	$update->bindParam(':phone',$phone,PDO::PARAM_STR);
	$update->bindParam(':email',$email,PDO::PARAM_STR);
	$update->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	
	$update->bindParam(':teacher_id',$teacher_id,PDO::PARAM_INT);

$update->execute();

header('location:../src/prof.php?id='.$teacher_id.'&page=information');
