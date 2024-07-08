<?php 
	/*::::::::::::::::::::::: SESSION GENERATE ::::::::::::::::::::::*/

		$aSs = date('Y');
		$constaSs = $aSs." - ".($aSs+1);
		$selected = 0;

		$findSession = $dtb->query('SELECT * FROM t_2023_session WHERE session_year = "'.$constaSs.'"');
		$showSession = $findSession->fetch();
		
		if(empty($showSession)){

			for ($x=1; $x <= 4; $x++) { 

				if ($x == 1) {
					$session_code = "PREM".$aSs.($aSs+1);
					$session_name = "Premier semestre";
					$session_semester = 1;
				}elseif ($x == 2) {
					$session_code = "ETE".$aSs.($aSs+1);
					$session_name = "Semestre d'été";
					$session_semester = 3;
				}elseif ($x == 3) {
					$session_code = "DEUX".$aSs.($aSs+1);
					$session_name = "Deuxième semestre";
					$session_semester = 2;
				}elseif ($x == 4) {
					$session_code = "HIVER".$aSs.($aSs+1);
					$session_name = "Semestre d'hiver";
					$session_semester = 4;
				}
				
				$session_year = $constaSs;
				$date_entry = date('Y-m-d');

				$insertSession = $dtb->prepare('INSERT INTO t_2023_session (
					session_code,
					session_name,
					session_year,
					session_semester,
					selected,
					date_entry
				) VALUES (
					:session_code,
					:session_name,
					:session_year,
					:session_semester,
					:selected,
					:date_entry
				)');$insertSession->execute(array(
					'session_code' => $session_code,
					'session_name' => $session_name,
					'session_year' => $session_year,
					'session_semester' => $session_semester,
					'selected' => $selected,
					'date_entry' => $date_entry
				));	
			}
		}
 ?>
