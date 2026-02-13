<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Général</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
	
				<div class="back flex-1 overflow-y-auto">
					<div class="w-full grid gap-4 xl:grid-cols-4 sm:grid-cols-1 p-4">
						<a href="./creat.account" <?php if($rg_user['level'] <= 3) { echo "";}else{ echo "class='toolInactive'";}?>>
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white h-[160px]'>
							<b class="text-lg"><i class="bi-person-fill"></i>&nbsp;&nbsp; Utilisateur</b><br><br>
							<p class="text-slate-500">Consulter la liste des utilisateurs. Créer un nouveau utilisateur.</p>
							</div>
						</a>
						<a href="./gestion_finance" <?php if($rg_user['level'] <= 3) { echo "";}else{ echo "class='toolInactive'";}?>>
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white h-[160px]'>
							<b class="text-lg"><i class="bi-currency-exchange"></i>&nbsp;&nbsp; Finance</b><br><br>
							<p class="text-slate-500">Voir, Modifier les détails financiaires des étudiants.</p>
							</div>
						</a>
						<a href="./login-locations" <?php if($rg_user['level'] <= 3) { echo "";}else{ echo "class='toolInactive'";}?>>
							<div class='m-0 p-3 <?=$bg_one_color?> hover:bg-slate-700 rounded-md border-2 border-slate-800 hover:border-cyan-500 transition-all text-white h-[160px]'>
							<b class="text-lg"><i class="bi-geo-alt-fill"></i>&nbsp;&nbsp; Localisations GPS</b><br><br>
							<p class="text-slate-500">Voir les coordonnées GPS des connexions utilisateurs sur une carte.</p>
							</div>
						</a>
					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>	
</body>
</html>