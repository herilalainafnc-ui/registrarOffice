<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

if (Middleware::getCurrentUser()['level'] > 3) {
    die("Accès refusé.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Créer une Évaluation</title>
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
    <!-- TOP BAR --><?php require('../init/topbar.php');?>

    <div class="w-full flex flex-col lg:flex-row">
        <!-- Menu principal -->
        <?php require('../init/menubar.php'); ?>

        <!-- Contenu principal -->
        <div class="w-full lg:w-10/12 flex flex-col overflow-y-auto" style="height: calc(100vh - 56px);">
            <div class="p-6 pb-20">
                <div class="flex items-center gap-4 mb-6">
                    <a href="<?=$app_base?>/evaluation/admin" class="<?=$txt_two_color?> hover:<?=$txt_one_color?>">
                        <i class="bi bi-arrow-left text-2xl"></i>
                    </a>
                    <h1 class="text-3xl font-bold text-blue-400">Créer un formulaire</h1>
                </div>

                <form id="evaluationForm" action="<?=$app_base?>/evaluation/store" method="POST" class="max-w-4xl mx-auto">
                    
                    <!-- En-tête du formulaire -->
                    <div class="<?=$bg_two_color?> rounded-lg border-t-8 border-blue-500 shadow-md p-6 mb-6">
                        <input type="text" name="title" placeholder="Titre du formulaire" required
                               class="w-full bg-transparent border-0 border-b border-gray-600 focus:ring-0 focus:border-blue-500 text-3xl font-bold <?=$txt_one_color?> placeholder-gray-500 mb-4 px-0 py-2">
                        
                        <textarea name="description" placeholder="Description du formulaire..." rows="2"
                                  class="w-full bg-transparent border-0 border-b border-gray-600 focus:ring-0 focus:border-blue-500 <?=$txt_two_color?> placeholder-gray-500 px-0 resize-y"></textarea>
                    </div>

                    <!-- Configuration -->
                    <div class="<?=$bg_two_color?> rounded-lg border <?=$br_two_color?> p-5 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1 <?=$txt_two_color?>">Cible</label>
                            <select name="target_role" class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-2 <?=$txt_one_color?> focus:ring-blue-500">
                                <option value="all">Tous les utilisateurs</option>
                                <option value="student">Étudiants uniquement</option>
                                <option value="teacher">Professeurs uniquement</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1 <?=$txt_two_color?>">Date limite (optionnel)</label>
                            <input type="datetime-local" name="end_date" class="w-full bg-gray-800 border <?=$br_two_color?> rounded p-2 <?=$txt_one_color?> focus:ring-blue-500 text-sm">
                        </div>
                    </div>

                    <!-- Conteneur des questions -->
                    <div id="questionsContainer" class="space-y-6">
                        <!-- Les questions seront ajoutées ici en JS -->
                    </div>

                    <!-- Bouton Ajouter Question -->
                    <div class="mt-6 text-center">
                        <button type="button" id="btnAddQuestion" class="bg-gray-700 hover:bg-gray-600 text-white rounded-full h-12 w-12 flex items-center justify-center mx-auto shadow-lg transition-transform hover:scale-110">
                            <i class="bi bi-plus-lg text-xl"></i>
                        </button>
                    </div>

                    <!-- Action Bar fixe en bas -->
                    <div class="fixed bottom-[0px] lg:bottom-6 right-0 w-full lg:w-10/12 <?=$bg_one_color?> border-t <?=$br_two_color?> p-4 flex justify-between items-center z-50">
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_published" value="1" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-sm font-medium <?=$txt_one_color?>">Publier immédiatement</span>
                            </label>
                        </div>
                        <div class="flex gap-3">
                            <a href="<?=$app_base?>/evaluation/admin" class="px-6 py-2 bg-gray-600 hover:bg-gray-500 rounded font-medium text-white transition-colors">Annuler</a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 rounded font-bold text-white shadow-lg transition-colors flex items-center gap-2">
                                <i class="bi bi-save"></i> Enregistrer
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

        <div class="options-container mt-4 pl-2 hidden border-l-2 border-gray-600">
            <!-- Options (pour radio/checkbox) iront ici -->
        </div>

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
    let questionCounter = 0;
    const container = document.getElementById('questionsContainer');
    const btnAdd = document.getElementById('btnAddQuestion');
    const qTemplate = document.getElementById('questionTemplate').innerHTML;
    const oTemplate = document.getElementById('optionTemplate').innerHTML;

    function addQuestion() {
        const id = questionCounter++;
        const html = qTemplate.replace(/{id}/g, id);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const block = tempDiv.firstElementChild;
        container.appendChild(block);
        
        // Scroll into view
        block.scrollIntoView({ behavior: 'smooth', block: 'center' });
        block.querySelector('input[type="text"]').focus();

        setupQuestionListeners(block, id);
    }

    function setupQuestionListeners(block, id) {
        // Supprimer la question
        block.querySelector('.btn-delete-question').addEventListener('click', () => {
            block.style.opacity = 0;
            block.style.transform = 'scale(0.95)';
            setTimeout(() => block.remove(), 200);
        });

        // Changement de type
        const typeSelect = block.querySelector('.question-type-select');
        const optsContainer = block.querySelector('.options-container');
        
        typeSelect.addEventListener('change', (e) => {
            const type = e.target.value;
            if (['multiple_choice', 'checkbox'].includes(type)) {
                optsContainer.classList.remove('hidden');
                if (optsContainer.children.length === 0) {
                    addOption(optsContainer, id, type); // Ajoute une option par défaut
                    addAddOptionButton(optsContainer, id, type);
                } else {
                    // Update icons
                    updateOptionIcons(optsContainer, type);
                }
            } else {
                optsContainer.classList.add('hidden');
            }
        });
    }

    function addOption(container, qId, type) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = oTemplate.replace(/{id}/g, qId);
        const optBlock = tempDiv.firstElementChild;
        
        const icon = optBlock.querySelector('.option-icon');
        icon.className = type === 'checkbox' ? 'bi bi-square text-gray-500 option-icon' : 'bi bi-circle text-gray-500 option-icon';
        
        // Setup delete option
        optBlock.querySelector('.btn-delete-option').addEventListener('click', () => {
            optBlock.remove();
        });

        // Insert before the "Add option" button if it exists
        const addBtnBlock = container.querySelector('.add-option-block');
        if (addBtnBlock) {
            container.insertBefore(optBlock, addBtnBlock);
        } else {
            container.appendChild(optBlock);
        }
        
        optBlock.querySelector('input').focus();
    }

    function addAddOptionButton(container, qId, type) {
        const block = document.createElement('div');
        block.className = 'flex items-center gap-3 mt-2 add-option-block';
        const iconClass = type === 'checkbox' ? 'bi-square' : 'bi-circle';
        
        block.innerHTML = `
            <i class="bi ${iconClass} text-gray-600"></i>
            <button type="button" class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">Ajouter une option</button>
        `;
        
        block.querySelector('button').addEventListener('click', () => {
            addOption(container, qId, type);
        });
        
        container.appendChild(block);
    }

    function updateOptionIcons(container, type) {
        const isCheck = type === 'checkbox';
        container.querySelectorAll('.option-icon').forEach(icon => {
            icon.className = isCheck ? 'bi bi-square text-gray-500 option-icon' : 'bi bi-circle text-gray-500 option-icon';
        });
        const addBtnIcon = container.querySelector('.add-option-block i');
        if (addBtnIcon) {
            addBtnIcon.className = isCheck ? 'bi bi-square text-gray-600' : 'bi bi-circle text-gray-600';
        }
    }

    btnAdd.addEventListener('click', addQuestion);

    // Ajouter une première question par défaut
    addQuestion();

    // Prevent form submission by enter key except on textarea
    document.getElementById('evaluationForm').addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            return false;
        }
    });
});
</script>
</body>
</html>