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
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-file-text text-2xl"></i><br>
						List pour la FOP
				</center>
			
		</a>
	</div>

<!-- BRANCHE D'OUTILS 2 -->	
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-envelope-at text-2xl"></i><br>
						Tamplate mail CSV
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-ticket-perforated-fill text-2xl"></i><br>
						Tickets mail
				</center>
			
		</a>
		
		
	</div>

<!-- BRANCHE D'OUTILS 3 -->		
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-person-lines-fill text-2xl"></i><br>
						Worked Lists
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-card-checklist text-2xl"></i><br>
						Worked Slip
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 4 -->		
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "student.php" OR $page == "cours.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-sort-alpha-up-alt text-2xl"></i><br>
						Trier par
				</center>
			
		</a>
		<ul class="dropdown-menu border bg-slate-300 text-black p-0 rounded-0 text-xs">

					<li><a href="#?trie=student_id" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">ID</p>
					</a></li>
					<li><a href="#?trie=student_nom" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Nom</p>
					</a></li>
					<li><a href="#?trie=student_prenom" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Préom</p>
					</a></li>
					<li><a href="#?trie=etude_envisage" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Mention</p>
					</a></li>
					<li><a href="#?trie=etude_option" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Parcours</p>
					</a></li>
					<li><a href="#?trie=annee_scolaire" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Année</p>
					</a></li>
					<li><a href="#?trie=annee_etude" class="triage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Niveau</p>
					</a></li>
		</ul>


		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "student.php" OR $page == "cours.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-funnel text-2xl"></i><br>
						Filtrage
				</center>
			
		</a>

		<ul class="dropdown-menu border bg-slate-300 text-black p-0 rounded-0 text-xs overflow-auto" style="max-height:400px">
					<b class="toolInactive">Mention</b>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>						
					<li><a href="#?filterFiliere=<?=$showSignMention['filiere_description']?>"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><?=$showSignMention['filiere_description']?></p>
					</a></li>
 <?php 
}
 ?>
					<b class="toolInactive">Année</b>
<?php
$y = date('Y');
for ($i=0; $i <= 8; $i++) { 
	
$as = $y." - ".($y+1);
?>
					<li><a href="#?filterYear=<?=$as?>"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><?=$as?></p>
					</a></li>
<?php
$y = $y - 1;
}
 ?>					
 					<b class="toolInactive">Année</b>
					<li><a href="#?filterLevel=1"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 1</p>
					</a></li>
					<li><a href="#?filterLevel=2"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 2</p>
					</a></li>
					<li><a href="#?filterLevel=3"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 3</p>
					</a></li>
		</ul>

		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 searchTool">
				<center>
				<i class="bi-search text-2xl"></i><br>
						Rechercher
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 5 -->		
	<div class="w-2/12 border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-highlighter text-2xl"></i><br>
						Election SA
				</center>
			
		</a>
		
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-download text-2xl"></i><br>
						Photos en masse
				</center>
			
		</a>
		<a href="#" class="text-xs w-4/12 hover:bg-slate-300 active:bg-cyan-700 p-1 toolInactive">
				<center>
				<i class="bi-gear-fill text-2xl"></i><br>
						Paramètres
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
			$('.search').css({'display':'block'});
			$('#std-search').focus();
			$('#cours-search').focus();
			$('#prof-search').focus();

		});
		
		$('.triage').click(function(){
			
			var hrefValue = $(this).attr('href');
		    var trie = hrefValue.split('trie=')[1];
		    
		    $('#stdTriage-result').css({'display':'block'});
		    $('#stdSearch-result').css({'display':'none'});
			$('#all-std').css({'display':'none'});

		    $.ajax({
				url:"../init/std-live.php",
				method:"POST",
				data:{trie:trie},

				success:function(data){
					$("#stdTriage-result").html(data);
				}
			});
		});

		
		    




	});
</script>