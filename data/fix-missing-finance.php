<?php
/**
 * Script de correction : Insère les lignes manquantes dans t_2024_etudiant_finace
 * pour les étudiants qui ont une inscription (t_2024_inscription_session) 
 * mais pas de ligne finance correspondante.
 * 
 * Usage : php fix-missing-finance.php [--dry-run]
 *   --dry-run : Affiche les corrections sans les appliquer
 */

require_once __DIR__ . '/backdb.php';

$dryRun = in_array('--dry-run', $argv ?? []);

echo "=== Correction des lignes finance manquantes ===\n";
echo "Mode : " . ($dryRun ? "DRY-RUN (simulation)" : "EXECUTION REELLE") . "\n\n";

// Trouver toutes les inscriptions sans ligne finance correspondante (depuis le 09 mars 2026)
$sql = "SELECT ins.student_id, ins.session_id, ins.etude_mention, ins.status, ins.niveau_std, 
               ins.nbr_semester, ins.annee_scolaire, ins.date_entry,
               std.graduated,
               s.session_semester, s.session_year
        FROM t_2024_inscription_session ins
        INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
        INNER JOIN t_2023_session s ON ins.session_id = s.session_id
        LEFT JOIN t_2024_etudiant_finace fin ON ins.student_id = fin.student_id AND ins.session_id = fin.session_id
        WHERE ins.date_entry >= '2026-03-09' AND fin.id IS NULL
        ORDER BY ins.session_id, ins.student_id";

$missing = $dtb->query($sql);
$rows = $missing->fetchAll(PDO::FETCH_ASSOC);

echo "Lignes manquantes trouvées : " . count($rows) . "\n\n";

$inserted = 0;
$skipped = 0;
$errors = [];

foreach ($rows as $row) {
    $student_id = $row['student_id'];
    $session_id = $row['session_id'];
    $status = $row['status'];
    $etude_mention = $row['etude_mention'];
    $level = $row['niveau_std'];
    $nbr_semester = $row['nbr_semester'];
    $annee_scolaire = $row['annee_scolaire'];
    $date_entry = $row['date_entry'];
    $graduated = $row['graduated'];

    // Chercher la configuration financière
    $configStmt = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence
        WHERE std_status = :status AND std_mention = :mention AND level = :level AND semester = :semester');
    $configStmt->execute([
        'status' => $status,
        'mention' => $etude_mention,
        'level' => $level,
        'semester' => $nbr_semester
    ]);
    $result_finance = $configStmt->fetch(PDO::FETCH_ASSOC);

    if (!$result_finance) {
        $msg = "  [SKIP] #$student_id session=$session_id - Config finance introuvable ($status/$etude_mention/L$level/Sem$nbr_semester)";
        echo $msg . "\n";
        $errors[] = $msg;
        $skipped++;
        continue;
    }

    // Calcul des coûts (même logique que generate.student.php)
    $cout_fraix_generaux = floatval($result_finance['frais_generaux']);
    $nbr_day = intval($result_finance['nb_jours_semestre']);
    $cout_costume = $result_finance['frais_costume'];
    $cout_frais_graduation = ($graduated == 1) ? floatval($result_finance['frais_graduation']) : 0;

    // Voyage d'étude
    if ($nbr_semester == 1) {
        $cout_voyage = $result_finance['frais_voyage'];
    } else {
        $checkSem1 = $dtb->prepare('SELECT COUNT(*) as cnt FROM t_2024_inscription_session ins 
            INNER JOIN t_2023_session ses ON ins.session_id = ses.session_id 
            WHERE ins.student_id = :student_id AND ses.session_year = :annee_scolaire AND ses.session_semester = 1');
        $checkSem1->execute(['student_id' => $student_id, 'annee_scolaire' => $annee_scolaire]);
        $hasSem1 = $checkSem1->fetch();

        if ($hasSem1['cnt'] > 0) {
            $cout_voyage = $result_finance['frais_voyage'];
        } else {
            $cout_voyage = floatval($result_finance['frais_voyage']) * 2;
        }
    }

    // Fond dépôt dortoir
    if ($status == "Externe" || $status == "Bungalow") {
        $cout_fondDepot_dortoir = $result_finance['fond_depot'];
    } else {
        $cout_fondDepot_dortoir = 0;
    }

    // Logement
    $cout_logement = floatval($result_finance['dortoir']) * $nbr_day;

    echo "  [" . ($dryRun ? "SIMUL" : "INSERT") . "] #$student_id session=$session_id ($etude_mention, $status, L$level)";
    echo " -> frais=$cout_fraix_generaux, logement=$cout_logement, voyage=$cout_voyage\n";

    if (!$dryRun) {
        try {
            $insertStmt = $dtb->prepare('INSERT INTO t_2024_etudiant_finace(
                student_id, session_id, mention, level, status,
                cout_logement, cout_fraix_generaux, cout_fondDepot_dortoir,
                cout_frais_graduation, cout_costume, cout_voyage, date_entry
            ) VALUES (
                :student_id, :session_id, :mention, :level, :status,
                :cout_logement, :cout_fraix_generaux, :cout_fondDepot_dortoir,
                :cout_frais_graduation, :cout_costume, :cout_voyage, :date_entry
            )');

            $insertStmt->execute([
                'student_id' => $student_id,
                'session_id' => $session_id,
                'mention' => $etude_mention,
                'level' => $level,
                'status' => $status,
                'cout_logement' => $cout_logement,
                'cout_fraix_generaux' => $cout_fraix_generaux,
                'cout_fondDepot_dortoir' => $cout_fondDepot_dortoir,
                'cout_frais_graduation' => $cout_frais_graduation,
                'cout_costume' => $cout_costume,
                'cout_voyage' => $cout_voyage,
                'date_entry' => $date_entry
            ]);
            $inserted++;
        } catch (PDOException $e) {
            $msg = "  [ERREUR] #$student_id session=$session_id : " . $e->getMessage();
            echo $msg . "\n";
            $errors[] = $msg;
        }
    } else {
        $inserted++;
    }
}

echo "\n=== RÉSULTAT ===\n";
echo "Insérées : $inserted\n";
echo "Ignorées (config absente) : $skipped\n";
if (count($errors) > 0) {
    echo "\nErreurs/Avertissements :\n";
    foreach ($errors as $err) {
        echo "  $err\n";
    }
}

if ($dryRun) {
    echo "\n⚠ Mode DRY-RUN : aucune modification n'a été faite. Relancez sans --dry-run pour appliquer.\n";
}
