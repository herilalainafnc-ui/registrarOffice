<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php
	// SÉCURITÉ: Cette page est réservée aux administrateurs et registraires
	requireLevel(ROLE_REGISTRAR, './accueil');
	?>
	<title>Actualités</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/actus.css?v=<?=@filemtime(__DIR__ . '/css/actus.css')?>">
</head>
<?php
/* =============================================================================
   Actualites (/news) — les annonces de la rubrique Actualites de l'espace
   etudiant (src/student.actus.php). Niveau 3 et moins.

   Chaque annonce dit ce qu'en voient les etudiants : en ligne, programmee
   (sa date de publication n'est pas arrivee), masquee, ou expiree (sa date de
   fin est passee). L'editeur montre la carte telle qu'elle paraitra.

   Donnees : une seule requete, posee dans la page en JSON — l'editeur s'ouvre
   sans aller-retour au serveur. Ecritures : app/.annonces/ (add, update,
   toggle, delete). Prefixe « ac- » ; styles src/css/actus.css, comportement
   src/js/actus.js.
   ========================================================================== */
$acCategories = [
	'info'     => ['label' => 'Information', 'icon' => 'bi-info-circle',          'color' => '#0ea5e9'],
	'event'    => ['label' => 'Événement',   'icon' => 'bi-calendar-event',       'color' => '#a78bfa'],
	'academic' => ['label' => 'Académique',  'icon' => 'bi-mortarboard',          'color' => '#34d399'],
	'urgent'   => ['label' => 'Urgent',      'icon' => 'bi-exclamation-triangle', 'color' => '#f87171'],
	'sport'    => ['label' => 'Sport',       'icon' => 'bi-trophy',               'color' => '#fbbf24'],
	'culture'  => ['label' => 'Culture',     'icon' => 'bi-palette',              'color' => '#f472b6'],
];

