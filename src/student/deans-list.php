<?php
/**
 * Dean's List - Tous les étudiants ayant une moyenne >= 15
 * Affiche tous les étudiants par semestre, mention et cycle
 * Style shadcn - Minimaliste avec couleur bleue
 */
?>

<style>
.deans-list-container {
    scrollbar-width: thin;
    scrollbar-color: rgba(59, 130, 246, 0.3) transparent;
}
.deans-list-container::-webkit-scrollbar {
    width: 6px;
}
.deans-list-container::-webkit-scrollbar-track {
    background: transparent;
}
.deans-list-container::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.3);
    border-radius: 3px;
}
.deans-list-container::-webkit-scrollbar-thumb:hover {
    background: rgba(59, 130, 246, 0.5);
}

.dl-page-header {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(51, 65, 85, 0.5);
}

.dl-year-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 16px;
    backdrop-filter: blur(8px);
    overflow: hidden;
}

.dl-year-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
    border-bottom: 1px solid rgba(51, 65, 85, 0.4);
}

.dl-cycle-tab {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 10px;
    transition: all 0.2s ease;
}
.dl-cycle-tab:hover {
    border-color: rgba(59, 130, 246, 0.4);
}

.dl-semester-card {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.3);
    border-radius: 12px;
    transition: all 0.2s ease;
}
.dl-semester-card:hover {
    border-color: rgba(59, 130, 246, 0.3);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.dl-semester-header {
    background: rgba(30, 41, 59, 0.5);
    border-bottom: 1px solid rgba(51, 65, 85, 0.3);
}

.dl-mention-section {
    background: rgba(30, 41, 59, 0.3);
    border: 1px solid rgba(51, 65, 85, 0.2);
    border-radius: 8px;
    margin-bottom: 12px;
}

.dl-mention-header {
    background: rgba(59, 130, 246, 0.08);
    border-bottom: 1px solid rgba(51, 65, 85, 0.2);
    padding: 8px 12px;
    border-radius: 8px 8px 0 0;
}

.dl-student-item {
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(51, 65, 85, 0.3);
    border-radius: 10px;
    transition: all 0.15s ease;
}
.dl-student-item:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(59, 130, 246, 0.4);
    transform: translateX(4px);
}

.dl-cycle-badge {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.15));
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.dl-score-box {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
    border: 1px solid rgba(59, 130, 246, 0.25);
    border-radius: 10px;
    padding: 6px 14px;
    min-width: 80px;
    text-align: center;
}

.dl-mention-tag {
    background: rgba(59, 130, 246, 0.1);
    color: #60a5fa;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
}

.dl-level-tag {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
}

.dl-rank-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
}

.dl-rank-1 {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #1e293b;
}
.dl-rank-2 {
    background: linear-gradient(135deg, #94a3b8, #64748b);
    color: #1e293b;
}
.dl-rank-3 {
    background: linear-gradient(135deg, #cd7f32, #b45309);
    color: white;
}
.dl-rank-other {
    background: rgba(100, 116, 139, 0.3);
    color: #94a3b8;
}

.dl-deans-badge {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.2));
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.4);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    animation: pulse-blue 2s ease-in-out infinite;
}

@keyframes pulse-blue {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.3); }
    50% { box-shadow: 0 0 8px 2px rgba(59, 130, 246, 0.2); }
}

.dl-empty-state {
    background: rgba(30, 41, 59, 0.3);
    border: 1px dashed rgba(51, 65, 85, 0.4);
    border-radius: 10px;
}

