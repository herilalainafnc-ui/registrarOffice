<?php 
$yearNow = date('Y');

$yearScoolNow = $_POST['yearStatistic'];

$globalStatTotalQ = $dtb->query('SELECT COUNT(DISTINCT ins.student_id) AS total
 FROM t_2024_inscription_session ins
 INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
 WHERE ins.session_id = "'.$session_id.'"
	 AND ins.etude_mention IN (SELECT filiere_sigle FROM filiere WHERE filiere_sigle != "CPRE" AND filiere_sigle != "EDUC")
	 AND (std.suspended IS NULL OR std.suspended != 1)
	 AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)');
$globalStatTotal = intval(($globalStatTotalQ->fetch())['total'] ?? 0);

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique par Mention </b><em class="text-xs"> • <b>Session : </b> <?=$semestre." ".$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
				<th style="width: 8%" colspan="2">R.niveau</th>
				<th style="width: 8%" colspan="2">Licence 1</th>
				<th style="width: 8%" colspan="2">Licence 2</th>
				<th style="width: 8%" colspan="2">Licence 3</th>
				<th style="width: 8%" colspan="2">Master 1</th>
				<th style="width: 8%" colspan="2">Master 2</th>
				<th style="width: 4%">Total</th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th style="color: orange;"></th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
				<th style="color: orange;">H</th>
				<th style="color: orange;">F</th>
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
$rm_H = 0;
$rm_F = 0;
$licence1_H = 0;
$licence1_F = 0;
$licence2_H = 0;
$licence2_F = 0;
$licence3_H = 0;
$licence3_F = 0;
$master1_H = 0;
$master1_F = 0;
$master2_H = 0;
$master2_F = 0;
$thorizontal = 0;

	while($mt = $mentio->fetch()){
		
		$filiere_sigle = $mt['filiere_sigle'];
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 0 AND std.sex = 1 THEN ins.student_id END) AS RM_H,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 0 AND std.sex = 0 THEN ins.student_id END) AS RM_F,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 1 AND std.sex = 1 THEN ins.student_id END) AS Licence1_H,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 1 AND std.sex = 0 THEN ins.student_id END) AS Licence1_F,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 2 AND std.sex = 1 THEN ins.student_id END) AS Licence2_H,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 2 AND std.sex = 0 THEN ins.student_id END) AS Licence2_F,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 3 AND std.sex = 1 THEN ins.student_id END) AS Licence3_H,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 3 AND std.sex = 0 THEN ins.student_id END) AS Licence3_F,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 4 AND std.sex = 1 THEN ins.student_id END) AS Master1_H,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 4 AND std.sex = 0 THEN ins.student_id END) AS Master1_F,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 5 AND std.sex = 1 THEN ins.student_id END) AS Master2_H,
		   COUNT(DISTINCT CASE WHEN ins.niveau_std = 5 AND std.sex = 0 THEN ins.student_id END) AS Master2_F,
		   COUNT(DISTINCT ins.student_id) AS MentionTotal

    FROM t_2024_inscription_session ins
    INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
    WHERE ins.etude_mention = "'.$filiere_sigle.'" AND ins.session_id = "'.$session_id.'" AND (std.suspended IS NULL OR std.suspended != 1) AND (std.retrait_universite IS NULL OR std.retrait_universite = 0) ORDER BY ins.etude_mention');


           $row = $result->fetch();

            	echo "<tr>";   
	   		  echo "<td>" . $mention . "</td>"; 
	                echo "<td>" . $row['RM_H'] . "</td>";
	                echo "<td>" . $row['RM_F'] . "</td>";
	                echo "<td>" . $row['Licence1_H'] . "</td>";
	                echo "<td>" . $row['Licence1_F'] . "</td>";
	                echo "<td>" . $row['Licence2_H'] . "</td>";
	                echo "<td>" . $row['Licence2_F'] . "</td>";
	                echo "<td>" . $row['Licence3_H'] . "</td>";
	                echo "<td>" . $row['Licence3_F'] . "</td>";
	                echo "<td>" . $row['Master1_H'] . "</td>";
	                echo "<td>" . $row['Master1_F'] . "</td>";
	                echo "<td>" . $row['Master2_H'] . "</td>";
	                echo "<td>" . $row['Master2_F'] . "</td>";
	 				echo "<td>".$sommeHoriz =  
	                $row['RM_H']
	                + $row['RM_F']
	                + $row['Licence1_H']
	                + $row['Licence1_F']
	                + $row['Licence2_H']
	                + $row['Licence2_F']
	                + $row['Licence3_H']
	                + $row['Licence3_F']
	                + $row['Master1_H']
	                + $row['Master1_F']
	                + $row['Master2_H']
	                + $row['Master2_F']	."</td>";

    			echo "</tr>";
    		
$rm_H += $row['RM_H'];
$rm_F += $row['RM_F'];
$licence1_H += $row['Licence1_H'];
$licence1_F += $row['Licence1_F'];
$licence2_H += $row['Licence2_H'];
$licence2_F += $row['Licence2_F'];
$licence3_H += $row['Licence3_H'];
$licence3_F += $row['Licence3_F'];
$master1_H += $row['Master1_H'];
$master1_F += $row['Master1_F'];
$master2_H += $row['Master2_H'];
$master2_F += $row['Master2_F'];
$thorizontal += intval($row['MentionTotal']);
   
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid; color: orange;" >
				<th style="color: orange;">Sous total</th>
				<th style="color: orange;"><?=$rm_H?></th>
				<th style="color: orange;"><?=$rm_F?></th>
				<th style="color: orange;"><?=$licence1_H?></th>
				<th style="color: orange;"><?=$licence1_F?></th>
				<th style="color: orange;"><?=$licence2_H?></th>
				<th style="color: orange;"><?=$licence2_F?></th>
				<th style="color: orange;"><?=$licence3_H?></th>
				<th style="color: orange;"><?=$licence3_F?></th>
				<th style="color: orange;"><?=$master1_H?></th>
				<th style="color: orange;"><?=$master1_F?></th>
				<th style="color: orange;"><?=$master2_H?></th>
				<th style="color: orange;"><?=$master2_F?></th>
				<th></th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th colspan="2"><?=$rm_H+$rm_F?></th>
				<th colspan="2"><?=$licence1_H+$licence1_F?></th>
				<th colspan="2"><?=$licence2_H+$licence2_F?></th>
				<th colspan="2"><?=$licence3_H+$licence3_F?></th>
				<th colspan="2"><?=$master1_H+$master1_F?></th>
				<th colspan="2"><?=$master2_H+$master2_F?></th>
				<th><?=$globalStatTotal?></th>
			</tr>
		</thead>
	</table>

</div>