<?php
/* =============================================================================
   Messagerie : envoi et lecture des fichiers (photos, videos, vocaux, documents).

   POST action=debut    nom, taille, duree (ms, vocal) -> id, jeton, taille d'un morceau
   POST action=morceau  id, jeton, position, morceau (fichier) -> octets recus ;
                        au dernier morceau, le fichier est verifie et pret
   GET  id=…            le fichier, si le demandeur peut le voir
        &apercu=1       la vignette d'une photo
        &telecharger=1  en piece jointe plutot qu'affiche

   Les fichiers sont dans data/messagerie/ : nginx renvoie 404 sur /data/ et
   Apache refuse tout (data/.htaccess). Ils ne sortent que par ici, apres
   msgFichierAccessible(). Les plages d'octets (Range) sont servies : une
   video se lit et se parcourt sans tout telecharger.
   ========================================================================== */
require_once __DIR__ . '/../data/messagerie.php';
msgSessionLecture();
require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/middleware.php';
require_once __DIR__ . '/../data/guard.ajax.php';
refuserRequeteCroisee();

$moi = msgMoi($dtb);
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function msgFichierJson(array $charge, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------ envoi */

if ($methode === 'POST') {
    if (!$moi) {
        msgFichierJson(['ok' => false, 'session_expiree' => true, 'message' => 'Votre session a expiré. Reconnectez-vous pour continuer.'], 401);
    }
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        msgFichierJson(['ok' => false, 'csrf_expire' => true, 'message' => 'Jeton de sécurité expiré.'], 400);
    }
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'debut') {
            msgFichierJson(['ok' => true] + msgFichierDebut($dtb, $moi, (string) ($_POST['nom'] ?? ''),
                (int) ($_POST['taille'] ?? 0), isset($_POST['duree']) ? (int) $_POST['duree'] : null));
        }
        if ($action === 'morceau') {
            $m = $_FILES['morceau'] ?? null;
            if (!$m || ($m['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($m['tmp_name'])) {
                msgFichierJson(['ok' => false, 'message' => 'Morceau non reçu.'], 400);
            }
            msgFichierJson(['ok' => true, 'fichier' => msgFichierMorceau($dtb, $moi, (int) ($_POST['id'] ?? 0),
                (string) ($_POST['jeton'] ?? ''), (int) ($_POST['position'] ?? -1), $m['tmp_name'])]);
        }
        msgFichierJson(['ok' => false, 'message' => 'Action inconnue.'], 400);
    } catch (MessagerieRefus $e) {
        if (str_starts_with($e->getMessage(), 'reprendre:')) {
            msgFichierJson(['ok' => false, 'reprendre' => (int) substr($e->getMessage(), 10), 'message' => 'Reprise de l\'envoi.'], 409);
        }
        msgFichierJson(['ok' => false, 'message' => $e->getMessage()], $e->statut === 403 ? 400 : $e->statut);
    } catch (Throwable $e) {
        error_log('Messagerie (fichier) : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
        msgFichierJson(['ok' => false, 'message' => 'Erreur du serveur pendant l\'envoi.'], 500);
    }
}

/* ---------------------------------------------------------------- lecture */

$introuvable = function (int $code = 404) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $code === 401 ? 'Session expirée.' : 'Fichier introuvable.';
    exit;
};
if (!$moi) {
    $introuvable(401);
}
$f = msgFichierAccessible($dtb, $moi, (int) ($_GET['id'] ?? 0));
if (!$f || $f['statut'] !== 'pret' || str_contains($f['chemin'], '..')) {
    $introuvable();
}
$apercu = !empty($_GET['apercu']) && (int) $f['apercu'] === 1;
$chemin = msgDossierFichiers() . '/' . $f['chemin'] . ($apercu ? '.apercu' : '');
if (!is_file($chemin)) {
    $introuvable();
}

$taille = (int) filesize($chemin);
$mime = $apercu ? ((new finfo(FILEINFO_MIME_TYPE))->file($chemin) ?: 'image/jpeg') : $f['mime'];
$etag = '"' . $f['jeton'] . ($apercu ? 'a' : '') . '-' . $taille . '"';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Accept-Ranges: bytes');
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

$affichable = in_array($mime, MSG_MIME_AFFICHABLES, true) && empty($_GET['telecharger']);
$nom = $f['nom'];
$ascii = preg_replace('~[^A-Za-z0-9._ -]+~', '_', $nom) ?: 'fichier';
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($affichable ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nom));
if ($mime !== 'application/pdf') {
    /* Ouvert seul dans un onglet, le fichier ne peut rien executer. */
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
}

/* Plage demandee : « bytes=debut-fin », « bytes=debut- » ou « bytes=-n » */
$debut = 0;
$fin = $taille - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('~^bytes=(\d*)-(\d*)$~', trim($_SERVER['HTTP_RANGE']), $r) && ($r[1] !== '' || $r[2] !== '')) {
    if ($r[1] === '') {
        $debut = max(0, $taille - (int) $r[2]);
    } else {
        $debut = (int) $r[1];
        $fin = $r[2] !== '' ? min((int) $r[2], $taille - 1) : $taille - 1;
    }
    if ($debut > $fin || $debut >= $taille) {
        http_response_code(416);
        header('Content-Range: bytes */' . $taille);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $debut . '-' . $fin . '/' . $taille);
}
$longueur = $fin - $debut + 1;
header('Content-Length: ' . $longueur);
if ($methode === 'HEAD') {
    exit;
}

@set_time_limit(0);
while (ob_get_level() > 0) {
    ob_end_clean();
}
$flux = fopen($chemin, 'rb');
fseek($flux, $debut);
$reste = $longueur;
while ($reste > 0 && !feof($flux) && !connection_aborted()) {
    $bloc = fread($flux, (int) min(262144, $reste));
    if ($bloc === false || $bloc === '') {
        break;
    }
    echo $bloc;
    $reste -= strlen($bloc);
    flush();
}
fclose($flux);
