<?php
/**
 * ExportController - Export PDF, XLSX, impressions
 * Routes: /export, /export/pdf, /export/pdf-landscape, /export/xlsx, /export/gen-pdf, /sheet
 */
class ExportController extends BaseController {

    public function __construct() {
        $this->requireAuthenticated('/login');
    }

    public function index() {
        $this->requireLevelAccess(3);
        $this->renderSrc('export.php');
    }

    public function pdf() {
        $this->requireLevelAccess(3);
        $this->renderSrc('data.topdf.php');
    }

    public function pdfLandscape() {
        $this->requireLevelAccess(3);
        $this->renderSrc('data.topdf_paysage.php');
    }

    public function xlsx() {
        $this->requireLevelAccess(3);
        $this->renderSrc('data.toxlsx.php');
    }

    public function genPdf() {
        $this->requireLevelAccess(3);
        $this->renderSrc('gen.pdf.php');
    }

    public function sheet() {
        $this->requireLevelAccess(3);
        $this->renderSrc('sheet.php');
    }
}
