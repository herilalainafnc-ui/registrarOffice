<?php 
	require ('../../data/backdb.php');
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
$session = $dtb->query('SELECT * FROM t_2023_session');

while ($ss = $session->fetch()) {
	
	$session_id = $ss['session_id'];
	$session_year = $ss['session_year'];
	$session_semester = $ss['session_semester'];

	$notes = $dtb->prepare('UPDATE t_2023_notes SET session_id=:session_id WHERE annee_scolaire=:session_year AND semester=:semester AND transfert!=1');
	
	$notes->bindParam(':session_id',$session_id,PDO::PARAM_INT);
	$notes->bindParam(':session_year',$session_year,PDO::PARAM_STR);
	$notes->bindParam(':semester',$session_semester,PDO::PARAM_INT);

	$notes->execute();

}



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

	header('location:./end.php');
?>