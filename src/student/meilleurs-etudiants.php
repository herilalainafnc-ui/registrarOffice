<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // notes : réservées au personnel ; classement de toute l'université
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
    background: rgb(var(--c-accent) / 0.3);
    border-radius: 3px;
}
.best-students-container::-webkit-scrollbar-thumb:hover {
    background: rgb(var(--c-accent) / 0.5);
}

.page-header {
    background: rgb(var(--c-canvas) / 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgb(var(--c-line) / 0.5);
}

.year-card {
    background: rgb(var(--c-canvas) / 0.5);
    border: 1px solid rgb(var(--c-line) / 0.4);
    border-radius: 16px;
    backdrop-filter: blur(8px);
    overflow: hidden;
}

.year-header {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.1), rgb(var(--c-accent) / 0.05));
    border-bottom: 1px solid rgb(var(--c-line) / 0.4);
}

.cycle-tab {
    background: rgb(var(--c-surface) / 0.6);
    border: 1px solid rgb(var(--c-line) / 0.4);
    border-radius: 10px;
    transition: all 0.2s ease;
}
.cycle-tab:hover {
    border-color: rgb(var(--c-accent) / 0.4);
}

.semester-card {
    background: rgb(var(--c-canvas) / 0.6);
    border: 1px solid rgb(var(--c-line) / 0.3);
    border-radius: 12px;
    transition: all 0.2s ease;
}
.semester-card:hover {
    border-color: rgb(var(--c-accent) / 0.3);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.semester-header {
    background: rgb(var(--c-surface) / 0.5);
    border-bottom: 1px solid rgb(var(--c-line) / 0.3);
}

.student-item {
    background: rgb(var(--c-surface) / 0.4);
    border: 1px solid rgb(var(--c-line) / 0.3);
    border-radius: 10px;
    transition: all 0.15s ease;
}
.student-item:hover {
    background: rgb(var(--c-surface) / 0.7);
    border-color: rgb(var(--c-accent) / 0.4);
    transform: translateX(4px);
}

.cycle-badge {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.2), rgb(var(--c-accent) / 0.15));
    color: rgb(var(--c-accent-text));
    border: 1px solid rgb(var(--c-accent) / 0.3);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.score-box {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.15), rgb(var(--c-accent) / 0.1));
    border: 1px solid rgb(var(--c-accent) / 0.25);
    border-radius: 10px;
    padding: 6px 14px;
    min-width: 80px;
    text-align: center;
}

.mention-tag {
    background: rgb(var(--c-accent) / 0.1);
    color: rgb(var(--c-accent-text));
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
}

.level-tag {
    background: rgb(var(--c-text-3) / 0.2);
    color: rgb(var(--c-text-3));
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
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), rgb(var(--c-surface) / 0.4));
    border-color: rgba(251, 191, 36, 0.3);
}
.student-item.best-global:hover {
    border-color: rgba(251, 191, 36, 0.5);
}

.empty-state {
    background: rgb(var(--c-surface) / 0.3);
    border: 1px dashed rgb(var(--c-line) / 0.4);
    border-radius: 10px;
}

/* Light mode */
[data-theme="light"] .page-header {
    background: rgb(var(--c-surface) / 0.9);
    border-bottom-color: rgb(var(--c-line-strong) / 0.8);
}
[data-theme="light"] .year-card {
    background: rgb(var(--c-surface) / 0.8);
    border-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .year-header {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.08), rgb(var(--c-accent) / 0.03));
    border-bottom-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .cycle-tab {
    background: rgb(var(--c-surface-2) / 0.8);
    border-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .semester-card {
    background: rgb(var(--c-surface) / 0.9);
    border-color: rgb(var(--c-line-strong) / 0.5);
}
[data-theme="light"] .semester-header {
    background: rgb(var(--c-surface-2) / 0.8);
    border-bottom-color: rgb(var(--c-line-strong) / 0.5);
}
[data-theme="light"] .student-item {
    background: rgb(var(--c-surface) / 0.8);
    border-color: rgb(var(--c-line-strong) / 0.5);
}
[data-theme="light"] .student-item:hover {
    background: rgb(var(--c-surface-2) / 0.9);
    border-color: rgb(var(--c-accent) / 0.4);
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
    background: rgb(var(--c-surface) / 0.8);
    border: 1px solid rgb(var(--c-line) / 0.5);
    color: rgb(var(--c-text));
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    cursor: pointer;
    min-width: 180px;
}
.export-select:focus {
    outline: none;
    border-color: rgb(var(--c-accent) / 0.5);
}
.export-select option {
    background: rgb(var(--c-surface));
    color: rgb(var(--c-text));
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
    background: rgb(var(--c-canvas) / 0.95);
    border: 1px solid rgb(var(--c-line) / 0.5);
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

    <?php
    /* Le choix de l'année, entre l'en-tête et la zone qui défile : il reste
       en vue pendant la lecture, et src/js/annees-ajax.js le tient à jour. */
    require_once __DIR__ . '/annees-classement.php';
    $choixAnnees = anneesClassement($dtb);
    require __DIR__ . '/annees-navigation.php';
    ?>

    <!-- Contenu scrollable -->
    <div class="best-students-container flex-1 overflow-y-auto overflow-x-hidden p-4" data-annees-conteneur data-url="<?= htmlspecialchars($app_base . '/top-students', ENT_QUOTES, 'UTF-8') ?>">
        
        <?php require __DIR__ . '/meilleurs-etudiants.annees.php'; ?>

    </div>
</div>

<script src="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/src/js/annees-ajax.js?v=<?= @filemtime(__DIR__ . '/../js/annees-ajax.js') ?>" defer></script>
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
        Toast.warning('Veuillez sélectionner un semestre.');
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
        Toast.warning('Aucune donnée à exporter pour ce semestre.');
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
        Toast.warning('Aucune donnée à exporter pour ce semestre.');
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