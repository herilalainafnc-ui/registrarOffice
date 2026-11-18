<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Paramètres — Espace étudiant</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/settings.mentions.css?v=20260915-1">
	<link rel="stylesheet" href="<?=$app_base?>/src/css/settings.espace-etudiant.css?v=20260915-1">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Espace étudiant — ce que les étudiants voient

   Les notes sont publiées session par session : le Sénat valide une session
   d'examens, on publie celle-là. Tant qu'une session est masquée, ses notes
   n'apparaissent pas dans « Mes Notes » et ne comptent pas dans la moyenne
   affichée à l'étudiant. Le personnel, lui, voit toujours tout.

   Superadmin uniquement (AdminController::espaceEtudiantSettings et
   app/espace-etudiant.save.php). Voir data/espace.etudiant.php.
   ========================================================================== */

require_once(__DIR__ . '/../data/espace.etudiant.php');

$parAnnee  = notesPublicationParAnnee($dtb);
$rubriques = espaceEtudiantRubriques();

/* Sessions qui ont des notes et que les étudiants ne voient pas encore. */
$enAttente = [];
foreach ($parAnnee as $sessions) {
	foreach ($sessions as $s) {
		if (!$s['publiee'] && (int) $s['nb_notes'] > 0) { $enAttente[] = $s; }
	}
}

$nf = static fn ($n) => number_format((int) $n, 0, ',', ' ');
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

				<div class="back flex-1 overflow-y-auto">
					<div class="mn-page ee-page">

						<div class="mn-entete">
							<div class="mn-entete__icone"><i class="bi bi-eye-fill"></i></div>
							<div>
								<h1 class="mn-titre">Espace étudiant</h1>
								<p class="mn-sous">
									Ce que les étudiants voient dans leur espace. Chaque changement
									s'applique immédiatement.
								</p>
							</div>
						</div>

						<div class="mn-note">
							<i class="bi bi-info-circle-fill"></i>
							<div>
								<b>Les notes se publient session par session, après l'aval du Sénat.</b>
								Une session masquée n'apparaît pas dans « Mes Notes » et ne compte pas dans
								la moyenne affichée à l'étudiant, qui la trahirait sinon. Relevés, bulletins
								et classements restent réservés au personnel, qui voit toujours tout.
								Une session créée à l'avenir démarre masquée.
							</div>
						</div>

<?php if ($enAttente !== []) { ?>
						<div class="mn-note mn-note--alerte">
							<i class="bi bi-hourglass-split"></i>
							<div>
								<b><?= count($enAttente) ?> session<?= count($enAttente) > 1 ? 's' : '' ?> avec des notes
								en attente de publication :</b>
								<?= e(implode(', ', array_map(
									static fn ($s) => trim((string) $s['session_name']) . ' ' . trim((string) $s['session_year']),
									$enAttente
								))) ?>.
							</div>
						</div>
<?php } ?>

						<h2 class="ee-section">Rubriques</h2>
						<div class="ee-rubriques">
<?php foreach ($rubriques as $cle => $r) {
	$visible = espaceEtudiantRubriqueVisible($dtb, $cle);
	$trace   = espaceEtudiantRubriqueTrace($dtb, $cle);
?>
							<label class="ee-rubrique">
								<span class="ee-rubrique__texte">
									<span class="ee-rubrique__nom"><?= e($r['libelle']) ?></span>
									<span class="ee-rubrique__aide"><?= e($r['aide']) ?></span>
									<span class="ee-trace js-ee-trace"><?= $trace
										? e('Modifié ' . espaceEtudiantQuand($trace['modifie_le'], $trace['modifie_par']))
										: 'Réglage par défaut' ?></span>
								</span>
								<span class="ee-bascule">
									<input type="checkbox" class="js-ee-rubrique" role="switch"
									       data-cle="<?= e($cle) ?>" data-libelle="<?= e($r['libelle']) ?>"
									       <?= $visible ? 'checked' : '' ?>>
									<span class="ee-bascule__piste" aria-hidden="true"></span>
									<span class="ee-bascule__etat js-ee-rubrique-etat"><?= $visible ? 'Visible' : 'Masquée' ?></span>
								</span>
							</label>
<?php } ?>
						</div>

						<h2 class="ee-section">Publication des notes</h2>
