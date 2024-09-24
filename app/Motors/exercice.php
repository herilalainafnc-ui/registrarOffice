<?php 
	
	require ('../../data/backdb.php');


// MODIFICATION DE TOUT LES INSCRIT DE LA DATE 2024-09 EN NEW_STUDENT		
/*

		UPDATE tbl_2024_etudiant
		SET new_student = 1
		WHERE date_entry LIKE '2024-09-%' AND (new_student = '' OR new_student = 0);

		UPDATE tbl_2024_etudiant
		SET new_student = 0
		WHERE date_entry NOT LIKE '2024-09-%';

*/

// $date_entry = "2024-09-";

// $findNew = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE date_entry LIKE "%'.$date_entry.'%"');

// $nbr = 1;
// while ($showNew = $findNew->fetch()) {
// 	echo "<br>".$nbr."	".$showNew['new_student'];
	
// 	$student_id = $showNew['student_id'];
	
// 	$changeToNewStd = $dtb->prepare('UPDATE tbl_2024_etudiant SET new_student = 1,annee_scolaire = "2024 - 2025" WHERE student_id =:student_id');
	
// 	$changeToNewStd->bindParam(':student_id',$student_id,PDO::PARAM_STR);
// 	$changeToNewStd->execute();

// $nbr++;

// }


// Suppression des doublants
		
//COPY THIS IN THE SQL EDITOR FROM YOUR TABLE ON THE LOCALHOST SERVER
/*	
	DELETE FROM t_2024_etudiant_finace
		WHERE id NOT IN (
		    SELECT * FROM (
		        SELECT MIN(id)
		        FROM t_2024_etudiant_finace
		        GROUP BY student_id, session_id
		    ) AS temp
		);
	
	DELETE FROM t_2024_inscription_session
		WHERE id NOT IN (
		    SELECT * FROM (
		        SELECT MIN(id)
		        FROM t_2024_inscription_session
		        GROUP BY student_id, session_id
		    ) AS temp
		);
*/


// Suppression des espaces dans l'adresse mail
		// UPDATE `tbl_2024_etudiant` SET `student_email` = REPLACE(`student_email`, ' ', '');


/*	$student = $dtb->query('SELECT * FROM tbl_2024_etudiant');
	
	while($std = $student->fetch()) {
		
		$student_id = $std['student_id'];
		$etude_envisage = $std['etude_envisage'];
		$session_id = 0;
		$depot_list = 0;
		$verification_signatures = 0;
		$mode_payement = 0;
		$cours_selected = 0;
		$data_completion = 1;
		$caisse_verification = 0;
		$nbr_semester = 0;
		$test_niveau = 1;

				$find_etd_opt = $dtb->query('SELECT * FROM filiere WHERE filiere_description ="'.$etude_envisage.'" LIMIT 1');
					$show_etd_opt = $find_etd_opt->fetch();
					if (!empty($show_etd_opt)) {
						$etude_mention = $show_etd_opt['filiere_sigle'];
					}else{
						$etude_mention = '';
					}	
		
		
		if (!empty($std['status'])) {
			$status = $std['status'];	
		}else{
			$status = 'Externe';
		}
		
		
		if (!empty($std['graduated'])) {
			$new_student = 1;
		}else{
			$new_student = 0;
		}
		
		if (!empty($std['graduated'])) {
			$graduated = 1;
		}else {
			$graduated = 0;
		}

		$annee_scolaire = $std['annee_scolaire'];
		$date_entry = $std['date_entry'];
		
		$insert_inscription_session = $dtb->prepare('INSERT INTO t_2024_inscription_session(
			student_id,
			etude_mention,
			status,
			new_student,
			graduated,
			caisse_verification,
			data_completion,
			cours_selected,
			mode_payement,
			verification_signatures,
			depot_list,
			session_id,
			nbr_semester,
			test_niveau,
			annee_scolaire,
			date_entry
		)VALUES(
			:student_id,
			:etude_mention,
			:status,
			:new_student,
			:graduated,
			:caisse_verification,
			:data_completion,
			:cours_selected,
			:mode_payement,
			:verification_signatures,
			:depot_list,
			:session_id,
			:nbr_semester,
			:test_niveau,
			:annee_scolaire,
			:date_entry
		)'); $insert_inscription_session->execute(array(
			'student_id' => $student_id,
			'etude_mention' => $etude_mention,
			'status' => $status,
			'new_student' => $new_student,
			'graduated' => $graduated,
			'caisse_verification' => $caisse_verification,
			'data_completion' => $data_completion,
			'cours_selected' => $cours_selected,
			'mode_payement' => $mode_payement,
			'verification_signatures' => $verification_signatures,
			'depot_list' => $depot_list,
			'session_id' => $session_id,
			'nbr_semester' => $nbr_semester,
			'test_niveau' => $test_niveau,
			'annee_scolaire' => $annee_scolaire,
			'date_entry' => $date_entry
		));
	} */


