<?php
/**
 * Retrait d'un cours du cursus d'un etudiant pour une session donnee.
 *
 * SECURISE : authentification + niveau + CSRF (voir _guard.php), POST uniquement.
 *
 * Trois corrections par rapport a la version precedente :
 *  1. Les montants ne viennent plus du client. Ils sont relus depuis
 *     t_2024_cours_finance : le navigateur pouvait auparavant dicter ce qui
 *     etait soustrait du solde de l'etudiant.
 *  2. Retrait DOUX de la note (remove = 1, ajout = 0, retrait_date) au lieu
 *     d'un DELETE. C'est la convention du reste de l'application
 *     (cf. app/.student/del-cours.momentanee.php) et c'est ce qui permet a la
 *     tentative retiree de figurer au releve de notes.
 *  3. Le tout dans une transaction : les compteurs financiers ne peuvent plus
 *     rester desynchronises si une etape echoue.
 */

require_once(__DIR__ . '/_guard.php');
inscriptionGuard('POST');

header('Content-Type: application/json; charset=utf-8');

$cours_id   = isset($_POST['cours_id'])   ? (int)$_POST['cours_id']   : 0;
$session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
$student_id = isset($_POST['student_id']) ? trim((string)$_POST['student_id']) : '';

if ($cours_id <= 0 || $session_id <= 0 || $student_id === '') {
	inscriptionRefus('Paramètres incomplets (cours, session ou étudiant manquant).', 400);
}

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
	$dtb->beginTransaction();

	// --- Montants reels, relus en base (jamais ceux transmis par le client) ---
	$ligne = $dtb->prepare('SELECT cours_cout, lab_cout FROM t_2024_cours_finance
		WHERE cours_id = :cours_id AND student_id = :student_id AND session_id = :session_id
		FOR UPDATE');
	$ligne->execute(['cours_id' => $cours_id, 'student_id' => $student_id, 'session_id' => $session_id]);
	$ligneCours = $ligne->fetch(PDO::FETCH_ASSOC);

	if (!$ligneCours) {
		$dtb->rollBack();
		inscriptionRefus("Ce cours n'est pas rattaché à cet étudiant pour cette session.", 404);
	}

	$coutCours = (float)$ligneCours['cours_cout'];
	$coutLab   = (float)$ligneCours['lab_cout'];

	// --- Retrait de la ligne de facturation du cours ---
	$suppr = $dtb->prepare('DELETE FROM t_2024_cours_finance
		WHERE cours_id = :cours_id AND student_id = :student_id AND session_id = :session_id');
	$suppr->execute(['cours_id' => $cours_id, 'student_id' => $student_id, 'session_id' => $session_id]);

	// --- Retrait DOUX de la note : la tentative reste au releve ---
	$retirer = $dtb->prepare('UPDATE t_2023_notes
		SET remove = 1, ajout = 0, retrait_date = :retrait_date,
		    last_change_user_id = :user_id, last_change_datetime = NOW()
		WHERE id_cours = :cours_id AND student_id = :student_id AND session_id = :session_id');
	$retirer->execute([
		'retrait_date' => date('Y-m-d'),
		'user_id'      => $userId,
		'cours_id'     => $cours_id,
		'student_id'   => $student_id,
		'session_id'   => $session_id,
	]);

	// --- Mise a jour des totaux, bornee a zero ---
	$fin = $dtb->prepare('SELECT cout_totalCours, cout_totalLab FROM t_2024_etudiant_finace
		WHERE student_id = :student_id AND session_id = :session_id FOR UPDATE');
	$fin->execute(['student_id' => $student_id, 'session_id' => $session_id]);
	$finance = $fin->fetch(PDO::FETCH_ASSOC);

	if (!$finance) {
		$dtb->rollBack();
		inscriptionRefus('Fiche financière introuvable pour cette session.', 404);
	}

	$resteCours = max(0, (float)$finance['cout_totalCours'] - $coutCours);
	$resteLab   = max(0, (float)$finance['cout_totalLab']   - $coutLab);

	$maj = $dtb->prepare('UPDATE t_2024_etudiant_finace
		SET cout_totalCours = :cout_totalCours, cout_totalLab = :cout_totalLab
		WHERE student_id = :student_id AND session_id = :session_id');
	$maj->execute([
		'cout_totalCours' => $resteCours,
		'cout_totalLab'   => $resteLab,
		'student_id'      => $student_id,
		'session_id'      => $session_id,
	]);

	$dtb->commit();

	// Les montants renvoyes font autorite : le front recalcule a partir d'eux.
	echo json_encode([
		'success'         => true,
		'message'         => 'Cours retiré avec succès.',
		'cours_cout'      => $coutCours,
		'lab_cout'        => $coutLab,
		'cout_totalCours' => $resteCours,
		'cout_totalLab'   => $resteLab,
	]);

} catch (Exception $e) {
	if ($dtb->inTransaction()) { $dtb->rollBack(); }
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Erreur lors du retrait du cours.']);
}
