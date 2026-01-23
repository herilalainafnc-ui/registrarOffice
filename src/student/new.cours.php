
<?php 
	/*::::::::::::::::::::::: VÉRIFICATION SUSPENSION ::::::::::::::::::::::*/
	
	// Vérifier si l'étudiant est suspendu
	$isSuspended = false;
	if (isset($profil['suspended']) && $profil['suspended'] == 1) {
		$dateFin = $profil['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			$isSuspended = true;
		}
	}
	
	if ($isSuspended) {
?>
<div class="mt-2 p-4 text-center">
	<div class="bg-orange-500 text-white p-6 rounded-lg shadow-lg">
		<i class="bi bi-exclamation-triangle-fill text-5xl"></i>
		<h3 class="text-xl font-bold mt-3">ÉTUDIANT SUSPENDU</h3>
		<p class="mt-2">Cet étudiant est actuellement suspendu et ne peut pas prendre de nouveaux cours.</p>
		<?php if (!empty($profil['date_fin_suspension'])) { ?>
			<p class="mt-2 text-sm">Fin de suspension prévue: <b><?= date('d/m/Y', strtotime($profil['date_fin_suspension'])) ?></b></p>
		<?php } ?>
		<?php if (!empty($profil['motif_suspension'])) { ?>
			<p class="mt-2 text-sm bg-orange-600 p-2 rounded">Motif: <?= htmlspecialchars($profil['motif_suspension']) ?></p>
		<?php } ?>
	</div>
</div>
<?php
		return; // Arrêter l'exécution du reste de la page
	}
?>

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
<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);">	
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
 	
