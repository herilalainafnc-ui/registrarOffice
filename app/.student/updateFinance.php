<?php 

require '../../data/backdb.php';
	
	echo "<br>".$etude_envisage = $_GET['etude_envisage'];
	echo "<br>".$status = $_GET['status'];
	echo "<br>".$student_id = $_GET['student_id'];
	echo "<br>".$session_id = $_GET['session_id'];
	echo "<br>".$annee_etude = $_GET['annee_etude'];
	echo "<br>".$abonment = $_GET['abonment'];
	echo "<br>".$graduated = $_GET['graduated'];

	$findMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description = "'.$etude_envisage.'"');
	
		$showMention = $findMention->fetch();
		
		echo "<br><br>".$etude_mention = $showMention['filiere_sigle'];


	$verification_finance_licence = $dtb->query('SELECT * FROM t_2024_finance_detail_licence WHERE std_status = "'.$status.'" AND std_mention = "'.$etude_mention.'"');

		$result_finance = $verification_finance_licence->fetch();

/* ::::::::::::::::::::::::::::::::::::::: GENERATE COST FINANCE :::::::::::::::::::::::::::::::::::::::::*/
	
	/*============== DETERMINATION OF DAYS NUMBER ==============*/
	
	if ($annee_etude == 1) {

		echo "<br>". $nbr_day = $result_finance['nb_jours_semestre'];

		echo "<br>". $frais_costume = $result_finance['frais_costume'];
	
	}elseif ($annee_etude == 2) {
	
		echo "<br>". $nbr_day = $result_finance['nb_jours_semestre_L2'];

		echo "<br>". $frais_costume = 0;
	
	}elseif ($annee_etude == 3) {
	
		echo "<br>". $nbr_day = $result_finance['nb_jours_semestre_L3'];
		
		echo "<br>". $frais_costume = 0;
	}

	/*============== FRAIS GENEREAUX ==============*/

	echo "<br>". $cout_fraix_generaux = $result_finance['frais_generaux'];

	/*============== LOGMENT ==============*/

	echo "<br>Dortoir = ". $cout_logement = $result_finance['dortoir'] * $nbr_day;


	/*============== ABONEMENT CAF ==============*/

	if($abonment == 1) {

		echo "<br>Caf = ". $cout_abonment = $result_finance['cafeteria'] * $nbr_day;

	}else{
		
		echo "<br>". $cout_abonment = 0;

	}


	/*============== FOND DE DEPOT ==============*/

	echo "<br>". $fond_depot = $result_finance['fond_depot'];


	/*============== FRAIS DE GRADUATION ==============*/

	if ($graduated == 1) {

		echo "<br>". $cout_frais_graduation = $result_finance['frais_graduation'];

	}else{

		echo "<br>". $cout_frais_graduation = 0;

	}


	/*============== FRAIS DE COSTUME ==============*/
	
	echo "<br>". $frais_costume = $result_finance['frais_costume'];


$financement = $dtb->prepare("UPDATE t_2024_etudiant_finace SET 
	mention=:mention,
	level=:level,
	status=:status,
	cout_logement=:cout_logement,
	cout_fraix_generaux=:cout_fraix_generaux,
	cout_fondDepot_dortoir=:cout_fondDepot_dortoir,
	cout_abonment=:cout_abonment,
	cout_frais_graduation=:cout_frais_graduation,
	cout_costume=:cout_costume,
	last_change_datetime=:last_change_datetime
	
	WHERE student_id=:student_id AND session_id=:session_id");
	
	$financement->bindParam(':mention',$etude_mention,PDO::PARAM_STR);
	$financement->bindParam(':level',$annee_etude,PDO::PARAM_INT);
	$financement->bindParam(':status',$status,PDO::PARAM_STR);
	$financement->bindParam(':cout_logement',$cout_logement,PDO::PARAM_STR);
	$financement->bindParam(':cout_fraix_generaux',$cout_fraix_generaux,PDO::PARAM_STR);
	$financement->bindParam(':cout_fondDepot_dortoir',$fond_depot,PDO::PARAM_STR);
	$financement->bindParam(':cout_abonment',$cout_abonment,PDO::PARAM_STR);
	$financement->bindParam(':cout_frais_graduation',$cout_frais_graduation,PDO::PARAM_STR);
	$financement->bindParam(':cout_costume',$frais_costume,PDO::PARAM_STR);
	$financement->bindParam(':last_change_datetime',$last_change_datetime,PDO::PARAM_STR);
	$financement->bindParam(':student_id',$student_id,PDO::PARAM_STR);
	$financement->bindParam(':session_id',$session_id,PDO::PARAM_INT);

	$financement->execute();

 ?>