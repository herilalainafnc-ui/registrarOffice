
<style>
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
		cursor: pointer;
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
	
	select.field-input option {
		background: #0f172a;
		color: #e2e8f0;
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

<form method="post" action="<?=$app_base?>/app/.prof/updateProf?id=<?=$teacher_id?>&rg_id=<?=$rg_id?>" class="form-no-refrech">
<div class="w-full grid gap-4 grid-cols-1 lg:grid-cols-2 mt-3">

	<div class='info-card'>
		<div class="card-header">
			<div class="card-title">
				<i class="bi bi-person-badge"></i>
				<span>Informations personnelles</span>
			</div>
			<div class="card-actions">
				<input type="submit" class="submitPers btn-primary hidden" value="Enregistrer">
				<a href="#" class="annulPers btn-secondary hidden">Annuler</a>
				<a href="#" id="editPers" class="btn-edit"><i class="bi-pencil"></i></a>	
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Nom</label>
					<p class="showPers field-value"><?=strtoupper($name)?></p>
					<input id="firstPers" class="editPers field-input hidden" type="text" name="name" value="<?=$name?>">
				</div>

				<div class="field-group">
					<label class="field-label">Date de naissance</label>
					<p class="showPers field-value"><?=$profil['birthday']?></p>
					<input class="editPers field-input hidden" type="date" name="birthday" value="<?=$profil['birthday']?>">
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
					<label class="field-label">Diplôme</label>
					<p class="showPers field-value"><?=$profil['diplome']?></p>
					<input class="editPers field-input hidden" type="text" name="diplome" value="<?=$profil['diplome']?>">
				</div>
    		</div>
			<div>
				<div class="field-group">
					<label class="field-label">Prénom</label>
					<p class="showPers field-value"><?=$lastName?></p>
					<input class="editPers field-input hidden" type="text" name="lastName" value="<?=$lastName?>">
				</div>

				<div class="field-group">
					<label class="field-label">Lieu de naissance</label>
					<p class="showPers field-value"><?=$profil['lieuN']?></p>
					<input class="editPers field-input hidden" type="text" name="lieuN" value="<?=$profil['lieuN']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Religion</label>
					<p class="showPers field-value"><?=$profil['religion']?></p>
					<select class="editPers field-input hidden" name="religion">
						<option <?php if($religion == 'Adventiste' OR $religion == 'Adventiste du Septieme-jour'){echo 'selected';}?>>Adventiste</option>
						<option <?php if($religion != 'Adventiste' AND $religion != 'Adventiste du Septieme-jour'){echo 'selected';}?>>non Adventiste</option>
					</select>
				</div>
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
				<input type="submit" class="submitContact btn-primary hidden" value="Enregistrer">
				<a href="#" class="annulContact btn-secondary hidden">Annuler</a>
				<a href="#" id="editContact" class="btn-edit"><i class="bi-pencil"></i></a>
			</div>
		</div>

		<div class="grid-two-cols">
			<div>
				<div class="field-group">
					<label class="field-label">Téléphone</label>
					<p class="showContact field-value"><?=$profil['phone']?></p>
					<input id="firstContact" class="editContact field-input hidden" type="text" name="phone" value="<?=$profil['phone']?>">
				</div>
				
				<div class="field-group">
					<label class="field-label">Adresse mail</label>
					<p class="showContact field-value"><?=$profil['email']?></p>
					<input class="editContact field-input hidden" type="text" name="email" value="<?=$profil['email']?>">
				</div>
			</div>
			<div>
				<div class="field-group">
					<label class="field-label">Extension</label>
					<p class="showContact field-value">—</p>
				</div>
			</div>
		</div>
	</div>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->
</div>
</form>

<!--  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script type="text/javascript">
	$(document).ready(function(){

		$('.form-no-refrech').on('submit',function (e) {
			e.preventDefault();

			var url = '<?=$app_base?>/app/.prof/updateProf?id=<?=$teacher_id?>&rg_id=<?=$rg_id?>';
			var data = $(this).serialize();

			$.post(url,data,function(response){
				
				$('.submitPers').css({'display':'none'});
				$('.submitContact').css({'display':'none'});
			
				$('.annulPers').css({'display':'none'});
				$('.annulContact').css({'display':'none'});
			
				$('#editPers').css({'display':'block'});
				$('#editContact').css({'display':'block'});
			});
		});
		
		$('#editPers').click(function(){
			$(this).css({'display':'none'});
			$('.annulPers').css({'display':'block'});
			$('.editPers').css({'display':'block'});
			$('#firstPers').focus();
			$('.showPers').css({'display':'none'});
		});
		$('.annulPers').click(function(){
			$(this).css({'display':'none'});
			$('#editPers').css({'display':'block'});
			$('.editPers').css({'display':'none'});
			$('.showPers').css({'display':'block'});
			$('.submitPers').css({'display':'none'});
		});

		$('.editPers').click(function(){
			$('.submitPers').css({'display':'block'});
		});


		$('#editContact').click(function(){
			$(this).css({'display':'none'});
			$('.annulContact').css({'display':'block'});
			$('.editContact').css({'display':'block'});
			$('#firstContact').focus();
			$('.showContact').css({'display':'none'});
		});
		$('.annulContact').click(function(){
			$(this).css({'display':'none'});
			$('#editContact').css({'display':'block'});
			$('.editContact').css({'display':'none'});
			$('.showContact').css({'display':'block'});
			$('.submitContact').css({'display':'none'});
		});

		$('.editContact').click(function(){
			$('.submitContact').css({'display':'block'});
		});
	});
</script>