<div class="w-full h-23 <?=$txt_one_color?> py-1 flex shadow-md">
<!-- BRANCHE D'OUTILS 1 -->	
	<div class="sm:w-3/12 lg:w-2/12 lg:border-r flex px-1">
		
		<a href="#" id="<?php 
if($page == "accueil.php" OR $page == "student.php" OR $page=="inscription.php") {
 	echo "exportListStd";
}elseif($page == "accueil.cours.php" OR $page == "cours.php") {
	echo "exportListCours";
}elseif($page == "accueil.prof.php" OR $page == "prof.php") {
	echo "exportListProf";
} ?>" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1">
				<center>
				<i class="bi-filetype-pdf text-2xl"></i><br>
						Exporter la liste
				</center>
			
		</a>
		<a id="exportStatistic" href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1">
				<center>
				<i class="bi-list-columns text-2xl"></i><br>
						Statistique
				</center>
			
		</a>
		<a href="#" id="exportListFOP" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
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
	<div class="sm:w-3/12 lg:w-2/12 lg:border-r flex px-1">

		<a href="#" id="exportMesupres" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-bar-chart-steps text-2xl"></i><br>
						Mesupres
				</center>
			
		</a>
		
		<a href="#" id="exportListCSV" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-envelope-at text-2xl"></i><br>
						Tamplate mail CSV
				</center>
			
		</a>
		<a href="#" id="exportTicketMail" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
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
	<div class="sm:w-3/12 lg:w-2/12 lg:border-r flex px-1">
		
		<a href="#" id="worked" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-person-lines-fill text-2xl"></i><br>
						Worked
				</center>
			
		</a>
		<a href="#" id="workedSlip" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-card-checklist text-2xl"></i><br>
						Worked Slip
				</center>
			
		</a>
		<a target='_blank' href="../inscription/inscription.php" id="workedSlip" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-person-fill-add text-2xl"></i><br>
						Inscription
				</center>
			
		</a>
	</div>

<!-- BRANCHE D'OUTILS 4 -->		
	<div class="sm:w-3/12 lg:w-2/12 lg:border-r flex px-1">
		
		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "student.php" OR $page == "cours.php" OR $page == "prof.php" OR $page == "inscription.php") {
	echo "toolInactive";
} ?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-sort-alpha-up-alt text-2xl"></i><br>
						Trier par
				</center>
			
		</a>
		<ul class="dropdown-menu border <?=$bg_six_color?> text-black p-0 rounded-0 text-xs" style="max-height:400px; min-width: 200px;">
<?php if ($page == "accueil.php") { ?>
					<li><a href="#?trie=student_id" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">ID</p>
					</a></li>
					<li><a href="#?trie=student_nom" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Nom</p>
					</a></li>
					<li><a href="#?trie=student_prenom" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Préom</p>
					</a></li>
					<li><a href="#?trie=etude_envisage" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Mention</p>
					</a></li>
					<li><a href="#?trie=etude_option" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Parcours</p>
					</a></li>
					<li><a href="#?trie=annee_scolaire" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Année</p>
					</a></li>
					<li><a href="#?trie=annee_etude" class="stdTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Niveau</p>
					</a></li>
<?php }elseif($page == "accueil.cours.php") { ?>
					<li><a href="#?trie=Sigle" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Sigle</p>
					</a></li>
					<li><a href="#?trie=title" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Cours</p>
					</a></li>
					<li><a href="#?trie=dep_desc" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Mention</p>
					</a></li>
					<li><a href="#?trie=nb_crd" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Crédit</p>
					</a></li>
					<li><a href="#?trie=category" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Catégorie</p>
					</a></li>
					<li><a href="#?trie=yearlevel" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Niveau</p>
					</a></li>
					<li><a href="#?trie=semester" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Semestre</p>
					</a></li>
					<li><a href="#?trie=id_teacher" class="coursTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Enseignant</p>
					</a></li>
<?php }elseif($page == "accueil.prof.php") { ?>
					<li><a href="#?trie=teacher_id" class="profTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">ID</p>
					</a></li>
					<li><a href="#?trie=name" class="profTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Nom</p>
					</a></li>
					<li><a href="#?trie=lastName" class="profTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Préom</p>
					</a></li>
					<li><a href="#?trie=address" class="profTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Adresse</p>
					</a></li>
					<li><a href="#?trie=phone" class="profTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Téléphone</p>
					</a></li>
					<li><a href="#?trie=email" class="profTriage"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Email</p>
					</a></li>
<?php } ?>
		</ul>


		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "student.php" OR $page == "cours.php" OR $page == "prof.php" OR $page == "inscription.php") {
	echo "toolInactive";
} ?>" data-bs-toggle="dropdown" aria-expanded="false">
				<center>
				<i class="bi-funnel text-2xl"></i><br>
						Filtrage
				</center>
			
		</a>

		<ul class="dropdown-menu border <?=$bg_six_color?> text-black p-0 rounded-0 text-xs overflow-auto" style="max-height:400px; min-width: 200px;">

