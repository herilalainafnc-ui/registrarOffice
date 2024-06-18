<div class="w-full h-23 bg-slate-400 py-1 flex shadow-md">
<!-- BRANCHE D'OUTILS 1 -->	
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" id="exportListStd" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-filetype-pdf text-2xl"></i><br>
						Exporter la liste
				</center>
			
		</a>
		<a target="_blank" href="./data.topdf.php?ptype=Statistique" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-list-columns text-2xl"></i><br>
						Statistique
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-file-text text-2xl"></i><br>
						List pour la FOP
				</center>
			
		</a>
	</div>

<!-- BRANCHE D'OUTILS 2 -->	
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-envelope-at text-2xl"></i><br>
						Tamplate mail CSV
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-ticket-perforated-fill text-2xl"></i><br>
						Tickets mail
				</center>
			
		</a>
		
		
	</div>

<!-- BRANCHE D'OUTILS 3 -->		
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-person-lines-fill text-2xl"></i><br>
						Worked Lists
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-card-checklist text-2xl"></i><br>
						Worked Slip
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 4 -->		
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-sort-alpha-up-alt text-2xl"></i><br>
						Trier par
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-funnel text-2xl"></i><br>
						Filter l'affichage
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 searchTool">
				<center>
				<i class="bi-search text-2xl"></i><br>
						Rechercher
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 5 -->		
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-highlighter text-2xl"></i><br>
						Election SA
				</center>
			
		</a>
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1">
				<center>
				<i class="bi-download text-2xl"></i><br>
						Photos en masse
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 toolInactive">
				<center>
				<i class="bi-gear-fill text-2xl"></i><br>
						Paramètres Généraux
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 6 -->		
	<div class="w-2/12 flex px-1 lg:hidden">
		
		

	</div>
</div>

<!-- NOTIFICATION MANAGER --><?php require('./student/notificationGeneral.php');?>

<script type="text/javascript">
	$(document).ready(function() {
		$('.searchTool').click(function() {
			$('.searchStudent').css({'display':'block'});
			$('#std-search').focus();

		});
	});
</script>