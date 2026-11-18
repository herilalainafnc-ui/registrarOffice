<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Gestion finance</title>
</head>

<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<style>
	/* ========== SHADCN UI STYLE - FINANCE ========== */

	:root {
		--background: 222.2 84% 4.9%;
		--foreground: 210 40% 98%;
		--card: 222.2 84% 4.9%;
		--card-foreground: 210 40% 98%;
		--primary: 199 89% 48%;
		--primary-foreground: 222.2 47.4% 11.2%;
		--secondary: 217.2 32.6% 17.5%;
		--muted: 217.2 32.6% 17.5%;
		--muted-foreground: 215 20.2% 65.1%;
		--border: 217.2 32.6% 17.5%;
		--ring: 199 89% 48%;
		--radius: 0.5rem;
	}

	/* Remove number input spinners */
	input[type=number]::-webkit-inner-spin-button,
	input[type=number]::-webkit-outer-spin-button {
		-webkit-appearance: none;
		margin: 0;
	}
	input[type=number] {
		-moz-appearance: textfield;
	}

	/* Finance Card */
	.finance-card {
		background: linear-gradient(145deg, rgb(var(--c-surface) / 0.8), rgb(var(--c-canvas) / 0.9));
		border: 1px solid rgb(var(--c-line) / 0.5);
		border-radius: 0.75rem;
		backdrop-filter: blur(10px);
		box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
		padding: 1rem;
		margin-bottom: 1rem;
		transition: all 0.2s ease;
	}

	.finance-card:hover {
		border-color: rgb(var(--c-line) / 0.7);
		box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.2);
	}

	.finance-card-title {
		font-size: 1rem;
		font-weight: 600;
		color: rgb(var(--c-text));
		letter-spacing: -0.025em;
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.finance-card-title i {
		color: rgb(var(--c-accent-text));
	}

	/* Semester Badge */
	.semester-badge {
		display: inline-flex;
		align-items: center;
		gap: 0.375rem;
		padding: 0.25rem 0.75rem;
		font-size: 0.7rem;
		font-weight: 600;
		letter-spacing: 0.05em;
		text-transform: uppercase;
		border-radius: 9999px;
		background: rgb(var(--c-accent) / 0.15);
		color: rgb(var(--c-accent-text));
		border: 1px solid rgb(var(--c-accent) / 0.3);
	}

	/* Finance Table */
	.tableFnc {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		border-radius: 0.5rem;
		overflow: hidden;
		border: 1px solid rgb(var(--c-line) / 0.4);
	}

	.tableFnc thead {
		background: rgb(var(--c-surface) / 0.9);
	}

	.tableFnc thead tr:first-child th {
		background: rgb(var(--c-accent) / 0.1);
		border-bottom: 1px solid rgb(var(--c-accent) / 0.2);
		color: rgb(var(--c-accent-text));
		font-weight: 600;
		font-size: 0.8rem;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		padding: 0.6rem 0.5rem;
	}

	.tableFnc thead tr:not(:first-child) th {
		padding: 0.4rem 0.35rem;
		background: rgb(var(--c-surface) / 0.8);
		color: rgb(var(--c-text-3));
		font-weight: 500;
		font-size: 0.7rem;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		border-bottom: 1px solid rgb(var(--c-line) / 0.5);
	}

	.tableFnc tbody td {
		border-bottom: 1px solid rgb(var(--c-line) / 0.25);
		border-right: 1px solid rgb(var(--c-line) / 0.15);
		text-align: left;
		background: transparent;
		padding: 0;
		transition: background 0.15s ease;
	}

	.tableFnc tbody tr {
		transition: background 0.15s ease;
	}

	.tableFnc tbody tr:hover {
		background: rgb(var(--c-raised) / 0.2);
	}

	.tableFnc tbody tr:hover td {
		background: transparent;
	}

	.tableFnc tbody th {
		padding: 0.5rem 0.6rem;
		color: rgb(var(--c-text-2));
		font-size: 0.75rem;
		font-weight: 500;
		background: rgb(var(--c-surface) / 0.4);
		border-bottom: 1px solid rgb(var(--c-line) / 0.25);
		border-right: 1px solid rgb(var(--c-line) / 0.2);
		white-space: nowrap;
	}

	.tableFnc tfoot th {
		padding: 0.6rem;
		background: rgb(var(--c-surface) / 0.4);
		border-top: 1px solid rgb(var(--c-line) / 0.4);
	}

	/* Input Fields */
	.prntInput {
		width: 100px;
		padding: 0;
		font-weight: 600;
	}

	.inputFnc {
		background: rgb(var(--c-canvas) / 0.4);
		padding: 0.4rem 0.5rem;
		width: 100%;
		font-size: 0.7rem;
		text-align: right;
		border: 1px solid transparent;
		border-radius: 0.25rem;
		color: rgb(var(--c-text));
		transition: all 0.2s ease;
		font-weight: 500;
		font-variant-numeric: tabular-nums;
	}

	.inputFnc:hover {
		border-color: rgb(var(--c-accent) / 0.4);
		background: rgb(var(--c-surface) / 0.6);
	}

	.inputFnc:focus {
		outline: none;
		border-color: rgb(var(--c-accent));
		background: rgb(var(--c-surface) / 0.8);
		box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.15);
		color: #ffffff;
	}

	/* Save Button */
	.btn-save {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 0.5rem;
		padding: 0.45rem 1.25rem;
		font-size: 0.8rem;
		font-weight: 500;
		border-radius: 0.375rem;
		cursor: pointer;
		border: none;
		outline: none;
		transition: all 0.2s ease;
	}

	.btn-save-inactive {
		background: rgb(var(--c-raised) / 0.5);
		color: rgb(var(--c-text-3));
		cursor: not-allowed;
		border: 1px solid rgb(var(--c-line) / 0.4);
	}

	.btn-save-active {
		background: linear-gradient(135deg, rgb(var(--c-accent)), rgb(var(--c-accent-strong)));
		color: white;
		box-shadow: 0 1px 3px rgba(14, 165, 233, 0.3);
		border: 1px solid transparent;
	}

	.btn-save-active:hover {
		background: linear-gradient(135deg, rgb(var(--c-accent-strong)), rgb(var(--c-accent-strong)));
		transform: translateY(-1px);
		box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
	}

	/* Page Header */
	.page-header {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		margin-bottom: 1.25rem;
		padding-bottom: 0.75rem;
		border-bottom: 1px solid rgb(var(--c-line) / 0.4);
	}

	.page-header-icon {
		width: 2.5rem;
		height: 2.5rem;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 0.5rem;
		background: linear-gradient(135deg, rgb(var(--c-accent) / 0.2), rgb(var(--c-accent) / 0.1));
		color: rgb(var(--c-accent-text));
		font-size: 1.1rem;
	}

	.page-header-title {
		font-size: 1.35rem;
		font-weight: 700;
		color: rgb(var(--c-text));
		letter-spacing: -0.025em;
	}

	.page-header-desc {
		font-size: 0.8rem;
		color: rgb(var(--c-text-3));
	}

	/* Les dates limites des tranches, par session : /finance/echeances */
	.page-header-lien {
		margin-left: auto;
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		height: 2.25rem;
		padding: 0 0.9rem;
		border-radius: 999px;
		box-shadow: inset 0 0 0 1px rgb(var(--c-line-strong));
		color: rgb(var(--c-accent-text));
		font-size: 0.8rem;
		font-weight: 600;
		text-decoration: none;
		white-space: nowrap;
		transition: background-color .15s;
	}
	.page-header-lien:hover { background: rgb(var(--c-accent) / 0.1); }

	/* Scrollbar */
	.finance-scroll::-webkit-scrollbar {
		width: 6px;
		height: 6px;
	}

	.finance-scroll::-webkit-scrollbar-track {
		background: rgb(var(--c-canvas) / 0.5);
		border-radius: 3px;
	}

	.finance-scroll::-webkit-scrollbar-thumb {
		background: rgb(var(--c-raised) / 0.8);
		border-radius: 3px;
	}

	.finance-scroll::-webkit-scrollbar-thumb:hover {
		background: rgb(var(--c-raised) / 0.8);
	}

	/* Level header coloring */
	.th-licence { color: #a78bfa; }
	.th-master { color: #f59e0b; }
	/* Remise a niveau : niveau 0, jusqu'ici absent de la page. */
	.th-ran { color: rgb(var(--c-accent-text)); }

	/* ===== Barème par session ===== */

	/* Barre collante : on garde sous les yeux l'année et le semestre que l'on
	   modifie, même après avoir défilé dans une longue liste de filières. */
	.bareme-barre {
		position: sticky; top: 0; z-index: 20;
		display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;
		padding: .75rem 1rem; margin-bottom: 1.25rem;
		background: rgb(var(--c-canvas) / .95);
		border: 1px solid rgb(var(--c-line) / .6);
		border-radius: .75rem;
		backdrop-filter: blur(10px);
	}
	.bareme-select {
		background: rgb(var(--c-canvas) / .8);
		border: 1px solid rgb(var(--c-line) / .8);
		color: rgb(var(--c-text)); border-radius: .375rem;
		padding: .4rem .6rem; font-size: .8rem;
	}
	.bareme-select:focus { outline: none; border-color: rgb(var(--c-accent)); }

	.bareme-onglets { display: flex; flex-wrap: wrap; gap: .35rem; }
	.bareme-onglet {
		padding: .35rem .8rem; font-size: .75rem; font-weight: 500;
		border-radius: 9999px; text-decoration: none;
		background: rgb(var(--c-surface) / .7); color: rgb(var(--c-text-3));
		border: 1px solid rgb(var(--c-line) / .6);
	}
	.bareme-onglet:hover { color: rgb(var(--c-text)); border-color: rgb(var(--c-line) / .9); }
	.bareme-onglet[aria-selected="true"] {
		background: rgb(var(--c-accent) / .18); color: rgb(var(--c-accent-text));
		border-color: rgb(var(--c-accent) / .5);
	}

	/* Provenance du tarif : propre à la session, ou hérité de la base. */
	.bareme-etat { font-size: .7rem; padding: .2rem .55rem; border-radius: 9999px; white-space: nowrap; }
	.bareme-etat-propre { background: rgba(16,185,129,.15); color: #34d399; border: 1px solid rgba(16,185,129,.35); }
	.bareme-etat-base   { background: rgb(var(--c-text-3) / .12); color: rgb(var(--c-text-3)); border: 1px solid rgb(var(--c-line-strong) / .3); }
	.bareme-etat-absent { background: rgba(249,115,22,.15); color: #fb923c; border: 1px solid rgba(249,115,22,.4); }

	/* Une case héritée est signalée discrètement : on voit d'un coup d'œil ce
	   qui a été ajusté pour cette session et ce qui vient encore de la base. */
	.inputFnc[data-herite="1"] { color: rgb(var(--c-text-3)); font-style: italic; }
	.inputFnc[data-modifie="1"] {
		border-color: rgb(var(--c-accent)); background: rgb(var(--c-accent) / .12);
		color: #fff; font-style: normal;
	}

	.bareme-action {
		font-size: .72rem; padding: .3rem .7rem; border-radius: .375rem; cursor: pointer;
		background: rgb(var(--c-surface) / .8); color: rgb(var(--c-text-2));
		border: 1px solid rgb(var(--c-line) / .8);
	}
	.bareme-action:hover { color: #fff; border-color: rgb(var(--c-accent)); }
	.bareme-action:disabled { opacity: .5; cursor: wait; }
	.bareme-action-danger:hover { color: #fca5a5; border-color: rgba(239,68,68,.6); }

	.bareme-vide {
		padding: 1.5rem; text-align: center; color: rgb(var(--c-text-3)); font-size: .8rem;
		border: 1px dashed rgb(var(--c-line) / .8); border-radius: .5rem;
	}

	/* =====================================================================
	   MODE CLAIR

	   Cette page n'en avait aucune déclaration : quatre-vingt-cinq couleurs
	   sombres codées en dur, donc des titres #f1f5f9 et des textes #e2e8f0
	   invisibles sur un fond clair. Le thème est posé sur <html> par
	   init/head.php depuis localStorage.

	   Teintes reprises des surcharges de init/head.php, pour que la page
	   s'accorde au reste de l'administration : bleu nuit #0a1628 → #8eb8d4,
	   accent #0369a1 (la variante sombre #38bdf8 tombe à 2,3:1 sur blanc).

	   Les contrastes ont été calculés : #0a1628 18,1:1 · #143755 12,3:1 ·
	   #3d6a8a 5,8:1 · #0369a1 5,9:1 · #047857 5,5:1 · #b45309 5,0:1,
	   tous sur blanc.
	   ===================================================================== */

	[data-theme="light"] .finance-card {
		background: rgb(var(--c-surface));
		border-color: rgb(var(--c-line));
		box-shadow: 0 1px 3px rgba(10, 22, 40, .08);
	}
	[data-theme="light"] .finance-card:hover { border-color: rgb(var(--c-line-strong)); }
	[data-theme="light"] .finance-card-title { color: rgb(var(--c-text)); }
	[data-theme="light"] .finance-card-title i { color: rgb(var(--c-accent-text)); }

	[data-theme="light"] .page-header { border-bottom-color: rgb(var(--c-line)); }
	[data-theme="light"] .page-header-title { color: rgb(var(--c-text)); }
	[data-theme="light"] .page-header-desc { color: rgb(var(--c-text-3)); }
	[data-theme="light"] .page-header-icon {
		background: rgba(3, 105, 161, .08);
		color: rgb(var(--c-accent-text));
	}

	[data-theme="light"] .semester-badge {
		background: rgba(3, 105, 161, .1);
		color: rgb(var(--c-accent-text));
		border-color: rgb(var(--c-line-strong) / .28);
	}

	/* --- Tableau --- */
	[data-theme="light"] .tableFnc { border-color: rgb(var(--c-line)); }
	[data-theme="light"] .tableFnc thead { background: rgb(var(--c-canvas)); }
	[data-theme="light"] .tableFnc thead tr:first-child th {
		background: rgba(3, 105, 161, .08);
		border-bottom-color: rgb(var(--c-line-strong) / .22);
		color: rgb(var(--c-accent-text));
	}
	[data-theme="light"] .tableFnc thead tr:not(:first-child) th {
		background: rgb(var(--c-surface));
		color: rgb(var(--c-text-3));
		border-bottom-color: rgb(var(--c-line));
	}
	[data-theme="light"] .tableFnc tbody td {
		border-bottom-color: rgb(var(--c-line));
		border-right-color: rgb(var(--c-line));
	}
	[data-theme="light"] .tableFnc tbody tr:hover { background: rgb(var(--c-surface)); }
	[data-theme="light"] .tableFnc tbody th {
		background: rgb(var(--c-surface));
		color: rgb(var(--c-text));
		border-bottom-color: rgb(var(--c-line));
		border-right-color: rgb(var(--c-line));
	}
	[data-theme="light"] .tableFnc tfoot th {
		background: rgb(var(--c-surface));
		border-top-color: rgb(var(--c-line));
	}

	/* En-têtes de niveau : les teintes sombres passent mal sur blanc.
	   #7c3aed 6,4:1 · #a16207 4,6:1 · #0e7490 4,7:1. */
	[data-theme="light"] .th-licence { color: #7c3aed; }
	[data-theme="light"] .th-master  { color: #a16207; }
	[data-theme="light"] .th-ran     { color: rgb(var(--c-accent-text)); }

	/* --- Champs de saisie ---
	   !important assumé : init/head.php force tous les inputs en clair avec
	   la même arme, cette règle doit pouvoir la préciser. */
	[data-theme="light"] .inputFnc {
		background-color: rgb(var(--c-surface)) !important;
		border-color: rgb(var(--c-line)) !important;
		color: rgb(var(--c-text)) !important;
		color-scheme: light;
	}
	[data-theme="light"] .inputFnc:hover {
		border-color: rgb(var(--c-line-strong) / .45) !important;
		background: rgb(var(--c-surface)) !important;
	}
	[data-theme="light"] .inputFnc:focus {
		border-color: rgb(var(--c-accent-strong)) !important;
		background: rgb(var(--c-surface)) !important;
		box-shadow: 0 0 0 2px rgba(3, 105, 161, .15);
	}
	/* Valeur héritée du barème de base : gris lisible, pas délavé. */
	[data-theme="light"] .inputFnc[data-herite="1"] { color: rgb(var(--c-text-3)) !important; }
	[data-theme="light"] .inputFnc[data-modifie="1"] {
		border-color: rgb(var(--c-accent-strong)) !important;
		background: rgba(3, 105, 161, .07) !important;
		color: rgb(var(--c-text)) !important;
	}

	/* En mode sombre, color-scheme doit être déclarée aussi : sans elle le
	   navigateur peint le chrome natif du champ selon le thème du système,
	   ce qui donnait des champs clairs sur une page sombre. */
	.inputFnc, .bareme-select { color-scheme: dark; }

	/* --- Boutons --- */
	/* #8eb8d4 ne donnait que 1,84:1 sur ce fond : le libelle du bouton
	   etait illisible. #5a8aa8 atteint 3,26:1 — assez pour se lire, assez
	   efface pour rester visiblement inactif. */
	[data-theme="light"] .btn-save-inactive {
		background: rgb(var(--c-canvas));
		color: rgb(var(--c-text-3));
		border-color: rgb(var(--c-line));
	}
	[data-theme="light"] .btn-save-active {
		background: linear-gradient(135deg, rgb(var(--c-accent-strong)), #075985);
		color: #ffffff;
	}
	[data-theme="light"] .btn-save-active:hover {
		background: linear-gradient(135deg, #075985, #0c4a6e);
	}

	/* ===== Frais supplémentaires ===== */
	.frais-perso-panneau {
		background: rgb(var(--c-canvas) / .55);
		border: 1px solid rgb(var(--c-line) / .6);
		border-radius: .6rem;
		padding: .7rem .9rem;
		margin-bottom: 1rem;
	}
	.frais-perso-entete {
		display: flex; gap: 1rem; align-items: flex-start;
		flex-wrap: wrap; justify-content: space-between;
	}
	.frais-perso-entete b { color: rgb(var(--c-text)); font-size: .85rem; }
	.frais-perso-note { display: block; color: rgb(var(--c-text-3)); font-size: .7rem; max-width: 46rem; margin-top: .15rem; }
	.frais-perso-ajout { display: flex; gap: .4rem; align-items: center; }
	.frais-perso-champ {
		background: rgb(var(--c-canvas) / .6);
		border: 1px solid rgb(var(--c-line) / .8);
		border-radius: .4rem;
		color: rgb(var(--c-text));
		font-size: .75rem;
		padding: .3rem .5rem;
		min-width: 15rem;
		max-width: 100%;
	}
	.frais-perso-champ:focus { outline: none; border-color: rgb(var(--c-accent)); }
	.frais-perso-table { width: 100%; margin-top: .7rem; border-collapse: collapse; font-size: .72rem; }
	.frais-perso-table th {
		text-align: left; color: rgb(var(--c-text-3)); font-weight: 500;
		padding: .2rem .5rem; border-bottom: 1px solid rgb(var(--c-line) / .5);
	}
	.frais-perso-table td {
		padding: .3rem .5rem; color: rgb(var(--c-text));
		border-bottom: 1px solid rgb(var(--c-line) / .25);
		vertical-align: middle;
	}
	.frais-perso-table tr:last-child td { border-bottom: 0; }
	.frais-perso-table td em { color: #fb923c; font-style: normal; font-size: .65rem; }
	.frais-perso-off td { color: rgb(var(--c-text-3)); }
	.frais-perso-select {
		background: rgb(var(--c-canvas) / .6);
		border: 1px solid rgb(var(--c-line) / .8);
		border-radius: .4rem;
		color: rgb(var(--c-text)); font-size: .72rem; padding: .2rem .35rem;
	}
	.frais-perso-sem { display: inline-flex; align-items: center; gap: .2rem; margin-right: .5rem; }
	.frais-perso-sem input { accent-color: rgb(var(--c-accent)); width: .85rem; height: .85rem; }
	.frais-perso-note-inline { color: rgb(var(--c-text-3)) !important; font-size: .65rem; }
	.frais-perso-actions { white-space: nowrap; text-align: right; }
	.frais-perso-cond { color: rgb(var(--c-text-3)) !important; font-size: .62rem; display: block; }
	.frais-perso-bouton {
		background: none; border: 0; color: inherit; cursor: pointer;
		opacity: .7; padding: .1rem .2rem; line-height: 1;
	}
	.frais-perso-bouton:hover { opacity: 1; color: rgb(var(--c-accent-text)); }
	.frais-perso-ligne th { color: rgb(var(--c-accent-text)); }
	.frais-perso-marque { opacity: .7; font-size: .7rem; }

	[data-theme="light"] .frais-perso-panneau { background: rgb(var(--c-surface-2) / .9); border-color: rgb(var(--c-line)); }
	[data-theme="light"] .frais-perso-entete b { color: rgb(var(--c-text)); }
	[data-theme="light"] .frais-perso-note { color: rgb(var(--c-text-3)); }
	[data-theme="light"] .frais-perso-champ { background: rgb(var(--c-surface)); border-color: rgb(var(--c-line)); color: rgb(var(--c-text)) !important; }
	[data-theme="light"] .frais-perso-table td { color: rgb(var(--c-text)); border-bottom-color: rgb(var(--c-line)); }
	[data-theme="light"] .frais-perso-table th { color: rgb(var(--c-text-3)); border-bottom-color: rgb(var(--c-line)); }
	[data-theme="light"] .frais-perso-off td { color: rgb(var(--c-text-3)); }
	[data-theme="light"] .frais-perso-select { background: rgb(var(--c-surface)); border-color: rgb(var(--c-line)); color: rgb(var(--c-text)) !important; }
	[data-theme="light"] .frais-perso-ligne th { color: rgb(var(--c-accent-text)); }
	/* --- Barre de session --- */
	[data-theme="light"] .bareme-barre {
		background: rgb(var(--c-surface-2) / .97);
		border-color: rgb(var(--c-line));
	}
	[data-theme="light"] .bareme-select {
		background: rgb(var(--c-surface));
		border-color: rgb(var(--c-line));
		color: rgb(var(--c-text));
		color-scheme: light;
	}
	[data-theme="light"] .bareme-select:focus { border-color: rgb(var(--c-accent-strong)); }

	[data-theme="light"] .bareme-onglet {
		background: rgb(var(--c-surface));
		color: rgb(var(--c-text-3));
		border-color: rgb(var(--c-line));
	}
	[data-theme="light"] .bareme-onglet:hover { color: rgb(var(--c-text)); border-color: rgb(var(--c-line-strong)); }
	[data-theme="light"] .bareme-onglet[aria-selected="true"] {
		background: rgba(3, 105, 161, .1);
		color: rgb(var(--c-accent-text));
		border-color: rgb(var(--c-line-strong) / .35);
	}

	[data-theme="light"] .bareme-etat-propre {
		background: rgba(4, 120, 87, .08); color: #047857; border-color: rgba(4, 120, 87, .28);
	}
	[data-theme="light"] .bareme-etat-base {
		background: rgba(61, 106, 138, .08); color: rgb(var(--c-text-3)); border-color: rgb(var(--c-line));
	}
	[data-theme="light"] .bareme-etat-absent {
		background: #fef6e7; color: #b45309; border-color: #f0c977;
	}

	[data-theme="light"] .bareme-action {
		background: rgb(var(--c-surface)); color: rgb(var(--c-text)); border-color: rgb(var(--c-line));
	}
	[data-theme="light"] .bareme-action:hover { color: rgb(var(--c-accent-text)); border-color: rgb(var(--c-accent-strong)); }
	[data-theme="light"] .bareme-action-danger:hover { color: #b91c1c; border-color: #b91c1c; }

	[data-theme="light"] .bareme-vide {
		color: rgb(var(--c-text-3));
		border-color: rgb(var(--c-line-strong));
	}

	/* --- Panneau « Appliquer aussi à » --- */
	[data-theme="light"] .bareme-partage {
		background: rgba(3, 105, 161, .05);
		border-color: rgb(var(--c-line-strong) / .25);
	}
	[data-theme="light"] .bareme-partage-titre { color: rgb(var(--c-text)); }
	[data-theme="light"] .bareme-partage-titre b { color: rgb(var(--c-accent-text)); }
	[data-theme="light"] .bareme-partage-choix { color: rgb(var(--c-text)); }
	[data-theme="light"] .bareme-partage-choix:hover { background: rgb(var(--c-canvas)); }
	[data-theme="light"] .bareme-partage-choix input { accent-color: rgb(var(--c-accent-strong)); }
	[data-theme="light"] .bareme-partage-marque { color: #b45309; }
	[data-theme="light"] .bareme-partage-note { color: rgb(var(--c-text-3)); }
	[data-theme="light"] .bareme-partage-note i { color: #b45309; }

	/* --- Ascenseurs --- */
	[data-theme="light"] .finance-scroll::-webkit-scrollbar-track { background: rgb(var(--c-canvas)); }
	[data-theme="light"] .finance-scroll::-webkit-scrollbar-thumb { background: rgb(var(--c-raised)); }
	[data-theme="light"] .finance-scroll::-webkit-scrollbar-thumb:hover { background: rgb(var(--c-raised)); }

	/* ===== Appliquer un barème à plusieurs filières ===== */

	.bareme-partage {
		margin-top: .75rem; padding: .75rem .9rem;
		background: rgb(var(--c-accent) / .06);
		border: 1px solid rgb(var(--c-accent) / .25);
		border-radius: .5rem;
	}
	.bareme-partage-titre { font-size: .75rem; color: rgb(var(--c-text-2)); margin-bottom: .5rem; }
	.bareme-partage-titre b { color: rgb(var(--c-accent-text)); }

	.bareme-partage-liste {
		display: grid; gap: .3rem .9rem;
		grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
	}
	.bareme-partage-choix {
		display: flex; align-items: center; gap: .45rem;
		font-size: .75rem; color: rgb(var(--c-text));
		padding: .3rem .45rem; border-radius: .3rem;
		cursor: pointer;
		/* 44 px au doigt : la page est consultée aussi sur tablette. */
		min-height: 2.1rem;
	}
	.bareme-partage-choix:hover { background: rgb(var(--c-raised) / .45); }
	.bareme-partage-choix input { accent-color: rgb(var(--c-accent)); width: 1rem; height: 1rem; }

	/* Filière déjà ajustée : signalée par une icône, pas par la couleur seule. */
	.bareme-partage-marque { color: #fb923c; font-size: .65rem; }
	.bareme-partage-note {
		margin-top: .5rem; font-size: .68rem; color: rgb(var(--c-text-3));
	}
	.bareme-partage-note i { color: #fb923c; }
</style>
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require_once(__DIR__ . '/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			
				<div class="back flex-1 overflow-hidden">
					
					<div class="p-4 overflow-auto finance-scroll" style="height: calc(100vh - 75px);">

<?php
/* =============================================================================
   Barème financier — par session

   AVANT : la page éditait t_2024_finance_detail_licence sans aucune notion de
   session. Un ajustement de tarif écrasait l'historique et s'appliquait
   rétroactivement à toutes les années — alors que la table portait déjà une
   colonne session_id, inutilisée, à 0 sur ses 192 lignes.

   MAINTENANT : on choisit une année scolaire puis un semestre ; le couple
   désigne une session du catalogue, et c'est cette session qu'on ajuste. Une
   session sans tarif propre hérite du barème de base (session 0), de sorte
   qu'aucune session ne puisse se retrouver sans barème. Voir
   data/finance.bareme.php.

   Le niveau 0 (remise à niveau) est désormais affiché : ses 32 lignes
   existaient en base sans être ni visibles ni modifiables, alors que 66
   étudiants y sont inscrits.
   ========================================================================== */

require_once(__DIR__ . '/../data/finance.bareme.php');

$anneeChoisie  = baremeAnneeChoisie($dtb, $_GET['annee'] ?? null);
$anneesDispo   = baremeAnnees($dtb);
$sessionsAnnee = baremeSessionsAnnee($dtb, $anneeChoisie);

/* Semestre demandé : il doit exister dans l'année choisie. Les sessions d'été
   et d'hiver sont incluses — 19 dossiers de 2025-2026 ont des cours pris en
   session d'été et aucune ligne de frais, faute de barème pour ce semestre. */
$semestresDispo = array_keys($sessionsAnnee);
$semestreChoisi = isset($_GET['semestre']) ? (int) $_GET['semestre'] : 0;
if (!in_array($semestreChoisi, $semestresDispo, true)) {
	$semestreChoisi = $semestresDispo[0] ?? 1;
}

$sessionCourante = $sessionsAnnee[$semestreChoisi] ?? null;
$sessionId       = $sessionCourante ? (int) $sessionCourante['session_id'] : BAREME_BASE;

/* On édite soit une session, soit le barème de base lui-même. */
$editeBase = (($_GET['base'] ?? '') === '1');
$cibleId   = $editeBase ? BAREME_BASE : $sessionId;

/* Compteur « X / N filières ajustées » : sur les seules mentions ouvertes,
   comme les cartes affichées (src/finance/bareme.grille.php). */
require_once(__DIR__ . '/../data/mentions.php');
$mentionsOuvertes  = mentionsSiglesOuverts($dtb);
$nbMentions        = count($mentionsOuvertes);
$mentionsAjusteesN = $cibleId === BAREME_BASE ? 0 : count(array_intersect(baremeMentionsSession($dtb, $cibleId), $mentionsOuvertes));

/** Lien vers la page, paramètres courants conservés. */
function baremeLien(string $annee, int $semestre, bool $base = false): string
{
	global $app_base;

	return $app_base . '/finance?annee=' . urlencode($annee)
		. '&semestre=' . $semestre . ($base ? '&base=1' : '');
}
?>
						<!-- Page Header -->
						<div class="page-header">
							<div class="page-header-icon">
								<i class="bi bi-wallet2"></i>
							</div>
							<div>
								<div class="page-header-title">Gestion Finance</div>
								<div class="page-header-desc">Barème par session — ajustable à chaque semestre, par filière et niveau</div>
							</div>
							<a class="page-header-lien" href="<?=$app_base?>/finance/echeances"><i class="bi bi-calendar-check" aria-hidden="true"></i><span>Échéances de paiement</span></a>
						</div>

						<!-- Barre de session -->
						<div class="bareme-barre">
							<label class="text-slate-400" for="baremeAnnee" style="font-size:.75rem">Année</label>
							<select id="baremeAnnee" class="bareme-select">
								<?php foreach ($anneesDispo as $an) { ?>
									<option value="<?= e($an) ?>"<?= $an === $anneeChoisie ? ' selected' : '' ?>><?= e($an) ?></option>
								<?php } ?>
							</select>

							<?php /* Partie remplacee en AJAX au changement d'annee ou de
							         semestre. Le selecteur d'annee reste en dehors : sa liste
							         ne change pas, et le remplacer lui ferait perdre le focus. */ ?>
							<div id="baremeBarreDyn" class="flex items-center gap-3 flex-1 flex-wrap">
								<?php require(__DIR__ . '/finance/bareme.barre.php'); ?>
							</div>
						</div>

						<?php /* Conteneur remplace en AJAX : la grille, ou le message quand
						         l'annee n'a aucune session au catalogue. */ ?>
						<div id="baremeContenu">
<?php if (!$sessionCourante && !$editeBase) { ?>
							<div class="bareme-vide">
								Aucune session au catalogue pour <?= e($anneeChoisie) ?>.
							</div>
<?php } else {
	require(__DIR__ . '/finance/bareme.grille.php');
} ?>
						</div>
					</div>
					
					<?php require('../init/footer.php'); ?>
				</div>


			</div>

		</div>

	</div>

<?php /* Le script vivait apres </html>, et se limitait a selectionner le
         contenu d'une case au clic. Il porte desormais tout le comportement
         de la page : enregistrement en AJAX, reprise du bareme de base, retour
         a la base, changement d'annee. Le systeme de toasts vient de
         init/footer.php, deja inclus plus haut. */ ?>
<script src="<?=$app_base?>/src/js/finance.bareme.js?v=20260916-2"></script>
</body>
</html>