<?php 
	$path = $_SERVER['PHP_SELF'];
	$page = basename($path);
	
	// MVC: Déterminer la route actuelle à partir de REQUEST_URI
	$_request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$current_route = $_request_uri;
	if ($app_base && strpos($current_route, $app_base) === 0) {
		$current_route = substr($current_route, strlen($app_base));
	}
	$current_route = '/' . ltrim($current_route ?: '', '/');
	if ($current_route !== '/') $current_route = rtrim($current_route, '/');

	// MVC: Mapper la route actuelle vers l'ancien nom de fichier PHP
	// Ceci permet à toutes les vérifications $page == "fichier.php" de fonctionner
	$_route_to_page = [
		'/tableau-de-bord'    => 'tableau-de-bord.php',
		'/didacticiel'        => 'didacticiel.php',
		'/etudiants'          => 'accueil.php',
		'/classrooms'         => 'accueil.classroom.php',
		'/professors'         => 'accueil.prof.php',
		'/courses'            => 'accueil.cours.php',
		'/schedule'           => 'accueil.emploi-temps.php',
		'/fusion-pdf'         => 'fusion-pdf.php',
		'/qr-generator'       => 'qr-generator.php',
		'/retouche-photo'     => 'retouche-photo.php',
		'/studio'             => 'studio.php',
		'/students/create'    => 'creat.student.php',
		'/deans-list'         => 'deans-list.php',
		'/top-students'       => 'meilleurs-etudiants.php',
		'/news'               => 'admin.actus.php',
		'/settings'           => 'settings.php',
		'/login'              => 'index.php',
		'/student/home'       => 'student.home.php',
		'/student/notes'  => 'student.dashboard.php',
		'/student/info'       => 'student.info.php',
		'/student/news'       => 'student.actus.php',
		'/student/quiz'       => 'student.quiz.php',
		'/student/game'       => 'student.game.php',
		'/student/transition' => 'student.transition.php',
		'/student'            => 'student.php',
		'/teacher/dashboard'  => 'teacher.dashboard.php',
		'/accounts/create'    => 'creat.account.php',
		'/login-locations'    => 'login-locations.php',
		'/my-account'         => 'my.account.php',
		'/finance'            => 'gestion_finance.php',
		'/finance/account-balance' => 'account_balance.php',
		'/professor'          => 'prof.php',
		'/course'             => 'cours.php',
		'/inscription'        => 'inscription.php',
		'/reel'               => 'reel.php',
		'/export'             => 'export.php',
		'/export/pdf'         => 'data.topdf.php',
		'/export/pdf-landscape' => 'data.topdf_paysage.php',
		'/export/xlsx'        => 'data.toxlsx.php',
	];
	if (isset($_route_to_page[$current_route])) {
		$page = $_route_to_page[$current_route];
	}
	// Normaliser les anciens chemins vers les nouvelles routes MVC
	$_route_aliases = [
		'/src/accueil' => '/etudiants',
		'/src/accueil.classroom' => '/classrooms',
		'/src/accueil.prof' => '/professors',
		'/src/accueil.cours' => '/courses',
		'/src/accueil.emploi-temps' => '/schedule',
		'/src/creat.student' => '/students/create',
		'/src/deans-list' => '/deans-list',
		'/src/meilleurs-etudiants' => '/top-students',
		'/src/admin.actus' => '/news',
		'/src/settings' => '/settings',
		'/src/index' => '/login',
		'/src/student.home' => '/student/home',
		'/src/student.dashboard' => '/student/notes',
		'/src/student.info' => '/student/info',
		'/src/student.actus' => '/student/news',
		'/src/student.quiz' => '/student/quiz',
		'/src/student.game' => '/student/game',
		'/src/student.transition' => '/student/transition',
		'/src/teacher.dashboard' => '/teacher/dashboard',
		'/src/creat.account' => '/accounts/create',
		'/src/login-locations' => '/login-locations',
		'/src/my.account' => '/my-account',
		'/src/gestion_finance' => '/finance',
		'/src/student' => '/student',
		'/src/prof' => '/professor',
		'/src/cours' => '/course',
		// Le menu ouvre le guichet par son ancien chemin (inscription/inscription.php).
		'/inscription/inscription' => '/inscription',
	];
	if (isset($_route_aliases[$current_route])) {
		$current_route = $_route_aliases[$current_route];
	}

	// Helper pour vérifier la route active
	if (!function_exists('isRoute')) {
		function isRoute($route) {
			global $current_route;
			if (is_array($route)) return in_array($current_route, $route);
			return $current_route === $route;
		}
	}

	// Utiliser le middleware pour récupérer l'utilisateur courant
	// Le middleware est déjà initialisé dans head.php
	$rg_user = currentUser();
	
	if ($rg_user) {
		// Variables pour compatibilité avec l'ancien code
		$infinit_pseudo = $rg_user['pseudo'];
		$infinit_password = $rg_user['password'];
		$rg_id = $rg_user['id'];
		$rg_name = $rg_user['nom'];
		$rg_last_name = $rg_user['prenom'];
		$rg_userId = $rg_user['id'];
		$rg_photos = $rg_user['photos'];
		$privilege = $rg_user['privilege'];

		/* ------------------------------------------------------------------
		   Avatar : une seule fois, pour les deux endroits qui l'affichent.

		   Le menu deroulant montrait une icone FIGEE (bi-person-circle) : il
		   n'a jamais ete relie a la photo du compte. Et l'avatar de la barre
		   emettait son <img> sans condition, si bien qu'un compte sans photo
		   — ou dont le fichier a disparu, ce qui arrive apres un changement de
		   nom — affichait une image cassee.

		   On verifie donc que le fichier EXISTE avant de l'annoncer, et l'on
		   retombe sinon sur les initiales, qui identifient le compte mieux
		   qu'une silhouette generique.

		   filemtime en parametre : le nouveau televersement nomme la photo
		   d'apres l'identifiant du compte (u27.jpg), le nom ne change donc
		   plus d'un envoi a l'autre et le navigateur servirait l'ancienne. */
		$rg_avatar_url = '';
		$rg_photo_nom = trim((string) ($rg_user['photos'] ?? ''));

		if ($rg_photo_nom !== '') {
			/* Certains comptes portent une URL absolue et non un nom de fichier :
			   la photo vient alors de leur connexion Google. Traitee comme un
			   chemin local, elle produisait « /app/photosuser/https://... »,
			   donc une image cassee. On la sert telle quelle.

			   https uniquement : une image en http sur une page en https serait
			   bloquee comme contenu mixte, et l'on n'accepte ainsi ni « data: »
			   ni « javascript: » venus de la base. */
			if (preg_match('#^https://#i', $rg_photo_nom)) {
				$rg_avatar_url = $rg_photo_nom;
			} else {
				$rg_photo_chemin = __DIR__ . '/../app/photosuser/' . $rg_photo_nom;
				if (is_file($rg_photo_chemin)) {
					$rg_avatar_url = $app_base . '/app/photosuser/' . encodeFilePath($rg_photo_nom)
						. '?v=' . @filemtime($rg_photo_chemin);
				}
			}
		}

		$rg_initiales = mb_strtoupper(
			mb_substr((string) ($rg_user['nom'] ?? ''), 0, 1)
			. mb_substr((string) ($rg_user['prenom'] ?? ''), 0, 1)
		);
		if (trim($rg_initiales) === '') {
			$rg_initiales = mb_strtoupper(mb_substr((string) ($rg_user['pseudo'] ?? '?'), 0, 2));
		}
		$rg_level = $rg_user['level'];
	} else {
		// Redirection vers la page de login MVC
		// Memorise la page demandee pour y revenir apres reconnexion.
		if (function_exists('memoriserUrlDemandee')) { memoriserUrlDemandee(); }
		header('Location: ' . $app_base . '/login');
		exit;
	}
 ?>

