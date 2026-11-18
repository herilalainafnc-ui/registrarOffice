<?php
/**
 * FinanceController - Pages de gestion financière
 * Routes: /finance, /finance/save, /finance/insert, /finance/authorized-reinscription, /finance/echeances
 */
class FinanceController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function index() {
        $this->requireLevelAccess(3);
        $this->renderSrc('gestion_finance.php');
    }

    public function accountBalance() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/account_balance.php');
    }

    public function accountBalanceUpdate() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/account_balance_update.php');
    }

    public function save() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/save_finance.php');
    }

    public function insert() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/insert_finance.php');
    }

    /** Les dates limites de chaque tranche, par session (src/finance/echeances.php). */
    public function echeances() {
        $this->requireLevelAccess(3);
        $this->renderSrc('finance/echeances.php');
    }

    public function authorizedReinscription() {
        $this->requireAnyLevel([1, 3, 4]);
        $this->renderSrc('finance/authorized_reinscription.php');
    }
}
