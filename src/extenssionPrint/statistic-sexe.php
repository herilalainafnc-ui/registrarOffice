<?php 
$yearNow = date('Y');

$yearScoolNow = $_POST['yearStatistic'];

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique par Niveau </b><em class="text-xs">- Année <?=$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
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
				<th></th>
			</tr>
			
		</thead>
		<tbody>
			<?php

 $mentio = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE"');

 // Requête SQL pour récupérer les données groupées
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
		
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
           SUM(CASE WHEN annee_etude = 1 AND sex = 1 THEN 1 ELSE 0 END) AS Licence1_H,
           SUM(CASE WHEN annee_etude = 1 AND sex = 0 THEN 1 ELSE 0 END) AS Licence1_F,
           SUM(CASE WHEN annee_etude = 2 AND sex = 1 THEN 1 ELSE 0 END) AS Licence2_H,
           SUM(CASE WHEN annee_etude = 2 AND sex = 0 THEN 1 ELSE 0 END) AS Licence2_F,
           SUM(CASE WHEN annee_etude = 3 AND sex = 1 THEN 1 ELSE 0 END) AS Licence3_H,
           SUM(CASE WHEN annee_etude = 3 AND sex = 0 THEN 1 ELSE 0 END) AS Licence3_F,
           SUM(CASE WHEN annee_etude = 4 AND sex = 1 THEN 1 ELSE 0 END) AS Master1_H,
           SUM(CASE WHEN annee_etude = 4 AND sex = 0 THEN 1 ELSE 0 END) AS Master1_F,
           SUM(CASE WHEN annee_etude = 5 AND sex = 1 THEN 1 ELSE 0 END) AS Master2_H,
           SUM(CASE WHEN annee_etude = 5 AND sex = 0 THEN 1 ELSE 0 END) AS Master2_F

    FROM tbl_2024_etudiant WHERE etude_envisage = "'.$mention.'" AND annee_scolaire = "'.$yearScoolNow.'" ORDER BY etude_envisage');


           $row = $result->fetch();

            	echo "<tr>";   
	   				echo "<td>" . $mention . "</td>"; 
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
	                $row['Licence1_H']
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
    		

$licence1_H =+ $licence1_H + $row['Licence1_H'];
$licence1_F =+ $licence1_F + $row['Licence1_F'];
$licence2_H =+ $licence2_H + $row['Licence2_H'];
$licence2_F =+ $licence2_F + $row['Licence2_F'];
$licence3_H =+ $licence3_H + $row['Licence3_H'];
$licence3_F =+ $licence3_F + $row['Licence3_F'];
$master1_H =+ $master1_H + $row['Master1_H'];
$master1_F =+ $master1_F + $row['Master1_F'];
$master2_H =+ $master2_H + $row['Master2_H'];
$master2_F =+ $master2_F + $row['Master2_F'];
$thorizontal =+ intval($thorizontal) + intval($sommeHoriz);
   
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid; color: orange;" >
				<th style="color: orange;">Sous total</th>
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
				<th colspan="2"><?=$licence1_H+$licence1_F?></th>
				<th colspan="2"><?=$licence2_H+$licence2_F?></th>
				<th colspan="2"><?=$licence3_H+$licence3_F?></th>
				<th colspan="2"><?=$master1_H+$master1_F?></th>
				<th colspan="2"><?=$master2_H+$master2_F?></th>
				<th><?=$thorizontal?></th>
			</tr>
		</thead>
	</table>

</div>