/* =============================================================================
   Sigle d'un cours unique : avis pendant la frappe

   Utilisé par la modale « Ajouter un cours » (src/main/bigNotif.php) et la
   fiche d'un cours (src/cours/information.php). Interroge
   app/.cours/sigle.verifier.php 300 ms après la dernière frappe, et prévient
   le formulaire par surChangement(etat) pour qu'il grise son bouton.

   Le serveur refuse de toute façon un sigle déjà pris (addCours.php,
   updateCours.php) : si la vérification échoue ici, on ne bloque pas,
   on le dit.
   ========================================================================== */
(function () {
	'use strict';
	if (window.CoursSigle) return;

	var BASE = typeof APP_BASE !== 'undefined' ? APP_BASE : '';

	function normaliser(v) {
		return String(v || '').replace(/\s+/g, ' ').trim().toUpperCase();
	}

	function surveiller(champ, options) {
		var o = options || {};
		var avis = o.avis || null;
		var classes = o.classes || {};
		var initiale = normaliser(o.initiale || '');
		var etat = { libre: true, enCours: false, doublons: [] };
		var minuterie = null;
		var controleur = null;
		var derniere = null;

		function signaler() {
			if (typeof o.surChangement === 'function') o.surChangement(etat);
		}

		function afficher(type, contenu) {
			if (!avis) return;
			avis.className = ((o.classeAvis || '') + ' ' + (classes[type] || '')).trim();
			while (avis.firstChild) avis.removeChild(avis.firstChild);
			(contenu || []).forEach(function (n) {
				avis.appendChild(typeof n === 'string' ? document.createTextNode(n) : n);
			});
		}

		function marquer(invalide) {
			if (o.classeInvalide) champ.classList.toggle(o.classeInvalide, invalide);
			champ.setAttribute('aria-invalid', invalide ? 'true' : 'false');
		}

		function contenuDoublons(doublons) {
			var titre = document.createElement('strong');
			titre.textContent = doublons.length > 1 ? 'Ce sigle est déjà utilisé par ' + doublons.length + ' cours :' : 'Ce sigle est déjà utilisé :';
			var liste = document.createElement('ul');
			doublons.slice(0, 3).forEach(function (d) {
				var li = document.createElement('li');
				var lien = document.createElement('a');
				lien.href = BASE + '/cours?id=' + encodeURIComponent(d.id) + '&page=information';
				lien.target = '_blank';
				lien.rel = 'noopener';
				lien.setAttribute('data-pt-skip', '1');
				lien.textContent = d.sigle + ' — ' + (d.titre || 'sans titre');
				li.appendChild(lien);
				li.appendChild(document.createTextNode(' · ' + d.mention + ', ' + d.niveau + ', semestre ' + d.semestre + (d.retire ? ', retiré' : '')));
				liste.appendChild(li);
			});
			var contenu = [titre, liste];
			if (doublons.length > 3) contenu.push('… et ' + (doublons.length - 3) + ' autre(s).');
			return contenu;
		}

		function appliquerDoublons(doublons) {
			etat = { libre: false, enCours: false, doublons: doublons };
			afficher('doublon', contenuDoublons(doublons));
			marquer(true);
			signaler();
		}

		function reinitialiser() {
			clearTimeout(minuterie);
			if (controleur) controleur.abort();
			derniere = null;
			etat = { libre: true, enCours: false, doublons: [] };
			afficher('neutre', []);
			marquer(false);
			signaler();
		}

		function verifier() {
			var valeur = normaliser(champ.value);
			if (valeur === derniere && !etat.enCours) return;
			derniere = valeur;
			if (controleur) controleur.abort();

			if (valeur === '' || (initiale !== '' && valeur === initiale)) {
				etat = { libre: true, enCours: false, doublons: [] };
				afficher('neutre', []);
				marquer(false);
				signaler();
				return;
			}

			etat.enCours = true;
			afficher('attente', ['Vérification du sigle…']);
			signaler();

			controleur = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var url = BASE + '/app/.cours/sigle.verifier.php?sigle=' + encodeURIComponent(champ.value) + (o.sauf ? '&sauf=' + encodeURIComponent(o.sauf) : '');
			fetch(url, {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
				signal: controleur ? controleur.signal : undefined
			})
				.then(function (r) { return r.json().then(function (d) { if (!r.ok || !d.ok) throw new Error(d.message || 'erreur'); return d; }); })
				.then(function (d) {
					if (normaliser(champ.value) !== valeur) return;
					if (d.libre) {
						etat = { libre: true, enCours: false, doublons: [] };
						afficher('ok', ['✓ Sigle disponible']);
						marquer(false);
						signaler();
					} else {
						appliquerDoublons(d.doublons || []);
					}
				})
				.catch(function (e) {
					if (e && e.name === 'AbortError') return;
					etat = { libre: true, enCours: false, doublons: [] };
					afficher('attente', ['Vérification impossible pour le moment : le sigle sera contrôlé à l\'enregistrement.']);
					signaler();
				});
		}

		champ.addEventListener('input', function () {
			clearTimeout(minuterie);
			if (normaliser(champ.value) !== derniere) {
				etat.enCours = true;
				signaler();
			}
			minuterie = setTimeout(verifier, 300);
		});
		champ.setAttribute('autocomplete', 'off');

		return {
			etat: function () { return etat; },
			reinitialiser: reinitialiser,
			/* Réponse du serveur à l'enregistrement : il a trouvé un doublon que
			   l'avis n'affichait pas (créé entre-temps par quelqu'un d'autre). */
			forcerDoublons: function (r) { appliquerDoublons((r && r.doublons) || []); }
		};
	}

	window.CoursSigle = { surveiller: surveiller, normaliser: normaliser };
})();
