<?php
	require('../init/.forPrint/top.forPrint.php'); 
	$level = isset($_GET['level']) ? $_GET['level'] : 'all';
	$semester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
	$student_id = $_GET['student_id'];
	$yes = 1;
	$printName = $student_id."-TRANSCRIPT_SESSION";

	$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');
	$stdA = $searchStd->fetch();
?>
<div class="mb-24">
	<center>
		<b class="text-2xl">Transcript par Session</b>
	</center>
	<div class="flex text-xs px-1 py-1" style="border: 1px solid #8e9bb2;">
		<div class="w-10/12 flex">
			<div class="text-right w-4/12">
				<label>Matricule - </label><br>
				<label>Noms - </label><br>
				<label>Mention - </label><br>
				<label>Parcours - </label><br>
				<label>Niveau - </label><br>
				<label>Mail / </label>
				<label>Contact - </label><br>
				<label>Adresse - </label>
			</div>
			<div class="w-8/12 pl-1">
				<b><?=$student_id?></b><br>
				<b><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></b><br>
				<b><?=$stdA['etude_envisage']?></b><br>
				<b><?=$stdA['etude_option']?></b><br>
				<b><?php 
					if($stdA['annee_etude'] <= 3) {
						echo "Licence ".$stdA['annee_etude'];
					} else {
						echo "Master ".($stdA['annee_etude'] - 3);
					}
				?></b><br>
				<b><?=$stdA['student_email']?></b>
				<b>/ <?=$stdA['student_tel']?></b><br>
				<b><?=$stdA['student_adresse']?></b>
			</div>
		</div>
		<div class="w-2/12">
			<img src="../app/photosetudiants/<?=$stdA['image_student']?>">
		</div>
	</div><br>

