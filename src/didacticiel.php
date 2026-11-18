<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Didacticiel</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/didacticiel.page.css?v=<?=@filemtime(__DIR__ . '/css/didacticiel.page.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Didacticiel : les visites guidees du compte, par theme.

   Une visite se lance sur sa page (lien « …#didacticiel=id », repris par
   src/js/didacticiel.js). Les visites de detail (fiche, feuille, dossier)
   n'ont pas de carte : elles continuent celle de leur liste, et se relancent
   depuis le menu « Didacticiel » de leur page.
   Definitions : data/didacticiel.php. Acces : DashboardController::didacticiel.
   ========================================================================== */
require_once(__DIR__ . '/../data/didacticiel.php');

$aiVisites = didacticielVisites(didacticielProfil());
$aiParId = array_column($aiVisites, null, 'id');
$aiCartes = array_values(array_filter($aiVisites, fn ($v) => !empty($v['hub'])));
$aiPages = [
	'/tableau-de-bord'         => 'Tableau de bord',
	'/etudiants'               => 'Liste des étudiants',
	'/inscription/inscription' => 'Inscription',
	'/remise-notes'            => 'Remises de notes',
	'/teacher/dashboard'       => 'Mes cours',
	'/teacher/remise-notes'    => 'Remise des notes',
];
/* Les cartes par theme, dans l'ordre du menu ; un theme inconnu va a la fin. */
$aiOrdre = ['Prise en main', 'Étudiants', 'Admissions', 'Notes', 'Pédagogie', 'Distinctions', 'Communication',
	'Outils', 'Finance', 'Paramètres', 'Mon compte', 'Mon enseignement'];
$aiGroupes = [];
foreach ($aiCartes as $v) { $aiGroupes[$v['groupe']][] = $v; }
$aiRang = static function ($g) use ($aiOrdre) { $r = array_search($g, $aiOrdre, true); return $r === false ? 99 : $r; };
uksort($aiGroupes, static fn ($a, $b) => $aiRang($a) <=> $aiRang($b));
$aiAncre = static fn ($g) => 'theme-' . trim(preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $g) ?: 'x')), '-');

/** La visite qui prend le relais : suite declaree, ou action d'une etape. */
$aiSuite = function (array $v) use ($aiParId): ?array {
	$id = $v['suite'] ?? null;
	foreach ($v['etapes'] as $e) { if (!empty($e['action']['suite'])) { $id = $e['action']['suite']; } }
	return $id !== null && isset($aiParId[$id]) ? $aiParId[$id] : null;
};
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<div class="ai-page">

						<header class="ai-entete">
							<p class="ai-surtitre">Aide</p>
							<h1 class="ai-titre">Didacticiel</h1>
							<p class="ai-chapeau">Des visites guidées, sur les vraies pages de l'application&nbsp;: chaque étape montre un élément et dit à quoi il sert. Une à deux minutes par visite&nbsp;; <kbd>Échap</kbd> l'arrête à tout moment.</p>
						</header>

						<div class="ai-corps">
							<section class="ai-visites" aria-label="Visites guidées">
<?php if (!$aiCartes): ?>
								<p class="ai-vide">Aucune visite n'est prévue pour votre compte pour l'instant.</p>
<?php endif; ?>
<?php if ($aiCartes): ?>
								<?php /* Aller a un theme, ou chercher une visite par son nom (filtre sur cette page). */ ?>
								<div class="ai-outils">
									<label class="ai-recherche">
										<i class="bi bi-search" aria-hidden="true"></i>
										<span class="sr-only">Rechercher une visite</span>
										<input type="search" id="aiRecherche" placeholder="Rechercher une visite…" autocomplete="off">
									</label>
									<nav class="ai-themes" aria-label="Thèmes">
<?php foreach ($aiGroupes as $g => $liste): ?>
										<a class="ai-theme" href="#<?=$aiAncre($g)?>"><?=htmlspecialchars($g)?><b><?=count($liste)?></b></a>
<?php endforeach; ?>
									</nav>
								</div>
								<p class="ai-vide" id="aiAucune" hidden>Aucune visite ne correspond à cette recherche.</p>
<?php endif; ?>
<?php foreach ($aiGroupes as $g => $liste): ?>
								<section class="ai-groupe" id="<?=$aiAncre($g)?>" aria-labelledby="<?=$aiAncre($g)?>-titre">
									<h2 class="ai-groupe__titre" id="<?=$aiAncre($g)?>-titre"><?=htmlspecialchars($g)?> <span><?=count($liste)?> visite<?= count($liste) > 1 ? 's' : '' ?></span></h2>
								<div class="ai-cartes">
<?php foreach ($liste as $v):
	$depart = $v['depart'] ?? $v['pages'][0];
	$suite = $aiSuite($v);
	$nbEtapes = count($v['etapes']);