.dl-count-badge {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Light mode */
[data-theme="light"] .dl-page-header {
    background: rgba(255, 255, 255, 0.9);
    border-bottom-color: rgba(203, 213, 225, 0.8);
}
[data-theme="light"] .dl-year-card {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .dl-year-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(37, 99, 235, 0.03));
    border-bottom-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .dl-cycle-tab {
    background: rgba(241, 245, 249, 0.8);
    border-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .dl-semester-card {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .dl-semester-header {
    background: rgba(241, 245, 249, 0.8);
    border-bottom-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .dl-student-item {
    background: rgba(248, 250, 252, 0.8);
    border-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .dl-student-item:hover {
    background: rgba(241, 245, 249, 0.9);
    border-color: rgba(59, 130, 246, 0.4);
}
[data-theme="light"] .dl-mention-section {
    background: rgba(248, 250, 252, 0.6);
    border-color: rgba(203, 213, 225, 0.4);
}
[data-theme="light"] .dl-mention-header {
    background: rgba(59, 130, 246, 0.06);
}

.dl-export-btn {
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
.dl-export-btn-pdf {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}
.dl-export-btn-pdf:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}
.dl-export-btn-excel {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #4ade80;
}
.dl-export-btn-excel:hover {
    background: rgba(34, 197, 94, 0.25);
    border-color: rgba(34, 197, 94, 0.5);
}

.dl-export-select {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(51, 65, 85, 0.5);
    color: #e2e8f0;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    cursor: pointer;
    min-width: 180px;
}
.dl-export-select:focus {
    outline: none;
    border-color: rgba(59, 130, 246, 0.5);
}
.dl-export-select option {
    background: #1e293b;
    color: #e2e8f0;
}

/* Modal export */
.dl-export-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}
.dl-export-modal.active {
    display: flex;
}
.dl-export-modal-content {
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
    <div class="dl-page-header px-6 py-4 flex-shrink-0">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/10 border border-blue-500/30 flex items-center justify-center">
                    <i class="bi bi-star-fill text-blue-400 text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-100 tracking-tight">Dean's List</h1>
                    <p class="text-xs text-slate-400 mt-0.5">Étudiants d'excellence (moyenne ≥ 15/20)</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openDLExportModal('pdf')" class="dl-export-btn dl-export-btn-pdf">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </button>
                <button onclick="openDLExportModal('excel')" class="dl-export-btn dl-export-btn-excel">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Export -->
    <div id="dlExportModal" class="dl-export-modal">
        <div class="dl-export-modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-100">
                    <i class="bi bi-download text-blue-400 mr-2"></i>Exporter les données
                </h3>
                <button onclick="closeDLExportModal()" class="text-slate-400 hover:text-slate-200">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-slate-400 block mb-2">Sélectionner le semestre</label>
                    <select id="dlExportSemesterSelect" class="dl-export-select w-full">
                        <option value="">-- Choisir un semestre --</option>
                    </select>
                </div>
                
                <div>
                    <label class="text-xs text-slate-400 block mb-2">Cycle</label>
                    <select id="dlExportCycleSelect" class="dl-export-select w-full">
                        <option value="">Tous les cycles</option>
                        <option value="Licence">Licence uniquement</option>
                        <option value="Master">Master uniquement</option>
                    </select>
                </div>
                
                <div class="flex gap-2 mt-6">
                    <button onclick="closeDLExportModal()" class="flex-1 py-2 px-4 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 transition-colors">
                        Annuler
                    </button>
                    <button onclick="executeDLExport()" id="executeDLExportBtn" class="flex-1 py-2 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors">
                        <i class="bi bi-download mr-1"></i> Exporter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu scrollable -->
    <div class="deans-list-container flex-1 overflow-y-auto overflow-x-hidden p-4">
        
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
                                    INNER JOIN tbl_2024_etudiant e ON n.student_id = e.student_id
                                    WHERE n.session_id = :session_id
                                    AND n.ajout = 1
                                    AND n.grade > 0
                                    AND n.remove = 0
                                    AND e.remove != 1
                                    AND e.annee_etude >= :niveau_min
                                    AND e.annee_etude <= :niveau_max
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
                                        HAVING SUM(n.credit) >= 30 AND MIN(n.grade) >= 15
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
                                             data-photo="<?= (!empty($student['image_student']) && file_exists('../app/photosetudiants/'.$student['image_student'])) ? '../app/photosetudiants/'.$student['image_student'] : '' ?>"
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
                                                <?php if (!empty($student['image_student']) && file_exists('../app/photosetudiants/'.$student['image_student'])): ?>
                                                    <img src="../app/photosetudiants/<?= $student['image_student'] ?>" 
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
        <div class="mt-6 mb-4 flex items-center justify-between">
            
            <!-- Bouton Précédent -->
            <?php if ($page > 1): ?>
            <a href="?page=deans-list&page_annee=<?= $page - 1 ?>" 
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
            <a href="?page=deans-list&page_annee=<?= $page + 1 ?>" 
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
let dlCurrentExportType = 'pdf';

// Ouvrir le modal d'export
function openDLExportModal(type) {
    dlCurrentExportType = type;
    const modal = document.getElementById('dlExportModal');
    modal.classList.add('active');
    
    // Remplir le select des semestres
    populateDLSemesterSelect();
    
    // Mettre à jour le bouton
    const btn = document.getElementById('executeDLExportBtn');
    if (type === 'pdf') {
        btn.innerHTML = '<i class="bi bi-file-earmark-pdf mr-1"></i> Exporter PDF';
        btn.className = 'flex-1 py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium transition-colors';
    } else {
        btn.innerHTML = '<i class="bi bi-file-earmark-excel mr-1"></i> Exporter Excel';
        btn.className = 'flex-1 py-2 px-4 rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium transition-colors';
    }
}

function closeDLExportModal() {
    document.getElementById('dlExportModal').classList.remove('active');
}

// Remplir le select des semestres
function populateDLSemesterSelect() {
    const select = document.getElementById('dlExportSemesterSelect');
    const sessions = new Map();
    
    document.querySelectorAll('.dl-student-item').forEach(item => {
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
function executeDLExport() {
    const sessionId = document.getElementById('dlExportSemesterSelect').value;
    const cycleFilter = document.getElementById('dlExportCycleSelect').value;
    
    if (!sessionId) {
        alert('Veuillez sélectionner un semestre');
        return;
    }
    
    if (dlCurrentExportType === 'pdf') {
        exportDLToPDF(sessionId, cycleFilter);
    } else {
        exportDLToExcel(sessionId, cycleFilter);
    }
    
    closeDLExportModal();
}

// Collecter les données des étudiants Dean's List pour un semestre
function collectDLStudentData(sessionId, cycleFilter) {
    const data = [];
    document.querySelectorAll('.dl-student-item').forEach(item => {
        // Filtrer par session
        if (item.dataset.sessionId !== sessionId) return;
        
        // Filtrer par cycle si spécifié
        const cycle = item.dataset.cycle;
        if (cycleFilter && cycle !== cycleFilter) return;
        
        const nameEl = item.querySelector('a');
        const mentionTag = item.dataset.mention;
        const levelEl = item.querySelector('.dl-level-tag');
        const scoreEl = item.querySelector('.dl-score-box .text-lg');
        const rankEl = item.querySelector('.dl-rank-badge');
        const photoUrl = item.dataset.photo || '';
        
        const semesterCard = item.closest('.dl-semester-card');
        const yearCard = item.closest('.dl-year-card');
        
        const semester = semesterCard ? semesterCard.querySelector('.dl-semester-header span')?.textContent.trim() : '';
        const year = yearCard ? yearCard.querySelector('.dl-year-header h2')?.textContent.trim() : '';
        
        data.push({
            annee: year,
            cycle: cycle,
            semestre: semester,
            nom: nameEl ? nameEl.textContent.trim() : '',
            mention: mentionTag || '',
            niveau: levelEl ? levelEl.textContent.trim() : '',
            moyenne: scoreEl ? scoreEl.textContent.trim() : '',
            rank: rankEl ? parseInt(rankEl.textContent.trim()) : 999,
            photo: photoUrl
        });
    });
    
    // Trier par moyenne décroissante
    data.sort((a, b) => parseFloat(b.moyenne) - parseFloat(a.moyenne));
    
    return data;
}

// Convertir image en base64
async function getDLImageBase64(url) {
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

// Export PDF
async function exportDLToPDF(sessionId, cycleFilter) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    const data = collectDLStudentData(sessionId, cycleFilter);
    
    if (data.length === 0) {
        alert('Aucune donnée à exporter pour ce semestre');
        return;
    }
    
    const semesterName = data[0]?.semestre || 'Semestre';
    const yearName = data[0]?.annee || '';
    
    // En-tête avec fond bleu
    doc.setFillColor(30, 64, 175);
    doc.rect(0, 0, 210, 35, 'F');
    
    doc.setFontSize(18);
    doc.setTextColor(255, 255, 255);
    doc.setFont(undefined, 'bold');
    doc.text("Dean's List", 14, 15);
    
    doc.setFont(undefined, 'normal');
    doc.setFontSize(11);
    doc.text(`${semesterName} - ${yearName}`, 14, 25);
    
    doc.setFontSize(9);
    doc.setTextColor(200, 200, 200);
    doc.text('Exporté le ' + new Date().toLocaleDateString('fr-FR'), 150, 25);
    
    // Charger les photos
    const photoPromises = data.map(d => getDLImageBase64(d.photo));
    const photos = await Promise.all(photoPromises);
    
    let yPos = 45;
    const rowHeight = 22;
    const pageHeight = 280;
    
    // En-tête du tableau
    doc.setFillColor(241, 245, 249);
    doc.rect(10, yPos - 5, 190, 10, 'F');
    doc.setFontSize(9);
    doc.setTextColor(71, 85, 105);
    doc.setFont(undefined, 'bold');
    doc.text('#', 15, yPos, { align: 'center' });
    doc.text('Photo', 28, yPos, { align: 'center' });
    doc.text('Nom Complet', 75, yPos, { align: 'center' });
    doc.text('Mention', 125, yPos, { align: 'center' });
    doc.text('Niveau', 155, yPos, { align: 'center' });
    doc.text('Moyenne', 180, yPos, { align: 'center' });
    
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
            doc.text('#', 15, yPos, { align: 'center' });
            doc.text('Photo', 28, yPos, { align: 'center' });
            doc.text('Nom Complet', 75, yPos, { align: 'center' });
            doc.text('Mention', 125, yPos, { align: 'center' });
            doc.text('Niveau', 155, yPos, { align: 'center' });
            doc.text('Moyenne', 180, yPos, { align: 'center' });
            yPos += 12;
            doc.setFont(undefined, 'normal');
        }
        
        // Fond alternée
        if (i % 2 === 0) {
            doc.setFillColor(248, 250, 252);
            doc.rect(10, yPos - 5, 190, rowHeight, 'F');
        }
        
        // Rang
        doc.setFontSize(10);
        doc.setTextColor(100, 116, 139);
        doc.text(String(i + 1), 15, yPos + 4, { align: 'center' });
        
        // Photo
        if (photo) {
            try {
                doc.addImage(photo, 'JPEG', 20, yPos - 3, 16, 16);
            } catch (e) {
                // Ignorer les erreurs de photo
            }
        }
        
        // Nom
        doc.setFontSize(10);
        doc.setTextColor(30, 41, 59);
        doc.text(d.nom.substring(0, 30), 42, yPos + 4);
        
        // Mention
        doc.setFontSize(9);
        doc.setTextColor(59, 130, 246);
        doc.text(d.mention, 125, yPos + 4, { align: 'center' });
        
        // Niveau
        doc.setTextColor(100, 116, 139);
        doc.text(d.niveau, 155, yPos + 4, { align: 'center' });
        
        // Moyenne
        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(59, 130, 246);
        doc.text(d.moyenne + '/20', 180, yPos + 4, { align: 'center' });
        doc.setFont(undefined, 'normal');
        
        yPos += rowHeight;
    }
    
    // Pied de page
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text(`Page ${i} / ${pageCount}`, 105, 290, { align: 'center' });
    }
    
    doc.save(`deans-list_${semesterName.replace(/[^a-zA-Z0-9]/g, '_')}_${yearName}.pdf`);
}

// Export Excel
function exportDLToExcel(sessionId, cycleFilter) {
    const data = collectDLStudentData(sessionId, cycleFilter);
    
    if (data.length === 0) {
        alert('Aucune donnée à exporter pour ce semestre');
        return;
    }
    
    const semesterName = data[0]?.semestre || 'Semestre';
    const yearName = data[0]?.annee || '';
    
    // Préparer les données pour Excel
    const excelData = data.map((d, index) => ({
        'Rang': index + 1,
        'Nom Complet': d.nom,
        'Mention': d.mention,
        'Niveau': d.niveau,
        'Cycle': d.cycle,
        'Moyenne': parseFloat(d.moyenne)
    }));
    
    // Créer le workbook
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(excelData);
    
    // Ajuster les largeurs de colonnes
    ws['!cols'] = [
        { wch: 6 },   // Rang
        { wch: 30 },  // Nom
        { wch: 15 },  // Mention
        { wch: 10 },  // Niveau
        { wch: 10 },  // Cycle
        { wch: 10 }   // Moyenne
    ];
    
    XLSX.utils.book_append_sheet(wb, ws, "Dean's List");
    XLSX.writeFile(wb, `deans-list_${semesterName.replace(/[^a-zA-Z0-9]/g, '_')}_${yearName}.xlsx`);
}
</script>
