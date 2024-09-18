<?php 
$yearNow = date('Y');
$yearScoolNow = ($yearNow)." - ".$yearNow+1;
/*$yearScoolNow = "2023 - 2024";*/

$printName = "STATISTIQUE";

 ?>
<div class="">

<?php 
$tLevel1 = 0;
$tLevel2 = 0;
$tLevel3 = 0;
for ($i=0; $i < 1 ; $i++) {

 ?>
<b>Statistique des Internats - Année universitaire <?=$yearScoolNow?></b>
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
				<th>H</th>
				<th>F</th>
				<th>H</th>
				<th>F</th>
				<th>H</th>
				<th>F</th>
				<th></th>
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
	
	
		$interne = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE status = "Interne" AND etude_envisage="'.$filiere.'" AND (sex="'.$sex1.'" OR sex="'.$sex2.'")');
		
		$nbInterne = 0;
		while($int = $interne->fetch()){
			$nbInterne++;
		}

		$bungalow = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE status = "Bungalow" AND etude_envisage="'.$filiere.'" AND sex!="'.$sex1.'" AND sex!="'.$sex2.'"');
		
		$nbBung = 0;
		while($bung = $bungalow->fetch()){
			$nbNBung++;
		}

		$externe = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE status = "Externe" AND etude_envisage="'.$filiere.'" AND sex!="'.$sex1.'" AND sex!="'.$sex2.'"');
		
		$nbBung = 0;
		while($bung = $externe->fetch()){
			$nbNBung++;
		}
		
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
			<tr style="page-break-inside: avoid;">
				<td><?=$filiere?></td>
				<td><?=$nbadvt?></td>
				<td><?=$nbNonAdvt?></td>
				<td><?=$ttLevel1?></td>
				<td><?=$ttLevel2?></td>
				<td><?=$ttLevel3?></td>
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
			<tr style="page-break-inside: avoid;">
				<th>Total</th>
				<th><?=$tnbadvt?></th>
				<th><?=$tnbNonAdvt?></th>
				<th><?=$tLevel1?></th>
				<th><?=$tLevel2?></th>
				<th><?=$tLevel3?></th>
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