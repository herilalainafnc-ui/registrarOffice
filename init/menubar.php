<style type="text/css">
	/* ===== BLEU NUIT SIDEBAR STYLES ===== */
	.sidebar-menu {
		background: #0a1628;
		border-right: 1px solid #1a3a5c;
		overflow-y: auto;
		overflow-x: hidden;
		transition: background 0.2s ease, border-color 0.2s ease;
	}
	
	/* Light Mode Sidebar */
	[data-theme="light"] .sidebar-menu {
		background: #f0f7fc;
		border-right-color: #8eb8d4;
	}
	
	[data-theme="light"] .menu-section-header {
		color: #1a3a5c;
	}
	
	[data-theme="light"] .menu-item a {
		color: #1a3a5c;
	}
	
	[data-theme="light"] .menu-item:hover a {
		background: #e0eef7;
		color: #0a1628;
	}
	
	[data-theme="light"] .menu-item.active a {
		background: rgba(78, 158, 222, 0.2);
		color: #1a3a5c;
	}
	
	[data-theme="light"] .menu-icon-wrapper {
		background: #e0eef7;
	}
	
	[data-theme="light"] .menu-item:hover .menu-icon-wrapper {
		background: #d1e5f4;
	}
	
	[data-theme="light"] .menu-item.active .menu-icon-wrapper {
		background: rgba(78, 158, 222, 0.25);
	}
	
	[data-theme="light"] .menu-icon {
		color: #3d6a8a;
	}
	
	[data-theme="light"] .menu-item:hover .menu-icon {
		color: #1a3a5c;
	}
	
	[data-theme="light"] .menu-item.active .menu-icon {
		color: #1a3a5c;
	}
	
	[data-theme="light"] .mobile-menu-toggle {
		background: #4e9ede;
	}
	
	[data-theme="light"] .sidebar-overlay {
		background: rgba(10, 22, 40, 0.5);
	}
	
	.sidebar-menu::-webkit-scrollbar {
		width: 4px;
	}
	
	.sidebar-menu::-webkit-scrollbar-track {
		background: transparent;
	}
	
	.sidebar-menu::-webkit-scrollbar-thumb {
		background: #1a3a5c;
		border-radius: 4px;
	}

	/* Menu Section Headers */
	.menu-section-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 16px;
		margin: 16px 8px 4px 8px;
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: #5a8aa8;
	}
	
	/* Menu Items */
	.menu-item {
		margin: 2px 8px;
		border-radius: 6px;
	}
	
	.menu-item a {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 10px 12px;
		color: #8eb8d4;
		text-decoration: none;
		border-radius: 6px;
		transition: all 0.15s ease;
		font-size: 13px;
	}
	
	.menu-item:hover a {
		background: #0d1f3c;
		color: #e8f1f8;
	}
	
	/* Active Menu Item */
	.menu-item.active a {
		background: rgba(78, 158, 222, 0.15);
		color: #4e9ede;
		font-weight: 500;
	}
	
	/* Menu Icon Container */
	.menu-icon-wrapper {
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 6px;
		background: #0d1f3c;
		transition: all 0.15s ease;
	}
	
	.menu-item:hover .menu-icon-wrapper {
		background: #1a3a5c;
	}
	
	.menu-item.active .menu-icon-wrapper {
		background: rgba(78, 158, 222, 0.2);
	}
	
	.menu-icon {
		font-size: 16px;
		color: #5a8aa8;
	}
	
	.menu-item:hover .menu-icon {
		color: #e8f1f8;
	}
	
	.menu-item.active .menu-icon {
		color: #4e9ede;
	}
	
	/* Menu Title */
	.menu-title {
		font-size: 13px;
		flex: 1;
	}
	
	/* Inactive/Disabled Items */
	.toolInactive {
		opacity: 0.4;
		pointer-events: none;
	}
	
	/* Mobile Menu Toggle */
	.mobile-menu-toggle {
		display: none;
		position: fixed;
		bottom: 20px;
		right: 20px;
		width: 48px;
		height: 48px;
		background: #4e9ede;
		border-radius: 8px;
		z-index: 1000;
		cursor: pointer;
		align-items: center;
		justify-content: center;
		transition: all 0.15s ease;
		border: none;
	}
	
	.mobile-menu-toggle:hover {
		background: #3d8ed0;
	}
	
	.mobile-menu-toggle i {
		color: white;
		font-size: 20px;
	}
	
	/* Mobile Sidebar Overlay */
	.sidebar-overlay {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(10, 22, 40, 0.85);
		z-index: 998;
		opacity: 0;
		transition: opacity 0.2s ease;
	}
	
	.sidebar-overlay.active {
		opacity: 1;
	}
	
	/* Responsive Styles */
	@media (max-width: 1023px) {
		.sidebar-menu {
			position: fixed;
			top: 56px;
			left: -280px;
			width: 260px;
			height: calc(100vh - 56px) !important;
			z-index: 999;
			transition: left 0.2s ease;
		}
		
		.sidebar-menu.active {
			left: 0;
		}
		
		.mobile-menu-toggle {
			display: flex;
		}
		
		.sidebar-overlay {
			display: block;
		}
	}
