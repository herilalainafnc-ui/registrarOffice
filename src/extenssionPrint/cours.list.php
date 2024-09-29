<?php require('../init/.forPrint/top.forPrint.php');  ?>
<center>
	<b class="text-2xl">Liste de cours</b>
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

<table class="tbl">
	<thead class="bg-slate-200">
 			<tr>
 				<td class="w-[30px]" style="border-bottom: 0px;">No</td>
 				<td class="w-[80px]" style="border-bottom: 0px;">Sigle</td>
 				<td class="w-[300px]" style="border-bottom: 0px;">Cours</td>
 				<td class="w-[160px]" style="border-bottom: 0px;">Crédit</td>
 				<td class="w-[50px]" style="border-bottom: 0px;">Niveau</td>
 				<td class="w-" style="border-bottom: 0px;">Semestre</td>
 				<td class="w-" style="border-bottom: 0px;">Catégorie</td>
 			</tr>
 	</thead>
 	<tbody>
	<?php

		
		if($_POST['yearlevel'] == "tout" AND $_POST['semester'] == "tout") {
			if (!empty($_POST['master'])) {
				$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc = '".$affm['filiere_sigle']."' ORDER BY yearlevel, semester ASC");

			}else{
				$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc = '".$affm['filiere_sigle']."' AND yearlevel < 4 ORDER BY yearlevel, semester ASC");			
			}
			
		}elseif($_POST['yearlevel'] <> "tout" AND $_POST['semester'] <> "tout") {
			
			$yearlevel = $_POST['yearlevel'];
			$semester = $_POST['semester'];	

			$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc = '".$affm['filiere_sigle']."' AND yearlevel= '".$yearlevel."' AND semester='".$semester."' ORDER BY yearlevel, id");
			

		}elseif($_POST['yearlevel'] == "tout" AND $_POST['semester'] <> "tout") {

			$semester = $_POST['semester'];

			if (!empty($_POST['master'])) {

				$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc = '".$affm['filiere_sigle']."' AND semester='".$semester."' ORDER BY yearlevel, id");
			}else{
				$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc = '".$affm['filiere_sigle']."' AND yearlevel < 4 AND semester='".$semester."' ORDER BY yearlevel, id");
			}
		}elseif($_POST['yearlevel'] <> "tout" AND $_POST['semester'] == "tout") {

			$yearlevel = $_POST['yearlevel'];

				$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc = '".$affm['filiere_sigle']."' AND yearlevel='".$yearlevel."' ORDER BY yearlevel, id");
				 
		}

	
	$n = 1;
	while($affiche = $cours->fetch()){
 	?>
 			<tr>
 				
 				<td class="w-[30px]"><?=$n?></td>
 				<td class="w-[80px]"><b><?=$id = $affiche['Sigle']?></b></td>
 				<td class="w-[300px]"><?=$affiche['title']?></td>
 				<td class="w-[160px]"><?=$affiche['nb_crd']?></td>
 				<td class="w-[50px]">L<?=$affiche['yearlevel']?></td>
 				<td class="w-"><?=$affiche['semester']?></td>
 				<td class="w-"><?php 
if ($affiche['category'] == 0){
	echo "Général";
}elseif ($affiche['category'] == 1) {
	echo "Majeur";
}elseif ($affiche['category'] == -1 OR $affiche['category'] == 2) {
	echo "Selective";
}elseif ($affiche['category'] == 3) {
	echo "Additionnel";
}elseif ($affiche['category'] == 5) {
	echo "``";
}else{
	echo "-";
}


 			?></td>
 				
 			</tr>
 		<?php
		$n++;
	}?>

 	</tbody>
	<thead class="bg-slate-200">
		<tr>
			<th colspan="7"><b><?=$nombresS = ($n-1);?></b></th>
		</tr>
	</thead>
</table>
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
					<th class="border-1 w-[30px]" colspan="2"><b><?=$nombre?></b></th>
					<th class="border-1 w-[300px]" colspan="4"><b>TOUT LES COURS</b></th>
				</tr>
			</thead>
		</table>
</div><?php require('../init/.forPrint/foot.forPrint.php'); ?>