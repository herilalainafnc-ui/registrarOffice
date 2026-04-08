<?php
/**
 * AdminController - Pages d'administration
 * Routes: /settings, /accounts/create, /news, /login-locations, /deans-list, etc.
 */
class AdminController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function settings() {
        $this->requireLevelAccess(3);
        $this->renderSrc('settings.php');
    }

    public function bulkEmail() {
        $this->requireLevelAccess(3);
        $this->renderSrc('bulk-email.php');
    }

    public function createAccount() {
        $this->requireLevelAccess(1);
        $this->renderSrc('creat.account.php');
    }

    public function news() {
        $this->requireLevelAccess(3);
        $this->renderSrc('admin.actus.php');
    }

    public function loginLocations() {
        $this->requireLevelAccess(3);
        $this->renderSrc('login-locations.php');
    }

    public function deansList() {
        $this->renderSrc('deans-list.php');
    }

    public function topStudents() {
        $this->renderSrc('meilleurs-etudiants.php');
    }

    public function sessionRankings() {
        $this->renderSrc('session-rankings.php');
    }

    public function sessions() {
        $this->requireLevelAccess(1);
        $this->renderSrc('sessions.php');
    }

    public function myAccount() {
        $this->renderSrc('my.account.php');
    }

    public function professor() {
        $this->renderSrc('prof.php');
    }

    public function course() {
        $this->renderSrc('cours.php');
    }

    public function reel() {
        $this->renderSrc('reel.php');
    }

    public function button() {
        $this->renderSrc('button.php');
    }

    public function verify() {
        $this->render('verifier/index.php', ROOT_DIR . '/verifier');
    }
}