<?php if ($page == "accueil.php") { ?>
					<b class="text-grey bg-cyan-400 px-2">Mention</b>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>						
					<li><a href="#?filter=etude_envisage&channel=<?=$showSignMention['filiere_description']?>" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><?=$showSignMention['filiere_description']?></p>
					</a></li>
 <?php 
}
 ?>
					<b class="text-grey bg-cyan-400 px-2">Année</b>
<?php
$y = date('Y');
for ($i=0; $i <= 4; $i++) { 
	
$as = $y." - ".($y+1);
?>
					<li><a href="#?filter=annee_scolaire&channel=<?=$as?>" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><?=$as?></p>
					</a></li>
<?php
$y = $y - 1;
}
 ?>					
 					<b class="text-grey bg-cyan-400 px-2">Niveau</b>
					<li><a href="#?filter=annee_etude&channel=1" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 1</p>
					</a></li>
					<li><a href="#?filter=annee_etude&channel=2" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 2</p>
					</a></li>
					<li><a href="#?filter=annee_etude&channel=3" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 3</p>
					</a></li>
					<li><a href="#?filter=annee_etude&channel=4" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Master 1</p>
					</a></li>
					<li><a href="#?filter=annee_etude&channel=5" class="stdFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Master 2</p>
					</a></li>

<?php }elseif($page == "accueil.cours.php") { ?>

					<b class="text-grey bg-cyan-400 px-2">Mention</b>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>						
					<li><a href="#?filter=dep_desc&channel=<?=$showSignMention['filiere_sigle']?>" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white"><?=$showSignMention['filiere_description']?></p>
					</a></li>
 <?php 
}
 ?>
 					<b class="text-grey bg-cyan-400 px-2">Catégorie</b>
					<li><a href="#?filter=category&channel=0" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Général</p>
					</a></li>
					<li><a href="#?filter=category&channel=1" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Mageur</p>
					</a></li>
					<li><a href="#?filter=category&channel=2" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Selective</p>
					</a></li>
					<li><a href="#?filter=category&channel=3" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Additionnel</p>
					</a></li>


 					<b class="text-grey bg-cyan-400 px-2">Niveau</b>
					<li><a href="#?filter=yearlevel&channel=1" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 1</p>
					</a></li>
					<li><a href="#?filter=yearlevel&channel=2" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 2</p>
					</a></li>
					<li><a href="#?filter=yearlevel&channel=3" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Licence 3</p>
					</a></li>
					<li><a href="#?filter=yearlevel&channel=4" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Master 1</p>
					</a></li>
					<li><a href="#?filter=yearlevel&channel=5" class="coursFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Master 2</p>
					</a></li>

<?php }elseif($page == "accueil.prof.php") { ?>		
					
					<b class="text-grey bg-cyan-400 px-2">Adresse</b>
 					
					<li><a href="#?filter=address&channel=Campus UAZ" class="profFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Campus UAZ</p>
					</a></li>
					<li><a href="#?filter=address&channel=" class="profFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Autre</p>
					</a></li>
					<b class="text-grey bg-cyan-400 px-2">Réligion</b>
 					
					<li><a href="#?filter=religion&channel=adventiste" class="profFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Adventiste</p>
					</a></li>
					<li><a href="#?filter=religion&channel=non adventiste" class="profFilter"><p class="px-2 py-1 hover:bg-cyan-700 hover:text-white">Non Adventiste</p>
					</a></li>	
<?php } ?>
		</ul>

		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 searchTool">
				<center>
				<i class="bi-search text-2xl"></i><br>
						Rechercher
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 5 -->		
	<div class="sm:w-3/12 lg:w-2/12 lg:border-r flex px-1">
		
		<a href="#" id="badgeGr" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?>">
				<center>
				<i class="bi-person-badge-fill text-2xl"></i><br>
						Badge
				</center>
			
		</a>

		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?> toolInactive">
				<center>
				<i class="bi-highlighter text-2xl"></i><br>
						Election SA
				</center>
			
		</a>
		
		<a href="#" class="text-xs w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
	echo "toolInactive";
} ?> toolInactive">
				<center>
				<i class="bi-download text-2xl"></i><br>
						Photos en masse
				</center>
			
		</a>

	</div>

<!-- BRANCHE D'OUTILS 6 -->		
	<div class="hidden block w-2/12 lg:flex px-1">
		<a href="./settings.php" class="text-xs lg:w-4/12 hover:<?=$bg_six_color?> active:bg-cyan-700 p-1">
				<center>
				<i class="bi-gear-fill text-2xl"></i><br>
						Paramètres
				</center>
			
		</a>
		

	</div>
