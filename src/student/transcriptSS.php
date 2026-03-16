<div class=" mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);" id="transcriptSSContainer">
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

	// Récupérer toutes les sessions distinctes où l'étudiant a des notes
	$searchAllSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
		FROM t_2023_notes n 
		INNER JOIN t_2023_session s ON n.session_id = s.session_id 
		WHERE n.student_id = '".$student_id."' AND n.ajout = '".$yes."' 
		ORDER BY s.session_year ASC, s.session_semester ASC");

	$sessionCount = 0;
	
	while($showSs = $searchAllSessions->fetch()){
		$sessionCount++;
		$session_id = $showSs['session_id'];
		$combinAnual = $showSs['session_year'];
		
		// Récupérer le yearlevel depuis les notes de cette session
		$getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id='".$student_id."' AND session_id='".$session_id."' AND ajout='".$yes."' LIMIT 1");
		$ylData = $getYearlevel->fetch();
		$yearlevel = $ylData ? $ylData['yearlevel'] : 1;
		
		// Déterminer le niveau (Licence ou Master)
		if ($yearlevel <= 3) {
			$niveau_label = "Licence " . $yearlevel;
		} else {
			$niveau_label = "Master " . ($yearlevel - 3);
		}
		?>
<div class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
		<?php
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND session_id='".$session_id."' ORDER BY id");

				if ($cours->rowCount() > 0) {
					?>
					<b></b>
					<table class="simpleTbl mb-1">
						<thead>
							<tr class="text-center bg-gradient-to-r from-cyan-500">
								<th colspan="10" id="semestre<?=$sessionCount;?>"><b><?=$niveau_label?></b> | <?=$showSs['session_name']?> - Session N°<?=$showSs['session_semester']?> | Année <?=$combinAnual?></th>
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
$tcreditGPA = 0;
$tnote = 0;
$tnotecredit = 0;
					$note_id = 0;				
					while($crs=$cours->fetch()){
						$note_id = $crs['id'];
						if (!empty($crs)) {
							?>
							<tbody class="<?=$bg_four_color?>">
								<form method="post" action="<?=$app_base?>/app/.student/updatenote?id=<?=$id;?>&nbr=<?=$sessionCount.$nbr;?>&note_id=<?=$note_id;?>&as=<?=$sessionCount?>&user_id=<?=$rg_id?>" class="form-update-note">			
				<tr id="note<?=$sessionCount.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black">
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
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><?php if ($crs['cours_category'] == 5): ?><span class="px-2"><?php echo ($crs['grade'] == -2 || $crs['grade'] >= 10) ? '<span style="color:#15803d;font-weight:bold;">V</span>' : '<span style="color:#b91c1c;font-weight:bold;">E</span>'; ?></span><?php elseif ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 note-input" type="text" name="nb_crd<?=$sessionCount.$nbr;?>" value="<?=$crs['grade']?>" data-action="<?=$app_base?>/app/.student/updatenote?id=<?=$id;?>&nbr=<?=$sessionCount.$nbr;?>&note_id=<?=$note_id;?>&as=<?=$sessionCount?>&user_id=<?=$rg_id?>" data-nbr="<?=$sessionCount.$nbr;?>"><?php elseif ($rg_level <= 6): ?><span class="px-2"><?= $crs['grade'] == -2 ? 'OK' : $crs['grade'] ?></span><?php else: ?><em class="px-2">masqué</em><?php endif; ?></td>
					<td><?php if($crs['cours_category'] == 5){ $notecredi = 0; echo '--'; }else{ echo $notecredi = $crs['credit'] * $crs['grade']; } ?></td>
					
					<td class="<?php 
if ($crs['cours_category'] == 5) {
	if ($crs['grade'] == -2 OR $crs['grade'] >= 10) { echo "bg-green-500"; } elseif ($crs['grade'] > 0) { echo "bg-red-500"; } else { echo "bg-none"; }
}elseif ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
	echo "bg-green-500";
}elseif ($crs['grade'] < 10 and $crs['grade'] > 0) {
	echo "bg-red-500";
}elseif ($crs['grade'] == 0){
	echo "bg-none";
}

					 ?> text-center" title="<?php 
if ($crs['cours_category'] == 5) {
	echo ($crs['grade'] == -2 || $crs['grade'] >= 10) ? 'Validé' : 'Echec';
}elseif ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
	echo "Succès";
}elseif ($crs['grade'] < 10 and $crs['grade'] > 0){
	echo "Echec";
}elseif ($crs['grade'] == 0){
	echo "";
}

							 ?>"><?php 
if ($crs['cours_category'] == 5) {
	echo ($crs['grade'] == -2 || $crs['grade'] >= 10) ? 'V' : 'E';
}elseif ($crs['grade'] == -2 OR $crs['grade'] >= 10) {
	echo "S";
}elseif($crs['grade'] < 10 and $crs['grade'] > 0){
	echo "E";
}elseif ($crs['grade'] == 0){
	echo "";
}

							 ?></td>
					<td><?php if ($rg_level <= 3): ?><div class="relative">
						<a href="#" id="coursPush<?=$sessionCount.$nbr?>" data-bs-toggle="dropdown" aria-expanded="false" title="Historique de solde"><span class="bi-three-dots-vertical"></span></a>

							<ul class="dropdown-menu absolute border <?=$bg_six_color?> text-black p-0 rounded-0 text-xs">

								<li><p class="px-2 py-1">Session ID : <?=$session_id?></p></li>
								<hr>
								<li><a href="#" class="btn-delete-cours" 
									data-url="<?=$app_base?>/app/.student/del-cours.momentanee.php?student_id=<?=$student_id?>&id=<?=$id?>&as=<?=$sessionCount?>&idSupprCours=<?=$note_id?>&user_id=<?=$rg_id?>"
									data-cours="<?=$crs['title_cours']?>">
									<p class="px-2 py-1 hover:bg-red-600 hover:text-white"><i class="bi-trash"></i> Supprimer</p>
								</a></li>


							</ul>

							
						</div>
					<?php endif; ?></td>
					

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
$isPassFail = ($crs['cours_category'] == 5);
$tcredit+= $credit + $crs['credit'];
if (!$isPassFail) {
	$tcreditGPA += $crs['credit'];
	$tnote+= $note + $crs['grade'];
	$tnotecredit+= $notecredit + $notecredi;
}


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

<form method="post" action="<?=$app_base?>/app/.student/updatePromotionNote?id=<?=$id;?>&session_id=<?=$session_id?>&student_id=<?=$student_id;?>&nbr=<?=$sessionCount.$nbr;?>&sessionCount=<?=$sessionCount?>&annee_scolaire=<?=$annee_scolaire?>&user_id=<?=$rg_id?>" enctype="multipart/form-data" class="form-no-refrech">
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
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><?php if ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 promo-input" type="text" name="grade_work_educ" value="<?=$grade_work_educ?>" data-action="<?=$app_base?>/app/.student/updatePromotionNote?id=<?=$id;?>&session_id=<?=$session_id?>&student_id=<?=$student_id;?>&nbr=<?=$sessionCount.$nbr;?>&sessionCount=<?=$sessionCount?>&annee_scolaire=<?=$annee_scolaire?>&user_id=<?=$rg_id?>" data-group="promo-<?=$session_id?>"><?php else: ?><span class="px-2"><?= $grade_work_educ ?></span><?php endif; ?></td>
				</tr>

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Remarque académique</td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><?php if ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 promo-input" type="text" name="grade_remark_acad" value="<?=$grade_remark_acad?>" data-action="<?=$app_base?>/app/.student/updatePromotionNote?id=<?=$id;?>&session_id=<?=$session_id?>&student_id=<?=$student_id;?>&nbr=<?=$sessionCount.$nbr;?>&sessionCount=<?=$sessionCount?>&annee_scolaire=<?=$annee_scolaire?>&user_id=<?=$rg_id?>" data-group="promo-<?=$session_id?>"><?php else: ?><span class="px-2"><?= $grade_remark_acad ?></span><?php endif; ?></td>
				</tr>

				<tr class="<?=$bg_four_color?> text-right">
					<td colspan="4">Note de participation à l'exercice de chapelle et à la semaine de prière</td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><?php if ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 promo-input" type="text" name="grade_chapel_part" value="<?=$grade_chapel_part?>" data-action="<?=$app_base?>/app/.student/updatePromotionNote?id=<?=$id;?>&session_id=<?=$session_id?>&student_id=<?=$student_id;?>&nbr=<?=$sessionCount.$nbr;?>&sessionCount=<?=$sessionCount?>&annee_scolaire=<?=$annee_scolaire?>&user_id=<?=$rg_id?>" data-group="promo-<?=$session_id?>"><?php else: ?><span class="px-2"><?= $grade_chapel_part ?></span><?php endif; ?></td>
				</tr>
</form>		
<?php 
	}
 ?>
				
				<!-- <tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2"><?php if($nbrGen != 0){echo $moyenGenSem = round(($tTGen/$nbrGen),2);}else{echo 0;$moyenGenSem =0;}?></th>
				</tr> -->
				<tr>
					<th colspan="4" class="text-right">Moyenne Majeure</th>
					<th class="px-2"><?php if($nbrMaj != 0){echo $moyenMajSem = round(($tTMaj/$nbrMaj),2);}else{echo 0;$moyenMajSem=0;}?></th>
				</tr>
				<!--  -->
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2 bg-cyan-700"><?php if($nbrFinale != 0){echo $moyenFinale = round(($tnotecredit/$tcreditGPA),2);}else{echo 0;$moyenFinale =0;}?></th>
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
				<td class="px-2 w-2/12 text-bold"><?php if($sessionCount > 0){echo round($cumulWorkNote/$sessionCount, 2);}else{echo 0;}?></td>
			</tr>
			<!-- <tr>
				<td class="p-1 w-8/12 text-right">Nemarque académique cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?php if($sessionCount > 0){echo round($cumulremarkAcad/$sessionCount, 2);}else{echo 0;}?></td>
			</tr> -->
			<tr>
				<td class="p-1 w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?php if($sessionCount > 0){echo round($cumulChapel/$sessionCount, 2);}else{echo 0;}?></td>
			</tr>
		</tbody>
	</table>
	<table class="simpleTbl mb-1 w-full">
		<thead class="bg-slate-900">
			<tr>
				<th class="p-1 w-8/12 text-right">Moyenne Générale Cumulative</th>
				<th class="py-1 px-2 w-2/12"><?php if($sessionCount > 0){echo round($cumulGen/$sessionCount, 2);}else{echo 0;}?></th>
			</tr>
			<tr>
				<th class="p-1 w-8/12 text-right">Moyenne Majeure Cumulative</th>
				<th class="py-1 px-2 w-2/12"><?php if($sessionCount > 0){echo round($cumulMaj/$sessionCount, 2);}else{echo 0;}?></th>
			</tr>
			
			<!--  -->
			<tr>
				<th class="p-1 w-8/12 text-right bg-cyan-700">Moyenne Cumulative</th>
				<th class="py-1 px-2 w-2/12 bg-cyan-700  text-white"><?php if($sessionCount > 0){echo round($cumulFinale/$sessionCount, 2);}else{echo 0;}?></th>
			</tr>
		</thead>
	</table>
</div>
</div>

<script type="text/javascript">
$(document).ready(function(){
	
	// Fonction pour rafraîchir uniquement la vue des notes (sans recharger toute la page)
	function refreshTranscriptSS() {
		var scrollTop = $('#transcriptSSContainer').scrollTop();
		var currentUrl = window.location.href;
		
		$.ajax({
			url: currentUrl,
			method: 'GET',
			beforeSend: function() {
				$('#transcriptSSContainer').css('opacity', '0.5');
			},
			success: function(data) {
				var tempDiv = document.createElement('div');
				var bodyMatch = data.match(/<body[^>]*>([\s\S]*)<\/body>/i);
				if (bodyMatch) {
					tempDiv.innerHTML = bodyMatch[1];
				} else {
					tempDiv.innerHTML = data;
				}
				var newContainer = tempDiv.querySelector('#transcriptSSContainer');
				if (newContainer) {
					$('#transcriptSSContainer').html(newContainer.innerHTML).css('opacity', '1');
					$('#transcriptSSContainer').scrollTop(scrollTop);
				} else {
					$('#transcriptSSContainer').css('opacity', '1');
					window.location.reload();
				}
			},
			error: function() {
				$('#transcriptSSContainer').css('opacity', '1');
				if (typeof Toast !== 'undefined') {
					Toast.error('Erreur lors du rafraîchissement');
				}
			}
		});
	}

	// Fonction de validation de note (max 20)
	function validateNote(value) {
		if (value === 'ok' || value === 'Ok' || value === 'OK') {
			return { valid: true };
		}
		var numValue = parseFloat(value);
		if (isNaN(numValue)) {
			return { valid: false, message: 'La note doit être un nombre valide.' };
		}
		if (numValue > 20) {
			return { valid: false, message: 'La note ne peut pas dépasser 20. Veuillez saisir une note entre 0 et 20.' };
		}
		if (numValue < 0) {
			return { valid: false, message: 'La note ne peut pas être négative.' };
		}
		return { valid: true };
	}

	// ========== MISE A JOUR DES NOTES INDIVIDUELLES ==========
	// Délégation sur le conteneur - fonctionne même après remplacement AJAX du contenu
	var noteTimeout;
	$('#transcriptSSContainer').on('change', '.note-input', function() {
		var input = $(this);
		var url = input.data('action');
		var nbr = input.data('nbr');
		var noteValue = input.val().trim().replace(',', '.');

		// Arrondir à 2 décimales
		var nVal = parseFloat(noteValue);
		if (!isNaN(nVal) && nVal != -2) {
			noteValue = '' + (Math.round(nVal * 100) / 100);
			input.val(noteValue);
		}
		
		// Validation côté client
		var validation = validateNote(noteValue);
		if (!validation.valid) {
			if (typeof Toast !== 'undefined') {
				Toast.error(validation.message);
			} else {
				alert(validation.message);
			}
			input.focus();
			input.css('border', '2px solid red');
			setTimeout(function() { input.css('border', ''); }, 3000);
			return;
		}
		
		// Construire les données manuellement (pas de dépendance au <form>)
		var data = 'nb_crd' + nbr + '=' + encodeURIComponent(noteValue);
		
		clearTimeout(noteTimeout);
		noteTimeout = setTimeout(function() {
			$.ajax({
				url: url,
				method: 'POST',
				data: data,
				dataType: 'json',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
					input.css('opacity', '0.5');
				},
				success: function(response) {
					input.css('opacity', '1');
					if (response && response.success) {
						if (typeof Toast !== 'undefined') {
							Toast.success(response.message || 'Note mise à jour avec succès!');
						}
						refreshTranscriptSS();
					} else {
						if (typeof Toast !== 'undefined') {
							Toast.error(response.message || 'Erreur lors de la mise à jour de la note');
						} else {
							alert(response.message || 'Erreur!');
						}
					}
				},
				error: function(xhr) {
					input.css('opacity', '1');
					var msg = 'Erreur lors de la mise à jour de la note';
					try {
						var resp = JSON.parse(xhr.responseText);
						if (resp && resp.message) msg = resp.message;
					} catch(e) {}
					if (typeof Toast !== 'undefined') {
						Toast.error(msg);
					} else {
						alert(msg);
					}
				}
			});
		}, 500);
	});
	
	// ========== MISE A JOUR DES NOTES DE PROMOTION ==========
	var promoTimeout;
	$('#transcriptSSContainer').on('change', '.promo-input', function() {
		var input = $(this);
		var url = input.data('action');
		var group = input.data('group');
		
		// Collecter toutes les valeurs du même groupe
		var allInputs = $('#transcriptSSContainer .promo-input[data-group="' + group + '"]');
		var hasError = false;
		var data = {};
		
		allInputs.each(function() {
			var val = $(this).val().trim();
			if (val !== '') {
				var validation = validateNote(val);
				if (!validation.valid) {
					if (typeof Toast !== 'undefined') {
						Toast.error(validation.message);
					} else {
						alert(validation.message);
					}
					$(this).focus();
					$(this).css('border', '2px solid red');
					var el = $(this);
					setTimeout(function() { el.css('border', ''); }, 3000);
					hasError = true;
					return false; // break
				}
			}
			data[$(this).attr('name')] = val;
		});
		
		if (hasError) return;
		
		clearTimeout(promoTimeout);
		promoTimeout = setTimeout(function() {
			$.ajax({
				url: url,
				method: 'POST',
				data: data,
				dataType: 'json',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
					allInputs.css('opacity', '0.5');
				},
				success: function(response) {
					allInputs.css('opacity', '1');
					if (response && response.success) {
						if (typeof Toast !== 'undefined') {
							Toast.success(response.message || 'Notes de promotion mises à jour!');
						}
						refreshTranscriptSS();
					} else {
						if (typeof Toast !== 'undefined') {
							Toast.error(response.message || 'Erreur lors de la mise à jour');
						} else {
							alert(response.message || 'Erreur!');
						}
					}
				},
				error: function(xhr) {
					allInputs.css('opacity', '1');
					var msg = 'Erreur lors de la mise à jour';
					try {
						var resp = JSON.parse(xhr.responseText);
						if (resp && resp.message) msg = resp.message;
					} catch(e) {}
					if (typeof Toast !== 'undefined') {
						Toast.error(msg);
					} else {
						alert(msg);
					}
				}
			});
		}, 500);
	});
	
	// ========== SUPPRESSION DE COURS ==========
	$('#transcriptSSContainer').on('click', '.btn-delete-cours', function(e) {
		e.preventDefault();
		var btn = $(this);
		var url = btn.data('url');
		var coursName = btn.data('cours');
		
		if (typeof Toast !== 'undefined' && typeof Toast.confirm === 'function') {
			Toast.confirm('Voulez-vous vraiment supprimer "' + coursName + '" du transcript?', {
				title: 'Supprimer ce cours?',
				confirmText: 'Supprimer',
				cancelText: 'Annuler'
			}).then(function(confirmed) {
				if (confirmed) {
					$.ajax({
						url: url,
						type: 'GET',
						dataType: 'json',
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
						},
						success: function(response) {
							if (response && response.success) {
								Toast.success(response.message || 'Cours supprimé avec succès!');
								setTimeout(function() { refreshTranscriptSS(); }, 800);
							} else {
								Toast.error(response.message || 'Erreur lors de la suppression');
							}
						},
						error: function(xhr) {
							var msg = 'Erreur lors de la suppression';
							try {
								var resp = JSON.parse(xhr.responseText);
								if (resp && resp.message) msg = resp.message;
							} catch(e) {}
							Toast.error(msg);
						}
					});
				}
			});
		} else {
			if (confirm('Voulez-vous vraiment supprimer "' + coursName + '" du transcript?')) {
				$.ajax({
					url: url, type: 'GET', dataType: 'json',
					beforeSend: function(xhr) { xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); },
					success: function(response) {
						if (response && response.success) { alert('Cours supprimé!'); refreshTranscriptSS(); }
						else { alert(response.message || 'Erreur'); }
					},
					error: function() { alert('Erreur lors de la suppression'); }
				});
			}
		}
	});
	
});
</script>