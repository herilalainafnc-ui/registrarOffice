/* =========================================================================
   Expiration de session pendant un appel AJAX.

   Avant : un endpoint protege repondait 302 vers /login, jQuery suivait la
   redirection et recevait le HTML de la page de connexion, qu'il inserait
   dans la page. L'interface semblait figee, sans la moindre explication.

   Maintenant : les endpoints repondent 401 + JSON (voir data/guard.ajax.php).
   Ce script intercepte cette reponse, previent l'utilisateur, et le renvoie
   vers la connexion. La page qu'il consultait est memorisee cote serveur :
   il y reviendra apres s'etre reconnecte.
   ========================================================================= */
(function () {
    'use strict';

    // Une session n'expire qu'une fois : sans ce verrou, dix appels AJAX
    // simultanes declencheraient dix messages et dix redirections.
    var dejaTraite = false;

    var DELAI_REDIRECTION = 2600; // laisse le temps de lire le message

    function urlConnexion(reponse) {
        if (reponse && typeof reponse.login_url === 'string' && reponse.login_url.charAt(0) === '/') {
            return reponse.login_url;
        }
        return (typeof window.APP_BASE === 'string' ? window.APP_BASE : '') + '/login';
    }

    function avertir(message, titre) {
        if (typeof window.Toast !== 'undefined' && typeof Toast.warning === 'function') {
            Toast.warning(message, { title: titre, duration: DELAI_REDIRECTION });
        } else if (window.console) {
            // Sans la bibliotheque de notifications : la console, jamais de boite native.
            console.warn(message);
        }
    }

    function traiterSessionExpiree(reponse) {
        if (dejaTraite) { return; }
        dejaTraite = true;

        avertir(
            (reponse && reponse.message) || 'Votre session a expiré. Reconnectez-vous pour continuer.',
            'Session expirée'
        );

        var destination = urlConnexion(reponse);
        window.setTimeout(function () {
            window.location.href = destination;
        }, DELAI_REDIRECTION);
    }

    function traiterAccesRefuse(reponse) {
        // Pas de redirection ici : l'utilisateur est bien connecte, il lui
        // manque seulement un privilege. Le renvoyer vers /login n'aiderait pas.
        avertir(
            (reponse && reponse.message) || "Accès refusé : privilèges insuffisants.",
            'Accès refusé'
        );
    }

    /** Extrait le JSON d'une reponse jQuery, quelle que soit sa forme. */
    function lireReponse(xhr) {
        if (!xhr) { return null; }
        if (xhr.responseJSON) { return xhr.responseJSON; }
        if (typeof xhr.responseText === 'string' && xhr.responseText.charAt(0) === '{') {
            try { return JSON.parse(xhr.responseText); } catch (e) { return null; }
        }
        return null;
    }

    if (typeof window.jQuery !== 'undefined') {
        jQuery(document).ajaxError(function (evenement, xhr) {
            if (!xhr) { return; }
            if (xhr.status === 401) { traiterSessionExpiree(lireReponse(xhr)); }
            else if (xhr.status === 403) { traiterAccesRefuse(lireReponse(xhr)); }
        });
    }

    /* Les appels en fetch() ne passent pas par jQuery. On enveloppe fetch pour
       les couvrir aussi, en laissant la reponse intacte pour l'appelant :
       clone() evite de consommer le corps, que le code appelant doit encore
       pouvoir lire. */
    if (typeof window.fetch === 'function') {
        var fetchOrigine = window.fetch;
        window.fetch = function () {
            return fetchOrigine.apply(this, arguments).then(function (reponse) {
                if (reponse && (reponse.status === 401 || reponse.status === 403)) {
                    reponse.clone().json().then(function (donnees) {
                        if (reponse.status === 401) { traiterSessionExpiree(donnees); }
                        else { traiterAccesRefuse(donnees); }
                    }).catch(function () {
                        if (reponse.status === 401) { traiterSessionExpiree(null); }
                    });
                }
                return reponse;
            });
        };
    }
})();