/*	if($level<=1) {

 		$init = $level;

 	}elseif($level==2) {

 		if($etude_envisage == "Théologie") {
 			$init = 1;
 			$level = 3;
 		}else{
 			$init = $level-1;
 		}

 	}elseif($level==3) {

 		$init = $level-2;

 	}elseif($level==4) {

 		$init = $level;

 	}elseif($level==5) {

 		$init = $level-1;

 	}*/

 	if ($level <= 3) {
 	
 		$init = 1;
 		$level = 3;
 	
 	}elseif($level > 3) {
 		
 		$init = 4;
 		$level = 5;
 	
 	}

	for ($a=$init; $a <= $level; $a++) {
 ?>
	<div id="year<?=$a?>" class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
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
 <form action="../app/.student/checkCours?id=<?=$id?>&student_id=<?=$student_id?>&page=newCours&user_id=<?=$rg_id?>
&etude_envisage=<?=$profil['etude_envisage']?>
&status=<?=$profil['status']?>
&new_student=<?=$profil['new_student']?>
&graduated=<?=$profil['graduated']?>
&student_adresse=<?=$profil['student_adresse']?>
&etude_option=<?=$profil['etude_option']?>
&annee_etude=<?=$profil['annee_etude']?>
&sponsor_nom=<?=$profil['sponsor_nom']?>
&sponsor_prenom=<?=$profil['sponsor_prenom']?>
&sponsor_tel=<?=$profil['sponsor_tel']?>
&sponsor_adresse=<?=$profil['sponsor_adresse']?>
&situationf=<?=$profil['situationf']?>
&nom_conjoint=<?=$profil['nom_conjoint']?>
&nb_enfant=<?=$profil['nb_enfant']?>
&abonment=<?=$profil['abonment']?>" method="post" class="form-newCours">

		<table class="simpleTbl mb-1 w-full">
			<thead>
				<tr class="text-left bg-gradient-to-r from-green-600">
					<th colspan="10">SEMESTRE <?=$s?></th>
				</tr>
			</thead>
			<thead class="<?=$bg_one_color?> text-white">
				<tr>
					<th class="w-5"></th>
					<th class="w-20">Sigle</th>
					<th class="">Titre du cours</th>
					<th class="sm:w-2/12 lg:w-3/12">Observations</th>
					<th class="w-20">Crédits</th>
					<th class="w-20">Catégorie</th>
				</tr>	
			</thead>
			<tbody class="<?=$bg_four_color?>">
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

	/*$cours = $dtb->query("SELECT * FROM t_2023_cours WHERE dep_desc ='".$eE."' AND yearlevel='".$a."' AND semester='".$s."' ORDER BY title");*/
	
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

	$verifyExisting = $dtb->query('SELECT * FROM t_2023_notes WHERE id_cours = "'.$note_id.'" AND student_id="'.$student_id.'" AND ajout = 1 AND remove = 0');
	$validExisting = $verifyExisting->fetch();
	 ?>
				<tr 
<?php if (empty($validExisting)) { ?>	

				id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black"

<?php }elseif(!empty($validExisting) AND ($validExisting['grade'] >= 12 OR $validExisting['grade'] == 0)) { ?>

				class="bg-slate-700"

<?php }elseif(!empty($validExisting) AND $validExisting['grade'] > 0 AND $validExisting['grade'] < 12){ ?>

				id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black bg-slate-700"

<?php } ?>>
					<td class="p-0 text-center" style="height: 15px;">




<?php if (empty($validExisting)) { ?>	

						<input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;">

<?php }elseif(!empty($validExisting) AND $validExisting['grade'] > 0 AND $validExisting['grade'] < 12){ ?>

						<input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;">	

<?php }elseif(!empty($validExisting) AND ($validExisting['grade'] >= 12 OR $validExisting['grade'] == 0)){ ?>	

						<i class="bi-x-lg text-red-300"></i>	

<?php } ?>		
					

					</td>
					<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><?=$crs['Sigle']?></td>
					<td><?php
							if ($etude_envisage == "Etudes anglophones") {
								echo $crs['title_english'];
							}else{
								echo $crs['title'];
							}
					?>
					</td>
					<td><?php if (!empty($validExisting) AND $validExisting['grade'] >= 10) {
	
	echo "<em class='text-green-400'><b>".$validExisting['grade']."</b> de moyenne</em>";

}elseif (!empty($validExisting) AND $validExisting['grade'] > 0 AND $validExisting['grade'] < 10) {
	
	echo "<em class='text-red-500'><b>".$validExisting['grade']."</b> de moyenne, en état d'echec.</em>";

}elseif (!empty($validExisting) AND $validExisting['grade'] == 0){

	echo "<em class='text-orange-400'>Ajouté le - ".substr($validExisting['date_entry'], 0, 10)."</b></em>";

} ?></td>
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
								$(this).attr("class","hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black");
								$('#chk<?=$a.$s.$nbr;?>').prop("checked", false);	
							}

						});

						$('#chk<?=$a.$s.$nbr;?>').click(function(){
							
							if($(this).prop("checked") == false) {
								$('#cours<?=$a.$s.$nbr;?>').attr("class","bg-blue-500");
								$(this).prop("checked", true);	
							}else{
								$('#cours<?=$a.$s.$nbr;?>').attr("class","hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black");
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
					<th></th>
					<th><?php if(($nbr-1)<1){echo 0;}else{echo $tcredit;}?></th>
					<th></th>
				</tr>
				<tr>
					<td colspan="6" class="<?=$bg_four_color?>">
						<div class="flex">
							<div class="w-40 pt-1">
								<a href="#" id="selectAll<?=$a.$s;?>" class="px-2 py-0 m-1"><i class="bi-arrow-90deg-up"></i> Cocher tout</a>
								<a href="#" id="deselectAll<?=$a.$s;?>" class="px-2 py-0 m-1 hidden"><i class="bi-arrow-90deg-up"></i> Décocher tout</a>
							</div>
							<div>
								<b>Session :</b>
								<select name="semesterSession" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarSs<?=$a.$s;?>">
									<option <?php if (date('m')>=7) {echo "selected";} ?>>Premier semestre</option>
									<option>Semestre d'été</option>
									<option <?php if (date('m')<7) {echo "selected";} ?>>Deuxième semestre</option>
									<option>Semestre d'hiver</option>
								</select>

								<b>Année du cours :</b>
								<select name="annee_scolaire" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarA<?=$a.$s;?>">
									<?php
										$mois = date('m');
										if (intval($mois) < 7){
											$z = date('Y');
										}else{
											
											$z = date('Y') + 1;
										}
										$yn = 1;
						        			for ($i=1; $i < 6; $i++) { 
						        			?>
						        				<option><?=($z-1)." - ".$z?></option>
						        			<?php
						        			$z = $z-$yn;
						        		}
					        		 ?>
								</select>
							</div>
						
							
						
						<button id="submit<?=$a.$s;?>" type="submit" class="submiting px-2 text-center py-0 m-1 text-slate-400 bg-black">Ajouter au transcript</button>
						</div>
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
																$('#cours<?=$a.$s;?>'+i).attr("class","hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black");
															}

														});

														$('#scolarA<?=$a.$s;?>').on('change', function(){
															var scolarA<?=$a.$s;?> = $(this).val();
															var scolarSs<?=$a.$s;?> = $('#scolarSs<?=$a.$s;?>').val();
															
															if (scolarA<?=$a.$s;?> != '' && scolarSs<?=$a.$s;?> !='') {
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-white bg-black');
															}/*else{
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-slate-400 bg-slate-700 toolInactive');
															}*/

														});

														$('#scolarSs<?=$a.$s;?>').on('change', function(){
															var scolarSs<?=$a.$s;?> = $(this).val();
															var scolarA<?=$a.$s;?> = $('#scolarA<?=$a.$s;?>').val();
															
															if (scolarA<?=$a.$s;?> != '' && scolarSs<?=$a.$s;?> !='') {
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-white bg-black');
															}/*else{
																$('#submit<?=$a.$s;?>').attr('class','submiting px-2 text-center py-0 m-1 text-slate-400 bg-slate-700 toolInactive');
															}*/

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
<script type="text/javascript">
	$(document).ready(function(){

	    window.onload = function() {
	        var a = '<?=$a-1?>';
	        window.location.hash = '#year'+a;
	    };

		$(".form-newCours").on('submit',function (e) {

			 e.preventDefault();

			var url = '../app/.student/checkCours?id=<?=$id?>&student_id=<?=$student_id?>&page=newCours&user_id=<?=$rg_id?>
&etude_envisage=<?=$profil['etude_envisage']?>
&status=<?=$profil['status']?>
&new_student=<?=$profil['new_student']?>
&graduated=<?=$profil['graduated']?>
&student_adresse=<?=$profil['student_adresse']?>
&etude_option=<?=$profil['etude_option']?>
&annee_etude=<?=$profil['annee_etude']?>
&sponsor_nom=<?=$profil['sponsor_nom']?>
&sponsor_prenom=<?=$profil['sponsor_prenom']?>
&sponsor_tel=<?=$profil['sponsor_tel']?>
&sponsor_adresse=<?=$profil['sponsor_adresse']?>
&situationf=<?=$profil['situationf']?>
&nom_conjoint=<?=$profil['nom_conjoint']?>
&nb_enfant=<?=$profil['nb_enfant']?>
&abonment=<?=$profil['abonment']?>';
			
			var data = $(this).serialize();

			$.post(url,data,function(response){
				
				alert("Cours sauvegardé !");
				/*$(".submiting").attr('class','submiting px-2 text-center py-0 m-1 text-slate-400 bg-slate-700 toolInactive');*/
				
			});

		});
	});

</script>