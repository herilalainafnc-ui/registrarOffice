<?php 
$level = $_GET['level'];
$semester = $_GET['semester'];
$student_id = $_GET['student_id'];
$yes = 1;
$initA = 1;
$initS = 1;
$printName = $student_id."-BULLETIN";
 
if ($level > 3) {

	$initA = 4;

}elseif($level <= 3) {
	
	if($level == "all"){
		$level = $_GET['std_niveau'];
	}elseif($level == 2){
		$initA = 2;
	}elseif($level == 3){
		$initA = 3;
	}
	
}
if ($semester == "all") {
	$sem = 2;
}

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'" AND remove != 1');

$stdA = $searchStd->fetch();
 ?>


<div class="mb-24">

<center>
	<b class="text-2xl">Relevé de notes</b>

</center>

<div class="border border-black flex text-xs px-2 py-1">
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
<?php 
	for ($a=$initA; $a <= $level; $a++) {
		?>
			<div>
		<?php 
	echo "<div> <b>NIVEAU L".$a." 2023 - 2024</b>";
		
		if ($semester == 1) {
			$sem = 1;
		}elseif($semester == 2) {
			$initS = 2;
			$sem = 2;
		}

		for ($s=$initS; $s <=$sem ; $s++) { 
			
		
 ?>
		<table class="tbl simpleTbl mb-1">
			<thead>
				<tr class="text-center bg-slate-500 text-white">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">SIGLE</th>
					<th style="width: 400px">TITRE DU COURS</th>
					<th style="width: 50px">CREDITS</th>
					<th style="width: 50px">Categorie</th>
					<th style="width: 50px">Notes/20</th>
					<th style="width: 50px">Crd*Not</th>
					<th style="width: 30px">Etat</th>
				</tr>	
			</thead>
			<tbody>
	<?php
	$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND grade>10 AND ajout = '".$yes."' AND yearlevel='".$a."' AND semester='".$s."' AND remove != 1 ORDER BY id");
	
	$nbr = 0;
	$nbrMaj = 0;
	$credit = 0;
	$note = 0;
	$notecredit = 0;

	$nbrGen = 0;
	$tGen = 0;
	$tTGen = 0;

	/**/
	$nbrFinale = 0;
	$tFinale = 0;
	$tTFinale = 0;

	$tMaj = 0;
	$tTMaj = 0;
	$tcredit = 0;
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$tcreditMaj = 0;
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$tnote = 0;
	$tnotecredit = 0;
	
	if($cours->rowCount() > 0) {
		while ($crs = $cours->fetch()) {
			$note_id = $crs['id'];
			$session_id = $crs['session_id'];
			$annee_scolaire = $crs['annee_scolaire'];
	 ?>
				<tr>
					<td><?=$crs['Sigle']?></td>
					<td><?=$crs['title_cours']?></td>
					<td><?=$crs['credit']?></td>
					<td><?php 
if ($crs['cours_category'] == 0){
	echo "Général";
}elseif ($crs['cours_category'] == 1) {
	echo "Majeur";
}elseif ($crs['cours_category'] == -1 OR $crs['cours_category'] == 2) {
	echo "Selective";
}elseif ($crs['cours_category'] == 3) {
	echo "Additionnel";
}elseif ($crs['cours_category'] == 5) {
	echo "``";
}else{
	echo "-";
}
						 ?></td>
					<td><?=$crs['grade']?></td>
					<td><?=$notecredi = $crs['credit'] * $crs['grade']?></td>
					
					<td class="text-center"><?php 
if ($crs['grade'] < 10) {
	echo "E";
}elseif ($crs['grade'] == -2 OR $crs['grade'] > 10){
	echo "S";
}

							 ?></td>
<?php 
	if ($crs['cours_category'] == 1) {
		$valmajeur = $crs['grade'];
		$ident = 1;
	}else {
		$valmajeur = 0;
		$ident = 0;
	}
 ?>
				</tr>

	<?php
$credit = 0;
$notes = 0;
/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
$creditMaj = 0;
/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
$tcredit+= $credit + $crs['credit'];
/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {

	$tcreditMaj+=$creditMaj+ $crs['credit'];
	$nbrMaj++;

}else{
	$tcreditMaj+=$creditMaj+ 0;
}
/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
$tnote+= $note + $crs['grade'];
$tnotecredit+= $notecredit + $notecredi;

/* --- CALCULE DES NOTES GENERAL --- */

if (($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) {
	$gradeGen = $crs['grade'];
	$nbrGen++;
}else{
	$gradeGen = 0;
}
	$tTGen += $tGen + $gradeGen;

/* --- CALCULE DES NOTES MAJEURS --- */
 
if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
	
	$gradeMaj = $notecredi;

	$nbrMaj++;
}else{
	$gradeMaj = 0;
}

	$tTMaj += $tMaj + $gradeMaj;

/* --- CALCULE DES NOTES FINALES --- */
 
if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur") OR ($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) {
	$gradeFinale = $crs['grade'];
	$nbrFinale++;
}else{
	$gradeFinale = 0;
}

	$tTFinale += $tFinale + $gradeFinale;

		$nbr++;
		}
	}
	 ?>			
			</tbody>
			<tfoot>
				<tr class="bg-slate-200">
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?php if(!empty($tcredit)) { echo $tcredit;}?></th>
					<th></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnote,2);}?></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnotecredit,2);}?></th>
					<th colspan="2"></th>
				</tr>

