<?php
// Helper function to format dates - returns empty string for invalid dates
function formatDateForInput($date) {
	if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
		return '';
	}
	return $date;
}
?>
<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<style>
	/* ===== TOAST NOTIFICATIONS ===== */
	.toast-container {
		position: fixed;
		top: 20px;
		right: 20px;
		z-index: 9999;
		display: flex;
		flex-direction: column;
		gap: 12px;
		pointer-events: none;
	}

	.toast {
		pointer-events: all;
		min-width: 320px;
		max-width: 420px;
		padding: 16px 20px;
		border-radius: 12px;
		backdrop-filter: blur(16px);
		box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05);
		display: flex;
		align-items: flex-start;
		gap: 14px;
		animation: toastSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
		position: relative;
		overflow: hidden;
	}

	.toast.toast-out {
		animation: toastSlideOut 0.3s cubic-bezier(0.4, 0, 1, 1) forwards;
	}

	@keyframes toastSlideIn {
		from { opacity: 0; transform: translateX(100%) scale(0.8); }
		to { opacity: 1; transform: translateX(0) scale(1); }
	}

	@keyframes toastSlideOut {
		from { opacity: 1; transform: translateX(0) scale(1); }
		to { opacity: 0; transform: translateX(100%) scale(0.8); }
	}

	.toast-icon {
		width: 24px;
		height: 24px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		font-size: 14px;
	}

	.toast-content {
		flex: 1;
		min-width: 0;
	}

	.toast-title {
		font-size: 14px;
		font-weight: 600;
		margin-bottom: 4px;
	}

	.toast-message {
		font-size: 13px;
		opacity: 0.85;
		line-height: 1.4;
	}

	.toast-close {
		background: none;
		border: none;
		color: inherit;
		opacity: 0.5;
		cursor: pointer;
		padding: 4px;
		margin: -4px;
		transition: opacity 0.2s;
		font-size: 18px;
	}

	.toast-close:hover {
		opacity: 1;
	}

	.toast-progress {
		position: absolute;
		bottom: 0;
		left: 0;
		height: 3px;
		border-radius: 0 0 12px 12px;
		animation: toastProgress linear forwards;
	}

	@keyframes toastProgress {
		from { width: 100%; }
		to { width: 0%; }
	}

	/* Toast types */
	.toast-success {
		background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
		color: white;
	}
	.toast-success .toast-icon { background: rgba(255, 255, 255, 0.2); }
	.toast-success .toast-progress { background: rgba(255, 255, 255, 0.4); }

	.toast-error {
		background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
		color: white;
	}
	.toast-error .toast-icon { background: rgba(255, 255, 255, 0.2); }
	.toast-error .toast-progress { background: rgba(255, 255, 255, 0.4); }

	.toast-warning {
		background: linear-gradient(135deg, rgba(245, 158, 11, 0.95), rgba(217, 119, 6, 0.95));
		color: white;
	}
	.toast-warning .toast-icon { background: rgba(255, 255, 255, 0.2); }
	.toast-warning .toast-progress { background: rgba(255, 255, 255, 0.4); }

	.toast-info {
		background: linear-gradient(135deg, rgba(14, 165, 233, 0.95), rgba(2, 132, 199, 0.95));
		color: white;
	}
	.toast-info .toast-icon { background: rgba(255, 255, 255, 0.2); }
	.toast-info .toast-progress { background: rgba(255, 255, 255, 0.4); }

	/* Light mode toasts */
	[data-theme="light"] .toast {
		box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
	}

	/* Saving overlay */
	.saving-overlay {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(15, 23, 42, 0.6);
		backdrop-filter: blur(4px);
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 9998;
		opacity: 0;
		visibility: hidden;
		transition: all 0.3s ease;
	}

	.saving-overlay.active {
		opacity: 1;
		visibility: visible;
	}

	.saving-spinner {
		width: 50px;
		height: 50px;
		border: 3px solid rgba(56, 189, 248, 0.2);
		border-top-color: #38bdf8;
		border-radius: 50%;
		animation: spin 0.8s linear infinite;
	}

	@keyframes spin {
		to { transform: rotate(360deg); }
	}

	/* ===== SHADCN-INSPIRED MODERN STYLES ===== */
	.info-card {
		background: rgba(15, 23, 42, 0.6);
		backdrop-filter: blur(12px);
		border: 1px solid rgba(51, 65, 85, 0.5);
		border-radius: 12px;
		padding: 20px;
		transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	.info-card:hover {
		border-color: rgba(56, 189, 248, 0.4);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(56, 189, 248, 0.1);
	}
	
	.card-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 20px;
		padding-bottom: 12px;
		border-bottom: 1px solid rgba(51, 65, 85, 0.5);
	}
	
	.card-title {
		font-size: 14px;
		font-weight: 600;
		color: #f1f5f9;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	
	.card-title i {
		color: #38bdf8;
		font-size: 16px;
	}
	
	.card-actions {
		display: flex;
		gap: 8px;
		align-items: center;
	}
	
	.btn-edit {
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 8px;
		background: rgba(51, 65, 85, 0.5);
		color: #94a3b8;
		transition: all 0.15s ease;
		border: 1px solid transparent;
	}
	
	.btn-edit:hover {
		background: rgba(56, 189, 248, 0.1);
		color: #38bdf8;
		border-color: rgba(56, 189, 248, 0.3);
	}
	
	.btn-primary {
		padding: 6px 14px;
		border-radius: 8px;
		background: linear-gradient(135deg, #0ea5e9, #0284c7);
		color: white;
		font-size: 12px;
		font-weight: 500;
		transition: all 0.15s ease;
		border: none;
		box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
	}
	
	.btn-primary:hover {
		background: linear-gradient(135deg, #38bdf8, #0ea5e9);
		transform: translateY(-1px);
		box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
	}
	
	.btn-secondary {
		padding: 6px 14px;
		border-radius: 8px;
		background: rgba(51, 65, 85, 0.6);
		color: #94a3b8;
		font-size: 12px;
		font-weight: 500;
		transition: all 0.15s ease;
		border: 1px solid rgba(71, 85, 105, 0.5);
	}
	
	.btn-secondary:hover {
		background: rgba(71, 85, 105, 0.6);
		color: #e2e8f0;
	}
	
	.field-group {
		margin-bottom: 16px;
	}
	
	.field-label {
		font-size: 11px;
		font-weight: 500;
		color: #64748b;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		margin-bottom: 4px;
		display: block;
	}
	
	.field-value {
		font-size: 13px;
		color: #e2e8f0;
		padding: 8px 0;
		border-bottom: 1px solid rgba(51, 65, 85, 0.3);
	}
	
	.field-value.locked {
		color: #94a3b8;
		display: flex;
		align-items: center;
		gap: 6px;
	}
	
	.field-value.locked i {
		font-size: 12px;
		color: #64748b;
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
		padding: 8px;
	}
	
	.grid-two-cols {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 24px;
	}
	
	@media (max-width: 640px) {
		.grid-two-cols {
			grid-template-columns: 1fr;
			gap: 16px;
		}
		
		.info-card {
			padding: 16px;
		}
	}
	
	/* Light mode */
	[data-theme="light"] .info-card {
		background: rgba(255, 255, 255, 0.9);
		border-color: rgba(203, 213, 225, 0.8);
	}
	
	[data-theme="light"] .info-card:hover {
		border-color: rgba(14, 165, 233, 0.5);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
	}
	
	[data-theme="light"] .card-header {
		border-bottom-color: rgba(203, 213, 225, 0.6);
	}
	
	[data-theme="light"] .card-title {
		color: #1e293b;
	}
	
	[data-theme="light"] .field-label {
		color: #64748b;
	}
	
	[data-theme="light"] .field-value {
		color: #1e293b;
		border-bottom-color: rgba(203, 213, 225, 0.5);
	}
	
	[data-theme="light"] .field-input {
		background: #ffffff;
		border-color: #e2e8f0;
		color: #1e293b;
	}
	
	[data-theme="light"] .btn-edit {
		background: rgba(241, 245, 249, 0.8);
		color: #64748b;
	}
	
	[data-theme="light"] .btn-edit:hover {
		background: rgba(14, 165, 233, 0.1);
		color: #0ea5e9;
	}
	
	[data-theme="light"] .btn-secondary {
		background: #f1f5f9;
		color: #475569;
		border-color: #e2e8f0;
	}
</style>

<form method="post" action="<?=$app_base?>/app/.student/updateStd?student_id=<?=$student_id?>&id=<?=$id?>&rg_id=<?=$rg_id?>&etude_envisage=<?=$etude_envisage?>" class="form-no-refrech" target="_blank">
<div class="w-full grid gap-4 grid-cols-1 lg:grid-cols-2 mt-3 p-3 overflow-auto" style="max-height: calc(100vh - 246px);">

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-person-badge"></i>
				<span>Informations personnelles</span>
			</div>
			<div class="card-actions">
				<a href="#" class="submitPers submitRedirect btn-primary hidden">Enregistrer</a>
				<a href="#" class="annulPers btn-secondary hidden">Annuler</a>
				<a href="#" id="editPers" class="btn-edit"><i class="bi-pencil"></i></a>	
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Nom</label>
					<p class="showPers field-value"><?=strtoupper($student_nom)?></p>
					<input id="firstPers" class="editPers field-input hidden" type="text" name="student_nom" value="<?=$student_nom?>">
				</div>

				<div class="field-group">
					<label class="field-label">Date de naissance</label>
					<p class="showPers field-value"><?=formatDateForInput($profil['dateNaissance'])?></p>
					<input class="editPers field-input hidden" type="date" name="dateNaissance" value="<?=formatDateForInput($profil['dateNaissance'])?>">
				</div>

				<div class="field-group">
					<label class="field-label">Genre</label>
					<p class="showPers field-value"><?php if($profil['sex'] == '0'){echo 'Féminin';}else{echo 'Masculin';}?></p>
					<select class="editPers field-input hidden" name="sex">
						<option value="1" <?php if($profil['sex'] == '1'){echo 'selected';}?>>Masculin</option>
						<option value="0" <?php if($profil['sex'] == '0'){echo 'selected';}?>>Féminin</option>
					</select>
				</div>

				<div class="field-group">
					<label class="field-label">CIN</label>
					<p class="showPers field-value"><?=$profil['num_cin']?></p>
					<input class="editPers field-input hidden" type="text" name="num_cin" value="<?=$profil['num_cin']?>">
				</div>

				<div class="field-group">
					<label class="field-label">CIN région</label>
					<p class="showPers field-value"><?php $findRegi = $dtb->query('SELECT * FROM region WHERE id ="'.$profil['cin_region'].'"'); $showRegi = $findRegi->fetch(); if(!empty($showRegi)){echo $showRegi['region'];}?></p>
					<select class="editPers field-input hidden" name="cin_region">
						<option value=""></option>
<?php 
$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
while ($showR = $findRegion->fetch()) {
 ?>	
 						<option value="<?=$showR['id']?>" <?php if($profil['cin_region'] == $showR['id'] OR $profil['cin_region'] == $showR['region']){echo 'selected';}?>><?=$showR['region']?></option>
 <?php 
}
 ?>
					</select>
				</div>
			</div>
			<div>
				<div class="field-group">
					<label class="field-label">Prénom</label>
					<p class="showPers field-value"><?=$student_prenom?></p>
					<input class="editPers field-input hidden" type="text" name="student_prenom" value="<?=$student_prenom?>">
				</div>

				<div class="field-group">
					<label class="field-label">Lieu de naissance</label>
					<p class="showPers field-value"><?=$profil['lieuNaissance']?></p>
					<input class="editPers field-input hidden" type="text" name="lieuNaissance" value="<?=$profil['lieuNaissance']?>">
				</div>

				<div class="field-group">
					<label class="field-label">Nationalité</label>
					<p class="showPers field-value"><?=$profil['nationalite']?></p>
					<input class="editPers field-input hidden" type="text" name="nationalite" value="<?=$profil['nationalite']?>">
				</div>

				<div class="field-group">
					<label class="field-label">Date de délivrance</label>
					<p class="showPers field-value"><?=formatDateForInput($profil['cin_date_delivre'])?></p>
					<input class="editPers field-input hidden" type="date" name="cin_date_delivre" value="<?=formatDateForInput($profil['cin_date_delivre'])?>">
				</div>

				<input type="hidden" name="session_id" value="">
			</div>
		</div>
	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-telephone"></i>
				<span>Informations de contact</span>
			</div>
			<div class="card-actions">
				<a href="#" class="submitContact submitRedirect btn-primary hidden">Enregistrer</a>
				<a href="#" class="annulContact btn-secondary hidden">Annuler</a>
				<a href="#" id="editContact" class="btn-edit"><i class="bi-pencil"></i></a>
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Téléphone</label>
					<p class="showContact field-value"><?=$profil['student_tel']?></p>
					<input id="firstContact" class="editContact field-input hidden" type="text" name="student_tel" value="<?=$profil['student_tel']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Adresse mail</label>
					<p class="showContact field-value"><?=$profil['student_email']?></p>
					<input class="editContact field-input hidden" type="text" name="student_email" value="<?=$profil['student_email']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Adresse d'origine</label>
					<p class="showContact field-value"><?=$profil['pays_origine']?></p>
					<input class="editContact field-input hidden" type="text" name="pays_origine" value="<?=$profil['pays_origine']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Région</label>
					<p class="showContact field-value"><?php $findRegi = $dtb->query('SELECT * FROM region WHERE id ="'.$profil['student_region'].'"'); $showRegi = $findRegi->fetch(); if (!empty($showRegi)) {echo $showRegi['region'];}?></p>
					<select class="editContact field-input hidden" name="student_region">
						<option value=""></option>
<?php 
$findRegion = $dtb->query('SELECT * FROM region ORDER BY region');
while ($showR = $findRegion->fetch()) {
 ?>	
 						<option value="<?=$showR['id']?>" <?php if($profil['student_region'] == $showR['id'] OR $profil['student_region'] == $showR['region']){echo 'selected';}?>><?=$showR['region']?></option>
 <?php 
}
 ?>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Adresse actuelle</label>
					<p class="showContact field-value"><?=$profil['student_adresse']?></p>
					<input class="editContact field-input hidden" type="text" name="student_adresse" value="<?=$profil['student_adresse']?>">
				</div>
			</div>
			<div>
				<?php 
					if ($level <= 3) {
$findBacc = $dtb->query('SELECT * FROM t_2024_bacc WHERE student_id = "'.$student_id.'"');
$showBacc = $findBacc->fetch();
				 ?>	
				<div class="field-group">
					<label class="field-label">Série du Bacc</label>
					<p class="showContact field-value"><?php if(!empty($showBacc)) {echo $showBacc['bacc_serie'];}?></p>
					<select class="editContact field-input hidden" name="serie_bacc">
						<option value=""></option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'A1') { echo 'selected';}}?>>A1</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'A2') { echo 'selected';}}?>>A2</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'BTP') { echo 'selected';}}?>>BTP</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'C') { echo 'selected';}}?>>C</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'D') { echo 'selected';}}?>>D</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'Electronique') { echo 'selected';}}?>>Electronique</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'G1') { echo 'selected';}}?>>G1</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'G2') { echo 'selected';}}?>>G2</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'G3') { echo 'selected';}}?>>G3</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'L') { echo 'selected';}}?>>L</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'S') { echo 'selected';}}?>>S</option>
						<option <?php if(!empty($showBacc)) {if($showBacc['bacc_serie'] == 'OSE') { echo 'selected';}}?>>OSE</option>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Année d'obtention Bacc</label>
					<p class="showContact field-value"><?php if(!empty($showBacc)) {echo formatDateForInput($showBacc['date_obtent']);}?></p>
					<input class="editContact field-input hidden" type="date" name="obtention_bacc" value="<?php if(!empty($showBacc)) { echo formatDateForInput($showBacc['date_obtent']); } ?>">
				</div>
				
				<?php 
					}elseif($level > 3){
$findDiplome = $dtb->query('SELECT * FROM t_2024_diplome_preced WHERE student_id = "'.$student_id.'"');
$showDiplome = $findDiplome->fetch();
				?>
				<div class="field-group">
					<label class="field-label">Diplôme précédent</label>
					<p class="showContact field-value"><?php if(!empty($showDiplome)) {echo $showDiplome['diplome_name'];}?></p>
					<input class="editContact field-input hidden" type="text" name="diplome_preced" value="<?php if(!empty($showDiplome)) {echo $showDiplome['diplome_name'];}?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Date d'obtention</label>
					<p class="showContact field-value"><?php if(!empty($showDiplome)) {echo formatDateForInput($showDiplome['date_obtent']);}?></p>
					<input class="editContact field-input hidden" type="date" name="date_obtent_diplome_preced" value="<?php if(!empty($showDiplome)) { echo formatDateForInput($showDiplome['date_obtent']); } ?>">
				</div>
				<?php 
					}
				?>
				
				<div class="field-group">
					<label class="field-label">Mot de passe mail</label>
					<p class="showContact field-value locked"><i class="bi bi-lock-fill"></i><?=$profil['password'];?></p>
				</div>
			</div>
		</div>
	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-mortarboard"></i>
				<span>Informations d'études</span>
			</div>
			<div class="card-actions">
				<a href="#" class="submitEtd submitRedirect btn-primary hidden">Enregistrer</a>
				<a href="#" class="annulEtd btn-secondary hidden">Annuler</a>
				<a href="#" id="editEtd" class="btn-edit"><i class="bi-pencil"></i></a>
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Mention</label>
					<p class="field-value locked"><i class="bi-lock-fill"></i><?=$etude_envisage?></p>
				</div>
				
				<div class="field-group">
					<label class="field-label">Niveau</label>
					<p class="showEtd field-value"><?php if ($profil['annee_etude']==0) {
										echo "Remise à niveau";
									}elseif($profil['annee_etude'] > 0 AND $profil['annee_etude'] < 4) {
										echo "Licence ".$profil['annee_etude'];
									}else{
										echo "Master ".($profil['annee_etude']-3);
									} ?>	
					</p>
					<select class="editEtd field-input hidden" name="annee_etude" id="annee_etude">
						<option value="0" <?php if($profil['annee_etude'] == 0){echo 'selected';}?>>Remise à niveau</option>
						<option value="1" <?php if($profil['annee_etude'] == 1){echo 'selected';}?>>Licence 1</option>
						<option value="2" <?php if($profil['annee_etude'] == 2){echo 'selected';}?>>Licence 2</option>
						<option value="3" <?php if($profil['annee_etude'] == 3){echo 'selected';}?>>Licence 3</option>
						<option value="4" <?php if($profil['annee_etude'] == 4){echo 'selected';}?>>Master 1</option>
						<option value="5" <?php if($profil['annee_etude'] == 5){echo 'selected';}?>>Master 2</option>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Matricule</label>
					<p class="field-value locked"><i class="bi-lock-fill"></i><?=$profil['student_id']?></p>
				</div>
				
				<div class="field-group">
					<label class="field-label">Status</label>
					<p class="showEtd field-value"><?=$profil['status']?></p>
					<select class="editEtd field-input hidden" name="status" id="status">
						<option value=""></option>
						<option <?php if($profil['status'] == 'Externe'){echo 'selected';}?>>Externe</option>
						<option <?php if($profil['status'] == 'Interne'){echo 'selected';}?>>Interne</option>
						<option <?php if($profil['status'] == 'Bungalow'){echo 'selected';}?>>Bungalow</option>
					</select>
				</div>
			</div>
			<div>
				<div class="field-group">
					<label class="field-label">Parcours</label>
					<p class="showEtd field-value"><?=$etude_option?></p>
					<select id="firstEtd" class="editEtd field-input hidden" name="etude_option">
						<option value=""></option>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description ="'.$etude_envisage.'"');

	$showSignMention = $findSignMention->fetch();
	$SignMention = $showSignMention['filiere_sigle'];


