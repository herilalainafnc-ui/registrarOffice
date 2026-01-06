<?php
	require ('../../data/backdb.php');

	$cours = $dtb->query("SELECT * FROM t_2023_notes");

	while ($affCnoSS=$cours->fetch()) {


		if (empty($affCnoSS['session_id'])) {
	
			$id = $affCnoSS['id'];
			
			echo ' - semestrer : '.$affCnoSS['semester'];
			
			echo ' - date_entry : '.$affCnoSS['date_entry'];


			//if (!empty($affCnoSS['annee_scolaire']) OR $affCnoSS['annee_scolaire'] == 0) {
				
				$year = substr($affCnoSS['date_entry'], 0, 4);

				echo "<b>".$year."</b>";

				$yInit = $year-1;
				$yFin = $year;

				echo ' annee_scolaire <> : '.$scolarYear = $yInit.' - '.$yFin;	
			
			/*}else{
				echo ' annee_scolaire : '.$scolarYear = $affCnoSS['annee_scolaire'];
			}*/
			

			$session_id = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester = "'.$affCnoSS['semester'].'" AND session_year = "'.$scolarYear.'"');

			$ss_id = $session_id->fetch();

			$ssUpdate = $ss_id['session_id'];

			$update = $dtb->prepare('UPDATE t_2023_notes SET 
				
				annee_scolaire=:annee_scolaire,
				session_id=:ssUpdate

				WHERE id=:id');
			$update->bindParam(':id',$id,PDO::PARAM_INT);
			$update->bindParam(':annee_scolaire',$scolarYear,PDO::PARAM_STR);
			$update->bindParam(':ssUpdate',$ssUpdate,PDO::PARAM_INT);
			$update->execute();
		}
	}


?>