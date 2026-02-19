<?php
/**
 * Page de déconnexion sécurisée
 */
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require('../data/backdb.php');
require('../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// Récupérer les infos utilisateur avant déconnexion pour l'écran d'au revoir
$userName = '';
$userFirstName = '';
$userPhoto = '';
$userType = 'staff';

if (isLoggedIn()) {
    $user = Middleware::getCurrentUser();
    if ($user) {
        $userName = $user['nom'] ?? '';
        $userFirstName = $user['prenom'] ?? '';
        $userPhoto = $user['photos'] ?? '';
        $userType = 'staff';
        
        // Pour les étudiants, récupérer les infos depuis tbl_2024_etudiant
        if (Middleware::isStudent()) {
            $studentInfo = Middleware::getStudentInfo();
            if ($studentInfo) {
                $userName = $studentInfo['student_nom'] ?? $userName;
                $userFirstName = $studentInfo['student_prenom'] ?? $userFirstName;
                $userPhoto = $studentInfo['image_student'] ?? '';
                $userType = 'student';
            }
        }
        
        // Pour les enseignants, récupérer les infos depuis teacher
        if (Middleware::isTeacher()) {
            $teacherInfo = Middleware::getTeacherInfo();
            if ($teacherInfo) {
                $userName = $teacherInfo['lastName'] ?? $userName;
                $userFirstName = $teacherInfo['name'] ?? $userFirstName;
                $userPhoto = $teacherInfo['teacher_image'] ?? '';
                $userType = 'teacher';
            }
        }
    }
    // Log de déconnexion
    Middleware::logSecurityEvent('logout', ['user_id' => $_SESSION['user_id'] ?? null]);
}

// Stocker les infos pour l'écran d'au revoir (dans des variables locales car la session sera détruite)
$goodbyeName = urlencode($userName);
$goodbyeFirstname = urlencode($userFirstName);
$goodbyePhoto = urlencode($userPhoto);
$goodbyeUserType = urlencode($userType);

// Déconnexion sécurisée via le middleware
Middleware::logout();

// Redirection vers la page d'au revoir avec les paramètres
header('Location: ' . $app_base . '/goodbye?name=' . $goodbyeName . '&firstname=' . $goodbyeFirstname . '&photo=' . $goodbyePhoto . '&type=' . $goodbyeUserType);
exit;
?>