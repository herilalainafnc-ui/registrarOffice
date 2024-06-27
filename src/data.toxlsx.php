<?php
require '../data/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactoey;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function exportToExcel($htmlContent) {
    // Créer une nouvelle feuille de calcul
    $spreadsheet = new Spreadsheet();

    // Charger le contenu HTML dans la feuille de calcul
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
    $spreadsheet = $reader->loadFromString($htmlContent);

    // Créer un écrivain pour écrire dans un fichier Excel
    $writer = new Xlsx($spreadsheet);
    $fileName = 'document_name.xlsx';

    // Envoyer les en-têtes pour télécharger le fichier Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    // Écrire le fichier Excel dans la sortie
    $writer->save('php://output');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Générer le contenu HTML dynamique
    ob_start();
    // Inclure ou générer votre contenu dynamique ici
    // Par exemple, include 'votre_fichier_dynamique.php';
    include 'data.topdf.php';
    $htmlContent = ob_get_clean();

    // Appeler la fonction pour exporter en Excel
    exportToExcel($htmlContent);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export HTML to Excel</title>
</head>
<body>
    <form method="post" action="">
        <button type="submit">Export to Excel</button>
    </form>
</body>
</html>
