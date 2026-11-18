<?php
/**
 * Toggle un champ d'annonce (is_pinned ou is_active)
 * Endpoint AJAX — retourne JSON
 * SÉCURISÉ: Vérification des privilèges + CSRF
 */

require('../../data/backdb.php');
require('../../data/middleware.php');
initMiddleware($dtb);

header('Content-Type: application/json; charset=utf-8');

// SÉCURITÉ
if (!isRegistrar()) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// CSRF via POST
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

try {
    $id    = (int) ($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = (int) ($_POST['value'] ?? 0);

    // Validate field
    if (!in_array($field, ['is_pinned', 'is_active'])) {
        echo json_encode(['success' => false, 'message' => 'Champ invalide']);
        exit;
    }

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit;
    }

    // Check exists
    $annonce = DB::find('t_annonces', $id);
    if (!$annonce) {
        echo json_encode(['success' => false, 'message' => 'Annonce introuvable']);
        exit;
    }

    // Update
    $stmt = $dtb->prepare("UPDATE t_annonces SET $field = ? WHERE id = ?");
    $stmt->execute([$value, $id]);

    $labels = [
        'is_pinned' => $value ? 'Annonce épinglée' : 'Annonce désépinglée',
        'is_active' => $value ? 'Annonce activée' : 'Annonce masquée',
    ];

    Middleware::logSecurityEvent('annonce_toggled', [
        'annonce_id' => $id,
        'field' => $field,
        'value' => $value,
        'by_user' => $_SESSION['user_id'] ?? null
    ]);

    echo json_encode(['success' => true, 'message' => $labels[$field]]);

} catch (Exception $e) {
    error_log('Erreur toggle annonce: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
