<?php
/**
 * AJAX endpoint pour récupérer la liste des cours
 * Utilisé pour rafraîchir dynamiquement la liste sans recharger la page
 */

require('../../data/backdb.php');

// Démarrer la session pour récupérer les variables de couleur
// Durée de vie de la session : 10 heures
ini_set('session.gc_maxlifetime', 36000);
ini_set('session.cookie_lifetime', 36000);
session_start();

// Définir les variables de couleur depuis la session
$bg_one_color = $_SESSION['bg_one_color'] ?? 'bg-[#1e3a5f]';
$bg_two_color = $_SESSION['bg_two_color'] ?? 'bg-[#2a4a6f]';
$bg_three_color = $_SESSION['bg_three_color'] ?? 'bg-[#3a5a7c]';
$bg_four_color = $_SESSION['bg_four_color'] ?? 'bg-[#0f2744]';
$bg_five_color = $_SESSION['bg_five_color'] ?? 'bg-[#3a5a7c]';
$br_two_color = $_SESSION['br_two_color'] ?? 'border-[#2a4a6f]';

// Récupérer rg_id depuis la session
$rg_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Récupérer les paramètres
$id = isset($_GET['id']) ? $_GET['id'] : null;
$student_id = isset($_GET['student_id']) ? urldecode($_GET['student_id']) : null;
$etude_envisage = isset($_GET['etude_envisage']) ? urldecode($_GET['etude_envisage']) : null;
$etude_option = isset($_GET['etude_option']) ? urldecode($_GET['etude_option']) : null;
$level = isset($_GET['level']) ? intval($_GET['level']) : 1;

if (!$student_id) {
    echo '<div class="text-red-500 p-4">Erreur: ID étudiant manquant</div>';
    exit;
}

// Récupérer les infos du profil depuis la table tbl_2024_etudiant
$findProfil = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE id = :id');
$findProfil->execute(['id' => $id]);
$profil = $findProfil->fetch();

if ($profil) {
    $etude_envisage = $profil['etude_envisage'];
    $etude_option = $profil['etude_option'];
    $level = $profil['annee_etude']; // annee_etude au lieu de level
}

// Mapper etude_envisage vers le code département
if ($etude_envisage == "Théologie") {
    $eE = 'THEO';
} elseif ($etude_envisage == "Gestion") {
    $eE = 'GEST';
} elseif ($etude_envisage == "Informatique") {
    $eE = 'INFO';
} elseif ($etude_envisage == "Sciences Infirmières") {
    $eE = 'NURS';
} elseif ($etude_envisage == "Education") {
    $eE = 'EDUC';
} elseif ($etude_envisage == "Communication") {
    $eE = 'COMM';
} elseif ($etude_envisage == "Etudes anglophones") {
    $eE = 'LANG';
} elseif ($etude_envisage == "Droit") {
    $eE = 'DROI';
} else {
    $eE = '';
}

// Calculer init et level
if ($level <= 3) {
    $init = 1;
    $level = 3;
} elseif ($level > 3) {
    $init = 4;
    $level = 5;
}

