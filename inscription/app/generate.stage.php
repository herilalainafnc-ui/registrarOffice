<?php 

	require('../../data/backdb.php');

	$student_id = $_GET['student_id'];
	$stage = $_GET['stage'];

	$y = date('Y');

	$findStudent_Session = $dtb->query('SELECT * FROM t_2024_inscription_session WHERE student_id="'.$student_id.'" ORDER BY id DESC');

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