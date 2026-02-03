<?php 
	header('Content-Type: application/json');
	
	try {
		require('../../data/backdb.php');

		$cours_id = $_GET['cours_id'] ?? '';
		$student_id = $_GET['student_id'] ?? '';
		$session_id = $_GET['session_id'] ?? '';
		$cours_cout = $_GET['cours_cout'] ?? 0;
		$lab_cout = $_GET['lab_cout'] ?? 0;


		$retraitFinance = $dtb->prepare('DELETE FROM t_2024_cours_finance WHERE cours_id =:cours_id AND student_id=:student_id AND session_id=:session_id');
		$retraitFinance->bindValue(':cours_id',$cours_id,PDO::PARAM_INT);
		$retraitFinance->bindValue(':student_id',$student_id,PDO::PARAM_STR);
		$retraitFinance->bindValue(':session_id',$session_id,PDO::PARAM_INT);
		$retraitFinance->execute();


		$retraitCours = $dtb->prepare('DELETE FROM t_2023_notes WHERE id_cours =:cours_id AND student_id=:student_id AND session_id=:session_id');
		$retraitCours->bindValue(':cours_id',$cours_id,PDO::PARAM_INT);
		$retraitCours->bindValue(':student_id',$student_id,PDO::PARAM_STR);
		$retraitCours->bindValue(':session_id',$session_id,PDO::PARAM_INT);
		$retraitCours->execute();


		// Requête préparée pour éviter l'injection SQL
		$stmtFinance = $dtb->prepare("SELECT * FROM t_2024_etudiant_finace WHERE student_id = :student_id AND session_id = :session_id");
		$stmtFinance->execute(['student_id' => $student_id, 'session_id' => $session_id]);
		$findCoutFromFinance = $stmtFinance;

		$showCFromFinance = $findCoutFromFinance->fetch();
	
			$fCout = $showCFromFinance['cout_totalCours'];
			$fCout_lab = $showCFromFinance['cout_totalLab'];

			$restCout = $fCout - floatval($cours_cout);
			$restCoutLab = $fCout_lab - floatval($lab_cout);

			$updateReste = $dtb->prepare("UPDATE t_2024_etudiant_finace SET cout_totalCours=:cout_totalCours,cout_totalLab=:cout_totalLab WHERE student_id=:student_id AND session_id=:session_id");
			
			$updateReste->bindParam(':cout_totalCours',$restCout,PDO::PARAM_STR);
			$updateReste->bindParam(':cout_totalLab',$restCoutLab,PDO::PARAM_STR);
			$updateReste->bindParam(':student_id',$student_id,PDO::PARAM_STR);
			$updateReste->bindParam(':session_id',$session_id,PDO::PARAM_INT);
			
			$updateReste->execute();

		echo json_encode(['success' => true, 'message' => 'Cours retiré avec succès']);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
?>