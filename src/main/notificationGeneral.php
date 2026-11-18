<!-- ////////////////////// GENERAL TOOLBAR //////////////////////////////////////////// -->

<!-- FOR LIST STUDENT -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListStd" style="backdrop-filter: blur(3px);">
		
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl text-xs">
			<div class="p-2 text-black">
				<b>Exporter la liste d'étudiant.</b>
			</div>
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=listeStd" target="_blank">
			<div class="p-2">
					<b class="toolInactive">Types d'exoprtation.</b>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
													      			<label for="pdf_export_type">Types</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="pdf_export_type" name="exportation" class="input w-full">
				      				<option value="general">Liste d'étudiant générale</option>
				      				<option value="internat">Liste d'étudiant interne</option>
				      				<option value="abnment">Liste d'étudiant abonnée</option>
				      				<option value="adventiste">Liste d'étudiant Adventiste</option>
				      				<option value="non_adventiste">Liste d'étudiant Non-Adventiste</option>

				      			</select>	
				      		</div>
				      	</div>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="pdf_new_student" type="checkbox" name="new_student">
				      			<label for="pdf_new_student"> Les nouveaux seulement.</label>
				      		</div>
				      	</div>
				      	<hr>

	      			<b class="toolInactive">Listes.</b>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="pdf_mention">Mention</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="pdf_mention" name="types" class="input w-full">
				      				<option value="TOUT">Tout</option>
			<?php 
						$voir = $dtb->query("SELECT * FROM filiere");
						while ($affiche = $voir->fetch()) {?>
											
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

			<?php	
				}
			 ?>	
				      			</select>	
				      		</div>
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="pdf_anneescolaire">Année</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="pdf_anneescolaire" name="anneescolaire" class="input w-full">
				      				 <?php
										$y = date('Y');
										for ($i=0; $i <= 3; $i++) { 
											
											if (date('m')>7) {
												$annee = $y." - ".($y+1);	
											}else{
												$annee = ($y-1)." - ".$y;
											}
											
										?>
										<option value="<?=$annee?>"><?=$annee?></option>
										<?php
										$y = $y - 1;
										}
									?>
				      			</select>	
				      		</div>
				      	</div>

				      	<hr>
				      	
				      	<b class="toolInactive">Cours.</b>
				    
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="pdf_cours" type="checkbox" name="cours">
				      			<label for="pdf_cours">Afficher la liste des cours à chaque étudiant.</label>	
				      		</div>	
				      	</div>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			
				      		</div>
				      		<div class="w-9/12">
				      			<input id="pdf_notes" type="checkbox" name="notes">
				      			<label for="pdf_notes">Y-compris les nôtes.</label>	
				      		</div>	
				      	</div>
				      	
				      	<hr>
				      	
				      	<b class="toolInactive">Niveau/Semestre.</b>
				      	
				      	<div class="flex mb-3">

				      		<div class="w-3/12 text-right pr-2">
				      			<label for="pdf_annee">Niveau : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="pdf_annee" name="annee_etude" class="input w-full">
				      				<option value="tout">Tout</option>
				      				<option value="1">Licence 1</option>
				      				<option value="2">Licence 2</option>
				      				<option value="3">Licence 3</option>
				      				<option value="4">Master 1</option>
				      				<option value="5">Master 2</option>
				      				<option value="10">Classe spéciale</option>
				      			</select>	
				      		</div>
				      	</div>
				      	
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2"></div>
				      		<div class="w-9/12">
				      			<select id="niveau_scope" name="niveau_scope" class="input w-full">
				      				<option value="all" selected>Tous niveaux (Licence + Master)</option>
				      				<option value="licence">Licence seulement (sans Master)</option>
				      			</select>
				      			<small class="text-slate-500">Appliqué uniquement si Niveau = Tout.</small>
				      		</div>
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="pdf_semestre">Semestre : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="pdf_semestre" name="semestre" class="input w-full">
				      				<option <?php 
if (date('m')>7) {
	echo "selected";	
}else{

}
						 ?> value="1">Premier semestre</option>
						<option value="3">Semestre d'été</option>
						<option <?php 
