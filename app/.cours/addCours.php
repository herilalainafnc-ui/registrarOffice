<?php
	require ('../../data/backdb.php');

	$date_entry = date('Y-m-d');
	$sigle = $_POST['sigle'];
	$title = $_POST['title'];
	$title_english = $_POST['title_english'];
	$nb_credit = $_POST['nb_credit'];
	$category = $_POST['category'];
	$dep_desc = $_POST['dep_desc'];
	$parcours = $_POST['parcours'];
	$teacher_id = $_POST['teacher_id'];;
	$semester = $_POST['semester'];
	$lab = $_POST['lab'];
	
	if($lab == "0" OR $lab == "") {
 				$cout_lab = 0;
 			}else{
 				$cout_lab = 30000;
			}

 			$cCrd = 18000;

 			$cout = $cCrd * $nb_crd;

	$ajout = 1;

	$yearlevel = $_POST['yearlevel'];
	$description = $_POST['description'];
	$remark = $_POST['remark'];

	$active = 1;

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
				parcours,
				id_teacher,
				semester,
				lab,
				cout,
				cout_lab,
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
				:parcours,
				:id_teacher,
				:semester,
				:lab,
				:cout,
				:cout_lab,
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
				'parcours' => $parcours,
				'id_teacher' => $teacher_id,
				'semester' => $semester,
				'lab' => $lab,
				'cout' => $cout,
				'cout_lab' => $cout_lab,
				'ajout' => $ajout,
				'yearlevel' => $yearlevel,
				'active' => $active,
				'date_entry' => $date_entry 
	));

	header('location:../../src/accueil.cours.php');

}else{
	echo "Opération non effectué!!!";
 ?>
 	<a href="../../src/accueil.cours.php">Retour</a>
<?php 
}
 ?>