<?php 
	$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'" AND remove != 1');
	$showPromotion = $searchPromotion->fetch();
	if (!empty($showPromotion)) {
		$grade_work_educ = $showPromotion['grade_work_educ'];
		$grade_remark_acad = $showPromotion['grade_remark_acad'];
		$grade_chapel_part = $showPromotion['grade_chapel_part'];
	}else{
		$grade_work_educ = "";
		$grade_remark_acad = "";
		$grade_chapel_part = "";
	}
 ?>

				<tr class=" text-black text-right">
					<td colspan="4">Note de Work Education</td>
					<td class="text-left"><?=$grade_work_educ?></td>
				</tr>
				
				<tr class="text-black text-right">
					<td colspan="4">Remarque académique</td>
					<td class="text-left"><?=$grade_remark_acad?></td>
				</tr>
				
				<tr class="text-black text-right">
					<td colspan="4">Note de participation à l'exercice de chapelle et à la semaine de prière</td>
					<td class="text-left"><?=$grade_chapel_part?></td>
				</tr>

				<!-- <tr>
					<th colspan="4" class="text-right">Moyenne Majeur</th>
					<th class="bg-slate-200"><?php if($nbrMaj != 0){echo round(($moyenMajSem = ($tTMaj/$nbrMaj)),6);}else{echo 0;$moyenMajSem = 0;}?></th>
				</tr> -->
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeur</th>
					<!-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
					<th class="bg-slate-200"><?php if($nbrMaj != 0){echo round(($moyenMajSem = ($tTMaj/$tcreditMaj)),6);}else{echo 0;$moyenMajSem =0;}?></th>
					<!-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
				</tr>
				
				<!--  -->
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="bg-cyan-700 text-white"><?php if($nbr != 0){echo round(($moyenGenSem = $tnotecredit/$tcredit),6);}else{echo 0;$moyenGenSem =0;}?></th>
				</tr>

				
				<!-- <tr>
					<th colspan="4" class="bg-slate-200 text-right">Moyenne Générale</th>
					<th class="bg-slate-200"><?php if($nbrGen != 0){echo $moyenGenSem = round(($tTGen/$nbrGen),2);}else{echo 0;}?></th>
				</tr>
				<tr>
					<th colspan="4" class="bg-slate-200 text-right">Moyenne Majeur</th>
					<th class="bg-slate-200"><?php if($nbrMaj != 0){echo $moyenMajSem = round(($tTMaj/$nbrMaj),2);}else{echo 0;}?></th>
				</tr>
				
				<tr>
					<th colspan="4" class="text-right">Moyenne</th>
					<th class="bg-cyan-700 text-white"><?php if($nbrFinale != 0){echo $moyenFinale = round(($tTFinale/$nbrFinale),2);}else{echo 0;$moyenFinale =0;}?></th>
				</tr> -->
			</tfoot>

		</table>
<?php
		}
	echo "</div></div>";
	}
?>
</div>

