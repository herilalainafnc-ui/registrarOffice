<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // classement de toute l'université : réservé au personnel
/*
 * Une année universitaire de la page « Top Students ».
 *
 * Rendue dans la page au premier affichage, puis seule quand on change
 * d'année (src/meilleurs-etudiants.php avec ?fragment=1, src/js/annees-ajax.js).
 * Attend $dtb, $app_base et $_GET['page_annee'].
 */
?>
        <?php
        // Calculer l'année académique actuelle
        // Si on est entre janvier et août, l'année académique est (année-1)-(année)
        // Si on est entre septembre et décembre, l'année académique est (année)-(année+1)
        $currentMonth = intval(date('n'));
        $currentYear = intval(date('Y'));
        
        if ($currentMonth >= 9) {
            // Septembre à Décembre : année actuelle - année suivante
            $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
        } else {
            // Janvier à Août : année précédente - année actuelle
            $currentAcademicYear = ($currentYear - 1) . '-' . $currentYear;
        }
        
        // Pagination
        $page = isset($_GET['page_annee']) ? intval($_GET['page_annee']) : 1;
        $limit = 1;
        $offset = ($page - 1) * $limit;
        
        // Compter le nombre total d'années disponibles (seulement celles <= année actuelle)
        $countAnnees = $dtb->prepare('SELECT COUNT(DISTINCT session_year) as total FROM t_2023_session WHERE session_year IS NOT NULL AND session_year <= :currentYear');
        $countAnnees->execute([':currentYear' => $currentAcademicYear]);
        $totalAnnees = $countAnnees->fetch()['total'];
        $totalPages = ceil($totalAnnees / $limit);
        /* Une page hors bornes (ancien lien, saisie) montre l'année la plus proche. */
        $page = max(1, min($page, max(1, (int) $totalPages)));
        $offset = ($page - 1) * $limit;
        
        // Récupérer les années distinctes depuis t_2023_session (seulement <= année actuelle)
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
        <div class="year-card mb-4">
            
            <!-- Year Header -->
            <div class="year-header px-5 py-4">
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
                <?php foreach ($cycles as $cycle): 
                    
                    // Trouver le meilleur étudiant GLOBAL pour chaque session de ce cycle (toutes mentions confondues)
                    $bestGlobalBySession = [];
                    foreach ($all_sessions as $sess) {
                        $globalQuery = "
                            SELECT 
                                n.student_id,
                                ROUND(SUM(n.credit * CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) / SUM(n.credit), 2) as moyenne
                            FROM t_2023_notes n
                            INNER JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
                            WHERE n.session_id = :session_id
                            AND n.ajout = 1
                            AND n.grade REGEXP '^[0-9]+([.,][0-9]*)?$' AND n.cours_category <> 5 AND COALESCE(n.Sigle, '') NOT LIKE '%RELP 291%' AND COALESCE(n.title_cours, '') NOT LIKE '%formation spirituelle%'
                            AND n.remove = 0
                            AND e.remove != 1
                            AND COALESCE(NULLIF((SELECT MAX(i.niveau_std) FROM t_2024_inscription_session i WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1), 0), e.annee_etude) >= :niveau_min
                            AND COALESCE(NULLIF((SELECT MAX(i.niveau_std) FROM t_2024_inscription_session i WHERE i.student_id = n.student_id AND i.session_id = n.session_id AND COALESCE(i.`remove`, 0) != 1), 0), e.annee_etude) <= :niveau_max
                            GROUP BY n.student_id
                            HAVING SUM(n.credit) >= 25 AND ROUND(SUM(n.credit * CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) / SUM(n.credit), 2) >= 17
                            ORDER BY moyenne DESC
                            LIMIT 1
                        ";
                        $globalStmt = $dtb->prepare($globalQuery);
                        $globalStmt->execute([
                            ':session_id' => $sess['session_id'],
                            ':niveau_min' => $cycle['min'],
                            ':niveau_max' => $cycle['max']
                        ]);
                        $bestGlobal = $globalStmt->fetch(PDO::FETCH_ASSOC);
                        if ($bestGlobal) {
                            $bestGlobalBySession[$sess['session_id']] = $bestGlobal['student_id'];
                        }
                    }
                ?>
                
                <!-- Cycle Section -->
                <div class="cycle-tab p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="cycle-badge">
                            <i class="<?= $cycle['icon'] ?> mr-1.5"></i><?= $cycle['nom'] ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <?php foreach ($all_sessions as $session): 
                            $session_id = $session['session_id'];
                            $session_name = $session['session_name'];
                            $session_semester = $session['session_semester'];
                            $bestGlobalStudentId = $bestGlobalBySession[$session_id] ?? null;
                        ?>
                        
                        <!-- Session Card -->
                        <div class="semester-card overflow-hidden">
                            <div class="semester-header px-4 py-3 flex items-center gap-2">
                                <i class="bi bi-collection text-blue-400/80"></i>
                                <span class="text-sm font-medium text-slate-300"><?= $session_name ?> (S<?= $session_semester ?>)</span>
                            </div>
                            
                            <div class="p-3 space-y-2">
                                <?php 
                                $has_data = false;
                                
                                foreach ($all_mentions as $mention):
                                    $etude_envisage = $mention['filiere_description'];
                                    $sigle = $mention['filiere_sigle'];
                                    
                                    // Requête basée sur session_id comme dans transcriptSS.php
                                    // Critères: minimum 25 crédits ET moyenne générale (GPA) >= 17/20
                                    // Moyenne = SUM(credit * grade) / SUM(credit)
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
                                        HAVING SUM(n.credit) >= 25 AND ROUND(SUM(n.credit * CAST(REPLACE(n.grade, ',', '.') AS DECIMAL(5,2))) / SUM(n.credit), 2) >= 17
                                        ORDER BY moyenne DESC
                                        LIMIT 1
                                    ";
                                    
                                    $stmt = $dtb->prepare($query);
                                    $stmt->execute([
                                        ':session_id' => $session_id,
                                        ':etude_envisage' => $etude_envisage,
                                        ':niveau_min' => $cycle['min'],
                                        ':niveau_max' => $cycle['max']
                                    ]);
                                    
                                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($student):
                                        $has_data = true;
                                        $niveau = $student['annee_etude'] <= 3 ? 'L'.$student['annee_etude'] : 'M'.($student['annee_etude'] - 3);
                                        $isBestGlobal = ($bestGlobalStudentId && $student['student_id'] == $bestGlobalStudentId);
                                ?>
                                
                                <!-- Student Item -->
                                <div class="student-item p-3 flex items-center gap-3 <?= $isBestGlobal ? 'best-global' : '' ?>"
                                     data-photo="<?= (!empty($student['image_student']) && file_exists(__DIR__.'/../../app/photosetudiants/'.$student['image_student'])) ? $app_base.'/app/photosetudiants/'.$student['image_student'] : '' ?>"
                                     data-session-id="<?= $session_id ?>"
                                     data-session-name="<?= htmlspecialchars($session_name) ?>">
                                    
                                    <!-- Rank & Avatar -->
                                    <div class="flex-shrink-0 relative">
                                        <?php if (!empty($student['image_student']) && file_exists(__DIR__.'/../../app/photosetudiants/'.$student['image_student'])): ?>
                                            <img src="<?=$app_base?>/app/photosetudiants/<?= $student['image_student'] ?>" 
                                                 class="w-10 h-10 rounded-full object-cover ring-2 <?= $isBestGlobal ? 'ring-amber-400/60' : 'ring-blue-500/40' ?>">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-slate-700/80 flex items-center justify-center ring-2 <?= $isBestGlobal ? 'ring-amber-400/60' : 'ring-slate-600/50' ?>">
                                                <i class="bi bi-person-fill text-slate-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute -top-1 -left-1 w-5 h-5 rounded-full bg-gradient-to-br <?= $isBestGlobal ? 'from-amber-300 to-amber-500' : 'from-amber-400 to-amber-500' ?> flex items-center justify-center shadow-lg">
                                            <i class="bi <?= $isBestGlobal ? 'bi-trophy-fill' : 'bi-star-fill' ?> text-white text-xs"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Info -->
                                    <div class="flex-grow min-w-0">
                                        <div class="flex items-center gap-2">
                                            <a href="./student?id=<?= $student['id'] ?>&page=information" 
                                               class="text-sm font-medium text-slate-200 hover:text-blue-400 transition-colors truncate">
                                                <?= strtoupper($student['student_nom']) ?> <?= ucfirst(strtolower($student['student_prenom'])) ?>
                                            </a>
                                            <?php if ($isBestGlobal): ?>
                                            <span class="best-global-tag"><i class="bi bi-trophy-fill mr-1"></i>Meilleur du semestre</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="mention-tag"><?= $sigle ?></span>
                                            <span class="level-tag"><?= $niveau ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Score -->
                                    <div class="flex-shrink-0">
                                        <div class="score-box">
                                            <span class="text-lg font-bold text-blue-400"><?= number_format($student['moyenne'], 2) ?></span>
                                            <span class="text-xs text-slate-500">/20</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php 
                                    endif;
                                endforeach;
                                
                                if (!$has_data): 
                                ?>
                                <div class="empty-state p-6 text-center">
                                    <i class="bi bi-inbox text-slate-600 text-3xl"></i>
                                    <p class="text-xs text-slate-500 mt-2">Aucune donnée disponible</p>
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
        <div class="mt-6 mb-4 flex items-center justify-between">
            
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
        
