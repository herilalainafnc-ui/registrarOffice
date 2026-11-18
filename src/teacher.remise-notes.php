<?php
/**
 * Remise des notes — espace enseignant.
 *
 * Deux vues dans un seul fichier, selon la presence de cours_id :
 *   - sans : la liste des cours enseignes et l'etat de leur remise ;
 *   - avec : la grille de saisie, calquee sur la feuille imprimee
 *            (src/extenssionPrint/remiseNotes.php) pour que l'enseignant
 *            retrouve les memes colonnes dans le meme ordre.
 *
 * Rien de ce qui est saisi ici n'atteint le dossier de l'etudiant : la grille
 * ecrit dans t_remise_notes_ligne, et seule la validation du registrariat
 * reporte les notes dans t_2023_notes (data/remise.notes.php).
 */
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Remise des notes</title>
<?php
require_once('../data/middleware.php');
initMiddleware($dtb);
require_once('../data/remise.notes.php');

/* Les niveaux 1 a 3 entrent pour verifier une feuille au nom de l'enseignant ;
   les enseignants et les chefs de mention (des professeurs) pour leurs cours ;
   les autres roles n'ont rien a y faire. */
if (!aEspaceEnseignant() && (int)($_SESSION['user_level'] ?? 99) > 3) {
	header('Location: ' . $app_base . '/etudiants');
	exit;
}

