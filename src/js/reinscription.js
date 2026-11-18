/* =============================================================================
   Autorisation de réinscription — interactions

   - filtres : état (compteurs cliquables), recherche, filière, année ;
   - interrupteurs par étudiant, via /api/update-authorization et
     /api/update-dossier, protégés par jeton CSRF ;
   - réinitialisation globale : aperçu chiffré, phrase de confirmation,
     puis bandeau d'annulation ;
   - annulation d'un lot.

   Les étudiants arrivent en données (#rsnDonnees), pas en lignes HTML : la
   page en écrivait 2 120 d'un coup (2,7 Mo, 42 000 nœuds, plus de 4 s de
   styles et de mise en page). Le filtre travaille sur ces données ; seules les
   lignes retenues sont dessinées, par 100, la suite venant au défilement.

   Tout est mis à jour sur place : rien ne recharge la page.
   ========================================================================== */

(function () {
	'use strict';

	var configEl = document.getElementById('rsnConfig');
	var donneesEl = document.getElementById('rsnDonnees');
	if (!configEl || !donneesEl) { return; }

	var CFG = JSON.parse(configEl.textContent);
	var BASE = window.APP_BASE || '';
	var POINT = BASE + '/app/reinscription.save.php';
	var API = {
		authorized_reinscription: BASE + '/api/update-authorization',
		dossier_ok: BASE + '/api/update-dossier'
	};
	var PHRASE = 'reinitialiser';
	var PAR_PAGE = 100;
	var MODIFIABLE = {
		dossier_ok: (CFG.permises || []).indexOf('dossier_ok') !== -1,
		authorized_reinscription: (CFG.permises || []).indexOf('authorized_reinscription') !== -1
	};

	function $(id) { return document.getElementById(id); }

	/* Recherche insensible aux accents et à la casse : « theologie » doit
	   trouver « Théologie ». La clé est calculée une fois, pas à chaque frappe. */
	function normaliser(s) {
		return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
	}

	function echapper(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	/* ---- Les données ------------------------------------------------------ */

	/* [id, matricule, nom, filière, parcours, niveau, résidence, année,
	    autorisé, dossier, trace dossier, trace autorisation] */
	var etudiants = JSON.parse(donneesEl.textContent).map(function (r) {
		var o = {
			id: String(r[0]), matricule: r[1], nom: r[2], filiere: r[3], niveau: r[5],
			residence: r[6], annee: r[7],
			authorized_reinscription: r[8] === 1, dossier_ok: r[9] === 1,
			trace: { dossier_ok: r[10], authorized_reinscription: r[11] }
		};
		o.cle = normaliser(r[1] + ' ' + r[2] + ' ' + r[3] + ' ' + r[4]);
		o.etat = etatDe(o.authorized_reinscription, o.dossier_ok);
		return o;
	});
	var parId = {};
	etudiants.forEach(function (o) { parId[o.id] = o; });

	/* Lignes dessinées, par identifiant : pour les mettre à jour sur place. */
	var dessinees = {};

	function etatDe(autorise, dossier) {
		if (autorise && dossier) { return 'prets'; }
		if (dossier) { return 'attente_caisse'; }
		if (autorise) { return 'attente_dossier'; }
		return 'aucun';
	}

	/* ---- Échanges avec le serveur ----------------------------------------- */

	function poster(url, champs) {
		var corps = new FormData();
		Object.keys(champs).forEach(function (k) {
			var v = champs[k];
			if (Array.isArray(v)) {
				v.forEach(function (x) { corps.append(k + '[]', x); });
			} else {
				corps.append(k, v);
			}
		});
		corps.append('csrf_token', window.CSRF_TOKEN || '');

		return fetch(url, {
			method: 'POST',
			body: corps,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () {
				return { ok: false, success: false, message: 'Réponse illisible du serveur (' + r.status + ').' };
			});
		});
	}

	function messageEchec(charge, repli) {
		if (charge && charge.csrf_expire) {
			return 'Jeton de sécurité expiré. Rechargez la page et réessayez.';
		}
		return (charge && charge.message) || repli;
	}

	function notifier(type, message) {
		if (window.Toast && typeof window.Toast[type] === 'function') {
			window.Toast[type](message);
		} else if (window.console) {
			console.warn(message);
		}
	}

	/* ---- Une ligne -------------------------------------------------------- */

	function cellule(o, col) {
		var valeur = o[col];
		var etat = valeur ? 'Oui' : 'Non';
		var controle;
		if (MODIFIABLE[col]) {
			var libelle = col === 'dossier_ok' ? 'Dossier validé' : 'Réinscription autorisée';
			controle = '<label class="rsn-bascule"><input type="checkbox" role="switch" class="js-rsn-bascule"'
				+ ' data-colonne="' + col + '" data-id="' + echapper(o.id) + '"'
				+ ' aria-label="' + echapper(libelle + ' — ' + o.nom) + '"' + (valeur ? ' checked' : '') + '>'
				+ '<span class="rsn-bascule__texte">' + etat + '</span></label>';
		} else {
			controle = '<span class="rsn-etiquette' + (valeur ? ' rsn-etiquette--oui' : '') + '" data-colonne="' + col + '">'
				+ etat + '</span>';
		}
		return '<td class="rsn-centre">' + controle
			+ '<span class="rsn-trace" data-trace="' + col + '">' + echapper(o.trace[col]) + '</span></td>';
	}

	function ligneHTML(o) {
		return '<tr class="rsn-ligne" data-id="' + echapper(o.id) + '">'
			+ '<td><span class="rsn-nom">' + echapper(o.nom) + '</span>'
			+ '<span class="rsn-matricule">' + echapper(o.matricule) + '</span></td>'
			+ '<td><span class="rsn-filiere">' + echapper(o.filiere) + '</span>'
			+ '<span class="rsn-niveau">' + echapper(o.niveau) + '</span></td>'
			+ '<td>' + echapper(o.residence) + '</td>'
			+ '<td>' + echapper(o.annee) + '</td>'
			+ cellule(o, 'dossier_ok')
			+ cellule(o, 'authorized_reinscription')
			+ '</tr>';
	}

	/**
	 * Écrit un drapeau : dans les données, et dans la ligne si elle est
	 * dessinée (interrupteur ou étiquette, texte, trace « par · le »).
	 * @param {string|undefined} trace undefined = laisser la trace en place
	 */
	function ecrireDrapeau(o, col, valeur, trace) {
		o[col] = valeur;
		if (trace !== undefined) { o.trace[col] = trace; }
		o.etat = etatDe(o.authorized_reinscription, o.dossier_ok);

		var ligne = dessinees[o.id];
		if (!ligne) { return; }

		var bascule = ligne.querySelector('.js-rsn-bascule[data-colonne="' + col + '"]');
		if (bascule) {
			bascule.checked = valeur;
			var texte = bascule.nextElementSibling;
			if (texte) { texte.textContent = valeur ? 'Oui' : 'Non'; }
		}
		var etiquette = ligne.querySelector('.rsn-etiquette[data-colonne="' + col + '"]');
		if (etiquette) {
			etiquette.textContent = valeur ? 'Oui' : 'Non';
			etiquette.classList.toggle('rsn-etiquette--oui', valeur);
		}
		if (trace !== undefined) {
			var zone = ligne.querySelector('[data-trace="' + col + '"]');
			if (zone) { zone.textContent = trace; }
		}
	}

	/* Éclair bref sur la ligne modifiée : relance l'animation même si la classe
	   est déjà là (retrait, lecture forcée, rajout). */
	function signalerLigne(ligne) {
		ligne.classList.remove('rsn-ligne--modifiee');
		void ligne.offsetWidth;
		ligne.classList.add('rsn-ligne--modifiee');
	}

	/* ---- Compteurs -------------------------------------------------------- */

	function appliquerStats(stats) {
		Object.keys(stats).forEach(function (k) {
			Array.prototype.forEach.call(document.querySelectorAll('[data-stat="' + k + '"]'), function (el) {
				el.textContent = Number(stats[k]).toLocaleString('fr-FR');
			});
		});
	}

	/** Recompte depuis les données : utilisé après un geste unitaire. */
	function recompter() {
		var s = { total: etudiants.length, prets: 0, attente_caisse: 0, attente_dossier: 0, aucun: 0 };
		etudiants.forEach(function (o) { s[o.etat] = (s[o.etat] || 0) + 1; });
		appliquerStats(s);
	}

	/* ---- Filtres et affichage par tranches -------------------------------- */

	var recherche = $('rsnRecherche');
	var selFiliere = $('rsnFiliere');
	var selAnnee = $('rsnAnnee');
	var compte = $('rsnCompte');
	var vide = $('rsnVide');
	var corps = $('rsnCorps');
	var cadre = corps ? corps.closest('.rsn-cadre') : null;
	var suite = $('rsnSuite');
	var suiteBtn = $('rsnSuiteBtn');
	var filtreEtat = 'tous';
	var retenus = [];
	var affiches = 0;

	/** Dessine la tranche suivante des étudiants retenus. */
	function dessinerSuite() {
		if (!corps) { return; }
		var tranche = retenus.slice(affiches, affiches + PAR_PAGE);
		if (tranche.length === 0) { majSuite(); return; }

		var gabarit = document.createElement('tbody');
		gabarit.innerHTML = tranche.map(ligneHTML).join('');
		var lignes = Array.prototype.slice.call(gabarit.children);
		lignes.forEach(function (tr) { dessinees[tr.dataset.id] = tr; });
		var fragment = document.createDocumentFragment();
		lignes.forEach(function (tr) { fragment.appendChild(tr); });
		corps.appendChild(fragment);

		affiches += tranche.length;
		majSuite();
	}

	function majSuite() {
		var reste = retenus.length - affiches;
		if (suite) { suite.hidden = reste <= 0; }
		if (suiteBtn && reste > 0) {
			var n = Math.min(PAR_PAGE, reste);
			suiteBtn.textContent = 'Afficher les ' + n.toLocaleString('fr-FR') + ' suivants'
				+ ' (encore ' + reste.toLocaleString('fr-FR') + ')';
		}
		if (compte) {
			var total = etudiants.length.toLocaleString('fr-FR');
			compte.textContent = retenus.length === etudiants.length
				? total + ' étudiant' + (etudiants.length > 1 ? 's' : '')
				: retenus.length.toLocaleString('fr-FR') + ' sur ' + total;
		}
	}

	function filtrer() {
		var q = recherche ? normaliser(recherche.value.trim()) : '';
		var filiere = selFiliere ? selFiliere.value : '';
		var annee = selAnnee ? selAnnee.value : '';

		retenus = etudiants.filter(function (o) {
			return (filtreEtat === 'tous' || o.etat === filtreEtat)
				&& (!filiere || o.filiere === filiere)
				&& (!annee || o.annee === annee)
				&& (!q || o.cle.indexOf(q) !== -1);
		});

		if (corps) { corps.textContent = ''; }
		dessinees = {};
		affiches = 0;
		if (cadre) { cadre.scrollTop = 0; }
		if (vide) { vide.hidden = retenus.length !== 0; }
		dessinerSuite();
	}

	/* La suite vient d'elle-même quand on approche du bas du cadre ; le bouton
	   reste pour le clavier et les navigateurs sans IntersectionObserver. */
	if (suiteBtn) { suiteBtn.addEventListener('click', dessinerSuite); }
	if (suite && cadre && 'IntersectionObserver' in window) {
		new IntersectionObserver(function (entrees) {
			if (entrees[0].isIntersecting && affiches < retenus.length) { dessinerSuite(); }
		}, { root: cadre, rootMargin: '0px 0px 600px 0px' }).observe(suite);
	}

	var minuterie = null;
	if (recherche) {
		recherche.addEventListener('input', function () {
			clearTimeout(minuterie);
			minuterie = setTimeout(filtrer, 120);
		});
	}
	if (selFiliere) { selFiliere.addEventListener('change', filtrer); }
	if (selAnnee) { selAnnee.addEventListener('change', filtrer); }

	Array.prototype.forEach.call(document.querySelectorAll('.rsn-etat'), function (bouton) {
		bouton.addEventListener('click', function () {
			filtreEtat = bouton.dataset.filtre;
			Array.prototype.forEach.call(document.querySelectorAll('.rsn-etat'), function (b) {
				b.setAttribute('aria-pressed', b === bouton ? 'true' : 'false');
			});
			filtrer();
		});
	});

	var effacer = $('rsnEffacer');
	if (effacer) {
		effacer.addEventListener('click', function () {
			if (recherche) { recherche.value = ''; }
			if (selFiliere) { selFiliere.value = ''; }
			if (selAnnee) { selAnnee.value = ''; }
			var tous = document.querySelector('.rsn-etat[data-filtre="tous"]');
			if (tous) { tous.click(); } else { filtrer(); }
		});
	}

	/* ---- Interrupteurs unitaires ------------------------------------------ */

	document.addEventListener('change', function (e) {
		var bascule = e.target;
		if (!bascule.classList || !bascule.classList.contains('js-rsn-bascule')) { return; }

		var col = bascule.dataset.colonne;
		var valeur = bascule.checked;
		var o = parId[bascule.dataset.id];
		if (!o) { return; }
		var champs = { student_id: o.id };
		champs[col] = valeur ? 1 : 0;

		bascule.disabled = true;

		poster(API[col], champs).then(function (charge) {
			bascule.disabled = false;
			if (!charge || !charge.success) {
				bascule.checked = !valeur;
				notifier('error', messageEchec(charge, "La modification n'a pas été enregistrée."));
				return;
			}

			var nom = col === 'dossier_ok' ? charge.dossier_checked_by_name : charge.authorized_by_name;
			var le = col === 'dossier_ok' ? charge.dossier_checked_date : charge.authorized_date;
			ecrireDrapeau(o, col, valeur, nom ? nom + ' · ' + String(le || '').split(' ')[0] : '');
			if (dessinees[o.id]) { signalerLigne(dessinees[o.id]); }
			/* Pas de refiltrage ici : sous le filtre « Attente caisse », la ligne
			   qu'on vient d'autoriser disparaîtrait sous le curseur. Les
			   compteurs, eux, suivent immédiatement. */
			recompter();
			notifier('success', charge.message);
		}).catch(function () {
			bascule.disabled = false;
			bascule.checked = !valeur;
			notifier('error', 'Connexion perdue : la modification n\'a pas été enregistrée.');
		});
	});

	/* ---- Réinitialisation globale ----------------------------------------- */

	var voile = $('rsnVoile');
	var ouvrir = $('rsnOuvrirReinit');
	var confirmer = $('rsnConfirmer');
	var fermerBtn = $('rsnFermer');
	var saisie = $('rsnConfirmation');
	var portee = $('rsnPortee');
	var dernierFocus = null;

	function colonnesChoisies() {
		return Array.prototype.slice.call(document.querySelectorAll('.js-rsn-colonne'))
			.filter(function (c) { return c.checked && !c.disabled; })
			.map(function (c) { return c.value; });
	}

	function phraseSaisie() {
		return normaliser(saisie ? saisie.value.trim() : '') === PHRASE;
	}

	function majConfirmer() {
		if (confirmer) { confirmer.disabled = !(phraseSaisie() && colonnesChoisies().length > 0); }
	}

	/** Remplit la portée : combien d'étudiants, combien perdent le droit de se réinscrire. */
	function chargerApercu() {
		if (!portee) { return; }
		portee.textContent = 'Calcul de la portée…';

		var cols = colonnesChoisies();
		if (cols.length === 0) {
			portee.textContent = 'Choisissez au moins une colonne.';
			return;
		}

		poster(POINT, { action: 'apercu', colonnes: cols }).then(function (c) {
			if (!c || !c.ok) {
				portee.textContent = messageEchec(c, 'Portée indisponible.');
				return;
			}

			Object.keys(c.par_colonne || {}).forEach(function (col) {
				var cible = document.querySelector('[data-apercu="' + col + '"]');
				if (cible) { cible.textContent = Number(c.par_colonne[col]).toLocaleString('fr-FR'); }
			});

			// Construit en nœuds : aucune donnée n'est injectée comme HTML.
			portee.textContent = '';
			var b1 = document.createElement('b');
			b1.textContent = Number(c.etudiants).toLocaleString('fr-FR') + ' étudiant' + (c.etudiants > 1 ? 's' : '');
			portee.appendChild(b1);
			portee.appendChild(document.createTextNode(' seront remis à « non coché ». '));
			if (c.bloques > 0) {
				var b2 = document.createElement('b');
				b2.textContent = Number(c.bloques).toLocaleString('fr-FR');
				portee.appendChild(b2);
				portee.appendChild(document.createTextNode(
					' peuvent se réinscrire aujourd\'hui et ne le pourront plus tant qu\'ils n\'auront pas été cochés à nouveau.'
				));
			}
		}).catch(function () {
			portee.textContent = 'Portée indisponible : connexion perdue.';
		});
	}

	function ouvrirDialogue() {
		if (!voile) { return; }
		dernierFocus = document.activeElement;
		voile.hidden = false;
		if (saisie) { saisie.value = ''; }
		majConfirmer();
		chargerApercu();
		var premier = voile.querySelector('.js-rsn-colonne:not(:disabled)') || saisie;
		if (premier) { premier.focus(); }
	}

	function fermerDialogue() {
		if (!voile) { return; }
		voile.hidden = true;
		if (dernierFocus && typeof dernierFocus.focus === 'function') { dernierFocus.focus(); }
	}

	if (ouvrir) { ouvrir.addEventListener('click', ouvrirDialogue); }
	if (fermerBtn) { fermerBtn.addEventListener('click', fermerDialogue); }
	if (saisie) { saisie.addEventListener('input', majConfirmer); }

	Array.prototype.forEach.call(document.querySelectorAll('.js-rsn-colonne'), function (c) {
		c.addEventListener('change', function () { majConfirmer(); chargerApercu(); });
	});

	if (voile) {
		// Clic sur le voile, hors de la fenêtre : on referme.
		voile.addEventListener('click', function (e) { if (e.target === voile) { fermerDialogue(); } });

		voile.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { e.preventDefault(); fermerDialogue(); return; }

			// Le focus reste dans la fenêtre tant qu'elle est ouverte.
			if (e.key === 'Tab') {
				var focusables = Array.prototype.slice.call(
					voile.querySelectorAll('button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])')
				);
				if (focusables.length === 0) { return; }
				var premier = focusables[0];
				var dernier = focusables[focusables.length - 1];
				if (e.shiftKey && document.activeElement === premier) { e.preventDefault(); dernier.focus(); }
				else if (!e.shiftKey && document.activeElement === dernier) { e.preventDefault(); premier.focus(); }
			}
		});
	}

	if (confirmer) {
		confirmer.addEventListener('click', function () {
			if (!phraseSaisie()) { return; }
			var cols = colonnesChoisies();
			if (cols.length === 0) { return; }

			var libelle = confirmer.innerHTML;
			confirmer.disabled = true;
			confirmer.textContent = 'Réinitialisation…';

			poster(POINT, { action: 'reinitialiser', colonnes: cols, confirmation: saisie.value.trim() })
				.then(function (c) {
					confirmer.innerHTML = libelle;
					if (!c || !c.ok) {
						majConfirmer();
						notifier('error', messageEchec(c, 'La réinitialisation a échoué, rien n\'a été modifié.'));
						return;
					}

					var trace = c.par_nom ? c.par_nom + ' · ' + c.date : '';
					Object.keys(c.par_colonne || {}).forEach(function (col) {
						etudiants.forEach(function (o) {
							if (o[col]) { ecrireDrapeau(o, col, false, trace); }
						});
					});

					appliquerStats(c.stats);
					fermerDialogue();
					filtrer();
					if (c.total > 0) { afficherBandeau(c.lot, c.total, c.par_nom, c.date); }
					notifier('success', c.message);
				})
				.catch(function () {
					confirmer.innerHTML = libelle;
					majConfirmer();
					notifier('error', 'Connexion perdue : impossible de savoir si la réinitialisation a eu lieu. Rechargez la page pour vérifier.');
				});
		});
	}

	/* ---- Bandeau et annulation -------------------------------------------- */

	var bandeau = $('rsnBandeau');
	var bandeauTexte = $('rsnBandeauTexte');
	var annulerLot = $('rsnAnnulerLot');

	function afficherBandeau(lot, total, parNom, date) {
		if (!bandeau || !bandeauTexte || !annulerLot) { return; }
		bandeauTexte.textContent = '';
		var b = document.createElement('b');
		b.textContent = 'Réinitialisation effectuée';
		bandeauTexte.appendChild(b);
		bandeauTexte.appendChild(document.createTextNode(
			' — ' + Number(total).toLocaleString('fr-FR') + ' case(s) remise(s) à « non coché »'
			+ (parNom ? ' par ' + parNom : '') + (date ? ' le ' + date : '') + '.'
		));
		annulerLot.dataset.lot = lot;
		annulerLot.disabled = false;
		bandeau.hidden = false;
	}

	if (annulerLot) {
		annulerLot.addEventListener('click', function () {
			var lot = annulerLot.dataset.lot;
			if (!lot) { return; }

			annulerLot.disabled = true;
			var libelle = annulerLot.innerHTML;
			annulerLot.textContent = 'Annulation…';

			poster(POINT, { action: 'annuler', lot: lot }).then(function (c) {
				annulerLot.innerHTML = libelle;
				if (!c || !c.ok) {
					annulerLot.disabled = false;
					notifier('error', messageEchec(c, 'L\'annulation a échoué.'));
					return;
				}

				var restaures = c.restaures || {};
				var traces = c.traces || {};
				Object.keys(restaures).forEach(function (col) {
					restaures[col].forEach(function (id) {
						var o = parId[String(id)];
						if (!o) { return; }
						var t = traces[col] && traces[col][id] !== undefined ? traces[col][id] : undefined;
						ecrireDrapeau(o, col, true, t);
					});
				});

				appliquerStats(c.stats);
				filtrer();
				if (bandeau) { bandeau.hidden = true; }
				notifier(c.ignores > 0 ? 'warning' : 'success', c.message);
			}).catch(function () {
				annulerLot.innerHTML = libelle;
				annulerLot.disabled = false;
				notifier('error', 'Connexion perdue : l\'annulation n\'a pas pu être confirmée.');
			});
		});
	}

	filtrer();
})();
