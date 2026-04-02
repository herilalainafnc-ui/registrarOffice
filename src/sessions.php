<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') {
    $app_base = '';
}

// Compatibilite: le projet peut stocker l'authentification sous plusieurs cles session.
$isAuthenticated = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['infinit_pseudo']);
if (!$isAuthenticated) {
    header('Location: ' . $app_base . '/login');
    exit;
}

require('../data/backdb.php');

function isValidDateValue($value) {
    if ($value === null || $value === '') {
        return true;
    }

    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

function ensureGraduationDateColumn(PDO $dtb) {
    $checkStmt = $dtb->query("SHOW COLUMNS FROM t_2023_session LIKE 'graduation_date'");
    $exists = (bool)$checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
        $dtb->exec("ALTER TABLE t_2023_session ADD COLUMN graduation_date DATE NULL AFTER date_entry");
    }
}

function generateCurrentYearSessions(PDO $dtb) {
    $currentYear = (int)date('Y');
    $sessionYear = $currentYear . ' - ' . ($currentYear + 1);

    $existsStmt = $dtb->prepare('SELECT COUNT(*) FROM t_2023_session WHERE session_year = :session_year');
    $existsStmt->execute(['session_year' => $sessionYear]);
    $existingCount = (int)$existsStmt->fetchColumn();

    if ($existingCount > 0) {
        throw new RuntimeException("Des sessions existent deja pour l'annee " . $sessionYear . '.');
    }

    $definitions = [
        ['code_prefix' => 'PREM', 'name' => 'Premier semestre', 'semester' => 1],
        ['code_prefix' => 'ETE', 'name' => "Semestre d'ete", 'semester' => 3],
        ['code_prefix' => 'DEUX', 'name' => 'Deuxieme semestre', 'semester' => 2],
        ['code_prefix' => 'HIVER', 'name' => "Semestre d'hiver", 'semester' => 4],
    ];

    $dateEntry = date('Y-m-d');
    $graduationDate = date('Y-m-d', strtotime('+4 months', strtotime($dateEntry)));

    $insertStmt = $dtb->prepare(
        'INSERT INTO t_2023_session
         (session_code, session_name, session_year, session_semester, selected, date_entry, graduation_date)
         VALUES
         (:session_code, :session_name, :session_year, :session_semester, :selected, :date_entry, :graduation_date)'
    );

    foreach ($definitions as $def) {
        $insertStmt->execute([
            'session_code' => $def['code_prefix'] . $currentYear . ($currentYear + 1),
            'session_name' => $def['name'],
            'session_year' => $sessionYear,
            'session_semester' => $def['semester'],
            'selected' => 0,
            'date_entry' => $dateEntry,
            'graduation_date' => $graduationDate,
        ]);
    }

    return $sessionYear;
}

function syncActiveSessionByCurrentDate(PDO $dtb) {
    $today = date('Y-m-d');

    $activeStmt = $dtb->prepare(
        'SELECT session_id
         FROM t_2023_session
         WHERE date_entry IS NOT NULL
           AND graduation_date IS NOT NULL
           AND date_entry <= :today
           AND graduation_date >= :today
         ORDER BY date_entry DESC, graduation_date ASC, session_semester ASC, session_id DESC
         LIMIT 1'
    );
    $activeStmt->execute(['today' => $today]);
    $activeId = (int)$activeStmt->fetchColumn();

    $dtb->beginTransaction();
    try {
        $dtb->exec('UPDATE t_2023_session SET selected = 0');

        if ($activeId > 0) {
            $setActiveStmt = $dtb->prepare('UPDATE t_2023_session SET selected = 1 WHERE session_id = :session_id');
            $setActiveStmt->execute(['session_id' => $activeId]);
        }

        $dtb->commit();
    } catch (Throwable $e) {
        if ($dtb->inTransaction()) {
            $dtb->rollBack();
        }
        throw $e;
    }

    return $activeId;
}
$flashType = '';
$flashMessage = '';
$isAjaxRequest = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1');

