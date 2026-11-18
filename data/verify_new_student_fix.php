<?php
require('backdb.php');

echo "Vérification des modifications\n";
echo str_repeat("=", 80) . "\n\n";

// Statistiques globales
$stats = $dtb->query('
SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN new_student = 1 THEN 1 ELSE 0 END) as nouveaux,
  SUM(CASE WHEN new_student = 0 THEN 1 ELSE 0 END) as anciens,
  SUM(CASE WHEN new_student IS NULL THEN 1 ELSE 0 END) as null_val
FROM tbl_2024_etudiant
')->fetch(PDO::FETCH_ASSOC);

echo "Statistiques des étudiants (tbl_2024_etudiant):\n";
echo "  Total: {$stats['total']}\n";
echo "  Nouveaux (new_student = 1): {$stats['nouveaux']}\n";
echo "  Anciens (new_student = 0): {$stats['anciens']}\n";
echo "  NULL: {$stats['null_val']}\n\n";

// Vérifier qu'il n'y a plus d'étudiants avec 2+ sessions et new_student = 1
$anomalies = $dtb->query('
SELECT COUNT(DISTINCT e.student_id) as count
FROM tbl_2024_etudiant e
INNER JOIN (
  SELECT student_id, COUNT(*) as nb
  FROM t_2024_inscription_session
  GROUP BY student_id
  HAVING COUNT(*) >= 2
) t ON e.student_id = t.student_id
WHERE e.new_student = 1
')->fetch(PDO::FETCH_ASSOC);

echo "Étudiants avec 2+ sessions et new_student = 1 (anomalies): {$anomalies['count']}\n";
echo "  Status: " . ($anomalies['count'] == 0 ? "✓ OK (aucune anomalie)" : "✗ PROBLÈME") . "\n\n";

// Afficher quelques exemples d'étudiants corrigés
echo "Exemples d'étudiants avec 4+ sessions (maintenant marqués comme anciens):\n";
$examples = $dtb->query('
SELECT e.student_id, e.student_nom, e.student_prenom, e.new_student, COUNT(ins.id) as nb_sessions
FROM tbl_2024_etudiant e
LEFT JOIN t_2024_inscription_session ins ON e.student_id = ins.student_id
GROUP BY e.student_id
HAVING COUNT(ins.id) >= 4
LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);

foreach ($examples as $ex) {
  $status = $ex['new_student'] == 1 ? 'NOUVEAU' : 'ANCIEN';
  echo "  - {$ex['student_id']}: {$ex['student_nom']} {$ex['student_prenom']} ({$ex['nb_sessions']} sessions) = {$status}\n";
}
echo "\n" . str_repeat("=", 80) . "\n";
?>
