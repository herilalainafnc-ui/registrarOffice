<?php
$student_id = $_GET['student_id'];
$level = $_GET['level'];

$printName = "Worked_point-".$student_id;

$searchStd = $dtb->query('SELECT * FROM etudiant_second_semester_23 WHERE student_id = "'.$student_id.'"');

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
			
			<b>L<?=$stdA['annee_etude']?></b><br>
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

<table style="width: 100%" class="contenu">
	<thead>
		<tr>
			<th>Date</th>
			<th colspan="2">Dimanche</th>
			<th colspan="2">Lundi</th>
			<th colspan="2">Mardi</th>
			<th colspan="2">Mercredi</th>
			<th colspan="2">Jeudi</th>
			<th colspan="2">Vendredi</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th></th>
			<th>Durée</th>
			<th>Sign</th>
			<th>Durée</th>
			<th>Sign</th>
			<th>Durée</th>
			<th>Sign</th>
			<th>Durée</th>
			<th>Sign</th>
			<th>Durée</th>
			<th>Sign</th>
			<th>Durée</th>
			<th>Sign</th>
		</tr>
	</tbody>
	<tbody>
		<?php for ($i=0; $i <15 ; $i++) { 
		 ?>
		<tr>
			<td>___/___/____</td>
			<td>____hr</td>
			<td></td>
			<td>____hr</td>
			<td></td>
			<td>____hr</td>
			<td></td>
			<td>____hr</td>
			<td></td>
			<td>____hr</td>
			<td></td>
			<td>____hr</td>
			<td></td>

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

<style type="text/css">
	.contenu{
		border: 1px solid black;
		border-collapse: collapse;
		font-size: 13px;
	}
	.contenu th,.contenu td{
		border: 1px solid black;
		text-align: center;
	}
	.contenu td{
		height: 35px;
	}
</style>