</style>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Menu -->
<div class="xl:w-2/12 lg:w-3/12 sidebar-menu hidden lg:block" id="sidebarMenu" style="height: calc(100vh - 56px);">
	
	<nav class="py-2">
		<!-- Etudiants Section -->
		<div class="menu-section-header">
			<span>Étudiants</span>
		</div>
		
		<div class="menu-item <?php if($page == "accueil.php" OR $page == "student.php") { echo "active"; } ?>">
			<a href="./accueil.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-people-fill menu-icon"></i>
				</div>
				<span class="menu-title">Liste des étudiants</span>
			</a>
		</div>

		<div class="menu-item <?php if($page == "creat.student.php") { echo "active"; } ?> <?php if($rg_user['level'] > 2) { echo "toolInactive"; } ?>">
			<a href="./creat.student.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un étudiant</span>
			</a>
		</div>

		<div class="menu-item <?php if($page == "meilleurs-etudiants.php") { echo "active"; } ?>">
			<a href="./meilleurs-etudiants.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-trophy-fill menu-icon"></i>
				</div>
				<span class="menu-title">Meilleurs Étudiants</span>
			</a>
		</div>
		
		<!-- Professeurs Section -->
		<div class="menu-section-header">
			<span>Professeurs</span>
		</div>
		
		<div class="menu-item <?php if($page == "accueil.prof.php" OR $page == "prof.php") { echo "active"; } ?>">
			<a href="./accueil.prof.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-lines-fill menu-icon"></i>
				</div>
				<span class="menu-title">Liste des professeurs</span>
			</a>
		</div>

		<div class="menu-item <?php if($rg_user['level'] > 2) { echo "toolInactive"; } ?>">
			<a href="#" id="addProf">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un professeur</span>
			</a>
		</div>
		
		<!-- Cours Section -->
		<div class="menu-section-header">
			<span>Cours</span>
		</div>
		
		<div class="menu-item <?php if($page == "accueil.cours.php" OR $page == "cours.php") { echo "active"; } ?>">
			<a href="./accueil.cours.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-list-columns-reverse menu-icon"></i>
				</div>
				<span class="menu-title">Liste des cours</span>
			</a>
		</div>

		<div class="menu-item <?php if($rg_user['level'] > 2) { echo "toolInactive"; } ?>">
			<a href="#" id="addCours">
				<div class="menu-icon-wrapper">
					<i class="bi bi-file-earmark-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un cours</span>
			</a>
		</div>

		<!-- Emploi du temps Section -->
		<div class="menu-section-header">
			<span>Emploi du temps</span>
		</div>
		
		<div class="menu-item">
			<a href="./accueil.cours.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-calendar3-week menu-icon"></i>
				</div>
				<span class="menu-title">Emplois du temps</span>
			</a>
		</div>
		
		<!-- Paramètres Section -->
		<div class="menu-section-header">
			<span>Paramètres</span>
		</div>
		
		<div class="menu-item <?php if($page == "settings.php") { echo "active"; } ?>">
			<a href="./settings.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-sliders menu-icon"></i>
				</div>
				<span class="menu-title">Paramètres généraux</span>
			</a>
		</div>
		
	</nav>

</div>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
	<i class="bi bi-list"></i>
</button>

<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		const mobileToggle = document.getElementById('mobileMenuToggle');
		const sidebar = document.getElementById('sidebarMenu');
		const overlay = document.getElementById('sidebarOverlay');
		const toggleIcon = mobileToggle.querySelector('i');
		
		function toggleMenu() {
			sidebar.classList.toggle('active');
			overlay.classList.toggle('active');
			toggleIcon.classList.toggle('bi-list');
			toggleIcon.classList.toggle('bi-x-lg');
		}
		
		mobileToggle.addEventListener('click', toggleMenu);
		overlay.addEventListener('click', toggleMenu);
		
		const menuLinks = sidebar.querySelectorAll('.menu-item a');
		menuLinks.forEach(link => {
			link.addEventListener('click', function() {
				if (window.innerWidth < 1024) toggleMenu();
			});
		});
		
		window.addEventListener('resize', function() {
			if (window.innerWidth >= 1024) {
				sidebar.classList.remove('active');
				overlay.classList.remove('active');
				toggleIcon.classList.add('bi-list');
				toggleIcon.classList.remove('bi-x-lg');
			}
		});
	});
</script>