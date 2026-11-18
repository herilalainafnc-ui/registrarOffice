<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Emplois du temps</title>
	<?php /* La grille est passee d'un tableau a rowspan a un agenda positionne :
	         les styles ont grossi au point de ne plus avoir leur place en ligne. */ ?>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/emploi-temps.css?v=<?=@filemtime(__DIR__.'/css/emploi-temps.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<?php /* La barre d'outils des etudiants (exports, communication, badges…)
			         n'a rien a faire ici : elle egarait l'utilisateur. */ ?>

				<div class="w-full px-0.5 flex flex-1 overflow-hidden">

					<div class="edt-page my-1 mx-0.5 w-full overflow-auto" id="edtPage">

						<?php
						/* =====================================================================
						   Emploi du temps — vue, agenda, liste

						   L'annee est choisie (voir data/edt.php). La VUE dit ce que l'on
						   regarde : une classe, une salle, un enseignant, ou tout. Le filtre
						   se fait ICI, avant la disposition des seances : filtrer dans le
						   navigateur cachait des cartes mais laissait leurs voisines
						   etroites, calculees pour toutes les seances de l'annee.
						   ================================================================== */
						require_once __DIR__ . '/../data/edt.php';
						require_once __DIR__ . '/../data/vitrine.config.php';

						$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

						$edtAnnees = edtAnnees($dtb);
						$edtAnneeDefaut = edtAnneeParDefaut($dtb);
						$edtAnnee = trim((string)($_GET['annee'] ?? ''));
						if ($edtAnnee === '' || !in_array($edtAnnee, $edtAnnees, true)) {
							$edtAnnee = $edtAnneeDefaut;
						}
						$edtJours = edtJours();
						$edtModif = edtPeutModifier();

						$stSeances = $dtb->prepare(
							"SELECT e.*, sa.salle_nom, sa.salle_code AS salle_code_actuel, t.name AS prof_nom, t.lastName AS prof_prenom
							 FROM t_2024_emploi_du_temps e
							 LEFT JOIN t_2024_salles sa ON e.salle_id = sa.id
							 LEFT JOIN teacher t ON e.id_teacher = t.uid
							 WHERE e.statut = 'confirme' AND e.annee_scolaire = ?
							 ORDER BY FIELD(e.jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'),
							          e.heure_debut");
						$stSeances->execute([$edtAnnee]);
						$edtToutes = $stSeances->fetchAll(PDO::FETCH_ASSOC);
						foreach ($edtToutes as $i => $s) {
							$edtToutes[$i]['salle_code'] = $s['salle_code_actuel'] ?: $s['salle_code'];
						}

						/* Referentiels des filtres */
						$edtMentions = $dtb->query("SELECT DISTINCT dep_desc FROM t_2023_cours
						                            WHERE dep_desc <> '' AND remove != 1 ORDER BY dep_desc")->fetchAll(PDO::FETCH_COLUMN);
						$edtSalles = $dtb->query("SELECT id, salle_code, salle_nom, capacite, type_salle, batiment, etage, equipements, disponible
						                          FROM t_2024_salles ORDER BY salle_code")->fetchAll(PDO::FETCH_ASSOC);
						$edtProfs = $dtb->query("SELECT uid, name, lastName FROM teacher
						                         WHERE uid > 0 AND COALESCE(remove, 0) != 1 ORDER BY name, lastName")->fetchAll(PDO::FETCH_ASSOC);
						$edtSallesParId = array_column($edtSalles, null, 'id');
						$edtProfsParId = array_column($edtProfs, null, 'uid');

						/* La vue et ses filtres, lus dans l'adresse */
						$edtVues = ['tout' => 'Tout', 'classe' => 'Une classe', 'salle' => 'Une salle', 'enseignant' => 'Un enseignant'];
						$edtVue = (string) ($_GET['vue'] ?? 'tout');
						if (!isset($edtVues[$edtVue])) { $edtVue = 'tout'; }
						$edtF = [
							'mention'  => trim((string) ($_GET['mention'] ?? '')),
							'niveau'   => trim((string) ($_GET['niveau'] ?? '')),
							'semestre' => in_array((string) ($_GET['semestre'] ?? ''), ['1', '2'], true) ? (string) $_GET['semestre'] : '',
							'type'     => in_array((string) ($_GET['type'] ?? ''), ['cours', 'td', 'tp', 'examen'], true) ? (string) $_GET['type'] : '',
							'salle'    => (int) ($_GET['salle'] ?? 0),
							'prof'     => (int) ($_GET['prof'] ?? 0),
						];
						$edtNiveaux = ['0' => 'Remise à niveau', '1' => 'L1', '2' => 'L2', '3' => 'L3', '4' => 'M1', '5' => 'M2'];
						if (!in_array($edtF['mention'], $edtMentions, true)) { $edtF['mention'] = ''; }
						if (!isset($edtNiveaux[$edtF['niveau']])) { $edtF['niveau'] = ''; }
						/* Chaque vue montre aussitot quelque chose : sans choix, la premiere
						   classe, la premiere salle, le premier enseignant qui a cours. */
						if ($edtVue === 'classe') {
							if ($edtF['mention'] === '') { $edtF['mention'] = $edtMentions[0] ?? ''; }
							if ($edtF['niveau'] === '') { $edtF['niveau'] = '1'; }
						}
						if ($edtVue === 'salle' && !isset($edtSallesParId[$edtF['salle']])) {
							$edtF['salle'] = (int) ($edtSalles[0]['id'] ?? 0);
						}
						if ($edtVue === 'enseignant' && !isset($edtProfsParId[$edtF['prof']])) {
							$avecCours = array_values(array_filter(array_map(fn ($s) => (int) $s['id_teacher'], $edtToutes), fn ($u) => isset($edtProfsParId[$u])));
							$edtF['prof'] = $avecCours[0] ?? (int) ($edtProfs[0]['uid'] ?? 0);
						}

						$edtGarde = static function (array $s) use ($edtVue, $edtF) : bool {
							if ($edtF['semestre'] !== '' && (string) $s['semester'] !== $edtF['semestre']) { return false; }
							if ($edtF['type'] !== '' && $s['type_seance'] !== $edtF['type']) { return false; }
							switch ($edtVue) {
								case 'classe':     return $s['mention'] === $edtF['mention'] && (string) $s['niveau'] === $edtF['niveau'];
								case 'salle':      return (int) $s['salle_id'] === $edtF['salle'];
								case 'enseignant': return (int) $s['id_teacher'] === $edtF['prof'];
								default:           return ($edtF['mention'] === '' || $s['mention'] === $edtF['mention'])
								                       && ($edtF['niveau'] === '' || (string) $s['niveau'] === $edtF['niveau']);
							}
						};
						$edtSeances = array_values(array_filter($edtToutes, $edtGarde));

						/* Conflits deja enregistres, calcules sur toute l'annee : une seance
						   peut entrer en conflit avec une autre qui n'est pas dans la vue. */
						$edtConflits = edtConflitsEntre($edtToutes);
						/* Une seance restee dans une salle mise hors service est a deplacer. */
						foreach ($edtToutes as $s) {
							$sa = $edtSallesParId[(int) $s['salle_id']] ?? null;
							if ($sa && (int) $sa['disponible'] !== 1) {
								$edtConflits[(int) $s['id']][] = ['severity' => 'error',
									'message' => 'Salle ' . ($sa['salle_code'] ?: $sa['salle_nom']) . ' mise hors service : séance à déplacer'];
							}
						}
						// Occupation de chaque salle sur l'annee, pour la fenetre des salles.
						$edtSeancesParSalle = array_count_values(array_map(fn ($s) => (int) $s['salle_id'], $edtToutes));
						// Formulaire des salles : longueurs et types lus dans la table.
						$edtColonnesSalle = $edtModif ? edtColonnesSalles($dtb) : [];
						$edtTypesSalle = $edtModif ? edtTypesSalle($dtb, $edtColonnesSalle) : [];
						$edtLibTypeSalle = static fn ($t) => ['amphi' => 'Amphithéâtre', 'td' => 'Salle de TD', 'tp' => 'Salle de TP',
							'labo' => 'Laboratoire', 'autre' => 'Autre'][(string) $t] ?? (string) $t;
						$edtLibEtage = static function ($n) : string {
							if ($n === null || trim((string) $n) === '') { return ''; }
							$n = (int) $n;
							return $n === 0 ? 'rez-de-chaussée' : ($n === 1 ? '1er étage' : $n . 'e étage');
						};

						/* Ce que l'on regarde, en toutes lettres */
						$nomProf = static fn (array $p) => trim(($p['name'] ?? '') . ' ' . ($p['lastName'] ?? ''));
						switch ($edtVue) {
							case 'classe':
								$edtTitre = $edtF['mention'] . ' · ' . ($edtNiveaux[$edtF['niveau']] ?? $edtF['niveau']);
								break;
							case 'salle':
								$sa = $edtSallesParId[$edtF['salle']] ?? null;
								$edtTitre = $sa ? 'Salle ' . ($sa['salle_code'] ?: $sa['salle_nom'])
									. ((int) $sa['capacite'] > 0 ? ' · ' . (int) $sa['capacite'] . ' places' : '')
									. ((int) $sa['disponible'] !== 1 ? ' · indisponible' : '') : 'Aucune salle';
								break;
							case 'enseignant':
								$edtTitre = isset($edtProfsParId[$edtF['prof']]) ? $nomProf($edtProfsParId[$edtF['prof']]) : 'Aucun enseignant';
								break;
							default:
								$morceaux = array_filter([$edtF['mention'], $edtNiveaux[$edtF['niveau']] ?? '']);
								$edtTitre = $morceaux ? implode(' · ', $morceaux) : 'Toutes les classes';
						}
						$edtPrecision = array_filter([
							$edtF['semestre'] !== '' ? 'Semestre ' . $edtF['semestre'] : 'Les deux semestres',
							['cours' => 'Cours', 'td' => 'TD', 'tp' => 'TP', 'examen' => 'Examens'][$edtF['type']] ?? '',
						]);
						$edtMinutesTotales = array_sum(array_map(fn ($s) => max(0, (edtMinutes($s['heure_fin']) ?? 0) - (edtMinutes($s['heure_debut']) ?? 0)), $edtSeances));
						$edtEnConflit = array_values(array_filter($edtSeances, fn ($s) => !empty($edtConflits[(int) $s['id']])));

						/* Bornes deduites des seances affichees, 08–17 par defaut : la grille
						   reste dessinee meme vide, pour qu'on puisse y cliquer un creneau. */
						[$edtDebutMin, $edtFinMin] = edtPlage($edtSeances);
						$edtHauteurHeure = 62;   // pixels ; la feuille de styles lit cette valeur

						// Une journee par colonne, chacune disposee en colonnes de chevauchement.
						$edtParJour = array_fill_keys($edtJours, []);
						foreach ($edtSeances as $s) {
							if (isset($edtParJour[$s['jour_semaine']])) {
								$edtParJour[$s['jour_semaine']][] = $s;
							}
						}
						foreach ($edtParJour as $j => $liste) {
							$edtParJour[$j] = edtDisposerJournee($liste);
						}

						/** Position verticale, en pixels, d'une minute de la journee. */
						$edtY = static function (int $minutes) use ($edtDebutMin, $edtHauteurHeure) : float {
							return ($minutes - $edtDebutMin) / 60 * $edtHauteurHeure;
						};

						/* Le jour et l'heure de Madagascar, pas ceux du serveur (UTC en
						   production) : voir edtMaintenant() dans data/edt.php. */
						$edtMaintenant = edtMaintenant();
						$edtAujourdhui = ['Sunday'=>'', 'Monday'=>'Lundi', 'Tuesday'=>'Mardi',
						                  'Wednesday'=>'Mercredi', 'Thursday'=>'Jeudi',
						                  'Friday'=>'Vendredi', 'Saturday'=>'Samedi'][$edtMaintenant->format('l')] ?? '';
						?>

						<!-- En-tête -->
						<header class="edt-tete">
							<div>
								<h1><i class="bi bi-calendar3-week" aria-hidden="true"></i> Emplois du temps</h1>
								<?php /* Le resume depend de l'annee : il porte un identifiant pour que la
								         bascule d'annee le remplace sans recharger la page. */ ?>
								<p id="edtResume"><?= count($edtToutes) ?> séance<?= count($edtToutes) > 1 ? 's' : '' ?> enregistrée<?= count($edtToutes) > 1 ? 's' : '' ?> pour l'année <?= $e($edtAnnee) ?></p>
							</div>
							<div class="edt-tete__actions">
								<button type="button" onclick="edtOuvrirSalles()" class="edt-btn edt-btn--second"><i class="bi bi-door-open" aria-hidden="true"></i> Salles</button>
								<button type="button" onclick="openExportModal()" class="edt-btn edt-btn--second"><i class="bi bi-download" aria-hidden="true"></i> Exporter</button>
								<?php if ($edtModif): ?>
								<button type="button" onclick="openAddModal()" class="edt-btn edt-btn--primaire"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une séance</button>
								<?php endif; ?>
							</div>
						</header>

						<?php /* Les filtres forment un formulaire GET : l'adresse les porte
						         (/schedule?vue=classe&mention=INFO&niveau=1), elle reste copiable et
						         le bouton « precedent » les retrouve. Le script de la page les
						         applique sans recharger (edtAppliquerVue). */ ?>
						<form class="edt-filtres" id="edtFiltres" method="get" action="">
							<fieldset class="edt-vues">
								<legend>Afficher</legend>
								<?php foreach ($edtVues as $cle => $libelle): ?>
								<label><input type="radio" name="vue" value="<?= $cle ?>"<?= $cle === $edtVue ? ' checked' : '' ?>><span><?= $e($libelle) ?></span></label>
								<?php endforeach; ?>
							</fieldset>

							<div class="edt-filtres__champs">
								<?php /* Changer d'annee se fait en AJAX, mais l'adresse suit.
								         data-annee : celle a l'ecran, pour revenir en arriere si un
								         rafraichissement echoue ; data-defaut : celle que le serveur
								         choisit sans parametre. */ ?>
								<label class="edt-champ-bloc">Année
									<select id="filterAnnee" name="annee" class="edt-champ" data-annee="<?= $e($edtAnnee) ?>" data-defaut="<?= $e($edtAnneeDefaut) ?>">
										<?php foreach ($edtAnnees as $an): ?>
										<option value="<?= $e($an) ?>"<?= $an === $edtAnnee ? ' selected' : '' ?>><?= $e($an) ?><?= edtCompteSeances($dtb, $an) === 0 ? ' (vide)' : '' ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="edt-champ-bloc">Semestre
									<select name="semestre" id="filterSemester" class="edt-champ">
										<option value="">Les deux</option>
										<option value="1"<?= $edtF['semestre'] === '1' ? ' selected' : '' ?>>Semestre 1</option>
										<option value="2"<?= $edtF['semestre'] === '2' ? ' selected' : '' ?>>Semestre 2</option>
									</select>
								</label>
								<label class="edt-champ-bloc" data-vues="tout classe">Mention
									<select name="mention" id="filterMention" class="edt-champ">
										<option value="" data-vues="tout">Toutes</option>
										<?php foreach ($edtMentions as $m): ?>
										<option value="<?= $e($m) ?>"<?= $m === $edtF['mention'] ? ' selected' : '' ?>><?= $e($m) ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="edt-champ-bloc" data-vues="tout classe">Niveau
									<select name="niveau" id="filterNiveau" class="edt-champ">
										<option value="" data-vues="tout">Tous</option>
										<?php foreach ($edtNiveaux as $n => $lib): ?>
										<option value="<?= $n ?>"<?= (string) $n === $edtF['niveau'] ? ' selected' : '' ?>><?= $e($lib) ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="edt-champ-bloc" data-vues="salle">Salle
									<select name="salle" id="filterSalle" class="edt-champ">
										<?php foreach ($edtSalles as $sa): ?>
										<option value="<?= (int) $sa['id'] ?>"<?= (int) $sa['id'] === $edtF['salle'] ? ' selected' : '' ?>><?= $e(($sa['salle_code'] ?: $sa['salle_nom']) . ((int) $sa['capacite'] > 0 ? ' · ' . (int) $sa['capacite'] . ' places' : '') . ((int) $sa['disponible'] !== 1 ? ' · indisponible' : '')) ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="edt-champ-bloc edt-champ-bloc--large" data-vues="enseignant">Enseignant
									<select name="prof" id="filterProf" class="edt-champ">
										<?php foreach ($edtProfs as $p): ?>
										<option value="<?= (int) $p['uid'] ?>"<?= (int) $p['uid'] === $edtF['prof'] ? ' selected' : '' ?>><?= $e($nomProf($p)) ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="edt-champ-bloc">Type
									<select name="type" id="filterTypeSeance" class="edt-champ">
										<option value="">Tous</option>
										<option value="cours"<?= $edtF['type'] === 'cours' ? ' selected' : '' ?>>Cours</option>
										<option value="td"<?= $edtF['type'] === 'td' ? ' selected' : '' ?>>TD</option>
										<option value="tp"<?= $edtF['type'] === 'tp' ? ' selected' : '' ?>>TP</option>
										<option value="examen"<?= $edtF['type'] === 'examen' ? ' selected' : '' ?>>Examens</option>
									</select>
								</label>
								<noscript><button type="submit" class="edt-btn edt-btn--second">Afficher</button></noscript>
							</div>
						</form>

						<?php /* Ce que montre la grille, en toutes lettres, et ce qui cloche. */ ?>
						<div class="edt-contexte" id="edtContexte">
							<div class="edt-contexte__titre">
								<h2><?= $e($edtTitre) ?></h2>
								<span><?= $e(implode(' · ', $edtPrecision)) ?> · <?= count($edtSeances) ?> séance<?= count($edtSeances) > 1 ? 's' : '' ?>, <?= $e(rtrim(rtrim(number_format($edtMinutesTotales / 60, 1, ',', ''), '0'), ',')) ?> h par semaine</span>
							</div>
							<?php if ($edtEnConflit): ?>
							<div class="edt-alerte" role="status">
								<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
								<div>
									<b><?= count($edtEnConflit) ?> séance<?= count($edtEnConflit) > 1 ? 's' : '' ?> en conflit dans cette vue</b> — marquée<?= count($edtEnConflit) > 1 ? 's' : '' ?> d'un triangle sur la grille.
									<ul>
										<?php foreach (array_slice($edtEnConflit, 0, 4) as $s): ?>
										<li><?= $e($s['cours_sigle'] . ', ' . strtolower($s['jour_semaine']) . ' ' . substr($s['heure_debut'], 0, 5) . ' : ' . implode(' ; ', array_column($edtConflits[(int) $s['id']], 'message'))) ?></li>
										<?php endforeach; ?>
										<?php if (count($edtEnConflit) > 4): ?><li>… et <?= count($edtEnConflit) - 4 ?> autre(s).</li><?php endif; ?>
									</ul>
								</div>
							</div>
							<?php endif; ?>
						</div>

						<!-- Légende -->
						<div class="edt-legende">
							<span class="edt-legende__titre">Mentions</span>
							<?php foreach ([
								'THEO' => '#4f46e5', 'GEST' => '#d97706', 'INFO' => '#0891b2', 'NURS' => '#db2777',
								'EDUC' => '#059669', 'COMM' => '#7c3aed', 'LANG' => '#ea580c', 'DROI' => '#dc2626',
							] as $code => $couleur): ?>
							<span><span class="legend-box" style="background: <?= $couleur ?>;"></span><?= $code ?></span>
							<?php endforeach; ?>
							<span class="edt-legende__titre">Liseré</span>
							<span><span class="legend-box legend-box--cours"></span>Cours</span>
							<span><span class="legend-box" style="background:#c4b5fd;"></span>TD</span>
							<span><span class="legend-box" style="background:#6ee7b7;"></span>TP</span>
							<span><span class="legend-box" style="background:#fca5a5;"></span>Examen</span>
						</div>
						<p class="edt-aide">
							<i class="bi bi-hand-index" aria-hidden="true"></i>
							<?php if ($edtModif): ?>
							Cliquez sur un <b>créneau libre</b> pour y ajouter une séance, sur une <b>séance</b> pour la modifier ; glissez-la pour la déplacer.
							<?php else: ?>
							Consultation seule : l'emploi du temps se modifie au registrariat. Survolez une séance pour son détail.
							<?php endif; ?>
						</p>

						<?php /* =====================================================================
						   L'agenda

						   Chaque seance est posee selon son debut et sa duree reels ; celles
						   qui se chevauchent se partagent la largeur de la journee. Une couche
						   de creneaux d'une demi-heure couvre chaque colonne : on y depose une
						   carte glissee, on y clique pour ajouter une seance a cet horaire.
						   La grille est dessinee meme vide.
						   ================================================================== */ ?>
						<?php /* Un conteneur unique : c'est ce noeud que le rafraichissement remplace. */ ?>
						<div id="edtZone">
						<?php if (!$edtSeances): ?>
						<div class="edt-vide-bandeau">
							<i class="bi bi-calendar-x" aria-hidden="true"></i>
							Aucune séance pour <?= $e($edtTitre) ?><?= $edtF['semestre'] !== '' ? ' au semestre ' . $e($edtF['semestre']) : '' ?>.
							<?php if ($edtModif): ?>Cliquez sur un créneau de la grille pour en ajouter une.<?php endif; ?>
						</div>
						<?php endif; ?>
						<div class="edt-defile">
							<div class="edt<?= $edtModif ? ' edt--modifiable' : '' ?>" id="edtGrille"
							     style="--edt-jours: <?= count($edtJours) ?>; --edt-heure: <?= $edtHauteurHeure ?>px;"
							     data-debut-min="<?= $edtDebutMin ?>"
							     data-heure-px="<?= $edtHauteurHeure ?>"
							     data-vue="<?= $e($edtVue) ?>"
							     data-mention="<?= $e($edtF['mention']) ?>"
							     data-niveau="<?= $e($edtF['niveau']) ?>"
							     data-salle="<?= $edtVue === 'salle' ? (int) $edtF['salle'] : '' ?>"
							     data-prof="<?= $edtVue === 'enseignant' ? (int) $edtF['prof'] : '' ?>">

								<div class="edt__entete">
									<div class="edt__jour"></div>
									<?php foreach ($edtJours as $j): ?>
									<div class="edt__jour<?= $j === $edtAujourdhui ? ' edt__jour--aujourdhui' : '' ?>" data-jour="<?= $e($j) ?>">
										<?= $e($j) ?>
										<small><?= count($edtParJour[$j]) ?> séance<?= count($edtParJour[$j]) > 1 ? 's' : '' ?></small>
									</div>
									<?php endforeach; ?>
								</div>

								<div class="edt__corps" style="height: <?= ($edtFinMin - $edtDebutMin) / 60 * $edtHauteurHeure ?>px;">

									<div class="edt__heures">
										<?php for ($m = $edtDebutMin; $m < $edtFinMin; $m += 60): ?>
										<div class="edt__heure" style="top: <?= $edtY($m) ?>px;"><?= edtHeure($m) ?></div>
										<?php endfor; ?>
									</div>

									<?php foreach ($edtJours as $jour): ?>
									<div class="edt__colonne<?= $jour === 'Samedi' ? ' edt__colonne--weekend' : '' ?>"
									     data-jour="<?= $e($jour) ?>">

										<?php /* Creneaux d'une demi-heure : depot d'une carte, clic pour ajouter. */ ?>
										<?php for ($m = $edtDebutMin; $m < $edtFinMin; $m += 30): ?>
										<div class="edt__creneau"
										     style="top: <?= $edtY($m) ?>px; height: <?= $edtHauteurHeure / 2 ?>px;"
										     data-jour="<?= $e($jour) ?>"
										     data-heure="<?= edtHeure($m) ?>"<?= $edtModif ? ' title="Ajouter une séance le ' . $e(strtolower($jour)) . ' à ' . edtHeure($m) . '"' : '' ?>></div>
										<?php endfor; ?>

										<?php if ($jour === $edtAujourdhui):
											$maintenant = (int) $edtMaintenant->format('G') * 60 + (int) $edtMaintenant->format('i');
											if ($maintenant >= $edtDebutMin && $maintenant <= $edtFinMin): ?>
										<div class="edt__maintenant" style="top: <?= $edtY($maintenant) ?>px;"
										     title="Il est <?= $edtMaintenant->format('H:i') ?>"></div>
										<?php endif; endif; ?>

										<?php foreach ($edtParJour[$jour] as $s):
											$duree = $s['fin_min'] - $s['debut_min'];
											$larg  = 100 / max(1, $s['nb_colonnes']);
											$haut  = max(18, $duree / 60 * $edtHauteurHeure - 3);
											$courte = $duree < 75;
											$prof = trim(($s['prof_nom'] ?? '') . ' ' . ($s['prof_prenom'] ?? ''));
											$conflits = $edtConflits[(int) $s['id']] ?? [];
											$bloquant = (bool) array_filter($conflits, fn ($c) => $c['severity'] === 'error');
											$classe = ($s['mention'] ?? '') . ' ' . edtNiveauCourt($s['niveau']);
											$detail = $s['cours_sigle'] . ' — ' . $s['cours_title'] . "\n"
												. substr($s['heure_debut'], 0, 5) . ' – ' . substr($s['heure_fin'], 0, 5) . "\n"
												. ($s['salle_nom'] ?: $s['salle_code'] ?: 'Salle non précisée') . "\n"
												. $classe . ($prof !== '' ? "\n" . $prof : '')
												. ($conflits ? "\n\n⚠ " . implode("\n⚠ ", array_column($conflits, 'message')) : '');
										?>
										<div class="edt-carte<?= $courte ? ' edt-carte--courte' : '' ?><?= $conflits ? ($bloquant ? ' edt-carte--conflit' : ' edt-carte--alerte') : '' ?>"
										     draggable="<?= $edtModif ? 'true' : 'false' ?>"
										     tabindex="0"
										     <?= $edtModif ? 'role="button"' : '' ?>
										     data-id="<?= (int) $s['id'] ?>"
										     data-mention="<?= $e($s['mention']) ?>"
										     data-niveau="<?= $e($s['niveau']) ?>"
										     data-semester="<?= $e($s['semester']) ?>"
										     data-type="<?= $e($s['type_seance']) ?>"
										     data-parcours="<?= $e($s['parcours'] ?? '') ?>"
										     data-duree-min="<?= (int) $duree ?>"
										     style="top: <?= $edtY($s['debut_min']) ?>px;
										            height: <?= $haut ?>px;
										            left: calc(<?= $s['colonne'] * $larg ?>% + 2px);
										            width: calc(<?= $larg ?>% - 4px);"
										     title="<?= $e($detail) ?>"
										     aria-label="<?= $e($s['cours_sigle'] . ', ' . $s['jour_semaine'] . ' ' . substr($s['heure_debut'],0,5) . ' à ' . substr($s['heure_fin'],0,5) . ($conflits ? ', en conflit' : '') . ($edtModif ? '. Entrée pour modifier.' : '')) ?>">
											<?php if ($conflits): ?><i class="bi bi-exclamation-triangle-fill edt-carte__alerte" aria-hidden="true"></i><?php endif; ?>
											<span class="edt-carte__sigle"><?= $e($s['cours_sigle']) ?></span>
											<span class="edt-carte__titre"><?= $e($s['cours_title']) ?></span>
											<span class="edt-carte__ligne"><?= $e(substr($s['heure_debut'],0,5)) ?> – <?= $e(substr($s['heure_fin'],0,5)) ?></span>
											<span class="edt-carte__ligne"><i class="bi bi-geo-alt-fill"></i> <?= $e($s['salle_nom'] ?: ($s['salle_code'] ?: '—')) ?></span>
											<span class="edt-carte__ligne"><?= $e($classe) ?></span>
											<?php if (!empty($s['parcours']) && strtolower($s['parcours']) !== 'all'): ?>
											<span class="edt-carte__badge"><?= $e($s['parcours']) ?></span>
											<?php endif; ?>
										</div>
										<?php endforeach; ?>
									</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						</div><!-- #edtZone -->

						<!-- Liste des séances -->
						<h2 class="edt-sous-titre"><i class="bi bi-list-ul" aria-hidden="true"></i> Les séances de cette vue</h2>
						<table class="simpleTbl edt-liste">
							<thead>
								<tr>
									<th>Sigle</th>
									<th>Titre</th>
									<th>Type</th>
									<th>Jour</th>
									<th>Horaire</th>
									<th>Salle</th>
									<th>Enseignant</th>
									<th>Classe</th>
									<?php if ($edtModif): ?><th><span class="sr-only">Actions</span></th><?php endif; ?>
								</tr>
							</thead>
							<tbody id="edtListe">
								<?php if (!$edtSeances): ?>
								<tr><td colspan="<?= $edtModif ? 9 : 8 ?>" class="edt-liste__vide">Aucune séance dans cette vue.</td></tr>
								<?php endif; ?>
								<?php foreach ($edtSeances as $c):
									$prof = trim(($c['prof_nom'] ?? '') . ' ' . ($c['prof_prenom'] ?? ''));
									$conflits = $edtConflits[(int) $c['id']] ?? [];
								?>
								<tr class="course-row<?= $conflits ? ' edt-liste__conflit' : '' ?>" data-id="<?= (int) $c['id'] ?>">
									<td class="edt-liste__sigle"><?php if ($conflits): ?><i class="bi bi-exclamation-triangle-fill" title="<?= $e(implode("\n", array_column($conflits, 'message'))) ?>"></i> <?php endif; ?><?= $e($c['cours_sigle']) ?></td>
									<td><?= $e($c['cours_title']) ?></td>
									<td><span class="edt-type edt-type--<?= $e($c['type_seance']) ?>"><?= $e(['cours' => 'Cours', 'td' => 'TD', 'tp' => 'TP', 'examen' => 'Examen'][$c['type_seance']] ?? $c['type_seance']) ?></span></td>
									<td><?= $e($c['jour_semaine']) ?></td>
									<td class="edt-liste__heure"><?= $e(substr($c['heure_debut'], 0, 5)) ?> – <?= $e(substr($c['heure_fin'], 0, 5)) ?></td>
									<td><?= $e($c['salle_nom'] ?: ($c['salle_code'] ?: '—')) ?></td>
									<td><?= $prof !== '' ? $e($prof) : '—' ?></td>
									<td><?= $e($c['mention'] . ' ' . edtNiveauCourt($c['niveau'])) ?><?= !empty($c['parcours']) && strtolower($c['parcours']) !== 'all' ? ' <small>' . $e($c['parcours']) . '</small>' : '' ?></td>
									<?php if ($edtModif): ?>
									<td class="edt-liste__actions">
										<button type="button" onclick="openEditModal(<?= (int) $c['id'] ?>)" title="Modifier" aria-label="Modifier <?= $e($c['cours_sigle']) ?>"><i class="bi bi-pencil"></i></button>
										<button type="button" onclick="deleteSeance(<?= (int) $c['id'] ?>)" class="edt-liste__supprimer" title="Supprimer" aria-label="Supprimer <?= $e($c['cours_sigle']) ?>"><i class="bi bi-trash"></i></button>
									</td>
									<?php endif; ?>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>

		</div>

	</div>

	<!-- Modal Ajouter une séance -->
	<div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
		<div class="bg-slate-800 rounded-lg w-full max-w-2xl mx-4 max-h-[90vh] overflow-auto">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 class="text-lg font-semibold text-white"><i class="bi bi-plus-circle text-cyan-500"></i> Ajouter une séance</h3>
				<button onclick="closeModal()" class="text-slate-400 hover:text-white"><i class="bi bi-x-lg"></i></button>
			</div>
			
			<form id="addSeanceForm" class="p-4">
				<div class="grid grid-cols-2 gap-4">
					<!-- Sélection du cours -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Cours * <span class="text-slate-500">(700+ cours disponibles)</span></label>
						
						<!-- Champ de recherche -->
						<div class="relative mb-2">
							<i class="bi bi-search absolute left-3 top-2.5 text-slate-400"></i>
							<input type="text" id="searchCours" placeholder="Rechercher par sigle, titre ou mot-clé..." 
								class="w-full bg-slate-700 text-white text-sm pl-9 pr-3 py-2 rounded border border-slate-600 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
								autocomplete="off">
							<span id="searchCoursCount" class="absolute right-3 top-2.5 text-xs text-slate-500"></span>
						</div>
						
						<div class="flex gap-2 mb-2">
							<select id="filterCourseMention" class="bg-slate-600 text-white text-xs px-2 py-1 rounded">
								<option value="">Filtre mention</option>
								<?php
								$allMentions = $dtb->query("SELECT DISTINCT dep_desc FROM t_2023_cours WHERE dep_desc != '' AND remove != 1 ORDER BY dep_desc");
								while($m = $allMentions->fetch()) {
									echo '<option value="'.$m['dep_desc'].'">'.$m['dep_desc'].'</option>';
								}
								?>
							</select>
							<select id="filterCourseNiveau" class="bg-slate-600 text-white text-xs px-2 py-1 rounded">
								<option value="">Filtre niveau</option>
								<option value="1">L1</option>
								<option value="2">L2</option>
								<option value="3">L3</option>
								<option value="4">M1</option>
								<option value="5">M2</option>
							</select>
							<button type="button" id="resetFilters" class="bg-slate-600 hover:bg-slate-500 text-white text-xs px-2 py-1 rounded" title="Réinitialiser">
								<i class="bi bi-x-lg"></i>
							</button>
						</div>
						<select id="coursSelect" name="cours_id" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600" size="8" style="height: auto;">
							<option value="">-- Sélectionner un cours --</option>
						</select>
						<p class="text-slate-500 text-xs mt-1"><i class="bi bi-info-circle"></i> Tapez au moins 2 caractères pour rechercher</p>
					</div>
					
					<!-- Parcours concerné -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Parcours concerné</label>
						<select name="parcours" id="parcoursSelect" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Tous les parcours de la mention --</option>
						</select>
						<p class="text-slate-500 text-xs mt-1"><i class="bi bi-info-circle"></i> Laissez vide si le cours est pour tous les parcours</p>
					</div>
					
					<!-- Type de séance -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Type de séance *</label>
						<select name="type_seance" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="cours">Cours magistral</option>
							<option value="td">TD</option>
							<option value="tp">TP</option>
							<option value="examen">Examen</option>
						</select>
					</div>
					
					<!-- Jour -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Jour *</label>
						<select name="jour_semaine" id="jourSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Jour --</option>
							<option value="Lundi">Lundi</option>
							<option value="Mardi">Mardi</option>
							<option value="Mercredi">Mercredi</option>
							<option value="Jeudi">Jeudi</option>
							<option value="Vendredi">Vendredi</option>
							<option value="Samedi">Samedi</option>
						</select>
					</div>
					
					<!-- Heure début -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure début *</label>
						<select name="heure_debut" id="heureDebutSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<?php /* Toutes les demi-heures : une seance deposee a 08:30 gardait
							         son horaire dans la grille mais perdait ses trente minutes a
							         l'enregistrement de sa fiche, faute d'option a 08:30. */ ?>
							<option value="">-- Heure --</option>
							<?php for ($m = 420; $m <= 1230; $m += 30): ?><option value="<?= edtHeure($m) ?>"><?= edtHeure($m) ?></option><?php endfor; ?>
						</select>
					</div>
					
					<!-- Heure fin -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure fin *</label>
						<select name="heure_fin" id="heureFinSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<?php /* Toutes les demi-heures : une seance deposee a 08:30 gardait
							         son horaire dans la grille mais perdait ses trente minutes a
							         l'enregistrement de sa fiche, faute d'option a 08:30. */ ?>
							<option value="">-- Heure --</option>
							<?php for ($m = 450; $m <= 1260; $m += 30): ?><option value="<?= edtHeure($m) ?>"><?= edtHeure($m) ?></option><?php endfor; ?>
						</select>
					</div>
					
					<!-- Salle -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Salle *</label>
						<select name="salle_id" id="salleSelect" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Sélectionner --</option>
						</select>
					</div>
					
					<!-- Enseignant -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Enseignant</label>
						<select name="id_teacher" id="teacherSelect" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Enseignant du cours --</option>
						</select>
					</div>
					
					<!-- Remarque -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Remarque</label>
						<input type="text" name="remarque" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600" placeholder="Optionnel...">
					</div>
				</div>
				
				<!-- Zone conflits -->
				<div id="conflictsZone" class="mt-4 hidden">
					<div class="bg-slate-900 rounded p-3">
						<h4 class="text-sm font-semibold text-white mb-2"><i class="bi bi-exclamation-triangle text-yellow-500"></i> Conflits détectés</h4>
						<div id="conflictsList"></div>
					</div>
				</div>
				
				<!-- Boutons -->
				<div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-700">
					<button type="button" onclick="checkConflicts()" class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-2 rounded">
						<i class="bi bi-search"></i> Vérifier conflits
					</button>
					<button type="button" onclick="closeModal()" class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-2 rounded">Annuler</button>
					<button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white text-sm px-4 py-2 rounded">
						<i class="bi bi-check-lg"></i> Ajouter
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal Exporter emploi du temps -->
	<div id="exportModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
		<div class="bg-slate-800 rounded-lg w-full max-w-md mx-4">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 class="text-lg font-semibold text-white"><i class="bi bi-download text-emerald-500"></i> Exporter l'emploi du temps</h3>
				<button onclick="closeExportModal()" class="text-slate-400 hover:text-white"><i class="bi bi-x-lg"></i></button>
			</div>
			
			<div class="p-4">
				<!-- Sélection de la mention -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Mention</label>
					<select id="exportMention" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Toutes les mentions --</option>
						<?php
						/* Meme annee que la grille, et valeurs echappees : la mention vient
						   de la base et etait ecrite telle quelle dans l'attribut value. */
						$stME = $dtb->prepare("SELECT DISTINCT mention FROM t_2024_emploi_du_temps
						                       WHERE mention <> '' AND annee_scolaire = ? ORDER BY mention");
						$stME->execute([$edtAnnee]);
						foreach ($stME->fetchAll(PDO::FETCH_COLUMN) as $me) {
							$x = htmlspecialchars((string) $me, ENT_QUOTES, 'UTF-8');
							echo '<option value="' . $x . '">' . $x . '</option>';
						}
						?>
					</select>
				</div>
				
				<!-- Sélection du type de séance -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Type de séance</label>
					<select id="exportType" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Tous les types --</option>
						<option value="cours">Cours magistraux</option>
						<option value="td">TD</option>
						<option value="tp">TP</option>
						<option value="examen">Examens</option>
					</select>
				</div>
				
				<!-- Sélection du niveau -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Niveau</label>
					<select id="exportNiveau" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Tous les niveaux --</option>
						<option value="1">L1</option>
						<option value="2">L2</option>
						<option value="3">L3</option>
						<option value="4">M1</option>
						<option value="5">M2</option>
					</select>
				</div>
				
				<!-- Boutons d'export -->
				<div class="flex gap-3 mt-6">
					<button onclick="exportScheduleToPDF()" class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2.5 rounded flex items-center justify-center gap-2">
						<i class="bi bi-file-earmark-pdf"></i> Exporter en PDF
					</button>
					<button onclick="exportScheduleToExcel()" class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2.5 rounded flex items-center justify-center gap-2">
						<i class="bi bi-file-earmark-excel"></i> Exporter en Excel
					</button>
				</div>
			</div>
		</div>
	</div>

	<?php /* =========================================================================
	   Salles : la liste pour tous ; ajout, modification et mise hors service
	   pour le registrariat. La capacite et la disponibilite servent a la
	   detection des conflits : elles n'etaient modifiables qu'en base.
	   ====================================================================== */ ?>
	<div id="sallesModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50" role="dialog" aria-modal="true" aria-labelledby="sallesTitre">
		<div class="bg-slate-800 rounded-lg w-full max-w-3xl mx-4 max-h-[90vh] overflow-auto">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 id="sallesTitre" class="text-lg font-semibold text-white"><i class="bi bi-door-open text-cyan-500" aria-hidden="true"></i> Salles</h3>
				<button type="button" onclick="edtFermerSalles()" class="text-slate-400 hover:text-white" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
			</div>

			<div class="p-4">
				<?php /* Remplace a chaque rafraichissement, comme la grille. */ ?>
				<div id="edtSallesCorps">
					<?php $nbDispo = count(array_filter($edtSalles, fn ($sa) => (int) $sa['disponible'] === 1)); ?>
					<div class="edt-salles__tete">
						<p><?= count($edtSalles) ?> salles, dont <?= $nbDispo ?> disponible<?= $nbDispo > 1 ? 's' : '' ?>. Séances comptées pour <?= $e($edtAnnee) ?>.</p>
						<?php if ($edtModif): ?>
						<button type="button" onclick="edtNouvelleSalle()" class="edt-btn edt-btn--primaire"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une salle</button>
						<?php endif; ?>
					</div>
					<div class="edt-salles__defile">
					<table class="simpleTbl edt-salles__table">
						<thead>
							<tr>
								<th>Salle</th>
								<th>Places</th>
								<th>Type</th>
								<th>Lieu</th>
								<th>Séances</th>
								<th>État</th>
								<th><span class="sr-only">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($edtSalles as $sa):
								$code = (string) ($sa['salle_code'] ?: $sa['salle_nom']);
								$batiment = trim((string) $sa['batiment']);
								$etage = $edtLibEtage($sa['etage']);
								$dispo = (int) $sa['disponible'] === 1;
								$nb = $edtSeancesParSalle[(int) $sa['id']] ?? 0;
							?>
							<tr class="<?= $dispo ? '' : 'edt-salles__hs' ?>"
							    data-salle="<?= $e(json_encode([
							        'id' => (int) $sa['id'], 'salle_code' => $sa['salle_code'], 'salle_nom' => $sa['salle_nom'],
							        'capacite' => (int) $sa['capacite'], 'type_salle' => $sa['type_salle'], 'batiment' => $sa['batiment'],
							        'etage' => $sa['etage'], 'equipements' => $sa['equipements'], 'disponible' => $dispo ? 1 : 0,
							    ], JSON_UNESCAPED_UNICODE)) ?>">
								<td>
									<span class="edt-salles__code"><?= $e($code) ?></span>
									<?php if ($sa['salle_nom'] && $sa['salle_nom'] !== $sa['salle_code']): ?><small><?= $e($sa['salle_nom']) ?></small><?php endif; ?>
									<?php if (trim((string) $sa['equipements']) !== ''): ?><small class="edt-salles__equip"><?= $e($sa['equipements']) ?></small><?php endif; ?>
									<?php $resume = implode(' · ', array_filter([(int) $sa['capacite'] > 0 ? (int) $sa['capacite'] . ' places' : '',
										$sa['type_salle'] ? $edtLibTypeSalle($sa['type_salle']) : '', $batiment, $etage,
										$nb . ' séance' . ($nb > 1 ? 's' : '')])); ?>
									<?php if ($resume !== ''): ?><small class="edt-salles__mobile"><?= $e($resume) ?></small><?php endif; ?>
								</td>
								<td class="edt-liste__heure"><?= (int) $sa['capacite'] > 0 ? (int) $sa['capacite'] : '—' ?></td>
								<td><?= $sa['type_salle'] ? $e($edtLibTypeSalle($sa['type_salle'])) : '—' ?></td>
								<td><?= $batiment !== '' ? $e($batiment) : ($etage === '' ? '—' : '') ?><?php if ($etage !== ''): ?><small><?= $e($etage) ?></small><?php endif; ?></td>
								<td class="edt-liste__heure"><?= $nb ?></td>
								<td><span class="edt-etat edt-etat--<?= $dispo ? 'ok' : 'hs' ?>"><?= $dispo ? 'Disponible' : 'Hors service' ?></span></td>
								<td class="edt-liste__actions">
									<button type="button" onclick="edtVoirSalle(<?= (int) $sa['id'] ?>)" title="Voir son emploi du temps" aria-label="Emploi du temps de la salle <?= $e($code) ?>"><i class="bi bi-calendar3-week"></i></button>
									<?php if ($edtModif): ?>
									<button type="button" onclick="edtEditerSalle(<?= (int) $sa['id'] ?>)" title="Modifier" aria-label="Modifier la salle <?= $e($code) ?>"><i class="bi bi-pencil"></i></button>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
							<?php if (!$edtSalles): ?>
							<tr><td colspan="7" class="edt-liste__vide">Aucune salle enregistrée.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
					</div>
				</div>

				<?php if ($edtModif):
					$maxSalle = static fn (string $c) => isset($edtColonnesSalle[$c]['max']) ? ' maxlength="' . (int) $edtColonnesSalle[$c]['max'] . '"' : '';
					$batiments = array_values(array_unique(array_filter(array_map(fn ($sa) => trim((string) $sa['batiment']), $edtSalles))));
					sort($batiments);
				?>
				<form id="salleForm" class="edt-salle-form" method="post" action="" data-zone-ignorer hidden novalidate>
					<h4 id="salleFormTitre">Nouvelle salle</h4>
					<input type="hidden" name="id" value="">
					<div class="edt-salle-form__grille">
						<label class="edt-champ-bloc">Code *
							<input type="text" name="salle_code" class="edt-champ" required autocomplete="off"<?= $maxSalle('salle_code') ?> placeholder="ex. T02">
						</label>
						<label class="edt-champ-bloc">Nom
							<input type="text" name="salle_nom" class="edt-champ" autocomplete="off"<?= $maxSalle('salle_nom') ?> placeholder="Repris du code si vide">
						</label>
						<label class="edt-champ-bloc">Places *
							<input type="number" name="capacite" class="edt-champ" required min="0" max="2000" step="1" inputmode="numeric" placeholder="ex. 40">
						</label>
						<label class="edt-champ-bloc">Type
							<?php if ($edtTypesSalle): ?>
							<select name="type_salle" class="edt-champ">
								<?php if (empty($edtColonnesSalle['type_salle']['enum']) || !empty($edtColonnesSalle['type_salle']['null'])): ?><option value="">—</option><?php endif; ?>
								<?php foreach ($edtTypesSalle as $t): ?>
								<option value="<?= $e($t) ?>"><?= $e($edtLibTypeSalle($t)) ?></option>
								<?php endforeach; ?>
							</select>
							<?php else: ?>
							<input type="text" name="type_salle" class="edt-champ" autocomplete="off"<?= $maxSalle('type_salle') ?>>
							<?php endif; ?>
						</label>
						<label class="edt-champ-bloc">Bâtiment
							<input type="text" name="batiment" class="edt-champ" list="edtBatiments" autocomplete="off"<?= $maxSalle('batiment') ?>>
						</label>
						<label class="edt-champ-bloc">Étage
							<input type="number" name="etage" class="edt-champ" min="-9" max="99" step="1" inputmode="numeric" placeholder="0 = rez-de-chaussée">
						</label>
						<label class="edt-champ-bloc edt-salle-form__large">Équipements
							<input type="text" name="equipements" class="edt-champ" autocomplete="off"<?= $maxSalle('equipements') ?> placeholder="ex. vidéoprojecteur, tableau blanc">
						</label>
					</div>
					<datalist id="edtBatiments">
						<?php foreach ($batiments as $b): ?><option value="<?= $e($b) ?>"></option><?php endforeach; ?>
					</datalist>
					<label class="edt-salle-form__dispo">
						<input type="checkbox" name="disponible" value="1" checked>
						<span>Disponible pour les séances <small>Décochée, la salle ne se choisit plus et ses séances de l'année sont signalées à déplacer.</small></span>
					</label>
					<p class="edt-salle-form__erreur" id="salleFormErreur" role="alert" hidden></p>
					<div class="edt-salle-form__actions">
						<button type="button" onclick="edtAnnulerSalle()" class="edt-btn edt-btn--second">Annuler</button>
						<button type="submit" class="edt-btn edt-btn--primaire" id="salleFormEnvoyer"><i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer la salle</button>
					</div>
				</form>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Modal Modifier une séance -->
	<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
		<div class="bg-slate-800 rounded-lg w-full max-w-2xl mx-4 max-h-[90vh] overflow-auto">
			<div class="p-4 border-b border-slate-700 flex justify-between items-center">
				<h3 class="text-lg font-semibold text-white"><i class="bi bi-pencil-square text-cyan-500"></i> Modifier la séance</h3>
				<button onclick="closeEditModal()" class="text-slate-400 hover:text-white"><i class="bi bi-x-lg"></i></button>
			</div>
			
			<form id="editSeanceForm" class="p-4">
				<input type="hidden" id="editSeanceId" name="id">
				
				<!-- Affichage du cours (non modifiable) -->
				<div class="mb-4 bg-slate-900 p-3 rounded">
					<label class="block text-slate-400 text-xs mb-1">Cours</label>
					<div id="editCoursInfo" class="text-white font-semibold"></div>
				</div>
				
				<!-- Parcours concerné -->
				<div class="mb-4">
					<label class="block text-slate-400 text-xs mb-1">Parcours concerné</label>
					<select name="parcours" id="editParcours" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
						<option value="">-- Tous les parcours de la mention --</option>
					</select>
				</div>
				
				<div class="grid grid-cols-2 gap-4">
					<!-- Type de séance -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Type de séance *</label>
						<select name="type_seance" id="editTypeSeance" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="cours">Cours magistral</option>
							<option value="td">TD</option>
							<option value="tp">TP</option>
							<option value="examen">Examen</option>
						</select>
					</div>
					
					<!-- Jour -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Jour *</label>
						<select name="jour_semaine" id="editJour" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="Lundi">Lundi</option>
							<option value="Mardi">Mardi</option>
							<option value="Mercredi">Mercredi</option>
							<option value="Jeudi">Jeudi</option>
							<option value="Vendredi">Vendredi</option>
							<option value="Samedi">Samedi</option>
						</select>
					</div>
					
					<!-- Heure début -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure début *</label>
						<select name="heure_debut" id="editHeureDebut" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<?php /* Toutes les demi-heures : une seance deposee a 08:30 gardait
							         son horaire dans la grille mais perdait ses trente minutes a
							         l'enregistrement de sa fiche, faute d'option a 08:30. */ ?>
							<?php for ($m = 420; $m <= 1230; $m += 30): ?><option value="<?= edtHeure($m) ?>"><?= edtHeure($m) ?></option><?php endfor; ?>
						</select>
					</div>
					
					<!-- Heure fin -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Heure fin *</label>
						<select name="heure_fin" id="editHeureFin" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<?php /* Toutes les demi-heures : une seance deposee a 08:30 gardait
							         son horaire dans la grille mais perdait ses trente minutes a
							         l'enregistrement de sa fiche, faute d'option a 08:30. */ ?>
							<?php for ($m = 450; $m <= 1260; $m += 30): ?><option value="<?= edtHeure($m) ?>"><?= edtHeure($m) ?></option><?php endfor; ?>
						</select>
					</div>
					
					<!-- Salle -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Salle *</label>
						<select name="salle_id" id="editSalle" required class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Sélectionner --</option>
						</select>
					</div>
					
					<!-- Enseignant -->
					<div>
						<label class="block text-slate-400 text-xs mb-1">Enseignant</label>
						<select name="id_teacher" id="editTeacher" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600">
							<option value="">-- Aucun --</option>
						</select>
					</div>
					
					<!-- Remarque -->
					<div class="col-span-2">
						<label class="block text-slate-400 text-xs mb-1">Remarque</label>
						<input type="text" name="remarque" id="editRemarque" class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded border border-slate-600" placeholder="Optionnel...">
					</div>
				</div>
				
				<!-- Zone conflits -->
				<div id="editConflictsZone" class="mt-4 hidden">
					<div class="bg-slate-900 rounded p-3">
						<h4 class="text-sm font-semibold text-white mb-2"><i class="bi bi-exclamation-triangle text-yellow-500"></i> Conflits détectés</h4>
						<div id="editConflictsList"></div>
					</div>
				</div>
				
				<!-- Boutons -->
				<div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-700">
					<button type="button" onclick="closeEditModal()" class="bg-slate-600 hover:bg-slate-500 text-white text-sm px-4 py-2 rounded">Annuler</button>
					<button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white text-sm px-4 py-2 rounded">
						<i class="bi bi-check-lg"></i> Enregistrer
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Données pour export -->
	<?php
	/* Les seances exportables sont celles que la grille montre : meme annee,
	   meme statut. La requete precedente prenait TOUTE la table — toutes les
	   annees scolaires et tous les statuts, seances annulees comprises — si
	   bien qu'un export ne correspondait pas a l'ecran dont il etait tire. */
	$stExport = $dtb->prepare(
		"SELECT e.*, s.salle_nom
		 FROM t_2024_emploi_du_temps e
		 LEFT JOIN t_2024_salles s ON e.salle_id = s.id
		 WHERE e.statut = 'confirme' AND e.annee_scolaire = ?
		 ORDER BY FIELD(e.jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'),
		          e.heure_debut");
	$stExport->execute([$edtAnnee]);
	$seancesData = [];
	foreach ($stExport as $s) {
		$seancesData[] = [
			'id' => $s['id'],
			'cours_sigle' => $s['cours_sigle'],
			'cours_title' => $s['cours_title'],
			'type_seance' => $s['type_seance'],
			'jour_semaine' => $s['jour_semaine'],
			'heure_debut' => $s['heure_debut'],
			'heure_fin' => $s['heure_fin'],
			'salle_nom' => $s['salle_nom'],
			'mention' => $s['mention'],
			'niveau' => $s['niveau'],
			'semester' => $s['semester'],
			'parcours' => $s['parcours'] ?? ''
		];
	}
	?>
	<!-- jsPDF et SheetJS pour export -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
	<?php /* Les donnees d'export dependent de l'annee affichee. Elles sont posees
	         dans un bloc de donnees plutot que dans un « const » de script : la
	         bascule d'annee se fait en AJAX, et un const ne se reassigne pas.
	         Le type application/json n'est jamais execute — on en lit le texte. */ ?>
	<script type="application/json" id="edtDonnees"><?= json_encode(
		['annee' => $edtAnnee, 'seances' => $seancesData],
		JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	) ?></script>

	<script>
	/* Donnees d'export, relues a chaque bascule d'annee (voir edtRelireDonnees). */
	let allSeancesData = [];
	let EDT_ANNEE_EXPORT = '';

	function edtRelireDonnees(source) {
		const bloc = (source || document).getElementById('edtDonnees');
		if (!bloc) { return; }
		try {
			const d = JSON.parse(bloc.textContent);
			allSeancesData = d.seances || [];
			EDT_ANNEE_EXPORT = d.annee || '';
		} catch (e) {
			// Donnees illisibles : on laisse l'export vide plutot que faux.
			allSeancesData = [];
		}
	}
	edtRelireDonnees(document);

	const joursOrdre = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
	
	// ==================== EXPORT MODAL ====================
	function openExportModal() {
		document.getElementById('exportModal').classList.remove('hidden');
		document.getElementById('exportModal').classList.add('flex');
		// Pré-remplir avec les filtres actuels
		document.getElementById('exportMention').value = document.getElementById('filterMention').value;
		document.getElementById('exportType').value = document.getElementById('filterTypeSeance').value;
		document.getElementById('exportNiveau').value = document.getElementById('filterNiveau').value;
	}
	
	function closeExportModal() {
		document.getElementById('exportModal').classList.add('hidden');
		document.getElementById('exportModal').classList.remove('flex');
	}
	
	// Filtrer les séances selon les critères
	function getFilteredSeances() {
		const mention = document.getElementById('exportMention').value;
		const type = document.getElementById('exportType').value;
		const niveau = document.getElementById('exportNiveau').value;
		
		return allSeancesData.filter(s => {
			if(mention && s.mention !== mention) return false;
			if(type && s.type_seance !== type) return false;
			if(niveau && s.niveau != niveau) return false;
			return true;
		});
	}
	
	// Générer le titre du fichier
	function getExportTitle() {
		const mention = document.getElementById('exportMention').value;
		const type = document.getElementById('exportType').value;
		const niveau = document.getElementById('exportNiveau').value;
		
		// L annee figure au titre : un export sans elle ne se relit pas.
		let title = 'Emploi du temps ' + EDT_ANNEE_EXPORT;
		if(mention) title += ' - ' + mention;
		if(type) title += ' - ' + type.toUpperCase();
		if(niveau) title += ' - L' + niveau;
		
		return title;
	}
	
	// ==================== EXPORT PDF ====================
	function exportScheduleToPDF() {
		// Vérifier que jsPDF est chargé
		if(typeof window.jspdf === 'undefined') {
			edtDire('Erreur: jsPDF non chargé. Rechargez la page.', 'error');
			return;
		}
		
		const jsPDF = window.jspdf.jsPDF;
		const seances = getFilteredSeances();
		
		if(seances.length === 0) {
			edtDire('Aucune séance à exporter', 'error');
			return;
		}
		
		try {
			// Trier par jour et heure
			seances.sort((a, b) => {
				const jourA = joursOrdre.indexOf(a.jour_semaine);
				const jourB = joursOrdre.indexOf(b.jour_semaine);
				if(jourA !== jourB) return jourA - jourB;
				return a.heure_debut.localeCompare(b.heure_debut);
			});
			
			const doc = new jsPDF('landscape', 'mm', 'a4');
			const pageWidth = doc.internal.pageSize.getWidth();
			const pageHeight = doc.internal.pageSize.getHeight();
			
			// En-tête avec fond bleu
			doc.setFillColor(30, 64, 175);
			doc.rect(0, 0, pageWidth, 30, 'F');
			
			doc.setFontSize(16);
			doc.setTextColor(255, 255, 255);
			doc.setFont(undefined, 'bold');
			doc.text(getExportTitle(), 14, 12);
			
			doc.setFontSize(10);
			doc.setFont(undefined, 'normal');
			// L'annee affichee, et non 2025-2026 ecrit en dur ; la date de Madagascar.
			doc.text('Universite Adventiste de Zurcher - Annee scolaire ' + edtAnnee(), 14, 20);
			doc.text('Genere le ' + new Date().toLocaleDateString('fr-FR', { timeZone: 'Indian/Antananarivo' }), 14, 26);
			
			doc.setTextColor(200, 200, 200);
			doc.text('Total: ' + seances.length + ' seance(s)', pageWidth - 60, 20);
			
			let yPos = 38;
			
			// Créer une grille par jour
			const seancesByJour = {};
			seances.forEach(s => {
				if(!seancesByJour[s.jour_semaine]) seancesByJour[s.jour_semaine] = [];
				seancesByJour[s.jour_semaine].push(s);
			});
			
			// Couleurs par type
			const typeColors = {
				'cours': [8, 145, 178],
				'td': [124, 58, 237],
				'tp': [5, 150, 105],
				'examen': [217, 119, 6]
			};
			
			// Colonnes du tableau
			const cols = [
				{ title: 'Horaire', width: 30, x: 14 },
				{ title: 'Sigle', width: 25, x: 44 },
				{ title: 'Titre du cours', width: 80, x: 69 },
				{ title: 'Type', width: 20, x: 149 },
				{ title: 'Salle', width: 25, x: 169 },
				{ title: 'Niveau', width: 25, x: 194 },
				{ title: 'Parcours', width: 30, x: 219 }
			];
			const rowHeight = 8;
			const headerHeight = 10;
			
			// Fonction pour dessiner l'en-tête du tableau
			function drawTableHeader(y) {
				doc.setFillColor(51, 65, 85);
				doc.rect(14, y, pageWidth - 28, headerHeight, 'F');
				doc.setFontSize(8);
				doc.setTextColor(255, 255, 255);
				doc.setFont(undefined, 'bold');
				cols.forEach(col => {
					doc.text(col.title, col.x + 2, y + 7);
				});
				return y + headerHeight;
			}
			
			// Fonction pour dessiner une ligne de données
			function drawTableRow(y, data, typeSeance) {
				// Fond alterné
				const rowIndex = Math.floor((y - 38) / rowHeight);
				if(rowIndex % 2 === 0) {
					doc.setFillColor(241, 245, 249);
					doc.rect(14, y, pageWidth - 28, rowHeight, 'F');
				}
				
				doc.setFontSize(8);
				doc.setTextColor(30, 41, 59);
				doc.setFont(undefined, 'normal');
				
				// Dessiner chaque cellule
				cols.forEach((col, idx) => {
					let text = data[idx] || '';
					// Tronquer si trop long
					const maxChars = Math.floor(col.width / 2);
					if(text.length > maxChars) {
						text = text.substring(0, maxChars - 2) + '..';
					}
					
					// Colorer la cellule Type
					if(idx === 3 && typeColors[typeSeance]) {
						doc.setFillColor(...typeColors[typeSeance]);
						doc.rect(col.x, y, col.width, rowHeight, 'F');
						doc.setTextColor(255, 255, 255);
						doc.setFont(undefined, 'bold');
					}
					
					doc.text(text, col.x + 2, y + 6);
					
					// Reset couleur
					doc.setTextColor(30, 41, 59);
					doc.setFont(undefined, 'normal');
				});
				
				return y + rowHeight;
			}
			
			// Pour chaque jour
			joursOrdre.forEach(jour => {
				const seancesDuJour = seancesByJour[jour];
				if(!seancesDuJour || seancesDuJour.length === 0) return;
				
				// Calculer l'espace nécessaire
				const espaceBesoin = headerHeight + (seancesDuJour.length * rowHeight) + 20;
				
				// Nouvelle page si nécessaire
				if(yPos + espaceBesoin > pageHeight - 20) {
					doc.addPage();
					yPos = 15;
				}
				
				// Titre du jour
				doc.setFontSize(11);
				doc.setTextColor(30, 64, 175);
				doc.setFont(undefined, 'bold');
				doc.text(jour, 14, yPos);
				yPos += 5;
				
				// En-tête du tableau
				yPos = drawTableHeader(yPos);
				
				// Lignes de données
				seancesDuJour.forEach(s => {
					const rowData = [
						s.heure_debut.substring(0, 5) + ' - ' + s.heure_fin.substring(0, 5),
						s.cours_sigle,
						s.cours_title,
						s.type_seance.toUpperCase(),
						s.salle_nom,
						s.mention + ' L' + s.niveau,
						s.parcours || '-'
					];
					yPos = drawTableRow(yPos, rowData, s.type_seance);
				});
				
				// Bordure du tableau
				doc.setDrawColor(200, 200, 200);
				doc.rect(14, yPos - (seancesDuJour.length * rowHeight) - headerHeight, pageWidth - 28, (seancesDuJour.length * rowHeight) + headerHeight);
				
				yPos += 12;
			});
			
			// Pied de page
			const pageCount = doc.internal.getNumberOfPages();
			for(let i = 1; i <= pageCount; i++) {
				doc.setPage(i);
				doc.setFontSize(8);
				doc.setTextColor(128, 128, 128);
				doc.text('Page ' + i + '/' + pageCount, pageWidth / 2, pageHeight - 10, { align: 'center' });
			}
			
			// Nom du fichier
			let filename = 'emploi_du_temps';
			const mentionVal = document.getElementById('exportMention').value;
			const typeVal = document.getElementById('exportType').value;
			const niveauVal = document.getElementById('exportNiveau').value;
			if(mentionVal) filename += '_' + mentionVal;
			if(typeVal) filename += '_' + typeVal;
			if(niveauVal) filename += '_L' + niveauVal;
			filename += '.pdf';
			
			doc.save(filename);
			edtDire('PDF exporte avec succes!', 'success');
			closeExportModal();
		
		} catch(error) {
			console.error('Erreur export PDF:', error);
			edtDire('Erreur lors de l\'export PDF: ' + error.message, 'error');
		}
	}
	
	// ==================== EXPORT EXCEL ====================
	function exportScheduleToExcel() {
		const seances = getFilteredSeances();
		
		if(seances.length === 0) {
			edtDire('Aucune séance à exporter', 'error');
			return;
		}
		
		// Trier par jour et heure
		seances.sort((a, b) => {
			const jourA = joursOrdre.indexOf(a.jour_semaine);
			const jourB = joursOrdre.indexOf(b.jour_semaine);
			if(jourA !== jourB) return jourA - jourB;
			return a.heure_debut.localeCompare(b.heure_debut);
		});
		
		// Créer le workbook
		const wb = XLSX.utils.book_new();
		
		// Feuille 1: Liste complète
		const wsData = [
			['EMPLOI DU TEMPS - ' + getExportTitle().toUpperCase()],
			['Université Adventiste de Zurcher - Année scolaire ' + edtAnnee()],
			['Généré le ' + new Date().toLocaleDateString('fr-FR', { timeZone: 'Indian/Antananarivo' })],
			[''],
			['Jour', 'Heure début', 'Heure fin', 'Sigle', 'Titre du cours', 'Type', 'Salle', 'Mention', 'Niveau', 'Parcours']
		];
		
		seances.forEach(s => {
			wsData.push([
				s.jour_semaine,
				s.heure_debut.substring(0, 5),
				s.heure_fin.substring(0, 5),
				s.cours_sigle,
				s.cours_title,
				s.type_seance.toUpperCase(),
				s.salle_nom,
				s.mention,
				'L' + s.niveau,
				s.parcours || ''
			]);
		});
		
		const ws = XLSX.utils.aoa_to_sheet(wsData);
		
		// Largeur des colonnes
		ws['!cols'] = [
			{ wch: 12 },  // Jour
			{ wch: 10 },  // Heure début
			{ wch: 10 },  // Heure fin
			{ wch: 12 },  // Sigle
			{ wch: 40 },  // Titre
			{ wch: 10 },  // Type
			{ wch: 12 },  // Salle
			{ wch: 10 },  // Mention
			{ wch: 8 },   // Niveau
			{ wch: 15 }   // Parcours
		];
		
		// Fusion pour le titre
		ws['!merges'] = [
			{ s: { r: 0, c: 0 }, e: { r: 0, c: 9 } },
			{ s: { r: 1, c: 0 }, e: { r: 1, c: 9 } },
			{ s: { r: 2, c: 0 }, e: { r: 2, c: 9 } }
		];
		
		XLSX.utils.book_append_sheet(wb, ws, 'Emploi du temps');
		
		// Feuille 2: Grille hebdomadaire
		const heures = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
		const grilleData = [
			['GRILLE HEBDOMADAIRE'],
			[''],
			['Heure', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi']
		];
		
		heures.forEach(heure => {
			const heureInt = parseInt(heure.substring(0, 2));
			const row = [heure];
			
			['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'].forEach(jour => {
				// Trouver les séances qui couvrent cette heure
				const seancesCeCreneaux = seances.filter(s => {
					const debut = parseInt(s.heure_debut.substring(0, 2));
					const fin = parseInt(s.heure_fin.substring(0, 2));
					return s.jour_semaine === jour && heureInt >= debut && heureInt < fin;
				});
				
				if(seancesCeCreneaux.length > 0) {
					const textes = seancesCeCreneaux.map(s => 
						`${s.cours_sigle} (${s.type_seance.toUpperCase()}) - ${s.salle_nom}`
					);
					row.push(textes.join('\n'));
				} else {
					row.push('');
				}
			});
			
			grilleData.push(row);
		});
		
		const wsGrille = XLSX.utils.aoa_to_sheet(grilleData);
		wsGrille['!cols'] = [
			{ wch: 8 },
			{ wch: 30 },
			{ wch: 30 },
			{ wch: 30 },
			{ wch: 30 },
			{ wch: 30 }
		];
		wsGrille['!merges'] = [
			{ s: { r: 0, c: 0 }, e: { r: 0, c: 5 } }
		];
		
		XLSX.utils.book_append_sheet(wb, wsGrille, 'Grille hebdomadaire');
		
		// Feuille 3: Par jour (une feuille par jour)
		joursOrdre.slice(0, 5).forEach(jour => {
			const seancesDuJour = seances.filter(s => s.jour_semaine === jour);
			if(seancesDuJour.length === 0) return;
			
			const jourData = [
				[jour.toUpperCase()],
				[''],
				['Horaire', 'Sigle', 'Titre', 'Type', 'Salle', 'Mention/Niveau', 'Parcours']
			];
			
			seancesDuJour.forEach(s => {
				jourData.push([
					s.heure_debut.substring(0, 5) + ' - ' + s.heure_fin.substring(0, 5),
					s.cours_sigle,
					s.cours_title,
					s.type_seance.toUpperCase(),
					s.salle_nom,
					s.mention + ' L' + s.niveau,
					s.parcours || ''
				]);
			});
			
			const wsJour = XLSX.utils.aoa_to_sheet(jourData);
			wsJour['!cols'] = [
				{ wch: 15 },
				{ wch: 12 },
				{ wch: 40 },
				{ wch: 10 },
				{ wch: 12 },
				{ wch: 15 },
				{ wch: 15 }
			];
			wsJour['!merges'] = [
				{ s: { r: 0, c: 0 }, e: { r: 0, c: 6 } }
			];
			
			XLSX.utils.book_append_sheet(wb, wsJour, jour);
		});
		
		// Nom du fichier
		let filename = 'emploi_du_temps';
		const mention = document.getElementById('exportMention').value;
		const type = document.getElementById('exportType').value;
		const niveau = document.getElementById('exportNiveau').value;
		if(mention) filename += '_' + mention;
		if(type) filename += '_' + type;
		if(niveau) filename += '_L' + niveau;
		filename += '.xlsx';
		
		XLSX.writeFile(wb, filename);
		edtDire('Excel exporté avec succès!', 'success');
		closeExportModal();
	}
	</script>

	<script>
	/* =========================================================================
	   Emploi du temps — interactions

	   Trois changements de fond par rapport a la version precedente :

	   1. Plus aucun location.reload(). Chaque ajout, deplacement, modification
	      ou suppression rechargeait la page entiere, ce qui ramenait en haut,
	      vidait les filtres et faisait clignoter tout l'ecran pour une carte
	      deplacee de deux centimetres. On rafraichit desormais la grille seule.

	   2. Le jeton anti-CSRF accompagne toutes les ecritures. L'API l'exige
	      maintenant : elle acceptait auparavant les modifications de n'importe
	      quelle origine, et meme sans etre connecte.

	   3. Les messages passent par le systeme de toasts du projet (init/toast.php)
	      au lieu d'un toast maison, d'alert() et de innerHTML. Ce dernier point
	      n'est pas cosmetique : les messages de conflit contiennent des
	      intitules venus de la base, qui etaient injectes en HTML brut.
	   ========================================================================= */

	const EDT_API = APP_BASE + '/src/emploi-temps.api';

	/* Lue a chaque appel, et non figee au chargement : la bascule d'annee se
	   fait maintenant en AJAX, donc la valeur change sans que la page ne soit
	   reconstruite. */
	function edtAnnee() {
		const sel = document.getElementById('filterAnnee');
		return sel ? sel.value : '';
	}

	/* ---------------------------------------------------------------------
	   Messages
	   Le module de toasts est charge avec la page (init/toast.php). S'il
	   manquait, le message part dans la console : jamais de boite native.
	   --------------------------------------------------------------------- */
	function edtDire(message, type = 'info', duree) {
		if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
			Toast.show(message, type, duree ? { duration: duree } : {});
			return;
		}
		if (window.console) { console.warn(message); }
	}

	/** Confirmation en modale (init/toast.php). Sans elle, on ne fait rien. */
	function edtDemander(options, texte) {
		if (typeof Toast !== 'undefined' && Toast && typeof Toast.confirm === 'function') {
			return Toast.confirm(texte, options || {});
		}
		return Promise.resolve(false);
	}

	/** Liste de conflits -> texte lisible, sans interpolation de HTML. */
	function edtTexteConflits(conflits) {
		return (conflits || []).map(c => c.message).join(' · ');
	}

	/* ---------------------------------------------------------------------
	   Appels a l'API
	   --------------------------------------------------------------------- */

	/**
	 * @param {FormData|null} corps  null = lecture en GET
	 * @returns {Promise<object|null>} null quand la session a expire : session.js
	 *          a deja affiche le message et lance la redirection.
	 */
	async function edtApi(action, corps = null, params = {}) {
		let reponse;

		if (corps) {
			corps.set('action', action);
			// L'annee courante voyage avec chaque ecriture : sans elle, l'API
			// retomberait sur son defaut, qui n'est pas forcement celle affichee.
			const an = edtAnnee();
			if (an && !corps.has('annee_scolaire')) {
				corps.set('annee_scolaire', an);
			}
			if (typeof CSRF_TOKEN !== 'undefined' && !corps.has('csrf_token')) {
				corps.set('csrf_token', CSRF_TOKEN);
			}
			reponse = await fetch(EDT_API, {
				method: 'POST',
				body: corps,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				credentials: 'same-origin'
			});
		} else {
			const q = new URLSearchParams(Object.assign({ action: action }, params));
			const an = edtAnnee();
			if (an) { q.set('annee_scolaire', an); }
			reponse = await fetch(EDT_API + '?' + q.toString(), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				credentials: 'same-origin'
			});
		}

		// 401/403 : src/js/session.js s'en occupe deja (message + redirection).
		if (reponse.status === 401 || reponse.status === 403) { return null; }

		let donnees;
		try { donnees = await reponse.json(); }
		catch (e) { throw new Error('Réponse illisible du serveur.'); }

		// Jeton perime : reconnu a ce drapeau et non a un code de statut, le
		// serveur ne pouvant pas en emettre un qui soit a la fois specifique et
		// accepte partout (voir la note dans src/emploi-temps.api.php).
		if (donnees && donnees.csrf_expire) {
			edtDire(donnees.error || 'Session expirée. Rechargez la page.', 'error', 12000);
			return null;
		}
		return donnees;
	}

	/* ---------------------------------------------------------------------
	   Rafraichissement, sans rechargement

	   On redemande la page a l'adresse courante et on en preleve les morceaux
	   qui dependent des donnees. Le calcul de placement — chevauchements,
	   demi-heures, largeurs — reste ainsi entierement en PHP (data/edt.php).
	   Le refaire en JavaScript aurait signifie deux implementations d'un meme
	   algorithme, donc deux occasions de diverger.

	   Cinq morceaux, et non deux : la bascule d'annee passe par ici, et l'annee
	   ne commande pas seulement la grille. Le resume la cite, la liste des
	   mentions n'offre que celles de l'annee, et les donnees d'export en
	   dependent aussi. En n'echangeant que la grille, on affichait 2026-2027
	   avec les mentions et l'export de 2025-2026.
	   --------------------------------------------------------------------- */

	/** Remplace un noeud par son homologue du document recu. */
	function edtEchanger(doc, id) {
		const ancien = document.getElementById(id);
		const nouveau = doc.getElementById(id);
		if (ancien && nouveau) { ancien.replaceWith(nouveau); return true; }
		return false;
	}

	/**
	 * Remplace les options d'un select en preservant le choix courant.
	 *
	 * Changer d'annee change la liste des mentions. Une mention filtree qui
	 * n'existe pas dans la nouvelle annee ne peut pas etre conservee : on
	 * retombe alors sur « toutes », sinon la grille paraitrait vide sans que
	 * rien n'explique pourquoi.
	 */
	function edtEchangerOptions(doc, id) {
		const ancien = document.getElementById(id);
		const nouveau = doc.getElementById(id);
		if (!ancien || !nouveau) { return; }

		const choix = ancien.value;
		ancien.innerHTML = nouveau.innerHTML;

		const existeEncore = Array.prototype.some.call(
			ancien.options, o => o.value === choix);
		ancien.value = existeEncore ? choix : '';
	}

	async function edtRafraichir() {
		const reponse = await fetch(window.location.href, {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		});
		if (!reponse.ok) { throw new Error('rafraîchissement impossible'); }

		const doc = new DOMParser().parseFromString(await reponse.text(), 'text/html');

		/* Zone absente de la reponse : la session a probablement expire et le
		   serveur a renvoye la page de connexion. Une eventuelle ecriture, elle,
		   a reussi — laisser l'ancienne grille afficherait un etat faux. On
		   recharge, ce qui mene a la connexion si c'est bien de cela qu'il
		   s'agit. */
		if (!doc.getElementById('edtZone')) {
			window.location.reload();
			return;
		}

		// La grille passe d'un etat vide a un etat rempli et inversement : le
		// conteneur echange couvre les deux formes.
		edtEchanger(doc, 'edtZone');
		edtEchanger(doc, 'edtListe');
		edtEchanger(doc, 'edtResume');
		edtEchanger(doc, 'edtContexte');
		edtEchanger(doc, 'edtSallesCorps');

		edtEchangerOptions(doc, 'exportMention');
		// Une salle ajoutee, renommee ou mise hors service change cette liste.
		edtEchangerOptions(doc, 'filterSalle');

		// Donnees d'export : relues depuis le bloc du document recu.
		edtRelireDonnees(doc);

	}

	/* ---------------------------------------------------------------------
	   Glisser-deposer

	   Les ecouteurs sont delegues au document : la grille est remplacee a
	   chaque rafraichissement, et des ecouteurs poses sur chaque carte
	   auraient disparu avec elle.
	   --------------------------------------------------------------------- */
	let edtTiree = null;

	document.addEventListener('dragstart', function (e) {
		const carte = e.target.closest ? e.target.closest('.edt-carte') : null;
		if (!carte) { return; }
		edtTiree = {
			id: carte.dataset.id,
			duree: parseInt(carte.dataset.dureeMin, 10) || 120,
			element: carte
		};
		carte.classList.add('est-tiree');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', carte.dataset.id);
	});

	document.addEventListener('dragend', function (e) {
		const carte = e.target.closest ? e.target.closest('.edt-carte') : null;
		if (carte) { carte.classList.remove('est-tiree'); }
		document.querySelectorAll('.est-cible, .est-refuse')
			.forEach(el => el.classList.remove('est-cible', 'est-refuse'));
		edtTiree = null;
	});

	document.addEventListener('dragover', function (e) {
		const creneau = e.target.closest ? e.target.closest('.edt__creneau') : null;
		if (!creneau || !edtTiree) { return; }
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		creneau.classList.add('est-cible');
	});

	document.addEventListener('dragleave', function (e) {
		const creneau = e.target.closest ? e.target.closest('.edt__creneau') : null;
		if (creneau) { creneau.classList.remove('est-cible', 'est-refuse'); }
	});

	document.addEventListener('drop', async function (e) {
		const creneau = e.target.closest ? e.target.closest('.edt__creneau') : null;
		if (!creneau || !edtTiree) { return; }
		e.preventDefault();
		creneau.classList.remove('est-cible', 'est-refuse');

		/* Aucune verification d'occupation ici. L'ancienne version refusait le
		   depot des qu'une carte se trouvait dans la cellule visee, ce qui
		   interdisait de poser deux seances en parallele — deux salles
		   differentes, deux promotions differentes — alors que c'est le cas
		   normal. C'est le serveur qui tranche, avec les vraies regles. */
		await edtDeplacer(edtTiree.id, creneau.dataset.jour, creneau.dataset.heure, false);
	});

	async function edtDeplacer(id, jour, heure, force) {
		const corps = new FormData();
		corps.set('id', id);
		corps.set('jour_semaine', jour);
		corps.set('heure_debut', heure);
		if (force) { corps.set('force', '1'); }

		try {
			const r = await edtApi('move_seance', corps);
			if (!r) { return; }

			if (r.success) {
				edtDire('Séance déplacée au ' + jour.toLowerCase() + ' ' + heure + '.', 'success');
				await edtRafraichir();
				return;
			}

			if (r.conflicts && r.conflicts.length) {
				/* Conflit contournable — deux cours proposes a la meme promotion.
				   L'ancienne version bloquait sans recours, alors que la creation
				   de la meme seance, elle, passait : on pouvait creer une seance
				   impossible a deplacer ensuite. */
				if (r.forcable) {
					if (await edtDemander({ title: 'Conflit d\'horaire', confirmText: 'Déplacer quand même' }, 'Conflit :\n\n' + edtTexteConflits(r.conflicts)
						+ '\n\nDéplacer quand même ?')) {
						await edtDeplacer(id, jour, heure, true);
					}
					return;
				}
				edtDire(edtTexteConflits(r.conflicts), 'error', 10000);
				return;
			}
			edtDire(r.error || 'Le déplacement a échoué.', 'error');
		} catch (err) {
			edtDire('Le serveur n’a pas répondu. La séance n’a pas été déplacée.', 'error');
		}
	}

	/* Clavier : le glisser-deposer n'est pas utilisable sans souris. Entree
	   ouvre la fiche, d'ou l'on peut changer le jour et l'heure. */
	document.addEventListener('keydown', function (e) {
		const carte = e.target.closest ? e.target.closest('.edt-carte') : null;
		if (!carte) { return; }
		if ((e.key === 'Enter' || e.key === ' ') && document.querySelector('.edt--modifiable')) {
			e.preventDefault();
			openEditModal(carte.dataset.id);
		}
	});

	/* Un clic suffit : le double-clic n'etait annonce que par une ligne d'aide,
	   et l'on cherchait comment ouvrir une seance. Un creneau libre, lui, ouvre
	   la fiche d'ajout a cet horaire, preremplie selon la vue. Rien de tout cela
	   en consultation seule. */
	document.addEventListener('click', function (e) {
		if (!document.querySelector('.edt--modifiable')) { return; }
		const carte = e.target.closest ? e.target.closest('.edt-carte') : null;
		if (carte) { openEditModal(carte.dataset.id); return; }
		const creneau = e.target.closest ? e.target.closest('.edt__creneau') : null;
		if (creneau) {
			const g = document.getElementById('edtGrille');
			openAddModal({ jour: creneau.dataset.jour, heure: creneau.dataset.heure,
				mention: g ? g.dataset.mention : '', niveau: g ? g.dataset.niveau : '',
				salle: g ? g.dataset.salle : '', prof: g ? g.dataset.prof : '' });
		}
	});

	/* ---------------------------------------------------------------------
	   Filtres
	   --------------------------------------------------------------------- */
	document.addEventListener('change', function (e) {
		if (e.target.matches('#filterAnnee')) {
			edtChangerAnnee(e.target.value, true);
			return;
		}
		if (e.target.closest && e.target.closest('#edtFiltres')) {
			edtAppliquerVue(true);
		}
	});

	/* ---------------------------------------------------------------------
	   Bascule d'annee

	   En AJAX, mais l'adresse suit quand meme : /schedule?annee=2026-2027 doit
	   rester copiable, partageable et retrouvable par l'historique. C'est ce
	   que fait pushState — il change la barre d'adresse sans rien recharger.
	   Ensuite edtRafraichir() lit window.location.href, donc la nouvelle
	   adresse, et rapporte l'annee demandee.

	   « empiler » distingue les deux appelants : un choix de l'utilisateur
	   ajoute une entree a l'historique, un retour en arriere n'en ajoute pas —
	   sinon le bouton « precedent » ne parviendrait jamais a sortir de la page.
	   --------------------------------------------------------------------- */
	let edtBasculeEnCours = false;

	async function edtChangerAnnee(annee, empiler) {
		if (edtBasculeEnCours) { return; }
		edtBasculeEnCours = true;

		const select = document.getElementById('filterAnnee');
		const precedente = select ? select.dataset.annee || select.value : '';

		if (select) { select.value = annee; select.disabled = true; }

		try {
			if (empiler) {
				const u = new URL(window.location.href);
				u.searchParams.set('annee', annee);
				window.history.pushState({ annee: annee }, '', u.toString());
			}
			await edtRafraichir();
			if (select) { select.dataset.annee = annee; }
		} catch (err) {
			/* Le rafraichissement a echoue : on remet le selecteur sur l'annee
			   reellement affichee. Le laisser sur la valeur demandee ferait
			   croire a une bascule qui n'a pas eu lieu. */
			if (select) { select.value = precedente; }
			edtDire('Le changement d’année a échoué. Rien n’a été modifié à l’écran.', 'error');
		} finally {
			if (select) { select.disabled = false; }
			edtBasculeEnCours = false;
		}
	}

	/* Boutons « precedent » et « suivant » : l'adresse change sans que la page
	   ne soit rechargee, c'est donc a nous de resynchroniser l'affichage. */
	window.addEventListener('popstate', function () {
		/* La vue d'abord : ses champs reprennent les valeurs de l'adresse. */
		edtRemplirFiltresDepuisAdresse();
		edtAfficherChampsDeVue();
		const voulue = new URL(window.location.href).searchParams.get('annee');
		const select = document.getElementById('filterAnnee');
		if (!select) { return; }

		// Sans parametre d'annee, on revient a celle que le serveur choisit.
		const cible = voulue !== null ? voulue : (select.dataset.defaut || select.value);
		if (cible === (select.dataset.annee || select.value)) { edtRafraichir().catch(() => window.location.reload()); return; }

		const existe = Array.prototype.some.call(select.options, o => o.value === cible);
		if (!existe) { window.location.reload(); return; }

		edtChangerAnnee(cible, false);
	});

	function edtAncienFiltreInutilise() {
		const lire = id => { const el = document.getElementById(id); return el ? el.value : ''; };
		const mention = lire('filterMention');
		const niveau = lire('filterNiveau');
		const semester = lire('filterSemester');
		const type = lire('filterTypeSeance');

		const garde = d =>
			(!mention  || d.mention  === mention)
			&& (!niveau   || d.niveau   === niveau)
			&& (!semester || d.semester === semester)
			&& (!type     || d.type     === type);

		/* hidden, et non style.display : une carte masquee doit sortir aussi du
		   glisser-deposer et du parcours au clavier, pas seulement de la vue. */
		document.querySelectorAll('.edt-carte').forEach(c => {
			const visible = garde(c.dataset);
			c.hidden = !visible;
			c.setAttribute('draggable', visible ? 'true' : 'false');
		});

		document.querySelectorAll('.course-row').forEach(r => {
			r.hidden = !garde(r.dataset);
		});

		// Le compteur par jour doit refleter ce qui est reellement affiche.
		document.querySelectorAll('.edt__colonne').forEach(col => {
			const n = col.querySelectorAll('.edt-carte:not([hidden])').length;
			const entete = document.querySelector(
				'.edt__jour[data-jour="' + CSS.escape(col.dataset.jour) + '"] small');
			if (entete) { entete.textContent = n + ' séance' + (n > 1 ? 's' : ''); }
		});
	}

	/* ---------------------------------------------------------------------
	   Fenetres
	   --------------------------------------------------------------------- */
	/**
	 * @param {object} [p] jour, heure (debut), mention, niveau, salle, prof : ce
	 *   que le creneau clique et la vue affichee permettent de deviner.
	 */
	async function openAddModal(p) {
		p = p || {};
		const m = document.getElementById('addModal');
		m.classList.remove('hidden');
		m.classList.add('flex');
		if (p.mention) { document.getElementById('filterCourseMention').value = p.mention; }
		if (p.niveau !== undefined && p.niveau !== '') { document.getElementById('filterCourseNiveau').value = p.niveau; }
		if (p.jour) { document.getElementById('jourSelect').value = p.jour; }
		if (p.heure) {
			edtChoisir('heureDebutSelect', p.heure);
			const [h, mi] = p.heure.split(':').map(Number);
			const fin = Math.min(h * 60 + mi + 120, 21 * 60);
			edtChoisir('heureFinSelect', String(Math.floor(fin / 60)).padStart(2, '0') + ':' + String(fin % 60).padStart(2, '0'));
		}
		await Promise.all([loadCours(), loadSalles(), loadTeachers()]);
		if (p.salle) { document.getElementById('salleSelect').value = p.salle; }
		if (p.prof) { document.getElementById('teacherSelect').value = p.prof; }
		const champ = document.getElementById('searchCours');
		if (champ) { champ.focus(); }
	}

	function closeModal() {
		const m = document.getElementById('addModal');
		m.classList.add('hidden');
		m.classList.remove('flex');
		document.getElementById('addSeanceForm').reset();
		document.getElementById('conflictsZone').classList.add('hidden');
	}

	/* ---------------------------------------------------------------------
	   Salles

	   La liste est rendue par le serveur (#edtSallesCorps) et remplacee au
	   rafraichissement. Le formulaire prend sa place le temps d'une saisie :
	   une seule chose a l'ecran a la fois.
	   --------------------------------------------------------------------- */
	function edtOuvrirSalles() {
		const m = document.getElementById('sallesModal');
		edtSallesMontrerFormulaire(false);
		m.classList.remove('hidden');
		m.classList.add('flex');
		const fermer = m.querySelector('[aria-label="Fermer"]');
		if (fermer) { fermer.focus(); }
	}

	function edtFermerSalles() {
		const m = document.getElementById('sallesModal');
		m.classList.add('hidden');
		m.classList.remove('flex');
	}

	function edtSallesMontrerFormulaire(oui) {
		const f = document.getElementById('salleForm');
		const liste = document.getElementById('edtSallesCorps');
		if (f) { f.hidden = !oui; }
		if (liste) { liste.hidden = !!(f && oui); }
	}

	function edtErreurSalle(texte) {
		const p = document.getElementById('salleFormErreur');
		if (!p) { return; }
		p.textContent = texte;
		p.hidden = !texte;
	}

	function edtNouvelleSalle() { edtRemplirFormSalle(null); }

	function edtEditerSalle(id) {
		const ligne = Array.prototype.find.call(
			document.querySelectorAll('#edtSallesCorps tr[data-salle]'),
			l => JSON.parse(l.dataset.salle).id === Number(id));
		if (ligne) { edtRemplirFormSalle(JSON.parse(ligne.dataset.salle)); }
	}

	/** @param {object|null} s  la salle a modifier, null pour en creer une */
	function edtRemplirFormSalle(s) {
		const f = document.getElementById('salleForm');
		if (!f) { return; }
		f.reset();
		document.getElementById('salleFormTitre').textContent =
			s ? 'Modifier la salle ' + (s.salle_code || s.salle_nom) : 'Nouvelle salle';
		['id', 'salle_code', 'salle_nom', 'capacite', 'type_salle', 'batiment', 'etage', 'equipements'].forEach(c => {
			const el = f.elements[c];
			if (!el) { return; }
			const v = s && s[c] !== null && s[c] !== undefined ? String(s[c]) : '';
			if (el.tagName === 'SELECT') {
				// Une valeur ancienne hors de la liste reste affichee telle quelle.
				if (v !== '' && !Array.prototype.some.call(el.options, o => o.value === v)) {
					const o = document.createElement('option');
					o.value = v; o.textContent = v;
					el.appendChild(o);
				}
				el.value = v;
				if (el.selectedIndex < 0) { el.selectedIndex = 0; }
				return;
			}
			el.value = v;
		});
		f.elements.disponible.checked = !s || Number(s.disponible) === 1;
		edtErreurSalle('');
		edtSallesMontrerFormulaire(true);
		f.elements.salle_code.focus();
	}

	function edtAnnulerSalle() {
		edtErreurSalle('');
		edtSallesMontrerFormulaire(false);
	}

	/** La grille passe a la vue « une salle » sur celle-ci. */
	function edtVoirSalle(id) {
		const radio = document.querySelector('#edtFiltres input[name=vue][value=salle]');
		if (radio) { radio.checked = true; }
		const sel = document.getElementById('filterSalle');
		if (sel) { sel.value = String(id); }
		edtFermerSalles();
		edtAppliquerVue(true);
	}

	/* Ecouteur pose sur le formulaire lui-meme, pas delegue au document : il
	   doit passer avant ceux de src/js/transitions.js (rideau) et de
	   src/js/zone-ajax.js, qui s'ecartent quand l'envoi est deja pris en main. */
	const edtFormSalle = document.getElementById('salleForm');
	if (edtFormSalle) edtFormSalle.addEventListener('submit', async function (e) {
		e.preventDefault();
		const f = e.currentTarget;
		const code = f.elements.salle_code.value.trim();
		const places = f.elements.capacite.value.trim();
		if (code === '') {
			edtErreurSalle('Le code de la salle est obligatoire.');
			f.elements.salle_code.focus();
			return;
		}
		if (!/^\d{1,4}$/.test(places) || Number(places) > 2000) {
			edtErreurSalle('Places : un nombre entre 0 et 2000.');
			f.elements.capacite.focus();
			return;
		}

		const corps = new FormData(f);
		// Case decochee : absente du formulaire, on l'ecrit explicitement.
		if (!f.elements.disponible.checked) { corps.set('disponible', '0'); }
		const creation = !f.elements.id.value;
		const bouton = document.getElementById('salleFormEnvoyer');
		bouton.disabled = true;
		edtErreurSalle('');
		try {
			const r = await edtApi(creation ? 'add_salle' : 'update_salle', corps);
			if (!r) { return; }
			if (!r.success) { edtErreurSalle(r.error || 'La salle n’a pas été enregistrée.'); return; }
			edtDire(creation ? 'Salle ' + code + ' ajoutée.' : 'Salle ' + code + ' enregistrée.', 'success');
			const n = Number(r.a_deplacer) || 0;
			if (n > 0) {
				edtDire(n > 1
					? n + ' séances de l’année sont encore dans cette salle : elles sont signalées à déplacer.'
					: 'Une séance de l’année est encore dans cette salle : elle est signalée à déplacer.', 'warning', 9000);
			}
			await edtRafraichir();
			edtSallesMontrerFormulaire(false);
		} catch (err) {
			edtErreurSalle('La salle n’a pas été enregistrée. Vérifiez la connexion et réessayez.');
		} finally {
			bouton.disabled = false;
		}
	});

	// Echap : referme le formulaire, puis la fenetre ; clic sur le fond : la ferme.
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		const m = document.getElementById('sallesModal');
		if (!m || m.classList.contains('hidden')) { return; }
		const f = document.getElementById('salleForm');
		if (f && !f.hidden) { edtAnnulerSalle(); } else { edtFermerSalles(); }
	});
	document.addEventListener('click', function (e) {
		if (e.target && e.target.id === 'sallesModal') { edtFermerSalles(); }
	});

	/** L1-L3, M1-M2, RàN : le code ecrivait « L » devant tout niveau. */
	function edtNiveauCourt(n) {
		n = parseInt(n, 10);
		if (n === 0) { return 'RàN'; }
		if (n >= 1 && n <= 3) { return 'L' + n; }
		if (n >= 4 && n <= 5) { return 'M' + (n - 3); }
		return String(isNaN(n) ? '' : n);
	}

	/** Choisit une valeur dans un <select>, en l'ajoutant si la liste ne l'a pas. */
	function edtChoisir(id, valeur) {
		const select = document.getElementById(id);
		if (!select || !valeur) { return; }
		if (!Array.prototype.some.call(select.options, o => o.value === valeur)) {
			const o = document.createElement('option');
			o.value = valeur; o.textContent = valeur;
			select.appendChild(o);
		}
		select.value = valeur;
	}

	/**
	 * Liste des salles : code, nom et capacite ; une salle indisponible reste
	 * visible mais ne se choisit pas, sauf si c'est celle de la seance.
	 */
	function edtRemplirSalles(id, salles, actuelle) {
		edtRemplirSelect(id, '-- Sélectionner --', salles, s => s.id, s =>
			(s.salle_code || s.salle_nom) + (s.salle_code && s.salle_nom && s.salle_nom !== s.salle_code ? ' — ' + s.salle_nom : '')
			+ (Number(s.capacite) > 0 ? ' · ' + s.capacite + ' places' : '')
			+ (Number(s.disponible) !== 1 ? ' · indisponible' : ''));
		const select = document.getElementById(id);
		salles.forEach(s => {
			if (Number(s.disponible) === 1 || String(s.id) === String(actuelle || '')) { return; }
			const o = select.querySelector('option[value="' + CSS.escape(String(s.id)) + '"]');
			if (o) { o.disabled = true; }
		});
	}

	/* ---------------------------------------------------------------------
	   La vue : ce que montre la grille

	   Les champs utiles a la vue choisie s'affichent (data-vues) ; changer un
	   champ met l'adresse a jour et redemande la page, qui filtre et dispose les
	   seances cote serveur.
	   --------------------------------------------------------------------- */
	function edtVueChoisie() {
		const r = document.querySelector('#edtFiltres input[name=vue]:checked');
		return r ? r.value : 'tout';
	}

	function edtAfficherChampsDeVue() {
		const vue = edtVueChoisie();
		document.querySelectorAll('#edtFiltres [data-vues]').forEach(el => {
			const visible = el.dataset.vues.split(' ').indexOf(vue) !== -1;
			if (el.tagName === 'OPTION') { el.hidden = !visible; el.disabled = !visible; }
			else { el.hidden = !visible; }
		});
	}

	function edtAdresseDeVue() {
		const vue = edtVueChoisie();
		const u = new URL(window.location.href);
		['vue', 'mention', 'niveau', 'semestre', 'type', 'salle', 'prof'].forEach(k => u.searchParams.delete(k));
		const lire = id => { const el = document.getElementById(id); return el ? el.value : ''; };
		const poser = (k, val) => { if (val !== '' && val !== null && val !== undefined) { u.searchParams.set(k, val); } };
		if (vue !== 'tout') { poser('vue', vue); }
		if (vue === 'tout' || vue === 'classe') { poser('mention', lire('filterMention')); poser('niveau', lire('filterNiveau')); }
		if (vue === 'salle') { poser('salle', lire('filterSalle')); }
		if (vue === 'enseignant') { poser('prof', lire('filterProf')); }
		poser('semestre', lire('filterSemester'));
		poser('type', lire('filterTypeSeance'));
		return u;
	}

	let edtVueEnCours = null;
	async function edtAppliquerVue(empiler) {
		edtAfficherChampsDeVue();
		const u = edtAdresseDeVue();
		if (empiler) { window.history.pushState({ vue: true }, '', u.toString()); }
		else { window.history.replaceState({ vue: true }, '', u.toString()); }
		const zone = document.getElementById('edtZone');
		if (zone) { zone.setAttribute('aria-busy', 'true'); }
		const jeton = edtVueEnCours = {};
		try {
			await edtRafraichir();
			if (jeton !== edtVueEnCours) { return; }
			edtRemplirFiltresDepuisAdresse();
		} catch (err) {
			edtDire('L’affichage n’a pas pu être mis à jour. Rechargez la page.', 'error');
		} finally {
			const z = document.getElementById('edtZone');
			if (z) { z.removeAttribute('aria-busy'); }
		}
	}

	/* Les valeurs retenues par le serveur (premiere classe, premiere salle...)
	   reviennent dans la grille recue : les champs s'y alignent. */
	function edtRemplirFiltresDepuisAdresse() {
		const u = new URL(window.location.href);
		const g = document.getElementById('edtGrille');
		const vue = (g && g.dataset.vue) || u.searchParams.get('vue') || 'tout';
		const radio = document.querySelector('#edtFiltres input[name=vue][value="' + CSS.escape(vue) + '"]');
		if (radio) { radio.checked = true; }
		const regler = (id, val) => { const el = document.getElementById(id); if (el && val !== null && val !== undefined) { el.value = val; } };
		regler('filterMention', g ? g.dataset.mention : (u.searchParams.get('mention') || ''));
		regler('filterNiveau', g ? g.dataset.niveau : (u.searchParams.get('niveau') || ''));
		if (g && g.dataset.salle) { regler('filterSalle', g.dataset.salle); }
		if (g && g.dataset.prof) { regler('filterProf', g.dataset.prof); }
		regler('filterSemester', u.searchParams.get('semestre') || '');
		regler('filterTypeSeance', u.searchParams.get('type') || '');
	}

	/** Remplit un <select> sans interpoler de HTML. */
	function edtRemplirSelect(id, vide, elements, valeurDe, texteDe) {
		const select = document.getElementById(id);
		if (!select) { return; }
		select.innerHTML = '';
		const p = document.createElement('option');
		p.value = '';
		p.textContent = vide;
		select.appendChild(p);
		(elements || []).forEach(el => {
			const o = document.createElement('option');
			o.value = valeurDe(el);
			o.textContent = texteDe(el);
			select.appendChild(o);
		});
	}

	async function openEditModal(id) {
		const modal = document.getElementById('editModal');
		modal.classList.remove('hidden');
		modal.classList.add('flex');

		try {
			const s = await edtApi('get_seance', null, { id: id });
			if (!s) { return; }
			if (s.error) { edtDire(s.error, 'error'); closeEditModal(); return; }

			document.getElementById('editSeanceId').value = s.id;

			// textContent et non innerHTML : l'intitule vient de la base.
			const info = document.getElementById('editCoursInfo');
			info.textContent = '';
			const sigle = document.createElement('span');
			sigle.className = 'text-cyan-400';
			sigle.textContent = s.cours_sigle;
			info.appendChild(sigle);
			info.appendChild(document.createTextNode(' — ' + s.cours_title + ' '));
			const promo = document.createElement('span');
			promo.className = 'text-slate-400';
			promo.textContent = '(' + s.mention + ' ' + edtNiveauCourt(s.niveau) + (s.semester ? ', semestre ' + s.semester : '') + ')';
			info.appendChild(promo);

			document.getElementById('editTypeSeance').value = s.type_seance;
			document.getElementById('editJour').value = s.jour_semaine;
			edtChoisir('editHeureDebut', String(s.heure_debut).substring(0, 5));
			edtChoisir('editHeureFin', String(s.heure_fin).substring(0, 5));
			await loadEditSalles(s.salle_id);
			await loadEditTeachers(s.id_teacher);
			document.getElementById('editSalle').value = s.salle_id || '';
			document.getElementById('editTeacher').value = s.id_teacher || '';
			document.getElementById('editRemarque').value = s.remarque || '';

			await loadParcours(s.mention, 'editParcours');
			document.getElementById('editParcours').value = s.parcours || '';
		} catch (err) {
			edtDire('Impossible de charger cette séance.', 'error');
			closeEditModal();
		}
	}

	function closeEditModal() {
		const m = document.getElementById('editModal');
		m.classList.add('hidden');
		m.classList.remove('flex');
		document.getElementById('editSeanceForm').reset();
		document.getElementById('editConflictsZone').classList.add('hidden');
	}

	async function loadEditSalles(actuelle) {
		const salles = await edtApi('get_salles');
		if (!salles) { return; }
		edtRemplirSalles('editSalle', salles, actuelle);
	}

	async function loadEditTeachers(actuel) {
		const profs = await edtApi('get_teachers');
		if (!profs) { return; }
		edtRemplirSelect('editTeacher', '-- Aucun --',
			profs.filter(t => !Number(t.retire) || String(t.uid) === String(actuel || '')),
			t => t.uid, t => (t.name + ' ' + t.lastName).trim() + (Number(t.retire) ? ' (retiré)' : ''));
	}

	/* Deplacer l'heure de debut deplace la fin d'autant : on conserve la duree,
	   au lieu de la recalculer a l'heure pleine comme auparavant. */
	function edtDecalerFin(idDebut, idFin) {
		const debut = document.getElementById(idDebut);
		const fin = document.getElementById(idFin);
		if (!debut || !fin) { return; }

		let duree = 60;
		const enMinutes = v => {
			const m = /^(\d{1,2}):(\d{2})/.exec(v || '');
			return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;
		};
		const versTexte = m =>
			String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

		debut.addEventListener('focus', function () {
			const d = enMinutes(debut.value), f = enMinutes(fin.value);
			if (d !== null && f !== null && f > d) { duree = f - d; }
		});
		debut.addEventListener('change', function () {
			const d = enMinutes(debut.value);
			if (d === null) { return; }
			fin.value = versTexte(d + duree);
		});
	}
	edtDecalerFin('editHeureDebut', 'editHeureFin');
	edtDecalerFin('heureDebutSelect', 'heureFinSelect');

	/** Affiche les conflits dans une fenetre, en texte. */
	function edtAfficherConflits(idZone, idListe, conflits) {
		const zone = document.getElementById(idZone);
		const liste = document.getElementById(idListe);
		if (!zone || !liste) { return; }

		liste.innerHTML = '';
		if (!conflits || !conflits.length) {
			const ok = document.createElement('div');
			ok.className = 'text-green-400 text-sm';
			ok.textContent = '✓ Aucun conflit détecté';
			liste.appendChild(ok);
		} else {
			conflits.forEach(c => {
				const l = document.createElement('div');
				l.className = (c.severity === 'error' ? 'text-red-400' : 'text-yellow-400')
					+ ' text-sm mb-1';
				l.textContent = (c.severity === 'error' ? '✗ ' : '⚠ ') + c.message;
				liste.appendChild(l);
			});
		}
		zone.classList.remove('hidden');
	}

	document.getElementById('editSeanceForm').addEventListener('submit', async function (e) {
		e.preventDefault();
		await edtEnvoyerFiche(this, 'update_seance', false);
	});

	document.getElementById('addSeanceForm').addEventListener('submit', async function (e) {
		e.preventDefault();
		await edtEnvoyerFiche(this, 'add_seance', false);
	});

	async function edtEnvoyerFiche(form, action, force) {
		const bouton = form.querySelector('button[type="submit"]');
		const libelle = bouton ? bouton.textContent : '';
		if (bouton) { bouton.disabled = true; bouton.textContent = 'Enregistrement…'; }

		try {
			const corps = new FormData(form);
			if (force) { corps.set('force', '1'); }

			const r = await edtApi(action, corps);
			if (!r) { return; }

			if (r.success) {
				edtDire(action === 'add_seance' ? 'Séance ajoutée.' : 'Séance modifiée.', 'success');
				if (action === 'add_seance') { closeModal(); } else { closeEditModal(); }
				await edtRafraichir();
				return;
			}

			if (r.conflicts && r.conflicts.length) {
				const zone = action === 'add_seance'
					? ['conflictsZone', 'conflictsList'] : ['editConflictsZone', 'editConflictsList'];
				edtAfficherConflits(zone[0], zone[1], r.conflicts);

				if (r.forcable && await edtDemander({ title: 'Conflit d\'horaire', confirmText: 'Enregistrer quand même' }, 'Conflit :\n\n' + edtTexteConflits(r.conflicts)
					+ '\n\nEnregistrer quand même ?')) {
					if (bouton) { bouton.disabled = false; bouton.textContent = libelle; }
					await edtEnvoyerFiche(form, action, true);
					return;
				}
				edtDire('Enregistrement refusé : conflit non résolu.', 'error');
				return;
			}

			edtDire(r.error || "L'enregistrement a échoué.", 'error', 9000);
		} catch (err) {
			edtDire('Le serveur n’a pas répondu. Rien n’a été enregistré.', 'error');
		} finally {
			if (bouton) { bouton.disabled = false; bouton.textContent = libelle; }
		}
	}

	/* ---------------------------------------------------------------------
	   Fenetre d'ajout : choix du cours
	   --------------------------------------------------------------------- */
	let allCoursCache = null;

	async function loadAllCours() {
		if (allCoursCache) { return allCoursCache; }
		const r = await edtApi('get_cours');
		allCoursCache = r || [];
		return allCoursCache;
	}

	async function loadCours() {
		const mention = document.getElementById('filterCourseMention').value;
		const niveau = document.getElementById('filterCourseNiveau').value;
		const search = document.getElementById('searchCours').value.trim().toLowerCase();

		const tous = await loadAllCours();

		let retenus = tous.filter(c => {
			if (mention && c.dep_desc !== mention) { return false; }
			if (niveau && String(c.yearlevel) !== String(niveau)) { return false; }
			if (search.length >= 2) {
				const s = (c.Sigle + ' ' + c.title + ' ' + c.dep_desc).toLowerCase();
				if (!s.includes(search)) { return false; }
			}
			return true;
		});

		if (search.length >= 2) {
			retenus.sort((a, b) => {
				const aS = a.Sigle.toLowerCase().startsWith(search) ? 0 : 1;
				const bS = b.Sigle.toLowerCase().startsWith(search) ? 0 : 1;
				if (aS !== bS) { return aS - bS; }
				const aT = a.title.toLowerCase().startsWith(search) ? 0 : 1;
				const bT = b.title.toLowerCase().startsWith(search) ? 0 : 1;
				return aT - bT;
			});
		}

		document.getElementById('searchCoursCount').textContent = retenus.length + ' cours';

		const affiches = retenus.slice(0, 100);
		edtRemplirSelect('coursSelect', '-- Sélectionner un cours --', affiches,
			c => c.id,
			c => c.Sigle + ' — ' + c.title + ' (' + c.dep_desc + ' ' + edtNiveauCourt(c.yearlevel) + (c.semester ? ', S' + c.semester : '') + ')');

		if (retenus.length > 100) {
			const o = document.createElement('option');
			o.disabled = true;
			o.textContent = '… et ' + (retenus.length - 100) + ' autres (affinez votre recherche)';
			document.getElementById('coursSelect').appendChild(o);
		}
	}

	let edtDelaiRecherche = null;
	document.getElementById('searchCours').addEventListener('input', function () {
		clearTimeout(edtDelaiRecherche);
		edtDelaiRecherche = setTimeout(loadCours, 300);
	});
	document.getElementById('filterCourseMention').addEventListener('change', loadCours);
	document.getElementById('filterCourseNiveau').addEventListener('change', loadCours);
	document.getElementById('resetFilters').addEventListener('click', function () {
		document.getElementById('searchCours').value = '';
		document.getElementById('filterCourseMention').value = '';
		document.getElementById('filterCourseNiveau').value = '';
		loadCours();
	});

	document.getElementById('coursSelect').addEventListener('change', async function () {
		if (!this.value) {
			edtRemplirSelect('parcoursSelect', '-- Tous les parcours de la mention --', [], () => '', () => '');
			return;
		}
		// Le cache evite de redemander les 471 cours a chaque selection.
		const tous = await loadAllCours();
		const choisi = tous.find(c => String(c.id) === String(this.value));
		if (choisi) { await loadParcours(choisi.dep_desc, 'parcoursSelect'); }
	});

	async function loadParcours(mention, selectId) {
		const p = await edtApi('get_parcours', null, { mention: mention || '' });
		if (!p) { return; }
		edtRemplirSelect(selectId, '-- Tous les parcours de la mention --', p,
			x => x.nom, x => x.nom);
	}

	async function loadSalles() {
		const salles = await edtApi('get_salles');
		if (!salles) { return; }
		edtRemplirSalles('salleSelect', salles, null);
	}

	async function loadTeachers() {
		const profs = await edtApi('get_teachers');
		if (!profs) { return; }
		edtRemplirSelect('teacherSelect', '-- Enseignant du cours --', profs.filter(t => !Number(t.retire)),
			t => t.uid, t => (t.name + ' ' + t.lastName).trim());
	}

	/** Verification a la demande, depuis la fenetre d'ajout. */
	async function checkConflicts() {
		const form = document.getElementById('addSeanceForm');
		const corps = new FormData(form);

		const coursId = corps.get('cours_id');
		if (!coursId) { edtDire('Choisissez d’abord un cours.', 'warning'); return; }

		const tous = await loadAllCours();
		const cours = tous.find(c => String(c.id) === String(coursId));
		if (cours) {
			corps.set('mention', cours.dep_desc);
			corps.set('niveau', cours.yearlevel);
			corps.set('semester', cours.semester || '');
			if (!corps.get('parcours')) { corps.set('parcours', cours.parcours || ''); }
		}

		try {
			const r = await edtApi('check_conflicts', corps);
			if (!r) { return; }
			edtAfficherConflits('conflictsZone', 'conflictsList', r.conflicts);
		} catch (err) {
			edtDire('Vérification impossible : le serveur n’a pas répondu.', 'error');
		}
	}

	/* ---------------------------------------------------------------------
	   Suppression
	   --------------------------------------------------------------------- */
	async function deleteSeance(id) {
		// Action irreversible : on demande confirmation avant, pas de regret apres.
		if (!(await edtDemander({ title: 'Supprimer la séance', confirmText: 'Supprimer', danger: true }, 'Supprimer définitivement cette séance ?'))) { return; }

		const corps = new FormData();
		corps.set('id', id);

		try {
			const r = await edtApi('delete_seance', corps);
			if (!r) { return; }

			if (r.success) {
				edtDire('Séance supprimée.', 'success');
				await edtRafraichir();
			} else {
				edtDire(r.error || 'La suppression a échoué.', 'error');
			}
		} catch (err) {
			edtDire('Le serveur n’a pas répondu. La séance n’a pas été supprimée.', 'error');
		}
	}

	// Les champs de la vue affichee (le serveur a deja filtre la grille).
	edtAfficherChampsDeVue();
	</script>
</body>
</html>
