<?php 
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require ('../../data/backdb.php');

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
$last_change_user_id = $_GET['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

	// Récupérer les informations actuelles de la note pour l'historique
	$getCurrentNote = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :note_id');
	$getCurrentNote->execute(array('note_id' => $idSupprCours));
	$currentNote = $getCurrentNote->fetch();

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

	$updatCoursFinance = $dtb->prepare("UPDATE t_2024_cours_finance SET 
		remove=:remove,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime
	
	WHERE cours_id=:id");
	
	$updatCoursFinance->bindParam(':remove',$remove,PDO::PARAM_INT);
	$updatCoursFinance->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updatCoursFinance->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$updatCoursFinance->bindParam(':id',$id_cours,PDO::PARAM_INT);

	$updatCoursFinance->execute();


	$findCoutFromNote = $dtb->query("SELECT * FROM t_2023_cours WHERE id='".$id_cours."'");
	$showCFromNote = $findCoutFromNote->fetch();
	
		$vCout = $showCFromNote['cout'];
		$vCout_lab = $showCFromNote['cout_lab'];


	$findCoutFromFinance = $dtb->query("SELECT * FROM t_2024_etudiant_finace WHERE student_id='".$student_id."'");
	$showCFromFinance = $findCoutFromFinance->fetch();
	
		$fCout = $showCFromFinance['cout_totalCours'];
		$fCout_lab = $showCFromFinance['cout_totalLab'];

		$restCout = $fCout + $vCout;
		$restCoutLab = $fCout_lab + $vCout_lab;

		$updateReste = $dtb->prepare("UPDATE t_2024_etudiant_finace SET cout_totalCours=:cout_totalCours,cout_totalLab=:cout_totalLab WHERE student_id=:student_id");
		$updateReste->bindParam(':cout_totalCours',$restCout,PDO::PARAM_STR);
		$updateReste->bindParam(':cout_totalLab',$restCoutLab,PDO::PARAM_STR);
		$updateReste->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$updateReste->execute();

header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS#semestre'.$as);

 ?>