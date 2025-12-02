<?php 
	$path = $_SERVER['PHP_SELF'];
	$page = basename($path);
	
	$infinit_pseudo = $_SESSION['infinit_pseudo'] ;
	$infinit_password = $_SESSION['infinit_password'];


	if($infinit_pseudo !='' && $infinit_password !=''){

		$rg_utilisateur = $dtb->query("SELECT * FROM compt_utilisateur WHERE pseudo='".$infinit_pseudo."' AND password='".$infinit_password."' AND etat=1 limit 1");
		$rg_user = $rg_utilisateur->fetch();

		$rg_id = $rg_user['id'];
		$rg_name = $rg_user['nom'];
		$rg_last_name = $rg_user['prenom'];
		$rg_userId = $rg_user['id'];
		$rg_photos = $rg_user['photos'];
		$privilege = $rg_user['privilege'];
		$rg_level = $rg_user['level'];

		/*if ($page != 'inscription.php' AND $privilege == 'visitor') {
			header('location:../inscription/inscription.php');
		}*/

	}else{
		header('location:./index.php');
	}
 ?>

<style>
	/* Topbar animations and styles */
	.topbar-search-input {
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	.topbar-search-input:focus {
		box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.3);
		border-color: #06b6d4;
		transform: scale(1.02);
	}
	
	.user-avatar {
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	.user-avatar:hover {
		transform: scale(1.1);
		box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
	}
	
	.dropdown-menu {
		animation: slideDown 0.2s ease-out;
		backdrop-filter: blur(8px);
	}
	
	@keyframes slideDown {
		from {
			opacity: 0;
			transform: translateY(-10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
	
	.dropdown-item-hover {
		transition: all 0.2s ease;
		position: relative;
		overflow: hidden;
	}
	
	.dropdown-item-hover::before {
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
	
	.dropdown-item-hover:hover::before {
		transform: scaleY(1);
	}
	
	.logo-container {
		transition: all 0.3s ease;
	}
	
	.logo-container:hover {
		transform: translateX(3px);
	}
	
	.search-icon {
		position: absolute;
		left: 10px;
		top: 50%;
		transform: translateY(-50%);
		color: #94a3b8;
		pointer-events: none;
	}
	
	.search-wrapper {
		position: relative;
	}
	
	.search-wrapper input {
		padding-left: 35px;
	}
	
	/* Mobile menu toggle */
	@media (max-width: 640px) {
		.mobile-hidden {
			display: none !important;
		}
	}
</style>

<div class="flex items-center justify-between w-full h-14 shadow-lg <?=$bg_one_color?> <?=$txt_one_color?> px-3 border-b border-slate-700">
	
	<!-- Logo Section -->
	<div class="flex items-center gap-3 lg:w-2/12">
		<a href="../src/" class="logo-container flex items-center gap-2 hover:opacity-80 transition-opacity">
			<div class="w-8 h-8 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-lg p-1 shadow-md">
				<img src="../file/logo-coldbloud.png" class="w-full h-full">
			</div>
			<span class="hidden md:block font-bold text-base bg-gradient-to-r from-cyan-400 to-blue-500 bg-clip-text text-transparent">
				Infinit Registrar
			</span>
		</a>
	</div>
	
	<!-- Search Section -->
	<div class="flex-1 px-2 lg:px-4 lg:w-7/12">
		<div class="search w-full flex justify-center">
			<?php 
			if($page == "accueil.php" OR $page == "student.php") {
			 ?>
			<form method="post" action="accueil.php" class="search-wrapper w-full max-w-md">
				<input id="std-search" type="text" name="search" 
					   placeholder="Rechercher un étudiant..." 
					   class="topbar-search-input h-10 px-2 pl-10 text-sm border border-slate-600 <?=$bg_two_color?> text-slate-100 w-full rounded-lg focus:outline-none placeholder-slate-400">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}elseif($page == "accueil.cours.php" OR $page == "cours.php") {
			?>
			<form method="post" action="accueil.cours.php" class="search-wrapper w-full max-w-md">
				<i class="bi bi-book search-icon"></i>
				<input id="cours-search" type="text" name="search" 
					   placeholder="Rechercher un cours..." 
					   class="topbar-search-input h-10 px-2 pl-10 text-sm border border-slate-600 <?=$bg_two_color?> text-slate-100 w-full rounded-lg focus:outline-none placeholder-slate-400">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}elseif($page == "accueil.prof.php" OR $page == "prof.php") {
			?>
			<form method="post" action="accueil.cours.php" class="search-wrapper w-full max-w-md">
				<i class="bi bi-person-badge search-icon"></i>
				<input id="prof-search" type="text" name="search" 
					   placeholder="Rechercher un enseignant..." 
					   class="topbar-search-input h-10 px-2 pl-10 text-sm border border-slate-600 <?=$bg_two_color?> text-slate-100 w-full rounded-lg focus:outline-none placeholder-slate-400">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}
			?>
		</div>
	</div>
	
	<!-- User Profile Section -->
	<div class="flex items-center gap-3 lg:w-3/12 justify-end">
		<!-- Theme Toggle (commented out but styled) -->
		<!-- <div class="mobile-hidden">
			<button id="light" class="p-2 hover:bg-slate-700 rounded-lg transition-colors">
				<i class="bi-sun-fill text-lg text-yellow-400"></i>
			</button>
			<button id="dark" class="hidden p-2 hover:bg-slate-700 rounded-lg transition-colors">
				<i class="bi-moon-stars-fill text-lg"></i>
			</button>
		</div> -->
		
		<!-- User Dropdown -->
		<div class="relative dropdown-container">
			<a href="#" class="flex items-center gap-2 hover:opacity-90 transition-all duration-300" data-bs-toggle="dropdown" aria-expanded="false">
				<span class="hidden sm:block font-medium text-sm text-slate-200 hover:text-cyan-400 transition-colors"><?=$rg_last_name;?></span>
				<div class="user-avatar rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 w-10 h-10 p-0.5 shadow-lg ring-2 ring-transparent hover:ring-cyan-400/50 transition-all duration-300">
					<img src="../app/photosuser/<?=$rg_photos?>" class="rounded-full w-full h-full object-cover border-2 border-slate-800" alt="User Avatar">
				</div>		
			</a>
			
			<!-- Dropdown Menu -->
			<ul class="dropdown-menu bg-slate-800/95 backdrop-blur-xl border border-slate-700/50 text-slate-100 p-2 rounded-xl shadow-2xl text-sm overflow-auto" 
			    style="max-height:500px; min-width: 260px; position: absolute; right: 0; margin-top: 12px; backdrop-filter: blur(12px);">
		        
		        <!-- User Info Header with gradient background -->
		        <li class="px-4 py-3 bg-gradient-to-br from-slate-700 via-slate-600 to-slate-700 rounded-lg mb-2 shadow-md">
		        	<div class="flex items-center gap-3">
		        		<div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg">
		        			<i class="bi bi-person-circle text-white text-xl"></i>
		        		</div>
		        		<div class="flex-1 min-w-0">
		        			<div class="font-bold text-white truncate"><?=$rg_name?> <?=$rg_last_name?></div>
		        			<div class="text-xs text-cyan-400 font-medium uppercase tracking-wide"><?=$rg_user['privilege'];?></div>
		        		</div>
		        	</div>
		        </li>
		        
		        <!-- My Account -->
		        <li class="dropdown-item-hover mb-1">
		        	<a href="./my.account.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
		        			<i class="bi-gear text-cyan-400 group-hover:rotate-90 transition-transform duration-300"></i>
		        		</div>
		        		<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Mon compte</span>
		        	</a>
		        </li>
		
				<?php if($rg_user['level'] <=2) { ?>
				
				<!-- Divider -->
				<li class="my-2">
					<div class="h-px bg-gradient-to-r from-transparent via-slate-600 to-transparent"></div>
				</li>
				
				<!-- Section Title -->
				<li class="px-3 py-2">
					<div class="flex items-center gap-2">
						<div class="w-1 h-4 bg-gradient-to-b from-purple-500 to-blue-500 rounded-full"></div>
						<span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Outils Dev</span>
					</div>
				</li>
				
				<!-- Dev Tools Items -->
				<li class="dropdown-item-hover mb-1">
					<a href="../app/Motors/" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
						<div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-500/20 transition-colors">
			        		<i class="bi-code-slash text-purple-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">Lanceur de code</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
				</li>

				<li class="dropdown-item-hover mb-1">
					<a href="../wordpress/wp-login.php" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
						<div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center group-hover:bg-blue-500/20 transition-colors">
			        		<i class="bi-wordpress text-blue-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200">WordPress</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
				</li>
		        
		        <li class="dropdown-item-hover mb-1">
		        	<a href="../../phpmyadmin/index.php?route=/database/structure&db=registrar_db" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center group-hover:bg-orange-500/20 transition-colors">
			        		<i class="bi-database-exclamation text-orange-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 text-sm">MySQL</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
		        </li>
		        
		        <li class="dropdown-item-hover mb-1">
		        	<a href="https://icons.getbootstrap.com/" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-500/20 transition-colors">
			        		<i class="bi-bootstrap-fill text-purple-500"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 text-sm">Icons</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
		        </li>

		        <li class="dropdown-item-hover mb-1">
		        	<a href="https://tailwindcss.com/docs/installation" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
			        		<i class="bi-filetype-css text-cyan-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 text-sm">Tailwind</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
		        </li>
		        
		        <!-- Divider -->
		        <li class="my-2">
					<div class="h-px bg-gradient-to-r from-transparent via-slate-600 to-transparent"></div>
				</li>
				
				<!-- Applications Section Title -->
				<li class="px-3 py-2">
					<div class="flex items-center gap-2">
						<div class="w-1 h-4 bg-gradient-to-b from-green-500 to-blue-500 rounded-full"></div>
						<span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Applications</span>
					</div>
				</li>

				<!-- Applications Items -->
		        <li class="dropdown-item-hover mb-1">
		        	<a href="../../rhm/" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center group-hover:bg-green-500/20 transition-colors">
			        		<i class="bi-app-indicator text-green-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 text-sm">RH</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
		        </li>

		        <li class="dropdown-item-hover mb-1">
		        	<a href="../../uaz-site/" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-yellow-500/10 flex items-center justify-center group-hover:bg-yellow-500/20 transition-colors">
			        		<i class="bi-award-fill text-yellow-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 text-sm">UAZ Site</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
		        </li>

		        <li class="dropdown-item-hover mb-1">
		        	<a href="../../" target="_blank" class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-slate-700/70 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center group-hover:bg-blue-500/20 transition-colors">
			        		<i class="bi-alexa text-blue-400"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 text-sm">Registrar</span>
			        	<i class="bi-box-arrow-up-right text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
			        </a>
		        </li>
		        
				<?php }?>
		        
		        <!-- Final Divider -->
		        <li class="my-2">
					<div class="h-px bg-gradient-to-r from-transparent via-slate-600 to-transparent"></div>
				</li>
		        
		        <!-- Logout with special styling -->
		        <li class="dropdown-item-hover">
		        	<a href="#" class="logOut flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-gradient-to-r hover:from-red-600 hover:to-red-700 transition-all duration-200 group">
		        		<div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center group-hover:bg-red-500/20 transition-colors">
			        		<i class="bi-door-open-fill text-red-400 group-hover:text-white transition-colors"></i>
			        	</div>
			        	<span class="flex-1 group-hover:translate-x-1 transition-transform duration-200 font-medium group-hover:text-white">Se déconnecter</span>
			        	<i class="bi-arrow-right text-sm opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-200"></i>
			        </a>
		        </li>
			</ul>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#std-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#stdTriage-result').css({'display':'none'});
				$('#stdSearch-result').css({'display':'block'});
				$('#all-std').css({'display':'none'});

				$.ajax({
					url:"../init/.student/std-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#stdSearch-result").html(data);
					}
				});

			}else{
				$('#stdTriage-result').css({'display':'none'});
				$('#stdSearch-result').css({'display':'none'});
				$('#all-std').css({'display':'block'});
			}
		});
