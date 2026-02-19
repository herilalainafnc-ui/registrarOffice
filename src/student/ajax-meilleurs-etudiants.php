<?php
/**
 * AJAX - Charger plus d'années pour les meilleurs étudiants
 */
require('../../data/connectdb.php');

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 2;
$limit = 2;

$annees = $dtb->query("SELECT DISTINCT annee_scolaire FROM t_2023_notes WHERE annee_scolaire IS NOT NULL ORDER BY annee_scolaire DESC LIMIT $limit OFFSET $offset");
$mentions_list = $dtb->query('SELECT DISTINCT filiere_description, filiere_sigle FROM filiere ORDER BY filiere_sigle');
$all_mentions = $mentions_list->fetchAll(PDO::FETCH_ASSOC);

$cycles = [
    ['nom' => 'Licence', 'icon' => 'bi-mortarboard', 'min' => 1, 'max' => 3],
    ['nom' => 'Master', 'icon' => 'bi-award', 'min' => 4, 'max' => 5]
];

$count = 0;
while ($annee = $annees->fetch()) {
    $annee_scolaire = $annee['annee_scolaire'];
    if (empty($annee_scolaire)) continue;
    $count++;
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
                                    e.id,
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
                                HAVING SUM(n.credit) >= 25 AND ROUND(SUM(n.credit * n.grade) / SUM(n.credit), 2) >= 17
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
                                <?php if (!empty($student['image_student']) && file_exists(__DIR__.'/../../app/photosetudiants/'.$student['image_student'])): ?>
                                    <img src="<?=$app_base?>/app/photosetudiants/<?= $student['image_student'] ?>" 
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
                                <a href="./student?id=<?= $student['id'] ?>&page=information" 
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

<?php 
}

// Si aucune donnée, retourner un indicateur
if ($count == 0) {
    echo '<!--NO_MORE_DATA-->';
}
?>
