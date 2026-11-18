<?php
/**
 * Marque une etape du parcours d'inscription comme franchie.
 * SECURISE : authentification + niveau + CSRF (voir _guard.php), POST uniquement.
 *
 * Repond desormais en JSON : la version precedente ecrivait « BLOCKED » en
 * texte brut pour un etudiant suspendu, et le front l'ignorait silencieusement.
 */

require_once(__DIR__ . '/_guard.php');
inscriptionGuard('POST');

header('Content-Type: application/json; charset=utf-8');

$student_id = $_GET['student_id'] ?? '';
$stage      = isset($_GET['stage']) ? (int)$_GET['stage'] : 0;

if ($student_id === '') {
	inscriptionRefus('Étudiant manquant.', 400);
}

// Etape -> colonne marquee. Remplace deux blocs if/elseif identiques de 30 lignes.
$colonnes = [
	2 => 'data_verification',
	3 => 'cours_selected',
	4 => 'mode_payement',
	5 => 'verification_signatures',
	6 => 'depot_list',
	7 => 'caisse_verification',
];

try {
	// --- Etudiant suspendu : refus explicite ---
	$checkSuspension = $dtb->prepare('SELECT suspended, date_fin_suspension FROM tbl_2024_etudiant WHERE student_id = :student_id ORDER BY remove ASC, id ASC LIMIT 1');
	$checkSuspension->execute(['student_id' => $student_id]);
	$suspensionData = $checkSuspension->fetch();

	if ($suspensionData && $suspensionData['suspended'] == 1) {
		$dateFin = $suspensionData['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			echo json_encode([
				'success' => false,
				'blocked' => true,
				'message' => 'Étudiant suspendu : progression impossible'
					. (!empty($dateFin) ? " (jusqu'au " . date('d/m/Y', strtotime($dateFin)) . ')' : '') . '.',
			]);
			exit;
		}
	}

	if (!isset($colonnes[$stage])) {
		// Etape 1 notamment : rien a marquer, ce n'est pas une erreur.
		echo json_encode(['success' => true, 'updated' => false, 'message' => 'Aucune étape à marquer.']);
		exit;
	}

	// La session visee est celle que le front indique ; a defaut, celle de
	// l'annee en cours. Choisir « l'id le plus grand » marquait l'etape sur le
	// dossier d'une autre annee des lors que celui-ci avait ete cree apres.
	$show_stage = inscSessionCourante($dtb, $student_id, $_GET['session_id'] ?? null);

	if (!$show_stage) {
		echo json_encode(['success' => false, 'message' => "Aucune inscription trouvée pour cet étudiant."]);
		exit;
	}

	// L'etape ne se marque que sur un dossier de l'annee scolaire en cours.
	// La version precedente testait « annee != annee precedente » avant
	// juillet : de janvier a juin elle refusait donc exactement les dossiers
	// de l'annee courante et acceptait les perimes.
	if (!inscAnneeEstCourante($show_stage['annee_effective'])) {
		echo json_encode([
			'success' => false,
			'updated' => false,
			'message' => "Cette inscription porte sur l'année " . $show_stage['annee_effective']
				. ', alors que l\'année en cours est ' . inscAnneeCourante()
				. '. Créez la session de l\'année en cours avant de poursuivre.',
		]);
		exit;
	}

	// Nom de colonne issu d'une liste fermee : jamais de donnee utilisateur ici.
	$maj = $dtb->prepare('UPDATE t_2024_inscription_session SET ' . $colonnes[$stage] . ' = 1 WHERE id = :id');
	$maj->execute(['id' => $show_stage['id']]);

	echo json_encode(['success' => true, 'updated' => true, 'stage' => $stage]);

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => "Erreur lors de l'enregistrement de l'étape."]);
}
