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

// Protection contre les inclusions multiples
if (defined('MIDDLEWARE_LOADED')) {
    return;
}
define('MIDDLEWARE_LOADED', true);

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
if (!defined('ROLE_SUPERADMIN')) define('ROLE_SUPERADMIN', 1);
if (!defined('ROLE_ADMINISTRATOR')) define('ROLE_ADMINISTRATOR', 2);
if (!defined('ROLE_REGISTRAR')) define('ROLE_REGISTRAR', 3);
if (!defined('ROLE_COMPTABILITE')) define('ROLE_COMPTABILITE', 4);
if (!defined('ROLE_MEDIA')) define('ROLE_MEDIA', 5);
if (!defined('ROLE_CHEF_MENTION')) define('ROLE_CHEF_MENTION', 6);
if (!defined('ROLE_TEACHER')) define('ROLE_TEACHER', 7);
if (!defined('ROLE_STUDENT')) define('ROLE_STUDENT', 8);

// Mapping des niveaux vers les noms de privilèges
if (!defined('ROLE_LABELS')) {
    define('ROLE_LABELS', [
        1 => 'Superadmin',
        2 => 'Administrateur',
        3 => 'Registraire',
        4 => 'Comptabilité',
        5 => 'Média',
        6 => 'Chef de mention',
        7 => 'Professeur',
        8 => 'Étudiant'
    ]);
}

// Durée de vie du cookie "Se souvenir de moi" (20 jours)
if (!defined('REMEMBER_ME_DURATION')) define('REMEMBER_ME_DURATION', 20 * 24 * 60 * 60);

