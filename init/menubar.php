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
		width: 56px;
		height: 56px;
		background: #4e9ede;
		border-radius: 50%;
		z-index: 1001;
		cursor: pointer;
		align-items: center;
		justify-content: center;
		transition: all 0.15s ease;
		border: none;
		box-shadow: 0 4px 15px rgba(78, 158, 222, 0.4);
	}
	
	.mobile-menu-toggle:hover {
		background: #3d8ed0;
		transform: scale(1.05);
	}
	
	.mobile-menu-toggle:active {
		transform: scale(0.95);
	}
	
	.mobile-menu-toggle i {
		color: white;
		font-size: 24px;
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
		visibility: hidden;
		transition: opacity 0.3s ease, visibility 0.3s ease;
	}
	
	.sidebar-overlay.active {
		opacity: 1;
		visibility: visible;
	}
	
	/* Desktop Styles - Sidebar visible in flow */
	@media (min-width: 1024px) {
		.sidebar-menu {
			position: relative !important;
			left: 0 !important;
			display: block !important;
			transform: translateX(0) !important;
		}
		
		.mobile-menu-toggle {
			display: none !important;
		}
		
		.sidebar-overlay {
			display: none !important;
		}
	}
	
	/* Mobile Styles - Sidebar hidden by default, slides in */
	@media (max-width: 1023px) {
		.sidebar-menu {
			position: fixed !important;
			top: 56px !important;
			left: 0 !important;
			transform: translateX(-100%) !important;
			width: 270px !important;
			height: calc(100vh - 56px) !important;
			z-index: 999 !important;
			transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			display: block !important;
			overflow-y: auto !important;
			box-shadow: none !important;
			visibility: hidden;
		}
		
		.sidebar-menu.active {
			transform: translateX(0) !important;
			box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3) !important;
			visibility: visible;
		}
		
		.mobile-menu-toggle {
			display: flex !important;
		}
		
		.sidebar-overlay {
			display: block !important;
		}
	}
