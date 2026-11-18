<?php
/* =============================================================================
   Image d'une annonce — partagé par add.annonce.php et update.annonce.php

   Avant : le résultat de imagejpeg() n'était pas vérifié. Quand le serveur ne
   pouvait pas écrire dans app/uploads/annonces, l'annonce était quand même
   enregistrée avec le nom d'une image inexistante : publication « réussie »,
   image en 404 chez les étudiants, et aucun message nulle part.

   Maintenant : une image choisie mais non enregistrée arrête l'opération avec
   un message ; le nom n'est renvoyé que si le fichier existe bel et bien.

   L'image est ramenée à 1600 px au plus (côté le plus long), en JPEG 82 :
   une photo de téléphone (4000 px, plusieurs Mo) se chargeait telle quelle
   chez des étudiants souvent sur une connexion lente.
   ========================================================================== */

if (!function_exists('annonceEnregistrerImage')) {

    /** Message lisible pour une erreur d'envoi PHP (UPLOAD_ERR_*). */
    function annonceMessageEnvoi(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return "L'image dépasse la taille autorisée par le serveur. Choisissez une image plus légère.";
            case UPLOAD_ERR_PARTIAL:
                return "L'envoi de l'image a été interrompu. Réessayez.";
            default:
                return "Le serveur n'a pas pu recevoir l'image. Réessayez, ou enregistrez sans image.";
        }
    }

    /** Message si les dates d'une annonce ne vont pas, sinon null. */
    function annonceErreurDates(?string $publication, ?string $fin): ?string
    {
        foreach ([$publication, $fin] as $date) {
            if ($date !== null && $date !== '') {
                $lue = DateTime::createFromFormat('!Y-m-d', $date);
                if (!$lue || $lue->format('Y-m-d') !== $date) {
                    return 'Date illisible : choisissez-la dans le calendrier.';
                }
            }
        }
        if ($publication && $fin && $fin < $publication) {
            return "La fin d'affichage doit venir après la publication.";
        }
        return null;
    }

    /** Retire l'image d'une annonce : par son seul nom, jamais un chemin. */
    function annonceSupprimerImage(string $nom, string $dossier): void
    {
        $nom = basename($nom);
        if ($nom === '' || $nom === '.' || $nom === '..') {
            return;
        }
        $chemin = rtrim($dossier, '/') . '/' . $nom;
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }

    /**
     * Enregistre l'image téléversée dans $dossier (ré-encodée en JPEG par GD).
     * @return string|null nom du fichier créé, ou null si rien n'a été écrit
     */
    function annonceEnregistrerImage(string $tmp, string $nomOriginal, string $dossier): ?string
    {
        $dossier = rtrim($dossier, '/') . '/';
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true)) {
            error_log('Annonces : impossible de créer ' . $dossier);
            return null;
        }
        if (!is_writable($dossier)) {
            error_log('Annonces : le serveur ne peut pas écrire dans ' . $dossier);
            return null;
        }

        $base = 'annonce_' . time() . '_' . bin2hex(random_bytes(4));

        // Ré-encodage GD : neutralise un fichier piégé déguisé en image.
        if (function_exists('imagecreatefromstring')) {
            $source = @imagecreatefromstring((string) file_get_contents($tmp));
            if ($source) {
                /* 1600 px au plus, sur fond blanc : un PNG transparent ne vire
                   plus au noir en JPEG. */
                $l = imagesx($source);
                $h = imagesy($source);
                $echelle = min(1, 1600 / max($l, $h, 1));
                $l2 = max(1, (int) round($l * $echelle));
                $h2 = max(1, (int) round($h * $echelle));
                $toile = imagecreatetruecolor($l2, $h2);
                imagefill($toile, 0, 0, imagecolorallocate($toile, 255, 255, 255));
                imagecopyresampled($toile, $source, 0, 0, 0, 0, $l2, $h2, $l, $h);
                imagedestroy($source);
                $nom = $base . '.jpg';
                $ok = @imagejpeg($toile, $dossier . $nom, 82);
                imagedestroy($toile);
                if ($ok && is_file($dossier . $nom) && filesize($dossier . $nom) > 0) {
                    return $nom;
                }
                @unlink($dossier . $nom);
                error_log('Annonces : échec de l\'écriture de ' . $dossier . $nom);
                return null;
            }
        }

        // Repli sans GD (format que GD ne lit pas) : copie telle quelle.
        $ext = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $nom = $base . '.' . $ext;
        if (move_uploaded_file($tmp, $dossier . $nom) && is_file($dossier . $nom)) {
            return $nom;
        }
        error_log('Annonces : échec du déplacement vers ' . $dossier . $nom);
        return null;
    }
}