if (date('m')>7) {
	
}else{
	echo "selected";
}
						 ?> value="2">Deuxième semestre</option>
						<option value="4">Semestre d'hiver</option>
				      			</select>	
				      		</div>
				      	</div>
				      	<hr>

				      	<b class="toolInactive">Colonne.</b>
				      	
				      	<div class="flex mb-3">
				      		
				      		<div class="w-4/12 p-2">
				      			<input id="pdf_signature" type="checkbox" name="signature">
				      			<label for="pdf_signature">Colonne de signature.</label>	
				      		</div>
				      		<div class="w-4/12 p-2">
				      			<input id="pdf_remarque" type="checkbox" name="remarque">
				      			<label for="pdf_remarque">Colonne de remarque.</label>	
				      		</div>
				      		<div class="w-4/12 p-2">
				      			<input id="pdf_taille" type="checkbox" name="taille">
				      			<label for="pdf_taille">Colonne taille.</label>	
				      		</div>	

				      	</div>
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListStd" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnStudentList" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR PRESENCE SHEET -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifPresence" style="backdrop-filter: blur(3px);">
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl text-xs">
			<div class="p-2 text-black">
				<b>Exporter la fiche de présence.</b>
			</div>
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=listeStd" target="_blank">
				<input type="hidden" name="exportation" value="general">
				<input type="hidden" name="presence_sheet" value="1">
				<div class="p-2">
					<b class="toolInactive">Type de présence.</b>
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="presence_type">Type</label>
						</div>
						<div class="w-9/12">
							<select id="presence_type" name="presence_type" class="input w-full">
								<option value="classe" selected>Présence de classe</option>
								<option value="chapelle_lundi">Exercice de chapelle (lundis)</option>
								<option value="semaine_priere">Semaine de prière (lundi à jeudi)</option>
							</select>
						</div>
					</div>

					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="chapelle_dates">Lundis</label>
						</div>
						<div class="w-9/12">
							<input
								id="chapelle_dates"
								type="text"
								name="chapelle_dates"
								class="input w-full"
								value="13 avril, 20 avril, 27 avril, 11 mai, 1 juin, 8 juin, 15 juin, 22 juin, 29 juin, 6 juillet"
							>
							<small class="text-slate-500">Utilisé pour le type chapelle (séparer par virgules).</small>
						</div>
					</div>

					<hr>

					<b class="toolInactive">Listes.</b>
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="presence_types">Mention</label>
						</div>
						<div class="w-9/12">
							<select id="presence_types" name="types" class="input w-full">
								<option value="TOUT">Tout</option>
								<?php
								$voirPresence = $dtb->query("SELECT * FROM filiere");
								while ($affichePresence = $voirPresence->fetch()) {
								?>
									<option value="<?=$affichePresence['filiere_sigle'];?>"><?=$affichePresence['filiere_description'];?></option>
								<?php
								}
								?>
							</select>
						</div>
					</div>

					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="presence_anneescolaire">Année</label>
						</div>
						<div class="w-9/12">
							<select id="presence_anneescolaire" name="anneescolaire" class="input w-full">
								<?php
								$yPresence = date('Y');
								for ($iPresence=0; $iPresence <= 3; $iPresence++) {
									if (date('m')>7) {
										$anneePresence = $yPresence." - ".($yPresence+1);
									} else {
										$anneePresence = ($yPresence-1)." - ".$yPresence;
									}
								?>
									<option value="<?=$anneePresence?>"><?=$anneePresence?></option>
								<?php
									$yPresence = $yPresence - 1;
								}
								?>
							</select>
						</div>
					</div>

					<hr>

					<b class="toolInactive">Niveau/Semestre.</b>
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="presence_annee">Niveau : </label>
						</div>
						<div class="w-9/12">
							<select id="presence_annee" name="annee_etude" class="input w-full">
								<option value="tout">Tout</option>
								<option value="1">Licence 1</option>
								<option value="2">Licence 2</option>
								<option value="3">Licence 3</option>
								<option value="4">Master 1</option>
								<option value="5">Master 2</option>
								<option value="10">Classe spéciale</option>
							</select>
						</div>
					</div>

					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2"></div>
						<div class="w-9/12">
							<select id="presence_niveau_scope" name="niveau_scope" class="input w-full">
								<option value="all" selected>Tous niveaux (Licence + Master)</option>
								<option value="licence">Licence seulement (sans Master)</option>
							</select>
						</div>
					</div>

					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="presence_semestre">Semestre : </label>
						</div>
						<div class="w-9/12">
							<select id="presence_semestre" name="semestre" class="input w-full">
								<option <?php if (date('m')>7) { echo "selected"; } ?> value="1">Premier semestre</option>
								<option value="3">Semestre d'été</option>
								<option <?php if (date('m')<=7) { echo "selected"; } ?> value="2">Deuxième semestre</option>
								<option value="4">Semestre d'hiver</option>
							</select>
						</div>
					</div>
				</div>
				<div class="p-3">
					<center>
						<a href="#" id="cancelnotifPresence" class="bg-slate-400 p-2 rounded-md">Annuler</a>
						<input type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
					</center>
				</div>
			</form>
		</div>
	</div>

<!-- FOR LIST COURS -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListCours" style="backdrop-filter: blur(3px);">
		
		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<div class="p-2 text-black">
				<b>Exporter la liste de cours.</b>
			</div>
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=listeCours" target="_blank">
			<div class="p-2">
	      			<b class="toolInactive">Listes.</b>
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="types">Mention</label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="types" name="types" class="input w-full">
				      				<option value="TOUT">Tout</option>
			<?php 
						$voir = $dtb->query("SELECT * FROM filiere");
						while ($affiche = $voir->fetch()) {?>
											
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

			<?php	
				}
			 ?>	
				      			</select>	
				      		</div>
				      	</div>

				      	<hr>
				      	
				      	<b class="toolInactive">Niveau/Semestre.</b>
				      	
				      	<div class="flex mb-3">

				      		<div class="w-3/12 text-right pr-2">
				      			<label for="annee">Niveau : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="annee" name="yearlevel" class="input w-full">
				      				<option value="tout">Tout</option>
				      				<option value="1">Licence 1</option>
				      				<option value="2">Licence 2</option>
				      				<option value="3">Licence 3</option>
				      				<option value="4">Master 1</option>
				      				<option value="5">Master 2</option>
				      			</select>	
				      		</div>
				      	</div>
				      	
				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2"></div>
				      		<div class="w-9/12">
				      			<input id="master" type="checkbox" name="master">
				      			<label for="master">Avec MASTER</label>	
				      		</div>
				      	</div>

				      	<div class="flex mb-3">
				      		<div class="w-3/12 text-right pr-2">
				      			<label for="semestre">Semestre : </label>
				      		</div>
				      		<div class="w-9/12">
				      			<select id="semestre" name="semester" class="input w-full">
				      				<option <?php 
if (date('m')>7) {
	echo "selected";	
}else{

}
						 ?> value="1">Premier semestre</option>
						<option value="3">Semestre d'été</option>
						<option <?php 
