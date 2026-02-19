<?php
/**
 * DashboardController - Tableaux de bord et listes principales
 * Routes: /dashboard, /classrooms, /professors, /courses, /schedule
 */
class DashboardController extends BaseController {

    public function index() {
        $this->renderSrc('accueil.php');
    }

    public function classrooms() {
        $this->renderSrc('accueil.classroom.php');
    }

    public function professors() {
        $this->renderSrc('accueil.prof.php');
    }

    public function courses() {
        $this->renderSrc('accueil.cours.php');
    }

    public function schedule() {
        $this->renderSrc('accueil.emploi-temps.php');
    }
}
