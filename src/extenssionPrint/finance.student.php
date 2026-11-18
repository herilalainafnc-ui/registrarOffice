<?php

	$yearFinance = trim((string)($_POST['yearFinance'] ?? ''));
	require_once(__DIR__ . '/../../data/finance.bareme.php'); // baremeFraisGraduation()
	$semestreFinance = trim((string)($_POST['semestreFinance'] ?? ''));
	$types = trim((string)($_POST['types'] ?? 'TOUT'));
	$level = trim((string)($_POST['level'] ?? 'TOUT'));

	$printName = "FINANCE_ETUDIANT" . $yearFinance . "_Sem" . $semestreFinance;

	$verifySession = $dtb->prepare('SELECT * FROM t_2023_session WHERE session_semester = :semester AND session_year = :year LIMIT 1');
	$verifySession->execute([
		'semester' => $semestreFinance,
		'year' => $yearFinance,
	]);
	$showSession = $verifySession->fetch(PDO::FETCH_ASSOC);

	$session_id = (int)($showSession['session_id'] ?? 0);
	
 ?>

<div class="">
	<b>État financier de l'étudiant inscrit en année <?=$yearFinance?> - <?=($showSession['session_name'] ?? 'Session introuvable')?></b>
	<?php if ($session_id <= 0): ?>
		<p class="text-red-600 text-xs mt-2">Session introuvable pour l'annee/semestre selectionnes.</p>
		<?php require('../init/.forPrint/foot.forPrint.php'); ?>
		<?php return; ?>
	<?php endif; ?>

	<table class="simpleTbl tbl text-[8px]">
		<thead class="text-center">
			<tr>
				<th rowspan="2">No</th>
				<th rowspan="2">ID</th>
				<th rowspan="2">Nom et prénom</th>
				<th rowspan="2">Mention</th>
				<th rowspan="2">Frais généraux</th>
				<th rowspan="2">Nb crédit</th>
				<th rowspan="2">Ecolage</th>
				<th rowspan="2">Lab</th>
				<th colspan="2">Résidence</th>
				<th rowspan="2">Frais dépôt</th>
				<th colspan="2">Abonement</th>
				<th rowspan="2">Voyage/Colloque</th>
				<?php if ($semestreFinance != 1) { ?>
				<th>Frais Graduation</th>
				<?php } ?>
				<th rowspan="2">TOTAL</th>
				<th colspan="2">Payement</th>
				<th rowspan="2">Sponsor</th>
			</tr>
			<tr>
				<th>Types</th>
				<th>Logement</th>
				<th>Types</th>
				<th>Frais</th>
				<?php if ($semestreFinance != 1) { ?>
				<th>Frais Graduation</th>
				<?php } ?>
				<th>Types</th>
				<th>Tranche</th>
			</tr>
		</thead>
		<tbody>
