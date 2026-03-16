<?php 
$yearNow = date('Y');

$yearScoolNow = $_POST['yearStatistic'];

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique par Résidence </b><em class="text-xs"> • <b>Session : </b> <?=$semestre." ".$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
				<th style="width: 8%" colspan="2">Interne</th>
				<th style="width: 8%" colspan="2">Bungalow</th>
				<th style="width: 8%" colspan="2">Externe</th>
				<th style="width: 4%">Total</th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th></th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th></th>
			</tr>
			
		</thead>
		<tbody>
			<?php

 $mentio = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE" AND filiere_sigle !="EDUC"');

 // Requête SQL pour récupérer les données groupées
$interne_H = 0;
$interne_F = 0;
$bungalow_H = 0;
$bungalow_F = 0;
$externe_H = 0;
$externe_F = 0;
$thorizontal = 0;

	while($mt = $mentio->fetch()){
		
		$filiere_sigle = $mt['filiere_sigle'];
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
           SUM(CASE WHEN ins.status = "Interne" AND std.sex = 1 THEN 1 ELSE 0 END) AS Interne_H,
           SUM(CASE WHEN ins.status = "Interne" AND std.sex = 0 THEN 1 ELSE 0 END) AS Interne_F,
           SUM(CASE WHEN ins.status = "Bungalow" AND std.sex = 1 THEN 1 ELSE 0 END) AS Bungalow_H,
           SUM(CASE WHEN ins.status = "Bungalow" AND std.sex = 0 THEN 1 ELSE 0 END) AS Bungalow_F,
           SUM(CASE WHEN (ins.status = "Externe" OR ins.status = "") AND std.sex = 1 THEN 1 ELSE 0 END) AS Externe_H,
           SUM(CASE WHEN (ins.status = "Externe" OR ins.status = "") AND std.sex = 0 THEN 1 ELSE 0 END) AS Externe_F

    FROM t_2024_inscription_session ins
    INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
    WHERE ins.etude_mention = "'.$filiere_sigle.'" AND ins.session_id = "'.$session_id.'" AND (std.suspended IS NULL OR std.suspended != 1) AND (std.retrait_universite IS NULL OR std.retrait_universite = 0) ORDER BY ins.etude_mention');


           $row = $result->fetch();

            	echo "<tr>";   
	   				echo "<td>" . $mention . "</td>"; 
	                echo "<td>" . $row['Interne_H'] . "</td>";
	                echo "<td>" . $row['Interne_F'] . "</td>";
	                echo "<td>" . $row['Bungalow_H'] . "</td>";
	                echo "<td>" . $row['Bungalow_F'] . "</td>";
	                echo "<td>" . $row['Externe_H'] . "</td>";
	                echo "<td>" . $row['Externe_F'] . "</td>";
	 				echo "<td>".$sommeHoriz = $row['Interne_H']+$row['Interne_F']+$row['Bungalow_H']+$row['Bungalow_F']+$row['Externe_H']+$row['Externe_F']."</td>";
    			echo "</tr>";
    		

$interne_H += $row['Interne_H'];
$interne_F += $row['Interne_F'];
$bungalow_H += $row['Bungalow_H'];
$bungalow_F += $row['Bungalow_F'];
$externe_H += $row['Externe_H'];
$externe_F += $row['Externe_F'];
$thorizontal += intval($sommeHoriz);      
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="color: orange;">Sous total</th>
				<th style="color: orange;"><?=$interne_H?></th>
				<th style="color: orange;"><?=$interne_F?></th>
				<th style="color: orange;"><?=$bungalow_H?></th>
				<th style="color: orange;"><?=$bungalow_F?></th>
				<th style="color: orange;"><?=$externe_H?></th>
				<th style="color: orange;"><?=$externe_F?></th>
				<th></th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th colspan="2"><?=$interne_H+$interne_F?></th>
				<th colspan="2"><?=$bungalow_H+$bungalow_F?></th>
				<th colspan="2"><?=$externe_H+$externe_F?></th>
				<th><?=$thorizontal?></th>
			</tr>
		</thead>
	</table>

</div>