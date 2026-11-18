<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Adresses Google</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/adresses-google.css?v=<?=@filemtime(__DIR__ . '/css/adresses-google.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
/* =============================================================================
   Adresses Google (/settings/adresses-google)

   Les adresses @zurcher.edu.mg de la base — fiches etudiants, enseignants,
   comptes de l'application — comparees aux comptes Google Workspace du
   domaine : active, jamais connectee, suspendue, absente de Google. Une
   adresse d'etudiant absente se cree d'un clic, avec le mot de passe de sa
   fiche, comme le faisait l'import CSV dans Google Admin.

   Superadmin uniquement (AdminController::adressesGoogle).

   Donnees : app/adresses-google.api.php ; Google : data/google.admin.php.
   ========================================================================== */
require_once(__DIR__ . '/../data/google.admin.php');
$agConfiguree = googleAdminConfiguree();
?>
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 overflow-y-auto">
					<main class="ag" id="ag" data-api="<?=$app_base?>/app/adresses-google.api.php" data-csrf="<?=e(csrf_token())?>">

						<header class="ag-tete">
							<span class="ag-tete__icone" aria-hidden="true"><i class="bi bi-google"></i></span>
							<div class="ag-tete__texte">
								<h1>Adresses Google</h1>
								<p>Les adresses <b>@<?=e(GOOGLE_ADMIN_DOMAINE)?></b> de la base, comparées aux comptes Google du domaine.</p>
							</div>
							<div class="ag-tete__fraicheur" id="agFraicheur" hidden>
								<span id="agLuLe"></span>
								<button type="button" class="ag-bouton ag-bouton--discret" id="agActualiser" title="Relire les comptes dans Google">
									<i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>Actualiser</span>
								</button>
							</div>
						</header>

						<?php if (!$agConfiguree): ?>
						<section class="ag-config" aria-labelledby="agConfigTitre">
							<span class="ag-config__icone" aria-hidden="true"><i class="bi bi-plug"></i></span>
							<div>
								<h2 id="agConfigTitre">Google n'est pas encore relié à l'application</h2>
								<p>Il manque <?=e(implode(' et ', googleAdminCeQuiManque()))?>.</p>
								<p>Une fois relié, cette page montre l'état de chaque adresse, et « Créer l'étudiant » crée aussitôt son compte Google : plus besoin d'importer de fichier CSV dans Google Admin.</p>
							</div>
						</section>
						<?php else: ?>

						<nav class="ag-etats" id="agEtats" aria-label="Filtrer par état" hidden>
							<button type="button" class="ag-etat" data-statut="" aria-pressed="true"><span>Toutes</span><b data-compte="toutes"></b></button>
							<button type="button" class="ag-etat ag-etat--active" data-statut="active" aria-pressed="false" title="Le compte existe et la personne s'y est déjà connectée"><i aria-hidden="true"></i><span>Actives</span><b data-compte="active"></b></button>
							<button type="button" class="ag-etat ag-etat--jamais" data-statut="jamais" aria-pressed="false" title="Le compte existe mais personne ne s'y est encore connecté"><i aria-hidden="true"></i><span>Jamais connectées</span><b data-compte="jamais"></b></button>
							<button type="button" class="ag-etat ag-etat--absente" data-statut="absente" aria-pressed="false" title="Adresse de la base sans compte Google"><i aria-hidden="true"></i><span>Absentes de Google</span><b data-compte="absente"></b></button>
							<button type="button" class="ag-etat ag-etat--suspendue" data-statut="suspendue" aria-pressed="false" title="Compte suspendu ou archivé dans Google Admin"><i aria-hidden="true"></i><span>Suspendues</span><b data-compte="suspendue"></b></button>
							<button type="button" class="ag-etat ag-etat--corriger" data-statut="a_corriger" aria-pressed="false" title="Adresse avec un accent ou un espace : Google la refuse"><i aria-hidden="true"></i><span>À corriger</span><b data-compte="a_corriger"></b></button>
							<button type="button" class="ag-etat ag-etat--google" data-statut="google_seul" aria-pressed="false" title="Comptes Google qu'aucune fiche ne porte (personnel, anciens, essais)"><i aria-hidden="true"></i><span>Dans Google seulement</span><b data-compte="google_seul"></b></button>
						</nav>

						<div class="ag-outils" id="agOutils" hidden>
							<label class="ag-recherche">
								<i class="bi bi-search" aria-hidden="true"></i>
								<input type="search" id="agRecherche" placeholder="Nom, matricule ou adresse" aria-label="Rechercher une adresse ou une personne" autocomplete="off">
							</label>
							<select id="agSource" class="ag-select" aria-label="Qui porte l'adresse">
								<option value="">Toutes les fiches</option>
								<option value="etudiant">Étudiants</option>
								<option value="enseignant">Enseignants</option>
								<option value="compte">Comptes de l'application</option>
							</select>
							<label class="ag-interrupteur" title="Les étudiants inscrits cette année, ou admis pour elle">
								<input type="checkbox" id="agAnnee">
								<span class="ag-interrupteur__piste" aria-hidden="true"></span>
								<span id="agAnneeLibelle">Étudiants de l'année</span>
							</label>
							<button type="button" class="ag-bouton" id="agCreerSelection" hidden>
								<i class="bi bi-person-plus" aria-hidden="true"></i><span></span>
							</button>
						</div>

						<div class="ag-tableau" id="agTableau" aria-live="polite">
							<div class="ag-chargement" id="agChargement">
								<span class="ag-chargement__rond" aria-hidden="true"></span>
								<p>Lecture des comptes Google du domaine…</p>
							</div>
						</div>
						<p class="ag-note" id="agNote" hidden>
							<i class="bi bi-info-circle" aria-hidden="true"></i>
							« Créer » ouvre le compte Google avec l'adresse et le mot de passe de la fiche, comme l'import CSV : l'étudiant peut se connecter tout de suite sur mail.google.com.
						</p>
						<?php endif; ?>
					</main>
				</div>

				<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>

	<div class="ag-dialogue" id="agDialogue" role="alertdialog" aria-modal="true" aria-labelledby="agDialogueTitre" hidden>
		<div class="ag-dialogue__carte">
			<h2 id="agDialogueTitre"></h2>
			<p id="agDialogueTexte"></p>
			<div class="ag-dialogue__actions">
				<button type="button" class="ag-bouton ag-bouton--discret" data-reponse="non">Annuler</button>
				<button type="button" class="ag-bouton" data-reponse="oui" id="agDialogueOui"></button>
			</div>
		</div>
	</div>

	<?php if ($agConfiguree): ?>
	<script src="<?=$app_base?>/src/js/adresses-google.js?v=<?=@filemtime(__DIR__ . '/js/adresses-google.js')?>"></script>
	<?php endif; ?>
</body>
</html>
