<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php 
	// SÉCURITÉ: Cette page est réservée aux administrateurs et registraires
	requireLevel(ROLE_REGISTRAR, './accueil');
	?>
	<title>Gestion des Utilisateurs</title>
	<style>
		/* ========== SHADCN UI STYLE ========== */
		
		/* Variables CSS */
		:root {
			--background: 222.2 84% 4.9%;
			--foreground: 210 40% 98%;
			--card: 222.2 84% 4.9%;
			--card-foreground: 210 40% 98%;
			--primary: 199 89% 48%;
			--primary-foreground: 222.2 47.4% 11.2%;
			--secondary: 217.2 32.6% 17.5%;
			--secondary-foreground: 210 40% 98%;
			--muted: 217.2 32.6% 17.5%;
			--muted-foreground: 215 20.2% 65.1%;
			--accent: 217.2 32.6% 17.5%;
			--accent-foreground: 210 40% 98%;
			--destructive: 0 62.8% 30.6%;
			--destructive-foreground: 210 40% 98%;
			--border: 217.2 32.6% 17.5%;
			--input: 217.2 32.6% 17.5%;
			--ring: 199 89% 48%;
			--radius: 0.5rem;
		}

		/* Card Component */
		.shad-card {
			background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
			border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 0.75rem;
			backdrop-filter: blur(10px);
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
		}

		.shad-card-header {
			padding: 1.5rem 1.5rem 0;
		}

		.shad-card-title {
			font-size: 1.25rem;
			font-weight: 600;
			color: #f1f5f9;
			letter-spacing: -0.025em;
		}

		.shad-card-description {
			color: #94a3b8;
			font-size: 0.875rem;
			margin-top: 0.25rem;
		}

		.shad-card-content {
			padding: 1.5rem;
		}

		/* Button Component */
		.shad-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			padding: 0.5rem 1rem;
			font-size: 0.875rem;
			font-weight: 500;
			border-radius: 0.375rem;
			transition: all 0.2s ease;
			cursor: pointer;
			border: none;
			outline: none;
		}

		.shad-btn:focus-visible {
			outline: 2px solid #0ea5e9;
			outline-offset: 2px;
		}

		.shad-btn-primary {
			background: linear-gradient(135deg, #0ea5e9, #0284c7);
			color: white;
			box-shadow: 0 1px 3px rgba(14, 165, 233, 0.3);
		}

		.shad-btn-primary:hover {
			background: linear-gradient(135deg, #0284c7, #0369a1);
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
		}

		.shad-btn-primary:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			transform: none;
		}

		.shad-btn-secondary {
			background: rgba(51, 65, 85, 0.8);
			color: #e2e8f0;
			border: 1px solid rgba(71, 85, 105, 0.5);
		}

		.shad-btn-secondary:hover {
			background: rgba(71, 85, 105, 0.8);
		}

		.shad-btn-ghost {
			background: transparent;
			color: #94a3b8;
		}

		.shad-btn-ghost:hover {
			background: rgba(51, 65, 85, 0.5);
			color: #f1f5f9;
		}

		.shad-btn-icon {
			width: 2.25rem;
			height: 2.25rem;
			padding: 0;
		}

		/* Input Component */
		.shad-dialog .shad-input,
		.shad-input {
			width: 100%;
			padding: 0.5rem 0.75rem !important;
			font-size: 0.875rem !important;
			background: rgba(30, 41, 59, 0.95) !important;
			border: 1px solid rgba(71, 85, 105, 0.8) !important;
			border-radius: 0.375rem;
			color: #ffffff !important;
			-webkit-text-fill-color: #ffffff !important;
			transition: all 0.2s ease;
			opacity: 1;
		}

		.shad-dialog .shad-input:focus,
		.shad-input:focus {
			outline: none;
			border-color: #0ea5e9 !important;
			box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
			background: rgba(30, 41, 59, 1) !important;
			color: #ffffff !important;
			-webkit-text-fill-color: #ffffff !important;
		}

		.shad-dialog .shad-input::placeholder,
		.shad-input::placeholder {
			color: #94a3b8 !important;
			-webkit-text-fill-color: #94a3b8 !important;
			opacity: 1;
		}

		/* Fix autofill styles */
		.shad-input:-webkit-autofill,
		.shad-input:-webkit-autofill:hover,
		.shad-input:-webkit-autofill:focus {
			-webkit-text-fill-color: #ffffff !important;
			-webkit-box-shadow: 0 0 0px 1000px rgba(30, 41, 59, 0.95) inset !important;
			transition: background-color 5000s ease-in-out 0s;
		}

		/* Fix select inside dialog */
		.shad-dialog .shad-select,
		.shad-select {
			color: #ffffff !important;
			-webkit-text-fill-color: #ffffff !important;
		}

		.shad-input-error {
			border-color: #ef4444 !important;
			background: rgba(239, 68, 68, 0.1);
		}

		.shad-input-success {
			border-color: #22c55e !important;
			background: rgba(34, 197, 94, 0.1);
		}

		/* Label Component */
		.shad-label {
			display: block;
			font-size: 0.875rem;
			font-weight: 500;
			color: #e2e8f0;
			margin-bottom: 0.375rem;
		}

		/* Select Component */
		.shad-select {
			width: 100%;
			padding: 0.5rem 0.75rem;
			font-size: 0.875rem;
			background: rgba(15, 23, 42, 0.6);
			border: 1px solid rgba(51, 65, 85, 0.8);
			border-radius: 0.375rem;
			color: #f1f5f9;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.shad-select:focus {
			outline: none;
			border-color: #0ea5e9;
			box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
		}

		.shad-select option {
			background: #1e293b;
			color: #f1f5f9;
		}

		/* Badge Component */
		.shad-badge {
			display: inline-flex;
			align-items: center;
			padding: 0.125rem 0.625rem;
			font-size: 0.75rem;
			font-weight: 500;
			border-radius: 9999px;
			transition: all 0.2s ease;
		}

		.shad-badge-success {
			background: rgba(34, 197, 94, 0.2);
			color: #22c55e;
			border: 1px solid rgba(34, 197, 94, 0.3);
		}

		.shad-badge-danger {
			background: rgba(239, 68, 68, 0.2);
			color: #ef4444;
			border: 1px solid rgba(239, 68, 68, 0.3);
		}

		/* Avatar Component */
		.shad-avatar {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 2.5rem;
			height: 2.5rem;
			border-radius: 9999px;
			overflow: hidden;
			background: linear-gradient(135deg, #0ea5e9, #8b5cf6);
			flex-shrink: 0;
		}

		.shad-avatar img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.shad-avatar-fallback {
			font-size: 0.875rem;
			font-weight: 600;
			color: white;
			text-transform: uppercase;
		}

		/* Table Component */
		.shad-table {
			width: 100%;
			border-collapse: separate;
			border-spacing: 0;
		}

		.shad-table thead {
			background: rgba(30, 41, 59, 0.8);
		}

		.shad-table th {
			padding: 0.75rem 1rem;
			text-align: left;
			font-size: 0.75rem;
			font-weight: 600;
			color: #94a3b8;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			border-bottom: 1px solid rgba(51, 65, 85, 0.5);
		}

		.shad-table td {
			padding: 0.75rem 1rem;
			font-size: 0.875rem;
			color: #e2e8f0;
			border-bottom: 1px solid rgba(51, 65, 85, 0.3);
		}

		.shad-table tbody tr {
			transition: background 0.15s ease;
		}

		.shad-table tbody tr:hover {
			background: rgba(51, 65, 85, 0.3);
		}

		/* Dialog/Modal Component */
		.shad-dialog-overlay {
			position: fixed;
			inset: 0;
			background: rgba(0, 0, 0, 0.7);
			backdrop-filter: blur(4px);
			z-index: 50;
			display: none;
			animation: fadeIn 0.2s ease;
		}

		.shad-dialog-overlay.active {
			display: flex;
			align-items: center;
			justify-content: center;
		}

		@keyframes fadeIn {
			from { opacity: 0; }
			to { opacity: 1; }
		}

		@keyframes slideIn {
			from { opacity: 0; transform: scale(0.95) translateY(-10px); }
			to { opacity: 1; transform: scale(1) translateY(0); }
		}

		.shad-dialog {
			background: linear-gradient(145deg, #1e293b, #0f172a);
			border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 0.75rem;
			width: 100%;
			max-width: 600px;
			max-height: 90vh;
			overflow-y: auto;
			box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
			animation: slideIn 0.3s ease;
		}

		.shad-dialog-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 1.25rem 1.5rem;
			border-bottom: 1px solid rgba(51, 65, 85, 0.5);
		}

		.shad-dialog-title {
			font-size: 1.125rem;
			font-weight: 600;
			color: #f1f5f9;
		}

		.shad-dialog-body {
			padding: 1.5rem;
		}

		.shad-dialog-footer {
			display: flex;
			justify-content: flex-end;
			gap: 0.75rem;
			padding: 1rem 1.5rem;
			border-top: 1px solid rgba(51, 65, 85, 0.5);
			background: rgba(15, 23, 42, 0.5);
		}

		/* Switch/Toggle Component */
		.shad-switch {
			position: relative;
			display: inline-flex;
			align-items: center;
			gap: 0.75rem;
			cursor: pointer;
		}

		.shad-switch input {
			position: absolute;
			opacity: 0;
			width: 0;
			height: 0;
		}

		.shad-switch-track {
			width: 2.75rem;
			height: 1.5rem;
			background: rgba(51, 65, 85, 0.8);
			border-radius: 9999px;
			transition: all 0.2s ease;
			position: relative;
		}

		.shad-switch-thumb {
			position: absolute;
			top: 2px;
			left: 2px;
			width: 1.25rem;
			height: 1.25rem;
			background: white;
			border-radius: 9999px;
			transition: all 0.2s ease;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
		}

		.shad-switch input:checked + .shad-switch-track {
			background: linear-gradient(135deg, #0ea5e9, #0284c7);
		}

		.shad-switch input:checked + .shad-switch-track .shad-switch-thumb {
			transform: translateX(1.25rem);
		}

		.shad-switch-label {
			font-size: 0.875rem;
			color: #e2e8f0;
		}

		/* Photo Upload */
		.photo-upload-container {
			position: relative;
			width: 100%;
			aspect-ratio: 1;
			border-radius: 0.75rem;
			overflow: hidden;
			background: rgba(30, 41, 59, 0.5);
			border: 2px dashed rgba(51, 65, 85, 0.8);
			transition: all 0.2s ease;
			cursor: pointer;
			display: block;
		}

		.photo-upload-container:hover {
			border-color: #0ea5e9;
			background: rgba(14, 165, 233, 0.1);
		}

		.photo-upload-container img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.photo-upload-placeholder {
			position: absolute;
			inset: 0;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			color: #64748b;
		}

		.photo-upload-placeholder i {
			font-size: 2.5rem;
			margin-bottom: 0.5rem;
		}

		/* Separator */
		.shad-separator {
			height: 1px;
			background: linear-gradient(to right, transparent, rgba(51, 65, 85, 0.5), transparent);
			margin: 1rem 0;
		}

		/* Form Group */
		.form-group { margin-bottom: 1rem; }

		/* Header Actions */
		.header-actions {
			display: flex;
			align-items: center;
			gap: 1rem;
			padding: 1rem 1.5rem;
			background: rgba(15, 23, 42, 0.5);
			border-bottom: 1px solid rgba(51, 65, 85, 0.3);
		}

		/* Search Input */
		.search-wrapper {
			position: relative;
			flex: 1;
			max-width: 400px;
		}

		.search-wrapper i {
			position: absolute;
			left: 0.75rem;
			top: 50%;
			transform: translateY(-50%);
			color: #64748b;
		}

		.search-wrapper .shad-input { padding-left: 2.5rem; }

		/* Stats Cards */
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 1rem;
			margin-bottom: 1.5rem;
		}

		.stat-card {
			background: rgba(30, 41, 59, 0.5);
			border: 1px solid rgba(51, 65, 85, 0.3);
			border-radius: 0.5rem;
			padding: 1rem;
			display: flex;
			align-items: center;
			gap: 1rem;
		}

		.stat-icon {
			width: 3rem;
			height: 3rem;
			border-radius: 0.5rem;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.25rem;
		}

		.stat-icon.primary { background: rgba(14, 165, 233, 0.2); color: #0ea5e9; }
		.stat-icon.success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
		.stat-icon.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
		.stat-icon.danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

		.stat-content h4 { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
		.stat-content p { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; }

		/* Role Badges */
		.role-superadmin { background: linear-gradient(135deg, rgba(220, 38, 38, 0.2), rgba(185, 28, 28, 0.2)); color: #ef4444; border: 1px solid rgba(220, 38, 38, 0.3); }
		.role-admin { background: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(139, 92, 246, 0.2)); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
		.role-registrar { background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(6, 182, 212, 0.2)); color: #0ea5e9; border: 1px solid rgba(14, 165, 233, 0.3); }
		.role-comptabilite { background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2)); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
		.role-media { background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(219, 39, 119, 0.2)); color: #ec4899; border: 1px solid rgba(236, 72, 153, 0.3); }
		.role-chef-mention { background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.2)); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
		.role-teacher { background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.2)); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
		.role-student { background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(99, 102, 241, 0.2)); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }

		/* Space utility */
		.space-y-4 > * + * { margin-top: 1rem; }
	</style>
</head>
<body class="<?=$bg_three_color?> text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12">
				<div class="back p-4" style="height: calc(100vh - 60px); overflow-y: auto;">
					
					<!-- Header avec Stats -->
					<?php 
					$totalUsers = $dtb->query("SELECT COUNT(*) FROM compt_utilisateur")->fetchColumn();
					$activeUsers = $dtb->query("SELECT COUNT(*) FROM compt_utilisateur WHERE etat = 1")->fetchColumn();
					$adminCount = $dtb->query("SELECT COUNT(*) FROM compt_utilisateur WHERE level IN (1,2,3)")->fetchColumn();
					?>
					
					<div class="stats-grid">
						<div class="stat-card">
							<div class="stat-icon primary"><i class="bi-people-fill"></i></div>
							<div class="stat-content">
								<h4>Total Utilisateurs</h4>
								<p><?= $totalUsers ?></p>
							</div>
						</div>
						<div class="stat-card">
							<div class="stat-icon success"><i class="bi-person-check-fill"></i></div>
							<div class="stat-content">
								<h4>Comptes Actifs</h4>
								<p><?= $activeUsers ?></p>
							</div>
						</div>
						<div class="stat-card">
							<div class="stat-icon warning"><i class="bi-shield-fill-check"></i></div>
							<div class="stat-content">
								<h4>Administrateurs</h4>
								<p><?= $adminCount ?></p>
							</div>
						</div>
						<div class="stat-card">
							<div class="stat-icon danger"><i class="bi-person-x-fill"></i></div>
							<div class="stat-content">
								<h4>Inactifs</h4>
								<p><?= $totalUsers - $activeUsers ?></p>
							</div>
						</div>
					</div>

					<!-- Main Card -->
					<div class="shad-card">
						<div class="header-actions">
							<div class="search-wrapper">
								<i class="bi-search"></i>
								<input type="text" id="searchUsers" class="shad-input" placeholder="Rechercher un utilisateur...">
							</div>
							<button id="addUserBtn" class="shad-btn shad-btn-primary">
								<i class="bi-person-plus-fill"></i>
								Nouvel Utilisateur
							</button>
						</div>

						<div class="shad-card-content" style="max-height: calc(100vh - 320px); overflow-y: auto;">
							<table class="shad-table" id="usersTable">
								<thead>
									<tr>
										<th style="width: 50px;"></th>
										<th>Utilisateur</th>
										<th>Identifiant</th>
										<th>Rôle</th>
										<th>Poste</th>
										<th>Statut</th>
										<th style="width: 100px;">Actions</th>
									</tr>
								</thead>
								<tbody>
<?php 
	$utilisateur = $dtb->query("SELECT * FROM compt_utilisateur ORDER BY level, nom");
	while($user = $utilisateur->fetch()){
		$user_id = $user['id'];
		
		// Badge selon le niveau
		$roleBadgeClass = 'shad-badge role-student';
		$roleLabel = $user['privilege'];
		switch($user['level']) {
			case 1: $roleBadgeClass = 'shad-badge role-superadmin'; $roleLabel = 'Superadmin'; break;
			case 2: $roleBadgeClass = 'shad-badge role-admin'; $roleLabel = 'Administrateur'; break;
			case 3: $roleBadgeClass = 'shad-badge role-registrar'; $roleLabel = 'Registraire'; break;
			case 4: $roleBadgeClass = 'shad-badge role-comptabilite'; $roleLabel = 'Comptabilité'; break;
			case 5: $roleBadgeClass = 'shad-badge role-media'; $roleLabel = 'Média'; break;
			case 6: $roleBadgeClass = 'shad-badge role-chef-mention'; $roleLabel = 'Chef de mention'; break;
			case 7: $roleBadgeClass = 'shad-badge role-teacher'; $roleLabel = 'Professeur'; break;
			case 8: $roleBadgeClass = 'shad-badge role-student'; $roleLabel = 'Étudiant'; break;
		}
		
		$initials = strtoupper(substr($user['nom'] ?? 'U', 0, 1) . substr($user['prenom'] ?? '', 0, 1));
?>
									<tr data-user-id="<?=$user_id?>" data-name="<?=strtolower($user['nom'].' '.$user['prenom'].' '.$user['pseudo'])?>">
										<td>
											<div class="shad-avatar">
												<?php if (!empty($user['photos'])): ?>
													<img src="../app/photosuser/<?=$user['photos']?>" alt="<?=$user['nom']?>">
												<?php else: ?>
													<span class="shad-avatar-fallback"><?=$initials?></span>
												<?php endif; ?>
											</div>
										</td>
										<td>
											<div>
												<div class="font-medium"><?=$user['nom']?> <?=$user['prenom']?></div>
												<div class="text-xs text-slate-400"><?=$user['mail'] ?: 'Pas d\'email'?></div>
											</div>
										</td>
										<td>
											<code class="text-cyan-400 bg-slate-800 px-2 py-0.5 rounded text-xs"><?=$user['pseudo']?></code>
										</td>
										<td><span class="<?=$roleBadgeClass?>"><?=$roleLabel?></span></td>
										<td class="text-slate-300"><?=$user['post'] ?: '-'?></td>
										<td>
											<?php if($user['etat'] == 1): ?>
												<span class="shad-badge shad-badge-success"><i class="bi-check-circle-fill mr-1"></i> Actif</span>
											<?php else: ?>
												<span class="shad-badge shad-badge-danger"><i class="bi-x-circle-fill mr-1"></i> Inactif</span>
											<?php endif; ?>
										</td>
										<td>
											<button class="shad-btn shad-btn-ghost shad-btn-icon updateUserBtn" data-user-id="<?=$user_id?>" title="Modifier">
												<i class="bi-pencil"></i>
											</button>
										</td>
									</tr>

<!-- Modal Update User -->
<div class="shad-dialog-overlay" id="updateDialog<?=$user_id?>">
	<div class="shad-dialog">
		<?php $userData = DB::find('compt_utilisateur', $user_id); ?>
		<form method="post" action="<?=$app_base?>/app/.user/updateUser?rg_id=<?=$rg_id;?>&id=<?=$user_id?>" enctype="multipart/form-data" class="updateUserForm">
			<?= csrf_field() ?>
			<div class="shad-dialog-header">
				<h3 class="shad-dialog-title"><i class="bi-person-gear mr-2 text-cyan-400"></i>Modifier l'utilisateur</h3>
				<button type="button" class="shad-btn shad-btn-ghost shad-btn-icon closeDialogBtn"><i class="bi-x-lg"></i></button>
			</div>
			
			<div class="shad-dialog-body">
				<div class="flex gap-6">
					<div class="w-1/3">
						<label class="photo-upload-container" for="photosuser<?=$user_id?>">
							<?php if (!empty($userData['photos'])): ?>
								<img src="../app/photosuser/<?=$userData['photos']?>" id="photoPreview<?=$user_id?>">
							<?php else: ?>
								<div class="photo-upload-placeholder" id="photoPlaceholder<?=$user_id?>">
									<i class="bi-camera"></i>
									<span class="text-sm">Choisir photo</span>
								</div>
							<?php endif; ?>
						</label>
						<input type="file" accept=".jpg,.png,.JPG,.PNG" name="photos<?=$user_id?>" id="photosuser<?=$user_id?>" class="hidden" onchange="previewPhoto(this, <?=$user_id?>)">
						<input type="hidden" name="oldPhotos<?=$user_id?>" value="<?=$userData['photos']?>">
						
						<div class="shad-separator"></div>
						
						<label class="shad-switch">
							<input type="checkbox" name="etat<?=$user_id?>" id="etatSwitch<?=$user_id?>" <?= $userData['etat'] == 1 ? 'checked' : '' ?>>
							<div class="shad-switch-track"><div class="shad-switch-thumb"></div></div>
							<span class="shad-switch-label" id="etatLabel<?=$user_id?>"><?= $userData['etat'] == 1 ? 'Actif' : 'Inactif' ?></span>
						</label>
					</div>
					
					<div class="w-2/3 space-y-4">
						<div class="grid grid-cols-2 gap-4">
							<div class="form-group">
								<label class="shad-label">Nom</label>
								<input class="shad-input" type="text" name="nom<?=$user_id?>" value="<?=$userData['nom']?>" required>
							</div>
							<div class="form-group">
								<label class="shad-label">Prénom</label>
								<input class="shad-input" type="text" name="prenom<?=$user_id?>" value="<?=$userData['prenom']?>">
							</div>
						</div>
						
						<div class="form-group">
							<label class="shad-label">Poste</label>
							<input class="shad-input" type="text" name="post<?=$user_id?>" value="<?=$userData['post']?>">
						</div>
						
						<div class="form-group">
							<label class="shad-label">Email</label>
							<input class="shad-input" type="email" name="mail<?=$user_id?>" value="<?=$userData['mail']?>">
						</div>
						
						<div class="shad-separator"></div>
						
						<div class="form-group">
							<label class="shad-label">Privilège</label>
							<select class="shad-select" name="level<?=$user_id?>">
									<option value="1" <?= $userData['level'] == 1 ? 'selected' : '' ?>>👑 Superadmin</option>
									<option value="2" <?= $userData['level'] == 2 ? 'selected' : '' ?>>🛡️ Administrateur</option>
									<option value="3" <?= $userData['level'] == 3 ? 'selected' : '' ?>>📋 Registraire</option>
									<option value="4" <?= $userData['level'] == 4 ? 'selected' : '' ?>>💰 Comptabilité</option>
									<option value="5" <?= $userData['level'] == 5 ? 'selected' : '' ?>>📷 Média</option>
									<option value="6" <?= $userData['level'] == 6 ? 'selected' : '' ?>>🏅 Chef de mention</option>
									<option value="7" <?= $userData['level'] == 7 ? 'selected' : '' ?>>👨‍🏫 Professeur</option>
									<option value="8" <?= $userData['level'] == 8 ? 'selected' : '' ?>>🎓 Étudiant</option>
								</select>
							</div>

							<?php if ($userData['level'] == 8 || ($userData['privilege'] ?? '') === 'student'): ?>
							<div class="form-group">
								<label class="shad-label">Matricule étudiant lié</label>
								<input type="text" class="shad-input" id="studentSearchEdit<?=$user_id?>" placeholder="🔍 Rechercher par nom ou matricule..." autocomplete="off" data-user-id="<?=$user_id?>">
								<div class="studentSearchResultsEdit" id="studentSearchResultsEdit<?=$user_id?>" data-user-id="<?=$user_id?>" style="max-height:180px;overflow-y:auto;margin-top:4px;display:none;background:#0f1729;border:1px solid rgba(14,165,233,0.2);border-radius:8px;"></div>
								<input type="hidden" name="student_id<?=$user_id?>" id="selectedStudentIdEdit<?=$user_id?>" value="<?=htmlspecialchars($userData['student_id'] ?? '')?>">
								<?php if (!empty($userData['student_id'])):
									$stInfo = $dtb->prepare("SELECT student_nom, student_prenom FROM tbl_2024_etudiant WHERE student_id = :sid LIMIT 1");
									$stInfo->execute(['sid' => $userData['student_id']]);
									$stRow = $stInfo->fetch();
								?>
								<div style="margin-top:6px;padding:6px 10px;background:rgba(14,165,233,0.1);border:1px solid rgba(14,165,233,0.3);border-radius:6px;font-size:13px;color:#e8f1f8;">
									✅ Lié à: <b><?=htmlspecialchars($userData['student_id'])?></b>
									<?php if ($stRow): ?> — <?=htmlspecialchars($stRow['student_nom'].' '.$stRow['student_prenom'])?><?php endif; ?>
								</div>
								<?php else: ?>
								<div style="margin-top:6px;padding:6px 10px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:6px;font-size:13px;color:#fca5a5;">
									⚠️ Aucun matricule lié — recherchez et sélectionnez l'étudiant ci-dessus
								</div>
								<?php endif; ?>
							</div>
							<?php endif; ?>

						<div class="form-group">
							<label class="shad-label">Pseudo</label>
							<input class="shad-input" type="text" name="pseudo<?=$user_id?>" value="<?=$userData['pseudo']?>" required>
						</div>
						
						<div class="form-group">
							<label class="shad-label">Nouveau mot de passe</label>
							<input class="shad-input" type="password" name="password<?=$user_id?>" placeholder="Laisser vide pour conserver">
						</div>
					</div>
				</div>
			</div>
			
			<div class="shad-dialog-footer">
				<button type="button" class="shad-btn shad-btn-secondary closeDialogBtn">Annuler</button>
				<button type="submit" class="shad-btn shad-btn-primary"><i class="bi-check-lg"></i> Enregistrer</button>
			</div>
		</form>
	</div>
</div>
<?php } ?>
								</tbody>
							</table>
						</div>
					</div>

				</div>
			</div>

		</div>

	</div>

<!-- Modal Add User -->
<div class="shad-dialog-overlay" id="addUserDialog">
	<div class="shad-dialog">
		<form id="formToAddUser" method="post" action="<?=$app_base?>/app/.user/add.user?id=<?=$rg_id?>" enctype="multipart/form-data">
			<?= csrf_field() ?>
			<div class="shad-dialog-header">
				<h3 class="shad-dialog-title"><i class="bi-person-plus-fill mr-2 text-cyan-400"></i>Créer un utilisateur</h3>
				<button type="button" class="shad-btn shad-btn-ghost shad-btn-icon closeDialogBtn"><i class="bi-x-lg"></i></button>
			</div>
			
			<div class="shad-dialog-body">
				<div class="flex gap-6">
					<div class="w-1/3">
						<label class="photo-upload-container" for="photosuser">
							<img src="" id="newPhotoPreview" style="display: none;">
							<div class="photo-upload-placeholder" id="newPhotoPlaceholder">
								<i class="bi-camera"></i>
								<span class="text-sm">Ajouter photo</span>
							</div>
						</label>
						<input type="file" accept=".jpg,.png" name="photos" id="photosuser" class="hidden" onchange="previewNewPhoto(this)">
					</div>
					
					<div class="w-2/3 space-y-4">
						<div class="grid grid-cols-2 gap-4">
							<div class="form-group">
								<label class="shad-label">Nom <span class="text-red-400">*</span></label>
								<input class="shad-input" type="text" name="nom" id="nom" required>
							</div>
							<div class="form-group">
								<label class="shad-label">Prénom <span class="text-red-400">*</span></label>
								<input class="shad-input" type="text" name="prenom" id="prenom" required>
							</div>
						</div>
						
						<div class="form-group">
							<label class="shad-label">Poste</label>
							<input class="shad-input" type="text" name="post" id="post">
						</div>
						
						<div class="form-group">
							<label class="shad-label">Email</label>
							<input class="shad-input" type="email" name="mail" id="mail">
						</div>
						
						<div class="shad-separator"></div>
						
						<div class="form-group">
							<label class="shad-label">Privilège</label>
							<select class="shad-select" name="level" id="levelSelect">
								<option value="1">👨‍💻 Superadmin</option>
								<option value="2">🛡️ Administrateur</option>
								<option value="3" selected>📋 Registraire</option>
								<option value="4">💰 Comptabilité</option>
								<option value="5">📷 Média</option>
								<option value="6">🏅 Chef de mention</option>
								<option value="7">👨‍🏫 Professeur</option>
								<option value="8">🎓 Étudiant</option>
							</select>
						</div>
						
						<div id="teacherLinkSection" class="form-group hidden">
							<label class="shad-label">Lier au professeur</label>
							<select class="shad-select" name="teacher_uid">
								<option value="">-- Sélectionner --</option>
								<?php
								$teachers = $dtb->query("SELECT uid, name, lastName FROM teacher WHERE remove != 1 ORDER BY lastName, name");
								while($teacher = $teachers->fetch()) {
									echo '<option value="'.$teacher['uid'].'">'.$teacher['lastName'].' '.$teacher['name'].'</option>';
								}
								?>
							</select>
						</div>
						
						<div id="studentLinkSection" class="form-group hidden">
									<label class="shad-label">Lier à l'étudiant</label>
									<input type="text" class="shad-input" id="studentSearchAdd" placeholder="🔍 Rechercher par nom ou matricule..." autocomplete="off">
									<div id="studentSearchResults" style="max-height:180px;overflow-y:auto;margin-top:4px;display:none;background:#0f1729;border:1px solid rgba(14,165,233,0.2);border-radius:8px;"></div>
									<input type="hidden" name="student_id" id="selectedStudentId">
									<div id="selectedStudentBadge" class="hidden" style="margin-top:6px;padding:6px 10px;background:rgba(14,165,233,0.1);border:1px solid rgba(14,165,233,0.3);border-radius:6px;display:flex;align-items:center;gap:8px;">
										<span id="selectedStudentText" style="flex:1;font-size:13px;color:#e8f1f8;"></span>
										<button type="button" onclick="clearStudentSelection()" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:16px;">✕</button>
									</div>
						</div>
						
						<div class="form-group">
							<label class="shad-label">Pseudo <span class="text-red-400">*</span></label>
							<input class="shad-input" type="text" name="pseudo" id="pseudo" required>
						</div>
						
						<div class="grid grid-cols-2 gap-4">
							<div class="form-group">
								<label class="shad-label">Mot de passe <span class="text-red-400">*</span></label>
								<input class="shad-input" type="password" name="password" id="password" required>
							</div>
							<div class="form-group">
								<label class="shad-label">Confirmation <span class="text-red-400">*</span></label>
								<input class="shad-input" type="password" name="confirmpass" id="confirmpass" required>
							</div>
						</div>
						<p id="passwordMatch" class="text-xs hidden"></p>
					</div>
				</div>
			</div>
			
			<div class="shad-dialog-footer">
				<button type="button" class="shad-btn shad-btn-secondary closeDialogBtn">Annuler</button>
				<button type="submit" id="btnAddUser" class="shad-btn shad-btn-primary" disabled>
					<i class="bi-person-plus"></i> Créer
				</button>
			</div>
		</form>
	</div>
</div>

<script>
$(document).ready(function() {
	
	// Global Toast bridge (keeps existing call sites unchanged)
	window.showToast = function(type, title, message) {
		const text = [title, message].filter(Boolean).join(' - ');
		if (window.Toast && typeof window.Toast[type] === 'function') {
			window.Toast[type](text);
			return;
		}
		if (window.Toast && typeof window.Toast.info === 'function') {
			window.Toast.info(text);
		}
	};
	
	// Dialog Management
	$('#addUserBtn').on('click', function() { $('#addUserDialog').addClass('active'); });
	$('.updateUserBtn').on('click', function() { $('#updateDialog' + $(this).data('user-id')).addClass('active'); });
	$('.closeDialogBtn').on('click', function() { $(this).closest('.shad-dialog-overlay').removeClass('active'); });
	$('.shad-dialog-overlay').on('click', function(e) { if (e.target === this) $(this).removeClass('active'); });
	$(document).on('keydown', function(e) { if (e.key === 'Escape') $('.shad-dialog-overlay.active').removeClass('active'); });
	
	// Search
	$('#searchUsers').on('input', function() {
		const search = $(this).val().toLowerCase();
		$('#usersTable tbody tr').each(function() {
			$(this).toggle(($(this).data('name') || '').includes(search));
		});
	});
	
	// Password Validation
	function validatePasswords() {
		const password = $('#password').val();
		const confirmpass = $('#confirmpass').val();
		const nom = $('#nom').val();
		const prenom = $('#prenom').val();
		const pseudo = $('#pseudo').val();
		
		if (nom && prenom && pseudo && password && confirmpass) {
			if (password === confirmpass) {
				$('#password, #confirmpass').removeClass('shad-input-error').addClass('shad-input-success');
				$('#passwordMatch').removeClass('hidden text-red-400').addClass('text-green-400').text('✓ Mots de passe identiques');
				$('#btnAddUser').prop('disabled', false);
			} else {
				$('#password, #confirmpass').removeClass('shad-input-success').addClass('shad-input-error');
				$('#passwordMatch').removeClass('hidden text-green-400').addClass('text-red-400').text('✗ Mots de passe différents');
				$('#btnAddUser').prop('disabled', true);
			}
		} else {
			$('#password, #confirmpass').removeClass('shad-input-error shad-input-success');
			$('#passwordMatch').addClass('hidden');
			$('#btnAddUser').prop('disabled', true);
		}
	}
	
	$('#password, #confirmpass, #nom, #prenom, #pseudo').on('input', validatePasswords);
	
	// Level Select
	$('#levelSelect').on('change', function() {
		const level = $(this).val();
		$('#teacherLinkSection, #studentLinkSection').addClass('hidden');
		if (level === '7') $('#teacherLinkSection').removeClass('hidden');
		else if (level === '8') $('#studentLinkSection').removeClass('hidden');
	});
	
	// Switch Labels
	$('[id^="etatSwitch"]').on('change', function() {
		$('#etatLabel' + this.id.replace('etatSwitch', '')).text(this.checked ? 'Actif' : 'Inactif');
	});
	
	// Form Submissions
	$('.updateUserForm').on('submit', function() { showToast('info', 'Enregistrement...', 'Mise à jour en cours.'); });
	$('#formToAddUser').on('submit', function(e) {
		if ($('#password').val() !== $('#confirmpass').val()) {
			e.preventDefault();
			showToast('error', 'Erreur', 'Les mots de passe ne correspondent pas.');
			return false;
		}
		showToast('info', 'Création...', 'Création en cours.');
	});
	
	// ====== Student Search Autocomplete ======
	function clearStudentSelection() {
		$('#selectedStudentId').val('');
		$('#selectedStudentBadge').addClass('hidden').hide();
		$('#selectedStudentText').html('');
		$('#studentSearchAdd').val('');
	}
	function studentSearch(input, resultsDiv, hiddenInput, badgeDiv, badgeText) {
		let timer;
		$(input).on('input', function() {
			clearTimeout(timer);
			const q = $(this).val().trim();
			if (q.length < 2) { $(resultsDiv).hide().empty(); return; }
			timer = setTimeout(function() {
				$.get(APP_BASE+'/app/.user/search.student.php', { q: q }, function(data) {
					if (data.length === 0) {
						$(resultsDiv).html('<div style="padding:10px;color:#94a3b8;font-size:13px;">Aucun étudiant trouvé</div>').show();
						return;
					}
					let html = '';
					data.forEach(function(s) {
						html += '<div class="student-result-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid rgba(51,65,85,0.3);font-size:13px;color:#e8f1f8;transition:background 0.2s;" '
							+ 'onmouseover="this.style.background=\'rgba(14,165,233,0.15)\'" onmouseout="this.style.background=\'none\'" '
							+ 'data-id="' + s.student_id + '" data-name="' + s.student_nom + ' ' + s.student_prenom + '">'
							+ '<b style="color:#0ea5e9;">' + s.student_id + '</b> — ' + s.student_nom + ' ' + s.student_prenom
							+ (s.etude_envisage ? ' <span style="color:#64748b;font-size:11px;">(' + s.etude_envisage + ')</span>' : '')
							+ '</div>';
					});
					$(resultsDiv).html(html).show();
					$(resultsDiv).find('.student-result-item').on('click', function() {
						const sid = $(this).data('id');
						const sname = $(this).data('name');
						$(hiddenInput).val(sid);
						$(input).val('');
						$(resultsDiv).hide();
						if (badgeDiv && badgeText) {
							$(badgeText).html('✅ <b>' + sid + '</b> — ' + sname);
							$(badgeDiv).removeClass('hidden').show();
						}
						showToast('success', 'Étudiant sélectionné', sid + ' — ' + sname);
					});
				}, 'json');
			}, 300);
		});
	}

	// Init search for Add modal
	studentSearch('#studentSearchAdd', '#studentSearchResults', '#selectedStudentId', '#selectedStudentBadge', '#selectedStudentText');

	// Init search for Edit modals
	$('[id^="studentSearchEdit"]').each(function() {
		const uid = $(this).data('user-id');
		studentSearch('#studentSearchEdit'+uid, '#studentSearchResultsEdit'+uid, '#selectedStudentIdEdit'+uid, null, null);
	});

	// URL Params for Toast
	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.get('success') === '1') {
		showToast('success', 'Succès !', 'Opération effectuée avec succès.');
		window.history.replaceState({}, document.title, window.location.pathname);
	}
	if (urlParams.get('error')) {
		showToast('error', 'Erreur', decodeURIComponent(urlParams.get('error')));
		window.history.replaceState({}, document.title, window.location.pathname);
	}
});

// Photo Preview
function previewPhoto(input, userId) {
	if (input.files && input.files[0]) {
		const reader = new FileReader();
		reader.onload = function(e) {
			const container = input.closest('form').querySelector('.photo-upload-container');
			let img = container.querySelector('img');
			const placeholder = container.querySelector('.photo-upload-placeholder');
			
			if (!img) {
				img = document.createElement('img');
				container.appendChild(img);
			}
			img.src = e.target.result;
			img.style.display = 'block';
			if (placeholder) placeholder.style.display = 'none';
		};
		reader.readAsDataURL(input.files[0]);
		showToast('info', 'Photo sélectionnée', 'Enregistrez pour appliquer.');
	}
}

function previewNewPhoto(input) {
	if (input.files && input.files[0]) {
		const reader = new FileReader();
		reader.onload = function(e) {
			$('#newPhotoPreview').attr('src', e.target.result).show();
			$('#newPhotoPlaceholder').hide();
		};
		reader.readAsDataURL(input.files[0]);
	}
}
</script>

</body>
</html>


