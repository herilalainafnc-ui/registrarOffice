<?php
require '../../data/backdb.php';

if(isset($_POST['types']) AND ($_POST['types']!= 'TOUT')){

	$types = $_POST['types'];
	$mention = $dtb->query("SELECT * FROM filiere WHERE filiere_sigle = '".$types."'ORDER BY filiere_id");

}elseif(isset($_POST['types']) AND ($_POST['types']== 'TOUT')){

	$mention = $dtb->query("SELECT * FROM filiere ORDER BY filiere_id");

}

$nbrs = 1;

while($affm = $mention->fetch()){?>

	<b style="font-size: 17px;">Mention <?=$title = $affm['filiere_description']?></b>

<?php 
if(empty($_POST['cours'])){
 ?>
 
<table class="tbl simpleTbl mb-1">
	<thead>
 			<tr>
 				<td style="width: 7%;">ID</td>
 				<td style="width: 37%;">Nom et prénom</td>
 				<td style="width: 10%;">Année</td>
 				<td style="width: 15%;">Contact</td>
 				<!-- <td style="width: 31%;">Mail</td> -->
 				<td style="width: 31%;">Parcours</td>

 			</tr>
 	</thead>
</table>
	<?php
}else{ echo "";}
		$anneescolaire = $_POST['anneescolaire'];
		
		$etudiant = $dtb->query("SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude < 4 ORDER BY annee_etude ASC, student_id ASC");
		if(!empty($_POST['master'])){
			$etudiant = $dtb->query("SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' ORDER BY annee_etude, etude_option, student_id");
		}
		
		if($_POST['annee_etude']<>"tout"){
			$annee_etude = $_POST['annee_etude'];
			$etudiant = $dtb->query("SELECT * FROM etudiant_second_semester_23 WHERE annee_scolaire='".$anneescolaire."' AND etude_envisage = '".$title."' AND annee_etude= '".$annee_etude."' ORDER BY annee_etude, etude_option, student_id");
		}
	
	$n = 1;
	while($affiche = $etudiant->fetch()){
 	?>
 	<div>
 		<table class="table">
 			<tr class="tr">
 				<td style="width: 7%; border : 1px solid black;<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?> padding: 5px"><b><?=$student_id = $affiche['student_id']?></b></td>
 				<td style="width: 37%; border : 1px solid black;<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?> padding: 5px"><?=$affiche['student_nom']." ".$affiche['student_prenom']?></td>
 				<td style="width: 10%; border : 1px solid black;<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?> padding: 5px"><?=$affiche['annee_etude']?></td>
 				<td style="width: 15%; border : 1px solid black;<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?> padding: 5px"><?=$affiche['student_tel']?></td>
 				<td style="width: 31%; border : 1px solid black;<?php if(empty($_POST['cours'])){ echo"border-bottom : 0px;"; } ?> padding: 5px"><?=$affiche['etude_option']?></td>
 			</tr>
 		</table>
 	</div>
	
	<?php
		$semestre = $_POST['semestre'];
		if(!empty($_POST['cours'])){
	?>
		<div style="margin-top: 5px;margin-bottom: 10px;">
	 		<table class="table">
		 		<thead>
		 			<tr>
		 				<td id="c1">Sigle</td>
		 				<td id="c2">Titre du cours</td>
		 				<td id="c3">Crédit</td>
		 				<?php 
		 				if(!empty($_POST['notes'])){
		 					echo "<td id='c3'>Notes/20</td>";
		 				}else{echo '';}

		 				if(!empty($_POST['signature'])){
		 					echo "<td id='c3'>Signature</td>";
		 				}
		 				if (!empty($_POST['remarque'])) {
		 					echo "<td id='c3' style='width:30%;'>Remarque</td>";
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
					<tr class="tr">

						<td id="c1"><?=$affcours['Sigle']?></td>
						<td id="c2"><?=$affcours['title_cours']?></td>
						<td id="c3"><?=$affcours['credit']?></td>
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
							$tcredit+= $credit + $affcours['credit'];
							$tnotes+= $notes + $affcours['grade'];
						$nn++;
		 				}
		 			 ?>
		 		</tbody>
		 		<tfoot>
		 			<tr>
		 				<td id="f1"></td>
		 				<td id="f2"></td>
		 				<td id="f3"><b><?php

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
	<div style="height: 20px; background: #c4c4c4; padding: 5px;">
		<table>
			<tr>
				<td style="width: 50%"><b>Total des étudiants dans la mention <?=$title?></b></td>
				<td style="width: 50%"><b><?=$nombresS = ($n-1);?></b></td>
			</tr>
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
<div style="height: 20px; background: #dbdbdb; padding: 10px; border: 1px solid black; margin-top: 10px;">
		<table>
			<tr>
				<td style="width: 50%"><b>Nombre total de tous les étudiants</b></td>
				<td style="width: 50%"><b><?=$nombre?></b></td>
			</tr>
		</table>
</div>