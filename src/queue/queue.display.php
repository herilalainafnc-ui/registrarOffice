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
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700;800&display=swap" rel="stylesheet">
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }

		body {
			font-family: 'Inter', sans-serif;
			background: #0a1628;
			color: white;
			overflow: hidden;
			height: 100vh;
			cursor: none;
		}

		/* ========== THREE.JS CANVAS ========== */
		#webGLBg {
			position: fixed;
			top: 0; left: 0;
			width: 100%; height: 100%;
			z-index: 0;
		}

		/* ========== CUSTOM CURSOR ========== */
		.custom-cursor {
			position: fixed;
			width: 36px;
			height: 36px;
			border: 2px solid rgba(255,255,255,0.6);
			border-radius: 50%;
			pointer-events: none;
			z-index: 9000;
			transform: translate(-50%, -50%);
			transition: width 0.2s ease, height 0.2s ease, border-color 0.2s ease;
			background: transparent;
		}
		.custom-cursor::before {
			content: "";
			position: absolute;
			top: 50%; left: 50%;
			transform: translate(-50%, -50%);
			width: 5px; height: 5px;
			background: white;
			border-radius: 50%;
		}

		/* ========== LAYOUT ========== */
		.display-container {
			position: relative;
			z-index: 2;
			height: 100vh;
			display: grid;
			grid-template-rows: auto 1fr auto;
		}

		/* ========== HEADER ========== */
		.display-header {
			background: rgba(10, 22, 40, 0.5);
			backdrop-filter: blur(30px);
			-webkit-backdrop-filter: blur(30px);
			border-bottom: 1px solid rgba(255,255,255,0.06);
			padding: 16px 40px;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.display-header h1 {
			font-family: 'Syne', sans-serif;
			font-size: 1.5rem;
			font-weight: 700;
			color: white;
			letter-spacing: -0.01em;
			display: flex;
			align-items: center;
		}
		.display-header .session-name {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 0.85rem;
			color: rgba(255,255,255,0.4);
			font-weight: 400;
			margin-top: 2px;
		}
		.display-clock {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 2rem;
			font-weight: 300;
			color: rgba(255,255,255,0.25);
			letter-spacing: -0.02em;
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
			border-right: 1px solid rgba(255,255,255,0.05);
			padding: 40px;
			display: flex;
			flex-direction: column;
			align-items: center;
		}
		.called-title {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 0.85rem;
			font-weight: 500;
			color: rgba(255,255,255,0.35);
			text-transform: uppercase;
			letter-spacing: 0.2em;
			margin-bottom: 30px;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.called-title i {
			color: rgba(6, 182, 212, 0.7);
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

		/* Adaptive card sizes */
		.called-numbers[data-count="1"] .called-number-card {
			padding: 30px 80px;
			min-width: 350px;
		}
		.called-numbers[data-count="1"] .called-number-card .number {
			font-size: 7rem;
		}
		.called-numbers[data-count="2"] .called-number-card {
			flex: 0 1 45%;
			min-width: 220px;
		}
		.called-numbers[data-count="2"] .called-number-card .number {
			font-size: 5rem;
		}
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
			background: rgba(255, 255, 255, 0.06);
			border: 1px solid rgba(255, 255, 255, 0.12);
			border-radius: 20px;
			padding: 24px 40px;
			text-align: center;
			backdrop-filter: blur(20px);
			-webkit-backdrop-filter: blur(20px);
			animation: slideIn 0.5s ease-out, glow 3s ease-in-out infinite;
			min-width: 200px;
			transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
		}
		.called-number-card .number {
			font-family: 'JetBrains Mono', monospace;
			font-size: 4.5rem;
			font-weight: 800;
			color: white;
			line-height: 1;
			text-shadow: 0 0 40px rgba(14, 165, 233, 0.3);
		}
		.called-number-card .name {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 1rem;
			color: rgba(255,255,255,0.6);
			margin-top: 8px;
			font-weight: 400;
		}
		.called-number-card .mention-label {
			font-family: 'Inter', sans-serif;
			font-size: 0.75rem;
			color: rgba(255,255,255,0.3);
			margin-top: 4px;
			text-transform: uppercase;
			letter-spacing: 0.08em;
		}
		.called-number-card.new-call {
			animation: slideIn 0.5s ease-out, glowStrong 2s ease-in-out infinite, pulse 1.5s ease-in-out 3;
			border-color: rgba(14, 165, 233, 0.5);
			box-shadow: 0 0 60px rgba(14, 165, 233, 0.2);
		}

		@keyframes slideIn {
			from { opacity: 0; transform: translateY(30px) scale(0.9); }
			to { opacity: 1; transform: translateY(0) scale(1); }
		}
		@keyframes glow {
			0%, 100% { box-shadow: 0 0 20px rgba(14, 165, 233, 0.05); }
			50% { box-shadow: 0 0 50px rgba(14, 165, 233, 0.12); }
		}
		@keyframes glowStrong {
			0%, 100% { box-shadow: 0 0 30px rgba(14, 165, 233, 0.1); }
			50% { box-shadow: 0 0 70px rgba(14, 165, 233, 0.3); }
		}
		@keyframes pulse {
			0%, 100% { transform: scale(1); }
			50% { transform: scale(1.03); }
		}

		/* ========== PROCHAINS (droite) ========== */
		.next-section {
			padding: 40px;
			display: flex;
			flex-direction: column;
		}
		.next-title {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 0.85rem;
			font-weight: 500;
			color: rgba(255,255,255,0.25);
			text-transform: uppercase;
			letter-spacing: 0.2em;
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
			border-bottom: 1px solid rgba(255,255,255,0.04);
			transition: all 0.3s;
		}
		.next-item:hover {
			background: rgba(255,255,255,0.03);
		}
		.next-item .number {
			font-family: 'JetBrains Mono', monospace;
			font-size: 1.6rem;
			font-weight: 700;
			color: rgba(255,255,255,0.5);
		}
		.next-item .info {
			text-align: right;
			color: rgba(255,255,255,0.25);
			font-family: 'Inter', sans-serif;
			font-size: 0.85rem;
		}

		/* ========== FOOTER ========== */
		.display-footer {
			background: rgba(10, 22, 40, 0.4);
			backdrop-filter: blur(30px);
			-webkit-backdrop-filter: blur(30px);
			border-top: 1px solid rgba(255,255,255,0.06);
			padding: 14px 40px;
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
			font-family: 'Space Grotesk', sans-serif;
			font-size: 1.4rem;
			font-weight: 600;
		}
		.footer-stat-label {
			font-family: 'Inter', sans-serif;
			font-size: 0.65rem;
			color: rgba(255,255,255,0.25);
			text-transform: uppercase;
			letter-spacing: 0.12em;
		}
		.footer-brand {
			font-family: 'Inter', sans-serif;
			color: rgba(255,255,255,0.15);
			font-size: 0.75rem;
			font-weight: 300;
		}

		/* ========== NO SESSION ========== */
		.no-session {
			position: relative;
			z-index: 2;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			height: 100vh;
			text-align: center;
		}
		.no-session i {
			font-size: 4rem;
			color: rgba(255,255,255,0.1);
			margin-bottom: 20px;
		}
		.no-session h2 {
			font-family: 'Syne', sans-serif;
			font-size: 2rem;
			color: rgba(255,255,255,0.25);
			font-weight: 700;
		}
		.no-session p {
			font-family: 'Inter', sans-serif;
			color: rgba(255,255,255,0.15);
			margin-top: 10px;
			font-weight: 300;
		}
		.no-session .loader-dot {
			display: inline-block;
			width: 8px; height: 8px;
			border-radius: 50%;
			background: rgba(255,255,255,0.2);
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
			top: 0; left: 0;
			width: 100%; height: 100%;
			background: rgba(14, 165, 233, 0.08);
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
			font-family: 'Inter', sans-serif;
			color: rgba(255,255,255,0.15);
			text-align: center;
			font-size: 1.1rem;
			margin-top: 40px;
			font-weight: 300;
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
			font-size: 4rem;
			color: rgba(14, 165, 233, 0.6);
			margin-bottom: 20px;
			animation: pulse 2s ease-in-out infinite;
		}
		.activation-overlay h2 {
			font-family: 'Syne', sans-serif;
			font-size: 1.8rem;
			color: rgba(255,255,255,0.8);
			margin-bottom: 10px;
			font-weight: 600;
		}
		.activation-overlay p {
			font-family: 'Inter', sans-serif;
			color: rgba(255,255,255,0.3);
			font-size: 0.9rem;
			font-weight: 300;
		}

		/* ========== VIDEO & MUSIC ========== */
		.yt-player-wrapper {
			position: fixed;
			bottom: -500px; left: -500px;
			width: 1px; height: 1px;
			overflow: hidden;
			pointer-events: none;
			opacity: 0;
		}
		.video-container {
			width: 100%;
			aspect-ratio: 16/9;
			background: rgba(0,0,0,0.3);
			border-radius: 16px;
			overflow: hidden;
			margin-bottom: 20px;
			border: 1px solid rgba(255,255,255,0.06);
			transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
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
			background: rgba(255,255,255,0.06);
			border: 1px solid rgba(255,255,255,0.08);
			border-radius: 30px;
			padding: 6px 14px;
			backdrop-filter: blur(20px);
			-webkit-backdrop-filter: blur(20px);
			opacity: 0.5;
			transition: opacity 0.3s;
		}
		.music-indicator:hover { opacity: 0.9; }
		.music-indicator i { color: rgba(14, 165, 233, 0.7); font-size: 0.9rem; }
		.music-indicator .bars {
			display: flex;
			align-items: flex-end;
			gap: 2px;
			height: 14px;
		}
		.music-indicator .bar {
			width: 3px;
			background: rgba(14, 165, 233, 0.6);
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

		/* ========== FULLSCREEN VIDEO OVERLAY ========== */
		.video-container.fs-mode {
			position: fixed !important;
			top: 0 !important; left: 0 !important;
			width: 100vw !important; height: 100vh !important;
			z-index: 50 !important;
			border-radius: 0 !important;
			margin: 0 !important;
			background: #000 !important;
			border: none !important;
			aspect-ratio: unset !important;
		}
		.video-container.fs-mode iframe {
			width: 100% !important;
			height: 100% !important;
		}

		/* Fullscreen transition overlay */
		.fs-transition {
			position: fixed;
			top: 0; left: 0;
			width: 100%; height: 100%;
			z-index: 49;
			background: radial-gradient(circle at center, rgba(14,165,233,0.08), #000 70%);
			pointer-events: none;
			opacity: 0;
			transition: opacity 0.5s ease;
		}
		.fs-transition.active {
			opacity: 1;
		}

		/* Called numbers overlay on video */
		.fs-called-overlay {
			position: fixed;
			bottom: 0; left: 0;
			width: 100%;
			z-index: 55;
			pointer-events: none;
			padding: 0 30px 30px;
			opacity: 0;
			transform: translateY(30px);
			transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1) 0.3s, transform 0.5s cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
			visibility: hidden;
		}
		.fs-called-overlay.active {
			opacity: 1;
			transform: translateY(0);
			visibility: visible;
		}
		.fs-called-bar {
			background: rgba(0, 0, 0, 0.6);
			backdrop-filter: blur(24px);
			-webkit-backdrop-filter: blur(24px);
			border: 1px solid rgba(255,255,255,0.1);
			border-radius: 20px;
			padding: 16px 30px;
			display: flex;
			align-items: center;
			gap: 24px;
		}
		.fs-called-label {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 0.7rem;
			font-weight: 500;
			color: rgba(255,255,255,0.4);
			text-transform: uppercase;
			letter-spacing: 0.15em;
			white-space: nowrap;
		}
		.fs-called-numbers {
			display: flex;
			gap: 16px;
			flex-wrap: wrap;
			flex: 1;
			justify-content: center;
		}
		.fs-called-chip {
			background: rgba(14, 165, 233, 0.15);
			border: 1px solid rgba(14, 165, 233, 0.3);
			border-radius: 14px;
			padding: 8px 24px;
			text-align: center;
			animation: slideIn 0.4s ease-out;
		}
		.fs-called-chip.new-call {
			animation: slideIn 0.4s ease-out, glowStrong 2s ease-in-out infinite;
			border-color: rgba(14, 165, 233, 0.6);
		}
		.fs-called-chip .fs-num {
			font-family: 'JetBrains Mono', monospace;
			font-size: 2.2rem;
			font-weight: 800;
			color: white;
			line-height: 1;
			text-shadow: 0 0 30px rgba(14, 165, 233, 0.4);
		}
		.fs-called-chip .fs-name {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 0.75rem;
			color: rgba(255,255,255,0.6);
			margin-top: 2px;
		}
		.fs-called-stats {
			display: flex;
			gap: 16px;
			white-space: nowrap;
		}
		.fs-stat {
			text-align: center;
		}
		.fs-stat-val {
			font-family: 'Space Grotesk', sans-serif;
			font-size: 1.1rem;
			font-weight: 600;
		}
		.fs-stat-lbl {
			font-family: 'Inter', sans-serif;
			font-size: 0.55rem;
			color: rgba(255,255,255,0.3);
			text-transform: uppercase;
			letter-spacing: 0.08em;
		}
		/* Clock overlay in fullscreen */
		.fs-clock {
			position: fixed;
			top: 20px; right: 30px;
			z-index: 56;
			font-family: 'Space Grotesk', sans-serif;
			font-size: 1.3rem;
			font-weight: 300;
			color: rgba(255,255,255,0.25);
			background: rgba(0,0,0,0.4);
			backdrop-filter: blur(16px);
			-webkit-backdrop-filter: blur(16px);
			border-radius: 12px;
			padding: 6px 16px;
			pointer-events: none;
			opacity: 0;
			transform: translateY(-15px);
			transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.25s, transform 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.25s;
			visibility: hidden;
		}
		.fs-clock.active {
			opacity: 1;
			transform: translateY(0);
			visibility: visible;
		}

		/* ========== ANIMATIONS ========== */
		@keyframes fadeInUp {
			from { opacity: 0; transform: translateY(20px); }
			to { opacity: 1; transform: translateY(0); }
		}

		/* ========== RESPONSIVE ========== */
		@media (max-width: 1024px) {
			.display-main {
				grid-template-columns: 1fr;
				overflow-y: auto;
			}
			.called-section {
				border-right: none;
				border-bottom: 1px solid rgba(255,255,255,0.05);
				padding: 24px 20px;
			}
			.next-section { padding: 24px 20px; }
			.called-numbers[data-count="1"] .called-number-card { min-width: 240px; padding: 20px 50px; }
			.called-numbers[data-count="1"] .called-number-card .number { font-size: 5rem; }
			.called-numbers[data-count="2"] .called-number-card .number { font-size: 4rem; }
			.display-header { padding: 14px 20px; }
			.display-header h1 { font-size: 1.2rem; }
			.display-header h1 img { height: 28px !important; margin-right: 8px !important; }
			.display-clock { font-size: 1.5rem; }
			.display-footer { padding: 10px 20px; }
			.footer-stats { gap: 20px; }
			.footer-stat-value { font-size: 1.2rem; }
			body { overflow-y: auto; }
		}

		@media (max-width: 640px) {
			body { overflow-y: auto; height: auto; min-height: 100vh; cursor: auto; }
			.custom-cursor { display: none; }
			.display-container { height: auto; min-height: 100vh; grid-template-rows: auto auto auto; }
			.display-header {
				padding: 10px 12px;
				flex-direction: column;
				gap: 4px;
				text-align: center;
			}
			.display-header h1 { font-size: 1rem; }
			.display-header h1 img { height: 22px !important; margin-right: 6px !important; }
			.display-header .session-name { font-size: 0.7rem; }
			.display-clock { font-size: 1.1rem; }

			.called-section { padding: 16px 10px; }
			.called-title { font-size: 0.75rem; margin-bottom: 16px; letter-spacing: 0.1em; }
			.called-title span { display: none; }
			.called-title::after { content: 'Numéros appelés'; }
			.called-numbers { gap: 10px; }

			.called-number-card { padding: 12px 20px; border-radius: 14px; min-width: 120px !important; }
			.called-number-card .name { font-size: 0.8rem; }
			.called-number-card .mention-label { font-size: 0.6rem; }

			.called-numbers[data-count="1"] .called-number-card { min-width: 160px !important; padding: 16px 30px; }
			.called-numbers[data-count="1"] .called-number-card .number { font-size: 3.5rem; }
			.called-numbers[data-count="2"] .called-number-card { flex: 0 1 45%; min-width: 110px !important; }
			.called-numbers[data-count="2"] .called-number-card .number { font-size: 2.8rem; }
			.called-numbers[data-count="3"] .called-number-card .number,
			.called-numbers[data-count="many"] .called-number-card .number { font-size: 2.2rem; }
			.called-numbers[data-count="3"] .called-number-card,
			.called-numbers[data-count="many"] .called-number-card { flex: 0 1 calc(50% - 6px); min-width: 100px !important; }

			.next-section { padding: 16px 10px; }
			.next-title { font-size: 0.75rem; margin-bottom: 12px; }
			.next-item { padding: 10px 12px; }
			.next-item .number { font-size: 1.3rem; }
			.next-item .info { font-size: 0.7rem; }

			.video-container { border-radius: 10px; margin-bottom: 12px; }

			.display-footer {
				padding: 8px 12px;
				flex-direction: column;
				gap: 4px;
			}
			.footer-stats { gap: 16px; }
			.footer-stat-value { font-size: 1rem; }
			.footer-stat-label { font-size: 0.55rem; }
			.footer-brand { font-size: 0.6rem; }

			.empty-state { font-size: 0.9rem; margin-top: 20px; }
			.no-session i { font-size: 3rem; }
			.no-session h2 { font-size: 1.3rem; }
			.no-session p { font-size: 0.8rem; }

			.activation-overlay h2 { font-size: 1.3rem; }
			.activation-overlay i { font-size: 3.5rem; }
			.activation-overlay p { font-size: 0.8rem; }

			.music-indicator { bottom: 60px; right: 10px; padding: 4px 10px; }
		}

		@media (max-width: 400px) {
			.called-numbers[data-count="1"] .called-number-card .number { font-size: 2.8rem; }
			.called-numbers[data-count="2"] .called-number-card .number { font-size: 2.2rem; }
			.called-numbers[data-count="3"] .called-number-card .number,
			.called-numbers[data-count="many"] .called-number-card .number { font-size: 1.8rem; }
			.display-header h1 { font-size: 0.85rem; }
			.display-clock { font-size: 0.95rem; }
		}

		@media (max-height: 500px) and (orientation: landscape) {
			.display-main { grid-template-columns: 1fr 1fr; overflow-y: auto; }
			.called-section { border-right: 1px solid rgba(255,255,255,0.05); border-bottom: none; padding: 12px; }
			.next-section { padding: 12px; }
			.display-header { padding: 6px 14px; }
			.display-header h1 { font-size: 0.95rem; }
			.display-header h1 img { height: 20px !important; }
			.display-clock { font-size: 1rem; }
			.called-title { font-size: 0.7rem; margin-bottom: 10px; }
			.called-number-card .number { font-size: 2.5rem !important; }
			.called-number-card { padding: 8px 16px !important; min-width: 100px !important; }
			.display-footer { padding: 4px 14px; }
			.footer-stat-value { font-size: 0.95rem; }
		}
	</style>
</head>
<body>

<!-- Three.js gradient background -->
<canvas id="webGLBg"></canvas>
<div class="custom-cursor" id="customCursor"></div>

<div id="displayContainer" class="display-container" style="display:none;">
	<!-- HEADER -->
	<div class="display-header">
		<div>
			<h1><img src="<?=$app_base?>/file/UAZLogo.png" alt="UAZ" style="height:35px;vertical-align:middle;margin-right:12px;">Université Adventiste Zurcher</h1>
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
				<div class="footer-stat-value" style="color: rgba(250, 204, 21, 0.8);" id="dispWaiting">0</div>
				<div class="footer-stat-label">En attente</div>
			</div>
			<div class="footer-stat">
				<div class="footer-stat-value" style="color: rgba(14, 165, 233, 0.8);" id="dispCalled">0</div>
				<div class="footer-stat-label">Appelés</div>
			</div>
			<div class="footer-stat">
				<div class="footer-stat-value" style="color: rgba(34, 197, 94, 0.7);" id="dispDone">0</div>
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
	<p style="color: rgba(255,255,255,0.15); margin-top: 30px; font-size: 0.8rem;">
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

<div class="fs-called-overlay" id="fsCalledOverlay">
	<div class="fs-called-bar">
		<div class="fs-called-label"><i class="bi bi-megaphone-fill"></i>&nbsp; Appelés</div>
		<div class="fs-called-numbers" id="fsCalledNumbers"></div>
		<div class="fs-called-stats" id="fsCalledStats"></div>
	</div>
</div>
<div class="fs-clock" id="fsClock">--:--:--</div>
<div class="fs-transition" id="fsTransition"></div>

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
let isFullscreenVideo = false; // Mode plein écran vidéo

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
			// Exit fullscreen mode too
			if (isFullscreenVideo) {
				isFullscreenVideo = false;
				$('#videoContainer').removeClass('fs-mode').hide();
				$('#fsCalledOverlay').removeClass('active');
				$('#fsClock').removeClass('active');
			}
			lastCalledIds = '';
		}
	}).fail(function() {
		// Silencieux en cas d'erreur réseau — on réessaie au prochain cycle
	});
}

