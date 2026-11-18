<style>
	/* Couleurs : src/css/theme.css (« Pied »). */
	.footer-bleu-nuit {
		font-size: 11.5px;
		white-space: nowrap;
		transition: background-color .2s ease, border-color .2s ease, color .2s ease;
	}
	.footer-bleu-nuit > div { overflow: hidden; text-overflow: ellipsis; }
</style>
<div class="w-full h-6 footer-bleu-nuit py-0.5 px-2 flex">
	<div class="w-3/12 px-4" id="footerInfo">
		<!-- Dynamic footer info -->
	</div>
	<div class="w-3/12 px-4">
		
	</div>
	<div class="w-3/12 px-4">
		
	</div>
	<div class="w-3/12 px-4 text-right">
		<p id="content">Chargement...</p>
	</div>
</div>

<!-- Include Toast Notification System -->
<?php require('../init/toast.php'); ?>

<!-- Include Keyboard Shortcuts -->
<?php require('../init/shortcuts.php'); ?>

<!-- Include Page Loader -->
<?php require('../init/loader.php'); ?>

<?php /* Navigation sans rechargement dans les pages qui declarent une zone
         (data-zone-ajax) ; sans zone, le script ne fait rien. */ ?>
<script src="<?= htmlspecialchars(isset($app_base) ? $app_base : '', ENT_QUOTES, 'UTF-8') ?>/src/js/zone-ajax.js?v=<?= @filemtime(__DIR__ . '/../src/js/zone-ajax.js') ?>" defer></script>

<script type="text/javascript">
	$(document).ready(function() {
		// Update footer info based on current page
		// Type d'élément listé (pour le compteur "en direct" mis à jour par head.php)
		<?php if(isset($sdt_nb)): ?>
		window.__listNoun = 'étudiants';
		<?php elseif(isset($cours_nb)): ?>
		window.__listNoun = 'cours';
		<?php elseif(isset($prof_nb)): ?>
		window.__listNoun = 'professeurs';
		<?php endif; ?>
		if (window.__updateListCount) { window.__updateListCount(); }
	});
</script>