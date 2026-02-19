<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Ajout étudiant</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

<form id="form-inscription" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
			
				<!-- Header bar with title and save button -->
				<div class="inscription-header mx-1 px-4 py-2 flex items-center justify-between flex-shrink-0">
					<div class="flex items-center gap-3">
						<div class="icon-wrapper">
							<i class="bi bi-person-plus-fill"></i>
						</div>
						<div>
							<h2 class="text-base font-semibold text-slate-100">Nouvelle inscription</h2>
							<p class="text-xs text-slate-400">Remplissez les informations de l'étudiant</p>
						</div>
					</div>
					<div class="flex items-center gap-3">
						<p><em class="text-red-400 text-sm" id="alert"></em></p>
					</div>
				</div>

				<!-- ===== MULTI-STEP PROGRESS BAR ===== -->
				<div class="msf-progress-wrapper mx-1 flex-shrink-0">
					<ul id="msf-progressbar">
						<li class="active" id="step-personal">
							<div class="step-icon"><i class="bi bi-person-badge"></i></div>
							<strong>Identité</strong>
						</li>
						<li id="step-contact">
							<div class="step-icon"><i class="bi bi-telephone"></i></div>
							<strong>Contact</strong>
						</li>
						<li id="step-study">
							<div class="step-icon"><i class="bi bi-mortarboard"></i></div>
							<strong>Études</strong>
						</li>
						<li id="step-family">
							<div class="step-icon"><i class="bi bi-people"></i></div>
							<strong>Famille</strong>
						</li>
						<li id="step-sponsor">
							<div class="step-icon"><i class="bi bi-person-heart"></i></div>
							<strong>Sponsor</strong>
						</li>
						<li id="step-finish">
							<div class="step-icon"><i class="bi bi-check2-circle"></i></div>
							<strong>Confirmer</strong>
						</li>
					</ul>
					<div class="msf-progress-track">
						<div class="msf-progress-fill" id="msf-progress-fill"></div>
					</div>
				</div>

				<div class="w-full px-1 flex-1 overflow-y-auto overflow-x-hidden py-2" style="scrollbar-width: thin; scrollbar-color: rgba(78, 158, 222, 0.3) transparent;">
						
						<div class="<?=$bg_one_color?> my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>

						<div class="w-full flex justify-center px-1 pb-4">
						<div class="w-full max-w-2xl">

							<!-- ===== STEP 1: Identité ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-person-badge"></i>
										<span>Infos personnelle</span>
									</div>
									<div class="step-counter">Étape 1 / 6</div>
								</div>
								<div class="card-body">
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom <span class="text-red-400">*</span></label>
											<input class="field-input requierd-1" type="text" name="student_nom" id="student_nom" placeholder="Entrez le nom">
										</div>
										<div class="field-group">
											<label class="field-label">Prénom</label>
											<input class="field-input" type="text" name="student_prenom" id="student_prenom" placeholder="Entrez le prénom">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Date de naissance <span class="text-red-400">*</span></label>
											<input class="field-input requierd-2" type="date" name="dateNaissance">
										</div>
										<div class="field-group">
											<label class="field-label">Lieu de naissance <span class="text-red-400">*</span></label>
											<input class="field-input requierd-3" type="text" name="lieuNaissance" placeholder="Lieu de naissance">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">CIN</label>
											<input class="field-input" type="text" name="num_cin" placeholder="Numéro CIN">
										</div>
										<div class="field-group">
											<label class="field-label">Date de délivrance</label>
											<input class="field-input" type="date" name="cin_date_delivre">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">CIN région</label>
											<select class="field-input" name="cin_region">
												<option value="">Sélectionner...</option>
								<?php 
								$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
								while ($showR = $findRegion->fetch()) {
								 ?>	
								 				<option value="<?=$showR['id']?>"><?=$showR['region']?></option>
								 <?php 
								}
								 ?>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Genre</label>
											<select class="field-input" name="sex">
												<option value="1">Masculin</option>
												<option value="0">Féminin</option>
											</select>
										</div>
									</div>
									<div class="field-group">
										<label class="field-label">Nationalité <span class="text-red-400">*</span></label>
										<input class="field-input requierd-4" type="text" name="nationalite" placeholder="Nationalité">
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<div></div>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 2: Contact & Photo ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-telephone"></i>
										<span>Infos du contact, photos</span>
									</div>
									<div class="step-counter">Étape 2 / 6</div>
								</div>
								<div class="card-body">
									<div class="flex gap-4">
										<div class="flex-1">
											<div class="field-group">
												<label class="field-label">Téléphone</label>
												<input class="field-input" type="text" name="student_tel" placeholder="034 00 000 00">
											</div>
											<div class="field-group">
												<label class="field-label">Adresse mail</label>
												<input class="field-input" type="email" name="student_email" id="student_email" placeholder="email@example.com">
											</div>
											<div class="field-group">
												<label class="field-label">Pays d'origine <span class="text-red-400">*</span></label>
												<input class="field-input requierd-5" type="text" name="pays_origine" placeholder="Madagascar">
											</div>
											<div class="field-group">
												<label class="field-label">Région <span class="text-red-400">*</span></label>
												<select class="field-input requierd-6" name="student_region">
													<option value="">Sélectionner...</option>
						<?php 
						$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
						while ($showR = $findRegion->fetch()) {
						 ?>	
						 					<option value="<?=$showR['id']?>"><?=$showR['region']?></option>
						 <?php 
						}
						 ?>
												</select>
											</div>
											<div class="field-group">
												<label class="field-label">Adresse actuel <span class="text-red-400">*</span></label>
												<input class="field-input requierd-7" type="text" name="student_adresse" placeholder="Adresse complète">
											</div>
										</div>
										<div class="w-28">
											<label class="field-label">Photo</label>
											<label for="student_images" class="photo-upload-box">
												<i class="bi bi-camera text-3xl text-slate-500"></i>
												<span class="text-xs text-slate-500 mt-1" id="photoNote">Cliquer pour ajouter</span>
											</label>
											<input type="file" accept="image/*" name="image_student" id="student_images" class="hidden">
											<canvas id="convertCanvasCreate" style="display:none;"></canvas>
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 3: Études ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-mortarboard"></i>
										<span>Infos d'étude</span>
									</div>
									<div class="step-counter">Étape 3 / 6</div>
								</div>
								<div class="card-body">
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Matricule</label>
											<div class="matricule-display" id="student_id">
												<i class="bi bi-lock-fill text-cyan-400"></i>
												<span>00000</span>
											</div>
											<input type="text" name="student_id" id="student_id_form" class="hidden">
										</div>
										<div class="field-group">
											<label class="field-label">Entrée du</label>
											<select class="field-input" name="semestre">
												<option value="1">Premier semestre</option>
												<option value="2">Deuxième semestre</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Mention <span class="text-red-400">*</span></label>
											<select class="field-input requierd-8" name="etude_envisage" id="etude_envisage">
												<option value="">Sélectionner...</option>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>	
												<option value="<?=$showSignMention['filiere_sigle']?>"><?=$showSignMention['filiere_description']?></option>
 <?php 
}
 ?>												
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Parcours <span class="text-red-400">*</span></label>
											<div id="etude_option">
												<select id="firstEtd" class="field-input requierd-9" name="etude_option">
													<option value="">Sélectionner...</option>
												</select>
											</div>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Niveau</label>
											<select class="field-input" name="annee_etude" id="annee_etude">
												<option value="0">Remise à niveau</option>
												<option value="1" selected>Licence 1</option>
												<option value="2">Licence 2</option>
												<option value="3">Licence 3</option>
												<option value="4">Master 1</option>
												<option value="5">Master 2</option>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Année universitaire <span class="text-red-400">*</span></label>
											<select class="field-input requierd-10" name="annee_scolaire">
						<?php
						$y = date('Y');
						for ($i=0; $i <= 7; $i++) { 
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
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Status</label>
											<select class="field-input" name="status">
												<option>Externe</option>
												<option>Interne</option>
												<option>Bungalow</option>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Ancien étudiant</label>
											<select class="field-input" name="new_student">
												<option value="1">Non</option>
												<option value="0">Oui</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group obtention_Bacc">
											<label class="field-label">Année d'obtention Bacc</label>
											<input class="field-input" type="date" name="obtention_bacc">
										</div>
										<div class="field-group obtention_Bacc">
											<label class="field-label">Série du Bacc</label>
											<select class="field-input" name="serie_bacc">
												<option>A1</option>
												<option>A2</option>
												<option>BTP</option>
												<option>C</option>
												<option>D</option>
												<option>Electronique</option>
												<option>G1</option>
												<option>G2</option>
												<option>G3</option>
												<option>L</option>
												<option>S</option>
												<option>OSE</option>
												<option>Technique Ouvrage Bois</option>
												<option>Technique Ouvrage Métalique</option>
												<option>Technique Maintenance Automobile</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3 diplome_preced hidden">
										<div class="field-group">
											<label class="field-label">Diplôme précédent</label>
											<input class="field-input" type="text" name="diplome_preced">
										</div>
										<div class="field-group">
											<label class="field-label">Date d'obtention</label>
											<input class="field-input" type="date" name="date_obtent_diplome_preced">
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 4: Parents ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-people"></i>
										<span>Infos parentale</span>
									</div>
									<div class="step-counter">Étape 4 / 6</div>
								</div>
								<div class="card-body">
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom du père</label>
											<input class="field-input" type="text" name="father_name" placeholder="Nom complet">
										</div>
										<div class="field-group">
											<label class="field-label">Sa profession</label>
											<input class="field-input" type="text" name="father_prof" placeholder="Profession">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom de la mère</label>
											<input class="field-input" type="text" name="mother_name" placeholder="Nom complet">
										</div>
										<div class="field-group">
											<label class="field-label">Sa profession</label>
											<input class="field-input" type="text" name="mother_prof" placeholder="Profession">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Téléphone</label>
											<input class="field-input" type="text" name="parent_tel" placeholder="034 00 000 00">
										</div>
										<div class="field-group">
											<label class="field-label">Adresse</label>
											<input class="field-input" type="text" name="parent_adresse" placeholder="Adresse des parents">
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 5: Sponsor & Situation familiale ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-person-heart"></i>
										<span>Sponsor & Situation familiale</span>
									</div>
									<div class="step-counter">Étape 5 / 6</div>
								</div>
								<div class="card-body">
									<div class="msf-section-label"><i class="bi bi-shield-check"></i> Garant financier</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nom du sponsor</label>
											<input class="field-input" type="text" name="sponsor_nom" placeholder="Nom">
										</div>
										<div class="field-group">
											<label class="field-label">Prénom</label>
											<input class="field-input" type="text" name="sponsor_prenom" placeholder="Prénom">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Téléphone</label>
											<input class="field-input" type="text" name="sponsor_tel" placeholder="034 00 000 00">
										</div>
										<div class="field-group">
											<label class="field-label">Adresse</label>
											<input class="field-input" type="text" name="sponsor_adresse" placeholder="Adresse du sponsor">
										</div>
									</div>

									<div class="msf-divider"></div>

									<div class="msf-section-label"><i class="bi bi-house-heart"></i> Situation familiale & autres</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">État civil</label>
											<select class="field-input" name="situationf">
												<option>Célibataire</option>
												<option>Marié</option>
											</select>
										</div>
										<div class="field-group">
											<label class="field-label">Nom du/de conjoint(e)</label>
											<input class="field-input" type="text" name="nom_conjoint" placeholder="Nom du conjoint">
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Nombre d'enfant</label>
											<input class="field-input" type="number" name="nb_enfant" value="0" min="0">
										</div>
										<div class="field-group">
											<label class="field-label">Réligion</label>
											<select class="field-input" name="religion">
												<option>Adventiste</option>
												<option>FLM</option>
												<option>Apokalypsy</option>
												<option>Catholique</option>
												<option>FJKM</option>
												<option>Lutherien</option>
												<option>Musulman</option>
												<option>Shyn</option>
												<option>Jesosy Mamonjy</option>
												<option>Autre...</option>
											</select>
										</div>
									</div>
									<div class="grid grid-cols-2 gap-3">
										<div class="field-group">
											<label class="field-label">Numéro visa</label>
											<input class="field-input" type="text" name="num_visa" placeholder="Numéro visa">
										</div>
										<div class="field-group">
											<label class="field-label">Abonné au CAF</label>
											<select class="field-input" name="abonment">
												<option value="0">Non</option>
												<option value="1">Oui</option>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button type="button" class="msf-btn msf-btn-next">Suivant <i class="bi bi-arrow-right"></i></button>
							</div>
							</fieldset>

							<!-- ===== STEP 6: Confirmation ===== -->
							<fieldset class="msf-fieldset">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-check2-circle"></i>
										<span>Confirmation</span>
									</div>
									<div class="step-counter">Étape 6 / 6</div>
								</div>
								<div class="card-body">
									<div class="msf-summary">
										<div class="msf-summary-icon">
											<i class="bi bi-clipboard-check"></i>
										</div>
										<h3>Vérifiez les informations</h3>
										<p>Relisez les données saisies avant de valider l'inscription.</p>

										<div class="msf-recap" id="msf-recap">
											<!-- Filled by JS -->
										</div>
									</div>
								</div>
							</div>
							<div class="msf-nav">
								<button type="button" class="msf-btn msf-btn-prev"><i class="bi bi-arrow-left"></i> Précédent</button>
								<button class="msf-btn msf-btn-submit" id="btn-inscription" type="button">
									<i class="bi bi-check2-circle"></i> Enregistrer l'étudiant
								</button>
							</div>
							</fieldset>

						</div>
						</div>

				</div>
</form>					
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>
<style type="text/css">
	/* ===== SHADCN-INSPIRED FORM STYLES ===== */
	.inscription-header {
		background: rgba(15, 23, 42, 0.8);
		backdrop-filter: blur(8px);
		border-bottom: 1px solid rgba(51, 65, 85, 0.5);
	}
	
	.inscription-header .icon-wrapper {
		width: 40px;
		height: 40px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(56, 189, 248, 0.1));
		border-radius: 10px;
		border: 1px solid rgba(56, 189, 248, 0.3);
	}
	
	.inscription-header .icon-wrapper i {
		font-size: 18px;
		color: #38bdf8;
	}
	
	.form-card {
		background: rgba(15, 23, 42, 0.6);
		backdrop-filter: blur(12px);
		border: 1px solid rgba(51, 65, 85, 0.5);
		border-radius: 12px;
		overflow: hidden;
		transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	.form-card:hover {
		border-color: rgba(56, 189, 248, 0.4);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(56, 189, 248, 0.1);
	}
	
	.form-card .card-header {
		padding: 14px 16px;
		border-bottom: 1px solid rgba(51, 65, 85, 0.5);
		background: rgba(15, 23, 42, 0.4);
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	
	.form-card .card-title {
		display: flex;
		align-items: center;
		gap: 10px;
		font-size: 14px;
		font-weight: 600;
		color: #f1f5f9;
	}
	
	.form-card .card-title i {
		color: #38bdf8;
		font-size: 16px;
	}
	
	.form-card .card-body {
		padding: 16px;
	}

	.step-counter {
		font-size: 12px;
		color: #64748b;
		font-weight: 500;
	}
	
	.field-group {
		margin-bottom: 12px;
	}
	
	.field-label {
		display: block;
		font-size: 11px;
		font-weight: 500;
		color: #94a3b8;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		margin-bottom: 6px;
	}
	
	.field-input {
		width: 100%;
		padding: 8px 12px;
		background: rgba(15, 23, 42, 0.8);
		border: 1px solid rgba(51, 65, 85, 0.6);
		border-radius: 8px;
		color: #e2e8f0;
		font-size: 13px;
		transition: all 0.15s ease;
	}
	
	.field-input:focus {
		outline: none;
		border-color: #38bdf8;
		box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
	}
	
	.field-input::placeholder {
		color: #64748b;
	}
	
	select.field-input {
		cursor: pointer;
	}
	
	select.field-input option {
		background: #0f172a;
		color: #e2e8f0;
	}
	
	.matricule-display {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		background: rgba(15, 23, 42, 0.8);
		border: 1px solid rgba(51, 65, 85, 0.6);
		border-radius: 8px;
		color: #e2e8f0;
		font-size: 14px;
		font-weight: 500;
	}
	
	.photo-upload-box {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 100px;
		background: rgba(15, 23, 42, 0.8);
		border: 2px dashed rgba(51, 65, 85, 0.6);
		border-radius: 10px;
		cursor: pointer;
		transition: all 0.2s ease;
		margin-top: 4px;
	}
	
	.photo-upload-box:hover {
		border-color: #38bdf8;
		background: rgba(56, 189, 248, 0.05);
	}
	
	/* Error states */
	.field-input.error {
		border-color: #ef4444;
		background: rgba(239, 68, 68, 0.1);
	}

	/* ===== MULTI-STEP PROGRESS BAR ===== */
	.msf-progress-wrapper {
		padding: 20px 24px 12px;
		background: rgba(15, 23, 42, 0.5);
		border-bottom: 1px solid rgba(51, 65, 85, 0.3);
	}

	#msf-progressbar {
		display: flex;
		justify-content: space-between;
		margin: 0 0 16px 0;
		padding: 0;
		position: relative;
		z-index: 1;
		list-style: none;
	}

	#msf-progressbar li {
		display: flex;
		flex-direction: column;
		align-items: center;
		flex: 1;
		position: relative;
		font-size: 11px;
		font-weight: 500;
		color: #475569;
		transition: color 0.4s ease;
	}

	#msf-progressbar li strong {
		margin-top: 8px;
		white-space: nowrap;
	}

	#msf-progressbar li .step-icon {
		width: 42px;
		height: 42px;
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 18px;
		background: rgba(15, 23, 42, 0.8);
		border: 2px solid rgba(51, 65, 85, 0.5);
		color: #475569;
		transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
		z-index: 2;
	}

	#msf-progressbar li::after {
		content: '';
		position: absolute;
		top: 21px;
		left: 50%;
		width: 100%;
		height: 2px;
		background: rgba(51, 65, 85, 0.4);
		z-index: 0;
	}

	#msf-progressbar li:last-child::after {
		display: none;
	}

	#msf-progressbar li.active {
		color: #38bdf8;
	}

	#msf-progressbar li.active .step-icon {
		background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(56, 189, 248, 0.1));
		border-color: #0ea5e9;
		color: #38bdf8;
		box-shadow: 0 0 20px rgba(14, 165, 233, 0.25);
	}

	#msf-progressbar li.done {
		color: #34d399;
	}

	#msf-progressbar li.done .step-icon {
		background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(52, 211, 153, 0.1));
		border-color: #10b981;
		color: #34d399;
	}

	#msf-progressbar li.done::after {
		background: #10b981;
	}

	.msf-progress-track {
		width: 100%;
		height: 4px;
		background: rgba(51, 65, 85, 0.4);
		border-radius: 4px;
		overflow: hidden;
	}

	.msf-progress-fill {
		height: 100%;
		width: 16.66%;
		background: linear-gradient(90deg, #0ea5e9, #38bdf8);
		border-radius: 4px;
		transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 0 0 8px rgba(14, 165, 233, 0.4);
	}

	/* ===== FIELDSET STEPS ===== */
	.msf-fieldset {
		border: none;
		padding: 0;
		margin: 0;
		min-width: 0;
	}

	.msf-fieldset:not(:first-of-type) {
		display: none;
	}

	/* ===== NAVIGATION BUTTONS ===== */
	.msf-nav {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-top: 16px;
		padding: 0 4px;
	}

	.msf-btn {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 10px 24px;
		font-size: 13px;
		font-weight: 600;
		border-radius: 10px;
		border: none;
		cursor: pointer;
		transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
	}

	.msf-btn-next {
		background: linear-gradient(135deg, #0ea5e9, #0284c7);
		color: white;
		box-shadow: 0 2px 10px rgba(14, 165, 233, 0.3);
	}

	.msf-btn-next:hover {
		background: linear-gradient(135deg, #38bdf8, #0ea5e9);
		transform: translateY(-1px);
		box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4);
	}

	.msf-btn-prev {
		background: rgba(51, 65, 85, 0.5);
		color: #94a3b8;
		border: 1px solid rgba(51, 65, 85, 0.6);
	}

	.msf-btn-prev:hover {
		background: rgba(51, 65, 85, 0.8);
		color: #e2e8f0;
		transform: translateY(-1px);
	}

	.msf-btn-submit {
		background: linear-gradient(135deg, #10b981, #059669);
		color: white;
		box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);
		padding: 12px 32px;
		font-size: 14px;
	}

	.msf-btn-submit:hover {
		background: linear-gradient(135deg, #34d399, #10b981);
		transform: translateY(-1px);
		box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
	}

	/* ===== SECTION LABELS & DIVIDERS ===== */
	.msf-section-label {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 13px;
		font-weight: 600;
		color: #38bdf8;
		margin-bottom: 12px;
		padding-bottom: 8px;
		border-bottom: 1px solid rgba(56, 189, 248, 0.15);
	}

	.msf-section-label i {
		font-size: 15px;
	}

	.msf-divider {
		height: 1px;
		background: linear-gradient(90deg, transparent, rgba(51, 65, 85, 0.5), transparent);
		margin: 20px 0;
	}

	/* ===== SUMMARY / CONFIRMATION STEP ===== */
	.msf-summary {
		text-align: center;
		padding: 16px 0;
	}

	.msf-summary-icon {
		width: 64px;
		height: 64px;
		border-radius: 16px;
		background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(56, 189, 248, 0.08));
		border: 2px solid rgba(14, 165, 233, 0.3);
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 auto 16px;
		font-size: 28px;
		color: #38bdf8;
	}

	.msf-summary h3 {
		font-size: 18px;
		font-weight: 700;
		color: #f1f5f9;
		margin: 0 0 6px;
	}

	.msf-summary p {
		font-size: 13px;
		color: #64748b;
		margin: 0 0 24px;
	}

	.msf-recap {
		text-align: left;
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 8px;
	}

	.msf-recap-item {
		padding: 8px 12px;
		background: rgba(15, 23, 42, 0.6);
		border: 1px solid rgba(51, 65, 85, 0.4);
		border-radius: 8px;
	}

	.msf-recap-item .recap-label {
		font-size: 10px;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: #64748b;
		margin-bottom: 2px;
	}

	.msf-recap-item .recap-value {
		font-size: 13px;
		color: #e2e8f0;
		font-weight: 500;
		word-break: break-word;
	}

	.msf-recap-item .recap-value.highlight {
		color: #38bdf8;
	}

	/* ===== ANIMATION ===== */
	.msf-fieldset {
		animation: msfSlideIn 0.4s ease;
	}

	@keyframes msfSlideIn {
		from { opacity: 0; transform: translateX(30px); }
		to { opacity: 1; transform: translateX(0); }
	}

	@keyframes msfSlideOut {
		from { opacity: 1; transform: translateX(0); }
		to { opacity: 0; transform: translateX(-30px); }
	}

	/* ===== LIGHT MODE ===== */
	[data-theme="light"] .inscription-header {
		background: rgba(255, 255, 255, 0.9);
		border-bottom-color: rgba(203, 213, 225, 0.8);
	}
	
	[data-theme="light"] .inscription-header h2 {
		color: #1e293b !important;
	}
	
	[data-theme="light"] .inscription-header p {
		color: #64748b !important;
	}
	
	[data-theme="light"] .form-card {
		background: rgba(255, 255, 255, 0.9);
		border-color: rgba(203, 213, 225, 0.8);
	}
	
	[data-theme="light"] .form-card:hover {
		border-color: rgba(14, 165, 233, 0.5);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
	}
	
	[data-theme="light"] .form-card .card-header {
		background: rgba(241, 245, 249, 0.8);
		border-bottom-color: rgba(203, 213, 225, 0.6);
	}
	
	[data-theme="light"] .form-card .card-title {
		color: #1e293b;
	}
	
	[data-theme="light"] .field-label {
		color: #64748b;
	}
	
	[data-theme="light"] .field-input {
		background: #ffffff;
		border-color: #e2e8f0;
		color: #1e293b;
	}
	
	[data-theme="light"] .field-input::placeholder {
		color: #94a3b8;
	}
	
	[data-theme="light"] .matricule-display {
		background: #f8fafc;
		border-color: #e2e8f0;
		color: #1e293b;
	}
	
	[data-theme="light"] .photo-upload-box {
		background: #f8fafc;
		border-color: #cbd5e1;
	}
	
	[data-theme="light"] .photo-upload-box:hover {
		border-color: #0ea5e9;
		background: rgba(14, 165, 233, 0.05);
	}

	[data-theme="light"] .msf-progress-wrapper {
		background: rgba(241, 245, 249, 0.8);
		border-bottom-color: rgba(203, 213, 225, 0.6);
	}

	[data-theme="light"] #msf-progressbar li .step-icon {
		background: #f8fafc;
		border-color: #e2e8f0;
	}

	[data-theme="light"] .msf-btn-prev {
		background: #f1f5f9;
		border-color: #e2e8f0;
		color: #475569;
	}

	[data-theme="light"] .msf-btn-prev:hover {
		background: #e2e8f0;
		color: #1e293b;
	}

	[data-theme="light"] .msf-recap-item {
		background: #f8fafc;
		border-color: #e2e8f0;
	}

	[data-theme="light"] .msf-recap-item .recap-value {
		color: #1e293b;
	}

	[data-theme="light"] .step-counter {
		color: #94a3b8;
	}

	/* Scrollbar styling */
	.overflow-y-auto::-webkit-scrollbar {
		width: 6px;
	}
	
	.overflow-y-auto::-webkit-scrollbar-track {
		background: transparent;
	}
	
	.overflow-y-auto::-webkit-scrollbar-thumb {
		background: rgba(78, 158, 222, 0.3);
		border-radius: 3px;
	}
	
	.overflow-y-auto::-webkit-scrollbar-thumb:hover {
		background: rgba(78, 158, 222, 0.5);
	}

	/* ===== RESPONSIVE ===== */
	@media (max-width: 640px) {
		#msf-progressbar li strong { display: none; }
		#msf-progressbar li .step-icon { width: 36px; height: 36px; font-size: 15px; border-radius: 10px; }
		#msf-progressbar li::after { top: 18px; }
		.msf-progress-wrapper { padding: 14px 12px 8px; }
		.msf-recap { grid-template-columns: 1fr; }
		.msf-btn { padding: 9px 16px; font-size: 12px; }
		.msf-btn-submit { padding: 10px 20px; font-size: 13px; }
	}
</style>
<script type="text/javascript">
	$(document).ready(function(){

		// ============================================================
		// Multi-Step Form Logic
		// ============================================================
		var currentStep = 1;
		var totalSteps = $(".msf-fieldset").length;
		var progressItems = $("#msf-progressbar li");

		updateProgressBar(currentStep);

		// NEXT button
		$(".msf-btn-next").click(function(){
			var currentFieldset = $(this).closest('.msf-fieldset');
			var nextFieldset = currentFieldset.next('.msf-fieldset');

			if (nextFieldset.length === 0) return;

			// Mark current step as done
			progressItems.eq(currentStep - 1).removeClass('active').addClass('done');
			// Mark next step as active
			progressItems.eq(currentStep).addClass('active');

			// Animate transition
			currentFieldset.css('animation', 'msfSlideOut 0.3s ease forwards');
			setTimeout(function(){
				currentFieldset.hide().css('animation', '');
				nextFieldset.show().css('animation', 'msfSlideIn 0.4s ease');
				
				// Scroll to top of form area
				currentFieldset.closest('.overflow-y-auto').scrollTop(0);

				// If last step, build recap
				if (currentStep + 1 === totalSteps) {
					buildRecap();
				}
			}, 280);

			currentStep++;
			updateProgressBar(currentStep);
		});

		// PREVIOUS button
		$(".msf-btn-prev").click(function(){
			var currentFieldset = $(this).closest('.msf-fieldset');
			var prevFieldset = currentFieldset.prev('.msf-fieldset');

			if (prevFieldset.length === 0) return;

			// Remove active/done from current, restore active on previous
			progressItems.eq(currentStep - 1).removeClass('active done');
			progressItems.eq(currentStep - 2).removeClass('done').addClass('active');

			currentFieldset.css('animation', 'msfSlideOut 0.3s ease forwards');
			setTimeout(function(){
				currentFieldset.hide().css('animation', '');
				prevFieldset.show().css('animation', 'msfSlideIn 0.4s ease');
				currentFieldset.closest('.overflow-y-auto').scrollTop(0);
			}, 280);

			currentStep--;
			updateProgressBar(currentStep);
		});

		function updateProgressBar(step) {
			var percent = (step / totalSteps) * 100;
			$("#msf-progress-fill").css("width", percent + "%");
		}

		function buildRecap() {
			var recapFields = [
				{ label: 'Nom', name: 'student_nom', hl: true },
				{ label: 'Prénom', name: 'student_prenom', hl: true },
				{ label: 'Date de naissance', name: 'dateNaissance' },
				{ label: 'Lieu de naissance', name: 'lieuNaissance' },
				{ label: 'Nationalité', name: 'nationalite' },
				{ label: 'Téléphone', name: 'student_tel' },
				{ label: 'Email', name: 'student_email' },
				{ label: 'Adresse', name: 'student_adresse' },
				{ label: 'Matricule', name: 'student_id', fromId: 'student_id_form', hl: true },
				{ label: 'Mention', name: 'etude_envisage', selectText: true, hl: true },
				{ label: 'Parcours', name: 'etude_option', selectText: true },
				{ label: 'Niveau', name: 'annee_etude', selectText: true },
				{ label: 'Année scolaire', name: 'annee_scolaire', selectText: true },
				{ label: 'Père', name: 'father_name' },
				{ label: 'Mère', name: 'mother_name' },
				{ label: 'Sponsor', name: 'sponsor_nom' },
			];

			var html = '';
			recapFields.forEach(function(f) {
				var val = '';
				if (f.fromId) {
					val = $('#' + f.fromId).val();
				} else if (f.selectText) {
					var sel = $('[name="' + f.name + '"]');
					val = sel.is('select') ? sel.find('option:selected').text() : sel.val();
				} else {
					val = $('[name="' + f.name + '"]').val();
				}
				val = val || '—';
				var cls = f.hl ? ' highlight' : '';
				html += '<div class="msf-recap-item"><div class="recap-label">' + f.label + '</div><div class="recap-value' + cls + '">' + $('<span>').text(val).html() + '</div></div>';
			});

			$('#msf-recap').html(html);
		}

		// ============================================================
		// Existing Logic — Mention / Parcours / Matricule
		// ============================================================
		$('#etude_envisage').on('change', function(){
			var mention = $(this).val();
			var annee_etude = $('#annee_etude').val();

			$.ajax({
				url:APP_BASE+"/src/services/parcours.live",
				method:"POST",
				data:{mention:mention},

				success:function(data){
					$("#etude_option").html(data);
				}
			});

			$.ajax({
				url:APP_BASE+"/src/services/matricule.live",
				method:"POST",
				data:{mention:mention, annee_etude:annee_etude},

				success:function(data){
					$("#student_id").html('<i class="bi-lock-fill"></i>'+data);
					$("#student_id_form").val(data);
				}
			});
		});
		$('#annee_etude').on('change', function(){
			var mention = $('#etude_envisage').val();
			var annee_etude = $(this).val();

			$.ajax({
				url:APP_BASE+"/src/services/matricule.live",
				method:"POST",
				data:{mention:mention, annee_etude:annee_etude},

				success:function(data){
					$("#student_id").html('<i class="bi-lock-fill"></i>'+data);
					$("#student_id_form").val(data);
				}
			});
			
			if(annee_etude <= 3) {
				$('.obtention_Bacc').css({'display':'block'});
				$('.diplome_preced').css({'display':'none'});
			}else if(annee_etude > 3) {
				$('.obtention_Bacc').css({'display':'none'});
				$('.diplome_preced').css({'display':'block'});
			}

		});

		// ============================================================
		// Image conversion
		// ============================================================
		$('#student_images').on('change', function(){
			var fileInput = this;
			var file = fileInput.files[0];
			if(!file) return;

			var reader = new FileReader();
			reader.onload = function(e) {
				var img = new Image();
				img.onload = function() {
					var canvas = document.getElementById('convertCanvasCreate');
					var maxSize = 1200;
					var w = img.width, h = img.height;
					if (w > maxSize || h > maxSize) {
						if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
						else { w = Math.round(w * maxSize / h); h = maxSize; }
					}
					canvas.width = w;
					canvas.height = h;
					var ctx = canvas.getContext('2d');
					ctx.drawImage(img, 0, 0, w, h);
					canvas.toBlob(function(blob) {
						var baseName = file.name.replace(/\.[^.]+$/, '');
						var convertedFile = new File([blob], baseName + '.jpg', { type: 'image/jpeg' });
						var dataTransfer = new DataTransfer();
						dataTransfer.items.add(convertedFile);
						fileInput.files = dataTransfer.files;
						$('#photoNote').text('Image prête (' + w + 'x' + h + ')');
					}, 'image/jpeg', 0.9);
				};
				img.onerror = function() {
					$('#photoNote').text('Format non supporté');
					fileInput.value = '';
				};
				img.src = e.target.result;
			};
			reader.readAsDataURL(file);
		});

		// ============================================================
		// Validation & Submit
		// ============================================================
		$('#btn-inscription').click(function() {
			
			var r_1 = $('.requierd-1').val();
			var r_2 = $('.requierd-2').val();
			var r_3 = $('.requierd-3').val();
			var r_4 = $('.requierd-4').val();
			var r_5 = $('.requierd-5').val();
			var r_6 = $('.requierd-6').val();
			var r_7 = $('.requierd-7').val();
			var r_8 = $('.requierd-8').val();
			var r_9 = $('.requierd-9').val();
			var r_10 = $('.requierd-10').val();
				
			if(r_1 =="" || r_2 =="" || r_3 =="" || r_4 =="" || r_5 =="" || r_6 =="" || r_7 =="" || r_8 =="" || r_9 =="" || r_10 =="") {			
				
				$('#alert').text('Ces zones sont obligatoires !');

				// Reset error styles
				$('.requierd-1,.requierd-2,.requierd-3,.requierd-4,.requierd-5,.requierd-6,.requierd-7,.requierd-8,.requierd-9,.requierd-10').each(function(){
					if($(this).val() === '') {
						$(this).css({'border':'1px solid #ef4444','background':'rgba(239,68,68,0.15)'});
					}
				});

				// Find which step has the first missing required field and go there
				var requiredFields = [
					{ el: '.requierd-1', step: 1 }, { el: '.requierd-2', step: 1 },
					{ el: '.requierd-3', step: 1 }, { el: '.requierd-4', step: 1 },
					{ el: '.requierd-5', step: 2 }, { el: '.requierd-6', step: 2 },
					{ el: '.requierd-7', step: 2 }, { el: '.requierd-8', step: 3 },
					{ el: '.requierd-9', step: 3 }, { el: '.requierd-10', step: 3 }
				];

				for (var i = 0; i < requiredFields.length; i++) {
					if ($(requiredFields[i].el).val() === '') {
						goToStep(requiredFields[i].step);
						break;
					}
				}
			
			}else{

				$('#form-inscription').attr('action', APP_BASE+'/app/.student/addStd?rg_id=<?=$rg_id?>');
				$('#form-inscription').attr('method','post');
				$(this).attr('types','submit');

			}
		});

		$('#btn-inscription').hover(function() {

			var r_1 = $('.requierd-1').val();
			var r_2 = $('.requierd-2').val();
			var r_3 = $('.requierd-3').val();
			var r_4 = $('.requierd-4').val();
			var r_5 = $('.requierd-5').val();
			var r_6 = $('.requierd-6').val();
			var r_7 = $('.requierd-7').val();
			var r_8 = $('.requierd-8').val();
			var r_9 = $('.requierd-9').val();
			var r_10 = $('.requierd-10').val();
			
			if(r_1 !="" && r_2 !="" && r_3 !="" && r_4 !="" && r_5 !="" && r_6 !="" && r_7 !="" && r_8 !="" && r_9 !="" && r_10 !="") {			
				$(this).attr('type','submit');
				$('#form-inscription').attr('action', APP_BASE+'/app/.student/addStd?rg_id=<?=$rg_id?>');
				$('#form-inscription').attr('method','post');
			}
		});

		// ============================================================
		// Go to specific step (for validation redirect)
		// ============================================================
		function goToStep(targetStep) {
			if (targetStep === currentStep) return;

			// Reset all progress
			progressItems.removeClass('active done');
			for (var i = 0; i < targetStep - 1; i++) {
				progressItems.eq(i).addClass('done');
			}
			progressItems.eq(targetStep - 1).addClass('active');

			// Show target fieldset
			$('.msf-fieldset').hide();
			$('.msf-fieldset').eq(targetStep - 1).show().css('animation', 'msfSlideIn 0.4s ease');

			currentStep = targetStep;
			updateProgressBar(currentStep);
		}

		// ============================================================
		// Auto-generate email
		// ============================================================
		$("#student_nom").keyup(function() {
			var name = $(this).val().toLowerCase();

			if($("#student_prenom").val() == ""){
				$("#student_email").val(name+'.uaz@zurcher.edu.mg');
			}else{
				var lastname = $('#student_prenom').val().substr(0,3).toLowerCase();
				$("#student_email").val(name+'.'+lastname+'@zurcher.edu.mg');
			}
		});

		$('#student_prenom').keyup(function() {
			var name = $('#student_nom').val().toLowerCase();
			
			if($(this).val() == ""){
				$("#student_email").val(name+'.uaz@zurcher.edu.mg');
			}else{
				var lastname = $(this).val().substr(0,3).toLowerCase();
				$("#student_email").val(name+'.'+lastname+'@zurcher.edu.mg');
			}
		});

		// Clear error style on focus
		$('.field-input').on('focus', function(){
			$(this).css({'border':'','background':''});
			$('#alert').text('');
		});
	});
</script>