function renderCalledTickets(tickets, isNewCall) {
	if (!tickets || tickets.length === 0) {
		$('#calledNumbers').attr('data-count', '0').html('<p class="empty-state">En attente d\'appel...</p>');
		$('#fsCalledNumbers').html('');
		return;
	}

	// Définir le data-count pour adapter la taille des cartes
	const count = tickets.length;
	let countAttr = count <= 3 ? String(count) : 'many';
	$('#calledNumbers').attr('data-count', countAttr);

	let html = '';
	let fsHtml = '';
	tickets.forEach(function(t, idx) {
		const newClass = isNewCall ? ' new-call' : '';
		// Normal view
		html += '<div class="called-number-card' + newClass + '" style="animation-delay: ' + (idx * 0.15) + 's">';
		html += '  <div class="number">' + escapeHtml(t.formatted) + '</div>';
		if (t.student_name) html += '  <div class="name">' + escapeHtml(t.student_name) + '</div>';
		if (t.mention) html += '  <div class="mention-label">' + escapeHtml(t.mention) + '</div>';
		html += '</div>';
		// Fullscreen overlay chips
		fsHtml += '<div class="fs-called-chip' + newClass + '">';
		fsHtml += '<div class="fs-num">' + escapeHtml(t.formatted) + '</div>';
		if (t.student_name) fsHtml += '<div class="fs-name">' + escapeHtml(t.student_name) + '</div>';
		fsHtml += '</div>';
	});

	$('#calledNumbers').html(html);
	$('#fsCalledNumbers').html(fsHtml);
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

			// Show/hide video container (skip in fullscreen mode)
			if (!isFullscreenVideo) {
				if (shouldPlay && videoId) {
					$('#videoContainer').show();
				} else if (!videoId) {
					$('#videoContainer').hide();
				}
			}

			// Music indicator (hide in fullscreen mode)
			if (shouldPlay && !isFullscreenVideo) {
				$('#musicIndicator').show();
			} else {
				$('#musicIndicator').hide();
			}

			// ---- Fullscreen video mode ----
			const wantFullscreen = !!m.video_fullscreen && videoId !== '';

			if (wantFullscreen && !isFullscreenVideo) {
				// Entering fullscreen — cinematic transition
				isFullscreenVideo = true;
				$('#fsTransition').addClass('active');
				setTimeout(function() {
					$('#videoContainer').show().addClass('fs-mode');
					$('#fsCalledOverlay').addClass('active');
					$('#fsClock').addClass('active');
					updateFsStats();
					setTimeout(function() { $('#fsTransition').removeClass('active'); }, 600);
				}, 150);
			} else if (!wantFullscreen && isFullscreenVideo) {
				// Exiting fullscreen — reverse transition
				isFullscreenVideo = false;
				$('#fsTransition').addClass('active');
				$('#fsCalledOverlay').removeClass('active');
				$('#fsClock').removeClass('active');
				setTimeout(function() {
					$('#videoContainer').removeClass('fs-mode');
					if (!(shouldPlay && videoId)) $('#videoContainer').hide();
					setTimeout(function() { $('#fsTransition').removeClass('active'); }, 600);
				}, 150);
			}

			// Update fullscreen clock
			if (isFullscreenVideo) {
				$('#fsClock').text($('#displayClock').text());
				updateFsStats();
			}
		}
	});
}

