<style>
/* Student Menubar Responsive Styles */
@media (max-width: 1023px) {
	.student-profile-sidebar {
		width: 100% !important;
		max-height: none !important;
		height: auto !important;
		overflow: visible !important;
	}
	
	.student-profile-sidebar .flex.my-2 {
		flex-wrap: wrap;
	}
	
	.student-profile-sidebar .w-\[75px\] {
		width: 60px !important;
	}
	
	.student-menu-items {
		display: flex !important;
		flex-wrap: wrap !important;
		gap: 4px;
	}
	
	.student-menu-items > a {
		width: calc(50% - 4px) !important;
	}
	
	.student-menu-items > a > div {
		padding: 6px 8px !important;
		font-size: 12px !important;
	}
	
	.student-menu-items > a > div i {
		font-size: 14px !important;
	}
}

@media (max-width: 640px) {
	.student-profile-sidebar .w-\[75px\] {
		width: 50px !important;
	}
	
	.student-menu-items > a {
		width: 100% !important;
	}
	
	.student-menu-items > a > div {
		padding: 4px 6px !important;
		font-size: 11px !important;
	}
	
	/* Hide some info on mobile */
	.student-profile-sidebar .text-sm {
		font-size: 11px !important;
	}
}
</style>

<div class="student-profile-sidebar my-1 px-2 mx-0.5 lg:w-4/12 xl:w-3/12 bg-slate-300 pb-4">
						
						<div class="flex my-2 relative">

							<a href="#" data-bs-toggle="dropdown" aria-expanded="false">
							<div class="w-[75px] <?=$bg_one_color?>">
								<?php
								
								$extentionImage = substr($profil['image_student'], -4);

								if ($extentionImage == '.jpg' OR $extentionImage == '.JPG') {
								
								?>
									<img src="../app/photosetudiants/<?=$profil['image_student']?>" class="border-1 border-black w-full">

								<?php	
								}else{ ?>
									
									<img src="../app/photosetudiants/10054.jpg" class="border-1 border-black w-full">

								<?php }	?>
								
							</div></a>

							<ul class="dropdown-menu border <?=$bg_five_color?> text-black p-0 rounded-0 text-xs" style="max-height:400px;">
								<?php 
									if(!empty($profil['image_student'])) {
										if ($profil['image_student'] !="" OR $imangeLen >=10) {
								?>
								<a href="#" id="listOpt1"><p class="px-2 py-1 hover:bg-cyan-500">Agrandir</p></a>
								<?php
										} 
									}
								?>
								<a href="#" id="listOpt2"><p class="px-2 py-1 hover:bg-cyan-500">Modifier</p></a>
							</ul>

							<div class="w-9/12 text-left pl-3">
								
								<?php if(!empty($profil['suspended']) && $profil['suspended'] == 1): ?>
								<div class="w-full bg-gradient-to-r from-orange-600 to-orange-500 px-2 text-white mb-1">
									<b><i class="bi-person-dash-fill"></i> SUSPENDU</b>
								</div>
								<?php elseif(!empty($profil['retrait_universite']) && $profil['retrait_universite'] == 2): ?>
								<div class="w-full bg-gradient-to-r from-blue-600 to-blue-500 px-2 text-white mb-1">
									<b><i class="bi-door-open-fill"></i> RETRAIT MOMENTANÉ</b>
								</div>
								<?php elseif(!empty($profil['retrait_universite']) && $profil['retrait_universite'] == 3): ?>
								<div class="w-full bg-gradient-to-r from-blue-800 to-blue-700 px-2 text-white mb-1">
									<b><i class="bi-door-closed-fill"></i> RETRAIT DÉFINITIF</b>
								</div>
								<?php else: ?>
								<div class="w-full bg-gradient-to-r from-cyan-500 px-2 text-white">
									<b>
<?php
if ($profil['new_student'] == 1) {
	echo "Nouveau";
}elseif ($profil['new_student'] == 10) {
	echo "Spécial";
}else{
	echo "Ancien";
}
?>	</b>
								</div>
								<?php endif; ?>
								
								<b class="text-1xl"><?=$profil['student_id'] ?></b>
								<p><?php
