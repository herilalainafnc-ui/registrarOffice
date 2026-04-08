<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$app_base = defined('APP_BASE') ? APP_BASE : '';
$currentUser = currentUser();
$canUseBulkEmail = isset($currentUser['level']) && (int)$currentUser['level'] <= 3;

if (($_GET['ajax'] ?? '') === 'recipient-search') {
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=UTF-8');

    if (!$canUseBulkEmail) {
        http_response_code(403);
        echo json_encode(['items' => [], 'error' => 'forbidden']);
        exit;
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        echo json_encode(['items' => []]);
        exit;
    }

    $like = '%' . $q . '%';
    $rows = [];

    try {
        $studentStmt = $dtb->prepare("SELECT CONCAT(TRIM(student_nom), ' ', TRIM(student_prenom)) AS full_name, TRIM(student_email) AS email, 'Etudiant' AS source FROM tbl_2024_etudiant WHERE remove != 1 AND student_email IS NOT NULL AND TRIM(student_email) <> '' AND (student_nom LIKE :q OR student_prenom LIKE :q OR student_email LIKE :q) LIMIT 12");
        $studentStmt->execute(['q' => $like]);
        $rows = array_merge($rows, $studentStmt->fetchAll(PDO::FETCH_ASSOC));

        $teacherStmt = $dtb->prepare("SELECT CONCAT(TRIM(lastName), ' ', TRIM(name)) AS full_name, TRIM(email) AS email, 'Professeur' AS source FROM teacher WHERE remove != 1 AND email IS NOT NULL AND TRIM(email) <> '' AND (lastName LIKE :q OR name LIKE :q OR email LIKE :q) LIMIT 12");
        $teacherStmt->execute(['q' => $like]);
        $rows = array_merge($rows, $teacherStmt->fetchAll(PDO::FETCH_ASSOC));

        $userStmt = $dtb->prepare("SELECT CONCAT(TRIM(nom), ' ', TRIM(prenom)) AS full_name, TRIM(mail) AS email, 'Utilisateur' AS source FROM compt_utilisateur WHERE etat = 1 AND mail IS NOT NULL AND TRIM(mail) <> '' AND (nom LIKE :q OR prenom LIKE :q OR mail LIKE :q) LIMIT 12");
        $userStmt->execute(['q' => $like]);
        $rows = array_merge($rows, $userStmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode(['items' => [], 'error' => 'query_failed']);
        exit;
    }
    $items = [];
    $seen = [];

    foreach ($rows as $row) {
        $email = strtolower(trim((string)($row['email'] ?? '')));
        if ($email === '' || isset($seen[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $seen[$email] = true;
        $items[] = [
            'name' => trim((string)($row['full_name'] ?? '')),
            'email' => $email,
            'source' => (string)($row['source'] ?? ''),
        ];
    }

    echo json_encode(['items' => array_slice($items, 0, 25)], JSON_UNESCAPED_UNICODE);
    exit;
}

$bulkEmailResult = [
    'success' => false,
    'message' => '',
    'sent' => 0,
    'failed' => 0,
    'total' => 0
];

$bulkForm = [
    'target_type' => $_POST['target_type'] ?? 'students',
    'subject' => $_POST['subject'] ?? '',
    'message' => $_POST['message'] ?? '',
    'custom_recipients' => $_POST['custom_recipients'] ?? ''
];

if (!function_exists('processBulkEmailSend')) {
    function processBulkEmailSend($dtb, $canUseBulkEmail, $currentUser, $app_base, $bulkForm, $csrfToken) {
        $result = [
            'success' => false,
            'message' => '',
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
        ];

        if (!$canUseBulkEmail) {
            $result['message'] = "Acces refuse pour l'envoi en masse.";
            return $result;
        }

        if (!verify_csrf($csrfToken)) {
            $result['message'] = 'Token CSRF invalide. Rechargez la page.';
            return $result;
        }

        $targetType = trim((string)($bulkForm['target_type'] ?? 'students'));
        $subject = trim((string)$bulkForm['subject']);
        $messageText = trim((string)$bulkForm['message']);
        $customRecipientsRaw = (string)$bulkForm['custom_recipients'];

        if ($subject === '' || $messageText === '') {
            $result['message'] = 'Sujet et message sont obligatoires.';
        } elseif (strlen($subject) > 190) {
            $result['message'] = 'Le sujet est trop long (max 190 caracteres).';
        } else {
            $emails = [];

            if ($targetType === 'students') {
                $stmt = $dtb->query("SELECT DISTINCT TRIM(student_email) AS email FROM tbl_2024_etudiant WHERE remove != 1 AND student_email IS NOT NULL AND TRIM(student_email) <> ''");
                $emails = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            } elseif ($targetType === 'teachers') {
                $stmt = $dtb->query("SELECT DISTINCT TRIM(email) AS email FROM teacher WHERE remove != 1 AND email IS NOT NULL AND TRIM(email) <> ''");
                $emails = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            } elseif ($targetType === 'users') {
                $stmt = $dtb->query("SELECT DISTINCT TRIM(mail) AS email FROM compt_utilisateur WHERE etat = 1 AND mail IS NOT NULL AND TRIM(mail) <> ''");
                $emails = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            } elseif ($targetType === 'custom') {
                $tokens = preg_split('/[\s,;]+/', $customRecipientsRaw);
                $emails = is_array($tokens) ? $tokens : [];
            } else {
                $result['message'] = 'Type de destinataire invalide.';
            }

            if ($result['message'] === '') {
                $validEmails = [];
                foreach ($emails as $email) {
                    $clean = strtolower(trim((string)$email));
                    if ($clean !== '' && filter_var($clean, FILTER_VALIDATE_EMAIL)) {
                        $validEmails[$clean] = true;
                    }
                }

                $recipients = array_keys($validEmails);
                $maxRecipients = 300;

                if (count($recipients) === 0) {
                    $result['message'] = 'Aucun email valide trouve.';
                } elseif (count($recipients) > $maxRecipients) {
                    $result['message'] = 'Trop de destinataires en une fois (max ' . $maxRecipients . ').';
                } else {
                    $safeSubject = str_replace(["\r", "\n"], ' ', $subject);
                    $fromName = 'UAZ Registrar - No Reply';
                    $fromEmail = 'noreply@zurcher.edu.mg';
                    $logoCid = 'uaz_logo_inline';
                    $logoPath = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/file/UAZLogo.png';
                    $logoBinary = is_file($logoPath) ? @file_get_contents($logoPath) : false;
                    $logoInlineTag = $logoBinary !== false
                        ? '<img src="cid:' . $logoCid . '" alt="UAZ" style="height:42px;vertical-align:middle;">'
                        : '<span style="color:#e8f1f8;font-size:18px;font-weight:700;vertical-align:middle;display:inline-block;">UAZ</span>';

                    $safeMessageText = htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8');
                    $htmlMessage = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($safeSubject, ENT_QUOTES, 'UTF-8') . '</title></head><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;">'
                        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:20px 0;background:#f8fafc;">'
                        . '<tr><td align="center">'
                        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">'
                        . '<tr><td style="background:#0a1628;padding:18px 24px;">'
                        . $logoInlineTag
                        . '<span style="color:#e8f1f8;font-size:18px;font-weight:700;margin-left:12px;vertical-align:middle;display:inline-block;">Universite Adventiste Zurcher</span>'
                        . '</td></tr>'
                        . '<tr><td style="padding:24px;color:#0f172a;font-size:15px;line-height:1.6;">'
                        . nl2br($safeMessageText)
                        . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0 12px 0;">'
                        . '<div style="font-size:12px;color:#64748b;">Message automatique, merci de ne pas repondre a cet email.</div>'
                        . '</td></tr>'
                        . '</table>'
                        . '</td></tr>'
                        . '</table>'
                        . '</body></html>';

                    $plainTextMessage = $messageText . "\n\nMessage automatique, merci de ne pas repondre a cet email.";
                    $boundaryMixed = 'mixed_' . md5((string)microtime(true));
                    $boundaryAlt = 'alt_' . md5((string)(microtime(true) . '_alt'));

                    $mailBody = '';
                    $mailBody .= '--' . $boundaryMixed . "\r\n";
                    $mailBody .= 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"' . "\r\n\r\n";

                    $mailBody .= '--' . $boundaryAlt . "\r\n";
                    $mailBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $mailBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                    $mailBody .= $plainTextMessage . "\r\n\r\n";

                    $mailBody .= '--' . $boundaryAlt . "\r\n";
                    $mailBody .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $mailBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                    $mailBody .= $htmlMessage . "\r\n\r\n";
                    $mailBody .= '--' . $boundaryAlt . "--\r\n";

                    if ($logoBinary !== false) {
                        $mailBody .= '--' . $boundaryMixed . "\r\n";
                        $mailBody .= "Content-Type: image/png; name=\"UAZLogo.png\"\r\n";
                        $mailBody .= "Content-Transfer-Encoding: base64\r\n";
                        $mailBody .= 'Content-ID: <' . $logoCid . ">\r\n";
                        $mailBody .= "Content-Disposition: inline; filename=\"UAZLogo.png\"\r\n\r\n";
                        $mailBody .= chunk_split(base64_encode($logoBinary)) . "\r\n";
                    }

                    $mailBody .= '--' . $boundaryMixed . "--\r\n";

                    $headers = [];
                    $headers[] = 'MIME-Version: 1.0';
                    $headers[] = 'Content-Type: multipart/related; boundary="' . $boundaryMixed . '"';
                    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
                    $headers[] = 'Reply-To: ' . $fromEmail;
                    $headers[] = 'Sender: ' . $fromEmail;
                    $headers[] = 'Return-Path: ' . $fromEmail;
                    $headers[] = 'X-Mailer: PHP/' . phpversion();
                    $headersString = implode("\r\n", $headers);
                    $envelopeSender = '-f ' . $fromEmail;

                    $sent = 0;
                    $failed = 0;
                    foreach ($recipients as $recipient) {
                        if (@mail($recipient, $safeSubject, $mailBody, $headersString, $envelopeSender)) {
                            $sent++;
                        } else {
                            $failed++;
                        }
                    }

                    $result['success'] = $sent > 0;
                    $result['sent'] = $sent;
                    $result['failed'] = $failed;
                    $result['total'] = count($recipients);
                    $result['message'] = 'Envoi termine.';
                }
            }
        }

        return $result;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_bulk_email') {
    $bulkEmailResult = processBulkEmailSend(
        $dtb,
        $canUseBulkEmail,
        $currentUser,
        $app_base,
        $bulkForm,
        $_POST['csrf_token'] ?? ''
    );

    $isAjaxSend = (($_GET['ajax'] ?? '') === 'send-bulk-email')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

    if ($isAjaxSend) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($bulkEmailResult, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <!-- REQUEST HEAD --><?php require('../init/head.php');?>
    <title>Email en masse</title>
    <style>
        .gmail-shell { background: #f1f3f4; border: 1px solid #d2d6dc; border-radius: 16px; padding: 16px; }
        .gmail-compose { background: #ffffff; border: 1px solid #d2d6dc; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08); }
        .gmail-head { background: linear-gradient(90deg, #0a1628, #112a4b); color: #e8f1f8; padding: 14px 18px; }
        .gmail-row { border-bottom: 1px solid #e5e7eb; padding: 10px 14px; background: #fff; }
        .gmail-row:last-child { border-bottom: 0; }
        .gmail-input { width: 100%; border: none; outline: none; background: transparent; color: #0f172a; font-size: 14px; }
        .gmail-textarea { width: 100%; min-height: 240px; resize: vertical; border: none; outline: none; color: #0f172a; font-size: 14px; line-height: 1.6; }
        .gmail-side { background: #fff; border: 1px solid #d2d6dc; border-radius: 14px; padding: 12px; }
        .gmail-status { display: none; border-radius: 10px; border: 1px solid transparent; padding: 10px 12px; font-size: 13px; margin-bottom: 12px; }
        .gmail-status.success { display: block; border-color: #10b981; background: #ecfdf5; color: #065f46; }
        .gmail-status.error { display: block; border-color: #ef4444; background: #fef2f2; color: #991b1b; }
        .gmail-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; border: 1px solid #9ca3af; background: #f3f4f6; color: #111827; font-size: 12px; }
        .gmail-chip button { border: none; background: transparent; color: #4b5563; cursor: pointer; font-size: 12px; }
        .gmail-results { border: 1px solid #d1d5db; border-radius: 10px; background: #fff; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12); }
        .gmail-results button { border-bottom: 1px solid #eef2f7; }
        .gmail-results button:last-child { border-bottom: 0; }
    </style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
    <div class="h-screen w-full <?=$bg_three_color?>">

        <!-- TOP BAR --><?php require('../init/topbar.php');?>
        <!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
        <!-- NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

        <div class="w-full flex flex-col lg:flex-row">

            <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

            <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

                <div class="back flex-1 overflow-y-auto p-4">
                    <div class="max-w-6xl mx-auto gmail-shell">
                        <div class="flex items-center justify-between mb-3">
                            <h1 class="text-base md:text-lg font-semibold text-slate-800"><i class="bi bi-send-check"></i>&nbsp; Composeur Email UAZ</h1>
                            <a href="<?=$app_base?>/settings" class="text-sm text-blue-700 hover:text-blue-600">Retour aux parametres</a>
                        </div>

                        <div id="mailStatus" class="gmail-status"></div>

                        <form method="post" action="<?=$app_base?>/settings/bulk-email" class="grid gap-4 lg:grid-cols-12" id="bulkMailForm">
                            <input type="hidden" name="action" value="send_bulk_email" />
                            <input type="hidden" name="csrf_token" value="<?=csrf_token()?>" />

                            <div class="lg:col-span-9 gmail-compose">
                                <div class="gmail-head font-semibold">Nouveau message</div>

                                <div class="gmail-row">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-slate-500 w-10">A</span>
                                        <input type="text" id="recipientSearch" class="gmail-input" placeholder="Rechercher un nom ou un email" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> autocomplete="off" />
                                    </div>
                                    <div class="relative mt-2">
                                        <div id="recipientResults" class="hidden gmail-results absolute left-0 right-0 mt-1 z-50 max-h-64 overflow-auto"></div>
                                    </div>
                                    <div id="selectedRecipients" class="mt-2 flex flex-wrap gap-2"></div>
                                </div>

                                <div class="gmail-row">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-slate-500 w-10">Objet</span>
                                        <input type="text" name="subject" maxlength="190" value="<?=e($bulkForm['subject'])?>" class="gmail-input" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> required />
                                    </div>
                                </div>

                                <div class="gmail-row">
                                    <textarea name="message" class="gmail-textarea" placeholder="Ecrivez votre message..." <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> required><?=e($bulkForm['message'])?></textarea>
                                </div>
                            </div>

                            <div class="lg:col-span-3 gmail-side">
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Destinataires</label>
                                <select id="targetType" name="target_type" class="w-full rounded-md border border-slate-300 bg-white text-slate-700 px-3 py-2 text-sm" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                                    <option value="students" <?php if($bulkForm['target_type']==='students'){ echo 'selected'; } ?>>Tous les etudiants</option>
                                    <option value="teachers" <?php if($bulkForm['target_type']==='teachers'){ echo 'selected'; } ?>>Tous les professeurs</option>
                                    <option value="users" <?php if($bulkForm['target_type']==='users'){ echo 'selected'; } ?>>Tous les utilisateurs</option>
                                    <option value="custom" <?php if($bulkForm['target_type']==='custom'){ echo 'selected'; } ?>>Liste manuelle</option>
                                </select>

                                <label class="block text-xs font-semibold text-slate-500 mt-3 mb-1">Emails manuels</label>
                                <textarea id="customRecipients" name="custom_recipients" rows="8" class="w-full rounded-md border border-slate-300 bg-white text-slate-700 px-3 py-2 text-xs" placeholder="adresse1@domaine.com&#10;adresse2@domaine.com" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>><?=e($bulkForm['custom_recipients'])?></textarea>

                                <p class="text-[11px] text-slate-500 mt-2">Clique sur un resultat pour l'ajouter comme destinataire.</p>

                                <button id="sendBtn" type="submit" class="mt-4 w-full px-4 py-2.5 rounded-full bg-blue-600 hover:bg-blue-500 text-white font-medium transition" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                                    <i class="bi bi-send-fill"></i> Envoyer
                                </button>

                                <div id="sendingState" class="hidden mt-2 text-xs text-slate-500">Envoi en cours...</div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php require('../init/footer.php'); ?>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    (function () {
        const form = document.getElementById('bulkMailForm');
        const searchInput = document.getElementById('recipientSearch');
        const resultsBox = document.getElementById('recipientResults');
        const chipsBox = document.getElementById('selectedRecipients');
        const customRecipients = document.getElementById('customRecipients');
        const targetType = document.getElementById('targetType');
        const sendBtn = document.getElementById('sendBtn');
        const sendingState = document.getElementById('sendingState');
        const mailStatus = document.getElementById('mailStatus');

        if (!form || !searchInput || !resultsBox || !chipsBox || !customRecipients || !targetType) {
            return;
        }

        const selected = {};
        let debounceTimer = null;

        function setCustomMode() {
            if (targetType.value !== 'custom') {
                targetType.value = 'custom';
            }
        }

        function syncTextarea() {
            customRecipients.value = Object.keys(selected).join('\n');
        }

        function renderChips() {
            chipsBox.innerHTML = '';
            Object.keys(selected).forEach(function (email) {
                const item = selected[email];
                const chip = document.createElement('span');
                chip.className = 'gmail-chip';
                chip.innerHTML = '<span>' + item.name + ' &lt;' + item.email + '&gt;</span><button type="button" data-email="' + item.email + '" class="text-cyan-300 hover:text-white">x</button>';
                chipsBox.appendChild(chip);
            });

            chipsBox.querySelectorAll('button[data-email]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    delete selected[this.getAttribute('data-email')];
                    syncTextarea();
                    renderChips();
                });
            });
        }

        function addRecipient(item) {
            const email = (item.email || '').toLowerCase().trim();
            if (!email) return;
            selected[email] = {
                name: (item.name || item.email || '').trim(),
                email: email,
                source: item.source || ''
            };
            setCustomMode();
            syncTextarea();
            renderChips();
            searchInput.value = '';
            resultsBox.innerHTML = '';
            resultsBox.classList.add('hidden');
        }

        function parseTextareaRecipients() {
            const raw = customRecipients.value || '';
            const parts = raw.split(/[\s,;]+/);
            Object.keys(selected).forEach(function (k) { delete selected[k]; });
            parts.forEach(function (token) {
                const email = token.toLowerCase().trim();
                if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    selected[email] = { name: email, email: email, source: 'Manuel' };
                }
            });
            syncTextarea();
            renderChips();
        }

        function renderResults(items) {
            resultsBox.innerHTML = '';

            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-xs text-slate-400';
                empty.textContent = 'Aucun resultat';
                resultsBox.appendChild(empty);
                resultsBox.classList.remove('hidden');
                return;
            }

            items.forEach(function (item) {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'w-full text-left px-3 py-2 hover:bg-slate-100';
                row.innerHTML =
                    '<div class="text-sm text-slate-800">' + (item.name || item.email) + '</div>' +
                    '<div class="text-xs text-slate-500">' + item.email + ' - ' + (item.source || '') + '</div>';
                row.addEventListener('click', function () {
                    addRecipient(item);
                });
                resultsBox.appendChild(row);
            });

            resultsBox.classList.remove('hidden');
        }

        function fetchRecipients(term) {
            const url = APP_BASE + '/settings/bulk-email?ajax=recipient-search&q=' + encodeURIComponent(term);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    return res.text().then(function (text) {
                        let payload = null;
                        try {
                            payload = JSON.parse(text);
                        } catch (e) {
                            throw new Error('invalid_json');
                        }

                        if (!res.ok) {
                            throw new Error(payload.error || 'http_' + res.status);
                        }

                        return payload;
                    });
                })
                .then(function (data) {
                    renderResults(Array.isArray(data.items) ? data.items : []);
                })
                .catch(function (err) {
                    let message = 'Erreur de recherche';
                    if (err && err.message === 'forbidden') {
                        message = 'Acces refuse';
                    } else if (err && err.message === 'query_failed') {
                        message = 'Erreur base de donnees';
                    } else if (err && err.message === 'invalid_json') {
                        message = 'Reponse serveur invalide';
                    }
                    if (typeof Toast !== 'undefined' && typeof Toast.error === 'function') {
                        Toast.error(message);
                    } else {
                        resultsBox.innerHTML = '<div class="px-3 py-2 text-xs text-rose-300">' + message + '</div>';
                        resultsBox.classList.remove('hidden');
                    }
                });
        }

        searchInput.addEventListener('input', function () {
            const term = this.value.trim();
            clearTimeout(debounceTimer);

            if (term.length < 2) {
                resultsBox.innerHTML = '';
                resultsBox.classList.add('hidden');
                return;
            }

            debounceTimer = setTimeout(function () {
                fetchRecipients(term);
            }, 220);
        });

        document.addEventListener('click', function (event) {
            if (!resultsBox.contains(event.target) && event.target !== searchInput) {
                resultsBox.classList.add('hidden');
            }
        });

        customRecipients.addEventListener('blur', function () {
            parseTextareaRecipients();
        });

        function showStatus(result) {
            const ok = !!result.success;

            let message = (result.message || (ok ? 'Envoi termine.' : 'Echec de l\'envoi.'));
            if ((result.total || 0) > 0) {
                message += ' Total: ' + result.total + ' | Envoyes: ' + (result.sent || 0) + ' | Echecs: ' + (result.failed || 0);
            }

            if (typeof Toast !== 'undefined') {
                if (ok && typeof Toast.success === 'function') {
                    Toast.success(message);
                    return;
                }
                if (!ok && typeof Toast.error === 'function') {
                    Toast.error(message);
                    return;
                }
            }

            if (mailStatus) {
                mailStatus.className = 'gmail-status ' + (ok ? 'success' : 'error');
                mailStatus.innerHTML = '<b>' + message + '</b>';
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const payload = new FormData(form);
            const url = form.getAttribute('action') + '?ajax=send-bulk-email';
            let loadingToastId = null;

            if (sendBtn) {
                sendBtn.disabled = true;
            }
            if (sendingState) {
                sendingState.classList.remove('hidden');
            }
            if (typeof Toast !== 'undefined' && typeof Toast.loading === 'function') {
                loadingToastId = Toast.loading('Envoi en cours...');
            }

            fetch(url, {
                method: 'POST',
                body: payload,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) {
                    return res.text().then(function (text) {
                        let json = null;
                        try {
                            json = JSON.parse(text);
                        } catch (err) {
                            throw new Error('invalid_json');
                        }
                        if (!res.ok) {
                            throw new Error(json.error || 'http_' + res.status);
                        }
                        return json;
                    });
                })
                .then(function (result) {
                    showStatus(result);
                })
                .catch(function () {
                    showStatus({ success: false, message: 'Erreur reseau pendant l\'envoi.' });
                })
                .finally(function () {
                    if (loadingToastId && typeof Toast !== 'undefined' && typeof Toast.dismiss === 'function') {
                        Toast.dismiss(loadingToastId);
                    }
                    if (sendBtn) {
                        sendBtn.disabled = false;
                    }
                    if (sendingState) {
                        sendingState.classList.add('hidden');
                    }
                });
        });

        <?php if ($bulkEmailResult['message'] !== '') { ?>
        showStatus(<?=json_encode($bulkEmailResult, JSON_UNESCAPED_UNICODE)?>);
        <?php } ?>

        parseTextareaRecipients();
    })();
    </script>
</body>
</html>
