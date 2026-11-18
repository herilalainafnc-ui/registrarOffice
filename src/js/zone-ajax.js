/*
 * Naviguer dans une page du back-office sans la recharger.
 *
 * Une page declare sa zone de contenu : <div id="..." data-zone-ajax>. Dans
 * cette zone, sont intercepte :
 *   - un lien vers la meme page (meme chemin, autres parametres) ;
 *   - un formulaire GET vers la meme page ;
 *   - un champ marque data-zone-auto (liste, case) : il envoie son
 *     formulaire des qu'il change, a la place de onchange="this.form.submit()".
 * La page est demandee en arriere-plan ; seule la zone est remplacee, le menu
 * et la barre du haut restent. L'adresse suit, Precedent et Suivant du
 * navigateur marchent.
 *
 * Les scripts de la zone sont rejoues apres chaque remplacement, chacun dans
 * un bloc { } : leurs const et let ne se heurtent pas a ceux de la version
 * precedente, et leurs fonctions restent globales (onclick="..."). Une
 * bibliotheque deja chargee ne l'est pas deux fois.
 *
 * ZoneAjax.recharger() remplace location.reload() apres une action :
 * la zone reprend l'etat a jour, sans recharger la page.
 *
 * Un menu hors de la zone peut la piloter : ses liens vers la meme page,
 * dans un conteneur data-zone-pilote, naviguent aussi dans la zone. Dans un
 * groupe data-zone-actif="classes", le lien de l'adresse courante recoit ces
 * classes et aria-current ; data-zone-cle="id page" limite la comparaison a
 * ces parametres.
 *
 * Liens et formulaires marques data-zone-ignorer : navigation classique.
 * En cas d'echec (session expiree, erreur serveur), navigation classique.
 * Charge par init/footer.php ; sans zone dans la page, il ne fait rien.
 */
