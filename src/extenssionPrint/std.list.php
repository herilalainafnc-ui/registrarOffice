<?php 
	
	require('../init/.forPrint/top.forPrint.php');
	//require('../init/.forPrint/top.sa.php');
	$exportation = $_POST['exportation'];
	
?>
<center>
	<b class="">Liste des <?php if (!empty($_POST['new_student'])) { echo "nouveaux";}?> étudiants <?php 
	if($exportation == "internat"){
	
		echo "internes";
	
	}elseif($exportation == "abnment"){
	
		echo "abonnée";	
	
	}

echo " en ".$_POST['anneescolaire'];

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
	
	$mention = $dtb->query("SELECT * FROM filiere WHERE filiere_sigle = '".$types."'ORDER BY filiere_id");

}elseif(isset($_POST['types']) AND ($_POST['types']== 'TOUT')){

	$mention = $dtb->query("SELECT * FROM filiere ORDER BY filiere_id");

}

$nbrs = 1;
$nombre = 0;
while($affm = $mention->fetch()){ ?>

	<b class="text-sm">Mention <?=$title = $affm['filiere_description']?></b><br>

<?php 
if(empty($_POST['cours'])){
 ?>
 

<table class="tbl mb-0">
	<thead class="bg-slate-200">
 			<tr>
 				<td class="w-[40px] border-x px-1">No</td>
 				<td class="w-[50px] border-r px-1">ID</td>
 				<td class="w-[400px] border-r px-1">Nom et prénom</td>
 				<!-- <td class="w-[160px] border-1" style="border-bottom: 0px;">Mention</td> -->
 				<td class="border-r w-[70px] px-1">Niveau</td>
 				<!-- <td class="w- border-1" style="border-bottom: 0px;">Contact</td> -->
 				<?php 
				if ($exportation == "abnment" OR $exportation == "internat") {
					?>
				<td class="w-[80px] border-r px-1">Sexe</td>
				<td class="w-[80px] border-r px-1">Résidence</td>
					<?php 
				}else{
 				 ?>
 				<td class="border-r px-1 text-right">Adresse Email</td>
 				<!-- <td class="border-r px-1 text-right">Signature</td> -->
 				 <?php 
				}
				?>
 				
 			</tr>
 	</thead>
</table>
	<?php
}else{ echo "";}
		$anneescolaire = $_POST['anneescolaire'];
			if ($exportation == "general") {
				
				if (!empty($_POST['new_student'])) {
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 AND new_student = 1 ORDER BY annee_etude ASC, student_id ASC");
				}else{
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 ORDER BY annee_etude ASC, student_id ASC");
				}
			
			}elseif($exportation == "internat"){
				
				if (!empty($_POST['new_student'])) {
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 AND status = 'Interne' AND new_student = 1 ORDER BY annee_etude ASC, student_id ASC");
				}else{
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 AND status = 'Interne' ORDER BY annee_etude ASC, student_id ASC");
				}
				

			}elseif($exportation == "abnment"){
				
				if (!empty($_POST['new_student'])) {
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 AND abonment = 1 AND new_student = 1 ORDER BY annee_etude ASC, student_id ASC");
				}else{
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 AND abonment = 1 ORDER BY annee_etude ASC, student_id ASC");
				}
				

			}
		

		if(!empty($_POST['master'])){
			if ($exportation == "general") {
				
				if (!empty($_POST['new_student'])) {
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND new_student = 1 ORDER BY annee_etude, etude_option, student_id");
				}else{
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' ORDER BY annee_etude, etude_option, student_id");
				}
				

			}elseif($exportation == "internat"){
				
				if (!empty($_POST['new_student'])) {
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND status = 'Interne' AND new_student = 1 ORDER BY annee_etude, etude_option, student_id");
				}else{
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND status = 'Interne' ORDER BY annee_etude, etude_option, student_id");
				}
				

			}elseif($exportation == "abnment"){
				if (!empty($_POST['new_student'])) {
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND abonment = 1 AND new_student = 1 ORDER BY annee_etude, etude_option, student_id");
				}else{
					$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND abonment = 1 ORDER BY annee_etude, etude_option, student_id");
				}
				

			}
		
		
		}
		
		if($_POST['annee_etude']<>"tout"){

			$annee_etude = $_POST['annee_etude'];

			if ($exportation == "general") {
if (!empty($_POST['new_student'])) {
}else{
}
				$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude= '".$annee_etude."' ORDER BY annee_etude, etude_option, student_id");

			}elseif($exportation == "internat"){
if (!empty($_POST['new_student'])) {
}else{
}
				$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude= '".$annee_etude."' AND status = 'Interne' ORDER BY annee_etude, etude_option, student_id");

			}elseif($exportation == "abnment"){
if (!empty($_POST['new_student'])) {
}else{
}
				$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude= '".$annee_etude."' AND abonment = 1 ORDER BY annee_etude, etude_option, student_id");
			}
			

		}
	
	$n = 1;
	$tn = 0;
	while($affiche = $etudiant->fetch()){
 	?>
 	<div>
 		<table class="tbl mb-0">
 			
 			<tr class="<?php if(!empty($_POST['cours'])){ echo"bg-slate-400"; } ?>" style="page-break-inside: avoid;">

 				<td class="w-[40px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><b><?=$n?></b></td>
 				
 				<td class="w-[50px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><b><?=$student_id = $affiche['student_id']?></b></td>
 				
 				<td class="w-[400px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$affiche['student_nom']." ".$affiche['student_prenom']?></td>
 				
 				<!-- <td class="w-[160px]" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?=$affiche['etude_option']?></td> -->
 				
 				<td class="w-[70px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?php
				if($affiche['annee_etude']<=3) {
					echo "Licence ".$affiche['annee_etude'];
				}else{
					echo "Master ".($affiche['annee_etude']-3);
				} ?></td>
 				
 				<!-- <td class="w-" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?=$affiche['student_tel']?></td> -->
 				<?php 
				if ($exportation == "abnment" OR $exportation == "internat") {
					?>
				
				<td class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>" style="border-bottom: 0px; width: 80px"><?php if ($affiche['sex'] == 1){ echo "Masculin";}else{ echo "Feminin";}?></td>
				
				<td class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>" style="border-bottom: 0px; width: 80px"><?=$affiche['status']?></td>
					<?php 
				}else{
 				 ?>
 				
 				<td class="text-right <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$affiche['student_email']?></td>
 				<!-- <td style="height: 40px"></td> -->
 				 <?php 
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

			}elseif($_POST['annee_etude']!='tout' AND $_POST['semestre']='tout'){
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id = '".$student_id."' AND ajout = 1 AND annee_scolaire='".$annee_scolaire."' AND yearlevel='".$annee_etude."' ORDER BY title_cours");

			}elseif($_POST['annee_etude']=='tout' AND $_POST['semestre']!='tout'){
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id = '".$student_id."' AND ajout = 1 AND annee_scolaire='".$annee_scolaire."' AND semester='".$semestre."' ORDER BY title_cours");
			
			}elseif($_POST['annee_etude']='tout' AND $_POST['semestre']='tout'){
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