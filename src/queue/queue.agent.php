<?php
// Chargement middleware est fait dans head.php
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php
	// Vérification d'accès - niveau Registraire minimum
	requireLevel(ROLE_REGISTRAR, $app_base . '/dashboard');
	?>
	<title>File d'attente - Gestion</title>
	<style>
		/* ========== QUEUE AGENT STYLES ========== */
		.queue-card {
			background: rgba(15, 23, 42, 0.6);
			border: 1px solid rgba(51, 65, 85, 0.4);
			border-radius: 12px;
			backdrop-filter: blur(10px);
		}
		.queue-card-header {
			background: linear-gradient(135deg, rgba(8, 145, 178, 0.15), rgba(6, 182, 212, 0.05));
			border-bottom: 1px solid rgba(51, 65, 85, 0.4);
			padding: 16px 20px;
			border-radius: 12px 12px 0 0;
		}
		
		[data-theme="light"] .queue-card {
			background: rgba(255, 255, 255, 0.8);
			border-color: #d1e5f4;
		}
		[data-theme="light"] .queue-card-header {
			background: linear-gradient(135deg, rgba(78, 158, 222, 0.1), rgba(78, 158, 222, 0.05));
			border-bottom-color: #d1e5f4;
		}

		.stat-card {
			background: rgba(30, 41, 59, 0.5);
			border: 1px solid rgba(51, 65, 85, 0.3);
			border-radius: 10px;
			padding: 16px;
			text-align: center;
		}
		[data-theme="light"] .stat-card {
			background: rgba(240, 247, 252, 0.8);
			border-color: #d1e5f4;
		}
		.stat-number {
			font-size: 2rem;
			font-weight: 700;
			line-height: 1;
		}
		.stat-label {
			font-size: 0.75rem;
			color: #94a3b8;
			margin-top: 4px;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}

		.btn-call {
			background: linear-gradient(135deg, #0891b2, #06b6d4);
			color: white;
			border: none;
			border-radius: 10px;
			padding: 16px 32px;
			font-size: 1.25rem;
			font-weight: 700;
			cursor: pointer;
			transition: all 0.2s;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.btn-call:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(8, 145, 178, 0.4);
		}
		.btn-call:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			transform: none;
			box-shadow: none;
		}

		.btn-generate {
			background: linear-gradient(135deg, #059669, #10b981);
			color: white;
			border: none;
			border-radius: 8px;
			padding: 10px 20px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s;
		}
		.btn-generate:hover {
			transform: translateY(-1px);
			box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
		}

		.ticket-list {
			max-height: 400px;
			overflow-y: auto;
		}
		.ticket-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 10px 14px;
			border-bottom: 1px solid rgba(51, 65, 85, 0.2);
			transition: background 0.2s;
		}
		.ticket-item:hover {
			background: rgba(51, 65, 85, 0.2);
		}
		[data-theme="light"] .ticket-item {
			border-bottom-color: #e0eef7;
		}
		[data-theme="light"] .ticket-item:hover {
			background: rgba(78, 158, 222, 0.08);
		}
		.ticket-number {
			font-family: 'JetBrains Mono', monospace;
			font-size: 1.1rem;
			font-weight: 700;
			color: #e2e8f0;
			min-width: 60px;
		}
		[data-theme="light"] .ticket-number {
			color: #1a3a5c;
		}
		.ticket-status {
			padding: 3px 10px;
			border-radius: 20px;
			font-size: 0.7rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}
		.status-waiting { background: rgba(234, 179, 8, 0.2); color: #eab308; }
		.status-called { background: rgba(8, 145, 178, 0.2); color: #06b6d4; animation: pulse-cyan 2s infinite; }
		.status-serving { background: rgba(124, 58, 237, 0.2); color: #8b5cf6; }
		.status-done { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
		.status-skipped { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

		@keyframes pulse-cyan {
			0%, 100% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0.4); }
			50% { box-shadow: 0 0 0 8px rgba(6, 182, 212, 0); }
		}

		/* ========== CURRENT CALL SECTION ========== */
		.current-call-card {
			background: linear-gradient(135deg, rgba(8, 145, 178, 0.12), rgba(6, 182, 212, 0.05));
			border: 2px solid rgba(6, 182, 212, 0.35);
			border-radius: 12px;
			overflow: hidden;
		}
		[data-theme="light"] .current-call-card {
			background: linear-gradient(135deg, rgba(78, 158, 222, 0.08), rgba(78, 158, 222, 0.03));
			border-color: #8eb8d4;
		}
		.current-call-number {
			font-family: 'JetBrains Mono', monospace;
			font-size: 2.8rem;
			font-weight: 800;
			color: #06b6d4;
			line-height: 1;
			text-shadow: 0 0 15px rgba(6, 182, 212, 0.3);
		}
		.btn-recall {
			background: linear-gradient(135deg, #d97706, #f59e0b);
			color: white;
			border: none;
			border-radius: 10px;
			padding: 12px 24px;
			font-size: 1rem;
			font-weight: 700;
			cursor: pointer;
			transition: all 0.2s;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.btn-recall:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(217, 119, 6, 0.4);
		}
		.btn-recall:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			transform: none;
			box-shadow: none;
		}
		.current-call-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 10px 0;
			border-bottom: 1px solid rgba(6, 182, 212, 0.15);
		}
		.current-call-item:last-child {
			border-bottom: none;
		}

		.batch-selector {
			display: flex;
			align-items: center;
			gap: 8px;
			background: rgba(30, 41, 59, 0.5);
			border: 1px solid rgba(51, 65, 85, 0.4);
			border-radius: 8px;
			padding: 8px 14px;
		}
		[data-theme="light"] .batch-selector {
			background: rgba(255, 255, 255, 0.8);
			border-color: #d1e5f4;
		}
		.batch-selector input[type="number"] {
			width: 60px;
			background: rgba(15, 23, 42, 0.8);
			border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 6px;
			color: #e2e8f0;
			text-align: center;
			padding: 6px;
			font-size: 1.1rem;
			font-weight: 700;
		}
		[data-theme="light"] .batch-selector input[type="number"] {
			background: #f0f7fc;
			border-color: #8eb8d4;
			color: #1a3a5c;
		}

		.session-badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			background: rgba(34, 197, 94, 0.15);
			color: #22c55e;
			padding: 4px 12px;
			border-radius: 20px;
			font-size: 0.8rem;
			font-weight: 600;
		}
		.session-badge.inactive {
			background: rgba(239, 68, 68, 0.15);
			color: #ef4444;
		}
		.link-display {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			color: #06b6d4;
			text-decoration: none;
			font-size: 0.85rem;
		}
		.link-display:hover { text-decoration: underline; }

		.queue-input {
			width: 100%;
			background: rgba(15, 23, 42, 0.5);
			border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 8px;
			padding: 8px 12px;
			color: #e2e8f0;
			font-size: 0.875rem;
		}
		.queue-input:focus {
			outline: none;
			border-color: #0891b2;
			box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.2);
		}
		[data-theme="light"] .queue-input {
			background: #f0f7fc;
			border-color: #8eb8d4;
			color: #1a3a5c;
		}
		[data-theme="light"] .queue-input:focus {
			border-color: #4e9ede;
			box-shadow: 0 0 0 2px rgba(78, 158, 222, 0.2);
		}
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">
	<?php require('../init/topbar.php'); ?>
	<div class="w-full flex flex-col lg:flex-row">
		<?php require('../init/menubar.php'); ?>
		<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<div class="back flex-1 overflow-hidden">
				<div class="p-4 overflow-auto" style="height: calc(100vh - 75px);">

					<!-- PAGE HEADER -->
					<div class="flex items-center justify-between mb-6">
						<div class="flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(8, 145, 178, 0.15);">
								<i class="bi bi-people-fill text-xl" style="color: #0891b2;"></i>
							</div>
							<div>
								<h1 class="text-lg font-bold <?=$txt_one_color?>">File d'attente — Inscriptions</h1>
								<p class="text-xs text-slate-400">Gestion et appel des étudiants</p>
							</div>
						</div>
						<div class="flex items-center gap-3">
							<a href="<?=$app_base?>/queue/display" target="_blank" class="link-display">
								<i class="bi bi-display"></i> Ouvrir l'écran public
							</a>
							<div id="sessionBadge"></div>
						</div>
					</div>

					<!-- SESSION CONTROLS -->
					<div id="sessionControls" class="queue-card mb-4">
						<div class="queue-card-header flex items-center justify-between">
							<span class="<?=$txt_one_color?> font-semibold"><i class="bi bi-calendar-event me-2"></i>Session</span>
							<div class="flex gap-2">
								<button onclick="showCreateSession()" class="btn-generate text-sm" id="btnCreateSession">
									<i class="bi bi-plus-lg me-1"></i> Nouvelle session
								</button>
								<button onclick="closeSession()" class="text-sm px-3 py-2 rounded-lg bg-red-900/30 text-red-400 border border-red-800/40 hover:bg-red-900/50" id="btnCloseSession" style="display:none;">
									<i class="bi bi-x-lg me-1"></i> Fermer
								</button>
							</div>
						</div>
						<div class="p-4" id="sessionInfo">
							<p class="text-slate-400 text-sm">Chargement...</p>
						</div>
					</div>

					<!-- CREATE SESSION MODAL -->
					<div id="createSessionModal" class="queue-card mb-4" style="display:none;">
						<div class="p-4">
							<div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
								<div>
									<label class="text-xs text-slate-400 block mb-1">Nom de la session</label>
									<input type="text" id="inputSessionName" placeholder="Ex: Inscription S1 2026" class="queue-input">
								</div>
								<div>
									<label class="text-xs text-slate-400 block mb-1">Date</label>
									<input type="date" id="inputSessionDate" value="<?=date('Y-m-d')?>" class="queue-input">
								</div>
								<div class="flex gap-2">
									<button onclick="createSession()" class="btn-generate">
										<i class="bi bi-check-lg me-1"></i> Créer
									</button>
									<button onclick="$('#createSessionModal').slideUp(200)" class="px-3 py-2 rounded-lg bg-slate-700 text-slate-300 text-sm hover:bg-slate-600">
										Annuler
									</button>
								</div>
							</div>
						</div>
					</div>

					<div id="queueContent" style="display:none;">
						<!-- STATS -->
						<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
							<div class="stat-card">
								<div class="stat-number <?=$txt_one_color?>" id="statTotal">0</div>
								<div class="stat-label">Total</div>
							</div>
							<div class="stat-card">
								<div class="stat-number text-yellow-400" id="statWaiting">0</div>
								<div class="stat-label">En attente</div>
							</div>
							<div class="stat-card">
								<div class="stat-number text-cyan-400" id="statCalled">0</div>
								<div class="stat-label">Appelés</div>
							</div>
							<div class="stat-card">
								<div class="stat-number text-violet-400" id="statServing">0</div>
								<div class="stat-label">En cours</div>
							</div>
							<div class="stat-card">
								<div class="stat-number text-green-400" id="statDone">0</div>
								<div class="stat-label">Terminés</div>
							</div>
						</div>

						<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
							<!-- COLONNE GAUCHE: Appel -->
							<div class="lg:col-span-1">
								<!-- Appel groupé -->
								<div class="queue-card mb-4">
									<div class="queue-card-header">
										<span class="<?=$txt_one_color?> font-semibold"><i class="bi bi-megaphone-fill me-2"></i>Appeler les suivants</span>
									</div>
									<div class="p-5 flex flex-col items-center gap-4">
										<div class="batch-selector">
											<label class="text-slate-400 text-sm">Nombre :</label>
											<input type="number" id="batchSize" value="1" min="1" max="10">
										</div>
										<button onclick="callNext()" class="btn-call w-full justify-center" id="btnCallNext">
											<i class="bi bi-megaphone-fill"></i>
											<span>APPELER</span>
										</button>
										<p class="text-xs text-slate-500 text-center">
											Appelle les prochains numéros en attente
										</p>
									</div>
								</div>

								<!-- Numéro(s) en cours d'appel -->
								<div id="currentCallSection" class="current-call-card mb-4" style="display:none;">
									<div class="queue-card-header" style="background: linear-gradient(135deg, rgba(217, 119, 6, 0.15), rgba(245, 158, 11, 0.05));">
										<span class="<?=$txt_one_color?> font-semibold"><i class="bi bi-telephone-forward-fill me-2" style="color: #f59e0b;"></i>Numéro(s) en cours d'appel</span>
									</div>
									<div class="p-4" id="currentCallContent">
										<!-- Rempli dynamiquement par JS -->
									</div>
								</div>

								<!-- Générer un ticket -->
								<div class="queue-card">
									<div class="queue-card-header">
										<span class="<?=$txt_one_color?> font-semibold"><i class="bi bi-ticket-perforated me-2"></i>Nouveau ticket</span>
									</div>
									<div class="p-4">
										<div class="mb-3">
											<label class="text-xs text-slate-400 block mb-1">Nom (optionnel)</label>
											<input type="text" id="ticketName" placeholder="Nom de l'étudiant" class="queue-input">
										</div>
										<div class="mb-3">
											<label class="text-xs text-slate-400 block mb-1">Mention (optionnel)</label>
											<select id="ticketMention" class="queue-input">
												<option value="">-- Aucune --</option>
												<?php
												// Charger les mentions depuis la base si disponible
												try {
													$mentions = $dtb->query("SELECT DISTINCT mention FROM t_mention WHERE etat = 1 ORDER BY mention ASC");
													while($m = $mentions->fetch(PDO::FETCH_ASSOC)) {
														echo '<option value="'.htmlspecialchars($m['mention']).'">'.htmlspecialchars($m['mention']).'</option>';
													}
												} catch(Exception $e) {
													// Fallback si la table n'existe pas
													echo '<option value="Théologie">Théologie</option>';
													echo '<option value="Gestion">Gestion</option>';
													echo '<option value="Informatique">Informatique</option>';
													echo '<option value="Sciences Infirmières">Sciences Infirmières</option>';
													echo '<option value="Éducation">Éducation</option>';
													echo '<option value="Communication">Communication</option>';
												}
												?>
											</select>
										</div>
										<button onclick="generateTicket()" class="btn-generate w-full text-center">
											<i class="bi bi-plus-circle me-1"></i> Générer le ticket
										</button>
									</div>
								</div>

								<!-- Liste de lecture vidéo -->
								<div class="queue-card mt-4">
									<div class="queue-card-header flex items-center justify-between">
										<span class="<?=$txt_one_color?> font-semibold"><i class="bi bi-collection-play me-2"></i>Liste de lecture</span>
										<button onclick="clearPlaylist()" class="text-xs text-red-400 hover:text-red-300 px-2 py-1 rounded hover:bg-red-900/20" title="Vider la playlist">
											<i class="bi bi-trash3 me-1"></i>Vider
										</button>
									</div>
									<div class="p-4">
										<!-- Ajouter une vidéo -->
										<div class="mb-3">
											<label class="text-xs text-slate-400 block mb-1">Ajouter une vidéo YouTube</label>
											<div class="flex gap-2">
												<input type="text" id="playlistAddUrl" placeholder="https://youtube.com/watch?v=..." class="queue-input flex-1" onkeydown="if(event.key==='Enter')addToPlaylist()">
												<button onclick="addToPlaylist()" class="px-3 py-2 rounded-lg bg-green-700 text-white text-sm hover:bg-green-600" title="Ajouter">
													<i class="bi bi-plus-lg"></i>
												</button>
											</div>
										</div>

										<!-- Playlist -->
										<div id="playlistItems" class="mb-3 rounded-lg border border-slate-700/60 overflow-hidden" style="max-height: 220px; overflow-y: auto;">
											<p class="text-slate-500 text-xs text-center p-3">Aucune vidéo</p>
										</div>

										<!-- Contrôles de lecture -->
										<div class="flex items-center justify-center gap-2 mb-3">
											<button onclick="playlistPrev()" class="px-3 py-2 rounded-lg bg-slate-700 text-slate-300 text-sm hover:bg-slate-600" title="Précédent">
												<i class="bi bi-skip-backward-fill"></i>
											</button>
											<button onclick="toggleMusic()" id="btnMusicToggle" class="px-5 py-2 rounded-lg bg-slate-700 text-slate-300 text-sm hover:bg-slate-600 flex items-center gap-2">
												<i class="bi bi-play-fill" id="musicToggleIcon"></i>
												<span id="musicToggleText">Lecture</span>
											</button>
											<button onclick="playlistNext()" class="px-3 py-2 rounded-lg bg-slate-700 text-slate-300 text-sm hover:bg-slate-600" title="Suivant">
												<i class="bi bi-skip-forward-fill"></i>
											</button>
										</div>

										<!-- Volume -->
										<div class="flex items-center gap-2">
											<i class="bi bi-volume-down text-slate-500 text-sm"></i>
											<input type="range" id="musicVolume" min="0" max="100" value="50" class="flex-1" style="accent-color:#06b6d4;" oninput="setMusicVolume(this.value)">
											<span class="text-slate-500 text-xs w-8 text-right" id="musicVolumeLabel">50%</span>
										</div>
										<p class="text-xs text-slate-500 mt-2" id="musicStatus">Aucune vidéo configurée</p>
									</div>
								</div>
							</div>

							<!-- COLONNE DROITE: Liste des tickets -->
							<div class="lg:col-span-2">
								<div class="queue-card">
									<div class="queue-card-header flex items-center justify-between">
										<span class="<?=$txt_one_color?> font-semibold"><i class="bi bi-list-ol me-2"></i>Tickets</span>
										<select id="filterStatus" onchange="refreshTickets()" class="queue-input" style="width: auto; padding: 4px 10px; font-size: 0.75rem;">
											<option value="all">Tous</option>
											<option value="waiting" selected>En attente</option>
											<option value="called">Appelés</option>
											<option value="serving">En cours</option>
											<option value="done">Terminés</option>
											<option value="skipped">Passés</option>
										</select>
									</div>
									<div class="ticket-list" id="ticketList">
										<p class="text-slate-400 text-sm p-4">Chargement...</p>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
			<?php require('../init/footer.php'); ?>
		</div>
	</div>
