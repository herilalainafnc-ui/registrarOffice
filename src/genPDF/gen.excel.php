<?php
/**
 * Export Excel - Liste des étudiants
 * Génère un fichier Excel (.xlsx) ou CSV compatible Excel
 */

require '../../data/backdb.php';

// PhpSpreadsheet classes (utilisées si disponibles)
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Récupération des paramètres
$exportation = $_POST['exportation'] ?? 'general';
$new_student = isset($_POST['new_student']) ? true : false;
$types = $_POST['types'] ?? 'TOUT';
$anneescolaire = $_POST['anneescolaire'] ?? '';
$annee_etude = $_POST['annee_etude'] ?? 'tout';
$semestre = $_POST['semestre'] ?? '1';

// Colonnes à inclure
$col_matricule = isset($_POST['col_matricule']);
$col_nom = isset($_POST['col_nom']);
$col_prenom = isset($_POST['col_prenom']);
$col_sexe = isset($_POST['col_sexe']);
$col_datenaissance = isset($_POST['col_datenaissance']);
$col_mention = isset($_POST['col_mention']);
$col_niveau = isset($_POST['col_niveau']);
$col_email = isset($_POST['col_email']);
$col_telephone = isset($_POST['col_telephone']);
$col_adresse = isset($_POST['col_adresse']);
$col_status = isset($_POST['col_status']);
$col_religion = isset($_POST['col_religion']);

// Construction de la requête SQL
$sql = "SELECT 
    e.student_id,
    e.student_nom,
    e.student_prenom,
    e.sex,
    e.dateNaissance,
    e.etude_envisage,
    e.annee_etude,
    e.student_email,
    e.student_tel,
    e.student_adresse,
    e.status,
    e.religion,
    e.new_student,
    e.annee_scolaire
FROM tbl_2024_etudiant e
WHERE e.annee_scolaire = :anneescolaire";

// Filtres supplémentaires
$params = ['anneescolaire' => $anneescolaire];

// Filtre par mention
if ($types != 'TOUT') {
    $sql .= " AND e.etude_envisage = :mention";
    $params['mention'] = $types;
}

// Filtre par niveau
if ($annee_etude != 'tout') {
    $sql .= " AND e.annee_etude = :niveau";
    $params['niveau'] = $annee_etude;
}

// Filtre nouveaux étudiants
if ($new_student) {
    $sql .= " AND e.new_student = '1'";
}

// Filtre par type d'exportation
switch ($exportation) {
    case 'internat':
        $sql .= " AND e.status = 'Interne'";
        break;
    case 'abnment':
        $sql .= " AND e.abonment = '1'";
        break;
    case 'adventiste':
        $sql .= " AND e.religion = 'Adventiste'";
        break;
    case 'non_adventiste':
        $sql .= " AND e.religion != 'Adventiste'";
        break;
}

$sql .= " ORDER BY e.student_nom, e.student_prenom";

// Exécution de la requête
$stmt = $dtb->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Génération du nom de fichier
$filename = 'Liste_Etudiants_' . str_replace(' ', '_', $types) . '_' . str_replace(' - ', '-', $anneescolaire) . '_' . date('Y-m-d');

// Construction des en-têtes
$headers = [];
if ($col_matricule) $headers[] = 'Matricule';
if ($col_nom) $headers[] = 'Nom';
if ($col_prenom) $headers[] = 'Prénom';
if ($col_sexe) $headers[] = 'Sexe';
if ($col_datenaissance) $headers[] = 'Date de naissance';
if ($col_mention) $headers[] = 'Mention';
if ($col_niveau) $headers[] = 'Niveau';
if ($col_email) $headers[] = 'Email';
if ($col_telephone) $headers[] = 'Téléphone';
if ($col_adresse) $headers[] = 'Adresse';
if ($col_status) $headers[] = 'Statut';
if ($col_religion) $headers[] = 'Religion';