$findOption = $dtb->query('SELECT * FROM filiere_parcours WHERE departement ="'.$SignMention.'" ORDER BY description');
while ($showO = $findOption->fetch()) {
 ?>	
 						<option <?php if($profil['etude_option'] == $showO['description']){echo 'selected';}?>><?=$showO['description']?></option>
 <?php 
}
 ?>		
 					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Année universitaire</label>
					<p class="showEtd field-value"><?=$profil['annee_scolaire']?></p>
					<select class="editEtd field-input hidden" name="annee_scolaire">
<?php
$y = date('Y');
for ($i=0; $i <= 8; $i++) { 
	
	$as = $y." - ".($y+1);
	?>
						<option <?php if($profil['annee_scolaire'] == $as){echo 'selected';}?>><?=$as?></option>
<?php
$y = $y - 1;
}
 ?>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Ancien étudiant</label>
					<p class="showEtd field-value"><?php $new_student = $profil['new_student']; if($profil['new_student'] == 1){echo 'Non';}else{echo 'Oui';}?></p>
					<select class="editEtd field-input hidden" name="new_student" id="new_student">
						<option <?php if($profil['new_student'] == 1){echo 'selected';}?> value="1">Non</option>
						<option <?php if($profil['new_student'] == 0){echo 'selected';}?> value="0">Oui</option>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Gradué</label>
					<p class="showEtd field-value"><?php if($profil['graduated'] == "" OR $profil['graduated'] == 0){echo 'Non';}else{echo 'Oui';}?></p>
					<select class="editEtd field-input hidden" name="graduated" id="graduated">
						<option <?php if($profil['graduated'] == 0){echo 'selected';}?> value="0">Non</option>
						<option <?php if($profil['graduated'] == 1){echo 'selected';}?> value="1">Oui</option>
					</select>
				</div>
			</div>
		</div>
	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-people"></i>
				<span>Informations parentales</span>
			</div>
			<div class="card-actions">
				<a href="#" class="submitParent submitRedirect btn-primary hidden">Enregistrer</a>
				<a href="#" class="annulParent btn-secondary hidden">Annuler</a>
				<a href="#" id="editParent" class="btn-edit"><i class="bi-pencil"></i></a>
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Nom du père</label>
					<p class="showParent field-value"><?=$profil['father_name']?></p>
					<input id="firstParent" class="editParent field-input hidden" type="text" name="father_name" value="<?=$profil['father_name']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Nom de la mère</label>
					<p class="showParent field-value"><?=$profil['mother_name']?></p>
					<input class="editParent field-input hidden" type="text" name="mother_name" value="<?=$profil['mother_name']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Téléphone</label>
					<p class="showParent field-value"><?=$profil['parent_tel']?></p>
					<input class="editParent field-input hidden" type="text" name="parent_tel" value="<?=$profil['parent_tel']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Adresse</label>
					<p class="showParent field-value"><?=$profil['parent_adresse']?></p>
					<input class="editParent field-input hidden" type="text" name="parent_adresse" value="<?=$profil['parent_adresse']?>">
				</div>
			</div>
			<div>
				<div class="field-group">
					<label class="field-label">Sa profession (père)</label>
					<p class="showParent field-value"><?=$profil['father_prof']?></p>
					<input class="editParent field-input hidden" type="text" name="father_prof" value="<?=$profil['father_prof']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Sa profession (mère)</label>
					<p class="showParent field-value"><?=$profil['mother_prof']?></p>
					<input class="editParent field-input hidden" type="text" name="mother_prof" value="<?=$profil['mother_prof']?>">
				</div>
			</div>
		</div>
	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-heart"></i>
				<span>Informations sponsoring</span>
			</div>
			<div class="card-actions">
				<a href="#" class="submitSpons submitRedirect btn-primary hidden">Enregistrer</a>
				<a href="#" class="annulSpons btn-secondary hidden">Annuler</a>
				<a href="#" id="editSpons" class="btn-edit"><i class="bi-pencil"></i></a>
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Nom du sponsor</label>
					<p class="showSpons field-value"><?=$profil['sponsor_nom']?></p>
					<input id="firstSpons" class="editSpons field-input hidden" type="text" name="sponsor_nom" value="<?=$profil['sponsor_nom']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Téléphone</label>
					<p class="showSpons field-value"><?=$profil['sponsor_tel']?></p>
					<input class="editSpons field-input hidden" type="text" name="sponsor_tel" value="<?=$profil['sponsor_tel']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Adresse</label>
					<p class="showSpons field-value"><?=$profil['sponsor_adresse']?></p>
					<input class="editSpons field-input hidden" type="text" name="sponsor_adresse" value="<?=$profil['sponsor_adresse']?>">
				</div>
			</div>
			<div>
				<div class="field-group">
					<label class="field-label">Prénom</label>
					<p class="showSpons field-value"><?=$profil['sponsor_prenom']?></p>
					<input class="editSpons field-input hidden" type="text" name="sponsor_prenom" value="<?=$profil['sponsor_prenom']?>">
				</div>
			</div>
		</div>
	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-three-dots"></i>
				<span>Autres informations</span>
			</div>
			<div class="card-actions">
				<a href="#" class="submitAutr submitRedirect btn-primary hidden">Enregistrer</a>
				<a href="#" class="annulAutr btn-secondary hidden">Annuler</a>
				<a href="#" id="editAutr" class="btn-edit"><i class="bi-pencil"></i></a>
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">État civil</label>
					<p class="showAutr field-value"><?=$profil['situationf']?></p>
					<select id="firstAutr" class="editAutr field-input hidden" name="situationf">
						<option <?php if($profil['situationf'] == 'Célibataire'){echo 'selected';}?>>Célibataire</option>
						<option <?php if($profil['situationf'] == 'Marié'){echo 'selected';}?>>Marié</option>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Nombre d'enfants</label>
					<p class="showAutr field-value"><?=$profil['nb_enfant']?></p>
					<input class="editAutr field-input hidden" type="text" name="nb_enfant" value="<?=$profil['nb_enfant']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Numéro visa</label>
					<p class="showAutr field-value"><?=$profil['num_visa']?></p>
					<input class="editAutr field-input hidden" type="text" name="num_visa" value="<?=$profil['num_visa']?>">
				</div>
			</div>
			<div>
				<div class="field-group">
					<label class="field-label">Nom du/de conjoint(e)</label>
					<p class="showAutr field-value"><?=$profil['nom_conjoint']?></p>
					<input class="editAutr field-input hidden" type="text" name="nom_conjoint" value="<?=$profil['nom_conjoint']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Religion</label>
					<p class="showAutr field-value"><?=$profil['religion']?></p>
					<select class="editAutr field-input hidden" name="religion">
						<option <?php if($profil['religion'] == 'Adventiste' OR $profil['religion'] == 'Adventiste du Septieme-jour'){echo 'selected';}?>>Adventiste</option>
						<option <?php if($profil['religion'] != 'Adventiste' AND $profil['religion'] != 'Adventiste du Septieme-jour'){echo 'selected';}?>>non Adventiste</option>
					</select>
				</div>
				
				<div class="field-group">
					<label class="field-label">Abonné au CAF</label>
					<p class="showAutr field-value"><?php if ($profil['abonment'] == 1) { echo "Oui"; }else{ echo "Non"; }?></p>
					<select class="editAutr field-input hidden" name="abonment" id="abonment">
						<option <?php if($profil['abonment'] == 1){ echo 'selected'; }?> value="1">Oui</option>
						<option <?php if($profil['abonment'] == 0 OR $profil['abonment'] == ''){ echo 'selected'; }?> value="0">Non</option>
					</select>
				</div>
			</div>
		</div>
	</div>

