<?php
/**
 * Export Excel - Liste des étudiants
 * Génère un fichier Excel (.xlsx), sinon un XML Excel 2003 compatible Excel
 */

/* Donnees personnelles des etudiants : registrariat seul. La route
   /export/students/xlsx le verifiait deja, mais le fichier repondait aussi a
   son adresse directe, sans connexion (constate le 08/10/2026). */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(3);

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

// Récupération de la mention et de la session
$mention_name = 'Tous les programmes';
$session_name = '';
$session_year = '';

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
$responseMode = $_POST['response'] ?? '';

// Même logique de sélection que l'export PDF (std.list.php)
$session_id = 0;
$sessionStmt = $dtb->prepare(
    "SELECT session_id, session_name, session_year
     FROM t_2023_session
     WHERE session_semester = :semester AND session_year = :year
     LIMIT 1"
);
$sessionStmt->execute([
    'semester' => $semestre,
    'year' => $anneescolaire,
]);
$sessionData = $sessionStmt->fetch(PDO::FETCH_ASSOC);
if ($sessionData) {
    $session_id = (int)$sessionData['session_id'];
    $session_name = $sessionData['session_name'];
    $session_year = $sessionData['session_year'];
}

// Récupération du nom de la mention
if ($types !== 'TOUT') {
    $mentionStmt = $dtb->prepare("SELECT filiere_description FROM filiere WHERE filiere_sigle = :sigle LIMIT 1");
    $mentionStmt->execute(['sigle' => $types]);
    $mentionData = $mentionStmt->fetch(PDO::FETCH_ASSOC);
    if ($mentionData) {
        $mention_name = $mentionData['filiere_description'];
    } else {
        $mention_name = $types;
    }
}

$students = [];
$groupedStudents = [];

if ($session_id > 0) {
    $mentions = [];

    if ($types !== 'TOUT') {
        $mentionListStmt = $dtb->prepare("SELECT filiere_sigle, filiere_description FROM filiere WHERE filiere_sigle = :sigle ORDER BY filiere_id");
        $mentionListStmt->execute(['sigle' => $types]);
        $mentions = $mentionListStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($mentions)) {
            $mentions[] = [
                'filiere_sigle' => $types,
                'filiere_description' => $mention_name,
            ];
        }
    } else {
        $mentionListStmt = $dtb->query("SELECT filiere_sigle, filiere_description FROM filiere ORDER BY filiere_id");
        $mentions = $mentionListStmt ? $mentionListStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    foreach ($mentions as $mentionItem) {
        $mentionSigle = (string)($mentionItem['filiere_sigle'] ?? '');
        $mentionLabel = (string)($mentionItem['filiere_description'] ?? $mentionSigle);

        if ($mentionSigle === '') {
            continue;
        }

        $sql = "SELECT
            ins.student_id,
            std.student_nom,
            std.student_prenom,
            std.sex,
            std.dateNaissance,
            std.etude_envisage,
            std.annee_etude,
            std.student_email,
            std.student_tel,
            std.student_adresse,
            ins.status,
            std.religion
        FROM t_2024_inscription_session ins
        INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        WHERE ins.session_id = :session_id
          AND ins.etude_mention = :mention
          AND (std.suspended IS NULL OR std.suspended != 1)
          AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)";

        $params = [
            'session_id' => $session_id,
            'mention' => $mentionSigle,
        ];

        // Filtre par niveau
        if ($annee_etude != 'tout') {
            $sql .= " AND std.annee_etude = :niveau";
            $params['niveau'] = $annee_etude;
        }

        // Filtre nouveaux étudiants
        if ($new_student) {
            $sql .= " AND ins.new_student = 1";
        }

        // Filtre par type d'exportation
        switch ($exportation) {
            case 'internat':
                $sql .= " AND ins.status = 'Interne'";
                break;
            case 'abnment':
                $sql .= " AND std.abonment = 1";
                break;
            case 'adventiste':
                $sql .= " AND (std.religion = 'Adventiste' OR std.religion = 'Adventiste du Septieme-jour')";
                break;
            case 'non_adventiste':
                $sql .= " AND std.religion != 'Adventiste' AND std.religion != 'Adventiste du Septieme-jour'";
                break;
        }

        $sql .= " GROUP BY ins.student_id ORDER BY std.annee_etude ASC, ins.student_id ASC";

        $stmt = $dtb->prepare($sql);
        $stmt->execute($params);
        $studentsByMention = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedStudents[] = [
            'mention_sigle' => $mentionSigle,
            'mention' => $mentionLabel,
            'students' => $studentsByMention,
        ];

        if (!empty($studentsByMention)) {
            $students = array_merge($students, $studentsByMention);
        }
    }
}

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

