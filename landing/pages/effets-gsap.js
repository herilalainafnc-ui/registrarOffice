/* =============================================================================
   Effets GSAP de la vitrine
   =============================================================================

   Trois modules independants :
     1. defilement horizontal epingle (GSAP ScrollTrigger)
     2. curseur suivi de formes
     3. rideau de transition entre les pages

   ---------------------------------------------------------------------------
   Pourquoi le defilement natif plutot que Locomotive Scroll

   Locomotive applique une transformation au conteneur de page. Or une
   transformation, meme identite, fait de l'element le bloc conteneur de ses
   descendants position:fixed : ceux-ci cessent d'etre ancres a la fenetre. La
   vitrine en compte plusieurs — la barre de navigation, l'indicateur de
   defilement, le bandeau de statistiques, l'ecran de chargement — qui se
   mettraient tous a defiler avec la page.

   ScrollTrigger fonctionne aussi bien sur le defilement natif et donne le
   meme epinglage horizontal, sans cette contrepartie ni la dependance
   supplementaire. Le seul renoncement est l'inertie du defilement lisse.
   ---------------------------------------------------------------------------

   Tout est conditionne a la presence de GSAP : si le CDN est lent ou
   inaccessible, la page reste utilisable, simplement sans animation.
   ========================================================================== */

