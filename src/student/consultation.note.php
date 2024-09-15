
<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">	
<?php 
	/*::::::::::::::::::::::::::::::::::::::::*/
	if ($level > 3) {
		$init = 4;
	}elseif($level <= 3) {
		$init = 1;
	}

	for ($a=$init; $a <= $level; $a++) { 
/*::::::::::::::::::::::::::::::::::::::::*/
?>
	<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
<b>
		<?php 
		if($a<=3) {
			echo "NIVEAU Licence ".$a;
			$nbrA = $a+1;
		}else{
			echo "NIVEAU Master ".($a-3);
			$nbrA = $a-2;
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
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<!-- <th class="w-18">SIGLE</th> -->
					<th class="px-2">Cours</th>
					<!-- <th class="w-4">Crd</th> -->
					<th class="w-5">Notes</th>
				</tr>	
			</thead>
			<tbody class="<?=$bg_four_color?>">
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
				<tr class="
<?php 
if ($crs['grade'] < 10) {
	echo "bg-red-700";
}else{
	echo "bg-green-700";
}
					 ?>

					">
					<!-- <td><?=$crs['Sigle']?></td> -->
					<td><?=$crs['title_cours']?></td>
					<!-- <td><?=$crs['credit']?></td> -->
					<td><?=$crs['grade']?></td>
					
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

/* --- CALCULE DES NOTES GENERAL --- */

if (($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) {
	$gradeGen = $crs['grade'];
	$nbrGen++;
}else{
	$gradeGen = 0;
}
	$tTGen += $tGen + $gradeGen;


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
			<tfoot class="<?=$bg_one_color?> text-white">
				<tr>
					<!-- <th></th> -->
					<th class="px-2"><?=$nbr?> cours</th>
					<!-- <th><?php if(!empty($tcredit)) { echo $tcredit;}?></th> -->
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnote,2);}?></th>
					
					
				</tr>
			</tfoot>

		</table>
<?php
		}
	echo "</div>";
	}
?>
</div>

