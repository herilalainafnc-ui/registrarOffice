/*
 * Pilotage des listes du back-office (etudiants, cours, enseignants).
 *
 * La recherche rapide de la barre du haut (src/js/recherche.js), les
 * filtres, le tri et « Afficher la suite » agissent sur UN SEUL etat,
 * applique en entier a chaque fois : trier garde les filtres, filtrer garde
 * la recherche. L etat est inscrit dans l adresse de la page (lien
 * partageable, conserve au rechargement).
 *
 * Le serveur : init/.listes/liste.php, decrit par data/listes.php.
 */
(function () {
	'use strict';

	var cfgEl = document.getElementById('liste-config');
	if (!cfgEl) { return; }
	var cfg = JSON.parse(cfgEl.textContent);

	/* Le compteur du pied (init/head.php) s efface devant ce pilote. */
	window.listePilotee = true;

	var $ = function (id) { return document.getElementById(id); };
	var table       = $('liste-table');
	var corps       = $('liste-corps');
	var barre       = $('liste-barre');
	var suite       = $('liste-suite');
	var suiteBouton = $('liste-suite-bouton');
	var triLibelle  = $('listeTriLibelle');
	var triMenu     = $('listeTriMenu');
	var filtresMenu = $('listeFiltresMenu');
	var filtresNb   = $('listeFiltresCompte');
	var pied        = $('footerInfo');
	var boite      = table ? table.closest('#all-std, #all-cours, #all-prof') : null;
	var nbColonnes  = table ? table.querySelectorAll('thead th').length : 1;

	var etat = JSON.parse(JSON.stringify(cfg.initial.etat));
	if (Array.isArray(etat.filtres)) { etat.filtres = {}; }
	var reponse = cfg.initial;
	var enCours = null;

	/* ------------------------------------------------------------ outils */

	function echapper(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function nombre(n) { return Number(n).toLocaleString('fr-FR'); }
	function nom(n) { return n > 1 ? cfg.pluriel : cfg.singulier; }
	function nbFiltres() {
		return Object.keys(etat.filtres).reduce(function (s, k) { return s + etat.filtres[k].length; }, 0);
	}
	function libelleOption(cle, v) {
		var f = reponse.facettes && reponse.facettes[cle];
		if (f) { for (var i = 0; i < f.options.length; i++) { if (f.options[i].v === v) { return f.options[i].l; } } }
		return v;
	}

	/* ------------------------------------------------------------- etat */

	function parametres(page) {
		var p = new URLSearchParams();
		if (etat.q) { p.set('q', etat.q); }
		Object.keys(etat.filtres).forEach(function (k) {
			etat.filtres[k].forEach(function (v) { p.append(k, v); });
		});
		var defaut = etat.tri === cfg.triDefaut && etat.sens === cfg.tris[etat.tri].sens;
		if (!defaut) { p.set('tri', etat.tri); p.set('sens', etat.sens); }
		if (page) { p.set('page', page); }
		return p;
	}

	function majAdresse() {
		var s = parametres(0).toString();
		try { history.replaceState(null, '', location.pathname + (s ? '?' + s : '') + location.hash); } catch (e) {}
	}

	function appliquer() {
		etat.page = 0;
		majAdresse();
		charger(false);
	}

	function charger(ajouter) {
		if (enCours) { enCours.abort(); }
		var ctl = new AbortController();
		enCours = ctl;
		var page = ajouter ? etat.page + 1 : 0;
		var p = parametres(page);
		p.set('liste', cfg.liste);
		table.setAttribute('aria-busy', 'true');
		table.classList.add('liste-charge');
		if (ajouter) { suiteBouton.disabled = true; }

		fetch(cfg.url + '?' + p.toString(), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
			signal: ctl.signal
		})
		.then(function (r) {
			if (r.status === 401) { location.href = (window.APP_BASE || '') + '/login'; throw new Error('session'); }
			return r.json().then(function (j) {
				if (!r.ok || !j.ok) { throw new Error(j.message || 'La liste n’a pas pu être chargée.'); }
				return j;
			});
		})
		.then(function (j) {
			etat.page = page;
			if (ajouter) {
				corps.insertAdjacentHTML('beforeend', j.html);
			} else {
				corps.innerHTML = j.html;
				if (boite) { boite.scrollTop = 0; }
			}
			reponse = j;
			rendre();
		})
		.catch(function (e) {
			if (e.name === 'AbortError' || e.message === 'session') { return; }
			if (window.Toast) { Toast.error(e.message); }
		})
		.finally(function () {
			if (enCours !== ctl) { return; }
			enCours = null;
			table.removeAttribute('aria-busy');
			table.classList.remove('liste-charge');
			suiteBouton.disabled = false;
		});
	}

	/* ------------------------------------------------------------ rendu */

	function rendre() {
		rendreBarre();
		rendreEntetes();
		rendreSuite();
		rendreTriMenu();
		rendreFiltresMenu();
		if (pied) { pied.textContent = nombre(reponse.total) + ' ' + nom(reponse.total); }

		if (!reponse.total) {
			var filtre = nbFiltres() || etat.q;
			corps.innerHTML = '<tr class="liste-vide"><td colspan="' + nbColonnes + '">'
				+ '<i class="bi bi-search" aria-hidden="true"></i>'
				+ '<strong>Aucun ' + echapper(cfg.singulier) + ' ne correspond.</strong>'
				+ (filtre ? '<button type="button" class="liste-lien" data-action="tout-effacer">Retirer la recherche et les filtres</button>' : '')
				+ '</td></tr>';
		}
	}

	/* La barre au-dessus du tableau : combien, et selon quoi. */
	function rendreBarre() {
		var puces = [];
		if (etat.q) {
			puces.push('<button type="button" class="liste-puce" data-action="sans-recherche" title="Retirer la recherche">'
				+ '<i class="bi bi-search" aria-hidden="true"></i>« ' + echapper(etat.q) + ' »<i class="bi bi-x" aria-hidden="true"></i></button>');
		}
		Object.keys(etat.filtres).forEach(function (k) {
			etat.filtres[k].forEach(function (v) {
				puces.push('<button type="button" class="liste-puce" data-action="sans-filtre" data-k="' + echapper(k) + '" data-v="' + echapper(v) + '"'
					+ ' title="Retirer ce filtre"><span>' + echapper(cfg.filtres[k] || k) + ' :</span> ' + echapper(libelleOption(k, v))
					+ '<i class="bi bi-x" aria-hidden="true"></i></button>');
			});
		});
		var n = reponse.total;
		var html = '<div class="liste-barre__compte"><b>' + nombre(n) + '</b> ' + echapper(nom(n))
			+ (puces.length ? (n > 1 ? ' correspondent' : ' correspond') : '') + '</div>';
		if (puces.length) {
			html += '<div class="liste-barre__puces">' + puces.join('')
				+ (puces.length > 1 ? '<button type="button" class="liste-lien" data-action="tout-effacer">Tout effacer</button>' : '')
				+ '</div>';
		}
		html += '<div class="liste-barre__tri">Trié par <b>' + echapper(cfg.tris[etat.tri].libelle) + '</b> '
			+ '<i class="bi ' + (etat.sens === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') + '" aria-hidden="true"></i>'
			+ '<span class="sr-only">' + (etat.sens === 'asc' ? 'croissant' : 'décroissant') + '</span></div>';
		barre.innerHTML = html;
	}

	/* En-tetes : la colonne triee porte sa fleche, les autres une double. */
	function rendreEntetes() {
		table.querySelectorAll('thead .liste-tri').forEach(function (b) {
			var actif = b.getAttribute('data-tri') === etat.tri;
			var th = b.closest('th');
			b.classList.toggle('actif', actif);
			th.setAttribute('aria-sort', actif ? (etat.sens === 'asc' ? 'ascending' : 'descending') : 'none');
			var i = b.querySelector('i');
			i.className = 'bi ' + (actif ? (etat.sens === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up');
		});
	}

	function rendreSuite() {
		var reste = reponse.reste;
		suite.hidden = !reste;
		if (!reste) { return; }
		var lot = Math.min(reste, cfg.parPage);
		suiteBouton.innerHTML = '<i class="bi bi-chevron-down" aria-hidden="true"></i>'
			+ (reste > cfg.parPage ? 'Afficher les ' + lot + ' suivants' : 'Afficher les ' + lot + ' derniers')
			+ '<span>' + nombre(reponse.affiches) + ' sur ' + nombre(reponse.total) + '</span>';
	}

	/* Menu « Trier » : la colonne, puis le sens. */
	function rendreTriMenu() {
		if (triLibelle) {
			triLibelle.textContent = etat.tri === cfg.triDefaut && etat.sens === cfg.tris[etat.tri].sens
				? 'Trier' : 'Tri : ' + cfg.tris[etat.tri].libelle;
		}
		if (!triMenu) { return; }
		var html = '<div class="liste-menu__titre">Trier par</div>';
		Object.keys(cfg.tris).forEach(function (k) {
			var actif = k === etat.tri;
			html += '<button type="button" class="liste-menu__option' + (actif ? ' actif' : '') + '" data-action="tri" data-tri="' + echapper(k) + '"'
				+ ' role="menuitemradio" aria-checked="' + actif + '">'
				+ '<i class="bi ' + (actif ? 'bi-check2' : '') + '" aria-hidden="true"></i>' + echapper(cfg.tris[k].libelle) + '</button>';
		});
		html += '<div class="liste-menu__separateur"></div><div class="liste-menu__titre">Ordre</div>'
			+ '<div class="liste-menu__sens">'
			+ '<button type="button" data-action="sens" data-sens="asc" class="' + (etat.sens === 'asc' ? 'actif' : '') + '" aria-pressed="' + (etat.sens === 'asc') + '"><i class="bi bi-arrow-up" aria-hidden="true"></i>Croissant</button>'
			+ '<button type="button" data-action="sens" data-sens="desc" class="' + (etat.sens === 'desc' ? 'actif' : '') + '" aria-pressed="' + (etat.sens === 'desc') + '"><i class="bi bi-arrow-down" aria-hidden="true"></i>Décroissant</button>'
			+ '</div>';
		triMenu.innerHTML = html;
	}

	/* Les options cochees remontent en tete de leur groupe a l ouverture du
	   panneau (sinon, cachees plus bas dans une liste qui defile). Tant que
	   le panneau est ouvert, l ordre ne bouge plus : l option cliquee ne
	   doit pas sauter sous la souris. */
	var ordrePrecedent = {};
	function ordonner(k, options) {
		var liste;
		if (filtresMenu.classList.contains('show') && ordrePrecedent[k]) {
			var pos = {};
			ordrePrecedent[k].forEach(function (v, i) { pos[v] = i; });
			liste = options.slice().sort(function (a, b) {
				return (a.v in pos ? pos[a.v] : 1e6) - (b.v in pos ? pos[b.v] : 1e6);
			});
		} else {
			liste = options.filter(function (o) { return o.on; }).concat(options.filter(function (o) { return !o.on; }));
		}
		ordrePrecedent[k] = liste.map(function (o) { return o.v; });
		return liste;
	}

	/* Menu « Filtres » : chaque option dit combien de lignes elle donnerait. */
	function rendreFiltresMenu() {
		var n = nbFiltres();
		if (filtresNb) { filtresNb.hidden = !n; filtresNb.textContent = n; }
		if (!filtresMenu) { return; }

		var focus = document.activeElement && filtresMenu.contains(document.activeElement)
			? { k: document.activeElement.getAttribute('data-k'), v: document.activeElement.getAttribute('data-v') } : null;
		var ancienDefil = {};
		filtresMenu.querySelectorAll('.liste-filtre__options').forEach(function (o) { ancienDefil[o.getAttribute('data-k')] = o.scrollTop; });

		var html = '<div class="liste-filtres__tete"><span>Filtres</span>'
			+ (n ? '<button type="button" class="liste-lien" data-action="filtres-effacer">Tout effacer</button>' : '')
			+ '</div><div class="liste-filtres__corps">';
		Object.keys(reponse.facettes).forEach(function (k) {
			var f = reponse.facettes[k];
			var actifs = (etat.filtres[k] || []).length;
			html += '<fieldset class="liste-filtre"><legend>' + echapper(f.libelle)
				+ (actifs ? '<button type="button" class="liste-lien" data-action="filtre-effacer" data-k="' + echapper(k) + '">Effacer</button>' : '')
				+ '</legend>';
			if (!f.options.length) {
				html += '<p class="liste-filtre__aide">' + echapper(f.aide || 'Aucune valeur.') + '</p>';
			} else {
				html += '<div class="liste-filtre__options" data-k="' + echapper(k) + '">';
				ordonner(k, f.options).forEach(function (o) {
					var vide = !o.n && !o.on;
					html += '<label class="liste-filtre__option' + (vide ? ' vide' : '') + '">'
						+ '<input type="checkbox" data-k="' + echapper(k) + '" data-v="' + echapper(o.v) + '"' + (o.on ? ' checked' : '') + (vide ? ' disabled' : '') + '>'
						+ '<span>' + echapper(o.l) + '</span><em>' + nombre(o.n) + '</em></label>';
				});
				html += '</div>';
			}
			html += '</fieldset>';
		});
		html += '</div><div class="liste-filtres__pied"><b>' + nombre(reponse.total) + '</b> ' + echapper(nom(reponse.total)) + '</div>';
		filtresMenu.innerHTML = html;

		filtresMenu.querySelectorAll('.liste-filtre__options').forEach(function (o) {
			var d = ancienDefil[o.getAttribute('data-k')];
			if (d) { o.scrollTop = d; }
		});
		if (focus && focus.k) {
			var cible = filtresMenu.querySelector('input[data-k="' + CSS.escape(focus.k) + '"][data-v="' + CSS.escape(focus.v || '') + '"]');
			if (cible) { cible.focus({ preventScroll: true }); }
		}
	}

	/* --------------------------------------------------------- actions */

	function action(e) {
		var b = e.target.closest('[data-action]');
		if (!b) { return; }
		var a = b.getAttribute('data-action');
		if (a === 'tri') {
			var k = b.getAttribute('data-tri');
			if (etat.tri === k) { etat.sens = etat.sens === 'asc' ? 'desc' : 'asc'; }
			else { etat.tri = k; etat.sens = cfg.tris[k].sens; }
		} else if (a === 'sens') {
			etat.sens = b.getAttribute('data-sens');
		} else if (a === 'sans-recherche') {
			etat.q = '';
		} else if (a === 'sans-filtre' || a === 'filtre-effacer') {
			var cle = b.getAttribute('data-k');
			if (a === 'filtre-effacer') { delete etat.filtres[cle]; }
			else {
				etat.filtres[cle] = (etat.filtres[cle] || []).filter(function (v) { return v !== b.getAttribute('data-v'); });
				if (!etat.filtres[cle].length) { delete etat.filtres[cle]; }
			}
			nettoyerDependants();
		} else if (a === 'filtres-effacer') {
			etat.filtres = {};
		} else if (a === 'tout-effacer') {
			etat.filtres = {};
			etat.q = '';
		} else {
			return;
		}
		e.preventDefault();
		appliquer();
	}

	/* Retirer une mention retire les parcours qui en dependaient. */
	function nettoyerDependants() {
		if (!etat.filtres.mention && etat.filtres.parcours) { delete etat.filtres.parcours; }
	}

	barre.addEventListener('click', action);
	corps.addEventListener('click', action);
	if (triMenu) { triMenu.addEventListener('click', action); }
	if (filtresMenu) {
		filtresMenu.addEventListener('click', action);
		var boutonFiltres = document.getElementById('listeFiltresBouton');
		if (boutonFiltres) { boutonFiltres.addEventListener('show.bs.dropdown', rendreFiltresMenu); }
		filtresMenu.addEventListener('change', function (e) {
			var c = e.target;
			if (!c.matches('input[type="checkbox"][data-k]')) { return; }
			var k = c.getAttribute('data-k'), v = c.getAttribute('data-v');
			var liste = etat.filtres[k] || [];
			liste = c.checked ? liste.concat([v]) : liste.filter(function (x) { return x !== v; });
			if (liste.length) { etat.filtres[k] = liste; } else { delete etat.filtres[k]; }
			nettoyerDependants();
			appliquer();
		});
	}

	table.querySelector('thead').addEventListener('click', function (e) {
		var b = e.target.closest('.liste-tri');
		if (!b) { return; }
		var k = b.getAttribute('data-tri');
		if (etat.tri === k) { etat.sens = etat.sens === 'asc' ? 'desc' : 'asc'; }
		else { etat.tri = k; etat.sens = cfg.tris[k].sens; }
		appliquer();
	});

	suiteBouton.addEventListener('click', function () { charger(true); });

	/* La recherche rapide (src/js/recherche.js) lit la recherche du tableau
	   et la remplace par ici : son option « Filtrer la liste » alimente le
	   meme etat, filtres et tri compris. */
	window.listeRecherche = {
		pluriel: cfg.pluriel,
		q: function () { return etat.q || ''; },
		appliquer: function (q) {
			q = String(q || '').replace(/\s+/g, ' ').trim();
			if (q === etat.q) { return; }
			etat.q = q;
			appliquer();
		}
	};

	rendre();
})();
