<?php
/**
 * =============================================================================
 * MIDDLEWARE DE SÉCURITÉ - INFINIT REGISTRAR
 * =============================================================================
 * 
 * Ce fichier centralise toutes les fonctions de sécurité :
 * - Authentification et gestion des sessions
 * - Contrôle des privilèges (RBAC)
 * - Protection CSRF
 * - Sécurisation des cookies
 * 
 * @author Infinit Registrar
 * @version 1.0.0
 * @date 2026-01-19
 */

// Empêcher l'accès direct
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

/**
 * =============================================================================
 * CONFIGURATION
 * =============================================================================
 */

// Niveaux de privilèges (du plus élevé au plus bas)
define('ROLE_ADMINISTRATOR', 1);
define('ROLE_REGISTRAR', 2);
define('ROLE_USER', 3);
define('ROLE_VISITOR', 4);

// Durée de vie du cookie "Se souvenir de moi" (20 jours)
define('REMEMBER_ME_DURATION', 20 * 24 * 60 * 60);

// Durée de vie du token CSRF (1 heure)
define('CSRF_TOKEN_LIFETIME', 3600);

/**
 * =============================================================================
 * CLASSE MIDDLEWARE
 * =============================================================================
 */

class Middleware {
    
    private static $dtb = null;
    private static $currentUser = null;
    
    /**
     * Initialise le middleware avec la connexion à la base de données
     */
    public static function init($database) {
        self::$dtb = $database;
        self::startSecureSession();
    }
    
    /**
     * Démarre une session sécurisée
     */
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des sessions
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            
            // En production avec HTTPS, activer :
            // ini_set('session.cookie_secure', 1);
            // ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Régénérer l'ID de session périodiquement pour éviter le fixation
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * ==========================================================================
     * AUTHENTIFICATION
     * ==========================================================================
     */
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Récupère l'utilisateur courant depuis la session ou les cookies
     */
    public static function getCurrentUser() {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        
        // Vérifier la session
        if (self::isAuthenticated()) {
            self::$currentUser = self::getUserById($_SESSION['user_id']);
            return self::$currentUser;
        }
        
        // Vérifier le cookie "Se souvenir de moi"
        if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
            $user = self::verifyRememberToken($_COOKIE['remember_token']);
            if ($user) {
                self::loginUser($user, false);
                self::$currentUser = $user;
                return self::$currentUser;
            }
        }
        
