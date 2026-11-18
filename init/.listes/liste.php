<?php
/*
 * Mise a jour en direct d une liste (etudiants, cours, enseignants) : la
 * recherche, les filtres et le tri appliques ENSEMBLE. Repond en JSON :
 * les lignes rendues, le total et les options des filtres avec leurs nombres.
 * Voir data/listes.php (la description) et src/js/listes.js (l appelant).
 *
 * Les droits sont ceux des pages : la liste des etudiants est refusee aux
 * etudiants et aux enseignants (DashboardController::index), celles des cours
 * et des enseignants sont reservees au superadmin et au registrariat.
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');

$nom = (string) ($_GET['liste'] ?? '');
if ($nom === 'etudiants') {
	exigerConnexion();
	refuserEtudiant();
	refuserEnseignant("La liste complète des étudiants est réservée au personnel.");
} elseif ($nom === 'cours' || $nom === 'enseignants') {
	exigerConnexion();
	exigerPrivileges(['superadmin', 'registrar'], "Seuls le superadmin et le registrariat ont accès à cette liste.");
} else {
	http_response_code(404);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'message' => 'Liste inconnue.']);
	exit;
}

require __DIR__ . '/../../data/backdb.php';
require_once __DIR__ . '/../../data/cours.sigle.php';
require_once __DIR__ . '/../../data/listes.php';

$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_app_root, strlen($_doc_root)) ?: '';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
	$def = listesDefinition($nom, $dtb);
	$etat = listesEtat($def, $_GET);
	$r = listesExecuter($dtb, $def, $etat, $app_base);
	echo json_encode(['ok' => true] + $r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	http_response_code(500);
	error_log('[listes] ' . $e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'La liste n’a pas pu être chargée. Réessayez.'], JSON_UNESCAPED_UNICODE);
}
