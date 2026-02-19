<?php
/**
 * Meilleurs Étudiants par Semestre, par Mention et par Cycle (Licence/Master)
 * Style shadcn - Minimaliste avec couleur bleue
 */
?>

<style>
.best-students-container {
    scrollbar-width: thin;
    scrollbar-color: rgba(59, 130, 246, 0.3) transparent;
}
.best-students-container::-webkit-scrollbar {
    width: 6px;
}
.best-students-container::-webkit-scrollbar-track {
    background: transparent;
}
.best-students-container::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.3);
    border-radius: 3px;
}
.best-students-container::-webkit-scrollbar-thumb:hover {
    background: rgba(59, 130, 246, 0.5);
}

.page-header {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(51, 65, 85, 0.5);
}

.year-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 16px;
    backdrop-filter: blur(8px);
    overflow: hidden;
}

.year-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
    border-bottom: 1px solid rgba(51, 65, 85, 0.4);
}

.cycle-tab {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 10px;
    transition: all 0.2s ease;
}
.cycle-tab:hover {
    border-color: rgba(59, 130, 246, 0.4);
}

.semester-card {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.3);
    border-radius: 12px;
    transition: all 0.2s ease;
}
.semester-card:hover {
    border-color: rgba(59, 130, 246, 0.3);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.semester-header {
    background: rgba(30, 41, 59, 0.5);
    border-bottom: 1px solid rgba(51, 65, 85, 0.3);
}

.student-item {
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(51, 65, 85, 0.3);
    border-radius: 10px;
    transition: all 0.15s ease;
}
.student-item:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(59, 130, 246, 0.4);
    transform: translateX(4px);
}

.cycle-badge {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.15));
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.score-box {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
    border: 1px solid rgba(59, 130, 246, 0.25);
    border-radius: 10px;
    padding: 6px 14px;
    min-width: 80px;
    text-align: center;
}

.mention-tag {
    background: rgba(59, 130, 246, 0.1);
    color: #60a5fa;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
}

.level-tag {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
}

.best-global-tag {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.25), rgba(245, 158, 11, 0.2));
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.4);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    animation: pulse-gold 2s ease-in-out infinite;
}

@keyframes pulse-gold {
    0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.3); }
    50% { box-shadow: 0 0 8px 2px rgba(251, 191, 36, 0.2); }
}

.student-item.best-global {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), rgba(30, 41, 59, 0.4));
    border-color: rgba(251, 191, 36, 0.3);
}
.student-item.best-global:hover {
    border-color: rgba(251, 191, 36, 0.5);
}

.empty-state {
    background: rgba(30, 41, 59, 0.3);
    border: 1px dashed rgba(51, 65, 85, 0.4);
    border-radius: 10px;
}