// Générer le HTML de la liste des cours
for ($a = $init; $a <= $level; $a++) {
?>
<div id="year<?=$a?>" class='p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all'>
    <b>
        <?php 
        if($a <= 3) {
            echo "NIVEAU Licence ".$a;
        } else {
            echo "NIVEAU Master ".($a-3);
        }
        ?>		
    </b>
<?php		
    for ($s = 1; $s <= 2; $s++) { 
?>
    <!-- DEBUT DU FORMULAIRE -->
    <form action="javascript:void(0);" data-action="../app/.student/checkCours.php" method="post" class="form-newCours">
    	<input type="hidden" name="id" value="<?=htmlspecialchars($id ?? '')?>">
    	<input type="hidden" name="student_id" value="<?=htmlspecialchars($student_id ?? '')?>">
    	<input type="hidden" name="page" value="newCours">
    	<input type="hidden" name="user_id" value="<?=htmlspecialchars($rg_id ?? '')?>">
    	<input type="hidden" name="etude_envisage" value="<?=htmlspecialchars($profil['etude_envisage'] ?? '')?>">
    	<input type="hidden" name="status" value="<?=htmlspecialchars($profil['status'] ?? '')?>">
    	<input type="hidden" name="new_student" value="<?=htmlspecialchars($profil['new_student'] ?? '')?>">
    	<input type="hidden" name="graduated" value="<?=htmlspecialchars($profil['graduated'] ?? '')?>">
    	<input type="hidden" name="student_adresse" value="<?=htmlspecialchars($profil['student_adresse'] ?? '')?>">
    	<input type="hidden" name="etude_option" value="<?=htmlspecialchars($profil['etude_option'] ?? '')?>">
    	<input type="hidden" name="annee_etude" value="<?=htmlspecialchars($profil['annee_etude'] ?? '')?>">
    	<input type="hidden" name="sponsor_nom" value="<?=htmlspecialchars($profil['sponsor_nom'] ?? '')?>">
    	<input type="hidden" name="sponsor_prenom" value="<?=htmlspecialchars($profil['sponsor_prenom'] ?? '')?>">
    	<input type="hidden" name="sponsor_tel" value="<?=htmlspecialchars($profil['sponsor_tel'] ?? '')?>">
    	<input type="hidden" name="sponsor_adresse" value="<?=htmlspecialchars($profil['sponsor_adresse'] ?? '')?>">
    	<input type="hidden" name="situationf" value="<?=htmlspecialchars($profil['situationf'] ?? '')?>">
    	<input type="hidden" name="nom_conjoint" value="<?=htmlspecialchars($profil['nom_conjoint'] ?? '')?>">
    	<input type="hidden" name="nb_enfant" value="<?=htmlspecialchars($profil['nb_enfant'] ?? 0)?>">
    	<input type="hidden" name="abonment" value="<?=htmlspecialchars($profil['abonment'] ?? 0)?>">

        <table class="simpleTbl mb-1 w-full">
            <thead>
                <tr class="text-left bg-gradient-to-r from-green-600">
                    <th colspan="10">SEMESTRE <?=$s?></th>
                </tr>
            </thead>
            <thead class="<?=$bg_one_color?> text-white">
                <tr>
                    <th class="w-5"></th>
                    <th class="w-20">Sigle</th>
                    <th class="">Titre du cours</th>
                    <th class="sm:w-2/12 lg:w-3/12">Observations</th>
                    <th class="w-20">Crédits</th>
                    <th class="w-20">Catégorie</th>
                </tr>	
            </thead>
            <tbody class="<?=$bg_four_color?>">
<?php
    // Récupérer le parcours
    $parcour = $dtb->prepare('SELECT * FROM filiere_parcours WHERE description = :desc');
    $parcour->execute(['desc' => $etude_option]);
    $afparc = $parcour->fetch();
    $parcours = !empty($afparc) ? $afparc['shortcode'] : '';
    
    $tout = 'all';

    $cours = $dtb->prepare("SELECT * FROM t_2023_cours WHERE dep_desc = :dep AND yearlevel = :level AND semester = :sem AND (parcours = :parcours OR parcours = :tout) ORDER BY title");
    $cours->execute([
        'dep' => $eE,
        'level' => $a,
        'sem' => $s,
        'parcours' => $parcours,
        'tout' => $tout
    ]);
    
    $nbr = 0;
    $tcredit = 0;
    
    while ($crs = $cours->fetch()) {
        $note_id = $crs['id'];

        $verifyExisting = $dtb->prepare('SELECT * FROM t_2023_notes WHERE id_cours = :id_cours AND student_id = :student_id AND ajout = 1 AND remove = 0');
        $verifyExisting->execute(['id_cours' => $note_id, 'student_id' => $student_id]);
        $validExisting = $verifyExisting->fetch();
?>
                <tr 
<?php if (empty($validExisting)) { ?>	
                    id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black"
<?php } elseif(!empty($validExisting) && ($validExisting['grade'] >= 12 || $validExisting['grade'] == 0)) { ?>
                    class="bg-slate-700"
<?php } elseif(!empty($validExisting) && $validExisting['grade'] > 0 && $validExisting['grade'] < 12) { ?>
                    id="cours<?=$a.$s.$nbr;?>" class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black bg-slate-700"
<?php } ?>>
                    <td class="p-0 text-center" style="height: 15px;">
<?php if (empty($validExisting)) { ?>	
                        <input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;">
<?php } elseif(!empty($validExisting) && $validExisting['grade'] > 0 && $validExisting['grade'] < 12) { ?>
                        <input id="chk<?=$a.$s.$nbr;?>" type="checkbox" name="checklist[]" value="<?=$note_id?>" style="width: 100%; height: 100%;margin: none; border: none;">	
<?php } elseif(!empty($validExisting) && ($validExisting['grade'] >= 12 || $validExisting['grade'] == 0)) { ?>	
                        <i class="bi-x-lg text-red-300"></i>	
<?php } ?>		
                    </td>
                    <td class="bg-gradient-to-r from-cyan-800 to-cyan-600"><?=$crs['Sigle']?></td>
                    <td><?php
                        if ($etude_envisage == "Etudes anglophones") {
                            echo $crs['title_english'];
                        } else {
                            echo $crs['title'];
                        }
                    ?></td>
                    <td><?php 
                        if (!empty($validExisting) && $validExisting['grade'] >= 10) {
                            echo "<em class='text-green-400'><b>".$validExisting['grade']."</b> de moyenne</em>";
                        } elseif (!empty($validExisting) && $validExisting['grade'] > 0 && $validExisting['grade'] < 10) {
                            echo "<em class='text-red-500'><b>".$validExisting['grade']."</b> de moyenne, en état d'echec.</em>";
                        } elseif (!empty($validExisting) && $validExisting['grade'] == 0) {
                            echo "<em class='text-orange-400'>Ajouté le - ".substr($validExisting['date_entry'], 0, 10)."</b></em>";
                        }
                    ?></td>
                    <td><?=$crs['nb_crd']?></td>
                    <td><?php 
                        if ($crs['category'] == 0) {
                            echo "Général";
                        } elseif ($crs['category'] == 1) {
                            echo "Majeur";
                        } elseif ($crs['category'] == 2) {
                            echo "Selective";
                        } elseif ($crs['category'] == 3) {
                            echo "Additionnel";
                        } else {
                            echo "-";
                        }
                    ?></td>
                </tr>
<?php
        $tcredit += $crs['nb_crd'];
        $nbr++;
    }
?>			
            </tbody>
            <tfoot class="<?=$bg_one_color?> text-white">
                <tr>
                    <th colspan="2"></th>
                    <th><?=$nbr?> cours</th>
                    <th></th>
                    <th><?php if($nbr < 1) { echo 0; } else { echo $tcredit; } ?></th>
                    <th></th>
                </tr>
                <tr>
                    <td colspan="6" class="<?=$bg_four_color?>">
                        <div class="flex">
                            <div class="w-40 pt-1">
                                <a href="#" id="selectAll<?=$a.$s;?>" class="px-2 py-0 m-1"><i class="bi-arrow-90deg-up"></i> Cocher tout</a>
                                <a href="#" id="deselectAll<?=$a.$s;?>" class="px-2 py-0 m-1 hidden"><i class="bi-arrow-90deg-up"></i> Décocher tout</a>
                            </div>
                            <div>
                                <b>Session :</b>
                                <select name="semesterSession" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarSs<?=$a.$s;?>">
                                    <option <?php if (date('m') >= 7) { echo "selected"; } ?>>Premier semestre</option>
                                    <option>Semestre d'été</option>
                                    <option <?php if (date('m') < 7) { echo "selected"; } ?>>Deuxième semestre</option>
                                    <option>Semestre d'hiver</option>
                                </select>

                                <b>Année du cours :</b>
                                <select name="annee_scolaire" class="h-[22px] m-1 py-0 text-black text-sm" id="scolarA<?=$a.$s;?>">
                                    <?php
                                        $mois = date('m');
                                        if (intval($mois) < 7) {
                                            $z = date('Y');
                                        } else {
                                            $z = date('Y') + 1;
                                        }
                                        for ($i = 1; $i < 6; $i++) { 
                                    ?>
                                        <option><?=($z-1)." - ".$z?></option>
                                    <?php
                                            $z = $z - 1;
                                        }
                                    ?>
                                </select>
                            </div>
                            <button id="submit<?=$a.$s;?>" type="submit" class="submiting px-2 text-center py-0 m-1 text-slate-400 bg-black">Ajouter au transcript</button>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </form>
    <!-- FIN DU FORMULAIRE -->
<?php
    }
    echo "</div>";
}
?>
