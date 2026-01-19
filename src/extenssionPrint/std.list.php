<?php 
	
	require('../init/.forPrint/top.forPrint.php');
	//require('../init/.forPrint/top.sa.php');
	$exportation = $_POST['exportation'];
	$semestre = $_POST['semestre'];
	$yearScoolNow = $_POST['anneescolaire'];
	
	$findSessionOnSS = $dtb->query('SELECT * FROM t_2023_session WHERE session_semester ="'.$semestre.'" AND session_year = "'.$yearScoolNow.'"');

	$showSessionOnSS = $findSessionOnSS->fetch();
	$session_id = $showSessionOnSS['session_id'];
	$session_name = $showSessionOnSS['session_name'];
	$session_year = $showSessionOnSS['session_year'];

?>
<center>
	<b class="">Liste des <?php if (!empty($_POST['new_student'])) { echo "nouveaux";}?> étudiants <?php 
	if($exportation == "internat"){
	
		echo "internes";
	
	}elseif($exportation == "abnment"){
	
		echo "abonnée";	
	
	}elseif($exportation == "adventiste"){
	
		echo "Adventistes";	
	
	}elseif($exportation == "non_adventiste"){
	
		echo "Non-Adventistes";	
	
	}

echo " <br><em class='text-xs'>".$session_name."  ".$session_year."</em>";

	if (!empty($_POST['cours'])) {
		echo " avec ces cours";
		if (!empty($_POST['notes'])) {
			echo ' et les notes';
		}
	}
	 ?></b>
</center>

<?php
	$printName = "LISTE_ETUDIANT";
	

if(isset($_POST['types']) AND ($_POST['types']!= 'TOUT')){

	$types = $_POST['types'];
	
	$mention = $dtb->query("SELECT * FROM filiere WHERE filiere_sigle = '".$types."' ORDER BY filiere_id");

}elseif(isset($_POST['types']) AND ($_POST['types']== 'TOUT')){

	$mention = $dtb->query("SELECT * FROM filiere ORDER BY filiere_id");

}

