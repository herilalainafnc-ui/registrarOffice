<?php
/**
 * ExportController - Export PDF, XLSX, impressions
 * Routes: /export, /export/pdf, /export/pdf-landscape, /export/xlsx, /export/gen-pdf, /sheet
 */
class ExportController extends BaseController {

    public function index() {
        $this->renderSrc('export.php');
    }

    public function pdf() {
        $this->renderSrc('data.topdf.php');
    }

    public function pdfLandscape() {
        $this->renderSrc('data.topdf_paysage.php');
    }

    public function xlsx() {
        $this->renderSrc('data.toxlsx.php');
    }

    public function genPdf() {
        $this->renderSrc('gen.pdf.php');
    }

    public function sheet() {
        $this->renderSrc('sheet.php');
    }
}
