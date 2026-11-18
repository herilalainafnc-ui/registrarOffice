<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Réglages de la vitrine</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/settings.vitrine.css?v=<?=@filemtime(__DIR__.'/css/settings.vitrine.css')?>">
<?php
/* =============================================================================
   Reglages de la page publique

   Les textes, dates, coordonnees et visuels de la vitrine etaient ecrits en dur
   dans les fragments PHP : changer une date de rentree demandait une
   intervention de developpeur et un deploiement. Cette page les rend
   modifiables par le registrariat.

   Le formulaire est ENTIEREMENT pilote par la table `vitrine_config` : le
   groupe, l'ordre, le libelle et le type de champ en viennent. Ajouter un
   reglage se fait donc dans data/vitrine.seed.php, sans toucher a ce fichier.
   ========================================================================== */

require_once __DIR__ . '/../data/vitrine.config.php';

$messageType = '';
$messageTexte = '';

/* --- Enregistrement -------------------------------------------------------
   Chemin de REPLI uniquement. Le cas normal passe par
   app/vitrine-config.save.php, appele en arriere-plan par
   src/js/settings.vitrine.js : la page ne se recharge pas et l'on ne perd pas
   l'endroit du formulaire ou l'on travaillait.

   Ce bloc reste malgre tout : sans JavaScript, le formulaire se soumet
   normalement, et il doit enregistrer plutot que d'echouer en silence. La
   logique est partagee avec le point d'entree AJAX (data/vitrine.save.php) —
   pas de seconde copie des regles de televersement. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!function_exists('verify_csrf') || !verify_csrf()) {
        $messageType = 'erreur';
        $messageTexte = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
    } else {
        require_once __DIR__ . '/../data/vitrine.save.php';

        $agent = trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''));
        if ($agent === '') { $agent = $_SESSION['user_pseudo'] ?? null; }

        $r = vitrineEnregistrer($dtb, $_POST, $_FILES, $agent);
        $messageType  = $r['type'];
        $messageTexte = $r['message'];
    }
}
/* --- Lecture --------------------------------------------------------------- */
$parGroupe = [];
try {
    $q = $dtb->query('SELECT * FROM vitrine_config ORDER BY groupe, ordre, libelle');
    foreach ($q as $r) { $parGroupe[$r['groupe']][] = $r; }
} catch (Throwable $e) {
    $messageType = 'erreur';
    $messageTexte = 'La table des réglages est introuvable. Lancez data/vitrine.seed.php.';
}

$titresGroupes = [
    'identite'   => ['Identité',      'Nom, sigle et phrase d’accroche'],
    'dates'      => ['Dates clés',    'Rentrée, test d’admission, année académique'],
    'contact'    => ['Coordonnées',   'Adresse, téléphones et courriel du registrariat'],
    'reseaux'    => ['Réseaux',       'Liens vers les comptes officiels'],
    'accueil'    => ['Page d’accueil','Titre animé et boutons du bandeau'],
    'formations' => ['Formations',    'Titres de section et visuel de chaque mention'],
    'campus'     => ['Campus',        'Titres de section et chiffres'],
    'admissions' => ['Admissions',    'Titres de section et condition de diplôme'],
];
?>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- Conteneur de defilement repris des autres pages de reglages.
			     J'avais d'abord invente une mise en page en flex : le contenu
			     restait coince dans une colonne etroite, le reste de la largeur
			     tombait sur le fond nu. -->
			<div class="back flex-1 overflow-y-auto">
				<div class="rv-page">

				<div class="rv-entete">
					<div>
						<h1 class="rv-titre">Réglages de la vitrine</h1>
						<p class="rv-sous">Ce que vous modifiez ici s’applique immédiatement à la page publique.</p>
					</div>
					<a class="rv-voir" href="<?=$app_base?>/" target="_blank" rel="noopener">
						Voir la vitrine ↗
					</a>
				</div>

