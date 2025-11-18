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
 

<table class="tbl mb-0">
	<thead class="bg-slate-200">
 			<tr>
 				<td class="w-[40px] border-x px-1">No</td>
 				<td class="w-[55px] border-r px-1">ID</td>
 				<td class="w-[300px] border-r px-1">Nom et prénom</td>
 				<td class="border-r w-[70px] px-1">Niveau</td>
 				
 				<?php 
				if ($exportation == "abnment" OR $exportation == "internat") {
					?>
				<td class="w-[80px] border-r px-1">Sexe</td>
				<td class="w-[80px] border-r px-1">Résidence</td>
					<?php 
				}else{
 				 ?>
 				<td class="border-r px-1 text-right">Adresse Email</td>
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

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id ='".$session_id."' AND etude_mention='".$etude_mention."' AND new_student = 1 ORDER BY niveau_std ASC, student_id ASC");

				}else{

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' ORDER BY niveau_std ASC, student_id ASC");

				}
			
			}elseif($exportation == "internat"){
				
				if (!empty($_POST['new_student'])) {

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND niveau_std < 4 AND status = 'Interne' AND new_student = 1 ORDER BY niveau_std ASC, student_id ASC");

				}else{
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND niveau_std < 4 AND status = 'Interne' ORDER BY niveau_std ASC, student_id ASC");

				}
				

			}elseif($exportation == "abnment"){
				
				if (!empty($_POST['new_student'])) {

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND niveau_std < 4 AND abonment_std = 1 AND new_student = 1 ORDER BY niveau_std ASC, student_id ASC");
					
				}else{

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND niveau_std < 4 AND abonment_std = 1 ORDER BY niveau_std ASC, student_id ASC");

				}
				

			}
		

		if(!empty($_POST['master'])){
			
			if ($exportation == "general") {
				
				if (!empty($_POST['new_student'])) {

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND new_student = 1 ORDER BY niveau_std, etude_mention, student_id");

					
				}else{

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' ORDER BY niveau_std, etude_mention, student_id");

				}
				

			}elseif($exportation == "internat"){
				
				if (!empty($_POST['new_student'])) {
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND status = 'Interne' AND new_student = 1 ORDER BY niveau_std, etude_mention, student_id");

				}else{
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND status = 'Interne' ORDER BY niveau_std, etude_mention, student_id");

				}
				

			}elseif($exportation == "abnment"){
				
				if (!empty($_POST['new_student'])) {
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND abonment = 1 AND new_student = 1 ORDER BY niveau_std, etude_mention, student_id");

				}else{
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND abonment = 1 ORDER BY niveau_std, etude_mention, student_id");

				}
				

			}
		
		
		}
		
		if($_POST['annee_etude']<>"tout"){

			$annee_etude = $_POST['annee_etude'];

			if ($exportation == "general") {
				
				if (!empty($_POST['new_student'])) {
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND new_student = 1 AND niveau_std = '".$annee_etude."' ORDER BY niveau_std, etude_mention, student_id");
				
				}else{

					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND niveau_std = '".$annee_etude."' ORDER BY niveau_std, etude_mention, student_id");
				
				}
				

			}elseif($exportation == "internat"){
				
				if (!empty($_POST['new_student'])) {
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND status = 'Interne' AND new_student = 1  AND niveau_std = '".$annee_etude."' ORDER BY niveau_std, etude_mention, student_id");

				}else{
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND status = 'Interne' AND niveau_std = '".$annee_etude."' ORDER BY niveau_std, etude_mention, student_id");

				}

			}elseif($exportation == "abonment"){
				if (!empty($_POST['new_student'])) {
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND abonment_std = 1 AND new_student = 1  AND niveau_std = '".$annee_etude."' ORDER BY niveau_std, etude_mention, student_id");
				
				}else{
					
					$etudiant = $dtb->query("SELECT * FROM t_2024_inscription_session WHERE session_id='".$session_id."' AND etude_mention='".$etude_mention."' AND abonment_std = 1 AND niveau_std = '".$annee_etude."' ORDER BY niveau_std, etude_mention, student_id");
				
				}
			}
			

		}
	
	$n = 1;
	$tn = 0;
	while($affiche = $etudiant->fetch()){
		
		$student_id = $affiche['student_id'];
		$annee_etude = $affiche['niveau_std'];
		$status = $affiche['status'];

		$findStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id="'.$student_id.'"');
		
		$showStd = $findStd->fetch();
		$student_id = $affiche['student_id'];
		$student_nom = $showStd['student_nom'];
		$student_prenom = $showStd['student_prenom'];
		$student_email = $showStd['student_email'];

 	?>
 	<div>
 		<table class="tbl mb-0">
 			
 			<tr class="<?php if(!empty($_POST['cours'])){ echo"bg-slate-400"; } ?>" style="page-break-inside: avoid;">

 				<td class="w-[40px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><b><?=$n?></b></td>
 				
 				<td class="w-[55px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><b><?=$student_id?></b></td>
 				
 				<td class="w-[300px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$student_nom." ".$student_prenom?></td>
 				
 				<td class="w-[70px] border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?php
				if($annee_etude<=3) {
					echo "Licence ".$annee_etude;
				}else{
					echo "Master ".($annee_etude-3);
				} ?></td>
 				
 				<?php 
				if ($exportation == "abnment" OR $exportation == "internat") {
					?>
				
				<td class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>" style="border-bottom: 0px; width: 80px"><?php if ($showStd['sex'] == 1){ echo "Masculin";}else{ echo "Feminin";}?></td>
				
				<td class="border-r <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>" style="border-bottom: 0px; width: 80px"><?=$status?></td>
					<?php 
				}else{
 				 ?>
 				
 				<td class="text-right <?php if(!empty($_POST['cours'])){ echo"text-white"; }?>"><?=$student_email?></td>

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