try {
    ensureGraduationDateColumn($dtb);
    syncActiveSessionByCurrentDate($dtb);
} catch (Throwable $e) {
    $flashType = 'error';
    $flashMessage = "Impossible d'initialiser automatiquement la gestion des sessions actives. Verifiez la structure de t_2023_session.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        ensureGraduationDateColumn($dtb);

        if ($action === 'generate_four_sessions') {
            $generatedYear = generateCurrentYearSessions($dtb);
            syncActiveSessionByCurrentDate($dtb);

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Les 4 sessions ont ete generees pour ' . $generatedYear . '.',
                    'year' => $generatedYear,
                ]);
                exit;
            }

            header('Location: ' . $app_base . '/sessions?status=generated&year=' . urlencode($generatedYear));
            exit;
        }

        if ($action === 'update') {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $sessionCode = trim((string)($_POST['session_code'] ?? ''));
            $sessionName = trim((string)($_POST['session_name'] ?? ''));
            $sessionYear = trim((string)($_POST['session_year'] ?? ''));
            $sessionSemester = (int)($_POST['session_semester'] ?? 0);
            $dateEntry = trim((string)($_POST['date_entry'] ?? ''));
            $graduationDate = trim((string)($_POST['graduation_date'] ?? ''));

            if ($sessionId <= 0 || $sessionName === '' || $sessionYear === '' || $sessionSemester <= 0) {
                throw new RuntimeException('Champs obligatoires manquants.');
            }

            if (!isValidDateValue($dateEntry) || !isValidDateValue($graduationDate)) {
                throw new RuntimeException('Format de date invalide (YYYY-MM-DD attendu).');
            }

            if ($dateEntry !== '' && $graduationDate !== '' && $graduationDate < $dateEntry) {
                throw new RuntimeException('La date de fin de semestre doit etre apres la date de debut.');
            }

            $stmt = $dtb->prepare(
                'UPDATE t_2023_session
                 SET session_code = :session_code,
                     session_name = :session_name,
                     session_year = :session_year,
                     session_semester = :session_semester,
                     date_entry = :date_entry,
                     graduation_date = :graduation_date
                 WHERE session_id = :session_id'
            );

            $stmt->execute([
                'session_code' => $sessionCode !== '' ? $sessionCode : null,
                'session_name' => $sessionName,
                'session_year' => $sessionYear,
                'session_semester' => $sessionSemester,
                'date_entry' => $dateEntry !== '' ? $dateEntry : null,
                'graduation_date' => $graduationDate !== '' ? $graduationDate : null,
                'session_id' => $sessionId,
            ]);

            syncActiveSessionByCurrentDate($dtb);

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Session mise a jour avec succes.',
                    'session_id' => $sessionId,
                ]);
                exit;
            }

            header('Location: ' . $app_base . '/sessions?status=updated');
            exit;
        }

        if ($action === 'create') {
            $sessionCode = trim((string)($_POST['session_code'] ?? ''));
            $sessionName = trim((string)($_POST['session_name'] ?? ''));
            $sessionYear = trim((string)($_POST['session_year'] ?? ''));
            $sessionSemester = (int)($_POST['session_semester'] ?? 0);
            $dateEntry = trim((string)($_POST['date_entry'] ?? ''));
            $graduationDate = trim((string)($_POST['graduation_date'] ?? ''));

            if ($sessionName === '' || $sessionYear === '' || $sessionSemester <= 0) {
                throw new RuntimeException('Champs obligatoires manquants.');
            }

            if (!isValidDateValue($dateEntry) || !isValidDateValue($graduationDate)) {
                throw new RuntimeException('Format de date invalide (YYYY-MM-DD attendu).');
            }

            if ($dateEntry !== '' && $graduationDate !== '' && $graduationDate < $dateEntry) {
                throw new RuntimeException('La date de fin de semestre doit etre apres la date de debut.');
            }

            $stmt = $dtb->prepare(
                'INSERT INTO t_2023_session
                 (session_code, session_name, session_year, session_semester, selected, date_entry, graduation_date)
                 VALUES
                 (:session_code, :session_name, :session_year, :session_semester, :selected, :date_entry, :graduation_date)'
            );

            $stmt->execute([
                'session_code' => $sessionCode !== '' ? $sessionCode : null,
                'session_name' => $sessionName,
                'session_year' => $sessionYear,
                'session_semester' => $sessionSemester,
                'selected' => 0,
                'date_entry' => $dateEntry !== '' ? $dateEntry : null,
                'graduation_date' => $graduationDate !== '' ? $graduationDate : null,
            ]);

            syncActiveSessionByCurrentDate($dtb);

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Session creee avec succes.',
                ]);
                exit;
            }

            header('Location: ' . $app_base . '/sessions?status=created');
            exit;
        }

        throw new RuntimeException('Action invalide.');
    } catch (Throwable $e) {
        if ($isAjaxRequest) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }

        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }
}

