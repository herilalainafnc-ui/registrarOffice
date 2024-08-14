<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Student</title>
</head>
<body class="<?=$bg_three_color?> text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-10/12">
			<!-- BARRE D'OUTILS --><?php require('../init/toolbar.php');?>
				
				<div class="w-full px-0.5" style="height: calc(100vh - 157px);">
					<div class="flex w-full">
						<!-- <div class="<?=$bg_one_color?> my-1 mx-0.5 w-3/12 p-2 text-slate-100 overflow-auto hidden" id="stdSearch-result" style="height: calc(100vh - 157px);"></div> -->
<?php
	$id = $_GET['id'];
	$retrouve = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE id ='".$id."' LIMIT 1");

if($retrouve->rowCount() > 0) {
$profil = $retrouve->fetch();
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
$date_entry = $profil['date_entry'];
$yes = 1;


	$searchMention = $dtb->query('SELECT * FROM filiere WHERE filiere_description = "'.$etude_envisage.'"');
	$showMention=$searchMention->fetch();
	$etude_envisage_ang = $showMention['filiere_description_ang'];


		$searchParcours = $dtb->query('SELECT * FROM filiere_parcours WHERE description = "'.$etude_option.'"');
		$showParcours=$searchParcours->fetch();
		if(!empty($showParcours)){
			$etude_option_ang = $showParcours['description_ang'];	
		}else{
		$etude_option_ang = '';
		}

						require('../init/.student/student.menubar.php');
 ?>					
					

						<div class="my-1 mx-0.5 lg:w-9/12 xl:w-9/12 xxl:w-7/12 <?=$bg_one_color?> <?=$txt_one_color?>" style="max-height: calc(100vh - 160px);">
							<div class="h-[70px] p-2 flex">
								<div class="w-4/12 px-1">
									<b class="text-lg mt-3"><?php 

									if (isset($_GET['page']) AND !empty($_GET['page'])) {
									$sdt_page = $_GET['page'];
									if($sdt_page == "information") {
										echo strtoupper($sdt_page);
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
										echo "COURS SUPPRIMÉ";
									}
								}

									 ?></b>
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