<?php
/* Journal d'activité — liste paginée, en JSON (src/js/settings.logs.js).
   Superadmin uniquement : le journal expose les données envoyées et les
   valeurs modifiées de tout le projet. La règle est appliquée ICI : ce
   fichier est joignable directement, hors du routeur. */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ((int) ($_SESSION['user_level'] ?? 99) !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Accès refusé : le journal d\'activité est réservé au super administrateur.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once(__DIR__ . '/../data/audit.lecture.php');

try {
    if (!journalDisponible($dtb)) {
        echo json_encode(['ok' => true, 'indisponible' => true, 'items' => [], 'suivant' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $filtres = journalFiltres($_GET);
    $sortie  = ['ok' => true] + journalListe($dtb, $filtres, $filtres['apres'] ? 200 : 50);
    /* Les chiffres du jour accompagnent la premiere page, et chaque
       interrogation du direct qui les demande (stats=1) : ils suivent sans
       recharger la liste. */
    if ((!$filtres['avant'] && !$filtres['apres']) || !empty($_GET['stats'])) {
        $sortie['stats'] = journalStats($dtb);
    }
    echo json_encode($sortie, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('audit.liste : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Lecture du journal impossible.'], JSON_UNESCAPED_UNICODE);
}
