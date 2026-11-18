<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$user = Middleware::getCurrentUser();
if (($user['level'] ?? 10) > 3) {
    die("Accès refusé.");
}

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmtForm = $dtb->prepare("SELECT * FROM rg_eval_forms WHERE id = ?");
$stmtForm->execute([$formId]);
$form = $stmtForm->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("Formulaire introuvable.");
}

$stmtQuestions = $dtb->prepare("SELECT * FROM rg_eval_questions WHERE form_id = ? ORDER BY display_order ASC, id ASC");
$stmtQuestions->execute([$formId]);
$questions = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

$optionsByQuestion = [];
$questionIds = array_column($questions, 'id');
if (!empty($questionIds)) {
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmtOptions = $dtb->prepare("SELECT * FROM rg_eval_options WHERE question_id IN ($placeholders) ORDER BY display_order ASC, id ASC");
    $stmtOptions->execute($questionIds);
    foreach ($stmtOptions->fetchAll(PDO::FETCH_ASSOC) as $option) {
        $optionsByQuestion[$option['question_id']][] = $option;
    }
}

$stmtResponses = $dtb->prepare("SELECT COUNT(*) FROM rg_eval_responses WHERE form_id = ?");
$stmtResponses->execute([$formId]);
$responseCount = (int)$stmtResponses->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Modifier un formulaire</title>
    <style>
        .question-block {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .question-block:focus-within, .question-block.active {
            border-left-color: rgb(var(--c-accent));
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .drag-handle { cursor: grab; }
        .drag-handle:active { cursor: grabbing; }
    </style>
</head>
<body class="<?=$bg_three_color?> <?=$txt_one_color?> sm:text-xs lg:text-sm">

<div class="h-screen w-full <?=$bg_three_color?>">
    <?php require('../init/topbar.php');?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col overflow-y-auto" style="height: calc(100vh - 56px);">
            <div class="p-6 pb-20">
                <div class="flex items-center gap-4 mb-4">
                    <a href="<?=$app_base?>/evaluation/admin" class="<?=$txt_two_color?> hover:<?=$txt_one_color?>">
                        <i class="bi bi-arrow-left text-2xl"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-blue-400">Modifier le formulaire</h1>
                        <p class="<?=$txt_two_color?> mt-1">Vous pouvez ajuster les questions, les options et la visibilité.</p>
                    </div>
                </div>

                <?php if ($responseCount > 0): ?>
                    <div class="mb-6 rounded-lg border border-yellow-700 bg-yellow-950/50 p-4 text-yellow-200">
                        <div class="flex items-start gap-3">
                            <i class="bi bi-exclamation-triangle-fill text-xl mt-0.5"></i>
                            <div>
                                <div class="font-semibold">Ce formulaire a déjà <?=$responseCount?> réponse(s).</div>
                                <div class="text-sm text-yellow-100/90 mt-1">Réenregistrer la structure remplacera les anciennes questions et supprimera les réponses détaillées associées à ces questions.</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="evaluationForm" action="<?=$app_base?>/evaluation/update/<?=$form['id']?>" method="POST" class="max-w-4xl mx-auto">
                    <div class="<?=$bg_two_color?> rounded-lg border-t-8 border-blue-500 shadow-md p-6 mb-6">
                        <input type="text" name="title" placeholder="Titre du formulaire" required value="<?=htmlspecialchars($form['title'])?>"
                               class="w-full bg-transparent border-0 border-b border-gray-600 focus:ring-0 focus:border-blue-500 text-3xl font-bold <?=$txt_one_color?> placeholder-gray-500 mb-4 px-0 py-2">

                        <textarea name="description" placeholder="Description du formulaire..." rows="2"
                                  class="w-full bg-transparent border-0 border-b border-gray-600 focus:ring-0 focus:border-blue-500 <?=$txt_two_color?> placeholder-gray-500 px-0 resize-y"><?=htmlspecialchars($form['description'] ?? '')?></textarea>
                    </div>

                    <div class="<?=$bg_two_color?> rounded-lg border <?=$br_two_color?> p-5 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1 <?=$txt_two_color?>">Cible</label>
                            <select name="target_role" class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-2 <?=$txt_one_color?> focus:ring-blue-500">
                                <option value="all" <?=$form['target_role'] === 'all' ? 'selected' : ''?>>Tous les utilisateurs</option>
                                <option value="student" <?=$form['target_role'] === 'student' ? 'selected' : ''?>>Étudiants uniquement</option>
                                <option value="teacher" <?=$form['target_role'] === 'teacher' ? 'selected' : ''?>>Professeurs uniquement</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1 <?=$txt_two_color?>">Date limite (optionnel)</label>
                            <input type="datetime-local" name="end_date" value="<?=!empty($form['end_date']) ? date('Y-m-d\TH:i', strtotime($form['end_date'])) : ''?>" class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-2 <?=$txt_one_color?> focus:ring-blue-500 text-sm">
                        </div>
                    </div>

                    <div id="questionsContainer" class="space-y-6">
                        <?php foreach ($questions as $index => $question): ?>
                            <?php $questionOptions = $optionsByQuestion[$question['id']] ?? []; ?>
                            <div class="question-block <?=$bg_two_color?> rounded-lg border <?=$br_two_color?> p-6 relative group" data-id="<?=$index?>">
                                <div class="absolute top-2 left-1/2 -translate-x-1/2 text-gray-500 hover:text-gray-300 drag-handle opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i class="bi bi-grip-horizontal text-xl"></i>
                                </div>

                                <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-red-400 transition-colors btn-delete-question">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                                    <div class="col-span-2">
                                        <input type="text" name="questions[<?=$index?>][text]" placeholder="Question" required value="<?=htmlspecialchars($question['question_text'])?>"
                                               class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-3 <?=$txt_one_color?> focus:ring-blue-500 placeholder-gray-500 font-medium">
                                    </div>
                                    <div>
                                        <select name="questions[<?=$index?>][type]" class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-3 <?=$txt_one_color?> focus:ring-blue-500 question-type-select">
                                            <option value="short" <?=$question['question_type'] === 'short' ? 'selected' : ''?>>Réponse courte</option>
                                            <option value="paragraph" <?=$question['question_type'] === 'paragraph' ? 'selected' : ''?>>Paragraphe</option>
                                            <option value="multiple_choice" <?=$question['question_type'] === 'multiple_choice' ? 'selected' : ''?>>Choix multiple</option>
                                            <option value="checkbox" <?=$question['question_type'] === 'checkbox' ? 'selected' : ''?>>Cases à cocher</option>
                                            <option value="rating" <?=$question['question_type'] === 'rating' ? 'selected' : ''?>>Échelle de notation (1-5)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="options-container mt-4 pl-2 <?=in_array($question['question_type'], ['multiple_choice', 'checkbox'], true) ? '' : 'hidden'?> border-l-2 border-gray-600">
                                    <?php foreach ($questionOptions as $option): ?>
                                        <div class="flex items-center gap-3 mb-2 group">
                                            <i class="bi <?= $question['question_type'] === 'checkbox' ? 'bi-square' : 'bi-circle' ?> text-gray-500 option-icon"></i>
                                            <input type="text" name="questions[<?=$index?>][options][]" placeholder="Option" required value="<?=htmlspecialchars($option['option_text'])?>"
                                                   class="flex-1 bg-transparent border-0 border-b border-gray-600 hover:border-gray-400 focus:ring-0 focus:border-blue-500 <?=$txt_one_color?> px-0 py-1 transition-colors">
                                            <button type="button" class="text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity btn-delete-option">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-3 <?=in_array($question['question_type'], ['multiple_choice', 'checkbox'], true) ? '' : 'hidden'?> add-option-row"></div>

                                <div class="mt-4 pt-4 border-t <?=$br_two_color?> flex justify-end items-center gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <span class="text-sm <?=$txt_two_color?>">Obligatoire</span>
                                        <div class="relative inline-flex items-center">
                                            <input type="checkbox" name="questions[<?=$index?>][required]" value="1" class="sr-only peer" <?= $question['is_required'] ? 'checked' : '' ?>>
                                            <div class="w-9 h-5 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-6 text-center">
                        <button type="button" id="btnAddQuestion" class="bg-gray-700 hover:bg-gray-600 text-white rounded-full h-12 w-12 flex items-center justify-center mx-auto shadow-lg transition-transform hover:scale-110">
                            <i class="bi bi-plus-lg text-xl"></i>
                        </button>
                    </div>

                    <div class="fixed bottom-[0px] lg:bottom-6 right-0 w-full lg:w-10/12 <?=$bg_one_color?> border-t <?=$br_two_color?> p-4 flex justify-between items-center z-50">
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_published" value="1" class="sr-only peer" <?= !empty($form['is_published']) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-sm font-medium <?=$txt_one_color?>">Publié</span>
                            </label>
                        </div>
                        <div class="flex gap-3">
                            <a href="<?=$app_base?>/evaluation/admin" class="px-6 py-2 bg-gray-600 hover:bg-gray-500 rounded font-medium text-white transition-colors">Annuler</a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 rounded font-bold text-white shadow-lg transition-colors flex items-center gap-2">
                                <i class="bi bi-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>

<template id="questionTemplate">
    <div class="question-block <?=$bg_two_color?> rounded-lg border <?=$br_two_color?> p-6 relative group" data-id="{id}">
        <div class="absolute top-2 left-1/2 -translate-x-1/2 text-gray-500 hover:text-gray-300 drag-handle opacity-0 group-hover:opacity-100 transition-opacity">
            <i class="bi bi-grip-horizontal text-xl"></i>
        </div>

        <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-red-400 transition-colors btn-delete-question">
            <i class="bi bi-trash"></i>
        </button>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
            <div class="col-span-2">
                <input type="text" name="questions[{id}][text]" placeholder="Question" required
                       class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-3 <?=$txt_one_color?> focus:ring-blue-500 placeholder-gray-500 font-medium">
            </div>
            <div>
                <select name="questions[{id}][type]" class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-3 <?=$txt_one_color?> focus:ring-blue-500 question-type-select">
                    <option value="short">Réponse courte</option>
                    <option value="paragraph">Paragraphe</option>
                    <option value="multiple_choice">Choix multiple</option>
                    <option value="checkbox">Cases à cocher</option>
                    <option value="rating">Échelle de notation (1-5)</option>
                </select>
            </div>
        </div>

        <div class="options-container mt-4 pl-2 hidden border-l-2 border-gray-600"></div>

        <div class="mt-4 pt-4 border-t <?=$br_two_color?> flex justify-end items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <span class="text-sm <?=$txt_two_color?>">Obligatoire</span>
                <div class="relative inline-flex items-center">
                    <input type="checkbox" name="questions[{id}][required]" value="1" class="sr-only peer" checked>
                    <div class="w-9 h-5 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
                </div>
            </label>
        </div>
    </div>
</template>

<template id="optionTemplate">
    <div class="flex items-center gap-3 mb-2 group">
        <i class="bi bi-circle text-gray-500 option-icon"></i>
        <input type="text" name="questions[{id}][options][]" placeholder="Option" required
               class="flex-1 bg-transparent border-0 border-b border-gray-600 hover:border-gray-400 focus:ring-0 focus:border-blue-500 <?=$txt_one_color?> px-0 py-1 transition-colors">
        <button type="button" class="text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity btn-delete-option">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let questionCounter = <?=count($questions)?>;
    const container = document.getElementById('questionsContainer');
    const btnAdd = document.getElementById('btnAddQuestion');
    const qTemplate = document.getElementById('questionTemplate').innerHTML;
    const oTemplate = document.getElementById('optionTemplate').innerHTML;

    function addOption(optionsContainer, questionId, type) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = oTemplate.replace(/{id}/g, questionId);
        const optionBlock = tempDiv.firstElementChild;
        const icon = optionBlock.querySelector('.option-icon');
        icon.className = type === 'checkbox' ? 'bi bi-square text-gray-500 option-icon' : 'bi bi-circle text-gray-500 option-icon';

        optionBlock.querySelector('.btn-delete-option').addEventListener('click', () => {
            optionBlock.remove();
        });

        const addRow = optionsContainer.querySelector('.add-option-row');
        if (addRow) {
            optionsContainer.insertBefore(optionBlock, addRow);
        } else {
            optionsContainer.appendChild(optionBlock);
        }

        const input = optionBlock.querySelector('input');
        if (input) {
            input.focus();
        }
    }

    function ensureAddOptionButton(optionsContainer, questionId, type) {
        let addRow = optionsContainer.querySelector('.add-option-row');
        if (!addRow) {
            addRow = document.createElement('div');
            addRow.className = 'mt-2 add-option-row';
            optionsContainer.appendChild(addRow);
        }

        addRow.innerHTML = '<button type="button" class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">Ajouter une option</button>';
        addRow.querySelector('button').onclick = () => addOption(optionsContainer, questionId, type);
    }

    function updateOptionIcons(optionsContainer, type) {
        const iconClass = type === 'checkbox' ? 'bi bi-square text-gray-500 option-icon' : 'bi bi-circle text-gray-500 option-icon';
        optionsContainer.querySelectorAll('.option-icon').forEach(icon => {
            icon.className = iconClass;
        });
    }

    function setupQuestion(block, questionId) {
        const deleteBtn = block.querySelector('.btn-delete-question');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                block.style.opacity = 0;
                block.style.transform = 'scale(0.95)';
                setTimeout(() => block.remove(), 200);
            });
        }

        const typeSelect = block.querySelector('.question-type-select');
        const optionsContainer = block.querySelector('.options-container');

        const syncOptions = () => {
            const type = typeSelect.value;
            if (['multiple_choice', 'checkbox'].includes(type)) {
                optionsContainer.classList.remove('hidden');
                if (optionsContainer.children.length === 0) {
                    addOption(optionsContainer, questionId, type);
                }
                ensureAddOptionButton(optionsContainer, questionId, type);
                updateOptionIcons(optionsContainer, type);
            } else {
                optionsContainer.classList.add('hidden');
                const addRow = optionsContainer.querySelector('.add-option-row');
                if (addRow) {
                    addRow.remove();
                }
            }
        };

        if (typeSelect) {
            typeSelect.addEventListener('change', syncOptions);
            syncOptions();
        }
    }

    btnAdd.addEventListener('click', () => {
        const id = questionCounter++;
        const html = qTemplate.replace(/{id}/g, id);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const block = tempDiv.firstElementChild;
        container.appendChild(block);
        setupQuestion(block, id);
        block.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const firstInput = block.querySelector('input[type="text"]');
        if (firstInput) {
            firstInput.focus();
        }
    });

    container.querySelectorAll('.question-block').forEach((block, index) => {
        setupQuestion(block, index);
    });
});
</script>
</body>
</html>