if($profil['annee_etude'] == 0) {
	echo "Remise à niveau";
}elseif ($profil['annee_etude']>0 AND $profil['annee_etude']<=3) {
	echo "Licence ".$profil['annee_etude'];
}else{
	echo "Master ".$profil['annee_etude']-3;
}
								?></p>
								<p><a href="https://mail.google.com/mail/u/0/#inbox?compose=<?=$profil['student_email']?>" target="_blank"><?=$profil['student_email']?></a></p>
							</div>
							
						</div>
						<hr>
						<div class="w-full py-2 text-sm">
								<b><?=strtoupper($profil['student_nom']) ?> <?=$profil['student_prenom'] ?></b><br>
								<em><?=$profil['etude_envisage']." - ".$profil['etude_option'] ?></em><br>
								<b>Année <?=$profil['annee_scolaire']?></b><br>
								<a href="?id=<?=$id;?>&page=histInfos" class="text-[11px] text-green-600 hover:text-green-400" style="line-height: 12px;">
									<i class="bi-clock-history"></i> Modifié par <?php 
								// Récupérer la dernière modification depuis l'historique
								$findLastModif = $dtb->query("SELECT h.action_by, h.action_date, u.prenom, u.nom 
									FROM t_student_modification_history h 
									LEFT JOIN compt_utilisateur u ON h.action_by = u.id 
									WHERE h.student_id = '".$student_id."' 
									ORDER BY h.action_date DESC LIMIT 1");
								$lastModif = $findLastModif->fetch();
								
								if (!empty($lastModif) && !empty($lastModif['prenom'])) {
									echo "<b>[".$lastModif['prenom']."]</b><br>".date('Y-m-d H:i', strtotime($lastModif['action_date']));
								} elseif (!empty($profil['last_change_user_id'])) {
									// Fallback sur l'ancienne méthode si pas d'historique
									$user_modif_id = $profil['last_change_user_id'];
									$findUser = $dtb->query('SELECT * FROM compt_utilisateur WHERE id = "'.$user_modif_id.'"');
									$showUser = $findUser->fetch();
									if (!empty($showUser)) {
										echo "<b>[".$showUser['prenom']."]</b><br>".$profil['last_change_datetime'];	
									}
								}
								 ?>
								</a>
						</div><hr>
						<div class="student-menu-items w-full text-md">
								<a href="?id=<?=$id;?>&page=information">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "information") {
	echo "bg-cyan-700 text-white";
} ?>">
										<i class="bi-info-square"></i>
												Information
									</div>
								</a>
		
								<a href="?id=<?=$id;?>&page=transcriptSS" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "transcriptSS") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-newspaper"></i>
												Historique des notes
									</div>
								</a>
								
								<a href="?id=<?=$id;?>&page=newCours" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "newCours") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-folder-plus"></i>
												Ajout de cours
									</div>
								</a>
		
								<a href="?id=<?=$id;?>&page=bulletin" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "bulletin") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-journal-album"></i>
												Relevé de notes
									</div>
								</a>
								
								<a href="?id=<?=$id;?>&page=histNotes" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "histNotes") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-clock-history"></i>
												Audit notes
									</div>
								</a>
								<hr>
								<a href="?id=<?=$id;?>&page=diplome&langue=FR" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md <?php 
