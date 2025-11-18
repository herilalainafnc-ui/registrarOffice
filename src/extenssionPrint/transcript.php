<?php 
require('../init/.forPrint/top.forPrint.php');

$level = $_GET['level'];
$semester = $_GET['semester'];
$student_id = $_GET['student_id'];
$yes = 1;
if($_GET['std_niveau'] <=3) {
    $initA = 1;	
    $stage = "Licence";
}elseif($_GET['std_niveau'] >3){
    $initA = 4;
    $stage = "Master";
}

$initS = 1;
$printName = $student_id."-TRANSCRIPT_SEMMESTRE";
 
if($level == "all"){
    $level = $_GET['std_niveau'];
}elseif($level == 2){
    $initA = 2;
}elseif($level == 3){
    $initA = 3;
}

if ($semester == "all") {
    $sem = 2;
}else{
    $sem = $_GET['semester'];
}

$searchStd = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id = "'.$student_id.'"');
$stdA = $searchStd->fetch();

// Récupérer l'année académique du premier cours du premier niveau/semestre affiché
$annee_scolaire_db = "Non spécifiée";
$coursAnnee = $dtb->query("SELECT annee_scolaire FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout = '".$yes."' AND yearlevel='".$initA."' AND semester='".$initS."' ORDER BY id LIMIT 1");
if ($coursAnnee->rowCount() > 0) {
    $firstCourse = $coursAnnee->fetch();
    $annee_scolaire_db = $firstCourse['annee_scolaire'];
}
?>

<div class="mb-24" id="exportToExcel">

<center>
    <b class="text-2xl">Résultat académique</b>
</center>

<div class="flex text-xs px-1 py-1" style="border: 1px solid #8e9bb2;">
	<div class="w-10/12" style="display: flex;">
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
    <div class="w-2/12">
		<img src="../app/photosetudiants/<?=$stdA['image_student']?>">
    </div>
</div>

<div><b>Année académique : <?=$annee_scolaire_db?></b></div>
	
<?php 
	$workNote = 0;
	$remarkAcad = 0;
	$chapel = 0;
	$gen = 0;
	$maj = 0;

	/**/ 
	$finale = 0;
	/**/
	$cumulFinale = 0;

	$cumulWorkNote = 0;
	$cumulremarkAcad = 0;
	$cumulChapel = 0;
	$cumulGen = 0;
	$cumulMaj = 0;

	for ($a=$initA; $a <= $level; $a++) {
		?>
			<div>
		<?php
	echo "<div> <b>NIVEAU ".$stage." ";

	if($a<=3) {
			echo $a;
			$nbrA = $a+1;
		}else{
			echo ($a-3);
			$nbrA = $a-2;
		}
		
		if ($semester == 1) {
			$sem = 1;
		}elseif($semester == 2) {
			$initS = 2;
			$sem = 2;
		}
	echo "</b>";
		for ($s=$initS; $s <=$sem ; $s++) { 
			
		
 ?>

		<table class="tbl mb-1" style="page-break-inside: avoid;">
			<thead>
				<tr class="text-center bg-slate-500 text-white">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="bg-slate-200">
				<tr>
					<th style="width: 100px">Sigle</th>
					<th style="width: 400px">Titre du cours</th>
					<th style="width: 50px">Crédits</th>
					<th style="width: 50px">Catégorie</th>
					<th style="width: 50px">Notes/20</th>
					<th style="width: 60px">Crd*Not</th>
					<th style="width: 30px">État</th>
				</tr>	
			</thead>
			<tbody>
	<?php
	$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout = '".$yes."' AND yearlevel='".$a."' AND semester='".$s."' ORDER BY id");
	
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
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$tcreditMaj = 0;
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$tnote = 0;
	$tnotecredit = 0;
	
		if($cours->rowCount() > 0) {
			while ($crs = $cours->fetch()) {
				// Filtre : ignorer les cours avec note 0
				if ($crs['grade'] == 0) {
					continue;
				}
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
	$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');
	$showPromotion = $searchPromotion->fetch();
	if (!empty($showPromotion)) {
		$grade_work_educ = $showPromotion['grade_work_educ'];
		$grade_remark_acad = $showPromotion['grade_remark_acad'];
		$grade_chapel_part = $showPromotion['grade_chapel_part'];
	}else{
		$grade_work_educ = 0;
		$grade_remark_acad = 0;
		$grade_chapel_part = 0;
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
					<th colspan="4" class="bg-slate-200 text-right">Moyenne Générale</th>
					<th class="bg-slate-200"><?php if($nbrGen != 0){echo $moyenGenSem = round(($tTGen/$nbrGen),2);}else{echo 0;$moyenGenSem =0;}?></th>
				</tr>
				<tr>
					<th colspan="4" class="bg-slate-200 text-right">Moyenne Majeur</th>
					<th class="bg-slate-200"><?php if($nbrMaj != 0){echo $moyenMajSem = round(($tTMaj/$nbrMaj),2);}else{echo$moyenMajSem =0; 0;}?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right">Moyenne</th>
					<th class="bg-slate-400"><?php if($nbrFinale != 0){echo $moyenFinale = round(($tTFinale/$nbrFinale),2);}else{echo 0;$moyenFinale =0;}?></th>
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

			</tfoot>

		</table>

<?php
		$cumulWorkNote += $workNote + $grade_work_educ;
		$cumulremarkAcad += $remarkAcad + $grade_remark_acad;
		$cumulChapel += $chapel + $grade_chapel_part;
		$cumulGen += $gen + $moyenGenSem;
		$cumulMaj += $maj + $moyenMajSem;

		/**/
		$cumulFinale += $finale + $moyenFinale;

		}
	echo "</div></div>";
	}
?>

<!-- CUMULATIVE -->
<div>
	<br>
	<table class="tbl mb-1 w-full">
		<tbody>
			<tr class="border-r border-b">
				<th colspan="2" class="bg-slate-200 text-center">RÉCAPITULATION</th>
			</tr>
			<tr class="border-r border-b">
				<td class=" w-8/12 text-right">Note de Work Education cumulative</td>
				<td class=" w-2/12 text-bold"><?=round(($cumulWorkNote*20)/((($a-1)*2)*20),3);?></td>
			</tr>
			<!-- <tr class="border-r border-b">
				<td class=" w-8/12 text-right">Nemarque académique cumulative</td>
				<td class=" w-2/12 text-bold"><?=round(($cumulremarkAcad*20)/((($a-1)*2)*20),3);?></td>
			</tr> -->
			<tr class="border-r border-b">
				<td class=" w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class=" w-2/12 text-bold"><?=round(($cumulChapel*20)/((($a-1)*2)*20),3);?></td>
			</tr>
		</tbody>
	</table>
	<table class="simpleTbl mb-1 w-full">
		<thead>
			<tr>
				<th class=" w-8/12 text-right">Moyenne Générale Cumulative</th>
				<th class=" w-2/12"><?=round(($cumulGen*20)/((($nbrA-1)*2)*20),3);?></th>
			</tr>
			<tr>
				<th class=" w-8/12 text-right">Moyenne Majeur Cumulative</th>
				<th class=" w-2/12"><?=round(($cumulMaj*20)/((($nbrA-1)*2)*20),3);?></th>
			</tr>
			<!--  -->
			<tr>
				<th class=" w-8/12 text-right">Moyenne Cumulative</th>
				<th class=" w-2/12"><?=round(($cumulFinale*20)/((($nbrA-1)*2)*20),3);?></th>
			</tr>
		</thead>
	</table>
</div>
</div>
<?php require('../init/.forPrint/foot.forPrint.php'); ?>