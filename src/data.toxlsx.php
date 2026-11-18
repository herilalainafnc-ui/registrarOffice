<?php
/**
 * Export HTML vers Excel.
 * Recoit du HTML (htmlContent) et renvoie un vrai .xlsx si possible.
 * Fallback: XML Excel 2003 (.xml) pour eviter l'alerte extension/format.
 *
 * Il ne lit aucune donnee (il convertit le tableau que la page lui envoie),
 * mais repondait a n'importe qui : connexion exigee, comme les impressions
 * dont il prend le relais (src/data.topdf.php).
 */
require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Methode non autorisee.";
    exit;
}

$htmlContent = isset($_POST['htmlContent']) ? (string)$_POST['htmlContent'] : '';
$rawFileName = isset($_POST['fileName']) ? (string)$_POST['fileName'] : '';

if ($htmlContent === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Aucune donnee HTML a exporter.";
    exit;
}

// Nettoyage basique de securite (retirer scripts/event handlers inline).
$htmlContent = preg_replace('#<script[^>]*>.*?</script>#is', '', $htmlContent);
$htmlContent = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $htmlContent);

// Nom de fichier robuste.
$baseName = trim($rawFileName) !== '' ? $rawFileName : ('export_' . date('Y-m-d_H-i-s'));
$baseName = preg_replace('/[^A-Za-z0-9_\-\. ]+/', '_', $baseName);
$baseName = trim($baseName, " .\t\n\r\0\x0B");
if ($baseName === '') {
    $baseName = 'export_' . date('Y-m-d_H-i-s');
}

// Essayer d'abord la generation XLSX native (sans alerte Excel).
$autoloadCandidates = [
    __DIR__ . '/../data/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if ($autoloadLoaded
    && class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')
    && class_exists('PhpOffice\\PhpSpreadsheet\\Reader\\Html')
    && class_exists('PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
    $reader->loadFromString($htmlContent, $spreadsheet);

    // Normaliser le titre de la feuille.
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Fallback: XML Excel 2003 (extension coherente -> pas d'alerte format/extension).
$xmlEscape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
};

$rows = [];
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
if ($loaded) {
    $tables = $dom->getElementsByTagName('table');
    foreach ($tables as $table) {
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $row = [];
            foreach ($tr->childNodes as $cellNode) {
                if (!$cellNode instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($cellNode->tagName);
                if ($tag !== 'td' && $tag !== 'th') {
                    continue;
                }
                $text = trim(preg_replace('/\s+/', ' ', $cellNode->textContent ?? ''));
                $row[] = $text;
            }
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
        // Ligne vide entre tableaux pour garder une separation visuelle.
        if (!empty($rows)) {
            $rows[] = [];
        }
    }
}
libxml_clear_errors();

if (empty($rows)) {
    $plain = trim(strip_tags($htmlContent));
    $rows[] = [$plain !== '' ? $plain : 'Export'];
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $baseName . '.xml"');
header('Pragma: no-cache');
header('Expires: 0');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" "
    . "xmlns:o=\"urn:schemas-microsoft-com:office:office\" "
    . "xmlns:x=\"urn:schemas-microsoft-com:office:excel\" "
    . "xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" "
    . "xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";
echo "  <Worksheet ss:Name=\"Export\">\n";
echo "    <Table>\n";

foreach ($rows as $row) {
    echo "      <Row>\n";
    if (empty($row)) {
        echo "        <Cell><Data ss:Type=\"String\"></Data></Cell>\n";
    } else {
        foreach ($row as $cell) {
            echo '        <Cell><Data ss:Type="String">' . $xmlEscape($cell) . "</Data></Cell>\n";
        }
    }
    echo "      </Row>\n";
}

echo "    </Table>\n";
echo "  </Worksheet>\n";
echo "</Workbook>\n";
exit;
?>
