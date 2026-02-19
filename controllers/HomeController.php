<?php
/**
 * HomeController - Pages publiques (landing)
 * Routes: /, /formations, /admissions, /campus, /contact
 */
class HomeController extends BaseController {

    public function index() {
        $this->renderLanding('index.php');
    }

    public function formations() {
        $this->render('landing/pages/formations.php', ROOT_DIR . '/landing/pages');
    }

    public function admissions() {
        $this->render('landing/pages/admissions.php', ROOT_DIR . '/landing/pages');
    }

    public function campus() {
        $this->render('landing/pages/campus.php', ROOT_DIR . '/landing/pages');
    }

    public function contact() {
        $this->render('landing/pages/contact.php', ROOT_DIR . '/landing/pages');
    }
}
