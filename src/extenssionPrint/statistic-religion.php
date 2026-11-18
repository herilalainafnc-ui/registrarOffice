<?php 
$yearNow = date('Y');

$yearScoolNow = $_POST['yearStatistic'];

$globalStatTotalQ = $dtb->query('SELECT COUNT(DISTINCT ins.student_id) AS total
 FROM t_2024_inscription_session ins
 INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
 WHERE ins.session_id = '.$dtb->quote($session_id).'
	 AND (std.suspended IS NULL OR std.suspended != 1)
	 AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)');
$globalStatTotal = intval(($globalStatTotalQ->fetch())['total'] ?? 0);

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique par Religion </b><em class="text-xs"> • <b>Session : </b> <?=$semestre." ".$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
				<th style="width: 8%" colspan="2">Adventiste</th>
				<th style="width: 8%" colspan="2">Non Adventiste</th>
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
$adventiste_H = 0;
$adventiste_F = 0;
$nonAdventiste_H = 0;
$nonAdventiste_F = 0;
$thorizontal = 0;

	while($mt = $mentio->fetch()){
		
		$filiere_sigle = $mt['filiere_sigle'];
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
		   COUNT(DISTINCT CASE WHEN (std.religion = "Adventiste" OR std.religion = "Adventiste du Septieme-jour") AND std.sex = 1 THEN ins.student_id END) AS Adventiste_H,
		   COUNT(DISTINCT CASE WHEN (std.religion = "Adventiste" OR std.religion = "Adventiste du Septieme-jour") AND std.sex = 0 THEN ins.student_id END) AS Adventiste_F,
		   COUNT(DISTINCT CASE WHEN std.religion != "Adventiste" AND std.religion != "Adventiste du Septieme-jour" AND std.sex = 1 THEN ins.student_id END) AS NonAdventiste_H,
		   COUNT(DISTINCT CASE WHEN std.religion != "Adventiste" AND std.religion != "Adventiste du Septieme-jour" AND std.sex = 0 THEN ins.student_id END) AS NonAdventiste_F
       
    FROM t_2024_inscription_session ins
    INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
    WHERE ins.etude_mention = '.$dtb->quote($filiere_sigle).' AND ins.session_id = '.$dtb->quote($session_id).' AND (std.suspended IS NULL OR std.suspended != 1) AND (std.retrait_universite IS NULL OR std.retrait_universite = 0) ORDER BY ins.etude_mention');


           $row = $result->fetch();

            	echo "<tr>";   
	   				echo "<td>" . $mention . "</td>"; 
	                echo "<td>" . $row['Adventiste_H'] . "</td>";
	                echo "<td>" . $row['Adventiste_F'] . "</td>";
	                echo "<td>" . $row['NonAdventiste_H'] . "</td>";
	                echo "<td>" . $row['NonAdventiste_F'] . "</td>";
	 				echo "<td>".$sommeHoriz = 
	 				$row['Adventiste_H']+
					$row['Adventiste_F']+
					$row['NonAdventiste_H']+
					$row['NonAdventiste_F']
	 				."</td>";
    			echo "</tr>";
    		

$adventiste_H += $row['Adventiste_H'];
$adventiste_F += $row['Adventiste_F'];
$nonAdventiste_H += $row['NonAdventiste_H'];
$nonAdventiste_F += $row['NonAdventiste_F'];
$thorizontal += intval($sommeHoriz);      
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="color: orange;">Sous total</th>
				<th style="color: orange;"><?=$adventiste_H?></th>
				<th style="color: orange;"><?=$adventiste_F?></th>
				<th style="color: orange;"><?=$nonAdventiste_H?></th>
				<th style="color: orange;"><?=$nonAdventiste_F?></th>
				<th></th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th colspan="2"><?=$adventiste_H+$adventiste_F?></th>
				<th colspan="2"><?=$nonAdventiste_H+$nonAdventiste_F?></th>
				<th><?=$globalStatTotal?></th>
			</tr>
		</thead>
	</table>

</div>












