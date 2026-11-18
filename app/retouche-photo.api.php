<?php
/* =============================================================================
   Retouche photo — la sauvegarde sur le serveur

   Le pendant serveur de src/js/retouche-photo.sauvegarde.js : l'atelier
   garde son travail dans le navigateur (IndexedDB) et en envoie une copie ici,
   pour le retrouver sur tout appareil apres connexion (demande du 13/10/2026).
   Chaque enregistrement du navigateur (photo d'origine, reglages, pinceau,
   detourage, fond, etat de l'atelier) devient un dossier :

       data/retouche/<compte>/<magasin>/<cle>/enregistrement.json   le JSON, les
                                                 donnees binaires remplacees par
                                                 { "$part": "p0", ... }
       data/retouche/<compte>/<magasin>/<cle>/meta.json             majLe, taille
       data/retouche/<compte>/<magasin>/<cle>/p0.bin, p1.bin...     les donnees

   data/ est ferme au navigateur (data/.htaccess) : tout passe par ce fichier,
   pour le compte connecte seul (le chemin vient de la session, jamais de la
   requete). Media et superadmin, comme la page (src/retouche-photo.php).

   GET  ?action=liste                         les enregistrements (majLe), les
                                              suppressions, la date du dernier
                                              « Tout fermer », la place prise
   GET  ?action=lire&magasin=&cle=            le JSON d'un enregistrement
   GET  ?action=part&magasin=&cle=&part=      une donnee binaire
   POST ?action=debut      (JSON)             annonce un enregistrement et la
                                              taille de ses donnees ; sans donnees,
                                              il est ecrit aussitot
   POST ?action=morceau&jeton=&part=&pos=     un morceau (corps brut) : la limite
                                              de PHP en production est de 2 Mo par
                                              fichier et 8 Mo par envoi
   POST ?action=fin&jeton=                    met l'enregistrement en place
   POST ?action=supprimer  (JSON)             retire des enregistrements et garde
                                              la trace de la suppression (un autre
                                              appareil ne les renvoie pas)
   POST ?action=vider      (JSON)             « Tout fermer » : tout retirer

   Le plus recent l'emporte (majLe, en millisecondes, pose par le navigateur).
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function rpRepondre(array $charge, int $code = 200): void
{
	http_response_code($code);
	echo json_encode($charge, JSON_UNESCAPED_UNICODE);
	exit;
}

if (!in_array((int) ($_SESSION['user_level'] ?? 99), [ROLE_SUPERADMIN, ROLE_MEDIA], true)) {
	rpRepondre(['ok' => false, 'message' => 'La retouche photo est réservée au service Media.'], 403);
}
$compte = (int) ($_SESSION['user_id'] ?? 0);
if ($compte <= 0) {
	rpRepondre(['ok' => false, 'session_expiree' => true, 'message' => 'Votre session a expiré. Reconnectez-vous pour continuer.'], 401);
}

const RP_MAGASINS = ['fichiers', 'photos', 'masques', 'fonds', 'detourages', 'atelier'];
const RP_QUOTA = 3 * 1024 * 1024 * 1024;   // par compte
const RP_MORCEAU_MAX = 1024 * 1024;        // le navigateur envoie 512 Ko
const RP_JSON_MAX = 4 * 1024 * 1024;

$racine = __DIR__ . '/../data/retouche/' . $compte;
if (!is_dir($racine) && !@mkdir($racine, 0775, true) && !is_dir($racine)) {
	rpRepondre(['ok' => false, 'message' => "La sauvegarde du serveur n'est pas disponible (dossier data/retouche)."], 500);
}

function rpMagasin(string $m): string
{
	if (!in_array($m, RP_MAGASINS, true)) { rpRepondre(['ok' => false, 'message' => 'Magasin inconnu.'], 400); }
	return $m;
}
function rpNom(string $v, string $quoi): string
{
	if (!preg_match('~^[A-Za-z0-9_.-]{1,80}$~', $v) || $v[0] === '.') { rpRepondre(['ok' => false, 'message' => $quoi . ' invalide.'], 400); }
	return $v;
}
function rpLireJson(string $chemin, $defaut)
{
	if (!is_file($chemin)) { return $defaut; }
	$v = json_decode((string) file_get_contents($chemin), true);
	return $v === null ? $defaut : $v;
}
function rpEcrireJson(string $chemin, $donnees): void
{
	$tmp = $chemin . '.' . bin2hex(random_bytes(4)) . '.tmp';
	file_put_contents($tmp, json_encode($donnees, JSON_UNESCAPED_UNICODE));
	rename($tmp, $chemin);
}
function rpEffacer(string $chemin): void
{
	if (!file_exists($chemin) && !is_link($chemin)) { return; }
	if (is_dir($chemin) && !is_link($chemin)) {
		foreach (scandir($chemin) as $f) { if ($f !== '.' && $f !== '..') { rpEffacer($chemin . '/' . $f); } }
		@rmdir($chemin);
	} else {
		@unlink($chemin);
	}
}
function rpCorpsJson(): array
{
	$brut = file_get_contents('php://input', false, null, 0, RP_JSON_MAX + 1);
	if ($brut === false || strlen($brut) > RP_JSON_MAX) { rpRepondre(['ok' => false, 'message' => 'Enregistrement trop volumineux.'], 413); }
	$v = json_decode($brut, true);
	if (!is_array($v)) { rpRepondre(['ok' => false, 'message' => 'Requête illisible.'], 400); }
	return $v;
}
/* Un verrou par compte : deux onglets ou deux appareils n'ecrivent pas en meme temps. */
function rpVerrou(string $racine)
{
	$v = fopen($racine . '/.verrou', 'c');
	flock($v, LOCK_EX);
	return $v;
}
/* Place prise par le compte (hors envois en cours). */
function rpPlace(string $racine): int
{
	$total = 0;
	foreach (RP_MAGASINS as $m) {
		foreach (glob($racine . '/' . $m . '/*/meta.json') ?: [] as $f) { $total += (int) (rpLireJson($f, [])['taille'] ?? 0); }
	}
	return $total;
}

