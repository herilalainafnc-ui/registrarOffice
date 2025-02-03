<?php
require('../init/.forPrint/top.forPrint.php');
$id = $_GET['id'];
$student_id = $_GET['student_id'];
$student_nom = $_GET['student_nom'];
$student_prenom = $_GET['student_prenom'];
$etude_envisage = $_GET['etude_envisage'];
$etude_option = $_GET['etude_option'];

$student_tel = $_GET['student_tel'];
$image_student = $_GET['image_student'];

$printName = $student_id."-CHECKLIST";



$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');

$stdA = $searchStd->fetch();
?>

	<center>
		<b class="text-2xl">Check list</b>
	</center>
<div class="flex text-xs px-2 py-1 my-2" style="border: 1px solid #8e9bb2;">
	<div class="w-full flex">
		<div class="text-right w-4/12">
			<label>Matricule - </label><br>
			<label>Noms - </label><br>
			<label>Mention - </label><br>
			<label>Parcours - </label><br>
			<label>Niveau - </label><br>
			<label>Mail / </label>
			<label>Contact - </label><br>
			<label>Adresse - </label>
		</div>
		<div class="w-8/12 pl-1">
			<b><?=$student_id?></b><br>
			<b><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></b><br>
			<b><?=$stdA['etude_envisage']?></b><br>
			<b><?=$stdA['etude_option']?></b><br>
			<b>L<?=$stdA['annee_etude']?></b><br>
			<b><?=$stdA['student_email']?></b>
			<b>/ <?=$stdA['student_tel']?></b><br>
			<b><?=$stdA['student_adresse']?></b>
		</div>
	</div>
	
</div>

<div style="font-family: corbel, sans-serif;">
<?php
if ($etude_envisage == "Théologie") {
	$dep_desc = 'THEO';
}elseif ($etude_envisage == "Gestion") {
	$dep_desc = 'GEST';
}elseif ($etude_envisage == "Informatique") {
	$dep_desc = 'INFO';
}elseif ($etude_envisage == "Sciences Infirmières") {
	$dep_desc = 'NURS';
}elseif ($etude_envisage == "Education") {
	$dep_desc = 'EDUC';
}elseif ($etude_envisage == "Communication") {
	$dep_desc = 'COMM';
}elseif ($etude_envisage == "Etudes anglophones") {
	$dep_desc = 'LANG';
}elseif ($etude_envisage == "Droit") {
	$dep_desc = 'DROI';
}elseif ($etude_envisage == "Cours Préparatoire") {
	$dep_desc = 'CPRE';
}elseif ($etude_envisage == "Tous les cours") {
	$dep_desc = 'TOUT';
}

	$parcour = $dtb->query('SELECT * FROM filiere_parcours WHERE description = "'.$etude_option.'"');
	$afparc = $parcour->fetch();
	
	$parcours = $afparc['shortcode'];
	
	$tout = 'all';

	$courseselective = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc='".$dep_desc."' AND yearlevel = 1 AND semester = 1 AND (parcours = '".$parcours."' OR parcours = '".$tout."') AND category = 2 AND remove != 1  ORDER BY title");
	$cours_table_selective = $courseselective->fetch()
?>
<!-- <table class="tbl w-full">

	<thead>
<?php 
if(!empty($cours_table_selective)){
 ?>
		<tr>	
			<th class="border-r border-b" style="width: 20px">Nb</th>
			<th class="border-r border-b" style="width: 90px">Sigle</th>
			<th class="border-r border-b">Cours Séléctive - Semestre 1</th>
			<th class="border-r border-b" style="width: 30px">Crd</th>
			<th class="border-r border-b" style="width: 20px">Ch</th>
			<th class="border-r border-b" style="width: 50px">Final</th>
		</tr>
<?php 
}
 ?>
	</thead>
	<tbody>
