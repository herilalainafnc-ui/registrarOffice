
<?php 
require_once(__DIR__ . '/../../data/guard.ajax.php');
require_once(__DIR__ . '/../../data/cours.sigle.php');
exigerConnexion(); refuserEtudiant(); // notes : réservées au personnel
	/*::::::::::::::::::::::: CHARGEMENT AUTONOME (AJAX) ::::::::::::::::::::::*/
	
	// Si appelé directement via AJAX, charger les dépendances
	if (!isset($profil) && (isset($_GET['student_id']) || isset($_GET['id']))) {
		require_once('../../data/backdb.php');
		
		// Démarrer la session pour accéder aux variables de couleur et utilisateur
		if (session_status() === PHP_SESSION_NONE) {
			ini_set('session.gc_maxlifetime', 36000);
			ini_set('session.cookie_lifetime', 36000);
			session_start();
		}
		
		// Calculer app_base (même logique que init/head.php)
		$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
		$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
		$app_base = substr($_app_root, strlen($_doc_root));
		if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
		
		// Récupérer l'ID utilisateur depuis la session
		$rg_id = $_SESSION['user_id'] ?? 0;
		
		// Récupérer les couleurs depuis la session
		$bg_one_color = $_SESSION['bg_one_color'] ?? 'bg-slate-800';
		$bg_two_color = $_SESSION['bg_two_color'] ?? 'bg-slate-900';
		$bg_three_color = $_SESSION['bg_three_color'] ?? 'bg-slate-900';
		$bg_four_color = $_SESSION['bg_four_color'] ?? 'bg-slate-700';
		$bg_five_color = $_SESSION['bg_five_color'] ?? 'bg-slate-700';
		
		$student_id = $_GET['student_id'] ?? '';
		$id = $_GET['id'] ?? '';
		$ajax_session_id = $_GET['session_id'] ?? '';
		
		if (!empty($student_id)) {
			$stmt = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id AND remove != 1 LIMIT 1');
			$stmt->execute(['student_id' => $student_id]);
			$profil = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($profil) {
				$id = $profil['id'];
				$student_id = $profil['student_id'];
				$student_nom = $profil['student_nom'];
				$student_prenom = $profil['student_prenom'];
				$level = $profil['annee_etude'];
				$annee_scolaire = $profil['annee_scolaire'];
				$etude_envisage = $profil['etude_envisage'];
				$etude_option = $profil['etude_option'];
			}
		}
	}
	
	// Récupérer le session_id (depuis GET pour AJAX, sinon depuis le formulaire parent)
	$current_session_id = $ajax_session_id ?? '';
?>

<?php 
	/*::::::::::::::::::::::: VÉRIFICATION SUSPENSION ::::::::::::::::::::::*/
	
	// Vérifier si l'étudiant est suspendu
	$isSuspended = false;
	if (isset($profil['suspended']) && $profil['suspended'] == 1) {
		$dateFin = $profil['date_fin_suspension'];
		if (empty($dateFin) || strtotime($dateFin) >= strtotime(date('Y-m-d'))) {
			$isSuspended = true;
		}
	}
	
	if ($isSuspended) {
?>
<div class="mt-2 p-4 text-center">
	<div class="bg-orange-500 text-white p-6 rounded-lg shadow-lg">
		<i class="bi bi-exclamation-triangle-fill text-5xl"></i>
		<h3 class="text-xl font-bold mt-3">ÉTUDIANT SUSPENDU</h3>
		<p class="mt-2">Cet étudiant est actuellement suspendu et ne peut pas prendre de nouveaux cours.</p>
		<?php if (!empty($profil['date_fin_suspension'])) { ?>
			<p class="mt-2 text-sm">Fin de suspension prévue: <b><?= date('d/m/Y', strtotime($profil['date_fin_suspension'])) ?></b></p>
		<?php } ?>
		<?php if (!empty($profil['motif_suspension'])) { ?>
			<p class="mt-2 text-sm bg-orange-600 p-2 rounded">Motif: <?= htmlspecialchars($profil['motif_suspension']) ?></p>
		<?php } ?>
	</div>
</div>
<?php
		return; // Arrêter l'exécution du reste de la page
	}
?>

