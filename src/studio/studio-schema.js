/* =============================================================================
   Studio UAZ — validation d'un preset

   Deux contrôles, avec des messages en clair :
   1. la forme : le schéma JSON de src/studio/presets/preset.schema.json (sous-
      ensemble utilisé : $ref, anyOf, const, enum, type, required, properties,
      additionalProperties, minProperties, items, minItems, maxItems, minLength,
      maxLength, pattern, minimum, maximum) ;
   2. la charte (docs/studio-uaz/, Charte.dc.html) : couleurs par paires
      autorisées, une seule cellule pleine par rangée, 40 px au bord du visuel,
      pas de texte sous 12 px, l'or du titre seulement en grand, l'en-tête et
      le pied communs, les champs et emplacements cités qui existent.

   Le même fichier sert à l'éditeur (src/js/studio.js), au service de rendu
   (src/studio/rendu.html) et aux tests (tests/studio/). Un preset invalide
   n'est jamais rendu.
   ========================================================================== */
(function (racine, fabrique) {
	if (typeof module === 'object' && module.exports) { module.exports = fabrique(); } else { racine.StudioSchema = fabrique(); }
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	/* --------------------------------------------------------- le schéma */
	function typeDe(v) {
		if (v === null) { return 'null'; }
		if (Array.isArray(v)) { return 'array'; }
		if (typeof v === 'number') { return Number.isInteger(v) ? 'integer' : 'number'; }
		return typeof v;
	}
	function accepteType(v, attendu) {
		const t = typeDe(v);
		return t === attendu || (attendu === 'number' && t === 'integer');
	}
	function resoudre(ref, racineSchema) {
		if (ref.indexOf('#/$defs/') !== 0) { throw new Error('Référence de schéma non prise en charge : ' + ref); }
		const def = (racineSchema.$defs || {})[ref.slice(8)];
		if (!def) { throw new Error('Définition absente du schéma : ' + ref); }
		return def;
	}
	function montrer(v) { return typeof v === 'string' ? '« ' + v + ' »' : JSON.stringify(v); }

	function verifierSchema(v, s, chemin, racineSchema, erreurs) {
		if (s.$ref) { s = resoudre(s.$ref, racineSchema); }
		if (s.anyOf) {
			const ok = s.anyOf.some((sous) => { const e = []; verifierSchema(v, sous, chemin, racineSchema, e); return e.length === 0; });
			if (!ok) { erreurs.push(chemin + ' : ' + montrer(v) + " n'a pas une forme acceptée."); }
			return;
		}
		if ('const' in s && v !== s.const) { erreurs.push(chemin + ' : ' + montrer(s.const) + ' attendu.'); return; }
		if (s.enum && s.enum.indexOf(v) === -1) {
			erreurs.push(chemin + ' : ' + montrer(v) + ' non permis (' + s.enum.join(', ') + ').');
			return;
		}
		if (s.type && !accepteType(v, s.type)) { erreurs.push(chemin + ' : ' + s.type + ' attendu, ' + typeDe(v) + ' trouvé.'); return; }
		const t = typeDe(v);
		if (t === 'string') {
			if (s.minLength != null && v.length < s.minLength) { erreurs.push(chemin + ' : au moins ' + s.minLength + ' caractères.'); }
			if (s.maxLength != null && v.length > s.maxLength) { erreurs.push(chemin + ' : au plus ' + s.maxLength + ' caractères.'); }
			if (s.pattern && !new RegExp(s.pattern).test(v)) { erreurs.push(chemin + ' : ' + montrer(v) + ' ne suit pas la forme attendue.'); }
		}
		if (t === 'number' || t === 'integer') {
			if (s.minimum != null && v < s.minimum) { erreurs.push(chemin + ' : ' + v + ' est sous le minimum (' + s.minimum + ').'); }
			if (s.maximum != null && v > s.maximum) { erreurs.push(chemin + ' : ' + v + ' dépasse le maximum (' + s.maximum + ').'); }
		}
		if (t === 'array') {
			if (s.minItems != null && v.length < s.minItems) { erreurs.push(chemin + ' : au moins ' + s.minItems + ' élément(s).'); }
			if (s.maxItems != null && v.length > s.maxItems) { erreurs.push(chemin + ' : au plus ' + s.maxItems + ' élément(s).'); }
			if (s.items) { v.forEach((x, i) => verifierSchema(x, s.items, chemin + '[' + i + ']', racineSchema, erreurs)); }
		}
		if (t === 'object') {
			const cles = Object.keys(v);
			if (s.minProperties != null && cles.length < s.minProperties) { erreurs.push(chemin + ' : au moins ' + s.minProperties + ' entrée(s).'); }
			(s.required || []).forEach((k) => { if (!(k in v)) { erreurs.push(chemin + ' : « ' + k + ' » manque.'); } });
			const props = s.properties || {};
			cles.forEach((k) => {
				if (props[k]) { verifierSchema(v[k], props[k], chemin + '.' + k, racineSchema, erreurs); }
				else if (s.additionalProperties === false) { erreurs.push(chemin + ' : « ' + k + ' » est inconnu.'); }
				else if (s.additionalProperties && typeof s.additionalProperties === 'object') { verifierSchema(v[k], s.additionalProperties, chemin + '.' + k, racineSchema, erreurs); }
			});
		}
	}

	/* ---------------------------------------------------------- la charte */
	/* Dimensions des formats (A4 : 210 x 297 mm a 96 px par pouce) et filets */
	const FORMATS = { '4:5': [1080, 1350, 4], '1:1': [1080, 1080, 4], '9:16': [1080, 1920, 4], 'A4': [794, 1123, 3] };
	const PLEINES = ['navy', 'goldDeep'];
	/* Couleurs de texte permises sur chaque fond (contraste 4,5:1 ; l'or 4,00:1 : grand texte seulement) */
	const PAIRES = {
		cream: ['navy', 'ink', 'bronze', 'navySoft'], paper: ['navy', 'ink', 'bronze', 'navySoft'], sand: ['navy', 'ink', 'bronze', 'navySoft'],
		navy: ['white', 'goldLight'], goldDeep: ['white'], goldLight: ['navy'],
	};
	const COULEUR_TEXTE = { cream: 'navy', paper: 'navy', sand: 'navy', navy: 'white', goldDeep: 'white', goldLight: 'navy' };
	const COULEUR_LABEL = { cream: 'bronze', paper: 'bronze', sand: 'bronze', navy: 'goldLight', goldDeep: 'white', goldLight: 'navy' };
	const TEXTUELS = ['texte', 'label', 'titre', 'liste', 'badge', 'flux'];
	const MARGE_BORD = 40;
	const TAILLE_MIN = 12;          // 12 px sur 1080 ; en A4 (96 px par pouce), 12 px = 9 pt
	const TAILLE_MIN_OR = 24;
	const TAILLE_MIN_QR = 120;      // 3 mm par module au moins en A4 pour un QR de 41 modules
	const MARQUE = 'zurcher.edu.mg';
	/* Story : bandes couvertes par l'interface d'Instagram (haut, bas) */
	const ZONES = { '9:16': [250, 250] };
	const DERIVES = { date: ['jour', 'mois', 'jourSeul', 'moisSeul', 'annee', 'numerique', 'long', 'jourMois'], heure: ['valeur', 'unite'] };
	const SPECIAUX = ['code', 'annee', 'pagination'];
	const SPECIAUX_REPETES = ['element', 'numero'];

	function margesDe(m) {
		if (m == null) { return [0, 0, 0, 0]; }
		return Array.isArray(m) ? m : [m, m, m, m];
	}
	/* Les variables d'un gabarit : {champ}, {champ:derive}, {a|b} (le premier rempli) ;
	   {code} et {annee} sont fournis par le moteur, {element} et {numero} dans une rangee repetee. */
	function variables(gabarit) {
		const sortie = [];
		String(gabarit || '').replace(/\{([a-zA-Z0-9|]+)(?::([a-zA-Z]+))?\}/g, (m, ids, derive) => {
			ids.split('|').forEach((id) => sortie.push({ id, derive: derive || '', alternative: ids.indexOf('|') !== -1 }));
			return m;
		});
		return sortie;
	}
	function blocsA_plat(blocs, sortie) {
		(blocs || []).forEach((b) => { sortie.push(b); if (b.type === 'groupe') { blocsA_plat(b.blocs, sortie); } });
		return sortie;
	}

	function verifierCharte(p) {
		const erreurs = [];
		const champs = {};
		(p.champs || []).forEach((c, i) => {
			if (champs[c.id]) { erreurs.push('champs[' + i + '] : l\'identifiant « ' + c.id + ' » est déjà pris.'); }
			champs[c.id] = c;
		});
		const images = {};
		(p.images || []).forEach((im) => { images[im.id] = im; });
		const nom = (id) => '« ' + id + ' »';

		(p.champs || []).forEach((c) => {
			if (c.type === 'motCle' && (!champs[c.de] || champs[c.de].type !== 'titre')) { erreurs.push('champ ' + nom(c.id) + ' : « de » doit nommer un champ titre.'); }
			if (c.type === 'liste' && (c.max == null || c.maxElement == null)) { erreurs.push('champ ' + nom(c.id) + ' : une liste a besoin de « max » (éléments) et de « maxElement » (caractères).'); }
			if (['texte', 'texteLong', 'titre', 'motCle', 'url'].indexOf(c.type) !== -1 && c.max == null) { erreurs.push('champ ' + nom(c.id) + ' : « max » (longueur) manque.'); }
			if (c.type === 'sousCode' && !(p.sousCodes || []).length) { erreurs.push('champ ' + nom(c.id) + ' : aucun sous-code n\'est défini.'); }
			if (c.paragraphes && c.type !== 'texteLong') { erreurs.push('champ ' + nom(c.id) + ' : « paragraphes » ne sert qu\'à un texteLong.'); }
			if (c.type !== 'texteLong' && c.max > 400) { erreurs.push('champ ' + nom(c.id) + ' : 400 caractères au plus (seul un texte long va au-delà).'); }
			(c.formats || []).forEach((f) => { if (!(p.formats || {})[f]) { erreurs.push('champ ' + nom(c.id) + ' : le format « ' + f + ' » n\'existe pas dans ce preset.'); } });
		});
		[['anneeDepuis', 'date'], ['dateDepuis', 'date']].forEach(([cle, type]) => {
			if (p[cle] && (!champs[p[cle]] || champs[p[cle]].type !== type)) { erreurs.push(cle + ' : ' + nom(p[cle]) + ' n\'est pas un champ de type ' + type + '.'); }
		});
		/* titreDepuis : un champ, ou un gabarit « {prenom} {nom} » */
		if (p.titreDepuis && p.titreDepuis.indexOf('{') === -1 && !champs[p.titreDepuis]) { erreurs.push('titreDepuis : ' + nom(p.titreDepuis) + ' n\'est pas un champ.'); }

		const verifierGabarit = (g, ou, repete) => {
			variables(g).forEach(({ id, derive }) => {
				if (SPECIAUX.indexOf(id) !== -1) { if (derive) { erreurs.push(ou + ' : {' + id + '} ne prend pas de « :' + derive + ' ».'); } return; }
				if (SPECIAUX_REPETES.indexOf(id) !== -1) { if (!repete) { erreurs.push(ou + ' : {' + id + '} ne sert que dans une rangée répétée.'); } return; }
				const c = champs[id];
				if (!c) { erreurs.push(ou + ' : le champ ' + nom(id) + ' n\'existe pas.'); return; }
				if (c.type === 'liste') { erreurs.push(ou + ' : la liste ' + nom(id) + ' ne s\'écrit pas dans un texte (bloc « liste »).'); }
				if (derive && (DERIVES[c.type] || []).indexOf(derive) === -1) { erreurs.push(ou + ' : « ' + derive + ' » n\'existe pas pour ' + nom(id) + '.'); }
				if (!derive && DERIVES[c.type]) { erreurs.push(ou + ' : ' + nom(id) + ' s\'écrit {' + id + ':' + DERIVES[c.type][0] + '}.'); }
			});
		};
		if (p.titreDepuis && p.titreDepuis.indexOf('{') !== -1) { verifierGabarit(p.titreDepuis, 'titreDepuis', false); }
		const verifierSi = (o, ou) => {
			['si', 'sauf'].forEach((cle) => {
				if (o[cle] == null) { return; }
				String(o[cle]).split('|').forEach((id) => { if (!champs[id] && !images[id]) { erreurs.push(ou + ' : « ' + cle + ' » nomme ' + nom(id) + ', ni champ ni image.'); } });
			});
		};

		Object.keys(p.formats || {}).forEach((f) => {
			const fmt = p.formats[f];
			const ref = FORMATS[f];
			const lieu = 'formats.' + f;
			if (ref && (fmt.largeur !== ref[0] || fmt.hauteur !== ref[1])) { erreurs.push(lieu + ' : ' + ref[0] + ' x ' + ref[1] + ' attendu.'); }
			if (ref && fmt.filet !== ref[2]) { erreurs.push(lieu + ' : filet de ' + ref[2] + ' px attendu (signature de la marque).'); }
			/* Le texte long sur deux pages : un bloc « flux » sur la premiere, le meme sur la suivante */
			const flux = (rangees) => blocsA_plat([].concat(...(rangees || []).map((r) => [].concat(...(r.cellules || []).map((c) => c.blocs || [])))), []).filter((b) => b.type === 'flux');
			const fluxUn = flux(fmt.rangees);
			if (fluxUn.length > 1) { erreurs.push(lieu + ' : un seul bloc « flux » par page.'); }
			if (fmt.pageSuivante) {
				const fluxDeux = flux(fmt.pageSuivante.rangees);
				if (fluxUn.length !== 1 || fluxDeux.length !== 1 || fluxUn[0].valeur !== fluxDeux[0].valeur) {
					erreurs.push(lieu + '.pageSuivante : la première page et la suivante ont chacune un bloc « flux », sur le même champ.');
				}
				verifierRangees(fmt.pageSuivante.rangees || [], fmt, lieu + '.pageSuivante');
			} else if ((fmt.rangees || []).some((r) => r.derniere)) {
				erreurs.push(lieu + ' : « derniere » ne sert qu\'avec « pageSuivante ».');
			}
			verifierRangees(fmt.rangees || [], fmt, lieu);
		});

		function verifierRangees(rangees, fmt, lieu) {
			const restes = rangees.filter((r) => r.hauteur === 'reste').length;
			if (restes > 1) { erreurs.push(lieu + ' : une seule rangée « reste ».'); }
			if (rangees.filter((r) => r.recoit).length > 1) { erreurs.push(lieu + ' : une seule rangée « recoit ».'); }
			const fixes = rangees.reduce((s, r) => s + (r.hauteur === 'reste' ? 0 : r.hauteur), 0) + fmt.filet * (rangees.length - 1);
			if (fixes > fmt.hauteur - (restes ? 40 : 0)) { erreurs.push(lieu + ' : les rangées dépassent la hauteur (' + fixes + ' px pour ' + fmt.hauteur + ').'); }

			rangees.forEach((r, ir) => {
				const lr = lieu + '.rangees[' + ir + ']' + (r.id ? ' (' + r.id + ')' : '');
				verifierSi(r, lr);
				const cellules = r.cellules || [];
				/* Rangee repetee (la grille des filieres) : une cellule modele, claire, copiee par element ;
				   le moteur garde 40 px au bord du visuel a chaque ligne. */
				if (r.repeter) {
					if (!champs[r.repeter] || champs[r.repeter].type !== 'liste') { erreurs.push(lr + ' : « repeter » doit nommer un champ liste.'); }
					if (cellules.length !== 1) { erreurs.push(lr + ' : une rangée répétée a une seule cellule modèle.'); }
					if (cellules[0] && PLEINES.indexOf(cellules[0].fond) !== -1) { erreurs.push(lr + ' : la cellule répétée ne peut pas être pleine (une seule par rangée).'); }
				} else if (r.colonnes != null) {
					erreurs.push(lr + ' : « colonnes » ne sert qu\'avec « repeter ».');
				}
				if (cellules.length > 1 && cellules.filter((c) => PLEINES.indexOf(c.fond) !== -1).length > 1) {
					erreurs.push(lr + ' : une seule cellule pleine (marine ou or) par rangée.');
				}
				const larges = cellules.reduce((s, c) => s + (c.largeur === 'reste' ? 0 : c.largeur), 0) + fmt.filet * (cellules.length - 1);
				if (larges > fmt.largeur) { erreurs.push(lr + ' : les cellules dépassent la largeur (' + larges + ' px).'); }

				cellules.forEach((c, ic) => {
					const lc = lr + '.cellules[' + ic + ']';
					verifierSi(c, lc);
					const blocs = blocsA_plat(c.blocs, []);
					const texte = blocs.some((b) => TEXTUELS.indexOf(b.type) !== -1);
					const m = margesDe(c.marges);
					if (texte && !r.repeter && ic === 0 && m[3] < MARGE_BORD) { erreurs.push(lc + ' : marge gauche de ' + MARGE_BORD + ' px au moins au bord du visuel.'); }
					if (texte && !r.repeter && ic === cellules.length - 1 && m[1] < MARGE_BORD) { erreurs.push(lc + ' : marge droite de ' + MARGE_BORD + ' px au moins au bord du visuel.'); }
					blocs.forEach((b, ib) => {
						const lb = lc + '.blocs[' + ib + '] (' + b.type + ')';
						verifierSi(b, lb);
						['valeur', 'motCle', 'etiquette'].forEach((k) => { if (b[k] != null) { verifierGabarit(b[k], lb + '.' + k, !!r.repeter); } });
						/* des tailles de texte (la pastille, le logo et le QR ont leur propre taille) */
						(TEXTUELS.indexOf(b.type) !== -1 ? ['taille', 'min'] : []).concat(['tailleEtiquette', 'tailleLibelle', 'tailleNumero']).forEach((k) => {
							if (b[k] != null && b[k] < TAILLE_MIN) { erreurs.push(lb + ' : ' + k + ' ' + b[k] + ' px, jamais sous ' + TAILLE_MIN + ' px.'); }
						});
						if (b.min != null && b.taille != null && b.min > b.taille) { erreurs.push(lb + ' : « min » dépasse « taille ».'); }
						if (b.type === 'liste' && (!champs[b.champ] || champs[b.champ].type !== 'liste')) { erreurs.push(lb + ' : « champ » doit nommer un champ liste.'); }
						if (b.type === 'image' && !images[b.emplacement]) { erreurs.push(lb + ' : l\'emplacement ' + nom(b.emplacement) + ' n\'existe pas.'); }
						/* un QR code : une adresse (champ url), assez grand pour etre lu */
						if (b.type === 'qr') {
							const v = variables(b.valeur);
							if (v.length !== 1 || !champs[v[0].id] || champs[v[0].id].type !== 'url') { erreurs.push(lb + ' : « valeur » est une seule adresse, {champ} de type url.'); }
							if (!(b.taille >= TAILLE_MIN_QR)) { erreurs.push(lb + ' : un QR code fait ' + TAILLE_MIN_QR + ' px de côté au moins.'); }
						}
						/* un texte long : un seul champ texteLong a paragraphes */
						if (b.type === 'flux') {
							const v = variables(b.valeur);
							const c2 = v.length === 1 ? champs[v[0].id] : null;
							if (!c2 || c2.type !== 'texteLong' || !c2.paragraphes || String(b.valeur).trim() !== '{' + v[0].id + '}') {
								erreurs.push(lb + ' : « valeur » est un seul {champ}, texte long à « paragraphes ».');
							}
						}
						if (b.type === 'titre' && b.motCle) {
							const plancher = b.min != null ? b.min : b.taille;
							if (plancher < TAILLE_MIN_OR) { erreurs.push(lb + ' : l\'or sur crème (4,00:1) ne sert qu\'en grand : pas sous ' + TAILLE_MIN_OR + ' px.'); }
							if (PAIRES[c.fond].indexOf('navy') === -1 && !b.couleurMotCle) { erreurs.push(lb + ' : le mot-clé en or ne se lit que sur un fond clair.'); }
						}
						/* la couleur du texte sur son fond */
						if (TEXTUELS.indexOf(b.type) !== -1) {
							const fond = b.type === 'badge' && b.fond ? b.fond : c.fond;
							const coul = b.couleur || (b.type === 'label' || b.type === 'badge' ? COULEUR_LABEL : COULEUR_TEXTE)[fond];
							if (PAIRES[fond] && PAIRES[fond].indexOf(coul) === -1) { erreurs.push(lb + ' : ' + coul + ' sur ' + fond + ' n\'est pas une paire de la charte.'); }
						}
					});
				});
			});

			/* En-tête et pied identiques partout */
			const premiere = rangees[0];
			const blocsTete = premiere ? blocsA_plat([].concat(...premiere.cellules.map((c) => c.blocs)), []) : [];
			const gabaritsTete = blocsTete.map((b) => b.valeur || '').join(' ');
			if (!blocsTete.some((b) => b.type === 'logo') || gabaritsTete.indexOf('{code}') === -1 || gabaritsTete.indexOf('{annee}') === -1) {
				erreurs.push(lieu + ' : la première rangée est l\'en-tête commun (logo, {code}, {annee}).');
			}
			const derniere = rangees[rangees.length - 1];
			const blocsPied = derniere ? blocsA_plat([].concat(...derniere.cellules.map((c) => c.blocs)), []) : [];
			if (!derniere || derniere.cellules.length !== 1 || derniere.cellules[0].fond !== 'navy' || !blocsPied.some((b) => (b.valeur || '').indexOf(MARQUE) !== -1)) {
				erreurs.push(lieu + ' : la dernière rangée est le pied commun (une cellule marine avec « ' + MARQUE + ' »).');
			}
		}
		(p.images || []).forEach((im) => { if (images[im.id] !== im) { erreurs.push('images : « ' + im.id + ' » en double.'); } });
		return erreurs;
	}

	/** Le preset est-il utilisable ? { ok, erreurs } */
	function valider(preset, schema) {
		const erreurs = [];
		if (!preset || typeof preset !== 'object') { return { ok: false, erreurs: ['Le preset est illisible (objet JSON attendu).'] }; }
		verifierSchema(preset, schema, 'preset', schema, erreurs);
		if (!erreurs.length) { erreurs.push.apply(erreurs, verifierCharte(preset)); }
		return { ok: erreurs.length === 0, erreurs };
	}

	return { valider, FORMATS, ZONES, PAIRES, COULEUR_TEXTE, COULEUR_LABEL, DERIVES, MARGE_BORD, variables, margesDe };
});
