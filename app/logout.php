<?php
/**
 * Page de déconnexion sécurisée
 */
require('../data/backdb.php');
require('../data/middleware.php');

// Initialiser le middleware
initMiddleware($dtb);

// Log de déconnexion avant de supprimer la session
if (isLoggedIn()) {
    Middleware::logSecurityEvent('logout', ['user_id' => $_SESSION['user_id'] ?? null]);
}

// Déconnexion sécurisée via le middleware
Middleware::logout();

// Redirection vers la page de connexion
header('Location: ../src/index.php');
exit;
?>