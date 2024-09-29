<?php
require('../init/.forPrint/top.forPrint.php'); 
$student_id = $_GET['student_id'];
$level = $_GET['level'];

$printName = $student_id."-WORKED_HOURS";

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');

$stdA = $searchStd->fetch();

?>
<br>
<div class="border border-black flex text-xs px-1 py-1">
	<div class="w-10/12 flex">
		<div class="text-right w-4/12">
			<label>Matricule - </label><br>
			<label>Noms - </label><br>
			<label>Mention - </label><br>
			<label>Niveau - </label><br>
			<label>Mail / </label>
			<label>Contact - </label><br>
			<label>TRAVAIL - </label><br>
			<label>SUPERVISEUR - </label>
		</div>
		<div class="w-8/12 pl-1">
			<b><?=$student_id?></b><br>
			<b><?=strtoupper($stdA['student_nom'])." ".$stdA['student_prenom']?></b><br>
			<b><?=$stdA['etude_envisage']?></b><br>
			
			<b><?php 
	if($stdA['annee_etude'] <=3 ){
		echo "Licence ".$stdA['annee_etude'];
	}elseif ($stdA['annee_etude'] > 3){
		echo "Master ".($stdA['annee_etude'] - 3);
	}
?></b><br>
			<b><?=$stdA['student_email']?></b>
			<b>/ <?=$stdA['student_tel']?></b><br>
			<b><input type="text" placeholder="_____________________________" class="h-4 p-0 border-0 text-xs"></b><br>
			<b><input type="text" placeholder="_____________________________" class="h-4 p-0 border-0 text-xs"></b>
		</div>
	</div>
	<div class="w-2/12">
		<img src="../app/photosetudiants/<?=$stdA['image_student']?>">
	</div>
</div>

<center>
	<b class="text-2xl">Worked</b>

</center>

<div style="width: 100%; font-family: arial; font-size: 13px;">

<table style="width: 100%" class="tbl contenu">
	<thead>
		<tr>
			<th class="border-r border-b">Date</th>
			<th colspan="2" class="border-r border-b">Dimanche</th>
			<th colspan="2" class="border-r border-b">Lundi</th>
			<th colspan="2" class="border-r border-b">Mardi</th>
			<th colspan="2" class="border-r border-b">Mercredi</th>
			<th colspan="2" class="border-r border-b">Jeudi</th>
			<th colspan="2" class="border-b">Vendredi</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th class="border-r border-b"></th>
			<th class="border-r border-b">Durée</th>
			<th class="border-r border-b">Sign</th>
			<th class="border-r border-b">Durée</th>
			<th class="border-r border-b">Sign</th>
			<th class="border-r border-b">Durée</th>
			<th class="border-r border-b">Sign</th>
			<th class="border-r border-b">Durée</th>
			<th class="border-r border-b">Sign</th>
			<th class="border-r border-b">Durée</th>
			<th class="border-r border-b">Sign</th>
			<th class="border-r border-b">Durée</th>
			<th class="border-b">Sign</th>
		</tr>
	</tbody>
	<tbody>
		<?php for ($i=0; $i <15 ; $i++) { 
		 ?>
		<tr>
			<td class="border-r border-b">___/___/____</td>
			<td class="border-r border-b">____hr</td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b">____hr</td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b">____hr</td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b">____hr</td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b">____hr</td>
			<td class="border-r border-b"></td>
			<td class="border-r border-b">____hr</td>
			<td class="border-b"></td>

		</tr>
		<?php 
		}
		 ?>
	</tbody>
</table>
<br>
<div style="width: 100%; text-align: right;">
	<label>Note : <b>____/ 20</b></label><br><br>
	<label>Signature et nom de superviseur</label>
	<br><br><br>
	__________________

</div>

</div>