/* =============================================================================
   Recherche rapide : Ctrl+K (⌘K sur Mac) ou « / », depuis n'importe quelle page.
   Modele : la recherche de laravel.com (bouton dans la barre du haut, fenetre
   centree, resultats groupes, navigation au clavier).

   Deux sources :
     - les pages du menu, cherchees ici meme dans la barre laterale : le menu ne
       montre que ce que le compte peut ouvrir, la recherche non plus ;
     - les dossiers (etudiants, cours, enseignants), demandes a
       app/recherche.rapide.php, qui applique les droits et le perimetre des listes.

   Les « recents » ne retiennent que des pages, jamais un nom d'etudiant : le
   navigateur d'un bureau partage n'a pas a garder qui a ete consulte.

   Sur une liste ou une fiche (etudiants, cours, enseignants), son groupe passe
   en tete. Sur une liste, la premiere option filtre le tableau
   (window.listeRecherche, src/js/listes.js) : on tape, Entree, comme avant.
   ========================================================================== */
(function () {
	'use strict';

	const config = document.getElementById('rcConfig');
	if (!config) { return; }
	const API = config.dataset.api;
	const DOSSIERS = config.dataset.dossiers === '1';
	const CONTEXTE = config.dataset.contexte || '';
	const CLE_RECENTS = 'rc-recents';
	const estMac = /Mac|iPhone|iPad/.test(navigator.platform || '') || /Mac OS/.test(navigator.userAgent);

	document.querySelectorAll('[data-rc-mod]').forEach(function (k) { k.textContent = estMac ? '⌘' : 'Ctrl'; });

	/* --- Outils ------------------------------------------------------------ */
	const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const plat = (s) => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

	/** Met en evidence chaque mot cherche, sans tenir compte des accents ni de la casse. */
	function surligner(texte, q) {
		const mots = plat(q).split(/\s+/).filter((m) => m.length > 0);
		if (!mots.length) { return esc(texte); }
		/* Texte « aplati » caractere par caractere, avec la position d'origine */
		let aplati = '';
		const origine = [];
		Array.from(String(texte)).forEach(function (c, i) {
			const p = plat(c);
			for (let k = 0; k < p.length; k++) { aplati += p[k]; origine.push(i); }
		});
		const car = Array.from(String(texte));
		const marque = new Array(car.length).fill(false);
		mots.forEach(function (m) {
			let d = aplati.indexOf(m);
			while (d !== -1) {
				for (let k = d; k < d + m.length; k++) { marque[origine[k]] = true; }
				d = aplati.indexOf(m, d + m.length);
			}
		});
		let html = '', ouvert = false;
		car.forEach(function (c, i) {
			if (marque[i] && !ouvert) { html += '<mark>'; ouvert = true; }
			if (!marque[i] && ouvert) { html += '</mark>'; ouvert = false; }
			html += esc(c);
		});
		return html + (ouvert ? '</mark>' : '');
	}

	function lireRecents() {
		try { return JSON.parse(localStorage.getItem(CLE_RECENTS) || '[]').slice(0, 5); } catch (e) { return []; }
	}
	function retenir(page) {
		try {
			const r = lireRecents().filter((x) => x.lien !== page.lien);
			r.unshift({ titre: page.titre, detail: page.detail, lien: page.lien, icone: page.icone });
			localStorage.setItem(CLE_RECENTS, JSON.stringify(r.slice(0, 5)));
		} catch (e) { /* stockage indisponible : sans gravite */ }
	}

	/* --- Les pages du menu ----------------------------------------------------- */
	let pages = null;
	function indexerPages() {
		const vues = new Set();
		const liste = [];
		const ajouter = function (a, titre, section, icone) {
			const lien = a.getAttribute('href');
			if (!lien || lien === '#' || lien.indexOf('javascript:') === 0 || vues.has(lien)) { return; }
			vues.add(lien);
			liste.push({ titre: titre, detail: section, lien: lien, icone: icone || 'bi-arrow-right-short', cle: plat(titre + ' ' + section) });
		};
		let section = '';
		document.querySelectorAll('#sidebarMenu .menu-section-header, #sidebarMenu .menu-item a').forEach(function (el) {
			if (el.classList.contains('menu-section-header')) { section = el.textContent.trim(); return; }
			const titre = el.querySelector('.menu-title');
			const icone = el.querySelector('.menu-icon');
			if (titre) {
				ajouter(el, titre.textContent.trim(), section || 'Menu',
					icone ? Array.from(icone.classList).find((c) => c.indexOf('bi-') === 0) : '');
			}
		});
		/* Le menu du compte (Mon compte, Bulletin academique…) */
		document.querySelectorAll('#sidebarMenu .side-compte a[href]').forEach(function (a) {
			if (a.classList.contains('logOut')) { return; }
			const i = a.querySelector('.bi');
			ajouter(a, a.textContent.trim(), 'Compte', i ? Array.from(i.classList).find((c) => c.indexOf('bi-') === 0) : '');
		});
		return liste;
	}
	function chercherPages(q) {
		pages = pages || indexerPages();
		const mots = plat(q).split(/\s+/).filter(Boolean);
		return pages
			.filter((p) => mots.every((m) => p.cle.indexOf(m) !== -1))
			.map((p) => ({ p: p, rang: plat(p.titre).indexOf(mots[0]) === 0 ? 0 : (plat(p.titre).indexOf(mots[0]) !== -1 ? 1 : 2) }))
			.sort((a, b) => a.rang - b.rang)
			.slice(0, 6)
			.map((x) => x.p);
	}

	/* --- La fenetre -------------------------------------------------------------- */
	let fond, champ, zone, chargement, retour = null, actif = -1, options = [];
	let minuterie = 0, controleur = null, dernier = '';

	function construire() {
		fond = document.createElement('div');
		fond.className = 'rc-fond';
		fond.hidden = true;
		fond.innerHTML =
			'<div class="rc-panneau" role="dialog" aria-modal="true" aria-label="Recherche rapide">'
			+ '<div class="rc-saisie">'
			+ '<i class="bi bi-search" aria-hidden="true"></i>'
			+ '<input type="search" id="rcChamp" role="combobox" aria-expanded="true" aria-controls="rcResultats" aria-autocomplete="list"'
			+ ' autocomplete="off" spellcheck="false" placeholder="' + (DOSSIERS ? 'Un étudiant, un cours, un enseignant, une page…' : 'Une page du menu…') + '">'
			+ '<span class="rc-chargement" hidden aria-hidden="true"></span>'
			+ '<button type="button" class="rc-fermer" aria-label="Fermer la recherche"><kbd>Échap</kbd></button>'
			+ '</div>'
			+ '<div class="rc-resultats" id="rcResultats" role="listbox" aria-label="Résultats"></div>'
			+ '<div class="rc-pied" aria-hidden="true">'
			+ '<span><kbd>↵</kbd> ouvrir</span><span><kbd>↑</kbd><kbd>↓</kbd> naviguer</span><span><kbd>Échap</kbd> fermer</span>'
			+ '</div>'
			+ '</div>';
		document.body.appendChild(fond);
		champ = fond.querySelector('#rcChamp');
		zone = fond.querySelector('#rcResultats');
		chargement = fond.querySelector('.rc-chargement');

		fond.addEventListener('mousedown', function (e) { if (e.target === fond) { fermer(); } });
		fond.querySelector('.rc-fermer').addEventListener('click', fermer);
		champ.addEventListener('input', function () { lancer(champ.value); });
		champ.addEventListener('keydown', clavier);
		zone.addEventListener('mousemove', function (e) {
			const o = e.target.closest('.rc-option');
			if (o) { activer(options.indexOf(o), false); }
		});
		zone.addEventListener('click', function (e) {
			const o = e.target.closest('.rc-option');
			if (o) { choisir(o, e); }
		});
		/* Le focus ne sort pas de la fenetre : Tab va du champ au bouton Échap */
		fond.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && e.target !== champ) { e.preventDefault(); fermer(); return; }
			if (e.key !== 'Tab') { return; }
			const bouton = fond.querySelector('.rc-fermer');
			if (document.activeElement === champ && !e.shiftKey) { e.preventDefault(); bouton.focus(); }
			else { e.preventDefault(); champ.focus(); }
		});
	}

	function ouvrir() {
		if (!fond) { construire(); }
		if (!fond.hidden) { champ.focus(); return; }
		retour = document.activeElement;
		pages = null;                       // le menu a pu changer (zone AJAX)
		fond.hidden = false;
		document.documentElement.classList.add('rc-ouvert');
		/* Sur une liste, on repart de sa recherche : la corriger, ou la remplacer en tapant */
		champ.value = window.listeRecherche ? window.listeRecherche.q() : '';
		lancer(champ.value);
		champ.focus();
		champ.select();
	}

	/* Sur une liste : filtrer le tableau avec ce qui est tape, ou retirer sa recherche */
	function groupeListe(q) {
		const liste = window.listeRecherche;
		if (!liste) { return null; }
		const actuelle = liste.q();
		if (q === '' && actuelle === '') { return null; }
		const option = q === ''
			? { titre: 'Afficher toute la liste des ' + liste.pluriel, detail: 'Retire la recherche « ' + actuelle + ' » du tableau', icone: 'bi-x-circle' }
			: { titre: 'Filtrer la liste des ' + liste.pluriel, detail: '« ' + q + ' », en gardant les filtres et le tri du tableau', icone: 'bi-funnel' };
		option.filtrer = q;
		option.lien = location.pathname + (q ? '?q=' + encodeURIComponent(q) : '');
		return { titre: 'Cette liste', resultats: [option] };
	}
	function fermer() {
		if (!fond || fond.hidden) { return; }
		fond.hidden = true;
		document.documentElement.classList.remove('rc-ouvert');
		if (controleur) { controleur.abort(); }
		clearTimeout(minuterie);
		if (retour && typeof retour.focus === 'function') { retour.focus(); }
	}

	/* --- Recherche ---------------------------------------------------------------- */
	function lancer(q) {
		q = q.trim();
		dernier = q;
		clearTimeout(minuterie);
		if (controleur) { controleur.abort(); controleur = null; }
		const groupesPages = [];
		const liste = groupeListe(q);
		if (liste) { groupesPages.push(liste); }
		if (q === '') {
			const r = lireRecents();
			if (r.length) { groupesPages.push({ titre: 'Récemment ouverts', resultats: r, pages: true }); }
			afficher(groupesPages, q, false);
			return;
		}
		const trouvees = chercherPages(q);
		if (trouvees.length) { groupesPages.push({ titre: 'Pages', resultats: trouvees, pages: true }); }
		const viser = DOSSIERS && q.length >= 2;
		afficher(groupesPages, q, viser);
		if (!viser) { return; }
		chargement.hidden = false;
		minuterie = setTimeout(function () {
			controleur = new AbortController();
			fetch(API + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controleur.signal })
				.then((r) => r.ok ? r.json() : Promise.reject(r.status))
				.then(function (j) {
					if (q !== dernier) { return; }
					/* Le groupe de la page en tete ; sur sa liste, « Voir tout » ferait
					   doublon avec « Filtrer la liste » (et perdrait filtres et tri) */
					const dossiers = (j.groupes || []).slice().sort((a, b) => (b.cle === CONTEXTE) - (a.cle === CONTEXTE));
					const groupes = groupesPages.concat(dossiers.map((g) => ({
						titre: g.titre,
						tout: window.listeRecherche && g.cle === CONTEXTE ? '' : g.tout,
						resultats: g.resultats.map((x) => Object.assign({ icone: g.icone }, x)),
					})));
					afficher(groupes, q, false);
				})
				.catch(function (e) {
					if (e && e.name === 'AbortError') { return; }
					if (q === dernier) { afficher(groupesPages, q, false, 'La recherche dans les dossiers est indisponible. Réessayez dans un instant.'); }
				})
				.finally(function () { if (q === dernier) { chargement.hidden = true; } });
		}, 160);
	}

	function afficher(groupes, q, enAttente, erreur) {
		if (!enAttente) { chargement.hidden = true; }
		let html = '', n = 0;
		groupes.forEach(function (g) {
			html += '<section class="rc-groupe"><div class="rc-groupe__entete"><span>' + esc(g.titre) + '</span>'
				+ (g.tout ? '<a class="rc-groupe__tout" href="' + esc(g.tout) + '">Voir tout</a>' : '') + '</div><ul class="rc-liste" role="group" aria-label="' + esc(g.titre) + '">';
			g.resultats.forEach(function (r) {
				html += '<li><a class="rc-option" role="option" id="rc-opt-' + n + '" aria-selected="false" href="' + esc(r.lien) + '"'
					+ (g.pages ? ' data-page="1"' : '') + (r.filtrer != null ? ' data-filtrer="' + esc(r.filtrer) + '"' : '') + ' data-titre="' + esc(r.titre) + '" data-detail="' + esc(r.detail || '') + '" data-icone="' + esc(r.icone || '') + '">'
					+ '<span class="rc-option__icone" aria-hidden="true"><i class="bi ' + esc(r.icone || 'bi-arrow-right-short') + '"></i></span>'
					+ '<span class="rc-option__texte"><span class="rc-option__titre">' + surligner(r.titre, q) + '</span>'
					+ (r.detail ? '<span class="rc-option__detail">' + surligner(r.detail, q) + '</span>' : '') + '</span>'
					+ '<i class="bi bi-arrow-return-left rc-option__entree" aria-hidden="true"></i></a></li>';
				n++;
			});
			html += '</ul></section>';
		});
		if (erreur) { html += '<p class="rc-attente" role="status">' + esc(erreur) + '</p>'; }
		else if (enAttente) { html += '<p class="rc-attente" role="status">Recherche dans les dossiers…</p>'; }
		if (!n && !enAttente) {
			html += q === ''
				? '<p class="rc-message">' + (DOSSIERS ? 'Tapez un <b>nom</b>, un <b>matricule</b>, un <b>sigle</b> de cours ou une <b>page</b>.' : 'Tapez le nom d’une <b>page</b> du menu.')
					+ '<small>Ouvrez cette recherche de partout avec ' + (estMac ? '⌘' : 'Ctrl') + ' K ou « / ».</small></p>'
				: '<p class="rc-message" role="status">Aucun résultat pour « <b>' + esc(q) + '</b> ».'
					+ (DOSSIERS && q.length < 2 ? '<small>Deux caractères au moins pour chercher dans les dossiers.</small>' : '<small>Vérifiez l’orthographe, ou cherchez le matricule.</small>') + '</p>';
		}
		zone.innerHTML = html;
		options = Array.from(zone.querySelectorAll('.rc-option'));
		activer(options.length ? 0 : -1, false);
	}

	function activer(i, defiler) {
		if (actif >= 0 && options[actif]) { options[actif].setAttribute('aria-selected', 'false'); }
		actif = i;
		if (i < 0 || !options[i]) { champ.removeAttribute('aria-activedescendant'); return; }
		options[i].setAttribute('aria-selected', 'true');
		champ.setAttribute('aria-activedescendant', options[i].id);
		if (defiler !== false) { options[i].scrollIntoView({ block: 'nearest' }); }
	}

	function choisir(o, e) {
		if (o.dataset.filtrer !== undefined && window.listeRecherche) {
			if (e) { e.preventDefault(); }
			fermer();
			window.listeRecherche.appliquer(o.dataset.filtrer);
			return;
		}
		if (o.dataset.page === '1') {
			retenir({ titre: o.dataset.titre, detail: o.dataset.detail, lien: o.getAttribute('href'), icone: o.dataset.icone });
		}
		if (e) { return; }                  // un clic suit le lien lui-meme
		fermer();
		o.click();                          // le rideau de transition (src/js/transitions.js) s'en charge
	}

	function clavier(e) {
		if (e.key === 'ArrowDown') { e.preventDefault(); if (options.length) { activer((actif + 1) % options.length); } }
		else if (e.key === 'ArrowUp') { e.preventDefault(); if (options.length) { activer((actif - 1 + options.length) % options.length); } }
		else if (e.key === 'Home' && options.length && champ.value === '') { e.preventDefault(); activer(0); }
		else if (e.key === 'End' && options.length && champ.value === '') { e.preventDefault(); activer(options.length - 1); }
		else if (e.key === 'Enter') { e.preventDefault(); if (options[actif]) { choisir(options[actif]); } }
		else if (e.key === 'Escape') { e.preventDefault(); fermer(); }
	}

	/* --- Ouverture -------------------------------------------------------------------- */
	document.addEventListener('click', function (e) {
		if (e.target.closest('[data-recherche-ouvrir]')) { e.preventDefault(); ouvrir(); }
	});
	document.addEventListener('keydown', function (e) {
		const k = (e.key || '').toLowerCase();
		if (k === 'k' && (e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey) {
			e.preventDefault();
			if (fond && !fond.hidden) { fermer(); } else { ouvrir(); }
			return;
		}
		if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
			const c = e.target;
			if (c && (c.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(c.tagName))) { return; }
			if (fond && !fond.hidden) { return; }
			e.preventDefault();
			ouvrir();
		}
	});
})();