// Durée de vie du token CSRF (1 heure)
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', 3600);

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
     * Vérifie si l'utilisateur est superadmin
     */
    public static function isSuperAdmin() {
        return self::hasPrivilege('superadmin') || self::hasMinLevel(ROLE_SUPERADMIN);
    }

    /**
     * Vérifie si l'utilisateur est administrateur ou supérieur
     */
    public static function isAdmin() {
        return self::hasPrivilege('superadmin') || self::hasPrivilege('administrator') || self::hasMinLevel(ROLE_ADMINISTRATOR);
    }
    
    /**
     * Vérifie si l'utilisateur est registraire ou supérieur
     */
    public static function isRegistrar() {
        return self::isAdmin() || self::hasPrivilege('registrar') || self::hasMinLevel(ROLE_REGISTRAR);
    }

    /**
     * Vérifie si l'utilisateur est comptabilité
     */
    public static function isComptabilite() {
        return self::isAdmin() || self::hasPrivilege('comptabilite') || 
               (int)(self::getCurrentUser()['level'] ?? 0) === ROLE_COMPTABILITE;
    }

    /**
     * Vérifie si l'utilisateur est média
     */
    public static function isMedia() {
        return self::isAdmin() || self::hasPrivilege('media') || 
               (int)(self::getCurrentUser()['level'] ?? 0) === ROLE_MEDIA;
    }

    /**
     * Vérifie si l'utilisateur est chef de mention
     */
    public static function isChefMention() {
        return self::isAdmin() || self::hasPrivilege('chef_mention') || 
               (int)(self::getCurrentUser()['level'] ?? 0) === ROLE_CHEF_MENTION;
    }

    /**
     * ==========================================================================
     * RÔLES ÉTUDIANT ET PROFESSEUR
     * ==========================================================================
     */

    /**
     * Vérifie si l'utilisateur est un professeur
     */
    public static function isTeacher() {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        return ($user['user_type'] ?? '') === 'teacher' || 
               self::hasPrivilege('teacher') ||
               (int)($user['level'] ?? 0) === ROLE_TEACHER ||
               ($user['privilege'] ?? '') === 'teacher';
    }

    /**
     * Vérifie si l'utilisateur est un étudiant
     */
    public static function isStudent() {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        return ($user['user_type'] ?? '') === 'student' || 
               self::hasPrivilege('student') ||
               (int)($user['level'] ?? 0) === ROLE_STUDENT ||
               ($user['privilege'] ?? '') === 'student';
    }

    /**
     * Récupère le label du rôle par son niveau
     */
    public static function getRoleLabel($level) {
        $labels = ROLE_LABELS;
        return $labels[(int)$level] ?? 'Inconnu';
    }

    /**
     * Récupère l'ID du professeur lié à l'utilisateur courant
     */
    public static function getTeacherUid() {
        $user = self::getCurrentUser();
        if (!$user || !self::isTeacher()) return null;
        
        // 1. Vérifier si teacher_uid est directement dans compt_utilisateur
        if (!empty($user['teacher_uid'])) {
            return $user['teacher_uid'];
        }
        
        // 2. Fallback: chercher dans la table teacher par nom/prenom
        if (self::$dtb) {
            try {
                $nom = $user['nom'] ?? '';
                $prenom = $user['prenom'] ?? '';
                
                if (!empty($nom) && !empty($prenom)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT uid FROM teacher WHERE lastName = :nom AND name = :prenom LIMIT 1"
                    );
                    $stmt->execute(['nom' => $nom, 'prenom' => $prenom]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && !empty($result['uid'])) {
                        $_SESSION['cached_teacher_uid'] = $result['uid'];
                        return $result['uid'];
                    }
                }
            } catch (PDOException $e) {
                // Ignorer silencieusement
            }
        }
        
        return $_SESSION['cached_teacher_uid'] ?? null;
    }

    /**
     * Récupère l'ID étudiant lié à l'utilisateur courant
     */
    public static function getStudentId() {
        $user = self::getCurrentUser();
        if (!$user || !self::isStudent()) return null;
        
        // 0. Vérifier le cache en session (évite les requêtes répétées)
        if (!empty($_SESSION['cached_student_id'])) {
            return $_SESSION['cached_student_id'];
        }
        
        // 1. Vérifier si student_id est directement dans compt_utilisateur
        if (!empty($user['student_id'])) {
            $_SESSION['cached_student_id'] = $user['student_id'];
            return $user['student_id'];
        }
        
        // 2. Fallback: chercher dans tbl_2024_etudiant
        if (self::$dtb) {
            try {
                $nom = trim($user['nom'] ?? '');
                $prenom = trim($user['prenom'] ?? '');
                
                // 2a. Recherche exacte insensible à la casse
                if (!empty($nom) && !empty($prenom)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT student_id FROM tbl_2024_etudiant 
                         WHERE LOWER(TRIM(student_nom)) = LOWER(:nom) 
                         AND LOWER(TRIM(student_prenom)) = LOWER(:prenom) 
                         ORDER BY annee_scolaire DESC LIMIT 1"
                    );
                    $stmt->execute(['nom' => $nom, 'prenom' => $prenom]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && !empty($result['student_id'])) {
                        $_SESSION['cached_student_id'] = $result['student_id'];
                        return $result['student_id'];
                    }
                }
                
                // 2b. Recherche par pseudo = student_id
                $pseudo = trim($user['pseudo'] ?? '');
                if (!empty($pseudo)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT student_id FROM tbl_2024_etudiant 
                         WHERE student_id = :pseudo 
                         ORDER BY annee_scolaire DESC LIMIT 1"
                    );
                    $stmt->execute(['pseudo' => $pseudo]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && !empty($result['student_id'])) {
                        $_SESSION['cached_student_id'] = $result['student_id'];
                        return $result['student_id'];
                    }
                }
                
                // 2c. Recherche par email
                $mail = trim($user['mail'] ?? '');
                if (!empty($mail)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT student_id FROM tbl_2024_etudiant 
                         WHERE LOWER(TRIM(student_mail)) = LOWER(:mail) 
                         ORDER BY annee_scolaire DESC LIMIT 1"
                    );
                    $stmt->execute(['mail' => $mail]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && !empty($result['student_id'])) {
                        $_SESSION['cached_student_id'] = $result['student_id'];
                        return $result['student_id'];
                    }
                }
                
                // 2d. Recherche LIKE (dernière chance)
                if (!empty($nom) && !empty($prenom)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT student_id FROM tbl_2024_etudiant 
                         WHERE LOWER(TRIM(student_nom)) LIKE LOWER(:nom) 
                         AND LOWER(TRIM(student_prenom)) LIKE LOWER(:prenom) 
                         ORDER BY annee_scolaire DESC LIMIT 1"
                    );
                    $stmt->execute(['nom' => '%' . $nom . '%', 'prenom' => '%' . $prenom . '%']);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result && !empty($result['student_id'])) {
                        $_SESSION['cached_student_id'] = $result['student_id'];
                        return $result['student_id'];
                    }
                }
            } catch (PDOException $e) {
                // Ignorer silencieusement
            }
        }
        
        return null;
    }

    /**
     * Vérifie si le professeur a accès à un cours spécifique
     */
    public static function teacherCanAccessCourse($courseId) {
        if (self::isAdmin() || self::isRegistrar()) return true;
        if (!self::isTeacher()) return false;
        
        $teacherUid = self::getTeacherUid();
        if (!$teacherUid || !self::$dtb) return false;
        
        $stmt = self::$dtb->prepare(
            "SELECT COUNT(*) FROM t_2023_cours WHERE id = :course_id AND id_teacher = :teacher_uid"
        );
        $stmt->execute(['course_id' => $courseId, 'teacher_uid' => $teacherUid]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si le professeur a accès à un étudiant (inscrit à ses cours)
     */
    public static function teacherCanAccessStudent($studentId, $anneeScolaire = null) {
        if (self::isAdmin() || self::isRegistrar()) return true;
        if (!self::isTeacher()) return false;
        
        $teacherUid = self::getTeacherUid();
        if (!$teacherUid || !self::$dtb) return false;
        
        // Récupérer les étudiants via t_2023_notes (cours enseignés par le professeur)
        $query = "SELECT COUNT(DISTINCT n.student_id) 
                  FROM t_2023_notes n
                  INNER JOIN t_2023_cours c ON n.id_cours = c.id
                  WHERE c.id_teacher = :teacher_uid 
                  AND n.student_id = :student_id
                  AND n.ajout = 1";
        
        $params = ['teacher_uid' => $teacherUid, 'student_id' => $studentId];
        
        if ($anneeScolaire) {
            $query .= " AND n.annee_scolaire = :annee_scolaire";
            $params['annee_scolaire'] = $anneeScolaire;
        }
        
        $stmt = self::$dtb->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère la liste des cours enseignés par le professeur
     */
    public static function getTeacherCourses($anneeScolaire = null) {
        if (!self::isTeacher() || !self::$dtb) return [];
        
        $teacherUid = self::getTeacherUid();
        if (!$teacherUid) return [];
        
        $query = "SELECT * FROM t_2023_cours WHERE id_teacher = :teacher_uid AND remove != 1";
        $params = ['teacher_uid' => $teacherUid];
        
        if ($anneeScolaire) {
            $query .= " AND annee_scolaire = :annee_scolaire";
            $params['annee_scolaire'] = $anneeScolaire;
        }
        
        $query .= " ORDER BY title";
        
        $stmt = self::$dtb->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des étudiants inscrits aux cours du professeur
     */
    public static function getTeacherStudents($anneeScolaire = null) {
        if (!self::isTeacher() || !self::$dtb) return [];
        
        $teacherUid = self::getTeacherUid();
        if (!$teacherUid) return [];
        
        $query = "SELECT DISTINCT e.* 
                  FROM tbl_2024_etudiant e
                  INNER JOIN t_2023_notes n ON e.student_id = n.student_id
                  INNER JOIN t_2023_cours c ON n.id_cours = c.id
                  WHERE c.id_teacher = :teacher_uid
                  AND n.ajout = 1";
        
        $params = ['teacher_uid' => $teacherUid];
        
        if ($anneeScolaire) {
            $query .= " AND e.annee_scolaire = :annee_scolaire";
            $params['annee_scolaire'] = $anneeScolaire;
        }
        
        $query .= " ORDER BY e.student_nom, e.student_prenom";
        
        $stmt = self::$dtb->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si l'étudiant peut accéder à ses propres informations
     */
    public static function studentCanAccessOwnData($studentId) {
        if (self::isAdmin() || self::isRegistrar()) return true;
        if (!self::isStudent()) return false;
        
        return self::getStudentId() === $studentId;
    }

    /**
     * Récupère les informations de l'étudiant connecté
     */
    public static function getStudentInfo() {
        if (!self::isStudent() || !self::$dtb) return null;
        
        $studentId = self::getStudentId();
        
        // 1. Par student_id si trouvé
        if ($studentId) {
            $stmt = self::$dtb->prepare(
                "SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id ORDER BY annee_scolaire DESC LIMIT 1"
            );
            $stmt->execute(['student_id' => $studentId]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($info) return $info;
        }
        
        // 2. Fallback direct par nom/prenom de l'utilisateur connecté
        $user = self::getCurrentUser();
        if ($user) {
            try {
                $nom = trim($user['nom'] ?? '');
                $prenom = trim($user['prenom'] ?? '');
                
                if (!empty($nom) && !empty($prenom)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT * FROM tbl_2024_etudiant 
                         WHERE LOWER(TRIM(student_nom)) = LOWER(:nom) 
                         AND LOWER(TRIM(student_prenom)) = LOWER(:prenom) 
                         ORDER BY annee_scolaire DESC LIMIT 1"
                    );
                    $stmt->execute(['nom' => $nom, 'prenom' => $prenom]);
                    $info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($info) {
                        // Mettre en cache le student_id trouvé
                        $_SESSION['cached_student_id'] = $info['student_id'];
                        return $info;
                    }
                }
            } catch (PDOException $e) {
                // Ignorer
            }
        }
        
        return null;
    }

    /**
     * Récupère les informations de l'enseignant connecté
     */
    public static function getTeacherInfo() {
        if (!self::isTeacher() || !self::$dtb) return null;
        
        $teacherUid = self::getTeacherUid();
        if (!$teacherUid) return null;
        
        $stmt = self::$dtb->prepare(
            "SELECT * FROM teacher WHERE uid = :uid LIMIT 1"
        );
        $stmt->execute(['uid' => $teacherUid]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les cours de l'étudiant connecté
     */
    public static function getStudentCourses($anneeScolaire = null, $semestre = null) {
        if (!self::isStudent() || !self::$dtb) return [];
        
        $studentId = self::getStudentId();
        if (!$studentId) return [];
        
        $query = "SELECT n.Sigle, n.title_cours as title, n.credit as nb_crd, 
                         n.grade as note, n.grade as note_final,
                         n.annee_scolaire, n.semester, n.yearlevel,
                         c.id_teacher, c.dep_desc
                  FROM t_2023_notes n
                  LEFT JOIN t_2023_cours c ON n.id_cours = c.id
                  WHERE n.student_id = :student_id
                  AND n.ajout = 1";
        
        $params = ['student_id' => $studentId];
        
        if ($anneeScolaire) {
            $query .= " AND n.annee_scolaire = :annee_scolaire";
            $params['annee_scolaire'] = $anneeScolaire;
        }
        
        if ($semestre) {
            $query .= " AND n.semester = :semestre";
            $params['semestre'] = $semestre;
        }
        
        $query .= " ORDER BY n.title_cours";
        
        $stmt = self::$dtb->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les notes de l'étudiant connecté
     */
    public static function getStudentNotes($anneeScolaire = null) {
        if (!self::isStudent() || !self::$dtb) return [];
        
        $studentId = self::getStudentId();
        if (!$studentId) return [];
        
        $query = "SELECT n.Sigle, n.title_cours as title, n.credit as nb_crd, 
                         n.grade as note, n.grade as note_final,
                         n.annee_scolaire, n.semester, n.yearlevel
                  FROM t_2023_notes n
                  WHERE n.student_id = :student_id
                  AND n.ajout = 1";
        
        $params = ['student_id' => $studentId];
        
        if ($anneeScolaire) {
            $query .= " AND n.annee_scolaire = :annee_scolaire";
            $params['annee_scolaire'] = $anneeScolaire;
        }
        
        $query .= " ORDER BY n.annee_scolaire DESC, n.semester, n.title_cours";
        
        $stmt = self::$dtb->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exige que l'utilisateur soit professeur, sinon redirige
     */
    public static function requireTeacher($redirectUrl = null) {
        if (!self::isTeacher() && !self::isAdmin() && !self::isRegistrar()) {
            if ($redirectUrl) {
                header("Location: $redirectUrl");
            } else {
                http_response_code(403);
                die('<h1>403 - Accès Refusé</h1><p>Cette page est réservée aux enseignants.</p>');
            }
            exit;
        }
    }

    /**
     * Exige que l'utilisateur soit étudiant, sinon redirige
     */
    public static function requireStudent($redirectUrl = null) {
        if (!self::isStudent() && !self::isAdmin() && !self::isRegistrar()) {
            if ($redirectUrl) {
                header("Location: $redirectUrl");
            } else {
                http_response_code(403);
                die('<h1>403 - Accès Refusé</h1><p>Cette page est réservée aux étudiants.</p>');
            }
            exit;
        }
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

if (!function_exists('initMiddleware')) {
/**
 * Initialise le middleware (à appeler après la connexion DB)
 */
function initMiddleware($dtb) {
    Middleware::init($dtb);
}
}

if (!function_exists('isLoggedIn')) {
/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return Middleware::isAuthenticated();
}
}

if (!function_exists('currentUser')) {
/**
 * Récupère l'utilisateur courant
 */
function currentUser() {
    return Middleware::getCurrentUser();
}
}

if (!function_exists('requireAuth')) {
/**
 * Exige une authentification
 */
function requireAuth($redirect = './index.php') {
    Middleware::requireAuth($redirect);
}
}

if (!function_exists('requireLevel')) {
/**
 * Exige un niveau minimum
 */
function requireLevel($level, $redirect = null) {
    Middleware::requireLevel($level, $redirect);
}
}

if (!function_exists('csrf_field')) {
/**
 * Génère le champ CSRF
 */
function csrf_field() {
    return Middleware::csrfField();
}
}

if (!function_exists('csrf_token')) {
/**
 * Récupère le token CSRF
 */
function csrf_token() {
    return Middleware::getCSRFToken();
}
}

if (!function_exists('verify_csrf')) {
/**
 * Vérifie le token CSRF
 */
function verify_csrf($token = null) {
    return Middleware::verifyCSRFToken($token);
}
}

if (!function_exists('require_csrf')) {
/**
 * Exige un token CSRF valide
 */
function require_csrf() {
    Middleware::requireCSRF();
}
}

if (!function_exists('e')) {
/**
 * Échappe une valeur HTML
 */
function e($value) {
    return Middleware::escape($value);
}
}

if (!function_exists('hasLevel')) {
/**
 * Vérifie si l'utilisateur a le niveau requis
 */
function hasLevel($level) {
    return Middleware::hasMinLevel($level);
}
}

if (!function_exists('isSuperAdmin')) {
/**
 * Vérifie si l'utilisateur est superadmin
 */
function isSuperAdmin() {
    return Middleware::isSuperAdmin();
}
}

if (!function_exists('isAdmin')) {
/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin() {
    return Middleware::isAdmin();
}
}

if (!function_exists('isRegistrar')) {
/**
 * Vérifie si l'utilisateur est registrar ou admin
 */
function isRegistrar() {
    return Middleware::isRegistrar();
}
}

if (!function_exists('isComptabilite')) {
/**
 * Vérifie si l'utilisateur est comptabilité
 */
function isComptabilite() {
    return Middleware::isComptabilite();
}
}

if (!function_exists('isMedia')) {
/**
 * Vérifie si l'utilisateur est média
 */
function isMedia() {
    return Middleware::isMedia();
}
}

if (!function_exists('isChefMention')) {
/**
 * Vérifie si l'utilisateur est chef de mention
 */
function isChefMention() {
    return Middleware::isChefMention();
}
}

if (!function_exists('getRoleLabel')) {
/**
 * Récupère le label du rôle par son niveau
 */
function getRoleLabel($level) {
    return Middleware::getRoleLabel($level);
}
}

if (!function_exists('isTeacher')) {
/**
 * Vérifie si l'utilisateur est un professeur
 */
function isTeacher() {
    return Middleware::isTeacher();
}
}

if (!function_exists('isStudent')) {
/**
 * Vérifie si l'utilisateur est un étudiant
 */
function isStudent() {
    return Middleware::isStudent();
}
}

if (!function_exists('getTeacherUid')) {
/**
 * Récupère l'ID du professeur lié
 */
function getTeacherUid() {
    return Middleware::getTeacherUid();
}
}

if (!function_exists('getStudentId')) {
/**
 * Récupère l'ID de l'étudiant lié
 */
function getStudentId() {
    return Middleware::getStudentId();
}
}

if (!function_exists('teacherCanAccessCourse')) {
/**
 * Vérifie si le prof peut accéder à un cours
 */
function teacherCanAccessCourse($courseId) {
    return Middleware::teacherCanAccessCourse($courseId);
}
}

if (!function_exists('teacherCanAccessStudent')) {
/**
 * Vérifie si le prof peut accéder à un étudiant
 */
function teacherCanAccessStudent($studentId, $anneeScolaire = null) {
    return Middleware::teacherCanAccessStudent($studentId, $anneeScolaire);
}
}

if (!function_exists('studentCanAccessOwnData')) {
/**
 * Vérifie si l'étudiant accède à ses propres données
 */
function studentCanAccessOwnData($studentId) {
    return Middleware::studentCanAccessOwnData($studentId);
}
}

if (!function_exists('requireTeacher')) {
/**
 * Exige que l'utilisateur soit professeur
 */
function requireTeacher($redirect = null) {
    Middleware::requireTeacher($redirect);
}
}

if (!function_exists('requireStudent')) {
/**
 * Exige que l'utilisateur soit étudiant
 */
function requireStudent($redirect = null) {
    Middleware::requireStudent($redirect);
}
}

if (!function_exists('getTeacherCourses')) {
/**
 * Récupère les cours du professeur connecté
 */
function getTeacherCourses($anneeScolaire = null) {
    return Middleware::getTeacherCourses($anneeScolaire);
}
}

if (!function_exists('getTeacherStudents')) {
/**
 * Récupère les étudiants du professeur connecté
 */
function getTeacherStudents($anneeScolaire = null) {
    return Middleware::getTeacherStudents($anneeScolaire);
}
}

if (!function_exists('getStudentInfo')) {
/**
 * Récupère les infos de l'étudiant connecté
 */
function getStudentInfo() {
    return Middleware::getStudentInfo();
}
}

if (!function_exists('getStudentCourses')) {
/**
 * Récupère les cours de l'étudiant connecté
 */
function getStudentCourses($anneeScolaire = null, $semestre = null) {
    return Middleware::getStudentCourses($anneeScolaire, $semestre);
}
}

if (!function_exists('getStudentNotes')) {
/**
 * Récupère les notes de l'étudiant connecté
 */
function getStudentNotes($anneeScolaire = null) {
    return Middleware::getStudentNotes($anneeScolaire);
}
}
