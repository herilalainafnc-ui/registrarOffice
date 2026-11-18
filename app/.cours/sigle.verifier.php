<?php
/* Le sigle est-il déjà pris ? JSON pour src/js/cours.sigle.js.
   GET sigle=…  [sauf=id du cours modifié]  — lecture seule. */

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once(__DIR__ . '/../../data/cours.sigle.php');

$sigle = mb_substr((string) ($_GET['sigle'] ?? ''), 0, 100);
$sauf  = ctype_digit((string) ($_GET['sauf'] ?? '')) ? (int) $_GET['sauf'] : null;

try {
    /* Modification (sauf = le cours modifie) : le code AFFICHE ne doit pas
       etre celui qu un autre cours affiche. Creation (pas de sauf) : le sigle
       devient une cle technique, les cles des autres cours comptent aussi.
       Voir data/cours.sigle.php. */
    $doublons = $sauf !== null
        ? coursSigleAliasDoublons($dtb, $sigle, $sauf)
        : coursSigleCreationDoublons($dtb, $sigle);
    echo json_encode([
        'ok'       => true,
        'sigle'    => coursSigleNormalise($sigle),
        'libre'    => !$doublons,
        'doublons' => $doublons,
        'message'  => $doublons ? coursSigleMessage($doublons) : null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('sigle.verifier : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Vérification du sigle impossible.'], JSON_UNESCAPED_UNICODE);
}
