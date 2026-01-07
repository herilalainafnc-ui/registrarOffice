<div>	
<?php 
	$level = $profil['annee_etude'];

	$workNote = 0;
	$remarkAcad = 0;
	$chapel = 0;
	$gen = 0;
	$maj = 0;
	
	/**/ 
	$finale = 0;

	$cumulWorkNote = 0;
	$cumulremarkAcad = 0;
	$cumulChapel = 0;
	$cumulGen = 0;
	$cumulMaj = 0;
	
	/**/
	$cumulFinale = 0;

	// Récupérer toutes les sessions distinctes où l'étudiant a des notes (même logique que transcriptSS.php)
	$searchAllSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
		FROM t_2023_notes n 
		INNER JOIN t_2023_session s ON n.session_id = s.session_id 
		WHERE n.student_id = '".$student_id."' AND n.ajout = '".$yes."' 
		ORDER BY s.session_year ASC, s.session_semester ASC");

	$sessionCount = 0;
	
	while($showSs = $searchAllSessions->fetch()){
		$sessionCount++;
		$session_id = $showSs['session_id'];
		
		$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND session_id='".$session_id."' ORDER BY id");

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
		$tcreditMaj = 0;
		$tnote = 0;
		$tnotecredit = 0;
		
		if($cours->rowCount() > 0) {
			while ($crs = $cours->fetch()) {
				$note_id = $crs['id'];
				$annee_scolaire = $crs['annee_scolaire'];
			
				if($crs['grade']==-2){
					$notecredi = 0;
				}else{ 
					$notecredi = $crs['credit'] * floatval($crs['grade']);
				}
					 
				if ($crs['cours_category'] == 1) {
					$valmajeur = $crs['grade'];
					$ident = 1;
				}else {
					$valmajeur = 0;
					$ident = 0;
				}

				$credit = 0;
				$creditMaj = 0;
				$notes = 0;

				$tcredit+= $credit + $crs['credit'];

				if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
					$tcreditMaj+=$creditMaj+ $crs['credit'];
				}else{
					$tcreditMaj+=$creditMaj+ 0;
				}

				$tnote+= $note + floatval($crs['grade']);
				$tnotecredit+= $notecredit + $notecredi;

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
					$gradeMaj = $crs['grade'];
					$nbrMaj++;
				}else{
					$gradeMaj = 0;
				}
				$tTMaj += $tMaj + $gradeMaj;

				/* --- CALCULE DES NOTES FINALES --- */
				if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur") OR ($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) {
					$gradeFinale = $crs['grade'];
					$nbrFinale++;
				}else{
					$gradeFinale = 0;
				}
				$tTFinale += $tFinale + floatval($gradeFinale);

				$nbr++;
			}
		}
		
		// Notes de promotion
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
		
		// Moyenne Majeure = somme des notes majeur / nombre de cours majeur (comme transcriptSS)
		if($nbrMaj != 0){
			$moyenMajSem = $tTMaj / $nbrMaj;
		}else{
			$moyenMajSem = 0;
		}
		
		// Moyenne Générale = somme(credit * note) / somme(credit) (comme transcriptSS)
		if($tcredit != 0){
			$moyenGenSem = $tnotecredit / $tcredit;
		}else{
			$moyenGenSem = 0;
		}

		if (!empty($grade_work_educ) or !empty($grade_remark_acad) or !empty($grade_chapel_part)) {
			$cumulWorkNote += $workNote + $grade_work_educ;
			$cumulremarkAcad += $remarkAcad + $grade_remark_acad;
			$cumulChapel += $chapel + $grade_chapel_part;
		}
		
		$cumulGen += $gen + $moyenGenSem;
		$cumulMaj += $maj + $moyenMajSem;
		$cumulFinale += $finale + $moyenGenSem;
	}
?>
<!-- CUMULATIVE -->
<div class='p-1 <?=$bg_three_color?> hover:<?=$bg_four_color?> mb-2 rounded-md border-2 border-slate-600 hover:border-cyan-500 transition-all text-xs text-white'>
		<label>Moyenne Majeure Cumulative = </label>
		<b><?php 
			if($sessionCount > 0){
				$totalMajCumul = round($cumulMaj / $sessionCount, 2);
				echo $totalMajCumul;
			}else{
				$totalMajCumul = 0;
				echo 0;
			}
		?></b>
		&nbsp;&nbsp;&nbsp;
		<label>Moyenne Générale Cumulative = </label>
		<b><?php 
			if($sessionCount > 0){
				$totalGenCumul = round($cumulGen / $sessionCount, 2);
				echo $totalGenCumul;
			}else{
				$totalGenCumul = 0;
				echo 0;
			}
		?></b>
	</tr>
</div>

</div>

