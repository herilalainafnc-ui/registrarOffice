<?php 
	require ('../../data/backdb.php');

	$annee_scolaire = (date('Y')-1).' - '.date('Y');

	$student = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_etude >= 3 AND annee_scolaire = "'.$annee_scolaire.'" ORDER BY student_id');

	while($std = $student->fetch()){
		echo "<br>";
		$student_id = $std['student_id'];

		$level = $std['annee_etude'];

		$workNote = 0;
		$remarkAcad = 0;
		$chapel = 0;
		$gen = 0;
		$maj = 0;
		$yes = 1;
		/**/ 
		$finale = 0;

		$cumulWorkNote = 0;
		$cumulremarkAcad = 0;
		$cumulChapel = 0;
		$cumulGen = 0;
		$cumulMaj = 0;
		
		/**/
		$cumulFinale = 0;

		if ($level > 3) {
			$init = 4;
		}elseif($level <= 3) {
			$init = 1;
		}

		for ($a=$init; $a <= $level; $a++) { 

			for ($s=1; $s <=2 ; $s++) {	
			

		$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND yearlevel='".$a."' AND semester='".$s."' ORDER BY id");
		
		$nbr = 0;
		
	
	
		$nbrMaj = 0;
		$credit = 0;
		$note = 0;
		$notecredit = 0;

		$nbrGen = 0;
		$tGen = 0;
		$tTGen = 0;

		/**/
		$nbrFinale = 0;
		$tFinale = 0;
		$tTFinale = 0;

		$tMaj = 0;
		$tTMaj = 0;
		$tcredit = 0;
		/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
		$tcreditMaj = 0;
		/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
		$tnote = 0;
		$tnotecredit = 0;
		
		if($cours->rowCount() > 0) {
			while ($crs = $cours->fetch()) {
			$note_id = $crs['id'];
		$session_id = $crs['session_id'];
		$annee_scolaire = $crs['annee_scolaire'];
	
			
				
					if($crs['grade']==-2){echo "";}else{ $notecredi = intval($crs['credit']) * intval($crs['grade']);}
						 
		if ($crs['cours_category'] == 1) {
			$valmajeur = $crs['grade'];
			$ident = 1;
		}else {
			$valmajeur = 0;
			$ident = 0;
		}

	$credit = 0;
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$creditMaj = 0;
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$notes = 0;

	$tcredit+= intval($credit) + intval($crs['credit']);

	/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {

		$tcreditMaj+= intval($creditMaj) + intval($crs['credit']);
		$nbrMaj++;

	}else{
		$tcreditMaj+=$creditMaj+ 0;
	}
	/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$tnote+= floatval($note) + floatval($crs['grade']);
	$tnotecredit+= floatval($notecredit) + floatval($notecredi);

	/* --- CALCULE DES NOTES GENERAL --- */

	if (($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) {
		$gradeGen = $crs['grade'];
		$nbrGen++;
	}else{
		$gradeGen = 0;
	}
		$tTGen += $tGen + $gradeGen;

	/* --- CALCULE DES NOTES MAJEURS --- */
	 
	if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
		$gradeMaj = $notecredi;
		$nbrMaj++;
	}else{
		$gradeMaj = 0;
	}

		$tTMaj += floatval($tMaj) + floatval($gradeMaj);

	/* --- CALCULE DES NOTES FINALES --- */
	 
	if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur") OR ($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) {
		$gradeFinale = $crs['grade'];
		$nbrFinale++;
	}else{
		$gradeFinale = 0;
	}

		$tTFinale += floatval($tFinale) + floatval($gradeFinale);


			$nbr++;
			}
		}
		
		if(!empty($session_id)){
			$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');
			$showPromotion = $searchPromotion->fetch();
			if (!empty($showPromotion)) {
				$grade_work_educ = $showPromotion['grade_work_educ'];
				$grade_remark_acad = $showPromotion['grade_remark_acad'];
				$grade_chapel_part = $showPromotion['grade_chapel_part'];
			}else{
				$grade_work_educ = 0;
				$grade_remark_acad = 0;
				$grade_chapel_part = 0;
			}

		}
	 if($nbrMaj != 0){$moyenMajSem = ($tTMaj/$tcreditMaj);}else{$moyenMajSem =0;}
	 if($nbr != 0){$moyenGenSem = $tnotecredit/$tcredit;}else{$moyenGenSem =0;}

			if (!empty($grade_work_educ) or !empty($grade_remark_acad) or !empty($grade_chapel_part)) {
				$cumulWorkNote += $workNote + $grade_work_educ;
				$cumulremarkAcad += $remarkAcad + $grade_remark_acad;
				$cumulChapel += $chapel + $grade_chapel_part;
			}
			
			$cumulGen += $gen + $moyenGenSem;
			$cumulMaj += $maj + $moyenMajSem;
			
			}

		}
	?>
	<!-- CUMULATIVE -->
			<b><?=$student_id?></b>
			
			<label>Level = <?=$level?> | Majeur = </label>
			<b><?=$totalMajCumul = round(($cumulMaj*20)/((($a-1)*2)*20),6);?></b>
			&nbsp;&nbsp;&nbsp;
			<label>Générale = </label>
			<b><?=$totalGenCumul = round(($cumulGen*20)/((($a-1)*2)*20),6);?></b>
<?php
		/*:::::::::::::::::::::::::::::::::::: GENERATE :::::::::::::::::::::::::::::::::*/

		$trouveDeans = $dtb->query('SELECT * FROM tbl_2024_deans WHERE student_id = "'.$student_id.'"');

		$voirDeans = $trouveDeans->fetch();

		if (!empty($voirDeans)) {
			

		}else{
			$insertDeans = $dtb->prepare('INSERT INTO tbl_2024_deans(
				student_id,
				student_dep,
				general_cumul,
				mageur_cumul,
				annee_scolaire
			)VALUES(
				:student_id,
				:student_dep,
				:general_cumul,
				:mageur_cumul,
				:annee_scolaire
			)');
			$insertDeans->execute(array())		}


		/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
	}
 ?>