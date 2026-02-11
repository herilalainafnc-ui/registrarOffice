<?php
/**
 * Page Mes Informations - Espace Étudiant
 * Design Hyper Scroll 3D - Style Bleu Nuit
 */

require('../data/backdb.php');
require('../data/middleware.php');

initMiddleware($dtb);

if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ./index');
    exit;
}

$studentInfo = getStudentInfo();
if (!$studentInfo) {
    header('Location: ./student.home');
    exit;
}

// Helper
function safe($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
function safeOr($val, $fallback = '—') {
    $v = trim($val ?? '');
    return $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $fallback;
}

// Calcul niveau
$annee = (int)($studentInfo['annee_etude'] ?? 0);
if ($annee === 0) $niveau = 'Remise à niveau';
elseif ($annee >= 1 && $annee <= 3) $niveau = 'Licence ' . $annee;
elseif ($annee === 4) $niveau = 'Master 1';
elseif ($annee === 5) $niveau = 'Master 2';
else $niveau = 'Niveau ' . $annee;

// Statut étudiant
$newStd = (int)($studentInfo['new_student'] ?? 0);
if ($newStd === 1) $typeEtudiant = 'Nouveau';
elseif ($newStd === 10) $typeEtudiant = 'Spécial';
else $typeEtudiant = 'Ancien';

// Status hébergement
$statusLabel = safeOr($studentInfo['status']);

// Abonnement cantine
$abonnement = ((int)($studentInfo['abonment'] ?? 0) === 1) ? 'Abonné' : 'Non abonné';

// Situation familiale
$situationF = safeOr($studentInfo['situationf']);

// Suspension
$isSuspended = !empty($studentInfo['suspended']) && $studentInfo['suspended'] == 1;
$isRetrait = !empty($studentInfo['retrait_universite']) && $studentInfo['retrait_universite'] >= 2;

// Photo
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';

$photoUrl = '';
if (!empty($studentInfo['image_student'])) {
    $photoUrl = $app_base . '/app/photosetudiants/' . rawurlencode($studentInfo['image_student']);
}

// Region lookup
$regionName = '—';
if (!empty($studentInfo['student_region'])) {
    $stmtR = $dtb->prepare("SELECT region FROM region WHERE id = :id LIMIT 1");
    $stmtR->execute(['id' => $studentInfo['student_region']]);
    $rRow = $stmtR->fetch();
    if ($rRow) $regionName = htmlspecialchars($rRow['region']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Informations - Espace Étudiant</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.33/dist/lenis.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg: #050d1a;
            --card-bg: rgba(13, 31, 60, 0.45);
            --text: #e8f1f8;
            --accent: #0ea5e9;
            --accent-2: #8eb8d4;
            --accent-red: #ef4444;
            --border: rgba(142, 184, 212, 0.12);
            --font-display: 'Syne', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-code: 'JetBrains Mono', monospace;
            --font-grotesk: 'Space Grotesk', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            overflow: hidden;
            width: 100vw;
            height: 100vh;
        }

        /* ===== POST-PROCESSING OVERLAYS ===== */
        .scanlines {
            position: fixed;
            inset: 0;
            background: linear-gradient(to bottom,
                rgba(255,255,255,0), rgba(255,255,255,0) 50%,
                rgba(0,0,0,0.15) 50%, rgba(0,0,0,0.15));
            background-size: 100% 4px;
            pointer-events: none;
            z-index: 100;
        }
        .vignette {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle, transparent 40%, rgba(5,13,26,0.9) 120%);
            z-index: 101;
            pointer-events: none;
        }
        .noise {
            position: fixed;
            inset: 0;
            z-index: 102;
            opacity: 0.04;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* ===== HUD ===== */
        .hud {
            position: fixed;
            inset: 1.5rem;
            z-index: 50;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-family: var(--font-code);
            font-size: 10px;
            color: rgba(142, 184, 212, 0.4);
            text-transform: uppercase;
        }
        .hud-top, .hud-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .hud strong { color: var(--accent); }
        .hud-line {
            flex: 1;
            height: 1px;
            background: rgba(142, 184, 212, 0.15);
            margin: 0 1rem;
            position: relative;
        }
        .hud-line::after {
            content: '';
            position: absolute;
            right: 0; top: -2px;
            width: 5px; height: 5px;
            background: var(--accent);
        }
        .center-nav {
            align-self: flex-start;
            margin-top: auto;
            margin-bottom: auto;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
        }

        /* ===== TOP BAR ===== */
        .std-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 60;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
            height: 56px;
            background: rgba(10, 22, 40, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(14, 165, 233, 0.08);
            pointer-events: auto;
        }
        .std-topbar .logo-img {
            width: 32px;
            height: 32px;
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
            width: 36px;
            height: 36px;
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
            bottom: -30px;
            left: 50%;
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

        /* ===== 3D SCENE ===== */
        .viewport {
            position: fixed;
            inset: 0;
            perspective: 1000px;
            overflow: hidden;
            z-index: 1;
        }
        .world {
            position: absolute;
            top: 50%;
            left: 50%;
            transform-style: preserve-3d;
            will-change: transform;
        }
        .item {
            position: absolute;
            left: 0; top: 0;
            backface-visibility: hidden;
            transform-origin: center center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== CARDS ===== */
        .card {
            width: 360px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            position: relative;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 0 0 1px rgba(0,0,0,0.3), 0 20px 60px rgba(0,0,0,0.4);
            transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            transform: translate(-50%, -50%);
            border-radius: 2px;
        }
        .card.wide { width: 420px; }
        .card.photo-card { width: 300px; }

        @media (hover: hover) {
            .card:hover {
                border-color: var(--accent);
                box-shadow: 0 0 40px rgba(14, 165, 233, 0.15), 0 20px 60px rgba(0,0,0,0.5);
                background: rgba(13, 31, 60, 0.7);
            }
        }

        /* Corner decorations */
        .card::before, .card::after {
            content: '';
            position: absolute;
            width: 12px; height: 12px;
            border: 1px solid transparent;
            transition: 0.4s;
        }
        .card::before {
            top: -1px; left: -1px;
            border-top-color: rgba(142, 184, 212, 0.3);
            border-left-color: rgba(142, 184, 212, 0.3);
        }
        .card::after {
            bottom: -1px; right: -1px;
            border-bottom-color: rgba(142, 184, 212, 0.3);
            border-right-color: rgba(142, 184, 212, 0.3);
        }
        .card:hover::before, .card:hover::after {
            width: 100%; height: 100%;
            border-color: var(--accent);
        }

        .card-header {
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-section { font-family: var(--font-display); font-size: 0.65rem; font-weight: 700; color: var(--accent); letter-spacing: 0.12em; text-transform: uppercase; }
        .card-icon { width: 10px; height: 10px; background: var(--accent); }

        .card h2 {
            font-family: var(--font-display);
            font-size: 1.6rem;
            line-height: 1;
            margin: 0 0 1rem;
            text-transform: uppercase;
            font-weight: 800;
            color: #fff;
        }

        /* Data rows */
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 0.4rem 0;
            border-bottom: 1px solid rgba(142, 184, 212, 0.05);
        }
        .data-row:last-child { border-bottom: none; }
        .data-label {
            font-family: var(--font-code);
            font-size: 0.65rem;
            color: rgba(142, 184, 212, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            flex-shrink: 0;
        }
        .data-value {
            font-family: var(--font-grotesk);
            font-size: 0.85rem;
            color: var(--text);
            text-align: right;
            margin-left: 1rem;
            word-break: break-word;
        }
        .data-value.accent { color: var(--accent); font-weight: 600; }
        .data-value.warn { color: #fbbf24; }
        .data-value.danger { color: #ef4444; }

        .card-footer {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
            font-family: var(--font-code);
            font-size: 0.6rem;
            color: rgba(142, 184, 212, 0.3);
            display: flex;
            justify-content: space-between;
        }

        /* Photo card */
        .photo-wrapper {
            width: 100%;
            aspect-ratio: 3/4;
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
            position: relative;
            background: rgba(10, 22, 40, 0.5);
        }
        .photo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(0.1) contrast(1.05);
            transition: filter 0.4s;
        }
        .card:hover .photo-wrapper img {
            filter: grayscale(0) contrast(1.1);
        }
        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(142, 184, 212, 0.2);
            font-size: 4rem;
        }
        .photo-label {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 0.5rem;
            background: linear-gradient(transparent, rgba(5,13,26,0.9));
            font-family: var(--font-code);
            font-size: 0.6rem;
            color: var(--accent);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Big floating text */
        .big-text {
            font-family: var(--font-display);
            font-size: 12vw;
            font-weight: 800;
            color: transparent;
            -webkit-text-stroke: 2px rgba(142, 184, 212, 0.06);
            text-transform: uppercase;
            white-space: nowrap;
            transform: translate(-50%, -50%);
            pointer-events: none;
            letter-spacing: -0.5rem;
        }

        /* Stars */
        .star {
            position: absolute;
            width: 2px; height: 2px;
            background: rgba(142, 184, 212, 0.6);
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 2px;
            font-family: var(--font-code);
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .badge-active { background: rgba(14, 165, 233, 0.15); color: var(--accent); border: 1px solid rgba(14, 165, 233, 0.3); }
        .badge-warn { background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Scroll proxy */
        .scroll-proxy {
            height: 8000vh;
            position: absolute;
            width: 100%;
            z-index: -1;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .card { width: 300px !important; padding: 1.25rem; }
            .card h2 { font-size: 1.2rem; }
            .big-text { font-size: 20vw; }
            .hud { display: none; }
            .std-topbar { height: 50px; padding: 0 1rem; }
            .std-topbar .logo-img { width: 28px; height: 28px; }
            .std-topbar .page-title { font-size: 0.78rem; }
            .data-value { font-size: 0.75rem; }
        }
        @media (max-width: 480px) {
            .card { width: 260px !important; padding: 1rem; }
            .card h2 { font-size: 1rem; }
        }
    </style>
</head>
<body>

    <!-- OVERLAYS -->
    <div class="scanlines"></div>
    <div class="vignette"></div>
    <div class="noise"></div>

    <!-- HUD -->
    <div class="hud">
        <div class="hud-top">
            <span>SYS.STUDENT_DATA</span>
            <div class="hud-line"></div>
            <span>ID: <strong><?= safe($studentInfo['student_id']) ?></strong></span>
        </div>
        <div class="center-nav">
            SCROLL DEPTH // <strong id="vel-readout">0.00</strong>
        </div>
        <div class="hud-bottom">
            <span>COORD: <strong id="coord">000</strong></span>
            <div class="hud-line"></div>
            <span><?= safe($studentInfo['annee_scolaire'] ?? '') ?> [<?= $niveau ?>]</span>
        </div>
    </div>

    <!-- TOP BAR -->
    <div class="std-topbar">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ" class="logo-img">
            <span class="page-title">Mes Informations</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <a href="./student.home" class="std-nav-icon home" data-tooltip="Accueil">
                <i class="bi bi-house-door-fill"></i>
            </a>
            <a href="../app/logout" class="std-nav-icon logout" data-tooltip="Déconnexion">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- 3D WORLD -->
    <div class="viewport" id="viewport">
        <div class="world" id="world"></div>
    </div>

    <div class="scroll-proxy"></div>

    <script>
    // ============================================================
    // Student Data — injected from PHP
    // ============================================================
    const STUDENT = {
        id: <?= json_encode(safe($studentInfo['student_id'])) ?>,
        nom: <?= json_encode(safe($studentInfo['student_nom'])) ?>,
        prenom: <?= json_encode(safe($studentInfo['student_prenom'])) ?>,
        photo: <?= json_encode($photoUrl) ?>,
        dateNaissance: <?= json_encode(safeOr($studentInfo['dateNaissance'])) ?>,
        lieuNaissance: <?= json_encode(safeOr($studentInfo['lieuNaissance'])) ?>,
        sex: <?= json_encode(safeOr($studentInfo['sex'])) ?>,
        nationalite: <?= json_encode(safeOr($studentInfo['nationalite'])) ?>,
        numCin: <?= json_encode(safeOr($studentInfo['num_cin'])) ?>,
        cinDate: <?= json_encode(safeOr($studentInfo['cin_date_delivre'])) ?>,
        cinRegion: <?= json_encode(safeOr($studentInfo['cin_region'])) ?>,
        paysOrigine: <?= json_encode(safeOr($studentInfo['pays_origine'])) ?>,
        tel: <?= json_encode(safeOr($studentInfo['student_tel'])) ?>,
        email: <?= json_encode(safeOr($studentInfo['student_email'])) ?>,
        adresse: <?= json_encode(safeOr($studentInfo['student_adresse'])) ?>,
        region: <?= json_encode($regionName) ?>,
        mention: <?= json_encode(safeOr($studentInfo['etude_envisage'])) ?>,
        option: <?= json_encode(safeOr($studentInfo['etude_option'])) ?>,
        niveau: <?= json_encode($niveau) ?>,
        annee_scolaire: <?= json_encode(safeOr($studentInfo['annee_scolaire'])) ?>,
        status: <?= json_encode($statusLabel) ?>,
        type: <?= json_encode($typeEtudiant) ?>,
        abonnement: <?= json_encode($abonnement) ?>,
        religion: <?= json_encode(safeOr($studentInfo['religion'])) ?>,
        situationf: <?= json_encode($situationF) ?>,
        conjoint: <?= json_encode(safeOr($studentInfo['nom_conjoint'])) ?>,
        nbEnfant: <?= json_encode(safeOr($studentInfo['nb_enfant'])) ?>,
        fatherName: <?= json_encode(safeOr($studentInfo['father_name'])) ?>,
        fatherProf: <?= json_encode(safeOr($studentInfo['father_prof'])) ?>,
        motherName: <?= json_encode(safeOr($studentInfo['mother_name'])) ?>,
        motherProf: <?= json_encode(safeOr($studentInfo['mother_prof'])) ?>,
        parentTel: <?= json_encode(safeOr($studentInfo['parent_tel'])) ?>,
        parentAdresse: <?= json_encode(safeOr($studentInfo['parent_adresse'])) ?>,
        sponsorNom: <?= json_encode(safeOr($studentInfo['sponsor_nom'])) ?>,
        sponsorPrenom: <?= json_encode(safeOr($studentInfo['sponsor_prenom'])) ?>,
        sponsorTel: <?= json_encode(safeOr($studentInfo['sponsor_tel'])) ?>,
        sponsorAdresse: <?= json_encode(safeOr($studentInfo['sponsor_adresse'])) ?>,
        dateEntry: <?= json_encode(safeOr($studentInfo['date_entry'])) ?>,
        isSuspended: <?= $isSuspended ? 'true' : 'false' ?>,
        isRetrait: <?= $isRetrait ? 'true' : 'false' ?>,
        suspended: <?= json_encode($isSuspended) ?>,
        motifSuspension: <?= json_encode(safeOr($studentInfo['motif_suspension'] ?? '')) ?>,
        graduated: <?= json_encode((int)($studentInfo['graduated'] ?? 0)) ?>
    };

    // ============================================================
    // Configuration
    // ============================================================
    const CONFIG = {
        zGap: 900,
        camSpeed: 2.5,
        starCount: 120,
        loopSize: 0
    };

    const state = { scroll: 0, velocity: 0, targetSpeed: 0, mouseX: 0, mouseY: 0 };

    const world = document.getElementById('world');
    const viewport = document.getElementById('viewport');
    const items = [];

    // ============================================================
    // Build Cards Data
    // ============================================================
    const CARDS = [
        // 0: Big text
        { type: 'text', text: STUDENT.prenom },
        // 1: Photo card
        { type: 'photo' },
        // 2: Big text
        { type: 'text', text: 'IDENTITÉ' },
        // 3: Identity card
        {
            type: 'card',
            section: 'IDENTITÉ',
            title: STUDENT.nom,
            wide: true,
            rows: [
                ['Prénom', STUDENT.prenom],
                ['Nom', STUDENT.nom],
                ['Date de naissance', STUDENT.dateNaissance],
                ['Lieu de naissance', STUDENT.lieuNaissance],
                ['Sexe', STUDENT.sex],
                ['Nationalité', STUDENT.nationalite],
                ['Pays d\'origine', STUDENT.paysOrigine]
            ],
            footer: ['MATRICULE: ' + STUDENT.id, 'TYPE: ' + STUDENT.type]
        },
        // 4: CIN card
        {
            type: 'card',
            section: 'PIÈCE D\'IDENTITÉ',
            title: 'CIN',
            rows: [
                ['N° CIN', STUDENT.numCin, 'accent'],
                ['Date délivré', STUDENT.cinDate],
                ['Région CIN', STUDENT.cinRegion]
            ],
            footer: ['DOCUMENT', 'OFFICIEL']
        },
        // 5: Big text
        { type: 'text', text: 'CONTACT' },
        // 6: Contact card
        {
            type: 'card',
            section: 'CONTACT',
            title: 'COORD',
            wide: true,
            rows: [
                ['Téléphone', STUDENT.tel, 'accent'],
                ['Email', STUDENT.email],
                ['Adresse', STUDENT.adresse],
                ['Région', STUDENT.region]
            ],
            footer: ['COMMUNICATION', 'CHANNELS']
        },
        // 7: Big text
        { type: 'text', text: 'ÉTUDES' },
        // 8: Academic card
        {
            type: 'card',
            section: 'PARCOURS ACADÉMIQUE',
            title: STUDENT.niveau,
            wide: true,
            rows: [
                ['Mention', STUDENT.mention, 'accent'],
                ['Parcours', STUDENT.option],
                ['Niveau', STUDENT.niveau],
                ['Année scolaire', STUDENT.annee_scolaire],
                ['Statut', STUDENT.status],
                ['Type', STUDENT.type],
                ['Religion', STUDENT.religion],
                ['Cantine', STUDENT.abonnement]
            ],
            footer: ['SESSION', STUDENT.annee_scolaire]
        },
        // 9: Big text
        { type: 'text', text: 'FAMILLE' },
        // 10: Family card
        {
            type: 'card',
            section: 'FAMILLE',
            title: 'PARENTS',
            wide: true,
            rows: [
                ['Père', STUDENT.fatherName],
                ['Profession père', STUDENT.fatherProf],
                ['Mère', STUDENT.motherName],
                ['Profession mère', STUDENT.motherProf],
                ['Tél. parents', STUDENT.parentTel, 'accent'],
                ['Adresse parents', STUDENT.parentAdresse]
            ],
            footer: ['TUTEURS', 'LÉGAUX']
        },
        // 11: Situation familiale card
        {
            type: 'card',
            section: 'SITUATION FAMILIALE',
            title: 'CIVIL',
            rows: [
                ['Situation', STUDENT.situationf],
                ['Conjoint', STUDENT.conjoint],
                ['Enfants', STUDENT.nbEnfant]
            ],
            footer: ['ÉTAT', 'CIVIL']
        },
        // 12: Big text
        { type: 'text', text: 'GARANT' },
        // 13: Sponsor card
        {
            type: 'card',
            section: 'GARANT FINANCIER',
            title: 'SPONSOR',
            wide: true,
            rows: [
                ['Nom', STUDENT.sponsorNom],
                ['Prénom', STUDENT.sponsorPrenom],
                ['Téléphone', STUDENT.sponsorTel, 'accent'],
                ['Adresse', STUDENT.sponsorAdresse]
            ],
            footer: ['FINANCIAL', 'GUARANTOR']
        },
        // 14: Big text
        { type: 'text', text: STUDENT.nom }
    ];

    // Add suspension card if applicable
    if (STUDENT.isSuspended) {
        CARDS.splice(9, 0, {
            type: 'card',
            section: '⚠ SUSPENSION',
            title: 'SUSPENDU',
            rows: [
                ['Statut', 'SUSPENDU', 'danger'],
                ['Motif', STUDENT.motifSuspension]
            ],
            footer: ['ALERT', 'ACTIVE']
        });
    }

    const totalItems = CARDS.length;
    CONFIG.loopSize = (totalItems + CONFIG.starCount) * CONFIG.zGap;

    // ============================================================
    // Build Scene
    // ============================================================
    function init() {
        for (let i = 0; i < totalItems; i++) {
            const data = CARDS[i];
            const el = document.createElement('div');
            el.className = 'item';

            if (data.type === 'text') {
                const txt = document.createElement('div');
                txt.className = 'big-text';
                txt.innerText = data.text;
                el.appendChild(txt);
                items.push({ el, type: 'text', x: 0, y: 0, rot: 0, baseZ: -i * CONFIG.zGap });

            } else if (data.type === 'photo') {
                const card = document.createElement('div');
                card.className = 'card photo-card';
                let photoHTML = '';
                if (STUDENT.photo) {
                    photoHTML = `<img src="${STUDENT.photo}" alt="Photo" onerror="this.parentNode.innerHTML='<div class=\\'photo-placeholder\\'><i class=\\'bi bi-person\\'></i></div>'">`;
                } else {
                    photoHTML = `<div class="photo-placeholder"><i class="bi bi-person"></i></div>`;
                }
                card.innerHTML = `
                    <div class="card-header">
                        <span class="card-section">PHOTO</span>
                        <div class="card-icon"></div>
                    </div>
                    <div class="photo-wrapper">
                        ${photoHTML}
                        <div class="photo-label">${STUDENT.id} // ${STUDENT.type}</div>
                    </div>
                    <h2 style="font-size:1.1rem;margin:0;">${STUDENT.prenom} ${STUDENT.nom}</h2>
                    <div class="card-footer">
                        <span>${STUDENT.mention}</span>
                        <span>${STUDENT.niveau}</span>
                    </div>
                `;
                el.appendChild(card);

                const angle = (i / totalItems) * Math.PI * 4;
                const x = Math.cos(angle) * (window.innerWidth * 0.2);
                const y = Math.sin(angle) * (window.innerHeight * 0.15);
                items.push({ el, type: 'card', x, y, rot: (Math.random() - 0.5) * 10, baseZ: -i * CONFIG.zGap });

            } else {
                const card = document.createElement('div');
                card.className = 'card' + (data.wide ? ' wide' : '');

                let rowsHTML = '';
                data.rows.forEach(function(r) {
                    const cls = r[2] ? ' ' + r[2] : '';
                    rowsHTML += `<div class="data-row"><span class="data-label">${r[0]}</span><span class="data-value${cls}">${r[1]}</span></div>`;
                });

                card.innerHTML = `
                    <div class="card-header">
                        <span class="card-section">${data.section}</span>
                        <div class="card-icon"></div>
                    </div>
                    <h2>${data.title}</h2>
                    ${rowsHTML}
                    <div class="card-footer">
                        <span>${data.footer[0]}</span>
                        <span>${data.footer[1]}</span>
                    </div>
                    <div style="position:absolute;bottom:1.5rem;right:1.5rem;font-family:var(--font-display);font-size:3rem;opacity:0.04;font-weight:900;">${String(i).padStart(2,'0')}</div>
                `;
                el.appendChild(card);

                const angle = (i / totalItems) * Math.PI * 6;
                const x = Math.cos(angle) * (window.innerWidth * 0.22);
                const y = Math.sin(angle) * (window.innerHeight * 0.18);
                const rot = (Math.random() - 0.5) * 15;
                items.push({ el, type: 'card', x, y, rot, baseZ: -i * CONFIG.zGap });
            }

            world.appendChild(el);
        }

        // Stars
        for (let i = 0; i < CONFIG.starCount; i++) {
            const el = document.createElement('div');
            el.className = 'star';
            world.appendChild(el);
            items.push({
                el, type: 'star',
                x: (Math.random() - 0.5) * 3000,
                y: (Math.random() - 0.5) * 3000,
                baseZ: -Math.random() * CONFIG.loopSize
            });
        }

        // Mouse parallax
        window.addEventListener('mousemove', function(e) {
            state.mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
            state.mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
        });
    }
    init();

    // ============================================================
    // Lenis Smooth Scroll
    // ============================================================
    const lenis = new Lenis({
        smooth: true,
        lerp: 0.08,
        direction: 'vertical',
        gestureDirection: 'vertical',
        smoothTouch: true
    });

    lenis.on('scroll', function(ev) {
        state.scroll = ev.scroll;
        state.targetSpeed = ev.velocity;
    });

    // ============================================================
    // RAF Loop
    // ============================================================
    const feedbackVel = document.getElementById('vel-readout');
    let lastTime = 0;

    function raf(time) {
        lenis.raf(time);

        const delta = time - lastTime;
        lastTime = time;

        state.velocity += (state.targetSpeed - state.velocity) * 0.1;
        feedbackVel.innerText = Math.abs(state.velocity).toFixed(2);
        document.getElementById('coord').innerText = state.scroll.toFixed(0);

        // Camera tilt
        const tiltX = state.mouseY * 4 - state.velocity * 0.4;
        const tiltY = state.mouseX * 4;
        world.style.transform = 'rotateX(' + tiltX + 'deg) rotateY(' + tiltY + 'deg)';

        // Dynamic perspective
        const baseFov = 1000;
        const fov = baseFov - Math.min(Math.abs(state.velocity) * 8, 500);
        viewport.style.perspective = fov + 'px';

        // Items
        const cameraZ = state.scroll * CONFIG.camSpeed;
        const modC = CONFIG.loopSize;

        items.forEach(function(item) {
            let relZ = item.baseZ + cameraZ;
            let vizZ = ((relZ % modC) + modC) % modC;
            if (vizZ > 500) vizZ -= modC;

            let alpha = 1;
            if (vizZ < -3500) alpha = 0;
            else if (vizZ < -2500) alpha = (vizZ + 3500) / 1000;
            if (vizZ > 100 && item.type !== 'star') alpha = 1 - ((vizZ - 100) / 400);
            if (alpha < 0) alpha = 0;

            item.el.style.opacity = alpha;

            if (alpha > 0) {
                let trans = 'translate3d(' + item.x + 'px, ' + item.y + 'px, ' + vizZ + 'px)';

                if (item.type === 'star') {
                    const stretch = Math.max(1, Math.min(1 + Math.abs(state.velocity) * 0.08, 8));
                    trans += ' scale3d(1, 1, ' + stretch + ')';
                } else if (item.type === 'text') {
                    trans += ' rotateZ(' + item.rot + 'deg)';
                    if (Math.abs(state.velocity) > 1) {
                        const offset = state.velocity * 1.5;
                        item.el.style.textShadow = offset + 'px 0 rgba(14,165,233,0.4), ' + (-offset) + 'px 0 rgba(142,184,212,0.3)';
                    } else {
                        item.el.style.textShadow = 'none';
                    }
                } else {
                    const t = time * 0.001;
                    const floatY = Math.sin(t + item.x * 0.01) * 6;
                    trans += ' rotateZ(' + item.rot + 'deg) rotateY(' + floatY + 'deg)';
                }

                item.el.style.transform = trans;
            }
        });

        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);
    </script>

    <?php include('./student.transition.php'); ?>
</body>
</html>
