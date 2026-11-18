/*
 * Registre de presence (src/teacher.presence.php) : aucune action ne recharge
 * la page.
 *
 * - Naviguer (annee, cours, retour a la liste, seance) : on demande le
 *   contenu seul (?fragment=1), on remplace la vue (#prVue) et l'adresse
 *   suit (history.pushState) ; Precedent / Suivant du navigateur marchent.
 * - Ecrire (seances prevues, nouvelle seance, heures de la seance, appel,
 *   justificatif, « non pointes presents », suppression) : l'ecriture part a
 *   app/.presence/enregistrer.php, puis seules les zones touchees sont
 *   redemandees et remplacees. Le champ en cours d'edition n'est jamais
 *   remplace ; le focus revient la ou il etait.
 *
 * Tous les ecouteurs sont poses sur #presenceApp : ce qui est remplace n'a
 * pas a etre rebranche. Si un chargement echoue (session expiree, reseau),
 * on retombe sur la navigation classique.
 *
 * SANS RESEAU (src/js/hors-ligne.js, /sw.js) :
 * - Les pages deja ouvertes (et celles que le script precharge a l'ouverture
 *   de la liste : les cours, leurs dernieres seances) reviennent de la copie
 *   gardee sur l'appareil.
 * - Chaque ecriture que le reseau ne laisse pas partir est rangee dans la file
 *   de l'appareil et part toute seule au retour du reseau. En attendant, la
 *   ligne s'affiche comme si c'etait fait, marquee « en attente » (barre
 *   orangee) ; les comptes du bilan, eux, ne bougent qu'apres l'envoi.
 * - La file est la source de verite de ce qui est en attente : apres chaque
 *   navigation ou rafraichissement, reappliquerFile() la repose sur la page
 *   (une copie ancienne ne fait donc pas « revenir en arriere » un pointage).
 * - Une seance peut etre ouverte sans reseau : l'appel est copie du modele
 *   (#prModeleAppel : les etudiants du cours), elle porte un identifiant
 *   provisoire « local-... » remplace par le vrai quand le serveur la cree.
 */
