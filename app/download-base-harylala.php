<?php 
	
	require('../data/backdb.php');

	$date_entry = date('Y-m-d');
	$annee_scolaire = '2025 - 2026';
	$new_student = 1;
	$data_completion = 1;
	$session_id = 486;
	$nbr_semester = 1;

	$lalaStudent = $dtb->query('SELECT * FROM students11 WHERE id = 1935 AND account_code IS not null');

	$nb = 1;
	while($showlalaStd = $lalaStudent->fetch()) {

		$student_nom = strtoupper($showlalaStd['nom']);
		$student_prenom = $showlalaStd['prenom'];
		$dateNaissance = $showlalaStd['date_naissance'];
		$lieuNaissance = $showlalaStd['lieu_naissance'];
		$num_cin = $showlalaStd['cin_numero'];
		$cin_date_delivre = $showlalaStd['cin_date_delivrance'];
		$cin_region = $showlalaStd['cin_lieu_delivrance'];
		$sex = $showlalaStd['sexe'];
		$nationalite = $showlalaStd['nationalite'];
		$student_tel = $showlalaStd['telephone'];
		$student_email = $showlalaStd['email'];
		$password = $showlalaStd['plain_password'];	
		$lookup_code = $showlalaStd['account_code'];	
		$student_region = $showlalaStd['region'];
		$student_adresse = $showlalaStd['adresse'];
		$dbimage = $showlalaStd['image'];

		$student_id = $showlalaStd['matricule'];
		
			$mention = $dtb->query('SELECT * FROM filiere WHERE filiere_id = "'.$showlalaStd['mention_id'].'"');

			$mt = $mention->fetch();

			$etude_envisage = $mt['filiere_description'];
			$etude_sigle = $mt['filiere_sigle'];

		$annee_etude = 1;

		$status = ucfirst($showlalaStd['statut_interne']);

			$parcours = $dtb->query('SELECT * FROM parcours WHERE id = "'.$showlalaStd['parcours_id'].'"');

			$prc = $parcours->fetch();

			if ($prc== null) {
				$etude_option = '';	
			}else{
				$etude_option = $prc['nom'];
			}

		
		$father_name = $showlalaStd['nom_pere'];
		$father_prof = $showlalaStd['profession_pere'];
		$mother_name = $showlalaStd['nom_mere'];
		$mother_prof = $showlalaStd['profession_mere'];
		$parent_tel = $showlalaStd['contact_mere'];
		$parent_adresse = $showlalaStd['adresse_parents'];
		$sponsor_nom = $showlalaStd['sponsor_nom'];
		$sponsor_prenom = $showlalaStd['sponsor_prenom'];
		$sponsor_tel = $showlalaStd['sponsor_telephone'];
		$sponsor_adresse = $showlalaStd['sponsor_adresse'];
		$religion = $showlalaStd['religion'];
		$abonment = $showlalaStd['created_at'];

		/* FOR TABLE tbl_2024_etudiant */

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
		religion,
		abonment,
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
		:religion,
		:abonment,
		:date_entry

	)');

	$insertStd->execute(array(
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
		'religion' => $religion,
		'abonment' => $abonment,
		'date_entry' => $date_entry
	));

	/* FOR TABLE t_2024_inscription_session */

	$creatLineStdToSession = $dtb->prepare('INSERT INTO t_2024_inscription_session (
		student_id,
		etude_mention,
		status,
		new_student,
		data_completion,
		session_id,
		nbr_semester,
		annee_scolaire,
		date_entry
	) VALUES (
		:student_id,
		:etude_mention,
		:status,
		:new_student,
		:data_completion,
		:session_id,
		:nbr_semester,
		:annee_scolaire,
		:date_entry
	)');
	
	$creatLineStdToSession->execute(array(
		'student_id' => $student_id,
		'etude_mention' => $etude_sigle,
		'status' => $status,
		'new_student' => $new_student,
		'data_completion' => $data_completion,
		'session_id' => $session_id,
		'nbr_semester' => $nbr_semester,
		'annee_scolaire' => $annee_scolaire,
		'date_entry' => $date_entry
	));

		$nb++;
	}

header('location:../src/');

 ?>