<!-- ===== KEYBOARD SHORTCUTS HELPER ===== -->
<style>
    /* Keyboard Shortcuts Modal */
    .shortcuts-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(10, 22, 40, 0.9);
        backdrop-filter: blur(8px);
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .shortcuts-modal.show {
        display: flex;
        opacity: 1;
    }

    .shortcuts-content {
        background: linear-gradient(135deg, #0d1f3c, #0a1628);
        border: 1px solid #1a3a5c;
        border-radius: 16px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    [data-theme="light"] .shortcuts-content {
        background: linear-gradient(135deg, #ffffff, #f0f7fc);
        border-color: #8eb8d4;
    }

    .shortcuts-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #1a3a5c;
    }

    [data-theme="light"] .shortcuts-header {
        border-bottom-color: #8eb8d4;
    }

    .shortcuts-title {
        font-size: 18px;
        font-weight: 600;
        color: #e8f1f8;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    [data-theme="light"] .shortcuts-title {
        color: #0a1628;
    }

    .shortcuts-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .shortcuts-close:hover {
        background: rgba(239, 68, 68, 0.2);
        transform: scale(1.05);
    }

    .shortcut-group {
        margin-bottom: 16px;
    }

    .shortcut-group-title {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #5a8aa8;
        margin-bottom: 10px;
    }

    .shortcut-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 4px;
        transition: background 0.15s ease;
    }

    .shortcut-item:hover {
        background: rgba(78, 158, 222, 0.1);
    }

    .shortcut-label {
        color: #8eb8d4;
        font-size: 13px;
    }

    [data-theme="light"] .shortcut-label {
        color: #1a3a5c;
    }

    .shortcut-keys {
        display: flex;
        gap: 4px;
    }

    .key {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 26px;
        padding: 0 8px;
        background: #1a3a5c;
        border: 1px solid #264d73;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #e8f1f8;
        text-transform: uppercase;
        box-shadow: 0 2px 0 #0a1628;
    }

    [data-theme="light"] .key {
        background: #ffffff;
        border-color: #8eb8d4;
        color: #0a1628;
        box-shadow: 0 2px 0 #d1e5f4;
    }

    .key-plus {
        color: #5a8aa8;
        font-size: 12px;
        padding: 0 2px;
    }

    /* Help Button */
    .shortcuts-help-btn {
        position: fixed;
        bottom: 30px;
        left: 20px;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(78, 158, 222, 0.2);
        border: 1px solid #4e9ede;
        color: #4e9ede;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        z-index: 999;
    }

    .shortcuts-help-btn:hover {
        background: #4e9ede;
        color: #0a1628;
        transform: scale(1.05);
    }

    @media (max-width: 1023px) {
        .shortcuts-help-btn {
            bottom: 100px;
        }
    }
</style>

<!-- Shortcuts Modal -->
<div class="shortcuts-modal" id="shortcutsModal">
    <div class="shortcuts-content">
        <div class="shortcuts-header">
            <div class="shortcuts-title">
                <i class="bi bi-keyboard"></i>
                Raccourcis clavier
            </div>
            <button class="shortcuts-close" onclick="toggleShortcutsModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="shortcut-group">
            <div class="shortcut-group-title">Navigation</div>
            <div class="shortcut-item">
                <span class="shortcut-label">Rechercher</span>
                <div class="shortcut-keys">
                    <span class="key">Ctrl</span>
                    <span class="key-plus">+</span>
                    <span class="key">K</span>
                </div>
            </div>
            <div class="shortcut-item">
                <span class="shortcut-label">Aller à l'accueil</span>
                <div class="shortcut-keys">
                    <span class="key">Alt</span>
                    <span class="key-plus">+</span>
                    <span class="key">H</span>
                </div>
            </div>
        </div>

        <div class="shortcut-group">
            <div class="shortcut-group-title">Actions</div>
            <div class="shortcut-item">
                <span class="shortcut-label">Basculer le thème</span>
                <div class="shortcut-keys">
                    <span class="key">Ctrl</span>
                    <span class="key-plus">+</span>
                    <span class="key">Shift</span>
                    <span class="key-plus">+</span>
                    <span class="key">D</span>
                </div>
            </div>
            <div class="shortcut-item">
                <span class="shortcut-label">Afficher les raccourcis</span>
                <div class="shortcut-keys">
                    <span class="key">?</span>
                </div>
            </div>
            <div class="shortcut-item">
                <span class="shortcut-label">Fermer le modal</span>
                <div class="shortcut-keys">
                    <span class="key">Esc</span>
                </div>
            </div>
        </div>

        <div class="shortcut-group">
            <div class="shortcut-group-title">Étudiants</div>
            <div class="shortcut-item">
                <span class="shortcut-label">Nouvel étudiant</span>
                <div class="shortcut-keys">
                    <span class="key">Alt</span>
                    <span class="key-plus">+</span>
                    <span class="key">N</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help Button (hidden on mobile) -->
<button class="shortcuts-help-btn hidden lg:flex" id="shortcutsHelpBtn" title="Raccourcis clavier (?)">
    <i class="bi bi-keyboard"></i>
</button>

<script>
// Keyboard shortcuts functionality
function toggleShortcutsModal() {
    const modal = document.getElementById('shortcutsModal');
    modal.classList.toggle('show');
}

document.addEventListener('keydown', function(e) {
    // Show shortcuts modal with "?"
    if (e.key === '?' && !e.ctrlKey && !e.altKey) {
        const activeElement = document.activeElement;
        if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault();
            toggleShortcutsModal();
        }
    }

    // Close modal with Escape
    if (e.key === 'Escape') {
        const modal = document.getElementById('shortcutsModal');
        if (modal && modal.classList.contains('show')) {
            modal.classList.remove('show');
        }
    }

    // Focus search with Ctrl+K
    if (e.ctrlKey && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('std-search') || 
                           document.getElementById('cours-search') || 
                           document.getElementById('prof-search');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    // Toggle theme with Ctrl+Shift+D
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.click();
        }
    }

    // Go to home with Alt+H
    if (e.altKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = './accueil';
    }

    // New student with Alt+N
    if (e.altKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = './creat.student';
    }
});

// Help button click
document.getElementById('shortcutsHelpBtn')?.addEventListener('click', toggleShortcutsModal);

// Close modal when clicking outside
document.getElementById('shortcutsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('show');
    }
});
</script>
