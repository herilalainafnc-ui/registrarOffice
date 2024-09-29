<?php
require 'vendor/autoload.php';
require 'sheet.php'; // Inclure le fichier d'authentification

use Google\Service\Sheets;

function writeToGoogleSheet($spreadsheetId, $range, $values) {
    $client = getClient();
    $service = new Sheets($client);

    $body = new Sheets\ValueRange([
        'values' => $values
    ]);

    $params = [
        'valueInputOption' => 'RAW'
    ];

    $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
    printf("%d cellules mises à jour.", $result->getUpdates()->getUpdatedCells());
}

// Exemple d'utilisation
$spreadsheetId = 'votre_id_de_feuille'; // Remplacez par votre ID de feuille
$range = 'Feuille1!A1'; // Remplacez par le nom de votre feuille et la plage
$values = [
    ['Colonne1', 'Colonne2'], // En-têtes de colonnes
    ['Valeur1', 'Valeur2'], // Valeurs à écrire
];

writeToGoogleSheet($spreadsheetId, $range, $values);
?>

<?php
    // require '../data/vendor/autoload.php';

    // use PhpOffice\PhpSpreadsheet\Spreadsheet;
    // use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    // use PhpOffice\PhpSpreadsheet\Reader\Html;
    // use PhpOffice\PhpSpreadsheet\Style\Alignment;
    // use PhpOffice\PhpSpreadsheet\Style\Fill;
    // use PhpOffice\PhpSpreadsheet\Style\Border;
    // use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;




    // if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //     $htmlContent = $_POST['htmlContent'];

    //     $spreadsheet = new Spreadsheet();
    //     $reader = new Html();
    //     $reader->loadFromString($htmlContent, $spreadsheet);


    //         $sheet = $spreadsheet->getActiveSheet();

    //     // Appliquer des styles aux cellules
    //     $sheet->getStyle('A1:Z1')->applyFromArray([
    //         'font' => [
    //             'bold' => true,
    //             'color' => ['rgb' => 'FFFFFF'],
    //         ],
    //         'fill' => [
    //             'fillType' => Fill::FILL_SOLID,
    //             'startColor' => ['rgb' => '4F81BD'],
    //         ],
    //         'alignment' => [
    //             'horizontal' => Alignment::HORIZONTAL_CENTER,
    //         ],
    //         'borders' => [
    //             'allBorders' => [
    //                 'borderStyle' => Border::BORDER_THIN,
    //                 'color' => ['rgb' => '000000'],
    //             ],
    //         ],
    //     ]);

    //    // Ajouter et redimensionner une image
    //    // $drawing = new Drawing();
    //    // $drawing->setName('Logo');
    //    // $drawing->setDescription('Logo');
    //    // $drawing->setPath('../file/UAZ Official Black Logo.jpg'); // chemin de votre image
    //    // $drawing->setHeight(150); // ajuster la hauteur
    //    // $drawing->setWidth(150);  // ajuster la largeur
    //    // $drawing->setCoordinates('A1'); // positionner l'image
    //    // $drawing->setWorksheet($sheet);

    //     $writer = new Xlsx($spreadsheet);
    //     $fileName = 'export_' . date('Y-m-d_H-i-s') . '.xlsx';

    //     // Envoyer les en-têtes pour télécharger le fichier Excel
    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment;filename="' . $fileName . '"');
    //     header('Cache-Control: max-age=0');

    //     $writer->save('php://output');
    //     exit;
    // }
?>
