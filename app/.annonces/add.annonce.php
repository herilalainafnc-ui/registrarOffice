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

    // Validate category
    $validCategories = ['info', 'event', 'academic', 'urgent', 'sport', 'culture'];
    if (!in_array($category, $validCategories)) {
        $category = 'info';
    }

    // Image upload
    $imageName = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpName);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($mimeType, $allowedMimes)) {
            respond_add(false, 'Format d\'image non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP.', 422);
        }

        // Generate unique filename
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageName = 'annonce_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $destDir = '../uploads/annonces/';
        
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Re-encode with GD for security
        $uploaded = false;
        if (function_exists('imagecreatefromstring')) {
            $imgData = file_get_contents($tmpName);
            $src = @imagecreatefromstring($imgData);
            if ($src) {
                $destPath = $destDir . $imageName;
                // Save as JPEG for consistency
                $imageName = pathinfo($imageName, PATHINFO_FILENAME) . '.jpg';
                $destPath = $destDir . $imageName;
                imagejpeg($src, $destPath, 90);
                imagedestroy($src);
                $uploaded = true;
            }
        }
        
        if (!$uploaded) {
            // Fallback: simple move
            move_uploaded_file($tmpName, $destDir . $imageName);
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
