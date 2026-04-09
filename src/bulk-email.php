<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$app_base = defined('APP_BASE') ? APP_BASE : '';
$currentUser = currentUser();
$canUseBulkEmail = isset($currentUser['level']) && (int)$currentUser['level'] <= 3;

if (($_GET['ajax'] ?? '') === 'send-bulk-email') {
    @ini_set('display_errors', '0');
}

$activeSessionId = 0;
try {
    $activeSessionStmt = $dtb->query("SELECT session_id FROM t_2023_session WHERE selected = 1 ORDER BY session_id DESC LIMIT 1");
    $activeSessionId = (int)($activeSessionStmt ? $activeSessionStmt->fetchColumn() : 0);
} catch (Throwable $e) {
    $activeSessionId = 0;
}

$mentionOptions = [];
$levelOptions = [];
try {
    if ($activeSessionId > 0) {
        $mentionStmt = $dtb->prepare("SELECT DISTINCT TRIM(ins.etude_mention) AS mention FROM t_2024_inscription_session ins WHERE ins.session_id = :sid AND ins.etude_mention IS NOT NULL AND TRIM(ins.etude_mention) <> '' ORDER BY mention ASC");
        $mentionStmt->execute(['sid' => $activeSessionId]);
        $mentionOptions = $mentionStmt->fetchAll(PDO::FETCH_COLUMN);

        $levelStmt = $dtb->prepare("SELECT DISTINCT ins.niveau_std AS level_value FROM t_2024_inscription_session ins WHERE ins.session_id = :sid AND ins.niveau_std IS NOT NULL AND ins.niveau_std > 0 ORDER BY ins.niveau_std ASC");
        $levelStmt->execute(['sid' => $activeSessionId]);
        $levelOptions = $levelStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Throwable $e) {
    $mentionOptions = [];
    $levelOptions = [];
}

$postedMentionFilters = [];
if (isset($_POST['mention_filters']) && is_array($_POST['mention_filters'])) {
    foreach ($_POST['mention_filters'] as $mentionValue) {
        $mentionValue = trim((string)$mentionValue);
        if ($mentionValue !== '' && !in_array($mentionValue, $postedMentionFilters, true)) {
            $postedMentionFilters[] = $mentionValue;
        }
    }
}

$currentBox = strtolower(trim((string)($_GET['box'] ?? 'compose')));
$allowedBoxes = ['compose', 'inbox', 'sent', 'drafts', 'favorites', 'trash'];
if (!in_array($currentBox, $allowedBoxes, true)) {
    $currentBox = 'compose';
}

if (!function_exists('ensureBulkMailboxTable')) {
    function ensureBulkMailboxTable($dtb) {
        static $ready = false;
        if ($ready) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS t_bulk_email_mailbox (
                    mailbox_id INT AUTO_INCREMENT PRIMARY KEY,
                    mailbox_folder VARCHAR(16) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    preview_text TEXT NULL,
                    preview_image LONGTEXT NULL,
                    message_html MEDIUMTEXT NULL,
                    recipient_total INT NOT NULL DEFAULT 0,
                    sent_total INT NOT NULL DEFAULT 0,
                    failed_total INT NOT NULL DEFAULT 0,
                    created_by VARCHAR(160) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_mailbox_folder_date (mailbox_folder, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $dtb->exec($sql);

            // Backward compatibility for already existing table.
            try {
                $dtb->exec("ALTER TABLE t_bulk_email_mailbox ADD COLUMN preview_image LONGTEXT NULL AFTER preview_text");
            } catch (Throwable $ignored) {
            }
            try {
                $dtb->exec("ALTER TABLE t_bulk_email_mailbox ADD COLUMN message_html MEDIUMTEXT NULL AFTER preview_image");
            } catch (Throwable $ignored) {
            }

            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
}

if (!function_exists('bulkMailboxInsert')) {
    function bulkMailboxInsert($dtb, $folder, $subject, $preview, $recipientTotal, $sentTotal, $failedTotal, $createdBy, $messageHtml = '', $previewImage = '') {
        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare('INSERT INTO t_bulk_email_mailbox (mailbox_folder, subject, preview_text, preview_image, message_html, recipient_total, sent_total, failed_total, created_by) VALUES (:folder, :subject, :preview_text, :preview_image, :message_html, :recipient_total, :sent_total, :failed_total, :created_by)');
            $stmt->execute([
                'folder' => substr(trim((string)$folder), 0, 16),
                'subject' => substr(trim((string)$subject), 0, 255),
                'preview_text' => substr(trim((string)$preview), 0, 5000),
                'preview_image' => substr((string)$previewImage, 0, 1000000),
                'message_html' => substr((string)$messageHtml, 0, 5000000),
                'recipient_total' => (int)$recipientTotal,
                'sent_total' => (int)$sentTotal,
                'failed_total' => (int)$failedTotal,
                'created_by' => substr(trim((string)$createdBy), 0, 160),
            ]);
        } catch (Throwable $e) {
            // Keep email sending functional even if mailbox logging fails.
        }
    }
}

if (!function_exists('bulkMailboxFetchByFolder')) {
    function bulkMailboxFetchByFolder($dtb, $folder, $limit = 40) {
        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare('SELECT mailbox_id, subject, preview_text, preview_image, message_html, recipient_total, sent_total, failed_total, created_by, created_at FROM t_bulk_email_mailbox WHERE mailbox_folder = :folder ORDER BY mailbox_id DESC LIMIT :limit');
            $stmt->bindValue(':folder', (string)$folder, PDO::PARAM_STR);
            $stmt->bindValue(':limit', max(1, min(200, (int)$limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('bulkMailboxCountByFolder')) {
    function bulkMailboxCountByFolder($dtb) {
        $counts = ['inbox' => 0, 'sent' => 0];
        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->query("SELECT mailbox_folder, COUNT(*) AS total FROM t_bulk_email_mailbox WHERE mailbox_folder IN ('inbox', 'sent') GROUP BY mailbox_folder");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $folder = (string)($row['mailbox_folder'] ?? '');
                if (isset($counts[$folder])) {
                    $counts[$folder] = (int)($row['total'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            $counts = ['inbox' => 0, 'sent' => 0];
        }
        return $counts;
    }
}

if (!function_exists('isExternalImapMailboxEnabled')) {
    function isExternalImapMailboxEnabled() {
        if (!function_exists('imap_open')) {
            return false;
        }

        if (!defined('MAILBOX_IMAP_ENABLED') || MAILBOX_IMAP_ENABLED !== true) {
            return false;
        }

        $host = defined('MAILBOX_IMAP_HOST') ? trim((string)MAILBOX_IMAP_HOST) : '';
        $username = defined('MAILBOX_IMAP_USERNAME') ? trim((string)MAILBOX_IMAP_USERNAME) : '';
        $password = defined('MAILBOX_IMAP_PASSWORD') ? (string)MAILBOX_IMAP_PASSWORD : '';

        return ($host !== '' && $username !== '' && $password !== '');
    }
}

if (!function_exists('decodeImapSubject')) {
    function decodeImapSubject($value) {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        if (function_exists('imap_utf8')) {
            $decoded = @imap_utf8($value);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $value;
    }
}

if (!function_exists('openImapFolder')) {
    function openImapFolder($folderName) {
        $host = defined('MAILBOX_IMAP_HOST') ? trim((string)MAILBOX_IMAP_HOST) : '';
        $port = defined('MAILBOX_IMAP_PORT') ? (int)MAILBOX_IMAP_PORT : 993;
        $encryption = defined('MAILBOX_IMAP_ENCRYPTION') ? strtolower(trim((string)MAILBOX_IMAP_ENCRYPTION)) : 'ssl';
        $username = defined('MAILBOX_IMAP_USERNAME') ? (string)MAILBOX_IMAP_USERNAME : '';
        $password = defined('MAILBOX_IMAP_PASSWORD') ? (string)MAILBOX_IMAP_PASSWORD : '';

        if ($host === '' || $username === '' || $password === '') {
            return false;
        }

        $flags = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        $mailboxPath = '{' . $host . ':' . $port . $flags . '}';
        return @imap_open($mailboxPath . $folderName, $username, $password);
    }
}

if (!function_exists('imapPartCharset')) {
    function imapPartCharset($part) {
        if (!is_object($part)) {
            return '';
        }

        $parameterSets = [];
        if (isset($part->parameters) && is_array($part->parameters)) {
            $parameterSets[] = $part->parameters;
        }
        if (isset($part->dparameters) && is_array($part->dparameters)) {
            $parameterSets[] = $part->dparameters;
        }

        foreach ($parameterSets as $params) {
            foreach ($params as $param) {
                $attr = strtolower(trim((string)($param->attribute ?? '')));
                if ($attr === 'charset') {
                    return trim((string)($param->value ?? ''));
                }
            }
        }

        return '';
    }
}

if (!function_exists('decodeImapBodyText')) {
    function decodeImapBodyText($rawBody, $encoding, $charset = '') {
        $rawBody = (string)$rawBody;
        $encoding = (int)$encoding;
        $charset = trim((string)$charset);

        if ($encoding === 3) {
            $decoded = base64_decode($rawBody, true);
            if ($decoded !== false) {
                $rawBody = $decoded;
            }
        } elseif ($encoding === 4) {
            $rawBody = quoted_printable_decode($rawBody);
        }

        if ($charset !== '') {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($rawBody, 'UTF-8', $charset);
                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
            if (function_exists('iconv')) {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $rawBody);
                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
        }

        return $rawBody;
    }
}

if (!function_exists('flattenImapTextParts')) {
    function flattenImapTextParts($structure, $prefix = '') {
        $parts = [];
        if (!is_object($structure)) {
            return $parts;
        }

        if (isset($structure->parts) && is_array($structure->parts) && count($structure->parts) > 0) {
            foreach ($structure->parts as $idx => $subPart) {
                $partNo = $prefix === '' ? (string)($idx + 1) : ($prefix . '.' . ($idx + 1));
                $parts = array_merge($parts, flattenImapTextParts($subPart, $partNo));
            }
            return $parts;
        }

        $type = (int)($structure->type ?? -1);
        $subtype = strtoupper(trim((string)($structure->subtype ?? '')));
        if ($type === 0 && ($subtype === 'PLAIN' || $subtype === 'HTML')) {
            $parts[] = [
                'part_no' => $prefix === '' ? '1' : $prefix,
                'subtype' => $subtype,
                'encoding' => (int)($structure->encoding ?? 0),
                'charset' => imapPartCharset($structure),
            ];
        }

        return $parts;
    }
}

if (!function_exists('fetchImapReadableBody')) {
    function fetchImapReadableBody($imap, $msgNo) {
        $msgNo = (int)$msgNo;
        if ($msgNo <= 0) {
            return ['html' => '', 'text' => ''];
        }

        $structure = @imap_fetchstructure($imap, $msgNo);
        $parts = flattenImapTextParts($structure);

        $htmlBody = '';
        $plainBody = '';

        foreach ($parts as $partInfo) {
            $partNo = (string)($partInfo['part_no'] ?? '1');
            $raw = (string)@imap_fetchbody($imap, $msgNo, $partNo, FT_PEEK);
            if ($raw === '') {
                continue;
            }

            $decoded = decodeImapBodyText(
                $raw,
                (int)($partInfo['encoding'] ?? 0),
                (string)($partInfo['charset'] ?? '')
            );

            if (strtoupper((string)($partInfo['subtype'] ?? '')) === 'HTML') {
                if ($htmlBody === '') {
                    $htmlBody = trim((string)$decoded);
                }
            } else {
                if ($plainBody === '') {
                    $plainBody = trim((string)$decoded);
                }
            }
        }

        if ($htmlBody === '' && $plainBody === '') {
            $raw = (string)@imap_body($imap, $msgNo, FT_PEEK);
            if ($raw !== '') {
                $plainBody = trim((string)decodeImapBodyText($raw, 0, ''));
            }
        }

        return ['html' => $htmlBody, 'text' => $plainBody];
    }
}

if (!function_exists('fetchImapMailboxCount')) {
    function fetchImapMailboxCount($folderName) {
        $imap = openImapFolder($folderName);
        if ($imap === false) {
            return ['count' => 0, 'error' => 'imap_open_failed'];
        }

        $total = (int)@imap_num_msg($imap);
        @imap_close($imap);

        return ['count' => max(0, $total), 'error' => ''];
    }
}

if (!function_exists('fetchImapMailboxItems')) {
    function fetchImapMailboxItems($folderName, $limit = 25) {
        $imap = openImapFolder($folderName);
        if ($imap === false) {
            return ['items' => [], 'count' => 0, 'error' => 'imap_open_failed'];
        }

        $total = (int)@imap_num_msg($imap);
        if ($total <= 0) {
            @imap_close($imap);
            return ['items' => [], 'count' => 0, 'error' => ''];
        }

        $sorted = @imap_sort($imap, SORTDATE, 1);
        if (!is_array($sorted)) {
            $sorted = range($total, 1);
        }

        $messageNos = array_slice($sorted, 0, max(1, min(200, (int)$limit)));
        $items = [];

        foreach ($messageNos as $msgNo) {
            $overviewList = @imap_fetch_overview($imap, (string)$msgNo, 0);
            $overview = (is_array($overviewList) && isset($overviewList[0])) ? $overviewList[0] : null;

            $subject = '';
            $from = 'Externe';
            $dateRaw = '';
            if ($overview) {
                $subject = decodeImapSubject((string)($overview->subject ?? ''));
                $from = decodeImapSubject((string)($overview->from ?? 'Externe'));
                $dateRaw = (string)($overview->date ?? '');
            }

            if ($subject === '') {
                $subject = '(Sans objet)';
            }
            $uid = (int)@imap_uid($imap, (int)$msgNo);
            $previewShort = 'Cliquez pour ouvrir le contenu du message.';

            $items[] = [
                'subject' => $subject,
                'preview_text' => $previewShort,
                'created_by' => $from,
                'created_at' => $dateRaw,
                'message_id' => $uid > 0 ? $uid : (int)$msgNo,
                'recipient_total' => 0,
                'sent_total' => 0,
                'failed_total' => 0,
            ];
        }

        @imap_close($imap);
        return ['items' => $items, 'count' => $total, 'error' => ''];
    }
}

if (!function_exists('fetchImapMessageDetail')) {
    function fetchImapMessageDetail($box, $messageId) {
        $box = strtolower(trim((string)$box));
        $messageId = (int)$messageId;
        if ($messageId <= 0) {
            return ['success' => false, 'error' => 'invalid_id'];
        }

        $folderMap = [
            'inbox' => (defined('MAILBOX_IMAP_INBOX_FOLDER') ? (string)MAILBOX_IMAP_INBOX_FOLDER : 'INBOX'),
            'sent' => (defined('MAILBOX_IMAP_SENT_FOLDER') ? (string)MAILBOX_IMAP_SENT_FOLDER : '[Gmail]/Sent Mail'),
        ];
        if (!isset($folderMap[$box])) {
            return ['success' => false, 'error' => 'invalid_box'];
        }

        $imap = openImapFolder($folderMap[$box]);
        if ($imap === false) {
            return ['success' => false, 'error' => 'imap_open_failed'];
        }

        $msgNo = (int)@imap_msgno($imap, (int)$messageId);
        if ($msgNo <= 0) {
            $msgNo = (int)$messageId;
        }

        $overviewList = @imap_fetch_overview($imap, (string)$msgNo, 0);
        $overview = (is_array($overviewList) && isset($overviewList[0])) ? $overviewList[0] : null;

        $subject = decodeImapSubject((string)($overview->subject ?? '(Sans objet)'));
        $from = decodeImapSubject((string)($overview->from ?? 'Externe'));
        $dateRaw = (string)($overview->date ?? '');

        $bodyData = fetchImapReadableBody($imap, $msgNo);

        @imap_close($imap);

        $htmlBody = trim((string)($bodyData['html'] ?? ''));
        $plainBody = trim((string)($bodyData['text'] ?? ''));
        $safeBody = '';
        if ($htmlBody !== '') {
            $safeBody = $htmlBody;
        } elseif ($plainBody !== '') {
            $safeBody = nl2br(htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8'));
        } else {
            $safeBody = '<em>Contenu indisponible.</em>';
        }

        return [
            'success' => true,
            'subject' => $subject,
            'from' => $from,
            'created_at' => $dateRaw,
            'html' => '<div style="white-space:pre-wrap;line-height:1.6;">' . $safeBody . '</div>',
        ];
    }
}

if (!function_exists('fetchLocalMailboxMessageDetail')) {
    function fetchLocalMailboxMessageDetail($dtb, $messageId) {
        $messageId = (int)$messageId;
        if ($messageId <= 0) {
            return ['success' => false, 'error' => 'invalid_id'];
        }

        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare('SELECT subject, preview_text, preview_image, message_html, created_by, created_at, recipient_total, sent_total, failed_total FROM t_bulk_email_mailbox WHERE mailbox_id = :id LIMIT 1');
            $stmt->execute(['id' => $messageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['success' => false, 'error' => 'not_found'];
            }

            $subject = (string)($row['subject'] ?? 'Sans objet');
            $from = (string)($row['created_by'] ?? 'Systeme');
            $createdAt = (string)($row['created_at'] ?? '');
            $preview = (string)($row['preview_text'] ?? '');
            $stats = 'Total: ' . (int)($row['recipient_total'] ?? 0)
                . ' | Envoyes: ' . (int)($row['sent_total'] ?? 0)
                . ' | Echecs: ' . (int)($row['failed_total'] ?? 0);

            $content = $preview !== '' ? $preview : 'Contenu indisponible.';
            $storedHtml = trim((string)($row['message_html'] ?? ''));
            if ($storedHtml !== '') {
                $safeBody = $storedHtml;
            } else {
                $safeBody = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
            }

            return [
                'success' => true,
                'subject' => $subject,
                'from' => $from,
                'created_at' => $createdAt,
                'html' => '<div style="white-space:pre-wrap;line-height:1.6;">' . $safeBody . '<br><br><strong>' . htmlspecialchars($stats, ENT_QUOTES, 'UTF-8') . '</strong></div>',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'query_failed'];
        }
    }
}

if (!function_exists('buildMailboxPreviewDataUri')) {
    function buildMailboxPreviewDataUri($binary, $mime) {
        $binary = (string)$binary;
        $mime = trim((string)$mime);
        if ($binary === '' || $mime === '' || strpos($mime, 'image/') !== 0) {
            return '';
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $img = @imagecreatefromstring($binary);
            if ($img !== false) {
                $srcW = imagesx($img);
                $srcH = imagesy($img);
                $maxW = 220;
                $maxH = 130;
                $ratio = min($maxW / max(1, $srcW), $maxH / max(1, $srcH), 1);
                $newW = max(1, (int)floor($srcW * $ratio));
                $newH = max(1, (int)floor($srcH * $ratio));

                $tmp = imagecreatetruecolor($newW, $newH);
                if ($tmp !== false) {
                    imagealphablending($tmp, true);
                    imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
                    ob_start();
                    imagejpeg($tmp, null, 78);
                    $jpeg = ob_get_clean();
                    imagedestroy($tmp);
                    imagedestroy($img);
                    if ($jpeg !== false && $jpeg !== '') {
                        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
                    }
                }
                imagedestroy($img);
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }
}

$mailboxCounts = ['inbox' => 0, 'sent' => 0];
$inboxMessages = [];
$sentMessages = [];
$mailboxSource = 'local';
$mailboxWarning = '';

if (!function_exists('getMailboxViewPayload')) {
    function getMailboxViewPayload($dtb, $box, $canUseBulkEmail) {
        $box = strtolower(trim((string)$box));
        $validBoxes = ['compose', 'inbox', 'sent', 'drafts', 'favorites', 'trash'];
        if (!in_array($box, $validBoxes, true)) {
            $box = 'compose';
        }

        $titleMap = [
            'compose' => 'Nouveau message',
            'inbox' => 'Boite de reception',
            'sent' => 'Messages envoyes',
            'drafts' => 'Brouillons',
            'favorites' => 'Favoris',
            'trash' => 'Corbeille',
        ];

        $payload = [
            'box' => $box,
            'title' => $titleMap[$box],
            'items' => [],
            'counts' => ['inbox' => 0, 'sent' => 0],
            'source' => 'local',
            'warning' => '',
        ];

        if (!$canUseBulkEmail) {
            return $payload;
        }

        if (defined('MAILBOX_IMAP_ENABLED') && MAILBOX_IMAP_ENABLED === true && !function_exists('imap_open')) {
            $payload['source'] = 'local';
            $payload['warning'] = 'Extension PHP IMAP non activee sur le serveur.';
        }

        if (isExternalImapMailboxEnabled()) {
            $payload['source'] = 'imap';

            $imapInboxFolder = defined('MAILBOX_IMAP_INBOX_FOLDER') ? (string)MAILBOX_IMAP_INBOX_FOLDER : 'INBOX';
            $imapSentFolder = defined('MAILBOX_IMAP_SENT_FOLDER') ? (string)MAILBOX_IMAP_SENT_FOLDER : '[Gmail]/Sent Mail';

            $inboxCountResult = fetchImapMailboxCount($imapInboxFolder);
            $sentCountResult = fetchImapMailboxCount($imapSentFolder);

            $inboxCount = (int)($inboxCountResult['count'] ?? 0);
            $sentCount = (int)($sentCountResult['count'] ?? 0);

            $selectedResult = ['items' => [], 'count' => 0, 'error' => ''];
            if ($box === 'inbox') {
                $selectedResult = fetchImapMailboxItems($imapInboxFolder, 25);
                $inboxCount = (int)($selectedResult['count'] ?? $inboxCount);
            } elseif ($box === 'sent') {
                $selectedResult = fetchImapMailboxItems($imapSentFolder, 25);
                $sentCount = (int)($selectedResult['count'] ?? $sentCount);
            }

            if (($inboxCountResult['error'] ?? '') !== '' && ($sentCountResult['error'] ?? '') !== '' && ($selectedResult['error'] ?? '') !== '') {
                $payload['source'] = 'local';
                $payload['warning'] = 'Connexion IMAP indisponible. Affichage de la boite locale.';
            } else {
                $payload['counts'] = [
                    'inbox' => $inboxCount,
                    'sent' => $sentCount,
                ];

                if ($box === 'inbox') {
                    $payload['items'] = (array)($selectedResult['items'] ?? []);
                } elseif ($box === 'sent') {
                    $payload['items'] = (array)($selectedResult['items'] ?? []);
                }

                if (($selectedResult['error'] ?? '') !== '' && in_array($box, ['inbox', 'sent'], true)) {
                    $payload['warning'] = 'Impossible de lire certains messages IMAP. Reessayez.';
                }

                return $payload;
            }
        }

        $payload['counts'] = bulkMailboxCountByFolder($dtb);
        if ($box === 'inbox') {
            $payload['items'] = bulkMailboxFetchByFolder($dtb, 'inbox', 60);
        } elseif ($box === 'sent') {
            $payload['items'] = bulkMailboxFetchByFolder($dtb, 'sent', 60);
        }

        return $payload;
    }
}

if ($canUseBulkEmail) {
    $mailboxPayload = getMailboxViewPayload($dtb, $currentBox, $canUseBulkEmail);
    $mailboxCounts = (array)($mailboxPayload['counts'] ?? ['inbox' => 0, 'sent' => 0]);
    $mailboxSource = (string)($mailboxPayload['source'] ?? 'local');
    $mailboxWarning = (string)($mailboxPayload['warning'] ?? '');
    if ($currentBox === 'inbox') {
        $inboxMessages = (array)($mailboxPayload['items'] ?? []);
    } elseif ($currentBox === 'sent') {
        $sentMessages = (array)($mailboxPayload['items'] ?? []);
    }
}

if (!function_exists('collectBulkRecipientEmails')) {
    function collectBulkRecipientEmails($dtb, $activeSessionId, $targetType, $customRecipientsRaw = '', $mentionFilters = [], $levelFilter = '') {
        $result = [
            'emails' => [],
            'error' => '',
        ];

        $mentionList = [];
        if (is_array($mentionFilters)) {
            foreach ($mentionFilters as $mentionValue) {
                $mentionValue = trim((string)$mentionValue);
                if ($mentionValue !== '' && !in_array($mentionValue, $mentionList, true)) {
                    $mentionList[] = $mentionValue;
                }
            }
        }

        $levelFilter = trim((string)$levelFilter);
        if ($levelFilter !== '' && !ctype_digit($levelFilter)) {
            $result['error'] = 'Filtre de niveau invalide.';
            return $result;
        }

        if ($targetType === 'students') {
            if ((int)$activeSessionId <= 0) {
                $result['error'] = 'Aucune session active trouvee.';
                return $result;
            }

            $sql = "SELECT DISTINCT TRIM(std.student_email) AS email
                    FROM tbl_2024_etudiant std
                    INNER JOIN t_2024_inscription_session ins ON ins.student_id = std.student_id
                    WHERE ins.session_id = :active_session_id
                      AND std.remove != 1
                      AND std.student_email IS NOT NULL
                      AND TRIM(std.student_email) <> ''";
            $params = [];
            $params['active_session_id'] = (int)$activeSessionId;

            if (!empty($mentionList)) {
                $placeholders = [];
                foreach ($mentionList as $index => $mentionValue) {
                    $paramName = 'mention_' . $index;
                    $placeholders[] = ':' . $paramName;
                    $params[$paramName] = $mentionValue;
                }
                $sql .= ' AND ins.etude_mention IN (' . implode(', ', $placeholders) . ')';
            }

            if ($levelFilter !== '') {
                $sql .= ' AND ins.niveau_std = :level_filter';
                $params['level_filter'] = (int)$levelFilter;
            }

            $stmt = $dtb->prepare($sql);
            $stmt->execute($params);
            $result['emails'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $result;
        }

        if ($targetType === 'teachers') {
            $stmt = $dtb->query("SELECT DISTINCT TRIM(email) AS email FROM teacher WHERE remove != 1 AND email IS NOT NULL AND TRIM(email) <> ''");
            $result['emails'] = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            return $result;
        }

        if ($targetType === 'users') {
            $stmt = $dtb->query("SELECT DISTINCT TRIM(mail) AS email FROM compt_utilisateur WHERE etat = 1 AND mail IS NOT NULL AND TRIM(mail) <> ''");
            $result['emails'] = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            return $result;
        }

        if ($targetType === 'custom') {
            $tokens = preg_split('/[\s,;]+/', (string)$customRecipientsRaw);
            $result['emails'] = is_array($tokens) ? $tokens : [];
            return $result;
        }

        $result['error'] = 'Type de destinataire invalide.';
        return $result;
    }
}

if (!function_exists('normalizeUploadedFiles')) {
    function normalizeUploadedFiles($filesEntry) {
        $normalized = [];
        if (!is_array($filesEntry) || !isset($filesEntry['name'])) {
            return $normalized;
        }

        if (is_array($filesEntry['name'])) {
            $count = count($filesEntry['name']);
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => $filesEntry['name'][$i] ?? '',
                    'type' => $filesEntry['type'][$i] ?? '',
                    'tmp_name' => $filesEntry['tmp_name'][$i] ?? '',
                    'error' => $filesEntry['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $filesEntry['size'][$i] ?? 0,
                ];
            }
        } else {
            $normalized[] = $filesEntry;
        }

        return $normalized;
    }
}

if (!function_exists('buildEmailAttachments')) {
    function buildEmailAttachments($filesEntry) {
        $result = [
            'error' => '',
            'items' => [],
        ];

        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/zip',
            'application/x-rar-compressed',
            'application/vnd.rar',
        ];

        $maxFileSize = 5 * 1024 * 1024;
        $maxTotalSize = 15 * 1024 * 1024;
        $totalSize = 0;

        $files = normalizeUploadedFiles($filesEntry);
        if (empty($files)) {
            return $result;
        }

        foreach ($files as $file) {
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($errorCode !== UPLOAD_ERR_OK) {
                $result['error'] = 'Echec de chargement d\'une piece jointe (code ' . $errorCode . ').';
                return $result;
            }

            $tmpName = (string)($file['tmp_name'] ?? '');
            $fileName = trim((string)($file['name'] ?? 'fichier'));
            $fileSize = (int)($file['size'] ?? 0);

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $result['error'] = 'Piece jointe invalide detectee.';
                return $result;
            }

            if ($fileSize <= 0 || $fileSize > $maxFileSize) {
                $result['error'] = 'Chaque piece jointe doit faire au maximum 5 Mo.';
                return $result;
            }

            $totalSize += $fileSize;
            if ($totalSize > $maxTotalSize) {
                $result['error'] = 'La taille totale des pieces jointes depasse 15 Mo.';
                return $result;
            }

            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string)finfo_file($finfo, $tmpName);
                    finfo_close($finfo);
                }
            }
            if ($mime === '') {
                $mime = (string)($file['type'] ?? 'application/octet-stream');
            }

            if (!in_array($mime, $allowedMime, true)) {
                $result['error'] = 'Type de fichier non autorise pour les pieces jointes.';
                return $result;
            }

            $binary = @file_get_contents($tmpName);
            if ($binary === false) {
                $result['error'] = 'Impossible de lire une piece jointe.';
                return $result;
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
            if ($safeName === '' || $safeName === null) {
                $safeName = 'piece_jointe';
            }

            $result['items'][] = [
                'name' => $safeName,
                'mime' => $mime,
                'data' => $binary,
            ];
        }

        return $result;
    }
}

if (($_GET['ajax'] ?? '') === 'recipient-preview') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!$canUseBulkEmail) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'forbidden', 'items' => [], 'total' => 0]);
        exit;
    }

    try {
        $targetType = trim((string)($_GET['target_type'] ?? 'students'));
        $customRecipientsRaw = (string)($_GET['custom_recipients'] ?? '');
        $mentionFilters = isset($_GET['mention_filters']) && is_array($_GET['mention_filters']) ? $_GET['mention_filters'] : [];
        $levelFilter = trim((string)($_GET['level_filter'] ?? ''));

        $collection = collectBulkRecipientEmails($dtb, $activeSessionId, $targetType, $customRecipientsRaw, $mentionFilters, $levelFilter);
        if ($collection['error'] !== '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'invalid_filter', 'message' => $collection['error'], 'items' => [], 'total' => 0], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $validEmails = [];
        foreach ($collection['emails'] as $email) {
            $clean = strtolower(trim((string)$email));
            if ($clean !== '' && filter_var($clean, FILTER_VALIDATE_EMAIL)) {
                $validEmails[$clean] = true;
            }
        }

        $emails = array_keys($validEmails);
        echo json_encode([
            'success' => true,
            'total' => count($emails),
            'items' => array_slice($emails, 0, 120),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'query_failed', 'items' => [], 'total' => 0]);
        exit;
    }
}

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
                $studentStmt = $dtb->prepare("SELECT CONCAT(TRIM(student_nom), ' ', TRIM(student_prenom)) AS full_name, TRIM(student_email) AS email, 'Etudiant' AS source
                                                                         FROM tbl_2024_etudiant
                                                                         WHERE remove != 1
                                                                             AND student_email IS NOT NULL
                                                                             AND TRIM(student_email) <> ''
                                                                             AND (student_nom LIKE :q OR student_prenom LIKE :q OR student_email LIKE :q)
                                                                         LIMIT 12");
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

if (($_GET['ajax'] ?? '') === 'mailbox-view') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!$canUseBulkEmail) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'forbidden']);
        exit;
    }

    try {
        $box = strtolower(trim((string)($_GET['box'] ?? 'compose')));
        $payload = getMailboxViewPayload($dtb, $box, $canUseBulkEmail);
        echo json_encode([
            'success' => true,
            'box' => (string)($payload['box'] ?? 'compose'),
            'title' => (string)($payload['title'] ?? 'Nouveau message'),
            'items' => (array)($payload['items'] ?? []),
            'counts' => (array)($payload['counts'] ?? ['inbox' => 0, 'sent' => 0]),
            'source' => (string)($payload['source'] ?? 'local'),
            'warning' => (string)($payload['warning'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'mailbox_failed']);
        exit;
    }
}

if (($_GET['ajax'] ?? '') === 'mailbox-message') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!$canUseBulkEmail) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'forbidden']);
        exit;
    }

    $box = strtolower(trim((string)($_GET['box'] ?? 'inbox')));
    $id = (int)($_GET['id'] ?? 0);

    try {
        $detail = [];
        if (isExternalImapMailboxEnabled() && in_array($box, ['inbox', 'sent'], true)) {
            $detail = fetchImapMessageDetail($box, $id);
        }

        if (empty($detail) || ($detail['success'] ?? false) !== true) {
            $detail = fetchLocalMailboxMessageDetail($dtb, $id);
        }

        if (($detail['success'] ?? false) !== true) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'not_found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'subject' => (string)($detail['subject'] ?? 'Sans objet'),
            'from' => (string)($detail['from'] ?? 'Systeme'),
            'created_at' => (string)($detail['created_at'] ?? ''),
            'html' => (string)($detail['html'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'message_failed']);
        exit;
    }
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
    'message_html' => $_POST['message_html'] ?? '',
    'custom_recipients' => $_POST['custom_recipients'] ?? '',
    'mention_filters' => $postedMentionFilters,
    'level_filter' => trim((string)($_POST['level_filter'] ?? '')),
];

