<?php
/**
 * Session et jeton de securite, pour le mode hors ligne (src/js/hors-ligne.js).
 *
 * Quand le reseau revient, la file des changements faits hors ligne demande ici :
 *   - le reseau est-il la ? (toute reponse HTTP, meme 401, le prouve : le
 *     navigateur ne sait pas le dire de facon fiable) ;
 *   - la session est-elle encore ouverte, et pour quel compte ? Sinon : 401, la file
 *     attend la reconnexion au lieu d'envoyer dans le vide ;
 *   - le jeton CSRF a jour : celui de la page ouverte avant la coupure a pu expirer ;
 *   - l'heure du serveur, pour dater les changements sans se fier a l'horloge de
 *     l'appareil (decalage, serveur_le).
 *
 * GET seulement, jamais mis en cache.
 */
require_once(__DIR__ . '/../data/guard.ajax.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* Connecte, sinon 401 en JSON (le client le comprend : « reconnectez-vous »). */
$_SERVER['HTTP_X_REQUESTED_WITH'] = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'XMLHttpRequest';
exigerConnexion();

echo json_encode([
    'ok'          => true,
    'utilisateur' => (int) ($_SESSION['user_id'] ?? 0),
    'csrf_token'  => function_exists('csrf_token') ? csrf_token() : '',
    'serveur_le'  => (int) round(microtime(true) * 1000),
]);
