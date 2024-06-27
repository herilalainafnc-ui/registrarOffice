<?php require ('../data/backdb.php');
	
	$printName = "LISTE_ETUDIANT_FOP";
	$types = $_POST['types'];

	if (isset($_POST['yearMesupres']) AND $_POST['yearMesupres'] != "") {
		
		$annee_scolaire = $_POST['yearMesupres'];

	}elseif(isset($_POST['yearMesupres']) AND $_POST['yearMesupres'] == ""){

		$mois = date('m');
		if ($mois >= 10) {
			$annee_scolaire = date('Y')." - ".(date('Y')+1);
		}else{
			$annee_scolaire = (date('Y')-1)." - ".date('Y'); 
		}
			
	}
?>

<div class="mb-2">
	<center>
		<b>Requête de données MESUPRES - Année académique <?=$annee_scolaire?></b>
	</center>
</div>

<div>
<?php 
	if ($types == "TOUT") {
		$mention = $dtb->query('SELECT * FROM filiere');
	}else{
		$mention = $dtb->query('SELECT * FROM filiere WHERE filiere_sigle = "'.$types.'"');
	}
	

	while($mnt = $mention->fetch()){
		$ment = $mnt['filiere_description'];
 ?>
 <h4 class="text-center bg-cyan-700 text-white"><b><?=$ment?></b></h4>
	<table class="simpleTbl mb-2 text-xs">
		<thead class="bg-slate-200">
			<tr>
				<th class="w-[]">N°</th>
				<th class="w-[]">INSTITUTION</th>
				<th class="w-[]">DOMAINE</th>
				<th class="w-[]">MENTION</th>
				<th class="w-[]">PARCOURS</th>
				<th class="w-[]">TYPE DE FORMATION</th>
				<th class="w-[]">NIVEAU</th>
				<th class="w-[]">NOM</th>
				<th class="w-[]">PRÉNOMS</th>
				<th class="w-[]">SEXE</th>
				<th class="w-[]">DATE DE NAISSANCE</th>
				<th class="w-[]">CIN</th>
				<th class="w-[]">NATIONALITÉ</th>
				<th class="w-[]">ANNÉE D’OBTENTION DU BACC</th>
				<th class="w-[]">SÉRIE DU BACC</th>
				<th class="w-[]">CODE DE REDOUBLEMENT</th>
				<th class="w-[]">BOURSIER</th>
				<th class="w-[]">TAUX DE BOURSE</th>
				<th class="w-[]">ADRESSE EXACTE</th>
				<th class="w-[]">NUMERO TELEPHONE</th>
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
				<td><?="Privée"?></td>
				<td><?php
$domaine = $dtb->query('SELECT * FROM filiere WHERE filiere_description = "'.$af['etude_envisage'].'"');
$dmn = $domaine->fetch();
echo $dmn['domaine_signe'];
			?></td>

				<td><?=$af['etude_envisage'];?></td>
				
				<td><?=$af['etude_option']?></td>
				<td><?='FI'?></td>
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
				
				<td><?=$af['student_nom']?></td>
				<td><?=$af['student_prenom']?></td>
				<td><?php
if($af['sex'] == 1 OR $af['sex'] == "Masculin") {
	echo "M";
}else{
	echo "F";
}				?></td>
				<td><?=$af['dateNaissance']?></td>
				<td><?php if($af['num_cin']==0){ echo "";}else{ echo "_".$af['num_cin'];}?></td>
				<td><?=$af['nationalite']?></td>
				
				<td></td>
				
				<td></td>
				
				

				

				<td>N</td>
				
				<td><?php if ($af['sponsor_nom'] != ""){ echo "OUI";}else{ echo "NON";} ?></td>
				<td><?php if ($af['sponsor_nom'] != ""){ echo "1";}else{ echo "";} ?></td>
				<td><?=$af['student_adresse']?></td>
				<td><?=$af['student_tel']?></td>
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