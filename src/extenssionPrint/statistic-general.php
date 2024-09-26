<?php 
$yearNow = date('Y');

$yearScoolNow = $_POST['yearStatistic'];

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique générale </b><em class="text-xs">- Année <?=$yearScoolNow?></em>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 18%">Mention</th>
				<th style="width: 8%" colspan="2">Licence 1</th>
				<th style="width: 8%" colspan="2">Licence 2</th>
				<th style="width: 8%" colspan="2">Licence 3</th>
				<th style="width: 8%" colspan="2">Master 1</th>
				<th style="width: 8%" colspan="2">Master 2</th>
				<th style="width: 4%" colspan="2">Total</th>
			</tr>
			<tr style="page-break-inside: avoid;">
				<th style="color: orange;"></th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
				<th style="color: orange;">New</th>
				<th style="color: orange;">Old</th>
			</tr>
			
		</thead>
		<tbody>
			<?php

 $mentio = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE"');

 // Requête SQL pour récupérer les données groupées
$licence1_N = 0;
$licence1_A = 0;
$licence2_N = 0;
$licence2_A = 0;
$licence3_N = 0;
$licence3_A = 0;
$master1_N = 0;
$master1_A = 0;
$master2_N = 0;
$master2_A = 0;
$thorizontal_N = 0;
$thorizontal_A = 0;

	while($mt = $mentio->fetch()){
		
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
           SUM(CASE WHEN annee_etude = 1 AND new_student = 1 THEN 1 ELSE 0 END) AS Licence1_N,
           SUM(CASE WHEN annee_etude = 1 AND new_student = 0 THEN 1 ELSE 0 END) AS Licence1_A,
           SUM(CASE WHEN annee_etude = 2 AND new_student = 1 THEN 1 ELSE 0 END) AS Licence2_N,
           SUM(CASE WHEN annee_etude = 2 AND new_student = 0 THEN 1 ELSE 0 END) AS Licence2_A,
           SUM(CASE WHEN annee_etude = 3 AND new_student = 1 THEN 1 ELSE 0 END) AS Licence3_N,
           SUM(CASE WHEN annee_etude = 3 AND new_student = 0 THEN 1 ELSE 0 END) AS Licence3_A,
           SUM(CASE WHEN annee_etude = 4 AND new_student = 1 THEN 1 ELSE 0 END) AS Master1_N,
           SUM(CASE WHEN annee_etude = 4 AND new_student = 0 THEN 1 ELSE 0 END) AS Master1_A,
           SUM(CASE WHEN annee_etude = 5 AND new_student = 1 THEN 1 ELSE 0 END) AS Master2_N,
           SUM(CASE WHEN annee_etude = 5 AND new_student = 0 THEN 1 ELSE 0 END) AS Master2_A

    FROM tbl_2024_etudiant WHERE etude_envisage = "'.$mention.'" AND annee_scolaire = "'.$yearScoolNow.'" ORDER BY etude_envisage');


           $row = $result->fetch();

            	echo "<tr>";   
	   				echo "<td>" . $mention . "</td>"; 
	                echo "<td>" . $row['Licence1_N'] . "</td>";
	                echo "<td>" . $row['Licence1_A'] . "</td>";
	                echo "<td>" . $row['Licence2_N'] . "</td>";
	                echo "<td>" . $row['Licence2_A'] . "</td>";
	                echo "<td>" . $row['Licence3_N'] . "</td>";
	                echo "<td>" . $row['Licence3_A'] . "</td>";
	                echo "<td>" . $row['Master1_N'] . "</td>";
	                echo "<td>" . $row['Master1_A'] . "</td>";
	                echo "<td>" . $row['Master2_N'] . "</td>";
	                echo "<td>" . $row['Master2_A'] . "</td>";
	 				echo "<td>".$sommeHoriz_N =  
	                $row['Licence1_N']
	                + $row['Licence2_N']
	                + $row['Licence3_N']
	                + $row['Master1_N']
	                + $row['Master2_N']."</td>";
	                echo "<td>".$sommeHoriz_A =  
	                + $row['Licence1_A']
	                + $row['Licence2_A']
	                + $row['Licence3_A']
	                + $row['Master1_A']
	                + $row['Master2_A']."</td>";

    			echo "</tr>";
    		

$licence1_N =+ $licence1_N + $row['Licence1_N'];
$licence1_A =+ $licence1_A + $row['Licence1_A'];
$licence2_N =+ $licence2_N + $row['Licence2_N'];
$licence2_A =+ $licence2_A + $row['Licence2_A'];
$licence3_N =+ $licence3_N + $row['Licence3_N'];
$licence3_A =+ $licence3_A + $row['Licence3_A'];
$master1_N =+ $master1_N + $row['Master1_N'];
$master1_A =+ $master1_A + $row['Master1_A'];
$master2_N =+ $master2_N + $row['Master2_N'];
$master2_A =+ $master2_A + $row['Master2_A'];
$thorizontal_N =+ intval($thorizontal_N) + intval($sommeHoriz_N);
$thorizontal_A =+ intval($thorizontal_A) + intval($sommeHoriz_A);
   
	}
        ?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid; color: orange;" >
				<th style="color: orange;">Sous total</th>
				<th style="color: orange;"><?=$licence1_N?></th>
				<th style="color: orange;"><?=$licence1_A?></th>
				<th style="color: orange;"><?=$licence2_N?></th>
				<th style="color: orange;"><?=$licence2_A?></th>
				<th style="color: orange;"><?=$licence3_N?></th>
				<th style="color: orange;"><?=$licence3_A?></th>
				<th style="color: orange;"><?=$master1_N?></th>
				<th style="color: orange;"><?=$master1_A?></th>
				<th style="color: orange;"><?=$master2_N?></th>
				<th style="color: orange;"><?=$master2_A?></th>
				<th style="color: orange;"><?=$thorizontal_N?></th>
				<th style="color: orange;"><?=$thorizontal_A?></th>
				
			</tr>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th colspan="2"><?=$licence1_N+$licence1_A?></th>
				<th colspan="2"><?=$licence2_N+$licence2_A?></th>
				<th colspan="2"><?=$licence3_N+$licence3_A?></th>
				<th colspan="2"><?=$master1_N+$master1_A?></th>
				<th colspan="2"><?=$master2_N+$master2_A?></th>
				<th colspan="2"><?=$thorizontal_N+$thorizontal_A?></th>
			</tr>
		</thead>
	</table>

</div>