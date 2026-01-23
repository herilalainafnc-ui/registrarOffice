<?php 
	$path = $_SERVER['PHP_SELF'];
	$page = basename($path);
	
	// Utiliser le middleware pour récupérer l'utilisateur courant
	// Le middleware est déjà initialisé dans head.php
	$rg_user = currentUser();
	
	if ($rg_user) {
		// Variables pour compatibilité avec l'ancien code
		$infinit_pseudo = $rg_user['pseudo'];
		$infinit_password = $rg_user['password'];
		$rg_id = $rg_user['id'];
		$rg_name = $rg_user['nom'];
		$rg_last_name = $rg_user['prenom'];
		$rg_userId = $rg_user['id'];
		$rg_photos = $rg_user['photos'];
		$privilege = $rg_user['privilege'];
		$rg_level = $rg_user['level'];
	} else {
		// Redirection gérée par le middleware, mais au cas où
		header('Location: ./index.php');
		exit;
	}
 ?>

<style>
	/* ===== THEME TOGGLE & MODES ===== */
	
	/* Theme Toggle Button */
	.theme-toggle {
		width: 40px;
		height: 40px;
		border-radius: 8px;
		border: 1px solid #1a3a5c;
		background: #0d1f3c;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
		position: relative;
		overflow: hidden;
	}
	
	.theme-toggle:hover {
		background: #1a3a5c;
		border-color: #4e9ede;
	}
	
	.theme-toggle .icon-sun,
	.theme-toggle .icon-moon {
		position: absolute;
		transition: all 0.3s ease;
	}
	
	.theme-toggle .icon-sun {
		color: #fbbf24;
		font-size: 18px;
	}
	
	.theme-toggle .icon-moon {
		color: #8eb8d4;
		font-size: 18px;
	}
	
	/* Dark mode (default) */
	[data-theme="dark"] .theme-toggle .icon-sun {
		opacity: 1;
		transform: rotate(0deg) scale(1);
	}
	
	[data-theme="dark"] .theme-toggle .icon-moon {
		opacity: 0;
		transform: rotate(-90deg) scale(0);
	}
	
	/* Light mode */
	[data-theme="light"] .theme-toggle .icon-sun {
		opacity: 0;
		transform: rotate(90deg) scale(0);
	}
	
	[data-theme="light"] .theme-toggle .icon-moon {
		opacity: 1;
		transform: rotate(0deg) scale(1);
	}
	
	[data-theme="light"] .theme-toggle {
		background: #e8f1f8;
		border-color: #8eb8d4;
	}
	
	[data-theme="light"] .theme-toggle:hover {
		background: #d1e5f4;
		border-color: #4e9ede;
	}
	
	[data-theme="light"] .theme-toggle .icon-moon {
		color: #1a3a5c;
	}
	
	/* ===== LIGHT MODE STYLES ===== */
	[data-theme="light"] .topbar-bleu-nuit {
		background: #e8f1f8 !important;
		border-bottom-color: #8eb8d4 !important;
	}
	
	[data-theme="light"] .topbar-bleu-nuit {
		color: #0a1628 !important;
	}
	
	[data-theme="light"] .logo-text-gradient {
		background: linear-gradient(135deg, #1a3a5c, #4e9ede);
		-webkit-background-clip: text;
		background-clip: text;
	}
	
	[data-theme="light"] .topbar-search-input {
		background: #ffffff !important;
		border-color: #8eb8d4 !important;
		color: #0a1628 !important;
	}
	
	[data-theme="light"] .topbar-search-input::placeholder {
		color: #5a8aa8 !important;
	}
	
	[data-theme="light"] .dropdown-menu {
		background: #ffffff !important;
		border-color: #8eb8d4 !important;
	}
	
	[data-theme="light"] .dropdown-item-hover:hover {
		background: #e8f1f8 !important;
		color: #0a1628 !important;
	}
	
	/* ===== BLEU NUIT TOPBAR STYLES ===== */
	.topbar-search-input {
		transition: all 0.15s ease;
		background: #0d1f3c !important;
		border-color: #1a3a5c !important;
		color: #e8f1f8 !important;
	}
	
	.topbar-search-input:focus {
		box-shadow: 0 0 0 2px rgba(78, 158, 222, 0.3);
		border-color: #4e9ede !important;
	}
	
	.topbar-search-input::placeholder {
		color: #5a8aa8 !important;
	}
	
	.user-avatar {
		transition: all 0.15s ease;
	}
	
	.user-avatar:hover {
		transform: scale(1.05);
		box-shadow: 0 2px 8px rgba(78, 158, 222, 0.3);
	}
	
	.dropdown-menu {
		background: #0a1628 !important;
		border: 1px solid #1a3a5c !important;
		border-radius: 8px;
	}
	
	.dropdown-item-hover {
		transition: all 0.15s ease;
		color: #8eb8d4;
	}
	
	.dropdown-item-hover:hover {
		background: #0d1f3c !important;
		color: #e8f1f8 !important;
	}
	
	.logo-container {
		transition: all 0.15s ease;
	}
	
	.logo-container:hover {
		opacity: 0.8;
	}
	
	.search-icon {
		position: absolute;
		left: 10px;
		top: 50%;
		transform: translateY(-50%);
		color: #5a8aa8;
		pointer-events: none;
	}
	
	.search-wrapper {
		position: relative;
	}
	
	.search-wrapper input {
		padding-left: 35px;
	}
	
	/* Topbar background */
	.topbar-bleu-nuit {
		background: #0a1628 !important;
		border-bottom: 1px solid #1a3a5c !important;
	}
	
	/* Topbar sticky on mobile */
	@media (max-width: 1023px) {
		.topbar-bleu-nuit {
			position: sticky !important;
			top: 0 !important;
			z-index: 1001 !important;
		}
	}
	
	/* Logo text gradient */
	.logo-text-gradient {
		background: linear-gradient(135deg, #4e9ede, #8eb8d4);
		-webkit-background-clip: text;
		-webkit-text-fill-color: transparent;
		background-clip: text;
	}
	
	/* Mobile menu toggle */
	@media (max-width: 640px) {
		.mobile-hidden {
			display: none !important;
		}
	}
</style>

<div class="flex items-center justify-between w-full h-14 topbar-bleu-nuit text-[#e8f1f8] px-3">
	
	<!-- Logo Section -->
	<div class="hidden md:flex items-center gap-3 lg:w-2/12">
		<a href="../src/" class="logo-container flex items-center gap-2">
			<div class="w-8 h-8 bg-gradient-to-br from-[#4e9ede] to-[#1a3a5c] rounded-lg p-1 shadow-md">
				<img src="../file/logo-coldbloud.png" class="w-full h-full">
			</div>
			<span class="hidden md:block font-bold text-base logo-text-gradient">
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
			<form method="post" action="accueil" class="search-wrapper w-full max-w-md">
				<input id="std-search" type="text" name="search" 
					   placeholder="Rechercher un étudiant..." 
					   class="topbar-search-input h-10 px-2 pl-10 text-sm border border-slate-600 <?=$bg_two_color?> text-slate-100 w-full rounded-lg focus:outline-none placeholder-slate-400">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}elseif($page == "accueil.cours.php" OR $page == "cours.php") {
			?>
			<form method="post" action="accueil.cours" class="search-wrapper w-full max-w-md">
				<input id="cours-search" type="text" name="search" 
					   placeholder="Rechercher un cours..." 
					   class="topbar-search-input h-10 px-2 pl-10 text-sm border border-slate-600 <?=$bg_two_color?> text-slate-100 w-full rounded-lg focus:outline-none placeholder-slate-400">
				<button type="submit" style="display: none" onclick="surligne();"></button>
			</form>
			<?php 
			}elseif($page == "accueil.prof.php" OR $page == "prof.php") {
			?>
			<form method="post" action="accueil.prof" class="search-wrapper w-full max-w-md">
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
		<!-- Theme Toggle Button -->
		<button class="theme-toggle" id="themeToggle" title="Basculer le thème">
			<i class="bi bi-sun-fill icon-sun"></i>
			<i class="bi bi-moon-stars-fill icon-moon"></i>
		</button>
		
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
	// ===== THEME TOGGLE FUNCTIONALITY =====
	(function() {
		const themeToggle = document.getElementById('themeToggle');
		const html = document.documentElement;
		
		// Get saved theme or default to dark
		const savedTheme = localStorage.getItem('theme') || 'dark';
		html.setAttribute('data-theme', savedTheme);
		
		// Toggle theme on click
		if (themeToggle) {
			themeToggle.addEventListener('click', function() {
				const currentTheme = html.getAttribute('data-theme');
				const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
				
				html.setAttribute('data-theme', newTheme);
				localStorage.setItem('theme', newTheme);
				
				// Dispatch event for other components to react
				window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: newTheme } }));
			});
		}
	})();

	$(document).ready(function() {
		$('#std-search').keyup(function() {
			var input = $(this).val();
			if(input != ''){
				$('#stdTriage-result').css({'display':'none'});
				$('#stdSearch-result').css({'display':'block'});
				$('#all-std').css({'display':'none'});

				$.ajax({
					url:"../init/.student/std-livesearch",
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
					url:"../init/.cours/cours-livesearch",
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
					url:"../init/.prof/prof-livesearch",
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
	});

</script>
