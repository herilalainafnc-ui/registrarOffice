<style type="text/css">
	/* ============================================
	   BLEU NUIT TOOLBAR STYLES
	   Modern, minimal design with night blue
	   ============================================ */
	
	/* Toolbar Container */
	.toolbar-container {
		background: #0a1628;
		border-bottom: 1px solid #1a3a5c;
		min-height: 72px;
		transition: background 0.2s ease, border-color 0.2s ease;
	}
	
	/* ===== LIGHT MODE TOOLBAR ===== */
	[data-theme="light"] .toolbar-container {
		background: #f0f7fc;
		border-bottom-color: #8eb8d4;
	}
	
	[data-theme="light"] .toolbar-section {
		border-right-color: #8eb8d4;
	}
	
	[data-theme="light"] .tool-btn {
		color: #1a3a5c;
	}
	
	[data-theme="light"] .tool-btn:hover {
		background: #e0eef7;
		color: #0a1628;
	}
	
	[data-theme="light"] .tool-icon {
		background: #e0eef7;
	}
	
	[data-theme="light"] .tool-btn:hover .tool-icon {
		background: #4e9ede;
		color: #ffffff;
	}
	
	[data-theme="light"] .toolbar-dropdown {
		background: #ffffff;
		border-color: #8eb8d4;
	}
	
	[data-theme="light"] .toolbar-dropdown .dropdown-header {
		background: #4e9ede;
		color: #ffffff;
	}
	
	[data-theme="light"] .toolbar-dropdown li a p {
		color: #1a3a5c;
	}
	
	[data-theme="light"] .toolbar-dropdown li a:hover p {
		background: #e0eef7;
		color: #0a1628;
	}
	
	/* Toolbar Section */
	.toolbar-section {
		border-right: 1px solid #1a3a5c;
	}
	
	.toolbar-section:last-child {
		border-right: none;
	}
	
	/* Tool Button */
	.tool-btn {
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 6px;
		padding: 10px 8px;
		text-decoration: none;
		color: #8eb8d4;
		transition: all 0.15s ease;
		border-radius: 6px;
		cursor: pointer;
		border: none;
		background: transparent;
		min-height: 68px;
	}
	
	.tool-btn:hover {
		background: #0d1f3c;
		color: #e8f1f8;
	}
	
	.tool-btn:active {
		background: #0f2847;
	}
	
	/* Tool Icon */
	.tool-icon {
		width: 36px;
		height: 36px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 6px;
		background: #0d1f3c;
		transition: all 0.15s ease;
		font-size: 18px;
	}
	
	.tool-btn:hover .tool-icon {
		background: #4e9ede;
		color: #0a1628;
	}
	
	.tool-btn i {
		transition: color 0.15s ease;
	}
	
	/* Tool Label */
	.tool-label {
		font-size: 10px;
		font-weight: 500;
		text-align: center;
		line-height: 1.2;
		letter-spacing: 0.01em;
		max-width: 100%;
		word-wrap: break-word;
	}
	
	/* Inactive Tools */
	.toolInactive {
		opacity: 0.35;
		pointer-events: none;
		cursor: not-allowed;
	}
	
	.toolInactive .tool-icon {
		background: hsl(217.2 32.6% 12%);
	}
	
	/* Dropdown Menu Styles */
	.toolbar-dropdown {
		background: #0a1628;
		border: 1px solid #1a3a5c;
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
		padding: 4px;
	}
	
	.toolbar-dropdown .dropdown-header {
		background: #4e9ede;
		color: #0a1628;
		padding: 6px 12px;
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		margin: 4px;
		border-radius: 4px;
	}
	
	.toolbar-dropdown li a {
		display: block;
	}
	
	.toolbar-dropdown li a p {
		padding: 8px 12px;
		margin: 2px 4px;
		border-radius: 4px;
		color: #8eb8d4;
		transition: all 0.15s ease;
	}
	
	.toolbar-dropdown li a:hover p {
		background: #0d1f3c;
		color: #e8f1f8;
	}
	
	/* Responsive Toolbar */
	@media (max-width: 1024px) {
		.toolbar-section {
			border-right: none;
			border-bottom: 1px solid #1a3a5c;
		}
		
		.tool-btn {
			min-height: 58px;
			padding: 8px 6px;
		}
		
		.tool-icon {
			width: 32px;
			height: 32px;
			font-size: 16px;
		}
		
		.tool-label {
			font-size: 9px;
		}
	}
	
	@media (max-width: 640px) {
		.toolbar-container {
			min-height: auto;
		}
		
		.tool-btn {
			min-height: 52px;
			padding: 6px 4px;
			gap: 4px;
		}
		
		.tool-icon {
			width: 28px;
			height: 28px;
			font-size: 14px;
		}
		
		.tool-label {
			font-size: 8px;
		}
	}
	
	/* Tool Badge/Indicator */
	.tool-badge {
		position: absolute;
		top: 6px;
		right: 6px;
		width: 6px;
		height: 6px;
		background: hsl(0 84.2% 60.2%);
		border-radius: 50%;
	}
	
	/* Tool type hover colors - all use blue for consistency */
	.tool-btn.tool-export:hover .tool-icon,
	.tool-btn.tool-stats:hover .tool-icon,
	.tool-btn.tool-document:hover .tool-icon,
	.tool-btn.tool-finance:hover .tool-icon,
	.tool-btn.tool-sort:hover .tool-icon,
	.tool-btn.tool-filter:hover .tool-icon,
	.tool-btn.tool-search:hover .tool-icon,
	.tool-btn.tool-settings:hover .tool-icon {
		background: hsl(217.2 91.2% 59.8%);
		color: hsl(222.2 84% 4.9%);
	}
