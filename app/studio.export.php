<?php
/* =============================================================================
   Studio UAZ — l'export d'un visuel (src/studio.php, src/js/studio.js)

   GET  ?action=sante   le service de rendu repond-il ?
   POST (multipart)     preset, format (4:5…), sortie (png|jpg|pdf|zip), echelle
                        (1|2), impression (1 : PDF A4 avec fond perdu de 3 mm et
                        traits de coupe), donnees (JSON des champs), images (JSON :
                        fondre, ajustement, cadrage, reglages, alt), image_<emplacement>
                        (le fichier) -> le fichier exporte, ou une erreur JSON
                        { message, manquants, debordements }. « zip » : tous les
                        formats d'un coup (PNG et PDF ; en A4, les deux PDF).

   Les donnees sont controlees ici (data/studio.php), les images reencodees
   sans metadonnees ; le service (outils/studio-rendu/) rend avec le meme
   moteur que l'apercu et refuse ce qui manque ou deborde.

   Media et superadmin seuls, comme la page (DashboardController::studio).
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserRequeteCroisee(); // jamais depuis un autre site
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/studio.admin.php');

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function stRepondre(array $charge, int $code = 200): void
{
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($charge, JSON_UNESCAPED_UNICODE);
	exit;
}

if (!in_array((int) ($_SESSION['user_level'] ?? 99), [ROLE_SUPERADMIN, ROLE_MEDIA], true)) {
	stRepondre(['ok' => false, 'message' => 'Le Studio UAZ est réservé au service Media.'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
	if (($_GET['action'] ?? '') === 'sante') {
		$s = studioSante();
		stRepondre(['ok' => $s !== null, 'service' => $s]);
	}
	stRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
}
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); } // le rendu ne bloque pas les autres onglets

/* --- ce qui est demande */
$preset = studioPreset((string) ($_POST['preset'] ?? ''));
if (!$preset) { stRepondre(['ok' => false, 'message' => 'Preset inconnu.'], 404); }
$sortie = (string) ($_POST['sortie'] ?? 'png');
if (!in_array($sortie, ['png', 'jpg', 'pdf', 'zip'], true)) { stRepondre(['ok' => false, 'message' => 'Sortie inconnue (PNG, JPG, PDF ou ZIP).'], 422); }
$format = (string) ($_POST['format'] ?? '');
if ($sortie !== 'zip' && !isset($preset['formats'][$format])) { stRepondre(['ok' => false, 'message' => 'Ce format n\'existe pas pour ce preset.'], 422); }
$echelle = (int) ($_POST['echelle'] ?? 1) === 2 ? 2 : 1;
$impression = (string) ($_POST['impression'] ?? '') === '1';
if ($impression && ($sortie !== 'pdf' || $format !== 'A4')) { stRepondre(['ok' => false, 'message' => 'Le fond perdu et les traits de coupe sont pour le PDF A4.'], 422); }

/* un visuel enregistre (etape 5) : les presets a validation obligatoire n'exportent que ce qui a ete valide,
   lu sur le serveur — jamais ce que le navigateur envoie */
$userId = (int) ($_SESSION['user_id'] ?? 0);
$visuel = null;
if ((int) ($_POST['visuel'] ?? 0) > 0) {
	try {
		$visuel = studioVisuelLire($dtb, (int) $_POST['visuel']);
	} catch (PDOException $e) {
		stRepondre(['ok' => false, 'message' => 'Les visuels ne sont pas installés sur ce serveur.'], 503);
	}
	if (!$visuel || $visuel['preset'] !== $preset['id']) { stRepondre(['ok' => false, 'message' => 'Ce visuel n\'existe pas.'], 404); }
}
$refus = studioExportRefuse($visuel, $preset);
if ($refus !== null) { stRepondre(['ok' => false, 'message' => $refus, 'validation' => true], 403); }
$depuisEnregistre = $visuel !== null && studioValidationObligatoire($preset['id']);

$miseEnPage = studioMiseEnPage($preset, $depuisEnregistre ? $visuel['mise_en_page'] : json_decode((string) ($_POST['mise_en_page'] ?? '{}'), true));
$blocs = studioBlocs($preset, $depuisEnregistre ? $visuel['blocs'] : json_decode((string) ($_POST['blocs'] ?? '[]'), true));
$donnees = $depuisEnregistre ? $visuel['donnees'] : json_decode((string) ($_POST['donnees'] ?? '{}'), true);
if (!is_array($donnees)) { stRepondre(['ok' => false, 'message' => 'Les champs sont illisibles.'], 400); }
[$propres, $erreurs] = studioValiderDonnees($preset, $donnees);
$erreurs = array_merge($erreurs, studioServicesErreurs($preset, $propres, studioReglages($dtb)['listes']['services']));
if ($erreurs) { stRepondre(['ok' => false, 'message' => 'Le visuel n\'est pas prêt.', 'erreurs' => $erreurs], 422); }