// Fonction pour convertir le niveau en texte
function getNiveauText($niveau) {
    $niveaux = [
        '1' => 'Licence 1',
        '2' => 'Licence 2',
        '3' => 'Licence 3',
        '4' => 'Master 1',
        '5' => 'Master 2',
        '10' => 'Classe spéciale'
    ];
    return $niveaux[$niveau] ?? $niveau;
}

// Fonction pour nettoyer les données CSV
function cleanForCSV($str) {
    $str = str_replace('"', '""', $str);
    if (strpos($str, ',') !== false || strpos($str, '"') !== false || strpos($str, "\n") !== false) {
        $str = '"' . $str . '"';
    }
    return $str;
}

// Vérifier si PhpSpreadsheet est disponible
$usePhpSpreadsheet = false;
$spreadsheetPath = '../../vendor/autoload.php';

if (file_exists($spreadsheetPath)) {
    require $spreadsheetPath;
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $usePhpSpreadsheet = true;
    }
}

if ($usePhpSpreadsheet) {
    // Génération XLSX avec PhpSpreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Liste Étudiants');

    // En-têtes avec style
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }

    // Style des en-têtes
    $lastCol = chr(ord('A') + count($headers) - 1);
    $headerRange = 'A1:' . $lastCol . '1';
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Données
    $row = 2;
    foreach ($students as $student) {
        $col = 'A';
        if ($col_matricule) { $sheet->setCellValue($col++ . $row, $student['student_id']); }
        if ($col_nom) { $sheet->setCellValue($col++ . $row, strtoupper($student['student_nom'])); }
        if ($col_prenom) { $sheet->setCellValue($col++ . $row, $student['student_prenom']); }
        if ($col_sexe) { $sheet->setCellValue($col++ . $row, $student['sex'] == '1' ? 'M' : 'F'); }
        if ($col_datenaissance) { $sheet->setCellValue($col++ . $row, $student['dateNaissance']); }
        if ($col_mention) { $sheet->setCellValue($col++ . $row, $student['etude_envisage']); }
        if ($col_niveau) { $sheet->setCellValue($col++ . $row, getNiveauText($student['annee_etude'])); }
        if ($col_email) { $sheet->setCellValue($col++ . $row, $student['student_email']); }
        if ($col_telephone) { $sheet->setCellValue($col++ . $row, $student['student_tel']); }
        if ($col_adresse) { $sheet->setCellValue($col++ . $row, $student['student_adresse']); }
        if ($col_status) { $sheet->setCellValue($col++ . $row, $student['status']); }
        if ($col_religion) { $sheet->setCellValue($col++ . $row, $student['religion']); }
        $row++;
    }

    // Bordures pour toutes les données
    $dataRange = 'A1:' . $lastCol . ($row - 1);
    $sheet->getStyle($dataRange)->applyFromArray([
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);

    // Téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} else {
    // Fallback: Génération CSV compatible Excel
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');

    // BOM pour UTF-8 (compatibilité Excel)
    echo "\xEF\xBB\xBF";

    // En-têtes
    echo implode(';', array_map('cleanForCSV', $headers)) . "\n";

    // Données
    foreach ($students as $student) {
        $row = [];
        if ($col_matricule) $row[] = cleanForCSV($student['student_id']);
        if ($col_nom) $row[] = cleanForCSV(strtoupper($student['student_nom']));
        if ($col_prenom) $row[] = cleanForCSV($student['student_prenom']);
        if ($col_sexe) $row[] = $student['sex'] == '1' ? 'M' : 'F';
        if ($col_datenaissance) $row[] = cleanForCSV($student['dateNaissance']);
        if ($col_mention) $row[] = cleanForCSV($student['etude_envisage']);
        if ($col_niveau) $row[] = cleanForCSV(getNiveauText($student['annee_etude']));
        if ($col_email) $row[] = cleanForCSV($student['student_email']);
        if ($col_telephone) $row[] = cleanForCSV($student['student_tel']);
        if ($col_adresse) $row[] = cleanForCSV($student['student_adresse']);
        if ($col_status) $row[] = cleanForCSV($student['status']);
        if ($col_religion) $row[] = cleanForCSV($student['religion']);
        
        echo implode(';', $row) . "\n";
    }
}

exit;
?>
