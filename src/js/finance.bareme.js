/* =============================================================================
   Barème financier par session — interactions de la page /finance

   Trois gestes :
     - changer d'année (le semestre et la filière restent en place) ;
     - enregistrer une filière, sans recharger la page ;
     - reprendre le barème de base pour une session, ou y revenir.

   L'enregistrement n'envoie QUE les cases modifiées. C'est ce qui répare le
   défaut de l'ancien endpoint : il initialisait les onze montants à 0 puis ne
   reprenait que ceux présents dans le POST, si bien qu'enregistrer le semestre
   1 remettait frais_graduation à zéro — parce que la page n'en rend pas
   d'input à ce semestre.
   ========================================================================== */

(function () {
	'use strict';

	var POINT = (window.APP_BASE || '') + '/app/finance-bareme.save.php';
	var POINT_FRAIS = (window.APP_BASE || '') + '/app/finance-frais.save.php';

	/** Zone courante et ses paramètres, lus sur le conteneur rendu par PHP. */
	function zone() {
		return document.getElementById('baremeZone');
	}

	/** Nombre à partir d'une saisie, tolérant aux espaces de milliers. */
	function nombre(v) {
		var n = parseFloat(String(v == null ? '' : v).replace(/\s/g, '').replace(',', '.'));
		return isNaN(n) ? null : n;
	}

	/** Deux saisies désignent-elles le même montant ? */
	function memeMontant(a, b) {
		var x = nombre(a);
		var y = nombre(b);
		if (x === null && y === null) { return true; }
		if (x === null || y === null) { return false; }
		return Math.abs(x - y) < 0.0001;
	}

	/** La carte (filière) qui contient cet élément. */
	function carte(el) {
		return el.closest('.finance-card');
	}

	/** Champs modifiés d'une carte. */
	function champsModifies(carteEl) {
		return Array.prototype.filter.call(
			carteEl.querySelectorAll('.js-bareme-champ'),
			function (i) { return i.dataset.modifie === '1'; }
		);
	}

	/** Met à jour le bouton et le compteur d'une carte. */
	function rafraichirCarte(carteEl) {
		var modifies = champsModifies(carteEl);
		var bouton = carteEl.querySelector('.js-bareme-enregistrer');
		var compteur = carteEl.querySelector('.js-bareme-compteur');
		if (!bouton) { return; }

		/* Deux raisons d'activer le bouton :
		     - des montants ont été modifiés ;
		     - aucun n'a changé, mais des filières sont cochées. C'est le cas
		       « INFO et LANG ont les mêmes frais » : on veut propager un barème
		       déjà saisi, sans avoir à le retaper pour le rendre « modifié ». */
		var cibles = ciblesCochees(carteEl).length;
		var actif = modifies.length > 0 || cibles > 0;

		bouton.disabled = !actif;
		bouton.className = 'btn-save js-bareme-enregistrer '
			+ (actif ? 'btn-save-active' : 'btn-save-inactive');
		bouton.innerHTML = (modifies.length === 0 && cibles > 0)
			? '<i class="bi bi-diagram-2"></i> Appliquer'
			: '<i class="bi bi-check2-circle"></i> Enregistrer';

		if (compteur) {
			var bouts = [];
			if (modifies.length > 0) {
				bouts.push(modifies.length + ' montant' + (modifies.length > 1 ? 's' : '')
					+ ' modifié' + (modifies.length > 1 ? 's' : ''));
			}
			if (cibles > 0) {
				bouts.push(cibles + ' filière' + (cibles > 1 ? 's' : '') + ' ciblée'
					+ (cibles > 1 ? 's' : ''));
			}
			compteur.textContent = bouts.join(' · ');
		}

		rafraichirPartage(carteEl);
	}

	/* ---- Suivi des saisies ------------------------------------------------ */

	document.addEventListener('input', function (e) {
		var champ = e.target;
		if (!champ.classList || !champ.classList.contains('js-bareme-champ')) { return; }

		var modifie = !memeMontant(champ.value, champ.defaultValue);
		champ.dataset.modifie = modifie ? '1' : '0';
		// Une case corrigée puis rétablie redevient « héritée » si elle l'était.
		rafraichirCarte(carte(champ));
	});

	// Sélectionner le contenu au clic : c'est une grille de saisie, on remplace
	// une valeur plus souvent qu'on ne l'édite.
	document.addEventListener('focus', function (e) {
		if (e.target.classList && e.target.classList.contains('inputFnc')) {
			e.target.select();
		}
	}, true);

	/* ---- Garde-fou : quitter avec des montants non enregistrés ------------ */

	window.addEventListener('beforeunload', function (e) {
		// modificationsEnAttente() est declaree plus bas : les declarations de
		// fonction sont remontees, l'appel est donc valide ici.
		if (modificationsEnAttente() > 0) {
			e.preventDefault();
			e.returnValue = '';
			return '';
		}
	});

	/* ---- Appel au serveur -------------------------------------------------- */

	/**
	 * Envoie une requête au point d'entrée et renvoie la charge utile.
	 * Le jeton CSRF vient de la globale posée par init/head.php.
	 */
	function envoyer(corps) {
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

	/** Message d'échec, en distinguant le jeton expiré du reste. */
	function signalerEchec(charge) {
		var message = (charge && charge.message) || "L'opération a échoué.";
		if (charge && charge.csrf_expire) {
			message = 'Jeton de sécurité expiré. Rechargez la page et réessayez.';
		}
		if (window.Toast) { window.Toast.error(message); } else if (window.console) { console.error(message); }
	}

	/** Confirmation en modale (init/toast.php). Sans elle, on ne fait rien. */
	function demander(options, texte) {
		if (window.Toast && typeof window.Toast.confirm === 'function') {
			return window.Toast.confirm(texte, options || {});
		}
		return Promise.resolve(false);
	}

	/**
	 * La modale répond de façon asynchrone. Au premier clic on demande et on
	 * s'arrête ; si c'est confirmé, le clic est rejoué sur le même bouton et ce
	 * second passage trouve la marque et poursuit.
	 * @return {boolean} true si le geste est déjà confirmé.
	 */
	function confirme(el, options, texte) {
		if (el.dataset.confirme === '1') { delete el.dataset.confirme; return true; }
		demander(options, texte).then(function (ok) {
			if (ok) { el.dataset.confirme = '1'; el.click(); }
		});
		return false;
	}

	/** Même principe pour l'application à plusieurs filières. */
	function partageConfirme(bouton, carteEl, cibles, nbModifies) {
		if (bouton.dataset.confirme === '1') { delete bouton.dataset.confirme; return true; }
		confirmerPartage(carteEl, cibles, nbModifies).then(function (ok) {
			if (ok) { bouton.dataset.confirme = '1'; bouton.click(); }
		});
		return false;
	}

	/** Changement de période avec des montants non enregistrés. */
	function demanderPuisCharger(params, empiler, texte) {
		demander({ title: 'Montants non enregistrés', confirmText: 'Changer de période', danger: true }, texte)
			.then(function (ok) {
				if (ok) { charger(Object.assign({}, params, { confirme: true }), empiler); }
			});
		return false;
	}

	/* ---- Appliquer à plusieurs filières ----------------------------------- */

	/* Plusieurs filières partagent souvent un barème identique : relevé en
	   base, les huit mentions ne forment que cinq profils distincts par
	   semestre. Le panneau permet de saisir une fois et d'appliquer à
	   plusieurs, au lieu de retaper les mêmes montants trois ou quatre fois. */

	/** Ouvre ou referme le panneau de partage d'une carte. */
	document.addEventListener('click', function (e) {
		var bouton = e.target.closest ? e.target.closest('.js-bareme-partage') : null;
		if (!bouton) { return; }

		var panneau = carte(bouton).querySelector('.bareme-partage');
		if (!panneau) { return; }

		var ouvert = !panneau.hidden;
		panneau.hidden = ouvert;
		bouton.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
	});

	/** Filières cochées sur une carte. */
	function ciblesCochees(carteEl) {
		return Array.prototype.filter.call(
			carteEl.querySelectorAll('.js-bareme-cible'),
			function (c) { return c.checked; }
		);
	}

	/** Met à jour le libellé du bouton de partage selon la sélection. */
	function rafraichirPartage(carteEl) {
		var bouton = carteEl.querySelector('.js-bareme-partage');
		if (!bouton) { return; }

		var n = ciblesCochees(carteEl).length;
		bouton.innerHTML = n === 0
			? '<i class="bi bi-diagram-2"></i> Appliquer aussi à…'
			: '<i class="bi bi-diagram-2"></i> + ' + n + ' filière' + (n > 1 ? 's' : '');
	}

	document.addEventListener('change', function (e) {
		if (!e.target.classList || !e.target.classList.contains('js-bareme-cible')) { return; }
		// rafraichirCarte appelle rafraichirPartage et réévalue le bouton :
		// cocher une filière suffit à rendre l'enregistrement possible.
		rafraichirCarte(carte(e.target));
	});

	/**
	 * Confirmation avant d'appliquer à plusieurs filières.
	 *
	 * Distingue celles qui héritent encore du barème de base de celles qui
	 * portent déjà un tarif propre : ces dernières seraient remplacées, et
	 * l'ajustement de quelqu'un d'autre disparaîtrait sans avertissement.
	 *
	 * L'information est déjà dans le DOM (data-ajustee, rendu par le serveur) :
	 * pas besoin d'un aller-retour supplémentaire.
	 *
	 * @return {Promise<boolean>} résolue à true si l'on peut poursuivre.
	 */
	function confirmerPartage(carteEl, cibles, nbModifies) {
		if (cibles.length === 0) { return Promise.resolve(true); }

		var lignes = [];
		var ecrasees = 0;
		cibles.forEach(function (c) {
			var deja = c.dataset.ajustee === '1';
			if (deja) { ecrasees++; }
			lignes.push('  • ' + c.dataset.libelle
				+ (deja ? '  — tarif propre déjà ajusté, il sera remplacé' : ''));
		});

		var source = carteEl.dataset.libelle || carteEl.dataset.mention;
		var pluriel = cibles.length > 1 ? 's' : '';

		return demander({ title: nbModifies > 0 ? 'Enregistrer et appliquer' : 'Appliquer le barème', confirmText: nbModifies > 0 ? 'Enregistrer et appliquer' : 'Appliquer', danger: ecrasees > 0 },
			(nbModifies > 0
				? 'Enregistrer ' + source + ' et appliquer les mêmes montants à '
					+ cibles.length + ' autre' + pluriel + ' filière' + pluriel + ' ?'
				: 'Appliquer le barème de ' + source + ' à '
					+ cibles.length + ' filière' + pluriel + ' ?')
			+ '\n\n' + lignes.join('\n')
			+ (ecrasees > 0
				? '\n\n' + ecrasees + ' tarif' + (ecrasees > 1 ? 's' : '')
					+ ' déjà ajusté' + (ecrasees > 1 ? 's' : '') + ' sera remplacé'
					+ (ecrasees > 1 ? 's' : '') + '.'
				: '')
		);
	}

	/* ---- Enregistrer une filière ------------------------------------------ */

	document.addEventListener('click', function (e) {
		var bouton = e.target.closest ? e.target.closest('.js-bareme-enregistrer') : null;
		if (!bouton || bouton.disabled) { return; }

		var z = zone();
		var carteEl = carte(bouton);
		var modifies = champsModifies(carteEl);
		var cibles = ciblesCochees(carteEl);
		if (modifies.length === 0 && cibles.length === 0) { return; }

		if (cibles.length > 0 && !partageConfirme(bouton, carteEl, cibles, modifies.length)) { return; }

		var corps = new FormData();
		corps.append('action', 'enregistrer');
		corps.append('session_id', z.dataset.sessionId);
		corps.append('semestre', z.dataset.semestre);

		var champsAEnvoyer;
		if (modifies.length > 0) {
			/* Des montants ont changé : ils vont à cette filière, et aux
			   filières cochées. Seules les cases modifiées voyagent — le
			   serveur ne touche pas aux autres. */
			corps.append('mention', bouton.dataset.mention);
			cibles.forEach(function (c) { corps.append('mentions[]', c.value); });
			champsAEnvoyer = modifies;
		} else {
			/* Rien n'a changé, mais des filières sont cochées : on propage le
			   barème déjà en place. La filière source n'est PAS réécrite — elle
			   porte déjà ces montants — donc la première cible devient la
			   mention principale et les autres la complètent. Toutes les cases
			   voyagent, pas seulement les modifiées : il n'y en a aucune. */
			corps.append('mention', cibles[0].value);
			cibles.slice(1).forEach(function (c) { corps.append('mentions[]', c.value); });
			champsAEnvoyer = Array.prototype.slice.call(
				carteEl.querySelectorAll('.js-bareme-champ')
			);
		}

		champsAEnvoyer.forEach(function (champ) {
			/* Frais supplémentaires : la case vide EST transmise, car elle
			   signifie « revenir au barème de base » pour ce cas. Les colonnes
			   fixes, elles, ne voyagent pas à vide : les envoyer à 0
			   inventerait un tarif nul. */
			if (champ.dataset.perso) {
				var brut = String(champ.value).trim();
				corps.append('montants_perso[' + champ.dataset.perso + '][' + champ.dataset.cle + ']',
					brut === '' ? '' : String(nombre(champ.value)));
				return;
			}
			if (String(champ.value).trim() === '') { return; }
			corps.append('montants[' + champ.dataset.cle + '][' + champ.dataset.col + ']',
				String(nombre(champ.value)));
		});

		var libelle = bouton.innerHTML;
		bouton.disabled = true;
		bouton.textContent = 'Enregistrement…';

		envoyer(corps).then(function (charge) {
			if (!charge || !charge.ok) {
				signalerEchec(charge);
				bouton.innerHTML = libelle;
				rafraichirCarte(carteEl);
				return;
			}

			bouton.innerHTML = libelle;
			if (window.Toast) { window.Toast.success(charge.message); }

			if (cibles.length > 0) {
				/* D'autres filières viennent de changer : leurs cartes
				   affichent encore les anciennes valeurs. On relit la grille
				   entière plutôt que de recopier les montants case par case
				   dans le DOM — le serveur est la seule source fiable de ce
				   qui a réellement été écrit. */
				var p = paramsDeLUrl();
				charger({ annee: p.annee, semestre: p.semestre, base: p.base }, false);
				return;
			}

			// Les valeurs enregistrées deviennent la nouvelle référence, et la
			// case n'est plus héritée : elle appartient à cette session.
			// defaultValue est la référence côté serveur : la réécrire ici
			// évite un attribut data-initial en double sur chaque champ.
			modifies.forEach(function (champ) {
				champ.defaultValue = champ.value;
				champ.dataset.modifie = '0';
				if (z.dataset.base !== '1') {
					delete champ.dataset.herite;
					champ.removeAttribute('title');
				}
			});

			rafraichirCarte(carteEl);

			var pastille = carteEl.querySelector('.bareme-etat');
			if (pastille && z.dataset.base !== '1') {
				pastille.className = 'bareme-etat bareme-etat-propre';
				pastille.textContent = 'Tarif propre à cette session';
				carteEl.dataset.ajustee = '1';
			}
		}).catch(function () {
			signalerEchec(null);
			bouton.innerHTML = libelle;
			rafraichirCarte(carteEl);
		});
	});

	/* ---- Reprendre le barème de base, ou y revenir ------------------------ */

	function actionSurMention(bouton, action, confirmation) {
		var z = zone();
		if (confirmation && !confirme(bouton, { title: 'Revenir au barème de base', confirmText: 'Supprimer le tarif propre', danger: true }, confirmation)) { return; }

		var corps = new FormData();
		corps.append('action', action);
		corps.append('session_id', z.dataset.sessionId);
		corps.append('mention', bouton.dataset.mention);
		if (action === 'copier') {
			corps.append('source_session_id', '0'); // 0 = barème de base
		}

		bouton.disabled = true;

		envoyer(corps).then(function (charge) {
			bouton.disabled = false;
			if (!charge || !charge.ok) {
				signalerEchec(charge);
				return;
			}
			if (window.Toast) { window.Toast.success(charge.message); }
			// La grille entière change de provenance : on la relit depuis le
			// serveur plutôt que de deviner le nouvel état case par case.
			// Sans empiler d'entrée d'historique : la période n'a pas changé.
			var p = paramsDeLUrl();
			charger({ annee: p.annee, semestre: p.semestre, base: p.base }, false);
		}).catch(function () {
			bouton.disabled = false;
			signalerEchec(null);
		});
	}

	document.addEventListener('click', function (e) {
		if (!e.target.closest) { return; }

		var copier = e.target.closest('.js-bareme-copier');
		if (copier) {
			actionSurMention(copier, 'copier', null);
			return;
		}

		var reinit = e.target.closest('.js-bareme-reinit');
		if (reinit) {
			actionSurMention(reinit, 'reinitialiser',
				'Supprimer le tarif propre à cette session pour cette filière ?\n\n'
				+ 'Elle reviendra au barème de base. Les montants ajustés seront perdus.');
		}
	});

	/* ---- Frais supplémentaires : ajouter, renommer, désactiver ------------- */

	/** Envoie une action de définition, puis relit la grille. */
	function actionFrais(corps) {
		// Meme jeton que envoyer() : ces actions modifient le bareme.
		corps.append('csrf_token', window.CSRF_TOKEN || '');

		return fetch(POINT_FRAIS, {
			method: 'POST',
			body: corps,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () {
				return { ok: false, message: 'Réponse illisible du serveur (' + r.status + ').' };
			});
		}).then(function (charge) {
			if (!charge || !charge.ok) { signalerEchec(charge); return; }
			if (window.Toast) { window.Toast.success(charge.message); }

			/* La grille porte une ligne par frais : elle est relue en entier.
			   confirme:true — l'utilisateur vient de demander cette action, on
			   ne lui repose pas la question des montants en attente. */
			var p = paramsDeLUrl();
			charger({ annee: p.annee, semestre: p.semestre, base: p.base, confirme: true }, false);
		}).catch(function () { signalerEchec(null); });
	}

	document.addEventListener('submit', function (e) {
		var form = e.target.closest ? e.target.closest('.js-frais-ajout') : null;
		if (!form) { return; }
		e.preventDefault();

		var champ = form.querySelector('.js-frais-libelle');
		var libelle = champ ? champ.value.trim() : '';
		if (libelle === '') {
			if (window.Toast) { window.Toast.error('Donnez un nom au frais à ajouter.'); }
			if (champ) { champ.focus(); }
			return;
		}

		var corps = new FormData();
		corps.append('action', 'creer');
		corps.append('libelle', libelle);
		actionFrais(corps);
	});

	/** Renommage sur place : la pastille devient un champ, Échap annule. */
	function renommerSurPlace(bouton) {
		var puce = bouton.closest('.js-frais-ligne');
		var nomEl = puce ? puce.querySelector('.js-frais-nom') : null;
		if (!puce || !nomEl || puce.dataset.edition === '1') { return; }
		puce.dataset.edition = '1';

		var ancien = bouton.dataset.libelle || nomEl.textContent;
		var saisie = document.createElement('input');
		saisie.type = 'text';
		saisie.className = 'frais-perso-champ';
		saisie.style.minWidth = '12rem';
		saisie.maxLength = 120;
		saisie.value = ancien;
		nomEl.replaceWith(saisie);
		saisie.focus();
		saisie.select();

		var termine = false;
		function fin(valider) {
			if (termine) { return; }
			termine = true;

			var nom = saisie.value.trim();
			var span = document.createElement('span');
			span.className = 'js-frais-nom';
			span.textContent = ancien;
			saisie.replaceWith(span);
			puce.dataset.edition = '0';

			if (!valider || nom === '' || nom === ancien) { return; }
			var corps = new FormData();
			corps.append('action', 'renommer');
			corps.append('frais_id', bouton.dataset.frais);
			corps.append('libelle', nom);
			actionFrais(corps);
		}

		saisie.addEventListener('keydown', function (ev) {
			if (ev.key === 'Enter') { ev.preventDefault(); fin(true); }
			if (ev.key === 'Escape') { ev.preventDefault(); fin(false); }
		});
		saisie.addEventListener('blur', function () { fin(true); });
	}

	document.addEventListener('click', function (e) {
		if (!e.target.closest) { return; }

		var renommer = e.target.closest('.js-frais-renommer');
		if (renommer) {
			renommerSurPlace(renommer);
			return;
		}

		var regler = e.target.closest('.js-frais-regler');
		if (regler) {
			var ligne = ligneFrais(regler);
			if (!ligne) { return; }

			var semestres = [];
			Array.prototype.forEach.call(ligne.querySelectorAll('.js-frais-semestre'), function (c) {
				if (c.checked) { semestres.push(c.value); }
			});

			var corpsR = new FormData();
			corpsR.append('action', 'regler');
			corpsR.append('frais_id', regler.dataset.frais);
			corpsR.append('condition', (ligne.querySelector('.js-frais-condition') || {}).value || 'aucune');
			// Aucune case cochée = tous les semestres : le serveur lit la chaîne vide.
			corpsR.append('semestres', semestres.join(','));

			regler.disabled = true;
			actionFrais(corpsR);
			return;
		}
		var basculer = e.target.closest('.js-frais-basculer');
		if (!basculer) { return; }

		var actif = basculer.dataset.actif === '1';
		if (actif && !confirme(basculer,
				{ title: 'Désactiver ce frais', confirmText: 'Désactiver', danger: true },
				'Désactiver « ' + (basculer.dataset.libelle || 'ce frais') + ' » ?\n\n'
				+ 'Il ne sera plus facturé aux prochaines inscriptions. Les montants du barème '
				+ 'et ce qui a déjà été facturé sont conservés.')) {
			return;
		}

		var corps = new FormData();
		corps.append('action', actif ? 'desactiver' : 'reactiver');
		corps.append('frais_id', basculer.dataset.frais);
		actionFrais(corps);
	});
	/* ---- Frais supplémentaires : condition et semestres ------------------- */

	/** Ligne du tableau des frais qui contient cet élément. */
	function ligneFrais(el) {
		return el.closest ? el.closest('.js-frais-ligne') : null;
	}

	/* Un réglage modifié active son bouton : rien n'est envoyé à chaque clic
	   sur une case, comme pour les montants du barème. */
	document.addEventListener('change', function (e) {
		if (!e.target.classList) { return; }
		if (!e.target.classList.contains('js-frais-condition')
			&& !e.target.classList.contains('js-frais-semestre')) { return; }

		var ligne = ligneFrais(e.target);
		var bouton = ligne ? ligne.querySelector('.js-frais-regler') : null;
		if (bouton) { bouton.disabled = false; }
	});
	/* ---- Navigation sans rechargement ------------------------------------- */

	var GRILLE = (window.APP_BASE || '') + '/app/finance-bareme.grille.php';
	var enChargement = false;

	/** Nombre de montants saisis et non encore enregistrés, toutes filières. */
	function modificationsEnAttente() {
		var z = zone();
		return z ? z.querySelectorAll('.js-bareme-champ[data-modifie="1"]').length : 0;
	}

	/**
	 * Charge la grille d'une autre année ou d'un autre semestre.
	 *
	 * Remplace le rechargement complet : la page porte plus de neuf cents
	 * champs, et recharger renvoyait en haut en faisant perdre l'endroit où
	 * l'on travaillait.
	 *
	 * @param {boolean} empiler false quand l'appel vient de popstate, pour ne
	 *                          pas réempiler une entrée d'historique.
	 */
	function charger(params, empiler) {
		if (enChargement) { return; }

		// Les montants saisis seraient perdus : on demande avant de changer.
		var enAttente = modificationsEnAttente();
		if (enAttente > 0) {
			var suite = params.confirme === true || demanderPuisCharger(params, empiler,
				enAttente + ' montant' + (enAttente > 1 ? 's' : '')
				+ ' non enregistré' + (enAttente > 1 ? 's' : '') + '.\n\n'
				+ 'Changer de période les abandonnera. Continuer ?'
			);
			if (!suite) { return; }
		}

		enChargement = true;
		var contenu = document.getElementById('baremeContenu');
		if (contenu) { contenu.style.opacity = '.45'; }

		var requete = new URLSearchParams();
		requete.set('annee', params.annee);
		requete.set('semestre', String(params.semestre));
		if (params.base) { requete.set('base', '1'); }

		fetch(GRILLE + '?' + requete.toString(), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () {
				return { ok: false, message: 'Réponse illisible du serveur (' + r.status + ').' };
			});
		}).then(function (charge) {
			enChargement = false;
			if (contenu) { contenu.style.opacity = ''; }

			if (!charge || !charge.ok) {
				signalerEchec(charge);
				return;
			}

			var barre = document.getElementById('baremeBarreDyn');
			if (barre) { barre.innerHTML = charge.barre; }
			if (contenu) { contenu.innerHTML = charge.grille; }

			// Le sélecteur reste en place : on l'aligne sur ce que le serveur a
			// réellement retenu, qui peut différer de ce qui a été demandé.
			var selecteur = document.getElementById('baremeAnnee');
			if (selecteur && selecteur.value !== charge.annee) {
				selecteur.value = charge.annee;
			}

			if (empiler !== false) {
				window.history.pushState(
					{ annee: charge.annee, semestre: charge.semestre, base: charge.base },
					'',
					charge.url
				);
			}

			// Les boutons des nouvelles cartes partent désactivés.
			var z = zone();
			if (z) {
				Array.prototype.forEach.call(z.querySelectorAll('.finance-card'), rafraichirCarte);
			}

			// La barre est collante : on revient à son niveau plutôt que de
			// laisser le lecteur au milieu de la grille précédente.
			var conteneur = document.querySelector('.finance-scroll');
			if (conteneur && conteneur.scrollTop > 0) { conteneur.scrollTop = 0; }
		}).catch(function () {
			enChargement = false;
			if (contenu) { contenu.style.opacity = ''; }
			signalerEchec(null);
		});
	}

	/** Paramètres décrits par l'URL courante, pour l'état initial et popstate. */
	function paramsDeLUrl() {
		var url = new URL(window.location.href);
		var z = zone();

		return {
			annee: url.searchParams.get('annee')
				|| (document.getElementById('baremeAnnee') || {}).value
				|| '',
			semestre: url.searchParams.get('semestre')
				|| (z ? z.dataset.semestre : 1),
			base: url.searchParams.get('base') === '1'
		};
	}

	/* Changement d'année. */
	document.addEventListener('change', function (e) {
		if (e.target.id !== 'baremeAnnee') { return; }

		var courant = paramsDeLUrl();
		charger({
			// Le semestre est conservé : on compare souvent la même période
			// d'une année à l'autre.
			annee: e.target.value,
			semestre: courant.semestre,
			base: courant.base
		}, true);
	});

	/* Onglets de semestre et barème de base. Les liens gardent leur href : sans
	   JavaScript, ou sur un clic avec Ctrl / molette, la navigation classique
	   fonctionne toujours. */
	document.addEventListener('click', function (e) {
		if (!e.target.closest) { return; }
		var onglet = e.target.closest('.js-bareme-onglet');
		if (!onglet) { return; }

		// Ouverture dans un nouvel onglet : on laisse le navigateur faire.
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) { return; }

		e.preventDefault();
		charger({
			annee: onglet.dataset.annee,
			semestre: onglet.dataset.semestre,
			base: onglet.dataset.base === '1'
		}, true);
	});

	/* Boutons précédent / suivant du navigateur. Sans cela les pushState
	   empilés laisseraient l'URL changer sans que le contenu suive. */
	window.addEventListener('popstate', function (e) {
		var p = e.state || paramsDeLUrl();
		charger({ annee: p.annee, semestre: p.semestre, base: p.base }, false);
	});

	/* ---- État initial ----------------------------------------------------- */

	document.addEventListener('DOMContentLoaded', function () {
		var z = zone();
		if (!z) { return; }
		Array.prototype.forEach.call(z.querySelectorAll('.finance-card'), rafraichirCarte);

		// L'entrée d'historique de départ porte son état : un retour arrière
		// depuis une autre période retrouve ainsi la bonne grille.
		var p = paramsDeLUrl();
		window.history.replaceState(
			{ annee: p.annee, semestre: p.semestre, base: p.base },
			'',
			window.location.href
		);
	});
})();
