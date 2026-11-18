<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Tableau de bord</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/tableau-de-bord.css?v=<?=@filemtime(__DIR__ . '/css/tableau-de-bord.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Tableau de bord du personnel : la page d'arrivée après la connexion.

   Disposition reprise du gabarit « dashboard-4 » (shadcn), en PHP et sur les
   jetons de l'application :
     indicateurs        inscrits, nouveaux, licence / master, candidatures
     grande courbe      inscriptions cumulées depuis le 1er juillet (rentrée)
     carte de taux      saisie des notes de l'année terminée (registrariat)
     classement         les mentions par nombre d'inscrits
     répartition        cycle, sexe, hébergement
     colonne de côté    « À traiter » et accès rapides

   Les graphiques sont dessinés ici, sans bibliothèque : ils suivent le thème
   et se lisent sans JavaScript. Chaque valeur tracée est aussi écrite.

   Données et règles d'accès : data/tableau-de-bord.php.
   Accès : DashboardController::tableauDeBord (personnel ; les enseignants et
   les étudiants sont renvoyés vers leur propre espace).
   ========================================================================== */
require_once(__DIR__ . '/../data/tableau-de-bord.php');

$tdbCtx = tdbContexte();
$tdbAnnees = tdbAnnees($dtb);
$tdbAnnee = (string) ($_GET['annee'] ?? '');
if (!in_array($tdbAnnee, $tdbAnnees, true)) { $tdbAnnee = inscAnneeCourante(); }

$tdbItems = tdbATraiter($dtb, $tdbCtx, $app_base);
$tdb = tdbChiffres($dtb, $tdbCtx, $tdbAnnee);
$tdbA = $tdb['actuel'];
$tdbP = $tdb['avant'];
$tdbCourbe = tdbCourbe($dtb, $tdbAnnee);
/* La saisie se juge sur une année terminée : l'année choisie, ou la
   précédente si l'on regarde l'année en cours. */
$tdbAnneeNotes = $tdb['courante'] ? $tdb['precedente'] : $tdbAnnee;
$tdbSaisie = $tdbCtx['registrariat'] ? tdbSaisieNotes($dtb, $tdbAnneeNotes) : null;
$tdbAcces = tdbAccesRapides($tdbCtx, $app_base);
/* La rentrée en direct : le rythme des derniers jours, les dossiers au guichet
   (année en cours seulement, ses cases étant fiables) et les candidatures */
$tdbRythme = tdbRythme($tdbCourbe);
$tdbGuichet = $tdb['courante'] && $tdbCtx['inscription'] ? tdbGuichet($dtb, $tdbAnnee) : null;
$tdbAdmissions = $tdbCtx['registrariat'] ? tdbAdmissions($dtb, $tdbAnnee) : null;
if ($tdbAdmissions !== null && $tdbAdmissions['recues'] === 0) { $tdbAdmissions = null; }
/* L'activité vient du journal : réservée au superadmin, comme lui */
$tdbActivite = $tdbCtx['superadmin'] ? tdbActivite($dtb) : null;

$tdbCourt = fn (string $a) => str_replace(' - ', '-', $a);
$tdbNombre = fn (int $n) => number_format($n, 0, ',', "\u{00A0}");
$tdbPart = fn (int $n, int $total) => $total > 0 ? (int) round($n / $total * 100) : 0;
$tdbAn = $tdbCourt($tdbAnnee);
$tdbAnPrec = $tdbCourt($tdb['precedente']);

/** Pastille de tendance : flèche et signe disent le sens, la couleur le redit. */
$tdbTendance = function (int $actuel, int $avant) use ($tdbNombre): string {
	$e = $actuel - $avant;
	$sens = $e > 0 ? 'hausse' : ($e < 0 ? 'baisse' : 'egal');
	$icone = $e > 0 ? 'bi-arrow-up-right' : ($e < 0 ? 'bi-arrow-down-right' : 'bi-arrow-right');
	return '<span class="tb-tendance tb-tendance--' . $sens . '"><i class="bi ' . $icone . '" aria-hidden="true"></i>'
		. ($e > 0 ? '+' : ($e < 0 ? '−' : '')) . $tdbNombre(abs($e)) . '</span>';
};
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<?php /* La page entiere est une zone : changer d'annee la recharge sans le menu (src/js/zone-ajax.js) */ ?>
					<div class="tb-page" id="tbZone" data-zone-ajax>

						<?php
						/* Le compte ($rg_user) est charge par init/topbar.php, plus haut */
						$tdbPrenom = trim((string) ($rg_user['prenom'] ?? ''));
						if ($tdbPrenom === '') { $tdbPrenom = trim((string) ($rg_user['pseudo'] ?? '')); }
						$tdbPrenom = $tdbPrenom !== '' ? explode(' ', $tdbPrenom)[0] : '';
						$tdbSalut = (int) date('H') >= 18 ? 'Bonsoir' : 'Bonjour';
						?>
						<header class="tb-entete">
							<div>
								<p class="tb-surtitre"><?=htmlspecialchars(tdbDateDuJour())?><?php if (!empty($tdbCtx['cycle'])): ?> · <?=htmlspecialchars(perimetreLibelle())?><?php endif; ?></p>
								<h1 class="tb-titre"><?=$tdbSalut?><?= $tdbPrenom !== '' ? ', ' . htmlspecialchars($tdbPrenom) : '' ?></h1>
							</div>
							<form class="tb-annee" method="get" action="<?=$app_base?>/tableau-de-bord">
								<label for="tbAnnee">Année scolaire</label>
								<select id="tbAnnee" name="annee" data-zone-auto>
									<?php foreach ($tdbAnnees as $a): ?>
									<option value="<?=htmlspecialchars($a)?>"<?= $a === $tdbAnnee ? ' selected' : '' ?>><?=htmlspecialchars($tdbCourt($a))?></option>
									<?php endforeach; ?>
								</select>
								<noscript><button type="submit" class="tb-bouton">Afficher</button></noscript>
							</form>
						</header>

						<!-- ============ INDICATEURS ============ -->
						<section class="tb-stats" aria-label="Indicateurs de l'année <?=htmlspecialchars($tdbAn)?>">
							<div class="tb-stat">
								<div class="tb-stat__haut"><span class="tb-stat__libelle">Étudiants inscrits</span><span class="tb-stat__icone" aria-hidden="true"><i class="bi bi-people"></i></span></div>
								<b class="tb-stat__valeur"><?=$tdbNombre($tdbA['inscrits'])?></b>
								<p class="tb-stat__bas">
									<?php if ($tdb['courante'] && $tdb['meme_date'] !== null): ?>
									<span><b><?=$tdbNombre($tdb['meme_date'])?></b> l'an dernier à la même date · <?=$tdbNombre($tdbP['inscrits'])?> sur l'année</span>
									<?php else: ?>
									<?=$tdbTendance($tdbA['inscrits'], $tdbP['inscrits'])?><span>par rapport à <?=$tdbAnPrec?> (<?=$tdbNombre($tdbP['inscrits'])?>)</span>
									<?php endif; ?>
								</p>
							</div>
							<div class="tb-stat">
								<div class="tb-stat__haut"><span class="tb-stat__libelle">Nouveaux étudiants</span><span class="tb-stat__icone" aria-hidden="true"><i class="bi bi-person-plus"></i></span></div>
								<b class="tb-stat__valeur"><?=$tdbNombre($tdbA['nouveaux'])?></b>
								<p class="tb-stat__bas">
									<?php if ($tdb['courante']): /* l'annee commence : la comparer a toute l'annee passee n'a pas de sens */ ?>
									<span><?=$tdbNombre($tdbP['nouveaux'])?> sur toute l'année <?=$tdbAnPrec?></span>
									<?php else: ?>
									<?=$tdbTendance($tdbA['nouveaux'], $tdbP['nouveaux'])?><span>par rapport à <?=$tdbAnPrec?> (<?=$tdbNombre($tdbP['nouveaux'])?>)</span>
									<?php endif; ?>
								</p>
							</div>
							<div class="tb-stat">
								<div class="tb-stat__haut"><span class="tb-stat__libelle">Licence · Master</span><span class="tb-stat__icone" aria-hidden="true"><i class="bi bi-mortarboard"></i></span></div>
								<b class="tb-stat__valeur"><?=$tdbNombre($tdbA['licence'])?><small> · </small><?=$tdbNombre($tdbA['master'])?></b>
								<p class="tb-stat__bas"><span><?=$tdbNombre($tdbP['licence'])?> · <?=$tdbNombre($tdbP['master'])?> en <?=$tdbAnPrec?></span></p>
							</div>
							<?php if ($tdb['candidatures'] !== null): $c = $tdb['candidatures']; ?>
							<a class="tb-stat tb-stat--lien" href="<?=$app_base?>/candidatures">
								<div class="tb-stat__haut"><span class="tb-stat__libelle">Candidatures reçues</span><span class="tb-stat__icone" aria-hidden="true"><i class="bi bi-inbox"></i></span></div>
								<b class="tb-stat__valeur"><?=$tdbNombre($c['recues'] ?? 0)?></b>
								<p class="tb-stat__bas"><span><?=$tdbNombre($c['acceptees'] ?? 0)?> acceptée<?= ($c['acceptees'] ?? 0) > 1 ? 's' : '' ?> · <?=$tdbNombre($c['avant'] ?? 0)?> en <?=$tdbAnPrec?></span></p>
							</a>
							<?php endif; ?>
						</section>

						<div class="tb-corps">
							<div class="tb-principal">

								<!-- ============ RENTRÉE EN DIRECT ============ -->
								<?php
								$k = $tdbCourbe;
								/* Les deux vues (cumul, jour par jour) sont dessinées par
								   src/js/tableau-de-bord.js à partir de ces séries ; la phrase de
								   synthèse en donne l'essentiel à qui ne voit pas le graphique. */
								$tdbGraphe = ['annee' => $tdbAn, 'precedente' => $tdbAnPrec, 'debut' => $k['debut'],
									'serie' => $k['serie'], 'serie_prec' => $k['serie_prec'], 'limite' => $k['limite'], 'fenetre' => $k['fenetre']];
								?>
								<section class="tb-carte tb-rentree" aria-labelledby="tbRentreeTitre">
									<div class="tb-carte__entete">
										<div>
											<h2 class="tb-carte__titre" id="tbRentreeTitre"><?php if ($tdb['courante']): ?><span class="tb-direct" aria-hidden="true"></span>La rentrée en direct<?php else: ?>Les inscriptions <?=$tdbAn?><?php endif; ?></h2>
											<p class="tb-carte__sous">Chaque étudiant compté le jour de sa première inscription de l'année, comparé à <?=$tdbAnPrec?></p>
										</div>
										<div class="tb-vues" role="group" aria-label="Affichage du graphique">
											<button type="button" class="tb-vue" data-vue="cumul" aria-pressed="true"><i class="bi bi-graph-up" aria-hidden="true"></i>Cumul</button>
											<button type="button" class="tb-vue" data-vue="jours" aria-pressed="false"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Jour par jour</button>
										</div>
									</div>
									<?php if ($tdbRythme): $r = $tdbRythme; ?>
									<dl class="tb-rythme">
										<?php foreach ([
											["Aujourd'hui", $r['jour'], $r['jour_prec'], 'le même jour l\'an dernier'],
											['7 derniers jours', $r['semaine'], $r['semaine_prec'], 'aux mêmes dates l\'an dernier'],
											['Depuis le 1er juillet', $r['cumul'], $r['cumul_prec'], 'à la même date l\'an dernier'],
										] as [$quand, $n, $avant, $repere]): ?>
										<div class="tb-rythme__case">
											<dt><?=$quand?></dt>
											<dd class="tb-rythme__valeur"><b><?=$tdbNombre($n)?></b> inscription<?= $n > 1 ? 's' : '' ?></dd>
											<dd class="tb-rythme__avant"><?=$tdbNombre($avant)?> <?=$repere?></dd>
										</div>
										<?php endforeach; ?>
									</dl>
									<?php endif; ?>
									<p class="tb-courbe__resume">
										<?php if ($k['limite'] !== null): ?>
										Au <?=htmlspecialchars(tdbDate(date('Y-m-d')))?> : <b><?=$tdbNombre($k['total'])?></b> inscrit<?= $k['total'] > 1 ? 's' : '' ?>,
										contre <b><?=$tdbNombre((int) $k['au_jour_prec'])?></b> l'an dernier à la même date.
										<?php else: ?>
										<b><?=$tdbNombre($k['total'])?></b> inscrits sur l'année <?=$tdbAn?>, contre <b><?=$tdbNombre($k['total_prec'])?></b> en <?=$tdbAnPrec?>.
										<?php endif; ?>
									</p>
									<div class="tb-graphique" data-vue="cumul" tabindex="0" role="group" aria-roledescription="graphique"
									     aria-label="Inscriptions de <?=$tdbAn?> comparées à <?=$tdbAnPrec?>. Flèches gauche et droite : parcourir les jours.">
										<div class="tb-graphique__toile" aria-hidden="true"></div>
										<p class="sr-only" aria-live="polite" data-annonce></p>
										<noscript><p class="tb-vide">Ce graphique a besoin de JavaScript ; la phrase ci-dessus en donne l'essentiel.</p></noscript>
									</div>
									<div class="tb-legende">
										<button type="button" class="tb-serie tb-serie--a" data-serie="a" aria-pressed="true"><span aria-hidden="true"></span><?=$tdbAn?></button>
										<button type="button" class="tb-serie tb-serie--b" data-serie="b" aria-pressed="true"><span aria-hidden="true"></span><?=$tdbAnPrec?></button>
										<span class="tb-astuce"><i class="bi bi-cursor" aria-hidden="true"></i>Survolez le graphique, ou parcourez-le avec ← →</span>
									</div>
									<script type="application/json" class="tb-donnees"><?= json_encode($tdbGraphe, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
								</section>

								<?php if ($tdbGuichet !== null || $tdbAdmissions !== null): ?>
								<div class="tb-duo<?= $tdbGuichet === null || $tdbAdmissions === null ? ' tb-duo--seul' : '' ?>">
									<?php if ($tdbGuichet !== null): $g = $tdbGuichet; ?>
									<!-- ============ DOSSIERS AU GUICHET ============ -->
									<section class="tb-carte tb-guichet" aria-labelledby="tbGuichetTitre">
										<div class="tb-carte__entete">
											<div>
												<h2 class="tb-carte__titre" id="tbGuichetTitre">Dossiers au guichet</h2>
												<p class="tb-carte__sous"><?=$tdbAn?> · <?=tdbPluriel($g['total'], 'dossier ouvert', 'dossiers ouverts')?> · chacun à sa première étape à faire</p>
											</div>
											<a class="tb-lien" href="<?=$app_base?>/inscription/inscription" data-zone-ignorer>Guichet <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
										</div>
										<?php if ($g['total'] === 0): ?>
										<p class="tb-vide">Aucun dossier ouvert pour <?=$tdbAn?> pour l'instant.</p>
										<?php else: ?>
										<?php
											/* Bulle de chaque etape : qui y attend (les plus anciens d'abord), puis
											   qui l'a passee (les derniers venus d'abord) ; matricule et nom, en
											   deux colonnes (src/js/tableau-de-bord.js). Le clic ouvre la liste
											   complete des dossiers en attente, avec les liens. */
											$tdbGens = static function (array $liste, int $total, int $max) use ($tdbNombre): array {
												$lignes = array_map(static fn ($d) => $d['matricule'] . "\t" . ($d['nom'] !== '' ? $d['nom'] : '(fiche introuvable)'), array_slice($liste, 0, $max));
												$reste = $total - count($lignes);
												if ($reste > 0) { $lignes[] = 'et ' . $tdbNombre($reste) . ' autre' . ($reste > 1 ? 's' : ''); }
												return $lignes;
											};
										?>
										<ol class="tb-etapes">
											<?php foreach ($g['etapes'] as $i => $e):
												$bulle = [];
												if ($e['attente']) {
													$bulle[] = '## En attente à cette étape · ' . $tdbNombre($e['attente']);
													array_push($bulle, ...$tdbGens($e['dossiers'], $e['attente'], $e['faites'] ? 10 : 15));
												}
												if ($e['faites']) {
													$bulle[] = '## Étape faite · ' . $tdbNombre($e['faites']);
													array_push($bulle, ...$tdbGens($e['faits'], $e['faites'], $e['attente'] ? 5 : 15));
												}
												if (!$bulle) { $bulle[] = "Aucun dossier n'a encore atteint cette étape."; }
												$attributs = 'data-bulle-titre="' . htmlspecialchars(($i + 1) . '. ' . $e['nom']) . '" data-bulle="' . htmlspecialchars(implode("\n", $bulle)) . '" data-bulle-large';
												$ligne = '<span class="tb-etape__num">' . ($i + 1) . '</span>'
													. '<span class="tb-etape__nom">' . htmlspecialchars($e['nom']) . '<small>' . htmlspecialchars($e['aide']) . '</small></span>'
													. '<span class="tb-etape__piste" aria-hidden="true"><span style="--part: ' . $tdbPart($e['faites'], $g['total']) . '%"></span></span>'
													. '<span class="tb-etape__faites"><b>' . $tdbNombre($e['faites']) . '</b> faite' . ($e['faites'] > 1 ? 's' : '') . '</span>'
													. '<span class="tb-etape__attente">' . ($e['attente'] ? $tdbNombre($e['attente']) . ' en attente' : '') . '</span>'; ?>
											<li class="tb-etape<?= $e['attente'] ? ' tb-etape--attente' : '' ?>">
												<?php if ($e['attente']): ?>
												<details>
													<summary class="tb-etape__ligne" <?=$attributs?> data-bulle-pied="Cliquez pour ouvrir les dossiers en attente" data-bulle-si-ferme><?=$ligne?><i class="bi bi-chevron-down tb-etape__fleche" aria-hidden="true"></i></summary>
													<ul class="tb-dossiers">
														<?php foreach ($e['dossiers'] as $d): ?>
														<li><a href="<?=$app_base?>/inscription/inscription?student_id=<?=rawurlencode($d['matricule'])?>" data-zone-ignorer>
															<span><?=htmlspecialchars($d['nom'] !== '' ? $d['nom'] : $d['matricule'])?></span>
															<small><?=htmlspecialchars($d['matricule'])?><?= $d['depuis'] !== '' ? ' · ouvert ' . tdbDepuis($d['depuis']) : '' ?></small>
															<i class="bi bi-arrow-right" aria-hidden="true"></i>
														</a></li>
														<?php endforeach; ?>
														<?php if ($e['attente'] > count($e['dossiers'])): ?>
														<li class="tb-dossiers__plus">et <?=$tdbNombre($e['attente'] - count($e['dossiers']))?> autres, les plus anciens en premier</li>
														<?php endif; ?>
													</ul>
												</details>
												<?php else: ?>
												<div class="tb-etape__ligne" tabindex="0" <?=$attributs?>><?=$ligne?><span class="tb-etape__fleche" aria-hidden="true"></span></div>
												<?php endif; ?>
											</li>
											<?php endforeach; ?>
										</ol>
										<p class="tb-guichet__complets"><i class="bi bi-check2-circle" aria-hidden="true"></i><span><b><?=$tdbNombre($g['complets'])?></b> dossier<?= $g['complets'] > 1 ? 's' : '' ?> complet<?= $g['complets'] > 1 ? 's' : '' ?>, toutes étapes faites</span></p>
										<?php endif; ?>
									</section>
									<?php endif; ?>

									<?php if ($tdbAdmissions !== null): $ad = $tdbAdmissions;
										$etages = [
											['Candidatures reçues', $ad['recues'], null, 'bi-inbox', ''],
											['Acceptées', $ad['acceptees'], $ad['recues'], 'bi-hand-thumbs-up', 'des candidatures reçues'],
											['Fiche étudiante créée', $ad['fiches'], $ad['acceptees'], 'bi-person-vcard', 'des acceptées'],
											['Inscrites en ' . $tdbAn, $ad['inscrites'], $ad['fiches'], 'bi-check2-circle', 'des fiches créées'],
										];
										$sansFiche = $ad['acceptees'] - $ad['fiches'];
										$sansInscription = $ad['fiches'] - $ad['inscrites']; ?>
									<!-- ============ DES CANDIDATURES AUX INSCRIPTIONS ============ -->
									<section class="tb-carte tb-admissions" aria-labelledby="tbAdmTitre">
										<div class="tb-carte__entete">
											<div>
												<h2 class="tb-carte__titre" id="tbAdmTitre">Des candidatures aux inscriptions</h2>
												<p class="tb-carte__sous">Candidatures reçues depuis le 1<sup>er</sup> juillet <?=substr($tdbAnnee, 0, 4)?></p>
											</div>
											<a class="tb-lien" href="<?=$app_base?>/candidatures" data-zone-ignorer>Candidatures <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
										</div>
										<ol class="tb-entonnoir">
											<?php foreach ($etages as [$lib, $n, $base, $icone, $base_lib]):
												$part = $ad['recues'] > 0 ? round($n / $ad['recues'] * 100, 1) : 0;
												$taux = $base !== null ? $tdbPart($n, $base) : null; ?>
											<li class="tb-entonnoir__etage" style="--part: <?=$part?>%"<?php if ($taux !== null): ?> data-bulle-titre="<?=htmlspecialchars($lib)?>" data-bulle="<?=htmlspecialchars($tdbNombre($n) . ' sur ' . $tdbNombre($base) . ' ' . $base_lib . ' (' . $taux . ' %)')?>"<?php endif; ?>>
												<span class="tb-entonnoir__libelle"><i class="bi <?=$icone?>" aria-hidden="true"></i><?=htmlspecialchars($lib)?></span>
												<span class="tb-entonnoir__piste" aria-hidden="true"><span></span></span>
												<b class="tb-entonnoir__valeur"><?=$tdbNombre($n)?></b>
												<?php if ($taux !== null): ?><small class="tb-entonnoir__taux"><?=$taux?> %<span class="sr-only"> <?=$base_lib?></span></small><?php endif; ?>
											</li>
											<?php endforeach; ?>
										</ol>
										<?php if ($sansFiche > 0): ?>
										<details class="tb-attente">
											<summary><i class="bi bi-person-exclamation" aria-hidden="true"></i><span><b><?=$tdbNombre($sansFiche)?></b> acceptée<?= $sansFiche > 1 ? 's' : '' ?> sans fiche étudiante</span><i class="bi bi-chevron-down tb-attente__fleche" aria-hidden="true"></i></summary>
											<ul class="tb-dossiers">
												<?php foreach ($ad['sans_fiche'] as $c): ?>
												<li><a href="<?=$app_base . ($tdbCtx['niveau'] <= 3 ? '/students/create?candidat=' . (int) $c['id'] : '/candidatures?statut=accepte')?>" data-zone-ignorer>
													<span><?=htmlspecialchars((string) $c['nom_complet'])?></span>
													<small><?=htmlspecialchars((string) $c['mention_libelle'])?><?= $c['date_traitement'] ? ' · acceptée ' . tdbDepuis((string) $c['date_traitement']) : '' ?></small>
													<?php if ($tdbCtx['niveau'] <= 3): ?><em>Créer la fiche</em><?php endif; ?>
												</a></li>
												<?php endforeach; ?>
												<?php if ($sansFiche > count($ad['sans_fiche'])): ?><li class="tb-dossiers__plus">et <?=$tdbNombre($sansFiche - count($ad['sans_fiche']))?> autres</li><?php endif; ?>
											</ul>
										</details>
										<?php endif; ?>
										<?php if ($sansInscription > 0): ?>
										<details class="tb-attente">
											<summary><i class="bi bi-hourglass-split" aria-hidden="true"></i><span><b><?=$tdbNombre($sansInscription)?></b> fiche<?= $sansInscription > 1 ? 's' : '' ?> créée<?= $sansInscription > 1 ? 's' : '' ?>, pas encore inscrite<?= $sansInscription > 1 ? 's' : '' ?></span><i class="bi bi-chevron-down tb-attente__fleche" aria-hidden="true"></i></summary>
											<ul class="tb-dossiers">
												<?php foreach ($ad['sans_inscription'] as $c): ?>
												<li><a href="<?=$app_base?>/inscription/inscription?student_id=<?=rawurlencode((string) $c['student_id'])?>" data-zone-ignorer>
													<span><?=htmlspecialchars((string) $c['nom_complet'])?></span>
													<small><?=htmlspecialchars((string) $c['student_id'])?> · <?=htmlspecialchars((string) $c['mention_libelle'])?></small>
													<em>Ouvrir le dossier</em>
												</a></li>
												<?php endforeach; ?>
											</ul>
										</details>
										<?php endif; ?>
									</section>
									<?php endif; ?>
								</div>
								<?php endif; ?>

								<div class="tb-duo">
									<!-- ============ CLASSEMENT DES MENTIONS ============ -->
									<section class="tb-carte" aria-labelledby="tbRangTitre">
										<div class="tb-carte__entete">
											<div>
												<h2 class="tb-carte__titre" id="tbRangTitre">Classement des mentions</h2>
												<p class="tb-carte__sous"><?php if ($tdb['courante']): ?>Inscrits <?=$tdbAn?> · repère et chiffre gris : toute l'année <?=$tdbAnPrec?><?php else: ?>Inscrits <?=$tdbAn?>, écart avec <?=$tdbAnPrec?><?php endif; ?></p>
											</div>
										</div>
										<?php if (!$tdb['mentions']): ?>
										<p class="tb-vide">Aucune inscription pour ces deux années.</p>
										<?php else:
											$max = max(1, ...array_map(fn ($m) => max($m['n'], $m['avant']), $tdb['mentions'])); ?>
										<ol class="tb-rangs">
											<?php foreach ($tdb['mentions'] as $i => $m):
												$bulle = $tdbAn . ' : ' . $tdbNombre($m['n']) . ' inscrit' . ($m['n'] > 1 ? 's' : '') . ' (' . $tdbPart($m['n'], max(1, $tdbA['inscrits'])) . ' % du total)'
													. "\n" . $tdbAnPrec . ' : ' . $tdbNombre($m['avant']) . ($tdb['courante'] ? " sur toute l'année" : ''); ?>
											<li class="tb-rang" tabindex="0" data-bulle-titre="<?=htmlspecialchars($m['libelle'])?>" data-bulle="<?=htmlspecialchars($bulle)?>">
												<span class="tb-rang__numero"><?=$i + 1?></span>
												<div class="tb-rang__corps">
													<div class="tb-rang__ligne">
														<span class="tb-rang__libelle"><?=htmlspecialchars($m['libelle'])?></span>
														<?php /* Annee en cours : l'ecart avec une annee complete n'est pas une baisse */ ?>
														<span class="tb-rang__valeur"><b><?=$tdbNombre($m['n'])?></b><?= $tdb['courante'] ? '<small class="tb-rang__avant">' . $tdbNombre($m['avant']) . '</small>' : $tdbTendance($m['n'], $m['avant']) ?></span>
													</div>
													<span class="tb-rang__piste" aria-hidden="true">
														<span class="tb-rang__plein" style="width: <?=round($m['n'] / $max * 100, 1)?>%"></span>
														<span class="tb-rang__repere" style="left: <?=round($m['avant'] / $max * 100, 1)?>%" title="<?=htmlspecialchars($tdbAnPrec . ' : ' . $m['avant'])?>"></span>
													</span>
												</div>
											</li>
											<?php endforeach; ?>
										</ol>
										<?php endif; ?>
									</section>

									<?php if ($tdbSaisie !== null):
										$pc = $tdbPart($tdbSaisie['saisies'], $tdbSaisie['total']);
										$manquantes = $tdbSaisie['total'] - $tdbSaisie['saisies'];
										$circ = round(2 * M_PI * 42, 2); ?>
									<!-- ============ SAISIE DES NOTES ============ -->
									<section class="tb-carte tb-taux" aria-labelledby="tbTauxTitre">
										<div class="tb-carte__entete">
											<div>
												<h2 class="tb-carte__titre" id="tbTauxTitre">Saisie des notes</h2>
												<p class="tb-carte__sous">Année <?=htmlspecialchars($tdbCourt($tdbAnneeNotes))?>, terminée</p>
											</div>
										</div>
										<div class="tb-taux__haut">
											<div class="tb-anneau" aria-hidden="true" data-circ="<?=$circ?>">
												<svg viewBox="0 0 100 100">
													<circle class="tb-anneau__fond" cx="50" cy="50" r="42" />
													<circle class="tb-anneau__plein" cx="50" cy="50" r="42" stroke-dasharray="<?=round($circ * $pc / 100, 2)?> <?=$circ?>" />
												</svg>
												<b><?=$pc?> %</b>
											</div>
											<p class="tb-taux__texte" aria-live="polite"><b><?=$tdbNombre($tdbSaisie['saisies'])?></b> notes saisies sur <?=$tdbNombre($tdbSaisie['total'])?>.<br>
											<?php if ($manquantes > 0): ?><span class="tb-taux__manque"><?=$tdbNombre($manquantes)?> encore à saisir.</span><?php else: ?>Tout est saisi.<?php endif; ?></p>
										</div>
										<ul class="tb-taux__liste">
											<?php foreach ($tdbSaisie['mentions'] as $m): $p = $tdbPart($m['saisies'], $m['total']); ?>
											<li tabindex="0" data-taux="<?=$p?>" data-libelle="<?=htmlspecialchars($m['libelle'])?>" data-saisies="<?=$m['saisies']?>" data-total="<?=$m['total']?>">
												<span class="tb-taux__libelle"><?=htmlspecialchars($m['libelle'])?></span>
												<span class="tb-taux__piste" aria-hidden="true"><span style="width: <?=$p?>%"></span></span>
												<b class="tb-taux__valeur"><?=$p?> %</b>
											</li>
											<?php endforeach; ?>
										</ul>
									</section>
									<?php endif; ?>

									<!-- ============ RÉPARTITION ============ -->
									<?php
									$repartitions = [
										'Cycle'       => [['Licence', $tdbA['licence'], 'a'], ['Master', $tdbA['master'], 'b']],
										'Sexe'        => [['Femmes', $tdbA['femmes'], 'a'], ['Hommes', $tdbA['hommes'], 'b']],
										'Hébergement' => [['Externes', $tdbA['externe'], 'a'], ['Internes', $tdbA['interne'], 'b'], ['Bungalows', $tdbA['bungalow'], 'c']],
									];
									?>
									<section class="tb-carte tb-repart<?= $tdbSaisie !== null ? ' tb-repart--large' : '' ?>" aria-labelledby="tbRepartTitre">
										<div class="tb-carte__entete">
											<div>
												<h2 class="tb-carte__titre" id="tbRepartTitre">Répartition des inscrits</h2>
												<p class="tb-carte__sous"><?=$tdbAn?></p>
											</div>
										</div>
										<?php if ($tdbA['inscrits'] === 0): ?>
										<p class="tb-vide">Aucune inscription pour <?=$tdbAn?> pour l'instant.</p>
										<?php else: ?>
										<div class="tb-parts">
											<?php foreach ($repartitions as $titre => $parts):
												$inconnus = $tdbA['inscrits'] - array_sum(array_column($parts, 1)); ?>
											<div class="tb-part">
												<h3 class="tb-part__titre"><?=$titre?></h3>
												<div class="tb-part__barre" aria-hidden="true">
													<?php foreach ($parts as [$lib, $n, $ton]): if ($n > 0): ?><span class="tb-part__segment tb-part__segment--<?=$ton?>" style="flex-grow: <?=$n?>" data-cle="<?=$ton?>" data-bulle-titre="<?=$titre?>" data-bulle="<?=htmlspecialchars($lib . ' : ' . $tdbNombre($n) . ' (' . $tdbPart($n, $tdbA['inscrits']) . ' %)')?>"></span><?php endif; endforeach; ?>
													<?php if ($inconnus > 0): ?><span class="tb-part__segment tb-part__segment--x" style="flex-grow: <?=$inconnus?>" data-cle="x" data-bulle-titre="<?=$titre?>" data-bulle="<?=htmlspecialchars('Non renseigné : ' . $tdbNombre($inconnus) . ' (' . $tdbPart($inconnus, $tdbA['inscrits']) . ' %)')?>"></span><?php endif; ?>
												</div>
												<ul class="tb-part__legende">
													<?php foreach ($parts as [$lib, $n, $ton]): ?>
													<li tabindex="0" data-cle="<?=$ton?>"><span class="tb-pastille tb-pastille--<?=$ton?>" aria-hidden="true"></span><?=$lib?> <b><?=$tdbNombre($n)?></b> <small><?=$tdbPart($n, $tdbA['inscrits'])?> %</small></li>
													<?php endforeach; ?>
													<?php if ($inconnus > 0): ?><li tabindex="0" data-cle="x"><span class="tb-pastille tb-pastille--x" aria-hidden="true"></span>Non renseigné <b><?=$tdbNombre($inconnus)?></b></li><?php endif; ?>
												</ul>
											</div>
											<?php endforeach; ?>
										</div>
										<?php endif; ?>
									</section>
								</div>
							</div>

							<div class="tb-cote">
								<!-- ============ À TRAITER ============ -->
								<section class="tb-carte tb-traiter" aria-labelledby="tbTraiterTitre">
									<div class="tb-carte__entete">
										<h2 class="tb-carte__titre" id="tbTraiterTitre">À traiter</h2>
										<?php if ($tdbItems): ?><span class="tb-compte"><?=count($tdbItems)?></span><?php endif; ?>
									</div>
									<?php if (!$tdbItems): ?>
									<div class="tb-rien">
										<i class="bi bi-check2-circle" aria-hidden="true"></i>
										<p><b>Rien n'est en attente.</b> Les remises, candidatures et dossiers qui vous concernent sont à jour.</p>
									</div>
									<?php else: ?>
									<ul class="tb-liste">
										<?php foreach ($tdbItems as $it):
											$corps = '<span class="tb-item__icone" aria-hidden="true"><i class="bi ' . $it['icone'] . '"></i></span>'
												. '<span class="tb-item__texte"><b>' . htmlspecialchars($it['titre']) . '</b>'
												. ($it['detail'] !== '' ? '<small>' . htmlspecialchars($it['detail']) . '</small>' : '') . '</span>';
										?>
										<li class="tb-item tb-item--<?=$it['ton']?>">
											<?php if (!empty($it['liste'])): ?>
											<details class="tb-item__details">
												<summary class="tb-item__ligne"><?=$corps?><i class="bi bi-chevron-down tb-item__fleche" aria-hidden="true"></i></summary>
												<ul class="tb-sous-liste">
													<?php foreach ($it['liste'] as $l): ?>
													<li>
														<?php if ($l['lien'] !== ''): ?><a href="<?=htmlspecialchars($l['lien'])?>" data-zone-ignorer><?php else: ?><span><?php endif; ?>
														<span><?=htmlspecialchars($l['texte'])?></span>
														<?php if ($l['valeur'] !== ''): ?><b class="tb-sous-liste__valeur"><?=htmlspecialchars($l['valeur'])?></b><?php endif; ?>
														<?php if ($l['lien'] !== ''): ?></a><?php else: ?></span><?php endif; ?>
													</li>
													<?php endforeach; ?>
												</ul>
												<?php if ($it['lien'] !== ''): ?>
												<a class="tb-item__tout" href="<?=htmlspecialchars($it['lien'])?>" data-zone-ignorer>Ouvrir la page <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
												<?php endif; ?>
											</details>
											<?php else: ?>
											<a class="tb-item__ligne" href="<?=htmlspecialchars($it['lien'])?>" data-zone-ignorer><?=$corps?><i class="bi bi-chevron-right tb-item__fleche" aria-hidden="true"></i></a>
											<?php endif; ?>
										</li>
										<?php endforeach; ?>
									</ul>
									<?php endif; ?>
								</section>

								<!-- ============ ACCÈS RAPIDES ============ -->
								<?php if ($tdbAcces): ?>
								<section class="tb-carte" aria-labelledby="tbAccesTitre" data-tuto="acces-rapides">
									<div class="tb-carte__entete"><h2 class="tb-carte__titre" id="tbAccesTitre">Accès rapides</h2></div>
									<div class="tb-acces">
										<?php foreach ($tdbAcces as $a): ?>
										<a class="tb-acces__lien" href="<?=htmlspecialchars($a['lien'])?>" data-zone-ignorer><i class="bi <?=$a['icone']?>" aria-hidden="true"></i><span><?=htmlspecialchars($a['libelle'])?></span></a>
										<?php endforeach; ?>
									</div>
								</section>
								<?php endif; ?>
								<?php if ($tdbActivite !== null):
									$ac = $tdbActivite;
									$aujourdhui = end($ac['jours']);
									$actifsJour = $aujourdhui['personnel'] + $aujourdhui['enseignants'] + $aujourdhui['etudiants'];
									$sem = $ac['semaine'];
									$journal = $app_base . '/settings/logs';
									/* Détail des groupes : « 11 personnel · 44 étudiants » (les groupes vides se taisent) */
									$groupes = array_filter([
										$sem['personnel'] ? $tdbNombre($sem['personnel']) . ' personnel' : '',
										$sem['enseignants'] ? tdbPluriel($sem['enseignants'], 'enseignant', 'enseignants') : '',
										$sem['etudiants'] ? tdbPluriel($sem['etudiants'], 'étudiant', 'étudiants') : '',
									]); ?>
								<!-- ============ ACTIVITÉ ============ -->
								<section class="tb-carte tb-activite" aria-labelledby="tbActiviteTitre">
									<div class="tb-carte__entete">
										<div>
											<h2 class="tb-carte__titre" id="tbActiviteTitre">Activité</h2>
											<p class="tb-carte__sous">Comptes actifs, d'après le journal</p>
										</div>
										<a class="tb-lien" href="<?=$journal?>" data-zone-ignorer>Journal <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
									</div>
									<dl class="tb-activite__chiffres">
										<div>
											<dt><span class="tb-activite__pouls<?= $ac['maintenant'] ? ' est-actif' : '' ?>" aria-hidden="true"></span>En ce moment</dt>
											<dd><b><?=$tdbNombre($ac['maintenant'])?></b> <small>sur les 15 dernières minutes</small></dd>
										</div>
										<div>
											<dt>Aujourd'hui</dt>
											<dd><b><?=$tdbNombre($actifsJour)?></b> <small>compte<?= $actifsJour > 1 ? 's' : '' ?> actif<?= $actifsJour > 1 ? 's' : '' ?></small></dd>
										</div>
										<div>
											<dt>7 derniers jours</dt>
											<dd><b><?=$tdbNombre($sem['total'])?></b> <small><?=$tdbNombre($ac['semaine_avant']['total'])?> la semaine d'avant</small></dd>
											<?php if ($groupes): ?><dd class="tb-activite__groupes"><?=htmlspecialchars(implode(' · ', $groupes))?></dd><?php endif; ?>
										</div>
										<div>
											<dt>Enregistrements</dt>
											<dd><b><?=$tdbNombre($ac['signaux']['ecritures'])?></b> <small>en 7 jours, <?=$tdbNombre($ac['signaux_avant']['ecritures'])?> avant</small></dd>
										</div>
									</dl>
									<div class="tb-activite__graphique" tabindex="0" role="group" aria-roledescription="graphique"
									     aria-label="Comptes actifs par jour sur 14 jours. Flèches gauche et droite : parcourir les jours.">
										<div class="tb-activite__toile" aria-hidden="true"></div>
										<p class="sr-only" aria-live="polite" data-annonce></p>
									</div>
									<p class="tb-activite__legende">
										<span><i class="tb-pastille tb-pastille--a" aria-hidden="true"></i>Personnel</span>
										<span><i class="tb-pastille tb-pastille--b" aria-hidden="true"></i>Enseignants</span>
										<span><i class="tb-pastille tb-pastille--c" aria-hidden="true"></i>Étudiants</span>
									</p>
									<ul class="tb-activite__signaux">
										<?php $echecs = $ac['signaux']['echecs']; ?>
										<li class="<?= $echecs ? 'est-alerte' : 'est-calme' ?>">
											<i class="bi <?= $echecs ? 'bi-shield-exclamation' : 'bi-shield-check' ?>" aria-hidden="true"></i>
											<?php if ($echecs): ?>
											<a href="<?=$journal?>?types=echec_connexion&amp;du=<?=$ac['du']?>" data-zone-ignorer><b><?=$tdbNombre($echecs)?></b> échec<?= $echecs > 1 ? 's' : '' ?> de connexion en 7 jours</a>
											<?php else: ?>
											<span>Aucun échec de connexion en 7 jours</span>
											<?php endif; ?>
										</li>
										<?php $erreurs = $ac['signaux']['erreurs']; ?>
										<li class="<?= $erreurs ? 'est-alerte' : 'est-calme' ?>">
											<i class="bi <?= $erreurs ? 'bi-bug' : 'bi-check2-circle' ?>" aria-hidden="true"></i>
											<?php if ($erreurs): ?>
											<a href="<?=$journal?>?du=<?=$ac['du']?>" data-zone-ignorer><b><?=$tdbNombre($erreurs)?></b> erreur<?= $erreurs > 1 ? 's' : '' ?> serveur en 7 jours</a>
											<?php else: ?>
											<span>Aucune erreur serveur en 7 jours</span>
											<?php endif; ?>
										</li>
									</ul>
									<script type="application/json" class="tb-donnees-activite"><?= json_encode(['jours' => $ac['jours'], 'depuis' => $ac['depuis']], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
								</section>
								<?php endif; ?>
							</div>
						</div>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>
	<?php /* Hors de la zone : chargé une fois, il se réinitialise à chaque « zone:chargee » */ ?>
	<script src="<?=$app_base?>/src/js/tableau-de-bord.js?v=<?=@filemtime(__DIR__ . '/js/tableau-de-bord.js')?>" defer></script>
</body>
</html>
