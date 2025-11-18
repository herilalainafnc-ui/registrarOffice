<?php

/* provisoire */
require('../../data/backdb.php');

$semestre = "Deuxième semestre";
$annee_scolaire = "2024 - 2025";
$last_change_datetime = date('Y-m-d');

$findSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_name = "'.$semestre.'" AND session_year = "'.$annee_scolaire.'"');
$showSession = $findSession->fetch();

$session_id = $showSession['session_id'];
$semesterForInformation = $showSession['session_semester'];

$findInAddCours = $dtb->query('SELECT DISTINCT student_id FROM t_2023_notes WHERE session_id = "'.$session_id.'" AND yearlevel <=3');

$nb = 1;
while($showInAddCours = $findInAddCours->fetch()) {
	echo "<br>".$nb;
	echo " - ".$student_id = $showInAddCours['student_id'];

	$searchIfExist = $dtb->query('SELECT * FROM t_2024_inscription_session WHERE student_id = "'.$student_id.'" AND session_id= "'.$session_id.'"');

	$left = $searchIfExist->fetch();
	
	if (empty($left)) {
		$findInStudent = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');
		$profil = $findInStudent->fetch();

		$etude_envisage = $profil['etude_envisage'];

		$findInfiliere = $dtb->query('SELECT * FROM filiere WHERE filiere_description = "'.$etude_envisage.'"');
		$showInfiliere = $findInfiliere->fetch();
		$etude_envisage_sign = $showInfiliere['filiere_sigle'];

		if ($profil['status'] == "") {
			$status = "Externe";
		}else{
			$status = $profil['status'];
		}
		
		$new_student = $profil['new_student'];
		
		if($profil['graduated'] == "") {
			$graduated = 0;
		}else{
			$graduated = $profil['graduated'];
		}
		
		$student_adresse = $profil['student_adresse'];
		$etude_option = $profil['etude_option'];
		$annee_etude = $profil['annee_etude'];
		$sponsor_nom = $profil['sponsor_nom'];
		$sponsor_prenom = $profil['sponsor_prenom'];
		$sponsor_tel = $profil['sponsor_tel'];
		$sponsor_adresse = $profil['sponsor_adresse'];
		$situationf = $profil['situationf'];
		
		if ($profil['nom_conjoint']=="") {
			$nom_conjoint = "";
		}else{
			$nom_conjoint = $profil['nom_conjoint'];
		}
		if ($profil['nb_enfant']=="") {
			$nb_enfant = 0;
		}else{
			$nb_enfant = $profil['nb_enfant'];
		}
		if ($profil['abonment']=="") {
			$abonment = 0;
		}else{
			$abonment = $profil['abonment'];
		}
		$annee_scolaire = $profil['annee_scolaire'];

		/*--------------------------------------------------------------------------------*/

		$insertSession = $dtb->prepare('INSERT INTO t_2024_inscription_session (
			student_id,
			etude_mention,
			status,
			new_student,
			graduated,
			session_id,
			nbr_semester,
			adresse_actuel_std,
			parcours_std,
			niveau_std,
			sponsor_name,
			sponsor_lastName,
			sponsor_contact,
			sponsor_address,
			etat_civil_std,
			conjoint_name,
			nb_enfant,
			abonment_std,
			annee_scolaire,
			date_entry
		) VALUES (
			:student_id,
			:etude_mention,
			:status,
			:new_student,
			:graduated,
			:session_id,
			:nbr_semester,
			:adresse_actuel_std,
			:parcours_std,
			:niveau_std,
			:sponsor_name,
			:sponsor_lastName,
			:sponsor_contact,
			:sponsor_address,
			:etat_civil_std,
			:conjoint_name,
			:nb_enfant,
			:abonment_std,
			:annee_scolaire,
			:date_entry
		)');
		$insertSession->execute(array(
			'student_id' => $student_id,
			'etude_mention' => $etude_envisage_sign,
			'status' => $status,
			'new_student' => $new_student,
			'graduated' => $graduated,
			'session_id' => $session_id,
			'nbr_semester' => $semesterForInformation,
			'adresse_actuel_std' => $student_adresse,
			'parcours_std' => $etude_option,
			'niveau_std' => $annee_etude,
			'sponsor_name' => $sponsor_nom,
			'sponsor_lastName' => $sponsor_prenom,
			'sponsor_contact' => $sponsor_tel,
			'sponsor_address' => $sponsor_adresse,
			'etat_civil_std' => $situationf,
			'conjoint_name' => $nom_conjoint,
			'nb_enfant' => $nb_enfant,
			'abonment_std' => $abonment,
			'annee_scolaire' => $annee_scolaire,
			'date_entry' => $last_change_datetime
		));
		echo " - Ajout effectué !!!";
	}else{
		echo " - Donnée vérifié !";
	}
	$nb++;
}

?>