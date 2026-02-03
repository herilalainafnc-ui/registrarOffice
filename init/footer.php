<style>
	.footer-bleu-nuit {
		background: #0a1628;
		border-top: 1px solid #1a3a5c;
		color: #8eb8d4;
		font-size: 11px;
		transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
	}
	
	[data-theme="light"] .footer-bleu-nuit {
		background: #f0f7fc;
		border-top-color: #8eb8d4;
		color: #1a3a5c;
	}
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

<script type="text/javascript">
	$(document).ready(function() {
		// Update footer info based on current page
		<?php if(isset($sdt_nb)): ?>
		var sdt_nb = <?=$sdt_nb - 1?>;
		$('#footerInfo').text('Affichage limité à ' + sdt_nb + ' étudiants.');
		<?php endif; ?>

		<?php if(isset($cours_nb)): ?>
		var cours_nb = <?=$cours_nb - 1?>;
		$('#footerInfo').text('Affichage : ' + cours_nb + ' cours.');
		<?php endif; ?>

		<?php if(isset($prof_nb)): ?>
		var prof_nb = <?=$prof_nb - 1?>;
		$('#footerInfo').text('Affichage : ' + prof_nb + ' professeurs.');
		<?php endif; ?>
	});
</script>