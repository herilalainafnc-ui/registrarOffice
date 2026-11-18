/* =============================================================================
   Paramètres du diplôme — enregistrement sans rechargement

   Le formulaire partait en POST classique : la page se rechargeait, revenait
   en haut, et le message passait par l'adresse (?ok=1). Il est maintenant
   envoyé en AJAX ; le point d'entrée répond en JSON quand la requête le
   demande (app/.settings/save.diplome.config.php) et garde sa redirection
   sinon.

   Après succès, le formulaire est relu depuis le serveur : les aperçus de
   signature changent de nom de fichier à chaque téléversement, et les champs
   reprennent les valeurs réellement enregistrées.
   ========================================================================== */

(function () {
	'use strict';

	var SELECTEUR = 'form[action$="save.diplome.config.php"]';

	function dire(type, message) {
		if (window.Toast && typeof window.Toast[type] === 'function') {
			window.Toast[type](message, type === 'error' ? { duration: 8000 } : {});
		} else if (window.console) {
			console.warn(message);
		}
	}

	/** Remplace le contenu du formulaire par celui renvoyé par le serveur. */
	function rafraichir(form) {
		return fetch(window.location.pathname, { credentials: 'same-origin' })
			.then(function (r) { return r.text(); })
			.then(function (html) {
				var neuf = new DOMParser().parseFromString(html, 'text/html').querySelector(SELECTEUR);
				if (neuf) { form.innerHTML = neuf.innerHTML; }
			});
	}

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form.matches || !form.matches(SELECTEUR)) { return; }
		e.preventDefault();

		var bouton = form.querySelector('button[type="submit"]');
		var libelle = bouton ? bouton.innerHTML : '';
		if (bouton) {
			bouton.disabled = true;
			bouton.innerHTML = '<i class="bi-hourglass-split"></i> Enregistrement…';
		}
		var retablir = function () {
			if (bouton && bouton.isConnected) { bouton.disabled = false; bouton.innerHTML = libelle; }
		};

		fetch(form.getAttribute('action'), {
			method: 'POST',
			body: new FormData(form),
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () {
				return { ok: false, message: 'Réponse illisible du serveur (' + r.status + ').' };
			});
		}).then(function (charge) {
			if (!charge || !charge.ok) {
				retablir();
				dire('error', (charge && charge.message) || "L'enregistrement a échoué.");
				return;
			}
			dire('success', charge.message || 'Paramètres du diplôme enregistrés.');
			return rafraichir(form).then(retablir, retablir);
		}).catch(function () {
			retablir();
			dire('error', "L'enregistrement a échoué. Vérifiez la connexion et réessayez.");
		});
	});
})();