<?php
	/* Annee affichee : celle de la liste, et celle ou les cours cochés
	   s'inscrivent (data/cours.offerts.php). Par defaut, l'annee en cours. */
	require_once __DIR__ . '/../../data/cours.offerts.php';
	/* Au guichet d'inscription (inscription/inscription.php, qui inclut ce
	   volet ou le recharge avec session_id), on inscrit a la session en
	   cours : pas de choix d'annee. Le choix sert sur la fiche, pour
	   completer un cursus passe. */
	$coursGuichet = isset($_GET['session_id'])
		|| str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), (defined('APP_BASE') ? APP_BASE : '') . '/inscription');
	$avecChoixAnnee = !$coursGuichet;
	$academicYear = ($avecChoixAnnee ? coursAnneeValide($_REQUEST['academic_year'] ?? null) : null) ?? coursAnneeScolaire($dtb, null);
	$toutProgramme = $avecChoixAnnee && !empty($_REQUEST['programme']);
	$anneesChoix = coursAnneesChoix($dtb, coursAnneeScolaire($dtb, null));
	if (!in_array($academicYear, $anneesChoix, true)) { $anneesChoix[] = $academicYear; rsort($anneesChoix); }
?>
<?php if ($avecChoixAnnee): ?>
<?php /* Barre de l'annee : le choix, et une pastille « Annee passee » qui
         reste sous les yeux tant qu'on ajoute hors de l'annee en cours. La
         phrase d'explication part en toast au changement d'annee : elle
         allongeait la barre sur deux lignes. */ ?>
<style>
	.cours-annee {
		display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px;
		margin: 8px 8px 0; padding: 8px 12px; border-radius: 10px;
		background: rgb(var(--c-surface-2)); border: 1px solid rgb(var(--c-line));
		font-size: 13px; color: rgb(var(--c-text));
	}
	.cours-annee__choix { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; }
	.cours-annee__libelle { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-weight: 600; color: rgb(var(--c-text-2)); }
	.cours-annee__libelle i { color: rgb(var(--c-accent-text)); }
	.cours-annee__select {
		height: 32px; padding: 0 30px 0 10px; border-radius: 8px; cursor: pointer;
		background-color: rgb(var(--c-inset)); color: rgb(var(--c-text));
		border: 1px solid rgb(var(--c-line-strong)); font: inherit; font-weight: 600; font-variant-numeric: tabular-nums;
	}
	.cours-annee__select:focus-visible { outline: none; box-shadow: var(--ring); border-color: rgb(var(--c-accent)); }
	.cours-annee__passee {
		display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px;
		background: rgb(var(--c-warning) / .14); color: rgb(var(--c-warning)); border: 1px solid rgb(var(--c-warning) / .35);
		font-size: 12px; font-weight: 600; white-space: nowrap;
	}
	.cours-annee__passee[hidden] { display: none; }
	.cours-annee__programme {
		display: inline-flex; align-items: center; gap: 8px; margin: 0; min-height: 32px; padding: 0 12px; border-radius: 8px;
		cursor: pointer; color: rgb(var(--c-text-2)); border: 1px solid rgb(var(--c-line)); user-select: none;
		transition: background-color .15s ease, border-color .15s ease, color .15s ease;
	}
	.cours-annee__programme:hover { background: rgb(var(--c-raised)); color: rgb(var(--c-text)); }
	.cours-annee__programme input { width: 16px; height: 16px; margin: 0; accent-color: rgb(var(--c-accent)); cursor: pointer; }
	.cours-annee__programme:has(input:checked) { border-color: rgb(var(--c-accent) / .6); background: rgb(var(--c-accent) / .12); color: rgb(var(--c-text)); }
	.cours-annee__programme:has(input:focus-visible) { box-shadow: var(--ring); }
</style>
<div class="cours-annee" id="coursAnneeBarre">
	<div class="cours-annee__choix">
		<label for="coursAnnee" class="cours-annee__libelle"><i class="bi-calendar3" aria-hidden="true"></i> Année des cours</label>
		<select id="coursAnnee" class="cours-annee__select">
<?php foreach ($anneesChoix as $ac): ?>
			<option value="<?=htmlspecialchars($ac)?>" data-passee="<?= coursAnneePassee($ac) ? '1' : '0' ?>"<?= $ac === $academicYear ? ' selected' : '' ?>><?=htmlspecialchars($ac)?></option>
