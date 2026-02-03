<!-- ===== GLOBAL TOAST NOTIFICATION SYSTEM ===== -->
<style>
    /* Toast Container */
    .toast-container {
        position: fixed;
        top: 70px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
        max-width: 400px;
    }

    @media (max-width: 640px) {
        .toast-container {
            top: auto;
            bottom: 80px;
            right: 10px;
            left: 10px;
            max-width: none;
        }
    }

    /* Toast Item */
    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        pointer-events: auto;
        transform: translateX(120%);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        backdrop-filter: blur(10px);
        min-width: 280px;
        max-width: 100%;
    }

    .toast.show {
        transform: translateX(0);
        opacity: 1;
    }

    .toast.hide {
        transform: translateX(120%);
        opacity: 0;
    }

    /* Toast Types */
    .toast-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
        border-left: 4px solid #059669;
        color: #ffffff;
    }

    .toast-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.95), rgba(220, 38, 38, 0.95));
        border-left: 4px solid #dc2626;
        color: #ffffff;
    }

    .toast-warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.95), rgba(217, 119, 6, 0.95));
        border-left: 4px solid #d97706;
        color: #ffffff;
    }

    .toast-info {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(37, 99, 235, 0.95));
        border-left: 4px solid #2563eb;
        color: #ffffff;
    }

    /* Toast Icon */
    .toast-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2);
        flex-shrink: 0;
    }

    .toast-icon i {
        font-size: 14px;
    }

    /* Toast Content */
    .toast-content {
        flex: 1;
        min-width: 0;
    }

    .toast-title {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
    }

    .toast-message {
        font-size: 13px;
        opacity: 0.9;
        line-height: 1.4;
    }

    /* Toast Close Button */
    .toast-close {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        cursor: pointer;
        color: inherit;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .toast-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    .toast-close i {
        font-size: 12px;
    }

    /* Progress Bar */
    .toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background: rgba(255, 255, 255, 0.4);
        border-radius: 0 0 0 10px;
        transition: width linear;
    }

    /* Light Mode Adjustments */
    [data-theme="light"] .toast {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    /* Loading Toast */
    .toast-loading {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95));
        border-left: 4px solid #4e9ede;
        color: #ffffff;
    }

    .toast-loading .toast-icon {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Confirm Dialog Toast */
    .toast-confirm {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.98), rgba(15, 23, 42, 0.98));
        border-left: 4px solid #8b5cf6;
        color: #ffffff;
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    .toast-confirm .toast-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .toast-confirm .toast-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .toast-confirm .toast-btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }

    .toast-confirm .toast-btn-cancel {
        background: rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
    }

    .toast-confirm .toast-btn-cancel:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .toast-confirm .toast-btn-confirm {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .toast-confirm .toast-btn-confirm:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        transform: translateY(-1px);
    }
