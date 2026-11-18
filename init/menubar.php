<?php /* Styles : src/css/theme.css, section « la coque ». */ ?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Menu -->
<aside class="sidebar-menu" id="sidebarMenu" aria-label="Navigation principale">
<?php
	/* Le compte connecte, en tete de la barre comme dans la reference. La
	   photo et les initiales sont calculees par init/topbar.php ; une page qui
	   inclurait ce menu sans la barre du haut retombe sur les initiales. */
	$sideAvatar = isset($rg_avatar_url) ? (string) $rg_avatar_url : '';
	$sideInitiales = isset($rg_initiales) ? (string) $rg_initiales : '?';
	$sideNom = trim(($rg_user['prenom'] ?? '') . ' ' . ($rg_user['nom'] ?? ''));
	$sideRoles = [
		'superadmin'   => 'Super-administrateur',
		'administrator'=> 'Administration',
		'registrar'    => 'Registrariat',
		'comptabilite' => 'Comptabilité',
		'media'        => 'Média',
		'chef_mention' => 'Chef de mention',
		'teacher'      => 'Enseignant',
		'student'      => 'Étudiant',
		'work_education' => 'Work Education',
	];
	$sidePrivilege = (string) ($rg_user['privilege'] ?? '');
	$sideRole = $sideRoles[$sidePrivilege] ?? ucfirst($sidePrivilege);
	/* Compte limite a un cycle (data/perimetre.php) : il le lit ici, a cote de
	   son role, et sait pourquoi l'autre cycle n'apparait nulle part. */
	require_once(__DIR__ . '/../data/perimetre.php');
	if (perimetreLibelle() !== '') { $sideRole .= ' · ' . perimetreLibelle(); }
	$sidePoste = trim((string) ($rg_user['post'] ?? ''));
?>
	<a href="<?=$app_base?>/tableau-de-bord" class="side-brand" aria-label="Infinit Registrar, accueil">
		<span class="side-brand__mark"><img src="<?=$app_base?>/file/logo-coldbloud.png" alt=""></span>
		<span class="side-brand__nom">Infinit <b>Registrar</b></span>
	</a>

<?php
	$sideNomAffiche = $sideNom !== '' ? $sideNom : (string) ($rg_user['pseudo'] ?? '');
	$sideEtiquette = 'Menu du compte : ' . $sideNomAffiche . ', ' . $sideRole;
?>
	<?php /* Le compte n apparait qu ICI au bureau : la barre du haut ne repete
	         plus le nom ni la photo. Sur mobile, ou la barre laterale est
	         repliee, c est l avatar de la barre du haut qui ouvre ce menu.
	         strategy « fixed » : la barre laterale coupe ce qui depasse, le
	         menu doit se positionner par rapport a l ecran. */ ?>
	<div class="dropdown side-compte">
		<button type="button" class="side-profil" data-bs-toggle="dropdown" aria-expanded="false"
		        data-bs-popper-config='{"strategy":"fixed"}'
		        title="<?=htmlspecialchars($sideEtiquette, ENT_QUOTES, 'UTF-8')?>"
		        aria-label="<?=htmlspecialchars($sideEtiquette, ENT_QUOTES, 'UTF-8')?>">
			<span class="side-profil__photo">
<?php if ($sideAvatar !== ''): ?>
				<img src="<?=htmlspecialchars($sideAvatar, ENT_QUOTES, 'UTF-8')?>" alt="" width="44" height="44" decoding="async">
<?php else: ?>
				<?=htmlspecialchars($sideInitiales, ENT_QUOTES, 'UTF-8')?>
<?php endif; ?>
			</span>
			<span class="side-profil__texte">
				<span class="side-profil__nom"><?=htmlspecialchars($sideNomAffiche, ENT_QUOTES, 'UTF-8')?></span>
				<span class="side-profil__role"><?=htmlspecialchars($sideRole, ENT_QUOTES, 'UTF-8')?></span>
			</span>
			<i class="bi bi-chevron-expand side-profil__chevron" aria-hidden="true"></i>
		</button>
		<ul class="dropdown-menu side-compte__menu">
<?php if ($sidePoste !== ''): ?>
			<li class="side-compte__poste"><?=htmlspecialchars($sidePoste, ENT_QUOTES, 'UTF-8')?></li>