</div>

<script>
const QUEUE_API = '<?=$app_base?>/queue/api';
const CSRF_TOKEN = '<?= csrf_token() ?>';
let currentSessionId = null;
let refreshInterval = null;

// =====================================================================
// INITIALISATION
// =====================================================================
$(document).ready(function() {
	loadActiveSession();
});

function loadActiveSession() {
	$.getJSON(QUEUE_API, { action: 'get_active_session' }, function(res) {
		if (res.success && res.session) {
			currentSessionId = res.session.id;
			showActiveSession(res.session);
			startAutoRefresh();
		} else {
			showNoSession();
		}
	});
}

function showActiveSession(session) {
	$('#sessionInfo').html(
		'<div class="flex items-center gap-3">' +
			'<span class="<?=$txt_one_color?> font-medium">' + escapeHtml(session.session_name) + '</span>' +
			'<span class="text-slate-500 text-xs">' + session.session_date + '</span>' +
		'</div>'
	);
	$('#sessionBadge').html('<span class="session-badge"><i class="bi bi-circle-fill" style="font-size:6px"></i> Session active</span>');
	$('#btnCloseSession').show();
	$('#queueContent').show();
	updateStats(session.stats);
	refreshTickets();
	refreshCurrentCall();
	loadPlaylistState();
}