<?php
	$studentSql = 'SELECT DISTINCT
			ins.student_id,
			ins.etude_mention,
			ins.niveau_std,
			std.student_nom,
			std.student_prenom,
			std.abonment,
			std.sponsor_nom
		FROM t_2024_inscription_session ins
		INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
		WHERE ins.session_id = :session_id
		  AND (std.suspended IS NULL OR std.suspended != 1)
		  AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)';

	$studentParams = ['session_id' => $session_id];

	if ($types !== 'TOUT' && $types !== '') {
		$studentSql .= ' AND ins.etude_mention = :mention';
		$studentParams['mention'] = $types;
	}

	if ($level !== 'TOUT' && $level !== '') {
		$studentSql .= ' AND ins.niveau_std = :level';
		$studentParams['level'] = (int)$level;
	}

	$studentSql .= ' ORDER BY ins.student_id';

	$findStudents = $dtb->prepare($studentSql);
	$findStudents->execute($studentParams);

	$findFinanceByStudent = $dtb->prepare('SELECT * FROM t_2024_etudiant_finace WHERE session_id = :session_id AND student_id = :student_id ORDER BY id DESC LIMIT 1');

	$findCoursFinance = $dtb->prepare('SELECT * FROM t_2024_cours_finance WHERE student_id = :student_id AND session_id = :session_id AND remove != 1');

	$nbrF = 1;
	$gttl = 0;

	while ($showStd = $findStudents->fetch(PDO::FETCH_ASSOC)) {
		$student_id = (string)$showStd['student_id'];

		$findFinanceByStudent->execute([
			'session_id' => $session_id,
			'student_id' => $student_id,
		]);
		$showF = $findFinanceByStudent->fetch(PDO::FETCH_ASSOC) ?: [];

		$findCoursFinance->execute([
			'student_id' => $student_id,
			'session_id' => $session_id,
		]);

		$nb_crd = 0;
		$ttl_cout = 0;
		$lab_course_count = 0;

		while ($showCrs = $findCoursFinance->fetch(PDO::FETCH_ASSOC)) {
			$nb_crd += (int)($showCrs['cours_credit'] ?? 0);
			$ttl_cout += (float)($showCrs['cours_cout'] ?? 0);
			if ((float)($showCrs['lab_cout'] ?? 0) > 0) {
				$lab_course_count++;
			}
		}

		$ttl_lab = $lab_course_count * 35000;

		if ($ttl_lab > 70000) {
			$ttl_lab = 70000;
		}

		$coutFraixGeneraux = (float)($showF['cout_fraix_generaux'] ?? 0);
		$coutLogement = (float)($showF['cout_logement'] ?? 0);
		$coutFondDepot = (float)($showF['cout_fondDepot_dortoir'] ?? 0);
		$coutAbonment = (float)($showF['cout_abonment'] ?? 0);
		$coutVoyage = (float)($showF['cout_voyage'] ?? 0);
		/* Meme regle que la fiche d'inscription : deuxieme semestre et etudiant
		   diplomant. Ce total les ignorait, et facturait donc un frais que la
		   fiche n'affichait pas — 339 dossiers au 16/09/2026. */
		$coutGraduation = baremeFraisGraduation($showF['cout_frais_graduation'] ?? 0, $semestreFinance, $showStd['graduated'] ?? 0);
		$ttl = $coutFraixGeneraux + $ttl_cout + $ttl_lab + $coutLogement + $coutFondDepot + $coutAbonment + $coutVoyage + $coutGraduation;

 ?>
			<tr style="<?php if ($ttl_cout == 0) { echo "background-color: #e87c68"; }?>">
				<td><?=$nbrF?></td>
				<td><?=$student_id?></td>
				<td><?=$showStd['student_nom']." ".$showStd['student_prenom']?></td>
				<td><?=($showF['mention'] ?? $showStd['etude_mention'])?></td>
				<td><?=$coutFraixGeneraux?></td>
				<td><?=$nb_crd?></td>
				<td><?=$ttl_cout?></td>
				<td><?=number_format($ttl_lab, 0, '', ' ')?> ar</td>
				<td><?=($showF['status'] ?? '')?></td>
				<td><?=$coutLogement?></td>
				<td><?=$coutFondDepot?></td>
				<td><?php if($showStd['abonment']==1){ echo "Abonné";}?></td>
				<td><?=$coutAbonment?></td>
				<td><?=$coutVoyage?></td>
				<?php if ($semestreFinance != 1) { ?>
				<td><?=$coutGraduation?></td>
				<?php } ?>
				<td><?=$ttl?></td>
				<td><?=($showF['mode_payement'] ?? '')?></td>
				<td><?php
			$modePayement = (string)($showF['mode_payement'] ?? '');
if ($modePayement == 'A') {
	echo ' <em class="text-[5px]">100%</em>';
}elseif ($modePayement == 'B') {
	echo ' <em class="text-[5px]">50%,50%</em>';
}elseif ($modePayement == 'C') {
	echo ' <em class="text-[5px]">75%,25%</em>';
}elseif ($modePayement == 'D') {
	echo ' <em class="text-[5px]">40%,30%,30%</em>';
}elseif ($modePayement == 'E') {
	echo ' <em class="text-[5px]">25%,25%,25%,25%</em>';
}			
			?>	</td>
				<td><?=$showStd['sponsor_nom']?></td>
			</tr>
<?php
	$gttl += $ttl;
	$nbrF++;
	}
 ?>
		</tbody>
		<tfoot>
			<tr>
				<th colspan="4"></th>
				<th>FG</th>
				<th></th>
				<th>ECO</th>
				<th colspan="2"></th>
				<th>Dortoir</th>
				<th>FD</th>
				<th></th>
				<th colspan="2" class="bg-slate-200">Total général</th>
				<th colspan="4"><?=number_format($gttl, 0,'', ' ')?> ar</th>
			</tr>
		</tfoot>
	</table>
</div>
<?php require('../init/.forPrint/foot.forPrint.php');?>