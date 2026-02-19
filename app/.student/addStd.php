<?php 
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require ('../../data/backdb.php');

	$student_nom = trim($_POST['student_nom']);
	$student_prenom = trim($_POST['student_prenom']);
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
	$student_email = str_replace(' ', '', $_POST['student_email']);
	$pays_origine = $_POST['pays_origine'];
	$student_region = $_POST['student_region'];
	$student_adresse = $_POST['student_adresse'];
	

	$init_id = $_POST['student_id'];

	$verifyID = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id ="'.$init_id.'"');
	$showID = $verifyID->fetch();
	if (!empty($showId)) {
		$student_id = $init_id + 1;
	}else{
		$student_id = $init_id;
	}

	$mention = $_POST['etude_envisage'];
	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle ="'.$mention.'"');
	$showM = $findMention->fetch();
	$etude_envisage = $showM['filiere_description'];

	$graduated = 0;
	$abonment = $_POST['abonment'];
	$annee_etude = $_POST['annee_etude'];
	$status = $_POST['status'];
	$etude_option = $_POST['etude_option'];
	$semester = $_POST['semestre'];
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

	/*:::::::::::::::::::: VERIFICATION SESSION ::::::::::::::::::::*/

	$find_session = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester = "'.$semester.'" AND session_year = "'.$annee_scolaire.'" LIMIT 1');

	$showSession = $find_session->fetch();

	$session_id = $showSession['session_id'];

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
	$allowed_extensions = ['.jpg','.JPG','.png','.PNG','.jpeg','.JPEG'];
	$extension_image = strrchr($image,".");
	$image_dest = '../photosetudiants/';

	$dbimage = $student_id.'-'.$image;

	if(!empty($image) && !empty($image_tmp)){
		if(!in_array($extension_image, $allowed_extensions)){
			// Extension non autorisée — on saute l'image
			$dbimage = '';
		} else {
			$tempPath = $image_dest . 'tmp_' . $dbimage;
			move_uploaded_file($image_tmp, $tempPath);

			// Vérifier le vrai type MIME
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$realMime = $finfo->file($tempPath);
			$webMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

			if(in_array($realMime, $webMimes) && function_exists('imagecreatefromstring')){
				// Ré-encoder en JPEG propre
				$imgData = file_get_contents($tempPath);
				$gdImage = @imagecreatefromstring($imgData);
				if($gdImage){
					$dbimage = pathinfo($dbimage, PATHINFO_FILENAME) . '.jpg';
					$finalPath = $image_dest . $dbimage;
					imagejpeg($gdImage, $finalPath, 90);
					imagedestroy($gdImage);
					if($tempPath !== $finalPath) @unlink($tempPath);
				} else {
					rename($tempPath, $image_dest . $dbimage);
				}
			} elseif(!in_array($realMime, $webMimes)){
				// Format non-web — tenter conversion GD
				$converted = false;
				if(function_exists('imagecreatefromstring')){
					$imgData = file_get_contents($tempPath);
					$gdImage = @imagecreatefromstring($imgData);
					if($gdImage){
						$dbimage = pathinfo($dbimage, PATHINFO_FILENAME) . '.jpg';
						$finalPath = $image_dest . $dbimage;
						imagejpeg($gdImage, $finalPath, 90);
						imagedestroy($gdImage);
						if($tempPath !== $finalPath) @unlink($tempPath);
						$converted = true;
					}
				}
				if(!$converted){
					@unlink($tempPath);
					$dbimage = '';
				}
			} else {
				rename($tempPath, $image_dest . $dbimage);
			}
		}
	} else {
		$dbimage = '';
	}

	/*:::::::::::::::::::: FINANCE ::::::::::::::::::::*/

	$verification_finance_licence = $dtb->query('SELECT * FROM t_2024_finance_detail_licence WHERE std_status = "'.$status.'" AND std_mention = "'.$mention.'"');

		$result_finance = $verification_finance_licence->fetch();


	/*============== DETERMINATION OF DAYS NUMBER ==============*/
	
	if ($annee_etude == 1) {

		$nbr_day = $result_finance['nb_jours_semestre'];

		$frais_costume = $result_finance['frais_costume'];
	
	}elseif ($annee_etude == 2) {
	
		$nbr_day = $result_finance['nb_jours_semestre_L2'];

		$frais_costume = 0;
	
	}elseif ($annee_etude == 3) {
	
		$nbr_day = $result_finance['nb_jours_semestre_L3'];
		$frais_costume = 0;
	}

	/*============== FRAIS GENEREAUX ==============*/

	$cout_fraix_generaux = $result_finance['frais_generaux'];

	/*============== LOGMENT ==============*/

	$cout_logement = $result_finance['dortoir'] * $nbr_day;


	/*============== ABONEMENT CAF ==============*/

	if($abonment == 1) {

		$cout_abonment = $result_finance['cafeteria'] * $nbr_day;

	}else{
		
		$cout_abonment = 0;

	}


	/*============== FOND DE DEPOT ==============*/
	if ($annee_etude == 1 AND $new_student == 1) {

		$fond_depot = $result_finance['fond_depot'];

	}else{

		$fond_depot = 0;

	}
	


	/*============== FRAIS DE COSTUME ==============*/
	
	$frais_costume = $result_finance['frais_costume'];

	if ($semester == 1) {

		$frais_voyage = $result_finance['frais_voyage'];

	}else{

		$frais_voyage = 0;
		
	}

	/*:::::::::::::::::::: DIPLÔME PRECEDENT ::::::::::::::::::::*/

	if($annee_etude <= 3 ) {
	
		$obtention_bacc	= $_POST['obtention_bacc'];
		$serie_bacc= $_POST['serie_bacc'];

		$insertBacc = $dtb->prepare('INSERT INTO t_2024_bacc(
				student_id,
				date_obtent,
				bacc_serie,
				user_id,
				date_entry
			)VALUES(
				:student_id,
				:date_obtent,
				:bacc_serie,
				:user_id,
				:date_entry
			)');$insertBacc->execute(array(
				'student_id'  => $student_id,
				'date_obtent' => $obtention_bacc,
				'bacc_serie' => $serie_bacc,
				'user_id' => $last_change_user_id,
				'date_entry' => $date_entry
			));


	}elseif($annee_etude > 3 ) {

		$diplome_preced = $_POST['diplome_preced'];
		$date_obtent_diplome_preced = $_POST['date_obtent_diplome_preced'];

		$insertBacc = $dtb->prepare('INSERT INTO t_2024_diplome_preced(
				student_id,
				diplome_name,
				date_obtent,
				user_id,
				date_entry
			)VALUES(
				:student_id,
				:diplome_name,
				:date_obtent,
				:user_id,
				:date_entry
			)');$insertBacc->execute(array(
				'student_id' => $student_id,
				'diplome_name' => $diplome_preced,
				'date_obtent' => $date_obtent_diplome_preced,
				'user_id' => $last_change_user_id,
				'date_entry' => $date_entry
			));
	
	}

	/*:::::::::::::::::::: INFORMATION ::::::::::::::::::::*/

	$insertStd = $dtb->prepare('INSERT INTO tbl_2024_etudiant(
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
		abonment,
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
		:abonment,
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
		'abonment' => $abonment,
		'last_change_user_id' => $last_change_user_id,
		'date_entry' => $date_entry
	));



	$findId = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'" LIMIT 1');
	$showId = $findId->fetch();
	$id = $showId['id'];
	
	header('location:' . $app_base . '/student?id='.$id.'&page=information')
 ?>