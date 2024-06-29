<?php
	require ('../data/backdb.php');

	$date_entry = date('Y-m-d');
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

	$active = 1;

<<<<<<< HEAD
if($sigle !="" AND $title!="") {
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

}else{
	echo "Opération non effectué!!!";
 ?>
 	<a href="../list_cours.php">Retour</a>
<?php 
}
 ?>
 
=======
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
>>>>>>> 85524414d66b1ba7e7b483083fca4da32379781b