</style>

<div class="toolbar-container w-full py-2 flex flex-wrap <?=$txt_one_color?>">
	
	<!-- SECTION 1: Export & Stats -->	
	<div class="toolbar-section sm:w-full md:w-4/12 lg:w-3/12 flex px-1">
		
		<a href="#" id="<?php 
		if($page == "accueil.php" OR $page == "student.php" OR $page=="inscription.php") {
		 	echo "exportListStd";
		}elseif($page == "accueil.cours.php" OR $page == "cours.php") {
			echo "exportListCours";
		}elseif($page == "accueil.prof.php" OR $page == "prof.php") {
			echo "exportListProf";
		} ?>" class="tool-btn tool-export text-xs flex-1 <?php if($rg_user['level'] <=3) { echo "";}else{ echo "toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-filetype-pdf"></i>
			</div>
			<span class="tool-label">Exporter<br>la liste</span>
		</a>
		
		<a id="exportStatistic" href="#" class="tool-btn tool-stats text-xs flex-1 <?php if($rg_user['level'] <=3) { echo "";}else{ echo "toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-bar-chart-line-fill"></i>
			</div>
			<span class="tool-label">Statistique</span>
		</a>
		
		<a href="#" id="exportListFOP" class="tool-btn tool-document text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-file-text-fill"></i>
			</div>
			<span class="tool-label">Liste pour<br>la FOP</span>
		</a>
		
		<a href="#" id="finance" class="tool-btn tool-finance text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=3) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-currency-exchange"></i>
			</div>
			<span class="tool-label">Finance</span>
		</a>
		
	</div>

	<!-- SECTION 2: Mail & Communication -->	
	<div class="toolbar-section sm:w-full md:w-3/12 lg:w-2/12 flex px-1">

		<a href="#" id="exportMesupres" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-bar-chart-steps"></i>
			</div>
			<span class="tool-label">Mesupres</span>
		</a>
		
		<a href="#" id="exportListCSV" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-envelope-at-fill"></i>
			</div>
			<span class="tool-label">Template<br>mail CSV</span>
		</a>
		
		<a href="#" id="exportTicketMail" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-ticket-perforated-fill"></i>
			</div>
			<span class="tool-label">Tickets<br>mail</span>
		</a>
		
	</div>

	<!-- SECTION 3: Inscription & Work -->	
	<div class="toolbar-section sm:w-full md:w-3/12 lg:w-2/12 flex px-1">
		
		<a target='_blank' href="../inscription/inscription.php" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=4) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-person-fill-add"></i>
			</div>
			<span class="tool-label">Inscription</span>
		</a>
		
		<a href="#" id="worked" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=4) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-person-lines-fill"></i>
			</div>
			<span class="tool-label">Worked</span>
		</a>
		
		<a href="#" id="workedSlip" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=4) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-card-checklist"></i>
			</div>
			<span class="tool-label">Worked<br>Slips</span>
		</a>
		
	</div>

	<!-- SECTION 4: Sort, Filter & Search -->	
	<div class="toolbar-section sm:w-full md:w-3/12 lg:w-2/12 flex px-1">
		
		<!-- Sort Dropdown -->
		<a href="#" class="tool-btn tool-sort text-xs flex-1 <?php 
		if($page == "student.php" OR $page == "cours.php" OR $page == "prof.php" OR $page == "inscription.php") {
			echo "toolInactive";
		} ?>" data-bs-toggle="dropdown" aria-expanded="false">
			<div class="tool-icon">
				<i class="bi bi-sort-alpha-up-alt"></i>
			</div>
			<span class="tool-label">Trier par</span>
		</a>
		
		<ul class="dropdown-menu toolbar-dropdown text-xs overflow-auto" style="max-height:400px; min-width: 200px;">
