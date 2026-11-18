<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

// Seul le personnel d'administration/management peut accéder
if (Middleware::getCurrentUser()['level'] > 3) {
    die("Accès refusé.");
}

// Fetch stats summary
$stats = [
    'total_forms' => $dtb->query("SELECT count(*) FROM rg_eval_forms")->fetchColumn(),
    'active_forms' => $dtb->query("SELECT count(*) FROM rg_eval_forms WHERE is_published=1 AND (end_date IS NULL OR end_date >= NOW())")->fetchColumn(),
    'total_responses' => $dtb->query("SELECT count(*) FROM rg_eval_responses")->fetchColumn()
];

// Fetch recent forms
$forms = $dtb->query("
    SELECT f.*, 
           (SELECT COUNT(*) FROM rg_eval_responses r WHERE r.form_id = f.id) as response_count 
    FROM rg_eval_forms f 
    ORDER BY f.created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Administration des Évaluations</title>
</head>
<body class="<?=$bg_three_color?> <?=$txt_one_color?> sm:text-xs lg:text-sm">

<div class="h-screen w-full <?=$bg_three_color?>">
    <!-- TOP BAR --><?php require('../init/topbar.php');?>

    <div class="w-full flex flex-col lg:flex-row">
        <!-- Menu principal -->
        <?php require('../init/menubar.php'); ?>

        <!-- Contenu principal -->
        <div class="w-full lg:w-10/12 flex flex-col overflow-y-auto" style="height: calc(100vh - 56px);">
            <div class="p-6">
                
                <div class="flex justify-between items-center mb-6 border-b <?=$br_two_color?> pb-4">
            <h1 class="text-3xl font-bold text-blue-400"><i class="bi bi-bar-chart-fill mr-2"></i> Tableaux de bord - Évaluations</h1>
            <div class="flex items-center gap-3">
                <a href="<?=$app_base?>/evaluation/automate" class="bg-blue-700 hover:bg-blue-600 text-white px-4 py-2 rounded font-bold shadow-lg transition-colors flex items-center gap-2">
                    <i class="bi bi-send-fill"></i> Automatiser l'envoi
                </a>
                <a href="<?=$app_base?>/evaluation/create" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded font-bold shadow-lg transition-colors flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i> Nouveau Formulaire
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="<?=$bg_two_color?> p-6 rounded-lg border <?=$br_two_color?> shadow flex items-center justify-between">
                <div>
                    <h3 class="<?=$txt_two_color?> text-sm font-bold uppercase tracking-wider">Total Formulaires</h3>
                    <div class="text-3xl font-bold <?=$txt_one_color?> mt-1"><?=$stats['total_forms']?></div>
                </div>
                <div class="w-12 h-12 rounded-full bg-blue-900 text-blue-400 flex items-center justify-center text-xl">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
            
            <div class="<?=$bg_two_color?> p-6 rounded-lg border <?=$br_two_color?> shadow flex items-center justify-between">
                <div>
                    <h3 class="<?=$txt_two_color?> text-sm font-bold uppercase tracking-wider">Actifs en ce moment</h3>
                    <div class="text-3xl font-bold text-green-400 mt-1"><?=$stats['active_forms']?></div>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-900 text-green-400 flex items-center justify-center text-xl">
                    <i class="bi bi-activity"></i>
                </div>
            </div>

            <div class="<?=$bg_two_color?> p-6 rounded-lg border <?=$br_two_color?> shadow flex items-center justify-between">
                <div>
                    <h3 class="<?=$txt_two_color?> text-sm font-bold uppercase tracking-wider">Total Réponses</h3>
                    <div class="text-3xl font-bold text-purple-400 mt-1"><?=$stats['total_responses']?></div>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-900 text-purple-400 flex items-center justify-center text-xl">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>

        <!-- Tableau des formulaires -->
        <div class="<?=$bg_two_color?> rounded-lg shadow border <?=$br_two_color?> overflow-hidden">
            <div class="px-6 py-4 border-b <?=$br_two_color?> <?=$bg_two_color?>">
                <h2 class="text-xl font-bold <?=$txt_one_color?>">Formulaires récents</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="<?=$bg_three_color?> <?=$txt_two_color?> text-sm uppercase tracking-wider">
                            <th class="px-6 py-3 font-medium">Titre</th>
                            <th class="px-6 py-3 font-medium">Statut</th>
                            <th class="px-6 py-3 font-medium">Cible</th>
                            <th class="px-6 py-3 font-medium text-center">Réponses</th>
                            <th class="px-6 py-3 font-medium">Création</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y <?=$br_three_color?> text-sm">
                        <?php if(empty($forms)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center <?=$txt_two_color?>">Aucun formulaire créé pour le moment.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($forms as $f): ?>
                            <tr class="hover:<?=$bg_one_color?> transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium <?=$txt_one_color?>"><?=htmlspecialchars($f['title'])?></div>
                                    <div class="<?=$txt_two_color?> text-xs truncate max-w-xs"><?=htmlspecialchars($f['description'])?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($f['is_published']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">Publié</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-700 text-gray-300">Brouillon</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 <?=$txt_one_color?> capitalize">
                                    <?=$f['target_role'] == 'all' ? 'Tous' : $f['target_role']?>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-blue-400">
                                    <?=$f['response_count']?>
                                </td>
                                <td class="px-6 py-4 <?=$txt_two_color?> text-xs">
                                    <?=date('d/m/Y H:i', strtotime($f['created_at']))?>
                                </td>
                                <td class="px-6 py-4 text-right flex justify-end gap-2">
                                    <a href="<?=$app_base?>/evaluation/edit/<?=$f['id']?>" class="p-1.5 bg-gray-700 hover:bg-gray-600 rounded text-blue-400" title="Modifier"><i class="bi bi-pencil"></i></a>
                                    <a href="<?=$app_base?>/evaluation/stats/<?=$f['id']?>" class="p-1.5 bg-gray-700 hover:bg-gray-600 rounded text-purple-400" title="Résultats / Graphiques"><i class="bi bi-pie-chart-fill"></i></a>
                                    <button
                                        type="button"
                                        class="p-1.5 bg-gray-700 hover:bg-red-900 rounded text-red-400 btn-open-delete-modal"
                                        data-delete-url="<?=$app_base?>/evaluation/delete/<?=$f['id']?>"
                                        data-form-title="<?=htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8')?>"
                                        title="Supprimer"
                                    ><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </div>
        <?php require('../init/footer.php'); ?>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60" aria-hidden="true">
    <div class="w-full max-w-md <?=$bg_two_color?> border <?=$br_two_color?> rounded-xl shadow-xl">
        <div class="px-5 py-4 border-b <?=$br_two_color?> flex items-center justify-between">
            <h3 class="text-lg font-bold text-red-400">Confirmer la suppression</h3>
            <button type="button" id="btnCloseDeleteModal" class="text-gray-400 hover:text-white" aria-label="Fermer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="px-5 py-4">
            <p class="<?=$txt_one_color?> mb-2">Vous allez supprimer ce formulaire :</p>
            <p id="deleteModalFormTitle" class="font-semibold text-yellow-300 break-words"></p>
            <p class="<?=$txt_two_color?> text-sm mt-3">Cette action est définitive et supprimera aussi les réponses associées.</p>
        </div>

        <div class="px-5 py-4 border-t <?=$br_two_color?> flex justify-end gap-3">
            <button type="button" id="btnCancelDelete" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white rounded">Annuler</button>
            <a href="#" id="btnConfirmDelete" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded font-semibold">Supprimer</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('deleteModal');
    const titleNode = document.getElementById('deleteModalFormTitle');
    const confirmBtn = document.getElementById('btnConfirmDelete');
    const closeBtn = document.getElementById('btnCloseDeleteModal');
    const cancelBtn = document.getElementById('btnCancelDelete');
    const openButtons = document.querySelectorAll('.btn-open-delete-modal');

    function openModal(deleteUrl, formTitle) {
        confirmBtn.setAttribute('href', deleteUrl);
        titleNode.textContent = formTitle || 'Formulaire sans titre';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        confirmBtn.setAttribute('href', '#');
        titleNode.textContent = '';
    }

    openButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.dataset.deleteUrl, btn.dataset.formTitle);
        });
    });

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
});
</script>

</body>
</html>