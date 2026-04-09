<?php
/**
 * BaseController - Contrôleur de base pour l'architecture MVC
 * Tous les contrôleurs héritent de cette classe
 */
class BaseController {

    /**
     * Initialiser le middleware de securite pour les routes MVC
     */
    protected function initSecurity() {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        require_once ROOT_DIR . '/data/middleware.php';

        if (session_status() === PHP_SESSION_NONE) {
            Middleware::startSecureSession();
        }

        $initialized = true;
    }

    /**
     * Exiger une authentification sur une route MVC
     */
    protected function requireAuthenticated($redirectPath = '/login') {
        $this->initSecurity();
        $base = defined('APP_BASE') ? APP_BASE : '';

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . $base . $redirectPath, true, 302);
            exit;
        }
    }

    /**
     * Exiger un niveau maximum (1 = superadmin, 8 = etudiant)
     */
    protected function requireLevelAccess($requiredLevel, $message = null) {
        $this->initSecurity();

        $currentLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : null;
        $allowed = $currentLevel !== null ? ($currentLevel <= (int)$requiredLevel) : false;

        if (!$allowed && function_exists('hasLevel')) {
            $allowed = hasLevel((int)$requiredLevel);
        }

        if (!$allowed) {
            $defaultMessage = "Vous n'avez pas les privileges necessaires pour acceder à cette ressource.";
            $this->forbidden($message ?: $defaultMessage);
        }
    }

    /**
     * Exiger que l'utilisateur appartienne a une liste precise de niveaux
     */
    protected function requireAnyLevel(array $allowedLevels, $message = null) {
        $this->initSecurity();

        $currentLevel = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : 0;
        $normalized = array_map('intval', $allowedLevels);

        if (!in_array($currentLevel, $normalized, true)) {
            $defaultMessage = "Votre role n'est pas autorise a acceder a cette ressource.";
            $this->forbidden($message ?: $defaultMessage);
        }
    }

    /**
     * Reponse standard 403
     */
    protected function forbidden($message) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>403 - Acces refuse</title>';
        echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#0a1628;color:#e8f1f8;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}.card{max-width:560px;background:#0d1f3c;border:1px solid #1a3a5c;border-radius:12px;padding:24px}h1{margin:0 0 8px;color:#4e9ede}p{margin:0;line-height:1.5}</style></head><body>';
        echo '<div class="card"><h1>403 - Acces refuse</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
        echo '</body></html>';
        exit;
    }

    /**
     * Rendre une vue en l'incluant avec le bon contexte de répertoire
     * @param string $viewPath Chemin relatif à ROOT_DIR
     * @param string|null $workingDir Répertoire de travail (pour compatibilité des require relatifs)
     */
    protected function render($viewPath, $workingDir = null) {
        $fullPath = ROOT_DIR . '/' . $viewPath;

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo "Vue introuvable: $viewPath";
            return;
        }

        // Changer le répertoire de travail pour que les require relatifs fonctionnent
        if ($workingDir) {
            chdir($workingDir);
        } else {
            chdir(dirname($fullPath));
        }

        // Rendre $app_base disponible dans la vue (compatibilité arrière)
        $app_base = defined('APP_BASE') ? APP_BASE : '';

        require $fullPath;
        exit;
    }

    /**
     * Rendre une vue depuis le dossier src/
     * @param string $file Nom du fichier dans src/
     */
    protected function renderSrc($file) {
        $this->render('src/' . $file, ROOT_DIR . '/src');
    }

    /**
     * Rendre une vue depuis le dossier landing/
     * @param string $file Nom du fichier dans landing/
     */
    protected function renderLanding($file) {
        $this->render('landing/' . $file, ROOT_DIR . '/landing');
    }

    /**
     * Rendre une vue depuis le dossier inscription/
     * @param string $file Nom du fichier dans inscription/
     */
    protected function renderInscription($file) {
        $this->render('inscription/' . $file, ROOT_DIR . '/inscription');
    }

    /**
     * Rediriger vers une URL
     */
    protected function redirect($url, $code = 302) {
        header("Location: $url", true, $code);
        exit;
    }

    /**
     * Rediriger vers une route MVC
     */
    protected function redirectTo($path, $code = 302) {
        $this->redirect(url($path), $code);
    }
}
