<?php
require __DIR__ . '/data/backdb.php';
$sid = 488;
$mentions = $dtb->query("SELECT filiere_sigle FROM filiere WHERE filiere_sigle != 'CPRE' AND filiere_sigle != 'EDUC'")->fetchAll(PDO::FETCH_COLUMN);
$total = 0;
foreach ($mentions as $m) {
    $sql = "SELECT
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 0 AND ins.new_student = 1 THEN ins.student_id END) AS RM_N,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 0 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS RM_A,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 1 AND ins.new_student = 1 THEN ins.student_id END) AS L1_N,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 1 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS L1_A,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 2 AND ins.new_student = 1 THEN ins.student_id END) AS L2_N,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 2 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS L2_A,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 3 AND ins.new_student = 1 THEN ins.student_id END) AS L3_N,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 3 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS L3_A,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 4 AND ins.new_student = 1 THEN ins.student_id END) AS M1_N,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 4 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS M1_A,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 5 AND ins.new_student = 1 THEN ins.student_id END) AS M2_N,
        COUNT(DISTINCT CASE WHEN ins.niveau_std = 5 AND (ins.new_student IS NULL OR ins.new_student <> 1) THEN ins.student_id END) AS M2_A
        FROM t_2024_inscription_session ins
        INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
        WHERE ins.session_id = :sid AND ins.etude_mention = :m
          AND (std.suspended IS NULL OR std.suspended != 1)
          AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)";
    $st = $dtb->prepare($sql);
    $st->execute(['sid' => $sid, 'm' => $m]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    foreach (['RM_N','RM_A','L1_N','L1_A','L2_N','L2_A','L3_N','L3_A','M1_N','M1_A','M2_N','M2_A'] as $k) {
        $total += (int)($r[$k] ?? 0);
    }
}
var_dump($total);
