<?php
/**
 * Écran d'affichage public - File d'attente UAZ
 * Destiné à être affiché sur un grand écran (TV, projecteur)
 * Pas d'authentification requise - lecture seule
 */

// Calcul du chemin de base (sans head.php car page standalone)
// __DIR__ = ROOT_DIR/src/queue, dirname(dirname(__DIR__)) = ROOT_DIR
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';

// Connexion à la base de données
require_once('../data/backdb.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>File d'attente — Inscriptions UAZ</title>
	<link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700;800&display=swap');

		* { margin: 0; padding: 0; box-sizing: border-box; }

		body {
			font-family: 'Inter', sans-serif;
			background: #0a1628;
			color: white;
			overflow: hidden;
			height: 100vh;
		}

		/* ========== LAYOUT ========== */
		.display-container {
			height: 100vh;
			display: grid;
			grid-template-rows: auto 1fr auto;
		}

		/* ========== HEADER ========== */
		.display-header {
			background: linear-gradient(135deg, #0c1e38, #162a46);
			border-bottom: 2px solid rgba(8, 145, 178, 0.3);
			padding: 20px 40px;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.display-header h1 {
			font-size: 1.8rem;
			font-weight: 800;
			background: linear-gradient(135deg, #06b6d4, #0891b2);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
		}
		.display-header .session-name {
			font-size: 1rem;
			color: #94a3b8;
			font-weight: 400;
		}
		.display-clock {
			font-size: 2rem;
			font-weight: 700;
			color: #e2e8f0;
			font-family: 'JetBrains Mono', monospace;
		}

		/* ========== MAIN CONTENT ========== */
		.display-main {
			display: grid;
			grid-template-columns: 1.2fr 0.8fr;
			gap: 0;
			overflow: hidden;
		}

		/* ========== NUMÉROS APPELÉS (gauche) ========== */
		.called-section {
			background: linear-gradient(180deg, #0c1e38 0%, #0a1628 100%);
			border-right: 2px solid rgba(8, 145, 178, 0.2);
			padding: 40px;
			display: flex;
			flex-direction: column;
			align-items: center;
		}
		.called-title {
			font-size: 1.2rem;
			font-weight: 600;
			color: #94a3b8;
			text-transform: uppercase;
			letter-spacing: 0.15em;
			margin-bottom: 30px;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.called-title i {
			color: #06b6d4;
		}
		.called-numbers {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: center;
			gap: 20px;
			flex: 1;
			width: 100%;
			padding: 10px 0;
		}
		/* 1 seul numéro : très grand */
		.called-numbers[data-count="1"] .called-number-card {
			padding: 30px 80px;
			min-width: 350px;
		}
		.called-numbers[data-count="1"] .called-number-card .number {
			font-size: 7rem;
		}
		/* 2 numéros côte à côte */
		.called-numbers[data-count="2"] .called-number-card {
			flex: 0 1 45%;
			min-width: 220px;
		}
		.called-numbers[data-count="2"] .called-number-card .number {
			font-size: 5rem;
		}
		/* 3+ numéros en grille */
		.called-numbers[data-count="3"] .called-number-card,
		.called-numbers[data-count="many"] .called-number-card {
			flex: 0 1 calc(50% - 12px);
			min-width: 180px;
		}
		.called-numbers[data-count="3"] .called-number-card .number,
		.called-numbers[data-count="many"] .called-number-card .number {
			font-size: 3.8rem;
		}
		.called-number-card {
			background: linear-gradient(135deg, rgba(8, 145, 178, 0.2), rgba(6, 182, 212, 0.1));
			border: 2px solid rgba(6, 182, 212, 0.4);
			border-radius: 20px;
			padding: 20px 40px;
			text-align: center;
			animation: slideIn 0.5s ease-out, glow 2s ease-in-out infinite, pulse 1.5s ease-in-out 3;
			min-width: 200px;
		}
		.called-number-card .number {
			font-family: 'JetBrains Mono', monospace;
			font-size: 4.5rem;
			font-weight: 800;
			color: #06b6d4;
			line-height: 1;
			text-shadow: 0 0 30px rgba(6, 182, 212, 0.5);
		}
		.called-number-card .name {
			font-size: 1.1rem;
			color: #cbd5e1;
			margin-top: 8px;
		}
		.called-number-card .mention-label {
			font-size: 0.8rem;
			color: #64748b;
			margin-top: 4px;
		}
		.called-number-card.new-call {
			animation: slideIn 0.5s ease-out, glow 2s ease-in-out infinite, pulse 1.5s ease-in-out 3;
			border-color: rgba(6, 182, 212, 0.8);
			box-shadow: 0 0 40px rgba(6, 182, 212, 0.4);
		}

		@keyframes slideIn {
			from { opacity: 0; transform: translateY(30px) scale(0.9); }
			to { opacity: 1; transform: translateY(0) scale(1); }
		}
		@keyframes glow {
			0%, 100% { box-shadow: 0 0 20px rgba(6, 182, 212, 0.1); }
			50% { box-shadow: 0 0 40px rgba(6, 182, 212, 0.3); }
		}
		@keyframes pulse {
			0%, 100% { transform: scale(1); }
			50% { transform: scale(1.03); }
		}

		/* ========== PROCHAINS (droite) ========== */
		.next-section {
			background: rgba(15, 23, 42, 0.5);
			padding: 40px;
			display: flex;
			flex-direction: column;
		}
		.next-title {
			font-size: 1.2rem;
			font-weight: 600;
			color: #64748b;
			text-transform: uppercase;
			letter-spacing: 0.15em;
			margin-bottom: 20px;
			text-align: center;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 10px;
		}
		.next-list {
			flex: 1;
			overflow: hidden;
		}
		.next-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 14px 20px;
			border-bottom: 1px solid rgba(51, 65, 85, 0.3);
			transition: all 0.3s;
		}
		.next-item .number {
			font-family: 'JetBrains Mono', monospace;
			font-size: 1.8rem;
			font-weight: 700;
			color: #cbd5e1;
		}
		.next-item .info {
			text-align: right;
			color: #64748b;
			font-size: 0.9rem;
		}

		/* ========== FOOTER ========== */
		.display-footer {
			background: linear-gradient(135deg, #0c1e38, #162a46);
			border-top: 2px solid rgba(8, 145, 178, 0.2);
			padding: 15px 40px;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.footer-stats {
			display: flex;
			gap: 30px;
		}
		.footer-stat {
			text-align: center;
		}
		.footer-stat-value {
			font-size: 1.5rem;
			font-weight: 800;
		}
		.footer-stat-label {
			font-size: 0.7rem;
			color: #64748b;
			text-transform: uppercase;
			letter-spacing: 0.1em;
		}
		.footer-brand {
			color: #334155;
			font-size: 0.8rem;
		}

		/* ========== NO SESSION ========== */
		.no-session {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			height: 100vh;
			text-align: center;
		}
		.no-session i {
			font-size: 5rem;
			color: #1e293b;
			margin-bottom: 20px;
		}
		.no-session h2 {
			font-size: 2rem;
			color: #334155;
			font-weight: 700;
		}
		.no-session p {
			color: #475569;
			margin-top: 10px;
		}
		.no-session .loader-dot {
			display: inline-block;
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background: #334155;
			margin: 0 4px;
			animation: dotPulse 1.4s infinite ease-in-out;
		}
		.no-session .loader-dot:nth-child(2) { animation-delay: 0.2s; }
		.no-session .loader-dot:nth-child(3) { animation-delay: 0.4s; }
		@keyframes dotPulse {
			0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
			40% { transform: scale(1); opacity: 1; }
		}

		/* ========== NOTIFICATION FLASH ========== */
		.flash-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(6, 182, 212, 0.1);
			pointer-events: none;
			animation: flashFade 1s ease-out forwards;
			z-index: 100;
		}
		@keyframes flashFade {
			0% { opacity: 1; }
			100% { opacity: 0; }
		}

		/* ========== PLACEHOLDER ========== */
		.empty-state {
			color: #334155;
			text-align: center;
			font-size: 1.2rem;
			margin-top: 40px;
		}

		/* ========== ACTIVATION OVERLAY ========== */
		.activation-overlay {
			position: fixed;
			top: 0; left: 0; right: 0; bottom: 0;
			background: rgba(10, 22, 40, 0.97);
			z-index: 9999;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			transition: opacity 0.5s;
		}
		.activation-overlay i {
			font-size: 5rem;
			color: #06b6d4;
			margin-bottom: 20px;
			animation: pulse 2s ease-in-out infinite;
		}
		.activation-overlay h2 {
			font-size: 2rem;
			color: #e2e8f0;
			margin-bottom: 10px;
		}
		.activation-overlay p {
			color: #64748b;
			font-size: 1rem;
		}

		/* ========== AMBIENT MUSIC CONTROL ========== */
		.yt-player-wrapper {
			position: fixed;
			bottom: -500px;
			left: -500px;
			width: 1px;
			height: 1px;
			overflow: hidden;
			pointer-events: none;
			opacity: 0;
		}
		/* ========== VIDEO PLAYER ========== */
		.video-container {
			width: 100%;
			aspect-ratio: 16/9;
			background: #000;
			border-radius: 12px;
			overflow: hidden;
			margin-bottom: 20px;
			box-shadow: 0 4px 20px rgba(0,0,0,0.4);
		}
		.video-container iframe {
			width: 100% !important;
			height: 100% !important;
			border: 0;
		}
		.music-indicator {
			position: fixed;
			bottom: 80px;
			right: 20px;
			z-index: 200;
			display: flex;
			align-items: center;
			gap: 6px;
			background: rgba(15, 23, 42, 0.7);
			border: 1px solid rgba(8, 145, 178, 0.2);
			border-radius: 30px;
			padding: 6px 14px;
			backdrop-filter: blur(8px);
			opacity: 0.5;
			transition: opacity 0.3s;
		}
		.music-indicator:hover { opacity: 0.9; }
		.music-indicator i { color: #06b6d4; font-size: 0.9rem; }
		.music-indicator .bars {
			display: flex;
			align-items: flex-end;
			gap: 2px;
			height: 14px;
		}
		.music-indicator .bar {
			width: 3px;
			background: #06b6d4;
			border-radius: 1px;
			animation: barPulse 1.2s ease-in-out infinite;
		}
		.music-indicator .bar:nth-child(1) { height: 6px; animation-delay: 0s; }
		.music-indicator .bar:nth-child(2) { height: 10px; animation-delay: 0.2s; }
		.music-indicator .bar:nth-child(3) { height: 4px; animation-delay: 0.4s; }
		.music-indicator .bar:nth-child(4) { height: 12px; animation-delay: 0.1s; }
		@keyframes barPulse {
			0%, 100% { transform: scaleY(0.4); }
			50% { transform: scaleY(1.2); }
		}
	</style>
</head>
<body>

<div id="displayContainer" class="display-container" style="display:none;">
	<!-- HEADER -->
	<div class="display-header">
		<div>
			<h1><img src="<?=$app_base?>/file/logo-coldbloud.png" alt="UAZ" style="height:42px;vertical-align:middle;margin-right:12px;">Université Adventiste Zurcher</h1>
			<div class="session-name" id="displaySessionName">Inscriptions</div>
		</div>
		<div class="display-clock" id="displayClock">--:--:--</div>
	</div>

	<!-- MAIN -->
	<div class="display-main">
		<!-- Numéros appelés -->
		<div class="called-section">
			<div class="called-title">
				<i class="bi bi-megaphone-fill"></i>
				<span>Numéros appelés — Veuillez vous présenter</span>
			</div>
			<div class="called-numbers" id="calledNumbers">
				<p class="empty-state">En attente d'appel...</p>
			</div>
		</div>

		<!-- Prochains en attente -->
		<div class="next-section">
			<!-- Video player -->
			<div class="video-container" id="videoContainer" style="display:none;">
				<div id="ytPlayerVisible"></div>
			</div>

			<div class="next-title">
				<i class="bi bi-clock-fill"></i>
				<span>Prochains numéros</span>
			</div>
			<div class="next-list" id="nextList">
				<p class="empty-state">Aucun ticket en attente</p>
			</div>
		</div>
	</div>

	<!-- FOOTER -->
	<div class="display-footer">
		<div class="footer-stats">
			<div class="footer-stat">
				<div class="footer-stat-value text-yellow-400" id="dispWaiting">0</div>
				<div class="footer-stat-label">En attente</div>
			</div>
			<div class="footer-stat">
				<div class="footer-stat-value" style="color: #06b6d4;" id="dispCalled">0</div>
				<div class="footer-stat-label">Appelés</div>
			</div>
			<div class="footer-stat">
				<div class="footer-stat-value" style="color: #22c55e;" id="dispDone">0</div>
				<div class="footer-stat-label">Traités</div>
			</div>
		</div>
		<div class="footer-brand">Infinit Registrar • <?=date('d/m/Y')?></div>
	</div>
</div>

<!-- NO SESSION -->
<div id="noSessionScreen" class="no-session">
	<i class="bi bi-hourglass"></i>
	<h2>Aucune session active</h2>
	<p>La file d'attente n'est pas encore ouverte.</p>
	<p style="color: #334155; margin-top: 30px; font-size: 0.8rem;">
		Vérification automatique
		<span class="loader-dot"></span>
		<span class="loader-dot"></span>
		<span class="loader-dot"></span>
	</p>
</div>

<!-- Audio notification (bell sound) -->
<audio id="bellSound" preload="auto">
	<source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgipGJgHZxeouir6SPYj1FaICKhH53d4WHlJ2Yjm9TTFRvi5OJfnh4eISPmZmUhm1YVmKAj5KHfHp7foeRl5WOgGxdW2h/jI+IgnyBhYyUl5SOgW5jZnN+iY6GgIGIjJCUlpCEcW1mb3mEi4qDgYeMjo+Sk5GLgHRwa3R+g4mIg4OLj5CQkpKOiH54cmd0fYKHiYOFi5CPkJCRkYyHf3tydXuBhIeGhImNj4+PkJGMiYR/e3V2e4KEhoaIi4+PjpCQjomGgoB6d3h8g4SGh4mLjY+Pj4+OioeFgn58enp8gIOFhouLjI6Oj4+OioiGg4B+fHt7fYGDhYaIi42Njo6NjIqIhYOBf318fH2AgoPFhomLjIyNjYyLiYeGhIKAf318fH6AgoSGiIqLjIyMi4uKiIaFg4KAf359fX6AgoSGh4mKi4yMi4qJiIaFg4KBgH9+fn+AgoOFh4iKi4uLi4qJiIeGhYOCgYB/f39/gIGDhIaHiYqKi4uKiomIh4aFhIOCgYCAf4CAgYKEhYeIiYqKioqJiYiHhoWEg4OCgYGAgICBgoOEhoeIiYqKiomJiIeHhoWEhIOCgoGBgIGBgoOEhYaHiImJiYmJiIiHhoaFhISEg4KBgYGBgYKDhIWGh4iIiYmJiIiHh4aGhYSEg4OCgoGBgYGCg4SEhoeHiIiIiIiIh4eGhoWFhISEg4KCgoKCgoKDhISFhoeHiIiIiIeHh4aGhoWFhISDg4KCgoKCgoODhIWFhoeHh4iHh4eHhoaGhYWFhISDg4ODg4KDg4OEhIWFhoaHh4eHh4eHhoaGhYWFhYSEhIODg4ODg4ODhISFhYaGh4eHh4eHhoaGhoWFhYWEhISDg4ODg4ODhISEhYWGhoeHh4eHhoaGhoaFhYWFhISEhIODg4OEhISEhYWFhoaGh4eHh4aGhoaGhoWFhYSFhISEhIODg4SEhISFhYWGhoaHh4eHhoaGhoaFhoWFhYSEhISEhISEhISEhIWFhYaGhoeHh4eGhoaGhoaGhYWFhYWEhISEhISEhISEhYWFhYaGhoeHh4aGhoaGhoaFhYWFhYWEhISEhISEhISFhYWFhoaGhoeHh4aGhoaGhoaFhYaFhYWEhISEhISEhIWFhYWFhoaGh4eHh4aGhoaGhoaFhYWFhYWFhISEhISEhIWFhYWGhoaHh4eHhoaGhoaGhoaFhYWFhYWFhISEhISFhYWFhYWGhoaHh4eHhoaGhoaGhoaFhYWFhYWFhYSEhIWFhYWFhYaGhoeHh4eGhoaGhoaGhYWFhYWFhYWFhISFhYWFhYaGhoaHh4eGhoaGhoaGhoWFhYWFhYWFhYWFhYWFhYWGhoaGhoaGhoaGhoaGhoaFhYaFhYWFhYWFhYWFhYWGhoaGhoaGhoaGhoaGhoaGhYWFhYWFhYWFhYW" type="audio/wav">
</audio>

<!-- YouTube Player (hidden) -->
<div class="yt-player-wrapper">
	<div id="ytPlayer"></div>
</div>

<!-- Music indicator -->
<div class="music-indicator" id="musicIndicator" style="display:none;">
	<i class="bi bi-music-note-beamed"></i>
	<div class="bars">
		<div class="bar"></div>
		<div class="bar"></div>
		<div class="bar"></div>
		<div class="bar"></div>
	</div>
</div>

<!-- Activation overlay (nécessaire pour activer l'audio/synthèse vocale) -->
<div class="activation-overlay" id="activationOverlay" onclick="activateAudio()">
	<i class="bi bi-volume-up-fill"></i>
	<h2>Cliquez pour activer l'affichage</h2>
	<p>Un clic est requis pour activer le son et les annonces vocales</p>
</div>

<script>
const API_URL = '<?=$app_base?>/queue/api';
let lastCalledIds = ''; // Pour détecter les changements
let pollInterval = null;
let sharedAudioCtx = null; // AudioContext partagé
let audioActivated = false;

// =====================================================================
// HORLOGE
// =====================================================================
function updateClock() {
	const now = new Date();
	const h = String(now.getHours()).padStart(2, '0');
	const m = String(now.getMinutes()).padStart(2, '0');
	const s = String(now.getSeconds()).padStart(2, '0');
	$('#displayClock').text(h + ':' + m + ':' + s);
}
setInterval(updateClock, 1000);
updateClock();

// =====================================================================
// POLLING TEMPS RÉEL
// =====================================================================
function pollStatus() {
	$.getJSON(API_URL, { action: 'public_status' }, function(res) {
		if (res.success && res.active) {
			$('#displayContainer').show();
			$('#noSessionScreen').hide();

			$('#displaySessionName').text(res.session.name + ' — ' + res.session.date);

			// Vérifier si de nouveaux numéros ont été appelés
			const calledTickets = res.called_tickets || [];
			const currentIds = calledTickets.map(t => t.ticket_number + '@' + (t.called_at || '')).join(',');

			if (currentIds !== lastCalledIds) {
				// Nouveaux appels détectés
				const isNewCall = lastCalledIds !== '' && calledTickets.length > 0;
				renderCalledTickets(calledTickets, isNewCall);

				// Flash visuel + son
				if (isNewCall) {
					flashScreen();
					playBell(calledTickets);
				}

				lastCalledIds = currentIds;
			}

			// Prochains en attente
			renderNextTickets(res.waiting_tickets || []);

			// Stats
			if (res.stats) {
				$('#dispWaiting').text(res.stats.waiting || 0);
				$('#dispCalled').text(res.stats.called || 0);
				$('#dispDone').text(res.stats.done || 0);
			}
		} else {
			$('#displayContainer').hide();
			$('#noSessionScreen').show();
			lastCalledIds = '';
		}
	}).fail(function() {
		// Silencieux en cas d'erreur réseau — on réessaie au prochain cycle
	});
}

function renderCalledTickets(tickets, isNewCall) {
	if (!tickets || tickets.length === 0) {
		$('#calledNumbers').attr('data-count', '0').html('<p class="empty-state">En attente d\'appel...</p>');
		return;
	}

	// Définir le data-count pour adapter la taille des cartes
	const count = tickets.length;
	let countAttr = count <= 3 ? String(count) : 'many';
	$('#calledNumbers').attr('data-count', countAttr);

	let html = '';
	tickets.forEach(function(t, idx) {
		const newClass = isNewCall ? ' new-call' : '';
		html += '<div class="called-number-card' + newClass + '" style="animation-delay: ' + (idx * 0.15) + 's">';
		html += '  <div class="number">' + escapeHtml(t.formatted) + '</div>';
		if (t.student_name) html += '  <div class="name">' + escapeHtml(t.student_name) + '</div>';
		if (t.mention) html += '  <div class="mention-label">' + escapeHtml(t.mention) + '</div>';
		html += '</div>';
	});

	$('#calledNumbers').html(html);
}

function renderNextTickets(tickets) {
	if (!tickets || tickets.length === 0) {
		$('#nextList').html('<p class="empty-state">Aucun ticket en attente</p>');
		return;
	}

	let html = '';
	tickets.forEach(function(t) {
		const num = t.formatted || String(t.ticket_number).padStart(3, '0');
		html += '<div class="next-item">';
		html += '  <span class="number">#' + num + '</span>';
		html += '  <span class="info">';
		if (t.student_name) html += escapeHtml(t.student_name);
		if (t.mention) html += '<br>' + escapeHtml(t.mention);
		html += '</span>';
		html += '</div>';
	});

	$('#nextList').html(html);
}

function flashScreen() {
	const flash = $('<div class="flash-overlay"></div>');
	$('body').append(flash);
	setTimeout(function() { flash.remove(); }, 1000);
}

// Convertir un nombre en texte français naturel (0-999)
function numberToFrench(n) {
	n = parseInt(n, 10);
	if (isNaN(n) || n < 0) return '';
	if (n === 0) return 'zéro';

	const units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
		'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
	const tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

	function convertBelow100(num) {
		if (num < 20) return units[num];
		const t = Math.floor(num / 10);
		const u = num % 10;
		if (t === 7 || t === 9) {
			// 70-79 = soixante-dix..., 90-99 = quatre-vingt-dix...
			const sub = (t === 7) ? 10 + u : 10 + u;
			const base = tens[t];
			if (sub === 11 && t === 7) return base + ' et onze';
			if (sub < 20) return base + '-' + units[sub];
			return base + '-' + units[sub];
		}
		if (u === 0) {
			if (t === 8) return 'quatre-vingts';
			return tens[t];
		}
		if (u === 1 && (t === 2 || t === 3 || t === 4 || t === 5 || t === 6)) {
			return tens[t] + ' et un';
		}
		return tens[t] + '-' + units[u];
	}

	if (n < 100) return convertBelow100(n);

	const h = Math.floor(n / 100);
	const remainder = n % 100;
	let result = '';
	if (h === 1) {
		result = 'cent';
	} else {
		result = units[h] + ' cent';
		if (remainder === 0) result += 's'; // deux cents, trois cents...
	}
	if (remainder > 0) {
		result += ' ' + convertBelow100(remainder);
	}
	return result;
}

function playBell(tickets) {
	if (!audioActivated) return;
	try {
		// Utiliser le contexte audio partagé
		if (!sharedAudioCtx) {
			sharedAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
		}
		if (sharedAudioCtx.state === 'suspended') {
			sharedAudioCtx.resume();
		}
		const audioCtx = sharedAudioCtx;
		
		// Séquence de carillon d'appel
		playTone(audioCtx, 1200, 0, 0.25, 0.6, 'sine');
		playTone(audioCtx, 1200, 0.15, 0.15, 0.4, 'sine');
		playTone(audioCtx, 880, 0.35, 0.3, 0.5, 'sine');
		playTone(audioCtx, 660, 0.55, 0.3, 0.5, 'sine');
		playTone(audioCtx, 880, 0.85, 0.5, 0.5, 'sine');

		// Annonce vocale des numéros (après le carillon)
		if (tickets && tickets.length > 0 && 'speechSynthesis' in window) {
			setTimeout(function() {
				// Annuler toute synthèse en cours (corrige un bug Chrome où la queue se bloque)
				speechSynthesis.cancel();

				const nums = tickets.map(function(t) {
					// Convertir en français naturel : 010 → "dix", 041 → "quarante et un"
					const num = parseInt(t.formatted, 10);
					return 'numéro ' + numberToFrench(num);
				}).join(', ');
				const text = 'Votre Attention! ' + nums + '. Veuillez vous présenter à l\'entrer.';
				
				const utterance = new SpeechSynthesisUtterance(text);
				utterance.lang = 'fr-FR';
				utterance.rate = 0.9;
				utterance.pitch = 1.05;
				utterance.volume = 1.0;
				
				// Préférer une voix française si disponible
				const voices = speechSynthesis.getVoices();
				const frVoice = voices.find(v => v.lang.startsWith('fr'));
				if (frVoice) utterance.voice = frVoice;
				
				speechSynthesis.speak(utterance);
			}, 1800);
		}
	} catch(e) {
		console.warn('playBell error:', e);
		try {
			const audio = document.getElementById('bellSound');
			if (audio) { audio.currentTime = 0; audio.play().catch(function() {}); }
		} catch(e2) {}
	}
}

function playTone(audioCtx, frequency, startTime, duration, volume, type) {
	const oscillator = audioCtx.createOscillator();
	const gainNode = audioCtx.createGain();
	
	oscillator.connect(gainNode);
	gainNode.connect(audioCtx.destination);
	
	oscillator.type = type || 'sine';
	oscillator.frequency.value = frequency;
	
	const vol = volume || 0.3;
	gainNode.gain.setValueAtTime(vol, audioCtx.currentTime + startTime);
	gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + startTime + duration);
	
	oscillator.start(audioCtx.currentTime + startTime);
	oscillator.stop(audioCtx.currentTime + startTime + duration);
}

function escapeHtml(text) {
	if (!text) return '';
	const div = document.createElement('div');
	div.appendChild(document.createTextNode(text));
	return div.innerHTML;
}

// Démarrer le polling toutes les 2 secondes
pollInterval = setInterval(pollStatus, 2000);
pollStatus(); // Appel initial

// =====================================================================
// ACTIVATION AUDIO (nécessaire pour les navigateurs modernes)
// =====================================================================
function activateAudio() {
	// 1. Créer et activer l'AudioContext partagé
	try {
		sharedAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
		sharedAudioCtx.resume();
		// Jouer un son muet pour débloquer
		const osc = sharedAudioCtx.createOscillator();
		const gain = sharedAudioCtx.createGain();
		gain.gain.value = 0;
		osc.connect(gain);
		gain.connect(sharedAudioCtx.destination);
		osc.start();
		osc.stop(sharedAudioCtx.currentTime + 0.1);
	} catch(e) { console.warn('AudioContext init failed:', e); }

	// 2. Activer la synthèse vocale avec un warmup silencieux
	if ('speechSynthesis' in window) {
		speechSynthesis.cancel();
		const warmup = new SpeechSynthesisUtterance('');
		warmup.volume = 0;
		warmup.lang = 'fr-FR';
		speechSynthesis.speak(warmup);

		// Test audible rapide
		setTimeout(function() {
			speechSynthesis.cancel();
			const test = new SpeechSynthesisUtterance('Syst\u00e8me activ\u00e9');
			test.lang = 'fr-FR';
			test.rate = 0.9;
			test.volume = 0.5;
			const voices = speechSynthesis.getVoices();
			const frVoice = voices.find(v => v.lang.startsWith('fr'));
			if (frVoice) test.voice = frVoice;
			speechSynthesis.speak(test);
		}, 200);
	}

	// 3. Activer l'audio HTML
	try {
		const audio = document.getElementById('bellSound');
		if (audio) { audio.volume = 0; audio.play().then(() => audio.pause()).catch(()=>{}); }
	} catch(e) {}

	audioActivated = true;

	// Masquer l'overlay avec animation
	const overlay = document.getElementById('activationOverlay');
	overlay.style.opacity = '0';
	setTimeout(function() { overlay.style.display = 'none'; }, 500);
}

// Précharger les voix pour la synthèse vocale
if ('speechSynthesis' in window) {
	speechSynthesis.getVoices();
	speechSynthesis.onvoiceschanged = function() { speechSynthesis.getVoices(); };
}

// =====================================================================
// LECTEUR YOUTUBE (contrôlé par l'admin via API)
// =====================================================================
let ytPlayer = null;
let ytReady = false;
let ytCurrentVideoId = '';
let ytTargetPlaying = false;
let ytTargetVolume = 50;
let ytDucking = false; // true pendant les annonces pour bloquer le rétablissement du volume

// Charger l'API YouTube IFrame
const ytScript = document.createElement('script');
ytScript.src = 'https://www.youtube.com/iframe_api';
document.head.appendChild(ytScript);

function onYouTubeIframeAPIReady() {
	// Hidden player for audio bell (kept for sounds)
	ytPlayer = new YT.Player('ytPlayer', {
		height: '1',
		width: '1',
		videoId: '',
		playerVars: {
			autoplay: 0,
			controls: 0,
			loop: 1,
			modestbranding: 1,
			rel: 0,
			showinfo: 0,
			fs: 0
		},
		events: {
			'onReady': function() {
				ytReady = true;
				pollMusicState();
			},
			'onStateChange': function(event) {}
		}
	});

	// Visible player for video display
	window.ytVisiblePlayer = new YT.Player('ytPlayerVisible', {
		height: '360',
		width: '640',
		videoId: '',
		playerVars: {
			autoplay: 0,
			controls: 0,
			loop: 0,
			modestbranding: 1,
			rel: 0,
			showinfo: 0,
			fs: 0,
			iv_load_policy: 3
		},
		events: {
			'onReady': function() {
				window.ytVisibleReady = true;
			},
			'onStateChange': function(event) {
				if (event.data === YT.PlayerState.ENDED && ytTargetPlaying) {
					// Auto-advance playlist
					$.post(API_URL, { action: 'playlist_auto_next' }, function(res) {
						if (res.success && res.action === 'loop') {
							window.ytVisiblePlayer.seekTo(0);
							window.ytVisiblePlayer.playVideo();
						}
						// If 'next', the next poll will pick up the new video
					}, 'json').fail(function() {
						window.ytVisiblePlayer.seekTo(0);
						window.ytVisiblePlayer.playVideo();
					});
				}
			}
		}
	});
}

function pollMusicState() {
	$.getJSON(API_URL, { action: 'get_music' }, function(res) {
		if (res.success && res.music) {
			const m = res.music;
			const videoId = m.video_id || '';
			const shouldPlay = !!m.playing && videoId !== '';
			const volume = m.volume || 50;

			// ---- Visible video player ----
			if (window.ytVisibleReady && window.ytVisiblePlayer) {
				// Change video if needed
				if (videoId && videoId !== ytCurrentVideoId) {
					ytCurrentVideoId = videoId;
					window.ytVisiblePlayer.loadVideoById({ videoId: videoId, startSeconds: 0 });
					if (!shouldPlay) {
						setTimeout(function() { window.ytVisiblePlayer.pauseVideo(); }, 500);
					}
				}

				// Play / Pause
				if (shouldPlay && window.ytVisiblePlayer.getPlayerState && window.ytVisiblePlayer.getPlayerState() !== YT.PlayerState.PLAYING) {
					window.ytVisiblePlayer.playVideo();
				} else if (!shouldPlay && window.ytVisiblePlayer.getPlayerState && window.ytVisiblePlayer.getPlayerState() === YT.PlayerState.PLAYING) {
					window.ytVisiblePlayer.pauseVideo();
				}

				// Stop if no video
				if (!videoId && ytCurrentVideoId) {
					window.ytVisiblePlayer.stopVideo();
					ytCurrentVideoId = '';
				}

				// Volume (skip during ducking)
				ytTargetVolume = volume;
				if (typeof window.ytVisiblePlayer.setVolume === 'function' && !ytDucking) {
					window.ytVisiblePlayer.setVolume(volume);
				}

				ytTargetPlaying = shouldPlay;
			}

			// Show/hide video container
			if (shouldPlay && videoId) {
				$('#videoContainer').show();
			} else if (!videoId) {
				$('#videoContainer').hide();
			}

			// Music indicator
			if (shouldPlay) {
				$('#musicIndicator').show();
			} else {
				$('#musicIndicator').hide();
			}
		}
	});
}

// Polling de l'état musique toutes les 3 secondes
setInterval(pollMusicState, 3000);

// Baisser le volume YouTube pendant toute la durée de l'annonce (carillon + voix)
const _originalPlayBell = playBell;
playBell = function(tickets) {
	if (window.ytVisibleReady && window.ytVisiblePlayer && ytTargetPlaying && typeof window.ytVisiblePlayer.setVolume === 'function') {
		ytDucking = true;
		window.ytVisiblePlayer.setVolume(Math.max(5, Math.round(ytTargetVolume * 0.15)));

		function restoreVolume() {
			if (window.ytVisibleReady && window.ytVisiblePlayer && ytTargetPlaying) {
				window.ytVisiblePlayer.setVolume(ytTargetVolume);
			}
			ytDucking = false;
		}

		if ('speechSynthesis' in window) {
			const checkSpeechEnd = setInterval(function() {
				if (!speechSynthesis.speaking && !speechSynthesis.pending) {
					clearInterval(checkSpeechEnd);
					setTimeout(restoreVolume, 800);
				}
			}, 300);
		}

		setTimeout(function() {
			if (ytDucking) restoreVolume();
		}, 15000);
	}
	_originalPlayBell(tickets);
};

// Activer l'autoplay au premier clic (politique navigateur)
document.addEventListener('click', function() {
	if (window.ytVisibleReady && window.ytVisiblePlayer && ytTargetPlaying) {
		try { window.ytVisiblePlayer.playVideo(); } catch(e) {}
	}
}, { once: true });
</script>
</body>
</html>
