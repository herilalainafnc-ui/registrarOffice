/* =============================================================================
   Tableau de bord : les graphiques qu'on peut interroger (src/tableau-de-bord.php)

   - La rentrée : deux vues, « Cumul » (l'année depuis le 1er juillet) et
     « Jour par jour » (six semaines), dessinées ici en SVG à partir des séries
     de la page (.tb-donnees). Survol, toucher ou flèches du clavier : un repère
     suit le jour, la bulle compare les deux années à la même date. La légende
     masque ou montre chaque année.
   - Partout ailleurs, un élément [data-bulle] montre sa bulle au survol et au
     focus ; la répartition relie segments et légende ; l'anneau de la saisie
     des notes prend le taux de la mention survolée.

   La page entière est une zone AJAX (changer d'année la recharge) : tout est
   réinitialisé à chaque « zone:chargee ». Sans bibliothèque ; les couleurs
   viennent des jetons du thème (classes CSS), un changement de thème suit seul.
   ========================================================================== */
(function () {
	'use strict';

	const JOUR = 86400000;
	const nombre = new Intl.NumberFormat('fr-FR');
	const dateLongue = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });
	const dateAnnee = new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
	const dateCourte = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', timeZone: 'UTC' });
	const moisCourt = new Intl.DateTimeFormat('fr-FR', { month: 'short', timeZone: 'UTC' });
	const sansMouvement = () => !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
	const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const majuscule = (s) => s.charAt(0).toUpperCase() + s.slice(1);
	const signe = (n) => (n > 0 ? '+' : n < 0 ? '−' : '') + nombre.format(Math.abs(n));
	let gradients = 0;

	/* --- La bulle, une pour la page ------------------------------------------------- */
	let bulle = null;
	function laBulle() {
		if (!bulle || !bulle.isConnected) {
			bulle = document.createElement('div');
			bulle.className = 'tb-infobulle';
			bulle.setAttribute('aria-hidden', 'true');
			document.body.appendChild(bulle);
		}
		return bulle;
	}
	/* Qui a ouvert la bulle : quitter le graphique ne doit pas fermer celle
	   qu'une mention vient d'ouvrir (l'ordre des évènements n'est pas garanti) */
	let proprietaire = null;
	/** Place la bulle au-dessus du point (x, y) de l'écran, retournée si elle déborde. */
	function montrerBulle(html, x, y, qui) {
		const b = laBulle();
		proprietaire = qui || null;
		b.innerHTML = html;
		b.classList.add('est-visible');
		const l = b.offsetWidth, h = b.offsetHeight, m = 8;
		let gauche = Math.min(Math.max(m, x - l / 2), window.innerWidth - l - m);
		let haut = y - h - 12;
		if (haut < m) { haut = y + 18; }
		b.style.left = Math.round(gauche) + 'px';
		b.style.top = Math.round(haut) + 'px';
	}
	/** Ferme la bulle ; avec « qui », seulement si c'est la sienne. */
	function cacherBulle(qui) {
		if (!bulle || (qui && qui !== proprietaire)) { return; }
		bulle.classList.remove('est-visible');
		proprietaire = null;
	}

	/* --- Échelle --------------------------------------------------------------------- */
	/** Un maximum « rond » et son pas, pour 3 à 5 lignes de grille. */
	function echelle(max) {
		max = Math.max(1, max);
		for (const p of [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000]) {
			if (max / p <= 5) { return { haut: Math.max(p, Math.ceil(max / p) * p), pas: p }; }
		}
		return { haut: max, pas: max };
	}

	/* =================================================================================
	   LA RENTRÉE
	   ================================================================================= */
	function initRentree(carte) {
		const source = carte.querySelector('.tb-donnees');
		const cadre = carte.querySelector('.tb-graphique');
		const toile = carte.querySelector('.tb-graphique__toile');
		const annonce = carte.querySelector('[data-annonce]');
		if (!source || !cadre || !toile || cadre.dataset.pret) { return; }
		cadre.dataset.pret = '1';
		let d;
		try { d = JSON.parse(source.textContent); } catch (e) { return; }

		const debut = Date.parse(d.debut + 'T00:00:00Z');
		const debutPrec = Date.UTC(new Date(debut).getUTCFullYear() - 1, 6, 1);
		const a = d.serie, b = d.serie_prec;
		const dernierA = a.length - 1;                    // année en cours : aujourd'hui
		const parJour = (s, i) => (i < 0 || i >= s.length) ? null : s[i] - (i > 0 ? s[i - 1] : 0);
		const date = (i) => debut + i * JOUR;
		const datePrec = (i) => debutPrec + i * JOUR;

		const etat = { vue: 'cumul', a: true, b: true, i: null, anime: true };
		const g = {};                                     // la géométrie du dernier dessin

		/* ------------------------------------------------------------- dessin */
		function dessiner() {
			const L = Math.max(280, toile.clientWidth);
			const H = L < 520 ? 220 : 270;
			const m = { g: 44, d: 14, h: 18, b: 28 };
			const w = L - m.g - m.d, h = H - m.h - m.b;
			Object.assign(g, { L, H, m, w, h });
			toile.style.height = H + 'px';
			toile.innerHTML = etat.vue === 'cumul' ? svgCumul() : svgJours();
			const svg = toile.querySelector('svg');
			if (etat.anime && !sansMouvement()) {
				svg.querySelectorAll('.tb-c-ligne--a').forEach((p) => {
					const n = p.getTotalLength();
					p.style.strokeDasharray = n + ' ' + n;
					p.style.strokeDashoffset = n;
				});
				svg.classList.add('tb-c--entree');
				requestAnimationFrame(() => requestAnimationFrame(() => {
					svg.classList.add('tb-c--joue');
					svg.querySelectorAll('.tb-c-ligne--a').forEach((p) => { p.style.strokeDashoffset = 0; });
				}));
			}
			etat.anime = false;
			if (etat.i !== null) { reperer(etat.i, false); }
		}

		function grille(haut, pas, y) {
			let s = '';
			for (let v = 0; v <= haut; v += pas) {
				s += '<line class="tb-c-grille" x1="' + g.m.g + '" x2="' + (g.L - g.m.d) + '" y1="' + y(v) + '" y2="' + y(v) + '"/>'
					+ '<text class="tb-c-axe" x="' + (g.m.g - 8) + '" y="' + (y(v) + 4) + '" text-anchor="end">' + nombre.format(v) + '</text>';
			}
			return s;
		}

		/* Vue « Cumul » : l'année, du 1er juillet au 30 juin */
		function svgCumul() {
			const n = b.length;
			const max = Math.max(etat.a && a.length ? a[dernierA] : 0, etat.b ? b[n - 1] : 0, 1);
			const { haut, pas } = echelle(max);
			const x = (i) => g.m.g + i / (n - 1) * g.w;
			const y = (v) => g.m.h + g.h - v / haut * g.h;
			Object.assign(g, { x, y, n, haut });
			const trace = (s, fin) => { let p = ''; for (let i = 0; i <= fin; i++) { p += (i ? 'L' : 'M') + x(i).toFixed(1) + ',' + y(s[i]).toFixed(1); } return p; };
			const id = 'tbGrad' + (++gradients);
			let s = '<svg width="' + g.L + '" height="' + g.H + '" viewBox="0 0 ' + g.L + ' ' + g.H + '">'
				+ '<defs><linearGradient id="' + id + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="tb-c-stop1"/><stop offset="1" class="tb-c-stop2"/></linearGradient></defs>'
				+ grille(haut, pas, y);
			/* Les mois, au 1er de chacun */
			let jour = 0;
			for (let k = 0; k < 12; k++) {
				const t = Date.UTC(new Date(debut).getUTCFullYear(), 6 + k, 1);
				jour = Math.round((t - debut) / JOUR);
				if (g.w < 420 && k % 2) { continue; }
				s += '<text class="tb-c-axe" x="' + (x(jour) + 2) + '" y="' + (g.H - 8) + '">' + esc(moisCourt.format(t).replace('.', '')) + '</text>'
					+ '<line class="tb-c-mois" x1="' + x(jour) + '" x2="' + x(jour) + '" y1="' + (g.m.h + g.h) + '" y2="' + (g.m.h + g.h + 4) + '"/>';
			}
			if (etat.b) { s += '<path class="tb-c-ligne tb-c-ligne--b" d="' + trace(b, n - 1) + '"/>'; }
			if (etat.a && a.length) {
				const ligne = trace(a, dernierA);
				s += '<path class="tb-c-aire" fill="url(#' + id + ')" d="' + ligne + 'L' + x(dernierA).toFixed(1) + ',' + y(0) + 'L' + x(0) + ',' + y(0) + 'Z"/>'
					+ '<path class="tb-c-ligne tb-c-ligne--a" d="' + ligne + '"/>';
			}
			if (d.limite !== null) {
				const xj = x(d.limite);
				s += '<line class="tb-c-jour" x1="' + xj + '" x2="' + xj + '" y1="' + g.m.h + '" y2="' + (g.m.h + g.h) + '"/>'
					+ '<text class="tb-c-jour-libelle" x="' + (xj + 6) + '" y="' + (g.m.h + 10) + '">Aujourd\'hui</text>';
				if (etat.a) { s += '<circle class="tb-c-pouls" cx="' + xj + '" cy="' + y(a[dernierA]) + '" r="5"/><circle class="tb-c-fin" cx="' + xj + '" cy="' + y(a[dernierA]) + '" r="4"/>'; }
			}
			return s + reperes() + '</svg>';
		}

		/* Vue « Jour par jour » : six semaines, l'an dernier aux mêmes dates en fond */
		function svgJours() {
			const [f, n] = d.fenetre;
			const fin = f + n - 1;
			let max = 1;
			for (let i = f; i <= fin; i++) {
				if (etat.a && i <= dernierA) { max = Math.max(max, parJour(a, i)); }
				if (etat.b) { max = Math.max(max, parJour(b, i)); }
			}
			const { haut, pas } = echelle(max);
			const c = g.w / n;                                // une case par jour
			const x = (i) => g.m.g + (i - f) * c + c / 2;     // milieu de la case
			const y = (v) => g.m.h + g.h - v / haut * g.h;
			Object.assign(g, { x, y, c, f, fin, haut });
			let s = '<svg width="' + g.L + '" height="' + g.H + '" viewBox="0 0 ' + g.L + ' ' + g.H + '">';
			for (let i = f; i <= fin; i++) {
				const jourSemaine = new Date(date(i)).getUTCDay();
				if (jourSemaine === 0 || jourSemaine === 6) {
					s += '<rect class="tb-c-weekend" x="' + (x(i) - c / 2) + '" y="' + g.m.h + '" width="' + c + '" height="' + g.h + '"/>';
				}
			}
			s += grille(haut, pas, y) + '<rect class="tb-c-case" x="0" y="' + g.m.h + '" width="' + c + '" height="' + g.h + '" visibility="hidden"/>';
			const unSurDeux = c < 13;                      // cases étroites : un lundi sur deux
			let rang = 0, lundis = 0;
			for (let i = f; i <= fin; i++) {
				const vb = parJour(b, i), va = i <= dernierA ? parJour(a, i) : null;
				if (etat.b && vb > 0) {
					s += '<rect class="tb-c-barre tb-c-barre--b" style="--i:' + rang + '" x="' + (x(i) - c * 0.38) + '" y="' + y(vb) + '" width="' + (c * 0.76) + '" height="' + (y(0) - y(vb)) + '" rx="' + Math.min(3, c * 0.2) + '"/>';
				}
				if (etat.a && va > 0) {
					s += '<rect class="tb-c-barre tb-c-barre--a' + (i === d.limite ? ' tb-c-barre--jour' : '') + '" style="--i:' + rang + '" x="' + (x(i) - c * 0.24) + '" y="' + y(va) + '" width="' + (c * 0.48) + '" height="' + (y(0) - y(va)) + '" rx="' + Math.min(3, c * 0.16) + '"/>';
				}
				const dt = new Date(date(i));
				if (dt.getUTCDay() === 1 && (!unSurDeux || lundis++ % 2 === 0)) {
					s += '<text class="tb-c-axe" x="' + x(i) + '" y="' + (g.H - 8) + '" text-anchor="middle">' + esc(dateCourte.format(date(i)).replace('.', '')) + '</text>';
				}
				rang++;
			}
			const aucune = etat.a && !a.slice(f, Math.min(fin, dernierA) + 1).some((v, k) => parJour(a, f + k) > 0);
			if (aucune) {
				s += '<text class="tb-c-vide" x="' + (g.m.g + g.w / 2) + '" y="' + (g.m.h + g.h / 2) + '" text-anchor="middle">Aucune inscription ' + esc(d.annee) + ' sur ces six semaines</text>';
			}
			return s + reperes() + '</svg>';
		}

		/* Le repère : une ligne verticale et deux points, cachés tant qu'on ne survole pas */
		function reperes() {
			return '<g class="tb-c-repere" visibility="hidden">'
				+ '<line class="tb-c-repere__ligne" y1="' + g.m.h + '" y2="' + (g.m.h + g.h) + '"/>'
				+ '<circle class="tb-c-point tb-c-point--b" r="4.5"/><circle class="tb-c-point tb-c-point--a" r="5.5"/></g>'
				+ '<rect class="tb-c-capteur" x="' + g.m.g + '" y="' + g.m.h + '" width="' + g.w + '" height="' + g.h + '"/>';
		}

		/* ------------------------------------------------------------- repère */
		function bornes() {
			return etat.vue === 'cumul' ? [0, g.n - 1] : [g.f, g.fin];
		}
		function indiceAuPoint(clientX) {
			const r = toile.getBoundingClientRect();
			const px = clientX - r.left;
			const [min, max] = bornes();
			const i = etat.vue === 'cumul'
				? Math.round((px - g.m.g) / g.w * (g.n - 1))
				: g.f + Math.floor((px - g.m.g) / g.c);
			return Math.min(max, Math.max(min, i));
		}

		function reperer(i, bulleAuPoint) {
			etat.i = i;
			const svg = toile.querySelector('svg');
			if (!svg) { return; }
			const rep = svg.querySelector('.tb-c-repere');
			const pa = svg.querySelector('.tb-c-point--a'), pb = svg.querySelector('.tb-c-point--b');
			const xi = g.x(i);
			rep.setAttribute('visibility', 'visible');
			rep.querySelector('line').setAttribute('x1', xi);
			rep.querySelector('line').setAttribute('x2', xi);
			const cumul = etat.vue === 'cumul';
			const va = i <= dernierA ? (cumul ? a[i] : parJour(a, i)) : null;
			const vb = cumul ? b[i] : parJour(b, i);
			const placer = (p, v, montre) => {
				p.setAttribute('visibility', montre && v !== null ? 'visible' : 'hidden');
				if (v !== null) { p.setAttribute('cx', xi); p.setAttribute('cy', g.y(v)); }
			};
			placer(pa, va, etat.a && cumul);
			placer(pb, vb, etat.b && cumul);
			if (!cumul) {
				const caseJour = svg.querySelector('.tb-c-case');
				caseJour.setAttribute('x', xi - g.c / 2);
				caseJour.setAttribute('visibility', 'visible');
				svg.classList.add('tb-c--survol');
				svg.querySelectorAll('.tb-c-barre').forEach((r) => {
					const x = parseFloat(r.getAttribute('x')) + parseFloat(r.getAttribute('width')) / 2;
					r.classList.toggle('est-active', Math.abs(x - xi) < g.c / 2);
				});
			}

			/* La bulle */
			const titre = majuscule(dateLongue.format(date(i)));
			let lignes = '';
			const ligne = (cle, libelle, valeur, note) => '<div class="tb-infobulle__ligne"><span class="tb-infobulle__pastille tb-infobulle__pastille--' + cle + '"></span>'
				+ '<span>' + esc(libelle) + '</span><b>' + valeur + '</b>' + (note ? '<small>' + note + '</small>' : '') + '</div>';
			if (etat.a) {
				lignes += va === null
					? ligne('a', d.annee, '—', 'à venir')
					: cumul
						? ligne('a', d.annee, nombre.format(va), parJour(a, i) > 0 ? '+' + nombre.format(parJour(a, i)) + ' ce jour' : '')
						: ligne('a', d.annee, nombre.format(va), va > 1 ? 'inscriptions' : 'inscription');
			}
			if (etat.b) {
				lignes += ligne('b', d.precedente, nombre.format(vb), esc(dateAnnee.format(datePrec(i)).replace(/\./g, '')));
			}
			if (cumul && etat.a && etat.b && va !== null) {
				const e = va - vb;
				lignes += '<div class="tb-infobulle__ecart tb-infobulle__ecart--' + (e > 0 ? 'hausse' : e < 0 ? 'baisse' : 'egal') + '">'
					+ signe(e) + ' par rapport à l\'an dernier à la même date</div>';
			}
			const html = '<strong class="tb-infobulle__titre">' + esc(titre) + '</strong>' + lignes;
			const r = toile.getBoundingClientRect();
			const yHaut = Math.min(va !== null && etat.a ? g.y(va) : g.H, etat.b ? g.y(vb) : g.H);
			montrerBulle(html, r.left + xi, r.top + (bulleAuPoint ? Math.max(g.m.h, yHaut) : g.m.h), toile);
			if (annonce && !bulleAuPoint) {
				annonce.textContent = titre + '. ' + (etat.a ? d.annee + ' : ' + (va === null ? 'à venir' : va) + '. ' : '') + (etat.b ? d.precedente + ' : ' + vb + '.' : '');
			}
		}
		function effacer() {
			etat.i = null;
			cacherBulle(toile);
			const svg = toile.querySelector('svg');
			if (!svg) { return; }
			svg.querySelector('.tb-c-repere').setAttribute('visibility', 'hidden');
			const caseJour = svg.querySelector('.tb-c-case');
			if (caseJour) { caseJour.setAttribute('visibility', 'hidden'); }
			svg.classList.remove('tb-c--survol');
		}

		/* ---------------------------------------------------------- évènements */
		toile.addEventListener('pointermove', (e) => { if (e.target.closest('.tb-c-capteur')) { reperer(indiceAuPoint(e.clientX), true); } });
		toile.addEventListener('pointerdown', (e) => { if (e.target.closest('.tb-c-capteur')) { reperer(indiceAuPoint(e.clientX), true); } });
		/* Au doigt, la bulle reste après le toucher : elle part au prochain toucher ailleurs */
		toile.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch' && document.activeElement !== cadre) { effacer(); } });
		cadre.addEventListener('focus', () => { reperer(etat.i !== null ? etat.i : defaut(), false); });
		cadre.addEventListener('blur', effacer);
		cadre.addEventListener('keydown', (e) => {
			const [min, max] = bornes();
			let i = etat.i !== null ? etat.i : defaut();
			if (e.key === 'ArrowLeft') { i -= e.shiftKey ? 7 : 1; }
			else if (e.key === 'ArrowRight') { i += e.shiftKey ? 7 : 1; }
			else if (e.key === 'Home') { i = min; }
			else if (e.key === 'End') { i = max; }
			else if (e.key === 'Escape') { effacer(); return; }
			else { return; }
			e.preventDefault();
			reperer(Math.min(max, Math.max(min, i)), false);
		});
		function defaut() {
			const [min, max] = bornes();
			return d.limite !== null ? Math.min(max, Math.max(min, d.limite)) : max;
		}

		carte.querySelectorAll('.tb-vue').forEach((bouton) => {
			bouton.addEventListener('click', () => {
				if (etat.vue === bouton.dataset.vue) { return; }
				etat.vue = bouton.dataset.vue;
				etat.i = null;
				etat.anime = true;
				cadre.dataset.vue = etat.vue;
				carte.querySelectorAll('.tb-vue').forEach((v) => v.setAttribute('aria-pressed', String(v === bouton)));
				cacherBulle();
				dessiner();
			});
		});
		carte.querySelectorAll('.tb-serie').forEach((bouton) => {
			bouton.addEventListener('click', () => {
				const cle = bouton.dataset.serie;
				if (etat[cle] && !etat[cle === 'a' ? 'b' : 'a']) { return; }    // au moins une année reste visible
				etat[cle] = !etat[cle];
				bouton.setAttribute('aria-pressed', String(etat[cle]));
				dessiner();
			});
		});

		if (window.ResizeObserver) {
			let largeur = 0;
			new ResizeObserver(() => {
				if (Math.abs(toile.clientWidth - largeur) < 2) { return; }
				largeur = toile.clientWidth;
				cacherBulle();
				dessiner();
			}).observe(toile);
		} else {
			dessiner();
			window.addEventListener('resize', dessiner);
		}
	}

	/* =================================================================================
	   L'ACTIVITÉ : les comptes actifs de chaque jour, empilés par groupe
	   ================================================================================= */
	const GROUPES = [['personnel', 'Personnel', 'a'], ['enseignants', 'Enseignants', 'b'], ['etudiants', 'Étudiants', 'c']];

	function initActivite(carte) {
		const source = carte.querySelector('.tb-donnees-activite');
		const cadre = carte.querySelector('.tb-activite__graphique');
		const toile = carte.querySelector('.tb-activite__toile');
		const annonce = cadre && cadre.querySelector('[data-annonce]');
		if (!source || !cadre || !toile || cadre.dataset.pret) { return; }
		cadre.dataset.pret = '1';
		let d;
		try { d = JSON.parse(source.textContent); } catch (e) { return; }
		const jours = d.jours;
		const total = (j) => j.personnel + j.enseignants + j.etudiants;
		const quand = (j) => Date.parse(j.date + 'T00:00:00Z');
		const g = {};
		let anime = true, actif = null;

		function dessiner() {
			const L = Math.max(220, toile.clientWidth), H = 150;
			const m = { g: 28, d: 4, h: 8, b: 22 };
			const w = L - m.g - m.d, h = H - m.h - m.b;
			const n = jours.length, c = w / n;
			const { haut, pas } = echelle(Math.max(1, ...jours.map(total)));
			const y = (v) => m.h + h - v / haut * h;
			Object.assign(g, { L, H, m, w, h, c, n });
			toile.style.height = H + 'px';
			let s = '<svg width="' + L + '" height="' + H + '" viewBox="0 0 ' + L + ' ' + H + '">';
			for (let v = 0; v <= haut; v += pas) {
				s += '<line class="tb-c-grille" x1="' + m.g + '" x2="' + (L - m.d) + '" y1="' + y(v) + '" y2="' + y(v) + '"/>'
					+ '<text class="tb-c-axe" x="' + (m.g - 6) + '" y="' + (y(v) + 4) + '" text-anchor="end">' + nombre.format(v) + '</text>';
			}
			s += '<rect class="tb-c-case" x="0" y="' + m.h + '" width="' + c + '" height="' + h + '" visibility="hidden"/>';
			const unSurDeux = c < 30;                      // colonne etroite : un jour sur deux, depuis aujourd'hui
			jours.forEach((j, i) => {
				let base = 0;
				GROUPES.forEach(([cle, , ton]) => {
					const v = j[cle];
					if (!v) { return; }
					s += '<rect class="tb-c-pile tb-c-pile--' + ton + '" data-j="' + i + '" style="--i:' + i + '" x="' + (m.g + i * c + c * 0.2) + '" y="' + y(base + v)
						+ '" width="' + (c * 0.6) + '" height="' + (y(base) - y(base + v)) + '"/>';
					base += v;
				});
				const dt = quand(j);
				const premier = new Date(dt).getUTCDate() === 1;
				if (i === n - 1 || (!unSurDeux || (n - 1 - i) % 2 === 0)) {
					const texte = i === n - 1 ? 'auj.' : (premier ? dateCourte.format(dt).replace('.', '') : String(new Date(dt).getUTCDate()));
					s += '<text class="tb-c-axe' + (i === n - 1 ? ' tb-c-axe--jour' : '') + '" x="' + (m.g + i * c + c / 2) + '" y="' + (H - 6) + '" text-anchor="middle">' + esc(texte) + '</text>';
				}
			});
			s += '<rect class="tb-c-capteur" x="' + m.g + '" y="' + m.h + '" width="' + w + '" height="' + h + '"/></svg>';
			toile.innerHTML = s;
			const svg = toile.querySelector('svg');
			if (anime && !sansMouvement()) {
				svg.classList.add('tb-c--entree');
				requestAnimationFrame(() => requestAnimationFrame(() => svg.classList.add('tb-c--joue')));
			}
			anime = false;
			if (actif !== null) { montrer(actif, false); }
		}

		function montrer(i, auPoint) {
			actif = i;
			const svg = toile.querySelector('svg');
			if (!svg) { return; }
			const xi = g.m.g + i * g.c + g.c / 2;
			const caseJour = svg.querySelector('.tb-c-case');
			caseJour.setAttribute('x', xi - g.c / 2);
			caseJour.setAttribute('visibility', 'visible');
			svg.classList.add('tb-c--survol');
			svg.querySelectorAll('.tb-c-pile').forEach((r) => r.classList.toggle('est-active', +r.dataset.j === i));
			const j = jours[i];
			const titre = majuscule(dateLongue.format(quand(j)));
			const html = '<strong class="tb-infobulle__titre">' + esc(titre) + '</strong>'
				+ GROUPES.map(([cle, libelle, ton]) => '<div class="tb-infobulle__ligne"><span class="tb-infobulle__pastille tb-infobulle__pastille--' + cle + '"></span>'
					+ '<span>' + libelle + '</span><b>' + nombre.format(j[cle]) + '</b></div>').join('')
				+ '<div class="tb-infobulle__ecart tb-infobulle__ecart--egal">' + nombre.format(total(j)) + ' compte' + (total(j) > 1 ? 's' : '') + ' actif' + (total(j) > 1 ? 's' : '')
				+ ' · ' + nombre.format(j.requetes) + ' requêtes · ' + nombre.format(j.ecritures) + ' enregistrement' + (j.ecritures > 1 ? 's' : '') + '</div>';
			const r = toile.getBoundingClientRect();
			montrerBulle(html, r.left + xi, r.top + g.m.h, toile);
			if (annonce && !auPoint) {
				annonce.textContent = titre + ' : ' + GROUPES.map(([cle, libelle]) => libelle + ' ' + j[cle]).join(', ') + '.';
			}
		}
		function effacer() {
			actif = null;
			cacherBulle(toile);
			const svg = toile.querySelector('svg');
			if (!svg) { return; }
			svg.querySelector('.tb-c-case').setAttribute('visibility', 'hidden');
			svg.classList.remove('tb-c--survol');
		}
		const auPoint = (e) => {
			const r = toile.getBoundingClientRect();
			return Math.min(g.n - 1, Math.max(0, Math.floor((e.clientX - r.left - g.m.g) / g.c)));
		};

		toile.addEventListener('pointermove', (e) => { if (e.target.closest('.tb-c-capteur')) { montrer(auPoint(e), true); } });
		toile.addEventListener('pointerdown', (e) => { if (e.target.closest('.tb-c-capteur')) { montrer(auPoint(e), true); } });
		toile.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch' && document.activeElement !== cadre) { effacer(); } });
		cadre.addEventListener('focus', () => montrer(actif !== null ? actif : jours.length - 1, false));
		cadre.addEventListener('blur', effacer);
		cadre.addEventListener('keydown', (e) => {
			let i = actif !== null ? actif : jours.length - 1;
			if (e.key === 'ArrowLeft') { i--; } else if (e.key === 'ArrowRight') { i++; }
			else if (e.key === 'Home') { i = 0; } else if (e.key === 'End') { i = jours.length - 1; }
			else if (e.key === 'Escape') { effacer(); return; } else { return; }
			e.preventDefault();
			montrer(Math.min(jours.length - 1, Math.max(0, i)), false);
		});

		if (window.ResizeObserver) {
			let largeur = 0;
			new ResizeObserver(() => {
				if (Math.abs(toile.clientWidth - largeur) < 2) { return; }
				largeur = toile.clientWidth;
				dessiner();
			}).observe(toile);
		} else {
			dessiner();
		}
	}

	/* =================================================================================
	   LES AUTRES GRAPHIQUES
	   ================================================================================= */

	/* Toute bulle déclarée par data-bulle (classement, entonnoir, répartition) */
	function bulleDe(el) {
		/* « gauche<TAB>droite » : deux colonnes alignées (matricule, nom) ;
		   « ## titre » : un intertitre de groupe */
		const lignes = String(el.dataset.bulle || '').split('\n').filter(Boolean)
			.map((l) => {
				if (l.startsWith('## ')) { return '<div class="tb-infobulle__groupe">' + esc(l.slice(3)) + '</div>'; }
				const [g, d] = l.split('\t');
				return d === undefined
					? '<div class="tb-infobulle__ligne"><span>' + esc(l) + '</span></div>'
					: '<div class="tb-infobulle__ligne tb-infobulle__ligne--paire"><b>' + esc(g) + '</b><span>' + esc(d) + '</span></div>';
			}).join('');
		return (el.dataset.bulleTitre ? '<strong class="tb-infobulle__titre">' + esc(el.dataset.bulleTitre) + '</strong>' : '') + lignes
			+ (el.dataset.bullePied ? '<div class="tb-infobulle__ecart tb-infobulle__ecart--egal">' + esc(el.dataset.bullePied) + '</div>' : '');
	}
	function surBulle(e) {
		const el = e.target.closest && e.target.closest('[data-bulle]');
		if (!el || el.closest('.tb-graphique')) { return; }
		/* Une étape du guichet ouverte montre déjà sa liste ; au doigt, le toucher l'ouvre */
		if (el.hasAttribute('data-bulle-si-ferme') && (el.parentElement.open || e.pointerType === 'touch')) { cacherBulle(el); return; }
		if (proprietaire === el && bulle.classList.contains('est-visible')) { return; }   // déjà ouverte
		laBulle().classList.toggle('tb-infobulle--large', el.hasAttribute('data-bulle-large'));
		const r = el.getBoundingClientRect();
		const x = e.type === 'pointerover' && e.clientX ? e.clientX : r.left + r.width / 2;
		montrerBulle(bulleDe(el), x, r.top, el);
	}
	function horsBulle(e) {
		if (e.pointerType === 'touch') { return; }
		const el = e.target.closest && e.target.closest('[data-bulle]');
		if (el && !el.contains(e.relatedTarget)) { cacherBulle(el); }
	}

	/* Répartition : survoler un segment ou une ligne de légende éclaire les deux */
	function eclairer(e) {
		const el = e.target.closest && e.target.closest('.tb-part [data-cle]');
		const part = el && el.closest('.tb-part');
		document.querySelectorAll('.tb-part.est-eclaire').forEach((p) => { if (p !== part) { eteindre(p); } });
		if (!part) { return; }
		part.classList.add('est-eclaire');
		part.querySelectorAll('[data-cle]').forEach((x) => x.classList.toggle('est-actif', x.dataset.cle === el.dataset.cle));
	}
	function eteindre(part) {
		part.classList.remove('est-eclaire');
		part.querySelectorAll('.est-actif').forEach((x) => x.classList.remove('est-actif'));
	}

	/* Saisie des notes : l'anneau prend le taux de la mention survolée */
	function initAnneau(carte) {
		const anneau = carte.querySelector('.tb-anneau');
		const plein = anneau && anneau.querySelector('.tb-anneau__plein');
		const centre = anneau && anneau.querySelector('b');
		const texte = carte.querySelector('.tb-taux__texte');
		if (!plein || !centre || anneau.dataset.pret) { return; }
		anneau.dataset.pret = '1';
		const circ = parseFloat(anneau.dataset.circ);
		const origine = { trait: plein.getAttribute('stroke-dasharray'), centre: centre.textContent, texte: texte.innerHTML };
		let actif = null;
		const montrer = (li) => {
			if (li === actif) { return; }
			actif = li;
			carte.querySelectorAll('.tb-taux__liste li').forEach((x) => x.classList.toggle('est-actif', x === li));
			if (!li) {
				plein.setAttribute('stroke-dasharray', origine.trait);
				centre.textContent = origine.centre;
				texte.innerHTML = origine.texte;
				anneau.classList.remove('est-mention');
				return;
			}
			const t = parseFloat(li.dataset.taux);
			const manque = (+li.dataset.total) - (+li.dataset.saisies);
			plein.setAttribute('stroke-dasharray', (circ * t / 100).toFixed(2) + ' ' + circ);
			centre.textContent = t + ' %';
			anneau.classList.add('est-mention');
			texte.innerHTML = '<b>' + esc(li.dataset.libelle) + '</b><br>' + nombre.format(+li.dataset.saisies) + ' notes saisies sur ' + nombre.format(+li.dataset.total) + '.<br>'
				+ (manque > 0 ? '<span class="tb-taux__manque">' + nombre.format(manque) + ' encore à saisir.</span>' : 'Tout est saisi.');
		};
		/* L'anneau se remplit à l'arrivée */
		if (!sansMouvement()) {
			plein.setAttribute('stroke-dasharray', '0 ' + circ);
			requestAnimationFrame(() => requestAnimationFrame(() => { if (!actif) { plein.setAttribute('stroke-dasharray', origine.trait); } }));
		}
		const liste = carte.querySelector('.tb-taux__liste');
		liste.addEventListener('pointerover', (e) => { const li = e.target.closest('li[data-taux]'); if (li) { montrer(li); } });
		liste.addEventListener('pointerleave', () => montrer(null));
		liste.addEventListener('focusin', (e) => { const li = e.target.closest('li[data-taux]'); if (li) { montrer(li); } });
		liste.addEventListener('focusout', (e) => { if (!liste.contains(e.relatedTarget)) { montrer(null); } });
	}

	/* --- Mise en route ------------------------------------------------------------------- */
	let delegue = false;
	function init() {
		const page = document.querySelector('.tb-page');
		if (!page) { return; }
		page.classList.add('tb-js');
		page.querySelectorAll('.tb-rentree').forEach(initRentree);
		page.querySelectorAll('.tb-taux').forEach(initAnneau);
		page.querySelectorAll('.tb-activite').forEach(initActivite);
		if (!delegue) {
			delegue = true;
			document.addEventListener('pointerover', surBulle);
			/* Après un défilement, le pointeur est déjà sur l'élément sans y « entrer » */
			document.addEventListener('pointermove', surBulle);
			document.addEventListener('focusin', surBulle);
			document.addEventListener('pointerout', horsBulle);
			document.addEventListener('focusout', horsBulle);
			document.addEventListener('pointerover', eclairer);
			document.addEventListener('focusin', eclairer);
			document.addEventListener('scroll', () => cacherBulle(), true);
			/* Étape du guichet ouverte (clic, Entrée) : sa liste remplace la bulle */
			document.addEventListener('toggle', (e) => {
				if (e.target.open && e.target.querySelector) { cacherBulle(e.target.querySelector(':scope > summary')); }
			}, true);
			document.addEventListener('pointerdown', (e) => {
				if (proprietaire && proprietaire.contains && !proprietaire.contains(e.target)) { cacherBulle(); }
			});
		}
		/* Les barres poussent à l'arrivée, et de nouveau quand on change d'année
		   (la zone garde .tb-page : on retire la classe avant de la remettre) */
		page.classList.remove('tb-anime');
		void page.offsetWidth;
		requestAnimationFrame(() => requestAnimationFrame(() => page.classList.add('tb-anime')));
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	document.addEventListener('zone:chargee', init);
})();
