<?php
/* =============================================================================
   Retouche photo — la photo retouchee devient la photo de profil
   (demande du 19/10/2026 : « permettre qu'on puisse directement insérer la
   photo modifiée pour une photo de profil d'un étudiant ou d'un prof »)

   Le pendant serveur du bouton « Photo de profil » de l'atelier
   (src/js/retouche-photo.js) : chercher la personne, puis remplacer sa photo,
   comme le bouton « Changer la photo » de sa fiche
   (app/.student/updtateImgStd.php, app/.prof/updateImgProf.php) : meme dossier,
   meme nom de fichier, meme reencodage (data/photo.php), meme historique de
   la fiche etudiant. L'ancienne photo reste sur le serveur.

   Media et superadmin seuls, comme la page (src/retouche-photo.php).

   GET  ?action=chercher&type=etudiant|enseignant&q=   les personnes trouvees,
                                                        avec leur photo actuelle
   POST ?action=remplacer  (multipart : type, id, photo) remplace la photo
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserRequeteCroisee(); // jamais depuis un autre site

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function rppRepondre(array $charge, int $code = 200): void
{
	http_response_code($code);
	echo json_encode($charge, JSON_UNESCAPED_UNICODE);
	exit;
}

if (!in_array((int) ($_SESSION['user_level'] ?? 99), [ROLE_SUPERADMIN, ROLE_MEDIA], true)) {
	rppRepondre(['ok' => false, 'message' => 'La retouche photo est réservée au service Media.'], 403);
}
$compte = (int) ($_SESSION['user_id'] ?? 0);

// Chemin de base des liens (l'appli peut vivre dans un sous-dossier), comme app/recherche.rapide.php
$_dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require_once(__DIR__ . '/../data/listes.php');
require_once(__DIR__ . '/../data/photo.php');

/* Les deux sortes de fiches : liste (data/listes.php), table, cle, colonne de
   la photo, dossier et debut du nom de fichier (ceux de la fiche). */
const RPP_TYPES = [
	'etudiant'   => ['liste' => 'etudiants',   'table' => 'tbl_2024_etudiant', 'cle' => 'id',         'alias' => 'e',
	                 'colonne' => 'image_student', 'dossier' => 'photosetudiants',   'prefixe' => ''],
	'enseignant' => ['liste' => 'enseignants', 'table' => 'teacher',           'cle' => 'teacher_id', 'alias' => 't',
	                 'colonne' => 'teacher_image', 'dossier' => 'photosenseignants', 'prefixe' => 't'],
];

function rppType(string $type): array
{
	if (!isset(RPP_TYPES[$type])) { rppRepondre(['ok' => false, 'message' => 'Choisissez un étudiant ou un enseignant.'], 400); }
	return RPP_TYPES[$type];
}

/** Adresse de la photo d'une fiche, ou null si le fichier n'existe pas. */
function rppPhoto(array $t, $nom): ?string
{
	global $app_base;
	$nom = basename(trim((string) $nom));
	if ($nom === '' || !is_file(__DIR__ . '/' . $t['dossier'] . '/' . $nom)) { return null; }
	return $app_base . '/app/' . $t['dossier'] . '/' . rawurlencode($nom);
}

/** Ce que l'atelier montre d'une personne : nom, details, photo actuelle. */
function rppPersonne(string $type, array $l): array
{
	$t = RPP_TYPES[$type];
	if ($type === 'etudiant') {
		$nom = trim(mb_strtoupper((string) $l['student_nom']) . ' ' . (string) $l['student_prenom']);
		$detail = implode(' · ', array_filter([(string) $l['student_id'], (string) $l['etude_envisage'],
			$l['annee_etude'] !== null && $l['annee_etude'] !== '' ? listesNiveauLibelle($l['annee_etude']) : '', (string) $l['annee_scolaire']]));
		$id = (int) $l['id'];
	} else {
		$nom = trim(mb_strtoupper((string) $l['name']) . ' ' . (string) $l['lastName']);
		$detail = trim((string) $l['position']) ?: 'Enseignant';
		$id = (int) $l['teacher_id'];
	}
	return ['type' => $type, 'id' => $id, 'nom' => $nom !== '' ? $nom : 'Sans nom', 'detail' => $detail, 'photo' => rppPhoto($t, $l[$t['colonne']] ?? '')];
}

