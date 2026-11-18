<?php
// Rediriger si trailing slash (conflit dossier student/ vs student.php)
if (preg_match('#/student/$#', $_SERVER['REQUEST_URI'])) {
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
	// Vérification d'accès pour professeurs et étudiants
	require_once('../data/middleware.php');
	initMiddleware($dtb);
	
	$requestedStudentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	
	// Si c'est un étudiant, vérifier qu'il accède à ses propres données
	if (isStudent()) {
		$studentIdFromSession = getStudentId();
		$checkStudent = DB::selectOne("SELECT student_id FROM tbl_2024_etudiant WHERE id = :id", ['id' => $requestedStudentId]);
		if (!$checkStudent || $checkStudent['student_id'] !== $studentIdFromSession) {
			header('Location: ' . $app_base . '/student/notes');
			exit;
		}
	}
	
	// Si c'est un professeur, vérifier qu'il a accès à cet étudiant
	if (isTeacher() && !isAdmin() && !isRegistrar()) {
		$checkStudent = DB::selectOne("SELECT student_id FROM tbl_2024_etudiant WHERE id = :id", ['id' => $requestedStudentId]);
		if ($checkStudent && !teacherCanAccessStudent($checkStudent['student_id'])) {
			header('Location: ' . $app_base . '/teacher/dashboard?error=access_denied');
			exit;
		}
	}

	/* Le titre de l'onglet : l'etudiant, puis la page ouverte de sa fiche
	   avec le libelle de son menu. Ecrit apres les controles d'acces, et
	   seulement dans le perimetre du compte (data/perimetre.php) : sinon la
	   page ne montre rien, et l'onglet dit « Fiche étudiant ». */
	require_once('../data/perimetre.php');
	$ongletPages = [
		'information'  => 'Information',
		'transcript'   => 'Historique des notes par semestre',
		'transcriptSS' => 'Historique des notes',
		'newCours'     => 'Ajout de cours',
		'bulletin'     => 'Relevé de notes',
		'diplome'      => 'Diplôme',
		'courssupprim' => 'Cours retiré',
		'histNotes'    => 'Audit notes',
		'histInfos'    => 'Historique des modifications',
	];
	$ongletTitre = $ongletPages[$_GET['page'] ?? ''] ?? 'Fiche étudiant';
	$ongletFiche = DB::selectOne("SELECT * FROM tbl_2024_etudiant WHERE id = :id LIMIT 1", ['id' => $requestedStudentId]);
	if ($ongletFiche && perimetreEtudiantVisible($ongletFiche)) {
		$ongletNom = trim(($ongletFiche['student_nom'] ?? '') . ' ' . ($ongletFiche['student_prenom'] ?? ''));
		if ($ongletNom !== '') { $ongletTitre = $ongletNom . ' — ' . $ongletTitre; }
	}
	?>
	<title><?= htmlspecialchars($ongletTitre, ENT_QUOTES, 'UTF-8') ?></title>
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

	/* Hors du perimetre du compte (data/perimetre.php) : la fiche est
	   introuvable, comme un identifiant qui n'existe pas. */
	require_once('../data/perimetre.php');
	$profilCreeHorsCycle = null;
	if ($profil && !perimetreEtudiantVisible($profil)) {
		/* Exception : l'etudiant que ce compte vient lui-meme de creer
		   (app/.student/addStd.php note les creations de la session), mais
		   dans l'autre cycle ou sans niveau. Repondre « introuvable » juste
		   apres « Enregistrer » fait croire que la creation a echoue, et
		   pousse a recommencer : on confirme et on explique. */
		$creesSession = array_map('intval', $_SESSION['creations_etudiant'] ?? []);
		if (isset($_GET['cree']) && in_array((int) $profil['id'], $creesSession, true)) {
			$profilCreeHorsCycle = $profil;
		}
		$profil = null;
	}

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
					

						<?php /* Le contenu de la fiche : remplace sans recharger quand on change
						         de page dans le menu de la fiche (src/js/zone-ajax.js). */ ?>
						<div class="student-content-wrapper my-1 mx-0.5 w-full lg:w-9/12 xxl:w-7/12 <?=$bg_one_color?> <?=$txt_one_color?> pb-8" id="zoneFiche" data-zone-ajax>
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

									/* Compte etudiant : seules ses informations s'ouvrent ici. Releves,
									   bulletins et historiques montreraient toutes ses notes, y compris
									   celles d'une session non publiee (data/espace.etudiant.php). */
									if ((isStudent() || (int) ($_SESSION['user_level'] ?? 0) === 8)
										&& !in_array($sdt_page, ['information', 'histInfos'], true)) {
										echo '<div class="p-6 text-center text-slate-400">Vos notes se consultent dans <a class="text-cyan-400 underline" href="' . $app_base . '/student/notes">Mes Notes</a>.</div>';
									}elseif (in_array($sdt_page, ['newCours', 'courssupprim'], true) && (int) ($rg_user['level'] ?? 99) > 3) {
										/* Ajout et retrait de cours : niveau 3 et moins, comme la barre d'outils de la fiche. */
										echo '<div class="p-6 text-center text-slate-400">L\'ajout et le retrait de cours sont réservés au registrariat.</div>';
									}elseif($sdt_page == "information") {
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

<?php } elseif (!empty($profilCreeHorsCycle)) {
	$hc = $profilCreeHorsCycle;
	$hcNiveau = trim((string) $hc['annee_etude']);
	$hcCycle = $hcNiveau === '' ? null : ((int) $hcNiveau >= 4 ? 'Master' : 'Licence');
?>
						<div class="w-full px-6 py-16 text-center text-slate-400" role="status">
							<i class="bi bi-check-circle text-3xl text-emerald-400" aria-hidden="true"></i>
							<p class="mt-3 text-base font-semibold text-slate-200">Étudiant créé</p>
							<p class="mt-1 text-sm"><?= htmlspecialchars(trim($hc['student_nom'] . ' ' . $hc['student_prenom']), ENT_QUOTES, 'UTF-8') ?> — matricule <?= htmlspecialchars((string) $hc['student_id'], ENT_QUOTES, 'UTF-8') ?>.</p>
							<p class="mt-3 text-sm max-w-xl mx-auto">
								<?php if ($hcCycle === null): ?>
									Sa fiche n'apparaît pas dans votre liste : aucun niveau d'étude ne lui a été donné, et votre espace ne montre que le <?= htmlspecialchars(perimetreLibelle(), ENT_QUOTES, 'UTF-8') ?>. Le registrariat peut compléter son niveau.
								<?php else: ?>
									Sa fiche n'apparaît pas dans votre liste : il est en <?= $hcCycle ?>, et votre espace ne montre que le <?= htmlspecialchars(perimetreLibelle(), ENT_QUOTES, 'UTF-8') ?>. Le registrariat la retrouve dans la liste complète.
								<?php endif; ?>
							</p>
							<a class="inline-block mt-4 text-cyan-400 underline" href="<?=$app_base?>/students/create">Créer un autre étudiant</a>
							<span class="mx-2" aria-hidden="true">·</span>
							<a class="inline-block mt-4 text-cyan-400 underline" href="<?=$app_base?>/etudiants">Retour à la liste des étudiants</a>
						</div>
<?php } else { /* Identifiant inconnu, ou hors du perimetre du compte (data/perimetre.php) :
                  meme reponse, pour que l'autre cycle reste invisible. */ ?>
						<div class="w-full px-6 py-16 text-center text-slate-400" role="status">
							<i class="bi bi-person-x text-3xl" aria-hidden="true"></i>
							<p class="mt-3 text-base font-semibold text-slate-200">Fiche introuvable</p>
							<p class="mt-1 text-sm">Aucun étudiant ne correspond à cette adresse.</p>
							<a class="inline-block mt-4 text-cyan-400 underline" href="<?=$app_base?>/etudiants">Retour à la liste des étudiants</a>
						</div>
<?php
}
 ?>
					</div>
				</div>
				
				<?php require('../init/footer.php'); ?>

<?php /* Retour de « Créer un étudiant » (app/.student/addStd.php) : la fiche
         confirme la creation. Avant, rien ne la signalait : on croyait l'envoi
         perdu et on recommencait. */
if (!empty($profil) && isset($_GET['cree'])):
	$creeTexte = htmlspecialchars(trim($profil['student_nom'] . ' ' . $profil['student_prenom']), ENT_QUOTES, 'UTF-8')
		. ' — matricule ' . htmlspecialchars((string) $profil['student_id'], ENT_QUOTES, 'UTF-8') . '.';
	if ($_GET['cree'] === 'deja') {
		$creeTexte .= ' Le formulaire est arrivé plusieurs fois : un seul étudiant a été enregistré.';
	}
	/* Candidat de Master : le mail d'acceptation avec ses identifiants est
	   parti (ou non) a la creation (app/.student/addStd.php). */
	$mailCreation = $_SESSION['mail_creation'][(int) $profil['id']] ?? null;
	unset($_SESSION['mail_creation'][(int) $profil['id']]);
	/* Adresse universitaire changee a l'enregistrement : celle du formulaire
	   etait deja prise (data/adresse.etudiant.php). */
	$adresseCreation = $_SESSION['adresse_creation'][(int) $profil['id']] ?? null;
	unset($_SESSION['adresse_creation'][(int) $profil['id']]);
	/* Compte Google cree (ou non) par l'API a l'enregistrement (data/google.admin.php). */
	$googleCreation = $_SESSION['google_creation'][(int) $profil['id']] ?? null;
	unset($_SESSION['google_creation'][(int) $profil['id']]);
?>
				<script>
					(function () {
						function montrer() {
							if (window.Toast) { Toast.success(<?= json_encode($creeTexte, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, { title: 'Étudiant créé', duration: 9000 }); }
<?php if ($adresseCreation): ?>
							if (window.Toast) { Toast.warning(<?= json_encode($adresseCreation, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, { title: 'Adresse universitaire', duration: 12000 }); }
<?php endif; ?>
<?php if ($googleCreation): ?>
							if (window.Toast) { Toast[<?= json_encode($googleCreation['cree'] ? 'success' : 'warning') ?>](<?= json_encode($googleCreation['raison'] . (($googleCreation['cree'] || !empty($googleCreation['existait'])) ? '' : ((int) ($_SESSION['user_level'] ?? 99) === 1 ? ' Vous pourrez le créer depuis Communication › Adresses Google.' : ' Un super administrateur pourra le créer depuis Communication › Adresses Google.')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, { title: <?= json_encode($googleCreation['cree'] ? 'Compte Google créé' : 'Compte Google', JSON_UNESCAPED_UNICODE) ?>, duration: 12000 }); }
<?php endif; ?>
<?php if ($mailCreation): ?>
							if (window.Toast) { Toast[<?= json_encode($mailCreation['envoye'] ? 'success' : 'warning') ?>](<?= json_encode($mailCreation['raison'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, { title: <?= json_encode($mailCreation['envoye'] ? 'Mail envoyé' : 'Mail non envoyé', JSON_UNESCAPED_UNICODE) ?>, duration: 12000 }); }
<?php endif; ?>
						}
						if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', montrer); } else { montrer(); }
						/* Recharger la fiche ne repete pas le message. */
						try { var u = new URL(location.href); u.searchParams.delete('cree'); history.replaceState(history.state, '', u.pathname + u.search + u.hash); } catch (e) {}
					})();
				</script>
<?php endif; ?>

			</div>

		</div>

	</div>
<?php include('./student.transition.php'); ?>
</body>
</html>