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
	<?php
	// Vérification d'accès pour professeurs
	require_once('../data/middleware.php');
	initMiddleware($dtb);
	
	$requestedCourseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	
	// Les étudiants ne peuvent pas accéder à cette page
	if (isStudent() && !isAdmin() && !isRegistrar()) {
		header('Location: ' . $app_base . '/student/notes');
		exit;
	}
	
	// Si c'est un professeur, vérifier qu'il a accès à ce cours
	if (isTeacher() && !isAdmin() && !isRegistrar()) {
		if (!teacherCanAccessCourse($requestedCourseId)) {
			header('Location: ' . $app_base . '/teacher/dashboard?error=access_denied');
			exit;
		}
	}

	/* Le titre de l'onglet : le cours (code et intitule), puis la page
	   ouverte avec le libelle de son menu. Hors perimetre du compte, la page
	   ne montre rien et l'onglet dit « Fiche du cours ». */
	require_once('../data/perimetre.php');
	require_once('../data/cours.sigle.php');
	$ongletPages = [
		'information'     => 'Information',
		'notes'           => 'Intégration de notes',
		'promotion-notes' => 'Notes Work/Chapelle',
		'etudiants'       => 'Étudiants',
	];
	$ongletTitre = $ongletPages[$_GET['page'] ?? ''] ?? 'Fiche du cours';
	$ongletCours = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id = :id LIMIT 1');
	$ongletCours->execute(['id' => $requestedCourseId]);
	$ongletCours = $ongletCours->fetch();
	if ($ongletCours && perimetreCoursVisible($ongletCours)) {
		$ongletNom = trim(coursSigleAffiche($ongletCours) . ' ' . ($ongletCours['title'] ?? ''));
		if ($ongletNom !== '') { $ongletTitre = $ongletNom . ' — ' . $ongletTitre; }
	}
	?>
	<title><?= htmlspecialchars($ongletTitre, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/cours-information.css?v=<?=@filemtime(__DIR__ . '/css/cours-information.css')?>">
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
	/* Entier et requete preparee : l'id vient de l'adresse. Il est ensuite
	   reecrit dans les liens du menu et de la barre d'outils. */
	$id = (int) ($_GET['id'] ?? 0);
	$retrouve = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id = :id LIMIT 1');
	$retrouve->execute(['id' => $id]);

/* Hors du perimetre du compte (data/perimetre.php) : le cours est
   introuvable, comme un identifiant qui n'existe pas. */
require_once('../data/perimetre.php');
$profil = $retrouve->fetch();
if ($profil && !perimetreCoursVisible($profil)) { $profil = false; }

if($profil) {
$sigle = $profil['Sigle'];            // cle technique : sert a retrouver les notes
require_once('../data/cours.sigle.php');
coursSigleAliasInstaller($dtb);
$sigleAffiche = coursSigleAffiche($profil);   // code montre ; voir data/cours.sigle.php
$title = $profil['title'];
$title_english = $profil['title_english'];
$dep_desc = $profil['dep_desc'];
$date_entry = $profil['date_entry'];
$yes = 1;

						require('../init/.cours/cours.menubar.php');
 ?>					
					

						<?php /* Le contenu de la fiche : remplace sans recharger quand on change
						         de page dans le menu du cours (src/js/zone-ajax.js). */ ?>
						<div class="my-1 p-2 mx-0.5 sm:w-10/12 lg:w-9/12 xxl:w-7/12 <?=$bg_one_color?> text-white" style="max-height: calc(100vh - 160px);" id="zoneCours" data-zone-ajax>
							<?php /* La fiche d'information porte son propre en-tete (src/cours/information.php) :
							         ni titre en capitales ni barre d'outils, toute inactive sur cette page. Les
							         fenetres de notificationCours.php restent : « Supprimer » du menu ouvre l'une d'elles. */
							if (($_GET['page'] ?? '') === 'information'): ?>
							<?php require('./cours/notificationCours.php'); ?>
							<?php else: ?>
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
							<?php endif; ?>
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

<?php } else { /* Identifiant inconnu, ou hors du perimetre du compte (data/perimetre.php) :
                  meme reponse, pour que l'autre cycle reste invisible. */ ?>
						<div class="w-full px-6 py-16 text-center text-slate-400" role="status">
							<i class="bi bi-journal-x text-3xl" aria-hidden="true"></i>
							<p class="mt-3 text-base font-semibold text-slate-200">Cours introuvable</p>
							<p class="mt-1 text-sm">Aucun cours ne correspond à cette adresse.</p>
							<a class="inline-block mt-4 text-cyan-400 underline" href="<?=$app_base?>/courses">Retour à la liste des cours</a>
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