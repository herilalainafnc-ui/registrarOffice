<?php 
	require('../../data/backdb.php');

	$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

	$id = $_GET['id'];
	$session_id = $_GET['session_id'];
	$student_id = $_GET['student_id'];
	$nbr = $_GET['nbr'];
	$a = isset($_GET['a']) ? $_GET['a'] : '';
	$s = isset($_GET['s']) ? $_GET['s'] : '';
	$sessionCount = isset($_GET['sessionCount']) ? $_GET['sessionCount'] : '';
	$annee_scolaire = $_GET['annee_scolaire'];
	
	$grade_work_educ = $_POST['grade_work_educ'];
	$grade_remark_acad = $_POST['grade_remark_acad'];
	$grade_chapel_part = $_POST['grade_chapel_part'];

	// Validation: les notes ne doivent pas dépasser 20
	$validationErrors = [];
	if (!empty($grade_work_educ) && is_numeric($grade_work_educ) && $grade_work_educ > 20) {
		$validationErrors[] = 'Note de Work Education';
	}
	if (!empty($grade_remark_acad) && is_numeric($grade_remark_acad) && $grade_remark_acad > 20) {
		$validationErrors[] = 'Remarque académique';
	}
	if (!empty($grade_chapel_part) && is_numeric($grade_chapel_part) && $grade_chapel_part > 20) {
		$validationErrors[] = 'Note de chapelle';
	}
	if (!empty($validationErrors)) {
		$msg = implode(', ', $validationErrors) . ' ne peut pas dépasser 20.';
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => $msg]);
			exit;
		} else {
			header('location:../../src/student.php?id='.$id.'&page=transcriptSS&error=note_max');
			exit;
		}
	}

	$last_change_user_id = $_GET['user_id'];
	$date = date('Y-m-d');


	$search = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');

	$show = $search->fetch();

	if (!empty($show)) {
		 $updateNote = $dtb->prepare('UPDATE t_2023_promotion_notes SET
		 	grade_work_educ=:grade_work_educ,
		 	grade_remark_acad=:grade_remark_acad,
		 	grade_chapel_part=:grade_chapel_part,
		 	last_change_user_id=:last_change_user_id,
		 	last_change_datetime=:last_change_datetime
		 	WHERE student_id=:student_id AND session_id=:session_id');

		 $updateNote->bindParam(':grade_work_educ',$grade_work_educ,PDO::PARAM_STR);
		 $updateNote->bindParam(':grade_remark_acad',$grade_remark_acad,PDO::PARAM_STR);
		 $updateNote->bindParam(':grade_chapel_part',$grade_chapel_part,PDO::PARAM_STR);
		 $updateNote->bindParam(':last_change_user_id',$last_change_user_id,PDO::PARAM_INT);
		 $updateNote->bindParam(':last_change_datetime',$date,PDO::PARAM_STR);
		 $updateNote->bindParam(':student_id',$student_id,PDO::PARAM_INT);
		 $updateNote->bindParam(':session_id',$session_id,PDO::PARAM_INT);

		 $updateNote->execute();

	}else{

		$instertNote = $dtb->prepare('INSERT INTO t_2023_promotion_notes(
				student_id,
				session_id,
				semester,
				yearlevel,
				annee_scolaire,
				grade_work_educ,
				grade_chapel_part,
				grade_remark_acad,
				date_entry,
				last_change_user_id

			)VALUES(
				:student_id,
				:session_id,
				:semester,
				:yearlevel,
				:annee_scolaire,
				:grade_work_educ,
				:grade_chapel_part,
				:grade_remark_acad,
				:date_entry,
				:last_change_user_id
			)');
		$instertNote->execute(array(
				'student_id' => $student_id,
				'session_id' => $session_id,
				'semester' => $s,
				'yearlevel' => $a,
				'annee_scolaire' => $annee_scolaire,
				'grade_work_educ' => $grade_work_educ,
				'grade_chapel_part' => $grade_chapel_part,
				'grade_remark_acad' => $grade_remark_acad,
				'date_entry' => $date,
				'last_change_user_id' => $last_change_user_id
		));

	}

	if ($isAjax) {
		header('Content-Type: application/json');
		echo json_encode(['success' => true, 'message' => 'Notes de promotion mises à jour!']);
		exit;
	}

	header('location:../../src/student.php?id='.$id.'&page=transcriptSS#semestre'.$a.$s);
 ?>