$status = $_GET['status'] ?? '';
if ($status === 'updated') {
    $flashType = 'success';
    $flashMessage = 'Session mise a jour avec succes.';
} elseif ($status === 'created') {
    $flashType = 'success';
    $flashMessage = 'Session creee avec succes.';
} elseif ($status === 'generated') {
    $flashType = 'success';
    $generatedYear = trim((string)($_GET['year'] ?? ''));
    if ($generatedYear !== '') {
        $flashMessage = 'Les 4 sessions ont ete generees pour ' . $generatedYear . '.';
    } else {
        $flashMessage = 'Les 4 sessions ont ete generees.';
    }
}

try {
    $checkStmt = $dtb->query("SHOW COLUMNS FROM t_2023_session LIKE 'graduation_date'");
    $hasGraduationDate = (bool)$checkStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $hasGraduationDate = false;
}

$flashClasses = $flashType === 'success'
    ? 'bg-emerald-900/30 border-emerald-600 text-emerald-300'
    : 'bg-red-900/25 border-red-600 text-red-300';

if ($hasGraduationDate) {
    $sessionsStmt = $dtb->query(
        'SELECT session_id, session_code, session_name, session_year, session_semester, selected, date_entry, graduation_date
         FROM t_2023_session
         ORDER BY session_year DESC, session_semester ASC, session_id DESC'
    );
} else {
    $sessionsStmt = $dtb->query(
        'SELECT session_id, session_code, session_name, session_year, session_semester, selected, date_entry, NULL AS graduation_date
         FROM t_2023_session
         ORDER BY session_year DESC, session_semester ASC, session_id DESC'
    );
}

$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

