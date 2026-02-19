<?php
/**
 * =============================================================================
 * API Google Authentication - Étudiants @zurcher.edu.mg
 * =============================================================================
 * 
 * Reçoit le token Google ID depuis le client, vérifie l'email,
 * et connecte l'étudiant s'il existe dans la base de données.
 * 
 * Méthode: POST
 * Body JSON: { "credential": "Google ID token" }
 * Réponse JSON: { "success": bool, "redirect": string, "message": string }
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Seules les requêtes POST sont acceptées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

require('../../data/backdb.php');
require('../../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// Calculer le base path
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') $app_base = '';

// Si déjà connecté
if (isLoggedIn()) {
    echo json_encode(['success' => true, 'redirect' => $app_base . '/student/home']);
    exit;
}

// Lire le body JSON
$input = json_decode(file_get_contents('php://input'), true);
$credential = $input['credential'] ?? '';

// Récupérer les données GPS
$gpsLatitude = isset($input['gps_latitude']) && $input['gps_latitude'] !== null ? floatval($input['gps_latitude']) : null;
$gpsLongitude = isset($input['gps_longitude']) && $input['gps_longitude'] !== null ? floatval($input['gps_longitude']) : null;
$gpsAccuracy = isset($input['gps_accuracy']) && $input['gps_accuracy'] !== null ? floatval($input['gps_accuracy']) : null;
$gpsDenied = isset($input['gps_denied']) && $input['gps_denied'] === true;

if (empty($credential)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token manquant']);
    exit;
}

// Tenter l'authentification Google
$user = Middleware::authenticateWithGoogle($credential);

if ($user) {
    // Enregistrer la localisation GPS
    Middleware::saveLoginLocation($gpsLatitude, $gpsLongitude, $gpsAccuracy, 'google', $gpsDenied);
    
    $userLevel = (int)($user['level'] ?? 4);
    $userType = $user['user_type'] ?? 'staff';
    
    // Déterminer la destination (pour un étudiant Google, c'est toujours student.home)
    if ($userLevel === 8 || $userType === 'student' || ($user['privilege'] ?? '') === 'student') {
        $destination = $app_base . '/student/home';
    } elseif ($userLevel === 7 || $userType === 'teacher' || ($user['privilege'] ?? '') === 'teacher') {
        $destination = $app_base . '/teacher/dashboard';
    } else {
        $destination = $app_base . '/dashboard';
    }
    
    // Stocker la destination pour la page de chargement
    $_SESSION['login_redirect'] = $destination;
    
    echo json_encode([
        'success' => true,
        'redirect' => $app_base . '/loading',
        'message' => 'Connexion réussie'
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Aucun compte trouvé pour cette adresse email. Vérifiez que votre email @zurcher.edu.mg est bien enregistré dans le système.'
    ]);
}
