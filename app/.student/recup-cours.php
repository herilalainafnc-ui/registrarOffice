<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(3); refuserEtudiant(); refuserEnseignant(); // cours de l'étudiant : niveau 3 et moins, comme del-cours.momentanee
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require ('../../data/backdb.php');
require_once(__DIR__ . '/../../data/inscription.frais.php'); // inscriptionCoursFinanceSuivre : la facturation du cours suit

// Fuseau horaire Madagascar (UTC+3)
date_default_timezone_set('Indian/Antananarivo');

$student_id = $_GET['student_id'];
$id = $_GET['id'];
$as = $_GET['as'];
$idSupprCours = $_GET['idSupprCours'];
$ajout = 1;
$remove = 0;
$retrait_date = date('Y-m-d H:i:s');
$last_change_datetime = date('Y-m-d H:i:s');
$last_change_user_id = (int) ($_SESSION['user_id'] ?? 0); // auteur : le compte connecte, pas l'adresse
$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

	// Récupérer les informations actuelles de la note pour l'historique
	$getCurrentNote = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :note_id');
	$getCurrentNote->execute(array('note_id' => $idSupprCours));
	$currentNote = $getCurrentNote->fetch();

	// Récupérer l'id du cours depuis la note
	$id_cours = $currentNote ? $currentNote['id_cours'] : null;

	// Enregistrer dans l'historique des modifications de notes (restauration)
	if ($currentNote) {
		$insertHistory = $dtb->prepare('INSERT INTO t_notes_modification_history (
			note_id,
			student_id,
			session_id,
			cours_sigle,
			cours_titre,
			old_grade,
			new_grade,
			action_type,
			action_by,
			action_date,
			ip_address,
			commentaire
		) VALUES (
			:note_id,
			:student_id,
			:session_id,
			:cours_sigle,
			:cours_titre,
			:old_grade,
			:new_grade,
			:action_type,
			:action_by,
			:action_date,
			:ip_address,
			:commentaire
		)');

		$insertHistory->execute(array(
			'note_id' => $idSupprCours,
			'student_id' => $currentNote['student_id'],
			'session_id' => $currentNote['session_id'],
			'cours_sigle' => $currentNote['Sigle'],
			'cours_titre' => $currentNote['title_cours'],
			'old_grade' => $currentNote['grade'],
			'new_grade' => $currentNote['grade'],
			'action_type' => 'restauration',
			'action_by' => $last_change_user_id,
			'action_date' => $last_change_datetime,
			'ip_address' => $ip_address,
			'commentaire' => 'Cours restauré dans la liste'
		));
	}

	// La note et la facturation du cours changent ensemble
	$dtb->beginTransaction();
	$updatenote = $dtb->prepare("UPDATE t_2023_notes SET 
		retrait_date=:retrait_date,
		ajout=:ajout,
		remove=:remove,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime 
		WHERE id=:id");
	
	$updatenote->bindParam(':retrait_date',$retrait_date,PDO::PARAM_STR);
	$updatenote->bindParam(':ajout',$ajout,PDO::PARAM_INT);
	$updatenote->bindParam(':remove',$remove,PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$updatenote->bindParam(':id',$idSupprCours,PDO::PARAM_INT);

	$updatenote->execute();

	/* La facturation du cours revient : la ligne de CET etudiant pour CETTE session, et
	   les totaux de cette session recalcules depuis les lignes. Avant le 20/10/2026, la
	   ligne du cours etait remise chez TOUS les etudiants (WHERE cours_id seul) et son
	   cout ajoute aux totaux de toutes les sessions de l'etudiant. */
	if ($currentNote) {
		inscriptionCoursFinanceSuivre($dtb, $currentNote, 'remettre', $last_change_user_id);
	}
	$dtb->commit();

header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS#semestre'.$as);

 ?>