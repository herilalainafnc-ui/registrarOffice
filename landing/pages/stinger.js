/* =============================================================================
   Stinger de transition (canvas)
   =============================================================================
   Deux moities balayent l'ecran depuis les bords, chacune en trois couches
   decalees dans le temps — fond, accent, avant-plan — avec une acceleration
   quintique. Le sigle apparait au centre de chaque moitie pendant la fermeture.

   Adapte du principe des transitions de diffusion sportive : le decalage entre
   les couches donne l'impression d'epaisseur, et l'easing quintique produit
   l'arrivee seche qui fait l'effet.

   Le trace est en canvas plutot qu'en DOM : trois rectangles animes par cote a
   soixante images par seconde, c'est le bon outil, et cela n'ajoute aucun
   element au document.

   API :
     stingerUAZ.couvrir()          -> promesse resolue quand l'ecran est couvert
     stingerUAZ.decouvrir()        -> retire le rideau
     stingerUAZ.traverser(action)  -> couvre, execute l'action, decouvre
   ========================================================================== */

/* Le « || » garde le module d'un second montage. La page unique charge ce
   fichier tres tot, pour que le rideau soit trace avant le premier
   affichage, et le pied de page partage le charge a nouveau pour les pages
   autonomes : sans cette garde, les ecouteurs de clic seraient poses deux
   fois et chaque lien declencherait deux traversees. */