$action = (string) ($_GET['action'] ?? '');
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ------------------------------------------------------------------ chercher */
if ($action === 'chercher' && $methode === 'GET') {
	$type = (string) ($_GET['type'] ?? '');
	$t = rppType($type);
	$q = trim(preg_replace('~\s+~u', ' ', mb_substr((string) ($_GET['q'] ?? ''), 0, 80)));
	if (mb_strlen($q) < 2) { rppRepondre(['ok' => true, 'q' => $q, 'resultats' => [], 'plus' => false]); }
	$limite = 8;
	try {
		$def = listesDefinition($t['liste'], $dtb);
		[$where, $params] = listesConditions($def, ['q' => $q, 'filtres' => []]);
		// le matricule exact d'abord, puis par nom ; d'une meme personne, la fiche la plus recente en tete
		$ordre = $type === 'etudiant'
			? '(e.student_id = ' . $dtb->quote($q) . ') DESC, e.student_nom, e.student_prenom, e.id DESC'
			: 't.name, t.lastName';
		$st = $dtb->prepare('SELECT ' . $def['select'] . ' FROM ' . $def['from'] . ' WHERE ' . $where . ' ORDER BY ' . $ordre . ' LIMIT ' . ($limite + 1));
		$st->execute($params);
		$lignes = $st->fetchAll(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		error_log('retouche-photo.profil chercher : ' . $e->getMessage());
		rppRepondre(['ok' => false, 'message' => 'La recherche est indisponible pour le moment.'], 500);
	}
	rppRepondre(['ok' => true, 'q' => $q, 'plus' => count($lignes) > $limite,
		'resultats' => array_map(static fn ($l) => rppPersonne($type, $l), array_slice($lignes, 0, $limite))]);
}

/* ----------------------------------------------------------------- remplacer */
if ($action === 'remplacer' && $methode === 'POST') {
	// un envoi au-dela de post_max_size arrive vide : ni type, ni id
	if (!isset($_POST['type'], $_POST['id'])) {
		rppRepondre(['ok' => false, 'message' => "La photo n'est pas arrivée complète : réessayez."], 400);
	}
	$type = (string) $_POST['type'];
	$t = rppType($type);
	$id = (int) $_POST['id'];
	// la fiche doit etre de celles que la recherche propose (non supprimee, perimetre du compte)
	$def = listesDefinition($t['liste'], $dtb);
	$st = $dtb->prepare('SELECT ' . $def['select'] . ' FROM ' . $def['from'] . ' WHERE ' . $def['base'] . ' AND ' . $t['alias'] . '.' . $t['cle'] . ' = ?');
	$st->execute([$id]);
	$fiche = $id > 0 ? $st->fetch(PDO::FETCH_ASSOC) : false;
	if (!$fiche) { rppRepondre(['ok' => false, 'message' => 'Cette fiche est introuvable : elle a peut-être été supprimée.'], 404); }
	if (!isset($_FILES['photo'])) { rppRepondre(['ok' => false, 'message' => "La photo n'a pas été reçue. Réessayez."], 400); }

	$photo = photoEnregistrer($_FILES['photo'], __DIR__ . '/' . $t['dossier'], $t['prefixe'] . $id);
	if ($photo['erreur'] !== null || $photo['nom'] === null) {
		rppRepondre(['ok' => false, 'message' => $photo['erreur'] ?? "La photo n'a pas été reçue. Réessayez."], 422);
	}
	$ancienne = (string) ($fiche[$t['colonne']] ?? '');
	$maj = $dtb->prepare('UPDATE ' . $t['table'] . ' SET ' . $t['colonne'] . ' = ?, last_change_user_id = ?, last_change_datetime = ? WHERE ' . $t['cle'] . ' = ?');
	$maj->execute([$photo['nom'], $compte, date('Y-m-d'), $id]);
	if ($type === 'etudiant') {
		require_once(__DIR__ . '/.student/student_history_helper.php');
		logStudentModification($dtb, (string) $fiche['student_id'], $id, 'image_student', $ancienne, $photo['nom'], $compte, 'image', 'Photo de profil depuis la retouche photo');
	}
	$fiche[$t['colonne']] = $photo['nom'];
	rppRepondre(['ok' => true, 'personne' => rppPersonne($type, $fiche)]);
}

rppRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
