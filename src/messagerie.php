<!DOCTYPE html>
<html lang="fr">
<head>
	<?php /* La page a sa propre interface : pas de fenetres par-dessus (init/messagerie.dock.php). */
	define('MSG_PAGE_MESSAGERIE', true); ?>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Messagerie</title>
	<link rel="stylesheet" href="<?=$app_base?>/src/css/messagerie.css?v=<?=@filemtime(__DIR__ . '/css/messagerie.css')?>">
<?php
/* =============================================================================
   Messagerie instantanee : discussions a deux et en groupe, comme Messenger.

   Une seule page pour tous les comptes. Le cadre change : celui du personnel
   (barre du haut, menu) ou celui de l'espace etudiant (barre simple, rideau
   de transition). L'application elle-meme est dessinee par
   src/js/messagerie.js a partir de app/messagerie.api.php.

   Regles : data/messagerie.php. Acces : MessagerieController::index.
   ========================================================================== */
require_once(__DIR__ . '/../data/messagerie.php');

$msEtudiant = (int) ($_SESSION['user_level'] ?? 0) === (defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8)
	|| (function_exists('isStudent') && isStudent());
$msConfig = [
	'mode'     => 'page',
	'api'      => $app_base . '/app/messagerie.api.php',
	'fichier'  => $app_base . '/app/messagerie.fichier.php',
	'base'     => $app_base,
	'moi'      => (int) ($_SESSION['user_id'] ?? 0),
	'etudiant' => $msEtudiant,
	'morceau'  => MSG_MORCEAU,
];
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
</head>
<?php if ($msEtudiant): ?>
<body class="ms-page ms-page--etudiant">
	<header class="ms-barre-etudiant">
		<a class="ms-barre-etudiant__marque" href="<?=$e($app_base)?>/student/home">
			<img src="<?=$e($app_base)?>/file/UAZ Official.png" alt="">
			<span>Messagerie</span>
		</a>
		<nav aria-label="Espace étudiant">
			<a class="ms-barre-etudiant__icone" href="<?=$e($app_base)?>/student/home" title="Accueil" aria-label="Accueil"><i class="bi bi-house-door-fill" aria-hidden="true"></i></a>
			<a class="ms-barre-etudiant__icone" href="<?=$e($app_base)?>/logout" title="Déconnexion" aria-label="Déconnexion"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></a>
		</nav>
	</header>
	<main class="ms-cadre ms-cadre--etudiant">
<?php else: ?>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm ms-page">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back flex-1 min-h-0 ms-cadre">
<?php endif; ?>

	<div class="ms ms-racine" id="messagerie" data-vue="liste" aria-busy="true">

		<aside class="ms-liste" aria-labelledby="msTitre">
			<header class="ms-liste__tete">
				<h1 class="ms-titre" id="msTitre">Discussions</h1>
				<div class="ms-liste__actions">
					<?php /* Superadmin : message a plusieurs personnes, messages signales */ ?>
					<button type="button" class="ms-rond" data-action="admin" hidden aria-label="Administration de la messagerie" title="Administration">
						<i class="bi bi-three-dots" aria-hidden="true"></i><span class="ms-rond__pastille" data-moderation-nb hidden></span>
					</button>
					<button type="button" class="ms-rond" data-action="notifications" aria-label="Notifications du navigateur" title="Notifications du navigateur">
						<i class="bi bi-bell" aria-hidden="true"></i>
					</button>
					<button type="button" class="ms-rond" data-action="groupe" hidden aria-label="Nouveau groupe" title="Nouveau groupe">
						<i class="bi bi-people" aria-hidden="true"></i>
					</button>
					<button type="button" class="ms-rond ms-rond--accent" data-action="nouveau" aria-label="Nouveau message" title="Nouveau message">
						<i class="bi bi-pencil-square" aria-hidden="true"></i>
					</button>
				</div>
			</header>
			<label class="ms-recherche">
				<i class="bi bi-search" aria-hidden="true"></i>
				<span class="sr-only">Rechercher une discussion ou une personne</span>
				<input type="search" id="msRecherche" placeholder="Rechercher" autocomplete="off" spellcheck="false">
			</label>
			<div class="ms-liste__corps" id="msListe">
				<div class="ms-squelette" aria-hidden="true">
					<?php for ($i = 0; $i < 6; $i++): ?><div class="ms-squelette__ligne"><span></span><div><i></i><i></i></div></div><?php endfor; ?>
				</div>
			</div>
		</aside>

		<section class="ms-fil" id="msFil" aria-label="Discussion">
			<div class="ms-accueil">
				<div class="ms-accueil__sceau" aria-hidden="true"><i class="bi bi-chat-heart"></i></div>
				<h2>Vos messages</h2>
				<p>Écrivez à un collègue, un enseignant ou un étudiant&nbsp;: texte, message vocal, photo, vidéo ou document.</p>
				<button type="button" class="ms-bouton" data-action="nouveau"><i class="bi bi-pencil-square" aria-hidden="true"></i>Nouveau message</button>
			</div>
		</section>

		<aside class="ms-infos" id="msInfos" hidden aria-label="Informations sur la discussion"></aside>
	</div>

	<p class="sr-only" aria-live="polite" id="msAnnonce"></p>
	<script type="application/json" id="msConfig"><?= json_encode($msConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php if ($msEtudiant): ?>
	</main>
	<?php include('./student.transition.php'); ?>
<?php else: ?>
				</div>
				<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>
<?php endif; ?>
	<script src="<?=$app_base?>/src/js/messagerie.js?v=<?=@filemtime(__DIR__ . '/js/messagerie.js')?>" defer></script>
</body>
</html>
