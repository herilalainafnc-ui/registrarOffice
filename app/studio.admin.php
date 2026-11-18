<?php
/* =============================================================================
   Studio UAZ — l'administration (src/studio.php, bouton « Administration »)

   GET  ?action=reglages                  types actifs, valeurs par defaut, listes
   GET  ?action=validateurs               les validateurs designes
   GET  ?action=comptes&q=                des comptes du personnel a designer
   GET  ?action=media                     la mediatheque
   POST action=sauver_reglages            reglages (JSON : inactifs, defauts, listes)
   POST action=role_ajouter | role_retirer   user (id du compte)
   POST action=media_ajouter              titre, alt, image (le fichier)
   POST action=media_supprimer            id

   Superadmin seul : le niveau se lit dans la session (comme app/studio.visuels.php).
   Les editeurs lisent les reglages et la mediatheque par app/studio.visuels.php.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserRequeteCroisee(); // jamais depuis un autre site
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/studio.admin.php');

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function saRepondre(array $charge, int $code = 200): void
{
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($charge, JSON_UNESCAPED_UNICODE);
	exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ((int) ($_SESSION['user_level'] ?? 99) !== (int) ROLE_SUPERADMIN) {
	saRepondre(['ok' => false, 'message' => 'L\'administration du Studio est réservée au superadmin.'], 403);
}
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = (string) ($post ? ($_POST['action'] ?? '') : ($_GET['action'] ?? ''));
if ($post && session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

try {
	if (!$post) {
		switch ($action) {
			case 'reglages':
				saRepondre(['ok' => true, 'reglages' => studioReglages($dtb)]);
			case 'validateurs':
				saRepondre(['ok' => true, 'validateurs' => studioValidateurs($dtb)]);
			case 'comptes':
				saRepondre(['ok' => true, 'comptes' => studioComptesRecherche($dtb, (string) ($_GET['q'] ?? ''))]);
			case 'media':
				saRepondre(['ok' => true, 'media' => studioMediaListe($dtb)]);
		}
		saRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
	}

	switch ($action) {
		case 'sauver_reglages':
			$reg = studioReglagesNettoyer(json_decode((string) ($_POST['reglages'] ?? ''), true));
			studioReglagesSauver($dtb, $reg, $userId);
			saRepondre(['ok' => true, 'reglages' => studioReglages($dtb)]);

		case 'role_ajouter':
			studioValidateurAjouter($dtb, (int) ($_POST['user'] ?? 0), $userId);
			saRepondre(['ok' => true, 'validateurs' => studioValidateurs($dtb)]);

		case 'role_retirer':
			studioValidateurRetirer($dtb, (int) ($_POST['user'] ?? 0));
			saRepondre(['ok' => true, 'validateurs' => studioValidateurs($dtb)]);

		case 'media_ajouter':
			$f = $_FILES['image'] ?? null;
			if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { saRepondre(['ok' => false, 'message' => 'L\'image n\'est pas arrivée entière (trop lourde ?).'], 422); }
			$im = studioPreparerImage($f['tmp_name']);
			studioMediaAjouter($dtb, $im, (string) ($_POST['titre'] ?? ''), (string) ($_POST['alt'] ?? ''), $userId);
			saRepondre(['ok' => true, 'media' => studioMediaListe($dtb)]);

		case 'media_supprimer':
			studioMediaSupprimer($dtb, (int) ($_POST['id'] ?? 0));
			saRepondre(['ok' => true, 'media' => studioMediaListe($dtb)]);
	}
	saRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
} catch (StudioErreur $e) {
	saRepondre(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (PDOException $e) {
	saRepondre(['ok' => false, 'message' => 'L\'administration n\'est pas installée sur ce serveur (migration data/migration_studio_admin.sql).'], 503);
}
