<?php
/* =============================================================================
   Studio UAZ — les visuels enregistres (src/studio.php, src/js/studio.js)

   GET  ?action=liste&vue=mes|tous|a_valider   les visuels (sans leur contenu)
   GET  ?action=ouvrir&id=                     un visuel complet + ce que peut faire le compte
   GET  ?action=image&id=&f=                   l'image d'un visuel
   GET  ?action=historique&id=                 ses versions
   GET  ?action=journal                        qui a exporte quoi (validateurs et superadmin)
   GET  ?action=moi                            les roles du compte
   POST action=enregistrer   id (0 : nouveau), preset, format, donnees (JSON), images (JSON :
                             options + « fichier » de l'image deja enregistree), legende,
                             motif (auto|manuel), mise_en_page (JSON : { format: { id: { dx, dy, l, h, k } } }), blocs (JSON : blocs ajoutes, dans l'ordre des calques), image_<emplacement> (le fichier, si nouveau)
   POST action=dupliquer | supprimer | soumettre | valider | refuser (motif) | restaurer (version)

   Media, validateurs et superadmin ; le niveau se lit dans la session, comme
   la page (DashboardController::studio). Un visuel peut etre ouvert par tout
   le service ; il ne se modifie que par son auteur, un validateur ou le
   superadmin (les autres le dupliquent).
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserRequeteCroisee(); // jamais depuis un autre site
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/studio.admin.php');

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function svRepondre(array $charge, int $code = 200): void
{
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($charge, JSON_UNESCAPED_UNICODE);
	exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$niveau = (int) ($_SESSION['user_level'] ?? 99);
if (!in_array($niveau, [ROLE_SUPERADMIN, ROLE_MEDIA], true)) {
	svRepondre(['ok' => false, 'message' => 'Le Studio UAZ est réservé au service Media.'], 403);
}
try {
	$roles = studioRoles($dtb, $userId, $niveau);
} catch (PDOException $e) {
	svRepondre(['ok' => false, 'message' => 'Les visuels ne sont pas encore installés sur ce serveur (migration data/migration_studio_visuels.sql).'], 503);
}

$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = (string) ($post ? ($_POST['action'] ?? '') : ($_GET['action'] ?? ''));
$lire = static fn (string $k) => $post ? ($_POST[$k] ?? null) : ($_GET[$k] ?? null);
if ($post && session_status() === PHP_SESSION_ACTIVE) { session_write_close(); } // l'envoi d'images ne bloque pas les autres onglets

/** Ce que le compte peut faire d'un visuel, pour le client. */
function svDroits(array $v, int $userId, array $roles): array
{
	$modifier = studioPeutModifier($v, $userId, $roles);
	return [
		'modifier' => $modifier,
		'supprimer' => (int) $v['auteur_id'] === $userId || $roles['admin'],
		'soumettre' => $modifier && in_array($v['statut'], ['brouillon', 'refuse'], true),
		'decider' => $roles['validateur'] && $v['statut'] === 'a_valider' && ((int) $v['auteur_id'] !== $userId || $roles['admin']),
		'obligatoire' => studioValidationObligatoire((string) $v['preset']),
	];
}
function svSortie(array $v, int $userId, array $roles): array
{
	$images = [];
	foreach ($v['images'] as $id => $m) { $images[$id] = $m + ['url' => 'studio.visuels.php?action=image&id=' . (int) $v['id'] . '&f=' . rawurlencode((string) ($m['fichier'] ?? ''))]; }
	return [
		'id' => (int) $v['id'], 'preset' => $v['preset'], 'titre' => $v['titre'], 'format' => $v['format'], 'donnees' => $v['donnees'], 'images' => $images,
		'miseEnPage' => $v['mise_en_page'] ?: new stdClass(), 'blocs' => $v['blocs'], 'legende' => $v['legende'], 'statut' => $v['statut'], 'motifRefus' => $v['motif_refus'], 'auteur' => $v['auteur_nom'], 'valideur' => $v['valide_nom'],
		'valideLe' => $v['valide_le'], 'modifieLe' => $v['modifie_le'], 'droits' => svDroits($v, $userId, $roles), 'retourBrouillon' => !empty($v['retourBrouillon']),
	];
}
function svVisuel(PDO $dtb, $id): array
{
	$v = studioVisuelLire($dtb, (int) $id);
	if (!$v) { svRepondre(['ok' => false, 'message' => 'Ce visuel n\'existe pas (ou a été supprimé).'], 404); }
	return $v;
}

