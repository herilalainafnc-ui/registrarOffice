<?php
$mention = $_POST['types'];
$annee_scolaire = $_POST['yearworkedSlip'];
$date_begin = $_POST['date_begin'];
$date_end = $_POST['date_end'];

$verification_base = date('Ymd') . $annee_scolaire;
$printName = "WORKED_SLIP_ETUDIANT";

// Exclusions
$exclusion = " 
AND (graduated IS NULL OR graduated != 1)
AND (suspended IS NULL OR suspended != 1)
AND (retrait_universite IS NULL OR retrait_universite = 0)
AND (annee_etude <= 3)
";

// Mentions
if ($mention == "TOUT") {
    $mentions = $dtb->query('
        SELECT DISTINCT etude_envisage 
        FROM tbl_2024_etudiant 
        WHERE annee_scolaire = "'.$annee_scolaire.'" '.$exclusion.'
        ORDER BY etude_envisage
    ');
    $mentions_list = [];
    while ($m = $mentions->fetch()) {
        $mentions_list[] = $m['etude_envisage'];
    }
} else {
    $mentions_list = [$mention];
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
    .worked-slip-card {
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
.worked-slip-card {
    border: 1px solid #000;
    padding: 5px;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
    height: 100%;
}

/* Watermark */
.worked-slip-card::before {
    content: 'UAZ OFFICIAL';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-size: 26px;
    font-weight: bold;
    color: rgba(0, 90, 160, 0.06);
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
    border: 2px solid rgba(0, 90, 160, 0.35);
    border-radius: 50%;
    font-size: 6px;
    font-weight: bold;
    color: rgba(0, 90, 160, 0.6);
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
foreach ($mentions_list as $current_mention) {

    $student = $dtb->query('
        SELECT * FROM tbl_2024_etudiant 
        WHERE annee_scolaire = "'.$annee_scolaire.'" 
        AND etude_envisage="'.$current_mention.'" '.$exclusion.'
        ORDER BY student_id
    ');

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

<div class="worked-slip-card">
    <div class="slip-content">

        <div class="official-stamp">
            UAZ<br>WORK<br>EDU
        </div>

        <center>
            <b style="font-size:12px;">
                UNIVERSITE ADVENTISTE ZURCHER<br>
                Department Work Education Clearance Slip
            </b>
        </center>

        <div style="font-size:10px; height:85px;">
            <em>Matricule :</em> <b><?= $afficher['student_id'] ?></b><br>
            <em>Nom et prénoms :</em>
            <b><?= strtoupper($afficher['student_nom'])." ".$afficher['student_prenom'] ?></b><br>
            <em>Mention :</em>
            <b><?= $afficher['etude_envisage']." - ".$afficher['etude_option'] ?></b><br>
            <em>Niveau :</em>
            <b>Licence <?= $afficher['annee_etude'] ?></b><br>
            <em>Année académique :</em>
            <b><?= $afficher['annee_scolaire'] ?></b>
        </div>

        <div style="text-align:center; font-size:8px;">
            Work Education terminé et peut faire les examens.
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
    }

    echo '</div>';
}
?>
