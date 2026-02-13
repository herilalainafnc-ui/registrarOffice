<?php
/**
 * Page d'accueil interactive - Espace Étudiant
 * Effet Liquid Gradient interactif avec Three.js
 */

require('../data/backdb.php');
require('../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// Vérifier que l'utilisateur est connecté et est un étudiant
if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ./index');
    exit;
}

// Récupérer les informations de l'étudiant
$studentInfo = getStudentInfo();
$studentName = '';
$studentPrenom = '';
$studentMention = '';
$studentPhoto = '';

if ($studentInfo) {
    $studentName = htmlspecialchars($studentInfo['student_nom'] ?? '');
    $studentPrenom = htmlspecialchars($studentInfo['student_prenom'] ?? '');
    $studentMention = htmlspecialchars($studentInfo['etude_envisage'] ?? '');
    $studentPhoto = $studentInfo['image_student'] ?? '';
}

// Calculer le chemin racine
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';

// Salutation (sera ajustée côté client selon le fuseau local)
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Bonjour";
    $greetingIcon = "☀️";
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = "Bon après-midi";
    $greetingIcon = "🌤️";
} elseif ($hour >= 18 && $hour < 22) {
    $greeting = "Bonsoir";
    $greetingIcon = "🌅";
} else {
    $greeting = "Bonne nuit";
    $greetingIcon = "🌙";
}

