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
	$session_id = $showSs['session_id'];

	if(isset($_POST['checklist'])) {
		
		$tCout = 0;
		$tCout_lab = 0;
		
		foreach($_POST['checklist'] as $i){
			
			$grade = 0;
				$recherche = $dtb->query("SELECT * FROM t_2023_cours WHERE id ='".$i."'");
				
				$affiche = $recherche->fetch();
					$cours_id = $affiche['id'];
		      		$sigle = $affiche['Sigle'];
				    $id_cours = $affiche['id'];
					$title_cours = $affiche['title'];
					$credit = $affiche['nb_crd'];
					$lab = $affiche['lab'];
					$cout = $affiche['cout'];
					$cout_lab = $affiche['cout_lab'];
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


			$insert_Finance = $dtb->prepare("INSERT INTO t_2024_cours_finance(
				student_id,
				session_id,
				cours_id,
				cours_sigle,
				cours_title,
				cours_credit,
				cours_cout,
				lab_cout,
				date_entry,
				last_change_user_id
			) VALUES (
				:student_id,
				:session_id,
				:cours_id,
				:cours_sigle,
				:cours_title,
				:cours_credit,
				:cours_cout,
				:lab_cout,
				:date_entry,
				:last_change_user_id
			)");
			$insert_Finance->execute(array(
				'student_id' => $student_id,
				'session_id' => $session_id,
				'cours_id' => $cours_id,
				'cours_sigle' => $sigle,
				'cours_title' => $title_cours,
				'cours_credit' => $credit,
				'cours_cout' => $cout,
				'lab_cout' => $cout_lab,
				'date_entry' => $date_entry,
				'last_change_user_id' => $user_id_entry
			));

		/*===================== FIANCES =====================*/

		$tCout += $cout;
		$tCout_lab += $cout_lab;
		}
		
		$tCout;
		$tCout_lab;
		
		$updateTotality = $dtb->prepare("UPDATE t_2024_etudiant_finace SET session_id=:session_id,cout_totalCours=:cout_totalCours,cout_totalLab=:cout_totalLab WHERE student_id=:student_id");
		$updateTotality->bindParam(':session_id',$session_id,PDO::PARAM_INT);
		$updateTotality->bindParam(':cout_totalCours',$tCout,PDO::PARAM_STR);
		$updateTotality->bindParam(':cout_totalLab',$tCout_lab,PDO::PARAM_STR);
		$updateTotality->bindParam(':student_id',$student_id,PDO::PARAM_STR);
		$updateTotality->execute();
	}
	
	header('location:../../src/student.php?id='.$id.'&page=transcript');
	
	//header('location:../../src/genPDF/dom.php?id='.$id.'&page=transcript&ptype=ficheInscription');
 ?>