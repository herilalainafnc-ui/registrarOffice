<?php
$mention = $_POST['types'];
$annee_scolaire = $_POST['yearFilesSlip'];
$semestre = $_POST['semesterFilesSlip'] ?? '';
$date_begin = $_POST['date_begin'];
$date_end = $_POST['date_end'];

// Options d'affichage des champs
$show_matricule = isset($_POST['show_matricule']);
$show_nom = isset($_POST['show_nom']);
$show_mention = isset($_POST['show_mention']);
$show_niveau = isset($_POST['show_niveau']);

// Export pour un seul étudiant
$single_student_id = isset($_POST['single_student_id']) ? trim($_POST['single_student_id']) : '';

$verification_base = date('Ymd') . $annee_scolaire;
$printName = "FILES_CHECKING_SLIP_ETUDIANT";

// Session ciblée (même logique que la liste des étudiants : semestre + année)
$sessLookup = $dtb->prepare('SELECT session_id FROM t_2023_session WHERE session_semester = ? AND session_year = ? LIMIT 1');
$sessLookup->execute([$semestre, $annee_scolaire]);
$sessRow = $sessLookup->fetch();
$session_id = $sessRow ? $sessRow['session_id'] : null;

// Mentions à traiter, en SIGLE (comme la liste des étudiants).
// Le formulaire envoie la description : on convertit en sigle.
if ($mention == "TOUT") {
    $mentions = $dtb->query('SELECT filiere_sigle FROM filiere ORDER BY filiere_description');
    $mentions_list = [];
    while ($m = $mentions->fetch()) {
        $mentions_list[] = $m['filiere_sigle'];
    }
} else {
    $sig = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = ? LIMIT 1');
    $sig->execute([$mention]);
    $sigRow = $sig->fetch();
    $mentions_list = $sigRow ? [$sigRow['filiere_sigle']] : [];
}
?>

<style>
/* ====== PRINT CONFIG ====== */
@page {
    size: A4;
    margin: 10mm;
}

@media print {
    body {
        margin: 0;
        padding: 0;
    }
    .files-slip-card {
        page-break-inside: avoid;
    }
}

/* ====== PAGE GRID ====== */
.slip-page {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: repeat(5, 1fr);
    gap: 6px;
    width: 100%;
    height: 271.2mm; /* A4 usable height */
    page-break-after: always;
}

/* ====== CARD ====== */
.files-slip-card {
    border: 1px solid #000;
    padding: 5px;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
    height: 100%;
}

/* Watermark */
.files-slip-card::before {
    content: 'UAZ OFFICIAL';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-size: 26px;
    font-weight: bold;
    color: rgba(0, 128, 0, 0.06);
    white-space: nowrap;
    z-index: 0;
}

.slip-content {
    position: relative;
    z-index: 1;
}

/* Stamp */
.official-stamp {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 34px;
    height: 34px;
    border: 2px solid rgba(0, 128, 0, 0.35);
    border-radius: 50%;
    font-size: 6px;
    font-weight: bold;
    color: rgba(0, 128, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    transform: rotate(-15deg);
    text-align: center;
}

/* Security ref */
.security-badge {
    position: absolute;
    bottom: 2px;
    right: 5px;
    font-size: 6px;
    color: #555;
    font-family: monospace;
}
</style>

<?php
$total_slips = 0;
foreach ($mentions_list as $current_mention) {

    // Étudiants réellement inscrits à cette session pour cette mention
    // (même logique que la liste des étudiants). Master exclu, gradués inclus.
    $sql = 'SELECT std.*
        FROM t_2024_inscription_session ins
        INNER JOIN tbl_2024_etudiant std ON std.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        WHERE ins.session_id = ?
        AND ins.etude_mention = ?
        AND (std.suspended IS NULL OR std.suspended != 1)
        AND (std.retrait_universite IS NULL OR std.retrait_universite = 0)
        AND std.annee_etude <= 3';
    $params = [$session_id, $current_mention];
    if ($single_student_id !== '') {
        $sql .= ' AND std.student_id = ?';
        $params[] = $single_student_id;
    }
    $sql .= ' GROUP BY ins.student_id ORDER BY std.student_id';
    $student = $dtb->prepare($sql);
    $student->execute($params);

    $slips_per_page = 10;
    $counter = 0;

    echo '<div class="slip-page">';

    while ($afficher = $student->fetch()) {

        if ($counter > 0 && $counter % $slips_per_page == 0) {
            echo '</div><div class="slip-page">';
        }

        $verification_code = strtoupper(substr(
            md5($verification_base.$afficher['student_id']), 0, 8
        ));
?>

<div class="files-slip-card">
    <div class="slip-content">

        <div class="official-stamp">
            UAZ<br>FILES<br>OK
        </div>

        <center>
            <b style="font-size:12px;">
                UNIVERSITE ADVENTISTE ZURCHER<br>
                File's Checking Clearance Slip
            </b>
        </center>

        <div style="font-size:10px; height:85px;">
            <em>Matricule :</em> <b><?= $show_matricule ? $afficher['student_id'] : '___________________' ?></b><br>
            <em>Nom et prénoms :</em>
            <b><?= $show_nom ? strtoupper($afficher['student_nom'])." ".$afficher['student_prenom'] : '___________________________________' ?></b><br>
            <em>Mention :</em>
            <b><?= $show_mention ? $afficher['etude_envisage']." - ".$afficher['etude_option'] : '___________________________________' ?></b><br>
            <em>Niveau :</em>
            <b><?= $show_niveau ? 'Licence '.$afficher['annee_etude'] : '___________________' ?></b><br>
            <em>Année académique :</em>
            <b><?= $annee_scolaire ?></b>
        </div>

        <div style="text-align:center; font-size:8px;">
            Les dossiers de l'étudiant au bureau académique sont en ordre et peut faire les examens.
        </div>

        <hr style="margin:3px 0;">

        <table style="width:100%; font-size:10px;">
            <tr>
                <td>
                    <em>Date du début :</em> <b><?= $date_begin ?></b><br>
                    <em>Date d'expiration :</em> <b><?= $date_end ?></b>
                </td>
                <td>
                    <em>Signature</em><br><br><br>
                </td>
            </tr>
        </table>

        <div class="security-badge">
            REF: <?= $verification_code ?> | <?= date('d/m/Y H:i') ?>
        </div>

    </div>
</div>

<?php
        $counter++;
        $total_slips++;
    }

    echo '</div>';
}

echo '<div style="text-align:center;font-size:13px;font-weight:bold;margin-top:12px;padding:8px;border-top:2px solid #000;">Total : '.$total_slips.' slip(s) généré(s)</div>';
?>
