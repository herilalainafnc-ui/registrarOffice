<?php
	require '../dbauto/connectdb.php';

	$date_entry = $_POST['date_entry'];
	$sigle = $_POST['sigle'];
	$title = $_POST['title'];
	$title_english = $_POST['title_english'];
	$nb_credit = $_POST['nb_credit'];
	$category = $_POST['category'];
	$dep_desc = $_POST['dep_desc'];
	$teacher_id = $_POST['teacher_id'];;
	$semester = $_POST['semester'];
	$lab = $_POST['lab'];
	
	$ajout = 1;

	$yearlevel = $_POST['yearlevel'];
	$description = $_POST['description'];
	$remark = $_POST['remark'];


	
	if(isset($_POST['active'])){
		echo "<br>". $active = 1;
	}

$insertteacher = $dtb->prepare("INSERT INTO t_2023_cours(
			Sigle,
			title,
			title_english,
			Description,
			nb_crd,
			Remark,
			dep_desc,
			category,
			id_teacher,
			semester,
			lab,
			ajout,
			yearlevel,
			active,
			date_entry
) VALUES (
			:Sigle,
			:title,
			:title_english,
			:Description,
			:nb_crd,
			:Remark,
			:dep_desc,
			:category,
			:id_teacher,
			:semester,
			:lab,
			:ajout,
			:yearlevel,
			:active,
			:date_entry
)");$insertteacher->execute(array(
			'Sigle' => $sigle,
			'title' => $title,
			'title_english' => $title_english,
			'Description' => $description,
			'nb_crd' => $nb_credit,
			'Remark' => $remark,
			'dep_desc' => $dep_desc,
			'category' => $category,
			'id_teacher' => $teacher_id,
			'semester' => $semester,
			'lab' => $lab,
			'ajout' => $ajout,
			'yearlevel' => $yearlevel,
			'active' => $active,
			'date_entry' => $date_entry 
));
	header('location:../list_cours.php');
 ?>