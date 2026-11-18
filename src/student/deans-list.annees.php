<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // classement de toute l'université : réservé au personnel
/*
 * Une année universitaire de la page « Dean's List ».
 *
 * Rendue dans la page au premier affichage, puis seule quand on change
 * d'année (src/deans-list.php avec ?fragment=1, src/js/annees-ajax.js).
 * Attend $dtb, $app_base et $_GET['page_annee'].
 */
?>
        <?php
        // Calculer l'année académique actuelle
        $currentMonth = intval(date('n'));
        $currentYear = intval(date('Y'));
        
        if ($currentMonth >= 9) {
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
        } else {
            $currentAcademicYear = ($currentYear - 1) . '-' . $currentYear;
        }
        
        // Pagination
        $page = isset($_GET['page_annee']) ? intval($_GET['page_annee']) : 1;
        $limit = 1;
        $offset = ($page - 1) * $limit;
        
        // Compter le nombre total d'années disponibles
        $countAnnees = $dtb->prepare('SELECT COUNT(DISTINCT session_year) as total FROM t_2023_session WHERE session_year IS NOT NULL AND session_year <= :currentYear');
        $countAnnees->execute([':currentYear' => $currentAcademicYear]);
        $totalAnnees = $countAnnees->fetch()['total'];
        $totalPages = ceil($totalAnnees / $limit);
        /* Une page hors bornes (ancien lien, saisie) montre l'année la plus proche. */
        $page = max(1, min($page, max(1, (int) $totalPages)));
        $offset = ($page - 1) * $limit;
        
        // Récupérer les années distinctes
        $annees = $dtb->prepare("SELECT DISTINCT session_year FROM t_2023_session WHERE session_year IS NOT NULL AND session_year <= :currentYear ORDER BY session_year DESC LIMIT $limit OFFSET $offset");
        $annees->execute([':currentYear' => $currentAcademicYear]);
        
        $mentions_list = $dtb->query('SELECT DISTINCT filiere_description, filiere_sigle FROM filiere ORDER BY filiere_sigle');
        $all_mentions = $mentions_list->fetchAll(PDO::FETCH_ASSOC);
        
        $cycles = [
            ['nom' => 'Licence', 'icon' => 'bi-mortarboard', 'min' => 1, 'max' => 3],
            ['nom' => 'Master', 'icon' => 'bi-award', 'min' => 4, 'max' => 5]
        ];
        
        while ($annee = $annees->fetch()) {
            $annee_scolaire = $annee['session_year'];
            if (empty($annee_scolaire)) continue;
            
            // Récupérer les sessions de cette année
            $sessions = $dtb->query("SELECT session_id, session_name, session_semester FROM t_2023_session WHERE session_year = '".$annee_scolaire."' ORDER BY session_semester ASC");
            $all_sessions = $sessions->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <!-- Année Card -->
        <div class="dl-year-card mb-4">
            
            <!-- Year Header -->
            <div class="dl-year-header px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/15 border border-blue-500/25 flex items-center justify-center">
                        <i class="bi bi-calendar3 text-blue-400"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-100"><?= $annee_scolaire ?></h2>
                        <p class="text-xs text-slate-500">Année universitaire</p>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="p-4 space-y-4">
                <?php foreach ($cycles as $cycle): ?>
                
                <!-- Cycle Section -->
                <div class="dl-cycle-tab p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="dl-cycle-badge">
                            <i class="<?= $cycle['icon'] ?> mr-1.5"></i><?= $cycle['nom'] ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <?php foreach ($all_sessions as $session): 
                            $session_id = $session['session_id'];
                            $session_name = $session['session_name'];
                            $session_semester = $session['session_semester'];
                        ?>
                        
                        <!-- Session Card -->
                        <div class="dl-semester-card overflow-hidden">
                            <div class="dl-semester-header px-4 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-collection text-blue-400/80"></i>
                                    <span class="text-sm font-medium text-slate-300"><?= $session_name ?> (S<?= $session_semester ?>)</span>
                                </div>
                                <?php
                                // Compter le total d'étudiants Dean's List pour ce semestre et cycle
                                $countQuery = "
                                    SELECT COUNT(DISTINCT n.student_id) as total
                                    FROM t_2023_notes n
                                    INNER JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
                                    WHERE n.session_id = :session_id
                                    AND n.ajout = 1
                                    AND n.grade REGEXP '^[0-9]+([.,][0-9]*)?$' AND n.cours_category <> 5 AND COALESCE(n.Sigle, '') NOT LIKE '%RELP 291%' AND COALESCE(n.title_cours, '') NOT LIKE '%formation spirituelle%'
                                    AND n.remove = 0
                                    AND e.remove != 1
                                    AND COALESCE(NULLIF((SELECT MAX(i.niveau_std) FROM t_2024_inscription_session i WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1), 0), e.annee_etude) >= :niveau_min
                                    AND COALESCE(NULLIF((SELECT MAX(i.niveau_std) FROM t_2024_inscription_session i WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1), 0), e.annee_etude) <= :niveau_max
                                    AND n.student_id IN (
                                        SELECT student_id 
                                        FROM t_2023_notes 
                                        WHERE session_id = :session_id2
                                        AND ajout = 1 AND grade > 0 AND remove = 0
                                        GROUP BY student_id
                                        HAVING SUM(credit) >= 30 AND MIN(grade) >= 15
                                    )
                                ";
                                $countStmt = $dtb->prepare($countQuery);
                                $countStmt->execute([
                                    ':session_id' => $session_id,
                                    ':session_id2' => $session_id,
                                    ':niveau_min' => $cycle['min'],
                                    ':niveau_max' => $cycle['max']
                                ]);
                                $totalDeansListCycle = $countStmt->fetch()['total'];
                                ?>
                                <?php if ($totalDeansListCycle > 0): ?>
                                <span class="dl-count-badge"><?= $totalDeansListCycle ?> étudiant<?= $totalDeansListCycle > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-3 space-y-3">
                                <?php 
                                $has_data = false;
                                
                                foreach ($all_mentions as $mention):
                                    $etude_envisage = $mention['filiere_description'];
                                    $sigle = $mention['filiere_sigle'];
                                    
                                    // Requête pour obtenir TOUS les étudiants avec moyenne >= 15
                                    // Critères: minimum 30 crédits ET chaque cours >= 15/20
                                    $query = "
                                        SELECT 
                                            e.id,
                                            n.student_id,
                                            e.student_nom,
                                            e.student_prenom,
                                            e.annee_etude,
                                            e.image_student,
                                            SUM(n.credit) as total_credits,
                                            SUM(n.credit * CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) as total_points,
                                            ROUND(SUM(n.credit * CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) / SUM(n.credit), 2) as moyenne,
                                            MIN(CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) as note_min
                                        FROM t_2023_notes n
                                        INNER JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
                                        WHERE n.session_id = :session_id
                                        AND n.ajout = 1
                                        AND n.grade REGEXP '^[0-9]+([.,][0-9]*)?$' AND n.cours_category <> 5 AND COALESCE(n.Sigle, '') NOT LIKE '%RELP 291%' AND COALESCE(n.title_cours, '') NOT LIKE '%formation spirituelle%'
                                        AND n.remove = 0
                                        AND e.remove != 1
                                        AND e.etude_envisage = :etude_envisage
                                        AND COALESCE(NULLIF((SELECT MAX(i.niveau_std) FROM t_2024_inscription_session i WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1), 0), e.annee_etude) >= :niveau_min
                                        AND COALESCE(NULLIF((SELECT MAX(i.niveau_std) FROM t_2024_inscription_session i WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1), 0), e.annee_etude) <= :niveau_max
                                        GROUP BY n.student_id
                                        HAVING SUM(n.credit) >= 30 AND MIN(CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) >= 15
                                        ORDER BY moyenne DESC
                                    ";
                                    
                                    $stmt = $dtb->prepare($query);
                                    $stmt->execute([
                                        ':session_id' => $session_id,
                                        ':etude_envisage' => $etude_envisage,
                                        ':niveau_min' => $cycle['min'],
                                        ':niveau_max' => $cycle['max']
                                    ]);
                                    
                                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($students) > 0):
                                        $has_data = true;
                                ?>
                                
                                <!-- Mention Section -->
                                <div class="dl-mention-section">
                                    <div class="dl-mention-header flex items-center justify-between">
                                        <span class="text-sm font-medium text-blue-300"><?= $sigle ?></span>
                                        <span class="text-xs text-slate-500"><?= count($students) ?> étudiant<?= count($students) > 1 ? 's' : '' ?></span>
                                    </div>
                                    
                                    <div class="p-3 space-y-2">
                                        <?php 
                                        $rank = 0;
                                        foreach ($students as $student):
                                            $rank++;
                                            $niveau = $student['annee_etude'] <= 3 ? 'L'.$student['annee_etude'] : 'M'.($student['annee_etude'] - 3);
                                            
                                            // Déterminer la classe du badge de rang
                                            if ($rank == 1) {
                                                $rankClass = 'dl-rank-1';
                                            } elseif ($rank == 2) {
                                                $rankClass = 'dl-rank-2';
                                            } elseif ($rank == 3) {
                                                $rankClass = 'dl-rank-3';
                                            } else {
                                                $rankClass = 'dl-rank-other';
                                            }
                                        ?>
                                
                                        <!-- Student Item -->
                                        <div class="dl-student-item p-3 flex items-center gap-3"
                                             data-photo="<?= (!empty($student['image_student']) && file_exists(__DIR__.'/../../app/photosetudiants/'.$student['image_student'])) ? $app_base.'/app/photosetudiants/'.$student['image_student'] : '' ?>"
                                             data-session-id="<?= $session_id ?>"
                                             data-session-name="<?= htmlspecialchars($session_name) ?>"
                                             data-mention="<?= htmlspecialchars($sigle) ?>"
                                             data-cycle="<?= $cycle['nom'] ?>">
                                            
                                            <!-- Rank Badge -->
                                            <div class="dl-rank-badge <?= $rankClass ?>">
                                                <?= $rank ?>
                                            </div>
                                            
                                            <!-- Avatar -->
                                            <div class="flex-shrink-0 relative">
                                                <?php if (!empty($student['image_student']) && file_exists(__DIR__.'/../../app/photosetudiants/'.$student['image_student'])): ?>
                                                    <img src="<?=$app_base?>/app/photosetudiants/<?= $student['image_student'] ?>" 
                                                         class="w-10 h-10 rounded-full object-cover ring-2 ring-blue-500/40">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 rounded-full bg-slate-700/80 flex items-center justify-center ring-2 ring-slate-600/50">
                                                        <i class="bi bi-person-fill text-slate-400"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Info -->
                                            <div class="flex-grow min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <a href="./student?id=<?= $student['id'] ?>&page=information" 
                                                       class="text-sm font-medium text-slate-200 hover:text-blue-400 transition-colors truncate">
                                                        <?= strtoupper($student['student_nom']) ?> <?= ucfirst(strtolower($student['student_prenom'])) ?>
                                                    </a>
                                                    <?php if ($rank == 1): ?>
                                                    <span class="dl-deans-badge"><i class="bi bi-star-fill mr-1"></i>Top mention</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="dl-level-tag"><?= $niveau ?></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Score -->
                                            <div class="flex-shrink-0">
                                                <div class="dl-score-box">
                                                    <span class="text-lg font-bold text-blue-400"><?= number_format($student['moyenne'], 2) ?></span>
                                                    <span class="text-xs text-slate-500">/20</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <?php 
                                    endif;
                                endforeach;
                                
                                if (!$has_data): 
                                ?>
                                <div class="dl-empty-state p-6 text-center">
                                    <i class="bi bi-inbox text-slate-600 text-3xl"></i>
                                    <p class="text-xs text-slate-500 mt-2">Aucun étudiant avec moyenne ≥ 15</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php } ?>
        
        <!-- Navigation de pagination -->
        <div class="dl-pagination mt-6 mb-4 flex items-center justify-between">
            
            <!-- Bouton Précédent -->
            <?php if ($page > 1): ?>
            <a href="?page_annee=<?= $page - 1 ?>" data-page-annee="<?= $page - 1 ?>" data-pt-skip="1"
               class="py-3 px-5 rounded-xl border border-slate-600/50 bg-slate-700/30 hover:bg-slate-700/50 transition-all duration-200 flex items-center gap-2 group">
                <i class="bi bi-chevron-left text-slate-400 group-hover:-translate-x-1 transition-transform"></i>
                <span class="text-slate-300 font-medium">Années récentes</span>
            </a>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
            
            <!-- Indicateur de page -->
            <div class="flex items-center gap-2">
                <span class="text-slate-500 text-sm">Page</span>
                <span class="px-3 py-1 rounded-lg bg-blue-500/20 border border-blue-500/30 text-blue-400 font-semibold"><?= $page ?></span>
                <span class="text-slate-500 text-sm">/ <?= $totalPages ?></span>
            </div>
            
            <!-- Bouton Suivant -->
            <?php if ($page < $totalPages): ?>
            <a href="?page_annee=<?= $page + 1 ?>" data-page-annee="<?= $page + 1 ?>" data-pt-skip="1"
               class="py-3 px-5 rounded-xl border border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/20 transition-all duration-200 flex items-center gap-2 group">
                <span class="text-blue-400 font-medium">Années précédentes</span>
                <i class="bi bi-chevron-right text-blue-400 group-hover:translate-x-1 transition-transform"></i>
            </a>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
            
        </div>
        
