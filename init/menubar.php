<style type="text/css">
	/* Sidebar Animations & Transitions */
	.sidebar-menu {
		background: linear-gradient(180deg, #334155 0%, #1e293b 100%);
		box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
		overflow-y: auto;
		overflow-x: hidden;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	.sidebar-menu::-webkit-scrollbar {
		width: 6px;
	}
	
	.sidebar-menu::-webkit-scrollbar-track {
		background: rgba(15, 23, 42, 0.5);
		border-radius: 10px;
	}
	
	.sidebar-menu::-webkit-scrollbar-thumb {
		background: linear-gradient(180deg, #06b6d4, #3b82f6);
		border-radius: 10px;
	}
	
	.sidebar-menu::-webkit-scrollbar-thumb:hover {
		background: linear-gradient(180deg, #0891b2, #2563eb);
	}
	
	/* Menu Section Headers */
	.menu-section-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 12px 16px;
		margin: 16px 8px 8px 8px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: #94a3b8;
		position: relative;
	}
	
	.menu-section-header::before {
		content: '';
		width: 3px;
		height: 16px;
		background: linear-gradient(180deg, #06b6d4, #3b82f6);
		border-radius: 2px;
		box-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
	}
	
	.menu-section-header::after {
		content: '';
		flex: 1;
		height: 1px;
		background: linear-gradient(to right, rgba(148, 163, 184, 0.3), transparent);
		margin-left: 8px;
	}
	
	/* Menu Items */
	.menu-item {
		position: relative;
		margin: 4px 8px;
		border-radius: 10px;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		overflow: hidden;
	}
	
	.menu-item::before {
		content: '';
		position: absolute;
		left: 0;
		top: 0;
		height: 100%;
		width: 4px;
		background: linear-gradient(180deg, #06b6d4, #3b82f6);
		transform: scaleX(0);
		transition: transform 0.3s ease;
		border-radius: 0 4px 4px 0;
	}
	
	.menu-item:hover::before {
		transform: scaleX(1);
	}
	
	.menu-item a {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px 16px;
		color: #e2e8f0;
		text-decoration: none;
		border-radius: 10px;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
		z-index: 1;
	}
	
	.menu-item:hover a {
		background: rgba(6, 182, 212, 0.15);
		transform: translateX(4px);
		color: #fff;
	}
	
	/* Active Menu Item */
	.menu-item.active {
		background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(59, 130, 246, 0.2));
		border: 1px solid rgba(6, 182, 212, 0.3);
		box-shadow: 0 4px 12px rgba(6, 182, 212, 0.2);
	}
	
	.menu-item.active::before {
		transform: scaleX(1);
	}
	
	.menu-item.active a {
		color: #fff;
		font-weight: 600;
	}
	
	.menu-item.active .menu-icon {
		transform: scale(1.1);
		color: #06b6d4;
	}
	
	/* Menu Icon Container */
	.menu-icon-wrapper {
		width: 36px;
		height: 36px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 8px;
		background: rgba(51, 65, 85, 0.5);
		transition: all 0.3s ease;
	}
	
	.menu-item:hover .menu-icon-wrapper {
		background: rgba(6, 182, 212, 0.2);
		transform: rotate(5deg) scale(1.05);
	}
	
	.menu-item.active .menu-icon-wrapper {
		background: rgba(6, 182, 212, 0.25);
		box-shadow: 0 0 12px rgba(6, 182, 212, 0.4);
	}
	
	.menu-icon {
		font-size: 18px;
		color: #94a3b8;
		transition: all 0.3s ease;
	}
	
	.menu-item:hover .menu-icon {
		color: #06b6d4;
	}
	
	/* Menu Title */
	.menu-title {
		font-size: 14px;
		font-weight: 500;
		flex: 1;
	}
	
	/* Inactive/Disabled Items */
	.toolInactive {
		opacity: 0.5;
		pointer-events: none;
		cursor: not-allowed;
	}
	
	/* Mobile Menu Toggle */
	.mobile-menu-toggle {
		display: none;
		position: fixed;
		bottom: 24px;
		right: 24px;
		width: 56px;
		height: 56px;
		background: linear-gradient(135deg, #06b6d4, #3b82f6);
		border-radius: 50%;
		box-shadow: 0 4px 20px rgba(6, 182, 212, 0.4);
		z-index: 1000;
		cursor: pointer;
		align-items: center;
		justify-content: center;
		transition: all 0.3s ease;
		border: none;
	}
	
	.mobile-menu-toggle:hover {
		transform: scale(1.1);
		box-shadow: 0 6px 24px rgba(6, 182, 212, 0.6);
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
		background: rgba(0, 0, 0, 0.5);
		backdrop-filter: blur(4px);
		z-index: 998;
		opacity: 0;
		transition: opacity 0.3s ease;
	}
	
	.sidebar-overlay.active {
		opacity: 1;
	}
	
	/* Responsive Styles */
	@media (max-width: 1023px) {
		.sidebar-menu {
			position: fixed;
			top: 56px;
			left: -320px;
			width: 280px;
			height: calc(100vh - 56px);
			z-index: 999;
			transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		}
		
		.sidebar-menu.active {
			left: 0;
			box-shadow: 4px 0 20px rgba(0, 0, 0, 0.5);
		}
		
		.mobile-menu-toggle {
			display: flex;
		}
		
		.sidebar-overlay {
			display: block;
		}
	}
	
	/* Badge for items count */
	.menu-badge {
		padding: 2px 8px;
		background: linear-gradient(135deg, #06b6d4, #3b82f6);
		color: white;
		font-size: 11px;
		font-weight: 700;
		border-radius: 12px;
		box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
	}
	
	/* Animation on load */
	@keyframes slideInLeft {
		from {
			opacity: 0;
			transform: translateX(-20px);
		}
		to {
			opacity: 1;
			transform: translateX(0);
		}
	}
	
	.menu-item {
		animation: slideInLeft 0.3s ease forwards;
		opacity: 0;
	}
	
	.menu-item:nth-child(1) { animation-delay: 0.05s; }
	.menu-item:nth-child(2) { animation-delay: 0.1s; }
	.menu-item:nth-child(3) { animation-delay: 0.15s; }
	.menu-item:nth-child(4) { animation-delay: 0.2s; }
	.menu-item:nth-child(5) { animation-delay: 0.25s; }
	.menu-item:nth-child(6) { animation-delay: 0.3s; }
	.menu-item:nth-child(7) { animation-delay: 0.35s; }
	.menu-item:nth-child(8) { animation-delay: 0.4s; }
	.menu-item:nth-child(9) { animation-delay: 0.45s; }
	.menu-item:nth-child(10) { animation-delay: 0.5s; }
</style>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Menu -->
<div class="xl:w-2/12 lg:w-3/12 sidebar-menu hidden lg:block" id="sidebarMenu" style="height: calc(100vh - 48px);">
	
	<nav class="py-2">
		<!-- Etudiants Section -->
		<div class="menu-section-header">
			<i class="bi bi-mortarboard-fill text-cyan-400"></i>
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

		<div class="menu-item <?php if($rg_user['level'] > 2) { echo "toolInactive"; } ?>">
			<a href="./creat.student.php">
				<div class="menu-icon-wrapper">
					<i class="bi bi-person-plus-fill menu-icon"></i>
				</div>
				<span class="menu-title">Créer un étudiant</span>
			</a>
		</div>
		
		<!-- Professeurs Section -->
		<div class="menu-section-header">
			<i class="bi bi-person-badge-fill text-purple-400"></i>
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
			<i class="bi bi-book-fill text-blue-400"></i>
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
		
		<!-- Paramètres Section -->
		<div class="menu-section-header">
			<i class="bi bi-gear-fill text-orange-400"></i>
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
	// Mobile menu toggle functionality
	document.addEventListener('DOMContentLoaded', function() {
		const mobileToggle = document.getElementById('mobileMenuToggle');
		const sidebar = document.getElementById('sidebarMenu');
		const overlay = document.getElementById('sidebarOverlay');
		const toggleIcon = mobileToggle.querySelector('i');
		
		function toggleMenu() {
			sidebar.classList.toggle('active');
			overlay.classList.toggle('active');
			
			// Change icon
			if (sidebar.classList.contains('active')) {
				toggleIcon.classList.remove('bi-list');
				toggleIcon.classList.add('bi-x-lg');
			} else {
				toggleIcon.classList.remove('bi-x-lg');
				toggleIcon.classList.add('bi-list');
			}
		}
		
		mobileToggle.addEventListener('click', toggleMenu);
		overlay.addEventListener('click', toggleMenu);
		
		// Close menu when clicking a link
		const menuLinks = sidebar.querySelectorAll('.menu-item a');
		menuLinks.forEach(link => {
			link.addEventListener('click', function() {
				if (window.innerWidth < 1024) {
					toggleMenu();
				}
			});
		});
		
		// Handle window resize
		window.addEventListener('resize', function() {
			if (window.innerWidth >= 1024) {
				sidebar.classList.remove('active');
				overlay.classList.remove('active');
				toggleIcon.classList.remove('bi-x-lg');
				toggleIcon.classList.add('bi-list');
			}
		});
	});
</script>