/* Light mode */
[data-theme="light"] .page-header {
    background: rgba(255, 255, 255, 0.9);
    border-bottom-color: rgba(203, 213, 225, 0.8);
}
[data-theme="light"] .year-card {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .year-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(37, 99, 235, 0.03));
    border-bottom-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .cycle-tab {
    background: rgba(241, 245, 249, 0.8);
    border-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .semester-card {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .semester-header {
    background: rgba(241, 245, 249, 0.8);
    border-bottom-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .student-item {
    background: rgba(248, 250, 252, 0.8);
    border-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .student-item:hover {
    background: rgba(241, 245, 249, 0.9);
    border-color: rgba(59, 130, 246, 0.4);
}

.export-btn {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.export-btn-pdf {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}
.export-btn-pdf:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}
.export-btn-excel {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #4ade80;
}
.export-btn-excel:hover {
    background: rgba(34, 197, 94, 0.25);
    border-color: rgba(34, 197, 94, 0.5);
}

.export-select {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(51, 65, 85, 0.5);
    color: #e2e8f0;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    cursor: pointer;
    min-width: 180px;
}
.export-select:focus {
    outline: none;
    border-color: rgba(59, 130, 246, 0.5);
}
.export-select option {
    background: #1e293b;
    color: #e2e8f0;
}

/* Modal export */
.export-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}
.export-modal.active {
    display: flex;
}
.export-modal-content {
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid rgba(51, 65, 85, 0.5);
    border-radius: 16px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
}
</style>

<!-- Libraries pour export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="flex flex-col h-full">
    
    <!-- Header fixe -->
    <div class="page-header px-6 py-4 flex-shrink-0">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/10 border border-blue-500/30 flex items-center justify-center">
                    <i class="bi bi-trophy-fill text-blue-400 text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-100 tracking-tight">Top Student</h1>
                    <p class="text-xs text-slate-400 mt-0.5">GPA ≥ 17/20 • Minimum 25 crédits • Par semestre et mention</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openExportModal('pdf')" class="export-btn export-btn-pdf">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </button>
                <button onclick="openExportModal('excel')" class="export-btn export-btn-excel">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Export -->
    <div id="exportModal" class="export-modal">
        <div class="export-modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-100">
                    <i class="bi bi-download text-blue-400 mr-2"></i>Exporter les données
                </h3>
                <button onclick="closeExportModal()" class="text-slate-400 hover:text-slate-200">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-slate-400 block mb-2">Sélectionner le semestre</label>
                    <select id="exportSemesterSelect" class="export-select w-full">
                        <option value="">-- Choisir un semestre --</option>
                    </select>
                </div>
                
                <div>
                    <label class="text-xs text-slate-400 block mb-2">Cycle</label>
                    <select id="exportCycleSelect" class="export-select w-full">
                        <option value="">Tous les cycles</option>
                        <option value="Licence">Licence uniquement</option>
                        <option value="Master">Master uniquement</option>
                    </select>
                </div>
                
                <div class="flex gap-2 mt-6">
                    <button onclick="closeExportModal()" class="flex-1 py-2 px-4 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 transition-colors">
                        Annuler
                    </button>
                    <button onclick="executeExport()" id="executeExportBtn" class="flex-1 py-2 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors">
                        <i class="bi bi-download mr-1"></i> Exporter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu scrollable -->
    <div class="best-students-container flex-1 overflow-y-auto overflow-x-hidden p-4">
        
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
                                ROUND(SUM(n.credit * n.grade) / SUM(n.credit), 2) as moyenne
                            FROM t_2023_notes n
                            INNER JOIN tbl_2024_etudiant e ON n.student_id = e.student_id
                            WHERE n.session_id = :session_id
                            AND n.ajout = 1
                            AND n.grade > 0
                            AND n.remove = 0
                            AND e.remove != 1
                            AND e.annee_etude >= :niveau_min
                            AND e.annee_etude <= :niveau_max
                            GROUP BY n.student_id
                            HAVING SUM(n.credit) >= 25 AND ROUND(SUM(n.credit * n.grade) / SUM(n.credit), 2) >= 17
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
                                            SUM(n.credit * n.grade) as total_points,
                                            ROUND(SUM(n.credit * n.grade) / SUM(n.credit), 2) as moyenne,
                                            MIN(n.grade) as note_min
                                        FROM t_2023_notes n
                                        INNER JOIN tbl_2024_etudiant e ON n.student_id = e.student_id
                                        WHERE n.session_id = :session_id
                                        AND n.ajout = 1
                                        AND n.grade > 0
                                        AND n.remove = 0
                                        AND e.remove != 1
                                        AND e.etude_envisage = :etude_envisage
                                        AND e.annee_etude >= :niveau_min
                                        AND e.annee_etude <= :niveau_max
                                        GROUP BY n.student_id
                                        HAVING SUM(n.credit) >= 25 AND ROUND(SUM(n.credit * n.grade) / SUM(n.credit), 2) >= 17
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
            <a href="?page=meilleurs-etudiants&page_annee=<?= $page - 1 ?>" 
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
            <a href="?page=meilleurs-etudiants&page_annee=<?= $page + 1 ?>" 
               class="py-3 px-5 rounded-xl border border-blue-500/30 bg-blue-500/10 hover:bg-blue-500/20 transition-all duration-200 flex items-center gap-2 group">
                <span class="text-blue-400 font-medium">Années précédentes</span>
                <i class="bi bi-chevron-right text-blue-400 group-hover:translate-x-1 transition-transform"></i>
            </a>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
            
        </div>
        
    </div>
</div>

<script>
let currentExportType = 'pdf';

// Ouvrir le modal d'export
function openExportModal(type) {
    currentExportType = type;
    const modal = document.getElementById('exportModal');
    modal.classList.add('active');
    
    // Remplir le select des semestres
    populateSemesterSelect();
    
    // Mettre à jour le bouton
    const btn = document.getElementById('executeExportBtn');
    if (type === 'pdf') {
        btn.innerHTML = '<i class="bi bi-file-earmark-pdf mr-1"></i> Exporter PDF';
        btn.className = 'flex-1 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium transition-colors';
    } else {
        btn.innerHTML = '<i class="bi bi-file-earmark-excel mr-1"></i> Exporter Excel';
        btn.className = 'flex-1 py-2 px-4 rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium transition-colors';
    }
}

function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
}

// Remplir le select des semestres
function populateSemesterSelect() {
    const select = document.getElementById('exportSemesterSelect');
    const sessions = new Map();
    
    document.querySelectorAll('.student-item').forEach(item => {
        const sessionId = item.dataset.sessionId;
        const sessionName = item.dataset.sessionName;
        if (sessionId && sessionName && !sessions.has(sessionId)) {
            sessions.set(sessionId, sessionName);
        }
    });
    
    select.innerHTML = '<option value="">-- Choisir un semestre --</option>';
    sessions.forEach((name, id) => {
        select.innerHTML += `<option value="${id}">${name}</option>`;
    });
}

// Exécuter l'export
function executeExport() {
    const sessionId = document.getElementById('exportSemesterSelect').value;
    const cycleFilter = document.getElementById('exportCycleSelect').value;
    
    if (!sessionId) {
        alert('Veuillez sélectionner un semestre');
        return;
    }
    
    if (currentExportType === 'pdf') {
        exportToPDF(sessionId, cycleFilter);
    } else {
        exportToExcel(sessionId, cycleFilter);
    }
    
    closeExportModal();
}

// Collecter les données des meilleurs étudiants pour un semestre
function collectStudentData(sessionId, cycleFilter) {
    const data = [];
    document.querySelectorAll('.student-item').forEach(item => {
        // Filtrer par session
        if (item.dataset.sessionId !== sessionId) return;
        
        const nameEl = item.querySelector('a');
        const mentionEl = item.querySelector('.mention-tag');
        const levelEl = item.querySelector('.level-tag');
        const scoreEl = item.querySelector('.score-box .text-lg');
        const isBestGlobal = item.classList.contains('best-global');
        const photoUrl = item.dataset.photo || '';
        
        // Trouver le semestre et cycle parent
        const semesterCard = item.closest('.semester-card');
        const cycleTab = item.closest('.cycle-tab');
        const yearCard = item.closest('.year-card');
        
        const semester = semesterCard ? semesterCard.querySelector('.semester-header span')?.textContent.trim() : '';
        const cycle = cycleTab ? cycleTab.querySelector('.cycle-badge')?.textContent.trim() : '';
        const year = yearCard ? yearCard.querySelector('.year-header h2')?.textContent.trim() : '';
        
        // Filtrer par cycle si spécifié
        if (cycleFilter && !cycle.includes(cycleFilter)) return;
        
        data.push({
            annee: year,
            cycle: cycle,
            semestre: semester,
            nom: nameEl ? nameEl.textContent.trim() : '',
            mention: mentionEl ? mentionEl.textContent.trim() : '',
            niveau: levelEl ? levelEl.textContent.trim() : '',
            moyenne: scoreEl ? scoreEl.textContent.trim() : '',
            meilleurGlobal: isBestGlobal,
            photo: photoUrl
        });
    });
    return data;
}

// Convertir image en base64
async function getImageBase64(url) {
    if (!url) return null;
    try {
        const response = await fetch(url);
        const blob = await response.blob();
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
        });
    } catch (e) {
        return null;
    }
}

