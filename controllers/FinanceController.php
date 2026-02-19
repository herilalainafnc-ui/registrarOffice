<?php
/**
 * FinanceController - Pages de gestion financière
 * Routes: /finance, /finance/save, /finance/insert
 */
class FinanceController extends BaseController {

    public function index() {
        $this->renderSrc('gestion_finance.php');
    }

    public function save() {
        $this->renderSrc('finance/save_finance.php');
    }

    public function insert() {
        $this->renderSrc('finance/insert_finance.php');
    }
}
