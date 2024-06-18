<?php 
$yearNow = date('Y');
$yearScoolNow = ($yearNow-1)." - ".$yearNow;
/*$yearScoolNow = "2023 - 2024";*/

$printName = "Statistique";

 ?>
<div class="">

<?php 
$tLevel1 = 0;
$tLevel2 = 0;
$tLevel3 = 0;
for ($i=0; $i < 1 ; $i++) {

 ?>
<b>Statistique par sexe - Année universitaire <?=$yearScoolNow?></b>
	<table class="classicTbl">
		<thead>
			<tr>
				<th style="width: 18%">Mention</th>
				<th style="width: 10%">Masculin</th>
				<th style="width: 10%">Feminin</th>
				<th style="width: 4%">L1</th>
				<th style="width: 4%">L2</th>
				<th style="width: 4%">L3</th>
				<th style="width: 10%">Total</th>
			</tr>
		</thead>
		<tbody>
<?php

	$mention = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE"');

	$value=1;

	while($mt = $mention->fetch()){
	$filiere = $mt['filiere_description'];
	$sex1 = 'masculin';
	$sex2 = 1;
	
	
		$adventist = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire = "'.$yearScoolNow.'" AND etude_envisage="'.$filiere.'" AND (sex="'.$sex1.'" OR sex="'.$sex2.'")');
		
		$nbadvt = 0;
		while($advt = $adventist->fetch()){
			$nbadvt++;
		}

		$nonAdventist = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire = "'.$yearScoolNow.'" AND etude_envisage="'.$filiere.'" AND sex!="'.$sex1.'" AND sex!="'.$sex2.'"');
		
		$nbNonAdvt = 0;
		while($nonAdvt = $nonAdventist->fetch()){
			$nbNonAdvt++;
		}

		$niveau = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire = "'.$yearScoolNow.'" AND etude_envisage="'.$filiere.'"');
		
		$nbLevel1 = 0;
		$nbLevel2 = 0;
		$nbLevel3 = 0;
		
		$ttLevel1 = 0;
		$ttLevel2 = 0;
		$ttLevel3 = 0;

		while($level = $niveau->fetch()){
			
			if ($level['annee_etude'] == 1) {
				$nbLevel1 = 1;
				$nbLevel2 = 0;
				$nbLevel3 = 0;

			}elseif ($level['annee_etude'] == 2) {
				$nbLevel1 = 0;
				$nbLevel2 = 1;
				$nbLevel3 = 0;

			}elseif ($level['annee_etude'] == 3) {
				$nbLevel1 = 0;
				$nbLevel2 = 0;
				$nbLevel3 = 1;

			}

			$ttLevel1 += 0 + $nbLevel1;
			$ttLevel2 += 0 + $nbLevel2;
			$ttLevel3 += 0 + $nbLevel3;
		}

	
 ?>
			<tr>
				<td><?=$filiere?></td>
				<td><?=$nbadvt?></td>
				<td><?=$nbNonAdvt?></td>
				<td><?=$ttLevel1?></td>
				<td><?=$ttLevel2?></td>
				<td><?=$ttLevel3?></td>
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

		$tLevel1 += 0 + $ttLevel1;
		$tLevel2 += 0 + $ttLevel2;
		$tLevel3 += 0 + $ttLevel3;

	$value++;
	}
?>
		</tbody>
		<thead>
			<tr>
				<th>Total</th>
				<th><?=$tnbadvt?></th>
				<th><?=$tnbNonAdvt?></th>
				<th><?=$tLevel1?></th>
				<th><?=$tLevel2?></th>
				<th><?=$tLevel3?></th>
				<th><?=$thorizontal?></th>
			</tr>
		</thead>
	</table>
<?php 
	$yearNow = $yearNow-1;
	$yearScoolNow = ($yearNow-1)." - ".$yearNow;
}
?>
</div>