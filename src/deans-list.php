<?php
/* Changement d'année sans recharger la page (src/js/annees-ajax.js) : on ne
   rend que l'année demandée, sans la coque. Le contrôleur a déjà vérifié la
   connexion et les droits. */
if (isset($_GET['fragment'])) {
	require_once(dirname(__DIR__) . '/data/backdb.php');
	header('Content-Type: text/html; charset=UTF-8');
	header('Cache-Control: no-store');
	require(__DIR__ . '/student/deans-list.annees.php');
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Dean's List</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col dl-shell">
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto">
						
						<?php require('./student/deans-list.php'); ?>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>

		</div>

	</div>
</body>
</html>