if (date('m')>7) {
	
}else{
	echo "selected";
}
						 ?> value="2">Deuxième semestre</option>
						<option value="4">Semestre d'hiver</option>
				      			</select>	
				      		</div>
				      	</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListCours" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnCoursList" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR FOP LIST -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListFOP" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf_paysage?ptype=foplist" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la requête FOP.</b>
			</div>
			<div class="p-2">
				<div class="w-all">
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearFOP">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearFOP" id="yearFOP" class="input w-full">
								<option></option>
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				    </div>
					
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListFOP" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnFOP" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- CANEVAS MESUPRES (Excel) -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifCanevas" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<?php /* Le classeur du ministere, rempli avec les inscrits de l'annee (app/canevas-mesupres.php) : il se telecharge, la page reste. */ ?>
			<form method="post" id="formCanevas" action="<?=$app_base?>/app/canevas-mesupres">
			<div class="p-2 text-black">
				<b>Canevas de collecte de la MESupReS (Excel).</b>
			</div>
			<div class="p-2">
				<div class="w-all">
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="mentionCanevas">Mention</label>
						</div>
						<div class="w-9/12">
							<select id="mentionCanevas" name="mention" class="input w-full">
								<option value="TOUT">Toutes</option>
		<?php
					$voirCanevas = $dtb->query("SELECT filiere_sigle, filiere_description FROM filiere WHERE filiere_sigle <> 'CPRE' ORDER BY filiere_id");
					while ($afficheCanevas = $voirCanevas->fetch()) { ?>
								<option value="<?=htmlspecialchars($afficheCanevas['filiere_sigle'], ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($afficheCanevas['filiere_description'], ENT_QUOTES, 'UTF-8');?></option>
		<?php
					} ?>
							</select>
						</div>
					</div>

					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
							<label for="anneeCanevas">Année</label>
						</div>
						<div class="w-9/12">
							<select name="annee" id="anneeCanevas" class="input w-full">
		<?php
					/* Les annees qui ont des sessions, la plus recente d'abord ; l'annee universitaire en cours est choisie. */
					$anneesCanevas = $dtb->query("SELECT DISTINCT session_year FROM t_2023_session WHERE TRIM(COALESCE(session_year, '')) <> '' ORDER BY session_year DESC")->fetchAll(PDO::FETCH_COLUMN);
					$moisCanevas = (int) date('m');
					$enCoursCanevas = $moisCanevas >= 10 ? date('Y') . ' - ' . (date('Y') + 1) : (date('Y') - 1) . ' - ' . date('Y');
					if (!in_array($enCoursCanevas, $anneesCanevas, true)) { array_unshift($anneesCanevas, $enCoursCanevas); }
					foreach ($anneesCanevas as $anCanevas) { ?>
								<option<?= $anCanevas === $enCoursCanevas ? ' selected' : '' ?>><?=htmlspecialchars($anCanevas, ENT_QUOTES, 'UTF-8')?></option>
		<?php
					} ?>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifCanevas" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnCanevas" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Télécharger">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- MAIL CSV -->

	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifListCSV" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			
			<?php /* Fichier d'import Google Admin (src/genPDF/gen.mail_csv.php) : il se
			         telecharge, la fenetre reste ouverte pour un autre export. */ ?>
			<form method="post" action="<?=$app_base?>/src/genPDF/gen.mail_csv">
			<div class="p-2 text-black">
				<b>Comptes Google des étudiants (CSV à importer dans Google Admin).</b>
			</div>
			<div class="p-2">
				<div class="w-all">
					<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php
					/* Les mentions telles qu'ecrites sur les fiches : celles de la
					   table filiere different parfois (« Éducation ») et ne
					   retrouveraient pas les etudiants. */
					$voir = $dtb->query("SELECT DISTINCT etude_envisage FROM tbl_2024_etudiant WHERE `remove` = 0 AND TRIM(COALESCE(etude_envisage, '')) <> '' ORDER BY etude_envisage");
					while ($affiche = $voir->fetch()) {?>
								<option value="<?=htmlspecialchars($affiche['etude_envisage'], ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($affiche['etude_envisage'], ENT_QUOTES, 'UTF-8');?></option>
		<?php
			}
		 ?>
			      			</select>
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="niveauListCSV">Niveau</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="niveau" id="niveauListCSV" class="input w-full">
								<option value="tout">Tout</option>
								<option value="licence">Licence (et remise à niveau)</option>
								<option value="master">Master</option>
							</select>
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearListCSV">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearListCSV" id="yearListCSV" class="input w-full">
								<option></option>
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				    </div>

				    <div class="flex mb-3">
				    	<div class="w-3/12"></div>
					    <div class="w-9/12">
					    	<label class="inline-flex items-center gap-2 cursor-pointer">
					    		<input type="checkbox" name="nouveaux" value="1" id="nouveauxListCSV" checked>
					    		Nouveaux étudiants seulement
					    	</label>
					    </div>
				    </div>

				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifListCSV" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnListCSV" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Télécharger le CSV">
				</center>
			</div>
			</form>

		</div>

	</div>

<!-- FOR STATISTIC -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifStatistic" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=Statistique" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la statistique générale.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearStatistic">Semestre</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="semestre" id="semstre" class="input w-full">
								<option>Semestre d'été</option>
						<option <?php if (date('m')>=7) {echo "selected";} ?>>Premier semestre</option>
						<option>Semestre d'hiver</option>
						<option <?php if (date('m')<7) {echo "selected";} ?>>Deuxième semestre</option>
							</select>
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearStatistic">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearStatistic" id="yearStatistic" class="input w-full">
								 <?php
								$mois = date('m');
								if (intval($mois) < 9){
									$z = date('Y');
								}else{
									
									$z = date('Y') + 1;
								}
								$yn = 1;
				        			for ($i=1; $i < 10; $i++) { 
				        			?>
				        				<option><?=($z-1)." - ".$z?></option>
				        			<?php
				        			$z = $z-$yn;
				        		}
			        		 	?>
							</select>
					    </div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifStatistic" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnStatistic" type="submit" class="bg-cyan-800 p-2 rounded-md text-white mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR TICKET MAIL -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifTicketMail" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=ticketMail" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter le Ticket par Email.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>
					    </div>
					    
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
					      	<label for="level">Niveau</label>
					    </div>
					    <div class="w-9/12">
			      			<select id="level" name="level" class="input w-full">
			      				<option value="TOUT">Tout</option>
								<option value="1">Licence 1</option>
								<option value="2">Licence 2</option>
								<option value="3">Licence 3</option>
								<option value="4">Master 1</option>
								<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearTicket" id="yearTicket" class="input w-full">
								<option></option>
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifTicketMail" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnTicketMail" type="submit" class="bg-slate-400 p-2 rounded-md mx-1 toolInactive" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- FOR FINANCE -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifFinance" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf_paysage?ptype=Finance" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter le financement lors de l'inscrition de...</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearFinance">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearFinance" id="yearFinance" class="input w-full">
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="semestreFinance">Semestre</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="semestreFinance" id="semestreFinance" class="input w-full">
								<option <?php 
if (date('m')>7) {
	echo "selected";	
}else{

}
						 ?> value="1">Premier semestre</option>
						<option value="3">Semestre d'été</option>
						<option <?php 
