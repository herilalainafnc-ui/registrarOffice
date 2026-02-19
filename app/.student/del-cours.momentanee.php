<?php 
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require ('../../data/backdb.php');

// Fuseau horaire Madagascar (UTC+3)
date_default_timezone_set('Indian/Antananarivo');

// Vérifier si c'est une requête AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

try {
	$student_id = $_GET['student_id'];
	$id = $_GET['id'];
	$as = $_GET['as'];
	$idSupprCours = $_GET['idSupprCours'];
	$id_cours = isset($_GET['id_cours']) ? $_GET['id_cours'] : null;
	$ajout = 0;
	$remove = 1;
	$retrait_date = date('Y-m-d H:i:s');
	$last_change_datetime = date('Y-m-d H:i:s');
	$last_change_user_id = $_GET['user_id'];
	$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

	// Récupérer les informations actuelles de la note pour l'historique
	$getCurrentNote = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id = :note_id');
	$getCurrentNote->execute(array('note_id' => $idSupprCours));
	$currentNote = $getCurrentNote->fetch();

	// Enregistrer dans l'historique des modifications de notes (suppression) - optionnel
	if ($currentNote) {
		try {
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
				'new_grade' => null,
				'action_type' => 'suppression',
				'action_by' => $last_change_user_id,
				'action_date' => $last_change_datetime,
				'ip_address' => $ip_address,
				'commentaire' => 'Cours retiré de la liste'
			));
		} catch (PDOException $e) {
			// Ignorer l'erreur d'historique - la table peut ne pas exister
		}
	}

	// Mise à jour de la note (suppression logique)
	$updatenote = $dtb->prepare("UPDATE t_2023_notes SET 
		retrait_date=:retrait_date,
		ajout=:ajout,
		remove=:remove,
		last_change_user_id=:last_change_user_id,
		last_change_datetime=:last_change_datetime 
		WHERE id=:id");
	
	$updatenote->bindParam(':retrait_date', $retrait_date, PDO::PARAM_STR);
	$updatenote->bindParam(':ajout', $ajout, PDO::PARAM_INT);
	$updatenote->bindParam(':remove', $remove, PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_user_id', $last_change_user_id, PDO::PARAM_INT);
	$updatenote->bindParam(':last_change_datetime', $last_change_datetime, PDO::PARAM_STR);
	$updatenote->bindParam(':id', $idSupprCours, PDO::PARAM_INT);

	$updatenote->execute();

	// Mise à jour des finances du cours (optionnel)
	if ($id_cours) {
		try {
			$updatCoursFinance = $dtb->prepare("UPDATE t_2024_cours_finance SET 
				remove=:remove,
				last_change_user_id=:last_change_user_id,
				last_change_datetime=:last_change_datetime
				WHERE cours_id=:id");
			
			$updatCoursFinance->bindParam(':remove', $remove, PDO::PARAM_INT);
			$updatCoursFinance->bindParam(':last_change_user_id', $last_change_user_id, PDO::PARAM_INT);
			$updatCoursFinance->bindParam(':last_change_datetime', $last_change_datetime, PDO::PARAM_STR);
			$updatCoursFinance->bindParam(':id', $id_cours, PDO::PARAM_INT);

			$updatCoursFinance->execute();
		} catch (PDOException $e) {
			// Ignorer l'erreur - la table peut ne pas exister ou le cours n'est pas lié
		}
	}

	// Réponse selon le type de requête
	if ($isAjax) {
		header('Content-Type: application/json');
		echo json_encode(['success' => true, 'message' => 'Cours supprimé avec succès']);
		exit;
	} else {
		header('location:' . $app_base . '/student?id='.$id.'&page=transcriptSS#semestre'.$as);
		exit;
	}

} catch (Exception $e) {
	if ($isAjax) {
		header('Content-Type: application/json');
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
		exit;
	} else {
		die('Erreur lors de la suppression: ' . $e->getMessage());
	}
}
?>
