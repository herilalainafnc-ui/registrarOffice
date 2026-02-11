<?php
/**
 * Récupérer une annonce par ID
 * Endpoint AJAX — retourne JSON
 * SÉCURISÉ: Vérification des privilèges
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

try {
    $id = (int) ($_GET['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit;
    }

    $annonce = DB::find('t_annonces', $id);
    
    if (!$annonce) {
        echo json_encode(['success' => false, 'message' => 'Annonce introuvable']);
        exit;
    }

    echo json_encode(['success' => true, 'annonce' => $annonce]);

} catch (Exception $e) {
    error_log('Erreur get annonce: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
