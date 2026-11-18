<?php
/**
 * Registre de presence — espace enseignant.
 *
 * La page ne se recharge jamais : src/js/teacher-presence.js demande le
 * contenu seul (?fragment=1) a chaque action et ne remplace que ce qui
 * change. Le contenu est dans src/teacher.presence.contenu.php, les regles
 * dans data/presence.php, les ecritures dans app/.presence/enregistrer.php.
 */

/* Reponse partielle : le contenu seul, pour le script. Le controleur a deja
   verifie la connexion (TeacherController). */
if (isset($_GET['fragment'])) {
	require_once(dirname(__DIR__) . '/data/backdb.php');
	require_once(dirname(__DIR__) . '/data/middleware.php');
	initMiddleware($dtb);
	require_once(dirname(__DIR__) . '/data/presence.php');
	header('Content-Type: text/html; charset=UTF-8');
	header('Cache-Control: no-store');
	if (!aEspaceEnseignant() && (int)($_SESSION['user_level'] ?? 99) > 3) {
		http_response_code(403);
		exit;
	}
	require __DIR__ . '/teacher.presence.contenu.php';
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Présences</title>
<?php
require_once('../data/middleware.php');
initMiddleware($dtb);
require_once('../data/presence.php');

/* Comme la remise des notes : les niveaux 1 a 3 entrent pour consulter ou
   corriger un registre, enseignants et chefs de mention pour leurs cours, les
   autres roles n'ont rien a y faire. */
if (!aEspaceEnseignant() && (int)($_SESSION['user_level'] ?? 99) > 3) {
	header('Location: ' . $app_base . '/etudiants');
	exit;
}
?>
	<style>
		.pr-carte { background: rgb(var(--c-surface-2)); border: 1px solid rgb(var(--c-line)); border-radius: 12px; }
		[data-theme="light"] .pr-carte { background: rgb(var(--c-surface)); border-color: rgb(var(--c-line-strong)); }
		.pr-entete {
			display: flex; align-items: center; justify-content: space-between; gap: 10px;
			padding: 10px 16px; border-bottom: 1px solid rgb(var(--c-line));
			font-weight: 600; color: rgb(var(--c-text));
		}
		/* Fait sans reseau, pas encore envoye (src/js/teacher-presence.js, src/js/hors-ligne.js) */
		.pr-appel.est-en-attente { box-shadow: inset 3px 0 0 #fbbf24; }
		a.pr-seance.est-en-attente { box-shadow: inset 3px 0 0 #fbbf24; }
		.pr-titre { font-size: 22px; font-weight: 700; color: rgb(var(--c-text)); letter-spacing: -.01em; }
		.pr-sous { color: rgb(var(--c-text-2)); font-size: 13px; }
		.pr-etiquette {
			display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px;
			font-size: 11px; font-weight: 600; white-space: nowrap; border: 1px solid transparent;
		}
		.pr-bouton {
			display: inline-flex; align-items: center; justify-content: center; gap: 8px;
			min-height: 36px; padding: 0 16px; border-radius: 9px; border: 1px solid transparent;
			font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
			transition: filter .15s ease, background-color .15s ease;
		}
		.pr-bouton:hover:not(:disabled) { filter: brightness(1.1); }
		.pr-bouton:disabled { opacity: .5; cursor: not-allowed; }
		.pr-primaire { background: rgb(var(--c-accent)); color: #fff; }
		.pr-secondaire { background: rgb(var(--c-raised)); color: rgb(var(--c-text)); border-color: rgb(var(--c-line-strong)); }
		.pr-danger { background: transparent; color: #f87171; border-color: rgba(248,113,113,.4); }
		.pr-champ {
			min-height: 36px; padding: 0 10px; border-radius: 8px;
			background: rgb(var(--c-sunken)); border: 1px solid rgb(var(--c-line-strong));
			color: rgb(var(--c-text)); font-size: 13px; font-variant-numeric: tabular-nums;
		}
		.pr-champ:focus-visible, .pr-bouton:focus-visible, .pr-choix button:focus-visible {
			outline: 2px solid rgb(var(--c-accent)); outline-offset: 2px;
		}
		.pr-label { display: block; font-size: 11px; font-weight: 600; letter-spacing: .04em; color: rgb(var(--c-text-3)); margin-bottom: 4px; text-transform: uppercase; }
		.pr-bandeau { display: flex; gap: 10px; padding: 10px 14px; border-radius: 10px; font-size: 13px; line-height: 1.55; }
		.pr-bandeau-info { background: rgb(var(--c-accent) / .1); border: 1px solid rgb(var(--c-accent) / .35); color: rgb(var(--c-text-2)); }
		.pr-bandeau-alerte { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.4); color: rgb(var(--c-text)); }
		.pr-bandeau-erreur { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.4); color: rgb(var(--c-text)); }
		.pr-bandeau a { color: rgb(var(--c-accent-text)); text-decoration: underline; }

		/* Tableaux */
		.pr-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
		.pr-table th {
			position: sticky; top: 0; z-index: 2; background: rgb(var(--c-surface-2));
			padding: 8px 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
			color: rgb(var(--c-text-3)); border-bottom: 1px solid rgb(var(--c-line)); text-align: left;
		}
		.pr-table td { padding: 8px 10px; border-bottom: 1px solid rgb(var(--c-line) / .6); color: rgb(var(--c-text)); vertical-align: middle; }
		.pr-table tr:hover td { background: rgb(var(--c-accent) / .06); }
		.pr-num { text-align: center; font-variant-numeric: tabular-nums; }

		/* Seances : liste a gauche */
		.pr-seance {
			display: block; padding: 10px 14px; border-bottom: 1px solid rgb(var(--c-line) / .6);
			color: rgb(var(--c-text)); text-decoration: none; position: relative;
		}
		.pr-seance:hover { background: rgb(var(--c-accent) / .06); }
		.pr-seance[aria-current="true"] { background: rgb(var(--c-accent) / .14); }
		.pr-seance[aria-current="true"]::before {
			content: ""; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; border-radius: 3px; background: rgb(var(--c-accent));
		}
		.pr-seance__date { font-weight: 600; font-size: 13px; }
		.pr-seance__detail { font-size: 12px; color: rgb(var(--c-text-3)); margin-top: 2px; font-variant-numeric: tabular-nums; }

		/* Appel : une rangee par etudiant */
		.pr-appel { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto auto auto; align-items: center; gap: 10px 14px;
			padding: 8px 14px; border-bottom: 1px solid rgb(var(--c-line) / .6); }
		.pr-appel.est-pointe-absence { background: rgba(239,68,68,.06); }
		.pr-appel.est-pointe-retard { background: rgba(245,158,11,.06); }
		.pr-appel__nom { font-weight: 500; }
		.pr-appel__mat { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: rgb(var(--c-text-3)); }
		.pr-choix { display: inline-flex; border: 1px solid rgb(var(--c-line-strong)); border-radius: 9px; overflow: hidden; }
		.pr-choix button {
			min-width: 76px; min-height: 34px; padding: 0 10px; border: 0; background: transparent;
			color: rgb(var(--c-text-2)); font-size: 12px; font-weight: 600; cursor: pointer;
			transition: background-color .15s ease, color .15s ease;
		}
		.pr-choix button + button { border-left: 1px solid rgb(var(--c-line-strong)); }
		.pr-choix button:hover { background: rgb(var(--c-raised)); color: rgb(var(--c-text)); }
		.pr-choix button[aria-pressed="true"][data-statut="present"] { background: rgba(34,197,94,.2); color: #4ade80; }
		.pr-choix button[aria-pressed="true"][data-statut="retard"]  { background: rgba(245,158,11,.2); color: #fbbf24; }
		.pr-choix button[aria-pressed="true"][data-statut="absent"]  { background: rgba(239,68,68,.2); color: #f87171; }
		[data-theme="light"] .pr-choix button[aria-pressed="true"][data-statut="present"] { color: #15803d; }
		[data-theme="light"] .pr-choix button[aria-pressed="true"][data-statut="retard"]  { color: #b45309; }
		[data-theme="light"] .pr-choix button[aria-pressed="true"][data-statut="absent"]  { color: #dc2626; }
		.pr-minutes { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: rgb(var(--c-text-3)); }
		.pr-minutes input { width: 56px; text-align: center; }
		.pr-minutes[hidden] { display: none; }
		.pr-bilan-mini { font-size: 12px; font-variant-numeric: tabular-nums; white-space: nowrap; }
		.pr-sauve { animation: prSauve 1s ease-out; }
		@keyframes prSauve { from { box-shadow: 0 0 0 2px rgba(34,197,94,.6); } to { box-shadow: 0 0 0 2px transparent; } }

		/* Seances a gauche, appel a droite ; l'un sous l'autre sur mobile. */
		.pr-deux { display: grid; gap: 16px; align-items: start; grid-template-columns: minmax(0, 280px) minmax(0, 1fr); }

		.pr-toast {
			position: fixed; right: 20px; bottom: 20px; z-index: 50; max-width: 380px;
			padding: 12px 16px; border-radius: 10px; font-size: 13px; line-height: 1.5;
			background: rgb(var(--c-raised)); color: rgb(var(--c-text)); border: 1px solid rgb(var(--c-line-strong));
			box-shadow: 0 12px 32px rgb(0 0 0 / .35); transform: translateY(12px); opacity: 0;
			transition: opacity .2s ease, transform .2s ease; pointer-events: none;
		}
		.pr-toast.est-visible { opacity: 1; transform: none; }

		@media (max-width: 900px) {
			.pr-deux { grid-template-columns: minmax(0, 1fr); }
			.pr-appel { grid-template-columns: 24px minmax(0, 1fr); }
			.pr-appel > .pr-choix, .pr-appel > .pr-minutes, .pr-appel > .pr-justif, .pr-appel > .pr-bilan-mini { grid-column: 2; }
			.pr-choix { width: 100%; }
			.pr-choix button { flex: 1; min-height: 44px; min-width: 0; }
			.pr-champ, .pr-bouton { min-height: 44px; font-size: 16px; }
		}
		/* Telephone : la liste des cours en cartes. Le bouton « Registre »
		   etait au bout d'un tableau a sept colonnes, hors de l'ecran. */
		.pr-mobile { display: none; }
		@media (max-width: 640px) {
			.pr-liste-cours thead { display: none; }
			.pr-liste-cours, .pr-liste-cours tbody { display: block; }
			.pr-liste-cours tbody tr {
				display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px;
				padding: 14px 16px; border-bottom: 1px solid rgb(var(--c-line) / .6);
			}
			.pr-liste-cours tbody tr:last-child { border-bottom: 0; }
			.pr-liste-cours tbody td { display: block; padding: 0; border: 0; background: none !important; text-align: left; }
			.pr-liste-cours td:nth-child(1) { order: 1; flex: 1 1 auto; }
			.pr-liste-cours td:nth-child(6) { order: 2; }
			.pr-liste-cours td.pr-vide { display: none; }
			.pr-liste-cours td:nth-child(2) { order: 3; flex-basis: 100%; font-size: 15px; font-weight: 600; line-height: 1.35; }
			.pr-liste-cours td:nth-child(n+3):nth-child(-n+5) { order: 4; font-size: 12.5px; color: rgb(var(--c-text-2)); }
			.pr-liste-cours td:nth-child(n+4):nth-child(-n+5)::before { content: "· "; color: rgb(var(--c-text-3)); }
			.pr-liste-cours td:nth-child(7) { order: 5; flex-basis: 100%; margin-top: 6px; }
			.pr-liste-cours td:nth-child(7) .pr-bouton { display: flex; width: 100%; min-height: 44px !important; font-size: 14px !important; }
			.pr-mobile { display: inline; }
		}
		/* Pendant un chargement AJAX : la vue s'estompe si l'attente dure,
		   un trait accent file en haut ; la page reste utilisable. */
		#presenceApp[aria-busy="true"] #prVue { opacity: .55; transition: opacity .2s ease .15s; }
		#presenceApp { position: relative; }
		#presenceApp[aria-busy="true"]::before {
			content: ""; position: absolute; left: 0; top: -8px; height: 2px; width: 30%;
			background: rgb(var(--c-accent)); animation: prTrait 1s ease-in-out infinite;
		}
		@keyframes prTrait { from { transform: translateX(-100%); } to { transform: translateX(340%); } }
		.pr-zone-maj { animation: prZone .5s ease-out; }
		@keyframes prZone { from { opacity: .4; } }
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="min-h-screen w-full <?=$bg_three_color?>">

	<!-- TOP BAR --><?php require('../init/topbar.php');?>

	<div class="w-full flex flex-col lg:flex-row">

		<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

		<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<div class="w-full px-4 py-4 flex-1 overflow-auto">
				<div id="presenceApp" data-url="<?= htmlspecialchars($app_base . '/teacher/presence', ENT_QUOTES, 'UTF-8') ?>">
					<?php require __DIR__ . '/teacher.presence.contenu.php'; ?>
				</div>
				<p class="sr-annonce" id="prAnnonce" aria-live="polite" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap"></p>
				<div class="pr-toast" id="toast" role="status" aria-live="polite"></div>
			</div>
			<?php require('../init/footer.php'); ?>
		</div>
	</div>
</div>
<script src="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/src/js/teacher-presence.js?v=<?= @filemtime(__DIR__ . '/js/teacher-presence.js') ?>" defer></script>
</body>
</html>