if(isset($_GET['page']) and $_GET['page'] == "diplome") {
	echo "bg-cyan-700 text-white";
} ?>">
										
										<i class="bi-box-seam-fill"></i>
												Diplôme
									</div>
								</a>
								<hr>
								<a href="#" class="toolInactive">
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 rounded-md">
										<i class="bi-archive-fill"></i>
												Archive
									</div>
								</a>

								<?php if(!empty($profil['suspended']) && $profil['suspended'] == 1): ?>
								<a href="#" id="linkLeverSuspension">
									<div class="w-full hover:bg-green-500 hover:text-slate-100 p-2 my-1 text-green-600 rounded-md">
										<i class="bi-person-check-fill"></i>
												Lever la suspension
									</div>
								</a>
								<?php else: ?>
								<a href="#" id="linkSuspendStd" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-orange-500 hover:text-slate-100 p-2 my-1 text-orange-600 rounded-md">
										<i class="bi-person-dash-fill"></i>
												Suspendre
									</div>
								</a>
								<?php endif; ?>

								<?php if(!empty($profil['retrait_universite']) && $profil['retrait_universite'] >= 2): ?>
								<a href="#" id="linkAnnulerRetrait">
									<div class="w-full hover:bg-green-500 hover:text-slate-100 p-2 my-1 text-green-600 rounded-md">
										<i class="bi-arrow-return-left"></i>
												Annuler le retrait
									</div>
								</a>
								<?php else: ?>
								<a href="#" id="linkRetraitUniv" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-blue-500 hover:text-slate-100 p-2 my-1 text-blue-600 rounded-md">
										<i class="bi-door-open-fill"></i>
												Retrait de l'université
									</div>
								</a>
								<?php endif; ?>

								<a href="#" id="linkSupprStd" <?php if($rg_user['level'] <= 2) { echo "";}else{ echo "class='toolInactive'";}?>>
									<div class="w-full hover:bg-cyan-500 hover:text-slate-100 p-2 my-1 text-red-600 rounded-md">
										
										<i class="bi-trash3"></i>
												Supprimer
									</div>
								</a>

							</div>
						</div>


							<!-- MODAL SUSPENSION -->
						<div class="fixed inset-0 z-40 hidden overflow-y-auto" id="notifSuspendStd" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.student/suspendre.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>">
							<div class="w-[95%] max-w-[500px] <?=$bg_eight_color?> border-2 border-orange-500 mx-auto my-[3%] lg:my-[5%] opacity-100 drop-shadow-2xl rounded-lg">
								<div class="p-3 bg-orange-500 text-white rounded-t-md">
									<p class="text-lg font-bold"><i class="bi-exclamation-triangle-fill mr-2"></i>Suspendre l'étudiant</p>
								</div>
								<div class="p-4">
									<p class="mb-3 text-sm text-slate-600">Cette action va suspendre <b><?=strtoupper($profil['student_nom']).' '.$profil['student_prenom']?></b> de l'université. Il ne pourra pas se réinscrire ni prendre des cours pendant la période définie.</p>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Date de début</label>
										<input type="date" name="date_debut_suspension" required class="w-full p-2 border border-slate-300 rounded-md" value="<?=date('Y-m-d')?>">
									</div>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Date de fin</label>
										<input type="date" name="date_fin_suspension" id="date_fin_suspension" required class="w-full p-2 border border-slate-300 rounded-md">
									</div>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Durée prédéfinie</label>
										<select id="duree_predefinie" class="w-full p-2 border border-slate-300 rounded-md">
											<option value="">-- Choisir une durée --</option>
											<option value="6">1 semestre (6 mois)</option>
											<option value="12">2 semestres (1 an)</option>
											<option value="24">4 semestres (2 ans)</option>
											<option value="36">6 semestres (3 ans)</option>
											<option value="0">Indéfini</option>
										</select>
									</div>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Motif de la suspension</label>
										<textarea name="motif_suspension" rows="3" required class="w-full p-2 border border-slate-300 rounded-md" placeholder="Indiquez le motif de la suspension..."></textarea>
									</div>

								</div>
								<div class="flex p-3 border-t border-slate-200 justify-end gap-2">
									<a href="#" id="cancelSuspendStd" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md hover:bg-slate-300">Annuler</a>
									<button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600">
										<i class="bi-person-dash mr-1"></i> Confirmer la suspension
									</button>
								</div>
							</div>
</form>
						</div>

							<!-- MODAL LEVER SUSPENSION -->
						<div class="fixed inset-0 z-40 hidden overflow-y-auto" id="notifLeverSuspension" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.student/lever-suspension.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>">
							<div class="w-[95%] max-w-[500px] <?=$bg_eight_color?> border-2 border-green-500 mx-auto my-[3%] lg:my-[5%] opacity-100 drop-shadow-2xl rounded-lg">
								<div class="p-3 bg-green-500 text-white rounded-t-md">
									<p class="text-lg font-bold"><i class="bi-person-check-fill mr-2"></i>Lever la suspension</p>
								</div>
								<div class="p-4">
									<p class="mb-3 text-sm text-slate-600">Vous êtes sur le point de lever la suspension de <b><?=strtoupper($profil['student_nom']).' '.$profil['student_prenom']?></b>.</p>
									
									<?php if(!empty($profil['motif_suspension'])): ?>
									<div class="mb-3 p-3 bg-orange-50 border border-orange-200 rounded-md">
										<p class="text-sm font-medium text-orange-800 mb-1">Motif de suspension :</p>
										<p class="text-sm text-orange-700"><?=$profil['motif_suspension']?></p>
										<p class="text-xs text-orange-600 mt-2">
											Du <?=date('d/m/Y', strtotime($profil['date_debut_suspension']))?> 
											au <?=date('d/m/Y', strtotime($profil['date_fin_suspension']))?>
										</p>
									</div>
									<?php endif; ?>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Commentaire (optionnel)</label>
										<textarea name="commentaire_levee" rows="2" class="w-full p-2 border border-slate-300 rounded-md" placeholder="Raison de la levée de suspension..."></textarea>
									</div>

								</div>
								<div class="flex p-3 border-t border-slate-200 justify-end gap-2">
									<a href="#" id="cancelLeverSuspension" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md hover:bg-slate-300">Annuler</a>
									<button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
										<i class="bi-person-check mr-1"></i> Confirmer la levée
									</button>
								</div>
							</div>
