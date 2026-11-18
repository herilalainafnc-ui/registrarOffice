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

function is_ajax_request(): bool {
    return (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && stripos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
}

function respond_update(bool $success, string $message, int $statusCode = 200, ?array $annonce = null): void {
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

// SÉCURITÉ
if (!isRegistrar()) {
    respond_update(false, 'Accès refusé', 403);
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
    $retirerImage = !empty($_POST['remove_image']);

    if (!$id || empty($title) || empty($content)) {
        respond_update(false, 'Données invalides.', 422);
    }

    // Validate category
    $validCategories = ['info', 'event', 'academic', 'urgent', 'sport', 'culture'];
    if (!in_array($category, $validCategories)) {
        $category = 'info';
    }

    // Check annonce exists
    $existing = DB::find('t_annonces', $id);
    if (!$existing) {
        respond_update(false, 'Annonce introuvable.', 404);
    }

    require_once __DIR__ . '/_image.php';
    $erreurDates = annonceErreurDates($publishDate, $expireDate);
    if ($erreurDates !== null) {
        respond_update(false, $erreurDates, 422);
    }

    /* Image : celle de la base. Le nom envoye par le navigateur (« oldImage »)
       n'est plus lu : enregistre tel quel, il servait ensuite de chemin de
       suppression (delete.annonce.php) — un nom fabrique effacait n'importe
       quel fichier accessible au serveur. */
    $imageName = $existing['image'];
    $imageARetirer = null;
    $destDir = __DIR__ . '/../uploads/annonces/';
    if (!empty($_FILES['image']['name'])) {
        $erreurEnvoi = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erreurEnvoi !== UPLOAD_ERR_OK) {
            respond_update(false, annonceMessageEnvoi($erreurEnvoi), 422);
        }

        $tmpName = $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpName);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes)) {
            respond_update(false, 'Format d\'image non autorisé.', 422);
        }

        $newImageName = annonceEnregistrerImage($tmpName, (string) $_FILES['image']['name'], $destDir);
        if ($newImageName === null) {
            // L'ancienne image est conservée : rien n'est modifié.
            respond_update(false, "La nouvelle image n'a pas pu être enregistrée sur le serveur. L'annonce n'a pas été modifiée : réessayez.", 500);
        }

        // L'ancienne image part une fois l'annonce enregistree (plus bas).
        $imageARetirer = $existing['image'];
        $imageName = $newImageName;
    } elseif ($retirerImage) {
        $imageARetirer = $existing['image'];
        $imageName = null;
    }

    // Update
    $stmt = $dtb->prepare("UPDATE t_annonces SET title=?, content=?, excerpt=?, image=?, category=?, is_pinned=?, is_active=?, author=?, publish_date=?, expire_date=? WHERE id=?");
    $stmt->execute([
        $title, $content, $excerpt, $imageName, $category,
        $isPinned, $isActive, $author, $publishDate, $expireDate, $id
    ]);

    if (!empty($imageARetirer)) {
        annonceSupprimerImage($imageARetirer, $destDir);
    }

    Middleware::logSecurityEvent('annonce_updated', [
        'annonce_id' => $id,
        'title' => $title,
        'by_user' => $_SESSION['user_id'] ?? null
    ]);

    $updated = DB::find('t_annonces', $id);
    respond_update(true, 'Annonce mise à jour avec succès.', 200, $updated ?: null);

} catch (Exception $e) {
    error_log('Erreur mise à jour annonce: ' . $e->getMessage());
    respond_update(false, 'Erreur lors de la mise à jour.', 500);
}
