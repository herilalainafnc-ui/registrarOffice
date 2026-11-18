<?php
/* =============================================================================
   Canevas de collecte de la MESupReS en Excel (menu « Exports & documents » de /etudiants)

   POST  annee    « 2025 - 2026 »
         mention  « TOUT » ou le sigle d'une mention (THEO, GEST...)
   Rend le classeur du ministere, feuille ETUDIANTS remplie avec les inscrits de l'annee
   (data/canevas.mesupres.php). Lecture seule. Niveaux 1 a 3 (registrariat), comme les autres
   exports de la barre d'outils ; jamais depuis un autre site.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserRequeteCroisee();

if ((int) ($_SESSION['user_level'] ?? 99) > 3) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Accès refusé : cet export est réservé au registrariat.";
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Méthode non autorisée.";
    exit;
}

require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/canevas.mesupres.php');

$annee   = trim((string) ($_POST['annee'] ?? ''));
$mention = strtoupper(trim((string) ($_POST['mention'] ?? 'TOUT')));
if (!preg_match('/^\d{4} ?- ?\d{4}$/', $annee)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Choisissez une année universitaire.";
    exit;
}
if ($mention !== 'TOUT' && !preg_match('/^[A-Z]{2,10}$/', $mention)) {
    $mention = 'TOUT';
}

set_time_limit(120);
try {
    $lignes  = canevasMesupresLignes($dtb, $annee, $mention);
    $fichier = canevasMesupresXlsx($lignes, $annee);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Le canevas n'a pas pu être produit : " . $e->getMessage();
    exit;
}

$nom = 'CANEVAS DE COLLECTE_' . str_replace(' ', '', $annee) . '_UAZ' . ($mention !== 'TOUT' ? '_' . $mention : '') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nom . '"');
header('Content-Length: ' . filesize($fichier));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($fichier);
@unlink($fichier);