function showNoSession() {
	$('#sessionInfo').html('<p class="text-slate-500 text-sm">Aucune session active. Créez-en une pour commencer.</p>');
	$('#sessionBadge').html('<span class="session-badge inactive"><i class="bi bi-circle-fill" style="font-size:6px"></i> Inactive</span>');
	$('#btnCloseSession').hide();
	$('#queueContent').hide();
}

// =====================================================================
// SESSIONS
// =====================================================================
function showCreateSession() {
	$('#createSessionModal').slideToggle(200);
	$('#inputSessionName').focus();
}

function createSession() {
	const name = $('#inputSessionName').val().trim();
	const date = $('#inputSessionDate').val();

	if (!name) {
		Toast.error('Entrez un nom de session.');
		return;
	}

	$.post(QUEUE_API, {
		action: 'create_session',
		session_name: name,
		session_date: date,
		csrf_token: CSRF_TOKEN
	}, function(res) {
		if (res.success) {
			Toast.success(res.message);
			$('#createSessionModal').slideUp(200);
			$('#inputSessionName').val('');
			loadActiveSession();
		} else {
			Toast.error(res.message);
		}
	}, 'json').fail(function() {
		Toast.error('Erreur de connexion au serveur');
	});
}

function closeSession() {
	if (!confirm('Fermer cette session ? Les tickets en attente ne seront plus appelés.')) return;

	$.post(QUEUE_API, {
		action: 'close_session',
		session_id: currentSessionId,
		csrf_token: CSRF_TOKEN
	}, function(res) {
		if (res.success) {
			Toast.success(res.message);
			stopAutoRefresh();
			currentSessionId = null;
			loadActiveSession();
		}
	}, 'json');
}

