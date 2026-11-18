<?php
/**
 * Garde commune aux points d'entree du module inscription.
 *
 * Ces scripts sont joignables directement en HTTP : ils ne passent pas par
 * InscriptionController et n'heritent donc d'aucun de ses controles. Sans
 * cette garde, n'importe qui pouvait creer des inscriptions, modifier des
 * montants ou supprimer des notes sans etre connecte.
 *
 * Meme regle d'acces que la page : authentifie ET niveau <= 6 (le niveau
 * exige par InscriptionController::index).
 *
 * Usage en tete de chaque endpoint :
 *     require_once(__DIR__ . '/_guard.php');
 *     inscriptionGuard();            // POST + CSRF (defaut)
 *     inscriptionGuard('GET');       // lecture seule, CSRF quand meme exige
 */

// Fichier d'inclusion : rien a servir si on l'appelle directement.
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
	http_response_code(403);
	exit;
}

require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/middleware.php');
// Annee scolaire courante et resolution de la session : une seule definition
// pour toute la chaine d'inscription (voir data/inscription.session.php).
require_once(__DIR__ . '/../../data/inscription.session.php');
// Bareme financier par session : generate.student.php en a besoin pour lire le
// tarif de la session inscrite plutot qu'un tarif global.
require_once(__DIR__ . '/../../data/finance.bareme.php');
// Calcul des frais d'un dossier, partage par l'inscription et le recalcul.
require_once(__DIR__ . '/../../data/inscription.frais.php');
initMiddleware($dtb);

// Chemin de base de l'application, comme le calculent les autres endpoints.
// Les fragments de stages/ en ont besoin pour construire leurs URL absolues.
if (!isset($app_base)) {
	$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
	$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
	$app_base = ($_dr !== '' && strpos($_ar, $_dr) === 0) ? (substr($_ar, strlen($_dr)) ?: '') : '';
}

/** Niveau minimal requis, aligne sur InscriptionController::index(). */
const INSCRIPTION_NIVEAU_REQUIS = 6;

/**
 * Interrompt la requete avec une reponse JSON.
 * Middleware::requireCSRF() renvoie du HTML : inutilisable ici, tous ces
 * points d'entree sont consommes en AJAX et doivent repondre en JSON.
 */
function inscriptionRefus($message, $code = 403) {
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['success' => false, 'message' => $message]);
	exit;
}

/**
 * Garde des fragments HTML de inscription/stages/ (mode.payement, session,
 * top.stages). Ils sont charges en AJAX et rendent des donnees de l'etudiant
 * — mode.payement affiche toute la situation financiere. Sans authentification
 * ils repondaient a n'importe qui.
 *
 * Pas de CSRF ici : ces fragments ne font que lire et afficher. Le controle
 * d'acces suffit, et l'exiger casserait des appelants qui ne le transmettent pas.
 */
function inscriptionGuardFragment() {
	if (!isLoggedIn() || !hasLevel(INSCRIPTION_NIVEAU_REQUIS)) {
		http_response_code(403);
		header('Content-Type: text/html; charset=utf-8');
		echo '<div class="p-3 text-red-500">Accès refusé.</div>';
		exit;
	}
}

/**
 * @param string $methode Methode HTTP exigee ('POST', 'GET', ou '' pour ne pas verifier).
 */
function inscriptionGuard($methode = 'POST') {
	if (!isLoggedIn()) {
		inscriptionRefus('Session expirée. Reconnectez-vous pour continuer.', 401);
	}
	if (!hasLevel(INSCRIPTION_NIVEAU_REQUIS)) {
		inscriptionRefus("Accès refusé : privilèges insuffisants.", 403);
	}
	if ($methode !== '' && $_SERVER['REQUEST_METHOD'] !== $methode) {
		inscriptionRefus('Méthode non autorisée (' . $methode . ' attendu).', 405);
	}
	if (!verify_csrf()) {
		inscriptionRefus('Jeton de sécurité invalide ou expiré. Rafraîchissez la page.', 403);
	}
}
