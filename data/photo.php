<?php
/* =============================================================================
   Photos televersees : comptes, enseignants, etudiants

   Les scripts d'envoi ne verifiaient que l'extension annoncee par le
   navigateur, et trois d'entre eux (app/.user/updateUser.php,
   app/.prof/updateImgProf.php, app/.prof/addProf.php) appelaient in_array()
   sans en lire le resultat : n'importe quel fichier etait accepte, y compris
   un script PHP que le serveur aurait ensuite execute depuis le dossier des
   photos. Le nom du fichier venait en outre du prenom saisi (updateUser.php,
   add.user.php) : un prenom contenant « ../ » ecrivait hors du dossier, et
   deux homonymes s'ecrasaient mutuellement leur portrait.

   Ici, sur le modele de app/.user/mon-compte.save.php :
   - le CONTENU est lu (getimagesize), pas l'extension ;
   - l'image est reencodee par GD en JPEG, ce qui ecarte tout contenu cache
     (du code glisse a la suite d'une vraie image, par exemple) ;
   - le nom est fabrique ici : prefixe de la fiche et suffixe aleatoire, en
     .jpg. Un suffixe different a chaque envoi evite aussi que le navigateur
     n'affiche l'ancienne photo gardee en cache.
   ========================================================================== */

if (!function_exists('photoEnregistrer')) {

if (!defined('PHOTO_TAILLE_MAX')) { define('PHOTO_TAILLE_MAX', 10 * 1024 * 1024); }   // octets
if (!defined('PHOTO_PIXELS_MAX')) { define('PHOTO_PIXELS_MAX', 40000000); }          // 40 mégapixels

/**
 * Enregistre la photo recue, reencodee en JPEG, sous un nom fabrique ici.
 *
 * @param array|null $fichier  une entree de $_FILES, ou null
 * @param string     $dossier  dossier de destination (cree au besoin)
 * @param string     $prefixe  debut du nom : l'identifiant de la fiche (« u12 », « t845 »…)
 * @param int        $carre    0 : proportions gardees, plus grand cote ramene a 1200 px ;
 *                             sinon : recadrage carre centre de ce cote (avatars ronds)
 * @return array{nom: ?string, erreur: ?string}  nom et erreur a null : aucun fichier envoye
 */
function photoEnregistrer(?array $fichier, string $dossier, string $prefixe, int $carre = 0) : array
{
    $refus = static fn (string $message) : array => ['nom' => null, 'erreur' => $message];

    $code = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fichier === null || $code === UPLOAD_ERR_NO_FILE || (string) ($fichier['name'] ?? '') === '') {
        return ['nom' => null, 'erreur' => null];
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return $refus('Photo trop lourde : 10 Mo au plus.');
    }
    $tmp = (string) ($fichier['tmp_name'] ?? '');
    if ($code !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
        return $refus("La photo n'a pas été reçue. Réessayez.");
    }
    if ((int) @filesize($tmp) > PHOTO_TAILLE_MAX) {
        return $refus('Photo trop lourde : 10 Mo au plus.');
    }

    // Le contenu, pas l'extension : un script renomme en .jpg n'est pas une image.
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return $refus("Ce fichier n'est pas une photo. Formats acceptés : JPG, PNG ou WebP.");
    }
    if ((int) $info[0] * (int) $info[1] > PHOTO_PIXELS_MAX) {
        return $refus('Photo trop grande : 40 mégapixels au plus.');
    }
    // Sans GD, on refuse plutot que de copier un fichier non reencode.
    if (!function_exists('imagecreatefromstring')) {
        return $refus('Le serveur ne peut pas traiter les photos pour le moment.');
    }
    $source = @imagecreatefromstring((string) file_get_contents($tmp));
    if (!$source) {
        return $refus('Photo illisible. Enregistrez-la en JPG ou en PNG et réessayez.');
    }

    // Photo de telephone : l'orientation est dans les metadonnees, que GD ignore.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle !== 0 && ($tournee = imagerotate($source, $angle, 0))) {
            imagedestroy($source);
            $source = $tournee;
        }
    }

    $l = imagesx($source);
    $h = imagesy($source);
    if ($carre > 0) {
        $cote = min($l, $h);
        [$sx, $sy, $sl, $sh] = [intdiv($l - $cote, 2), intdiv($h - $cote, 2), $cote, $cote];
        [$dl, $dh] = [$carre, $carre];
    } else {
        $echelle = min(1, 1200 / max($l, $h));
        [$sx, $sy, $sl, $sh] = [0, 0, $l, $h];
        [$dl, $dh] = [max(1, (int) round($l * $echelle)), max(1, (int) round($h * $echelle))];
    }
    $cible = imagecreatetruecolor($dl, $dh);
    // Fond blanc : la transparence d'un PNG deviendrait noire en JPEG.
    imagefill($cible, 0, 0, imagecolorallocate($cible, 255, 255, 255));
    imagecopyresampled($cible, $source, 0, 0, $sx, $sy, $dl, $dh, $sl, $sh);
    imagedestroy($source);

    $dossier = rtrim($dossier, '/\\');
    if (!is_dir($dossier)) { @mkdir($dossier, 0775, true); }
    $prefixe = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $prefixe), '-');
    if ($prefixe === '') { $prefixe = 'photo'; }
    $nom = $prefixe . '-' . bin2hex(random_bytes(4)) . '.jpg';

    $ok = imagejpeg($cible, $dossier . '/' . $nom, 88);
    imagedestroy($cible);
    if (!$ok) {
        return $refus("La photo n'a pas pu être enregistrée.");
    }
    @chmod($dossier . '/' . $nom, 0664);
    return ['nom' => $nom, 'erreur' => null];
}

}
