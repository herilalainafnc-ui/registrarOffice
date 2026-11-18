<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion(); refuserEtudiant(); // notes : réservées au personnel ?>

<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">	
<?php 
	// Récupérer toutes les sessions distinctes où l'étudiant a des notes validées (>=10)
	$searchAllSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
		FROM t_2023_notes n 
		INNER JOIN t_2023_session s ON n.session_id = s.session_id 
		WHERE n.student_id = ".$dtb->quote($student_id)." AND n.ajout = ".$dtb->quote($yes)." AND (n.grade >= 10 OR n.grade = -2)
		ORDER BY s.session_year ASC, s.session_semester ASC");

	$sessionCount = 0;

	// GPA pondere par les credits : somme(credit x note) / somme(credits).
	// Formule officielle (systeme LMD), identique a l'export bulletin.
	$gpaCredits = 0;        $gpaNoteCredits = 0;
	$gpaCreditsMaj = 0;     $gpaNoteCreditsMaj = 0;
	$cumulWorkNote = 0;     $cumulWorkCount = 0;
	$cumulChapel = 0;       $cumulChapelCount = 0;
	
	while($showSs = $searchAllSessions->fetch()){
		$sessionCount++;
		$session_id = $showSs['session_id'];
		$combinAnual = $showSs['session_year'];
		
		// Récupérer le yearlevel depuis les notes de cette session
		$getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id=".$dtb->quote($student_id)." AND session_id=".$dtb->quote($session_id)." AND ajout=".$dtb->quote($yes)." AND (grade >= 10 OR grade = -2) LIMIT 1");
		$ylData = $getYearlevel->fetch();
		$yearlevel = $ylData ? $ylData['yearlevel'] : 1;
		
		// Déterminer le niveau (Licence ou Master)
		if ($yearlevel <= 3) {
			$niveau_label = "Licence " . $yearlevel;
		} else {
			$niveau_label = "Master " . ($yearlevel - 3);
		}
?>
	<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>

<?php	
	$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id =".$dtb->quote($student_id)." AND ajout=".$dtb->quote($yes)." AND session_id=".$dtb->quote($session_id)." AND (grade >= 10 OR grade = -2) ORDER BY id");
	
	if ($cours->rowCount() > 0) {
 ?>
		<table class="simpleTbl mb-1">
			<thead>
				<tr class="text-center bg-gradient-to-r from-cyan-500">
					<th colspan="10"><b><?=$niveau_label?></b> | <?=$showSs['session_name']?> - Session N°<?=$showSs['session_semester']?> | Année <?=$combinAnual?></th>
				</tr>
			</thead>
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<th class="w-20">Sigle</th>
					<th class="w-">Titre du cours</th>
					<th class="w-20">Crédits</th>
					<th class="w-20">Catégorie</th>
					<th class="w-20">Notes/20</th>
					<th class="w-20">Crd X Not</th>
					<th class="w-4">État</th>
				</tr>	
			</thead>
			<tbody class="<?=$bg_four_color?>">
	<?php
	$nbr = 0;
	$nbrMaj = 0;
	$credit = 0;
	$note = 0;
	$notecredit = 0;

	$nbrGen = 0;
	$tGen = 0;
	$tTGen = 0;

	$nbrFinale = 0;
	$tFinale = 0;
	$tTFinale = 0;

	$tMaj = 0;
	$tTMaj = 0;
	$tcredit = 0;
	$tcreditGPA = 0;      // credits comptant dans la moyenne
	$tcreditMaj = 0;
	$tnotecreditMaj = 0;
	$tnote = 0;
	$tnotecredit = 0;
	
	while ($crs = $cours->fetch()) {
		$note_id = $crs['id'];
		$annee_scolaire = $crs['annee_scolaire'];
	// Cours valide sans note numerique (-2 ou cours de validation) : affiche "V",
	// exclu de la moyenne.
	$gradeValue = (float)$crs['grade'];
	$isValidationCourse = ($crs['cours_category'] == 5)
		|| stripos((string)($crs['Sigle'] ?? ''), 'RELP 291') !== false
		|| stripos((string)($crs['title_cours'] ?? ''), 'formation spirituelle') !== false;
	$isStatusOnlyV = ($gradeValue == -2) || $isValidationCourse;
	$notecredi = $isStatusOnlyV ? 0 : $crs['credit'] * $gradeValue;
	 ?>
				<tr class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black">
					<td class="bg-gradient-to-r from-orange-800 to-orange-400"><?=$crs['Sigle']?></td>
					<td><?=$crs['title_cours']?></td>
					<td><?=$crs['credit']?></td>
					<td><?php 
if ($crs['cours_category'] == 0){
	echo "Général";
}elseif ($crs['cours_category'] == 1) {
	echo "Majeur";
}elseif ($crs['cours_category'] == -1 OR $crs['cours_category'] == 2) {
	echo "Selective";
}elseif ($crs['cours_category'] == 3) {
	echo "Additionnel";
}elseif ($crs['cours_category'] == 5) {
	echo "``";
}else{
	echo "-";
}
						 ?></td>
					<td><?=$isStatusOnlyV ? "V" : $crs["grade"]?></td>
					<td><?=$isStatusOnlyV ? "--" : $notecredi?></td>
					
					<td class="bg-green-500 text-center" title="<?=$isStatusOnlyV ? "Validé" : "Succès"?>"><?=$isStatusOnlyV ? "V" : "S"?></td>
<?php 
	if ($crs['cours_category'] == 1) {
		$valmajeur = $crs['grade'];
		$ident = 1;
	}else {
		$valmajeur = 0;
		$ident = 0;
	}
 ?>
				</tr>

	<?php
$credit = 0;
$notes = 0;
$creditMaj = 0;

$tcredit+= $credit + $crs['credit'];   // credits valides (inclut les cours "V")

if (!$isStatusOnlyV) {
	$tcreditGPA += $crs['credit'];

	if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
		// GPA majeur : credits et points
		$tcreditMaj     += $crs['credit'];
		$tnotecreditMaj += $crs['credit'] * $gradeValue;
		$nbrMaj++;
	}
}

$tnote+= $note + $crs['grade'];
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
	$gradeMaj = $notecredi;
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
	$tTFinale += $tFinale + $gradeFinale;

	$nbr++;
	}
	 ?>			
			</tbody>
			<tfoot class="<?=$bg_one_color?> text-white">
				<tr>
					<th colspan="2"><?=$nbr?> cours validés</th>
					<th><?php if(!empty($tcredit)) { echo $tcredit;}?></th>
					<th></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnote,2);}?></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnotecredit,2);}?></th>
					<th colspan="2"></th>
				</tr>

