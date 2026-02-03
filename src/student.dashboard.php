<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php');?>
    <title>Espace Étudiant</title>
    <style>
        /* Animation d'entrée slide-in */
        body {
            animation: slideInFromRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<?php
// Vérifier que l'utilisateur est connecté et est un étudiant
require('../data/middleware.php');
initMiddleware($dtb);

// Autoriser l'accès aux admins, registrars ET étudiants
if (!isStudent() && !isAdmin() && !isRegistrar()) {
    header('Location: ./index.php');
    exit;
}

// Récupérer les informations de l'étudiant
$studentInfo = getStudentInfo();

if (!$studentInfo && isStudent()) {
    echo '<div class="p-4 text-red-500">Erreur: Votre compte n\'est pas lié à un profil étudiant.</div>';
    exit;
}

// Récupérer l'année scolaire courante
$currentYear = $studentInfo['annee_scolaire'] ?? (date('m') >= 7 ? date('Y').' - '.(date('Y')+1) : (date('Y')-1).' - '.date('Y'));

// Récupérer le semestre actuel
$currentSemester = date('m') >= 7 ? 1 : 2;

// Récupérer l'ID de l'étudiant
$student_id = $studentInfo['student_id'];
$yes = 1;

// Récupérer toutes les sessions distinctes où l'étudiant a des notes
$allSessions = $dtb->query("SELECT DISTINCT n.session_id, s.session_name, s.session_semester, s.session_year 
    FROM t_2023_notes n 
    INNER JOIN t_2023_session s ON n.session_id = s.session_id 
    WHERE n.student_id = '".$student_id."' AND n.ajout = '".$yes."' 
    ORDER BY s.session_year ASC, s.session_semester ASC");

// Calculer la moyenne globale
$globalStats = $dtb->query("SELECT SUM(credit) as total_credits, SUM(credit * grade) as total_points 
    FROM t_2023_notes 
    WHERE student_id = '".$student_id."' AND ajout = 1 AND grade > 0");
$stats = $globalStats->fetch(PDO::FETCH_ASSOC);
$totalCredits = $stats['total_credits'] ?? 0;
$totalPoints = $stats['total_points'] ?? 0;
$moyenne = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : null;
?>

<div class="h-screen w-full <?=$bg_three_color?>">
    
    <!-- TOP BAR -->
    <div class="w-full bg-slate-800 h-14 flex items-center px-4 justify-between">
        <div class="flex items-center">
            <img src="../file/UAZ Official.png" alt="UAZ" class="h-10 mr-3">
            <span class="text-white font-bold">Université Adventiste Zurcher - Espace Étudiant</span>
        </div>
        <div class="flex items-center text-white">
            <span class="mr-4"><?= htmlspecialchars($studentInfo['student_nom'].' '.$studentInfo['student_prenom']) ?></span>
            <a href="../app/logout" class="bg-red-600 hover:bg-red-500 px-3 py-1 rounded text-sm">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>
    </div>

    <div class="w-full p-4" style="height: calc(100vh - 56px); overflow: auto;">
        
        <!-- Carte d'information -->
        <div class="<?=$bg_one_color?> rounded-lg p-6 mb-6">
            <div class="flex flex-col lg:flex-row gap-6">
                
                <!-- Photo -->
                <div class="flex-shrink-0">
                    <?php if (!empty($studentInfo['image_student'])): ?>
                        <img src="../app/photosetudiants/<?= htmlspecialchars($studentInfo['image_student']) ?>" 
                             alt="Photo" class="w-32 h-32 object-cover rounded-lg border-2 border-slate-500">
                    <?php else: ?>
                        <div class="w-32 h-32 bg-slate-600 rounded-lg flex items-center justify-center">
                            <i class="bi bi-person text-4xl text-slate-400"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Informations -->
                <div class="flex-1 text-white">
                    <h1 class="text-2xl font-bold mb-4">
                        <?= htmlspecialchars(strtoupper($studentInfo['student_nom']).' '.$studentInfo['student_prenom']) ?>
                    </h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-slate-400">Matricule:</span><br>
                            <strong><?= htmlspecialchars($studentInfo['student_id']) ?></strong>
                        </div>
                        <div>
                            <span class="text-slate-400">Mention:</span><br>
                            <strong><?= htmlspecialchars($studentInfo['etude_envisage']) ?></strong>
                        </div>
                        <div>
                            <span class="text-slate-400">Parcours:</span><br>
                            <strong><?= htmlspecialchars($studentInfo['etude_option'] ?? '-') ?></strong>
                        </div>
                        <div>
                            <span class="text-slate-400">Niveau:</span><br>
                            <strong>Licence <?= $studentInfo['annee_etude'] ?></strong>
                        </div>
                        <div>
                            <span class="text-slate-400">Année académique:</span><br>
                            <strong><?= htmlspecialchars($currentYear) ?></strong>
                        </div>
                        <div>
                            <span class="text-slate-400">Email:</span><br>
                            <strong><?= htmlspecialchars($studentInfo['student_email'] ?? '-') ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Moyenne -->
                <?php if ($moyenne !== null): ?>
                <div class="flex-shrink-0 text-center">
                    <div class="bg-slate-700 rounded-lg p-4">
                        <div class="text-slate-400 text-sm mb-1">Moyenne Pondérée</div>
                        <div class="text-3xl font-bold <?= $moyenne >= 10 ? 'text-green-400' : 'text-red-400' ?>">
                            <?= $moyenne ?>
                        </div>
                        <div class="text-slate-400 text-xs"><?= $totalCredits ?> crédits</div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            
            <!-- Mes Notes par Session -->
            <div class="<?=$bg_one_color?> rounded-lg p-4">
                <h2 class="text-lg font-bold text-white mb-4">
                    <i class="bi bi-clipboard-data mr-2"></i>
                    Mes Notes par Session
                </h2>
                
                <div class="max-h-screen overflow-auto">
                    <?php 
                    $sessionCount = 0;
                    $hasNotes = false;
                    
                    // Variables cumulatives
                    $cumulWorkNote = 0;
                    $cumulremarkAcad = 0;
                    $cumulChapel = 0;
                    $cumulGen = 0;
                    $cumulMaj = 0;
                    $cumulFinale = 0;
                    
                    while($showSs = $allSessions->fetch()):
                        $hasNotes = true;
                        $sessionCount++;
                        $session_id = $showSs['session_id'];
                        $combinAnual = $showSs['session_year'];
                        
                        // Récupérer le yearlevel depuis les notes de cette session
                        $getYearlevel = $dtb->query("SELECT yearlevel FROM t_2023_notes WHERE student_id='".$student_id."' AND session_id='".$session_id."' AND ajout='".$yes."' LIMIT 1");
                        $ylData = $getYearlevel->fetch();
                        $yearlevel = $ylData ? $ylData['yearlevel'] : 1;
                        
                        // Déterminer le niveau (Licence ou Master)
                        if ($yearlevel <= 3) {
                            $niveau_label = "Licence " . $yearlevel;
                        } else {
                            $niveau_label = "Master " . ($yearlevel - 3);
                        }
                        
                        // Récupérer les cours de cette session
                        $cours = $dtb->query("SELECT * FROM t_2023_notes WHERE student_id ='".$student_id."' AND ajout='".$yes."' AND session_id='".$session_id."' ORDER BY title_cours");
                        
                        if ($cours->rowCount() > 0):
                    ?>
                    <div class='p-2 <?=$bg_two_color?> mb-4 rounded-md border-2 border-slate-700'>
                        <table class="w-full text-sm mb-1">
                            <thead>
                                <tr class="text-center bg-gradient-to-r from-cyan-500 to-cyan-700">
                                    <th colspan="6" class="p-2 text-white font-bold">
                                        <b><?=$niveau_label?></b> | <?=$showSs['session_name']?> - Session N°<?=$showSs['session_semester']?> | Année <?=$combinAnual?>
                                    </th>
                                </tr>
                            </thead>
                            <thead class="<?=$bg_one_color?> text-white">
                                <tr>
                                    <th class="p-2 text-left">Sigle</th>
                                    <th class="p-2 text-left">Titre du cours</th>
                                    <th class="p-2 text-center w-20">Crédits</th>
                                    <th class="p-2 text-center w-24">Catégorie</th>
                                    <th class="p-2 text-center w-20">Note/20</th>
                                    <th class="p-2 text-center w-16">État</th>
                                </tr>
                            </thead>
                            <tbody class="<?=$bg_four_color?>">
                                <?php 
                                $nbr = 0;
                                $tcredit = 0;
                                $tnote = 0;
                                $tnotecredit = 0;
                                $nbrMaj = 0;
                                $tTMaj = 0;
                                $nbrFinale = 0;
                                $tTFinale = 0;
                                
                                while($crs = $cours->fetch()): 
                                    $nbr++;
                                    $tcredit += $crs['credit'];
                                    $tnote += $crs['grade'];
                                    $tnotecredit += $crs['credit'] * $crs['grade'];
                                    
                                    // Calcul des notes majeures
                                    if ($crs['cours_category'] == 1 || $crs['cours_category'] == "Majeur") {
                                        $nbrMaj++;
                                        $tTMaj += $crs['grade'];
                                    }
                                    
                                    // Calcul des notes finales (Majeur + Général)
                                    if ($crs['cours_category'] == 1 || $crs['cours_category'] == "Majeur" || 
                                        $crs['cours_category'] == 0 || $crs['cours_category'] == "Général") {
                                        $nbrFinale++;
                                        $tTFinale += $crs['grade'];
                                    }
                                    
                                    // Déterminer la catégorie
                                    $categorie = match($crs['cours_category']) {
                                        0 => "Général",
                                        1 => "Majeur",
                                        -1, 2 => "Selective",
                                        3 => "Additionnel",
                                        default => "-"
                                    };
                                    
                                    // Classe de couleur selon la note
                                    $noteClass = ($crs['grade'] >= 10 || $crs['grade'] == -2) ? 'bg-green-600' : 
                                                 (($crs['grade'] < 10 && $crs['grade'] > 0) ? 'bg-red-600' : '');
                                    $etat = ($crs['grade'] >= 10 || $crs['grade'] == -2) ? 'S' : 
                                           (($crs['grade'] < 10 && $crs['grade'] > 0) ? 'E' : '');
                                ?>
                                <tr class="hover:<?=$bg_five_color?> border-b border-slate-600">
                                    <td class="p-2 bg-gradient-to-r from-orange-800 to-orange-600 text-white"><?=$crs['Sigle']?></td>
                                    <td class="p-2 text-white"><?=$crs['title_cours']?></td>
                                    <td class="p-2 text-center text-white"><?=$crs['credit']?></td>
                                    <td class="p-2 text-center text-white"><?=$categorie?></td>
                                    <td class="p-2 text-center text-white font-bold"><?=$crs['grade'] > 0 ? $crs['grade'] : '-'?></td>
                                    <td class="p-2 text-center <?=$noteClass?> text-white font-bold"><?=$etat?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="<?=$bg_one_color?> text-white">
                                <tr>
                                    <th class="p-2" colspan="2"><?=$nbr?> cours</th>
                                    <th class="p-2"><?=$tcredit?> crédits</th>
                                    <th class="p-2"></th>
                                    <th class="p-2"><?=round($tnote, 2)?></th>
                                    <th class="p-2"></th>
                                </tr>
                                <?php 
                                // Récupérer les notes de promotion
                                $searchPromotion = $dtb->query('SELECT * FROM t_2023_promotion_notes WHERE student_id = "'.$student_id.'" AND session_id = "'.$session_id.'"');
                                $showPromotion = $searchPromotion->fetch();
                                if ($showPromotion): 
                                ?>
                                <tr class="<?=$bg_four_color?> text-right">
                                    <td colspan="4" class="p-1">Note de Work Education</td>
                                    <td class="p-1 text-center"><?=$showPromotion['grade_work_educ'] ?: '-'?></td>
                                    <td></td>
                                </tr>
                                <tr class="<?=$bg_four_color?> text-right">
                                    <td colspan="4" class="p-1">Remarque académique</td>
                                    <td class="p-1 text-center"><?=$showPromotion['grade_remark_acad'] ?: '-'?></td>
                                    <td></td>
                                </tr>
                                <tr class="<?=$bg_four_color?> text-right">
                                    <td colspan="4" class="p-1">Participation chapelle/prière</td>
                                    <td class="p-1 text-center"><?=$showPromotion['grade_chapel_part'] ?: '-'?></td>
                                    <td></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th colspan="4" class="p-2 text-right">Moyenne Majeure</th>
                                    <th class="p-2"><?php $moyenMajSem = $nbrMaj > 0 ? round($tTMaj / $nbrMaj, 2) : 0; echo $moyenMajSem; ?></th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="4" class="p-2 text-right">Moyenne Générale</th>
                                    <th class="p-2 bg-cyan-700"><?php $moyenFinale = $tcredit > 0 ? round($tnotecredit / $tcredit, 2) : 0; echo $moyenFinale; ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php 
                        // Cumuler les notes de promotion
                        if ($showPromotion) {
                            $cumulWorkNote += $showPromotion['grade_work_educ'] ?: 0;
                            $cumulremarkAcad += $showPromotion['grade_remark_acad'] ?: 0;
                            $cumulChapel += $showPromotion['grade_chapel_part'] ?: 0;
                        }
                        
                        // Cumuler les moyennes
                        $cumulMaj += $moyenMajSem;
                        $cumulFinale += $moyenFinale;
                        
                        endif;
                    endwhile; 
                    
                    // Afficher les moyennes cumulatives si l'étudiant a des notes
                    if ($hasNotes && $sessionCount > 0): 
                    ?>
                    <div class='p-2 <?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-600 text-xs text-white'>
                        <b class="text-lg">MOYENNE CUMULATIVE</b>
                        <table class="w-full text-sm mb-2 mt-2">
                            <tbody class="<?=$bg_two_color?>">
                                <tr class="border-b border-slate-600">
                                    <td class="p-2 text-right">Note de Work Education cumulative</td>
                                    <td class="p-2 w-24 text-center font-bold"><?=round($cumulWorkNote / $sessionCount, 2)?></td>
                                </tr>
                                <tr class="border-b border-slate-600">
                                    <td class="p-2 text-right">Note de participation à l'exercice de chapelle et à la semaine de prière cumulative</td>
                                    <td class="p-2 w-24 text-center font-bold"><?=round($cumulChapel / $sessionCount, 2)?></td>
                                </tr>
                            </tbody>
                        </table>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-900">
                                <tr class="border-b border-slate-600">
                                    <th class="p-2 text-right">Moyenne Majeure Cumulative</th>
                                    <th class="p-2 w-24"><?=round($cumulMaj / $sessionCount, 2)?></th>
                                </tr>
                                <tr class="bg-cyan-700">
                                    <th class="p-2 text-right">Moyenne Cumulative</th>
                                    <th class="p-2 w-24"><?=round($cumulFinale / $sessionCount, 2)?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <?php endif;
                    
                    if (!$hasNotes): 
                    ?>
                        <p class="text-slate-400 text-center py-4">Aucune note disponible</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Informations supplémentaires -->
        <div class="mt-6 <?=$bg_one_color?> rounded-lg p-4">
            <h2 class="text-lg font-bold text-white mb-4">
                <i class="bi bi-info-circle mr-2"></i>
                Informations
            </h2>
            <div class="text-slate-300 text-sm">
                <p>• Cette page affiche uniquement vos informations personnelles et académiques.</p>
                <p>• Les notes affichées sont provisoires jusqu'à leur validation définitive.</p>
                <p>• Pour toute question, veuillez contacter le bureau du registraire.</p>
            </div>
        </div>

    </div>
</div>

</body>
</html>
