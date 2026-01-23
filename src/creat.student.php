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
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>

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
						<button class="btn-save" id="btn-inscription" type="button">
							<i class="bi bi-check2-circle"></i>
							<span>Enregistrer</span>
						</button>
					</div>
				</div>

				<div class="w-full px-1 flex-1 overflow-y-auto overflow-x-hidden py-2" style="scrollbar-width: thin; scrollbar-color: rgba(78, 158, 222, 0.3) transparent;">
						
						<div class="<?=$bg_one_color?> my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>

						<div class="w-full grid gap-3 lg:grid-cols-2 xl:grid-cols-3 px-1 pb-4">
							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-person-badge"></i>
										<span>Infos personnelle</span>
									</div>
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

							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-telephone"></i>
										<span>Infos du contact, photos</span>
									</div>
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
												<span class="text-xs text-slate-500 mt-1">Cliquer pour ajouter</span>
											</label>
											<input type="file" accept=".jpg, .png" name="image_student" id="student_images" class="hidden">
										</div>
									</div>
								</div>
							</div>

							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-mortarboard"></i>
										<span>Infos d'étude</span>
									</div>
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

							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-people"></i>
										<span>Infos parentale</span>
									</div>
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

							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-person-heart"></i>
										<span>Infos du sponsor</span>
									</div>
								</div>
								<div class="card-body">
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
								</div>
							</div>

							<div class="form-card">
								<div class="card-header">
									<div class="card-title">
										<i class="bi bi-house-heart"></i>
										<span>Situation familiale, autres...</span>
									</div>
								</div>
								<div class="card-body">
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
	
	.btn-save {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 20px;
		background: linear-gradient(135deg, #0ea5e9, #0284c7);
		color: white;
		font-size: 13px;
		font-weight: 500;
		border-radius: 8px;
		border: none;
		cursor: pointer;
		transition: all 0.2s ease;
		box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
	}
	
	.btn-save:hover {
		background: linear-gradient(135deg, #38bdf8, #0ea5e9);
		transform: translateY(-1px);
		box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
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
	
	/* Light mode */
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

	/* Scrollbar styling for form area */
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
</style>
<script type="text/javascript">
	$(document).ready(function(){
		$('#etude_envisage').on('change', function(){
			var mention = $(this).val();
			var annee_etude = $('#annee_etude').val();

			$.ajax({
				url:"./services/parcours.live",
				method:"POST",
				data:{mention:mention},

				success:function(data){
					$("#etude_option").html(data);
				}
			});

			$.ajax({
				url:"./services/matricule.live",
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
				url:"./services/matricule.live",
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

				$('.requierd-1').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-2').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-3').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-4').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-5').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-6').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-7').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-8').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-9').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});			
				$('.requierd-10').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
			
			}else{

				$('#form-inscription').attr('action','../app/.student/addStd.php?rg_id=<?=$rg_id?>');
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
				$('#form-inscription').attr('action','../app/.student/addStd.php?rg_id=<?=$rg_id?>');
				$('#form-inscription').attr('method','post');
			}
		});

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
	});
</script>