if (date('m')>7) {
	
}else{
	echo "selected";
}
						 ?> value="2">Deuxième semestre</option>
						<option value="4">Semestre d'hiver</option>
							</select>
					    </div>
				</div>
				<hr><br>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>
					    </div>
					    
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
					      	<label for="level">Niveau</label>
					    </div>
					    <div class="w-9/12">
			      			<select id="level" name="level" class="input w-full">
			      				<option value="TOUT">Tout</option>
								<option value="1">Licence 1</option>
								<option value="2">Licence 2</option>
								<option value="3">Licence 3</option>
								<option value="4">Master 1</option>
								<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<!-- <hr><br>
				<div class="flex mb-3">
		      		<div class="w-3/12 text-right pr-2">
		      			
		      		</div>
		      		<div class="w-9/12">
		      			<input id="signature" type="checkbox" name="new_student">
		      			<label for="signature"> Les nouveaux seulement.</label>
		      		</div>
		      	</div> -->
				
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifFinance" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnFinance" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- FOR WORKED SLIP -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifworkedSlip" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=workedSlip" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les (worked slip).</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<label class="text-slate-500" style="font-size:11px;display:block;">Semestre</label>
					    	<select name="semesterWorkedSlip" class="input w-full" style="margin-bottom:8px;">
					    		<option <?php if (date('m')>=7) {echo "selected";} ?> value="1">Premier semestre</option>
					    		<option value="3">Semestre d'été</option>
					    		<option <?php if (date('m')<7) {echo "selected";} ?> value="2">Deuxième semestre</option>
					    		<option value="4">Semestre d'hiver</option>
					    	</select>
					    	<label class="text-slate-500" style="font-size:11px;display:block;">Année</label>
					    	<select name="yearworkedSlip" class="input w-full">
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
				<hr><br>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="yearTicket">Début d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input id="yearworkedSlip" type="date" name="date_begin" class="input w-full">
				    </div>
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="yearTicket">Fin d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input type="date" name="date_end" class="input w-full">
				    </div>
				</div>
				<hr><br>
				<b class="text-slate-500">Champs à afficher sur le slip :</b>
				<div class="flex mb-3 flex-wrap">
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_matricule" id="show_matricule" checked>
						<label for="show_matricule">Matricule</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_nom" id="show_nom" checked>
						<label for="show_nom">Nom et prénoms</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_mention" id="show_mention" checked>
						<label for="show_mention">Mention</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_niveau" id="show_niveau" checked>
						<label for="show_niveau">Niveau</label>
					</div>
				</div>
				<hr>
				<b class="text-slate-500">Exporter pour un seul étudiant :</b>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="single_student_id">Matricule</label>
					</div>
					<div class="w-9/12">
						<input type="text" name="single_student_id" id="single_student_id" class="input w-full" placeholder="Laisser vide pour exporter tout">
					</div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifworkedSlip" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnworkedSlip" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR FILES CHECKING SLIP -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notiffilesSlip" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=filesSlip" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les (File's Checking Slip).</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="typesFiles">Mention</label>
					    </div>
					    <div class="w-9/12">
							<select id="typesFiles" name="types" class="input w-full">
					  			<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
				  			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearFilesSlip">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<label class="text-slate-500" style="font-size:11px;display:block;">Semestre</label>
					    	<select name="semesterFilesSlip" class="input w-full" style="margin-bottom:8px;">
					    		<option <?php if (date('m')>=7) {echo "selected";} ?> value="1">Premier semestre</option>
					    		<option value="3">Semestre d'été</option>
					    		<option <?php if (date('m')<7) {echo "selected";} ?> value="2">Deuxième semestre</option>
					    		<option value="4">Semestre d'hiver</option>
					    	</select>
					    	<label class="text-slate-500" style="font-size:11px;display:block;">Année</label>
					    	<select name="yearFilesSlip" id="yearFilesSlip" class="input w-full">
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
				<hr><br>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="date_begin_files">Début d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input id="date_begin_files" type="date" name="date_begin" class="input w-full">
				    </div>
				</div>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
				      	<label for="date_end_files">Fin d'éxamen</label>
				    </div>
				    <div class="w-9/12">
				    	<input id="date_end_files" type="date" name="date_end" class="input w-full">
				    </div>
				</div>
				<hr><br>
				<b class="text-slate-500">Champs à afficher sur le slip :</b>
				<div class="flex mb-3 flex-wrap">
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_matricule" id="show_matricule_files" checked>
						<label for="show_matricule_files">Matricule</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_nom" id="show_nom_files" checked>
						<label for="show_nom_files">Nom et prénoms</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_mention" id="show_mention_files" checked>
						<label for="show_mention_files">Mention</label>
					</div>
					<div class="w-6/12 p-1">
						<input type="checkbox" name="show_niveau" id="show_niveau_files" checked>
						<label for="show_niveau_files">Niveau</label>
					</div>
				</div>
				<hr>
				<b class="text-slate-500">Exporter pour un seul étudiant :</b>
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="single_student_id_files">Matricule</label>
					</div>
					<div class="w-9/12">
						<input type="text" name="single_student_id" id="single_student_id_files" class="input w-full" placeholder="Laisser vide pour exporter tout">
					</div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotiffilesSlip" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnfilesSlip" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR WORKED -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifworked" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=worked" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter la liste (worked) de manière groupée.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<option value="TOUT">Tout</option>
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="niveau">Niveau</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="niveau" name="niveau" class="input w-full">
			    				<option value="1">Licence 1</option>
			    				<option value="2">Licence 2</option>
			    				<option value="3">Licence 3</option>
			    				<option value="4">Master 1</option>
			    				<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearworked" id="yearworked" class="input w-full">
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifworked" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnworked" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>

<!-- FOR BADGE EN GROUPE -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifBadgeGr" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl">
			<form method="post" action="<?=$app_base?>/src/data.topdf?ptype=BadgeGr" target="_blank">
			<div class="p-2 text-black">
				<b>Exporter les badges en groupe.</b>
			</div>
			<div class="p-2">
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="types">Mention</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="types" name="types" class="input w-full">
			      				<!-- <option value="TOUT">Tout</option> -->
		<?php 
					$voir = $dtb->query("SELECT * FROM filiere");
					while ($affiche = $voir->fetch()) {?>
										
								<option value="<?=$affiche['filiere_sigle']?>"><?=$affiche['filiere_description'];?></option>

		<?php	
			}
		 ?>	
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="niveau">Niveau</label>
					    </div>
					    <div class="w-9/12">
			    			<select id="niveau" name="level" class="input w-full">
			    				<option value="TOUT">Tout</option>
			    				<option value="1" selected>Licence 1</option>
			    				<option value="2">Licence 2</option>
			    				<option value="3">Licence 3</option>
			    				<option value="4">Master 1</option>
			    				<option value="5">Master 2</option>
			      			</select>	
					    </div>
				</div>
				<div class="flex mb-3">
						<div class="w-3/12 text-right pr-2">
					      	<label for="niveau">Semestre</label>
					    </div>
					    <div class="w-9/12">
			    			<select name="semesterBadgeGr" class="input w-full">
								<option <?php if (date('m')>=7) {echo "selected";} ?> value="1">Premier semestre</option>
								<option value="3">Semestre d'été</option>
								<option <?php if (date('m')<7) {echo "selected";} ?> value="2">Deuxième semestre</option>
								<option value="4">Semestre d'hiver</option>
							</select>
					    </div>
				</div>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	<label for="yearTicket">Année</label>
					    </div>
					    <div class="w-9/12">
					    	<select name="yearBadgeGr" id="yearBadgeGr" class="input w-full">
								<?php
								$y = date('Y');
								for ($i=0; $i <= 8; $i++) { 
									
									$as = $y." - ".($y+1);
								?>
								<option><?=$as?></option>
								<?php
								$y = $y - 1;
								}
								 ?>
							</select>
					    </div>
				</div>
				<hr><br>
				<div class="flex mb-3">
				    	<div class="w-3/12 text-right pr-2">
					      	
					    </div>
					    <div class="w-9/12">
					    	<input type="checkbox" name="back" id="back" checked>
								<label for="back"> Imprimer avec verso.</label>
					    </div>
				</div>
				<hr>
				<b class="text-slate-500">Si vous voulez exporter pour un seul étudiant...</b><br>
				<div class="flex mb-3">
				    	<div class="w-6/12 text-right pr-2">
					      	<label for="yearTicket">Matricule</label>
					    </div>
					    <div class="w-6/12">
					    	<input type="text" name="student_id" class="input w-full">
					    </div>
				</div><hr>
			</div>
			<div class="p-3">
				<center>
				<a href="#" id="cancelnotifBadgeGr" class="bg-slate-400 p-2 rounded-md">Annuler</a>
				<input id="btnBadgeGr" type="submit" class="bg-cyan-800 p-2 rounded-md mx-1 text-white" value="Afficher">
				</center>
			</div>
			</form>
		</div>

	</div>


<!-- FOR EXCEL EXPORT -->
	<div class="modal-legacy absolute w-full h-screen top-0 left-0 z-40 hidden" id="notifExcel" style="backdrop-filter: blur(3px);">

		<div class="w-[500px] bg-slate-100 border-2 border-slate-700 mx-auto my-[5%] opacity-100 drop-shadow-2xl rounded-lg overflow-hidden">
			<div class="p-3 bg-green-600 text-white flex items-center gap-2">
				<i class="bi bi-file-earmark-excel-fill text-lg"></i>
				<b>Exporter la liste d'étudiants (Excel)</b>
			</div>
			<form method="post" action="<?=$app_base?>/export/students/xlsx" target="_blank">
			<div class="p-4">
				<b class="text-slate-600 text-sm">Type d'exportation</b>
				<div class="flex mb-3 mt-2">
					<div class="w-3/12 text-right pr-2">
						<label for="excelExportType" class="text-sm">Type</label>
					</div>
					<div class="w-9/12">
						<select id="excelExportType" name="exportation" class="input w-full border rounded px-2 py-1">
							<option value="general">Liste d'étudiant générale</option>
							<option value="internat">Liste d'étudiant interne</option>
							<option value="abnment">Liste d'étudiant abonnée</option>
							<option value="adventiste">Liste d'étudiant Adventiste</option>
							<option value="non_adventiste">Liste d'étudiant Non-Adventiste</option>
							<option value="contacts">Liste avec contacts (email, téléphone)</option>
						</select>	
					</div>
				</div>
				
				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2"></div>
					<div class="w-9/12">
						<input id="excelNewStudent" type="checkbox" name="new_student">
						<label for="excelNewStudent" class="text-sm"> Les nouveaux seulement</label>
					</div>
				</div>
				
				<hr class="my-3">
				
				<b class="text-slate-600 text-sm">Filtres</b>
				<div class="flex mb-3 mt-2">
					<div class="w-3/12 text-right pr-2">
						<label for="excelMention" class="text-sm">Mention</label>
					</div>
					<div class="w-9/12">
						<select id="excelMention" name="types" class="input w-full border rounded px-2 py-1">
							<option value="TOUT">Tout</option>
<?php 
	$voir = $dtb->query("SELECT * FROM filiere");
	while ($affiche = $voir->fetch()) {?>
							<option value="<?=$affiche['filiere_sigle'];?>"><?=$affiche['filiere_description'];?></option>
<?php } ?>	
						</select>	
					</div>
				</div>

				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="excelAnneeScolaire" class="text-sm">Année</label>
					</div>
					<div class="w-9/12">
						<select id="excelAnneeScolaire" name="anneescolaire" class="input w-full border rounded px-2 py-1">
<?php
	$y = date('Y');
	for ($i=0; $i <= 3; $i++) { 
		if (date('m')>7) {
			$annee = $y." - ".($y+1);	
		}else{
			$annee = ($y-1)." - ".$y;
		}
?>
							<option value="<?=$annee?>"><?=$annee?></option>
<?php
		$y = $y - 1;
	}
?>
						</select>	
					</div>
				</div>

				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="excelNiveau" class="text-sm">Niveau</label>
					</div>
					<div class="w-9/12">
						<select id="excelNiveau" name="annee_etude" class="input w-full border rounded px-2 py-1">
							<option value="tout">Tout</option>
							<option value="1">Licence 1</option>
							<option value="2">Licence 2</option>
							<option value="3">Licence 3</option>
							<option value="4">Master 1</option>
							<option value="5">Master 2</option>
							<option value="10">Classe spéciale</option>
						</select>	
					</div>
				</div>

				<div class="flex mb-3">
					<div class="w-3/12 text-right pr-2">
						<label for="excelSemestre" class="text-sm">Semestre</label>
					</div>
					<div class="w-9/12">
						<select id="excelSemestre" name="semestre" class="input w-full border rounded px-2 py-1">
							<option <?php if (date('m')>7) { echo "selected"; } ?> value="1">Premier semestre</option>
							<option value="3">Semestre d'été</option>
							<option <?php if (date('m')<=7) { echo "selected"; } ?> value="2">Deuxième semestre</option>
							<option value="4">Semestre d'hiver</option>
						</select>	
					</div>
				</div>

				<hr class="my-3">

				<b class="text-slate-600 text-sm">Colonnes à inclure</b>
				<div class="flex flex-wrap mb-3 mt-2">
					<div class="w-4/12 p-1">
						<input id="excelMatricule" type="checkbox" name="col_matricule" checked>
						<label for="excelMatricule" class="text-sm">Matricule</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelNom" type="checkbox" name="col_nom" checked>
						<label for="excelNom" class="text-sm">Nom</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelPrenom" type="checkbox" name="col_prenom" checked>
						<label for="excelPrenom" class="text-sm">Prénom</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelSexe" type="checkbox" name="col_sexe" checked>
						<label for="excelSexe" class="text-sm">Sexe</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelDateNaissance" type="checkbox" name="col_datenaissance">
						<label for="excelDateNaissance" class="text-sm">Date naiss.</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelMentionCol" type="checkbox" name="col_mention" checked>
						<label for="excelMentionCol" class="text-sm">Mention</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelNiveauCol" type="checkbox" name="col_niveau" checked>
						<label for="excelNiveauCol" class="text-sm">Niveau</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelEmail" type="checkbox" name="col_email">
						<label for="excelEmail" class="text-sm">Email</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelTelephone" type="checkbox" name="col_telephone">
						<label for="excelTelephone" class="text-sm">Téléphone</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelAdresse" type="checkbox" name="col_adresse">
						<label for="excelAdresse" class="text-sm">Adresse</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelStatus" type="checkbox" name="col_status">
						<label for="excelStatus" class="text-sm">Statut</label>
					</div>
					<div class="w-4/12 p-1">
						<input id="excelReligion" type="checkbox" name="col_religion">
						<label for="excelReligion" class="text-sm">Religion</label>
					</div>
				</div>
			</div>
			<div class="p-3 bg-slate-200 flex justify-center gap-3">
				<a href="#" id="cancelnotifExcel" class="bg-slate-400 hover:bg-slate-500 text-white px-4 py-2 rounded-md transition">Annuler</a>
				<input id="btnExcel" type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md cursor-pointer transition" value="Télécharger Excel">
			</div>
			</form>
		</div>

	</div>


<!-- ----------------------------------------------------------------- SCRIPTS ----------------------------------------------------------------------------- -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script type="text/javascript">
	function toExcelColumn(index) {
		let value = index;
		let result = '';

		while (value > 0) {
			const remainder = (value - 1) % 26;
			result = String.fromCharCode(65 + remainder) + result;
			value = Math.floor((value - 1) / 26);
		}

		return result || 'A';
	}

	$(document).ready(function(){
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportExcel').click(function(){
			$('#notifExcel').css({'display':'block'});
		});

		$('#cancelnotifExcel').click(function(){
			$('#notifExcel').css({'display':'none'});
		});

		$('#notifExcel form').on('submit', async function(event) {
			event.preventDefault();

			if (typeof ExcelJS === 'undefined') {
				if (window.Toast) { Toast.error('La librairie Excel n\'est pas chargée.'); } else if (window.console) { console.error('La librairie Excel n\'est pas chargée.'); }
				return;
			}

			const form = this;
			const formData = new FormData(form);
			formData.set('response', 'json');

			try {
				const response = await fetch(form.action, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				});

				const payload = await response.json();

				if (!response.ok || !payload.success) {
					throw new Error(payload.message || 'Export impossible');
				}

				if (!payload.students || payload.students.length === 0) {
					if (window.Toast) { Toast.warning('Aucune donnée à exporter avec ces critères.'); } else if (window.console) { console.warn('Aucune donnée à exporter avec ces critères.'); }
					return;
				}

				const workbook = new ExcelJS.Workbook();
				workbook.creator = 'Infinit Registrar';
				workbook.created = new Date();
				workbook.modified = new Date();

				const worksheet = workbook.addWorksheet('Etudiants', {
					properties: { defaultRowHeight: 15 },
					pageSetup: { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
					margins: { left: 0.5, right: 0.5, top: 1, bottom: 0.75 }
				});

				const headerCount = payload.headers.length;
				const visualColCount = Math.max(headerCount + 1, 11);
				const visualEndCol = toExcelColumn(visualColCount);
				const logoUrl = (typeof APP_BASE !== 'undefined' ? APP_BASE : '') + '/file/UAZ%20Official%20Black%20Logo.jpg';

				// Configuration des colonnes
				worksheet.getColumn(1).width = 5;
				for (let i = 2; i <= visualColCount; i++) {
					worksheet.getColumn(i).width = 18;
				}

				// Rangées 1-2: En-tête avec logo et infos UAZ
				worksheet.mergeCells('B1:' + visualEndCol + '2');
				worksheet.mergeCells('A1:A2');

				const uazHeader = worksheet.getCell('B1');
				uazHeader.value = 'UNIVERSITÉ ADVENTISTE ZURCHER\nBUREAU DE LA REGISTRAIRE\nBP 325, Antsirabe 110, MADAGASCAR\nTél : 034 38 180 81';
				uazHeader.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
				uazHeader.font = { bold: true, size: 11, color: { argb: '000000' } };

				worksheet.getRow(1).height = 55;
				worksheet.getRow(2).height = 0;

				// Charger le logo
				try {
					const logoResponse = await fetch(logoUrl, { credentials: 'same-origin' });
					if (logoResponse.ok) {
						const logoBuffer = await logoResponse.arrayBuffer();
						const logoId = workbook.addImage({ buffer: logoBuffer, extension: 'jpg' });
						worksheet.addImage(logoId, {
							tl: { col: 0.1, row: 0.05 },
							ext: { width: 70, height: 70 }
						});
					}
				} catch (logoError) {
					console.warn('Logo non chargé:', logoError);
				}

				// Rangée 3: Vide
				worksheet.getRow(3).height = 8;

				// Rangée 4: Titre principal
				worksheet.mergeCells('A4:' + visualEndCol + '4');
				const titleCell = worksheet.getCell('A4');
				titleCell.value = 'Liste des étudiants';
				titleCell.font = { bold: true, size: 12, color: { argb: '000000' } };
				titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
				worksheet.getRow(4).height = 20;

				// Rangée 5: Session/Semestre
				worksheet.mergeCells('A5:' + visualEndCol + '5');
				const sessionCell = worksheet.getCell('A5');
				sessionCell.value = payload.session || '';
				sessionCell.font = { italic: true, size: 10, color: { argb: '666666' } };
				sessionCell.alignment = { horizontal: 'center', vertical: 'middle' };
				worksheet.getRow(5).height = 16;

				// Rangée 6: Vide
				worksheet.getRow(6).height = 6;

				const columnKeys = [];
				if (formData.get('col_matricule') !== null) columnKeys.push('student_id');
				if (formData.get('col_nom') !== null) columnKeys.push('student_nom');
				if (formData.get('col_prenom') !== null) columnKeys.push('student_prenom');
				if (formData.get('col_sexe') !== null) columnKeys.push('sex');
				if (formData.get('col_datenaissance') !== null) columnKeys.push('dateNaissance');
				if (formData.get('col_mention') !== null) columnKeys.push('etude_envisage');
				if (formData.get('col_niveau') !== null) columnKeys.push('annee_etude');
				if (formData.get('col_email') !== null) columnKeys.push('student_email');
				if (formData.get('col_telephone') !== null) columnKeys.push('student_tel');
				if (formData.get('col_adresse') !== null) columnKeys.push('student_adresse');
				if (formData.get('col_status') !== null) columnKeys.push('status');
				if (formData.get('col_religion') !== null) columnKeys.push('religion');

				const groupedStudents = Array.isArray(payload.grouped_students)
					? payload.grouped_students.filter(group => group && Array.isArray(group.students) && group.students.length > 0)
					: [];

				const groupsToRender = groupedStudents.length > 0
					? groupedStudents
					: [{ mention: payload.mention || 'Tous les programmes', students: payload.students || [] }];

				let currentRow = 7;

				groupsToRender.forEach(function(group) {
					const mentionTitle = 'Mention ' + (group.mention || 'Sans mention');
					worksheet.mergeCells('A' + currentRow + ':' + visualEndCol + currentRow);
					const mentionCell = worksheet.getCell('A' + currentRow);
					mentionCell.value = mentionTitle;
					mentionCell.font = { bold: true, size: 10, color: { argb: '000000' } };
					mentionCell.alignment = { horizontal: 'left', vertical: 'middle' };
					worksheet.getRow(currentRow).height = 16;
					currentRow++;

					const headerRow = worksheet.getRow(currentRow);
					headerRow.height = 22;

					const noHeaderCell = headerRow.getCell(1);
					noHeaderCell.value = 'No';
					noHeaderCell.font = { bold: true, color: { argb: 'FFFFFF' }, size: 10 };
					noHeaderCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: '6B7280' } };
					noHeaderCell.alignment = { horizontal: 'center', vertical: 'middle' };
					noHeaderCell.border = {
						top: { style: 'thin', color: { argb: 'FFFFFF' } },
						left: { style: 'thin', color: { argb: 'FFFFFF' } },
						bottom: { style: 'thin', color: { argb: 'FFFFFF' } },
						right: { style: 'thin', color: { argb: 'FFFFFF' } }
					};

					payload.headers.forEach(function(header, index) {
						const cell = headerRow.getCell(index + 2);
						cell.value = header;
						cell.font = { bold: true, color: { argb: 'FFFFFF' }, size: 10 };
						cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: '6B7280' } };
						cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
						cell.border = {
							top: { style: 'thin', color: { argb: 'FFFFFF' } },
							left: { style: 'thin', color: { argb: 'FFFFFF' } },
							bottom: { style: 'thin', color: { argb: 'FFFFFF' } },
							right: { style: 'thin', color: { argb: 'FFFFFF' } }
						};
					});

					currentRow++;

					(group.students || []).forEach(function(student, studentIndex) {
						const row = worksheet.getRow(currentRow);
						row.height = 18;

						const noCell = row.getCell(1);
						noCell.value = studentIndex + 1;
						noCell.alignment = { vertical: 'middle', horizontal: 'center' };

						columnKeys.forEach(function(key, colIndex) {
							const cell = row.getCell(colIndex + 2);

							if (key === 'sex') {
								cell.value = String(student.sex) === '1' ? 'M' : 'F';
							} else if (key === 'annee_etude') {
								const niveau = String(student.annee_etude || '');
								cell.value = niveau === '0' ? 'Remise à niveau' : (niveau === '1' ? 'Licence 1' : niveau === '2' ? 'Licence 2' : niveau === '3' ? 'Licence 3' : niveau === '4' ? 'Master 1' : niveau === '5' ? 'Master 2' : niveau === '10' ? 'Classe spéciale' : niveau);
							} else if (key === 'student_nom') {
								cell.value = (student.student_nom || '').toUpperCase();
							} else {
								cell.value = student[key] || '';
							}

							cell.alignment = { vertical: 'middle', wrapText: true };
							const bgColor = studentIndex % 2 === 0 ? 'F3F4F6' : 'FFFFFF';
							cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bgColor } };
							cell.border = {
								top: { style: 'thin', color: { argb: 'E5E7EB' } },
								left: { style: 'thin', color: { argb: 'E5E7EB' } },
								bottom: { style: 'thin', color: { argb: 'E5E7EB' } },
								right: { style: 'thin', color: { argb: 'E5E7EB' } }
							};
						});

						const noCellData = row.getCell(1);
						const bgColor = studentIndex % 2 === 0 ? 'F3F4F6' : 'FFFFFF';
						noCellData.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bgColor } };
						noCellData.border = {
							top: { style: 'thin', color: { argb: 'E5E7EB' } },
							left: { style: 'thin', color: { argb: 'E5E7EB' } },
							bottom: { style: 'thin', color: { argb: 'E5E7EB' } },
							right: { style: 'thin', color: { argb: 'E5E7EB' } }
						};

						currentRow++;
					});

					currentRow++;
				});

				worksheet.views = [{ state: 'frozen', ySplit: 6 }];

				await workbook.xlsx.writeBuffer().then(function(buffer) {
					const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
					const link = document.createElement('a');
					link.href = URL.createObjectURL(blob);
					link.download = (payload.filename || 'export') + '.xlsx';
					document.body.appendChild(link);
					link.click();
					URL.revokeObjectURL(link.href);
					link.remove();
				});
				$('#notifExcel').css({'display':'none'});
			} catch (error) {
				console.error('Erreur export Excel:', error);
				if (window.Toast) { Toast.error('Erreur lors de la création du fichier Excel : ' + error.message); } else if (window.console) { console.error(error); }
			}
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListStd').click(function(){
			$('#notifListStd').css({'display':'block'});
		});

		$('#cancelnotifListStd').click(function(){
			$('#notifListStd').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListCours').click(function(){
			$('#notifListCours').css({'display':'block'});
		});

		$('#cancelnotifListCours').click(function(){
			$('#notifListCours').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListFOP').click(function(){
			$('#notifListFOP').css({'display':'block'});
		});
		$('#yearFOP').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnFOP').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnFOP').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifListFOP').click(function(){
			$('#notifListFOP').css({'display':'none'});
		});


		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportListCSV').click(function(){
			$('#notifListCSV').css({'display':'block'});
		});
		$('#yearListCSV').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnListCSV').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnListCSV').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifListCSV').click(function(){
			$('#notifListCSV').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#exportCanevas').click(function(e){
			e.preventDefault();
			$('#notifCanevas').css({'display':'block'});
		});
		$('#cancelnotifCanevas').click(function(e){
			e.preventDefault();
			$('#notifCanevas').css({'display':'none'});
		});
		$('#formCanevas').on('submit', function(){
			// le classeur se telecharge : la fenetre se ferme, la page reste
			setTimeout(function(){ $('#notifCanevas').css({'display':'none'}); }, 600);
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/


		$('#exportTicketMail').click(function(){
			$('#notifTicketMail').css({'display':'block'});
		});
		$('#yearTicket').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnTicketMail').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnTicketMail').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifTicketMail').click(function(){
			$('#notifTicketMail').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('#finance').click(function(){
			$('#notifFinance').css({'display':'block'});
		});
		$('#yearFinance').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnFinance').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnFinance').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifFinance').click(function(){
			$('#notifFinance').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('#exportStatistic').click(function(){
			$('#notifStatistic').css({'display':'block'});
		});

		$('#exportPresence').click(function(){
			$('#notifPresence').css({'display':'block'});
		});
		$('#yearStatistic').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnStatistic').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnStatistic').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifStatistic').click(function(){
			$('#notifStatistic').css({'display':'none'});
		});

		$('#cancelnotifPresence').click(function(){
			$('#notifPresence').css({'display':'none'});
		});

		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		
		$('.logOut').click(function(){
			$('#notifLogOut').css({'display':'block'});
		});
		
		$('#cancelnotifLogOut').click(function(){
			$('#notifLogOut').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
		

		$('#workedSlip').click(function(){
			$('#notifworkedSlip').css({'display':'block'});
		});
		$('#yearworkedSlip').on('change',function(){
			
			if($(this).val()!='') {
			
				$('#btnworkedSlip').attr('class','bg-cyan-800 p-2 rounded-md text-white mx-1');
			
			}else{
			
				$('#btnworkedSlip').attr('class','<?=$bg_five_color?> p-2 rounded-md mx-1 toolInactive');
			}

		});
		$('#cancelnotifworkedSlip').click(function(){
			$('#notifworkedSlip').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

		$('#filesSlip').click(function(){
			$('#notiffilesSlip').css({'display':'block'});
		});
	
		$('#cancelnotiffilesSlip').click(function(){
			$('#notiffilesSlip').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

		$('#worked').click(function(){
			$('#notifworked').css({'display':'block'});
		});
	
		$('#cancelnotifworked').click(function(){
			$('#notifworked').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	

		$('#badgeGr').click(function(){
			$('#notifBadgeGr').css({'display':'block'});
		});
	
		$('#cancelnotifBadgeGr').click(function(){
			$('#notifBadgeGr').css({'display':'none'});
		});
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
	});

</script>

<style type="text/css">
	.input{
		padding: 0px 2px 0px 2px;
		font-size: 13px;
	}
</style>

<script type="text/javascript">
	// ===== ACCESSIBILITÉ & FERMETURE DES MODALS =====
	// Clic sur le fond + Échap ferment ; piège de focus clavier ; scroll de fond
	// bloqué ; role="dialog" + restauration du focus sur l'élément déclencheur.
	$(document).ready(function () {
		var $lastFocus = null;
		var SEL = '.modal-legacy:visible, #bigNotifProf:visible, #bigNotifCours:visible';

		function getFocusable($modal) {
			return $modal.find('a[href], button, input:not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])')
				.filter(':visible').filter(':not([disabled])');
		}
		function onOpen(modal) {
			var $m = $(modal);
			$lastFocus = $(document.activeElement);
			$m.attr({ 'role': 'dialog', 'aria-modal': 'true' });
			if (!$m.attr('aria-label')) {
				var t = $m.find('b, h2').first().text().trim();
				if (t) { $m.attr('aria-label', t); }
			}
			$('body').addClass('modal-open');
			var $f = getFocusable($m);
			if ($f.length) { $f[0].focus(); }
		}
		function onClose() {
			if ($(SEL).length === 0) { $('body').removeClass('modal-open'); }
			if ($lastFocus && $lastFocus.length) { try { $lastFocus[0].focus(); } catch (e) {} }
		}

		// Détecter ouverture/fermeture (les modals sont togglés via display)
		$('.modal-legacy, #bigNotifProf, #bigNotifCours').each(function () {
			var modal = this, wasShown = $(modal).is(':visible');
			new MutationObserver(function () {
				var shown = $(modal).is(':visible');
				if (shown === wasShown) { return; }
				wasShown = shown;
				if (shown) { onOpen(modal); } else { onClose(); }
			}).observe(modal, { attributes: true, attributeFilter: ['style', 'class'] });
		});

		// Clic sur l'overlay lui-même (et non sur son contenu) -> fermer
		$(document).on('click', '.modal-legacy', function (e) {
			if (e.target === this) { $(this).css('display', 'none'); }
		});

		$(document).on('keydown', function (e) {
			// Échap -> fermer
			if (e.key === 'Escape' || e.keyCode === 27) {
				$(SEL).css('display', 'none');
				return;
			}
			// Tab -> piège de focus dans le modal ouvert
			if (e.key === 'Tab' || e.keyCode === 9) {
				var $modal = $(SEL).first();
				if (!$modal.length) { return; }
				var $f = getFocusable($modal);
				if (!$f.length) { return; }
				var first = $f[0], last = $f[$f.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			}
		});
	});
</script>