</form>
						</div>

							<!-- MODAL RETRAIT UNIVERSITÉ -->
						<div class="fixed inset-0 z-40 hidden overflow-y-auto" id="notifRetraitUniv" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.student/retrait-universite.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>">
							<div class="w-[95%] max-w-[550px] <?=$bg_eight_color?> border-2 border-blue-500 mx-auto my-[3%] lg:my-[5%] opacity-100 drop-shadow-2xl rounded-lg">
								<div class="p-3 bg-blue-500 text-white rounded-t-md">
									<p class="text-lg font-bold"><i class="bi-door-open-fill mr-2"></i>Retrait de l'université</p>
								</div>
								<div class="p-4">
									<p class="mb-3 text-sm text-slate-600">Vous êtes sur le point de retirer <b><?=strtoupper($profil['student_nom']).' '.$profil['student_prenom']?></b> de l'université.</p>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Type de retrait</label>
										<select name="type_retrait" id="type_retrait" required class="w-full p-2 border border-slate-300 rounded-md">
											<option value="">-- Choisir le type de retrait --</option>
											<option value="momentane">Retrait momentané</option>
											<option value="definitif">Retrait définitif</option>
										</select>
									</div>
									
									<div class="mb-3 p-3 bg-slate-100 border border-slate-200 rounded-md">
										<label class="block text-sm font-medium mb-1 text-slate-700">Date d'entrée à l'université</label>
										<input type="date" name="date_entree_universite" readonly class="w-full p-2 border border-slate-300 rounded-md bg-slate-50" value="<?=!empty($profil['date_entry']) ? date('Y-m-d', strtotime($profil['date_entry'])) : ''?>">
										<p class="text-xs text-slate-500 mt-1">Date d'inscription initiale de l'étudiant</p>
									</div>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Date de départ</label>
										<input type="date" name="date_depart" required class="w-full p-2 border border-slate-300 rounded-md" value="<?=date('Y-m-d')?>">
									</div>
									
									<div class="mb-3" id="divDateRetour" style="display:none;">
										<label class="block text-sm font-medium mb-1 text-slate-700">Date probable de retour</label>
										<input type="date" name="date_retour_probable" id="date_retour_probable" class="w-full p-2 border border-slate-300 rounded-md">
										<p class="text-xs text-slate-500 mt-1">Obligatoire pour un retrait momentané</p>
									</div>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Cause du départ</label>
										<select name="cause_depart" id="cause_depart" required class="w-full p-2 border border-slate-300 rounded-md mb-2">
											<option value="">-- Choisir la cause --</option>
											<option value="raisons_personnelles">Raisons personnelles</option>
											<option value="raisons_financieres">Raisons financières</option>
											<option value="raisons_medicales">Raisons médicales</option>
											<option value="transfert">Transfert vers autre établissement</option>
											<option value="voyage">Voyage / Déplacement</option>
											<option value="abandon">Abandon des études</option>
											<option value="exclusion">Exclusion</option>
											<option value="deces">Décès</option>
											<option value="autre">Autre</option>
										</select>
										<textarea name="details_cause" rows="3" class="w-full p-2 border border-slate-300 rounded-md" placeholder="Détails supplémentaires sur la cause du départ..."></textarea>
									</div>

								</div>
								<div class="flex p-3 border-t border-slate-200 justify-end gap-2">
									<a href="#" id="cancelRetraitUniv" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md hover:bg-slate-300">Annuler</a>
									<button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
										<i class="bi-door-open mr-1"></i> Confirmer le retrait
									</button>
								</div>
							</div>
