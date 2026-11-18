<?php
/**
 * Garde commune aux points d'entree internes (app/, init/, fragments AJAX).
 *
 * Ces fichiers sont joignables directement en HTTP. Sans cette garde, ils
 * repondaient 200 avec de vraies donnees a une requete anonyme : listes de
 * cours, tableaux d'etudiants, classements.
 *
 * La reponse depend du type de requete :
 *   - appel AJAX      -> 401 + JSON, que le client sait interpreter
 *                        (voir src/js/session.js : message « session expiree »)
 *   - navigation      -> 302 vers /login, en memorisant la page demandee
 *
 * Repondre 302 a un appel AJAX etait le defaut precedent : jQuery suivait la
 * redirection et recevait le HTML de la page de connexion, qu'il inserait dans
 * la page. L'interface semblait figee sans explication.
 *
 * Usage, en TETE de fichier (avant toute sortie) :
 *     require_once(__DIR__ . '/../../data/guard.ajax.php');
 */

/* Amorcage : la garde doit fonctionner meme dans un fichier qui ne charge pas
   lui-meme le middleware. Les require_once sont sans effet s'il l'est deja. */
if (!function_exists('isLoggedIn')) {
    require_once(__DIR__ . '/backdb.php');
    require_once(__DIR__ . '/middleware.php');
    if (function_exists('initMiddleware') && isset($dtb)) { initMiddleware($dtb); }
}
if (!function_exists('estRequeteAjax')) {
/** Vrai si la requete courante attend des donnees, pas une page. */
function estRequeteAjax() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    // Navigateurs recents : Sec-Fetch-Mode distingue la navigation du reste.
    $mode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    if ($mode !== '' && $mode !== 'navigate') {
        return true;
    }
    // Repli : un client qui n'accepte que du JSON n'affiche pas une page.
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return ($accept !== '' && stripos($accept, 'application/json') !== false
            && stripos($accept, 'text/html') === false);
}
}

if (!function_exists('exigerConnexion')) {
/**
 * Interrompt la requete si l'utilisateur n'est pas connecte.
 *
 * @param int|null $niveauMax Niveau maximal autorise (les niveaux bas sont les
 *                            plus privilegies). null = simple connexion.
 */
function exigerConnexion($niveauMax = null) {
    $connecte = function_exists('isLoggedIn') ? isLoggedIn() : !empty($_SESSION['user_id']);

    $autorise = $connecte;
    if ($connecte && $niveauMax !== null && function_exists('hasLevel')) {
        $autorise = hasLevel($niveauMax);
    }
    if ($autorise) {
        return;
    }

    $base = defined('APP_BASE') ? APP_BASE : '';

    if (estRequeteAjax()) {
        http_response_code($connecte ? 403 : 401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'session_expiree' => !$connecte,
            'message' => $connecte
                ? "Accès refusé : privilèges insuffisants."
                : "Votre session a expiré. Reconnectez-vous pour continuer.",
            'login_url' => $base . '/login',
        ]);
        exit;
    }

    // Navigation classique : on memorise la page pour y revenir apres connexion.
    if (!$connecte && function_exists('memoriserUrlDemandee')) {
        memoriserUrlDemandee();
    }
    header('Location: ' . $base . '/login', true, 302);
    exit;
}
}

if (!function_exists('refuserEtudiant')) {
/**
 * Interrompt la requete pour un compte etudiant.
 *
 * A placer apres exigerConnexion() dans tout point d'entree qui lit ou modifie
 * des notes pour le compte du personnel : fiches, releves, bulletins,
 * impressions, classements. Un etudiant ne voit ses notes que dans
 * « Mes Notes », qui applique la publication par session
 * (data/espace.etudiant.php). Ces adresses ne verifiaient que la connexion :
 * un compte etudiant pouvait lire des notes non publiees, voire en modifier.
 */
function refuserEtudiant() {
    $niveauEtudiant = defined('ROLE_STUDENT') ? (int) ROLE_STUDENT : 8;
    $etudiant = (int) ($_SESSION['user_level'] ?? 0) === $niveauEtudiant
        || (function_exists('isStudent') && isStudent());
    if (!$etudiant) {
        return;
    }

    $base = defined('APP_BASE') ? APP_BASE : '';
    if (estRequeteAjax()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'ok'      => false,
            'message' => 'Accès réservé au personnel. Vos notes se consultent dans « Mes Notes ».',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . $base . '/student/notes', true, 302);
    exit;
}
}

