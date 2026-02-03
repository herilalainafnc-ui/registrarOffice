<?php 

	require('../../data/backdb.php');

	$student_id = $_GET['student_id'] ?? '';
	$stage = $_GET['stage'] ?? '';

	// Vérification si l'étudiant est suspendu avec requête préparée
	$checkSuspension = $dtb->prepare('SELECT suspended, date_fin_suspension FROM tbl_2024_etudiant WHERE student_id = :student_id');
	$checkSuspension->execute(['student_id' => $student_id]);
	$suspensionData = $checkSuspension->fetch();
	
	if ($suspensionData && $suspensionData['suspended'] == 1) {
		$dateFin = $suspensionData['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			echo "BLOCKED";
			exit;
		}
	}

	$y = date('Y');

	// Requête préparée pour éviter l'injection SQL
	$stmtSession = $dtb->prepare('SELECT * FROM t_2024_inscription_session WHERE student_id = :student_id ORDER BY id DESC');
	$stmtSession->execute(['student_id' => $student_id]);
	$findStudent_Session = $stmtSession;

		$show_stage = $findStudent_Session->fetch();

	$aSem = $y." - ".($y+1);
	$aSem_ = ($y-1)." - ".$y;


	if (date('m') >= 7) {
		if ($show_stage['annee_scolaire'] == $aSem) {
			$id = $show_stage['id'];

			if ($stage == 2) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET data_verification = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 3) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET cours_selected = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 4) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET mode_payement = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 5) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET verification_signatures = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 6) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET depot_list = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 7) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET caisse_verification = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}

		}
	}elseif (date('m') < 7) {
		if ($show_stage['annee_scolaire'] != $aSem_) {
			$id = $show_stage['id'];
			
			if ($stage == 2) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET data_verification = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 3) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET cours_selected = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 4) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET mode_payement = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 5) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET verification_signatures = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 6) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET depot_list = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}elseif ($stage == 7) {
				$updateStage = $dtb->prepare('UPDATE t_2024_inscription_session SET caisse_verification = 1 WHERE id=:id');
				$updateStage->bindParam(':id',$id,PDO::PARAM_INT);
				$updateStage->execute();
			}


		}
	}

 ?>