/* --- les images : une par emplacement, avec son texte alternatif */
$options = $depuisEnregistre ? $visuel['images'] : json_decode((string) ($_POST['images'] ?? '{}'), true);
$options = is_array($options) ? $options : [];
$images = [];
foreach (studioEmplacements($preset, $blocs) as $emplacement) {
	$id = $emplacement['id'];
	if ($depuisEnregistre) {
		$o = is_array($options[$id] ?? null) ? $options[$id] : [];
		$im = studioImageLire((int) $visuel['id'], (string) ($o['fichier'] ?? ''));
		if (!$im) {
			if (!empty($emplacement['requis'])) { $erreurs[] = "« {$emplacement['libelle']} » : une image est obligatoire."; }
			continue;
		}
		$images[$id] = ['type' => $im['type'], 'base64' => $im['base64'], 'alt' => mb_substr(studioTexte($o['alt'] ?? ''), 0, STUDIO_ALT_MAX)] + studioOptionsImage($o, $emplacement);
		continue;
	}
	$f = $_FILES['image_' . $id] ?? null;
	if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
		if (!empty($emplacement['requis'])) { $erreurs[] = "« {$emplacement['libelle']} » : une image est obligatoire."; }
		continue;
	}
	if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
		$erreurs[] = "« {$emplacement['libelle']} » : l'image n'est pas arrivée entière (trop lourde ?).";
		continue;
	}
	$o = is_array($options[$id] ?? null) ? $options[$id] : [];
	$alt = studioTexte($o['alt'] ?? '');
	if ($alt === '') { $erreurs[] = "« {$emplacement['libelle']} » : décrivez l'image (texte alternatif)."; continue; }
	try {
		$im = studioPreparerImage($f['tmp_name']);
	} catch (StudioErreur $e) {
		$erreurs[] = "« {$emplacement['libelle']} » : " . $e->getMessage();
		continue;
	}
	$images[$id] = ['type' => $im['type'], 'base64' => $im['base64'], 'alt' => mb_substr($alt, 0, STUDIO_ALT_MAX)] + studioOptionsImage($o, $emplacement);
}
if ($erreurs) { stRepondre(['ok' => false, 'message' => 'Le visuel n\'est pas prêt.', 'erreurs' => $erreurs], 422); }

/* --- le rendu : un fichier, ou tous les formats (« Tout exporter ») */
$base = studioBaseNom($preset, $propres);
if ($sortie === 'zip') {
	@set_time_limit(120);
	[$code, $corps, $type] = studioAppelerService('POST', '/lot', [
		'preset' => $preset, 'donnees' => $propres, 'images' => $images, 'miseEnPage' => $miseEnPage, 'blocs' => $blocs, 'titre' => $base, 'elements' => studioElementsLot($preset),
	], 90);
} else {
	[$code, $corps, $type] = studioAppelerService('POST', '/rendre', [
		'preset' => $preset, 'format' => $format, 'donnees' => $propres, 'images' => $images, 'miseEnPage' => $miseEnPage, 'blocs' => $blocs,
		'sortie' => $sortie, 'echelle' => $echelle, 'qualite' => 90, 'impression' => $impression,
		'titre' => $base . '_' . (STUDIO_FORMATS[$format] ?? ''),
	]);
}
if ($code === 0) {
	stRepondre(['ok' => false, 'message' => 'Le service de rendu ne répond pas : l\'export est impossible pour le moment. Prévenez l\'administrateur.'], 503);
}
if ($code !== 200) {
	$j = json_decode($corps, true);
	$libelles = array_column(array_merge($preset['champs'], $preset['images'] ?? []), 'libelle', 'id');
	$nommer = static fn (array $ids) => array_values(array_map(static fn ($id) => $libelles[$id] ?? $id, $ids));
	$debordements = [];
	foreach (is_array($j['debordements'] ?? null) ? $j['debordements'] : [] as $d) { $debordements = array_merge($debordements, $nommer($d['champs'] ?? [])); }
	/* « Tout exporter » : le format qui n'est pas pret est nomme */
	$message = is_array($j) && isset($j['erreur']) ? (string) $j['erreur'] : 'Le rendu a échoué.';
	if (is_array($j) && isset(STUDIO_NOMS_FORMATS[$j['format'] ?? ''])) { $message = '« ' . STUDIO_NOMS_FORMATS[$j['format']] . ' » : ' . $message; }
	stRepondre([
		'ok' => false,
		'message' => $message,
		'manquants' => $nommer(is_array($j['manquants'] ?? null) ? $j['manquants'] : []),
		'debordements' => array_values(array_unique($debordements)),
		'erreurs' => is_array($j['erreurs'] ?? null) ? $j['erreurs'] : [],
	], $code >= 400 && $code < 600 ? $code : 502);
}

/* « Tout exporter » : les fichiers rendus, nommes comme un a un, dans une archive */
if ($sortie === 'zip') {
	$j = json_decode($corps, true);
	$fichiers = [];
	foreach (is_array($j['fichiers'] ?? null) ? $j['fichiers'] : [] as $x) {
		$ext = ($x['sortie'] ?? '') === 'pdf' ? 'pdf' : 'png';
		$fichiers[studioNomFichier($preset, $propres, (string) ($x['format'] ?? ''), $ext, !empty($x['impression']) ? 'imprimeur' : '')] = (string) base64_decode((string) ($x['base64'] ?? ''), true);
	}
	if (!$fichiers) { stRepondre(['ok' => false, 'message' => 'Le service de rendu n\'a rien renvoyé.'], 502); }
	try {
		$corps = studioZip($fichiers);
	} catch (StudioErreur $e) {
		stRepondre(['ok' => false, 'message' => $e->getMessage()], 500);
	}
}

$nom = $sortie === 'zip' ? $base . '.zip' : studioNomFichier($preset, $propres, $format, $sortie, $impression ? 'imprimeur' : '');
/* le journal : qui a exporte quoi, quand, dans quel format */
try {
	studioJournalAjouter($dtb, $visuel, $preset, studioVisuelTitre($preset, $propres), $sortie === 'zip' ? '' : $format, $sortie === 'zip' ? 'zip' : ($impression ? 'imprimeur' : $sortie), $userId);
} catch (PDOException $e) { /* les tables ne sont pas installees : l'export ne s'arrete pas pour autant */ }
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf', 'zip' => 'application/zip'][$sortie]);
header('Content-Disposition: attachment; filename="' . $nom . '"');
header('Content-Length: ' . strlen($corps));
echo $corps;