$acTable = false;
$acAnnonces = [];
try {
	$acTable = (bool) $dtb->query("SHOW TABLES LIKE 't_annonces'")->fetchColumn();
	if ($acTable) {
		$acAnnonces = $dtb->query(
			'SELECT id, title, content, excerpt, image, category, is_pinned, is_active, author, publish_date, expire_date, created_at, updated_at
			   FROM t_annonces ORDER BY is_pinned DESC, publish_date DESC, created_at DESC'
		)->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$acTable = false;
}
?>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<div class="ac ac-entree" id="acPage">

						<header class="ac-tete">
							<span class="ac-tete__icone" aria-hidden="true"><i class="bi bi-megaphone"></i></span>
							<div class="ac-tete__texte">
								<h1>Actualités</h1>
								<p>Les annonces de la rubrique Actualités de l'espace étudiant. Une annonce n'y paraît qu'entre sa date de publication et sa date de fin&nbsp;; les épinglées passent en tête.</p>
							</div>
							<button type="button" class="ac-bouton" id="acNouvelle"<?= $acTable ? '' : ' disabled' ?>>
								<i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nouvelle annonce</span>
							</button>
						</header>

						<?php if (!$acTable): ?>
						<div class="ac-vide">
							<span class="ac-vide__icone" aria-hidden="true"><i class="bi bi-database-x"></i></span>
							<h2>La table des annonces n'existe pas encore</h2>
							<p>Appliquez <code>data/sql_annonces.sql</code> sur la base, puis rechargez la page.</p>
						</div>
						<?php else: ?>

						<div class="ac-outils">
							<div class="ac-etats" id="acEtats" role="group" aria-label="Filtrer par ce que voient les étudiants">
								<button type="button" class="ac-etat" data-etat="tout" aria-pressed="true"><span>Toutes</span><b></b></button>
								<button type="button" class="ac-etat ac-etat--en_ligne" data-etat="en_ligne" aria-pressed="false"><i aria-hidden="true"></i><span>En ligne</span><b></b></button>
								<button type="button" class="ac-etat ac-etat--programmee" data-etat="programmee" aria-pressed="false"><i aria-hidden="true"></i><span>Programmées</span><b></b></button>
								<button type="button" class="ac-etat ac-etat--masquee" data-etat="masquee" aria-pressed="false"><i aria-hidden="true"></i><span>Masquées</span><b></b></button>
								<button type="button" class="ac-etat ac-etat--expiree" data-etat="expiree" aria-pressed="false"><i aria-hidden="true"></i><span>Expirées</span><b></b></button>
							</div>
							<label class="ac-recherche">
								<i class="bi bi-search" aria-hidden="true"></i>
								<span class="sr-only">Rechercher une annonce</span>
								<input type="search" id="acRecherche" placeholder="Titre, contenu ou auteur" autocomplete="off">
							</label>
							<label class="ac-categorie">
								<span class="sr-only">Catégorie</span>
								<select id="acCategorie">
									<option value="">Toutes les catégories</option>
									<?php foreach ($acCategories as $code => $cat): ?>
									<option value="<?= $code ?>"><?= $cat['label'] ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>

						<section class="ac-liste" id="acListe" aria-label="Annonces" aria-live="polite"></section>
						<?php endif; ?>
					</div>
				</div>

				<!-- FOOTER -->
				<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>

<?php if ($acTable): ?>
	<?php /* L'editeur : le formulaire, et la carte telle que la verront les etudiants. */ ?>
	<dialog class="ac-dialog" id="acEditeur" aria-labelledby="acEditeurTitre">
		<form class="ac-editeur" id="acForm" method="dialog" novalidate>
			<header class="ac-editeur__tete">
				<h2 id="acEditeurTitre">Nouvelle annonce</h2>
				<button type="button" class="ac-icone" data-ac="fermer" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
			</header>

			<div class="ac-editeur__corps">
				<div class="ac-champs">
					<label class="ac-champ">
						<span class="ac-champ__nom">Titre <em aria-hidden="true">*</em><small class="ac-compte" data-pour="title"></small></span>
						<input type="text" name="title" maxlength="255" required autocomplete="off" placeholder="Ex. Rentrée du deuxième semestre">
					</label>

					<fieldset class="ac-champ">
						<legend class="ac-champ__nom">Catégorie</legend>
						<div class="ac-cats">
							<?php foreach ($acCategories as $code => $cat): ?>
							<label class="ac-cat" style="--cat: <?= $cat['color'] ?>">
								<input type="radio" name="category" value="<?= $code ?>"<?= $code === 'info' ? ' checked' : '' ?>>
								<span><i class="bi <?= $cat['icon'] ?>" aria-hidden="true"></i><?= $cat['label'] ?></span>
							</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<label class="ac-champ">
						<span class="ac-champ__nom">Résumé<small class="ac-compte" data-pour="excerpt"></small></span>
						<textarea name="excerpt" rows="2" maxlength="500" placeholder="Une ou deux phrases, sur la carte"></textarea>
						<small class="ac-aide">Vide&nbsp;: la carte montre le début du contenu.</small>
					</label>

					<label class="ac-champ">
						<span class="ac-champ__nom">Contenu <em aria-hidden="true">*</em></span>
						<textarea name="content" rows="6" required placeholder="Le texte complet, lu à l'ouverture de l'annonce"></textarea>
					</label>

					<div class="ac-champ">
						<span class="ac-champ__nom">Image</span>
						<div class="ac-image" id="acImage">
							<label class="ac-depot" id="acDepot">
								<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
								<i class="bi bi-image" aria-hidden="true"></i>
								<span><b>Choisir une image</b> ou la glisser ici</span>
								<small>JPG, PNG ou WEBP ; elle est réduite avant l'envoi.</small>
							</label>
							<div class="ac-image__choisie" hidden>
								<img alt="" id="acImageApercu">
								<div class="ac-image__actions">
									<span class="ac-image__info" id="acImageInfo"></span>
									<button type="button" class="ac-bouton ac-bouton--discret ac-bouton--petit" data-ac="changer-image"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Changer</button>
									<button type="button" class="ac-bouton ac-bouton--discret ac-bouton--petit ac-bouton--danger" data-ac="retirer-image"><i class="bi bi-trash3" aria-hidden="true"></i>Retirer</button>
								</div>
							</div>
						</div>
					</div>

					<div class="ac-dates">
						<label class="ac-champ">
							<span class="ac-champ__nom">Publication</span>
							<input type="date" name="publish_date">
						</label>
						<label class="ac-champ">
							<span class="ac-champ__nom">Fin d'affichage <em class="ac-facultatif">facultatif</em></span>
							<input type="date" name="expire_date">
						</label>
						<p class="ac-dates__etat" id="acDatesEtat" aria-live="polite"></p>
					</div>

					<div class="ac-bascules">
						<label class="ac-bascule">
							<input type="checkbox" name="is_active" value="1" checked>
							<span class="ac-bascule__piste" aria-hidden="true"></span>
							<span><b>Visible par les étudiants</b><small>Décochée : l'annonce reste ici, masquée.</small></span>
						</label>
						<label class="ac-bascule">
							<input type="checkbox" name="is_pinned" value="1">
							<span class="ac-bascule__piste" aria-hidden="true"></span>
							<span><b>Épingler en tête</b><small>Avant toutes les autres, quelle que soit sa date.</small></span>
						</label>
					</div>

					<label class="ac-champ">
						<span class="ac-champ__nom">Signée par</span>
						<input type="text" name="author" maxlength="100" autocomplete="off" placeholder="Service ou personne">
					</label>
				</div>

				<aside class="ac-apercu" aria-label="Aperçu côté étudiant">
					<p class="ac-apercu__titre">Ce que verront les étudiants</p>
					<article class="ac-carte" id="acCarte"></article>
					<p class="ac-apercu__note" id="acApercuNote"></p>
				</aside>
			</div>

			<?php /* Les questions et les erreurs restent DANS la fenetre : une fenetre
			         modale passe au-dessus de tout, les notifications de la page
			         s'afficheraient dessous, hors de portee. */ ?>
			<footer class="ac-editeur__pied" id="acPied">
				<span class="ac-editeur__id" id="acEditeurId"></span>
				<p class="ac-editeur__message" id="acMessage" role="alert" hidden></p>
				<div class="ac-editeur__boutons">
					<button type="button" class="ac-bouton ac-bouton--discret" data-ac="fermer">Annuler</button>
					<button type="submit" class="ac-bouton" id="acValider"><i class="bi bi-check-lg" aria-hidden="true"></i><span>Publier</span></button>
				</div>
				<div class="ac-question" id="acQuestion" hidden>
					<span>Les changements ne sont pas enregistrés.</span>
					<button type="button" class="ac-bouton ac-bouton--discret" data-ac="continuer">Continuer la saisie</button>
					<button type="button" class="ac-bouton ac-bouton--alerte" data-ac="abandonner">Abandonner</button>
				</div>
			</footer>
		</form>
	</dialog>

	<script type="application/json" id="acDonnees"><?= json_encode([
		'annonces' => $acAnnonces,
		'categories' => $acCategories,
		'aujourdhui' => date('Y-m-d'),
		'auteur' => trim(($rg_name ?? '') . ' ' . ($rg_last_name ?? '')), // pose par init/topbar.php
		'images' => $app_base . '/app/uploads/annonces/',
		'points' => [
			'ajouter' => $app_base . '/app/.annonces/add.annonce',
			'modifier' => $app_base . '/app/.annonces/update.annonce',
			'basculer' => $app_base . '/app/.annonces/toggle.annonce',
			'supprimer' => $app_base . '/app/.annonces/delete.annonce',
		],
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
	<script src="<?=$app_base?>/src/js/actus.js?v=<?=@filemtime(__DIR__ . '/js/actus.js')?>"></script>
<?php endif; ?>
</body>
</html>
