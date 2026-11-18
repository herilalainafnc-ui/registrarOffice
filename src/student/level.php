<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion(); refuserEtudiant(); // notes : réservées au personnel ?>
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
	// Nombre de sessions réellement notées : sert de diviseur aux moyennes cumulatives
	// (une session sans aucune note chiffrée ne doit pas compter).
	$cumulGenCount = 0;
	$cumulMajCount = 0;

	// --- GPA pondéré par les crédits (système LMD) ---------------------------
	// Moyenne = somme(crédit × note) / somme(crédits), cumulée sur TOUTES les
	// sessions. Chaque crédit pèse pareil : un cours de 20 crédits compte donc
	// bien plus qu'un cours de 3. C'est cette valeur qui détermine la mention.
	$gpaCredits        = 0;
	$gpaNoteCredits    = 0;
	$gpaCreditsMaj     = 0;
	$gpaNoteCreditsMaj = 0;
	
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
		$tcreditGPA = 0; // credits des seuls cours reellement notes (denominateur de la moyenne)
		$tcreditMaj = 0;
		$tnotecreditMaj = 0;
		$tnote = 0;
		$tnotecredit = 0;

		if($cours->rowCount() > 0) {
			while ($crs = $cours->fetch()) {
				$note_id = $crs['id'];
				$annee_scolaire = $crs['annee_scolaire'];

				/**
				 * Codes de statut, tous hors moyenne :
				 *   -4 : pas encore note (NN)      -1 : ancien code herite
				 *   -2 : valide sans note          -3 : echec sans note
				 *   vide / NULL / non numerique
				 * Sont aussi exclus les cours de validation (categorie 5, RELP 291,
				 * formation spirituelle).
				 * Un 0 saisi est une VRAIE note (echec) et compte dans la moyenne.
				 */
				$gradeValue = is_numeric($crs['grade']) ? (float)$crs['grade'] : null;
				$estCoursValidation = ((int)$crs['cours_category'] === 5)
					|| stripos((string)($crs['Sigle'] ?? ''), 'RELP 291') !== false
					|| stripos((string)($crs['title_cours'] ?? ''), 'formation spirituelle') !== false;
				$compteDansMoyenne = ($gradeValue !== null && $gradeValue >= 0 && !$estCoursValidation);

				$notecredi = $compteDansMoyenne ? $crs['credit'] * $gradeValue : 0;

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

				if (!$compteDansMoyenne) {
					// Cours hors moyenne : ni au numerateur, ni au denominateur
					$nbr++;
					continue;
				}

				$tcreditGPA += $crs['credit'];

				if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
					$tcreditMaj     += $crs['credit'];
					$tnotecreditMaj += $crs['credit'] * $gradeValue;
				}

				$tnote+= $note + $gradeValue;
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
		
		// Moyenne Majeure = somme(credit * note) / somme(credit) des cours majeurs notes,
		// comme la generale, le bulletin et l'espace etudiant (c'etait une simple moyenne)
		$moyenMajSem = ($tcreditMaj > 0) ? $tnotecreditMaj / $tcreditMaj : null;

		// Moyenne Générale = somme(credit * note) / somme(credit DES COURS NOTES).
		// null si la session ne contient aucune note chiffrée (semestre en cours) :
		// elle ne doit alors pas peser dans la moyenne cumulative.
		$moyenGenSem = ($tcreditGPA > 0) ? $tnotecredit / $tcreditGPA : null;

		if (!empty($grade_work_educ) or !empty($grade_remark_acad) or !empty($grade_chapel_part)) {
			$cumulWorkNote += $workNote + $grade_work_educ;
			$cumulremarkAcad += $remarkAcad + $grade_remark_acad;
			$cumulChapel += $chapel + $grade_chapel_part;
		}

		if ($moyenGenSem !== null) {
			$cumulGen    += $moyenGenSem;
			$cumulFinale += $moyenGenSem;
			$cumulGenCount++;
		}
		if ($moyenMajSem !== null) {
			$cumulMaj += $moyenMajSem;
			$cumulMajCount++;
		}

		// Cumuls du GPA : on additionne les CRÉDITS et les POINTS, jamais les
		// moyennes — c'est ce qui distingue un GPA d'une moyenne de moyennes.
		$gpaCredits        += $tcreditGPA;
		$gpaNoteCredits    += $tnotecredit;
		$gpaCreditsMaj     += $tcreditMaj;
		$gpaNoteCreditsMaj += $tnotecreditMaj;
	}
?>
<!-- CUMULATIVE -->
<div class='p-1 <?=$bg_three_color?> hover:<?=$bg_four_color?> mb-2 rounded-md border-2 border-slate-600 hover:border-cyan-500 transition-all text-xs text-white'>
		<label>Moyenne Majeure Cumulative = </label>
		<b><?php
			// GPA majeur : somme(crédit × note) / somme(crédits) des cours majeurs
			$totalMajCumul = ($gpaCreditsMaj > 0) ? round($gpaNoteCreditsMaj / $gpaCreditsMaj, 2) : 0;
			echo $totalMajCumul;
		?></b>
		&nbsp;&nbsp;&nbsp;
		<label>Moyenne Générale Cumulative = </label>
		<b><?php
			// GPA général — c'est cette valeur qui détermine la mention du diplôme
			$totalGenCumul = ($gpaCredits > 0) ? round($gpaNoteCredits / $gpaCredits, 2) : 0;
			echo $totalGenCumul;
		?></b>
	</tr>
</div>

</div>