</style>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ===== GLOBAL TOAST NOTIFICATION SYSTEM =====
const Toast = {
    container: null,
    defaultDuration: 4000,

    init: function() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.id = 'toastContainer';
            document.body.appendChild(this.container);
        }
    },

    icons: {
        success: 'bi-check-lg',
        error: 'bi-x-lg',
        warning: 'bi-exclamation-lg',
        info: 'bi-info-lg',
        loading: 'bi-arrow-repeat'
    },

    titles: {
        success: 'Succès',
        error: 'Erreur',
        warning: 'Attention',
        info: 'Information',
        loading: 'Chargement...'
    },

    show: function(message, type = 'info', options = {}) {
        this.init();

        const id = 'toast-' + Date.now();
        const title = options.title || this.titles[type];
        const duration = options.duration !== undefined ? options.duration : this.defaultDuration;
        const showProgress = options.showProgress !== false && duration > 0;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.id = id;
        toast.style.position = 'relative';
        toast.style.overflow = 'hidden';

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="bi ${this.icons[type]}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="Toast.dismiss('${id}')">
                <i class="bi bi-x"></i>
            </button>
            ${showProgress ? `<div class="toast-progress" style="width: 100%;"></div>` : ''}
        `;

        this.container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Progress bar animation & auto dismiss
        if (duration > 0) {
            const progressBar = toast.querySelector('.toast-progress');
            if (progressBar) {
                progressBar.style.transitionDuration = duration + 'ms';
                requestAnimationFrame(() => {
                    progressBar.style.width = '0%';
                });
            }

            setTimeout(() => {
                this.dismiss(id);
            }, duration);
        }

        return id;
    },

    dismiss: function(id) {
        const toast = document.getElementById(id);
        if (toast) {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(() => {
                toast.remove();
            }, 400);
        }
    },

    dismissAll: function() {
        if (this.container) {
            const toasts = this.container.querySelectorAll('.toast');
            toasts.forEach(toast => {
                this.dismiss(toast.id);
            });
        }
    },

    // Shorthand methods
    success: function(message, options = {}) {
        return this.show(message, 'success', options);
    },

    error: function(message, options = {}) {
        return this.show(message, 'error', options);
    },

    warning: function(message, options = {}) {
        return this.show(message, 'warning', options);
    },

    info: function(message, options = {}) {
        return this.show(message, 'info', options);
    },

    loading: function(message = 'Veuillez patienter...', options = {}) {
        options.duration = 0; // Don't auto-dismiss loading toasts
        return this.show(message, 'loading', options);
    },

    // Confirm dialog
    confirm: function(message, options = {}) {
        return new Promise((resolve) => {
            this.init();

            const id = 'toast-confirm-' + Date.now();
            const title = options.title || 'Confirmation';
            const confirmText = options.confirmText || 'Confirmer';
            const cancelText = options.cancelText || 'Annuler';

            const toast = document.createElement('div');
            toast.className = 'toast toast-confirm';
            toast.id = id;

            toast.innerHTML = `
                <div class="toast-header">
                    <div class="toast-icon">
                        <i class="bi bi-question-lg"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                </div>
                <div class="toast-actions">
                    <button class="toast-btn toast-btn-cancel" data-action="cancel">${cancelText}</button>
                    <button class="toast-btn toast-btn-confirm" data-action="confirm">${confirmText}</button>
                </div>
            `;

            toast.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                this.dismiss(id);
                resolve(false);
            });

            toast.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                this.dismiss(id);
                resolve(true);
            });

            this.container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.add('show');
            });
        });
    },

    // Update existing toast (useful for loading -> success/error)
    update: function(id, message, type, options = {}) {
        const toast = document.getElementById(id);
        if (toast) {
            const title = options.title || this.titles[type];
            const duration = options.duration !== undefined ? options.duration : this.defaultDuration;

            // Update classes
            toast.className = `toast toast-${type} show`;

            // Update content
            toast.querySelector('.toast-icon i').className = `bi ${this.icons[type]}`;
            toast.querySelector('.toast-title').textContent = title;
            toast.querySelector('.toast-message').textContent = message;

            // Auto dismiss after update
            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss(id);
                }, duration);
            }
        }
    }
};

// jQuery plugin for easy access
if (typeof $ !== 'undefined') {
    $.toast = function(message, type, options) {
        return Toast.show(message, type || 'info', options || {});
    };
    $.toast.success = function(message, options) { return Toast.success(message, options); };
    $.toast.error = function(message, options) { return Toast.error(message, options); };
    $.toast.warning = function(message, options) { return Toast.warning(message, options); };
    $.toast.info = function(message, options) { return Toast.info(message, options); };
    $.toast.loading = function(message, options) { return Toast.loading(message, options); };
    $.toast.dismiss = function(id) { Toast.dismiss(id); };
    $.toast.confirm = function(message, options) { return Toast.confirm(message, options); };
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    Toast.init();
});
</script>