</div>

<!-- NOTIFICATION MANAGER --><?php require('./main/notificationGeneral.php');?>


<!-- BIG NOTIF MANAGER --><?php require('./main/bigNotif.php');?>

<script type="text/javascript">

	$(document).ready(function() {
		$('.searchTool').click(function() {
			$('.search').css({'display':'block'});
			$('#std-search').focus();
			$('#cours-search').focus();
			$('#prof-search').focus();

		});
/*:::::::::::::::::::::::::::::: TRIAGE STD ::::::::::::::::::::::::::::::::*/		
		$('.stdTriage').click(function(){

			var hrefValue = $(this).attr('href');
		    var trie = hrefValue.split('trie=')[1];
		    
		    $('#stdTriage-result').css({'display':'block'});
		    $('#stdSearch-result').css({'display':'none'});
			$('#all-std').css({'display':'none'});

		    $.ajax({
				url:"../init/.student/std-live.php",
				method:"POST",
				data:{trie:trie},

				success:function(data){
					$("#stdTriage-result").html(data);
				}
			});
		});
/*:::::::::::::::::::::::::::::: TRIAGE COURS ::::::::::::::::::::::::::::::::*/		
		$('.coursTriage').click(function(){

			var hrefValue = $(this).attr('href');
		    var trie = hrefValue.split('trie=')[1];
		    
		    $('#coursTriage-result').css({'display':'block'});
		    $('#coursSearch-result').css({'display':'none'});
			$('#all-cours').css({'display':'none'});

		    $.ajax({
				url:"../init/.cours/cours-live.php",
				method:"POST",
				data:{trie:trie},

				success:function(data){
					$("#coursTriage-result").html(data);
				}
			});
		});

/*:::::::::::::::::::::::::::::: TRIAGE PROF ::::::::::::::::::::::::::::::::*/		
		$('.profTriage').click(function(){

			var hrefValue = $(this).attr('href');
		    var trie = hrefValue.split('trie=')[1];
		    
		    $('#profTriage-result').css({'display':'block'});
		    $('#profSearch-result').css({'display':'none'});
			$('#all-prof').css({'display':'none'});

		    $.ajax({
				url:"../init/.prof/prof-live.php",
				method:"POST",
				data:{trie:trie},

				success:function(data){
					$("#profTriage-result").html(data);
				}
			});
		});


/*:::::::::::::::::::::::::::: FILTRAGE STD ::::::::::::::::::::::::::::::::::*/		
		$('.stdFilter').click(function(){
			
			var hrefFilter = $(this).attr('href');
		    
		    var filter = hrefFilter.split('filter=')[1].split('&')[0];

		    var channel = hrefFilter.split('channel=')[1];

		    $('#stdTriage-result').css({'display':'none'});
		    $('#stdSearch-result').css({'display':'block'});
			$('#all-std').css({'display':'none'});

		    $.ajax({
				url:"../init/.student/std-livesearch.php",
				method:"POST",
				data:{filter:filter , channel:channel},

				success:function(data){
					$("#stdSearch-result").html(data);
				}
			});
		});
		    
/*:::::::::::::::::::::::::::: FILTRAGE COURS ::::::::::::::::::::::::::::::::::*/		
		$('.coursFilter').click(function(){
			
			var hrefFilter = $(this).attr('href');
		    
		    var filter = hrefFilter.split('filter=')[1].split('&')[0];

		    var channel = hrefFilter.split('channel=')[1];

		    $('#coursTriage-result').css({'display':'none'});
		    $('#coursSearch-result').css({'display':'block'});
			$('#all-cours').css({'display':'none'});

		    $.ajax({
				url:"../init/.cours/cours-livesearch.php",
				method:"POST",
				data:{filter:filter , channel:channel},

				success:function(data){
					$("#coursSearch-result").html(data);
				}
			});
		});

/*:::::::::::::::::::::::::::: FILTRAGE PROF ::::::::::::::::::::::::::::::::::*/		
		$('.profFilter').click(function(){
			
			var hrefFilter = $(this).attr('href');
		    
		    var filter = hrefFilter.split('filter=')[1].split('&')[0];

		    var channel = hrefFilter.split('channel=')[1];

		    $('#profTriage-result').css({'display':'none'});
		    $('#profSearch-result').css({'display':'block'});
			$('#all-prof').css({'display':'none'});

		    $.ajax({
				url:"../init/.prof/prof-livesearch.php",
				method:"POST",
				data:{filter:filter , channel:channel},

				success:function(data){
					$("#profSearch-result").html(data);
				}
			});
		});

	});
</script>