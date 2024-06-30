<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
</head>
<body class="bg-slate-600 text-sm">
	<div class="h-screen w-full bg-slate-600">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex" style="height: calc(100vh - 152px);">
					
					<div class="bg-slate-800 my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="coursSearch-result"></div>

					<div class="bg-slate-800 my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="coursTriage-result"></div>
					
					<div class="bg-slate-800 my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto" id="all-cours">
						
						<table class="simpleTbl">
							<thead class="bg-slate-500 text-white">
								<tr>
									<th>Sigle</th>
									<th>Cours</th>
									<th>Mention</th>
									<th>Crédit</th>
									<th>Catégorie</th>
									<th>Niveau</th>
									<th>Semestre</th>
									<th>Enseignant</th>
								</tr>
							</thead>
							<tbody>
<?php

	if (isset($_POST['search']) AND !empty($_POST['search'])) {
			$input = htmlspecialchars($_POST['search']);
			$recupcours = $dtb->query('SELECT * FROM t_2023_cours WHERE Sigle LIKE "%'.$input.'%" OR title LIKE "%'.$input.'%" OR dep_desc LIKE "%'.$input.'%" OR nb_crd LIKE "%'.$input.'%" OR title_english LIKE "%'.$input.'%" ORDER BY title limit 200');	
		}else{
			$recupcours = $dtb->query('SELECT * FROM t_2023_cours ORDER BY title limit 200');
		}
	$cours_nb = 1;
	while ($cours_list = $recupcours->fetch()) {
 ?>								
								<tr id="cours_<?=$cours_nb?>" class="hover:bg-slate-300 hover:text-slate-800">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['Sigle']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['title']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['dep_desc']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['nb_crd']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
if ($cours_list['category'] == 0){
	echo "Général";
}elseif ($cours_list['category'] == 1) {
	echo "Majeur";
}elseif ($cours_list['category'] == -1 OR $cours_list['category'] == 2) {
	echo "Selective";
}elseif ($cours_list['category'] == 3) {
	echo "Additionnel";
}elseif ($cours_list['category'] == 5) {
	echo "``";
}else{
	echo "-";
}
								?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['yearlevel']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['semester']?></div></a></td>
									<td><a href="./cours.php?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
$findTeach = $dtb->query('SELECT * FROM teacher WHERE uid = "'.$cours_list['id_teacher'].'"');
$showTeach = $findTeach->fetch();
if(!empty($showTeach)) {
	echo strtoupper($showTeach['name'])." ".$showTeach['lastName'];
}
?></div></a></td>
								</tr>

<?php
	$cours_nb++;
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