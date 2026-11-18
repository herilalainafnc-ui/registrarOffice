<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Paramètres — Mentions</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/settings.mentions.css?v=20260923-1">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Réglage des mentions de l'université (table filiere)

   POURQUOI CETTE PAGE N'EST PAS UN SIMPLE CRUD
   --------------------------------------------
   La table filiere n'a aucune contrainte de clé étrangère, mais son sigle sert
   de clé dans cinq tables et son libellé dans une sixième — plus de quatre
   mille lignes au total. Renommer un sigle les orphelinerait en silence ;
   renommer un libellé ferait échouer la recherche du barème à l'inscription,
   puisque plusieurs requêtes comparent « filiere_description = etude_envisage ».

   La page affiche donc en permanence ce qui se rattache à chaque mention, ferme
   visiblement les actions impossibles au lieu de les masquer, et propage un
   renommage de libellé aux fiches étudiantes dans la même transaction.

   Voir data/mentions.php pour les garde-fous.
   ========================================================================== */

require_once(__DIR__ . '/../data/mentions.php');

$mentions = mentionsListe($dtb);
$champs   = mentionsChamps();
$libDeps  = mentionsLibellesDependances();

$sigleOrphelins   = mentionsOrphelines($dtb);
$libelleOrphelins = mentionsLibellesOrphelins($dtb);
$parcoursOrphelins = mentionParcoursLibellesOrphelins($dtb);

$nonPubliques = array_values(array_filter($mentions, static fn ($m) => !$m['publique']));

/* CE QUE LE FORMULAIRE PUBLIC PROPOSERA REELLEMENT
   ------------------------------------------------
   Les mentions y etaient ecrites en dur a trois endroits : creer une mention
   ici ne la faisait pas apparaitre la-bas, et en fermer une ne l'en retirait
   pas. Elles en viennent maintenant (data/parcours.php), filtrees sur
   l'ouverture, avec leurs parcours ranges par cycle.

   Consequence a montrer ici : une mention ne prepare un diplome que si l'un de
   ses parcours porte le cycle correspondant. Tout marquer « Licence
   seulement » ferme donc le Master aux candidats — ce qui doit se lire au
   moment ou on le regle, pas se decouvrir sur le site public. */
$publiques    = parcoursMentionsPubliques();
$sansMaster   = [];
$sansLicence  = [];
$sansParcours = [];

foreach ($publiques as $p) {
    if ($p['parcours']['L'] === [] && $p['parcours']['M'] === []) {
        $sansParcours[] = $p['libelle'];
        continue;
    }
    if ($p['parcours']['M'] === []) { $sansMaster[] = $p['libelle']; }
    if ($p['parcours']['L'] === []) { $sansLicence[] = $p['libelle']; }
}
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

				<div class="back flex-1 overflow-y-auto">
					<div class="mn-page">

						<div class="mn-entete">
							<div class="mn-entete__icone"><i class="bi bi-mortarboard-fill"></i></div>
							<div>
								<h1 class="mn-titre">Mentions de l'université</h1>
								<p class="mn-sous">
<?php $nbFermees = count(array_filter($mentions, static fn ($x) => empty($x['ouverte']))); ?>
									<?= count($mentions) ?> mention(s)<?= $nbFermees > 0
										? ', dont ' . $nbFermees . ' fermée' . ($nbFermees > 1 ? 's' : '') . ' aux inscriptions'
										: '' ?>.
									Le sigle sert de clé dans cinq tables, le libellé rattache les fiches étudiantes.
								</p>
							</div>
						</div>

						<div class="mn-note">
							<i class="bi bi-info-circle-fill"></i>
							<div>
								<b>Le sigle ne se modifie plus dès qu'il est utilisé.</b>
								Il est recopié dans les inscriptions, les lignes financières, les cours,
								le barème et les parcours — sans contrainte de clé étrangère, donc le
								changer orphelinerait ces lignes sans avertissement.
								Le <b>libellé</b>, lui, peut être corrigé : les fiches étudiantes qui s'y
								rattachent sont mises à jour en même temps, et leur nombre vous est annoncé
								avant confirmation.
							</div>
						</div>

						<div class="mn-note">
							<i class="bi bi-globe2"></i>
							<div>
								<b>Le formulaire public de pré-inscription lit cette page.</b>
								Les <?= count($publiques) ?> mention(s) ouvertes y sont proposées, chacune
								avec ses parcours, dans le cycle réglé plus bas. Fermer une mention l'en
								retire<?php if ($nonPubliques !== []) { ?> — c'est aujourd'hui le cas de
								<?= e(implode(', ', array_column($nonPubliques, 'filiere_description'))) ?><?php } ?>.
								Un candidat ne voit que les parcours du diplôme qu'il demande.
							</div>
						</div>

