<?php 

// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

// Désactiver l'affichage des erreurs pour les requêtes AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($isAjax) {
	error_reporting(0);
	ini_set('display_errors', 0);
}

// Vérification d'autorisation : seuls superadmin (1) et registraire (3)
if (session_status() === PHP_SESSION_NONE) { ini_set('session.gc_maxlifetime', 36000); ini_set('session.cookie_lifetime', 36000); session_start(); }
$currentUserLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
if (!in_array($currentUserLevel, [1, 3], true)) {
	if ($isAjax) {
		http_response_code(403);
		echo 'Accès refusé. Seuls le superadmin et le registraire peuvent modifier les informations de l\'étudiant.';
		exit;
	} else {
		$id = $_GET['id'] ?? 0;
		header('location:' . $app_base . '/student?id='.$id.'&page=information&error=access_denied');
		exit;
	}
}

require '../../data/backdb.php';
require 'student_history_helper.php';
	
	$last_change_user_id = $_GET['rg_id'] ?? '';
	$id = $_GET['id'] ?? '';
	$student_id = $_GET['student_id'] ?? '';
	$student_nom = $_POST['student_nom'] ?? '';
	$student_prenom = $_POST['student_prenom'] ?? '';
	$etude_option = $_POST['etude_option'] ?? '';
	$student_tel = $_POST['student_tel'] ?? '';
	$sex = $_POST['sex'] ?? '';
	$student_email = $_POST['student_email'] ?? '';
	$annee_etude = $_POST['annee_etude'] ?? '';
	$dateNaissance = $_POST['dateNaissance'] ?? '';
	$nationalite = $_POST['nationalite'] ?? '';
	$student_adresse = $_POST['student_adresse'] ?? '';
	$student_region = $_POST['student_region'] ?? '';
	$lieuNaissance = $_POST['lieuNaissance'] ?? '';
	$num_cin = $_POST['num_cin'] ?? '';
	
	$cin_datedelivre = $_POST['cin_date_delivre'] ?? '';
	
	if (!empty($cin_datedelivre)) {

		$cin_date_delivre = "0000-00-00";

	}else{

		$cin_date_delivre = $cin_datedelivre;

	}

	$etude_envisage = $_GET['etude_envisage'];

// Récupérer les anciennes données pour l'historique
$getOldData = $dtb->prepare("SELECT * FROM tbl_2024_etudiant WHERE id = :id");
$getOldData->execute(['id' => $id]);
$oldStudentData = $getOldData->fetch(PDO::FETCH_ASSOC);

$findInfiliere = $dtb->prepare('SELECT * FROM filiere WHERE filiere_description = :etude_envisage');
$findInfiliere->execute(['etude_envisage' => $etude_envisage]);
$showInfiliere = $findInfiliere->fetch();
$etude_envisage_sign = $showInfiliere['filiere_sigle'] ?? '';


	$father_name = $_POST['father_name'] ?? '';
	$father_prof = $_POST['father_prof'] ?? '';
	$parent_tel = $_POST['parent_tel'] ?? '';
	$mother_name = $_POST['mother_name'] ?? '';
	$mother_prof = $_POST['mother_prof'] ?? '';
	$parent_adresse = $_POST['parent_adresse'] ?? '';
	$sponsor_nom = $_POST['sponsor_nom'] ?? '';
	$sponsor_prenom = $_POST['sponsor_prenom'] ?? '';
	$sponsor_adresse = $_POST['sponsor_adresse'] ?? '';
	$sponsor_tel = $_POST['sponsor_tel'] ?? '';
	$annee_scolaire = $_POST['annee_scolaire'] ?? '';
	$new_student = $_POST['new_student'] ?? '';
	$status = $_POST['status'] ?? '';
	$cin_region = $_POST['cin_region'] ?? '';
	$religion = $_POST['religion'] ?? '';
	$graduated = $_POST['graduated'] ?? '';
	
	$situationf = $_POST['situationf'] ?? '';
	$nb_enfant = $_POST['nb_enfant'] ?? '';
	$nom_conjoint = $_POST['nom_conjoint'] ?? '';

	$num_visa = $_POST['num_visa'] ?? '';
	$abonment = $_POST['abonment'] ?? '';
	
	$last_change_datetime = date('Y-m-d');

	$verificationOldStatus = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id');
	$verificationOldStatus->execute(['student_id' => $student_id]);
 	$oldStatusConfirmed = $verificationOldStatus->fetch();
 	$oldStatus = $oldStatusConfirmed['status'] ?? '';

 	
/*========================================== UPDATE STUDENT ON SESSION ==========================*/

