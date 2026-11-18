/* =============================================================================
   Plan du bureau — dessin et parcours (sans dependance).

   Utilise par l'ecran public de la file d'attente (src/queue/queue.display.php,
   bouton « Plan du bureau ») et par l'editeur (/queue/plan,
   src/js/plan-bureau-editeur.js). Le plan (data/plan.bureau.php) est en
   metres ; le dessin en unites SVG, 25 par metre.

   Le parcours se calcule ici : une grille de 25 cm sur le batiment, les murs
   (portes ouvertes) bloquent, et chaque troncon (entree -> etape 1 -> etape 2
   ...) cherche le chemin le plus court qui tourne peu, reste au milieu des
   couloirs et longe les troncons deja traces sans les recouvrir.
   Styles : src/css/plan-bureau.css (prefixe « pb- »).
   ========================================================================== */
(function () {
	'use strict';

	const S = 25;            // unites SVG par metre
	const NS = 'http://www.w3.org/2000/svg';
	let instances = 0;

	const el = (nom, attrs, parent) => {
		const e = document.createElementNS(NS, nom);
		if (attrs) { for (const k in attrs) { e.setAttribute(k, attrs[k]); } }
		if (parent) { parent.appendChild(e); }
		return e;
	};
	const horizontal = (cote) => cote === 'haut' || cote === 'bas';

	/* ------------------------------------------------------------ geometrie */

	/** Le trait d'un cote d'un rectangle : { o: 'h'|'v', c: coordonnee, a, b }. */
	function cote(r, c) {
		if (c === 'haut') { return { o: 'h', c: r.y, a: r.x, b: r.x + r.l }; }
		if (c === 'bas') { return { o: 'h', c: r.y + r.p, a: r.x, b: r.x + r.l }; }
		if (c === 'gauche') { return { o: 'v', c: r.x, a: r.y, b: r.y + r.p }; }
		return { o: 'v', c: r.x + r.l, a: r.y, b: r.y + r.p };
	}

	/** Une porte sur le cote d'un rectangle : son ouverture sur le trait. */
	function ouverture(r, porte) {
		const t = cote(r, porte.cote);
		const debut = t.a + porte.pos - porte.l / 2;
		return { o: t.o, c: t.c, a: debut, b: debut + porte.l, cote: porte.cote, r };
	}

	/** Les murs, portes ouvertes : [{ o, c, a, b, ext }]. Les murs communs a
	    deux pieces ne se dessinent qu'une fois, et une porte les ouvre tous. */
	function murs(plan) {
		const bat = { x: 0, y: 0, l: plan.batiment.l, p: plan.batiment.p };
		const lignes = new Map();
		const cle = (o, c) => o + Math.round(c * 100);
		const ajouter = (t, ext) => {
			const k = cle(t.o, t.c);
			if (!lignes.has(k)) { lignes.set(k, { o: t.o, c: t.c, morceaux: [], trous: [], ext: false }); }
			const l = lignes.get(k);
			l.morceaux.push([t.a, t.b]);
			if (ext) { l.ext = true; }
		};
		const percer = (t) => {
			const l = lignes.get(cle(t.o, t.c));
			if (l) { l.trous.push([t.a, t.b]); }
		};
		['haut', 'bas', 'gauche', 'droite'].forEach((c) => ajouter(cote(bat, c), true));
		plan.pieces.forEach((p) => { if (p.type === 'salle') { ['haut', 'bas', 'gauche', 'droite'].forEach((c) => ajouter(cote(p, c), false)); } });
		plan.pieces.forEach((p) => { if (p.type === 'salle') { (p.portes || []).forEach((porte) => percer(ouverture(p, porte))); } });
		percer(ouverture(bat, plan.entree));

		const resultat = [];
		lignes.forEach((l) => {
			const m = l.morceaux.slice().sort((u, v) => u[0] - v[0]);
			const unis = [];
			m.forEach(([a, b]) => {
				const der = unis[unis.length - 1];
				if (der && a <= der[1] + 0.001) { der[1] = Math.max(der[1], b); } else { unis.push([a, b]); }
			});
			unis.forEach(([a, b]) => {
				let morceaux = [[a, b]];
				l.trous.forEach(([ta, tb]) => {
					morceaux = morceaux.flatMap(([u, v]) => (tb <= u || ta >= v) ? [[u, v]] : [[u, ta], [tb, v]].filter(([p, q]) => q - p > 0.02));
				});
				// Un mur exterieur reste exterieur meme s'il double celui d'une salle
				morceaux.forEach(([u, v]) => resultat.push({ o: l.o, c: l.c, a: u, b: v, ext: l.ext }));
			});
		});
		return resultat;
	}

	/* -------------------------------------------------------------- parcours */

	/** Un tas binaire minimal pour A*. */
	function Tas() {
		const t = [];
		return {
			get taille() { return t.length; },
			ajouter(prio, v) {
				t.push([prio, v]);
				let i = t.length - 1;
				while (i > 0) { const p = (i - 1) >> 1; if (t[p][0] <= t[i][0]) { break; } [t[p], t[i]] = [t[i], t[p]]; i = p; }
			},
			retirer() {
				const haut = t[0], fin = t.pop();
				if (t.length) {
					t[0] = fin;
					let i = 0;
					for (;;) {
						const g = 2 * i + 1, d = g + 1;
						let m = i;
						if (g < t.length && t[g][0] < t[m][0]) { m = g; }
						if (d < t.length && t[d][0] < t[m][0]) { m = d; }
						if (m === i) { break; }
						[t[m], t[i]] = [t[i], t[m]]; i = m;
					}
				}
				return haut[1];
			},
		};
	}

	/** Le point ou se pose le numero d'une etape (centre de sa piece, decale
	    si la piece accueille plusieurs etapes). */
	function balises(plan) {
		const parPiece = new Map();
		plan.etapes.forEach((e, i) => { if (!parPiece.has(e.piece)) { parPiece.set(e.piece, []); } parPiece.get(e.piece).push(i); });
		const pos = [];
		parPiece.forEach((liste, id) => {
			const p = plan.pieces.find((q) => q.id === id);
			if (!p) { return; }
			const ecart = Math.min(1.9, (p.l - 0.6) / Math.max(1, liste.length));
			liste.forEach((i, k) => {
				pos[i] = { x: p.x + p.l / 2 + (k - (liste.length - 1) / 2) * ecart, y: p.y + p.p / 2 };
			});
		});
		return pos;
	}

	/**
	 * Le parcours : { depart, troncons: [{ n, points: [[x, y]], atteint }],
	 * balises, avertissements }. Points en metres.
	 */
	function itineraire(plan) {
		const L = plan.batiment.l, P = plan.batiment.p;
		/* Pas de la grille : 25 cm, plus large pour un tres grand batiment */
		const R = Math.max(0.25, Math.max(L, P) / 220);
		const nx = Math.max(2, Math.round(L / R)), ny = Math.max(2, Math.round(P / R));
		const N = nx * ny;
		const bloque = new Uint8Array(N);
		const centre = (i) => [((i % nx) + 0.5) * R, (Math.floor(i / nx) + 0.5) * R];
		const cellule = (x, y) => Math.min(ny - 1, Math.max(0, Math.floor(y / R))) * nx + Math.min(nx - 1, Math.max(0, Math.floor(x / R)));

		murs(plan).forEach((m) => {
			if (m.o === 'h') {
				for (let j = Math.max(0, Math.floor((m.c - R * 0.8) / R)); j <= Math.min(ny - 1, Math.floor((m.c + R * 0.8) / R)); j++) {
					const cy = (j + 0.5) * R;
					if (Math.abs(cy - m.c) > R * 0.56) { continue; }
					for (let i = Math.max(0, Math.floor(m.a / R)); i <= Math.min(nx - 1, Math.floor(m.b / R)); i++) {
						const cx = (i + 0.5) * R;
						if (cx >= m.a - R * 0.24 && cx <= m.b + R * 0.24) { bloque[j * nx + i] = 1; }
					}
				}
			} else {
				for (let i = Math.max(0, Math.floor((m.c - R * 0.8) / R)); i <= Math.min(nx - 1, Math.floor((m.c + R * 0.8) / R)); i++) {
					const cx = (i + 0.5) * R;
					if (Math.abs(cx - m.c) > R * 0.56) { continue; }
					for (let j = Math.max(0, Math.floor(m.a / R)); j <= Math.min(ny - 1, Math.floor(m.b / R)); j++) {
						const cy = (j + 0.5) * R;
						if (cy >= m.a - R * 0.24 && cy <= m.b + R * 0.24) { bloque[j * nx + i] = 1; }
					}
				}
			}
		});

		/* Distance au mur le plus proche, en cellules : le parcours s'en ecarte. */
		const dist = new Uint16Array(N).fill(65535);
		const file = [];
		for (let i = 0; i < N; i++) { if (bloque[i]) { dist[i] = 0; file.push(i); } }
		for (let k = 0; k < file.length; k++) {
			const i = file[k], x = i % nx;
			const voisins = [x > 0 ? i - 1 : -1, x < nx - 1 ? i + 1 : -1, i - nx, i + nx];
			for (const v of voisins) { if (v >= 0 && v < N && dist[v] > dist[i] + 1) { dist[v] = dist[i] + 1; file.push(v); } }
		}
		const usage = new Float32Array(N);
		const libre = (i) => !bloque[i];
		const plusProcheLibre = (i) => {
			if (libre(i)) { return i; }
			const [cx, cy] = centre(i);
			let best = -1, bd = Infinity;
			for (let k = 0; k < N; k++) {
				if (!libre(k)) { continue; }
				const [x, y] = centre(k), d = (x - cx) ** 2 + (y - cy) ** 2;
				if (d < bd) { bd = d; best = k; }
			}
			return best;
		};

		const DX = [1, -1, 0, 0], DY = [0, 0, 1, -1];
		function chercher(de, vers) {
			const g = new Float32Array(N * 4).fill(Infinity);
			const precedent = new Int32Array(N * 4).fill(-1);
			const tas = Tas();
			const [vx, vy] = [vers % nx, Math.floor(vers / nx)];
			const h = (i) => Math.abs((i % nx) - vx) + Math.abs(Math.floor(i / nx) - vy);
			for (let d = 0; d < 4; d++) { g[de * 4 + d] = 0; tas.ajouter(h(de), de * 4 + d); }
			const ferme = new Uint8Array(N * 4);
			while (tas.taille) {
				const s = tas.retirer();
				if (ferme[s]) { continue; }
				ferme[s] = 1;
				const i = s >> 2, d = s & 3;
				if (i === vers) {
					const chemin = [];
					for (let t = s; t !== -1; t = precedent[t]) { chemin.push(t >> 2); }
					return chemin.reverse();
				}
				const x = i % nx, y = Math.floor(i / nx);
				for (let nd = 0; nd < 4; nd++) {
					const X = x + DX[nd], Y = y + DY[nd];
					if (X < 0 || Y < 0 || X >= nx || Y >= ny) { continue; }
					const j = Y * nx + X;
					if (!libre(j)) { continue; }
					const cout = 1 + Math.max(0, 4 - dist[j]) * 0.9 + usage[j] * 1.6 + (nd !== d ? 2.5 : 0);
					const ns = j * 4 + nd, ng = g[s] + cout;
					if (ng < g[ns]) { g[ns] = ng; precedent[ns] = s; tas.ajouter(ng + h(j), ns); }
				}
			}
			return null;
		}
		const simplifier = (pts) => pts.filter((p, k) => {
			if (k === 0 || k === pts.length - 1) { return true; }
			const a = pts[k - 1], b = pts[k + 1];
			return !((a[0] === p[0] && p[0] === b[0]) || (a[1] === p[1] && p[1] === b[1]));
		});

		/* Depart : devant l'entree, dehors, puis la premiere cellule dedans. */
		const e = plan.entree;
		const bat = { x: 0, y: 0, l: L, p: P };
		const t = cote(bat, e.cote);
		const milieu = t.a + e.pos;
		const dehors = { haut: [milieu, -1.3], bas: [milieu, P + 1.3], gauche: [-1.3, milieu], droite: [L + 1.3, milieu] }[e.cote];
		const dedans = { haut: [milieu, 0.2], bas: [milieu, P - 0.2], gauche: [0.2, milieu], droite: [L - 0.2, milieu] }[e.cote];

		const pos = balises(plan);
		const avertissements = [];
		const troncons = [];
		let courant = plusProcheLibre(cellule(dedans[0], dedans[1]));
		let pointCourant = dehors;
		plan.etapes.forEach((etape, k) => {
			const b = pos[k];
			if (!b) { return; }
			const cible = plusProcheLibre(cellule(b.x, b.y));
			const chemin = courant >= 0 && cible >= 0 ? chercher(courant, cible) : null;
			let points;
			if (chemin) {
				points = chemin.map(centre).map(([x, y]) => [Math.round(x * 1000) / 1000, Math.round(y * 1000) / 1000]);
				points[points.length - 1] = [b.x, b.y];
				chemin.forEach((i) => {
					usage[i] += 1;
					const x = i % nx;
					[x > 0 ? i - 1 : -1, x < nx - 1 ? i + 1 : -1, i - nx, i + nx].forEach((v) => { if (v >= 0 && v < N) { usage[v] += 0.5; } });
				});
				/* Le depart du troncon : la balise precedente, ou la porte d'entree */
				const debut = k === 0 ? pointCourant : [pos[k - 1].x, pos[k - 1].y];
				const premier = points[0];
				if (debut[0] === premier[0] || debut[1] === premier[1]) { points.unshift(debut); }
				else { points.unshift([debut[0], premier[1]]); points.unshift(debut); }
				points = simplifier(points);
			} else {
				const piece = plan.pieces.find((p) => p.id === etape.piece);
				avertissements.push({ etape: k + 1, message: 'Étape ' + (k + 1) + ' : aucun passage ne mène à ' + (piece && (piece.nom || piece.code) ? '« ' + (piece.nom || piece.code) + ' »' : 'sa pièce') + '. Ajoutez une porte.' });
				points = [k === 0 ? pointCourant : [pos[k - 1].x, pos[k - 1].y], [b.x, b.y]];
			}
			troncons.push({ n: k + 1, points, atteint: !!chemin });
			courant = cible;
		});
		return { depart: dehors, troncons, balises: pos, avertissements };
	}

	/* ---------------------------------------------------------------- dessin */

	function cheminSvg(points) {
		return points.map((p, k) => (k ? 'L' : 'M') + (p[0] * S).toFixed(1) + ' ' + (p[1] * S).toFixed(1)).join('');
	}

	/**
	 * Dessine le plan dans un <svg>. options :
	 *   animer     un point suit le parcours (sauf mouvement reduit)
	 *   edition    dessin pour l'editeur (src/js/plan-bureau-editeur.js y pose son calque)
	 *   surEtape   (n) appele quand on clique un numero
	 * Renvoie { itineraire, focaliser(n|null), arreter() }.
	 */
	function dessiner(svg, plan, options) {
		const opt = options || {};
		const id = 'pb' + (++instances);
		const L = plan.batiment.l * S, P = plan.batiment.p * S;
		const k = Math.min(3, Math.max(0.8, Math.max(plan.batiment.l, plan.batiment.p) / 28));
		const marge = 44 * k;
		svg.innerHTML = '';
		svg.setAttribute('viewBox', [-marge, -marge, L + 2 * marge, P + 2 * marge].map((v) => v.toFixed(1)).join(' '));
		svg.classList.add('pb-plan');
		svg.style.setProperty('--pb-k', k);

		const defs = el('defs', null, svg);
		const motif = el('pattern', { id: id + '-calque', width: S, height: S, patternUnits: 'userSpaceOnUse' }, defs);
		el('rect', { width: S, height: S, class: 'pb-sol' }, motif);
		el('path', { d: 'M' + S + ' 0H0V' + S, class: 'pb-trame' }, motif);

		el('rect', { x: 0, y: 0, width: L, height: P, fill: 'url(#' + id + '-calque)' }, svg);

		const it = itineraire(plan);
		const etapesDe = new Map();
		plan.etapes.forEach((e, i) => { if (!etapesDe.has(e.piece)) { etapesDe.set(e.piece, []); } etapesDe.get(e.piece).push(i + 1); });

		/* Pieces : teinte des etapes, contour des espaces ouverts en edition */
		const gPieces = el('g', { class: 'pb-pieces' }, svg);
		plan.pieces.forEach((p) => {
			const ns = etapesDe.get(p.id) || [];
			const r = el('rect', {
				x: p.x * S, y: p.y * S, width: p.l * S, height: p.p * S, 'data-piece': p.id,
				class: 'pb-piece' + (ns.length ? ' pb-piece--etape' : '') + (p.type === 'espace' ? ' pb-piece--espace' : ''),
			}, gPieces);
			if (ns.length) { r.setAttribute('data-etapes', ' ' + ns.join(' ') + ' '); }
		});

		/* Murs */
		const gMurs = el('g', { class: 'pb-murs' }, svg);
		murs(plan).forEach((m) => {
			const d = m.o === 'h'
				? 'M' + (m.a * S).toFixed(1) + ' ' + (m.c * S).toFixed(1) + 'H' + (m.b * S).toFixed(1)
				: 'M' + (m.c * S).toFixed(1) + ' ' + (m.a * S).toFixed(1) + 'V' + (m.b * S).toFixed(1);
			el('path', { d, class: m.ext ? 'pb-mur pb-mur--ext' : 'pb-mur' }, gMurs);
		});

		/* Portes : battant et debattement, vers l'interieur de la piece */
		const gPortes = el('g', { class: 'pb-portes' }, svg);
		const battant = (hx, hy, nx, ny, tx, ty, l) => {
			const ax = hx + nx * l, ay = hy + ny * l, bx = hx + tx * l, by = hy + ty * l;
			const sens = (nx * ty - ny * tx) > 0 ? 1 : 0;
			el('path', { d: 'M' + (hx * S) + ' ' + (hy * S) + 'L' + (ax * S) + ' ' + (ay * S), class: 'pb-battant' }, gPortes);
			el('path', { d: 'M' + (ax * S) + ' ' + (ay * S) + 'A' + (l * S) + ' ' + (l * S) + ' 0 0 ' + sens + ' ' + (bx * S) + ' ' + (by * S), class: 'pb-debattement' }, gPortes);
		};
		const normale = { haut: [0, 1], bas: [0, -1], gauche: [1, 0], droite: [-1, 0] };
		plan.pieces.forEach((p) => {
			if (p.type !== 'salle') { return; }
			(p.portes || []).forEach((porte) => {
				const o = ouverture(p, porte);
				const [nx, ny] = normale[porte.cote];
				if (o.o === 'h') { battant(o.a, o.c, nx, ny, 1, 0, porte.l); } else { battant(o.c, o.a, nx, ny, 0, 1, porte.l); }
			});
		});
		const bat = { x: 0, y: 0, l: plan.batiment.l, p: plan.batiment.p };
		const oe = ouverture(bat, plan.entree);
		const [enx, eny] = { haut: [0, 1], bas: [0, -1], gauche: [1, 0], droite: [-1, 0] }[plan.entree.cote];
		const demi = plan.entree.l / 2;
		if (oe.o === 'h') { battant(oe.a, oe.c, enx, eny, 1, 0, demi); battant(oe.b, oe.c, enx, eny, -1, 0, demi); }
		else { battant(oe.c, oe.a, enx, eny, 0, 1, demi); battant(oe.c, oe.b, enx, eny, 0, -1, demi); }

		/* L'entree : perron et libelle, dehors */
		const gEntree = el('g', { class: 'pb-entree' }, svg);
		const ext = 0.95;
		const perron = oe.o === 'h'
			? { x: oe.a * S, y: (plan.entree.cote === 'bas' ? oe.c : oe.c - ext) * S, w: (oe.b - oe.a) * S, h: ext * S }
			: { x: (plan.entree.cote === 'droite' ? oe.c : oe.c - ext) * S, y: oe.a * S, w: ext * S, h: (oe.b - oe.a) * S };
		el('rect', { x: perron.x, y: perron.y, width: perron.w, height: perron.h, class: 'pb-perron' }, gEntree);
		const lib = {
			bas: [(oe.a + oe.b) / 2 * S, P + 38 * k, 0], haut: [(oe.a + oe.b) / 2 * S, -30 * k, 0],
			gauche: [-30 * k, (oe.a + oe.b) / 2 * S, -90], droite: [L + 30 * k, (oe.a + oe.b) / 2 * S, 90],
		}[plan.entree.cote];
		el('text', { x: lib[0], y: lib[1], class: 'pb-libelle-entree', transform: lib[2] ? 'rotate(' + lib[2] + ' ' + lib[0] + ' ' + lib[1] + ')' : '' }, gEntree).textContent = 'Entrée';

		/* Noms et codes des pieces */
		const gNoms = el('g', { class: 'pb-noms' }, svg);
		plan.pieces.forEach((p) => {
			const cx = (p.x + p.l / 2) * S, cy = (p.y + p.p / 2) * S;
			const aEtape = etapesDe.has(p.id);
			const code = p.code || '', nom = p.nom || '';
			if (aEtape) {
				if (nom) { el('text', { x: cx, y: cy + 34 * k, class: 'pb-nom' }, gNoms).textContent = nom; }
				return;
			}
			if (code && nom) {
				el('text', { x: cx, y: cy - 8 * k, class: 'pb-code' }, gNoms).textContent = code;
				el('text', { x: cx, y: cy + 10 * k, class: 'pb-nom' }, gNoms).textContent = nom;
			} else if (code || nom) {
				el('text', { x: cx, y: cy, class: nom ? 'pb-nom pb-nom--seul' : 'pb-code' }, gNoms).textContent = nom || code;
			}
		});

		/* Parcours */
		const gParcours = el('g', { class: 'pb-parcours' }, svg);
		const traces = it.troncons.map((t) => {
			const d = cheminSvg(t.points);
			const p = el('path', { d, class: 'pb-trace' + (t.atteint ? '' : ' pb-trace--rompue'), 'data-seg': t.n }, gParcours);
			return p;
		});
		it.troncons.forEach((t) => el('path', { d: cheminSvg(t.points), class: 'pb-flux', 'data-seg': t.n }, gParcours));
		const gChevrons = el('g', { class: 'pb-chevrons' }, gParcours);
		traces.forEach((p) => {
			const Lp = p.getTotalLength ? p.getTotalLength() : 0;
			if (Lp < 60 * k) { return; }
			const d = Lp - 46 * k;
			const a = p.getPointAtLength(d), b = p.getPointAtLength(d + 1);
			const ang = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
			const g = el('g', { 'data-seg': p.getAttribute('data-seg'), transform: 'translate(' + a.x.toFixed(1) + ' ' + a.y.toFixed(1) + ') rotate(' + ang.toFixed(1) + ') scale(' + k + ')' }, gChevrons);
			el('circle', { r: 8, class: 'pb-chevron-fond' }, g);
			el('path', { d: 'M-2.5 -4L2 0L-2.5 4', class: 'pb-chevron' }, g);
		});

		const marcheur = el('circle', { r: 7 * k, class: 'pb-marcheur', cx: it.depart[0] * S, cy: it.depart[1] * S }, svg);

		/* Numeros des etapes */
		const gBalises = el('g', { class: 'pb-balises' }, svg);
		const lesBalises = it.balises.map((b, i) => {
			if (!b) { return null; }
			const n = i + 1;
			const g = el('g', { class: 'pb-balise', 'data-etape': n, transform: 'translate(' + (b.x * S).toFixed(1) + ' ' + (b.y * S).toFixed(1) + ') scale(' + k + ')' }, gBalises);
			if (opt.surEtape) {
				g.setAttribute('tabindex', '0'); g.setAttribute('role', 'button'); g.setAttribute('aria-label', 'Étape ' + n);
				g.addEventListener('click', () => opt.surEtape(n));
				g.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); opt.surEtape(n); } });
			}
			el('circle', { class: 'pb-halo', r: 19 }, g);
			el('circle', { class: 'pb-disque', r: 19 }, g);
			el('text', { y: 1 }, g).textContent = n;
			return g;
		});

		function focaliser(n) {
			svg.classList.toggle('pb-focus', !!n);
			svg.querySelectorAll('[data-seg]').forEach((e) => e.classList.toggle('pb-hors', !!n && String(e.getAttribute('data-seg')) !== String(n)));
			svg.querySelectorAll('.pb-piece--etape').forEach((r) => r.classList.toggle('pb-vise', !!n && r.getAttribute('data-etapes').indexOf(' ' + n + ' ') >= 0));
			svg.querySelectorAll('.pb-balise').forEach((b) => b.classList.toggle('pb-balise--vise', !!n && b.getAttribute('data-etape') === String(n)));
		}

		/* Le point qui suit le parcours, avec un arret a chaque etape */
		let anime = null;
		const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (!opt.animer || reduit || !traces.length) {
			marcheur.remove();
		} else {
			const VITESSE = 0.16 * k, PAUSE = 800;
			const longueurs = traces.map((p) => p.getTotalLength());
			const total = longueurs.reduce((s, l) => s + l / VITESSE + PAUSE, 0) + PAUSE;
			const atteintes = new Array(traces.length).fill(false);
			let debut = null;
			const pas = (now) => {
				if (debut === null) { debut = now; }
				let t = (now - debut) % total;
				for (let i = 0; i < traces.length; i++) {
					const duree = longueurs[i] / VITESSE;
					if (t < duree) {
						const pt = traces[i].getPointAtLength(longueurs[i] * (t / duree));
						marcheur.setAttribute('cx', pt.x); marcheur.setAttribute('cy', pt.y);
						atteintes[i] = false;
						break;
					}
					t -= duree;
					if (t < PAUSE) {
						const b = lesBalises[i];
						if (b && !atteintes[i]) {
							atteintes[i] = true;
							b.classList.remove('pb-atteinte'); b.getBoundingClientRect(); b.classList.add('pb-atteinte');
						}
						break;
					}
					t -= PAUSE;
				}
				anime = requestAnimationFrame(pas);
			};
			anime = requestAnimationFrame(pas);
		}

		return {
			itineraire: it,
			focaliser,
			arreter() { if (anime) { cancelAnimationFrame(anime); anime = null; } },
		};
	}

	/** La liste des etapes (entree, puis 1, 2...) : boutons qui focalisent le plan. */
	function listeEtapes(ol, plan, surEtape) {
		ol.innerHTML = '';
		const ligne = (pastille, titre, sous, n) => {
			const li = document.createElement('li');
			const b = document.createElement(n ? 'button' : 'div');
			b.className = 'pb-etape' + (n ? '' : ' pb-etape--entree');
			if (n) { b.type = 'button'; b.dataset.etape = n; b.setAttribute('aria-pressed', 'false'); b.addEventListener('click', () => surEtape(n)); }
			const p = document.createElement('span'); p.className = 'pb-pastille'; p.textContent = pastille;
			const t = document.createElement('span'); t.className = 'pb-etape-texte';
			const t1 = document.createElement('span'); t1.className = 'pb-etape-titre'; t1.textContent = titre;
			t.appendChild(t1);
			if (sous) { const t2 = document.createElement('span'); t2.className = 'pb-etape-detail'; t2.textContent = sous; t.appendChild(t2); }
			b.append(p, t); li.appendChild(b); ol.appendChild(li);
		};
		ligne('E', 'Entrée', '', 0);
		plan.etapes.forEach((e, i) => {
			const piece = plan.pieces.find((p) => p.id === e.piece);
			const lieu = piece ? (piece.nom || (piece.code ? 'Pièce ' + piece.code : '')) : '';
			const titre = e.nom || ('Étape ' + (i + 1));
			const sous = [e.nom && lieu ? lieu : '', e.detail].filter(Boolean).join(' · ') || (e.nom ? '' : lieu);
			ligne(String(i + 1), titre, sous, i + 1);
		});
	}

	window.PlanBureau = { S, murs, itineraire, dessiner, listeEtapes, ouverture, cote };
})();
