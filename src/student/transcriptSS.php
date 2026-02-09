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
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><input class="insimple text-sm bg-transparent px-2" type="text" name="nb_crd<?=$sessionCount.$nbr;?>" value="<?=$crs['grade']?>"></td>
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
					<td><div class="relative">
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
					<th class="px-2 bg-cyan-700"><?php if($nbrFinale != 0){echo $moyenFinale = round(($tnotecredit/$tcredit),2);}else{echo 0;$moyenFinale =0;}?></th>
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
	
	// Gestion de la mise à jour des notes individuelles
	$('.form-update-note').on('submit', function(e) {
		e.preventDefault();
		var form = $(this);
		var url = form.attr('action');
		var data = form.serialize();
		
		$.post(url, data, function(response) {
			if (typeof Toast !== 'undefined') {
				Toast.success('Note mise à jour avec succès!');
			} else {
				alert('Note mise à jour!');
			}
		}).fail(function() {
			if (typeof Toast !== 'undefined') {
				Toast.error('Erreur lors de la mise à jour de la note');
			} else {
				alert('Erreur!');
			}
		});
	});
	
	// Gestion de la mise à jour des notes de promotion (Work Education, Chapel, etc.)
	$('.form-no-refrech').on('submit', function(e) {
		e.preventDefault();
		var form = $(this);
		var url = form.attr('action');
		var data = form.serialize();
		
		$.post(url, data, function(response) {
			if (typeof Toast !== 'undefined') {
				Toast.success('Notes de promotion mises à jour!');
			} else {
				alert('Notes mises à jour!');
			}
		}).fail(function() {
			if (typeof Toast !== 'undefined') {
				Toast.error('Erreur lors de la mise à jour');
			} else {
				alert('Erreur!');
			}
		});
	});
	
	// Gestion de la suppression de cours avec confirmation
	$('.btn-delete-cours').on('click', function(e) {
		e.preventDefault();
		var btn = $(this);
		var url = btn.data('url');
		var coursName = btn.data('cours');
		
		// Confirmation avant suppression
		if (typeof Toast !== 'undefined' && typeof Toast.confirm === 'function') {
			Toast.confirm('Voulez-vous vraiment supprimer "' + coursName + '" du transcript?', {
				title: 'Supprimer ce cours?',
				confirmText: 'Supprimer',
				cancelText: 'Annuler'
			}).then(function(confirmed) {
				if (confirmed) {
					// Proceed with deletion
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
								setTimeout(function() {
									window.location.reload();
								}, 800);
							} else {
								Toast.error(response.message || 'Erreur lors de la suppression');
							}
						},
						error: function(xhr, status, error) {
							console.error('Erreur AJAX:', status, error, xhr.responseText);
							var msg = 'Erreur lors de la suppression';
							try {
								var resp = JSON.parse(xhr.responseText);
								if (resp && resp.message) msg = resp.message;
							} catch(e) {
								if (xhr.responseText) msg += ': ' + xhr.responseText.substring(0, 100);
							}
							Toast.error(msg);
						}
					});
				}
			});
		} else {
			// Fallback to standard confirm
			if (confirm('Voulez-vous vraiment supprimer "' + coursName + '" du transcript?')) {
				$.ajax({
					url: url,
					type: 'GET',
					dataType: 'json',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
					},
					success: function(response) {
						if (response && response.success) {
							alert('Cours supprimé!');
							window.location.reload();
						} else {
							alert(response.message || 'Erreur lors de la suppression');
						}
					},
					error: function(xhr, status, error) {
						console.error('Erreur AJAX:', status, error, xhr.responseText);
						alert('Erreur lors de la suppression: ' + error);
					}
				});
			}
		}
	});
	
	// Auto-save on input change (debounced)
	var saveTimeout;
	$('.form-update-note input, .form-no-refrech input').on('change', function() {
		var form = $(this).closest('form');
		
		clearTimeout(saveTimeout);
		saveTimeout = setTimeout(function() {
			form.submit();
		}, 500);
	});
	
});
</script>