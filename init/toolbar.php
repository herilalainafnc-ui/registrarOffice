<?php
require_once(__DIR__ . '/../data/guard.ajax.php');
require_once(__DIR__ . '/../data/perimetre.php');
exigerConnexion();
/* Toolbar réservée aux superadmin (1), administrateur (2) et registraire (3).
   Le chef de mention n'en a que Trier et Filtres, sur la liste des étudiants :
   pas d'exports (ExportController les refuse d'ailleurs au-delà du niveau 3). */
$barreChef = isset($rg_user['level']) && $rg_user['level'] > 3 && perimetreChefMention();
if ($barreChef && ($page ?? '') !== 'accueil.php') { return; }
if (!isset($rg_user['level']) || ($rg_user['level'] > 3 && !$barreChef)) { return; }
?>
<style type="text/css">
	/* ============================================================
	   Barre d outils des listes. Couleurs : src/css/theme.css.
	   Bureau : une rangee d actions compactes, icone et libelle cote
	   a cote. Mobile : barre fixee en bas, icones seules si l ecran
	   est tres etroit.
	   ============================================================ */

	.toolbar-container {
		align-items: center;
		gap: 2px;
		padding: 8px 12px !important;
		transition: background-color .2s ease, border-color .2s ease;
	}

	.toolbar-section {
		align-items: center;
		gap: 2px;
		padding: 0 6px !important;
		border-right: 1px solid;
	}
	.toolbar-section:last-child { border-right: none; }

	.tool-btn {
		position: relative;
		display: inline-flex;
		flex-direction: row;
		align-items: center;
		gap: 8px;
		height: 38px;
		padding: 0 12px 0 6px;
		border: none;
		background: transparent;
		text-decoration: none;
		cursor: pointer;
		white-space: nowrap;
		transition: background-color .15s ease, color .15s ease;
	}

	.tool-icon {
		width: 28px;
		height: 28px;
		flex: 0 0 28px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 15px;
		transition: background-color .15s ease, color .15s ease, border-color .15s ease;
	}

	.tool-label {
		font-size: 13px;
		font-weight: 500;
		line-height: 1.2;
		letter-spacing: 0;
	}

	.toolInactive {
		opacity: .38;
		pointer-events: none;
		cursor: not-allowed;
	}

	/* Menus deroulants de la barre. */
	.toolbar-dropdown {
		padding: 6px !important;
		border-radius: 12px !important;
		background: rgb(var(--c-surface)) !important;
		border: 1px solid rgb(var(--c-line)) !important;
		box-shadow: var(--shadow-2) !important;
	}
	.toolbar-dropdown .dropdown-header {
		padding: 6px 10px;
		font-size: 10.5px;
		font-weight: 600;
		letter-spacing: .08em;
		text-transform: uppercase;
		color: rgb(var(--c-text-3));
	}
	.toolbar-dropdown li a { display: block; text-decoration: none; }
	.toolbar-dropdown li a p {
		margin: 1px 0;
		padding: 7px 10px;
		border-radius: 7px;
		font-size: 13px;
		color: rgb(var(--c-text-2));
		transition: background-color .12s ease, color .12s ease;
	}
	.toolbar-dropdown li a:hover p {
		background: rgb(var(--c-surface-2));
		color: rgb(var(--c-text));
	}

	.toolbar-dropdown-actions { min-width: 250px; }
	.toolbar-dropdown-actions .tool-btn {
		display: flex;
		width: 100%;
		height: auto;
		padding: 6px 8px !important;
		gap: 10px;
		white-space: normal;
		text-align: left;
	}
	.toolbar-dropdown-actions .tool-icon { width: 30px; height: 30px; flex-basis: 30px; }
	.toolbar-dropdown-actions .tool-label { font-size: 13px; }
	.toolbar-dropdown-actions .tool-btn.toolInactive { opacity: .4; }

	/* Export Excel : le vert du tableur, repere connu. */
	.tool-btn.tool-excel .tool-icon i { color: #22a35a; }
	.tool-btn.tool-excel:hover .tool-icon { background: #1f8f4e !important; border-color: #1f8f4e !important; }
	.tool-btn.tool-excel:hover .tool-icon i { color: #fff; }

	.tool-badge {
		position: absolute;
		top: 6px;
		right: 6px;
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: rgb(var(--c-danger));
	}

	/* Mobile : barre fixee en bas de l ecran. */
	@media (max-width: 1023px) {
		.toolbar-container {
			position: fixed !important;
			bottom: 0 !important;
			left: 0 !important;
			right: 0 !important;
			width: 100% !important;
			z-index: 1000;
			padding: 6px 6px !important;
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			scrollbar-width: none;
			border-top: 1px solid rgb(var(--c-line)) !important;
			border-bottom: none !important;
			box-shadow: 0 -8px 24px -12px rgb(0 0 0 / .4);
		}
		.toolbar-container::-webkit-scrollbar { display: none; }
		.toolbar-section { border-right: none; flex-shrink: 0; padding: 0 !important; }
		.toolbar-section > .tool-btn {
			flex-direction: column;
			height: auto;
			min-width: 60px;
			padding: 4px 6px;
			gap: 3px;
		}
		.toolbar-section > .tool-btn .tool-label { font-size: 10px; }
	}
	@media (max-width: 480px) {
		.toolbar-section > .tool-btn .tool-label { display: none; }
		.toolbar-section > .tool-btn { min-width: 46px; }
	}
</style>

<div class="toolbar-container w-full py-2 flex flex-nowrap overflow-x-auto <?=$txt_one_color?>">
<?php if (!$barreChef): ?>

	<!-- SECTION 1: Export & Stats -->
	<div class="toolbar-section flex-shrink-0 flex px-1">
		
		<a href="#" id="toolExports" class="tool-btn tool-export text-xs flex-1" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Exports & documents">
			<div class="tool-icon"><i class="bi bi-download"></i></div>
			<span class="tool-label">Exports</span>
		</a>
		<script>
		/* Arrivee par l'acces rapide « Exports » du tableau de bord (?exports=1) :
		   le menu s'ouvre seul, et le parametre quitte l'adresse (un rechargement
		   ne le rouvre pas). */
		(function () {
			var p = new URLSearchParams(location.search);
			if (p.get('exports') !== '1') { return; }
			function ouvrir() {
				var b = document.getElementById('toolExports');
				if (!b || !window.bootstrap || !window.bootstrap.Dropdown) { return; }
				window.bootstrap.Dropdown.getOrCreateInstance(b).show();
				var premier = b.parentElement.querySelector('.dropdown-menu a:not(.toolInactive)');
				if (premier) { premier.focus({ preventScroll: true }); }
				p.delete('exports');
				try { history.replaceState(history.state, '', location.pathname + (p.toString() ? '?' + p.toString() : '') + location.hash); } catch (e) {}
			}
			if (document.readyState === 'complete') { ouvrir(); } else { window.addEventListener('load', ouvrir); }
		})();
		</script>
		<ul class="dropdown-menu toolbar-dropdown toolbar-dropdown-actions" style="min-width:240px;">
		<a href="#" id="<?php
		if($page == "accueil.php" OR $page == "student.php" OR $page=="inscription.php") {
		 	echo "exportListStd";
		}elseif($page == "accueil.cours.php" OR $page == "cours.php") {
			echo "exportListCours";
		}elseif($page == "accueil.prof.php" OR $page == "prof.php") {
			echo "exportListProf";
		} ?>" class="tool-btn tool-export text-xs flex-1 <?php if($rg_user['level'] <=3) { echo "";}else{ echo "toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-filetype-pdf"></i>
			</div>
			<span class="tool-label">Exporter la liste</span>
		</a>
		
		<a id="exportStatistic" href="#" class="tool-btn tool-stats text-xs flex-1 <?php if($rg_user['level'] <=3) { echo "";}else{ echo "toolInactive";}?>">
			<div class="tool-icon">	
				<i class="bi bi-bar-chart-line-fill"></i>
			</div>
			<span class="tool-label">Statistique</span>
		</a>

		<a href="#" id="exportPresence" class="tool-btn tool-document text-xs flex-1 <?php if($rg_user['level'] <=3) { echo "";}else{ echo "toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-person-check-fill"></i>
			</div>
			<span class="tool-label">Fiche de présence</span>
		</a>

		<a href="#" id="exportExcel" class="tool-btn tool-excel text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-file-earmark-excel-fill"></i>
			</div>
			<span class="tool-label">Export Excel</span>
		</a>
		
		<a href="#" id="exportListFOP" title="Liste pour la FOP — frais généraux" class="tool-btn tool-document text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-file-text-fill"></i>
			</div>
			<span class="tool-label">Liste pour la FOP</span>
		</a>
		
		<a href="#" id="exportCanevas" title="Canevas de collecte de la MESupReS : les étudiants inscrits d'une année (Excel)" class="tool-btn tool-excel text-xs flex-1 <?php
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-file-earmark-spreadsheet-fill"></i>
			</div>
			<span class="tool-label">Canevas MESupReS</span>
		</a>

		<a href="#" id="finance" class="tool-btn tool-finance text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-currency-exchange"></i>
			</div>
			<span class="tool-label">Finance</span>
		</a>
		</ul>

	</div>

	<!-- SECTION 2: Mail & Communication -->	
	<div class="toolbar-section flex-shrink-0 flex px-1">

		<a href="#" class="tool-btn text-xs flex-1" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Communication">
			<div class="tool-icon"><i class="bi bi-envelope-fill"></i></div>
			<span class="tool-label">Communication</span>
		</a>
		<ul class="dropdown-menu toolbar-dropdown toolbar-dropdown-actions" style="min-width:240px;">
		<a href="#" id="exportListCSV" title="Modèle d'e-mails (fichier CSV)" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-envelope-at-fill"></i>
			</div>
			<span class="tool-label">Template mail CSV</span>
		</a>
		
		<a href="#" id="exportTicketMail" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-ticket-perforated-fill"></i>
			</div>
			<span class="tool-label">Tickets mail</span>
		</a>
		</ul>
		
	</div>

	<!-- SECTION 3: Work -->	
	<div class="toolbar-section flex-shrink-0 flex px-1">
		
		<a href="#" class="tool-btn text-xs flex-1" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Work Education">
			<div class="tool-icon"><i class="bi bi-briefcase-fill"></i></div>
			<span class="tool-label">Work Educ.</span>
		</a>
		<ul class="dropdown-menu toolbar-dropdown toolbar-dropdown-actions" style="min-width:240px;">
		<a href="#" id="worked" title="Liste Work Education (groupée)" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=6) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-person-lines-fill"></i>
			</div>
			<span class="tool-label">Worked</span>
		</a>
		
		<a href="#" id="workedSlip" title="Slips Work Education pour les examens" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=6) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-card-checklist"></i>
			</div>
			<span class="tool-label">Worked Slips</span>
		</a>

		<a href="#" id="filesSlip" title="Slips de vérification des dossiers" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=6) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-folder-check"></i>
			</div>
			<span class="tool-label">Files Slips</span>
		</a>
		</ul>

	</div>
<?php endif; ?>

	<!-- SECTION 4: Sort, Filter & Search -->
	<div class="toolbar-section flex-shrink-0 flex px-1">
		
<?php
	/* Sur les trois listes, Trier et Filtres pilotent la liste EN ENTIER avec
	   src/js/listes.js : ils se combinent avec la recherche au lieu de la
	   remplacer. Le libelle dit le tri en cours, la pastille le nombre de
	   filtres actifs. Ailleurs (fiches), ils restent inactifs comme avant. */
	$pageListe = in_array($page, ['accueil.php', 'accueil.cours.php', 'accueil.prof.php'], true);
?>
<?php if ($pageListe): ?>
		<a href="#" class="tool-btn tool-sort text-xs flex-1" id="listeTriBouton" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-haspopup="menu" title="Trier la liste">
			<div class="tool-icon"><i class="bi bi-sort-down" aria-hidden="true"></i></div>
			<span class="tool-label" id="listeTriLibelle">Trier</span>
		</a>
		<div class="dropdown-menu toolbar-dropdown liste-menu" id="listeTriMenu" role="menu" aria-label="Trier la liste"></div>

		<a href="#" class="tool-btn tool-filter text-xs flex-1" id="listeFiltresBouton" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="dialog" title="Filtrer la liste">
			<div class="tool-icon"><i class="bi bi-funnel" aria-hidden="true"></i></div>
			<span class="tool-label">Filtres</span>
			<span class="liste-pastille" id="listeFiltresCompte" hidden></span>
		</a>
		<div class="dropdown-menu toolbar-dropdown liste-filtres" id="listeFiltresMenu" aria-label="Filtrer la liste"></div>
<?php else: ?>
		<a href="#" class="tool-btn tool-sort text-xs flex-1 toolInactive" aria-disabled="true" tabindex="-1">
			<div class="tool-icon"><i class="bi bi-sort-down" aria-hidden="true"></i></div>
			<span class="tool-label">Trier</span>
		</a>
		<a href="#" class="tool-btn tool-filter text-xs flex-1 toolInactive" aria-disabled="true" tabindex="-1">
			<div class="tool-icon"><i class="bi bi-funnel" aria-hidden="true"></i></div>
			<span class="tool-label">Filtres</span>
		</a>
<?php endif; ?>
		<!-- Search Button -->
		<!-- <a href="#" class="tool-btn tool-search text-xs flex-1 searchTool">
			<div class="tool-icon">
				<i class="bi bi-search"></i>
			</div>
			<span class="tool-label">Rechercher</span>
		</a> -->

	</div>
<?php if (!$barreChef): ?>

	<!-- SECTION 5: Badge & Media -->
	<div class="toolbar-section flex-shrink-0 flex px-1">
		
		<a href="#" class="tool-btn text-xs flex-1" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Badges & photos">
			<div class="tool-icon"><i class="bi bi-person-badge-fill"></i></div>
			<span class="tool-label">Badges</span>
		</a>
		<ul class="dropdown-menu toolbar-dropdown toolbar-dropdown-actions" style="min-width:240px;">
		<a href="<?=$app_base?>/src/data.topdf?ptype=BadgeGr" id="badgeGr" title="Badges étudiants en groupe : sélection, aperçu et PDF" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-person-badge-fill"></i>
			</div>
			<span class="tool-label">Badge</span>
		</a>

		<a href="#" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-file-excel-fill"></i>
			</div>
			<span class="tool-label">Sans Photos</span>
		</a>
		
		<a href="#" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-download"></i>
			</div>
			<span class="tool-label">Photos en masse</span>
		</a>
		</ul>

	</div>
<?php endif; ?>

	<!-- SECTION 6: Settings -->
	<!-- <div class="toolbar-section flex-shrink-0 flex px-1">
		
		<a href="<?=$app_base?>/settings" class="tool-btn tool-settings text-xs flex-1">
			<div class="tool-icon">
				<i class="bi bi-gear-fill"></i>
			</div>
			<span class="tool-label">Paramètres</span>
		</a>

	</div> -->
	
</div>

<?php if (!$barreChef): /* les fenetres des exports */ ?>
<!-- NOTIFICATION MANAGER --><?php require('./main/notificationGeneral.php');?>

<!-- BIG NOTIF MANAGER --><?php require_once(__DIR__ . '/../src/main/bigNotif.php');?>
<?php endif; ?>

<script type="text/javascript">

	$(document).ready(function() {
		$('.searchTool').click(function() {
			$('.search').css({'display':'block'});
			$('#std-search').focus();
			$('#cours-search').focus();
			$('#prof-search').focus();

		});
/* Tri et filtres des listes : src/js/listes.js (voir data/listes.php). */

	});
</script>