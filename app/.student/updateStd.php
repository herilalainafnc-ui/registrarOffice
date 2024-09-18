<?php 

require '../../data/backdb.php';
	
	$last_change_user_id = $_GET['rg_id'];
	$id = $_GET['id'];
	$student_id = $_GET['student_id'];
	$student_nom = $_POST['student_nom'];
	$student_prenom = $_POST['student_prenom'];
	$etude_option = $_POST['etude_option'];
	$student_tel = $_POST['student_tel'];
	$sex = $_POST['sex'];
	$student_email = $_POST['student_email'];
	$annee_etude = $_POST['annee_etude'];
	$dateNaissance = $_POST['dateNaissance'];
	$nationalite = $_POST['nationalite'];
	$student_adresse = $_POST['student_adresse'];
	$student_region = $_POST['student_region'];
	$lieuNaissance = $_POST['lieuNaissance'];
	$num_cin = $_POST['num_cin'];
	
	echo $cin_datedelivre = $_POST['cin_date_delivre'];
	
	if (!empty($cin_datedelivre)) {

		$cin_date_delivre = "0000-00-00";

	}else{

		$cin_date_delivre = $cin_datedelivre;

	}

	$etude_envisage = $_GET['etude_envisage'];
	$father_name = $_POST['father_name'];
	$father_prof = $_POST['father_prof'];
	$parent_tel = $_POST['parent_tel'];
	$mother_name = $_POST['mother_name'];
	$mother_prof = $_POST['mother_prof'];
	$parent_adresse = $_POST['parent_adresse'];
	$sponsor_nom = $_POST['sponsor_nom'];
	$sponsor_prenom = $_POST['sponsor_prenom'];
	$sponsor_adresse = $_POST['sponsor_adresse'];
	$sponsor_tel = $_POST['sponsor_tel'];
	$annee_scolaire = $_POST['annee_scolaire'];
	$new_student = $_POST['new_student'];
	$status = $_POST['status'];
	$cin_region = $_POST['cin_region'];
	$religion = $_POST['religion'];
	$graduated = $_POST['graduated'];
	
	$situationf = $_POST['situationf'];
	$nb_enfant = $_POST['nb_enfant'];
	$nom_conjoint = $_POST['nom_conjoint'];

	$num_visa = $_POST['num_visa'];
	$abonment = $_POST['abonment'];
	
	$last_change_datetime = date('Y-m-d');

