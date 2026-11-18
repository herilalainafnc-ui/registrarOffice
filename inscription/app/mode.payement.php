<?php
/**
 * Enregistre le mode de paiement choisi pour une session.
 * SECURISE : authentification + niveau + CSRF (voir _guard.php), POST uniquement.
 */

require_once(__DIR__ . '/_guard.php');
inscriptionGuard('POST');

	header('Content-Type: application/json; charset=utf-8');

	/* Modes possibles : les cinq echeanciers de la fiche d'inscription
	   (src/extenssionPrint/fiche_inscription.php). La version precedente
	   acceptait n'importe quelle chaine, et ne verifiait pas qu'une ligne
	   avait bien ete modifiee : 220 lignes portent un mode vide. */
	const MODES_PAIEMENT = ['A', 'B', 'C', 'D', 'E'];

	try {
		$student_id = trim((string) ($_GET['student_id'] ?? ''));
		$session_id = (int) ($_GET['session_id'] ?? 0);
		$mode_payement = strtoupper(trim((string) ($_POST['modePayement'] ?? '')));

		if ($student_id === '' || $session_id <= 0) {
			inscriptionRefus('Étudiant ou session manquant.', 400);
		}
		if (!in_array($mode_payement, MODES_PAIEMENT, true)) {
			inscriptionRefus('Mode de paiement inconnu : ' . $mode_payement
				. '. Modes possibles : ' . implode(', ', MODES_PAIEMENT) . '.', 422);
		}

		$modifyPayement = $dtb->prepare('UPDATE t_2024_etudiant_finace SET mode_payement=:mode_payement WHERE student_id=:student_id AND session_id=:session_id');
		$modifyPayement->bindParam(':mode_payement',$mode_payement,PDO::PARAM_STR);
		$modifyPayement->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$modifyPayement->bindParam(':session_id',$session_id,PDO::PARAM_INT);
		$modifyPayement->execute();

		/* MySQL ne compte que les lignes CHANGEES : reenregistrer le mode deja
		   choisi (le meme bouton clique deux fois, la page revue) donne 0, et
		   l'etape annoncait a tort « aucune ligne de frais » (dossier 90023, le
		   12/10/2026, mode E deja pose). On distingue donc l'absence de ligne,
		   seule vraie erreur : les frais de la session ne sont pas calcules. */
		$lignes = $modifyPayement->rowCount();
		if ($lignes === 0) {
			$existe = $dtb->prepare('SELECT COUNT(*) FROM t_2024_etudiant_finace WHERE student_id = :student_id AND session_id = :session_id');
			$existe->execute(['student_id' => $student_id, 'session_id' => $session_id]);
			$lignes = (int) $existe->fetchColumn();
			if ($lignes === 0) {
				inscriptionRefus("Aucune ligne de frais pour cette session : recalculez les frais "
					. "avant de choisir un mode de paiement.", 422);
			}
		}

		echo json_encode(['success' => true, 'lignes' => $lignes,
			'message' => 'Mode de paiement ' . $mode_payement . ' enregistré'
				. ($lignes > 1 ? ' (' . $lignes . ' lignes en double mises à jour)' : '') . '.']);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
?>