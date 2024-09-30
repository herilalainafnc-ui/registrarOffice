<?php 
	require('../init/.forPrint/top.forPrint.php'); 
	$cours_id = $_GET['cours_id'];
	$yearRemiseNotes = $_GET['yearRemiseNotes'];

	$findCours = $dtb->query('SELECT * FROM t_2023_cours WHERE id = "'.$cours_id.'"');

	$showCours = $findCours->fetch();

	$printName = "NOTES-STD-IN_".$showCours['title'];
 ?>

<div class="text-xs text-center my-2" style="border: 1px solid #8e9bb2;">
	<p class="text-[17px] bg-blue-200 h-[20px]">Remise de notes du cours - [ <b><?=$showCours['title']?></b> ]</p>
	<div class="w-full p-2" style="display: flex;">

		<div class="text-right w-3/12">
			<label>Sigle - </label><br>
			<label>Catégorie - </label><br>
			<label>Enseignant(e) - </label><br>
			<label>Année - </label><br>			
		</div>
		<div class="w-4/12 text-left pl-1">
			
			<b><?=$showCours['Sigle']?></b><br>
			<b><?php
if ($showCours['category'] == 0){
	echo "Général";
}elseif ($showCours['category'] == 1) {
	echo "Majeur";
}elseif ($showCours['category'] == -1 OR $showCours['category'] == 2) {
	echo "Selective";
}elseif ($showCours['category'] == 3) {
	echo "Additionnel";
}elseif ($showCours['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
			?></b><br>
			<b><?php 
	
	$findTeacher = $dtb->query('SELECT * FROM teacher WHERE uid ="'.$showCours['id_teacher'].'"');
	$showTeacher = $findTeacher->fetch();
	
	echo $showTeacher['name']." ".$showTeacher['lastName'];
			?></b><br>
			<b><?=$yearRemiseNotes?></b><br>		
		</div>
		<div class="text-right w-3/12">
			<label>Crédit - </label><br>
			<label>Niveau - </label><br>
			<label>Semestre - </label><br>		
		</div>
		<div class="w-4/12 text-left pl-1">
			<b><?=$showCours['nb_crd']?></b><br>
			<b><?php if($showCours['yearlevel'] <=3 ){
				echo "Licence ".$showCours['yearlevel'];
			}elseif ($showCours['yearlevel'] > 3){
				echo "Master ".($showCours['yearlevel'] - 3);
			}
				?></b><br>
			<b><?=$showCours['semester']?></b><br>		
		</div>
	</div>
</div>

<b>Liste des étudiants</b>
 <table class="tbl">
 	<thead>
 		<tr>
 			<th class="py-1 border-l border-slate-400 w-[40px]">No</th>
 			<th class="py-1 border-l border-slate-400 w-[80px]">Matricule</th>
 			<th class="py-1 border-l border-slate-400">Noms</th>
 			<th class="py-1 border-l border-slate-400 w-[80px]">Niveau</th>
 			<th class="py-1 border-l border-slate-400 w-[80px]">Examen<br>Mi sem__%</th>
 			<th class="py-1 border-l border-slate-400 w-[80px]">Examen<br>Final__%</th>
 			<th class="py-1 border-l border-slate-400 w-[80px]">Notes<br>Final/20</th>
 		</tr>
 	</thead>
 	<tbody>
 <?php 
 $findStdInCours = $dtb->query('SELECT * FROM t_2023_notes WHERE id_cours = "'.$cours_id.'" AND annee_scolaire = "'.$yearRemiseNotes.'" ORDER BY student_id');
 $nbr = 1;
 while($showStdInCours = $findStdInCours->fetch()) {
  ?>
 		<tr class="text-[11px]" style="page-break-inside: avoid;">
 			<td class="py-1 border-l border-t border-slate-400"><?=$nbr?></td>
 			<td class="py-1 border-l border-t border-slate-400"><?=$showStdInCours['student_id']?></td>
 			<td class="py-1 border-l border-t border-slate-400"><?php

$jer = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE student_id='".$showStdInCours['student_id']."'");
$apotr = $jer->fetch();
if($apotr){
	if(is_null($apotr['student_nom']) AND is_null($apotr['student_prenom'])){
		echo "<em style='color:red'>Non défini</em>";
	}else{
		echo $apotr['student_nom']." ".$apotr['student_prenom'];
	}
}else{
	echo "<em style='color:red'>Etudiant non inscrit dans la base!!</em>";
}
					?></td>
 			<td class="py-1 border-l border-t border-slate-400"><?php if($apotr['annee_etude'] <=3 ){
				echo "Licence ".$apotr['annee_etude'];
			}elseif ($apotr['annee_etude'] > 3){
				echo "Master ".($apotr['annee_etude'] - 3);
			}
				?></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<td class="py-1 border-l border-t border-slate-400"></td>
 			<td class="py-1 border-l border-t border-slate-400 text-bold text-center" style="color: red"><?php if ($showStdInCours['grade'] != 0) { echo $showStdInCours['grade'];	}?></td>
 		</tr>
 <?php
 $nbr++;
	}
  ?>
 	</tbody>
 </table>

 <b><?php if ($nbr > 2) { echo ($nbr-1)." étudiants"; }else{ echo ($nbr-1)." étudiant"; } ?></b>


 <div class="flex w-full text-right">
 	<div class="w-9/12">
 		
 	</div>
 	<div class="w-3/12 pb-16 border-b border-slate-600">
 		<p class="text-xs">Signature est Noms de l'enseignant(e).</p>

 	</div>
 </div><?php require('../init/.forPrint/foot.forPrint.php'); ?>