// Assurez-vous d'utiliser une transaction si nécessaire

/*$dtb->beginTransaction();

$studentQuery = $dtb->query('SELECT * FROM tbl_2024_etudiant');

while ($std = $studentQuery->fetch()) {
    $student_id = $std['student_id'];
    $etude_envisage = $std['etude_envisage'];
    
    // Préparer la recherche de la filière

    $find_etd_opt = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = :etude_envisage LIMIT 1');
    $find_etd_opt->execute(['etude_envisage' => $etude_envisage]);
    $show_etd_opt = $find_etd_opt->fetch();
    $etude_mention = $show_etd_opt ? $show_etd_opt['filiere_sigle'] : '';

    $status = !empty($std['status']) ? $std['status'] : 'Externe';
    $new_student = !empty($std['graduated']) ? 1 : 0;
    $graduated = !empty($std['graduated']) ? 1 : 0;
    $annee_scolaire = $std['annee_scolaire'];
    $date_entry = $std['date_entry'];

    // Vérifiez si l'enregistrement existe déjà

    $checkExistence = $dtb->prepare('
        SELECT COUNT(*) FROM t_2024_inscription_session 
        WHERE student_id = :student_id 
        AND annee_scolaire = :annee_scolaire
        AND date_entry = :date_entry
    ');
    $checkExistence->execute([
        'student_id' => $student_id,
        'annee_scolaire' => $annee_scolaire,
        'date_entry' => $date_entry
    ]);

    $exists = $checkExistence->fetchColumn();
    
    if ($exists == 0) {
        // Préparer l'insertion
        $insert_inscription_session = $dtb->prepare('
            INSERT INTO t_2024_inscription_session (
                student_id, etude_mention, status, new_student, graduated,
                caisse_verification, data_completion, cours_selected, impression_verification,
                signatures, depot_list, session_id, nbr_semester, test_niveau, annee_scolaire, date_entry
            ) VALUES (
                :student_id, :etude_mention, :status, :new_student, :graduated,
                :caisse_verification, :data_completion, :cours_selected, :impression_verification,
                :signatures, :depot_list, :session_id, :nbr_semester, :test_niveau, :annee_scolaire, :date_entry
            )'
        );*/
       /* $insert_inscription_session->execute([
            'student_id' => $student_id,
            'etude_mention' => $etude_mention,
            'status' => $status,
            'new_student' => $new_student,
            'graduated' => $graduated,
            'caisse_verification' => 0,
            'data_completion' => 1,
            'cours_selected' => 0,
            'impression_verification' => 0,
            'signatures' => 0,
            'depot_list' => 0,
            'session_id' => 0,
            'nbr_semester' => 0,
            'test_niveau' => 1,
            'annee_scolaire' => $annee_scolaire,
            'date_entry' => $date_entry
        ]);
    }
}*/

// Commit transaction
// $dtb->commit();




/*

	$note = $dtb->query('SELECT * FROM t_2023_notes WHERE annee_scolaire="" ORDER BY student_id');

	while ($nt = $note->fetch()) {	
				
	echo "<br>".$student_id = $nt['student_id'];
	echo " | ".$nt['annee_scolaire'];
	echo " | yearlevel = ".$yearlevel = $nt['yearlevel'];
				$student = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE student_id ="'.$student_id.'"');
				$std = $student->fetch();
				echo " || ".$level = $std['annee_etude'];
				echo " | ".$year = $std['annee_scolaire'];
				echo " | ".$crackYear = substr($year, 0,4);
				if($level>3) {
					
				}
				if($level > 0 AND $level <= 3) {
					echo " | ID du cours = ".$id = $nt['id'];
					echo " | Année du cours = ".$beginYear = (($crackYear - $level) + $yearlevel);

						echo " |  Année construite = ".$buildingYear = ($beginYear)." - ".$beginYear+1;

						$insert = $dtb->prepare('UPDATE t_2023_notes SET annee_scolaire=:annee_scolaire WHERE id=:id');
						
						$insert->bindParam(':annee_scolaire',$buildingYear,PDO::PARAM_STR);
						$insert->bindParam(':id',$id,PDO::PARAM_INT);

						$insert->execute();

				}
	}*/





/* MODIFICATION DE SESSION A CHAQUE COURS DANS LA TABLE NOTES*/
// $session = $dtb->query('SELECT * FROM t_2023_session');

// while ($ss = $session->fetch()) {
	
