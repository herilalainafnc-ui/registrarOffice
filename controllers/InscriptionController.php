<?php
/**
 * InscriptionController - Pages d'inscription
 * Routes: /inscription
 */
class InscriptionController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function index() {
        $this->requireLevelAccess(6);
        $this->renderInscription('inscription.php');
    }

    /**
     * Les inscriptions tardives (bulletin academique, § 334) : le calendrier
     * des inscriptions de chaque session, et chaque inscription avec sa date,
     * son heure, son agent, son retard et sa penalite
     * (src/inscriptions.tardives.php). Niveaux 1 a 4 ; le calendrier et les
     * exonerations : niveaux 1 a 3 (verifie par la page).
     */
    public function tardives() {
        $this->requireAnyLevel([1, 2, 3, 4], "Les inscriptions tardives sont réservées au registrariat et à la comptabilité.");
        $this->renderSrc('inscriptions.tardives.php');
    }
}
