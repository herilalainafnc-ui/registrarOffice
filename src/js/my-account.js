/* =============================================================================
   Mon compte — interactions

   Chaque rangée s'ouvre sur place et s'enregistre seule, sans rechargement.
   Une seule à la fois : deux volets ouverts sur un formulaire de compte, c'est
   deux brouillons concurrents dont on ne sait plus lequel on est en train de
   remplir.

   Le jeton anti-CSRF et l'action accompagnent chaque envoi. Le point d'entrée
   n'accepte aucun identifiant d'utilisateur : il agit toujours sur la session,
   donc rien ici n'a besoin de transporter « qui ».
   ========================================================================== */
(function () {
    'use strict';

    var racine = document.getElementById('mon-compte');
    if (!racine) { return; }

    var CIBLE = (typeof APP_BASE === 'string' ? APP_BASE : '') + '/app/.user/mon-compte.save.php';

    function dire(message, type) {
        if (window.Toast && typeof Toast.show === 'function') {
            Toast.show(message, type, { duration: type === 'error' ? 9000 : 4000 });
            return;
        }
        if (window.console) { console.warn(message); }
    }

    /* ---------------------------------------------------------------------
       Ouverture des rangées
       --------------------------------------------------------------------- */
    function fermer(rangee) {
        rangee.classList.remove('est-ouverte');
        var bouton = rangee.querySelector('.mc-rangee__resume');
        if (bouton) { bouton.setAttribute('aria-expanded', 'false'); }
        var action = rangee.querySelector('.mc-rangee__action');
        if (action) { action.textContent = 'Modifier'; }
    }

    function ouvrir(rangee) {
        racine.querySelectorAll('.mc-rangee.est-ouverte').forEach(function (autre) {
            if (autre !== rangee) { fermer(autre); }
        });

        rangee.classList.add('est-ouverte');
        var bouton = rangee.querySelector('.mc-rangee__resume');
        if (bouton) { bouton.setAttribute('aria-expanded', 'true'); }
        var action = rangee.querySelector('.mc-rangee__action');
        if (action) { action.textContent = 'Annuler'; }

        /* Le focus part sur le premier champ : ouvrir un volet sans y conduire
           le curseur oblige à re-viser à la souris. Le délai laisse
           l'animation démarrer, sinon le navigateur défile vers un élément
           encore replié. */
        var premier = rangee.querySelector('input:not([type="hidden"]), select');
        if (premier) {
            window.setTimeout(function () { premier.focus({ preventScroll: true }); }, 180);
        }
    }

    racine.addEventListener('click', function (e) {
        var bouton = e.target.closest('.mc-rangee__resume');
        if (!bouton || bouton.classList.contains('mc-rangee__resume--verrouille')) { return; }

        var rangee = bouton.closest('.mc-rangee');
        if (rangee.classList.contains('est-ouverte')) { fermer(rangee); } else { ouvrir(rangee); }
    });

    // « Annuler » dans le volet : on referme et l'on rend au champ sa valeur.
    racine.addEventListener('click', function (e) {
        var annuler = e.target.closest('[data-annuler]');
        if (!annuler) { return; }
        e.preventDefault();
        var rangee = annuler.closest('.mc-rangee');
        var form = rangee.querySelector('form');
        if (form) { form.reset(); effacerErreurs(form); }
        fermer(rangee);
    });

    /* Échap referme le volet ouvert : c'est la sortie attendue de tout ce qui
       s'ouvre. */
    racine.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var rangee = e.target.closest('.mc-rangee.est-ouverte');
        if (!rangee) { return; }
        var form = rangee.querySelector('form');
        if (form) { form.reset(); effacerErreurs(form); }
        fermer(rangee);
        var bouton = rangee.querySelector('.mc-rangee__resume');
        if (bouton) { bouton.focus(); }
    });

    /* ---------------------------------------------------------------------
       Erreurs de champ
       --------------------------------------------------------------------- */
    function effacerErreurs(form) {
        form.querySelectorAll('.mc-champ--faux').forEach(function (c) {
            c.classList.remove('mc-champ--faux');
        });
        form.querySelectorAll('.mc-erreur').forEach(function (m) { m.remove(); });
        form.querySelectorAll('[aria-invalid]').forEach(function (i) {
            i.removeAttribute('aria-invalid');
            i.removeAttribute('aria-describedby');
        });
    }

    function poserErreurs(form, erreurs) {
        effacerErreurs(form);
        var premier = null;

        Object.keys(erreurs || {}).forEach(function (nom) {
            var champ = form.querySelector('[name="' + nom + '"]');
            if (!champ) { return; }
            var bloc = champ.closest('.mc-champ') || champ.parentNode;
            bloc.classList.add('mc-champ--faux');

            var id = 'mc-err-' + nom;
            var msg = document.createElement('span');
            msg.className = 'mc-erreur';
            msg.id = id;
            msg.setAttribute('role', 'alert');
            msg.textContent = erreurs[nom];
            bloc.appendChild(msg);

            champ.setAttribute('aria-invalid', 'true');
            champ.setAttribute('aria-describedby', id);
            if (!premier) { premier = champ; }
        });

        if (premier) { premier.focus({ preventScroll: false }); }
    }

    // La marque s'efface dès que l'on retouche le champ.
    racine.addEventListener('input', function (e) {
        var bloc = e.target.closest('.mc-champ--faux');
        if (!bloc) { return; }
        bloc.classList.remove('mc-champ--faux');
        var msg = bloc.querySelector('.mc-erreur');
        if (msg) { msg.remove(); }
        e.target.removeAttribute('aria-invalid');
    });

    /* ---------------------------------------------------------------------
       Envoi
       --------------------------------------------------------------------- */
    async function envoyer(form, corps) {
        var bouton = form.querySelector('button[type="submit"]');
        var libelle = bouton ? bouton.textContent : '';
        if (bouton) { bouton.disabled = true; bouton.textContent = 'Enregistrement…'; }

        try {
            var reponse = await fetch(CIBLE, {
                method: 'POST',
                body: corps,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            // 401 et 403 sont pris en charge globalement par src/js/session.js.
            if (reponse.status === 401 || reponse.status === 403) {
                var r403 = await reponse.json().catch(function () { return null; });
                // 403 ici n'est pas une session expirée mais un refus de rôle :
                // session.js parlerait de privilèges, ce qui est exact, mais le
                // message du serveur est plus précis.
                if (reponse.status === 403 && r403 && r403.message) { dire(r403.message, 'error'); }
                return null;
            }

            var r = await reponse.json().catch(function () {
                throw new Error('réponse illisible');
            });

            if (r && r.csrf_expire) { dire(r.message, 'error'); return null; }

            if (!r.ok) {
                if (r.erreurs) { poserErreurs(form, r.erreurs); }
                dire(r.message || "L'enregistrement n'a pas abouti.", 'error');
                return null;
            }

            effacerErreurs(form);
            return r;
        } catch (err) {
            dire('Le serveur n’a pas répondu. Rien n’a été enregistré.', 'error');
            return null;
        } finally {
            if (bouton) { bouton.disabled = false; bouton.textContent = libelle; }
        }
    }

    /** Réécrit la valeur affichée dans le résumé d'une rangée. */
    function majResume(rangee, texte) {
        var valeur = rangee.querySelector('.mc-rangee__valeur');
        if (!valeur) { return; }
        if (texte === '' || texte === null || texte === undefined) {
            valeur.textContent = 'Non renseigné';
            valeur.classList.add('mc-rangee__valeur--vide');
        } else {
            valeur.textContent = texte;
            valeur.classList.remove('mc-rangee__valeur--vide');
        }
    }

    /* --- Identité --------------------------------------------------------- */
    var formIdentite = document.getElementById('mc-form-identite');
    if (formIdentite) {
        formIdentite.addEventListener('submit', async function (e) {
            e.preventDefault();
            var r = await envoyer(formIdentite, new FormData(formIdentite));
            if (!r) { return; }

            /* Chaque rangée d'identité relit sa propre valeur : les cinq champs
               vivent dans un seul formulaire, mais s'affichent en cinq
               rangées. */
            racine.querySelectorAll('[data-champ]').forEach(function (rangee) {
                var nom = rangee.dataset.champ;
                if (r.valeurs && Object.prototype.hasOwnProperty.call(r.valeurs, nom)) {
                    majResume(rangee, r.valeurs[nom]);
                }
            });

            var nomTete = document.getElementById('mc-nom-affiche');
            if (nomTete && r.nomAffiche) { nomTete.textContent = r.nomAffiche; }

            racine.querySelectorAll('.mc-rangee.est-ouverte').forEach(fermer);
            dire(r.message, 'success');
        });
    }

    /* --- Photo de profil -------------------------------------------------- */
    var champPhoto = document.getElementById('mc-photo');
    if (champPhoto) {
        champPhoto.addEventListener('change', async function () {
            if (!champPhoto.files || !champPhoto.files.length) { return; }

            var zone = champPhoto.closest('.mc-avatar');
            if (zone) { zone.classList.add('est-occupe'); }

            var corps = new FormData();
            corps.set('action', 'avatar');
            corps.set('csrf_token', champPhoto.dataset.jeton || '');
            corps.set('photo', champPhoto.files[0]);

            try {
                var reponse = await fetch(CIBLE, {
                    method: 'POST', body: corps,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (reponse.status === 401 || reponse.status === 403) { return; }
                var r = await reponse.json();

                if (!r.ok) { dire(r.message || "L'image n'a pas pu être enregistrée.", 'error'); return; }

                /* Le nom du fichier ne change pas d'un envoi à l'autre — il est
                   déduit de l'identifiant du compte — d'où le paramètre de
                   version, sans lequel l'ancienne photo resterait affichée. */
                var url = (typeof APP_BASE === 'string' ? APP_BASE : '')
                        + '/app/photosuser/' + encodeURIComponent(r.photo) + '?v=' + r.version;

                racine.querySelectorAll('[data-avatar]').forEach(function (n) {
                    if (n.tagName === 'IMG') { n.src = url; return; }
                    // Les initiales cèdent la place à la vraie photo.
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Votre photo de profil';
                    img.setAttribute('data-avatar', '');
                    n.replaceWith(img);
                });

                // La barre du haut porte le même avatar.
                document.querySelectorAll('img[alt="User Avatar"]').forEach(function (n) { n.src = url; });

                dire(r.message, 'success');
            } catch (err) {
                dire('Le serveur n’a pas répondu. La photo n’a pas été envoyée.', 'error');
            } finally {
                if (zone) { zone.classList.remove('est-occupe'); }
                champPhoto.value = '';   // pour pouvoir renvoyer le même fichier
            }
        });
    }

    /* --- Mot de passe ----------------------------------------------------- */
    racine.addEventListener('click', function (e) {
        var oeil = e.target.closest('.mc-secret__oeil');
        if (!oeil) { return; }
        var input = oeil.parentNode.querySelector('input');
        if (!input) { return; }
        var cache = input.type === 'password';
        input.type = cache ? 'text' : 'password';
        oeil.setAttribute('aria-label', cache ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        oeil.querySelector('i').className = cache ? 'bi-eye-slash' : 'bi-eye';
    });

    /* Jauge de robustesse : elle explique le refus « au moins 8 caractères »
       avant qu'il ne tombe, plutôt qu'après l'envoi. */
    var nouveau = document.getElementById('mc-nouveau');
    if (nouveau) {
        var jauge = document.getElementById('mc-force-jauge');
        var mot = document.getElementById('mc-force-mot');

        nouveau.addEventListener('input', function () {
            var v = nouveau.value;
            var points = 0;
            if (v.length >= 8) { points++; }
            if (v.length >= 12) { points++; }
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) { points++; }
            if (/[0-9]/.test(v)) { points++; }
            if (/[^A-Za-z0-9]/.test(v)) { points++; }

            var echelle = [
                { l: 0,  t: '',                          c: '#f87171' },
                { l: 20, t: 'Trop court — 8 caractères minimum', c: '#f87171' },
                { l: 40, t: 'Faible',                    c: '#fb923c' },
                { l: 60, t: 'Correct',                   c: '#fbbf24' },
                { l: 80, t: 'Bon',                       c: '#34d399' },
                { l: 100, t: 'Excellent',                c: '#34d399' }
            ];
            var e2 = echelle[Math.min(points, 5)];
            if (v.length > 0 && v.length < 8) { e2 = echelle[1]; }

            if (jauge) { jauge.style.width = (v.length ? e2.l : 0) + '%'; jauge.style.background = e2.c; }
            if (mot) { mot.textContent = v.length ? e2.t : 'Au moins 8 caractères.'; }
        });
    }

    var formMdp = document.getElementById('mc-form-motdepasse');
    if (formMdp) {
        formMdp.addEventListener('submit', async function (e) {
            e.preventDefault();
            var r = await envoyer(formMdp, new FormData(formMdp));
            if (!r) { return; }

            formMdp.reset();
            if (jauge) { jauge.style.width = '0'; }
            if (mot) { mot.textContent = 'Au moins 8 caractères.'; }

            var quand = document.getElementById('mc-mdp-date');
            if (quand && r.changeLe) { quand.textContent = 'Modifié le ' + r.changeLe; }

            racine.querySelectorAll('.mc-rangee.est-ouverte').forEach(fermer);
            dire(r.message, 'success');
        });
    }

    /* Confirmation en modale (init/toast.php). Sans elle, le role ne change pas. */
    function demanderRole(texte) {
        if (window.Toast && typeof Toast.confirm === 'function') {
            return Toast.confirm(texte, { title: 'Changer de rôle', confirmText: 'Changer mon rôle', danger: true });
        }
        return Promise.resolve(false);
    }

    /* --- Rôle (superadmin seulement) -------------------------------------- */
    var formRole = document.getElementById('mc-form-role');
    if (formRole) {
        formRole.addEventListener('submit', async function (e) {
            e.preventDefault();

            var select = formRole.querySelector('select[name="level"]');
            var choisi = select.options[select.selectedIndex].textContent.trim();

            /* Confirmation : se retrograder ferme des pages auxquelles on avait
               accès une seconde plus tôt. Ce n'est pas réversible sans un autre
               superadmin. */
            if (select.value !== select.dataset.actuel) {
                if (!(await demanderRole('Changer votre rôle en « ' + choisi + ' » ?\n\n'
                    + 'Vos accès seront recalculés immédiatement. Si vous perdez le rôle '
                    + 'de superadmin, vous ne pourrez plus le reprendre vous-même.'))) {
                    return;
                }
            }

            var r = await envoyer(formRole, new FormData(formRole));
            if (!r) { return; }

            dire(r.message, 'success');

            /* Rechargement : le menu et la barre latérale sont rendus par le
               serveur d'après le niveau. Les laisser en place afficherait des
               entrées qui répondent maintenant 403. */
            if (r.rechargerLaPage) {
                window.setTimeout(function () { window.location.reload(); }, 900);
            }
        });
    }
})();
