<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php');?>
    <title>Solde de compte</title>
    <style>
        .balance-card {
            background: rgb(var(--c-canvas) / 0.6);
            border: 1px solid rgb(var(--c-line) / 0.45);
            border-radius: 0.75rem;
            overflow: hidden;
            height: calc(100vh - 115px);
            display: flex;
            flex-direction: column;
        }

        .balance-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgb(var(--c-line) / 0.45);
        }

        .balance-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .student-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            border: 1px solid rgb(var(--c-accent) / 0.35);
            background: rgb(var(--c-accent) / 0.14);
            color: rgb(var(--c-text));
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.35rem 0.65rem;
            white-space: nowrap;
        }

        .balance-icon {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgb(var(--c-accent) / 0.15);
            color: rgb(var(--c-accent-text));
            font-size: 1.1rem;
        }

        .balance-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: rgb(var(--c-text));
            letter-spacing: -0.02em;
        }

        .balance-subtitle {
            font-size: 0.8rem;
            color: rgb(var(--c-text-3));
        }

        .search-box {
            width: 100%;
            max-width: 380px;
            background: rgb(var(--c-canvas) / 0.7);
            border: 1px solid rgb(var(--c-accent) / 0.4);
            color: rgb(var(--c-text));
            border-radius: 0.5rem;
            padding: 0.65rem 0.85rem 0.65rem 3.05rem;
            font-size: 0.85rem;
            margin: 1rem;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%238ea5c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>');
            background-repeat: no-repeat;
            background-position: 0.85rem center;
            background-size: 1rem 1rem;
            transition: all 0.2s ease;
        }

        .search-box::placeholder {
            color: rgb(var(--c-text-3));
        }

        .balance-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            padding: 0 1rem 1rem 1rem;
        }

        .session-select {
            min-width: 280px;
            background: rgb(var(--c-canvas) / 0.7);
            border: 1px solid rgb(var(--c-accent) / 0.4);
            color: rgb(var(--c-text));
            border-radius: 0.5rem;
            padding: 0.65rem 0.85rem;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .session-select:focus {
            outline: none;
            border-color: rgb(var(--c-accent));
            background-color: rgb(var(--c-canvas) / 0.85);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
        }

        .search-box:focus {
            outline: none;
            border-color: rgb(var(--c-accent));
            background-color: rgb(var(--c-canvas) / 0.85);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
        }

        .balance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
            table-layout: fixed;
        }

        .balance-table-wrap {
            flex: 1;
            overflow: auto;
            position: relative;
        }

        .balance-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgb(var(--c-surface));
            color: rgb(var(--c-accent-text));
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.65rem 0.6rem;
            border-bottom: 1px solid rgb(var(--c-accent) / 0.2);
            text-align: left;
            box-shadow: 0 1px 0 rgba(14, 165, 233, 0.18);
        }

        .balance-table tbody td {
            padding: 0.6rem;
            border-bottom: 1px solid rgb(var(--c-line) / 0.3);
            color: rgb(var(--c-text));
        }

        .balance-table tbody tr:hover {
            background: rgb(var(--c-raised) / 0.2);
        }

        .amount-negative {
            color: #86efac;
            font-weight: 700;
        }

        .amount-positive {
            color: #fca5a5;
            font-weight: 700;
        }

        .amount-zero {
            color: rgb(var(--c-text-2));
            font-weight: 700;
        }

        .balance-editable {
            cursor: pointer;
        }

        .balance-editable:hover {
            background: rgb(var(--c-accent) / 0.12);
        }

        .balance-input {
            width: 100%;
            background: rgb(var(--c-canvas) / 0.9);
            border: 1px solid rgb(var(--c-accent) / 0.55);
            color: rgb(var(--c-text));
            border-radius: 0.35rem;
            padding: 0.2rem 0.35rem;
            font-size: 0.78rem;
            outline: none;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 0.2rem 0.6rem;
            border: 1px solid transparent;
        }

        .status-debitaire {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-creditaire {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.16);
            border-color: rgba(34, 197, 94, 0.3); 
        }

        .status-solde {
            color: rgb(var(--c-text));
            background: rgb(var(--c-text-3) / 0.22);
            border-color: rgb(var(--c-line-strong) / 0.35);
        }

        [data-theme="light"] .balance-card {
            background: rgb(var(--c-surface) / 0.9);
            border-color: rgb(var(--c-line-strong) / 0.35);
        }

        [data-theme="light"] .balance-header {
            border-bottom-color: rgb(var(--c-line-strong) / 0.28);
        }

        [data-theme="light"] .balance-title,
        [data-theme="light"] .balance-table tbody td {
            color: rgb(var(--c-text));
        }

        [data-theme="light"] .balance-subtitle {
            color: rgb(var(--c-text-2));
        }

        [data-theme="light"] .student-count-badge {
            background: rgb(var(--c-accent) / 0.12);
            border-color: rgb(var(--c-accent) / 0.25);
            color: rgb(var(--c-accent-text));
        }

        [data-theme="light"] .balance-icon {
            background: rgb(var(--c-accent) / 0.12);
        }

        [data-theme="light"] .search-box,
        [data-theme="light"] .session-select,
        [data-theme="light"] .balance-input {
            background: rgb(var(--c-surface) / 0.95);
            border-color: rgb(var(--c-line-strong) / 0.45);
            color: rgb(var(--c-text));
        }

        [data-theme="light"] .search-box::placeholder {
            color: rgb(var(--c-text-3));
        }

        [data-theme="light"] .balance-table thead th {
            background: rgb(var(--c-surface-2));
            color: rgb(var(--c-accent-text));
            border-bottom-color: rgb(var(--c-accent) / 0.25);
            box-shadow: 0 1px 0 rgba(96, 165, 250, 0.16);
        }

        [data-theme="light"] .balance-table tbody tr:hover {
            background: rgb(var(--c-accent) / 0.06);
        }

        [data-theme="light"] .status-solde {
            color: rgb(var(--c-text-2));
            background: rgb(var(--c-text-3) / 0.16);
            border-color: rgb(var(--c-line-strong) / 0.28);
        }

        [data-theme="light"] .status-debitaire {
            color: #b91c1c;
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.24);
        }

        [data-theme="light"] .status-creditaire {
            color: #166534;
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(34, 197, 94, 0.24);
        }
    </style>