$action = (string) ($_GET['action'] ?? '');
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($methode === 'POST') {
	refuserRequeteCroisee(); // jamais depuis un autre site
}

/* --------------------------------------------------------------------- liste */
if ($action === 'liste' && $methode === 'GET') {
	$enregistrements = [];
	foreach (RP_MAGASINS as $m) {
		foreach (glob($racine . '/' . $m . '/*/meta.json') ?: [] as $f) {
			$meta = rpLireJson($f, []);
			$enregistrements[] = ['magasin' => $m, 'cle' => basename(dirname($f)), 'majLe' => (int) ($meta['majLe'] ?? 0), 'taille' => (int) ($meta['taille'] ?? 0)];
		}
	}
	$compteInfo = rpLireJson($racine . '/compte.json', []);
	rpRepondre([
		'ok' => true, 'enregistrements' => $enregistrements,
		'supprimes' => (object) rpLireJson($racine . '/supprimes.json', []),
		'videLe' => (int) ($compteInfo['videLe'] ?? 0),
		'place' => ['prise' => array_sum(array_column($enregistrements, 'taille')), 'max' => RP_QUOTA],
	]);
}

/* ------------------------------------------------------------- lire et part */
if (($action === 'lire' || $action === 'part') && $methode === 'GET') {
	$dossier = $racine . '/' . rpMagasin((string) ($_GET['magasin'] ?? '')) . '/' . rpNom((string) ($_GET['cle'] ?? ''), 'Clé');
	if (!is_file($dossier . '/enregistrement.json')) { rpRepondre(['ok' => false, 'message' => 'Introuvable.'], 404); }
	if ($action === 'lire') {
		$meta = rpLireJson($dossier . '/meta.json', []);
		echo '{"ok":true,"majLe":' . (int) ($meta['majLe'] ?? 0) . ',"enregistrement":' . file_get_contents($dossier . '/enregistrement.json') . '}';
		exit;
	}
	$part = rpNom((string) ($_GET['part'] ?? ''), 'Donnée');
	$fichier = $dossier . '/' . $part . '.bin';
	if (!is_file($fichier)) { rpRepondre(['ok' => false, 'message' => 'Donnée introuvable.'], 404); }
	header('Content-Type: application/octet-stream');
	header('Content-Length: ' . filesize($fichier));
	readfile($fichier);
	exit;
}

