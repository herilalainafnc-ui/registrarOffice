<?php
/**
 * MessagerieController - Messagerie instantanee
 * Route: /messagerie (tout compte connecte : personnel, enseignants, etudiants)
 *
 * La page choisit son cadre : celui du personnel (barre du haut, menu) ou
 * celui de l'espace etudiant. Regles : data/messagerie.php.
 */
class MessagerieController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function index() {
        $this->renderSrc('messagerie.php');
    }
}
