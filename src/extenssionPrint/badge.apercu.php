<?php
/*
 * APERÇU DES BADGES (individuel et en groupe)
 * -------------------------------------------
 * Inclus par data.topdf.php pour ptype=Badge (après badge.php, qui fournit
 * l'étudiant) et ptype=BadgeGr. En groupe, la sélection (session, mention,
 * niveau, nouveaux / tous les inscrits, ou une liste de matricules) se fait
 * dans la page ; les étudiants sont chargés par src/api/badges.php et peuvent
 * être cochés / décochés un par un. Les filtres sont repris dans l'URL.
 *
 * L'aperçu affiche les vraies pages du PDF généré par js/badge-pdf.js
 * (pdf-lib), rendues avec pdf.js : ce qui est à l'écran est exactement ce
 * qui sera imprimé. Changer le format (6 ou 9 par page), le verso ou le
 * contour régénère l'aperçu ; « Télécharger » enregistre ce même PDF.
 */

$isGroup = ($ptype === 'BadgeGr');

if (!$isGroup) {
    $badgeStudents = [[
        'student_id'     => $student_id ?? '',
        'student_nom'    => $student_nom ?? '',
        'student_prenom' => $student_prenom ?? '',
        'etude_envisage' => $etude_envisage ?? '',
        'status'         => $status ?? '',
        'abonment'       => $abonment ?? '',
        'lookup_code'    => $lookup_code ?? '',
        'image_student'  => $image_student ?? '',
    ]];
    $badgeBack = false;
}
$badgeStudents = array_values($badgeStudents ?? []);

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$niveaux = ['1' => 'Licence 1', '2' => 'Licence 2', '3' => 'Licence 3', '4' => 'Master 1', '5' => 'Master 2'];