// =====================================================================
// TICKETS
// =====================================================================
function generateTicket() {
	if (!currentSessionId) return;

	const btn = $(event.target).closest('button');
	btn.prop('disabled', true);

	$.post(QUEUE_API, {
		action: 'generate_ticket',
		session_id: currentSessionId,
		student_name: $('#ticketName').val().trim(),
		mention: $('#ticketMention').val()
	}, function(res) {
		btn.prop('disabled', false);
		if (res.success) {
			Toast.success(res.message);
			$('#ticketName').val('');
			refreshTickets();
			refreshStats();
		} else {
			Toast.error(res.message);
		}
	}, 'json').fail(function() {
		btn.prop('disabled', false);
		Toast.error('Erreur de connexion');
	});
}

// =====================================================================
// APPEL GROUPÉ
// =====================================================================
function callNext() {
	if (!currentSessionId) return;

	const batchSize = parseInt($('#batchSize').val()) || 1;
	$('#btnCallNext').prop('disabled', true);

	$.post(QUEUE_API, {
		action: 'call_next',
		session_id: currentSessionId,
		batch_size: batchSize
	}, function(res) {
		$('#btnCallNext').prop('disabled', false);
		if (res.success) {
			const nums = res.called_tickets.map(t => '#' + t.formatted).join(', ');
			Toast.success('Appelé : ' + nums);
			playAgentSound();
			refreshTickets();
			refreshStats();
			refreshCurrentCall();
		} else {
			Toast.error(res.message);
		}
	}, 'json').fail(function() {
		$('#btnCallNext').prop('disabled', false);
		Toast.error('Erreur de connexion');
	});
}

