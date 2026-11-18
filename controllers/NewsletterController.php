<?php

require_once ROOT_DIR . '/data/backdb.php';
require_once ROOT_DIR . '/data/newsletter.php';

class NewsletterController extends BaseController {
    public function index() {
        $this->requireLevelAccess(3);
        $this->initSecurity();

        $dtb = $this->newsletterDb();

        ensureNewsletterTables($dtb);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string)($_POST['newsletter_action'] ?? ''));

            if ($action === 'subscriber_add') {
                $result = newsletterUpsertSubscriber($dtb, $_POST['email'] ?? '', $_POST['full_name'] ?? '', 'admin-panel');
                $_SESSION['newsletter_flash'] = $result['message'];
                $this->redirectTo('/newsletter');
            }

            if ($action === 'subscriber_unsubscribe' && !empty($_POST['token'])) {
                $result = newsletterMarkUnsubscribed($dtb, (string)$_POST['token']);
                $_SESSION['newsletter_flash'] = $result['message'];
                $this->redirectTo('/newsletter');
            }

            if ($action === 'campaign_create') {
                $scheduledAt = trim((string)($_POST['scheduled_at'] ?? ''));
                $sendNow = !empty($_POST['send_now']);
                $status = ($sendNow || $scheduledAt !== '') ? 'scheduled' : 'draft';
                $result = newsletterCreateCampaign(
                    $dtb,
                    $_POST['subject'] ?? '',
                    $_POST['body_text'] ?? '',
                    $_POST['body_html'] ?? '',
                    $scheduledAt,
                    $this->currentUserLabel(),
                    $status
                );

                if (!empty($result['success']) && $sendNow) {
                    $sendResult = newsletterSendCampaign($dtb, (int)$result['campaign_id']);
                    $_SESSION['newsletter_flash'] = $sendResult['message'];
                } else {
                    $_SESSION['newsletter_flash'] = $result['message'];
                }

                $this->redirectTo('/newsletter');
            }
        }

        $newsletterFlash = $_SESSION['newsletter_flash'] ?? '';
        unset($_SESSION['newsletter_flash']);

        $this->renderSrc('newsletter.php');
    }

    public function subscribe() {
        $this->initSecurity();

        $dtb = $this->newsletterDb();

        ensureNewsletterTables($dtb);

        $newsletterFlash = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = newsletterUpsertSubscriber($dtb, $_POST['email'] ?? '', $_POST['full_name'] ?? '', 'public-form');
            $newsletterFlash = (string)($result['message'] ?? '');
            $_SESSION['newsletter_flash'] = $newsletterFlash;
            $this->redirectTo('/newsletter/subscribe');
        }

        $this->renderSrc('newsletter.subscribe.php');
    }

    public function unsubscribe($token = '') {
        $this->initSecurity();

        $dtb = $this->newsletterDb();

        ensureNewsletterTables($dtb);

        $unsubscribeResult = ['success' => false, 'message' => 'Lien de desabonnement invalide.'];
        if ($token !== '') {
            $unsubscribeResult = newsletterMarkUnsubscribed($dtb, $token);
        }

        $_SESSION['newsletter_unsubscribe_result'] = $unsubscribeResult;

        $this->renderSrc('newsletter.unsubscribe.php');
    }

    private function currentUserLabel() {
        $user = currentUser();
        if (!is_array($user)) {
            return '';
        }

        $parts = [];
        foreach (['nom', 'prenom', 'name', 'lastName', 'pseudo'] as $key) {
            if (!empty($user[$key])) {
                $parts[] = trim((string)$user[$key]);
            }
        }

        return trim(implode(' ', array_slice($parts, 0, 2)));
    }

    private function newsletterDb() {
        global $dtb;

        if ($dtb instanceof PDO) {
            return $dtb;
        }

        if (function_exists('getDbConnection')) {
            $dtb = getDbConnection();
            if ($dtb instanceof PDO) {
                if (class_exists('DB')) {
                    DB::init($dtb);
                }
                return $dtb;
            }
        }

        throw new RuntimeException('Connexion base de donnees indisponible pour la newsletter.');
    }
}