?>
									<article class="ai-carte" data-visite="<?=htmlspecialchars($v['id'])?>" data-texte="<?=htmlspecialchars(mb_strtolower($v['titre'] . ' ' . $v['resume'] . ' ' . $g . ' ' . ($v['lieu'] ?? '')))?>">
										<div class="ai-carte__tete">
											<span class="ai-carte__icone" aria-hidden="true"><i class="bi <?=htmlspecialchars($v['icone'])?>"></i></span>
											<span class="ai-carte__groupe"><?=$nbEtapes?> étape<?= $nbEtapes > 1 ? 's' : '' ?></span>
											<span class="ai-faite" hidden><i class="bi bi-check2" aria-hidden="true"></i>Faite</span>
										</div>
										<h2 class="ai-carte__titre"><?=htmlspecialchars($v['titre'])?></h2>
										<p class="ai-carte__resume"><?=htmlspecialchars($v['resume'])?></p>
										<p class="ai-carte__trajet">
											<i class="bi bi-geo-alt" aria-hidden="true"></i><?=htmlspecialchars($v['lieu'] ?? $aiPages[$depart] ?? $depart)?>
<?php if ($suite): ?>
											<i class="bi bi-arrow-right ai-carte__puis" aria-hidden="true"></i><span class="sr-only">puis</span><?=htmlspecialchars($suite['titre'])?>
<?php endif; ?>
										</p>
										<a class="ai-lancer" href="<?=$app_base . htmlspecialchars($depart)?>#didacticiel=<?=htmlspecialchars($v['id'])?>">
											<span>Lancer la visite</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
										</a>
									</article>
<?php endforeach; ?>
								</div>
								</section>
<?php endforeach; ?>
							</section>

							<aside class="ai-cote" aria-labelledby="aiRaccourcis">
								<section class="ai-encart">
									<h2 class="ai-encart__titre" id="aiRaccourcis">À retenir</h2>
									<dl class="ai-touches">
										<div><dt><kbd>Ctrl</kbd><kbd>K</kbd> ou <kbd>/</kbd></dt><dd>Rechercher, depuis n'importe quelle page</dd></div>
										<div><dt><kbd>Échap</kbd></dt><dd>Fermer une fenêtre, arrêter une visite</dd></div>
										<div><dt><kbd>?</kbd></dt><dd>Les raccourcis clavier</dd></div>
										<div><dt><kbd>Alt</kbd><kbd>H</kbd></dt><dd>Revenir à l'accueil</dd></div>
									</dl>
									<button type="button" class="ai-second" onclick="if (typeof toggleShortcutsModal === 'function') { toggleShortcutsModal(); }">
										<i class="bi bi-keyboard" aria-hidden="true"></i>Tous les raccourcis
									</button>
								</section>
								<section class="ai-encart">
									<h2 class="ai-encart__titre">Sur chaque page</h2>
									<p class="ai-encart__texte">Le bouton <b>Didacticiel</b>, en bas du menu, propose les visites de la page où vous êtes&nbsp;: une fiche d'étudiant, une feuille de notes, un dossier d'inscription…</p>
								</section>
							</aside>
						</div>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>
	<script>
	/* Les visites deja faites, retenues par ce navigateur (src/js/didacticiel.js) */
	document.addEventListener('DOMContentLoaded', function () {
		/* Chercher une visite : titre, résumé, thème ou page ; accents facultatifs. */
		var champ = document.getElementById('aiRecherche');
		var sans = function (s) { return String(s).toLowerCase().normalize('NFD').replace(/\p{M}/gu, ''); };
		if (champ) {
			champ.addEventListener('input', function () {
				var q = sans(champ.value.trim()), visibles = 0;
				document.querySelectorAll('.ai-carte[data-texte]').forEach(function (c) {
					var ok = !q || sans(c.getAttribute('data-texte')).indexOf(q) >= 0;
					c.hidden = !ok;
					if (ok) { visibles++; }
				});
				document.querySelectorAll('.ai-groupe').forEach(function (g) {
						var n = g.querySelectorAll('.ai-carte:not([hidden])').length;
						g.hidden = !n;
						g.querySelector('.ai-groupe__titre span').textContent = n + ' visite' + (n > 1 ? 's' : '');
					});
				document.getElementById('aiAucune').hidden = visibles > 0;
			});
		}
		if (!window.Didacticiel) { return; }
		document.querySelectorAll('.ai-carte[data-visite]').forEach(function (c) {
			if (window.Didacticiel.etat(c.getAttribute('data-visite')) !== 'faite') { return; }
			c.classList.add('est-faite');
			c.querySelector('.ai-faite').hidden = false;
			c.querySelector('.ai-lancer span').textContent = 'Revoir la visite';
		});
	});
	</script>
</body>
</html>