<?php if ($sansMaster !== [] || $sansLicence !== [] || $sansParcours !== []) { ?>
						<?php /* Le réglage des cycles décide de ce que le site public
						         accepte : le dire ici évite de le découvrir par une
						         candidature qui n'arrive jamais. */ ?>
						<div class="mn-note mn-note--alerte">
							<i class="bi bi-exclamation-triangle-fill"></i>
							<div>
								<b>Ce que le formulaire public ne proposera pas.</b>
								Une mention ne prépare un diplôme que si l'un de ses parcours porte le
								cycle correspondant.
								<ul class="mn-anomalie__liste">
<?php if ($sansMaster !== []) { ?>
									<li>
										<b>Aucun Master :</b> <?= e(implode(', ', $sansMaster)) ?>.
										Ces mentions s'affichent « Licence seulement » sur le site.
										Pour y ouvrir un Master, créez son parcours ou passez un
										parcours existant en « Licence et master ».
									</li>
<?php } ?>
<?php if ($sansLicence !== []) { ?>
									<li>
										<b>Aucune Licence :</b> <?= e(implode(', ', $sansLicence)) ?>.
										Elles s'affichent « Master seulement ».
									</li>
<?php } ?>
<?php if ($sansParcours !== []) { ?>
									<li>
										<b>Aucun parcours :</b> <?= e(implode(', ', $sansParcours)) ?>.
										Ces mentions apparaissent grisées : un candidat n'aurait rien
										à choisir, la candidature est donc refusée.
									</li>
<?php } ?>
								</ul>
							</div>
						</div>
<?php } ?>

						<?php /* Création. Placée avant la liste : c'est l'action qu'on vient
						         chercher ici, la consultation vient ensuite. */ ?>
						<div class="mn-ajout">
							<div class="mn-ajout__titre">
								<i class="bi bi-plus-circle-fill"></i> Nouvelle mention
							</div>
							<p class="mn-aide" style="margin-bottom:.7rem">
								Le sigle est définitif en pratique : choisissez-le avec soin.
								2 à 4 lettres majuscules, sans accent ni chiffre.
							</p>
							<div class="mn-champs" id="mnNouvelle">
								<div class="mn-champ">
									<label for="mnNewSigle">Sigle</label>
									<input type="text" id="mnNewSigle" class="mn-input" maxlength="4"
									       placeholder="AGRO" autocomplete="off"
									       style="text-transform:uppercase">
								</div>
								<div class="mn-champ">
									<label for="mnNewFr">Libellé (français)</label>
									<input type="text" id="mnNewFr" class="mn-input" maxlength="100"
									       placeholder="Agronomie" autocomplete="off">
								</div>
								<div class="mn-champ">
									<label for="mnNewEn">Libellé (anglais)</label>
									<input type="text" id="mnNewEn" class="mn-input" maxlength="255"
									       placeholder="Agronomy" autocomplete="off">
								</div>
								<div class="mn-champ">
									<label for="mnNewDs">Sigle du domaine</label>
									<input type="text" id="mnNewDs" class="mn-input" maxlength="10"
									       placeholder="STECH" autocomplete="off">
								</div>
								<div class="mn-champ">
									<label for="mnNewDn">Domaine</label>
									<input type="text" id="mnNewDn" class="mn-input" maxlength="200"
									       placeholder="Science et Technologie" autocomplete="off">
								</div>
							</div>
							<div class="mn-actions">
								<button type="button" class="mn-btn mn-btn--primaire" id="mnCreer">
									<i class="bi bi-check2"></i> Créer la mention
								</button>
							</div>
						</div>

						<div class="mn-grille" id="mnGrille">
