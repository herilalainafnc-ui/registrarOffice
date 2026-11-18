/* =============================================================================
   Studio UAZ — le moteur de rendu des visuels

   Une seule source pour l'apercu et l'export : l'editeur (src/js/studio.js)
   rend le visuel ici, a taille reelle, puis le reduit a l'ecran ; le service
   de rendu (outils/studio-rendu/) charge exactement ce fichier dans Chromium
   pour l'exporter. Ce qui s'affiche est ce qui sort.

   Entree : la definition du preset (src/studio/presets/*.json), le format, les
   donnees saisies et les images. Sortie : le visuel (HTML et charte.css) et un
   rapport { manquants, debordements, avertissements }.

   - Les textes saisis ne passent jamais par innerHTML : textContent seulement
     (et des <b> crees un par un pour **gras**).
   - Typographie francaise : espace insecable avant : ; ! ? et dans « », et
     pas de veuve au dernier mot du titre.
   - Champs facultatifs vides : leur bloc, leur cellule ou leur rangee
     disparait ; une rangee « recoit » prend la hauteur liberee, une cellule
     « reste » la largeur.
   - Ajustement : un bloc qui a un « min » perd 2 px a la fois tant que sa
     cellule deborde ; au minimum, le debordement est signale et l'export
     refuse (« Texte trop long »). La mesure porte sur le texte vraiment rendu.
   - Charte : une cellule de texte qui se retrouve au bord du visuel (sa
     voisine masquee) garde 40 px de marge de ce cote.
   - Fond perdu (PDF pour l'imprimeur) : le visuel grandit de 3 mm de chaque
     cote et les cellules du bord prolongent leur fond, sans que rien ne
     bouge a l'interieur du format fini.
   - Texte long (communique en A4) : un bloc « flux » qui ne tient pas sur la
     page passe sur une deuxieme, dessinee par « pageSuivante » (en-tete et
     pied repetes) ; la coupe est cherchee mot a mot sur le texte rendu.
   - Mise en page (jalon 3 de « modifier comme dans Canva ») : un element peut etre
     deplace et redimensionne (miseEnPage : { id: { dx, dy, l, h, k } }, en pixels
     du format) ; la grille ne bouge pas, l'element est decale par-dessus, sa cellule
     ne le rogne plus, et le reste du visuel est mesure comme avant.
   - Blocs ajoutes (jalon 4 de « modifier comme dans Canva ») : texte libre, image ou forme
     poses par-dessus le visuel (blocs : [{ id, type, geo: { format: { x, y, l, h } }, ... }]),
     dans l'ordre des calques ; couleurs de la charte et police Archivo seulement. Ils ne
     passent jamais par innerHTML ; sans position pour un format, celle d'un autre format
     est reprise a l'echelle de la largeur.
   - Garde-fous (jalon 5 de « modifier comme dans Canva ») : un element deplace ou un bloc
     ajoute qui depasse la marge de 40 px, sort du visuel, tombe dans les zones d'Instagram
     (story) ou a un texte sous 20 px (a l'echelle de 1080 px) est signale, jamais refuse ;
     l'element porte data-alerte pour que l'editeur le cerne.
   - Intitules (« Lieu », « Heure », « Date »…) : un texte fixe du preset se reecrit pour un visuel
     (donnees._etiquettes : { texte d'origine: texte voulu }) ; vide, il disparait de l'export (a
     l'ecran, il reste en filigrane pour qu'on puisse le retrouver). Le nom et l'adresse de
     l'universite, sa devise et la mention « Document officiel » restent fixes.
   - Story (9:16) : les rangees « cle » doivent rester hors des 250 px du haut
     et du bas, que couvre l'interface d'Instagram ; sinon, un avertissement.
   ========================================================================== */
