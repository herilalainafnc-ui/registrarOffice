<?php require('../init/.forPrint/top.forPrint.php'); ?>
<center>
	<b class="text-2xl">Liste d'étudiant</b>
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

while($affm = $mention->fetch()){ ?>

	<b style="font-size: 17px;">Mention <?=$title = $affm['filiere_description']?></b>

<?php 
if(empty($_POST['cours'])){
 ?>
 

<table class="tbl simpleTbl mb-0">
	<thead class="bg-slate-200">
 			<tr>
 				<td class="border-1" style="border-bottom: 0px; width: 40px">No</td>
 				<td class="border-1" style="border-bottom: 0px; width: 50px">ID</td>
 				<td class="border-1" style="border-bottom: 0px; width: 300px">Nom et prénom</td>
 				<!-- <td class="w-[160px] border-1" style="border-bottom: 0px;">Mention</td> -->
 				<td class="border-1" style="border-bottom: 0px; width: 60px">Niveau</td>
 				<!-- <td class="w- border-1" style="border-bottom: 0px;">Contact</td> -->
 				<td class="border-1" style="border-bottom: 0px;">Email</td>
 			</tr>
 	</thead>
</table>
	<?php
}else{ echo "";}
		$anneescolaire = $_POST['anneescolaire'];
		
		$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 ORDER BY annee_etude ASC, student_id ASC");
		if(!empty($_POST['master'])){
			$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' ORDER BY annee_etude, etude_option, student_id");
		}
		
		if($_POST['annee_etude']<>"tout"){
			$annee_etude = $_POST['annee_etude'];
			$etudiant = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude= '".$annee_etude."' ORDER BY annee_etude, etude_option, student_id");
		}
	
	$n = 1;
	while($affiche = $etudiant->fetch()){
 	?>
 	<div>
 		<table class="tbl simpleTbl mb-0">
 			<tr class="<?php if(!empty($_POST['cours'])){ echo"bg-slate-200"; } ?>" style="page-break-inside: avoid;">
 				<td class="w-[40px]" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?=$n?></td>
 				<td class="w-[50px]" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><b><?=$student_id = $affiche['student_id']?></b></td>
 				<td class="w-[300px]" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?=$affiche['student_nom']." ".$affiche['student_prenom']?></td>
 				<!-- <td class="w-[160px]" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?=$affiche['etude_option']?></td> -->
 				<td class="w-[60px]" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?php
				if($affiche['annee_etude']<=3) {
					echo "L ".$affiche['annee_etude'];
				}else{
					echo "M ".($affiche['annee_etude']-3);
				} ?></td>
 				<!-- <td class="w-" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>"><?=$affiche['student_tel']?></td> -->
 				<td class="w-" style="<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?>; text-align: right;"><?=$affiche['student_email']?></td>
 				
 			</tr>
 		</table>
 	</div>
	
	<?php
		$semestre = $_POST['semestre'];
		if(!empty($_POST['cours'])){
	?>
		<div style="margin-top: 5px;margin-bottom: 10px;">
	 		<table class="tbl simpleTbl mb-1">
		 		<thead class="bg-slate-800 text-white">
		 			<tr style="page-break-inside: avoid;">
		 				<td class='w-[100px]'>Sigle</td>
		 				<td class='w-[400px]'>Titre du cours</td>
		 				<td class='w-20'>Crédit</td>
		 				<?php 
		 				if(!empty($_POST['notes'])){
		 					echo "<td class='w-16'>Notes/20</td>";
		 				}else{echo '';}

		 				if(!empty($_POST['signature'])){
		 					echo "<td class=''>Signature</td>";
		 				}
		 				if (!empty($_POST['remarque'])) {
		 					echo "<td class='w-[120px]'>Remarque</td>";
		 				}
		 				 ?>
		 				
		 			</tr>
		 		</thead>
		 		<tbody class="bg-slate-100">
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
	}?>
	<div>
		<table class="tbl simpleTbl mb-1">
			<thead class="bg-slate-200">
				<tr style="page-break-inside: avoid;">
					<th class="border-1 w-[40px]"><b><?=$nombresS = ($n-1);?></b></th>
					<th class="border-1 w-[50px]"></th>
					<th class="border-1 w-[300px]"></th>
					<th class="border-1 w-[60px]"></th>
					<!-- <th class="border-1 w-[50px]"></th> -->
					<th class="border-1 w-"></th>
				</tr>
			</thead>
		</table>
 	</div>
<?php
							$nombre = 0;
							$cd[$nbrs] = $nombresS;
							foreach ($cd as $valeur){
								$nombre += $valeur;
							}
	$nbrs++;
}
?>
<div class="mt-4">
		<table class="tbl simpleTbl mb-1">
			<thead class="bg-slate-200">
				<tr>
					<th class="border-1 w-[40px]"><b><?=$nombre?></b></th>
					<th class="border-1 w-[50px]"></th>
					<th class="border-1 w-[300px]"><b>TOUT LES ETUDIANTS</b></th>
					<th class="border-1 w-[60px]"></th>
					<!-- <th class="border-1 w-[50px]"></th> -->
					<th class="border-1 w-"></th>
				</tr>
			</thead>
		</table>
</div><?php require('../init/.forPrint/foot.forPrint.php'); ?>