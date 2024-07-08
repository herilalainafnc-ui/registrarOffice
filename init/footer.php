<div class="w-10/12 h-6 <?=$bg_four_color?> mt-1 py-0.5 px-2 absolute bottom-0 flex">
	<div class="w-3/12 px-4 <?php
	if ($page == "accueil.php" OR $page =="student.php") {echo 'compterStd';}
	elseif ($page == "accueil.cours.php" OR $page =="cours.php") {echo 'compterCours';}
	elseif ($page == "accueil.prof.php" OR $page =="prof.php") {echo 'compterProf';}
	?>">
		
	</div>
	<div class="w-3/12 px-4">
		
	</div>
	<div class="w-3/12 px-4">
		
	</div>
	<div class="w-3/12 px-4 text-right">
		<p>Aujourd'hui <?= date('d/m/Y')?></p>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		var	sdt_nb = <?=$sdt_nb-1?>;		
		
		$('.compterStd').text('Affichage limité à '+sdt_nb+' étudiants.');

		$('.triage').click(function() {
			
			var sdt_nbLivesearch = <?=$sdt_nbLivesearch-1?>;

			$.ajax({
				url:"./",
				method:"POST",
				data:{sdt_nbLivesearch:sdt_nbLivesearch},

				success:function(data){

					$('.compterStd').text('Résultat : '+data+' étudiants.');
				}
			});

		});

		$('#std-search').keyup(function(){
			
			var sdt_nbLivesearch = <?=$sdt_nbLivesearch-1?>;

			$.ajax({
				url:"./",
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
				url:"./",
				method:"POST",
				data:{sdt_nbLive:sdt_nbLive},

				success:function(data){
					
					$('.compterStd').text('Résultat : '+data+' étudiants.');
				}
			});
		});
		
	});
</script>