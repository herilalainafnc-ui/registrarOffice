<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Classement par session</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full text-slate-100 overflow-hidden">
						
						<?php require('./student/session-rankings.php'); ?>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>

		</div>

	</div>
</body>
</html>
