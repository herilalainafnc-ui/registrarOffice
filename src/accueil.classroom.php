<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="sm:w-full lg:w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="back" style="height: calc(100vh - 152px);">
					
					

				
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>