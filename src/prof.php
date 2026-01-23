<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Enseignant</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
				
				<div class="w-full px-0.5 flex-1 overflow-hidden">
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

						require('../init/.prof/prof.menubar.php');
 ?>					
					

						<div class="my-1 p-2 mx-0.5 sm:w-10/12 lg:w-9/12 xxl:w-7/12 <?=$bg_one_color?> text-white overflow-auto" style="max-height: calc(100vh - 160px);">
							<div class="h-20 flex pb-2">
								<div class="w-4/12 px-1">
									<a class="sm:text-xs lg:text-lg mt-3"><?php 

									if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$prof_page = $_GET['page'];
									if($prof_page == "information") {
										echo strtoupper($prof_page);
										if(!empty($profil['last_change_user_id'])) {?>
									 
										 <p class="toolInactive text-xs">Dernière modification<br>le <?=$profil['last_change_datetime']?>
										 par <?php 
							$findUserModif = $dtb->query('SELECT * FROM compt_utilisateur WHERE id = "'.$profil['last_change_user_id'].'"');
							$showUserModif = $findUserModif->fetch();
							echo $showUserModif['prenom'];
										  ?>
										</p>
									 
									 <?php }else{?>
									 	<p class="toolInactive text-xs">Créé le <?=$profil['date_entry']?></p>
									  <?php	
									 	}
									}
								}

									 ?></a>
									
								</div>
								<!-- PROF TOOLBAR --><?php require ('../init/.prof/prof.toolbar.php');?>
								<!-- NOTIFICATION MANAGER --><?php require('./prof/notificationProf.php');?>
							</div>
							<?php 
								
								if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$prof_page = $_GET['page'];

									if($prof_page == "information") {
										require('./prof/information.php');
									}elseif($prof_page == "cours") {
										require('./prof/cours.php');
									}
								}
							?>

						</div>

<?php 
}
 ?>
					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>
</body>
</html>