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
            // Memorise la page demandee pour y revenir apres reconnexion.
            if (function_exists('memoriserUrlDemandee')) { memoriserUrlDemandee(); }
            header('Location: ' . $base . $redirectPath, true, 302);
            exit;
        }
    }

    /**
     * Refuser les comptes etudiants (classements, pages du personnel).
     * Leurs notes ne se consultent que dans « Mes Notes », qui applique la
     * publication par session (data/espace.etudiant.php).
     */
    protected function refuserEtudiant() {
        $this->initSecurity();
        $niveauEtudiant = defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8;
        if ((int) ($_SESSION['user_level'] ?? 0) === $niveauEtudiant
            || (function_exists('isStudent') && isStudent())) {
            $this->redirectTo('/student/notes');
        }
    }

    /**
     * Refuser les comptes enseignants.
     *
     * Un enseignant ne voit que ce qui touche aux cours qu'il enseigne : ni la
     * liste complete des etudiants, ni les moyennes de toute l'universite, ni
     * les fiches de ses collegues. Ses notes se transmettent par « Remise des
     * notes », controlee cours par cours.
     *
     * Les niveaux 1 a 3 passent toujours : un registraire par ailleurs
     * enseignant garde ses droits de registraire.
     */
    protected function refuserEnseignant($message = null) {
        $this->initSecurity();
        if ((int) ($_SESSION['user_level'] ?? 99) <= 3) {
            return;
        }
        /* Le responsable du Work Education n'a, lui aussi, rien a faire dans
           les listes du personnel : il retourne a sa page. */
        if (function_exists('estWorkEducation') && estWorkEducation()) {
            $this->redirectTo('/work-education');
        }
        $niveauEnseignant = defined('ROLE_TEACHER') ? (int) ROLE_TEACHER : 7;
        if ((int) ($_SESSION['user_level'] ?? 0) === $niveauEnseignant
            || (function_exists('isTeacher') && isTeacher())) {
            $this->forbidden($message ?: "Cette page ne concerne pas les cours que vous enseignez. "
                . "Vos notes se transmettent par « Remise des notes ».");
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
     * Exiger que l'utilisateur porte l'un des privileges cites.
     *
     * Complement de requireAnyLevel, pour les cas ou l'acces se decrit par des
     * ROLES et non par un rang. Les deux ne se recouvrent pas exactement : le
     * niveau 3 rassemble les six registraires ET un administrateur. Autoriser
     * « superadmin et registraire » par requireAnyLevel([1, 3]) ouvrirait donc
     * la page a un compte que personne n'a nomme.
     *
     * Le privilege est la colonne la plus sure du modele : chaque compte en a
     * un, et il porte le nom du role. `user_type`, qui semblait faire le meme
     * travail, est vide sur quatre comptes.
     *
     * Middleware::hasPrivilege examine les quatre colonnes de privilege : un
     * compte dont le role principal est autre mais qui porte le privilege en
     * second passe donc, ce qu'un test de niveau ne saurait pas voir.
     *
     * @param string[] $privileges Roles admis, tels qu'ecrits en base.
     */
    protected function requireAnyPrivileges(array $privileges, $message = null) {
        $this->initSecurity();

        $autorise = false;

        if (class_exists('Middleware')) {
            foreach ($privileges as $privilege) {
                if (Middleware::hasPrivilege($privilege)) { $autorise = true; break; }
            }
        }

        /* Repli sur la session quand le middleware n'a pas de connexion : le
           chemin controleur ne l'initialise pas toujours, et echouer ferme
           serait pire qu'un test plus grossier — un registraire legitime se
           verrait refuser sa propre page. */
        if (!$autorise && isset($_SESSION['user_privilege'])) {
            $autorise = in_array((string)$_SESSION['user_privilege'], $privileges, true);
        }

        /* Un superadmin passe partout : c'est le sens de ce role, et l'omettre
           d'une liste par distraction l'enfermerait dehors de sa propre
           administration. */
        if (!$autorise && (int)($_SESSION['user_level'] ?? 99) === 1) {
            $autorise = true;
        }

        if (!$autorise) {
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