<?php if ($page == "accueil.php") { ?>
			<li><a href="#?trie=student_id" class="stdTriage"><p>ID</p></a></li>
			<li><a href="#?trie=student_nom" class="stdTriage"><p>Nom</p></a></li>
			<li><a href="#?trie=student_prenom" class="stdTriage"><p>Prénom</p></a></li>
			<li><a href="#?trie=etude_envisage" class="stdTriage"><p>Mention</p></a></li>
			<li><a href="#?trie=etude_option" class="stdTriage"><p>Parcours</p></a></li>
			<li><a href="#?trie=annee_scolaire" class="stdTriage"><p>Année</p></a></li>
			<li><a href="#?trie=annee_etude" class="stdTriage"><p>Niveau</p></a></li>
<?php }elseif($page == "accueil.cours.php") { ?>
			<li><a href="#?trie=Sigle" class="coursTriage"><p>Sigle</p></a></li>
			<li><a href="#?trie=title" class="coursTriage"><p>Cours</p></a></li>
			<li><a href="#?trie=dep_desc" class="coursTriage"><p>Mention</p></a></li>
			<li><a href="#?trie=nb_crd" class="coursTriage"><p>Crédit</p></a></li>
			<li><a href="#?trie=category" class="coursTriage"><p>Catégorie</p></a></li>
			<li><a href="#?trie=yearlevel" class="coursTriage"><p>Niveau</p></a></li>
			<li><a href="#?trie=semester" class="coursTriage"><p>Semestre</p></a></li>
			<li><a href="#?trie=id_teacher" class="coursTriage"><p>Enseignant</p></a></li>
<?php }elseif($page == "accueil.prof.php") { ?>
			<li><a href="#?trie=teacher_id" class="profTriage"><p>ID</p></a></li>
			<li><a href="#?trie=name" class="profTriage"><p>Nom</p></a></li>
			<li><a href="#?trie=lastName" class="profTriage"><p>Prénom</p></a></li>
			<li><a href="#?trie=address" class="profTriage"><p>Adresse</p></a></li>
			<li><a href="#?trie=phone" class="profTriage"><p>Téléphone</p></a></li>
			<li><a href="#?trie=email" class="profTriage"><p>Email</p></a></li>
<?php } ?>
		</ul>

		<!-- Filter Dropdown -->
		<a href="#" class="tool-btn tool-filter text-xs flex-1 <?php 
		if($page == "student.php" OR $page == "cours.php" OR $page == "prof.php" OR $page == "inscription.php") {
			echo "toolInactive";
		} ?>" data-bs-toggle="dropdown" aria-expanded="false">
			<div class="tool-icon">
				<i class="bi bi-funnel-fill"></i>
			</div>
			<span class="tool-label">Filtrage</span>
		</a>

		<ul class="dropdown-menu toolbar-dropdown text-xs overflow-auto" style="max-height:400px; min-width: 200px;">

<?php if ($page == "accueil.php") { ?>
			<b class="dropdown-header">Mention</b>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>					
			<li><a href="#?filter=etude_envisage&channel=<?=$showSignMention['filiere_description']?>" class="stdFilter"><p><?=$showSignMention['filiere_description']?></p></a></li>
 <?php 
}
 ?>
			<b class="dropdown-header">Année</b>
<?php
$y = date('Y');
for ($i=0; $i <= 4; $i++) { 
	
$as = $y." - ".($y+1);
?>
			<li><a href="#?filter=annee_scolaire&channel=<?=$as?>" class="stdFilter"><p><?=$as?></p></a></li>
<?php
$y = $y - 1;
}
 ?>					
 			<b class="dropdown-header">Niveau</b>
			<li><a href="#?filter=annee_etude&channel=1" class="stdFilter"><p>Licence 1</p></a></li>
			<li><a href="#?filter=annee_etude&channel=2" class="stdFilter"><p>Licence 2</p></a></li>
			<li><a href="#?filter=annee_etude&channel=3" class="stdFilter"><p>Licence 3</p></a></li>
			<li><a href="#?filter=annee_etude&channel=4" class="stdFilter"><p>Master 1</p></a></li>
			<li><a href="#?filter=annee_etude&channel=5" class="stdFilter"><p>Master 2</p></a></li>

