/* =============================================================================
   Cours offerts par session

   La page prépare UNE session à la fois. Les quatre états vivent néanmoins
   ensemble en mémoire : on peut cocher au premier semestre, passer au second,
   y cocher aussi, et n'enregistrer qu'une fois. Changer d'onglet ne perd rien.

   L'enregistrement n'envoie que ce qui a CHANGÉ. L'ancienne page réécrivait
   les 663 cours × 4 sessions à chaque fois — 2 652 écritures pour une case.

   CHANGEMENT D'ANNÉE SANS RECHARGEMENT
   ------------------------------------
   Le sélecteur d'année soumettait un formulaire : la page entière se
   rechargeait. Elle est maintenant relue en arrière-plan et seul le bloc
   #cours-sessions est remplacé ; demarrer() s'applique alors au nouveau bloc.
   L'adresse suit (?academic_year=…), et précédent / suivant du navigateur
   ramènent l'année correspondante. Des cases non enregistrées font demander
   confirmation avant de changer d'année : elles seraient perdues.
   ========================================================================== */
(function () {
    'use strict';

    var CIBLE = (typeof APP_BASE === 'string' ? APP_BASE : '') + '/app/.cours/save_offered.php';

    /* Modifications en attente du bloc affiché. Remplacée à chaque demarrer() :
       le garde-fou de sortie de page et le changement d'année la consultent. */
    var enAttenteCourant = function () { return []; };
    var chargement = false;

    function dire(message, type) {
        if (window.Toast && typeof Toast.show === 'function') {
            Toast.show(message, type, { duration: type === 'error' ? 9000 : 4000 });
            return;
        }
        if (window.console) { console.warn(message); }
    }

    function demander(options, texte) {
        if (window.Toast && typeof Toast.confirm === 'function') {
            return Toast.confirm(texte, options);
        }
        return Promise.resolve(window.confirm(texte));
    }

    function demarrer() {
        var racine = document.getElementById('cours-sessions');
        if (!racine) { return; }

        /* État courant et état d'origine. La comparaison des deux donne le nombre
           de modifications en attente — l'information dont on a besoin pour savoir
           s'il reste quelque chose à enregistrer. */
        var etat = JSON.parse(document.getElementById('cso-etat').textContent);
        var origine = JSON.parse(JSON.stringify(etat));

        var sessions = JSON.parse(document.getElementById('cso-sessions-liste').textContent);
        var session = sessions[0];

        var annee = racine.dataset.annee || '';
        var jeton = racine.dataset.jeton || '';

        var lignes = Array.prototype.slice.call(racine.querySelectorAll('.cso-cours'));
        var groupes = Array.prototype.slice.call(racine.querySelectorAll('.cso-groupe'));

        function estOffert(id) {
            return !!(etat[id] && etat[id][session]);
        }

        /* -----------------------------------------------------------------
           Modifications en attente
           ----------------------------------------------------------------- */
        function enAttente() {
            var liste = [];
            Object.keys(etat).forEach(function (id) {
                sessions.forEach(function (s) {
                    var avant = (origine[id] && origine[id][s]) ? 1 : 0;
                    var apres = (etat[id] && etat[id][s]) ? 1 : 0;
                    if (avant !== apres) { liste.push({ id: id, session: s, valeur: apres }); }
                });
            });
            return liste;
        }
        enAttenteCourant = enAttente;

        /* -----------------------------------------------------------------
           Rendu
           ----------------------------------------------------------------- */
        var bouton = document.getElementById('cso-enregistrer');
        var note = document.getElementById('cso-note');
        var resume = document.getElementById('cso-resume');

        function majCompteurs() {
            // Onglets : nombre de cours offerts dans chaque session.
            sessions.forEach(function (s) {
                var n = 0;
                Object.keys(etat).forEach(function (id) { if (etat[id] && etat[id][s]) { n++; } });
                var puce = racine.querySelector('.cso-onglet[data-session="' + CSS.escape(s) + '"] .cso-onglet__compte');
                if (puce) { puce.textContent = n; }
            });

            // Groupes : offerts / total, pour la session courante.
            groupes.forEach(function (g) {
                var sesLignes = Array.prototype.slice.call(g.querySelectorAll('.cso-cours'));
                var offerts = sesLignes.filter(function (l) { return estOffert(l.dataset.id); }).length;
                var puce = g.querySelector('.cso-groupe__compte');
                if (puce) {
                    puce.textContent = offerts + ' / ' + sesLignes.length;
                    puce.classList.toggle('cso-groupe__compte--plein', offerts > 0);
                }
            });

            var total = lignes.length;
            var offertsTotal = lignes.filter(function (l) { return estOffert(l.dataset.id); }).length;
            if (resume) {
                resume.innerHTML = '<strong>' + offertsTotal + '</strong> offert'
                    + (offertsTotal > 1 ? 's' : '') + ' sur ' + total + ' cours';
            }

            // Barre d'enregistrement : le bouton dit combien de gestes il valide.
            var n = enAttente().length;
            if (bouton) {
                bouton.disabled = n === 0;
                bouton.textContent = n === 0 ? 'Aucune modification'
                    : 'Enregistrer ' + n + ' modification' + (n > 1 ? 's' : '');
            }
            if (note) {
                note.innerHTML = n === 0
                    ? 'Les cours non cochés ne sont pas proposés à l’inscription pour cette session.'
                    : '<strong>' + n + ' modification' + (n > 1 ? 's' : '') + ' non enregistrée'
                      + (n > 1 ? 's' : '') + '.</strong>';
            }
        }

        /** Remet chaque ligne en accord avec la session affichée. */
        function majLignes() {
            lignes.forEach(function (ligne) {
                var offert = estOffert(ligne.dataset.id);
                var case_ = ligne.querySelector('input[type="checkbox"]');
                if (case_) { case_.checked = offert; }
                ligne.classList.toggle('est-offert', offert);

                /* Semestre natif : mis en avant quand il diffère de la session
                   préparée. C'est le cas que cette page existe pour traiter, et
                   rien ne le montrait — un cours de premier semestre offert au
                   second n'était signalé nulle part. */
                var badge = ligne.querySelector('.cso-sem');
                if (!badge) { return; }
                var natif = ligne.dataset.sessionNative || '';
                var decale = natif !== '' && natif !== session;
                badge.classList.toggle('cso-sem--decale', decale);
                badge.textContent = decale ? (ligne.dataset.semCourt + ' → ' + abrege(session))
                                           : ligne.dataset.semCourt;
                badge.title = decale
                    ? 'Cours de ' + natif.toLowerCase() + ', proposé ici en ' + session.toLowerCase()
                    : 'Semestre habituel de ce cours';
            });
        }

        function abrege(label) {
            if (label === 'Premier semestre') { return 'S1'; }
            if (label === 'Deuxième semestre') { return 'S2'; }
            if (label === "Semestre d'été") { return 'Été'; }
            return 'Hiver';
        }

        /* -----------------------------------------------------------------
           Onglets de session
           ----------------------------------------------------------------- */
        racine.addEventListener('click', function (e) {
            var onglet = e.target.closest('.cso-onglet');
            if (!onglet) { return; }

            session = onglet.dataset.session;
            racine.querySelectorAll('.cso-onglet').forEach(function (o) {
                o.setAttribute('aria-selected', String(o === onglet));
            });
            var titre = document.getElementById('cso-session-courante');
            if (titre) { titre.textContent = session; }

            majLignes();
            majCompteurs();
            filtrer();
        });

        /* -----------------------------------------------------------------
           Cases
           ----------------------------------------------------------------- */
        racine.addEventListener('change', function (e) {
            var case_ = e.target;
            if (!case_.matches || !case_.matches('.cso-cours input[type="checkbox"]')) { return; }

            var id = case_.closest('.cso-cours').dataset.id;
            if (!etat[id]) { etat[id] = {}; }
            etat[id][session] = case_.checked ? 1 : 0;

            majLignes();
            majCompteurs();
        });

        /* Toute la ligne est cliquable : viser une case parmi six cents, c'est se
           tromper. On ignore le clic sur la case elle-même, qui a déjà basculé. */
        racine.addEventListener('click', function (e) {
            var ligne = e.target.closest('.cso-cours');
            if (!ligne) { return; }
            if (e.target.matches('input[type="checkbox"]')) { return; }

            /* La ligne est un <label> : sans preventDefault, le navigateur
               rebasculait la case après nous et le clic sur la ligne n'avait
               aucun effet (seul le clic sur la case elle-même cochait). */
            e.preventDefault();

            var case_ = ligne.querySelector('input[type="checkbox"]');
            if (!case_) { return; }
            case_.checked = !case_.checked;
            case_.dispatchEvent(new Event('change', { bubbles: true }));
        });

        /* -----------------------------------------------------------------
           Groupes
           ----------------------------------------------------------------- */
        racine.addEventListener('click', function (e) {
            var tete = e.target.closest('.cso-groupe__tete');
            if (!tete || e.target.closest('.cso-groupe__tout')) { return; }
            var g = tete.closest('.cso-groupe');
            g.classList.toggle('est-ouvert');
            tete.setAttribute('aria-expanded', String(g.classList.contains('est-ouvert')));
        });

        racine.addEventListener('click', function (e) {
            var tout = e.target.closest('.cso-groupe__tout');
            if (!tout) { return; }
            e.stopPropagation();

            var g = tout.closest('.cso-groupe');
            /* Seulement les lignes VISIBLES : si un filtre est actif, « tout
               cocher » ne doit pas atteindre ce qu'on ne voit pas. */
            var visibles = Array.prototype.slice.call(g.querySelectorAll('.cso-cours'))
                .filter(function (l) { return !l.hidden; });
            if (!visibles.length) { return; }

            // Tout décocher si tout est déjà coché, sinon tout cocher.
            var toutCoche = visibles.every(function (l) { return estOffert(l.dataset.id); });
            visibles.forEach(function (l) {
                if (!etat[l.dataset.id]) { etat[l.dataset.id] = {}; }
                etat[l.dataset.id][session] = toutCoche ? 0 : 1;
            });

            majLignes();
            majCompteurs();
        });

        /* -----------------------------------------------------------------
           Filtres
           ----------------------------------------------------------------- */
        var recherche = document.getElementById('cso-recherche');
        var fMention = document.getElementById('cso-mention');
        var fNiveau = document.getElementById('cso-niveau');
        var fEtat = document.getElementById('cso-etat-filtre');
        var vide = document.getElementById('cso-vide');

        function filtrer() {
            var q = (recherche ? recherche.value : '').trim().toLowerCase();
            var m = fMention ? fMention.value : '';
            var n = fNiveau ? fNiveau.value : '';
            var et = fEtat ? fEtat.value : '';

            var visiblesTotal = 0;

            lignes.forEach(function (l) {
                var garde = true;
                if (q && l.dataset.recherche.indexOf(q) === -1) { garde = false; }
                if (garde && m && l.dataset.mention !== m) { garde = false; }
                if (garde && n && l.dataset.niveau !== n) { garde = false; }
                if (garde && et === 'offerts' && !estOffert(l.dataset.id)) { garde = false; }
                if (garde && et === 'non' && estOffert(l.dataset.id)) { garde = false; }
                l.hidden = !garde;
                if (garde) { visiblesTotal++; }
            });

            /* Un groupe dont aucune ligne ne passe le filtre disparaît : laisser
               des en-têtes vides donne l'impression d'un résultat alors qu'il n'y
               a rien dedans. */
            groupes.forEach(function (g) {
                var reste = Array.prototype.slice.call(g.querySelectorAll('.cso-cours'))
                    .some(function (l) { return !l.hidden; });
                g.hidden = !reste;
                // Un filtre actif ouvre les groupes : sinon on cherche à l'aveugle.
                if (reste && (q || m || n || et)) { g.classList.add('est-ouvert'); }
            });

            if (vide) { vide.hidden = visiblesTotal > 0; }
        }

        [recherche, fMention, fNiveau, fEtat].forEach(function (el) {
            if (!el) { return; }
            el.addEventListener('input', filtrer);
            el.addEventListener('change', filtrer);
        });

        /* -----------------------------------------------------------------
           Année scolaire : sans rechargement (voir changerAnnee)
           ----------------------------------------------------------------- */
        var selectAnnee = document.getElementById('academic_year');
        if (selectAnnee) {
            selectAnnee.addEventListener('change', function () {
                changerAnnee(selectAnnee.value, true);
            });
            var formAnnee = selectAnnee.form;
            if (formAnnee) {
                formAnnee.addEventListener('submit', function (e) {
                    e.preventDefault();
                    changerAnnee(selectAnnee.value, true);
                });
            }
        }

        // « Passer à 2025 - 2026 » dans l'avertissement d'année.
        racine.addEventListener('click', function (e) {
            var lien = e.target.closest('.cso-avertit a[href]');
            if (!lien) { return; }
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) { return; }
            e.preventDefault();
            var cibleAnnee = new URL(lien.href, window.location.href).searchParams.get('academic_year') || '';
            changerAnnee(cibleAnnee, true);
        });

        /* -----------------------------------------------------------------
           Enregistrement
           ----------------------------------------------------------------- */
        if (bouton) {
            bouton.addEventListener('click', async function () {
                var attente = enAttente();
                if (!attente.length) { return; }

                // Seulement ce qui a changé — le serveur n'applique que ce qu'il reçoit.
                var charge = {};
                attente.forEach(function (m) {
                    if (!charge[m.id]) { charge[m.id] = {}; }
                    charge[m.id][m.session] = m.valeur;
                });

                var libelle = bouton.textContent;
                bouton.disabled = true;
                bouton.textContent = 'Enregistrement…';

                try {
                    var corps = new FormData();
                    corps.set('academic_year', annee);
                    corps.set('csrf_token', jeton);
                    corps.set('course_sessions_payload', JSON.stringify(charge));

                    var reponse = await fetch(CIBLE, {
                        method: 'POST', body: corps,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    });

                    if (reponse.status === 401 || reponse.status === 403) {
                        var r4 = await reponse.json().catch(function () { return null; });
                        if (reponse.status === 403 && r4 && r4.message) { dire(r4.message, 'error'); }
                        return;
                    }

                    var r = await reponse.json().catch(function () {
                        throw new Error('réponse illisible');
                    });

                    if (r && r.csrf_expire) { dire(r.message, 'error'); return; }
                    if (!r.ok) { dire(r.message || "L'enregistrement n'a pas abouti.", 'error'); return; }

                    // L'état enregistré devient le nouvel état d'origine.
                    origine = JSON.parse(JSON.stringify(etat));
                    majCompteurs();
                    dire(r.message, 'success');
                } catch (err) {
                    dire('Le serveur n’a pas répondu. Rien n’a été enregistré.', 'error');
                } finally {
                    bouton.disabled = false;
                    bouton.textContent = libelle;
                    majCompteurs();
                }
            });
        }

        // Premier rendu.
        majLignes();
        majCompteurs();
        filtrer();
    }

    /* -------------------------------------------------------------------------
       Changement d'année
       ------------------------------------------------------------------------- */

    /** Adresse de la page pour une année ('' : l'année par défaut du serveur). */
    function adresseAnnee(annee) {
        var url = new URL(window.location.href);
        if (annee) { url.searchParams.set('academic_year', annee); }
        else { url.searchParams.delete('academic_year'); }
        return url.toString();
    }

    /**
     * Charge l'année demandée et remplace le bloc #cours-sessions.
     * @param {boolean} empiler false quand l'appel vient de précédent / suivant.
     */
    function changerAnnee(annee, empiler) {
        var racine = document.getElementById('cours-sessions');
        if (!racine || chargement) { return; }

        var actuelle = racine.dataset.annee || '';
        var adresseActuelle = window.location.href;
        var select = document.getElementById('academic_year');
        if (annee !== '' && annee === actuelle) { return; }

        var attente = enAttenteCourant().length;
        var confirmation = attente === 0
            ? Promise.resolve(true)
            : demander({ title: 'Cases non enregistrées', confirmText: 'Changer d’année', danger: true },
                attente + ' modification' + (attente > 1 ? 's' : '') + ' non enregistrée'
                + (attente > 1 ? 's' : '') + ' pour ' + actuelle + '.\n\n'
                + 'Changer d’année les abandonnera. Continuer ?');

        confirmation.then(function (ok) {
            if (!ok) {
                // On reste : le sélecteur et l'adresse reviennent à l'année affichée.
                if (select) { select.value = actuelle; }
                if (!empiler) { window.history.pushState({ annee: actuelle }, '', adresseAnnee(actuelle)); }
                return;
            }

            var adresse = adresseAnnee(annee);
            chargement = true;
            racine.classList.add('est-chargement');
            racine.setAttribute('aria-busy', 'true');

            fetch(adresse, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.text();
                })
                .then(function (html) {
                    var neuve = new DOMParser().parseFromString(html, 'text/html').getElementById('cours-sessions');
                    if (!neuve) { throw new Error('bloc introuvable'); }

                    enAttenteCourant = function () { return []; };
                    racine.replaceWith(document.importNode(neuve, true));
                    if (empiler) {
                        window.history.pushState({ annee: neuve.dataset.annee || annee }, '', adresse);
                    }
                    demarrer();

                    var defilement = document.querySelector('.back');
                    if (defilement) { defilement.scrollTop = 0; }
                })
                .catch(function () {
                    // Relecture impossible : navigation classique vers la même adresse.
                    window.location.href = adresse;
                })
                .then(function () {
                    chargement = false;
                    var bloc = document.getElementById('cours-sessions');
                    if (bloc) { bloc.classList.remove('est-chargement'); bloc.removeAttribute('aria-busy'); }
                    if (adresseActuelle === window.location.href && select && select.isConnected) { select.value = actuelle; }
                });
        });
    }

    /* Précédent / suivant : l'année est lue dans l'état empilé, ou dans l'adresse. */
    window.addEventListener('popstate', function (e) {
        var annee = (e.state && typeof e.state.annee === 'string')
            ? e.state.annee
            : (new URL(window.location.href).searchParams.get('academic_year') || '');
        changerAnnee(annee, false);
    });

    /* Quitter la page avec des cases cochées et non enregistrées, c'est perdre
       un travail qui se compte parfois en centaines de gestes.

       Le rideau de transition sait desormais se relever si la navigation est
       annulee (voir coverThen dans src/js/transitions.js) : sans ce correctif,
       repondre « rester » laissait l'ecran couvert. */
    window.addEventListener('beforeunload', function (e) {
        if (!enAttenteCourant().length) { return; }
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    demarrer();

    // L'entrée d'historique de départ porte son année : un retour arrière la retrouve.
    var depart = document.getElementById('cours-sessions');
    if (depart) {
        window.history.replaceState({ annee: depart.dataset.annee || '' }, '', window.location.href);
    }
})();
