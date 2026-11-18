/* =============================================================================
   Plan du bureau — editeur (/queue/plan). Page : src/queue/queue.plan.php,
   styles : src/css/queue.plan.css ; dessin et parcours : src/js/plan-bureau.js.

   Les pieces se choisissent, se deplacent et se redimensionnent sur le plan
   (alignement sur les murs voisins, pas de 10 cm), ou se reglent au chiffre
   dans l'onglet « Piece » (nom, code, type, position, dimensions, portes).
   L'onglet « Etapes » fixe le parcours : nombre, ordre, nom, precision et
   piece de chaque etape. « Batiment » : dimensions et entree. Le parcours se
   recalcule a chaque changement ; Ctrl+Z / Ctrl+Y annulent et retablissent.
   Rien n'est ecrit avant « Enregistrer » (POST sur la page, jeton CSRF).
   ========================================================================== */
(function () {
	'use strict';

	const PB = window.PlanBureau;
	const source = document.getElementById('peDonnees');
	if (!PB || !source) { return; }

	const $ = (id) => document.getElementById(id);
	const NS = 'http://www.w3.org/2000/svg';
	const S = PB.S;
	const COTES = { haut: 'Haut', bas: 'Bas', gauche: 'Gauche', droite: 'Droite' };

	let D = JSON.parse(source.textContent);
	let plan = copie(D.plan);
	let enregistre = JSON.stringify(plan);
	const passe = [], futur = [];
	let selection = null;
	let dessin = null;
	let glisse = null;

	const svg = $('peSvg');

	/* ------------------------------------------------------------ outils */

	function copie(o) { return JSON.parse(JSON.stringify(o)); }
	const r2 = (v) => Math.round(v * 100) / 100;
	const fr = (v) => String(r2(v)).replace('.', ',');
	const el = (nom, attrs, parent) => {
		const e = document.createElementNS(NS, nom);
		for (const k in attrs || {}) { e.setAttribute(k, attrs[k]); }
		if (parent) { parent.appendChild(e); }
		return e;
	};
	const piece = (id) => plan.pieces.find((p) => p.id === id) || null;
	const etapesDe = (id) => plan.etapes.map((e, i) => (e.piece === id ? i + 1 : 0)).filter(Boolean);
	const libelle = (p) => (p ? (p.nom || (p.code ? 'Pièce ' + p.code : (p.type === 'espace' ? 'Espace sans nom' : 'Pièce sans nom'))) : '?');
	const libelleComplet = (p) => {
		const n = etapesDe(p.id);
		return libelle(p) + (n.length ? ' — étape' + (n.length > 1 ? 's ' : ' ') + n.join(', ') : '');
	};
	function nouvelId() {
		let i = plan.pieces.length + 1;
		while (piece('p' + i)) { i++; }
		return 'p' + i;
	}
	function notifier(message, type) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); return; }
		if (type === 'error') { window.alert(message); }
	}
	async function confirmer(question, titre, bouton, danger) {
		if (window.Toast && typeof window.Toast.confirm === 'function') {
			return window.Toast.confirm(question, { title: titre, confirmText: bouton, cancelText: 'Annuler', danger: !!danger });
		}
		return window.confirm(question);
	}

	/* ---------------------------------------------------------- historique */

	function memoriser() {
		passe.push(JSON.stringify(plan));
		if (passe.length > 120) { passe.shift(); }
		futur.length = 0;
	}
	function changer(fn) {
		memoriser();
		fn();
		toutRendre();
	}
	function annuler() {
		if (!passe.length) { return; }
		futur.push(JSON.stringify(plan));
		plan = JSON.parse(passe.pop());
		if (selection && !piece(selection)) { selection = null; }
		toutRendre();
	}
	function retablir() {
		if (!futur.length) { return; }
		passe.push(JSON.stringify(plan));
		plan = JSON.parse(futur.pop());
		if (selection && !piece(selection)) { selection = null; }
		toutRendre();
	}

	/* -------------------------------------------------------------- dessin */

	let attente = null;
	function rendrePlan() {
		attente = null;
		if (dessin) { dessin.arreter(); }
		dessin = PB.dessiner(svg, plan, { edition: true });
		calque();
		alertes();
		barre();
	}
	function rendrePlanBientot() {
		if (!attente) { attente = requestAnimationFrame(rendrePlan); }
	}

	/** Le calque d'edition : une cible par piece, la selection et ses poignees. */
	function calque() {
		const g = el('g', { class: 'pe-calque' }, svg);
		plan.pieces.slice().sort((a, b) => b.l * b.p - a.l * a.p).forEach((p) => {
			el('rect', {
				x: p.x * S, y: p.y * S, width: p.l * S, height: p.p * S, 'data-id': p.id,
				class: 'pe-cible' + (p.type === 'espace' ? ' pe-cible--espace' : '') + (p.id === selection ? ' pe-cible--choisie' : ''),
			}, g);
			if (!p.nom && !p.code && !etapesDe(p.id).length) {
				el('text', { x: (p.x + p.l / 2) * S, y: (p.y + p.p / 2) * S, class: 'pe-sans-nom' }, g).textContent = 'Sans nom';
			}
		});
		/* L'entree : un clic ouvre ses reglages */
		const o = PB.ouverture({ x: 0, y: 0, l: plan.batiment.l, p: plan.batiment.p }, plan.entree);
		const h = o.o === 'h';
		const ext = plan.entree.cote === 'bas' || plan.entree.cote === 'droite';
		el('rect', {
			class: 'pe-cible-entree', 'data-entree': '1',
			x: h ? o.a * S : (ext ? o.c * S - 10 : o.c * S - 34), y: h ? (ext ? o.c * S - 10 : o.c * S - 34) : o.a * S,
			width: h ? (o.b - o.a) * S : 44, height: h ? 44 : (o.b - o.a) * S,
		}, g);

		const p = piece(selection);
		if (!p) { return; }
		const x = p.x * S, y = p.y * S, w = p.l * S, hh = p.p * S;
		el('rect', { x, y, width: w, height: hh, class: 'pe-contour' }, g);
		const k = svg.viewBox.baseVal.width / Math.max(1, svg.getBoundingClientRect().width);
		const t = Math.max(7, 9 * k);
		[['nw', x, y], ['n', x + w / 2, y], ['ne', x + w, y], ['e', x + w, y + hh / 2],
		 ['se', x + w, y + hh], ['s', x + w / 2, y + hh], ['sw', x, y + hh], ['w', x, y + hh / 2]].forEach(([c, px, py]) => {
			el('rect', { x: px - t / 2, y: py - t / 2, width: t, height: t, class: 'pe-poignee pe-poignee--' + c, 'data-poignee': c }, g);
		});
		const cote = el('text', { x: x + w / 2, y: y - 10 * Math.max(1, k * 0.8), class: 'pe-cotes' }, g);
		cote.textContent = fr(p.l) + ' × ' + fr(p.p) + ' m';
		cote.style.fontSize = (12 * Math.max(1, k * 0.9)) + 'px';
	}

	/* ------------------------------------------------- souris et doigts */

	function point(e) {
		const m = svg.getScreenCTM();
		if (!m) { return { x: 0, y: 0 }; }
		const p = svg.createSVGPoint();
		p.x = e.clientX; p.y = e.clientY;
		const q = p.matrixTransform(m.inverse());
		return { x: q.x / S, y: q.y / S };
	}
	const pixelsParMetre = () => { const m = svg.getScreenCTM(); return m ? m.a * S : 20; };

	/** Aimante une valeur aux murs voisins (bords des autres pieces et du batiment), sinon au decimetre. */
	function aimanter(v, lignes, seuil) {
		let best = null, d = seuil;
		lignes.forEach((l) => { const e = Math.abs(l - v); if (e < d) { d = e; best = l; } });
		return best !== null ? best : Math.round(v * 10) / 10;
	}
	function lignesVoisines(id, axe) {
		const res = axe === 'x' ? [0, plan.batiment.l] : [0, plan.batiment.p];
		plan.pieces.forEach((q) => {
			if (q.id === id) { return; }
			if (axe === 'x') { res.push(q.x, q.x + q.l); } else { res.push(q.y, q.y + q.p); }
		});
		return res;
	}

	svg.addEventListener('pointerdown', (e) => {
		if (e.button !== 0) { return; }
		const poignee = e.target.closest('[data-poignee]');
		const cible = e.target.closest('.pe-cible');
		if (e.target.closest('[data-entree]')) { choisirOnglet('batiment'); $('peEntreePos').focus(); return; }
		if (!poignee && !cible) { selectionner(null); return; }
		const id = poignee ? selection : cible.getAttribute('data-id');
		if (id !== selection) { selectionner(id); }
		const p = piece(id);
		glisse = { id, mode: poignee ? poignee.getAttribute('data-poignee') : 'deplacer', depart: point(e), origine: { x: p.x, y: p.y, l: p.l, p: p.p }, bouge: false };
		svg.setPointerCapture(e.pointerId);
		e.preventDefault();
	});

	svg.addEventListener('pointermove', (e) => {
		if (!glisse) { return; }
		const m = point(e);
		const dx = m.x - glisse.depart.x, dy = m.y - glisse.depart.y;
		if (!glisse.bouge) {
			if (Math.hypot(dx, dy) * pixelsParMetre() < 4) { return; }
			glisse.bouge = true;
			memoriser();
		}
		const p = piece(glisse.id), o = glisse.origine, L = plan.batiment.l, P = plan.batiment.p;
		const seuil = 9 / pixelsParMetre();
		const lx = lignesVoisines(p.id, 'x'), ly = lignesVoisines(p.id, 'y');
		if (glisse.mode === 'deplacer') {
			let x = o.x + dx, y = o.y + dy;
			const ax = aimanter(x, lx, seuil), bx = aimanter(x + o.l, lx, seuil) - o.l;
			x = Math.abs(ax - x) <= Math.abs(bx - x) ? ax : bx;
			const ay = aimanter(y, ly, seuil), by = aimanter(y + o.p, ly, seuil) - o.p;
			y = Math.abs(ay - y) <= Math.abs(by - y) ? ay : by;
			p.x = r2(Math.min(Math.max(0, x), L - o.l));
			p.y = r2(Math.min(Math.max(0, y), P - o.p));
		} else {
			const c = glisse.mode;
			let x1 = o.x, y1 = o.y, x2 = o.x + o.l, y2 = o.y + o.p;
			if (c.includes('w')) { x1 = Math.min(x2 - 0.5, Math.max(0, aimanter(o.x + dx, lx, seuil))); }
			if (c.includes('e')) { x2 = Math.max(x1 + 0.5, Math.min(L, aimanter(o.x + o.l + dx, lx, seuil))); }
			if (c.includes('n')) { y1 = Math.min(y2 - 0.5, Math.max(0, aimanter(o.y + dy, ly, seuil))); }
			if (c.includes('s')) { y2 = Math.max(y1 + 0.5, Math.min(P, aimanter(o.y + o.p + dy, ly, seuil))); }
			p.x = r2(x1); p.y = r2(y1); p.l = r2(x2 - x1); p.p = r2(y2 - y1);
			garderPortes(p);
		}
		rendrePlanBientot();
	});

	const finir = () => {
		if (!glisse) { return; }
		const bouge = glisse.bouge;
		glisse = null;
		if (bouge) { toutRendre(); }
	};
	svg.addEventListener('pointerup', finir);
	svg.addEventListener('pointercancel', finir);

	/** Une porte reste sur son cote quand la piece change de taille. */
	function garderPortes(p) {
		(p.portes || []).forEach((porte) => {
			const long = porte.cote === 'haut' || porte.cote === 'bas' ? p.l : p.p;
			porte.l = r2(Math.min(porte.l, long));
			porte.pos = r2(Math.min(Math.max(porte.pos, porte.l / 2), long - porte.l / 2));
		});
	}

	/* ------------------------------------------------------------ clavier */

	document.addEventListener('keydown', (e) => {
		const saisie = e.target.closest && e.target.closest('input, select, textarea, [contenteditable]');
		const mod = e.ctrlKey || e.metaKey;
		if (mod && !saisie && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); if (e.shiftKey) { retablir(); } else { annuler(); } return; }
		if (mod && !saisie && (e.key === 'y' || e.key === 'Y')) { e.preventDefault(); retablir(); return; }
		if (mod && (e.key === 's' || e.key === 'S')) { e.preventDefault(); enregistrer(); return; }
		if (saisie || !selection || mod) { return; }
		const pas = e.shiftKey ? 1 : 0.1;
		const fl = { ArrowLeft: [-pas, 0], ArrowRight: [pas, 0], ArrowUp: [0, -pas], ArrowDown: [0, pas] }[e.key];
		if (fl) {
			e.preventDefault();
			changer(() => {
				const p = piece(selection);
				p.x = r2(Math.min(Math.max(0, p.x + fl[0]), plan.batiment.l - p.l));
				p.y = r2(Math.min(Math.max(0, p.y + fl[1]), plan.batiment.p - p.p));
			});
		} else if (e.key === 'Delete' || e.key === 'Backspace') {
			e.preventDefault();
			supprimer();
		} else if (e.key === 'Escape') {
			selectionner(null);
		}
	});

	/* ----------------------------------------------------------- onglets */

	const onglets = { etapes: $('peOngletEtapes'), piece: $('peOngletPiece'), batiment: $('peOngletBatiment') };
	const panneaux = { etapes: $('pePanEtapes'), piece: $('pePanPiece'), batiment: $('pePanBatiment') };
	function choisirOnglet(nom, focus) {
		Object.keys(onglets).forEach((k) => {
			const actif = k === nom;
			onglets[k].setAttribute('aria-selected', String(actif));
			onglets[k].tabIndex = actif ? 0 : -1;
			panneaux[k].hidden = !actif;
		});
		if (focus) { onglets[nom].focus(); }
	}
	Object.keys(onglets).forEach((k, i, cles) => {
		onglets[k].addEventListener('click', () => choisirOnglet(k));
		onglets[k].addEventListener('keydown', (e) => {
			const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
			if (d) { e.preventDefault(); choisirOnglet(cles[(i + d + cles.length) % cles.length], true); }
		});
	});

	/* -------------------------------------------------- piece choisie */

	function selectionner(id) {
		selection = id;
		if (id) { choisirOnglet('piece'); }
		rendrePlan();
		formPiece();
	}

	/** Relie un champ au plan : la saisie redessine, et compte pour une seule etape d'annulation. */
	function relier(champ, appliquer, evenement, ensuite) {
		let premier = true;
		champ.addEventListener('focus', () => { premier = true; });
		champ.addEventListener(evenement || 'input', () => {
			if (premier) { memoriser(); premier = false; }
			appliquer(champ);
			rendrePlanBientot();
			barre();
		});
		champ.addEventListener('change', () => { premier = true; if (ensuite) { ensuite(); } });
	}
	/** Le nom d'une piece a change : les listes qui la citent suivent, sans tout reconstruire. */
	function libellesPieces() {
		document.querySelectorAll('#peEtapes select[data-k="piece"] option').forEach((o) => { const p = piece(o.value); if (p) { o.textContent = libelleComplet(p); } });
		listePieces();
	}
	const nombre = (champ) => { const v = parseFloat(String(champ.value).replace(',', '.')); return Number.isFinite(v) ? v : null; };

	relier($('pePieceNom'), (c) => { piece(selection).nom = c.value.slice(0, 60); }, 'input', libellesPieces);
	relier($('pePieceCode'), (c) => { piece(selection).code = c.value.slice(0, 6); }, 'input', libellesPieces);
	[['pePieceX', 'x'], ['pePieceY', 'y'], ['pePieceL', 'l'], ['pePieceP', 'p']].forEach(([id, cle]) => {
		relier($(id), (c) => {
			const v = nombre(c);
			if (v === null) { return; }
			const p = piece(selection);
			p[cle] = r2(cle === 'l' || cle === 'p' ? Math.max(0.5, v) : Math.max(0, v));
			garderPortes(p);
		});
	});
	document.querySelectorAll('input[name="peType"]').forEach((r) => r.addEventListener('change', () => {
		changer(() => { piece(selection).type = r.value; });
	}));

	function formPiece() {
		const p = piece(selection);
		$('pePieceVide').hidden = !!p;
		$('pePiece').hidden = !p;
		if (!p) { listePieces(); return; }
		$('pePieceNom').value = p.nom || '';
		$('pePieceCode').value = p.code || '';
		document.querySelectorAll('input[name="peType"]').forEach((r) => { r.checked = r.value === p.type; });
		$('pePieceX').value = r2(p.x); $('pePieceY').value = r2(p.y); $('pePieceL').value = r2(p.l); $('pePieceP').value = r2(p.p);
		$('pePortesBloc').hidden = p.type !== 'salle';
		const ul = $('pePortes');
		ul.innerHTML = '';
		if (!(p.portes || []).length) {
			const li = document.createElement('li');
			li.className = 'pe-vide';
			li.textContent = etapesDe(p.id).length ? 'Aucune porte : le parcours ne peut pas y entrer.' : 'Aucune porte.';
			ul.appendChild(li);
		}
		(p.portes || []).forEach((porte, i) => {
			const li = document.createElement('li');
			li.className = 'pe-porte';
			li.innerHTML =
				'<label class="pe-champ"><span>Côté</span><select data-k="cote">' + Object.keys(COTES).map((c) => '<option value="' + c + '">' + COTES[c] + '</option>').join('') + '</select></label>' +
				'<label class="pe-champ"><span>Milieu à</span><input type="number" step="0.1" min="0" inputmode="decimal" data-k="pos"></label>' +
				'<label class="pe-champ"><span>Largeur</span><input type="number" step="0.1" min="0.5" inputmode="decimal" data-k="l"></label>' +
				'<button type="button" class="pe-icone pe-icone--petite" data-retirer aria-label="Retirer la porte ' + (i + 1) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
			li.querySelector('[data-k="cote"]').value = porte.cote;
			li.querySelector('[data-k="pos"]').value = r2(porte.pos);
			li.querySelector('[data-k="l"]').value = r2(porte.l);
			relier(li.querySelector('[data-k="cote"]'), (c) => { porte.cote = c.value; garderPortes(p); }, 'change');
			relier(li.querySelector('[data-k="pos"]'), (c) => { const v = nombre(c); if (v !== null) { porte.pos = r2(v); } });
			relier(li.querySelector('[data-k="l"]'), (c) => { const v = nombre(c); if (v !== null) { porte.l = r2(Math.max(0.5, v)); } });
			li.querySelector('[data-retirer]').addEventListener('click', () => changer(() => { p.portes.splice(i, 1); }));
			ul.appendChild(li);
		});
	}

	function listePieces() {
		const ul = $('peListePieces');
		ul.innerHTML = '';
		plan.pieces.slice().sort((a, b) => libelle(a).localeCompare(libelle(b), 'fr')).forEach((p) => {
			const li = document.createElement('li');
			const b = document.createElement('button');
			b.type = 'button';
			b.className = 'pe-lien-piece';
			b.textContent = libelleComplet(p);
			b.addEventListener('click', () => selectionner(p.id));
			li.appendChild(b);
			ul.appendChild(li);
		});
	}

	$('peAjouterPorte').addEventListener('click', () => changer(() => {
		const p = piece(selection);
		p.portes = p.portes || [];
		const l = Math.min(0.9, p.l);
		p.portes.push({ cote: 'bas', pos: r2(p.l / 2), l });
	}));

	$('peAjouter').addEventListener('click', () => {
		const L = plan.batiment.l, P = plan.batiment.p;
		const l = Math.min(4, L), pp = Math.min(3, P);
		const id = nouvelId();
		changer(() => {
			plan.pieces.push({ id, code: '', nom: '', type: 'salle', x: r2((L - l) / 2), y: r2((P - pp) / 2), l, p: pp, portes: [] });
		});
		selectionner(id);
		$('pePieceNom').focus();
	});

	$('peDupliquer').addEventListener('click', () => {
		const p = piece(selection);
		if (!p) { return; }
		const id = nouvelId();
		changer(() => {
			const q = copie(p);
			q.id = id;
			q.x = r2(Math.min(p.x + 1, plan.batiment.l - p.l));
			q.y = r2(Math.min(p.y + 1, plan.batiment.p - p.p));
			plan.pieces.push(q);
		});
		selectionner(id);
	});

	async function supprimer() {
		const p = piece(selection);
		if (!p) { return; }
		const n = etapesDe(p.id);
		if (n.length) {
			notifier('« ' + libelle(p) + ' » accueille l’étape ' + n.join(', ') + ' : changez d’abord la pièce de cette étape, ou retirez l’étape.', 'error');
			return;
		}
		if (!(await confirmer('Supprimer « ' + libelle(p) + ' » du plan ?', 'Supprimer la pièce ?', 'Supprimer', true))) { return; }
		changer(() => { plan.pieces = plan.pieces.filter((q) => q.id !== p.id); });
		selectionner(null);
	}
	$('peSupprimer').addEventListener('click', supprimer);
	$('peDeselectionner').addEventListener('click', () => selectionner(null));

	/* ------------------------------------------------------------ etapes */

	function formEtapes() {
		const ol = $('peEtapes');
		const actif = document.activeElement && ol.contains(document.activeElement) ? document.activeElement.getAttribute('data-ref') : null;
		ol.innerHTML = '';
		if (!plan.etapes.length) {
			const li = document.createElement('li');
			li.className = 'pe-vide';
			li.textContent = 'Aucune étape : l’écran public ne montrera que le plan.';
			ol.appendChild(li);
		}
		plan.etapes.forEach((et, i) => {
			const li = document.createElement('li');
			li.className = 'pe-etape';
			li.innerHTML =
				'<span class="pe-etape__n" aria-hidden="true">' + (i + 1) + '</span>' +
				'<div class="pe-etape__champs">' +
				'<label class="pe-champ"><span>Nom de l’étape ' + (i + 1) + '</span><input type="text" maxlength="80" data-k="nom" data-ref="nom' + i + '" placeholder="Ex. : Dépôt du dossier"></label>' +
				'<label class="pe-champ"><span>Précision</span><input type="text" maxlength="160" data-k="detail" data-ref="detail' + i + '" placeholder="Ex. : Munissez-vous de votre reçu"></label>' +
				'<label class="pe-champ"><span>Pièce</span><select data-k="piece" data-ref="piece' + i + '"></select></label>' +
				'</div>' +
				'<div class="pe-etape__ordre">' +
				'<button type="button" class="pe-icone pe-icone--petite" data-sens="-1" aria-label="Monter l’étape ' + (i + 1) + '"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up" aria-hidden="true"></i></button>' +
				'<button type="button" class="pe-icone pe-icone--petite" data-sens="1" aria-label="Descendre l’étape ' + (i + 1) + '"' + (i === plan.etapes.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down" aria-hidden="true"></i></button>' +
				'<button type="button" class="pe-icone pe-icone--petite pe-icone--danger" data-retirer aria-label="Retirer l’étape ' + (i + 1) + '"><i class="bi bi-trash3" aria-hidden="true"></i></button>' +
				'</div>';
			const sel = li.querySelector('select');
			plan.pieces.slice().sort((a, b) => libelle(a).localeCompare(libelle(b), 'fr')).forEach((p) => {
				const o = document.createElement('option');
				o.value = p.id; o.textContent = libelleComplet(p);
				sel.appendChild(o);
			});
			sel.value = et.piece;
			li.querySelector('[data-k="nom"]').value = et.nom || '';
			li.querySelector('[data-k="detail"]').value = et.detail || '';
			relier(li.querySelector('[data-k="nom"]'), (c) => { et.nom = c.value.slice(0, 80); });
			relier(li.querySelector('[data-k="detail"]'), (c) => { et.detail = c.value.slice(0, 160); });
			sel.addEventListener('change', () => changer(() => { et.piece = sel.value; }));
			li.querySelectorAll('[data-sens]').forEach((b) => b.addEventListener('click', () => {
				const j = i + Number(b.getAttribute('data-sens'));
				changer(() => { const [m] = plan.etapes.splice(i, 1); plan.etapes.splice(j, 0, m); });
				const cible = $('peEtapes').querySelectorAll('.pe-etape')[j];
				const bouton = cible && cible.querySelector('[data-sens="' + b.getAttribute('data-sens') + '"]:not([disabled])');
				(bouton || (cible && cible.querySelector('input'))).focus();
			}));
			li.querySelector('[data-retirer]').addEventListener('click', () => changer(() => { plan.etapes.splice(i, 1); }));
			ol.appendChild(li);
		});
		if (actif) { const e = ol.querySelector('[data-ref="' + actif + '"]'); if (e) { e.focus(); } }
		$('peAjouterEtape').disabled = plan.etapes.length >= 20 || !plan.pieces.length;
	}

	$('peAjouterEtape').addEventListener('click', () => {
		const p = piece(selection) || piece((plan.etapes[plan.etapes.length - 1] || {}).piece) || plan.pieces[0];
		if (!p) { return; }
		changer(() => { plan.etapes.push({ nom: '', detail: '', piece: p.id }); });
		const champs = $('peEtapes').querySelectorAll('[data-k="nom"]');
		if (champs.length) { champs[champs.length - 1].focus(); }
	});

	/* ---------------------------------------------------------- batiment */

	function formBatiment() {
		$('peBatL').value = r2(plan.batiment.l);
		$('peBatP').value = r2(plan.batiment.p);
		$('peEntreeCote').value = plan.entree.cote;
		$('peEntreePos').value = r2(plan.entree.pos);
		$('peEntreeL').value = r2(plan.entree.l);
	}
	relier($('peBatL'), (c) => { const v = nombre(c); if (v !== null && v >= 3) { plan.batiment.l = r2(Math.min(200, v)); } });
	relier($('peBatP'), (c) => { const v = nombre(c); if (v !== null && v >= 3) { plan.batiment.p = r2(Math.min(200, v)); } });
	relier($('peEntreePos'), (c) => { const v = nombre(c); if (v !== null) { plan.entree.pos = r2(v); } });
	relier($('peEntreeL'), (c) => { const v = nombre(c); if (v !== null && v >= 0.6) { plan.entree.l = r2(Math.min(12, v)); } });
	relier($('peEntreeCote'), (c) => {
		plan.entree.cote = c.value;
		const long = c.value === 'haut' || c.value === 'bas' ? plan.batiment.l : plan.batiment.p;
		plan.entree.pos = r2(Math.min(Math.max(plan.entree.pos, plan.entree.l / 2), long - plan.entree.l / 2));
		$('peEntreePos').value = r2(plan.entree.pos);
	}, 'change');

	/* ----------------------------------------------------------- alertes */

	function alertes() {
		const liste = [];
		const L = plan.batiment.l, P = plan.batiment.p;
		plan.pieces.forEach((p) => {
			if (p.x + p.l > L + 0.01 || p.y + p.p > P + 0.01) { liste.push({ grave: true, texte: '« ' + libelle(p) + ' » dépasse du bâtiment : réduisez-la ou agrandissez le bâtiment.', id: p.id }); }
			(p.type === 'salle' ? p.portes || [] : []).forEach((porte, i) => {
				const long = porte.cote === 'haut' || porte.cote === 'bas' ? p.l : p.p;
				if (porte.pos - porte.l / 2 < -0.01 || porte.pos + porte.l / 2 > long + 0.01) { liste.push({ grave: true, texte: '« ' + libelle(p) + ' », porte ' + (i + 1) + ' : elle dépasse du côté ' + porte.cote + '.', id: p.id }); }
			});
		});
		const longE = plan.entree.cote === 'haut' || plan.entree.cote === 'bas' ? L : P;
		if (plan.entree.pos - plan.entree.l / 2 < -0.01 || plan.entree.pos + plan.entree.l / 2 > longE + 0.01) { liste.push({ grave: true, texte: 'L’entrée dépasse du côté ' + plan.entree.cote + ' du bâtiment.' }); }
		const salles = plan.pieces.filter((p) => p.type === 'salle');
		let chevauchements = 0;
		for (let i = 0; i < salles.length && chevauchements < 3; i++) {
			for (let j = i + 1; j < salles.length && chevauchements < 3; j++) {
				const a = salles[i], b = salles[j];
				const w = Math.min(a.x + a.l, b.x + b.l) - Math.max(a.x, b.x), h = Math.min(a.y + a.p, b.y + b.p) - Math.max(a.y, b.y);
				if (w > 0.05 && h > 0.05) { chevauchements++; liste.push({ texte: '« ' + libelle(a) + ' » et « ' + libelle(b) + ' » se chevauchent.', id: a.id }); }
			}
		}
		(dessin ? dessin.itineraire.avertissements : []).forEach((a) => liste.push({ texte: a.message }));

		const ul = $('peAlertes');
		ul.innerHTML = '';
		ul.hidden = !liste.length;
		liste.forEach((a) => {
			const li = document.createElement('li');
			li.className = a.grave ? 'pe-alerte pe-alerte--grave' : 'pe-alerte';
			li.innerHTML = '<i class="bi ' + (a.grave ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle') + '" aria-hidden="true"></i>';
			const t = document.createElement(a.id ? 'button' : 'span');
			t.textContent = a.texte;
			if (a.id) { t.type = 'button'; t.className = 'pe-alerte__lien'; t.addEventListener('click', () => selectionner(a.id)); }
			li.appendChild(t);
			ul.appendChild(li);
		});
		return liste;
	}

	/* --------------------------------------------------- barre et trace */

	const modifie = () => JSON.stringify(plan) !== enregistre;
	function barre() {
		$('peAnnuler').disabled = !passe.length;
		$('peRetablir').disabled = !futur.length;
		const m = modifie();
		const etat = $('peEtat');
		etat.textContent = m ? 'Modifications non enregistrées' : (D.defaut ? 'Plan par défaut, pas encore enregistré' : 'Enregistré');
		etat.classList.toggle('pe-etat--modifie', m);
		$('peEnregistrer').disabled = !D.table || (!m && !D.defaut);
		$('peRestaurer').hidden = !D.precedent;
		const trace = $('peTrace');
		if (D.modifie_le) {
			const d = new Date(String(D.modifie_le).replace(' ', 'T'));
			trace.textContent = 'Enregistré le ' + d.toLocaleDateString('fr-FR') + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + (D.auteur ? ' par ' + D.auteur : '') + '.';
		} else {
			trace.textContent = 'Plan de départ, redessiné d’après le croquis du bureau : dimensions approximatives.';
		}
	}

	function toutRendre() {
		if (selection && !piece(selection)) { selection = null; }
		rendrePlan();
		formPiece();
		formEtapes();
		formBatiment();
	}

	/* --------------------------------------------------------- serveur */

	async function jetonFrais() {
		const rep = await fetch(location.pathname, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
		const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(await rep.text());
		if (m) { window.CSRF_TOKEN = m[1]; }
	}
	async function poster(donnees, deuxieme) {
		donnees.set('csrf_token', window.CSRF_TOKEN || '');
		let rep;
		const options = { method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
		try { rep = await fetch(location.pathname, options); } catch (err) {
			await new Promise((r) => setTimeout(r, 700));
			rep = await fetch(location.pathname, options);   // enregistrer deux fois le meme plan est sans effet
		}
		let json = null;
		try { json = await rep.json(); } catch (x) { /* reponse sans JSON */ }
		if (json && json.code === 'csrf' && !deuxieme) { await jetonFrais(); return poster(donnees, true); }
		if (!rep.ok || !json || !json.success) {
			throw new Error((json && json.message) || 'Le serveur n’a pas répondu : vérifiez la connexion, puis réessayez.');
		}
		return json;
	}
	function recevoir(json) {
		D = Object.assign({}, D, json, { table: true });
		plan = copie(json.plan);
		enregistre = JSON.stringify(plan);
		toutRendre();
	}

	async function enregistrer() {
		const bouton = $('peEnregistrer');
		if (bouton.disabled) { return; }
		if (alertes().some((a) => a.grave)) {
			notifier('Le plan a des erreurs à corriger avant d’être enregistré (en rouge, au-dessus du plan).', 'error');
			return;
		}
		bouton.disabled = true;
		bouton.classList.add('is-attente');
		try {
			const d = new FormData();
			d.set('action', 'enregistrer');
			d.set('plan', JSON.stringify(plan));
			const json = await poster(d);
			recevoir(json);
			notifier(json.message, 'success');
		} catch (err) {
			notifier(err.message, 'error');
		} finally {
			bouton.classList.remove('is-attente');
			barre();
		}
	}
	$('peEnregistrer').addEventListener('click', enregistrer);

	$('peRestaurer').addEventListener('click', async () => {
		const question = (modifie() ? 'Les modifications non enregistrées seront perdues. ' : '')
			+ 'Remettre en service le plan d’avant le dernier enregistrement ? Celui d’aujourd’hui deviendra la version précédente.';
		if (!(await confirmer(question, 'Version précédente', 'Remettre en service'))) { return; }
		try {
			const d = new FormData();
			d.set('action', 'restaurer');
			const json = await poster(d);
			passe.length = 0; futur.length = 0;
			recevoir(json);
			notifier(json.message, 'success');
		} catch (err) {
			notifier(err.message, 'error');
		}
	});

	$('peAnnuler').addEventListener('click', annuler);
	$('peRetablir').addEventListener('click', retablir);

	window.addEventListener('beforeunload', (e) => {
		if (modifie()) { e.preventDefault(); e.returnValue = ''; }
	});
	/* La taille des poignees suit le zoom de l'ecran */
	window.addEventListener('resize', () => { if (selection) { rendrePlanBientot(); } });

	toutRendre();
})();