<?php foreach ($mentions as $m) {
	$u = $m['usage'];
	$sigle = (string) $m['filiere_sigle'];
?>
							<div class="mn-carte<?= empty($m['ouverte']) ? ' mn-carte--fermee' : '' ?>"
							     data-sigle="<?= e($sigle) ?>"
							     data-libelle="<?= e((string) $m['filiere_description']) ?>"
							     data-etudiants="<?= (int) $u['tbl_2024_etudiant'] ?>"
							     data-ouverte="<?= empty($m['ouverte']) ? '0' : '1' ?>"
							     data-ouvert="0">

								<div class="mn-carte__tete">
									<span class="mn-sigle"><?= e($sigle) ?></span>
									<div style="flex:1;min-width:0">
										<div class="mn-nom js-mn-nom"><?= e((string) $m['filiere_description']) ?></div>
										<div class="mn-domaine js-mn-domaine">
											<?= $m['domaine_name'] !== '' && $m['domaine_name'] !== '0'
												? e((string) $m['domaine_name'])
												: '<em>domaine non renseigné</em>' ?>
										</div>
									</div>
<?php if (empty($m['ouverte'])) { ?>
										<span class="mn-pastille mn-pastille--fermee"
										      title="Ne sera plus proposée pour un nouvel étudiant, un nouveau cours ni une pré-inscription. Les données existantes ne changent pas.">
											<i class="bi bi-lock-fill"></i> Fermée
										</span>
<?php } ?>
									<span class="mn-pastille <?= $m['publique'] ? 'mn-pastille--publique' : 'mn-pastille--privee' ?>"
									      title="<?= $m['publique']
											? 'Proposée sur le formulaire public de pré-inscription'
											: 'Absente du formulaire public de pré-inscription' ?>">
										<?= $m['publique'] ? 'Pré-inscription' : 'Interne' ?>
									</span>
								</div>

								<?php /* Les compteurs restent visibles : ils expliquent à eux seuls
								         pourquoi le sigle est verrouillé et la suppression fermée. */ ?>
								<div class="mn-usages">
<?php
	foreach (['t_2024_inscription_session', 't_2024_etudiant_finace', 't_2023_cours',
	          't_2024_finance_detail_licence', 'filiere_parcours', 'tbl_2024_etudiant'] as $table) {
		$n = (int) ($u[$table] ?? 0);
?>
									<span class="mn-usage<?= $n === 0 ? ' mn-usage--zero' : '' ?>">
										<b><?= $n ?></b> <?= e($libDeps[$table] ?? $table) ?>
									</span>
<?php } ?>
								</div>

								<div class="mn-actions">
									<button type="button" class="mn-btn js-mn-modifier">
										<i class="bi bi-pencil"></i> Modifier
									</button>
									<?php /* Fermer plutôt que supprimer : réversible, et sans toucher
									         aux données. C'est la seule action possible sur une mention
									         qui porte des inscriptions — la suppression, elle, reste
									         fermée tant qu'une donnée s'y rattache. */ ?>
									<button type="button" class="mn-btn js-mn-ouverture">
										<?php if (empty($m['ouverte'])) { ?>
											<i class="bi bi-unlock"></i> Réouvrir
										<?php } else { ?>
											<i class="bi bi-lock"></i> Fermer
										<?php } ?>
									</button>
									<button type="button" class="mn-btn mn-btn--danger js-mn-supprimer"
									        <?= $m['supprimable'] ? '' : 'disabled' ?>
									        title="<?= $m['supprimable']
											? 'Aucune donnée ne s\'y rattache : la suppression est possible'
											: 'Des données s\'y rattachent : la suppression est fermée' ?>">
										<i class="bi bi-trash"></i> Supprimer
									</button>
								</div>

								<div class="mn-form" hidden>
									<div class="mn-champs">
										<div class="mn-champ">
											<label>Sigle</label>
											<input type="text" class="mn-input" value="<?= e($sigle) ?>"
											       disabled
											       title="<?= $m['sigle_modifiable']
													? 'Modifiable seulement en supprimant puis recréant la mention'
													: 'Utilisé comme clé par des données existantes' ?>">
											<p class="mn-aide<?= $m['sigle_modifiable'] ? '' : ' mn-aide--verrou' ?>">
												<?php if ($m['sigle_modifiable']) { ?>
													Aucune donnée n'utilise ce sigle, mais le modifier revient à
													créer une autre mention : supprimez celle-ci et recréez-la.
												<?php } else { ?>
													<i class="bi bi-lock-fill"></i>
													Verrouillé : <?= (int) $u['total'] ?> ligne(s) l'utilisent comme clé.
												<?php } ?>
											</p>
										</div>
<?php foreach ($champs as $col => $libelle) {
		$valeur = (string) ($m[$col] ?? '');
		// domaine_name porte « 0 » sur une mention : valeur héritée, pas un nom.
		if ($col === 'domaine_name' && $valeur === '0') { $valeur = ''; }
?>
										<div class="mn-champ">
											<label for="mn-<?= e($sigle) ?>-<?= e($col) ?>"><?= e($libelle) ?></label>
											<input type="text" class="mn-input js-mn-champ"
											       id="mn-<?= e($sigle) ?>-<?= e($col) ?>"
											       data-col="<?= e($col) ?>"
											       value="<?= e($valeur) ?>"
											       autocomplete="off">
<?php if ($col === 'filiere_description') { ?>
											<p class="mn-aide">
												<?= (int) $u['tbl_2024_etudiant'] ?> fiche(s) étudiante(s) se
												rattachent à ce libellé et seront mises à jour avec lui.
											</p>
<?php } ?>
										</div>
<?php } ?>
									</div>
									<div class="mn-actions">
										<button type="button" class="mn-btn mn-btn--primaire js-mn-enregistrer">
											<i class="bi bi-check2"></i> Enregistrer
										</button>
										<button type="button" class="mn-btn js-mn-annuler">Annuler</button>
									</div>
								</div>

								<?php /* PARCOURS DE LA MENTION.
								         Replié par défaut : neuf cartes portant chacune la liste
								         de ses parcours et un formulaire d'ajout noieraient les
								         compteurs. L'attribut hidden, plutôt qu'un display en
								         style : il retire aussi le bloc du parcours au clavier. */
								$parcours = mentionParcoursListe($dtb, $sigle); ?>
								<div class="mn-parcours">
									<button type="button" class="mn-parcours__bascule js-mn-parcours-bascule"
									        aria-expanded="false">
										<i class="bi bi-diagram-3"></i>
										<span><?= count($parcours) ?> parcours</span>
										<i class="bi bi-chevron-down mn-parcours__fleche"></i>
									</button>

									<div class="mn-parcours__corps" hidden>
<?php if ($parcours === []) { ?>
										<p class="mn-aide" style="margin:.3rem 0 .6rem">
											Aucun parcours. Les candidats ne pourront en choisir aucun
											pour cette mention sur le formulaire de pré-inscription.
										</p>
<?php } ?>
<?php $cyclesLibelles = mentionParcoursCycles();
foreach ($parcours as $p) {
		$up = $p['usage'];
		$code = (string) $p['shortcode'];
		$cycleActuel = mentionParcoursCyclesValides($p['cycles'] ?? 'L,M');
?>
										<div class="mn-pc" data-code="<?= e($code) ?>"
										     data-libelle="<?= e((string) $p['description']) ?>"
										     data-etudiants="<?= (int) $up['tbl_2024_etudiant'] ?>"
										     data-inscriptions="<?= (int) $up['t_2024_inscription_session'] ?>">
											<div class="mn-pc__tete">
												<span class="mn-pc__code"><?= e($code) ?></span>
												<span class="mn-pc__nom js-mn-pc-nom"><?= e((string) $p['description']) ?></span>
												<span class="mn-pc__cycle" title="Cycles où ce parcours est proposé"><?= e($cyclesLibelles[$cycleActuel] ?? $cycleActuel) ?></span>
												<span class="mn-pc__usages">
													<?= (int) $up['t_2023_cours'] ?> cours ·
													<?= (int) $up['tbl_2024_etudiant'] ?> étud. ·
													<?= (int) $up['t_2024_inscription_session'] ?> insc.
												</span>
												<span class="mn-pc__actions">
													<button type="button" class="mn-btn mn-btn--mini js-mn-pc-modifier"
													        title="Modifier les libellés">
														<i class="bi bi-pencil"></i>
													</button>
													<button type="button" class="mn-btn mn-btn--mini mn-btn--danger js-mn-pc-supprimer"
													        <?= $p['supprimable'] ? '' : 'disabled' ?>
													        title="<?= $p['supprimable']
															? 'Aucune donnée ne s\'y rattache : la suppression est possible'
															: 'Des données s\'y rattachent : la suppression est fermée' ?>">
														<i class="bi bi-trash"></i>
													</button>
												</span>
											</div>

											<div class="mn-pc__form" hidden>
												<div class="mn-champs">
<?php foreach (mentionParcoursChamps() as $col => $libelle) { ?>
													<div class="mn-champ">
														<label for="pc-<?= e($code) ?>-<?= e($col) ?>"><?= e($libelle) ?></label>
														<input type="text" class="mn-input js-mn-pc-champ"
														       id="pc-<?= e($code) ?>-<?= e($col) ?>"
														       data-col="<?= e($col) ?>"
														       maxlength="255"
														       value="<?= e((string) ($p[$col] ?? '')) ?>"
														       autocomplete="off">
													</div>
<?php } ?>
													<?php /* Les parcours de master ne sont pas ceux de la licence :
													         ce choix dit ou celui-ci est propose. Il ne touche pas aux
													         fiches deja enregistrees. */ ?>
													<div class="mn-champ">
														<label for="pc-<?= e($code) ?>-cycles">Proposé en</label>
														<select class="mn-input js-mn-pc-champ"
														        id="pc-<?= e($code) ?>-cycles" data-col="cycles">
														<?php foreach ($cyclesLibelles as $valeur => $libelleCycle) { ?>
															<option value="<?= e($valeur) ?>"<?= $valeur === $cycleActuel ? ' selected' : '' ?>><?= e($libelleCycle) ?></option>
														<?php } ?>
														</select>
													</div>
												</div>
												<p class="mn-aide<?= $up['shortcode_verrouille'] ? ' mn-aide--verrou' : '' ?>">
													<?php if ($up['shortcode_verrouille']) { ?>
														<i class="bi bi-lock-fill"></i>
														Code verrouillé : <?= (int) $up['t_2023_cours'] ?> cours l'utilisent.
													<?php } ?>
													<?php if ($up['libelle_suivi'] > 0) { ?>
														Renommer le libellé mettra à jour
														<?= (int) $up['tbl_2024_etudiant'] ?> fiche(s) et
														<?= (int) $up['t_2024_inscription_session'] ?> inscription(s).
													<?php } ?>
												</p>
												<div class="mn-actions">
													<button type="button" class="mn-btn mn-btn--primaire mn-btn--mini js-mn-pc-enregistrer">
														<i class="bi bi-check2"></i> Enregistrer
													</button>
													<button type="button" class="mn-btn mn-btn--mini js-mn-pc-annuler">Annuler</button>
												</div>
											</div>
										</div>
<?php } ?>

										<?php /* Ajout d'un parcours à cette mention. */ ?>
										<div class="mn-pc-ajout">
											<div class="mn-champs">
												<div class="mn-champ">
													<label for="pcnew-code-<?= e($sigle) ?>">Code</label>
													<input type="text" class="mn-input js-mn-pc-new"
													       id="pcnew-code-<?= e($sigle) ?>" data-col="shortcode"
													       maxlength="20" placeholder="<?= e(substr($sigle, 0, 1)) ?>_NOUVEAU"
													       autocomplete="off" style="text-transform:uppercase">
												</div>
												<div class="mn-champ">
													<label for="pcnew-fr-<?= e($sigle) ?>">Libellé (français)</label>
													<input type="text" class="mn-input js-mn-pc-new"
													       id="pcnew-fr-<?= e($sigle) ?>" data-col="description"
													       maxlength="255" placeholder="Nouveau parcours" autocomplete="off">
												</div>
												<div class="mn-champ">
													<label for="pcnew-en-<?= e($sigle) ?>">Libellé (anglais)</label>
													<input type="text" class="mn-input js-mn-pc-new"
													       id="pcnew-en-<?= e($sigle) ?>" data-col="description_ang"
													       maxlength="255" placeholder="New track" autocomplete="off">
												</div>
												<div class="mn-champ">
													<label for="pcnew-cy-<?= e($sigle) ?>">Proposé en</label>
													<select class="mn-input js-mn-pc-new"
													        id="pcnew-cy-<?= e($sigle) ?>" data-col="cycles">
													<?php foreach (mentionParcoursCycles() as $valeur => $libelleCycle) { ?>
														<option value="<?= e($valeur) ?>"><?= e($libelleCycle) ?></option>
													<?php } ?>
													</select>
												</div>
											</div>
											<div class="mn-actions">
												<button type="button" class="mn-btn mn-btn--mini js-mn-pc-creer">
													<i class="bi bi-plus-lg"></i> Ajouter ce parcours
												</button>
											</div>
										</div>
									</div>
								</div>
							</div>
<?php } ?>
						</div>

						<?php /* Anomalies : des références qui ne correspondent à aucune
						         mention. Invisibles jusqu'ici, elles expliquent des
						         inscriptions qui échouent. */ ?>
						<div class="mn-anomalies">
							<h2>Références sans mention</h2>
							<p class="mn-anomalies__sous">
								Données pointant vers une mention qui n'existe pas dans cette liste.
							</p>