if (!function_exists('processBulkEmailSend')) {
    function processBulkEmailSend($dtb, $canUseBulkEmail, $currentUser, $app_base, $activeSessionId, $bulkForm, $csrfToken, $uploadedFiles = []) {
        $result = [
            'success' => false,
            'message' => '',
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'redirect_url' => '',
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
        $messageHtmlRaw = (string)($bulkForm['message_html'] ?? '');
        $customRecipientsRaw = (string)$bulkForm['custom_recipients'];
        $mentionFilters = [];
        if (isset($bulkForm['mention_filters']) && is_array($bulkForm['mention_filters'])) {
            foreach ($bulkForm['mention_filters'] as $mentionValue) {
                $mentionValue = trim((string)$mentionValue);
                if ($mentionValue !== '' && !in_array($mentionValue, $mentionFilters, true)) {
                    $mentionFilters[] = $mentionValue;
                }
            }
        }
        $levelFilter = trim((string)($bulkForm['level_filter'] ?? ''));

        if ($result['message'] === '' && ($subject === '' || $messageText === '')) {
            $result['message'] = 'Sujet et message sont obligatoires.';
        } elseif ($result['message'] === '' && strlen($subject) > 190) {
            $result['message'] = 'Le sujet est trop long (max 190 caracteres).';
        }

        $attachments = [];
        if ($result['message'] === '') {
            $attachResult = buildEmailAttachments($uploadedFiles['attachments'] ?? null);
            if ($attachResult['error'] !== '') {
                $result['message'] = $attachResult['error'];
            } else {
                $attachments = $attachResult['items'];
            }
        }

        $inlineImages = [];
        if ($result['message'] === '') {
            $inlineAllowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $inlineFiles = normalizeUploadedFiles($uploadedFiles['inline_images'] ?? null);
            $inlineIds = isset($_POST['inline_image_ids']) && is_array($_POST['inline_image_ids']) ? $_POST['inline_image_ids'] : [];

            foreach ($inlineFiles as $idx => $file) {
                $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($errorCode !== UPLOAD_ERR_OK) {
                    $result['message'] = 'Erreur de chargement d\'image inline.';
                    break;
                }

                $tmpName = (string)($file['tmp_name'] ?? '');
                if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                    $result['message'] = 'Image inline invalide.';
                    break;
                }

                $mime = '';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime = (string)finfo_file($finfo, $tmpName);
                        finfo_close($finfo);
                    }
                }
                if ($mime === '') {
                    $mime = (string)($file['type'] ?? 'application/octet-stream');
                }
                if (!in_array($mime, $inlineAllowed, true)) {
                    $result['message'] = 'Seules les images (JPG, PNG, GIF, WEBP) sont autorisees en glisser-deposer.';
                    break;
                }

                $binary = @file_get_contents($tmpName);
                if ($binary === false) {
                    $result['message'] = 'Impossible de lire une image inline.';
                    break;
                }

                $inlineId = trim((string)($inlineIds[$idx] ?? ''));
                if ($inlineId === '') {
                    $inlineId = 'img_' . $idx;
                }
                $cid = 'inline_' . preg_replace('/[^A-Za-z0-9_-]/', '', $inlineId) . '_' . substr(md5($inlineId . $idx), 0, 8);

                $inlineImages[$inlineId] = [
                    'cid' => $cid,
                    'mime' => $mime,
                    'name' => preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($file['name'] ?? ('inline_' . $idx))),
                    'data' => $binary,
                ];
            }
        }

        if ($result['message'] === '') {
            $collection = collectBulkRecipientEmails($dtb, $activeSessionId, $targetType, $customRecipientsRaw, $mentionFilters, $levelFilter);
            $emails = $collection['emails'];
            if ($collection['error'] !== '') {
                $result['message'] = $collection['error'];
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
                    $fromName = 'Université Adventiste Zurcher - No Reply';
                    $fromEmail = 'no-reply@zurcher.edu.mg';
                    $logoCid = 'uaz_logo_inline';
                    $logoPath = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/file/UAZLogo.png';
                    $logoBinary = false;
                    $logoMime = 'image/png';
                    $logoFileName = 'UAZLogo.png';

                    if (is_file($logoPath)) {
                        $rawLogo = @file_get_contents($logoPath);
                        if ($rawLogo !== false && function_exists('imagecreatefromstring')) {
                            $img = @imagecreatefromstring($rawLogo);
                            if ($img !== false) {
                                $srcW = imagesx($img);
                                $srcH = imagesy($img);
                                $maxW = 220;
                                $maxH = 64;

                                $scale = min($maxW / max(1, $srcW), $maxH / max(1, $srcH), 1);
                                $newW = max(1, (int)floor($srcW * $scale));
                                $newH = max(1, (int)floor($srcH * $scale));

                                $tmp = imagecreatetruecolor($newW, $newH);
                                if ($tmp !== false) {
                                    imagealphablending($tmp, false);
                                    imagesavealpha($tmp, true);
                                    $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                                    imagefilledrectangle($tmp, 0, 0, $newW, $newH, $transparent);

                                    imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

                                    ob_start();
                                    imagepng($tmp, null, 9);
                                    $logoBinary = ob_get_clean();

                                    imagedestroy($tmp);
                                }

                                imagedestroy($img);
                            }
                        }

                        if ($logoBinary === false) {
                            $logoBinary = $rawLogo;
                        }
                    }

                    $logoInlineTag = $logoBinary !== false
                        ? '<img src="cid:' . $logoCid . '" alt="UAZ" style="height:36px;vertical-align:middle;">'
                        : '<span style="color:#e8f1f8;font-size:18px;font-weight:700;vertical-align:middle;display:inline-block;">UAZ</span>';

                    $safeMessageText = htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8');
                    $bodyContentHtml = nl2br($safeMessageText);

                    if (trim($messageHtmlRaw) !== '') {
                        $sanitized = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $messageHtmlRaw);
                        // Remove editor-only controls so they never leak into outgoing HTML.
                        $sanitized = preg_replace('/<button\b[^>]*>.*?<\/button>/is', '', $sanitized);
                        $sanitized = preg_replace('/<span\b[^>]*class=["\'][^"\']*inline-image-handle[^"\']*["\'][^>]*>\s*<\/span>/is', '', $sanitized);
                        $sanitized = strip_tags($sanitized, '<p><br><div><span><strong><em><u><b><i><ul><ol><li><img>');
                        $sanitized = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $sanitized);
                        $sanitized = preg_replace('/\son[a-z]+\s*=\s*\'[^\']*\'/i', '', $sanitized);
                        $sanitized = preg_replace('/\sstyle\s*=\s*"[^"]*"/i', '', $sanitized);
                        $sanitized = preg_replace('/\sstyle\s*=\s*\'[^\']*\'/i', '', $sanitized);

                        $sanitized = preg_replace('/<img\b(?![^>]*data-inline-id=)[^>]*>/i', '', $sanitized);

                        $sanitized = preg_replace_callback('/<img\b[^>]*data-inline-id=["\']?([^"\'\s>]+)["\']?[^>]*>/i', function ($m) use ($inlineImages) {
                            $inlineId = (string)($m[1] ?? '');
                            if ($inlineId === '' || !isset($inlineImages[$inlineId])) {
                                return '';
                            }
                            $cid = $inlineImages[$inlineId]['cid'];
                            $matchedTag = (string)($m[0] ?? '');
                            $inlineWidth = '';
                            if (preg_match('/data-inline-width=["\']?(\d{2,4})["\']?/i', $matchedTag, $wm)) {
                                $parsedWidth = (int)($wm[1] ?? 0);
                                if ($parsedWidth >= 80 && $parsedWidth <= 1800) {
                                    $inlineWidth = $parsedWidth . 'px';
                                }
                            }

                            $style = 'max-width:100%;height:auto;border-radius:8px;display:block;margin:10px auto;';
                            if ($inlineWidth !== '') {
                                $style .= 'width:' . $inlineWidth . ';';
                            }

                            return '<img src="cid:' . $cid . '" alt="Image" style="' . $style . '">';
                        }, $sanitized);

                        if (trim(strip_tags($sanitized)) !== '' || strpos($sanitized, '<img') !== false) {
                            $bodyContentHtml = $sanitized;
                        }
                    }

                    $htmlMessage = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($safeSubject, ENT_QUOTES, 'UTF-8') . '</title></head><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;">'
                        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:20px 0;background:#f8fafc;">'
                        . '<tr><td align="center">'
                        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">'
                        . '<tr><td align="center" style="background:#0a1628;padding:18px 24px;text-align:center;">'
                        . '<div style="text-align:center;">' . $logoInlineTag . '</div>'
                        . '<div style="color:#e8f1f8;font-size:18px;font-weight:700;margin-top:10px;">Université Adventiste Zurcher</div>'
                        . '</td></tr>'
                        . '<tr><td style="padding:24px;color:#0f172a;font-size:15px;line-height:1.6;">'
                        . $bodyContentHtml
                        . '</td></tr>'
                        . '</table>'
                        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="margin-top:10px;">'
                        . '<tr><td align="center" style="font-size:12px;color:#64748b;line-height:1.7;padding:0 10px;">'
                        . 'Message automatique, merci de ne pas repondre a cet email.<br>'
                        . 'Contact: <a href="mailto:registrar@zurcher.edu.mg" style="color:#0b63ce;text-decoration:none;">registrar@zurcher.edu.mg</a> | Tel: <a href="tel:+261344600008" style="color:#0b63ce;text-decoration:none;">034 46 000 08</a><br>'
                        . '85Q7+M95, Antsampanimahazo, B.P. 325 Antsirabe 110, Vakinankaratra, Madagascar<br>'
                        . '&copy; ' . date('Y')
                        . '</td></tr>'
                        . '</table>'
                        . '</td></tr>'
                        . '</table>'
                        . '</body></html>';

                    $plainTextMessage = $messageText . "\n\nMessage automatique, merci de ne pas repondre a cet email.";
                    $boundaryMixed = 'mixed_' . md5((string)microtime(true));
                    $boundaryRelated = 'related_' . md5((string)(microtime(true) . '_related'));
                    $boundaryAlt = 'alt_' . md5((string)(microtime(true) . '_alt'));

                    $mailBody = '';
                    $mailBody .= '--' . $boundaryMixed . "\r\n";
                    $mailBody .= 'Content-Type: multipart/related; boundary="' . $boundaryRelated . '"' . "\r\n\r\n";

                    $mailBody .= '--' . $boundaryRelated . "\r\n";
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
                        $mailBody .= '--' . $boundaryRelated . "\r\n";
                        $mailBody .= 'Content-Type: ' . $logoMime . '; name="' . $logoFileName . '"' . "\r\n";
                        $mailBody .= "Content-Transfer-Encoding: base64\r\n";
                        $mailBody .= 'Content-ID: <' . $logoCid . ">\r\n";
                        $mailBody .= 'Content-Disposition: inline; filename="' . $logoFileName . '"' . "\r\n\r\n";
                        $mailBody .= chunk_split(base64_encode($logoBinary)) . "\r\n";
                    }

                    foreach ($inlineImages as $inlineImage) {
                        $mailBody .= '--' . $boundaryRelated . "\r\n";
                        $mailBody .= 'Content-Type: ' . $inlineImage['mime'] . '; name="' . $inlineImage['name'] . '"' . "\r\n";
                        $mailBody .= "Content-Transfer-Encoding: base64\r\n";
                        $mailBody .= 'Content-ID: <' . $inlineImage['cid'] . ">\r\n";
                        $mailBody .= 'Content-Disposition: inline; filename="' . $inlineImage['name'] . '"' . "\r\n\r\n";
                        $mailBody .= chunk_split(base64_encode($inlineImage['data'])) . "\r\n";
                    }

                    $mailBody .= '--' . $boundaryRelated . "--\r\n";

                    foreach ($attachments as $attachment) {
                        $mailBody .= '--' . $boundaryMixed . "\r\n";
                        $mailBody .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['name'] . '"' . "\r\n";
                        $mailBody .= "Content-Transfer-Encoding: base64\r\n";
                        $mailBody .= 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . "\r\n\r\n";
                        $mailBody .= chunk_split(base64_encode($attachment['data'])) . "\r\n";
                    }

                    $mailBody .= '--' . $boundaryMixed . "--\r\n";

                    $headers = [];
                    $headers[] = 'MIME-Version: 1.0';
                    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundaryMixed . '"';
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
                    $result['redirect_url'] = rtrim((string)$app_base, '/') . '/settings/bulk-email?box=inbox';

                    $createdBy = trim((string)(($currentUser['nom'] ?? '') . ' ' . ($currentUser['prenom'] ?? '')));
                    if ($createdBy === '') {
                        $createdBy = trim((string)($currentUser['pseudo'] ?? ''));
                    }
                    if ($createdBy === '') {
                        $createdBy = 'Utilisateur';
                    }

                    $previewPlain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$bodyContentHtml)));
                    if ($previewPlain === '') {
                        $previewPlain = trim(preg_replace('/\s+/', ' ', (string)$messageText));
                    }

                    $previewImage = '';
                    foreach ($inlineImages as $inlineImage) {
                        $previewImage = buildMailboxPreviewDataUri((string)($inlineImage['data'] ?? ''), (string)($inlineImage['mime'] ?? ''));
                        if ($previewImage !== '') {
                            break;
                        }
                    }

                    $storedMessageHtml = $htmlMessage;
                    if ($logoBinary !== false) {
                        $logoDataUri = buildMailboxPreviewDataUri((string)$logoBinary, (string)$logoMime);
                        if ($logoDataUri !== '') {
                            $storedMessageHtml = str_replace('cid:' . $logoCid, $logoDataUri, $storedMessageHtml);
                        }
                    }
                    foreach ($inlineImages as $inlineImage) {
                        $inlineCid = (string)($inlineImage['cid'] ?? '');
                        if ($inlineCid === '') {
                            continue;
                        }
                        $inlineDataUri = buildMailboxPreviewDataUri((string)($inlineImage['data'] ?? ''), (string)($inlineImage['mime'] ?? ''));
                        if ($inlineDataUri === '') {
                            continue;
                        }
                        $storedMessageHtml = str_replace('cid:' . $inlineCid, $inlineDataUri, $storedMessageHtml);
                    }

                    bulkMailboxInsert(
                        $dtb,
                        'sent',
                        $safeSubject,
                        $previewPlain,
                        count($recipients),
                        $sent,
                        $failed,
                        $createdBy,
                        $storedMessageHtml,
                        $previewImage
                    );

                    $reportSubject = 'Rapport d\'envoi: ' . $safeSubject;
                    $reportPreview = 'Campagne terminee. Total: ' . count($recipients) . ', envoyes: ' . $sent . ', echecs: ' . $failed . '.';

                    bulkMailboxInsert(
                        $dtb,
                        'inbox',
                        $reportSubject,
                        $reportPreview,
                        count($recipients),
                        $sent,
                        $failed,
                        $createdBy,
                        '',
                        ''
                    );
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
        $activeSessionId,
        $bulkForm,
        $_POST['csrf_token'] ?? '',
        $_FILES
    );

    $isAjaxSend = (($_GET['ajax'] ?? '') === 'send-bulk-email')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

    if ($isAjaxSend) {
        if (ob_get_level() > 0 && ob_get_length()) {
            ob_clean();
        }
        if (($bulkEmailResult['success'] ?? false) && empty($bulkEmailResult['redirect_url'])) {
            $bulkEmailResult['redirect_url'] = rtrim((string)$app_base, '/') . '/settings/bulk-email?box=inbox';
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($bulkEmailResult, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($bulkEmailResult['success'] ?? false) === true) {
        header('Location: ' . rtrim((string)$app_base, '/') . '/settings/bulk-email?box=inbox');
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
        .gmail-shell {
            --gm-shell-bg: #00102a;
            --gm-shell-border: #11325c;
            --gm-title: #e7f1ff;
            --gm-link: #7db1ff;
            --gm-link-hover: #a9cdff;
            --gm-compose-bg: #001533;
            --gm-compose-border: #11325c;
            --gm-row-bg: #001533;
            --gm-row-border: #163b69;
            --gm-input-text: #eaf2ff;
            --gm-input-placeholder: #98b6de;
            --gm-field-bg: #082246;
            --gm-field-border: #2b5488;
            --gm-field-focus: #5fa8ff;
            --gm-side-bg: #001533;
            --gm-side-border: #11325c;
            --gm-label: #b7ccee;
            --gm-hint: #9fb8dd;
            --gm-chip-bg: #0a2448;
            --gm-chip-border: #2a5081;
            --gm-chip-text: #eaf2ff;
            --gm-chip-close: #bad0ef;
            --gm-results-bg: #001837;
            --gm-results-border: #1b4373;
            --gm-result-divider: #1a3e6b;
            --gm-result-name: #eaf2ff;
            --gm-result-meta: #b2c7e7;
            --gm-result-hover: #072347;
            --gm-select-bg: #072347;
            --gm-select-border: #2b5488;
            --gm-select-text: #eaf2ff;
            --gm-send-bg: #1d5fd6;
            --gm-send-bg-hover: #2f77f0;
            --gm-send-disabled-bg: #35598b;

            width: 100%;
            background: var(--gm-shell-bg);
            border: 1px solid var(--gm-shell-border);
            border-radius: 16px;
            padding: clamp(12px, 2.5vw, 20px);
        }

        [data-theme="light"] .gmail-shell {
            --gm-shell-bg: #f1f3f4;
            --gm-shell-border: #d2d6dc;
            --gm-title: #1f2937;
            --gm-link: #1d4ed8;
            --gm-link-hover: #2563eb;
            --gm-compose-bg: #ffffff;
            --gm-compose-border: #d2d6dc;
            --gm-row-bg: #ffffff;
            --gm-row-border: #e5e7eb;
            --gm-input-text: #0f172a;
            --gm-input-placeholder: #94a3b8;
            --gm-field-bg: #ffffff;
            --gm-field-border: #cbd5e1;
            --gm-field-focus: #38bdf8;
            --gm-side-bg: #ffffff;
            --gm-side-border: #d2d6dc;
            --gm-label: #64748b;
            --gm-hint: #64748b;
            --gm-chip-bg: #f3f4f6;
            --gm-chip-border: #9ca3af;
            --gm-chip-text: #111827;
            --gm-chip-close: #4b5563;
            --gm-results-bg: #ffffff;
            --gm-results-border: #d1d5db;
            --gm-result-divider: #eef2f7;
            --gm-result-name: #1f2937;
            --gm-result-meta: #64748b;
            --gm-result-hover: #f1f5f9;
            --gm-select-bg: #ffffff;
            --gm-select-border: #cbd5e1;
            --gm-select-text: #334155;
            --gm-send-bg: #2563eb;
            --gm-send-bg-hover: #3b82f6;
            --gm-send-disabled-bg: #94a3b8;
        }

        .gmail-shell {
            width: 100%;
        }
        .gmail-app-grid {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 12px;
        }
        .gmail-nav {
            background: var(--gm-side-bg);
            border: 1px solid var(--gm-side-border);
            border-radius: 14px;
            padding: 12px;
        }
        .gmail-menu-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 8px 10px;
            color: var(--gm-input-text);
            font-size: 13px;
            text-decoration: none;
        }
        .gmail-menu-link:hover {
            background: rgba(125, 177, 255, 0.1);
            border-color: var(--gm-row-border);
        }
        .gmail-menu-link.active {
            background: rgba(125, 177, 255, 0.18);
            border-color: var(--gm-field-focus);
            font-weight: 600;
        }
        .gmail-menu-count {
            min-width: 22px;
            border-radius: 999px;
            padding: 1px 7px;
            font-size: 11px;
            text-align: center;
            background: var(--gm-field-bg);
            border: 1px solid var(--gm-field-border);
        }
        .gmail-content {
            min-width: 0;
        }
        .gmail-mailbox {
            background: var(--gm-compose-bg);
            border: 1px solid var(--gm-compose-border);
            border-radius: 14px;
            overflow: hidden;
        }
        .gmail-mailbox-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--gm-row-border);
            color: var(--gm-title);
            font-size: 15px;
            font-weight: 600;
        }
        .gmail-mail-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gm-row-border);
            background: var(--gm-row-bg);
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            cursor: pointer;
        }
        .gmail-mail-thumb {
            width: 84px;
            height: 54px;
            border-radius: 10px;
            border: 1px solid var(--gm-row-border);
            object-fit: cover;
            background: rgba(148, 163, 184, 0.12);
            display: block;
        }
        .gmail-mail-item:last-child {
            border-bottom: 0;
        }
        .gmail-mail-item:hover {
            background: var(--gm-result-hover);
        }
        .gmail-mail-main {
            min-width: 0;
        }
        .gmail-mail-subject {
            color: var(--gm-input-text);
            font-size: 14px;
            font-weight: 600;
        }
        .gmail-mail-meta {
            color: var(--gm-hint);
            font-size: 11px;
            margin-top: 3px;
        }
        .gmail-mail-preview {
            color: var(--gm-label);
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.45;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gmail-mail-actions {
            display: none;
            align-items: center;
            gap: 6px;
        }
        .gmail-mail-item:hover .gmail-mail-actions {
            display: inline-flex;
        }
        .gmail-mail-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid var(--gm-field-border);
            background: var(--gm-field-bg);
            color: var(--gm-input-text);
            cursor: pointer;
        }
        .gmail-mail-action-btn:hover {
            border-color: var(--gm-field-focus);
        }
        .gmail-empty {
            padding: 22px 16px;
            color: var(--gm-hint);
            font-size: 13px;
        }
        .gmail-modal {
            position: fixed;
            inset: 0;
            background: rgba(2, 10, 25, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 18px;
        }
        .gmail-modal.is-open {
            display: flex;
        }
        .gmail-modal-card {
            width: min(920px, 100%);
            max-height: 90vh;
            overflow: auto;
            border-radius: 14px;
            border: 1px solid var(--gm-compose-border);
            background: var(--gm-compose-bg);
            color: var(--gm-input-text);
        }
        .gmail-modal-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--gm-row-border);
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }
        .gmail-modal-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--gm-title);
        }
        .gmail-modal-meta {
            margin-top: 4px;
            font-size: 12px;
            color: var(--gm-hint);
        }
        .gmail-modal-close {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: 1px solid var(--gm-field-border);
            background: var(--gm-field-bg);
            color: var(--gm-input-text);
            cursor: pointer;
        }
        .gmail-modal-body {
            padding: 16px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--gm-input-text);
        }
        .gmail-compose-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--gm-field-focus);
            border-radius: 999px;
            padding: 8px 14px;
            color: var(--gm-title);
            background: rgba(125, 177, 255, 0.15);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .gmail-compose-btn:hover {
            background: rgba(125, 177, 255, 0.24);
        }
        .gmail-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .gmail-title {
            font-size: clamp(0.95rem, 1.2vw, 1.125rem);
            font-weight: 600;
            color: var(--gm-title);
            margin: 0;
        }
        .gmail-back-link {
            font-size: 0.8125rem;
            color: var(--gm-link);
            white-space: nowrap;
        }
        .gmail-back-link:hover { color: var(--gm-link-hover); }
        .gmail-compose {
            background: var(--gm-compose-bg);
            border: 1px solid var(--gm-compose-border);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            min-width: 0;
        }
        .gmail-head { background: linear-gradient(90deg, #00132f, #0a2e5a); color: #f4f8ff; padding: 14px 18px; }
        .gmail-row { border-bottom: 1px solid var(--gm-row-border); padding: 10px 14px; background: var(--gm-row-bg); }
        .gmail-row:last-child { border-bottom: 0; }
        .gmail-input {
            width: 100%;
            min-width: 0;
            border: 1px solid var(--gm-field-border) !important;
            outline: none;
            background: var(--gm-field-bg) !important;
            color: var(--gm-input-text) !important;
            font-size: 14px;
            border-radius: 8px;
            padding: 8px 10px !important;
            min-height: 38px;
        }
        .gmail-input::placeholder { color: var(--gm-input-placeholder); opacity: 1; }
        .gmail-input:focus {
            border-color: var(--gm-field-focus) !important;
            box-shadow: 0 0 0 3px rgba(79, 156, 255, 0.22);
        }
        .gmail-textarea {
            width: 100%;
            min-height: 240px;
            resize: vertical;
            border: 1px solid var(--gm-field-border) !important;
            outline: none;
            color: var(--gm-input-text) !important;
            font-size: 14px;
            line-height: 1.6;
            background: var(--gm-field-bg) !important;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .gmail-textarea::placeholder { color: var(--gm-input-placeholder); opacity: 1; }
        .gmail-textarea:focus {
            border-color: var(--gm-field-focus) !important;
            box-shadow: 0 0 0 3px rgba(79, 156, 255, 0.22);
        }
        .gmail-editor {
            width: 100%;
            min-height: 240px;
            resize: vertical;
            border: 1px solid var(--gm-field-border) !important;
            outline: none;
            color: var(--gm-input-text) !important;
            font-size: 14px;
            line-height: 1.6;
            background: var(--gm-field-bg) !important;
            border-radius: 10px;
            padding: 10px 12px;
            overflow-y: auto;
        }
        .gmail-editor:focus {
            border-color: var(--gm-field-focus) !important;
            box-shadow: 0 0 0 3px rgba(79, 156, 255, 0.22);
        }
        .gmail-editor:empty:before {
            content: attr(data-placeholder);
            color: var(--gm-input-placeholder);
        }
        .gmail-editor.dragover {
            outline: 2px dashed var(--gm-field-focus);
            outline-offset: 4px;
        }
        .inline-image-wrap {
            position: relative;
            display: inline-block;
            margin: 10px auto;
            max-width: 100%;
            border: 1px solid transparent;
            border-radius: 10px;
            vertical-align: top;
        }
        .inline-image-wrap.is-selected {
            border-color: var(--gm-field-focus);
            box-shadow: 0 0 0 2px rgba(79, 156, 255, 0.22);
        }
        .inline-image-wrap img {
            display: block;
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            user-select: none;
            -webkit-user-drag: none;
        }
        .inline-image-remove {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 22px;
            height: 22px;
            border: 0;
            border-radius: 50%;
            background: #ef4444;
            color: #ffffff;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            display: none;
        }
        .inline-image-wrap.is-selected .inline-image-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .inline-image-handle {
            position: absolute;
            width: 12px;
            height: 12px;
            right: -5px;
            bottom: -5px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            background: var(--gm-field-focus);
            cursor: nwse-resize;
            display: none;
        }
        .inline-image-wrap.is-selected .inline-image-handle {
            display: block;
        }
        .gmail-side { background: var(--gm-side-bg); border: 1px solid var(--gm-side-border); border-radius: 14px; padding: 12px; min-width: 0; }
        .gmail-status { display: none; border-radius: 10px; border: 1px solid transparent; padding: 10px 12px; font-size: 13px; margin-bottom: 12px; }
        .gmail-status.success { display: block; border-color: #10b981; background: #ecfdf5; color: #065f46; }
        .gmail-status.error { display: block; border-color: #ef4444; background: #fef2f2; color: #991b1b; }
        .gmail-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; border: 1px solid var(--gm-chip-border); background: var(--gm-chip-bg); color: var(--gm-chip-text); font-size: 12px; max-width: 100%; }
        .gmail-chip > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gmail-chip button { border: none; background: transparent; color: var(--gm-chip-close); cursor: pointer; font-size: 12px; }
        .gmail-results { border: 1px solid var(--gm-results-border); border-radius: 10px; background: var(--gm-results-bg); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18); }
        .gmail-results button { border-bottom: 1px solid var(--gm-result-divider); }
        .gmail-results button:last-child { border-bottom: 0; }
        .gmail-result-item { background: transparent; }
        .gmail-result-item:hover { background: var(--gm-result-hover); }
        .gmail-result-name { color: var(--gm-result-name); font-size: 0.875rem; }
        .gmail-result-meta { color: var(--gm-result-meta); font-size: 0.75rem; }

        .gmail-label { color: var(--gm-label); }
        .gmail-hint { color: var(--gm-hint); }
        .gmail-side select,
        .gmail-side textarea {
            background: var(--gm-field-bg) !important;
            border: 1px solid var(--gm-field-border) !important;
            color: var(--gm-select-text) !important;
        }
        .gmail-side select[multiple] {
            min-height: 110px;
            padding-top: 6px;
            padding-bottom: 6px;
        }
        .gmail-preview {
            margin-top: 12px;
            border: 1px solid var(--gm-field-border);
            background: var(--gm-field-bg);
            border-radius: 10px;
            padding: 8px;
        }
        .gmail-preview-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 11px;
            color: var(--gm-label);
        }
        .gmail-preview-list {
            max-height: 180px;
            overflow: auto;
            font-size: 12px;
            color: var(--gm-input-text);
            display: grid;
            gap: 4px;
        }
        .gmail-preview-item {
            border: 1px solid var(--gm-row-border);
            background: var(--gm-row-bg);
            border-radius: 7px;
            padding: 4px 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gmail-side textarea::placeholder { color: var(--gm-input-placeholder); opacity: 1; }
        .gmail-side select:focus,
        .gmail-side textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 156, 255, 0.22);
            border-color: var(--gm-field-focus) !important;
        }
        #sendBtn {
            background: var(--gm-send-bg) !important;
            color: #ffffff !important;
        }
        #sendBtn:hover { background: var(--gm-send-bg-hover) !important; }
        #sendBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: var(--gm-send-disabled-bg) !important;
        }

        @media (max-width: 1024px) {
            .gmail-textarea { min-height: 200px; }
        }

        @media (max-width: 767px) {
            .gmail-shell { border-radius: 12px; }
            .gmail-app-grid { grid-template-columns: 1fr; }
            .gmail-row { padding: 10px 10px; }
            .gmail-head { padding: 12px 12px; }
            .gmail-textarea { min-height: 170px; }
            .gmail-side textarea#customRecipients { min-height: 120px; }
            .gmail-back-link { width: 100%; }
            .gmail-mail-thumb {
                width: 68px;
                height: 46px;
            }
        }
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

                <div class="back flex-1 overflow-y-auto p-0">
                    <div class="w-full h-full gmail-shell">
                        <div class="gmail-toolbar">
                            <h1 class="gmail-title"><i class="bi bi-send-check"></i>&nbsp; Composeur Email UAZ</h1>
                            <a href="<?=$app_base?>/settings" class="gmail-back-link">Retour aux parametres</a>
                        </div>

                        <div id="mailStatus" class="gmail-status"></div>
                        <div class="text-[11px] gmail-hint mb-2" id="mailboxSourceInfo">
                            Source boite: <b id="mailboxSourceText"><?= $mailboxSource === 'imap' ? 'Gmail IMAP' : 'Locale' ?></b>
                            <span class="text-amber-300" id="mailboxSourceWarning"><?php if ($mailboxWarning !== '') { echo ' • ' . e($mailboxWarning); } ?></span>
                        </div>

                        <div class="gmail-app-grid">
                            <aside class="gmail-nav">
                                <a href="<?=$app_base?>/settings/bulk-email?box=compose" data-mailbox-nav="compose" class="gmail-compose-btn"><i class="bi bi-pencil-square"></i> Nouveau message</a>

                                <a href="<?=$app_base?>/settings/bulk-email?box=inbox" data-mailbox-nav="inbox" class="gmail-menu-link <?php if($currentBox === 'inbox'){ echo 'active'; } ?>">
                                    <span><i class="bi bi-inbox"></i> Boite de reception</span>
                                    <span class="gmail-menu-count" id="countInbox"><?=(int)($mailboxCounts['inbox'] ?? 0)?></span>
                                </a>
                                <a href="<?=$app_base?>/settings/bulk-email?box=sent" data-mailbox-nav="sent" class="gmail-menu-link <?php if($currentBox === 'sent'){ echo 'active'; } ?>">
                                    <span><i class="bi bi-send"></i> Messages envoyes</span>
                                    <span class="gmail-menu-count" id="countSent"><?=(int)($mailboxCounts['sent'] ?? 0)?></span>
                                </a>
                                <a href="<?=$app_base?>/settings/bulk-email?box=drafts" data-mailbox-nav="drafts" class="gmail-menu-link <?php if($currentBox === 'drafts'){ echo 'active'; } ?>">
                                    <span><i class="bi bi-envelope-paper"></i> Brouillons</span>
                                    <span class="gmail-menu-count">0</span>
                                </a>
                                <a href="<?=$app_base?>/settings/bulk-email?box=favorites" data-mailbox-nav="favorites" class="gmail-menu-link <?php if($currentBox === 'favorites'){ echo 'active'; } ?>">
                                    <span><i class="bi bi-star"></i> Favoris</span>
                                    <span class="gmail-menu-count">0</span>
                                </a>
                                <a href="<?=$app_base?>/settings/bulk-email?box=trash" data-mailbox-nav="trash" class="gmail-menu-link <?php if($currentBox === 'trash'){ echo 'active'; } ?>">
                                    <span><i class="bi bi-trash"></i> Corbeille</span>
                                    <span class="gmail-menu-count">0</span>
                                </a>
                            </aside>

                            <div class="gmail-content">
                                <form method="post" action="<?=$app_base?>/settings/bulk-email" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 md:gap-4 lg:grid-cols-12" id="bulkMailForm" <?php if($currentBox !== 'compose'){ echo 'style="display:none;"'; } ?>>
                                    <input type="hidden" name="action" value="send_bulk_email" />
                                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>" />

                                    <div class="lg:col-span-9 gmail-compose">
                                        <div class="gmail-head font-semibold">Nouveau message</div>

                                        <div class="gmail-row">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="text-xs font-semibold text-slate-500 w-10">A</span>
                                                <input type="text" id="recipientSearch" class="gmail-input" placeholder="Rechercher un nom ou un email" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> autocomplete="off" />
                                            </div>
                                            <div class="relative mt-2">
                                                <div id="recipientResults" class="hidden gmail-results absolute left-0 right-0 mt-1 z-50 max-h-64 overflow-auto"></div>
                                            </div>
                                            <div id="selectedRecipients" class="mt-2 flex flex-wrap gap-2"></div>
                                        </div>

                                        <div class="gmail-row">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="text-xs font-semibold text-slate-500 w-10">Objet</span>
                                                <input type="text" name="subject" maxlength="190" value="<?=e($bulkForm['subject'])?>" class="gmail-input" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> required />
                                            </div>
                                        </div>

                                        <div class="gmail-row">
                                            <div id="messageEditor" class="gmail-editor" data-placeholder="Ecrivez votre message..." contenteditable="<?php if(!$canUseBulkEmail){ echo 'false'; } else { echo 'true'; } ?>"><?=nl2br(e($bulkForm['message']))?></div>
                                            <input type="hidden" name="message" id="messagePlain" value="<?=e($bulkForm['message'])?>" />
                                            <input type="hidden" name="message_html" id="messageHtml" value="" />
                                        </div>

                                        <div class="gmail-row">
                                            <label class="block text-xs font-semibold gmail-label mb-1">Pieces jointes</label>
                                            <input type="file" id="attachmentsInput" name="attachments[]" multiple class="gmail-input" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar" />
                                            <p class="text-[11px] gmail-hint mt-1">Jusqu'a 5 Mo par fichier, 15 Mo au total.</p>
                                            <div id="attachmentsList" class="gmail-hint mt-1"></div>
                                        </div>
                                    </div>

                                    <div class="lg:col-span-3 gmail-side">
                                        <label class="block text-xs font-semibold gmail-label mb-1">Destinataires</label>
                                        <select id="targetType" name="target_type" class="w-full rounded-md border border-slate-300 bg-white text-slate-700 px-3 py-2 text-sm" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                                            <option value="students" <?php if($bulkForm['target_type']==='students'){ echo 'selected'; } ?>>Tous les etudiants</option>
                                            <option value="teachers" <?php if($bulkForm['target_type']==='teachers'){ echo 'selected'; } ?>>Tous les professeurs</option>
                                            <option value="users" <?php if($bulkForm['target_type']==='users'){ echo 'selected'; } ?>>Tous les utilisateurs</option>
                                            <option value="custom" <?php if($bulkForm['target_type']==='custom'){ echo 'selected'; } ?>>Liste manuelle</option>
                                        </select>

                                        <div id="studentFilters" class="mt-3">
                                            <label class="block text-xs font-semibold gmail-label mb-1">Filtrer par mentions (une ou plusieurs)</label>
                                            <select id="mentionFilters" name="mention_filters[]" multiple class="w-full rounded-md border border-slate-300 bg-white text-slate-700 px-3 py-2 text-xs" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                                                <?php foreach ($mentionOptions as $mentionOption): ?>
                                                    <option value="<?=e((string)$mentionOption)?>" <?php if(in_array((string)$mentionOption, $bulkForm['mention_filters'], true)){ echo 'selected'; } ?>><?=e((string)$mentionOption)?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="text-[11px] gmail-hint mt-1">Maintenir Ctrl (ou Cmd) pour selectionner plusieurs mentions.</p>

                                            <label class="block text-xs font-semibold gmail-label mt-3 mb-1">Filtrer par niveau</label>
                                            <select id="levelFilter" name="level_filter" class="w-full rounded-md border border-slate-300 bg-white text-slate-700 px-3 py-2 text-sm" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                                                <option value="">Tous les niveaux</option>
                                                <?php foreach ($levelOptions as $levelOption):
                                                    $lv = (int)$levelOption;
                                                    $label = $lv <= 3 ? ('Licence ' . $lv) : ('Master ' . ($lv - 3));
                                                ?>
                                                    <option value="<?=$lv?>" <?php if((string)$lv === (string)$bulkForm['level_filter']){ echo 'selected'; } ?>><?=$label?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <label class="block text-xs font-semibold gmail-label mt-3 mb-1">Emails manuels</label>
                                        <textarea id="customRecipients" name="custom_recipients" rows="8" class="w-full rounded-md border border-slate-300 bg-white text-slate-700 px-3 py-2 text-xs" placeholder="adresse1@domaine.com&#10;adresse2@domaine.com" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>><?=e($bulkForm['custom_recipients'])?></textarea>

                                        <p class="text-[11px] gmail-hint mt-2">Clique sur un resultat pour l'ajouter comme destinataire.</p>

                                        <div class="gmail-preview" id="recipientPreviewWrap">
                                            <div class="gmail-preview-head">
                                                <span>Apercu des emails cibles</span>
                                                <span id="recipientPreviewCount">0</span>
                                            </div>
                                            <div id="recipientPreviewList" class="gmail-preview-list">
                                                <div class="gmail-hint">Aucun destinataire a afficher.</div>
                                            </div>
                                        </div>

                                        <button id="sendBtn" type="submit" class="mt-4 w-full px-4 py-2.5 rounded-full bg-blue-600 hover:bg-blue-500 text-white font-medium transition" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                                            <i class="bi bi-send-fill"></i> Envoyer
                                        </button>

                                        <div id="sendingState" class="hidden mt-2 text-xs gmail-hint">Envoi en cours...</div>
                                    </div>
                                </form>

                                <?php
                                    $initialTitle = 'Nouveau message';
                                    $initialItems = [];
                                    if ($currentBox === 'inbox') {
                                        $initialTitle = 'Boite de reception';
                                        $initialItems = $inboxMessages;
                                    } elseif ($currentBox === 'sent') {
                                        $initialTitle = 'Messages envoyes';
                                        $initialItems = $sentMessages;
                                    } elseif ($currentBox === 'drafts') {
                                        $initialTitle = 'Brouillons';
                                    } elseif ($currentBox === 'favorites') {
                                        $initialTitle = 'Favoris';
                                    } elseif ($currentBox === 'trash') {
                                        $initialTitle = 'Corbeille';
                                    }
                                ?>
                                <div class="gmail-mailbox" id="mailboxPanel" <?php if($currentBox === 'compose'){ echo 'style="display:none;"'; } ?>>
                                    <div class="gmail-mailbox-head" id="mailboxHead"><?=e($initialTitle)?></div>
                                    <div id="mailboxList">
                                    <?php if (empty($initialItems)): ?>
                                        <div class="gmail-empty">Aucun message a afficher.</div>
                                    <?php else: ?>
                                        <?php foreach ($initialItems as $msg): ?>
                                            <?php
                                                $msgId = (int)($msg['message_id'] ?? ($msg['mailbox_id'] ?? 0));
                                                $subjectText = (string)($msg['subject'] ?? 'Sans objet');
                                                $createdByText = (string)($msg['created_by'] ?? 'Systeme');
                                                $createdAtText = (string)($msg['created_at'] ?? '');
                                                $previewText = (string)($msg['preview_text'] ?? '');
                                                $previewImage = trim((string)($msg['preview_image'] ?? ''));
                                                $statsText = '';
                                                $total = (int)($msg['recipient_total'] ?? 0);
                                                $sent = (int)($msg['sent_total'] ?? 0);
                                                $failed = (int)($msg['failed_total'] ?? 0);
                                                if ($total > 0 || $sent > 0 || $failed > 0) {
                                                    $statsText = ' • Total: ' . $total . ' | Envoyes: ' . $sent . ' | Echecs: ' . $failed;
                                                }
                                            ?>
                                            <div class="gmail-mail-item" data-mail-id="<?=$msgId?>">
                                                <?php if ($previewImage !== ''): ?>
                                                    <img src="<?=e($previewImage)?>" alt="Apercu image" class="gmail-mail-thumb" loading="lazy" />
                                                <?php endif; ?>
                                                <div class="gmail-mail-main">
                                                    <div class="gmail-mail-subject"><?=e($subjectText)?></div>
                                                    <div class="gmail-mail-meta"><?=e($createdByText)?> • <?=e($createdAtText)?><?=e($statsText)?></div>
                                                    <div class="gmail-mail-preview"><?=e($previewText)?></div>
                                                </div>
                                                <div class="gmail-mail-actions">
                                                    <button type="button" class="gmail-mail-action-btn" data-action="archive" title="Archiver"><i class="bi bi-archive"></i></button>
                                                    <button type="button" class="gmail-mail-action-btn" data-action="trash" title="Corbeille"><i class="bi bi-trash"></i></button>
                                                    <button type="button" class="gmail-mail-action-btn" data-action="mark" title="Marquer lu"><i class="bi bi-envelope-open"></i></button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="gmail-modal" id="mailReadModal" aria-hidden="true">
                            <div class="gmail-modal-card">
                                <div class="gmail-modal-head">
                                    <div>
                                        <h3 class="gmail-modal-title" id="mailReadTitle">Message</h3>
                                        <div class="gmail-modal-meta" id="mailReadMeta"></div>
                                    </div>
                                    <button type="button" class="gmail-modal-close" id="mailReadClose" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="gmail-modal-body" id="mailReadBody"></div>
                            </div>
                        </div>
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
        const studentFilters = document.getElementById('studentFilters');
        const mentionFilters = document.getElementById('mentionFilters');
        const levelFilter = document.getElementById('levelFilter');
        const recipientPreviewWrap = document.getElementById('recipientPreviewWrap');
        const recipientPreviewList = document.getElementById('recipientPreviewList');
        const recipientPreviewCount = document.getElementById('recipientPreviewCount');
        const sendBtn = document.getElementById('sendBtn');
        const sendingState = document.getElementById('sendingState');
        const mailStatus = document.getElementById('mailStatus');
        const attachmentsInput = document.getElementById('attachmentsInput');
        const attachmentsList = document.getElementById('attachmentsList');
        const messageEditor = document.getElementById('messageEditor');
        const messagePlain = document.getElementById('messagePlain');
        const messageHtml = document.getElementById('messageHtml');
        const mailboxPanel = document.getElementById('mailboxPanel');
        const mailboxHead = document.getElementById('mailboxHead');
        const mailboxList = document.getElementById('mailboxList');
        const mailboxSourceText = document.getElementById('mailboxSourceText');
        const mailboxSourceWarning = document.getElementById('mailboxSourceWarning');
        const countInbox = document.getElementById('countInbox');
        const countSent = document.getElementById('countSent');
        const navLinks = document.querySelectorAll('[data-mailbox-nav]');
        const mailReadModal = document.getElementById('mailReadModal');
        const mailReadTitle = document.getElementById('mailReadTitle');
        const mailReadMeta = document.getElementById('mailReadMeta');
        const mailReadBody = document.getElementById('mailReadBody');
        const mailReadClose = document.getElementById('mailReadClose');

        if (!form || !searchInput || !resultsBox || !chipsBox || !customRecipients || !targetType) {
            return;
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/\'/g, '&#039;');
        }

        function updateSourceInfo(source, warning) {
            if (mailboxSourceText) {
                mailboxSourceText.textContent = source === 'imap' ? 'Gmail IMAP' : 'Locale';
            }
            if (mailboxSourceWarning) {
                mailboxSourceWarning.textContent = warning ? (' • ' + warning) : '';
            }
        }

        function setActiveNav(box) {
            navLinks.forEach(function (link) {
                const linkBox = link.getAttribute('data-mailbox-nav');
                const isComposeBtn = link.classList.contains('gmail-compose-btn');
                if (isComposeBtn) {
                    return;
                }
                if (linkBox === box) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }

        function renderMailboxItems(items) {
            if (!mailboxList) {
                return;
            }
            if (!Array.isArray(items) || !items.length) {
                mailboxList.innerHTML = '<div class="gmail-empty">Aucun message a afficher.</div>';
                return;
            }

            mailboxList.innerHTML = items.map(function (msg) {
                var id = Number(msg.message_id || msg.mailbox_id || 0);
                var subject = escapeHtml(msg.subject || 'Sans objet');
                var by = escapeHtml(msg.created_by || 'Systeme');
                var at = escapeHtml(msg.created_at || '');
                var preview = escapeHtml(msg.preview_text || '');
                var previewImageRaw = String(msg.preview_image || '').trim();
                var previewImage = '';
                if (/^data:image\//i.test(previewImageRaw)) {
                    previewImage = escapeHtml(previewImageRaw);
                }
                var stats = '';
                var total = Number(msg.recipient_total || 0);
                var sent = Number(msg.sent_total || 0);
                var failed = Number(msg.failed_total || 0);
                if (total > 0 || sent > 0 || failed > 0) {
                    stats = ' • Total: ' + total + ' | Envoyes: ' + sent + ' | Echecs: ' + failed;
                }

                var thumbHtml = previewImage !== ''
                    ? '<img src="' + previewImage + '" alt="Apercu image" class="gmail-mail-thumb" loading="lazy">'
                    : '';

                return '<div class="gmail-mail-item" data-mail-id="' + id + '">'
                    + thumbHtml
                    + '<div class="gmail-mail-main">'
                    + '<div class="gmail-mail-subject">' + subject + '</div>'
                    + '<div class="gmail-mail-meta">' + by + ' • ' + at + stats + '</div>'
                    + '<div class="gmail-mail-preview">' + preview + '</div>'
                    + '</div>'
                    + '<div class="gmail-mail-actions">'
                    + '<button type="button" class="gmail-mail-action-btn" data-action="archive" title="Archiver"><i class="bi bi-archive"></i></button>'
                    + '<button type="button" class="gmail-mail-action-btn" data-action="trash" title="Corbeille"><i class="bi bi-trash"></i></button>'
                    + '<button type="button" class="gmail-mail-action-btn" data-action="mark" title="Marquer lu"><i class="bi bi-envelope-open"></i></button>'
                    + '</div>'
                    + '</div>';
            }).join('');
        }

        function openMailModal(payload) {
            if (!mailReadModal || !mailReadTitle || !mailReadMeta || !mailReadBody) {
                return;
            }
            mailReadTitle.textContent = payload.subject || 'Message';
            mailReadMeta.textContent = (payload.from || 'Systeme') + ' • ' + (payload.created_at || '');
            mailReadBody.innerHTML = payload.html || '<em>Contenu indisponible.</em>';
            mailReadModal.classList.add('is-open');
            mailReadModal.setAttribute('aria-hidden', 'false');
        }

        function closeMailModal() {
            if (!mailReadModal) {
                return;
            }
            mailReadModal.classList.remove('is-open');
            mailReadModal.setAttribute('aria-hidden', 'true');
        }

        function loadMessageDetail(messageId) {
            if (!messageId || !currentMailboxBox || currentMailboxBox === 'compose') {
                return;
            }

            const url = APP_BASE + '/settings/bulk-email?ajax=mailbox-message&box=' + encodeURIComponent(currentMailboxBox) + '&id=' + encodeURIComponent(String(messageId));
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    return res.json().then(function (json) {
                        if (!res.ok || !json.success) {
                            throw new Error((json && json.error) ? json.error : 'message_failed');
                        }
                        return json;
                    });
                })
                .then(function (payload) {
                    openMailModal(payload);
                })
                .catch(function () {
                    if (typeof Toast !== 'undefined' && typeof Toast.error === 'function') {
                        Toast.error('Impossible d\'ouvrir ce mail.');
                    }
                });
        }

        function switchMailboxView(box, title, items, counts, source, warning, updateUrl) {
            if (box === 'compose') {
                currentMailboxBox = 'compose';
                if (form) {
                    form.style.display = '';
                }
                if (mailboxPanel) {
                    mailboxPanel.style.display = 'none';
                }
            } else {
                currentMailboxBox = box;
                if (form) {
                    form.style.display = 'none';
                }
                if (mailboxPanel) {
                    mailboxPanel.style.display = '';
                }
                if (mailboxHead) {
                    mailboxHead.textContent = title || 'Boite';
                }
                renderMailboxItems(items || []);
            }

            if (counts) {
                if (countInbox) {
                    countInbox.textContent = String(Number(counts.inbox || 0));
                }
                if (countSent) {
                    countSent.textContent = String(Number(counts.sent || 0));
                }
            }

            updateSourceInfo(source || 'local', warning || '');
            setActiveNav(box);

            if (updateUrl) {
                try {
                    window.history.pushState({ box: box }, '', APP_BASE + '/settings/bulk-email?box=' + encodeURIComponent(box));
                } catch (e) {}
            }
        }

        function loadMailboxBox(box) {
            if (box === 'compose') {
                switchMailboxView('compose', 'Nouveau message', [], null, null, null, true);
                return;
            }

            const url = APP_BASE + '/settings/bulk-email?ajax=mailbox-view&box=' + encodeURIComponent(box);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    return res.json().then(function (json) {
                        if (!res.ok || !json.success) {
                            throw new Error((json && json.error) ? json.error : 'mailbox_failed');
                        }
                        return json;
                    });
                })
                .then(function (payload) {
                    switchMailboxView(
                        String(payload.box || box),
                        String(payload.title || ''),
                        Array.isArray(payload.items) ? payload.items : [],
                        payload.counts || null,
                        String(payload.source || 'local'),
                        String(payload.warning || ''),
                        true
                    );
                })
                .catch(function () {
                    if (typeof Toast !== 'undefined' && typeof Toast.error === 'function') {
                        Toast.error('Impossible de charger la boite.');
                    }
                });
        }

        navLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                const box = link.getAttribute('data-mailbox-nav');
                if (!box) {
                    return;
                }
                e.preventDefault();
                loadMailboxBox(box);
            });
        });

        if (mailboxList) {
            mailboxList.addEventListener('click', function (e) {
                const actionBtn = e.target.closest('.gmail-mail-action-btn');
                if (actionBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const action = actionBtn.getAttribute('data-action') || '';
                    if (typeof Toast !== 'undefined' && typeof Toast.info === 'function') {
                        Toast.info('Action "' + action + '" disponible bientot.');
                    }
                    return;
                }

                const row = e.target.closest('.gmail-mail-item[data-mail-id]');
                if (!row) {
                    return;
                }
                const messageId = Number(row.getAttribute('data-mail-id') || 0);
                if (messageId > 0) {
                    loadMessageDetail(messageId);
                }
            });
        }

        if (mailReadClose) {
            mailReadClose.addEventListener('click', closeMailModal);
        }
        if (mailReadModal) {
            mailReadModal.addEventListener('click', function (e) {
                if (e.target === mailReadModal) {
                    closeMailModal();
                }
            });
        }

        const selected = {};
        let debounceTimer = null;
        let previewDebounce = null;
        const inlineImageFiles = {};
        let selectedInlineWrap = null;
        let currentMailboxBox = <?=json_encode($currentBox, JSON_UNESCAPED_UNICODE)?>;

        function setCustomMode() {
            if (targetType.value !== 'custom') {
                targetType.value = 'custom';
                toggleStudentFilters();
            }
        }

        function toggleStudentFilters() {
            if (!studentFilters) {
                return;
            }

            const isStudents = targetType.value === 'students';
            studentFilters.style.display = isStudents ? 'block' : 'none';

            if (mentionFilters) {
                mentionFilters.disabled = !isStudents;
            }

            if (levelFilter) {
                levelFilter.disabled = !isStudents;
            }

            refreshRecipientPreview();
        }

        function syncTextarea() {
            customRecipients.value = Object.keys(selected).join('\n');
            refreshRecipientPreview();
        }

        function selectedMentionValues() {
            if (!mentionFilters) {
                return [];
            }
            return Array.prototype.slice.call(mentionFilters.options)
                .filter(function (option) { return option.selected; })
                .map(function (option) { return option.value; });
        }

        function renderRecipientPreview(items, total) {
            if (!recipientPreviewList || !recipientPreviewCount) {
                return;
            }

            recipientPreviewCount.textContent = String(total || 0);
            recipientPreviewList.innerHTML = '';

            if (!items || !items.length) {
                recipientPreviewList.innerHTML = '<div class="gmail-hint">Aucun destinataire a afficher.</div>';
                return;
            }

            items.forEach(function (email) {
                const row = document.createElement('div');
                row.className = 'gmail-preview-item';
                row.textContent = email;
                recipientPreviewList.appendChild(row);
            });

            if ((total || 0) > items.length) {
                const more = document.createElement('div');
                more.className = 'gmail-hint';
                more.textContent = '+' + ((total || 0) - items.length) + ' autres...';
                recipientPreviewList.appendChild(more);
            }
        }

        function refreshRecipientPreview() {
            if (!recipientPreviewWrap) {
                return;
            }

            if (previewDebounce) {
                clearTimeout(previewDebounce);
            }

            previewDebounce = setTimeout(function () {
                const params = new URLSearchParams();
                params.set('ajax', 'recipient-preview');
                params.set('target_type', targetType.value);
                params.set('custom_recipients', customRecipients.value || '');

                if (levelFilter && levelFilter.value) {
                    params.set('level_filter', levelFilter.value);
                }

                selectedMentionValues().forEach(function (mention) {
                    params.append('mention_filters[]', mention);
                });

                fetch(APP_BASE + '/settings/bulk-email?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) {
                        return res.json().then(function (json) {
                            if (!res.ok || !json.success) {
                                throw new Error((json && json.error) ? json.error : 'preview_failed');
                            }
                            return json;
                        });
                    })
                    .then(function (payload) {
                        renderRecipientPreview(Array.isArray(payload.items) ? payload.items : [], Number(payload.total || 0));
                    })
                    .catch(function () {
                        renderRecipientPreview([], 0);
                    });
            }, 180);
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

            var filteredItems = (Array.isArray(items) ? items : []).filter(function (item) {
                var email = String(item && item.email ? item.email : '').toLowerCase().trim();
                return email !== '' && !selected[email];
            });

            if (!filteredItems.length) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-xs text-slate-400';
                empty.textContent = 'Aucun resultat';
                resultsBox.appendChild(empty);
                resultsBox.classList.remove('hidden');
                return;
            }

            filteredItems.forEach(function (item) {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'w-full text-left px-3 py-2 gmail-result-item';
                row.innerHTML =
                    '<div class="gmail-result-name">' + (item.name || item.email) + '</div>' +
                    '<div class="gmail-result-meta">' + item.email + ' - ' + (item.source || '') + '</div>';
                row.addEventListener('click', function () {
                    addRecipient(item);
                });
                resultsBox.appendChild(row);
            });

            resultsBox.classList.remove('hidden');
        }

        function parseJsonFromText(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                const start = text.indexOf('{');
                const end = text.lastIndexOf('}');
                if (start !== -1 && end > start) {
                    return JSON.parse(text.slice(start, end + 1));
                }
                throw new Error('invalid_json');
            }
        }

        function updateMessageFields() {
            if (!messageEditor || !messagePlain || !messageHtml) {
                return;
            }
            const plainClone = messageEditor.cloneNode(true);
            plainClone.querySelectorAll('.inline-image-remove, .inline-image-handle').forEach(function (el) {
                el.remove();
            });
            const plain = (plainClone.innerText || '').replace(/\u00a0/g, ' ').trim();
            messagePlain.value = plain;
            messageHtml.value = messageEditor.innerHTML || '';
        }

        function getUsedInlineImageIds() {
            if (!messageEditor) {
                return [];
            }
            const images = messageEditor.querySelectorAll('img[data-inline-id]');
            const ids = [];
            images.forEach(function (img) {
                const id = img.getAttribute('data-inline-id');
                if (id) {
                    ids.push(id);
                }
            });
            return ids;
        }

        function clearInlineSelection() {
            if (selectedInlineWrap) {
                selectedInlineWrap.classList.remove('is-selected');
            }
            selectedInlineWrap = null;
        }

        function selectInlineWrap(wrap) {
            if (!wrap) {
                clearInlineSelection();
                return;
            }

            if (selectedInlineWrap && selectedInlineWrap !== wrap) {
                selectedInlineWrap.classList.remove('is-selected');
            }
            selectedInlineWrap = wrap;
            selectedInlineWrap.classList.add('is-selected');
        }

        function removeInlineWrap(wrap) {
            if (!wrap) {
                return;
            }

            const img = wrap.querySelector('img[data-inline-id]');
            if (img) {
                const id = img.getAttribute('data-inline-id');
                if (id && inlineImageFiles[id]) {
                    delete inlineImageFiles[id];
                }
            }

            wrap.remove();
            if (selectedInlineWrap === wrap) {
                selectedInlineWrap = null;
            }
            updateMessageFields();
        }

        function insertNodeAtCaret(node) {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) {
                if (messageEditor) {
                    messageEditor.appendChild(node);
                }
                return;
            }

            const range = sel.getRangeAt(0);
            range.deleteContents();
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
        }

        function bindInlineImageInteractions(wrap) {
            if (!wrap) {
                return;
            }

            const img = wrap.querySelector('img[data-inline-id]');
            const removeBtn = wrap.querySelector('.inline-image-remove');
            const handle = wrap.querySelector('.inline-image-handle');

            if (!img || !removeBtn || !handle) {
                return;
            }

            wrap.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                selectInlineWrap(wrap);
            });

            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                removeInlineWrap(wrap);
            });

            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                selectInlineWrap(wrap);

                const startX = e.clientX;
                const startWidth = img.getBoundingClientRect().width;
                const minWidth = 80;

                function onMove(moveEvent) {
                    if (!messageEditor) {
                        return;
                    }

                    const delta = moveEvent.clientX - startX;
                    const editorRect = messageEditor.getBoundingClientRect();
                    const maxWidth = Math.max(140, editorRect.width - 32);
                    const nextWidth = Math.max(minWidth, Math.min(maxWidth, startWidth + delta));

                    img.style.width = Math.round(nextWidth) + 'px';
                    img.style.height = 'auto';
                    img.style.maxWidth = '100%';
                    img.setAttribute('data-inline-width', String(Math.round(nextWidth)));
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    updateMessageFields();
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        }

        function createInlineImageWrap(file, inlineId) {
            const wrap = document.createElement('span');
            wrap.className = 'inline-image-wrap';
            wrap.setAttribute('contenteditable', 'false');

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.setAttribute('data-inline-id', inlineId);

            const defaultWidth = messageEditor
                ? Math.max(160, Math.min(420, Math.round(messageEditor.getBoundingClientRect().width - 36)))
                : 320;
            img.style.width = defaultWidth + 'px';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            img.style.borderRadius = '8px';
            img.style.display = 'block';
            img.setAttribute('data-inline-width', String(defaultWidth));

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'inline-image-remove';
            removeBtn.setAttribute('aria-label', 'Supprimer image');
            removeBtn.textContent = 'x';

            const handle = document.createElement('span');
            handle.className = 'inline-image-handle';

            wrap.appendChild(img);
            wrap.appendChild(removeBtn);
            wrap.appendChild(handle);

            bindInlineImageInteractions(wrap);
            return wrap;
        }

        function handleDroppedImages(fileList) {
            if (!messageEditor || !fileList || !fileList.length) {
                return;
            }

            const files = Array.prototype.slice.call(fileList).filter(function (f) {
                return /^image\//i.test(String(f.type || ''));
            });

            if (!files.length) {
                return;
            }

            files.forEach(function (file, idx) {
                const inlineId = 'img_' + Date.now() + '_' + idx + '_' + Math.floor(Math.random() * 1000);
                inlineImageFiles[inlineId] = file;

                const wrap = createInlineImageWrap(file, inlineId);
                insertNodeAtCaret(wrap);

                const spacer = document.createElement('br');
                insertNodeAtCaret(spacer);

                selectInlineWrap(wrap);
            });

            updateMessageFields();
        }

        function renderAttachmentsList() {
            if (!attachmentsInput || !attachmentsList) {
                return;
            }

            const files = attachmentsInput.files ? Array.prototype.slice.call(attachmentsInput.files) : [];
            if (!files.length) {
                attachmentsList.textContent = 'Aucune piece jointe.';
                return;
            }

            const names = files.map(function (f) {
                const sizeKb = Math.round((Number(f.size || 0) / 1024) * 10) / 10;
                return f.name + ' (' + sizeKb + ' Ko)';
            });
            attachmentsList.textContent = names.join(' | ');
        }

        function fetchRecipients(term) {
            const url = APP_BASE + '/settings/bulk-email?ajax=recipient-search&q=' + encodeURIComponent(term);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    return res.text().then(function (text) {
                        const payload = parseJsonFromText(text);

                        if (!res.ok) {
                            throw new Error(payload.message || payload.error || 'http_' + res.status);
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

        customRecipients.addEventListener('input', function () {
            refreshRecipientPreview();
        });

        if (mentionFilters) {
            mentionFilters.addEventListener('change', function () {
                refreshRecipientPreview();
            });
        }

        if (levelFilter) {
            levelFilter.addEventListener('change', function () {
                refreshRecipientPreview();
            });
        }

        targetType.addEventListener('change', function () {
            toggleStudentFilters();
        });

        if (attachmentsInput) {
            attachmentsInput.addEventListener('change', renderAttachmentsList);
        }

        if (messageEditor) {
            messageEditor.addEventListener('input', updateMessageFields);

            messageEditor.addEventListener('click', function (e) {
                if (!e.target.closest('.inline-image-wrap')) {
                    clearInlineSelection();
                }
            });

            messageEditor.addEventListener('keydown', function (e) {
                const key = e.key || '';
                if ((key === 'Delete' || key === 'Backspace') && selectedInlineWrap) {
                    e.preventDefault();
                    removeInlineWrap(selectedInlineWrap);
                }
            });

            messageEditor.addEventListener('dragover', function (e) {
                e.preventDefault();
                messageEditor.classList.add('dragover');
            });

            messageEditor.addEventListener('dragleave', function () {
                messageEditor.classList.remove('dragover');
            });

            messageEditor.addEventListener('drop', function (e) {
                e.preventDefault();
                messageEditor.classList.remove('dragover');
                handleDroppedImages(e.dataTransfer ? e.dataTransfer.files : []);
            });
        }

        document.addEventListener('click', function (e) {
            if (!messageEditor) {
                return;
            }
            if (!messageEditor.contains(e.target)) {
                clearInlineSelection();
            }
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

            updateMessageFields();
            if (messagePlain && !String(messagePlain.value || '').trim()) {
                showStatus({ success: false, message: 'Le message est obligatoire.' });
                return;
            }

            const payload = new FormData(form);

            const usedIds = getUsedInlineImageIds();
            usedIds.forEach(function (id) {
                if (inlineImageFiles[id]) {
                    payload.append('inline_images[]', inlineImageFiles[id], inlineImageFiles[id].name || ('inline_' + id + '.png'));
                    payload.append('inline_image_ids[]', id);
                }
            });

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
                        const json = parseJsonFromText(text);
                        if (!res.ok) {
                            throw new Error(json.message || json.error || 'http_' + res.status);
                        }
                        return json;
                    });
                })
                .then(function (result) {
                    showStatus(result);
                    if (result && result.success) {
                        setTimeout(function () {
                            loadMailboxBox('inbox');
                        }, 900);
                    }
                })
                .catch(function (err) {
                    const msg = (err && err.message && err.message !== 'invalid_json')
                        ? err.message
                        : 'Reponse serveur invalide ou connexion interrompue pendant l\'envoi.';
                    showStatus({ success: false, message: msg });
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

        window.addEventListener('popstate', function (event) {
            const boxFromState = event && event.state && event.state.box ? String(event.state.box) : '';
            if (boxFromState) {
                loadMailboxBox(boxFromState);
                return;
            }

            const params = new URLSearchParams(window.location.search || '');
            const boxFromUrl = params.get('box') || 'compose';
            loadMailboxBox(boxFromUrl);
        });

        setActiveNav(<?=json_encode($currentBox, JSON_UNESCAPED_UNICODE)?>);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMailModal();
            }
        });

        <?php if ($currentBox !== 'compose') { ?>
        loadMailboxBox(<?=json_encode($currentBox, JSON_UNESCAPED_UNICODE)?>);
        <?php } ?>

        parseTextareaRecipients();
        toggleStudentFilters();
        refreshRecipientPreview();
        renderAttachmentsList();
        updateMessageFields();
    })();
    </script>
</body>
</html>
