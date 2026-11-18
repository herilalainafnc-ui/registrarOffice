<?php
/* =============================================================================
   Enregistrement des reglages de la vitrine

   Extrait de src/settings.vitrine.php pour servir DEUX appelants :

     app/vitrine-config.save.php   l'enregistrement en arriere-plan (le cas
                                   normal : la page ne se recharge pas)
     src/settings.vitrine.php      la soumission classique du formulaire, qui
                                   reste le chemin de repli sans JavaScript

   Une seule copie de la logique, donc : les regles de securite du
   televersement — liste blanche, vrai type MIME, re-encodage par GD — ne
   doivent pas exister en deux versions qui divergeront.
   ========================================================================== */

if (!function_exists('vitrineEnregistrer')) {
/**
 * Applique un lot de modifications a la table vitrine_config.
 *
 * @param PDO         $dtb
 * @param array       $post   champs texte ($_POST)
 * @param array       $files  televersements ($_FILES)
 * @param string|null $agent  qui modifie, pour la piste d'audit
 *
 * @return array{type:string,message:string,images:array<string,string>,traces:array<string,string>}
 *         type vaut 'ok', 'partiel' ou 'erreur'.
 *         images associe la cle au nouveau nom de fichier — l'appelant AJAX
 *         s'en sert pour rafraichir l'apercu sans recharger la page.
 *         traces associe la cle au texte « Modifié le … », pour la meme raison.
 */
function vitrineEnregistrer(PDO $dtb, array $post, array $files, ?string $agent) : array
{
    $images = [];
    $traces = [];

    try {
        $connus = $dtb->query('SELECT cle, type FROM vitrine_config')->fetchAll(PDO::FETCH_KEY_PAIR);
        $maj = $dtb->prepare(
            'UPDATE vitrine_config SET valeur = ?, modifie_le = NOW(), modifie_par = ? WHERE cle = ?');

        /* Horodatage pris une fois pour tout le lot : les traces renvoyees a
           l'ecran doivent afficher la meme minute que celle inscrite en base,
           sinon un enregistrement a cheval sur un changement de minute donne
           deux valeurs differentes pour la meme operation. */
        $quand = date('d/m/Y à H:i');
        $trace = 'Modifié le ' . $quand . ($agent !== null && $agent !== '' ? ' par ' . $agent : '');

        $nb = 0;
        foreach ($connus as $cle => $type) {

            /* Les images arrivent par televersement, pas par le champ texte :
               elles sont traitees a part, plus bas. */
            if ($type === 'image') { continue; }

            // Le nom de champ HTML ne supporte pas le point.
            $champ = str_replace('.', '__', $cle);
            if (!array_key_exists($champ, $post)) { continue; }

            $v = trim((string) $post[$champ]);
            if ($type === 'url' && $v !== '' && !preg_match('#^https?://#i', $v)) {
                $v = 'https://' . $v;
            }
            $maj->execute([$v !== '' ? $v : null, $agent, $cle]);
            $traces[$champ] = $trace;
            $nb++;
        }

        /* --- Televersements ------------------------------------------------
           Liste blanche d'extensions, verification du VRAI type MIME, puis
           re-encodage par GD. Le re-encodage est ce qui compte : il neutralise
           toute charge dissimulee dans les metadonnees d'une image par
           ailleurs valide. */
        $dossier = __DIR__ . '/../file/vitrine/';
        $extensionsOk = ['jpg', 'jpeg', 'png', 'webp'];
        $mimesOk = ['image/jpeg', 'image/png', 'image/webp'];
        $nbImages = 0;
        $refus = [];

        foreach ($files as $champ => $f) {
            if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) { continue; }
            $cle = str_replace('__', '.', $champ);
            if (!isset($connus[$cle]) || $connus[$cle] !== 'image') { continue; }

            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $extensionsOk, true)) {
                $refus[] = $f['name'] . ' (extension refusée)';
                continue;
            }
            if ($f['size'] > 6 * 1024 * 1024) {
                $refus[] = $f['name'] . ' (plus de 6 Mo)';
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            if (!in_array($mime, $mimesOk, true)) {
                $refus[] = $f['name'] . ' (ce n’est pas une image)';
                continue;
            }

            // Nom deduit de la cle : previsible, et jamais fourni par le client.
            $nom = preg_replace('/[^a-z0-9\-]/', '-', strtolower($cle)) . '.jpg';
            $source = @imagecreatefromstring(file_get_contents($f['tmp_name']));
            if (!$source) {
                $refus[] = $f['name'] . ' (image illisible)';
                continue;
            }

            // Bornage : une photo d'appareil moderne ferait 8 Mo sur la page.
            $l = imagesx($source); $h = imagesy($source);
            $max = 1600;
            if ($l > $max || $h > $max) {
                $r = min($max / $l, $max / $h);
                $nl = (int) round($l * $r); $nh = (int) round($h * $r);
                $red = imagecreatetruecolor($nl, $nh);
                imagecopyresampled($red, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);
                imagedestroy($source);
                $source = $red;
            }
            imagejpeg($source, $dossier . $nom, 86);
            imagedestroy($source);

            $maj->execute([$nom, $agent, $cle]);
            $images[$champ] = $nom;
            $traces[$champ] = $trace;
            $nbImages++;
        }

        $message = $nb . ' réglage(s) enregistré(s)'
            . ($nbImages ? ', ' . $nbImages . ' image(s) mise(s) à jour' : '') . '.';
        $type = 'ok';
        if ($refus) {
            $type = 'partiel';
            $message .= ' Refusé : ' . implode(', ', $refus) . '.';
        }

        return ['type' => $type, 'message' => $message, 'images' => $images, 'traces' => $traces];

    } catch (Throwable $e) {
        error_log('Reglages vitrine: ' . $e->getMessage());
        return [
            'type'    => 'erreur',
            'message' => "L'enregistrement n'a pas abouti.",
            'images'  => [],
            'traces'  => [],
        ];
    }
}
}
