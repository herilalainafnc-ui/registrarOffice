    <!-- =====================================================================
         Confirmation de pre-inscription.

         Deux declencheurs desormais :

           - l'envoi sans rechargement, cas normal : le script du formulaire
             appelle preadmModale.ouvrir() (voir _contenu-admissions.php) ;
           - la redirection « ?envoye=1 », qui subsiste pour la soumission
             classique — sans JavaScript, ou si fetch() echoue.

         La modale est donc TOUJOURS presente dans la page, masquee par
         defaut, et s'ouvre a la demande. Elle etait auparavant incluse
         seulement quand le parametre etait la, et son script s'ouvrait de
         lui-meme a l'inclusion : rien ne permettait de l'afficher apres coup.

         Le contenu est rendu par le serveur : il reste visible si le script
         ne s'execute pas, la modale se comportant alors comme un encadre.
         ===================================================================== -->
    <style>
        .preadm-modale {
            position: fixed; inset: 0; z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .55);
            -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px);
        }
        .preadm-modale[hidden] { display: none; }
        .preadm-carte {
            position: relative;
            width: 100%; max-width: 460px;
            background: #ffffff;
            border-radius: 18px;
            padding: 36px 32px 28px;
            text-align: center;
            box-shadow: 0 24px 60px -12px rgba(15, 23, 42, .35);
            animation: preadm-entree .22s ease-out;
        }
        @keyframes preadm-entree {
            from { opacity: 0; transform: translateY(10px) scale(.97); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .preadm-carte { animation: none; }
        }
        .preadm-pastille {
            width: 68px; height: 68px; margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 34px; line-height: 1;
            background: #dcfce7; border-radius: 50%;
        }
        .preadm-titre {
            margin: 0 0 12px;
            font-size: 20px; font-weight: 700; color: #14532d;
        }
        .preadm-texte {
            margin: 0 0 24px;
            font-size: 15px; line-height: 1.6; color: #3f6b52;
        }
        .preadm-fermer {
            border: 0; border-radius: 10px;
            padding: 11px 26px;
            font-family: inherit; font-size: 15px; font-weight: 600;
            color: #ffffff; background: #16a34a;
            cursor: pointer;
            transition: background .15s ease;
        }
        .preadm-fermer:hover { background: #15803d; }
        .preadm-fermer:focus-visible { outline: 3px solid #86efac; outline-offset: 2px; }
        .preadm-croix {
            position: absolute; top: 12px; right: 14px;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            border: 0; border-radius: 8px;
            background: transparent; color: #6b8577;
            font-size: 20px; line-height: 1; cursor: pointer;
        }
        .preadm-croix:hover { background: #f1f5f4; color: #14532d; }
        .preadm-croix:focus-visible { outline: 3px solid #86efac; outline-offset: 2px; }
    </style>

    <?php /* Masquee sauf si l'on arrive par la redirection : dans ce cas elle
             est deja ouverte au premier rendu, sans attendre le script. */ ?>
    <div class="preadm-modale" id="preadm-modale" role="dialog" aria-modal="true" aria-labelledby="preadm-titre"
         <?= !empty($envoye) ? '' : 'hidden' ?>>
        <div class="preadm-carte">
            <button type="button" class="preadm-croix" id="preadm-croix" aria-label="Fermer">&times;</button>
            <div class="preadm-pastille" aria-hidden="true">&#127881;</div>
            <h2 class="preadm-titre" id="preadm-titre">Pré-inscription envoyée avec succès !</h2>
            <p class="preadm-texte">
                Merci pour votre intérêt pour l'Université Adventiste Zurcher.<br>
                Notre équipe vous contactera prochainement pour la suite du processus.
            </p>
            <button type="button" class="preadm-fermer" id="preadm-ok">Fermer</button>
        </div>
    </div>

    <script>
    /* Ouverture et fermeture, exposees pour que le formulaire puisse afficher
       la confirmation apres un envoi sans rechargement. */
    window.preadmModale = (function () {
        var modale = document.getElementById('preadm-modale');
        if (!modale) { return { ouvrir: function () {}, fermer: function () {} }; }

        var bouton = document.getElementById('preadm-ok');
        var elementActifAvant = null;
        var debordementInitial = '';
        var ouverte = false;

        function ouvrir() {
            if (ouverte) { return; }
            ouverte = true;

            /* Retenu a l'ouverture et non au chargement : apres un envoi, le
               focus se trouve sur le bouton du formulaire, et c'est la qu'il
               doit revenir a la fermeture. */
            elementActifAvant = document.activeElement;

            modale.hidden = false;
            // Le fond ne doit pas defiler pendant que la modale est ouverte.
            debordementInitial = document.body.style.overflow;
            document.body.style.overflow = 'hidden';

            document.addEventListener('keydown', surTouche);
            if (bouton) { bouton.focus(); }
        }

        function fermer() {
            if (!ouverte) { return; }
            ouverte = false;

            modale.hidden = true;
            document.body.style.overflow = debordementInitial;
            document.removeEventListener('keydown', surTouche);
            if (elementActifAvant && elementActifAvant.focus) { elementActifAvant.focus(); }

            /* Le parametre est retire pour qu'un rafraichissement ne reaffiche
               pas la confirmation. Conditionnel : sans « envoye » dans
               l'adresse — cas de l'envoi sans rechargement — reecrire l'URL
               effacerait sans raison le reste de la requete, et ferait perdre
               l'ancre de la rubrique. */
            if (window.history && history.replaceState
                && /[?&]envoye=/.test(window.location.search)) {
                history.replaceState(null, '', window.location.pathname + window.location.hash);
            }
        }

        function surTouche(e) {
            if (e.key === 'Escape' || e.keyCode === 27) { fermer(); return; }
            // Tant que la modale est ouverte, le clavier y reste enferme.
            if (e.key === 'Tab') {
                var focalisables = modale.querySelectorAll('button');
                if (!focalisables.length) { return; }
                var premier = focalisables[0];
                var dernier = focalisables[focalisables.length - 1];
                if (e.shiftKey && document.activeElement === premier) {
                    e.preventDefault(); dernier.focus();
                } else if (!e.shiftKey && document.activeElement === dernier) {
                    e.preventDefault(); premier.focus();
                }
            }
        }

        if (bouton) { bouton.addEventListener('click', fermer); }
        var croix = document.getElementById('preadm-croix');
        if (croix) { croix.addEventListener('click', fermer); }
        // Clic sur le fond, mais pas sur la carte.
        modale.addEventListener('click', function (e) {
            if (e.target === modale) { fermer(); }
        });

        /* Arrivee par la redirection : le serveur a rendu la modale visible,
           il reste a poser l'etat que ferme() attend (focus, blocage du
           defilement, ecoute du clavier). */
        if (!modale.hidden) {
            ouverte = true;
            elementActifAvant = document.activeElement;
            debordementInitial = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', surTouche);
            if (bouton) { bouton.focus(); }
        }

        return { ouvrir: ouvrir, fermer: fermer };
    })();
    </script>