function updateFsStats() {
	$('#fsCalledStats').html(
		'<div class="fs-stat"><div class="fs-stat-val" style="color:rgba(250,204,21,0.8)">' + ($('#dispWaiting').text() || '0') + '</div><div class="fs-stat-lbl">Attente</div></div>' +
		'<div class="fs-stat"><div class="fs-stat-val" style="color:rgba(14,165,233,0.8)">' + ($('#dispCalled').text() || '0') + '</div><div class="fs-stat-lbl">Appelés</div></div>' +
		'<div class="fs-stat"><div class="fs-stat-val" style="color:rgba(34,197,94,0.7)">' + ($('#dispDone').text() || '0') + '</div><div class="fs-stat-lbl">Traités</div></div>'
	);
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

<!-- Three.js Gradient Background -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
(function(){
// Custom cursor
const cursor = document.getElementById('customCursor');
if(cursor && window.innerWidth > 640) {
	document.addEventListener('mousemove', e => {
		cursor.style.left = e.clientX + 'px';
		cursor.style.top = e.clientY + 'px';
	});
}

// ========== TOUCH TEXTURE (mouse interaction) ==========
class TouchTexture {
	constructor(size = 64) {
		this.size = size;
		this.maxAge = 120;
		this.radius = 0.15;
		this.trail = [];
		this.canvas = document.createElement('canvas');
		this.canvas.width = this.canvas.height = this.size;
		this.ctx = this.canvas.getContext('2d');
		this.ctx.fillStyle = 'black';
		this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
		this.texture = new THREE.Texture(this.canvas);
		this.canvas.id = 'touchTexture';
		this.canvas.style.display = 'none';
	}
	update() {
		this.clear();
		this.trail.forEach((point, i) => {
			point.age++;
			if (point.age > this.maxAge) { this.trail.splice(i, 1); }
		});
		this.trail.forEach(point => { this.drawTouch(point); });
		this.texture.needsUpdate = true;
	}
	clear() {
		this.ctx.fillStyle = 'black';
		this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
	}
	addTouch(point) {
		let force = 0;
		const last = this.trail[this.trail.length - 1];
		if (last) {
			const dx = last.x - point.x;
			const dy = last.y - point.y;
			const dd = dx * dx + dy * dy;
			force = Math.min(dd * 10000, 1);
		}
		this.trail.push({ x: point.x, y: point.y, age: 0, force });
	}
	drawTouch(point) {
		const pos = { x: point.x * this.size, y: (1 - point.y) * this.size };
		let intensity = 1;
		if (point.age < this.maxAge * 0.3) {
			intensity = easeOutSine(point.age / (this.maxAge * 0.3));
		} else {
			intensity = easeOutSine(1 - (point.age - this.maxAge * 0.3) / (this.maxAge * 0.7));
		}
		intensity *= point.force;
		const radius = this.size * this.radius * intensity;
		const grd = this.ctx.createRadialGradient(pos.x, pos.y, radius * 0.25, pos.x, pos.y, radius);
		grd.addColorStop(0, 'rgba(255, 255, 255, 0.35)');
		grd.addColorStop(1, 'rgba(0, 0, 0, 0.0)');
		this.ctx.beginPath();
		this.ctx.fillStyle = grd;
		this.ctx.arc(pos.x, pos.y, radius, 0, Math.PI * 2);
		this.ctx.fill();
	}
}

function easeOutSine(t) { return Math.sin(t * Math.PI / 2); }

// ========== GRADIENT BACKGROUND ==========
class GradientBackground {
	constructor() {
		this.uniforms = {
			u_time: { value: 0 },
			u_mouse: { value: new THREE.Vector2(0.5, 0.5) },
			u_resolution: { value: new THREE.Vector2(window.innerWidth, window.innerHeight) },
			u_touch: { value: null },
			u_color1: { value: new THREE.Color('#1a0a2e') },
			u_color2: { value: new THREE.Color('#0d2137') },
			u_color3: { value: new THREE.Color('#0a192f') },
			u_color4: { value: new THREE.Color('#162447') }
		};
		this.vertexShader = `
			varying vec2 vUv;
			void main() {
				vUv = uv;
				gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
			}
		`;
		this.fragmentShader = `
			precision highp float;
			uniform float u_time;
			uniform vec2 u_mouse;
			uniform vec2 u_resolution;
			uniform sampler2D u_touch;
			uniform vec3 u_color1;
			uniform vec3 u_color2;
			uniform vec3 u_color3;
			uniform vec3 u_color4;
			varying vec2 vUv;
			
			vec3 mod289(vec3 x) { return x - floor(x * (1.0/289.0)) * 289.0; }
			vec4 mod289(vec4 x) { return x - floor(x * (1.0/289.0)) * 289.0; }
			vec4 permute(vec4 x) { return mod289(((x*34.0)+1.0)*x); }
			vec4 taylorInvSqrt(vec4 r) { return 1.79284291400159 - 0.85373472095314 * r; }
			
			float snoise(vec3 v) {
				const vec2 C = vec2(1.0/6.0, 1.0/3.0);
				const vec4 D = vec4(0.0, 0.5, 1.0, 2.0);
				vec3 i = floor(v + dot(v, C.yyy));
				vec3 x0 = v - i + dot(i, C.xxx);
				vec3 g = step(x0.yzx, x0.xyz);
				vec3 l = 1.0 - g;
				vec3 i1 = min(g.xyz, l.zxy);
				vec3 i2 = max(g.xyz, l.zxy);
				vec3 x1 = x0 - i1 + C.xxx;
				vec3 x2 = x0 - i2 + C.yyy;
				vec3 x3 = x0 - D.yyy;
				i = mod289(i);
				vec4 p = permute(permute(permute(
					i.z + vec4(0.0, i1.z, i2.z, 1.0))
					+ i.y + vec4(0.0, i1.y, i2.y, 1.0))
					+ i.x + vec4(0.0, i1.x, i2.x, 1.0));
				float n_ = 0.142857142857;
				vec3 ns = n_ * D.wyz - D.xzx;
				vec4 j = p - 49.0 * floor(p * ns.z * ns.z);
				vec4 x_ = floor(j * ns.z);
				vec4 y_ = floor(j - 7.0 * x_);
				vec4 x = x_ * ns.x + ns.yyyy;
				vec4 y = y_ * ns.x + ns.yyyy;
				vec4 h = 1.0 - abs(x) - abs(y);
				vec4 b0 = vec4(x.xy, y.xy);
				vec4 b1 = vec4(x.zw, y.zw);
				vec4 s0 = floor(b0)*2.0 + 1.0;
				vec4 s1 = floor(b1)*2.0 + 1.0;
				vec4 sh = -step(h, vec4(0.0));
				vec4 a0 = b0.xzyw + s0.xzyw*sh.xxyy;
				vec4 a1 = b1.xzyw + s1.xzyw*sh.zzww;
				vec3 p0 = vec3(a0.xy, h.x);
				vec3 p1 = vec3(a0.zw, h.y);
				vec3 p2 = vec3(a1.xy, h.z);
				vec3 p3 = vec3(a1.zw, h.w);
				vec4 norm = taylorInvSqrt(vec4(dot(p0,p0), dot(p1,p1), dot(p2,p2), dot(p3,p3)));
				p0 *= norm.x; p1 *= norm.y; p2 *= norm.z; p3 *= norm.w;
				vec4 m = max(0.6 - vec4(dot(x0,x0), dot(x1,x1), dot(x2,x2), dot(x3,x3)), 0.0);
				m = m * m;
				return 42.0 * dot(m*m, vec4(dot(p0,x0), dot(p1,x1), dot(p2,x2), dot(p3,x3)));
			}
			
			void main() {
				vec2 uv = vUv;
				float t = u_time * 0.08;
				float touch = texture2D(u_touch, uv).r;
				
				float n1 = snoise(vec3(uv * 1.5 + t * 0.3, t * 0.2)) * 0.5 + 0.5;
				float n2 = snoise(vec3(uv * 2.0 - t * 0.2, t * 0.15 + 10.0)) * 0.5 + 0.5;
				float n3 = snoise(vec3(uv * 0.8 + t * 0.1, t * 0.25 + 20.0)) * 0.5 + 0.5;
				float n4 = snoise(vec3(uv * 3.0 + t * 0.4, t * 0.1 + 30.0)) * 0.5 + 0.5;
				
				float mouseInfluence = length(uv - u_mouse) * 1.5;
				mouseInfluence = smoothstep(0.0, 1.0, mouseInfluence);
				
				vec3 c1 = mix(u_color1, u_color2, n1);
				vec3 c2 = mix(u_color3, u_color4, n2);
				vec3 color = mix(c1, c2, n3 * mouseInfluence);
				
				color += touch * 0.08;
				color += n4 * 0.03;
				color *= 0.95 + n1 * 0.1;
				
				gl_FragColor = vec4(color, 1.0);
			}
		`;
	}
}

// ========== APP ==========
class App {
	constructor() {
		this.canvas = document.getElementById('webGLBg');
		if(!this.canvas) return;
		try {
			this.renderer = new THREE.WebGLRenderer({ canvas: this.canvas, antialias: true, alpha: false });
		} catch(e) { return; }
		this.renderer.setSize(window.innerWidth, window.innerHeight);
		this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
		this.scene = new THREE.Scene();
		this.camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
		this.touchTexture = new TouchTexture(64);
		this.gradient = new GradientBackground();
		this.gradient.uniforms.u_touch.value = this.touchTexture.texture;
		const geometry = new THREE.PlaneGeometry(2, 2);
		const material = new THREE.ShaderMaterial({
			uniforms: this.gradient.uniforms,
			vertexShader: this.gradient.vertexShader,
			fragmentShader: this.gradient.fragmentShader,
		});
		this.mesh = new THREE.Mesh(geometry, material);
		this.scene.add(this.mesh);
		this.clock = new THREE.Clock();
		window.addEventListener('resize', () => this.onResize());
		window.addEventListener('mousemove', e => this.onMouseMove(e));
		this.animate();
	}
	onResize() {
		this.renderer.setSize(window.innerWidth, window.innerHeight);
		this.gradient.uniforms.u_resolution.value.set(window.innerWidth, window.innerHeight);
	}
	onMouseMove(e) {
		const x = e.clientX / window.innerWidth;
		const y = 1.0 - e.clientY / window.innerHeight;
		this.gradient.uniforms.u_mouse.value.set(x, y);
		this.touchTexture.addTouch({ x, y });
	}
	animate() {
		requestAnimationFrame(() => this.animate());
		this.gradient.uniforms.u_time.value = this.clock.getElapsedTime();
		this.touchTexture.update();
		this.renderer.render(this.scene, this.camera);
	}
}

// Start
try { new App(); } catch(e) { console.warn('WebGL not available:', e); }
})();
</script>
</body>
</html>
