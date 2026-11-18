<?php
/**
 * Ajouter une annonce
 * SÉCURISÉ: Vérification des privilèges + CSRF
 */

// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require('../../data/backdb.php');
require('../../data/middleware.php');
initMiddleware($dtb);

function is_ajax_request(): bool {
    return (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && stripos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
}

function respond_add(bool $success, string $message, int $statusCode = 200, ?array $annonce = null): void {
    global $app_base;
    if (is_ajax_request()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => $success, 'message' => $message];
        if ($annonce !== null) {
            $payload['annonce'] = $annonce;
        }
        echo json_encode($payload);
        exit;
    }

    if ($success) {
        header('location:' . $app_base . '/news?success=1&msg=' . urlencode($message));
    } else {
        header('location:' . $app_base . '/news?error=' . urlencode($message));
    }
    exit;
}

// SÉCURITÉ: Vérifier les privilèges
if (!isRegistrar()) {
    respond_add(false, 'Accès refusé: privilèges insuffisants', 403);
}

// SÉCURITÉ: Vérifier le token CSRF
require_csrf();

// Log
Middleware::logSecurityEvent('annonce_create_attempt', [
    'by_user' => $_SESSION['user_id'] ?? null
]);

try {
    $title      = trim($_POST['title'] ?? '');
    $content    = trim($_POST['content'] ?? '');
    $excerpt    = trim($_POST['excerpt'] ?? '') ?: null;
    $category   = $_POST['category'] ?? 'info';
    $author     = trim($_POST['author'] ?? '') ?: null;
    $publishDate = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d');
    $expireDate  = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
    $isPinned   = isset($_POST['is_pinned']) ? 1 : 0;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($title) || empty($content)) {
        respond_add(false, 'Le titre et le contenu sont obligatoires.', 422);
    }

    require_once __DIR__ . '/_image.php';
    $erreurDates = annonceErreurDates($publishDate, $expireDate);
    if ($erreurDates !== null) {
        respond_add(false, $erreurDates, 422);
    }

    // Validate category
    $validCategories = ['info', 'event', 'academic', 'urgent', 'sport', 'culture'];
    if (!in_array($category, $validCategories)) {
        $category = 'info';
    }

    // Image : voir app/.annonces/_image.php
    require_once __DIR__ . '/_image.php';
    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $erreurEnvoi = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erreurEnvoi !== UPLOAD_ERR_OK) {
            // Une image choisie puis refusée : publier sans elle tromperait l'auteur.
            respond_add(false, annonceMessageEnvoi($erreurEnvoi), 422);
        }

        $tmpName = $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpName);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes)) {
            respond_add(false, 'Format d\'image non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP.', 422);
        }

        $imageName = annonceEnregistrerImage($tmpName, (string) $_FILES['image']['name'], __DIR__ . '/../uploads/annonces/');
        if ($imageName === null) {
            respond_add(false, "L'image n'a pas pu être enregistrée sur le serveur. L'annonce n'a pas été publiée : réessayez, ou publiez-la sans image.", 500);
        }
    }

    // Insert
    $stmt = $dtb->prepare("INSERT INTO t_annonces (title, content, excerpt, image, category, is_pinned, is_active, author, publish_date, expire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $title, $content, $excerpt, $imageName, $category,
        $isPinned, $isActive, $author, $publishDate, $expireDate
    ]);

    $newId = (int) $dtb->lastInsertId();

    Middleware::logSecurityEvent('annonce_created', [
        'annonce_id' => $newId,
        'title' => $title,
        'by_user' => $_SESSION['user_id'] ?? null
    ]);

    $created = DB::find('t_annonces', $newId);
    respond_add(true, 'Annonce publiée avec succès.', 200, $created ?: null);

} catch (Exception $e) {
    error_log('Erreur création annonce: ' . $e->getMessage());
    respond_add(false, 'Erreur lors de la création de l\'annonce.', 500);
}
