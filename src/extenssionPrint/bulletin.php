
<?php
require('../init/.forPrint/top.forPrint.php');  
require_once(__DIR__ . '/../services/DocumentVerification.php');

$level = isset($_GET['level']) ? $_GET['level'] : 'all';
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$lang = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : 'fr';
if (!in_array($lang, ['fr', 'en'], true)) {
	$lang = 'fr';
}
$student_id = $_GET['student_id'];
$yes = 1;
// Notation : 'us' (americaine, lettres A-F) ou 'eu' (europeenne, /20).
// Par defaut : francais -> europeenne, anglais -> americaine (bulletin academique, §412).
$notation = isset($_GET['notation']) ? strtolower(trim((string)$_GET['notation'])) : '';
if (!in_array($notation, ['us', 'eu'], true)) {
	$notation = ($lang === 'en') ? 'us' : 'eu';
}

/**
 * Convertit une note europeenne (/20) en lettre americaine (bareme UAZ, §413).
 * % = note x 5. Les valeurs non numeriques (V, E...) sont laissees telles quelles.
 */
function gradeToLetterUS($g) {
	if (!is_numeric($g)) { return $g; }
	$g = (float)$g;
	if ($g >= 18) return 'A+';
	if ($g >= 17) return 'A';
	if ($g >= 16) return 'A-';
	if ($g >= 15) return 'B+';
	if ($g >= 14) return 'B';
	if ($g >= 13) return 'B-';
	if ($g >= 12) return 'C+';
	if ($g >= 11) return 'C';
	if ($g >= 10) return 'D';
	return 'F';
}

// Formate une note selon la notation choisie
$fmtGrade = function($g) use ($notation) {
	return ($notation === 'us') ? gradeToLetterUS($g) : $g;
};

$printName = $student_id."-BULLETIN-".strtoupper($lang)."-".strtoupper($notation);

$tr = [
	'fr' => [
		'report_title' => 'Releve de notes',
		'student_id' => 'Matricule',
		'names' => 'Noms',
		'mention' => 'Mention',
		'track' => 'Parcours',
		'level' => 'Niveau',
		'mail_contact' => 'Mail / Contact',
		'address' => 'Adresse',
		'licence' => 'Licence',
		'master' => 'Master',
		'title' => 'Titre',
		'cat_general' => 'General',
		'cat_major' => 'Majeur',
		'cat_selective' => 'Selective',
		'cat_additional' => 'Additionnel',
		'courses_validated' => 'cours valides',
		'work_education' => 'Note de Work Education',
		'academic_remark' => 'Remarque academique',
		'chapel_note' => 'Note de participation a l\'exercice de chapelle et a la semaine de priere',
		'major_avg' => 'Moyenne Majeure',
		'general_avg' => 'Moyenne Generale',
		'no_course' => 'Aucun cours valide trouve pour cet etudiant.',
		'general_summary' => 'RECAPITULATIF GENERAL',
		'total_sessions' => 'Nombre total de sessions',
		'total_valid_courses' => 'Nombre total de cours valides',
		'total_valid_credits' => 'Total des credits valides',
		'courses' => 'cours',
		'credits' => 'credits',
		'cumulative_avg' => 'MOYENNE CUMULATIVE',
		'cumulative_work' => 'Note de Work Education cumulative',
		'cumulative_chapel' => 'Note de participation a l\'exercice de chapelle et a la semaine de priere cumulative',
		'cumulative_major' => 'Moyenne Majeure Cumulative',
		'cumulative_general' => 'Moyenne Generale Cumulative',
		'state_short' => 'E',
	],
	'en' => [
		'report_title' => 'Grade Report',
		'student_id' => 'Student ID',
		'names' => 'Full Name',
		'mention' => 'Program',
		'track' => 'Track',
		'level' => 'Level',
		'mail_contact' => 'Email / Contact',
		'address' => 'Address',
		'licence' => 'Bachelor',
		'master' => 'Master',
		'title' => 'Course Title',
		'cat_general' => 'General',
		'cat_major' => 'Major',
		'cat_selective' => 'Selective',
		'cat_additional' => 'Additional',
		'courses_validated' => 'validated courses',
		'work_education' => 'Work Education Grade',
		'academic_remark' => 'Academic Remark',
		'chapel_note' => 'Chapel Exercise and Prayer Week Participation Grade',
		'major_avg' => 'Major Average',
		'general_avg' => 'General Average',
		'no_course' => 'No validated course found for this student.',
		'general_summary' => 'GENERAL SUMMARY',
		'total_sessions' => 'Total sessions',
		'total_valid_courses' => 'Total validated courses',
		'total_valid_credits' => 'Total validated credits',
		'courses' => 'courses',
		'credits' => 'credits',
		'cumulative_avg' => 'CUMULATIVE AVERAGE',
		'cumulative_work' => 'Cumulative Work Education Grade',
		'cumulative_chapel' => 'Cumulative Chapel and Prayer Week Participation Grade',
		'cumulative_major' => 'Cumulative Major Average',
		'cumulative_general' => 'Cumulative General Average',
		'state_short' => 'St',
	],
];

