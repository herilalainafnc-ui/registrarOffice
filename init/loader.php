<!-- ===== GLOBAL LOADING OVERLAY ===== -->
<style>
    /* Page Loading Overlay */
    .page-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(10, 22, 40, 0.95);
        backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .page-loading-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    [data-theme="light"] .page-loading-overlay {
        background: rgba(232, 241, 248, 0.95);
    }

    /* Spinner */
    .page-spinner {
        width: 50px;
        height: 50px;
        border: 3px solid #1a3a5c;
        border-top-color: #4e9ede;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    [data-theme="light"] .page-spinner {
        border-color: #8eb8d4;
        border-top-color: #4e9ede;
    }

    .page-loading-text {
        margin-top: 16px;
        color: #8eb8d4;
        font-size: 14px;
        font-weight: 500;
    }

    [data-theme="light"] .page-loading-text {
        color: #1a3a5c;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Progress bar for page load */
    .page-progress {
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: linear-gradient(90deg, #4e9ede, #3d8ed0);
        z-index: 99998;
        transition: width 0.3s ease;
        box-shadow: 0 0 10px rgba(78, 158, 222, 0.5);
    }

    .page-progress.loading {
        animation: progressPulse 1.5s ease-in-out infinite;
    }

    @keyframes progressPulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
</style>

<!-- Page Progress Bar -->
<div class="page-progress" id="pageProgress"></div>

<!-- Loading Overlay -->
<div class="page-loading-overlay" id="pageLoadingOverlay">
    <div class="page-spinner"></div>
    <div class="page-loading-text" id="pageLoadingText">Chargement...</div>
</div>

<script>
// Page Loading Manager
const PageLoader = {
    overlay: null,
    progress: null,
    loadingText: null,

    init: function() {
        this.overlay = document.getElementById('pageLoadingOverlay');
        this.progress = document.getElementById('pageProgress');
        this.loadingText = document.getElementById('pageLoadingText');
    },

    show: function(text = 'Chargement...') {
        this.init();
        if (this.overlay) {
            if (this.loadingText) this.loadingText.textContent = text;
            this.overlay.classList.add('show');
        }
        if (this.progress) {
            this.progress.style.width = '30%';
            this.progress.classList.add('loading');
        }
    },

    hide: function() {
        this.init();
        if (this.progress) {
            this.progress.style.width = '100%';
            this.progress.classList.remove('loading');
            setTimeout(() => {
                this.progress.style.width = '0%';
            }, 300);
        }
        if (this.overlay) {
            this.overlay.classList.remove('show');
        }
    },

    setProgress: function(percent) {
        this.init();
        if (this.progress) {
            this.progress.style.width = Math.min(percent, 100) + '%';
        }
    },

    setText: function(text) {
        this.init();
        if (this.loadingText) {
            this.loadingText.textContent = text;
        }
    }
};

// Auto-hide on page load
window.addEventListener('load', function() {
    PageLoader.hide();
});

// Show loading on page navigation (optional - uncomment if needed)
// document.addEventListener('click', function(e) {
//     const link = e.target.closest('a');
//     if (link && link.href && !link.href.startsWith('#') && !link.target && !link.hasAttribute('data-bs-toggle')) {
//         PageLoader.show();
//     }
// });

// jQuery integration
if (typeof $ !== 'undefined') {
    $.pageLoader = {
        show: function(text) { PageLoader.show(text); },
        hide: function() { PageLoader.hide(); },
        setProgress: function(percent) { PageLoader.setProgress(percent); },
        setText: function(text) { PageLoader.setText(text); }
    };
}
</script>