try {
	switch ($action) {
		case 'moi':
			svRepondre(['ok' => true, 'roles' => $roles, 'obligatoire' => STUDIO_VALIDATION_OBLIGATOIRE, 'reglages' => studioReglages($dtb)]);

		case 'media':
			svRepondre(['ok' => true, 'media' => studioMediaListe($dtb), 'utilisees' => studioImagesUtilisees($dtb, $userId)]);

		case 'media_image':
			$m = studioMediaLire($dtb, (int) $lire('id'));
			if (!$m) { http_response_code(404); exit; }
			header('Content-Type: ' . $m['type']);
			header('Cache-Control: private, max-age=3600');
			readfile($m['chemin']);
			exit;

		case 'liste':
			$vue = in_array($lire('vue'), ['mes', 'tous', 'a_valider'], true) ? $lire('vue') : 'mes';
			svRepondre(['ok' => true, 'visuels' => studioVisuelsListe($dtb, $vue, $userId), 'aValider' => count(studioVisuelsListe($dtb, 'a_valider', $userId))]);

		case 'ouvrir':
			$v = svVisuel($dtb, $lire('id'));
			svRepondre(['ok' => true, 'visuel' => svSortie($v, $userId, $roles)]);

		case 'image':
			$v = svVisuel($dtb, $lire('id'));
			$nom = (string) $lire('f');
			$ok = false;
			foreach ($v['images'] as $m) { if (($m['fichier'] ?? '') === $nom) { $ok = true; } }
			if (!$ok) {   // une image de l'historique
				$s = $dtb->prepare('SELECT images FROM t_studio_version WHERE visuel_id = :id AND images LIKE :f LIMIT 1');
				$s->execute(['id' => $v['id'], 'f' => '%' . $nom . '%']);
				$ok = $s->fetchColumn() !== false;
			}
			$im = $ok ? studioImageLire((int) $v['id'], $nom) : null;
			if (!$im) { http_response_code(404); exit; }
			header('Content-Type: ' . $im['type']);
			header('Cache-Control: private, max-age=3600');
			echo base64_decode($im['base64']);
			exit;

		case 'historique':
			$v = svVisuel($dtb, $lire('id'));
			svRepondre(['ok' => true, 'versions' => studioHistorique($dtb, (int) $v['id'])]);

		case 'journal':
			if (!$roles['validateur']) { svRepondre(['ok' => false, 'message' => 'Le journal est réservé aux validateurs.'], 403); }
			svRepondre(['ok' => true, 'journal' => studioJournalLire($dtb)]);
	}
	if (!$post) { svRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400); }

	/* ---- les ecritures */
	if ($action === 'enregistrer') {
		$preset = studioPreset((string) ($_POST['preset'] ?? ''));
		if (!$preset) { svRepondre(['ok' => false, 'message' => 'Preset inconnu.'], 404); }
		$existant = (int) ($_POST['id'] ?? 0) > 0 ? svVisuel($dtb, $_POST['id']) : null;
		if (!$existant && !studioPresetActif(studioReglages($dtb), $preset['id'])) { svRepondre(['ok' => false, 'message' => 'Ce type de visuel n\'est plus proposé.'], 422); }
		if ($existant && $existant['preset'] !== $preset['id']) { svRepondre(['ok' => false, 'message' => 'Ce visuel est d\'un autre type.'], 422); }
		if ($existant && !studioPeutModifier($existant, $userId, $roles)) {
			svRepondre(['ok' => false, 'message' => 'Ce visuel est à ' . $existant['auteur_nom'] . ' : dupliquez-le pour le modifier.'], 403);
		}
		$donnees = json_decode((string) ($_POST['donnees'] ?? '{}'), true);
		$options = json_decode((string) ($_POST['images'] ?? '{}'), true);
		if (!is_array($donnees) || !is_array($options)) { svRepondre(['ok' => false, 'message' => 'Les champs sont illisibles.'], 400); }
		$blocs = studioBlocs($preset, json_decode((string) ($_POST['blocs'] ?? ''), true) ?? ($existant['blocs'] ?? []));
		foreach (studioEmplacements($preset, $blocs) as $e) {
			$f = $_FILES['image_' . $e['id']] ?? null;
			if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || !is_array($options[$e['id']] ?? null)) { continue; }
			if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { svRepondre(['ok' => false, 'message' => "« {$e['libelle']} » : l'image n'est pas arrivée entière (trop lourde ?)."], 422); }
			try {
				$options[$e['id']]['nouveau'] = studioPreparerImage($f['tmp_name']);
			} catch (StudioErreur $x) {
				svRepondre(['ok' => false, 'message' => "« {$e['libelle']} » : " . $x->getMessage()], 422);
			}
		}
		$motif = (string) ($_POST['motif'] ?? 'auto') === 'manuel' ? 'manuel' : 'auto';
		$legende = isset($_POST['legende']) ? (string) $_POST['legende'] : ($existant['legende'] ?? null);
		$mep = json_decode((string) ($_POST['mise_en_page'] ?? ''), true);
		$v = studioVisuelEnregistrer($dtb, $existant, $preset, (string) ($_POST['format'] ?? ''), $donnees, $options, $legende, $motif, $userId, is_array($mep) ? $mep : ($existant['mise_en_page'] ?? []), $blocs);
		svRepondre(['ok' => true, 'visuel' => svSortie($v, $userId, $roles)]);
	}

	$v = svVisuel($dtb, $_POST['id'] ?? 0);
	$preset = studioPreset((string) $v['preset']);
	if (!$preset) { svRepondre(['ok' => false, 'message' => 'Le type de ce visuel n\'existe plus.'], 422); }
	$droits = svDroits($v, $userId, $roles);

	switch ($action) {
		case 'dupliquer':
			svRepondre(['ok' => true, 'visuel' => svSortie(studioVisuelDupliquer($dtb, $v, $userId), $userId, $roles)]);

		case 'supprimer':
			if (!$droits['supprimer']) { svRepondre(['ok' => false, 'message' => 'Seul l\'auteur ou le superadmin supprime un visuel.'], 403); }
			studioVisuelSupprimer($dtb, (int) $v['id']);
			svRepondre(['ok' => true]);

		case 'restaurer':
			if (!$droits['modifier']) { svRepondre(['ok' => false, 'message' => 'Ce visuel est à ' . $v['auteur_nom'] . '.'], 403); }
			svRepondre(['ok' => true, 'visuel' => svSortie(studioRestaurer($dtb, $v, (int) ($_POST['version'] ?? 0), $userId), $userId, $roles)]);

		case 'soumettre':
			if (!$droits['soumettre']) { svRepondre(['ok' => false, 'message' => 'Ce visuel ne peut pas être soumis maintenant.'], 403); }
			studioSoumettre($dtb, $v, $preset);
			svRepondre(['ok' => true, 'visuel' => svSortie(svVisuel($dtb, $v['id']), $userId, $roles)]);

		case 'valider':
		case 'refuser':
			studioDecider($dtb, $v, $action === 'valider', isset($_POST['motif']) ? (string) $_POST['motif'] : null, $userId, $roles);
			svRepondre(['ok' => true, 'visuel' => svSortie(svVisuel($dtb, $v['id']), $userId, $roles)]);
	}
	svRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
} catch (StudioErreur $e) {
	svRepondre(['ok' => false, 'message' => $e->getMessage()], 422);
}
