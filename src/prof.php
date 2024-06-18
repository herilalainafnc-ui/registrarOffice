<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Enseignant</title>
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
<?php
	$id = $_GET['id'];
	$retrouve = $dtb->query("SELECT * FROM teacher WHERE teacher_id ='".$id."' LIMIT 1");

if($retrouve->rowCount() > 0) {
$profil = $retrouve->fetch();
$uid = $profil['uid'];
$teacher_id = $profil['teacher_id'];
$name = $profil['name'];
$lastName = $profil['lastName'];
$phone = $profil['phone'];
$email = $profil['email'];
$diplome = $profil['diplome'];
$sex = $profil['sex'];
$religion = $profil['religion'];
$address = $profil['address'];
$position = $profil['position'];
$teacher_image = $profil['teacher_image'];
$category = $profil['category'];
$date_entry = $profil['date_entry'];
$yes = 1;

						require('../init/prof.menubar.php');
 ?>					
					

						<div class="my-1 p-2 mx-0.5 lg:w-9/12 xl:w-9/12 xxl:w-7/12 bg-slate-800 text-white overflow-auto" style="max-height: calc(100vh - 160px);">
							<div class="h-20 flex pb-2">
								<div class="w-4/12 flex px-1">
									<b class="text-xl mt-3"><?php 

									if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$prof_page = $_GET['page'];
									if($prof_page == "information") {
										echo strtoupper($prof_page);
									}
								}

									 ?></b>
								</div>
								<!-- PROF TOOLBAR --><?php require ('../init/prof.toolbar.php');?>
								<!-- NOTIFICATION MANAGER --><?php require('./student/notificationStd.php');?>
							</div>
							<?php 
								
								if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$prof_page = $_GET['page'];

									if($prof_page == "information") {
										require('./prof/information.php');
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