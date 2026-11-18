/*
 * Changement d'année universitaire sans recharger la page (Dean's List,
 * Top Students).
 *
 * Le conteneur [data-annees-conteneur] porte l'adresse de la page (data-url) ;
 * la barre [data-annees-nav] (src/student/annees-navigation.php) se tient
 * au-dessus, hors de la zone qui défile. Les liens [data-page-annee] restent de
 * vrais liens : sans JavaScript, ou avec Ctrl / clic milieu, ils ouvrent la
 * page entière. Sinon on demande à la page la seule année (?fragment=1), on la
 * met à la place de l'ancienne, la barre suit, et l'adresse aussi
 * (?page_annee=N) : Précédent / Suivant du navigateur et le partage de lien
 * marchent comme avant.
 *
 * Une année déjà vue, ou survolée, est gardée : y revenir est immédiat.
 * En cas d'échec (session expirée, erreur serveur, réseau), on retombe sur la
 * navigation classique, qui affiche la connexion ou l'erreur.
 */
(function () {
    'use strict';

    var conteneur = document.querySelector('[data-annees-conteneur]');
    if (!conteneur || !window.fetch || !window.history || !history.pushState) { return; }

    var barre = document.querySelector('[data-annees-nav]');
    var liste = barre ? barre.querySelector('[data-annees-choix]') : null;
    var total = barre ? parseInt(barre.getAttribute('data-annees-total'), 10) : 0;
    var adresse = conteneur.getAttribute('data-url') || location.pathname;
    var memoire = new Map();   // page -> Promise du HTML de l'année
    var sequence = 0;          // seule la dernière demande s'affiche
    var pageCourante = liste ? parseInt(liste.value, 10) : lirePage(location.search);

    var annonce = document.createElement('p');
    annonce.className = 'annees-annonce';
    annonce.setAttribute('aria-live', 'polite');
    conteneur.parentNode.insertBefore(annonce, conteneur);

    memoire.set(pageCourante, Promise.resolve(conteneur.innerHTML));
    history.replaceState({ pageAnnee: pageCourante }, '', location.href);

    function lirePage(recherche) {
        var n = parseInt(new URLSearchParams(recherche).get('page_annee'), 10);
        return n > 0 ? n : 1;
    }

    function adressePage(page) {
        var cible = new URL(location.href);
        cible.searchParams.set('page_annee', page);
        return cible.pathname + cible.search + cible.hash;
    }

    function libelle(page) {
        var option = liste && liste.options[page - 1];
        return option ? option.textContent.trim() : '';
    }

    function obtenir(page) {
        if (memoire.has(page)) { return memoire.get(page); }
        var promesse = fetch(adresse + '?fragment=1&page_annee=' + page, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (reponse) {
            // Une redirection, c'est la page de connexion : session expirée.
            if (reponse.redirected || !reponse.ok) { throw new Error('annee ' + page); }
            return reponse.text();
        });
        memoire.set(page, promesse);
        promesse.catch(function () { memoire.delete(page); });
        return promesse;
    }

    // La barre montre l'année choisie ; chaque flèche mène à la voisine, ou
    // s'éteint au bout de la liste (un lien sans href n'est pas focalisable).
    function tenirBarre(page) {
        if (!barre) { return; }
        liste.value = String(page);
        [['recente', page - 1, 'Année plus récente'], ['ancienne', page + 1, 'Année plus ancienne']].forEach(function (f) {
            var lien = barre.querySelector('.annees-fleche--' + f[0]);
            if (!lien) { return; }
            if (f[1] >= 1 && f[1] <= total) {
                lien.setAttribute('href', adressePage(f[1]));
                lien.setAttribute('data-page-annee', f[1]);
                lien.setAttribute('data-pt-skip', '1');
                lien.setAttribute('aria-label', f[2] + ' : ' + libelle(f[1]));
                lien.removeAttribute('aria-disabled');
            } else {
                if (lien === document.activeElement) { liste.focus(); }
                lien.removeAttribute('href');
                lien.removeAttribute('data-page-annee');
                lien.setAttribute('aria-label', f[2]);
                lien.setAttribute('aria-disabled', 'true');
            }
        });
    }

    function aller(page, options) {
        var jeton = ++sequence;
        if (page === pageCourante) {
            // Retour à l'année affichée pendant qu'une autre se charge :
            // la demande en cours est abandonnée.
            tenirBarre(page);
            conteneur.removeAttribute('aria-busy');
            if (barre) { barre.removeAttribute('data-chargement'); }
            return;
        }
        var sens = page > pageCourante ? 1 : -1;

        // Réponse visible tout de suite : la barre passe à l'année choisie,
        // l'année affichée s'estompe si l'attente se prolonge (theme.css).
        tenirBarre(page);
        conteneur.setAttribute('aria-busy', 'true');
        if (barre) { barre.setAttribute('data-chargement', ''); }

        obtenir(page).then(function (html) {
            if (jeton !== sequence) { return; }
            conteneur.innerHTML = html;
            conteneur.removeAttribute('aria-busy');
            if (barre) { barre.removeAttribute('data-chargement'); }
            pageCourante = page;
            if (!options.depuisHistorique) {
                history.pushState({ pageAnnee: page }, '', adressePage(page));
            }

            // Le haut de la nouvelle année, barre comprise.
            conteneur.scrollTop = 0;
            var repere = barre || conteneur;
            if (repere.getBoundingClientRect().top < 0) { repere.scrollIntoView({ block: 'start' }); }

            // Plus ancienne : l'année arrive de la droite ; plus récente, de
            // la gauche, comme les flèches.
            conteneur.style.setProperty('--annees-sens', sens);
            conteneur.classList.remove('annees-arrivee');
            void conteneur.offsetWidth;
            conteneur.classList.add('annees-arrivee');

            // Un lien du bas de page a disparu avec l'ancienne année : le
            // focus passe à la liste des années, en haut.
            if (options.depuisContenu && liste) { liste.focus({ preventScroll: true }); }
            var titre = libelle(page);
            annonce.textContent = titre ? 'Année universitaire ' + titre + ' affichée.' : '';
        }).catch(function () {
            if (jeton !== sequence) { return; }
            if (options.depuisHistorique) {
                location.reload();
            } else {
                location.href = adressePage(page);
            }
        });
    }

    function lienAnnee(e) {
        var lien = e.target.closest && e.target.closest('a[data-page-annee]');
        return lien && (conteneur.contains(lien) || (barre && barre.contains(lien))) ? lien : null;
    }

    // Sur l'ancêtre commun de la barre et de l'année, et non sur document :
    // ce gestionnaire passe ainsi avant celui du rideau de transition
    // (src/js/transitions.js), que data-pt-skip écarte aussi.
    var racine = conteneur.parentNode;
    racine.addEventListener('click', function (e) {
        var lien = lienAnnee(e);
        if (!lien || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        e.preventDefault();
        aller(parseInt(lien.getAttribute('data-page-annee'), 10), { depuisContenu: conteneur.contains(lien) });
    });

    if (barre) {
        liste.addEventListener('change', function () {
            aller(parseInt(liste.value, 10), {});
        });
        barre.addEventListener('submit', function (e) {
            e.preventDefault();
            aller(parseInt(liste.value, 10), {});
        });
    }

    // Survol ou focus d'un lien : l'année est demandée d'avance, le clic
    // n'a plus qu'à l'afficher.
    function preparer(e) {
        var lien = lienAnnee(e);
        if (lien) { obtenir(parseInt(lien.getAttribute('data-page-annee'), 10)).catch(function () {}); }
    }
    racine.addEventListener('pointerover', preparer);
    racine.addEventListener('focusin', preparer);

    window.addEventListener('popstate', function (e) {
        var page = e.state && e.state.pageAnnee ? e.state.pageAnnee : lirePage(location.search);
        aller(page, { depuisHistorique: true });
    });
})();
