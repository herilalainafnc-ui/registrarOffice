<?php 
	header('Content-Type: application/json');
	
	try {
		require('../../data/backdb.php');

		$student_id = $_GET['student_id'] ?? '';
		$session_id = $_GET['session_id'] ?? '';
		$mode_payement = $_POST['modePayement'] ?? '';

		$modifyPayement = $dtb->prepare('UPDATE t_2024_etudiant_finace SET mode_payement=:mode_payement WHERE student_id=:student_id AND session_id=:session_id');

		$modifyPayement->bindParam(':mode_payement',$mode_payement,PDO::PARAM_STR);
		$modifyPayement->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$modifyPayement->bindParam(':session_id',$session_id,PDO::PARAM_INT);

		$modifyPayement->execute();

		echo json_encode(['success' => true, 'message' => 'Mode de paiement enregistré avec succès']);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
?>