        return null;
    }
    
    /**
     * Récupère un utilisateur par son ID (avec requête préparée)
     */
    private static function getUserById($userId) {
        if (self::$dtb === null) return null;
        
        $stmt = self::$dtb->prepare("SELECT * FROM compt_utilisateur WHERE id = :id AND etat = 1 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Authentifie un utilisateur avec pseudo et mot de passe
     */
    public static function authenticate($pseudo, $password, $remember = false) {
        if (self::$dtb === null) return false;
        
        // Hash du mot de passe avec le salt
        $salt = 'fixing_password';
        $hashedPassword = hash('sha256', $password . $salt);
        
        // Requête préparée pour éviter les injections SQL
        $stmt = self::$dtb->prepare(
            "SELECT * FROM compt_utilisateur 
             WHERE pseudo = :pseudo AND password = :password AND etat = 1 
             LIMIT 1"
        );
        $stmt->execute([
            'pseudo' => $pseudo,
            'password' => $hashedPassword
        ]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            self::loginUser($user, $remember);
            return true;
        }
        
        return false;
    }
    
    /**
     * Connecte un utilisateur et crée la session
     */
    private static function loginUser($user, $remember = false) {
        // Régénérer l'ID de session pour éviter le fixation
        session_regenerate_id(true);
        
        // Stocker les informations en session (PAS le mot de passe !)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_pseudo'] = $user['pseudo'];
        $_SESSION['user_level'] = $user['level'];
        $_SESSION['user_privilege'] = $user['privilege'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['login_time'] = time();
        
        // Compatibilité avec l'ancien système
        $_SESSION['infinit_pseudo'] = $user['pseudo'];
        $_SESSION['infinit_password'] = $user['password'];
        
        // Gérer le cookie "Se souvenir de moi"
        if ($remember) {
            self::createRememberToken($user['id']);
        }
        
        self::$currentUser = $user;
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public static function logout() {
        // Supprimer le token de mémorisation
        if (isset($_COOKIE['remember_token'])) {
            self::deleteRememberToken($_COOKIE['remember_token']);
            self::deleteCookie('remember_token');
        }
        
        // Supprimer les anciens cookies de compatibilité
        self::deleteCookie('infinit_pseudo');
        self::deleteCookie('infinit_password');
        
        // Détruire la session
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        self::$currentUser = null;
    }
    
    /**
     * ==========================================================================
     * TOKENS "SE SOUVENIR DE MOI"
     * ==========================================================================
     */
    
    /**
     * Crée un token de mémorisation sécurisé
     */
    private static function createRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', time() + REMEMBER_ME_DURATION);
        
        // Stocker le token hashé en base de données
        // Note: Vous devrez créer une table 'remember_tokens' si elle n'existe pas
        try {
            $stmt = self::$dtb->prepare(
                "INSERT INTO remember_tokens (user_id, token_hash, expires_at) 
                 VALUES (:user_id, :token_hash, :expires_at)
                 ON DUPLICATE KEY UPDATE token_hash = :token_hash, expires_at = :expires_at"
            );
            $stmt->execute([
                'user_id' => $userId,
                'token_hash' => $hashedToken,
                'expires_at' => $expiry
            ]);
        } catch (PDOException $e) {
            // Table n'existe peut-être pas, utiliser l'ancienne méthode
            self::setSecureCookie('infinit_pseudo', $_SESSION['user_pseudo'], REMEMBER_ME_DURATION);
        }
        
        // Stocker le token non-hashé dans le cookie
        self::setSecureCookie('remember_token', $token, REMEMBER_ME_DURATION);
    }
    
    /**
     * Vérifie un token de mémorisation
     */
    private static function verifyRememberToken($token) {
        if (self::$dtb === null) return null;
        
        $hashedToken = hash('sha256', $token);
        
        try {
            $stmt = self::$dtb->prepare(
                "SELECT u.* FROM compt_utilisateur u
                 INNER JOIN remember_tokens rt ON u.id = rt.user_id
                 WHERE rt.token_hash = :token_hash 
                 AND rt.expires_at > NOW()
                 AND u.etat = 1
                 LIMIT 1"
            );
            $stmt->execute(['token_hash' => $hashedToken]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback sur l'ancienne méthode
            return self::legacyVerifyRemember();
        }
    }
    
    /**
     * Méthode de compatibilité avec l'ancien système de cookies
     */
    private static function legacyVerifyRemember() {
        if (isset($_COOKIE['infinit_pseudo']) && isset($_COOKIE['infinit_password'])) {
            $stmt = self::$dtb->prepare(
                "SELECT * FROM compt_utilisateur 
                 WHERE pseudo = :pseudo AND password = :password AND etat = 1 
                 LIMIT 1"
            );
            $stmt->execute([
                'pseudo' => $_COOKIE['infinit_pseudo'],
                'password' => $_COOKIE['infinit_password']
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
    
    /**
     * Supprime un token de mémorisation
     */
    private static function deleteRememberToken($token) {
        if (self::$dtb === null) return;
        
        $hashedToken = hash('sha256', $token);
        
        try {
            $stmt = self::$dtb->prepare("DELETE FROM remember_tokens WHERE token_hash = :token_hash");
            $stmt->execute(['token_hash' => $hashedToken]);
        } catch (PDOException $e) {
            // Ignorer si la table n'existe pas
        }
    }
    
    /**
     * ==========================================================================
     * CONTRÔLE D'ACCÈS / PRIVILÈGES
     * ==========================================================================
     */
    
    /**
     * Vérifie si l'utilisateur a le niveau requis
     * Plus le niveau est BAS, plus les privilèges sont ÉLEVÉS
     */
    public static function hasMinLevel($requiredLevel) {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        return (int)$user['level'] <= $requiredLevel;
    }
    
    /**
     * Vérifie si l'utilisateur a un privilège spécifique
     */
    public static function hasPrivilege($privilege) {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        // Vérifier les 4 colonnes de privilèges
        return $user['privilege'] === $privilege ||
               ($user['privilege_2'] ?? '') === $privilege ||
               ($user['privilege_3'] ?? '') === $privilege ||
               ($user['privilege_4'] ?? '') === $privilege;
    }
    
    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public static function isAdmin() {
        return self::hasPrivilege('administrator');
    }
    
    /**
     * Vérifie si l'utilisateur est registrar ou supérieur
     */
    public static function isRegistrar() {
        return self::hasPrivilege('administrator') || self::hasPrivilege('registrar');
    }
    
    /**
     * Exige un niveau minimum, sinon redirige
     */
    public static function requireLevel($requiredLevel, $redirectUrl = null) {
        if (!self::hasMinLevel($requiredLevel)) {
            if ($redirectUrl) {
                header("Location: $redirectUrl");
            } else {
                http_response_code(403);
                die('<h1>403 - Accès Refusé</h1><p>Vous n\'avez pas les privilèges nécessaires pour accéder à cette page.</p>');
            }
            exit;
        }
    }
    
    /**
     * Exige une authentification, sinon redirige vers login
     */
    public static function requireAuth($redirectUrl = './index.php') {
        if (!self::isAuthenticated()) {
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    /**
     * ==========================================================================
     * PROTECTION CSRF
     * ==========================================================================
     */
    
    /**
     * Génère ou récupère un token CSRF
     */
    public static function getCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Génère un champ input hidden pour le token CSRF
     */
    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . self::getCSRFToken() . '">';
    }
    
    /**
     * Vérifie la validité du token CSRF
     */
    public static function verifyCSRFToken($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        }
        
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Comparaison sécurisée timing-safe
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Exige un token CSRF valide, sinon erreur
     */
    public static function requireCSRF() {
        if (!self::verifyCSRFToken()) {
            http_response_code(403);
            die('<h1>403 - Token CSRF Invalide</h1><p>Votre session a expiré ou la requête est invalide. Veuillez rafraîchir la page et réessayer.</p>');
        }
    }
    
    /**
     * ==========================================================================
     * COOKIES SÉCURISÉS
     * ==========================================================================
     */
    
    /**
     * Définit un cookie sécurisé
     */
    public static function setSecureCookie($name, $value, $duration = 0) {
        $options = [
            'expires' => $duration > 0 ? time() + $duration : 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'  // Ou 'Strict' pour plus de sécurité
        ];
        
        setcookie($name, $value, $options);
    }
    
    /**
     * Supprime un cookie
     */
    public static function deleteCookie($name) {
        setcookie($name, '', time() - 3600, '/');
    }
    
    /**
     * ==========================================================================
     * UTILITAIRES
     * ==========================================================================
     */
    
    /**
     * Échappe une valeur pour l'affichage HTML (protection XSS)
     */
    public static function escape($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Alias court pour escape
     */
    public static function e($value) {
        return self::escape($value);
    }
    
    /**
     * Récupère l'adresse IP du client
     */
    public static function getClientIP() {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        return '0.0.0.0';
    }
    
    /**
     * Log une action de sécurité (dans la base de données)
     */
    public static function logSecurityEvent($event, $details = []) {
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $userId = $_SESSION['user_id'] ?? null;
        
        // Log dans la base de données si disponible
        if (self::$dtb !== null) {
            try {
                $stmt = self::$dtb->prepare(
                    "INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, details) 
                     VALUES (:event_type, :user_id, :ip_address, :user_agent, :details)"
                );
                $stmt->execute([
                    'event_type' => $event,
                    'user_id' => $userId,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'details' => json_encode($details)
                ]);
                return true;
            } catch (PDOException $e) {
                // Si la table n'existe pas, fallback sur le fichier
            }
        }
        
        // Fallback: log dans un fichier
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $userId,
            'ip' => $ip,
            'details' => $details
        ];
        
        $logFile = APP_ROOT . '/logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
        return false;
    }
}

/**
 * =============================================================================
 * FONCTIONS HELPER GLOBALES
 * =============================================================================
 */

/**
 * Initialise le middleware (à appeler après la connexion DB)
 */
function initMiddleware($dtb) {
    Middleware::init($dtb);
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return Middleware::isAuthenticated();
}

/**
 * Récupère l'utilisateur courant
 */
function currentUser() {
    return Middleware::getCurrentUser();
}

/**
 * Exige une authentification
 */
function requireAuth($redirect = './index.php') {
    Middleware::requireAuth($redirect);
}

/**
 * Exige un niveau minimum
 */
function requireLevel($level, $redirect = null) {
    Middleware::requireLevel($level, $redirect);
}

/**
 * Génère le champ CSRF
 */
function csrf_field() {
    return Middleware::csrfField();
}

/**
 * Récupère le token CSRF
 */
function csrf_token() {
    return Middleware::getCSRFToken();
}

/**
 * Vérifie le token CSRF
 */
function verify_csrf($token = null) {
    return Middleware::verifyCSRFToken($token);
}

/**
 * Exige un token CSRF valide
 */
function require_csrf() {
    Middleware::requireCSRF();
}

/**
 * Échappe une valeur HTML
 */
function e($value) {
    return Middleware::escape($value);
}

/**
 * Vérifie si l'utilisateur a le niveau requis
 */
function hasLevel($level) {
    return Middleware::hasMinLevel($level);
}

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin() {
    return Middleware::isAdmin();
}

/**
 * Vérifie si l'utilisateur est registrar ou admin
 */
function isRegistrar() {
    return Middleware::isRegistrar();
}