function callSpecific(ticketNumber) {
	$.post(QUEUE_API, {
		action: 'call_specific',
		session_id: currentSessionId,
		ticket_number: ticketNumber
	}, function(res) {
		if (res.success) {
			Toast.success(res.message);
			playAgentSound();
			refreshTickets();
			refreshStats();
			refreshCurrentCall();
		} else {
			Toast.error(res.message);
		}
	}, 'json');
}

function recallTicket(ticketNumber) {
	$.post(QUEUE_API, {
		action: 'recall',
		session_id: currentSessionId,
		ticket_number: ticketNumber
	}, function(res) {
		if (res.success) {
			Toast.info(res.message);
			playAgentSound();
			refreshTickets();
			refreshCurrentCall();
		} else {
			Toast.error(res.message);
		}
	}, 'json');
}

function markDone(ticketId) {
	$.post(QUEUE_API, { action: 'mark_done', ticket_id: ticketId }, function(res) {
		if (res.success) { refreshTickets(); refreshStats(); refreshCurrentCall(); }
	}, 'json');
}

function markSkipped(ticketId) {
	$.post(QUEUE_API, { action: 'mark_skipped', ticket_id: ticketId }, function(res) {
		if (res.success) { refreshTickets(); refreshStats(); refreshCurrentCall(); }
	}, 'json');
}

// =====================================================================
// REFRESH / POLLING
// =====================================================================
function refreshTickets() {
	if (!currentSessionId) return;

	const filter = $('#filterStatus').val();

	$.getJSON(QUEUE_API, {
		action: 'get_tickets',
		session_id: currentSessionId,
		status: filter
	}, function(res) {
		if (res.success) {
			renderTickets(res.tickets);
		}
	});
}

