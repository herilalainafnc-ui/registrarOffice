<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
	<?php
	// Vérification d'accès pour étudiants
	require_once('../data/middleware.php');
	initMiddleware($dtb);
	
	// Les étudiants sont redirigés vers leur tableau de bord
	if (isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ' . $app_base . '/student/dashboard');
	}
	
	// Les professeurs voient uniquement leurs cours (filtré dans le live-search)
	$isTeacherView = isTeacher() && !isAdmin() && !isRegistrar();
	?>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
			
				<div class="w-full px-0.5 flex flex-1 overflow-hidden">
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="coursSearch-result"></div>

					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto hidden" id="coursTriage-result"></div>
					
					<div class="<?=$bg_one_color?> my-1 mx-0.5 w-full p-2 text-slate-100 overflow-auto" id="all-cours">
						
						<table class="simpleTbl">
							<thead class="<?=$bg_four_color?> text-white">
								<tr>
									<th>Sigle</th>
									<th>Cours</th>
									<th>Mention</th>
									<th>Parcours</th>
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
			// Requête sécurisée avec DB::searchCours
			$coursResults = DB::searchCours($input, 200);
		}else{
			$coursResults = DB::select('SELECT * FROM t_2023_cours WHERE remove != 1 ORDER BY title LIMIT 200');
		}
	$cours_nb = 1;
	foreach ($coursResults as $cours_list) {
 ?>								
								<tr id="cours_<?=$cours_nb?>" class="hover:bg-slate-600 hover:text-slate-800">	
									<td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['Sigle']?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['title']?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['dep_desc']?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php 
if($cours_list['parcours'] == "all") { echo "Tronc comun";}else{
	// Requête sécurisée
	$showParcours = DB::selectOne('SELECT * FROM filiere_parcours WHERE shortcode = :code', ['code' => $cours_list['parcours']]);
	if(!empty($showParcours)) {
		echo $showParcours['description'];
	}
}
								?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['nb_crd']?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php
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
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php
												if ($cours_list['yearlevel']==0) {
													echo "Remise à niveau";
												}elseif($cours_list['yearlevel']>0 AND $cours_list['yearlevel']<=3) {
													echo "Licence ".$cours_list['yearlevel'];
												}else{
													echo "Master ".($cours_list['yearlevel']-3);
												} ?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?=$cours_list['semester']?></div></a></td>
									<td><a href="./cours?id=<?=$cours_list['id']?>&page=information"><div class="w-full"><?php
// Requête sécurisée
$showTeach = DB::selectOne('SELECT * FROM teacher WHERE uid = :uid', ['uid' => $cours_list['id_teacher']]);
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