<?php endif; ?>
			<li><a class="dropdown-item" href="<?=$app_base?>/my-account"><i class="bi bi-person-gear" aria-hidden="true"></i>Mon compte</a></li>
			<li><a class="dropdown-item" href="<?=$app_base?>/reglement"><i class="bi bi-journal-text" aria-hidden="true"></i>Bulletin académique</a></li>
			<li><a class="dropdown-item" href="<?=$app_base?>/didacticiel"><i class="bi bi-signpost-split" aria-hidden="true"></i>Didacticiel</a></li>
			<?php /* Seulement quand le navigateur le permet (src/js/pwa.js) */ ?>
			<li data-pwa-installer hidden><a class="dropdown-item" href="#"><i class="bi bi-download" aria-hidden="true"></i>Installer l'application</a></li>
			<li><hr class="dropdown-divider"></li>
			<li><a class="dropdown-item side-compte__sortie logOut" href="#"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Se déconnecter</a></li>
		</ul>
	</div>

	<?php /* Pas de champ de recherche ici : la recherche rapide de la barre du
	         haut (Ctrl+K ou « / ») trouve les pages de ce menu, et aussi les
	         etudiants, cours et enseignants. Voir src/js/recherche.js. */ ?>

	<nav class="py-1">
<?php
	/* Un enseignant ne navigue que dans ce qui touche ses propres cours : ni la
	   liste complète des étudiants, ni les classements de toute l'université.
	   Les contrôleurs appliquent la même règle (BaseController::refuserEnseignant) ;
	   ce test ne fait que cesser de montrer des portes fermées.

	   Les niveaux 1 à 3 gardent tout : un registraire par ailleurs enseignant
	   reste registraire. */
	$estEnseignant = (int)$rg_user['level'] > 3
		&& (function_exists('isTeacher') ? isTeacher() : (int)$rg_user['level'] === 7);

	/* « Mon enseignement » : les enseignants, et les chefs de mention, qui sont
	   des professeurs et ont souvent des cours. Le chef garde en plus son menu
	   de chef ($estEnseignant reste faux pour lui). */
	$aEnseignement = $estEnseignant || ((int)$rg_user['level'] > 3
		&& (function_exists('aEspaceEnseignant') ? aEspaceEnseignant() : (int)$rg_user['level'] === 6));

	/* Nombre de remises en attente, affiché en pastille pour le registrariat :
	   sans ce rappel, une feuille transmise pouvait attendre sans que personne
	   ne pense à ouvrir la page. */
	$remisesEnAttente = 0;
	if (!$estEnseignant && function_exists('hasAnyPrivilege') && hasAnyPrivilege(['superadmin', 'registrar'])) {
		try {
			$remisesEnAttente = (int)$dtb->query(
				'SELECT COUNT(*) FROM t_remise_notes WHERE statut = "soumise"')->fetchColumn();
		} catch (Throwable $e) {
			$remisesEnAttente = 0; // table pas encore migrée : le menu reste utilisable
		}
	}
?>
<?php if (function_exists('estWorkEducation') && estWorkEducation()): /* le responsable du Work Education n'a que sa page (init/head.php) */ ?>
		<button type="button" class="menu-section-header" aria-expanded="true">Work Education</button>

		<div class="menu-item <?php if(isRoute('/work-education')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/work-education">
				<div class="menu-icon-wrapper">
					<i class="bi bi-tools menu-icon"></i>
				</div>
				<span class="menu-title">Tâches des étudiants</span>
			</a>
		</div>
<?php else: /* tout le menu du personnel, jusqu'a la fin de nav */ ?>
<?php if (!$estEnseignant): ?>
		<!-- Accueil du personnel (src/tableau-de-bord.php) -->
		<div class="menu-item <?php if(isRoute('/tableau-de-bord')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/tableau-de-bord">
				<div class="menu-icon-wrapper">
					<i class="bi bi-grid-1x2 menu-icon"></i>
				</div>
				<span class="menu-title">Tableau de bord</span>
			</a>
		</div>
<?php endif; ?>

<?php if ($aEnseignement): ?>
		<!-- Espace enseignant -->
		<button type="button" class="menu-section-header" aria-expanded="true">Mon enseignement</button>

		<div class="menu-item <?php if(isRoute('/teacher/dashboard')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/teacher/dashboard">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-workspace menu-icon"></i>
				</div>
				<span class="menu-title">Mes cours</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/teacher/remise-notes')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/teacher/remise-notes">
				<div class="menu-icon-wrapper">
					<i class="bi bi-journal-check menu-icon"></i>
				</div>
				<span class="menu-title">Remise des notes</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/teacher/presence')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/teacher/presence">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-check menu-icon"></i>
				</div>
				<span class="menu-title">Présences</span>
			</a>
		</div>

		<?php if ((int)$rg_user['level'] > 3): /* depuis son adresse Google ; les niveaux 1 a 3 ont la « Boîte mail » no-reply */ ?>
		<div class="menu-item <?php if(isRoute('/settings/bulk-email')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/settings/bulk-email">
				<div class="menu-icon-wrapper">
					<i class="bi bi-envelope menu-icon"></i>
				</div>
				<span class="menu-title">E-mails</span>
			</a>
		</div>
		<?php endif; ?>
