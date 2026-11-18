<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$user = Middleware::getCurrentUser();
$userId = $user['id'] ?? 0;
$userLevel = $user['level'] ?? 10;
$userRole = 'student'; // déterminez cela en fonction du niveau réel

// Récupérer les évaluations disponibles
$stmt = $dtb->prepare("
    SELECT * FROM rg_eval_forms 
    WHERE is_published = 1 
    AND (target_role = 'all' OR target_role = :role)
    AND (start_date IS NULL OR start_date <= NOW())
    AND (end_date IS NULL OR end_date >= NOW())
    ORDER BY created_at DESC
");
$stmt->execute(['role' => $userRole]);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Évaluations et Sondages</title>
    <style>
        .eval-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid rgb(var(--c-accent));
        }
        .eval-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
            background: rgb(var(--c-accent) / 0.2);
            color: rgb(var(--c-accent-text));
        }
    </style>
</head>
<body class="<?=$bg_three_color?> <?=$txt_one_color?> sm:text-xs lg:text-sm">

    <div class="h-screen w-full <?=$bg_three_color?>">
        <!-- TOP BAR --><?php require('../init/topbar.php');?>
        
        <div class="w-full flex flex-col lg:flex-row">
            <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>
            
            <!-- Contenu principal -->
            <div class="w-full lg:w-10/12 flex flex-col overflow-y-auto" style="height: calc(100vh - 56px);">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-blue-400">Mes Évaluations</h1>
                    <p class="<?=$txt_two_color?> mt-2">Participez aux sondages et évaluations pour améliorer nos services.</p>
                </div>
                <?php if($userLevel <= 3): ?>
                <a href="<?=$app_base?>/evaluation/admin" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded text-white font-medium flex items-center gap-2">
                    <i class="bi bi-gear-fill"></i> Gestion Admin
                </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(empty($forms)): ?>
                    <div class="col-span-full text-center py-12 <?=$bg_two_color?> rounded-lg border <?=$br_two_color?>">
                        <i class="bi bi-inbox text-5xl <?=$txt_two_color?> mb-4 block"></i>
                        <h3 class="text-xl font-medium <?=$txt_one_color?>">Aucune évaluation disponible</h3>
                        <p class="<?=$txt_two_color?>">Il n'y a actuellement aucun sondage ou évaluation requérant votre participation.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($forms as $form): ?>
                        <div class="eval-card <?=$bg_two_color?> rounded-lg p-5 border shadow-sm <?=$br_two_color?> flex flex-col h-full">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-lg font-semibold <?=$txt_one_color?>"><?=htmlspecialchars($form['title'])?></h3>
                                <span class="status-badge"><i class="bi bi-clock-history"></i> En cours</span>
                            </div>
                            <p class="text-sm <?=$txt_two_color?> mb-6 flex-grow"><?=nl2br(htmlspecialchars($form['description']))?></p>
                            
                            <div class="flex justify-between items-center mt-auto pt-4 border-t <?=$br_two_color?>">
                                <div class="text-xs <?=$txt_two_color?>">
                                    <?php if($form['end_date']): ?>
                                        <i class="bi bi-calendar-event"></i> Jusqu'au <?=date('d/m/Y', strtotime($form['end_date']))?>
                                    <?php endif; ?>
                                </div>
                                <a href="<?=$app_base?>/evaluation/form/<?=$form['id']?>" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded text-sm transition font-medium">
                                    Participer <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php require('../init/footer.php'); ?>
    </div>
</div>

</body>
</html>