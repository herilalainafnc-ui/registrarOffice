<?php 
$yearNow = date('Y');

$semestre =$_POST['semestre'];
$yearScoolNow = $_POST['yearStatistic'];

$findSessionOnSS = $dtb->query('SELECT * FROM t_2023_session WHERE session_name ="'.$semestre.'" AND session_year = "'.$yearScoolNow.'"');

$showSessionOnSS = $findSessionOnSS->fetch();
$session_id = $showSessionOnSS['session_id'];
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

 $mentio = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle !="CPRE" AND filiere_sigle !="EDUC"');

 // Requête SQL pour récupérer les données groupées
$abonnee_H = 0;
$abonnee_F = 0;
$nonAbonnee_H = 0;
$nonAbonnee_F = 0;
$thorizontal = 0;

	while($mt = $mentio->fetch()){

		$filiere_sigle= $mt['filiere_sigle'];
		$mention = $mt['filiere_description'];
	

$result = $dtb->query('SELECT *,
           SUM(CASE WHEN abonment_std = 1 THEN 1 ELSE 0 END) AS Abonnee_H,
           SUM(CASE WHEN abonment_std = 1 THEN 1 ELSE 0 END) AS Abonnee_F,
           SUM(CASE WHEN abonment_std = 0 THEN 1 ELSE 0 END) AS NonAbonnee_H,
           SUM(CASE WHEN abonment_std = 0 THEN 1 ELSE 0 END) AS NonAbonnee_F
       
    FROM t_2024_inscription_session WHERE etude_mention = "'.$filiere_sigle.'" AND session_id = "'.$session_id.'" ORDER BY etude_mention');



           $row = $result->fetch();

            	echo "<tr>";   
	   				echo "<td>" . $mention . "</td>"; 
	                echo "<td>" . $row['Abonnee_H'] . "</td>";
	                echo "<td>" . $row['Abonnee_F'] . "</td>";
	                echo "<td>" . $row['NonAbonnee_H'] . "</td>";
	                echo "<td>" . $row['NonAbonnee_F'] . "</td>";
	 				echo "<td>".$sommeHoriz = 
	 				$row['Abonnee_H']+
					$row['Abonnee_F']+
					$row['NonAbonnee_H']+
					$row['NonAbonnee_F']
	 				."</td>";
    			echo "</tr>";
    		

$abonnee_H =+ $abonnee_H + $row['Abonnee_H'];
$abonnee_F =+ $abonnee_F + $row['Abonnee_F'];
$nonAbonnee_H =+ $nonAbonnee_H + $row['NonAbonnee_H'];
$nonAbonnee_F =+ $nonAbonnee_F + $row['NonAbonnee_F'];
$thorizontal =+ intval($thorizontal) +  intval($sommeHoriz);      
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