window.stingerUAZ = window.stingerUAZ || (function () {
    'use strict';

    /* Palette tiree du logo de l universite : bleu marine et or. Les deux
       moities se distinguent par leur profondeur, pas par des teintes
       etrangeres a l identite. */
    var COULEURS = {
        a: { fond: '#2a4a86', accent: '#907848', avant: '#183060' },
        b: { fond: '#1f3a6e', accent: '#b89a5e', avant: '#12244a' },
        texte: '#ffffff',
        accentTexte: '#c9a961'
    };

    var DUREE_FERMETURE = 620;   // ms
    var DUREE_OUVERTURE = 520;

    var toile = null;
    var ctx = null;
    var enCours = false;

    function moinsDAnimation() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /* Acceleration quintique sortante : depart vif, arrivee amortie. */
    function quintOut(t) {
        return 1 - Math.pow(1 - t, 5);
    }

    /* Ramene une valeur dans [0,1] sur une fenetre temporelle decalee. */
    function fenetre(t, debut, largeur) {
        return Math.min(1, Math.max(0, (t - debut) / largeur));
    }

    /* Le stinger pose lui-meme son style, plutot que de dependre d'une
       feuille exterieure. Il sert desormais aussi la page de connexion, qui
       n'inclut pas vitrine.css : reposer sur elle aurait donne un canvas
       sans position ni empilement, donc un rectangle blanc en plein milieu
       du document.

       Le z-index passe au-dessus de l'ecran de chargement de la page unique
       (9800), sans quoi le rideau se serait leve derriere lui, invisible. */
    function poserLeStyle() {
        if (document.getElementById('style-stinger-uaz')) { return; }
        var style = document.createElement('style');
        style.id = 'style-stinger-uaz';
        style.textContent =
            '.stinger-uaz{position:fixed;inset:0;z-index:9900;pointer-events:none;' +
            'opacity:0;visibility:hidden}' +
            '.stinger-uaz.est-visible{opacity:1;visibility:visible}' +
            '@media (prefers-reduced-motion: reduce){.stinger-uaz{display:none}}';
        document.head.appendChild(style);
    }

    function preparerToile() {
        if (toile) { return; }
        poserLeStyle();
        toile = document.createElement('canvas');
        toile.className = 'stinger-uaz';
        toile.setAttribute('aria-hidden', 'true');
        document.body.appendChild(toile);
        ctx = toile.getContext('2d');
        dimensionner();
        window.addEventListener('resize', dimensionner);
    }

    function dimensionner() {
        if (!toile) { return; }
        // On tient compte de la densite de pixels, sinon le trace est flou sur
        // les ecrans a haute resolution.
        var d = window.devicePixelRatio || 1;
        toile.width = Math.round(window.innerWidth * d);
        toile.height = Math.round(window.innerHeight * d);
        toile.style.width = window.innerWidth + 'px';
        toile.style.height = window.innerHeight + 'px';
        ctx.setTransform(d, 0, 0, d, 0, 0);
    }

    /**
     * Trace une image de l'animation.
     * @param {number} t progression globale, de 0 (ecran libre) a 1 (couvert)
     */
    function tracer(t) {
        var L = window.innerWidth;
        var H = window.innerHeight;
        var m = L / 2;

        ctx.clearRect(0, 0, L, H);

        // Trois couches par cote, decalees : le fond part le premier, l'accent
        // suit, l'avant-plan ferme la marche. C'est ce decalage qui donne
        // l'epaisseur.
        var tFond   = quintOut(fenetre(t, 0.00, 0.72));
        var tAccent = quintOut(fenetre(t, 0.14, 0.72));
        var tAvant  = quintOut(fenetre(t, 0.28, 0.72));

        function bande(progression, couleurA, couleurB) {
            var l = m * progression;
            if (l <= 0) { return; }
            ctx.fillStyle = couleurA;
            ctx.fillRect(0, 0, l, H);
            ctx.fillStyle = couleurB;
            ctx.fillRect(L - l, 0, l, H);
        }

        bande(tFond,   COULEURS.a.fond,   COULEURS.b.fond);
        bande(tAccent, COULEURS.a.accent, COULEURS.b.accent);
        bande(tAvant,  COULEURS.a.avant,  COULEURS.b.avant);

        // Liseres d'accent sur le front de l'avant-plan : ils soulignent le
        // mouvement et evitent une jonction plate au centre.
        var e = Math.max(2, L * 0.006);
        var xa = m * tAvant;
        if (xa > 0 && tAvant < 1) {
            ctx.fillStyle = COULEURS.a.accent;
            ctx.fillRect(xa - e, 0, e, H);
            ctx.fillStyle = COULEURS.b.accent;
            ctx.fillRect(L - xa, 0, e, H);
        }

        // Sigle : UAZ au centre de l ecran, en tres gras. Il n apparait qu une
        // fois l avant-plan suffisamment avance pour lui servir de fond.
        var tSigle = quintOut(fenetre(t, 0.45, 0.5));
        if (tSigle > 0) {
            dessinerSigle(L / 2, H / 2, Math.min(L, H), tSigle);
        }
    }

    /* « UAZ » centre, en graisse maximale. Le texte grandit legerement en
       apparaissant : l arrivee se sent au lieu d etre un simple fondu. */
    function dessinerSigle(cx, cy, reference, avancement) {
        var taille = reference * 0.19;
        var echelle = 0.86 + 0.14 * avancement;

        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(echelle, echelle);
        ctx.globalAlpha = avancement;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        // 900 plutot que bold : les polices systeme montent jusque-la, et
        // c est cette graisse qui donne au sigle sa presence.
        ctx.font = '900 ' + Math.round(taille) + 'px system-ui, -apple-system, '
                 + '"Segoe UI", Helvetica, Arial, sans-serif';
        ctx.letterSpacing = Math.round(taille * 0.04) + 'px';   // ignore si non gere

        ctx.fillStyle = COULEURS.texte;
        ctx.fillText('UAZ', 0, 0);

        // Filet dore sous le sigle, repris du logo de l universite.
        var l = ctx.measureText('UAZ').width;
        ctx.fillStyle = COULEURS.accentTexte;
        ctx.fillRect(-l / 2, taille * 0.62, l * avancement, Math.max(3, taille * 0.055));
        ctx.restore();
    }

    function animer(de, vers, duree) {
        return new Promise(function (resoudre) {
            preparerToile();
            toile.classList.add('est-visible');
            var depart = null;

            function image(horodatage) {
                if (depart === null) { depart = horodatage; }
                var p = Math.min(1, (horodatage - depart) / duree);
                tracer(de + (vers - de) * p);
                if (p < 1) {
                    requestAnimationFrame(image);
                } else {
                    resoudre();
                }
            }
            requestAnimationFrame(image);
        });
    }

    function couvrir() {
        if (moinsDAnimation()) { return Promise.resolve(); }
        enCours = true;
        return animer(0, 1, DUREE_FERMETURE);
    }

    function decouvrir() {
        if (moinsDAnimation()) { return Promise.resolve(); }
        // On repart de l'ecran couvert vers l'ecran libre : les couches se
        // retirent dans le meme ordre, ce qui garde la lecture coherente.
        return animer(1, 0, DUREE_OUVERTURE).then(function () {
            if (toile) { toile.classList.remove('est-visible'); }
            enCours = false;
        });
    }

    /**
     * Couvre l'ecran, execute l'action pendant qu'il est masque, puis decouvre.
     * L'action ne voit jamais l'ecran a moitie couvert.
     */
    function traverser(action) {
        if (moinsDAnimation()) {
            if (typeof action === 'function') { action(); }
            return Promise.resolve();
        }
        if (enCours) { return Promise.resolve(); }   // pas deux a la fois

        /* L'action ne doit s'executer qu'une fois, et seulement si elle a
           encore un sens.

           couvrir() avance par requestAnimationFrame, que le navigateur
           suspend des que l'onglet passe a l'arriere-plan ou qu'il decide de
           brider la page. La promesse se resolvait alors longtemps apres le
           clic — et le defilement partait au moment ou le visiteur, croyant
           que rien ne s'etait passe, avait deja navigue ailleurs a la main.
           La page sautait sans raison apparente.

           Deux garde-fous : une minuterie qui prend le relais si l'animation
           s'endort, et l'abandon pur et simple si le visiteur a repris la
           main entre-temps. Quand il a defile lui-meme, c'est lui qui decide
           ou il se trouve. */
        var faite = false;
        var departY = window.pageYOffset || document.documentElement.scrollTop || 0;

        function executer() {
            if (faite) { return; }
            faite = true;
            var maintenant = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (Math.abs(maintenant - departY) > 120) { return; }  // il a repris la main
            if (typeof action === 'function') { action(); }
        }

        var relais = setTimeout(executer, DUREE_FERMETURE + 250);

        return couvrir()
            .then(function () {
                clearTimeout(relais);
                executer();
                // Laisser une image au navigateur pour appliquer le changement
                // avant de rouvrir, sinon on decouvre l'ancien etat.
                return new Promise(function (r) { setTimeout(r, 90); });
            })
            .then(decouvrir);
    }

    /* =====================================================================
       Traversee d'une page a l'autre

       Le rideau se ferme sur la page qu'on quitte, et se leve sur celle qui
       arrive : le chargement se fait pendant que l'ecran est couvert, ce qui
       remplace le blanc du navigateur par une transition tenue.

       Les deux moities de l'effet vivent dans deux documents differents et
       ne peuvent pas se parler. Un temoin depose dans sessionStorage fait le
       lien : la page de depart le pose avant de naviguer, celle d'arrivee le
       lit puis l'efface. sessionStorage plutot que localStorage — le temoin
       ne doit valoir que pour cet onglet, et disparaitre avec lui.

       Les liens concernes portent data-stinger. Un attribut plutot qu'une
       liste d'adresses ecrite ici : c'est le balisage qui sait quels
       passages meritent l'effet, et la regle reste lisible sur place.
       ===================================================================== */

    var TEMOIN = 'stinger-uaz-traversee';

    function relierLesLiens() {
        document.addEventListener('click', function (e) {
            var lien = e.target.closest ? e.target.closest('a[data-stinger]') : null;
            if (!lien) { return; }

            // On laisse passer tout ce qui n'est pas une navigation simple :
            // nouvel onglet, telechargement, clic du milieu, modificateurs.
            if (e.defaultPrevented || e.button !== 0) { return; }
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
            if (lien.target && lien.target !== '_self') { return; }
            if (lien.hasAttribute('download')) { return; }

            var url;
            try { url = new URL(lien.href, window.location.href); } catch (err) { return; }
            if (url.origin !== window.location.origin) { return; }

            if (moinsDAnimation()) { return; }   // navigation ordinaire

            e.preventDefault();
            try { sessionStorage.setItem(TEMOIN, '1'); } catch (err) {}

            // La navigation ne doit pas dependre de la fin de l'animation.
            // Le trace avance par requestAnimationFrame, que le navigateur
            // suspend des que l'onglet passe a l'arriere-plan : un visiteur
            // qui clique puis change d'onglet ne verrait jamais la promesse
            // se resoudre, et resterait sur un ecran couvert qui ne mene
            // nulle part. Le delai de secours part quoi qu'il arrive.
            var parti = false;
            function partir() {
                if (parti) { return; }
                parti = true;
                window.location.href = url.href;
            }
            couvrir().then(partir);
            setTimeout(partir, DUREE_FERMETURE + 250);
        });
    }

    function leverLeRideauALArrivee() {
        var attendu;
        try { attendu = sessionStorage.getItem(TEMOIN); } catch (err) { return; }
        if (!attendu) { return; }
        try { sessionStorage.removeItem(TEMOIN); } catch (err) {}
        if (moinsDAnimation()) { return; }

        // On couvre avant le premier affichage : sans ce trace immediat, la
        // page apparaitrait nue une fraction de seconde avant d'etre
        // recouverte, ce qui donnerait un clignotement au lieu d'une
        // transition.
        preparerToile();
        toile.classList.add('est-visible');
        tracer(1);

        // La page unique a son propre ecran de chargement, au-dessus duquel
        // le rideau passe maintenant. Le laisser aurait empile deux
        // transitions : on le considere comme deja termine.
        var chargement = document.getElementById('chargement');
        if (chargement) { chargement.classList.add('est-fini'); }

        requestAnimationFrame(function () {
            requestAnimationFrame(decouvrir);
        });
    }

    function demarrer() {
        leverLeRideauALArrivee();
        relierLesLiens();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }

    // Retour par le bouton « precedent » : la page peut etre restauree telle
    // qu'on l'a quittee, rideau ferme compris. On le retire.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted && toile) {
            toile.classList.remove('est-visible');
            enCours = false;
        }
    });

    return {
        couvrir: couvrir,
        decouvrir: decouvrir,
        traverser: traverser,
        estActif: function () { return enCours; }
    };
})();
