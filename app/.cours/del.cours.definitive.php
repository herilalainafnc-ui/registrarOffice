<?php
	// MVC base path
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = substr($_ar, strlen($_dr)) ?: '';

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}

	$currentUserLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 99;
	if (!in_array($currentUserLevel, [1, 3], true)) {
		http_response_code(403);
		header('location:' . $app_base . '/courses?error=access_denied');
		exit;
	}

	require '../../data/backdb.php';

	$id = (int)($_GET['id'] ?? 0);

	$delete = $dtb->prepare("DELETE FROM t_2023_cours WHERE id =:id");
	$delete->bindvalue(':id', $id, PDO::PARAM_INT);
	$delete->execute();
	header('location:' . $app_base . '/courses');
 ?>