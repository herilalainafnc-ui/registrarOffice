<head>
	<script>
		// Apply saved theme immediately to prevent flash
		(function() {
			const savedTheme = localStorage.getItem('theme') || 'dark';
			document.documentElement.setAttribute('data-theme', savedTheme);
		})();
		/* Barre laterale reduite, posee AVANT l affichage pour que la page ne
		   saute pas : automatique de 1024 a 1439 px, sinon le choix memorise
		   (voir init/menubar.php). */
		(function () {
			try {
				var l = window.innerWidth, p = localStorage.getItem('registrar.menu.rail');
				if (l >= 1024 && (p ? p === 'rail' : l < 1440)) {
					document.documentElement.classList.add('side-rail');
				}
			} catch (e) {}
		})();
	</script>
	<?php 
	// Charger le middleware de sécurité (gère automatiquement la session)
	// Utiliser des chemins basés sur le dossier parent pour rendre les requires robustes
	require_once(dirname(__DIR__) . '/data/backdb.php');
	require_once(dirname(__DIR__) . '/data/middleware.php');
	
	// Initialiser le middleware avec la connexion DB
	initMiddleware($dtb);

	// Calculer le chemin racine de l'application (fonctionne en local et en production)
	// Local: /a.registrar  |  Production: (vide = racine)
	// Méthode fiable: comparer DOCUMENT_ROOT et le dossier réel de l'app
	$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
	$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
	$app_base = substr($_app_root, strlen($_doc_root));
	if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';

	// Définir APP_BASE si pas déjà fait (mode sans front controller)
	if (!defined('APP_BASE')) define('APP_BASE', $app_base);
	if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));

	// Charger les helpers MVC si pas déjà chargés
	if (!function_exists('url')) {
		require_once(dirname(__DIR__) . '/core/helpers.php');
	}

	// Encoder un chemin de fichier en préservant les / (encode chaque segment)
	if (!function_exists('encodeFilePath')) {
		function encodeFilePath($path) {
			if (empty($path)) return '';
			return implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
		}
	}
	
	// Vérifier l'authentification (redirige vers login si non connecté),
	// sauf pour les pages explicitement marquées comme publiques.
	if (empty($allow_public_page)) {
		requireAuth($app_base . '/login');
	}

	/* Le responsable du Work Education n'a que sa page, son compte, la
	   messagerie, le didacticiel et le bulletin academique : toute autre page
	   du personnel (meme ouverte par son adresse) le ramene a la sienne. */
	if (function_exists('estWorkEducation') && estWorkEducation()) {
		$_we_chemin = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
		if ($app_base !== '' && strpos($_we_chemin, $app_base) === 0) { $_we_chemin = substr($_we_chemin, strlen($app_base)); }
		$_we_chemin = rtrim($_we_chemin, '/');
		if (!in_array($_we_chemin, ['/work-education', '/my-account', '/messagerie', '/didacticiel', '/reglement'], true)) {
			header('Location: ' . $app_base . '/work-education', true, 302);
			exit;
		}
		unset($_we_chemin);
	}

	/*:::::::::::::::::::::::: COULEURS DE SESSION ::::::::::::::::::::::::::::::*/

	/* Ces noms de classes restent ceux que les pages attendent, mais pointent
	   sur la palette neutre de Tailwind, redefinie plus bas sur les jetons de
	   src/css/theme.css : ils suivent le theme clair ou sombre sans que les
	   pages aient a changer. */

	$_SESSION['bg_one_color'] = 'bg-slate-800';     /* carte, panneau de contenu */
	$_SESSION['bg_two_color'] = 'bg-slate-900';     /* champ, zone en creux */
	$_SESSION['bg_three_color'] = 'bg-slate-900';   /* fond de page */
	$_SESSION['bg_four_color'] = 'bg-slate-700';    /* entete de tableau */
	$_SESSION['bg_five_color'] = 'bg-slate-700';    /* survol */
	$_SESSION['bg_six_color'] = 'bg-slate-600';     /* survol appuye, bouton */
	$_SESSION['bg_seven_color'] = 'bg-slate-500';   /* accent neutre */
	$_SESSION['bg_eight_color'] = 'bg-slate-300';   /* pastille claire */


	$_SESSION['br_two_color'] = 'border-slate-700';
	$_SESSION['br_three_color'] = 'border-slate-600';

	$_SESSION['simpleTbl'] = 'simpleTbl';
	
	$_SESSION['txt_one_color'] = 'text-slate-100';   /* texte courant */
	$_SESSION['txt_two_color'] = 'text-slate-400';   /* texte secondaire */
	$_SESSION['txt_three_color'] = 'text-slate-950'; /* texte sur fond clair */

	/*$_SESSION['bg_one_color'] = 'bg-slate-100';
	$_SESSION['bg_two_color'] = 'bg-slate-200';
	$_SESSION['bg_three_color'] = 'bg-slate-300';
	$_SESSION['bg_four_color'] = 'bg-slate-400';
	$_SESSION['bg_five_color'] = 'bg-slate-500';
	$_SESSION['bg_six_color'] = 'bg-slate-600';
	$_SESSION['bg_seven_color'] = 'bg-slate-700';
	$_SESSION['bg_eight_color'] = 'bg-slate-800';

	$_SESSION['br_two_color'] = 'border-slate-200';
	$_SESSION['br_three_color'] = 'border-slate-700';

	$_SESSION['simpleTbl'] = 'simpleTblLight';

	$_SESSION['txt_one_color'] = 'text-slate-800';
	$_SESSION['txt_two_color'] = 'text-slate-400';
	$_SESSION['txt_three_color'] = 'text-slate-100';*/

	$bg_one_color = $_SESSION['bg_one_color'];
	$bg_two_color = $_SESSION['bg_two_color'];
	$bg_three_color = $_SESSION['bg_three_color'];
	$bg_four_color = $_SESSION['bg_four_color'];
	$bg_five_color = $_SESSION['bg_five_color'];
	$bg_six_color = $_SESSION['bg_six_color'];
	$bg_seven_color = $_SESSION['bg_seven_color'];
	$bg_eight_color = $_SESSION['bg_eight_color'];
	$br_two_color = $_SESSION['br_two_color'];
	$br_three_color = $_SESSION['br_three_color'];
	$txt_one_color = $_SESSION['txt_one_color'];
	$txt_two_color = $_SESSION['txt_two_color'];
	$txt_three_color = $_SESSION['txt_three_color'];
	
	/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
	 ?>

	<!-- backdb.php déjà inclus via middleware -->

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&family=JetBrains+Mono:wght@500..600&display=swap">

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
	<?php include __DIR__ . '/pwa.php'; /* application installable */ ?>

	<!-- Variables JS globales : chemin de base et jeton CSRF -->
	<script>
		var APP_BASE = '<?=$app_base?>';
		// Jeton CSRF a joindre a toute requete AJAX qui modifie des donnees.
		var CSRF_TOKEN = '<?= function_exists('csrf_token') ? csrf_token() : '' ?>';
	</script>

	<!-- Transitions de page (stinger). Le script correspondant est charge en
	     debut de <body> par init/topbar.php : il lui faut document.body. -->
	<link rel="stylesheet" href="<?=$app_base?>/src/css/transitions.css?v=<?=@filemtime(__DIR__ . '/../src/css/transitions.css')?>">