$teacherUid  = getTeacherUid();
$teacherInfo = null;
if ($teacherUid) {
	$stmt = $dtb->prepare('SELECT * FROM teacher WHERE uid = :uid LIMIT 1');
	$stmt->execute(['uid' => $teacherUid]);
	$teacherInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$anneeCourante = remiseAnneeCourante();
$coursId       = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
$semestreParam = isset($_GET['semestre']) && $_GET['semestre'] !== '' ? (int)$_GET['semestre'] : null;

/* Annees proposees dans le selecteur : celles ou l'enseignant a reellement des
   inscriptions, plus l'annee courante meme si elle est encore vide. */
$anneesAvecCours   = [];
$anneesDisponibles = [$anneeCourante];
if ($teacherUid) {
	$stmt = $dtb->prepare('SELECT DISTINCT n.annee_scolaire
		FROM t_2023_notes n
		JOIN t_2023_cours c ON c.id = n.id_cours
		WHERE c.id_teacher = :uid AND c.remove != 1 AND n.remove = 0
		ORDER BY n.annee_scolaire DESC');
	$stmt->execute(['uid' => $teacherUid]);
	$anneesAvecCours = $stmt->fetchAll(PDO::FETCH_COLUMN);
	foreach ($anneesAvecCours as $a) {
		if (!in_array($a, $anneesDisponibles, true)) { $anneesDisponibles[] = $a; }
	}
}
rsort($anneesDisponibles);

/* Annee par defaut : la plus recente ou l'enseignant a effectivement des
   etudiants. L'annee civile en cours est souvent encore vide en debut d'annee
   scolaire — ouvrir dessus donnait une page vide a un enseignant qui a pourtant
   des feuilles a remettre. */
$anneeDefaut = $anneeCourante;
if (!in_array($anneeCourante, $anneesAvecCours, true) && $anneesAvecCours) {
	$anneeDefaut = $anneesAvecCours[0];
}
$annee = trim((string)($_GET['annee'] ?? $anneeDefaut));
?>
	<style>
		.remise-carte {
			background: rgb(var(--c-surface-2));
			border: 1px solid rgb(var(--c-line));
			border-radius: 12px;
		}
		[data-theme="light"] .remise-carte {
			background: rgb(var(--c-surface));
			border-color: rgb(var(--c-line-strong));
		}
		.remise-entete {
			background: linear-gradient(135deg, rgb(var(--c-accent) / 0.16), rgb(var(--c-accent) / 0.04));
			border-bottom: 1px solid rgb(var(--c-line));
		}
		[data-theme="light"] .remise-entete { border-bottom-color: rgb(var(--c-line-strong)); }

		.remise-etiquette {
			display: inline-flex; align-items: center; gap: 6px;
			padding: 3px 10px; border-radius: 999px;
			font-size: 11px; font-weight: 600; white-space: nowrap;
		}

		/* Grille de saisie : en-tete fige, la liste peut etre longue. */
		.grille { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
		.grille thead th {
			position: sticky; top: 0; z-index: 3;
			background: rgb(var(--c-surface-2)); color: rgb(var(--c-text));
			padding: 8px 6px; font-weight: 600; font-size: 11px;
			border-bottom: 2px solid rgb(var(--c-line)); text-align: center; line-height: 1.25;
		}
		[data-theme="light"] .grille thead th { background: rgb(var(--c-surface-2)); color: rgb(var(--c-text)); border-bottom-color: rgb(var(--c-line-strong)); }
		.grille tbody td {
			padding: 3px 6px; border-bottom: 1px solid rgb(var(--c-line) / 0.55);
			color: rgb(var(--c-text)); vertical-align: middle;
		}
		[data-theme="light"] .grille tbody td { color: rgb(var(--c-text)); border-bottom-color: rgb(var(--c-line)); }
		.grille tbody tr:hover td { background: rgb(var(--c-accent) / 0.10); }
		.grille tbody tr.ligne-focus td { background: rgb(var(--c-accent) / 0.16); }

		/* Une cellule de note : discrete au repos, cadree a la saisie. */
		.cel-note {
			width: 58px; padding: 5px 4px; text-align: center;
			background: transparent; border: 1px solid transparent; border-radius: 6px;
			color: inherit; font-size: 13px; font-variant-numeric: tabular-nums;
		}
		.cel-note:hover  { border-color: rgb(var(--c-accent) / 0.45); }
		.cel-note:focus  { outline: none; border-color: rgb(var(--c-accent)); background: rgb(var(--c-accent) / 0.14); }
		.cel-note.vide::placeholder { color: rgb(var(--c-text-3)); }
		[data-theme="light"] .cel-note.vide::placeholder { color: rgb(var(--c-text-3)); }
		.cel-note:disabled { opacity: .65; cursor: not-allowed; }

		/* Retour visuel de l'enregistrement automatique. */
		.cel-ok     { animation: celOk 1.1s ease-out; }
		.cel-erreur { border-color: #ef4444 !important; background: rgba(239,68,68,0.16) !important; }
		/* Saisie faite sans reseau, pas encore envoyee (src/js/hors-ligne.js) */
		.cel-attente { box-shadow: inset 0 0 0 2px #fbbf24 !important; }
		/* ---- Feuille de calcul : colonnes, ponderation, formule, moyennes ---- */
		.tableur-outils { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 14px; }
		.tableur-outils .bouton { padding: 7px 14px; }
		.tableur-menu {
			position: absolute; z-index: 30; top: calc(100% + 4px); left: 0; min-width: 210px; padding: 4px;
			background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-line-strong)); border-radius: 10px;
			box-shadow: 0 12px 32px rgb(0 0 0 / .35);
		}
		.tableur-menu[hidden] { display: none; }
		.tableur-menu button {
			display: flex; align-items: center; gap: 8px; width: 100%; min-height: 40px; padding: 8px 12px;
			border: 0; border-radius: 8px; background: transparent; color: rgb(var(--c-text)); font-size: 13px; text-align: left; cursor: pointer;
		}
		.tableur-menu button:hover, .tableur-menu button:focus-visible { background: rgb(var(--c-accent) / .16); outline: none; }
		.tableur-formule { display: flex; align-items: stretch; border-top: 1px solid rgb(var(--c-line)); font-size: 12.5px; }
		.tableur-fx {
			display: flex; align-items: center; padding: 6px 12px; font-style: italic; font-weight: 700;
			color: rgb(var(--c-accent-text)); background: rgb(var(--c-raised)); border-right: 1px solid rgb(var(--c-line));
		}
		.tableur-formule #barreFormule { padding: 6px 12px; color: rgb(var(--c-text-2)); font-variant-numeric: tabular-nums; overflow-x: auto; white-space: nowrap; }
		.tableur-cadre { overflow: auto; max-height: calc(100vh - 380px); min-height: 240px; border-top: 1px solid rgb(var(--c-line)); }
		@media (max-width: 640px) { .tableur-cadre { max-height: calc(100vh - 250px); } }
		.tableur { border-collapse: separate; border-spacing: 0; font-size: 13px; width: max-content; min-width: 100%; }
		.tableur th, .tableur td {
			box-sizing: border-box; height: 34px; padding: 0;
			border-right: 1px solid rgb(var(--c-line) / .85); border-bottom: 1px solid rgb(var(--c-line) / .85);
		}
		.tableur td { background: rgb(var(--c-surface-2)); color: rgb(var(--c-text)); }
		[data-theme="light"] .tableur td { background: rgb(var(--c-surface)); }
		.tableur .masque { display: none; }
		.tableur thead th {
			position: sticky; z-index: 4; background: rgb(var(--c-raised)); color: rgb(var(--c-text-2));
			font-weight: 600; font-size: 11.5px; text-align: center;
		}
		.tableur .tt-lettres th { top: 0; height: 22px; font-weight: 500; font-size: 11px; color: rgb(var(--c-text-3)); }
		.tableur .tt-titres th  { top: 22px; height: 44px; line-height: 1.2; padding: 0 6px; }
		.tableur .tt-poids th   { top: 66px; height: 40px; }
		.tableur .tt-poids th.tt-etiq { text-align: left; padding: 0 12px; color: rgb(var(--c-text)); }
		.tableur .col-actif { color: rgb(var(--c-accent-text)); background: rgb(var(--c-accent) / .22); }
		.tableur .tt-num {
			position: sticky; left: 0; z-index: 2; width: 40px; min-width: 40px; text-align: center; font-size: 11px; font-weight: 500;
			background: rgb(var(--c-raised)); color: rgb(var(--c-text-3));
		}
		.tableur tr.ligne-focus .tt-num { background: rgb(var(--c-accent) / .28); color: rgb(var(--c-accent-text)); }
		.tableur thead .tt-num { z-index: 6; }
		.tableur .tt-mat { width: 90px; min-width: 90px; text-align: center; padding: 0 6px; font-family: ui-monospace, monospace; font-size: 11.5px; }
		.tableur .tt-nom { min-width: 220px; padding: 0 10px; white-space: nowrap; }
		.tableur .tt-niv { width: 84px; min-width: 84px; text-align: center; font-size: 11.5px; color: rgb(var(--c-text-2)); }
		@media (min-width: 900px) {
			.tableur td.tt-mat, .tableur th.tt-mat { position: sticky; left: 40px; z-index: 2; }
			.tableur td.tt-nom, .tableur th.tt-nom { position: sticky; left: 130px; z-index: 2; box-shadow: 1px 0 0 rgb(var(--c-line)); }
			.tableur thead th.tt-mat, .tableur thead th.tt-nom { z-index: 5; }
		}
		.tableur tbody tr.ligne-focus td { background: rgb(var(--c-accent) / .10); }
		.tableur tbody tr:hover td { background: rgb(var(--c-accent) / .07); }
		.tableur .tt-col { width: 88px; min-width: 88px; }
		.tableur .tt-titre { display: flex; align-items: center; justify-content: center; gap: 4px; }
		.tableur .js-retirer {
			width: 22px; height: 22px; border: 0; border-radius: 6px; background: transparent; color: rgb(var(--c-text-3));
			font-size: 15px; line-height: 1; cursor: pointer;
		}
		.tableur .js-retirer:hover, .tableur .js-retirer:focus-visible { background: rgba(239,68,68,.2); color: #f87171; outline: none; }
		/* Une cellule : on la remplit, on ne la « voit » pas — le curseur de cellule la designe, comme dans un tableur. */
		.tableur .cel-note {
			display: block; width: 100%; height: 33px; padding: 0 6px; border: 0; border-radius: 0;
			background: transparent; text-align: center; font-size: 13px;
		}
		.tableur .cel-note:hover { background: rgb(var(--c-accent) / .08); }
		.tableur .cel-note:focus { outline: 2px solid rgb(var(--c-accent)); outline-offset: -2px; background: rgb(var(--c-accent) / .12); }
		.tableur .cel-erreur { box-shadow: inset 0 0 0 2px #ef4444; }
		.tableur .champ-poids {
			display: block; width: 100%; height: 39px; padding: 0 6px; border: 0; border-radius: 0; text-align: center;
			font-weight: 700; background: rgb(var(--c-accent) / .10);
		}
		.tableur .champ-poids:focus { outline: 2px solid rgb(var(--c-accent)); outline-offset: -2px; }
		.tableur .tt-note { width: 80px; min-width: 80px; text-align: center; background: rgb(var(--c-raised)); }
		[data-theme="light"] .tableur td.tt-note { background: rgb(var(--c-raised)); }
		.tableur .tt-statut { width: 72px; min-width: 72px; }
		.tableur .tt-der { width: 84px; min-width: 84px; text-align: center; font-size: 10.5px; color: rgb(var(--c-text-3)); }
		.tableur tfoot td, .tableur tfoot th { position: sticky; bottom: 0; z-index: 3; background: rgb(var(--c-raised)); font-weight: 700; text-align: center; font-variant-numeric: tabular-nums; border-top: 2px solid rgb(var(--c-line)); }
		.tableur tfoot th { text-align: left; padding: 0 12px; }
		#totalPoids.tt-ok { background: rgba(34,197,94,.15); color: #4ade80; border: 1px solid #4ade8055; }
		#totalPoids.tt-ko { background: rgba(239,68,68,.15); color: #f87171; border: 1px solid #f8717155; }
		.col-neuve { animation: colNeuve 1.4s ease-out; }
		@keyframes colNeuve { 0% { background: rgb(var(--c-accent) / .55); } 100% { background: transparent; } }
		@media (prefers-reduced-motion: reduce) { .col-neuve { animation: none; } }
		/* ---- Colonnes en nombre libre, tailles reglables a la souris ---- */
		.tableur { table-layout: fixed; width: var(--largeur-table, 1000px); min-width: 0; --h1: 22px; --h2: 44px; --h3: 40px; }
		.tableur .tt-lettres { --h: var(--h1); }
		.tableur .tt-titres  { --h: var(--h2); }
		.tableur .tt-poids   { --h: var(--h3); }
		.tableur th, .tableur td { height: var(--h, 34px); overflow: hidden; }
		.tableur .tt-lettres th, .tableur .tt-titres th, .tableur .tt-poids th { height: var(--h); }
		.tableur .tt-titres th { top: var(--h1); }
		.tableur .tt-poids th  { top: calc(var(--h1) + var(--h2)); }
		.tableur .cel-note { height: calc(var(--h, 34px) - 1px); min-height: 0; }
		.tableur .champ-poids { height: calc(var(--h3) - 1px); min-height: 0; }
		.tableur .tt-col, .tableur .tt-mat, .tableur .tt-niv, .tableur .tt-note, .tableur .tt-statut, .tableur .tt-der, .tableur .tt-num { width: auto; min-width: 0; }
		.tableur .tt-nom { min-width: 0; text-overflow: ellipsis; }
		.tableur .tt-num, .tableur thead th { overflow: visible; }   /* les poignees de redimensionnement debordent de quelques pixels */
		@media (min-width: 900px) {
			.tableur td.tt-mat, .tableur th.tt-mat { left: var(--w-num, 40px); }
			.tableur td.tt-nom, .tableur th.tt-nom { left: calc(var(--w-num, 40px) + var(--w-mat, 90px)); }
		}
		.tableur .tt-titre { padding: 0 4px; min-width: 0; }
		.tableur .tt-titre-nom { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
		.tableur span.tt-titre-nom { white-space: normal; line-height: 1.15; max-height: 100%; }
		@media (min-width: 900px) {
			.tableur .tt-poids th.tt-etiq { position: sticky; left: var(--w-num, 40px); z-index: 5; }
		}
		.tableur input.tt-titre-nom {
			width: 100%; height: 28px; padding: 0 6px; border: 1px solid transparent; border-radius: 6px; background: transparent;
			color: inherit; font: inherit; font-weight: 600; text-align: center;
		}
		.tableur input.tt-titre-nom:hover { border-color: rgb(var(--c-accent) / .45); }
		.tableur input.tt-titre-nom:focus { outline: none; border-color: rgb(var(--c-accent)); background: rgb(var(--c-accent) / .14); }
		.tableur .tt-titres th { padding: 0 2px; }
		.tableur td.cel-sel { background: rgb(var(--c-accent) / .22) !important; box-shadow: inset 0 0 0 1px rgb(var(--c-accent) / .55); }
		body.sel-drag, body.sel-drag * { user-select: none !important; -webkit-user-select: none !important; }
		.tableur-menu-sep { height: 1px; margin: 4px 6px; background: rgb(var(--c-line)); }
		.tableur-menu-sep[hidden], .tableur-menu button[hidden] { display: none; }
		/* Poignees de redimensionnement : bord droit d'un en-tete de colonne, bord bas d'un numero de ligne. */
		.rz-col { position: absolute; top: 0; right: -4px; z-index: 8; width: 9px; height: 100%; cursor: col-resize; touch-action: none; }
		.rz-lig { position: absolute; left: 0; bottom: -4px; z-index: 8; width: 100%; height: 9px; cursor: row-resize; touch-action: none; }
		.rz-col::after, .rz-lig::after { content: ''; position: absolute; opacity: 0; background: rgb(var(--c-accent)); transition: opacity .12s; }
		.rz-col::after { top: 0; bottom: 0; left: 4px; width: 2px; }
		.rz-lig::after { left: 0; right: 0; top: 4px; height: 2px; }
		.rz-col:hover::after, .rz-lig:hover::after, .rz-en-cours::after { opacity: 1; }
		@media (pointer: coarse) { .rz-col { width: 18px; right: -9px; } .rz-col::after { left: 8px; } .rz-lig { height: 18px; bottom: -9px; } .rz-lig::after { top: 8px; } }
		/* Pendant le glissement : rien ne se selectionne, le curseur reste celui du redimensionnement. */
		body.rz-actif, body.rz-actif * { user-select: none !important; -webkit-user-select: none !important; }
		body.rz-actif-col, body.rz-actif-col * { cursor: col-resize !important; }
		body.rz-actif-lig, body.rz-actif-lig * { cursor: row-resize !important; }
		@keyframes celOk {
			0%   { background: rgba(34,197,94,0.45); }
			100% { background: transparent; }
		}

		.note-finale {
			font-weight: 700; font-variant-numeric: tabular-nums;
			font-size: 14px; text-align: center;
		}
		.note-s  { color: #4ade80; }   /* succes */
		.note-e  { color: #f87171; }   /* echec */
		.note-nn { color: rgb(var(--c-text-3)); }   /* pas encore note */
		.note-ko { color: #fbbf24; }   /* incomplete : il manque une composante */

		.champ-poids {
			width: 62px; text-align: center; padding: 5px;
			background: rgb(var(--c-canvas) / 0.6); border: 1px solid rgb(var(--c-line));
			border-radius: 6px; color: rgb(var(--c-text)); font-variant-numeric: tabular-nums;
		}
		[data-theme="light"] .champ-poids { background: rgb(var(--c-surface-2)); border-color: rgb(var(--c-line-strong)); color: rgb(var(--c-text)); }
		.champ-poids:focus { outline: none; border-color: rgb(var(--c-accent)); }

		.bouton {
			display: inline-flex; align-items: center; gap: 8px;
			padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
			border: none; cursor: pointer; transition: filter .15s ease;
		}
		.bouton:hover:not(:disabled) { filter: brightness(1.12); }
		.bouton:disabled { opacity: .5; cursor: not-allowed; }
		.bouton-primaire  { background: rgb(var(--c-accent)); color: #fff; }
		.bouton-secondaire{ background: rgb(var(--c-raised)); color: rgb(var(--c-text)); }
		[data-theme="light"] .bouton-secondaire { background: rgb(var(--c-raised)); color: rgb(var(--c-text)); }

		.journal-ligne {
			display: grid; grid-template-columns: 132px 1fr; gap: 10px;
			padding: 8px 12px; border-bottom: 1px solid rgb(var(--c-line) / 0.5); font-size: 12px;
		}
		[data-theme="light"] .journal-ligne { border-bottom-color: rgb(var(--c-line)); }
		.journal-heure { color: rgb(var(--c-text-3)); font-variant-numeric: tabular-nums; }

		.bandeau {
			padding: 10px 14px; border-radius: 8px; font-size: 13px;
			display: flex; align-items: flex-start; gap: 10px; line-height: 1.5;
		}
		.bandeau-info    { background: rgb(var(--c-accent) / 0.14);  border: 1px solid rgb(var(--c-accent) / 0.4);  color: rgb(var(--c-text-2)); }
		.bandeau-alerte  { background: rgba(245,158,11,0.14); border: 1px solid rgba(245,158,11,0.4); color: #fcd34d; }
		.bandeau-erreur  { background: rgba(239,68,68,0.14);  border: 1px solid rgba(239,68,68,0.4);  color: #fca5a5; }
		.bandeau-succes  { background: rgba(34,197,94,0.14);  border: 1px solid rgba(34,197,94,0.4);  color: #86efac; }

		/* Telephone : la liste des feuilles en cartes. Le tableau a huit
		   colonnes ; le bouton « Saisir » restait hors de l'ecran, a droite. */
		.feuille-mobile { display: none; }
		@media (max-width: 640px) {
			.grille-feuilles thead { display: none; }
			.grille-feuilles, .grille-feuilles tbody { display: block; }
			.grille-feuilles tbody tr {
				display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px;
				padding: 14px 16px; border-bottom: 1px solid rgb(var(--c-line) / 0.6);
			}
			.grille-feuilles tbody tr:last-child { border-bottom: 0; }
			.grille-feuilles tbody td { display: block; padding: 0; border: 0; background: none !important; text-align: left !important; }
			.grille-feuilles td:nth-child(1) { order: 1; flex: 1 1 auto; }
			.grille-feuilles td:nth-child(7) { order: 2; }
			.grille-feuilles td:nth-child(2) { order: 3; flex-basis: 100%; font-size: 15px; font-weight: 600; line-height: 1.35; }
			.grille-feuilles td:nth-child(n+3):nth-child(-n+6) { order: 4; font-size: 12.5px; color: rgb(var(--c-text-2)); }
			.grille-feuilles td:nth-child(n+4):nth-child(-n+6)::before { content: "· "; color: rgb(var(--c-text-3)); }
			.grille-feuilles td:nth-child(8) { order: 5; flex-basis: 100%; margin-top: 6px; }
			.grille-feuilles td:nth-child(8) .bouton { display: flex; width: 100%; min-height: 44px; justify-content: center; font-size: 14px !important; }
			.feuille-mobile { display: inline; }
		}
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="min-h-screen w-full <?=$bg_three_color?>">

	<!-- TOP BAR --><?php require('../init/topbar.php');?>

	<div class="w-full flex flex-col lg:flex-row">

		<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

		<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<?php /* Annee, ouverture d'une feuille et retour : sans recharger (src/js/zone-ajax.js). */ ?>
			<div class="w-full px-4 py-4 flex-1 overflow-auto" id="zoneRemise" data-zone-ajax>

<?php if (!$teacherUid && aEspaceEnseignant()): ?>
	<?php /* Cause la plus frequente d'un espace enseignant vide : le compte
	         existe mais n'est relie a aucune fiche du personnel enseignant. */ ?>
	<div class="bandeau bandeau-alerte">
		<i class="bi bi-exclamation-triangle-fill"></i>
		<div>
			<b>Votre compte n'est relie a aucune fiche d'enseignant.</b><br>
			Sans ce lien, l'application ne sait pas quels cours sont les votres. Demandez au
			registrariat d'ouvrir <em>Creer un compte</em> et de renseigner l'enseignant
			correspondant a votre compte.
		</div>
	</div>
<?php
	// Rien d'autre a afficher : toutes les vues dependent du lien enseignant.
	echo '</div></div></div></div></body></html>';
	exit;
endif;

/* =========================================================================
   VUE 1 — LISTE DES COURS ENSEIGNES
   ====================================================================== */
if (!$coursId):

	require_once(__DIR__ . "/../data/cours.sigle.php");
	coursSigleAliasInstaller($dtb);

	$stmt = $dtb->prepare('SELECT
			c.id, c.Sigle, c.sigle_affiche, c.title, c.nb_crd, c.yearlevel, c.category,
			n.annee_scolaire,
			' . REMISE_SQL_SEMESTRE . ' AS semestre,
			COUNT(*) AS nb_etudiants,
			SUM(CASE WHEN n.grade IS NULL OR n.grade = "" OR n.grade < 0 THEN 1 ELSE 0 END) AS nb_sans_note
		FROM t_2023_cours c
		JOIN t_2023_notes n ON n.id_cours = c.id
		LEFT JOIN t_2023_session s ON s.session_id = n.session_id
		LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
		WHERE c.id_teacher = :uid AND c.remove != 1 AND n.remove = 0
		  AND n.annee_scolaire = :annee
		  AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
		GROUP BY c.id, c.Sigle, c.sigle_affiche, c.title, c.nb_crd, c.yearlevel, c.category, n.annee_scolaire, semestre
		ORDER BY semestre ASC, COALESCE(NULLIF(TRIM(c.sigle_affiche), ""), c.Sigle) ASC');
	$stmt->execute(['uid' => $teacherUid, 'annee' => $annee]);
	$feuilles = $stmt->fetchAll(PDO::FETCH_ASSOC);

	/* Etat des remises deja ouvertes, pour l'afficher en face de chaque cours
	   sans rouvrir une remise par ligne. */
	$etats = [];
	if ($feuilles) {
		$stmt = $dtb->prepare('SELECT cours_id, semestre, statut, modifie_le, soumis_le, traite_le, motif_rejet
			FROM t_remise_notes WHERE teacher_uid = :uid AND annee_scolaire = :annee');
		$stmt->execute(['uid' => $teacherUid, 'annee' => $annee]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
			$etats[$r['cours_id'] . '|' . (string)$r['semestre']] = $r;
		}
	}
?>
	<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
		<div>
			<h1 class="text-2xl font-bold text-white mb-1">
				<i class="bi bi-journal-check mr-2"></i>Remise des notes
			</h1>
			<p class="text-slate-400">
				<?php if ($teacherInfo): ?>
					<?= htmlspecialchars($teacherInfo['name'] . ' ' . $teacherInfo['lastName']) ?> —
				<?php endif; ?>
				<?= count($feuilles) ?> feuille(s) a remettre pour <?= htmlspecialchars($annee) ?>
			</p>
		</div>
		<form method="get" class="flex items-center gap-2">
			<label class="text-slate-400 text-xs">Annee scolaire</label>
			<select name="annee" id="remiseAnnee" class="champ-poids" style="width:auto;padding:7px 10px" data-zone-auto>
				<?php foreach ($anneesDisponibles as $a): ?>
					<option value="<?= htmlspecialchars($a) ?>" <?= $a === $annee ? 'selected' : '' ?>>
						<?= htmlspecialchars($a) ?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="bouton bouton-secondaire" style="padding:6px 12px">Afficher</button></noscript>
		</form>
	</div>

	<div class="bandeau bandeau-info mb-4" data-tuto="remise-principe">
		<i class="bi bi-info-circle-fill"></i>
		<div>
			Vos notes restent dans votre feuille tant que vous ne l'avez pas transmise.
			Une fois transmise, le registrariat la controle : c'est sa validation qui reporte
			les notes dans le bulletin de l'etudiant.
		</div>
	</div>

<?php if (!$feuilles): ?>
	<div class="remise-carte p-10 text-center text-slate-400">
		<i class="bi bi-inbox text-3xl block mb-3"></i>
		Aucun etudiant inscrit a vos cours pour <?= htmlspecialchars($annee) ?>.
	</div>
<?php else: ?>
	<div class="remise-carte overflow-hidden" data-tuto="feuilles">
		<table class="grille grille-feuilles">
			<thead>
				<tr>
					<th class="text-left" style="text-align:left">Sigle</th>
					<th class="text-left" style="text-align:left">Cours</th>
					<th>Niveau</th>
					<th>Cr.</th>
					<th>Semestre</th>
					<th>Etudiants</th>
					<th>Etat de la remise</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php foreach ($feuilles as $f):
		$cle   = $f['id'] . '|' . (string)$f['semestre'];
		$etat  = $etats[$cle] ?? null;
		$info  = remiseStatutInfo($etat['statut'] ?? 'brouillon');
		$lien  = '?cours_id=' . (int)$f['id']
		       . '&annee=' . rawurlencode($annee)
		       . '&semestre=' . rawurlencode((string)$f['semestre']);
?>
				<tr>
					<td class="font-semibold"><?= htmlspecialchars(coursSigleAffiche($f)) ?></td>
					<td><a href="<?= $lien ?>" class="hover:text-cyan-400"><?= htmlspecialchars($f['title']) ?></a></td>
					<td class="text-center text-slate-400"><?= remiseLibelleNiveau($f['yearlevel']) ?></td>
					<td class="text-center text-slate-400"><?= (int)$f['nb_crd'] ?><span class="feuille-mobile"> cr.</span></td>
					<td class="text-center text-slate-400"><?= htmlspecialchars(remiseLibelleSemestre($f['semestre'])) ?></td>
					<td class="text-center"><?= $nbEtu = (int)$f['nb_etudiants'] ?><span class="feuille-mobile"> étudiant<?= $nbEtu > 1 ? 's' : '' ?></span></td>
					<td class="text-center">
						<span class="remise-etiquette"
						      style="background:<?= $info['couleur'] ?>22;color:<?= $info['couleur'] ?>;border:1px solid <?= $info['couleur'] ?>55">
							<i class="<?= $info['icone'] ?>"></i><?= $info['libelle'] ?>
						</span>
						<?php if (!empty($etat['modifie_le'])): ?>
							<div class="text-[10px] text-slate-500 mt-1">
								modifiee le <?= date('d/m/Y a H:i', strtotime($etat['modifie_le'])) ?>
							</div>
						<?php endif; ?>
					</td>
					<td class="text-center">
						<a href="<?= $lien ?>" class="bouton bouton-primaire" style="padding:6px 14px;font-size:12px">
							<?php if (($etat['statut'] ?? 'brouillon') === 'validee'): ?>
								<i class="bi bi-eye"></i>Consulter
							<?php elseif (($etat['statut'] ?? '') === 'soumise'): ?>
								<i class="bi bi-eye"></i>Suivre
							<?php else: ?>
								<i class="bi bi-pencil-square"></i>Saisir
							<?php endif; ?>
						</a>
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

	<script>
	/* Feuilles gardees sur l'appareil (src/js/hors-ligne.js) : on peut les ouvrir et saisir sans reseau. */
	(function () {
		const lancer = () => {
			const h = window.HorsLigne;
			if (!h || !h.actif || !h.garder) { return; }
			const liens = [...document.querySelectorAll('#zoneRemise a[href*="cours_id="]')]
				.map(a => a.getAttribute('href')).slice(0, 12);
			h.garder(liens, {
				cle: 'notes',
				suivantes: doc => { const r = doc.querySelector('a[href^="?annee="]'); return r ? [r.getAttribute('href')] : []; }
			});
		};
		if (window.HorsLigne) { setTimeout(lancer, 1500); }
		else { document.addEventListener('DOMContentLoaded', () => setTimeout(lancer, 2500), { once: true }); }
	})();
	</script>

<?php
/* =========================================================================
   VUE 2 — GRILLE DE SAISIE
   ====================================================================== */
else:

	if (!remiseEnseignantPossedeCours($dtb, $coursId)) {
		echo '<div class="bandeau bandeau-erreur"><i class="bi bi-shield-exclamation"></i>'
		   . '<div>Ce cours ne fait pas partie de vos enseignements.</div></div>';
		echo '</div></div></div></div></body></html>';
		exit;
	}

	$remise = remiseOuvrir($dtb, $coursId, $annee, $semestreParam);
	if (!$remise) {
		echo '<div class="bandeau bandeau-erreur"><i class="bi bi-x-octagon"></i>'
		   . '<div>Cours introuvable.</div></div>';
		echo '</div></div></div></div></body></html>';
		exit;
	}

	$stmt = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id = :id LIMIT 1');
	$stmt->execute(['id' => $coursId]);
	$cours = $stmt->fetch(PDO::FETCH_ASSOC);

	$lignes      = remiseLignes($dtb, (int)$remise['id']);
	/* Assiduite (§476) : un etudiant au-dela de 15 % d'absences doit quitter le cours. */
	require_once(__DIR__ . '/../data/presence.php');
	$assiduite   = presenceEtatsCours($dtb, (int)$remise['cours_id'], $remise['annee_scolaire'], $remise['semestre'] === null ? null : (int)$remise['semestre']);
	$controle    = remiseControler($dtb, $remise, $lignes);
	$journal     = remiseJournal($dtb, (int)$remise['id'], 60);
	$composantes = remiseComposantes();
	$modifiable  = remisePeutSaisir($remise);
	$info        = remiseStatutInfo($remise['statut']);
	$coursValid  = noteEstCoursValidation($cours ?: []);
	$retour      = '?annee=' . rawurlencode($annee);
?>
	<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
		<div class="flex items-center gap-3">
			<a href="<?= $retour ?>" class="bouton bouton-secondaire" style="padding:7px 12px">
				<i class="bi bi-arrow-left"></i>Mes feuilles
			</a>
			<h1 class="text-xl font-bold text-white">
				<?= htmlspecialchars($remise['sigle'] ?? '') ?> — <?= htmlspecialchars($remise['titre_cours'] ?? '') ?>
			</h1>
		</div>
		<span class="remise-etiquette" style="font-size:12px;padding:6px 14px;background:<?= $info['couleur'] ?>22;color:<?= $info['couleur'] ?>;border:1px solid <?= $info['couleur'] ?>55">
			<i class="<?= $info['icone'] ?>"></i><?= $info['libelle'] ?>
		</span>
	</div>

	<?php if ($remise['statut'] === 'rejetee' && !empty($remise['motif_rejet'])): ?>
	<div class="bandeau bandeau-erreur mb-4">
		<i class="bi bi-arrow-counterclockwise"></i>
		<div>
			<b>Le registrariat vous a renvoye cette feuille</b>
			<?php if (!empty($remise['traite_le'])): ?>
				le <?= date('d/m/Y a H:i', strtotime($remise['traite_le'])) ?>
			<?php endif; ?> :<br>
			<?= nl2br(htmlspecialchars($remise['motif_rejet'])) ?><br>
			<span class="text-slate-400">Corrigez les lignes concernees, puis transmettez de nouveau.</span>
		</div>
	</div>
	<?php elseif ($remise['statut'] === 'soumise'): ?>
	<div class="bandeau bandeau-alerte mb-4">
		<i class="bi bi-hourglass-split"></i>
		<div>
			Feuille transmise le <?= !empty($remise['soumis_le']) ? date('d/m/Y a H:i', strtotime($remise['soumis_le'])) : '' ?>
			et en attente du controle du registrariat. Elle est en lecture seule jusqu'a sa validation
			ou son renvoi.
		</div>
	</div>
	<?php elseif ($remise['statut'] === 'validee'): ?>
	<div class="bandeau bandeau-succes mb-4">
		<i class="bi bi-check-circle-fill"></i>
		<div>
			Feuille validee par le registrariat
			<?php if (!empty($remise['traite_le'])): ?>le <?= date('d/m/Y a H:i', strtotime($remise['traite_le'])) ?><?php endif; ?>.
			Les notes figurent desormais dans le bulletin des etudiants.
		</div>
	</div>
	<?php endif; ?>

	<!-- Cartouche du cours : les memes champs que la feuille imprimee -->
	<div class="remise-carte mb-4" data-tuto="feuille-cartouche">
		<div class="remise-entete px-4 py-2 text-white font-semibold">
			<i class="bi bi-file-earmark-text mr-1"></i>Feuille de remise
		</div>
		<div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2 p-4 text-sm">
			<div><span class="text-slate-400">Sigle</span><br><b class="text-white"><?= htmlspecialchars($cours['Sigle'] ?? '') ?></b></div>
			<div><span class="text-slate-400">Credit</span><br><b class="text-white"><?= (int)($cours['nb_crd'] ?? 0) ?></b></div>
			<div><span class="text-slate-400">Niveau</span><br><b class="text-white"><?= remiseLibelleNiveau($cours['yearlevel'] ?? 0) ?></b></div>
			<div><span class="text-slate-400">Semestre</span><br><b class="text-white"><?= htmlspecialchars(remiseLibelleSemestre($remise['semestre'])) ?></b></div>
			<div><span class="text-slate-400">Enseignant</span><br><b class="text-white"><?= $teacherInfo ? htmlspecialchars($teacherInfo['name'] . ' ' . $teacherInfo['lastName']) : '-' ?></b></div>
			<div><span class="text-slate-400">Annee</span><br><b class="text-white"><?= htmlspecialchars($remise['annee_scolaire']) ?></b></div>
			<div><span class="text-slate-400">Etudiants</span><br><b class="text-white"><?= count($lignes) ?></b></div>
			<div><span class="text-slate-400">Notes pretes</span><br><b class="text-white"><?= $controle['pretes'] ?> / <?= $controle['total'] ?></b></div>
		</div>
	</div>

	<?php if ($coursValid): ?>
	<div class="bandeau bandeau-info mb-4">
		<i class="bi bi-info-circle"></i>
		<div>
			Cours de validation : la note ne s'exprime qu'en <b>V</b> (valide) ou <b>E</b> (echec).
			Une moyenne saisie ici sera convertie a la validation — 10 et plus donne V.
		</div>
	</div>
	<?php endif; ?>

<?php
	/* Les colonnes de la feuille : les cinq composantes de base qui servent (ponderation > 0, ou deja des notes),
	   puis toutes les colonnes ajoutees par l'enseignant (autant qu'il veut). Les composantes de base non utilisees
	   restent disponibles dans « + Colonne ». */
	$cols = [];
	foreach ($composantes as $champ => $meta) {
		$p = (float)$remise[$meta['poids']];
		$actif = $p > 0;
		if (!$actif) {
			foreach ($lignes as $l) { if (($l[$champ] ?? null) !== null && $l[$champ] !== '') { $actif = true; break; } }
		}
		$cols[] = ['champ' => $champ, 'cle' => $meta['poids'], 'libelle' => $meta['libelle'], 'poids' => $p, 'extra' => false, 'actif' => $actif];
	}
	foreach ($remise['extras'] ?? [] as $x) {
		$cols[] = ['champ' => 'x:' . $x['cle'], 'cle' => 'px_' . $x['cle'], 'libelle' => $x['libelle'], 'poids' => (float)$x['poids'], 'extra' => true, 'actif' => true];
	}
	$esc  = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	$fmt  = static fn ($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
	$dis  = $modifiable ? '' : ' disabled';
	$mask = static fn (array $c) => $c['actif'] ? '' : ' masque';
	$lettreDe = static function (int $n): string { $l = ''; do { $l = chr(65 + $n % 26) . $l; $n = intdiv($n, 26) - 1; } while ($n >= 0); return $l; };
	$k  = 0;
	$lt = static function () use (&$k, $lettreDe) { return $lettreDe($k++); };

	/* Les balises d'une colonne : rendues ici pour les colonnes existantes, et par les gabarits ci-dessous
	   pour celles qu'on ajoute sans recharger la page (src : le script de cette page, creerColonneDom). */
	$thLettre = static fn (array $c, string $l) => '<th class="tt-col' . $mask($c) . '" data-col="' . $esc($c['champ']) . '">' . $l . '</th>';
	$thTitre  = static fn (array $c) => '<th class="tt-col' . $mask($c) . '" data-col="' . $esc($c['champ']) . '"><div class="tt-titre">'
		. ($c['extra']
			? '<input type="text" class="tt-titre-nom" maxlength="60" value="' . $esc($c['libelle']) . '" aria-label="Nom de la colonne"' . $dis . '>'
			: '<span class="tt-titre-nom">' . $esc($c['libelle']) . '</span>')
		. ($modifiable ? '<button type="button" class="js-retirer" data-col="' . $esc($c['champ']) . '" title="Retirer la colonne ' . $esc($c['libelle'])
			. '" aria-label="Retirer la colonne ' . $esc($c['libelle']) . '">&times;</button>' : '')
		. '</div></th>';
	$thPoids  = static fn (array $c) => '<th class="tt-col' . $mask($c) . '" data-col="' . $esc($c['champ']) . '">'
		. '<input type="number" class="champ-poids js-poids" min="0" max="100" step="any" data-poids="' . $esc($c['cle'])
		. '" aria-label="Ponderation de la colonne ' . $esc($c['libelle']) . ' en pourcent" value="' . $fmt($c['poids']) . '"' . $dis . '></th>';
	$tdCellule = static fn (array $c, $valeur, string $nom) => '<td class="tt-col' . $mask($c) . '" data-col="' . $esc($c['champ']) . '">'
		. '<input type="text" inputmode="decimal" class="cel-note js-cellule' . ($valeur === null ? ' vide' : '') . '" data-champ="' . $esc($c['champ'])
		. '" placeholder="—" aria-label="' . $esc($c['libelle'] . ' de ' . $nom) . '" value="' . ($valeur === null ? '' : $fmt($valeur)) . '"' . $dis . '></td>';
	$tdMoyenne = static fn (array $c) => '<td class="tt-col js-moyenne' . $mask($c) . '" data-col="' . $esc($c['champ']) . '">—</td>';
	$colonneEl = static fn (array $c) => '<col data-rz="' . $esc($c['champ']) . '" data-col="' . $esc($c['champ']) . '"'
		. ($c['actif'] ? '' : ' class="masque"') . ' style="width:' . ($c['extra'] ? 130 : 96) . 'px">';
	$modele = ['champ' => '__CHAMP__', 'cle' => 'px___CLE__', 'libelle' => '__NOM__', 'poids' => 0, 'extra' => true, 'actif' => true];
	$valeurDe = static fn (array $c, array $l) => $c['extra'] ? ($l['extras'][substr($c['champ'], 2)] ?? null) : ($l[$c['champ']] ?? null);
?>
	<!-- Feuille de calcul : colonnes, ponderation, notes, calcul automatique -->
	<div class="remise-carte mb-4" data-tuto="feuille-grille">
		<div class="remise-entete px-4 py-2 flex items-center justify-between flex-wrap gap-2">
			<span class="text-white font-semibold"><i class="bi bi-table mr-1"></i>Feuille de notes</span>
			<span id="etatSauvegarde" class="text-xs text-slate-400"></span>
		</div>

		<div class="tableur-outils" data-tuto="feuille-poids">
<?php if ($modifiable): ?>
			<div class="relative">
				<button type="button" id="btnAjouterColonne" class="bouton bouton-secondaire" aria-haspopup="menu" aria-expanded="false" aria-controls="menuColonnes">
					<i class="bi bi-plus-lg"></i>Colonne
				</button>
				<div id="menuColonnes" class="tableur-menu" role="menu" hidden>
<?php $actifs = array_column($cols, 'actif', 'champ'); foreach ($composantes as $champ => $meta): ?>
					<button type="button" role="menuitem" data-ajout="<?= $champ ?>"<?= $actifs[$champ] ? ' hidden' : '' ?>><i class="bi bi-layout-three-columns"></i><?= $esc($meta['libelle']) ?></button>
<?php endforeach; ?>
					<div class="tableur-menu-sep" role="separator"></div>
					<button type="button" role="menuitem" data-nouvelle><i class="bi bi-plus-square"></i>Nouvelle colonne…</button>
				</div>
			</div>
			<button type="button" id="btnRepartir" class="bouton bouton-secondaire" title="Donne la meme part a chaque colonne (100 % au total)">
				<i class="bi bi-distribute-horizontal"></i>Répartir à parts égales
			</button>
<?php endif; ?>
		</div>

		<div class="tableur-cadre">
			<table class="tableur" id="tableur">
				<colgroup>
					<col data-rz="num" style="width:40px"><col data-rz="mat" style="width:90px"><col data-rz="nom" style="width:220px"><col data-rz="niv" style="width:84px">
<?php foreach ($cols as $c): ?>
					<?= $colonneEl($c) ?>

<?php endforeach; ?>
					<col data-rz="note" style="width:80px"><col data-rz="statut" style="width:72px"><col data-rz="der" style="width:84px">
				</colgroup>
				<thead>
					<tr class="tt-lettres" aria-hidden="true">
						<th class="tt-num tt-coin" data-rz="num"></th>
						<th class="tt-mat" data-rz="mat"><?= $lt() ?></th>
						<th class="tt-nom" data-rz="nom"><?= $lt() ?></th>
						<th class="tt-niv" data-rz="niv"><?= $lt() ?></th>
<?php foreach ($cols as $c): ?>
						<?= $thLettre($c, $c['actif'] ? $lt() : '') ?>

<?php endforeach; ?>
						<th class="tt-note" data-rz="note"><?= $lt() ?></th>
						<th class="tt-statut" data-rz="statut"><?= $lt() ?></th>
						<th class="tt-der" data-rz="der"><?= $lt() ?></th>
					</tr>
					<tr class="tt-titres">
						<th class="tt-num" data-rz="num">No</th>
						<th class="tt-mat" data-rz="mat">Matricule</th>
						<th class="tt-nom" data-rz="nom" style="text-align:left">Noms</th>
						<th class="tt-niv" data-rz="niv">Niveau</th>
<?php foreach ($cols as $c): ?>
						<?= $thTitre($c) ?>

<?php endforeach; ?>
						<th class="tt-note" data-rz="note">Note<br>/20</th>
						<th class="tt-statut" data-rz="statut">Statut</th>
						<th class="tt-der" data-rz="der">Derniere<br>saisie</th>
					</tr>
					<tr class="tt-poids">
						<th class="tt-num">%</th>
						<th class="tt-etiq" colspan="3" scope="row">Pondération</th>
<?php foreach ($cols as $c): ?>
						<?= $thPoids($c) ?>

<?php endforeach; ?>
						<th class="tt-note"><span id="totalPoids" class="remise-etiquette"></span></th>
						<th class="tt-statut"></th>
						<th class="tt-der"></th>
					</tr>
				</thead>
				<tbody>
<?php if (!$lignes): ?>
					<tr><td colspan="<?= 7 + count($cols) ?>" class="text-center text-slate-400 py-6" style="height:auto">
						Aucun etudiant inscrit a ce cours pour cette annee et ce semestre.
					</td></tr>
<?php else: $numero = 1; foreach ($lignes as $ligne):
		$nom    = trim(($ligne['student_nom'] ?? '') . ' ' . ($ligne['student_prenom'] ?? ''));
?>
					<tr data-student="<?= htmlspecialchars($ligne['student_id']) ?>">
						<th scope="row" class="tt-num"><?= $numero++ ?></th>
						<td class="tt-mat"><?= htmlspecialchars($ligne['student_id']) ?></td>
						<td class="tt-nom"><?= $nom !== '' ? htmlspecialchars($nom) : '<em class="text-red-400">Nom non defini</em>' ?>
							<?php if (($assiduite[$ligne['student_id']] ?? '') === 'depasse'): ?>
								<a href="<?= $app_base ?>/teacher/presence?cours_id=<?= (int)$remise['cours_id'] ?>&annee=<?= rawurlencode($remise['annee_scolaire']) ?>&semestre=<?= rawurlencode((string)$remise['semestre']) ?>#bilan"
								   class="remise-etiquette" style="margin-left:6px;background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(248,113,113,.4);text-decoration:none"
								   title="Au-delà de 15 % d'absences : doit quitter le cours (Bulletin académique, §476)"><i class="bi bi-person-x"></i>Absences &gt; 15 %</a>
							<?php endif; ?>
						</td>
						<td class="tt-niv"><?= remiseLibelleNiveau($ligne['annee_etude'] ?? 0) ?></td>
<?php foreach ($cols as $c): ?>
						<?= $tdCellule($c, $valeurDe($c, $ligne), $nom) ?>

<?php endforeach; ?>
						<td class="tt-note">
							<?php /* Calculee, jamais saisie : la moyenne des colonnes selon la ponderation (remiseCalculerNote). */ ?>
							<output class="note-finale js-note-finale" aria-live="polite">—</output>
						</td>
						<td class="tt-statut">
							<select class="cel-note js-cellule" data-champ="statut_special" aria-label="Statut de <?= htmlspecialchars($nom) ?>"
							        title="V = valide sans note, E = echec sans note, NN = pas encore note"
							        <?= $modifiable ? '' : 'disabled' ?>>
								<option value=""   <?= empty($ligne['statut_special']) ? 'selected' : '' ?>>—</option>
								<option value="V"  <?= ($ligne['statut_special'] ?? '') === 'V'  ? 'selected' : '' ?>>V</option>
								<option value="E"  <?= ($ligne['statut_special'] ?? '') === 'E'  ? 'selected' : '' ?>>E</option>
								<option value="NN" <?= ($ligne['statut_special'] ?? '') === 'NN' ? 'selected' : '' ?>>NN</option>
							</select>
						</td>
						<td class="tt-der js-derniere">
							<?= !empty($ligne['modifie_le']) ? date('d/m H:i', strtotime($ligne['modifie_le'])) : '—' ?>
						</td>
					</tr>
<?php endforeach; endif; ?>
				</tbody>
<?php if ($lignes): ?>
				<tfoot>
					<tr>
						<th colspan="4" scope="row">Moyenne de la classe</th>
<?php foreach ($cols as $c): ?>
						<?= $tdMoyenne($c) ?>

<?php endforeach; ?>
						<td class="tt-note" id="moyenneNote">—</td>
						<td></td>
						<td></td>
					</tr>
				</tfoot>
<?php endif; ?>
			</table>
		</div>
<?php if ($modifiable): ?>
		<?php /* Gabarits d'une colonne ajoutee sans recharger la page : memes balises que ci-dessus. */ ?>
		<template id="tplCol"><?= $colonneEl($modele) ?></template>
		<template id="tplLettre"><?= $thLettre($modele, '') ?></template>
		<template id="tplTitre"><?= $thTitre($modele) ?></template>
		<template id="tplPoids"><?= $thPoids($modele) ?></template>
		<template id="tplCellule"><?= $tdCellule($modele, null, '') ?></template>
		<template id="tplMoyenne"><?= $tdMoyenne($modele) ?></template>
<?php endif; ?>
	</div>

	<!-- Controle et transmission -->
	<div class="remise-carte mb-4" data-tuto="feuille-transmettre">
		<div class="remise-entete px-4 py-2 text-white font-semibold">
			<i class="bi bi-clipboard-check mr-1"></i>Controle avant transmission
		</div>
		<div class="p-4" id="zoneControle">
			<?php require __DIR__ . '/remise/_controle.php'; ?>
		</div>
<?php if ($modifiable): ?>
		<div class="px-4 pb-4 flex items-center gap-3">
			<button type="button" id="btnTransmettre" class="bouton bouton-primaire"
			        <?= $controle['bloquants'] ? 'disabled' : '' ?>>
				<i class="bi bi-send-fill"></i>Transmettre au registrariat
			</button>
			<span class="text-slate-500 text-xs">
				Apres transmission, la feuille passe en lecture seule jusqu'a la decision du registrariat.
			</span>
		</div>
<?php endif; ?>
	</div>

	<!-- Journal -->
	<div class="remise-carte">
		<div class="remise-entete px-4 py-2 flex items-center justify-between cursor-pointer" id="basculeJournal">
			<span class="text-white font-semibold">
				<i class="bi bi-clock-history mr-1"></i>Historique des modifications
			</span>
			<i class="bi bi-chevron-down text-slate-400" id="chevronJournal"></i>
		</div>
		<div id="corpsJournal" style="display:none;max-height:300px;overflow:auto">
<?php if (!$journal): ?>
			<p class="p-5 text-center text-slate-400 text-sm">Aucune modification enregistree.</p>
<?php else: foreach ($journal as $j): ?>
			<div class="journal-ligne">
				<div class="journal-heure"><?= date('d/m/Y H:i:s', strtotime($j['date_action'])) ?></div>
				<div class="text-slate-300">
					<b class="text-white"><?= htmlspecialchars($j['auteur_nom'] ?: 'Compte supprime') ?></b>
					<?php if (!empty($j['auteur_role'])): ?>
						<span class="text-slate-500">(<?= htmlspecialchars($j['auteur_role']) ?>)</span>
					<?php endif; ?>
					<?php
					switch ($j['action']) {
						case 'creation':    echo ' a ouvert la feuille'; break;
						case 'ponderation': echo ' a change la ponderation <b>' . htmlspecialchars((string)$j['champ']) . '</b>'; break;
						case 'soumission':  echo ' a transmis la feuille au registrariat'; break;
						case 'validation':  echo ' a <b style="color:#4ade80">valide</b> la feuille'; break;
						case 'rejet':       echo ' a <b style="color:#f87171">renvoye</b> la feuille'; break;
						default:
							echo ' a saisi <b>' . htmlspecialchars((string)$j['champ']) . '</b>';
							if (!empty($j['student_id'])) {
								echo ' pour <span class="font-mono text-xs">' . htmlspecialchars($j['student_id']) . '</span>';
							}
					}
					?>
					<?php if ($j['ancienne_valeur'] !== null || $j['nouvelle_valeur'] !== null): ?>
						<span class="ml-1">
							<?php if ($j['ancienne_valeur'] !== null): ?>
								<span style="color:#f87171;text-decoration:line-through"><?= htmlspecialchars($j['ancienne_valeur']) ?></span>
								<span class="text-slate-500 mx-1">→</span>
							<?php endif; ?>
							<span style="color:#4ade80"><?= htmlspecialchars($j['nouvelle_valeur'] ?? 'vide') ?></span>
						</span>
					<?php endif; ?>
				</div>
			</div>
<?php endforeach; endif; ?>
		</div>
	</div>

	<script>
	(function () {
		const REMISE_ID = <?= (int)$remise['id'] ?>;
		const URL_SAVE  = APP_BASE + '/app/.remise/enregistrer.php';
		const URL_ENVOI = APP_BASE + '/app/.remise/soumettre.php';
		const POIDS_BASE = <?= json_encode(array_map(fn($m) => $m['poids'], $composantes)) ?>;   // composante de base -> champ de ponderation
		const TABLEUR   = document.getElementById('tableur');

		const etat = document.getElementById('etatSauvegarde');
		let enCours = 0;
		let gardees = 0;             // saisies gardees sur l'appareil depuis l'ouverture de la feuille
		let rafMoy = 0;              // rendez-vous du calcul des moyennes
		let aSynchroniser = false;   // des saisies de cette feuille attendent (ou ont attendu) l'envoi
		const HL = () => (window.HorsLigne && window.HorsLigne.actif) ? window.HorsLigne : null;

		/* Les ecouteurs poses sur document sont retires quand la feuille est rechargee dans la zone (zone-ajax.js). */
		if (window.__remiseDocument) { window.__remiseDocument.forEach(([t, f]) => document.removeEventListener(t, f)); }
		const surDocument = [];
		window.__remiseDocument = surDocument;
		function ecouterDocument(type, f) { document.addEventListener(type, f); surDocument.push([type, f]); }

		function afficherEtat(texte, couleur) {
			etat.textContent = texte;
			etat.style.color = couleur || '#5a8aa8';
		}

		function etatFinal() {
			if (gardees > 0) {
				afficherEtat(gardees + (gardees > 1 ? ' saisies gardées' : ' saisie gardée')
					+ ' sur cet appareil : envoi dès que le réseau revient.', '#fbbf24');
			} else {
				afficherEtat('Toutes les modifications sont enregistrees.', '#4ade80');
			}
		}

		/* Les saisies de CETTE feuille qui attendent le reseau. */
		function enAttente() {
			const h = HL();
			if (!h) { return Promise.resolve([]); }
			return h.lister().then(l => l.filter(i => i.module === 'notes' && i.statut === 'attente'
				&& i.champs.some(p => p[0] === 'remise_id' && p[1] === String(REMISE_ID))));
		}

		/* Un seul enregistrement a la fois, dans l'ordre : coller vingt cellules ou saisir vite ne lance pas vingt
		   requetes en parallele (certaines echouaient, et les reponses arrivaient dans le desordre).
		   Sans reseau, la saisie est rangee sur l'appareil (file de src/js/hors-ligne.js) et part au retour du
		   reseau ; le resultat porte alors enFile et pas de reponse du serveur. */
		let serie = Promise.resolve();
		function envoyer(corps, libelle) {
			enCours++;
			afficherEtat('Enregistrement…', '#fbbf24');
			corps.append('remise_id', REMISE_ID);
			const lancer = () => {
				const h = HL();
				if (h) {
					const champs = [];
					corps.forEach((v, k) => champs.push([k, v]));
					return h.envoyer({ module: 'notes', url: URL_SAVE, champs: champs, libelle: libelle || '' });
				}
				corps.append('csrf_token', CSRF_TOKEN);
				return fetch(URL_SAVE, {
					method: 'POST',
					body: corps,
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				}).then(r => r.json().then(d => ({ ok: r.ok, data: d })))
				  .catch(() => ({ ok: false, data: { message: 'Connexion perdue : rien n\'a ete enregistre.' } }));
			};
			const p = serie.then(lancer, lancer);
			serie = p.catch(() => {});
			return p.finally(() => {
				enCours--;
				if (enCours === 0) { etatFinal(); }
			});
		}

		/* ================================================================ colonnes (base + ajoutees) */
		const cleDe = champ => POIDS_BASE[champ] || ('px_' + champ.slice(2));   // nom du champ de ponderation
		const champsTous = () => [...TABLEUR.querySelectorAll('.tt-titres th[data-col]')].map(t => t.dataset.col);
		const thTitre = champ => TABLEUR.querySelector('.tt-titres th[data-col="' + champ + '"]');
		const libelleDe = champ => {
			const el = (thTitre(champ) || document.createElement('i')).querySelector('.tt-titre-nom');
			return el ? String(el.value !== undefined && el.tagName === 'INPUT' ? el.value : el.textContent).trim() || 'Colonne' : champ;
		};
		const poidsInput = champ => TABLEUR.querySelector('.js-poids[data-poids="' + cleDe(champ) + '"]');
		const colVisible = champ => { const th = thTitre(champ); return !!th && !th.classList.contains('masque'); };
		const champsVisibles = () => champsTous().filter(colVisible);
		const poidsDe = champ => parseFloat((poidsInput(champ) || {}).value) || 0;
		const nombreDe = brut => { const v = String(brut == null ? '' : brut).trim().replace(',', '.'); return v === '' || isNaN(Number(v)) ? null : parseFloat(v); };
		const fmtNote = v => String(Math.round(v * 100) / 100).replace('.', ',');
		const echapAttr = t => String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

		/* Les memes regles que app/.remise/enregistrer.php : une note de 0 a 20, ou rien. */
		function validerCellule(cel) {
			if (cel.dataset.champ === 'statut_special') { return null; }
			const brut = cel.value.trim().replace(',', '.');
			if (brut === '') { return null; }
			if (isNaN(Number(brut))) { return 'Note attendue entre 0 et 20.'; }
			const v = Math.round(parseFloat(brut) * 100) / 100;
			if (v < 0 || v > 20) { return 'Note hors bareme : ' + v + ' (attendu 0 a 20).'; }
			return null;
		}

		/* La note /20 telle que le serveur la calcule (remiseCalculerNote), pour l'afficher sans reseau
		   et pendant la frappe : moyenne ponderee de toutes les colonnes qui ont une ponderation. */
		function calculerNote(tr) {
			const special = (tr.querySelector('[data-champ="statut_special"]').value || '').trim();
			if (special !== '') { return { valeur: null, etat: 'special', detail: special.toUpperCase() }; }
			let somme = 0, poidsUtiles = 0, saisies = 0;
			const manquantes = [];
			champsTous().forEach(champ => {
				const poids = poidsDe(champ);
				if (poids <= 0) { return; }
				poidsUtiles += poids;
				const cel = tr.querySelector('input[data-champ="' + champ + '"]');
				const n = cel ? nombreDe(cel.value) : null;
				if (n === null) { manquantes.push(libelleDe(champ)); return; }
				saisies++;
				somme += n * poids;
			});
			if (poidsUtiles <= 0) { return { valeur: null, etat: 'vide', detail: 'Aucune ponderation definie' }; }
			if (saisies === 0) { return { valeur: null, etat: 'vide', detail: 'Aucune note saisie' }; }
			if (manquantes.length) { return { valeur: null, etat: 'incomplete', detail: 'Manque : ' + manquantes.join(', ') }; }
			return { valeur: Math.round(somme / poidsUtiles * 100) / 100, etat: 'calculee', detail: 'Moyenne ponderee des composantes' };
		}

		function recalculerTout(horsLigne) {
			TABLEUR.querySelectorAll('tr[data-student]').forEach(tr => { peindreNote(tr, calculerNote(tr)); if (horsLigne) { provisoire(tr); } });
		}
		function provisoire(tr) {
			const n = tr.querySelector('.js-note-finale');
			if (n) { n.title = (n.title ? n.title + ' ' : '') + '(calcul provisoire, sans réseau)'; }
		}

		function libelleCellule(tr, cel) {
			const nom = (tr.children[2] ? tr.children[2].textContent : '').replace(/\s+/g, ' ').trim() || tr.dataset.student;
			const quoi = cel.dataset.champ === 'statut_special' ? 'Statut' : libelleDe(cel.dataset.champ);
			return nom + ' — ' + quoi + ' : ' + (cel.value.trim() || 'vide');
		}

		/* --- Somme des ponderations (ligne « Ponderation ») */
		function majTotalPoids() {
			let total = 0;
			TABLEUR.querySelectorAll('.js-poids').forEach(i => { total += parseFloat(i.value) || 0; });
			total = Math.round(total * 100) / 100;
			const badge = document.getElementById('totalPoids');
			const ok = Math.abs(total - 100) < 0.01;
			badge.textContent = 'Σ ' + String(total).replace('.', ',') + ' %';
			badge.title = ok ? 'La somme des pondérations fait 100 %.' : 'La somme des pondérations doit faire 100 %.';
			badge.classList.toggle('tt-ok', ok);
			badge.classList.toggle('tt-ko', !ok);
			TABLEUR.querySelectorAll('.js-poids').forEach(i => { i.max = String(Math.max(0, Math.round((100 - (total - (parseFloat(i.value) || 0))) * 100) / 100)); });
		}

		/* La ponderation de toutes les colonnes part d'un bloc (le serveur recalcule alors chaque note). */
		function envoyerPonderation() {
			const corps = new FormData();
			corps.append('cible', 'ponderation');
			TABLEUR.querySelectorAll('.js-poids').forEach(i => corps.append(i.dataset.poids, i.value || '0'));
			envoyer(corps, 'Ponderation du cours').then(({ ok, data, enFile }) => {
				if (enFile) { gardees++; aSynchroniser = true; recalculerTout(true); etatFinal(); return; }
				if (!ok) {
					afficherEtat(data.message || 'Echec', '#f87171');
					if (data.plafond) { setTimeout(() => (window.ZoneAjax ? ZoneAjax.recharger() : location.reload()), 1800); }   // on reprend ce que le serveur a
					return;
				}
				if (enCours > 0) { return; }   // d'autres changements suivent : l'etat local est plus a jour
				Object.entries(data.recalcul || {}).forEach(([sid, note]) => {
					const tr = TABLEUR.querySelector('tr[data-student="' + CSS.escape(sid) + '"]');
					if (tr) { peindreNote(tr, note); }
				});
			});
		}
		/* La somme ne peut pas depasser 100 % : ce que la frappe ferait depasser est ramene a ce qui reste (les autres
		   colonnes gardent leur part). Le serveur applique la meme regle. */
		function plafonner(inp) {
			const autres = [...TABLEUR.querySelectorAll('.js-poids')].filter(i => i !== inp).reduce((a, i) => a + (parseFloat(i.value) || 0), 0);
			const reste = Math.max(0, Math.round((100 - autres) * 100) / 100);
			const v = parseFloat(inp.value);
			if (isNaN(v)) { return; }
			if (v < 0) { inp.value = '0'; return; }
			if (v > reste + 0.001) {
				inp.value = String(reste);
				const msg = 'Le total des pondérations ne peut pas dépasser 100 % : il reste ' + String(reste).replace('.', ',') + ' % pour cette colonne.';
				afficherEtat(msg, '#fbbf24');
				if (window.Toast && typeof Toast.show === 'function') { Toast.show(echapperTexte(msg), 'warning', { duration: 4000 }); }
			}
		}
		function lierPoids(inp) {
			inp.addEventListener('input', () => { plafonner(inp); majTotalPoids(); recalculerTout(false); planifierFormule(); });   // le calcul suit la frappe
			inp.addEventListener('change', () => { plafonner(inp); majTotalPoids(); envoyerPonderation(); });
		}

		/* --- Note calculee ---------------------------------------------- */
		/* La colonne /20 n'est pas saisissable : elle montre la moyenne ponderee des colonnes,
		   recalculee par le serveur a chaque saisie et a chaque changement de ponderation.
		   Incomplete, elle dit ce qui manque. */
		function peindreNote(tr, note) {
			const champ = tr.querySelector('.js-note-finale');
			if (!champ) { return; }

			champ.classList.remove('note-s', 'note-e', 'note-nn', 'note-ko');
			if (note.etat === 'calculee') {
				champ.textContent = String(note.valeur).replace('.', ',');
				champ.classList.add(note.valeur >= 10 ? 'note-s' : 'note-e');
			} else if (note.etat === 'special') {
				champ.textContent = note.detail;
				champ.classList.add(note.detail === 'E' ? 'note-e' : 'note-nn');
			} else if (note.etat === 'incomplete') {
				champ.textContent = '…';
				champ.classList.add('note-ko');
			} else {
				champ.textContent = '—';
				champ.classList.add('note-nn');
			}
			champ.title = note.etat === 'calculee' ? 'Moyenne ponderee des composantes' : (note.detail || '');
			tr.dataset.note = note.etat === 'calculee' ? note.valeur : '';
			planifierMoyennes();
		}

		/* --- Une cellule : un envoi a chaque cellule quittee ------------- */
		function lierCellule(cel) {
			cel.addEventListener('change', () => {
				const tr = cel.closest('tr');
				cel.classList.remove('cel-erreur', 'cel-ok');
				const refus = validerCellule(cel);
				if (refus) {
					cel.classList.add('cel-erreur');
					cel.title = refus;
					afficherEtat(refus, '#f87171');
					return;
				}
				const corps = new FormData();
				corps.append('student_id', tr.dataset.student);
				corps.append('champ', cel.dataset.champ);
				corps.append('valeur', cel.value.trim());

				envoyer(corps, libelleCellule(tr, cel)).then(({ ok, data, enFile }) => {
					if (!ok) {
						cel.classList.add('cel-erreur');
						cel.title = data.message || 'Valeur refusee';
						afficherEtat(data.message || 'Valeur refusee', '#f87171');
						if (data.fige) { setTimeout(() => (window.ZoneAjax ? ZoneAjax.recharger() : location.reload()), 1500); }
						return;
					}
					cel.classList.toggle('vide', cel.value.trim() === '');
					if (enFile) {   // le reseau manque : la saisie est gardee, la note /20 est calculee ici en attendant
						gardees++;
						aSynchroniser = true;
						cel.classList.add('cel-attente');
						cel.title = 'Enregistré sur cet appareil, pas encore envoyé';
						peindreNote(tr, calculerNote(tr));
						provisoire(tr);
						etatFinal();
						return;
					}
					cel.classList.remove('cel-attente');
					cel.classList.add('cel-ok');
					setTimeout(() => cel.classList.remove('cel-ok'), 1200);

					peindreNote(tr, enCours > 0 ? calculerNote(tr) : data.note);   // d'autres saisies suivent : la reponse serait deja perimee

					const derniere = tr.querySelector('.js-derniere');
					if (derniere) { derniere.textContent = data.heure.slice(0, 5); }
				});
			});

			// Entree descend d'une ligne : on saisit une colonne entiere sans la souris.
			cel.addEventListener('keydown', e => {
				if (e.key !== 'Enter') { return; }
				e.preventDefault();
				const tr = cel.closest('tr');
				const suivante = e.shiftKey ? tr.previousElementSibling : tr.nextElementSibling;
				if (!suivante || !suivante.matches('tr[data-student]')) { cel.blur(); return; }
				const cible = suivante.querySelector('[data-champ="' + cel.dataset.champ + '"]');
				if (cible) { cible.focus(); cible.select && cible.select(); }
			});
		}

		/* --- Le nom d'une colonne ajoutee se change sur place */
		function lierTitre(inp) {
			inp.dataset.avant = inp.value;
			inp.addEventListener('change', () => {
				const th = inp.closest('th');
				const cle = th.dataset.col.slice(2);
				const nom = inp.value.replace(/\s+/g, ' ').trim() || inp.dataset.avant || 'Colonne';
				inp.value = nom;
				if (nom === inp.dataset.avant) { return; }
				inp.dataset.avant = nom;
				majNomsColonnes(th.dataset.col);
				const corps = new FormData();
				corps.append('cible', 'colonne_renommer');
				corps.append('cle', cle);
				corps.append('libelle', nom);
				envoyer(corps, 'Colonne renommée « ' + nom + ' »').then(({ ok, data, enFile }) => {
					if (enFile) { gardees++; aSynchroniser = true; etatFinal(); return; }
					if (!ok) { afficherEtat((data && data.message) || 'Echec', '#f87171'); }
				});
			});
			inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); inp.blur(); } });
			inp.addEventListener('focus', () => inp.select());
		}
		/* Le nom sert aux etiquettes de cellules (lecteurs d'ecran) et a la barre de formule. */
		function majNomsColonnes(champ) {
			const nom = libelleDe(champ);
			TABLEUR.querySelectorAll('tr[data-student]').forEach(tr => {
				const cel = tr.querySelector('input[data-champ="' + champ + '"]');
				const qui = (tr.children[2] ? tr.children[2].textContent : '').replace(/\s+/g, ' ').trim();
				if (cel) { cel.setAttribute('aria-label', nom + ' de ' + qui); }
			});
			const p = poidsInput(champ);
			if (p) { p.setAttribute('aria-label', 'Ponderation de la colonne ' + nom + ' en pourcent'); }
			TABLEUR.querySelectorAll('.js-retirer[data-col="' + champ + '"]').forEach(b => { b.title = 'Retirer la colonne ' + nom; b.setAttribute('aria-label', 'Retirer la colonne ' + nom); });
			planifierFormule();
		}

		// Etat initial, calcule cote serveur puis rendu ici pour n'avoir qu'une seule mise en forme.
		<?php
		$initial = [];
		foreach ($lignes as $ligne) {
			$c = remiseCalculerNote($ligne, $remise);
			$initial[$ligne['student_id']] = ['valeur' => $c['valeur'], 'etat' => $c['etat'], 'detail' => $c['detail']];
		}
		?>
		const NOTES_INITIALES = <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>;
		Object.entries(NOTES_INITIALES).forEach(([sid, note]) => {
			const tr = TABLEUR.querySelector('tr[data-student="' + CSS.escape(sid) + '"]');
			if (tr) { peindreNote(tr, note); }
		});
		TABLEUR.querySelectorAll('.js-poids').forEach(lierPoids);
		TABLEUR.querySelectorAll('.js-cellule').forEach(lierCellule);
		TABLEUR.querySelectorAll('input.tt-titre-nom').forEach(lierTitre);

		/* ================================================================ feuille de calcul */
		/* Les lettres de colonnes, comme dans un tableur (la colonne des numeros de ligne n'en a pas). */
		function majLettres() {
			let i = 0;
			TABLEUR.querySelectorAll('.tt-lettres th:not(.tt-coin)').forEach(th => {
				if (th.classList.contains('masque')) { return; }
				let n = i++, l = '';
				do { l = String.fromCharCode(65 + (n % 26)) + l; n = Math.floor(n / 26) - 1; } while (n >= 0);   // A..Z, AA, AB...
				th.firstChild && th.firstChild.nodeType === 3 ? (th.firstChild.nodeValue = l) : th.insertBefore(document.createTextNode(l), th.firstChild);
			});
		}

		function afficherColonne(champ, oui) {
			if (typeof reinitSelection === 'function') { reinitSelection(); }
			TABLEUR.querySelectorAll('[data-col="' + champ + '"]').forEach(el => el.classList.toggle('masque', !oui));
			majLettres();
			majMenuColonnes();
			majTotalPoids();
			majLargeurTable();
			planifierMoyennes();
		}

		/* Une colonne de base qui a une ponderation ou des notes sert : elle s'affiche (la file hors ligne peut en ramener). */
		function revelerUtiles() {
			champsTous().forEach(champ => {
				const notes = [...TABLEUR.querySelectorAll('input[data-champ="' + champ + '"]')].some(c => c.value.trim() !== '');
				if (!colVisible(champ) && (poidsDe(champ) > 0 || notes)) { afficherColonne(champ, true); }
			});
		}

		/* Une colonne de base retiree : plus de notes, plus de ponderation. */
		function viderLocalement(champ) {
			TABLEUR.querySelectorAll('input[data-champ="' + champ + '"]').forEach(c => { c.value = ''; c.classList.add('vide'); c.classList.remove('cel-attente', 'cel-erreur'); });
			const p = poidsInput(champ);
			if (p) { p.value = '0'; }
			afficherColonne(champ, false);
		}

		/* --- Colonnes ajoutees : le gabarit est rendu par le serveur (memes balises que les colonnes d'origine) */
		const gabarit = id => document.getElementById(id).innerHTML;
		const nouvelleCle = () => (Math.random().toString(36) + '00000000').replace(/[^a-z0-9]/g, '').slice(0, 8);
		function creerColonneDom(cle, nom) {
			const champ = 'x:' + cle;
			if (thTitre(champ)) { return; }
			const remplir = html => html.replace(/__CHAMP__/g, champ).replace(/__CLE__/g, cle).replace(/__NOM__/g, echapAttr(nom));
			const avant = (sel, id) => { const ref = TABLEUR.querySelector(sel); if (ref) { ref.insertAdjacentHTML('beforebegin', remplir(gabarit(id))); } };
			avant('colgroup col[data-rz="note"]', 'tplCol');
			avant('.tt-lettres th.tt-note', 'tplLettre');
			avant('.tt-titres th.tt-note', 'tplTitre');
			avant('.tt-poids th.tt-note', 'tplPoids');
			TABLEUR.querySelectorAll('tbody tr[data-student] td.tt-note').forEach(td => td.insertAdjacentHTML('beforebegin', remplir(gabarit('tplCellule'))));
			avant('#moyenneNote', 'tplMoyenne');
			TABLEUR.querySelectorAll('[data-col="' + champ + '"] .js-cellule').forEach(lierCellule);
			TABLEUR.querySelectorAll('[data-col="' + champ + '"] .js-poids').forEach(lierPoids);
			TABLEUR.querySelectorAll('[data-col="' + champ + '"] input.tt-titre-nom').forEach(lierTitre);
			TABLEUR.querySelectorAll('th[data-col="' + champ + '"]').forEach(ajouterPoignees);
			if (largeurs[champ]) { fixerLargeur(champ, largeurs[champ], false); }
			majNomsColonnes(champ);
			majLettres();
			majMenuColonnes();
			majTotalPoids();
			majLargeurTable();
			planifierMoyennes();
		}
		function retirerColonneDom(champ) {
			if (typeof reinitSelection === 'function') { reinitSelection(); }
			TABLEUR.querySelectorAll('[data-col="' + champ + '"]').forEach(el => el.remove());
			majLettres();
			majMenuColonnes();
			majTotalPoids();
			majLargeurTable();
			recalculerTout(false);
		}

		/* --- Ajouter / retirer une colonne */
		const btnAjout = document.getElementById('btnAjouterColonne');
		const menuCol = document.getElementById('menuColonnes');
		function majMenuColonnes() {
			const nb = champsVisibles().length;
			const compte = document.getElementById('compteColonnes');
			if (compte) { compte.textContent = nb + (nb > 1 ? ' colonnes' : ' colonne'); }
			if (!menuCol) { return; }
			/* Une colonne deja dans la feuille (ou une colonne qui porte deja ce nom) n'est plus proposee. */
			const noms = champsVisibles().map(c => ({ c, nom: libelleDe(c).toLowerCase() }));
			menuCol.querySelectorAll('[data-ajout]').forEach(b => {
				const nom = b.textContent.trim().toLowerCase();
				b.hidden = colVisible(b.dataset.ajout) || noms.some(x => x.c !== b.dataset.ajout && x.nom === nom);
			});
			const sep = menuCol.querySelector('.tableur-menu-sep');
			if (sep) { sep.hidden = !menuCol.querySelector('[data-ajout]:not([hidden])'); }
		}
		function fermerMenu() { if (menuCol && !menuCol.hidden) { menuCol.hidden = true; btnAjout.setAttribute('aria-expanded', 'false'); } }
		function signalerNouvelle(champ) {
			TABLEUR.querySelectorAll('[data-col="' + champ + '"]').forEach(el => { el.classList.remove('col-neuve'); void el.offsetWidth; el.classList.add('col-neuve'); });
		}
		function ajouterColonneExtra() {
			const nb = TABLEUR.querySelectorAll('.tt-titres th[data-col^="x:"]').length;
			const cle = nouvelleCle();
			const nom = 'Colonne ' + (nb + 1);
			creerColonneDom(cle, nom);
			const champ = 'x:' + cle;
			signalerNouvelle(champ);
			const corps = new FormData();
			corps.append('cible', 'colonne_creer');
			corps.append('cle', cle);
			corps.append('libelle', nom);
			envoyer(corps, 'Nouvelle colonne « ' + nom + ' »').then(({ ok, data, enFile }) => {
				if (enFile) { gardees++; aSynchroniser = true; etatFinal(); return; }
				if (!ok) { (window.__hlErreurs = window.__hlErreurs || []).push('colonne_creer refusee : ' + JSON.stringify(data)); retirerColonneDom(champ); afficherEtat((data && data.message) || 'Echec', '#f87171'); }
			});
			const inp = thTitre(champ).querySelector('input.tt-titre-nom');
			if (inp) { inp.focus(); }
			afficherEtat('Colonne ajoutée : donnez-lui un nom, puis indiquez sa pondération.', '#5a8aa8');
		}
		if (btnAjout) {
			btnAjout.addEventListener('click', e => {
				e.stopPropagation();
				majMenuColonnes();
				menuCol.hidden = !menuCol.hidden;
				btnAjout.setAttribute('aria-expanded', menuCol.hidden ? 'false' : 'true');
				if (!menuCol.hidden) { const premier = menuCol.querySelector('button:not([hidden])'); if (premier) { premier.focus(); } }
			});
			menuCol.addEventListener('click', e => {
				const nouvelle = e.target.closest('[data-nouvelle]');
				if (nouvelle) { fermerMenu(); ajouterColonneExtra(); return; }
				const b = e.target.closest('[data-ajout]');
				if (!b) { return; }
				const champ = b.dataset.ajout;
				fermerMenu();
				if (colVisible(champ)) { const deja = poidsInput(champ); if (deja) { deja.focus(); } return; }   // deja dans la feuille
				afficherColonne(champ, true);
				signalerNouvelle(champ);
				const p = poidsInput(champ);
				if (p) { p.focus(); p.select(); }
				afficherEtat('Colonne « ' + libelleDe(champ) + ' » ajoutée : indiquez sa pondération.', '#5a8aa8');
			});
			ecouterDocument('click', e => { if (!menuCol.contains(e.target) && e.target !== btnAjout) { fermerMenu(); } });
			ecouterDocument('keydown', e => { if (e.key === 'Escape' && !menuCol.hidden) { fermerMenu(); btnAjout.focus(); } });
		}

		TABLEUR.addEventListener('click', e => {
			const b = e.target.closest('.js-retirer');
			if (!b) { return; }
			const champ = b.dataset.col;
			const extra = champ.startsWith('x:');
			const nbNotes = [...TABLEUR.querySelectorAll('input[data-champ="' + champ + '"]')].filter(c => c.value.trim() !== '').length;
			const retirer = () => {
				const corps = new FormData();
				corps.append('cible', 'vider_colonne');
				corps.append('champ', champ);
				envoyer(corps, 'Retrait de la colonne ' + libelleDe(champ)).then(({ ok, data, enFile }) => {
					if (!ok) { afficherEtat((data && data.message) || 'Echec', '#f87171'); return; }
					if (extra) { retirerColonneDom(champ); } else { viderLocalement(champ); }
					if (enFile) { gardees++; aSynchroniser = true; recalculerTout(true); etatFinal(); return; }
					if (enCours > 0) { return; }
					Object.entries(data.recalcul || {}).forEach(([sid, note]) => {
						const tr = TABLEUR.querySelector('tr[data-student="' + CSS.escape(sid) + '"]');
						if (tr) { peindreNote(tr, note); }
					});
				});
			};
			if (!extra && !nbNotes && poidsDe(champ) <= 0) { afficherColonne(champ, false); return; }   // colonne de base vide : elle disparait simplement
			if (extra && !nbNotes && poidsDe(champ) <= 0) { retirer(); return; }                          // colonne ajoutee vide : rien a perdre
			const texte = nbNotes ? 'Les ' + nbNotes + ' note(s) de cette colonne seront effacées (le journal les garde)' + (extra ? ' et la colonne supprimée.' : ' et sa pondération passera à 0.')
			                      : (extra ? 'La colonne sera supprimée.' : 'Sa pondération passera à 0.');
			Toast.confirm(texte, { title: 'Retirer la colonne « ' + libelleDe(champ) + ' » ?', confirmText: 'Retirer la colonne', danger: true })
				.then(ok => { if (ok) { retirer(); } });
		});

		/* Repartir 100 % a parts egales entre les colonnes affichees, quel qu'en soit le nombre. */
		const btnRepartir = document.getElementById('btnRepartir');
		if (btnRepartir) {
			btnRepartir.addEventListener('click', () => {
				const champs = champsVisibles();
				if (!champs.length) { afficherEtat('Ajoutez d\'abord une colonne.', '#fbbf24'); return; }
				const part = Math.floor(10000 / champs.length) / 100;
				let dernier = null;
				champsTous().forEach(champ => {
					const i = poidsInput(champ);
					if (!i) { return; }
					if (champs.includes(champ)) { i.value = String(part); dernier = i; } else { i.value = '0'; }
				});
				if (dernier) { dernier.value = String(Math.round((100 - part * (champs.length - 1)) * 100) / 100); }   // le reste va a la derniere
				majTotalPoids();
				recalculerTout(false);
				poidsInput(champs[0]).dispatchEvent(new Event('change', { bubbles: true }));
			});
		}

		/* --- Moyennes de la classe (pied de la feuille) */
		function planifierMoyennes() { cancelAnimationFrame(rafMoy); rafMoy = requestAnimationFrame(majMoyennes); }
		function majMoyennes() {
			TABLEUR.querySelectorAll('.js-moyenne').forEach(td => {
				const v = [...TABLEUR.querySelectorAll('input[data-champ="' + td.dataset.col + '"]')].map(c => nombreDe(c.value)).filter(x => x !== null);
				td.textContent = v.length ? fmtNote(v.reduce((a, b) => a + b, 0) / v.length) : '—';
				td.title = v.length + ' note(s) saisie(s)';
			});
			const notes = [...TABLEUR.querySelectorAll('tr[data-student]')].map(tr => nombreDe(tr.dataset.note)).filter(x => x !== null);
			const m = document.getElementById('moyenneNote');
			if (m) { m.textContent = notes.length ? fmtNote(notes.reduce((a, b) => a + b, 0) / notes.length) : '—'; m.title = notes.length + ' note(s) calculee(s)'; }
		}

		/* --- La formule de la ligne choisie : le calcul, en clair */
		function texteFormule(tr) {
			const nom = (tr.children[2] ? tr.children[2].textContent : '').replace(/\s+/g, ' ').trim() || tr.dataset.student;
			const special = (tr.querySelector('[data-champ="statut_special"]').value || '').trim();
			if (special) { return nom + ' : statut ' + special.toUpperCase() + ' — pas de moyenne.'; }
			const termes = [];
			const manque = [];
			let somme = 0, poids = 0;
			champsTous().forEach(champ => {
				const p = poidsDe(champ);
				if (p <= 0) { return; }
				poids += p;
				const cel = tr.querySelector('input[data-champ="' + champ + '"]');
				const n = cel ? nombreDe(cel.value) : null;
				if (n === null) { manque.push(libelleDe(champ)); termes.push(libelleDe(champ) + ' ? × ' + String(p).replace('.', ',') + ' %'); return; }
				termes.push(libelleDe(champ) + ' ' + String(n).replace('.', ',') + ' × ' + String(p).replace('.', ',') + ' %');
				somme += n * p;
			});
			if (poids <= 0) { return nom + ' : indiquez la pondération d\'au moins une colonne.'; }
			return nom + ' = (' + termes.join(' + ') + ') ÷ ' + String(poids).replace('.', ',') + ' % = '
			     + (manque.length ? 'incomplet (manque : ' + manque.join(', ') + ')' : fmtNote(somme / poids));
		}
		/* Pas de barre permanente (trop d'informations) : le calcul d'une note s'affiche au survol de la note /20. */
		const barre = document.getElementById('barreFormule');
		const aideFormule = barre ? barre.textContent : '';
		let ligneFormule = null;
		function afficherFormule(tr) { ligneFormule = tr; if (barre) { barre.textContent = tr ? texteFormule(tr) : aideFormule; } }
		function planifierFormule() { if (barre && ligneFormule && ligneFormule.isConnected) { afficherFormule(ligneFormule); } }
		TABLEUR.addEventListener('mouseover', e => {
			const n = e.target.closest('.js-note-finale');
			if (!n) { return; }
			const tr = n.closest('tr[data-student]');
			if (tr) { n.title = texteFormule(tr); }
		});

		TABLEUR.addEventListener('focusin', e => {
			const cel = e.target.closest('.js-cellule, .js-poids');
			TABLEUR.querySelectorAll('.col-actif').forEach(el => el.classList.remove('col-actif'));
			if (!cel) { return; }
			const champ = cel.dataset.champ || (cel.dataset.poids && champsTous().find(c => cleDe(c) === cel.dataset.poids));
			if (champ) { TABLEUR.querySelectorAll('.tt-lettres th[data-col="' + champ + '"], .tt-titres th[data-col="' + champ + '"]').forEach(el => el.classList.add('col-actif')); }
			if (cel.matches('input, select')) { cel.dataset.avant = cel.value; }
			if (cel.matches('input.js-cellule')) { cel.select(); }
			if (cel.classList.contains('js-cellule') && !ignorerFocus) { const pos = positionDe(cel); sel = { a: pos, b: pos }; peindreSelection(); }
			afficherFormule(cel.closest('tr[data-student]'));
		});
		/* La note et la formule suivent la frappe, avant meme l'enregistrement. */
		TABLEUR.addEventListener('input', e => {
			const cel = e.target.closest('input.js-cellule');
			if (!cel) { return; }
			const tr = cel.closest('tr');
			if (validerCellule(cel) === null) { peindreNote(tr, calculerNote(tr)); }
			afficherFormule(tr);
			planifierMoyennes();
		});
		ecouterDocument('horsligne', () => { const a = document.activeElement; if (a && TABLEUR.contains(a)) { afficherFormule(a.closest('tr[data-student]')); } });

		/* ================================================================ selection, clavier, presse-papiers */
		/* Comme dans un tableur : une plage de cellules se choisit a la souris (glisser, Maj+clic) ou au clavier
		   (Maj+fleches, Ctrl+A) ; Ctrl+C / Ctrl+X / Ctrl+V copient, coupent et collent des plages (depuis ou vers Excel) ;
		   Suppr efface, Ctrl+D / Ctrl+R remplissent vers le bas / la droite, Ctrl+Z / Ctrl+Y annulent et retablissent,
		   F2 edite, Echap annule, Entree / Maj+Entree descendent / remontent, Debut / Fin / Ctrl+fleches / Page haut / bas
		   se deplacent. Chaque cellule modifiee est enregistree comme une saisie normale. */
		let sel = null;                 // { a: ancre, b: extremite }, chacune { r, c }
		let ignorerFocus = false;
		const lignesDonnees = () => [...TABLEUR.querySelectorAll('tbody tr[data-student]')];
		const colsGrille = () => champsVisibles().concat(['statut_special']);
		const celluleAt = (r, c) => { const tr = lignesDonnees()[r], champ = colsGrille()[c]; return tr && champ ? tr.querySelector('[data-champ="' + champ + '"]') : null; };
		const positionDe = cel => ({ r: lignesDonnees().indexOf(cel.closest('tr')), c: colsGrille().indexOf(cel.dataset.champ) });
		const plage = () => sel ? { r0: Math.min(sel.a.r, sel.b.r), r1: Math.max(sel.a.r, sel.b.r), c0: Math.min(sel.a.c, sel.b.c), c1: Math.max(sel.a.c, sel.b.c) } : null;
		const plusieurs = () => !!sel && (sel.a.r !== sel.b.r || sel.a.c !== sel.b.c);
		function cellulesDePlage() {
			const r = plage(), out = [];
			if (!r) { return out; }
			for (let i = r.r0; i <= r.r1; i++) { for (let j = r.c0; j <= r.c1; j++) { const c = celluleAt(i, j); if (c) { out.push(c); } } }
			return out;
		}
		function peindreSelection() {
			TABLEUR.querySelectorAll('td.cel-sel').forEach(td => td.classList.remove('cel-sel'));
			if (!plusieurs()) { return; }
			cellulesDePlage().forEach(c => { const td = c.closest('td'); if (td) { td.classList.add('cel-sel'); } });
		}
		function reinitSelection() { sel = null; TABLEUR.querySelectorAll('td.cel-sel').forEach(td => td.classList.remove('cel-sel')); }
		function aller(r, c) {
			const nl = lignesDonnees().length, nc = colsGrille().length;
			const cel = celluleAt(Math.max(0, Math.min(nl - 1, r)), Math.max(0, Math.min(nc - 1, c)));
			if (cel) { cel.focus(); cel.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
		}
		function etendre(dr, dc) {
			if (!sel) { return; }
			const nl = lignesDonnees().length, nc = colsGrille().length;
			sel.b = { r: Math.max(0, Math.min(nl - 1, sel.b.r + dr)), c: Math.max(0, Math.min(nc - 1, sel.b.c + dc)) };
			peindreSelection();
			const cel = celluleAt(sel.b.r, sel.b.c);
			if (cel) { cel.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
		}

		/* Annuler / retablir : chaque lot (une saisie, un collage, un effacement...) est une etape. */
		const pileAnnuler = [], pileRefaire = [];
		let lot = null, enAnnulation = false;
		function noterChangement(cel, avant, apres) {
			if (enAnnulation || avant === apres) { return; }
			const it = { cel, avant, apres };
			if (lot) { lot.push(it); return; }
			pileAnnuler.push([it]);
			pileRefaire.length = 0;
			if (pileAnnuler.length > 300) { pileAnnuler.shift(); }
		}
		function dansUnLot(fn) {
			lot = [];
			try { fn(); } finally {
				const l = lot;
				lot = null;
				if (l.length) { pileAnnuler.push(l); pileRefaire.length = 0; if (pileAnnuler.length > 300) { pileAnnuler.shift(); } }
			}
		}
		/* Une valeur posee par le programme : comme si on l'avait saisie (verification, enregistrement, calcul). */
		function appliquerValeur(cel, v) {
			if (!cel || cel.disabled) { return false; }
			v = String(v == null ? '' : v).trim();
			if (cel.tagName === 'SELECT') {
				const opt = [...cel.options].find(o => o.value.toLowerCase() === v.toLowerCase());
				if (!opt && v !== '') { return false; }
				v = opt ? opt.value : '';
			}
			if (cel.value === v) { return false; }
			cel.dataset.avant = cel.value;
			cel.value = v;
			cel.dispatchEvent(new Event('input', { bubbles: true }));
			cel.dispatchEvent(new Event('change', { bubbles: true }));
			return true;
		}
		/* Toute modification validee d'une cellule est notee (celle qu'on tape comme celles du programme). */
		TABLEUR.addEventListener('change', e => {
			const cel = e.target.closest('.js-cellule');
			if (!cel) { return; }
			noterChangement(cel, cel.dataset.avant === undefined ? '' : cel.dataset.avant, cel.value);
			cel.dataset.avant = cel.value;
		}, true);
		function refaireDefaire(depuis, vers, defaire) {
			const l = depuis.pop();
			if (!l) { afficherEtat(defaire ? 'Rien à annuler.' : 'Rien à rétablir.', '#5a8aa8'); return; }
			enAnnulation = true;
			try { (defaire ? l.slice().reverse() : l).forEach(it => { if (it.cel.isConnected) { appliquerValeur(it.cel, defaire ? it.avant : it.apres); } }); }
			finally { enAnnulation = false; }
			vers.push(l);
		}

		function effacerSelection() { dansUnLot(() => cellulesDePlage().forEach(c => appliquerValeur(c, ''))); }
		function remplirSelection(versBas) {
			const r = plage();
			if (!r) { return; }
			dansUnLot(() => {
				if (versBas) {
					const ligne = r.r1 > r.r0;   // une seule ligne : on reprend la cellule du dessus
					for (let j = r.c0; j <= r.c1; j++) {
						const src = celluleAt(ligne ? r.r0 : r.r0 - 1, j);
						if (!src) { continue; }
						for (let i = ligne ? r.r0 + 1 : r.r0; i <= r.r1; i++) { appliquerValeur(celluleAt(i, j), src.value); }
					}
				} else {
					const colonne = r.c1 > r.c0;
					for (let i = r.r0; i <= r.r1; i++) {
						const src = celluleAt(i, colonne ? r.c0 : r.c0 - 1);
						if (!src) { continue; }
						for (let j = colonne ? r.c0 + 1 : r.c0; j <= r.c1; j++) { appliquerValeur(celluleAt(i, j), src.value); }
					}
				}
			});
		}

		/* --- Souris : glisser pour choisir une plage, Maj+clic pour l'etendre */
		let glisseSel = null;
		TABLEUR.addEventListener('pointerdown', e => {
			if (e.target.closest('.rz-col, .rz-lig') || e.button > 0) { return; }
			const cel = e.target.closest('input.js-cellule, select.js-cellule');
			if (!cel) { return; }
			if (e.shiftKey && sel) { e.preventDefault(); sel.b = positionDe(cel); peindreSelection(); return; }
			glisseSel = { depart: cel, actif: false };
		});
		TABLEUR.addEventListener('pointermove', e => {
			if (!glisseSel || !(e.buttons & 1)) { return; }
			const sous = document.elementFromPoint(e.clientX, e.clientY);
			const td = sous && sous.closest ? sous.closest('td') : null;
			const cel = td ? td.querySelector('input.js-cellule, select.js-cellule') : null;
			if (!cel || (!glisseSel.actif && cel === glisseSel.depart)) { return; }
			glisseSel.actif = true;
			document.body.classList.add('sel-drag');
			try { glisseSel.depart.setSelectionRange(0, 0); } catch (x) { /* liste deroulante */ }
			sel = { a: positionDe(glisseSel.depart), b: positionDe(cel) };
			peindreSelection();
		});
		ecouterDocument('pointerup', () => { document.body.classList.remove('sel-drag'); glisseSel = null; });
		TABLEUR.addEventListener('selectstart', e => { if (glisseSel && glisseSel.actif) { e.preventDefault(); } });

		/* --- Clavier */
		TABLEUR.addEventListener('keydown', e => {
			const cel = e.target.closest('input.js-cellule, select.js-cellule');
			if (!cel) { return; }
			const mod = e.ctrlKey || e.metaKey, cle = e.key, liste = cel.tagName === 'SELECT';
			const modifiee = cel.dataset.avant !== undefined && cel.value !== cel.dataset.avant;       // saisie pas encore validee
			const pret = plusieurs() || liste || (!modifiee && cel.selectionStart === 0 && cel.selectionEnd === cel.value.length);   // « mode selection » : les fleches changent de cellule
			const pos = positionDe(cel);

			if (cle === 'Escape') {
				if (cel.dataset.avant !== undefined && cel.value !== cel.dataset.avant) { cel.value = cel.dataset.avant; cel.classList.remove('cel-erreur'); const tr = cel.closest('tr'); peindreNote(tr, calculerNote(tr)); afficherFormule(tr); }
				const p0 = positionDe(cel); sel = { a: p0, b: p0 }; peindreSelection();
				cel.blur();
				return;
			}
			if (mod && !e.altKey) {
				const k = String(cle).toLowerCase();
				if (k === 'a' && pret) { e.preventDefault(); sel = { a: { r: 0, c: 0 }, b: { r: lignesDonnees().length - 1, c: colsGrille().length - 1 } }; peindreSelection(); return; }
				if (k === 'z' && !modifiee) { e.preventDefault(); if (e.shiftKey) { refaireDefaire(pileRefaire, pileAnnuler, false); } else { refaireDefaire(pileAnnuler, pileRefaire, true); } return; }
				if (k === 'y' && !modifiee) { e.preventDefault(); refaireDefaire(pileRefaire, pileAnnuler, false); return; }
				if (k === 'd') { e.preventDefault(); remplirSelection(true); return; }
				if (k === 'r') { e.preventDefault(); remplirSelection(false); return; }
				if (pret || !(cle === 'ArrowLeft' || cle === 'ArrowRight' || cle === 'Home' || cle === 'End')) {
					if (cle === 'Home') { e.preventDefault(); aller(0, 0); return; }
					if (cle === 'End') { e.preventDefault(); aller(lignesDonnees().length - 1, colsGrille().length - 1); return; }
					if (cle === 'ArrowUp') { e.preventDefault(); if (e.shiftKey) { etendre(-pos.r, 0); } else { aller(0, pos.c); } return; }
					if (cle === 'ArrowDown') { e.preventDefault(); if (e.shiftKey) { etendre(1e6, 0); } else { aller(1e6, pos.c); } return; }
				}
				if (pret && cle === 'ArrowLeft') { e.preventDefault(); if (e.shiftKey) { etendre(0, -1e6); } else { aller(pos.r, 0); } return; }
				if (pret && cle === 'ArrowRight') { e.preventDefault(); if (e.shiftKey) { etendre(0, 1e6); } else { aller(pos.r, 1e6); } return; }
				return;   // Ctrl+C / X / V : evenements copy, cut, paste ci-dessous ; les autres combinaisons restent au navigateur
			}
			if (cle === 'F2' && !liste) { e.preventDefault(); const n = cel.value.length; cel.setSelectionRange(n, n); cel.dataset.avant = cel.value; return; }
			if ((cle === 'Delete' || cle === 'Backspace') && (plusieurs() || liste)) { e.preventDefault(); effacerSelection(); return; }

			const ancre = sel ? sel.b : pos;
			if (cle === 'ArrowDown' || cle === 'ArrowUp') {
				e.preventDefault();
				const d = cle === 'ArrowDown' ? 1 : -1;
				if (e.shiftKey && pret) { if (!sel) { sel = { a: pos, b: pos }; } etendre(d, 0); } else { aller((plusieurs() ? positionDe(cel).r : pos.r) + d, pos.c); }
				return;
			}
			if (cle === 'ArrowLeft' || cle === 'ArrowRight') {
				const d = cle === 'ArrowRight' ? 1 : -1;
				const bord = d < 0 ? (cel.selectionStart === 0 && cel.selectionEnd === 0) : (cel.selectionStart === cel.value.length && cel.selectionEnd === cel.value.length);
				if (e.shiftKey) { if (pret) { e.preventDefault(); if (!sel) { sel = { a: pos, b: pos }; } etendre(0, d); } return; }   // sinon : selection de texte native
				if (!(pret || bord)) { return; }
				e.preventDefault();
				aller(pos.r, pos.c + d);
				return;
			}
			if (pret && !e.shiftKey) {
				if (cle === 'Home') { e.preventDefault(); aller(pos.r, 0); return; }
				if (cle === 'End') { e.preventDefault(); aller(pos.r, 1e6); return; }
				if (cle === 'PageDown') { e.preventDefault(); aller(pos.r + 10, pos.c); return; }
				if (cle === 'PageUp') { e.preventDefault(); aller(pos.r - 10, pos.c); return; }
			}
			void ancre;
		});

		/* --- Copier, couper, coller (plages) */
		function tsvDePlage() {
			const r = plage(), lignes = [];
			if (!r) { return ''; }
			for (let i = r.r0; i <= r.r1; i++) {
				const v = [];
				for (let j = r.c0; j <= r.c1; j++) { const c = celluleAt(i, j); v.push(c ? c.value : ''); }
				lignes.push(v.join('\t'));
			}
			return lignes.join('\n');
		}
		TABLEUR.addEventListener('copy', e => {
			const cel = e.target.closest('.js-cellule');
			if (!cel || !(plusieurs() || cel.tagName === 'SELECT')) { return; }   // une seule case : copie native du texte
			e.preventDefault();
			e.clipboardData.setData('text/plain', plusieurs() ? tsvDePlage() : cel.value);
			afficherEtat((plusieurs() ? cellulesDePlage().length : 1) + ' cellule(s) copiée(s).', '#5a8aa8');
		});
		TABLEUR.addEventListener('cut', e => {
			const cel = e.target.closest('.js-cellule');
			if (!cel || cel.disabled || !(plusieurs() || cel.tagName === 'SELECT')) { return; }
			e.preventDefault();
			e.clipboardData.setData('text/plain', plusieurs() ? tsvDePlage() : cel.value);
			effacerSelection();
		});
		TABLEUR.addEventListener('paste', e => {
			const cel = e.target.closest('.js-cellule');
			if (!cel || cel.disabled) { return; }
			const texte = (e.clipboardData || window.clipboardData).getData('text');
			const bloc = texte.replace(/\r/g, '').replace(/\n+$/, '').split('\n').map(l => l.split('\t'));
			const unique = bloc.length === 1 && bloc[0].length === 1;
			if (unique && !plusieurs() && cel.tagName !== 'SELECT') { return; }   // une valeur dans une case : collage natif
			e.preventDefault();
			const r = plage() || (() => { const p0 = positionDe(cel); return { r0: p0.r, c0: p0.c, r1: p0.r, c1: p0.c }; })();
			let n = 0;
			dansUnLot(() => {
				if (unique) { cellulesDePlage().concat(plusieurs() ? [] : [cel]).forEach(c => { if (appliquerValeur(c, bloc[0][0])) { n++; } }); }
				else { bloc.forEach((valeurs, i) => valeurs.forEach((v, j) => { if (appliquerValeur(celluleAt(r.r0 + i, r.c0 + j), v)) { n++; } })); }
			});
			afficherEtat(n + ' cellule(s) collée(s).', '#5a8aa8');
		});

		/* ================================================================ largeurs et hauteurs a la souris */
		/* Tirer la bordure droite d'un en-tete de colonne (A, B, C... ou son titre) change la largeur de la colonne ;
		   tirer la bordure basse d'un numero de ligne change la hauteur de la ligne. Les tailles sont gardees sur cet
		   appareil, par feuille. Minimum : 50 px de large, 24 px de haut. */
		const LARG_MIN = 50, HAUT_MIN = 24;
		const LARG_DEFAUT = { num: 40, mat: 90, nom: 220, niv: 84, note: 80, statut: 72, der: 84 };
		const HAUT_DEFAUT = { h1: 22, h2: 44, h3: 40 };
		const CLE_ETAT = 'registrar-feuille-' + REMISE_ID;
		let tailles = { l: {}, h: {} };
		try { tailles = Object.assign({ l: {}, h: {} }, JSON.parse(localStorage.getItem(CLE_ETAT) || '{}')); } catch (e) { /* stockage indisponible */ }
		const largeurs = tailles.l, hauteurs = tailles.h;
		function sauverTailles() { try { localStorage.setItem(CLE_ETAT, JSON.stringify(tailles)); } catch (e) { /* tant pis */ } }

		const colEl = cle => TABLEUR.querySelector('colgroup col[data-rz="' + cle + '"]');
		const largeurDe = cle => { const c = colEl(cle); return parseFloat(c && c.style.width) || LARG_DEFAUT[cle] || (cle.startsWith('x:') ? 130 : 96); };
		function majLargeurTable() {
			let total = 0;
			TABLEUR.querySelectorAll('colgroup col').forEach(c => { if (!c.classList.contains('masque')) { total += parseFloat(c.style.width) || 0; } });
			TABLEUR.style.setProperty('--largeur-table', total + 'px');
			TABLEUR.style.setProperty('--w-num', largeurDe('num') + 'px');
			TABLEUR.style.setProperty('--w-mat', largeurDe('mat') + 'px');
		}
		function fixerLargeur(cle, px, garder) {
			const c = colEl(cle);
			if (!c) { return; }
			px = Math.max(LARG_MIN, Math.round(px));
			c.style.width = px + 'px';
			largeurs[cle] = px;
			majLargeurTable();
			if (garder) { sauverTailles(); }
		}
		function fixerHauteur(tr, px, garder) {
			px = Math.max(HAUT_MIN, Math.round(px));
			const cle = tr.classList.contains('tt-lettres') ? 'h1' : tr.classList.contains('tt-titres') ? 'h2' : tr.classList.contains('tt-poids') ? 'h3' : 'r:' + tr.dataset.student;
			if (/^h\d$/.test(cle)) { TABLEUR.style.setProperty('--' + cle, px + 'px'); } else { tr.style.setProperty('--h', px + 'px'); }
			hauteurs[cle] = px;
			if (garder) { sauverTailles(); }
		}
		const hauteurDe = tr => tr.getBoundingClientRect().height;

		/* Les poignees : une par en-tete de colonne (lettres et titres), une par numero de ligne. */
		function ajouterPoignees(el) {
			if (!el || el.querySelector(':scope > .rz-col, :scope > .rz-lig')) { return; }
			if (el.matches('.tt-lettres th, .tt-titres th') && (el.dataset.rz || el.dataset.col)) {
				const h = document.createElement('span');
				h.className = 'rz-col';
				h.setAttribute('aria-hidden', 'true');
				h.title = 'Glisser pour changer la largeur (double-clic : largeur d\'origine)';
				el.appendChild(h);
			}
			if (el.matches('.tt-num')) {
				const h = document.createElement('span');
				h.className = 'rz-lig';
				h.setAttribute('aria-hidden', 'true');
				h.title = 'Glisser pour changer la hauteur (double-clic : hauteur d\'origine)';
				el.appendChild(h);
			}
		}
		/* La cle de colonne d'un en-tete : data-rz pour les colonnes fixes, data-col pour les autres. */
		const cleRz = th => th.dataset.rz || th.dataset.col;
		TABLEUR.querySelectorAll('.tt-lettres th, .tt-titres th, .tt-num').forEach(ajouterPoignees);

		let glisse = null;
		TABLEUR.addEventListener('pointerdown', e => {
			const poignee = e.target.closest('.rz-col, .rz-lig');
			if (!poignee || e.button > 0) { return; }
			e.preventDefault();
			e.stopPropagation();
			const colonne = poignee.classList.contains('rz-col');
			const cellule = poignee.parentElement;
			glisse = colonne
				? { type: 'col', cle: cleRz(cellule), depart: e.clientX, init: largeurDe(cleRz(cellule)) }
				: { type: 'lig', tr: cellule.closest('tr'), depart: e.clientY, init: hauteurDe(cellule.closest('tr')) };
			try { poignee.setPointerCapture(e.pointerId); } catch (x) { /* ancien navigateur */ }
			document.body.classList.add('rz-actif', colonne ? 'rz-actif-col' : 'rz-actif-lig');
			poignee.classList.add('rz-en-cours');
			glisse.poignee = poignee;
		});
		TABLEUR.addEventListener('pointermove', e => {
			if (!glisse) { return; }
			if (glisse.type === 'col') { fixerLargeur(glisse.cle, glisse.init + e.clientX - glisse.depart, false); }
			else { fixerHauteur(glisse.tr, glisse.init + e.clientY - glisse.depart, false); }
		});
		function finGlisse() {
			if (!glisse) { return; }
			glisse.poignee.classList.remove('rz-en-cours');
			document.body.classList.remove('rz-actif', 'rz-actif-col', 'rz-actif-lig');
			sauverTailles();
			glisse = null;
		}
		TABLEUR.addEventListener('pointerup', finGlisse);
		TABLEUR.addEventListener('pointercancel', finGlisse);
		/* Double-clic sur une poignee : taille d'origine. */
		TABLEUR.addEventListener('dblclick', e => {
			const poignee = e.target.closest('.rz-col, .rz-lig');
			if (!poignee) { return; }
			e.preventDefault();
			if (poignee.classList.contains('rz-col')) {
				const cle = cleRz(poignee.parentElement);
				const c = colEl(cle);
				if (c) { c.style.width = (LARG_DEFAUT[cle] || (cle.startsWith('x:') ? 130 : 96)) + 'px'; delete largeurs[cle]; majLargeurTable(); }
			} else {
				const tr = poignee.closest('tr');
				if (/tt-(lettres|titres|poids)/.test(tr.className)) {
					const cle = tr.classList.contains('tt-lettres') ? 'h1' : tr.classList.contains('tt-titres') ? 'h2' : 'h3';
					TABLEUR.style.removeProperty('--' + cle);
					delete hauteurs[cle];
				} else { tr.style.removeProperty('--h'); delete hauteurs['r:' + tr.dataset.student]; }
			}
			sauverTailles();
		});
		/* Rien ne se selectionne ni ne se saisit pendant le glissement. */
		TABLEUR.addEventListener('selectstart', e => { if (glisse) { e.preventDefault(); } });

		/* Les tailles gardees se reappliquent a l'ouverture. */
		Object.keys(largeurs).forEach(cle => { if (colEl(cle)) { fixerLargeur(cle, largeurs[cle], false); } else { delete largeurs[cle]; } });
		Object.keys(hauteurs).forEach(cle => {
			if (/^h\d$/.test(cle)) { TABLEUR.style.setProperty('--' + cle, hauteurs[cle] + 'px'); return; }
			const tr = TABLEUR.querySelector('tr[data-student="' + CSS.escape(cle.slice(2)) + '"]');
			if (tr) { tr.style.setProperty('--h', hauteurs[cle] + 'px'); } else { delete hauteurs[cle]; }
		});

		TABLEUR.querySelectorAll('input.tt-titre-nom').forEach(i => majNomsColonnes(i.closest('th').dataset.col));
		majLettres();
		majMenuColonnes();
		majTotalPoids();
		majLargeurTable();
		planifierMoyennes();

		/* --- Saisies gardees sur l'appareil ------------------------------ */
		/* La feuille affichee peut venir d'une copie ancienne : ce qui attend l'envoi est reporte
		   dessus (la file fait foi), dans l'ordre, avec le marquage « en attente » : colonnes creees,
		   renommees ou retirees, ponderation, notes. */
		function appliquerFile() {
			const h = HL();
			if (!h) { return; }
			h.lister().then(liste => {
				let n = 0;
				liste.forEach(it => {
					if (it.module !== 'notes' || it.statut !== 'attente') { return; }
					const c = {};
					it.champs.forEach(p => { c[p[0]] = p[1]; });
					if (String(c.remise_id) !== String(REMISE_ID)) { return; }
					n++;
					if (c.cible === 'colonne_creer') { creerColonneDom(c.cle, c.libelle || 'Colonne'); return; }
					if (c.cible === 'colonne_renommer') {
						const th = thTitre('x:' + c.cle), inp = th && th.querySelector('input.tt-titre-nom');
						if (inp) { inp.value = c.libelle; inp.dataset.avant = c.libelle; majNomsColonnes('x:' + c.cle); }
						return;
					}
					if (c.cible === 'vider_colonne') { if (c.champ.startsWith('x:')) { retirerColonneDom(c.champ); } else { viderLocalement(c.champ); } return; }
					if (c.cible === 'ponderation') {
						TABLEUR.querySelectorAll('.js-poids').forEach(i => { if (c[i.dataset.poids] !== undefined) { i.value = c[i.dataset.poids]; } });
						return;
					}
					const tr = TABLEUR.querySelector('tr[data-student="' + CSS.escape(c.student_id || '') + '"]');
					const cel = tr && tr.querySelector('[data-champ="' + c.champ + '"]');
					if (!cel) { return; }
					cel.value = c.valeur;
					cel.classList.toggle('vide', String(c.valeur).trim() === '');
					cel.classList.add('cel-attente');
					cel.title = 'Enregistré sur cet appareil, pas encore envoyé';
				});
				if (n) {
					gardees = n;
					aSynchroniser = true;
					revelerUtiles();
					majTotalPoids();
					recalculerTout(true);
					etatFinal();
				}
			});
		}
		if (HL()) { appliquerFile(); }
		else { document.addEventListener('DOMContentLoaded', appliquerFile, { once: true }); }

		/* La file vient de partir : la feuille reprend l'etat du serveur (sauf si une cellule est en cours de saisie). */
		let minuterie = 0;
		function apresEnvoi() {
			if (!etat.isConnected || !aSynchroniser) { return; }
			enAttente().then(reste => {
				if (reste.length) { return; }
				const actif = document.activeElement;
				if (actif && actif.matches && actif.matches('.js-cellule, .js-poids, .tt-titre-nom') && TABLEUR.contains(actif)) { minuterie = setTimeout(apresEnvoi, 3000); return; }
				aSynchroniser = false;
				gardees = 0;
				(window.ZoneAjax ? ZoneAjax.recharger() : location.reload());
			});
		}
		ecouterDocument('horsligne', e => {
			const d = e.detail || {};
			if (!etat.isConnected) { return; }
			if (d.type === 'refuse' && d.item && d.item.module === 'notes') {
				Toast.error(echapperTexte((d.item.libelle ? d.item.libelle + ' : ' : '') + (d.item.message || 'refusé par le serveur'))
				          + '<br>Voir la pastille en bas à gauche.', { duration: 9000 });
			}
			if (d.type === 'fini' || d.type === 'envoye') { clearTimeout(minuterie); minuterie = setTimeout(apresEnvoi, d.type === 'fini' ? 300 : 1500); }
		});

		/* --- Journal ----------------------------------------------------- */
		const bascule = document.getElementById('basculeJournal');
		if (bascule) {
			bascule.addEventListener('click', () => {
				const corps = document.getElementById('corpsJournal');
				const ouvert = corps.style.display !== 'none';
				corps.style.display = ouvert ? 'none' : 'block';
				document.getElementById('chevronJournal').className =
					'bi text-slate-400 ' + (ouvert ? 'bi-chevron-down' : 'bi-chevron-up');
			});
		}

		/* --- Transmission ------------------------------------------------ */
		const btn = document.getElementById('btnTransmettre');
		if (btn) {
			btn.addEventListener('click', () => {
				Toast.confirm('Vous ne pourrez plus la modifier tant qu\'elle n\'aura pas été '
				            + 'validée ou renvoyée.',
				              { title: 'Transmettre cette feuille au registrariat ?', confirmText: 'Transmettre', danger: false })
				.then(ok => { if (ok) { transmettre(); } });
			});

			/* La transmission exige le reseau, et que toutes les saisies faites sans reseau soient parties. */
			function transmettre() {
				btn.disabled = true;
				const h = HL();
				const avant = h ? enAttente().then(l => (l.length ? h.rejouer().then(enAttente) : l)) : Promise.resolve([]);
				avant.then(reste => {
					if (reste.length) {
						btn.disabled = false;
						Toast.error('Des saisies faites hors ligne attendent encore d\'être envoyées (' + reste.length
						          + ') : transmettez la feuille quand le réseau est revenu et que la pastille indique « Tout est envoyé ».',
						            { duration: 10000 });
						return;
					}
					envoiTransmission();
				});
			}

			function envoiTransmission() {
				const corps = new FormData();
				corps.append('csrf_token', CSRF_TOKEN);
				corps.append('remise_id', REMISE_ID);
				fetch(URL_ENVOI, {
					method: 'POST', body: corps,
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				}).then(r => r.json()).then(d => {
					if (d.ok) {
						Toast.success(echapperTexte(d.message || 'Feuille transmise au registrariat.'));
						(window.ZoneAjax ? ZoneAjax.recharger() : location.reload());   // la feuille passe en lecture seule, sans recharger la page
						return;
					}
					btn.disabled = false;
					// Ce qui bloque la transmission, une ligne par point.
					const bloquants = (d.controle && d.controle.bloquants) || [];
					Toast.error(echapperTexte(d.message || 'Transmission refusée.')
					          + (bloquants.length ? '<br>' + bloquants.map(echapperTexte).join('<br>') : ''),
					            { duration: 10000 });
				}).catch(() => { btn.disabled = false; Toast.error('Connexion perdue : la feuille n\'a pas été transmise.'); });
			}
		}

		function echapperTexte(t) {
			return String(t == null ? '' : t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
		}
	})();

	</script>

<?php endif; /* fin vue 2 */ ?>

			</div>
			<?php require('../init/footer.php'); ?>
		</div>
	</div>
</div>
</body>
</html>