<?php if ($sigleOrphelins === [] && $libelleOrphelins === [] && $parcoursOrphelins === []) { ?>
							<div class="mn-anomalie mn-anomalie--ok">
								<i class="bi bi-check-circle-fill"></i>
								<div>Aucune anomalie : toutes les références correspondent à une mention ou à un parcours.</div>
							</div>
<?php } ?>

<?php foreach ($sigleOrphelins as $o) { ?>
							<div class="mn-anomalie">
								<i class="bi bi-exclamation-triangle-fill"></i>
								<div>
									<code><?= e((string) $o['sigle']) ?></code> apparaît dans
									<code>filiere_parcours</code> (<?= (int) $o['parcours'] ?> parcours)
									mais n'est pas une mention. Ces parcours ne sont proposés nulle part.
								</div>
							</div>
<?php } ?>

<?php foreach ($libelleOrphelins as $o) { ?>
							<div class="mn-anomalie">
								<i class="bi bi-exclamation-triangle-fill"></i>
								<div>
									<?= (int) $o['etudiants'] ?> étudiant(s) portent le libellé de mention
									<code><?= e((string) $o['libelle']) ?></code>, qui ne correspond à aucune
									mention. La recherche du barème échoue pour ces fiches : à l'inscription,
									elles produisent « Configuration financière introuvable ».
								</div>
							</div>
<?php } ?>

<?php if ($parcoursOrphelins !== []) {
	$totalPc = array_sum(array_column($parcoursOrphelins, 'etudiants')); ?>
							<div class="mn-anomalie">
								<i class="bi bi-exclamation-triangle-fill"></i>
								<div>
									<b><?= (int) $totalPc ?> étudiant(s) portent un parcours qui n'existe pas.</b>
									Les jointures par libellé — relevé de notes, liste des cours offerts,
									fiche de contrôle — ne trouvent rien pour ces fiches.
									<ul class="mn-anomalie__liste">
<?php foreach ($parcoursOrphelins as $o) { ?>
										<li>
											<code><?= e((string) $o['libelle']) ?></code>
											— <?= (int) $o['etudiants'] ?> étudiant(s)
										</li>
<?php } ?>
									</ul>
									Corriger revient soit à créer le parcours manquant sous le bon code, soit
									à renommer un parcours existant vers ce libellé — le renommage propage aux
									fiches, ce qui les rattache d'un coup.
								</div>
							</div>
<?php } ?>
						</div>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

<script src="<?=$app_base?>/src/js/settings.mentions.js?v=20260923-1"></script>
</body>
</html>
