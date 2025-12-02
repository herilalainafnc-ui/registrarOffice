<style type="text/css">
	/* Toolbar Container */
	.toolbar-container {
		background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
		box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
		border-bottom: 1px solid rgba(6, 182, 212, 0.2);
		min-height: 75px;
		transition: all 0.3s ease;
	}
	
	/* Toolbar Section */
	.toolbar-section {
		border-right: 1px solid rgba(148, 163, 184, 0.1);
		transition: all 0.3s ease;
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
		padding: 12px 8px;
		text-decoration: none;
		color: #cbd5e1;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		border-radius: 8px;
		overflow: hidden;
		cursor: pointer;
		border: none;
		background: transparent;
		min-height: 70px;
	}
	
	.tool-btn::before {
		content: '';
		position: absolute;
		bottom: 0;
		left: 0;
		right: 0;
		height: 3px;
		background: linear-gradient(90deg, #06b6d4, #3b82f6);
		transform: scaleX(0);
		transition: transform 0.3s ease;
	}
	
	.tool-btn:hover::before {
		transform: scaleX(1);
	}
	
	.tool-btn:hover {
		background: rgba(6, 182, 212, 0.1);
		color: #fff;
		transform: translateY(-2px);
	}
	
	.tool-btn:active {
		background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(59, 130, 246, 0.2));
		transform: translateY(0);
	}
	
	/* Tool Icon */
	.tool-icon {
		width: 40px;
		height: 40px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 10px;
		background: rgba(51, 65, 85, 0.5);
		transition: all 0.3s ease;
		font-size: 20px;
	}
	
	.tool-btn:hover .tool-icon {
		background: rgba(6, 182, 212, 0.2);
		transform: rotate(-5deg) scale(1.1);
		box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
	}
	
	.tool-btn i {
		transition: all 0.3s ease;
	}
	
	.tool-btn:hover i {
		color: #06b6d4;
	}
	
	/* Tool Label */
	.tool-label {
		font-size: 11px;
		font-weight: 500;
		text-align: center;
		line-height: 1.2;
		letter-spacing: 0.02em;
		max-width: 100%;
		word-wrap: break-word;
	}
	
	/* Inactive Tools */
	.toolInactive {
		opacity: 0.4;
		pointer-events: none;
		cursor: not-allowed;
	}
	
	.toolInactive .tool-icon {
		background: rgba(51, 65, 85, 0.3);
	}
	
	/* Dropdown Menu Styles */
	.toolbar-dropdown {
		background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
		border: 1px solid rgba(6, 182, 212, 0.3);
		border-radius: 12px;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
		padding: 8px;
		backdrop-filter: blur(12px);
		animation: dropdownSlide 0.2s ease;
	}
	
	@keyframes dropdownSlide {
		from {
			opacity: 0;
			transform: translateY(-10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
	
	.toolbar-dropdown .dropdown-header {
		background: linear-gradient(135deg, #06b6d4, #3b82f6);
		color: white;
		padding: 8px 12px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		margin: 4px 0;
		border-radius: 6px;
		box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
	}
	
	.toolbar-dropdown li a {
		display: block;
		transition: all 0.2s ease;
	}
	
	.toolbar-dropdown li a p {
		padding: 10px 12px;
		margin: 0;
		border-radius: 6px;
		color: #e2e8f0;
		transition: all 0.2s ease;
		position: relative;
		overflow: hidden;
	}
	
	.toolbar-dropdown li a p::before {
		content: '';
		position: absolute;
		left: 0;
		top: 0;
		height: 100%;
		width: 3px;
		background: linear-gradient(180deg, #06b6d4, #3b82f6);
		transform: scaleY(0);
		transition: transform 0.2s ease;
	}
	
	.toolbar-dropdown li a:hover p {
		background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(59, 130, 246, 0.15));
		color: #fff;
		transform: translateX(4px);
	}
	
	.toolbar-dropdown li a:hover p::before {
		transform: scaleY(1);
	}
	
	/* Responsive Toolbar */
	@media (max-width: 1024px) {
		.toolbar-section {
			border-right: none;
			border-bottom: 1px solid rgba(148, 163, 184, 0.1);
		}
		
		.tool-btn {
			min-height: 60px;
			padding: 8px 6px;
		}
		
		.tool-icon {
			width: 36px;
			height: 36px;
			font-size: 18px;
		}
		
		.tool-label {
			font-size: 10px;
		}
	}
	
	@media (max-width: 640px) {
		.toolbar-container {
			min-height: auto;
		}
		
		.tool-btn {
			min-height: 55px;
			padding: 6px 4px;
			gap: 4px;
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
	
	/* Tool Badge/Indicator */
	.tool-badge {
		position: absolute;
		top: 8px;
		right: 8px;
		width: 8px;
		height: 8px;
		background: linear-gradient(135deg, #ef4444, #dc2626);
		border-radius: 50%;
		box-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
		animation: pulse 2s infinite;
	}
	
	@keyframes pulse {
		0%, 100% {
			opacity: 1;
			transform: scale(1);
		}
		50% {
			opacity: 0.7;
			transform: scale(1.1);
		}
	}
	
	/* Special color variants for different tool types */
	.tool-btn.tool-export:hover i { color: #f59e0b; }
	.tool-btn.tool-stats:hover i { color: #8b5cf6; }
	.tool-btn.tool-document:hover i { color: #10b981; }
	.tool-btn.tool-finance:hover i { color: #ef4444; }
	.tool-btn.tool-sort:hover i { color: #06b6d4; }
	.tool-btn.tool-filter:hover i { color: #3b82f6; }
	.tool-btn.tool-search:hover i { color: #f59e0b; }
	.tool-btn.tool-settings:hover i { color: #64748b; }
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