// Date formatée (sera ajustée côté client)
$dateFormatted = strftime('%A %d %B %Y');
if (!$dateFormatted) {
    setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fra');
    $dateFormatted = strftime('%A %d %B %Y');
}
if (!$dateFormatted) {
    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $dateFormatted = $formatter->format(new DateTime());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Étudiant - Université Adventiste Zurcher</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            overflow: hidden;
            font-family: "Inter", sans-serif;
            cursor: none;
        }
        #webGLApp {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        /* ===== HEADING ===== */
        .heading {
            position: fixed;
            top: 32%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            color: white;
            text-align: center;
            white-space: nowrap;
            pointer-events: none;
            font-family: "Syne", sans-serif;
            font-size: clamp(2.5rem, 7vw, 6rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            text-transform: none;
            line-height: 1.1;
            opacity: 0;
            animation: fadeInUp 1s ease 0.5s forwards;
            max-width: 90vw;
        }

        /* ===== STUDENT INFO CARD ===== */
        .student-welcome {
            position: fixed;
            top: 56%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            text-align: center;
            pointer-events: none;
            opacity: 0;
            animation: fadeInUp 1s ease 0.8s forwards;
            max-width: 90vw;
            width: 100%;
        }
        .student-welcome .greeting-text {
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(1rem, 2.5vw, 1.5rem);
            color: rgba(255,255,255,0.8);
            font-weight: 300;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .student-welcome .student-name {
            font-family: "Syne", sans-serif;
            font-size: clamp(1.5rem, 4vw, 3rem);
            color: white;
            font-weight: 700;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 85vw;
            margin: 0 auto;
        }
        .student-welcome .student-mention {
            font-family: "Inter", sans-serif;
            font-size: clamp(0.8rem, 1.5vw, 1rem);
            color: rgba(255,255,255,0.6);
            font-weight: 400;
            margin-top: 0.5rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .student-welcome .date-text {
            font-family: "Inter", sans-serif;
            font-size: clamp(0.7rem, 1.2vw, 0.9rem);
            color: rgba(255,255,255,0.4);
            font-weight: 300;
            margin-top: 1rem;
            letter-spacing: 0.05em;
        }

        /* ===== NAVIGATION CARDS ===== */
        .nav-cards {
            position: fixed;
            bottom: 6rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            gap: 1rem;
            pointer-events: auto;
            opacity: 0;
            animation: fadeInUp 1s ease 1.2s forwards;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 90vw;
        }
        .nav-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            padding: 1.25rem 1.5rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            color: white;
            text-decoration: none;
            font-family: "Inter", sans-serif;
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.03em;
            cursor: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .nav-card i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            transition: transform 0.3s ease;
        }
        .nav-card:hover {
            background: rgba(14, 165, 233, 0.15);
            border-color: rgba(14, 165, 233, 0.5);
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 20px 40px rgba(14, 165, 233, 0.15);
            color: white;
            text-decoration: none;
        }
        .nav-card:hover i {
            transform: scale(1.2);
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            pointer-events: auto;
            opacity: 0;
            animation: fadeInDown 0.8s ease 0.3s forwards;
        }
        .top-bar .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .top-bar .logo-section img {
            height: 40px;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
        }
        .top-bar .logo-section span {
            font-family: "Syne", sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.03em;
        }
        .top-bar .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .top-bar .user-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            object-fit: cover;
        }
        .top-bar .user-default {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        .logout-btn {
            padding: 0.5rem 1rem;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 10px;
            color: #fca5a5;
            font-family: "Inter", sans-serif;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-decoration: none;
            cursor: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.35);
            border-color: rgba(239, 68, 68, 0.6);
            color: #fecaca;
            transform: translateY(-2px);
        }

        /* ===== COLOR SCHEME CONTROLS ===== */
        .color-controls {
            position: fixed;
            bottom: 1.5rem;
            right: 2rem;
            z-index: 10;
            display: flex;
            gap: 0.5rem;
            pointer-events: auto;
            opacity: 0;
            animation: fadeInUp 0.8s ease 1.5s forwards;
        }
        .color-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            cursor: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .color-btn:hover {
            border-color: rgba(255, 255, 255, 0.7);
            transform: scale(1.2);
        }
        .color-btn.active {
            border-color: white;
            box-shadow: 0 0 12px rgba(255,255,255,0.3);
        }
        .color-btn[data-scheme="1"] { background: linear-gradient(135deg, #0ea5e9, #0a1628); }
        .color-btn[data-scheme="2"] { background: linear-gradient(135deg, #38bdf8, #0f2847); }
        .color-btn[data-scheme="3"] { background: linear-gradient(135deg, #0ea5e9, #3d6a8a); }
        .color-btn[data-scheme="4"] { background: linear-gradient(135deg, #06b6d4, #1a3a5c); }
        .color-btn[data-scheme="5"] { background: linear-gradient(135deg, #8eb8d4, #0a1628); }

        /* ===== FOOTER ===== */
        .footer-info {
            position: fixed;
            bottom: 1.5rem;
            left: 2rem;
            z-index: 10;
            font-family: "Inter", sans-serif;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.3);
            letter-spacing: 0.05em;
            opacity: 0;
            animation: fadeInUp 0.8s ease 1.5s forwards;
        }

        /* ===== CUSTOM CURSOR ===== */
        .custom-cursor {
            position: fixed;
            width: 40px;
            height: 40px;
            border: 2px solid white;
            border-radius: 50%;
            pointer-events: none;
            z-index: 1000;
            transform: translate(-50%, -50%);
            transition: width 0.2s ease, height 0.2s ease, border-width 0.2s ease, border-color 0.2s ease;
            background: transparent;
            will-change: transform;
        }
        .custom-cursor::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
        }

        /* ===== TIME WIDGET ===== */
        .time-widget {
            position: fixed;
            top: 50%;
            right: 2rem;
            transform: translateY(-50%);
            z-index: 10;
            text-align: right;
            pointer-events: none;
            opacity: 0;
            animation: fadeInRight 1s ease 1s forwards;
        }
        .time-widget .time-display {
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 300;
            color: rgba(255,255,255,0.15);
            line-height: 1;
            letter-spacing: -0.02em;
        }
        .time-widget .time-seconds {
            font-size: clamp(1rem, 2vw, 1.5rem);
            color: rgba(255,255,255,0.08);
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) translateY(30px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) translateY(0);
            }
        }
        .nav-cards {
            animation-name: fadeInUpNav;
        }
        @keyframes fadeInUpNav {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateY(-50%) translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }
        .footer-info, .color-controls {
            animation-name: fadeInSimple;
        }
        @keyframes fadeInSimple {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .nav-cards {
                gap: 0.75rem;
                bottom: 4rem;
            }
            .nav-card {
                min-width: 90px;
                padding: 1rem;
                font-size: 0.7rem;
            }
            .nav-card i {
                font-size: 1.2rem;
            }
            .top-bar {
                padding: 0.75rem 1rem;
            }
            .top-bar .logo-section span {
                display: none;
            }
            .time-widget {
                display: none;
            }
            .color-controls {
                bottom: 1rem;
                right: 1rem;
            }
            .footer-info {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .heading {
                font-size: clamp(1.8rem, 8vw, 3rem);
            }
            .student-welcome .student-name {
                font-size: clamp(1.2rem, 6vw, 2rem);
            }
            .nav-card {
                min-width: 75px;
                padding: 0.75rem;
            }
        }

        /* HD screens (1366x768, 1280x720) where vertical space is tight */
        @media (max-height: 700px) {
            .heading {
                top: 25%;
                font-size: clamp(2rem, 5vw, 4rem);
            }
            .student-welcome {
                top: 48%;
            }
            .student-welcome .student-name {
                font-size: clamp(1.2rem, 3vw, 2rem);
            }
            .student-welcome .greeting-text {
                font-size: clamp(0.85rem, 2vw, 1.1rem);
                margin-bottom: 0.25rem;
            }
            .student-welcome .date-text {
                margin-top: 0.5rem;
            }
            .nav-cards {
                bottom: 3rem;
                gap: 0.6rem;
            }
            .nav-card {
                min-width: 100px;
                padding: 0.9rem 1.2rem;
                font-size: 0.75rem;
            }
            .nav-card i {
                font-size: 1.2rem;
                margin-bottom: 0.3rem;
            }
        }

        /* Very short screens (old laptops, split view) */
        @media (max-height: 550px) {
            .heading {
                top: 18%;
                font-size: clamp(1.5rem, 4vw, 3rem);
            }
            .student-welcome {
                top: 42%;
            }
            .nav-cards {
                bottom: 1.5rem;
            }
            .color-controls {
                display: none;
            }
            .footer-info {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="logo-section">
            <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ">
            <span>Université Adventiste Zurcher</span>
        </div>
        <div class="user-section">
            <?php if (!empty($studentPhoto)): ?>
                <img src="<?=$app_base?>/app/photosetudiants/<?= htmlspecialchars($studentPhoto) ?>" 
                     alt="Photo" class="user-photo">
            <?php else: ?>
                <div class="user-default"><i class="bi bi-person"></i></div>
            <?php endif; ?>
            <a href="../app/logout" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>
    </div>

    <!-- HEADING -->
    <h1 class="heading">Espace Étudiant</h1>

    <!-- STUDENT WELCOME -->
    <div class="student-welcome">
        <div class="greeting-text"><?=$greetingIcon?> <?=$greeting?></div>
        <div class="student-name"><?= $studentPrenom ?> <?= $studentName ?></div>
        <?php if ($studentMention): ?>
            <div class="student-mention"><?= $studentMention ?></div>
        <?php endif; ?>
        <div class="date-text"><?= $dateFormatted ?></div>
    </div>

    <!-- NAVIGATION CARDS -->
    <div class="nav-cards">
        <a href="./student.dashboard" class="nav-card">
            <i class="bi bi-clipboard-data"></i>
            Mes Notes
        </a>
        <a href="./student.info" class="nav-card">
            <i class="bi bi-person-badge"></i>
            Mes Infos
        </a>
        <a href="./student.actus" class="nav-card">
            <i class="bi bi-megaphone"></i>
            Actus
        </a>
        <a href="./student.quiz" class="nav-card">
            <i class="bi bi-patch-question"></i>
            Quiz
        </a>
        <!-- <a href="./student/historique-notes" class="nav-card">
            <i class="bi bi-clock-history"></i>
            Historique
        </a> -->
        <a href="./student.game" class="nav-card">
            <i class="bi bi-dpad"></i>
            Jeu
        </a>
    </div>

    <!-- TIME WIDGET -->
    <div class="time-widget">
        <div class="time-display" id="timeDisplay">--:--</div>
    </div>

    <!-- COLOR SCHEME DOTS -->
    <div class="color-controls">
        <button class="color-btn" data-scheme="1" title="Cyan & Nuit"></button>
        <button class="color-btn" data-scheme="2" title="Sky & Nuit"></button>
        <button class="color-btn" data-scheme="3" title="Cyan & Acier"></button>
        <button class="color-btn" data-scheme="4" title="Teal & Nuit"></button>
        <button class="color-btn active" data-scheme="5" title="Pâle & Profond"></button>
    </div>

    <!-- FOOTER -->
    <div class="footer-info">Infinit Registrar © <?= date('Y') ?></div>

    <!-- CUSTOM CURSOR -->
    <div class="custom-cursor" id="customCursor"></div>

    <!-- THREE.JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

    <script>
    // ============================================================
    // TouchTexture
    // ============================================================
    class TouchTexture {
        constructor() {
            this.size = 64;
            this.width = this.height = this.size;
            this.maxAge = 64;
            this.radius = 0.25 * this.size;
            this.speed = 1 / this.maxAge;
            this.trail = [];
            this.last = null;
            this.initTexture();
        }

        initTexture() {
            this.canvas = document.createElement("canvas");
            this.canvas.width = this.width;
            this.canvas.height = this.height;
            this.ctx = this.canvas.getContext("2d");
            this.ctx.fillStyle = "black";
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.texture = new THREE.Texture(this.canvas);
        }

        update() {
            this.clear();
            let speed = this.speed;
            for (let i = this.trail.length - 1; i >= 0; i--) {
                const point = this.trail[i];
                let f = point.force * speed * (1 - point.age / this.maxAge);
                point.x += point.vx * f;
                point.y += point.vy * f;
                point.age++;
                if (point.age > this.maxAge) {
                    this.trail.splice(i, 1);
                } else {
                    this.drawPoint(point);
                }
            }
            this.texture.needsUpdate = true;
        }

        clear() {
            this.ctx.fillStyle = "black";
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        }

        addTouch(point) {
            let force = 0;
            let vx = 0;
            let vy = 0;
            const last = this.last;
            if (last) {
                const dx = point.x - last.x;
                const dy = point.y - last.y;
                if (dx === 0 && dy === 0) return;
                const dd = dx * dx + dy * dy;
                let d = Math.sqrt(dd);
                vx = dx / d;
                vy = dy / d;
                force = Math.min(dd * 20000, 2.0);
            }
            this.last = { x: point.x, y: point.y };
            this.trail.push({ x: point.x, y: point.y, age: 0, force, vx, vy });
        }

        drawPoint(point) {
            const pos = {
                x: point.x * this.width,
                y: (1 - point.y) * this.height
            };

            let intensity = 1;
            if (point.age < this.maxAge * 0.3) {
                intensity = Math.sin((point.age / (this.maxAge * 0.3)) * (Math.PI / 2));
            } else {
                const t = 1 - (point.age - this.maxAge * 0.3) / (this.maxAge * 0.7);
                intensity = -t * (t - 2);
            }
            intensity *= point.force;

            const radius = this.radius;
            let color = `${((point.vx + 1) / 2) * 255}, ${((point.vy + 1) / 2) * 255}, ${intensity * 255}`;
            let offset = this.size * 5;
            this.ctx.shadowOffsetX = offset;
            this.ctx.shadowOffsetY = offset;
            this.ctx.shadowBlur = radius * 1;
            this.ctx.shadowColor = `rgba(${color},${0.2 * intensity})`;

            this.ctx.beginPath();
            this.ctx.fillStyle = "rgba(255,0,0,1)";
            this.ctx.arc(pos.x - offset, pos.y - offset, radius, 0, Math.PI * 2);
            this.ctx.fill();
        }
    }

    // ============================================================
    // GradientBackground
    // ============================================================
    class GradientBackground {
        constructor(sceneManager) {
            this.sceneManager = sceneManager;
            this.mesh = null;
            this.uniforms = {
                uTime: { value: 0 },
                uResolution: { value: new THREE.Vector2(window.innerWidth, window.innerHeight) },
                uColor1: { value: new THREE.Vector3(0.557, 0.722, 0.831) },
                uColor2: { value: new THREE.Vector3(0.039, 0.086, 0.157) },
                uColor3: { value: new THREE.Vector3(0.149, 0.302, 0.451) },
                uColor4: { value: new THREE.Vector3(0.051, 0.122, 0.235) },
                uColor5: { value: new THREE.Vector3(0.239, 0.416, 0.541) },
                uColor6: { value: new THREE.Vector3(0.059, 0.157, 0.278) },
                uSpeed: { value: 1.2 },
                uIntensity: { value: 1.8 },
                uTouchTexture: { value: null },
                uGrainIntensity: { value: 0.08 },
                uZoom: { value: 1.0 },
                uDarkNavy: { value: new THREE.Vector3(0.039, 0.086, 0.157) },
                uGradientSize: { value: 1.0 },
                uGradientCount: { value: 6.0 },
                uColor1Weight: { value: 1.0 },
                uColor2Weight: { value: 1.0 }
            };
        }

        init() {
            const viewSize = this.sceneManager.getViewSize();
            const geometry = new THREE.PlaneGeometry(viewSize.width, viewSize.height, 1, 1);

            const material = new THREE.ShaderMaterial({
                uniforms: this.uniforms,
                vertexShader: `
                    varying vec2 vUv;
                    void main() {
                        vec3 pos = position.xyz;
                        gl_Position = projectionMatrix * modelViewMatrix * vec4(pos, 1.);
                        vUv = uv;
                    }
                `,
                fragmentShader: `
                    uniform float uTime;
                    uniform vec2 uResolution;
                    uniform vec3 uColor1;
                    uniform vec3 uColor2;
                    uniform vec3 uColor3;
                    uniform vec3 uColor4;
                    uniform vec3 uColor5;
                    uniform vec3 uColor6;
                    uniform float uSpeed;
                    uniform float uIntensity;
                    uniform sampler2D uTouchTexture;
                    uniform float uGrainIntensity;
                    uniform float uZoom;
                    uniform vec3 uDarkNavy;
                    uniform float uGradientSize;
                    uniform float uGradientCount;
                    uniform float uColor1Weight;
                    uniform float uColor2Weight;
                    
                    varying vec2 vUv;
                    
                    #define PI 3.14159265359
                    
                    float grain(vec2 uv, float time) {
                        vec2 grainUv = uv * uResolution * 0.5;
                        float grainValue = fract(sin(dot(grainUv + time, vec2(12.9898, 78.233))) * 43758.5453);
                        return grainValue * 2.0 - 1.0;
                    }
                    
                    vec3 getGradientColor(vec2 uv, float time) {
                        float gradientRadius = uGradientSize;
                        
                        vec2 center1 = vec2(0.5 + sin(time * uSpeed * 0.4) * 0.4, 0.5 + cos(time * uSpeed * 0.5) * 0.4);
                        vec2 center2 = vec2(0.5 + cos(time * uSpeed * 0.6) * 0.5, 0.5 + sin(time * uSpeed * 0.45) * 0.5);
                        vec2 center3 = vec2(0.5 + sin(time * uSpeed * 0.35) * 0.45, 0.5 + cos(time * uSpeed * 0.55) * 0.45);
                        vec2 center4 = vec2(0.5 + cos(time * uSpeed * 0.5) * 0.4, 0.5 + sin(time * uSpeed * 0.4) * 0.4);
                        vec2 center5 = vec2(0.5 + sin(time * uSpeed * 0.7) * 0.35, 0.5 + cos(time * uSpeed * 0.6) * 0.35);
                        vec2 center6 = vec2(0.5 + cos(time * uSpeed * 0.45) * 0.5, 0.5 + sin(time * uSpeed * 0.65) * 0.5);
                        
                        vec2 center7 = vec2(0.5 + sin(time * uSpeed * 0.55) * 0.38, 0.5 + cos(time * uSpeed * 0.48) * 0.42);
                        vec2 center8 = vec2(0.5 + cos(time * uSpeed * 0.65) * 0.36, 0.5 + sin(time * uSpeed * 0.52) * 0.44);
                        vec2 center9 = vec2(0.5 + sin(time * uSpeed * 0.42) * 0.41, 0.5 + cos(time * uSpeed * 0.58) * 0.39);
                        vec2 center10 = vec2(0.5 + cos(time * uSpeed * 0.48) * 0.37, 0.5 + sin(time * uSpeed * 0.62) * 0.43);
                        vec2 center11 = vec2(0.5 + sin(time * uSpeed * 0.68) * 0.33, 0.5 + cos(time * uSpeed * 0.44) * 0.46);
                        vec2 center12 = vec2(0.5 + cos(time * uSpeed * 0.38) * 0.39, 0.5 + sin(time * uSpeed * 0.56) * 0.41);
                        
                        float influence1 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center1));
                        float influence2 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center2));
                        float influence3 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center3));
                        float influence4 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center4));
                        float influence5 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center5));
                        float influence6 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center6));
                        float influence7 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center7));
                        float influence8 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center8));
                        float influence9 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center9));
                        float influence10 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center10));
                        float influence11 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center11));
                        float influence12 = 1.0 - smoothstep(0.0, gradientRadius, length(uv - center12));
                        
                        vec2 rotatedUv1 = uv - 0.5;
                        float angle1 = time * uSpeed * 0.15;
                        rotatedUv1 = vec2(rotatedUv1.x * cos(angle1) - rotatedUv1.y * sin(angle1), rotatedUv1.x * sin(angle1) + rotatedUv1.y * cos(angle1));
                        rotatedUv1 += 0.5;
                        
                        vec2 rotatedUv2 = uv - 0.5;
                        float angle2 = -time * uSpeed * 0.12;
                        rotatedUv2 = vec2(rotatedUv2.x * cos(angle2) - rotatedUv2.y * sin(angle2), rotatedUv2.x * sin(angle2) + rotatedUv2.y * cos(angle2));
                        rotatedUv2 += 0.5;
                        
                        float radialInfluence1 = 1.0 - smoothstep(0.0, 0.8, length(rotatedUv1 - 0.5));
                        float radialInfluence2 = 1.0 - smoothstep(0.0, 0.8, length(rotatedUv2 - 0.5));
                        
                        vec3 color = vec3(0.0);
                        color += uColor1 * influence1 * (0.55 + 0.45 * sin(time * uSpeed)) * uColor1Weight;
                        color += uColor2 * influence2 * (0.55 + 0.45 * cos(time * uSpeed * 1.2)) * uColor2Weight;
                        color += uColor3 * influence3 * (0.55 + 0.45 * sin(time * uSpeed * 0.8)) * uColor1Weight;
                        color += uColor4 * influence4 * (0.55 + 0.45 * cos(time * uSpeed * 1.3)) * uColor2Weight;
                        color += uColor5 * influence5 * (0.55 + 0.45 * sin(time * uSpeed * 1.1)) * uColor1Weight;
                        color += uColor6 * influence6 * (0.55 + 0.45 * cos(time * uSpeed * 0.9)) * uColor2Weight;
                        
                        if (uGradientCount > 6.0) {
                            color += uColor1 * influence7 * (0.55 + 0.45 * sin(time * uSpeed * 1.4)) * uColor1Weight;
                            color += uColor2 * influence8 * (0.55 + 0.45 * cos(time * uSpeed * 1.5)) * uColor2Weight;
                            color += uColor3 * influence9 * (0.55 + 0.45 * sin(time * uSpeed * 1.6)) * uColor1Weight;
                            color += uColor4 * influence10 * (0.55 + 0.45 * cos(time * uSpeed * 1.7)) * uColor2Weight;
                        }
                        if (uGradientCount > 10.0) {
                            color += uColor5 * influence11 * (0.55 + 0.45 * sin(time * uSpeed * 1.8)) * uColor1Weight;
                            color += uColor6 * influence12 * (0.55 + 0.45 * cos(time * uSpeed * 1.9)) * uColor2Weight;
                        }
                        
                        color += mix(uColor1, uColor3, radialInfluence1) * 0.45 * uColor1Weight;
                        color += mix(uColor2, uColor4, radialInfluence2) * 0.4 * uColor2Weight;
                        
                        color = clamp(color, vec3(0.0), vec3(1.0)) * uIntensity;
                        
                        float luminance = dot(color, vec3(0.299, 0.587, 0.114));
                        color = mix(vec3(luminance), color, 1.35);
                        color = pow(color, vec3(0.92));
                        
                        float brightness1 = length(color);
                        float mixFactor1 = max(brightness1 * 1.2, 0.15);
                        color = mix(uDarkNavy, color, mixFactor1);
                        
                        float maxBrightness = 1.0;
                        float brightness = length(color);
                        if (brightness > maxBrightness) {
                            color = color * (maxBrightness / brightness);
                        }
                        
                        return color;
                    }
                    
                    void main() {
                        vec2 uv = vUv;
                        
                        vec4 touchTex = texture2D(uTouchTexture, uv);
                        float vx = -(touchTex.r * 2.0 - 1.0);
                        float vy = -(touchTex.g * 2.0 - 1.0);
                        float intensity = touchTex.b;
                        uv.x += vx * 0.8 * intensity;
                        uv.y += vy * 0.8 * intensity;
                        
                        vec2 center = vec2(0.5);
                        float dist = length(uv - center);
                        float ripple = sin(dist * 20.0 - uTime * 3.0) * 0.04 * intensity;
                        float wave = sin(dist * 15.0 - uTime * 2.0) * 0.03 * intensity;
                        uv += vec2(ripple + wave);
                        
                        vec3 color = getGradientColor(uv, uTime);
                        
                        float grainValue = grain(uv, uTime);
                        color += grainValue * uGrainIntensity;
                        
                        float timeShift = uTime * 0.5;
                        color.r += sin(timeShift) * 0.02;
                        color.g += cos(timeShift * 1.4) * 0.02;
                        color.b += sin(timeShift * 1.2) * 0.02;
                        
                        float brightness2 = length(color);
                        float mixFactor2 = max(brightness2 * 1.2, 0.15);
                        color = mix(uDarkNavy, color, mixFactor2);
                        
                        color = clamp(color, vec3(0.0), vec3(1.0));
                        
                        float maxBrightness = 1.0;
                        float brightness = length(color);
                        if (brightness > maxBrightness) {
                            color = color * (maxBrightness / brightness);
                        }
                        
                        gl_FragColor = vec4(color, 1.0);
                    }
                `
            });

            this.mesh = new THREE.Mesh(geometry, material);
            this.mesh.position.z = 0;
            this.sceneManager.scene.add(this.mesh);
        }

        update(delta) {
            if (this.uniforms.uTime) {
                this.uniforms.uTime.value += delta;
            }
        }

        onResize(width, height) {
            const viewSize = this.sceneManager.getViewSize();
            if (this.mesh) {
                this.mesh.geometry.dispose();
                this.mesh.geometry = new THREE.PlaneGeometry(viewSize.width, viewSize.height, 1, 1);
            }
            if (this.uniforms.uResolution) {
                this.uniforms.uResolution.value.set(width, height);
            }
        }
    }

    // ============================================================
    // App
    // ============================================================
    class App {
        constructor() {
            this.renderer = new THREE.WebGLRenderer({
                antialias: true,
                powerPreference: "high-performance",
                alpha: false,
                stencil: false,
                depth: false
            });
            this.renderer.setSize(window.innerWidth, window.innerHeight);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            this.renderer.setAnimationLoop(null);
            document.body.appendChild(this.renderer.domElement);
            this.renderer.domElement.id = "webGLApp";

            this.camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 10000);
            this.camera.position.z = 50;
            this.scene = new THREE.Scene();
            this.scene.background = new THREE.Color(0x0a1628);
            this.clock = new THREE.Clock();

            this.touchTexture = new TouchTexture();
            this.gradientBackground = new GradientBackground(this);
            this.gradientBackground.uniforms.uTouchTexture.value = this.touchTexture.texture;

            this.colorSchemes = {
                1: { // Cyan + Bleu nuit
                    color1: new THREE.Vector3(0.055, 0.647, 0.914),  // #0ea5e9 cyan
                    color2: new THREE.Vector3(0.039, 0.086, 0.157)   // #0a1628 bleu nuit
                },
                2: { // Sky blue + Bleu nuit
                    color1: new THREE.Vector3(0.220, 0.741, 0.973),  // #38bdf8 sky
                    color2: new THREE.Vector3(0.059, 0.157, 0.278)   // #0f2847 bleu nuit
                },
                3: { // Cyan + Bleu acier + Bleu nuit
                    color1: new THREE.Vector3(0.055, 0.647, 0.914),  // #0ea5e9 cyan
                    color2: new THREE.Vector3(0.039, 0.086, 0.157),  // #0a1628 bleu nuit
                    color3: new THREE.Vector3(0.239, 0.416, 0.541)   // #3d6a8a bleu acier
                },
                4: { // Teal + Bleu nuit moyen
                    color1: new THREE.Vector3(0.024, 0.714, 0.831),  // #06b6d4 teal
                    color2: new THREE.Vector3(0.102, 0.227, 0.361),  // #1a3a5c bleu moyen
                    color3: new THREE.Vector3(0.353, 0.541, 0.659)   // #5a8aa8 bleu gris
                },
                5: { // Bleu pâle + Bleu nuit profond
                    color1: new THREE.Vector3(0.557, 0.722, 0.831),  // #8eb8d4 bleu pâle
                    color2: new THREE.Vector3(0.039, 0.086, 0.157),  // #0a1628 bleu nuit
                    color3: new THREE.Vector3(0.149, 0.302, 0.451),  // #264d73 bleu clair
                    color4: new THREE.Vector3(0.051, 0.122, 0.235),  // #0d1f3c bleu foncé
                    color5: new THREE.Vector3(0.239, 0.416, 0.541),  // #3d6a8a bleu acier
                    color6: new THREE.Vector3(0.059, 0.157, 0.278)   // #0f2847
                }
            };
            this.currentScheme = 5;
            this.init();
        }

        setColorScheme(scheme) {
            if (!this.colorSchemes[scheme]) return;
            this.currentScheme = scheme;
            const colors = this.colorSchemes[scheme];
            const uniforms = this.gradientBackground.uniforms;

            if (scheme === 3) {
                uniforms.uColor1.value.copy(colors.color1);
                uniforms.uColor2.value.copy(colors.color2);
                uniforms.uColor3.value.copy(colors.color3);
                uniforms.uColor4.value.copy(colors.color1);
                uniforms.uColor5.value.copy(colors.color2);
                uniforms.uColor6.value.copy(colors.color3);
            } else if (scheme === 4) {
                uniforms.uColor1.value.copy(colors.color1);
                uniforms.uColor2.value.copy(colors.color2);
                uniforms.uColor3.value.copy(colors.color3);
                uniforms.uColor4.value.copy(colors.color1);
                uniforms.uColor5.value.copy(colors.color2);
                uniforms.uColor6.value.copy(colors.color3);
            } else if (scheme === 5) {
                uniforms.uColor1.value.copy(colors.color1);
                uniforms.uColor2.value.copy(colors.color2);
                uniforms.uColor3.value.copy(colors.color3);
                uniforms.uColor4.value.copy(colors.color4);
                uniforms.uColor5.value.copy(colors.color5);
                uniforms.uColor6.value.copy(colors.color6);
            } else {
                uniforms.uColor1.value.copy(colors.color1);
                uniforms.uColor2.value.copy(colors.color2);
                uniforms.uColor3.value.copy(colors.color1);
                uniforms.uColor4.value.copy(colors.color2);
                uniforms.uColor5.value.copy(colors.color1);
                uniforms.uColor6.value.copy(colors.color2);
            }

            // Settings per scheme - all use app's bleu nuit base
            this.scene.background = new THREE.Color(0x0a1628);
            uniforms.uDarkNavy.value.set(0.039, 0.086, 0.157);

            if (scheme === 1 || scheme === 5) {
                uniforms.uGradientSize.value = 0.45;
                uniforms.uGradientCount.value = 12.0;
                uniforms.uSpeed.value = 1.5;
                uniforms.uColor1Weight.value = 0.6;
                uniforms.uColor2Weight.value = 1.6;
            } else if (scheme === 4) {
                uniforms.uGradientSize.value = 0.5;
                uniforms.uGradientCount.value = 10.0;
                uniforms.uSpeed.value = 1.3;
                uniforms.uColor1Weight.value = 0.7;
                uniforms.uColor2Weight.value = 1.5;
            } else {
                uniforms.uGradientSize.value = 1.0;
                uniforms.uGradientCount.value = 6.0;
                uniforms.uSpeed.value = 1.2;
                uniforms.uColor1Weight.value = 1.0;
                uniforms.uColor2Weight.value = 1.0;
            }
        }

        init() {
            this.gradientBackground.init();
            this.setColorScheme(this.currentScheme);
            this.render();
            this.tick();

            window.addEventListener("resize", () => this.onResize());
            window.addEventListener("mousemove", (ev) => this.onMouseMove(ev));
            window.addEventListener("touchmove", (ev) => this.onTouchMove(ev));

            document.addEventListener("visibilitychange", () => {
                if (!document.hidden) this.render();
            });
        }

        onTouchMove(ev) {
            const touch = ev.touches[0];
            this.onMouseMove({ clientX: touch.clientX, clientY: touch.clientY });
        }

        onMouseMove(ev) {
            this.mouse = {
                x: ev.clientX / window.innerWidth,
                y: 1 - ev.clientY / window.innerHeight
            };
            this.touchTexture.addTouch(this.mouse);
        }

        getViewSize() {
            const fovInRadians = (this.camera.fov * Math.PI) / 180;
            const height = Math.abs(this.camera.position.z * Math.tan(fovInRadians / 2) * 2);
            return { width: height * this.camera.aspect, height };
        }

        update(delta) {
            this.touchTexture.update();
            this.gradientBackground.update(delta);
        }

        render() {
            const delta = this.clock.getDelta();
            const clampedDelta = Math.min(delta, 0.1);
            this.renderer.render(this.scene, this.camera);
            this.update(clampedDelta);
        }

        tick() {
            this.render();
            requestAnimationFrame(() => this.tick());
        }

        onResize() {
            this.camera.aspect = window.innerWidth / window.innerHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(window.innerWidth, window.innerHeight);
            this.gradientBackground.onResize(window.innerWidth, window.innerHeight);
        }
    }

    // ============================================================
    // Initialize App
    // ============================================================
    const app = new App();

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => app.render());
    } else {
        setTimeout(() => app.render(), 0);
    }

    // ============================================================
    // Color Scheme Buttons
    // ============================================================
    const colorButtons = document.querySelectorAll(".color-btn");
    colorButtons.forEach((btn) => {
        btn.addEventListener("click", () => {
            const scheme = parseInt(btn.dataset.scheme);
            app.setColorScheme(scheme);
            colorButtons.forEach((b) => b.classList.remove("active"));
            btn.classList.add("active");
        });
    });

    // ============================================================
    // Custom Cursor
    // ============================================================
    const cursor = document.getElementById("customCursor");
    let mouseX = 0, mouseY = 0;

    document.addEventListener("mousemove", (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        cursor.style.left = mouseX + "px";
        cursor.style.top = mouseY + "px";
    });

    // Cursor grows on interactive elements
    document.querySelectorAll("a, button, .nav-card").forEach((el) => {
        el.addEventListener("mouseenter", () => {
            cursor.style.width = "55px";
            cursor.style.height = "55px";
            cursor.style.borderWidth = "3px";
            cursor.style.borderColor = "rgba(255,255,255,0.8)";
        });
        el.addEventListener("mouseleave", () => {
            cursor.style.width = "40px";
            cursor.style.height = "40px";
            cursor.style.borderWidth = "2px";
            cursor.style.borderColor = "white";
        });
    });

    // Pulse effect on movement
    let lastMouseMoveTime = 0;
    let pulseFrame = null;
    function checkPulse() {
        if (Date.now() - lastMouseMoveTime > 100) {
            cursor.style.borderWidth = "2px";
            pulseFrame = null;
        } else {
            pulseFrame = requestAnimationFrame(checkPulse);
        }
    }
    document.addEventListener("mousemove", () => {
        lastMouseMoveTime = Date.now();
        cursor.style.borderWidth = "2.5px";
        if (!pulseFrame) pulseFrame = requestAnimationFrame(checkPulse);
    });

    // ============================================================
    // Live Clock
    // ============================================================
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const display = document.getElementById('timeDisplay');
        if (display) {
            display.innerHTML = `${h}:${m}<span class="time-seconds">:${s}</span>`;
        }
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ============================================================
    // Hide cursor on touch devices
    // ============================================================
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        cursor.style.display = 'none';
        document.body.style.cursor = 'auto';
    }

    // ============================================================
    // Salutation selon l'heure locale de l'utilisateur
    // ============================================================
    (function() {
        const h = new Date().getHours();
        let greeting, icon;
        if (h >= 5 && h < 12)       { greeting = 'Bonjour';        icon = '☀️'; }
        else if (h >= 12 && h < 18) { greeting = 'Bon après-midi'; icon = '🌤️'; }
        else if (h >= 18 && h < 22) { greeting = 'Bonsoir';        icon = '🌅'; }
        else                        { greeting = 'Bonne nuit';     icon = '🌙'; }
        const el = document.querySelector('.greeting-text');
        if (el) el.textContent = icon + ' ' + greeting;

        // Date locale en français
        const dateEl = document.querySelector('.date-text');
        if (dateEl) {
            try {
                dateEl.textContent = new Date().toLocaleDateString('fr-FR', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
            } catch(e) {}
        }
    })();
    </script>

    <?php include('./student.transition.php'); ?>
</body>
</html>
