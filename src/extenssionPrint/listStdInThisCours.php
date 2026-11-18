<?php
require('../init/.forPrint/top.forPrint.php');
require_once(__DIR__ . '/../../data/cours.sigle.php');

$cours_id = $_GET['cours_id'];
$yearForCours = isset($_GET['yearForCours']) ? $_GET['yearForCours'] : '';
$semesterForCours = isset($_GET['semesterForCours']) ? trim((string)$_GET['semesterForCours']) : '';
$signatureForCours = isset($_GET['signatureForCours']) ? trim((string)$_GET['signatureForCours']) : '';
if ($yearForCours === '' || $semesterForCours === '') {
    http_response_code(400);
    echo 'Année et semestre requis pour exporter la liste des étudiants.';
    exit;
}

$findCours = $dtb->query('SELECT * FROM t_2023_cours WHERE id = '.$dtb->quote($cours_id).'');

$showCours = $findCours->fetch();


$printName = "STD-IN_".$showCours['title'];

?>
<div class="text-xs text-center my-2" style="border: 1px solid #8e9bb2;">
    <p class="text-[17px] bg-blue-200 h-[20px]">Titre du cours - [ <b><?=$showCours['title']?></b> ]</p>
    <div class="w-full p-2" style="display: flex;">

        <div class="text-right w-3/12">
            <label>Sigle - </label><br>
            <label>Catégorie - </label><br>
            <label>Enseignant(e) - </label><br>
            <label>Année - </label><br>
        </div>
        <div class="w-4/12 text-left pl-1">

            <b><?= htmlspecialchars(coursSigleAffiche($showCours), ENT_QUOTES, 'UTF-8') ?></b><br>
            <b><?php
if ($showCours['category'] == 0){
    echo "Général";
}elseif ($showCours['category'] == 1) {
    echo "Majeur";
}elseif ($showCours['category'] == -1 OR $showCours['category'] == 2) {
    echo "Selective";
}elseif ($showCours['category'] == 3) {
    echo "Additionnel";
}elseif ($showCours['category'] == 5) {
    echo "``";
}else{
    echo "-";
}
            ?></b><br>
            <b><?php 

    $findTeacher = $dtb->query('SELECT * FROM teacher WHERE uid ='.$dtb->quote($showCours['id_teacher']).'');
    $showTeacher = $findTeacher->fetch();
    
    echo $showTeacher['name']." ".$showTeacher['lastName'];
            ?></b><br>
            <b><?=$yearForCours?></b><br>
        </div>
        <div class="text-right w-3/12">
            <label>Crédit - </label><br>
            <label>Niveau - </label><br>
            <label>Semestre - </label><br>
        </div>
        <div class="w-4/12 text-left pl-1">
            <b><?=$showCours['nb_crd']?></b><br>
            <b><?php if($showCours['yearlevel'] <=3 ){
                echo "Licence ".$showCours['yearlevel'];
            }elseif ($showCours['yearlevel'] > 3){
                echo "Master ".($showCours['yearlevel'] - 3);
            }
                ?></b><br>
            <b><?php
                if ($semesterForCours === '3') {
                    echo "Semestre d'été";
                } elseif ($semesterForCours === '4') {
                    echo "Semestre d'hiver";
                } else {
                    echo "Semestre ".$semesterForCours;
                }
            ?></b><br>
        </div>
    </div>
</div>

<?php $showSignatureColumn = ($signatureForCours !== ''); ?>
<?php $colCount = ($showSignatureColumn ? 5 : 6); ?>
<b>Liste des étudiants</b>

 <table class="tbl">
    <thead>
        <tr>
            <th class="w-[30px]">No</th>
            <th class="">Matricule</th>
            <th class="">Noms</th>
            <th class="w-[90px]">Niveau</th>
            <?php if (! $showSignatureColumn): ?>
            <th class="w-[100px]">Téléphone</th>
            <?php endif; ?>
            <th class="text-right"><?php echo ($showSignatureColumn ? 'Signature' : 'Email'); ?></th>
        </tr>
    </thead>
    <tbody>
