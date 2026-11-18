<?php
/* Media et superadmin seuls, meme par l'adresse du fichier : le niveau se lit
   dans la session, comme DashboardController::studio. */
require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion(); // connecte, sinon /login
if (!in_array((int) ($_SESSION['user_level'] ?? 99), [ROLE_SUPERADMIN, ROLE_MEDIA], true)) {
	http_response_code(403);
	echo '<h1>403 - Accès refusé</h1><p>Le Studio UAZ est réservé au service Media.</p>';
	exit;
}
require_once(__DIR__ . '/../data/studio.php');
$stPresets = array_keys(studioPresets());
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Studio UAZ</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/studio/charte.css?v=<?=@filemtime(__DIR__ . '/studio/charte.css')?>">
	<link rel="stylesheet" href="<?=$app_base?>/src/css/studio.css?v=<?=@filemtime(__DIR__ . '/css/studio.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm st-page">
<?php
/* =============================================================================
   Studio UAZ (Application) — pour le Media et le superadmin
   Un visuel a partir d'un preset (src/studio/presets/) : on remplit des champs,
   on place une image, on choisit un format, on exporte. Les couleurs, la
   police et la grille sont celles de la charte de l'universite et ne se
   changent pas (docs/studio-uaz/).
   L'apercu est rendu par src/studio/studio-rendu.js ; l'export par le service
   de rendu (outils/studio-rendu/), avec le meme moteur, via
   app/studio.export.php.
   Etapes (20/10/2026) : 1, le preset Evenement en 4:5 ; 2, les six presets ;
   3, les formats carre, story (zones d'Instagram) et A4, le PDF (et celui de
   l'imprimeur : fond perdu, traits de coupe) et « Tout exporter » en ZIP.
   Etape 5 (21/10/2026) : visuels enregistres sur le serveur (brouillon
   automatique, duplication, historique, annuler/retablir), circuit de
   validation (obligatoire pour COM et REC), legende suggeree, journal des
   exports (data/studio.visuels.php, app/studio.visuels.php).
   26/10/2026 : la disposition de Figma, dans le menu du registrar. A gauche le
   fichier (menu, nom, etat), les formats (comme les pages) et les calques ; au
   centre le plan de travail (zoom, main) et la barre d'outils flottante en bas ;
   a droite les proprietes de l'element choisi (Design) et la publication.
   ========================================================================== */