if (!function_exists('exigerPrivileges')) {
/**
 * Interrompt la requete si l'utilisateur n'a aucun des privileges donnes.
 *
 * Pendant de BaseController::requireAnyPrivileges pour les fragments appeles
 * hors controleur : fermer une page ne sert a rien si le fragment AJAX qui
 * fournit ses donnees reste ouvert. Un superadmin passe toujours
 * (hasAnyPrivilege). A placer apres exigerConnexion().
 */
function exigerPrivileges(array $privileges, $message = null) {
    if (function_exists('hasAnyPrivilege') && hasAnyPrivilege($privileges)) {
        return;
    }
    $message = $message ?: "Accès refusé : privilèges insuffisants.";
    http_response_code(403);
    if (estRequeteAjax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
}

if (!function_exists('refuserEnseignant')) {
/**
 * Interrompt la requete pour un compte enseignant.
 *
 * A placer apres exigerConnexion() dans tout point d'entree qui MODIFIE une
 * donnee que l'enseignant n'a pas a toucher : fiche etudiant, inscription a un
 * cours, et surtout ecriture directe dans t_2023_notes.
 *
 * Un enseignant ne pose ses notes que par une remise
 * (src/teacher.remise-notes.php), qui n'atteint le dossier de l'etudiant
 * qu'une fois validee par le registrariat. Sans cette garde, le circuit de
 * validation se contournait en appelant l'ancien point d'entree directement :
 * app/.cours/bulksavenotes.php n'exigeait que « ne pas etre etudiant », et
 * ecrivait dans t_2023_notes sans controle ni journal.
 *
 * Les niveaux 1 a 3 passent toujours : un registraire par ailleurs enseignant
 * garde ses droits de registraire.
 */
function refuserEnseignant($message = null) {
    if ((int) ($_SESSION['user_level'] ?? 99) <= 3) {
        return;
    }
    $base = defined('APP_BASE') ? APP_BASE : '';

    /* Le responsable du Work Education : memes portes fermees qu'un
       enseignant, mais il retourne a sa page. */
    if (function_exists('estWorkEducation') && estWorkEducation()) {
        if (estRequeteAjax()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Action réservée au personnel du registrariat.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . $base . '/work-education', true, 302);
        exit;
    }

    $niveauEnseignant = defined('ROLE_TEACHER') ? (int) ROLE_TEACHER : 7;
    $enseignant = (int) ($_SESSION['user_level'] ?? 0) === $niveauEnseignant
        || (function_exists('isTeacher') && isTeacher());
    if (!$enseignant) {
        return;
    }
    $message = $message ?: 'Action reservee au registrariat. Vos notes se transmettent par « Remise des notes ».';

    if (estRequeteAjax()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . $base . '/teacher/remise-notes?erreur=' . rawurlencode($message), true, 302);
    exit;
}
}

if (!function_exists('refuserRequeteCroisee')) {
/**
 * Interrompt une requete emise depuis un autre site (CSRF).
 *
 * Les actions de app/ (supprimer un etudiant, un enseignant, un cours,
 * suspendre, modifier des notes…) ne demandaient aucun jeton : une image ou
 * un formulaire poses sur n'importe quel site suffisaient, si une personne du
 * registrariat connectee l'ouvrait, a declencher l'action a son insu — les
 * suppressions sont de simples liens (GET).
 *
 * Plutot que d'ajouter un jeton a chacun des liens et formulaires qui y
 * menent, on lit l'en-tete Sec-Fetch-Site, pose par le navigateur et qu'un
 * site tiers ne peut pas falsifier : « same-origin » pour l'application
 * elle-meme, « none » pour une adresse saisie ou un favori, « cross-site »
 * ou « same-site » pour un autre site (y compris un autre sous-domaine).
 * Navigateur trop ancien pour l'envoyer : on compare l'hote de l'en-tete
 * Origin, a defaut Referer, a celui de la requete ; sans l'un ni l'autre, on
 * laisse passer.
 *
 * A placer apres exigerConnexion() dans tout point d'entree qui ecrit sans
 * verifier de jeton.
 */
function refuserRequeteCroisee() {
    $hoteDe = static fn ($adresse) => strtolower((string) parse_url((string) $adresse, PHP_URL_HOST));
    $site = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));

    if ($site !== '') {
        $croisee = in_array($site, ['cross-site', 'same-site'], true);
    } else {
        $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($source === '' || $source === 'null') {
            $source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        }
        $hoteRequete = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $croisee = $source !== '' && $source !== 'null' && $hoteDe($source) !== '' && $hoteDe($source) !== $hoteRequete;
    }
    if (!$croisee) {
        return;
    }

    error_log('Requete croisee refusee : ' . ($_SERVER['REQUEST_URI'] ?? '') . ' depuis '
        . ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? $site));
    $message = "Action refusée : elle doit être lancée depuis l'application.";
    http_response_code(403);
    if (estRequeteAjax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
}

if (!function_exists('exigerNiveauNotes')) {
/**
 * Reserve l'ecriture directe dans t_2023_notes aux niveaux 1 a 3.
 *
 * Raccourci pour les points d'entree de notes, qui refaisaient chacun leur
 * propre test de niveau — quand ils en faisaient un.
 */
function exigerNiveauNotes() {
    exigerConnexion();
    refuserEtudiant();
    refuserEnseignant();
    if ((int) ($_SESSION['user_level'] ?? 99) > 3) {
        $message = "Modification des notes reservee au registrariat.";
        http_response_code(403);
        if (estRequeteAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
}