<?php /* TAILWIND — feuille precompilee (npm run build:css, voir tailwind.config.js).
         Elle remplace le CDN, qui chargeait 511 Ko de JavaScript et generait
         les styles dans le navigateur a chaque page. Elle est liee a la FIN
         de ce <head>, plus bas : c est la place ou le CDN inserait ses styles,
         apres les autres feuilles, et donc l ordre dans lequel les regles de
         meme poids se departagent. */ ?>


	<?php /* Numéro de version à changer à chaque modification de ces feuilles :
	         sans lui, le serveur ne dit pas combien de temps les garder et les
	         navigateurs réutilisent l'ancienne pendant des jours. C'est ce qui
	         gardait une ancienne règle de modal.css sur la modale des cours. */ ?>
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/style.css?v=20260915-1">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/button.css?v=20260915-1">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/responsive.css?v=20261010-1">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/modal.css?v=20260915-1">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/student.css?v=20260915-1">
	<?php /* Le theme passe en dernier : c est lui qui fixe les couleurs. Sa
	         version suit la date du fichier. */ ?>
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/tokens.css?v=<?=@filemtime(dirname(__DIR__) . '/src/css/tokens.css')?>">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/theme.css?v=<?=@filemtime(dirname(__DIR__) . '/src/css/theme.css')?>">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/recherche.css?v=<?=@filemtime(dirname(__DIR__) . '/src/css/recherche.css')?>">
	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/didacticiel.css?v=<?=@filemtime(dirname(__DIR__) . '/src/css/didacticiel.css')?>">

	<!-- ===== GLOBAL UTILITY STYLES ===== -->
	<style>
		/* Spin animation for loading indicators */
		.animate-spin {
			animation: spin 1s linear infinite;
		}
		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		
		/* Pulse animation */
		.animate-pulse {
			animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
		}
		@keyframes pulse {
			0%, 100% { opacity: 1; }
			50% { opacity: .5; }
		}
		
		/* Fade in animation */
		.animate-fadeIn {
			animation: fadeIn 0.3s ease-out;
		}
		@keyframes fadeIn {
			from { opacity: 0; transform: translateY(-10px); }
			to { opacity: 1; transform: translateY(0); }
		}
	</style>

	<!-- ===== GLOBAL THEME STYLES ===== -->
	<style>
		/* Prevent body scroll on desktop - app should be contained */
		html, body {
			margin: 0;
			padding: 0;
			height: 100vh;
			max-height: 100vh;
		}
		
		/* Allow scroll on mobile */
		@media (min-width: 1024px) {
			html, body {
				overflow: hidden;
			}
		}
		
		@media (max-width: 1023px) {
			html, body {
				height: auto;
				max-height: none;
			}
			/* L ecran defile, pas le body : sinon la barre du haut (sticky)
			   part avec la page (voir src/css/responsive.css). */
			html {
				overflow-x: hidden;
				overflow-y: auto;
			}

			/* iOS safe-area support for fixed toolbar */
			.toolbar-container {
				padding-bottom: calc(6px + env(safe-area-inset-bottom, 0px)) !important;
			}

			/* Ensure content isn't hidden behind fixed toolbar */
			body {
				padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
			}
		}
		
		/* Transition for smooth theme change - only main containers */
		body,
		.h-screen,
		.topbar-bleu-nuit,
		.menubar-bleu-nuit,
		.toolbar-container,
		.footer-bleu-nuit,
		.info-card,
		table,
		thead,
		.dropdown-menu {
			transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
		}
		
		/* Le theme clair ne remappe plus les classes une a une : les jetons de
		   src/css/theme.css s inversent, et tout le balisage suit. */
	</style>

	<link rel="stylesheet" type="text/css" href="<?=$app_base?>/src/css/tailwind.build.css?v=<?=@filemtime(dirname(__DIR__) . '/src/css/tailwind.build.css')?>">