?>
	<div class="h-screen w-full <?=$bg_three_color?>">
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<main class="st" id="stApp"
					data-base="<?=$app_base?>/src/studio/"
					data-export="<?=$app_base?>/app/studio.export.php"
					data-retouche="<?=$app_base?>/retouche-photo"
					data-compte="<?=(int) ($_SESSION['user_id'] ?? 0)?>"
					data-visuels="<?=$app_base?>/app/studio.visuels.php"
					data-admin="<?=$app_base?>/app/studio.admin.php"
					data-presets="<?=e(json_encode($stPresets))?>">

					<!-- ============ GAUCHE : le fichier, les formats, les calques (comme Figma) -->
					<aside class="st-gauche" aria-label="Visuel et calques">
						<header class="st-fichier">
							<div class="st-fichier__ligne">
								<button type="button" class="st-icone st-icone--menu" id="stMenuPrincipal" aria-haspopup="menu" aria-expanded="false" aria-controls="stMenuFichier" aria-label="Menu du Studio" title="Menu : mes visuels, nouveau, historique…"><i class="bi bi-list" aria-hidden="true"></i><span class="st-point" id="stMenuPoint" hidden></span></button>
								<div class="st-fichier__nom">
									<span class="st-surtitre">Studio UAZ</span>
									<h1 id="stTitre">Sans titre</h1>
								</div>
								<span class="st-fichier__outils">
									<button type="button" class="st-icone st-icone--petit" id="stAnnuler" title="Annuler (Ctrl+Z)" aria-label="Annuler" disabled><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
									<button type="button" class="st-icone st-icone--petit" id="stRetablir" title="Rétablir (Ctrl+Maj+Z)" aria-label="Rétablir" disabled><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
								</span>
							</div>
							<div class="st-fichier__etat" aria-live="polite">
								<span class="st-badge st-badge--brouillon" id="stStatut">Brouillon</span>
								<span class="st-sauve" id="stSauve">Pas encore enregistré</span>
							</div>
							<label class="st-type">
								<span class="st-type__libelle">Type</span>
								<select id="stPreset" class="st-saisie st-saisie--compacte" aria-label="Type de visuel"></select>
							</label>
						</header>
						<div class="st-onglets st-onglets--gauche" role="tablist" aria-label="Panneau de gauche">
							<button type="button" class="st-onglet" role="tab" id="stOngletCalques" aria-controls="stPanneauCalques" aria-selected="true">Calques</button>
							<button type="button" class="st-onglet" role="tab" id="stChamps" aria-controls="stPanneauChamps" aria-selected="false" tabindex="-1" title="Tous les champs, pour le clavier et ceux qui ne paraissent pas sur le visuel">Champs</button>
						</div>
						<div class="st-gauche__corps st-calques-panneau" id="stPanneauCalques" role="tabpanel" aria-labelledby="stOngletCalques">
							<section class="st-section" aria-labelledby="stFormatsTitre">
								<h2 class="st-section__titre" id="stFormatsTitre">Formats</h2>
								<div class="st-pages" role="tablist" aria-label="Format" aria-orientation="vertical" id="stFormats"></div>
							</section>
							<section class="st-section st-section--calques" aria-labelledby="stCalquesTitre">
								<h2 class="st-section__titre" id="stCalquesTitre">Calques</h2>
								<!-- les blocs ajoutes : le premier est au-dessus des autres ; glisser une ligne change l'ordre -->
								<div class="st-groupe" id="stCalquesBloc" hidden>
									<h3 class="st-groupe__titre">Blocs ajoutés</h3>
									<ul class="st-calques" id="stCalques"></ul>
								</div>
								<!-- les elements du visuel (la charte) : ils se modifient et se deplacent, ne se suppriment pas -->
								<div class="st-groupe">
									<h3 class="st-groupe__titre">Éléments de la charte <i class="bi bi-lock" aria-hidden="true" title="Ils ne se suppriment pas"></i></h3>
									<ul class="st-calques" id="stCalquesVisuel"></ul>
								</div>
							</section>
						</div>
						<div class="st-gauche__corps" id="stPanneauChamps" role="tabpanel" aria-labelledby="stChamps" hidden>
							<p class="st-aide st-aide--panneau">Tous les champs du visuel : pour le clavier, et pour ceux qui ne paraissent pas sur le visuel affiché.</p>
							<form id="stForm" class="st-formulaire" novalidate autocomplete="off"></form>
						</div>
					</aside>

					<!-- ============ CENTRE : le plan de travail et la barre d'outils -->
					<section class="st-centre" aria-label="Plan de travail">
						<div class="st-scene" id="stScene">
							<button type="button" class="st-cadre__nom" id="stCadreNom" title="Le format affiché (Maj+1 : tout voir)">Post 4:5</button>
							<div class="st-cadre" id="stCadre">
								<div id="stVisuel"></div>
								<div class="st-marges" id="stMargesCalque" hidden aria-hidden="true"></div>
								<!-- Story : ce que couvre l'interface d'Instagram (apercu seulement, jamais exporte) -->
								<div class="st-zones" id="stZonesCalque" hidden aria-hidden="true">
									<div class="st-zone st-zone--haut"><span>Interface d'Instagram</span></div>
									<div class="st-zone st-zone--bas"><span>Interface d'Instagram</span></div>
								</div>
							</div>
							<p class="st-lecture" id="stLecture" role="status" hidden></p>
							<div class="st-toast" id="stToast">
								<p class="st-etat" id="stEtat" role="status" aria-live="polite"></p>
								<button type="button" class="st-icone st-icone--petit" id="stToastFermer" aria-label="Fermer le message"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
							</div>
							<!-- La barre d'outils de Figma, en bas : un outil de creation se tire sur le visuel (un clic : taille par defaut), puis rend la main a « Deplacer » -->
							<div class="st-outils" id="stOutils" role="toolbar" aria-label="Outils">
								<button type="button" class="st-outils__btn" data-outil="deplacer" aria-pressed="true" aria-label="Déplacer (V)" data-astuce="Déplacer · V"><i class="bi bi-cursor" aria-hidden="true"></i></button>
								<button type="button" class="st-outils__btn" data-outil="main" aria-pressed="false" aria-label="Main (H, ou Espace maintenu)" data-astuce="Main · H ou Espace"><i class="bi bi-hand-index" aria-hidden="true"></i></button>
								<span class="st-outils__sep" aria-hidden="true"></span>
								<button type="button" class="st-outils__btn" data-outil="rect" aria-pressed="false" aria-label="Rectangle (R)" data-astuce="Rectangle · R"><i class="bi bi-square" aria-hidden="true"></i></button>
								<button type="button" class="st-outils__btn" data-outil="cercle" aria-pressed="false" aria-label="Ellipse (O)" data-astuce="Ellipse · O"><i class="bi bi-circle" aria-hidden="true"></i></button>
								<button type="button" class="st-outils__btn" data-outil="ligne" aria-pressed="false" aria-label="Ligne (L)" data-astuce="Ligne · L"><i class="bi bi-slash-lg" aria-hidden="true"></i></button>
								<span class="st-outils__sep" aria-hidden="true"></span>
								<button type="button" class="st-outils__btn" data-outil="texte" aria-pressed="false" aria-label="Texte (T)" data-astuce="Texte · T"><i class="bi bi-fonts" aria-hidden="true"></i></button>
								<button type="button" class="st-outils__btn" data-outil="image" aria-label="Image (Ctrl+Maj+K)" data-astuce="Image · Ctrl+Maj+K"><i class="bi bi-image" aria-hidden="true"></i></button>
							</div>
						</div>
					</section>

					<!-- ============ DROITE : Design (l'element choisi, le visuel, l'export) et Publication -->
					<aside class="st-droite" aria-label="Propriétés">
						<header class="st-droite__tete">
							<button type="button" class="st-zoom" id="stZoom" aria-haspopup="menu" aria-expanded="false" aria-controls="stMenuZoom" title="Zoom (Ctrl+molette)">100 %<i class="bi bi-chevron-down" aria-hidden="true"></i></button>
							<button type="button" class="st-bouton st-bouton--principal st-bouton--compact" id="stAllerExport"><i class="bi bi-download" aria-hidden="true"></i> Exporter</button>
						</header>
						<div class="st-onglets st-onglets--droite" role="tablist" aria-label="Panneau de droite">
							<button type="button" class="st-onglet" role="tab" id="stOngletDesign" aria-controls="stPanneauDesign" aria-selected="true">Design</button>
							<button type="button" class="st-onglet" role="tab" id="stOngletPublication" aria-controls="stPanneauPublication" aria-selected="false" tabindex="-1">Publication<span class="st-point" id="stPointPublication" hidden></span></button>
						</div>
						<div class="st-droite__corps" id="stPanneauDesign" role="tabpanel" aria-labelledby="stOngletDesign">
							<!-- l'element choisi sur le visuel : position, texte, couleurs, son champ (src/js/studio.js) -->
							<div class="st-proprietes" id="stFlottant" role="group" aria-label="Élément sélectionné" hidden></div>
							<!-- rien de choisi : le visuel lui-meme -->
							<section class="st-section" id="stDocument" aria-labelledby="stDocumentTitre">
								<h2 class="st-section__titre" id="stDocumentTitre">Visuel</h2>
								<p class="st-dimensions" id="stDimensions"></p>
								<label class="st-case st-case--zones" id="stMargesBascule">
									<input type="checkbox" id="stMarges"> Marges de 40 px
								</label>
								<label class="st-case st-case--zones" id="stZonesBascule" hidden>
									<input type="checkbox" id="stZones" checked> Zones d'Instagram
								</label>
								<button type="button" class="st-lien" id="stMepReset" hidden><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Remettre la mise en page d'origine</button>
								<p class="st-aide">Un clic choisit un élément, un double-clic écrit le texte sur le visuel. Ctrl+molette : zoom ; Espace : déplacer le plan.</p>
							</section>
							<section class="st-section" aria-labelledby="stVerifTitre">
								<h2 class="st-section__titre" id="stVerifTitre">Vérifications</h2>
								<div class="st-alertes" id="stAlertes" aria-live="polite"></div>
							</section>
							<section class="st-section" id="stExport" aria-labelledby="stExporterTitre">
								<h2 class="st-section__titre" id="stExporterTitre">Exporter</h2>
								<!-- les sorties du format choisi : PNG, JPG, PDF ; en A4, le PDF de l'imprimeur -->
								<div class="st-sorties" id="stSorties"></div>
								<div class="st-lot">
									<button type="button" class="st-bouton" id="stToutExporter" data-sortie="zip" data-echelle="1"><i class="bi bi-file-earmark-zip" aria-hidden="true"></i> Tout exporter (.zip)</button>
									<p class="st-aide">Les quatre formats d'un coup : chacun en PNG et en PDF, et l'A4 aussi pour l'imprimeur.</p>
								</div>
								<button type="button" class="st-lien" id="stAllerValidation" hidden><i class="bi bi-arrow-right" aria-hidden="true"></i> Aller à la validation</button>
								<p class="st-service" id="stService" aria-live="polite">Service de rendu : vérification…</p>
							</section>
						</div>
						<div class="st-droite__corps" id="stPanneauPublication" role="tabpanel" aria-labelledby="stOngletPublication" hidden>
							<!-- VALIDATION : facultative, obligatoire pour le communique et le recrutement -->
							<section class="st-section" id="stValidation" aria-labelledby="stValidationTitre">
								<h2 class="st-section__titre" id="stValidationTitre">Validation</h2>
								<p class="st-aide" id="stValidationTexte"></p>
								<div class="st-bloc__boutons">
									<button type="button" class="st-bouton st-bouton--principal" id="stSoumettre" hidden>Soumettre pour validation</button>
									<button type="button" class="st-bouton st-bouton--principal" id="stValider" hidden><i class="bi bi-check2-circle" aria-hidden="true"></i> Valider</button>
									<button type="button" class="st-bouton" id="stRefuser" hidden>Refuser…</button>
								</div>
							</section>
							<!-- LEGENDE pour la publication, a partir des champs -->
							<section class="st-section" aria-labelledby="stLegendeTitre">
								<h2 class="st-section__titre" id="stLegendeTitre">Légende pour la publication</h2>
								<textarea class="st-saisie" id="stLegende" rows="8" maxlength="2200" aria-describedby="stLegendeAide"></textarea>
								<p class="st-aide" id="stLegendeAide">Écrite à partir des champs et du texte alternatif de l'image ; modifiable.</p>
								<div class="st-bloc__boutons">
									<button type="button" class="st-bouton" id="stLegendeCopier"><i class="bi bi-clipboard" aria-hidden="true"></i> Copier</button>
									<button type="button" class="st-lien" id="stLegendeRefaire" hidden>Réécrire depuis les champs</button>
								</div>
							</section>
						</div>
					</aside>
				</main>
			</div>
		</div>
	</div>

	<!-- LES MENUS : le menu du Studio (comme celui de Figma), le zoom, le clic droit -->
	<div class="st-menu" id="stMenuFichier" role="menu" aria-label="Menu du Studio" hidden>
		<button type="button" class="st-menu__item" role="menuitem" id="stOuvrir"><i class="bi bi-collection" aria-hidden="true"></i><span>Mes visuels…</span><span class="st-pastille" id="stAValider" hidden></span></button>
		<button type="button" class="st-menu__item" role="menuitem" id="stNouveau"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nouveau visuel</span></button>
		<button type="button" class="st-menu__item" role="menuitem" id="stDupliquer" disabled><i class="bi bi-copy" aria-hidden="true"></i><span>Dupliquer ce visuel</span></button>
		<button type="button" class="st-menu__item" role="menuitem" id="stHistorique" disabled><i class="bi bi-clock-history" aria-hidden="true"></i><span>Historique des versions…</span></button>
		<div class="st-menu__sep" role="separator"></div>
		<button type="button" class="st-menu__item" role="menuitem" id="stJournalOuvrir" hidden><i class="bi bi-journal-text" aria-hidden="true"></i><span>Journal des exports…</span></button>
		<button type="button" class="st-menu__item" role="menuitem" id="stAdmin" hidden><i class="bi bi-gear" aria-hidden="true"></i><span>Administration…</span></button>
		<button type="button" class="st-menu__item" role="menuitem" id="stVider"><i class="bi bi-eraser" aria-hidden="true"></i><span>Vider le visuel…</span></button>
		<div class="st-menu__sep" role="separator"></div>
		<button type="button" class="st-menu__item" role="menuitem" id="stAideRaccourcis"><i class="bi bi-keyboard" aria-hidden="true"></i><span>Raccourcis clavier</span><kbd>Ctrl+Maj+?</kbd></button>
	</div>
	<div class="st-menu" id="stMenuZoom" role="menu" aria-label="Zoom" hidden></div>
	<div class="st-menu" id="stMenuContexte" role="menu" aria-label="Actions" hidden></div>

	<!-- LES VISUELS : liste, historique, journal, refus -->
	<dialog class="st-dialogue" id="stDlgVisuels" aria-labelledby="stDlgVisuelsTitre">
		<header><h2 id="stDlgVisuelsTitre">Visuels</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
		<div class="st-formats" role="tablist" aria-label="Quels visuels" id="stVues"></div>
		<ul class="st-lignes" id="stListe"></ul>
	</dialog>
	<dialog class="st-dialogue" id="stDlgHistorique" aria-labelledby="stDlgHistoriqueTitre">
		<header><h2 id="stDlgHistoriqueTitre">Historique des versions</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
		<p class="st-aide">Restaurer une version ne perd rien : elle devient la version courante, et l'actuelle reste dans la liste.</p>
		<ul class="st-lignes" id="stVersions"></ul>
	</dialog>
	<dialog class="st-dialogue st-dialogue--large" id="stDlgJournal" aria-labelledby="stDlgJournalTitre">
		<header><h2 id="stDlgJournalTitre">Journal des exports</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
		<div class="st-tableau"><table><thead><tr><th>Date</th><th>Qui</th><th>Visuel</th><th>Format</th><th>État du visuel</th></tr></thead><tbody id="stJournal"></tbody></table></div>
	</dialog>
	<dialog class="st-dialogue st-dialogue--large" id="stDlgAdmin" aria-labelledby="stDlgAdminTitre">
		<header><h2 id="stDlgAdminTitre">Administration du Studio</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
		<div class="st-formats" role="tablist" aria-label="Rubriques" id="stAdmOnglets"></div>
		<p class="st-etat" id="stAdmMessage" role="status" aria-live="polite"></p>
		<div class="st-adm" id="stAdmZone"></div>
	</dialog>
	<dialog class="st-dialogue st-dialogue--large" id="stDlgMedia" aria-labelledby="stDlgMediaTitre">
		<header><h2 id="stDlgMediaTitre">Médiathèque</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
		<div id="stMediaZone"></div>
	</dialog>
	<dialog class="st-dialogue" id="stDlgRefus" aria-labelledby="stDlgRefusTitre">
		<form method="dialog" id="stFormRefus">
			<header><h2 id="stDlgRefusTitre">Refuser ce visuel</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
			<label class="st-libelle" for="stMotifRefus">Ce qu'il faut corriger</label>
			<textarea class="st-saisie" id="stMotifRefus" rows="4" maxlength="300" required></textarea>
			<div class="st-bloc__boutons"><button type="submit" class="st-bouton st-bouton--principal">Refuser</button><button type="button" class="st-bouton" data-fermer>Annuler</button></div>
		</form>
	</dialog>
	<dialog class="st-dialogue st-dialogue--large" id="stDlgRaccourcis" aria-labelledby="stDlgRaccourcisTitre">
		<header><h2 id="stDlgRaccourcisTitre">Raccourcis clavier</h2><button type="button" class="st-icone" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
		<p class="st-aide">Les mêmes que dans Figma. Ils ne jouent pas pendant qu'on écrit dans une case.</p>
		<div class="st-raccourcis">
			<section>
				<h3>Outils</h3>
				<dl>
					<dt>Déplacer</dt><dd><kbd>V</kbd></dd>
					<dt>Main</dt><dd><kbd>H</kbd> ou <kbd>Espace</kbd> maintenu</dd>
					<dt>Rectangle</dt><dd><kbd>R</kbd></dd>
					<dt>Ellipse</dt><dd><kbd>O</kbd></dd>
					<dt>Ligne</dt><dd><kbd>L</kbd></dd>
					<dt>Texte</dt><dd><kbd>T</kbd></dd>
					<dt>Image</dt><dd><kbd>Ctrl</kbd> <kbd>Maj</kbd> <kbd>K</kbd></dd>
				</dl>
			</section>
			<section>
				<h3>Sélection</h3>
				<dl>
					<dt>Écrire le texte choisi</dt><dd><kbd>Entrée</kbd> ou double-clic</dd>
					<dt>Déplacer de 1 px (10 px)</dt><dd>flèches (<kbd>Maj</kbd>)</dd>
					<dt>Sans aimant</dt><dd><kbd>Alt</kbd> en glissant</dd>
					<dt>Dupliquer un bloc</dt><dd><kbd>Ctrl</kbd> <kbd>D</kbd></dd>
					<dt>Avancer / reculer</dt><dd><kbd>Ctrl</kbd> <kbd>]</kbd> / <kbd>Ctrl</kbd> <kbd>[</kbd></dd>
					<dt>Supprimer un bloc</dt><dd><kbd>Suppr</kbd></dd>
					<dt>Tout désélectionner</dt><dd><kbd>Échap</kbd></dd>
				</dl>
			</section>
			<section>
				<h3>Vue</h3>
				<dl>
					<dt>Zoom</dt><dd><kbd>Ctrl</kbd> + molette, <kbd>Ctrl</kbd> <kbd>+</kbd> / <kbd>Ctrl</kbd> <kbd>-</kbd></dd>
					<dt>Tout voir</dt><dd><kbd>Maj</kbd> <kbd>1</kbd></dd>
					<dt>Zoom sur la sélection</dt><dd><kbd>Maj</kbd> <kbd>2</kbd></dd>
					<dt>Taille réelle (100 %)</dt><dd><kbd>Maj</kbd> <kbd>0</kbd></dd>
					<dt>Déplacer le plan</dt><dd>molette, <kbd>Espace</kbd> + glisser</dd>
					<dt>Masquer les panneaux</dt><dd><kbd>Ctrl</kbd> <kbd>\</kbd></dd>
					<dt>Annuler / rétablir</dt><dd><kbd>Ctrl</kbd> <kbd>Z</kbd> / <kbd>Ctrl</kbd> <kbd>Maj</kbd> <kbd>Z</kbd></dd>
				</dl>
			</section>
		</div>
	</dialog>

	<script src="<?=$app_base?>/src/studio/vendor/qrcode-generator.js?v=<?=@filemtime(__DIR__ . '/studio/vendor/qrcode-generator.js')?>"></script>
	<script src="<?=$app_base?>/src/studio/studio-schema.js?v=<?=@filemtime(__DIR__ . '/studio/studio-schema.js')?>"></script>
	<script src="<?=$app_base?>/src/studio/studio-rendu.js?v=<?=@filemtime(__DIR__ . '/studio/studio-rendu.js')?>"></script>
	<script src="<?=$app_base?>/src/js/studio.js?v=<?=@filemtime(__DIR__ . '/js/studio.js')?>"></script>
</body>
</html>