<?php endforeach; ?>
		</select>
		<span class="cours-annee__passee" id="coursAnneeAvis" role="status"
		      title="Les cours cochés s'ajoutent à cette année, pas à l'année en cours"<?= coursAnneePassee($academicYear) ? '' : ' hidden' ?>>
			<i class="bi-clock-history" aria-hidden="true"></i> Année passée
		</span>
	</div>
	<label class="cours-annee__programme" title="Sans cette case : seulement les cours offerts cette année-là (page « Cours par session »)">
		<input type="checkbox" id="coursProgramme"<?= $toutProgramme ? ' checked' : '' ?>>
		Tout le programme du niveau
	</label>
</div>
<?php endif; ?>
<div id="coursListContainer" class="mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 280px);">
<?php require __DIR__ . '/new.cours.liste.php'; ?>
</div>
<script type="text/javascript">
	$(document).ready(function(){

	    window.onload = function() {
	        var a = '<?=$a-1?>';
	        window.location.hash = '#year'+a;
	    };

		// Attacher les événements du formulaire au chargement
		bindFormEvents();
		bindCoursEvents();
	});
	
	var hoverClass = "hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black";
	
	// Délégation d'événements pour les clics sur les lignes et checkboxes de cours
	// Ces événements survivent au remplacement du DOM via AJAX
	function bindCoursEvents() {
		var container = $('#coursListContainer');
		
		// Clic sur une ligne de cours (tr avec id commençant par "cours")
		container.off('click', 'tr[id^="cours"]').on('click', 'tr[id^="cours"]', function(e) {
			// Ignorer si le clic est directement sur la checkbox
			if ($(e.target).is('input[type="checkbox"]')) return;
			var id = $(this).attr('id');
			var chkId = id.replace('cours', 'chk');
			var chk = $('#' + chkId);
			if (chk.prop("checked") == false) {
				$(this).attr("class", "bg-blue-500");
				chk.prop("checked", true);
			} else {
				$(this).attr("class", hoverClass);
				chk.prop("checked", false);
			}
		});
		
		// Clic sur une checkbox (change la couleur de la ligne)
		container.off('change', 'input[id^="chk"]').on('change', 'input[id^="chk"]', function() {
			var id = $(this).attr('id');
			var rowId = id.replace('chk', 'cours');
			var row = $('#' + rowId);
			if ($(this).prop("checked")) {
				row.attr("class", "bg-blue-500");
			} else {
				row.attr("class", hoverClass);
			}
		});
		
		// Cocher tout
		container.off('click', 'a[id^="selectAll"]').on('click', 'a[id^="selectAll"]', function(e) {
			e.preventDefault();
			var suffix = $(this).attr('id').replace('selectAll', '');
			$(this).addClass('hidden');
			$('#deselectAll' + suffix).removeClass('hidden');
			$('input[id^="chk' + suffix + '"]').each(function() {
				$(this).prop("checked", true);
				var rowId = $(this).attr('id').replace('chk', 'cours');
				$('#' + rowId).attr("class", "bg-blue-500");
			});
		});
		
		// Décocher tout
		container.off('click', 'a[id^="deselectAll"]').on('click', 'a[id^="deselectAll"]', function(e) {
			e.preventDefault();
			var suffix = $(this).attr('id').replace('deselectAll', '');
			$(this).addClass('hidden');
			$('#selectAll' + suffix).removeClass('hidden');
			$('input[id^="chk' + suffix + '"]').each(function() {
				$(this).prop("checked", false);
				var rowId = $(this).attr('id').replace('chk', 'cours');
				$('#' + rowId).attr("class", hoverClass);
			});
		});
		
		// Changement de session/année → activer le bouton submit
		container.off('change', 'select[id^="scolarA"], select[id^="scolarSs"]').on('change', 'select[id^="scolarA"], select[id^="scolarSs"]', function() {
			var id = $(this).attr('id');
			// Extraire le suffixe (ex: "11", "12", "21", etc.)
			var suffix = id.replace('scolarA', '').replace('scolarSs', '');
			var scolarA = $('#scolarA' + suffix).val();
			var scolarSs = $('#scolarSs' + suffix).val();
			if (scolarA != '' && scolarSs != '') {
				$('#submit' + suffix).attr('class', 'submiting px-2 text-center py-0 m-1 text-white bg-black');
			}
		});
	}
	
	// Déterminer le chemin de base selon le contexte
	var basePath = window.location.pathname.includes('/inscription/') ? '<?=$app_base?>/app/.student/' : '<?=$app_base?>/app/.student/';
	
	// Fonction pour rafraîchir uniquement la liste des cours
	/* Annee choisie et « tout le programme » : la liste rechargee (apres un
	   ajout, ou en changeant d'annee) garde l'une et l'autre. */
	$(document).off('change.coursAnnee').on('change.coursAnnee', '#coursAnnee, #coursProgramme', function () {
		var annee = $('#coursAnnee').val();
		var passee = $('#coursAnnee option:selected').data('passee') == 1;
		$('#coursAnneeAvis').prop('hidden', !passee);
		/* L'explication, une fois, au moment ou l'on change d'annee. */
		if (this.id === 'coursAnnee' && window.Toast) {
			if (passee) {
				Toast.warning('Les cours cochés s\'ajouteront à l\'année ' + $('<span>').text(annee).html() + ', pas à l\'année en cours.', { title: 'Année passée' });
			} else {
				Toast.info('Les cours cochés s\'ajoutent à l\'année en cours.', { title: $('<span>').text(annee).html() });
			}
		}
		refreshCoursList(true);
	});

	function refreshCoursList(remonter) {
		var scrollTop = remonter ? 0 : $('#coursListContainer').scrollTop();
		var currentHash = window.location.hash;

		// Construire l'URL relative depuis la page actuelle
		var ajaxUrl = '<?=$app_base?>/app/.student/get-cours-list.php';

		$.ajax({
			url: ajaxUrl,
			method: 'GET',
			data: {
				id: <?=json_encode($id ?? '')?>,
				student_id: <?=json_encode($student_id ?? '')?>,
				academic_year: $('#coursAnnee').val() || '',
				programme: $('#coursProgramme').prop('checked') ? 1 : 0,
				choix: $('#coursAnnee').length ? 1 : 0   // 0 : guichet d'inscription
			},
			beforeSend: function() {
				$('#coursListContainer').css('opacity', '0.5');
			},
			success: function(data) {
				$('#coursListContainer').html(data).css('opacity', '1');
				// Restaurer la position du scroll
				$('#coursListContainer').scrollTop(scrollTop);
				// Réattacher les événements du formulaire
				bindFormEvents();
			},
			error: function() {
				$('#coursListContainer').css('opacity', '1');
				Toast.error('Erreur lors du rafraîchissement de la liste');
			}
		});
	}
	
	// Fonction pour réattacher les événements après le rafraîchissement AJAX
	function bindFormEvents() {
		$(".form-newCours").off('submit').on('submit', function (e) {
			e.preventDefault();
			 
			var form = $(this);
			var submitBtn = form.find('button[type="submit"]');
			var originalText = submitBtn.text();
			 
			// Vérifier si au moins une case est cochée
			if (form.find('input[type="checkbox"]:checked').length === 0) {
				Toast.warning('Veuillez sélectionner au moins un cours');
				return;
			}
			 
			// Désactiver le bouton pendant l'envoi
			submitBtn.prop('disabled', true).text('Enregistrement...');

			var url = form.data('action');
			
			if (!url) {
				Toast.error('URL du formulaire non trouvée');
				submitBtn.prop('disabled', false).text(originalText);
				return;
			}
			
			var data = $(this).serialize();

			/* Le message dit OU les cours sont partis : session et annee. */
			var nbCoches = form.find('input[type="checkbox"]:checked').length;
			var ou = $('<span>').text(form.find('[name="semesterSession"]').val() + ' ' + form.find('[name="annee_scolaire"]').val()).html();

			$.post(url, data, function(response){
				Toast.success(nbCoches + (nbCoches > 1 ? ' cours ajoutés' : ' cours ajouté') + ' au transcript — ' + ou + '.');

				submitBtn.prop('disabled', false).text(originalText);
				refreshCoursList();
				
			}).fail(function(){
				Toast.error('Erreur lors de l\'ajout du cours');
				submitBtn.prop('disabled', false).text(originalText);
			});
		});
	}

</script>