/*/////////////////////////////////////////////////////////////////*/
		$('#cours-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#coursTriage-result').css({'display':'none'});
				$('#coursSearch-result').css({'display':'block'});
				$('#all-cours').css({'display':'none'});

				$.ajax({
					url:"../init/.cours/cours-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#coursSearch-result").html(data);
					}
				});

			}else{
				$('#coursTriage-result').css({'display':'none'});
				$('#coursSearch-result').css({'display':'none'});
				$('#all-cours').css({'display':'block'});
			}
		});
/*/////////////////////////////////////////////////////////////////*/
		$('#prof-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#profTriage-result').css({'display':'none'});
				$('#profSearch-result').css({'display':'block'});
				$('#all-prof').css({'display':'none'});

				$.ajax({
					url:"../init/.prof/prof-livesearch.php",
					method:"POST",
					data:{input:input},

					success:function(data){
						$("#profSearch-result").html(data);
					}
				});

			}else{
				$('#profTriage-result').css({'display':'none'});
				$('#profSearch-result').css({'display':'none'});
				$('#all-prof').css({'display':'block'});
			}
		});
/*/////////////////////////////////////////////////////////////////*/
		$('#light').click(function(){
			
			var bg_one_color = '<?=$bg_one_color?>';
			var bg_two_color = '<?=$bg_two_color?>';
			var bg_three_color = '<?=$bg_three_color?>';
			var bg_four_color = '<?=$bg_four_color?>';
			var bg_five_color = '<?=$bg_five_color?>';
			var bg_six_color = '<?=$bg_six_color?>';
			var bg_seven_color = '<?=$bg_seven_color?>';
			var bg_eight_color = '<?=$bg_eight_color?>';
	
			var br_two_color = '<?=$br_two_color?>';
			var br_three_color = '<?=$br_three_color?>';

			var txt_one_color = '<?=$txt_one_color?>';
			var txt_two_color = '<?=$txt_two_color?>';
			var txt_three_color = '<?=$txt_three_color?>';

			$(this).css({'display':'none'});
			$('#dark').css({'display':'block'});

		});

		$('#dark').click(function(){
			
			$(this).css({'display':'none'});
			$('#light').css({'display':'block'});


		});
	});

</script>
