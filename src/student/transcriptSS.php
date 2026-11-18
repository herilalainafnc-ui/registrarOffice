<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion(); refuserEtudiant(); // notes : réservées au personnel ?>
<style>
/* ===== Tableau de notes — lisibilité (en-tête session, zébrage, moyennes) ===== */
.bulletin-table { border-collapse: collapse; width: 100%; }

/* En-tête de session (bandeau) */
.bulletin-table thead:first-of-type th {
	background: linear-gradient(90deg, #0e7490, #06b6d4) !important;
	color: #fff !important;
	padding: 8px 10px !important;
	font-size: 13px;
	letter-spacing: .02em;
	border: none !important;
}
/* En-tête de colonnes */
.bulletin-table thead:nth-of-type(2) th {
	padding: 7px 8px !important;
	font-size: 12px;
	white-space: nowrap;
}
/* Corps : un peu plus d'air */
.bulletin-table tbody td { padding: 6px 8px !important; }
/* Zébrage (les cellules colorées — sigle, état — gardent leur couleur) */
.bulletin-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.05); }
.bulletin-table tbody tr:hover { background: rgba(6,182,212,0.14) !important; }

/* Totaux + moyennes */
.bulletin-table tfoot th,
.bulletin-table tfoot td { padding: 6px 8px !important; }
.bulletin-table tfoot tr:nth-last-child(-n+2) th { font-size: 13px; }
.bulletin-table tfoot tr:nth-last-child(-n+2) th:last-child {
	font-size: 16px;
	font-weight: 800;
}
.bulletin-table tfoot tr:last-child th:last-child {
	background: #0891b2 !important;
	color: #fff !important;
}

/* Tentative retirée du cursus (cours repris ultérieurement) :
   affichée pour la transparence du parcours, mais hors moyenne. */
