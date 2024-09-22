<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="sm:w-full lg:w-10/12">
	
			
				<div class="back" style="height: calc(100vh - 152px);">
					<div class="w-full grid gap-4 xl:grid-cols-4 sm:grdi-cols-1 p-4">
						<a href="./my.account.php">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white'>
							<b class="text-lg"><i class="bi-gear"></i>&nbsp;&nbsp; Mon compte</b><br><br>
							<p class="text-slate-500">Je peux modifier, configurer, verifier mon compte dans ce rubrique.</p>
							</div>
						</a>
						<a href="./creat.account.php">
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white'>
							<b class="text-lg"><i class="bi-person-fill-add"></i>&nbsp;&nbsp; Nouveau compte</b><br><br>
							<p class="text-slate-500">C'est simple. Créez un nouveau compte pour un autre utilisateur.</p>
							</div>
						</a>

					</div>
					
					<?php require('../init/footer.php'); ?>
				</div>


			</div>

		</div>

	</div>	
</body>
</html>