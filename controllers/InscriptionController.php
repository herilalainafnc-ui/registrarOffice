<?php
/**
 * InscriptionController - Pages d'inscription
 * Routes: /inscription
 */
class InscriptionController extends BaseController {

    public function index() {
        $this->renderInscription('inscription.php');
    }
}
