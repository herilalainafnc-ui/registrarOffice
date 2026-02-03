<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Student</title>
	<?php
	// Vérification d'accès pour professeurs et étudiants
	require_once('../data/middleware.php');
	initMiddleware($dtb);
	
	$requestedStudentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	
	// Si c'est un étudiant, vérifier qu'il accède à ses propres données
	if (isStudent()) {
		$studentIdFromSession = getStudentId();
		$checkStudent = DB::selectOne("SELECT student_id FROM tbl_2024_etudiant WHERE id = :id", ['id' => $requestedStudentId]);
		if (!$checkStudent || $checkStudent['student_id'] !== $studentIdFromSession) {
			header('Location: ./student.dashboard.php');
			exit;
		}
	}
	
	// Si c'est un professeur, vérifier qu'il a accès à cet étudiant
	if (isTeacher() && !isAdmin() && !isRegistrar()) {
		$checkStudent = DB::selectOne("SELECT student_id FROM tbl_2024_etudiant WHERE id = :id", ['id' => $requestedStudentId]);
		if ($checkStudent && !teacherCanAccessStudent($checkStudent['student_id'])) {
			header('Location: ./teacher.dashboard.php?error=access_denied');
			exit;
		}
	}
	?>
	<style>
		/* Mobile - full page scroll */
		@media (max-width: 1023px) {
			.student-content-wrapper,
			.student-main-container,
			.student-flex-container,
			.student-profile-sidebar {
				max-height: none !important;
				height: auto !important;
				overflow: visible !important;
			}
		}
		
		/* Desktop - single scroll, full height containers */
		@media (min-width: 1024px) {
			.student-flex-container {
				display: flex !important;
				flex-direction: row !important;
				height: 100% !important;
				overflow: hidden !important;
			}
			.student-flex-container > .flex {
				height: 100% !important;
				overflow: hidden !important;
			}
			.student-profile-sidebar {
				height: 100% !important;
				overflow-y: auto !important;
				overflow-x: hidden !important;
				flex-shrink: 0 !important;
			}
			.student-content-wrapper {
				height: 100% !important;
				overflow-y: auto !important;
				overflow-x: hidden !important;
				flex-grow: 1 !important;
			}
			/* Hide scrollbar for sidebar, keep for content */
			.student-profile-sidebar::-webkit-scrollbar {
				width: 0px !important;
			}
		}
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="student-main-container w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
				
				<div class="student-flex-container w-full px-0.5 flex-1">
					<div class="flex flex-col lg:flex-row w-full h-full">
						<!-- <div class="<?=$bg_one_color?> my-1 mx-0.5 w-3/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result" style="height: calc(100vh - 157px);"></div> -->
<?php
	$id = (int)$_GET['id']; // Cast en int pour sécurité
	
	// Requête sécurisée avec DB::selectOne
	$profil = DB::selectOne("SELECT * FROM tbl_2024_etudiant WHERE id = :id LIMIT 1", ['id' => $id]);

if($profil) {
$student_id = $profil['student_id'];
$student_nom = $profil['student_nom'];
$student_prenom = $profil['student_prenom'];
$level = $profil['annee_etude'];
$annee_scolaire = $profil['annee_scolaire'];
$etude_envisage = $profil['etude_envisage'];
$etude_option = $profil['etude_option'];
$student_tel = $profil['student_tel'];
$image_student = $profil['image_student'];
$lookup_code = $profil['lookup_code'];
$status = $profil['status'];
$abonment = $profil['abonment'];
$date_entry = $profil['date_entry'];
$yes = 1;


	// Requête sécurisée
	$showMention = DB::selectOne('SELECT * FROM filiere WHERE filiere_description = :desc', ['desc' => $etude_envisage]);
	$etude_envisage_ang = $showMention ? $showMention['filiere_description_ang'] : '';


	// Requête sécurisée
	$showParcours = DB::selectOne('SELECT * FROM filiere_parcours WHERE description = :desc', ['desc' => $etude_option]);
	if(!empty($showParcours)){
		$etude_option_ang = $showParcours['description_ang'];	
	}else{
		$etude_option_ang = '';
	}

						require('../init/.student/student.menubar.php');
 ?>					
					

						<div class="student-content-wrapper my-1 mx-0.5 w-full lg:w-9/12 xxl:w-7/12 <?=$bg_one_color?> <?=$txt_one_color?> pb-8">
							<div class="min-h-[70px] p-2 flex flex-wrap lg:flex-nowrap">
								<div class="w-full lg:w-4/12 px-1 mb-2 lg:mb-0">
									<a class="sm:text-xs lg:text-lg mt-3"><?php 

									if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$sdt_page = $_GET['page'];
									if($sdt_page == "information") {
										echo strtoupper($sdt_page);
										if(!empty($profil['last_change_user_id'])) {?>
									 
										 <p class="toolInactive text-xs">Dernière modification<br>le <?=$profil['last_change_datetime']?>
										 par <?php 
							// Requête sécurisée
							$showUserModif = DB::findUser($profil['last_change_user_id']);
							echo $showUserModif ? $showUserModif['prenom'] : 'Inconnu';
										  ?>
										</p>
									 
									 <?php }else{?>
									 	<p class="toolInactive text-xs">Créé le <?=$profil['date_entry']?></p>
									  <?php	
									 	}
									}elseif($sdt_page == "transcript") {
										echo "TRANSCRIPT / SEMESTRE";
									}elseif($sdt_page == "transcriptSS") {
										echo "TRANSCRIPT / SESSION";
									}elseif($sdt_page == "newCours") {
										echo "NOUVEAUX COURS";
									}elseif($sdt_page == "bulletin") {
										echo strtoupper($sdt_page);
									}elseif($sdt_page == "diplome") {
										echo strtoupper($sdt_page);
									}elseif($sdt_page == "courssupprim") {
										echo "COURS RETIRÉ";
									}elseif($sdt_page == "histNotes") {
										echo "AUDIT DES MODIFICATIONS DE NOTES";
									}elseif($sdt_page == "histInfos") {
										echo "HISTORIQUE DES MODIFICATIONS";
									}
								}

									 ?></a>
								</div>
								<!-- STUDENT TOOLBAR --><?php require ('../init/.student/student.toolbar.php');?>
								<!-- NOTIFICATION MANAGER --><?php require('./student/notificationStd.php');?>
							</div>
							<?php 
								
								if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$sdt_page = $_GET['page'];

									if($sdt_page == "information") {
										require('./student/information.php');
									}elseif($sdt_page == "transcript") {
										require('./student/transcript.php');
									}elseif($sdt_page == "transcriptSS") {
										require('./student/transcriptSS.php');
									}elseif($sdt_page == "newCours") {
										require('./student/new.cours.php');
									}elseif($sdt_page == "bulletin") {
										require('./student/bulletin.php');
									}elseif($sdt_page == "diplome") {
										require('./student/diplome.php');
									}elseif($sdt_page == "courssupprim") {
										require('./student/courssupprim.php');
									}elseif($sdt_page == "histNotes") {
										require('./student/historique-notes.php');
									}elseif($sdt_page == "histInfos") {
										require('./student/historique-infos.php');
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