</style>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Menu -->
<div class="xl:w-2/12 lg:w-3/12 sidebar-menu" id="sidebarMenu" style="height: calc(100vh - 56px);">
	
	<nav class="py-2">
		<!-- Inscription -->
		<div class="menu-item <?php if(isRoute('/inscription')) { echo "active"; } ?> <?php if($rg_user['level'] > 6) { echo "toolInactive"; } ?>">
			<a href="<?=$app_base?>/inscription/inscription" target="_blank">
				<div class="menu-icon-wrapper" style="background: rgba(14, 165, 233, 0.15);">
					<i class="bi bi-person-fill-add menu-icon" style="color: #0ea5e9;"></i>
				</div>
				<span class="menu-title">Inscription</span>
			</a>
		</div>

		<!-- Etudiants Section -->
		<div class="menu-section-header">
			<span>Étudiants</span>
		</div>
		
		<div class="menu-item <?php if(isRoute('/dashboard')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/dashboard">
				<div class="menu-icon-wrapper">
					<i class="bi bi-people-fill menu-icon"></i>
				</div>
				<span class="menu-title">Liste des étudiants</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/students/create')) { echo "active"; } ?> <?php if($rg_user['level'] > 3) { echo "toolInactive"; } ?>">
			<a href="<?=$app_base?>/students/create">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un étudiant</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/deans-list')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/deans-list">
				<div class="menu-icon-wrapper">
					<i class="bi bi-star-fill menu-icon"></i>
				</div>
				<span class="menu-title">Dean's List</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/top-students')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/top-students">
				<div class="menu-icon-wrapper">
					<i class="bi bi-trophy-fill menu-icon"></i>
				</div>
				<span class="menu-title">Top Student</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/session-rankings')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/session-rankings">
				<div class="menu-icon-wrapper">
					<i class="bi bi-bar-chart-line-fill menu-icon"></i>
				</div>
				<span class="menu-title">Classement session</span>
			</a>
		</div>
		
		<!-- Professeurs Section -->
		<div class="menu-section-header">
			<span>Professeurs</span>
		</div>
		
		<div class="menu-item <?php if(isRoute(['/professors', '/professor'])) { echo "active"; } ?>">
			<a href="<?=$app_base?>/professors">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-lines-fill menu-icon"></i>
				</div>
				<span class="menu-title">Liste des professeurs</span>
			</a>
		</div>

		<div class="menu-item <?php if($rg_user['level'] > 3) { echo "toolInactive"; } ?>">
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
		
		<div class="menu-item <?php if(isRoute(['/courses', '/course'])) { echo "active"; } ?>">
			<a href="<?=$app_base?>/courses">
				<div class="menu-icon-wrapper">
					<i class="bi bi-list-columns-reverse menu-icon"></i>
				</div>
				<span class="menu-title">Liste des cours</span>
			</a>
		</div>

		<div class="menu-item <?php if($rg_user['level'] > 3) { echo "toolInactive"; } ?>">
			<a href="#" id="addCours">
				<div class="menu-icon-wrapper">
					<i class="bi bi-file-earmark-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un cours</span>
			</a>
		</div>

		<!-- Application Section -->
		<div class="menu-section-header">
			<span>Application</span>
		</div>
		
		<div class="menu-item <?php if(isRoute('/schedule')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/schedule">
				<div class="menu-icon-wrapper">
					<i class="bi bi-calendar3-week menu-icon"></i>
				</div>
				<span class="menu-title">Emplois du temps</span>
			</a>
		</div>

		<div class="menu-item <?php if(isRoute('/queue')) { echo "active"; } ?> <?php if($rg_user['level'] > 1) { echo "toolInactive"; } ?>">
			<a href="<?=$app_base?>/queue">
				<div class="menu-icon-wrapper" style="background: rgba(14, 165, 233, 0.15);">
					<i class="bi bi-people-fill menu-icon" style="color: #0ea5e9;"></i>
				</div>
				<span class="menu-title">File d'attente</span>
			</a>
		</div>

		<!-- Communication Section -->
		<div class="menu-section-header">
			<span>Communication</span>
		</div>
		
		<div class="menu-item <?php if(isRoute('/news')) { echo "active"; } ?> <?php if($rg_user['level'] > 3) { echo "toolInactive"; } ?>">
			<a href="<?=$app_base?>/news">
				<div class="menu-icon-wrapper">
					<i class="bi bi-megaphone-fill menu-icon"></i>
				</div>
				<span class="menu-title">Actualités</span>
			</a>
		</div>

		<!-- Paramètres Section -->
		<div class="menu-section-header">
			<span>Paramètres</span>
		</div>
		
		<div class="menu-item <?php if(isRoute('/settings')) { echo "active"; } ?>">
			<a href="<?=$app_base?>/settings">
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
		
		if (!mobileToggle || !sidebar || !overlay) {
			console.error('Menu elements not found');
			return;
		}
		
		const toggleIcon = mobileToggle.querySelector('i');
		
		function openMenu() {
			sidebar.classList.add('active');
			overlay.classList.add('active');
			if (toggleIcon) {
				toggleIcon.classList.remove('bi-list');
				toggleIcon.classList.add('bi-x-lg');
			}
			document.body.style.overflow = 'hidden';
		}
		
		function closeMenu() {
			sidebar.classList.remove('active');
			overlay.classList.remove('active');
			if (toggleIcon) {
				toggleIcon.classList.add('bi-list');
				toggleIcon.classList.remove('bi-x-lg');
			}
			document.body.style.overflow = '';
		}
		
		function toggleMenu() {
			if (sidebar.classList.contains('active')) {
				closeMenu();
			} else {
				openMenu();
			}
		}
		
		mobileToggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			toggleMenu();
		});
		
		overlay.addEventListener('click', function(e) {
			e.preventDefault();
			closeMenu();
		});
		
		// Close menu when clicking a link
		const menuLinks = sidebar.querySelectorAll('.menu-item a');
		menuLinks.forEach(link => {
			link.addEventListener('click', function() {
				if (window.innerWidth < 1024) {
					closeMenu();
				}
			});
		});
		
		// Handle window resize
		window.addEventListener('resize', function() {
			if (window.innerWidth >= 1024) {
				closeMenu();
			}
		});
	});
</script>