/* =============================================================================
   Reglages de la vitrine — enregistrement sans rechargement

   Le formulaire compte trente-sept champs sur huit groupes. Soumis a l'ancienne,
   il rechargeait la page : retour en haut, groupe replie, et l'on cherchait a
   nouveau le champ qu'on venait de corriger. Ici la sauvegarde part en
   arriere-plan et seule la reponse bouge a l'ecran — le toast, les apercus
   d'images et les lignes « Modifié le … ».

   Le rideau de transition ne joue pas non plus : preventDefault() ci-dessous
   suffit a l'ecarter (src/js/transitions.js verifie defaultPrevented), et
   data-pt-skip sur le formulaire le dit en plus explicitement dans le
   balisage, pour qui lit le HTML sans lire ce fichier.

   Sans JavaScript, rien de tout ceci ne s'execute et le formulaire se soumet
   normalement : src/settings.vitrine.php garde son traitement de repli.
   ========================================================================== */
(function () {
    'use strict';

    var form = document.querySelector('form.rv-corps');
    if (!form) { return; }

    var bouton = form.querySelector('.rv-enregistrer');
    var base = (typeof window.APP_BASE === 'string') ? window.APP_BASE : '';
    var CIBLE = base + '/app/vitrine-config.save.php';

    var enCours = false;

    function dire(message, type, duree) {
        if (typeof window.Toast === 'undefined') { return; }
        Toast.show(message, type, { duration: duree });
    }

    /** Type renvoye par le serveur -> type de toast. */
    function versTypeToast(t) {
        if (t === 'ok') { return 'success'; }
        if (t === 'partiel') { return 'warning'; }
        return 'error';
    }

    /* Un nouvel apercu doit contourner le cache : le nom du fichier ne change
       pas d'un televersement a l'autre (il est deduit de la cle), donc sans
       parametre l'ancienne image resterait affichee. */
    function rafraichirApercu(champ, nom) {
        var entree = form.querySelector('#' + CSS.escape(champ));
        if (!entree) { return; }
        var bloc = entree.closest('.rv-image');
        if (!bloc) { return; }
        var cadre = bloc.querySelector('.rv-image__apercu');
        if (!cadre) { return; }

        var img = cadre.querySelector('img');
        if (!img) {
            cadre.innerHTML = '';
            img = document.createElement('img');
            img.alt = '';
            cadre.appendChild(img);
        }
        img.src = base + '/file/vitrine/' + nom + '?v=' + Date.now();

        /* Le champ fichier est vide apres coup : sinon un second enregistrement
           renverrait la meme image et la re-encoderait pour rien. */
        entree.value = '';
    }

    function rafraichirTrace(champ, texte) {
        var entree = form.querySelector('#' + CSS.escape(champ));
        if (!entree) { return; }
        var cellule = entree.closest('.rv-champ');
        if (!cellule) { return; }

        var p = cellule.querySelector('.rv-trace');
        if (!p) {
            p = document.createElement('p');
            p.className = 'rv-trace';
            cellule.appendChild(p);
        }
        p.textContent = texte;
        if (typeof window.ptBump === 'function') { window.ptBump(p); }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (enCours) { return; }
        enCours = true;

        var libelle = bouton ? bouton.textContent : '';
        if (bouton) {
            bouton.disabled = true;
            bouton.textContent = 'Enregistrement…';
        }

        function rendreLeBouton() {
            enCours = false;
            if (bouton) {
                bouton.disabled = false;
                bouton.textContent = libelle;
            }
        }

        fetch(CIBLE, {
            method: 'POST',
            body: new FormData(form),
            /* En-tete lu par data/guard.ajax.php : il distingue un appel de
               donnees d'une navigation, et fait repondre 401 en JSON plutot
               qu'une redirection vers la page de connexion. */
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (reponse) {
            /* 401 et 403 sont deja pris en charge globalement par
               src/js/session.js : il annonce la session expiree et renvoie a la
               connexion. On ne double pas son message. */
            if (reponse.status === 401 || reponse.status === 403) {
                return null;
            }
            return reponse.json().catch(function () {
                throw new Error('reponse illisible');
            });
        })
        .then(function (r) {
            if (r === null) { return; }

            dire(r.message || 'Enregistrement terminé.',
                 versTypeToast(r.type),
                 r.type === 'ok' ? 4000 : 9000);

            if (r.images) {
                Object.keys(r.images).forEach(function (champ) {
                    rafraichirApercu(champ, r.images[champ]);
                });
            }
            if (r.traces) {
                Object.keys(r.traces).forEach(function (champ) {
                    rafraichirTrace(champ, r.traces[champ]);
                });
            }

            /* Jeton renouvele : il expire au bout d'une heure et la page ne se
               recharge plus jamais d'elle-meme. Sans cette mise a jour, une
               longue seance d'edition finirait par se faire refuser. */
            if (r.csrf) {
                var jeton = form.querySelector('input[name="csrf_token"]');
                if (jeton) { jeton.value = r.csrf; }
            }
        })
        .catch(function () {
            dire('Le serveur n’a pas répondu. Vos modifications ne sont pas enregistrées — '
               + 'vérifiez votre connexion et réessayez.', 'error', 10000);
        })
        .then(rendreLeBouton);
    });
})();