$nbrs = 1;
$nombre = 0;
while($affm = $mention->fetch()){ ?>

	<b class="text-sm">Mention <?=$title = $affm['filiere_description']?></b><br>


<?php
	
	$etude_mention = $affm['filiere_sigle'];

if(empty($_POST['cours'])){
 ?>
 

<table class="tbl mb-0" style="table-layout: fixed; width: 100%;">
	<thead class="bg-slate-200">
 			<tr>
 				<td style="width: 35px;" class="border-x px-1">No</td>
 				<td style="width: 75px;" class="border-r px-1">ID</td>
 				<td style="width: 230px;" class="border-r px-1">Nom et prénom</td>
 				<td style="width: 60px;" class="border-r px-1">Niveau</td>
 				
 				<?php 
				if ($exportation == "abnment" OR $exportation == "internat") {
					?>
				<td style="width: 70px;" class="border-r px-1">Sexe</td>
				<td style="width: 70px;" class="border-r px-1">Résidence</td>
					<?php 
				}elseif ($exportation == "adventiste" OR $exportation == "non_adventiste") {
					?>
				<td style="width: 70px;" class="border-r px-1">Sexe</td>
				<td style="width: 100px;" class="border-r px-1">Religion</td>
					<?php 
				}else{
 				 ?>
 				<td class="border-r px-1">Adresse Email</td>
 				 <?php 
				}
				
				if(!empty($_POST['signature'])){
					echo '<td style="width: 100px;" class="border-r px-1">Signature</td>';
				}
				if(!empty($_POST['remarque'])){
					echo '<td style="width: 120px;" class="border-r px-1">Remarque</td>';
				}
				?>
 				
 			</tr>
 	</thead>
</table>
	<?php
}else{ echo "";}
		$anneescolaire = $_POST['anneescolaire'];
		
		// Construction de la requête de base
		$sql = "SELECT ins.*, std.annee_etude as real_niveau, std.graduated 
				FROM t_2024_inscription_session ins 
				INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id 
				WHERE ins.session_id = '".$session_id."' 
				AND ins.etude_mention = '".$etude_mention."'
				AND (std.graduated IS NULL OR std.graduated != 1)";
		
		// Filtre par type d'exportation
		if ($exportation == "internat") {
			$sql .= " AND ins.status = 'Interne'";
		} elseif ($exportation == "abnment") {
			$sql .= " AND ins.abonment_std = 1";
		} elseif ($exportation == "adventiste") {
			$sql .= " AND (std.religion = 'Adventiste' OR std.religion = 'Adventiste du Septieme-jour')";
		} elseif ($exportation == "non_adventiste") {
			$sql .= " AND std.religion != 'Adventiste' AND std.religion != 'Adventiste du Septieme-jour'";
		}
		
		// Filtre nouveaux étudiants
		if (!empty($_POST['new_student'])) {
			$sql .= " AND ins.new_student = 1";
		}
		
		// Filtre par niveau (utilise le vrai niveau de tbl_2024_etudiant)
		if ($_POST['annee_etude'] != "tout") {
			$niveau_filtre = $_POST['annee_etude'];
			$sql .= " AND std.annee_etude = '".$niveau_filtre."'";
		} else {
			// Si pas de filtre niveau et pas "avec MASTER", limiter à Licence (niveau < 4)
			if (empty($_POST['master'])) {
				$sql .= " AND std.annee_etude < 4";
			}
		}
		
		// Éviter les doublons
		$sql .= " GROUP BY ins.student_id";
		
		$sql .= " ORDER BY std.annee_etude ASC, ins.student_id ASC";
		
		$etudiant = $dtb->query($sql);
	
	$n = 1;
	$tn = 0;
	while($affiche = $etudiant->fetch()){
		
		$student_id = $affiche['student_id'];
		$status = $affiche['status'];
		
		// Utiliser annee_etude de la jointure (real_niveau)
		$annee_etude = $affiche['real_niveau'];

		$findStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id="'.$student_id.'"');
		
		$showStd = $findStd->fetch();
		
		// Ne pas afficher si l'étudiant n'existe pas dans tbl_2024_etudiant
		if(!$showStd){
			continue;
		}
		
		$student_nom = $showStd['student_nom'];
		$student_prenom = $showStd['student_prenom'];
		$student_email = $showStd['student_email'];

 	?>
 	<div>
 		<table class="tbl mb-0" style="table-layout: fixed; width: 100%;">
 			
 			<tr class="<?php if(!empty($_POST['cours'])){ echo"bg-slate-400"; } ?>" style="page-break-inside: avoid;">

 				<td style="width: 35px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><b><?=$n?></b></td>
 				
 				<td style="width: 75px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><b><?=$student_id?></b></td>
 				
 				<td style="width: 230px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$student_nom." ".$student_prenom?></td>
 				
 				<td style="width: 60px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?php
				if($annee_etude == 0){
					echo "L1 (RN)";
				}elseif($annee_etude<=3) {
					echo "L".$annee_etude;
				}else{
					echo "M".($annee_etude-3);
				} ?></td>
 				
 				<?php 
				if ($exportation == "abnment" OR $exportation == "internat") {
					?>
				
				<td style="width: 70px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?php if ($showStd['sex'] == 1){ echo "Masculin";}else{ echo "Feminin";}?></td>
				
				<td style="width: 70px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$status?></td>
					<?php 
					if(!empty($_POST['signature'])){
						echo '<td style="width: 100px;" class="border-r"></td>';
					}
					if(!empty($_POST['remarque'])){
						echo '<td style="width: 120px;" class="border-r"></td>';
					}
				}elseif ($exportation == "adventiste" OR $exportation == "non_adventiste") {
					?>
				
				<td style="width: 70px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?php if ($showStd['sex'] == 1){ echo "Masculin";}else{ echo "Feminin";}?></td>
				
				<td style="width: 100px;" class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$showStd['religion']?></td>
					<?php 
					if(!empty($_POST['signature'])){
						echo '<td style="width: 100px;" class="border-r"></td>';
					}
					if(!empty($_POST['remarque'])){
						echo '<td style="width: 120px;" class="border-r"></td>';
					}
				}else{
 				 ?>
 				
 				<td class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$student_email?></td>

 				 <?php 
					if(!empty($_POST['signature'])){
						echo '<td style="width: 100px;" class="border-l"></td>';
					}
					if(!empty($_POST['remarque'])){
						echo '<td style="width: 120px;" class="border-l"></td>';
					}
				}
				?>
 	
 			</tr>

 		</table>
 	</div>
	
	<?php
		$semestre = $_POST['semestre'];
		if(!empty($_POST['cours'])){
	?>
		<div class="mb-2">
	 		<table class="tbl mb-1">
		 		<thead class="bg-orange-200">
		 			<tr style="page-break-inside: avoid;">
		 				
		 				<td class='w-[100px] px-1'><b>Sigle</b></td>
		 				
		 				<td class='w-[400px] px-1'><b>Titre du cours</b></td>
		 				
		 				<td class='w-20 px-1'><b>Crédit</b></td>
		 				
		 				<?php 
		 				if(!empty($_POST['notes'])){
		 					echo "<td class='w-16 px-1'><b>Notes/20</b></td>";
		 				}else{echo '';}

		 				if(!empty($_POST['signature'])){
		 					echo "<td class=' px-1'><b>Signature</b></td>";
		 				}
		 				if (!empty($_POST['remarque'])) {
		 					echo "<td class='w-[120px] px-1'><b>Remarque</b></td>";
		 				}
		 				 ?>
		 				
		 			</tr>
		 		</thead>
		 		<tbody>
		 			<?php
		 			$annee_scolaire = $_POST['anneescolaire'];
		 	if($_POST['annee_etude']!='tout' AND $_POST['semestre']!='tout'){	
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout = 1 AND annee_scolaire='".$annee_scolaire."' AND yearlevel='".$annee_etude."' AND semester='".$semestre."' ORDER BY title_cours");

			}elseif($_POST['annee_etude']!='tout' AND $_POST['semestre']=='tout'){
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id = '".$student_id."' AND ajout = 1 AND annee_scolaire='".$annee_scolaire."' AND yearlevel='".$annee_etude."' ORDER BY title_cours");

			}elseif($_POST['annee_etude']=='tout' AND $_POST['semestre']!='tout'){
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id = '".$student_id."' AND ajout = 1 AND annee_scolaire='".$annee_scolaire."' AND semester='".$semestre."' ORDER BY title_cours");
			
			}elseif($_POST['annee_etude']=='tout' AND $_POST['semestre']=='tout'){
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id = '".$student_id."' AND ajout = 1 AND annee_scolaire='".$annee_scolaire."' ORDER BY title_cours");

			}
		 				$nn = 1;
		 				$credit = 0;
		 				$notes = 0;
		 				$tcredit = 0;
		 				$tnotes = 0;
		 				while($affcours = $cours->fetch()){
					?>
					<tr style="page-break-inside: avoid;">

						<td><?=$affcours['Sigle']?></td>
						<td><?=$affcours['title_cours']?></td>
						<td><?=$affcours['credit']?></td>
						<?php 
			 				if(!empty($_POST['notes'])){
			 					echo "<td id='c3'>".$affcours['grade']."</td>";
			 				}else{echo '';}
			 				if(!empty($_POST['signature'])){
			 					echo "<td id='c3'></td>";
			 				}
			 				if (!empty($_POST['remarque'])) {
			 					echo "<td id='c3' style='width:30%;'></td>";
			 				}
		 				 ?>
		 				
					</tr>
					<?php
							$tcredit+= $credit + intval($affcours['credit']);
							$tnotes+= $notes + intval($affcours['grade']);
						$nn++;
		 				}
		 			 ?>
		 		</tbody>
		 		<tfoot>
		 			<tr style="page-break-inside: avoid;">
		 				<td></td>
		 				<td></td>
		 				<td><b><?php

		 				if (!empty($tcredit)){echo $tcredit;}else{echo'0';}?></b></td>
		 				<?php 
		 				if(!empty($_POST['notes'])){
		 						echo "<td id='f3'>".$tnotes."</td>";	
		 				}
		 				 ?>
		 			</tr>
		 		</tfoot>
		 	</table>
		 </div>
	<?php
		}
		$n++;
		
	}
			$tn = $n-1;
			$nombre= $nombre +$tn;	
	$nbrs++;
}
?>
<div class="mt-4">
		<table class="tbl mb-1">
			<thead class="bg-slate-200">
				<tr>
					<th><b><?=$nombre?></b></th>
					<th colspan="4"><b>Total de tout les étudiants</b></th>
				</tr>
			</thead>
		</table>
</div><?php require('../init/.forPrint/foot.forPrint.php'); ?>