<?php if ($messageTexte !== ''): ?>
				<!-- Le retour d'enregistrement passe par un toast : un bandeau
				     poussait tout le formulaire vers le bas, et sur une page
				     aussi longue il restait souvent hors de vue au moment ou
				     l'on relisait le champ qu'on venait de modifier. -->
				<script>
				document.addEventListener('DOMContentLoaded', function () {
					if (typeof Toast === 'undefined') { return; }
					var type = <?= json_encode($messageType === 'ok' ? 'success'
					                        : ($messageType === 'erreur' ? 'error' : 'warning'),
					                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
					Toast.show(
						<?= json_encode($messageTexte, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
						                | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
						type,
						{ duration: type === 'success' ? 4000 : 8000 }
					);
				});
				</script>
<?php endif; ?>

<?php if (!$parGroupe): ?>
				<div class="rv-vide">
					<p>Aucun réglage n’est installé.</p>
					<p class="rv-vide__aide">Lancez <code>php data/vitrine.seed.php</code> sur le serveur.</p>
				</div>
<?php else: ?>
				<!-- data-pt-skip : pas de rideau de transition. L'enregistrement se
				     fait en arriere-plan, la page ne change pas (voir
				     src/js/settings.vitrine.js). -->
				<form method="post" enctype="multipart/form-data" class="rv-corps" data-pt-skip="1">
					<?= csrf_field() ?>

					<!-- Sommaire : huit groupes, on ne veut pas les chercher. -->
					<nav class="rv-sommaire" aria-label="Sections">
<?php foreach ($parGroupe as $g => $lignes): ?>
						<a href="#g-<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>">
							<?= htmlspecialchars($titresGroupes[$g][0] ?? ucfirst($g), ENT_QUOTES, 'UTF-8') ?>
							<span><?= count($lignes) ?></span>
						</a>
<?php endforeach; ?>
					</nav>

<?php foreach ($parGroupe as $g => $lignes): ?>
					<section class="rv-groupe" id="g-<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>">
						<header class="rv-groupe__tete">
							<h2><?= htmlspecialchars($titresGroupes[$g][0] ?? ucfirst($g), ENT_QUOTES, 'UTF-8') ?></h2>
<?php if (!empty($titresGroupes[$g][1])): ?>
							<p><?= htmlspecialchars($titresGroupes[$g][1], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
						</header>

						<div class="rv-grille">
<?php foreach ($lignes as $r):
							$champ = str_replace('.', '__', $r['cle']);
							$val   = (string) ($r['valeur'] ?? '');
							$large = in_array($r['type'], ['long', 'liste'], true);
?>
							<div class="rv-champ<?= $large ? ' rv-champ--large' : '' ?>">
								<label class="rv-label" for="<?= $champ ?>">
									<?= htmlspecialchars($r['libelle'], ENT_QUOTES, 'UTF-8') ?>
								</label>

<?php if ($r['type'] === 'long' || $r['type'] === 'liste'): ?>
								<textarea id="<?= $champ ?>" name="<?= $champ ?>" class="rv-input rv-textarea"
								          rows="<?= $r['type'] === 'liste' ? 6 : 3 ?>"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>

<?php elseif ($r['type'] === 'date'): ?>
								<input type="date" id="<?= $champ ?>" name="<?= $champ ?>" class="rv-input"
								       value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">

<?php elseif ($r['type'] === 'url'): ?>
								<input type="url" id="<?= $champ ?>" name="<?= $champ ?>" class="rv-input"
								       value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"
								       placeholder="https://…">

<?php elseif ($r['type'] === 'image'): ?>
								<div class="rv-image">
									<div class="rv-image__apercu">
<?php if ($val !== ''): ?>
										<img src="<?=$app_base?>/file/vitrine/<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>?v=<?= time() ?>" alt="">
<?php else: ?>
										<span>Aucune image</span>
<?php endif; ?>
									</div>
									<div class="rv-image__champ">
										<input type="file" id="<?= $champ ?>" name="<?= $champ ?>"
										       accept="image/jpeg,image/png,image/webp" class="rv-fichier">
										<p class="rv-aide">JPG, PNG ou WebP · 6 Mo maximum · redimensionnée à 1600 px</p>
									</div>
								</div>

<?php else: ?>
								<input type="text" id="<?= $champ ?>" name="<?= $champ ?>" class="rv-input"
								       value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>

<?php if (!empty($r['aide']) && $r['type'] !== 'image'): ?>
								<p class="rv-aide"><?= htmlspecialchars($r['aide'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if (!empty($r['modifie_le'])): ?>
								<p class="rv-trace">
									Modifié le <?= date('d/m/Y à H:i', strtotime($r['modifie_le'])) ?>
<?php if (!empty($r['modifie_par'])): ?>
									par <?= htmlspecialchars($r['modifie_par'], ENT_QUOTES, 'UTF-8') ?>
<?php endif; ?>
								</p>
<?php endif; ?>
							</div>
<?php endforeach; ?>
						</div>
					</section>
<?php endforeach; ?>

					<!-- Barre d'action fixe : le formulaire est long, le bouton
					     ne doit pas se trouver a mille pixels du champ modifie. -->
					<div class="rv-barre">
						<span class="rv-barre__note">Les modifications s’appliquent dès l’enregistrement.</span>
						<button type="submit" class="rv-enregistrer">Enregistrer</button>
					</div>
				</form>
<?php endif; ?>

				</div><!-- .rv-page -->

				<!-- Le pied de page vit DANS la zone de defilement, comme sur
				     les autres pages de reglages. Place hors de la mise en page,
				     il se logeait a cote de la colonne au lieu de dessous, et
				     celle-ci se retractait a moins de la moitie de la largeur. -->
				<?php require('../init/footer.php'); ?>


			<?php /* Enregistrement en arriere-plan : la page ne se recharge plus.
			         Charge apres le formulaire, il le trouve deja present et n'a
			         pas besoin d'attendre DOMContentLoaded. */ ?>
			<script src="<?=$app_base?>/src/js/settings.vitrine.js?v=<?=@filemtime(__DIR__.'/js/settings.vitrine.js')?>"></script>

			</div><!-- .back -->
			</div>
		</div>
	</div>
</body>
</html>
