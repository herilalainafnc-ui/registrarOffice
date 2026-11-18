<?php
/**
 * WorkEducationController - Contrôleur du Work Education
 * Routes: /work-education
 *
 * La tache de Work Education de chaque etudiant, session par session
 * (src/work-education.php, donnees : data/work.education.php). Elle
 * s'imprime sur la fiche d'inscription.
 *
 * Le responsable du Work Education (niveau 9, dont c'est la seule page),
 * l'administration et le registrariat (niveaux 1 a 3).
 */
class WorkEducationController extends BaseController {

    public function index() {
        $this->requireAuthenticated();
        $this->requireAnyLevel([1, 2, 3, ROLE_WORK_EDUCATION],
            "Cette page est réservée au responsable du Work Education et au registrariat.");
        $this->renderSrc('work-education.php');
    }
}