function refreshStats() {
	if (!currentSessionId) return;

	$.getJSON(QUEUE_API, { action: 'get_active_session' }, function(res) {
		if (res.success && res.session) {
			updateStats(res.session.stats);
		}
	});
}

function updateStats(stats) {
	if (!stats) return;
	$('#statTotal').text(stats.total || 0);
	$('#statWaiting').text(stats.waiting || 0);
	$('#statCalled').text(stats.called || 0);
	$('#statServing').text(stats.serving || 0);
	$('#statDone').text(stats.done || 0);
}

function renderTickets(tickets) {
	if (!tickets || tickets.length === 0) {
		$('#ticketList').html('<p class="text-slate-500 text-sm p-4 text-center">Aucun ticket</p>');
		return;
	}

	const statusLabels = { waiting: 'en attente', called: 'appelé', serving: 'en cours', done: 'terminé', skipped: 'passé' };
	let html = '';

	tickets.forEach(function(t) {
		const num = String(t.ticket_number).padStart(3, '0');
		const statusClass = 'status-' + t.status;

		html += '<div class="ticket-item">';
		html += '  <div class="flex items-center gap-3">';
		html += '    <span class="ticket-number">#' + num + '</span>';
		html += '    <div>';
		if (t.student_name) html += '<span class="text-slate-300 text-sm">' + escapeHtml(t.student_name) + '</span><br>';
		if (t.mention) html += '<span class="text-slate-500 text-xs">' + escapeHtml(t.mention) + '</span>';
		html += '    </div>';
		html += '  </div>';
		html += '  <div class="flex items-center gap-2">';
		html += '    <span class="ticket-status ' + statusClass + '">' + (statusLabels[t.status] || t.status) + '</span>';

		// Actions contextuelles
		if (t.status === 'waiting') {
			html += '    <button onclick="callSpecific(' + t.ticket_number + ')" class="text-cyan-400 hover:text-cyan-300 text-xs px-2 py-1 rounded hover:bg-slate-700" title="Appeler"><i class="bi bi-megaphone"></i></button>';
			html += '    <button onclick="markSkipped(' + t.id + ')" class="text-slate-400 hover:text-slate-300 text-xs px-2 py-1 rounded hover:bg-slate-700" title="Passer"><i class="bi bi-skip-forward"></i></button>';
		}
		if (t.status === 'called') {
			html += '    <button onclick="recallTicket(' + t.ticket_number + ')" class="text-amber-400 hover:text-amber-300 text-xs px-2 py-1 rounded hover:bg-slate-700" title="Rappeler"><i class="bi bi-megaphone-fill"></i></button>';
			html += '    <button onclick="markDone(' + t.id + ')" class="text-green-400 hover:text-green-300 text-xs px-2 py-1 rounded hover:bg-slate-700" title="Terminé"><i class="bi bi-check-lg"></i></button>';
		}
		if (t.status === 'serving') {
			html += '    <button onclick="markDone(' + t.id + ')" class="text-green-400 hover:text-green-300 text-xs px-2 py-1 rounded hover:bg-slate-700" title="Terminé"><i class="bi bi-check-lg"></i></button>';
		}
		if (t.status === 'skipped') {
			html += '    <button onclick="callSpecific(' + t.ticket_number + ')" class="text-cyan-400 hover:text-cyan-300 text-xs px-2 py-1 rounded hover:bg-slate-700" title="Rappeler"><i class="bi bi-arrow-counterclockwise"></i></button>';
		}

		html += '  </div>';
		html += '</div>';
	});

	$('#ticketList').html(html);
}

function refreshCurrentCall() {
	if (!currentSessionId) return;

	$.getJSON(QUEUE_API, {
		action: 'get_tickets',
		session_id: currentSessionId,
		status: 'called'
	}, function(res) {
		if (res.success && res.tickets && res.tickets.length > 0) {
			$('#currentCallSection').show();
			renderCurrentCall(res.tickets);
		} else {
			$('#currentCallSection').hide();
		}
	});
}

