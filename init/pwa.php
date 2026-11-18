<?php
/* Application installable (PWA) : ce que le telephone lit pour proposer
   « Installer » / « Sur l'ecran d'accueil ». A inclure dans chaque <head>
   d'une page de l'application (init/head.php et les pages qui ont leur
   propre en-tete : connexion, chargement, espace etudiant). Le manifeste et
   la page d'ouverture : controllers/ApplicationController.php ; le service
   worker : /sw.js ; le bouton « Installer l'application » : src/js/pwa.js. */
if (!defined('PWA_POSE')) {
    define('PWA_POSE', true);
    $_pwa_base = htmlspecialchars(defined('APP_BASE') ? APP_BASE : ($app_base ?? ''), ENT_QUOTES, 'UTF-8');
    $_pwa_js = dirname(__DIR__) . '/src/js/pwa.js';
    $_pwa_hl = dirname(__DIR__) . '/src/js/hors-ligne.js';
    $_pwa_util = (int) ($_SESSION['user_id'] ?? 0);
?>
	<link rel="manifest" href="<?= $_pwa_base ?>/manifest.webmanifest">
	<meta name="theme-color" content="#222e65">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="Registrar">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	<link rel="apple-touch-icon" href="<?= $_pwa_base ?>/src/pwa/apple-touch-icon.png">
	<script src="<?= $_pwa_base ?>/src/js/pwa.js?v=<?= @filemtime($_pwa_js) ?>" data-base="<?= $_pwa_base ?>" defer></script>
<?php if ($_pwa_util > 0): /* connecte : le mode hors ligne (file des changements, pastille d'etat) */ ?>
	<meta name="registrar-utilisateur" content="<?= $_pwa_util ?>">
	<script src="<?= $_pwa_base ?>/src/js/hors-ligne.js?v=<?= @filemtime($_pwa_hl) ?>" data-base="<?= $_pwa_base ?>" defer></script>
<?php endif; ?>
<?php
    unset($_pwa_base, $_pwa_js, $_pwa_hl, $_pwa_util);
}
