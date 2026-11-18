/* =============================================================================
   Easter egg : le campus en monde ouvert

   Une voiture a physique reelle sur un campus librement parcourable. On y
   retrouve les reperes de l'universite, des rampes, des caisses qui se
   renversent, et sept diplomes disperses — un par mention.

   Se declenche par la sequence du Konami Code. Rien n'y renvoie : c'est le
   principe d'un easter egg.

   BIBLIOTHEQUES
   Three.js (MIT) pour le rendu, cannon.js (MIT) pour la physique. Toutes deux
   permissives, chargees depuis cdnjs. Les decors, les proportions et le code
   ci-dessous sont propres a ce projet.

   POURQUOI UN CHARGEMENT DIFFERE
   Les deux bibliotheques pesent pres de 750 Ko. Les faire porter a tous les
   visiteurs d'une vitrine universitaire, pour un jeu que la plupart ne verront
   jamais, serait indefendable — a Madagascar en particulier, ou la bande
   passante se paie. Elles ne sont chargees qu'a l'instant ou la sequence est
   reconnue.

   POURQUOI cannon.js ET NON cannon-es
   cannon-es n'est distribue qu'en module ES. Ce projet n'a pas d'etape de
   compilation : il lui faut un script classique exposant un global.
   ========================================================================== */

