<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Enseignants</title>
</head>
<body class="<?=$bg_three_color?> text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex" style="height: calc(100vh - 152px);">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="profSearch-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="profTriage-result"></div>

					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto" id="all-prof">
						
						<table class="simpleTbl">
							<thead class="<?=$bg_four_color?> text-white">
								<tr>
									<th>ID</th>
									<th>Nom</th>
									<th>Prénom</th>
									<th>address</th>
									<th>Téléphone</th>
									<th>Email</th>
									
								</tr>
							</thead>
							<tbody>
<?php

	if (isset($_POST['search']) AND !empty($_POST['search'])) {
			$input = htmlspecialchars($_POST['search']);
			$recupprof = $dtb->query('SELECT * FROM teacher WHERE teacher_id LIKE "%'.$input.'%" OR name LIKE "%'.$input.'%" OR lastName LIKE "%'.$input.'%" OR email LIKE "%'.$input.'%" OR phone LIKE "%'.$input.'%" AND remove != 1 ORDER BY teacher_id DESC limit 200');	
		}else{
			$recupprof = $dtb->query('SELECT * FROM teacher WHERE remove != 1 ORDER BY teacher_id DESC limit 200');
		}
	$prof_nb = 1;
	while ($prof_list = $recupprof->fetch()) {
 ?>								
								<tr id="prof_<?=$prof_nb?>" class="hover:<?=$bg_six_color?> hover:text-slate-800">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['teacher_id']?></div></a></td>
									<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=strtoupper($prof_list['name'])?></div></a></td>
									<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['lastName']?></div></a></td>
									<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['address']?></div></a></td>
									<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['phone']?></div></a></td>
									<td><a href="./prof.php?id=<?=$prof_list['teacher_id']?>&page=information"><div class="w-full"><?=$prof_list['email']?></div></a></td>
									
								</tr>

<?php
	$prof_nb++;
	}
 ?>								

							</tbody>
						</table>

					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>
			</div>


		</div>

	</div>
</body>
</html>