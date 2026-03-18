<?php 
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	require('../../data/backdb.php');

	// Fuseau horaire Madagascar (UTC+3)
	date_default_timezone_set('Indian/Antananarivo');

	$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

	// Vérification d'autorisation : seuls les niveaux <= 3 (superadmin, admin, registrar) peuvent modifier les notes
	if (session_status() === PHP_SESSION_NONE) { ini_set('session.gc_maxlifetime', 36000); ini_set('session.cookie_lifetime', 36000); session_start(); }
	$currentUserLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
	if ($currentUserLevel > 3) {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas la permission de modifier les notes.']);
			exit;
		} else {
			$id = $_GET['id'] ?? 0;
			header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS&error=access_denied');
			exit;
		}
	}

	$id = $_GET['id'];
	$as = $_GET['as'];
	$nbr = $_GET['nbr'];
	$note_id = $_GET['note_id'];

	$rawNote = isset($_POST['nb_crd'.$nbr]) ? trim((string)$_POST['nb_crd'.$nbr]) : '';

	// Récupérer les informations actuelles de la note
	$getCurrentNote = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :note_id');
	$getCurrentNote->execute(array('note_id' => $note_id));
	$currentNote = $getCurrentNote->fetch();

	$isValidationCourse = false;
	if ($currentNote) {
		$isValidationCourse = (
			(int)($currentNote['cours_category'] ?? 0) === 5
			|| stripos((string)($currentNote['Sigle'] ?? ''), 'RELP 291') !== false
			|| stripos((string)($currentNote['title_cours'] ?? ''), 'formation spirituelle') !== false
		);
	}

	if ($isValidationCourse) {
		$upper = strtoupper($rawNote);
		if ($upper === 'V' || $upper === 'OK') {
			$note = -2;
		} elseif ($upper === 'E') {
			$note = 5;
		} else {
			$note = str_replace(',', '.', $rawNote);
			if (is_numeric($note)) {
				$note = round(floatval($note), 2);
			}
		}
	} else {
		if ($rawNote == "ok" OR $rawNote == "Ok" OR $rawNote == "OK") {
			$note = -2;
		} else {
			$note = str_replace(',', '.', $rawNote);
			// Arrondir à 2 décimales si la note est numérique
			if (is_numeric($note)) {
				$note = round(floatval($note), 2);
			}
		}
	}

	// Validation: la note ne doit pas dépasser 20
	if ($note != -2 && is_numeric($note) && $note > 20) {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'La note ne peut pas dépasser 20. Veuillez saisir une note entre 0 et 20.']);
			exit;
		} else {
			header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS&error=note_max#semestre'.$as);
			exit;
		}
	}

	// Validation: la note doit être numérique
	if ($note != -2 && !is_numeric($note)) {
		if ($isAjax) {
			header('Content-Type: application/json');
			if ($isValidationCourse) {
				echo json_encode(['success' => false, 'message' => 'Pour ce cours, utilisez V (validé) ou E (échec).']);
			} else {
				echo json_encode(['success' => false, 'message' => 'La note doit être un nombre valide.']);
			}
			exit;
		} else {
			header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS&error=note_invalid#semestre'.$as);
			exit;
		}
	}
	
	$last_change_user_id = $_GET['user_id'];
	$date = date('Y-m-d H:i:s');
	$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

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

	if ($isAjax) {
		header('Content-Type: application/json');
		echo json_encode(['success' => true, 'message' => 'Note mise à jour avec succès!']);
		exit;
	}

	header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS#semestre'.$as);
 ?>