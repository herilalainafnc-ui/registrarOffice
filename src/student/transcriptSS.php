<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">
<?php
	$anual = substr($annee_scolaire, 0, 4);

	$workNote = 0;
	$remarkAcad = 0;
	$chapel = 0;
	$gen = 0;
	$maj = 0;
	
	/**/ 
	$finale = 0;

	$cumulWorkNote = 0;
	$cumulremarkAcad = 0;
	$cumulChapel = 0;
	$cumulGen = 0;
	$cumulMaj = 0;
	
	/**/
	$cumulFinale = 0;

	$beginAnual = $anual - $level;
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
		<?php
		$combinAnual = $beginAnual." - ".$beginAnual+1;

			$searchSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_year="'.$combinAnual.'"');
			while($showSs = $searchSession->fetch()){
				$session_id = $showSs['session_id'];

				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND session_id='".$session_id."' ORDER BY id");

				if ($cours->rowCount() > 0) {
					?>
					<b></b>
					<table class="simpleTbl mb-1">
						<thead>
							<tr class="text-center bg-gradient-to-r from-cyan-500">
								<th colspan="10" id="semestre<?=$a.$s;?>"><?=$showSs['session_name']?> - Session N*<?=$showSs['session_semester']?> | Année <?=$combinAnual?></th>
							</tr>
						</thead>
						<thead class="<?=$bg_one_color?> text-white">
							<tr>
								<th class="w-20">Sigle</th>
								<th class="w-">Titre du cours</th>
								<th class="w-20">Crédits</th>
								<th class="w-20">Catégorie</th>
								<th class="w-20">Notes/20</th>
								<th class="w-20">Crd * Not</th>
								<th class="w-4">État</th>
								<th class="w-4">ID</th>
								<th class="w-4"></th>
							</tr>
						</thead>	
					<?php
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
$tnote = 0;
$tnotecredit = 0;				
					while($crs=$cours->fetch()){
						if (!empty($crs)) {
							?>
							<tbody class="<?=$bg_four_color?>">
								<form method="post" action="../app/.student/updatenote.php?id=<?=$id;?>&nbr=<?=$s.$nbr;?>&note_id=<?=$note_id;?>&as=<?=$a.$s?>&user_id=<?=$rg_id?>">			
				<tr id="note<?=$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black">
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
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><input class="insimple text-sm bg-transparent px-2" type="text" name="nb_crd<?=$s.$nbr;?>" value="<?=$crs['grade']?>"></td>
					<td><?=$notecredi = $crs['credit'] * $crs['grade']?></td>
					
					<td class="<?php 
if ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
	echo "bg-green-500";

}elseif ($crs['grade'] < 10 and $crs['grade'] > 0) {
	echo "bg-red-500";
}elseif ($crs['grade'] == 0){
	echo "bg-none";
}

					 ?> text-center" title="<?php 
if ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
	echo "Succès";
}elseif ($crs['grade'] < 10 and $crs['grade'] > 0){
	echo "Echec";
}elseif ($crs['grade'] == 0){
	echo "";
}

							 ?>"><?php 
if ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
	echo "S";
}elseif($crs['grade'] < 10 and $crs['grade'] > 0){
	echo "E";
}elseif ($crs['grade'] == 0){
	echo "";
}

							 ?></td>
					<td><?=$session_id?></td>
					<td><div class="relative">
						<a href="#" id="coursPush<?=$a.$s.$nbr?>" data-bs-toggle="dropdown" aria-expanded="false" title="Historique de solde"><span class="bi-three-dots-vertical"></span></a>

							<ul class="dropdown-menu absolute border <?=$bg_six_color?> text-black p-0 rounded-0 text-xs">

								<li><a href="../app/.student/del-cours.momentanee.php?student_id=<?=$student_id?>&id=<?=$id?>&as=<?=$a.$s?>&idSupprCours=<?=$note_id?>&user_id=<?=$rg_id?>">		<p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Supprimer</p>
								</a></li>

							</ul>

							
						</div>
					</td>
					

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
</form>
<?php

$credit = 0;
$notes = 0;
$tcredit+= $credit + $crs['credit'];
$tnote+= $note + $crs['grade'];
$tnotecredit+= $notecredit + $notecredi;


/* --- CALCULE DES NOTES MAJEURS --- */
 
if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
	$gradeMaj = $crs['grade'];
	$nbrMaj++;
}else{
	$gradeMaj = 0;
}

	$tTMaj += $tMaj + $gradeMaj;

/* --- CALCULE DES NOTES MAJEURS --- */
 
if (($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) {
	$gradeMaj = $crs['grade'];
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

	 ?>		

							</tbody>
							<?php
						}
						$nbr++;
					}
				?>
