<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Home</title>
</head>
<body class="<?=$bg_three_color?> text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex" style="height: calc(100vh - 152px);">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="stdTriage-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto" id="all-std">
						
						<table class="simpleTbl">
							<thead class="<?=$bg_four_color?> text-white">
								<tr>
									<th>ID</th>
									<th>Nom</th>
									<th>Prénom</th>
									<th>Mention</th>
									<th>Parcours</th>
									<th>Année</th>
									<th>Niveau</th>
								</tr>
							</thead>
							<tbody>
<?php

	if (isset($_POST['search']) AND !empty($_POST['search'])) {
			$input = htmlspecialchars($_POST['search']);
			$recupsdt = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE student_id LIKE "%'.$input.'%" OR student_nom LIKE "%'.$input.'%" OR student_prenom LIKE "%'.$input.'%" OR sex LIKE "%'.$input.'%" OR student_email LIKE "%'.$input.'%" OR student_tel LIKE "%'.$input.'%" OR religion LIKE "%'.$input.'%" AND remove != 1 ORDER BY id DESC limit 800');	
		}else{
			$recupsdt = $dtb->query('SELECT * FROM tbl_2024_etudiant WHERE remove != 1 ORDER BY id DESC limit 200');
		}
	$sdt_nb = 1;
	while ($sdt_list = $recupsdt->fetch()) {
 ?>								
								<tr id="std_<?=$sdt_nb?>" class="hover:<?=$bg_six_color?> hover:<?=$txt_three_color?> <?=$txt_one_color?>">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"
									><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_id']?></div></a></td>
									<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=strtoupper($sdt_list['student_nom'])?></div></a></td>
									<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['student_prenom']?></div></a></td>
									<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_envisage']?></div></a></td>
									<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['etude_option']?></div></a></td>
									<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?=$sdt_list['annee_scolaire']?></div></a></td>
									<td><a href="./student.php?id=<?=$sdt_list['id']?>&page=information"><div class="w-full"><?php
										if($sdt_list['annee_etude']<=3) {
											echo "Licence ".$sdt_list['annee_etude'];
										}else{
											echo "Master ".($sdt_list['annee_etude']-3);
										} ?></div></a>
									</td>
								</tr>

<?php
	$sdt_nb++;
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