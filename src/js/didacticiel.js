/* =============================================================================
   Didacticiel : des visites guidees sur les vraies pages de l'application.

   Chaque etape met un element en evidence (un « trou » dans un voile sombre)
   et l'explique dans une bulle. Les visites sont decrites dans
   data/didacticiel.php, et injectees par init/topbar.php (#didacticielConfig) ;
   /didacticiel les recense.

   Lancer une visite :
     - un bouton [data-didacticiel-lancer="id"] (menu « Didacticiel » du pied
       de la barre laterale, qui liste les visites de la page) ;
     - une adresse « …#didacticiel=id » (les liens de /didacticiel) ;
     - la fin d'une visite qui a une suite (liste -> fiche, guichet -> dossier) :
       la suite attend, le temps d'une session, que sa page soit ouverte ;
     - la premiere arrivee sur une page d'accueil : une invitation, une fois.

   Ce qui est retenu (navigateur, par compte) : les visites faites, et la
   reponse a l'invitation. Rien d'autre.
   ========================================================================== */
(function () {
	'use strict';

	const source = document.getElementById('didacticielConfig');
	if (!source) { return; }
	let cfg;
	try { cfg = JSON.parse(source.textContent); } catch (e) { return; }

	const ATTENTE_SUITE = 15 * 60 * 1000;
	const cle = (k) => 'didacticiel:' + cfg.utilisateur + ':' + k;
	const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	function lire(stock, k, defaut) {
		try { const v = stock.getItem(cle(k)); return v === null ? defaut : JSON.parse(v); } catch (e) { return defaut; }
	}
	function ecrire(stock, k, v) {
		try { if (v === null) { stock.removeItem(cle(k)); } else { stock.setItem(cle(k), JSON.stringify(v)); } } catch (e) { /* stockage indisponible */ }
	}
	const local = () => window.localStorage;
	const session = () => window.sessionStorage;

	/* --- Les visites du compte, et celles de cette page ------------------------- */
	const parId = {};
	cfg.visites.forEach((v) => { parId[v.id] = v; });

	function disponible(v) {
		return !!v && Array.isArray(v.etapes) && v.pages.indexOf(cfg.chemin) !== -1 && (!v.si || !!document.querySelector(v.si));
	}
	function ici() { return cfg.visites.filter(disponible); }

	function etat(id) { return (lire(local(), 'vus', {}) || {})[id] || ''; }
	function marquer(id, valeur) {
		const vus = lire(local(), 'vus', {}) || {};
		if (vus[id] === 'faite' && valeur !== 'faite') { return; }
		vus[id] = valeur;
		ecrire(local(), 'vus', vus);
	}

	/* --- Les elements : voile, trou, bulle --------------------------------------- */
	let voile = null, trou = null, bulle = null, titre, texte, progres, barre, pied, fleche;
	let mode = '', visite = null, etapes = [], rang = -1, courant = null, retour = null, cadre = 0;

	function construire() {
		if (bulle) { return; }
		voile = document.createElement('div');
		voile.className = 'dt-voile';
		voile.hidden = true;
		trou = document.createElement('div');
		trou.className = 'dt-trou';
		trou.hidden = true;
		bulle = document.createElement('div');
		bulle.className = 'dt-bulle';
		bulle.hidden = true;
		bulle.setAttribute('role', 'dialog');
		bulle.setAttribute('aria-modal', 'true');
		bulle.setAttribute('aria-labelledby', 'dtTitre');
		bulle.setAttribute('aria-describedby', 'dtTexte');
		bulle.tabIndex = -1;
		bulle.innerHTML =
			'<div class="dt-bulle__tete"><span class="dt-progres" id="dtProgres"></span>'
			+ '<button type="button" class="dt-fermer" data-dt="fermer" aria-label="Arrêter la visite"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>'
			+ '<div class="dt-barre" aria-hidden="true"><span></span></div>'
			+ '<h2 class="dt-titre" id="dtTitre"></h2>'
			+ '<p class="dt-texte" id="dtTexte"></p>'
			+ '<div class="dt-pied"></div>'
			+ '<span class="dt-fleche" aria-hidden="true"></span>';
		[voile, trou, bulle].forEach((el) => document.body.appendChild(el));
		titre = bulle.querySelector('.dt-titre');
		texte = bulle.querySelector('.dt-texte');
		progres = bulle.querySelector('.dt-progres');
		barre = bulle.querySelector('.dt-barre span');
		pied = bulle.querySelector('.dt-pied');
		fleche = bulle.querySelector('.dt-fleche');
		bulle.addEventListener('click', function (e) {
			const b = e.target.closest('[data-dt]');
			if (b) { faire(b.getAttribute('data-dt')); }
		});
	}

	/** Les boutons du pied : [{ dt, libelle, classe, icone }] */
	function boutons(liste, touches) {
		pied.innerHTML = (touches ? '<span class="dt-touches" aria-hidden="true"><kbd><i class="bi bi-arrow-left"></i></kbd><kbd><i class="bi bi-arrow-right"></i></kbd></span>' : '<span class="dt-touches"></span>')
			+ liste.map((b) => '<button type="button" class="dt-bouton dt-bouton--' + b.classe + '" data-dt="' + b.dt + '">'
				+ esc(b.libelle) + (b.icone ? '<i class="bi ' + b.icone + '" aria-hidden="true"></i>' : '') + '</button>').join('');
	}

	function ouvrirCadre() {
		construire();
		if (!bulle.hidden) { return; }
		retour = document.activeElement;
		/* Sur telephone, le tiroir du menu ouvert cacherait la page */
		const menu = document.getElementById('sidebarMenu');
		if (menu && menu.classList.contains('active')) {
			const fond = document.getElementById('sidebarOverlay');
			if (fond) { fond.click(); }
		}
		voile.hidden = false;
		bulle.hidden = false;
		document.documentElement.classList.add('dt-actif');
		document.addEventListener('keydown', clavier, true);
		window.addEventListener('resize', replacer);
		document.addEventListener('scroll', replacer, true);
	}
	function fermerCadre() {
		if (!bulle || bulle.hidden) { return; }
		voile.hidden = true;
		trou.hidden = true;
		bulle.hidden = true;
		mode = '';
		visite = null;
		courant = null;
		cancelAnimationFrame(suivi);
		document.documentElement.classList.remove('dt-actif');
		document.removeEventListener('keydown', clavier, true);
		window.removeEventListener('resize', replacer);
		document.removeEventListener('scroll', replacer, true);
		if (retour && retour.isConnected && typeof retour.focus === 'function') { retour.focus({ preventScroll: true }); }
	}

	/* --- Une etape : ou est sa cible ---------------------------------------------- */
	function visible(el) {
		if (!el || !el.isConnected) { return false; }
		const r = el.getBoundingClientRect();
		if (r.width < 2 || r.height < 2 || r.right <= 0 || r.left >= window.innerWidth) { return false; }
		const s = getComputedStyle(el);
		return s.visibility !== 'hidden' && s.display !== 'none';
	}

	/** null : l'etape ne concerne pas ce compte (cible absente) ; sinon la cible a montrer (ou aucune : au centre). */
	function resoudre(e) {
		if (!e.cible) { return { el: null, texte: e.texte }; }
		const el = document.querySelector(e.cible);
		if (!el) { return null; }
		if (visible(el)) { return { el: el, texte: e.texte }; }
		const repli = e.mobile ? document.querySelector(e.mobile) : null;
		if (repli && visible(repli)) { return { el: repli, texte: e.texteMobile || e.texte }; }
		return { el: null, texte: e.texteMobile || e.texte };
	}

	/* --- Visite -------------------------------------------------------------------- */
	function lancer(id) {
		const v = parId[id];
		if (!disponible(v)) { return false; }
		const liste = v.etapes
			.filter((e) => !e.chemins || e.chemins.indexOf(cfg.chemin) !== -1)
			.filter((e) => resoudre(e) !== null);
		if (!liste.length) { return false; }
		ouvrirCadre();
		mode = 'visite';
		visite = v;
		etapes = liste;
		marquer(v.id, etat(v.id) || 'commencee');
		aller(0, 1);
		return true;
	}

	function aller(i, sens) {
		while (i >= 0 && i < etapes.length) {
			const r = resoudre(etapes[i]);
			if (r) { rang = i; montrer(etapes[i], r); return; }
			i += sens;
		}
		if (sens > 0) { finir(); }
	}

	function montrer(e, r) {
		const dernier = rang === etapes.length - 1;
		const action = e.action && document.querySelector(e.action.lien) ? e.action : null;
		progres.textContent = 'Étape ' + (rang + 1) + ' sur ' + etapes.length;
		barre.style.width = Math.round((rang + 1) / etapes.length * 100) + '%';
		titre.textContent = e.titre || '';
		texte.textContent = r.texte || '';
		const liste = [];
		if (rang > 0) { liste.push({ dt: 'precedent', libelle: 'Précédent', classe: 'second' }); }
		if (action) {
			liste.push({ dt: dernier ? 'finir' : 'suivant', libelle: dernier ? 'Terminer' : 'Suivant', classe: 'second' });
			liste.push({ dt: 'action', libelle: action.libelle, classe: 'premier', icone: 'bi-arrow-right' });
		} else {
			liste.push({ dt: dernier ? 'finir' : 'suivant', libelle: dernier ? 'Terminer' : 'Suivant', classe: 'premier', icone: dernier ? 'bi-check2' : '' });
		}
		boutons(liste, true);
		courant = r.el;
		if (courant) { amener(courant); }
		placer();
		derniere = '';
		suivre();
		const premier = pied.querySelector('.dt-bouton--premier');
		(premier || bulle).focus({ preventScroll: true });
	}

	function faire(quoi) {
		if (quoi === 'fermer') { arreter(); }
		else if (quoi === 'suivant') { aller(rang + 1, 1); }
		else if (quoi === 'precedent') { aller(rang - 1, -1); }
		else if (quoi === 'finir') { finir(); }
		else if (quoi === 'action') { agir(); }
		else if (quoi === 'commencer') { const v = visite; ecrire(local(), 'invitation', 'acceptee'); fermerCadre(); if (v) { lancer(v.id); } }
		else if (quoi === 'plus-tard') { plusTard(); }
	}

	function finir() {
		const v = visite;
		if (!v) { fermerCadre(); return; }
		marquer(v.id, 'faite');
		fermerCadre();
		if (v.suite) { ecrire(session(), 'suite', { id: v.suite, t: Date.now() }); }
	}
	function arreter() {
		if (mode === 'invitation') { plusTard(); return; }
		if (visite) { marquer(visite.id, 'arretee'); }
		fermerCadre();
	}
	/* Le bouton d'action ouvre le lien de la page (zone AJAX ou page suivante) ; la suite attend */
	function agir() {
		const e = etapes[rang];
		const lien = e && e.action ? document.querySelector(e.action.lien) : null;
		if (visite) { marquer(visite.id, 'faite'); }
		if (e && e.action && e.action.suite) { ecrire(session(), 'suite', { id: e.action.suite, t: Date.now() }); }
		retour = null;
		fermerCadre();
		if (lien) { lien.click(); }
	}

	/* --- Invitation, a la premiere arrivee ------------------------------------------ */
	function inviter() {
		const v = ici().find((x) => x.accueil);
		if (!v || etat(v.id) || lire(local(), 'invitation', null)) { return; }
		setTimeout(function () {
			if (mode || document.querySelector('.rc-fond:not([hidden]), .modal.show, #shortcutsModal.show')) { return; }
			ouvrirCadre();
			mode = 'invitation';
			visite = v;
			progres.textContent = 'Didacticiel';
			barre.style.width = '0';
			titre.textContent = 'Première visite ?';
			/* Le texte de la visite si elle a le sien (data/didacticiel.pages.php) */
			texte.textContent = v.invitation || ("En une minute, une visite guidée vous montre le menu, la recherche et cette page. "
				+ "Vous la retrouverez à tout moment dans « Didacticiel », en bas du menu.");
			boutons([
				{ dt: 'plus-tard', libelle: 'Plus tard', classe: 'second' },
				{ dt: 'commencer', libelle: 'Commencer la visite', classe: 'premier', icone: 'bi-arrow-right' },
			], false);
			courant = null;
			placer();
			pied.querySelector('.dt-bouton--premier').focus({ preventScroll: true });
		}, 1000);
	}
	function plusTard() {
		ecrire(local(), 'invitation', 'plus-tard');
		fermerCadre();
	}

	/* --- Placement -------------------------------------------------------------------- */
	/* Fait defiler la page pour que la cible soit vue ET que la bulle tienne a cote */
	function amener(el) {
		const H = window.innerHeight, L = window.innerWidth;
		const defiler = (bloc) => el.scrollIntoView({ block: bloc, inline: 'nearest', behavior: 'auto' });
		const r = el.getBoundingClientRect();
		if (r.height > H - 140) {
			/* Plus haut que l'ecran (une grille de notes) : on en montre le haut */
			if (r.top < 60 || r.top > H / 3) { defiler('start'); }
			return;
		}
		if (L < 640) {
			if (r.top < 72 || r.bottom > H - 16) { defiler('center'); }
			return;
		}
		const bh = bulle.offsetHeight + 30, bw = bulle.offsetWidth + 30;
		const aCote = L - r.right >= bw || r.left >= bw;
		const entier = r.top >= 64 && r.bottom <= H - 12;
		const dessusDessous = r.top - 64 >= bh || H - r.bottom >= bh;
		if (entier && (dessusDessous || aCote)) { return; }
		/* Ni dessus ni dessous au centre : en haut de l'ecran, la bulle dessous */
		defiler(!aCote && r.height + bh <= H - 76 ? 'start' : 'center');
	}

	function replacer() {
		if (!mode) { return; }
		cancelAnimationFrame(cadre);
		cadre = requestAnimationFrame(placer);
	}

	/* La page peut encore bouger sous la visite (vue chargee par une zone AJAX,
	   images, polices) : tant qu'une etape est affichee, la bulle suit sa cible. */
	let suivi = 0, derniere = '';
	function suivre() {
		cancelAnimationFrame(suivi);
		if (!mode) { return; }
		if (courant && courant.isConnected) {
			const r = courant.getBoundingClientRect();
			const empreinte = Math.round(r.left) + ',' + Math.round(r.top) + ',' + Math.round(r.width) + ',' + Math.round(r.height);
			if (empreinte !== derniere) { derniere = empreinte; placer(); }
		}
		suivi = requestAnimationFrame(suivre);
	}

	function placer() {
		const L = window.innerWidth, H = window.innerHeight, m = 12, ecart = 14, marge = 6;
		const etroit = L < 640;
		bulle.classList.toggle('dt-bulle--docke', etroit);
		fleche.className = 'dt-fleche';
		if (!courant || !courant.isConnected) {
			trou.hidden = true;
			voile.classList.add('dt-voile--sombre');
			bulle.style.left = Math.max(m, (L - bulle.offsetWidth) / 2) + 'px';
			bulle.style.top = Math.max(m, (H - bulle.offsetHeight) / 2) + 'px';
			return;
		}
		voile.classList.remove('dt-voile--sombre');
		const r = courant.getBoundingClientRect();
		/* Le trou, ramene dans l'ecran */
		const t = {
			left: Math.max(4, r.left - marge), top: Math.max(4, r.top - marge),
			right: Math.min(L - 4, r.right + marge), bottom: Math.min(H - 4, r.bottom + marge),
		};
		t.width = Math.max(0, t.right - t.left);
		t.height = Math.max(0, t.bottom - t.top);
		trou.hidden = false;
		trou.style.left = t.left + 'px';
		trou.style.top = t.top + 'px';
		trou.style.width = t.width + 'px';
		trou.style.height = t.height + 'px';

		const bw = bulle.offsetWidth, bh = bulle.offsetHeight;
		if (etroit) {
			/* Telephone : la bulle se range en bas, ou en haut si la cible est en bas */
			const enBas = (t.top + t.height / 2) < H / 2;
			bulle.style.left = m + 'px';
			bulle.style.top = (enBas ? H - bh - m : m) + 'px';
			return;
		}
		const place = {
			bas: H - t.bottom - ecart - m >= bh,
			haut: t.top - ecart - m >= bh,
			droite: L - t.right - ecart - m >= bw,
			gauche: t.left - ecart - m >= bw,
		};
		const elance = t.height > t.width;                            // une colonne (le menu) : a cote
		const ordre = elance ? ['droite', 'gauche', 'bas', 'haut'] : ['bas', 'haut', 'droite', 'gauche'];
		const cote = ordre.find((c) => place[c]);
		const borne = (v, min, max) => Math.min(Math.max(v, min), max);
		let x, y;
		if (cote === 'bas' || cote === 'haut') {
			x = borne(t.left + t.width / 2 - bw / 2, m, L - bw - m);
			y = cote === 'bas' ? t.bottom + ecart : t.top - ecart - bh;
			fleche.classList.add('dt-fleche--' + cote);
			fleche.style.left = borne(t.left + t.width / 2 - x - 6, 18, bw - 30) + 'px';
			fleche.style.top = '';
		} else if (cote === 'droite' || cote === 'gauche') {
			x = cote === 'droite' ? t.right + ecart : t.left - ecart - bw;
			y = borne(t.top + Math.min(t.height, H) / 2 - bh / 2, m, H - bh - m);
			fleche.classList.add('dt-fleche--' + cote);
			fleche.style.top = borne(t.top + Math.min(t.height, H - t.top) / 2 - y - 6, 18, bh - 30) + 'px';
			fleche.style.left = '';
		} else {
			/* Nulle part : par-dessus, en bas de l'ecran */
			x = (L - bw) / 2;
			y = H - bh - m;
		}
		bulle.style.left = Math.round(x) + 'px';
		bulle.style.top = Math.round(y) + 'px';
	}

	/* --- Clavier : la visite d'abord ----------------------------------------------------- */
	function clavier(e) {
		if (!mode) { return; }
		const k = e.key;
		if (k === 'Escape') { e.preventDefault(); e.stopPropagation(); arreter(); return; }
		if (mode === 'visite' && k === 'ArrowRight') { e.preventDefault(); e.stopPropagation(); faire(rang < etapes.length - 1 ? 'suivant' : 'finir'); return; }
		if (mode === 'visite' && k === 'ArrowLeft') { e.preventDefault(); e.stopPropagation(); if (rang > 0) { faire('precedent'); } return; }
		if (k === 'Tab') {
			const f = Array.from(bulle.querySelectorAll('button'));
			const i = f.indexOf(document.activeElement);
			e.preventDefault();
			f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
			return;
		}
		/* Ni la recherche rapide ni l'aide des raccourcis par-dessus la visite */
		if (k === '/' || k === '?' || ((e.ctrlKey || e.metaKey) && (k || '').toLowerCase() === 'k')) {
			e.preventDefault();
			e.stopPropagation();
		}
	}

	/* --- Le menu « Didacticiel » du pied de la barre laterale ------------------------------- */
	function remplirMenu() {
		const menu = document.getElementById('didacticielMenu');
		if (!menu) { return; }
		const liste = ici();
		let html = '';
		if (liste.length) {
			html += '<li class="dt-menu__titre">Sur cette page</li>';
			liste.forEach(function (v) {
				html += '<li><button type="button" class="dropdown-item" data-didacticiel-lancer="' + esc(v.id) + '">'
					+ '<i class="bi ' + (etat(v.id) === 'faite' ? 'bi-check2-circle' : 'bi-play-circle') + '" aria-hidden="true"></i>' + esc(v.titre) + '</button></li>';
			});
			html += '<li><hr class="dropdown-divider"></li>';
		}
		html += '<li><a class="dropdown-item" href="' + esc(cfg.aide) + '"><i class="bi bi-signpost-split" aria-hidden="true"></i>Toutes les visites guidées</a></li>';
		menu.innerHTML = html;
	}

	/* --- Demarrages ---------------------------------------------------------------------- */
	function depuisAdresse() {
		const m = /^#didacticiel=([\w-]+)$/.exec(location.hash);
		if (!m) { return false; }
		try { history.replaceState(history.state, '', location.pathname + location.search); } catch (e) { /* sans gravite */ }
		if (!disponible(parId[m[1]])) { return false; }
		setTimeout(function () { lancer(m[1]); }, 600);
		return true;
	}
	function reprendre() {
		const s = lire(session(), 'suite', null);
		if (!s) { return false; }
		if (!s.id || Date.now() - (s.t || 0) > ATTENTE_SUITE || !parId[s.id]) { ecrire(session(), 'suite', null); return false; }
		if (!disponible(parId[s.id])) { return false; }
		ecrire(session(), 'suite', null);
		setTimeout(function () { lancer(s.id); }, 600);
		return true;
	}

	document.addEventListener('click', function (e) {
		const b = e.target.closest('[data-didacticiel-lancer]');
		if (!b) { return; }
		e.preventDefault();
		lancer(b.getAttribute('data-didacticiel-lancer'));
	});
	window.addEventListener('hashchange', depuisAdresse);
	/* Une zone AJAX change de vue (liste -> feuille) : le menu suit, une suite peut partir */
	document.addEventListener('zone:chargee', function () {
		remplirMenu();
		if (mode === 'visite' && (!disponible(visite) || (courant && !courant.isConnected))) { fermerCadre(); }
		reprendre();
	});

	window.Didacticiel = { lancer: lancer, etat: etat, ici: ici };

	function demarrer() {
		remplirMenu();
		if (!depuisAdresse() && !reprendre()) { inviter(); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', demarrer); } else { demarrer(); }
})();
