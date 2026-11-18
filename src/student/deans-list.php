<?php
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); refuserEtudiant(); refuserEnseignant(); // notes : réservées au personnel ; classement de toute l'université
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
    background: rgb(var(--c-accent) / 0.3);
    border-radius: 3px;
}
.deans-list-container::-webkit-scrollbar-thumb:hover {
    background: rgb(var(--c-accent) / 0.5);
}

.dl-page-header {
    background: rgb(var(--c-canvas) / 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgb(var(--c-line) / 0.5);
}

.dl-year-card {
    background: rgb(var(--c-canvas) / 0.5);
    border: 1px solid rgb(var(--c-line) / 0.4);
    border-radius: 16px;
    backdrop-filter: blur(8px);
    overflow: hidden;
}

.dl-year-header {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.1), rgb(var(--c-accent) / 0.05));
    border-bottom: 1px solid rgb(var(--c-line) / 0.4);
}

.dl-cycle-tab {
    background: rgb(var(--c-surface) / 0.6);
    border: 1px solid rgb(var(--c-line) / 0.4);
    border-radius: 10px;
    transition: all 0.2s ease;
}
.dl-cycle-tab:hover {
    border-color: rgb(var(--c-accent) / 0.4);
}

.dl-semester-card {
    background: rgb(var(--c-canvas) / 0.6);
    border: 1px solid rgb(var(--c-line) / 0.3);
    border-radius: 12px;
    transition: all 0.2s ease;
}
.dl-semester-card:hover {
    border-color: rgb(var(--c-accent) / 0.3);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.dl-semester-header {
    background: rgb(var(--c-surface) / 0.5);
    border-bottom: 1px solid rgb(var(--c-line) / 0.3);
}

.dl-mention-section {
    background: rgb(var(--c-surface) / 0.3);
    border: 1px solid rgb(var(--c-line) / 0.2);
    border-radius: 8px;
    margin-bottom: 12px;
}

.dl-mention-header {
    background: rgb(var(--c-accent) / 0.08);
    border-bottom: 1px solid rgb(var(--c-line) / 0.2);
    padding: 8px 12px;
    border-radius: 8px 8px 0 0;
}

.dl-student-item {
    background: rgb(var(--c-surface) / 0.4);
    border: 1px solid rgb(var(--c-line) / 0.3);
    border-radius: 10px;
    transition: all 0.15s ease;
}
.dl-student-item:hover {
    background: rgb(var(--c-surface) / 0.7);
    border-color: rgb(var(--c-accent) / 0.4);
    transform: translateX(4px);
}

.dl-cycle-badge {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.2), rgb(var(--c-accent) / 0.15));
    color: rgb(var(--c-accent-text));
    border: 1px solid rgb(var(--c-accent) / 0.3);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.dl-score-box {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.15), rgb(var(--c-accent) / 0.1));
    border: 1px solid rgb(var(--c-accent) / 0.25);
    border-radius: 10px;
    padding: 6px 14px;
    min-width: 80px;
    text-align: center;
}

.dl-mention-tag {
    background: rgb(var(--c-accent) / 0.1);
    color: rgb(var(--c-accent-text));
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
}

.dl-level-tag {
    background: rgb(var(--c-text-3) / 0.2);
    color: rgb(var(--c-text-3));
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
    background: linear-gradient(135deg, #94a3b8, rgb(var(--c-line-strong)));
    color: #1e293b;
}
.dl-rank-3 {
    background: linear-gradient(135deg, #cd7f32, #b45309);
    color: white;
}
.dl-rank-other {
    background: rgb(var(--c-text-3) / 0.3);
    color: rgb(var(--c-text-3));
}

.dl-deans-badge {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.25), rgb(var(--c-accent) / 0.2));
    color: rgb(var(--c-accent-text));
    border: 1px solid rgb(var(--c-accent) / 0.4);
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
    background: rgb(var(--c-surface) / 0.3);
    border: 1px dashed rgb(var(--c-line) / 0.4);
    border-radius: 10px;
}