<?php
	$n_selective = 1;
	$credit_selective = 0;
	$tcredit_selective = 0;
	while ($cours_table_selective = $courseselective->fetch()) {
?>
		<tr>
			
			<td class="border-r border-b"><?=$n_selective;?></td>
			<td class="sigle border-r border-b"><?php echo $sigle = $cours_table_selective['Sigle'];?></td>
			<td class="border-r border-b"><?=$cours_table_selective['title'];?></td>
			<td class="border-r border-b"><?=$cours_table_selective['nb_crd'];?></td>
			<td class="border-r border-b">
<?php 
	$notes_selective = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id = "'.$student_id.'" AND Sigle = "'.$sigle.'" AND ajout = 1 OR remove = 0 LIMIT 1');
	$nt_selective = $notes_selective->fetch();
	if (empty($nt_selective)) {
		echo "";
	}elseif($nt_selective['grade'] == 0){
		echo "";
	}
	else{
		echo "<i class='bi-check text-green-700'></i>";	
	}

?>
			</td>
			<td class="border-r border-b">
<?php	
	if (empty($nt_selective)) {
		$searchcours = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id = "'.$student_id.'" AND title_cours LIKE "%'.$cours_table_selective['title'].'%" AND (ajout = 1)');
		$showCours = $searchcours->fetch();
		if (!empty($showCours)) {
			echo $showCours['grade'];
		}else{
			echo "";
		}
	}elseif($nt_selective['grade'] == 0){
		echo "";
	}
	else{
		echo $nt_selective['grade'];	
	}
?>
			</td>
		</tr>
<?php
		$tcredit_selective+= $credit_selective + $cours_table_selective['nb_crd'];
		$n_selective++;
	}
?>
	</tbody>
	<tfoot>
<?php 
if(!empty($cours_table_selective)){
 ?>		
		<tr>		
			<td class="border-r border-b"></td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b"><?=$tcredit_selective?></td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b"></td>
		</tr>
<?php 
}
 ?>
	</tfoot>
</table><br>
 -->


<?php
for ($y=1; $y <= 3; $y++) {
	for ($i=1; $i <=2 ; $i++) { 
		$course = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc='".$dep_desc."' AND yearlevel ='".$y."' AND semester ='".$i."' AND (category = 1 OR category = 0) AND (parcours = '".$parcours."' OR parcours = '".$tout."') AND remove != 1 ORDER BY title");
?>
<div class="pt-1">
	
<table class="tbl w-full">
	<thead>
		<tr style="page-break-inside: avoid;">
			<th class="border-r border-b" style="width: 70px">Prérequis</th>
			<th class="border-r border-b" style="width: 20px">Nb</th>
			<th class="border-r border-b" style="width: 90px">Sigle</th>
			<th class="border-r border-b">Année <?=$y;?> - Semestre <?=$i;?></th>
			<th  class="border-r border-b"style="width: 30px">Crd</th>
			<th class="border-r border-b" style="width: 20px">Ch</th>
			<th class="border-r border-b" style="width: 50px">Final</th>
		</tr>
	</thead>
	<tbody>
<?php
	$n = 1;
	$credit = 0;
	$tcredit = 0;
	while ($cours_table = $course->fetch()) {
?>
	
		<tr style="page-break-inside: avoid;">
			<td class="border-r border-b"><em>-</em></td>
			<td class="border-r border-b"><?=$n;?></td>
			<td class="sigle border-r border-b"><b><?php echo $sigle = $cours_table['Sigle'];?></b></td>
			<td class="border-r border-b"><?=$cours_table['title'];?></td>
			<td class="border-r border-b"><?=$cours_table['nb_crd'];?></td>
			<td class="border-r border-b">
<?php 
	$notes = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id = "'.$student_id.'" AND Sigle = "'.$sigle.'" AND (ajout = 1) LIMIT 1');
	$nt = $notes->fetch();
	if (empty($nt)) {
		echo "";
	}elseif($nt['grade'] == 0){
		echo "";
	}
	else{
		echo "<b class='text-green-700'><i class='bi-check'></i></b>";	
	}

?>
			</td>
			<td class="border-r border-b">
<?php	
	if (empty($nt)) {
		echo "";
	}elseif($nt['grade'] == 0){
		echo "";
	}
	else{
		echo round($nt['grade'],2);	
	}
?>
			</td>
		</tr>
	
<?php
		$tcredit+= $credit + $cours_table['nb_crd'];
		$n++;
	}
?>
	</tbody>
	<tfoot>
		<tr style="page-break-inside: avoid;">
			<td style="border: none"></td>
			<td style="border: none"></td>
			<td style="border: none"></td>
			<td style="border: none"></td>
			<td style="border: none"><?=$tcredit?></td>
			<td style="border: none"></td>
			<td style="border: none"></td>
		</tr>
	</tfoot>
</table>
</div>
<?php
	}
}
 ?>
</div><?php require('../init/.forPrint/foot.forPrint.php'); ?>