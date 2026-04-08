<?php
/**
 * TeacherController - Pages enseignants
 * Routes: /teacher/dashboard
 */
class TeacherController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function dashboard() {
        $this->renderSrc('teacher.dashboard.php');
    }
}