</head>

<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php');?>
    <?php require('../src/main/notificationGeneral.php');?>

    <div class="w-full flex flex-col lg:flex-row">

        <?php require('../init/menubar.php');?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

            <div class="back flex-1 overflow-hidden">

                <div class="p-4 overflow-hidden" style="height: calc(100vh - 75px);">
                    <?php
                    if (!isset($rg_user) || $rg_user['level'] > 3) {
                        header('Location: ' . $app_base . '/etudiants');
                        exit;
                    }

                    $sessionsStmt = $dtb->prepare("SELECT session_id, session_name, session_year, session_semester FROM t_2023_session ORDER BY session_year DESC, session_semester DESC, session_id DESC");
                    $sessionsStmt->execute();
                    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $selectedSessionId = (int)($_GET['session_id'] ?? 0);
                    if ($selectedSessionId <= 0) {
                        // Chercher la session active (selected = 1)
                        $activeSessionStmt = $dtb->prepare("SELECT session_id FROM t_2023_session WHERE selected = 1 ORDER BY session_id DESC LIMIT 1");
                        $activeSessionStmt->execute();
                        $activeSessionIdResult = (int)($activeSessionStmt ? $activeSessionStmt->fetchColumn() : 0);
                        if ($activeSessionIdResult > 0) {
                            $selectedSessionId = $activeSessionIdResult;
                        } elseif (!empty($sessions)) {
                            // Fallback à la première session si aucune session active trouvée
                            $selectedSessionId = (int)$sessions[0]['session_id'];
                        }
                    }

                    $selectedSession = null;
                    foreach ($sessions as $sess) {
                        if ((int)$sess['session_id'] === $selectedSessionId) {
                            $selectedSession = $sess;
                            break;
                        }
                    }

                    if ($selectedSession === null && !empty($sessions)) {
                        $selectedSession = $sessions[0];
                        $selectedSessionId = (int)$selectedSession['session_id'];
                    }

                    $students = [];
                    if ($selectedSessionId > 0) {
                        $studentsStmt = $dtb->prepare("SELECT DISTINCT std.student_id, std.account_code, std.student_nom, std.student_prenom
                                                       FROM t_2024_inscription_session ins
                                                       INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
                                                                                                             WHERE ins.session_id = :session_id
                                                                                                                 AND (std.suspended IS NULL OR std.suspended != 1)
                                                                                                                 AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)
                                                                                                                 AND std.remove != 1
                                                       ORDER BY std.student_id DESC");
                        $studentsStmt->execute(['session_id' => $selectedSessionId]);
                        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
                    }

                    $studentTotal = count($students);

                    $toNumber = function ($value) {
                        $raw = trim((string)$value);
                        if ($raw === '') {
                            return 0.0;
                        }
                        $raw = str_replace([' ', "\xC2\xA0"], '', $raw);
                        $raw = str_replace(',', '.', $raw);
                        return is_numeric($raw) ? (float)$raw : 0.0;
                    };

                    $financeIds = [];
                    foreach ($students as $row) {
                        $matricule = trim((string)($row['student_id'] ?? ''));
                        if ($matricule !== '') {
                            $financeIds[$matricule] = true;
                        }
                        $digits = preg_replace('/\D+/', '', $matricule);
                        if ($digits !== '' && ctype_digit((string)$digits)) {
                            $financeIds[(string)((int)$digits)] = true;
                        }
                    }

                    $balanceByFinanceId = [];
                    $financeActualIdByKey = [];
                    if ($selectedSessionId > 0 && !empty($financeIds)) {
                        $financeIds = array_keys($financeIds);
                        $placeholders = [];
                        $params = [];

                        foreach ($financeIds as $i => $id) {
                            $key = 'sid_' . $i;
                            $placeholders[] = ':' . $key;
                            $params[$key] = (string)$id;
                        }

                        $params['session_id'] = $selectedSessionId;

                        $hasBalanceColumn = false;
                        $colStmt = $dtb->query("SHOW COLUMNS FROM finance LIKE 'balance_amount'");
                        if ($colStmt && $colStmt->fetch(PDO::FETCH_ASSOC)) {
                            $hasBalanceColumn = true;
                        }

                        $financeSql = $hasBalanceColumn
                            ? "SELECT student_id, balance_amount, total_payment, amount_pay_1, amount_pay_2, amount_pay_3, amount_pay_4, amount_pay_5
                               FROM finance
                               WHERE session_id = :session_id
                                 AND student_id IN (" . implode(', ', $placeholders) . ")"
                            : "SELECT student_id, total_payment, amount_pay_1, amount_pay_2, amount_pay_3, amount_pay_4, amount_pay_5
                               FROM finance
                               WHERE session_id = :session_id
                                 AND student_id IN (" . implode(', ', $placeholders) . ")";

                        $financeStmt = $dtb->prepare($financeSql);
                        $financeStmt->execute($params);

                        while ($fr = $financeStmt->fetch(PDO::FETCH_ASSOC)) {
                            $financeStudentIdRaw = trim((string)($fr['student_id'] ?? ''));
                            if ($financeStudentIdRaw === '') {
                                continue;
                            }

                            $storedBalance = $hasBalanceColumn ? ($fr['balance_amount'] ?? null) : null;
                            if ($storedBalance !== null && trim((string)$storedBalance) !== '') {
                                $balanceValue = $toNumber($storedBalance);
                            } else {
                                $total = $toNumber($fr['total_payment'] ?? 0);
                                $paid = $toNumber($fr['amount_pay_1'] ?? 0)
                                    + $toNumber($fr['amount_pay_2'] ?? 0)
                                    + $toNumber($fr['amount_pay_3'] ?? 0)
                                    + $toNumber($fr['amount_pay_4'] ?? 0)
                                    + $toNumber($fr['amount_pay_5'] ?? 0);

                                $balanceValue = $total - $paid;
                            }

                            $balanceByFinanceId[$financeStudentIdRaw] = $balanceValue;
                            $financeActualIdByKey[$financeStudentIdRaw] = $financeStudentIdRaw;

                            $financeDigits = preg_replace('/\D+/', '', $financeStudentIdRaw);
                            if ($financeDigits !== '' && ctype_digit((string)$financeDigits)) {
                                $financeDigitsKey = (string)((int)$financeDigits);
                                $balanceByFinanceId[$financeDigitsKey] = $balanceValue;
                                $financeActualIdByKey[$financeDigitsKey] = $financeStudentIdRaw;
                            }
                        }
                    }
                    ?>

                    <div class="balance-card">
                        <div class="balance-header">
                            <div class="balance-header-left">
                                <div class="balance-icon"><i class="bi bi-wallet2"></i></div>
                                <div>
                                    <div class="balance-title">Solde de compte</div>
                                    <div class="balance-subtitle">
                                        Matricule, Account Code, Nom complet, Solde et etat debitaire/creditaire
                                        <?php if (!empty($selectedSession)): ?>
                                            - Session: <?=htmlspecialchars((string)($selectedSession['session_name'] ?? ''), ENT_QUOTES, 'UTF-8')?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="student-count-badge"><i class="bi bi-people-fill"></i> Total etudiants: <?=$studentTotal?></div>
                        </div>

                        <div class="balance-filters">
                            <form method="get" action="<?=$app_base?>/finance/account-balance">
                                <select name="session_id" class="session-select">
                                    <?php foreach ($sessions as $session): ?>
                                        <?php
                                        $sid = (int)($session['session_id'] ?? 0);
                                        $isSelected = $sid === $selectedSessionId;
                                        $label = trim((string)($session['session_name'] ?? 'Session ' . $sid));
                                        $year = trim((string)($session['session_year'] ?? ''));
                                        $semester = trim((string)($session['session_semester'] ?? ''));
                                        $extra = trim($year . ($semester !== '' ? ' - Semestre ' . $semester : ''));
                                        ?>
                                        <option value="<?=$sid?>" <?=$isSelected ? 'selected' : ''?>>
                                            <?=htmlspecialchars($label . ($extra !== '' ? ' (' . $extra . ')' : ''), ENT_QUOTES, 'UTF-8')?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button type="submit" class="session-select">Afficher</button></noscript>
                            </form>

                            <input type="text" id="balanceSearch" class="search-box" placeholder="Rechercher par matricule, account code ou nom...">
                        </div>

                        <div class="balance-table-wrap">
                            <table class="balance-table" id="balanceTable">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">Matricule</th>
                                        <th style="width: 18%;">Account Code</th>
                                        <th style="width: 31%;">Nom complet</th>
                                        <th style="width: 18%;">Solde</th>
                                        <th style="width: 18%;">Etat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    $matricule = trim((string)($student['student_id'] ?? ''));
                                    $accountCode = trim((string)($student['account_code'] ?? ''));
                                    $fullName = trim(strtoupper((string)($student['student_nom'] ?? '')) . ' ' . (string)($student['student_prenom'] ?? ''));

                                    $financeStudentId = '';
                                    $digits = preg_replace('/\D+/', '', $matricule);
                                    $candidateIds = [];
                                    if ($matricule !== '') {
                                        $candidateIds[] = $matricule;
                                    }
                                    if ($digits !== '' && ctype_digit((string)$digits)) {
                                        $candidateIds[] = (string)((int)$digits);
                                    }

                                    $hasFinanceRow = false;
                                    foreach ($candidateIds as $candidateId) {
                                        if (isset($balanceByFinanceId[$candidateId])) {
                                            $hasFinanceRow = true;
                                            $financeStudentId = (string)($financeActualIdByKey[$candidateId] ?? $candidateId);
                                            break;
                                        }
                                    }

                                    $balance = $hasFinanceRow
                                        ? (float)$balanceByFinanceId[$candidateId]
                                        : 0.0;

                                    if ($balance > 0) {
                                        $statusLabel = 'Debitaire';
                                        $statusClass = 'status-debitaire';
                                        $amountClass = 'amount-positive';
                                    } elseif ($balance < 0) {
                                        $statusLabel = 'Creditaire';
                                        $statusClass = 'status-creditaire';
                                        $amountClass = 'amount-negative';
                                    } else {
                                        $statusLabel = 'Solde';
                                        $statusClass = 'status-solde';
                                        $amountClass = 'amount-zero';
                                    }

                                    $balanceText = ($balance > 0 ? '-' : ($balance < 0 ? '+' : '')) . number_format(abs($balance), 0, '', ' ') . ' Ar';
                                    ?>
                                    <tr class="balance-row">
                                        <td><?=htmlspecialchars($matricule, ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars($accountCode !== '' ? $accountCode : '-', ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8')?></td>
                                        <td class="<?=$amountClass?> <?=$hasFinanceRow ? 'balance-editable' : ''?>"
                                            data-editable="<?=$hasFinanceRow ? '1' : '0'?>"
                                            data-session-id="<?= (int)$selectedSessionId ?>"
                                            data-student-id="<?=htmlspecialchars($financeStudentId, ENT_QUOTES, 'UTF-8')?>"
                                            data-balance="<?=htmlspecialchars((string)$balance, ENT_QUOTES, 'UTF-8')?>"
                                            title="<?=$hasFinanceRow ? 'Double-cliquez pour modifier le solde' : 'Ligne finance introuvable pour cette session'?>"><?=htmlspecialchars($balanceText, ENT_QUOTES, 'UTF-8')?></td>
                                        <td><span class="status-badge <?=$statusClass?>" data-status="1"><?=htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8')?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>

<script>
    (function () {
        var APP_BASE_JS = <?=json_encode((string)$app_base)?>;

        /* Branche la recherche et l'édition du solde sur le bloc .balance-card
           affiché. Relancée après chaque changement de session (voir plus bas) :
           le bloc est alors remplacé et ses écouteurs avec lui. */
        function demarrer() {
        var input = document.getElementById('balanceSearch');
        var rows = document.querySelectorAll('#balanceTable tbody .balance-row');

        if (!input || !rows.length) {
            return;
        }

        input.addEventListener('input', function () {
            var term = (input.value || '').toLowerCase().trim();

            rows.forEach(function (row) {
                var text = (row.textContent || '').toLowerCase();
                row.style.display = text.indexOf(term) !== -1 ? '' : 'none';
            });
        });

        function parseBalance(raw) {
            var val = String(raw || '').replace(/\s+/g, '').replace(',', '.');
            var num = Number(val);
            return Number.isFinite(num) ? num : null;
        }

        function formatBalance(balance) {
            var absValue = Math.abs(balance);
            var grouped = absValue.toLocaleString('fr-FR').replace(/\u202f/g, ' ');
            var sign = balance > 0 ? '-' : (balance < 0 ? '+' : '');
            return sign + grouped + ' Ar';
        }

        function applyStatus(cell, balance) {
            cell.classList.remove('amount-positive', 'amount-negative', 'amount-zero');
            var status = cell.parentElement.querySelector('[data-status="1"]');
            if (!status) {
                return;
            }
            status.classList.remove('status-debitaire', 'status-creditaire', 'status-solde');

            if (balance > 0) {
                cell.classList.add('amount-positive');
                status.classList.add('status-debitaire');
                status.textContent = 'Debitaire';
            } else if (balance < 0) {
                cell.classList.add('amount-negative');
                status.classList.add('status-creditaire');
                status.textContent = 'Creditaire';
            } else {
                cell.classList.add('amount-zero');
                status.classList.add('status-solde');
                status.textContent = 'Solde';
            }
        }

        function openEditor(cell) {
            if (cell.dataset.editing === '1') {
                return;
            }

            var sessionId = cell.getAttribute('data-session-id') || '';
            var studentId = cell.getAttribute('data-student-id') || '';
            if (!sessionId || !studentId) {
                return;
            }

            var currentBalance = Number(cell.getAttribute('data-balance') || '0');
            var inputEl = document.createElement('input');
            inputEl.type = 'text';
            inputEl.className = 'balance-input';
            inputEl.value = String(currentBalance).replace('.', ',');

            var originalText = cell.textContent;
            cell.dataset.editing = '1';
            cell.textContent = '';
            cell.appendChild(inputEl);
            inputEl.focus();
            inputEl.select();

            function closeEditor(cancel) {
                cell.dataset.editing = '0';
                if (cancel) {
                    cell.textContent = originalText;
                    return;
                }

                var parsed = parseBalance(inputEl.value);
                if (parsed === null) {
                    cell.textContent = originalText;
                    return;
                }

                var body = new URLSearchParams();
                body.set('session_id', sessionId);
                body.set('student_id', studentId);
                body.set('balance_amount', String(parsed));

                fetch(APP_BASE_JS + '/finance/account-balance/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        throw new Error((payload && payload.message) ? payload.message : 'save_failed');
                    }

                    var saved = Number(payload.balance_amount || 0);
                    cell.setAttribute('data-balance', String(saved));
                    cell.textContent = formatBalance(saved);
                    applyStatus(cell, saved);
                })
                .catch(function () {
                    cell.textContent = originalText;
                    if (window.Toast) { Toast.error('Impossible de mettre à jour le solde.'); } else if (window.console) { console.error('Impossible de mettre à jour le solde.'); }
                });
            }

            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    closeEditor(false);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeEditor(true);
                }
            });

            inputEl.addEventListener('blur', function () {
                closeEditor(false);
            });
        }

        document.querySelectorAll('#balanceTable td[data-editable="1"]').forEach(function (cell) {
            cell.addEventListener('dblclick', function () {
                openEditor(cell);
            });
        });
        }

        /* ---- Changement de session sans rechargement ------------------------
           Le sélecteur soumettait un formulaire : toute la page se rechargeait.
           La page de la session choisie est relue et seul .balance-card est
           remplacé. L'adresse suit (?session_id=…) et précédent / suivant
           ramènent la session correspondante. */
        var chargement = false;

        function changerSession(sessionId, empiler) {
            var carte = document.querySelector('.balance-card');
            if (!carte || chargement) { return; }

            var url = new URL(window.location.href);
            if (sessionId) { url.searchParams.set('session_id', sessionId); }
            else { url.searchParams.delete('session_id'); }

            var recherche = document.getElementById('balanceSearch');
            var terme = recherche ? recherche.value : '';

            chargement = true;
            carte.style.opacity = '.55';
            carte.setAttribute('aria-busy', 'true');

            fetch(url.toString(), { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.text();
                })
                .then(function (html) {
                    var neuve = new DOMParser().parseFromString(html, 'text/html').querySelector('.balance-card');
                    if (!neuve) { throw new Error('bloc introuvable'); }
                    carte.replaceWith(document.importNode(neuve, true));
                    if (empiler) { window.history.pushState({ session_id: sessionId }, '', url.toString()); }
                    demarrer();

                    // La recherche en cours s'applique à la nouvelle session.
                    var nouvelleRecherche = document.getElementById('balanceSearch');
                    if (nouvelleRecherche && terme) {
                        nouvelleRecherche.value = terme;
                        nouvelleRecherche.dispatchEvent(new Event('input'));
                    }
                })
                .catch(function () {
                    window.location.href = url.toString();
                })
                .then(function () {
                    chargement = false;
                    var bloc = document.querySelector('.balance-card');
                    if (bloc) { bloc.style.opacity = ''; bloc.removeAttribute('aria-busy'); }
                });
        }

        document.addEventListener('change', function (event) {
            if (event.target.matches && event.target.matches('.balance-filters select[name="session_id"]')) {
                changerSession(event.target.value, true);
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target.closest ? event.target.closest('.balance-filters form') : null;
            if (!form) { return; }
            event.preventDefault();
            var select = form.querySelector('select[name="session_id"]');
            if (select) { changerSession(select.value, true); }
        });

        window.addEventListener('popstate', function (event) {
            var sid = (event.state && event.state.session_id !== undefined)
                ? String(event.state.session_id)
                : (new URL(window.location.href).searchParams.get('session_id') || '');
            changerSession(sid, false);
        });

        demarrer();
        window.history.replaceState({ session_id: new URL(window.location.href).searchParams.get('session_id') || '' }, '', window.location.href);
    })();
</script>
</body>
</html>
