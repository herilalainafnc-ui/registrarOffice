<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // notes : réservées au personnel ; l'enseignant passe par une remise
refuserRequeteCroisee(); // action lancee depuis l application, jamais depuis un autre site (CSRF)
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
	$getNoteMeta = $dtb->prepare("SELECT cours_category, Sigle, title_cours FROM t_2023_notes WHERE id = :idcours LIMIT 1");

	foreach ($notes as $note) {
		$idcours = (int)($note['idcours'] ?? 0);
		$rawGrade = trim((string)($note['grade'] ?? ''));
		$grade = str_replace(',', '.', $rawGrade);
		$studentId = $note['student_id'] ?? '';

		$getNoteMeta->execute([':idcours' => $idcours]);
		$meta = $getNoteMeta->fetch(PDO::FETCH_ASSOC);
		$isValidationCourse = false;
		if ($meta) {
			$isValidationCourse = (
				(int)($meta['cours_category'] ?? 0) === 5
				|| stripos((string)($meta['Sigle'] ?? ''), 'RELP 291') !== false
				|| stripos((string)($meta['title_cours'] ?? ''), 'formation spirituelle') !== false
			);
		}

		$upper = strtoupper($rawGrade);
		if ($upper === 'V' || $upper === 'OK') {
			$gradeNum = -2;
		} elseif ($upper === 'E') {
			$gradeNum = -3;
		} elseif ($upper === 'NN') {
			// -4 = pas encore noté, hors moyenne (data/notes.statut.php).
			$gradeNum = -4;
		} else {
			// Validation: note doit etre numerique
			if ($grade === '' || !is_numeric($grade)) {
				continue; // Ignorer les notes vides
			}

			$gradeNum = round(floatval($grade), 2);
		}

		// Validation: note ne doit pas dépasser 20
		if ($gradeNum > 20) {
			$errors[] = [
				'student_id' => $studentId,
				'grade' => $gradeNum,
				'message' => "Note de $gradeNum pour $studentId dépasse le barème de 20/20"
			];
			continue;
		}

		// Validation: note ne doit pas etre negative (sauf -2/-3/-4 = statuts sans note)
		if ($gradeNum < 0 && !in_array($gradeNum, [-2, -3, -4])) {
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
