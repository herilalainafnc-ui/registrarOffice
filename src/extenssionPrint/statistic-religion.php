<?php 
$yearNow = date('Y');

$yearScoolNow = $_POST['yearStatistic'];

 ?>
<div class="" style="page-break-inside: avoid;">

<b>Statistique par Religion </b><em class="text-xs">- Année <?=$yearScoolNow?></em>
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

 $mentio = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE"');

 // Requête SQL pour récupérer les données groupées
$adventiste_H = 0;
$adventiste_F = 0;
$nonAdventiste_H = 0;
$nonAdventiste_F = 0;
$thorizontal = 0;

	while($mt = $mentio->fetch()){
		
		$mention = $mt['filiere_description'];

$result = $dtb->query('SELECT *,
           SUM(CASE WHEN religion = "Adventiste" AND sex = 1 THEN 1 ELSE 0 END) AS Adventiste_H,
           SUM(CASE WHEN religion = "Adventiste" AND sex = 0 THEN 1 ELSE 0 END) AS Adventiste_F,
           SUM(CASE WHEN religion != "Adventiste" AND sex = 1 THEN 1 ELSE 0 END) AS NonAdventiste_H,
           SUM(CASE WHEN religion != "Adventiste" AND sex = 0 THEN 1 ELSE 0 END) AS NonAdventiste_F
       
    FROM tbl_2024_etudiant WHERE etude_envisage = "'.$mention.'" AND annee_scolaire = "'.$yearScoolNow.'" AND (graduated IS NULL OR graduated != 1) ORDER BY etude_envisage');


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
    		

$adventiste_H =+ $adventiste_H + $row['Adventiste_H'];
$adventiste_F =+ $adventiste_F + $row['Adventiste_F'];
$nonAdventiste_H =+ $nonAdventiste_H + $row['NonAdventiste_H'];
$nonAdventiste_F =+ $nonAdventiste_F + $row['NonAdventiste_F'];
$thorizontal =+ intval($thorizontal) +  intval($sommeHoriz);      
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
				<th><?=$thorizontal?></th>
			</tr>
		</thead>
	</table>

</div>












<!-- <?php 
$yearNow = date('Y');
$yearScoolNow = ($yearNow-1)." - ".$yearNow;
$printName = "STATISTIQUE";

 ?>
<div class="mb-4">

<?php 
for ($i=0; $i < 1 ; $i++) {

 ?>
<b>Statistique par réligion - Année universitaire <?=$yearScoolNow?></b>
	<table class="tbl" style="page-break-inside: avoid;">
		<thead>
			<tr style="page-break-inside: avoid;">
				<th style="width: 30%">Mention</th>
				<th style="width: 10%">Adventiste</th>
				<th style="width: 10%">Non Adventiste</th>
				<th style="width: 10%">Total</th>
			</tr>
		</thead>
		<tbody>
<?php

	$mention = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE"');

	$value=1;

	while($mt = $mention->fetch()){
	$filiere = $mt['filiere_description'];
	$advent1 = 'Adventiste du Septieme-jour';
	$advent2 = 'Adventiste';
	
	
		$adventist = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearScoolNow.'" AND etude_envisage="'.$filiere.'" AND (religion="'.$advent1.'" OR religion="'.$advent2.'")');
		
		$nbadvt = 0;
		while($advt = $adventist->fetch()){
			$nbadvt++;
		}

		$nonAdventist = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$yearScoolNow.'" AND etude_envisage="'.$filiere.'" AND religion!="'.$advent1.'" AND religion!="'.$advent2.'"');
		
		$nbNonAdvt = 0;
		while($nonAdvt = $nonAdventist->fetch()){
			$nbNonAdvt++;
		}

	
 ?>
			<tr style="page-break-inside: avoid;">
				<td><?=$filiere?></td>
				<td><?=$nbadvt?></td>
				<td><?=$nbNonAdvt?></td>
				<td><?=$horizontal = $nbadvt+$nbNonAdvt?></td>
			</tr>
<?php
		
		$tnbadvt = 0;
		$nbadvT[$value] = $nbadvt;
		foreach ($nbadvT as $val) {
			$tnbadvt += $val;
		}

		$tnbNonAdvt = 0;
		$nbNonAdvT[$value] = $nbNonAdvt;
		foreach ($nbNonAdvT as $val) {
			$tnbNonAdvt += $val;
		}

		$thorizontal = 0;
		$horizontaL[$value] = $horizontal;
		foreach ($horizontaL as $val) {
			$thorizontal += $val;
		}

	$value++;
	}
?>
		</tbody>
		<thead>
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th><?=$tnbadvt?></th>
				<th><?=$tnbNonAdvt?></th>
				<th><?=$thorizontal?></th>
			</tr>
		</thead>
	</table>
<?php 
	$yearNow = $yearNow-1;
	$yearScoolNow = ($yearNow-1)." - ".$yearNow;
}
?>
</div> -->