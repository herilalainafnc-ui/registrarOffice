<?php 
	require('../../data/backdb.php');

	// Fuseau horaire Madagascar (UTC+3)
	date_default_timezone_set('Indian/Antananarivo');

	$id = $_GET['id'];
	$as = $_GET['as'];
	$nbr = $_GET['nbr'];
	$note_id = $_GET['note_id'];
	 

	if ($_POST['nb_crd'.$nbr] == "ok" OR $_POST['nb_crd'.$nbr] == "Ok" OR  $_POST['nb_crd'.$nbr] == "OK") {
		$note = -2;	
	}else{
		$note = $_POST['nb_crd'.$nbr];
		//str_replace(',', '.', $_POST['note']);
	}
	
	$last_change_user_id = $_GET['user_id'];
	$date = date('Y-m-d H:i:s');
	$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

	// Récupérer les informations actuelles de la note pour l'historique
	$getCurrentNote = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :note_id');
	$getCurrentNote->execute(array('note_id' => $note_id));
	$currentNote = $getCurrentNote->fetch();

	// Enregistrer dans l'historique des modifications de notes
	if ($currentNote && $currentNote['grade'] != $note) {
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
			ip_address
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
			:ip_address
		)');

		$insertHistory->execute(array(
			'note_id' => $note_id,
			'student_id' => $currentNote['student_id'],
			'session_id' => $currentNote['session_id'],
			'cours_sigle' => $currentNote['Sigle'],
			'cours_titre' => $currentNote['title_cours'],
			'old_grade' => $currentNote['grade'],
			'new_grade' => $note,
			'action_type' => 'modification',
			'action_by' => $last_change_user_id,
			'action_date' => $date,
			'ip_address' => $ip_address
		));
	}

	$updateNote = $dtb->prepare('UPDATE t_2023_notes SET grade=:note,last_change_user_id=:last_change_user_id,last_change_datetime=:last_change_datetime WHERE id=:note_id');

	$updateNote->bindParam(':note',$note,PDO::PARAM_STR);
	$updateNote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
	$updateNote->bindParam(':last_change_datetime',$date,PDO::PARAM_STR);
	$updateNote->bindParam(':note_id',$note_id,PDO::PARAM_INT);

	$updateNote->execute();

	header('location:../../src/student.php?id='.$id.'&page=transcriptSS#semestre'.$as);
 ?>