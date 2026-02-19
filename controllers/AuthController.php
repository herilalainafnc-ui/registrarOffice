<?php
/**
 * AuthController - Authentification
 * Routes: /login, /logout, /loading, /goodbye
 */
class AuthController extends BaseController {

    public function login() {
        $this->renderSrc('index.php');
    }

    public function logout() {
        $this->render('app/logout.php', ROOT_DIR . '/app');
    }

    public function loading() {
        $this->renderSrc('loading.php');
    }

    public function goodbye() {
        $this->renderSrc('goodbye.php');
    }
}