</form>
						</div>

							<!-- MODAL ANNULER RETRAIT -->
						<div class="fixed inset-0 z-40 hidden overflow-y-auto" id="notifAnnulerRetrait" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.student/annuler-retrait.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>">
							<div class="w-[95%] max-w-[500px] <?=$bg_eight_color?> border-2 border-green-500 mx-auto my-[3%] lg:my-[5%] opacity-100 drop-shadow-2xl rounded-lg">
								<div class="p-3 bg-green-500 text-white rounded-t-md">
									<p class="text-lg font-bold"><i class="bi-arrow-return-left mr-2"></i>Annuler le retrait</p>
								</div>
								<div class="p-4">
									<p class="mb-3 text-sm text-slate-600">Vous êtes sur le point d'annuler le retrait de <b><?=strtoupper($profil['student_nom']).' '.$profil['student_prenom']?></b> et de le réintégrer à l'université.</p>
									
									<?php if(!empty($profil['retrait_universite']) && $profil['retrait_universite'] >= 2): ?>
									<div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
										<p class="text-sm font-medium text-blue-800 mb-1">Informations du retrait :</p>
										<p class="text-sm text-blue-700">
											<strong>Type :</strong> <?=$profil['type_retrait'] == 'momentane' ? 'Retrait momentané' : 'Retrait définitif'?>
										</p>
										<p class="text-sm text-blue-700">
											<strong>Cause :</strong> <?=ucfirst(str_replace('_', ' ', $profil['cause_depart'] ?? 'Non spécifiée'))?>
										</p>
										<?php if(!empty($profil['date_depart_universite'])): ?>
										<p class="text-xs text-blue-600 mt-2">
											Date de départ : <?=date('d/m/Y', strtotime($profil['date_depart_universite']))?>
											<?php if(!empty($profil['date_retour_probable'])): ?>
											| Retour prévu : <?=date('d/m/Y', strtotime($profil['date_retour_probable']))?>
											<?php endif; ?>
										</p>
										<?php endif; ?>
									</div>
									<?php endif; ?>
									
									<div class="mb-3">
										<label class="block text-sm font-medium mb-1 text-slate-700">Commentaire (optionnel)</label>
										<textarea name="commentaire_retour" rows="2" class="w-full p-2 border border-slate-300 rounded-md" placeholder="Raison de l'annulation du retrait..."></textarea>
									</div>

								</div>
								<div class="flex p-3 border-t border-slate-200 justify-end gap-2">
									<a href="#" id="cancelAnnulerRetrait" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-md hover:bg-slate-300">Annuler</a>
									<button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
										<i class="bi-arrow-return-left mr-1"></i> Confirmer le retour
									</button>
								</div>
							</div>
</form>
						</div>

							<!-- MODIF IMAGE -->

						<div class="fixed inset-0 z-40 hidden overflow-y-auto" id="notifModifIMG" style="backdrop-filter: blur(30px);">
<form method="post" action="../app/.student/updtateImgStd.php?id=<?=$id?>&user_id=<?=$rg_id?>&student_id=<?=$student_id?>" enctype="multipart/form-data">
							<div class="w-[95%] max-w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[3%] lg:my-[5%] opacity-100 drop-shadow-2xl rounded-lg">
								<div class="p-2">
									<p>Modifier l'image d'étudiant</p>
								</div>
								<div class="p-2">
									
									<div class="rounded-md <?=$bg_six_color?> h-20 text-center relative active hover:<?=$bg_three_color?> hover:text-white">
										<label for="image_student" class="text-lg mt-4"><i class="bi-image"></i></label>
										<p id="imgNote">Choisir une image sur votre PC</p>
										<input type="file" accept=".jpg, .png" name="image_student" id="image_student" class="w-full h-20 absolute z-40 top-0 left-0" style="opacity: 0;">
									</div>
									

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelModifIMG" class="px-2 <?=$bg_six_color?> rounded-md py-1 mx-1">Annuler</a>
									<button type="submit" class="px-2 rounded-md py-1 text-white mx-1 btnInactive" id="btnModify">Modifier</button>
								</div>
							</div>
</form>
						</div>

