<?php 
	require('../data/backdb.php');

	$id = $_GET['id'];
	echo "<br>".$session_id = $_GET['session_id'];
	echo "<br>".$student_id = $_GET['student_id'];
	echo "<br>".$nbr = $_GET['nbr'];
	echo "<br>".$a = $_GET['a'];
	echo "<br>".$s = $_GET['s'];
	echo "<br>".$annee_scolaire = $_GET['annee_scolaire'];
	
	echo "<br>".$grade_work_educ = $_POST['grade_work_educ'];
	echo "<br>".$grade_remark_acad = $_POST['grade_remark_acad'];
	echo "<br>".$grade_chapel_part = $_POST['grade_chapel_part'];

	$last_change_user_id = $_GET['user_id'];
	$date = date('Y-m-d');


	$search = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');

	$show = $search->fetch();

	if (!empty($show)) {
		echo "<br>Disponible";
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
		echo "<br>Non disponible";

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

	header('location:../src/student.php?id='.$id.'&page=transcript#semestre'.$a.$s);
 ?>