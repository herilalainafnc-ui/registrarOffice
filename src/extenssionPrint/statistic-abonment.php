<?php 
$yearNow = date('Y');

$semestre =$_POST['semestre'];
$yearScoolNow = $_POST['yearStatistic'];

$findSessionOnSS = $dtb->query('SELECT * FROM t_2023_session WHERE session_name ='.$dtb->quote($semestre).' AND session_year = '.$dtb->quote($yearScoolNow).'');

$showSessionOnSS = $findSessionOnSS->fetch();
$session_id = $showSessionOnSS['session_id'];

$globalStatTotalQ = $dtb->query('SELECT COUNT(DISTINCT ins.student_id) AS total
 FROM t_2024_inscription_session ins
 INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
 WHERE ins.session_id = '.$dtb->quote($session_id).'
	 AND (std.suspended IS NULL OR std.suspended != 1)
	 AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)');
$globalStatTotal = intval(($globalStatTotalQ->fetch())['total'] ?? 0);

// Compter le nombre d'etudiants abonnes uniques pour la session
$countAbonnesQ = $dtb->query('SELECT COUNT(DISTINCT ins.student_id) AS total_abonnes FROM t_2024_inscription_session ins INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1) WHERE COALESCE((SELECT MAX(f.cout_abonment > 0) FROM t_2024_etudiant_finace f WHERE f.student_id = ins.student_id AND f.session_id = ins.session_id AND f.`remove` != 1), ins.abonment_std = 1) = 1 AND ins.session_id = '.$dtb->quote($session_id).' AND (std.suspended IS NULL OR std.suspended != 1) AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)');
$countAbonnesR = $countAbonnesQ->fetch();
$totalAbonnesAnnee = intval($countAbonnesR['total_abonnes']);
 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique d'abonnement </b><em class="text-xs"> • <b>Session : </b> <?=$semestre." ".$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
				<th style="width: 8%" colspan="2">Abonné</th>
				<th style="width: 8%" colspan="2">Non Abonné</th>
				<th style="width: 4%">Total</th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th></th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th></th>
			</tr>
			
		</thead>
		<tbody>
			<?php

 $mentio = $dtb->query('SELECT * FROM filiere');

 // Requête SQL pour récupérer les données groupées
$abonnee_H = 0;
$abonnee_F = 0;
$nonAbonnee_H = 0;
$nonAbonnee_F = 0;
$thorizontal = 0;

	while($mt = $mentio->fetch()){

		$filiere_sigle= $mt['filiere_sigle'];
		$mention = $mt['filiere_description'];
	

		$result = $dtb->query('SELECT
			COUNT(DISTINCT CASE WHEN COALESCE((SELECT MAX(f.cout_abonment > 0) FROM t_2024_etudiant_finace f WHERE f.student_id = ins.student_id AND f.session_id = ins.session_id AND f.`remove` != 1), ins.abonment_std = 1) = 1 AND std.sex = 1 THEN ins.student_id END) AS Abonnee_H,
			COUNT(DISTINCT CASE WHEN COALESCE((SELECT MAX(f.cout_abonment > 0) FROM t_2024_etudiant_finace f WHERE f.student_id = ins.student_id AND f.session_id = ins.session_id AND f.`remove` != 1), ins.abonment_std = 1) = 1 AND std.sex = 0 THEN ins.student_id END) AS Abonnee_F,
			COUNT(DISTINCT CASE WHEN NOT COALESCE((SELECT MAX(f.cout_abonment > 0) FROM t_2024_etudiant_finace f WHERE f.student_id = ins.student_id AND f.session_id = ins.session_id AND f.`remove` != 1), ins.abonment_std = 1) AND std.sex = 1 THEN ins.student_id END) AS NonAbonnee_H,
			COUNT(DISTINCT CASE WHEN NOT COALESCE((SELECT MAX(f.cout_abonment > 0) FROM t_2024_etudiant_finace f WHERE f.student_id = ins.student_id AND f.session_id = ins.session_id AND f.`remove` != 1), ins.abonment_std = 1) AND std.sex = 0 THEN ins.student_id END) AS NonAbonnee_F
        FROM t_2024_inscription_session ins
        INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        WHERE ins.etude_mention = '.$dtb->quote($filiere_sigle).' AND ins.session_id = '.$dtb->quote($session_id).' AND (std.suspended IS NULL OR std.suspended != 1) AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)');		$row = $result->fetch();

		// Normaliser
		$row['Abonnee_H'] = isset($row['Abonnee_H']) ? intval($row['Abonnee_H']) : 0;
		$row['Abonnee_F'] = isset($row['Abonnee_F']) ? intval($row['Abonnee_F']) : 0;
		$row['NonAbonnee_H'] = isset($row['NonAbonnee_H']) ? intval($row['NonAbonnee_H']) : 0;
		$row['NonAbonnee_F'] = isset($row['NonAbonnee_F']) ? intval($row['NonAbonnee_F']) : 0;

		echo "<tr>";
			echo "<td>" . $mention . "</td>";
			echo "<td>" . $row['Abonnee_H'] . "</td>";
			echo "<td>" . $row['Abonnee_F'] . "</td>";
			echo "<td>" . $row['NonAbonnee_H'] . "</td>";
			echo "<td>" . $row['NonAbonnee_F'] . "</td>";
			$sommeHoriz = $row['Abonnee_H'] + $row['Abonnee_F'] + $row['NonAbonnee_H'] + $row['NonAbonnee_F'];
			echo "<td>" . $sommeHoriz . "</td>";
		echo "</tr>";

		$abonnee_H += $row['Abonnee_H'];
		$abonnee_F += $row['Abonnee_F'];
		$nonAbonnee_H += $row['NonAbonnee_H'];
		$nonAbonnee_F += $row['NonAbonnee_F'];
		$thorizontal += $sommeHoriz;
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="color: orange;">Sous total</th>
				<th style="color: orange;"><?=$abonnee_H?></th>
				<th style="color: orange;"><?=$abonnee_F?></th>
				<th style="color: orange;"><?=$nonAbonnee_H?></th>
				<th style="color: orange;"><?=$nonAbonnee_F?></th>
				<th></th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th colspan="2"><?=$abonnee_H+$abonnee_F?></th>
				<th colspan="2"><?=$nonAbonnee_H+$nonAbonnee_F?></th>
				<th><?=$globalStatTotal?></th>
			</tr>
		</thead>
	</table>

</div>