$findLatestStudentSession = $dtb->prepare(
	'SELECT ins.session_id, COALESCE(sess.session_semester, ins.nbr_semester) AS session_semester
	 FROM t_2024_inscription_session ins
	 LEFT JOIN t_2023_session sess ON sess.session_id = ins.session_id
	 WHERE ins.student_id = :student_id
	 ORDER BY COALESCE(sess.date_entry, ins.date_entry, "0000-00-00") DESC, ins.id DESC
	 LIMIT 1'
);
$findLatestStudentSession->execute(['student_id' => $student_id]);
$latestSessionRow = $findLatestStudentSession->fetch(PDO::FETCH_ASSOC);

$session_id_for_modification = (int)($latestSessionRow['session_id'] ?? 0);
$nbr_semester = $latestSessionRow['session_semester'] ?? '';

if ($session_id_for_modification <= 0) {
	$message = 'Aucune inscription session existante pour cet étudiant. Mise à jour refusée pour éviter une création automatique.';
	if ($isAjax) {
		http_response_code(422);
		echo $message;
		exit;
	}
	header('location:' . $app_base . '/student?id='.$id.'&page=information&error=no_student_session');
	exit;
}

$verification = $dtb->prepare('SELECT * FROM t_2024_inscription_session WHERE student_id = :student_id AND session_id = :session_id');
$verification->execute(['student_id' => $student_id, 'session_id' => $session_id_for_modification]);

$answering = $verification->fetch();