</div>

<div class="fixed inset-0 z-50 hidden" id="sessionForInformation" style="backdrop-filter: blur(8px); background: rgba(0,0,0,0.5);">
	<div class="flex items-center justify-center min-h-screen p-4">
		<div class="w-full max-w-md bg-slate-900 border border-slate-700 rounded-xl shadow-2xl overflow-hidden">
			<div class="px-6 py-4 border-b border-slate-700 bg-gradient-to-r from-slate-800 to-slate-900">
				<h3 class="text-lg font-semibold text-white flex items-center gap-2">
					<i class="bi bi-save text-cyan-400"></i>
					Enregistrement de la modification
				</h3>
				<p class="text-sm text-slate-400 mt-1">Sélectionnez la session pour cette modification</p>
			</div>
			
			<div class="p-6 space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="field-label">Semestre</label>
						<select name="semesterForInformation" class="field-input w-full mt-1">
							<option <?php if (date('m')>7) { echo "selected"; } ?>>Premier semestre</option>
							<option>Semestre d'été</option>
							<option <?php if (date('m')<=7) { echo "selected"; } ?>>Deuxième semestre</option>
							<option>Semestre d'hiver</option>
						</select>
					</div>

					<div>
						<label class="field-label">Année scolaire</label>
						<select name="annee_scolaireForInformation" class="field-input w-full mt-1">
							<?php
							$y = date('Y');
							for ($i=0; $i <= 3; $i++) { 
								if (date('m')>7) {
									$as = $y." - ".($y+1);	
								}else{
									$as = ($y-1)." - ".$y;
								}
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
			
			<div class="px-6 py-4 border-t border-slate-700 bg-slate-800/50 flex justify-end gap-3">
				<a href="#" id="cancelsessionForInformation" class="btn-secondary px-5 py-2">Annuler</a>
				<input id="submit" type="submit" class="btn-primary px-5 py-2 cursor-pointer" value="Confirmer">
			</div>
		</div>
	</div>
</div>

</form>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script type="text/javascript">
	// ===== TOAST NOTIFICATION SYSTEM =====
	function showToast(type, title, message, duration = 4000) {
		const container = document.getElementById('toast-container');
		const toast = document.createElement('div');
		toast.className = `toast toast-${type}`;
		
		const icons = {
			success: '<i class="bi bi-check-lg"></i>',
			error: '<i class="bi bi-x-lg"></i>',
			warning: '<i class="bi bi-exclamation-triangle"></i>',
			info: '<i class="bi bi-info-lg"></i>'
		};
		
		toast.innerHTML = `
			<div class="toast-icon">${icons[type]}</div>
			<div class="toast-content">
				<div class="toast-title">${title}</div>
				<div class="toast-message">${message}</div>
			</div>
			<button class="toast-close" onclick="this.parentElement.classList.add('toast-out'); setTimeout(() => this.parentElement.remove(), 300);">
				<i class="bi bi-x"></i>
			</button>
			<div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
		`;
		
		container.appendChild(toast);
		
		setTimeout(() => {
			toast.classList.add('toast-out');
			setTimeout(() => toast.remove(), 300);
		}, duration);
	}

	// ===== SAVING OVERLAY =====
	function showSavingOverlay() {
		let overlay = document.querySelector('.saving-overlay');
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.className = 'saving-overlay';
			overlay.innerHTML = '<div class="saving-spinner"></div>';
			document.body.appendChild(overlay);
		}
		setTimeout(() => overlay.classList.add('active'), 10);
	}

	function hideSavingOverlay() {
		const overlay = document.querySelector('.saving-overlay');
		if (overlay) {
			overlay.classList.remove('active');
		}
	}

	// ===== REFRESH INFORMATION SECTION =====
	function refreshInformationSection() {
		const currentUrl = window.location.href;
		$.get(currentUrl, function(response) {
			const newContent = $(response).find('.w-full.grid.gap-4').first();
			if (newContent.length) {
				$('.w-full.grid.gap-4').first().html(newContent.html());
				showToast('info', 'Données actualisées', 'Les informations ont été rechargées.');
			}
		}).fail(function() {
			console.log('Refresh failed, but data was saved');
		});
	}

	$(document).ready(function(){

		var rg_user = <?=$rg_user['level'];?>;
		

		$('.submitRedirect').click(function(){
			$('#sessionForInformation').attr('class','absolute w-full h-screen top-0 left-0 z-40');
		});
		$('#cancelsessionForInformation').click(function(){
			$('#sessionForInformation').attr('class','absolute w-full h-screen top-0 left-0 z-40 hidden');
		});
		
		$('#submit').click(function() {

			$('#sessionForInformation').attr('class','absolute w-full h-screen top-0 left-0 z-40 hidden');
		});
		
		$('.form-no-refrech').on('submit',function (e) {
			
			e.preventDefault();

			var url = '<?=$app_base?>/app/.student/updateStd?student_id=<?=$student_id?>&id=<?=$id?>&rg_id=<?=$rg_id?>&etude_envisage=<?=$etude_envisage?>';
			
			showSavingOverlay();
			
			var session_id = $('input[name="session_id"]').val();
			
			var data = $(this).serialize();
			
			$.ajax({
				url: url,
				type: 'POST',
				data: data,
				dataType: 'text',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				success: function(response){
					hideSavingOverlay();
					showToast('success', 'Modification réussie', 'Les informations de l\'étudiant ont été mises à jour avec succès.');

					// Reset UI state
					$('.submitPers, .submitContact, .submitEtd, .submitParent, .submitSpons, .submitAutr').addClass('hidden');
					$('.annulPers, .annulContact, .annulEtd, .annulParent, .annulSpons, .annulAutr').addClass('hidden');
					$('#editPers, #editContact, #editEtd, #editParent, #editSpons, #editAutr').css({'display':'block'});
					$('.editPers, .editContact, .editEtd, .editParent, .editSpons, .editAutr').addClass('hidden');
					$('.showPers, .showContact, .showEtd, .showParent, .showSpons, .showAutr').css({'display':'block'});

					// Refresh section to show updated data
					setTimeout(function() {
						refreshInformationSection();
					}, 500);
				},
				error: function(xhr, status, error){
					hideSavingOverlay();
					showToast('error', 'Erreur de modification', 'Une erreur est survenue lors de la mise à jour. Veuillez réessayer.');
					console.error('Update error:', error);
				}
			});
		});
		
		$('#editPers').click(function(){
			if (rg_user < 3 || rg_user == 6) {

			$(this).css({'display':'none'});
			$('.annulPers').removeClass('hidden').css({'display':'block'});
			$('.editPers').removeClass('hidden').css({'display':'block'});
			$('#firstPers').focus();
			$('.showPers').css({'display':'none'});

			}else{
				showToast('warning', 'Accès refusé', 'Vous n\'avez pas les droits pour modifier ce contenu.');
			}
		});
		$('.annulPers').click(function(){
			$(this).addClass('hidden');
			$('#editPers').css({'display':'block'});
			$('.editPers').addClass('hidden');
			$('.showPers').css({'display':'block'});
			$('.submitPers').addClass('hidden');
		});

		$('.editPers').click(function(){
			$('.submitPers').removeClass('hidden').css({'display':'block'});
		});


		$('#editContact').click(function(){
			if (rg_user < 3 || rg_user == 6) {
			$(this).css({'display':'none'});
			$('.annulContact').removeClass('hidden').css({'display':'block'});
			$('.editContact').removeClass('hidden').css({'display':'block'});
			$('#firstContact').focus();
			$('.showContact').css({'display':'none'});
			}else{
				showToast('warning', 'Accès refusé', 'Vous n\'avez pas les droits pour modifier ce contenu.');
			}
		});
		$('.annulContact').click(function(){
			$(this).addClass('hidden');
			$('#editContact').css({'display':'block'});
			$('.editContact').addClass('hidden');
			$('.showContact').css({'display':'block'});
			$('.submitContact').addClass('hidden');
		});

		$('.editContact').click(function(){
			$('.submitContact').removeClass('hidden').css({'display':'block'});
		});


		$('#editEtd').click(function(){
			if (rg_user < 3 || rg_user == 6) {
			$(this).css({'display':'none'});
			$('.annulEtd').removeClass('hidden').css({'display':'block'});
			$('.editEtd').removeClass('hidden').css({'display':'block'});
			$('#firstEtd').focus();
			$('.showEtd').css({'display':'none'});
			}else{
				showToast('warning', 'Accès refusé', 'Vous n\'avez pas les droits pour modifier ce contenu.');
			}
		});
		$('.annulEtd').click(function(){
			$(this).addClass('hidden');
			$('#editEtd').css({'display':'block'});
			$('.editEtd').addClass('hidden');
			$('.showEtd').css({'display':'block'});
			$('.submitEtd').addClass('hidden');
		});

		$('.editEtd').click(function(){
			$('.submitEtd').removeClass('hidden').css({'display':'block'});
		});


		$('#editParent').click(function(){
			if (rg_user < 3 || rg_user == 6) {
			$(this).css({'display':'none'});
			$('.annulParent').removeClass('hidden').css({'display':'block'});
			$('.editParent').removeClass('hidden').css({'display':'block'});
			$('#firstParent').focus();
			$('.showParent').css({'display':'none'});
			}else{
				showToast('warning', 'Accès refusé', 'Vous n\'avez pas les droits pour modifier ce contenu.');
			}
		});
		$('.annulParent').click(function(){
			$(this).addClass('hidden');
			$('#editParent').css({'display':'block'});
			$('.editParent').addClass('hidden');
			$('.showParent').css({'display':'block'});
			$('.submitParent').addClass('hidden');
		});

		$('.editParent').click(function(){
			$('.submitParent').removeClass('hidden').css({'display':'block'});
		});


		$('#editSpons').click(function(){
			if (rg_user < 3 || rg_user == 6) {
			$(this).css({'display':'none'});
			$('.annulSpons').removeClass('hidden').css({'display':'block'});
			$('.editSpons').removeClass('hidden').css({'display':'block'});
			$('#firstSpons').focus();
			$('.showSpons').css({'display':'none'});
			}else{
				showToast('warning', 'Accès refusé', 'Vous n\'avez pas les droits pour modifier ce contenu.');
			}
		});
		$('.annulSpons').click(function(){
			$(this).addClass('hidden');
			$('#editSpons').css({'display':'block'});
			$('.editSpons').addClass('hidden');
			$('.showSpons').css({'display':'block'});
			$('.submitSpons').addClass('hidden');
		});

		$('.editSpons').click(function(){
			$('.submitSpons').removeClass('hidden').css({'display':'block'});
		});


		$('#editAutr').click(function(){
			if (rg_user < 3 || rg_user == 6) {
			$(this).css({'display':'none'});
			$('.annulAutr').removeClass('hidden').css({'display':'block'});
			$('.editAutr').removeClass('hidden').css({'display':'block'});
			$('#firstAutr').focus();
			$('.showAutr').css({'display':'none'});
			}else{
				showToast('warning', 'Accès refusé', 'Vous n\'avez pas les droits pour modifier ce contenu.');
			}
		});
		$('.annulAutr').click(function(){
			$(this).addClass('hidden');
			$('#editAutr').css({'display':'block'});
			$('.editAutr').addClass('hidden');
			$('.showAutr').css({'display':'block'});
			$('.submitAutr').addClass('hidden');
		});

		$('.editAutr').click(function(){
			$('.submitAutr').removeClass('hidden').css({'display':'block'});
		});
	});
</script>