<!-- AJOUT DE COURS -->
	<!-- ====================================================================
	     Modale « Ajouter un cours »

	     Alignee sur celle des enseignants : memes classes mdl-prof, donc un
	     seul jeu de styles pour les deux. Elles partagent la meme structure
	     (en-tete fixe, corps defilant, pied fixe) et le meme comportement.

	     Identifiants, noms de champs et classes de validation inchanges :
	     app/.cours/addCours et les gestionnaires existants fonctionnent tels
	     quels.
	     ==================================================================== -->
	<div class="mdl-prof" id="bigNotifCours" aria-hidden="true">
		<div class="mdl-prof__voile" id="overlayAddCours"></div>

		<div class="mdl-prof__zone">
			<div class="mdl-prof__panneau mdl-prof__panneau--large">

				<div class="mdl-prof__entete">
					<div class="mdl-prof__titre">
						<div class="mdl-prof__pastille"><i class="bi-journal-plus"></i></div>
						<div>
							<h2>Ajouter un cours</h2>
							<p>Renseignez les informations du cours</p>
						</div>
					</div>
					<button type="button" class="mdl-prof__fermer" id="closeAddCours" aria-label="Fermer">
						<i class="bi-x-lg"></i>
					</button>
				</div>

				<form method="post" action="<?=$app_base?>/app/.cours/addCours" enctype="multipart/form-data"
				      class="form-no-refrech-cours" style="display:flex;flex-direction:column;min-height:0;flex:1 1 auto;">

					<div class="mdl-prof__corps">

						<div class="mdl-prof__colonnes">

							<div>
								<div class="mdl-prof__champ">
									<label>Sigle <span class="req">*</span></label>
									<input type="text" name="sigle" id="sigleInput" class="requierd-cours-1" placeholder="Ex: INFO101" aria-describedby="sigleAvis">
									<p class="mdl-prof__avis" id="sigleAvis" aria-live="polite"></p>
								</div>
								<div class="mdl-prof__champ">
									<label>Titre <span class="req">*</span></label>
									<input type="text" name="title" class="requierd-cours-2" placeholder="Intitulé du cours">
								</div>
								<div class="mdl-prof__champ">
									<label>Titre en anglais</label>
									<input type="text" name="title_english" placeholder="English title">
								</div>
								<div class="mdl-prof__champ mdl-prof__paire">
									<div>
										<label>Crédits</label>
										<select name="nb_credit">
<?php /* 0 : cours sans credit (soutien de francais...), rien a payer ; 1 reste le choix de depart */ ?>
<?php for ($i = 0; $i <= 20; $i++) { ?>
											<option<?= $i === 1 ? ' selected' : '' ?>><?=$i?></option>
<?php } ?>
										</select>
									</div>
									<div>
										<label>Catégorie</label>
										<select name="category">
											<option value="0">Général</option>
											<option value="1">Majeur</option>
											<option value="2">Sélectionné</option>
											<option value="3">Additionnel</option>
										</select>
									</div>
								</div>
								<div class="mdl-prof__champ">
									<label>Mention</label>
									<select name="dep_desc" id="mentionSelect">
<?php
	$cat = $dtb->query("SELECT * FROM filiere");
	while ($ct = $cat->fetch()) {
?>
										<option><?=$ct['filiere_sigle']?></option>
<?php } ?>
									</select>
								</div>
								<div class="mdl-prof__champ">
									<label>Parcours</label>
									<select name="parcours" id="parcours">
										<option value="all">-</option>
<?php
	$cat = $dtb->query("SELECT * FROM filiere");
	while ($ct = $cat->fetch()) {
?>
										<option><?=$ct['filiere_sigle']?></option>
<?php } ?>
									</select>
								</div>
							</div>

							<div>
								<div class="mdl-prof__champ mdl-prof__paire">
									<div>
										<label>Niveau</label>
										<select name="yearlevel">
											<option value="0">Remise à niveau</option>
											<option value="1" selected>Licence 1</option>
											<option value="2">Licence 2</option>
											<option value="3">Licence 3</option>
											<option value="4">Master 1</option>
											<option value="5">Master 2</option>
										</select>
									</div>
									<div>
										<label>Semestre</label>
										<select name="semester">
											<option>1</option>
											<option>2</option>
										</select>
									</div>
								</div>
								<div class="mdl-prof__champ">
									<label>Laboratoire</label>
									<select name="lab">
										<option value="0"></option>
										<option value="1">Lab 1</option>
										<option value="2">Lab 2</option>
										<option value="3">Lab 3</option>
										<option value="4">Simulation Room</option>
										<option value="5">Labo recherche</option>
									</select>
								</div>
								<div class="mdl-prof__champ">
									<label>Enseignant</label>
									<select name="teacher_id">
										<option value=""></option>