$sessionsByYear = [];
foreach ($sessions as $sessionRow) {
    $yearKey = trim((string)($sessionRow['session_year'] ?? ''));
    if ($yearKey === '') {
        $yearKey = 'Annee non definie';
    }

    if (!isset($sessionsByYear[$yearKey])) {
        $sessionsByYear[$yearKey] = [];
    }

    $sessionsByYear[$yearKey][] = $sessionRow;
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Gestion des sessions</title>
    <style>
        :root {
            --sh-page-grad-a: rgba(14, 165, 233, 0.12);
            --sh-page-grad-b: rgba(16, 185, 129, 0.10);
            --sh-card-bg: rgba(15, 23, 42, 0.70);
            --sh-card-border: rgba(148, 163, 184, 0.22);
            --sh-card-shadow: rgba(2, 6, 23, 0.22);
            --sh-title: #f8fafc;
            --sh-subtitle: #94a3b8;
            --sh-input-bg: rgba(15, 23, 42, 0.55);
            --sh-input-border: rgba(148, 163, 184, 0.32);
            --sh-input-text: #f8fafc;
            --sh-input-focus: rgba(34, 211, 238, 0.85);
            --sh-input-ring: rgba(34, 211, 238, 0.2);
            --sh-muted-btn-bg: rgba(51, 65, 85, 0.7);
            --sh-muted-btn-hover: rgba(71, 85, 105, 0.75);
            --sh-muted-btn-border: rgba(148, 163, 184, 0.25);
            --sh-muted-btn-text: #cbd5e1;
            --sh-year-border: rgba(34, 211, 238, 0.28);
            --sh-year-bg-a: rgba(14, 116, 144, 0.2);
            --sh-year-bg-b: rgba(15, 23, 42, 0.75);
            --sh-row-border: rgba(148, 163, 184, 0.2);
            --sh-row-bg: rgba(15, 23, 42, 0.42);
        }

        [data-theme="light"] {
            --sh-page-grad-a: rgba(14, 165, 233, 0.16);
            --sh-page-grad-b: rgba(16, 185, 129, 0.12);
            --sh-card-bg: rgba(255, 255, 255, 0.88);
            --sh-card-border: rgba(15, 23, 42, 0.10);
            --sh-card-shadow: rgba(2, 6, 23, 0.08);
            --sh-title: #0f172a;
            --sh-subtitle: #475569;
            --sh-input-bg: rgba(248, 250, 252, 0.95);
            --sh-input-border: rgba(15, 23, 42, 0.18);
            --sh-input-text: #0f172a;
            --sh-input-focus: rgba(14, 165, 233, 0.75);
            --sh-input-ring: rgba(14, 165, 233, 0.2);
            --sh-muted-btn-bg: rgba(241, 245, 249, 0.95);
            --sh-muted-btn-hover: rgba(226, 232, 240, 1);
            --sh-muted-btn-border: rgba(15, 23, 42, 0.15);
            --sh-muted-btn-text: #334155;
            --sh-year-border: rgba(14, 116, 144, 0.3);
            --sh-year-bg-a: rgba(103, 232, 249, 0.25);
            --sh-year-bg-b: rgba(241, 245, 249, 0.7);
            --sh-row-border: rgba(15, 23, 42, 0.12);
            --sh-row-bg: rgba(248, 250, 252, 0.88);
        }

        .sh-page {
            background:
                radial-gradient(circle at 10% 10%, var(--sh-page-grad-a), transparent 30%),
                radial-gradient(circle at 90% 80%, var(--sh-page-grad-b), transparent 35%);
        }

        .sh-card {
            background: var(--sh-card-bg);
            border: 1px solid var(--sh-card-border);
            border-radius: 14px;
            box-shadow: 0 10px 30px var(--sh-card-shadow);
            backdrop-filter: blur(8px);
        }

        .sh-title {
            color: var(--sh-title);
            letter-spacing: -0.01em;
        }

        .sh-subtitle {
            color: var(--sh-subtitle);
        }

        .sh-input {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--sh-input-border);
            background: var(--sh-input-bg);
            color: var(--sh-input-text);
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .form-control.sh-input {
            background-color: var(--sh-input-bg) !important;
            border-color: var(--sh-input-border) !important;
            color: var(--sh-input-text) !important;
        }

        :root:not([data-theme="light"]) .form-control.sh-input,
        [data-theme="dark"] .form-control.sh-input {
            background-color: rgba(15, 23, 42, 0.85) !important;
            border-color: rgba(100, 116, 139, 0.55) !important;
            color: #f8fafc !important;
            color-scheme: dark;
        }

        :root:not([data-theme="light"]) .form-control.sh-input::placeholder,
        [data-theme="dark"] .form-control.sh-input::placeholder {
            color: #94a3b8 !important;
        }

        :root:not([data-theme="light"]) input[type="date"].sh-input,
        [data-theme="dark"] input[type="date"].sh-input {
            color-scheme: dark;
        }

        :root:not([data-theme="light"]) input[type="date"].sh-input::-webkit-calendar-picker-indicator,
        [data-theme="dark"] input[type="date"].sh-input::-webkit-calendar-picker-indicator {
            filter: invert(0.95);
            opacity: 0.9;
        }

        .sh-input:focus {
            outline: none;
            border-color: var(--sh-input-focus);
            box-shadow: 0 0 0 3px var(--sh-input-ring);
        }

        .sh-input:disabled {
            opacity: 0.75;
            cursor: not-allowed;
        }

        .sh-btn {
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.14s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }

        .sh-btn:hover {
            transform: translateY(-1px);
        }

        .sh-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .sh-btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #06b6d4);
            color: #f8fafc;
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.28);
        }

        .sh-btn-primary:hover {
            box-shadow: 0 10px 24px rgba(14, 165, 233, 0.35);
        }

        .sh-btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ecfdf5;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.28);
        }

        .sh-btn-success:hover {
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.35);
        }

        .sh-btn-muted {
            background: var(--sh-muted-btn-bg);
            border: 1px solid var(--sh-muted-btn-border);
            color: var(--sh-muted-btn-text);
        }

        .sh-btn-muted:hover {
            background: var(--sh-muted-btn-hover);
        }

        .sh-year-chip {
            border-radius: 12px;
            border: 1px solid var(--sh-year-border);
            background: linear-gradient(120deg, var(--sh-year-bg-a), var(--sh-year-bg-b));
        }

        .sh-row {
            border-radius: 12px;
            border: 1px solid var(--sh-row-border);
            background: var(--sh-row-bg);
        }

        .sh-label {
            font-size: 11px;
            color: var(--sh-subtitle);
            margin-bottom: 4px;
            display: block;
            font-weight: 500;
        }

        .sh-grid {
            min-width: 1320px;
            display: grid;
            grid-template-columns: 80px 220px 260px 180px 120px 210px 210px 180px 140px;
            gap: 12px;
            align-items: end;
        }

        .sh-save-col {
            display: flex;
            align-items: end;
            justify-content: flex-start;
        }

        .sh-btn-save {
            width: auto;
            min-width: 110px;
            padding-left: 14px;
            padding-right: 14px;
        }

        .sh-grid-create {
            min-width: 1240px;
            display: grid;
            grid-template-columns: 220px 260px 180px 120px 210px 210px 180px 150px;
            gap: 12px;
            align-items: end;
        }

        @media (max-width: 1024px) {
            .sh-grid,
            .sh-grid-create {
                min-width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .sh-grid,
            .sh-grid-create {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php'); ?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back sh-page flex-1 overflow-y-auto p-4 lg:p-6">

                <div class="sh-card p-5 text-white mb-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <h1 class="sh-title text-xl font-semibold"><i class="bi bi-calendar2-week mr-2"></i>Gestion des sessions</h1>
                            <p class="sh-subtitle text-xs mt-1">date_entry = debut du semestre, graduation_date = fin du semestre.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <form id="generateSessionsForm" method="post">
                                <input type="hidden" name="action" value="generate_four_sessions">
                                <button type="submit" class="sh-btn sh-btn-success px-4 py-2 text-xs">
                                    Generer les 4 sessions
                                </button>
                            </form>
                            <a href="<?=$app_base?>/session-rankings" class="sh-btn sh-btn-muted px-4 py-2 text-xs">
                                Voir les classements
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($flashMessage !== ''): ?>
                    <div class="mb-4 p-3 rounded-md border <?=$flashClasses?>">
                        <?=htmlspecialchars($flashMessage)?>
                    </div>
                <?php endif; ?>

                <div class="sh-card p-5 text-white mb-4">
                    <h2 class="sh-title text-sm font-semibold mb-3">Ajouter une session</h2>
                    <form id="createSessionForm" method="post" class="overflow-x-auto">
                        <input type="hidden" name="action" value="create">
                        <div class="sh-grid-create">
                            <div>
                                <label class="sh-label">Code session</label>
                                <input type="text" name="session_code" placeholder="Code session (optionnel)" class="form-control sh-input" maxlength="50">
                            </div>

                            <div>
                                <label class="sh-label">Nom session</label>
                                <input type="text" name="session_name" placeholder="Nom session" class="form-control sh-input" required>
                            </div>

                            <div>
                                <label class="sh-label">Annee</label>
                                <input type="text" name="session_year" placeholder="Annee ex: 2025 - 2026" class="form-control sh-input" required>
                            </div>

                            <div>
                                <label class="sh-label">Semestre</label>
                                <input type="number" min="1" max="4" name="session_semester" placeholder="Semestre" class="form-control sh-input" required>
                            </div>

                            <div>
                                <label class="sh-label">Date debut (date_entry)</label>
                                <input type="date" name="date_entry" class="form-control sh-input" required>
                            </div>

                            <div>
                                <label class="sh-label">Date fin (graduation_date)</label>
                                <input type="date" name="graduation_date" class="form-control sh-input" required>
                            </div>

                            <div class="pb-2">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                    <span class="inline-block w-2 h-2 rounded-full bg-cyan-400"></span>
                                    Session active geree automatiquement par date
                                </label>
                            </div>

                            <div>
                                <button type="submit" class="sh-btn sh-btn-primary w-full px-4 py-2 text-xs">
                                    Ajouter la session
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="sh-card p-5 text-white">
                    <h2 class="sh-title text-sm font-semibold mb-3">Liste des sessions (t_2023_session)</h2>

                    <div id="sessionsListWrapper">
                    <?php if (empty($sessions)): ?>
                        <p class="text-slate-400 text-xs">Aucune session trouvee.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($sessionsByYear as $academicYear => $yearSessions): ?>
                                <div class="sh-year-chip px-4 py-3">
                                    <div class="sh-subtitle text-xs">Annee academique</div>
                                    <div class="text-sm font-semibold text-cyan-300"><?= htmlspecialchars($academicYear) ?></div>
                                </div>

                                <?php foreach ($yearSessions as $session): ?>
                                <form method="post" class="js-session-update-form sh-row p-3 overflow-x-auto">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="session_id" value="<?= (int)$session['session_id'] ?>">

                                    <div class="sh-grid">
                                        <div>
                                            <label class="sh-label">ID</label>
                                            <input type="text" value="<?= (int)$session['session_id'] ?>" class="form-control sh-input text-slate-300" disabled>
                                        </div>

                                        <div>
                                            <label class="sh-label">Code session</label>
                                            <input type="text" name="session_code" value="<?= htmlspecialchars((string)$session['session_code']) ?>" class="form-control sh-input" maxlength="50">
                                        </div>

                                        <div>
                                            <label class="sh-label">Nom session</label>
                                            <input type="text" name="session_name" value="<?= htmlspecialchars((string)$session['session_name']) ?>" class="form-control sh-input" required>
                                        </div>

                                        <div>
                                            <label class="sh-label">Annee</label>
                                            <input type="text" name="session_year" value="<?= htmlspecialchars((string)$session['session_year']) ?>" class="form-control sh-input" required>
                                        </div>

                                        <div>
                                            <label class="sh-label">Semestre</label>
                                            <input type="number" min="1" max="4" name="session_semester" value="<?= (int)$session['session_semester'] ?>" class="form-control sh-input" required>
                                        </div>

                                        <div>
                                            <label class="sh-label">Date debut (date_entry)</label>
                                            <input type="date" name="date_entry" value="<?= htmlspecialchars((string)$session['date_entry']) ?>" class="form-control sh-input" required>
                                        </div>

                                        <div>
                                            <label class="sh-label">Date fin (graduation_date)</label>
                                            <input type="date" name="graduation_date" value="<?= htmlspecialchars((string)$session['graduation_date']) ?>" class="form-control sh-input" required>
                                        </div>

                                        <div class="pb-2 text-xs">
                                            <?php if ((int)$session['selected'] === 1): ?>
                                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-1">
                                                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                                                    Session active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-500/20 text-slate-300 border border-slate-500/35 px-2 py-1">
                                                    <span class="inline-block w-2 h-2 rounded-full bg-slate-400"></span>
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="sh-save-col">
                                            <button type="submit" class="sh-btn sh-btn-primary sh-btn-save py-2 text-xs">
                                                Enregistrer
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
            (function () {
                const initialFlash = <?= json_encode([
                    'type' => $flashType,
                    'message' => $flashMessage,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

                function notifySuccess(message) {
                    if (typeof Toast !== 'undefined' && typeof Toast.success === 'function') {
                        Toast.success(message || 'Operation reussie.');
                        return;
                    }

                    console.log(message || 'Operation reussie.');
                }

                function notifyError(message) {
                    if (typeof Toast !== 'undefined' && typeof Toast.error === 'function') {
                        Toast.error(message || 'Une erreur est survenue.');
                        return;
                    }

                    console.error(message || 'Une erreur est survenue.');
                }

                function notifyInfo(message) {
                    if (typeof Toast !== 'undefined' && typeof Toast.info === 'function') {
                        Toast.info(message || 'Information.');
                        return;
                    }

                    console.info(message || 'Information.');
                }

                function showInitialFlashToast() {
                    if (!initialFlash || !initialFlash.message) {
                        return;
                    }

                    if (initialFlash.type === 'success') {
                        notifySuccess(initialFlash.message);
                        return;
                    }

                    if (initialFlash.type === 'error') {
                        notifyError(initialFlash.message);
                        return;
                    }

                    notifyInfo(initialFlash.message);
                }

                function confirmWithToast(message) {
                    if (typeof Toast !== 'undefined' && typeof Toast.confirm === 'function') {
                        return Toast.confirm(message, {
                            title: 'Confirmation',
                            confirmText: 'Oui, generer',
                            cancelText: 'Annuler'
                        });
                    }

                    return Promise.resolve(window.confirm(message));
                }

                async function postFormAjax(form) {
                    const formData = new FormData(form);
                    formData.append('ajax', '1');

                    // `name="action"` inputs can shadow `form.action` in the DOM.
                    // Read the real attribute to avoid malformed URLs like [object HTMLInputElement].
                    const formActionAttr = (form.getAttribute('action') || '').trim();
                    const postUrl = formActionAttr !== '' ? formActionAttr : window.location.href;

                    const response = await fetch(postUrl, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    let data = null;
                    try {
                        data = await response.json();
                    } catch (e) {
                        data = { success: false, message: 'Reponse serveur invalide.' };
                    }

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Operation echouee.');
                    }

                    return data;
                }

                async function refreshSessionsList() {
                    const response = await fetch(window.location.href, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newWrapper = doc.getElementById('sessionsListWrapper');
                    const currentWrapper = document.getElementById('sessionsListWrapper');

                    if (newWrapper && currentWrapper) {
                        currentWrapper.innerHTML = newWrapper.innerHTML;
                        bindUpdateForms();
                    }
                }

                function bindUpdateForms() {
                    const updateForms = document.querySelectorAll('.js-session-update-form');
                    updateForms.forEach(function (form) {
                        if (form.dataset.bound === '1') {
                            return;
                        }
                        form.dataset.bound = '1';

                        form.addEventListener('submit', async function (event) {
                            event.preventDefault();

                            const submitBtn = form.querySelector('button[type="submit"]');
                            if (submitBtn) submitBtn.disabled = true;

                            try {
                                const result = await postFormAjax(form);
                                notifySuccess(result.message || 'Session mise a jour.');
                                await refreshSessionsList();
                            } catch (error) {
                                notifyError(error.message);
                            } finally {
                                if (submitBtn) submitBtn.disabled = false;
                            }
                        });
                    });
                }

                function bindCreateForm() {
                    const form = document.getElementById('createSessionForm');
                    if (!form) return;

                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();

                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) submitBtn.disabled = true;

                        try {
                            const result = await postFormAjax(form);
                            notifySuccess(result.message || 'Session creee.');
                            form.reset();
                            await refreshSessionsList();
                        } catch (error) {
                            notifyError(error.message);
                        } finally {
                            if (submitBtn) submitBtn.disabled = false;
                        }
                    });
                }

                function bindGenerateForm() {
                    const form = document.getElementById('generateSessionsForm');
                    if (!form) return;

                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();

                        const confirmed = await confirmWithToast("Generer automatiquement les 4 sessions pour l'annee en cours ?");
                        if (!confirmed) {
                            return;
                        }

                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) submitBtn.disabled = true;

                        try {
                            const result = await postFormAjax(form);
                            notifySuccess(result.message || 'Sessions generees.');
                            await refreshSessionsList();
                        } catch (error) {
                            notifyError(error.message);
                        } finally {
                            if (submitBtn) submitBtn.disabled = false;
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    showInitialFlashToast();
                    bindUpdateForms();
                    bindCreateForm();
                    bindGenerateForm();
                });
            })();
            </script>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>
</body>
</html>