<?php
$rang = 0;
foreach ($parAnnee as $annee => $sessions) {
	$rang++;
	$nbPubliees = count(array_filter($sessions, static fn ($s) => (bool) $s['publiee']));
	$avecNotes  = count(array_filter($sessions, static fn ($s) => (int) $s['nb_notes'] > 0));
	/* Les trois années les plus récentes ouvertes : c'est là que se jouent les
	   publications. Les plus anciennes restent consultables, repliées. */
?>
						<details class="ee-annee"<?= $rang <= 3 ? ' open' : '' ?>>
							<summary class="ee-annee__tete">
								<i class="bi bi-chevron-right ee-annee__fleche" aria-hidden="true"></i>
								<span class="ee-annee__nom"><?= e($annee) ?></span>
								<span class="ee-annee__resume js-ee-resume">
									<?= $nbPubliees ?> publiée<?= $nbPubliees > 1 ? 's' : '' ?> sur <?= count($sessions) ?>
									<?= $avecNotes === 0 ? '· aucune note saisie' : '' ?>
								</span>
							</summary>
							<div class="ee-sessions">
<?php foreach ($sessions as $s) {
	$publiee   = (bool) $s['publiee'];
	$libelle   = trim((string) $s['session_name']) . ' ' . trim((string) $s['session_year']);
	$nbNotes   = (int) $s['nb_notes'];

	if (!$s['decidee']) {
		$trace = 'Nouvelle session : masquée par défaut';
	} elseif ($s['modifie_par'] === 'installation') {
		$trace = $publiee ? 'Publiée à la mise en place' : 'Masquée à la mise en place, en attente du Sénat';
	} else {
		$trace = ($publiee ? 'Publiée ' : 'Masquée ') . espaceEtudiantQuand($s['modifie_le'], $s['modifie_par']);
	}
?>
								<div class="ee-session <?= $publiee ? 'est-publiee' : 'est-masquee' ?>"
								     data-session="<?= (int) $s['session_id'] ?>"
								     data-libelle="<?= e($libelle) ?>"
								     data-etudiants="<?= (int) $s['nb_etudiants'] ?>"
								     data-non-notees="<?= (int) $s['nb_non_notees'] ?>"
								     data-publiee="<?= $publiee ? '1' : '0' ?>">
									<div class="ee-session__info">
										<div class="ee-session__nom">
											<?= e(trim((string) $s['session_name'])) ?>
											<span class="ee-session__id">session <?= (int) $s['session_id'] ?></span>
										</div>
										<div class="ee-session__chiffres">
<?php if ($nbNotes > 0) { ?>
											<?= $nf($nbNotes) ?> note<?= $nbNotes > 1 ? 's' : '' ?>
											· <?= $nf($s['nb_etudiants']) ?> étudiant<?= (int) $s['nb_etudiants'] > 1 ? 's' : '' ?>
<?php if ((int) $s['nb_non_notees'] > 0) { ?>
											· <span class="ee-session__nn"><?= $nf($s['nb_non_notees']) ?> pas encore notée<?= (int) $s['nb_non_notees'] > 1 ? 's' : '' ?></span>
<?php } ?>
<?php } else { ?>
											Aucune note saisie
<?php } ?>
										</div>
										<div class="ee-trace js-ee-trace"><?= e($trace) ?></div>
									</div>
									<span class="ee-etat js-ee-etat">
										<i class="bi <?= $publiee ? 'bi-eye-fill' : 'bi-eye-slash-fill' ?>" aria-hidden="true"></i>
										<?= $publiee ? 'Publiée' : 'Masquée' ?>
									</span>
									<button type="button" class="ee-btn <?= $publiee ? 'ee-btn--masquer' : 'ee-btn--publier' ?> js-ee-publier">
										<?= $publiee ? 'Masquer' : 'Publier les notes' ?>
									</button>
								</div>
<?php } ?>
							</div>
						</details>
<?php } ?>

					</div>

					<?php require('../init/footer.php'); ?>
				</div>
			</div>
		</div>
	</div>

<script src="<?=$app_base?>/src/js/settings.espace-etudiant.js?v=20260915-1"></script>
</body>
</html>
