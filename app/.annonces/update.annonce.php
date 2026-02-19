<?php
/**
 * Mettre à jour une annonce
 * SÉCURISÉ: Vérification des privilèges + CSRF
 */

// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require('../../data/backdb.php');
require('../../data/middleware.php');
initMiddleware($dtb);

// SÉCURITÉ
if (!isRegistrar()) {
    http_response_code(403);
    die('Accès refusé');
}

require_csrf();

try {
    $id         = (int) ($_POST['id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $content    = trim($_POST['content'] ?? '');
    $excerpt    = trim($_POST['excerpt'] ?? '') ?: null;
    $category   = $_POST['category'] ?? 'info';
    $author     = trim($_POST['author'] ?? '') ?: null;
    $publishDate = !empty($_POST['publish_date']) ? $_POST['publish_date'] : null;
    $expireDate  = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
    $isPinned   = isset($_POST['is_pinned']) ? 1 : 0;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $oldImage   = $_POST['oldImage'] ?? '';

    if (!$id || empty($title) || empty($content)) {
        header('location:' . $app_base . '/news?error=' . urlencode('Données invalides.'));
        exit;
    }

    // Validate category
    $validCategories = ['info', 'event', 'academic', 'urgent', 'sport', 'culture'];
    if (!in_array($category, $validCategories)) {
        $category = 'info';
    }

    // Check annonce exists
    $existing = DB::find('t_annonces', $id);
    if (!$existing) {
        header('location:' . $app_base . '/news?error=' . urlencode('Annonce introuvable.'));
        exit;
    }

    // Image upload
    $imageName = $oldImage ?: $existing['image'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpName);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($mimeType, $allowedMimes)) {
            header('location:' . $app_base . '/news?error=' . urlencode('Format d\'image non autorisé.'));
            exit;
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $newImageName = 'annonce_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $destDir = '../uploads/annonces/';
        
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Re-encode with GD
        $uploaded = false;
        if (function_exists('imagecreatefromstring')) {
            $imgData = file_get_contents($tmpName);
            $src = @imagecreatefromstring($imgData);
            if ($src) {
                $newImageName = pathinfo($newImageName, PATHINFO_FILENAME) . '.jpg';
                imagejpeg($src, $destDir . $newImageName, 90);
                imagedestroy($src);
                $uploaded = true;
            }
        }
        if (!$uploaded) {
            move_uploaded_file($tmpName, $destDir . $newImageName);
        }

        // Delete old image
        if (!empty($existing['image'])) {
            $oldPath = $destDir . $existing['image'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $imageName = $newImageName;
    }

    // Update
    $stmt = $dtb->prepare("UPDATE t_annonces SET title=?, content=?, excerpt=?, image=?, category=?, is_pinned=?, is_active=?, author=?, publish_date=?, expire_date=? WHERE id=?");
    $stmt->execute([
        $title, $content, $excerpt, $imageName, $category,
        $isPinned, $isActive, $author, $publishDate, $expireDate, $id
    ]);

    Middleware::logSecurityEvent('annonce_updated', [
        'annonce_id' => $id,
        'title' => $title,
        'by_user' => $_SESSION['user_id'] ?? null
    ]);

    header('location:' . $app_base . '/news?success=1&msg=' . urlencode('Annonce mise à jour avec succès.'));
    exit;

} catch (Exception $e) {
    error_log('Erreur mise à jour annonce: ' . $e->getMessage());
    header('location:' . $app_base . '/news?error=' . urlencode('Erreur lors de la mise à jour.'));
    exit;
}
