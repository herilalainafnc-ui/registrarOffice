<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
require_once('../data/cours.sigle.php');
coursSigleAliasInstaller($dtb);
initMiddleware($dtb);

$user = Middleware::getCurrentUser();
$isGuest = empty($user['id']);

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Provient du contrôleur

// Récupérer le formulaire
$stmt = $dtb->prepare("SELECT * FROM rg_eval_forms WHERE id = ?");
$stmt->execute([$formId]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form || !$form['is_published']) {
    die("Formulaire indisponible ou supprimé.");
}

// Récupérer les informations du cours et du professeur liés au formulaire
$courseMeta = null;
if (!empty($form['cours_id']) && !empty($form['session_id'])) {
    $stmtMeta = $dtb->prepare("
        SELECT c.Sigle, c.sigle_affiche, c.title AS course_title, c.semester, c.dep_desc,
               CONCAT(t.name, ' ', t.lastName) AS prof_nom,
               s.session_name, s.session_year
        FROM t_2023_cours c
        LEFT JOIN teacher t ON t.uid = c.id_teacher
        LEFT JOIN t_2023_session s ON s.session_id = ?
        WHERE c.id = ?
    ");
    $stmtMeta->execute([$form['session_id'], $form['cours_id']]);
    $courseMeta = $stmtMeta->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Récupérer les questions
$stmtQ = $dtb->prepare("SELECT * FROM rg_eval_questions WHERE form_id = ? ORDER BY display_order ASC");
$stmtQ->execute([$formId]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les options
$stmtOpt = $dtb->prepare("
    SELECT o.* FROM rg_eval_options o 
    JOIN rg_eval_questions q ON o.question_id = q.id 
    WHERE q.form_id = ? 
    ORDER BY o.display_order ASC
");
$stmtOpt->execute([$formId]);
$optionsRow = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);

$optionsByQ = [];
foreach($optionsRow as $o){
    $optionsByQ[$o['question_id']][] = $o;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $allow_public_page = true; ?>
    <?php require('../init/head.php');?>
    <title><?=htmlspecialchars($form['title'])?> - Évaluation</title>
    <style>
        .g-form-header {
            border-top: 10px solid rgb(var(--c-accent));
            border-radius: 8px;
        }
        .q-card {
            transition: box-shadow 0.2s;
            border-left: 3px solid transparent;
        }
        .q-card:focus-within {
            border-left-color: rgb(var(--c-accent));
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .required-asterisk { color: #f87171; }
    </style>
</head>
<body class="bg-gray-900 text-gray-200">

<div class="flex flex-col h-screen overflow-hidden">
    <div class="bg-gray-800 border-b border-gray-700 px-4 sm:px-6 py-3 flex items-center shadow-md">
        <a href="<?=$app_base?>/evaluation" class="text-blue-400 hover:text-blue-300 mr-4 shrink-0">
            <i class="bi bi-arrow-left text-xl"></i>
        </a>
        <h1 class="text-lg sm:text-xl font-bold truncate"><?=htmlspecialchars($form['title'])?></h1>
    </div>

    <div class="flex-1 overflow-y-auto px-3 sm:px-4 py-6 sm:py-8">
        <div class="max-w-3xl mx-auto">
            
            <form action="<?=$app_base?>/evaluation/submit/<?=$form['id']?>" method="POST" id="evalForm">
                
                <!-- En-tête Google Form Style -->
                <div class="g-form-header bg-gray-800 p-4 sm:p-6 shadow-md mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2 break-words"><?=htmlspecialchars($form['title'])?></h1>
                    <p class="text-gray-400 text-sm whitespace-pre-line mb-4"><?=htmlspecialchars($form['description'])?></p>

                    <?php if ($courseMeta): ?>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm border-t border-gray-700 pt-4">
                        <div class="flex items-center gap-2 text-gray-300 min-w-0">
                            <i class="bi bi-person-circle text-blue-400 shrink-0"></i>
                            <span class="min-w-0"><span class="text-gray-500 text-xs uppercase tracking-wide mr-1">Professeur</span><strong class="break-words"><?= htmlspecialchars($courseMeta['prof_nom'] ?: '—') ?></strong></span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-300 min-w-0">
                            <i class="bi bi-tag text-blue-400 shrink-0"></i>
                            <span class="min-w-0"><span class="text-gray-500 text-xs uppercase tracking-wide mr-1">Sigle</span><strong class="break-words"><?= htmlspecialchars(coursSigleAffiche($courseMeta) ?: '—') ?></strong></span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-300 min-w-0">
                            <i class="bi bi-calendar2 text-blue-400 shrink-0"></i>
                            <span class="min-w-0"><span class="text-gray-500 text-xs uppercase tracking-wide mr-1">Semestre</span><strong class="break-words"><?= $courseMeta['semester'] == 1 ? '1er semestre' : ($courseMeta['semester'] == 2 ? '2ème semestre' : 'Semestre ' . $courseMeta['semester']) ?></strong></span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-300 min-w-0">
                            <i class="bi bi-mortarboard text-blue-400 shrink-0"></i>
                            <span class="min-w-0"><span class="text-gray-500 text-xs uppercase tracking-wide mr-1">Année</span><strong class="break-words"><?= htmlspecialchars(($courseMeta['session_name'] ?? '') . ' ' . ($courseMeta['session_year'] ?? '')) ?></strong></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 text-xs font-semibold text-red-400">* Indique une question obligatoire</div>
                </div>

                <?php if ($isGuest): ?>
                <div class="q-card bg-gray-800 rounded-lg p-4 sm:p-6 shadow-md border border-gray-700 mb-6">
                    <label class="block text-base sm:text-lg font-medium text-white mb-2">
                        Adresse e-mail <span class="required-asterisk">*</span>
                    </label>
                    <p class="text-sm text-gray-400 mb-4">Une seule réponse est autorisée par adresse e-mail.</p>
                    <input
                        type="email"
                        name="respondent_email"
                        required
                        autocomplete="email"
                        placeholder="exemple@domaine.com"
                        class="w-full bg-transparent border-0 border-b-2 border-gray-600 focus:ring-0 focus:border-blue-500 text-white placeholder-gray-500 transition-colors"
                    >
                </div>
                <?php endif; ?>

                <!-- Liste des questions -->
                <?php foreach($questions as $q): ?>
                <div class="q-card bg-gray-800 rounded-lg p-4 sm:p-6 shadow-md border border-gray-700 mb-6">
                    <label class="block text-base sm:text-lg font-medium text-white mb-4 break-words">
                        <?=htmlspecialchars($q['question_text'])?> 
                        <?php if($q['is_required']): ?><span class="required-asterisk">*</span><?php endif; ?>
                    </label>

                    <?php if($q['question_type'] == 'short'): ?>
                        <input type="text" name="q_<?=$q['id']?>" class="w-full bg-transparent border-0 border-b-2 border-gray-600 focus:ring-0 focus:border-blue-500 text-white placeholder-gray-500 transition-colors" placeholder="Votre réponse" <?= $q['is_required'] ? 'required' : '' ?>>
                    
                    <?php elseif($q['question_type'] == 'paragraph'): ?>
                        <textarea name="q_<?=$q['id']?>" rows="3" class="w-full bg-transparent border-0 border-b-2 border-gray-600 focus:ring-0 focus:border-blue-500 text-white placeholder-gray-500 transition-colors resize-y" placeholder="Votre réponse détaillée" <?= $q['is_required'] ? 'required' : '' ?>></textarea>

                    <?php elseif($q['question_type'] == 'multiple_choice'): ?>
                        <div class="space-y-2">
                        <?php foreach($optionsByQ[$q['id']] ?? [] as $opt): ?>
                            <label class="flex items-start sm:items-center gap-3 cursor-pointer">
                                <input type="radio" name="q_<?=$q['id']?>" value="<?=$opt['id']?>" class="form-radio text-blue-500 bg-gray-900 border-gray-600 focus:ring-blue-500 h-5 w-5 mt-0.5 sm:mt-0 shrink-0" <?= $q['is_required'] ? 'required' : '' ?>>
                                <span class="break-words"><?=htmlspecialchars($opt['option_text'])?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>

                    <?php elseif($q['question_type'] == 'checkbox'): ?>
                        <div class="space-y-2" data-checkbox-group="<?=$q['id']?>" <?= $q['is_required'] ? 'data-required="1"' : '' ?>>
                        <?php foreach($optionsByQ[$q['id']] ?? [] as $opt): ?>
                            <label class="flex items-start sm:items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="q_<?=$q['id']?>[]" value="<?=$opt['id']?>" class="form-checkbox text-blue-500 bg-gray-900 border-gray-600 focus:ring-blue-500 h-5 w-5 rounded mt-0.5 sm:mt-0 shrink-0">
                                <span class="break-words"><?=htmlspecialchars($opt['option_text'])?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>

                    <?php elseif($q['question_type'] == 'rating'): ?>
                        <!-- Bloc rating : empilé verticalement sur mobile pour éviter le débordement -->
                        <div class="bg-gray-900 p-3 sm:p-4 rounded-lg w-full">
                            <div class="flex items-center justify-center gap-3 sm:gap-4 mb-2">
                                <?php for($i=1; $i<=5; $i++): ?>
                                <label class="flex flex-col items-center cursor-pointer">
                                    <span class="mb-1 sm:mb-2 font-bold text-sm sm:text-base"><?=$i?></span>
                                    <input type="radio" name="q_<?=$q['id']?>" value="<?=$i?>" class="form-radio text-blue-500 bg-gray-900 border-gray-500 h-4 w-4 sm:h-5 sm:w-5" <?= $q['is_required'] ? 'required' : '' ?>>
                                </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex items-center justify-between text-[11px] sm:text-sm font-medium uppercase text-gray-500 px-1">
                                <span>Faible</span>
                                <span>Excellent</span>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Autres types (stars, dropdown, etc) -->
                        <div class="text-yellow-400 italic">Preuve de concept: Type <?=$q['question_type']?> (à compléter)</div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>

                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 bg-gray-800 p-4 rounded-lg border border-gray-700 shadow-md">
                    <p class="text-xs text-gray-500">Ne soumettez pas de mots de passe via ce formulaire.</p>
                    <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded font-bold shadow-lg transition-colors">
                        Envoyer ma réponse
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    // Validation basique côté client pour le joli UI
    document.getElementById('evalForm').addEventListener('submit', function(e) {
        const requiredCheckboxGroups = this.querySelectorAll('[data-checkbox-group][data-required="1"]');
        for (const group of requiredCheckboxGroups) {
            const checked = group.querySelectorAll('input[type="checkbox"]:checked').length;
            if (checked === 0) {
                e.preventDefault();
                if (window.Toast) { Toast.warning('Veuillez cocher au moins une option pour toutes les questions obligatoires.'); } else if (window.console) { console.warn('Questions obligatoires sans reponse.'); }
                return;
            }
        }

        // Optionnel : afficher un message de chargement/progression
        let btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Envoi en cours...';
        btn.disabled = true;
    });
</script>
<?php require('../init/toast.php'); ?>
</body>
</html>