/*========================================== UPDATE STUDENT =====================================*/

	$updateStudent = $dtb->prepare("UPDATE tbl_2024_etudiant SET 
		student_nom=:student_nom,
		student_prenom=:student_prenom,
		etude_option=:etude_option,
		student_tel=:student_tel,
		sex=:sex, 
		student_email=:student_email, 
		dateNaissance=:dateNaissance, 
		nationalite=:nationalite, 
		student_adresse=:student_adresse,
		student_region=:student_region,
		lieuNaissance=:lieuNaissance,
		num_cin=:num_cin,
		cin_date_delivre=:cin_date_delivre,
		father_name=:father_name,
		father_prof=:father_prof,
		parent_tel=:parent_tel,
		mother_name=:mother_name,
		mother_prof=:mother_prof,
		parent_adresse=:parent_adresse,
		sponsor_nom=:sponsor_nom,
		sponsor_prenom=:sponsor_prenom,
		sponsor_adresse=:sponsor_adresse,
		sponsor_tel=:sponsor_tel,
		annee_scolaire=:annee_scolaire,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime,
		status=:status,
		graduated=:graduated,
		situationf=:situationf,
		nb_enfant=:nb_enfant,
		nom_conjoint=:nom_conjoint,
		abonment=:abonment,
		new_student=:new_student,
		cin_region=:cin_region,
		religion=:religion,
		num_visa=:num_visa,
		annee_etude=:annee_etude

		WHERE id=:id");

	$updateStudent->bindParam(':student_nom',$student_nom,PDO::PARAM_STR);
	$updateStudent->bindParam(':student_prenom',$student_prenom,PDO::PARAM_STR);
	$updateStudent->bindParam(':etude_option',$etude_option,PDO::PARAM_STR);
	$updateStudent->bindParam(':student_tel',$student_tel,PDO::PARAM_STR);
	$updateStudent->bindParam(':sex',$sex,PDO::PARAM_STR);
	$updateStudent->bindParam(':student_email',$student_email,PDO::PARAM_STR);
	$updateStudent->bindParam(':dateNaissance',$dateNaissance,PDO::PARAM_STR);
	$updateStudent->bindParam(':nationalite',$nationalite,PDO::PARAM_STR);
	$updateStudent->bindParam(':student_adresse',$student_adresse,PDO::PARAM_STR);
	$updateStudent->bindParam(':student_region',$student_region,PDO::PARAM_STR);
	$updateStudent->bindParam(':lieuNaissance',$lieuNaissance,PDO::PARAM_STR);
	$updateStudent->bindParam(':num_cin',$num_cin,PDO::PARAM_STR);
	$updateStudent->bindParam(':cin_date_delivre',$cin_date_delivre,PDO::PARAM_STR);
	$updateStudent->bindParam(':father_name',$father_name,PDO::PARAM_STR);
	$updateStudent->bindParam(':father_prof',$father_prof,PDO::PARAM_STR);
	$updateStudent->bindParam(':parent_tel',$parent_tel,PDO::PARAM_STR);
	$updateStudent->bindParam(':mother_name',$mother_name,PDO::PARAM_STR);
	$updateStudent->bindParam(':mother_prof',$mother_prof,PDO::PARAM_STR);
	$updateStudent->bindParam(':parent_adresse',$parent_adresse,PDO::PARAM_STR);
	$updateStudent->bindParam(':sponsor_nom',$sponsor_nom,PDO::PARAM_STR);
	$updateStudent->bindParam(':sponsor_prenom',$sponsor_prenom,PDO::PARAM_STR);
	$updateStudent->bindParam(':sponsor_adresse',$sponsor_adresse,PDO::PARAM_STR);
	$updateStudent->bindParam(':sponsor_tel',$sponsor_tel,PDO::PARAM_STR);
	$updateStudent->bindParam(':annee_scolaire',$annee_scolaire,PDO::PARAM_STR);
	$updateStudent->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updateStudent->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$updateStudent->bindParam(':status',$status,PDO::PARAM_STR);
	$updateStudent->bindParam(':graduated',$graduated,PDO::PARAM_STR);
	$updateStudent->bindParam(':situationf',$situationf,PDO::PARAM_STR);
	$updateStudent->bindParam(':nb_enfant',$nb_enfant,PDO::PARAM_INT);
	$updateStudent->bindParam(':nom_conjoint',$nom_conjoint,PDO::PARAM_STR);
	$updateStudent->bindParam(':abonment',$abonment,PDO::PARAM_INT);
	$updateStudent->bindParam(':new_student',$new_student,PDO::PARAM_STR);
	$updateStudent->bindParam(':cin_region',$cin_region,PDO::PARAM_STR);
	$updateStudent->bindParam(':religion',$religion,PDO::PARAM_STR);
	$updateStudent->bindParam(':num_visa',$num_visa,PDO::PARAM_STR);
	$updateStudent->bindParam(':annee_etude',$annee_etude,PDO::PARAM_INT);
	$updateStudent->bindParam(':id',$id,PDO::PARAM_INT);

	$updateStudent->execute();
	

/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/


	if(isset($_POST['serie_bacc']) OR isset($_POST['obtention_bacc'])) {

		$serie_bacc = $_POST['serie_bacc'];
		$obtention_bacc = $_POST['obtention_bacc'];

		$searchBacc = $dtb->query('SELECT * FROM t_2024_bacc WHERE student_id = "'.$student_id.'"');
		$trouveBacc = $searchBacc->fetch();
		
		if (!empty($trouveBacc)) {
			
			$updDiplome = $dtb->prepare("UPDATE t_2024_bacc SET 
				date_obtent=:date_obtent,
				bacc_serie=:bacc_serie,
				user_id=:user_id,
				date_entry=:date_entry
				WHERE student_id=:student_id");
	
			$updDiplome->bindParam(':date_obtent',$obtention_bacc,PDO::PARAM_STR);
			$updDiplome->bindParam(':bacc_serie',$serie_bacc,PDO::PARAM_STR);
			$updDiplome->bindParam(':user_id',$last_change_user_id,PDO::PARAM_STR);
			$updDiplome->bindParam(':date_entry',$last_change_datetime,PDO::PARAM_STR);
			$updDiplome->bindParam(':student_id',$student_id,PDO::PARAM_STR);

			$updDiplome->execute();

		}else{
			
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
				'date_entry' => $last_change_datetime
			));
		}
	}
	
	if(isset($_POST['diplome_preced']) OR isset($_POST['date_obtent_diplome_preced'])) {

		$diplome_preced = $_POST['diplome_preced'];
		$date_obtent_diplome_preced = $_POST['date_obtent_diplome_preced'];

		$searchDiplome = $dtb->query('SELECT * FROM t_2024_diplome_preced WHERE student_id = "'.$student_id.'"');
		$trouveDiplome = $searchDiplome->fetch();
		
		if (!empty($trouveDiplome)) {
			
			$updDiplomePreced = $dtb->prepare("UPDATE t_2024_diplome_preced SET 
				diplome_name=:diplome_name,
				date_obtent=:date_obtent,
				user_id=:user_id,
				date_entry=:date_entry
				WHERE student_id=:student_id");
	
			$updDiplomePreced->bindParam(':diplome_name',$diplome_preced,PDO::PARAM_STR);
			$updDiplomePreced->bindParam(':date_obtent',$date_obtent_diplome_preced,PDO::PARAM_STR);
			$updDiplomePreced->bindParam(':user_id',$last_change_user_id,PDO::PARAM_STR);
			$updDiplomePreced->bindParam(':date_entry',$last_change_datetime,PDO::PARAM_STR);
			$updDiplomePreced->bindParam(':student_id',$student_id,PDO::PARAM_STR);
			$updDiplomePreced->execute();

		}else{
			
			$insertDiplome = $dtb->prepare('INSERT INTO t_2024_diplome_preced(
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
			)');$insertDiplome->execute(array(
				'student_id' => $student_id,
				'diplome_name'  => $diplome_preced,
				'date_obtent' => $date_obtent_diplome_preced,
				'user_id' => $last_change_user_id,
				'date_entry' => $last_change_datetime
			));
		}
	}

 header('location:../../src/student.php?id='.$id.'&page=information');

?>