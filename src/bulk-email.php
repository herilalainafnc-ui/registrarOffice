<?php
// Pour les requêtes AJAX : supprimer les erreurs et buffériser AVANT tout require
// (index.php active display_errors=1 — ceci évite que des warnings polluent le JSON)
if (isset($_GET['ajax']) && $_GET['ajax'] !== '') {
    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');
    @error_reporting(0);
    ob_start();
}

require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$app_base = defined('APP_BASE') ? APP_BASE : '';
$currentUser = currentUser();
$niveauBoite = (int)($currentUser['level'] ?? 99);
/* Enseignants et chefs de mention (demande du 19/10/2026) : la meme fenetre
   d'envoi, mais depuis l'adresse Google de leur connexion, vers des personnes
   ou leurs etudiants ; ni la boite no-reply, ni les groupes de l'universite.
   Leurs envois sont dans leurs « Messages envoyes » de Gmail et dans « Mes
   envois » ici. Voir data/courriel.enseignant.php. */
$modeEnseignant = $niveauBoite > 3 && function_exists('aEspaceEnseignant') && aEspaceEnseignant();
$canUseBulkEmail = $niveauBoite <= 3 || $modeEnseignant;
/* La garde des autres comptes (comptabilite, media…) : ici et non dans le
   controleur, qui n'a pas la base pour savoir qui enseigne. */
if (!$canUseBulkEmail) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(403);
    if (($_GET['ajax'] ?? '') !== '' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'forbidden', 'erreur' => 'Accès refusé.', 'message' => 'Accès refusé.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>403 - Acces refuse</title>'
        . '<style>body{margin:0;font-family:Arial,sans-serif;background:#0a1628;color:#e8f1f8;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}.card{max-width:560px;background:#0d1f3c;border:1px solid #1a3a5c;border-radius:12px;padding:24px}h1{margin:0 0 8px;color:#4e9ede}p{margin:0;line-height:1.5}</style></head><body>'
        . '<div class="card"><h1>403 - Acces refuse</h1><p>La boîte mail est réservée au registrariat et aux enseignants.</p></div>'
        . '</body></html>';
    exit;
}
$expediteurEnseignant = null;
if ($modeEnseignant) {
    require_once __DIR__ . '/../data/courriel.enseignant.php';
    $expediteurEnseignant = courrielEnseignantExpediteur($dtb, is_array($currentUser) ? $currentUser : null);
}
/* L'historique des envois : ceux de no-reply (NULL) pour le registrariat, les
   siens pour un enseignant ('' sans connexion Google : aucun). */
$filtreExpediteur = $modeEnseignant ? (string)$expediteurEnseignant['adresse'] : null;

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
    if ($activeSessionId > 0 && !$modeEnseignant) {
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

$currentBox = strtolower(trim((string)($_GET['box'] ?? 'inbox')));
/* Dossiers de la boite Gmail (data/boite.gmail.php), plus « groupes »
   (historique des envois groupes) et « compose ». « favorites » : ancien nom
   des messages suivis. */
$allowedBoxes = ['compose', 'inbox', 'starred', 'important', 'sent', 'drafts', 'all', 'spam', 'trash', 'groupes', 'favorites'];
if (!in_array($currentBox, $allowedBoxes, true)) {
    $currentBox = 'inbox';
}
if ($currentBox === 'favorites') {
    $currentBox = 'starred';
}
if ($modeEnseignant && $currentBox !== 'compose') {
    $currentBox = 'groupes'; // un enseignant n'a ici que « Mes envois »
}

/* =============================================================================
   Boite Gmail de no-reply@zurcher.edu.mg : points d'acces de src/js/boite-gmail.js.
   Le client IMAP de data/imap.client.php remplace l'extension PHP imap, absente
   du serveur (la boite ne s'affichait donc jamais). Registrariat seul, comme la
   page. La session est fermee avant de parler a Gmail : les requetes de la page
   ne s'attendent pas les unes les autres.
   ========================================================================== */
$ajaxGmail = (string) ($_GET['ajax'] ?? '');
if (in_array($ajaxGmail, ['gmail-liste', 'gmail-complements', 'gmail-message', 'gmail-action', 'gmail-piece'], true)) {
    require_once __DIR__ . '/../data/boite.gmail.php';
    while (ob_get_level() > 0) { ob_end_clean(); }
    $repondreJson = static function (array $donnees, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    };
    if (!$canUseBulkEmail || $modeEnseignant) {
        $repondreJson(['ok' => false, 'erreur' => 'Accès réservé au registrariat.'], 403);
    }
    if (!boiteGmailConfiguree()) {
        $repondreJson(['ok' => false, 'erreur' => 'La boîte Gmail n’est pas configurée sur ce serveur.'], 503);
    }
    $dossierGmail = (string) ($_REQUEST['dossier'] ?? 'inbox');
    if ($ajaxGmail === 'gmail-action') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('verify_csrf') || !verify_csrf($_POST['csrf_token'] ?? null)) {
            $repondreJson(['ok' => false, 'erreur' => 'Jeton de sécurité invalide : rechargez la page.'], 403);
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    try {
        if ($ajaxGmail === 'gmail-liste') {
            $repondreJson(['ok' => true] + boiteGmailListe($dossierGmail, (int) ($_GET['page'] ?? 0), mb_substr((string) ($_GET['q'] ?? ''), 0, 200)));
        }
        if ($ajaxGmail === 'gmail-complements') {
            $repondreJson(['ok' => true] + boiteGmailComplements($dossierGmail, explode(',', (string) ($_GET['uids'] ?? ''))));
        }
        if ($ajaxGmail === 'gmail-message') {
            $repondreJson(['ok' => true] + boiteGmailMessage($dossierGmail, (int) ($_GET['uid'] ?? 0)));
        }
        if ($ajaxGmail === 'gmail-action') {
            boiteGmailAction($dossierGmail, (int) ($_POST['uid'] ?? 0), (string) ($_POST['action'] ?? ''));
            $repondreJson(['ok' => true]);
        }
        /* gmail-piece : le fichier lui-meme, toujours en telechargement (jamais
           affiche dans la page, qui pourrait sinon executer un HTML joint). */
        $piece = boiteGmailPiece($dossierGmail, (int) ($_GET['uid'] ?? 0), (string) ($_GET['section'] ?? ''));
        $nomPiece = str_replace(["\r", "\n", '"'], '', $piece['nom']);
        $typePiece = preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $piece['type']) ? $piece['type'] : 'application/octet-stream';
        header('Content-Type: ' . $typePiece);
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\x20-\x7E]/', '_', $nomPiece) . '"; filename*=UTF-8\'\'' . rawurlencode($nomPiece));
        header('Content-Length: ' . strlen($piece['donnees']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $piece['donnees'];
        exit;
    } catch (Throwable $e) {
        error_log('Boite Gmail (' . $ajaxGmail . ') : ' . $e->getMessage());
        $repondreJson(['ok' => false, 'erreur' => $e->getMessage()], 502);
    }
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
                    sender_email VARCHAR(190) NULL DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_mailbox_folder_date (mailbox_folder, created_at),
                    INDEX idx_mailbox_sender (sender_email, mailbox_folder, mailbox_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"; // sender_email : data/migration_courriel_enseignant.sql

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
    /* $senderEmail : NULL pour un envoi de no-reply, l'adresse de l'enseignant sinon. */
    function bulkMailboxInsert($dtb, $folder, $subject, $preview, $recipientTotal, $sentTotal, $failedTotal, $createdBy, $messageHtml = '', $previewImage = '', $senderEmail = null) {
        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare('INSERT INTO t_bulk_email_mailbox (mailbox_folder, subject, preview_text, preview_image, message_html, recipient_total, sent_total, failed_total, created_by, sender_email) VALUES (:folder, :subject, :preview_text, :preview_image, :message_html, :recipient_total, :sent_total, :failed_total, :created_by, :sender_email)');
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
                'sender_email' => $senderEmail === null ? null : substr(strtolower(trim((string)$senderEmail)), 0, 190),
            ]);
        } catch (Throwable $e) {
            // Keep email sending functional even if mailbox logging fails.
        }
    }
}