<?php }elseif($page == "accueil.cours.php") { ?>

			<b class="dropdown-header">Mention</b>
<?php 
$findSignMention = $dtb->query('SELECT * FROM filiere ORDER BY filiere_description');
while ($showSignMention = $findSignMention->fetch()) {
 ?>					
			<li><a href="#?filter=dep_desc&channel=<?=$showSignMention['filiere_sigle']?>" class="coursFilter"><p><?=$showSignMention['filiere_description']?></p></a></li>
 <?php 
}
 ?>
 			<b class="dropdown-header">Catégorie</b>
			<li><a href="#?filter=category&channel=0" class="coursFilter"><p>Général</p></a></li>
			<li><a href="#?filter=category&channel=1" class="coursFilter"><p>Majeur</p></a></li>
			<li><a href="#?filter=category&channel=2" class="coursFilter"><p>Selective</p></a></li>
			<li><a href="#?filter=category&channel=3" class="coursFilter"><p>Additionnel</p></a></li>

 			<b class="dropdown-header">Niveau</b>
			<li><a href="#?filter=yearlevel&channel=1" class="coursFilter"><p>Licence 1</p></a></li>
			<li><a href="#?filter=yearlevel&channel=2" class="coursFilter"><p>Licence 2</p></a></li>
			<li><a href="#?filter=yearlevel&channel=3" class="coursFilter"><p>Licence 3</p></a></li>
			<li><a href="#?filter=yearlevel&channel=4" class="coursFilter"><p>Master 1</p></a></li>
			<li><a href="#?filter=yearlevel&channel=5" class="coursFilter"><p>Master 2</p></a></li>

<?php }elseif($page == "accueil.prof.php") { ?>		
			
			<b class="dropdown-header">Adresse</b>
 					
			<li><a href="#?filter=address&channel=Campus UAZ" class="profFilter"><p>Campus UAZ</p></a></li>
			<li><a href="#?filter=address&channel=" class="profFilter"><p>Autre</p></a></li>
			
			<b class="dropdown-header">Réligion</b>
 					
			<li><a href="#?filter=religion&channel=adventiste" class="profFilter"><p>Adventiste</p></a></li>
			<li><a href="#?filter=religion&channel=non adventiste" class="profFilter"><p>Non Adventiste</p></a></li>	
<?php } ?>
		</ul>

		<!-- Search Button -->
		<a href="#" class="tool-btn tool-search text-xs flex-1 searchTool">
			<div class="tool-icon">
				<i class="bi bi-search"></i>
			</div>
			<span class="tool-label">Rechercher</span>
		</a>

	</div>

	<!-- SECTION 5: Badge & Media -->	
	<div class="toolbar-section sm:w-full md:w-3/12 lg:w-2/12 flex px-1">
		
		<a href="#" id="badgeGr" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=2) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-person-badge-fill"></i>
			</div>
			<span class="tool-label">Badge</span>
		</a>

		<a href="#" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=1) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-file-excel-fill"></i>
			</div>
			<span class="tool-label">Sans<br>Photos</span>
		</a>
		
		<a href="#" class="tool-btn text-xs flex-1 <?php 
		if($page == "accueil.cours.php" OR $page == "cours.php" OR $page == "accueil.prof.php" OR $page == "prof.php") {
			echo "toolInactive";
		} ?><?php if($rg_user['level'] <=1) { echo "";}else{ echo " toolInactive";}?>">
			<div class="tool-icon">
				<i class="bi bi-download"></i>
			</div>
			<span class="tool-label">Photos en<br>masse</span>
		</a>

	</div>

	<!-- SECTION 6: Settings -->	
	<div class="toolbar-section hidden lg:flex w-1/12 px-1">
		
		<a href="./settings.php" class="tool-btn tool-settings text-xs flex-1">
			<div class="tool-icon">
				<i class="bi bi-gear-fill"></i>
			</div>
			<span class="tool-label">Paramètres</span>
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