</head>
<script>
        $(document).ready(function() {
            // Affichage de la date et heure en temps réel
            function updateDateTime() {
                const now = new Date();
                const options = {
                    // Heure de Madagascar, quel que soit le reglage de l'appareil.
                    timeZone: 'Indian/Antananarivo',
                    weekday: 'long',
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                };
                const dateTimeStr = now.toLocaleDateString('fr-FR', options);
                $('#content').text(dateTimeStr);
            }

            // Appel initial
            updateDateTime();

            // Mettre à jour chaque seconde
            setInterval(updateDateTime, 1000);
        });
    </script>

    <script type="text/javascript">
        // Vue "cartes" mobile : renseigne automatiquement data-label sur chaque
        // cellule à partir de l'en-tête du tableau, pour les .simpleTbl.stack-cards,
        // y compris les tableaux insérés en AJAX (recherche / tri).
        (function () {
            function fillLabels(table) {
                var headRow = table.querySelector('thead tr');
                var headers = [];
                if (headRow) {
                    headRow.querySelectorAll('th, td').forEach(function (c) {
                        headers.push(c.textContent.trim());
                    });
                }
                var body = table.querySelector('tbody');
                if (!body) return;
                // Libellés des cartes (mobile)
                if (headers.length) {
                    body.querySelectorAll('tr').forEach(function (tr) {
                        var tds = tr.children, i;
                        for (i = 0; i < tds.length; i++) {
                            if (tds[i].tagName === 'TD' && headers[i] && !tds[i].hasAttribute('data-label')) {
                                tds[i].setAttribute('data-label', headers[i]);
                            }
                        }
                    });
                }
                // État vide : aucun résultat
                if (body.children.length === 0) {
                    var cols = headers.length || 1;
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td colspan="' + cols + '" style="text-align:center;padding:28px 12px;color:rgb(var(--c-text-3));">' +
                        '<i class="bi bi-inbox" style="font-size:26px;display:block;margin-bottom:8px;opacity:.55;"></i>' +
                        'Aucun résultat</td>';
                    body.appendChild(tr);
                }
                if (window.__updateListCount) { window.__updateListCount(); }
            }
            // Compteur "en direct" : nombre de lignes de la liste visible -> footer
            window.__updateListCount = function () {
                if (window.listePilotee) { return; } // src/js/listes.js tient le compte
                var fi = document.getElementById('footerInfo');
                if (!fi || !window.__listNoun) { return; }
                var visible = null;
                document.querySelectorAll('table.simpleTbl.stack-cards').forEach(function (t) {
                    if (t.offsetParent !== null) { visible = t; }
                });
                if (!visible) { return; }
                var body = visible.querySelector('tbody');
                if (!body) { return; }
                var rows = body.querySelectorAll('tr');
                var count = rows.length;
                if (count === 1 && rows[0].querySelector('td[colspan]')) { count = 0; }
                fi.textContent = count + ' ' + window.__listNoun + (count === 0 ? ' — aucun résultat' : '');
            };
            function process(root) {
                (root || document).querySelectorAll('table.simpleTbl.stack-cards').forEach(fillLabels);
            }
            document.addEventListener('DOMContentLoaded', function () {
                process(document);
                if (!document.body) return;
                new MutationObserver(function (muts) {
                    muts.forEach(function (m) {
                        m.addedNodes.forEach(function (n) {
                            if (n.nodeType !== 1) return;
                            if (n.matches && n.matches('table.simpleTbl.stack-cards')) fillLabels(n);
                            else if (n.querySelectorAll) process(n);
                        });
                    });
                }).observe(document.body, { childList: true, subtree: true });

                // Recompter quand une liste est affichée/masquée (recherche vidée, tri, filtre)
                document.querySelectorAll('#all-std,#stdSearch-result,#stdTriage-result,#all-cours,#coursSearch-result,#coursTriage-result,#all-prof,#profSearch-result,#profTriage-result').forEach(function (c) {
                    new MutationObserver(function () {
                        if (window.__updateListCount) { window.__updateListCount(); }
                    }).observe(c, { attributes: true, attributeFilter: ['style', 'class'] });
                });
            });
        })();
    </script>