<?php
	$workNote = 0;
	$remarkAcad = 0;
	$chapel = 0;
	$gen = 0;
	$maj = 0;
	$finale = 0;

	$cumulWorkNote = 0;
	$cumulremarkAcad = 0;
	$cumulChapel = 0;
	$cumulGen = 0;
	$cumulMaj = 0;
	$cumulFinale = 0;
	$cumulCredit = 0;
	$cumulNoteCredit = 0;
	
	// Récapitulatif général
	$totalCoursValides = 0;
	$totalCoursEchoues = 0;
	$totalCreditsValides = 0;
	$totalCreditsEchoues = 0;
	$totalCours = 0;

	// Construire la requête avec filtres optionnels
	$sqlWhere = "WHERE n.student_id = '".$student_id."' AND n.ajout = '".$yes."'";
	
	// Filtre par niveau
	if ($level != 'all' && is_numeric($level)) {
		$sqlWhere .= " AND n.yearlevel = '".$level."'";
	}
	
	// Filtre par semestre de session
	if ($semester != 'all' && is_numeric($semester)) {
		$sqlWhere .= " AND s.session_semester = '".$semester."'";
	}

	// Récupérer toutes les sessions distinctes où l'étudiant a des notes
	$searchAllSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
		FROM t_2023_notes n 
		INNER JOIN t_2023_session s ON n.session_id = s.session_id 
		".$sqlWhere." 
		ORDER BY s.session_year ASC, s.session_semester ASC");

	$sessionCount = 0;
	
	while($showSs = $searchAllSessions->fetch()){
		$sessionCount++;
		$session_id = $showSs['session_id'];
		$combinAnual = $showSs['session_year'];
		
		// Récupérer le yearlevel depuis les notes de cette session
		$getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id='".$student_id."' AND session_id='".$session_id."' AND ajout='".$yes."' LIMIT 1");
		$ylData = $getYearlevel->fetch();
		$yearlevel = $ylData ? $ylData['yearlevel'] : 1;
		
		// Déterminer le niveau (Licence ou Master)
		if ($yearlevel <= 3) {
			$niveau_label = "Licence " . $yearlevel;
		} else {
			$niveau_label = "Master " . ($yearlevel - 3);
		}

		$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND session_id='".$session_id."' ORDER BY id");

		if ($cours->rowCount() > 0) {
			?>
			<table class="tbl mb-1" style="page-break-inside: avoid;">
				<thead>
					<tr class="text-center bg-slate-500 text-white">
						<th colspan="10"><b><?=$niveau_label?></b> | <?=$showSs['session_name']?> - Session N°<?=$showSs['session_semester']?> | Année <?=$combinAnual?></th>
					</tr>
				</thead>
				<thead class="bg-slate-200">
					<tr>
						<th style="width: 100px">Sigle</th>
						<th style="width: 400px">Titre du cours</th>
						<th style="width: 50px">Crédits</th>
						<th style="width: 50px">Catégorie</th>
						<th style="width: 50px">Notes/20</th>
						<th style="width: 50px">Crd*Not</th>
						<th style="width: 30px">État</th>
					</tr>
				</thead>	
			<?php
			$nbr = 0;
			$nbrMaj = 0;
			$tcredit = 0;
			$tcreditMaj = 0;
			$tnote = 0;
			$tnotecredit = 0;
			$tTMaj = 0;
			
			// Compteurs pour session
			$coursValides = 0;
			$coursEchoues = 0;
			$creditsValides = 0;
			$creditsEchoues = 0;
			
			while($crs = $cours->fetch()){
				$notecredi = $crs['credit'] * $crs['grade'];
				?>
				<tbody>
					<tr>
						<td><?=$crs['Sigle']?></td>
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
						<td><?=$crs['grade']?></td>
						<td><?=$notecredi?></td>
						<td class="text-center"><?php 
							if ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
								echo "S";
								$coursValides++;
								$creditsValides += $crs['credit'];
							}elseif($crs['grade'] < 10 and $crs['grade'] > 0){
								echo "E";
								$coursEchoues++;
								$creditsEchoues += $crs['credit'];
							}elseif ($crs['grade'] == 0){
								echo "";
							}
						?></td>					
					</tr>
				</tbody>
				<?php
				$tcredit += $crs['credit'];
				$tnote += $crs['grade'];
				$tnotecredit += $notecredi;
				
				// Calcul majeur (même logique que page web)
				if ($crs['cours_category'] == 1) {
					$tTMaj += $crs['grade'];
					$nbrMaj++;
				}
				
				$nbr++;
			}
			
			// Calcul des moyennes (même formule que page web)
			$moyenGenSem = ($tcredit > 0) ? round($tnotecredit / $tcredit, 2) : 0;
			$moyenMajSem = ($nbrMaj > 0) ? round($tTMaj / $nbrMaj, 2) : 0;
			
			// Ajout aux totaux généraux
			$totalCoursValides += $coursValides;
			$totalCoursEchoues += $coursEchoues;
			$totalCreditsValides += $creditsValides;
			$totalCreditsEchoues += $creditsEchoues;
			$totalCours += $nbr;
			?>
			<tfoot>
				<tr>
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?=$tcredit?></th>
					<th></th>
					<th class="px-2"><?=round($tnote, 2)?></th>
					<th><?=round($tnotecredit, 2)?></th>
					<th></th>
				</tr>
				<?php
				if(!empty($session_id)){
					$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');
					$showPromotion = $searchPromotion->fetch();
					$grade_work_educ = !empty($showPromotion) ? $showPromotion['grade_work_educ'] : "";
					$grade_remark_acad = !empty($showPromotion) ? $showPromotion['grade_remark_acad'] : "";
					$grade_chapel_part = !empty($showPromotion) ? $showPromotion['grade_chapel_part'] : "";
				?>
				<tr class="text-right">
					<td colspan="4">Note de Work Education</td>
					<td class="text-left"><?=$grade_work_educ?></td>
				</tr>
				<tr class="text-right">
					<td colspan="4">Remarque académique</td>
					<td class="text-left"><?=$grade_remark_acad?></td>
				</tr>
				<tr class="text-right">
					<td colspan="4">Note de participation à l'exercice de chapelle et à la semaine de prière</td>
					<td class="text-left"><?=$grade_chapel_part?></td>
				</tr>
				<?php 
				}
				?>
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeur</th>
					<th class="px-2"><?=$moyenMajSem?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2 bg-cyan-700 text-white"><?=$moyenGenSem?></th>
				</tr>
				<!-- Récapitulatif de la session -->
				<tr class="bg-slate-100">
					<th colspan="7" class="text-left text-xs p-1">
						<b>Récapitulatif :</b> 
						<?=$nbr?> cours | 
						<span class="text-green-700"><?=$coursValides?> validé(s) (<?=$creditsValides?> crédits)</span> | 
						<span class="text-red-700"><?=$coursEchoues?> échoué(s) (<?=$creditsEchoues?> crédits)</span> | 
						Total : <?=$tcredit?> crédits
					</th>
				</tr>
			</tfoot>
			</table>
			<?php
			// Cumuls
			if (!empty($grade_work_educ)) $cumulWorkNote += floatval($grade_work_educ);
			if (!empty($grade_chapel_part)) $cumulChapel += floatval($grade_chapel_part);
			$cumulMaj += $moyenMajSem;
			$cumulGen += $moyenGenSem;
			$cumulCredit += $tcredit;
			$cumulNoteCredit += $tnotecredit;
		}
	}
	
	if($sessionCount == 0) {
		echo '<div class="text-center py-8"><p>Aucune session trouvée pour cet étudiant.</p></div>';
	} else {
		// Moyenne cumulative
		$moyenneCumulative = ($cumulCredit > 0) ? round($cumulNoteCredit / $cumulCredit, 2) : 0;
		$tauxReussite = ($totalCours > 0) ? round(($totalCoursValides / $totalCours) * 100, 1) : 0;
?>

<!-- RÉCAPITULATIF GÉNÉRAL -->
<div class='p-2 mb-4 rounded-md text-xs' style="border: 2px solid #334155; background: #f1f5f9;">
	<b class="text-sm">RÉCAPITULATIF GÉNÉRAL</b>
	<table class="mb-1 w-full" style="page-break-inside: avoid;">
		<tbody>
			<tr>
				<td class="p-1 w-6/12">Nombre total de sessions</td>
				<td class="px-2 w-6/12 font-bold"><?=$sessionCount?></td>
			</tr>
			<tr>
				<td class="p-1 w-6/12">Nombre total de cours</td>
				<td class="px-2 w-6/12 font-bold"><?=$totalCours?></td>
			</tr>
			<tr>
				<td class="p-1 w-6/12 text-green-700">Cours validés (Succès)</td>
				<td class="px-2 w-6/12 font-bold text-green-700"><?=$totalCoursValides?> cours (<?=$totalCreditsValides?> crédits)</td>
			</tr>
			<tr>
				<td class="p-1 w-6/12 text-red-700">Cours échoués (Échec)</td>
				<td class="px-2 w-6/12 font-bold text-red-700"><?=$totalCoursEchoues?> cours (<?=$totalCreditsEchoues?> crédits)</td>
			</tr>
			<tr>
				<td class="p-1 w-6/12">Total des crédits</td>
				<td class="px-2 w-6/12 font-bold"><?=$cumulCredit?> crédits</td>
			</tr>
			<tr>
				<td class="p-1 w-6/12">Taux de réussite</td>
				<td class="px-2 w-6/12 font-bold <?=($tauxReussite >= 50) ? 'text-green-700' : 'text-red-700'?>"><?=$tauxReussite?>%</td>
			</tr>
		</tbody>
	</table>
</div>

<div class='p-1 mb-4 rounded-md border-1 border-slate-600 text-xs'>
	<b>MOYENNE CUMULATIVE</b>
	<table class="mb-1 w-full" style="page-break-inside: avoid;">
		<tbody>
			<tr>
				<td class="text-xs p-1 w-8/12 text-right">Note de Work Education cumulative</td>
				<td class="text-xs px-2 w-2/12 text-bold"><?=($sessionCount > 0) ? round($cumulWorkNote / $sessionCount, 2) : 0?></td>
			</tr>
			<tr>
				<td class="text-xs p-1 w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class="text-xs px-2 w-2/12 text-bold"><?=($sessionCount > 0) ? round($cumulChapel / $sessionCount, 2) : 0?></td>
			</tr>
		</tbody>
	</table>
	<table class="mb-1 w-full" style="page-break-inside: avoid;">
		<thead>
			<tr>
				<th class="text-xs p-1 w-8/12 text-right">Moyenne Majeur Cumulative</th>
				<th class="text-xs py-1 px-2 w-2/12"><?=($sessionCount > 0) ? round($cumulMaj / $sessionCount, 2) : 0?></th>
			</tr>
			<tr>
				<th class="text-xs p-1 w-8/12 text-right bg-cyan-700">Moyenne Générale Cumulative</th>
				<th class="text-xs py-1 px-2 w-2/12 bg-cyan-700 text-white"><?=$moyenneCumulative?></th>
			</tr>
		</thead>
	</table>
</div>
<?php
	}
?>
</div>
<?php require('../init/.forPrint/foot.forPrint.php'); ?>