<tfoot class="<?=$bg_one_color?> text-white">
				<tr>
					<th colspan="2"><?=$nbr?> cours</th>
					<th><?php if(!empty($tcredit)) { echo $tcredit;}?></th>
					<th></th>
					<th class="px-2"><?php if(($nbr-1)<1){echo 0;}else{echo round($tnote,2);}?></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo round($tnotecredit,2);}?></th>
					<th colspan="2"></th>
				</tr>

<form method="post" action="../app/.student/updatePromotionNote.php?id=<?=$id;?>&session_id=<?=$session_id?>&student_id=<?=$student_id;?>&nbr=<?=$s.$nbr;?>&a=<?=$a?>&s=<?=$s?>&annee_scolaire=<?=$annee_scolaire?>&user_id=<?=$rg_id?>" enctype="multipart/form-data" class="form-no-refrech">
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

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Note de Work Education</td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><input class="insimple text-sm bg-transparent px-2" type="text" name="grade_work_educ" value="<?=$grade_work_educ?>"></td>
				</tr>

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Remarque académique</td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><input class="insimple text-sm bg-transparent px-2" type="text" name="grade_remark_acad" value="<?=$grade_remark_acad?>"></td>
				</tr>

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Note de participation à l'exercice de chapelle et à la semaine de prière</td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><input class="insimple text-sm bg-transparent px-2" type="text" name="grade_chapel_part" value="<?=$grade_chapel_part?>"></td>
				</tr>

				<button type="submit" class="hidden"></button>
</form>		
<?php 
	}
 ?>
				
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2"><?php if($nbrGen != 0){echo $moyenGenSem = round(($tTGen/$nbrGen),2);}else{echo 0;$moyenGenSem =0;}?></th>
				</tr>
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeur</th>
					<th class="px-2"><?php if($nbrMaj != 0){echo $moyenMajSem = round(($tTMaj/$nbrMaj),2);}else{echo 0;}?></th>
				</tr>
				<!--  -->
				<tr>
					<th colspan="4" class="text-right">Moyenne</th>
					<th class="px-2 bg-cyan-700"><?php if($nbrFinale != 0){echo $moyenFinale = round(($tTFinale/$nbrFinale),2);}else{echo 0;$moyenFinale =0;}?></th>
				</tr>
			</tfoot>
							
					</table>
				<?php
				if (!empty($grade_work_educ) or !empty($grade_remark_acad) or !empty($grade_chapel_part)) {
			$cumulWorkNote += $workNote + $grade_work_educ;
			$cumulremarkAcad += $remarkAcad + $grade_remark_acad;
			$cumulChapel += $chapel + $grade_chapel_part;
		}
		
		$cumulGen += $gen + $moyenGenSem;
		$cumulMaj += $maj + $moyenMajSem;
		
		/**/
		$cumulFinale += $finale + $moyenFinale;
				}
			}

		$beginAnual++;
		?>
</div>
		<?php
	}

	?>

<div class='p-1 <?=$bg_three_color?> hover:<?=$bg_four_color?> mb-4 rounded-md border-2 border-slate-600 hover:border-cyan-500 transition-all text-xs text-white'>
	<b>MOYENNE CUMULATIVE</b>
	<table class="simpleTbl mb-1 w-full">
		<tbody class=" <?=$bg_two_color?>">
			<tr>
				<td class="p-1 w-8/12 text-right">Note de Work Education cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?=round(($cumulWorkNote*20)/((($i-1)*2)*20),3);?></td>
			</tr>
			<tr>
				<td class="p-1 w-8/12 text-right">Nemarque académique cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?=round(($cumulremarkAcad*20)/((($i-1)*2)*20),3);?></td>
			</tr>
			<tr>
				<td class="p-1 w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?=round(($cumulChapel*20)/((($i-1)*2)*20),3);?></td>
			</tr>
		</tbody>
	</table>
	<table class="simpleTbl mb-1 w-full">
		<thead class="bg-slate-900">
			<tr>
				<th class="p-1 w-8/12 text-right">Moyenne Générale Cumulative</th>
				<th class="py-1 px-2 w-2/12"><?=round(($cumulGen*20)/((($i-1)*2)*20),3);?></th>
			</tr>
			<tr>
				<th class="p-1 w-8/12 text-right">Moyenne Majeur Cumulative</th>
				<th class="py-1 px-2 w-2/12"><?=round(($cumulMaj*20)/((($i-1)*2)*20),3);?></th>
			</tr>
			
			<!--  -->
			<tr>
				<th class="p-1 w-8/12 text-right bg-cyan-700">Moyenne Cumulative</th>
				<th class="py-1 px-2 w-2/12 bg-cyan-700  text-white"><?=round(($cumulFinale*20)/((($i-1)*2)*20),3);?></th>
			</tr>
		</thead>
	</table>
</div>
</div>