function renderCurrentCall(tickets) {
	if (!tickets || tickets.length === 0) {
		$('#currentCallContent').html('<p class="text-slate-500 text-sm text-center">Aucun numéro appelé</p>');
		return;
	}

	let html = '';

	tickets.forEach(function(t) {
		const num = String(t.ticket_number).padStart(3, '0');
		html += '<div class="current-call-item">';
		html += '  <div class="flex items-center gap-3">';
		html += '    <span class="current-call-number">#' + num + '</span>';
		html += '    <div>';
		if (t.student_name) html += '<span class="text-slate-300 text-sm">' + escapeHtml(t.student_name) + '</span><br>';
		if (t.mention) html += '<span class="text-slate-500 text-xs">' + escapeHtml(t.mention) + '</span>';
		html += '    </div>';
		html += '  </div>';
		html += '  <div class="flex items-center gap-2">';
		html += '    <button onclick="recallTicket(' + t.ticket_number + ')" class="btn-recall" title="Rappeler ce numéro">';
		html += '      <i class="bi bi-megaphone-fill"></i> RAPPELER';
		html += '    </button>';
		html += '    <button onclick="markDone(' + t.id + ')" class="text-green-400 hover:text-green-300 text-sm px-3 py-2 rounded-lg hover:bg-green-900/20 border border-green-800/30" title="Terminé">';
		html += '      <i class="bi bi-check-lg me-1"></i>Terminé';
		html += '    </button>';
		html += '  </div>';
		html += '</div>';
	});

	// Bouton rappeler tous si plus d'un
	if (tickets.length > 1) {
		html += '<div class="flex justify-center mt-3">';
		html += '  <button onclick="recallAllCalled()" class="btn-recall w-full justify-center" title="Rappeler tous les numéros">';
		html += '    <i class="bi bi-megaphone-fill"></i> RAPPELER TOUS';
		html += '  </button>';
		html += '</div>';
	}

	$('#currentCallContent').html(html);
}

function recallAllCalled() {
	if (!currentSessionId) return;
	// Recall each called ticket
	$('#currentCallContent .current-call-item').each(function() {
		const recallBtn = $(this).find('.btn-recall').first();
		if (recallBtn.length) recallBtn.trigger('click');
	});
}

function startAutoRefresh() {
	if (refreshInterval) clearInterval(refreshInterval);
	refreshInterval = setInterval(function() {
		refreshTickets();
		refreshStats();
		refreshCurrentCall();
	}, 5000);
}

function stopAutoRefresh() {
	if (refreshInterval) {
		clearInterval(refreshInterval);
		refreshInterval = null;
	}
}

// =====================================================================
// LISTE DE LECTURE VIDÉO
// =====================================================================
let musicPlaying = false;
let currentPlaylist = [];
let currentPlaylistIndex = 0;

function loadPlaylistState() {
	if (!currentSessionId) return;
	$.getJSON(QUEUE_API, { action: 'get_music', session_id: currentSessionId }, function(res) {
		if (res.success && res.music) {
			currentPlaylist = res.music.playlist || [];
			currentPlaylistIndex = res.music.current_index || 0;
			musicPlaying = !!res.music.playing;
			$('#musicVolume').val(res.music.volume);
			$('#musicVolumeLabel').text(res.music.volume + '%');
			renderPlaylist();
			updateMusicToggleUI();
			updateMusicStatus();
		}
	});
}

function addToPlaylist() {
	if (!currentSessionId) return;
	const url = $('#playlistAddUrl').val().trim();
	if (!url) { Toast.error('Entrez un lien YouTube.'); return; }

	$.post(QUEUE_API, {
		action: 'add_to_playlist',
		session_id: currentSessionId,
		youtube_url: url
	}, function(res) {
		if (res.success) {
			Toast.success(res.message);
			$('#playlistAddUrl').val('');
			currentPlaylist = res.playlist || [];
			currentPlaylistIndex = res.current_index ?? currentPlaylistIndex;
			renderPlaylist();
			updateMusicStatus();
		} else {
			Toast.error(res.message);
		}
	}, 'json').fail(function() {
		Toast.error('Erreur de connexion');
	});
}

function removeFromPlaylist(index) {
	if (!currentSessionId) return;
	$.post(QUEUE_API, {
		action: 'remove_from_playlist',
		session_id: currentSessionId,
		index: index
	}, function(res) {
		if (res.success) {
			currentPlaylist = res.playlist || [];
			currentPlaylistIndex = res.current_index ?? 0;
			renderPlaylist();
			updateMusicStatus();
		}
	}, 'json');
}

function playlistNext() {
	if (!currentSessionId || currentPlaylist.length === 0) return;
	$.post(QUEUE_API, {
		action: 'playlist_next',
		session_id: currentSessionId
	}, function(res) {
		if (res.success) {
			currentPlaylistIndex = res.current_index;
			musicPlaying = true;
			renderPlaylist();
			updateMusicToggleUI();
			updateMusicStatus();
		}
	}, 'json');
}

function playlistPrev() {
	if (!currentSessionId || currentPlaylist.length === 0) return;
	$.post(QUEUE_API, {
		action: 'playlist_prev',
		session_id: currentSessionId
	}, function(res) {
		if (res.success) {
			currentPlaylistIndex = res.current_index;
			musicPlaying = true;
			renderPlaylist();
			updateMusicToggleUI();
			updateMusicStatus();
		}
	}, 'json');
}

function playPlaylistItem(index) {
	if (!currentSessionId) return;
	$.post(QUEUE_API, {
		action: 'set_playlist_index',
		session_id: currentSessionId,
		index: index
	}, function(res) {
		if (res.success) {
			currentPlaylistIndex = res.current_index;
			musicPlaying = true;
			renderPlaylist();
			updateMusicToggleUI();
			updateMusicStatus();
		}
	}, 'json');
}

function toggleMusic() {
	if (!currentSessionId) return;
	musicPlaying = !musicPlaying;
	sendMusicState();
}

