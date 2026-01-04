<?php
/**
 * Meilleurs Étudiants par Semestre, par Mention et par Cycle (Licence/Master)
 * Style shadcn - Minimaliste avec couleur bleue
 */
?>

<style>
.card-shadcn {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.5);
    border-radius: 0.75rem;
    backdrop-filter: blur(8px);
}
.card-shadcn:hover {
    border-color: rgba(59, 130, 246, 0.5);
}
.student-row {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 0.5rem;
    transition: all 0.15s ease;
}
.student-row:hover {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(59, 130, 246, 0.4);
}
.badge-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}
.score-badge {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2));
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 0.5rem;
    padding: 4px 12px;
}
</style>

<div class="p-6 overflow-auto" style="max-height: calc(100vh - 180px);">
    
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-100 tracking-tight">
            Meilleurs Étudiants
        </h1>
        <p class="text-sm text-slate-400 mt-1">Classement par semestre et par mention</p>
    </div>

    <?php
    $annees = $dtb->query('SELECT DISTINCT annee_scolaire FROM t_2023_notes WHERE annee_scolaire IS NOT NULL ORDER BY annee_scolaire DESC');
    $mentions_list = $dtb->query('SELECT DISTINCT filiere_description, filiere_sigle FROM filiere ORDER BY filiere_sigle');
    $all_mentions = $mentions_list->fetchAll(PDO::FETCH_ASSOC);
    
    $cycles = [
        ['nom' => 'Licence', 'min' => 1, 'max' => 3],
        ['nom' => 'Master', 'min' => 4, 'max' => 5]
    ];
    
    while ($annee = $annees->fetch()) {
        $annee_scolaire = $annee['annee_scolaire'];
        if (empty($annee_scolaire)) continue;
    ?>
    
    <!-- Année Card -->
    <div class="card-shadcn p-5 mb-5">
        
        <!-- Year Header -->
        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-700/50">
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                <i class="bi bi-calendar3 text-blue-400"></i>
            </div>
            <div>
                <h2 class="text-lg font-medium text-slate-100"><?= $annee_scolaire ?></h2>
                <p class="text-xs text-slate-500">Année scolaire</p>
            </div>
        </div>
        
        <?php foreach ($cycles as $cycle): ?>
        
        <!-- Cycle Section -->
        <div class="mb-5 last:mb-0">
            <div class="flex items-center gap-2 mb-4">
                <span class="badge-blue">
                    <i class="bi bi-mortarboard-fill mr-1"></i><?= $cycle['nom'] ?>
                </span>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php for ($sem = 1; $sem <= 2; $sem++): ?>
                
                <!-- Semester Card -->
                <div class="bg-slate-800/30 rounded-lg border border-slate-700/40 p-4">
                    <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-700/30">
                        <i class="bi bi-collection text-blue-400/70 text-sm"></i>
                        <span class="text-sm font-medium text-slate-300">Semestre <?= $sem ?></span>
                    </div>
                    
                    <div class="space-y-2">
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
                        
                        <!-- Student Row -->
                        <div class="student-row p-3 flex items-center gap-3">
                            
                            <!-- Avatar -->
                            <div class="flex-shrink-0">
                                <?php if (!empty($student['image_student']) && file_exists('../app/photosetudiants/'.$student['image_student'])): ?>
                                    <img src="../app/photosetudiants/<?= $student['image_student'] ?>" 
                                         class="w-9 h-9 rounded-full object-cover ring-2 ring-blue-500/30">
                                <?php else: ?>
                                    <div class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center ring-2 ring-slate-600">
                                        <i class="bi bi-person text-slate-400"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Info -->
                            <div class="flex-grow min-w-0">
                                <a href="./student.php?id=<?= $student['student_id'] ?>&page=information" 
                                   class="text-sm font-medium text-slate-200 hover:text-blue-400 transition-colors truncate block">
                                    <?= strtoupper($student['student_nom']) ?> <?= $student['student_prenom'] ?>
                                </a>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-blue-400 font-medium"><?= $sigle ?></span>
                                    <span class="text-slate-600">•</span>
                                    <span class="text-xs text-slate-500"><?= $niveau ?></span>
                                </div>
                            </div>
                            
                            <!-- Score -->
                            <div class="flex-shrink-0">
                                <div class="score-badge text-center">
                                    <span class="text-base font-semibold text-blue-400"><?= number_format($student['moyenne'], 2) ?></span>
                                    <span class="text-xs text-slate-500 ml-0.5">/20</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                            endif;
                        endforeach;
                        
                        if (!$has_data): 
                        ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-slate-600 text-2xl"></i>
                            <p class="text-xs text-slate-500 mt-1">Aucune donnée</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php endfor; ?>
            </div>
        </div>
        
        <?php endforeach; ?>
    </div>
    
    <?php } ?>
</div>