<?php endif; ?>

<?php if (!$estEnseignant && function_exists('hasAnyPrivilege') && hasAnyPrivilege(['superadmin', 'registrar'])): ?>
		<!-- Validation des remises -->
		<button type="button" class="menu-section-header" aria-expanded="true">Notes</button>

		<div class="menu-item <?php if(isRoute('/remise-notes')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/remise-notes">
				<div class="menu-icon-wrapper">
					<i class="bi bi-clipboard2-check menu-icon"></i>
				</div>
				<span class="menu-title">Remises de notes</span>
				<?php if ($remisesEnAttente > 0): ?>
				<span class="menu-pastille" title="Remises en attente de validation"><?=$remisesEnAttente?></span>
				<?php endif; ?>
			</a>
		</div>

		<?php /* Depassements des 15 % d'absences signales par les enseignants (§476). */
		      require_once(__DIR__ . '/../data/presence.php');
		      $signalementsEnAttente = presenceSignalementsEnAttente($dtb); ?>
		<div class="menu-item <?php if(isRoute('/presence')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/presence">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-check menu-icon"></i>
				</div>
				<span class="menu-title">Assiduité</span>
				<?php if ($signalementsEnAttente > 0): ?>
				<span class="menu-pastille" title="Étudiants au-delà de 15 % d'absences, à traiter"><?=$signalementsEnAttente?></span>
				<?php endif; ?>
			</a>
		</div>