function clearPlaylist() {
	if (!currentSessionId) return;
	if (!confirm('Vider la liste de lecture ?')) return;
	$.post(QUEUE_API, {
		action: 'clear_playlist',
		session_id: currentSessionId
	}, function(res) {
		if (res.success) {
			Toast.info(res.message);
			currentPlaylist = [];
			currentPlaylistIndex = 0;
			musicPlaying = false;
			renderPlaylist();
			updateMusicToggleUI();
			updateMusicStatus();
		}
	}, 'json');
}

function setMusicVolume(val) {
	$('#musicVolumeLabel').text(val + '%');
	clearTimeout(window._musicVolTimer);
	window._musicVolTimer = setTimeout(function() {
		sendMusicState();
	}, 300);
}

function sendMusicState() {
	if (!currentSessionId) return;
	const currentItem = currentPlaylist[currentPlaylistIndex];
	$.post(QUEUE_API, {
		action: 'set_music',
		session_id: currentSessionId,
		youtube_url: currentItem ? currentItem.url : '',
		playing: musicPlaying ? 1 : 0,
		volume: $('#musicVolume').val()
	}, function(res) {
		if (res.success) {
			updateMusicToggleUI();
			updateMusicStatus();
		}
	}, 'json');
}

function renderPlaylist() {
	if (currentPlaylist.length === 0) {
		$('#playlistItems').html('<p class="text-slate-500 text-xs text-center p-3">Aucune vidéo</p>');
		return;
	}

	let html = '';
	currentPlaylist.forEach(function(item, idx) {
		const isActive = idx === currentPlaylistIndex;
		const activeCls = isActive ? 'bg-cyan-900/30 border-l-2 border-l-cyan-400' : 'border-l-2 border-l-transparent';
		const iconHtml = isActive && musicPlaying
			? '<i class="bi bi-soundwave text-cyan-400" style="font-size:0.7rem"></i>'
			: '<span class="text-slate-600" style="font-size:0.7rem">' + (idx + 1) + '</span>';

		html += '<div class="flex items-center gap-2 px-2 py-1.5 border-b border-slate-700/30 hover:bg-slate-800/50 cursor-pointer ' + activeCls + '" onclick="playPlaylistItem(' + idx + ')">';
		html += '  <div class="w-5 text-center flex-shrink-0">' + iconHtml + '</div>';
		html += '  <img src="https://img.youtube.com/vi/' + escapeHtml(item.video_id) + '/default.jpg" class="rounded object-cover flex-shrink-0" style="width:40px;height:28px" alt="" onerror="this.style.display=\'none\'">';
		html += '  <div class="flex-1 min-w-0"><span class="text-xs text-slate-300 block" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(item.title || item.video_id) + '</span></div>';
		html += '  <button onclick="event.stopPropagation();removeFromPlaylist(' + idx + ')" class="text-slate-600 hover:text-red-400 flex-shrink-0 p-1"><i class="bi bi-x-lg" style="font-size:0.65rem"></i></button>';
		html += '</div>';
	});

	$('#playlistItems').html(html);
}

function updateMusicToggleUI() {
	if (musicPlaying) {
		$('#musicToggleIcon').attr('class', 'bi bi-pause-fill');
		$('#musicToggleText').text('Pause');
		$('#btnMusicToggle').removeClass('bg-slate-700').addClass('bg-cyan-700');
	} else {
		$('#musicToggleIcon').attr('class', 'bi bi-play-fill');
		$('#musicToggleText').text('Lecture');
		$('#btnMusicToggle').removeClass('bg-cyan-700').addClass('bg-slate-700');
	}
}

function updateMusicStatus() {
	if (currentPlaylist.length === 0) {
		$('#musicStatus').text('Aucune vidéo configurée');
	} else if (musicPlaying) {
		const item = currentPlaylist[currentPlaylistIndex];
		$('#musicStatus').text('En lecture : ' + (item ? (item.title || item.video_id) : '') + ' (' + (currentPlaylistIndex + 1) + '/' + currentPlaylist.length + ')');
	} else {
		$('#musicStatus').text('En pause (' + currentPlaylist.length + ' vidéo' + (currentPlaylist.length > 1 ? 's' : '') + ')');
	}
}

// Utilitaire : échapper le HTML
function escapeHtml(text) {
	if (!text) return '';
	const div = document.createElement('div');
	div.appendChild(document.createTextNode(text));
	return div.innerHTML;
}

// Son de confirmation d'appel (côté agent)
function playAgentSound() {
	try {
		const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
		const osc = audioCtx.createOscillator();
		const gain = audioCtx.createGain();
		osc.connect(gain);
		gain.connect(audioCtx.destination);
		osc.type = 'sine';
		osc.frequency.value = 880;
		gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
		gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
		osc.start(audioCtx.currentTime);
		osc.stop(audioCtx.currentTime + 0.2);
	} catch(e) {}
}
</script>
</body>
</html>
