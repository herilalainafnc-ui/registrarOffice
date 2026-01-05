<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Meilleurs Étudiants</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="sm:w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto">
						
						<?php require('./student/meilleurs-etudiants.php'); ?>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>

		</div>

	</div>
</body>
</html>
