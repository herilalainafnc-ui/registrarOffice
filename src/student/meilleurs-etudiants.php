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
</style>

<div class="flex flex-col h-full">
    
    <!-- Header fixe -->
    <div class="page-header px-6 py-4 flex-shrink-0">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/10 border border-blue-500/30 flex items-center justify-center">
                <i class="bi bi-trophy-fill text-blue-400 text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl font-semibold text-slate-100 tracking-tight">Meilleurs Étudiants</h1>
                <p class="text-xs text-slate-400 mt-0.5">Classement par semestre, mention et cycle</p>
            </div>
        </div>
    </div>

    <!-- Contenu scrollable -->
    <div class="best-students-container flex-1 overflow-y-auto overflow-x-hidden p-4">
        
        <?php
        $annees = $dtb->query('SELECT DISTINCT annee_scolaire FROM t_2023_notes WHERE annee_scolaire IS NOT NULL ORDER BY annee_scolaire DESC');
        $mentions_list = $dtb->query('SELECT DISTINCT filiere_description, filiere_sigle FROM filiere ORDER BY filiere_sigle');
        $all_mentions = $mentions_list->fetchAll(PDO::FETCH_ASSOC);
        
        $cycles = [
            ['nom' => 'Licence', 'icon' => 'bi-mortarboard', 'min' => 1, 'max' => 3],
            ['nom' => 'Master', 'icon' => 'bi-award', 'min' => 4, 'max' => 5]
        ];
        
        while ($annee = $annees->fetch()) {
            $annee_scolaire = $annee['annee_scolaire'];
            if (empty($annee_scolaire)) continue;
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
                <?php foreach ($cycles as $cycle): ?>
                
                <!-- Cycle Section -->
                <div class="cycle-tab p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="cycle-badge">
                            <i class="<?= $cycle['icon'] ?> mr-1.5"></i><?= $cycle['nom'] ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <?php for ($sem = 1; $sem <= 2; $sem++): ?>
                        
                        <!-- Semester Card -->
                        <div class="semester-card overflow-hidden">
                            <div class="semester-header px-4 py-3 flex items-center gap-2">
                                <i class="bi bi-collection text-blue-400/80"></i>
                                <span class="text-sm font-medium text-slate-300">Semestre <?= $sem ?></span>
                            </div>
                            
                            <div class="p-3 space-y-2">
                                <?php 
                                $has_data = false;
                                
                                foreach ($all_mentions as $mention):
                                    $etude_envisage = $mention['filiere_description'];
                                    $sigle = $mention['filiere_sigle'];
                                    
                                    $query = "
                                        SELECT 
                                            n.student_id,
                                            e.student_nom,
                                            e.student_prenom,
                                            e.annee_etude,
                                            e.image_student,
                                            ROUND(SUM(n.credit * n.grade) / SUM(n.credit), 2) as moyenne
                                        FROM t_2023_notes n
                                        INNER JOIN tbl_2024_etudiant e ON n.student_id = e.student_id
                                        WHERE n.annee_scolaire = :annee_scolaire
                                        AND n.semester = :semester
                                        AND n.grade > 0
                                        AND n.remove = 0
                                        AND e.remove != 1
                                        AND e.etude_envisage = :etude_envisage
                                        AND e.annee_etude >= :niveau_min
                                        AND e.annee_etude <= :niveau_max
                                        GROUP BY n.student_id
                                        HAVING SUM(n.credit) > 0
                                        ORDER BY moyenne DESC
                                        LIMIT 1
                                    ";
                                    
                                    $stmt = $dtb->prepare($query);
                                    $stmt->execute([
                                        ':annee_scolaire' => $annee_scolaire,
                                        ':semester' => $sem,
                                        ':etude_envisage' => $etude_envisage,
                                        ':niveau_min' => $cycle['min'],
                                        ':niveau_max' => $cycle['max']
                                    ]);
                                    
                                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($student):
                                        $has_data = true;
                                        $niveau = $student['annee_etude'] <= 3 ? 'L'.$student['annee_etude'] : 'M'.($student['annee_etude'] - 3);
                                ?>
                                
                                <!-- Student Item -->
                                <div class="student-item p-3 flex items-center gap-3">
                                    
                                    <!-- Rank & Avatar -->
                                    <div class="flex-shrink-0 relative">
                                        <?php if (!empty($student['image_student']) && file_exists('../app/photosetudiants/'.$student['image_student'])): ?>
                                            <img src="../app/photosetudiants/<?= $student['image_student'] ?>" 
                                                 class="w-10 h-10 rounded-full object-cover ring-2 ring-blue-500/40">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-slate-700/80 flex items-center justify-center ring-2 ring-slate-600/50">
                                                <i class="bi bi-person-fill text-slate-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute -top-1 -left-1 w-5 h-5 rounded-full bg-gradient-to-br from-amber-400 to-amber-500 flex items-center justify-center shadow-lg">
                                            <i class="bi bi-star-fill text-white text-xs"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Info -->
                                    <div class="flex-grow min-w-0">
                                        <a href="./student.php?id=<?= $student['student_id'] ?>&page=information" 
                                           class="text-sm font-medium text-slate-200 hover:text-blue-400 transition-colors truncate block">
                                            <?= strtoupper($student['student_nom']) ?> <?= ucfirst(strtolower($student['student_prenom'])) ?>
                                        </a>
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
                        
                        <?php endfor; ?>
                    </div>
                </div>
                
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php } ?>
    </div>
</div>
