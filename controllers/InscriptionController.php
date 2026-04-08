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
}
