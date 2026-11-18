<?php
/* =============================================================================
   Cours offerts par session

   Refaite. L'ancienne version presentait 663 cours en lignes et les QUATRE
   sessions en colonnes de cases : 2 652 cases sur un ecran, sans moyen de voir
   d'un coup d'oeil ce qui etait offert.

   Le modele mental juste n'est pas « ce cours, dans quelles sessions ? » mais
   « cette session, quels cours ? ». La page suit ce sens : des onglets de
   session, UNE colonne de cases, et les cours regroupes par mention et niveau.

   Deux informations manquaient et coutaient du temps :
     - le semestre NATIF du cours, pour voir qu'on offre au second semestre un
       cours de premier — le cas meme que cette page sert a traiter ;
     - l'annee scolaire que le formulaire d'inscription regarde vraiment, qui
       pouvait differer de celle choisie ici. On cochait alors dans le vide.
   ========================================================================== */
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours offerts par session</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/cours-sessions.css?v=<?=@filemtime(__DIR__.'/css/cours-sessions.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<div class="back flex-1 overflow-y-auto p-4 lg:p-6">

<?php
/* Controle d'acces : superadmin et registraires, comme
   AdminController::manageCoursesSessions() et comme le point
   d'enregistrement. Les trois doivent dire la meme chose. */
$rolesAdmis = ['superadmin', 'registrar'];
$peutGerer = in_array((string) ($rg_user['privilege'] ?? ''), $rolesAdmis, true)
          || (int) ($rg_user['level'] ?? 99) === 1;

if (!$peutGerer):
?>
				<div class="bg-red-900 border border-red-700 text-red-100 p-4 rounded-lg">
					Accès refusé. Seuls le registrariat et les superadmins peuvent gérer les cours offerts.
				</div>
<?php else:

require_once __DIR__ . '/../data/cours.offerts.php';

$sessions = coursSessions();

/* Annee affichee : celle demandee, sinon celle que le formulaire
   d'inscription utilisera. Les deux pages lisent maintenant la meme
   fonction — c'est ce qui evite de preparer une annee que personne ne
   regarde. */
$anneeDemandee = trim((string) ($_GET['academic_year'] ?? ''));
$anneeInscription = coursAnneeScolaire($dtb);
$annee = $anneeDemandee !== '' ? $anneeDemandee : $anneeInscription;

/* Les cours, avec enseignant et parcours lisible. */
require_once(__DIR__ . '/../data/cours.sigle.php');
coursSigleAliasInstaller($dtb);
$cours = $dtb->query(
	"SELECT c.id, c.Sigle, c.sigle_affiche, c.title, c.dep_desc, c.parcours, c.yearlevel, c.semester,
	        t.name AS prof_nom, t.lastName AS prof_prenom
	 FROM t_2023_cours c
	 LEFT JOIN teacher t ON t.uid = c.id_teacher
	 WHERE c.remove != 1
	 ORDER BY c.dep_desc, c.yearlevel, c.title")->fetchAll(PDO::FETCH_ASSOC);

$parcoursNoms = [];
foreach ($dtb->query('SELECT shortcode, description FROM filiere_parcours') as $p) {
	$code = trim((string) $p['shortcode']);
	if ($code !== '') { $parcoursNoms[$code] = trim((string) $p['description']); }
}

/* Etat des cours offerts pour cette annee. Le tableau est complet pour les
   quatre sessions : le script tient les quatre etats en memoire, on peut donc
   cocher dans plusieurs sessions et n'enregistrer qu'une fois. */
$st = $dtb->prepare(
	'SELECT course_id, session_label, offered FROM t_2023_course_sessions
	  WHERE academic_year = ?');
$st->execute([$annee]);

$etat = [];
foreach ($cours as $c) {
	$etat[(string) $c['id']] = array_fill_keys($sessions, 0);
}
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
	$id = (string) $r['course_id'];
	if (isset($etat[$id]) && in_array($r['session_label'], $sessions, true)) {
		$etat[$id][$r['session_label']] = (int) $r['offered'] === 1 ? 1 : 0;
	}
}

/* Regroupement par mention puis niveau. */
$groupes = [];
foreach ($cours as $c) {
	$cle = $c['dep_desc'] . '|' . $c['yearlevel'];
	$groupes[$cle][] = $c;
}

$mentions = [];
$niveaux = [];
foreach ($cours as $c) {
	if ($c['dep_desc'] !== '') { $mentions[$c['dep_desc']] = true; }
	$niveaux[(string) $c['yearlevel']] = true;
}
ksort($mentions, SORT_NATURAL | SORT_FLAG_CASE);
ksort($niveaux, SORT_NATURAL);

/** « Licence 1 », « Master 2 »… */
$libNiveau = static function ($n) : string {
	$n = (int) $n;
	if ($n === 0) { return 'Remise à niveau'; }
	if ($n <= 3) { return 'Licence ' . $n; }
	return 'Master ' . ($n - 3);
};

/** Abrege une session, pour le liseré de semestre. */
$abrege = static function (string $s) : string {
	if ($s === 'Premier semestre') { return 'S1'; }
	if ($s === 'Deuxième semestre') { return 'S2'; }
	if ($s === "Semestre d'été") { return 'Été'; }
	return 'Hiver';
};
?>
				<div class="cso" id="cours-sessions"
				     data-annee="<?= e($annee) ?>"
				     data-jeton="<?= e(csrf_token()) ?>">

					<div class="cso-tete">
						<div>
							<h1>Cours offerts par session</h1>
							<p>
								Cochez les cours réellement proposés pour la session
								<strong id="cso-session-courante"><?= e($sessions[0]) ?></strong>.
								Un cours non coché n’apparaît pas au moment d’inscrire un étudiant.
							</p>
						</div>

						<form method="get" class="cso-annee">
							<label for="academic_year">Année scolaire</label>
							<select id="academic_year" name="academic_year">
<?php
/* Les annees proposees : les memes que dans le volet « Ajout de cours »
   d'une fiche etudiant (coursAnneesChoix, data/cours.offerts.php) — celles
   du calendrier officiel et celles deja renseignees ici. Une annee nouvelle
   apparait des que ses sessions sont creees (page « Sessions »). */
$anneesProposees = coursAnneesChoix($dtb, $anneeInscription);
if (!in_array($annee, $anneesProposees, true)) { $anneesProposees[] = $annee; rsort($anneesProposees); }
foreach ($anneesProposees as $an):
?>
								<option value="<?= e($an) ?>"<?= $an === $annee ? ' selected' : '' ?>><?= e($an) ?></option>
<?php endforeach; ?>
							</select>
							<?php /* Sans JavaScript : bouton d'envoi. Avec, src/js/cours-sessions.js
							         change d'année sans recharger la page. */ ?>
							<noscript><button type="submit" class="cso-btn">Afficher</button></noscript>
						</form>
					</div>

<?php if ($annee !== $anneeInscription): ?>
					<?php /* Le piege qui a coute du temps : preparer une annee que le
					         formulaire d'inscription ne regarde pas. On le dit, avec le
					         lien pour y aller. */ ?>
					<div class="cso-avertit">
						<i class="bi-exclamation-triangle-fill" aria-hidden="true"></i>
						<span>
							Vous préparez <strong><?= e($annee) ?></strong>, mais le formulaire d’inscription
							utilise actuellement <strong><?= e($anneeInscription) ?></strong>.
							Ce que vous cochez ici n’y apparaîtra pas.
							<a href="?academic_year=<?= rawurlencode($anneeInscription) ?>">Passer à <?= e($anneeInscription) ?></a>
						</span>
					</div>
<?php endif; ?>

					<?php /* Onglets : une session a la fois, avec son compteur. Ils
					         remplacent les quatre colonnes de cases. */ ?>
					<div class="cso-sessions" role="tablist" aria-label="Session à préparer">
<?php foreach ($sessions as $i => $s): ?>
						<button type="button" class="cso-onglet" role="tab"
						        data-session="<?= e($s) ?>"
						        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
							<?= e($s) ?>
							<span class="cso-onglet__compte">0</span>
						</button>
<?php endforeach; ?>
					</div>

					<div class="cso-filtres">
						<input type="search" id="cso-recherche" placeholder="Rechercher un sigle, un titre, un enseignant…"
						       aria-label="Rechercher un cours">

						<select id="cso-mention" aria-label="Filtrer par mention">
							<option value="">Toutes les mentions</option>
<?php foreach (array_keys($mentions) as $m): ?>
							<option value="<?= e(mb_strtolower($m)) ?>"><?= e($m) ?></option>
<?php endforeach; ?>
						</select>

						<select id="cso-niveau" aria-label="Filtrer par niveau">
							<option value="">Tous les niveaux</option>
<?php foreach (array_keys($niveaux) as $n): ?>
							<option value="<?= e($n) ?>"><?= e($libNiveau($n)) ?></option>
<?php endforeach; ?>
						</select>

						<?php /* Filtre par etat : « ne me montre que ce qui est offert »
						         est la question qu'on se pose en relisant son travail. */ ?>
						<select id="cso-etat-filtre" aria-label="Filtrer par état">
							<option value="">Offerts et non offerts</option>
							<option value="offerts">Offerts seulement</option>
							<option value="non">Non offerts seulement</option>
						</select>

						<span class="cso-resume" id="cso-resume" aria-live="polite"></span>
					</div>

<?php foreach ($groupes as $cle => $liste):
	[$mention, $niveau] = explode('|', $cle, 2);
?>
					<section class="cso-groupe est-ouvert">
						<button type="button" class="cso-groupe__tete" aria-expanded="true">
							<span class="cso-groupe__chevron" aria-hidden="true"><i class="bi-chevron-right"></i></span>
							<span class="cso-groupe__nom">
								<?= e($mention) ?>
								<span class="cso-groupe__niveau">· <?= e($libNiveau($niveau)) ?></span>
							</span>
							<span class="cso-groupe__compte">0 / <?= count($liste) ?></span>
							<span class="cso-groupe__tout" role="button" tabindex="0">Tout cocher</span>
						</button>

						<div class="cso-groupe__corps">
<?php foreach ($liste as $c):
	$prof = trim((string) ($c['prof_prenom'] ?? '') . ' ' . (string) ($c['prof_nom'] ?? ''));
	$codeParcours = trim((string) ($c['parcours'] ?? ''));
	$libParcours = ($codeParcours === '' || strtolower($codeParcours) === 'all')
		? 'Tronc commun'
		: ($parcoursNoms[$codeParcours] ?? $codeParcours);
	$sessionNative = coursSessionLabel((int) $c['semester']);
	$recherche = mb_strtolower(coursSigleAffiche($c) . ' ' . $c['Sigle'] . ' ' . $c['title'] . ' ' . $prof . ' '
		. $c['dep_desc'] . ' ' . $libParcours);
?>
							<label class="cso-cours"
							       data-id="<?= (int) $c['id'] ?>"
							       data-mention="<?= e(mb_strtolower((string) $c['dep_desc'])) ?>"
							       data-niveau="<?= e((string) $c['yearlevel']) ?>"
							       data-session-native="<?= e($sessionNative) ?>"
							       data-sem-court="<?= e($abrege($sessionNative)) ?>"
							       data-recherche="<?= e($recherche) ?>">
								<input type="checkbox" aria-label="Offrir <?= e(coursSigleAffiche($c)) ?> à cette session">
								<?php /* Les trois colonnes se coupent a l'ellipse quand elles
								         debordent : l'attribut title en rend la version entiere,
								         sans quoi un sigle long serait tronque sans recours. */ ?>
								<span class="cso-cours__sigle" title="<?= e(coursSigleAffiche($c)) ?>"><?= e(coursSigleAffiche($c)) ?></span>
								<span class="cso-cours__titre" title="<?= e($c['title']) ?>"><?= e($c['title']) ?></span>
								<span class="cso-cours__meta">
									<span class="cso-cours__prof" title="<?= $prof !== '' ? e($prof) : 'Enseignant non attribué' ?>"><?= $prof !== '' ? e($prof) : '—' ?></span>
									<span class="cso-cours__parcours" title="<?= e($libParcours) ?>"><?= e($libParcours) ?></span>
									<?php /* Le semestre natif. Le script le met en avant quand il
									         diffère de la session préparée. */ ?>
									<span class="cso-sem"><?= e($abrege($sessionNative)) ?></span>
								</span>
							</label>
<?php endforeach; ?>
						</div>
					</section>
<?php endforeach; ?>

					<div class="cso-vide" id="cso-vide" hidden>
						<i class="bi-search" aria-hidden="true"></i>
						Aucun cours ne correspond à votre recherche.
					</div>

					<?php /* Barre collante : le bouton ne doit pas se trouver à six
					         cents lignes de la case qu'on vient de cocher. */ ?>
					<div class="cso-barre">
						<span class="cso-barre__note" id="cso-note" aria-live="polite"></span>
						<button type="button" class="cso-btn" id="cso-enregistrer" disabled>
							<i class="bi-check-lg" aria-hidden="true"></i>
							<span>Aucune modification</span>
						</button>
					</div>

					<?php /* L'état initial et la liste des sessions, en blocs de données :
					         le script les relit sans qu'aucun HTML ne soit interpolé dans
					         du JavaScript. */ ?>
					<script type="application/json" id="cso-etat"><?= json_encode($etat, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
					<script type="application/json" id="cso-sessions-liste"><?= json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
				</div>

				<script src="<?=$app_base?>/src/js/cours-sessions.js?v=<?=@filemtime(__DIR__.'/js/cours-sessions.js')?>"></script>
<?php endif; ?>

			</div>

			<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>
</body>
</html>
