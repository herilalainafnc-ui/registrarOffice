<?php
/**
 * FinanceController - Pages de gestion financière
 * Routes: /finance, /finance/save, /finance/insert, /finance/authorized-reinscription
 */
class FinanceController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function index() {
        $this->requireLevelAccess(3);
        $this->renderSrc('gestion_finance.php');
    }

    public function save() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/save_finance.php');
    }

    public function insert() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/insert_finance.php');
    }

    public function authorizedReinscription() {
        $this->requireAnyLevel([1, 3, 4]);
        $this->renderSrc('finance/authorized_reinscription.php');
    }
}
