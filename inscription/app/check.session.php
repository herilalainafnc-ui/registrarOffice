<?php
/**
 * Vérifie si une session existe déjà pour un étudiant avec le semestre et l'année spécifiés
 * Retourne JSON: { exists: boolean, session_name: string|null }
 */

require('../../data/backdb.php');

header('Content-Type: application/json');

if (!isset($_POST['student_id']) || !isset($_POST['semester']) || !isset($_POST['year'])) {
    echo json_encode(['exists' => false, 'error' => 'Paramètres manquants']);
    exit;
}

$student_id = htmlspecialchars($_POST['student_id']);
$semester = htmlspecialchars($_POST['semester']);
$annee = htmlspecialchars($_POST['year']);

try {
    // Chercher le session_id correspondant au semestre et à l'année dans t_2023_session
    $stmt = $dtb->prepare('SELECT session_id, session_name FROM t_2023_session WHERE session_name = ? AND session_year = ?');
    $stmt->execute([$semester, $annee]);
    $session_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session_info) {
        // Session non trouvée dans le catalogue
        echo json_encode(['exists' => false, 'session_found' => false, 'message' => 'Session non configurée dans le système']);
        exit;
    }
    
    $session_id = $session_info['session_id'];
    
    // Vérifier si l'étudiant a déjà EXACTEMENT cette session (même semestre + même année)
    $stmt2 = $dtb->prepare('SELECT id FROM t_2024_inscription_session WHERE student_id = ? AND session_id = ?');
    $stmt2->execute([$student_id, $session_id]);
    $existing = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier aussi si l'étudiant a une autre session pour la même année (pour afficher un message d'avertissement)
    $stmt3 = $dtb->prepare('SELECT ins.id, ins.session_id, sess.session_name 
        FROM t_2024_inscription_session ins 
        LEFT JOIN t_2023_session sess ON ins.session_id = sess.session_id 
        WHERE ins.student_id = ? AND ins.annee_scolaire = ? AND ins.session_id != ?');
    $stmt3->execute([$student_id, $annee, $session_id]);
    $otherSession = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode([
            'exists' => true, 
            'session_found' => true,
            'session_id' => $session_id,
            'session_name' => $session_info['session_name'],
            'message' => 'La session a déjà été créée pour ce semestre.'
        ]);
    } elseif ($otherSession) {
        // L'étudiant a une autre session pour cette année - il peut la remplacer
        echo json_encode([
            'exists' => false, 
            'session_found' => true,
            'session_id' => $session_id,
            'will_replace' => true,
            'old_session_name' => $otherSession['session_name'],
            'message' => 'Attention: Une inscription existe pour "' . $otherSession['session_name'] . '". Elle sera remplacée.'
        ]);
    } else {
        echo json_encode([
            'exists' => false, 
            'session_found' => true,
            'session_id' => $session_id,
            'message' => 'Aucune session pour ce semestre. Vous pouvez enregistrer.'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
