<?php

if (!function_exists('newsletterNormalizeEmail')) {
    function newsletterNormalizeEmail($email) {
        $email = strtolower(trim((string)$email));
        return preg_replace('/\s+/', '', $email);
    }
}

if (!function_exists('newsletterGenerateToken')) {
    function newsletterGenerateToken($length = 32) {
        try {
            return bin2hex(random_bytes(max(16, (int)ceil($length / 2))));
        } catch (Throwable $e) {
            return bin2hex(pack('H*', md5(uniqid((string)mt_rand(), true))));
        }
    }
}

if (!function_exists('ensureNewsletterTables')) {
    function ensureNewsletterTables(PDO $dtb) {
        static $ready = false;
        if ($ready) {
            return;
        }

        $dtb->exec("CREATE TABLE IF NOT EXISTS t_newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            full_name VARCHAR(190) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            unsubscribe_token VARCHAR(128) NOT NULL,
            source VARCHAR(64) NOT NULL DEFAULT 'public-form',
            subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_newsletter_email (email),
            UNIQUE KEY uniq_newsletter_token (unsubscribe_token),
            KEY idx_newsletter_status (status),
            KEY idx_newsletter_source (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dtb->exec("CREATE TABLE IF NOT EXISTS t_newsletter_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255) NOT NULL,
            body_text MEDIUMTEXT NOT NULL,
            body_html MEDIUMTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_by VARCHAR(190) NULL,
            recipient_total INT NOT NULL DEFAULT 0,
            sent_total INT NOT NULL DEFAULT 0,
            failed_total INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_newsletter_campaign_status (status, scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dtb->exec("CREATE TABLE IF NOT EXISTS t_newsletter_deliveries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            subscriber_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            sent_at DATETIME NULL,
            error_message VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_newsletter_campaign_subscriber (campaign_id, subscriber_id),
            KEY idx_newsletter_delivery_status (status),
            KEY idx_newsletter_delivery_campaign (campaign_id),
            KEY idx_newsletter_delivery_subscriber (subscriber_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ready = true;
    }
}

if (!function_exists('newsletterRenderText')) {
    function newsletterRenderText($value, array $subscriber = [], $unsubscribeUrl = '') {
        $value = (string)$value;
        $name = trim((string)($subscriber['full_name'] ?? ''));
        $email = trim((string)($subscriber['email'] ?? ''));
        return str_replace(['{{nom}}', '{{name}}', '{{email}}', '{{unsubscribe_url}}'], [
            $name !== '' ? $name : 'abonne',
            $name !== '' ? $name : 'abonne',
            $email,
            $unsubscribeUrl,
        ], $value);
    }
}

if (!function_exists('newsletterRenderBodyHtml')) {
    function newsletterRenderBodyHtml($value, array $subscriber = [], $unsubscribeUrl = '') {
        $rendered = newsletterRenderText($value, $subscriber, $unsubscribeUrl);
        return nl2br(htmlspecialchars($rendered, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('newsletterSubscriptionLink')) {
    function newsletterSubscriptionLink(array $subscriber) {
        $base = defined('APP_BASE') ? APP_BASE : '';
        $token = urlencode((string)($subscriber['unsubscribe_token'] ?? ''));
        return rtrim($base, '/') . '/newsletter/unsubscribe/' . $token;
    }
}

if (!function_exists('newsletterUpsertSubscriber')) {
    function newsletterUpsertSubscriber(PDO $dtb, $email, $fullName = '', $source = 'public-form') {
        ensureNewsletterTables($dtb);

        $email = newsletterNormalizeEmail($email);
        $fullName = trim((string)$fullName);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Adresse email invalide.'];
        }

        $token = newsletterGenerateToken(64);

        $stmt = $dtb->prepare("INSERT INTO t_newsletter_subscribers (email, full_name, status, unsubscribe_token, source, subscribed_at, unsubscribed_at)
            VALUES (:email, :full_name, 'active', :token, :source, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                status = 'active',
                unsubscribe_token = VALUES(unsubscribe_token),
                source = VALUES(source),
                subscribed_at = NOW(),
                unsubscribed_at = NULL");
        $stmt->execute([
            'email' => $email,
            'full_name' => $fullName,
            'token' => $token,
            'source' => substr(trim((string)$source), 0, 64),
        ]);

        $fetch = $dtb->prepare('SELECT id, email, full_name, status, unsubscribe_token, subscribed_at, unsubscribed_at FROM t_newsletter_subscribers WHERE email = :email LIMIT 1');
        $fetch->execute(['email' => $email]);

        return [
            'success' => true,
            'message' => 'Abonne enregistre.',
            'subscriber' => $fetch->fetch(PDO::FETCH_ASSOC) ?: [],
        ];
    }
}

if (!function_exists('newsletterGetSubscriberByToken')) {
    function newsletterGetSubscriberByToken(PDO $dtb, $token) {
        ensureNewsletterTables($dtb);
        $stmt = $dtb->prepare('SELECT id, email, full_name, status, unsubscribe_token, subscribed_at, unsubscribed_at FROM t_newsletter_subscribers WHERE unsubscribe_token = :token LIMIT 1');
        $stmt->execute(['token' => trim((string)$token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('newsletterMarkUnsubscribed')) {
    function newsletterMarkUnsubscribed(PDO $dtb, $token) {
        ensureNewsletterTables($dtb);
        $subscriber = newsletterGetSubscriberByToken($dtb, $token);
        if (!$subscriber) {
            return ['success' => false, 'message' => 'Lien de desabonnement invalide.'];
        }

        $stmt = $dtb->prepare("UPDATE t_newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => (int)$subscriber['id']]);

        return ['success' => true, 'message' => 'Desabonnement confirme.', 'subscriber' => $subscriber];
    }
}

if (!function_exists('newsletterListSubscribers')) {
    function newsletterListSubscribers(PDO $dtb, $limit = 200) {
        ensureNewsletterTables($dtb);
        $stmt = $dtb->prepare('SELECT id, email, full_name, status, unsubscribe_token, source, subscribed_at, unsubscribed_at FROM t_newsletter_subscribers ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, min(1000, (int)$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('newsletterListCampaigns')) {
    function newsletterListCampaigns(PDO $dtb, $limit = 20) {
        ensureNewsletterTables($dtb);
        $stmt = $dtb->prepare('SELECT id, subject, status, scheduled_at, sent_at, created_by, recipient_total, sent_total, failed_total, created_at, updated_at FROM t_newsletter_campaigns ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, min(100, (int)$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('newsletterCreateCampaign')) {
    function newsletterCreateCampaign(PDO $dtb, $subject, $bodyText, $bodyHtml = '', $scheduledAt = null, $createdBy = null, $status = 'draft') {
        ensureNewsletterTables($dtb);

        $subject = trim((string)$subject);
        $bodyText = trim((string)$bodyText);
        $bodyHtml = trim((string)$bodyHtml);

        if ($subject === '' || $bodyText === '') {
            return ['success' => false, 'message' => 'Sujet et contenu sont obligatoires.'];
        }

        $scheduledAtValue = null;
        if (!empty($scheduledAt)) {
            $timestamp = strtotime((string)$scheduledAt);
            if ($timestamp !== false) {
                $scheduledAtValue = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $stmt = $dtb->prepare('INSERT INTO t_newsletter_campaigns (subject, body_text, body_html, status, scheduled_at, created_by) VALUES (:subject, :body_text, :body_html, :status, :scheduled_at, :created_by)');
        $stmt->execute([
            'subject' => substr($subject, 0, 255),
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'status' => $status,
            'scheduled_at' => $scheduledAtValue,
            'created_by' => substr(trim((string)$createdBy), 0, 190),
        ]);

        return [
            'success' => true,
            'message' => 'Campagne enregistree.',
            'campaign_id' => (int)$dtb->lastInsertId(),
        ];
    }
}

if (!function_exists('newsletterSendTransport')) {
    function newsletterSendTransport($recipient, $subject, $htmlBody, $plainBody, $fromEmail, $fromName) {
        $recipient = trim((string)$recipient);
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email destinataire invalide.'];
        }

        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            return ['success' => false, 'message' => 'Envoi email desactive par la configuration.'];
        }

        $subject = str_replace(["\r", "\n"], ' ', (string)$subject);
        $fromEmail = trim((string)$fromEmail);
        $fromName = trim((string)$fromName);
        if ($fromEmail === '') {
            $fromEmail = 'no-reply@localhost';
        }
        if ($fromName === '') {
            $fromName = 'Newsletter';
        }

        $phpmailerSrc = (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__)) . '/vendor/phpmailer/phpmailer/src';
        if (is_file($phpmailerSrc . '/PHPMailer.php')) {
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
                require_once $phpmailerSrc . '/Exception.php';
                require_once $phpmailerSrc . '/PHPMailer.php';
                require_once $phpmailerSrc . '/SMTP.php';
            }

            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
                    $mail->SMTPAuth = defined('SMTP_AUTH') ? SMTP_AUTH : true;
                    $mail->Username = defined('SMTP_USER') ? SMTP_USER : $fromEmail;
                    $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
                    $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
                    $mail->CharSet = 'UTF-8';
                    $mail->setFrom($fromEmail, $fromName);
                    $mail->addAddress($recipient);
                    $mail->Subject = $subject;
                    $mail->isHTML(true);
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $plainBody;
                    $mail->send();

                    return ['success' => true, 'message' => 'Envoye via PHPMailer.'];
                } catch (Throwable $e) {
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }
        }

        $boundary = 'nl_' . md5((string)microtime(true));
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $plainBody . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $ok = @mail($recipient, $subject, $body, implode("\r\n", $headers), '-f ' . $fromEmail);
        return $ok ? ['success' => true, 'message' => 'Envoye via mail().'] : ['success' => false, 'message' => 'Echec de mail().'];
    }
}

if (!function_exists('newsletterRenderCampaignHtml')) {
    function newsletterRenderCampaignHtml($subject, $bodyHtml, $unsubscribeUrl) {
        $subject = htmlspecialchars((string)$subject, ENT_QUOTES, 'UTF-8');
        $bodyHtml = trim((string)$bodyHtml);
        if ($bodyHtml === '') {
            $bodyHtml = '<p>Aucun contenu.</p>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $subject . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;background:#f4f7fb;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #dbe4f0;border-radius:14px;overflow:hidden;">'
            . '<tr><td style="background:#0a1628;color:#e8f1f8;padding:24px 28px;font-size:22px;font-weight:700;">Newsletter</td></tr>'
            . '<tr><td style="padding:28px;line-height:1.7;font-size:15px;">' . $bodyHtml . '</td></tr>'
            . '<tr><td style="padding:0 28px 24px 28px;font-size:12px;color:#64748b;line-height:1.6;">'
            . 'Vous recevez cet email car vous etes abonne a la newsletter.<br>'
            . 'Desabonnement: <a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0b63ce;">' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '</a>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</body></html>';
    }
}

if (!function_exists('newsletterSendCampaign')) {
    function newsletterSendCampaign(PDO $dtb, $campaignId, callable $progressCallback = null) {
        ensureNewsletterTables($dtb);

        $campaignStmt = $dtb->prepare('SELECT * FROM t_newsletter_campaigns WHERE id = :id LIMIT 1');
        $campaignStmt->execute(['id' => (int)$campaignId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            return ['success' => false, 'message' => 'Campagne introuvable.'];
        }

        $subscribers = $dtb->query("SELECT id, email, full_name, status, unsubscribe_token FROM t_newsletter_subscribers WHERE status = 'active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $recipientTotal = count($subscribers);

        $dtb->prepare('UPDATE t_newsletter_campaigns SET status = :status, recipient_total = :recipient_total, sent_total = 0, failed_total = 0 WHERE id = :id')->execute([
            'status' => 'sending',
            'recipient_total' => $recipientTotal,
            'id' => (int)$campaignId,
        ]);

        $sent = 0;
        $failed = 0;
        $fromEmail = defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@localhost';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Newsletter';
        $baseUrl = defined('APP_BASE') ? APP_BASE : '';

        foreach ($subscribers as $subscriber) {
            $unsubscribeUrl = rtrim($baseUrl, '/') . '/newsletter/unsubscribe/' . urlencode((string)$subscriber['unsubscribe_token']);
            $renderedText = newsletterRenderText((string)$campaign['body_text'], $subscriber, $unsubscribeUrl);
            $renderedHtml = trim((string)($campaign['body_html'] ?? ''));
            if ($renderedHtml !== '') {
                $renderedHtml = newsletterRenderText($renderedHtml, $subscriber, $unsubscribeUrl);
            } else {
                $renderedHtml = newsletterRenderBodyHtml($campaign['body_text'], $subscriber, $unsubscribeUrl);
            }

            $html = newsletterRenderCampaignHtml((string)$campaign['subject'], $renderedHtml, $unsubscribeUrl);
            $sendResult = newsletterSendTransport((string)$subscriber['email'], (string)$campaign['subject'], $html, $renderedText . "\n\nDesabonnement: " . $unsubscribeUrl, $fromEmail, $fromName);

            $deliveryStatus = $sendResult['success'] ? 'sent' : 'failed';
            $deliveryError = $sendResult['success'] ? null : substr((string)($sendResult['message'] ?? 'Erreur envoi'), 0, 255);

            $deliveryStmt = $dtb->prepare('INSERT INTO t_newsletter_deliveries (campaign_id, subscriber_id, status, sent_at, error_message) VALUES (:campaign_id, :subscriber_id, :status, :sent_at, :error_message) ON DUPLICATE KEY UPDATE status = VALUES(status), sent_at = VALUES(sent_at), error_message = VALUES(error_message)');
            $deliveryStmt->execute([
                'campaign_id' => (int)$campaignId,
                'subscriber_id' => (int)$subscriber['id'],
                'status' => $deliveryStatus,
                'sent_at' => $sendResult['success'] ? date('Y-m-d H:i:s') : null,
                'error_message' => $deliveryError,
            ]);

            if ($sendResult['success']) {
                $sent++;
            } else {
                $failed++;
            }

            if (is_callable($progressCallback)) {
                $progressCallback([
                    'subscriber' => $subscriber['email'],
                    'status' => $deliveryStatus,
                    'sent' => $sent,
                    'failed' => $failed,
                    'total' => $recipientTotal,
                ]);
            }
        }

        $finalStatus = $failed > 0 && $sent === 0 ? 'failed' : 'sent';
        $dtb->prepare('UPDATE t_newsletter_campaigns SET status = :status, sent_at = NOW(), sent_total = :sent_total, failed_total = :failed_total WHERE id = :id')->execute([
            'status' => $finalStatus,
            'sent_total' => $sent,
            'failed_total' => $failed,
            'id' => (int)$campaignId,
        ]);

        return [
            'success' => true,
            'message' => 'Campagne traitee.',
            'campaign_id' => (int)$campaignId,
            'recipient_total' => $recipientTotal,
            'sent' => $sent,
            'failed' => $failed,
            'status' => $finalStatus,
        ];
    }
}

if (!function_exists('newsletterProcessDueCampaigns')) {
    function newsletterProcessDueCampaigns(PDO $dtb, $limit = 5, callable $progressCallback = null) {
        ensureNewsletterTables($dtb);

        $stmt = $dtb->prepare("SELECT id FROM t_newsletter_campaigns WHERE status = 'scheduled' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id ASC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min(25, (int)$limit)), PDO::PARAM_INT);
        $stmt->execute();

        $processed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $processed[] = newsletterSendCampaign($dtb, (int)$row['id'], $progressCallback);
        }

        return $processed;
    }
}
