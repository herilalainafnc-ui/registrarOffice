<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
</head>
<body class="<?=$bg_three_color?> text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex" style="height: calc(100vh - 152px);">
					
					<div class="my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto grid gap-4 grid-cols-4">
						
						
							<div class="rounded-md border-1 border-slate-500 h-[250px] hover:shadow-lg">
								<a href="">
								<div class="p-2 w-full">
									<div class="w-10/12 border">
										<p class="text-xl"><b>Nom du cours</b></p>
									</div>
									<div class="w-2/12 text-right py-1 border">
										<a href="#" class="">
											<span class="py-1 px-2 rounded-xl hover:<?=$bg_four_color?> w-3 h-3">
												<i class="bi-three-dots-vertical"></i>
											</span>
										</a>
									</div>
								</div>
								<div class="p-2">
									<p>Nom de l'enseignant</p>
								</div>
								</a>
							</div>
						

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>