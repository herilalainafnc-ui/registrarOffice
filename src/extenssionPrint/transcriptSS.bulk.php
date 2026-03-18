<?php
/**
 * Export groupé : Relevé semestriel des notes pour une liste d'étudiants filtrés
 * Paramètre POST : session_id, student_ids[] (liste filtrée depuis session-rankings)
 */
$bulk_session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : (isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0);
$posted_ids      = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];
$printName = 'RELEVES_SESSION_' . $bulk_session_id;
$yes = 1;

if (!$bulk_session_id) {
	echo '<p class="text-center py-8">Session non spécifiée.</p>';
	return;
}

// Info de la session (pour le titre)
$stmtSess = $dtb->prepare("SELECT * FROM t_2023_session WHERE session_id = :sid");
$stmtSess->execute(['sid' => $bulk_session_id]);
$sessionInfo = $stmtSess->fetch();
$sessionLabel = $sessionInfo
	? htmlspecialchars($sessionInfo['session_name']).' — S'.(int)$sessionInfo['session_semester'].' / '.htmlspecialchars($sessionInfo['session_year'])
	: 'Session #'.$bulk_session_id;

// Récupérer les étudiants : utiliser la liste POST si fournie, sinon tous dans la session
if (!empty($posted_ids)) {
	// Sécuriser les IDs (chaque ID est une chaîne matricule, pas int)
	$safePlaceholders = implode(',', array_fill(0, count($posted_ids), '?'));
	$stmtStudents = $dtb->prepare("
		SELECT * FROM tbl_2024_etudiant
		WHERE student_id IN ($safePlaceholders)
		ORDER BY student_nom, student_prenom
	");
	$stmtStudents->execute(array_values($posted_ids));
} else {
	$stmtStudents = $dtb->prepare("
		SELECT DISTINCT e.*
		FROM t_2023_notes n
		INNER JOIN tbl_2024_etudiant e ON n.student_id = e.student_id
		WHERE n.session_id = :sid AND n.ajout = 1
		ORDER BY e.student_nom, e.student_prenom
	");
	$stmtStudents->execute(['sid' => $bulk_session_id]);
}
$allStudents = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
$studentTotal = count($allStudents);

if ($studentTotal === 0) {
	echo '<p class="text-center py-8">Aucun étudiant trouvé pour cette session.</p>';
	return;
}

$studentIdx = 0;

foreach ($allStudents as $stdA) :
	$studentIdx++;
	$student_id = $stdA['student_id'];
	$isLastStudent = ($studentIdx === $studentTotal);
?>
<div class="mb-4" style="<?= !$isLastStudent ? 'page-break-after: always;' : '' ?>">

	<?php require('../init/.forPrint/top.forPrint.php'); ?>

	<center><b class="text-lg">Relevé semestriel des notes</b></center>
	<p class="text-center text-[10px] text-slate-500 mb-1"><?= $sessionLabel ?></p>

	<!-- En-tête étudiant -->
	<div class="flex text-[10px] px-1 py-0.5" style="border: 1px solid #8e9bb2;">
		<div class="w-10/12 flex">
			<div class="text-right w-4/12">
				<label>Matricule - </label><br>
				<label>Noms - </label><br>
				<label>Mention - </label><br>
				<label>Parcours - </label><br>
				<label>Niveau - </label><br>
				<label>Mail / Contact - </label><br>
				<label>Adresse - </label>
			</div>
			<div class="w-8/12 pl-1">
				<b><?= htmlspecialchars($student_id) ?></b><br>
				<b><?= htmlspecialchars(strtoupper($stdA['student_nom']).' '.$stdA['student_prenom']) ?></b><br>
				<b><?= htmlspecialchars($stdA['etude_envisage'] ?? '') ?></b><br>
				<b><?= htmlspecialchars($stdA['etude_option'] ?? '') ?></b><br>
				<b><?php echo ($stdA['annee_etude'] <= 3) ? 'Licence '.(int)$stdA['annee_etude'] : 'Master '.((int)$stdA['annee_etude'] - 3); ?></b><br>
				<b><?= htmlspecialchars($stdA['student_email'] ?? '') ?> / <?= htmlspecialchars($stdA['student_tel'] ?? '') ?></b><br>
				<b><?= htmlspecialchars($stdA['student_adresse'] ?? '') ?></b>
			</div>
		</div>
		<div class="w-2/12">
			<img src="../app/photosetudiants/<?= htmlspecialchars($stdA['image_student'] ?? '') ?>">
		</div>
	</div><br>

	<?php
	// ── Compteurs cumulatifs pour cet étudiant ──────────────────────────
	$cumulWorkNote   = 0; $cumulChapel    = 0;
	$cumulGen        = 0; $cumulMaj       = 0;
	$cumulGenCount   = 0; $cumulMajCount  = 0;
	$cumulCredit     = 0; $cumulNoteCredit = 0;

	$totalCoursValides    = 0; $totalCreditsValides    = 0;
	$totalCoursEchoues    = 0; $totalCreditsEchoues    = 0;
	$totalCoursIncomplete = 0; $totalCreditsIncomplete = 0;
	$totalCours = 0;
	$sessionCount = 0;

	$searchAllSessions = $dtb->query("
		SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year
		FROM t_2023_notes n
		INNER JOIN t_2023_session s ON n.session_id = s.session_id
		WHERE n.student_id = '".addslashes($student_id)."' AND n.ajout = '".$yes."'
		  AND n.session_id = '".$bulk_session_id."'
		ORDER BY s.session_year ASC, s.session_semester ASC
	");

	while ($showSs = $searchAllSessions->fetch()) :
		$sessionCount++;
		$session_id   = $showSs['session_id'];
		$combinAnual  = $showSs['session_year'];

		$getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id='".addslashes($student_id)."' AND session_id='".(int)$session_id."' AND ajout='".$yes."' LIMIT 1");
		$ylData       = $getYearlevel->fetch();
		$yearlevel    = $ylData ? $ylData['yearlevel'] : 1;
		$niveau_label = ($yearlevel <= 3) ? 'Licence '.$yearlevel : 'Master '.($yearlevel - 3);

		$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id='".addslashes($student_id)."' AND ajout='".$yes."' AND session_id='".(int)$session_id."' ORDER BY id");

		if ($cours->rowCount() > 0) :
	?>
	<table class="tbl mb-0.5 text-[10px]" style="page-break-inside: avoid;">
		<thead>
			<tr class="text-center bg-slate-500 text-white text-[9px]">
				<th colspan="7" class="py-0.5"><b><?= $niveau_label ?></b> | <?= htmlspecialchars($showSs['session_name']) ?> - Session N°<?= (int)$showSs['session_semester'] ?> | Année <?= htmlspecialchars($combinAnual) ?></th>
			</tr>
		</thead>
		<thead class="bg-slate-200">
			<tr>
				<th style="width: 70px">Sigle</th>
				<th style="width: 250px">Titre du cours</th>
				<th style="width: 35px">Crd</th>
				<th style="width: 45px">Cat.</th>
				<th style="width: 35px">Note</th>
				<th style="width: 40px">Crd*N</th>
				<th style="width: 40px">État</th>
			</tr>
		</thead>
		<?php
		$nbr = 0; $nbrMaj = 0;
		$tcredit = 0; $tcreditGPA = 0; $tnote = 0; $tnotecredit = 0; $tTMaj = 0;
		$coursValides = 0; $coursEchoues = 0; $coursIncomplete = 0;
		$creditsValides = 0; $creditsEchoues = 0; $creditsIncomplete = 0;

		while ($crs = $cours->fetch()) :
			$isIncomplete = ($crs['grade'] == 0);
			$isPassFail   = ($crs['cours_category'] == 5)
				|| stripos($crs['Sigle']       ?? '', 'RELP 291')            !== false
				|| stripos($crs['title_cours'] ?? '', 'formation spirituelle') !== false;
			$notecredi = ($isIncomplete || $isPassFail) ? 0 : $crs['credit'] * $crs['grade'];
		?>
		<tbody>
			<tr>
				<td><?= htmlspecialchars($crs['Sigle']) ?></td>
				<td><?= htmlspecialchars($crs['title_cours']) ?></td>
				<td><?= (int)$crs['credit'] ?></td>
				<td><?php
					if ($isPassFail)                                              echo '``';
					elseif ($crs['cours_category'] == 0)                          echo 'Général';
					elseif ($crs['cours_category'] == 1)                          echo 'Majeur';
					elseif ($crs['cours_category'] == -1 || $crs['cours_category'] == 2) echo 'Sélective';
					elseif ($crs['cours_category'] == 3)                          echo 'Additionnel';
					else                                                          echo '-';
				?></td>
				<td><?php
					if ($isPassFail)        { echo ($crs['grade'] == -2 || $crs['grade'] >= 10) ? '<span style="color:#15803d;font-weight:bold;">V</span>' : '<span style="color:#b91c1c;font-weight:bold;">E</span>'; }
					elseif ($isIncomplete)  { echo '<span style="color:#b91c1c;font-style:italic;">--</span>'; }
					else                   { echo $crs['grade']; }
				?></td>
				<td><?php
					if ($isPassFail || $isIncomplete) echo '--';
					else echo $notecredi;
				?></td>
				<td class="text-center"><?php
					if ($isPassFail) {
						if    ($crs['grade'] == -2 || $crs['grade'] >= 10) { echo '<span style="color:#15803d;font-weight:bold;">Validé</span>';   $coursValides++;  $creditsValides  += $crs['credit']; }
						elseif($crs['grade'] > 0)                          { echo '<span style="color:#b91c1c;font-weight:bold;">Échec</span>';    $coursEchoues++;  $creditsEchoues  += $crs['credit']; }
						elseif($isIncomplete)                              { echo '<span style="color:#d97706;font-weight:bold;">Incomplet</span>'; $coursIncomplete++;$creditsIncomplete+=$crs['credit']; }
					} elseif ($crs['grade'] == -2 || $crs['grade'] >= 10) { echo '<span style="color:#15803d;font-weight:bold;">Validé</span>';   $coursValides++;  $creditsValides  += $crs['credit']; }
					elseif  ($crs['grade'] < 10 && $crs['grade'] > 0)     { echo '<span style="color:#b91c1c;font-weight:bold;">Échec</span>';    $coursEchoues++;  $creditsEchoues  += $crs['credit']; }
					elseif  ($isIncomplete)                                { echo '<span style="color:#d97706;font-weight:bold;">Incomplet</span>'; $coursIncomplete++;$creditsIncomplete+=$crs['credit']; }
				?></td>
			</tr>
		</tbody>
		<?php
			$tcredit += $crs['credit'];
			if (!$isPassFail) { $tcreditGPA += $crs['credit']; $tnote += $crs['grade']; $tnotecredit += $notecredi; }
			if ($crs['cours_category'] == 1) { $tTMaj += $crs['grade']; $nbrMaj++; }
			$nbr++;
		endwhile; // cours

		$moyenGenSem = ($tcreditGPA > 0) ? round($tnotecredit / $tcreditGPA, 2) : null;
		$moyenMajSem = ($nbrMaj > 0)     ? round($tTMaj / $nbrMaj, 2)           : null;

		$totalCoursValides    += $coursValides;    $totalCreditsValides    += $creditsValides;
		$totalCoursEchoues    += $coursEchoues;    $totalCreditsEchoues    += $creditsEchoues;
		$totalCoursIncomplete += $coursIncomplete; $totalCreditsIncomplete += $creditsIncomplete;
		$totalCours += $nbr;
		?>
		<tfoot>
			<tr>
				<th colspan="2"><?= $nbr ?> cours</th>
				<th><?= $tcredit ?></th>
				<th></th>
				<th class="px-2"><?= round($tnote, 2) ?></th>
				<th><?= round($tnotecredit, 2) ?></th>
				<th></th>
			</tr>
			<?php
			$grade_work_educ = ''; $grade_chapel_part = '';
			if (!empty($session_id)) {
				$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.addslashes($student_id).'" AND session_id = "'.(int)$session_id.'"');
				$showPromotion   = $searchPromotion->fetch();
				$grade_work_educ   = !empty($showPromotion) ? $showPromotion['grade_work_educ']   : '';
				$grade_chapel_part = !empty($showPromotion) ? $showPromotion['grade_chapel_part'] : '';
			?>
			<tr class="text-right">
				<td colspan="5">Note de Work Education</td>
				<td colspan="2" class="text-left"><?= htmlspecialchars($grade_work_educ) ?></td>
			</tr>
			<tr class="text-right">
				<td colspan="5">Participation chapelle / semaine de prière</td>
				<td colspan="2" class="text-left"><?= htmlspecialchars($grade_chapel_part) ?></td>
			</tr>
			<?php } ?>
			<tr>
				<th colspan="5" class="text-right">Moyenne Majeure</th>
				<th colspan="2" class="px-2"><?= $moyenMajSem !== null ? $moyenMajSem : '--' ?></th>
			</tr>
			<tr>
				<th colspan="5" class="text-right">Moyenne Générale</th>
				<th colspan="2" class="px-2 bg-cyan-700 text-white"><?= $moyenGenSem !== null ? $moyenGenSem : '--' ?></th>
			</tr>
			<tr class="bg-slate-100">
				<th colspan="7" class="text-left text-[8px] p-0.5">
					<b>Récapitulatif :</b> <?= $nbr ?> cours |
					<span style="color:#15803d;"><?= $coursValides ?> validé(s) (<?= $creditsValides ?> crédits)</span> |
					<span style="color:#b91c1c;"><?= $coursEchoues ?> échoué(s) (<?= $creditsEchoues ?> crédits)</span>
					<?php if ($coursIncomplete > 0): ?> | <span style="color:#b45309;"><?= $coursIncomplete ?> incomplet(s) (<?= $creditsIncomplete ?> crédits)</span><?php endif; ?>
					| Total : <?= $tcredit ?> crédits
				</th>
			</tr>
		</tfoot>
	</table>
	<?php
		if (!empty($grade_work_educ))   $cumulWorkNote += floatval($grade_work_educ);
		if (!empty($grade_chapel_part)) $cumulChapel   += floatval($grade_chapel_part);
		if ($moyenGenSem !== null) { $cumulGen += $moyenGenSem; $cumulGenCount++; }
		if ($moyenMajSem !== null) { $cumulMaj += $moyenMajSem; $cumulMajCount++; }
		$cumulCredit     += $tcredit;
		$cumulNoteCredit += $tnotecredit;
	endif; // rowCount > 0
	endwhile; // sessions

	if ($sessionCount === 0) {
		echo '<p class="text-center text-[10px]">Aucune note trouvée pour cet étudiant.</p>';
	} else {
		$moyenneCumulative = ($cumulGenCount > 0) ? round($cumulGen / $cumulGenCount, 2)  : 0;
		$tauxReussite      = ($totalCours > 0)    ? round(($totalCoursValides / $totalCours) * 100, 1) : 0;
	?>
	<!-- Récapitulatif général -->
	<div class="p-1 mb-1 rounded-md text-[9px]" style="border: 1px solid #334155; background: #f1f5f9; page-break-inside: avoid;">
		<b class="text-[10px]">RÉCAPITULATIF GÉNÉRAL</b>
		<table class="mb-1 w-full">
			<tbody>
				<tr><td class="text-xs p-0.5 w-6/12">Sessions</td><td class="text-xs px-2 w-6/12 font-bold"><?= $sessionCount ?></td></tr>
				<tr><td class="text-xs p-0.5 w-6/12">Total cours</td><td class="text-xs px-2 font-bold"><?= $totalCours ?></td></tr>
				<tr><td class="text-xs p-0.5 text-green-700">Cours validés</td><td class="text-xs px-2 font-bold text-green-700"><?= $totalCoursValides ?> (<?= $totalCreditsValides ?> crédits)</td></tr>
				<tr><td class="text-xs p-0.5 text-red-700">Cours échoués</td><td class="text-xs px-2 font-bold text-red-700"><?= $totalCoursEchoues ?> (<?= $totalCreditsEchoues ?> crédits)</td></tr>
				<?php if ($totalCoursIncomplete > 0): ?>
				<tr><td class="text-xs p-0.5" style="color:#b45309;">Incomplets</td><td class="text-xs px-2 font-bold" style="color:#b45309;"><?= $totalCoursIncomplete ?> (<?= $totalCreditsIncomplete ?> crédits)</td></tr>
				<?php endif; ?>
				<tr><td class="text-xs p-0.5">Taux de réussite</td><td class="text-xs px-2 font-bold <?= ($tauxReussite >= 50) ? 'text-green-700' : 'text-red-700' ?>"><?= $tauxReussite ?>%</td></tr>
			</tbody>
		</table>
	</div>
	<!-- Moyennes cumulatives -->
	<div class="p-1 mb-1 rounded-md text-[9px]" style="page-break-inside: avoid;">
		<b class="text-[10px]">MOYENNE CUMULATIVE</b>
		<table class="mb-1 w-full">
			<thead>
				<tr>
					<th class="text-xs p-0.5 w-8/12 text-right">Moyenne Majeure Cumulative</th>
					<th class="text-xs py-0.5 px-2 w-2/12"><?= ($cumulMajCount > 0) ? round($cumulMaj / $cumulMajCount, 2) : '--' ?></th>
				</tr>
				<tr>
					<th class="text-xs p-0.5 w-8/12 text-right bg-cyan-700">Moyenne Générale Cumulative</th>
					<th class="text-xs py-0.5 px-2 w-2/12 bg-cyan-700 text-white"><?= $moyenneCumulative ?></th>
				</tr>
			</thead>
		</table>
	</div>
	<?php } ?>

	<?php require('../init/.forPrint/foot.forPrint.php'); ?>
</div>
<?php endforeach; // students ?>
