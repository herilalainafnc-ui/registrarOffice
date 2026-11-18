<?php
/**
 * AuthController - Authentification
 * Routes: /login, /logout, /loading, /choisir-espace, /goodbye
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

    /* Une personne du personnel qui est aussi enseignante choisit son espace (src/choisir-espace.php). */
    public function choisirEspace() {
        $this->renderSrc('choisir-espace.php');
    }

    public function goodbye() {
        $this->renderSrc('goodbye.php');
    }
}