// Export PDF avec photos
async function exportToPDF(sessionId, cycleFilter) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    const data = collectStudentData(sessionId, cycleFilter);
    
    if (data.length === 0) {
        alert('Aucune donnée à exporter pour ce semestre');
        return;
    }
    
    // Trier AVANT de charger les photos
    data.sort((a, b) => {
        if (a.meilleurGlobal && !b.meilleurGlobal) return -1;
        if (!a.meilleurGlobal && b.meilleurGlobal) return 1;
        return parseFloat(b.moyenne) - parseFloat(a.moyenne);
    });
    
    const semesterName = data[0]?.semestre || 'Semestre';
    const yearName = data[0]?.annee || '';
    
    // En-tête avec fond bleu
    doc.setFillColor(30, 64, 175);
    doc.rect(0, 0, 210, 35, 'F');
    
    doc.setFontSize(18);
    doc.setTextColor(255, 255, 255);
    doc.setFont(undefined, 'bold');
    doc.text('Meilleurs Etudiants', 14, 15);
    
    doc.setFont(undefined, 'normal');
    doc.setFontSize(11);
    doc.text(`${semesterName} - ${yearName}`, 14, 25);
    
    doc.setFontSize(9);
    doc.setTextColor(200, 200, 200);
    doc.text('Exporté le ' + new Date().toLocaleDateString('fr-FR'), 150, 25);
    
    // Charger les photos APRÈS le tri (dans le bon ordre)
    const photoPromises = data.map(d => getImageBase64(d.photo));
    const photos = await Promise.all(photoPromises);
    
    let yPos = 45;
    const rowHeight = 25;
    const pageHeight = 280;
    
    // En-tête du tableau
    doc.setFillColor(241, 245, 249);
    doc.rect(10, yPos - 5, 190, 10, 'F');
    doc.setFontSize(9);
    doc.setTextColor(71, 85, 105);
    doc.setFont(undefined, 'bold');
    doc.text('Photo', 22, yPos, { align: 'center' });
    doc.text('Nom Complet', 68, yPos, { align: 'center' });
    doc.text('Mention', 115, yPos, { align: 'center' });
    doc.text('Niveau', 140, yPos, { align: 'center' });
    doc.text('Moyenne', 165, yPos, { align: 'center' });
    doc.text('Statut', 188, yPos, { align: 'center' });
    
    yPos += 12;
    doc.setFont(undefined, 'normal');
    
    for (let i = 0; i < data.length; i++) {
        const d = data[i];
        const photo = photos[i];
        
        // Nouvelle page si nécessaire
        if (yPos + rowHeight > pageHeight) {
            doc.addPage();
            yPos = 20;
            
            // Réafficher l'en-tête
            doc.setFillColor(241, 245, 249);
            doc.rect(10, yPos - 5, 190, 10, 'F');
            doc.setFontSize(9);
            doc.setTextColor(71, 85, 105);
            doc.setFont(undefined, 'bold');
            doc.text('Photo', 22, yPos, { align: 'center' });
            doc.text('Nom Complet', 68, yPos, { align: 'center' });
            doc.text('Mention', 115, yPos, { align: 'center' });
            doc.text('Niveau', 140, yPos, { align: 'center' });
            doc.text('Moyenne', 165, yPos, { align: 'center' });
            doc.text('Statut', 188, yPos, { align: 'center' });
            yPos += 12;
            doc.setFont(undefined, 'normal');
        }
        
        // Fond alternée
        if (i % 2 === 0) {
            doc.setFillColor(248, 250, 252);
            doc.rect(10, yPos - 5, 190, rowHeight, 'F');
        }
        
        // Fond doré pour le meilleur
        if (d.meilleurGlobal) {
            doc.setFillColor(254, 243, 199);
            doc.rect(10, yPos - 5, 190, rowHeight, 'F');
        }
        
        // Photo
        if (photo) {
            try {
                doc.addImage(photo, 'JPEG', 13, yPos - 3, 18, 18);
            } catch (e) {
                // Photo non chargée, dessiner un placeholder
                doc.setFillColor(148, 163, 184);
                doc.circle(22, yPos + 6, 9, 'F');
            }
        } else {
            // Placeholder cercle
            doc.setFillColor(148, 163, 184);
            doc.circle(22, yPos + 6, 9, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(12);
            doc.text('?', 22, yPos + 9, { align: 'center' });
        }
        
        // Formater le nom (première lettre de chaque mot en majuscule)
        function formatName(name) {
            return name.split(' ').map(word => {
                if (word.length === 0) return '';
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            }).join(' ');
        }
        
        // Découper le nom si trop long
        const nomFormate = formatName(d.nom);
        const maxWidth = 55; // largeur max pour le nom
        doc.setFontSize(9);
        const nomLines = doc.splitTextToSize(nomFormate, maxWidth);
        
        // Texte - Nom
        doc.setTextColor(30, 41, 59);
        doc.setFont(undefined, d.meilleurGlobal ? 'bold' : 'normal');
        
        if (nomLines.length > 1) {
            doc.text(nomLines[0], 36, yPos + 3);
            doc.text(nomLines[1], 36, yPos + 9);
        } else {
            doc.text(nomLines[0], 36, yPos + 6);
        }
        
        doc.setFont(undefined, 'normal');
        doc.setFontSize(7);
        doc.setTextColor(100, 116, 139);
        doc.text(d.cycle, 36, yPos + 15);
        
        // Mention (centré)
        doc.setFontSize(10);
        doc.setTextColor(59, 130, 246);
        doc.text(d.mention, 115, yPos + 7, { align: 'center' });
        
        // Niveau (centré)
        doc.setTextColor(71, 85, 105);
        doc.text(d.niveau, 140, yPos + 7, { align: 'center' });
        
        // Moyenne avec fond (centré)
        doc.setFillColor(219, 234, 254);
        doc.roundedRect(152, yPos - 1, 26, 14, 2, 2, 'F');
        doc.setTextColor(30, 64, 175);
        doc.setFont(undefined, 'bold');
        doc.text(d.moyenne, 165, yPos + 8, { align: 'center' });
        
        // Badge meilleur (centré)
        if (d.meilleurGlobal) {
            doc.setFillColor(251, 191, 36);
            doc.roundedRect(180, yPos - 1, 18, 14, 2, 2, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(8);
            doc.setFont(undefined, 'bold');
            doc.text('TOP', 189, yPos + 7, { align: 'center' });
        }
        
        yPos += rowHeight;
    }
    
    // Pied de page
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(148, 163, 184);
        doc.text(`Page ${i} / ${pageCount}`, 100, 290, { align: 'center' });
    }
    
    const fileName = `meilleurs-etudiants-${semesterName.replace(/[^a-zA-Z0-9]/g, '-')}.pdf`;
    doc.save(fileName);
}