<!-- AFFICHE IMAGE -->

						<div class="fixed inset-0 z-40 hidden overflow-y-auto" id="notifAffichIMG" style="backdrop-filter: blur(30px);">

							<div class="w-[95%] max-w-[500px] <?=$bg_eight_color?> border-2 border-slate-700 mx-auto my-[3%] lg:my-[5%] opacity-100 drop-shadow-2xl rounded-lg">
								
								<div class="p-2">
									<div class="w-full h-[400px]" style="background-image: url('../app/photosetudiants/<?=$profil['image_student']?>');background-position: center; background-size: cover;background-repeat: no-repeat;">
										
									</div>

								</div>
								<div class="flex p-2">
									<a href="#" id="cancelAffichIMG" class="px-2 <?=$bg_six_color?> rounded-md py-1 mx-1">Retour</a>
								</div>
							</div>

						</div>


<script type="text/javascript">
	$(document).ready(function(){
		$('#listOpt1').click(function(){
			$('#notifAffichIMG').css({'display':'block'});
		});
		$('#listOpt2').click(function(){
			$('#notifModifIMG').css({'display':'block'});
		});
		$('#cancelModifIMG').click(function(){
			$('#notifModifIMG').css({'display':'none'});
		});
		$('#cancelAffichIMG').click(function(){
			$('#notifAffichIMG').css({'display':'none'});
		});
		$('#image_student').on('change',function(){
			var image_student = $(this).val();
			if(image_student!="") {
				$('#imgNote').text('Image bien ajouté.');
				$('#btnModify').attr('class','px-2 rounded-md py-1 text-white mx-1 bg-cyan-700');
			}
		});
		$('#linkSupprStd').click(function(){
			$('#notifSupprStd').css({'display':'block'});
		});
		
		// Gestion de la suspension
		$('#linkSuspendStd').click(function(){
			$('#notifSuspendStd').css({'display':'block'});
		});
		$('#cancelSuspendStd').click(function(){
			$('#notifSuspendStd').css({'display':'none'});
		});
		
		// Gestion de la levée de suspension
		$('#linkLeverSuspension').click(function(){
			$('#notifLeverSuspension').css({'display':'block'});
		});
		$('#cancelLeverSuspension').click(function(){
			$('#notifLeverSuspension').css({'display':'none'});
		});
		
		// Calcul automatique de la date de fin selon la durée choisie
		$('#duree_predefinie').change(function(){
			var duree = $(this).val();
			if(duree !== '' && duree !== '0') {
				var today = new Date();
				today.setMonth(today.getMonth() + parseInt(duree));
				var yyyy = today.getFullYear();
				var mm = String(today.getMonth() + 1).padStart(2, '0');
				var dd = String(today.getDate()).padStart(2, '0');
				$('#date_fin_suspension').val(yyyy + '-' + mm + '-' + dd);
			} else if(duree === '0') {
				// Indéfini = 10 ans
				var today = new Date();
				today.setFullYear(today.getFullYear() + 10);
				var yyyy = today.getFullYear();
				var mm = String(today.getMonth() + 1).padStart(2, '0');
				var dd = String(today.getDate()).padStart(2, '0');
				$('#date_fin_suspension').val(yyyy + '-' + mm + '-' + dd);
			}
		});
		
		// Gestion du retrait de l'université
		$('#linkRetraitUniv').click(function(){
			$('#notifRetraitUniv').css({'display':'block'});
		});
		$('#cancelRetraitUniv').click(function(){
			$('#notifRetraitUniv').css({'display':'none'});
		});
		
		// Afficher/masquer la date de retour selon le type de retrait
		$('#type_retrait').change(function(){
			var typeRetrait = $(this).val();
			if(typeRetrait === 'momentane') {
				$('#divDateRetour').show();
				$('#date_retour_probable').attr('required', true);
			} else {
				$('#divDateRetour').hide();
				$('#date_retour_probable').attr('required', false);
				$('#date_retour_probable').val('');
			}
		});
		
		// Gestion de l'annulation du retrait
		$('#linkAnnulerRetrait').click(function(){
			$('#notifAnnulerRetrait').css({'display':'block'});
		});
		$('#cancelAnnulerRetrait').click(function(){
			$('#notifAnnulerRetrait').css({'display':'none'});
		});
	});
</script>