(function (racine, fabrique) {
	racine.StudioRendu = fabrique(racine.StudioSchema);
})(typeof self !== 'undefined' ? self : this, function (Schema) {
	'use strict';

	const JOURS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
	const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
	const ALIGNER = { debut: 'flex-start', centre: 'center', fin: 'flex-end', etirer: 'stretch', ligneDeBase: 'baseline' };
	const JUSTIFIER = { debut: 'flex-start', centre: 'center', fin: 'flex-end', entre: 'space-between' };
	const TEXTUELS = ['texte', 'label', 'titre', 'liste', 'badge', 'flux'];
	const NBSP = ' ';
	const PAS = 2;            // l'ajustement retire 2 px a la fois
	const MOTS_MIN = 10;      // texte long coupe entre deux pages : environ deux lignes de chaque cote
	const LOGO = 'images/logo-uaz.jpg';
	/* les textes fixes qui ne se reecrivent pas : l'identite de l'universite */
	const ETIQUETTE_VERROUILLEE = /zurcher|spiritus|document officiel/i;
	const etiquetteModifiable = (v) => typeof v === 'string' && v.trim() !== '' && v.indexOf('{') === -1 && !ETIQUETTE_VERROUILLEE.test(v);

	const jeton = (nom) => 'var(--uaz-' + nom + ')';
	const px = (n) => n + 'px';

	/* ------------------------------------------------------- typographie */
	function typo(s) {
		return String(s == null ? '' : s)
			.replace(/[ \t\r\n  ]+/g, ' ').trim()
			.replace(/ ([:;!?»])/g, NBSP + '$1')
			.replace(/« ?/g, '«' + NBSP)
			.replace(/([^  ])»/g, '$1' + NBSP + '»');
	}
	/* Pas de dernier mot seul sur sa ligne ; mais a deux mots (« Inscriptions
	   ouvertes »), les souder interdirait toute coupure : on les laisse. */
	function sansVeuve(s) {
		if (s.split(' ').filter(Boolean).length < 3) { return s; }
		const i = s.lastIndexOf(' ');
		return i > 0 ? s.slice(0, i) + NBSP + s.slice(i + 1) : s;
	}
	function estVide(v) {
		if (v == null) { return true; }
		if (Array.isArray(v)) { return v.filter((x) => String(x == null ? '' : x).trim() !== '').length === 0; }
		return String(v).trim() === '';
	}

	/* ------------------------------------------------- valeurs derivees */
	function lireDate(v) {
		const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v || ''));
		if (!m) { return null; }
		const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
		return d.getUTCMonth() === +m[2] - 1 ? { a: +m[1], m: +m[2], j: +m[3], js: d.getUTCDay() } : null;
	}
	function lireHeure(v) {
		const m = /^\s*(\d{1,2})\s*(?:[hH:]\s*(\d{2})?)?\s*$/.exec(String(v || ''));
		if (!m || +m[1] > 23 || (m[2] && +m[2] > 59)) { return null; }
		return { h: +m[1], mn: m[2] ? +m[2] : null };
	}
	function derive(champ, v, d) {
		if (champ.type === 'date') {
			const x = lireDate(v);
			if (!x) { return ''; }
			const jj = String(x.j).padStart(2, '0'), mm = String(x.m).padStart(2, '0');
			return {
				jour: JOURS[x.js] + ' ' + x.j, mois: MOIS[x.m - 1] + ' ' + x.a, jourSeul: String(x.j), moisSeul: MOIS[x.m - 1], annee: String(x.a),
				numerique: jj + '/' + mm + '/' + x.a, long: x.j + ' ' + MOIS[x.m - 1] + ' ' + x.a, jourMois: x.j + ' ' + MOIS[x.m - 1],
			}[d] || '';
		}
		if (champ.type === 'heure') {
			const x = lireHeure(v);
			if (!x) { return ''; }
			if (d === 'unite') { return x.mn === null ? (x.h <= 1 ? 'heure' : 'heures') : ''; }
			return x.mn === null ? String(x.h) : x.h + 'h' + String(x.mn).padStart(2, '0');
		}
		return '';
	}

	/* ------------------------------------------------------ le contexte */
	function contexte(preset, donnees, images, apercu) {
		const champs = {};
		(preset.champs || []).forEach((c) => { champs[c.id] = c; });
		const val = (id) => (donnees || {})[id];
		const rempli = (id) => {
			if (champs[id]) {
				const c = champs[id];
				if (c.type === 'date') { return !!lireDate(val(id)); }
				if (c.type === 'heure') { return !!lireHeure(val(id)); }
				return !estVide(val(id));
			}
			return !!((images || {})[id] && images[id].src);
		};
		const sc = String(val('sousCode') || '');
		const code = (preset.sousCodes || []).some((s) => s.code === sc) ? sc : preset.code;
		const date = preset.anneeDepuis ? lireDate(val(preset.anneeDepuis)) : null;
		const unRempli = (ids) => String(ids).split('|').some((id) => rempli(id));
		return {
			preset, champs, donnees: donnees || {}, images: images || {}, apercu: !!apercu, val, rempli,
			etiquettes: (donnees && donnees._etiquettes && typeof donnees._etiquettes === 'object') ? donnees._etiquettes : {},
			code, annee: String(date ? date.a : new Date().getFullYear()),
			pagination: '',   // « Page 1 sur 2 » quand le texte long passe sur deux pages
			/* « si » : l'un des champs est rempli ; « sauf » : aucun ne l'est (l'adresse ecrite, sauf s'il y a un QR code) */
			condition(si, sauf) { return (si == null || unRempli(si)) && (sauf == null || !unRempli(sauf)); },
		};
	}

	/* Un gabarit « · {diffusion} » : le texte, les champs cites, et s'il faut le
	   taire (un champ vide) ou le montrer a remplir (champ obligatoire, apercu).
	   {objet|poste} : le premier des deux qui est rempli. {element} et {numero} :
	   l'element d'une rangee repetee (la grille des filieres). */
	function gabarit(g, ctx) {
		const champsCites = [];
		let vide = false, aRemplir = false;
		const valeurDe = (id, d) => {
			const c = ctx.champs[id];
			if (!c) { return ''; }
			return d ? derive(c, ctx.val(id), d) : String(ctx.val(id) == null ? '' : ctx.val(id));
		};
		const texte = String(g || '').replace(/\{([a-zA-Z0-9|]+)(?::([a-zA-Z]+))?\}/g, (m, ids, d) => {
			if (ids === 'code') { return ctx.code; }
			if (ids === 'annee') { return ctx.annee; }
			if (ids === 'pagination') { return ctx.pagination || ''; }
			if (ids === 'element') { return ctx.element ? ctx.element.texte : ''; }
			if (ids === 'numero') { return ctx.element ? ctx.element.numero : ''; }
			const liste = ids.split('|').filter((id) => ctx.champs[id]);
			if (!liste.length) { return ''; }
			const id = liste.find((x) => valeurDe(x, d).trim() !== '') || liste[0];
			champsCites.push(id);
			const c = ctx.champs[id];
			const v = valeurDe(id, d);
			if (v.trim() === '') {
				/* « [Date] » une seule fois : sur le jour, pas sur le mois ; sur l'heure, pas sur « heures » */
				const principal = !d || ['jour', 'jourSeul', 'valeur', 'numerique', 'long', 'jourMois'].indexOf(d) !== -1;
				if (c.requis && ctx.apercu && principal && !ctx.rempli(id)) { aRemplir = true; return '[' + c.libelle + ']'; }
				vide = true;
			}
			return v;
		});
		return { texte: typo(texte), champs: champsCites, vide, aRemplir };
	}

	/* ------------------------------------------------------- les blocs */
	function el(balise, classe) {
		const e = document.createElement(balise);
		if (classe) { e.className = classe; }
		return e;
	}
	function marges(e, m) {
		if (m == null) { return; }
		const t = Schema.margesDe(m);
		e.style.margin = t.map(px).join(' ');
	}
	/* La taille passe par --t : l'ajustement la change sans toucher au reste. */
	function taille(e, n, min) {
		e.style.setProperty('--t', px(n));
		e.dataset.t = String(n);
		if (min != null && min < n) { e.dataset.min = String(min); }
	}
	function styleTexte(e, b, defauts, fond) {
		const o = Object.assign({}, defauts, b);
		taille(e, o.taille, b.min);
		e.style.fontSize = 'var(--t)';
		e.style.fontWeight = String(o.graisse);
		if (o.approche) { e.style.letterSpacing = o.approche + 'em'; }
		if (o.interligne) { e.style.lineHeight = String(o.interligne); }
		if (o.etirement) { e.style.fontStretch = o.etirement + '%'; }
		if (o.majuscules) { e.style.textTransform = 'uppercase'; }
		if (o.uneLigne) { e.classList.add('uaz-une-ligne'); }
		e.style.color = jeton(o.couleur || (o.label ? Schema.COULEUR_LABEL : Schema.COULEUR_TEXTE)[fond]);
		marges(e, b.marges);
	}
	function remplirTexte(e, texte, gras) {
		if (!gras) { e.textContent = texte; return; }
		texte.split(/\*\*(.+?)\*\*/).forEach((morceau, i) => {
			if (morceau === '') { return; }
			if (i % 2) { const b = el('b'); b.textContent = morceau; e.appendChild(b); } else { e.appendChild(document.createTextNode(morceau)); }
		});
	}

	const BLOCS = {
		texte(b, env, defauts) {
			/* un intitule fixe reecrit pour ce visuel (vide : absent de l'export, en filigrane a l'ecran) */
			const fixe = etiquetteModifiable(b.valeur);
			const remplace = fixe && Object.prototype.hasOwnProperty.call(env.ctx.etiquettes, b.valeur) ? String(env.ctx.etiquettes[b.valeur]).slice(0, 60) : null;
			if (remplace === '' && !env.ctx.apercu) { return null; }
			const g = gabarit(remplace !== null && remplace !== '' ? remplace.replace(/[{}]/g, '') : b.valeur, env.ctx);
			if (g.vide || g.texte === '') { return null; }
			const e = el('div', 'uaz-texte');
			styleTexte(e, b, Object.assign({ taille: 18, graisse: 500 }, defauts), env.fond);
			remplirTexte(e, g.texte, b.gras);
			if (g.champs[0]) { e.dataset.champ = g.champs[0]; }
			if (fixe) { e.dataset.etiquette = b.valeur; }
			if (g.aRemplir || remplace === '') { e.classList.add('uaz-a-remplir'); }
			return e;
		},
		label(b, env) {
			return BLOCS.texte(b, env, { taille: 17, graisse: 800, approche: 0.16, majuscules: true, label: true });
		},
		titre(b, env) {
			const g = gabarit(b.valeur, env.ctx);
			if (g.vide || g.texte === '') { return null; }
			const e = el('div', 'uaz-texte uaz-titre');
			styleTexte(e, b, { taille: 58, graisse: 800, interligne: 0.98, etirement: 92, approche: -0.025 }, env.fond);
			const titre = g.aRemplir ? g.texte : sansVeuve(g.texte);
			const motCle = b.motCle ? gabarit(b.motCle, env.ctx) : null;
			const cle = motCle && !motCle.vide && !motCle.aRemplir ? motCle.texte : '';
			const pos = cle ? titre.replace(/ /g, ' ').toLowerCase().indexOf(cle.replace(/ /g, ' ').toLowerCase()) : -1;
			if (cle && pos === -1) { env.rapport.avertissements.push({ champ: motCle.champs[0], message: "Le mot-clé en or n'apparaît pas tel quel dans le titre." }); }
			if (pos >= 0) {
				e.appendChild(document.createTextNode(titre.slice(0, pos)));
				const s = el('span', 'uaz-mot-cle');
				s.textContent = titre.slice(pos, pos + cle.length);
				if (b.couleurMotCle) { s.style.color = jeton(b.couleurMotCle); }
				if (motCle.champs[0]) { s.dataset.champ = motCle.champs[0]; }
				e.appendChild(s);
				e.appendChild(document.createTextNode(titre.slice(pos + cle.length)));
			} else {
				e.textContent = titre;
			}
			if (g.champs[0]) { e.dataset.champ = g.champs[0]; }
			if (g.aRemplir) { e.classList.add('uaz-a-remplir'); }
			return e;
		},
		ressort() { return el('div', 'uaz-ressort'); },
		filet(b) {
			const e = el('div', 'uaz-filet');
			e.style.flexShrink = '0';
			e.style.width = b.largeur === 'plein' || b.largeur == null ? '100%' : px(b.largeur);
			e.style.maxWidth = '100%';
			e.style.height = px(b.epaisseur || 2);
			e.style.background = jeton(b.couleur || 'navy');
			marges(e, b.marges);
			return e;
		},
		liste(b, env) {
			const c = env.ctx.champs[b.champ];
			const elements = (Array.isArray(env.ctx.val(b.champ)) ? env.ctx.val(b.champ) : []).map(typo).filter((x) => x !== '');
			if (!elements.length) { return null; }
			const e = el('div', 'uaz-liste');
			e.dataset.champ = b.champ;
			taille(e, b.taille || 29, b.min);
			marges(e, b.marges);
			const filet = (b.epaisseurFilet || 1.5) + 'px solid ' + jeton('rule');
			const couleurLabel = jeton(Schema.COULEUR_LABEL[env.fond]);
			if (b.libelle) {
				const l = el('div', 'uaz-texte uaz-liste__libelle');
				styleTexte(l, { taille: b.tailleLibelle || 17, approche: b.approcheLibelle || 0.16 }, { graisse: 800, majuscules: true, label: true }, env.fond);
				l.style.paddingBottom = px(b.espaceLibelle == null ? 16 : b.espaceLibelle);
				l.style.borderBottom = filet;
				l.textContent = b.libelle;
				e.appendChild(l);
			}
			elements.forEach((texte, i) => {
				const dernier = i === elements.length - 1;
				const ligne = el('div', 'uaz-liste__element');
				ligne.style.gap = px(b.espace == null ? 22 : b.espace);
				ligne.style.padding = px(b.pas == null ? 18 : b.pas) + ' 0' + (dernier ? ' 0' : '');
				if (!dernier) { ligne.style.borderBottom = filet; }
				const n = el('span', 'uaz-liste__numero');
				n.style.fontSize = px(b.tailleNumero || 13);
				n.style.fontWeight = '800';
				n.style.color = couleurLabel;
				n.textContent = String(i + 1).padStart(2, '0');
				const t = el('span', 'uaz-texte');
				t.style.fontSize = 'var(--t)';
				t.style.fontWeight = String(b.graisse || 700);
				if (b.approche) { t.style.letterSpacing = b.approche + 'em'; }
				t.style.color = jeton(b.couleur || Schema.COULEUR_TEXTE[env.fond]);
				t.textContent = texte;
				ligne.appendChild(n);
				ligne.appendChild(t);
				e.appendChild(ligne);
			});
			if (c && c.max != null && elements.length > c.max) { env.rapport.avertissements.push({ champ: b.champ, message: 'Au plus ' + c.max + ' éléments.' }); }
			return e;
		},
		image(b, env) {
			const im = env.ctx.images[b.emplacement];
			if (!im || !im.src) { return null; }
			const def = (env.ctx.preset.images || []).find((x) => x.id === b.emplacement) || {};
			const ajustement = im.ajustement || def.ajustement || 'contenir';
			const fondre = im.fondre != null ? im.fondre : def.fondre !== false;
			const e = el('div', 'uaz-image uaz-image--' + ajustement + (fondre ? ' uaz-image--fondre' : ''));
			e.dataset.image = b.emplacement;
			const img = el('img');
			img.alt = String(im.alt || '');
			img.decoding = 'sync';
			/* « contenir » : l'image entiere (illustrations) ; « remplir » : le cadre plein, recadre
			   apres la mise en page (cadrer : point focal et zoom). */
			if (ajustement === 'contenir') { img.style.height = (b.hauteur || 94) + '%'; }
			const filtre = filtreImage(im.reglages, env);
			if (filtre) { img.style.filter = filtre; }
			img.src = im.src;
			e.appendChild(img);
			const g = b.etiquette ? gabarit(b.etiquette, env.ctx) : null;
			if (g && !g.vide && g.texte !== '') {
				const t = el('div', 'uaz-image__etiquette');
				const pos = b.positionEtiquette || [18, 20];
				t.style.top = px(pos[0]);
				t.style.right = px(pos[1]);
				t.style.fontSize = px(b.tailleEtiquette || 14);
				t.style.fontWeight = '800';
				t.style.letterSpacing = (b.approcheEtiquette == null ? 0.14 : b.approcheEtiquette) + 'em';
				t.style.textTransform = 'uppercase';
				t.style.color = jeton('navy');
				t.textContent = g.texte;
				if (g.champs[0]) { t.dataset.champ = g.champs[0]; }
				e.appendChild(t);
			}
			return e;
		},
		logo(b, env) {
			const img = el('img', 'uaz-logo');
			img.src = env.base + LOGO;
			img.alt = "Logo de l'Université Adventiste Zurcher";
			img.decoding = 'sync';
			img.style.width = img.style.height = px(b.taille || 78);
			marges(img, b.marges);
			return img;
		},
		badge(b, env) {
			const fixe = etiquetteModifiable(b.valeur);
			const remplace = fixe && Object.prototype.hasOwnProperty.call(env.ctx.etiquettes, b.valeur) ? String(env.ctx.etiquettes[b.valeur]).slice(0, 60) : null;
			if (remplace === '' && !env.ctx.apercu) { return null; }
			const g = gabarit(remplace !== null && remplace !== '' ? remplace.replace(/[{}]/g, '') : b.valeur, env.ctx);
			if (g.vide || g.texte === '') { return null; }
			const e = el('div', 'uaz-texte uaz-badge');
			if (fixe) { e.dataset.etiquette = b.valeur; }
			if (remplace === '') { e.classList.add('uaz-a-remplir'); }
			const fond = b.fond || env.fond;
			styleTexte(e, b, { taille: 14, graisse: 800, approche: 0.14, majuscules: true, label: true }, fond);
			if (b.fond) { e.style.background = jeton(b.fond); }
			if (b.bordure) { e.style.border = px(b.bordure) + ' solid currentColor'; }
			const r = b.remplissage || [7, 12];
			e.style.padding = px(r[0]) + ' ' + px(r[1]);
			e.textContent = g.texte;
			return e;
		},
		pastille(b) {
			const e = el('span', 'uaz-pastille');
			e.style.width = e.style.height = px(b.taille || 12);
			e.style.background = jeton(b.couleur || 'goldLight');
			marges(e, b.marges);
			return e;
		},
		groupe(b, env) {
			const e = el('div', 'uaz-groupe');
			disposer(e, b);
			marges(e, b.marges);
			(b.blocs || []).forEach((sous) => { const x = bloc(sous, env); if (x) { e.appendChild(x); } });
			return e.childElementCount ? e : null;
		},
		/* Un texte long (champ « paragraphes ») : un paragraphe par ligne saisie. Sa
		   tranche de mots [debut, fin) vient de la pagination ; e._remplir(fin) le
		   redessine jusqu'a un autre mot pour chercher la coupe. */
		flux(b, env) {
			const id = (Schema.variables(b.valeur)[0] || {}).id;
			const mots = motsDe(env.ctx.val(id));
			const t = (env.tranches || {})[id] || [0, mots.length];
			if (t[0] >= t[1]) { return null; }
			const e = el('div', 'uaz-texte uaz-flux');
			styleTexte(e, b, { taille: 16, graisse: 500, interligne: 1.5 }, env.fond);
			e.dataset.champ = id;
			e.dataset.flux = id;
			const espace = b.espace == null ? 12 : b.espace;
			e._debut = t[0];
			e._total = mots.length;
			e._mots = mots;
			e._remplir = (fin) => {
				e.replaceChildren();
				let p = null;
				for (let i = t[0]; i < fin; i++) {
					if (!p || mots[i].p !== mots[i - 1].p) {
						p = el('p', 'uaz-flux__p');
						p.style.margin = '0 0 ' + px(espace) + ' 0';
						p.textContent = mots[i].m;
						e.appendChild(p);
					} else {
						p.textContent += ' ' + mots[i].m;
					}
				}
				if (p) { p.style.marginBottom = '0'; }
			};
			e._remplir(t[1]);
			return e;
		},
		/* Un QR code vers une adresse saisie : marine sur creme, zone de silence de
		   4 modules, modules entiers (net en PNG), en SVG (vectoriel en PDF). */
		qr(b, env) {
			const g = gabarit(b.valeur, env.ctx);
			const Q = typeof self !== 'undefined' ? self.qrcode : null;
			if (g.vide || g.aRemplir || g.texte === '' || !Q) { return null; }
			const id = g.champs[0];
			const adresse = String(env.ctx.val(id) == null ? '' : env.ctx.val(id)).trim();
			Q.stringToBytes = Q.stringToBytesFuncs['UTF-8'];
			const q = Q(0, 'M');
			q.addData(adresse, 'Byte');
			q.make();
			const n = q.getModuleCount(), zone = b.zone == null ? 4 : b.zone, cote = n + 2 * zone;
			const module = Math.max(1, Math.floor(b.taille / cote));
			const NS = 'http://www.w3.org/2000/svg';
			const svg = document.createElementNS(NS, 'svg');
			svg.setAttribute('viewBox', '0 0 ' + cote + ' ' + cote);
			svg.setAttribute('width', String(module * cote));
			svg.setAttribute('height', String(module * cote));
			svg.setAttribute('shape-rendering', 'crispEdges');
			svg.setAttribute('role', 'img');
			svg.setAttribute('aria-label', 'QR code : ' + adresse);
			let d = '';
			for (let r = 0; r < n; r++) { for (let c = 0; c < n; c++) { if (q.isDark(r, c)) { d += 'M' + (c + zone) + ' ' + (r + zone) + 'h1v1h-1z'; } } }
			const chemin = document.createElementNS(NS, 'path');
			chemin.setAttribute('d', d);
			chemin.style.fill = jeton('navy');
			svg.appendChild(chemin);
			const e = el('div', 'uaz-qr');
			e.style.width = e.style.height = px(b.taille);
			e.dataset.champ = id;
			marges(e, b.marges);
			e.appendChild(svg);
			return e;
		},
	};
	/* ------------------------------------------------------- les images */
	/* Reglages rapides (demande du kit : limites et dans la marque) : lumiere, contraste,
	   saturation en points de -100 a +100 (bornes plus serrees ci-dessous), et un mode :
	   couleur, noir et blanc, ou duotone marine/creme (filtre SVG de la page). */
	const REGLAGES = { lumiere: [-40, 40], contraste: [-40, 40], saturation: [-100, 60] };
	const DUOTONE = { sombre: [0x23, 0x2A, 0x5F], clair: [0xF4, 0xF2, 0xED] };   // navy, cream
	let numeroPage = 0;
	const borne = (v, b) => Math.max(b[0], Math.min(b[1], Number(v) || 0));
	function filtreImage(r, env) {
		if (!r) { return ''; }
		const f = [];
		const l = borne(r.lumiere, REGLAGES.lumiere), c = borne(r.contraste, REGLAGES.contraste), s = borne(r.saturation, REGLAGES.saturation);
		if (l) { f.push('brightness(' + (1 + l / 100) + ')'); }
		if (c) { f.push('contrast(' + (1 + c / 100) + ')'); }
		if (r.mode === 'nb') { f.push('grayscale(1)'); } else if (r.mode === 'duotone') { env.page.duotone = true; f.push('url(#' + env.page.idDuotone + ')'); } else if (s) { f.push('saturate(' + (1 + s / 100) + ')'); }
		return f.join(' ');
	}
	/* Le duotone : la luminance, puis du marine (ombres) au creme (lumieres) */
	function filtreDuotone(id) {
		const NS = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS(NS, 'svg');
		svg.setAttribute('class', 'uaz-filtres');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('width', '0');
		svg.setAttribute('height', '0');
		const filtre = document.createElementNS(NS, 'filter');
		filtre.setAttribute('id', id);
		filtre.setAttribute('color-interpolation-filters', 'sRGB');
		const gris = document.createElementNS(NS, 'feColorMatrix');
		gris.setAttribute('type', 'matrix');
		gris.setAttribute('values', '0.2126 0.7152 0.0722 0 0  0.2126 0.7152 0.0722 0 0  0.2126 0.7152 0.0722 0 0  0 0 0 1 0');
		const table = document.createElementNS(NS, 'feComponentTransfer');
		['R', 'G', 'B'].forEach((canal, i) => {
			const fn = document.createElementNS(NS, 'feFunc' + canal);
			fn.setAttribute('type', 'table');
			fn.setAttribute('tableValues', (DUOTONE.sombre[i] / 255).toFixed(4) + ' ' + (DUOTONE.clair[i] / 255).toFixed(4));
			table.appendChild(fn);
		});
		filtre.append(gris, table);
		svg.appendChild(filtre);
		return svg;
	}

	/* Apres la mise en page : chaque image « remplir » recadree (point focal x, y en part
	   de l'image, zoom de 1 a 4 au-dessus du plein cadre ; le meme point reste au centre
	   d'un format a l'autre), et sa resolution verifiee : agrandie au-dela de 100 % (ecran)
	   ou sous 150 points par pouce (A4), elle sera floue. Le rapport garde, pour l'editeur,
	   la partie visible de chaque image (rapport.images). */
	const DPI_IMPRESSION = 150;
	function cadrer(page, format, ctx, rapport) {
		page.querySelectorAll('.uaz-image[data-image]').forEach((cadre) => {
			const id = cadre.dataset.image;
			const img = cadre.querySelector('img');
			const im = ctx.images[id] || {};
			const iw = img.naturalWidth, ih = img.naturalHeight, fw = cadre.clientWidth, fh = cadre.clientHeight;
			if (!iw || !ih || !fw || !fh) { return; }
			let echelle, visible;
			if (cadre.classList.contains('uaz-image--remplir')) {
				const c = im.cadrage || {};
				const zoom = Math.max(1, Math.min(4, Number(c.zoom) || 1));
				echelle = Math.max(fw / iw, fh / ih) * zoom;
				const w = iw * echelle, h = ih * echelle;
				const x = Number.isFinite(+c.x) ? +c.x : 0.5, y = Number.isFinite(+c.y) ? +c.y : 0.5;
				const gauche = Math.min(0, Math.max(fw - w, fw / 2 - x * w));
				const haut = Math.min(0, Math.max(fh - h, fh / 2 - y * h));
				Object.assign(img.style, { position: 'absolute', maxWidth: 'none', width: px(w), height: px(h), left: px(gauche), top: px(haut) });
				visible = [-gauche / w, -haut / h, fw / w, fh / h];
			} else {
				echelle = Math.min(img.offsetWidth / iw, img.offsetHeight / ih);
				visible = [0, 0, 1, 1];
			}
			if (!rapport.images[id]) { rapport.images[id] = { cadre: [fw, fh], source: [iw, ih], echelle, visible }; }
			/* la resolution : pixels de l'image par pixel du visuel */
			const plafond = format === 'A4' ? 96 / DPI_IMPRESSION : 1;
			if (echelle > plafond * 1.05 && !rapport.avertissements.some((a) => a.champ === id && a.resolution)) {
				const besoin = [Math.ceil(iw * echelle / plafond), Math.ceil(ih * echelle / plafond)];
				rapport.avertissements.push({
					champ: id, resolution: true,
					message: format === 'A4'
						? 'Résolution trop faible pour l\'impression : ' + Math.round(96 / echelle) + ' points par pouce (150 au moins). Une image d\'au moins ' + besoin[0] + ' × ' + besoin[1] + ' px, ou moins de zoom.'
						: 'L\'image est agrandie à ' + Math.round(echelle * 100) + ' % : elle sera floue. Une image d\'au moins ' + besoin[0] + ' × ' + besoin[1] + ' px, ou moins de zoom.',
				});
			}
		});
	}

	/* Les mots d'un texte long, avec leur paragraphe (une ligne saisie = un paragraphe). */
	function motsDe(v) {
		const sortie = [];
		String(v == null ? '' : v).split(/\r?\n/).map(typo).filter((p) => p !== '')
			.forEach((p, ip) => p.split(' ').forEach((m) => sortie.push({ p: ip, m })));
		return sortie;
	}

	function disposer(e, o) {
		e.style.flexDirection = o.disposition === 'ligne' ? 'row' : 'column';
		if (o.aligner) { e.style.alignItems = ALIGNER[o.aligner]; }
		if (o.justifier) { e.style.justifyContent = JUSTIFIER[o.justifier]; }
		if (o.espace) { e.style.gap = px(o.espace); }
	}
	function bloc(b, env) {
		if (!env.ctx.condition(b.si, b.sauf) || !BLOCS[b.type]) { return null; }
		const e = BLOCS[b.type](b, env);
		if (!e) { return null; }
		/* Mise en page commune : la signature du communique (filet au-dessus,
		   calee a droite, largeur minimale), un badge centre dans sa ligne. */
		if (b.alignerSoi) { e.style.alignSelf = ALIGNER[b.alignerSoi]; }
		if (b.retrait != null) { e.style.padding = Schema.margesDe(b.retrait).map(px).join(' '); }
		if (b.filetHaut) { e.style.borderTop = px(b.filetHaut) + ' solid ' + jeton(b.couleurFilet || 'navy'); }
		if (b.largeurMin) { e.style.minWidth = px(b.largeurMin); }
		return e;
	}
	function blocsA_plat(blocs, sortie) {
		(blocs || []).forEach((b) => { sortie.push(b); if (b.type === 'groupe') { blocsA_plat(b.blocs, sortie); } });
		return sortie;
	}

	/* ---------------------------------------------------- la grille */
	/* Une page : les rangees du format (ou de sa pageSuivante), dans le format fini,
	   ou agrandie du fond perdu « env.fp » de chaque cote. env.mode : « seule »,
	   « premiere » (le texte long continue page suivante : les rangees « derniere »
	   et les blocs qui suivent le flux attendent la derniere page) ou « suivante ». */
	function construire(fmt, rangeesDef, format, env) {
		const ctx = env.ctx, fp = env.fp || 0;
		env.page = { idDuotone: 'uaz-duotone-' + (++numeroPage), duotone: false };   // le filtre duotone de cette page
		const racineV = el('div', 'uaz-visuel' + (ctx.apercu ? ' uaz-visuel--apercu' : ''));
		racineV.style.width = px(fmt.largeur + 2 * fp);
		racineV.style.height = px(fmt.hauteur + 2 * fp);
		racineV.style.gap = px(fmt.filet);
		racineV.dataset.format = format;
		if (fp) { racineV.dataset.fondPerdu = String(fp); }

		/* Une rangee est montree si sa condition tient et qu'il lui reste une cellule
		   (pour une rangee repetee : un element dans sa liste). */
		const elementsDe = (r) => (Array.isArray(ctx.val(r.repeter)) ? ctx.val(r.repeter) : []).map(typo).filter((x) => x !== '');
		const rangees = rangeesDef.map((r) => {
			if (!ctx.condition(r.si, r.sauf) || (r.derniere && env.mode === 'premiere')) { return { r, cellules: [] }; }
			if (r.repeter) { return { r, cellules: elementsDe(r).length ? r.cellules : [] }; }
			return { r, cellules: r.cellules.filter((c) => ctx.condition(c.si, c.sauf)) };
		});
		const montrees = rangees.filter((x) => x.cellules.length);
		const libere = rangees.filter((x) => !x.cellules.length).reduce((s, x) => s + (x.r.hauteur === 'reste' ? 0 : x.r.hauteur) + fmt.filet, 0);
		const receveuse = montrees.find((x) => x.r.recoit);

		montrees.forEach(({ r, cellules }, ir) => {
			const bords = { haut: ir === 0, bas: ir === montrees.length - 1 };
			const ligne = el('div', 'uaz-rangee');
			ligne.style.gap = px(fmt.filet);
			if (r.id) { ligne.dataset.rangee = r.id; }
			if (r.cle) { ligne.dataset.cle = '1'; }
			/* le fond perdu s'ajoute a la premiere et a la derniere rangee (une rangee « reste » le prend d'elle-meme) */
			if (r.hauteur === 'reste') {
				ligne.style.flex = '1 1 0';
			} else {
				ligne.style.height = px(r.hauteur + (receveuse && receveuse.r === r ? libere : 0) + (bords.haut ? fp : 0) + (bords.bas ? fp : 0));
			}
			if (r.repeter) {
				repeter(ligne, r, elementsDe(r), fmt, env, bords);
			} else {
				const aReste = cellules.some((c) => c.largeur === 'reste');
				cellules.forEach((c, i) => {
					const reste = c.largeur === 'reste' || (!aReste && i === cellules.length - 1);
					ligne.appendChild(cellule(c, Object.assign({ premiere: i === 0, derniere: i === cellules.length - 1, reste }, bords), env));
				});
			}
			racineV.appendChild(ligne);
		});
		if (env.page.duotone) { racineV.appendChild(filtreDuotone(env.page.idDuotone)); }
		return racineV;
	}

	/* Une cellule : son fond, sa largeur, ses marges (40 px au bord du visuel pour
	   une cellule de texte, plus le fond perdu) et ses blocs. */
	function cellule(c, pos, env0) {
		const fp = env0.fp || 0;
		const cel = el('div', 'uaz-cellule');
		cel.style.background = jeton(c.fond);
		cel.style.color = jeton(Schema.COULEUR_TEXTE[c.fond]);
		cel.style.flex = pos.reste ? '1 1 0' : '0 0 ' + px(c.largeur + (pos.premiere ? fp : 0) + (pos.derniere ? fp : 0));
		const m = Schema.margesDe(c.marges).slice();
		const texte = blocsA_plat(c.blocs, []).some((b) => TEXTUELS.indexOf(b.type) !== -1);
		if (texte && pos.premiere) { m[3] = Math.max(m[3], Schema.MARGE_BORD); }
		if (texte && pos.derniere) { m[1] = Math.max(m[1], Schema.MARGE_BORD); }
		if (fp) {
			if (pos.haut) { m[0] += fp; }
			if (pos.derniere) { m[1] += fp; }
			if (pos.bas) { m[2] += fp; }
			if (pos.premiere) { m[3] += fp; }
		}
		cel.style.padding = m.map(px).join(' ');
		disposer(cel, c);
		const env = Object.assign({}, env0, { fond: c.fond });
		let blocs = c.blocs || [];
		if (env.mode === 'premiere') {
			const i = blocs.findIndex((b) => b.type === 'flux');
			if (i !== -1) { blocs = blocs.slice(0, i + 1); }
		}
		blocs.forEach((b) => { const x = bloc(b, env); if (x) { cel.appendChild(x); } });
		return cel;
	}

	/* Une rangee repetee (la grille des filieres) : une cellule par element, en
	   lignes equilibrees de « colonnes » au plus (5 -> 3 + 2, 4 -> 2 + 2), sans case
	   vide ou le marine apparaitrait en bloc. */
	function repeter(ligne, r, elements, fmt, env, bords) {
		const fp = env.fp || 0;
		const colonnes = r.colonnes || 3;
		const nLignes = Math.ceil(elements.length / colonnes);
		const base = Math.floor(elements.length / nLignes), extra = elements.length % nLignes;
		ligne.style.flexDirection = 'column';
		let k = 0;
		for (let l = 0; l < nLignes; l++) {
			const n = base + (l < extra ? 1 : 0);
			/* une grille et non des cellules souples : des colonnes egales, quelles que
			   soient les marges (celles du bord sont portees a 40 px) ; en fond perdu,
			   les colonnes du bord ont 3 mm de plus */
			const sous = el('div', 'uaz-rangee');
			sous.style.display = 'grid';
			if (fp && n > 1) {
				const col = (plus) => 'calc((100% - ' + ((n - 1) * fmt.filet + 2 * fp) + 'px) / ' + n + (plus ? ' + ' + plus + 'px' : '') + ')';
				sous.style.gridTemplateColumns = [col(fp)].concat(new Array(n - 2).fill(col(0)), [col(fp)]).join(' ');
			} else {
				sous.style.gridTemplateColumns = 'repeat(' + n + ', minmax(0, 1fr))';
			}
			sous.style.gap = px(fmt.filet);
			sous.style.flex = '1 1 0';
			for (let i = 0; i < n; i++, k++) {
				const ctx = Object.assign(Object.create(env.ctx), { element: { texte: elements[k], numero: String(k + 1).padStart(2, '0') } });
				const pos = { premiere: i === 0, derniere: i === n - 1, reste: true, haut: bords.haut && l === 0, bas: bords.bas && l === nLignes - 1 };
				const cel = cellule(r.cellules[0], pos, Object.assign({}, env, { ctx }));
				cel.dataset.champ = r.repeter;
				sous.appendChild(cel);
			}
			ligne.appendChild(sous);
		}
	}

	/* ------------------------------------------------------ l'ajustement */
	function deborde(e) {
		return e.scrollHeight > e.clientHeight + 1 || e.scrollWidth > e.clientWidth + 1;
	}
	function reduire(e) {
		const t = +e.dataset.t - PAS;
		if (t < +e.dataset.min) { return false; }
		e.style.setProperty('--t', px(t));
		e.dataset.t = String(t);
		return true;
	}
	function ajuster(racineV, rapport) {
		racineV.querySelectorAll('.uaz-cellule').forEach((cel) => {
			/* 1. une ligne qui ne tient pas dans sa largeur : elle seule */
			cel.querySelectorAll('.uaz-une-ligne[data-min]').forEach((t) => {
				let garde = 200;
				while (garde-- > 0 && t.scrollWidth > t.clientWidth + 1 && reduire(t)) { /* encore 2 px */ }
			});
			/* 2. la cellule qui deborde : le bloc qui a le plus de marge d'abord */
			let garde = 400;
			while (garde-- > 0 && deborde(cel)) {
				const elastiques = Array.from(cel.querySelectorAll('[data-min]')).filter((x) => +x.dataset.t - PAS >= +x.dataset.min);
				if (!elastiques.length) { break; }
				elastiques.sort((a, b) => (+b.dataset.t - +b.dataset.min) - (+a.dataset.t - +a.dataset.min));
				reduire(elastiques[0]);
			}
			const tropLongue = (selecteur) => Array.from(cel.querySelectorAll(selecteur)).some((t) => t.scrollWidth > t.clientWidth + 1);
			let trop = deborde(cel) || tropLongue('.uaz-une-ligne');
			/* Les textes a remplir de l'apercu (« [Date limite] ») ne font pas deborder une case :
			   sans eux, tient-elle ? (L'export n'en a jamais.) */
			const aRemplir = Array.from(cel.querySelectorAll('.uaz-a-remplir'));
			if (trop && aRemplir.length) {
				aRemplir.forEach((x) => { x.style.display = 'none'; });
				trop = deborde(cel) || tropLongue('.uaz-une-ligne:not(.uaz-a-remplir)');
				aRemplir.forEach((x) => { x.style.display = ''; });
			}
			if (trop) {
				const champs = Array.from(new Set(Array.from(cel.querySelectorAll('[data-champ]:not(.uaz-a-remplir)')).map((x) => x.dataset.champ)));
				rapport.debordements.push({ champs });
				cel.dataset.deborde = '1';
				if (racineV.classList.contains('uaz-visuel--apercu')) { cel.classList.add('uaz-deborde'); }
			}
		});
	}

	/* La premiere page d'un texte long : le plus de mots possible tant que sa case
	   tient (recherche par moitiés sur le texte rendu) ; le reste va page suivante. */
	function couper(page) {
		const f = page.querySelector('[data-flux]');
		if (!f) { return null; }
		const cel = f.closest('.uaz-cellule');
		let bas = f._debut, haut = f._total;
		while (bas < haut) {
			const milieu = Math.ceil((bas + haut) / 2);
			f._remplir(milieu);
			if (deborde(cel)) { haut = milieu - 1; } else { bas = milieu; }
		}
		/* Coupe au milieu d'un paragraphe : jamais une ligne seule de part et d'autre
		   (veuve, orpheline) ; a peu pres deux lignes, soit MOTS_MIN mots, de chaque cote. */
		const m = f._mots;
		if (bas > f._debut && bas < f._total && m[bas - 1].p === m[bas].p) {
			let debutP = bas, finP = bas;
			while (debutP > 0 && m[debutP - 1].p === m[bas].p) { debutP--; }
			while (finP < f._total && m[finP].p === m[bas].p) { finP++; }
			if (finP - bas < MOTS_MIN) { bas = Math.max(debutP, bas - (MOTS_MIN - (finP - bas))); }
			if (bas - debutP < MOTS_MIN) { bas = debutP; }
			bas = Math.max(bas, f._debut);
		}
		f._remplir(bas);
		if (bas === f._debut) { f.style.display = 'none'; }
		return { id: f.dataset.flux, fin: bas, total: f._total };
	}

	/* Story : une information cle (le texte des rangees « cle ») sous l'interface
	   d'Instagram, en haut ou en bas ? Mesure sans la mise a l'echelle de l'apercu. */
	function yDans(e, racineV) {
		let y = 0;
		for (let x = e; x && x !== racineV; x = x.offsetParent) { y += x.offsetTop; }
		return y;
	}
	function zones(racineV, format, rapport) {
		const z = Schema.ZONES[format];
		if (!z) { return; }
		const h = racineV.offsetHeight;
		const hors = Array.from(racineV.querySelectorAll('[data-cle] .uaz-texte, [data-cle] .uaz-qr')).filter((e) => {
			if (!e.offsetHeight) { return false; }
			const y = yDans(e, racineV);
			return y < z[0] || y + e.offsetHeight > h - z[1];
		});
		if (hors.length) {
			rapport.avertissements.push({ champ: hors[0].dataset.champ || '', message: 'Story : une information clé tombe dans les ' + z[0] + ' px du haut ou du bas, sous l\'interface d\'Instagram.' });
		}
	}

	/* Les elements que l'utilisateur a deplaces ou ajoutes : seuls controles ici (la grille de la charte est sure). */
	const MARGE_MIN = 40, TAILLE_TEXTE_MIN = 20;
	function garderFous(page, format, rapport, fp) {
		const rr = page.getBoundingClientRect();
		const k = page.offsetWidth ? rr.width / page.offsetWidth : 1;
		const L = page.offsetWidth - 2 * (fp || 0), H = page.offsetHeight - 2 * (fp || 0);
		const z = Schema.ZONES[format];
		const vus = new Set();
		const dire = (e, cle, message) => {
			const champ = e.dataset.libre || e.dataset.champ || e.dataset.image || '';
			const id = champ + '|' + cle;
			e.setAttribute('data-alerte', ((e.getAttribute('data-alerte') || '') + ' ' + cle).trim());
			if (!vus.has(id) && rapport.avertissements.length < 24) { vus.add(id); rapport.avertissements.push({ champ, message }); }
		};
		page.querySelectorAll('[data-deplace], .uaz-libre').forEach((e) => {
			const r = e.getBoundingClientRect();
			if (!r.width || !r.height) { return; }
			const libre = e.classList.contains('uaz-libre');
			const forme = e.classList.contains('uaz-libre--forme');
			const texte = e.classList.contains('uaz-libre--texte') || (!libre && !e.matches('[data-image]'));
			const x = (r.left - rr.left) / k - (fp || 0), y = (r.top - rr.top) / k - (fp || 0), l = r.width / k, h = r.height / k;
			if (x + l <= 0 || y + h <= 0 || x >= L || y >= H) { dire(e, 'hors', 'est hors du visuel : il n\'apparaîtra pas.'); return; }
			if (forme) { return; }   // un aplat peut aller jusqu'au bord
			if (x < MARGE_MIN - 0.5 || y < MARGE_MIN - 0.5 || x + l > L - MARGE_MIN + 0.5 || y + h > H - MARGE_MIN + 0.5) { dire(e, 'marge', 'dépasse la marge de ' + MARGE_MIN + ' px du bord.'); }
			if (z && (y < z[0] || y + h > H - z[1]) && texte) { dire(e, 'story', 'tombe dans les ' + z[0] + ' px du haut ou du bas, sous l\'interface d\'Instagram.'); }
			if (texte) {
				const taille = parseFloat(getComputedStyle(e).fontSize) * (1080 / L);
				if (taille < TAILLE_TEXTE_MIN - 0.01) { dire(e, 'petit', 'est trop petit pour se lire (' + Math.round(taille) + ' px ; ' + TAILLE_TEXTE_MIN + ' px au moins).'); }
			}
		});
	}

	/* ------------------------------------------------------- l'entree */
	function manquants(preset, ctx) {
		const sortie = [];
		(preset.champs || []).forEach((c) => {
			if (c.type === 'liste') {
				const n = (Array.isArray(ctx.val(c.id)) ? ctx.val(c.id) : []).filter((x) => String(x || '').trim() !== '').length;
				if (n < (c.min || 0)) { sortie.push(c.id); }
				return;
			}
			if (c.requis && !ctx.rempli(c.id)) { sortie.push(c.id); }
		});
		(preset.images || []).forEach((im) => { if (im.requis && !ctx.rempli(im.id)) { sortie.push(im.id); } });
		return sortie;
	}

	async function polices() {
		if (!document.fonts) { return; }
		/* les instances fixes de la charte (charte.css), toutes : la mesure de l'ajustement en depend */
		await Promise.all(Array.from(document.fonts).filter((f) => f.family.replace(/["']/g, '') === 'Archivo').map((f) => f.load().catch(() => null)));
		await document.fonts.ready;
	}

	async function decoder(page, rapport) {
		await Promise.all(Array.from(page.querySelectorAll('img')).map((img) => (img.decode ? img.decode() : Promise.resolve()).catch(() => {
			rapport.avertissements.push({ champ: img.closest('[data-image]') ? img.closest('[data-image]').dataset.image : '', message: "Une image n'a pas pu être lue." });
		})));
	}

	/* --- mise en page libre : un element decale et redimensionne par-dessus la grille ---
	   dx, dy : decalage ; l, h : largeur et hauteur imposees ; k : echelle du texte. */
	const BORNES_MEP = { dx: [-3000, 3000], dy: [-3000, 3000], l: [20, 3000], h: [20, 3000], k: [0.3, 4] };
	function miseEnPageValide(a) {
		const sortie = {};
		Object.keys(BORNES_MEP).forEach((c) => {
			const v = Number(a && a[c]);
			if (isFinite(v) && a[c] !== null && a[c] !== '') { sortie[c] = Math.max(BORNES_MEP[c][0], Math.min(BORNES_MEP[c][1], v)); }
		});
		return sortie;
	}
	function poser(e, a, racineV) {
		a = miseEnPageValide(a);
		e.setAttribute('data-deplace', '1');
		e.style.position = 'relative';
		e.style.zIndex = '6';
		e.style.left = (a.dx || 0) + 'px';
		e.style.top = (a.dy || 0) + 'px';
		if (a.l) { e.style.width = a.l + 'px'; e.style.maxWidth = 'none'; e.style.alignSelf = 'flex-start'; e.style.flex = '0 0 auto'; }
		if (a.h) { e.style.height = a.h + 'px'; e.style.maxHeight = 'none'; e.style.flex = '0 0 auto'; }
		if (a.k && Math.abs(a.k - 1) > 0.001) {
			if (!e.dataset.tailleBase) { e.dataset.tailleBase = String(parseFloat(getComputedStyle(e).fontSize)); }
			e.style.fontSize = (parseFloat(e.dataset.tailleBase) * a.k) + 'px';
			e.querySelectorAll('[style*="font-size"]').forEach((x) => {
				if (!x.dataset.tailleBase) { x.dataset.tailleBase = String(parseFloat(getComputedStyle(x).fontSize)); }
				x.style.fontSize = (parseFloat(x.dataset.tailleBase) * a.k) + 'px';
			});
		}
		/* les cellules ne le rognent plus ; le bord du visuel, si */
		for (let p = e.parentElement; p && p !== racineV; p = p.parentElement) { p.style.overflow = 'visible'; }
	}
	function appliquerMiseEnPage(page, miseEnPage) {
		Object.keys(miseEnPage || {}).forEach((id) => {
			const e = page.querySelector('[data-champ="' + id + '"], [data-image="' + id + '"]');
			if (e) { poser(e, miseEnPage[id], page); }
		});
	}

	/* --- blocs ajoutes : texte libre, image, forme --- */
	const COULEURS_BLOC = ['navy', 'gold', 'goldDeep', 'goldLight', 'bronze', 'cream', 'paper', 'sand', 'ink', 'rule', 'navySoft', 'white'];
	const GRAISSES_BLOC = [400, 500, 600, 700, 800];
	const nb = (v, min, max, defaut) => { const n = Number(v); return isFinite(n) && v !== null && v !== '' ? Math.max(min, Math.min(max, n)) : defaut; };
	/* La position d'un bloc pour ce format : la sienne, ou celle d'un autre format mise a l'echelle de la largeur */
	function geoBloc(b, format, fmt, formats) {
		const g = b.geo && b.geo[format];
		let x, y, l, h;
		if (g) { ({ x, y, l, h } = g); } else {
			const ref = Object.keys(b.geo || {}).find((k) => formats[k]);
			if (ref) {
				const k = fmt.largeur / formats[ref].largeur, r = b.geo[ref];
				x = r.x * k; y = r.y * k; l = r.l * k; h = r.h * k;
			} else { l = fmt.largeur * 0.4; h = fmt.largeur * 0.2; x = (fmt.largeur - l) / 2; y = (fmt.hauteur - h) / 2; }
		}
		return { x: nb(x, -2000, 6000, 0), y: nb(y, -2000, 6000, 0), l: nb(l, 4, 6000, 200), h: nb(h, 2, 6000, 100) };
	}
	function ajouterBlocs(page, blocs, format, fmt, preset, images, fp, apercu) {
		if (!Array.isArray(blocs) || !blocs.length) { return; }
		const couche = el('div', 'uaz-libres');
		blocs.forEach((b, i) => {
			if (!b || !/^b[0-9]{1,4}$/.test(b.id || '')) { return; }
			const g = geoBloc(b, format, fmt, preset.formats);
			const e = el('div', 'uaz-libre uaz-libre--' + b.type);
			e.dataset.libre = b.id;
			e.style.left = (g.x + (fp || 0)) + 'px';
			e.style.top = (g.y + (fp || 0)) + 'px';
			e.style.width = g.l + 'px';
			e.style.zIndex = String(i + 1);
			if (b.type === 'texte') {
				e.style.fontSize = nb(b.taille, 8, 600, 48) + 'px';
				e.style.fontWeight = String(GRAISSES_BLOC.includes(Number(b.graisse)) ? Number(b.graisse) : 600);
				e.style.color = jeton(COULEURS_BLOC.includes(b.couleur) ? b.couleur : 'navy');
				e.style.textAlign = ['left', 'center', 'right'].includes(b.align) ? b.align : 'left';
				if (b.maj) { e.style.textTransform = 'uppercase'; }
				e.textContent = typo(String(b.texte || '').slice(0, 600));
				if (!String(b.texte || '').trim()) {   // vide : « Votre texte » a l'ecran seulement, rien a l'export
					if (!apercu) { return; }
					e.classList.add('uaz-a-remplir');
					e.textContent = 'Votre texte';
				}
			} else if (b.type === 'forme') {
				e.style.height = g.h + 'px';
				e.style.background = jeton(COULEURS_BLOC.includes(b.couleur) ? b.couleur : 'gold');
				e.style.opacity = String(nb(b.opacite, 0.05, 1, 1));
				e.style.borderRadius = b.forme === 'cercle' ? '50%' : nb(b.rayon, 0, 600, 0) + 'px';
			} else if (b.type === 'image') {
				e.style.height = g.h + 'px';
				const im = images && images['libre' + b.id];
				if (im && im.src) {
					const img = el('img');
					img.alt = String(im.alt || '');
					img.decoding = 'sync';
					const c = im.cadrage || { x: 0.5, y: 0.5, zoom: 1 };
					img.style.objectFit = im.ajustement === 'remplir' ? 'cover' : 'contain';
					img.style.objectPosition = (c.x * 100) + '% ' + (c.y * 100) + '%';
					img.style.transformOrigin = (c.x * 100) + '% ' + (c.y * 100) + '%';
					if (c.zoom > 1) { img.style.transform = 'scale(' + c.zoom + ')'; }
					img.src = im.src;
					e.appendChild(img);
				} else if (apercu) { e.classList.add('uaz-libre--vide'); } else { return; }
			} else { return; }
			couche.appendChild(e);
		});
		page.appendChild(couche);
	}

	/**
	 * Rend le visuel dans « cible ».

	 * o : { preset, format, donnees, base, apercu, fondPerdu (px), miseEnPage: { id: { dx, dy, l, h, k } }, blocs: [...],
	 *       images: { id: { src, alt, fondre, ajustement, cadrage: { x, y, zoom }, reglages: { lumiere, contraste, saturation, mode } } } }
	 * Renvoie { racine, pages, manquants, debordements, avertissements, images } : « racine » est
	 * le visuel, ou un .uaz-document qui contient ses deux pages ; « images », pour chaque
	 * emplacement : le cadre, la taille de l'image, son echelle et la partie visible.
	 */
	async function rendre(cible, o) {
		const preset = o.preset;
		const fmt = preset.formats && preset.formats[o.format];
		if (!fmt) { throw new Error('Le format « ' + o.format + ' » n\'existe pas pour ce preset.'); }
		const ctx = contexte(preset, o.donnees, o.images, o.apercu);
		const rapport = { manquants: manquants(preset, ctx), debordements: [], avertissements: [], images: {} };
		const env = { ctx, base: o.base || '', rapport, fp: o.fondPerdu || 0, mode: 'seule', tranches: {} };
		const seule = construire(fmt, fmt.rangees, o.format, env);
		cible.replaceChildren(seule);
		await polices();
		await decoder(seule, rapport);
		ajuster(seule, rapport);
		appliquerMiseEnPage(seule, o.miseEnPage);
		ajouterBlocs(seule, o.blocs, o.format, fmt, preset, o.images, o.fondPerdu, o.apercu);
		cadrer(seule, o.format, ctx, rapport);
		let pages = [seule];
		rapport.racine = seule;

		/* Le texte long ne tient pas : deux pages, l'en-tete et le pied repetes. */
		if (fmt.pageSuivante && seule.querySelector('.uaz-cellule[data-deborde] [data-flux]')) {
			rapport.debordements = [];
			rapport.avertissements = [];
			rapport.images = {};
			const doc = el('div', 'uaz-document');
			ctx.pagination = 'Page 1 sur 2';
			const p1 = construire(fmt, fmt.rangees, o.format, Object.assign({}, env, { mode: 'premiere' }));
			doc.appendChild(p1);
			cible.replaceChildren(doc);
			await decoder(p1, rapport);
			const coupe = couper(p1);
			ajuster(p1, rapport);
			appliquerMiseEnPage(p1, o.miseEnPage);
			ajouterBlocs(p1, o.blocs, o.format, fmt, preset, o.images, o.fondPerdu, o.apercu);
			cadrer(p1, o.format, ctx, rapport);
			ctx.pagination = 'Page 2 sur 2';
			const tranches = coupe ? { [coupe.id]: [coupe.fin, coupe.total] } : {};
			const p2 = construire(fmt, fmt.pageSuivante.rangees, o.format, Object.assign({}, env, { mode: 'suivante', tranches }));
			doc.appendChild(p2);
			await decoder(p2, rapport);
			ajuster(p2, rapport);
			appliquerMiseEnPage(p2, o.miseEnPage);
			cadrer(p2, o.format, ctx, rapport);
			pages = [p1, p2];
			rapport.racine = doc;
		}
		pages.forEach((p) => zones(p, o.format, rapport));
		pages.forEach((p) => garderFous(p, o.format, rapport, o.fondPerdu));
		rapport.pages = pages.length;
		return rapport;
	}

	return { rendre, etiquetteModifiable, poser, miseEnPageValide, BORNES_MEP, COULEURS_BLOC, GRAISSES_BLOC, typo, sansVeuve, lireDate, lireHeure, derive, motsDe, PAS, REGLAGES };
});