.bulletin-table tbody tr.cours-retire { opacity: .55; }
.bulletin-table tbody tr.cours-retire td:nth-child(2) { text-decoration: line-through; }
</style>

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
	// Compteurs propres à chaque note : un semestre non renseigné ne doit pas
	// diluer la moyenne (même règle que pour les moyennes générales).
	$cumulWorkCount   = 0;
	$cumulChapelCount = 0;

	// GPA pondéré par les crédits : somme(crédit × note) / somme(crédits),
	// cumulée sur toutes les sessions. Formule officielle (système LMD).
	$gpaCredits        = 0;
	$gpaNoteCredits    = 0;
	$gpaCreditsMaj     = 0;
	$gpaNoteCreditsMaj = 0;
	$cumulGen = 0;
	$cumulMaj = 0;
	$cumulGenCount = 0;
	$cumulMajCount = 0;
	
	/**/
	$cumulFinale = 0;
	$cumulFinaleCount = 0;

	// Récupérer toutes les sessions distinctes où l'étudiant a des notes
	$searchAllSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
		FROM t_2023_notes n 
		INNER JOIN t_2023_session s ON n.session_id = s.session_id 
		WHERE n.student_id = '".$student_id."' AND n.ajout IN (0,1) 
		ORDER BY s.session_year ASC, s.session_semester ASC");

	$sessionCount = 0;
	
	while($showSs = $searchAllSessions->fetch()){
		$sessionCount++;
		$session_id = $showSs['session_id'];
		$combinAnual = $showSs['session_year'];
		
		// Récupérer le yearlevel depuis les notes de cette session
		$getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id='".$student_id."' AND session_id='".$session_id."' AND ajout IN (0,1) ORDER BY ajout DESC LIMIT 1");
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
				$cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout IN (0,1) AND session_id='".$session_id."' ORDER BY ajout DESC, id");

				if ($cours->rowCount() > 0) {
					?>
					<b></b>
					<div class="tbl-x-scroll"><table class="simpleTbl mb-1 bulletin-table">
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
$tcreditMaj = 0;
$tnotecreditMaj = 0;
$tcredit = 0;
$tcreditGPA = 0;
$tnote = 0;
$tnotecredit = 0;
$moyenGenSem = null;
$moyenMajSem = null;
$moyenFinale = null;
					$note_id = 0;				
					while($crs=$cours->fetch()){
						$note_id = $crs['id'];
						$isValidationCourse = ($crs['cours_category'] == 5) 
							|| stripos($crs['Sigle'] ?? '', 'RELP 291') !== false
							|| stripos($crs['title_cours'] ?? '', 'formation spirituelle') !== false;
						$gradeValue = (float)$crs['grade'];
						$isLegacyValidationE = $isValidationCourse && $gradeValue > 0 && $gradeValue < 10;
						// Codes de statut : -2 validé sans note, -3 échec sans note,
						// -4 pas encore noté (NN), -1 ancien code hérité.
						// Un 0 saisi est une VRAIE note et compte dans la moyenne.
						$isNonNote = ($gradeValue == -4 || $gradeValue == -1 || !is_numeric($crs['grade']) || trim((string)$crs['grade']) === '');
						$hasStatusOnlyGrade = ($gradeValue == -2 || $gradeValue == -3 || $isNonNote || $isLegacyValidationE);
						$notePasChiffree = $isNonNote;

						/* Tentative retirée (ajout = 0) : cours supprimé du cursus, le plus
						   souvent parce qu'il a été REPRIS ensuite. Affichée pour la
						   transparence du parcours, mais exclue de la moyenne. */
						$estRetire = ((int)$crs['ajout'] === 0);

						$statusLetter = '';
						$statusTitle = '';
						$statusClass = 'bg-none';
						if ($isNonNote) {
							$statusLetter = 'NN';
							$statusTitle = 'Pas encore noté';
							$statusClass = 'bg-slate-500';
						} elseif ($gradeValue == -2 || ($isValidationCourse && $gradeValue >= 10)) {
							$statusLetter = 'V';
							$statusTitle = 'Valide';
							$statusClass = 'bg-green-500';
						} elseif ($gradeValue == -3 || $isLegacyValidationE) {
							$statusLetter = 'E';
							$statusTitle = 'Echec';
							$statusClass = 'bg-red-500';
						} elseif ($gradeValue >= 0) {
							if ($gradeValue >= 10) {
								$statusLetter = 'S';
								$statusTitle = 'Succes';
								$statusClass = 'bg-green-500';
							} else {
								$statusLetter = 'E';
								$statusTitle = 'Echec';
								$statusClass = 'bg-red-500';
							}
						}

						if ($hasStatusOnlyGrade) {
							$displayGrade = $statusLetter;
						} else {
							$displayGrade = ($gradeValue == -2 ? 'OK' : $crs['grade']);
						}
						if (!empty($crs)) {
							?>
							<tbody class="<?=$bg_four_color?>">
								<form method="post" action="<?=$app_base?>/app/.student/updatenote?id=<?=$id;?>&nbr=<?=$sessionCount.$nbr;?>&note_id=<?=$note_id;?>&as=<?=$sessionCount?>&user_id=<?=$rg_id?>" class="form-update-note">			
				<tr id="note<?=$sessionCount.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black<?= $estRetire ? ' cours-retire' : '' ?>">
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
}elseif ($isValidationCourse) {
	echo "``";
}else{
	echo "-";
}
						 ?></td>
					<td class="<?=$bg_six_color?> text-slate-800 px-0"><?php if ($isValidationCourse): ?><span class="px-2"><?php echo ($statusLetter === 'V') ? '<span style="color:#15803d;font-weight:bold;">V</span>' : (($statusLetter === 'E') ? '<span style="color:#b91c1c;font-weight:bold;">E</span>' : ''); ?></span><?php elseif ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 note-input" type="text" name="nb_crd<?=$sessionCount.$nbr;?>" value="<?=$displayGrade?>" data-action="<?=$app_base?>/app/.student/updatenote?id=<?=$id;?>&nbr=<?=$sessionCount.$nbr;?>&note_id=<?=$note_id;?>&as=<?=$sessionCount?>&user_id=<?=$rg_id?>" data-nbr="<?=$sessionCount.$nbr;?>"><?php elseif ($rg_level <= 6): ?><span class="px-2"><?= $displayGrade ?></span><?php else: ?><em class="px-2">masqué</em><?php endif; ?></td>
					<td><?php if($estRetire || $isValidationCourse || $hasStatusOnlyGrade){ $notecredi = 0; echo '--'; }else{ echo $notecredi = $crs['credit'] * $crs['grade']; } ?></td>
					
					<td class="<?= $estRetire ? 'bg-slate-600' : $statusClass ?> text-center" title="<?= $estRetire ? 'Tentative retirée du cursus (hors moyenne)' : $statusTitle ?>"><?= $estRetire ? 'RET' : $statusLetter ?></td>
					<td><?php if ($rg_level <= 3): ?><div class="relative">
						<a href="#" id="coursPush<?=$sessionCount.$nbr?>" data-bs-toggle="dropdown" aria-expanded="false" title="Historique de solde"><span class="bi-three-dots-vertical"></span></a>

							<ul class="dropdown-menu absolute border border-slate-600 <?=$bg_one_color?> text-slate-100 p-0 rounded-md text-xs">

								<li><p class="px-2 py-1">Session ID : <?=$session_id?></p></li>
								<hr>
								<?php if ($estRetire): ?>
								<li><a href="<?=$app_base?>/app/.student/recup-cours?student_id=<?=$student_id?>&id=<?=$id?>&as=<?=$sessionCount?>&idSupprCours=<?=$note_id?>&user_id=<?=$rg_id?>">
									<p class="px-2 py-1 hover:bg-green-600 hover:text-white"><i class="bi-arrow-counterclockwise"></i> Restaurer</p>
								</a></li>
								<?php else: ?>
								<li><a href="#" class="btn-delete-cours"
									data-url="<?=$app_base?>/app/.student/del-cours.momentanee.php?student_id=<?=$student_id?>&id=<?=$id?>&as=<?=$sessionCount?>&idSupprCours=<?=$note_id?>&user_id=<?=$rg_id?>"
									data-cours="<?=$crs['title_cours']?>">
									<p class="px-2 py-1 hover:bg-red-600 hover:text-white"><i class="bi-trash"></i> Supprimer</p>
								</a></li>
								<?php endif; ?>


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
$isPassFail = $isValidationCourse || $hasStatusOnlyGrade || $notePasChiffree || $estRetire;
if (!$estRetire) { $tcredit += $credit + $crs['credit']; }
if (!$isPassFail) {
	$tcreditGPA += $crs['credit'];
	$tnote+= $note + $crs['grade'];
	$tnotecredit+= $notecredit + $notecredi;
}


/* --- CALCULE DES NOTES MAJEURS --- */
 
if ((($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur")) && !$isPassFail) {
	$gradeMaj = $crs['grade'];
	$nbrMaj++;
	// GPA majeur : on additionne crédits et points
	$tcreditMaj     += $crs['credit'];
	$tnotecreditMaj += $crs['credit'] * $gradeValue;
}else{
	$gradeMaj = 0;
}

	$tTMaj += $tMaj + $gradeMaj;

/* --- CALCULE DES NOTES FINALES --- */
 
if ((($crs['cours_category'] == 1) OR ($crs['cours_category'] == "Majeur") OR ($crs['cours_category'] == 0) OR ($crs['cours_category'] == "Général")) && !$isPassFail) {
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
					<?php /* Ponderee par les credits, comme la generale, le bulletin et l'espace etudiant (c'etait une simple moyenne des notes) */ ?>
					<th class="px-2"><?php if($tcreditMaj > 0){echo $moyenMajSem = round(($tnotecreditMaj/$tcreditMaj),2);}else{echo "--";$moyenMajSem=null;}?></th>
				</tr>
				<!--  -->
				<tr>
					<th colspan="4" class="text-right">Moyenne Générale</th>
					<th class="px-2 bg-cyan-700"><?php if($tcreditGPA > 0){$moyenGenSem = $tnotecredit/$tcreditGPA; echo round($moyenGenSem,2);}else{echo "--";$moyenGenSem = null;}?></th>
				</tr>
			</tfoot>
							
					</table></div>
				<?php
				// Compteurs indépendants : un semestre non renseigné ne dilue plus
		if (!empty($grade_work_educ))   { $cumulWorkNote += $grade_work_educ;   $cumulWorkCount++; }
		if (!empty($grade_chapel_part)) { $cumulChapel   += $grade_chapel_part; $cumulChapelCount++; }

		if ($moyenGenSem !== null) {
			$cumulGen += $gen + $moyenGenSem;
			$cumulGenCount++;
		}
		if ($moyenMajSem !== null) {
			$cumulMaj += $maj + $moyenMajSem;
			$cumulMajCount++;
		}

		// Cumuls du GPA : on additionne crédits et points, jamais des moyennes
		$gpaCredits        += $tcreditGPA;
		$gpaNoteCredits    += $tnotecredit;
		$gpaCreditsMaj     += $tcreditMaj;
		$gpaNoteCreditsMaj += $tnotecreditMaj;
		
		/**/
		if ($moyenFinale !== null) {
			$cumulFinale += $finale + $moyenFinale;
			$cumulFinaleCount++;
		}
				}
		?>
</div>
		<?php
	}

	?>

<?php
	// Valeurs cumulatives affichees (moyenne des notes par-semestre)
	$dispCumulWork   = ($cumulWorkCount > 0) ? round($cumulWorkNote / $cumulWorkCount, 2) : '';
	$dispCumulChapel = ($cumulChapelCount > 0) ? round($cumulChapel / $cumulChapelCount, 2) : '';
	$cumulAction = $app_base.'/app/.student/updateCumulativePromotionNote?id='.$id.'&student_id='.$student_id.'&user_id='.$rg_id;
?>
<div class='p-1 <?=$bg_three_color?> hover:<?=$bg_four_color?> mb-4 rounded-md border-2 border-slate-600 hover:border-cyan-500 transition-all text-xs text-white'>
	<b>MOYENNE CUMULATIVE</b>
	<?php if ($rg_level <= 3): ?><p class="text-slate-300 italic mb-1">Saisir une note cumulative la repartit automatiquement, de facon coherente, sur chaque semestre.</p><?php endif; ?>
	<table class="simpleTbl mb-1 w-full">
		<tbody class=" <?=$bg_two_color?>">
			<tr>
				<td class="p-1 w-8/12 text-right">Note de Work Education cumulative</td>
				<td class="<?=$bg_six_color?> text-slate-800 px-0 w-2/12 text-bold"><?php if ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 cumul-input w-full" type="text" name="cumul_work_educ" value="<?=$dispCumulWork?>" data-original="<?=$dispCumulWork?>" data-action="<?=$cumulAction?>" data-group="cumul-all"><?php else: ?><span class="px-2 text-white"><?= $dispCumulWork ?></span><?php endif; ?></td>
			</tr>
			<!-- <tr>
				<td class="p-1 w-8/12 text-right">Nemarque académique cumulative</td>
				<td class="px-2 w-2/12 text-bold"><?php if($sessionCount > 0){echo round($cumulremarkAcad/$sessionCount, 2);}else{echo 0;}?></td>
			</tr> -->
			<tr>
				<td class="p-1 w-8/12 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
				<td class="<?=$bg_six_color?> text-slate-800 px-0 w-2/12 text-bold"><?php if ($rg_level <= 3): ?><input class="insimple text-sm bg-transparent px-2 cumul-input w-full" type="text" name="cumul_chapel_part" value="<?=$dispCumulChapel?>" data-original="<?=$dispCumulChapel?>" data-action="<?=$cumulAction?>" data-group="cumul-all"><?php else: ?><span class="px-2 text-white"><?= $dispCumulChapel ?></span><?php endif; ?></td>
			</tr>
		</tbody>
	</table>
	<table class="simpleTbl mb-1 w-full">
		<thead class="bg-slate-900">
			<?php
				/* « Moyenne Cumulative » a été supprimée : elle affichait la même
				   valeur que « Moyenne Générale Cumulative » ($moyenFinale et
				   $moyenGenSem étaient alimentés par le même calcul).
				   Ordre repris de la page bulletin : majeure, puis générale mise
				   en avant. */
			?>
			<tr>
				<th class="p-1 w-8/12 text-right">Moyenne Majeure Cumulative</th>
				<th class="py-1 px-2 w-2/12"><?= $gpaCreditsMaj > 0 ? round($gpaNoteCreditsMaj / $gpaCreditsMaj, 2) : '--' ?></th>
			</tr>
			<tr>
				<th class="p-1 w-8/12 text-right bg-cyan-700">Moyenne Générale Cumulative</th>
				<th class="py-1 px-2 w-2/12 bg-cyan-700 text-white"><?= $gpaCredits > 0 ? round($gpaNoteCredits / $gpaCredits, 2) : '--' ?></th>
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
					if (window.ZoneAjax) { ZoneAjax.recharger(); } else { window.location.reload(); }
				}
			},
			error: function() {
				$('#transcriptSSContainer').css('opacity', '1');
				tsDire('error', 'Erreur lors du rafraîchissement');
			}
		});
	}

	// Validation des notes de cours: nombre, V (valide), E (echec) ou NN (pas encore note)
	function validateCourseNote(value) {
		var v = (value || '').trim().toUpperCase();
		if (v === 'V' || v === 'E' || v === 'OK' || v === 'NN' || v === '') {
			return { valid: true };
		}
		var numValue = parseFloat(v.replace(',', '.'));
		if (isNaN(numValue)) {
			return { valid: false, message: 'La note doit etre un nombre valide, V ou E.' };
		}
		if (numValue > 20) {
			return { valid: false, message: 'La note ne peut pas depasser 20. Veuillez saisir une note entre 0 et 20.' };
		}
		if (numValue < 0) {
			return { valid: false, message: 'La note ne peut pas etre negative.' };
		}
		return { valid: true };
	}

	// Validation des notes de promotion: uniquement numerique
	function validatePromotionNote(value) {
		if ((value || '').trim() === '') {
			return { valid: true };
		}
		var numValue = parseFloat(String(value).replace(',', '.'));
		if (isNaN(numValue)) {
			return { valid: false, message: 'La note doit etre un nombre valide.' };
		}
		if (numValue > 20) {
			return { valid: false, message: 'La note ne peut pas depasser 20. Veuillez saisir une note entre 0 et 20.' };
		}
		if (numValue < 0) {
			return { valid: false, message: 'La note ne peut pas etre negative.' };
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
		var rawValue = input.val().trim();
		var upperValue = rawValue.toUpperCase();
		var noteValue;
		if (upperValue === 'V' || upperValue === 'E' || upperValue === 'OK' || upperValue === 'NN' || upperValue === '') {
			noteValue = (upperValue === 'OK') ? 'V' : (upperValue === '' ? 'NN' : upperValue);
			input.val(noteValue);
		} else {
			noteValue = rawValue.replace(',', '.');
			// Arrondir a 2 decimales
			var nVal = parseFloat(noteValue);
			if (!isNaN(nVal)) {
				noteValue = '' + (Math.round(nVal * 100) / 100);
				input.val(noteValue);
			}
		}
		
		// Validation côté client
		var validation = validateCourseNote(noteValue);
		if (!validation.valid) {
			tsDire('error', validation.message);
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
						tsDire('error', response.message || 'Erreur lors de la mise à jour de la note');
					}
				},
				error: function(xhr) {
					input.css('opacity', '1');
					var msg = 'Erreur lors de la mise à jour de la note';
					try {
						var resp = JSON.parse(xhr.responseText);
						if (resp && resp.message) msg = resp.message;
					} catch(e) {}
					tsDire('error', msg);
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
				var validation = validatePromotionNote(val);
				if (!validation.valid) {
					tsDire('error', validation.message);
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
						tsDire('error', response.message || 'Erreur lors de la mise à jour');
					}
				},
				error: function(xhr) {
					allInputs.css('opacity', '1');
					var msg = 'Erreur lors de la mise à jour';
					try {
						var resp = JSON.parse(xhr.responseText);
						if (resp && resp.message) msg = resp.message;
					} catch(e) {}
					tsDire('error', msg);
				}
			});
		}, 500);
	});
	
	// ========== SAISIE DE LA NOTE CUMULATIVE (repartie sur chaque semestre) ==========
	var cumulTimeout;
	$('#transcriptSSContainer').on('change', '.cumul-input', function() {
		var input = $(this);
		var url = input.data('action');
		var group = input.data('group');
		var allInputs = $('#transcriptSSContainer .cumul-input[data-group="' + group + '"]');

		// N'envoyer que les metriques REELLEMENT modifiees (sinon on redistribuerait
		// l'autre note inutilement). On compare a la valeur d'origine.
		var data = {};
		var hasChange = false;
		var hasError = false;
		allInputs.each(function() {
			var el = $(this);
			var val = (el.val() || '').trim();
			var original = ('' + (el.data('original') || '')).trim();
			if (val === original) { return; }      // inchange -> ignore
			if (val === '') { return; }             // vide -> ne pas ecraser
			var validation = validatePromotionNote(val);
			if (!validation.valid) {
				tsDire('error', validation.message);
				el.focus();
				el.css('border', '2px solid red');
				setTimeout(function() { el.css('border', ''); }, 3000);
				hasError = true;
				return false; // break
			}
			data[el.attr('name')] = val.replace(',', '.');
			hasChange = true;
		});

		if (hasError || !hasChange) { return; }

		clearTimeout(cumulTimeout);
		cumulTimeout = setTimeout(function() {
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
						if (typeof Toast !== 'undefined') { Toast.success(response.message || 'Note cumulative repartie !'); }
						refreshTranscriptSS();
					} else {
						tsDire('error', response.message || 'Erreur lors de la repartition');
					}
				},
				error: function(xhr) {
					allInputs.css('opacity', '1');
					var msg = 'Erreur lors de la repartition';
					try { var resp = JSON.parse(xhr.responseText); if (resp && resp.message) msg = resp.message; } catch(e) {}
					tsDire('error', msg);
				}
			});
		}, 500);
	});

	/* Messages en toast (init/toast.php) ; sans lui, la console. Jamais de boite native. */
	function tsDire(type, message) {
		if (typeof Toast !== 'undefined' && typeof Toast[type] === 'function') { Toast[type](message); }
		else if (window.console) { console.warn(message); }
	}

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
		} else if (window.console) {
			console.warn('Confirmation indisponible : le cours n\'est pas supprimé.');
		}
	});
	
});
</script>