<?php endif; ?>

		<?php if (!$estEnseignant && $rg_user['level'] <= 6): /* Inscription est la seule entrée ouverte jusqu'au niveau 6 */ ?>
		<!-- Inscription -->
		 <button type="button" class="menu-section-header" aria-expanded="true">Admissions</button>
		<?php endif; ?>
		<?php if (!$estEnseignant && $rg_user['level'] <= 6): ?>
		<div class="menu-item <?php if(isRoute('/inscription')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/inscription/inscription" target="_blank">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-fill-add menu-icon"></i>
				</div>
				<span class="menu-title">Inscription</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if (!$estEnseignant && (int)($rg_user['level'] ?? 99) <= 4): /* comme InscriptionController::tardives */ ?>
		<div class="menu-item <?php if(isRoute('/inscriptions/tardives')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/inscriptions/tardives">
				<div class="menu-icon-wrapper">
					<i class="bi bi-hourglass-split menu-icon"></i>
				</div>
				<span class="menu-title">Inscriptions tardives</span>
			</a>
		</div>
		<?php endif; ?>

		<?php /* Superadmin et registrariat seulement, comme AdmissionController. */ ?>
		<?php if (hasAnyPrivilege(['superadmin', 'registrar'])): ?>
		<div class="menu-item <?php if(isRoute('/candidatures')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/candidatures">
				<div class="menu-icon-wrapper">
					<i class="bi bi-inbox-fill menu-icon"></i>
				</div>
				<span class="menu-title">Candidatures</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if (!$estEnseignant): /* toute cette section porte sur l'ensemble des étudiants */ ?>
		<!-- Etudiants Section -->
		<button type="button" class="menu-section-header" aria-expanded="true">Étudiants</button>

		<div class="menu-item <?php if(isRoute('/etudiants')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/etudiants">
				<div class="menu-icon-wrapper">
					<i class="bi bi-people-fill menu-icon"></i>
				</div>
				<span class="menu-title">Liste des étudiants</span>
			</a>
		</div>

		<?php if ($rg_user['level'] <= 3): ?>
		<div class="menu-item <?php if(isRoute('/students/create')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/students/create">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un étudiant</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if (in_array((int)($rg_user['level'] ?? 99), [1, 3, 4], true)): /* comme FinanceController::authorizedReinscription */ ?>
		<div class="menu-item <?php if(isRoute('/finance/authorized-reinscription')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/finance/authorized-reinscription">
				<div class="menu-icon-wrapper">
					<i class="bi bi-check2-circle menu-icon"></i>
				</div>
				<span class="menu-title">Réinscriptions</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ((int)($rg_user['level'] ?? 99) <= 3): /* comme WorkEducationController::index */ ?>
		<div class="menu-item <?php if(isRoute('/work-education')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/work-education">
				<div class="menu-icon-wrapper">
					<i class="bi bi-tools menu-icon"></i>
				</div>
				<span class="menu-title">Work Education</span>
			</a>
		</div>
		<?php endif; ?>

		<div class="menu-item <?php if(isRoute('/deans-list')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/deans-list">
				<div class="menu-icon-wrapper">
					<i class="bi bi-star-fill menu-icon"></i>
				</div>
				<span class="menu-title">Dean's List</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/top-students')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/top-students">
				<div class="menu-icon-wrapper">
					<i class="bi bi-trophy-fill menu-icon"></i>
				</div>
				<span class="menu-title">Top Student</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/session-rankings')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/session-rankings">
				<div class="menu-icon-wrapper">
					<i class="bi bi-bar-chart-line-fill menu-icon"></i>
				</div>
				<span class="menu-title">Classement session</span>
			</a>
		</div>
		<?php endif; /* fin section Étudiants */ ?>

		<?php if (!$estEnseignant && ($rg_user['level'] <= 3 || hasAnyPrivilege(['superadmin', 'registrar']))): /* liste : superadmin et registrariat ; création : niveau 3 et moins */ ?>
		<!-- Professeurs Section -->
		<button type="button" class="menu-section-header" aria-expanded="true">Professeurs</button>
		
		<?php if (hasAnyPrivilege(['superadmin', 'registrar'])): ?>
		<div class="menu-item <?php if(isRoute(['/professors', '/professor'])) { echo "active"; } ?>">
			<a href="<?=$app_base?>/professors">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-lines-fill menu-icon"></i>
				</div>
				<span class="menu-title">Liste des professeurs</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 3): ?>
		<div class="menu-item">
			<a href="#" id="addProf">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un professeur</span>
			</a>
		</div>
		<?php endif; ?>
		
		<?php endif; ?>

		<?php if (!$estEnseignant && ($rg_user['level'] <= 3 || hasAnyPrivilege(['superadmin', 'registrar']))): /* liste : superadmin et registrariat ; création : niveau 3 et moins */ ?>
		<!-- Cours Section -->
		<button type="button" class="menu-section-header" aria-expanded="true">Cours</button>
		
		<?php if (hasAnyPrivilege(['superadmin', 'registrar'])): ?>
		<div class="menu-item <?php if(isRoute(['/courses', '/course'])) { echo "active"; } ?>">
			<a href="<?=$app_base?>/courses">
				<div class="menu-icon-wrapper">
					<i class="bi bi-list-columns-reverse menu-icon"></i>
				</div>
				<span class="menu-title">Liste des cours</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 3): ?>
		<div class="menu-item">
			<a href="#" id="addCours">
				<div class="menu-icon-wrapper">
					<i class="bi bi-file-earmark-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un cours</span>
			</a>
		</div>
		<?php endif; ?>

		<?php endif; ?>

		<?php /* Emploi du temps et Evaluation ne sont pas ouverts aux enseignants
		         (DashboardController::schedule, EvaluationController) : ils
		         n'ont de cette section que la file d'attente, reservee au
		         niveau 1. */ ?>
		<?php if (!$estEnseignant || $rg_user['level'] <= 1): ?>
		<!-- Application Section -->
		<button type="button" class="menu-section-header" aria-expanded="true">Application</button>
		<?php endif; ?>

		<?php if (!$estEnseignant): ?>
		<div class="menu-item <?php if(isRoute('/schedule')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/schedule">
				<div class="menu-icon-wrapper">
					<i class="bi bi-calendar3-week menu-icon"></i>
				</div>
				<span class="menu-title">Emplois du temps</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute(['/evaluation', '/evaluations'])) { echo "active"; } ?>">
			<a href="<?=$app_base?>/evaluation">
				<div class="menu-icon-wrapper">
					<i class="bi bi-ui-radios menu-icon"></i>
				</div>
				<span class="menu-title">Évaluation</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/fusion-pdf')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/fusion-pdf">
				<div class="menu-icon-wrapper">
					<i class="bi bi-file-earmark-pdf menu-icon"></i>
				</div>
				<span class="menu-title">Fusion de PDF</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/qr-generator')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/qr-generator">
				<div class="menu-icon-wrapper">
					<i class="bi bi-qr-code menu-icon"></i>
				</div>
				<span class="menu-title">Générateur de QR Code</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if (in_array((int)($rg_user['level'] ?? 99), [1, 5], true)): /* Media et superadmin, comme DashboardController::retouchePhoto */ ?>
		<div class="menu-item <?php if(isRoute('/retouche-photo')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/retouche-photo">
				<div class="menu-icon-wrapper">
					<i class="bi bi-camera menu-icon"></i>
				</div>
				<span class="menu-title">Retouche photo</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/studio')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/studio">
				<div class="menu-icon-wrapper">
					<i class="bi bi-easel menu-icon"></i>
				</div>
				<span class="menu-title">Studio UAZ</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 1): ?>
		<div class="menu-item <?php if(isRoute('/queue')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/queue">
				<div class="menu-icon-wrapper">
					<i class="bi bi-people-fill menu-icon"></i>
				</div>
				<span class="menu-title">File d'attente</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 1): /* Plan du bureau : superadmin seul (QueueController::plan) */ ?>
		<div class="menu-item <?php if(isRoute('/queue/plan')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/queue/plan">
				<div class="menu-icon-wrapper">
					<i class="bi bi-map menu-icon"></i>
				</div>
				<span class="menu-title">Plan du bureau</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 3): /* Actualités et Newsletter : niveau 3 et moins */ ?>
		<!-- Communication Section -->
		<button type="button" class="menu-section-header" aria-expanded="true">Communication</button>
		<?php endif; ?>
		
		<?php if ($rg_user['level'] <= 3): ?>
		<div class="menu-item <?php if(isRoute('/news')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/news">
				<div class="menu-icon-wrapper">
					<i class="bi bi-megaphone-fill menu-icon"></i>
				</div>
				<span class="menu-title">Actualités</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 3): /* la boite no-reply : n'etait joignable que par Parametres */ ?>
		<div class="menu-item <?php if(isRoute('/settings/bulk-email')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/settings/bulk-email">
				<div class="menu-icon-wrapper">
					<i class="bi bi-envelope menu-icon"></i>
				</div>
				<span class="menu-title">Boîte mail</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ((int)($rg_user['level'] ?? 99) === 1): /* superadmin, comme le controleur */ ?>
		<div class="menu-item <?php if(isRoute('/settings/adresses-google')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/settings/adresses-google">
				<div class="menu-icon-wrapper">
					<i class="bi bi-google menu-icon"></i>
				</div>
				<span class="menu-title">Adresses Google</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 3): ?>
		<div class="menu-item <?php if(isRoute('/newsletter')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/newsletter">
				<div class="menu-icon-wrapper">
					<i class="bi bi-envelope-paper-heart menu-icon"></i>
				</div>
				<span class="menu-title">Newsletter</span>
			</a>
		</div>
		<?php endif; ?>

		<?php if ($rg_user['level'] <= 4): /* comme AdminController::settings (niveau 4 et moins) */ ?>
		<!-- Paramètres Section -->
		<button type="button" class="menu-section-header" aria-expanded="true">Paramètres</button>
		
		<div class="menu-item <?php if(isRoute('/settings')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/settings">
				<div class="menu-icon-wrapper">
					<i class="bi bi-sliders menu-icon"></i>
				</div>
				<span class="menu-title">Paramètres généraux</span>
			</a>
		</div>
		
		<?php endif; ?>
