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
		<b class="text-2xl">Remise de notes</b>
	</center>
<div class="border border-black flex text-xs px-2 py-1 my-2">
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
}elseif ($etude_envisage == "Cours Préparatoire") {
	$dep_desc = 'CPRE';
}elseif ($etude_envisage == "Tous les cours") {
	$dep_desc = 'TOUT';
}

	$parcour = $dtb->query('SELECT * FROM filiere_parcours WHERE description = "'.$etude_option.'"');
	$afparc = $parcour->fetch();
	
	$parcours = $afparc['shortcode'];
	
	$tout = 'all';


for ($y=1; $y <= 3; $y++) {
	for ($i=1; $i <=2 ; $i++) { 
		$course = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc='".$dep_desc."' AND yearlevel ='".$y."' AND semester ='".$i."' AND (category = 1 OR category = 0) AND (parcours = '".$parcours."' OR parcours = '".$tout."') ORDER BY title");
?>
<table class="tbl w-full">
	<thead>
		<tr style="background: #d7f0fb;">
			<th style="width: 20px">Nb</th>
			<th style="width: 70px">Sigle</th>
			<th>Cours - Année <?=$y;?> | Semestre <?=$i;?></th>
			<th style="width: 40px">Cr</th>
			<th style="width: 40px">Catég</th>
			<th style="width: 40px">Notes</th>
			<th style="width: 40px">Cr*Notes</th>
		</tr>
	</thead>
	<tbody>
<?php
	$n = 1;
	$nbrMaj = 0;
	$note = 0;
	$credit = 0;
	$tcredit = 0;
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
	$tcreditMaj = 0;
	$tnote = 0;
	$tnotecredit = 0;

	while ($cours_table = $course->fetch()) {
?>
	
		<tr>
			<td><?=$n;?></td>
			<td><b><?php echo $sigle = $cours_table['Sigle'];?></b></td>
			<td><?=$cours_table['title'];?></td>
			<td><?= $credit=$cours_table['nb_crd'];?></td>
			<td>
<?php 
	if ($cours_table['category'] == 0){
	echo "Général";
}elseif ($cours_table['category'] == 1) {
	echo "Majeur";
}elseif ($cours_table['category'] == -1 OR $crs['cours_category'] == 2) {
	echo "Selective";
}elseif ($cours_table['category'] == 3) {
	echo "Additionnel";
}elseif ($cours_table['category'] == 5) {
	echo "``";
}else{
	echo "-";
}

?>
			</td>
			<td>
<?php	
	//if (empty($nt)) {
	
		$finding = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id = "'.$student_id.'" AND title_cours LIKE "%'.$cours_table['title'].'%" AND credit = "'.$cours_table['nb_crd'].'" AND credit = "'.$cours_table['nb_crd'].'" ORDER BY grade DESC LIMIT 1');
		$showing = $finding->fetch();
		if(!empty($showing)){
			echo "<a style='color:green'>".$grade = $showing['grade']."</a>";
		}else{

			$firstSpace = strpos($cours_table['title']," ");
			$captFirstWord = substr($cours_table['title'], 0, $firstSpace);
			$suitWord = strchr($cours_table['title'],$firstSpace+1);


			$findingSecond = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id = "'.$student_id.'" AND title_cours LIKE "%'.$captFirstWord.'%" AND credit = "'.$cours_table['nb_crd'].'" ORDER BY grade DESC LIMIT 1');
			
			$showingSecond = $findingSecond->fetch();
				
				if(!empty($showingSecond)){
					echo "<a style='color:orange'>".$grade = $showingSecond['grade']."</a>";
				}else{

					$findingTierd = $dtb->query('SELECT * FROM t_2023_notes WHERE student_id = "'.$student_id.'" AND title_cours LIKE "%'.$suitWord.'%" AND credit = "'.$cours_table['nb_crd'].'" ORDER BY grade DESC LIMIT 1');
			
					$showingTierd = $findingTierd->fetch();
						if(!empty($showingTierd)){
							echo "<a style='color:red'>".$grade = $showingTierd['grade']."</a>";
						}


				}
			
			
		}
		
	// }elseif($nt['grade'] == 0){
	// 	echo "";
	// }
	// else{
	// 	echo round($nt['grade'],2);	
	// }
?>
			</td>
			<td><?= $noteCredi = floatval($grade) * intval($credit)?></td>
<?php 
	if ($cours_table['category'] == 1) {
		$valmajeur = $grade;
		$ident = 1;
	}else {
		$valmajeur = 0;
		$ident = 0;
	}
 ?>

		</tr>
	
<?php
$crdt = 0;
$notes = 0;
/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
$creditMaj = 0;
/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
$tcredit+= $crdt + $credit;
/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
if (($cours_table['category'] == 1) OR ($cours_table['category'] == "Majeur")) {

	$tcreditMaj+=$creditMaj + $credit;
	$nbrMaj++;

}else{
	$tcreditMaj+=$creditMaj+ 0;
}
/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/

$tnote+= $note + floatval($grade);
$tnotecredit+= $notecredit + $noteCredi;

/* --- CALCULE DES NOTES GENERAL --- */

if (($cours_table['category'] == 0) OR ($cours_table['category'] == "Général")) {
	$gradeGen = $grade;
	$nbrGen++;
}else{
	$gradeGen = 0;
}
	$tTGen += $tGen + floatval($gradeGen);

/* --- CALCULE DES NOTES MAJEURS --- */
 
if (($cours_table['category'] == 1) OR ($cours_table['category'] == "Majeur")) {
	$gradeMaj = $noteCredi;
	$nbrMaj++;
}else{
	$gradeMaj = 0;
}

	$tTMaj += $tMaj + $gradeMaj;

/* --- CALCULE DES NOTES FINALES --- */
 
if (($cours_table['category'] == 1) OR ($cours_table['category'] == "Majeur") OR ($cours_table['category'] == 0) OR ($cours_table['category'] == "Général")) {
	$gradeFinale = $grade;
	$nbrFinale++;
}else{
	$gradeFinale = 0;
}

	$tTFinale += $tFinale + floatval($gradeFinale);

		$tcredit+= $crdt + $credit;
		$n++;
	}
?>
	</tbody>
	<tfoot>
		<tr style="background : #e9e9e9">
			<td></td>
			<td></td>
			<td></td>
			<td><?=$tcredit?></td>
			<td></td>
			<td><?=$tnote?></td>
			<td><?=$tnotecredit?></td>
		</tr>
	</tfoot>	
</table>
<table class="tbl w-full" style="margin-top: 10px">
	<tfoot>
		<tr style="background : #e9e9e9">
			<td colspan="4" style="text-align: right;">Moyenne générale</td>
			<td style="width: 63px"><?php if($nbrMaj != 0){echo round(($moyenMajSem = ($tTMaj/$tcreditMaj)),6);}else{echo 0;$moyenMajSem =0;}?></td>
		</tr>
	</tfoot>
	<tfoot>
		<tr style="background : #e9e9e9">
			<td colspan="4" style="text-align: right;">Moyenne mageur</td>
			<td style="width: 63px"><?php if($n != 0){echo round(($moyenGenSem = $tnotecredit/$tcredit),6);}else{echo 0;$moyenGenSem =0;}?></td>
		</tr>
	</tfoot>
</table>
<br>

<?php
	}
}
 ?>
</div><?php require('../init/.forPrint/foot.forPrint.php'); ?>