// Export Excel
function exportToExcel(sessionId, cycleFilter) {
    const data = collectStudentData(sessionId, cycleFilter);
    
    if (data.length === 0) {
        alert('Aucune donnée à exporter pour ce semestre');
        return;
    }
    
    const semesterName = data[0]?.semestre || 'Semestre';
    
    // Trier par meilleur global d'abord, puis par moyenne
    data.sort((a, b) => {
        if (a.meilleurGlobal && !b.meilleurGlobal) return -1;
        if (!a.meilleurGlobal && b.meilleurGlobal) return 1;
        return parseFloat(b.moyenne) - parseFloat(a.moyenne);
    });
    
    // Préparer les données pour Excel
    const excelData = data.map((d, index) => ({
        'Rang': index + 1,
        'Année Académique': d.annee,
        'Cycle': d.cycle,
        'Nom Complet': d.nom,
        'Mention': d.mention,
        'Niveau': d.niveau,
        'Moyenne': parseFloat(d.moyenne) || 0,
        'Meilleur du Semestre': d.meilleurGlobal ? '★ OUI' : ''
    }));
    
    // Créer le workbook
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(excelData);
    
    // Ajuster la largeur des colonnes
    ws['!cols'] = [
        { wch: 6 },  // Rang
        { wch: 15 }, // Année
        { wch: 10 }, // Cycle
        { wch: 35 }, // Nom
        { wch: 10 }, // Mention
        { wch: 8 },  // Niveau
        { wch: 10 }, // Moyenne
        { wch: 18 }  // Meilleur
    ];
    
    XLSX.utils.book_append_sheet(wb, ws, semesterName.substring(0, 31));
    
    // Télécharger
    const fileName = `meilleurs-etudiants-${semesterName.replace(/[^a-zA-Z0-9]/g, '-')}.xlsx`;
    XLSX.writeFile(wb, fileName);
}

// Fermer modal avec Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeExportModal();
});
</script>