.dl-count-badge {
    background: rgb(var(--c-accent) / 0.15);
    color: rgb(var(--c-accent-text));
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Light mode */
[data-theme="light"] .dl-page-header {
    background: rgb(var(--c-surface) / 0.9);
    border-bottom-color: rgb(var(--c-line-strong) / 0.8);
}
[data-theme="light"] .dl-year-card {
    background: rgb(var(--c-surface) / 0.8);
    border-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .dl-year-header {
    background: linear-gradient(135deg, rgb(var(--c-accent) / 0.08), rgb(var(--c-accent) / 0.03));
    border-bottom-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .dl-cycle-tab {
    background: rgb(var(--c-surface-2) / 0.8);
    border-color: rgb(var(--c-line-strong) / 0.6);
}
[data-theme="light"] .dl-semester-card {
    background: rgb(var(--c-surface) / 0.9);
    border-color: rgb(var(--c-line-strong) / 0.5);
}
[data-theme="light"] .dl-semester-header {
    background: rgb(var(--c-surface-2) / 0.8);
    border-bottom-color: rgb(var(--c-line-strong) / 0.5);
}
[data-theme="light"] .dl-student-item {
    background: rgb(var(--c-surface) / 0.8);
    border-color: rgb(var(--c-line-strong) / 0.5);
}
[data-theme="light"] .dl-student-item:hover {
    background: rgb(var(--c-surface-2) / 0.9);
    border-color: rgb(var(--c-accent) / 0.4);
}
[data-theme="light"] .dl-mention-section {
    background: rgb(var(--c-surface) / 0.6);
    border-color: rgb(var(--c-line-strong) / 0.4);
}
[data-theme="light"] .dl-mention-header {
    background: rgb(var(--c-accent) / 0.06);
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
    background: rgb(var(--c-surface) / 0.8);
    border: 1px solid rgb(var(--c-line) / 0.5);
    color: rgb(var(--c-text));
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    cursor: pointer;
    min-width: 180px;
}
.dl-export-select:focus {
    outline: none;
    border-color: rgb(var(--c-accent) / 0.5);
}
.dl-export-select option {
    background: rgb(var(--c-surface));
    color: rgb(var(--c-text));
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
    background: rgb(var(--c-canvas) / 0.95);
    border: 1px solid rgb(var(--c-line) / 0.5);
    border-radius: 16px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
}

/* =====================================================================
   Responsivite
   ---------------------------------------------------------------------
   Le probleme principal n'etait pas un debordement mais l'empilement des
   marges internes : cinq niveaux de conteneurs, chacun avec son p-3 ou
   p-4, consommaient 216 px sur un ecran de 375 — 58 % de la largeur
   disparaissait avant d'afficher le moindre nom. Les fiches etudiant
   tombaient a 135 px et 30 noms etaient tronques.

   On reduit donc les marges par palier. Les selecteurs combinent la classe
   semantique et l'utilitaire Tailwind pour passer devant lui en
   specificite (0,2,0 contre 0,1,0) sans recourir a !important.
   ===================================================================== */

@media (max-width: 1023px) {
    .deans-list-container.p-4       { padding: 0.5rem !important; }
    .dl-year-card > .p-4            { padding: 0.5rem !important; }
    .dl-cycle-tab.p-4               { padding: 0.5rem !important; }
    .dl-semester-card > .p-3        { padding: 0.5rem !important; }
    .dl-mention-section > .p-3      { padding: 0.5rem !important; }
    .dl-student-item.p-3            { padding: 0.5rem !important; gap: 0.5rem; }

    /* En-tetes de carte : moins de rembourrage lateral, le titre respire. */
    .dl-semester-header.px-4        { padding-left: 0.625rem !important; padding-right: 0.625rem !important; }

    /* Les seuls textes sous 11 px sont les badges de rang (« 1 », « 2 »)
       et les etiquettes de niveau (« L3 ») : 1 a 3 caracteres dans une
       pastille. Les agrandir les ferait deborder sans rien gagner en
       lisibilite. On se contente d un point de plus sur l etiquette,
       qui porte une information et non un simple rang. */
    .dl-level-tag                   { font-size: 11px; }

    /* Cibles tactiles : 44 px est le minimum confortable au doigt. */
    .deans-list-container a,
    .deans-list-container button,
    .deans-list-container select    { min-height: 44px; }

    /* Les champs sous 16 px declenchent un zoom automatique sur iOS. */
    .deans-list-container input,
    .deans-list-container select    { font-size: 16px; }

    /* En-tete et pagination : passer a la ligne plutot que de comprimer. */
    .dl-header-bar,
    .dl-pagination                  { flex-wrap: wrap; gap: 0.5rem; }
}

/* Telephones etroits : on recupere encore quelques pixels. */
@media (max-width: 400px) {
    .deans-list-container.p-4       { padding: 0.375rem !important; }
    .dl-student-item.p-3            { padding: 0.375rem !important; }
    .dl-cycle-tab.p-4               { padding: 0.375rem !important; }
}

/* Le nom de l'etudiant prime sur le reste : il ne doit plus etre le
   premier element sacrifie quand la place manque. */
.dl-student-item .flex-grow.min-w-0 { min-width: 0; flex: 1 1 auto; }

/* --- Enveloppe de page -----------------------------------------------
   La hauteur etait figee en style inline a calc(100vh - 56px), donc
   impossible a surcharger en CSS. Sous 1024 px la page defile maintenant
   d'un bloc, au lieu d'imbriquer un panneau defilant dans une hauteur
   d'ecran — ce qui, sur mobile, rognait le contenu et piegeait le pouce
   entre deux zones de defilement. */
.dl-shell { height: auto; }

@media (min-width: 1024px) {
    .dl-shell { height: calc(100vh - 56px); }
}

@supports (height: 100dvh) {
    @media (min-width: 1024px) {
        .dl-shell { height: calc(100dvh - 56px); }
    }
}
</style>

<!-- Libraries pour export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="flex flex-col h-full">
    
    <!-- Header fixe -->
    <div class="dl-page-header px-6 py-4 flex-shrink-0">
        <div class="dl-header-bar flex items-center justify-between">
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

    <?php
    /* Le choix de l'année, entre l'en-tête et la zone qui défile : il reste
       en vue pendant la lecture, et src/js/annees-ajax.js le tient à jour. */
    require_once __DIR__ . '/annees-classement.php';
    $choixAnnees = anneesClassement($dtb);
    require __DIR__ . '/annees-navigation.php';
    ?>

    <!-- Contenu scrollable -->
    <div class="deans-list-container flex-1 overflow-y-auto overflow-x-hidden p-4" data-annees-conteneur data-url="<?= htmlspecialchars($app_base . '/deans-list', ENT_QUOTES, 'UTF-8') ?>">
        
        <?php require __DIR__ . '/deans-list.annees.php'; ?>

    </div>
</div>

<script src="<?= htmlspecialchars($app_base, ENT_QUOTES, 'UTF-8') ?>/src/js/annees-ajax.js?v=<?= @filemtime(__DIR__ . '/../js/annees-ajax.js') ?>" defer></script>
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
        Toast.warning('Veuillez sélectionner un semestre.');
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
        Toast.warning('Aucune donnée à exporter pour ce semestre.');
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
        Toast.warning('Aucune donnée à exporter pour ce semestre.');
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
