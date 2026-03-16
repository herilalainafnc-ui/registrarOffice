<?php
/**
 * Script de correction : Insère les lignes manquantes dans t_2024_etudiant_finace
 * pour les étudiants qui ont une inscription dans t_2024_inscription_session
 * mais aucune ligne finance correspondante.
 * 
 * Date de référence : inscriptions depuis le 09 mars 2026
 * 
 * Usage : php fix_missing_finance.php [--dry-run]
 *   --dry-run : affiche ce qui serait inséré sans modifier la base
 */

require(__DIR__ . '/backdb.php');

$dryRun = in_array('--dry-run', $argv ?? []);

if ($dryRun) {
    echo "=== MODE DRY-RUN (aucune modification) ===\n\n";
}

// Trouver toutes les inscriptions sans ligne finance correspondante (depuis le 09/03/2026)
$sql = "SELECT ins.student_id, ins.session_id, ins.etude_mention, ins.status, 
               ins.niveau_std, ins.date_entry, ins.annee_scolaire,
               s.session_semester, s.session_year,
               std.graduated, std.status AS std_status_original
        FROM t_2024_inscription_session ins
        LEFT JOIN t_2024_etudiant_finace fin 
            ON ins.student_id = fin.student_id AND ins.session_id = fin.session_id
        INNER JOIN tbl_2024_etudiant std ON ins.student_id = std.student_id
        INNER JOIN t_2023_session s ON ins.session_id = s.session_id
        WHERE ins.date_entry >= '2026-03-09' AND fin.id IS NULL
        ORDER BY ins.session_id, ins.etude_mention, ins.student_id";

$missing = $dtb->query($sql);
$rows = $missing->fetchAll(PDO::FETCH_ASSOC);

$totalMissing = count($rows);
echo "Lignes finance manquantes trouvées : {$totalMissing}\n\n";

if ($totalMissing === 0) {
    echo "Aucune correction nécessaire.\n";
    exit(0);
}

$inserted = 0;
$skipped = 0;
$errors = [];

foreach ($rows as $row) {
    $student_id = $row['student_id'];
    $session_id = $row['session_id'];
    $etude_mention = $row['etude_mention'];
    $status = $row['status'];
    $level = $row['niveau_std'];
    $nbr_semester = $row['session_semester'];
    $graduated = intval($row['graduated']);
    $annee_scolaire = $row['session_year'];
    $date_entry = $row['date_entry'];

    // Chercher la configuration financière correspondante
    $findConfig = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence 
        WHERE std_status = :status AND std_mention = :mention AND level = :level AND semester = :semester');
    $findConfig->execute([
        'status' => $status,
        'mention' => $etude_mention,
        'level' => $level,
        'semester' => $nbr_semester
    ]);
    $config = $findConfig->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        $errors[] = "SKIP {$student_id} (session {$session_id}): Config finance introuvable pour {$status}/{$etude_mention}/L{$level}/S{$nbr_semester}";
        $skipped++;
        continue;
    }

    $cout_fraix_generaux = floatval($config['frais_generaux']);
    $nbr_day = intval($config['nb_jours_semestre']);
    $cout_costume = floatval($config['frais_costume']);
    $cout_voyage = floatval($config['frais_voyage']);
    $cout_fondDepot_dortoir = 0;
    $cout_frais_graduation = 0;

    // Frais de graduation si diplômé
    if ($graduated == 1) {
        $cout_frais_graduation = floatval($config['frais_graduation']);
    }

    // Voyage : doubler si semestre 2 et pas inscrit au semestre 1
    if ($nbr_semester > 1) {
        $checkSem1 = $dtb->prepare('SELECT COUNT(*) as cnt FROM t_2024_inscription_session ins 
            INNER JOIN t_2023_session ses ON ins.session_id = ses.session_id 
            WHERE ins.student_id = :student_id AND ses.session_year = :annee_scolaire AND ses.session_semester = 1');
        $checkSem1->execute(['student_id' => $student_id, 'annee_scolaire' => $annee_scolaire]);
        $hasSem1 = $checkSem1->fetch();
        if ($hasSem1['cnt'] == 0) {
            $cout_voyage = $cout_voyage * 2;
        }
    }

    // Fond dépôt si externe ou bungalow
    if ($row['std_status_original'] == "Externe" || $row['std_status_original'] == "Bungalow") {
        $cout_fondDepot_dortoir = floatval($config['fond_depot']);
    }

    $cout_logement = floatval($config['dortoir']) * $nbr_day;

    if ($dryRun) {
        echo "  [DRY] INSERT finance: student={$student_id}, session={$session_id}, "
           . "mention={$etude_mention}, status={$status}, level={$level}, "
           . "logement={$cout_logement}, frais_gen={$cout_fraix_generaux}, "
           . "voyage={$cout_voyage}, graduation={$cout_frais_graduation}\n";
        $inserted++;
        continue;
    }

    try {
        $insert = $dtb->prepare('INSERT INTO t_2024_etudiant_finace(
            student_id, session_id, mention, level, status,
            cout_logement, cout_fraix_generaux, cout_fondDepot_dortoir,
            cout_frais_graduation, cout_costume, cout_voyage, date_entry
        ) VALUES (
            :student_id, :session_id, :mention, :level, :status,
            :cout_logement, :cout_fraix_generaux, :cout_fondDepot_dortoir,
            :cout_frais_graduation, :cout_costume, :cout_voyage, :date_entry
        )');

        $insert->execute([
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

        echo "  OK: student={$student_id}, session={$session_id}, mention={$etude_mention}\n";
        $inserted++;
    } catch (PDOException $e) {
        $errors[] = "ERREUR {$student_id} (session {$session_id}): " . $e->getMessage();
        $skipped++;
    }
}

echo "\n=== RÉSULTAT ===\n";
echo "Insérés : {$inserted}\n";
echo "Ignorés : {$skipped}\n";

if (!empty($errors)) {
    echo "\n=== ERREURS / AVERTISSEMENTS ===\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
}

// Vérification finale
if (!$dryRun) {
    $verify = $dtb->query("SELECT COUNT(*) as cnt FROM t_2024_inscription_session ins 
        LEFT JOIN t_2024_etudiant_finace fin ON ins.student_id = fin.student_id AND ins.session_id = fin.session_id 
        WHERE ins.date_entry >= '2026-03-09' AND fin.id IS NULL");
    $remaining = $verify->fetch();
    echo "\nVérification : {$remaining['cnt']} ligne(s) encore manquante(s) après correction.\n";
}