(function () {
    'use strict';

    var app = document.getElementById('presenceApp');
    if (!app || !window.fetch || !window.history || !history.pushState) { return; }

    var URL_SAVE = APP_BASE + '/app/.presence/enregistrer.php';
    var toast = document.getElementById('toast');
    var annonceSr = document.getElementById('prAnnonce');
    var finToast = 0;
    var sequence = 0;        // seule la derniere navigation s'affiche
    var ecrituresEnCours = 0;
    var attenteRafraichir = 0;
    var LOCAL = /^local-/;   // identifiant provisoire d'une seance ouverte sans reseau

    /* Les memes seuils que data/presence.php (presenceCategorie). */
    var RETARD_TOLERE = 5;
    var RETARD_ABSENCE = 10;

    /* Zones du registre que les ecritures font changer. */
    var ZONES_BILAN = ['prEntete', 'prAlerte', 'prSeances', 'bilan'];

    function vue() { return document.getElementById('prVue'); }
    function donnee(nom) { var v = vue(); return v ? v.getAttribute('data-' + nom) : null; }
    function hl() { var h = window.HorsLigne; return h && h.actif ? h : null; }
    function sansReseau() { var h = hl(); return !!h && !h.enLigne(); }

    /* Les messages sont les toasts de l'application (init/toast.php), comme
       partout ailleurs ; le texte est echappe, il peut venir du serveur. */
    function annoncer(texte, type, duree) {
        var sur = String(texte == null ? '' : texte).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
        if (window.Toast && typeof window.Toast.show === 'function') {
            window.Toast.show(sur, type || 'success', { duration: duree || (type === 'error' ? 6000 : 3500) });
            return;
        }
        if (toast) {   // repli : le bandeau de la page
            toast.textContent = texte;
            toast.classList.add('est-visible');
            clearTimeout(finToast);
            finToast = setTimeout(function () { toast.classList.remove('est-visible'); }, duree || 3200);
        }
    }

    /* ------------------------------------------------------------ adresses */

    function adresse(href) {
        var u = new URL(href, location.href);
        u.hash = '';
        return u;
    }
    function adresseFragment(u) {
        var f = new URL(u.href);
        f.searchParams.set('fragment', '1');
        return f.pathname + f.search;
    }

    /* Le contenu de la page pour une adresse, sans la coque. Sans reseau, si le morceau n'a pas ete
       garde, la vue se lit dans la copie de la page complete (/sw.js) : #prVue y est aussi. */
    function charger(u) {
        function lire(url, entetes) {
            return fetch(url, { credentials: 'same-origin', headers: entetes }).then(function (reponse) {
                // Une redirection, c'est la page de connexion : session expiree.
                if (reponse.redirected || !reponse.ok) { throw new Error('contenu'); }
                return reponse.text();
            });
        }
        return lire(adresseFragment(u), { 'X-Requested-With': 'XMLHttpRequest' }).catch(function (err) {
            if (!sansReseau()) { throw err; }
            return lire(u.pathname + u.search, {});
        }).then(function (html) {
            var modele = document.createElement('template');
            modele.innerHTML = html;
            return modele.content;
        });
    }

    /* ---------------------------------------------------------- navigation */

    /**
     * Affiche une autre vue.
     * options.ancre : id a ramener dans l'ecran (« appel ») ;
     * options.enHaut : remonter en haut de la page (autre cours, autre annee) ;
     * options.depuisHistorique : Precedent / Suivant, ne pas empiler l'adresse.
     */
    function aller(href, options) {
        options = options || {};
        var u = adresse(href);
        var jeton = ++sequence;
        app.setAttribute('aria-busy', 'true');

        return charger(u).then(function (contenu) {
            if (jeton !== sequence) { return; }
            var nouvelle = contenu.getElementById('prVue');
            if (!nouvelle || !vue()) { throw new Error('vue'); }
            vue().replaceWith(nouvelle);
            app.removeAttribute('aria-busy');
            if (!options.depuisHistorique) {
                history.pushState({ presence: true }, '', u.pathname + u.search);
            }
            reappliquerFile();
            if (donnee('vue') === 'liste') { setTimeout(prefetch, 1500); }

            if (options.enHaut) {
                window.scrollTo(0, 0);
                var titre = nouvelle.querySelector('h1');
                if (titre) {
                    titre.setAttribute('tabindex', '-1');
                    titre.focus({ preventScroll: true });
                }
            } else if (options.ancre) {
                var cible = document.getElementById(options.ancre);
                if (cible) {
                    var haut = cible.getBoundingClientRect().top;
                    if (haut < 0 || haut > window.innerHeight * 0.6) { cible.scrollIntoView({ block: 'start' }); }
                    cible.setAttribute('tabindex', '-1');
                    cible.focus({ preventScroll: true });
                }
            }
            var h1 = nouvelle.querySelector('h1');
            if (annonceSr && h1) { annonceSr.textContent = h1.textContent.trim(); }
        }).catch(function () {
            if (jeton !== sequence) { return; }
            if (sansReseau()) {   // rien de garde pour cette page : on reste ou on est, sans perdre la saisie
                app.removeAttribute('aria-busy');
                annoncer('Cette page n\'a pas été gardée sur l\'appareil : elle sera disponible avec le réseau.', 'warning', 5000);
                return;
            }
            location.href = u.pathname + u.search;
        });
    }

    /**
     * Remplace quelques zones par leur etat a jour, sans toucher au reste.
     * Le focus revient sur l'element qu'il occupait, s'il a un id.
     * Sans reseau, ou dans une seance ouverte sans reseau (le serveur ne la connait
     * pas encore), la page garde son etat : une copie ancienne l'effacerait.
     */
    function rafraichir(ids) {
        if (sansReseau() || LOCAL.test(donnee('seance') || '')) { return Promise.resolve(); }
        var u = adresse(location.href);
        return charger(u).then(function (contenu) {
            ids.forEach(function (id) {
                var ancien = document.getElementById(id);
                var neuf = contenu.getElementById(id);
                if (!ancien || !neuf) { return; }
                var actif = document.activeElement;
                var focusId = ancien.contains(actif) && actif.id ? actif.id : null;
                ancien.replaceWith(neuf);
                if (focusId) {
                    var retour = document.getElementById(focusId);
                    if (retour) { retour.focus({ preventScroll: true }); }
                }
            });
            reappliquerFile();
        }).catch(function () { /* l'affichage garde son etat ; la prochaine action le corrigera */ });
    }

    /* Apres une rafale de pointages : une seule mise a jour des comptes. */
    function rafraichirBientot() {
        clearTimeout(attenteRafraichir);
        attenteRafraichir = setTimeout(function () {
            if (ecrituresEnCours > 0) { rafraichirBientot(); return; }
            rafraichir(ZONES_BILAN);
        }, 700);
    }

    /* ------------------------------------------------------------ ecritures */

    /* Envoi sans le module hors ligne (navigateur trop ancien) : comme avant. */
    function envoiDirect(champs) {
        var corps = new FormData();
        corps.append('csrf_token', CSRF_TOKEN);
        champs.forEach(function (p) { corps.append(p[0], p[1]); });
        return fetch(URL_SAVE, {
            method: 'POST', body: corps, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok && d.ok, data: d }; });
        });
    }

    /**
     * Une ecriture : elle part tout de suite, ou, si le reseau manque, elle est rangee sur
     * l'appareil et partira au retour du reseau. Rend { ok, enFile, data } : quand enFile est
     * vrai, il n'y a pas encore de reponse du serveur (data vide) ; un refus est annonce ici.
     * options : produit (identifiant provisoire de la seance creee), acquis404 (un « introuvable »
     * vaut « deja fait » : suppression), sansSeance.
     */
    function envoyer(action, donnees, libelle, options) {
        options = options || {};
        var h = hl();
        var seance = options.sansSeance ? null : donnee('seance');
        ecrituresEnCours++;
        /* Une seance ouverte sans reseau et deja creee depuis : on parle de la vraie. */
        var preparer = (seance && LOCAL.test(seance) && h)
            ? h.alias(seance).then(function (reel) {
                if (reel !== seance && vue() && donnee('seance') === seance) { vue().setAttribute('data-seance', reel); }
                return reel;
            })
            : Promise.resolve(seance);

        return preparer.then(function (s) {
            var champs = [['action', action], ['feuille_id', donnee('feuille') || '0']];
            var fileSeulement = false;
            if (s && s !== '0') {
                champs.push(['seance_id', s]);
                fileSeulement = LOCAL.test(s);   // pas encore connue du serveur : derriere sa creation
            }
            Object.keys(donnees || {}).forEach(function (k) { champs.push([k, donnees[k]]); });
            if (h) {
                return h.envoyer({ module: 'presence', url: URL_SAVE, champs: champs, libelle: libelle || '',
                    produit: options.produit, acquis404: options.acquis404, fileSeulement: fileSeulement });
            }
            return envoiDirect(champs);
        }).then(function (res) {
            if (!res.ok && !res.enFile) { annoncer((res.data && res.data.message) || 'Enregistrement refusé.', 'error'); }
            return res;
        }).catch(function () {
            annoncer('Connexion perdue : rien n\'a été enregistré.', 'error');
            return { ok: false, data: {} };
        }).finally(function () { ecrituresEnCours--; });
    }

    /* Rien n'est parti : on le dit une fois, simplement. */
    var dejaDit = 0;
    function annoncerEnFile() {
        if (Date.now() - dejaDit < 15000) { return; }
        dejaDit = Date.now();
        var h = hl();
        var quand = h && h.session && !h.session() ? 'après votre reconnexion' : 'dès que le réseau revient';
        annoncer('Enregistré sur cet appareil : ça partira ' + quand + '.', 'info', 4500);
    }

    /* Le bilan d'un etudiant, dans sa rangee d'appel et dans le tableau. */
    function peindreBilan(sid, b) {
        var mini = document.querySelector('.pr-appel[data-student="' + CSS.escape(sid) + '"] .js-bilan');
        if (mini) {
            mini.textContent = b.comptees + (b.permises !== null ? ' / ' + b.permises : '') + ' abs.';
            mini.style.color = b.couleur;
            mini.title = b.libelle;
        }
        var ligne = document.querySelector('tr[data-bilan="' + CSS.escape(sid) + '"]');
        if (!ligne) { return; }
        ligne.querySelector('.js-b-presences').textContent = b.presences;
        ligne.querySelector('.js-b-retards').textContent = b.retards;
        ligne.querySelector('.js-b-absences').textContent = b.absences;
        ligne.querySelector('.js-b-comptees').innerHTML = '<b>' + b.comptees + '</b>' + (b.permises !== null ? ' / ' + b.permises : '');
        ligne.querySelector('.js-b-taux').textContent = b.taux !== null ? String(b.taux).replace('.', ',') + ' %' : '—';
        var etiquette = ligne.querySelector('.js-b-etat .pr-etiquette');
        etiquette.style.background = b.couleur + '22';
        etiquette.style.color = b.couleur;
        etiquette.style.borderColor = b.couleur + '55';
        etiquette.firstElementChild.className = b.icone;
        etiquette.lastChild.textContent = b.libelle;
    }

    /* La categorie que le serveur donnerait (data/presence.php, presenceCategorie). */
    function categorieLocale(statut, minutes) {
        if (statut === 'present') { return 'present'; }
        if (statut === 'absent') { return 'absence'; }
        if (statut === 'retard') {
            var m = parseInt(minutes, 10);
            if (isNaN(m)) { m = RETARD_TOLERE; }
            return m >= RETARD_ABSENCE ? 'absence' : (m >= RETARD_TOLERE ? 'retard' : 'present');
        }
        return null;
    }

    /* L'etat d'une rangee d'appel : boutons, minutes, couleur. Rend la categorie. */
    function peindrePointage(rangee, statut, minutes) {
        rangee.querySelectorAll('.pr-choix button').forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-statut') === statut ? 'true' : 'false');
        });
        rangee.querySelector('.pr-minutes').hidden = statut !== 'retard';
        if (statut === 'retard' && minutes !== undefined && minutes !== '') {
            var champ = rangee.querySelector('.js-minutes');
            if (champ && document.activeElement !== champ) { champ.value = minutes; }
        }
        rangee.classList.remove('est-pointe-present', 'est-pointe-retard', 'est-pointe-absence');
        var cat = categorieLocale(statut, minutes);
        if (cat) { rangee.classList.add('est-pointe-' + cat); }
        return cat;
    }

    function marquerEnAttente(element, oui) {
        if (!element) { return; }
        element.classList.toggle('est-en-attente', oui);
        if (oui) { element.setAttribute('data-attente', '1'); } else { element.removeAttribute('data-attente'); }
    }

    var LIBELLE_STATUT = { present: 'présent', retard: 'en retard', absent: 'absent', '': 'non pointé' };

    function pointer(rangee, statut) {
        var sid = rangee.getAttribute('data-student');
        var donnees = { student_id: sid, statut: statut };
        if (statut === 'retard') { donnees.minutes_retard = rangee.querySelector('.js-minutes').value; }
        var nom = rangee.querySelector('.pr-appel__nom').textContent.trim();
        envoyer('pointer', donnees, nom + ' : ' + LIBELLE_STATUT[statut]).then(function (res) {
            if (!res.ok) { return; }
            if (res.enFile) {   // le reseau manque : on affiche comme si c'etait fait, la ligne reste « en attente »
                peindrePointage(rangee, statut, donnees.minutes_retard);
                marquerEnAttente(rangee, true);
                rangee.classList.remove('pr-sauve');
                void rangee.offsetWidth;
                rangee.classList.add('pr-sauve');
                annoncerEnFile();
                return;
            }
            var d = res.data;
            peindrePointage(rangee, statut, donnees.minutes_retard);
            marquerEnAttente(rangee, false);
            rangee.classList.remove('est-pointe-present', 'est-pointe-retard', 'est-pointe-absence');
            if (d.categorie) { rangee.classList.add('est-pointe-' + d.categorie); }
            rangee.classList.remove('pr-sauve');
            void rangee.offsetWidth;
            rangee.classList.add('pr-sauve');
            peindreBilan(sid, d.bilan);

            if (d.bilan.vient_detre_signale) {
                annoncer(nom + ' dépasse 15 % d\'absences et doit quitter le cours. Le registrariat en est informé.', 'warning', 8000);
            } else if (statut === 'retard' && d.categorie === 'absence') {
                annoncer('Retard de 10 minutes ou plus : compté comme une absence (§476).', 'info');
            }
            rafraichirBientot();
        });
    }

    /* ------------------------------------------- la file, reposee sur la page */

    function ligneEtudiant(sid) {
        return document.querySelector('.pr-appel[data-student="' + CSS.escape(sid) + '"]');
    }

    /* Ce qui attend d'etre envoye (src/js/hors-ligne.js) s'affiche par-dessus la page chargee :
       une copie plus ancienne ne fait pas « revenir en arriere » un pointage fait hors ligne. */
    function reappliquerFile() {
        var h = hl();
        if (!h || donnee('vue') !== 'registre') { return Promise.resolve(); }
        var feuille = donnee('feuille');
        var seance = donnee('seance');
        return h.lister().then(function (liste) {
            liste.forEach(function (it) {
                if (it.module !== 'presence' || it.statut !== 'attente') { return; }
                var c = {};
                it.champs.forEach(function (p) { c[p[0]] = p[1]; });
                if (c.feuille_id !== feuille) { return; }
                var ici = c.seance_id === seance;
                var rangee;
                switch (c.action) {
                    case 'pointer':
                        rangee = ici && ligneEtudiant(c.student_id);
                        if (rangee) { peindrePointage(rangee, c.statut, c.minutes_retard); marquerEnAttente(rangee, true); }
                        break;
                    case 'justificatif':
                        rangee = ici && ligneEtudiant(c.student_id);
                        if (rangee) {
                            var sel = rangee.querySelector('.js-justif');
                            if (sel) { sel.value = c.justificatif; }
                            marquerEnAttente(rangee, true);
                        }
                        break;
                    case 'tous_presents':
                        if (ici) {
                            document.querySelectorAll('.pr-appel').forEach(function (r) {
                                if (!/est-pointe-/.test(r.className)) { peindrePointage(r, 'present'); marquerEnAttente(r, true); }
                            });
                        }
                        break;
                    case 'reglage':
                        var champ = document.getElementById('seancesPrevues');
                        if (champ && document.activeElement !== champ) { champ.value = c.seances_prevues; }
                        break;
                    case 'seance_modifier':
                        if (ici) {
                            var f = document.getElementById('formEnseignant');
                            if (f) {
                                if (f.elements.heure_prevue && document.activeElement !== f.elements.heure_prevue) { f.elements.heure_prevue.value = c.heure_prevue || ''; }
                                if (f.elements.arrivee_enseignant && document.activeElement !== f.elements.arrivee_enseignant) { f.elements.arrivee_enseignant.value = c.arrivee_enseignant || ''; }
                                if (f.elements.message_retard) { f.elements.message_retard.checked = !!c.message_retard; }
                            }
                        }
                        break;
                    case 'seance_supprimer':
                        if (c.seance_id) {
                            document.querySelectorAll('a.pr-seance[href*="seance=' + c.seance_id + '"]').forEach(function (a) {
                                a.hidden = true;
                            });
                        }
                        break;
                }
            });
        });
    }

    /* ------------------------------------- une seance ouverte sans reseau */

    /* L'appel vierge : le modele du serveur (aucune seance ouverte) ou l'appel affiche, remis a zero. */
    function modeleAppel() {
        var t = document.getElementById('prModeleAppel');
        if (t && t.content) {
            var depuisModele = t.content.cloneNode(true).querySelector('#appel');
            if (depuisModele) { return depuisModele; }
        }
        var actuel = document.getElementById('appel');
        if (!actuel || !actuel.querySelector('#listeAppel .pr-appel')) { return null; }
        var copie = actuel.cloneNode(true);
        copie.querySelectorAll('.pr-appel').forEach(function (r) {
            r.className = 'pr-appel';
            r.removeAttribute('data-attente');
            r.querySelectorAll('.pr-choix button').forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
            r.querySelector('.pr-minutes').hidden = true;
            var j = r.querySelector('.js-justif');
            if (j) { j.value = ''; }
        });
        var etat = copie.querySelector('#etatEnseignant');
        if (etat) { etat.textContent = ''; }
        return copie;
    }

    function dateLongue(iso) {
        var d = new Date(iso + 'T12:00:00');
        if (isNaN(d.getTime())) { return iso; }
        var t = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        return t.charAt(0).toUpperCase() + t.slice(1);
    }

    /* Affiche la seance que le serveur n'a pas encore ; d : les champs du formulaire « Nouvelle seance ». */
    function ouvrirSeanceLocale(id, d) {
        var section = modeleAppel();
        var ancien = document.getElementById('appel');
        if (!section || !ancien) { return false; }
        var date = dateLongue(d.date_seance);
        var cible = section.querySelector('[data-seance-date]');
        if (cible) { cible.textContent = date; }
        var f = section.querySelector('#formEnseignant');
        if (f) {
            if (f.elements.heure_prevue) { f.elements.heure_prevue.value = d.heure_prevue || ''; }
            if (f.elements.arrivee_enseignant) { f.elements.arrivee_enseignant.value = d.arrivee_enseignant || ''; }
            if (f.elements.message_retard) { f.elements.message_retard.checked = !!d.message_retard; }
        }
        ancien.replaceWith(section);
        vue().setAttribute('data-seance', id);

        /* La seance prend sa place dans la liste, marquee « en attente ». */
        var liste = document.getElementById('prSeances');
        if (liste) {
            liste.querySelectorAll('a.pr-seance').forEach(function (a) { a.setAttribute('aria-current', 'false'); });
            var vide = liste.querySelector('p');
            if (vide) { vide.remove(); }
            var lien = document.createElement('a');
            lien.className = 'pr-seance est-en-attente';
            lien.href = '#appel';
            lien.setAttribute('aria-current', 'true');
            lien.setAttribute('data-local', id);
            lien.innerHTML = '<div class="pr-seance__date"></div><div class="pr-seance__detail">Hors ligne · en attente d\'envoi</div>';
            lien.firstChild.textContent = date;
            var entete = liste.querySelector('.pr-entete');
            if (entete && entete.nextSibling) { liste.insertBefore(lien, entete.nextSibling); } else { liste.appendChild(lien); }
        }
        return true;
    }

    /* Retire du serveur ce qui n'y est pas : une seance jamais envoyee disparait avec toute sa file. */
    function abandonnerSeanceLocale(id) {
        return hl().retirer(function (i) {
            return i.module === 'presence' && (
                (i.produit && i.produit.provisoire === id) ||
                i.champs.some(function (p) { return p[0] === 'seance_id' && p[1] === id; }));
        });
    }

    /* -------------------------------------------------------------- clics */

    app.addEventListener('click', function (e) {
        var lien = e.target.closest('a[data-nav]');
        if (lien && app.contains(lien)) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
            e.preventDefault();
            if (lien.classList.contains('pr-seance')) {
                aller(lien.getAttribute('href'), { ancre: 'appel' });
            } else {
                aller(lien.getAttribute('href'), { enHaut: true });
            }
            return;
        }

        /* La seance ouverte sans reseau : le lien ne mene nulle part, l'appel est deja la. */
        var local = e.target.closest('a.pr-seance[data-local]');
        if (local) {
            e.preventDefault();
            var appel = document.getElementById('appel');
            if (appel) { appel.scrollIntoView({ block: 'start' }); }
            return;
        }

        var choix = e.target.closest('.pr-choix button');
        if (choix) {
            var rangee = choix.closest('.pr-appel');
            // Un second clic sur l'etat choisi le retire : la ligne redevient « non pointee ».
            pointer(rangee, choix.getAttribute('aria-pressed') === 'true' ? '' : choix.getAttribute('data-statut'));
            return;
        }

        if (e.target.closest('#btnTousPresents')) {
            envoyer('tous_presents', {}, 'Tous les non pointés présents').then(function (res) {
                if (!res.ok) { return; }
                if (res.enFile) {
                    document.querySelectorAll('.pr-appel').forEach(function (r) {
                        if (!/est-pointe-/.test(r.className)) { peindrePointage(r, 'present'); marquerEnAttente(r, true); }
                    });
                    annoncerEnFile();
                    return;
                }
                annoncer(res.data.message);
                rafraichir(['prAlerte', 'prSeances', 'listeAppel', 'bilan']);
            });
            return;
        }

        if (e.target.closest('#btnSupprimer')) {
            var base = donnee('base');
            var seanceCourante = donnee('seance') || '';
            var demande = (window.Toast && typeof window.Toast.confirm === 'function')
                ? window.Toast.confirm('Tous ses pointages seront supprimés et les bilans recalculés.',
                                       { title: 'Supprimer cette séance ?', confirmText: 'Supprimer la séance', danger: true })
                : Promise.resolve(false);   // sans modale, on ne supprime pas
            demande.then(function (ok) {
                if (!ok) { return; }
                var lib = 'Suppression de la séance du ' + ((document.querySelector('[data-seance-date]') || {}).textContent || '').trim();
                /* Une seance que le serveur ne connait pas encore : il suffit d'oublier ce qui la concerne. */
                if (LOCAL.test(seanceCourante) && hl()) {
                    hl().alias(seanceCourante).then(function (reel) {
                        if (reel !== seanceCourante) { return false; }
                        return abandonnerSeanceLocale(seanceCourante).then(function () { return true; });
                    }).then(function (abandonnee) {
                        if (abandonnee === false) { return envoyerSuppression(base, lib); }
                        vue().setAttribute('data-seance', '0');
                        aller(base, { ancre: 'prSeances' }).then(function () { annoncer('Séance supprimée.'); });
                    });
                    return;
                }
                envoyerSuppression(base, lib);
            });
        }
    });

    function envoyerSuppression(base, libelle) {
        var idSupprime = donnee('seance');
        return envoyer('seance_supprimer', {}, libelle, { acquis404: true }).then(function (res) {
            if (!res.ok) { return; }
            aller(base, { ancre: 'prSeances' }).then(function () {
                if (res.enFile) {
                    document.querySelectorAll('a.pr-seance[href*="seance=' + idSupprime + '"]').forEach(function (a) { a.hidden = true; });
                    annoncerEnFile();
                } else {
                    annoncer('Séance supprimée.');
                    garderCours();
                }
            });
        });
    }

    /* ------------------------------------------------------------ saisies */

    app.addEventListener('change', function (e) {
        var cible = e.target;

        if (cible.matches('select[data-nav-annee]')) {
            aller('?annee=' + encodeURIComponent(cible.value), { enHaut: true });
            return;
        }

        if (cible.id === 'seancesPrevues') {
            envoyer('reglage', { seances_prevues: cible.value.trim() }, 'Séances prévues : ' + (cible.value.trim() || 'aucune')).then(function (res) {
                if (!res.ok) { return; }
                if (res.enFile) { annoncerEnFile(); return; }
                annoncer('Séances prévues enregistrées.');
                rafraichir(['prEntete', 'prAlerte', 'absencesPermises', 'prSeances', 'listeAppel', 'bilan']);
            });
            return;
        }

        var formEnseignant = cible.closest('#formEnseignant');
        if (formEnseignant) {
            var d = {};
            new FormData(formEnseignant).forEach(function (v, k) { d[k] = v; });
            if (!('message_retard' in d)) { d.message_retard = ''; }
            envoyer('seance_modifier', d, 'Heures de la séance du ' + ((document.querySelector('[data-seance-date]') || {}).textContent || '').trim()).then(function (res) {
                if (!res.ok) { return; }
                if (res.enFile) { annoncerEnFile(); return; }
                annoncer(res.data.sans_penalite
                    ? 'Séance enregistrée : sans pénalité pour les étudiants (§474).'
                    : 'Séance enregistrée.');
                rafraichir(['prAlerte', 'prSeances', 'etatEnseignant', 'listeAppel', 'bilan']);
            });
            return;
        }

        if (cible.classList.contains('js-minutes')) {
            pointer(cible.closest('.pr-appel'), 'retard');
            return;
        }

        if (cible.classList.contains('js-justif')) {
            var rangeeJ = cible.closest('.pr-appel');
            var nomJ = rangeeJ.querySelector('.pr-appel__nom').textContent.trim();
            envoyer('justificatif', {
                student_id: rangeeJ.getAttribute('data-student'),
                justificatif: cible.value
            }, 'Justificatif de ' + nomJ).then(function (res) {
                if (!res.ok) { return; }
                if (res.enFile) { marquerEnAttente(rangeeJ, true); annoncerEnFile(); return; }
                annoncer('Justificatif enregistré.');
            });
        }
    });

    /* ---------------------------------------------------------- formulaires */

    app.addEventListener('submit', function (e) {
        var form = e.target;
        e.preventDefault();   // aucun formulaire de la page ne recharge
        if (form.id !== 'formSeance') { return; }

        var d = {};
        new FormData(form).forEach(function (v, k) { d[k] = v; });
        var bouton = form.querySelector('button[type=submit]');
        bouton.disabled = true;
        var base = donnee('base');
        var idLocal = 'local-' + Date.now().toString(36);
        envoyer('seance_creer', d, 'Ouverture de la séance du ' + dateLongue(d.date_seance), {
            sansSeance: true,
            produit: { champ: 'seance_id', provisoire: idLocal, reponse: 'seance_id' }
        }).then(function (res) {
            bouton.disabled = false;
            if (!res.ok) { return; }
            if (res.enFile) {
                if (ouvrirSeanceLocale(idLocal, d)) {
                    annoncer('Séance ouverte sur cet appareil : faites l\'appel, tout partira au retour du réseau.', 'info', 5000);
                    var appel = document.getElementById('appel');
                    if (appel) { appel.scrollIntoView({ block: 'start' }); }
                } else {
                    annoncer('Séance enregistrée sur cet appareil : ouvrez-la au retour du réseau.', 'warning', 6000);
                }
                return;
            }
            aller(base + '&seance=' + res.data.seance_id, { ancre: 'appel' }).then(function () {
                annoncer(res.data.existait ? 'Cette séance existait déjà : elle est ouverte.' : 'Séance ouverte : faites l\'appel.');
                garderCours();
            });
        });
    });

    /* ------------------------------------------------- retour du reseau */

    /* La file vient de partir (src/js/hors-ligne.js) : la page reprend l'etat du serveur. */
    function apresEnvoi() {
        var h = hl();
        if (!h || donnee('vue') !== 'registre') { return; }
        h.lister().then(function (liste) {
            if (liste.some(function (i) { return i.module === 'presence' && i.statut === 'attente'; })) { return; }
            var seance = donnee('seance') || '';
            if (LOCAL.test(seance)) {
                h.alias(seance).then(function (reel) {
                    if (reel === seance) { return; }   // la creation n'a pas abouti : le message de refus le dit
                    aller(donnee('base') + '&seance=' + reel, { ancre: 'appel' });
                });
                return;
            }
            document.querySelectorAll('[data-attente]').forEach(function (el) { marquerEnAttente(el, false); });
            rafraichir(ZONES_BILAN.concat(['listeAppel']));
            garderCours();
        });
    }

    var minuterieEnvoi = 0;
    document.addEventListener('horsligne', function (e) {
        var d = e.detail || {};
        if (d.type === 'refuse' && d.item && d.item.module === 'presence') {
            annoncer((d.item.libelle ? d.item.libelle + ' : ' : '') + (d.item.message || 'refusé par le serveur') + ' Voir la pastille en bas à gauche.', 'error', 9000);
        }
        if (d.type === 'fini' || d.type === 'envoye') {
            clearTimeout(minuterieEnvoi);
            minuterieEnvoi = setTimeout(apresEnvoi, d.type === 'fini' ? 200 : 1500);
        }
    });

    /* ----------------------------------------------- avant la coupure */

    /* Garde a jour sur l'appareil la page « par defaut » du cours (la liste de ses seances) : sans cela,
       la copie d'avant la creation d'une seance ne la montrerait pas une fois hors ligne. */
    function garderCours() {
        var base = donnee('base');
        if (sansReseau() || donnee('vue') !== 'registre' || !base) { return; }
        var u = adresse(base);
        fetch(adresseFragment(u), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
        fetch(u.pathname + u.search, { credentials: 'same-origin' }).then(function (r) {
            return r.ok && !r.redirected ? r.text() : '';
        }).then(function (html) {
            var h = hl();
            if (html && h) { h.noterPage(u.pathname + u.search, titrePage(new DOMParser().parseFromString(html, 'text/html'))); }
        }).catch(function () {});
    }

    /* Le nom d'une page dans la liste « disponible sans connexion » : le cours, l'annee ou la date de la seance. */
    function titrePage(racine) {
        var h1 = racine.querySelector('h1');
        var t = h1 ? h1.textContent.replace(/\s+/g, ' ').trim() : 'Présences';
        var an = racine.querySelector('select[data-nav-annee]');
        if (an && an.selectedOptions && an.selectedOptions[0]) { t += ' — ' + an.selectedOptions[0].textContent.trim(); }
        var jour = racine.querySelector('[data-seance-date]');
        if (jour) { t += ' — séance du ' + jour.textContent.trim(); }
        return t;
    }

    /* A l'ouverture de la liste (reseau present) : les cours et leurs dernieres seances sont gardes
       sur l'appareil, pour qu'on puisse les ouvrir et faire l'appel sans reseau. Au plus toutes les 20 min. */
    function prefetch() {
        var h = hl();
        if (!h || !h.enLigne() || donnee('vue') !== 'liste') { return; }
        var cle = 'registrar-prefetch-presence-' + h.utilisateur;
        try {
            if (Date.now() - parseInt(localStorage.getItem(cle) || '0', 10) < 20 * 60 * 1000) { return; }
            localStorage.setItem(cle, String(Date.now()));
        } catch (err) { /* stockage refuse : on precharge a chaque ouverture */ }

        var file = [];
        vue().querySelectorAll('a[data-nav][href*="cours_id="]').forEach(function (a) {
            if (file.length < 8) { file.push({ u: adresse(a.getAttribute('href')), niveau: 0 }); }
        });
        var vus = {};
        function suivant() {
            var tache = file.shift();
            if (!tache) { return Promise.resolve(); }
            var chemin = tache.u.pathname + tache.u.search;
            if (vus[chemin]) { return suivant(); }
            vus[chemin] = true;
            return fetch(chemin, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok || r.redirected) { throw new Error('page'); }
                return r.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                h.noterPage(chemin, titrePage(doc));
                if (tache.niveau === 0) {
                    var retour = doc.querySelector('#prEntete a[data-nav]');   // « Mes cours » de cette annee
                    if (retour) { file.push({ u: adresse(retour.getAttribute('href')), niveau: 1 }); }
                    var n = 0;
                    doc.querySelectorAll('a.pr-seance[href]').forEach(function (a) {
                        if (n++ < 2) { file.push({ u: adresse(a.getAttribute('href')), niveau: 1 }); }
                    });
                }
            }).catch(function () { /* cette page-la ne sera pas disponible hors ligne */ }).then(suivant);
        }
        Promise.all([suivant(), suivant()]);
    }

    /* ------------------------------------------------------------ historique */

    history.replaceState({ presence: true }, '', location.href);
    window.addEventListener('popstate', function () {
        aller(location.href, { depuisHistorique: true });
    });

    /* Le module hors ligne est charge en differe (init/pwa.php) : on attend la fin du chargement. */
    function demarrer() {
        var h = hl();
        if (h && vue() && donnee('vue') !== 'erreur') { h.noterPage(location.pathname + location.search, titrePage(document)); }
        reappliquerFile();
        setTimeout(prefetch, 2500);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', demarrer); } else { demarrer(); }
})();
