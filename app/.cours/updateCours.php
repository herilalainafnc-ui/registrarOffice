<?php 
	require '../../data/backdb.php';


	$id = $_GET['id'];
	$last_change_user_id = $_GET['rg_id'];

	$sigle = $_POST['sigle'];
	$dep_desc = $_POST['dep_desc'];
	$id_teacher = $_POST['id_teacher'];
	$title = $_POST['title'];
	$title_english = $_POST['title_english'];
	$yearlevel = $_POST['yearlevel'];
	$nb_crd = $_POST['nb_crd'];
	$semester = $_POST['semester'];
	$category = $_POST['category'];

	$last_change_datetime = date('Y-m-d');

	$update = $dtb->prepare("UPDATE t_2023_cours SET
		sigle=:sigle,
		dep_desc=:dep_desc,
		id_teacher=:id_teacher,
		title=:title,
		title_english=:title_english,
		yearlevel=:yearlevel,
		nb_crd=:nb_crd,
		semester=:semester,
		category=:category,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	WHERE id=:id");

		$update->bindParam('sigle',$sigle,PDO::PARAM_STR);
		$update->bindParam('dep_desc',$dep_desc,PDO::PARAM_STR);
		$update->bindParam('id_teacher',$id_teacher,PDO::PARAM_INT);
		$update->bindParam('title',$title,PDO::PARAM_STR);
		$update->bindParam('title_english',$title_english,PDO::PARAM_STR);
		$update->bindParam('yearlevel',$yearlevel,PDO::PARAM_INT);
		$update->bindParam('nb_crd',$nb_crd,PDO::PARAM_INT);
		$update->bindParam('semester',$semester,PDO::PARAM_INT);
		$update->bindParam('category',$category,PDO::PARAM_INT);
		$update->bindParam('last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		$update->bindParam('last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
		$update->bindParam('id',$id,PDO::PARAM_INT);

		$update->execute();

	header('location:../../src/cours.php?id='.$id.'&page=information');

 ?>