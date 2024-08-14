<?php require ('../data/backdb.php');
	
	$printName = "LISTE_ETUDIANT_FOP";
	$types = $_POST['types'];

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

<div class="mb-2">
	<center>
		<b>Requête de données FOP - Année académique <?=$annee_scolaire?></b>
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
	<table class="simpleTbl mb-2">
		<thead class="bg-slate-200">
			<tr>
				<th class="w-[30px]">N°</th>
				<th class="w-[20px]">N° d'enregistrement</th>
				<th class="w-[300px]">Nom et prénoms</th>
				<th class="w-[100px]">Date de naissance</th>
				<th class="w-[200px]">Lieu de naissance</th>
				<th class="w-[100px]">N° Carte d'Identité Nationale</th>
				<th class="w-[100px]">Date de délivrance CIN</th>
				<th class="w-[20px]">Niveau</th>
				<th class="w-[]">Département</th>
				<th class="w-[20px]">Domaine</th>
				<th class="w-[20px]">Mention de la filière</th>
				<th class="w-[100px]">Parcours</th>
				<th class="w-[]">Secteur</th>
				<th class="w-[]">Spécialité</th>
				<th class="w-[]">Option</th>
			</tr>
		</thead>
		<tbody>
<?php
$std = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire = "'.$annee_scolaire.'" AND etude_envisage = "'.$ment.'" ORDER BY annee_etude,student_id');	
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
				<td><?=$af['num_cin']?></td>
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