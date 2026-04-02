<?php 
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}

	$currentUserLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
	// Seuls superadmin, admin et registraire peuvent modifier/ajouter des notes
	if ($currentUserLevel > 3) {
		http_response_code(403);
		$isAjaxAuth = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
		if ($isAjaxAuth) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas la permission de modifier les notes.']);
			exit;
		}
		$idDenied = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		header('location:' . $app_base . '/course?id=' . $idDenied . '&page=notes&error=access_denied');
		exit;
	}

	require ('../../data/backdb.php');
	
	$id = $_GET['id'];
	$idcours = $_GET['idcours'];
	$grade =  str_replace(',', '.', $_POST['note']);
	$date_entry = date('Y-m-d');

	$year = $_GET['year'];
	$sigle = $_GET['sigle'];

	// Validation: note ne doit pas dépasser 20
	$gradeNum = floatval($grade);
	$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

	if ($gradeNum > 20) {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'La note ' . $gradeNum . ' dépasse le barème de 20/20']);
			exit;
		} else {
			header('location:' . $app_base . '/src/cours?id='.$id.'&page=notes#'.$sigle.$year);
			exit;
		}
	}

	if ($gradeNum < 0 && $gradeNum != -2) {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Note négative non autorisée']);
			exit;
		} else {
			header('location:' . $app_base . '/src/cours?id='.$id.'&page=notes#'.$sigle.$year);
			exit;
		}
	}

	$update = $dtb->prepare("UPDATE t_2023_notes SET grade=:grade WHERE id=:idcours");
	$update->bindParam(':grade',$grade,PDO::PARAM_STR);
	$update->bindParam(':idcours',$idcours,PDO::PARAM_INT);
	$update->execute();

	if ($isAjax) {
		header('Content-Type: application/json');
		echo json_encode(['success' => true, 'message' => 'Note mise à jour avec succès', 'grade' => $gradeNum]);
		exit;
	}

	header('location:' . $app_base . '/src/cours?id='.$id.'&page=notes#'.$sigle.$year);

 ?>