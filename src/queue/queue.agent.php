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
		/* ========== MINIMALIST QUEUE STYLES ========== */
		.q-card {
			background: rgba(15, 23, 42, 0.5);
			border: 1px solid rgba(51, 65, 85, 0.3);
			border-radius: 10px;
		}
		[data-theme="light"] .q-card {
			background: rgba(255, 255, 255, 0.85);
			border-color: #dce8f0;
		}
		.q-head {
			padding: 10px 14px;
			border-bottom: 1px solid rgba(51, 65, 85, 0.25);
			display: flex;
			align-items: center;
			justify-content: space-between;
			font-size: 0.8rem;
			font-weight: 600;
		}
		[data-theme="light"] .q-head { border-bottom-color: #dce8f0; }
		.q-body { padding: 12px 14px; }

		/* Stats inline */
		.q-stats {
			display: flex;
			gap: 6px;
			flex-wrap: wrap;
		}
		.q-stat {
			flex: 1;
			min-width: 65px;
			text-align: center;
			padding: 8px 4px;
			border-radius: 8px;
			background: rgba(30, 41, 59, 0.4);
			border: 1px solid rgba(51, 65, 85, 0.2);
		}
		[data-theme="light"] .q-stat { background: rgba(240, 247, 252, 0.8); border-color: #dce8f0; }
		.q-stat-n { font-size: 1.5rem; font-weight: 700; line-height: 1; }
		.q-stat-l { font-size: 0.6rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }

		/* Buttons */
		.btn-call {
			background: #0891b2;
			color: white;
			border: none;
			border-radius: 8px;
			padding: 12px 20px;
			font-size: 1rem;
			font-weight: 700;
			cursor: pointer;
			transition: opacity 0.15s;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			width: 100%;
		}
		.btn-call:hover { opacity: 0.85; }
		.btn-call:disabled { opacity: 0.4; cursor: not-allowed; }

		.btn-gen {
			background: #059669;
			color: white;
			border: none;
			border-radius: 6px;
			padding: 8px 14px;
			font-weight: 600;
			font-size: 0.8rem;
			cursor: pointer;
			transition: opacity 0.15s;
		}
		.btn-gen:hover { opacity: 0.85; }

		.btn-sm {
			border: none;
			background: none;
			cursor: pointer;
			padding: 4px 6px;
			border-radius: 4px;
			font-size: 0.75rem;
			transition: background 0.15s;
		}
		.btn-sm:hover { background: rgba(51, 65, 85, 0.3); }

		/* Tickets */
		.ticket-list { max-height: 400px; overflow-y: auto; }
		.t-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 7px 12px;
			border-bottom: 1px solid rgba(51, 65, 85, 0.15);
			gap: 8px;
			font-size: 0.8rem;
		}
		.t-row:last-child { border-bottom: none; }
		.t-row:hover { background: rgba(51, 65, 85, 0.15); }
		[data-theme="light"] .t-row { border-bottom-color: #e8f0f5; }
		[data-theme="light"] .t-row:hover { background: rgba(78, 158, 222, 0.06); }
		.t-num {
			font-family: 'JetBrains Mono', monospace;
			font-size: 0.9rem;
			font-weight: 700;
			min-width: 50px;
			color: #e2e8f0 !important;
		}
		[data-theme="light"] .t-num { color: #1a3a5c !important; }
		.t-badge {
			padding: 2px 8px;
			border-radius: 10px;
			font-size: 0.6rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.03em;
			white-space: nowrap;
		}
		.status-waiting { background: rgba(234, 179, 8, 0.15); color: #eab308; }
		.status-called { background: rgba(8, 145, 178, 0.15); color: #06b6d4; }
		.status-serving { background: rgba(124, 58, 237, 0.15); color: #8b5cf6; }
		.status-done { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
		.status-skipped { background: rgba(100, 116, 139, 0.15); color: #94a3b8; }
		.status-absent { background: rgba(239, 68, 68, 0.15); color: #f87171; }

		/* Current call */
		.call-num {
			font-family: 'JetBrains Mono', monospace;
			font-size: 2rem;
			font-weight: 800;
			color: #06b6d4;
			line-height: 1;
		}
		.btn-recall {
			background: #d97706;
			color: white;
			border: none;
			border-radius: 6px;
			padding: 6px 12px;
			font-size: 0.75rem;
			font-weight: 600;
			cursor: pointer;
			transition: opacity 0.15s;
			display: inline-flex;
			align-items: center;
			gap: 4px;
		}
		.btn-recall:hover { opacity: 0.85; }
		.current-call-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 8px 0;
			border-bottom: 1px solid rgba(51, 65, 85, 0.15);
			gap: 8px;
			flex-wrap: wrap;
		}
		.current-call-item:last-child { border-bottom: none; }

		/* Input */
		.q-input {
			width: 100%;
			background: #0f172a !important;
			border: 1px solid rgba(51, 65, 85, 0.5) !important;
			border-radius: 6px;
			padding: 7px 10px;
			color: #f1f5f9 !important;
			font-size: 0.8rem;
		}
		.q-input::placeholder { color: #64748b !important; }
		.q-input option { background: #0f172a !important; color: #f1f5f9 !important; }
		.q-input:focus {
			outline: none;
			border-color: #0891b2 !important;
			box-shadow: 0 0 0 1px rgba(8, 145, 178, 0.2);
		}
		[data-theme="light"] .q-input { background: #ffffff !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
		[data-theme="light"] .q-input::placeholder { color: #94a3b8 !important; }
		[data-theme="light"] .q-input option { background: #ffffff !important; color: #0f172a !important; }
		[data-theme="light"] .q-input:focus { border-color: #4e9ede !important; }

		/* Badges */
		.s-badge {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 3px 10px;
			border-radius: 10px;
			font-size: 0.7rem;
			font-weight: 600;
		}
		.s-badge.on { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
		.s-badge.off { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
		.link-display {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			color: #06b6d4;
			text-decoration: none;
			font-size: 0.75rem;
		}
		.link-display:hover { text-decoration: underline; }

		/* Batch */
		.batch-input {
			width: 48px;
			background: #0f172a !important;
			border: 1px solid rgba(51, 65, 85, 0.5) !important;
			border-radius: 6px;
			color: #f1f5f9 !important;
			text-align: center;
			padding: 5px;
			font-size: 1rem;
			font-weight: 700;
		}
		[data-theme="light"] .batch-input { background: #ffffff !important; border-color: #cbd5e1 !important; color: #0f172a !important; }

		/* ========== BENTO GRID ========== */
		.bento {
			display: grid;
			gap: 10px;
			grid-template-columns: 1fr;
			grid-template-areas:
				"stats"
				"call"
				"newticket"
				"tickets"
				"playlist";
		}
		.bento > * { grid-area: var(--grid-area); min-width: 0; }

		@media (min-width: 640px) {
			.bento {
				grid-template-columns: 1fr 1fr;
				grid-template-areas:
					"stats     stats"
					"call      newticket"
					"tickets   tickets"
					"playlist  playlist";
			}
		}
		@media (min-width: 1024px) {
			.bento {
				grid-template-columns: repeat(4, 1fr);
				grid-template-rows: auto auto 1fr;
				grid-template-areas:
					"stats     stats      tickets   tickets"
					"call      newticket  tickets   tickets"
					"playlist  playlist   tickets   tickets";
			}
		}
		#bentoTickets { display: flex; flex-direction: column; }
		#bentoTickets .ticket-list { flex: 1; }
		@media (min-width: 1024px) {
			#bentoTickets .ticket-list { max-height: none; }
		}

		/* ========== RESPONSIVE ========== */
		@media (max-width: 639px) {
			.q-stat-n { font-size: 1.2rem; }
			.q-stats { gap: 4px; }
			.q-stat { min-width: 55px; padding: 6px 2px; }
			.q-head { padding: 8px 10px; font-size: 0.75rem; }
			.q-body { padding: 10px; }
			.call-num { font-size: 1.5rem; }
			.btn-call { padding: 10px; font-size: 0.9rem; }
			.current-call-item { flex-direction: column; align-items: flex-start; gap: 6px; }
			.header-row { flex-direction: column; gap: 6px; align-items: flex-start !important; }
			.header-actions { width: 100%; justify-content: space-between; }
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
					<div class="header-row flex items-center justify-between mb-4">
						<h1 class="text-base font-bold <?=$txt_one_color?>" style="display:flex;align-items:center;gap:8px;">
							<i class="bi bi-people-fill" style="color:#0891b2;font-size:1.1rem;"></i>
							File d'attente
						</h1>
						<div class="header-actions flex items-center gap-3">
							<a href="<?=$app_base?>/queue/display" target="_blank" class="link-display">
								<i class="bi bi-display"></i> Écran public
							</a>
							<div id="sessionBadge"></div>
						</div>
					</div>

					<!-- SESSION CONTROLS -->
					<div id="sessionControls" class="q-card mb-3">
						<div class="q-head">
							<span class="<?=$txt_one_color?>"><i class="bi bi-calendar-event me-2"></i>Session</span>
							<div class="flex gap-2">
								<button onclick="showCreateSession()" class="btn-gen" id="btnCreateSession">
									<i class="bi bi-plus-lg me-1"></i> Nouvelle
								</button>
								<button onclick="closeSession()" class="btn-sm text-red-400" id="btnCloseSession" style="display:none;">
									<i class="bi bi-x-lg me-1"></i> Fermer
								</button>
							</div>
						</div>
						<div class="q-body" id="sessionInfo">
							<p class="text-slate-400 text-xs">Chargement...</p>
						</div>
					</div>
					<div id="createSessionModal" style="display:none;" class="q-card mb-3">
						<div class="q-body">
							<div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
								<div>
									<label class="text-xs text-slate-400 block mb-1">Nom</label>
									<input type="text" id="inputSessionName" placeholder="Ex: Inscription S1 2026" class="q-input">
								</div>
								<div>
									<label class="text-xs text-slate-400 block mb-1">Date</label>
									<input type="date" id="inputSessionDate" value="<?=date('Y-m-d')?>" class="q-input">
								</div>
								<div class="flex gap-2">
									<button onclick="createSession()" class="btn-gen">
										<i class="bi bi-check-lg me-1"></i> Créer
									</button>
									<button onclick="$('#createSessionModal').slideUp(200)" class="btn-sm text-slate-400">Annuler</button>
								</div>
							</div>
						</div>
					</div>

					<div id="queueContent" class="bento" style="display:none;">
						<!-- STATS -->
						<div class="q-card" style="--grid-area: stats">
							<div class="q-head">
								<span class="<?=$txt_one_color?>"><i class="bi bi-bar-chart-line me-2"></i>Stats</span>
							</div>
							<div class="q-body">
								<div class="q-stats">
									<div class="q-stat">
										<div class="q-stat-n <?=$txt_one_color?>" id="statTotal">0</div>
										<div class="q-stat-l">Total</div>
									</div>
									<div class="q-stat">
										<div class="q-stat-n text-yellow-400" id="statWaiting">0</div>
										<div class="q-stat-l">Attente</div>
									</div>
									<div class="q-stat">
										<div class="q-stat-n text-cyan-400" id="statCalled">0</div>
										<div class="q-stat-l">Appelés</div>
									</div>
									<div class="q-stat">
										<div class="q-stat-n text-violet-400" id="statServing">0</div>
										<div class="q-stat-l">En cours</div>
									</div>
									<div class="q-stat">
										<div class="q-stat-n text-green-400" id="statDone">0</div>
										<div class="q-stat-l">Finis</div>
									</div>
									<div class="q-stat">
										<div class="q-stat-n text-red-400" id="statAbsent">0</div>
										<div class="q-stat-l">Absents</div>
									</div>
								</div>
							</div>
						</div>

						<!-- APPEL -->
						<div class="q-card" style="--grid-area: call">
							<div class="q-head">
								<span class="<?=$txt_one_color?>"><i class="bi bi-megaphone-fill me-2"></i>Appel</span>
							</div>
							<div class="q-body">
								<div class="flex items-center gap-3 mb-3">
									<span class="text-slate-400 text-xs">Nombre :</span>
									<input type="number" id="batchSize" value="1" min="1" max="10" class="batch-input">
								</div>
								<button onclick="callNext()" class="btn-call" id="btnCallNext">
									<i class="bi bi-megaphone-fill"></i>
									<span>APPELER</span>
								</button>
								<!-- En cours d'appel -->
								<div id="currentCallSection" style="display:none; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(51,65,85,0.25);">
									<div class="text-xs font-semibold mb-2 text-amber-500"><i class="bi bi-telephone-forward-fill me-1"></i>En cours d'appel</div>
									<div id="currentCallContent"></div>
								</div>
							</div>
						</div>

						<!-- Nouveau ticket -->
						<div class="q-card" style="--grid-area: newticket">
							<div class="q-head">
								<span class="<?=$txt_one_color?>"><i class="bi bi-ticket-perforated me-2"></i>Nouveau ticket</span>
							</div>
							<div class="q-body">
								<div class="mb-2">
									<label class="text-xs text-slate-400 block mb-1">Nom</label>
									<input type="text" id="ticketName" placeholder="Nom de l'étudiant" class="q-input">
								</div>
								<div class="mb-3">
									<label class="text-xs text-slate-400 block mb-1">Mention</label>
									<select id="ticketMention" class="q-input">
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
								<button onclick="generateTicket()" class="btn-gen w-full text-center">
									<i class="bi bi-plus-circle me-1"></i> Générer
								</button>
							</div>
						</div>

						<!-- Playlist -->
						<div class="q-card" style="--grid-area: playlist">
							<div class="q-head">
								<span class="<?=$txt_one_color?>"><i class="bi bi-collection-play me-2"></i>Playlist</span>
								<div class="flex items-center gap-1">
									<button onclick="toggleVideoFullscreen()" id="btnVideoFullscreen" class="btn-sm text-slate-400" title="Vidéo plein écran sur l'affichage">
										<i class="bi bi-arrows-fullscreen" id="videoFullscreenIcon"></i>
									</button>
									<button onclick="refreshPlaylistTitles()" class="btn-sm text-cyan-400" title="Rafraîchir les titres">
										<i class="bi bi-arrow-repeat"></i>
									</button>
									<button onclick="clearPlaylist()" class="btn-sm text-red-400" title="Vider">
										<i class="bi bi-trash3"></i>
									</button>
								</div>
							</div>
							<div class="q-body">
								<div class="flex gap-2 mb-3">
									<input type="text" id="playlistAddUrl" placeholder="URL YouTube..." class="q-input flex-1" onkeydown="if(event.key==='Enter')addToPlaylist()">
									<button onclick="addToPlaylist()" class="btn-gen" style="padding:6px 10px;" title="Ajouter">
										<i class="bi bi-plus-lg"></i>
									</button>
								</div>
								<div id="playlistItems" class="mb-3 rounded-lg border border-slate-700/40" style="max-height:250px;overflow-y:auto;">
									<p class="text-slate-500 text-xs text-center p-3">Aucune vidéo</p>
								</div>
								<div class="flex items-center justify-center gap-2 mb-2">
									<button onclick="playlistPrev()" class="btn-sm text-slate-300" title="Précédent"><i class="bi bi-skip-backward-fill"></i></button>
									<button onclick="toggleMusic()" id="btnMusicToggle" class="btn-sm text-slate-300 flex items-center gap-1">
										<i class="bi bi-play-fill" id="musicToggleIcon"></i>
										<span id="musicToggleText" class="text-xs">Lecture</span>
									</button>
									<button onclick="playlistNext()" class="btn-sm text-slate-300" title="Suivant"><i class="bi bi-skip-forward-fill"></i></button>
								</div>
								<div class="flex items-center gap-2">
									<i class="bi bi-volume-down text-slate-500 text-xs"></i>
									<input type="range" id="musicVolume" min="0" max="100" value="50" class="flex-1" style="accent-color:#06b6d4;" oninput="setMusicVolume(this.value)">
									<span class="text-slate-500 text-xs w-6 text-right" id="musicVolumeLabel">50%</span>
								</div>
								<p class="text-xs text-slate-500 mt-2" id="musicStatus">Aucune vidéo</p>
							</div>
						</div>

						<!-- TICKETS -->
						<div class="q-card" style="--grid-area: tickets" id="bentoTickets">
							<div class="q-head">
								<span class="<?=$txt_one_color?>"><i class="bi bi-list-ol me-2"></i>Tickets</span>
								<select id="filterStatus" onchange="refreshTickets()" class="q-input" style="width:auto;padding:3px 8px;font-size:0.7rem;">
									<option value="all">Tous</option>
									<option value="waiting" selected>Attente</option>
									<option value="called">Appelés</option>
									<option value="serving">En cours</option>
									<option value="done">Finis</option>
									<option value="skipped">Passés</option>
									<option value="absent">Absents</option>
								</select>
							</div>
							<div class="ticket-list" id="ticketList">
								<p class="text-slate-400 text-xs p-3">Chargement...</p>
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
	loadPlaylistState(); // Playlist globale, indépendante des sessions
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
		'<div class="flex items-center gap-2">' +
			'<span class="<?=$txt_one_color?> text-xs font-medium">' + escapeHtml(session.session_name) + '</span>' +
			'<span class="text-slate-500" style="font-size:0.65rem;">' + session.session_date + '</span>' +
		'</div>'
	);
	$('#sessionBadge').html('<span class="s-badge on"><i class="bi bi-circle-fill" style="font-size:6px"></i> Active</span>');
	$('#btnCloseSession').show();
	$('#queueContent').show();
	updateStats(session.stats);
	refreshTickets();
	refreshCurrentCall();
	loadPlaylistState();
}

function showNoSession() {
	$('#sessionInfo').html('<p class="text-slate-500 text-xs">Aucune session active.</p>');
	$('#sessionBadge').html('<span class="s-badge off"><i class="bi bi-circle-fill" style="font-size:6px"></i> Inactive</span>');
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

function markAbsent(ticketId) {
	$.post(QUEUE_API, { action: 'mark_absent', ticket_id: ticketId }, function(res) {
		if (res.success) { Toast.info(res.message); refreshTickets(); refreshStats(); refreshCurrentCall(); }
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
	$('#statAbsent').text(stats.absent || 0);
}

function renderTickets(tickets) {
	if (!tickets || tickets.length === 0) {
		$('#ticketList').html('<p class="text-slate-500 text-xs p-3 text-center">Aucun ticket</p>');
		return;
	}

	const statusLabels = { waiting: 'attente', called: 'appelé', serving: 'en cours', done: 'fini', skipped: 'passé', absent: 'absent' };
	let html = '';

	tickets.forEach(function(t) {
		const num = String(t.ticket_number).padStart(3, '0');
		const statusClass = 'status-' + t.status;

		html += '<div class="t-row">';
		html += '  <div class="flex items-center gap-2">';
		html += '    <span class="t-num">#' + num + '</span>';
		if (t.student_name) html += '<span class="text-slate-400 text-xs">' + escapeHtml(t.student_name) + '</span>';
		html += '  </div>';
		html += '  <div class="flex items-center gap-1 flex-wrap justify-end">';
		html += '    <span class="t-badge ' + statusClass + '">' + (statusLabels[t.status] || t.status) + '</span>';

		// Actions
		if (t.status === 'waiting') {
			html += '<button onclick="callSpecific(' + t.ticket_number + ')" class="btn-sm text-cyan-400" title="Appeler"><i class="bi bi-megaphone"></i></button>';
			html += '<button onclick="markSkipped(' + t.id + ')" class="btn-sm text-slate-400" title="Passer"><i class="bi bi-skip-forward"></i></button>';
		}
		if (t.status === 'called') {
			html += '<button onclick="recallTicket(' + t.ticket_number + ')" class="btn-sm text-amber-400" title="Rappeler"><i class="bi bi-megaphone-fill"></i></button>';
			html += '<button onclick="markDone(' + t.id + ')" class="btn-sm text-green-400" title="Terminé"><i class="bi bi-check-lg"></i></button>';
			html += '<button onclick="markAbsent(' + t.id + ')" class="btn-sm text-red-400" title="Absent"><i class="bi bi-person-x"></i></button>';
		}
		if (t.status === 'serving') {
			html += '<button onclick="markDone(' + t.id + ')" class="btn-sm text-green-400" title="Terminé"><i class="bi bi-check-lg"></i></button>';
			html += '<button onclick="markAbsent(' + t.id + ')" class="btn-sm text-red-400" title="Absent"><i class="bi bi-person-x"></i></button>';
		}
		if (t.status === 'skipped' || t.status === 'absent') {
			html += '<button onclick="callSpecific(' + t.ticket_number + ')" class="btn-sm text-cyan-400" title="Rappeler"><i class="bi bi-arrow-counterclockwise"></i></button>';
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
		$('#currentCallContent').html('<p class="text-slate-500 text-xs text-center">Aucun numéro appelé</p>');
		return;
	}

	let html = '';

	tickets.forEach(function(t) {
		const num = String(t.ticket_number).padStart(3, '0');
		html += '<div class="current-call-item">';
		html += '  <div class="flex items-center gap-2">';
		html += '    <span class="call-num">#' + num + '</span>';
		if (t.student_name) html += '<span class="text-slate-400 text-xs">' + escapeHtml(t.student_name) + '</span>';
		html += '  </div>';
		html += '  <div class="flex items-center gap-1 flex-wrap">';
		html += '    <button onclick="recallTicket(' + t.ticket_number + ')" class="btn-recall" title="Rappeler">';
		html += '      <i class="bi bi-megaphone-fill"></i><span class="hidden sm:inline"> Rappeler</span>';
		html += '    </button>';
		html += '    <button onclick="markDone(' + t.id + ')" class="btn-sm text-green-400" title="Terminé"><i class="bi bi-check-lg"></i></button>';
		html += '    <button onclick="markAbsent(' + t.id + ')" class="btn-sm text-red-400" title="Absent"><i class="bi bi-person-x"></i></button>';
		html += '  </div>';
		html += '</div>';
	});

	if (tickets.length > 1) {
		html += '<div class="flex justify-center mt-2">';
		html += '  <button onclick="recallAllCalled()" class="btn-recall w-full justify-center">';
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
let videoFullscreen = false;

function loadPlaylistState() {
	$.getJSON(QUEUE_API, { action: 'get_music' }, function(res) {
		if (res.success && res.music) {
			currentPlaylist = res.music.playlist || [];
			currentPlaylistIndex = res.music.current_index || 0;
			musicPlaying = !!res.music.playing;
			$('#musicVolume').val(res.music.volume);
			$('#musicVolumeLabel').text(res.music.volume + '%');
			videoFullscreen = !!res.music.video_fullscreen;
			updateVideoFullscreenUI();
			renderPlaylist();
			updateMusicToggleUI();
			updateMusicStatus();
		}
	});
}

function addToPlaylist() {
	const url = $('#playlistAddUrl').val().trim();
	if (!url) { Toast.error('Entrez un lien YouTube.'); return; }

	$.post(QUEUE_API, {
		action: 'add_to_playlist',
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
	$.post(QUEUE_API, {
		action: 'remove_from_playlist',
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
	if (currentPlaylist.length === 0) return;
	$.post(QUEUE_API, {
		action: 'playlist_next'
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
	if (currentPlaylist.length === 0) return;
	$.post(QUEUE_API, {
		action: 'playlist_prev'
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
	$.post(QUEUE_API, {
		action: 'set_playlist_index',
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
	musicPlaying = !musicPlaying;
	sendMusicState();
}

function clearPlaylist() {
	if (!confirm('Vider la liste de lecture ?')) return;
	$.post(QUEUE_API, {
		action: 'clear_playlist'
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
	const currentItem = currentPlaylist[currentPlaylistIndex];
	$.post(QUEUE_API, {
		action: 'set_music',
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

		html += '<div class="playlist-drag-item flex items-center gap-2 px-2 py-1.5 border-b border-slate-700/30 hover:bg-slate-800/50 cursor-pointer ' + activeCls + '" draggable="true" data-idx="' + idx + '" onclick="playPlaylistItem(' + idx + ')">';
		html += '  <div class="drag-handle flex-shrink-0 cursor-grab text-slate-600 hover:text-slate-400 px-0.5" title="Glisser pour réorganiser" onmousedown="event.stopPropagation()" style="touch-action:none"><i class="bi bi-grip-vertical" style="font-size:0.85rem"></i></div>';
		html += '  <div class="w-5 text-center flex-shrink-0">' + iconHtml + '</div>';
		html += '  <img src="https://img.youtube.com/vi/' + escapeHtml(item.video_id) + '/default.jpg" class="rounded object-cover flex-shrink-0" style="width:40px;height:28px" alt="" onerror="this.style.display=\'none\'">';
		html += '  <div class="flex-1 min-w-0"><span class="text-xs text-slate-300 block" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escapeHtml(item.title || item.video_id) + '">' + escapeHtml(item.title || item.video_id) + '</span></div>';
		html += '  <button onclick="event.stopPropagation();removeFromPlaylist(' + idx + ')" class="text-slate-600 hover:text-red-400 flex-shrink-0 p-1"><i class="bi bi-x-lg" style="font-size:0.65rem"></i></button>';
		html += '</div>';
	});

	$('#playlistItems').html(html);
	initPlaylistDragDrop();
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

function toggleVideoFullscreen() {
	videoFullscreen = !videoFullscreen;
	$.post(QUEUE_API, {
		action: 'set_video_fullscreen',
		fullscreen: videoFullscreen ? 1 : 0
	}, function(res) {
		if (res.success) {
			updateVideoFullscreenUI();
			Toast.info(videoFullscreen ? 'Vidéo en plein écran activée' : 'Vidéo plein écran désactivée');
		}
	}, 'json');
}

function updateVideoFullscreenUI() {
	if (videoFullscreen) {
		$('#videoFullscreenIcon').attr('class', 'bi bi-fullscreen-exit');
		$('#btnVideoFullscreen').removeClass('text-slate-400').addClass('text-cyan-400');
	} else {
		$('#videoFullscreenIcon').attr('class', 'bi bi-arrows-fullscreen');
		$('#btnVideoFullscreen').removeClass('text-cyan-400').addClass('text-slate-400');
	}
}

// Utilitaire : échapper le HTML
function escapeHtml(text) {
	if (!text) return '';
	const div = document.createElement('div');
	div.appendChild(document.createTextNode(text));
	return div.innerHTML;
}

// ===== DRAG & DROP PLAYLIST =====
let dragSrcIndex = null;

function initPlaylistDragDrop() {
	const container = document.getElementById('playlistItems');
	if (!container) return;

	const items = container.querySelectorAll('.playlist-drag-item');
	items.forEach(function(item) {
		// Drag start only from handle
		const handle = item.querySelector('.drag-handle');
		if (handle) {
			handle.addEventListener('mousedown', function() {
				item.setAttribute('draggable', 'true');
			});
		}

		item.addEventListener('dragstart', function(e) {
			dragSrcIndex = parseInt(this.dataset.idx);
			this.style.opacity = '0.4';
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', dragSrcIndex);
		});

		item.addEventListener('dragend', function() {
			this.style.opacity = '1';
			container.querySelectorAll('.playlist-drag-item').forEach(function(el) {
				el.classList.remove('border-t-2', 'border-t-cyan-400');
			});
			dragSrcIndex = null;
		});

		item.addEventListener('dragover', function(e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			// Visual feedback
			container.querySelectorAll('.playlist-drag-item').forEach(function(el) {
				el.classList.remove('border-t-2', 'border-t-cyan-400');
			});
			this.classList.add('border-t-2', 'border-t-cyan-400');
		});

		item.addEventListener('dragleave', function() {
			this.classList.remove('border-t-2', 'border-t-cyan-400');
		});

		item.addEventListener('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			const toIndex = parseInt(this.dataset.idx);
			if (dragSrcIndex !== null && dragSrcIndex !== toIndex) {
				reorderPlaylist(dragSrcIndex, toIndex);
			}
			this.classList.remove('border-t-2', 'border-t-cyan-400');
		});
	});
}

function reorderPlaylist(fromIdx, toIdx) {
	$.post(QUEUE_API, {
		action: 'reorder_playlist',
		from_index: fromIdx,
		to_index: toIdx
	}, function(res) {
		if (res.success) {
			currentPlaylist = res.playlist || [];
			currentPlaylistIndex = res.current_index ?? 0;
			renderPlaylist();
			updateMusicStatus();
		} else {
			Toast.error(res.message || 'Erreur');
		}
	}, 'json').fail(function() {
		Toast.error('Erreur de connexion');
	});
}

function refreshPlaylistTitles() {
	if (currentPlaylist.length === 0) {
		Toast.info('Aucune vidéo à rafraîchir.');
		return;
	}
	Toast.info('Récupération des titres...');
	$.post(QUEUE_API, {
		action: 'refresh_playlist_titles'
	}, function(res) {
		if (res.success) {
			currentPlaylist = res.playlist || [];
			renderPlaylist();
			updateMusicStatus();
			Toast.success(res.message);
		} else {
			Toast.error(res.message);
		}
	}, 'json').fail(function() {
		Toast.error('Erreur de connexion');
	});
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
