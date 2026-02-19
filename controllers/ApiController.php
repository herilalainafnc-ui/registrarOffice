<?php
/**
 * ApiController - Points d'entrée API (AJAX, services)
 * Routes: /api/google-auth, /api/schedule, /api/services/*, /api/data
 */
class ApiController extends BaseController {

    public function googleAuth() {
        $this->renderSrc('api/google-auth.php');
    }

    public function schedule() {
        $this->renderSrc('emploi-temps.api.php');
    }

    public function documentVerification() {
        $this->renderSrc('services/DocumentVerification.php');
    }

    public function matriculeLive() {
        $this->renderSrc('services/matricule.live.php');
    }

    public function parcoursLive() {
        $this->renderSrc('services/parcours.live.php');
    }

    public function parcoursAddCours() {
        $this->renderSrc('services/parcours.live.addCours.php');
    }

    public function data() {
        $this->render('data/data.php', ROOT_DIR . '/data');
    }
}
