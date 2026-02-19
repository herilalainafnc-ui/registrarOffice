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
            background: #0a1628;
            color: #e8f1f8;
            animation: fadeIn 0.6s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ===== TOP BAR ===== */
        .std-topbar {
            background: rgba(10, 22, 40, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(14, 165, 233, 0.08);
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
            background: rgba(14, 165, 233, 0.2); 
            border-radius: 10px; 
        }
        .std-content::-webkit-scrollbar-thumb:hover { 
            background: rgba(14, 165, 233, 0.4); 
        }

        /* ===== CARDS ===== */
        .std-card {
            background: rgba(13, 31, 60, 0.6);
            border: 1px solid rgba(14, 165, 233, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: border-color 0.3s ease;
        }
        .std-card:hover {
            border-color: rgba(14, 165, 233, 0.15);
        }
        .std-card-title {
            font-family: "Syne", sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: #e8f1f8;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .std-card-title i {
            color: #0ea5e9;
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
            border: 2px solid rgba(14, 165, 233, 0.2);
            flex-shrink: 0;
        }
        .std-profile-placeholder {
            width: 110px;
            height: 110px;
            border-radius: 14px;
            background: rgba(14, 165, 233, 0.08);
            border: 2px solid rgba(14, 165, 233, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .std-profile-placeholder i {
            font-size: 2.5rem;
            color: #3d6a8a;
        }
        .std-profile-name {
            font-family: "Syne", sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #e8f1f8;
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
            color: #5a8aa8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 500;
            margin-bottom: 0.15rem;
        }
        .std-info-item span {
            font-size: 0.85rem;
            color: #e8f1f8;
            font-weight: 600;
        }

        /* ===== AVERAGE BADGE ===== */
        .std-avg-badge {
            background: rgba(14, 165, 233, 0.06);
            border: 1px solid rgba(14, 165, 233, 0.15);
            border-radius: 14px;
            padding: 1rem 1.5rem;
            text-align: center;
            flex-shrink: 0;
            min-width: 120px;
        }
        .std-avg-label {
            font-size: 0.68rem;
            color: #5a8aa8;
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
            color: #5a8aa8;
            margin-top: 0.15rem;
        }

        /* ===== SESSION TABLE BLOCKS ===== */
        .std-session-block {
            background: rgba(15, 40, 71, 0.5);
            border: 1px solid rgba(14, 165, 233, 0.06);
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
        .std-table-scroll::-webkit-scrollbar-thumb { background: rgba(14, 165, 233, 0.2); border-radius: 10px; }
        .std-table-scroll::-webkit-scrollbar-thumb:hover { background: rgba(14, 165, 233, 0.4); }
        .std-session-block:hover {
            border-color: rgba(14, 165, 233, 0.15);
        }
        .std-session-header {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(6, 182, 212, 0.08));
            padding: 0.65rem 1rem;
            font-family: "Syne", sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            color: #7dd3fc;
            letter-spacing: 0.02em;
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
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
            color: #5a8aa8;
            background: rgba(10, 22, 40, 0.5);
            border-bottom: 1px solid rgba(14, 165, 233, 0.06);
        }
        .std-table thead th.text-center { text-align: center; }
        .std-table tbody td {
            padding: 0.5rem 0.75rem;
            color: #e8f1f8;
            border-bottom: 1px solid rgba(26, 58, 92, 0.4);
            vertical-align: middle;
        }
        .std-table tbody tr {
            transition: background 0.2s ease;
        }
        .std-table tbody tr:hover {
            background: rgba(14, 165, 233, 0.04);
        }
        .std-table tbody tr:last-child td {
            border-bottom: none;
        }
        .std-sigle {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(6, 182, 212, 0.12));
            color: #7dd3fc;
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
            color: #8eb8d4;
            background: rgba(10, 22, 40, 0.4);
            border-bottom: 1px solid rgba(26, 58, 92, 0.3);
        }
        .std-table tfoot .std-promo-row td {
            background: rgba(15, 40, 71, 0.3);
            font-size: 0.76rem;
            color: #5a8aa8;
        }
        .std-avg-cell {
            background: rgba(14, 165, 233, 0.12) !important;
            color: #38bdf8 !important;
            font-weight: 700;
            font-family: "Syne", sans-serif;
        }

        /* ===== CUMULATIVE BLOCK ===== */
        .std-cumul-block {
            background: rgba(14, 165, 233, 0.04);
            border: 1px solid rgba(14, 165, 233, 0.12);
            border-radius: 12px;
            overflow: hidden;
        }
        .std-cumul-title {
            font-family: "Syne", sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: #e8f1f8;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(14, 165, 233, 0.08);
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .std-cumul-title i { color: #0ea5e9; }
        .std-cumul-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 1rem;
            border-bottom: 1px solid rgba(26, 58, 92, 0.25);
            font-size: 0.8rem;
        }
        .std-cumul-row:last-child { border-bottom: none; }
        .std-cumul-row .label { color: #5a8aa8; }
        .std-cumul-row .value { 
            font-weight: 700; 
            color: #e8f1f8;
            font-family: "Syne", sans-serif;
        }
        .std-cumul-highlight {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.12), rgba(6, 182, 212, 0.06));
        }
        .std-cumul-highlight .value {
            color: #38bdf8;
            font-size: 1rem;
        }

        /* ===== INFO SECTION ===== */
        .std-info-section p {
            font-size: 0.82rem;
            color: #5a8aa8;
            line-height: 1.8;
        }
        .std-info-section p::before {
            content: "";
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #0ea5e9;
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
        echo '<h2 style="color:#e8f1f8;margin-bottom:0.5rem;">Compte non lié</h2>';
        echo '<p>Votre compte utilisateur n\'est pas encore lié à un profil étudiant (matricule).</p>';
        echo '<p style="color:#5a8aa8;margin-top:0.5rem;">Veuillez contacter le bureau du registraire pour lier votre compte.</p>';
        echo '<a href="' . $app_base . '/student/home" style="display:inline-block;margin-top:1rem;padding:8px 20px;background:rgba(14,165,233,0.15);border:1px solid rgba(14,165,233,0.3);border-radius:8px;color:#38bdf8;text-decoration:none;">← Retour à l\'accueil</a>';
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

try {
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
            ORDER BY s.session_year ASC, s.session_semester ASC");
        $stmtSessions->execute(['sid' => $student_id, 'ajout' => $yes]);
        $allSessions = $stmtSessions;

        // Calculer la moyenne globale
        $stmtStats = $dtb->prepare("SELECT SUM(credit) as total_credits, SUM(credit * grade) as total_points 
            FROM t_2023_notes 
            WHERE student_id = :sid AND ajout = 1 AND grade > 0");
        $stmtStats->execute(['sid' => $student_id]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        $totalCredits = $stats['total_credits'] ?? 0;
        $totalPoints = $stats['total_points'] ?? 0;
        $moyenne = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : null;
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>

<div class="h-screen w-full" style="background: #0a1628;">
    
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
                
                if ($dbError === 'tables_missing'): ?>
                    <div style="text-align:center; padding: 3rem 0;">
                        <i class="bi bi-database-exclamation" style="font-size: 2.5rem; color: #f59e0b; display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: #f59e0b; font-size: 0.9rem; font-weight:600;">Les tables de notes ne sont pas encore configurées</p>
                        <p style="color: #5a8aa8; font-size: 0.8rem; margin-top:0.5rem;">Les notes seront disponibles une fois les tables créées par l'administrateur.</p>
                    </div>
                <?php elseif ($dbError): ?>
                    <div style="text-align:center; padding: 3rem 0;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; color: #ef4444; display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: #fca5a5; font-size: 0.9rem;">Erreur de base de données</p>
                        <p style="color: #5a8aa8; font-size: 0.75rem; margin-top:0.5rem;"><?=htmlspecialchars($dbError)?></p>
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
                            
                            while($crs = $cours->fetch()): 
                                $nbr++;
                                $tcredit += $crs['credit'];
                                $tnote += $crs['grade'];
                                $tnotecredit += $crs['credit'] * $crs['grade'];
                                
                                if ($crs['cours_category'] == 1 || $crs['cours_category'] == "Majeur") {
                                    $nbrMaj++;
                                    $tTMaj += $crs['grade'];
                                }
                                
                                if ($crs['cours_category'] == 1 || $crs['cours_category'] == "Majeur" || 
                                    $crs['cours_category'] == 0 || $crs['cours_category'] == "Général") {
                                    $nbrFinale++;
                                    $tTFinale += $crs['grade'];
                                }
                                
                                $categorie = match($crs['cours_category']) {
                                    0 => "Général",
                                    1 => "Majeur",
                                    -1, 2 => "Selective",
                                    3 => "Additionnel",
                                    default => "-"
                                };
                                
                                $isSuccess = ($crs['grade'] >= 10 || $crs['grade'] == -2);
                                $isFail = ($crs['grade'] < 10 && $crs['grade'] > 0);
                                $etat = $isSuccess ? 'S' : ($isFail ? 'E' : '');
                            ?>
                            <tr>
                                <td class="std-sigle"><?=$crs['Sigle']?></td>
                                <td><?=$crs['title_cours']?></td>
                                <td class="text-center"><?=$crs['credit']?></td>
                                <td class="text-center"><?=$categorie?></td>
                                <td class="text-center font-semibold"><?=$crs['grade'] > 0 ? $crs['grade'] : '-'?></td>
                                <td class="text-center">
                                    <?php if ($etat): ?>
                                        <span class="std-badge <?= $isSuccess ? 'std-badge-success' : 'std-badge-danger' ?>"><?=$etat?></span>
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
                            if ($showPromotion): 
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
                                <th class="text-center"><?php $moyenMajSem = $nbrMaj > 0 ? round($tTMaj / $nbrMaj, 2) : 0; echo $moyenMajSem; ?></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4" style="text-align:right;">Moyenne Générale</th>
                                <th class="text-center std-avg-cell"><?php $moyenFinale = $tcredit > 0 ? round($tnotecredit / $tcredit, 2) : 0; echo $moyenFinale; ?></th>
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
                    <div class="std-cumul-row">
                        <span class="label">Note de Work Education cumulative</span>
                        <span class="value"><?=round($cumulWorkNote / $sessionCount, 2)?></span>
                    </div>
                    <div class="std-cumul-row">
                        <span class="label">Participation chapelle & prière cumulative</span>
                        <span class="value"><?=round($cumulChapel / $sessionCount, 2)?></span>
                    </div>
                    <div class="std-cumul-row">
                        <span class="label">Moyenne Majeure Cumulative</span>
                        <span class="value"><?=round($cumulMaj / $sessionCount, 2)?></span>
                    </div>
                    <div class="std-cumul-row std-cumul-highlight">
                        <span class="label" style="color: #7dd3fc; font-weight: 600;">Moyenne Cumulative</span>
                        <span class="value"><?=round($cumulFinale / $sessionCount, 2)?></span>
                    </div>
                </div>
                <?php endif;
                endif; // end elseif ($allSessions)
                
                if (!$hasNotes && !$dbError): 
                ?>
                    <div style="text-align:center; padding: 3rem 0;">
                        <i class="bi bi-inbox" style="font-size: 2.5rem; color: #3d6a8a; display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: #5a8aa8; font-size: 0.9rem;">Aucune note disponible</p>
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
                <p>Les notes affichées sont provisoires jusqu'à leur validation définitive.</p>
                <p>Pour toute question, veuillez contacter le bureau du registraire.</p>
            </div>
        </div>

    </div>
</div>

<?php include('./student.transition.php'); ?>
</body>
</html>
