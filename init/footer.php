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
<div class="sm:w-full lg:w-10/12 h-6 footer-bleu-nuit mt-1 py-0.5 px-2 absolute bottom-0 flex">
	<div class="w-3/12 px-4" id="<?php
	/*if ($page == "accueil.php" OR $page =="student.php") {echo 'compterStd';}
	elseif ($page == "accueil.cours.php" OR $page =="cours.php") {echo 'compterCours';}
	elseif ($page == "accueil.prof.php" OR $page =="prof.php") {echo 'compterProf';}*/
	?>">
		
	</div>
	<div class="w-3/12 px-4">
		
	</div>
	<div class="w-3/12 px-4">
		
	</div>
	<div class="w-3/12 px-4 text-right">
		<p id="content">Chargement...</p>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		
		var	sdt_nb = <?=$sdt_nb-1?>;		
		
		$('#compterStd').text('Affichage limité à '+sdt_nb+' étudiants.');

		$('.triage').click(function() {
			
			var sdt_nbLivesearch = <?=$sdt_nbLivesearch-1?>;

			$.ajax({
				url:"#",
				method:"POST",
				data:{sdt_nbLivesearch:sdt_nbLivesearch},

				success:function(data){

					$('#compterStd').text('Résultat : '+data+' étudiants.');
				}
			});

		});

		$('#std-search').keyup(function(){
			
			var sdt_nbLivesearch = <?=$sdt_nbLivesearch-1?>;

			$.ajax({
				url:"#",
				method:"POST",
				data:{sdt_nbLivesearch:sdt_nbLivesearch},

				success:function(data){
					
					$('.compterStd').text('Résultat : '+data+' étudiants.');
				}
			});
		});

		$('.filter').click(function() {
			var sdt_nbLive = <?=$sdt_nbLive-1?>;

			$.ajax({
				url:"#",
				method:"POST",
				data:{sdt_nbLive:sdt_nbLive},

				success:function(data){
					
					$('.compterStd').text('Résultat : '+data+' étudiants.');
				}
			});
		});
		
	});
</script>