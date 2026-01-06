<?php 
	require ('../../data/backdb.php');

	$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE annee_scolaire = 0");

	while ($affCnoSS=$cours->fetch()) {
		
		$id = $affCnoSS['id'];
		
		echo "<br>".$session_id = $affCnoSS['session_id'];
		
		$session = $dtb->query('SELECT * FROM t_2023_session WHERE session_id = "'.$session_id.'"');

			$ss = $session->fetch();

			echo " >>>> ".$sessionYear = $ss['session_year'];

			$update = $dtb->prepare('UPDATE t_2023_notes SET 
				
				annee_scolaire=:annee_scolaire

				WHERE id=:id');

			$update->bindParam(':annee_scolaire',$sessionYear,PDO::PARAM_STR);
			$update->bindParam(':id',$id,PDO::PARAM_INT);
			
			$update->execute();

	}

 ?>