if (!function_exists('bulkMailboxFetchByFolder')) {
    /* $expediteur : NULL, les envois de no-reply ; une adresse, ceux de cet enseignant. */
    function bulkMailboxFetchByFolder($dtb, $folder, $limit = 40, $expediteur = null) {
        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare('SELECT mailbox_id, subject, preview_text, preview_image, message_html, recipient_total, sent_total, failed_total, created_by, created_at FROM t_bulk_email_mailbox WHERE mailbox_folder = :folder AND sender_email <=> :expediteur ORDER BY mailbox_id DESC LIMIT :limit');
            $stmt->bindValue(':folder', (string)$folder, PDO::PARAM_STR);
            $stmt->bindValue(':expediteur', $expediteur, $expediteur === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':limit', max(1, min(200, (int)$limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('bulkMailboxCountByFolder')) {
    function bulkMailboxCountByFolder($dtb, $expediteur = null) {
        $counts = ['inbox' => 0, 'sent' => 0];
        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare("SELECT mailbox_folder, COUNT(*) AS total FROM t_bulk_email_mailbox WHERE mailbox_folder IN ('inbox', 'sent') AND sender_email <=> :expediteur GROUP BY mailbox_folder");
            $stmt->bindValue(':expediteur', $expediteur, $expediteur === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        /* Ancien chemin par l'extension PHP imap (absente du serveur) : la
           boite Gmail passe desormais par data/boite.gmail.php. Ce chemin ne
           sert plus qu'a l'historique local des envois groupes. */
        return false;
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
    function normalizeUtf8Text($value) {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        // Remove control chars that often break JSON rendering.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $value);

        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
                if (is_string($converted) && $converted !== '') {
                    $value = $converted;
                }
            }

            if (function_exists('iconv') && (!function_exists('mb_check_encoding') || !mb_check_encoding($value, 'UTF-8'))) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                if (is_string($converted) && $converted !== '') {
                    $value = $converted;
                }
            }
        }

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    function decodeImapSubject($value) {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return normalizeUtf8Text($decoded);
            }
        }

        if (function_exists('imap_utf8')) {
            $decoded = @imap_utf8($value);
            if (is_string($decoded) && $decoded !== '') {
                return normalizeUtf8Text($decoded);
            }
        }

        return normalizeUtf8Text($value);
    }
}

if (!function_exists('openImapFolder')) {
    function normalizeImapFolderKey($value) {
        $value = strtolower(trim((string)$value));
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') {
                $value = strtolower($ascii);
            }
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string)$value);
    }

    function sanitizeImapMailboxSegment($value) {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        // Remove null bytes and ASCII control chars that break imap_open().
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return trim((string)$value);
    }

    function clearImapRuntimeErrors() {
        if (function_exists('imap_errors')) {
            @imap_errors();
        }
        if (function_exists('imap_alerts')) {
            @imap_alerts();
        }
    }

    function buildImapMailboxPath() {
        $host = defined('MAILBOX_IMAP_HOST') ? sanitizeImapMailboxSegment((string)MAILBOX_IMAP_HOST) : '';
        $port = defined('MAILBOX_IMAP_PORT') ? (int)MAILBOX_IMAP_PORT : 993;
        $encryption = defined('MAILBOX_IMAP_ENCRYPTION') ? strtolower(trim((string)MAILBOX_IMAP_ENCRYPTION)) : 'ssl';

        if ($host === '') {
            return '';
        }

        $flags = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        return '{' . $host . ':' . $port . $flags . '}';
    }

    function fetchImapFolderMap() {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $username = defined('MAILBOX_IMAP_USERNAME') ? (string)MAILBOX_IMAP_USERNAME : '';
        $password = defined('MAILBOX_IMAP_PASSWORD') ? (string)MAILBOX_IMAP_PASSWORD : '';
        $mailboxPath = buildImapMailboxPath();

        $cache = [];
        if ($username === '' || $password === '' || $mailboxPath === '') {
            return $cache;
        }

        try {
            $imap = @imap_open($mailboxPath, $username, $password, OP_HALFOPEN);
        } catch (Throwable $e) {
            $imap = false;
        }
        if ($imap === false) {
            clearImapRuntimeErrors();
            return $cache;
        }

        $folders = @imap_list($imap, $mailboxPath, '*');
        if (is_array($folders)) {
            foreach ($folders as $fullName) {
                $fullName = (string)$fullName;
                $localNameRaw = $fullName;
                $bracePos = strpos($localNameRaw, '}');
                if ($bracePos !== false) {
                    $localNameRaw = substr($localNameRaw, $bracePos + 1);
                }

                $localNameRaw = sanitizeImapMailboxSegment($localNameRaw);
                if ($localNameRaw === '') {
                    continue;
                }

                $localNameDecoded = $localNameRaw;
                if (function_exists('imap_utf7_decode')) {
                    $decodedName = @imap_utf7_decode($localNameRaw);
                    if (is_string($decodedName) && $decodedName !== '') {
                        $localNameDecoded = $decodedName;
                    }
                }

                $decodedSanitized = sanitizeImapMailboxSegment($localNameDecoded);
                $normalizedRaw = normalizeImapFolderKey($localNameRaw);
                $normalizedDecoded = normalizeImapFolderKey($decodedSanitized);

                if ($normalizedRaw !== '') {
                    $cache[$normalizedRaw] = (string)$localNameRaw;
                }
                if ($normalizedDecoded !== '') {
                    $cache[$normalizedDecoded] = (string)$localNameRaw;
                }
            }
        }

        @imap_close($imap);
        clearImapRuntimeErrors();

        return $cache;
    }

    function probeImapFolderCount($folderName) {
        $folderName = sanitizeImapMailboxSegment($folderName);
        if ($folderName === '') {
            return ['ok' => false, 'count' => -1];
        }

        $imap = openImapFolder($folderName);
        if ($imap === false) {
            return ['ok' => false, 'count' => -1];
        }

        $count = (int)@imap_num_msg($imap);
        @imap_close($imap);
        clearImapRuntimeErrors();

        return ['ok' => true, 'count' => max(0, $count)];
    }

    function resolveImapFolderName($folderType) {
        $folderType = strtolower(trim((string)$folderType));

        if ($folderType === 'inbox') {
            $configured = defined('MAILBOX_IMAP_INBOX_FOLDER') ? sanitizeImapMailboxSegment((string)MAILBOX_IMAP_INBOX_FOLDER) : 'INBOX';
            return $configured !== '' ? $configured : 'INBOX';
        }

        if ($folderType !== 'sent') {
            return '';
        }

        $candidates = [];
        $configuredSent = defined('MAILBOX_IMAP_SENT_FOLDER') ? sanitizeImapMailboxSegment((string)MAILBOX_IMAP_SENT_FOLDER) : '';
        if ($configuredSent !== '') {
            $candidates[] = $configuredSent;
        }
        $candidates[] = '[Gmail]/Sent Mail';
        $candidates[] = '[Gmail]/Sent';
        $candidates[] = '[Gmail]/Messages envoyes';
        $candidates[] = '[Google Mail]/Sent Mail';
        $candidates[] = '[Google Mail]/Sent';
        $candidates[] = '[Google Mail]/Messages envoyes';
        $candidates[] = '[Google Mail]/Gesendet';
        $candidates[] = 'Sent';
        $candidates[] = 'Sent Mail';
        $candidates[] = 'INBOX.Sent';

        $folderMap = fetchImapFolderMap();
        $probeFolders = [];

        if (!empty($folderMap)) {
            foreach ($candidates as $candidate) {
                $candidate = sanitizeImapMailboxSegment($candidate);
                if ($candidate === '') {
                    continue;
                }
                $key = normalizeImapFolderKey($candidate);
                if (isset($folderMap[$key])) {
                    $probeFolders[] = (string)$folderMap[$key];
                }
            }

            foreach ($folderMap as $normalized => $realName) {
                if (strpos($normalized, 'sent') !== false || strpos($normalized, 'envoy') !== false || strpos($normalized, 'gesendet') !== false) {
                    $probeFolders[] = (string)$realName;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = sanitizeImapMailboxSegment($candidate);
            if ($candidate !== '') {
                $probeFolders[] = $candidate;
            }
        }

        $seen = [];
        $bestFolder = '';
        $bestCount = -1;
        $firstAccessible = '';

        foreach ($probeFolders as $probeFolder) {
            $probeFolder = sanitizeImapMailboxSegment($probeFolder);
            if ($probeFolder === '') {
                continue;
            }
            if (isset($seen[$probeFolder])) {
                continue;
            }
            $seen[$probeFolder] = true;

            $probe = probeImapFolderCount($probeFolder);
            if (($probe['ok'] ?? false) !== true) {
                continue;
            }

            if ($firstAccessible === '') {
                $firstAccessible = $probeFolder;
            }

            $count = (int)($probe['count'] ?? 0);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestFolder = $probeFolder;
            }
        }

        if ($bestFolder !== '') {
            return $bestFolder;
        }
        if ($firstAccessible !== '') {
            return $firstAccessible;
        }

        return $configuredSent !== '' ? $configuredSent : '[Gmail]/Sent Mail';
    }

    function openImapFolder($folderName) {
        $username = defined('MAILBOX_IMAP_USERNAME') ? (string)MAILBOX_IMAP_USERNAME : '';
        $password = defined('MAILBOX_IMAP_PASSWORD') ? (string)MAILBOX_IMAP_PASSWORD : '';
        $mailboxPath = buildImapMailboxPath();
        $folderName = sanitizeImapMailboxSegment($folderName);

        if ($mailboxPath === '' || $folderName === '' || $username === '' || $password === '') {
            return false;
        }

        try {
            $imap = @imap_open($mailboxPath . $folderName, $username, $password);
        } catch (Throwable $e) {
            $imap = false;
        }
        if ($imap === false) {
            clearImapRuntimeErrors();
        }

        return $imap;
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
        clearImapRuntimeErrors();

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
        clearImapRuntimeErrors();
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
            'inbox' => resolveImapFolderName('inbox'),
            'sent' => resolveImapFolderName('sent'),
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
        clearImapRuntimeErrors();

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
    /* $expediteur : comme bulkMailboxFetchByFolder ; un enseignant ne lit que ses envois. */
    function fetchLocalMailboxMessageDetail($dtb, $messageId, $expediteur = null) {
        $messageId = (int)$messageId;
        if ($messageId <= 0) {
            return ['success' => false, 'error' => 'invalid_id'];
        }

        try {
            ensureBulkMailboxTable($dtb);
            $stmt = $dtb->prepare('SELECT subject, preview_text, preview_image, message_html, created_by, created_at, recipient_total, sent_total, failed_total FROM t_bulk_email_mailbox WHERE mailbox_id = :id AND sender_email <=> :expediteur LIMIT 1');
            $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
            $stmt->bindValue(':expediteur', $expediteur, $expediteur === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
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
    function getMailboxViewPayload($dtb, $box, $canUseBulkEmail, $expediteur = null) {
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

        /* Un enseignant : ses envois, jamais la boite no-reply. */
        if ($expediteur !== null) {
            $payload['counts'] = bulkMailboxCountByFolder($dtb, $expediteur);
            $payload['items'] = $box === 'sent' ? bulkMailboxFetchByFolder($dtb, 'sent', 60, $expediteur) : [];
            return $payload;
        }

        if (defined('MAILBOX_IMAP_ENABLED') && MAILBOX_IMAP_ENABLED === true && !function_exists('imap_open')) {
            $payload['source'] = 'local';
            $payload['warning'] = 'Extension PHP IMAP non activee sur le serveur.';
        }

        if (isExternalImapMailboxEnabled()) {
            $payload['source'] = 'imap';
            $payload['counts'] = bulkMailboxCountByFolder($dtb);

            $imapInboxFolder = resolveImapFolderName('inbox');
            $imapSentFolder = resolveImapFolderName('sent');
            $selectedResult = ['items' => [], 'count' => 0, 'error' => ''];
            if ($box === 'inbox') {
                $selectedResult = fetchImapMailboxItems($imapInboxFolder, 25);
                if (($selectedResult['error'] ?? '') === '') {
                    $payload['counts']['inbox'] = (int)($selectedResult['count'] ?? 0);
                }
            } elseif ($box === 'sent') {
                $selectedResult = fetchImapMailboxItems($imapSentFolder, 25);
                if (($selectedResult['error'] ?? '') === '') {
                    $payload['counts']['sent'] = (int)($selectedResult['count'] ?? 0);
                }
            }

            if (($selectedResult['error'] ?? '') !== '' && in_array($box, ['inbox', 'sent'], true)) {
                $payload['source'] = 'local';
                $payload['warning'] = 'Connexion IMAP indisponible. Affichage de la boite locale.';
            } else {
                if ($box === 'inbox') {
                    $payload['items'] = (array)($selectedResult['items'] ?? []);
                } elseif ($box === 'sent') {
                    $payload['items'] = (array)($selectedResult['items'] ?? []);
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

if ($canUseBulkEmail && !$modeEnseignant) {
    // Render quickly with local cache; IMAP refresh is done asynchronously via AJAX.
    $mailboxCounts = bulkMailboxCountByFolder($dtb);

    if (isExternalImapMailboxEnabled()) {
        $mailboxSource = 'imap';
        $mailboxWarning = 'Chargement IMAP en cours...';
    } elseif (defined('MAILBOX_IMAP_ENABLED') && MAILBOX_IMAP_ENABLED === true && !function_exists('imap_open')) {
        $mailboxSource = 'local';
        $mailboxWarning = 'Extension PHP IMAP non activee sur le serveur.';
    }

    if ($currentBox === 'inbox') {
        $inboxMessages = bulkMailboxFetchByFolder($dtb, 'inbox', 30);
    } elseif ($currentBox === 'sent') {
        $sentMessages = bulkMailboxFetchByFolder($dtb, 'sent', 30);
    }
}

if (!function_exists('collectBulkRecipientEmails')) {
    function collectBulkRecipientEmails($dtb, $activeSessionId, $targetType, $customRecipientsRaw = '', $mentionFilters = [], $levelFilter = '') {
        $result = [
            'emails' => [],
            'recipient_meta' => [],
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

        if ($targetType === 'students' || $targetType === 'students_unregularized') {
            if ((int)$activeSessionId <= 0) {
                $result['error'] = 'Aucune session active trouvee.';
                return $result;
            }

                        $sql = "SELECT DISTINCT TRIM(std.student_email) AS email, TRIM(std.student_id) AS student_id,
                                                     TRIM(CONCAT_WS(' ', NULLIF(TRIM(IFNULL(std.student_nom, '')), ''), NULLIF(TRIM(IFNULL(std.student_prenom, '')), ''))) AS full_name
                    FROM tbl_2024_etudiant std
                    INNER JOIN t_2024_inscription_session ins ON ins.student_id = std.student_id
                    WHERE ins.session_id = :active_session_id
                      AND std.remove != 1
                      AND std.student_email IS NOT NULL
                      AND TRIM(std.student_email) <> ''";

                        if ($targetType === 'students_unregularized') {
                            // Cible metier: etudiants debitaires (solde > 0), filtre applique apres lecture finance.
                        }

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
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result['emails'] = [];
            $financeStudentKeys = [];
            foreach ($rows as $row) {
                $email = strtolower(trim((string)($row['email'] ?? '')));
                if ($email === '') {
                    continue;
                }

                $studentId = trim((string)($row['student_id'] ?? ''));
                $fullName = trim((string)($row['full_name'] ?? ''));
                $studentIdDigits = preg_replace('/\D+/', '', $studentId);
                $financeStudentId = ctype_digit((string)$studentIdDigits) ? (string)((int)$studentIdDigits) : '';
                $result['emails'][] = $email;
                $result['recipient_meta'][$email] = [
                    'student_id' => $studentId,
                    'finance_student_id' => $financeStudentId,
                    'full_name' => $fullName,
                    'remaining_amount' => 0.0,
                ];

                if ($studentId !== '') {
                    $financeStudentKeys[$studentId] = true;
                }
                if ($financeStudentId !== '') {
                    $financeStudentKeys[$financeStudentId] = true;
                }
            }

            if ($targetType === 'students_unregularized' && !empty($financeStudentKeys)) {
                $financeKeys = array_values(array_keys($financeStudentKeys));
                $placeholders = [];
                $financeParams = [];
                foreach ($financeKeys as $idx => $sid) {
                    $key = 'sid_' . $idx;
                    $placeholders[] = ':' . $key;
                    $financeParams[$key] = $sid;
                }

                $hasBalanceColumn = false;
                try {
                    $colStmt = $dtb->query("SHOW COLUMNS FROM finance LIKE 'balance_amount'");
                    if ($colStmt && $colStmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasBalanceColumn = true;
                    }
                } catch (Throwable $e) {
                    $hasBalanceColumn = false;
                }

                $financeParams['active_session_id'] = (int)$activeSessionId;
                $financeSql = $hasBalanceColumn
                    ? "SELECT student_id, balance_amount, total_payment, amount_pay_1, amount_pay_2, amount_pay_3, amount_pay_4, amount_pay_5
                       FROM finance
                       WHERE session_id = :active_session_id
                         AND student_id IN (" . implode(', ', $placeholders) . ")"
                    : "SELECT student_id, total_payment, amount_pay_1, amount_pay_2, amount_pay_3, amount_pay_4, amount_pay_5
                       FROM finance
                       WHERE session_id = :active_session_id
                         AND student_id IN (" . implode(', ', $placeholders) . ")";

                $financeStmt = $dtb->prepare($financeSql);
                $financeStmt->execute($financeParams);
                $financeRows = $financeStmt->fetchAll(PDO::FETCH_ASSOC);

                $remainingByStudentId = [];
                foreach ($financeRows as $fr) {
                    $sidRaw = trim((string)($fr['student_id'] ?? ''));
                    if ($sidRaw === '') {
                        continue;
                    }

                    $toNumber = function ($value) {
                        $raw = trim((string)$value);
                        if ($raw === '') {
                            return 0.0;
                        }
                        $raw = str_replace([' ', "\xC2\xA0"], '', $raw);
                        $raw = str_replace(',', '.', $raw);
                        return is_numeric($raw) ? (float)$raw : 0.0;
                    };

                    $storedBalance = $hasBalanceColumn ? ($fr['balance_amount'] ?? null) : null;
                    if ($storedBalance !== null && trim((string)$storedBalance) !== '') {
                        $remaining = max(0.0, $toNumber($storedBalance));
                    } else {
                        $total = $toNumber($fr['total_payment'] ?? 0);
                        $paid = $toNumber($fr['amount_pay_1'] ?? 0)
                            + $toNumber($fr['amount_pay_2'] ?? 0)
                            + $toNumber($fr['amount_pay_3'] ?? 0)
                            + $toNumber($fr['amount_pay_4'] ?? 0)
                            + $toNumber($fr['amount_pay_5'] ?? 0);

                        $remaining = max(0.0, $total - $paid);
                    }

                    $remainingByStudentId[$sidRaw] = $remaining;
                    $sidDigits = preg_replace('/\D+/', '', $sidRaw);
                    if ($sidDigits !== '' && ctype_digit((string)$sidDigits)) {
                        $remainingByStudentId[(string)((int)$sidDigits)] = $remaining;
                    }
                }

                foreach ($result['recipient_meta'] as $email => $meta) {
                    $sidRaw = trim((string)($meta['student_id'] ?? ''));
                    $sidDigits = trim((string)($meta['finance_student_id'] ?? ''));
                    if ($sidRaw !== '' && isset($remainingByStudentId[$sidRaw])) {
                        $result['recipient_meta'][$email]['remaining_amount'] = (float)$remainingByStudentId[$sidRaw];
                    } elseif ($sidDigits !== '' && isset($remainingByStudentId[$sidDigits])) {
                        $result['recipient_meta'][$email]['remaining_amount'] = (float)$remainingByStudentId[$sidDigits];
                    }
                }

                $debtorEmails = [];
                $debtorMeta = [];
                foreach ($result['emails'] as $email) {
                    $amount = (float)($result['recipient_meta'][$email]['remaining_amount'] ?? 0.0);
                    if ($amount > 0) {
                        $debtorEmails[] = $email;
                        $debtorMeta[$email] = $result['recipient_meta'][$email];
                    }
                }
                $result['emails'] = $debtorEmails;
                $result['recipient_meta'] = $debtorMeta;
            }

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

if (!function_exists('collecterDestinataires')) {
    /* Les destinataires selon le compte : un enseignant n'ecrit qu'a des
       personnes ou a ses etudiants (« mes_cours », d'un cours ou de tous) ; les
       groupes de l'universite restent au registrariat. */
    function collecterDestinataires($dtb, $activeSessionId, $modeEnseignant, $targetType, $customRecipientsRaw = '', $mentionFilters = [], $levelFilter = '', $coursId = 0) {
        if ($modeEnseignant) {
            if ($targetType === 'custom') {
                return collectBulkRecipientEmails($dtb, $activeSessionId, 'custom', $customRecipientsRaw);
            }
            if ($targetType === 'mes_cours') {
                $etudiants = courrielEnseignantEtudiants($dtb, (int)$coursId);
                $meta = [];
                foreach ($etudiants as $e) {
                    $meta[$e['email']] = ['student_id' => $e['matricule'], 'full_name' => $e['nom'], 'remaining_amount' => 0.0];
                }
                return ['emails' => array_column($etudiants, 'email'), 'recipient_meta' => $meta, 'error' => ''];
            }
            return ['emails' => [], 'recipient_meta' => [], 'error' => 'Ce groupe est réservé au registrariat.'];
        }
        return collectBulkRecipientEmails($dtb, $activeSessionId, $targetType, $customRecipientsRaw, $mentionFilters, $levelFilter);
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
    if (ob_get_level()) ob_clean();
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

        $collection = collecterDestinataires($dtb, $activeSessionId, $modeEnseignant, $targetType, $customRecipientsRaw, $mentionFilters, $levelFilter, (int)($_GET['cours_id'] ?? 0));
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
    if (ob_get_level()) ob_clean();
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

    /* Un enseignant : ses etudiants et l'annuaire @zurcher.edu.mg, pas toute la base. */
    if ($modeEnseignant) {
        try {
            echo json_encode(['success' => true, 'items' => courrielEnseignantRecherche($dtb, $q)], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['success' => true, 'items' => []]);
        }
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
                                          AND (student_nom LIKE ? OR student_prenom LIKE ? OR student_email LIKE ? OR LOWER(student_email) = LOWER(?))
                                      ORDER BY (LOWER(student_email) = LOWER(?)) DESC, student_email LIKE ? DESC, student_nom LIKE ? DESC, student_prenom LIKE ? DESC
                                      LIMIT 25");
        $studentStmt->execute([$like, $like, $like, $q, $q, $q . '%', $like, $like]);
        $rows = array_merge($rows, $studentStmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $ignored) {}

    try {
        $teacherStmt = $dtb->prepare("SELECT CONCAT(TRIM(lastName), ' ', TRIM(name)) AS full_name, TRIM(email) AS email, 'Professeur' AS source
                                      FROM teacher
                                      WHERE remove != 1
                                          AND email IS NOT NULL
                                          AND TRIM(email) <> ''
                                          AND (lastName LIKE ? OR name LIKE ? OR email LIKE ? OR LOWER(email) = LOWER(?))
                                      ORDER BY (LOWER(email) = LOWER(?)) DESC, email LIKE ? DESC, lastName LIKE ? DESC, name LIKE ? DESC
                                      LIMIT 25");
        $teacherStmt->execute([$like, $like, $like, $q, $q, $q . '%', $like, $like]);
        $rows = array_merge($rows, $teacherStmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $ignored) {}

    try {
        $userStmt = $dtb->prepare("SELECT CONCAT(TRIM(nom), ' ', TRIM(prenom)) AS full_name, TRIM(mail) AS email, 'Utilisateur' AS source
                                   FROM compt_utilisateur
                                   WHERE etat = 1
                                       AND mail IS NOT NULL
                                       AND TRIM(mail) <> ''
                                       AND (nom LIKE ? OR prenom LIKE ? OR mail LIKE ? OR LOWER(mail) = LOWER(?))
                                   ORDER BY (LOWER(mail) = LOWER(?)) DESC, mail LIKE ? DESC, nom LIKE ? DESC, prenom LIKE ? DESC
                                   LIMIT 25");
        $userStmt->execute([$like, $like, $like, $q, $q, $q . '%', $like, $like]);
        $rows = array_merge($rows, $userStmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $ignored) {}
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

    echo json_encode(['success' => true, 'items' => array_slice($items, 0, 25)], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['ajax'] ?? '') === 'mailbox-view') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=UTF-8');

    if (!$canUseBulkEmail) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'forbidden']);
        exit;
    }

    try {
        $box = strtolower(trim((string)($_GET['box'] ?? 'compose')));
        $payload = getMailboxViewPayload($dtb, $box, $canUseBulkEmail, $filtreExpediteur);
        $response = [
            'success' => true,
            'box' => (string)($payload['box'] ?? 'compose'),
            'title' => (string)($payload['title'] ?? 'Nouveau message'),
            'items' => (array)($payload['items'] ?? []),
            'counts' => (array)($payload['counts'] ?? ['inbox' => 0, 'sent' => 0]),
            'source' => (string)($payload['source'] ?? 'local'),
            'warning' => (string)($payload['warning'] ?? ''),
        ];

        $json = json_encode($response, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            // Last-resort sanitation to keep mailbox AJAX functional.
            array_walk_recursive($response, function (&$v) {
                if (is_string($v)) {
                    $v = normalizeUtf8Text($v);
                }
            });
            $json = json_encode($response, JSON_UNESCAPED_UNICODE);
        }

        if ($json === false) {
            $response = [
                'success' => true,
                'box' => (string)$box,
                'title' => ($box === 'sent' ? 'Messages envoyes' : ($box === 'inbox' ? 'Boite de reception' : 'Nouveau message')),
                'items' => [],
                'counts' => (array)bulkMailboxCountByFolder($dtb, $filtreExpediteur),
                'source' => 'local',
                'warning' => 'Boite IMAP instable, affichage local active.',
            ];
            $json = json_encode($response, JSON_UNESCAPED_UNICODE);
        }

        echo $json !== false ? $json : '{"success":true,"box":"compose","title":"Nouveau message","items":[],"counts":{"inbox":0,"sent":0},"source":"local","warning":"Boite locale chargee."}';
        exit;
    } catch (Throwable $e) {
        $box = strtolower(trim((string)($_GET['box'] ?? 'compose')));
        $safePayload = [
            'success' => true,
            'box' => $box,
            'title' => ($box === 'sent' ? 'Messages envoyes' : ($box === 'inbox' ? 'Boite de reception' : 'Nouveau message')),
            'items' => [],
            'counts' => (array)bulkMailboxCountByFolder($dtb, $filtreExpediteur),
            'source' => 'local',
            'warning' => 'Connexion IMAP indisponible temporairement. Boite locale affichee.',
        ];
        echo json_encode($safePayload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($_GET['ajax'] ?? '') === 'mailbox-message') {
    if (ob_get_level()) ob_clean();
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
        if (!$modeEnseignant && isExternalImapMailboxEnabled() && in_array($box, ['inbox', 'sent'], true)) {
            $detail = fetchImapMessageDetail($box, $id);
        }

        if (empty($detail) || ($detail['success'] ?? false) !== true) {
            $detail = fetchLocalMailboxMessageDetail($dtb, $id, $filtreExpediteur);
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
    'target_type' => $_POST['target_type'] ?? 'custom', // « Des personnes » : le champ A commence vide, comme dans Gmail
    'subject' => $_POST['subject'] ?? '',
    'message' => $_POST['message'] ?? '',
    'message_html' => $_POST['message_html'] ?? '',
    'custom_recipients' => $_POST['custom_recipients'] ?? '',
    'mention_filters' => $postedMentionFilters,
    'level_filter' => trim((string)($_POST['level_filter'] ?? '')),
    'cours_id' => (int)($_POST['cours_id'] ?? 0), // « Mes étudiants » d'un enseignant : un cours, ou 0 pour tous
];

if (!function_exists('processBulkEmailSend')) {
    /* $expediteur : NULL, envoi du registrariat depuis no-reply ; ['adresse', 'nom']
       d'un enseignant, envoi depuis sa boite Gmail (data/courriel.enseignant.php). */
    function processBulkEmailSend($dtb, $canUseBulkEmail, $currentUser, $app_base, $activeSessionId, $bulkForm, $csrfToken, $uploadedFiles = [], $progressCallback = null, $expediteur = null) {
        @set_time_limit(0);
        @ignore_user_abort(true);

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

        $modeEnseignant = $expediteur !== null;
        if ($modeEnseignant && (string)($expediteur['adresse'] ?? '') === '') {
            $result['message'] = 'Connectez-vous avec Google pour envoyer depuis votre adresse @zurcher.edu.mg.';
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
            $collection = collecterDestinataires($dtb, $activeSessionId, $modeEnseignant, $targetType, $customRecipientsRaw, $mentionFilters, $levelFilter, (int)($bulkForm['cours_id'] ?? 0));
            $emails = $collection['emails'];
            $recipientMeta = isset($collection['recipient_meta']) && is_array($collection['recipient_meta']) ? $collection['recipient_meta'] : [];
            if ($collection['error'] !== '') {
                $result['message'] = $collection['error'];
            }

            if ($result['message'] === '') {
                $targetCandidates = is_array($emails) ? count($emails) : 0;
                $validEmails = [];
                $validRecipientMeta = [];
                $invalidCount = 0;
                $duplicateCount = 0;
                foreach ($emails as $email) {
                    $clean = strtolower(trim((string)$email));
                    if ($clean === '' || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
                        $invalidCount++;
                        continue;
                    }
                    if (isset($validEmails[$clean])) {
                        $duplicateCount++;
                        continue;
                    }
                    $validEmails[$clean] = true;
                    $validRecipientMeta[$clean] = $recipientMeta[$clean] ?? [];
                }

                $recipients = array_keys($validEmails);
                // Limite de securite anti-abus, mais suffisamment haute pour les campagnes reelles.
                $maxRecipients = 2000;
                if ($modeEnseignant) {
                    /* Des personnes : toutes visibles sur un message. Ses etudiants :
                       en copie cachee, dans les limites de Gmail pour un compte. */
                    $maxRecipients = $targetType === 'custom' ? COURRIEL_ENSEIGNANT_MAX_PERSONNES : COURRIEL_ENSEIGNANT_MAX_ETUDIANTS;
                }

                if (count($recipients) === 0) {
                    $result['message'] = 'Aucun email valide trouve.';
                } elseif (count($recipients) > $maxRecipients) {
                    $result['message'] = $modeEnseignant && $targetType === 'custom'
                        ? 'Plus de ' . $maxRecipients . ' personnes sur un message : écrivez à « Mes étudiants », ou envoyez en plusieurs fois.'
                        : 'Trop de destinataires en une fois (max ' . $maxRecipients . ').';
                } else {
                    $safeSubject = str_replace(["\r", "\n"], ' ', $subject);
                    $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Université Adventiste Zurcher - No Reply';
                    $fromEmail = defined('SMTP_FROM')      ? SMTP_FROM      : 'no-reply@zurcher.edu.mg';
                    if ($modeEnseignant) {
                        $fromName = (string)$expediteur['nom'];
                        $fromEmail = (string)$expediteur['adresse'];
                    }

                    // Charger PHPMailer si disponible
                    $phpmailerSrc = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/vendor/phpmailer/phpmailer/src';
                    $usePhpMailer = is_file($phpmailerSrc . '/PHPMailer.php');
                    if ($usePhpMailer && !class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
                        require_once $phpmailerSrc . '/Exception.php';
                        require_once $phpmailerSrc . '/PHPMailer.php';
                        require_once $phpmailerSrc . '/SMTP.php';
                    }

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
                    $baseBodyContentHtml = nl2br($safeMessageText);

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
                            $baseBodyContentHtml = $sanitized;
                        }
                    }

                    $containsAmountToken = (strpos($messageText, '{{montant_restant') !== false) || (strpos($baseBodyContentHtml, '{{montant_restant') !== false);

                    $envelopeSender = '-f ' . $fromEmail;

                    $resolvedNamesByEmail = [];
                    if (!empty($recipients) && !$modeEnseignant) { // les noms ne servent qu'aux messages un par un de no-reply
                        $emailPlaceholders = [];
                                                $emailParams = [];
                        foreach ($recipients as $i => $mailAddress) {
                                                        $mailValue = strtolower(trim((string)$mailAddress));

                            $k = 'em_' . $i;
                            $emailPlaceholders[] = ':' . $k;
                            $emailParams[$k] = $mailValue;
                        }

                        if (!empty($emailPlaceholders)) {
                            $nameSqlStudents = "SELECT LOWER(TRIM(student_email)) AS email_key,
                                                       TRIM(CONCAT_WS(' ', NULLIF(TRIM(IFNULL(student_nom, '')), ''), NULLIF(TRIM(IFNULL(student_prenom, '')), ''))) AS full_name
                                                FROM tbl_2024_etudiant
                                                WHERE remove != 1
                                                  AND student_email IS NOT NULL
                                                  AND TRIM(student_email) <> ''
                                                  AND LOWER(TRIM(student_email)) IN (" . implode(', ', $emailPlaceholders) . ")";
                            $nameStmtStudents = $dtb->prepare($nameSqlStudents);
                            $nameStmtStudents->execute($emailParams);
                            $nameRowsStudents = $nameStmtStudents->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($nameRowsStudents as $nameRow) {
                                $ek = strtolower(trim((string)($nameRow['email_key'] ?? '')));
                                $fn = trim((string)($nameRow['full_name'] ?? ''));
                                if ($ek !== '' && $fn !== '' && !isset($resolvedNamesByEmail[$ek])) {
                                    $resolvedNamesByEmail[$ek] = $fn;
                                }
                            }

                            $nameSqlTeachers = "SELECT LOWER(TRIM(email)) AS email_key,
                                                       TRIM(CONCAT_WS(' ', NULLIF(TRIM(IFNULL(lastName, '')), ''), NULLIF(TRIM(IFNULL(name, '')), ''))) AS full_name
                                                FROM teacher
                                                WHERE remove != 1
                                                  AND email IS NOT NULL
                                                  AND TRIM(email) <> ''
                                                  AND LOWER(TRIM(email)) IN (" . implode(', ', $emailPlaceholders) . ")";
                            $nameStmtTeachers = $dtb->prepare($nameSqlTeachers);
                            $nameStmtTeachers->execute($emailParams);
                            $nameRowsTeachers = $nameStmtTeachers->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($nameRowsTeachers as $nameRow) {
                                $ek = strtolower(trim((string)($nameRow['email_key'] ?? '')));
                                $fn = trim((string)($nameRow['full_name'] ?? ''));
                                if ($ek !== '' && $fn !== '' && !isset($resolvedNamesByEmail[$ek])) {
                                    $resolvedNamesByEmail[$ek] = $fn;
                                }
                            }

                            $nameSqlUsers = "SELECT LOWER(TRIM(mail)) AS email_key,
                                                    TRIM(CONCAT_WS(' ', NULLIF(TRIM(IFNULL(nom, '')), ''), NULLIF(TRIM(IFNULL(prenom, '')), ''))) AS full_name
                                             FROM compt_utilisateur
                                             WHERE etat = 1
                                               AND mail IS NOT NULL
                                               AND TRIM(mail) <> ''
                                               AND LOWER(TRIM(mail)) IN (" . implode(', ', $emailPlaceholders) . ")";
                            $nameStmtUsers = $dtb->prepare($nameSqlUsers);
                            $nameStmtUsers->execute($emailParams);
                            $nameRowsUsers = $nameStmtUsers->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($nameRowsUsers as $nameRow) {
                                $ek = strtolower(trim((string)($nameRow['email_key'] ?? '')));
                                $fn = trim((string)($nameRow['full_name'] ?? ''));
                                if ($ek !== '' && $fn !== '' && !isset($resolvedNamesByEmail[$ek])) {
                                    $resolvedNamesByEmail[$ek] = $fn;
                                }
                            }
                        }
                    }

                    /* L'enveloppe du message. Le pied : no-reply ne lit pas les
                       reponses ; un enseignant, si. */
                    if ($modeEnseignant) {
                        $piedHtml = 'Message de ' . htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8')
                            . ' (<a href="mailto:' . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') . '" style="color:#0b63ce;text-decoration:none;">' . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') . '</a>).<br>'
                            . 'Pour lui répondre, utilisez simplement « Répondre ».<br>';
                        $piedTexte = "\n\n-- \n" . $fromName . "\n" . $fromEmail;
                    } else {
                        $piedHtml = 'Message automatique, merci de ne pas repondre à cet email.<br>'
                            . 'Contact: <a href="mailto:registrar@zurcher.edu.mg" style="color:#0b63ce;text-decoration:none;">registrar@zurcher.edu.mg</a> | Tel: <a href="tel:+261343818081" style="color:#0b63ce;text-decoration:none;">034 38 180 81</a><br>';
                        $piedTexte = "\n\nMessage automatique, merci de ne pas repondre à cet email.";
                    }
                    $envelopper = function ($corpsHtml) use ($safeSubject, $logoInlineTag, $piedHtml) {
                        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($safeSubject, ENT_QUOTES, 'UTF-8') . '</title></head><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;">'
                            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:20px 0;background:#f8fafc;">'
                            . '<tr><td align="center">'
                            . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">'
                            . '<tr><td align="center" style="background:#0a1628;padding:18px 24px;text-align:center;">'
                            . '<div style="text-align:center;">' . $logoInlineTag . '</div>'
                            . '<div style="color:#e8f1f8;font-size:18px;font-weight:700;margin-top:10px;">Université Adventiste Zurcher</div>'
                            . '</td></tr>'
                            . '<tr><td style="padding:24px;color:#0f172a;font-size:15px;line-height:1.6;">'
                            . $corpsHtml
                            . '</td></tr>'
                            . '</table>'
                            . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="margin-top:10px;">'
                            . '<tr><td align="center" style="font-size:12px;color:#64748b;line-height:1.7;padding:0 10px;">'
                            . $piedHtml
                            . '85Q7+M95, Antsampanimahazo, B.P. 325 Antsirabe 110, Vakinankaratra, Madagascar<br>'
                            . '&copy; ' . date('Y')
                            . '</td></tr>'
                            . '</table>'
                            . '</td></tr>'
                            . '</table>'
                            . '</body></html>';
                    };

                    $sent = 0;
                    $failed = 0;
                    $failedRecipients = [];
                    $recipientTotalCount = count($recipients);
                    $erreurEnvoi = '';
                    $refusEnvoi = '';
                    if ($modeEnseignant) {
                        /* Un seul message (ou un par lot de 100 en copie cachee), depuis
                           sa boite Gmail : pas de personnalisation par destinataire. */
                        $htmlMessage = $envelopper($baseBodyContentHtml);
                        $imagesCorps = [];
                        if ($logoBinary !== false) {
                            $imagesCorps[] = ['data' => $logoBinary, 'cid' => $logoCid, 'nom' => $logoFileName, 'type' => $logoMime];
                        }
                        foreach ($inlineImages as $inlineImage) {
                            $imagesCorps[] = ['data' => $inlineImage['data'], 'cid' => $inlineImage['cid'], 'nom' => $inlineImage['name'], 'type' => $inlineImage['mime']];
                        }
                        $envoi = courrielEnseignantEnvoyer([
                            'de' => ['adresse' => $fromEmail, 'nom' => $fromName],
                            'mode' => $targetType === 'custom' ? 'personnes' : 'etudiants',
                            'destinataires' => $recipients,
                            'objet' => $safeSubject,
                            'html' => $htmlMessage,
                            'texte' => $messageText . $piedTexte,
                            'images' => $imagesCorps,
                            'pieces' => $attachments,
                            'progression' => $progressCallback,
                        ]);
                        $sent = $envoi['envoyes'];
                        $failed = $envoi['echecs'];
                        $failedRecipients = $envoi['en_echec'];
                        $erreurEnvoi = $envoi['erreur'];
                        $refusEnvoi = $envoi['refus'];
                    }
                    foreach ($modeEnseignant ? [] : $recipients as $idx => $recipient) {
                        $meta = $validRecipientMeta[$recipient] ?? [];
                        $studentName = trim((string)($meta['full_name'] ?? ''));
                        if ($studentName === '' && isset($resolvedNamesByEmail[$recipient])) {
                            $studentName = trim((string)$resolvedNamesByEmail[$recipient]);
                        }
                        if ($studentName === '') {
                            $studentName = 'Etudiant';
                        }

                        $remainingAmount = (float)($meta['remaining_amount'] ?? 0);
                        $remainingAmountLabel = number_format($remainingAmount, 0, '', ' ') . ' Ar';

                        $searchTokens = ['{{nom_etudiant}}', '{{montant_restant}}', '{{montant_restant_brut}}'];
                        $replaceTokens = [$studentName, $remainingAmountLabel, (string)((int)round($remainingAmount))];

                        $recipientMessageText = str_replace($searchTokens, $replaceTokens, $messageText);
                        $recipientBodyContentHtml = str_replace($searchTokens, $replaceTokens, $baseBodyContentHtml);

                        if ($targetType === 'students_unregularized' && !$containsAmountToken) {
                            $recipientMessageText .= "\n\nMontant restant a payer: " . $remainingAmountLabel . ".";
                            $recipientBodyContentHtml .= '<p style="margin-top:14px;"><strong>Montant restant a payer: ' . htmlspecialchars($remainingAmountLabel, ENT_QUOTES, 'UTF-8') . '</strong></p>';
                        }

                        $htmlMessage = $envelopper($recipientBodyContentHtml);

                        $plainTextMessage = $recipientMessageText . $piedTexte;
                        $boundaryMixed = 'mixed_' . md5((string)(microtime(true) . '_' . $recipient));
                        $boundaryRelated = 'related_' . md5((string)(microtime(true) . '_related_' . $recipient));
                        $boundaryAlt = 'alt_' . md5((string)(microtime(true) . '_alt_' . $recipient));

                        $headers = [];
                        $headers[] = 'MIME-Version: 1.0';
                        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundaryMixed . '"';
                        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
                        $headers[] = 'Reply-To: ' . $fromEmail;
                        $headers[] = 'Sender: ' . $fromEmail;
                        $headers[] = 'Return-Path: ' . $fromEmail;
                        $headers[] = 'X-Mailer: PHP/' . phpversion();
                        $headersString = implode("\r\n", $headers);

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

                        $ok = false;
                        $errorMsg = '';
                        if (!empty($usePhpMailer) && class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
                            try {
                                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host       = defined('SMTP_HOST')   ? SMTP_HOST   : 'smtp.gmail.com';
                                $mail->SMTPAuth   = defined('SMTP_AUTH')   ? SMTP_AUTH   : true;
                                $mail->Username   = defined('SMTP_USER')   ? SMTP_USER   : $fromEmail;
                                $mail->Password   = defined('SMTP_PASS')   ? SMTP_PASS   : '';
                                $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = defined('SMTP_PORT')   ? SMTP_PORT   : 587;
                                $mail->CharSet    = 'UTF-8';
                                $mail->Timeout    = 15;
                                $mail->setFrom($fromEmail, $fromName);
                                $mail->addAddress($recipient);
                                $mail->Subject = $safeSubject;
                                $mail->isHTML(true);
                                $mail->Body    = $htmlMessage;
                                $mail->AltBody = $plainTextMessage;
                                if ($logoBinary !== false) {
                                    $mail->addStringEmbeddedImage($logoBinary, $logoCid, $logoFileName, 'base64', $logoMime);
                                }
                                foreach ($inlineImages as $inlineImage) {
                                    $mail->addStringEmbeddedImage($inlineImage['data'], $inlineImage['cid'], $inlineImage['name'], 'base64', $inlineImage['mime']);
                                }
                                foreach ($attachments as $attachment) {
                                    $mail->addStringAttachment($attachment['data'], $attachment['name'], 'base64', $attachment['mime']);
                                }
                                $mail->send();
                                $ok = true;
                            } catch (\PHPMailer\PHPMailer\Exception $e) {
                                $ok = false;
                                $errorMsg = $e->getMessage();
                                // Log error to file for debugging
                                if (defined('ROOT_DIR')) {
                                    $logDir = ROOT_DIR . '/logs';
                                    if (!is_dir($logDir)) {
                                        @mkdir($logDir, 0755, true);
                                    }
                                    @error_log('[' . date('Y-m-d H:i:s') . '] PHPMailer Error for ' . $recipient . ': ' . $errorMsg . "\n", 3, $logDir . '/email_errors.log');
                                }
                            }
                        } else {
                            // Fallback mail() natif
                            $ok = @mail($recipient, $safeSubject, $mailBody, $headersString, $envelopeSender);
                            if (!$ok) {
                                usleep(50000);
                                $ok = @mail($recipient, $safeSubject, $mailBody, $headersString, $envelopeSender);
                            }
                        }

                        if ($ok) {
                            $sent++;
                        } else {
                            $failed++;
                            if (count($failedRecipients) < 30) {
                                $failedRecipients[] = $recipient;
                            }
                        }

                        if ((($sent + $failed) % 50) === 0) {
                            // Very small pacing to reduce relay throttling without risking timeout.
                            usleep(20000);
                        }

                        if (is_callable($progressCallback)) {
                            $progressPayload = [
                                'type' => 'progress',
                                'recipient' => (string)$recipient,
                                'status' => $ok ? 'envoye' : 'echec',
                                'index' => (int)($idx + 1),
                                'total' => (int)$recipientTotalCount,
                                'sent' => (int)$sent,
                                'failed' => (int)$failed,
                                'processed' => (int)($sent + $failed),
                            ];
                            try {
                                $progressCallback($progressPayload);
                            } catch (Throwable $ignored) {
                            }
                        }
                    }

                    $result['success'] = $sent > 0 && $erreurEnvoi === '';
                    $result['sent'] = $sent;
                    $result['failed'] = $failed;
                    $result['total'] = count($recipients);
                    $result['message'] = 'Envoi termine. Cibles: ' . $targetCandidates
                        . ' | Emails valides uniques: ' . count($recipients)
                        . ' | Envoyes: ' . $sent
                        . ' | Echecs: ' . $failed;
                    if ($invalidCount > 0 || $duplicateCount > 0) {
                        $result['message'] .= ' | Invalides: ' . $invalidCount . ' | Doublons: ' . $duplicateCount;
                    }
                    if ($refusEnvoi !== '' && $failed > 0) {
                        $result['message'] .= ' | ' . $refusEnvoi;
                    }
                    if ($erreurEnvoi !== '') {
                        /* Google a tout arrete (droits, quota) : la raison d'abord. */
                        $result['message'] = $sent > 0
                            ? 'Envoi interrompu après ' . $sent . ' destinataires sur ' . count($recipients) . '. ' . $erreurEnvoi
                            : $erreurEnvoi;
                    }
                    if (!empty($failedRecipients)) {
                        $result['failed_recipients'] = $failedRecipients;
                    }
                    $result['redirect_url'] = rtrim((string)$app_base, '/') . '/settings/bulk-email?box=' . ($modeEnseignant ? 'groupes' : 'inbox');

                    $createdBy = trim((string)(($currentUser['nom'] ?? '') . ' ' . ($currentUser['prenom'] ?? '')));
                    if ($createdBy === '') {
                        $createdBy = trim((string)($currentUser['pseudo'] ?? ''));
                    }
                    if ($createdBy === '') {
                        $createdBy = 'Utilisateur';
                    }
                    if ($modeEnseignant) {
                        $createdBy = $fromName;
                    }

                    $previewPlain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$baseBodyContentHtml)));
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

                    if ($modeEnseignant) {
                        /* « Mes envois » de l'enseignant ; rien quand rien n'est parti.
                           Pas de rapport dans la boite du registrariat. */
                        if ($sent > 0) {
                            bulkMailboxInsert($dtb, 'sent', $safeSubject, $previewPlain, count($recipients), $sent, $failed,
                                $createdBy, $storedMessageHtml, $previewImage, $fromEmail);
                        }
                        return $result;
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
                    $reportPreview = 'Campagne terminee. Cibles: ' . $targetCandidates
                        . ', valides uniques: ' . count($recipients)
                        . ', envoyes: ' . $sent
                        . ', echecs: ' . $failed
                        . ', invalides: ' . $invalidCount
                        . ', doublons: ' . $duplicateCount . '.';
                    if (!empty($failedRecipients)) {
                        $reportPreview .= ' Echecs (extrait): ' . implode(', ', $failedRecipients) . '.';
                    }

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
    $isAjaxSend = (($_GET['ajax'] ?? '') === 'send-bulk-email')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
    $isStreamSend = $isAjaxSend && (($_GET['stream'] ?? '') === '1');

    $emitStreamEvent = null;
    if ($isStreamSend) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');

        header('Content-Type: application/x-ndjson; charset=UTF-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');

        $emitStreamEvent = function ($payload) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                return;
            }
            echo $json . "\n";
            @ob_flush();
            @flush();
        };

        try {
            $emitStreamEvent([
                'type' => 'start',
                'message' => 'Envoi en cours...',
            ]);
        } catch (Throwable $ignored) {
        }
    }

    try {
        $bulkEmailResult = processBulkEmailSend(
            $dtb,
            $canUseBulkEmail,
            $currentUser,
            $app_base,
            $activeSessionId,
            $bulkForm,
            $_POST['csrf_token'] ?? '',
            $_FILES,
            $emitStreamEvent,
            $expediteurEnseignant
        );
    } catch (Throwable $e) {
        $bulkEmailResult = [
            'success' => false,
            'message' => 'Erreur interne pendant l\'envoi. Merci de reessayer.',
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'redirect_url' => '',
        ];
    }

    if ($isStreamSend && is_callable($emitStreamEvent)) {
        try {
            $emitStreamEvent([
                'type' => 'result',
                'result' => $bulkEmailResult,
            ]);
        } catch (Throwable $ignored) {
        }
        exit;
    }

    if ($isAjaxSend) {
        if (ob_get_level() > 0 && ob_get_length()) {
            ob_clean();
        }
        if (($bulkEmailResult['success'] ?? false) && empty($bulkEmailResult['redirect_url'])) {
            $bulkEmailResult['redirect_url'] = rtrim((string)$app_base, '/') . '/settings/bulk-email?box=' . ($modeEnseignant ? 'groupes' : 'inbox');
        }
        header('Content-Type: application/json; charset=UTF-8');
        $json = json_encode($bulkEmailResult, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            array_walk_recursive($bulkEmailResult, function (&$v) {
                if (is_string($v)) {
                    if (function_exists('normalizeUtf8Text')) {
                        $v = normalizeUtf8Text($v);
                    } else {
                        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', (string)$v);
                    }
                }
            });
            $json = json_encode($bulkEmailResult, JSON_UNESCAPED_UNICODE);
        }
        echo $json !== false ? $json : '{"success":false,"message":"Erreur interne pendant envoi.","sent":0,"failed":0,"total":0,"redirect_url":""}';
        exit;
    }

    if (($bulkEmailResult['success'] ?? false) === true) {
        header('Location: ' . rtrim((string)$app_base, '/') . '/settings/bulk-email?box=' . ($modeEnseignant ? 'groupes' : 'inbox'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <!-- REQUEST HEAD --><?php require('../init/head.php');?>
    <title><?= $modeEnseignant ? 'E-mails' : 'Boîte mail' ?></title>
    <?php /* Ergonomie de Gmail, charte de l'application : src/css/boite-gmail.css et src/js/boite-gmail.js. */ ?>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/boite-gmail.css?v=<?=@filemtime(__DIR__ . '/css/boite-gmail.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm bm-page">
<?php
    /* La boite no-reply@zurcher.edu.mg (data/boite.gmail.php) : l'ergonomie de
       Gmail dans la charte de l'application (src/css/boite-gmail.css,
       src/js/boite-gmail.js). La redaction s'ouvre en fenetre, en bas a
       droite, par-dessus la boite, comme dans Gmail. */
    require_once __DIR__ . '/../data/boite.gmail.php';
    $adresseBoite = boiteGmailAdresse();
    $boiteConfiguree = boiteGmailConfiguree();
    $menuBoite = [
        ['inbox', 'bi-inbox', 'Boîte de réception', 'countInbox'],
        ['starred', 'bi-star', 'Messages suivis', ''],
        ['important', 'bi-bookmark', 'Importants', ''],
        ['sent', 'bi-send', 'Messages envoyés', ''],
        ['drafts', 'bi-file-earmark', 'Brouillons', 'gmCompteDrafts'],
        ['all', 'bi-envelope', 'Tous les messages', ''],
        ['spam', 'bi-exclamation-octagon', 'Spam', 'gmCompteSpam'],
        ['trash', 'bi-trash', 'Corbeille', ''],
    ];
    /* Les mentions s'affichent par leur nom, pas par leur code. */
    $nomsMentions = ['THEO' => 'Théologie', 'GEST' => 'Gestion', 'INFO' => 'Informatique', 'NURS' => 'Sciences infirmières',
        'EDUC' => 'Éducation', 'COMM' => 'Communication', 'LANG' => 'Études anglophones', 'DROI' => 'Droit'];
    $boiteDepart = $currentBox === 'compose' ? 'inbox' : $currentBox;

    /* Un enseignant : son adresse de connexion, ses cours de l'annee, et si
       Google laisse partir ses messages (data/gmail.envoi.php). */
    $coursEnseignant = [];
    $etudiantsEnseignant = 0;
    $etatEnvoi = ['pret' => true, 'raison' => ''];
    if ($modeEnseignant) {
        $adresseBoite = (string)$expediteurEnseignant['adresse'];
        $boiteConfiguree = true;
        $menuBoite = [];
        $boiteDepart = 'groupes';
        try {
            $coursEnseignant = courrielEnseignantCours($dtb);
            $etudiantsEnseignant = count(courrielEnseignantEtudiants($dtb));
        } catch (Throwable $e) {
            error_log('E-mails enseignant : cours illisibles : ' . $e->getMessage());
        }
        $etatEnvoi = $adresseBoite !== ''
            ? gmailEnvoiEtat($adresseBoite)
            : ['pret' => false, 'raison' => "Vous êtes connecté sans Google : vos messages ne peuvent partir que de l'adresse @zurcher.edu.mg de votre connexion Google. Déconnectez-vous, puis reconnectez-vous avec « Se connecter avec Google »."];
    }
    $peutEnvoyer = $canUseBulkEmail && $etatEnvoi['pret'];
?>
    <div class="h-screen w-full <?=$bg_three_color?>">
        <!-- TOP BAR --><?php require('../init/topbar.php');?>
        <!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
        <!-- NOTIFICATION MANAGER --><?php require_once(__DIR__ . '/main/bigNotif.php');?>

        <div class="w-full flex flex-col lg:flex-row">
            <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

            <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
                <div class="bm">
                    <aside class="bm-dossiers" aria-label="Dossiers">
                        <div class="bm-dossiers__tete">
                            <?php if ($modeEnseignant): ?>
                            <h1>E-mails</h1>
                            <p title="Vos messages partent de cette adresse"><?=e($adresseBoite !== '' ? $adresseBoite : 'Connexion Google requise')?></p>
                            <?php else: ?>
                            <h1>Boîte mail</h1>
                            <p title="Adresse de la boîte"><?=e($adresseBoite !== '' ? $adresseBoite : 'no-reply@zurcher.edu.mg')?></p>
                            <?php endif; ?>
                        </div>
                        <a href="<?=$app_base?>/settings/bulk-email?box=compose" data-mailbox-nav="compose" class="bm-nouveau gm-nouveau gmail-compose-btn">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i><span>Nouveau message</span>
                        </a>
                        <nav class="bm-dossiers__liste">
                            <?php foreach ($menuBoite as [$boiteNav, $iconeNav, $libelleNav, $compteNav]): ?>
                            <a href="<?=$app_base?>/settings/bulk-email?box=<?=$boiteNav?>" data-mailbox-nav="<?=$boiteNav?>" class="bm-dossier gm-nav__lien gmail-menu-link<?= $boiteDepart === $boiteNav ? ' active' : '' ?>">
                                <i class="bi <?=$iconeNav?>" aria-hidden="true"></i><span><?=e($libelleNav)?></span><b class="gm-nav__compte"<?= $compteNav !== '' ? ' id="' . $compteNav . '"' : '' ?>></b>
                            </a>
                            <?php endforeach; ?>
                            <?php if ($modeEnseignant): ?>
                            <a href="<?=$app_base?>/settings/bulk-email?box=groupes" data-mailbox-nav="groupes" class="bm-dossier gm-nav__lien gmail-menu-link active" title="Les messages envoyés depuis cette page">
                                <i class="bi bi-send" aria-hidden="true"></i><span>Mes envois</span><b class="gm-nav__compte"></b>
                            </a>
                            <?php if ($adresseBoite !== ''): ?>
                            <a href="https://mail.google.com/mail/?authuser=<?=rawurlencode($adresseBoite)?>" target="_blank" rel="noopener" class="bm-dossier" title="Les réponses à vos messages arrivent dans votre boîte Gmail">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>Ouvrir Gmail</span>
                            </a>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="bm-dossiers__titre">Application</div>
                            <a href="<?=$app_base?>/settings/bulk-email?box=groupes" data-mailbox-nav="groupes" class="bm-dossier gm-nav__lien gmail-menu-link<?= $boiteDepart === 'groupes' ? ' active' : '' ?>" title="Historique des envois groupés faits depuis l'application">
                                <i class="bi bi-people" aria-hidden="true"></i><span>Envois groupés</span><b class="gm-nav__compte"></b>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </aside>

                    <section class="bm-principal" id="mailboxPanel" aria-label="Messages">
                        <div class="gm-progres" id="gmProgres" hidden></div>
                        <?php /* La recherche parcourt la boite no-reply : pas pour un enseignant. */ ?>
                        <form class="bm-recherche" id="gmRecherche" role="search"<?= $modeEnseignant ? ' hidden' : '' ?>>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" id="gmRechercheChamp" placeholder="Rechercher dans les messages (nom, adresse, objet…)" aria-label="Rechercher dans les messages" autocomplete="off">
                            <button type="button" class="bm-icone" id="gmRechercheEffacer" aria-label="Effacer la recherche" title="Effacer la recherche" hidden><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </form>
                        <?php if (!$boiteConfiguree): ?>
                        <div class="gm-erreur">La boîte Gmail n’est pas configurée sur ce serveur (réglages MAILBOX_IMAP_* de data/config.php).</div>
                        <?php endif; ?>
                        <?php if ($modeEnseignant && !$etatEnvoi['pret']): ?>
                        <div class="gm-erreur" role="alert"><?=e($etatEnvoi['raison'])?></div>
                        <?php endif; ?>

                        <div class="gm-outils" id="gmOutilsListe">
                            <span class="gm-outils__case"><input type="checkbox" class="gm-case" id="gmToutCocher" aria-label="Tout sélectionner" title="Tout sélectionner"></span>
                            <button type="button" class="bm-icone" id="gmActualiser" title="Actualiser" aria-label="Actualiser"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                            <span id="gmActionsSelection" class="bm-selection" hidden>
                                <button type="button" class="bm-icone" data-action="archiver" title="Archiver : retirer de la boîte de réception" aria-label="Archiver"><i class="bi bi-archive" aria-hidden="true"></i></button>
                                <button type="button" class="bm-icone" data-action="corbeille" title="Mettre à la corbeille" aria-label="Mettre à la corbeille"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                <button type="button" class="bm-icone" data-action="lu" title="Marquer comme lu" aria-label="Marquer comme lu"><i class="bi bi-envelope-open" aria-hidden="true"></i></button>
                                <button type="button" class="bm-icone" data-action="nonlu" title="Marquer comme non lu" aria-label="Marquer comme non lu"><i class="bi bi-envelope" aria-hidden="true"></i></button>
                            </span>
                            <span class="gm-titre-boite" id="gmTitreBoite"></span>
                            <div class="gm-pagination" id="gmPagination">
                                <span></span>
                                <button type="button" class="bm-icone" id="gmPrecedent" title="Messages plus récents" aria-label="Messages plus récents"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                                <button type="button" class="bm-icone" id="gmSuivant" title="Messages plus anciens" aria-label="Messages plus anciens"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="gm-liste" id="mailboxList" role="list" aria-label="Liste des messages"></div>
                        <article class="gm-lecture" id="gmLecture" hidden></article>
                        <?php /* Reperes encore lus par l'ancien script de la page. */ ?>
                        <span id="mailboxHead" hidden></span><span id="mailboxSourceText" hidden></span><span id="mailboxSourceWarning" hidden></span>
                    </section>
                </div>

                <?php require('../init/footer.php'); ?>
            </div>
        </div>
    </div>

    <?php /* ------------------------------------------------------------------
       Redaction : une fenetre en bas a droite, comme dans Gmail. Elle se
       reduit, s'agrandit, se ferme en gardant le brouillon. Les champs
       gardent les noms et identifiants que l'ancien script envoie. */ ?>
    <div class="bm-voile" id="bmVoile" hidden></div>
    <form method="post" action="<?=$app_base?>/settings/bulk-email" enctype="multipart/form-data" id="bulkMailForm" class="bm-redaction" data-etat="ouvert" role="dialog" aria-labelledby="bmRedactionTitre" <?php if($currentBox !== 'compose'){ echo 'style="display:none;"'; } ?>>
        <input type="hidden" name="action" value="send_bulk_email" />
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>" />

        <div class="bm-redaction__tete" id="bmRedactionTete">
            <span id="bmRedactionTitre">Nouveau message</span>
            <button type="button" class="bm-icone bm-icone--tete" data-redaction="reduire" title="Réduire" aria-label="Réduire"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
            <button type="button" class="bm-icone bm-icone--tete" data-redaction="agrandir" title="Agrandir" aria-label="Agrandir"><i class="bi bi-arrows-angle-expand" aria-hidden="true"></i></button>
            <button type="button" class="bm-icone bm-icone--tete" data-redaction="fermer" title="Fermer (le brouillon est gardé)" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="bm-redaction__corps">
            <div id="mailStatus" class="gmail-status" role="status"></div>

            <?php if ($modeEnseignant): /* l'expediteur, sous les yeux : pas no-reply */ ?>
            <div class="bm-champ" id="bmEnseignant">
                <span class="bm-champ__libelle">De</span>
                <span class="bm-champ__valeur bm-de">
                    <?php if ($adresseBoite !== ''): ?>
                    <b><?=e($expediteurEnseignant['nom'])?></b><span>&lt;<?=e($adresseBoite)?>&gt;</span>
                    <?php else: ?>
                    <span>Connexion Google requise</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!$etatEnvoi['pret']): ?>
            <div class="gm-erreur" role="alert"><?=e($etatEnvoi['raison'])?></div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="bm-mode" role="tablist" aria-label="À qui écrire">
                <button type="button" role="tab" class="bm-mode__choix" id="bmModePersonnes" data-mode="personnes" aria-selected="true">
                    <i class="bi bi-person" aria-hidden="true"></i> Des personnes
                </button>
                <button type="button" role="tab" class="bm-mode__choix" id="bmModeGroupe" data-mode="groupe" aria-selected="false">
                    <?php if ($modeEnseignant): ?>
                    <i class="bi bi-mortarboard" aria-hidden="true"></i> Mes étudiants
                    <?php else: ?>
                    <i class="bi bi-people" aria-hidden="true"></i> Un groupe
                    <?php endif; ?>
                </button>
            </div>

            <?php /* Le type de destinataires : pilote par les deux onglets ci-dessus. */ ?>
            <select id="targetType" name="target_type" hidden <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                <option value="custom" <?php if($bulkForm['target_type']==='custom'){ echo 'selected'; } ?>>Des personnes</option>
                <?php if ($modeEnseignant): ?>
                <option value="mes_cours" <?php if($bulkForm['target_type']==='mes_cours'){ echo 'selected'; } ?>>Mes étudiants</option>
                <?php else: ?>
                <option value="students" <?php if($bulkForm['target_type']==='students'){ echo 'selected'; } ?>>Tous les étudiants</option>
                <option value="students_unregularized" <?php if($bulkForm['target_type']==='students_unregularized'){ echo 'selected'; } ?>>Étudiants avec un solde à payer</option>
                <option value="teachers" <?php if($bulkForm['target_type']==='teachers'){ echo 'selected'; } ?>>Tous les enseignants</option>
                <option value="users" <?php if($bulkForm['target_type']==='users'){ echo 'selected'; } ?>>Tous les comptes de l'application</option>
                <?php endif; ?>
            </select>

            <div class="bm-champ bm-champ--a" id="bmZonePersonnes">
                <label class="bm-champ__libelle" for="recipientSearch">À</label>
                <div class="bm-champ__valeur">
                    <div id="selectedRecipients" class="bm-puces"></div>
                    <input type="text" id="recipientSearch" class="gmail-input" placeholder="Nom, matricule ou adresse e-mail" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> autocomplete="off" />
                    <div id="recipientResults" class="hidden gmail-results"></div>
                </div>
                <button type="button" class="bm-lien" id="bmCollerListe" aria-expanded="false" title="Coller plusieurs adresses d'un coup">Coller une liste</button>
            </div>
            <div class="bm-liste-adresses" id="bmZoneListe" hidden>
                <label class="bm-champ__libelle" for="customRecipients">Adresses (une par ligne, ou séparées par des virgules)</label>
                <textarea id="customRecipients" name="custom_recipients" rows="4" placeholder="adresse1@domaine.com&#10;adresse2@domaine.com" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>><?=e($bulkForm['custom_recipients'])?></textarea>
            </div>
            <?php if ($modeEnseignant): ?>
            <p class="bm-aide" id="bmAidePersonnes">Un seul message, comme dans Gmail : chaque destinataire voit les adresses des autres.</p>
            <?php endif; ?>

            <div class="bm-groupe" id="bmZoneGroupe" hidden>
                <?php if ($modeEnseignant): ?>
                <select id="bmGroupe" hidden><option value="mes_cours">Mes étudiants</option></select>
                <label class="bm-champ__libelle" for="bmCours">Cours de l'année <?=e(courrielEnseignantAnnee())?></label>
                <select id="bmCours" name="cours_id" class="bm-select" <?php if(!$canUseBulkEmail || !$coursEnseignant){ echo 'disabled'; } ?>>
                    <?php if (!$coursEnseignant): ?>
                    <option value="0">Aucun étudiant inscrit à vos cours cette année</option>
                    <?php else: ?>
                    <option value="0" data-libelle="Tous mes cours">Tous mes cours (<?=$etudiantsEnseignant?> étudiant<?=$etudiantsEnseignant > 1 ? 's' : ''?>)</option>
                    <?php foreach ($coursEnseignant as $coursMail):
                        $libelleCours = trim($coursMail['sigle'] . ' — ' . $coursMail['titre'], ' —');
                        $compteCours = $coursMail['etudiants'] . ' étudiant' . ($coursMail['etudiants'] > 1 ? 's' : '')
                            . ($coursMail['sans_adresse'] > 0 ? ', ' . $coursMail['sans_adresse'] . ' sans adresse' : '');
                    ?>
                    <option value="<?=$coursMail['id']?>" data-libelle="<?=e($libelleCours)?>" <?php if((int)$bulkForm['cours_id'] === $coursMail['id']){ echo 'selected'; } ?>><?=e($libelleCours)?><?= $coursMail['semestre'] > 0 ? ' · S' . $coursMail['semestre'] : '' ?> (<?=e($compteCours)?>)</option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="bm-aide">En copie cachée : aucun étudiant ne voit l'adresse des autres. Les étudiants suspendus ou retirés ne reçoivent rien.</p>
                <?php else: ?>
                <label class="bm-champ__libelle" for="bmGroupe">Groupe</label>
                <select id="bmGroupe" class="bm-select" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                    <option value="students">Tous les étudiants</option>
                    <option value="students_unregularized">Étudiants avec un solde à payer</option>
                    <option value="teachers">Tous les enseignants</option>
                    <option value="users">Tous les comptes de l'application</option>
                </select>
                <?php endif; ?>
                <div id="studentFilters" class="bm-filtres">
                    <div class="bm-filtres__ligne">
                        <span class="bm-champ__libelle">Mentions</span>
                        <div class="bm-pastilles" id="bmMentions" role="group" aria-label="Mentions">
                            <button type="button" class="bm-pastille" data-mention="" aria-pressed="true">Toutes</button>
                            <?php foreach ($mentionOptions as $mentionOption): $m = (string) $mentionOption; ?>
                            <button type="button" class="bm-pastille" data-mention="<?=e($m)?>" aria-pressed="<?= in_array($m, $bulkForm['mention_filters'], true) ? 'true' : 'false' ?>"><?=e($nomsMentions[$m] ?? $m)?></button>
                            <?php endforeach; ?>
                        </div>
                        <select id="mentionFilters" name="mention_filters[]" multiple hidden <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                            <?php foreach ($mentionOptions as $mentionOption): ?>
                                <option value="<?=e((string)$mentionOption)?>" <?php if(in_array((string)$mentionOption, $bulkForm['mention_filters'], true)){ echo 'selected'; } ?>><?=e((string)$mentionOption)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bm-filtres__ligne">
                        <label class="bm-champ__libelle" for="levelFilter">Niveau</label>
                        <select id="levelFilter" name="level_filter" class="bm-select bm-select--court" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?>>
                            <option value="">Tous les niveaux</option>
                            <?php foreach ($levelOptions as $levelOption):
                                $lv = (int)$levelOption;
                                $label = $lv <= 3 ? ('Licence ' . $lv) : ('Master ' . ($lv - 3));
                            ?>
                                <option value="<?=$lv?>" <?php if((string)$lv === (string)$bulkForm['level_filter']){ echo 'selected'; } ?>><?=$label?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bm-champ">
                <label class="bm-champ__libelle" for="subjectInput">Objet</label>
                <input type="text" id="subjectInput" name="subject" maxlength="190" value="<?=e($bulkForm['subject'])?>" class="gmail-input" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> required />
            </div>

            <div id="messageEditor" class="gmail-editor bm-editeur" data-placeholder="Écrivez votre message… (vous pouvez y glisser des images)" contenteditable="<?php if(!$canUseBulkEmail){ echo 'false'; } else { echo 'true'; } ?>"><?=nl2br(e($bulkForm['message']))?></div>
            <input type="hidden" name="message" id="messagePlain" value="<?=e($bulkForm['message'])?>" />
            <input type="hidden" name="message_html" id="messageHtml" value="" />

            <div id="attachmentsList" class="bm-pieces-jointes"></div>

            <details class="bm-apercu" id="recipientPreviewWrap">
                <summary>Voir la liste des destinataires (<span id="recipientPreviewCount">0</span>)</summary>
                <div id="recipientPreviewList" class="gmail-preview-list">
                    <div class="gmail-hint">Aucun destinataire pour l’instant.</div>
                </div>
            </details>

            <div id="sendingState" class="hidden bm-envoi-etat">Envoi en cours…</div>
            <div id="sendProgressWrap" class="gmail-send-progress hidden">
                <div class="gmail-send-progress-head">
                    <span>Envoi en cours</span>
                    <span id="sendProgressSummary">0 / 0</span>
                </div>
                <div class="bm-barre-envoi"><span id="bmBarreEnvoi"></span></div>
                <div id="sendProgressList" class="gmail-send-progress-list"></div>
            </div>
        </div>

        <div class="bm-redaction__pied">
            <button id="sendBtn" type="submit" <?php if(!$peutEnvoyer){ echo 'disabled'; } ?>>
                <i class="bi bi-send-fill" aria-hidden="true"></i> Envoyer
            </button>
            <label class="bm-icone" for="attachmentsInput" title="Joindre des fichiers (5 Mo par fichier, 15 Mo au total)" aria-label="Joindre des fichiers">
                <i class="bi bi-paperclip" aria-hidden="true"></i>
            </label>
            <input type="file" id="attachmentsInput" name="attachments[]" multiple class="bm-cache" <?php if(!$canUseBulkEmail){ echo 'disabled'; } ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar" />
            <span class="bm-portee" id="bmPortee" aria-live="polite">Aucun destinataire</span>
            <button type="button" class="bm-icone bm-icone--droite" data-redaction="supprimer" title="Supprimer le brouillon" aria-label="Supprimer le brouillon"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </div>
    </form>

    <div class="bm-dialogue" id="bmDialogue" role="alertdialog" aria-modal="true" aria-labelledby="bmDialogueTitre" hidden>
        <div class="bm-dialogue__carte">
            <h2 id="bmDialogueTitre"></h2>
            <p id="bmDialogueTexte"></p>
            <div class="bm-dialogue__actions">
                <button type="button" class="bm-bouton bm-bouton--discret" data-reponse="non">Annuler</button>
                <button type="button" class="bm-bouton" data-reponse="oui" id="bmDialogueOui"></button>
            </div>
        </div>
    </div>

    <div class="gm-annonce" id="gmAnnonce" role="status" aria-live="polite"></div>

    <?php /* Ancienne fenetre de lecture : encore referencee par l'ancien script, jamais affichee. */ ?>
    <div class="gmail-modal" id="mailReadModal" aria-hidden="true" hidden>
        <h3 id="mailReadTitle"></h3><div id="mailReadMeta"></div><button type="button" id="mailReadClose"></button><div id="mailReadBody"></div>
    </div>

    <script>window.BOITE_GMAIL_ADRESSE = <?=json_encode($adresseBoite)?>; window.BOITE_DEPART = <?=json_encode($currentBox)?>; window.BOITE_MODE = <?=json_encode($modeEnseignant ? 'enseignant' : 'registrariat')?>;</script>
    <script src="<?=$app_base?>/src/js/boite-gmail.js?v=<?=@filemtime(__DIR__ . '/js/boite-gmail.js')?>"></script>

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
        const coursFilter = document.getElementById('bmCours'); // « Mes étudiants » d'un enseignant
        const recipientPreviewWrap = document.getElementById('recipientPreviewWrap');
        const recipientPreviewList = document.getElementById('recipientPreviewList');
        const recipientPreviewCount = document.getElementById('recipientPreviewCount');
        const sendBtn = document.getElementById('sendBtn');
        const sendingState = document.getElementById('sendingState');
        const sendProgressWrap = document.getElementById('sendProgressWrap');
        const sendProgressSummary = document.getElementById('sendProgressSummary');
        const sendProgressList = document.getElementById('sendProgressList');
        const mailStatus = document.getElementById('mailStatus');
        const attachmentsInput = document.getElementById('attachmentsInput');
        const attachmentsList = document.getElementById('attachmentsList');
        const subjectInput = document.getElementById('subjectInput');
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

        function showMailboxLoading(box) {
            if (box === 'compose') {
                return;
            }
            currentMailboxBox = box;
            setActiveNav(box);
            if (form) {
                form.style.display = 'none';
            }
            if (mailboxPanel) {
                mailboxPanel.style.display = '';
            }
            if (mailboxHead) {
                mailboxHead.textContent = (box === 'sent') ? 'Messages envoyes' : (box === 'inbox' ? 'Boite de reception' : 'Boite');
            }
            if (mailboxList) {
                mailboxList.innerHTML = '<div class="gmail-loading"><span class="gmail-loading-dot"></span>Chargement des messages IMAP...</div>';
            }
        }

        function loadMailboxBox(box, updateUrl) {
            if (typeof updateUrl === 'undefined') {
                updateUrl = true;
            }

            /* La boite Gmail (src/js/boite-gmail.js) affiche dossiers et
               messages ; ce script garde le formulaire d'envoi. */
            if (window.BoiteGmail) {
                currentMailboxBox = box;
                window.BoiteGmail.afficher(box, updateUrl);
                return;
            }

            if (updateUrl) {
                pushMailboxUrl(box);
            }

            if (box === 'compose') {
                abortMailboxRequest();
                mailboxRequestToken++;
                switchMailboxView('compose', 'Nouveau message', [], null, null, null, false);
                return;
            }

            showMailboxLoading(box);
            abortMailboxRequest();
            const requestToken = ++mailboxRequestToken;

            if (typeof AbortController !== 'undefined') {
                mailboxFetchController = new AbortController();
            }

            const url = APP_BASE + '/settings/bulk-email?ajax=mailbox-view&box=' + encodeURIComponent(box);
            const fetchOptions = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
            if (mailboxFetchController) {
                fetchOptions.signal = mailboxFetchController.signal;
            }

            fetch(url, fetchOptions)
                .then(function (res) {
                    return res.json().then(function (json) {
                        if (!res.ok || !json.success) {
                            throw new Error((json && json.error) ? json.error : 'mailbox_failed');
                        }
                        return json;
                    });
                })
                .then(function (payload) {
                    if (requestToken !== mailboxRequestToken) {
                        return;
                    }
                    switchMailboxView(
                        String(payload.box || box),
                        String(payload.title || ''),
                        Array.isArray(payload.items) ? payload.items : [],
                        payload.counts || null,
                        String(payload.source || 'local'),
                        String(payload.warning || ''),
                        false
                    );
                })
                .catch(function (err) {
                    if (requestToken !== mailboxRequestToken) {
                        return;
                    }
                    if (err && (err.name === 'AbortError' || String(err.message || '').toLowerCase() === 'aborterror')) {
                        return;
                    }
                    if (typeof Toast !== 'undefined' && typeof Toast.error === 'function') {
                        Toast.error('Impossible de charger la boite.');
                    }
                })
                .finally(function () {
                    if (requestToken === mailboxRequestToken) {
                        mailboxFetchController = null;
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
                loadMailboxBox(box, true);
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
        let mailboxRequestToken = 0;
        let mailboxFetchController = null;
        const REGULARIZATION_SUBJECT = 'Rappel de régularisation de votre écolage';
        
        const REGULARIZATION_BODY_L3 = [
            'Bonjour {{nom_etudiant}},',
            '',
            'Sauf erreur de notre part, votre solde des frais de scolarité est de <strong>{{montant_restant}}</strong>.',
            '',
            'En tant qu\'étudiant de <strong>Licence 3</strong>, veuillez noter que <strong style="color: #d32f2f;">le solde doit être intégralement réglé au plus tard le 10 juillet 2026</strong>. Vous pourrez retirer votre Financial Slip <strong>du 06 au 10 juillet 2026</strong>.',
            '',
            '<strong style="color: #d32f2f;">⚠️ IMPORTANT :</strong>',
            '• <strong>Aucune négociation ni dérogation ne sera accordée</strong>',
            '• <strong>Sans règlement intégral du solde ET sans Financial Slip</strong>, vous ne serez <strong>PAS autorisé à se présenter aux examens finaux</strong>',
            '',
            'En cas de paiement déjà effectué, veuillez ignorer ce message ou contacter le bureau de la comptabilité.',
            '',
            'Cordialement,',
            'Service de la Comptabilité'
        ].join('\n');

        const REGULARIZATION_BODY_L1L2 = [
            'Bonjour {{nom_etudiant}},',
            '',
            'Sauf erreur de notre part, votre solde des frais de scolarité est de <strong>{{montant_restant}}</strong>.',
            '',
            'En tant qu\'étudiant de <strong>Licence 1 ou 2</strong>, veuillez noter que <strong style="color: #d32f2f;">le solde doit être intégralement réglé au plus tard le 17 juillet 2026</strong>. Vous pourrez retirer votre Financial Slip <strong>du 03 au 17 juillet 2026</strong>.',
            '',
            '<strong style="color: #d32f2f;">⚠️ IMPORTANT :</strong>',
            '• <strong>Aucune négociation ni dérogation ne sera accordée</strong>',
            '• <strong>Sans règlement intégral du solde ET sans Financial Slip</strong>, vous ne serez <strong>PAS autorisé à se présenter aux examens finaux</strong>',
            '',
            'En cas de paiement déjà effectué, veuillez ignorer ce message ou contacter le bureau de la comptabilité.',
            '',
            'Cordialement,',
            'Service de la Comptabilité'
        ].join('\n');

        function pushMailboxUrl(box) {
            try {
                window.history.pushState({ box: box }, '', APP_BASE + '/settings/bulk-email?box=' + encodeURIComponent(box));
            } catch (e) {}
        }

        function abortMailboxRequest() {
            if (mailboxFetchController && typeof mailboxFetchController.abort === 'function') {
                try {
                    mailboxFetchController.abort();
                } catch (e) {}
            }
            mailboxFetchController = null;
        }

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

            const isStudents = targetType.value === 'students' || targetType.value === 'students_unregularized';
            studentFilters.style.display = isStudents ? 'block' : 'none';

            if (mentionFilters) {
                mentionFilters.disabled = !isStudents;
            }

            if (levelFilter) {
                levelFilter.disabled = !isStudents;
            }

            refreshRecipientPreview();
        }

        function getEditorPlainValue() {
            if (!messageEditor) {
                return '';
            }
            const clone = messageEditor.cloneNode(true);
            clone.querySelectorAll('.inline-image-remove, .inline-image-handle').forEach(function (el) {
                el.remove();
            });
            return (clone.innerText || '').replace(/\u00a0/g, ' ').trim();
        }

        function setEditorText(text) {
            if (!messageEditor) {
                return;
            }
            const safe = escapeHtml(String(text || ''));
            messageEditor.innerHTML = safe.replace(/\r?\n/g, '<br>');
        }

        function setEditorTextWithHtml(html) {
            if (!messageEditor) {
                return;
            }
            messageEditor.innerHTML = String(html || '').replace(/\r?\n/g, '<br>');
        }

        function applyRegularizationTemplate() {
            if (targetType.value !== 'students_unregularized') {
                return;
            }

            const hasSubject = subjectInput && String(subjectInput.value || '').trim() !== '';
            const hasMessage = getEditorPlainValue() !== '';

            if (hasSubject || hasMessage) {
                return;
            }

            // Déterminer le template selon le niveau
            let templateBody = REGULARIZATION_BODY_L1L2;
            if (levelFilter && levelFilter.value) {
                const level = parseInt(levelFilter.value, 10);
                if (level === 3) {
                    templateBody = REGULARIZATION_BODY_L3;
                } else if (level === 1 || level === 2) {
                    templateBody = REGULARIZATION_BODY_L1L2;
                }
            }

            if (subjectInput) {
                subjectInput.value = REGULARIZATION_SUBJECT;
            }
            setEditorTextWithHtml(templateBody);
            updateMessageFields();
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
                if (coursFilter && targetType.value === 'mes_cours') {
                    params.set('cours_id', coursFilter.value || '0');
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
                /* Le nom seul (l'adresse au survol) ; l'adresse quand il n'y a pas de nom. */
                const label = (item.name && item.name.toLowerCase() !== item.email) ? item.name : item.email;
                chip.title = item.email;
                chip.innerHTML = '<span>' + escapeHtml(label) + '</span><button type="button" data-email="' + escapeHtml(item.email) + '" title="Retirer" aria-label="Retirer ' + escapeHtml(label) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
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

            /* Une etiquette retirable par fichier : src/js/boite-gmail.js. */
            if (window.BoiteGmail && window.BoiteGmail.piecesJointes) {
                window.BoiteGmail.piecesJointes();
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
                applyRegularizationTemplate();
            });
        }

        if (coursFilter) {
            coursFilter.addEventListener('change', function () {
                refreshRecipientPreview();
                applyRegularizationTemplate();
            });
        }

        targetType.addEventListener('change', function () {
            toggleStudentFilters();
            applyRegularizationTemplate();
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

        function resetSendProgress() {
            if (sendProgressWrap) {
                sendProgressWrap.classList.remove('hidden');
            }
            if (sendProgressSummary) {
                sendProgressSummary.textContent = '0 / 0';
            }
            if (sendProgressList) {
                sendProgressList.innerHTML = '<div class="gmail-hint">Preparation de l\'envoi...</div>';
            }
        }

        function appendSendProgressRow(recipient, status, sent, failed, processed, total) {
            if (!sendProgressList) {
                return;
            }

            if (sendProgressList.querySelector('.gmail-hint')) {
                sendProgressList.innerHTML = '';
            }

            const row = document.createElement('div');
            row.className = 'gmail-send-progress-item';

            const statusClass = status === 'envoye' ? 'gmail-send-progress-ok' : 'gmail-send-progress-fail';
            const statusLabel = status === 'envoye' ? 'Envoye' : 'Echec';

            row.innerHTML = '<span>' + escapeHtml(recipient || '') + '</span><span class="' + statusClass + '">' + statusLabel + '</span>';
            sendProgressList.prepend(row);

            while (sendProgressList.children.length > 120) {
                sendProgressList.removeChild(sendProgressList.lastChild);
            }

            if (sendProgressSummary) {
                sendProgressSummary.textContent = String(Number(processed || 0)) + ' / ' + String(Number(total || 0))
                    + ' (OK: ' + String(Number(sent || 0)) + ', KO: ' + String(Number(failed || 0)) + ')';
            }
        }

        function readNdjsonStream(response, onEvent) {
            if (!response || !response.body || !window.TextDecoder) {
                return Promise.reject(new Error('stream_not_supported'));
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            function pump() {
                return reader.read().then(function (chunk) {
                    if (chunk.done) {
                        if (buffer.trim()) {
                            const line = buffer.trim();
                            buffer = '';
                            try {
                                onEvent(JSON.parse(line));
                            } catch (e) {}
                        }
                        return;
                    }

                    buffer += decoder.decode(chunk.value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    lines.forEach(function (line) {
                        const trimmed = line.trim();
                        if (!trimmed) {
                            return;
                        }
                        try {
                            onEvent(JSON.parse(trimmed));
                        } catch (e) {}
                    });

                    return pump();
                });
            }

            return pump();
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

            const url = form.getAttribute('action') + '?ajax=send-bulk-email&stream=1';
            let loadingToastId = null;
            let streamedResult = null;
            const streamProgressState = {
                processed: 0,
                total: 0,
                sent: 0,
                failed: 0
            };

            resetSendProgress();

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
                    if (!res.ok) {
                        return res.text().then(function (text) {
                            const json = parseJsonFromText(text);
                            throw new Error(json.message || json.error || 'http_' + res.status);
                        });
                    }

                    return readNdjsonStream(res, function (evt) {
                        if (!evt || typeof evt !== 'object') {
                            return;
                        }
                        if (evt.type === 'progress') {
                            streamProgressState.processed = Number(evt.processed || 0);
                            streamProgressState.total = Number(evt.total || 0);
                            streamProgressState.sent = Number(evt.sent || 0);
                            streamProgressState.failed = Number(evt.failed || 0);

                            appendSendProgressRow(
                                String(evt.recipient || ''),
                                String(evt.status || ''),
                                streamProgressState.sent,
                                streamProgressState.failed,
                                streamProgressState.processed,
                                streamProgressState.total
                            );
                            return;
                        }
                        if (evt.type === 'result') {
                            streamedResult = evt.result || null;
                        }
                    }).then(function () {
                        if (!streamedResult) {
                            if (streamProgressState.processed > 0) {
                                return {
                                    success: false,
                                    message: 'Flux interrompu avant resultat final. Progression recue: ' + streamProgressState.processed + ' / ' + streamProgressState.total + '.',
                                    sent: streamProgressState.sent,
                                    failed: streamProgressState.failed,
                                    total: streamProgressState.total
                                };
                            }
                            throw new Error('stream_no_result');
                        }
                        return streamedResult;
                    });
                })
                .then(function (result) {
                    /* Reussi : la fenetre de redaction se ferme et la boite l'annonce. */
                    if (result && result.success && window.BoiteGmail && window.BoiteGmail.apresEnvoi) {
                        window.BoiteGmail.apresEnvoi(result);
                        return;
                    }
                    showStatus(result);
                    if (result && result.success) {
                        /* L'envoi part de no-reply : il apparait dans ses messages envoyes. */
                        setTimeout(function () {
                            loadMailboxBox('sent');
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
                loadMailboxBox(boxFromState, false);
                return;
            }

            const params = new URLSearchParams(window.location.search || '');
            const boxFromUrl = params.get('box') || 'compose';
            loadMailboxBox(boxFromUrl, false);
        });

        setActiveNav(<?=json_encode($currentBox, JSON_UNESCAPED_UNICODE)?>);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMailModal();
            }
        });

        <?php if ($currentBox !== 'compose') { ?>
        loadMailboxBox(<?=json_encode($currentBox, JSON_UNESCAPED_UNICODE)?>, false);
        <?php } ?>

        parseTextareaRecipients();
        toggleStudentFilters();
        applyRegularizationTemplate();
        refreshRecipientPreview();
        renderAttachmentsList();
        updateMessageFields();
    })();
    </script>
</body>
</html>
