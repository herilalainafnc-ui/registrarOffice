<?php
/**
 * API: Mettre à jour l'autorisation de réinscription
 * Endpoint: POST /api/update-authorization
 */

// Supprimer l'affichage des erreurs PHP (pour ne pas polluer le JSON)
error_reporting(0);
ini_set('display_errors', 0);

// Nettoyer tout output buffer existant
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Seules les requêtes POST sont acceptées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Inclure les fichiers de configuration
require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// Vérifier que l'utilisateur est authentifié
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Récupérer l'utilisateur actuel
$user = currentUser();
if (!$user || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
    exit;
}

// Vérifier le rôle via le niveau (level) ou le privilege
// Level 1 = superadmin, Level 4 = comptabilité (caissier)
$user_level = (int)($user['level'] ?? 99);
$user_privilege = $user['privilege'] ?? '';

$allowed_levels = [1, 2, 4]; // superadmin, admin, comptabilité
$allowed_privileges = ['superadmin', 'administrator', 'comptabilite'];

if (!in_array($user_level, $allowed_levels) && !in_array($user_privilege, $allowed_privileges)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé. Seuls les caissiers/trésoriers peuvent modifier les autorisations.']);
    exit;
}

$user_id = $user['id'];

// Récupérer les données du POST
$student_id = $_POST['student_id'] ?? null;
$authorized = isset($_POST['authorized_reinscription']) ? (int)$_POST['authorized_reinscription'] : 0;

// Validation
if (empty($student_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID étudiant manquant']);
    exit;
}

// Vérifier que l'étudiant existe
$stmt = $dtb->prepare("SELECT id FROM tbl_2024_etudiant WHERE id = :id");
$stmt->execute(['id' => $student_id]);
if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
    exit;
}

try {
    // Mettre à jour l'autorisation
    $updateStmt = $dtb->prepare("
        UPDATE tbl_2024_etudiant 
        SET 
            authorized_reinscription = :authorized,
            authorized_by = :authorized_by,
            authorized_date = NOW()
        WHERE id = :id
    ");

    $result = $updateStmt->execute([
        'authorized' => $authorized,
        'authorized_by' => $user_id,
        'id' => $student_id
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $authorized ? 'Étudiant autorisé pour la réinscription' : 'Autorisation retirée',
            'authorized' => $authorized
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
