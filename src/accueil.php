<?php
// Vérification d'accès AVANT tout output HTML
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

// Les étudiants sont redirigés vers leur tableau de bord
if (isStudent() && !isAdmin() && !isRegistrar()) {
	header('Location: ' . $app_base . '/student/notes');
	exit;
}

// Les professeurs voient uniquement leurs étudiants (filtré dans le live-search)
$isTeacherView = isTeacher() && !isAdmin() && !isRegistrar();
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Liste des étudiants</title>
	<style>
		/* Animation d'entrée slide-in */
		body {
			animation: slideInFromRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
		}
		
		@keyframes slideInFromRight {
			from {
				opacity: 0;
				transform: translateX(30px);
			}
			to {
				opacity: 1;
				transform: translateX(0);
			}
		}
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="stdTriage-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto" id="all-std">
<?php
	/* La liste : recherche, filtres et tri appliques ensemble, lus dans
	   l adresse de la page ; src/js/listes.js prend ensuite le relais.
	   Voir data/listes.php. */
	require_once(__DIR__ . '/../data/listes.php');
	$listeSource = $_GET;
	if (!empty($_POST['search'])) { $listeSource['q'] = (string) $_POST['search']; }
	listesAfficher($dtb, 'etudiants', $listeSource, $app_base);
?>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>