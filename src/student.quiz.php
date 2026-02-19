<?php
/**
 * Quiz interactif — Connais-tu l'UAZ ?
 * Mini-quiz sur l'Université Adventiste Zurcher
 * Inspiré par le CSS Video Game Quiz (CodePen)
 * Palette Bleu Nuit
 */

require('../data/backdb.php');
require('../data/middleware.php');
initMiddleware($dtb);

if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ' . $app_base . '/login');
    exit;
}

$studentInfo = getStudentInfo();
$studentName = htmlspecialchars(($studentInfo['student_prenom'] ?? '') . ' ' . ($studentInfo['student_nom'] ?? ''));

$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base  = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz UAZ — Espace Étudiant</title>
    <link rel="shortcut icon" href="<?=$app_base?>/file/logo-coldbloud.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0a1628;
            font-family: "Inter", sans-serif;
            color: #e8f1f8;
            overflow: hidden;
            height: 100vh;
            cursor: none;
        }

        /* ===== SVG Squiggly filters (hidden) ===== */
        svg { position: absolute; width: 0; height: 0; }

        /* ===== Topbar ===== */
        .std-topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            height: 56px; display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem;
            background: rgba(10, 22, 40, 0.85);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
        }
        .std-topbar .logo-img { width: 34px; height: 34px; border-radius: 8px; }
        .std-topbar .page-title {
            font-family: "Syne", sans-serif; font-weight: 700; font-size: 0.9rem;
            color: #e8f1f8; letter-spacing: 0.02em;
        }
        .std-nav-icon {
            width: 36px; height: 36px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; text-decoration: none;
            color: rgba(142, 184, 212, 0.7); font-size: 1.1rem;
            border: 1px solid rgba(142, 184, 212, 0.12);
            background: rgba(142, 184, 212, 0.06);
            transition: all 0.3s ease; cursor: none;
        }
        .std-nav-icon:hover {
            color: #0ea5e9; border-color: rgba(14, 165, 233, 0.4);
            background: rgba(14, 165, 233, 0.12); transform: translateY(-2px);
        }
        .std-nav-icon.logout:hover { color: #f87171; border-color: rgba(248,113,113,0.4); background: rgba(248,113,113,0.12); }

        /* ===== Background grain & vignette ===== */
        .overlay {
            position: fixed; inset: 0; z-index: 1; pointer-events: none;
            background-image:
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><filter id="n"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%25" height="100%25" filter="url(%23n)" opacity="0.03"/></svg>'),
                radial-gradient(ellipse at center, rgba(0,0,0,0) 0%, rgba(0,0,0,0.35) 100%);
        }
        canvas.grain { position: fixed; top: 0; left: 0; z-index: 2; pointer-events: none; }

        /* ===== Main container ===== */
        .quiz-container {
            position: relative; z-index: 10;
            width: 100%; height: 100vh;
            padding-top: 56px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }

        /* ===== Loading / Intro screen ===== */
        .quiz-loading {
            position: fixed; inset: 0; z-index: 200;
            background: linear-gradient(135deg, #0c1e38, #0a1628);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            transition: clip-path 0.8s cubic-bezier(0.65, 0, 0.35, 1);
            clip-path: inset(0 0 0 0);
        }
        .quiz-loading.loaded { clip-path: inset(0 0 100% 0); }

        .quiz-loading .sunburst {
            position: absolute; width: 500px; height: 500px;
            animation: spin 20s linear infinite;
            opacity: 0.08;
        }
        .quiz-loading .sunburst::before {
            content: ''; position: absolute; inset: 0;
            background: conic-gradient(from 0deg, transparent 0%, rgba(14,165,233,0.3) 10%, transparent 20%);
            border-radius: 50%;
        }

        .loading-title {
            font-family: "Syne", sans-serif; font-size: clamp(3rem, 8vw, 6rem);
            font-weight: 800; color: white; position: relative; z-index: 5;
            text-align: center; line-height: 1.1;
        }
        .loading-title span {
            display: inline-block; opacity: 0; transform: scale(0);
        }
        .loading-subtitle {
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(1rem, 2.5vw, 1.5rem);
            color: rgba(14, 165, 233, 0.8); position: relative; z-index: 5;
            margin-top: 0.5rem;
        }
        .loading-subtitle span {
            display: inline-block; opacity: 0; transform: scale(0);
        }
        .loading-btn {
            position: relative; z-index: 5; margin-top: 2.5rem;
            background: transparent; border: 2px solid rgba(14, 165, 233, 0.5);
            color: #0ea5e9; padding: 0.75rem 2rem; border-radius: 12px;
            font-family: "Syne", sans-serif; font-size: 1.1rem; font-weight: 600;
            cursor: none; opacity: 0;
            animation: pulse 1.5s 2.5s infinite, fadeInSimple 0.5s 2s forwards;
            transition: all 0.3s ease;
        }
        .loading-btn:hover {
            background: rgba(14, 165, 233, 0.15); border-color: #0ea5e9;
            transform: translateY(-3px); box-shadow: 0 10px 30px rgba(14, 165, 233, 0.2);
        }

        /* ===== Circle backdrop ===== */
        .quiz-circle {
            position: absolute; left: 50%; top: 50%;
            transform: translate(-50%, -50%);
            width: 320px; height: 320px; border-radius: 50%;
            background: rgba(14, 165, 233, 0.06);
            border: 1px solid rgba(14, 165, 233, 0.12);
            transition: all 0.6s cubic-bezier(1, 0, 0.2, 0.99);
            animation: squiggly-anim 0.3s linear infinite;
        }

        /* ===== Question area (left) ===== */
        .quiz-question {
            position: absolute; left: 5%; top: 50%; transform: translateY(-50%);
            width: 30%; max-width: 380px;
        }
        .quiz-question h1 {
            font-family: "Syne", sans-serif; font-size: clamp(1.1rem, 2.2vw, 1.6rem);
            font-weight: 700; color: #f1f5f9; line-height: 1.3;
            border-top: 2px solid rgba(14, 165, 233, 0.4); padding-top: 0.75rem;
        }
        .quiz-question .instruction {
            font-size: 0.8rem; color: rgba(142, 184, 212, 0.5); margin-top: 0.5rem;
        }
        .quiz-hint-btn {
            display: inline-block; font-size: 0.8rem; color: #0ea5e9; margin-top: 0.75rem;
            cursor: none; text-decoration: underline; background: none; border: none;
            font-family: inherit; transition: color 0.2s;
        }
        .quiz-hint-btn:hover { color: #38bdf8; }
        .quiz-hint {
            display: none; margin-top: 0.5rem; font-size: 0.85rem;
            color: rgba(245, 158, 11, 0.8); font-style: italic;
        }

        /* ===== Question number indicator ===== */
        .question-number {
            font-family: "Space Grotesk", sans-serif; font-size: 0.75rem;
            color: rgba(14, 165, 233, 0.6); letter-spacing: 0.1em;
            text-transform: uppercase; margin-bottom: 0.5rem;
        }

        /* ===== Scene (center) ===== */
        .quiz-scene {
            position: absolute; left: 50%; top: 50%;
            transform: translate(-50%, -50%);
            width: 200px; height: 200px;
            display: flex; align-items: center; justify-content: center;
            animation: float 3s ease-in-out infinite;
        }
        .scene-icon {
            font-size: 6rem; filter: drop-shadow(0 4px 20px rgba(14, 165, 233, 0.3));
            animation: squiggly-anim 0.3s linear infinite;
        }

        /* ===== Answers (right) ===== */
        .quiz-answers {
            position: absolute; right: 5%; top: 50%; transform: translateY(-50%);
            width: 30%; max-width: 360px;
            counter-reset: answer-count;
        }
        .answer-card {
            position: relative; padding: 0.875rem 1.25rem 0.875rem 1.5rem;
            border: 2px solid rgba(142, 184, 212, 0.2);
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px);
            color: #e8f1f8; font-size: 0.95rem; font-weight: 500;
            margin-bottom: 1rem; cursor: none;
            transition: all 0.3s ease; transform: skewX(-4deg);
            border-radius: 8px; counter-increment: answer-count;
            opacity: 1; left: 0;
        }
        .answer-card:hover {
            border-color: rgba(14, 165, 233, 0.5);
            background: rgba(14, 165, 233, 0.1);
            text-indent: 12px;
        }
        .answer-card:hover .answer-num { top: -22px; }
        .answer-card::before { display: none; }
        .answer-num {
            position: absolute; top: -18px; left: -14px;
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(14, 165, 233, 0.2); border: 2px solid rgba(14, 165, 233, 0.4);
            color: #0ea5e9; font-weight: 700; font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15);
        }
        .answer-card.correct {
            background: rgba(34, 197, 94, 0.25); border-color: rgba(34, 197, 94, 0.6);
        }
        .answer-card.wrong {
            background: rgba(239, 68, 68, 0.25); border-color: rgba(239, 68, 68, 0.6);
        }

        /* ===== Feedback splash ===== */
        .quiz-feedback {
            position: fixed; left: 50%; top: 50%;
            transform: translate(-50%, -50%) scale(0) rotate(15deg);
            z-index: 50; font-family: "Syne", sans-serif;
            font-size: clamp(3rem, 8vw, 5rem); font-weight: 800;
            text-transform: uppercase; letter-spacing: -3px;
            pointer-events: none; transition: all 0.3s ease;
            animation: squiggly-anim 0.3s linear infinite;
        }
        .quiz-feedback.correct { color: #22c55e; text-shadow: 0 4px 0 #15803d; }
        .quiz-feedback.wrong { color: #ef4444; text-shadow: 0 4px 0 #991b1b; }
        .quiz-feedback.show { transform: translate(-50%, -50%) scale(1) rotate(0deg); }

        /* ===== Breadcrumbs ===== */
        .quiz-breadcrumbs {
            position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%);
            z-index: 20; display: flex; gap: 1.25rem;
        }
        .breadcrumb-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(142, 184, 212, 0.2);
            transition: all 0.3s ease; position: relative;
        }
        .breadcrumb-dot.active {
            background: white; box-shadow: 0 0 0 5px rgba(14, 165, 233, 0.25);
        }
        .breadcrumb-dot.correct {
            background: transparent;
        }
        .breadcrumb-dot.correct::after {
            content: '\F26E'; font-family: "bootstrap-icons"; font-size: 14px;
            position: absolute; top: -5px; left: -3px; color: #22c55e;
        }
        .breadcrumb-dot.wrong {
            background: transparent;
        }
        .breadcrumb-dot.wrong::after {
            content: '\F62A'; font-family: "bootstrap-icons"; font-size: 14px;
            position: absolute; top: -5px; left: -3px; color: #ef4444;
        }

        /* ===== Score info (bottom-right during quiz) ===== */
        .quiz-score-live {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 20;
            font-family: "Space Grotesk", sans-serif; font-size: 0.8rem;
            color: rgba(142, 184, 212, 0.5); letter-spacing: 0.05em;
        }
        .quiz-score-live span { color: #0ea5e9; font-weight: 600; }

        /* ===== End modal ===== */
        .quiz-modal-overlay {
            position: fixed; inset: 0; z-index: 150;
            background: rgba(10, 22, 40, 0.85); backdrop-filter: blur(8px);
            display: none; align-items: center; justify-content: center;
        }
        .quiz-modal-overlay.show { display: flex; }
        .quiz-modal {
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border: 1px solid rgba(14, 165, 233, 0.25);
            border-radius: 20px; padding: 3rem; text-align: center;
            max-width: 500px; width: 90%;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            animation: scaleIn 0.6s ease;
        }
        .quiz-modal h1 {
            font-family: "Syne", sans-serif; font-size: 2.5rem; font-weight: 800;
            color: #f1f5f9; margin-bottom: 0.5rem;
        }
        .quiz-modal .final-score {
            font-family: "Space Grotesk", sans-serif; font-size: 1.5rem;
            color: #0ea5e9; font-weight: 600; margin-bottom: 1rem;
        }
        .quiz-modal .final-message {
            color: #94a3b8; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem;
        }
        .quiz-modal .result-emoji {
            font-size: 4rem; margin-bottom: 1rem; display: block;
        }
        .modal-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1.75rem; border-radius: 12px;
            font-family: "Syne", sans-serif; font-size: 1rem; font-weight: 600;
            cursor: none; border: none; transition: all 0.3s ease; margin: 0 0.5rem;
        }
        .modal-btn.primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white; box-shadow: 0 4px 20px rgba(14, 165, 233, 0.3);
        }
        .modal-btn.primary:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(14, 165, 233, 0.4); }
        .modal-btn.secondary {
            background: rgba(51, 65, 85, 0.6); color: #e2e8f0;
            border: 1px solid rgba(71, 85, 105, 0.5);
        }
        .modal-btn.secondary:hover { background: rgba(71, 85, 105, 0.6); transform: translateY(-3px); }

        /* ===== Custom Cursor ===== */
        .custom-cursor {
            position: fixed; width: 40px; height: 40px;
            border: 2px solid white; border-radius: 50%;
            pointer-events: none; z-index: 1000;
            transform: translate(-50%, -50%);
            transition: width 0.2s ease, height 0.2s ease, border-color 0.2s ease;
            background: transparent; will-change: transform;
        }
        .custom-cursor::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 6px; height: 6px; background: white; border-radius: 50%;
        }

        /* ===== Animations ===== */
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) translateY(0); }
            50% { transform: translate(-50%, -50%) translateY(-15px); }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        @keyframes fadeInSimple { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleIn {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes squiggly-anim {
            0%  { filter: url('#squiggly-0'); }
            25% { filter: url('#squiggly-1'); }
            50% { filter: url('#squiggly-2'); }
            75% { filter: url('#squiggly-3'); }
            100%{ filter: url('#squiggly-4'); }
        }
        @keyframes bounceIn {
            0%   { transform: scale(0); }
            20%  { transform: scale(1.15); }
            40%  { transform: scale(0.9); }
            60%  { transform: scale(1.05); }
            80%  { transform: scale(0.97); }
            100% { transform: scale(1); }
        }

        /* ===== Responsive ===== */
        @media (max-width: 1024px) {
            .quiz-question { position: relative; left: auto; top: auto; transform: none;
                width: 90%; max-width: none; text-align: center; margin-bottom: 1rem; }
            .quiz-answers { position: relative; right: auto; top: auto; transform: none;
                width: 90%; max-width: none; }
            .quiz-scene { position: relative; left: auto; top: auto; transform: none;
                margin: 1rem 0; width: 120px; height: 120px; }
            .scene-icon { font-size: 4rem; }
            .quiz-circle { display: none; }
            .quiz-container { justify-content: flex-start; padding-top: 70px; overflow-y: auto; }
            .quiz-content { display: flex; flex-direction: column; align-items: center; width: 100%; padding: 0 1rem; }
        }
        @media (max-width: 768px) {
            .std-topbar { height: 50px; padding: 0 1rem; }
            .std-topbar .logo-img { width: 28px; height: 28px; }
            .std-topbar .page-title { font-size: 0.78rem; }
            .answer-card { font-size: 0.85rem; padding: 0.75rem 1rem 0.75rem 1.25rem; }
            .quiz-feedback { font-size: 2.5rem; }
            .quiz-breadcrumbs { bottom: 1rem; gap: 0.75rem; }
        }
    </style>
</head>
<body>

<!-- SVG Squiggly Filters -->
<svg version="1.1" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <filter id="squiggly-0">
            <feTurbulence baseFrequency="0.06" numOctaves="3" result="noise" seed="0"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="6"/>
        </filter>
        <filter id="squiggly-1">
            <feTurbulence baseFrequency="0.06" numOctaves="3" result="noise" seed="1"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="8"/>
        </filter>
        <filter id="squiggly-2">
            <feTurbulence baseFrequency="0.06" numOctaves="3" result="noise" seed="2"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="6"/>
        </filter>
        <filter id="squiggly-3">
            <feTurbulence baseFrequency="0.06" numOctaves="3" result="noise" seed="3"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="8"/>
        </filter>
        <filter id="squiggly-4">
            <feTurbulence baseFrequency="0.06" numOctaves="3" result="noise" seed="4"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="6"/>
        </filter>
    </defs>
</svg>

<!-- Grain & Vignette -->
<div class="overlay"></div>
<canvas class="grain"></canvas>

<!-- Topbar -->
<div class="std-topbar">
    <div style="display:flex;align-items:center;gap:0.5rem;">
        <img src="<?=$app_base?>/file/UAZ Official.png" alt="UAZ" class="logo-img">
        <span class="page-title">Quiz UAZ</span>
    </div>
    <div style="display:flex;align-items:center;gap:0.5rem;">
        <a href="<?=$app_base?>/student/home" class="std-nav-icon home" data-tooltip="Accueil">
            <i class="bi bi-house-door-fill"></i>
        </a>
        <a href="<?=$app_base?>/student/dashboard" class="std-nav-icon" data-tooltip="Notes">
            <i class="bi bi-clipboard-data-fill"></i>
        </a>
        <a href="<?=$app_base?>/logout" class="std-nav-icon logout" data-tooltip="Déconnexion">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<!-- Loading / Intro Screen -->
<div class="quiz-loading" id="quizLoading">
    <div class="sunburst"></div>
    <div class="loading-title" id="loadingTitle"></div>
    <div class="loading-subtitle" id="loadingSubtitle"></div>
    <button class="loading-btn" id="startBtn" onclick="startQuiz()">
        <i class="bi bi-play-fill"></i> Commencer le quiz
    </button>
</div>

<!-- Quiz Container -->
<div class="quiz-container" id="quizContainer" style="display:none;">

    <!-- Circle backdrop -->
    <div class="quiz-circle" id="quizCircle"></div>

    <!-- Content wrapper for mobile layout -->
    <div class="quiz-content">

        <!-- Question (left) -->
        <div class="quiz-question" id="questionArea">
            <div class="question-number" id="questionNumber">Question 1 / 10</div>
            <h1 id="questionText"></h1>
            <p class="instruction">Clique sur une réponse ou utilise les touches 1, 2, 3</p>
            <button class="quiz-hint-btn" id="hintBtn">💡 Besoin d'un indice ?</button>
            <div class="quiz-hint" id="hintText"></div>
        </div>

        <!-- Scene (center) -->
        <div class="quiz-scene" id="sceneArea">
            <div class="scene-icon" id="sceneIcon"></div>
        </div>

        <!-- Answers (right) -->
        <div class="quiz-answers" id="answersArea">
            <div class="answer-card" id="answer0">
                <span class="answer-num">1</span>
                <span class="answer-text"></span>
            </div>
            <div class="answer-card" id="answer1">
                <span class="answer-num">2</span>
                <span class="answer-text"></span>
            </div>
            <div class="answer-card" id="answer2">
                <span class="answer-num">3</span>
                <span class="answer-text"></span>
            </div>
        </div>

    </div>
</div>

<!-- Feedback splash -->
<div class="quiz-feedback" id="feedback"></div>

<!-- Breadcrumbs -->
<div class="quiz-breadcrumbs" id="breadcrumbs"></div>

<!-- Live score -->
<div class="quiz-score-live" id="liveScore" style="display:none;">
    Score : <span id="scoreDisplay">0</span> / <span id="totalDisplay">0</span>
</div>

<!-- End Modal -->
<div class="quiz-modal-overlay" id="endModal">
    <div class="quiz-modal">
        <span class="result-emoji" id="resultEmoji"></span>
        <h1 id="resultTitle">Quiz terminé !</h1>
        <div class="final-score" id="finalScore"></div>
        <div class="final-message" id="finalMessage"></div>
        <div>
            <button class="modal-btn primary" onclick="restartQuiz()">
                <i class="bi bi-arrow-clockwise"></i> Rejouer
            </button>
            <a href="<?=$app_base?>/student/home" class="modal-btn secondary" style="text-decoration:none;">
                <i class="bi bi-house-door"></i> Accueil
            </a>
        </div>
    </div>
</div>

<!-- Custom Cursor -->
<div class="custom-cursor" id="customCursor"></div>

<script>
// ================================================================
// UAZ Quiz Data
// ================================================================
const questions = [
    // ===== HISTOIRE & FONDATION =====
    {
        question: "En quelle année l'Université Adventiste Zurcher a-t-elle été fondée ?",
        correct: "1996",
        wrong: ["1962", "1935"],
        icon: "🏛️",
        hint: "L'UAZ existe déjà depuis trente ans.",
        bg: "linear-gradient(135deg, #0c1e38, #0a1628)"
    },
    {
        question: "Quel est le nom du fondateur de l'UAZ ?",
        correct: "Jean Rudolf Zurcher",
        wrong: ["Jean Nussbaum Pichot", "Albert Schweitzer Zurcher"],
        icon: "👤",
        hint: "L'université porte son nom !",
        bg: "linear-gradient(135deg, #1a2d4a, #0a1628)"
    },
    {
        question: "Avant de devenir université, l'UAZ était quel type d'établissement ?",
        correct: "Un séminaire théologique",
        wrong: ["Un lycée technique", "Une école primaire"],
        icon: "📜",
        hint: "L'enseignement religieux était au cœur de ses débuts.",
        bg: "linear-gradient(135deg, #0f2847, #0a1628)"
    },
    {
        question: "L'UAZ fait partie de quel réseau mondial d'universités ?",
        correct: "Le réseau éducatif adventiste",
        wrong: ["L'Alliance Francophone", "Le réseau UNESCO"],
        icon: "🌍",
        hint: "C'est lié à la confession religieuse de l'université.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "Quel sigle désigne l'Université Adventiste Zurcher ?",
        correct: "UAZ",
        wrong: ["UZA", "ZUA"],
        icon: "🔤",
        hint: "L'ordre suit : Université, Adventiste, Zurcher.",
        bg: "linear-gradient(135deg, #102842, #0a1628)"
    },
    {
        question: "L'UAZ porte le nom de famille de son fondateur. De quel pays était-il originaire ?",
        correct: "Suisse",
        wrong: ["France", "Allemagne"],
        icon: "🇨🇭",
        hint: "Le nom 'Zurcher' a une consonance germanique.",
        bg: "linear-gradient(135deg, #132d4b, #0a1628)"
    },

    // ===== GÉOGRAPHIE & LOCALISATION =====
    {
        question: "Dans quel district de Madagascar se trouve l'UAZ ?",
        correct: "Antsirabe",
        wrong: ["Antananarivo", "Fianarantsoa"],
        icon: "📍",
        hint: "C'est la ville d'eau.",
        bg: "linear-gradient(135deg, #0f2847, #0a1628)"
    },
    {
        question: "Dans quelle région de Madagascar se situe Antsirabe ?",
        correct: "Vakinankaratra",
        wrong: ["Analamanga", "Haute Matsiatra"],
        icon: "🗺️",
        hint: "C'est une région des Hautes Terres centrales.",
        bg: "linear-gradient(135deg, #0b2138, #0a1628)"
    },
    {
        question: "Quel est le surnom donné à Antsirabe ?",
        correct: "La ville d'eau",
        wrong: ["La ville lumière", "La cité du soleil"],
        icon: "💧",
        hint: "La ville est connue pour ses sources thermales.",
        bg: "linear-gradient(135deg, #0f2d4e, #0a1628)"
    },
    {
        question: "Quel est l'altitude approximative d'Antsirabe ?",
        correct: "1 500 mètres",
        wrong: ["800 mètres", "2 200 mètres"],
        icon: "⛰️",
        hint: "C'est sur les Hautes Terres, mais pas le sommet.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "Quel est le climat prédominant à Antsirabe ?",
        correct: "Tropical d'altitude",
        wrong: ["Équatorial humide", "Semi-aride"],
        icon: "🌡️",
        hint: "L'altitude modifie le climat tropical habituel.",
        bg: "linear-gradient(135deg, #112a45, #0a1628)"
    },
    {
        question: "Quelle est la province historique dans laquelle se trouve Antsirabe ?",
        correct: "Antananarivo",
        wrong: ["Toamasina", "Mahajanga"],
        icon: "🏘️",
        hint: "C'est la même province que la capitale.",
        bg: "linear-gradient(135deg, #0e2845, #0a1628)"
    },
    {
        question: "À quelle distance approximative d'Antananarivo se trouve Antsirabe ?",
        correct: "170 km",
        wrong: ["80 km", "350 km"],
        icon: "🚗",
        hint: "C'est environ 3-4 heures de route.",
        bg: "linear-gradient(135deg, #14304d, #0a1628)"
    },
    {
        question: "Antsirabe est célèbre pour ses sources d'eau. Quel type de sources ?",
        correct: "Thermales",
        wrong: ["Glaciaires", "Souterraines karstiques"],
        icon: "♨️",
        hint: "L'eau y est naturellement chaude.",
        bg: "linear-gradient(135deg, #0c1e38, #0a1628)"
    },
    {
        question: "Dans quel axe routier national se trouve Antsirabe ?",
        correct: "RN7",
        wrong: ["RN2", "RN4"],
        icon: "🛣️",
        hint: "C'est la route la plus touristique de Madagascar.",
        bg: "linear-gradient(135deg, #162a46, #0a1628)"
    },

    // ===== VIE SPIRITUELLE =====
    {
        question: "Quelle confession religieuse est associée à l'UAZ ?",
        correct: "Adventiste du Septième Jour",
        wrong: ["Catholique Romaine", "Église Luthérienne"],
        icon: "⛪",
        hint: "C'est dans le nom de l'université !",
        bg: "linear-gradient(135deg, #162a46, #0a1628)"
    },
    {
        question: "Quel jour de la semaine est observé comme sabbat à l'UAZ ?",
        correct: "Le samedi",
        wrong: ["Le dimanche", "Le vendredi"],
        icon: "📅",
        hint: "Les Adventistes observent le septième jour de la semaine.",
        bg: "linear-gradient(135deg, #0e2845, #0a1628)"
    },
    {
        question: "Quelle activité commence traditionnellement chaque journée à l'UAZ ?",
        correct: "Le culte du matin",
        wrong: ["Le sport matinal", "L'appel des étudiants"],
        icon: "🙏",
        hint: "C'est un moment de recueillement spirituel.",
        bg: "linear-gradient(135deg, #14304d, #0a1628)"
    },
    {
        question: "Quel événement spirituel hebdomadaire rassemble tout le campus ?",
        correct: "Le culte du sabbat",
        wrong: ["La messe du dimanche", "Le séminaire du mercredi"],
        icon: "🕊️",
        hint: "C'est le jour sacré des Adventistes.",
        bg: "linear-gradient(135deg, #0e2540, #0a1628)"
    },
    {
        question: "À quelle heure commence généralement le sabbat à l'UAZ ?",
        correct: "Au coucher du soleil le vendredi",
        wrong: ["À minuit le samedi", "À 6h le samedi matin"],
        icon: "🌅",
        hint: "Les Adventistes suivent le calendrier biblique.",
        bg: "linear-gradient(135deg, #0f2847, #0a1628)"
    },
    {
        question: "Quel livre sacré est à la base de l'enseignement spirituel à l'UAZ ?",
        correct: "La Bible",
        wrong: ["Le Coran", "Le Talmud"],
        icon: "📖",
        hint: "C'est le texte fondamental du christianisme.",
        bg: "linear-gradient(135deg, #1a2d4a, #0a1628)"
    },
    {
        question: "Quel mode de vie alimentaire est encouragé par l'église adventiste ?",
        correct: "Le végétarisme",
        wrong: ["Le régime carnivore", "Le jeûne permanent"],
        icon: "🥗",
        hint: "La santé et une alimentation saine sont des valeurs adventistes.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "Comment appelle-t-on le moment de prière en groupe le soir à l'UAZ ?",
        correct: "Le culte du soir",
        wrong: ["Les vêpres", "La méditation nocturne"],
        icon: "🌙",
        hint: "C'est le pendant du culte du matin.",
        bg: "linear-gradient(135deg, #0b2138, #0a1628)"
    },

    // ===== ACADÉMIQUE & ÉTUDES =====
    {
        question: "Quelle est la vision de l'UAZ ?",
        correct: "Préparer aujourd'hui les leaders de demain",
        wrong: ["L'amour de Dieu et du prochain", "Le service dans l'excellence"],
        icon: "🎓",
        hint: "Elle combine leadership et spiritualité.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "En quelle langue se déroulent principalement les cours à l'UAZ ?",
        correct: "Français",
        wrong: ["Malgache", "Anglais"],
        icon: "🗣️",
        hint: "C'est la langue officielle de l'enseignement supérieur à Madagascar.",
        bg: "linear-gradient(135deg, #112a45, #0a1628)"
    },
    {
        question: "Quel département n'existe PAS à l'UAZ ?",
        correct: "Médecine vétérinaire",
        wrong: ["Théologie", "Informatique"],
        icon: "🔬",
        hint: "Pensez aux animaux...",
        bg: "linear-gradient(135deg, #132d4b, #0a1628)"
    },
    {
        question: "Quelle mention n'est PAS proposée à l'UAZ ?",
        correct: "Architecture navale",
        wrong: ["Sciences Infirmières", "Gestion"],
        icon: "📚",
        hint: "Pensez au maritime...",
        bg: "linear-gradient(135deg, #102640, #0a1628)"
    },
    {
        question: "Combien de semestres comporte une année académique à l'UAZ ?",
        correct: "4 semestres",
        wrong: ["3 trimestres", "1 quadrimestre"],
        icon: "📆",
        hint: "Deux semestres principaux, chacun divisé en deux parties.",
        bg: "linear-gradient(135deg, #0c1e38, #0a1628)"
    },
    {
        question: "Quel système de notation est utilisé à l'UAZ ?",
        correct: "Sur 20",
        wrong: ["Sur 100", "Lettres (A, B, C)"],
        icon: "💯",
        hint: "C'est le système français classique.",
        bg: "linear-gradient(135deg, #0f2847, #0a1628)"
    },
    {
        question: "Quel diplôme obtient-on après 3 ans d'études à l'UAZ ?",
        correct: "Licence",
        wrong: ["Master", "Doctorat"],
        icon: "🎓",
        hint: "C'est le premier grade du système LMD.",
        bg: "linear-gradient(135deg, #162a46, #0a1628)"
    },
    {
        question: "Que signifie le sigle LMD ?",
        correct: "Licence-Master-Doctorat",
        wrong: ["Lycée-Maîtrise-Diplôme", "Latin-Maths-Droit"],
        icon: "📝",
        hint: "C'est le système universitaire standard.",
        bg: "linear-gradient(135deg, #0e2845, #0a1628)"
    },
    {
        question: "La mention Théologie à l'UAZ forme principalement des…",
        correct: "Pasteurs et théologiens",
        wrong: ["Ingénieurs", "Médecins"],
        icon: "✝️",
        hint: "C'est lié au ministère religieux.",
        bg: "linear-gradient(135deg, #14304d, #0a1628)"
    },
    {
        question: "Quelle mention forme les futurs soignants à l'UAZ ?",
        correct: "Sciences Infirmières",
        wrong: ["Pharmacie", "Chirurgie"],
        icon: "🏥",
        hint: "Ils travaillent dans les hôpitaux au chevet des patients.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "Quel domaine d'études inclut l'informatique à l'UAZ ?",
        correct: "Sciences et Technologies",
        wrong: ["Arts et Lettres", "Droit"],
        icon: "💻",
        hint: "C'est le domaine des sciences exactes et appliquées.",
        bg: "linear-gradient(135deg, #102842, #0a1628)"
    },
    {
        question: "Combien d'années d'études faut-il pour obtenir un Master à l'UAZ ?",
        correct: "5 ans (3+2)",
        wrong: ["4 ans", "6 ans"],
        icon: "🎯",
        hint: "Licence en 3 ans, puis 2 ans de plus.",
        bg: "linear-gradient(135deg, #0b2138, #0a1628)"
    },

    // ===== VIE CAMPUS =====
    {
        question: "Comment s'appelle le système de gestion académique de l'UAZ ?",
        correct: "Infinit Registrar",
        wrong: ["Campus Pro", "SchoolNet"],
        icon: "💻",
        hint: "Vous l'utilisez en ce moment même !",
        bg: "linear-gradient(135deg, #102842, #0a1628)"
    },
    {
        question: "Quel type d'hébergement est proposé aux étudiants sur le campus ?",
        correct: "Des dortoirs (internats)",
        wrong: ["Des appartements individuels", "Des villas partagées"],
        icon: "🏠",
        hint: "Les étudiants partagent des chambres.",
        bg: "linear-gradient(135deg, #132d4b, #0a1628)"
    },
    {
        question: "Comment appelle-t-on le lieu où l'on mange à l'UAZ ?",
        correct: "La cafétéria",
        wrong: ["Le restaurant gastronomique", "La brasserie"],
        icon: "🍽️",
        hint: "C'est le terme classique pour une cantine universitaire.",
        bg: "linear-gradient(135deg, #0e2540, #0a1628)"
    },
    {
        question: "Les étudiants de l'UAZ portent-ils un uniforme ?",
        correct: "Oui, en tenue correcte exigée",
        wrong: ["Non, tenue libre", "Seulement le vendredi"],
        icon: "👔",
        hint: "L'UAZ prépare déjà les leaders de demain, donc une tenue professionnelle est de rigueur.",
        bg: "linear-gradient(135deg, #0f2d4e, #0a1628)"
    },
    {
        question: "Quel sport est couramment pratiqué sur le campus de l'UAZ ?",
        correct: "Le football",
        wrong: ["Le hockey sur glace", "Le cricket"],
        icon: "⚽",
        hint: "C'est le sport le plus populaire à Madagascar.",
        bg: "linear-gradient(135deg, #0c1e38, #0a1628)"
    },
    {
        question: "Quel événement annuel rassemble les étudiants pour des activités sportives et culturelles ?",
        correct: "La journée culturelle",
        wrong: ["Le marathon académique", "Les olympiades nationales"],
        icon: "🎭",
        hint: "C'est un temps fort du campus mêlant sport et culture.",
        bg: "linear-gradient(135deg, #1a2d4a, #0a1628)"
    },
    {
        question: "Quel est le jour de repos hebdomadaire au campus de l'UAZ ?",
        correct: "Le samedi (sabbat)",
        wrong: ["Le dimanche", "Le lundi"],
        icon: "😴",
        hint: "C'est le jour sacré des Adventistes.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "Quel est l'endroit où les étudiants peuvent emprunter des livres à l'UAZ ?",
        correct: "La bibliothèque Paul Pichot",
        wrong: ["La médiathèque Paul Pichot", "La librairie Paul Pichot"],
        icon: "📚",
        hint: "Elle se trouve sur le campus même.",
        bg: "linear-gradient(135deg, #112a45, #0a1628)"
    },

    // ===== CULTURE GÉNÉRALE MADAGASCAR =====
    {
        question: "Quelle est la capitale de Madagascar ?",
        correct: "Antananarivo",
        wrong: ["Antsirabe", "Toamasina"],
        icon: "🏙️",
        hint: "On l'appelle aussi Tana.",
        bg: "linear-gradient(135deg, #0e2845, #0a1628)"
    },
    {
        question: "Quel océan borde Madagascar ?",
        correct: "L'océan Indien",
        wrong: ["L'océan Atlantique", "L'océan Pacifique"],
        icon: "🌊",
        hint: "Madagascar est à l'est du continent africain.",
        bg: "linear-gradient(135deg, #14304d, #0a1628)"
    },
    {
        question: "Quelle est la monnaie officielle de Madagascar ?",
        correct: "L'Ariary",
        wrong: ["Le Franc CFA", "Le Dollar malgache"],
        icon: "💰",
        hint: "Elle a remplacé le Franc Malgache.",
        bg: "linear-gradient(135deg, #0f2847, #0a1628)"
    },
    {
        question: "Quelles sont les deux langues officielles de Madagascar ?",
        correct: "Malgache et Français",
        wrong: ["Malgache et Anglais", "Français et Swahili"],
        icon: "🗣️",
        hint: "L'une est la langue locale, l'autre un héritage colonial.",
        bg: "linear-gradient(135deg, #132d4b, #0a1628)"
    },
    {
        question: "Quel animal est emblématique de Madagascar ?",
        correct: "Le lémurien",
        wrong: ["Le lion", "L'éléphant"],
        icon: "🐒",
        hint: "C'est un primate qu'on ne trouve nulle part ailleurs.",
        bg: "linear-gradient(135deg, #0b2138, #0a1628)"
    },
    {
        question: "Quel arbre emblématique pousse à Madagascar ?",
        correct: "Le baobab",
        wrong: ["Le séquoia", "L'eucalyptus"],
        icon: "🌳",
        hint: "L'allée de ces arbres à Morondava est mondialement célèbre.",
        bg: "linear-gradient(135deg, #0d2640, #0a1628)"
    },
    {
        question: "Madagascar est la combientième plus grande île du monde ?",
        correct: "La 4ème",
        wrong: ["La 2ème", "La 7ème"],
        icon: "🏝️",
        hint: "Après le Groenland, la Nouvelle-Guinée et Bornéo.",
        bg: "linear-gradient(135deg, #102842, #0a1628)"
    },
    {
        question: "Quelle plante à épice Madagascar est-elle le premier producteur mondial ?",
        correct: "La vanille",
        wrong: ["Le poivre", "La cannelle"],
        icon: "🌿",
        hint: "C'est une gousse noire très parfumée.",
        bg: "linear-gradient(135deg, #0e2540, #0a1628)"
    },
    {
        question: "Quel canal sépare Madagascar de l'Afrique ?",
        correct: "Le canal du Mozambique",
        wrong: ["Le canal de Suez", "Le détroit de Gibraltar"],
        icon: "🌏",
        hint: "Il porte le nom d'un pays voisin d'Afrique de l'Est.",
        bg: "linear-gradient(135deg, #162a46, #0a1628)"
    },
    {
        question: "Quel est le plat traditionnel malgache consommé quotidiennement ?",
        correct: "Le riz (vary)",
        wrong: ["Le couscous", "Les pâtes"],
        icon: "🍚",
        hint: "Les Malgaches en consomment 3 fois par jour.",
        bg: "linear-gradient(135deg, #0f2d4e, #0a1628)"
    }
];

// ================================================================
// State
// ================================================================
let currentQuestion = 0;
let score = 0;
let totalAnswered = 0;
let transitioning = false;
let shuffledQuestions = [];
const QUIZ_LENGTH = 10; // Number of questions per round

// ================================================================
// Loading screen animation
// ================================================================
(function initLoading() {
    const title = "Quiz UAZ";
    const subtitle = "Connais-tu ton université ?";
    const titleEl = document.getElementById('loadingTitle');
    const subtitleEl = document.getElementById('loadingSubtitle');

    // Animate title letters
    title.split('').forEach((char, i) => {
        const span = document.createElement('span');
        span.textContent = char === ' ' ? '\u00A0' : char;
        span.style.animation = `bounceIn 0.6s ${0.1 + i * 0.08}s forwards`;
        titleEl.appendChild(span);
    });

    // Animate subtitle letters
    setTimeout(() => {
        subtitle.split('').forEach((char, i) => {
            const span = document.createElement('span');
            span.textContent = char === ' ' ? '\u00A0' : char;
            span.style.animation = `bounceIn 0.4s ${i * 0.03}s forwards`;
            subtitleEl.appendChild(span);
        });
    }, 800);
})();

// ================================================================
// Start Quiz
// ================================================================
function startQuiz() {
    document.getElementById('quizLoading').classList.add('loaded');

    // Shuffle and pick QUIZ_LENGTH questions
    shuffledQuestions = [...questions].sort(() => Math.random() - 0.5).slice(0, QUIZ_LENGTH);
    currentQuestion = 0;
    score = 0;
    totalAnswered = 0;

    setTimeout(() => {
        document.getElementById('quizContainer').style.display = '';
        document.getElementById('liveScore').style.display = '';

        // Build breadcrumbs
        const bc = document.getElementById('breadcrumbs');
        bc.innerHTML = '';
        for (let i = 0; i < shuffledQuestions.length; i++) {
            const dot = document.createElement('div');
            dot.className = 'breadcrumb-dot' + (i === 0 ? ' active' : '');
            bc.appendChild(dot);
        }

        initQuestion(0);
    }, 900);
}

// ================================================================
// Init Question
// ================================================================
function initQuestion(index) {
    const q = shuffledQuestions[index];

    // Question number
    document.getElementById('questionNumber').textContent = `Question ${index + 1} / ${shuffledQuestions.length}`;

    // Question text
    document.getElementById('questionText').textContent = q.question;

    // Scene icon
    document.getElementById('sceneIcon').textContent = q.icon;

    // Hint
    document.getElementById('hintText').style.display = 'none';
    document.getElementById('hintText').textContent = q.hint;

    // Background
    document.body.style.background = '#0a1628';

    // Build answers: shuffle correct + wrong
    const allAnswers = [q.correct, ...q.wrong].sort(() => Math.random() - 0.5);

    for (let i = 0; i < 3; i++) {
        const card = document.getElementById('answer' + i);
        card.querySelector('.answer-text').textContent = allAnswers[i];
        card.className = 'answer-card';
        card.style.left = '0';
        card.style.opacity = '1';
        card.dataset.correct = allAnswers[i] === q.correct ? 'true' : 'false';
    }

    // Update live score
    document.getElementById('scoreDisplay').textContent = score;
    document.getElementById('totalDisplay').textContent = totalAnswered;
}

// ================================================================
// Check Answer
// ================================================================
function handleAnswer(answerIndex) {
    if (transitioning) return;
    transitioning = true;

    const card = document.getElementById('answer' + answerIndex);
    const isCorrect = card.dataset.correct === 'true';
    const feedback = document.getElementById('feedback');
    const dots = document.querySelectorAll('.breadcrumb-dot');

    if (isCorrect) {
        score++;
        card.classList.add('correct');
        feedback.textContent = 'Correct !';
        feedback.className = 'quiz-feedback correct show';
        dots[currentQuestion].classList.add('correct');
    } else {
        card.classList.add('wrong');
        // Highlight the correct one
        for (let i = 0; i < 3; i++) {
            if (document.getElementById('answer' + i).dataset.correct === 'true') {
                document.getElementById('answer' + i).classList.add('correct');
            }
        }
        feedback.textContent = 'Raté !';
        feedback.className = 'quiz-feedback wrong show';
        dots[currentQuestion].classList.add('wrong');
    }

    totalAnswered++;
    dots[currentQuestion].classList.remove('active');

    currentQuestion++;

    // Check if quiz is over
    if (currentQuestion >= shuffledQuestions.length) {
        setTimeout(() => showEndScreen(), 1500);
        setTimeout(() => {
            feedback.className = 'quiz-feedback';
        }, 1000);
        return;
    }

    // Move to next
    if (dots[currentQuestion]) dots[currentQuestion].classList.add('active');

    // Transition: slide answers out
    setTimeout(() => {
        for (let i = 0; i < 3; i++) {
            const c = document.getElementById('answer' + i);
            c.style.left = '80px';
            c.style.opacity = '0';
        }
    }, 400);

    // Transition: circle scale
    const circle = document.getElementById('quizCircle');
    setTimeout(() => {
        circle.style.transform = 'translate(-50%, -50%) scale(8)';
        feedback.className = 'quiz-feedback';
    }, 600);

    // Reset and load next
    setTimeout(() => {
        circle.style.transition = 'none';
        circle.style.transform = 'translate(-50%, -50%) scale(0)';

        setTimeout(() => {
            circle.style.transition = 'all 0.6s cubic-bezier(1, 0, 0.2, 0.99)';
            circle.style.transform = 'translate(-50%, -50%) scale(1)';

            for (let i = 0; i < 3; i++) {
                const c = document.getElementById('answer' + i);
                c.style.left = '0';
                c.style.opacity = '1';
            }

            initQuestion(currentQuestion);
            transitioning = false;
        }, 100);
    }, 1000);
}

// ================================================================
// End Screen
// ================================================================
function showEndScreen() {
    const pct = Math.round((score / shuffledQuestions.length) * 100);
    let emoji, title, message;

    if (pct === 100) {
        emoji = '🏆'; title = 'Parfait !';
        message = `Incroyable ${<?= json_encode(trim($studentName)) ?>} ! Tu connais l'UAZ sur le bout des doigts. Un vrai Zurchérien !`;
    } else if (pct >= 80) {
        emoji = '🌟'; title = 'Excellent !';
        message = `Bravo ! Tu as une très bonne connaissance de l'UAZ. Continue comme ça !`;
    } else if (pct >= 60) {
        emoji = '👏'; title = 'Bien joué !';
        message = `Pas mal du tout ! Tu connais déjà bien ton université. Encore un peu d'effort !`;
    } else if (pct >= 40) {
        emoji = '🤔'; title = 'Pas mal...';
        message = `Tu as encore des choses à découvrir sur l'UAZ. N'hésite pas à explorer le campus !`;
    } else {
        emoji = '📖'; title = 'À revoir !';
        message = `Il est temps de mieux connaître ton université ! Repasse le quiz pour t'améliorer.`;
    }

    document.getElementById('resultEmoji').textContent = emoji;
    document.getElementById('resultTitle').textContent = title;
    document.getElementById('finalScore').textContent = `${score} / ${shuffledQuestions.length} (${pct}%)`;
    document.getElementById('finalMessage').textContent = message;
    document.getElementById('endModal').classList.add('show');
}

// ================================================================
// Restart
// ================================================================
function restartQuiz() {
    document.getElementById('endModal').classList.remove('show');
    transitioning = false;

    shuffledQuestions = [...questions].sort(() => Math.random() - 0.5).slice(0, QUIZ_LENGTH);
    currentQuestion = 0;
    score = 0;
    totalAnswered = 0;

    // Rebuild breadcrumbs
    const bc = document.getElementById('breadcrumbs');
    bc.innerHTML = '';
    for (let i = 0; i < shuffledQuestions.length; i++) {
        const dot = document.createElement('div');
        dot.className = 'breadcrumb-dot' + (i === 0 ? ' active' : '');
        bc.appendChild(dot);
    }

    initQuestion(0);
}

// ================================================================
// Event Listeners
// ================================================================

// Click answers
document.querySelectorAll('.answer-card').forEach((card, i) => {
    card.addEventListener('click', () => handleAnswer(i));
});

// Keyboard 1/2/3
document.addEventListener('keypress', (e) => {
    if (e.key === '1') handleAnswer(0);
    if (e.key === '2') handleAnswer(1);
    if (e.key === '3') handleAnswer(2);
});

// Hint button
document.getElementById('hintBtn').addEventListener('click', () => {
    const hint = document.getElementById('hintText');
    hint.style.display = hint.style.display === 'none' ? 'block' : 'none';
});

// ================================================================
// Custom Cursor
// ================================================================
const cursor = document.getElementById('customCursor');
document.addEventListener('mousemove', (e) => {
    cursor.style.left = e.clientX + 'px';
    cursor.style.top = e.clientY + 'px';
});

document.querySelectorAll('a, button, .answer-card, .std-nav-icon').forEach(el => {
    el.addEventListener('mouseenter', () => {
        cursor.style.width = '55px';
        cursor.style.height = '55px';
        cursor.style.borderColor = 'rgba(14, 165, 233, 0.8)';
    });
    el.addEventListener('mouseleave', () => {
        cursor.style.width = '40px';
        cursor.style.height = '40px';
        cursor.style.borderColor = 'white';
    });
});

// Hide cursor on touch devices
if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
    cursor.style.display = 'none';
    document.body.style.cursor = 'auto';
}

// ================================================================
// Grain Canvas
// ================================================================
class Grain {
    constructor(el) {
        this.patternSize = 150;
        this.patternAlpha = 10;
        this.patternRefreshInterval = 4;
        this.canvas = el;
        this.ctx = this.canvas.getContext('2d');
        this.patternCanvas = document.createElement('canvas');
        this.patternCanvas.width = this.patternSize;
        this.patternCanvas.height = this.patternSize;
        this.patternCtx = this.patternCanvas.getContext('2d');
        this.patternData = this.patternCtx.createImageData(this.patternSize, this.patternSize);
        this.patternPixelDataLength = this.patternSize * this.patternSize * 4;
        this.frame = 0;
        this.resize = this.resize.bind(this);
        this.loop = this.loop.bind(this);
        window.addEventListener('resize', this.resize);
        this.resize();
        requestAnimationFrame(this.loop);
    }
    resize() {
        this.canvas.width = window.innerWidth * devicePixelRatio;
        this.canvas.height = window.innerHeight * devicePixelRatio;
    }
    update() {
        for (let i = 0; i < this.patternPixelDataLength; i += 4) {
            const v = Math.random() * 255;
            this.patternData.data[i] = v;
            this.patternData.data[i + 1] = v;
            this.patternData.data[i + 2] = v;
            this.patternData.data[i + 3] = this.patternAlpha;
        }
        this.patternCtx.putImageData(this.patternData, 0, 0);
    }
    draw() {
        const { ctx, patternCanvas, canvas } = this;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = ctx.createPattern(patternCanvas, 'repeat');
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    loop() {
        if (++this.frame % this.patternRefreshInterval === 0) {
            this.update();
            this.draw();
        }
        requestAnimationFrame(this.loop);
    }
}

new Grain(document.querySelector('.grain'));
</script>

<?php include('./student.transition.php'); ?>
</body>
</html>
