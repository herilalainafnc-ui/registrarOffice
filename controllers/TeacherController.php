<?php
/**
 * TeacherController - Pages enseignants
 * Routes: /teacher/dashboard
 */
class TeacherController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
        $this->refuserEtudiant();
    }

    public function dashboard() {
        $this->renderSrc('teacher.dashboard.php');
    }

    /**
     * Remise des notes : la seule voie par laquelle un enseignant pose des
     * notes. La page elle-meme verifie, cours par cours, qu'il en est bien
     * l'enseignant — les identifiants de cours se devinent dans l'URL.
     *
     * Ouverte aussi aux niveaux 1 a 3, qui doivent pouvoir regarder une feuille
     * sans se faire passer pour son auteur.
     */
    public function remiseNotes() {
        /* Page gardee hors ligne par /sw.js : la copie est rangee au nom de ce compte (jamais montree a un autre). */
        header('X-Utilisateur: ' . (int) ($_SESSION['user_id'] ?? 0));
        $this->renderSrc('teacher.remise-notes.php');
    }

    /**
     * Registre de presence (Bulletin academique, §472 a §476). Meme regle
     * d'acces que la remise : la page verifie cours par cours que
     * l'enseignant en est le titulaire ; les niveaux 1 a 3 consultent.
     */
    public function presence() {
        /* Page gardee hors ligne par /sw.js : la copie est rangee au nom de ce compte (jamais montree a un autre). */
        header('X-Utilisateur: ' . (int) ($_SESSION['user_id'] ?? 0));
        $this->renderSrc('teacher.presence.php');
    }
}
