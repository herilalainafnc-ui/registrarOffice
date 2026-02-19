<?php
/**
 * StudentController - Pages étudiants
 * Routes: /student, /student/home, /student/dashboard, /student/info, etc.
 */
class StudentController extends BaseController {

    public function show() {
        $this->renderSrc('student.php');
    }

    public function home() {
        $this->renderSrc('student.home.php');
    }

    public function dashboard() {
        $this->renderSrc('student.dashboard.php');
    }

    public function info() {
        $this->renderSrc('student.info.php');
    }

    public function news() {
        $this->renderSrc('student.actus.php');
    }

    public function quiz() {
        $this->renderSrc('student.quiz.php');
    }

    public function game() {
        $this->renderSrc('student.game.php');
    }

    public function transition() {
        $this->renderSrc('student.transition.php');
    }

    public function create() {
        $this->renderSrc('creat.student.php');
    }

    public function debug() {
        $this->renderSrc('debug.student.php');
    }
}
