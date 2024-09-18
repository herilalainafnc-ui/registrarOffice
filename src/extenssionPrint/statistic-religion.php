<?php 
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
</div>