<?php endif; /* fin du menu du personnel */ ?>
	</nav>

	<div class="side-pied">
		<?php /* Les visites guidees de la page, puis toutes (src/js/didacticiel.js remplit le menu) */ ?>
		<div class="dropup" data-tuto="didacticiel">
			<button type="button" class="side-pied__bouton" data-bs-toggle="dropdown" aria-expanded="false"
			        data-bs-popper-config='{"strategy":"fixed"}' title="Didacticiel : visites guidées">
				<i class="bi bi-signpost-split" aria-hidden="true"></i>
				<span>Didacticiel</span>
			</button>
			<ul class="dropdown-menu side-compte__menu dt-menu" id="didacticielMenu">
				<li><a class="dropdown-item" href="<?=$app_base?>/didacticiel"><i class="bi bi-signpost-split" aria-hidden="true"></i>Toutes les visites guidées</a></li>
			</ul>
		</div>
		<button type="button" class="side-pied__bouton" onclick="if (typeof toggleShortcutsModal === 'function') { toggleShortcutsModal(); }" title="Raccourcis clavier (?)">
			<i class="bi bi-keyboard" aria-hidden="true"></i>
			<span>Raccourcis clavier</span>
			<kbd>?</kbd>
		</button>
	</div>

</aside>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
	<i class="bi bi-list"></i>
