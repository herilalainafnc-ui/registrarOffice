<?php
/**
 * AJAX endpoint: recherche d'étudiants dans tbl_2024_etudiant
 * Utilisé par les modals d'ajout/édition d'utilisateurs
 * Retourne JSON : [{student_id, student_nom, student_prenom, etude_envisage}, ...]
 */

header('Content-Type: application/json; charset=utf-8');

// Charger la connexion DB
$rootPath = dirname(__DIR__, 2);
if (file_exists($rootPath . '/data/backdb.php')) {
    require_once $rootPath . '/data/backdb.php';
} elseif (file_exists(__DIR__ . '/../../data/backdb.php')) {
    require_once __DIR__ . '/../../data/backdb.php';
}

if (!isset($dtb)) {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $search = '%' . $q . '%';
    
    // Déterminer les colonnes disponibles
    $columns = ['student_id', 'student_nom', 'student_prenom'];
    $hasEtude = false;
    
    try {
        $colStmt = $dtb->query("SHOW COLUMNS FROM tbl_2024_etudiant");
        $allCols = [];
        while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            $allCols[] = $col['Field'];
        }
        if (in_array('etude_envisage', $allCols)) {
            $hasEtude = true;
            $columns[] = 'etude_envisage';
        }
    } catch (PDOException $e) {
        // Ignorer, utiliser les colonnes par défaut
    }
    
    $selectCols = implode(', ', $columns);
    
    $sql = "SELECT {$selectCols} FROM tbl_2024_etudiant 
            WHERE student_id LIKE :q1 
               OR student_nom LIKE :q2 
               OR student_prenom LIKE :q3
            ORDER BY student_nom ASC
            LIMIT 15";
    
    $stmt = $dtb->prepare($sql);
    $stmt->execute([
        'q1' => $search,
        'q2' => $search,
        'q3' => $search
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // S'assurer que etude_envisage existe dans le résultat
    if (!$hasEtude) {
        foreach ($results as &$row) {
            $row['etude_envisage'] = '';
        }
    }
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur de recherche']);
}
