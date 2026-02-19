<?php
/**
 * TeacherController - Pages enseignants
 * Routes: /teacher/dashboard
 */
class TeacherController extends BaseController {

    public function dashboard() {
        $this->renderSrc('teacher.dashboard.php');
    }
}
