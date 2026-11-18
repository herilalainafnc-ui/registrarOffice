<!-- ===== GLOBAL TOAST NOTIFICATION SYSTEM ===== -->
<?php /* Chaque jeton de couleur a sa valeur de repli (la palette sombre de
         src/css/tokens.css) : les toasts s'affichent aussi sur les pages qui ne
         chargent pas les jetons, comme le site public (landing/). */ ?>
<style>
    :root {
        --toast-bg: linear-gradient(160deg, rgb(var(--c-canvas, 18 22 44) / 0.97), rgb(var(--c-surface, 26 31 60) / 0.96));
        --toast-border: rgb(var(--c-line-strong, 58 66 108) / 0.28);
        --toast-text: rgb(var(--c-text, 236 238 246));
        --toast-muted: rgb(var(--c-text-2, 179 185 214));
        --toast-shadow: 0 20px 50px rgba(2, 6, 23, 0.48);
        --toast-close-bg: rgb(var(--c-surface-2, 32 38 72) / 0.58);
        --toast-progress: rgba(148, 163, 184, 0.45);
        --toast-success: #10b981;
        --toast-error: #ef4444;
        --toast-warning: #f59e0b;
        --toast-info: rgb(var(--c-accent, 90 111 218));
    }

    [data-theme="light"] {
        --toast-bg: linear-gradient(160deg, rgb(var(--c-surface, 26 31 60) / 0.98), rgb(var(--c-surface-2, 32 38 72) / 0.97));
        --toast-border: rgb(var(--c-line-strong, 58 66 108) / 0.48);
        --toast-text: rgb(var(--c-text, 236 238 246));
        --toast-muted: rgb(var(--c-text-2, 179 185 214));
        --toast-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
        --toast-close-bg: rgb(var(--c-surface-2, 32 38 72) / 0.9);
        --toast-progress: rgba(100, 116, 139, 0.4);
    }

    .toast-container {
        position: fixed;
        top: 74px;
        right: 18px;
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

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid var(--toast-border);
        box-shadow: var(--toast-shadow);
        pointer-events: auto;
        transform: translateX(120%);
        opacity: 0;
        transition: transform 0.35s ease, opacity 0.25s ease;
        backdrop-filter: blur(10px);
        min-width: 280px;
        max-width: 100%;
        color: var(--toast-text);
        background: var(--toast-bg);
    }

    .toast.show {
        transform: translateX(0);
        opacity: 1;
    }

    .toast.hide {
        transform: translateX(120%);
        opacity: 0;
    }

    .toast-success { border-left: 4px solid var(--toast-success); }
    .toast-error { border-left: 4px solid var(--toast-error); }
    .toast-warning { border-left: 4px solid var(--toast-warning); }
    .toast-info,
    .toast-loading { border-left: 4px solid var(--toast-info); }

    .toast-icon {
        width: 28px;
        height: 28px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgb(var(--c-accent, 90 111 218) / 0.14);
        color: var(--toast-text);
        flex-shrink: 0;
    }

    .toast-success .toast-icon { background: rgba(16, 185, 129, 0.18); }
    .toast-error .toast-icon { background: rgba(239, 68, 68, 0.18); }
    .toast-warning .toast-icon { background: rgba(245, 158, 11, 0.2); }

    .toast-content {
        flex: 1;
        min-width: 0;
    }

    .toast-title {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
        color: var(--toast-text);
    }

    .toast-message {
        font-size: 13px;
        color: var(--toast-muted);
        line-height: 1.4;
        white-space: pre-line;
    }

    .toast-close {
        width: 24px;
        height: 24px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--toast-close-bg);
        border: 1px solid var(--toast-border);
        cursor: pointer;
        color: inherit;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .toast-close:hover {
        transform: translateY(-1px);
        filter: brightness(1.05);
    }

    .toast-close i {
        font-size: 12px;
    }

    .toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--toast-progress);
        border-radius: 0 0 0 12px;
        transition: width linear;
    }

    .toast-loading .toast-icon {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* --- Modale de confirmation (remplace window.confirm) ------------------
       confirm() fige le navigateur, son apparence échappe à l'application et
       son texte ne se met pas en forme. La modale reprend les couleurs des
       toasts ; au-dessus d'eux (z-index) pour qu'un message ne la masque pas. */
    .tconf {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgb(var(--c-canvas, 18 22 44) / 0.62);
        backdrop-filter: blur(3px);
        opacity: 0;
        transition: opacity 0.16s ease;
    }

    .tconf.est-visible { opacity: 1; }

    .tconf__boite {
        width: min(480px, 100%);
        max-height: calc(100vh - 32px);
        overflow: auto;
        border-radius: 14px;
        border: 1px solid var(--toast-border);
        background: var(--toast-bg);
        box-shadow: var(--toast-shadow);
        color: var(--toast-text);
        padding: 20px 20px 16px;
        transform: translateY(8px) scale(0.98);
        transition: transform 0.16s ease;
    }

    .tconf.est-visible .tconf__boite { transform: none; }

    .tconf__tete {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .tconf__icone {
        width: 36px;
        height: 36px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
        background: rgb(var(--c-accent, 90 111 218) / 0.16);
        color: var(--toast-info);
    }

    .tconf--danger .tconf__icone {
        background: rgba(239, 68, 68, 0.16);
        color: var(--toast-error);
    }

    .tconf__titre {
        margin: 6px 0 0;
        font-size: 16px;
        font-weight: 600;
        line-height: 1.3;
        color: var(--toast-text);
    }

    .tconf__texte {
        margin: 12px 0 0;
        font-size: 14px;
        line-height: 1.55;
        color: var(--toast-muted);
        white-space: pre-line;          /* les \n des anciens confirm() restent des retours à la ligne */
        overflow-wrap: anywhere;
    }

    .tconf__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .tconf__bouton {
        min-height: 40px;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid var(--toast-border);
        transition: filter 0.15s ease, transform 0.15s ease;
    }

    .tconf__bouton:hover { filter: brightness(1.08); }
    .tconf__bouton:active { transform: translateY(1px); }

    .tconf__bouton:focus-visible {
        outline: 2px solid var(--toast-info);
        outline-offset: 2px;
    }

    .tconf__bouton--annuler {
        background: var(--toast-close-bg);
        color: var(--toast-text);
    }

    .tconf__bouton--ok {
        background: linear-gradient(135deg, rgb(var(--c-accent-strong, 70 88 194)), rgb(var(--c-accent-strong, 70 88 194)));
        border-color: transparent;
        color: #fff;
    }

    .tconf--danger .tconf__bouton--ok {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
    }

    @media (prefers-reduced-motion: reduce) {
        .tconf, .tconf__boite, .toast { transition: none; }
    }
</style>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ===== GLOBAL TOAST NOTIFICATION SYSTEM =====
/* Exposé sur window : le script était auparavant déclaré en « const Toast »,
   qui ne crée PAS de propriété window.Toast. Tous les appels écrits
   « if (window.Toast) … else alert() » tombaient donc sur alert(), et les
   « if (window.Toast) Toast.success() » n'affichaient rien. */
(function () {
    'use strict';

    // Inclus deux fois dans une même page : on garde la première instance.
    if (window.Toast && window.Toast.__registrar) { return; }

    function echapper(texte) {
        return String(texte == null ? '' : texte)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    var Toast = {
        __registrar: true,
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
            /* Les icones sont celles de Bootstrap Icons. Le back-office les
               charge deja ; ailleurs (site public), on ajoute la meme feuille,
               a la meme adresse, pour que les toasts gardent leurs icones. */
            if (!document.querySelector('link[href*="bootstrap-icons"]')) {
                const icones = document.createElement('link');
                icones.rel = 'stylesheet';
                icones.href = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';
                document.head.appendChild(icones);
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
            if (!this.icons[type]) { type = 'info'; }

            const id = 'toast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            const title = options.title || this.titles[type];
            const duration = options.duration !== undefined ? options.duration : this.defaultDuration;
            const showProgress = options.showProgress !== false && duration > 0;

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.id = id;
            toast.style.position = 'relative';
            toast.style.overflow = 'hidden';
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="bi ${this.icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${echapper(title)}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button type="button" class="toast-close" aria-label="Fermer" onclick="Toast.dismiss('${id}')">
                    <i class="bi bi-x"></i>
                </button>
                ${showProgress ? `<div class="toast-progress" style="width: 100%;"></div>` : ''}
            `;

            this.container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

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
            options.duration = 0;
            return this.show(message, 'loading', options);
        },

        /**
         * Confirmation en modale, à la place de window.confirm().
         * Renvoie une promesse résolue à true (confirmé) ou false (annulé).
         *
         * Le message est posé en texte brut (textContent) : il peut contenir des
         * libellés venus de la base sans risque d'injection, et ses \n restent
         * des retours à la ligne.
         *
         * @param {string} message
         * @param {{title?:string, confirmText?:string, cancelText?:string, danger?:boolean}} options
         *        danger : bouton rouge. Déduit du texte s'il n'est pas précisé
         *        (supprimer, vider, fermer, désabonner, déconnecter…).
         */
        confirm: function(message, options = {}) {
            const texte = String(message == null ? '' : message);
            const titre = options.title || 'Confirmation';
            const ok = options.confirmText || 'Confirmer';
            const annuler = options.cancelText || 'Annuler';
            const danger = options.danger !== undefined
                ? !!options.danger
                : /supprim|vider|fermer|désabonn|desabonn|déconnect|retir|définitiv|perdu/i.test(titre + ' ' + ok + ' ' + texte);

            // Une confirmation à la fois : les suivantes attendent leur tour.
            const tour = (Toast._fileConfirm || Promise.resolve()).then(() => new Promise((resolve) => {
                const precedent = document.activeElement;
                const id = 'tconf-' + Date.now();

                const fond = document.createElement('div');
                fond.className = 'tconf' + (danger ? ' tconf--danger' : '');
                fond.innerHTML = `
                    <div class="tconf__boite" role="alertdialog" aria-modal="true"
                         aria-labelledby="${id}-titre" aria-describedby="${id}-texte">
                        <div class="tconf__tete">
                            <span class="tconf__icone" aria-hidden="true">
                                <i class="bi ${danger ? 'bi-exclamation-triangle' : 'bi-question-lg'}"></i>
                            </span>
                            <h2 class="tconf__titre" id="${id}-titre"></h2>
                        </div>
                        <p class="tconf__texte" id="${id}-texte"></p>
                        <div class="tconf__actions">
                            <button type="button" class="tconf__bouton tconf__bouton--annuler" data-action="annuler"></button>
                            <button type="button" class="tconf__bouton tconf__bouton--ok" data-action="ok"></button>
                        </div>
                    </div>`;
                fond.querySelector('.tconf__titre').textContent = titre;
                fond.querySelector('.tconf__texte').textContent = texte;
                const boutonAnnuler = fond.querySelector('[data-action="annuler"]');
                const boutonOk = fond.querySelector('[data-action="ok"]');
                boutonAnnuler.textContent = annuler;
                boutonOk.textContent = ok;

                let fini = false;
                const fermer = (resultat) => {
                    if (fini) { return; }
                    fini = true;
                    document.removeEventListener('keydown', clavier, true);
                    fond.classList.remove('est-visible');
                    setTimeout(() => fond.remove(), 180);
                    if (precedent && typeof precedent.focus === 'function' && document.contains(precedent)) {
                        precedent.focus();
                    }
                    resolve(resultat);
                };

                const clavier = (e) => {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                        fermer(false);
                    } else if (e.key === 'Tab') {
                        // Le focus reste dans la modale.
                        const cibles = [boutonAnnuler, boutonOk];
                        const i = cibles.indexOf(document.activeElement);
                        e.preventDefault();
                        cibles[(i + (e.shiftKey ? cibles.length - 1 : 1)) % cibles.length].focus();
                    }
                };

                boutonAnnuler.addEventListener('click', () => fermer(false));
                boutonOk.addEventListener('click', () => fermer(true));
                fond.addEventListener('mousedown', (e) => { if (e.target === fond) { fermer(false); } });
                document.addEventListener('keydown', clavier, true);

                document.body.appendChild(fond);
                requestAnimationFrame(() => fond.classList.add('est-visible'));
                // Action destructrice : le focus part sur « Annuler », pas sur l'action.
                (danger ? boutonAnnuler : boutonOk).focus();
            }));

            Toast._fileConfirm = tour.catch(() => {});
            return tour;
        },

        /**
         * Pour un formulaire classique :
         *   <form onsubmit="return Toast.confirmerFormulaire(event, this, 'Désabonner ce contact ?')">
         * Bloque l'envoi, demande, puis renvoie le formulaire si c'est confirmé.
         */
        confirmerFormulaire: function(evenement, formulaire, message, options = {}) {
            if (formulaire.dataset.toastConfirme === '1') {
                delete formulaire.dataset.toastConfirme;
                return true;
            }
            if (evenement) { evenement.preventDefault(); }
            this.confirm(message, options).then((confirme) => {
                if (!confirme) { return; }
                formulaire.dataset.toastConfirme = '1';
                if (typeof formulaire.requestSubmit === 'function') { formulaire.requestSubmit(); }
                else { formulaire.submit(); }
            });
            return false;
        },

        update: function(id, message, type, options = {}) {
            const toast = document.getElementById(id);
            if (toast) {
                const title = options.title || this.titles[type];
                const duration = options.duration !== undefined ? options.duration : this.defaultDuration;

                toast.className = `toast toast-${type} show`;
                toast.querySelector('.toast-icon i').className = `bi ${this.icons[type]}`;
                toast.querySelector('.toast-title').textContent = title;
                toast.querySelector('.toast-message').textContent = message;

                if (duration > 0) {
                    setTimeout(() => {
                        this.dismiss(id);
                    }, duration);
                }
            }
        }
    };

    window.Toast = Toast;

    /* alert() natif -> toast. Les appels qui restent (replis, pages anciennes)
       n'affichent plus la boîte du navigateur. Le type est déduit du texte ;
       le message est échappé, il peut contenir une valeur venue du serveur.
       L'original reste disponible : window.alert.natif(). */
    const alerteNative = window.alert;
    window.alert = function (message) {
        const texte = String(message == null ? '' : message);
        let type = 'info';
        if (/erreur|échec|echec|impossible|invalide|refus|introuvable|n'est pas|expir/i.test(texte)) { type = 'error'; }
        else if (/veuillez|aucun|attention|sélectionn|selectionn|obligatoire/i.test(texte)) { type = 'warning'; }
        else if (/succès|succes|réussi|enregistré|supprimé|ajouté/i.test(texte)) { type = 'success'; }
        Toast.show(echapper(texte), type, { duration: type === 'error' ? 8000 : 5000 });
    };
    window.alert.natif = alerteNative;

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

    document.addEventListener('DOMContentLoaded', function() {
        Toast.init();
    });
})();
</script>
