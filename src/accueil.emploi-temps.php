<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Emplois du temps</title>
	<style>
		.schedule-table {
			border-collapse: collapse;
			width: 100%;
			font-size: 12px;
		}
		.schedule-table th {
			background: #0f172a;
			color: #94a3b8;
			padding: 10px 5px;
			text-align: center;
			font-weight: 600;
			border: 1px solid #334155;
		}
		.schedule-table td {
			border: 1px solid #334155;
			padding: 3px;
			vertical-align: top;
			height: 50px;
			background: #1e293b;
		}
		.schedule-table td.time-col {
			background: #0f172a;
			color: #64748b;
			text-align: center;
			width: 60px;
			vertical-align: middle;
			font-size: 11px;
		}
		
		/* Container pour les cours côte à côte */
		.courses-container {
			display: flex;
			gap: 4px;
			height: 100%;
			width: 100%;
			align-items: stretch;
		}
		
		.course-card {
			border-radius: 4px;
			padding: 6px;
			color: white;
			font-size: 11px;
			cursor: grab;
			transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
			flex: 1;
			min-width: 0;
			overflow: hidden;
			display: flex;
			flex-direction: column;
		}
		.course-card:hover {
			transform: scale(1.02);
			box-shadow: 0 4px 12px rgba(0,0,0,0.3);
			z-index: 10;
		}
		.course-card.dragging {
			opacity: 0.5;
			cursor: grabbing;
		}
		
		/* Couleurs par type de séance (fallback) */
		.course-card.cours { background: #0891b2; }
		.course-card.td { background: #7c3aed; }
		.course-card.tp { background: #059669; }
		.course-card.examen { background: #d97706; }
		
		/* Couleurs par mention */
		.course-card[data-mention="THEO"] { background: linear-gradient(135deg, #6366f1, #4f46e5); } /* Indigo */
		.course-card[data-mention="GEST"] { background: linear-gradient(135deg, #f59e0b, #d97706); } /* Amber */
		.course-card[data-mention="INFO"] { background: linear-gradient(135deg, #06b6d4, #0891b2); } /* Cyan */
		.course-card[data-mention="NURS"] { background: linear-gradient(135deg, #ec4899, #db2777); } /* Pink */
		.course-card[data-mention="EDUC"] { background: linear-gradient(135deg, #10b981, #059669); } /* Emerald */
		.course-card[data-mention="COMM"] { background: linear-gradient(135deg, #8b5cf6, #7c3aed); } /* Violet */
		.course-card[data-mention="LANG"] { background: linear-gradient(135deg, #f97316, #ea580c); } /* Orange */
		.course-card[data-mention="DROI"] { background: linear-gradient(135deg, #ef4444, #dc2626); } /* Red */
		
		.course-card .sigle { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.course-card .course-title { font-weight: 700; font-size: 11px; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
		.course-card .details { opacity: 0.9; font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.course-card .parcours-badge { 
			background: rgba(255,255,255,0.2); 
			padding: 1px 4px; 
			border-radius: 3px; 
			font-size: 9px;
			display: inline-block;
			margin-top: 2px;
		}
		
		.legend-box { display: inline-block; width: 14px; height: 14px; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
		
		/* Drag and Drop styles */
		.schedule-table td.drop-target {
			background: #0f4c5c !important;
			border: 2px dashed #0ea5e9 !important;
		}
		.schedule-table td.drop-invalid {
			background: #7f1d1d !important;
			border: 2px dashed #ef4444 !important;
		}
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="sm:w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto">
						
						<!-- En-tête -->
						<div class="flex items-center justify-between mb-3">
							<div>
								<h1 class="text-lg font-bold text-white"><i class="bi bi-calendar3-week text-cyan-500"></i> Emplois du temps</h1>
								<span class="text-slate-400 text-xs">Année scolaire 2025-2026</span>
							</div>
							<div class="flex gap-2">
								<button onclick="openAddModal()" class="bg-cyan-600 hover:bg-cyan-700 text-white text-xs px-4 py-1.5 rounded flex items-center gap-1">
									<i class="bi bi-plus-lg"></i> Ajouter une séance
								</button>
								<select id="filterMention" class="bg-slate-700 text-white text-xs px-3 py-1.5 rounded border border-slate-600">
									<option value="">Toutes mentions</option>
									<?php
									$mentions = $dtb->query("SELECT DISTINCT mention FROM t_2024_emploi_du_temps WHERE mention != '' ORDER BY mention");
									while($m = $mentions->fetch()) {
										echo '<option value="'.$m['mention'].'">'.$m['mention'].'</option>';
									}
									?>
								</select>
								<select id="filterNiveau" class="bg-slate-700 text-white text-xs px-3 py-1.5 rounded border border-slate-600">
									<option value="">Tous niveaux</option>
									<option value="1">L1</option>
									<option value="2">L2</option>
									<option value="3">L3</option>
									<option value="4">M1</option>
									<option value="5">M2</option>
								</select>
								<select id="filterSemester" class="bg-slate-700 text-white text-xs px-3 py-1.5 rounded border border-slate-600">
									<option value="">Tous semestres</option>
									<option value="1">Semestre 1</option>
									<option value="2">Semestre 2</option>
								</select>
								<select id="filterTypeSeance" class="bg-slate-700 text-white text-xs px-3 py-1.5 rounded border border-slate-600">
									<option value="">Tous types</option>
									<option value="cours">Cours uniquement</option>
									<option value="td">TD uniquement</option>
									<option value="tp">TP uniquement</option>
									<option value="examen">Examens uniquement</option>
								</select>
								<button onclick="openExportModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-4 py-1.5 rounded flex items-center gap-1">
									<i class="bi bi-download"></i> Exporter
								</button>
							</div>
						</div>
						
						<!-- Légende des couleurs par mention -->
						<div class="flex flex-wrap gap-3 mb-3 p-2 bg-slate-800 rounded text-xs">
							<span class="text-slate-400"><i class="bi bi-palette"></i> Couleurs:</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #6366f1, #4f46e5);"></span>THEO</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></span>GEST</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #06b6d4, #0891b2);"></span>INFO</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #ec4899, #db2777);"></span>NURS</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #10b981, #059669);"></span>EDUC</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"></span>COMM</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #f97316, #ea580c);"></span>LANG</span>
							<span><span class="legend-box" style="background: linear-gradient(135deg, #ef4444, #dc2626);"></span>DROI</span>
						</div>

						<!-- Tableau emploi du temps -->
						<?php
						$jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
						$heures = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
						
						// Récupérer les cours avec leurs durées
						$query = $dtb->query("SELECT * FROM t_2024_emploi_du_temps WHERE statut = 'confirme'");
						$coursData = [];
						while($c = $query->fetch()) {
							$hDebut = intval(substr($c['heure_debut'], 0, 2));
							$hFin = intval(substr($c['heure_fin'], 0, 2));
							$c['duree'] = $hFin - $hDebut; // Durée en heures
							$c['heure_debut_int'] = $hDebut;
							$coursData[$c['jour_semaine']][$hDebut][] = $c;
						}
						
						// Tracker les cellules occupées par rowspan
						$cellulesOccupees = [];
						?>
						<table class="schedule-table">
							<thead>
								<tr>
									<th>Heure</th>
									<?php foreach($jours as $j): ?>
									<th><?=$j?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach($heures as $idx => $heure): 
									$heureInt = intval(substr($heure, 0, 2));
								?>
								<tr>
									<td class="time-col"><?=substr($heure,0,2)?>h</td>
									<?php foreach($jours as $jour): ?>
										<?php 
										// Vérifier si cette cellule est occupée par un rowspan précédent
										$cellKey = $jour . '_' . $heureInt;
										if(isset($cellulesOccupees[$cellKey])) {
											// Ne pas afficher de td, elle est couverte par rowspan
											continue;
										}
										
										// Chercher les cours qui commencent à cette heure
										$coursIci = $coursData[$jour][$heureInt] ?? [];
										
										if(count($coursIci) > 0):
											// Prendre le rowspan maximum parmi tous les cours
											$maxDuree = 1;
											foreach($coursIci as $cours) {
												if($cours['duree'] > $maxDuree) {
													$maxDuree = $cours['duree'];
												}
											}
											$rowspan = min($maxDuree, 10 - $idx); // Ne pas dépasser la grille
											
											// Marquer les cellules suivantes comme occupées
											for($i = 1; $i < $rowspan; $i++) {
												$cellulesOccupees[$jour . '_' . ($heureInt + $i)] = true;
											}
										?>
										<td rowspan="<?=$rowspan?>" style="height: <?=$rowspan * 50?>px;" class="droppable-cell" data-jour="<?=$jour?>" data-heure="<?=$heureInt?>">
											<div class="courses-container">
											<?php foreach($coursIci as $cours): ?>
											<div class="course-card <?=$cours['type_seance']?>" 
												draggable="true"
												data-id="<?=$cours['id']?>"
												data-duree="<?=$cours['duree']?>"
												data-mention="<?=$cours['mention']?>" 
												data-niveau="<?=$cours['niveau']?>" 
												data-semester="<?=$cours['semester']?>"
												data-salle="<?=$cours['salle_id']?>"
												data-teacher="<?=$cours['id_teacher']?>"
												data-parcours="<?=$cours['parcours'] ?? ''?>"
												data-type="<?=$cours['type_seance']?>"
												style="height: <?=($cours['duree'] * 50) - 10?>px;">
												<div class="course-title"><?=$cours['cours_title']?></div>
												<div class="details"><i class="bi bi-geo-alt-fill"></i> <?=$cours['salle_code']?></div>
												<div class="details"><?=$cours['mention']?> L<?=$cours['niveau']?></div>
												<?php if(!empty($cours['parcours'])): ?>
												<div class="parcours-badge"><?=$cours['parcours']?></div>
												<?php endif; ?>
												<div class="details"><?=substr($cours['heure_debut'],0,5)?> - <?=substr($cours['heure_fin'],0,5)?></div>
											</div>
											<?php endforeach; ?>
											</div>
										</td>
										<?php else: ?>
										<td class="droppable-cell" data-jour="<?=$jour?>" data-heure="<?=$heureInt?>"></td>
										<?php endif; ?>
									<?php endforeach; ?>
								</tr>
								<?php endforeach; /* heures */ ?>
							</tbody>
						</table>

						<!-- Légende -->
						<div class="mt-3 text-xs text-slate-400">
							<span class="mr-4"><span class="legend-box" style="background:#0891b2;"></span>Cours</span>
							<span class="mr-4"><span class="legend-box" style="background:#7c3aed;"></span>TD</span>
							<span class="mr-4"><span class="legend-box" style="background:#059669;"></span>TP</span>
							<span><span class="legend-box" style="background:#d97706;"></span>Examen</span>
						</div>

						<!-- Liste des séances -->
						<h2 class="text-base font-semibold text-white mt-5 mb-2"><i class="bi bi-list-ul text-cyan-500"></i> Liste des séances</h2>
						<table class="simpleTbl">
							<thead class="<?=$bg_four_color?> text-white">
								<tr>
									<th>Sigle</th>
									<th>Titre</th>
									<th>Type</th>
									<th>Jour</th>
									<th>Horaire</th>
									<th>Salle</th>
									<th>Mention</th>
									<th>Niveau</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$allCours = $dtb->query("SELECT * FROM t_2024_emploi_du_temps ORDER BY FIELD(jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), heure_debut");
								while($c = $allCours->fetch()):
									$typeBg = match($c['type_seance']) {
										'cours' => 'bg-cyan-700',
										'td' => 'bg-purple-700',
										'tp' => 'bg-green-700',
										default => 'bg-slate-600'
									};
								?>
								<tr class="hover:bg-slate-600 course-row" data-mention="<?=$c['mention']?>" data-niveau="<?=$c['niveau']?>" data-semester="<?=$c['semester']?>" data-type="<?=$c['type_seance']?>">
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600 font-semibold"><?=$c['cours_sigle']?></td>
									<td><?=$c['cours_title']?></td>
									<td><span class="<?=$typeBg?> px-2 py-0.5 rounded text-xs"><?=strtoupper($c['type_seance'])?></span></td>
									<td><?=$c['jour_semaine']?></td>
									<td><?=substr($c['heure_debut'],0,5)?> - <?=substr($c['heure_fin'],0,5)?></td>
									<td><i class="bi bi-geo-alt"></i> <?=$c['salle_code']?></td>
									<td><?=$c['mention']?></td>
									<td>L<?=$c['niveau']?></td>
									<td class="text-center">
										<button onclick="deleteSeance(<?=$c['id']?>)" class="text-red-400 hover:text-red-300" title="Supprimer">
											<i class="bi bi-trash"></i>
										</button>
									</td>
								</tr>
								<?php endwhile; ?>
							</tbody>
						</table>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>

		</div>

	</div>

	<!-- Modal Ajouter une séance -->
	<div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
		<div class="bg-slate-800 rounded-lg w-full max-w-2xl mx-4 max-h-[90vh] overflow-auto">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 class="text-lg font-semibold text-white"><i class="bi bi-plus-circle text-cyan-500"></i> Ajouter une séance</h3>
				<button onclick="closeModal()" class="text-slate-400 hover:text-white"><i class="bi bi-x-lg"></i></button>
			</div>
			
			<form id="addSeanceForm" class="p-4">
				<div class="grid grid-cols-2 gap-4">
					<!-- Sélection du cours -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Cours *</label>
						<select id="coursSelect" name="cours_id" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Sélectionner un cours --</option>
						</select>
						<div class="flex gap-2 mt-2">
							<select id="filterCourseMention" class="bg-slate-600 text-white text-xs px-2 py-1 rounded">
								<option value="">Filtre mention</option>
								<?php
								$allMentions = $dtb->query("SELECT DISTINCT dep_desc FROM t_2023_cours WHERE dep_desc != '' AND remove != 1 ORDER BY dep_desc");
								while($m = $allMentions->fetch()) {
									echo '<option value="'.$m['dep_desc'].'">'.$m['dep_desc'].'</option>';
								}
								?>
							</select>
							<select id="filterCourseNiveau" class="bg-slate-600 text-white text-xs px-2 py-1 rounded">
								<option value="">Filtre niveau</option>
								<option value="1">L1</option>
								<option value="2">L2</option>
								<option value="3">L3</option>
								<option value="4">M1</option>
								<option value="5">M2</option>
							</select>
						</div>
					</div>
					
					<!-- Parcours concerné -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Parcours concerné</label>
						<select name="parcours" id="parcoursSelect" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Tous les parcours de la mention --</option>
						</select>
						<p class="text-slate-500 text-xs mt-1"><i class="bi bi-info-circle"></i> Laissez vide si le cours est pour tous les parcours</p>
					</div>
					
					<!-- Type de séance -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Type de séance *</label>
						<select name="type_seance" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="cours">Cours magistral</option>
							<option value="td">TD</option>
							<option value="tp">TP</option>
							<option value="examen">Examen</option>
						</select>
					</div>
					
					<!-- Jour -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Jour *</label>
						<select name="jour_semaine" id="jourSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Jour --</option>
							<option value="Lundi">Lundi</option>
							<option value="Mardi">Mardi</option>
							<option value="Mercredi">Mercredi</option>
							<option value="Jeudi">Jeudi</option>
							<option value="Vendredi">Vendredi</option>
						</select>
					</div>
					
					<!-- Heure début -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure début *</label>
						<select name="heure_debut" id="heureDebutSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Heure --</option>
							<option value="08:00">08:00</option>
							<option value="09:00">09:00</option>
							<option value="10:00">10:00</option>
							<option value="11:00">11:00</option>
							<option value="12:00">12:00</option>
							<option value="13:00">13:00</option>
							<option value="14:00">14:00</option>
							<option value="15:00">15:00</option>
							<option value="16:00">16:00</option>
							<option value="17:00">17:00</option>
						</select>
					</div>
					
					<!-- Heure fin -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure fin *</label>
						<select name="heure_fin" id="heureFinSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Heure --</option>
							<option value="09:00">09:00</option>
							<option value="10:00">10:00</option>
							<option value="11:00">11:00</option>
							<option value="12:00">12:00</option>
							<option value="13:00">13:00</option>
							<option value="14:00">14:00</option>
							<option value="15:00">15:00</option>
							<option value="16:00">16:00</option>
							<option value="17:00">17:00</option>
							<option value="18:00">18:00</option>
						</select>
					</div>
					
					<!-- Salle -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Salle *</label>
						<select name="salle_id" id="salleSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Sélectionner --</option>
						</select>
					</div>
					
					<!-- Enseignant -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Enseignant</label>
						<select name="id_teacher" id="teacherSelect" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Enseignant du cours --</option>
						</select>
					</div>
					
					<!-- Remarque -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Remarque</label>
						<input type="text" name="remarque" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600" placeholder="Optionnel...">
					</div>
				</div>
				
				<!-- Zone conflits -->
				<div id="conflictsZone" class="mt-4 hidden">
					<div class="bg-slate-900 rounded p-3">
						<h4 class="text-sm font-semibold text-white mb-2"><i class="bi bi-exclamation-triangle text-yellow-500"></i> Conflits détectés</h4>
						<div id="conflictsList"></div>
					</div>
				</div>
				
				<!-- Boutons -->
				<div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-700">
					<button type="button" onclick="checkConflicts()" class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-2 rounded">
						<i class="bi bi-search"></i> Vérifier conflits
					</button>
					<button type="button" onclick="closeModal()" class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-2 rounded">Annuler</button>
					<button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white text-sm px-4 py-2 rounded">
						<i class="bi bi-check-lg"></i> Ajouter
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal Exporter emploi du temps -->
	<div id="exportModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
		<div class="bg-slate-800 rounded-lg w-full max-w-md mx-4">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 class="text-lg font-semibold text-white"><i class="bi bi-download text-emerald-500"></i> Exporter l'emploi du temps</h3>
				<button onclick="closeExportModal()" class="text-slate-400 hover:text-white"><i class="bi bi-x-lg"></i></button>
			</div>
			
			<div class="p-4">
				<!-- Sélection de la mention -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Mention</label>
					<select id="exportMention" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Toutes les mentions --</option>
						<?php
						$mentionsExport = $dtb->query("SELECT DISTINCT mention FROM t_2024_emploi_du_temps WHERE mention != '' ORDER BY mention");
						while($me = $mentionsExport->fetch()) {
							echo '<option value="'.$me['mention'].'">'.$me['mention'].'</option>';
						}
						?>
					</select>
				</div>
				
				<!-- Sélection du type de séance -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Type de séance</label>
					<select id="exportType" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Tous les types --</option>
						<option value="cours">Cours magistraux</option>
						<option value="td">TD</option>
						<option value="tp">TP</option>
						<option value="examen">Examens</option>
					</select>
				</div>
				
				<!-- Sélection du niveau -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Niveau</label>
					<select id="exportNiveau" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Tous les niveaux --</option>
						<option value="1">L1</option>
						<option value="2">L2</option>
						<option value="3">L3</option>
						<option value="4">M1</option>
						<option value="5">M2</option>
					</select>
				</div>
				
				<!-- Boutons d'export -->
				<div class="flex gap-3 mt-6">
					<button onclick="exportScheduleToPDF()" class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2.5 rounded flex items-center justify-center gap-2">
						<i class="bi bi-file-earmark-pdf"></i> Exporter en PDF
					</button>
					<button onclick="exportScheduleToExcel()" class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2.5 rounded flex items-center justify-center gap-2">
						<i class="bi bi-file-earmark-excel"></i> Exporter en Excel
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal Modifier une séance -->
	<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
		<div class="bg-slate-800 rounded-lg w-full max-w-2xl mx-4 max-h-[90vh] overflow-auto">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 class="text-lg font-semibold text-white"><i class="bi bi-pencil-square text-cyan-500"></i> Modifier la séance</h3>
				<button onclick="closeEditModal()" class="text-slate-400 hover:text-white"><i class="bi bi-x-lg"></i></button>
			</div>
			
			<form id="editSeanceForm" class="p-4">
				<input type="hidden" id="editSeanceId" name="id">
				
				<!-- Affichage du cours (non modifiable) -->
				<div class="mb-4 bg-slate-900 p-3 rounded">
					<label class="block text-slate-400 text-xs mb-1">Cours</label>
					<div id="editCoursInfo" class="text-white font-semibold"></div>
				</div>
				
				<!-- Parcours concerné -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Parcours concerné</label>
					<select name="parcours" id="editParcours" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Tous les parcours de la mention --</option>
					</select>
				</div>
				
				<div class="grid grid-cols-2 gap-4">
					<!-- Type de séance -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Type de séance *</label>
						<select name="type_seance" id="editTypeSeance" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="cours">Cours magistral</option>
							<option value="td">TD</option>
							<option value="tp">TP</option>
							<option value="examen">Examen</option>
						</select>
					</div>
					
					<!-- Jour -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Jour *</label>
						<select name="jour_semaine" id="editJour" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="Lundi">Lundi</option>
							<option value="Mardi">Mardi</option>
							<option value="Mercredi">Mercredi</option>
							<option value="Jeudi">Jeudi</option>
							<option value="Vendredi">Vendredi</option>
						</select>
					</div>
					
					<!-- Heure début -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure début *</label>
						<select name="heure_debut" id="editHeureDebut" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="08:00">08:00</option>
							<option value="09:00">09:00</option>
							<option value="10:00">10:00</option>
							<option value="11:00">11:00</option>
							<option value="12:00">12:00</option>
							<option value="13:00">13:00</option>
							<option value="14:00">14:00</option>
							<option value="15:00">15:00</option>
							<option value="16:00">16:00</option>
							<option value="17:00">17:00</option>
						</select>
					</div>
					
					<!-- Heure fin -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure fin *</label>
						<select name="heure_fin" id="editHeureFin" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="09:00">09:00</option>
							<option value="10:00">10:00</option>
							<option value="11:00">11:00</option>
							<option value="12:00">12:00</option>
							<option value="13:00">13:00</option>
							<option value="14:00">14:00</option>
							<option value="15:00">15:00</option>
							<option value="16:00">16:00</option>
							<option value="17:00">17:00</option>
							<option value="18:00">18:00</option>
						</select>
					</div>
					
					<!-- Salle -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Salle *</label>
						<select name="salle_id" id="editSalle" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Sélectionner --</option>
						</select>
					</div>
					
					<!-- Enseignant -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Enseignant</label>
						<select name="id_teacher" id="editTeacher" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Aucun --</option>
						</select>
					</div>
					
					<!-- Remarque -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Remarque</label>
						<input type="text" name="remarque" id="editRemarque" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600" placeholder="Optionnel...">
					</div>
				</div>
				
				<!-- Zone conflits -->
				<div id="editConflictsZone" class="mt-4 hidden">
					<div class="bg-slate-900 rounded p-3">
						<h4 class="text-sm font-semibold text-white mb-2"><i class="bi bi-exclamation-triangle text-yellow-500"></i> Conflits détectés</h4>
						<div id="editConflictsList"></div>
					</div>
				</div>
				
				<!-- Boutons -->
				<div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-700">
					<button type="button" onclick="closeEditModal()" class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-2 rounded">Annuler</button>
					<button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white text-sm px-4 py-2 rounded">
						<i class="bi bi-check-lg"></i> Enregistrer
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Données pour export -->
	<?php
	// Récupérer toutes les séances pour l'export
	$allSeancesExport = $dtb->query("SELECT * FROM t_2024_emploi_du_temps ORDER BY FIELD(jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), heure_debut");
	$seancesData = [];
	while($s = $allSeancesExport->fetch()) {
		$seancesData[] = [
			'id' => $s['id'],
			'cours_sigle' => $s['cours_sigle'],
			'cours_title' => $s['cours_title'],
			'type_seance' => $s['type_seance'],
			'jour_semaine' => $s['jour_semaine'],
			'heure_debut' => $s['heure_debut'],
			'heure_fin' => $s['heure_fin'],
			'salle_code' => $s['salle_code'],
			'mention' => $s['mention'],
			'niveau' => $s['niveau'],
			'semester' => $s['semester'],
			'parcours' => $s['parcours'] ?? ''
		];
	}
	?>
	<!-- jsPDF et SheetJS pour export -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
	<script>
	// Données pour export
	const allSeancesData = <?=json_encode($seancesData)?>;
	const joursOrdre = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
	
	// ==================== EXPORT MODAL ====================
	function openExportModal() {
		document.getElementById('exportModal').classList.remove('hidden');
		document.getElementById('exportModal').classList.add('flex');
		// Pré-remplir avec les filtres actuels
		document.getElementById('exportMention').value = document.getElementById('filterMention').value;
		document.getElementById('exportType').value = document.getElementById('filterTypeSeance').value;
		document.getElementById('exportNiveau').value = document.getElementById('filterNiveau').value;
	}
	
	function closeExportModal() {
		document.getElementById('exportModal').classList.add('hidden');
		document.getElementById('exportModal').classList.remove('flex');
	}
	
	// Filtrer les séances selon les critères
	function getFilteredSeances() {
		const mention = document.getElementById('exportMention').value;
		const type = document.getElementById('exportType').value;
		const niveau = document.getElementById('exportNiveau').value;
		
		return allSeancesData.filter(s => {
			if(mention && s.mention !== mention) return false;
			if(type && s.type_seance !== type) return false;
			if(niveau && s.niveau != niveau) return false;
			return true;
		});
	}
	
	// Générer le titre du fichier
	function getExportTitle() {
		const mention = document.getElementById('exportMention').value;
		const type = document.getElementById('exportType').value;
		const niveau = document.getElementById('exportNiveau').value;
		
		let title = 'Emploi du temps';
		if(mention) title += ' - ' + mention;
		if(type) title += ' - ' + type.toUpperCase();
		if(niveau) title += ' - L' + niveau;
		
		return title;
	}
	
	// ==================== EXPORT PDF ====================
	function exportScheduleToPDF() {
		// Vérifier que jsPDF est chargé
		if(typeof window.jspdf === 'undefined') {
			showNotification('Erreur: jsPDF non chargé. Rechargez la page.', 'error');
			return;
		}
		
		const jsPDF = window.jspdf.jsPDF;
		const seances = getFilteredSeances();
		
		if(seances.length === 0) {
			showNotification('Aucune séance à exporter', 'error');
			return;
		}
		
		try {
			// Trier par jour et heure
			seances.sort((a, b) => {
				const jourA = joursOrdre.indexOf(a.jour_semaine);
				const jourB = joursOrdre.indexOf(b.jour_semaine);
				if(jourA !== jourB) return jourA - jourB;
				return a.heure_debut.localeCompare(b.heure_debut);
			});
			
			const doc = new jsPDF('landscape', 'mm', 'a4');
			const pageWidth = doc.internal.pageSize.getWidth();
			const pageHeight = doc.internal.pageSize.getHeight();
			
			// En-tête avec fond bleu
			doc.setFillColor(30, 64, 175);
			doc.rect(0, 0, pageWidth, 30, 'F');
			
			doc.setFontSize(16);
			doc.setTextColor(255, 255, 255);
			doc.setFont(undefined, 'bold');
			doc.text(getExportTitle(), 14, 12);
			
			doc.setFontSize(10);
			doc.setFont(undefined, 'normal');
			doc.text('Universite Adventiste de Zurcher - Annee scolaire 2025-2026', 14, 20);
			doc.text('Genere le ' + new Date().toLocaleDateString('fr-FR'), 14, 26);
			
			doc.setTextColor(200, 200, 200);
			doc.text('Total: ' + seances.length + ' seance(s)', pageWidth - 60, 20);
			
			let yPos = 38;
			
			// Créer une grille par jour
			const seancesByJour = {};
			seances.forEach(s => {
				if(!seancesByJour[s.jour_semaine]) seancesByJour[s.jour_semaine] = [];
				seancesByJour[s.jour_semaine].push(s);
			});
			
			// Couleurs par type
			const typeColors = {
				'cours': [8, 145, 178],
				'td': [124, 58, 237],
				'tp': [5, 150, 105],
				'examen': [217, 119, 6]
			};
			
			// Colonnes du tableau
			const cols = [
				{ title: 'Horaire', width: 30, x: 14 },
				{ title: 'Sigle', width: 25, x: 44 },
				{ title: 'Titre du cours', width: 80, x: 69 },
				{ title: 'Type', width: 20, x: 149 },
				{ title: 'Salle', width: 25, x: 169 },
				{ title: 'Niveau', width: 25, x: 194 },
				{ title: 'Parcours', width: 30, x: 219 }
			];
			const rowHeight = 8;
			const headerHeight = 10;
			
			// Fonction pour dessiner l'en-tête du tableau
			function drawTableHeader(y) {
				doc.setFillColor(51, 65, 85);
				doc.rect(14, y, pageWidth - 28, headerHeight, 'F');
				doc.setFontSize(8);
				doc.setTextColor(255, 255, 255);
				doc.setFont(undefined, 'bold');
				cols.forEach(col => {
					doc.text(col.title, col.x + 2, y + 7);
				});
				return y + headerHeight;
			}
			
			// Fonction pour dessiner une ligne de données
			function drawTableRow(y, data, typeSeance) {
				// Fond alterné
				const rowIndex = Math.floor((y - 38) / rowHeight);
				if(rowIndex % 2 === 0) {
					doc.setFillColor(241, 245, 249);
					doc.rect(14, y, pageWidth - 28, rowHeight, 'F');
				}
				
				doc.setFontSize(8);
				doc.setTextColor(30, 41, 59);
				doc.setFont(undefined, 'normal');
				
				// Dessiner chaque cellule
				cols.forEach((col, idx) => {
					let text = data[idx] || '';
					// Tronquer si trop long
					const maxChars = Math.floor(col.width / 2);
					if(text.length > maxChars) {
						text = text.substring(0, maxChars - 2) + '..';
					}
					
					// Colorer la cellule Type
					if(idx === 3 && typeColors[typeSeance]) {
						doc.setFillColor(...typeColors[typeSeance]);
						doc.rect(col.x, y, col.width, rowHeight, 'F');
						doc.setTextColor(255, 255, 255);
						doc.setFont(undefined, 'bold');
					}
					
					doc.text(text, col.x + 2, y + 6);
					
					// Reset couleur
					doc.setTextColor(30, 41, 59);
					doc.setFont(undefined, 'normal');
				});
				
				return y + rowHeight;
			}
			
			// Pour chaque jour
			joursOrdre.forEach(jour => {
				const seancesDuJour = seancesByJour[jour];
				if(!seancesDuJour || seancesDuJour.length === 0) return;
				
				// Calculer l'espace nécessaire
				const espaceBesoin = headerHeight + (seancesDuJour.length * rowHeight) + 20;
				
				// Nouvelle page si nécessaire
				if(yPos + espaceBesoin > pageHeight - 20) {
					doc.addPage();
					yPos = 15;
				}
				
				// Titre du jour
				doc.setFontSize(11);
				doc.setTextColor(30, 64, 175);
				doc.setFont(undefined, 'bold');
				doc.text(jour, 14, yPos);
				yPos += 5;
				
				// En-tête du tableau
				yPos = drawTableHeader(yPos);
				
				// Lignes de données
				seancesDuJour.forEach(s => {
					const rowData = [
						s.heure_debut.substring(0, 5) + ' - ' + s.heure_fin.substring(0, 5),
						s.cours_sigle,
						s.cours_title,
						s.type_seance.toUpperCase(),
						s.salle_code,
						s.mention + ' L' + s.niveau,
						s.parcours || '-'
					];
					yPos = drawTableRow(yPos, rowData, s.type_seance);
				});
				
				// Bordure du tableau
				doc.setDrawColor(200, 200, 200);
				doc.rect(14, yPos - (seancesDuJour.length * rowHeight) - headerHeight, pageWidth - 28, (seancesDuJour.length * rowHeight) + headerHeight);
				
				yPos += 12;
			});
			
			// Pied de page
			const pageCount = doc.internal.getNumberOfPages();
			for(let i = 1; i <= pageCount; i++) {
				doc.setPage(i);
				doc.setFontSize(8);
				doc.setTextColor(128, 128, 128);
				doc.text('Page ' + i + '/' + pageCount, pageWidth / 2, pageHeight - 10, { align: 'center' });
			}
			
			// Nom du fichier
			let filename = 'emploi_du_temps';
			const mentionVal = document.getElementById('exportMention').value;
			const typeVal = document.getElementById('exportType').value;
			const niveauVal = document.getElementById('exportNiveau').value;
			if(mentionVal) filename += '_' + mentionVal;
			if(typeVal) filename += '_' + typeVal;
			if(niveauVal) filename += '_L' + niveauVal;
			filename += '.pdf';
			
			doc.save(filename);
			showNotification('PDF exporte avec succes!', 'success');
			closeExportModal();
		
		} catch(error) {
			console.error('Erreur export PDF:', error);
			showNotification('Erreur lors de l\'export PDF: ' + error.message, 'error');
		}
	}
	
	// ==================== EXPORT EXCEL ====================
	function exportScheduleToExcel() {
		const seances = getFilteredSeances();
		
		if(seances.length === 0) {
			showNotification('Aucune séance à exporter', 'error');
			return;
		}
		
		// Trier par jour et heure
		seances.sort((a, b) => {
			const jourA = joursOrdre.indexOf(a.jour_semaine);
			const jourB = joursOrdre.indexOf(b.jour_semaine);
			if(jourA !== jourB) return jourA - jourB;
			return a.heure_debut.localeCompare(b.heure_debut);
		});
		
		// Créer le workbook
		const wb = XLSX.utils.book_new();
		
		// Feuille 1: Liste complète
		const wsData = [
			['EMPLOI DU TEMPS - ' + getExportTitle().toUpperCase()],
			['Université Adventiste de Zurcher - Année scolaire 2025-2026'],
			['Généré le ' + new Date().toLocaleDateString('fr-FR')],
			[''],
			['Jour', 'Heure début', 'Heure fin', 'Sigle', 'Titre du cours', 'Type', 'Salle', 'Mention', 'Niveau', 'Parcours']
		];
		
		seances.forEach(s => {
			wsData.push([
				s.jour_semaine,
				s.heure_debut.substring(0, 5),
				s.heure_fin.substring(0, 5),
				s.cours_sigle,
				s.cours_title,
				s.type_seance.toUpperCase(),
				s.salle_code,
				s.mention,
				'L' + s.niveau,
				s.parcours || ''
			]);
		});
		
		const ws = XLSX.utils.aoa_to_sheet(wsData);
		
		// Largeur des colonnes
		ws['!cols'] = [
			{ wch: 12 },  // Jour
			{ wch: 10 },  // Heure début
			{ wch: 10 },  // Heure fin
			{ wch: 12 },  // Sigle
			{ wch: 40 },  // Titre
			{ wch: 10 },  // Type
			{ wch: 12 },  // Salle
			{ wch: 10 },  // Mention
			{ wch: 8 },   // Niveau
			{ wch: 15 }   // Parcours
		];
		
		// Fusion pour le titre
		ws['!merges'] = [
			{ s: { r: 0, c: 0 }, e: { r: 0, c: 9 } },
			{ s: { r: 1, c: 0 }, e: { r: 1, c: 9 } },
			{ s: { r: 2, c: 0 }, e: { r: 2, c: 9 } }
		];
		
		XLSX.utils.book_append_sheet(wb, ws, 'Emploi du temps');
		
		// Feuille 2: Grille hebdomadaire
		const heures = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
		const grilleData = [
			['GRILLE HEBDOMADAIRE'],
			[''],
			['Heure', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi']
		];
		
		heures.forEach(heure => {
			const heureInt = parseInt(heure.substring(0, 2));
			const row = [heure];
			
			['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'].forEach(jour => {
				// Trouver les séances qui couvrent cette heure
				const seancesCeCreneaux = seances.filter(s => {
					const debut = parseInt(s.heure_debut.substring(0, 2));
					const fin = parseInt(s.heure_fin.substring(0, 2));
					return s.jour_semaine === jour && heureInt >= debut && heureInt < fin;
				});
				
				if(seancesCeCreneaux.length > 0) {
					const textes = seancesCeCreneaux.map(s => 
						`${s.cours_sigle} (${s.type_seance.toUpperCase()}) - ${s.salle_code}`
					);
					row.push(textes.join('\n'));
				} else {
					row.push('');
				}
			});
			
			grilleData.push(row);
		});
		
		const wsGrille = XLSX.utils.aoa_to_sheet(grilleData);
		wsGrille['!cols'] = [
			{ wch: 8 },
			{ wch: 30 },
			{ wch: 30 },
			{ wch: 30 },
			{ wch: 30 },
			{ wch: 30 }
		];
		wsGrille['!merges'] = [
			{ s: { r: 0, c: 0 }, e: { r: 0, c: 5 } }
		];
		
		XLSX.utils.book_append_sheet(wb, wsGrille, 'Grille hebdomadaire');
		
		// Feuille 3: Par jour (une feuille par jour)
		joursOrdre.slice(0, 5).forEach(jour => {
			const seancesDuJour = seances.filter(s => s.jour_semaine === jour);
			if(seancesDuJour.length === 0) return;
			
			const jourData = [
				[jour.toUpperCase()],
				[''],
				['Horaire', 'Sigle', 'Titre', 'Type', 'Salle', 'Mention/Niveau', 'Parcours']
			];
			
			seancesDuJour.forEach(s => {
				jourData.push([
					s.heure_debut.substring(0, 5) + ' - ' + s.heure_fin.substring(0, 5),
					s.cours_sigle,
					s.cours_title,
					s.type_seance.toUpperCase(),
					s.salle_code,
					s.mention + ' L' + s.niveau,
					s.parcours || ''
				]);
			});
			
			const wsJour = XLSX.utils.aoa_to_sheet(jourData);
			wsJour['!cols'] = [
				{ wch: 15 },
				{ wch: 12 },
				{ wch: 40 },
				{ wch: 10 },
				{ wch: 12 },
				{ wch: 15 },
				{ wch: 15 }
			];
			wsJour['!merges'] = [
				{ s: { r: 0, c: 0 }, e: { r: 0, c: 6 } }
			];
			
			XLSX.utils.book_append_sheet(wb, wsJour, jour);
		});
		
		// Nom du fichier
		let filename = 'emploi_du_temps';
		const mention = document.getElementById('exportMention').value;
		const type = document.getElementById('exportType').value;
		const niveau = document.getElementById('exportNiveau').value;
		if(mention) filename += '_' + mention;
		if(type) filename += '_' + type;
		if(niveau) filename += '_L' + niveau;
		filename += '.xlsx';
		
		XLSX.writeFile(wb, filename);
		showNotification('Excel exporté avec succès!', 'success');
		closeExportModal();
	}
	</script>

	<script>
	// ==================== DRAG AND DROP ====================
	let draggedElement = null;
	let draggedData = null;
	
	// Initialiser le drag and drop
	document.querySelectorAll('.course-card[draggable="true"]').forEach(card => {
		card.addEventListener('dragstart', handleDragStart);
		card.addEventListener('dragend', handleDragEnd);
		card.addEventListener('dblclick', handleDoubleClick);
	});
	
	// Double-clic pour modifier
	async function handleDoubleClick(e) {
		e.preventDefault();
		const id = this.dataset.id;
		if(id) {
			openEditModal(id);
		}
	}
	
	document.querySelectorAll('.droppable-cell').forEach(cell => {
		cell.addEventListener('dragover', handleDragOver);
		cell.addEventListener('dragleave', handleDragLeave);
		cell.addEventListener('drop', handleDrop);
	});
	
	function handleDragStart(e) {
		draggedElement = this;
		draggedData = {
			id: this.dataset.id,
			duree: parseInt(this.dataset.duree) || 2,
			mention: this.dataset.mention,
			niveau: this.dataset.niveau,
			salle: this.dataset.salle,
			teacher: this.dataset.teacher
		};
		this.classList.add('dragging');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', this.dataset.id);
	}
	
	function handleDragEnd(e) {
		this.classList.remove('dragging');
		document.querySelectorAll('.drop-target, .drop-invalid').forEach(el => {
			el.classList.remove('drop-target', 'drop-invalid');
		});
		draggedElement = null;
		draggedData = null;
	}
	
	function handleDragOver(e) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		
		// Ne pas permettre le drop sur une cellule occupée (sauf si c'est la même)
		const hasOtherCourse = this.querySelector('.course-card') && 
			!this.contains(draggedElement);
		
		if(hasOtherCourse) {
			this.classList.add('drop-invalid');
			this.classList.remove('drop-target');
		} else {
			this.classList.add('drop-target');
			this.classList.remove('drop-invalid');
		}
	}
	
	function handleDragLeave(e) {
		this.classList.remove('drop-target', 'drop-invalid');
	}
	
	async function handleDrop(e) {
		e.preventDefault();
		this.classList.remove('drop-target', 'drop-invalid');
		
		if(!draggedData) return;
		
		const newJour = this.dataset.jour;
		const newHeure = this.dataset.heure;
		
		// Ne pas permettre le drop sur une cellule occupée par un autre cours
		const existingCard = this.querySelector('.course-card');
		if(existingCard && existingCard.dataset.id !== draggedData.id) {
			showNotification('Cette cellule est déjà occupée', 'error');
			return;
		}
		
		// Construire les nouvelles heures
		const heureDebut = String(newHeure).padStart(2, '0') + ':00';
		
		// Appeler l'API pour déplacer
		const formData = new FormData();
		formData.append('action', 'move_seance');
		formData.append('id', draggedData.id);
		formData.append('jour_semaine', newJour);
		formData.append('heure_debut', heureDebut);
		formData.append('duree', draggedData.duree);
		
		try {
			const response = await fetch('./emploi-temps.api.php', {
				method: 'POST',
				body: formData
			});
			const result = await response.json();
			
			if(result.success) {
				showNotification('Séance déplacée avec succès!', 'success');
				// Recharger la page pour voir les changements
				setTimeout(() => location.reload(), 500);
			} else if(result.conflicts) {
				let msg = 'Conflits: ' + result.conflicts.map(c => c.message).join(', ');
				showNotification(msg, 'error');
			} else {
				showNotification(result.error || 'Erreur lors du déplacement', 'error');
			}
		} catch(err) {
			showNotification('Erreur de connexion', 'error');
		}
	}
	
	// Notification toast
	function showNotification(message, type = 'info') {
		const colors = {
			success: 'bg-green-600',
			error: 'bg-red-600',
			info: 'bg-blue-600'
		};
		
		const toast = document.createElement('div');
		toast.className = `fixed bottom-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 text-sm`;
		toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'}"></i> ${message}`;
		document.body.appendChild(toast);
		
		setTimeout(() => toast.remove(), 4000);
	}
	
	// ==================== FILTRES ====================
	// Filtres tableau
	document.querySelectorAll('#filterMention, #filterNiveau, #filterSemester, #filterTypeSeance').forEach(el => {
		el.addEventListener('change', applyFilters);
	});
	
	function applyFilters() {
		const mention = document.getElementById('filterMention').value;
		const niveau = document.getElementById('filterNiveau').value;
		const semester = document.getElementById('filterSemester').value;
		const typeSeance = document.getElementById('filterTypeSeance').value;
		
		document.querySelectorAll('.course-card').forEach(card => {
			let show = true;
			if(mention && card.dataset.mention !== mention) show = false;
			if(niveau && card.dataset.niveau !== niveau) show = false;
			if(semester && card.dataset.semester !== semester) show = false;
			if(typeSeance && card.dataset.type !== typeSeance) show = false;
			card.style.display = show ? 'flex' : 'none';
		});
		
		document.querySelectorAll('.course-row').forEach(row => {
			let show = true;
			if(mention && row.dataset.mention !== mention) show = false;
			if(niveau && row.dataset.niveau !== niveau) show = false;
			if(semester && row.dataset.semester !== semester) show = false;
			if(typeSeance && row.dataset.type !== typeSeance) show = false;
			row.style.display = show ? '' : 'none';
		});
	}
	
	// Modal
	function openAddModal() {
		document.getElementById('addModal').classList.remove('hidden');
		document.getElementById('addModal').classList.add('flex');
		loadCours();
		loadSalles();
		loadTeachers();
	}
	
	function closeModal() {
		document.getElementById('addModal').classList.add('hidden');
		document.getElementById('addModal').classList.remove('flex');
		document.getElementById('addSeanceForm').reset();
		document.getElementById('conflictsZone').classList.add('hidden');
	}
	
	// ==================== MODAL EDITION ====================
	async function openEditModal(id) {
		const modal = document.getElementById('editModal');
		modal.classList.remove('hidden');
		modal.classList.add('flex');
		
		// Charger les salles et enseignants
		await loadEditSalles();
		await loadEditTeachers();
		
		// Charger les données de la séance
		try {
			const response = await fetch(`./emploi-temps.api.php?action=get_seance&id=${id}`);
			const seance = await response.json();
			
			if(seance) {
				document.getElementById('editSeanceId').value = seance.id;
				document.getElementById('editCoursInfo').innerHTML = `<span class="text-cyan-400">${seance.cours_sigle}</span> - ${seance.cours_title} <span class="text-slate-400">(${seance.mention} L${seance.niveau})</span>`;
				document.getElementById('editTypeSeance').value = seance.type_seance;
				document.getElementById('editJour').value = seance.jour_semaine;
				document.getElementById('editHeureDebut').value = seance.heure_debut;
				document.getElementById('editHeureFin').value = seance.heure_fin;
				document.getElementById('editSalle').value = seance.salle_id;
				document.getElementById('editTeacher').value = seance.id_teacher || '';
				document.getElementById('editRemarque').value = seance.remarque || '';
				
				// Charger les parcours de la mention et sélectionner celui de la séance
				await loadParcours(seance.mention, 'editParcours');
				document.getElementById('editParcours').value = seance.parcours || '';
			}
		} catch(err) {
			showNotification('Erreur lors du chargement', 'error');
		}
	}
	
	function closeEditModal() {
		document.getElementById('editModal').classList.add('hidden');
		document.getElementById('editModal').classList.remove('flex');
		document.getElementById('editSeanceForm').reset();
		document.getElementById('editConflictsZone').classList.add('hidden');
	}
	
	async function loadEditSalles() {
		const response = await fetch('./emploi-temps.api.php?action=get_salles');
		const salles = await response.json();
		
		const select = document.getElementById('editSalle');
		select.innerHTML = '<option value="">-- Sélectionner --</option>';
		salles.forEach(s => {
			select.innerHTML += `<option value="${s.id}">${s.salle_code}</option>`;
		});
	}
	
	async function loadEditTeachers() {
		const response = await fetch('./emploi-temps.api.php?action=get_teachers');
		const teachers = await response.json();
		
		const select = document.getElementById('editTeacher');
		select.innerHTML = '<option value="">-- Aucun --</option>';
		teachers.forEach(t => {
			select.innerHTML += `<option value="${t.uid}">${t.name} ${t.lastName}</option>`;
		});
	}
	
	// Auto-remplir heure fin dans le modal edit
	document.getElementById('editHeureDebut').addEventListener('change', function() {
		const heure = parseInt(this.value.substring(0, 2));
		if(!isNaN(heure)) {
			const currentFin = parseInt(document.getElementById('editHeureFin').value.substring(0, 2));
			const duree = currentFin - parseInt(document.getElementById('editHeureDebut').defaultValue.substring(0, 2)) || 1;
			const heureFin = String(Math.min(heure + duree, 18)).padStart(2, '0') + ':00';
			document.getElementById('editHeureFin').value = heureFin;
		}
	});
	
	// Soumettre le formulaire d'édition
	document.getElementById('editSeanceForm').addEventListener('submit', async function(e) {
		e.preventDefault();
		
		const formData = new FormData(this);
		formData.append('action', 'update_seance');
		
		try {
			const response = await fetch('./emploi-temps.api.php', {
				method: 'POST',
				body: formData
			});
			const result = await response.json();
			
			if(result.success) {
				showNotification('Séance modifiée avec succès!', 'success');
				closeEditModal();
				setTimeout(() => location.reload(), 500);
			} else if(result.conflicts) {
				const zone = document.getElementById('editConflictsZone');
				const list = document.getElementById('editConflictsList');
				zone.classList.remove('hidden');
				list.innerHTML = result.conflicts.map(c => {
					return `<div class="text-red-400 text-sm mb-1"><i class="bi bi-x-circle"></i> ${c.message}</div>`;
				}).join('');
			} else {
				showNotification(result.error || 'Erreur lors de la modification', 'error');
			}
		} catch(err) {
			showNotification('Erreur de connexion', 'error');
		}
	});
	
	// ==================== MODAL AJOUT ====================
	// Charger les cours
	async function loadCours() {
		const mention = document.getElementById('filterCourseMention').value;
		const niveau = document.getElementById('filterCourseNiveau').value;
		
		const params = new URLSearchParams({action: 'get_cours'});
		if(mention) params.append('mention', mention);
		if(niveau) params.append('niveau', niveau);
		
		const response = await fetch('./emploi-temps.api.php?' + params);
		const cours = await response.json();
		
		const select = document.getElementById('coursSelect');
		select.innerHTML = '<option value="">-- Sélectionner un cours --</option>';
		cours.forEach(c => {
			select.innerHTML += `<option value="${c.id}">${c.Sigle} - ${c.title} (${c.dep_desc} L${c.yearlevel})</option>`;
		});
	}
	
	// Filtres cours
	document.getElementById('filterCourseMention').addEventListener('change', loadCours);
	document.getElementById('filterCourseNiveau').addEventListener('change', loadCours);
	
	// Quand on sélectionne un cours, charger les parcours de sa mention
	document.getElementById('coursSelect').addEventListener('change', async function() {
		const coursId = this.value;
		if(!coursId) {
			document.getElementById('parcoursSelect').innerHTML = '<option value="">-- Tous les parcours de la mention --</option>';
			return;
		}
		
		// Récupérer la mention du cours sélectionné
		const response = await fetch('./emploi-temps.api.php?action=get_cours');
		const cours = await response.json();
		const selectedCours = cours.find(c => c.id == coursId);
		
		if(selectedCours) {
			await loadParcours(selectedCours.dep_desc, 'parcoursSelect');
		}
	});
	
	// Charger les parcours d'une mention
	async function loadParcours(mention, selectId) {
		const response = await fetch(`./emploi-temps.api.php?action=get_parcours&mention=${mention}`);
		const parcours = await response.json();
		
		const select = document.getElementById(selectId);
		select.innerHTML = '<option value="">-- Tous les parcours de la mention --</option>';
		parcours.forEach(p => {
			select.innerHTML += `<option value="${p.nom}">${p.nom}</option>`;
		});
	}
	
	// Charger les salles
	async function loadSalles() {
		const response = await fetch('./emploi-temps.api.php?action=get_salles');
		const salles = await response.json();
		
		const select = document.getElementById('salleSelect');
		select.innerHTML = '<option value="">-- Sélectionner --</option>';
		salles.forEach(s => {
			select.innerHTML += `<option value="${s.id}">${s.salle_code}</option>`;
		});
	}
	
	// Charger les enseignants
	async function loadTeachers() {
		const response = await fetch('./emploi-temps.api.php?action=get_teachers');
		const teachers = await response.json();
		
		const select = document.getElementById('teacherSelect');
		select.innerHTML = '<option value="">-- Enseignant du cours --</option>';
		teachers.forEach(t => {
			select.innerHTML += `<option value="${t.uid}">${t.name} ${t.lastName}</option>`;
		});
	}
	
	// Auto-remplir heure fin (+1h par défaut)
	document.getElementById('heureDebutSelect').addEventListener('change', function() {
		const heure = parseInt(this.value.substring(0, 2));
		if(!isNaN(heure)) {
			const heureFin = String(heure + 1).padStart(2, '0') + ':00';
			document.getElementById('heureFinSelect').value = heureFin;
		}
	});
	
	// Vérifier les conflits
	async function checkConflicts() {
		const form = document.getElementById('addSeanceForm');
		const formData = new FormData(form);
		formData.append('action', 'check_conflicts');
		
		// Récupérer les infos du cours pour mention/niveau
		const coursId = formData.get('cours_id');
		if(!coursId) {
			alert('Veuillez sélectionner un cours');
			return;
		}
		
		const coursResponse = await fetch(`./emploi-temps.api.php?action=get_cours`);
		const allCours = await coursResponse.json();
		const cours = allCours.find(c => c.id == coursId);
		
		if(cours) {
			formData.append('mention', cours.dep_desc);
			formData.append('niveau', cours.yearlevel);
			formData.append('parcours', cours.parcours || '');
		}
		
		const response = await fetch('./emploi-temps.api.php', {
			method: 'POST',
			body: formData
		});
		const result = await response.json();
		
		const zone = document.getElementById('conflictsZone');
		const list = document.getElementById('conflictsList');
		
		if(result.conflicts && result.conflicts.length > 0) {
			zone.classList.remove('hidden');
			list.innerHTML = result.conflicts.map(c => {
				const color = c.severity === 'error' ? 'text-red-400' : 'text-yellow-400';
				const icon = c.severity === 'error' ? 'bi-x-circle' : 'bi-exclamation-circle';
				return `<div class="${color} text-sm mb-1"><i class="bi ${icon}"></i> ${c.message}</div>`;
			}).join('');
		} else {
			zone.classList.remove('hidden');
			list.innerHTML = '<div class="text-green-400 text-sm"><i class="bi bi-check-circle"></i> Aucun conflit détecté</div>';
		}
	}
	
	// Soumettre le formulaire
	document.getElementById('addSeanceForm').addEventListener('submit', async function(e) {
		e.preventDefault();
		
		const formData = new FormData(this);
		formData.append('action', 'add_seance');
		
		const response = await fetch('./emploi-temps.api.php', {
			method: 'POST',
			body: formData
		});
		const result = await response.json();
		
		if(result.success) {
			alert('Séance ajoutée avec succès!');
			closeModal();
			location.reload();
		} else if(result.conflicts) {
			// Afficher les conflits
			const zone = document.getElementById('conflictsZone');
			const list = document.getElementById('conflictsList');
			zone.classList.remove('hidden');
			list.innerHTML = result.conflicts.map(c => {
				const color = c.severity === 'error' ? 'text-red-400' : 'text-yellow-400';
				return `<div class="${color} text-sm mb-1"><i class="bi bi-x-circle"></i> ${c.message}</div>`;
			}).join('');
		} else {
			alert('Erreur: ' + (result.error || 'Erreur inconnue'));
		}
	});
	
	// Supprimer une séance
	async function deleteSeance(id) {
		if(!confirm('Êtes-vous sûr de vouloir supprimer cette séance?')) return;
		
		const formData = new FormData();
		formData.append('action', 'delete_seance');
		formData.append('id', id);
		
		const response = await fetch('./emploi-temps.api.php', {
			method: 'POST',
			body: formData
		});
		const result = await response.json();
		
		if(result.success) {
			location.reload();
		} else {
			alert('Erreur lors de la suppression');
		}
	}
	</script>
</body>
</html>
