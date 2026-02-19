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

// SÉCURITÉ
if (!isRegistrar()) {
    http_response_code(403);
    die('Accès refusé');
}

require_csrf();

try {
    $id = (int) ($_POST['id'] ?? 0);
    
    if (!$id) {
        header('location:' . $app_base . '/news?error=' . urlencode('ID invalide.'));
        exit;
    }

    // Check exists & get image name
    $annonce = DB::find('t_annonces', $id);
    if (!$annonce) {
        header('location:' . $app_base . '/news?error=' . urlencode('Annonce introuvable.'));
        exit;
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

    header('location:' . $app_base . '/news?success=1&msg=' . urlencode('Annonce supprimée.'));
    exit;

} catch (Exception $e) {
    error_log('Erreur suppression annonce: ' . $e->getMessage());
    header('location:' . $app_base . '/news?error=' . urlencode('Erreur lors de la suppression.'));
    exit;
}
