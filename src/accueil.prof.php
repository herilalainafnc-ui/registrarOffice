<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Liste des professeurs</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="profSearch-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="profTriage-result"></div>

					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto" id="all-prof">
						
<?php
	/* Effectif affiche dans l'en-tete. Requete de comptage distincte : la
	   liste n'est interrogee qu'apres l'ouverture du tableau, plus bas. */
	$prof_total = (int)$dtb->query('SELECT COUNT(*) FROM teacher WHERE remove != 1')->fetchColumn();
?>
					<?php /* En-tete de page. Le bouton ouvre la MEME modale que l'entree
					         « Créer un professeur » du menu lateral (#bigNotifProf) : le
					         gestionnaire est etendu dans src/main/bigNotif.php plutot que
					         duplique ici. Meme condition de privilege que le menu. */ ?>
					<div class="liste-entete flex flex-wrap items-center justify-between gap-2 mb-3">
						<div>
							<h1 class="text-base sm:text-lg font-semibold text-white m-0">
								<i class="bi-person-badge"></i> Enseignants
							</h1>
							<p class="text-slate-400 text-[11px] m-0"><?=$prof_total?> enseignant<?=$prof_total > 1 ? 's' : ''?> enregistré<?=$prof_total > 1 ? 's' : ''?></p>
						</div>

						<button type="button" id="addProfPage"
							class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-all
							       bg-cyan-700 hover:bg-cyan-600 text-white border border-cyan-500/40
							       <?php if($rg_user['level'] > 3) { echo 'toolInactive opacity-50 cursor-not-allowed'; } ?>"
							<?php if($rg_user['level'] > 3) { echo 'disabled title="Privilèges insuffisants"'; } ?>>
							<i class="bi-person-plus-fill"></i>
							<span>Ajouter un enseignant</span>
						</button>
					</div>

<?php
	/* La liste : recherche, filtres et tri appliques ensemble, lus dans
	   l adresse de la page ; src/js/listes.js prend ensuite le relais.
	   Voir data/listes.php. */
	require_once(__DIR__ . '/../data/listes.php');
	$listeSource = $_GET;
	if (!empty($_POST['search'])) { $listeSource['q'] = (string) $_POST['search']; }
	listesAfficher($dtb, 'enseignants', $listeSource, $app_base);
?>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>