<?php
	if(!empty($session_id)){
		$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = '.$dtb->quote($student_id).' AND session_id = '.$dtb->quote($session_id).'');
		$showPromotion = $searchPromotion->fetch();
		if (!empty($showPromotion)) {
			$grade_work_educ = $showPromotion['grade_work_educ'];
			$grade_remark_acad = $showPromotion['grade_remark_acad'];
			$grade_chapel_part = $showPromotion['grade_chapel_part'];
		}else{
			$grade_work_educ = "";
			$grade_remark_acad = "";
			$grade_chapel_part = "";
		}
 ?>

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Note de Work Education</td>
					<td class="text-left"><?=$grade_work_educ?></td>
				</tr>

				<?php /* « Remarque académique » retirée de l'affichage : le champ
				         grade_remark_acad vaut 0 sur les 5933 lignes de la base.
				         Le rétablir dès qu'il sera réellement utilisé. */ ?>

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Note de participation à l'exercice de chapelle et à la semaine de prière</td>
					<td class="text-left"><?=$grade_chapel_part?></td>
				</tr>
<?php 
	}
 ?>
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeure</th>
					<th class="px-2"><?php $moyenMajSem = ($tcreditMaj > 0) ? $tnotecreditMaj / $tcreditMaj : null; echo $moyenMajSem !== null ? round($moyenMajSem, 2) : '--'; ?></th>
				</tr>
				
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2 bg-cyan-700"><?php $moyenGenSem = ($tcreditGPA > 0) ? $tnotecredit / $tcreditGPA : null; echo $moyenGenSem !== null ? round($moyenGenSem, 2) : '--'; ?></th>
				</tr>

			</tfoot>

		</table>
<?php
	}

	// ---- Cumuls du GPA : on additionne crédits et points, jamais des moyennes ----
	if (!empty($grade_work_educ))   { $cumulWorkNote += $grade_work_educ;   $cumulWorkCount++; }
	if (!empty($grade_chapel_part)) { $cumulChapel   += $grade_chapel_part; $cumulChapelCount++; }
	$gpaCredits        += $tcreditGPA;
	$gpaNoteCredits    += $tnotecredit;
	$gpaCreditsMaj     += $tcreditMaj;
	$gpaNoteCreditsMaj += $tnotecreditMaj;
?>
	</div>
<?php
	}

	if($sessionCount == 0) {
		echo '<div class="text-center text-slate-400 py-8"><i class="bi bi-inbox text-4xl"></i><p class="mt-2">Aucun cours validé trouvé</p></div>';
	} else {
?>

<!-- MOYENNE CUMULATIVE — mêmes formules que l'export bulletin -->
<div class='p-1 <?=$bg_three_color?> hover:<?=$bg_four_color?> mb-4 rounded-md border-2 border-slate-600 hover:border-cyan-500 transition-all text-xs text-white'>
	<b>MOYENNE CUMULATIVE</b>
	<table class="simpleTbl mb-1 w-full">
		<tbody class="<?=$bg_two_color?>">
			<tr>
				<td class="p-1 w-8/12 text-right">Note de Work Education cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?= $cumulWorkCount > 0 ? round($cumulWorkNote / $cumulWorkCount, 2) : '--' ?></td>
			</tr>
			<tr>
				<td class="p-1 w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?= $cumulChapelCount > 0 ? round($cumulChapel / $cumulChapelCount, 2) : '--' ?></td>
			</tr>
		</tbody>
	</table>
	<table class="simpleTbl mb-1 w-full">
		<thead class="bg-slate-900">
			<tr>
				<th class="p-1 w-8/12 text-right">Moyenne Majeure Cumulative</th>
				<th class="py-1 px-2 w-2/12"><?= $gpaCreditsMaj > 0 ? round($gpaNoteCreditsMaj / $gpaCreditsMaj, 2) : '--' ?></th>
			</tr>
			<tr>
				<th class="p-1 w-8/12 text-right bg-cyan-700">Moyenne Générale Cumulative</th>
				<th class="py-1 px-2 w-2/12 bg-cyan-700 text-white"><?= $gpaCredits > 0 ? round($gpaNoteCredits / $gpaCredits, 2) : '--' ?></th>
			</tr>
		</thead>
	</table>
</div>

<?php
	}
?>
</div>