// 	$session_id = $ss['session_id'];
// 	$session_year = $ss['session_year'];
// 	$session_semester = $ss['session_semester'];

// 	$notes = $dtb->prepare('UPDATE t_2023_notes SET session_id=:session_id WHERE annee_scolaire=:session_year AND semester=:semester AND transfert!=1');
	
// 	$notes->bindParam(':session_id',$session_id,PDO::PARAM_INT);
// 	$notes->bindParam(':session_year',$session_year,PDO::PARAM_STR);
// 	$notes->bindParam(':semester',$session_semester,PDO::PARAM_INT);

// 	$notes->execute();

// }



/* CREATION DE LA TABLE DE SESSION DEPUIS 2000 A 2024 */
// 	$buildyear = 2000;

// 	for ($i=0; $i < 24 ; $i++) { 

// 		for ($s=1; $s <=4 ; $s++) { 
			
// 			if ($s==1) {
// 				$session_code =	"PREM".($buildyear + $i).($buildyear + $i)+1;		
// 				$session_name = "Premier semestre";
// 				$session_semester = 1;
// 			}elseif($s==2) {
// 				$session_code =	"ETE".($buildyear + $i).($buildyear + $i)+1;
// 				$session_name = "Semestre d'été";
// 				$session_semester = 3;
// 			}elseif($s==3) {
// 				$session_code =	"DEUX".($buildyear + $i).($buildyear + $i)+1;
// 				$session_name = "Deuxième semestre";
// 				$session_semester = 2;
// 			}elseif($s==4) {
// 				$session_code =	"HIVER".($buildyear + $i).($buildyear + $i)+1;
// 				$session_name = "Semestre d'hiver";
// 				$session_semester = 4;
// 			}
			
// 			 $session_year = ($buildyear + $i)." - ".($buildyear + $i)+1;
// 			 $selected = 0;
// 			 $date_entry = date('Y-m-d');
			
// 			$session = $dtb->prepare('INSERT INTO t_2023_session(
// 				session_code,
// 				session_name,
// 				session_year,
// 				session_semester,
// 				selected,
// 				date_entry
// 			)VALUES(
// 				:session_code,
// 				:session_name,
// 				:session_year,
// 				:session_semester,
// 				:selected,
// 				:date_entry
// 			)');
// 			$session->execute(array(
// 				'session_code' => $session_code,
// 				'session_name' => $session_name,
// 				'session_year' => $session_year,
// 				'session_semester' => $session_semester,
// 				'selected' => $selected,
// 				'date_entry' => $date_entry
// 			));

// 		}
// 	}

	
 		// $cours = $dtb->query("SELECT * FROM t_2023_cours ORDER BY id");

 		// while($showCous = $cours->fetch()) {
 		// 	$id = $showCous['id'];
		// 	$nb_crd = $showCous['nb_crd'];
 		// 	$lab = $showCous['lab'];
 		// 	//$student_id = $showCous['student_id'];

		// 	if($lab == "0" OR $lab == "") {
 		// 		$cLab = 0;
 		// 	}else{
		// 		$cLab = 35000;
		// 	}

 		// 	$cCrd = 19000;

 		// 	$cout = $cCrd * $nb_crd;

 		// 	$updateCout = $dtb->prepare("UPDATE t_2023_cours SET cout=:cout,cout_lab=:cout_lab WHERE id=:id");

 		// 	$updateCout->bindParam(':cout',$cout,PDO::PARAM_STR);
		// 	$updateCout->bindParam(':cout_lab',$cLab,PDO::PARAM_STR);
 		// 	$updateCout->bindParam(':id',$id,PDO::PARAM_INT);
 		// 	$updateCout->execute();
			
//			$insert_Finance = $dtb->prepare("INSERT INTO t_2024_cours_finance(
//				student_id,
//				cours_id,
//				cours_sigle,
//				cours_title,
//				cours_credit,
//				cours_cout,
//				date_entry,
//				last_change_user_id
//			) VALUES (
//				:student_id,
//				:cours_id,
//				:cours_sigle,
//				:cours_title,
//				:cours_credit,
//				:cours_cout,
//				:date_entry,
//				:last_change_user_id
//			)");
//			$insert_Finance->execute(array(
//				'student_id' => $student_id,
//				'cours_id' => $cours_id,
//				'cours_sigle' => $sigle,
//				'cours_title' => $title_cours,
//				'cours_credit' => $credit,
//				'cours_cout' => $cout,
//				'date_entry' => $date_entry,
//				'last_change_user_id' => $user_id_entry
//			));

// 		}

















//	header('location:./end.php');
//	header('location : ../../src/accueil.php');
?>