<div>	
<?php
	
	if ($etude_envisage == "Théologie") {
		$eE = 'THEO';
	}elseif ($etude_envisage == "Gestion") {
		$eE = 'GEST';
	}elseif ($etude_envisage == "Informatique") {
		$eE = 'INFO';
	}elseif ($etude_envisage == "Sciences Infirmières") {
		$eE = 'NURS';
	}elseif ($etude_envisage == "Education") {
		$eE = 'EDUC';
	}elseif ($etude_envisage == "Communication") {
		$eE = 'COMM';
	}elseif ($etude_envisage == "Etudes anglophones") {
		$eE = 'LANG';
	}elseif ($etude_envisage == "Droit") {
		$eE = 'DROI';
	}
 	
 	if ($level>1) {
 		$init = $level-1;
 	}elseif($level==1){
 		$init = $level;
 	}
 	

	for ($a=$init; $a <= $level; $a++) {
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

 <!-- DEBUT DU FORMULAIRE -->
 <form action="../app/.student/checkCours.php?id=<?=$id?>&student_id=<?=$student_id?>&page=newCours&user_id=<?=$rg_id?>" method="post">
		<table class="simpleTbl mb-1">
			<thead>
				<tr class="text-center bg-gradient-to-r from-green-600">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<th class="w-5"></th>
					<th class="w-20">SIGLE</th>
					<th class="w-">TITRE DU COURS</th>
					<th class="w-20">CREDITS</th>
					<th class="w-20">Categorie</th>
				</tr>	
			</thead>
			<tbody class="bg-slate-500">
	<?php
	$parcour = $dtb->query('SELECT * FROM filiere_parcours WHERE description = "'.$etude_option.'"');
	$afparc = $parcour->fetch();
	if (!empty($afparc)) {
		$parcours = $afparc['shortcode'];
	}else{
		$parcours = '';
	}
	
	$tout = 'all';

	$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc ='".$eE."' AND yearlevel='".$a."' AND semester='".$s."' AND (parcours = '".$parcours."' OR parcours = '".$tout."') ORDER BY title");
	
	$nbr = 0;
	$nbrMaj = 0;
	$credit = 0;
	$note = 0;
	$notecredit = 0;

	$tMaj = 0;
	$tTMaj = 0;
	$tcredit = 0;
	$tnote = 0;
	$tnotecredit = 0;
	
	if($cours->rowCount() > 0) {
		while ($crs = $cours->fetch()) {
			$note_id = $crs['id'];
			$annee_scolaire = $crs['yearlevel'];
			$semester = $crs['semester'];
	 ?>
				<tr id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:bg-slate-400 hover:text-black">
					<td class="p-0" style="height: 15px;"><input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;"></td>
					<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><?=$crs['Sigle']?></td>
					<td><?=$crs['title']?></td>
					<td><?=$crs['nb_crd']?></td>
					<td><?php 
if ($crs['category'] == 0){
	echo "Général";
}elseif ($crs['category'] == 1) {
	echo "Majeur";
}elseif ($crs['category'] == 2) {
	echo "Selective";
}elseif ($crs['category'] == 3) {
	echo "Additionnel";
}else{
	echo "-";
}
						 ?></td>
					
				</tr>
				<script type="text/javascript">
					$(document).ready(function(){
						$('#cours<?=$a.$s.$nbr;?>').click(function(){
							
							if($('#chk<?=$a.$s.$nbr;?>').prop("checked") == false) {
								$(this).attr("class","bg-blue-500");
								$('#chk<?=$a.$s.$nbr;?>').prop("checked", true);	
							}else{
								$(this).attr("class","hover:transition-all duration-75 hover:bg-slate-400 hover:text-black");
								$('#chk<?=$a.$s.$nbr;?>').prop("checked", false);	
							}

						});

						$('#chk<?=$a.$s.$nbr;?>').click(function(){
							
							if($(this).prop("checked") == false) {
								$('#cours<?=$a.$s.$nbr;?>').attr("class","bg-blue-500");
								$(this).prop("checked", true);	
							}else{
								$('#cours<?=$a.$s.$nbr;?>').attr("class","hover:transition-all duration-75 hover:bg-slate-400 hover:text-black");
								$(this).prop("checked", false);	
							}

						});
					});
				</script>

	<?php

/* --- CALCULE DES NOTES MAJEURS --- */
 
$tcredit+= $credit + $crs['nb_crd'];
		$nbr++;
		}
	}
	 ?>			
			</tbody>
			<tfoot class="<?=$bg_one_color?> text-white">
				<tr>
					<th colspan="2"></th>
					<th><?=$nbr?> cours</th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo $tcredit;}?></th>
					<th></th>
				</tr>
				<tr>
					<td colspan="5" class="bg-slate-500">
						<a href="#" id="selectAll<?=$a.$s;?>" class="px-2 py-0 m-1"><i class="bi-arrow-90deg-up"></i> Cocher tout</a>
						<a href="#" id="deselectAll<?=$a.$s;?>" class="px-2 py-0 m-1 hidden"><i class="bi-arrow-90deg-up"></i> Décocher tout</a>
						
						<b>Session :</b>
						<select name="semesterSession" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarSs<?=$a.$s;?>">
							<option></option>
							<option>Premier semestre</option>
							<option>Semestre d'été</option>
							<option>Deuxième semestre</option>
							<option>Semestre d'hiver</option>
						</select>

						<b>Année du cours :</b>
						<select name="annee_scolaire" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarA<?=$a.$s;?>">
							<option></option>
							<?php
								$mois = date('m');
								if (intval($mois) < 8){
									$z = date('Y');
								}else{
									
									$z = date('Y') + 1;
								}
								$yn = 1;
				        			for ($i=0; $i < 6; $i++) { 
				        			?>
				        				<option><?=$z." - ".($z+1)?></option>
				        			<?php
				        			$z = $z-$yn;
				        		}
			        		 ?>
						</select>
						
						

						<button id="submit<?=$a.$s;?>" type="submit" class="px-2 py-0 m-1 bg-slate-700 text-slate-400 text-center" style="pointer-events: none;">Ajouter au transcript</button>
					</td>
				</tr>
												<script type="text/javascript">
													$(document).ready(function(){
														
														var nbr = <?=$nbr;?>;
														
														$('#selectAll<?=$a.$s;?>').click(function() {
															
															$(this).attr('class','px-2 py-0 m-1 hidden');
															$('#deselectAll<?=$a.$s;?>').attr('class','px-2 py-0 m-1');

															for (var i = 0; i < nbr ; i++) {
																$('#chk<?=$a.$s;?>'+i).prop("checked", true);
																$('#cours<?=$a.$s;?>'+i).attr("class","bg-blue-500");
															}

														});

														$('#deselectAll<?=$a.$s;?>').click(function() {
															
															$(this).attr('class','px-2 py-0 m-1 hidden');
															$('#selectAll<?=$a.$s;?>').attr('class','px-2 py-0 m-1');

															for (var i = 0; i < nbr ; i++) {
																$('#chk<?=$a.$s;?>'+i).prop("checked", false);
																$('#cours<?=$a.$s;?>'+i).attr("class","hover:transition-all duration-75 hover:bg-slate-400 hover:text-black");
															}

														});

														$('#scolarA<?=$a.$s;?>').on('change', function(){
															var scolarA<?=$a.$s;?> = $(this).val();
															var scolarSs<?=$a.$s;?> = $('#scolarSs<?=$a.$s;?>').val();
															
															if (scolarA<?=$a.$s;?> != '' && scolarSs<?=$a.$s;?> !='') {
																$('#submit<?=$a.$s;?>').attr('class','px-2 text-center py-0 m-1 bg-black text-white');
																$('#submit<?=$a.$s;?>').css({'pointer-events':'auto'});
															}else{
																$('#submit<?=$a.$s;?>').attr('class','px-2 text-center py-0 m-1 bg-slate-700 text-slate-400');
																$('#submit<?=$a.$s;?>').css({'pointer-events':'none'});
															}

														});

														$('#scolarSs<?=$a.$s;?>').on('change', function(){
															var scolarSs<?=$a.$s;?> = $(this).val();
															var scolarA<?=$a.$s;?> = $('#scolarA<?=$a.$s;?>').val();
															
															if (scolarA<?=$a.$s;?> != '' && scolarSs<?=$a.$s;?> !='') {
																$('#submit<?=$a.$s;?>').attr('class','px-2 text-center py-0 m-1 bg-black text-white');
																$('#submit<?=$a.$s;?>').css({'pointer-events':'auto'});
															}else{
																$('#submit<?=$a.$s;?>').attr('class','px-2 text-center py-0 m-1 bg-slate-700 text-slate-400');
																$('#submit<?=$a.$s;?>').css({'pointer-events':'none'});
															}

														});

													});
												</script>

		</table>
</form>

<!-- FIN DU FORMULAIRE -->
<?php
		}
	echo "</div>";
	}
?>
</div>