if (!empty($answering)) {
	$idForSession = $answering['id'];
	$updateSession = $dtb->prepare("UPDATE t_2024_inscription_session SET 
		
		status=:status,
		new_student=:new_student,
		graduated=:graduated,
		nbr_semester=:nbr_semester,
		adresse_actuel_std=:adresse_actuel_std,
		parcours_std=:parcours_std,
		niveau_std=:niveau_std,
		sponsor_name=:sponsor_name,
		sponsor_lastName=:sponsor_lastName,
		sponsor_contact=:sponsor_contact,
		sponsor_address=:sponsor_address,
		etat_civil_std=:etat_civil_std,
		conjoint_name=:conjoint_name,
		nb_enfant=:nb_enfant,
		abonment_std=:abonment_std,
		annee_scolaire=:annee_scolaire,
		date_entry=:date_entry

	WHERE id=:idForSession");

	$updateSession->bindParam(':status',$status,PDO::PARAM_STR);
	$updateSession->bindParam(':new_student',$new_student,PDO::PARAM_INT);
	$updateSession->bindParam(':graduated',$graduated,PDO::PARAM_INT);
	$updateSession->bindParam(':nbr_semester',$nbr_semester,PDO::PARAM_STR);
	$updateSession->bindParam(':adresse_actuel_std',$student_adresse,PDO::PARAM_STR);
	$updateSession->bindParam(':parcours_std',$etude_option,PDO::PARAM_STR);
	$updateSession->bindParam(':niveau_std',$annee_etude,PDO::PARAM_STR);
	$updateSession->bindParam(':sponsor_name',$sponsor_nom,PDO::PARAM_STR);
	$updateSession->bindParam(':sponsor_lastName',$sponsor_prenom,PDO::PARAM_STR);
	$updateSession->bindParam(':sponsor_contact',$sponsor_tel,PDO::PARAM_STR);
	$updateSession->bindParam(':sponsor_address',$sponsor_adresse,PDO::PARAM_STR);
	$updateSession->bindParam(':etat_civil_std',$situationf,PDO::PARAM_STR);
	$updateSession->bindParam(':conjoint_name',$nom_conjoint,PDO::PARAM_STR);
	$updateSession->bindParam(':nb_enfant',$nb_enfant,PDO::PARAM_INT);
	$updateSession->bindParam(':abonment_std',$abonment,PDO::PARAM_INT);
	$updateSession->bindParam(':annee_scolaire',$annee_scolaire,PDO::PARAM_STR);
	$updateSession->bindParam(':date_entry',$last_change_datetime,PDO::PARAM_STR);
	$updateSession->bindParam(':idForSession',$idForSession,PDO::PARAM_INT);

	$updateSession->execute();

}else{
	$message = 'Incohérence détectée: aucune inscription trouvée pour la session récente de cet étudiant. Aucune création automatique n\'a été faite.';
	if ($isAjax) {
		http_response_code(422);
		echo $message;
		exit;
	}
	header('location:' . $app_base . '/student?id='.$id.'&page=information&error=student_not_in_session');
	exit;
}

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

	// ========== ENREGISTREMENT DE L'HISTORIQUE DES MODIFICATIONS ==========
	$newStudentData = [
		'student_nom' => $student_nom,
		'student_prenom' => $student_prenom,
		'etude_option' => $etude_option,
		'student_tel' => $student_tel,
		'sex' => $sex,
		'student_email' => $student_email,
		'annee_etude' => $annee_etude,
		'dateNaissance' => $dateNaissance,
		'nationalite' => $nationalite,
		'student_adresse' => $student_adresse,
		'student_region' => $student_region,
		'lieuNaissance' => $lieuNaissance,
		'num_cin' => $num_cin,
		'cin_date_delivre' => $cin_date_delivre,
		'father_name' => $father_name,
		'father_prof' => $father_prof,
		'parent_tel' => $parent_tel,
		'mother_name' => $mother_name,
		'mother_prof' => $mother_prof,
		'parent_adresse' => $parent_adresse,
		'sponsor_nom' => $sponsor_nom,
		'sponsor_prenom' => $sponsor_prenom,
		'sponsor_adresse' => $sponsor_adresse,
		'sponsor_tel' => $sponsor_tel,
		'annee_scolaire' => $annee_scolaire,
		'status' => $status,
		'graduated' => $graduated,
		'situationf' => $situationf,
		'nb_enfant' => $nb_enfant,
		'nom_conjoint' => $nom_conjoint,
		'abonment' => $abonment,
		'new_student' => $new_student,
		'cin_region' => $cin_region,
		'religion' => $religion,
		'num_visa' => $num_visa
	];
	
	// Enregistrer toutes les modifications
	logStudentModifications($dtb, $oldStudentData, $newStudentData, $student_id, $id, $last_change_user_id, 'modification');
	// ========== FIN HISTORIQUE ==========
	

/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/


	if(isset($_POST['serie_bacc']) OR isset($_POST['obtention_bacc'])) {

		$serie_bacc = $_POST['serie_bacc'];
		$obtention_bacc = $_POST['obtention_bacc'];

		$searchBacc = $dtb->prepare('SELECT * FROM t_2024_bacc WHERE student_id = :student_id');
		$searchBacc->execute(['student_id' => $student_id]);
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

		$searchDiplome = $dtb->prepare('SELECT * FROM t_2024_diplome_preced WHERE student_id = :student_id');
		$searchDiplome->execute(['student_id' => $student_id]);
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


	$verification_finance = $dtb->prepare(
		'SELECT * FROM t_2024_finance_detail_licence 
		 WHERE std_status = :status AND std_mention = :mention AND level = :level AND semester = :semester LIMIT 1'
	);
	$verification_finance->execute(['status' => $status, 'mention' => $etude_envisage_sign, 'level' => $annee_etude, 'semester' => $nbr_semester]);

	$verif_Fnc = $verification_finance->fetch();

	$frais_generaux = $verif_Fnc['frais_generaux'];
	$logement = $verif_Fnc['dortoir'] * $verif_Fnc['nb_jours_semestre'];
	


 	if ($oldStatus == 'Interne') {
 		$fond_depot = $verif_Fnc['fond_depot'];	
 	}else{
 		$fond_depot = 0;	
 	}
	
	
	if ($abonment == 1) {
		$cafeteria = $verif_Fnc['cafeteria'] * $verif_Fnc['nb_jours_semestre'];
	}else{
		$cafeteria = 0;
	}
	
	$frais_graduation = $verif_Fnc['frais_graduation'];
	$costume = $verif_Fnc['frais_costume'];




	$updateFnc = $dtb->prepare('UPDATE t_2024_etudiant_finace SET 
		level=:level,
		status=:status,
		cout_fraix_generaux=:cout_fraix_generaux,
		cout_logement=:cout_logement,
		cout_fondDepot_dortoir=:cout_fondDepot_dortoir,
		cout_abonment=:cout_abonment,
		cout_frais_graduation=:cout_frais_graduation,
		cout_costume=:cout_costume

		WHERE student_id =:student_id AND session_id=:session_id');
		$updateFnc->bindParam(':level',$annee_etude,PDO::PARAM_INT);
		$updateFnc->bindParam(':status',$status,PDO::PARAM_STR);
		$updateFnc->bindParam(':cout_fraix_generaux',$frais_generaux,PDO::PARAM_STR);
		$updateFnc->bindParam(':cout_logement',$logement,PDO::PARAM_STR);
		$updateFnc->bindParam(':cout_fondDepot_dortoir',$fond_depot,PDO::PARAM_STR);
		$updateFnc->bindParam(':cout_abonment',$cafeteria,PDO::PARAM_STR);
		$updateFnc->bindParam(':cout_frais_graduation',$frais_graduation,PDO::PARAM_STR);
		$updateFnc->bindParam(':cout_costume',$costume,PDO::PARAM_STR);
		$updateFnc->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$updateFnc->bindParam(':session_id',$session_id_for_modification,PDO::PARAM_INT);
		$updateFnc->execute();


// Check if this is an AJAX request
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
				|| (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
				|| !empty($_POST);

if ($isAjaxRequest && !headers_sent()) {
	echo 'OK';
	exit;
}

header('location:' . $app_base . '/student?id='.$id.'&page=information');

?>