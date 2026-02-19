<?php
/**
 * BaseController - Contrôleur de base pour l'architecture MVC
 * Tous les contrôleurs héritent de cette classe
 */
class BaseController {

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