(function () {
    'use strict';

    var zone = document.querySelector('[data-zone-ajax]');
    if (!zone || !zone.id || !window.fetch || !window.DOMParser || !history.pushState) { return; }

    var sequence = 0;                // seule la derniere demande s'affiche
    var scriptsCharges = new Set();  // bibliotheques deja presentes
    document.querySelectorAll('script[src]').forEach(function (s) { scriptsCharges.add(s.src); });

    function memePage(url) {
        return url.origin === location.origin && url.pathname === location.pathname;
    }

    /* Les scripts d'un document analyse ne s'executent pas : on les recree. */
    function rejouerScripts(racine) {
        racine.querySelectorAll('script').forEach(function (ancien) {
            var type = (ancien.getAttribute('type') || 'text/javascript').toLowerCase();
            if (type !== 'text/javascript' && type !== 'application/javascript' && type !== 'module') { return; }
            var neuf = document.createElement('script');
            if (ancien.src) {
                if (scriptsCharges.has(ancien.src)) { ancien.remove(); return; }
                scriptsCharges.add(ancien.src);
                neuf.src = ancien.src;
                neuf.async = false;
            } else {
                neuf.textContent = '{\n' + ancien.textContent + '\n}';
            }
            ancien.replaceWith(neuf);
        });
    }

    /* Retour en haut de la zone. Le focus revient sur le champ qui a servi
       (un filtre garde son id), sinon sur le titre de la nouvelle vue. */
    function remonter(focusId) {
        zone.scrollTop = 0;
        var haut = zone.getBoundingClientRect().top;
        if (haut < 0) { zone.scrollIntoView({ block: 'start' }); }
        var champ = focusId ? document.getElementById(focusId) : null;
        if (champ && zone.contains(champ)) { champ.focus({ preventScroll: true }); return; }
        var titre = zone.querySelector('h1, h2');
        if (titre) {
            titre.setAttribute('tabindex', '-1');
            titre.focus({ preventScroll: true });
        }
    }

    /**
     * Affiche la zone de l'adresse demandee.
     * options.historique : false pour Precedent / Suivant et le rechargement ;
     * options.garderDefilement : rester a la meme hauteur (rechargement).
     */
    function aller(url, options) {
        options = options || {};
        var jeton = ++sequence;
        var hauteur = zone.scrollTop;
        var actif = document.activeElement;
        var focusId = actif && zone.contains(actif) && actif.id ? actif.id : null;
        zone.setAttribute('aria-busy', 'true');

        return fetch(url.href, { credentials: 'same-origin' })
            .then(function (reponse) {
                /* Redirige ailleurs (connexion) ou en erreur : navigation classique. */
                if (!reponse.ok || (reponse.redirected && !memePage(new URL(reponse.url)))) { throw new Error('zone'); }
                return reponse.text();
            })
            .then(function (html) {
                if (jeton !== sequence) { return; }
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var neuve = doc.getElementById(zone.id);
                if (!neuve) { throw new Error('zone'); }

                zone.replaceChildren.apply(zone, Array.prototype.slice.call(neuve.childNodes));
                if (doc.title) { document.title = doc.title; }
                rejouerScripts(zone);
                zone.removeAttribute('aria-busy');

                if (options.historique !== false) {
                    /* La meme adresse (meme choix refait) remplace l'entree au lieu d'en
                       empiler une identique, que Precedent semblerait ignorer. */
                    var cible = url.pathname + url.search + url.hash;
                    var memeAdresse = cible === location.pathname + location.search + location.hash;
                    history[memeAdresse ? 'replaceState' : 'pushState']({ zone: zone.id }, '', cible);
                }
                marquerActifs();
                if (options.garderDefilement) { zone.scrollTop = hauteur; } else { remonter(focusId); }
                document.dispatchEvent(new CustomEvent('zone:chargee', { detail: { url: url.href } }));
            })
            .catch(function () {
                if (jeton !== sequence) { return; }
                location.href = url.href;
            });
    }

    /* Menus pilotes : le lien de l'adresse courante est marque actif. */
    function marquerActifs() {
        var courant = new URL(location.href);
        document.querySelectorAll('[data-zone-actif]').forEach(function (groupe) {
            var classes = groupe.getAttribute('data-zone-actif').split(/\s+/).filter(Boolean);
            var cles = (groupe.getAttribute('data-zone-cle') || '').split(/\s+/).filter(Boolean);
            groupe.querySelectorAll('a[href]').forEach(function (a) {
                var href = a.getAttribute('href');
                if (!href || href.charAt(0) === '#') { return; }
                var u = new URL(a.href, location.href);
                if (!memePage(u)) { return; }
                var params = cles.length ? cles : Array.from(u.searchParams.keys());
                var actif = params.length > 0 && params.every(function (k) {
                    return u.searchParams.get(k) === courant.searchParams.get(k);
                });
                var cible = a.firstElementChild || a;
                classes.forEach(function (c) { cible.classList.toggle(c, actif); });
                if (actif) { a.setAttribute('aria-current', 'page'); } else { a.removeAttribute('aria-current'); }
            });
        });
    }

    function envoyerFormulaire(form) {
        var url = new URL(form.getAttribute('action') || location.href, location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        url.hash = '';
        return aller(url);
    }

    function formulaireGet(form) {
        if (!form || form.hasAttribute('data-zone-ignorer')) { return false; }
        var methode = (form.getAttribute('method') || 'get').toLowerCase();
        if (methode !== 'get' || (form.target && form.target !== '_self')) { return false; }
        return memePage(new URL(form.getAttribute('action') || location.href, location.href));
    }

    /* Les ecouteurs sont sur la zone, qui n'est jamais remplacee (seulement son
       contenu) : ils passent avant ceux du document, dont le rideau de
       transition (src/js/transitions.js), qui s'ecarte sur defaultPrevented. */
    function surClic(e, conteneur) {
        var lien = e.target.closest && e.target.closest('a[href]');
        if (!lien || !conteneur.contains(lien) || e.defaultPrevented) { return; }
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        if (lien.hasAttribute('data-zone-ignorer') || lien.hasAttribute('download')) { return; }
        if (lien.target && lien.target !== '_self') { return; }
        var href = lien.getAttribute('href');
        if (!href || href.charAt(0) === '#') { return; }
        var url = new URL(lien.href, location.href);
        if (!memePage(url) || (url.search === location.search && url.hash)) { return; }
        e.preventDefault();
        aller(url);
    }
    zone.addEventListener('click', function (e) { surClic(e, zone); });
    document.querySelectorAll('[data-zone-pilote]').forEach(function (pilote) {
        pilote.addEventListener('click', function (e) { surClic(e, pilote); });
    });

    zone.addEventListener('submit', function (e) {
        if (e.defaultPrevented || !formulaireGet(e.target)) { return; }
        e.preventDefault();
        envoyerFormulaire(e.target);
    });

    zone.addEventListener('change', function (e) {
        var champ = e.target;
        if (!champ.hasAttribute || !champ.hasAttribute('data-zone-auto') || !formulaireGet(champ.form)) { return; }
        envoyerFormulaire(champ.form);
    });

    history.replaceState({ zone: zone.id }, '', location.href);
    window.addEventListener('popstate', function (e) {
        if (!e.state || e.state.zone !== zone.id) { return; }
        aller(new URL(location.href), { historique: false });
    });

    window.ZoneAjax = {
        /** Reprend la zone a jour apres une action, sans recharger la page. */
        recharger: function () {
            return aller(new URL(location.href), { historique: false, garderDefilement: true });
        },
        /** Affiche une autre adresse de la meme page. */
        aller: function (href) { return aller(new URL(href, location.href)); }
    };
})();