(function () {
    'use strict';

    var LIBS = [
        'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/cannon.js/0.6.2/cannon.min.js'
    ];

    var SEQUENCE = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown',
                    'ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];
    var position = 0;

    var actif = false, boucle = null;
    var scene, camera, rendu, monde, vehicule, chassis;
    var mobiles = [];        // { corps, maille } synchronises a chaque image
    var roues = [];
    var diplomes = [], ramasses = 0;
    var touches = {}, conteneur, compteur, horloge;

    var C = {
        ciel: 0xdce9f5, marine: 0x183060, or: 0xc9a227, blanc: 0xf4f6fa,
        toit: 0x8c3b30, tronc: 0x6b4f3a, caisse: 0xc8a172, rampe: 0x9aa7b8,
        piste: 0xd9d2c4, eau: 0x5b93b8, terre: 0xb08968, pierre: 0x9c9a93,
        // Trois verts et trois toits : sans variation, une rangee d'arbres
        // identiques trahit immediatement la generation automatique.
        herbe: 0x7fa86b,
        feuilles: [0x4a7c52, 0x5c8f5e, 0x3f6b48],
        toits:    [0x8c3b30, 0xa04b3c, 0x77332b],
        peaux:    [0xe0b48f, 0xc98c5f, 0x8d5a3b, 0xf0cfa8]
    };

    var animes = [];   // objets dotes d'une fonction pas(t)

    // ---------------------------------------------------------------- clavier
    document.addEventListener('keydown', function (e) {
        if (actif || !e.key) { return; }
        var attendu = SEQUENCE[position];
        if (e.key.toLowerCase() === attendu.toLowerCase()) {
            if (++position === SEQUENCE.length) { position = 0; lancer(); }
        } else {
            position = (e.key === SEQUENCE[0]) ? 1 : 0;
        }
    });

    // ------------------------------------------------------------- chargement
    function charger(url) {
        return new Promise(function (ok, ko) {
            var s = document.createElement('script');
            s.src = url;
            s.onload = ok;
            s.onerror = function () { ko(new Error(url)); };
            document.head.appendChild(s);
        });
    }

    function lancer() {
        actif = true;
        conteneur = document.createElement('div');
        conteneur.className = 'oeuf-scene';
        conteneur.innerHTML = '<div class="oeuf-chargement">Chargement du campus…</div>';
        document.body.appendChild(conteneur);
        document.body.style.overflow = 'hidden';

        // En serie : cannon n'a pas besoin de three, mais deux chargements
        // simultanes sur une liaison lente se genent plus qu'ils ne s'aident.
        LIBS.reduce(function (chaine, url) {
            return chaine.then(function () {
                return (url.indexOf('three') > -1 && window.THREE) ||
                       (url.indexOf('cannon') > -1 && window.CANNON)
                       ? null : charger(url);
            });
        }, Promise.resolve())
        .then(construire)
        .catch(function () {
            conteneur.innerHTML =
                '<div class="oeuf-chargement">Le campus n’a pas pu se charger.' +
                '<br><button type="button" class="oeuf-fermer">Fermer</button></div>';
            conteneur.querySelector('.oeuf-fermer').addEventListener('click', quitter);
        });
    }

    // ------------------------------------------------------- fabriques d'objets
    function matiere(c) { return new THREE.MeshLambertMaterial({ color: c }); }

    /** Boite visible + corps physique, lies pour la duree de la partie. */
    function boiteSolide(l, h, p, couleur, x, y, z, masse) {
        var maille = new THREE.Mesh(new THREE.BoxGeometry(l, h, p), matiere(couleur));
        maille.position.set(x, y, z);
        scene.add(maille);

        var corps = new CANNON.Body({ mass: masse || 0 });
        corps.addShape(new CANNON.Box(new CANNON.Vec3(l / 2, h / 2, p / 2)));
        corps.position.set(x, y, z);
        monde.addBody(corps);

        // Seuls les corps mobiles ont besoin d'etre resynchronises : recopier
        // la position d'un batiment immobile a chaque image serait du gachis.
        if (masse) { mobiles.push({ corps: corps, maille: maille }); }
        return { corps: corps, maille: maille };
    }

    function batiment(x, z, l, h, p, avecToit) {
        boiteSolide(l, h, p, C.blanc, x, h / 2, z, 0);
        boiteSolide(l + 0.1, 0.6, p + 0.1, C.marine, x, 0.3, z, 0);
        if (avecToit) {
            var t = new THREE.Mesh(
                new THREE.ConeGeometry(Math.max(l, p) * 0.78, 2.2, 4), matiere(hasard(C.toits)));
            t.position.set(x, h + 1.1, z);
            t.rotation.y = Math.PI / 4;
            scene.add(t);
        }
    }

    function hasard(liste) { return liste[Math.floor(Math.random() * liste.length)]; }

    /* Arbre a etages. La hauteur, la teinte et le nombre de cones varient :
       une rangee d'arbres identiques se reconnait au premier coup d'oeil. */
    function arbre(x, z, echelle) {
        var e = echelle || (0.8 + Math.random() * 0.6);
        var vert = hasard(C.feuilles);
        var g = new THREE.Group();
        var tr = new THREE.Mesh(new THREE.BoxGeometry(0.3, 2.4 * e, 0.3), matiere(C.tronc));
        tr.position.y = 1.2 * e; g.add(tr);
        var etages = 2 + Math.round(Math.random());
        for (var i = 0; i < etages; i++) {
            var f = new THREE.Mesh(
                new THREE.ConeGeometry((1.35 - i * 0.3) * e, 1.9 * e, 7), matiere(vert));
            f.position.y = (2.5 + i * 1.05) * e;
            f.rotation.y = Math.random() * Math.PI;
            g.add(f);
        }
        g.position.set(x, 0, z);
        scene.add(g);
    }

    /** Tour a etages decroissants — le clocher, le chateau d'eau. */
    function tour(x, z, etages, base) {
        var b = base || 3;
        for (var i = 0; i < etages; i++) {
            var l = b - i * 0.45;
            boiteSolide(l, 2.2, l, i % 2 ? C.blanc : C.pierre, x, 1.1 + i * 2.2, z, 0);
        }
        var f = new THREE.Mesh(
            new THREE.ConeGeometry(b * 0.62, 3, 6), matiere(hasard(C.toits)));
        f.position.set(x, etages * 2.2 + 1.5, z);
        scene.add(f);
    }

    /** Portique d'entree : deux piliers et une traverse. */
    function arche(x, z, largeur) {
        var l = largeur || 9;
        boiteSolide(1, 5, 1, C.pierre, x - l / 2, 2.5, z, 0);
        boiteSolide(1, 5, 1, C.pierre, x + l / 2, 2.5, z, 0);
        boiteSolide(l + 1.6, 1.1, 1.2, C.marine, x, 5.6, z, 0);
        var pointe = new THREE.Mesh(new THREE.ConeGeometry(1.2, 1.8, 4), matiere(C.or));
        pointe.position.set(x, 7, z);
        pointe.rotation.y = Math.PI / 4;
        scene.add(pointe);
    }

    function lampadaire(x, z) {
        boiteSolide(0.22, 4.2, 0.22, C.pierre, x, 2.1, z, 0);
        var t = new THREE.Mesh(new THREE.ConeGeometry(0.5, 0.7, 6), matiere(C.or));
        t.position.set(x, 4.4, z);
        t.rotation.x = Math.PI;
        scene.add(t);
    }

    function banc(x, z, tourne) {
        var g = new THREE.Group();
        var a = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.16, 0.7), matiere(C.tronc));
        a.position.y = 0.5; g.add(a);
        [-1, 1].forEach(function (s) {
            var p = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.5, 0.6), matiere(C.pierre));
            p.position.set(s * 1, 0.25, 0); g.add(p);
        });
        g.position.set(x, 0, z);
        g.rotation.y = tourne || 0;
        scene.add(g);
    }

    /** Bassin circulaire avec jet — le cone monte et descend. */
    function fontaine(x, z) {
        boiteSolide(5, 0.7, 5, C.pierre, x, 0.35, z, 0);
        var eau = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.12, 4.2), matiere(C.eau));
        eau.position.set(x, 0.72, z);
        scene.add(eau);
        var jet = new THREE.Mesh(new THREE.ConeGeometry(0.4, 2.4, 8), matiere(C.eau));
        jet.position.set(x, 1.9, z);
        scene.add(jet);
        animes.push({ pas: function (t) {
            jet.scale.y = 1 + Math.sin(t * 2.2) * 0.22;
            jet.position.y = 1.9 + Math.sin(t * 2.2) * 0.24;
        }});
    }

    /** Mat et drapeau : le drapeau ondule par rotation lente. */
    function mat(x, z) {
        boiteSolide(0.2, 7, 0.2, C.pierre, x, 3.5, z, 0);
        var d = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.3, 0.06), matiere(C.marine));
        d.position.set(x + 1.2, 6.1, z);
        scene.add(d);
        animes.push({ pas: function (t) {
            d.rotation.y = Math.sin(t * 1.6) * 0.28;
            d.position.z = z + Math.sin(t * 1.6) * 0.18;
        }});
    }

    /** Silhouette d'etudiant : bloc, cone en guise de tete, deambule. */
    function passant(x, z, rayon) {
        var g = new THREE.Group();
        var corps = new THREE.Mesh(new THREE.BoxGeometry(0.5, 1.1, 0.32),
            matiere(hasard([C.marine, C.or, 0x4a7c52, 0x8c3b30])));
        corps.position.y = 0.85; g.add(corps);
        var tete = new THREE.Mesh(new THREE.ConeGeometry(0.24, 0.5, 6), matiere(hasard(C.peaux)));
        tete.position.y = 1.65; g.add(tete);
        scene.add(g);
        var r = rayon || 6, phase = Math.random() * Math.PI * 2, vit = 0.25 + Math.random() * 0.3;
        animes.push({ pas: function (t) {
            var a = phase + t * vit;
            g.position.set(x + Math.cos(a) * r, 0, z + Math.sin(a) * r);
            g.rotation.y = -a + Math.PI / 2;
            g.position.y = Math.abs(Math.sin(t * 6 + phase)) * 0.09;   // demarche
        }});
    }

    /** Eolienne : quatre pales qui tournent. Un repere visible de loin. */
    function eolienne(x, z) {
        boiteSolide(0.5, 9, 0.5, C.blanc, x, 4.5, z, 0);
        var moyeu = new THREE.Group();
        moyeu.position.set(x, 9.2, z);
        for (var i = 0; i < 4; i++) {
            var p = new THREE.Mesh(new THREE.BoxGeometry(0.28, 4.4, 0.1), matiere(C.blanc));
            p.position.y = 2.2;
            var b = new THREE.Group();
            b.add(p);
            b.rotation.z = i * Math.PI / 2;
            moyeu.add(b);
        }
        scene.add(moyeu);
        animes.push({ pas: function (t) { moyeu.rotation.z = t * 0.9; } });
    }

    /** Oiseaux : des cones qui tournent haut dans le ciel. */
    function oiseaux(x, z, hauteur, nombre) {
        var vol = new THREE.Group();
        vol.position.set(x, hauteur, z);
        for (var i = 0; i < nombre; i++) {
            var o = new THREE.Mesh(new THREE.ConeGeometry(0.3, 1.1, 4), matiere(0x3b4252));
            o.rotation.z = -Math.PI / 2;
            o.position.set(Math.cos(i / nombre * 6.28) * 7,
                           Math.sin(i * 1.7) * 1.6,
                           Math.sin(i / nombre * 6.28) * 7);
            vol.add(o);
        }
        scene.add(vol);
        animes.push({ pas: function (t) {
            vol.rotation.y = t * 0.35;
            vol.position.y = hauteur + Math.sin(t * 0.6) * 1.8;
        }});
    }

    /** Colline : un cone tres aplati, purement decoratif. */
    function colline(x, z, rayon, hauteur) {
        var c = new THREE.Mesh(new THREE.ConeGeometry(rayon, hauteur, 9), matiere(C.herbe));
        c.position.set(x, hauteur / 2 - 0.4, z);
        scene.add(c);
    }

    /** Rampe : un pave incline, immobile, sur lequel la voiture decolle. */
    function rampe(x, z, angle, rotation) {
        var l = 6, h = 0.5, p = 9;
        var maille = new THREE.Mesh(new THREE.BoxGeometry(l, h, p), matiere(C.rampe));
        var corps = new CANNON.Body({ mass: 0 });
        corps.addShape(new CANNON.Box(new CANNON.Vec3(l / 2, h / 2, p / 2)));
        var q = new CANNON.Quaternion();
        q.setFromEuler(angle, rotation || 0, 0, 'XYZ');
        corps.quaternion.copy(q);
        corps.position.set(x, 1.1, z);
        monde.addBody(corps);
        maille.position.copy(corps.position);
        maille.quaternion.copy(corps.quaternion);
        scene.add(maille);
    }

    /* =====================================================================
       Le monde, par zones

       Decoupe en fonctions nommees plutot qu'en une longue liste : chaque
       quartier se lit, se deplace et se retouche seul. C'est aussi ce qui
       permet de dire ou l'on est quand on parle du campus.
       ===================================================================== */

    function zoneEntree() {
        arche(0, -52, 10);
        mat(-7, -50); mat(7, -50);
        for (var x = -14; x <= 14; x += 7) { lampadaire(x, -46); }
        fontaine(0, -34);
        for (var i = 0; i < 5; i++) { passant(0, -34, 7 + i); }
    }

    function zoneAdministration() {
        batiment(-20, -22, 12, 8, 9, true);
        tour(-30, -26, 3, 3.2);                 // le clocher
        banc(-13, -16, 0); banc(-13, -12, 0);
        lampadaire(-11, -20); lampadaire(-11, -26);
        arbre(-28, -14); arbre(-31, -18);
        for (var i = 0; i < 4; i++) { passant(-20, -13, 4 + i * 1.6); }
    }

    function zoneBibliotheque() {
        batiment(20, -18, 10, 7, 10, false);
        batiment(30, -10, 6, 4, 6, true);       // annexe
        banc(13, -14, Math.PI / 2);
        lampadaire(12, -20);
        arbre(30, -22); arbre(33, -17); arbre(27, -25);
        for (var i = 0; i < 3; i++) { passant(20, -8, 5 + i * 2); }
    }

    function zoneChapelle() {
        batiment(-22, 10, 9, 6, 9, true);
        tour(-22, 3, 4, 2.6);                   // clocher, plus haut
        arbre(-32, 6); arbre(-30, 14); arbre(-34, 11);
        banc(-14, 8, Math.PI / 2); banc(-14, 13, Math.PI / 2);
    }

    function zoneSportive() {
        // Terrain : un rectangle clair, borde de gradins en blocs.
        var terrain = new THREE.Mesh(new THREE.PlaneGeometry(22, 32), matiere(0x6f9a5e));
        terrain.rotation.x = -Math.PI / 2;
        terrain.position.set(24, 0.02, 20);
        scene.add(terrain);
        for (var g = 0; g < 3; g++) {
            boiteSolide(24, 0.9, 2, C.pierre, 24, 0.45 + g * 0.9, 38 + g * 2, 0);
        }
        [[16, 8], [32, 8], [16, 32], [32, 32]].forEach(function (p) { lampadaire(p[0], p[1]); });
        for (var i = 0; i < 6; i++) { passant(24, 20, 5 + i * 1.4); }
    }

    function zoneResidences() {
        for (var i = 0; i < 4; i++) {
            batiment(-24 + (i % 2) * 9, 30 + Math.floor(i / 2) * 11, 7, 4 + i % 2, 7, i % 2 === 0);
        }
        for (var j = 0; j < 4; j++) { lampadaire(-30 + j * 8, 26); }
        banc(-18, 28, 0);
        for (var k = 0; k < 4; k++) { passant(-19, 36, 5 + k * 1.7); }
    }

    function zoneSante() {
        batiment(22, 44, 9, 6, 9, true);
        batiment(32, 50, 6, 4, 6, false);
        arbre(14, 48); arbre(16, 54); arbre(30, 40);
        for (var i = 0; i < 3; i++) { passant(22, 52, 4 + i * 1.8); }
    }

    function paysage() {
        // Collines au loin : elles ferment l'horizon sans rien couter.
        colline(-52, -40, 16, 7); colline(46, -46, 13, 6);
        colline(-58, 26, 18, 8);  colline(54, 34, 15, 7);
        colline(-40, 62, 14, 6);  colline(38, 66, 16, 7);

        eolienne(-46, -6);
        oiseaux(-10, -20, 26, 6);
        oiseaux(24, 34, 22, 5);

        // Bosquets, semes de facon irreguliere.
        var bosquets = [[-38, -34], [40, -30], [-44, 4], [42, 8],
                        [-36, 46], [44, 52], [-12, 62], [16, 66]];
        bosquets.forEach(function (b) {
            for (var i = 0; i < 5; i++) {
                arbre(b[0] + (Math.random() - 0.5) * 9, b[1] + (Math.random() - 0.5) * 9);
            }
        });

        // Alignement d'eucalyptus le long de l'allee, espacement irregulier.
        for (var z = -56; z <= 60; z += 7 + Math.random() * 3) {
            arbre(-6.6 + Math.random(), z);
            arbre(6.6 - Math.random(), z + 3);
        }
    }

    function terrainDeJeu() {
        rampe(-10, -8, -0.30, 0);
        rampe(11, 24, -0.30, Math.PI);
        rampe(0, 50, -0.26, 0);
        rampe(-30, 18, -0.28, Math.PI / 2);

        // Pyramides de caisses : elles se renversent, c'est tout l'interet.
        [[-26, 0], [26, 4], [-9, 32], [13, -36], [34, 24], [-34, 40]].forEach(function (p) {
            for (var e = 0; e < 3; e++) {
                for (var k = 0; k <= e; k++) {
                    boiteSolide(1.2, 1.2, 1.2, C.caisse,
                        p[0] + (k - e / 2) * 1.35, 3.2 - e * 1.25, p[1], 6);
                }
            }
        });

        // Quilles : legeres, elles s'envolent au moindre contact.
        [[-16, 52], [18, -44], [36, 12]].forEach(function (p) {
            for (var i = 0; i < 6; i++) {
                boiteSolide(0.6, 1.8, 0.6, C.blanc,
                    p[0] + (i % 3) * 1.4 - 1.4, 0.9, p[1] + Math.floor(i / 3) * 1.4, 2);
            }
        });
    }

    function monument() {
        boiteSolide(3, 1.6, 3, C.blanc, 0, 0.8, -42, 0);
        boiteSolide(2.2, 1.2, 2.2, C.pierre, 0, 2.2, -42, 0);
        var toque = new THREE.Mesh(new THREE.BoxGeometry(3.8, 0.26, 3.8), matiere(C.marine));
        toque.position.set(0, 3, -42);
        toque.rotation.y = Math.PI / 6;
        scene.add(toque);
        var pompon = new THREE.Mesh(new THREE.ConeGeometry(0.22, 0.8, 6), matiere(C.or));
        pompon.position.set(1.4, 2.7, -42);
        scene.add(pompon);
        animes.push({ pas: function (t) { toque.rotation.y = Math.PI / 6 + t * 0.15; } });
    }

    function semerLesDiplomes() {
        // Un par mention, chacun dans sa zone : le parcours fait le tour.
        [[-20, -13], [20, -8], [-22, 4], [24, 20], [-19, 36], [22, 52], [0, -42]]
        .forEach(function (p) {
            var d = new THREE.Mesh(
                new THREE.CylinderGeometry(0.18, 0.18, 1.3, 10), matiere(C.or));
            d.position.set(p[0], 1.2, p[1]);
            d.rotation.z = Math.PI / 2.4;
            d.userData.pris = false;
            diplomes.push(d);
            scene.add(d);
        });
    }

    function batirLeMonde() {
        zoneEntree();
        zoneAdministration();
        zoneBibliotheque();
        zoneChapelle();
        zoneSportive();
        zoneResidences();
        zoneSante();
        paysage();
        terrainDeJeu();
        monument();
        semerLesDiplomes();
    }

    // ------------------------------------------------------------- la voiture
    function construireVehicule() {
        var demi = new CANNON.Vec3(0.9, 0.35, 1.9);
        chassis = new CANNON.Body({ mass: 160 });
        chassis.addShape(new CANNON.Box(demi));
        chassis.position.set(0, 3, 0);
        /* Amortissement angulaire : sans lui la caisse continue de tourner sur
           elle-meme apres un choc, et la voiture devient impilotable. */
        chassis.angularDamping = 0.4;

        vehicule = new CANNON.RaycastVehicle({
            chassisBody: chassis,
            indexRightAxis: 0, indexUpAxis: 1, indexForwardAxis: 2
        });

        var reglage = {
            radius: 0.4,
            directionLocal: new CANNON.Vec3(0, -1, 0),
            axleLocal: new CANNON.Vec3(1, 0, 0),
            suspensionStiffness: 34,
            suspensionRestLength: 0.32,
            frictionSlip: 4.2,
            dampingRelaxation: 2.4,
            dampingCompression: 4.2,
            maxSuspensionForce: 100000,
            rollInfluence: 0.03,            // faible : la voiture se retourne peu
            maxSuspensionTravel: 0.35,
            customSlidingRotationalSpeed: -30,
            useCustomSlidingRotationalSpeed: true
        };

        [[-0.85, 1.35], [0.85, 1.35], [-0.85, -1.35], [0.85, -1.35]].forEach(function (p) {
            reglage.chassisConnectionPointLocal = new CANNON.Vec3(p[0], 0, p[1]);
            vehicule.addWheel(reglage);
        });
        vehicule.addToWorld(monde);

        // Carrosserie
        var g = new THREE.Group();
        var caisse = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.7, 3.8), matiere(C.marine));
        g.add(caisse);
        var habitacle = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.55, 1.7), matiere(C.blanc));
        habitacle.position.set(0, 0.6, -0.3);
        g.add(habitacle);
        scene.add(g);
        mobiles.push({ corps: chassis, maille: g });

        // Roues, positionnees par la physique a chaque image.
        var geo = new THREE.CylinderGeometry(0.4, 0.4, 0.32, 14);
        geo.rotateZ(Math.PI / 2);
        for (var i = 0; i < 4; i++) {
            var r = new THREE.Mesh(geo, matiere(0x1b1b1f));
            scene.add(r);
            roues.push(r);
        }
    }

    // ------------------------------------------------------------ construction
    function construire() {
        conteneur.innerHTML =
            '<canvas class="oeuf-toile"></canvas>' +
            '<div class="oeuf-hud">' +
              '<p class="oeuf-hud__titre">Campus UAZ</p>' +
              '<p class="oeuf-hud__score"><span id="oeuf-compteur">0</span> / 7 diplômes</p>' +
              '<p class="oeuf-hud__aide">Flèches ou WASD · Espace pour freiner · R pour se remettre d’aplomb · Échap pour sortir</p>' +
            '</div>' +
            '<button type="button" class="oeuf-quitter" aria-label="Quitter le campus">✕</button>';

        compteur = conteneur.querySelector('#oeuf-compteur');
        conteneur.querySelector('.oeuf-quitter').addEventListener('click', quitter);

        rendu = new THREE.WebGLRenderer({
            canvas: conteneur.querySelector('.oeuf-toile'), antialias: true });
        rendu.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        rendu.setSize(window.innerWidth, window.innerHeight);

        scene = new THREE.Scene();
        scene.background = new THREE.Color(C.ciel);
        scene.fog = new THREE.Fog(C.ciel, 55, 130);
        camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 260);

        scene.add(new THREE.HemisphereLight(0xffffff, C.herbe, 0.95));
        var soleil = new THREE.DirectionalLight(0xffffff, 0.55);
        soleil.position.set(20, 34, 14);
        scene.add(soleil);

        // --- monde physique ---
        monde = new CANNON.World();
        monde.gravity.set(0, -22, 0);
        monde.broadphase = new CANNON.SAPBroadphase(monde);
        monde.defaultContactMaterial.friction = 0.35;

        var solCorps = new CANNON.Body({ mass: 0 });
        solCorps.addShape(new CANNON.Plane());
        solCorps.quaternion.setFromAxisAngle(new CANNON.Vec3(1, 0, 0), -Math.PI / 2);
        monde.addBody(solCorps);

        var solMaille = new THREE.Mesh(new THREE.PlaneGeometry(300, 300), matiere(C.herbe));
        solMaille.rotation.x = -Math.PI / 2;
        scene.add(solMaille);

        var allee = new THREE.Mesh(new THREE.PlaneGeometry(8, 130), matiere(0xd9d2c4));
        allee.rotation.x = -Math.PI / 2;
        allee.position.y = 0.01;
        scene.add(allee);

        batirLeMonde();

        construireVehicule();

        horloge = new THREE.Clock();
        window.addEventListener('keydown', enfoncer);
        window.addEventListener('keyup', relacher);
        window.addEventListener('resize', redimensionner);
        animer();
    }

    // ------------------------------------------------------------- interaction
    function enfoncer(e) {
        if (e.key === 'Escape') { quitter(); return; }
        touches[e.key.toLowerCase()] = true;
        if (e.key.indexOf('Arrow') === 0 || e.key === ' ') { e.preventDefault(); }
    }
    function relacher(e) { touches[e.key.toLowerCase()] = false; }

    function redimensionner() {
        if (!rendu) { return; }
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        rendu.setSize(window.innerWidth, window.innerHeight);
    }

    /** Remet la voiture sur ses roues, la ou elle est. */
    function redresser() {
        chassis.quaternion.set(0, 0, 0, 1);
        chassis.angularVelocity.set(0, 0, 0);
        chassis.velocity.set(0, 0, 0);
        chassis.position.y += 1.6;
    }

    function commander() {
        var avant   = touches['arrowup']    || touches['z'] || touches['w'];
        var arriere = touches['arrowdown']  || touches['s'];
        var gauche  = touches['arrowleft']  || touches['q'] || touches['a'];
        var droite  = touches['arrowright'] || touches['d'];
        var frein   = touches[' '];

        var force = avant ? -1500 : (arriere ? 900 : 0);
        // Traction arriere : les roues 2 et 3 poussent, les 0 et 1 dirigent.
        vehicule.applyEngineForce(force, 2);
        vehicule.applyEngineForce(force, 3);

        var braquage = gauche ? 0.42 : (droite ? -0.42 : 0);
        vehicule.setSteeringValue(braquage, 0);
        vehicule.setSteeringValue(braquage, 1);

        var f = frein ? 22 : (force === 0 ? 2.2 : 0);   // frein moteur au relache
        for (var i = 0; i < 4; i++) { vehicule.setBrake(f, i); }

        if (touches['r']) { touches['r'] = false; redresser(); }
    }

    function animer() {
        boucle = requestAnimationFrame(animer);
        var dt = Math.min(horloge.getDelta(), 0.05);   // borne : un onglet
                                                       // revenu au premier plan
                                                       // ne doit pas faire un
                                                       // bond de plusieurs pas
        commander();
        monde.step(1 / 60, dt, 3);

        /* Le decor vivant. Ces objets n'ont pas de corps physique : ils
           tournent, ondulent ou deambulent sans rien heurter. Leur donner une
           physique couterait cher pour un resultat que personne ne remarque —
           on ne rentre pas dans un passant, on le regarde passer. */
        var t = horloge.getElapsedTime();
        for (var a = 0; a < animes.length; a++) { animes[a].pas(t); }

        for (var i = 0; i < mobiles.length; i++) {
            mobiles[i].maille.position.copy(mobiles[i].corps.position);
            mobiles[i].maille.quaternion.copy(mobiles[i].corps.quaternion);
        }
        for (var r = 0; r < roues.length; r++) {
            vehicule.updateWheelTransform(r);
            roues[r].position.copy(vehicule.wheelInfos[r].worldTransform.position);
            roues[r].quaternion.copy(vehicule.wheelInfos[r].worldTransform.quaternion);
        }

        var p = chassis.position;

        diplomes.forEach(function (d) {
            if (d.userData.pris) { return; }
            d.rotation.y += 0.03;
            d.position.y = 1.2 + Math.sin(Date.now() * 0.003 + d.position.x) * 0.14;
            var dx = d.position.x - p.x, dz = d.position.z - p.z;
            if (dx * dx + dz * dz < 4.5) {
                d.userData.pris = true;
                d.visible = false;
                if (compteur) { compteur.textContent = ++ramasses; }
                if (ramasses === diplomes.length) { celebrer(); }
            }
        });

        // Camera derriere la voiture, orientee par son cap reel.
        var av = new CANNON.Vec3(0, 0, 1);
        chassis.quaternion.vmult(av, av);
        var vise = new THREE.Vector3(p.x - av.x * 11, p.y + 5.5, p.z - av.z * 11);
        camera.position.lerp(vise, 0.08);
        camera.lookAt(p.x, p.y + 1, p.z);

        rendu.render(scene, camera);
    }

    function celebrer() {
        var f = document.createElement('div');
        f.className = 'oeuf-fin';
        f.innerHTML = '<p>Les sept mentions réunies.</p>' +
                      '<p class="oeuf-fin__sous">Vous avez fait le tour du campus.</p>';
        conteneur.appendChild(f);
    }

    // ------------------------------------------------------------------ sortie
    function quitter() {
        if (!actif) { return; }
        actif = false;
        cancelAnimationFrame(boucle);
        window.removeEventListener('keydown', enfoncer);
        window.removeEventListener('keyup', relacher);
        window.removeEventListener('resize', redimensionner);

        /* WebGL ne rend pas sa memoire au ramasse-miettes : sans liberation
           explicite, rouvrir le jeu plusieurs fois sature le contexte. */
        if (scene) {
            scene.traverse(function (o) {
                if (o.geometry) { o.geometry.dispose(); }
                if (o.material) {
                    (Array.isArray(o.material) ? o.material : [o.material])
                        .forEach(function (m) { m.dispose(); });
                }
            });
        }
        if (rendu) { rendu.dispose(); }
        if (conteneur && conteneur.parentNode) { conteneur.parentNode.removeChild(conteneur); }
        document.body.style.overflow = '';

        scene = camera = rendu = monde = vehicule = chassis = null;
        conteneur = compteur = horloge = null;
        mobiles = []; roues = []; diplomes = []; ramasses = 0; touches = {};
    }
})();