(function () {
    'use strict';

    var gsapPresent = (typeof window.gsap !== 'undefined');
    var moinsDAnimation = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var pointeurPrecis = window.matchMedia
        && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* =====================================================================
       1. Defilement horizontal epingle
       ===================================================================== */

    function defilementHorizontal() {
        var section = document.querySelector('.section-horizontale');
        if (!section || !gsapPresent || moinsDAnimation) { return; }
        if (typeof window.ScrollTrigger === 'undefined') { return; }

        var piste = section.querySelector('.section-horizontale__piste');
        if (!piste) { return; }

        gsap.registerPlugin(ScrollTrigger);

        // On bascule en mode anime : la piste cesse de defiler d'elle-meme,
        // c'est ScrollTrigger qui la deplace.
        section.classList.add('est-animee');

        var animation = null;

        function construire() {
            if (animation) { animation.scrollTrigger.kill(); animation.kill(); }

            var distance = piste.scrollWidth - window.innerWidth;
            // Rien a faire si la piste tient deja dans la fenetre : on rend
            // alors la main au defilement natif plutot que d'epingler dans le
            // vide.
            if (distance <= 0) {
                section.classList.remove('est-animee');
                return;
            }
            section.classList.add('est-animee');

            animation = gsap.to(piste, {
                x: -distance,
                ease: 'none',
                scrollTrigger: {
                    trigger: section,
                    start: 'top top',
                    end: function () { return '+=' + distance; },
                    pin: true,
                    scrub: 0.6,
                    invalidateOnRefresh: true,
                    anticipatePin: 1
                }
            });
        }

        construire();

        // La largeur de la piste depend des images : on recalcule quand elles
        // arrivent, sinon la distance d'epinglage serait sous-estimee.
        var images = piste.querySelectorAll('img');
        var restantes = images.length;
        if (restantes) {
            Array.prototype.forEach.call(images, function (img) {
                if (img.complete) { if (--restantes === 0) { ScrollTrigger.refresh(); } return; }
                img.addEventListener('load', function () {
                    if (--restantes === 0) { ScrollTrigger.refresh(); }
                });
                img.addEventListener('error', function () {
                    if (--restantes === 0) { ScrollTrigger.refresh(); }
                });
            });
        }

        /* On ne reconstruit QUE si la largeur a change.
           « resize » ne signale pas seulement un redimensionnement de fenetre :
           il part aussi quand une barre de defilement apparait ou disparait,
           quand la barre d'adresse d'un mobile se retracte, quand le clavier
           virtuel s'ouvre — bref des que la hauteur de la page bouge.
           Or la galerie epinglee est reconstruite en fonction de la LARGEUR
           seule. Rebatir a chaque soubresaut vertical recalculait l'epinglage
           et remappait la position de defilement : la page sautait ailleurs,
           sans rapport avec ce que le visiteur venait de faire. Il suffisait
           d'un clic ou d'une saisie modifiant la hauteur du document. */
        var minuteur = null;
        var largeurConnue = window.innerWidth;

        window.addEventListener('resize', function () {
            if (window.innerWidth === largeurConnue) { return; }
            largeurConnue = window.innerWidth;
            clearTimeout(minuteur);
            minuteur = setTimeout(function () { construire(); ScrollTrigger.refresh(); }, 200);
        });
    }

    /* =====================================================================
       2. Curseur et formes

       Les formes restent dans la section d'accueil : elles l'accompagnent
       sans suivre le visiteur sur toute la page, ce qui serait envahissant
       sur un site institutionnel.
       ===================================================================== */

    function curseurEtFormes() {
        if (!gsapPresent || moinsDAnimation || !pointeurPrecis) { return; }

        var curseur = document.querySelector('.curseur-uaz');
        var zone = document.querySelector('.formes-uaz');
        var formes = zone ? zone.querySelectorAll('.forme-uaz') : [];

        if (curseur) {
            // gsap.quickTo evite de recreer une animation a chaque mouvement.
            var versX = gsap.quickTo(curseur, 'x', { duration: 0.18, ease: 'power3' });
            var versY = gsap.quickTo(curseur, 'y', { duration: 0.18, ease: 'power3' });

            document.addEventListener('mousemove', function (e) {
                versX(e.clientX);
                versY(e.clientY);
            });

            // Le curseur s'elargit au survol des elements cliquables : il
            // signale l'interaction au lieu de se contenter de decorer.
            document.addEventListener('mouseover', function (e) {
                var cible = e.target.closest('a, button, [role="button"], input, select, textarea');
                gsap.to(curseur, {
                    scale: cible ? 2.2 : 1,
                    opacity: cible ? 0.45 : 1,
                    duration: 0.22,
                    ease: 'power3'
                });
            });
        }

        if (zone && formes.length) {
            var suivis = [];
            Array.prototype.forEach.call(formes, function (forme, i) {
                suivis.push({
                    x: gsap.quickTo(forme, 'x', { duration: 0.6 + i * 0.35, ease: 'power3' }),
                    y: gsap.quickTo(forme, 'y', { duration: 0.6 + i * 0.35, ease: 'power3' })
                });
            });

            // Position de depart au centre, pour eviter que les formes ne
            // surgissent du coin superieur gauche au premier mouvement.
            var boite = zone.getBoundingClientRect();
            suivis.forEach(function (s) {
                s.x(boite.width / 2);
                s.y(boite.height / 2);
            });

            zone.parentElement.addEventListener('mousemove', function (e) {
                var r = zone.getBoundingClientRect();
                var x = e.clientX - r.left;
                var y = e.clientY - r.top;
                suivis.forEach(function (s) { s.x(x); s.y(y); });
            });
        }
    }

    /* =====================================================================
       3. Rideau de transition entre les pages

       Le module existant (pages.js) utilise l'API View Transitions quand le
       navigateur la propose, et un simple fondu sinon. On enrichit ce repli
       d'un rideau anime, sans toucher au premier chemin.
       ===================================================================== */

    function rideauDeTransition() {
        if (!gsapPresent || moinsDAnimation) { return; }
        if (document.startViewTransition) { return; } // le navigateur fait mieux

        var rideau = document.querySelector('.rideau-page');
        if (!rideau) { return; }
        var bandes = rideau.querySelectorAll('.rideau-page__bande');
        if (!bandes.length) { return; }

        // Ouverture : le rideau se retire au chargement.
        gsap.set(rideau, { y: 0 });
        gsap.set(bandes, { scaleY: 1, transformOrigin: 'top' });
        gsap.to(bandes, {
            scaleY: 0,
            transformOrigin: 'top',
            duration: 0.55,
            ease: 'power3.inOut',
            stagger: 0.06,
            onComplete: function () { gsap.set(rideau, { y: '100%' }); }
        });

        // Fermeture : au depart vers une autre page de la vitrine.
        document.addEventListener('click', function (e) {
            var lien = e.target.closest('a[href]');
            if (!lien) { return; }
            // Un autre gestionnaire a deja pris le clic en charge : sur la page
            // unique, vitrine.js convertit les liens en ancres et appelle son
            // propre rideau. Sans ce test, les deux rideaux se declenchaient
            // pour un meme clic — celui-ci couvrait l'ecran pour une simple
            // descente vers une rubrique de la meme page.
            if (e.defaultPrevented) { return; }
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
            if (lien.target === '_blank' || lien.hasAttribute('download')) { return; }

            var url;
            try { url = new URL(lien.href, window.location.href); } catch (err) { return; }
            if (url.origin !== window.location.origin) { return; }          // lien externe
            if (url.pathname === window.location.pathname) { return; }      // meme page
            if (url.hash && url.pathname === window.location.pathname) { return; }

            e.preventDefault();
            gsap.set(rideau, { y: 0 });
            gsap.fromTo(bandes,
                { scaleY: 0, transformOrigin: 'bottom' },
                {
                    scaleY: 1,
                    transformOrigin: 'bottom',
                    duration: 0.45,
                    ease: 'power3.inOut',
                    stagger: 0.05,
                    onComplete: function () { window.location.href = url.href; }
                }
            );

            // Filet : si l'animation n'aboutit pas, on navigue quand meme.
            setTimeout(function () {
                if (window.location.href !== url.href) { window.location.href = url.href; }
            }, 1200);
        });
    }

    /* ===================================================================== */
    /* =====================================================================
       Images introuvables

       Une bonne part des illustrations est hebergee hors du site, et
       plusieurs ne repondent plus. Le navigateur affichait alors son icone de
       lien casse et le texte de remplacement au milieu de la carte.

       On ne masque pas l’image : sa place disparaitrait et la galerie se
       retrouverait trouee. On lui substitue un visuel de repli — un aplat
       marine pale au sigle de l’universite, dessine ici meme en SVG, donc
       sans requete supplementaire ni fichier a deployer. L’image se charge
       normalement et occupe sa boite comme les autres.

       Cette fonction vit ici parce que ce fichier est le seul charge a la
       fois par la page unique et par les pages autonomes.

       « error » ne remonte pas : on l’ecoute en phase de capture. Les images
       deja en echec ne le declencheront plus, d’ou le balayage.
       ===================================================================== */

    function imagesIntrouvables() {
        var dessin =
            '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400">' +
            '<rect width="640" height="400" fill="#eef2f9"/>' +
            '<text x="320" y="200" text-anchor="middle" dominant-baseline="central" ' +
            'font-family="system-ui,Segoe UI,Helvetica,Arial,sans-serif" font-size="58" ' +
            'font-weight="900" letter-spacing="6" fill="#183060" fill-opacity=".11">UAZ</text>' +
            '</svg>';
        var repli = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(dessin);

        var remplacer = function (img) {
            // Le drapeau evite de reboucler si le repli lui-meme echouait.
            if (img.dataset.repli) { return; }
            img.dataset.repli = '1';
            img.classList.add('image-absente');
            img.src = repli;
        };

        document.addEventListener('error', function (e) {
            var el = e.target;
            if (el && el.tagName === 'IMG') { remplacer(el); }
        }, true);

        var balayer = function () {
            Array.prototype.forEach.call(document.images, function (img) {
                if (img.complete && img.naturalWidth === 0) { remplacer(img); }
            });
        };
        balayer();
        window.addEventListener('load', balayer);
    }

    function demarrer() {
        try { defilementHorizontal(); } catch (e) { console.warn('[effets] defilement horizontal :', e); }
        try { curseurEtFormes(); }      catch (e) { console.warn('[effets] curseur :', e); }
        try { imagesIntrouvables(); }  catch (e) { console.warn('[effets] images :', e); }
        try { rideauDeTransition(); }   catch (e) { console.warn('[effets] rideau :', e); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})();