</button>

<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		const mobileToggle = document.getElementById('mobileMenuToggle');
		const topbarToggle = document.getElementById('topbarMenuToggle');
		const sidebar = document.getElementById('sidebarMenu');
		const overlay = document.getElementById('sidebarOverlay');
		
		if (!sidebar || !overlay) {
			console.error('Menu elements not found');
			return;
		}
		
		const toggleIcon = mobileToggle ? mobileToggle.querySelector('i') : null;
		const topbarIcon = topbarToggle ? topbarToggle.querySelector('i') : null;

		/* Les mouvements de la barre (src/css/theme.css, « barre laterale :
		   mouvement »). Coupes si la personne a demande moins d animations. */
		const sansMouvement = function () {
			return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		};

		/* L icone ☰ / ✕ pivote a chaque changement. */
		function basculerIcone(icone, ouvert) {
			if (!icone) { return; }
			const avant = icone.classList.contains('bi-x-lg');
			icone.classList.toggle('bi-list', !ouvert);
			icone.classList.toggle('bi-x-lg', ouvert);
			if (avant === ouvert) { return; }
			icone.classList.remove('icone-bascule');
			void icone.offsetWidth;
			icone.classList.add('icone-bascule');
		}

		/* A l ouverture du tiroir, ses blocs arrivent en cascade. */
		let finOuverture = 0;
		function openMenu() {
			const dejaOuvert = sidebar.classList.contains('active');
			sidebar.classList.add('active');
			overlay.classList.add('active');
			basculerIcone(toggleIcon, true);
			basculerIcone(topbarIcon, true);
			if (!dejaOuvert && !sansMouvement()) {
				clearTimeout(finOuverture);
				sidebar.classList.remove('est-en-ouverture');
				void sidebar.offsetWidth;
				sidebar.classList.add('est-en-ouverture');
				finOuverture = setTimeout(function () { sidebar.classList.remove('est-en-ouverture'); }, 700);
			}
			document.body.classList.add('sidebar-open'); document.body.style.overflow = 'hidden';
		}

		function closeMenu() {
			sidebar.classList.remove('active', 'est-en-ouverture');
			overlay.classList.remove('active');
			basculerIcone(toggleIcon, false);
			basculerIcone(topbarIcon, false);
			document.body.classList.remove('sidebar-open'); document.body.style.overflow = '';
		}
		
		function toggleMenu() {
			if (sidebar.classList.contains('active')) {
				closeMenu();
			} else {
				openMenu();
			}
		}
		
		if (mobileToggle) {
			mobileToggle.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				toggleMenu();
			});
		}
		
		/* ---- Barre reduite (bureau) ------------------------------------
		   De 1024 a 1439 px, la barre se reduit d elle-meme a ses icones :
		   ses 248 px coupaient les tableaux sur les portables de bureau.
		   Le bouton ☰ la deploie ou la reduit ; ce choix est memorise et
		   l emporte ensuite sur l automatique. La classe est deja posee
		   avant l affichage par init/head.php : pas de saut a l ouverture. */
		const racine = document.documentElement;
		const CLE_RAIL = 'registrar.menu.rail';
		const preference = function () {
			try { return localStorage.getItem(CLE_RAIL); } catch (e) { return null; }
		};
		const railVoulu = function () {
			const l = window.innerWidth;
			if (l < 1024) { return false; }
			const p = preference();
			return p ? p === 'rail' : l < 1440;
		};
		/* Pendant le mouvement, le contenu garde la plus grande de ses deux
		   largeurs (celle de la barre reduite) : ses tableaux ne sont remis en
		   page qu une fois, au debut ou a la fin, et non a chaque image. */
		const rangee = sidebar.parentElement;
		let colonnes = [];
		function figerContenu() {
			/* Les colonnes de contenu seulement : ni la barre, ni son voile,
			   ni les modales incluses avec le menu (fixes ou masquees). */
			libererContenu();
			colonnes = Array.prototype.filter.call(rangee ? rangee.children : [], function (el) {
				if (el === sidebar || el === overlay || /^(SCRIPT|STYLE|TEMPLATE)$/.test(el.tagName)) { return false; }
				const s = getComputedStyle(el);
				return s.display !== 'none' && (s.position === 'static' || s.position === 'relative');
			});
			const largeur = rangee.clientWidth - 76;
			colonnes.forEach(function (el) {
				el.style.setProperty('width', largeur + 'px', 'important');
				el.style.setProperty('flex', 'none', 'important');
			});
		}
		function libererContenu() {
			colonnes.forEach(function (el) {
				if (el.glissement) { el.glissement.cancel(); el.glissement = null; }
				el.style.removeProperty('width');
				el.style.removeProperty('flex');
			});
			colonnes = [];
		}

		let etapeRail = 0;
		let finRail = 0;
		function appliquerRail(rail) {
			clearTimeout(etapeRail);
			clearTimeout(finRail);
			libererContenu();
			racine.classList.remove('side-anime', 'side-repli-en-cours', 'side-deploi-en-cours');
			racine.classList.toggle('side-rail', rail);
			if (topbarToggle && window.innerWidth >= 1024) {
				topbarToggle.setAttribute('aria-expanded', rail ? 'false' : 'true');
				topbarToggle.setAttribute('aria-label', rail ? 'Déployer le menu' : 'Réduire le menu');
			}
		}
		appliquerRail(railVoulu());

		/* Au clic, la meme bascule, mais qui se voit.
		   - La barre anime sa largeur. Elle garde sa mise en page pleine
		     pendant qu elle se reduit (les icones ne sautent pas) et ne prend
		     la mise en page reduite qu a la fin ; pour se deployer, elle quitte
		     la mise en page reduite en restant etroite, puis s elargit.
		   - Le contenu prend tout de suite sa place finale, puis glisse depuis
		     l ancienne par une simple translation (FLIP) : la carte graphique
		     s en charge, sans remise en page a chaque image. Un second clic en
		     cours de route repart de la ou le contenu se trouve. */
		const DUREE_RAIL = 300;
		/* Reduite, ou en train de se reduire. */
		const railVisible = function () {
			return racine.classList.contains('side-rail') || racine.classList.contains('side-repli-en-cours');
		};
		function animerRail(rail) {
			if (railVisible() === rail) { return; }
			if (sansMouvement() || window.innerWidth < 1024 || !Element.prototype.animate) { appliquerRail(rail); return; }
			clearTimeout(etapeRail);
			clearTimeout(finRail);

			const avant = new Map();
			colonnes.forEach(function (el) { avant.set(el, el.getBoundingClientRect().left); });
			figerContenu();
			colonnes.forEach(function (el) {
				if (!avant.has(el)) { avant.set(el, el.getBoundingClientRect().left); }
			});

			racine.classList.add('side-anime');
			if (rail) {
				racine.classList.add('side-repli-en-cours');
			} else if (racine.classList.contains('side-rail')) {
				racine.classList.add('side-deploi-en-cours');
				racine.classList.remove('side-rail');
				void sidebar.offsetWidth;
				racine.classList.remove('side-deploi-en-cours');
			} else {
				racine.classList.remove('side-repli-en-cours'); // demi-tour en cours de route
			}

			colonnes.forEach(function (el) {
				const ecart = avant.get(el) - el.getBoundingClientRect().left;
				if (Math.abs(ecart) < 1) { return; }
				el.glissement = el.animate(
					[{ transform: 'translateX(' + ecart + 'px)' }, { transform: 'none' }],
					{ duration: DUREE_RAIL - 20, easing: 'cubic-bezier(.2, .8, .2, 1)' }
				);
			});

			if (rail) {
				etapeRail = setTimeout(function () { appliquerRail(true); }, DUREE_RAIL);
				return;
			}
			if (topbarToggle) {
				topbarToggle.setAttribute('aria-expanded', 'true');
				topbarToggle.setAttribute('aria-label', 'Réduire le menu');
			}
			finRail = setTimeout(function () {
				racine.classList.remove('side-anime');
				libererContenu();
			}, DUREE_RAIL + 60);
		}

		let attenteRedim = 0;
		window.addEventListener('resize', function () {
			clearTimeout(attenteRedim);
			attenteRedim = setTimeout(function () { appliquerRail(railVoulu()); }, 120);
		});

		if (topbarToggle) {
			topbarToggle.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				if (window.innerWidth >= 1024) {
					const rail = !railVisible();
					animerRail(rail);
					try { localStorage.setItem(CLE_RAIL, rail ? 'rail' : 'plein'); } catch (err) {}
				} else {
					toggleMenu();
				}
			});
		}

		/* En barre reduite, le libelle de chaque entree devient son info-bulle. */
		sidebar.querySelectorAll('.menu-item a').forEach(function (a) {
			const titre = a.querySelector('.menu-title');
			if (titre && !a.hasAttribute('title')) { a.setAttribute('title', titre.textContent.trim()); }
		});
		
		overlay.addEventListener('click', function(e) {
			e.preventDefault();
			closeMenu();
		});
		
		// Close menu when clicking a link
		const menuLinks = sidebar.querySelectorAll('.menu-item a');
		menuLinks.forEach(link => {
			link.addEventListener('click', function() {
				if (window.innerWidth < 1024) {
					closeMenu();
				}
			});
		});
		
		// Handle window resize
		window.addEventListener('resize', function() {
			if (window.innerWidth >= 1024) {
				closeMenu();
			}
		});

		/* ---- Sections repliables ---------------------------------------
		   Une section, c est un titre et les entrees qui le suivent. On se
		   souvient de ce que la personne a replie ; la section qui contient
		   la page courante reste toujours ouverte. */
		const nav = sidebar.querySelector('nav');
		const sections = [];
		let courante = null;
		Array.prototype.forEach.call(nav ? nav.children : [], function (el) {
			if (el.classList.contains('menu-section-header')) {
				courante = { entete: el, entrees: [] };
				sections.push(courante);
			} else if (el.classList.contains('menu-item') && courante) {
				courante.entrees.push(el);
			}
		});

		const CLE_REPLIS = 'registrar.menu.replis';
		let replis = {};
		try { replis = JSON.parse(localStorage.getItem(CLE_REPLIS) || '{}') || {}; } catch (e) { replis = {}; }

		/* Les entrees de chaque section passent dans un corps, que l on peut
		   deplier et replier en douceur (theme.css) ; le titre le pilote.
		   Rien ne bouge pendant cette mise en place. */
		sidebar.classList.add('menu-sans-anim');
		sections.forEach(function (section, n) {
			const corps = document.createElement('div');
			const contenu = document.createElement('div');
			corps.className = 'menu-section-corps';
			corps.id = 'menu-section-' + n;
			contenu.className = 'menu-section-contenu';
			corps.appendChild(contenu);
			section.entete.after(corps);
			section.entrees.forEach(function (e, i) {
				e.style.setProperty('--rang-section', i);
				contenu.appendChild(e);
			});
			section.entete.setAttribute('aria-controls', corps.id);
			section.corps = corps;
		});
		/* Ordre d arrivee des blocs quand le tiroir mobile s ouvre. */
		[sidebar.querySelector('.side-compte')]
			.concat(Array.prototype.slice.call(nav ? nav.children : []))
			.forEach(function (el, i) { if (el) { el.style.setProperty('--rang', i); } });

		function replier(section, replie) {
			section.entete.classList.toggle('est-replie', replie);
			section.entete.setAttribute('aria-expanded', replie ? 'false' : 'true');
			section.corps.classList.toggle('est-replie', replie);
		}

		/* Au clic, les entrees arrivent en cascade ; une section qui s ouvre
		   en bas du menu est amenee dans la vue une fois deroulee. */
		function basculer(section) {
			const replie = !section.entete.classList.contains('est-replie');
			const corps = section.corps;
			clearTimeout(corps.finMouvement);
			corps.classList.add('est-en-mouvement');
			replier(section, replie);
			corps.finMouvement = setTimeout(function () {
				corps.classList.remove('est-en-mouvement');
				if (replie || !nav) { return; }
				const bas = corps.getBoundingClientRect().bottom;
				const limite = nav.getBoundingClientRect().bottom;
				if (bas > limite) {
					nav.scrollBy({ top: bas - limite + 12, behavior: sansMouvement() ? 'auto' : 'smooth' });
				}
			}, 420);
			return replie;
		}

		sections.forEach(function (section) {
			const nom = section.entete.textContent.trim();
			const active = section.entrees.some(function (e) { return e.classList.contains('active'); });
			replier(section, !active && replis[nom] === true);
			section.entete.addEventListener('click', function () {
				const replie = basculer(section);
				replis[nom] = replie;
				try { localStorage.setItem(CLE_REPLIS, JSON.stringify(replis)); } catch (e) {}
			});
		});
		requestAnimationFrame(function () {
			requestAnimationFrame(function () { sidebar.classList.remove('menu-sans-anim'); });
		});
	});
</script>
<?php /* Les modales d ajout (enseignant, cours) sont chargees ICI, avec le
         menu qui porte leurs declencheurs. Elles n etaient auparavant incluses
         que par init/toolbar.php, present sur 9 pages seulement : sur les 25
         autres pages du menu, cliquer « Créer un professeur » ne faisait rien.
         require_once : toolbar.php l inclut aussi, sans dupliquer le balisage. */ ?>
<?php require_once(__DIR__ . '/../src/main/bigNotif.php'); ?>
