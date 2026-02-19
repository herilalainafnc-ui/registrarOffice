<?php
/**
 * Page Actualités — Espace Étudiant
 * Grille interactive d'annonces universitaires
 * Design inspiré d'une galerie fashion avec GSAP
 */

require('../data/backdb.php');
require('../data/middleware.php');

initMiddleware($dtb);

if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ' . $app_base . '/login');
    exit;
}

// Calculer le chemin racine
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base  = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';

// --- Fetch announcements ---
$annonces = [];
try {
    // Check if table exists
    $check = $dtb->query("SHOW TABLES LIKE 't_annonces'");
    if ($check->rowCount() > 0) {
        $stmt = $dtb->prepare("
            SELECT * FROM t_annonces 
            WHERE is_active = 1 
              AND (expire_date IS NULL OR expire_date >= CURDATE())
            ORDER BY is_pinned DESC, publish_date DESC
        ");
        $stmt->execute();
        $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table doesn't exist yet — will show empty state
}

// Category labels & colors
$catMeta = [
    'info'     => ['label' => 'Information',  'color' => '#0ea5e9', 'icon' => 'bi-info-circle'],
    'event'    => ['label' => 'Événement',    'color' => '#a78bfa', 'icon' => 'bi-calendar-event'],
    'academic' => ['label' => 'Académique',   'color' => '#34d399', 'icon' => 'bi-mortarboard'],
    'urgent'   => ['label' => 'Urgent',       'color' => '#f87171', 'icon' => 'bi-exclamation-triangle'],
    'sport'    => ['label' => 'Sport',        'color' => '#fbbf24', 'icon' => 'bi-trophy'],
    'culture'  => ['label' => 'Culture',      'color' => '#f472b6', 'icon' => 'bi-palette'],
];

// Encode for JS
$annoncesJson = json_encode($annonces, JSON_UNESCAPED_UNICODE);
$catMetaJson  = json_encode($catMeta, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualités — Espace Étudiant</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <style>
        :root {
            --bg: #060e1a;
            --bg-card: rgba(13, 31, 60, 0.55);
            --text: #e8f1f8;
            --text-dim: rgba(142, 184, 212, 0.5);
            --accent: #0ea5e9;
            --accent-2: #8eb8d4;
            --border: rgba(142, 184, 212, 0.1);
            --font-display: 'Syne', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --font-grotesk: 'Space Grotesk', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; user-select: none; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
            height: 100vh;
            cursor: grab;
        }
        body.dragging { cursor: grabbing; }
        body.detail-open { cursor: default; }

        /* ===== PRELOADER ===== */
        #preloader {
            position: fixed; inset: 0;
            background: var(--bg);
            z-index: 200000;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.8s ease;
        }
        #preloaderCanvas {
            width: 200px; height: 200px;
        }

        /* ===== TOP BAR (std-topbar) ===== */
        .std-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 10000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
            height: 56px;
            background: rgba(10, 22, 40, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(14, 165, 233, 0.08);
            opacity: 0;
        }
        .std-topbar .logo-img {
            width: 32px; height: 32px;
            object-fit: contain;
        }
        .std-topbar .page-title {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text);
            letter-spacing: 0.01em;
        }
        .std-nav-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.25s ease;
            text-decoration: none;
            border: 1px solid transparent;
            position: relative;
        }
        .std-nav-icon.home {
            background: rgba(14, 165, 233, 0.1);
            border-color: rgba(14, 165, 233, 0.2);
            color: #38bdf8;
        }
        .std-nav-icon.home:hover {
            background: rgba(14, 165, 233, 0.22);
            border-color: rgba(14, 165, 233, 0.4);
            color: #7dd3fc;
            transform: translateY(-1px);
        }
        .std-nav-icon.logout {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }
        .std-nav-icon.logout:hover {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.35);
            color: #fecaca;
            transform: translateY(-1px);
        }
        .std-nav-icon[data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: -30px; left: 50%;
            transform: translateX(-50%);
            background: rgba(10, 22, 40, 0.95);
            color: #8eb8d4;
            font-size: 0.68rem;
            font-family: var(--font-body);
            padding: 3px 8px;
            border-radius: 6px;
            white-space: nowrap;
            border: 1px solid rgba(14, 165, 233, 0.15);
            pointer-events: none;
            z-index: 200;
        }

        /* ===== HEADER INFO BAR ===== */
        .info-bar {
            position: fixed;
            top: 56px; left: 0; right: 0;
            z-index: 9000;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            column-gap: 1rem;
            padding: 1rem 1.5rem;
            pointer-events: none;
            opacity: 0;
            font-size: 12px;
        }
        .info-bar > * { pointer-events: auto; }
        .info-bar h3 {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 0.4rem;
            letter-spacing: 0.05em;
        }
        .info-bar p, .info-bar li {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-dim);
            line-height: 1.6;
        }
        .info-bar ul { list-style: none; }
        .info-bar a {
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.3s ease;
            font-family: var(--font-mono);
            font-size: 11px;
        }
        .info-bar a:hover { color: var(--accent); }
        .ib-section-1 { grid-column: 1 / span 3; }
        .ib-section-2 { grid-column: 5 / span 2; }
        .ib-section-3 { grid-column: 7 / span 2; }
        .ib-section-4 { grid-column: 10 / span 3; text-align: right; }

        /* ===== VIEWPORT & CANVAS ===== */
        .viewport {
            position: fixed; inset: 0;
            overflow: hidden;
            z-index: 1;
            opacity: 0;
        }
        .canvas-wrapper {
            position: absolute;
            top: 0; left: 0;
            transform-origin: 0 0;
            will-change: transform;
        }
        .grid-container {
            position: relative;
            width: 100%; height: 100%;
        }

        /* ===== GRID ITEMS (NEWS CARDS) ===== */
        .grid-item {
            position: absolute;
            width: 340px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            will-change: transform, opacity;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.5s ease, border-color 0.35s ease, box-shadow 0.35s ease;
            overflow: hidden;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .grid-item:hover {
            border-color: var(--accent);
            box-shadow: 0 0 30px rgba(14, 165, 233, 0.1);
        }
        .grid-item.out-of-view { opacity: 0.08; }

        .grid-item-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }
        .grid-item-gradient {
            width: 100%;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .grid-item-gradient i {
            font-size: 3rem;
            opacity: 0.3;
            z-index: 2;
        }
        .grid-item-gradient::before {
            content: '';
            position: absolute; inset: 0;
            opacity: 0.12;
        }

        .grid-item-body {
            padding: 1.25rem;
        }
        .grid-item-cat {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.6rem;
            padding: 3px 8px;
            border-radius: 3px;
            background: rgba(255,255,255,0.05);
        }
        .grid-item-pin {
            float: right;
            color: #fbbf24;
            font-size: 0.75rem;
        }
        .grid-item-title {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
            margin-bottom: 0.6rem;
            letter-spacing: -0.01em;
        }
        .grid-item-excerpt {
            font-family: var(--font-body);
            font-size: 0.78rem;
            color: var(--text-dim);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .grid-item-footer {
            padding: 0.75rem 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .grid-item-date {
            font-family: var(--font-mono);
            font-size: 10px;
            color: var(--text-dim);
            letter-spacing: 0.04em;
        }
        .grid-item-author {
            font-family: var(--font-mono);
            font-size: 10px;
            color: var(--text-dim);
        }

        /* ===== DETAIL OVERLAY ===== */
        .detail-overlay {
            position: fixed; inset: 0;
            z-index: 50000;
            display: flex;
            opacity: 0;
            pointer-events: none;
            background: rgba(5, 13, 26, 0.85);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
        }
        .detail-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        .detail-left {
            width: 45vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 3rem;
            position: relative;
        }
        .detail-visual {
            width: 100%;
            max-width: 420px;
            aspect-ratio: 4 / 3;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border);
        }
        .detail-visual img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .detail-visual-gradient {
            width: 100%; height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .detail-visual-gradient i {
            font-size: 5rem;
            opacity: 0.2;
        }
        .detail-right {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            padding: 6rem 4rem 4rem 3rem;
        }
        .detail-right::-webkit-scrollbar { width: 3px; }
        .detail-right::-webkit-scrollbar-track { background: transparent; }
        .detail-right::-webkit-scrollbar-thumb { background: rgba(14,165,233,0.2); border-radius: 10px; }

        .detail-number {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.75rem;
        }
        .detail-category {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 3px;
            background: rgba(255,255,255,0.06);
            margin-bottom: 1.25rem;
        }
        .detail-title {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            font-weight: 700;
            color: var(--text);
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 1.5rem;
        }
        .detail-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .detail-meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .detail-meta-label {
            font-family: var(--font-mono);
            font-size: 10px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .detail-meta-value {
            font-family: var(--font-grotesk);
            font-size: 0.85rem;
            color: var(--text);
            font-weight: 500;
        }
        .detail-content {
            font-family: var(--font-body);
            font-size: 0.92rem;
            color: rgba(232, 241, 248, 0.8);
            line-height: 1.8;
            white-space: pre-line;
            user-select: text;
        }
        .detail-content p {
            margin-bottom: 1rem;
        }

        /* Close button */
        .close-btn {
            position: fixed;
            top: 50%; right: 1.5rem;
            transform: translateY(-50%);
            width: 56px; height: 56px;
            background: none; border: none;
            cursor: pointer;
            z-index: 50001;
            opacity: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease;
        }
        .close-btn.active { pointer-events: all; }
        .close-btn:hover { opacity: 0.6 !important; }
        .close-btn svg { width: 48px; height: 48px; transform: rotate(180deg); }

        /* ===== FOOTER BAR ===== */
        .footer-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 9000;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            column-gap: 1rem;
            padding: 1rem 1.5rem;
            pointer-events: none;
            opacity: 0;
        }
        .footer-bar p {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-dim);
        }
        .fb-left { grid-column: 1 / span 4; }
        .fb-right { grid-column: 9 / span 4; text-align: right; }

        /* ===== CONTROLS ===== */
        .controls {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            z-index: 9500;
            opacity: 0;
            gap: 2px;
        }
        .ctrl-percentage {
            background: rgba(240, 240, 240, 0.95);
            padding: 0.5rem 1rem;
            border-radius: 4px 0 0 4px;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 400;
            color: #333;
            min-width: 3.5rem;
            text-align: center;
        }
        .ctrl-switch {
            display: flex;
            gap: 0;
            background: #1a1a1a;
            padding: 0.5rem 0.75rem;
            border-radius: 0 4px 4px 0;
        }
        .ctrl-btn {
            background: none; border: none;
            color: #555;
            cursor: pointer;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 400;
            text-transform: uppercase;
            padding: 4px 10px;
            position: relative;
            transition: color 0.3s ease;
            white-space: nowrap;
        }
        .ctrl-btn.active { color: #f0f0f0; }
        .ctrl-btn:hover { color: #aaa; }
        .ctrl-btn::before {
            content: '';
            position: absolute;
            width: 4px; height: 4px;
            background: #f0f0f0;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
            top: 50%; left: -4px;
            transform: translateY(-50%);
        }
        .ctrl-btn:hover::before { opacity: 1; }

        /* ===== FILTER PILLS ===== */
        .filter-bar {
            position: fixed;
            top: 56px; left: 50%;
            transform: translateX(-50%);
            z-index: 9500;
            display: flex;
            gap: 0.4rem;
            padding: 0.6rem 1rem;
            background: rgba(10, 22, 40, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 12px 12px;
            opacity: 0;
        }
        .filter-pill {
            background: none;
            border: 1px solid rgba(142, 184, 212, 0.12);
            color: var(--text-dim);
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .filter-pill:hover {
            border-color: var(--accent);
            color: var(--text);
        }
        .filter-pill.active {
            background: rgba(14, 165, 233, 0.15);
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ===== VIGNETTE ===== */
        .page-vignette {
            position: fixed; inset: 0;
            pointer-events: none;
            z-index: 9998;
            mix-blend-mode: overlay;
            background:
                linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.3) 15%, transparent 35%),
                linear-gradient(to top, rgba(0,0,0,0.5) 0%, transparent 20%);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 5;
            text-align: center;
            opacity: 0;
        }
        .empty-state i {
            font-size: 4rem;
            color: rgba(14, 165, 233, 0.15);
            margin-bottom: 1.5rem;
        }
        .empty-state h2 {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.75rem;
        }
        .empty-state p {
            font-family: var(--font-body);
            font-size: 0.85rem;
            color: var(--text-dim);
            max-width: 360px;
            line-height: 1.6;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .info-bar, .footer-bar { display: none; }
            .detail-left { width: 40vw; padding: 3rem 2rem; }
            .detail-right { padding: 5rem 2.5rem 3rem 2rem; }
        }
        @media (max-width: 768px) {
            .std-topbar { height: 50px; padding: 0 1rem; }
            .std-topbar .logo-img { width: 28px; height: 28px; }
            .std-topbar .page-title { font-size: 0.78rem; }
            .filter-bar { top: 50px; }
            .detail-overlay { flex-direction: column; }
            .detail-left { width: 100%; height: 35vh; padding: 2rem; }
            .detail-right { height: 65vh; padding: 2rem 1.5rem; }
            .detail-title { font-size: 1.5rem; }
            .close-btn { top: auto; bottom: 1rem; right: 1rem; }
            .controls { display: none; }
        }
        @media (max-width: 480px) {
            .filter-bar { gap: 0.25rem; padding: 0.4rem 0.5rem; }
            .filter-pill { font-size: 9px; padding: 3px 6px; }
            .detail-left { height: 28vh; }
        }
    </style>
</head>
<body>

    <!-- PRELOADER -->
    <div id="preloader">
        <canvas id="preloaderCanvas" width="200" height="200"></canvas>
    </div>

    <!-- TOP BAR -->
    <div class="std-topbar" id="topbar">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ" class="logo-img">
            <span class="page-title">Actualités</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <a href="<?=$app_base?>/student/home" class="std-nav-icon home" data-tooltip="Accueil">
                <i class="bi bi-house-door-fill"></i>
            </a>
            <a href="<?=$app_base?>/logout" class="std-nav-icon logout" data-tooltip="Déconnexion">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar" id="filterBar">
        <button class="filter-pill active" data-filter="all">Tout</button>
        <button class="filter-pill" data-filter="urgent">Urgent</button>
        <button class="filter-pill" data-filter="academic">Académique</button>
        <button class="filter-pill" data-filter="event">Événement</button>
        <button class="filter-pill" data-filter="sport">Sport</button>
        <button class="filter-pill" data-filter="culture">Culture</button>
        <button class="filter-pill" data-filter="info">Info</button>
    </div>

    <!-- INFO BAR (header grid) -->
    <div class="info-bar" id="infoBar">
        <div class="ib-section-1">
            <h3>+Université</h3>
            <p>Université Adventiste Zurcher</p>
            <p>Antsirabe, Madagascar</p>
        </div>
        <div class="ib-section-2">
            <h3>+Navigation</h3>
            <ul>
                <li><a href="<?=$app_base?>/student/home">Accueil</a></li>
                <li><a href="<?=$app_base?>/student/dashboard">Mes Notes</a></li>
                <li><a href="<?=$app_base?>/student/info">Mes Infos</a></li>
            </ul>
        </div>
        <div class="ib-section-3">
            <h3>+Catégories</h3>
            <p id="catCount">—</p>
            <p id="totalCount">—</p>
        </div>
        <div class="ib-section-4">
            <h3>+Portail</h3>
            <p>Infinit Registrar</p>
            <p>Est. <?= date('Y') ?></p>
        </div>
    </div>

    <!-- MAIN VIEWPORT -->
    <div class="viewport" id="viewport">
        <div class="canvas-wrapper" id="canvasWrapper">
            <div class="grid-container" id="gridContainer"></div>
        </div>
    </div>

    <!-- EMPTY STATE -->
    <?php if (empty($annonces)): ?>
    <div class="empty-state" id="emptyState">
        <i class="bi bi-megaphone"></i>
        <h2>Aucune actualité</h2>
        <p>Il n'y a pas encore d'annonces publiées. Les actualités de l'université apparaîtront ici.</p>
    </div>
    <?php endif; ?>

    <!-- DETAIL OVERLAY -->
    <div class="detail-overlay" id="detailOverlay">
        <div class="detail-left" id="detailLeft">
            <div class="detail-visual" id="detailVisual"></div>
        </div>
        <div class="detail-right" id="detailRight"></div>
    </div>

    <!-- CLOSE BUTTON -->
    <button class="close-btn" id="closeBtn">
        <svg width="48" height="48" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7.89873 16L6.35949 14.48L11.8278 9.08H0V6.92H11.8278L6.35949 1.52L7.89873 0L16 8L7.89873 16Z" fill="white"/>
        </svg>
    </button>

    <!-- CONTROLS -->
    <div class="controls" id="controls">
        <div class="ctrl-percentage" id="zoomPercent">60%</div>
        <div class="ctrl-switch">
            <button class="ctrl-btn" onclick="actusGallery.setZoom(0.35)">Zoom −</button>
            <button class="ctrl-btn active" onclick="actusGallery.setZoom(0.6)">Normal</button>
            <button class="ctrl-btn" onclick="actusGallery.setZoom(1.0)">Zoom +</button>
            <button class="ctrl-btn" onclick="actusGallery.fitZoom()">Fit</button>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-bar" id="footerBar">
        <div class="fb-left">
            <p>Espace Étudiant • Actualités & Annonces</p>
        </div>
        <div class="fb-right">
            <p>Infinit Registrar © <?= date('Y') ?></p>
        </div>
    </div>

    <!-- VIGNETTE -->
    <div class="page-vignette"></div>

    <script>
    // ============================================================
    // DATA
    // ============================================================
    const ANNONCES = <?= $annoncesJson ?>;
    const CAT_META = <?= $catMetaJson ?>;

    // Category gradient palettes for card headers
    const CAT_GRADIENTS = {
        info:     'linear-gradient(135deg, #0c2d4a 0%, #0ea5e9 100%)',
        event:    'linear-gradient(135deg, #1a1040 0%, #a78bfa 100%)',
        academic: 'linear-gradient(135deg, #0a2e1f 0%, #34d399 100%)',
        urgent:   'linear-gradient(135deg, #3b0a0a 0%, #f87171 100%)',
        sport:    'linear-gradient(135deg, #2a1e00 0%, #fbbf24 100%)',
        culture:  'linear-gradient(135deg, #2a0a20 0%, #f472b6 100%)'
    };

    // ============================================================
    // PRELOADER
    // ============================================================
    class Preloader {
        constructor() {
            this.canvas = document.getElementById('preloaderCanvas');
            this.ctx = this.canvas.getContext('2d');
            this.startTime = null;
            this.duration = 1800;
            this.animate();
        }

        animate() {
            const cx = this.canvas.width / 2;
            const cy = this.canvas.height / 2;
            let time = 0, lastT = 0;

            const rings = [
                { radius: 15, count: 6 },
                { radius: 28, count: 10 },
                { radius: 42, count: 14 },
                { radius: 56, count: 18 },
                { radius: 70, count: 22 }
            ];

            const loop = (ts) => {
                if (!this.startTime) this.startTime = ts;
                if (!lastT) lastT = ts;
                time += (ts - lastT) * 0.001;
                lastT = ts;

                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

                // Center dot
                this.ctx.beginPath();
                this.ctx.arc(cx, cy, 2.5, 0, Math.PI * 2);
                this.ctx.fillStyle = 'rgba(14, 165, 233, 0.9)';
                this.ctx.fill();

                rings.forEach((ring, ri) => {
                    for (let i = 0; i < ring.count; i++) {
                        const angle = (i / ring.count) * Math.PI * 2;
                        const pulse = Math.sin(time * 2.5 - ri * 0.4) * 2.5;
                        const x = cx + Math.cos(angle) * (ring.radius + pulse);
                        const y = cy + Math.sin(angle) * (ring.radius + pulse);
                        const wave = 0.4 + Math.sin(time * 2.5 - ri * 0.4 + i * 0.2) * 0.6;
                        const active = Math.sin(time * 2.5 - ri * 0.4 + i * 0.2) > 0.6;

                        // Line
                        this.ctx.beginPath();
                        this.ctx.moveTo(cx, cy);
                        this.ctx.lineTo(x, y);
                        this.ctx.lineWidth = 0.6;
                        this.ctx.strokeStyle = active
                            ? `rgba(14, 165, 233, ${wave * 0.6})`
                            : `rgba(142, 184, 212, ${wave * 0.3})`;
                        this.ctx.stroke();

                        // Dot
                        this.ctx.beginPath();
                        this.ctx.arc(x, y, 2, 0, Math.PI * 2);
                        this.ctx.fillStyle = active
                            ? `rgba(14, 165, 233, ${wave})`
                            : `rgba(142, 184, 212, ${wave * 0.6})`;
                        this.ctx.fill();
                    }
                });

                if (ts - this.startTime >= this.duration) {
                    this.complete();
                    return;
                }
                requestAnimationFrame(loop);
            };
            requestAnimationFrame(loop);
        }

        complete() {
            const el = document.getElementById('preloader');
            el.style.opacity = '0';
            setTimeout(() => {
                el.remove();
                actusGallery.start();
            }, 800);
        }
    }

    // ============================================================
    // ACTUS GALLERY
    // ============================================================
    class ActusGallery {
        constructor() {
            this.viewport = document.getElementById('viewport');
            this.wrapper  = document.getElementById('canvasWrapper');
            this.grid     = document.getElementById('gridContainer');
            this.detailOverlay = document.getElementById('detailOverlay');
            this.closeBtn = document.getElementById('closeBtn');

            this.config = {
                itemWidth: 340,
                itemHeight: 360,
                gap: 32,
                zoom: 0.6,
                cols: 0,
                rows: 0
            };

            this.items = [];
            this.filteredItems = [];
            this.currentFilter = 'all';
            this.detailOpen = false;
            this.dragging = false;
            this.dragStart = { x: 0, y: 0 };
            this.position  = { x: 0, y: 0 };
            this.velocity  = { x: 0, y: 0 };
            this.lastMouse = { x: 0, y: 0 };
            this.lastTime  = 0;
            this.animFrame  = null;
        }

        start() {
            this.buildGrid();
            this.setupDrag();
            this.setupFilters();
            this.setupDetail();
            this.playIntro();
        }

        buildGrid() {
            this.grid.innerHTML = '';
            this.items = [];

            const data = this.currentFilter === 'all'
                ? ANNONCES
                : ANNONCES.filter(a => a.category === this.currentFilter);

            this.filteredItems = data;

            if (data.length === 0) {
                const es = document.getElementById('emptyState');
                if (es) gsap.to(es, { opacity: 1, duration: 0.6 });
                return;
            } else {
                const es = document.getElementById('emptyState');
                if (es) gsap.to(es, { opacity: 0, duration: 0.3 });
            }

            // Calculate grid layout
            const vw = window.innerWidth;
            this.config.cols = Math.max(2, Math.ceil(Math.sqrt(data.length * 1.6)));
            this.config.rows = Math.ceil(data.length / this.config.cols);

            const totalW = this.config.cols * (this.config.itemWidth + this.config.gap) - this.config.gap;
            const totalH = this.config.rows * (this.config.itemHeight + this.config.gap) - this.config.gap;

            this.wrapper.style.width  = totalW + 'px';
            this.wrapper.style.height = totalH + 'px';

            data.forEach((annonce, i) => {
                const col = i % this.config.cols;
                const row = Math.floor(i / this.config.cols);
                const x = col * (this.config.itemWidth + this.config.gap);
                const y = row * (this.config.itemHeight + this.config.gap);

                const el = this.createCard(annonce, i);
                el.style.left = x + 'px';
                el.style.top  = y + 'px';
                el.style.width = this.config.itemWidth + 'px';
                el.style.opacity = '0';

                this.grid.appendChild(el);
                this.items.push({ el, annonce, x, y, col, row, index: i });
            });

            // Center the grid
            this.centerGrid(false);

            // Update counters
            const catCount = document.getElementById('catCount');
            const totalCount = document.getElementById('totalCount');
            if (catCount) catCount.textContent = Object.keys(CAT_META).length + ' catégories';
            if (totalCount) totalCount.textContent = ANNONCES.length + ' annonces';
        }

        createCard(a, index) {
            const el = document.createElement('div');
            el.className = 'grid-item';

            const cat = CAT_META[a.category] || CAT_META.info;
            const gradient = CAT_GRADIENTS[a.category] || CAT_GRADIENTS.info;
            const dateStr = a.publish_date ? new Date(a.publish_date).toLocaleDateString('fr-FR', {
                day: '2-digit', month: 'short', year: 'numeric'
            }) : '—';
            const excerpt = a.excerpt || (a.content ? a.content.substring(0, 140) + '…' : '');
            const num = String(index + 1).padStart(2, '0');

            // Image or gradient header
            let headerHTML;
            if (a.image) {
                headerHTML = `<img class="grid-item-image" src="<?=$app_base?>/app/uploads/annonces/${a.image}" alt="">`;
            } else {
                headerHTML = `<div class="grid-item-gradient" style="background:${gradient}">
                    <i class="bi ${cat.icon}"></i>
                </div>`;
            }

            el.innerHTML = `
                ${headerHTML}
                <div class="grid-item-body">
                    <div class="grid-item-cat" style="color:${cat.color}">
                        <i class="bi ${cat.icon}"></i> ${cat.label}
                        ${a.is_pinned == 1 ? '<span class="grid-item-pin"><i class="bi bi-pin-fill"></i></span>' : ''}
                    </div>
                    <div class="grid-item-title">${this.esc(a.title)}</div>
                    <div class="grid-item-excerpt">${this.esc(excerpt)}</div>
                </div>
                <div class="grid-item-footer">
                    <span class="grid-item-date">${num} • ${dateStr}</span>
                    <span class="grid-item-author">${this.esc(a.author || '')}</span>
                </div>
            `;

            el.addEventListener('click', () => {
                if (!this.dragging) this.openDetail(a, index);
            });

            return el;
        }

        centerGrid(animate = true) {
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const tw = parseFloat(this.wrapper.style.width)  * this.config.zoom;
            const th = parseFloat(this.wrapper.style.height) * this.config.zoom;
            const cx = (vw - tw) / 2;
            const cy = (vh - th) / 2;

            if (animate) {
                gsap.to(this.wrapper, {
                    x: cx, y: cy,
                    duration: 1, ease: 'power2.out'
                });
            } else {
                gsap.set(this.wrapper, { x: cx, y: cy, scale: this.config.zoom });
            }
            this.position.x = cx;
            this.position.y = cy;
        }

        // ---- DRAG ----
        setupDrag() {
            let dragThreshold = false;

            const onDown = (e) => {
                if (this.detailOpen) return;
                const pt = e.touches ? e.touches[0] : e;
                this.dragStart = { x: pt.clientX - this.position.x, y: pt.clientY - this.position.y };
                this.lastMouse = { x: pt.clientX, y: pt.clientY };
                this.lastTime = Date.now();
                this.velocity = { x: 0, y: 0 };
                dragThreshold = false;
                document.body.classList.add('dragging');

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('touchend', onUp);
            };

            const onMove = (e) => {
                e.preventDefault();
                const pt = e.touches ? e.touches[0] : e;
                const nx = pt.clientX - this.dragStart.x;
                const ny = pt.clientY - this.dragStart.y;

                if (!dragThreshold) {
                    const dist = Math.abs(pt.clientX - this.lastMouse.x) + Math.abs(pt.clientY - this.lastMouse.y);
                    if (dist > 5) dragThreshold = true;
                    else return;
                }

                this.dragging = true;

                const now = Date.now();
                const dt = Math.max(now - this.lastTime, 1);
                this.velocity.x = (pt.clientX - this.lastMouse.x) / dt * 16;
                this.velocity.y = (pt.clientY - this.lastMouse.y) / dt * 16;
                this.lastMouse = { x: pt.clientX, y: pt.clientY };
                this.lastTime = now;

                this.position.x = nx;
                this.position.y = ny;
                gsap.set(this.wrapper, { x: nx, y: ny });
            };

            const onUp = () => {
                document.body.classList.remove('dragging');
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onUp);

                if (this.dragging) {
                    // Inertia
                    const tx = this.position.x + this.velocity.x * 12;
                    const ty = this.position.y + this.velocity.y * 12;
                    this.position.x = tx;
                    this.position.y = ty;
                    gsap.to(this.wrapper, {
                        x: tx, y: ty,
                        duration: 0.8,
                        ease: 'power3.out'
                    });
                }
                setTimeout(() => { this.dragging = false; }, 50);
            };

            this.viewport.addEventListener('mousedown', onDown);
            this.viewport.addEventListener('touchstart', onDown, { passive: false });
        }

        // ---- FILTERS ----
        setupFilters() {
            document.querySelectorAll('.filter-pill').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    this.currentFilter = btn.dataset.filter;

                    // Animate out
                    gsap.to(this.items.map(i => i.el), {
                        opacity: 0, scale: 0.9,
                        duration: 0.3, ease: 'power2.in',
                        stagger: { amount: 0.2 },
                        onComplete: () => {
                            this.buildGrid();
                            this.playItemsIn();
                        }
                    });
                });
            });
        }

        // ---- DETAIL VIEW ----
        setupDetail() {
            this.closeBtn.addEventListener('click', () => this.closeDetail());
            this.detailOverlay.addEventListener('click', (e) => {
                if (e.target === document.getElementById('detailLeft') || e.target === this.detailOverlay) {
                    this.closeDetail();
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.detailOpen) this.closeDetail();
            });
        }

        openDetail(a, index) {
            this.detailOpen = true;
            document.body.classList.add('detail-open');

            const cat = CAT_META[a.category] || CAT_META.info;
            const gradient = CAT_GRADIENTS[a.category] || CAT_GRADIENTS.info;
            const dateStr = a.publish_date ? new Date(a.publish_date).toLocaleDateString('fr-FR', {
                weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'
            }) : '—';
            const num = String(index + 1).padStart(2, '0');

            // Visual
            const visual = document.getElementById('detailVisual');
            if (a.image) {
                visual.innerHTML = `<img src="<?=$app_base?>/app/uploads/annonces/${a.image}" alt="">`;
            } else {
                visual.innerHTML = `<div class="detail-visual-gradient" style="background:${gradient}">
                    <i class="bi ${cat.icon}"></i>
                </div>`;
            }

            // Content
            const right = document.getElementById('detailRight');
            const content = (a.content || '').replace(/\\n/g, '\n');
            right.innerHTML = `
                <div class="detail-number">${num} / ${String(ANNONCES.length).padStart(2, '0')}</div>
                <div class="detail-category" style="color:${cat.color}">
                    <i class="bi ${cat.icon}"></i> ${cat.label}
                </div>
                <h1 class="detail-title">${this.esc(a.title)}</h1>
                <div class="detail-meta">
                    <div class="detail-meta-item">
                        <span class="detail-meta-label">Date</span>
                        <span class="detail-meta-value">${dateStr}</span>
                    </div>
                    ${a.author ? `<div class="detail-meta-item">
                        <span class="detail-meta-label">Auteur</span>
                        <span class="detail-meta-value">${this.esc(a.author)}</span>
                    </div>` : ''}
                    ${a.is_pinned == 1 ? `<div class="detail-meta-item">
                        <span class="detail-meta-label">Statut</span>
                        <span class="detail-meta-value" style="color:#fbbf24">📌 Épinglé</span>
                    </div>` : ''}
                </div>
                <div class="detail-content">${this.esc(content)}</div>
            `;

            // Animate in
            this.detailOverlay.classList.add('active');
            gsap.fromTo(this.detailOverlay, { opacity: 0 }, { opacity: 1, duration: 0.6, ease: 'power2.out' });

            gsap.fromTo(visual, { scale: 0.85, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.8, ease: 'power2.out', delay: 0.15 });

            gsap.fromTo('.detail-number', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6, ease: 'power2.out', delay: 0.3 });
            gsap.fromTo('.detail-category', { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6, ease: 'power2.out', delay: 0.35 });
            gsap.fromTo('.detail-title', { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 0.7, ease: 'power2.out', delay: 0.4 });
            gsap.fromTo('.detail-meta', { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6, ease: 'power2.out', delay: 0.5 });
            gsap.fromTo('.detail-content', { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6, ease: 'power2.out', delay: 0.6 });

            // Close button
            this.closeBtn.classList.add('active');
            gsap.fromTo(this.closeBtn, { x: 40, opacity: 0 }, { x: 0, opacity: 1, duration: 0.5, ease: 'power2.out', delay: 0.7 });
        }

        closeDetail() {
            if (!this.detailOpen) return;

            gsap.to(this.closeBtn, { x: 40, opacity: 0, duration: 0.3, ease: 'power2.in' });
            gsap.to(this.detailOverlay, {
                opacity: 0, duration: 0.5, ease: 'power2.in',
                onComplete: () => {
                    this.detailOverlay.classList.remove('active');
                    this.closeBtn.classList.remove('active');
                    this.detailOpen = false;
                    document.body.classList.remove('detail-open');
                }
            });
        }

        // ---- ZOOM ----
        setZoom(level) {
            if (this.detailOpen) return;
            this.config.zoom = level;
            gsap.to(this.wrapper, {
                scale: level,
                duration: 1, ease: 'power2.inOut',
                onComplete: () => this.centerGrid()
            });
            document.getElementById('zoomPercent').textContent = Math.round(level * 100) + '%';
            document.querySelectorAll('.ctrl-btn').forEach(b => b.classList.remove('active'));
            if (level === 0.35) document.querySelectorAll('.ctrl-btn')[0].classList.add('active');
            else if (level === 0.6) document.querySelectorAll('.ctrl-btn')[1].classList.add('active');
            else if (level === 1.0) document.querySelectorAll('.ctrl-btn')[2].classList.add('active');
        }

        fitZoom() {
            if (this.detailOpen) return;
            const vw = window.innerWidth;
            const vh = window.innerHeight - 120;
            const gw = parseFloat(this.wrapper.style.width);
            const gh = parseFloat(this.wrapper.style.height);
            const zx = (vw - 80) / gw;
            const zy = (vh - 80) / gh;
            const fit = Math.max(0.15, Math.min(1.5, Math.min(zx, zy)));
            this.config.zoom = fit;
            gsap.to(this.wrapper, {
                scale: fit, duration: 1, ease: 'power2.inOut',
                onComplete: () => this.centerGrid()
            });
            document.getElementById('zoomPercent').textContent = Math.round(fit * 100) + '%';
            document.querySelectorAll('.ctrl-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.ctrl-btn')[3].classList.add('active');
        }

        // ---- INTRO ANIMATION ----
        playIntro() {
            gsap.to(this.viewport, { opacity: 1, duration: 0.5, ease: 'power2.out' });

            this.playItemsIn();

            gsap.to('#topbar',    { opacity: 1, duration: 0.8, ease: 'power2.out', delay: 0.3 });
            gsap.to('#filterBar', { opacity: 1, y: 0, duration: 0.6, ease: 'power2.out', delay: 0.5 });
            gsap.to('#infoBar',   { opacity: 1, duration: 1, ease: 'power2.out', delay: 0.8 });
            gsap.to('#footerBar', { opacity: 1, duration: 1, ease: 'power2.out', delay: 1 });
            gsap.to('#controls',  { opacity: 1, duration: 0.8, ease: 'power2.out', delay: 1.2 });

            <?php if (empty($annonces)): ?>
            gsap.to('#emptyState', { opacity: 1, duration: 0.8, ease: 'power2.out', delay: 0.6 });
            <?php endif; ?>
        }

        playItemsIn() {
            if (this.items.length === 0) return;

            // Animate from center to position
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const gw = parseFloat(this.wrapper.style.width);
            const gh = parseFloat(this.wrapper.style.height);
            const centerX = gw / 2 - this.config.itemWidth / 2;
            const centerY = gh / 2 - this.config.itemHeight / 2;

            this.items.forEach((item, i) => {
                gsap.set(item.el, { left: centerX, top: centerY, scale: 0.7, opacity: 0 });
            });

            gsap.to(this.items.map(it => it.el), {
                left: (i) => this.items[i].x,
                top:  (i) => this.items[i].y,
                scale: 1,
                opacity: 1,
                duration: 0.3,
                ease: 'power2.out',
                stagger: {
                    amount: 1.2,
                    from: 'start',
                    grid: [this.config.rows, this.config.cols]
                }
            });
        }

        esc(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }
    }

    // ============================================================
    // INIT
    // ============================================================
    const actusGallery = new ActusGallery();

    document.addEventListener('DOMContentLoaded', () => {
        new Preloader();
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (actusGallery.detailOpen) return;
        if (e.key === '1') actusGallery.setZoom(0.35);
        if (e.key === '2') actusGallery.setZoom(0.6);
        if (e.key === '3') actusGallery.setZoom(1.0);
        if (e.key === 'f' || e.key === 'F') actusGallery.fitZoom();
    });

    // Resize
    window.addEventListener('resize', () => {
        if (!actusGallery.detailOpen) {
            setTimeout(() => actusGallery.centerGrid(), 100);
        }
    });
    </script>

    <?php include('./student.transition.php'); ?>
</body>
</html>
