/* =============================================================================
   Notifications de la vitrine — API
   =============================================================================
   Meme surface publique que init/toast.php cote administration :

       Toast.show(message, type, { duration, title })
       Toast.success(message)   Toast.error(message)
       Toast.warning(message)   Toast.info(message)

   Volontairement le meme nom global : l'administration et la vitrine ne sont
   jamais chargees ensemble, et un appel ecrit dans une page se relit sans se
   demander duquel des deux systemes il s'agit.

   Le message arrive du serveur en texte brut et est pose avec textContent :
   aucune interpolation de HTML, donc rien a echapper cote appelant. C'est
   important ici — la page d'admissions renvoie dans son erreur des valeurs
   saisies par un visiteur non authentifie.
   ========================================================================== */
(function () {
    'use strict';

    if (window.Toast) { return; }          // deja monte : on ne double pas

    var DUREE_DEFAUT = 5000;

    var TITRES = {
        success: 'Succès',
        error:   'Erreur',
        warning: 'Attention',
        info:    'Information'
    };

    /* Traces SVG a un seul chemin : pas de police d'icones a charger pour
       quatre pictogrammes. */
    var ICONES = {
        success: 'M20 6L9 17l-5-5',
        error:   'M18 6L6 18M6 6l12 12',
        warning: 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L14.7 3.9a2 2 0 0 0-3.4 0z',
        info:    'M12 16v-4m0-4h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z'
    };

    var pile = null;

    function laPile() {
        if (pile && pile.isConnected) { return pile; }
        pile = document.createElement('div');
        pile.className = 'vt-pile';
        /* « polite » et non « assertive » : une confirmation d'envoi ne doit
           pas couper la lecture en cours d'un lecteur d'ecran. */
        pile.setAttribute('aria-live', 'polite');
        pile.setAttribute('aria-atomic', 'false');
        document.body.appendChild(pile);
        return pile;
    }

    function svg(trace) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"'
             + ' stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"'
             + ' aria-hidden="true"><path d="' + trace + '"></path></svg>';
    }

    var Toast = {
        show: function (message, type, options) {
            if (!message) { return null; }
            type = ICONES[type] ? type : 'info';
            options = options || {};

            var duree = options.duration !== undefined ? options.duration : DUREE_DEFAUT;
            var titre = options.title !== undefined ? options.title : TITRES[type];

            var el = document.createElement('div');
            el.className = 'vt-toast vt-toast--' + type;
            el.setAttribute('role', type === 'error' ? 'alert' : 'status');

            var icone = document.createElement('span');
            icone.className = 'vt-toast__icone';
            icone.innerHTML = svg(ICONES[type]);          // trace interne, jamais l'entree
            el.appendChild(icone);

            var corps = document.createElement('div');
            corps.className = 'vt-toast__corps';
            if (titre) {
                var h = document.createElement('p');
                h.className = 'vt-toast__titre';
                h.textContent = titre;
                corps.appendChild(h);
            }
            var p = document.createElement('p');
            p.className = 'vt-toast__texte';
            p.textContent = message;                      // texte brut, pas de HTML
            corps.appendChild(p);
            el.appendChild(corps);

            var fermer = document.createElement('button');
            fermer.type = 'button';
            fermer.className = 'vt-toast__fermer';
            fermer.setAttribute('aria-label', 'Fermer la notification');
            fermer.innerHTML = svg('M18 6L6 18M6 6l12 12');
            el.appendChild(fermer);

            var jauge = null;
            if (duree > 0) {
                jauge = document.createElement('span');
                jauge.className = 'vt-toast__jauge';
                el.appendChild(jauge);
            }

            laPile().appendChild(el);

            var minuteur = null;
            var parti = false;

            function retirer() {
                if (parti) { return; }
                parti = true;
                if (minuteur) { clearTimeout(minuteur); }
                el.classList.remove('est-visible');
                el.classList.add('est-sortant');
                /* On attend la fin de la transition pour retirer le noeud, avec
                   un filet : si la transition n'a pas lieu — onglet en arriere-
                   plan, mouvement reduit — transitionend ne vient jamais et le
                   message resterait dans le document. */
                var fini = function () {
                    el.removeEventListener('transitionend', fini);
                    if (el.parentNode) { el.parentNode.removeChild(el); }
                };
                el.addEventListener('transitionend', fini);
                setTimeout(fini, 400);
            }

            fermer.addEventListener('click', retirer);

            requestAnimationFrame(function () {
                el.classList.add('est-visible');
                if (jauge) {
                    jauge.style.transform = 'scaleX(1)';
                    requestAnimationFrame(function () {
                        jauge.style.transitionDuration = duree + 'ms';
                        jauge.style.transform = 'scaleX(0)';
                    });
                }
            });

            if (duree > 0) { minuteur = setTimeout(retirer, duree); }

            return { close: retirer, element: el };
        },

        success: function (m, o) { return this.show(m, 'success', o); },
        error:   function (m, o) { return this.show(m, 'error',   o); },
        warning: function (m, o) { return this.show(m, 'warning', o); },
        info:    function (m, o) { return this.show(m, 'info',    o); }
    };

    window.Toast = Toast;
})();
