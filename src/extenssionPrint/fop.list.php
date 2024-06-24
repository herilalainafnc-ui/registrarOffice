<?php require ('../../data/backdb.php');

	if (isset($_POST['yearFOP']) AND $_POST['yearFOP'] != "") {
		
		$annee_scolaire = $_POST['yearFOP'];

	}elseif(isset($_POST['yearFOP']) AND $_POST['yearFOP'] == ""){

		$mois = date('m');
		if ($mois >= 10) {
			$annee_scolaire = date('Y')." - ".(date('Y')+1);
		}else{
			$annee_scolaire = (date('Y')-1)." - ".date('Y'); 
		}
			
	}
?>

<div>
	<center>
		<h2>LISTE DES ETUDIANTS</h2>
		<h3>Requête de données pour la FOP <br> Année académique <?=$annee_scolaire?></h3>
		
	</center>
</div>

<div>
<?php 
	$mention = $dtb->query('SELECT * FROM filiere');

	while($mnt = $mention->fetch()){
		$ment = $mnt['filiere_description'];
 ?>
 <h4 style="background: #3ba7e9; text-align: center;"><?=$ment?></h4>
	<table class="tbl" style="font-size: 12px">
		<thead>
			<tr style="background: #6bb993;">
				<th style="width : 1%">N°</th>
				<th style="width : 5%">N° d'enregistrement</th>
				<th style="width : 15%">Nom et prénoms</th>
				<th style="width : 5%">Date de naissance</th>
				<th style="width : 15%">Lieu de naissance</th>
				<th style="width : 5%">N° Carte d'Identité Nationale</th>
				<th style="width : 5%">Date de délivrance CIN</th>
				<th style="width : 3%">Niveau</th>
				<th style="width : 10%">Département</th>
				<th style="width : 3%">Domaine</th>
				<th style="width : 10%">Mention de la filière</th>
				<th style="width : 10%">Parcours</th>
				<th style="width : 5%">Secteur</th>
				<th style="width : 5%">Spécialité</th>
				<th style="width : 5%">Option</th>
			</tr>
		</thead>
		<tbody>
<?php
$std = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire = "'.$annee_scolaire.'" AND etude_envisage = "'.$ment.'" ORDER BY annee_etude,student_id');	
$nb = 0;
	while($af = $std->fetch()){
		$nb++;
 ?>
			<tr>
				<td><?=$nb?></td>
				<td><?=$af['student_id']?></td>
				<td><?=$af['student_nom'].' '.$af['student_prenom']?></td>
				<td><?=$af['dateNaissance']?></td>
				<td><?=$af['lieuNaissance']?></td>
				<td>_<?=$af['num_cin']?></td>
				<td><?=$af['cin_date_delivre']?></td>
				<td><?php 
				$annee_etude = $af['annee_etude'];
				if ($annee_etude <= 3) {
					echo "L ".$annee_etude;
				}elseif($annee_etude == 4){
					echo "M 1";
				}elseif($annee_etude == 5){
					echo "M 2";
				}
				?></td>
				<td><?php 
			if($af['etude_envisage'] == 'Sciences Infirmières'){
				echo $af['etude_envisage'];
			}
			?></td>
				<td><?php
$domaine = $dtb->query('SELECT * FROM filiere WHERE filiere_description = "'.$af['etude_envisage'].'"');
$dmn = $domaine->fetch();
echo $dmn['domaine_signe'];
			?></td>
				<td><?php 
			if($af['etude_envisage'] != 'Sciences Infirmières'){
				echo $af['etude_envisage'];
			}
			?></td>
				<td><?=$af['etude_option']?></td>
				<td></td>
				<td><?php?></td>
				<td><?php?></td>
			</tr>


<?php 
	}
 ?>
		</tbody>
	</table>
<?php 
}
 ?>	
</div>


<style type="text/css">
	.tbl{
		border-collapse: collapse;
	}
	.tbl th,td{
		border: 1px solid grey;
	}
	.tbl th{
	}
</style>