/* --------------------------------------------------------------------- debut */
if ($action === 'debut' && $methode === 'POST') {
	$c = rpCorpsJson();
	$magasin = rpMagasin((string) ($c['magasin'] ?? ''));
	$cle = rpNom((string) ($c['cle'] ?? ''), 'Clé');
	$majLe = (int) ($c['majLe'] ?? 0);
	$parts = is_array($c['parts'] ?? null) ? $c['parts'] : [];
	if (!isset($c['enregistrement']) || !is_array($c['enregistrement'])) { rpRepondre(['ok' => false, 'message' => 'Enregistrement manquant.'], 400); }
	$tailles = [];
	foreach ($parts as $nom => $p) {
		$tailles[rpNom((string) $nom, 'Donnée')] = max(0, (int) ($p['taille'] ?? 0));
	}
	$dossier = $racine . '/' . $magasin . '/' . $cle;
	$actuel = rpLireJson($dossier . '/meta.json', null);
	if ($actuel && (int) ($actuel['majLe'] ?? 0) > $majLe) {
		rpRepondre(['ok' => true, 'plusRecent' => true, 'majLe' => (int) $actuel['majLe']]);
	}
	$json = json_encode($c['enregistrement'], JSON_UNESCAPED_UNICODE);
	$taille = strlen($json) + array_sum($tailles);
	if (rpPlace($racine) - (int) ($actuel['taille'] ?? 0) + $taille > RP_QUOTA) {
		rpRepondre(['ok' => false, 'plein' => true, 'message' => "Plus de place sur le serveur pour vos photos (3 Go par compte) : fermez celles dont vous n'avez plus besoin."], 507);
	}
	// envois abandonnes depuis plus de deux jours
	foreach (glob($racine . '/.envois/*', GLOB_ONLYDIR) ?: [] as $vieux) {
		if (filemtime($vieux) < time() - 2 * 86400) { rpEffacer($vieux); }
	}
	$jeton = bin2hex(random_bytes(12));
	$envoi = $racine . '/.envois/' . $jeton;
	if (!@mkdir($envoi, 0775, true)) { rpRepondre(['ok' => false, 'message' => "L'envoi n'a pas pu commencer."], 500); }
	file_put_contents($envoi . '/enregistrement.json', $json);
	rpEcrireJson($envoi . '/entete.json', ['magasin' => $magasin, 'cle' => $cle, 'majLe' => $majLe, 'parts' => $tailles, 'taille' => $taille]);
	foreach ($tailles as $nom => $t) { touch($envoi . '/' . $nom . '.bin'); }
	if (!$tailles) { $_GET['jeton'] = $jeton; $action = 'fin'; }
	else { rpRepondre(['ok' => true, 'jeton' => $jeton]); }
}

/* ------------------------------------------------------------------- morceau */
if ($action === 'morceau' && $methode === 'POST') {
	$envoi = $racine . '/.envois/' . rpNom((string) ($_GET['jeton'] ?? ''), 'Jeton');
	$entete = rpLireJson($envoi . '/entete.json', null);
	$part = rpNom((string) ($_GET['part'] ?? ''), 'Donnée');
	if (!$entete || !isset($entete['parts'][$part])) { rpRepondre(['ok' => false, 'message' => 'Envoi inconnu : recommencez.'], 404); }
	$fichier = $envoi . '/' . $part . '.bin';
	clearstatcache();
	$recu = (int) filesize($fichier);
	$pos = (int) ($_GET['pos'] ?? -1);
	if ($pos !== $recu) { rpRepondre(['ok' => true, 'recu' => $recu]); } // morceau deja recu, ou a reprendre plus loin
	$morceau = file_get_contents('php://input', false, null, 0, RP_MORCEAU_MAX + 1);
	if ($morceau === false || strlen($morceau) > RP_MORCEAU_MAX || $recu + strlen($morceau) > (int) $entete['parts'][$part]) {
		rpRepondre(['ok' => false, 'message' => 'Morceau invalide.'], 400);
	}
	file_put_contents($fichier, $morceau, FILE_APPEND);
	touch($envoi);
	clearstatcache();
	rpRepondre(['ok' => true, 'recu' => (int) filesize($fichier)]);
}

