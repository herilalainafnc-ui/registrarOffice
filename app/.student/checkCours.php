<?php 
	require ('../../data/backdb.php');

	$id = $_GET['id'];
	$student_id = $_GET['student_id'];
	$semesterSession = $_POST['semesterSession'];
	$annee_scolaire = $_POST['annee_scolaire'];
	$page = $_GET['page'];
	$user_id_entry = $_GET['user_id'];
	$date_entry = date('Y-m-d');
	$ajout = '1';

	$searchSs = $dtb->query('SELECT * FROM t_2023_session WHERE session_name="'.$semesterSession.'" AND session_year="'.$annee_scolaire.'"');

	$showSs = $searchSs->fetch();
	echo $session_id = $showSs['session_id'];

	if(isset($_POST['checklist'])) {

		foreach($_POST['checklist'] as $i){
			
			$grade = 0;
				$recherche = $dtb->query("SELECT * FROM t_2023_cours WHERE id ='".$i."'");
				$affiche = $recherche->fetch();
		      		$sigle = $affiche['Sigle'];
				    $id_cours = $affiche['id'];
					$title_cours = $affiche['title'];
					$credit = $affiche['nb_crd'];
					$lab = $affiche['lab'];
					$cours_category = $affiche['category'];
					$semester = $affiche['semester'];
					$yearlevel = $affiche['yearlevel'];
					$teacher_id = $affiche['id_teacher'];

			$insert = $dtb->prepare("INSERT INTO t_2023_notes(
				id_cours,
				Sigle,
				title_cours,
				student_id,
				teacher_id,
				session_id,
				semester,
				yearlevel,
				annee_scolaire,
				credit,
				lab,
				grade,
				ajout,
				cours_category,
				user_id_entry,
				date_entry
				) VALUES (
				:id_cours,
				:Sigle,
				:title_cours,
				:student_id,
				:teacher_id,
				:session_id,
				:semester,
				:yearlevel,
				:annee_scolaire,
				:credit,
				:lab,
				:grade,
				:ajout,
				:cours_category,
				:user_id_entry,
				:date_entry
			)");
			$insert->execute(array(
					'id_cours' => $id_cours,
					'Sigle' => $sigle,
					'title_cours' => $title_cours,
					'student_id' => $student_id,
					'teacher_id' => $teacher_id,
					'session_id' => $session_id,
					'semester' => $semester,
					'yearlevel' => $yearlevel,
					'annee_scolaire' => $annee_scolaire,
					'credit' => $credit,
					'lab' => $lab,
					'grade' => $grade,
					'ajout' => $ajout,
					'cours_category' => $cours_category,
					'user_id_entry' => $user_id_entry,
					'date_entry' => $date_entry
			));


		}
	}
	header('location:../../src/student.php?id='.$id.'&page=transcript')
 ?>