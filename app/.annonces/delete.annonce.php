<?php
/**
 * Supprimer une annonce
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

function respond_delete(bool $success, string $message, int $statusCode = 200, int $id = 0): void {
    global $app_base;
    if (is_ajax_request()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'id' => $id,
        ]);
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
    respond_delete(false, 'Accès refusé', 403);
}

require_csrf();

try {
    $id = (int) ($_POST['id'] ?? 0);
    
    if (!$id) {
        respond_delete(false, 'ID invalide.', 422);
    }

    // Check exists & get image name
    $annonce = DB::find('t_annonces', $id);
    if (!$annonce) {
        respond_delete(false, 'Annonce introuvable.', 404, $id);
    }

    // Delete image file
    if (!empty($annonce['image'])) {
        $imgPath = '../uploads/annonces/' . $annonce['image'];
        if (file_exists($imgPath)) {
            @unlink($imgPath);
        }
    }

    // Delete from DB
    DB::delete('t_annonces', 'id = ?', [$id]);

    Middleware::logSecurityEvent('annonce_deleted', [
        'annonce_id' => $id,
        'title' => $annonce['title'],
        'by_user' => $_SESSION['user_id'] ?? null
    ]);

    respond_delete(true, 'Annonce supprimée.', 200, $id);

} catch (Exception $e) {
    error_log('Erreur suppression annonce: ' . $e->getMessage());
    respond_delete(false, 'Erreur lors de la suppression.', 500);
}
