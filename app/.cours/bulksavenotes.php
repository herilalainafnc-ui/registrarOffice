<?php
	require('../../data/backdb.php');

	header('Content-Type: application/json');

	// Vérifier que la requête est POST
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
		exit;
	}

	$input = json_decode(file_get_contents('php://input'), true);

	if (empty($input['notes']) || !is_array($input['notes'])) {
		echo json_encode(['success' => false, 'message' => 'Aucune note à enregistrer']);
		exit;
	}

	$notes = $input['notes'];
	$errors = [];
	$saved = 0;
	$warnings = [];

	$update = $dtb->prepare("UPDATE t_2023_notes SET grade = :grade WHERE id = :idcours");

	foreach ($notes as $note) {
		$idcours = (int)($note['idcours'] ?? 0);
		$grade = str_replace(',', '.', $note['grade'] ?? '');
		$studentId = $note['student_id'] ?? '';

		// Validation: note doit être numérique
		if ($grade === '' || !is_numeric($grade)) {
			continue; // Ignorer les notes vides
		}

		$gradeNum = round(floatval($grade), 2);

		// Validation: note ne doit pas dépasser 20
		if ($gradeNum > 20) {
			$errors[] = [
				'student_id' => $studentId,
				'grade' => $gradeNum,
				'message' => "Note de $gradeNum pour $studentId dépasse le barème de 20/20"
			];
			continue;
		}

		// Validation: note ne doit pas être négative (sauf -2 = dispensé)
		if ($gradeNum < 0 && $gradeNum != -2) {
			$errors[] = [
				'student_id' => $studentId,
				'grade' => $gradeNum,
				'message' => "Note négative ($gradeNum) pour $studentId non autorisée"
			];
			continue;
		}

		try {
			$update->execute([':grade' => $gradeNum, ':idcours' => $idcours]);
			$saved++;
		} catch (Exception $e) {
			$errors[] = [
				'student_id' => $studentId,
				'grade' => $gradeNum,
				'message' => "Erreur lors de la sauvegarde pour $studentId"
			];
		}
	}

	echo json_encode([
		'success' => count($errors) === 0,
		'saved' => $saved,
		'errors' => $errors,
		'message' => $saved . ' note(s) enregistrée(s)' . (count($errors) > 0 ? ', ' . count($errors) . ' erreur(s)' : '')
	]);
