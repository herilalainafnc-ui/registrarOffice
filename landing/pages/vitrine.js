/* =============================================================================
   Vitrine en page unique
   =============================================================================
   Quatre comportements :
     1. ecran de chargement suivant l'arrivee des images ;
     2. barre de progression de lecture ;
     3. navigation par ancres, avec la rubrique courante mise en avant ;
     4. apparition des sections au defilement.

   Les effets GSAP eux-memes (defilement horizontal, curseur, rideau) vivent
   dans effets-gsap.js, qui s'applique aussi bien ici que sur les pages
   autonomes.
   ========================================================================== */

(function () {
    'use strict';

    var gsapPresent = (typeof window.gsap !== 'undefined');
    var moinsDAnimation = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* =====================================================================
       1. Ecran de chargement

       Il suit l'arrivee des images, mais s'efface de toute facon au bout de
       quelques secondes : sur une connexion lente, un visiteur ne doit jamais
       rester devant un ecran noir parce qu'une image tarde — d'autant que
       certaines viennent d'un autre domaine.
       ===================================================================== */

    function ecranDeChargement() {
        var ecran = document.getElementById('chargement');
        if (!ecran) { return; }
        var texte = document.getElementById('chargement-texte');
        var images = Array.prototype.slice.call(document.images);
        var total = images.length;
        var arrivees = 0;
        var fini = false;

        function afficher() {
            if (!texte) { return; }
            var part = total ? Math.round(arrivees * 100 / total) : 100;
            texte.textContent = part + ' %';
        }

        function terminer() {
            if (fini) { return; }
            fini = true;
            if (texte) { texte.textContent = '100 %'; }
            ecran.classList.add('est-fini');
            // ScrollTrigger a mesure la page pendant que des images
            // manquaient encore : on lui demande de refaire ses calculs.
            if (typeof window.ScrollTrigger !== 'undefined') {
                setTimeout(function () { ScrollTrigger.refresh(); }, 600);
            }
        }

        if (!total) { terminer(); return; }

        images.forEach(function (img) {
            if (img.complete) {
                arrivees++;
                return;
            }
            var compter = function () {
                arrivees++;
                afficher();
                if (arrivees >= total) { terminer(); }
            };
            img.addEventListener('load', compter, { once: true });
            // Une image absente ne doit pas bloquer : elle compte quand meme.
            img.addEventListener('error', compter, { once: true });
        });

        afficher();
        if (arrivees >= total) { terminer(); }

        // Filet de securite, quoi qu'il arrive.
        setTimeout(terminer, 4000);
        window.addEventListener('load', function () { setTimeout(terminer, 300); });
    }

    /* =====================================================================
       2. Barre de progression de lecture
       ===================================================================== */

    function barreDeProgression() {
        var barre = document.getElementById('vitrine-barre');
        if (!barre) { return; }

        function mettreAJour() {
            var hauteur = document.documentElement.scrollHeight - window.innerHeight;
            var part = hauteur > 0 ? (window.scrollY / hauteur) : 0;
            barre.style.width = Math.min(100, Math.max(0, part * 100)) + '%';
        }
        window.addEventListener('scroll', mettreAJour, { passive: true });
        window.addEventListener('resize', mettreAJour);
        mettreAJour();
    }

    /* =====================================================================
       3. Navigation par ancres

       Les liens de la barre pointent vers les pages autonomes : sur la page
       unique, on les redirige vers les sections correspondantes. Les adresses
       d'origine restent valides pour qui arrive de l'exterieur.
       ===================================================================== */

    function navigationParAncres() {
        var correspondance = {
            '/': 'accueil',
            '/formations': 'formations',
            '/admissions': 'admissions',
            '/campus': 'campus',
            '/contact': 'contact'
        };

        // Tous les liens internes, pas seulement ceux de la barre : le corps
        // des rubriques en contient une douzaine (« Voir les formations »,
        // « Nous contacter », les renvois de la FAQ...) qui faisaient sortir
        // de la page unique.
        var liens = document.querySelectorAll('a[href]');
        var parSection = {};

        Array.prototype.forEach.call(liens, function (lien) {
            var brut = lien.getAttribute('href') || '';
            var cible;

            if (brut.charAt(0) === '#') {
                // Deja une ancre : on ne touche pas a son adresse — son
                // pathname est celui de la page courante, et la convertir
                // renverrait « #pre-inscription » vers « #accueil ». Elle
                // recoit en revanche le meme traitement au clic que les
                // autres : laissee au navigateur, la navigation par ancre
                // n’a pas defile ici, le defilement epingle des galeries
                // remaniant la page pendant qu’elle s’opere.
                cible = brut.slice(1);
                if (!cible) { return; }
            } else {
                var url;
                try { url = new URL(lien.href, window.location.href); } catch (e) { return; }
                cible = correspondance[url.pathname.replace(/\/+$/, '') || '/'];
                if (!cible) { return; }

                lien.setAttribute('href', '#' + cible);

                // Seuls les liens de la barre servent de repere pour la
                // rubrique courante. Depuis que l’on convertit aussi ceux du
                // corps et du pied de page, c’etait le dernier lien rencontre
                // qui heritait du soulignement — souvent celui du pied de
                // page, hors de l’ecran : le trait ne s’est jamais affiche.
                if (lien.closest && lien.closest('.navbar')) {
                    parSection[cible] = lien;
                }
            }

            lien.addEventListener('click', function (e) {
                var section = document.getElementById(cible);
                if (!section) { return; }   // repli : navigation normale
                e.preventDefault();

                // Le stinger masque l ecran pendant le saut : on evite le
                // defilement eclair a travers plusieurs milliers de pixels,
                // et la coupure devient un effet plutot qu une secousse.
                var aller = function () {
                    // Le saut doit etre instantane : la feuille de style
                    // impose un defilement lisse, qui continuerait apres la
                    // levee du rideau et laisserait voir la fin de la course.
                    var racine = document.documentElement;
                    var ancien = racine.style.scrollBehavior;
                    racine.style.scrollBehavior = 'auto';
                    section.scrollIntoView({ behavior: 'auto', block: 'start' });
                    racine.style.scrollBehavior = ancien;
                    if (window.history && history.replaceState) {
                        history.replaceState(null, '', '#' + cible);
                    }
                };

                /* data-sans-rideau : le lien demande un simple defilement.

                   Le rideau se justifie pour un saut de plusieurs milliers de
                   pixels — il remplace une secousse par un effet. Mais la
                   fleche du hero mene a la rubrique juste en dessous : couvrir
                   l'ecran pour descendre d'un ecran nie le geste qu'elle
                   annonce, qui est precisement de faire defiler. */
                var sansRideau = lien.hasAttribute('data-sans-rideau');

                if (window.stingerUAZ && !moinsDAnimation && !sansRideau) {
                    window.stingerUAZ.traverser(aller);
                } else {
                    section.scrollIntoView({
                        behavior: moinsDAnimation ? 'auto' : 'smooth',
                        block: 'start'
                    });
                    if (window.history && history.replaceState) {
                        history.replaceState(null, '', '#' + cible);
                    }
                }
            });
        });

        // Mise en avant de la rubrique en cours de lecture.
        var sections = document.querySelectorAll('.vitrine-section[data-rubrique]');
        if (!sections.length || !('IntersectionObserver' in window)) { return; }

        var observateur = new IntersectionObserver(function (entrees) {
            entrees.forEach(function (entree) {
                if (!entree.isIntersecting) { return; }
                var nom = entree.target.getAttribute('data-rubrique');
                Array.prototype.forEach.call(
                    document.querySelectorAll('.navbar .nav-link'),
                    function (l) { l.classList.remove('active'); });
                if (parSection[nom]) { parSection[nom].classList.add('active'); }
            });
        }, { rootMargin: '-45% 0px -50% 0px' });

        Array.prototype.forEach.call(sections, function (s) { observateur.observe(s); });
    }
    /* =====================================================================
       Titre du hero : frappe au clavier

       Le titre se compose lettre a lettre, se tient un moment, s efface, et
       laisse la formule suivante. Dans chaque devise, un mot porte le sens :
       il est marque a part et recoit une police et une couleur distinctes.

       Le nom de l universite n a pas de mot en avant — c est un nom propre,
       pas une devise — et il reste plus longtemps a l ecran que les autres.
       ===================================================================== */

    /* Chaque formule est une suite de fragments ; le fragment marque « e »
       est celui qu on met en avant. */
    var FORMULES = [
        { fragments: [{ t: 'Université Adventiste Zurcher' }], tenue: 5200 },
        { fragments: [{ t: 'Votre ' }, { t: 'futur', e: true }, { t: ' commence ici' }], tenue: 3200 },
        { fragments: [{ t: 'Your ' }, { t: 'future', e: true }, { t: ' starts here' }], tenue: 3200 },
        { fragments: [{ t: 'Manomboka eto ny ' }, { t: 'hoavinao', e: true }], tenue: 3200 },
        { fragments: [{ t: 'Préparer aujourd’hui les ' }, { t: 'leaders', e: true }, { t: ' de demain' }], tenue: 3600 }
    ];

    var FRAPPE     = 55;    // ms par lettre a l ecriture
    var EFFACEMENT = 26;    // ms par lettre a l effacement — plus vif, sinon on attend
    var RESPIRE    = 450;   // ms entre l effacement et la formule suivante

    function titreFrappe() {
        var titre   = document.getElementById('vitrine-titre');
        var zone    = document.getElementById('titre-texte');
        var curseur = document.getElementById('titre-curseur');
        if (!titre || !zone) { return; }

        // Qui demande moins d animation garde le nom, ecrit une fois.
        if (moinsDAnimation) { return; }

        // Chaque lettre porte son fragment d origine : c est ce qui permet de
        // rendre le mot en avant dans sa propre police au fil de la frappe.
        function lettres(formule) {
            var suite = [];
            formule.fragments.forEach(function (frag) {
                Array.prototype.forEach.call(frag.t, function (c) {
                    suite.push({ c: c, e: !!frag.e });
                });
            });
            return suite;
        }

        function afficher(suite, combien) {
            zone.innerHTML = '';
            var courant = null;
            var emphase = null;
            for (var i = 0; i < combien; i++) {
                if (courant === null || suite[i].e !== emphase) {
                    courant = document.createElement('span');
                    if (suite[i].e) { courant.className = 'vitrine-titre__emphase'; }
                    emphase = suite[i].e;
                    zone.appendChild(courant);
                }
                courant.textContent += suite[i].c;
            }

            // Le curseur se range dans le meme mot que la derniere lettre.
            // Laisse a la suite du texte, il partait seul a la ligne des que
            // la precedente etait pleine.
            if (curseur) { (courant || zone).appendChild(curseur); }
        }

        // Le corps du texte suit la longueur de la formule. Il change pendant
        // que l ecran est vide : autrement le texte encore affiche sauterait.
        function ajusterCorps(formule) {
            var n = formule.fragments.reduce(function (t, f) { return t + f.t.length; }, 0);
            titre.classList.toggle('est-longue', n > 32);
        }

        var index = 0;

        function ecrire() {
            var formule = FORMULES[index];
            var suite = lettres(formule);
            var i = 0;
            ajusterCorps(formule);

            (function pas() {
                afficher(suite, ++i);
                if (i < suite.length) { setTimeout(pas, FRAPPE); }
                else { setTimeout(effacer, formule.tenue); }
            })();

            function effacer() {
                (function pas() {
                    afficher(suite, --i);
                    if (i > 0) { setTimeout(pas, EFFACEMENT); }
                    else {
                        index = (index + 1) % FORMULES.length;
                        setTimeout(ecrire, RESPIRE);
                    }
                })();
            }
        }

        // Le nom est deja ecrit dans le balisage : on le laisse le temps
        // convenu, puis la rotation prend le relais.
        setTimeout(function () {
            zone.textContent = '';
            index = 0;
            ecrire();
        }, FORMULES[0].tenue);
    }

    /* =====================================================================
       Plans du hero : chargement conditionnel et enchainement

       Deux vues se succedent : une descente du ciel vers le plateau, puis un
       survol de la route. La premiere pese 1,1 Mo en 1600 px, la seconde
       8,7 Mo — meme definition, memes reglages. L’ecart tient au plan :
       un mouvement lent sur un ciel se comprime bien, un survol rapide
       au-dessus d’une foret non. C’est pourquoi la legere passe en premier.

       On n’impose rien : la source n’est posee que si l’ecran la justifie et
       si la connexion la supporte ; sinon l’affiche suffit, et elle ne coute
       que 283 Ko.
       ===================================================================== */

    function fondVideo() {
        var plans = document.querySelectorAll('.vitrine-fond__video');
        var bouton = document.getElementById('vitrine-pause');
        if (!plans.length || !plans[0].dataset.large) { return; }

        // Une image qui bouge ne s’impose pas a qui demande le calme.
        if (moinsDAnimation) { return; }

        var lien = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        var type = lien && lien.effectiveType || '';

        // « Economiseur de donnees » actif : la demande est explicite.
        if (lien && lien.saveData) { return; }

        // 2G : plusieurs megaoctets y prendraient des minutes. On renonce.
        if (/(^|-)2g$/.test(type)) { return; }

        // Trois tailles, selon ce que l’ecran demande et ce que la connexion
        // peut porter. Le seuil ne rejette pas la 3G : elle est courante a
        // Madagascar, et lui refuser la video reviendrait a la refuser a une
        // bonne part des visiteurs. Elle recoit la taille intermediaire.
        var champ;
        if (window.innerWidth < 900)   { champ = 'small';  }
        else if (type === '3g')        { champ = 'medium'; }
        else                           { champ = 'large';  }

        var courant = 0;
        var enPause = false;

        function poserSource(video) {
            if (video.dataset.posee) { return; }
            video.dataset.posee = '1';
            var source = document.createElement('source');
            source.type = 'video/mp4';
            source.src = video.dataset[champ];
            video.appendChild(source);
            video.load();
        }

        function lire(video) {
            // Certains navigateurs refusent la lecture automatique malgre
            // « muted » ; l’echec est sans consequence, l’affiche reste.
            var essai = video.play();
            if (essai && essai.catch) { essai.catch(function () {}); }
        }

        function basculer() {
            var sortant = plans[courant];
            courant = (courant + 1) % plans.length;
            var entrant = plans[courant];

            poserSource(entrant);
            entrant.currentTime = 0;
            if (!enPause) { lire(entrant); }

            entrant.classList.add('est-visible');
            sortant.classList.remove('est-visible');
            // On laisse le fondu se terminer avant d’arreter le plan sortant,
            // sinon on voit sa derniere image se figer pendant la transition.
            setTimeout(function () { sortant.pause(); }, 1000);
        }

        Array.prototype.forEach.call(plans, function (video) {
            video.addEventListener('ended', function () {
                if (video === plans[courant]) { basculer(); }
            });
            // Le plan suivant se telecharge pendant que le precedent se joue,
            // pour qu’il soit pret au moment de la bascule.
            //
            // Le declencheur est un delai, non currentTime : au rechargement
            // le navigateur restaure la position de lecture precedente, si
            // bien qu’un seuil sur currentTime etait franchi aussitot et que
            // les deux videos se telechargeaient en concurrence.
            video.addEventListener('playing', function () {
                if (video !== plans[courant] || video.dataset.suivantDemande) { return; }
                video.dataset.suivantDemande = '1';
                setTimeout(function () {
                    poserSource(plans[(courant + 1) % plans.length]);
                }, 3000);
            });
        });

        poserSource(plans[0]);
        lire(plans[0]);

        /* ------------------------------------------------------------------
           Bouton de pause

           Il ne parait qu’une fois une video reellement chargee : sur une
           connexion menagee, il n’y aurait rien a arreter.
           ------------------------------------------------------------------ */
        if (!bouton) { return; }
        bouton.hidden = false;

        bouton.addEventListener('click', function () {
            enPause = !enPause;
            if (enPause) {
                plans[courant].pause();
                bouton.classList.add('est-en-pause');
                bouton.setAttribute('aria-label', 'Reprendre la vidéo d’arrière-plan');
            } else {
                lire(plans[courant]);
                bouton.classList.remove('est-en-pause');
                bouton.setAttribute('aria-label', 'Mettre la vidéo d’arrière-plan en pause');
            }
        });
    }

    /* =====================================================================
       Parallaxe de la video du hero

       La video se deplace moins vite que la page : le fond parait plus
       loin. Le deplacement reste faible, sinon on decouvre le bord de
       l element — c est pourquoi la video est dimensionnee a 120 %% de
       hauteur dans la feuille de style.
       ===================================================================== */

    function parallaxeVideo() {
        if (!gsapPresent || moinsDAnimation) { return; }
        if (typeof window.ScrollTrigger === 'undefined') { return; }
        var plans = document.querySelector('.vitrine-fond__plans');
        var section = document.querySelector('.vitrine-accueil');
        if (!plans || !section) { return; }
        gsap.registerPlugin(ScrollTrigger);

        gsap.to(plans, {
            yPercent: 6,           // la video descend pendant que la page monte
            ease: 'none',
            scrollTrigger: {
                trigger: section,
                start: 'top top',
                end: 'bottom top',
                scrub: true
            }
        });
    }
    /* =====================================================================
       Silhouettes de fond

       Chaque silhouette monte ou descend a sa propre vitesse pendant que sa
       section traverse l ecran, alternativement dans un sens et dans l autre.
       C est l ecart entre ces vitesses qui donne la profondeur : toutes au
       meme rythme, on ne verrait qu un decor qui glisse avec la page.

       Le disque ne bouge pas. Il reste ou il est, et le decalage se lit.
       ===================================================================== */

    function parallaxeFigures() {
        if (!gsapPresent) { return; }
        if (typeof window.ScrollTrigger === 'undefined') { return; }

        // Seule animation de la page qui ignore « prefers-reduced-motion ».
        // C'est un choix demande, et il se paie : la parallaxe est le
        // declencheur habituel des troubles vestibulaires chez ceux qui
        // activent ce reglage pour s'en proteger. Pour l'adoucir plutot que
        // la couper, diviser « vitesse » ci-dessous lorsque moinsDAnimation
        // vaut vrai.

        var figures = document.querySelectorAll('.figure-cote');
        if (!figures.length) { return; }
        gsap.registerPlugin(ScrollTrigger);

        Array.prototype.forEach.call(figures, function (figure) {
            var vitesse = parseFloat(figure.getAttribute('data-vitesse')) || 0;
            var image = figure.querySelector('img');
            var section = figure.parentElement;
            if (!image || !vitesse || !section) { return; }

            gsap.fromTo(image,
                { y: vitesse },
                {
                    y: -vitesse,
                    ease: 'none',
                    immediateRender: true,
                    scrollTrigger: {
                        trigger: section,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: 0.9
                    }
                });
        });
    }

    /* =====================================================================
       Galeries horizontales, une sur deux en sens inverse

       Le sens alterne pour que la lecture rebondisse d un bord a l autre au
       lieu de filer toujours du meme cote. La piste n est pas epinglee ici :
       elle glisse pendant que la section traverse l ecran, ce qui laisse le
       defilement vertical continu — on ne bloque le visiteur qu une fois,
       sur la section du campus.
       ===================================================================== */

    function galeriesAlternees() {
        if (!gsapPresent || moinsDAnimation) { return; }
        if (typeof window.ScrollTrigger === 'undefined') { return; }
        gsap.registerPlugin(ScrollTrigger);

        var galeries = document.querySelectorAll('.galerie-horizontale');
        Array.prototype.forEach.call(galeries, function (galerie, index) {
            var piste = galerie.querySelector('.galerie-horizontale__piste');
            if (!piste) { return; }

            var debord = piste.scrollWidth - galerie.offsetWidth;
            if (debord <= 0) { return; }   // rien a faire si tout tient

            galerie.classList.add('est-animee');

            // Le sens alterne par defaut, mais une galerie peut l imposer :
            // une suite numerotee doit se lire dans l ordre, sinon on parcourt
            // « etape 5, 4, 3... », ce que la numerotation dement.
            var impose = galerie.getAttribute('data-sens');
            var versLaGauche = impose
                ? (impose !== 'droite')
                : (index % 2 === 0);
            var depart = versLaGauche ? 0 : -debord;
            var arrivee = versLaGauche ? -debord : 0;

            // immediateRender pose la position de depart des la creation.
            // Sans invalidateOnRefresh : celui-ci reenregistrerait le point
            // de depart depuis la position courante lors d un rafraichissement,
            // ce qui annulait le sens inverse de la galerie de droite.
            gsap.fromTo(piste, { x: depart }, {
                x: arrivee,
                ease: 'none',
                immediateRender: true,
                scrollTrigger: {
                    trigger: galerie,
                    // Bornes resserrees : avec « top bottom / bottom top »,
                    // le mouvement commencait des que la section pointait en bas
                    // de l ecran et finissait quand elle en sortait par le haut.
                    // On ne voyait donc jamais ni les premieres cartes ni les
                    // dernieres : la course etait deja faite a l arrivee.
                    start: 'top 78%',
                    end: 'bottom 22%',
                    scrub: 0.6
                }
            });
        });
    }

    /* =====================================================================
       4. Apparition des sections au defilement
       ===================================================================== */

    function apparitions() {
        if (!gsapPresent || moinsDAnimation) { return; }
        if (typeof window.ScrollTrigger === 'undefined') { return; }
        gsap.registerPlugin(ScrollTrigger);

        // On anime les blocs internes, pas les sections entieres : animer une
        // section epinglee entrerait en conflit avec le defilement horizontal.
        var blocs = document.querySelectorAll(
            '.vitrine-section .section-header, .vitrine-section .feature-card, ' +
            '.vitrine-section .timeline-item, .vitrine-section .form-container'
        );

        Array.prototype.forEach.call(blocs, function (bloc) {
            gsap.from(bloc, {
                y: 26,
                autoAlpha: 0,
                duration: 0.6,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: bloc,
                    start: 'top 88%',
                    toggleActions: 'play none none none'
                }
            });
        });
    }

    /* ===================================================================== */

    function demarrer() {
        try { ecranDeChargement(); }   catch (e) { console.warn('[vitrine] chargement :', e); }
        try { barreDeProgression(); }  catch (e) { console.warn('[vitrine] progression :', e); }
        try { navigationParAncres(); } catch (e) { console.warn('[vitrine] navigation :', e); }
        try { titreFrappe(); }         catch (e) { console.warn('[vitrine] titre :', e); }
        try { fondVideo(); }           catch (e) { console.warn('[vitrine] video :', e); }
        try { parallaxeVideo(); }      catch (e) { console.warn('[vitrine] parallaxe :', e); }
        try { parallaxeFigures(); }    catch (e) { console.warn('[vitrine] silhouettes :', e); }
        try { galeriesAlternees(); }   catch (e) { console.warn('[vitrine] galeries :', e); }
        try { apparitions(); }         catch (e) { console.warn('[vitrine] apparitions :', e); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})();
