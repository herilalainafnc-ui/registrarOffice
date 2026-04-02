<?php
// Rediriger si trailing slash (conflit dossier cours/ vs cours.php)
if (preg_match('#/cours/$#', $_SERVER['REQUEST_URI'])) {
	$clean = preg_replace('#/+$#', '', $_SERVER['REQUEST_URI']);
	header('Location: ' . $clean, true, 301);
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Cours</title>
	<?php
	// Vérification d'accès pour professeurs
	require_once('../data/middleware.php');
	initMiddleware($dtb);
	
	$requestedCourseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	
	// Les étudiants ne peuvent pas accéder à cette page
	if (isStudent() && !isAdmin() && !isRegistrar()) {
		header('Location: ' . $app_base . '/student/dashboard');
		exit;
	}
	
	// Si c'est un professeur, vérifier qu'il a accès à ce cours
	if (isTeacher() && !isAdmin() && !isRegistrar()) {
		if (!teacherCanAccessCourse($requestedCourseId)) {
			header('Location: ' . $app_base . '/teacher/dashboard?error=access_denied');
			exit;
		}
	}
	?>
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
						<!-- <div class="<?=$bg_one_color?> my-1 mx-0.5 w-4/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result-mini" style="height: calc(100vh - 157px);"></div> -->
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

						require('../init/.cours/cours.menubar.php');
 ?>					
					

						<div class="my-1 p-2 mx-0.5 sm:w-10/12 lg:w-9/12 xxl:w-7/12 <?=$bg_one_color?> text-white" style="max-height: calc(100vh - 160px);">
							<div class="h-[65px] flex p-1">
								<div class="w-4/12 px-1">
									<a class="sm:text-xs lg:text-lg mt-3"><?php 

									if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$cours_page = $_GET['page'];
									if($cours_page == "information") {
										echo strtoupper($cours_page);
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
									}elseif($cours_page == "notes") {
										echo "NOTES";
									}elseif($cours_page == "promotion-notes") {
										echo "NOTES WORK/CHAPELLE";
									}elseif($cours_page == "etudiants") {
										echo "ETUDIANTS";
									}
								}

									 ?></a>
								</div>
								<!-- STUDENT TOOLBAR --><?php require ('../init/.cours/cours.toolbar.php');?>

								<!-- NOTIFICATION MANAGER --><?php require('./cours/notificationCours.php');?>
							</div>
							<?php 
								
								if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$cours_page = $_GET['page'];

									if($cours_page == "information") {
										require('./cours/information.php');
									}elseif($cours_page == "notes") {
										require('./cours/notes.php');
									}elseif($cours_page == "promotion-notes") {
										require('./cours/promotion-notes.php');
									}elseif($cours_page == "etudiants") {
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
				
				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>
</body>
</html>