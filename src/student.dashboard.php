<!DOCTYPE html>
<html>
<?php require('../init/head.php');?>
    <title>Mes Notes - Espace Étudiant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== BASE ===== */
        body {
            font-family: "Inter", sans-serif;
            background: rgb(var(--c-surface));
            color: rgb(var(--c-text));
            animation: fadeIn 0.6s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ===== TOP BAR ===== */
        .std-topbar {
            background: rgb(var(--c-canvas) / 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgb(var(--c-accent) / 0.08);
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
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
            color: rgb(var(--c-text));
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
            background: rgb(var(--c-accent) / 0.1);
            border-color: rgb(var(--c-accent) / 0.2);
            color: rgb(var(--c-accent-text));
        }
        .std-nav-icon.home:hover {
            background: rgb(var(--c-accent) / 0.22);
            border-color: rgb(var(--c-accent) / 0.4);
            color: rgb(var(--c-accent-text));
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
            background: rgb(var(--c-canvas) / 0.95);
            color: rgb(var(--c-text-2));
            font-size: 0.68rem;
            font-family: "Inter", sans-serif;
            padding: 3px 8px;
            border-radius: 6px;
            white-space: nowrap;
            border: 1px solid rgb(var(--c-accent) / 0.15);
            pointer-events: none;
            z-index: 200;
        }

        /* ===== CONTENT AREA ===== */
        .std-content {
            height: calc(100vh - 56px);
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1.5rem;
        }
        .std-content::-webkit-scrollbar { width: 4px; }
        .std-content::-webkit-scrollbar-track { background: transparent; }
        .std-content::-webkit-scrollbar-thumb { 
            background: rgb(var(--c-accent) / 0.2); 
            border-radius: 10px; 
        }
        .std-content::-webkit-scrollbar-thumb:hover { 
            background: rgb(var(--c-accent) / 0.4); 
        }

        /* ===== CARDS ===== */
        .std-card {
            background: rgb(var(--c-surface) / 0.6);
            border: 1px solid rgb(var(--c-accent) / 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: border-color 0.3s ease;
        }
        .std-card:hover {
            border-color: rgb(var(--c-accent) / 0.15);
        }
        .std-card-title {
            font-family: "Syne", sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: rgb(var(--c-text));
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .std-card-title i {
            color: rgb(var(--c-accent-text));
            font-size: 1.1rem;
        }

        /* ===== STUDENT PROFILE CARD ===== */
        .std-profile {
            display: flex;
            flex-direction: row;
            gap: 1.5rem;
            align-items: flex-start;
        }
        .std-profile-photo {
            width: 110px;
            height: 110px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid rgb(var(--c-accent) / 0.2);
            flex-shrink: 0;
        }
        .std-profile-placeholder {
            width: 110px;
            height: 110px;
            border-radius: 14px;
            background: rgb(var(--c-accent) / 0.08);
            border: 2px solid rgb(var(--c-accent) / 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .std-profile-placeholder i {
            font-size: 2.5rem;
            color: rgb(var(--c-text-3));
        }
        .std-profile-name {
            font-family: "Syne", sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: rgb(var(--c-text));
            margin-bottom: 0.75rem;
            letter-spacing: -0.01em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .std-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
        }
        .std-info-item label {
            display: block;
            font-size: 0.7rem;
            color: rgb(var(--c-text-3));
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 500;
            margin-bottom: 0.15rem;
        }
        .std-info-item span {
            font-size: 0.85rem;
            color: rgb(var(--c-text));
            font-weight: 600;
        }

        /* ===== AVERAGE BADGE ===== */
        .std-avg-badge {
            background: rgb(var(--c-accent) / 0.06);
            border: 1px solid rgb(var(--c-accent) / 0.15);
            border-radius: 14px;
            padding: 1rem 1.5rem;
            text-align: center;
            flex-shrink: 0;
            min-width: 120px;
        }
        .std-avg-label {
            font-size: 0.68rem;
            color: rgb(var(--c-text-3));
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .std-avg-value {
            font-family: "Syne", sans-serif;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .std-avg-value.success { color: #34d399; }
        .std-avg-value.danger { color: #f87171; }
        .std-avg-credits {
            font-size: 0.7rem;
            color: rgb(var(--c-text-3));
            margin-top: 0.15rem;
        }

        /* ===== SESSION TABLE BLOCKS ===== */
        .std-session-block {
            background: rgb(var(--c-surface) / 0.5);
            border: 1px solid rgb(var(--c-accent) / 0.06);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1rem;
            transition: border-color 0.3s ease;
        }
        .std-table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .std-table-scroll::-webkit-scrollbar { height: 4px; }
        .std-table-scroll::-webkit-scrollbar-track { background: transparent; }
        .std-table-scroll::-webkit-scrollbar-thumb { background: rgb(var(--c-accent) / 0.2); border-radius: 10px; }
        .std-table-scroll::-webkit-scrollbar-thumb:hover { background: rgb(var(--c-accent) / 0.4); }
        .std-session-block:hover {
            border-color: rgb(var(--c-accent) / 0.15);
        }
        .std-session-header {
            background: linear-gradient(135deg, rgb(var(--c-accent) / 0.15), rgb(var(--c-accent) / 0.08));
            padding: 0.65rem 1rem;
            font-family: "Syne", sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            color: rgb(var(--c-accent-text));
            letter-spacing: 0.02em;
            border-bottom: 1px solid rgb(var(--c-accent) / 0.1);
        }
        .std-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .std-table thead th {
            padding: 0.55rem 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgb(var(--c-text-3));
            background: rgb(var(--c-canvas) / 0.5);
            border-bottom: 1px solid rgb(var(--c-accent) / 0.06);
        }
        .std-table thead th.text-center { text-align: center; }
        .std-table tbody td {
            padding: 0.5rem 0.75rem;
            color: rgb(var(--c-text));
            border-bottom: 1px solid rgb(var(--c-line) / 0.4);
            vertical-align: middle;
        }
        .std-table tbody tr {
            transition: background 0.2s ease;
        }
        .std-table tbody tr:hover {
            background: rgb(var(--c-accent) / 0.04);
        }
        .std-table tbody tr:last-child td {
            border-bottom: none;
        }
        .std-sigle {
            background: linear-gradient(135deg, rgb(var(--c-accent) / 0.2), rgb(var(--c-accent) / 0.12));
            color: rgb(var(--c-accent-text));
            font-weight: 600;
            border-radius: 0;
            font-size: 0.75rem;
        }
        .std-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .std-badge-success {
            background: rgba(52, 211, 153, 0.15);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }
        .std-badge-danger {
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.2);
        }

        /* ===== TABLE FOOTER (TOTALS) ===== */
        .std-table tfoot th,
        .std-table tfoot td {
            padding: 0.5rem 0.75rem;
            font-size: 0.78rem;
            color: rgb(var(--c-text-2));
            background: rgb(var(--c-canvas) / 0.4);
            border-bottom: 1px solid rgb(var(--c-line) / 0.3);
        }
        .std-table tfoot .std-promo-row td {
            background: rgb(var(--c-surface) / 0.3);
            font-size: 0.76rem;
            color: rgb(var(--c-text-3));
        }
        .std-avg-cell {
            background: rgb(var(--c-accent) / 0.12) !important;
            color: rgb(var(--c-accent-text)) !important;
            font-weight: 700;
            font-family: "Syne", sans-serif;
        }

        /* ===== CUMULATIVE BLOCK ===== */
        .std-cumul-block {
            background: rgb(var(--c-accent) / 0.04);
            border: 1px solid rgb(var(--c-accent) / 0.12);
            border-radius: 12px;
            overflow: hidden;
        }
        .std-cumul-title {
            font-family: "Syne", sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: rgb(var(--c-text));
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgb(var(--c-accent) / 0.08);
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .std-cumul-title i { color: rgb(var(--c-accent-text)); }
        .std-cumul-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 1rem;
            border-bottom: 1px solid rgb(var(--c-line) / 0.25);
            font-size: 0.8rem;
        }
        .std-cumul-row:last-child { border-bottom: none; }
        .std-cumul-row .label { color: rgb(var(--c-text-3)); }
        .std-cumul-row .value { 
            font-weight: 700; 
            color: rgb(var(--c-text));
            font-family: "Syne", sans-serif;
        }
        .std-cumul-highlight {
            background: linear-gradient(135deg, rgb(var(--c-accent) / 0.12), rgb(var(--c-accent) / 0.06));
        }
        .std-cumul-highlight .value {
            color: rgb(var(--c-accent-text));
            font-size: 1rem;
        }

        /* ===== INFO SECTION ===== */
        .std-info-section p {
            font-size: 0.82rem;
            color: rgb(var(--c-text-3));
            line-height: 1.8;
        }
        .std-info-section p::before {
            content: "";
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgb(var(--c-accent));
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .std-profile { flex-direction: column; align-items: center; text-align: center; }
            .std-info-grid { grid-template-columns: 1fr 1fr; }
            .std-content { padding: 1rem; }
            .std-card { padding: 1rem; }
            .std-table { font-size: 0.72rem; }
            .std-topbar { height: 50px; }
            .std-topbar .logo-img { width: 28px; height: 28px; }
            .std-topbar .page-title { font-size: 0.78rem; }
            .std-nav-icon { width: 34px; height: 34px; font-size: 1rem; }
            .std-content { height: calc(100vh - 50px); }
            .std-session-header { font-size: 0.72rem; padding: 0.6rem 0.8rem; }
            .std-table th, .std-table td { padding: 0.4rem 0.5rem; }
            .std-table { min-width: 580px; }
            .std-profile-name { 
                font-size: 1.15rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 100%;
            }
        }
        @media (max-width: 480px) {
            .std-info-grid { grid-template-columns: 1fr; }
            .std-topbar { height: 46px; padding: 0 0.75rem; }
            .std-topbar .logo-img { width: 26px; height: 26px; }
            .std-topbar .page-title { font-size: 0.74rem; }
            .std-nav-icon { width: 32px; height: 32px; font-size: 0.95rem; border-radius: 8px; }
            .std-content { height: calc(100vh - 46px); padding: 0.75rem; }
            .std-card { border-radius: 12px; padding: 0.8rem; }
            .std-profile-photo { width: 70px; height: 70px; }
            .std-profile-placeholder { width: 70px; height: 70px; font-size: 1.8rem; }
            .std-profile-name { font-size: 1rem; }
            .std-avg-badge { padding: 0.8rem; min-width: unset; }
            .std-table { font-size: 0.68rem; min-width: 560px; }
            .std-table th, .std-table td { padding: 0.3rem 0.4rem; }
            .std-cumul-row { padding: 0.5rem 0.6rem; font-size: 0.75rem; }
            .std-profile-name { font-size: 1rem; }
        }

        /* Telephone : le tableau a six colonnes ne tient pas, et la note
           sortait a droite, derriere un defilement que rien ne signalait.
           Chaque cours devient deux lignes : le titre et la note, puis le
           sigle, les credits, la categorie et l'etat. Les totaux passent en
           « libelle ... valeur ». */
        @media (max-width: 600px) {
            .std-table { min-width: 0; font-size: 0.8rem; }
            .std-table thead { display: none; }
            .std-table, .std-table tbody, .std-table tfoot { display: block; }
            .std-table tbody tr {
                display: grid;
                grid-template-columns: auto auto 1fr auto;
                grid-template-areas: "titre titre titre note" "sigle cred cat etat";
                align-items: center;
                column-gap: 0.6rem;
                row-gap: 0.3rem;
                padding: 0.65rem 0.8rem;
                border-bottom: 1px solid rgb(var(--c-line) / 0.4);
            }
            .std-table tbody tr:last-child { border-bottom: none; }
            .std-table tbody td { padding: 0; border: 0; text-align: left !important; }
            .std-table tbody td:nth-child(1) {
                grid-area: sigle;
                justify-self: start;
                padding: 0.05rem 0.4rem;
                border-radius: 4px;
                font-size: 0.7rem;
                white-space: nowrap;
            }
            .std-table tbody td:nth-child(2) { grid-area: titre; font-weight: 600; line-height: 1.35; }
            .std-table tbody td:nth-child(3) { grid-area: cred; color: rgb(var(--c-text-3)); white-space: nowrap; }
            .std-table tbody td:nth-child(3)::after { content: " cr."; }
            .std-table tbody td:nth-child(4) { grid-area: cat; color: rgb(var(--c-text-3)); }
            .std-table tbody td:nth-child(5) {
                grid-area: note;
                justify-self: end;
                font-size: 1rem;
                font-weight: 700;
                white-space: nowrap;
            }
            .std-table tbody td:nth-child(6) { grid-area: etat; justify-self: end; }
            .std-table tfoot tr {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem 0.8rem;
                background: rgb(var(--c-canvas) / 0.4);
                border-bottom: 1px solid rgb(var(--c-line) / 0.3);
            }
            .std-table tfoot tr.std-promo-row { background: rgb(var(--c-surface) / 0.3); }
            .std-table tfoot th,
            .std-table tfoot td { padding: 0; border: 0; background: none; text-align: left !important; }
            .std-table tfoot th:empty,
            .std-table tfoot td:empty { display: none; }
            .std-table tfoot .std-avg-cell { padding: 0.15rem 0.5rem; border-radius: 6px; }
        }
    </style>
</head>
<body class="sm:text-xs lg:text-sm">
<?php
// Middleware déjà chargé par head.php — pas besoin de re-require
// Autoriser l'accès aux admins, registrars ET étudiants
if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/login');
    exit;
}

// Récupérer les informations de l'étudiant
$studentInfo = getStudentInfo();

if (!$studentInfo) {
    if (isStudent()) {
        echo '<div style="padding:2rem;text-align:center;color:#fca5a5;font-family:Inter,sans-serif;">';
        echo '<div style="font-size:2.5rem;margin-bottom:1rem;">⚠️</div>';
        echo '<h2 style="color:rgb(var(--c-text));margin-bottom:0.5rem;">Compte non lié</h2>';
        echo '<p>Votre compte utilisateur n\'est pas encore lié à un profil étudiant (matricule).</p>';
        echo '<p style="color:rgb(var(--c-text-3));margin-top:0.5rem;">Veuillez contacter le bureau du registraire pour lier votre compte.</p>';
        echo '<a href="' . $app_base . '/student/home" style="display:inline-block;margin-top:1rem;padding:8px 20px;background:rgb(var(--c-accent) / 0.15);border:1px solid rgb(var(--c-accent) / 0.3);border-radius:8px;color:rgb(var(--c-accent-text));text-decoration:none;">← Retour à l\'accueil</a>';
        echo '</div>';
    } else {
        echo '<div style="padding:2rem;text-align:center;color:#fca5a5;">Aucun profil étudiant à afficher.</div>';
    }
    echo '</body></html>';
    exit;
}

// Récupérer l'année scolaire courante
$currentYear = $studentInfo['annee_scolaire'] ?? (date('m') >= 7 ? date('Y').' - '.(date('Y')+1) : (date('Y')-1).' - '.date('Y'));

// Récupérer le semestre actuel
$currentSemester = date('m') >= 7 ? 1 : 2;

// Récupérer l'ID de l'étudiant
$student_id = $studentInfo['student_id'] ?? '';
$yes = 1;

// Initialiser les variables par défaut
$allSessions = null;
$totalCredits = 0;
$totalPoints = 0;
$moyenne = null;
$dbError = false;

// Publication des notes par session (data/espace.etudiant.php) : un compte
// étudiant ne voit que les sessions publiées, et sa moyenne ne compte qu'elles.
// Le personnel voit tout.
require_once __DIR__ . '/../data/espace.etudiant.php';
$vueEtudiant = isStudent();
$voirPromotion = true;
$sessionsEnAttente = [];

try {
    espaceEtudiantInstaller($dtb);
    if ($vueEtudiant) {
        $voirPromotion = espaceEtudiantRubriqueVisible($dtb, 'notes_promotion');
    }

    // Vérifier si les tables de notes existent
    $tableCheck = $dtb->query("SHOW TABLES LIKE 't_2023_notes'");
    if ($tableCheck->rowCount() === 0) {
        $dbError = 'tables_missing';
    } else {
        // Récupérer toutes les sessions distinctes où l'étudiant a des notes
        $stmtSessions = $dtb->prepare("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
            FROM t_2023_notes n 
            INNER JOIN t_2023_session s ON n.session_id = s.session_id 
            WHERE n.student_id = :sid AND n.ajout = :ajout
              AND (:tout = 1 OR n.session_id IN (SELECT session_id FROM t_notes_publication WHERE publiee = 1)) 
            ORDER BY s.session_year ASC, s.session_semester ASC");
        $stmtSessions->execute(['sid' => $student_id, 'ajout' => $yes, 'tout' => $vueEtudiant ? 0 : 1]);
        $allSessions = $stmtSessions;

        // Moyenne globale = GPA pondéré par les crédits : somme(crédit × note) / somme(crédits).
        // Exclus : notes non chiffrées (0, -1 hérité, -2 validé, -3 échec, -4 NN)
        // et cours de validation (catégorie 5, RELP 291, formation spirituelle),
        // exactement comme level.php, transcriptSS et bulletin.
        $stmtStats = $dtb->prepare("SELECT SUM(credit) as total_credits, SUM(credit * grade) as total_points
            FROM t_2023_notes
            WHERE student_id = :sid AND ajout = 1 AND grade > 0
              AND (:tout = 1 OR session_id IN (SELECT session_id FROM t_notes_publication WHERE publiee = 1))
              AND cours_category <> 5
              AND Sigle NOT LIKE '%RELP 291%'
              AND title_cours NOT LIKE '%formation spirituelle%'");
        $stmtStats->execute(['sid' => $student_id, 'tout' => $vueEtudiant ? 0 : 1]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        $totalCredits = $stats['total_credits'] ?? 0;
        $totalPoints = $stats['total_points'] ?? 0;
        $moyenne = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : null;

        // Sessions dont l'étudiant a des notes encore masquées : on le lui dit,
        // plutôt que de laisser croire qu'elles n'existent pas.
        if ($vueEtudiant) {
            $stmtAttente = $dtb->prepare("SELECT DISTINCT s.session_name, s.session_year, s.session_semester
                FROM t_2023_notes n
                INNER JOIN t_2023_session s ON n.session_id = s.session_id
                WHERE n.student_id = :sid AND n.ajout = 1
                  AND n.session_id NOT IN (SELECT session_id FROM t_notes_publication WHERE publiee = 1)
                ORDER BY s.session_year ASC, s.session_semester ASC");
            $stmtAttente->execute(['sid' => $student_id]);
            $sessionsEnAttente = $stmtAttente->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>

<div class="h-screen w-full" style="background: rgb(var(--c-surface));">
    
    <!-- TOP BAR -->
    <div class="std-topbar w-full flex items-center px-4 justify-between">
        <div class="flex items-center gap-2">
            <img src="../file/UAZ Official.png" alt="UAZ" class="logo-img">
            <span class="page-title">Mes Notes</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?=$app_base?>/student/home" class="std-nav-icon home" data-tooltip="Accueil">
                <i class="bi bi-house-door-fill"></i>
            </a>
            <a href="<?=$app_base?>/logout" class="std-nav-icon logout" data-tooltip="Déconnexion">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="std-content">
        
        <!-- CARTE DE PROFIL -->
        <div class="std-card mb-5">
            <div class="std-profile">
                
                <!-- Photo -->
                <div>
                    <?php if (!empty($studentInfo['image_student'])): ?>
                        <img src="<?=$app_base?>/app/photosetudiants/<?= htmlspecialchars($studentInfo['image_student']) ?>" 
                             alt="Photo" class="std-profile-photo"
                             onerror="this.onerror=null;this.parentElement.innerHTML='<div class=std-profile-placeholder><i class=\'bi bi-person\'></i></div>';">
                    <?php else: ?>
                        <div class="std-profile-placeholder">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Informations -->
                <div class="flex-1">
                    <div class="std-profile-name">
                        <?= htmlspecialchars(strtoupper($studentInfo['student_nom']).' '.$studentInfo['student_prenom']) ?>
                    </div>
                    
                    <div class="std-info-grid">
                        <div class="std-info-item">
                            <label>Matricule</label>
                            <span><?= htmlspecialchars($studentInfo['student_id']) ?></span>
                        </div>
                        <div class="std-info-item">
                            <label>Mention</label>
                            <span><?= htmlspecialchars($studentInfo['etude_envisage']) ?></span>
                        </div>
                        <div class="std-info-item">
                            <label>Parcours</label>
                            <span><?= htmlspecialchars($studentInfo['etude_option'] ?? '-') ?></span>
                        </div>
                        <div class="std-info-item">
                            <label>Niveau</label>
                            <span><?php
                                $ae = intval($studentInfo['annee_etude'] ?? 1);
                                echo $ae <= 3 ? "Licence $ae" : "Master " . ($ae - 3);
                            ?></span>
                        </div>
                        <div class="std-info-item">
                            <label>Année académique</label>
                            <span><?= htmlspecialchars($currentYear) ?></span>
                        </div>
                        <div class="std-info-item">
                            <label>Email</label>
                            <span><?= htmlspecialchars($studentInfo['student_email'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Moyenne -->
                <?php if ($moyenne !== null): ?>
                <div class="std-avg-badge">
                    <div class="std-avg-label">Moyenne Pondérée</div>
                    <div class="std-avg-value <?= $moyenne >= 10 ? 'success' : 'danger' ?>">
                        <?= $moyenne ?>
                    </div>
                    <div class="std-avg-credits"><?= $totalCredits ?> crédits</div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <?php if (!empty($sessionsEnAttente)): ?>
        <!-- NOTES EN ATTENTE DE PUBLICATION -->
        <div class="std-card mb-5">
            <div class="std-card-title">
                <i class="bi bi-hourglass-split"></i>
                Notes en attente de validation
            </div>
            <div class="std-info-section">
                <p>Les notes de <?= htmlspecialchars(implode(', ', array_map(static fn ($s) => trim($s['session_name']) . ' ' . trim($s['session_year']), $sessionsEnAttente))) ?> ne sont pas encore publiées : elles attendent la validation officielle.</p>
                <p>Elles apparaîtront ici, et compteront dans votre moyenne, dès leur publication par le bureau du registraire.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- MES NOTES PAR SESSION -->
        <div class="std-card mb-5">
            <div class="std-card-title">
                <i class="bi bi-clipboard-data"></i>
                Mes Notes par Session
            </div>
            
            <div>
                <?php 
                $sessionCount = 0;
                $hasNotes = false;
                
                // Variables cumulatives
                $cumulWorkNote = 0;
                $cumulremarkAcad = 0;
                $cumulChapel = 0;
                $cumulGen = 0;
                $cumulMaj = 0;
                $cumulFinale = 0;
                // GPA pondere par les credits (formule officielle, systeme LMD)
                $gpaCredits = 0; $gpaNoteCredits = 0;
                $gpaCreditsMaj = 0; $gpaNoteCreditsMaj = 0;
                
                if ($dbError === 'tables_missing'): ?>
                    <div style="text-align:center; padding: 3rem 0;">
                        <i class="bi bi-database-exclamation" style="font-size: 2.5rem; color: #f59e0b; display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: #f59e0b; font-size: 0.9rem; font-weight:600;">Les tables de notes ne sont pas encore configurées</p>
                        <p style="color: rgb(var(--c-text-3)); font-size: 0.8rem; margin-top:0.5rem;">Les notes seront disponibles une fois les tables créées par l'administrateur.</p>
                    </div>
                <?php elseif ($dbError): ?>
                    <div style="text-align:center; padding: 3rem 0;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; color: #ef4444; display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: #fca5a5; font-size: 0.9rem;">Erreur de base de données</p>
                        <p style="color: rgb(var(--c-text-3)); font-size: 0.75rem; margin-top:0.5rem;"><?=htmlspecialchars($dbError)?></p>
                    </div>
                <?php elseif ($allSessions):
                while($showSs = $allSessions->fetch()):
                    $hasNotes = true;
                    $sessionCount++;
                    $session_id = $showSs['session_id'];
                    $combinAnual = $showSs['session_year'];
                    
                    // Récupérer le yearlevel depuis les notes de cette session
                    $stmtYL = $dtb->prepare("SELECT yearlevel FROM t_2023_notes WHERE student_id=:sid AND session_id=:ssid AND ajout=:ajout LIMIT 1");
                    $stmtYL->execute(['sid' => $student_id, 'ssid' => $session_id, 'ajout' => $yes]);
                    $ylData = $stmtYL->fetch();
                    $yearlevel = $ylData ? $ylData['yearlevel'] : 1;
                    
                    // Déterminer le niveau (Licence ou Master)
                    if ($yearlevel <= 3) {
                        $niveau_label = "Licence " . $yearlevel;
                    } else {
                        $niveau_label = "Master " . ($yearlevel - 3);
                    }
                    
                    // Récupérer les cours de cette session
                    $stmtCours = $dtb->prepare("SELECT * FROM t_2023_notes WHERE student_id=:sid AND ajout=:ajout AND session_id=:ssid ORDER BY title_cours");
                    $stmtCours->execute(['sid' => $student_id, 'ajout' => $yes, 'ssid' => $session_id]);
                    $cours = $stmtCours;
                    
                    if ($cours->rowCount() > 0):
                ?>
                <div class="std-session-block">
                    <div class="std-session-header">
                        <?=$niveau_label?> &nbsp;·&nbsp; <?=$showSs['session_name']?> — Session N°<?=$showSs['session_semester']?> &nbsp;·&nbsp; <?=$combinAnual?>
                    </div>
                    <div class="std-table-scroll">
                    <table class="std-table">
                        <thead>
                            <tr>
                                <th>Sigle</th>
                                <th>Titre du cours</th>
                                <th class="text-center" style="width:70px;">Crédits</th>
                                <th class="text-center" style="width:90px;">Catégorie</th>
                                <th class="text-center" style="width:70px;">Note/20</th>
                                <th class="text-center" style="width:50px;">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $nbr = 0;
                            $tcredit = 0;
                            $tnote = 0;
                            $tnotecredit = 0;
                            $nbrMaj = 0;
                            $tTMaj = 0;
                            $nbrFinale = 0;
                            $tTFinale = 0;
                            
                            $tcreditGPA = 0;      // crédits comptant dans la moyenne
                            $tcreditMaj = 0;
                            $tnotecreditMaj = 0;

                            while($crs = $cours->fetch()):
                                $nbr++;
                                $tcredit += $crs['credit'];

                                // Même règle que partout ailleurs : seule une note
                                // chiffrée > 0 sur un cours non-validation compte.
                                $gradeValue = (float)$crs['grade'];
                                $estValidation = ((int)$crs['cours_category'] === 5)
                                    || stripos((string)($crs['Sigle'] ?? ''), 'RELP 291') !== false
                                    || stripos((string)($crs['title_cours'] ?? ''), 'formation spirituelle') !== false;
                                $compteDansMoyenne = is_numeric($crs['grade']) && $gradeValue > 0 && !$estValidation;

                                if ($compteDansMoyenne) {
                                    $tcreditGPA  += $crs['credit'];
                                    $tnote       += $gradeValue;
                                    $tnotecredit += $crs['credit'] * $gradeValue;

                                    if ($crs['cours_category'] == 1 || $crs['cours_category'] == "Majeur") {
                                        $nbrMaj++;
                                        $tTMaj          += $gradeValue;
                                        $tcreditMaj     += $crs['credit'];
                                        $tnotecreditMaj += $crs['credit'] * $gradeValue;
                                    }
                                }
                                
                                if ($crs['cours_category'] == 1 || $crs['cours_category'] == "Majeur" || 
                                    $crs['cours_category'] == 0 || $crs['cours_category'] == "Général") {
                                    $nbrFinale++;
                                    $tTFinale += $compteDansMoyenne ? $gradeValue : 0;
                                }
                                
                                $categorie = match($crs['cours_category']) {
                                    0 => "Général",
                                    1 => "Majeur",
                                    -1, 2 => "Selective",
                                    3 => "Additionnel",
                                    default => "-"
                                };
                                
                                // -4 s'affiche NN, -2 V, -3 E (data/notes.statut.php)
                                require_once __DIR__ . '/../data/notes.statut.php';
                                $stNote = noteStatut($crs['grade'], $estValidation);
                                $etat = $stNote['code'];
                                $isSuccess = in_array($etat, ['S', 'V'], true);
                            ?>
                            <tr>
                                <td class="std-sigle"><?=$crs['Sigle']?></td>
                                <td><?=$crs['title_cours']?></td>
                                <td class="text-center"><?=$crs['credit']?></td>
                                <td class="text-center"><?=$categorie?></td>
                                <td class="text-center font-semibold"><?=$stNote['libelle']?></td>
                                <td class="text-center">
                                    <?php if ($etat): ?>
                                        <span class="std-badge <?= $isSuccess ? 'std-badge-success' : ($etat === 'NN' ? '' : 'std-badge-danger') ?>"><?=$etat?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:left;"><?=$nbr?> cours</th>
                                <th class="text-center"><?=$tcredit?> cr.</th>
                                <th></th>
                                <th class="text-center"><?=round($tnote, 2)?></th>
                                <th></th>
                            </tr>
                            <?php 
                            $showPromotion = null;
                            try {
                                $stmtPromo = $dtb->prepare('SELECT * FROM t_2023_promotion_notes WHERE student_id = :sid AND session_id = :ssid');
                                $stmtPromo->execute(['sid' => $student_id, 'ssid' => $session_id]);
                                $showPromotion = $stmtPromo->fetch();
                            } catch (PDOException $e) { /* table might not exist */ }
                            if ($showPromotion && $voirPromotion): 
                            ?>
                            <tr class="std-promo-row">
                                <td colspan="4" style="text-align:right;">Note de Work Education</td>
                                <td class="text-center"><?=$showPromotion['grade_work_educ'] ?: '-'?></td>
                                <td></td>
                            </tr>
                            <tr class="std-promo-row">
                                <td colspan="4" style="text-align:right;">Remarque académique</td>
                                <td class="text-center"><?=$showPromotion['grade_remark_acad'] ?: '-'?></td>
                                <td></td>
                            </tr>
                            <tr class="std-promo-row">
                                <td colspan="4" style="text-align:right;">Participation chapelle/prière</td>
                                <td class="text-center"><?=$showPromotion['grade_chapel_part'] ?: '-'?></td>
                                <td></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th colspan="4" style="text-align:right;">Moyenne Majeure</th>
                                <th class="text-center"><?php $moyenMajSem = $tcreditMaj > 0 ? round($tnotecreditMaj / $tcreditMaj, 2) : 0; echo $moyenMajSem ?: "--"; ?></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" style="text-align:right;">Moyenne Générale</th>
                                <th class="text-center std-avg-cell"><?php $moyenFinale = $tcreditGPA > 0 ? round($tnotecredit / $tcreditGPA, 2) : 0; echo $moyenFinale ?: "--"; ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
                <?php 
                    // Cumuler les notes de promotion
                    if ($showPromotion) {
                        $cumulWorkNote += $showPromotion['grade_work_educ'] ?: 0;
                        $cumulremarkAcad += $showPromotion['grade_remark_acad'] ?: 0;
                        $cumulChapel += $showPromotion['grade_chapel_part'] ?: 0;
                    }
                    
                    // Cumuls du GPA : credits et points, jamais des moyennes
                    $gpaCredits        += $tcreditGPA;
                    $gpaNoteCredits    += $tnotecredit;
                    $gpaCreditsMaj     += $tcreditMaj;
                    $gpaNoteCreditsMaj += $tnotecreditMaj;
                    $cumulMaj += $moyenMajSem;
                    $cumulFinale += $moyenFinale;
                    
                    endif;
                endwhile; 
                
                // Moyennes cumulatives
                if ($hasNotes && $sessionCount > 0): 
                ?>
                <div class="std-cumul-block mt-4">
                    <div class="std-cumul-title">
                        <i class="bi bi-bar-chart-line"></i>
                        Moyenne Cumulative
                    </div>
                    <?php if ($voirPromotion): ?>
                    <div class="std-cumul-row">
                        <span class="label">Note de Work Education cumulative</span>
                        <span class="value"><?=round($cumulWorkNote / $sessionCount, 2)?></span>
                    </div>
                    <div class="std-cumul-row">
                        <span class="label">Participation chapelle & prière cumulative</span>
                        <span class="value"><?=round($cumulChapel / $sessionCount, 2)?></span>
                    </div>
                    <?php endif; ?>
                    <div class="std-cumul-row">
                        <span class="label">Moyenne Majeure Cumulative</span>
                        <span class="value"><?= $gpaCreditsMaj > 0 ? round($gpaNoteCreditsMaj / $gpaCreditsMaj, 2) : "--" ?></span>
                    </div>
                    <div class="std-cumul-row std-cumul-highlight">
                        <span class="label" style="color: rgb(var(--c-accent-text)); font-weight: 600;">Moyenne Cumulative</span>
                        <span class="value"><?= $gpaCredits > 0 ? round($gpaNoteCredits / $gpaCredits, 2) : "--" ?></span>
                    </div>
                </div>
                <?php endif;
                endif; // end elseif ($allSessions)
                
                if (!$hasNotes && !$dbError): 
                ?>
                    <div style="text-align:center; padding: 3rem 0;">
                        <i class="bi bi-inbox" style="font-size: 2.5rem; color: rgb(var(--c-text-3)); display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: rgb(var(--c-text-3)); font-size: 0.9rem;">Aucune note disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- INFORMATIONS -->
        <div class="std-card mb-5">
            <div class="std-card-title">
                <i class="bi bi-info-circle"></i>
                Informations
            </div>
            <div class="std-info-section">
                <p>Cette page affiche uniquement vos informations personnelles et académiques.</p>
                <p>Les notes d'une session apparaissent ici une fois validées et publiées par le bureau du registraire.</p>
                <p>Pour toute question, veuillez contacter le bureau du registraire.</p>
            </div>
        </div>

    </div>
</div>

<?php include('./student.transition.php'); ?>
</body>
</html>
