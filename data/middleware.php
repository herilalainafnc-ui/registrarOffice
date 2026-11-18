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
/* Le responsable du Work Education : il attribue la tache de chaque etudiant
   (/work-education) et ne voit rien d'autre (init/head.php le ramene a sa page). */
if (!defined('ROLE_WORK_EDUCATION')) define('ROLE_WORK_EDUCATION', 9);

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
        8 => 'Étudiant',
        9 => 'Work Education'
    ]);
}

// Durée de vie de la session (10 heures)
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 10 * 60 * 60);

// Durée de vie du cookie "Se souvenir de moi" (20 jours)
if (!defined('REMEMBER_ME_DURATION')) define('REMEMBER_ME_DURATION', 20 * 24 * 60 * 60);

// Durée de vie du token CSRF (1 heure)
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', 3600);

/**
 * =============================================================================
 * CLASSE MIDDLEWARE
 * =============================================================================
 */

if (!class_exists('Middleware')) {
class Middleware {
    
    private static $dtb = null;
    private static $currentUser = null;

    /* Pourquoi la derniere connexion Google a echoue, pour que la page de
       connexion dise quoi faire au lieu d'un « adresse non reconnue » qui
       laisse l'enseignant sans recours. */
    private static $motifRefusGoogle = null;

    /** @return string|null 'enseignant_sans_compte', ou null si indetermine. */
    public static function motifRefusGoogle() {
        return self::$motifRefusGoogle;
    }

    /**
     * Initialise le middleware avec la connexion à la base de données
     */
    public static function init($database) {
        self::$dtb = $database;
        self::startSecureSession();
    }

    /**
     * Connexion pour retrouver le compte connecte quand init() n'a pas ete appele.
     *
     * Le chemin controleur (routes.php, BaseController::initSecurity) charge le
     * middleware sans connexion : hasLevel() n'y retrouvait aucun compte et
     * refusait tout le monde. Ainsi l'export Excel des etudiants
     * (/export/students/xlsx, garde exigerConnexion(3)) repondait 403
     * « privileges insuffisants » meme au superadmin (signale le 21/10/2026).
     *
     * On reprend la connexion de la classe DB si elle existe, sinon une connexion
     * a part, depuis data/config.php (que ce chemin ne charge pas non plus) :
     * charger data/backdb.php ici priverait de $dtb les pages qui le chargent
     * ensuite avec require_once.
     */
    private static function connexionDeRepli() {
        static $repli = null;
        if (class_exists('DB') && DB::getPdo() instanceof PDO) {
            return DB::getPdo();
        }
        if ($repli !== null) {
            return $repli;
        }
        if (!defined('DB_DSN')) {
            require_once __DIR__ . '/config.php';
        }
        if (!defined('DB_DSN')) {
            return null;
        }
        try {
            $repli = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
        } catch (PDOException $e) {
            return null;
        }
        return $repli;
    }
    
    /**
     * Démarre une session sécurisée
     */
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Durée de vie de la session : 10 heures
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            ini_set('session.cookie_lifetime', SESSION_LIFETIME);
            
            // Configuration sécurisée des sessions
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);

            /* SameSite=Lax : le cookie de session ne part plus avec un
               formulaire ou une image d'un autre site (Chrome le faisait deja
               par defaut, pas Firefox ni Safari) ; il suit toujours un lien
               ordinaire vers l'application. Strict couperait la session en
               arrivant depuis un courriel. Secure en HTTPS : le cookie ne
               circule jamais en clair. Voir aussi refuserRequeteCroisee(). */
            ini_set('session.cookie_samesite', 'Lax');
            if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
                ini_set('session.cookie_secure', 1);
            }
            
            session_start();
            
            // Régénérer l'ID de session périodiquement pour éviter le fixation
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
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
            try {
                self::$currentUser = self::getUserById($_SESSION['user_id']);
            } catch (PDOException $e) {
                self::$currentUser = null;
            }
            return self::$currentUser;
        }
        
        // Vérifier le cookie "Se souvenir de moi"
        if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
            try {
                $user = self::verifyRememberToken($_COOKIE['remember_token']);
            } catch (PDOException $e) {
                $user = null;
            }
            if ($user) {
                self::loginUser($user, false);
                self::$currentUser = $user;
                // Reconnexion silencieuse par le cookie : c'est une connexion à part entière.
                if (class_exists('Audit')) {
                    Audit::evenement('login_remember', ['pseudo' => $user['pseudo'] ?? null]);
                }
                return self::$currentUser;
            }
        }
        
        return null;
    }
    
    /**
     * Récupère un utilisateur par son ID (avec requête préparée)
     */
    private static function getUserById($userId) {
        $cnx = self::$dtb ?? self::connexionDeRepli();
        if ($cnx === null) return null;

        $stmt = $cnx->prepare("SELECT * FROM compt_utilisateur WHERE id = :id AND etat = 1 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Authentifie un utilisateur avec pseudo et mot de passe
     */
    public static function authenticate($pseudo, $password, $remember = false) {
        if (self::$dtb === null) return false;

        $identifiant = trim((string) $pseudo);
        if ($identifiant === '') return false;

        // Récupérer l'utilisateur par pseudo (jamais par mot de passe)
        $stmt = self::$dtb->prepare(
            "SELECT * FROM compt_utilisateur WHERE pseudo = :pseudo AND etat = 1 LIMIT 1"
        );
        $stmt->execute(['pseudo' => $identifiant]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /* Repli sur l'adresse professionnelle : personne n'a a retenir un
           identifiant distinct de son courriel, et le bouton Google n'est pas
           toujours joignable. Le pseudo garde la priorite, pour qu'ouvrir cette
           voie ne change rien aux connexions existantes.

           Une adresse partagee par plusieurs comptes actifs est refusee plutot
           que tranchee au hasard : « acadsecret.ungrad@zurcher.edu.mg » sert a
           deux comptes, et un LIMIT 1 aurait connecte l'un ou l'autre selon
           l'ordre de la table. Ces comptes-la se connectent par leur pseudo. */
        if (!$user && strpos($identifiant, '@') !== false) {
            $stmt = self::$dtb->prepare(
                "SELECT * FROM compt_utilisateur
                 WHERE LOWER(TRIM(mail)) = LOWER(:mail) AND etat = 1
                 LIMIT 2"
            );
            $stmt->execute(['mail' => $identifiant]);
            $correspondances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($correspondances) === 1) {
                $user = $correspondances[0];
            } elseif (count($correspondances) > 1) {
                self::logSecurityEvent('login_email_ambigu', ['mail' => $identifiant]);
                return false;
            }
        }

        if (!$user) return false;

        $stored = (string) $user['password'];
        $ok = false;

        // Nouveau format sécurisé : password_hash / password_verify (bcrypt)
        if ($stored !== '' && password_verify($password, $stored)) {
            $ok = true;
            if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                self::updatePasswordHash($user['id'], password_hash($password, PASSWORD_DEFAULT));
            }
        } else {
            // Ancien format (sha256 + sel statique) : on vérifie puis on MIGRE vers bcrypt
            $legacy = hash('sha256', $password . 'fixing_password');
            if (hash_equals($stored, $legacy)) {
                $ok = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                self::updatePasswordHash($user['id'], $newHash);
                $user['password'] = $newHash;
            }
        }

        if ($ok) {
            self::loginUser($user, $remember);
            return true;
        }
        return false;
    }

    /**
     * Met à jour le hash de mot de passe en base (migration transparente sha256 -> bcrypt).
     */
    private static function updatePasswordHash($id, $newHash) {
        try {
            $up = self::$dtb->prepare("UPDATE compt_utilisateur SET password = :p WHERE id = :id");
            $up->execute(['p' => $newHash, 'id' => $id]);
        } catch (\Throwable $e) { /* la connexion reste valide même si la migration échoue */ }
    }
    
    /**
     * Connecte un utilisateur et crée la session
     */
    private static function loginUser($user, $remember = false) {
        // Régénérer l'ID de session pour éviter le fixation
        session_regenerate_id(true);
        unset($_SESSION['espace_choisi'], $_SESSION['espaces_cache']);   // une connexion neuve redemande l'espace (voir espacesDeLaPersonne)
        
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
     * Le compte a-t-il l'espace enseignant (Mes cours, Remise des notes,
     * Présences) ? Les enseignants, et les chefs de mention : ce sont tous des
     * professeurs, et beaucoup ont des cours à eux.
     *
     * Distinct de isTeacher(), qui ferme aussi au compte ce qui ne touche pas
     * ses propres cours (liste complète des étudiants, classements). Le chef
     * de mention garde ses vues de chef ; il gagne seulement l'espace
     * enseignant pour ses cours. Les niveaux 1 à 3 n'en relèvent pas, même avec
     * un privilège de chef en plus : ils consultent déjà toutes les feuilles.
     */
    public static function aEspaceEnseignant() {
        if (self::isTeacher()) return true;
        $user = self::getCurrentUser();
        if (!$user || (int)($user['level'] ?? 0) <= 3) return false;

        return (int)($user['level'] ?? 0) === ROLE_CHEF_MENTION ||
               ($user['user_type'] ?? '') === 'chef_mention' ||
               self::hasPrivilege('chef_mention');
    }

    /**
     * Les deux espaces d'une même personne (demande du 10/11/2026 : « des users
     * comme superadmin, média, compta ou autre peuvent être aussi profs : si
     * leur adresse est dans les deux cases, une page choisit la plateforme
     * enseignant ou l'autre »).
     *
     * Une personne est « double » quand son compte du personnel (niveaux 1 à 5 et
     * Work Education) et une fiche enseignant active se retrouvent : le courriel
     * du compte est celui de la fiche (teacher.email), ou le compte est relié à la
     * fiche par teacher_uid. Le compte enseignant (niveau 7, teacher_uid = la
     * fiche) est son espace enseignant ; il est créé au premier passage
     * (basculerEspace). Les chefs de mention n'en relèvent pas : leur espace
     * enseignant est déjà dans leur compte (aEspaceEnseignant).
     *
     * @param array|null $user Le compte (par défaut le compte connecté), du personnel ou enseignant
     * @return array|null null, ou ['fiche' => teacher, 'personnel' => compte, 'enseignant' => compte|null,
     *                     'actuel' => 'personnel'|'enseignant']
     */
    public static function espacesDeLaPersonne($user = null) {
        if ($user === null) { $user = self::getCurrentUser(); }
        $cnx = self::$dtb ?? self::connexionDeRepli();
        if (!is_array($user) || $cnx === null) return null;

        $niveau = (int)($user['level'] ?? 0);
        $estEnseignant = $niveau === ROLE_TEACHER;
        if (!$estEnseignant && !in_array($niveau, [ROLE_SUPERADMIN, ROLE_ADMINISTRATOR, ROLE_REGISTRAR, ROLE_COMPTABILITE, ROLE_MEDIA, ROLE_WORK_EDUCATION], true)) {
            return null;
        }

        try {
            /* La fiche : le lien explicite, sinon le courriel du compte du personnel. */
            $fiche = null;
            $uid = (int)($user['teacher_uid'] ?? 0);
            if ($uid > 0) {
                $st = $cnx->prepare("SELECT uid, name, lastName, position, email FROM teacher WHERE uid = :u AND remove = 0 LIMIT 1");
                $st->execute(['u' => $uid]);
                $fiche = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $courriels = self::googleEmailCandidates((string)($user['mail'] ?? ''));
            if (!$fiche && !$estEnseignant && $courriels) {
                $marques = implode(', ', array_map(fn ($i) => ':m' . $i, array_keys($courriels)));
                $st = $cnx->prepare("SELECT uid, name, lastName, position, email FROM teacher WHERE remove = 0 AND LOWER(TRIM(email)) IN ($marques) LIMIT 1");
                foreach ($courriels as $i => $c) { $st->bindValue(':m' . $i, $c); }
                $st->execute();
                $fiche = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$fiche) return null;

            /* Le compte enseignant de la fiche (peut ne pas exister encore). */
            $st = $cnx->prepare("SELECT * FROM compt_utilisateur WHERE teacher_uid = :u AND level = " . ROLE_TEACHER . " AND etat = 1 ORDER BY id LIMIT 1");
            $st->execute(['u' => $fiche['uid']]);
            $enseignant = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            /* Le compte du personnel : celui-ci, ou, depuis l'espace enseignant, celui qui désigne la même fiche. */
            $personnel = $estEnseignant ? null : $user;
            if ($estEnseignant) {
                $ficheMail = self::googleEmailCandidates((string)($fiche['email'] ?? ''));
                $filtre = "teacher_uid = :u";
                $params = ['u' => $fiche['uid']];
                foreach ($ficheMail as $i => $c) { $filtre .= " OR LOWER(TRIM(mail)) = :c$i"; $params["c$i"] = $c; }
                $st = $cnx->prepare("SELECT * FROM compt_utilisateur WHERE etat = 1 AND level IN (1, 2, 3, 4, 5, " . ROLE_WORK_EDUCATION . ") AND ($filtre) ORDER BY level, id LIMIT 1");
                $st->execute($params);
                $personnel = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$personnel) return null;

            return ['fiche' => $fiche, 'personnel' => $personnel, 'enseignant' => $estEnseignant ? $user : $enseignant,
                    'actuel' => $estEnseignant ? 'enseignant' : 'personnel'];
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Le compte connecté a-t-il deux espaces ? Mémorisé dix minutes en session :
     * le menu du compte le demande à chaque page.
     */
    public static function aDeuxEspaces() {
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id <= 0) return false;
        $c = $_SESSION['espaces_cache'] ?? null;
        if (is_array($c) && ($c['id'] ?? 0) === $id && time() - (int)($c['t'] ?? 0) < 600) {
            return (bool)$c['oui'];
        }
        $oui = self::espacesDeLaPersonne() !== null;
        $_SESSION['espaces_cache'] = ['id' => $id, 'oui' => $oui, 't' => time()];
        return $oui;
    }

    /**
     * Passe la session d'un espace à l'autre, sans mot de passe : la personne
     * est déjà authentifiée, et la bascule n'est possible qu'entre les deux
     * comptes que espacesDeLaPersonne() relie. Le compte enseignant est créé au
     * premier passage.
     *
     * @param string $espace 'personnel' ou 'enseignant'
     * @return array|false Le compte devenu courant
     */
    public static function basculerEspace($espace) {
        $espaces = self::espacesDeLaPersonne();
        if (!$espaces) return false;

        if ($espace === 'personnel') {
            $cible = $espaces['personnel'];
        } elseif ($espace === 'enseignant') {
            $cible = $espaces['enseignant'] ?: self::creerCompteEnseignantDuPersonnel($espaces['fiche'], $espaces['personnel']);
        } else {
            return false;
        }
        if (!$cible) return false;

        if ((int)$cible['id'] !== (int)($_SESSION['user_id'] ?? 0)) {
            $deId = (int)($_SESSION['user_id'] ?? 0);
            self::loginUser($cible, false);
            self::logSecurityEvent('espace_bascule', ['de' => $deId, 'vers' => (int)$cible['id'], 'espace' => $espace]);
        }
        $_SESSION['espace_choisi'] = $espace;
        unset($_SESSION['espaces_cache'], $_SESSION['login_redirect']);   // la page d'arrivée suit l'espace choisi
        return $cible;
    }

    /**
     * Crée le compte enseignant d'une personne du personnel. Sans courriel :
     * l'adresse reste celle du compte du personnel, que la connexion Google
     * retrouve ; deux comptes de même adresse rendraient son choix incertain. Mot
     * de passe aléatoire, jamais communiqué : on y entre par le choix de l'espace.
     */
    private static function creerCompteEnseignantDuPersonnel(array $fiche, array $personnel) {
        $cnx = self::$dtb ?? self::connexionDeRepli();
        if ($cnx === null || empty($fiche['uid'])) return false;
        try {
            $base = preg_replace('/[^a-z0-9._-]/i', '', explode('@', (string)($personnel['mail'] ?? ''))[0]) ?: ('enseignant' . $fiche['uid']);
            $pseudo = $base . '-ens';
            $st = $cnx->prepare('SELECT COUNT(*) FROM compt_utilisateur WHERE pseudo = :p');
            $st->execute(['p' => $pseudo]);
            if ((int)$st->fetchColumn() > 0) { $pseudo .= '-' . $fiche['uid']; }
            $poste = trim((string)($fiche['position'] ?? ''));

            $st = $cnx->prepare(
                "INSERT INTO compt_utilisateur
                 (pseudo, password, nom, prenom, post, mail, level, privilege, user_type, teacher_uid, etat, photos, theme, update_user, date_entry)
                 VALUES (:pseudo, :password, :nom, :prenom, :post, '', " . ROLE_TEACHER . ", 'teacher', 'teacher', :uid, 1, '', '', 0, CURDATE())"
            );
            $st->execute([
                'pseudo'   => $pseudo,
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'nom'      => trim((string)($fiche['name'] ?? '')),
                'prenom'   => trim((string)($fiche['lastName'] ?? '')),
                'post'     => $poste !== '' ? $poste : 'Enseignant',
                'uid'      => (int)$fiche['uid'],
            ]);
            $id = $cnx->lastInsertId();
            if (!$id) return false;
            self::logSecurityEvent('espace_enseignant_cree', ['user_id' => (int)$id, 'teacher_uid' => (int)$fiche['uid'], 'compte_personnel' => (int)$personnel['id']]);
            $st = $cnx->prepare('SELECT * FROM compt_utilisateur WHERE id = :id LIMIT 1');
            $st->execute(['id' => $id]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: false;
        } catch (PDOException $e) {
            self::logSecurityEvent('espace_enseignant_erreur', ['teacher_uid' => $fiche['uid'] ?? null, 'erreur' => $e->getMessage()]);
            return false;
        }
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
        if (!$user || !self::aEspaceEnseignant()) return null;
        
        // 1. Vérifier si teacher_uid est directement dans compt_utilisateur
        if (!empty($user['teacher_uid'])) {
            return $user['teacher_uid'];
        }
        
        /* 2. Repli : retrouver la fiche par le nom, puis par le courriel.

           Les deux tables ne rangent pas les noms pareil. compt_utilisateur
           met le nom de famille dans `nom` et les prenoms dans `prenom` ;
           la table teacher fait l'inverse sur une bonne part de ses fiches
           (`name` = nom de famille, `lastName` = prenoms). Ne tester qu'une
           seule combinaison laissait sans cours des enseignants pourtant
           presents dans les deux tables : l'espace enseignant s'ouvrait vide,
           sans rien dire de la cause.

           Ce repli ne dispense pas de renseigner teacher_uid, qui reste le
           seul lien sur. Il evite seulement qu'un oubli ferme tout l'espace. */
        if (self::$dtb) {
            try {
                $nom    = trim($user['nom'] ?? '');
                $prenom = trim($user['prenom'] ?? '');

                if ($nom !== '' && $prenom !== '') {
                    $stmt = self::$dtb->prepare(
                        "SELECT uid FROM teacher
                         WHERE remove = 0
                           AND (   (TRIM(lastName) = :nom1 AND TRIM(name) = :prenom1)
                                OR (TRIM(name) = :nom2 AND TRIM(lastName) = :prenom2))
                         LIMIT 1"
                    );
                    $stmt->execute([
                        'nom1' => $nom, 'prenom1' => $prenom,
                        'nom2' => $nom, 'prenom2' => $prenom,
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result && !empty($result['uid'])) {
                        $_SESSION['cached_teacher_uid'] = $result['uid'];
                        return $result['uid'];
                    }
                }

                // Le courriel professionnel est unique en pratique, et souvent
                // le seul point commun quand les noms ont ete saisis autrement.
                $mail = trim($user['mail'] ?? '');
                if ($mail !== '') {
                    $stmt = self::$dtb->prepare(
                        "SELECT uid FROM teacher
                         WHERE remove = 0 AND LOWER(TRIM(email)) = LOWER(:mail) LIMIT 1"
                    );
                    $stmt->execute(['mail' => $mail]);
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
        if (!self::aEspaceEnseignant() || !self::$dtb) return [];
        
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
        if (!self::aEspaceEnseignant() || !self::$dtb) return [];
        
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
            try {
                $stmt = self::$dtb->prepare(
                    "SELECT * FROM tbl_2024_etudiant WHERE student_id = :student_id ORDER BY remove ASC, annee_scolaire DESC LIMIT 1"
                );
                $stmt->execute(['student_id' => $studentId]);
                $info = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($info) return $info;
            } catch (PDOException $e) {
                // Ignorer et tenter les fallbacks ci-dessous
            }
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
        if (!self::aEspaceEnseignant() || !self::$dtb) return null;
        
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
        // Journal d'activité (data/audit.php) : connexions, échecs, déconnexions…
        if (class_exists('Audit')) {
            Audit::evenement((string) $event, is_array($details) ? $details : ['details' => $details]);
        }

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

    /**
     * ==========================================================================
     * GÉOLOCALISATION DES CONNEXIONS
     * ==========================================================================
     */

    /**
     * Enregistre la localisation GPS lors d'une connexion
     * 
     * @param float|null $latitude Latitude GPS
     * @param float|null $longitude Longitude GPS
     * @param float|null $accuracy Précision en mètres
     * @param string $loginMethod Méthode de connexion ('password' ou 'google')
     * @param bool $gpsDenied L'utilisateur a refusé la géolocalisation
     * @return bool
     */
    public static function saveLoginLocation($latitude = null, $longitude = null, $accuracy = null, $loginMethod = 'password', $gpsDenied = false) {
        if (self::$dtb === null) return false;
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return false;
        
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $city = null;
        $country = null;
        
        // Valider les coordonnées
        if ($latitude !== null) {
            $latitude = filter_var($latitude, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $latitude < -90 || $latitude > 90) $latitude = null;
        }
        if ($longitude !== null) {
            $longitude = filter_var($longitude, FILTER_VALIDATE_FLOAT);
            if ($longitude === false || $longitude < -180 || $longitude > 180) $longitude = null;
        }
        if ($accuracy !== null) {
            $accuracy = filter_var($accuracy, FILTER_VALIDATE_FLOAT);
            if ($accuracy === false || $accuracy < 0) $accuracy = null;
        }
        
        // Fallback serveur : si pas de coordonnées GPS, essayer la géolocalisation par IP
        if ($latitude === null || $longitude === null) {
            $geoData = self::geolocateByIP($ip);
            if ($geoData) {
                $latitude = $latitude ?? $geoData['lat'];
                $longitude = $longitude ?? $geoData['lon'];
                $accuracy = $accuracy ?? 5000; // ~5km précision IP
                $city = $geoData['city'] ?? null;
                $country = $geoData['country'] ?? null;
            }
        }
        
        try {
            // Créer la table si elle n'existe pas
            self::ensureLoginLocationsTable();
            
            $stmt = self::$dtb->prepare(
                "INSERT INTO login_locations (user_id, latitude, longitude, accuracy, ip_address, user_agent, login_method, gps_denied, city, country) 
                 VALUES (:user_id, :latitude, :longitude, :accuracy, :ip_address, :user_agent, :login_method, :gps_denied, :city, :country)"
            );
            $stmt->execute([
                'user_id' => $userId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'login_method' => $loginMethod,
                'gps_denied' => $gpsDenied ? 1 : 0,
                'city' => $city,
                'country' => $country
            ]);
            return true;
        } catch (PDOException $e) {
            // Log l'erreur silencieusement
            error_log("Erreur saveLoginLocation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Géolocalise une adresse IP via des APIs publiques gratuites
     * Retourne ['lat', 'lon', 'city', 'country'] ou null
     */
    private static function geolocateByIP($ip) {
        // Ne pas géolocaliser les IPs privées/locales
        if (self::isPrivateIP($ip)) {
            // Pour les IPs privées, utiliser l'IP publique du serveur
            $ip = ''; // ip-api.com retourne la position de l'IP publique si vide
        }
        
        // Essai 1: ip-api.com (gratuit, 45 req/min)
        try {
            $url = 'http://ip-api.com/json/' . $ip . '?fields=status,lat,lon,city,country,query';
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success' && isset($data['lat'], $data['lon'])) {
                    return [
                        'lat' => floatval($data['lat']),
                        'lon' => floatval($data['lon']),
                        'city' => $data['city'] ?? null,
                        'country' => $data['country'] ?? null
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silencieux
        }
        
        // Essai 2: ipwho.is (gratuit, illimité)
        try {
            $url = 'https://ipwho.is/' . $ip;
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && ($data['success'] ?? false) && isset($data['latitude'], $data['longitude'])) {
                    return [
                        'lat' => floatval($data['latitude']),
                        'lon' => floatval($data['longitude']),
                        'city' => $data['city'] ?? null,
                        'country' => $data['country'] ?? null
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silencieux
        }
        
        return null;
    }

    /**
     * Vérifie si une IP est privée/locale
     */
    private static function isPrivateIP($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            || $ip === '0.0.0.0' || $ip === '127.0.0.1' || $ip === '::1';
    }

    /**
     * Crée la table login_locations si elle n'existe pas
     */
    private static function ensureLoginLocationsTable() {
        try {
            self::$dtb->exec("
                CREATE TABLE IF NOT EXISTS `login_locations` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `user_id` INT(11) NOT NULL,
                    `latitude` DECIMAL(10, 8) DEFAULT NULL,
                    `longitude` DECIMAL(11, 8) DEFAULT NULL,
                    `accuracy` DECIMAL(10, 2) DEFAULT NULL,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `user_agent` TEXT DEFAULT NULL,
                    `login_method` VARCHAR(20) NOT NULL DEFAULT 'password',
                    `city` VARCHAR(100) DEFAULT NULL,
                    `country` VARCHAR(100) DEFAULT NULL,
                    `gps_denied` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_created` (`created_at`),
                    KEY `idx_location` (`latitude`, `longitude`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Table existe peut-être déjà
        }
    }

    /**
     * Récupère l'historique des localisations de connexion
     * 
     * @param int|null $userId Filtrer par utilisateur (null = tous)
     * @param int $limit Nombre maximum de résultats
     * @param string|null $dateFrom Date de début (Y-m-d)
     * @param string|null $dateTo Date de fin (Y-m-d)
     * @return array
     */
    public static function getLoginLocations($userId = null, $limit = 100, $dateFrom = null, $dateTo = null) {
        if (self::$dtb === null) return [];
        
        try {
            $sql = "SELECT ll.*, cu.pseudo, cu.nom, cu.prenom, cu.level, cu.privilege
                    FROM login_locations ll
                    LEFT JOIN compt_utilisateur cu ON ll.user_id = cu.id
                    WHERE 1=1";
            $params = [];
            
            if ($userId !== null) {
                $sql .= " AND ll.user_id = :user_id";
                $params['user_id'] = $userId;
            }
            
            if ($dateFrom !== null) {
                $sql .= " AND ll.created_at >= :date_from";
                $params['date_from'] = $dateFrom . ' 00:00:00';
            }
            
            if ($dateTo !== null) {
                $sql .= " AND ll.created_at <= :date_to";
                $params['date_to'] = $dateTo . ' 23:59:59';
            }
            
            $sql .= " ORDER BY ll.created_at DESC LIMIT " . intval($limit);
            
            $stmt = self::$dtb->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * ==========================================================================
     * AUTHENTIFICATION GOOGLE (Étudiants @zurcher.edu.mg)
     * ==========================================================================
     */

    /**
     * Authentifie un utilisateur via son token Google ID.
     * Vérifie que l'email est @zurcher.edu.mg, puis cherche le compte
     * dans compt_utilisateur (admin, prof, étudiant) ou tbl_2024_etudiant.
     *
     * @param string $idToken Le token JWT renvoyé par Google Identity Services
     * @return array|false Les infos utilisateur ou false
     */
    public static function authenticateWithGoogle($idToken) {
        self::$motifRefusGoogle = null;
        if (self::$dtb === null || empty($idToken)) return false;

        // 1. Vérifier le token auprès de Google
        $payload = self::verifyGoogleToken($idToken);
        if (!$payload) return false;

        $email = strtolower(trim($payload['email'] ?? ''));
        $emailCandidates = self::googleEmailCandidates($email);
        $canonicalEmail = self::canonicalGoogleEmail($email);
        $emailVerified = $payload['email_verified'] ?? false;

        // 2. Vérifier que l'email est vérifié et du domaine @zurcher.edu.mg
        if (!$emailVerified || !str_ends_with($email, '@zurcher.edu.mg')) {
            self::logSecurityEvent('google_login_rejected', [
                'email' => $email,
                'reason' => !$emailVerified ? 'email_not_verified' : 'invalid_domain'
            ]);
            return false;
        }

        // 3. Chercher DIRECTEMENT dans compt_utilisateur par email (admin, prof, étudiant)
        $user = null;
        $emailPlaceholders = [];
        $emailParams = [];
        foreach ($emailCandidates as $idx => $candidate) {
            $key = 'e' . $idx;
            $emailPlaceholders[] = ':' . $key;
            $emailParams[$key] = $candidate;
        }

        $stmt = self::$dtb->prepare(
            "SELECT * FROM compt_utilisateur 
             WHERE LOWER(TRIM(mail)) IN (" . implode(', ', $emailPlaceholders) . ")
             AND etat = 1
             LIMIT 1"
        );
        $stmt->execute($emailParams);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /* 4. Repli enseignant : retrouver la fiche du personnel enseignant par
              son courriel, puis le compte qui lui est rattache.

              Sans ce repli, un enseignant ne pouvait entrer par Google que si
              son adresse figurait aussi dans compt_utilisateur.mail. Or le
              courriel ne vit souvent que dans la table teacher. La recherche
              s'appuie donc sur celle-ci.

              Comme pour un etudiant, un enseignant dont la fiche porte cette
              adresse mais qui n'a pas encore de compte en recoit un a sa
              premiere connexion : il est relie a la fiche par teacher_uid,
              donc a ses cours, et son espace s'ouvre complet. */
        if (!$user) {
            $user = self::findTeacherUserByEmail($emailPlaceholders, $emailParams, $canonicalEmail, $payload);
        }

        // 5. Si toujours rien, chercher via la table étudiants
        if (!$user) {
            $student = null;

            // Chercher l'étudiant par email dans tbl_2024_etudiant
            $emailColumns = ['student_email', 'student_mail'];
            foreach ($emailColumns as $col) {
                try {
                    $stmt = self::$dtb->prepare(
                        "SELECT * FROM tbl_2024_etudiant 
                         WHERE LOWER(TRIM({$col})) IN (" . implode(', ', $emailPlaceholders) . ")
                         AND (remove IS NULL OR remove = 0)
                         ORDER BY annee_scolaire DESC LIMIT 1"
                    );
                    $stmt->execute($emailParams);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($student) break;
                } catch (PDOException $e) {
                    continue;
                }
            }

            if ($student) {
                $studentId = $student['student_id'] ?? '';

                // Chercher le compte utilisateur lié par student_id
                if (!empty($studentId)) {
                    $stmt = self::$dtb->prepare(
                        "SELECT * FROM compt_utilisateur 
                         WHERE student_id = :student_id AND etat = 1 LIMIT 1"
                    );
                    $stmt->execute(['student_id' => $studentId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                // Par nom/prénom de l'étudiant
                if (!$user && !empty($student['student_nom']) && !empty($student['student_prenom'])) {
                    $stmt = self::$dtb->prepare(
                        "SELECT * FROM compt_utilisateur 
                         WHERE LOWER(TRIM(nom)) = LOWER(:nom) 
                         AND LOWER(TRIM(prenom)) = LOWER(:prenom) 
                         AND (level = 8 OR privilege = 'student' OR user_type = 'student')
                         AND etat = 1 LIMIT 1"
                    );
                    $stmt->execute([
                        'nom' => trim($student['student_nom']),
                        'prenom' => trim($student['student_prenom'])
                    ]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                // Créer un compte automatiquement si l'étudiant existe mais pas de compte utilisateur
                if (!$user) {
                    $user = self::createStudentUserFromGoogle($student, $canonicalEmail, $payload);
                }
            }
        }

        // 6. Toujours pas trouvé → échec
        if (!$user) {
            self::logSecurityEvent('google_login_user_not_found', ['email' => $email]);
            return false;
        }

        // 7. Connecter l'utilisateur
        self::loginUser($user, false);
        $_SESSION['google_login'] = true;
        $_SESSION['google_email'] = $canonicalEmail;

        self::logSecurityEvent('google_login_success', [
            'email' => $email,
            'user_id' => $user['id'],
            'user_type' => $user['user_type'] ?? $user['privilege'] ?? 'unknown'
        ]);

        return $user;
    }

    /**
     * Retrouve le compte d'un enseignant à partir de son courriel professionnel.
     *
     * Deux tables, deux conventions : le courriel de l'enseignant vit dans
     * `teacher.email`, alors que la connexion cherche un `compt_utilisateur`.
     * On passe donc par la fiche pour atteindre le compte.
     *
     * Trois chemins, du plus sûr au plus approximatif :
     *   1. teacher_uid — le lien explicite ;
     *   2. le courriel recopié dans compt_utilisateur.mail ;
     *   3. le nom, testé dans les deux ordres, car `teacher` range souvent le
     *      nom de famille dans `name` et les prénoms dans `lastName`, l'inverse
     *      de compt_utilisateur (voir getTeacherUid).
     * Un compte enseignant trouvé par 2 ou 3 sans teacher_uid est relié à la
     * fiche au passage : l'adresse vérifiée par Google désigne cette fiche.
     *
     * Aucun compte : il est créé, relié à la fiche (createTeacherUserFromGoogle).
     *
     * @param string[] $emailPlaceholders Marqueurs SQL déjà construits (:e0, :e1…)
     * @param array    $emailParams       Valeurs correspondantes
     * @param string   $email             Adresse Google normalisée, pour le compte créé
     * @param array    $payload           Jeton Google (photo)
     * @return array|null Le compte actif, ou null
     */
    private static function findTeacherUserByEmail(array $emailPlaceholders, array $emailParams, string $email = '', array $payload = []) {
        /* Remis a zero ici et pas seulement chez l'appelant : la propriete est
           statique, et un motif laisse par une recherche precedente ferait
           afficher « vous etes enseignant » a une adresse inconnue. */
        self::$motifRefusGoogle = null;

        if (self::$dtb === null || !$emailPlaceholders) return null;

        try {
            $liste = implode(', ', $emailPlaceholders);

            $stmt = self::$dtb->prepare(
                "SELECT uid, name, lastName, position FROM teacher
                 WHERE remove = 0 AND LOWER(TRIM(email)) IN ({$liste})
                 LIMIT 1"
            );
            $stmt->execute($emailParams);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$teacher) return null;

            // 1. Le lien explicite.
            $stmt = self::$dtb->prepare(
                "SELECT * FROM compt_utilisateur
                 WHERE teacher_uid = :uid AND etat = 1 LIMIT 1"
            );
            $stmt->execute(['uid' => $teacher['uid']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) return $user;

            // 2. Le courriel, s'il a été recopié sur le compte.
            $stmt = self::$dtb->prepare(
                "SELECT * FROM compt_utilisateur
                 WHERE LOWER(TRIM(mail)) IN ({$liste}) AND etat = 1 LIMIT 1"
            );
            $stmt->execute($emailParams);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) return self::relierCompteEnseignant($user, (int) $teacher['uid']);

            // 3. Le nom, dans les deux ordres, et seulement pour un compte
            //    enseignant : un homonyme d'un autre rôle ne doit pas répondre.
            $nomFiche    = trim((string) ($teacher['name'] ?? ''));
            $prenomFiche = trim((string) ($teacher['lastName'] ?? ''));
            if ($nomFiche !== '' && $prenomFiche !== '') {
                $stmt = self::$dtb->prepare(
                    "SELECT * FROM compt_utilisateur
                     WHERE etat = 1
                       AND (level = 7 OR privilege = 'teacher' OR user_type = 'teacher')
                       AND (   (LOWER(TRIM(nom)) = LOWER(:n1) AND LOWER(TRIM(prenom)) = LOWER(:p1))
                            OR (LOWER(TRIM(nom)) = LOWER(:n2) AND LOWER(TRIM(prenom)) = LOWER(:p2)))
                     LIMIT 1"
                );
                $stmt->execute([
                    'n1' => $nomFiche,    'p1' => $prenomFiche,
                    'n2' => $prenomFiche, 'p2' => $nomFiche,
                ]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) return self::relierCompteEnseignant($user, (int) $teacher['uid']);
            }

            /* Fiche trouvée, aucun compte : il est créé, comme pour un étudiant. */
            $user = self::createTeacherUserFromGoogle($teacher, $email, $payload);
            if ($user) return $user;

            /* La création a échoué : le distinguer d'un inconnu, pour que le
               message de refus dise quoi faire plutôt que « adresse non reconnue ». */
            self::$motifRefusGoogle = 'enseignant_sans_compte';
            self::logSecurityEvent('google_login_teacher_sans_compte', [
                'teacher_uid' => $teacher['uid'],
                'nom'         => trim($nomFiche . ' ' . $prenomFiche),
            ]);
        } catch (PDOException $e) {
            // Repli silencieux : les autres chemins de recherche restent valables.
        }

        return null;
    }

    /**
     * Retourne les variantes d'email Google acceptées pour la connexion.
     * - email brut en minuscule
     * - alias + supprimé
     * - variante sans points sur la partie locale (Google)
     *
     * @param string $email
     * @return array
     */
    private static function googleEmailCandidates($email) {
        $email = strtolower(trim((string)$email));
        if ($email === '' || strpos($email, '@') === false) {
            return [];
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        $domain = trim($domain);

        if ($local === '' || $domain === '') {
            return [$email];
        }

        $candidates = [$local . '@' . $domain];

        // Alias Gmail/Google Workspace: ignorer le suffixe +tag
        $plusPos = strpos($local, '+');
        $baseLocal = $plusPos !== false ? substr($local, 0, $plusPos) : $local;
        $candidates[] = $baseLocal . '@' . $domain;

        // Pour les domaines Google connus du projet, ignorer aussi les points
        if (in_array($domain, ['gmail.com', 'googlemail.com', 'zurcher.edu.mg'], true)) {
            $dotlessLocal = str_replace('.', '', $baseLocal);
            $candidates[] = $dotlessLocal . '@' . $domain;
        }

        return array_values(array_unique(array_filter($candidates, function ($v) {
            return $v !== '';
        })));
    }

    /**
     * Canonicalise l'email Google pour stockage de session/compte.
     *
     * @param string $email
     * @return string
     */
    private static function canonicalGoogleEmail($email) {
        $candidates = self::googleEmailCandidates($email);
        return $candidates[1] ?? ($candidates[0] ?? strtolower(trim((string)$email)));
    }

    /**
     * Vérifie un token Google ID en appelant l'API tokeninfo de Google
     *
     * @param string $idToken
     * @return array|false Le payload décodé ou false
     */
    private static function verifyGoogleToken($idToken) {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) {
            return false;
        }
        
        $payload = json_decode($response, true);
        if (!$payload || empty($payload['email'])) {
            return false;
        }
        
        // Vérifier le client ID si configuré
        if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '') {
            if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
                self::logSecurityEvent('google_token_invalid_audience', [
                    'expected' => GOOGLE_CLIENT_ID,
                    'received' => $payload['aud'] ?? 'none'
                ]);
                return false;
            }
        }
        
        return $payload;
    }

    /**
     * Relie à sa fiche un compte enseignant trouvé par son courriel ou son nom.
     *
     * Sans teacher_uid, l'espace enseignant ne retrouve la fiche que par le
     * nom (getTeacherUid), ce qui échoue dès qu'une graphie diffère. Seuls un
     * compte enseignant et un compte de chef de mention (un professeur, voir
     * aEspaceEnseignant) sont reliés : un autre membre du personnel qui
     * enseigne garde son compte tel quel.
     */
    private static function relierCompteEnseignant(array $user, int $uid) {
        $enseignant = in_array((int) ($user['level'] ?? 0), [ROLE_CHEF_MENTION, 7], true)
            || in_array($user['privilege'] ?? '', ['teacher', 'chef_mention'], true)
            || in_array($user['user_type'] ?? '', ['teacher', 'chef_mention'], true);
        if (!$enseignant || !empty($user['teacher_uid']) || $uid <= 0) {
            return $user;
        }
        try {
            self::$dtb->prepare('UPDATE compt_utilisateur SET teacher_uid = :uid WHERE id = :id AND teacher_uid IS NULL')
                ->execute(['uid' => $uid, 'id' => (int) $user['id']]);
            $user['teacher_uid'] = $uid;
            self::logSecurityEvent('google_teacher_account_linked', ['user_id' => (int) $user['id'], 'teacher_uid' => $uid]);
        } catch (PDOException $e) {
            // Le compte reste utilisable : getTeacherUid retrouvera la fiche par le nom.
        }
        return $user;
    }

    /**
     * Crée le compte d'un enseignant à sa première connexion Google.
     *
     * Même principe que pour un étudiant : la fiche `teacher` porte l'adresse
     * vérifiée par Google, le compte est créé et relié à elle par teacher_uid,
     * donc à ses cours. Rôle enseignant (niveau 7) ; mot de passe aléatoire, la
     * connexion se fait par Google. Les noms suivent compt_utilisateur : nom de
     * famille dans `nom` (teacher.name), prénoms dans `prenom` (teacher.lastName).
     *
     * @return array|false Le nouveau compte, ou false
     */
    private static function createTeacherUserFromGoogle(array $teacher, string $email, array $payload) {
        if (self::$dtb === null || $email === '' || empty($teacher['uid'])) return false;

        try {
            /* Identifiant de connexion : la partie locale de l'adresse, ou, si
               elle est deja prise, la meme suivie du numero de fiche. */
            $pseudo = preg_replace('/[^a-z0-9._-]/i', '', explode('@', $email)[0]) ?: ('enseignant' . $teacher['uid']);
            $stmt = self::$dtb->prepare('SELECT COUNT(*) FROM compt_utilisateur WHERE pseudo = :p');
            $stmt->execute(['p' => $pseudo]);
            if ((int) $stmt->fetchColumn() > 0) {
                $pseudo .= '-' . $teacher['uid'];
            }
            $photo = (string) ($payload['picture'] ?? '');
            if (!preg_match('#^https://#i', $photo)) {
                $photo = '';
            }
            $poste = trim((string) ($teacher['position'] ?? ''));

            $stmt = self::$dtb->prepare(
                "INSERT INTO compt_utilisateur
                 (pseudo, password, nom, prenom, post, mail, level, privilege, user_type, teacher_uid, etat, photos, theme, update_user, date_entry)
                 VALUES (:pseudo, :password, :nom, :prenom, :post, :mail, 7, 'teacher', 'teacher', :uid, 1, :photos, '', 0, CURDATE())"
            );
            $stmt->execute([
                'pseudo'   => $pseudo,
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'nom'      => trim((string) ($teacher['name'] ?? '')),
                'prenom'   => trim((string) ($teacher['lastName'] ?? '')),
                'post'     => $poste !== '' ? $poste : 'Enseignant',
                'mail'     => $email,
                'uid'      => (int) $teacher['uid'],
                'photos'   => $photo,
            ]);

            $newId = self::$dtb->lastInsertId();
            if ($newId) {
                self::logSecurityEvent('google_teacher_account_created', ['user_id' => (int) $newId, 'teacher_uid' => (int) $teacher['uid']]);
                $stmt = self::$dtb->prepare("SELECT * FROM compt_utilisateur WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $newId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            self::logSecurityEvent('google_teacher_create_error', [
                'teacher_uid' => $teacher['uid'],
                'error'       => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Crée automatiquement un compte utilisateur pour un étudiant Google
     *
     * @param array $student Les données de l'étudiant depuis tbl_2024_etudiant
     * @param string $email L'adresse email Google
     * @param array $payload Le payload Google (contient name, picture, etc.)
     * @return array|false Le nouvel utilisateur ou false
     */
    private static function createStudentUserFromGoogle($student, $email, $payload) {
        if (self::$dtb === null) return false;

        try {
            $pseudo = $student['student_id'] ?? ('google_' . explode('@', $email)[0]);
            // Mot de passe aléatoire (l'étudiant utilisera toujours Google pour se connecter)
            $randomPass = bin2hex(random_bytes(16));
            $hashedPassword = password_hash($randomPass, PASSWORD_DEFAULT);

            $stmt = self::$dtb->prepare(
                "INSERT INTO compt_utilisateur 
                 (pseudo, password, nom, prenom, mail, level, privilege, user_type, student_id, etat, photos)
                 VALUES (:pseudo, :password, :nom, :prenom, :mail, 8, 'student', 'student', :student_id, 1, :photos)"
            );
            $stmt->execute([
                'pseudo' => $pseudo,
                'password' => $hashedPassword,
                'nom' => $student['student_nom'] ?? '',
                'prenom' => $student['student_prenom'] ?? '',
                'mail' => $email,
                'student_id' => $student['student_id'] ?? '',
                'photos' => $payload['picture'] ?? ''
            ]);

            $newId = self::$dtb->lastInsertId();
            if ($newId) {
                $stmt = self::$dtb->prepare("SELECT * FROM compt_utilisateur WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $newId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            // Log erreur
            self::logSecurityEvent('google_user_create_error', [
                'email' => $email, 
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }
}
} // End if(!class_exists('Middleware'))

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

if (!function_exists('hasAnyPrivilege')) {
/**
 * L'utilisateur porte-t-il l'un de ces privilèges ? Le superadmin passe toujours.
 *
 * Même règle que BaseController::requireAnyPrivileges() : menus et liens
 * affichent ainsi exactement ce que le contrôleur autorise, sans qu'une entrée
 * active mène à un refus.
 *
 * @param string[] $privileges Rôles admis, tels qu'écrits en base.
 */
function hasAnyPrivilege(array $privileges) {
    foreach ($privileges as $privilege) {
        if (Middleware::hasPrivilege($privilege)) { return true; }
    }
    if (isset($_SESSION['user_privilege']) && in_array((string) $_SESSION['user_privilege'], $privileges, true)) {
        return true;
    }
    return (int) ($_SESSION['user_level'] ?? 99) === 1;
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

if (!function_exists('aEspaceEnseignant')) {
/**
 * Le compte a-t-il l'espace enseignant ? Enseignants et chefs de mention.
 */
function aEspaceEnseignant() {
    return Middleware::aEspaceEnseignant();
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

/* ==========================================================================
 * RETOUR A LA PAGE D'ORIGINE APRES RECONNEXION
 * --------------------------------------------------------------------------
 * Quand la session expire, l'utilisateur est renvoye vers /login et perdait
 * la page ou il travaillait. On memorise l'URL demandee avant de rediriger,
 * puis on y revient une fois l'authentification reussie.
 * ========================================================================== */

if (!function_exists('memoriserUrlDemandee')) {
/**
 * Memorise l'URL courante pour y revenir apres reconnexion.
 *
 * On ne memorise QUE ce qui a du sens a rejouer : une navigation GET vers une
 * page. Sont ecartes les appels AJAX (rejouer un endpoint afficherait du JSON
 * brut), les exports (produisent un fichier), les ressources statiques, et les
 * pages du parcours d'authentification lui-meme (sinon on boucle).
 */
function memoriserUrlDemandee() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return; // un POST ne se rejoue pas : le corps est perdu
    }

    // Requete AJAX : la reponse n'est pas une page a reafficher.
    $ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
             && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $fetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    if ($ajax || ($fetchMode !== '' && $fetchMode !== 'navigate')) {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri === '') { return; }

    // Sécurité : uniquement un chemin local. « // » serait protocol-relative
    // et permettrait une redirection vers un site externe apres connexion.
    if ($uri[0] !== '/' || (isset($uri[1]) && $uri[1] === '/')) { return; }
    if (strpos($uri, "\r") !== false || strpos($uri, "\n") !== false) { return; }

    $chemin = parse_url($uri, PHP_URL_PATH) ?: '';

    // Parcours d'authentification : y revenir ferait une boucle.
    foreach (['/login', '/logout', '/loading', '/choisir-espace', '/goodbye'] as $exclu) {
        if ($chemin === $exclu || substr($chemin, -strlen($exclu)) === $exclu) { return; }
    }

    // Endpoints et exports : pas des pages.
    if (preg_match('~/(app|init)/~', $chemin)) { return; }
    if (strpos($chemin, 'data.topdf') !== false) { return; }
    if (preg_match('~\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|pdf|csv|xlsx?|zip|docx?)$~i', $chemin)) { return; }

    $_SESSION['url_demandee'] = $uri;
}
}

if (!function_exists('recupererUrlDemandee')) {
/**
 * Restitue l'URL memorisee et l'efface, ou null s'il n'y en a pas.
 * La validation est refaite ici : la session a pu etre ecrite ailleurs.
 */
function recupererUrlDemandee() {
    $uri = $_SESSION['url_demandee'] ?? null;
    unset($_SESSION['url_demandee']);

    if (!is_string($uri) || $uri === '') { return null; }
    if ($uri[0] !== '/' || (isset($uri[1]) && $uri[1] === '/')) { return null; }
    if (strpos($uri, "\r") !== false || strpos($uri, "\n") !== false) { return null; }

    return $uri;
}
}

if (!function_exists('aDeuxEspaces')) {
/**
 * Le compte connecté est-il aussi enseignant (ou, depuis l'espace enseignant, du personnel) ?
 * Le menu du compte propose alors « Changer d'espace ».
 */
function aDeuxEspaces() {
    return Middleware::aDeuxEspaces();
}
}

if (!function_exists('accueilDuCompte')) {
/**
 * La page d'accueil d'un compte, sans $app_base : le tableau de bord pour le
 * personnel, l'espace enseignant, l'espace etudiant.
 *
 * C'est la seule page d'arrivee apres la connexion, et celle de /login quand
 * on est deja connecte (demande du 06/10/2026). Avant, /login renvoyait le
 * compte deja connecte vers la liste des etudiants (140 fois en dix jours),
 * et une reconnexion rouvrait la page consultee avant l'expiration de la
 * session (recupererUrlDemandee, qui n'est plus appelee).
 */
function accueilDuCompte($user = null) {
    if (!is_array($user)) { $user = Middleware::getCurrentUser(); }
    if (!is_array($user)) { $user = []; }
    $niveau = (int) ($user['level'] ?? 4);
    $type = $user['user_type'] ?? 'staff';
    $privilege = $user['privilege'] ?? '';

    if ($niveau === 8 || $type === 'student' || $privilege === 'student') {
        return '/student/home';
    }
    if ($niveau === 7 || $type === 'teacher' || $privilege === 'teacher') {
        return '/teacher/dashboard';
    }
    if ($niveau === ROLE_WORK_EDUCATION || $type === 'work_education' || $privilege === 'work_education') {
        return '/work-education';
    }
    return '/tableau-de-bord';
}
}

if (!function_exists('estWorkEducation')) {
/**
 * Le compte connecte est-il celui du responsable du Work Education ? Il n'a
 * qu'une page (/work-education) : init/head.php le ramene vers elle, et
 * refuserEnseignant() lui ferme les listes du personnel comme a un enseignant.
 */
function estWorkEducation() {
    return (int) ($_SESSION['user_level'] ?? 0) === ROLE_WORK_EDUCATION;
}
}