$L = function($k) use ($tr, $lang) {
	return $tr[$lang][$k] ?? $k;
};

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = '.$dtb->quote($student_id).' ORDER BY remove ASC, id ASC LIMIT 1');
$stdA = $searchStd->fetch();

// Système anti-contrefaçon - Génération du code de vérification
$docVerification = new DocumentVerification($dtb);
$studentName = strtoupper($stdA['student_nom']) . " " . $stdA['student_prenom'];
$verificationData = $docVerification->getOrCreateBulletinVerification($student_id, $studentName, $level, $semester);
?>

<div class="mb-2">
<center>
	<b class="text-sm"><?=$L('report_title')?></b>
</center>

<div class="flex text-[10px] px-1 py-0.5" style="border: 1px solid #8e9bb2;">
    <div class="w-10/12 flex">
        <div class="text-right w-4/12">
			<label><?=$L('student_id')?> - </label><br>
			<label><?=$L('names')?> - </label><br>
			<label><?=$L('mention')?> - </label><br>
			<label><?=$L('track')?> - </label><br>
			<label><?=$L('level')?> - </label><br>
			<label><?=$L('mail_contact')?> - </label><br>
			<label><?=$L('address')?> - </label>
        </div>
        <div class="w-8/12 pl-1">
            <b><?=$student_id?></b><br>
            <b><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></b><br>
            <b><?=$stdA['etude_envisage']?></b><br>
            <b><?=$stdA['etude_option']?></b><br>
            <b><?php 
                if($stdA['annee_etude'] <= 3) {
					echo $L('licence')." ".$stdA['annee_etude'];
                } else {
					echo $L('master')." ".($stdA['annee_etude'] - 3);
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
</div>

<?php
	// Construire la requête avec filtres optionnels
	$sqlWhere = "WHERE n.student_id = ".$dtb->quote($student_id)." AND n.ajout = ".$dtb->quote($yes)." AND (n.grade >= 10 OR n.grade = -2)";
	
	// Filtre par niveau
	if ($level != 'all' && is_numeric($level)) {
		$sqlWhere .= " AND n.yearlevel = ".$dtb->quote($level)."";
	}
	
	// Filtre par semestre de session
	if ($semester != 'all' && is_numeric($semester)) {
		$sqlWhere .= " AND s.session_semester = ".$dtb->quote($semester)."";
	}

	// Récupérer toutes les sessions distinctes où l'étudiant a des notes validées (>=10)
	$searchAllSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
		FROM t_2023_notes n 
		INNER JOIN t_2023_session s ON n.session_id = s.session_id 
		".$sqlWhere." 
		ORDER BY s.session_year ASC, s.session_semester ASC");

	$sessionCount = 0;
	$cumulCredit = 0;
	$cumulNoteCredit = 0;
	$cumulMaj = 0;
	$cumulMajCount = 0;
	// GPA pondere par les credits (formule officielle, systeme LMD)
	$gpaCredits = 0;        $gpaNoteCredits = 0;
	$gpaCreditsMaj = 0;     $gpaNoteCreditsMaj = 0;
	$cumulWorkCount = 0;    $cumulChapelCount = 0;
	$cumulWorkNote = 0;
	$cumulChapel = 0;
	
	// Récapitulatif général
	$totalCoursValides = 0;
	$totalCreditsValides = 0;
	
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
			$niveau_label = $L('licence') . " " . $yearlevel;
		} else {
			$niveau_label = $L('master') . " " . ($yearlevel - 3);
		}

		// n.title_english (denormalise) en priorite ; sinon repli sur le cours maitre
		// Toutes les colonnes du WHERE sont prefixees « n. » : t_2023_cours
		// partage 11 noms de colonnes avec t_2023_notes (ajout, semester,
		// yearlevel, title_english...), qui seraient sinon ambigus.
		$cours = $dtb->query("SELECT n.*, c.title_english AS cours_title_en FROM t_2023_notes n LEFT JOIN t_2023_cours c ON c.id = n.id_cours WHERE n.student_id =".$dtb->quote($student_id)." AND n.ajout=".$dtb->quote($yes)." AND n.session_id=".$dtb->quote($session_id)." AND (n.grade >= 10 OR n.grade = -2) ORDER BY n.id");

		if ($cours->rowCount() > 0) {
			?>
			<table class="tbl mb-0 text-[10px]" style="page-break-inside: avoid;">
				<thead>
					<tr class="text-center bg-slate-500 text-white text-[10px]">
						<th colspan="10" class="py-0"> <?=$showSs['session_name']?> | <?=$combinAnual?></th>
					</tr>
					<!-- <b><?=$niveau_label?></b> | -->
					 <!-- - N°<?=$showSs['session_semester']?>   -->
				</thead>
				<thead class="bg-slate-200">
					<tr>
						<th style="width: 55px">Sigle</th>
						<th style="width: 220px"><?=$L('title')?></th>
						<th style="width: 25px">Cr</th>
						<th style="width: 30px">Cat</th>
						<th style="width: 25px">N</th>
						<th style="width: 30px">C*N</th>
						<th style="width: 15px"><?=$L('state_short')?></th>
					</tr>
				</thead>	
			<?php
			$nbr = 0;
			$nbrMaj = 0;
			$tcredit = 0;
			$tnote = 0;
			$tnotecredit = 0;
			$tTMaj = 0;
			$tcreditGPA = 0;
			$tcreditMaj = 0;
			$tnotecreditMaj = 0;
			
				while($crs = $cours->fetch()){
					// Cours valide sans note numerique : affiche "V", exclu de la moyenne
					$gradeValue = (float)$crs['grade'];
					$isValidationCourse = ($crs['cours_category'] == 5)
						|| stripos((string)($crs['Sigle'] ?? ''), 'RELP 291') !== false
						|| stripos((string)($crs['title_cours'] ?? ''), 'formation spirituelle') !== false;
					$isStatusOnlyV = ($gradeValue == -2) || $isValidationCourse;
					$notecredi = $isStatusOnlyV ? 0 : $crs['credit'] * $gradeValue;

					// Titre du cours : en anglais si demande et disponible
					$displayTitle = $crs['title_cours'];
					if ($lang === 'en') {
						if (!empty($crs['title_english']))      { $displayTitle = $crs['title_english']; }
						elseif (!empty($crs['cours_title_en'])) { $displayTitle = $crs['cours_title_en']; }
					}
				?>
				<tbody>
					<tr>
						<td><?=$crs['Sigle']?></td>
						<td><?=$displayTitle?></td>
						<td><?=$crs['credit']?></td>
						<td><?php 
							if ($crs['cours_category'] == 0){
								echo $L('cat_general');
							}elseif ($crs['cours_category'] == 1) {
								echo $L('cat_major');
							}elseif ($crs['cours_category'] == -1 OR $crs['cours_category'] == 2) {
								echo $L('cat_selective');
							}elseif ($crs['cours_category'] == 3) {
								echo $L('cat_additional');
							}elseif ($crs['cours_category'] == 5) {
								echo "``";
							}else{
								echo "-";
							}
						?></td>
						<td><?=$isStatusOnlyV ? 'V' : $fmtGrade($crs['grade'])?></td>
						<td><?=$isStatusOnlyV ? '--' : $notecredi?></td>
						<td class="text-center bg-green-200">V</td>					
					</tr>
				</tbody>
				<?php
				$tcredit += $crs['credit'];   // credits valides (inclut les cours "V")
				if (!$isStatusOnlyV) {
					$tcreditGPA  += $crs['credit'];
					$tnote       += $gradeValue;
					$tnotecredit += $notecredi;

					// GPA majeur : credits et points
					if ($crs['cours_category'] == 1) {
						$tTMaj          += $gradeValue;
						$nbrMaj++;
						$tcreditMaj     += $crs['credit'];
						$tnotecreditMaj += $crs['credit'] * $gradeValue;
					}
				}
				
				$nbr++;
			}
			
			// Calcul des moyennes (même formule que page web)
			// null quand rien n'est calculable : la session ne doit alors pas peser
			$moyenGenSem = ($tcreditGPA > 0) ? $tnotecredit / $tcreditGPA : null;
			$moyenMajSem = ($tcreditMaj > 0) ? $tnotecreditMaj / $tcreditMaj : null;
			
			// Ajout aux totaux généraux
			$totalCoursValides += $nbr;
			$totalCreditsValides += $tcredit;
			?>
			<tfoot>
				<tr class="bg-slate-200">
					<th colspan="2"><?=$nbr?> <?=$L('courses_validated')?></th>
					<th><?=$tcredit?></th>
					<th></th>
					<th class="px-2"><?=round($tnote, 2)?></th>
					<th><?=round($tnotecredit, 2)?></th>
					<th></th>
				</tr>
				<?php
				if(!empty($session_id)){
					$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = '.$dtb->quote($student_id).' AND session_id = '.$dtb->quote($session_id).'');
					$showPromotion = $searchPromotion->fetch();
					$grade_work_educ = !empty($showPromotion) ? $showPromotion['grade_work_educ'] : "";
					$grade_remark_acad = !empty($showPromotion) ? $showPromotion['grade_remark_acad'] : "";
					$grade_chapel_part = !empty($showPromotion) ? $showPromotion['grade_chapel_part'] : "";
				?>
				<tr class="text-right">
					<td colspan="4"><?=$L('work_education')?></td>
					<td class="text-left"><?=$grade_work_educ?></td>
				</tr>
				<tr class="text-right">
					<td colspan="4"><?=$L('chapel_note')?></td>
					<td class="text-left"><?=$grade_chapel_part?></td>
				</tr>
				<?php 
				}
				?>
				<tr>
					<th colspan="4" class="text-right"><?=$L('major_avg')?></th>
					<th class="px-2 bg-slate-200"><?= $moyenMajSem !== null ? $fmtGrade(round($moyenMajSem, 2)) : "--" ?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right"><?=$L('general_avg')?></th>
					<th class="px-2 bg-cyan-700 text-white"><?= $moyenGenSem !== null ? $fmtGrade(round($moyenGenSem, 2)) : "--" ?></th>
				</tr>
			</tfoot>
			</table>
			<br>
			<?php
			// Cumuls
			// Compteurs independants : un semestre non renseigne ne dilue plus
			if (!empty($grade_work_educ))   { $cumulWorkNote += floatval($grade_work_educ);   $cumulWorkCount++; }
			if (!empty($grade_chapel_part)) { $cumulChapel   += floatval($grade_chapel_part); $cumulChapelCount++; }
			// Cumuls du GPA : credits et points, jamais des moyennes
			$gpaCredits        += $tcreditGPA;
			$gpaNoteCredits    += $tnotecredit;
			$gpaCreditsMaj     += $tcreditMaj;
			$gpaNoteCreditsMaj += $tnotecreditMaj;
			$cumulCredit     += $tcreditGPA;
			$cumulNoteCredit += $tnotecredit;
		}
	}
	
	if($sessionCount == 0) {
		echo '<div class="text-center py-8"><p>'.$L('no_course').'</p></div>';
	} else {
		// Moyenne cumulative
		// GPA pondere par les credits — identique a la page bulletin
		$moyenneCumulative    = ($gpaCredits > 0)    ? round($gpaNoteCredits / $gpaCredits, 2)       : 0;
		$moyenneMajCumulative = ($gpaCreditsMaj > 0) ? round($gpaNoteCreditsMaj / $gpaCreditsMaj, 2) : 0;
?>

<!-- RÉCAPITULATIF GÉNÉRAL -->
<div class='p-1 border border-slate-700 mb-1 rounded-md text-[11px]' style="background: #f1f5f9;">
	<b class="text-[10px] pb-0"><?=$L('general_summary')?></b>
	<table class="w-full" style="page-break-inside: avoid;">
		<tbody>
			<tr>
				<td class="text-xs py-0 px-1 w-6/12"><?=$L('total_sessions')?></td>
				<td class="text-xs py-0 px-2 w-6/12 font-bold"><?=$sessionCount?></td>
			</tr>
			<tr>
				<td class="text-xs py-0 px-1 w-6/12"><?=$L('total_valid_courses')?></td>
				<td class="text-xs py-0 px-2 w-6/12 font-bold text-green-700"><?=$totalCoursValides?> <?=$L('courses')?></td>
			</tr>
			<tr>
				<td class="text-xs py-0 px-1 w-6/12"><?=$L('total_valid_credits')?></td>
				<td class="text-xs py-0 px-2 w-6/12 font-bold text-green-700"><?=$totalCreditsValides?> <?=$L('credits')?></td>
			</tr>
		</tbody>
	</table>
</div>

<div class='p-0.5 mb-1 rounded border border-slate-600 text-[10px]' style="line-height: 1.2;">
	<b class="text-[8px]"><?=$L('cumulative_avg')?></b>
	<table class="w-full" style="page-break-inside: avoid;">
		<tbody>
			<tr>
				<td class="text-[11px] p-0 w-8/12 text-right"><?=$L('cumulative_work')?></td>
				<td class="text-[11px] px-1 w-2/12 font-bold"><?=($cumulWorkCount > 0) ? round($cumulWorkNote / $cumulWorkCount, 2) : "--"?></td>
			</tr>
			<tr>
				<td class="text-[11px] p-0 w-8/12 text-right"><?=$L('cumulative_chapel')?></td>
				<td class="text-[11px] px-1 w-2/12 font-bold"><?=($cumulChapelCount > 0) ? round($cumulChapel / $cumulChapelCount, 2) : "--"?></td>
			</tr>
		</tbody>
	</table>
	<table class="w-full" style="page-break-inside: avoid;">
		<thead>
			<tr>
				<th class="text-[11px] p-0 w-8/12 text-right"><?=$L('cumulative_major')?></th>
				<th class="text-[11px] py-0 px-1 w-2/12"><?=$fmtGrade($moyenneMajCumulative)?></th>
			</tr>
			<tr>
				<th class="text-[11px] p-0 w-8/12 text-right bg-cyan-700"><?=$L('cumulative_general')?></th>
				<th class="text-[11px] py-0 px-1 w-2/12 bg-cyan-700 text-white"><?=$fmtGrade($moyenneCumulative)?></th>
			</tr>
		</thead>
	</table>
</div>

<!-- BLOC ANTI-CONTREFAÇON - QR CODE DE VÉRIFICATION -->
<?php
// Récapitulatif pour le QR code
$recapQR = [
    'sessions' => $sessionCount,
    'cours' => $totalCoursValides,
    'credits' => $totalCreditsValides,
    'moyenne' => $moyenneCumulative
];
?>
<?= $docVerification->getQRCodeHTML(
    $verificationData['doc_code'], 
    $verificationData['date_emission'],
    $verificationData['student_name'],
    $verificationData['student_id'],
    $verificationData['doc_type'],
    $verificationData['doc_hash'],
    $recapQR
) ?>

<?php
	}
?>
</div>
<?php require('../init/.forPrint/foot.forPrint.php'); ?>