/* ----------------------------------------------------------------------- fin */
if ($action === 'fin' && $methode === 'POST') {
	$jeton = rpNom((string) ($_GET['jeton'] ?? ''), 'Jeton');
	$envoi = $racine . '/.envois/' . $jeton;
	$entete = rpLireJson($envoi . '/entete.json', null);
	if (!$entete) { rpRepondre(['ok' => false, 'message' => 'Envoi inconnu : recommencez.'], 404); }
	clearstatcache();
	foreach ($entete['parts'] as $nom => $t) {
		if ((int) @filesize($envoi . '/' . $nom . '.bin') !== (int) $t) { rpRepondre(['ok' => false, 'message' => 'Envoi incomplet.'], 409); }
	}
	$magasin = rpMagasin((string) $entete['magasin']);
	$cle = rpNom((string) $entete['cle'], 'Clé');
	$majLe = (int) $entete['majLe'];
	$verrou = rpVerrou($racine);
	$dossierMagasin = $racine . '/' . $magasin;
	if (!is_dir($dossierMagasin)) { @mkdir($dossierMagasin, 0775, true); }
	$dossier = $dossierMagasin . '/' . $cle;
	$actuel = rpLireJson($dossier . '/meta.json', null);
	if ($actuel && (int) ($actuel['majLe'] ?? 0) > $majLe) {
		rpEffacer($envoi);
		rpRepondre(['ok' => true, 'plusRecent' => true, 'majLe' => (int) $actuel['majLe']]);
	}
	unlink($envoi . '/entete.json');
	rpEcrireJson($envoi . '/meta.json', ['majLe' => $majLe, 'taille' => (int) $entete['taille'], 'recuLe' => time()]);
	// le nouveau prend la place de l'ancien d'un coup (deux renommages)
	$ancien = $dossier . '.ancien.' . $jeton;
	if (is_dir($dossier)) { rename($dossier, $ancien); }
	rename($envoi, $dossier);
	rpEffacer($ancien);
	$supprimes = rpLireJson($racine . '/supprimes.json', []);
	if (isset($supprimes[$magasin . '/' . $cle]) && $supprimes[$magasin . '/' . $cle] <= $majLe) {
		unset($supprimes[$magasin . '/' . $cle]);
		rpEcrireJson($racine . '/supprimes.json', (object) $supprimes);
	}
	flock($verrou, LOCK_UN);
	rpRepondre(['ok' => true, 'fini' => true]);
}

/* ----------------------------------------------------------------- supprimer */
if ($action === 'supprimer' && $methode === 'POST') {
	$c = rpCorpsJson();
	$le = (int) ($c['le'] ?? 0);
	$verrou = rpVerrou($racine);
	$supprimes = rpLireJson($racine . '/supprimes.json', []);
	foreach ((array) ($c['elements'] ?? []) as $e) {
		$magasin = rpMagasin((string) ($e['magasin'] ?? ''));
		$cle = rpNom((string) ($e['cle'] ?? ''), 'Clé');
		$dossier = $racine . '/' . $magasin . '/' . $cle;
		$meta = rpLireJson($dossier . '/meta.json', null);
		if ($meta && (int) ($meta['majLe'] ?? 0) <= $le) { rpEffacer($dossier); }
		$supprimes[$magasin . '/' . $cle] = max((int) ($supprimes[$magasin . '/' . $cle] ?? 0), $le);
	}
	// la trace d'une suppression sert trois mois : assez pour qu'un appareil oublie revienne
	$limite = (int) (microtime(true) * 1000) - 90 * 86400 * 1000;
	$supprimes = array_filter($supprimes, static fn ($v) => $v >= $limite);
	rpEcrireJson($racine . '/supprimes.json', (object) $supprimes);
	flock($verrou, LOCK_UN);
	rpRepondre(['ok' => true]);
}

/* --------------------------------------------------------------------- vider */
if ($action === 'vider' && $methode === 'POST') {
	$c = rpCorpsJson();
	$le = (int) ($c['le'] ?? 0);
	$verrou = rpVerrou($racine);
	foreach (RP_MAGASINS as $m) {
		foreach (glob($racine . '/' . $m . '/*/meta.json') ?: [] as $f) {
			if ((int) (rpLireJson($f, [])['majLe'] ?? 0) <= $le) { rpEffacer(dirname($f)); }
		}
	}
	$compteInfo = rpLireJson($racine . '/compte.json', []);
	$compteInfo['videLe'] = max((int) ($compteInfo['videLe'] ?? 0), $le);
	rpEcrireJson($racine . '/compte.json', $compteInfo);
	rpEcrireJson($racine . '/supprimes.json', (object) []);
	flock($verrou, LOCK_UN);
	rpRepondre(['ok' => true]);
}

rpRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
