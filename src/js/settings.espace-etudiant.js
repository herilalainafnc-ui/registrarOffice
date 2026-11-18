/* =============================================================================
   Espace étudiant — interactions de /settings/espace-etudiant

   Deux gestes :
     - publier ou masquer les notes d'une session, après confirmation ;
     - afficher ou masquer une rubrique (interrupteur).

   Le serveur est la seule source de vérité : l'affichage ne change qu'après
   sa réponse, et un interrupteur refusé revient à son état précédent.
   ========================================================================== */

(function () {
	'use strict';

	var POINT = (window.APP_BASE || '') + '/app/espace-etudiant.save.php';

	function envoyer(champs) {
		var corps = new FormData();
		Object.keys(champs).forEach(function (k) { corps.append(k, champs[k]); });
		corps.append('csrf_token', window.CSRF_TOKEN || '');

		return fetch(POINT, {
			method: 'POST',
			body: corps,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () {
				return { ok: false, message: 'Réponse illisible du serveur (' + r.status + ').' };
			});
		});
	}

	function signalerEchec(charge) {
		var message = (charge && charge.message) || "L'opération a échoué.";
		if (window.Toast) { window.Toast.error(message); } else if (window.console) { console.error(message); }
	}

	function demander(options, texte) {
		if (window.Toast && typeof window.Toast.confirm === 'function') {
			return window.Toast.confirm(texte, options);
		}
		return Promise.resolve(window.confirm(texte));
	}

	function pluriel(n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); }

	/* ---- Publication d'une session --------------------------------------- */

	/** Met la ligne à l'état renvoyé par le serveur, et le résumé de son année. */
	function appliquer(ligne, publiee, trace) {
		ligne.dataset.publiee = publiee ? '1' : '0';
		ligne.classList.toggle('est-publiee', publiee);
		ligne.classList.toggle('est-masquee', !publiee);

		ligne.querySelector('.js-ee-etat').innerHTML = publiee
			? '<i class="bi bi-eye-fill" aria-hidden="true"></i> Publiée'
			: '<i class="bi bi-eye-slash-fill" aria-hidden="true"></i> Masquée';

		var bouton = ligne.querySelector('.js-ee-publier');
		bouton.className = 'ee-btn ' + (publiee ? 'ee-btn--masquer' : 'ee-btn--publier') + ' js-ee-publier';
		bouton.textContent = publiee ? 'Masquer' : 'Publier les notes';

		if (trace) { ligne.querySelector('.js-ee-trace').textContent = trace; }

		var annee = ligne.closest('.ee-annee');
		var resume = annee && annee.querySelector('.js-ee-resume');
		if (resume) {
			var lignes = annee.querySelectorAll('.ee-session');
			var n = annee.querySelectorAll('.ee-session[data-publiee="1"]').length;
			resume.textContent = n + ' publiée' + (n > 1 ? 's' : '') + ' sur ' + lignes.length;
		}
	}

	document.addEventListener('click', function (e) {
		var bouton = e.target.closest ? e.target.closest('.js-ee-publier') : null;
		if (!bouton || bouton.disabled) { return; }

		var ligne = bouton.closest('.ee-session');
		var publier = ligne.dataset.publiee !== '1';
		var libelle = ligne.dataset.libelle;
		var etudiants = parseInt(ligne.dataset.etudiants, 10) || 0;
		var nonNotees = parseInt(ligne.dataset.nonNotees, 10) || 0;

		var texte;
		if (publier) {
			texte = 'Publier les notes de « ' + libelle + ' » ?\n\n'
				+ (etudiants > 0
					? pluriel(etudiants, 'étudiant') + ' les verront dans « Mes Notes » dès maintenant, '
						+ 'et elles compteront dans leur moyenne.'
					: 'Aucune note n\'est encore saisie : les notes ajoutées ensuite seront visibles aussitôt.')
				+ (nonNotees > 0
					? '\n\n' + pluriel(nonNotees, 'note') + ' ne sont pas encore saisies : elles s\'afficheront « NN ».'
					: '');
		} else {
			texte = 'Masquer les notes de « ' + libelle + ' » ?\n\n'
				+ (etudiants > 0
					? 'Elles disparaîtront de « Mes Notes » pour ' + pluriel(etudiants, 'étudiant')
						+ ' et ne compteront plus dans leur moyenne affichée.'
					: 'Aucune note n\'est saisie pour cette session.');
		}

		demander({
			title: publier ? 'Publier les notes' : 'Masquer les notes',
			confirmText: publier ? 'Publier' : 'Masquer',
			danger: !publier
		}, texte).then(function (ok) {
			if (!ok) { return; }

			bouton.disabled = true;
			envoyer({ action: 'publication', session_id: ligne.dataset.session, publiee: publier ? '1' : '0' })
				.then(function (charge) {
					bouton.disabled = false;
					if (!charge || !charge.ok) { signalerEchec(charge); return; }
					appliquer(ligne, !!charge.publiee, charge.trace);
					if (window.Toast) { window.Toast.success(charge.message); }
				})
				.catch(function () {
					bouton.disabled = false;
					signalerEchec(null);
				});
		});
	});

	/* ---- Rubriques ------------------------------------------------------- */

	document.addEventListener('change', function (e) {
		var caseRubrique = e.target;
		if (!caseRubrique.classList || !caseRubrique.classList.contains('js-ee-rubrique')) { return; }

		var visible = caseRubrique.checked;
		var bloc = caseRubrique.closest('.ee-rubrique');
		var etat = bloc.querySelector('.js-ee-rubrique-etat');

		caseRubrique.disabled = true;
		envoyer({ action: 'rubrique', cle: caseRubrique.dataset.cle, visible: visible ? '1' : '0' })
			.then(function (charge) {
				caseRubrique.disabled = false;
				if (!charge || !charge.ok) {
					caseRubrique.checked = !visible;   // refusé : on revient à l'état réel
					signalerEchec(charge);
					return;
				}
				etat.textContent = charge.visible ? 'Visible' : 'Masquée';
				if (charge.trace) { bloc.querySelector('.js-ee-trace').textContent = charge.trace; }
				if (window.Toast) { window.Toast.success(charge.message); }
			})
			.catch(function () {
				caseRubrique.disabled = false;
				caseRubrique.checked = !visible;
				signalerEchec(null);
			});
	});
})();