<?php /* Transitions de page (stinger). Charge ICI, au tout debut du <body>,
         et non dans le <head> : le script doit trouver document.body pour
         poser le rideau avant le premier rendu. Dans le <head>, la page
         s'afficherait d'abord et le rideau viendrait la recouvrir — un
         clignotement. La feuille de styles est chargee par init/head.php. */ ?>
<?php /* Version deduite de la date du fichier, et non ecrite a la main :
         une version figee laissait les navigateurs servir l'ancienne copie
         apres chaque correction du module. */ ?>
<script src="<?=$app_base?>/src/js/transitions.js?v=<?=@filemtime(__DIR__.'/../src/js/transitions.js')?>"></script>
<?php /* Expiration de session pendant un appel AJAX : affiche un message
         et renvoie vers la connexion, au lieu de laisser l interface figee. */ ?>
<script src="<?=$app_base?>/src/js/session.js?v=20260817-1"></script>

<style>
	/* Couleurs : src/css/theme.css (« Barre du haut »). Ici, le comportement. */

	/* Bascule de theme : le soleil et la lune se relaient. */
	.theme-toggle {
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;
		overflow: hidden;
		transition: background-color .15s ease, border-color .15s ease;
	}
	.theme-toggle .icon-sun,
	.theme-toggle .icon-moon {
		position: absolute;
		font-size: 17px;
		transition: opacity .25s ease, transform .3s ease;
	}
	.theme-toggle .icon-sun { color: #ffd04d; }
	[data-theme="dark"] .theme-toggle .icon-sun  { opacity: 1; transform: rotate(0) scale(1); }
	[data-theme="dark"] .theme-toggle .icon-moon { opacity: 0; transform: rotate(-90deg) scale(0); }
	[data-theme="light"] .theme-toggle .icon-sun  { opacity: 0; transform: rotate(90deg) scale(0); }
	[data-theme="light"] .theme-toggle .icon-moon { opacity: 1; transform: rotate(0) scale(1); }

	.user-avatar { transition: box-shadow .15s ease; }


	/* Bouton du menu : mobile seulement. */
	.topbar-hamburger {
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		cursor: pointer;
		transition: background-color .15s ease;
	}

	@media (max-width: 1023px) {
		.topbar-bleu-nuit {
			position: sticky !important;
			top: 0 !important;
			z-index: 1001 !important;
		}
		.topbar-hamburger { display: flex; }
	}

	@media (max-width: 640px) {
		.mobile-hidden { display: none !important; }
	}
</style>

<?php
	/* Le titre de la barre : la page ou l on se trouve, sans la chercher
	   dans le menu. Les routes absentes de la liste n affichent rien. */
	$topbarTitres = [
		'/tableau-de-bord'      => ['Accueil', 'Tableau de bord'],
		'/didacticiel'          => ['Aide', 'Didacticiel'],
		'/messagerie'           => ['Accueil', 'Messagerie'],
		'/etudiants'            => ['Étudiants', 'Liste des étudiants'],
		'/students/create'      => ['Étudiants', 'Créer un étudiant'],
		'/student'              => ['Étudiants', 'Fiche étudiant'],
		'/deans-list'           => ['Étudiants', "Dean's List"],
		'/top-students'         => ['Étudiants', 'Top Student'],
		'/session-rankings'     => ['Étudiants', 'Classement session'],
		'/courses'              => ['Cours', 'Liste des cours'],
		'/course'               => ['Cours', 'Fiche du cours'],
		'/cours'                => ['Cours', 'Fiche du cours'],
		'/courses-sessions'     => ['Cours', 'Cours par session'],
		'/professors'           => ['Professeurs', 'Liste des professeurs'],
		'/professor'            => ['Professeurs', 'Fiche enseignant'],
		'/remise-notes'         => ['Notes', 'Remises de notes'],
		'/presence'             => ['Notes', 'Assiduité'],
		'/inscription'          => ['Admissions', 'Inscription'],
		'/candidatures'         => ['Admissions', 'Candidatures'],
		'/schedule'             => ['Application', 'Emplois du temps'],
		'/evaluation'           => ['Application', 'Évaluation'],
		'/fusion-pdf'           => ['Application', 'Fusion de PDF'],
		'/qr-generator'         => ['Application', 'Générateur de QR Code'],
		'/retouche-photo'       => ['Application', 'Retouche photo'],
		'/studio'               => ['Application', 'Studio UAZ'],
		'/queue'                => ['Application', "File d'attente"],
		'/queue/plan'           => ['Application', 'Plan du bureau'],
		'/news'                 => ['Communication', 'Actualités'],
		'/newsletter'           => ['Communication', 'Newsletter'],
		'/settings/bulk-email'  => ['Communication', 'Boîte mail'],
		'/settings/adresses-google' => ['Communication', 'Adresses Google'],
		'/settings/logs'        => ['Paramètres', "Journal d'activité"],
		'/settings'             => ['Paramètres', 'Paramètres généraux'],
		'/finance'              => ['Paramètres', 'Gestion finance'],
		'/finance/account-balance' => ['Paramètres', 'Solde de compte'],
		'/finance/echeances'    => ['Paramètres', 'Échéances de paiement'],
		'/work-education'       => ['Étudiants', 'Work Education'],
		'/inscriptions/tardives' => ['Admissions', 'Inscriptions tardives'],
		'/finance/authorized-reinscription' => ['Étudiants', 'Réinscriptions'],
		'/sessions'             => ['Paramètres', 'Sessions'],
		'/login-locations'      => ['Paramètres', 'Localisations GPS'],
		'/accounts/create'      => ['Paramètres', 'Utilisateurs'],
		'/my-account'           => ['Compte', 'Mon compte'],
		'/reglement'            => ['Compte', 'Bulletin académique'],
		'/teacher/dashboard'    => ['Mon enseignement', 'Mes cours'],
		'/teacher/remise-notes' => ['Mon enseignement', 'Remise des notes'],
		'/teacher/presence'     => ['Mon enseignement', 'Présences'],
	];
	$topbarTitre = $topbarTitres[$current_route] ?? null;
	if ($current_route === '/settings/bulk-email' && (int)($_SESSION['user_level'] ?? 99) > 3) {
		$topbarTitre = ['Mon enseignement', 'E-mails']; // l'envoi des enseignants (src/bulk-email.php)
	}
	if ($topbarTitre === null && strpos($current_route, '/settings/') === 0) {
		$topbarTitre = ['Paramètres', 'Paramètres généraux'];
	}
?>
<div class="flex items-center justify-between w-full h-14 topbar-bleu-nuit px-3 lg:px-5">
	
	<!-- Logo Section (always visible) + Mobile hamburger -->
	<div class="flex items-center gap-2 lg:w-3/12 flex-shrink-0 min-w-0">
		<!-- Hamburger button - mobile only -->
		<button id="topbarMenuToggle" class="topbar-hamburger" aria-label="Ouvrir le menu" aria-controls="sidebarMenu">
			<i class="bi bi-list text-xl"></i>
		</button>
		<?php /* Mobile : la marque reste ici, la barre laterale etant repliee.
		         Bureau : elle est en tete de la barre laterale. */ ?>
		<a href="<?=$app_base?>/tableau-de-bord" class="topbar-logo hidden sm:flex items-center gap-2" aria-label="Infinit Registrar, accueil">
			<span class="w-8 h-8 rounded-lg p-1 flex-shrink-0" style="background:rgb(255 255 255 / .14)">
				<img src="<?=$app_base?>/file/logo-coldbloud.png" class="w-full h-full" alt="">
			</span>
			<span class="hidden sm:block font-semibold text-sm whitespace-nowrap">Infinit <b style="color:rgb(var(--c-gold-text))">Registrar</b></span>
		</a>
<?php if ($topbarTitre): ?>
		<div class="topbar-titre hidden lg:block"><small><?=htmlspecialchars($topbarTitre[0], ENT_QUOTES, 'UTF-8')?></small><?=htmlspecialchars($topbarTitre[1], ENT_QUOTES, 'UTF-8')?></div>
<?php endif; ?>
	</div>
	
	<!-- Search Section -->
	<div class="flex-1 px-2 lg:px-4 lg:w-7/12">
		<div class="search w-full flex justify-center">
			<?php
			/* Une seule recherche, partout : la recherche rapide, sur le modele de
			   laravel.com (src/js/recherche.js). Sur les listes et les fiches, le
			   bouton dit ce qu'on y cherche et la fenetre met ce groupe en tete ;
			   sur une liste, elle propose aussi de filtrer le tableau (src/js/listes.js).
			   Les cours et les enseignants restent reserves comme /courses et /professors. */
			$rcContexte = '';
			if ($page == "accueil.php" OR $page == "student.php") {
				$rcContexte = 'etudiants';
			} elseif (($page == "accueil.cours.php" OR $page == "cours.php") && hasAnyPrivilege(['superadmin', 'registrar'])) {
				$rcContexte = 'cours';
			} elseif (($page == "accueil.prof.php" OR $page == "prof.php") && hasAnyPrivilege(['superadmin', 'registrar'])) {
				$rcContexte = 'enseignants';
			}
			$rcLibelle = ['etudiants' => 'Rechercher un étudiant', 'cours' => 'Rechercher un cours', 'enseignants' => 'Rechercher un enseignant'][$rcContexte] ?? 'Rechercher';
			?>
			<button type="button" class="rc-declencheur" data-recherche-ouvrir aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K /" aria-label="<?=$rcLibelle?>">
				<i class="bi bi-search" aria-hidden="true"></i>
				<span class="rc-declencheur__texte"><?=$rcLibelle?>…</span>
				<span class="rc-touches" aria-hidden="true"><kbd data-rc-mod>Ctrl</kbd><kbd>K</kbd></span>
			</button>
		</div>
	</div>
	<?php
	/* Recherche rapide : ce qu'elle peut chercher. Les dossiers (etudiants,
	   cours, enseignants) restent fermes aux enseignants, comme leurs listes ;
	   app/recherche.rapide.php applique les memes droits. */
	$rcNiveau = (int) ($_SESSION['user_level'] ?? 99);
	$rcEnseignant = $rcNiveau > 3 && ($rcNiveau === (defined('ROLE_TEACHER') ? (int) ROLE_TEACHER : 7) || (function_exists('isTeacher') && isTeacher()));
	$rcEtudiant = $rcNiveau === (defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8) || (function_exists('isStudent') && isStudent());
	/* Le responsable du Work Education non plus : sa recherche ne trouve que
	   les pages de son menu. */
	$rcDossiers = !$rcEnseignant && !$rcEtudiant && !(function_exists('estWorkEducation') && estWorkEducation());
	?>
	<div id="rcConfig" hidden data-api="<?=$app_base?>/app/recherche.rapide.php" data-dossiers="<?= $rcDossiers ? '1' : '0' ?>" data-contexte="<?=$rcContexte?>"></div>
	<script src="<?=$app_base?>/src/js/recherche.js?v=<?=@filemtime(dirname(__DIR__) . '/src/js/recherche.js')?>" defer></script>
	<?php
	/* Didacticiel : les visites guidees du compte, avec les etapes de celles
	   de cette page (data/didacticiel.php, src/js/didacticiel.js). Les
	   etudiants n'en ont pas. */
	require_once dirname(__DIR__) . '/data/didacticiel.php';
	$dtConfig = didacticielConfig((string) $current_route, (string) $app_base);
	if ($dtConfig['visites']):
	?>
	<script type="application/json" id="didacticielConfig"><?= json_encode($dtConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
	<script src="<?=$app_base?>/src/js/didacticiel.js?v=<?=@filemtime(dirname(__DIR__) . '/src/js/didacticiel.js')?>" defer></script>
	<?php endif; ?>
	
	<!-- User Profile Section -->
	<div class="flex items-center gap-3 lg:w-3/12 justify-end flex-shrink-0">
		<?php /* Messagerie : bouton rond et fenetres en bas a droite, au-dessus de la page
		         (init/messagerie.dock.php). On y entre par la, plus par le menu. */
		require __DIR__ . '/messagerie.dock.php'; ?>
		<!-- Theme Toggle Button -->
		<button class="theme-toggle" id="themeToggle" title="Basculer le thème" aria-label="Basculer le thème clair ou sombre">
			<i class="bi bi-sun-fill icon-sun"></i>
			<i class="bi bi-moon-stars-fill icon-moon"></i>
		</button>
		
		<!-- User Dropdown -->
		<?php /* Au bureau, le compte est en tete de la barre laterale : on ne le
		         repete pas ici. Sur mobile, ce menu reste le seul acces. */ ?>
		<div class="relative dropdown-container lg:hidden" data-tuto="compte-mobile">
			<a href="#" class="flex items-center gap-2 hover:opacity-90 transition-all duration-300" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true" aria-label="Menu du compte utilisateur" role="button">
				<div class="user-avatar rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 w-10 h-10 p-0.5 shadow-lg ring-2 ring-transparent hover:ring-cyan-400/50 transition-all duration-300">
<?php if ($rg_avatar_url !== ''): ?>
					<img src="<?=htmlspecialchars($rg_avatar_url, ENT_QUOTES, "UTF-8")?>" class="rounded-full w-full h-full object-cover border-2 border-slate-800" alt="User Avatar">
<?php else: ?>
					<?php /* Initiales : elles disent de quel compte il s'agit, ce qu'une
					         image cassee ne fait pas. */ ?>
					<div class="rounded-full w-full h-full flex items-center justify-center border-2 border-slate-800 text-white font-bold text-sm" aria-label="Avatar">
						<?=htmlspecialchars($rg_initiales, ENT_QUOTES, 'UTF-8')?>
					</div>
<?php endif; ?>
				</div>
			</a>
			
			<!-- Dropdown Menu -->
			<ul class="dropdown-menu bg-slate-800/95 backdrop-blur-xl border border-slate-700/50 text-slate-100 p-2 rounded-xl shadow-2xl text-sm overflow-auto" 
			    style="max-height:500px; min-width: 260px; position: absolute; right: 0; margin-top: 12px; backdrop-filter: blur(12px);">
		        
		        <!-- User Info Header with gradient background -->
		        <li class="compte-entete px-4 py-3 rounded-lg mb-2">
		        	<div class="flex items-center gap-3">
		        		<?php /* La photo du compte, et non plus l'icone figee qui se
		        		         trouvait ici : c'est la meme personne qu'a deux
		        		         centimetres au-dessus, elle doit avoir le meme visage. */ ?>
		        		<div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg overflow-hidden shrink-0">
<?php if ($rg_avatar_url !== ''): ?>
		        			<img src="<?=htmlspecialchars($rg_avatar_url, ENT_QUOTES, "UTF-8")?>" class="w-full h-full object-cover" alt="">
<?php else: ?>
		        			<span class="text-white font-bold text-sm"><?=htmlspecialchars($rg_initiales, ENT_QUOTES, 'UTF-8')?></span>
<?php endif; ?>
		        		</div>
		        		<div class="flex-1 min-w-0">
		        			<div class="font-semibold truncate"><?=$rg_name?> <?=$rg_last_name?></div>
		        			<div class="compte-role text-xs font-medium uppercase tracking-wide"><?=$rg_user['privilege'];?></div>
		        		</div>
		        	</div>
		        </li>
		        
<?php /* Une personne du personnel qui est aussi enseignante (ou l'inverse) passe d'un espace a l'autre. */ ?>
<?php if (function_exists('aDeuxEspaces') && aDeuxEspaces()): ?>
		        <li class="dropdown-item-hover mb-1">
		        	<a href="<?=$app_base?>/choisir-espace?depuis=menu" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition-colors">
		        			<i class="bi-arrow-left-right text-amber-400"></i>
		        		</div>
		        		<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Changer d'espace</span>
		        	</a>
		        </li>
<?php endif; ?>

		        <!-- My Account -->
		        <li class="dropdown-item-hover mb-1">
		        	<a href="<?=$app_base?>/my-account" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
		        			<i class="bi-gear text-cyan-400 group-hover:rotate-90 transition-transform duration-300"></i>
		        		</div>
		        		<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Mon compte</span>
		        	</a>
		        </li>

		        <!-- Reglement des etudes : consultable par tous les utilisateurs -->
		        <li class="dropdown-item-hover mb-1">
		        	<a href="<?=$app_base?>/reglement" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
		        			<i class="bi-journal-text text-cyan-400"></i>
		        		</div>
		        		<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Bulletin académique</span>
		        	</a>
		        </li>

		        <!-- Didacticiel : les visites guidees (src/didacticiel.php) -->
		        <li class="dropdown-item-hover mb-1">
		        	<a href="<?=$app_base?>/didacticiel" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
		        			<i class="bi-signpost-split text-cyan-400"></i>
		        		</div>
		        		<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Didacticiel</span>
		        	</a>
		        </li>

		        <!-- Installer l'application : visible seulement quand le navigateur
		             le permet, et plus du tout une fois installee (src/js/pwa.js) -->
		        <li class="dropdown-item-hover mb-1" data-pwa-installer hidden>
		        	<a href="#" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
		        			<i class="bi-download text-cyan-400"></i>
		        		</div>
		        		<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Installer l'application</span>
		        	</a>
		        </li>

				<?php /* Les sections « Outils Dev » et « Applications » ont ete retirees
				         de ce menu. Elles proposaient phpMyAdmin, la connexion WordPress,
				         le lanceur de code et les applications voisines — a tout compte de
				         niveau 3 ou moins, donc aux six registraires autant qu'aux
				         developpeurs. Ce menu est celui du compte : il n'a pas a etre la
				         porte d'entree de l'outillage. */ ?>
		        
		        <!-- Final Divider -->
		        <li class="my-2">
					<div class="h-px bg-gradient-to-r from-transparent via-slate-600 to-transparent"></div>
				</li>
		        
		        <!-- Logout with special styling -->
		        <li class="dropdown-item-hover">
		        	<a href="#" class="logOut flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-gradient-to-r hover:from-red-600 hover:to-red-700 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center group-hover:bg-red-500/20 transition-colors">
			        		<i class="bi-door-open-fill text-red-400 group-hover:text-white transition-colors"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 font-medium group-hover:text-white">Se déconnecter</span>
			        	<i class="bi-arrow-right text-sm opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-200"></i>
			        </a>
		        </li>
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
	// ===== THEME TOGGLE FUNCTIONALITY =====
	(function() {
		const themeToggle = document.getElementById('themeToggle');
		const html = document.documentElement;
		
		// Get saved theme or default to dark
		const savedTheme = localStorage.getItem('theme') || 'dark';
		html.setAttribute('data-theme', savedTheme);
		
		// Toggle theme on click
		if (themeToggle) {
			themeToggle.addEventListener('click', function() {
				const currentTheme = html.getAttribute('data-theme');
				const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
				
				html.setAttribute('data-theme', newTheme);
				localStorage.setItem('theme', newTheme);
				
				// Show toast notification
				if (typeof Toast !== 'undefined') {
					const themeName = newTheme === 'dark' ? 'Mode sombre' : 'Mode clair';
					Toast.info('Thème changé : ' + themeName, { title: 'Apparence', duration: 2000 });
				}
				
				// Dispatch event for other components to react
				window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: newTheme } }));
			});
		}
	})();

	$(document).ready(function() {
		// Logout with confirmation
		$('.logOut').click(function(e) {
			e.preventDefault();
			
			if (typeof Toast !== 'undefined') {
				Toast.confirm("Êtes-vous sûr de vouloir vous déconnecter ?", {
					title: "Déconnexion",
					confirmText: "Se déconnecter",
					cancelText: "Annuler"
				}).then(function(confirmed) {
					if (confirmed) {
						const loadingId = Toast.loading("Déconnexion en cours...");
						setTimeout(function() {
							window.location.href = APP_BASE+'/logout';
						}, 500);
					}
				});
			} else {
				// Sans la modale (init/toast.php), on se deconnecte sans boite native.
				window.location.href = APP_BASE+'/logout';
			}
		});

		/* La recherche : src/js/recherche.js (et src/js/listes.js pour le tableau des listes). */
	});

</script>
