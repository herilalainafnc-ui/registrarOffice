
<?php
require('../init/.forPrint/top.forPrint.php');  
require_once(__DIR__ . '/../services/DocumentVerification.php');

$level = isset($_GET['level']) ? $_GET['level'] : 'all';
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$student_id = $_GET['student_id'];
$yes = 1;
$printName = $student_id."-BULLETIN";

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');
$stdA = $searchStd->fetch();

// Système anti-contrefaçon - Génération du code de vérification
$docVerification = new DocumentVerification($dtb);
$studentName = strtoupper($stdA['student_nom']) . " " . $stdA['student_prenom'];
$verificationData = $docVerification->getOrCreateBulletinVerification($student_id, $studentName, $level, $semester);
?>

<div class="mb-6">
<center>
    <b class="text-sm">Relevé de notes</b>
</center>

<div class="flex text-[10px] px-1 py-0.5" style="border: 1px solid #8e9bb2;">
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
	// Construire la requête avec filtres optionnels
	$sqlWhere = "WHERE n.student_id = '".$student_id."' AND n.ajout = '".$yes."' AND n.grade >= 10";
	
	// Filtre par niveau
	if ($level != 'all' && is_numeric($level)) {
		$sqlWhere .= " AND n.yearlevel = '".$level."'";
	}
	
	// Filtre par semestre de session
	if ($semester != 'all' && is_numeric($semester)) {
		$sqlWhere .= " AND s.session_semester = '".$semester."'";
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
		$getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id='".$student_id."' AND session_id='".$session_id."' AND ajout='".$yes."' AND grade >= 10 LIMIT 1");
		$ylData = $getYearlevel->fetch();
		$yearlevel = $ylData ? $ylData['yearlevel'] : 1;
		
		// Déterminer le niveau (Licence ou Master)
		if ($yearlevel <= 3) {
			$niveau_label = "Licence " . $yearlevel;
		} else {
			$niveau_label = "Master " . ($yearlevel - 3);
		}

		$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND session_id='".$session_id."' AND grade >= 10 ORDER BY id");

		if ($cours->rowCount() > 0) {
			?>
			<table class="tbl mb-0 text-[10px]" style="page-break-inside: avoid;">
				<thead>
					<tr class="text-center bg-slate-500 text-white text-[10px]">
						<th colspan="10" class="py-0"><b><?=$niveau_label?></b> | <?=$showSs['session_name']?> - N°<?=$showSs['session_semester']?> | <?=$combinAnual?></th>
					</tr>
				</thead>
				<thead class="bg-slate-200">
					<tr>
						<th style="width: 55px">Sigle</th>
						<th style="width: 220px">Titre</th>
						<th style="width: 25px">Cr</th>
						<th style="width: 30px">Cat</th>
						<th style="width: 25px">N</th>
						<th style="width: 30px">C*N</th>
						<th style="width: 15px">É</th>
					</tr>
				</thead>	
			<?php
			$nbr = 0;
			$nbrMaj = 0;
			$tcredit = 0;
			$tnote = 0;
			$tnotecredit = 0;
			$tTMaj = 0;
			
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
						<td class="text-center bg-green-200">S</td>					
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
			$totalCoursValides += $nbr;
			$totalCreditsValides += $tcredit;
			?>
			<tfoot>
				<tr class="bg-slate-200">
					<th colspan="2"><?=$nbr?> cours validés</th>
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
					<th colspan="4" class="text-right">Moyenne Majeure</th>
					<th class="px-2 bg-slate-200"><?=$moyenMajSem?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2 bg-cyan-700 text-white"><?=$moyenGenSem?></th>
				</tr>
			</tfoot>
			</table>
			<?php
			// Cumuls
			if (!empty($grade_work_educ)) $cumulWorkNote += floatval($grade_work_educ);
			if (!empty($grade_chapel_part)) $cumulChapel += floatval($grade_chapel_part);
			$cumulMaj += $moyenMajSem;
			$cumulCredit += $tcredit;
			$cumulNoteCredit += $tnotecredit;
		}
	}
	
	if($sessionCount == 0) {
		echo '<div class="text-center py-8"><p>Aucun cours validé trouvé pour cet étudiant.</p></div>';
	} else {
		// Moyenne cumulative
		$moyenneCumulative = ($cumulCredit > 0) ? round($cumulNoteCredit / $cumulCredit, 2) : 0;
?>

<!-- RÉCAPITULATIF GÉNÉRAL -->
<div class='p-1 mb-2 rounded-md text-[11px]' style="border: 1px solid #334155; background: #f1f5f9;">
	<b class="text-[10px]">RÉCAPITULATIF GÉNÉRAL</b>
	<table class="mb-1 w-full" style="page-break-inside: avoid;">
		<tbody>
			<tr>
				<td class="text-xs p-1 w-6/12">Nombre total de sessions</td>
				<td class="text-xs px-2 w-6/12 font-bold"><?=$sessionCount?></td>
			</tr>
			<tr>
				<td class="text-xs p-1 w-6/12">Nombre total de cours validés</td>
				<td class="text-xs px-2 w-6/12 font-bold text-green-700"><?=$totalCoursValides?> cours</td>
			</tr>
			<tr>
				<td class="text-xs p-1 w-6/12">Total des crédits validés</td>
				<td class="text-xs px-2 w-6/12 font-bold text-green-700"><?=$totalCreditsValides?> crédits</td>
			</tr>
		</tbody>
	</table>
</div>

<div class='p-0.5 mb-1 rounded border border-slate-600 text-[10px]' style="line-height: 1.2;">
	<b class="text-[8px]">MOYENNE CUMULATIVE</b>
	<table class="w-full" style="page-break-inside: avoid;">
		<tbody>
			<tr>
				<td class="text-[11px] p-0 w-8/12 text-right">Note de Work Education cumulative</td>
				<td class="text-[11px] px-1 w-2/12 font-bold"><?=($sessionCount > 0) ? round($cumulWorkNote / $sessionCount, 2) : 0?></td>
			</tr>
			<tr>
				<td class="text-[11px] p-0 w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class="text-[11px] px-1 w-2/12 font-bold"><?=($sessionCount > 0) ? round($cumulChapel / $sessionCount, 2) : 0?></td>
			</tr>
		</tbody>
	</table>
	<table class="w-full" style="page-break-inside: avoid;">
		<thead>
			<tr>
				<th class="text-[11px] p-0 w-8/12 text-right">Moyenne Majeure Cumulative</th>
				<th class="text-[11px] py-0 px-1 w-2/12"><?=($sessionCount > 0) ? round($cumulMaj / $sessionCount, 2) : 0?></th>
			</tr>
			<tr>
				<th class="text-[11px] p-0 w-8/12 text-right bg-cyan-700">Moyenne Générale Cumulative</th>
				<th class="text-[11px] py-0 px-1 w-2/12 bg-cyan-700 text-white"><?=$moyenneCumulative?></th>
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