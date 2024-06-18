	<?php

try {
	$bdw = new PDO('mysql:host=localhost;dbname=backup_18_avril','herilalaina','J8wFF(FOy1KI(nay');
	$bdw -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
/*echo "Connexion correct ♥";*/
}catch(PDOException $e) {
	die(header('location:dbauto/creatdatabase.php'). $e->getMessage());
}

	/*NEW BASE */ require('../../data/backdb.php');


/*$stdSearch = $dtb->query('SELECT * FROM etudiant_second_semester_23');

while($show = $stdSearch->fetch()){

	$student_id = $show['student_id'];
	echo "<br><b>".$student_id."</b>";*/

	$rechercher = $bdw->query('SELECT * FROM std_inscription WHERE grade !=0');

	$nb = 1;
	while($aff = $rechercher->fetch()) {
		$nb;
		$student_id = $aff['student_id'];
		$ses_id = $aff['session_id'];
		$sigle = $aff['Sigle'];
		$title_cours = $aff['title_cours'];
		$teacher_id = $aff['teacher_id'];
		$teacher_name = $aff['teacher_name'];
		$credit = $aff['credit'];
		$lab = $aff['lab'];
		$cours_category = $aff['cours_category'];
		$grade = $aff['grade'];		
		$ajout = 1;
		$transfert = 1;
		$origine_transfert = "OLD";
		$date_entry = $aff['date_entry'];
		$last_change_datetime = $aff['last_change_datetime'];
		$title_english = $aff['title_english'];

		$id_cours = $aff['id_cours'];

		$chAnn = $bdw->query('SELECT * FROM cours WHERE id_cours = "'.$id_cours.'"');
		$ann = $chAnn->fetch();

		$yearlevel = $ann['yearlevel'];

		
		$chSession = $bdw->query('SELECT * FROM session WHERE session_id ="'.$ses_id.'"');
		$sess = $chSession->fetch();

		$short_code = $sess['short_code'];

		$sem = $sess['sem'];

		if($sem == 3) {
			$sem = 1;
		}elseif($sem == 4) {
			$sem = 2;
		}
		


		$newAct = $dtb->query('SELECT * FROM t_2023_session WHERE session_code = "'.$short_code.'"');
		$nAct = $newAct->fetch();

		$session_id = $nAct['session_id'];


		if (!empty($aff)){
		
			$transfertData = $dtb->prepare('INSERT INTO t_2023_notes(
				Sigle,
				title_cours,
				student_id,
				session_id,
				yearlevel,
				semester,
				teacher_id,
				teacher_name,
				credit,
				lab,
				cours_category,
				grade,
				ajout,
				transfert,
				origine_transfert,
				date_entry,
				last_change_datetime,
				title_english
			) VALUES (
				:Sigle,
				:title_cours,
				:student_id,
				:session_id,
				:yearlevel,
				:semester,
				:teacher_id,
				:teacher_name,
				:credit,
				:lab,
				:cours_category,
				:grade,
				:ajout,
				:transfert,
				:origine_transfert,
				:date_entry,
				:last_change_datetime,
				:title_english
			)');
			/*$transfertData->execute(array(
				'Sigle' => $sigle,
				'title_cours' => $title_cours,
				'student_id' => $student_id,
				'session_id' => $session_id,
				'yearlevel' => $yearlevel,
				'semester' => $sem,
				'teacher_id' => $teacher_id,
				'teacher_name' => $teacher_name,
				'credit' => $credit,
				'lab' => $lab,
				'cours_category' => $cours_category,
				'grade' => $grade,
				'ajout' => $ajout,
				'transfert' => $transfert,
				'origine_transfert' => $origine_transfert,
				'date_entry' => $date_entry,
				'last_change_datetime' => $last_change_datetime,
				'title_english' => $title_english
			));*/
		}
	$nb++;


	}
	return header('location:./end.php');
	

?>