if ($isGroup) {
    $pageTitre = 'Badges en groupe';
    $contexte = [];

    $sessionsBadge = $dtb->query('SELECT session_id, session_name, session_year, session_semester, selected
                                  FROM t_2023_session
                                  ORDER BY session_year DESC, session_semester ASC, session_id DESC')->fetchAll(PDO::FETCH_ASSOC);
    // Effectifs par session (inscriptions non supprimées), affichés dans la liste
    $effectifs = [];
    foreach ($dtb->query('SELECT session_id, COUNT(DISTINCT student_id) AS inscrits,
                                 COUNT(DISTINCT CASE WHEN new_student = 1 THEN student_id END) AS nouveaux
                          FROM t_2024_inscription_session WHERE COALESCE(`remove`, 0) != 1
                          GROUP BY session_id')->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $effectifs[(string) $e['session_id']] = $e;
    }
    // Session de la dernière inscription d'un nouvel étudiant : le défaut le plus utile
    // (la session « en cours » des paramètres peut ne compter aucun nouvel inscrit)
    $derniere = $dtb->query('SELECT session_id FROM t_2024_inscription_session
                             WHERE new_student = 1 AND COALESCE(`remove`, 0) != 1
                             ORDER BY id DESC LIMIT 1')->fetchColumn();
    $mentionsBadge = $dtb->query('SELECT DISTINCT filiere_sigle, filiere_description FROM filiere
                                  ORDER BY filiere_description')->fetchAll(PDO::FETCH_ASSOC);

    // Filtres de départ : paramètres d'URL, sinon ancien formulaire (POST), sinon défauts
    $idsSessions = array_map(fn($s) => (string) $s['session_id'], $sessionsBadge);
    $initSession = (string) ($_GET['session'] ?? '');
    if (!in_array($initSession, $idsSessions, true)) {
        $initSession = '';
        $semPost = (string) ($_POST['semesterBadgeGr'] ?? ''); $anPost = (string) ($_POST['yearBadgeGr'] ?? '');
        foreach ($sessionsBadge as $s) {
            if ($semPost !== '' && (string) $s['session_semester'] === $semPost && (string) $s['session_year'] === $anPost) { $initSession = (string) $s['session_id']; break; }
        }
        if ($initSession === '' && $derniere !== false && in_array((string) $derniere, $idsSessions, true)) $initSession = (string) $derniere;
        if ($initSession === '') foreach ($sessionsBadge as $s) { if ((int) $s['selected'] === 1) { $initSession = (string) $s['session_id']; break; } }
        if ($initSession === '' && $sessionsBadge) $initSession = (string) $sessionsBadge[0]['session_id'];
    }
    $initMention = (string) ($_GET['mention'] ?? $_POST['types'] ?? '');
    $initNiveau = (string) ($_GET['niveau'] ?? $_POST['level'] ?? '');
    if (!isset($niveaux[$initNiveau])) $initNiveau = '';
    $initInscrits = (($_GET['inscrits'] ?? '') === 'tous') ? 'tous' : 'nouveaux';
    $initMatricules = trim((string) ($_GET['matricules'] ?? $_POST['student_id'] ?? ''));
    $badgeBack = isset($_GET['verso']) ? ($_GET['verso'] === '1') : (empty($_POST) || !empty($_POST['back']));
    $badgeStudents = [];
} else {
    $pageTitre = 'Badge étudiant';
    $contexte = array_filter([$student_id ?? '', trim(($student_nom ?? '') . ' ' . ($student_prenom ?? ''))]);
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
$badgeJsVersion = @filemtime(__DIR__ . '/../js/badge-pdf.js') ?: '1';
?>
<title><?= $h($ongletTitre ?? $pageTitre) ?></title>
<style>
	.ba, .ba * { box-sizing: border-box; }
	.ba {
		--surface: rgb(var(--c-surface)); --surface-2: rgb(var(--c-surface-2)); --raised: rgb(var(--c-raised));
		--canvas: rgb(var(--c-canvas)); --sunken: rgb(var(--c-sunken)); --line: rgb(var(--c-line)); --line-strong: rgb(var(--c-line-strong));
		--text: rgb(var(--c-text)); --text-2: rgb(var(--c-text-2)); --text-3: rgb(var(--c-text-3));
		--accent: rgb(var(--c-accent)); --accent-strong: rgb(var(--c-accent-strong)); --accent-text: rgb(var(--c-accent-text));
		--gold: rgb(var(--c-gold)); --gold-text: rgb(var(--c-gold-text)); --warning: rgb(var(--c-warning)); --danger: rgb(var(--c-danger));
		margin: 0; height: 100vh; display: flex; flex-direction: column; background: var(--canvas); color: var(--text);
		font-family: Inter, "Segoe UI", system-ui, -apple-system, Arial, sans-serif; font-size: 14px; line-height: 1.45;
	}
	.ba a { color: inherit; text-decoration: none; }
	.ba button { font: inherit; }

	/* Barre du haut */
	.ba-top { flex: none; display: flex; align-items: center; gap: 14px; padding: 10px 18px; background: rgb(var(--c-topbar)); border-bottom: 1px solid rgb(var(--c-side-line)); }
	.ba-brand { display: flex; align-items: center; gap: 8px; font-weight: 600; color: rgb(var(--c-side-text-hi)); white-space: nowrap; }
	.ba-brand img { width: 22px; height: 22px; }
	.ba-sep { width: 1px; height: 26px; background: rgb(var(--c-side-line)); }
	.ba-heading { min-width: 0; }
	.ba-heading h1 { margin: 0; font-size: 15px; font-weight: 600; color: rgb(var(--c-side-text-hi)); }
	.ba-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 3px; }
	.ba-chip { font-size: 12px; padding: 1px 8px; border-radius: 999px; background: rgb(var(--c-side-2)); color: rgb(var(--c-side-text)); white-space: nowrap; }
	.ba-top-right { margin-left: auto; display: flex; gap: 8px; }

	/* Boutons */
	.ba-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 9px; border: 1px solid var(--line-strong); background: var(--surface-2); color: var(--text); padding: 8px 14px; cursor: pointer; font-weight: 500; transition: background .15s, border-color .15s, opacity .15s; }
	.ba-btn:hover:not(:disabled) { background: var(--raised); }
	.ba-btn:focus-visible, .ba-format input:focus-visible + .ba-format-card, .ba-switch input:focus-visible + span { outline: 2px solid var(--accent-text); outline-offset: 2px; }
	.ba-btn:disabled { opacity: .5; cursor: not-allowed; }
	.ba-btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; padding: 11px 16px; }
	.ba-btn-primary:hover:not(:disabled) { background: var(--accent-strong); }
	.ba-btn-icon { padding: 6px 9px; }

	/* Corps */
	.ba-body { flex: 1; min-height: 0; display: flex; }
	.ba-panel { width: 380px; flex: none; overflow-y: auto; background: var(--surface); border-right: 1px solid var(--line); padding: 18px; display: flex; flex-direction: column; gap: 22px; }
	.ba-section h2 { margin: 0 0 10px; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--text-3); }

	/* Résumé */
	.ba-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
	.ba-stat { background: var(--surface-2); border: 1px solid var(--line); border-radius: 10px; padding: 10px 8px; text-align: center; }
	.ba-stat b { display: block; font-size: 22px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
	.ba-stat span { font-size: 12px; color: var(--text-3); }
	.ba-stat.is-warn b { color: var(--warning); }

	/* Format */
	.ba-formats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
	/* Le label occupe toute la hauteur de la ligne de grille et la carte la remplit :
	   les deux cartes ont la même hauteur, même si l'une a une ligne de plus. */
	.ba-format { display: flex; }
	.ba-format input { position: absolute; opacity: 0; pointer-events: none; }
	.ba-format-card { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 12px 8px 10px; border-radius: 12px; border: 1.5px solid var(--line-strong); background: var(--surface-2); cursor: pointer; transition: border-color .15s, background .15s; text-align: center; }
	.ba-format-card:hover { border-color: var(--accent); }
	.ba-format input:checked + .ba-format-card { border-color: var(--accent); background: rgb(var(--c-accent) / .14); box-shadow: 0 0 0 3px rgb(var(--c-accent) / .18); }
	.ba-sheet { width: 54px; height: 76px; background: #fff; border-radius: 2px; position: relative; box-shadow: 0 2px 6px rgb(0 0 0 / .35); }
	.ba-sheet i { position: absolute; background: #2b3a86; border-radius: 1px; }
	.ba-format-card strong { font-size: 14px; }
	.ba-format-card small { font-size: 12px; color: var(--text-3); line-height: 1.3; }

	/* Interrupteurs */
	.ba-switch { display: flex; align-items: center; gap: 12px; padding: 10px 0; cursor: pointer; }
	.ba-switch + .ba-switch { border-top: 1px solid var(--line); }
	.ba-switch input { position: absolute; opacity: 0; pointer-events: none; }
	.ba-switch span { flex: none; width: 38px; height: 22px; border-radius: 999px; background: var(--line-strong); position: relative; transition: background .15s; }
	.ba-switch span::after { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; }
	.ba-switch input:checked + span { background: var(--accent); }
	.ba-switch input:checked + span::after { transform: translateX(16px); }
	.ba-switch div { min-width: 0; }
	.ba-switch div b { display: block; font-weight: 500; }
	.ba-switch div small { color: var(--text-3); font-size: 12px; }

	.ba-actions { display: flex; flex-direction: column; gap: 8px; }
	.ba-tip { display: flex; gap: 8px; font-size: 12px; color: var(--text-2); background: rgb(var(--c-gold) / .12); border: 1px solid rgb(var(--c-gold) / .35); border-radius: 10px; padding: 9px 11px; }
	.ba-tip i { color: var(--gold-text); }

	/* Sans photo */
	.ba-missing summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px; font-weight: 500; }
	.ba-missing summary::-webkit-details-marker { display: none; }
	.ba-missing summary .bi-chevron-down { margin-left: auto; transition: transform .15s; color: var(--text-3); }
	.ba-missing[open] summary .bi-chevron-down { transform: rotate(180deg); }
	.ba-missing ul { margin: 10px 0 0; padding: 0; list-style: none; max-height: 220px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px; }
	.ba-missing li { display: flex; gap: 10px; padding: 6px 10px; font-size: 13px; }
	.ba-missing li + li { border-top: 1px solid var(--line); }
	.ba-missing li code { font-family: inherit; color: var(--gold-text); font-variant-numeric: tabular-nums; }
	.ba-missing li em { margin-left: auto; font-style: normal; font-size: 12px; color: var(--danger); }

	/* Aperçu */
	.ba-preview { flex: 1; min-width: 0; display: flex; flex-direction: column; background: var(--sunken); }
	.ba-pbar { flex: none; display: flex; align-items: center; gap: 10px; padding: 10px 18px; border-bottom: 1px solid var(--line); background: var(--canvas); }
	.ba-pbar-title { font-weight: 600; }
	.ba-pbar-title small { font-weight: 400; color: var(--text-3); margin-left: 6px; }
	.ba-zoom { margin-left: auto; display: flex; align-items: center; gap: 4px; }
	.ba-zoom output { min-width: 52px; text-align: center; font-variant-numeric: tabular-nums; color: var(--text-2); }
	.ba-progress { height: 3px; background: transparent; overflow: hidden; flex: none; }
	.ba-progress div { height: 100%; width: 0; background: var(--accent); transition: width .2s; }
	.ba-pages { flex: 1; overflow: auto; padding: 28px 24px 60px; display: flex; flex-direction: column; align-items: center; gap: 28px; position: relative; }
	.ba-page { margin: 0; display: flex; flex-direction: column; align-items: center; gap: 8px; }
	.ba-page-sheet { background: #fff; box-shadow: 0 10px 30px rgb(0 0 0 / .45); position: relative; }
	.ba-page-sheet canvas { display: block; width: 100%; height: 100%; }
	.ba-sheet-loading { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #64748b; font-size: 13px; }
	.ba-sheet-loading .ba-spin { width: 22px; height: 22px; border-color: #cbd5e1; border-top-color: #4658c2; }
	.ba-page figcaption { font-size: 12px; color: var(--text-3); display: flex; gap: 8px; align-items: center; }
	.ba-page figcaption b { color: var(--text-2); font-weight: 600; }
	.ba-tag { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--line-strong); }
	.ba-tag.is-back { color: var(--gold-text); border-color: rgb(var(--c-gold) / .5); }
	.ba-busy { position: sticky; top: 0; z-index: 2; align-self: center; display: flex; align-items: center; gap: 10px; background: var(--surface); border: 1px solid var(--line-strong); border-radius: 999px; padding: 7px 14px; font-size: 13px; color: var(--text-2); box-shadow: 0 6px 20px rgb(0 0 0 / .35); }
	.ba-spin { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--line-strong); border-top-color: var(--accent-text); animation: ba-spin .8s linear infinite; }
	@keyframes ba-spin { to { transform: rotate(360deg); } }
	.ba-empty { margin: auto; max-width: 380px; text-align: center; color: var(--text-2); }
	.ba-empty i { font-size: 40px; color: var(--text-3); }
	.ba-empty h3 { margin: 10px 0 6px; font-size: 16px; color: var(--text); }
	.ba-error { color: var(--danger); }
	[hidden] { display: none !important; }

	/* Sélection (badges en groupe) */
	.ba-fields { display: grid; gap: 12px; }
	.ba-fields.is-off .ba-field-filter { opacity: .45; pointer-events: none; }
	.ba-field { display: grid; gap: 5px; min-width: 0; }
	.ba-field > span { font-size: 12px; font-weight: 500; color: var(--text-2); }
	.ba-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
	.ba .ba-select, .ba .ba-input { width: 100%; min-height: 38px; border-radius: 9px; border: 1px solid var(--line-strong); background-color: rgb(var(--c-inset)); color: var(--text); padding: 7px 11px; font-size: 14px; box-shadow: none; }
	.ba .ba-select { padding-right: 32px; }
	.ba .ba-select:focus, .ba .ba-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgb(var(--c-accent) / .25); }
	.ba .ba-select option { background: rgb(var(--c-surface)); color: var(--text); }
	.ba textarea.ba-input { resize: vertical; min-height: 38px; font-variant-numeric: tabular-nums; }
	.ba-input::placeholder { color: var(--text-3); }
	.ba-seg { display: grid; grid-template-columns: 1fr 1fr; padding: 3px; border-radius: 10px; background: rgb(var(--c-inset)); border: 1px solid var(--line-strong); }
	.ba-seg label { position: relative; }
	.ba-seg input { position: absolute; opacity: 0; pointer-events: none; }
	.ba-seg span { display: block; text-align: center; padding: 6px 8px; border-radius: 7px; font-size: 13px; color: var(--text-2); cursor: pointer; transition: background .15s, color .15s; }
	.ba-seg input:checked + span { background: var(--accent); color: #fff; font-weight: 600; }
	.ba-seg input:focus-visible + span { outline: 2px solid var(--accent-text); outline-offset: 1px; }
	.ba-note { font-size: 12px; color: var(--text-3); }
	.ba-note.is-warn { color: var(--warning); }
	.ba-divider { display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--text-3); }
	.ba-divider::before, .ba-divider::after { content: ""; flex: 1; height: 1px; background: var(--line); }

	/* Liste des étudiants */
	.ba-list-tools { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
	.ba-list-tools input[type=search] { grid-column: 1 / -1; }
	.ba-list-tools .ba-select { min-height: 34px; padding: 5px 28px 5px 9px; font-size: 13px; }
	.ba-list-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 13px; color: var(--text-2); }
	.ba-list-head .ba-link { margin-left: auto; }
	.ba-link { background: none; border: 0; padding: 0; color: var(--accent-text); cursor: pointer; font-size: 13px; }
	.ba-link:hover { text-decoration: underline; }
	.ba-list { max-height: 300px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px; background: rgb(var(--c-inset)); }
	.ba-stu { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; padding: 7px 10px; cursor: pointer; }
	.ba-stu + .ba-stu { border-top: 1px solid var(--line); }
	.ba-stu:hover { background: var(--surface-2); }
	.ba-stu.is-off { opacity: .5; }
	.ba-stu-main { min-width: 0; }
	.ba-stu-name { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; }
	.ba-stu-meta { display: block; font-size: 11px; color: var(--text-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.ba-stu-meta code { font-family: inherit; color: var(--gold-text); }
	.ba-stu-photo { font-size: 15px; color: rgb(var(--c-success)); }
	.ba-stu-photo.is-none { color: var(--warning); }
	.ba .ba-check { width: 17px; height: 17px; margin: 0; border-radius: 5px; border: 1.5px solid var(--line-strong); background-color: var(--surface); color: var(--accent); accent-color: var(--accent); cursor: pointer; }
	.ba .ba-check:focus { box-shadow: 0 0 0 3px rgb(var(--c-accent) / .25); outline: none; }
	.ba-list-empty { padding: 18px 12px; text-align: center; font-size: 13px; color: var(--text-3); }

	@media (max-width: 860px) {
		.ba { height: auto; min-height: 100vh; }
		.ba-body { flex-direction: column; }
		.ba-panel { width: 100%; border-right: 0; border-bottom: 1px solid var(--line); overflow: visible; }
		.ba-pages { padding: 18px 12px 40px; overflow: visible; }
		.ba-top { flex-wrap: wrap; padding: 10px 16px; }
		.ba-sep { display: none; }
		.ba-top-right { width: 100%; margin-left: 0; }
	}
	@media (prefers-reduced-motion: reduce) { .ba * { transition: none !important; animation: none !important; } }
</style>

<body class="ba">
	<header class="ba-top">
		<a class="ba-brand" href="<?= $h($app_base) ?>/tableau-de-bord" title="Retour au tableau de bord">
			<img src="<?= $h($app_base) ?>/file/logo-coldbloud.png" alt=""> Infinit Registrar
		</a>
		<span class="ba-sep" aria-hidden="true"></span>
		<div class="ba-heading">
			<h1><?= $h($pageTitre) ?></h1>
			<div class="ba-chips"><?php foreach ($contexte as $c) { ?><span class="ba-chip"><?= $h($c) ?></span><?php } ?></div>
		</div>
		<div class="ba-top-right">
			<a class="ba-btn" id="btnBack" href="<?= $h($app_base) ?>/etudiants"><i class="bi-arrow-left"></i> Retour</a>
		</div>
	</header>

	<div class="ba-body">
		<aside class="ba-panel" aria-label="Options d'impression">
			<section class="ba-section">
				<h2>Résumé</h2>
				<div class="ba-stats">
					<div class="ba-stat"><b id="stBadges"><?= count($badgeStudents) ?></b><span id="stBadgesLabel">badge<?= count($badgeStudents) > 1 ? 's' : '' ?></span></div>
					<div class="ba-stat"><b id="stPages">–</b><span>pages A4</span></div>
					<div class="ba-stat" id="stMissingBox"><b id="stMissing">–</b><span>sans photo</span></div>
				</div>
			</section>

			<?php if ($isGroup) { ?>
			<section class="ba-section" aria-labelledby="selLabel">
				<h2 id="selLabel">Sélection des étudiants</h2>
				<div class="ba-fields" id="filters">
					<label class="ba-field ba-field-filter"><span>Session</span>
						<select class="ba-select" id="fSession">
							<?php $anneePrec = null;
							foreach ($sessionsBadge as $s) {
								if ($s['session_year'] !== $anneePrec) { if ($anneePrec !== null) echo '</optgroup>'; echo '<optgroup label="' . $h($s['session_year']) . '">'; $anneePrec = $s['session_year']; }
								$ef = $effectifs[(string) $s['session_id']] ?? null;
								$compte = $ef ? ' — ' . (int) $ef['nouveaux'] . ' nouveau' . ((int) $ef['nouveaux'] > 1 ? 'x' : '') . ' / ' . (int) $ef['inscrits'] . ' inscrit' . ((int) $ef['inscrits'] > 1 ? 's' : '') : ' — aucun inscrit'; ?>
							<option value="<?= $h($s['session_id']) ?>" data-label="<?= $h(trim($s['session_year'] . ' · ' . $s['session_name'])) ?>" <?= (string) $s['session_id'] === $initSession ? 'selected' : '' ?>><?= $h($s['session_name']) ?><?= (int) $s['selected'] === 1 ? ' (en cours)' : '' ?><?= $h($compte) ?></option>
							<?php } if ($anneePrec !== null) echo '</optgroup>'; ?>
						</select>
					</label>
					<div class="ba-row2 ba-field-filter">
						<label class="ba-field"><span>Mention</span>
							<select class="ba-select" id="fMention">
								<option value="">Toutes</option>
								<?php foreach ($mentionsBadge as $m) { ?>
								<option value="<?= $h($m['filiere_sigle']) ?>" <?= (string) $m['filiere_sigle'] === $initMention ? 'selected' : '' ?>><?= $h($m['filiere_description']) ?></option>
								<?php } ?>
							</select>
						</label>
						<label class="ba-field"><span>Niveau</span>
							<select class="ba-select" id="fNiveau">
								<option value="">Tous</option>
								<?php foreach ($niveaux as $k => $v) { ?>
								<option value="<?= $h($k) ?>" <?= (string) $k === $initNiveau ? 'selected' : '' ?>><?= $h($v) ?></option>
								<?php } ?>
							</select>
						</label>
					</div>
					<div class="ba-field ba-field-filter"><span id="inscLabel">Inscrits</span>
						<div class="ba-seg" role="radiogroup" aria-labelledby="inscLabel">
							<label><input type="radio" name="fInscrits" value="nouveaux" <?= $initInscrits === 'nouveaux' ? 'checked' : '' ?>><span>Nouveaux inscrits</span></label>
							<label><input type="radio" name="fInscrits" value="tous" <?= $initInscrits === 'tous' ? 'checked' : '' ?>><span>Tous les inscrits</span></label>
						</div>
					</div>
					<div class="ba-divider">ou</div>
					<label class="ba-field"><span>Matricules précis</span>
						<textarea class="ba-input" id="fMatricules" rows="1" placeholder="Ex. 30288 30291, 30292" spellcheck="false"><?= $h($initMatricules) ?></textarea>
						<small class="ba-note" id="matNote">Un ou plusieurs matricules, séparés par des espaces ou des virgules. Remplace les filtres ci-dessus.</small>
					</label>
				</div>
			</section>

			<section class="ba-section" aria-labelledby="listLabel">
				<h2 id="listLabel">Étudiants</h2>
				<div class="ba-list-tools">
					<input type="search" class="ba-input" id="lSearch" placeholder="Rechercher un nom ou un matricule" aria-label="Rechercher dans la liste">
					<select class="ba-select" id="lPhoto" aria-label="Filtrer par photo" title="Photo">
						<option value="">Photo</option><option value="avec">Avec photo</option><option value="sans">Sans photo</option>
					</select>
					<select class="ba-select" id="lSort" aria-label="Ordre des badges" title="Ordre des badges">
						<option value="nom">A → Z</option><option value="matricule">Matricule</option>
					</select>
				</div>
				<div class="ba-list-head">
					<span id="listCount">Chargement…</span>
					<button type="button" class="ba-link" id="selAll">Tout cocher</button>
					<button type="button" class="ba-link" id="selNone">Tout décocher</button>
				</div>
				<div class="ba-list" id="stuList" role="group" aria-labelledby="listLabel"></div>
			</section>
			<?php } ?>

			<section class="ba-section">
				<h2 id="fmtLabel">Badges par page</h2>
				<div class="ba-formats" role="radiogroup" aria-labelledby="fmtLabel">
					<label class="ba-format"><input type="radio" name="layout" value="6" checked>
						<span class="ba-format-card"><span class="ba-sheet" data-layout="6" aria-hidden="true"></span><strong>6 par page</strong><small>2 × 3 · 75 × 85 mm</small></span>
					</label>
					<label class="ba-format"><input type="radio" name="layout" value="9">
						<span class="ba-format-card"><span class="ba-sheet" data-layout="9" aria-hidden="true"></span><strong>9 par page</strong><small>3 × 3 · 54 × 85,6 mm<br>format carte</small></span>
					</label>
				</div>
			</section>

			<section class="ba-section">
				<h2>Options</h2>
				<label class="ba-switch"><input type="checkbox" id="optBack" <?= !empty($badgeBack) ? 'checked' : '' ?>><span></span>
					<div><b>Verso</b><small>Une planche de verso en première page</small></div></label>
				<label class="ba-switch"><input type="checkbox" id="optBorder" checked><span></span>
					<div><b>Contour de découpe</b><small>Pointillés gris autour de chaque badge</small></div></label>
			</section>

			<section class="ba-section ba-actions">
				<button type="button" class="ba-btn ba-btn-primary" id="btnDownload" disabled><i class="bi-download"></i> Télécharger le PDF</button>
				<button type="button" class="ba-btn" id="btnOpen" disabled><i class="bi-printer"></i> Ouvrir pour imprimer</button>
				<div class="ba-tip"><i class="bi-info-circle"></i><span>Imprimez en <b>taille réelle (100 %)</b>, sans « ajuster à la page », pour garder les dimensions des badges.</span></div>
			</section>

			<section class="ba-section" id="missingSection" hidden>
				<details class="ba-missing">
					<summary><i class="bi-person-bounding-box" style="color:var(--warning)"></i> <span id="missingTitle"></span><i class="bi-chevron-down"></i></summary>
					<ul id="missingList"></ul>
				</details>
			</section>
		</aside>

		<main class="ba-preview">
			<div class="ba-pbar">
				<span class="ba-pbar-title">Aperçu <small id="pbarInfo"></small></span>
				<div class="ba-zoom" aria-label="Zoom">
					<button type="button" class="ba-btn ba-btn-icon" id="zoomOut" title="Réduire"><i class="bi-dash-lg"></i></button>
					<output id="zoomVal">100 %</output>
					<button type="button" class="ba-btn ba-btn-icon" id="zoomIn" title="Agrandir"><i class="bi-plus-lg"></i></button>
					<button type="button" class="ba-btn ba-btn-icon" id="zoomFit" title="Ajuster à la largeur"><i class="bi-arrows-angle-expand"></i></button>
				</div>
			</div>
			<div class="ba-progress" aria-hidden="true"><div id="progressBar"></div></div>
			<div class="ba-pages" id="pages" aria-live="polite">
				<div class="ba-busy" id="busy"><span class="ba-spin"></span><span id="busyText">Préparation de l'aperçu…</span></div>
			</div>
		</main>
	</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="<?= $h($app_base) ?>/src/js/badge-pdf.js?v=<?= $h($badgeJsVersion) ?>"></script>
<script>
(function () {
	'use strict';
	const IS_GROUP = <?= $isGroup ? 'true' : 'false' ?>;
	const APP_BASE = <?= json_encode((string) $app_base, $jsonFlags) ?>;
	let all = <?= json_encode($badgeStudents, $jsonFlags) ?>; // étudiants chargés
	const selected = new Set(all.map((s) => String(s.student_id))); // cochés
	let FILE_BASE = <?= json_encode((string) ($printName ?? 'BADGES'), $jsonFlags) ?>;
	const PHONE = '034 38 180 81 / 034 76 076 98';
	const PREF_KEY = 'badges.apercu.layout';

	pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

	const $ = (id) => document.getElementById(id);
	const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const pagesEl = $('pages');

	/* ---------- mini-feuilles A4 des cartes de format (proportions réelles) ---------- */
	document.querySelectorAll('.ba-sheet').forEach((sheet) => {
		const L = BadgePDF.LAYOUTS[sheet.dataset.layout], k = 54 / 595.28;
		const ox = (595.28 - (L.cols * L.w + (L.cols - 1) * L.gx)) / 2, oy = (841.89 - (L.rows * L.h + (L.rows - 1) * L.gy)) / 2;
		for (let r = 0; r < L.rows; r++) for (let c = 0; c < L.cols; c++) {
			const i = document.createElement('i');
			i.style.cssText = `left:${(ox + c * (L.w + L.gx)) * k}px;top:${(oy + r * (L.h + L.gy)) * k}px;width:${L.w * k}px;height:${L.h * k}px`;
			sheet.appendChild(i);
		}
	});

	/* ---------- préférences ---------- */
	try { const saved = localStorage.getItem(PREF_KEY); if (saved && BadgePDF.LAYOUTS[saved]) document.querySelector(`input[name=layout][value="${saved}"]`).checked = true; } catch (e) { }
	const opts = () => ({ layout: document.querySelector('input[name=layout]:checked').value, back: $('optBack').checked, border: $('optBorder').checked });

	/* ---------- sans photo ---------- */
	function renderMissing(students, unreadable) {
		const missing = students.filter((s) => !BadgePDF.hasPhoto(s)).map((s) => ({ s, why: 'aucune photo' }));
		const bad = students.filter((s) => unreadable.has(String(s.student_id))).map((s) => ({ s, why: 'photo illisible' }));
		const all = missing.concat(bad);
		$('stMissing').textContent = all.length;
		$('stMissingBox').classList.toggle('is-warn', all.length > 0);
		$('missingSection').hidden = all.length === 0;
		$('missingTitle').textContent = `${all.length} badge${all.length > 1 ? 's' : ''} sans photo`;
		$('missingList').innerHTML = all.map(({ s, why }) => `<li><code>${esc(s.student_id)}</code><span>${esc(String(s.student_nom || '').toUpperCase())} ${esc(s.student_prenom)}</span><em>${why}</em></li>`).join('');
	}

	/* ---------- zoom ---------- */
	let zoom = 1, fitMode = true;
	const PAGE_W = 595.28, PAGE_H = 841.89;
	// « Ajuster » : une page A4 entière visible (largeur et hauteur), pour voir la
	// planche complète d'un coup ; sur mobile (défilement de page), la largeur seule.
	function fitZoom() {
		const pw = PAGE_W * 96 / 72, ph = PAGE_H * 96 / 72;
		const byW = (pagesEl.clientWidth - 48) / pw;
		const mobile = window.matchMedia('(max-width: 860px)').matches;
		const byH = mobile ? Infinity : (pagesEl.clientHeight - 28 - 44) / ph;
		return Math.max(0.3, Math.min(1.5, byW, byH));
	}
	function applyZoom() {
		if (fitMode) zoom = fitZoom();
		$('zoomVal').textContent = Math.round(zoom * 100) + ' %';
		const w = PAGE_W * 96 / 72 * zoom, hgt = PAGE_H * 96 / 72 * zoom;
		pagesEl.querySelectorAll('.ba-page-sheet').forEach((s) => { s.style.width = w + 'px'; s.style.height = hgt + 'px'; });
		observeAll();
	}
	$('zoomIn').onclick = () => { fitMode = false; zoom = Math.min(3, zoom * 1.2); applyZoom(); };
	$('zoomOut').onclick = () => { fitMode = false; zoom = Math.max(0.3, zoom / 1.2); applyZoom(); };
	$('zoomFit').onclick = () => { fitMode = true; applyZoom(); };
	let resizeT; window.addEventListener('resize', () => { clearTimeout(resizeT); resizeT = setTimeout(() => fitMode && applyZoom(), 150); });

	/* ---------- rendu paresseux des pages (pdf.js) ---------- */
	let pdfDoc = null;
	const io = new IntersectionObserver((entries) => entries.forEach((e) => { if (e.isIntersecting) renderPage(e.target); }), { root: pagesEl, rootMargin: '600px 0px' });
	function observeAll() { pagesEl.querySelectorAll('.ba-page-sheet').forEach((s) => { io.unobserve(s); io.observe(s); }); }
	// Chaque feuille garde le zoom de son dernier rendu (_done) et celui voulu
	// (_want) ; un seul rendu à la fois par feuille, relancé tant qu'ils diffèrent.
	async function renderPage(sheet) {
		if (!pdfDoc) return;
		sheet._want = zoom;
		if (sheet._busy || sheet._done === sheet._want) return;
		sheet._busy = true;
		try {
			while (sheet._done !== sheet._want && sheet.isConnected) {
				const z = sheet._want, doc = pdfDoc;
				const page = await doc.getPage(+sheet.dataset.page);
				const dpr = Math.min(window.devicePixelRatio || 1, 2);
				const vp = page.getViewport({ scale: z * 96 / 72 * dpr });
				const c = document.createElement('canvas'); c.width = Math.round(vp.width); c.height = Math.round(vp.height);
				await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
				if (doc !== pdfDoc) break;
				sheet.replaceChildren(c); sheet._done = z;
			}
		} catch (e) {
			console.error(e);
		} finally {
			sheet._busy = false;
		}
	}

	/* ---------- génération ---------- */
	let current = null, token = 0;
	const busy = (on, text) => { $('busy').hidden = !on; if (text) $('busyText').textContent = text; };
	const setProgress = (p) => { $('progressBar').style.width = (p * 100) + '%'; };

	// Badges à imprimer : les étudiants cochés, dans l'ordre choisi
	function badgeList() {
		const sort = IS_GROUP ? $('lSort').value : 'nom';
		const out = all.filter((s) => selected.has(String(s.student_id)));
		if (sort === 'matricule') out.sort((a, b) => String(a.student_id).localeCompare(String(b.student_id), 'fr', { numeric: true }));
		else if (IS_GROUP && !matriculesMode()) out.sort((a, b) => `${a.student_nom} ${a.student_prenom}`.localeCompare(`${b.student_nom} ${b.student_prenom}`, 'fr', { sensitivity: 'base' }));
		return out;
	}

	function showEmpty(icon, title, text) {
		token++; pdfDoc = null; current = null;
		busy(false); setProgress(0);
		pagesEl.querySelectorAll('.ba-page').forEach((p) => p.remove());
		pagesEl.insertAdjacentHTML('beforeend', `<div class="ba-empty ba-page"><i class="bi-${icon}"></i><h3>${esc(title)}</h3><p>${esc(text)}</p></div>`);
		$('btnDownload').disabled = $('btnOpen').disabled = true;
		$('stBadges').textContent = '0'; $('stBadgesLabel').textContent = 'badge'; $('stPages').textContent = '0'; $('stMissing').textContent = '0';
		$('stMissingBox').classList.remove('is-warn'); $('missingSection').hidden = true;
		$('pbarInfo').textContent = '';
	}

	async function build() {
		const students = badgeList();
		if (!students.length) {
			if (!all.length) showEmpty('people', 'Aucun étudiant trouvé', IS_GROUP ? 'Aucun étudiant ne correspond à cette sélection. Changez la session, la mention, le niveau ou choisissez « Tous les inscrits ».' : 'Aucune donnée d\'étudiant.');
			else showEmpty('check2-square', 'Aucun badge sélectionné', 'Cochez au moins un étudiant dans la liste pour afficher l\'aperçu.');
			return;
		}
		const my = ++token, o = opts();
		try { localStorage.setItem(PREF_KEY, o.layout); } catch (e) { }
		$('btnDownload').disabled = $('btnOpen').disabled = true;
		$('stBadges').textContent = students.length; $('stBadgesLabel').textContent = students.length > 1 ? 'badges' : 'badge';
		busy(true, 'Génération de l\'aperçu…'); setProgress(0.05);
		try {
			const r = await BadgePDF.generate(students, { ...o, phone: PHONE, onProgress: (d, t) => { if (my === token) setProgress(Math.max(0.05, d / (t || 1)) * 0.85); } });
			if (my !== token) return;
			const doc = await pdfjsLib.getDocument({ data: r.bytes.slice() }).promise;
			if (my !== token) return;
			if (current && current.url) URL.revokeObjectURL(current.url);
			current = { ...r, opts: o, url: URL.createObjectURL(new Blob([r.bytes], { type: 'application/pdf' })) };
			pdfDoc = doc;
			buildPages(r, o, students.length);
			const unreadable = new Set(r.warnings.map((w) => (w.match(/^(.+?) : photo illisible/) || [])[1]).filter(Boolean));
			renderMissing(students, unreadable);
			$('stPages').textContent = r.pages;
			const per = r.layout.cols * r.layout.rows;
			$('pbarInfo').textContent = `${r.pages} page${r.pages > 1 ? 's' : ''} · ${per} badges par page${o.back ? ' · verso inclus' : ''}`;
			$('btnDownload').disabled = $('btnOpen').disabled = false;
			setProgress(1); setTimeout(() => my === token && setProgress(0), 400);
			busy(false);
		} catch (e) {
			console.error(e);
			if (my !== token) return;
			busy(false); setProgress(0);
			pagesEl.querySelectorAll('.ba-page').forEach((p) => p.remove());
			pagesEl.insertAdjacentHTML('beforeend', `<div class="ba-empty ba-page"><i class="bi-exclamation-triangle ba-error"></i><h3>L'aperçu n'a pas pu être généré</h3><p>${esc(e.message || e)}</p><button type="button" class="ba-btn" onclick="location.reload()"><i class="bi-arrow-clockwise"></i> Réessayer</button></div>`);
		}
	}

	function buildPages(r, o, n) {
		pagesEl.querySelectorAll('.ba-page').forEach((p) => p.remove());
		const per = r.layout.cols * r.layout.rows;
		const frag = document.createDocumentFragment();
		for (let p = 1; p <= r.pages; p++) {
			const isBack = o.back && p === 1, idx = p - (o.back ? 2 : 1);
			const from = idx * per + 1, to = Math.min(n, (idx + 1) * per);
			const fig = document.createElement('figure'); fig.className = 'ba-page';
			fig.innerHTML = `<div class="ba-page-sheet" data-page="${p}"><div class="ba-sheet-loading"><span class="ba-spin"></span>Rendu de la page…</div></div><figcaption><b>Page ${p}/${r.pages}</b>${isBack ? '<span class="ba-tag is-back">Verso</span>' : `<span class="ba-tag">Recto</span> ${from === to ? 'badge ' + from : 'badges ' + from + ' à ' + to}`}</figcaption>`;
			frag.appendChild(fig);
		}
		pagesEl.appendChild(frag);
		applyZoom();
	}

	/* ---------- actions ---------- */
	$('btnDownload').onclick = () => {
		if (!current) return;
		const a = document.createElement('a'); a.href = current.url;
		a.download = `${FILE_BASE}_${current.layout.name}${current.opts.back ? '_verso' : ''}_${BadgePDF.stamp()}.pdf`;
		document.body.appendChild(a); a.click(); a.remove();
	};
	$('btnOpen').onclick = () => { if (current) window.open(current.url, '_blank'); };

	let buildT;
	const rebuild = () => { clearTimeout(buildT); buildT = setTimeout(build, 120); };
	document.querySelectorAll('input[name=layout]').forEach((i) => i.addEventListener('change', rebuild));
	$('optBack').addEventListener('change', rebuild);
	$('optBorder').addEventListener('change', rebuild);

	/* ---------- retour : page précédente de l'application, sinon liste des étudiants ---------- */
	$('btnBack').addEventListener('click', (e) => {
		try {
			if (history.length > 1 && document.referrer && new URL(document.referrer).origin === location.origin) { e.preventDefault(); history.back(); }
		} catch (err) { }
	});

	/* ====================================================================
	 *  BADGES EN GROUPE : filtres, chargement, liste à cocher
	 * ==================================================================== */
	function matriculesMode() { return IS_GROUP && $('fMatricules').value.trim() !== ''; }
	const NIVEAUX = { 1: 'L1', 2: 'L2', 3: 'L3', 4: 'M1', 5: 'M2' };
	let loadToken = 0, rebuildListT;
	const rebuildSoon = (ms = 450) => { clearTimeout(rebuildListT); rebuildListT = setTimeout(build, ms); };

	function filterState() {
		return {
			session: $('fSession').value, mention: $('fMention').value, niveau: $('fNiveau').value,
			inscrits: document.querySelector('input[name=fInscrits]:checked').value,
			matricules: $('fMatricules').value.trim()
		};
	}

	// Les filtres sont repris dans l'URL : recharger ou partager la page garde la sélection
	function syncUrl(f) {
		const u = new URL(location.href);
		['session', 'mention', 'niveau', 'inscrits', 'matricules'].forEach((k) => u.searchParams.delete(k));
		u.searchParams.set('ptype', 'BadgeGr');
		if (f.matricules) u.searchParams.set('matricules', f.matricules);
		else {
			u.searchParams.set('session', f.session);
			if (f.mention) u.searchParams.set('mention', f.mention);
			if (f.niveau) u.searchParams.set('niveau', f.niveau);
			if (f.inscrits === 'tous') u.searchParams.set('inscrits', 'tous');
		}
		history.replaceState(null, '', u);
	}

	function updateContext(f) {
		const chips = [];
		if (f.matricules) chips.push(`${f.matricules.split(/[\s,;]+/).filter(Boolean).length} matricule(s)`);
		else {
			chips.push($('fSession').selectedOptions[0]?.dataset.label || '');
			chips.push(f.mention ? $('fMention').selectedOptions[0].text : 'Toutes mentions');
			chips.push(f.niveau ? $('fNiveau').selectedOptions[0].text : 'Tous niveaux');
			chips.push(f.inscrits === 'tous' ? 'Tous les inscrits' : 'Nouveaux inscrits');
		}
		document.querySelector('.ba-chips').innerHTML = chips.filter(Boolean).map((c) => `<span class="ba-chip">${esc(c)}</span>`).join('');
		const parts = f.matricules ? ['MATRICULES'] : [f.mention || 'TOUTES', f.niveau ? (NIVEAUX[f.niveau] || f.niveau) : 'TOUT'];
		FILE_BASE = 'BADGES_' + parts.join('_');
	}

	async function loadStudents() {
		const f = filterState(), my = ++loadToken;
		$('filters').classList.toggle('is-off', !!f.matricules);
		syncUrl(f); updateContext(f);
		$('listCount').textContent = 'Chargement…';
		$('stuList').innerHTML = '<div class="ba-list-empty"><span class="ba-spin" style="display:inline-block;vertical-align:middle"></span> Chargement des étudiants…</div>';
		busy(true, 'Chargement des étudiants…');
		const q = new URLSearchParams(f.matricules ? { matricules: f.matricules } : { session: f.session, mention: f.mention, niveau: f.niveau, inscrits: f.inscrits });
		try {
			const res = await fetch(`${APP_BASE}/src/api/badges.php?${q}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json().catch(() => ({}));
			if (my !== loadToken) return;
			if (!res.ok || !data.success) {
				if (data.session_expiree && data.login_url) { location.href = data.login_url; return; }
				throw new Error(data.message || ('Erreur ' + res.status));
			}
			all = data.etudiants || [];
			selected.clear(); all.forEach((s) => selected.add(String(s.student_id)));
			const nf = data.introuvables || [];
			$('matNote').textContent = nf.length ? `Introuvable${nf.length > 1 ? 's' : ''} : ${nf.join(', ')}` : 'Un ou plusieurs matricules, séparés par des espaces ou des virgules. Remplace les filtres ci-dessus.';
			$('matNote').classList.toggle('is-warn', nf.length > 0);
			renderList();
			build();
		} catch (e) {
			if (my !== loadToken) return;
			console.error(e);
			all = []; selected.clear(); renderList();
			$('listCount').textContent = 'Erreur de chargement';
			showEmpty('exclamation-triangle', 'Les étudiants n\'ont pas pu être chargés', e.message || String(e));
		}
	}

	function visibleStudents() {
		const q = $('lSearch').value.trim().toLowerCase(), ph = $('lPhoto').value;
		return all.filter((s) => (!q || `${s.student_id} ${s.student_nom} ${s.student_prenom}`.toLowerCase().includes(q))
			&& (!ph || (ph === 'avec') === BadgePDF.hasPhoto(s)));
	}

	function renderList() {
		const vis = visibleStudents();
		$('stuList').innerHTML = vis.length ? vis.map((s) => {
			const id = String(s.student_id), on = selected.has(id), photo = BadgePDF.hasPhoto(s);
			const niv = NIVEAUX[s.niveau] ? ' · ' + NIVEAUX[s.niveau] : '';
			return `<label class="ba-stu${on ? '' : ' is-off'}">
				<input type="checkbox" class="ba-check" data-id="${esc(id)}" ${on ? 'checked' : ''}>
				<span class="ba-stu-main"><span class="ba-stu-name">${esc(String(s.student_nom || '').toUpperCase())} ${esc(s.student_prenom)}</span>
				<span class="ba-stu-meta"><code>${esc(id)}</code> · ${esc(s.etude_envisage)}${niv}${s.status ? ' · ' + esc(s.status) : ''}</span></span>
				<i class="ba-stu-photo ${photo ? 'bi-person-check' : 'bi-person-x is-none'}" title="${photo ? 'Avec photo' : 'Sans photo'}"></i>
			</label>`;
		}).join('') : `<div class="ba-list-empty">${all.length ? 'Aucun étudiant ne correspond à la recherche.' : 'Aucun étudiant pour cette sélection.'}</div>`;
		updateCount();
	}
	function updateCount() {
		const n = all.filter((s) => selected.has(String(s.student_id))).length;
		$('listCount').innerHTML = `<b>${n}</b> sur ${all.length} sélectionné${n > 1 ? 's' : ''}`;
	}

	if (IS_GROUP) {
		$('stuList').addEventListener('change', (e) => {
			const id = e.target.dataset && e.target.dataset.id; if (!id) return;
			e.target.checked ? selected.add(id) : selected.delete(id);
			e.target.closest('.ba-stu').classList.toggle('is-off', !e.target.checked);
			updateCount(); rebuildSoon();
		});
		// « Tout cocher / décocher » agit sur les étudiants affichés (recherche et filtre photo)
		$('selAll').onclick = () => { visibleStudents().forEach((s) => selected.add(String(s.student_id))); renderList(); rebuildSoon(150); };
		$('selNone').onclick = () => { visibleStudents().forEach((s) => selected.delete(String(s.student_id))); renderList(); rebuildSoon(150); };
		$('lSearch').addEventListener('input', renderList);
		$('lPhoto').addEventListener('change', renderList);
		$('lSort').addEventListener('change', () => rebuildSoon(100));
		['fSession', 'fMention', 'fNiveau'].forEach((id) => $(id).addEventListener('change', loadStudents));
		document.querySelectorAll('input[name=fInscrits]').forEach((i) => i.addEventListener('change', loadStudents));
		let matT; $('fMatricules').addEventListener('input', () => { clearTimeout(matT); matT = setTimeout(loadStudents, 700); });
		$('fMatricules').addEventListener('input', (e) => { e.target.style.height = 'auto'; e.target.style.height = Math.min(120, e.target.scrollHeight + 2) + 'px'; });
	}

	/* ---------- démarrage ---------- */
	if (IS_GROUP) loadStudents();
	else build();
})();
</script>
</body>
</html>