<?php
	$teach = $dtb->query("SELECT * FROM teacher ORDER BY lastName");
	while ($tch = $teach->fetch()) {
?>
										<option value="<?=$tch['uid']?>"><?=$tch['lastName']?> <?=$tch['name']?></option>
<?php } ?>
									</select>
								</div>
								<div class="mdl-prof__champ">
									<label>Description</label>
									<textarea name="description" rows="2" placeholder="Contenu du cours"></textarea>
								</div>
								<div class="mdl-prof__champ">
									<label>Remarque</label>
									<textarea name="remark" rows="2" placeholder="Note interne"></textarea>
								</div>
							</div>

						</div>
					</div>

					<div class="mdl-prof__pied">
						<button type="button" class="mdl-prof__btn mdl-prof__btn--annuler" id="cancelnotifAddCours">Annuler</button>
						<input type="submit" id="btnAddCours" class="mdl-prof__btn mdl-prof__btn--valider toolInactive" value="Enregistrer">
					</div>

				</form>

			</div>
		</div>
	</div>


<!-- AJOUT DE PROF -->
	<!-- ====================================================================
	     Modale « Ajouter un enseignant »

	     Reecrite avec un CSS AUTONOME (prefixe mdl-), sans utilitaires
	     Tailwind ni Bootstrap. Ces deux frameworks cohabitent sur le projet
	     et Bootstrap marque ses utilitaires !important : impossible de
	     raisonner sur ce qui gagne. Ici, chaque regle est explicite.

	     Le voile porte a la fois une teinte ET un flou. Le flou seul ne
	     suffit pas quand la page derriere est sombre et peu dense : il n y a
	     rien a brouiller, et le resultat parait uniforme.

	     Identifiants et noms de champs inchanges : addProf.php et les
	     gestionnaires existants continuent de fonctionner tels quels.
	     ==================================================================== -->
	<style>
	.mdl-prof { position: fixed; inset: 0; z-index: 9999; display: none; }
	.mdl-prof.mdl-ouvert { display: block; }

	/* Voile : teinte + flou. La teinte assure le contraste meme si le
	   navigateur ignore backdrop-filter ; le flou apporte la profondeur. */
	/* Flou applique au CONTENU de la page, et non via backdrop-filter.
	   Ce dernier depend de l ordre de peinture : si la modale precede le
	   contenu, elle ne floute rien et le fond parait uni. Ici le resultat
	   ne depend d aucune subtilite de rendu.
	   La modale est sœur du conteneur de page, le flou ne l atteint pas. */
	.mdl-fond-floute {
		filter: blur(2.5px);
		transition: filter .18s ease;
	}

	.mdl-prof__voile {
		position: absolute; inset: 0;
		/* Ni teinte ni backdrop-filter : sur une interface deja sombre, tout
		   assombrissement supplementaire efface le fond au lieu de le mettre
		   en valeur. Le flou porte par la page suffit a separer les plans.
		   Ce div reste la cible du clic-hors-modale. */
		background: transparent;
	}

	/* Zone de defilement : centre le panneau et gere les ecrans courts. */
	.mdl-prof__zone {
		position: absolute; inset: 0;
		display: flex; align-items: center; justify-content: center;
		padding: 12px;
		overflow-y: auto;
	}

	/* Panneau : borne en largeur ET en hauteur, colonne pour que seul le
	   corps defile et que les boutons restent toujours atteignables. */
	.mdl-prof__panneau {
		position: relative;
		width: 100%;
		max-width: 680px;
		max-height: calc(100vh - 24px);
		display: flex; flex-direction: column;
		background: rgb(var(--c-canvas));
		border: 1px solid rgb(var(--c-line-strong) / .18);
		border-radius: 16px;
		box-shadow: 0 25px 60px -12px rgba(0, 0, 0, .8);
		overflow: hidden;
	}

	.mdl-prof__entete {
		display: flex; align-items: center; justify-content: space-between;
		gap: 12px; padding: 18px 20px; flex: 0 0 auto;
		border-bottom: 1px solid rgb(var(--c-line-strong) / .14);
	}
	.mdl-prof__titre { display: flex; align-items: center; gap: 12px; min-width: 0; }
	.mdl-prof__pastille {
		width: 40px; height: 40px; flex: 0 0 auto;
		border-radius: 10px; display: flex; align-items: center; justify-content: center;
		background: rgb(var(--c-accent) / .12); border: 1px solid rgb(var(--c-accent) / .25);
		color: rgb(var(--c-accent-text)); font-size: 18px;
	}
	.mdl-prof__titre h2 { margin: 0; font-size: 16px; font-weight: 600; color: rgb(var(--c-text)); }
	.mdl-prof__titre p  { margin: 2px 0 0; font-size: 12px; color: rgb(var(--c-text-3)); }

	.mdl-prof__fermer {
		width: 36px; height: 36px; flex: 0 0 auto; cursor: pointer;
		border-radius: 9px; border: 1px solid rgb(var(--c-line-strong) / .2);
		background: rgb(var(--c-surface) / .8); color: rgb(var(--c-text-2));
		display: flex; align-items: center; justify-content: center;
		transition: background .15s ease, color .15s ease;
	}
	.mdl-prof__fermer:hover { background: rgb(var(--c-raised) / .9); color: #fff; }

	/* Corps : la seule zone qui defile. */
	.mdl-prof__corps { padding: 18px 20px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }

	.mdl-prof__colonnes { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
	.mdl-prof__paire    { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
	.mdl-prof__champ    { margin-bottom: 14px; }
	.mdl-prof__champ:last-child { margin-bottom: 0; }

	.mdl-prof label {
		display: block; margin-bottom: 6px;
		font-size: 13px; font-weight: 500; color: rgb(var(--c-text-2));
	}
	.mdl-prof label .req { color: #f87171; }

	.mdl-prof input[type="text"],
	.mdl-prof input[type="date"],
	.mdl-prof input[type="email"],
	.mdl-prof input[type="tel"],
	.mdl-prof select {
		width: 100%; box-sizing: border-box; padding: 9px 12px;
		background: rgb(var(--c-surface) / .55);
		border: 1px solid rgb(var(--c-line) / .9);
		border-radius: 9px;
		color: rgb(var(--c-text)); font-size: 14px;
		outline: none;
		transition: border-color .15s ease, box-shadow .15s ease;
	}
	/* La modale des cours a deux zones de texte libre. */
	.mdl-prof textarea {
		width: 100%; box-sizing: border-box; padding: 9px 12px;
		background: rgb(var(--c-surface) / .55); color: rgb(var(--c-text)); font-size: 14px;
		border: 1px solid rgb(var(--c-line) / .9); border-radius: 9px;
		font-family: inherit; resize: vertical; outline: none;
		transition: border-color .15s ease, box-shadow .15s ease;
	}
	.mdl-prof textarea::placeholder { color: rgb(var(--c-text-3)); }
	.mdl-prof textarea:focus {
		border-color: rgb(var(--c-accent)); box-shadow: 0 0 0 3px rgba(6, 182, 212, .18);
	}

	/* Le formulaire des cours compte treize champs : il lui faut un peu
	   plus de largeur que celui des enseignants. */
	.mdl-prof__panneau--large { max-width: 860px; }

	/* Bouton inerte tant que le sigle et le titre ne sont pas saisis.
	   toolInactive porte deja pointer-events: none dans style.css ; on ne
	   traite ici que l apparence. Deux classes pour passer devant
	   .mdl-prof__btn--valider, de meme specificite mais declare avant. */
	.mdl-prof__btn.toolInactive {
		background: rgb(var(--c-surface)) !important; color: rgb(var(--c-text-3)) !important;
		border-color: rgb(var(--c-line) / .6) !important;
		cursor: default; pointer-events: none;
	}

	.mdl-prof input::placeholder { color: rgb(var(--c-text-3)); }
	.mdl-prof input:focus, .mdl-prof select:focus {
		border-color: rgb(var(--c-accent));
		box-shadow: 0 0 0 3px rgba(6, 182, 212, .18);
	}

	/* Zone photo */
	.mdl-prof__photo {
		width: 96px; height: 96px; cursor: pointer;
		border: 2px dashed rgb(var(--c-line-strong) / .7); border-radius: 12px;
		background: rgb(var(--c-surface) / .4);
		display: flex; flex-direction: column; align-items: center; justify-content: center;
		gap: 4px; color: rgb(var(--c-text-3)); font-size: 11px;
		transition: border-color .15s ease, color .15s ease;
	}
	.mdl-prof__photo:hover { border-color: rgb(var(--c-accent)); color: rgb(var(--c-accent-text)); }
	.mdl-prof__photo input { display: none; }

	.mdl-prof__alerte { margin: 0 0 12px; font-size: 13px; color: #f87171; display: none; }

	/* Avis sur le sigle, pendant la frappe (src/js/cours.sigle.js). */
	.mdl-prof__avis { margin: 6px 0 0; font-size: 12px; line-height: 1.45; color: rgb(var(--c-text-3)); }
	.mdl-prof__avis:empty { display: none; }
	.mdl-prof__avis--ok { color: #34d399; }
	.mdl-prof__avis--doublon { color: #fca5a5; }
	.mdl-prof__avis--attente { color: rgb(var(--c-text-3)); }
	.mdl-prof__avis ul { margin: 4px 0 0; padding-left: 16px; list-style: disc; }
	.mdl-prof__avis a { color: rgb(var(--c-accent-text)); text-decoration: underline; text-underline-offset: 2px; }

	/* Pied : hors de la zone defilante, toujours visible. */
	.mdl-prof__pied {
		display: flex; align-items: center; justify-content: flex-end; gap: 10px;
		padding: 14px 20px; flex: 0 0 auto;
		border-top: 1px solid rgb(var(--c-line-strong) / .14);
		background: rgb(var(--c-canvas) / .35);
	}
	.mdl-prof__btn {
		padding: 9px 18px; border-radius: 9px; cursor: pointer;
		font-size: 14px; font-weight: 500; border: 1px solid transparent;
		transition: background .15s ease;
	}
	.mdl-prof__btn--annuler { background: rgb(var(--c-surface)); color: rgb(var(--c-text-2)); border-color: rgb(var(--c-line) / .9); }
	.mdl-prof__btn--annuler:hover { background: rgb(var(--c-raised)); }
	.mdl-prof__btn--valider { background: rgb(var(--c-accent-strong)); color: #fff; }
	.mdl-prof__btn--valider:hover { background: rgb(var(--c-accent)); }

	/* Champ signale invalide par la validation existante */
	.mdl-prof .champ-invalide { border-color: #f87171 !important; background: rgba(248,113,113,.12) !important; }

	/* --- Ecrans etroits : une seule colonne, panneau pleine largeur --- */
	@media (max-width: 640px) {
		.mdl-prof__zone      { padding: 8px; align-items: flex-start; }
		.mdl-prof__panneau   { max-height: calc(100vh - 16px); border-radius: 12px; }
		.mdl-prof__colonnes  { grid-template-columns: 1fr; gap: 14px; }
		.mdl-prof__corps     { padding: 14px; }
		.mdl-prof__entete,
		.mdl-prof__pied      { padding: 12px 14px; }
		.mdl-prof__btn       { flex: 1 1 auto; min-height: 44px; }
		/* Ces selecteurs doivent egaler ceux du bloc principal :
		   .mdl-prof input[type="text"] (0,2,1) l emporte sur .mdl-prof input
		   (0,1,1), media query ou non — la cascade tranche par specificite
		   avant de regarder la portee. En dessous de 16 px, iOS zoome au
		   focus et decale la mise en page. */
		.mdl-prof input[type="text"],
		.mdl-prof input[type="date"],
		.mdl-prof input[type="email"],
		.mdl-prof input[type="tel"],
		.mdl-prof select,
		.mdl-prof textarea { font-size: 16px; min-height: 44px; }
	}

	@supports (height: 100dvh) {
		.mdl-prof__panneau { max-height: calc(100dvh - 24px); }
		@media (max-width: 640px) { .mdl-prof__panneau { max-height: calc(100dvh - 16px); } }
	}
	</style>

	<div class="mdl-prof" id="bigNotifProf" aria-hidden="true">
		<div class="mdl-prof__voile" id="overlayAddProf"></div>

		<div class="mdl-prof__zone">
			<div class="mdl-prof__panneau">

				<div class="mdl-prof__entete">
					<div class="mdl-prof__titre">
						<div class="mdl-prof__pastille"><i class="bi-person-plus-fill"></i></div>
						<div>
							<h2>Ajouter un enseignant</h2>
							<p>Remplissez les informations du professeur</p>
						</div>
					</div>
					<button type="button" class="mdl-prof__fermer" id="closeAddProf" aria-label="Fermer">
						<i class="bi-x-lg"></i>
					</button>
				</div>

				<form id="formToAddProf" enctype="multipart/form-data" style="display:flex;flex-direction:column;min-height:0;flex:1 1 auto;">

					<div class="mdl-prof__corps">
						<p class="mdl-prof__alerte" id="alert-prof"></p>

						<div class="mdl-prof__colonnes">

							<div>
								<div class="mdl-prof__champ">
									<label>Nom <span class="req">*</span></label>
									<input type="text" name="name" class="requierd-prof-1" placeholder="Nom de famille">
								</div>
								<div class="mdl-prof__champ">
									<label>Prénom</label>
									<input type="text" name="lastName" placeholder="Prénom">
								</div>
								<div class="mdl-prof__champ mdl-prof__paire">
									<div>
										<label>Date de naissance</label>
										<input type="date" name="birthday">
									</div>
									<div>
										<label>Lieu de naissance</label>
										<input type="text" name="lieuN" placeholder="Ville">
									</div>
								</div>
								<div class="mdl-prof__champ">
									<label>Adresse <span class="req">*</span></label>
									<input type="text" name="address" class="requierd-prof-2" placeholder="Adresse complète">
								</div>
								<div class="mdl-prof__champ mdl-prof__paire">
									<div>
										<label>Genre</label>
										<select name="sex">
											<option value="1">Masculin</option>
											<option value="0">Féminin</option>
										</select>
									</div>
									<div>
										<label>Groupe sanguin</label>
										<input type="text" name="blood_group" placeholder="Ex: A+">
									</div>
								</div>
							</div>

							<div>
								<div class="mdl-prof__champ">
									<label>Photo</label>
									<label class="mdl-prof__photo">
										<i class="bi-camera" style="font-size:20px;"></i>
										<span>Ajouter</span>
										<input type="file" name="teacher_image" accept="image/*">
									</label>
								</div>
								<div class="mdl-prof__champ">
									<label>Téléphone <span class="req">*</span></label>
									<input type="tel" name="phone" class="requierd-prof-3" placeholder="034 00 000 00">
								</div>
								<div class="mdl-prof__champ">
									<label>Email <span class="req">*</span></label>
									<input type="email" name="email" class="requierd-prof-4" placeholder="email@exemple.com">
								</div>
								<div class="mdl-prof__champ">
									<label>Diplôme <span class="req">*</span></label>
									<input type="text" name="diplome" class="requierd-prof-5" placeholder="Master, Doctorat...">
								</div>
								<div class="mdl-prof__champ">
									<label>Religion</label>
									<select name="religion">
										<option>Adventiste</option>
										<option>Non Adventiste</option>
									</select>
								</div>
								<div class="mdl-prof__champ">
									<label>Position <span class="req">*</span></label>
									<input type="text" name="position" class="requierd-prof-6" placeholder="Professeur, Assistant...">
								</div>
							</div>

						</div>
					</div>

					<div class="mdl-prof__pied">
						<button type="button" class="mdl-prof__btn mdl-prof__btn--annuler" id="cancelnotifAddProf">Annuler</button>
						<button type="button" class="mdl-prof__btn mdl-prof__btn--valider" id="btnAddProf">
							<i class="bi-check-lg"></i> Enregistrer
						</button>
					</div>

				</form>

			</div>
		</div>
	</div>


<script src="<?=$app_base?>/src/js/cours.sigle.js?v=20260915-1"></script>
<script type="text/javascript">
	$(document).ready(function() {
		/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
			$('#mentionSelect').on('change',function(){
				var mentionSelect = $(this).val();
					
					$.ajax({
					url:"<?=$app_base?>/src/services/parcours.live.addCours",
					method:"POST",
					data:{mentionSelect:mentionSelect},

					success:function(data){
						$("#parcours").html(data);
					}
				});
			});
		
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		$('#addCours').click(function(){
			rangerLeRideau();
			$('#bigNotifCours').css({'display':'block'});
			$('#sigleInput').focus();
		});
		
		$('#cancelnotifAddCours, #closeAddCours, #overlayAddCours').click(function(){
			$('#bigNotifCours').css({'display':'none'});
		});
		/* « Enregistrer » n'est actif que si le sigle et le titre sont saisis ET
		   que le sigle n'est pas déjà pris. Le sigle est vérifié pendant la
		   frappe par src/js/cours.sigle.js ; addCours.php refuse aussi un
		   doublon, au cas où une requête contournerait la modale. */
		var sigleCours = null;
		function majBoutonCours() {
			var etat = sigleCours ? sigleCours.etat() : { libre: true, enCours: false };
			var complet = $.trim($('.requierd-cours-1').val()) !== '' && $.trim($('.requierd-cours-2').val()) !== '';
			$('#btnAddCours').attr('class', 'mdl-prof__btn mdl-prof__btn--valider' + (complet && etat.libre && !etat.enCours ? '' : ' toolInactive'));
		}
		function notifierCours(type, message) {
			if (window.Toast && typeof Toast[type] === 'function') { Toast[type](message); }
			else if (window.Toast) { Toast.show(message, type); }
			else if (window.console) { console.warn(message); }
		}
		if (window.CoursSigle && document.getElementById('sigleInput')) {
			sigleCours = CoursSigle.surveiller(document.getElementById('sigleInput'), {
				avis: document.getElementById('sigleAvis'),
				classeAvis: 'mdl-prof__avis',
				classes: { ok: 'mdl-prof__avis--ok', doublon: 'mdl-prof__avis--doublon', attente: 'mdl-prof__avis--attente' },
				classeInvalide: 'champ-invalide',
				surChangement: majBoutonCours
			});
		}
		$('.requierd-cours-1, .requierd-cours-2').on('input keyup', majBoutonCours);

		$('.form-no-refrech-cours').on('submit',function (e) {
			e.preventDefault();

			var etat = sigleCours ? sigleCours.etat() : { libre: true, enCours: false };
			if (!etat.libre || etat.enCours) { $('#sigleInput').focus(); return; }

			var formulaire = this;
			var url = '<?=$app_base?>/app/.cours/addCours';
			$('#btnAddCours').attr('class', 'mdl-prof__btn mdl-prof__btn--valider toolInactive');

			$.ajax({ url: url, method: 'POST', data: $(this).serialize(), dataType: 'json' })
				.done(function (r) {
					if (r && r.ok) {
						notifierCours('success', r.message || 'Cours ajouté.');
						$('#bigNotifCours').css({'display':'none'});
						formulaire.reset();
						if (sigleCours) { sigleCours.reinitialiser(); }
					} else {
						notifierCours('error', (r && r.message) || 'Le cours n\'a pas été ajouté.');
					}
				})
				.fail(function (x) {
					var r = x.responseJSON || {};
					if (sigleCours && r.doublons && r.doublons.length) { sigleCours.forcerDoublons(r); }
					notifierCours('error', r.message || 'Le cours n\'a pas été ajouté. Réessayez.');
				})
				.always(majBoutonCours);
		});
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
		
		/* Les modales sont deplacees en fin de <body>.

		   Elles sont incluses par init/menubar.php, donc inserees TRES tot dans
		   le document — bien avant le contenu de la page. Or backdrop-filter ne
		   floute que ce qui est peint AVANT l element : le tableau, arrivant plus
		   loin dans le DOM, n entrait jamais dans l arriere-plan de la modale,
		   qui paraissait donc posee sur un aplat uni.

		   En fin de body, toute la page precede la modale et devient son
		   arriere-plan. C est aussi la place habituelle d une modale. */
		(function deplaceEnFinDeBody() {
			var aDeplacer = ['bigNotifProf', 'bigNotifCours'];
			aDeplacer.forEach(function (identifiant) {
				var el = document.getElementById(identifiant);
				if (el && el.parentElement !== document.body) {
					document.body.appendChild(el);
				}
			});
		})();
		// Deux declencheurs pour la meme modale : l entree du menu lateral et le
		// bouton pose sur la page des enseignants. Un seul gestionnaire, pour
		// que le comportement ne diverge pas entre les deux.
		/* Le conteneur de page = le premier enfant de <body> qui n est pas une
		   modale. On le floute pendant l ouverture. */
		function conteneurDePage() {
			return Array.prototype.filter.call(document.body.children, function (el) {
				return el.tagName === 'DIV' && el.id !== 'bigNotifProf' && el.id !== 'bigNotifCours';
			})[0] || null;
		}
		/* Le rideau de transition (src/js/transitions.js) est un overlay plein
		   ecran en z-index 9000. S il reste en position couvrante — chargement
		   interrompu, retour navigateur, onglet reactive — il masque la page,
		   et l on ne voit plus qu un aplat sombre derriere la modale.
		   On le remet au repos avant d ouvrir. */
		function rangerLeRideau() {
			var rideau = document.querySelector('.pt-stinger');
			if (rideau) { rideau.className = 'pt-stinger'; }
		}

		function flouterFond(actif) {
			var c = conteneurDePage();
			if (!c) { return; }
			if (actif) { c.classList.add('mdl-fond-floute'); }
			else { c.classList.remove('mdl-fond-floute'); }
		}

		/* Le flou suit la visibilite reelle de la modale.
		   L'accrocher aux seuls boutons de fermeture laissait le fond floute
		   apres un Echap, qui ferme par le gestionnaire clavier de
		   notificationGeneral.php sans passer par eux. Un observateur couvre
		   tous les chemins, y compris ceux qui seront ajoutes plus tard. */
		(function observerLesModales() {
			if (typeof MutationObserver === 'undefined') { return; }
			['bigNotifProf', 'bigNotifCours'].forEach(function (identifiant) {
				var modale = document.getElementById(identifiant);
				if (!modale) { return; }
				var visibleAvant = getComputedStyle(modale).display !== 'none';
				new MutationObserver(function () {
					var visible = getComputedStyle(modale).display !== 'none';
					if (visible === visibleAvant) { return; }
					visibleAvant = visible;
					flouterFond(visible);
				}).observe(modale, { attributes: true, attributeFilter: ['style', 'class'] });
			});
		})();

		/* Filet de securite si l observateur venait a manquer. Enregistre une
		   seule fois : place dans un gestionnaire de clic, il se serait
		   reenregistre a chaque ouverture. */
		$(document).on('keydown.mdlAjout', function (e) {
			if (e.key !== 'Escape' && e.keyCode !== 27) { return; }
			window.setTimeout(function () {
				var ouverte = ['bigNotifProf', 'bigNotifCours'].some(function (id) {
					var m = document.getElementById(id);
					return m && getComputedStyle(m).display !== 'none';
				});
				if (!ouverte) { flouterFond(false); }
			}, 30);
		});

		$('#addProf, #addProfPage').click(function(){
			

		rangerLeRideau();
			flouterFond(true);
			$('#bigNotifProf').css({'display':'block'});
		});
		
		$('#cancelnotifAddProf, #closeAddProf, #overlayAddProf').click(function(){
			flouterFond(false);
			$('#bigNotifProf').css({'display':'none'});
		});
		
		$('#btnAddProf').click(function() {
			var rec_1 = $('.requierd-prof-1').val();
			var rec_2 = $('.requierd-prof-2').val();
			var rec_3 = $('.requierd-prof-3').val();
			var rec_4 = $('.requierd-prof-4').val();
			var rec_5 = $('.requierd-prof-5').val();
			

			if (rec_1 !="" && rec_2 !="" && rec_3 !="" && rec_4 !="" && rec_5 !="") {
				$('#formToAddProf').attr('method','post');
				$('#formToAddProf').attr('action','<?=$app_base?>/app/.prof/addProf.php');
				$('#btnAddProf').attr('type','submit');

			}else{
				$('.requierd-prof-1').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-2').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-3').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-4').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				$('.requierd-prof-5').css({'border':'1px solid #FF6E6E','background':'#f4aeae'});
				
				$('#alert-prof').text('Ces zones sont obligatoires !');
			}
		});

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/	
	});
</script>
<style type="text/css">
	.input{
		padding: 0px 2px 0px 2px;
		font-size: 13px;
	}

	.inputDescript{
		padding: 2px 2px 2px 2px;
		font-size: 13px;
		border-radius: 5px;
		background: #f2f2f2;
	}
</style>