if ($responseMode === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'headers' => $headers,
        'students' => $students,
        'grouped_students' => $groupedStudents,
        'mention' => $mention_name,
        'session' => $session_name . ' ' . $session_year,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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

    if (empty($headers)) {
        $headers[] = 'Matricule';
        $col_matricule = true;
    }

    // Helpers pour les colonnes Excel au-dela de Z
    $columnFromIndex = static function ($index) {
        $result = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $result = chr(65 + $mod) . $result;
            $index = (int)(($index - 1) / 26);
        }
        return $result;
    };

    $lastColumnIndex = count($headers) + 1; // +1 pour la colonne No
    $lastCol = $columnFromIndex($lastColumnIndex);

    // Largeurs automatiques
    for ($colIndex = 1; $colIndex <= $lastColumnIndex; $colIndex++) {
        $sheet->getColumnDimension($columnFromIndex($colIndex))->setAutoSize(true);
    }

    $row = 1;
    foreach ($groupedStudents as $group) {
        if (empty($group['students'])) {
            continue;
        }

        // Ligne de section: Mention
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, 'Mention ' . ($group['mention'] ?? ''));
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $row++;

        // En-tetes
        $sheet->setCellValue('A' . $row, 'No');
        for ($i = 0; $i < count($headers); $i++) {
            $sheet->setCellValue($columnFromIndex($i + 2) . $row, $headers[$i]);
        }

        $headerRange = 'A' . $row . ':' . $lastCol . $row;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
        ]);
        $row++;

        $indexInMention = 1;
        foreach ($group['students'] as $student) {
            $currentCol = 1;
            $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $indexInMention);
            if ($col_matricule) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['student_id']); }
            if ($col_nom) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, strtoupper($student['student_nom'])); }
            if ($col_prenom) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['student_prenom']); }
            if ($col_sexe) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['sex'] == '1' ? 'M' : 'F'); }
            if ($col_datenaissance) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['dateNaissance']); }
            if ($col_mention) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['etude_envisage']); }
            if ($col_niveau) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, getNiveauText((string)$student['annee_etude'])); }
            if ($col_email) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['student_email']); }
            if ($col_telephone) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['student_tel']); }
            if ($col_adresse) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['student_adresse']); }
            if ($col_status) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['status']); }
            if ($col_religion) { $sheet->setCellValue($columnFromIndex($currentCol++) . $row, $student['religion']); }

            $dataRange = 'A' . $row . ':' . $lastCol . $row;
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ],
            ]);

            $row++;
            $indexInMention++;
        }

        // Ligne vide entre mentions
        $row++;
    }

    // Cas sans donnees: afficher seulement les en-tetes
    if ($row === 1) {
        $sheet->setCellValue('A1', 'No');
        for ($i = 0; $i < count($headers); $i++) {
            $sheet->setCellValue($columnFromIndex($i + 2) . '1', $headers[$i]);
        }

        $headerRange = 'A1:' . $lastCol . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
        ]);
    }

    // Téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} else {
    // Fallback: XML Excel 2003, mieux reconnu par Excel que du HTML .xls
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
    header('Cache-Control: max-age=0');

    $xmlEscape = static function ($value) {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" "
        . "xmlns:o=\"urn:schemas-microsoft-com:office:office\" "
        . "xmlns:x=\"urn:schemas-microsoft-com:office:excel\" "
        . "xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" "
        . "xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";
    echo "  <Worksheet ss:Name=\"Liste Etudiants\">\n";
    echo "    <Table>\n";

    foreach ($groupedStudents as $group) {
        if (empty($group['students'])) {
            continue;
        }

        // Titre de section mention
        echo "      <Row>\n";
        echo '        <Cell><Data ss:Type="String">' . $xmlEscape('Mention ' . ($group['mention'] ?? '')) . "</Data></Cell>\n";
        echo "      </Row>\n";

        // En-tetes du tableau
        echo "      <Row>\n";
        echo '        <Cell><Data ss:Type="String">No</Data></Cell>' . "\n";
        foreach ($headers as $header) {
            echo '        <Cell><Data ss:Type="String">' . $xmlEscape($header) . "</Data></Cell>\n";
        }
        echo "      </Row>\n";

        $indexInMention = 1;
        foreach ($group['students'] as $student) {
            echo "      <Row>\n";
            echo '        <Cell><Data ss:Type="String">' . $xmlEscape($indexInMention) . "</Data></Cell>\n";
            if ($col_matricule) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['student_id']) . "</Data></Cell>\n"; }
            if ($col_nom) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape(strtoupper($student['student_nom'])) . "</Data></Cell>\n"; }
            if ($col_prenom) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['student_prenom']) . "</Data></Cell>\n"; }
            if ($col_sexe) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape(($student['sex'] == '1') ? 'M' : 'F') . "</Data></Cell>\n"; }
            if ($col_datenaissance) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['dateNaissance']) . "</Data></Cell>\n"; }
            if ($col_mention) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['etude_envisage']) . "</Data></Cell>\n"; }
            if ($col_niveau) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape(getNiveauText((string)$student['annee_etude'])) . "</Data></Cell>\n"; }
            if ($col_email) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['student_email']) . "</Data></Cell>\n"; }
            if ($col_telephone) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['student_tel']) . "</Data></Cell>\n"; }
            if ($col_adresse) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['student_adresse']) . "</Data></Cell>\n"; }
            if ($col_status) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['status']) . "</Data></Cell>\n"; }
            if ($col_religion) { echo '        <Cell><Data ss:Type="String">' . $xmlEscape($student['religion']) . "</Data></Cell>\n"; }
            echo "      </Row>\n";
            $indexInMention++;
        }

        // Ligne vide separatrice
        echo "      <Row></Row>\n";
    }

    if (empty($students)) {
        echo "      <Row>\n";
        echo '        <Cell><Data ss:Type="String">No</Data></Cell>' . "\n";
        foreach ($headers as $header) {
            echo '        <Cell><Data ss:Type="String">' . $xmlEscape($header) . "</Data></Cell>\n";
        }
        echo "      </Row>\n";
    }

    echo "    </Table>\n";
    echo "  </Worksheet>\n";
    echo "</Workbook>\n";
}

exit;
?>