<?php 
// Keep export criteria consistent with src/cours/etudiants.php (Sigle + year).
// Notes of previous years may reference a different id_cours for the same course code.
$sigleCours = $showCours['Sigle'];
$sql = 'SELECT n.*, s.session_name, s.session_semester, s.session_year FROM t_2023_notes n LEFT JOIN t_2023_session s ON s.session_id = n.session_id WHERE n.Sigle = :sigle AND n.annee_scolaire = :annee_scolaire AND n.remove = 0';
$params = [
    'sigle' => $sigleCours,
    'annee_scolaire' => $yearForCours
];
if ($semesterForCours !== '') {
    $sql .= ' AND s.session_semester = :session_semester';
    $params['session_semester'] = $semesterForCours;
}
$sql .= ' ORDER BY n.session_id ASC, n.student_id ASC';
$findStdInCours = $dtb->prepare($sql);
$findStdInCours->execute($params);
$nbr = 1;
$currentSessionId = null;
while($showStdInCours = $findStdInCours->fetch()) {
    $jer = $dtb->query("SELECT * FROM tbl_2024_etudiant WHERE student_id=".$dtb->quote($showStdInCours['student_id'])." ORDER BY remove ASC, id ASC LIMIT 1");
    $apotr = $jer->fetch();
    $sessionId = (int)($showStdInCours['session_id'] ?? 0);

    // Exclure les etudiants suspendus et retires de l'universite.
    if (
        $apotr
        && (
            (isset($apotr['suspended']) && (int)$apotr['suspended'] === 1)
            || (isset($apotr['retrait_universite']) && (int)$apotr['retrait_universite'] !== 0)
        )
    ) {
        continue;
    }

    if ($currentSessionId !== $sessionId) {
        $currentSessionId = $sessionId;
        $sessionName = trim((string)($showStdInCours['session_name'] ?? ''));
        $sessionSemester = trim((string)($showStdInCours['session_semester'] ?? ''));
        $sessionYear = trim((string)($showStdInCours['session_year'] ?? ''));
        $sessionLabel = 'Session #'.$currentSessionId;
        if ($sessionName !== '' || $sessionSemester !== '' || $sessionYear !== '') {
            $sessionLabel = trim($sessionName.' - S'.$sessionSemester.' / '.$sessionYear, ' -/S');
            if ($sessionLabel === '') {
                $sessionLabel = 'Session #'.$currentSessionId;
            }
        }
?>
        <tr style="page-break-inside: avoid; background: #0f172a; color: #ffffff;">
            <td colspan="<?=$colCount?>"><b><?=htmlspecialchars($sessionLabel)?></b></td>
        </tr>
<?php
    }
  ?>
        <tr class="text-[11px]" style="page-break-inside: avoid;">
            <td class="py-1"><?=$nbr?></td>
            <td class="py-1"><?=$showStdInCours['student_id']?></td>
            <td class="py-1"><?php
if($apotr){
    if(is_null($apotr['student_nom']) AND is_null($apotr['student_prenom'])){
        echo "<em style='color:red'>Non défini</em>";
    }else{
        echo $apotr['student_nom']." ".$apotr['student_prenom'];
    }
}else{
    echo "<em style='color:red'>Etudiant supprimé de la base.</em>";
}
                    ?></td>
            <td class="py-1"><?php 
if($apotr){
    if($apotr['annee_etude'] <=3 ){
        echo "Licence ".$apotr['annee_etude'];
    }elseif ($apotr['annee_etude'] > 3){
        echo "Master ".($apotr['annee_etude'] - 3);
    }
}else{
    echo "-";
}
                ?></td>
            <?php if (! $showSignatureColumn): ?>
            <td class="py-1">
<?php 
if($apotr){
    echo $apotr['student_tel'];
}else{
    echo "-";
}
?>
</td>
            <?php endif; ?>
            <td class="py-1 text-right"><?php 
    if ($showSignatureColumn) {
        echo '&nbsp;';
    } elseif($apotr){
        echo $apotr['student_email'];
    }else{
        echo "-";
    }
                ?></td>
        </tr>
 <?php
 $nbr++;
    }
  ?>
    </tbody>
 </table>

 <b><?php if ($nbr > 2) { echo ($nbr-1)." étudiants"; }else{ echo ($nbr-1)." étudiant"; } ?></b>
 <?php require('../init/.forPrint/foot.forPrint.php'); ?>
