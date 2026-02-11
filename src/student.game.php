<?php
/**
 * Page Jeu - Rubik's Cube interactif
 * Espace Étudiant - Université Adventiste Zurcher
 */

require('../data/backdb.php');
require('../data/middleware.php');

initMiddleware($dtb);

if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ./index');
    exit;
}

$studentInfo = getStudentInfo();
$studentName = '';
$studentPrenom = '';
if ($studentInfo) {
    $studentName = htmlspecialchars($studentInfo['student_nom'] ?? '');
    $studentPrenom = htmlspecialchars($studentInfo['student_prenom'] ?? '');
}

$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rubik's Cube - Espace Étudiant</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            overflow: hidden;
            font-family: "Inter", sans-serif;
            background: #0a1628;
            color: #e8f1f8;
        }

        /* ===== BACKGROUND ===== */
        .game-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(14, 165, 233, 0.06) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 30%, rgba(142, 184, 212, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(38, 77, 115, 0.08) 0%, transparent 60%),
                linear-gradient(180deg, #0a1628 0%, #0d1f3c 40%, #0f2847 70%, #0a1628 100%);
        }

        /* ===== TOP BAR ===== */
        .std-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
            height: 56px;
            background: rgba(10, 22, 40, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(14, 165, 233, 0.08);
        }
        .std-topbar .logo-img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        .std-topbar .page-title {
            font-family: "Syne", sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            color: #e8f1f8;
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
            font-family: "Inter", sans-serif;
            padding: 3px 8px;
            border-radius: 6px;
            white-space: nowrap;
            border: 1px solid rgba(14, 165, 233, 0.15);
            pointer-events: none;
            z-index: 200;
        }

        /* ===== STATS BAR ===== */
        .stats-bar {
            position: fixed;
            top: 56px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 50;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 0.6rem 1.5rem;
            background: rgba(13, 31, 60, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(142, 184, 212, 0.1);
            border-radius: 14px;
            margin-top: 0.75rem;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: "Space Grotesk", sans-serif;
        }
        .stat-item i {
            color: #0ea5e9;
            font-size: 0.9rem;
        }
        .stat-label {
            font-size: 0.7rem;
            color: rgba(142, 184, 212, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .stat-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e8f1f8;
            font-variant-numeric: tabular-nums;
        }
        .stat-divider {
            width: 1px;
            height: 24px;
            background: rgba(142, 184, 212, 0.15);
        }

        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 50;
            display: flex;
            gap: 0.75rem;
        }
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(142, 184, 212, 0.15);
            font-family: "Inter", sans-serif;
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            outline: none;
        }
        .action-btn.primary {
            background: rgba(14, 165, 233, 0.15);
            border-color: rgba(14, 165, 233, 0.3);
            color: #7dd3fc;
        }
        .action-btn.primary:hover {
            background: rgba(14, 165, 233, 0.25);
            border-color: rgba(14, 165, 233, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.15);
        }
        .action-btn.secondary {
            background: rgba(142, 184, 212, 0.08);
            border-color: rgba(142, 184, 212, 0.15);
            color: #8eb8d4;
        }
        .action-btn.secondary:hover {
            background: rgba(142, 184, 212, 0.15);
            border-color: rgba(142, 184, 212, 0.3);
            transform: translateY(-2px);
        }
        .action-btn i { font-size: 1rem; }

        /* ===== RUBIK'S CUBE ===== */
        .centered {
            position: absolute;
            top: 0; bottom: 0; left: 0; right: 0;
            margin: auto;
        }

        .scene {
            width: 100%;
            height: 100%;
            perspective: 1200px;
            transform-style: preserve-3d;
            position: fixed;
            inset: 0;
            z-index: 10;
        }
        .scene > .pivot {
            width: 0;
            height: 0;
            transition: .18s;
        }
        .scene .anchor {
            width: 2em;
            height: 6em;
        }
        .scene div {
            position: absolute;
            transform-style: inherit;
        }

        .cube {
            font-size: 190%;
            margin-left: -1em;
            margin-top: -1em;
        }
        .cube > .piece {
            width: 1.9em;
            height: 1.9em;
        }
        .cube > .piece > .element {
            width: 100%;
            height: 100%;
            background: #0d1a2e;
            outline: 1px solid transparent;
            border: 0.05em solid #0a1628;
            border-radius: 10%;
        }

        /* Face transforms */
        .element.left   { transform: rotateX(0deg) rotateY(-90deg) rotateZ(180deg) translateZ(1em); }
        .element.right  { transform: rotateX(0deg) rotateY(90deg) rotateZ(90deg) translateZ(1em); }
        .element.back   { transform: rotateX(0deg) rotateY(180deg) rotateZ(-90deg) translateZ(1em); }
        .element.front  { transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg) translateZ(1em); }
        .element.bottom { transform: rotateX(-90deg) rotateY(0deg) rotateZ(-90deg) translateZ(1em); }
        .element.top    { transform: rotateX(90deg) rotateY(0deg) rotateZ(180deg) translateZ(1em); }

        /* Stickers */
        .sticker {
            position: absolute;
            top: 0; bottom: 0; left: 0; right: 0;
            margin: auto;
            transform: translateZ(2px);
            width: 95%;
            height: 95%;
            border-radius: 10%;
            outline: 1px solid transparent;
            box-shadow:
                inset 0.05em 0.05em 0.2rem 0 rgba(255,255,255,0.25),
                inset -0.05em -0.05em 0.2rem 0 rgba(0,0,0,0.25);
        }

        /* Rubik's standard colors */
        .sticker.blue   { background-color: #0055d4; }
        .sticker.green  { background-color: #00a825; }
        .sticker.white  { background-color: #e8e8e8; }
        .sticker.yellow { background-color: #f0c800; }
        .sticker.orange { background-color: #ff6600; }
        .sticker.red    { background-color: #e80000; }

        /* Guide overlay */
        #guide {
            position: fixed;
            z-index: 5;
        }

        /* ===== GLOW EFFECT UNDER CUBE ===== */
        .cube-glow {
            position: fixed;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.08) 0%, transparent 70%);
            z-index: 5;
            pointer-events: none;
            animation: glowPulse 4s ease-in-out infinite;
        }
        @keyframes glowPulse {
            0%, 100% { opacity: 0.6; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 1; transform: translate(-50%, -50%) scale(1.15); }
        }

        /* ===== INSTRUCTIONS HINT ===== */
        .hint-text {
            position: fixed;
            bottom: 5.5rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 50;
            font-family: "Inter", sans-serif;
            font-size: 0.7rem;
            color: rgba(142, 184, 212, 0.35);
            letter-spacing: 0.05em;
            text-align: center;
            pointer-events: none;
            white-space: nowrap;
        }

        /* ===== FOOTER ===== */
        .footer-info {
            position: fixed;
            bottom: 0.75rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 50;
            font-family: "Inter", sans-serif;
            font-size: 0.65rem;
            color: rgba(142, 184, 212, 0.2);
            letter-spacing: 0.05em;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .cube { font-size: 150%; }
            .std-topbar { height: 50px; padding: 0 1rem; }
            .std-topbar .logo-img { width: 28px; height: 28px; }
            .std-topbar .page-title { font-size: 0.78rem; }
            .stats-bar { gap: 1rem; padding: 0.5rem 1rem; }
            .stat-label { display: none; }
            .action-btn span { display: none; }
            .action-btn { padding: 0.6rem 0.9rem; }
            .hint-text { display: none; }
        }
        @media (max-width: 480px) {
            .cube { font-size: 120%; }
            .stats-bar { gap: 0.75rem; padding: 0.4rem 0.8rem; }
            .stat-value { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

    <!-- BACKGROUND -->
    <div class="game-bg"></div>

    <!-- GLOW -->
    <div class="cube-glow"></div>

    <!-- TOP BAR -->
    <div class="std-topbar">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ" class="logo-img">
            <span class="page-title">Rubik's Cube</span>
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

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stat-item">
            <i class="bi bi-arrows-move"></i>
            <div>
                <div class="stat-label">Mouvements</div>
                <div class="stat-value" id="moveCount">0</div>
            </div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <i class="bi bi-stopwatch"></i>
            <div>
                <div class="stat-label">Temps</div>
                <div class="stat-value" id="timerDisplay">00:00</div>
            </div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <i class="bi bi-trophy"></i>
            <div>
                <div class="stat-label">Record</div>
                <div class="stat-value" id="bestDisplay">--:--</div>
            </div>
        </div>
    </div>

    <!-- RUBIK'S CUBE SCENE -->
    <div id="scene" class="scene">
        <div id="pivot" class="pivot centered" style="transform: rotateX(-35deg) rotateY(-135deg);">
            <div id="cube" class="cube">
                <?php for ($i = 0; $i < 26; $i++): ?>
                <div class="piece">
                    <div class="element left"></div>
                    <div class="element right"></div>
                    <div class="element top"></div>
                    <div class="element bottom"></div>
                    <div class="element back"></div>
                    <div class="element front"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- GUIDE ANCHORS -->
    <div id="guide">
        <div id="anchor3" class="anchor" style="transform: translateZ(3px) translateY(-33.33%) rotate(-270deg) translateY(66.67%)"></div>
        <div id="anchor2" class="anchor" style="transform: translateZ(3px) translateY(-33.33%) rotate(-180deg) translateY(66.67%)"></div>
        <div id="anchor1" class="anchor" style="transform: translateZ(3px) translateY(-33.33%) rotate(-90deg) translateY(66.67%)"></div>
        <div id="anchor0" class="anchor" style="transform: translateZ(3px) translateY(-33.33%) rotate(0deg) translateY(66.67%)"></div>
    </div>

    <!-- HINT -->
    <div class="hint-text">
        <i class="bi bi-mouse"></i> Glisser sur le fond pour tourner &nbsp;·&nbsp; Glisser sur une face pour la pivoter
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons">
        <button class="action-btn primary" id="btnScramble" title="Mélanger">
            <i class="bi bi-shuffle"></i>
            <span>Mélanger</span>
        </button>
        <button class="action-btn secondary" id="btnReset" title="Réinitialiser">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Réinitialiser</span>
        </button>
    </div>

    <!-- FOOTER -->
    <div class="footer-info">Infinit Registrar © <?= date('Y') ?></div>

    <script>
    // ============================================================
    // Rubik's Cube Engine
    // ============================================================
    var colors = ['blue', 'green', 'white', 'yellow', 'orange', 'red'],
        pieces = document.getElementsByClassName('piece');

    // Move counter & timer
    var moveCount = 0,
        timerInterval = null,
        timerStart = 0,
        timerRunning = false,
        bestTime = localStorage.getItem('rubik_best') ? parseInt(localStorage.getItem('rubik_best')) : null;

    if (bestTime) {
        document.getElementById('bestDisplay').textContent = formatTime(bestTime);
    }

    function updateMoveCount() {
        document.getElementById('moveCount').textContent = moveCount;
    }

    function formatTime(ms) {
        var s = Math.floor(ms / 1000);
        var m = Math.floor(s / 60);
        s = s % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function startTimer() {
        if (timerRunning) return;
        timerRunning = true;
        timerStart = Date.now();
        timerInterval = setInterval(function() {
            var elapsed = Date.now() - timerStart;
            document.getElementById('timerDisplay').textContent = formatTime(elapsed);
        }, 200);
    }

    function stopTimer() {
        if (!timerRunning) return;
        timerRunning = false;
        clearInterval(timerInterval);
        var elapsed = Date.now() - timerStart;
        if (!bestTime || elapsed < bestTime) {
            bestTime = elapsed;
            localStorage.setItem('rubik_best', bestTime);
            document.getElementById('bestDisplay').textContent = formatTime(bestTime);
        }
    }

    function resetTimer() {
        timerRunning = false;
        clearInterval(timerInterval);
        timerStart = 0;
        document.getElementById('timerDisplay').textContent = '00:00';
    }

    // Returns j-th adjacent face of i-th face
    function mx(i, j) {
        return ([2, 4, 3, 5][j % 4 | 0] + i % 2 * ((j | 0) % 4 * 2 + 3) + 2 * (i / 2 | 0)) % 6;
    }

    function getAxis(face) {
        return String.fromCharCode('X'.charCodeAt(0) + face / 2);
    }

    // Assembles all 26 pieces into a solved cube
    function assembleCube() {
        function moveto(face) {
            id = id + (1 << face);
            pieces[i].children[face].appendChild(document.createElement('div'))
                .setAttribute('class', 'sticker ' + colors[face]);
            return 'translate' + getAxis(face) + '(' + (face % 2 * 4 - 2) + 'em)';
        }
        for (var id, x, i = 0; id = 0, i < 26; i++) {
            x = mx(i, i % 18);
            pieces[i].style.transform = 'rotateX(0deg)' + moveto(i % 6) +
                (i > 5 ? moveto(x) + (i > 17 ? moveto(mx(x, x + 2)) : '') : '');
            pieces[i].setAttribute('id', 'piece' + id);
        }
    }

    function getPieceBy(face, index, corner) {
        return document.getElementById('piece' +
            ((1 << face) + (1 << mx(face, index)) + (1 << mx(face, index + 1)) * corner));
    }

    // Swaps stickers (rotates a face clockwise N times)
    function swapPieces(face, times) {
        for (var i = 0; i < 6 * times; i++) {
            var piece1 = getPieceBy(face, i / 2, i % 2),
                piece2 = getPieceBy(face, i / 2 + 1, i % 2);
            for (var j = 0; j < 5; j++) {
                var sticker1 = piece1.children[j < 4 ? mx(face, j) : face].firstChild,
                    sticker2 = piece2.children[j < 4 ? mx(face, j + 1) : face].firstChild,
                    className = sticker1 ? sticker1.className : '';
                if (className)
                    sticker1.className = sticker2.className,
                    sticker2.className = className;
            }
        }
    }

    // Animates a face rotation
    function animateRotation(face, cw, currentTime) {
        var k = .3 * (face % 2 * 2 - 1) * (2 * cw - 1),
            qubes = Array(9).fill(pieces[face]).map(function(value, index) {
                return index ? getPieceBy(face, index / 2, index % 2) : value;
            });
        (function rotatePieces() {
            var passed = Date.now() - currentTime,
                style = 'rotate' + getAxis(face) + '(' + k * passed * (passed < 300) + 'deg)';
            qubes.forEach(function(piece) {
                piece.style.transform = piece.style.transform.replace(/rotate.\(\S+\)/, style);
            });
            if (passed >= 300)
                return swapPieces(face, 3 - 2 * cw);
            requestAnimationFrame(rotatePieces);
        })();
    }

    // Mouse / touch interaction
    function mousedown(md_e) {
        var pageX = md_e.pageX || (md_e.touches && md_e.touches[0].pageX),
            pageY = md_e.pageY || (md_e.touches && md_e.touches[0].pageY);
        var startXY = pivot.style.transform.match(/-?\d+\.?\d*/g).map(Number),
            element = md_e.target.closest('.element'),
            face = [].indexOf.call((element || cube).parentNode.children, element);

        function mousemove(mm_e) {
            var mmX = mm_e.pageX || (mm_e.touches && mm_e.touches[0].pageX),
                mmY = mm_e.pageY || (mm_e.touches && mm_e.touches[0].pageY);
            if (element) {
                var gid = /\d/.exec(document.elementFromPoint(mmX, mmY).id);
                if (gid && gid.input.includes('anchor')) {
                    mouseup();
                    var e = element.parentNode.children[mx(face, Number(gid) + 3)].hasChildNodes();
                    animateRotation(mx(face, Number(gid) + 1 + 2 * e), e, Date.now());
                    moveCount++;
                    updateMoveCount();
                    startTimer();
                }
            } else {
                pivot.style.transform =
                    'rotateX(' + (startXY[0] - (mmY - pageY) / 2) + 'deg)' +
                    'rotateY(' + (startXY[1] + (mmX - pageX) / 2) + 'deg)';
            }
        }

        function mouseup() {
            document.body.appendChild(guide);
            scene.removeEventListener('mousemove', mousemove);
            scene.removeEventListener('touchmove', mousemove);
            document.removeEventListener('mouseup', mouseup);
            document.removeEventListener('touchend', mouseup);
            scene.addEventListener('mousedown', mousedown);
            scene.addEventListener('touchstart', mousedown, { passive: false });
        }

        (element || document.body).appendChild(guide);
        scene.addEventListener('mousemove', mousemove);
        scene.addEventListener('touchmove', mousemove, { passive: false });
        document.addEventListener('mouseup', mouseup);
        document.addEventListener('touchend', mouseup);
        scene.removeEventListener('mousedown', mousedown);
        scene.removeEventListener('touchstart', mousedown);
    }

    document.ondragstart = function() { return false; }

    // Scramble: perform random moves
    function scrambleCube() {
        moveCount = 0;
        updateMoveCount();
        resetTimer();
        var moves = 20 + Math.floor(Math.random() * 10);
        for (var i = 0; i < moves; i++) {
            var face = Math.floor(Math.random() * 6);
            var cw = Math.random() > 0.5;
            swapPieces(face, cw ? 1 : 3);
        }
    }

    // Reset cube
    function resetCube() {
        // Remove all stickers
        var allStickers = document.querySelectorAll('.sticker');
        allStickers.forEach(function(s) { s.remove(); });
        // Remove piece IDs
        for (var i = 0; i < 26; i++) {
            pieces[i].removeAttribute('id');
            pieces[i].style.transform = '';
        }
        // Reassemble
        assembleCube();
        // Reset pivot
        pivot.style.transform = 'rotateX(-35deg) rotateY(-135deg)';
        // Reset stats
        moveCount = 0;
        updateMoveCount();
        resetTimer();
    }

    // Init
    window.addEventListener('load', function() {
        assembleCube();
        scene.addEventListener('mousedown', mousedown);
        scene.addEventListener('touchstart', mousedown, { passive: false });
    });

    // Buttons
    document.getElementById('btnScramble').addEventListener('click', function(e) {
        e.stopPropagation();
        scrambleCube();
    });
    document.getElementById('btnReset').addEventListener('click', function(e) {
        e.stopPropagation();
        resetCube();
    });

    // Prevent buttons from triggering cube rotation
    document.querySelectorAll('.action-btn, .std-topbar, .stats-bar').forEach(function(el) {
        el.addEventListener('mousedown', function(e) { e.stopPropagation(); });
        el.addEventListener('touchstart', function(e) { e.stopPropagation(); }, { passive: false });
    });
    </script>

    <?php include('./student.transition.php'); ?>
</body>
</html>
