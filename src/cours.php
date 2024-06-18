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
				
				<div class="w-full px-0.5" style="height: calc(100vh - 157px);">
					<div class="flex w-full">
						<div class="bg-slate-800 my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result-mini" style="height: calc(100vh - 157px);"></div>
<?php
	$id = $_GET['id'];
	$retrouve = $dtb->query("SELECT * FROM t_2023_cours WHERE id ='".$id."' LIMIT 1");

if($retrouve->rowCount() > 0) {
$profil = $retrouve->fetch();
$sigle = $profil['Sigle'];
$title = $profil['title'];
$title_english = $profil['title_english'];
$dep_desc = $profil['dep_desc'];
$date_entry = $profil['date_entry'];
$yes = 1;

						require('../init/cours.menubar.php');
 ?>					
					

						<div class="my-1 p-2 mx-0.5 lg:w-9/12 xl:w-9/12 xxl:w-7/12 bg-slate-800 text-white overflow-auto" style="max-height: calc(100vh - 160px);">
							<div class="h-20 flex pb-2">
								<div class="w-4/12 flex px-1">
									<b class="text-lg mt-3"><?php 

									if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$cours_page = $_GET['page'];
									if($cours_page == "information") {
										echo strtoupper($cours_page);
									}elseif($cours_page == "notes") {
										echo "NOTES";
									}elseif($cours_page == "etudiants") {
										echo "Etudiants qui font ce cours";
									}
								}

									 ?></b>
								</div>
								<!-- STUDENT TOOLBAR --><?php require ('../init/student.toolbar.php');?>
								<!-- NOTIFICATION MANAGER --><?php require('./student/notificationStd.php');?>
							</div>
							<?php 
								
								if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$cours_page = $_GET['page'];

									if($cours_page == "information") {
										require('./cours/information.php');
									}elseif($cours_page == "notes") {
										require('./cours/notes.php');
									}elseif($sdt_page == "etudiants") {
										require('./cours/etudiants.php');
									}
								}
							?>

						</div>

<?php 
}
 ?>
					</div>
				</div>
				
				<div class="w-10/12 h-6 bg-slate-500 mt-1 py-0.5 px-2 absolute bottom-0 flex">
					<div class="w-3/12">
						
					</div>
					<div class="w-3/12">
						
					</div>
					<div class="w-3/12">
						
					</div>
					<div class="w-3/12 text-black">
						<p>Aujourd'hui <?= date('d/m/Y')?></p>
					</div>
				</div>

			</div>

		</div>

	</div>
</body>
</html>