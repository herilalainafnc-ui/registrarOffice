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
	$parcours = $_POST['parcours'];

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
		parcours=:parcours,
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
		$update->bindParam('parcours',$parcours,PDO::PARAM_STR);
		$update->bindParam('last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		$update->bindParam('last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
		$update->bindParam('id',$id,PDO::PARAM_INT);

		$update->execute();


	$modify = $dtb->prepare('UPDATE t_2023_notes SET 
		Sigle=:sigle, 
		title_cours=:title, 
		title_english=:title_english,
		credit=:nb_crd,
		teacher_id=:id_teacher,
		semester=:semester,
		lab=:lab,
		yearlevel=:yearlevel,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	WHERE id_cours=:id');

	$modify->bindParam(':sigle',$sigle,PDO::PARAM_STR);
	$modify->bindParam(':title',$title,PDO::PARAM_STR);
	$modify->bindParam(':title_english',$title_english,PDO::PARAM_STR);
	$modify->bindParam(':nb_crd',$nb_crd,PDO::PARAM_INT);
	$modify->bindParam(':id_teacher',$id_teacher,PDO::PARAM_STR);
	$modify->bindParam(':semester',$semester,PDO::PARAM_INT);
	$modify->bindParam(':lab',$lab,PDO::PARAM_INT);
	$modify->bindParam(':yearlevel',$yearlevel,PDO::PARAM_INT);
	$modify->bindParam('last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$modify->bindParam('last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$modify->bindParam(':id',$id,PDO::PARAM_INT);

	$modify->execute();

	header('location:../../src/cours.php?id='.$id.'&page=information');

 ?>