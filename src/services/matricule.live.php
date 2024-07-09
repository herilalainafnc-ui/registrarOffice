<?php 
	require('../../data/backdb.php');

	$mention = $_POST['mention'];
	$annee_etude = $_POST['annee_etude'];

	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle ="'.$mention.'"');
	$showM = $findMention->fetch();
	if(!empty($showM)) {
		$etude_envisage = $showM['filiere_description'];	
	
			if ($annee_etude <= 3) {
			
			$findOption = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE etude_envisage="'.$etude_envisage.'" AND annee_etude <= 3 ORDER BY student_id DESC limit 1');
			
			$showO = $findOption->fetch();

			if(!empty($showO)) {
				echo (intval($showO['student_id'])+1);	
			}else{
				if ($mention == "THEO") {
					echo "10000";
				}elseif ($mention == "GEST") {
					echo "20000";
				}elseif ($mention == "INFO") {
					echo "30000";
				}elseif ($mention == "NURS") {
					echo "40000";
				}elseif ($mention == "EDUC") {
					echo "50000";
				}elseif ($mention == "COMM") {
					echo "60000";
				}elseif ($mention == "LANG") {
					echo "70000";
				}elseif ($mention == "CPRE") {
					echo "80000";
				}elseif ($mention == "DROI") {
					echo "90000";
				}

			}
			

		}elseif($annee_etude > 3){
			
			$findOptionmaster = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE etude_envisage="'.$etude_envisage.'" AND annee_etude > 3 ORDER BY id DESC limit 1');

			$showOmaster = $findOptionmaster->fetch();
			
			if (!empty($showOmaster)) {
				$captiveID = substr($showOmaster['student_id'],0,6);
				$captiveMT = substr($mention,0,1);

				echo ((intval($captiveID)+1).'M'.$captiveMT);
			}else{
				$captiveMT = substr($mention,0,1);
				if ($mention == "THEO") {
					echo "10000M".$captiveMT;	
				}elseif ($mention == "GEST") {
					echo "20000M".$captiveMT;	
				}elseif ($mention == "INFO") {
					echo "30000M".$captiveMT;	
				}elseif ($mention == "NURS") {
					echo "40000M".$captiveMT;	
				}elseif ($mention == "EDUC") {
					echo "50000M".$captiveMT;	
				}elseif ($mention == "COMM") {
					echo "60000M".$captiveMT;	
				}elseif ($mention == "LANG") {
					echo "70000M".$captiveMT;	
				}elseif ($mention == "DROI") {
					echo "90000M".$captiveMT;	
				}
			}
			

		}

	}else {
		echo "00000";
	}
 ?>