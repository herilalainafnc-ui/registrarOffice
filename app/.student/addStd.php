<?php 
	require ('../../data/backdb.php');

	$student_nom = $_POST['student_nom'];
	$student_prenom = $_POST['student_prenom'];
	$dateNaissance = $_POST['dateNaissance'];
	$lieuNaissance = $_POST['lieuNaissance'];
	$num_cin = $_POST['num_cin'];
	
	if (!empty($_POST['cin_date_delivre'])) {
		$cin_date_delivre = $_POST['cin_date_delivre'];
	}else{
		$cin_date_delivre = null;
	}
	$cin_region = $_POST['cin_region'];
	$sex = $_POST['sex'];
	$nationalite = $_POST['nationalite'];
	$student_tel = $_POST['student_tel'];
	$student_email = $_POST['student_email'];
	$pays_origine = $_POST['pays_origine'];
	$student_region = $_POST['student_region'];
	$student_adresse = $_POST['student_adresse'];
	$student_id = $_POST['student_id'];

	$mention = $_POST['etude_envisage'];
	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle ="'.$mention.'"');
	$showM = $findMention->fetch();
	$etude_envisage = $showM['filiere_description'];

	$annee_etude = $_POST['annee_etude'];
	$status = $_POST['status'];
	$etude_option = $_POST['etude_option'];
	$annee_scolaire = $_POST['annee_scolaire'];
	$new_student = $_POST['new_student'];
	$father_name = $_POST['father_name'];
	$father_prof = $_POST['father_prof'];
	$mother_name = $_POST['mother_name'];
	$mother_prof = $_POST['mother_prof'];
	$parent_tel = $_POST['parent_tel'];
	$parent_adresse = $_POST['parent_adresse'];
	$sponsor_nom = $_POST['sponsor_nom'];
	$sponsor_prenom = $_POST['sponsor_prenom'];
	$sponsor_tel = $_POST['sponsor_tel'];
	$sponsor_adresse = $_POST['sponsor_adresse'];
	$situationf = $_POST['situationf'];
	$nom_conjoint = $_POST['nom_conjoint'];
	$nb_enfant = $_POST['nb_enfant'];
	$religion = $_POST['religion'];
	$num_visa = $_POST['num_visa'];
	$last_change_user_id = $_GET['rg_id'];
	$date_entry = date("Y-m-d");

	/*:::::::::::::::::::: PASSWORD MAIL GENERATE ::::::::::::::::::::*/

	$a = rand(1000,9999);
	
	$mois = date('m');
	if ($mois < 7){
		$y = date('Y');
	}else{
		$y = date('Y')+1;
	}
	
	$password = $a."Student".$y;

	/*:::::::::::::::::::: CODE-BAR GENERATE ::::::::::::::::::::*/

	$lookup_code = $y.$student_id;


	/*:::::::::::::::::::: IMAGE GENERATE ::::::::::::::::::::*/

	$image = $_FILES['image_student']['name'];
	$image_tmp = $_FILES['image_student']['tmp_name'];
	$extension = array('.jpg','.JPG','.png','.PNG','.jpeg','.JPEG');
	$extension_image = strrchr($image,".");
	$image_dest = '../photosetudiants/';

	$dbimage = $student_id.''.$image;

	in_array($extension_image, $extension);
	move_uploaded_file($image_tmp, $image_dest.$dbimage);



	$insertStd = $dtb->prepare('INSERT INTO etudiant_second_semester_23(
		student_nom,
		student_prenom,
		dateNaissance,
		lieuNaissance,
		num_cin,
		cin_date_delivre,
		cin_region,
		sex,
		nationalite,
		student_tel,
		student_email,
		password,
		lookup_code,
		pays_origine,
		student_region,
		student_adresse,
		image_student,
		student_id,
		etude_envisage,
		annee_etude,
		status,
		etude_option,
		annee_scolaire,
		new_student,
		father_name,
		father_prof,
		mother_name,
		mother_prof,
		parent_tel,
		parent_adresse,
		sponsor_nom,
		sponsor_prenom,
		sponsor_tel,
		sponsor_adresse,
		situationf,
		nom_conjoint,
		nb_enfant,
		religion,
		num_visa,
		last_change_user_id,
		date_entry

	)VALUES(
		:student_nom,
		:student_prenom,
		:dateNaissance,
		:lieuNaissance,
		:num_cin,
		:cin_date_delivre,
		:cin_region,
		:sex,
		:nationalite,
		:student_tel,
		:student_email,
		:password,
		:lookup_code,
		:pays_origine,
		:student_region,
		:student_adresse,
		:image_student,
		:student_id,
		:etude_envisage,
		:annee_etude,
		:status,
		:etude_option,
		:annee_scolaire,
		:new_student,
		:father_name,
		:father_prof,
		:mother_name,
		:mother_prof,
		:parent_tel,
		:parent_adresse,
		:sponsor_nom,
		:sponsor_prenom,
		:sponsor_tel,
		:sponsor_adresse,
		:situationf,
		:nom_conjoint,
		:nb_enfant,
		:religion,
		:num_visa,
		:last_change_user_id,
		:date_entry

	)');$insertStd->execute(array(
		'student_nom' => $student_nom,
		'student_prenom' => $student_prenom,
		'dateNaissance' => $dateNaissance,
		'lieuNaissance' => $lieuNaissance,
		'num_cin' => $num_cin,
		'cin_date_delivre' => $cin_date_delivre,
		'cin_region' => $cin_region,
		'sex' => $sex,
		'nationalite' => $nationalite,
		'student_tel' => $student_tel,
		'student_email' => $student_email,
		'password' => $password,
		'lookup_code' => $lookup_code,
		'pays_origine' => $pays_origine,
		'student_region' => $student_region,
		'student_adresse' => $student_adresse,
		'image_student' => $dbimage,
		'student_id' => $student_id,
		'etude_envisage' => $etude_envisage,
		'annee_etude' => $annee_etude,
		'status' => $status,
		'etude_option' => $etude_option,
		'annee_scolaire' => $annee_scolaire,
		'new_student' => $new_student,
		'father_name' => $father_name,
		'father_prof' => $father_prof,
		'mother_name' => $mother_name,
		'mother_prof' => $mother_prof,
		'parent_tel' => $parent_tel,
		'parent_adresse' => $parent_adresse,
		'sponsor_nom' => $sponsor_nom,
		'sponsor_prenom' => $sponsor_prenom,
		'sponsor_tel' => $sponsor_tel,
		'sponsor_adresse' => $sponsor_adresse,
		'situationf' => $situationf,
		'nom_conjoint' => $nom_conjoint,
		'nb_enfant' => $nb_enfant,
		'religion' => $religion,
		'num_visa' => $num_visa,
		'last_change_user_id' => $last_change_user_id,
		'date_entry' => $date_entry
	));

	header('location:../../src/inscription.php');
 ?>