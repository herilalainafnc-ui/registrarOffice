
<div>	
<?php 
	for ($a=1; $a <= $level; $a++) { 
?>
	<div class='p-1 bg-slate-700 hover:bg-slate-600 mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
<b>
		<?php 
		if($a<=3) {
			echo "NIVEAU Licence ".$a;
		}else{
			echo "NIVEAU Master ".($a-3);
		}
		?>		
</b>	

<?php	
		for ($s=1; $s <=2 ; $s++) { 
			
		
 ?>
		<table class="simpleTbl mb-1">
			<thead>
				<tr class="text-center bg-gradient-to-r from-cyan-500">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="bg-slate-800 text-white">
				<tr>
					<th class="w-20">SIGLE</th>
					<th class="w-">TITRE DU COURS</th>
					<th class="w-20">CREDITS</th>
					<th class="w-20">Categorie</th>
					<th class="w-20">Notes/20</th>
					<th class="w-20">Crd X Not</th>
					<th class="w-4">Etat</th>
				</tr>	
			</thead>
			<tbody class="bg-slate-500">
	<?php
	$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND grade>10 AND ajout = '".$yes."' AND yearlevel='".$a."' AND semester='".$s."' ORDER BY id");
	
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
				<tr class="hover:transition-all duration-75 hover:bg-slate-400 hover:text-black">
					<td class="bg-gradient-to-r from-orange-800 to-orange-400"><?=$crs['Sigle']?></td>
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
					
					<td class="<?php 
if ($crs['grade'] < 10) {
	echo "bg-red-500";
}else{
	echo "bg-green-500";
}

					 ?> text-center"><?php 
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
			<tfoot class="bg-slate-800 text-white">
				<tr>
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?php if(!empty($tcredit)) { echo $tcredit;}?></th>
					<th></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnote,2);}?></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnotecredit,2);}?></th>
					<th colspan="2"></th>
				</tr>

<?php
	
	if(!empty($session_id)){
		$searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');
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

				<tr class="bg-slate-500 text-right">
					<td colspan="4">Note de Work Education</td>
					<td class="text-left"><?=$grade_work_educ?></td>
				</tr>
				
				<tr class="bg-slate-500 text-right">
					<td colspan="4">Remarque académique</td>
					<td class="text-left"><?=$grade_remark_acad?></td>
				</tr>
				
				<tr class="bg-slate-500 text-right">
					<td colspan="4">Note de participation à l'exercice de chapelle et à la semaine de prière</td>
					<td class="text-left"><?=$grade_chapel_part?></td>
				</tr>
<?php 
	}
 ?>
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeur</th>
					<!-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
					<th class="px-2"><?php if($nbrMaj != 0){echo round(($moyenMajSem = ($tTMaj/$tcreditMaj)),6);}else{echo 0;$moyenMajSem =0;}?></th>
					<!-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
				</tr>
				
				<!--  -->
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2 bg-cyan-700"><?php if($nbr != 0){echo round(($moyenGenSem = $tnotecredit/$tcredit),6);}else{echo 0;$moyenGenSem =0;}?></th>
				</tr>


				<!-- <tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th><?php if($nbrGen != 0){echo $moyenGenSem = round(($tTGen/$nbrGen),2);}else{echo 0;}?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeur</th>
					<th><?php if($nbrMaj != 0){echo $moyenMajSem = round(($tTMaj/$nbrMaj),2);}else{echo 0;}?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right">Moyenne</th>
					<th class="bg-cyan-700"><?php if($nbrFinale != 0){echo $moyenFinale = round(($tTFinale/$nbrFinale),2);}else{echo 0;$moyenFinale =0;}?></th>
				</tr> -->
			</tfoot>

		</table>
<?php
		}
	echo "</div>";
	}
?>
</div>

