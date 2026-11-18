/* =============================================================================
   Studio UAZ — l'editeur (src/studio.php)

   Le formulaire nait du preset (src/studio/presets/<id>.json) : un champ par
   entree, dans l'ordre de lecture du visuel, avec sa longueur maximale et son
   compteur. L'apercu est rendu par src/studio/studio-rendu.js, le moteur
   qu'utilise aussi l'export : ce qui s'affiche est ce qui sort.

   - Un clic sur un texte de l'apercu ouvre son champ.
   - L'image est preparee ici : sens de la photo applique, 2 400 px au plus,
     JPEG de moins de 1,8 Mo (le serveur n'accepte pas plus de 2 Mo par
     fichier) ; l'apercu montre exactement l'image qui sera envoyee.
   - Etape 5 : les visuels s'enregistrent sur le serveur (brouillon automatique,
     duplication, historique, annuler/retablir, validation, legende, journal) ; si le
     serveur ne les accepte pas, les champs restent dans le navigateur (par compte
     et par preset), les images non.
   - L'export est refuse tant qu'un champ obligatoire manque, qu'un texte
     deborde ou qu'une image n'a pas de description.
   - Les sorties suivent le format : PNG, JPG et PDF ; en A4, le PDF et celui
     de l'imprimeur (fond perdu de 3 mm, traits de coupe). « Tout exporter »
     rend les quatre formats dans un ZIP.
   - Story : les bandes que couvre l'interface d'Instagram se montrent sur
     l'apercu (interrupteur « Zones d'Instagram »).
   - 26/10/2026 : l'interface de Figma. Un clic choisit un element (ses
     proprietes a droite), un double-clic ou Entree ecrit son texte sur le
     visuel ; calques a gauche ; barre d'outils en bas (V, H, R, O, L, T) dont
     les outils se tirent sur le visuel ; zoom (Ctrl+molette, Maj+1, Maj+0,
     Maj+2) et main (Espace) ; clic droit ; raccourcis de Figma.
   ========================================================================== */
(function () {
	'use strict';

	const app = document.getElementById('stApp');
	if (!app) { return; }
	const BASE = app.dataset.base;
	const EXPORT = app.dataset.export;
	const COMPTE = app.dataset.compte || '0';
	const PRESETS = JSON.parse(app.dataset.presets || '[]');
	const ADMIN = app.dataset.admin || '';       // l'administration (app/studio.admin.php), superadmin
	const VISUELS = app.dataset.visuels || '';   // les visuels enregistres (app/studio.visuels.php)
	const RETOUCHE = app.dataset.retouche || '';   // l'atelier de retouche (/retouche-photo), pour « Retoucher dans l'editeur »
	const FORMATS = [['4:5', 'Post 4:5'], ['1:1', 'Carré 1:1'], ['9:16', 'Story 9:16'], ['A4', 'Affiche A4']];
	const NOM_FORMAT = { '4:5': 'Post', '1:1': 'Carré', '9:16': 'Story', 'A4': 'A4' };
	/* Les sorties de chaque format : [sortie, echelle, impression, libelle, icone] */
	const SORTIES = {
		ecran: [['png', 1, false, 'PNG · 1080 px', 'bi-download'], ['png', 2, false, 'PNG ×2 · 2160 px', 'bi-arrows-angle-expand'],
			['jpg', 1, false, 'JPG pour Facebook', 'bi-facebook'], ['pdf', 1, false, 'PDF', 'bi-file-earmark-pdf']],
		A4: [['pdf', 1, false, 'PDF · A4', 'bi-file-earmark-pdf'], ['pdf', 1, true, 'PDF pour l\'imprimeur', 'bi-printer'],
			['png', 2, false, 'PNG · 1588 px', 'bi-download']],
	};
	const TYPES_IMAGE = ['image/jpeg', 'image/png', 'image/webp'];
	const IMAGE_MAX_OCTETS = 15 * 1024 * 1024;
	const ENVOI_MAX_OCTETS = 1.8 * 1024 * 1024;
	const IMAGE_MAX_COTE = 2400;

	const $ = (id) => document.getElementById(id);
	const etat = { schema: null, presets: {}, preset: null, format: '4:5', donnees: {}, images: {}, rapport: null, numero: 0, minuteur: 0, service: null, occupe: false,
		/* etape 5 : le visuel enregistre, ses droits, ce qui est deja sur le serveur, annuler/retablir */
		visuel: null, serveur: null, roles: null, obligatoire: [], aValider: 0, lecture: false, chargement: false,
		reglages: null, miseEnPage: {}, blocs: [], aSelectionner: null, sigCalques: '', fleches: null, minuterieFleches: 0, sigSauvee: '', sigVue: '', minuterieSauve: 0, enregistrement: null, encore: null, messageSauve: '', erreurSauve: false, sauveA: null,
		legende: '', legendeManuelle: false, pile: [], pose: 0,
		/* la vue (zoom : null = tout voir) et l'outil de la barre */
		zoom: null, panX: 0, panY: 0, echelle: 1, ajuste: 1, outil: 'deplacer', espace: false, avalerClic: false };

	function el(balise, classe, texte) {
		const e = document.createElement(balise);
		if (classe) { e.className = classe; }
		if (texte != null) { e.textContent = texte; }
		return e;
	}
	async function lireJson(url) {
		const r = await fetch(url, { cache: 'no-cache', credentials: 'same-origin' });
		if (!r.ok) { throw new Error(url + ' : ' + r.status); }
		return r.json();
	}
	/* Les messages : un avis au-dessus de la barre d'outils (comme Figma) ; il s'efface seul, une erreur plus tard */
	let minuterieAvis = 0;
	function annoncer(texte, genre) {
		const e = $('stEtat');
		e.textContent = texte || '';
		e.className = 'st-etat' + (genre ? ' st-etat--' + genre : '');
		const t = $('stToast');
		if (!t) { return; }
		t.dataset.genre = genre || '';
		t.classList.toggle('st-toast--visible', !!texte);
		clearTimeout(minuterieAvis);
		if (texte) { minuterieAvis = setTimeout(() => t.classList.remove('st-toast--visible'), genre === 'erreur' ? 15000 : (genre === 'ok' ? 5000 : 9000)); }
	}

	/* ------------------------------------------------- brouillon local */
	const cleBrouillon = () => 'studio-uaz.' + COMPTE + '.' + etat.preset.id;
	let minuteurSauvegarde = 0;
	function sauverBrouillon() {
		if (etat.serveur !== false) { return; }
		clearTimeout(minuteurSauvegarde);
		minuteurSauvegarde = setTimeout(() => {
			try { localStorage.setItem(cleBrouillon(), JSON.stringify({ donnees: etat.donnees, format: etat.format })); } catch (e) { /* stockage indisponible */ }
		}, 400);
	}
	function lireBrouillon() {
		if (etat.serveur !== false) { return null; }
		try { return JSON.parse(localStorage.getItem(cleBrouillon()) || 'null'); } catch (e) { return null; }
	}

	/* --------------------------------------------------------- champs */
	const libelleDe = (id) => {
		if (/^libreb\d+$/.test(String(id))) { return 'Image ajoutée'; }
		if (/^b\d+$/.test(String(id)) && typeof blocDe === 'function' && blocDe(id)) { return libelleBloc(blocDe(id)); }
		const c = (etat.preset.champs || []).find((x) => x.id === id) || (etat.preset.images || []).find((x) => x.id === id);
		return c ? c.libelle : id;
	};
	function changer(id, valeur) {
		etat.donnees[id] = valeur;
		sauverBrouillon();
		planifierRendu();
	}
	function compteur(saisie, max) {
		const c = el('span', 'st-compteur');
		const maj = () => {
			const n = saisie.value.length;
			c.textContent = n + ' / ' + max;
			c.classList.toggle('st-compteur--plein', n >= max);
		};
		saisie.addEventListener('input', maj);
		maj();
		return c;
	}
	function champ(c) {
		const bloc = el('div', 'st-champ');
		bloc.dataset.champ = c.id;
		const id = 'st-' + c.id;
		const tete = el('div', 'st-champ__tete');
		const lab = el('label', 'st-libelle', c.libelle);
		lab.htmlFor = id;
		if (c.requis || c.min > 0) { lab.appendChild(el('span', 'st-requis', ' *')); lab.title = 'Obligatoire'; }
		tete.appendChild(lab);
		/* un champ qui ne sert que dans certains formats (la suite du communique : A4) */
		if (c.formats) {
			bloc.dataset.formats = c.formats.join(' ');
			const puce = el('span', 'st-format-champ', c.formats.map((f) => NOM_FORMAT[f] || f).join(' et ') + ' seulement');
			puce.title = 'Ce champ ne paraît que dans ces formats.';
			tete.appendChild(puce);
		}
		bloc.appendChild(tete);
		let aide = null;
		if (c.aide) { aide = el('p', 'st-aide', c.aide); aide.id = id + '-aide'; }
		const valeur = etat.donnees[c.id];
		let saisie;

		if (c.type === 'liste') {
			bloc.appendChild(liste(c, id));
			if (aide) { bloc.appendChild(aide); }
			return bloc;
		}
		if (serviceStrict(c)) {
			/* le service emetteur se choisit dans la liste du superadmin */
			saisie = el('select', 'st-saisie');
			saisie.appendChild(new Option('Choisir un service…', ''));
			const services = etat.reglages.listes.services.slice();
			if (valeur && services.indexOf(String(valeur)) === -1) { services.unshift(String(valeur)); }
			services.forEach((x) => saisie.appendChild(new Option(x, x)));
			saisie.value = valeur == null ? '' : String(valeur);
			saisie.addEventListener('change', () => changer(c.id, saisie.value));
		} else if (c.type === 'sousCode') {
			saisie = el('select', 'st-saisie');
			saisie.appendChild(new Option(etat.preset.code + ' (par défaut)', ''));
			(etat.preset.sousCodes || []).forEach((s) => saisie.appendChild(new Option(s.code + ' — ' + s.libelle, s.code)));
			saisie.value = valeur || '';
			saisie.addEventListener('change', () => {
				/* Le type d'evenement suit le sous-code, tant qu'on ne l'a pas ecrit soi-meme. */
				const avant = (etat.preset.sousCodes || []).find((s) => s.code === etat.donnees[c.id]);
				const apres = (etat.preset.sousCodes || []).find((s) => s.code === saisie.value);
				const type = $('st-type');
				if (type && apres && (!type.value.trim() || (avant && type.value === avant.libelle))) {
					type.value = apres.libelle;
					type.dispatchEvent(new Event('input'));
				}
				changer(c.id, saisie.value);
			});
		} else if (c.type === 'titre' || c.type === 'texteLong') {
			saisie = el('textarea', 'st-saisie');
			saisie.rows = c.type === 'titre' ? 3 : (c.paragraphes ? 9 : 4);
		} else if (c.type === 'url') {
			saisie = el('input', 'st-saisie');
			saisie.type = 'url';
			saisie.inputMode = 'url';
			saisie.placeholder = 'https://';
			saisie.spellcheck = false;
		} else if (c.type === 'date') {
			saisie = el('input', 'st-saisie');
			saisie.type = 'date';
		} else if (c.type === 'heure') {
			saisie = el('input', 'st-saisie');
			saisie.type = 'text';
			saisie.inputMode = 'numeric';
			saisie.placeholder = '15 ou 15h30';
			saisie.maxLength = 5;
		} else {
			saisie = el('input', 'st-saisie');
			saisie.type = 'text';
		}
		saisie.id = id;
		if (c.requis) { saisie.setAttribute('aria-required', 'true'); }
		if (aide) { saisie.setAttribute('aria-describedby', aide.id); }
		if (c.type !== 'sousCode' && !serviceStrict(c)) {
			saisie.value = valeur == null ? '' : String(valeur);
			if (c.max) {
				saisie.maxLength = c.max;
				tete.appendChild(compteur(saisie, c.max));
			}
			saisie.addEventListener('input', () => changer(c.id, saisie.value));
		}
		bloc.appendChild(saisie);
		if (saisie.tagName === 'INPUT' && saisie.type === 'text') { suggestions(c, saisie, bloc); }
		if (aide) { bloc.appendChild(aide); }
		return bloc;
	}

	/* Une liste (intervenants…) : une ligne par element, entre min et max. */
	function liste(c, id) {
		const boite = el('div', 'st-liste');
		boite.id = id;
		const dessiner = (focusSur) => {
			boite.replaceChildren();
			const elements = Array.isArray(etat.donnees[c.id]) ? etat.donnees[c.id] : [];
			elements.forEach((v, i) => {
				const ligne = el('div', 'st-liste__ligne');
				const n = el('span', 'st-liste__numero', String(i + 1).padStart(2, '0'));
				const saisie = el('input', 'st-saisie');
				saisie.type = 'text';
				saisie.maxLength = c.maxElement;
				saisie.value = v;
				saisie.setAttribute('aria-label', c.libelle + ' ' + (i + 1));
				if (LISTES_SUGGEREES[c.id] && etat.reglages && etat.reglages.listes[LISTES_SUGGEREES[c.id]].length) { suggestions(c, saisie, boite); }
				saisie.addEventListener('input', () => { const t = (etat.donnees[c.id] || []).slice(); t[i] = saisie.value; changer(c.id, t); });
				const retirer = el('button', 'st-icone');
				retirer.type = 'button';
				retirer.title = 'Retirer';
				retirer.setAttribute('aria-label', 'Retirer ' + c.libelle.toLowerCase() + ' ' + (i + 1));
				retirer.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
				retirer.addEventListener('click', () => { const t = (etat.donnees[c.id] || []).slice(); t.splice(i, 1); changer(c.id, t); dessiner(); });
				ligne.append(n, saisie, retirer);
				boite.appendChild(ligne);
				if (focusSur === i) { setTimeout(() => focaliser(saisie), 0); }
			});
			if (elements.length < c.max) {
				const ajouter = el('button', 'st-lien', '+ Ajouter');
				ajouter.type = 'button';
				ajouter.addEventListener('click', () => { const t = (etat.donnees[c.id] || []).concat(['']); etat.donnees[c.id] = t; dessiner(t.length - 1); });
				boite.appendChild(ajouter);
			}
			const bornes = c.min > 0 ? elements.length + ' · de ' + c.min + ' à ' + c.max : elements.length + ' sur ' + c.max + ' au plus';
			boite.appendChild(el('span', 'st-aide', bornes + ' · ' + c.maxElement + ' caractères chacun'));
		};
		dessiner();
		return boite;
	}

	/* --------------------------------------------------- les images */
	/* Un emplacement d'image : le fichier, sa description, fondre le blanc, l'image entiere
	   ou le cadre plein ; pour le cadre plein, le recadrage (point focal et zoom, gardes d'un
	   format a l'autre) ; les reglages rapides ; « Retoucher dans l'editeur » (l'atelier
	   /retouche-photo, dans un autre onglet, renvoie la photo ici). */
	const dessinsImages = {};   // emplacement -> redessiner son champ
	const CADRAGE = () => ({ x: 0.5, y: 0.5, zoom: 1 });
	const REGLAGES_NEUTRES = () => ({ lumiere: 0, contraste: 0, saturation: 0, mode: 'couleur' });
	const borner = (v, a, b) => Math.max(a, Math.min(b, v));

	/* Une image preparee posee dans son emplacement ; « garder » : son recadrage et ses
	   reglages restent (la meme photo revenue de l'atelier de retouche). */
	function poserImage(im, p, nom, original, garder) {
		const avant = etat.images[im.id];
		if (avant) { URL.revokeObjectURL(avant.url); }
		etat.images[im.id] = Object.assign(p, {
			url: URL.createObjectURL(p.blob), nom, original,
			alt: avant ? avant.alt : '',
			fondre: avant ? avant.fondre : im.fondre !== false,
			ajustement: avant ? avant.ajustement : (im.ajustement || 'contenir'),
			cadrage: garder && avant ? avant.cadrage : CADRAGE(),
			reglages: garder && avant ? avant.reglages : REGLAGES_NEUTRES(),
			fichier: null,   // pas encore enregistree sur le serveur
		});
		if (dessinsImages[im.id]) { dessinsImages[im.id](); }
		planifierRendu();
	}

	/* une image de la mediatheque (ou deja utilisee) posee dans l'emplacement, avec son texte alternatif */
	function depuisMediatheque(im) {
		return async (f, alt) => {
			try {
				const garde = etat.images[im.id];
				poserImage(im, await preparerImage(f), f.name, f, false);
				if (etat.images[im.id]) { etat.images[im.id].alt = alt || (garde ? garde.alt : ''); }
				if (dessinsImages[im.id]) { dessinsImages[im.id](); }
				annoncer('');
			} catch (e) { annoncer(e.message, 'erreur'); }
		};
	}
	function emplacement(im) {
		const bloc = el('div', 'st-champ st-image');
		bloc.dataset.image = im.id;
		const id = 'st-img-' + im.id;
		const lab = el('label', 'st-libelle', im.libelle);
		lab.htmlFor = id;
		if (im.requis) { lab.appendChild(el('span', 'st-requis', ' *')); }
		const fichier = el('input', 'st-fichier-cache');
		fichier.type = 'file';
		fichier.accept = TYPES_IMAGE.join(',');
		fichier.id = id;
		const vue = el('div', 'st-image__vue');
		const choisir = el('label', 'st-bouton', 'Choisir une image');
		choisir.htmlFor = id;
		bloc.append(lab, fichier, vue);

		const dessiner = () => {
			vue.replaceChildren();
			const x = etat.images[im.id];
			if (!x) {
				vue.appendChild(choisir);
				vue.appendChild(boutonMediatheque(depuisMediatheque(im)));
				vue.appendChild(el('p', 'st-aide', 'JPEG, PNG ou WebP, 15 Mo au plus.' + (im.requis ? '' : ' Facultative : sans image, le titre prend toute la largeur.')));
				return;
			}
			const tete = el('div', 'st-image__tete');
			const vignette = el('img', 'st-image__vignette');
			vignette.src = x.url;
			vignette.alt = '';
			const info = el('div', 'st-image__info');
			info.append(el('b', '', x.nom), el('span', 'st-aide', x.largeur + ' × ' + x.hauteur + ' px'));
			const liens = el('div', 'st-image__liens');
			const remplacer = el('label', 'st-lien', 'Remplacer');
			remplacer.htmlFor = id;
			const retirer = el('button', 'st-lien', 'Retirer');
			retirer.type = 'button';
			retirer.addEventListener('click', () => { URL.revokeObjectURL(x.url); delete etat.images[im.id]; dessiner(); planifierRendu(); });
			liens.append(remplacer, retirer, boutonMediatheque(depuisMediatheque(im), 'st-lien'));
			if (RETOUCHE) {
				const retoucher = el('button', 'st-lien', 'Retoucher dans l\'éditeur');
				retoucher.type = 'button';
				retoucher.title = 'Ouvre la photo dans l\'atelier de retouche (autre onglet) ; « Renvoyer au Studio UAZ » la remet ici.';
				retoucher.addEventListener('click', () => ouvrirRetouche(im.id));
				liens.appendChild(retoucher);
			}
			info.appendChild(liens);
			tete.append(vignette, info);

			const alt = el('input', 'st-saisie');
			alt.type = 'text';
			alt.maxLength = 150;
			alt.id = id + '-alt';
			alt.value = x.alt;
			alt.setAttribute('aria-required', 'true');
			alt.placeholder = "Ce que montre l'image, en une phrase";
			alt.addEventListener('input', () => { x.alt = alt.value; majAlertes(); });
			const labAlt = el('label', 'st-libelle', "Description de l'image");
			labAlt.htmlFor = alt.id;
			labAlt.appendChild(el('span', 'st-requis', ' *'));

			const fondre = el('label', 'st-case');
			const caseF = el('input');
			caseF.type = 'checkbox';
			caseF.checked = x.fondre;
			caseF.addEventListener('change', () => { x.fondre = caseF.checked; planifierRendu(); });
			fondre.append(caseF, document.createTextNode(' Fondre le fond blanc (illustrations)'));

			const ajust = el('select', 'st-saisie');
			ajust.id = id + '-cadrage';
			ajust.append(new Option('Image entière', 'contenir'), new Option('Remplir le cadre', 'remplir'));
			ajust.value = x.ajustement;
			ajust.addEventListener('change', () => { x.ajustement = ajust.value; dessiner(); planifierRendu(); });
			const labAjust = el('label', 'st-libelle', 'Cadrage');
			labAjust.htmlFor = ajust.id;

			const resolution = el('p', 'st-image__alerte');
			resolution.id = id + '-resolution';
			resolution.hidden = true;
			resolution.setAttribute('role', 'status');

			vue.append(tete, labAlt, alt, fondre, labAjust, ajust);
			if (x.ajustement === 'remplir') { vue.appendChild(recadrage(im, x, id)); }
			vue.append(reglagesImage(im, x, id), resolution);
			majImages();
		};
		dessinsImages[im.id] = dessiner;
		fichier.addEventListener('change', async () => {
			const f = fichier.files && fichier.files[0];
			fichier.value = '';
			if (!f) { return; }
			annoncer('Préparation de l\'image…');
			try {
				poserImage(im, await preparerImage(f), f.name, f, false);
				annoncer('');
				setTimeout(() => { const a = $(id + '-alt'); if (a && !a.value) { focaliser(a); } }, 0);
			} catch (e) {
				annoncer(e.message, 'erreur');
			}
		});
		dessiner();
		return bloc;
	}

	/* Le recadrage : la photo entiere, et en clair la partie que montre le format affiche.
	   On glisse cette fenetre (ou la photo dans l'apercu), fleches au clavier, zoom au curseur. */
	function recadrage(im, x, id) {
		const zone = el('div', 'st-recadrage');
		const tete = el('div', 'st-champ__tete');
		tete.append(el('span', 'st-libelle', 'Recadrer'), el('span', 'st-aide', 'ou glissez la photo dans l\'aperçu'));
		const boite = el('div', 'st-recadrage__boite');
		const ratio = x.largeur / x.hauteur;
		boite.style.aspectRatio = String(ratio);
		boite.style.width = 'min(100%, ' + Math.round(230 * ratio) + 'px)';
		const photo = el('img', 'st-recadrage__photo');
		photo.src = x.url;
		photo.alt = '';
		photo.draggable = false;
		const fenetre = el('div', 'st-recadrage__fenetre');
		fenetre.id = id + '-fenetre';
		fenetre.tabIndex = 0;
		fenetre.setAttribute('role', 'img');
		fenetre.setAttribute('aria-label', 'Partie visible de l\'image : flèches du clavier pour la déplacer');
		boite.append(photo, fenetre);

		/* la fenetre : celle du dernier rendu, estimee pendant un geste */
		const visible = () => {
			const r = etat.rapport && etat.rapport.images && etat.rapport.images[im.id];
			return r ? r.visible.slice() : [0, 0, 1, 1];
		};
		const deplacer = (cx, cy) => {
			const v = visible();
			x.cadrage.x = borner(cx, v[2] / 2, 1 - v[2] / 2);
			x.cadrage.y = borner(cy, v[3] / 2, 1 - v[3] / 2);
			placerFenetre(fenetre, [x.cadrage.x - v[2] / 2, x.cadrage.y - v[3] / 2, v[2], v[3]]);
			planifierRendu();
		};
		let geste = null;
		boite.addEventListener('pointerdown', (e) => {
			if (e.button !== 0) { return; }
			e.preventDefault();
			const r = boite.getBoundingClientRect();
			const fx = (e.clientX - r.left) / r.width, fy = (e.clientY - r.top) / r.height;
			const v = visible();
			const dedans = fx >= v[0] && fx <= v[0] + v[2] && fy >= v[1] && fy <= v[1] + v[3];
			/* hors de la fenetre : elle vient se centrer sous le doigt, puis suit */
			geste = { r, fx, fy, x: dedans ? x.cadrage.x : fx, y: dedans ? x.cadrage.y : fy };
			if (!dedans) { deplacer(fx, fy); }
			boite.setPointerCapture(e.pointerId);
			fenetre.focus({ preventScroll: true });
		});
		boite.addEventListener('pointermove', (e) => {
			if (!geste) { return; }
			deplacer(geste.x + (e.clientX - geste.r.left) / geste.r.width - geste.fx, geste.y + (e.clientY - geste.r.top) / geste.r.height - geste.fy);
		});
		const fin = () => { geste = null; };
		boite.addEventListener('pointerup', fin);
		boite.addEventListener('pointercancel', fin);
		fenetre.addEventListener('keydown', (e) => {
			const pas = e.shiftKey ? 0.1 : 0.02;
			const d = { ArrowLeft: [-pas, 0], ArrowRight: [pas, 0], ArrowUp: [0, -pas], ArrowDown: [0, pas] }[e.key];
			if (!d) { return; }
			e.preventDefault();
			deplacer(x.cadrage.x + d[0], x.cadrage.y + d[1]);
		});

		/* le zoom : de 100 % (le cadre juste plein) a 400 % */
		const ligne = el('div', 'st-reglage');
		const labZoom = el('label', 'st-libelle', 'Zoom');
		const curseur = el('input', 'st-curseur');
		curseur.type = 'range';
		curseur.min = '100';
		curseur.max = '400';
		curseur.step = '5';
		curseur.id = id + '-zoom';
		labZoom.htmlFor = curseur.id;
		curseur.value = String(Math.round(x.cadrage.zoom * 100));
		const valeur = el('output', 'st-reglage__valeur', curseur.value + ' %');
		curseur.addEventListener('input', () => {
			const avant = x.cadrage.zoom, apres = +curseur.value / 100;
			x.cadrage.zoom = apres;
			valeur.textContent = curseur.value + ' %';
			const v = visible(), w = v[2] * avant / apres, h = v[3] * avant / apres;
			placerFenetre(fenetre, [x.cadrage.x - w / 2, x.cadrage.y - h / 2, w, h]);
			planifierRendu();
		});
		ligne.append(labZoom, curseur, valeur);
		const recentrer = el('button', 'st-lien', 'Recentrer');
		recentrer.type = 'button';
		recentrer.addEventListener('click', () => { x.cadrage = CADRAGE(); dessinsImages[im.id](); planifierRendu(); });
		zone.append(tete, boite, ligne, recentrer);
		return zone;
	}
	function placerFenetre(f, v) {
		f.style.left = (v[0] * 100) + '%';
		f.style.top = (v[1] * 100) + '%';
		f.style.width = (v[2] * 100) + '%';
		f.style.height = (v[3] * 100) + '%';
	}

	/* Les reglages rapides, dans la marque : lumiere, contraste, saturation ; couleur, noir et
	   blanc ou duotone marine et creme. Le moteur les applique a l'apercu comme a l'export. */
	function reglagesImage(im, x, id) {
		const d = el('details', 'st-reglages');
		const r = x.reglages;
		d.open = r.lumiere !== 0 || r.contraste !== 0 || r.saturation !== 0 || r.mode !== 'couleur';
		d.appendChild(el('summary', '', 'Réglages de l\'image'));
		const bornes = StudioRendu.REGLAGES;
		const curseurs = {};
		[['lumiere', 'Lumière'], ['contraste', 'Contraste'], ['saturation', 'Saturation']].forEach(([cle, libelle]) => {
			const ligne = el('div', 'st-reglage');
			const lab = el('label', 'st-libelle', libelle);
			const c = el('input', 'st-curseur');
			c.type = 'range';
			c.min = String(bornes[cle][0]);
			c.max = String(bornes[cle][1]);
			c.step = '1';
			c.id = id + '-' + cle;
			lab.htmlFor = c.id;
			c.value = String(r[cle]);
			const v = el('output', 'st-reglage__valeur', (r[cle] > 0 ? '+' : '') + r[cle]);
			c.addEventListener('input', () => { r[cle] = +c.value; v.textContent = (r[cle] > 0 ? '+' : '') + r[cle]; planifierRendu(); });
			ligne.append(lab, c, v);
			curseurs[cle] = c;
			d.appendChild(ligne);
		});
		const modes = el('div', 'st-segments');
		modes.setAttribute('role', 'group');
		modes.setAttribute('aria-label', 'Rendu de l\'image');
		const majModes = () => {
			modes.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.mode === r.mode)));
			curseurs.saturation.disabled = r.mode !== 'couleur';
		};
		[['couleur', 'Couleur'], ['nb', 'Noir et blanc'], ['duotone', 'Duotone marine et crème']].forEach(([mode, libelle]) => {
			const b = el('button', 'st-segment', libelle);
			b.type = 'button';
			b.dataset.mode = mode;
			b.addEventListener('click', () => { r.mode = mode; majModes(); planifierRendu(); });
			modes.appendChild(b);
		});
		majModes();
		const remettre = el('button', 'st-lien', 'Remettre les réglages à zéro');
		remettre.type = 'button';
		remettre.addEventListener('click', () => { x.reglages = REGLAGES_NEUTRES(); dessinsImages[im.id](); planifierRendu(); });
		d.append(modes, remettre);
		return d;
	}

	/* Apres chaque rendu : la fenetre du recadrage et l'alerte de resolution de chaque emplacement */
	function majImages() {
		const r = etat.rapport;
		Object.keys(dessinsImages).forEach((emp) => {
			const f = $('st-img-' + emp + '-fenetre');
			const info = r && r.images && r.images[emp];
			if (f && info) { placerFenetre(f, info.visible); }
			const alerte = $('st-img-' + emp + '-resolution');
			if (alerte) {
				const a = r && r.avertissements.find((x) => x.champ === emp && x.resolution);
				alerte.hidden = !a;
				alerte.textContent = a ? a.message : '';
			}
		});
	}

	/* --- le pont avec l'atelier de retouche (src/js/retouche-photo.js, meme site, meme session) :
	   l'atelier s'ouvre avec ?studio=<jeton>, dit « pret », recoit la photo, et la renvoie
	   retouchee ; elle remplace celle de l'emplacement, recadrage et reglages gardes. */
	const ponts = new Map();   // jeton -> { emplacement, fenetre }
	function ouvrirRetouche(emp) {
		if (!etat.images[emp]) { return; }
		const jeton = 'st' + Array.from(crypto.getRandomValues(new Uint32Array(2)), (n) => n.toString(36)).join('');
		const fenetre = window.open(RETOUCHE + '?studio=' + jeton, '_blank');
		if (!fenetre) { annoncer('Le navigateur a bloqué l\'ouverture de l\'atelier de retouche : autorisez les fenêtres de ce site, puis réessayez.', 'erreur'); return; }
		ponts.set(jeton, { emplacement: emp, fenetre });
		annoncer('L\'atelier de retouche s\'ouvre dans un autre onglet. « Renvoyer au Studio UAZ » y remet la photo ici.');
	}
	window.addEventListener('message', async (e) => {
		const d = e.data;
		if (e.origin !== location.origin || !d || typeof d !== 'object' || !ponts.has(d.jeton)) { return; }
		const pont = ponts.get(d.jeton);
		if (e.source !== pont.fenetre) { return; }
		const im = (etat.preset.images || []).find((i) => i.id === pont.emplacement);
		const x = etat.images[pont.emplacement];
		if (d.type === 'studio-uaz:pret') {
			if (!im || !x) { return; }
			pont.fenetre.postMessage({ type: 'studio-uaz:photo', jeton: d.jeton, blob: x.original || x.blob, nom: x.nom, emplacement: im.id, libelle: im.libelle }, location.origin);
		} else if (d.type === 'studio-uaz:retour' && d.blob instanceof Blob) {
			if (!im) { annoncer('La photo retouchée est arrivée, mais ce type de visuel n\'a plus cet emplacement.', 'erreur'); return; }
			try {
				const nom = String(d.nom || 'photo-retouchee.jpg');
				const f = new File([d.blob], nom, { type: d.blob.type || 'image/jpeg' });
				poserImage(im, await preparerImage(f), nom, f, true);
				pont.fenetre.postMessage({ type: 'studio-uaz:recu', jeton: d.jeton }, location.origin);
				annoncer('Photo retouchée reçue : « ' + im.libelle + ' ».', 'ok');
			} catch (err) {
				annoncer(err.message, 'erreur');
			}
		}
	});

	/* L'image telle qu'elle partira : sens applique, 2 400 px au plus, JPEG < 1,8 Mo. */
	async function preparerImage(f) {
		if (TYPES_IMAGE.indexOf(f.type) === -1) { throw new Error('« ' + f.name + ' » : seules les images JPEG, PNG et WebP s\'ouvrent ici.'); }
		if (f.size > IMAGE_MAX_OCTETS) { throw new Error('« ' + f.name + ' » dépasse 15 Mo.'); }
		let bmp;
		try { bmp = await createImageBitmap(f, { imageOrientation: 'from-image' }); } catch (e) { throw new Error('« ' + f.name + ' » est illisible.'); }
		let k = Math.min(1, IMAGE_MAX_COTE / Math.max(bmp.width, bmp.height));
		for (let essai = 0; essai < 6; essai++, k *= 0.8) {
			const w = Math.max(1, Math.round(bmp.width * k)), h = Math.max(1, Math.round(bmp.height * k));
			const toile = el('canvas');
			toile.width = w;
			toile.height = h;
			const g = toile.getContext('2d');
			g.fillStyle = '#FFFFFF'; // une transparence devient du blanc : « fondre » le fait disparaitre
			g.fillRect(0, 0, w, h);
			g.drawImage(bmp, 0, 0, w, h);
			for (let q = 0.9; q >= 0.6; q -= 0.1) {
				const blob = await new Promise((ok) => toile.toBlob(ok, 'image/jpeg', q));
				if (blob && blob.size <= ENVOI_MAX_OCTETS) { return { blob, largeur: w, hauteur: h }; }
			}
		}
		throw new Error('« ' + f.name + ' » ne se réduit pas assez pour être envoyée.');
	}

	/* ------------------------------------------------------- l'apercu */
	function planifierRendu() {
		clearTimeout(etat.minuteur);
		etat.minuteur = setTimeout(rendreApercu, 60);
	}
	function imagesPourRendu() {
		const sortie = {};
		Object.keys(etat.images).forEach((id) => {
			const x = etat.images[id];
			sortie[id] = { src: x.url, alt: x.alt, fondre: x.fondre, ajustement: x.ajustement, cadrage: x.cadrage, reglages: x.reglages };
		});
		return sortie;
	}
	async function rendreApercu() {
		const numero = ++etat.numero;
		try {
			const r = await StudioRendu.rendre($('stVisuel'), {
				preset: etat.preset, format: etat.format, donnees: etat.donnees, images: imagesPourRendu(), base: BASE, apercu: true, miseEnPage: etat.miseEnPage[etat.format] || {}, blocs: etat.blocs,
			});
			if (numero !== etat.numero) { return; }
			etat.rapport = r;
			survoler(null);   // l'element survole vient d'etre remplace
			ajusterEchelle();
			majAlertes();
			majImages();
			if (etat.aSelectionner) { const a = etat.aSelectionner; etat.aSelectionner = null; selectionnerLibre(a.id, a.ouvrir); }
			placerSelection();
		} catch (e) {
			console.error(e);
			annoncer("L'aperçu n'a pas pu être rendu : " + e.message, 'erreur');
		}
	}

	/* ------------------------------------------------------- la vue : zoom et deplacement du plan
	   Comme dans Figma : « tout voir » par defaut (etat.zoom = null), Ctrl+molette zoome sous la
	   souris, la molette et la main deplacent le plan. etat.echelle est l'echelle a l'ecran. */
	const ZOOM_MIN = 0.05, ZOOM_MAX = 8;
	const MARGE_HAUT = 36, MARGE_BAS = 80, MARGE_COTE = 32;   // le nom du cadre au-dessus, la barre d'outils dessous
	function tailleVisuel() {
		const fmt = etat.preset && etat.preset.formats[etat.format];
		/* un document de deux pages (communique A4) : les deux pages l'une sous l'autre */
		const racine = etat.rapport && etat.rapport.racine;
		return fmt ? { l: racine ? racine.offsetWidth : fmt.largeur, h: racine ? racine.offsetHeight : fmt.hauteur } : null;
	}
	/* la place du cadre, centre, sans deplacement du plan */
	function baseCadre(k, t) {
		const scene = $('stScene');
		const W = scene.clientWidth, H = scene.clientHeight;
		return { x: (W - t.l * k) / 2, y: MARGE_HAUT + (H - MARGE_HAUT - MARGE_BAS - t.h * k) / 2 };
	}
	function ajusterEchelle() {
		const t = tailleVisuel();
		if (!t) { return; }
		const scene = $('stScene');
		etat.ajuste = Math.max(ZOOM_MIN, Math.min((scene.clientWidth - 2 * MARGE_COTE) / t.l, (scene.clientHeight - MARGE_HAUT - MARGE_BAS) / t.h, 1));
		const k = etat.zoom || etat.ajuste;
		etat.echelle = k;
		$('stVisuel').style.transform = 'scale(' + k + ')';
		const cadre = $('stCadre'), b = baseCadre(k, t);
		const x = Math.round(b.x + etat.panX), y = Math.round(b.y + etat.panY);
		cadre.style.width = Math.round(t.l * k) + 'px';
		cadre.style.height = Math.round(t.h * k) + 'px';
		cadre.style.left = x + 'px';
		cadre.style.top = y + 'px';
		const nom = $('stCadreNom');
		nom.style.left = x + 'px';
		nom.style.top = (y - 22) + 'px';
		const z = $('stZoom');
		if (z) { z.firstChild.textContent = Math.round(k * 100) + ' %'; }
		majMarges();
	}
	/* zoomer en gardant immobile le point (cx, cy) de l'ecran ; sans point : le centre du plan */
	function zoomer(k1, cx, cy) {
		const t = tailleVisuel();
		if (!t) { return; }
		const s = $('stScene').getBoundingClientRect(), c = $('stCadre').getBoundingClientRect(), k0 = etat.echelle;
		k1 = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, k1));
		if (cx == null) { cx = s.left + s.width / 2; cy = s.top + MARGE_HAUT + (s.height - MARGE_HAUT - MARGE_BAS) / 2; }
		const vx = (cx - c.left) / k0, vy = (cy - c.top) / k0;   // le point du visuel sous la souris
		const b = baseCadre(k1, t);
		etat.zoom = k1;
		etat.panX = cx - s.left - vx * k1 - b.x;
		etat.panY = cy - s.top - vy * k1 - b.y;
		ajusterEchelle();
		placerSelection();
	}
	function toutVoir() {
		etat.zoom = null;
		etat.panX = 0;
		etat.panY = 0;
		ajusterEchelle();
		placerSelection();
	}
	function tailleReelle() {
		etat.panX = 0;
		etat.panY = 0;
		etat.zoom = 1;
		ajusterEchelle();
		placerSelection();
	}
	/* Maj+2 : la selection au centre, aussi grande que possible (400 % au plus) */
	function zoomSelection() {
		const cible = sel && elementDe(sel.id, sel.genre);
		if (!cible) { toutVoir(); return; }
		const t = tailleVisuel(), s = $('stScene').getBoundingClientRect(), c = $('stCadre').getBoundingClientRect(), r = cible.getBoundingClientRect(), k0 = etat.echelle;
		const l = r.width / k0, h = r.height / k0, vx = (r.left - c.left) / k0 + l / 2, vy = (r.top - c.top) / k0 + h / 2;
		const k1 = Math.max(ZOOM_MIN, Math.min(4, (s.width - 160) / Math.max(l, 1), (s.height - MARGE_HAUT - MARGE_BAS - 80) / Math.max(h, 1)));
		const b = baseCadre(k1, t);
		etat.zoom = k1;
		etat.panX = s.width / 2 - vx * k1 - b.x;
		etat.panY = MARGE_HAUT + (s.height - MARGE_HAUT - MARGE_BAS) / 2 - vy * k1 - b.y;
		ajusterEchelle();
		placerSelection();
	}
	function deplacerVue(dx, dy) {
		etat.panX += dx;
		etat.panY += dy;
		ajusterEchelle();
		placerSelection();
	}
	/* Story : les bandes d'Instagram sur l'apercu, si l'interrupteur est mis */
	function majZones() {
		const story = !!Schema().ZONES[etat.format];
		$('stZonesBascule').hidden = !story;
		$('stZonesCalque').hidden = !story || !$('stZones').checked;
		majMarges();
	}
	/* le cadre des marges de 40 px, a l'ecran seulement */
	function majMarges() {
		const c = $('stMargesCalque');
		if (!c) { return; }
		c.hidden = !$('stMarges').checked;
		const m = 40 * (etat.echelle || 1);
		c.style.inset = m + 'px';
	}
	const Schema = () => window.StudioSchema;

	/* Les boutons d'export du format affiche */
	function sorties() {
		const zone = $('stSorties');
		zone.replaceChildren();
		(etat.format === 'A4' ? SORTIES.A4 : SORTIES.ecran).forEach(([sortie, echelle, impression, libelle, icone], i) => {
			const b = el('button', 'st-bouton' + (i === 0 ? ' st-bouton--principal' : ''));
			b.type = 'button';
			b.dataset.sortie = sortie;
			b.dataset.echelle = String(echelle);
			if (impression) { b.dataset.impression = '1'; }
			const ic = el('i', 'bi ' + icone);
			ic.setAttribute('aria-hidden', 'true');
			b.append(ic, document.createTextNode(' ' + libelle));
			if (impression) { b.title = 'Fond perdu de 3 mm et traits de coupe, pour l\'imprimeur'; }
			b.addEventListener('click', () => exporter(sortie, echelle, impression));
			zone.appendChild(b);
		});
		const pages = el('p', 'st-pages-doc');
		pages.id = 'stPages';
		pages.hidden = true;
		zone.appendChild(pages);
	}
	/* Montrer un element en ne faisant defiler que son panneau : le cadre de l'application ne
	   defile jamais (focus() et scrollIntoView() feraient monter toute la page, ecran blanc dessous). */
	function montrer(e, centrer) {
		let panneau = e.parentElement;
		while (panneau && panneau !== app && !(panneau.scrollHeight > panneau.clientHeight + 1 && /(auto|scroll)/.test(getComputedStyle(panneau).overflowY))) { panneau = panneau.parentElement; }
		if (!panneau) { return; }
		const a = e.getBoundingClientRect(), p = panneau.getBoundingClientRect();
		let delta = 0;
		if (centrer) { delta = a.top - p.top - (p.height - a.height) / 2; } else if (a.bottom > p.bottom - 12) { delta = a.bottom - p.bottom + 24; } else if (a.top < p.top + 12) { delta = a.top - p.top - 24; }
		if (delta) { panneau.scrollBy({ top: delta, behavior: centrer ? 'smooth' : 'auto' }); }
	}
	function focaliser(e, centrer) {
		e.focus({ preventScroll: true });
		montrer(e, centrer);
	}
	/* Ouvrir un champ (une alerte, un champ absent du visuel) : sur le visuel s'il y est, sinon dans l'onglet Champs */
	function ouvrirChamp(id) {
		const libre = /^libre(b\d+)$/.exec(String(id));
		if (libre) { selectionnerLibre(libre[1], false); return; }
		if (/^b\d+$/.test(String(id)) && blocDe(id)) { selectionnerLibre(id, false); return; }
		if (etat.preset && selectionner(id, genreDe(id), true)) { return; }
		ongletGauche('champs');
		const bloc = document.querySelector('.st-champ[data-champ="' + id + '"], .st-champ[data-image="' + id + '"]');
		if (!bloc) { return; }
		montrer(bloc, true);
		const saisie = bloc.querySelector('input:not([type=file]), textarea, select') || bloc.querySelector('label.st-bouton');
		if (saisie) { setTimeout(() => saisie.focus({ preventScroll: true }), 150); }
		bloc.classList.remove('st-champ--montre');
		void bloc.offsetWidth;
		bloc.classList.add('st-champ--montre');
	}

	/* -------------------------------------------- ce qui manque, l'export */
	function problemes() {
		const r = etat.rapport;
		if (!r) { return { liste: [], bloquant: true }; }
		const liste = [];
		/* une liste sous son minimum : le dire (« Pièces à fournir (2 au moins) ») */
		const manque = (id) => {
			const c = (etat.preset.champs || []).find((x) => x.id === id);
			return libelleDe(id) + (c && c.type === 'liste' && c.min > 1 ? ' (' + c.min + ' au moins)' : '');
		};
		if (r.manquants.length) { liste.push({ genre: 'manque', texte: 'À remplir : ' + r.manquants.map(manque).join(', ') + '.', champs: r.manquants }); }
		const trop = Array.from(new Set([].concat(...r.debordements.map((d) => d.champs))));
		if (r.debordements.length) { liste.push({ genre: 'deborde', texte: 'Texte trop long : ' + (trop.length ? trop.map(libelleDe).join(', ') : 'une case') + '. Raccourcissez-le.', champs: trop }); }
		const sansAlt = clesImages().filter((id) => !String(etat.images[id].alt || '').trim());
		if (sansAlt.length) { liste.push({ genre: 'manque', texte: 'Décrivez l\'image (texte alternatif) : ' + sansAlt.map(libelleDe).join(', ') + '.', champs: sansAlt }); }
		r.avertissements.forEach((a) => liste.push({ genre: 'avertit', texte: (a.champ ? libelleDe(a.champ) + ' : ' : '') + a.message, champs: a.champ ? [a.champ] : [] }));
		return { liste, bloquant: liste.some((p) => p.genre !== 'avertit') };
	}
	function majAlertes() {
		const p = problemes();
		const zone = $('stAlertes');
		zone.replaceChildren();
		document.querySelectorAll('.st-champ--manque, .st-champ--deborde').forEach((e) => e.classList.remove('st-champ--manque', 'st-champ--deborde'));
		p.liste.forEach((x) => {
			const b = el('button', 'st-alerte st-alerte--' + x.genre, x.texte);
			b.type = 'button';
			if (x.champs[0]) { b.addEventListener('click', () => ouvrirChamp(x.champs[0])); } else { b.disabled = true; }
			zone.appendChild(b);
			x.champs.forEach((id) => {
				const bloc = document.querySelector('.st-champ[data-champ="' + id + '"], .st-champ[data-image="' + id + '"]');
				if (bloc && x.genre !== 'avertit') { bloc.classList.add(x.genre === 'deborde' ? 'st-champ--deborde' : 'st-champ--manque'); }
			});
		});
		if (!p.liste.length) { zone.appendChild(el('p', 'st-pret', 'Le visuel est prêt à être exporté.')); }
		const raison = etat.service === false ? 'Le service de rendu ne répond pas.' : (p.bloquant ? 'Complétez le visuel d\'abord.' : raisonValidation());
		/* deux pages (communique A4 au texte long) : une image ne montrerait que la premiere */
		const pages = etat.rapport ? etat.rapport.pages || 1 : 1;
		/* un type a validation obligatoire : le chemin vers l'onglet Publication */
		const versValidation = $('stAllerValidation');
		if (versValidation) { versValidation.hidden = !(etat.service !== false && !p.bloquant && raisonValidation() && etat.serveur === true); }
		document.querySelectorAll('#stExport [data-sortie]').forEach((b) => {
			const image = b.dataset.sortie === 'png' || b.dataset.sortie === 'jpg';
			const ici = image && pages > 1 && b.closest('#stSorties') ? 'Le document fait ' + pages + ' pages : exportez-le en PDF.' : '';
			b.disabled = !!raison || !!ici || etat.occupe;
			b.title = raison || ici || (b.dataset.impression ? 'Fond perdu de 3 mm et traits de coupe, pour l\'imprimeur' : '');
		});
		const info = $('stPages');
		if (info) {
			info.hidden = pages < 2;
			info.textContent = pages > 1 ? 'Le texte continue sur une deuxième page : en-tête et pied repris, « Page 1 sur 2 » en bas.' : '';
		}
		majCalques();
		const rm = $('stMepReset');
		if (rm) { rm.hidden = !nbDeplaces() || etat.lecture; rm.textContent = ''; rm.innerHTML = '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Remettre la mise en page d\'origine (' + nbDeplaces() + ' élément' + (nbDeplaces() > 1 ? 's' : '') + ' modifié' + (nbDeplaces() > 1 ? 's' : '') + ')'; }
		suivreModifications();
	}
	/* un type de visuel a validation obligatoire ne s'exporte qu'une fois valide, et enregistre tel que valide */
	function raisonValidation() {
		if (!etat.preset || etat.obligatoire.indexOf(etat.preset.id) === -1) { return ''; }
		if (etat.serveur !== true) { return 'Ce type de visuel s\'exporte après validation : les visuels enregistrés ne sont pas disponibles.'; }
		const v = etat.visuel;
		if (!v) { return 'Ce type de visuel s\'exporte après validation : il s\'enregistre, puis se soumet.'; }
		if (v.statut === 'a_valider') { return 'En attente de validation.'; }
		if (v.statut === 'refuse') { return 'Refusé : corrigez-le et soumettez-le de nouveau.'; }
		if (v.statut !== 'valide') { return 'À soumettre pour validation d\'abord.'; }
		if (modifie()) { return 'Des modifications ne sont pas encore enregistrées.'; }
		return '';
	}
	function nomDepuis(entete, repli) {
		const m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(entete || '');
		return m ? decodeURIComponent(m[1]) : repli;
	}
	function telecharger(blob, nom) {
		const a = el('a');
		a.href = URL.createObjectURL(blob);
		a.download = nom;
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(() => URL.revokeObjectURL(a.href), 4000);
	}
	async function exporter(sortie, echelle, impression) {
		if (etat.occupe || problemes().bloquant) { return; }
		etat.occupe = true;
		majAlertes();
		annoncer(sortie === 'zip' ? 'Export des quatre formats en cours… (quelques secondes)' : 'Export en cours…');
		try {
			const fd = new FormData();
			fd.append('preset', etat.preset.id);
			fd.append('format', etat.format);
			fd.append('sortie', sortie);
			fd.append('echelle', String(echelle));
			if (impression) { fd.append('impression', '1'); }
			fd.append('donnees', JSON.stringify(etat.donnees));
			fd.append('mise_en_page', JSON.stringify(etat.miseEnPage));
			fd.append('blocs', JSON.stringify(etat.blocs));
			if (etat.visuel) { fd.append('visuel', String(etat.visuel.id)); }
			const depuisServeur = etat.visuel && etat.obligatoire.indexOf(etat.preset.id) !== -1;   // le serveur relit le visuel valide
			const options = {};
			clesImages().forEach((id) => {
				const x = etat.images[id];
				options[id] = { alt: x.alt, fondre: x.fondre, ajustement: x.ajustement, cadrage: x.cadrage, reglages: x.reglages };
				if (!depuisServeur) { fd.append('image_' + id, x.blob, id + '.jpg'); }
			});
			fd.append('images', JSON.stringify(options));
			const r = await fetch(EXPORT, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			if (!r.ok) {
				const j = await r.json().catch(() => ({}));
				const details = [].concat(j.erreurs || [], (j.manquants || []).length ? ['À remplir : ' + j.manquants.join(', ')] : [], (j.debordements || []).length ? ['Texte trop long : ' + j.debordements.join(', ')] : []);
				throw new Error((j.message || 'L\'export a échoué (' + r.status + ').') + (details.length ? ' ' + details.join(' · ') : ''));
			}
			const blob = await r.blob();
			const nom = nomDepuis(r.headers.get('Content-Disposition'), 'visuel.' + sortie);
			telecharger(blob, nom);
			annoncer('Exporté : ' + nom, 'ok');
		} catch (e) {
			annoncer(e.message, 'erreur');
		} finally {
			etat.occupe = false;
			majAlertes();
		}
	}

	/* ------------------------------------------------------- l'ouverture */
	function formats() {
		deselectionner();
		/* les formats, comme les pages de Figma : une forme a la proportion du format, son nom, sa taille */
		const zone = $('stFormats');
		zone.replaceChildren();
		FORMATS.forEach(([f, libelle]) => {
			const fmt = etat.preset.formats[f];
			const b = el('button', 'st-page');
			b.type = 'button';
			b.setAttribute('role', 'tab');
			b.dataset.format = f;
			const forme = el('span', 'st-page__forme');
			forme.setAttribute('aria-hidden', 'true');
			const r = el('span');
			r.style.aspectRatio = fmt ? fmt.largeur + ' / ' + fmt.hauteur : ({ '4:5': '4 / 5', '1:1': '1 / 1', '9:16': '9 / 16', A4: '210 / 297' })[f];
			if (fmt && fmt.hauteur > fmt.largeur) { r.style.width = 'auto'; r.style.height = '100%'; }
			forme.appendChild(r);
			b.append(forme, el('span', 'st-page__nom', libelle), el('span', 'st-page__taille', fmt ? fmt.largeur + ' × ' + fmt.hauteur : '—'));
			b.disabled = !fmt;
			if (!fmt) { b.title = 'Pas encore disponible pour ce visuel'; }
			b.setAttribute('aria-selected', String(f === etat.format));
			b.tabIndex = f === etat.format ? 0 : -1;
			b.addEventListener('click', () => { if (etat.format === f) { return; } etat.format = f; etat.zoom = null; etat.panX = 0; etat.panY = 0; annoncer(''); sauverBrouillon(); formats(); planifierRendu(); });
			zone.appendChild(b);
		});
		const fmt = etat.preset.formats[etat.format];
		const nom = (FORMATS.find((x) => x[0] === etat.format) || [0, etat.format])[1];
		$('stCadreNom').textContent = nom + (fmt ? ' · ' + fmt.largeur + ' × ' + fmt.hauteur : '');
		$('stDimensions').textContent = nom + (fmt ? ' — ' + fmt.largeur + ' × ' + fmt.hauteur + ' px' : '');
		/* les champs d'autres formats, en retrait ; les sorties du format ; les zones de la story */
		document.querySelectorAll('.st-champ[data-formats]').forEach((c) => c.classList.toggle('st-champ--ailleurs', c.dataset.formats.split(' ').indexOf(etat.format) === -1));
		sorties();
		majZones();
		majAlertes();
	}
	function construireFormulaire() {
		deselectionner();
		const form = $('stForm');
		form.replaceChildren();
		etat.preset.champs.forEach((c) => form.appendChild(champ(c)));
		(etat.preset.images || []).forEach((im) => form.appendChild(emplacement(im)));
	}
	/* les valeurs d'un visuel neuf : celles du preset, et une ligne vide par element exige d'une liste */
	function valeursInitiales(p) {
		const d = {};
		const reg = (etat.reglages && etat.reglages.defauts && etat.reglages.defauts[p.id]) || {};
		p.champs.forEach((c) => {
			if (reg[c.id] != null && c.type !== 'liste') { d[c.id] = reg[c.id]; } else if (c.defaut != null) { d[c.id] = c.defaut; } else if (c.type === 'liste' && c.min > 0) { d[c.id] = new Array(c.min).fill(''); }
		});
		return d;
	}
	/* Les images d'un visuel enregistre, relues sur le serveur */
	async function chargerImages(v) {
		for (const id of Object.keys(v.images || {})) {
			const m = v.images[id];
			if (!((etat.preset.images || []).some((i) => i.id === id) || (/^libre(b\d+)$/.test(id) && etat.blocs.some((b) => 'libre' + b.id === id))) || !m.fichier) { continue; }
			try {
				const r = await fetch(VISUELS + '?action=image&id=' + v.id + '&f=' + encodeURIComponent(m.fichier), { credentials: 'same-origin' });
				if (!r.ok) { throw new Error(String(r.status)); }
				const blob = await r.blob();
				const bmp = await createImageBitmap(blob);
				etat.images[id] = {
					blob, largeur: bmp.width, hauteur: bmp.height, url: URL.createObjectURL(blob), nom: m.fichier, original: null,
					alt: m.alt || '', fondre: !!m.fondre, ajustement: m.ajustement, cadrage: m.cadrage, reglages: m.reglages, fichier: m.fichier,
				};
			} catch (e) {
				annoncer('Une image de ce visuel ne se charge pas : ' + (libelleDe(id)) + '.', 'erreur');
			}
		}
	}
	/* Ouvre un type de visuel : neuf, ou un visuel enregistre (la reponse de app/studio.visuels.php) */
	async function ouvrirPreset(id, visuel) {
		const p = etat.presets[id];
		const v = StudioSchema.valider(p, etat.schema);
		if (!v.ok) {
			$('stForm').replaceChildren(el('p', 'st-alerte st-alerte--manque', 'Ce preset ne respecte pas la charte : ' + v.erreurs.slice(0, 5).join(' ')));
			return;
		}
		etat.chargement = true;
		deselectionner();
		clearTimeout(etat.minuterieSauve);
		Object.values(etat.images).forEach((x) => URL.revokeObjectURL(x.url));
		annoncer('');
		etat.preset = p;
		etat.images = {};
		Object.keys(dessinsImages).forEach((k) => { delete dessinsImages[k]; });
		etat.visuel = visuel || null;
		etat.rapport = null;   // celui du visuel precedent ne compte plus : l'export attend le rendu de celui-ci
		etat.lecture = !!visuel && !visuel.droits.modifier;
		etat.messageSauve = '';
		etat.erreurSauve = false;
		etat.sauveA = visuel ? new Date(String(visuel.modifieLe).replace(' ', 'T')) : null;
		const b = visuel ? null : lireBrouillon();
		etat.donnees = visuel ? clone(visuel.donnees) : (b && b.donnees ? b.donnees : valeursInitiales(p));
		etat.miseEnPage = visuel && visuel.miseEnPage && !Array.isArray(visuel.miseEnPage) ? clone(visuel.miseEnPage) : {};   // PHP envoie [] pour « rien »
		etat.blocs = visuel && Array.isArray(visuel.blocs) ? clone(visuel.blocs) : [];
		etat.sigCalques = '';
		etat.format = (visuel && p.formats[visuel.format] && visuel.format) || (b && b.format && p.formats[b.format] ? b.format : Object.keys(p.formats)[0]);
		etat.zoom = null;   // un autre visuel : on le voit en entier
		etat.panX = 0;
		etat.panY = 0;
		if (visuel) { await chargerImages(visuel); }
		construireFormulaire();
		formats();
		/* la legende : celle du visuel si elle a ete retouchee, sinon celle des champs */
		etat.legendeManuelle = false;
		majLegende();
		if (visuel && visuel.legende != null && visuel.legende !== etat.legende) { etat.legende = visuel.legende; etat.legendeManuelle = true; $('stLegende').value = etat.legende; }
		$('stLegende').value = etat.legende;
		etat.sigSauvee = etat.sigVue = signature();
		etat.pile = [instantane()];
		etat.pose = 0;
		etat.chargement = false;
		majVisuelUI();
		majAlertes();
		planifierRendu();
	}
	/* =========================================================================
	   Les visuels enregistres sur le serveur (etape 5, app/studio.visuels.php)

	   - Enregistrement automatique (1,2 s apres la derniere frappe) : rien n'est
	     cree tant qu'on n'a pas change quelque chose ; ce qui n'est pas enregistre
	     est signale, et le navigateur previent avant de quitter la page.
	   - Annuler / retablir : les champs, le format et les options des images
	     (une image ajoutee ou retiree se retrouve par l'historique).
	   - Historique : les 30 dernieres versions, restaurables sans rien perdre.
	   - Validation : facultative ; obligatoire pour le communique et le recrutement,
	     dont l'export part alors du contenu valide, relu sur le serveur.
	   - Un visuel d'un collegue s'ouvre en lecture ; on le duplique pour le modifier.
	   ====================================================================== */
	const STATUTS = { brouillon: 'Brouillon', a_valider: 'En attente de validation', valide: 'Validé', refuse: 'Refusé' };
	const NOMS_SORTIES = { png: 'PNG', jpg: 'JPG', pdf: 'PDF', imprimeur: 'PDF imprimeur', zip: 'Tous les formats (.zip)' };
	const clone = (o) => JSON.parse(JSON.stringify(o));
	const dateCourte = (s) => new Date(String(s).replace(' ', 'T')).toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

	async function api(params, corps) {
		const entetes = { 'X-Requested-With': 'XMLHttpRequest' };
		const r = corps
			? await fetch(VISUELS, { method: 'POST', body: corps, credentials: 'same-origin', headers: entetes })
			: await fetch(VISUELS + '?' + new URLSearchParams(params), { credentials: 'same-origin', headers: entetes });
		const j = await r.json().catch(() => ({ ok: false, message: 'Réponse illisible (' + r.status + ').' }));
		if (!r.ok || !j.ok) { const e = new Error(j.message || 'Erreur ' + r.status + '.'); e.statut = r.status; throw e; }
		return j;
	}
	function post(action, champs) {
		const fd = new FormData();
		fd.append('action', action);
		Object.keys(champs || {}).forEach((k) => fd.append(k, champs[k]));
		return api(null, fd);
	}

	/* ------------------------------------------------ ce qui change, ce qui est enregistre */
	function signature() {
		const imgs = {};
		clesImages().sort().forEach((id) => {
			const x = etat.images[id];
			imgs[id] = [x.url, x.alt, x.fondre, x.ajustement, x.cadrage, x.reglages];
		});
		return JSON.stringify([etat.preset.id, etat.format, etat.donnees, imgs, etat.legende, etat.miseEnPage, etat.blocs]);
	}
	const modifie = () => !!etat.preset && signature() !== etat.sigSauvee;
	function suivreModifications() {
		if (!etat.preset || etat.chargement) { return; }
		majLegende();
		const sig = signature();
		if (sig === etat.sigVue) { return; }
		etat.sigVue = sig;
		empiler();
		planifierSauvegarde();
		majVisuelUI();
	}
	function planifierSauvegarde() {
		clearTimeout(etat.minuterieSauve);
		if (etat.lecture || etat.serveur !== true) { return; }
		etat.minuterieSauve = setTimeout(() => enregistrer('auto'), 1200);
	}
	function messageSauve(texte, erreur) {
		etat.messageSauve = texte || '';
		etat.erreurSauve = !!erreur;
		majVisuelUI();
	}
	/* motif : « auto » (frappe) ou « manuel » (avant de soumettre : une version de l'historique a coup sur) */
	async function enregistrer(motif) {
		if (etat.lecture || etat.serveur !== true || !etat.preset || etat.chargement) { return etat.visuel; }
		clearTimeout(etat.minuterieSauve);
		if (etat.enregistrement) { etat.encore = motif === 'manuel' ? 'manuel' : (etat.encore || 'auto'); return etat.enregistrement; }
		const sig = signature();
		if (sig === etat.sigSauvee && motif !== 'manuel') { return etat.visuel; }
		etat.enregistrement = (async () => {
			messageSauve('Enregistrement…');
			const envoyees = clesImages().map((id) => [id, etat.images[id]]);
			try {
				const fd = new FormData();
				fd.append('action', 'enregistrer');
				fd.append('id', String(etat.visuel ? etat.visuel.id : 0));
				fd.append('preset', etat.preset.id);
				fd.append('format', etat.format);
				fd.append('donnees', JSON.stringify(etat.donnees));
				fd.append('legende', etat.legende);
				fd.append('mise_en_page', JSON.stringify(etat.miseEnPage));
				fd.append('blocs', JSON.stringify(etat.blocs));
				fd.append('motif', motif);
				const options = {};
				envoyees.forEach(([id, x]) => {
					options[id] = { alt: x.alt, fondre: x.fondre, ajustement: x.ajustement, cadrage: x.cadrage, reglages: x.reglages };
					if (x.fichier) { options[id].fichier = x.fichier; } else { fd.append('image_' + id, x.blob, id + '.jpg'); }
				});
				fd.append('images', JSON.stringify(options));
				const j = await api(null, fd);
				etat.visuel = j.visuel;
				envoyees.forEach(([id, x]) => { if (j.visuel.images[id]) { x.fichier = j.visuel.images[id].fichier; } });
				etat.sigSauvee = sig;
				etat.sauveA = new Date();
				try { history.replaceState(null, '', '#v=' + j.visuel.id); } catch (e) { /* adresse inchangee */ }
				if (j.visuel.retourBrouillon) { annoncer('Le visuel a changé depuis sa soumission : il repasse en brouillon. Soumettez-le de nouveau quand il est prêt.'); }
				etat.messageSauve = '';
				etat.erreurSauve = false;
			} catch (e) {
				etat.messageSauve = 'Non enregistré : ' + e.message;
				etat.erreurSauve = true;
				if (e.statut === 403) { etat.lecture = true; }
			} finally {
				etat.enregistrement = null;
				majVisuelUI();
				majAlertes();
				const encore = etat.encore;
				etat.encore = null;
				if (!etat.erreurSauve && (encore || modifie())) { enregistrer(encore || 'auto'); }
			}
			return etat.visuel;
		})();
		return etat.enregistrement;
	}

	/* ------------------------------------------------ annuler / retablir */
	function instantane() {
		const imgs = {};
		Object.keys(etat.images).forEach((id) => {
			const x = etat.images[id];
			imgs[id] = { ref: x, alt: x.alt, fondre: x.fondre, ajustement: x.ajustement, cadrage: clone(x.cadrage), reglages: clone(x.reglages) };
		});
		return { donnees: clone(etat.donnees), format: etat.format, images: imgs, miseEnPage: clone(etat.miseEnPage), blocs: clone(etat.blocs), t: Date.now() };
	}
	function empiler() {
		const i = instantane();
		const haut = etat.pile[etat.pose];
		if (haut && etat.pose === etat.pile.length - 1 && etat.pose > 0 && i.t - haut.t < 700) {
			etat.pile[etat.pose] = i;   // une frappe continue ne fait qu'un pas
		} else {
			etat.pile.length = etat.pose + 1;
			etat.pile.push(i);
			if (etat.pile.length > 100) { etat.pile.shift(); }
			etat.pose = etat.pile.length - 1;
		}
		majPile();
	}
	function majPile() {
		$('stAnnuler').disabled = etat.lecture || etat.pose < 1;
		$('stRetablir').disabled = etat.lecture || etat.pose >= etat.pile.length - 1;
	}
	function allerA(pose) {
		if (etat.lecture || pose < 0 || pose >= etat.pile.length) { return; }
		const i = etat.pile[pose];
		etat.pose = pose;
		etat.chargement = true;   // reconstruire le formulaire n'est pas une modification de plus
		etat.donnees = clone(i.donnees);
		etat.miseEnPage = i.miseEnPage && !Array.isArray(i.miseEnPage) ? clone(i.miseEnPage) : {};
		etat.blocs = clone(i.blocs || []);
		if (etat.preset.formats[i.format]) { etat.format = i.format; }
		Object.keys(i.images).forEach((id) => {
			const s = i.images[id], x = etat.images[id];
			if (x === s.ref) { Object.assign(x, { alt: s.alt, fondre: s.fondre, ajustement: s.ajustement, cadrage: clone(s.cadrage), reglages: clone(s.reglages) }); }
		});
		construireFormulaire();
		formats();
		planifierRendu();
		majLegende();   // la legende suit les champs retrouves avant de fixer ce qui est « vu »
		etat.sigVue = signature();   // ce n'est pas une nouvelle modification a empiler, mais elle s'enregistre
		etat.chargement = false;
		planifierSauvegarde();
		majPile();
		majVisuelUI();
	}

	/* ------------------------------------------------ la legende pour la publication */
	function genererLegende() {
		const p = etat.preset, d = etat.donnees;
		const net = (t) => String(t == null ? '' : t).replace(/\*\*/g, '').replace(/\s+/g, ' ').trim();
		const v = (id) => net(d[id]);
		const jour = (iso) => {
			const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
			return m ? new Date(+m[1], +m[2] - 1, +m[3]).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '';
		};
		const liste = (id) => (Array.isArray(d[id]) ? d[id] : []).map(net).filter(Boolean);
		const avec = (etiquette, ids) => { const t = ids.map(v).filter(Boolean).join(', '); return t ? etiquette + ' : ' + t : ''; };
		const quand = (idDate, idHeure) => { const j = jour(d[idDate]); return j ? 'Le ' + j + (v(idHeure) ? ' à ' + v(idHeure) : '') : ''; };
		const listeAvec = (etiquette, id) => { const l = liste(id); return l.length ? etiquette + ' : ' + l.join(', ') : ''; };
		const modeles = {
			evt: () => [v('titre'), v('sousTitre'), quand('date', 'heure'), avec('Lieu', ['lieu', 'lieuPrecision']), listeAvec('Avec', 'intervenants'), avec('Modération', ['moderation']), avec('Accès', ['acces']), avec('En direct', ['diffusion'])],
			ann: () => [v('titre'), v('texte'), avec('Quand', ['quandJour', 'quandMois']), avec('Lieu', ['lieu', 'lieuPrecision']), avec('Contact', ['contactNom', 'contactCoord']), v('retenir')],
			com: () => [avec('Communiqué', ['reference']) + (v('service') ? ' — ' + v('service') : ''), v('titre'), v('paragraphe1'), v('paragraphe2'), v('paragraphe3'), avec('Signé', ['signataireNom', 'signataireFonction']), avec('Renseignements', ['renseignements'])],
			fel: () => ['Félicitations à ' + [v('prenom'), v('nom')].filter(Boolean).join(' ') + ' !', v('motif'), v('message'), avec('Filière', ['filiere'])],
			ins: () => [v('titre'), avec('Année universitaire', ['anneeUniversitaire']), d.dateLimite ? 'Inscriptions jusqu\'au ' + jour(d.dateLimite) : '', listeAvec('Filières', 'filieres'), listeAvec('Pièces à fournir', 'pieces'), v('ouSinscrire'), avec('Horaires', ['horaires']), avec('Inscription en ligne', ['qrUrl'])],
			rec: () => ['Recrutement : ' + v('poste'), avec('Service', ['service', 'departement']), avec('Contrat', ['contrat']), avec('Lieu de travail', ['lieuTravail']), listeAvec('Profil', 'profil'), d.dateLimite ? 'Candidatures jusqu\'au ' + jour(d.dateLimite) : '', v('piecesPostuler'), avec('À envoyer à', ['email'])],
		};
		const lignes = (modeles[p.id] ? modeles[p.id]() : [v('titre')]).filter(Boolean);
		const images = clesImages().map((id) => net(etat.images[id].alt)).filter(Boolean);
		if (images.length) { lignes.push('Image : ' + images.join(' · ')); }
		lignes.push('Université Adventiste Zurcher · zurcher.edu.mg');
		return lignes.join('\n\n');
	}
	function majLegende(force) {
		if (!etat.preset || (etat.legendeManuelle && !force)) { return; }
		if (force) { etat.legendeManuelle = false; }
		etat.legende = genererLegende();
		const z = $('stLegende');
		if (z && z.value !== etat.legende) { z.value = etat.legende; }
		$('stLegendeRefaire').hidden = true;
	}

	/* ------------------------------------------------ l'etat du visuel a l'ecran */
	function majVisuelUI() {
		if (!etat.preset) { return; }
		const v = etat.visuel;
		const statut = v ? v.statut : 'brouillon';
		const badge = $('stStatut');
		badge.textContent = STATUTS[statut];
		badge.className = 'st-badge st-badge--' + statut;
		const sauve = $('stSauve');
		let texte;
		if (etat.serveur !== true) { texte = 'Brouillon gardé dans ce navigateur'; }
		else if (etat.messageSauve) { texte = etat.messageSauve; }
		else if (etat.lecture) { texte = 'Lecture seule'; }
		else if (modifie()) { texte = 'Modifications non enregistrées…'; }
		else if (v && etat.sauveA) { texte = 'Enregistré à ' + etat.sauveA.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }); }
		else if (v) { texte = 'Enregistré'; }
		else { texte = 'Pas encore enregistré'; }
		sauve.textContent = texte;
		sauve.classList.toggle('st-sauve--erreur', !!etat.erreurSauve);

		/* le nom du visuel, comme celui d'un fichier Figma */
		$('stTitre').textContent = titreVisuel();
		$('stTitre').title = titreVisuel();
		const serveur = etat.serveur === true;
		['stOuvrir', 'stNouveau'].forEach((id) => { $(id).disabled = !serveur; });
		document.querySelectorAll('#stOutils [data-outil]').forEach((b) => { b.disabled = etat.lecture && b.dataset.outil !== 'deplacer' && b.dataset.outil !== 'main'; });
		if (etat.lecture && CREATION[etat.outil]) { choisirOutil('deplacer'); }
		$('stVider').disabled = etat.lecture;
		$('stAdmin').hidden = !(etat.roles && etat.roles.admin);
		$('stDupliquer').disabled = !serveur || !v;
		$('stHistorique').disabled = !serveur || !v;
		majPile();
		app.classList.toggle('st--lecture', etat.lecture);
		$('stForm').inert = etat.lecture;
		$('stLegende').readOnly = etat.lecture;
		const lecture = $('stLecture');
		lecture.hidden = !etat.lecture || !v;
		if (v && etat.lecture) { lecture.textContent = 'Ce visuel est à ' + v.auteur + ' : vous pouvez le consulter. Pour le modifier, dupliquez-le (la copie est à vous).'; }

		/* la validation */
		const d = v ? v.droits : null;
		const obligatoire = etat.obligatoire.indexOf(etat.preset.id) !== -1;
		let aide;
		if (!serveur) { aide = 'La validation demande les visuels enregistrés, qui ne sont pas disponibles ici.'; }
		else if (statut === 'a_valider') { aide = 'En attente d\'un validateur. Toute modification remet le visuel en brouillon.'; }
		else if (statut === 'valide') { aide = 'Validé' + (v.valideur ? ' par ' + v.valideur : '') + (v.valideLe ? ' le ' + dateCourte(v.valideLe) : '') + '. Toute modification remet le visuel en brouillon.'; }
		else if (statut === 'refuse') { aide = 'Refusé' + (v.valideur ? ' par ' + v.valideur : '') + (v.motifRefus ? ' : « ' + v.motifRefus + ' »' : '') + '. Corrigez, puis soumettez de nouveau.'; }
		else if (obligatoire) { aide = 'Ce type de visuel s\'exporte une fois validé. Soumettez-le quand il est prêt.'; }
		else { aide = 'Facultatif pour ce type de visuel : soumettez-le si le service communication doit le relire.'; }
		$('stValidationTexte').textContent = aide;
		$('stSoumettre').hidden = !serveur || etat.lecture || !(statut === 'brouillon' || statut === 'refuse');
		$('stValider').hidden = !(d && d.decider);
		$('stRefuser').hidden = !(d && d.decider);
		$('stSoumettre').disabled = etat.occupe || !!(v && v.statut === 'a_valider');
		$('stLegendeRefaire').hidden = !etat.legendeManuelle;
		$('stJournalOuvrir').hidden = !(etat.roles && etat.roles.validateur);
		const nb = etat.aValider || 0;
		$('stAValider').hidden = !(etat.roles && etat.roles.validateur && nb > 0);
		$('stAValider').textContent = String(nb);
		$('stAValider').title = nb + ' visuel' + (nb > 1 ? 's' : '') + ' à valider';
		$('stMenuPoint').hidden = $('stAValider').hidden;
		/* un point sur « Publication » quand elle attend quelque chose : soumettre (validation obligatoire) ou decider */
		$('stPointPublication').hidden = !serveur || !((obligatoire && !etat.lecture && (statut === 'brouillon' || statut === 'refuse')) || (d && d.decider && statut === 'a_valider'));
	}
	/* Le nom du visuel, comme le serveur le donne a la liste (studioVisuelTitre) */
	function titreVisuel() {
		const src = String(etat.preset.titreDepuis || '');
		const d = etat.donnees;
		const t = src.indexOf('{') !== -1
			? src.replace(/\{([a-zA-Z0-9]+)\}/g, (m, k) => (typeof d[k] === 'string' || typeof d[k] === 'number' ? String(d[k]) : ''))
			: String(d[src] == null || typeof d[src] === 'object' ? '' : d[src]);
		const net = t.replace(/\*\*/g, '').replace(/\s+/g, ' ').trim();
		return net || 'Sans titre';
	}

	/* ------------------------------------------------ ouvrir, nouveau, dupliquer, supprimer */
	async function liberer() {   // ce qui est en cours part avant de changer de visuel
		if (etat.serveur === true && !etat.lecture && modifie()) { await enregistrer('auto'); }
	}
	async function ouvrirVisuel(id) {
		await liberer();
		try {
			const j = await api({ action: 'ouvrir', id: String(id) });
			remplirTypes(j.visuel.preset);
			await ouvrirPreset(j.visuel.preset, j.visuel);
			try { history.replaceState(null, '', '#v=' + j.visuel.id); } catch (e) { /* adresse inchangee */ }
			return true;
		} catch (e) {
			annoncer(e.message, 'erreur');
			return false;
		}
	}
	async function nouveauVisuel(presetId) {
		await liberer();
		try { history.replaceState(null, '', location.pathname + location.search); } catch (e) { /* adresse inchangee */ }
		await ouvrirPreset(presetId || etat.preset.id, null);
	}
	async function dupliquer() {
		if (!etat.visuel) { return; }
		await liberer();
		try {
			const j = await post('dupliquer', { id: etat.visuel.id });
			await ouvrirPreset(j.visuel.preset, j.visuel);
			try { history.replaceState(null, '', '#v=' + j.visuel.id); } catch (e) { /* adresse inchangee */ }
			annoncer('Copie créée : elle est à vous, l\'original ne change pas.', 'ok');
		} catch (e) {
			annoncer(e.message, 'erreur');
		}
	}
	async function supprimer(id) {
		if (!window.confirm('Supprimer ce visuel ? Il disparaît de la liste (le journal des exports le garde).')) { return false; }
		try {
			await post('supprimer', { id });
			if (etat.visuel && etat.visuel.id === id) { await nouveauVisuel(); }
			return true;
		} catch (e) {
			annoncer(e.message, 'erreur');
			return false;
		}
	}

	/* =========================================================================
	   Edition directe sur le visuel (jalon 1 de « modifier comme dans Canva »)
	   Un clic sur un texte de l'apercu ouvre, au meme endroit, une zone de saisie
	   qui reprend la police, la taille et la couleur du texte : on ecrit sur le
	   visuel. Le formulaire de gauche suit (meme champ, meme compteur) et reste
	   pour les listes, les dates, les codes et les images.
	   ====================================================================== */
	const EDITABLES_SUR_VISUEL = ['texte', 'texteLong', 'titre', 'motCle', 'heure'];
	let edition = null;   // { id, cible, zone, compteur }
	function fermerEdition() {
		if (!edition) { return; }
		edition.zone.remove();
		edition.compteur.remove();
		edition = null;
		app.classList.remove('st--ecriture');
	}
	/* Ce qu'on voit en ecrivant, c'est le rendu lui-meme (demande du 26/10/2026 : ni bord bleu ni fond blanc) :
	   la zone de saisie est invisible (texte transparent, seul le curseur se voit), posee exactement sur la
	   boite du texte avec la meme police, et le visuel se redessine a chaque frappe. */
	function placerEdition() {
		if (!edition) { return; }
		const cible = $('stVisuel').querySelector(edition.selecteur || '[data-champ="' + edition.id + '"]');
		if (!cible) { fermerEdition(); return; }
		app.classList.add('st--ecriture');
		const cadre = $('stCadre').getBoundingClientRect(), r = cible.getBoundingClientRect();
		const cs = getComputedStyle(cible), k = etat.echelle || 1;
		const px = (v) => (parseFloat(v) || 0) * k;
		const z = edition.zone;
		z.style.left = (r.left - cadre.left) + 'px';
		z.style.top = (r.top - cadre.top) + 'px';
		z.style.width = Math.max(r.width, 24 * k) + 'px';
		z.style.height = Math.max(r.height, parseFloat(cs.fontSize) * k * 1.2) + 'px';
		z.style.padding = [['Top'], ['Right'], ['Bottom'], ['Left']].map(([c]) => (px(cs['padding' + c]) + px(cs['border' + c + 'Width'])) + 'px').join(' ');
		z.style.fontFamily = cs.fontFamily;
		z.style.fontSize = px(cs.fontSize) + 'px';
		z.style.fontWeight = cs.fontWeight;
		z.style.fontStretch = cs.fontStretch;
		z.style.fontStyle = cs.fontStyle;
		z.style.fontFeatureSettings = cs.fontFeatureSettings;
		z.style.fontVariantNumeric = cs.fontVariantNumeric;
		z.style.fontKerning = cs.fontKerning;
		z.style.lineHeight = cs.lineHeight === 'normal' ? 'normal' : px(cs.lineHeight) + 'px';
		z.style.letterSpacing = parseFloat(cs.letterSpacing) ? px(cs.letterSpacing) + 'px' : 'normal';
		z.style.wordSpacing = parseFloat(cs.wordSpacing) ? px(cs.wordSpacing) + 'px' : 'normal';
		z.style.textAlign = cs.textAlign;
		z.style.textTransform = cs.textTransform;
		z.style.textIndent = px(cs.textIndent) + 'px';
		z.style.overflowWrap = cs.overflowWrap;
		z.style.hyphens = cs.hyphens;
		z.style.caretColor = cs.color;   // le texte est transparent : le curseur prend la couleur du texte rendu
		if (!z.dataset.pret) {   // ni correcteur ni extension d'ecriture sur le visuel
			z.dataset.pret = '1';
			z.spellcheck = false;
			['data-gramm', 'data-gramm_editor', 'data-enable-grammarly', 'data-lt-active'].forEach((a) => z.setAttribute(a, 'false'));
		}
		edition.compteur.style.left = z.style.left;
		edition.compteur.style.top = (parseFloat(z.style.top) + parseFloat(z.style.height) + 6) + 'px';
	}
	function editerSurVisuel(id) {
		const c = (etat.preset.champs || []).find((x) => x.id === id);
		const panneau = document.querySelector('.st-champ[data-champ="' + id + '"] .st-saisie');
		if (!c || !panneau || etat.lecture || EDITABLES_SUR_VISUEL.indexOf(c.type) === -1 || serviceStrict(c)) { return false; }
		if (edition && edition.id === id) { edition.zone.focus(); return true; }
		fermerEdition();
		const zone = el('textarea', 'st-edition');
		zone.value = panneau.value;
		zone.maxLength = c.max || 500;
		zone.setAttribute('aria-label', c.libelle);
		zone.spellcheck = true;
		const compteur = el('span', 'st-edition__compteur');
		const maj = () => { compteur.textContent = c.libelle + ' · ' + zone.value.length + ' / ' + zone.maxLength; compteur.classList.toggle('st-compteur--plein', zone.value.length >= zone.maxLength); };
		edition = { id, zone, compteur };
		maj();
		$('stCadre').append(zone, compteur);
		placerEdition();
		zone.addEventListener('input', () => {
			panneau.value = zone.value;
			panneau.dispatchEvent(new Event('input', { bubbles: true }));   // le champ de gauche fait le travail (compteur, rendu, enregistrement)
			maj();
		});
		zone.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); fermerEdition(); }   // la selection reste
			else if (e.key === 'Enter' && c.type !== 'texteLong') { e.preventDefault(); fermerEdition(); }   // un titre tient sur un paragraphe
			else if (e.key === 'Tab') { fermerEdition(); }
		});
		zone.addEventListener('blur', () => setTimeout(() => { if (edition && edition.zone === zone) { fermerEdition(); } }, 120));
		focaliser(zone);
		zone.setSelectionRange(zone.value.length, zone.value.length);
		return true;
	}

	/* =========================================================================
	   Selection et proprietes (comme dans Figma)
	   Un clic sur un element du visuel le choisit : un cadre l'entoure, sa taille
	   dessous, et le panneau de droite (Design) montre ses proprietes : position et
	   taille (X, Y, L, H), texte, couleurs, son champ. Un double-clic ou Entree ecrit
	   le texte sur le visuel ; les autres elements (date, liste, code, adresse, image)
	   montrent dans le panneau leur propre champ, celui-la meme de l'onglet « Champs »,
	   qui reste pour le clavier et pour les champs absents du visuel affiche.
	   ====================================================================== */
	let sel = null;   // { id, genre: 'champ' | 'image' | 'libre' | 'etiquette', hote: { bloc, parent, suivant } | null }
	const SELECTEUR = '[data-champ], [data-image], [data-libre], [data-etiquette]';
	const elementDe = (id, genre) => $('stVisuel').querySelector(genre === 'etiquette' ? '[data-etiquette="' + CSS.escape(id) + '"]' : (genre === 'libre' ? '[data-libre="' + id + '"]' : (genre === 'image' ? '[data-image="' + id + '"]' : '[data-champ="' + id + '"]')));
	const genreDe = (id) => (/^b\d+$/.test(String(id)) ? 'libre' : ((etat.preset.images || []).some((i) => i.id === id) ? 'image' : 'champ'));
	/* l'element choisi depuis ce qu'on a touche sur le visuel */
	function cibleDe(e) {
		if (e.dataset.libre) { return { id: e.dataset.libre, genre: 'libre' }; }
		if (e.dataset.etiquette && !e.dataset.champ) { return { id: e.dataset.etiquette, genre: 'etiquette' }; }
		if (e.dataset.champ) { return { id: e.dataset.champ, genre: 'champ' }; }
		return { id: e.dataset.image, genre: 'image' };
	}
	const ICONES_CHAMP = { titre: 'bi-type-h1', texte: 'bi-fonts', texteLong: 'bi-text-paragraph', motCle: 'bi-star', date: 'bi-calendar3', heure: 'bi-clock', liste: 'bi-list-ul', url: 'bi-qr-code', sousCode: 'bi-hash' };
	function iconeDe(genre, id) {
		if (genre === 'image') { return 'bi-image'; }
		if (genre === 'etiquette') { return 'bi-tag'; }
		if (genre === 'libre') {
			const b = blocDe(id);
			if (!b) { return 'bi-square'; }
			if (b.type === 'forme') { return b.forme === 'cercle' ? 'bi-circle' : (libelleBloc(b) === 'Ligne' ? 'bi-slash-lg' : 'bi-square'); }
			return ICONES_BLOC[b.type];
		}
		const c = (etat.preset.champs || []).find((x) => x.id === id);
		return (c && ICONES_CHAMP[c.type]) || 'bi-fonts';
	}
	function flottant() { return $('stFlottant'); }
	function rendreBloc() {   // le champ prete au panneau de droite retourne dans l'onglet Champs, a sa place
		if (!sel || !sel.hote) { return; }
		const h = sel.hote;
		if (h.parent.isConnected) { h.parent.insertBefore(h.bloc, h.suivant && h.suivant.parentNode === h.parent ? h.suivant : null); }
		sel.hote = null;
	}
	function deselectionner() {
		fermerEdition();
		rendreBloc();
		sel = null;
		const f = $('stFlottant');
		if (f) { f.hidden = true; f.replaceChildren(); }
		const doc = $('stDocument');
		if (doc) { doc.hidden = false; }
		majCalques();
		const o = $('stScene').querySelector('.st-selection');
		if (o) { o.remove(); }
		effacerGuides();
	}
	/* La boite d'un element dans son visuel (en px du visuel) : X, Y, L, H de Figma */
	function geoElement(e) {
		const racine = e.closest('.uaz-visuel') || e;
		const rr = racine.getBoundingClientRect(), r = e.getBoundingClientRect(), k = etat.echelle || 1;
		return { x: (r.left - rr.left) / k, y: (r.top - rr.top) / k, l: e.offsetWidth || r.width / k, h: e.offsetHeight || r.height / k };
	}
	/* un cadre de la scene (selection, survol) pose sur un element du visuel */
	function cadrerSur(o, cible) {
		const scene = $('stScene').getBoundingClientRect(), r = cible.getBoundingClientRect();
		o.style.left = (r.left - scene.left) + 'px';
		o.style.top = (r.top - scene.top) + 'px';
		o.style.width = r.width + 'px';
		o.style.height = r.height + 'px';
	}
	function placerSelection() {
		placerEdition();
		majCalques();
		if (!sel) { return; }
		const cible = elementDe(sel.id, sel.genre);
		if (!cible) { deselectionner(); return; }
		let o = $('stScene').querySelector('.st-selection');
		if (!o) { o = el('div', 'st-selection'); $('stScene').appendChild(o); poignees(o); }
		cadrerSur(o, cible);
		let t = o.querySelector('.st-selection__taille');
		if (!t) { t = el('span', 'st-selection__taille'); o.appendChild(t); }
		const g = geoElement(cible);
		t.textContent = Math.round(g.l) + ' × ' + Math.round(g.h);
		const f = $('stFlottant');
		if (f) { const b = f.querySelector('[data-role="remettre"]'); if (b) { b.hidden = !mepFormat()[sel.id]; } }
		majPosition();
	}
	/* le survol : un cadre fin autour de ce que la souris montre (sur le visuel ou dans les calques) */
	function survoler(cible) {
		const scene = $('stScene');
		let o = scene ? scene.querySelector('.st-survol') : null;
		if (!cible || (sel && cible === elementDe(sel.id, sel.genre))) { if (o) { o.remove(); } return; }
		if (!o) { o = el('div', 'st-survol'); scene.appendChild(o); }
		cadrerSur(o, cible);
	}
	function boutonOutil(libelle, icone, titre, action) {
		const b = el('button', 'st-outil');
		b.type = 'button';
		b.title = titre || libelle;
		b.innerHTML = '<i class="bi ' + icone + '" aria-hidden="true"></i>';
		if (libelle) { b.appendChild(document.createTextNode(' ' + libelle)); } else { b.setAttribute('aria-label', titre); }
		b.addEventListener('mousedown', (e) => e.preventDefault());   // la saisie garde le focus (et sa selection de texte)
		b.addEventListener('click', action);
		return b;
	}
	/* « Mot en or » : le texte choisi dans le titre devient le mot-cle du champ qui lui est lie */
	function outilsTexte(id, corps) {
		const motCle = (etat.preset.champs || []).find((c) => c.type === 'motCle' && c.de === id);
		const panneau = (cid) => document.querySelector('.st-champ[data-champ="' + cid + '"] .st-saisie');
		const poser = (cid, v) => { const p = panneau(cid); if (p) { p.value = v; p.dispatchEvent(new Event('input', { bubbles: true })); } };
		if (motCle) {
			const or = boutonOutil('Mot en or', 'bi-palette', 'Sélectionnez un mot dans le texte, puis cliquez ici : il passe en or.', () => {
				const z = edition && edition.zone;
				const choix = z ? z.value.slice(z.selectionStart, z.selectionEnd).trim() : '';
				if (!choix) { annoncer('Double-cliquez sur le texte, sélectionnez le mot (ou les mots) à mettre en or, puis « Mot en or ».'); return; }
				poser(motCle.id, choix.slice(0, motCle.max || 40));
				annoncer('En or : « ' + choix + ' ».', 'ok');
				majOutils();
			});
			or.dataset.role = 'or';
			const sans = boutonOutil('Sans or', 'bi-x-circle', 'Retire le mot en or.', () => { poser(motCle.id, ''); annoncer('Plus de mot en or.', 'ok'); majOutils(); });
			sans.dataset.role = 'sans-or';
			corps.append(or, sans);
		}
		corps.appendChild(boutonOutil('Effacer', 'bi-eraser', 'Vide ce texte.', () => {
			poser(id, '');
			if (edition && edition.id === id) { edition.zone.value = ''; edition.zone.focus(); }
			majOutils();
		}));
	}
	function majOutils() {
		const f = $('stFlottant');
		if (!sel || !f) { return; }
		const motCle = (etat.preset.champs || []).find((c) => c.type === 'motCle' && c.de === sel.id);
		const sans = f.querySelector('[data-role="sans-or"]');
		const or = f.querySelector('[data-role="or"]');
		const v = motCle ? String(etat.donnees[motCle.id] || '') : '';
		if (sans) { sans.hidden = !v; }
		if (or) { or.lastChild.textContent = v ? ' Mot en or : « ' + (v.length > 18 ? v.slice(0, 17) + '…' : v) + ' »' : ' Mot en or'; }
		placerSelection();
	}

	/* ------------------------------------------------ le panneau des proprietes */
	function section(titre, classe) {
		const s = el('section', 'st-section' + (classe ? ' ' + classe : ''));
		const h = el('h3', 'st-section__titre');
		h.appendChild(el('span', '', titre));
		s.appendChild(h);
		return s;
	}
	function entete(icone, nom) {
		const tete = el('div', 'st-flottant__tete');
		const i = el('i', 'bi ' + icone);
		i.setAttribute('aria-hidden', 'true');
		tete.append(i, el('b', '', nom));
		const fermer = el('button', 'st-icone st-icone--petit');
		fermer.type = 'button';
		fermer.title = 'Désélectionner (Échap)';
		fermer.setAttribute('aria-label', 'Désélectionner');
		fermer.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
		fermer.addEventListener('click', deselectionner);
		tete.appendChild(fermer);
		return tete;
	}
	function ouvrirProprietes(tete, sections) {
		const f = flottant();
		f.replaceChildren(tete, ...sections);
		f.hidden = false;
		$('stDocument').hidden = true;
		ongletDroit('design');
		if (f.parentElement) { f.parentElement.scrollTop = 0; }
	}
	/* une case reglable (X, Y, L, H, taille) : sa lettre se glisse (Maj : par 10), la case se tape, fleches haut et bas */
	function caseReglable(o) {
		const boite = el('label', 'st-num' + (o.seul ? ' st-num--seul' : ''));
		const lettre = el('span', 'st-num__lettre', o.lettre);
		lettre.setAttribute('aria-hidden', 'true');
		const saisie = el('input', 'st-num__saisie');
		saisie.type = 'text';
		saisie.inputMode = 'decimal';
		saisie.spellcheck = false;
		saisie.autocomplete = 'off';
		Object.keys(o.donnees || {}).forEach((k) => { saisie.dataset[k] = o.donnees[k]; });
		saisie.setAttribute('aria-label', o.libelle);
		saisie.readOnly = !o.actif;
		boite.classList.toggle('st-num--inactif', !o.actif);
		boite.title = o.actif ? o.libelle + ' : tapez une valeur, ou glissez la lettre' : (o.raison || '');
		boite.append(lettre, saisie);
		if (o.unite) { boite.appendChild(el('span', 'st-num__unite', o.unite)); }
		const lire = () => parseFloat(String(saisie.value).replace(',', '.').replace(/[^0-9.+-]/g, ''));
		saisie.addEventListener('focus', () => saisie.select());
		saisie.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') { e.preventDefault(); if (o.actif && isFinite(lire())) { o.appliquer(lire(), true); } saisie.select(); }
			else if (e.key === 'Escape') { e.preventDefault(); majPosition(true); saisie.blur(); }
			else if ((e.key === 'ArrowUp' || e.key === 'ArrowDown') && o.actif) {
				e.preventDefault();
				const v = Math.round((lire() || 0) + (e.key === 'ArrowUp' ? 1 : -1) * (e.shiftKey ? 10 : 1));
				saisie.value = String(v);
				o.appliquer(v, true);
			}
		});
		saisie.addEventListener('change', () => { if (o.actif && isFinite(lire())) { o.appliquer(lire(), true); } });
		lettre.addEventListener('pointerdown', (e) => {
			if (!o.actif || e.button !== 0) { return; }
			e.preventDefault();
			const v0 = lire() || 0, x0 = e.clientX, debut = o.debut ? o.debut() : null;
			lettre.setPointerCapture(e.pointerId);
			let v = null;
			const bouger = (ev) => { v = Math.round(v0 + (ev.clientX - x0) * (ev.shiftKey ? 10 : 1)); saisie.value = String(v); o.appliquer(v, false, debut); };
			const fin = () => {
				lettre.removeEventListener('pointermove', bouger);
				lettre.removeEventListener('pointerup', fin);
				lettre.removeEventListener('pointercancel', fin);
				if (v !== null) { o.appliquer(v, true, debut); }
			};
			lettre.addEventListener('pointermove', bouger);
			lettre.addEventListener('pointerup', fin);
			lettre.addEventListener('pointercancel', fin);
		});
		return boite;
	}
	/* la position : celle du bloc, ou le decalage d'un element de la charte (dx, dy) */
	function etatPosition() {
		if (!sel) { return null; }
		const e = elementDe(sel.id, sel.genre);
		if (!e) { return null; }
		const libre = sel.genre === 'libre';
		return { id: sel.id, libre, a0: libre ? geoLibre(sel.id) : mepDe(sel.id), g0: geoElement(e) };
	}
	function versPosition(p, cle, v) {
		const a = Object.assign({}, p.a0);
		if (p.libre) { a[cle] = cle === 'l' ? Math.max(4, v) : (cle === 'h' ? Math.max(2, v) : v); return a; }
		if (cle === 'x') { a.dx = (p.a0.dx || 0) + v - p.g0.x; } else if (cle === 'y') { a.dy = (p.a0.dy || 0) + v - p.g0.y; } else { a[cle] = Math.max(20, v); }
		return a;
	}
	const NOMS_POSITION = { x: 'Position horizontale (X), en pixels', y: 'Position verticale (Y), en pixels', l: 'Largeur (L), en pixels', h: 'Hauteur (H), en pixels' };
	function positionModifiable(cle) {
		if (!sel || etat.lecture || sel.genre === 'etiquette') { return false; }
		if (cle === 'h' && (sel.genre === 'champ' || (sel.genre === 'libre' && blocDe(sel.id) && blocDe(sel.id).type === 'texte'))) { return false; }
		return true;
	}
	function sectionPosition() {
		const s = section('Position et taille');
		if (sel.genre === 'champ' || sel.genre === 'image') {
			const id = sel.id;
			const rst = el('button', 'st-lien', 'Remettre à sa place');
			rst.type = 'button';
			rst.dataset.role = 'remettre';
			rst.title = 'Sa place et sa taille d\'origine, dans la grille de la charte';
			rst.hidden = !mepFormat()[id];
			rst.addEventListener('click', () => { remettre(id); });
			s.firstChild.appendChild(rst);
		}
		const grille = el('div', 'st-xywh');
		['x', 'y', 'l', 'h'].forEach((cle) => {
			const actif = positionModifiable(cle);
			grille.appendChild(caseReglable({
				lettre: { x: 'X', y: 'Y', l: 'L', h: 'H' }[cle], libelle: NOMS_POSITION[cle], actif, donnees: { position: cle },
				raison: sel.genre === 'etiquette' ? 'Un intitulé garde sa place dans la grille' : 'La hauteur d\'un texte suit le texte : changez sa largeur ou sa taille',
				debut: etatPosition,
				appliquer: (v, final, debut) => {
					const p = debut || etatPosition();
					if (!p) { return; }
					const a = versPosition(p, cle, v);
					poserMep(p.id, a);
					if (final) { garderMep(p.id, a); }
				},
			}));
		});
		s.appendChild(grille);
		if (sel.genre === 'etiquette') { s.appendChild(el('p', 'st-aide', 'Un intitulé garde sa place dans la grille : il se réécrit ou se masque.')); }
		return s;
	}
	/* les valeurs des cases, apres un geste ou un rendu (pas celle qu'on est en train de taper) */
	function majPosition(tout) {
		const f = $('stFlottant');
		if (!sel || !f || f.hidden) { return; }
		const e = elementDe(sel.id, sel.genre);
		if (!e) { return; }
		const g = geoElement(e);
		f.querySelectorAll('[data-position]').forEach((s) => { if (tout || document.activeElement !== s) { s.value = String(Math.round(g[s.dataset.position])); } });
		const k = f.querySelector('[data-role="echelle"]');
		if (k && (tout || document.activeElement !== k)) { k.value = String(Math.round((mepDe(sel.id).k || 1) * 100)); }
	}
	const NOMS_GRAISSE = { 100: 'Fin', 200: 'Très léger', 300: 'Léger', 400: 'Normal', 500: 'Moyen', 600: 'Demi-gras', 700: 'Gras', 800: 'Extra-gras', 900: 'Noir' };
	/* un texte de la charte : l'ecrire, le mot en or, sa taille (en % de la charte) */
	function sectionTexteChamp(id) {
		const s = section('Texte');
		const corps = el('div', 'st-flottant__corps');
		corps.appendChild(boutonOutil('Écrire', 'bi-cursor-text', 'Écrire ce texte sur le visuel (Entrée, ou double-clic)', () => editerSurVisuel(id)));
		outilsTexte(id, corps);
		s.appendChild(corps);
		const cible = elementDe(id, 'champ');
		if (cible) {
			const cs = getComputedStyle(cible);
			const police = String(cs.fontFamily || '').split(',')[0].replace(/["']/g, '').trim();
			s.appendChild(el('p', 'st-typo', police + ' · ' + (NOMS_GRAISSE[Math.round(Number(cs.fontWeight) / 100) * 100] || cs.fontWeight) + ' · ' + Math.round(parseFloat(cs.fontSize)) + ' px'));
		}
		s.appendChild(caseReglable({
			lettre: 'T', libelle: 'Taille du texte, en pourcentage de celle de la charte', unite: '%', seul: true, actif: !etat.lecture, donnees: { role: 'echelle' },
			appliquer: (v, final) => {
				const a = mepDe(id);
				a.k = Math.max(30, Math.min(400, v)) / 100;
				poserMep(id, a);
				if (final) { garderMep(id, a); }
			},
		}));
		return s;
	}
	/* Choisir un element de la charte (texte, image, autre champ) ; « editer » : l'ecrire tout de suite */
	function selectionner(id, genre, editer) {
		if (etat.lecture) { return false; }
		if (genre === 'libre') { return selectionnerLibre(id, editer); }
		if (genre === 'etiquette') { return selectionnerEtiquette(id, editer); }
		if (sel && sel.id === id && sel.genre === genre) {
			if (editer) { editerSelection(); }
			return true;
		}
		const cible = elementDe(id, genre);
		const c = genre === 'champ' ? (etat.preset.champs || []).find((x) => x.id === id) : (etat.preset.images || []).find((x) => x.id === id);
		const bloc = document.querySelector(genre === 'image' ? '.st-champ[data-image="' + id + '"]' : '.st-champ[data-champ="' + id + '"]');
		if (!cible || !c || !bloc) { return false; }
		deselectionner();
		sel = { id, genre, hote: null };
		const texte = genre === 'champ' && EDITABLES_SUR_VISUEL.indexOf(c.type) !== -1 && !serviceStrict(c);
		const sections = [sectionPosition()];
		if (texte) {
			sections.push(sectionTexteChamp(id));
		} else {
			const s = section(genre === 'image' ? 'Image' : 'Contenu', 'st-flottant__contenu');
			sel.hote = { bloc, parent: bloc.parentNode, suivant: bloc.nextSibling };
			s.appendChild(bloc);
			bloc.classList.remove('st-champ--ailleurs');
			sections.push(s);
		}
		ouvrirProprietes(entete(iconeDe(genre, id), c.libelle), sections);
		if (texte) { majOutils(); }
		placerSelection();
		if (editer) { editerSelection(); }
		return true;
	}
	/* Entree, double-clic : ecrire le texte choisi sur le visuel, ou aller a son champ */
	function editerSelection() {
		if (!sel || etat.lecture) { return false; }
		if (sel.genre === 'libre') { const b = blocDe(sel.id); return !!b && b.type === 'texte' && editerLibre(sel.id); }
		if (sel.genre === 'etiquette') { return editerEtiquette(sel.id); }
		if (sel.hote) {
			const s = sel.hote.bloc.querySelector('input:not([type=file]), textarea, select') || sel.hote.bloc.querySelector('label.st-bouton');
			if (s) { focaliser(s, true); return true; }
			return false;
		}
		return editerSurVisuel(sel.id);
	}
	/* depuis le visuel ou un calque : choisir (et ecrire, au double-clic) */
	function choisirElement(cible, editer) {
		const c = cibleDe(cible);
		if (selectionner(c.id, c.genre, editer)) { return true; }
		if (c.genre !== 'etiquette' && c.genre !== 'libre') { ouvrirChamp(c.id); }
		return false;
	}
	/* =========================================================================
	   Mise en page libre (jalon 3 de « modifier comme dans Canva »)
	   Un element se deplace (glisser, poignee de deplacement ou fleches) et se
	   redimensionne (poignees) par-dessus la grille ; il s'aimante aux marges de
	   40 px, au centre du visuel et aux bords des autres elements (Alt : sans
	   aimant). Le decalage est garde par format (etat.miseEnPage) avec le visuel,
	   et le moteur le rejoue a l'identique a l'export.
	   - texte : largeur (E, O) et echelle du texte (coin bas droit) ;
	   - image : largeur, hauteur et coins.
	   ====================================================================== */
	const mepFormat = (creer) => { if (creer && !etat.miseEnPage[etat.format]) { etat.miseEnPage[etat.format] = {}; } return etat.miseEnPage[etat.format] || {}; };
	const mepDe = (id) => Object.assign({}, mepFormat()[id] || {});
	const arrondi1 = (v) => Math.round(v * 10) / 10;
	const nbDeplaces = () => Object.keys(mepFormat()).length;
	function validerMep(a) {
		const v = StudioRendu.miseEnPageValide(a);
		Object.keys(v).forEach((c) => { v[c] = arrondi1(v[c]); });
		return v;
	}
	/* pendant le geste : directement sur le visuel, sans attendre le rendu complet */
	function poserMep(id, a) {
		if (genreDe(id) === 'libre') { poserLibre(id, a); return; }
		const e = elementDe(id, genreDe(id));
		if (e) { StudioRendu.poser(e, a, e.closest('.uaz-visuel')); }
		placerSelection();
	}
	/* fin du geste : ce qui est garde, puis le rendu complet */
	function garderMep(id, a) {
		if (genreDe(id) === 'libre') { garderLibre(id, a); return; }
		const v = validerMep(a);
		const neutre = !v.dx && !v.dy && !v.l && !v.h && (!v.k || v.k === 1);
		const f = mepFormat(true);
		if (neutre) { delete f[id]; } else { f[id] = v; }
		planifierRendu();
	}
	function remettre(id) {
		const f = mepFormat();
		if (f[id]) { delete f[id]; planifierRendu(); }
	}
	function remettreTout() {
		if (!nbDeplaces()) { return; }
		delete etat.miseEnPage[etat.format];
		deselectionner();
		planifierRendu();
		annoncer('Mise en page d\'origine rétablie (annuler la rend).', 'ok');
	}

	/* ------------------------------------------------ l'aimant et ses reperes */
	function reperes(id) {
		const e = elementDe(id, genreDe(id));
		const racine = e.closest('.uaz-visuel');
		const rr = racine.getBoundingClientRect(), k = etat.echelle;
		const L = racine.offsetWidth, H = racine.offsetHeight;
		const xs = [40, L / 2, L - 40], ys = [40, H / 2, H - 40];
		$('stVisuel').querySelectorAll('[data-champ], [data-image], [data-libre]').forEach((x) => {
			if (x === e || e.contains(x) || x.contains(e)) { return; }
			const r = x.getBoundingClientRect();
			if (!r.width || !r.height) { return; }
			const a = [(r.left - rr.left) / k, (r.right - rr.left) / k], b = [(r.top - rr.top) / k, (r.bottom - rr.top) / k];
			xs.push(a[0], (a[0] + a[1]) / 2, a[1]);
			ys.push(b[0], (b[0] + b[1]) / 2, b[1]);
		});
		return { xs, ys, rr };
	}
	function aimanter(valeurs, cibles, seuil) {
		let meilleur = null;
		valeurs.forEach((v) => cibles.forEach((c) => {
			const d = c - v;
			if (Math.abs(d) <= seuil && (!meilleur || Math.abs(d) < Math.abs(meilleur.d))) { meilleur = { d, c }; }
		}));
		return meilleur;
	}
	function guides(x, y, g) {
		const scene = $('stScene'), s = scene.getBoundingClientRect(), k = etat.echelle;
		scene.querySelectorAll('.st-guide').forEach((e) => e.remove());
		[[x, 'v'], [y, 'h']].forEach(([c, sens]) => {
			if (c == null) { return; }
			const t = el('div', 'st-guide st-guide--' + sens);
			if (sens === 'v') { t.style.left = (g.lignes.rr.left + c * k - s.left) + 'px'; } else { t.style.top = (g.lignes.rr.top + c * k - s.top) + 'px'; }
			scene.appendChild(t);
		});
	}
	const effacerGuides = () => $('stScene').querySelectorAll('.st-guide').forEach((e) => e.remove());

	/* ------------------------------------------------ les gestes */
	function geste(type, e, id, pos) {
		const cible = elementDe(id, genreDe(id));
		if (!cible) { return null; }
		const r = cible.getBoundingClientRect(), k = etat.echelle, rr = cible.closest('.uaz-visuel').getBoundingClientRect();
		return {
			type, pos, id, x0: e.clientX, y0: e.clientY, a0: genreDe(id) === 'libre' ? geoLibre(id) : mepDe(id), bouge: false, courant: genreDe(id) === 'libre' ? geoLibre(id) : mepDe(id),
			boite: { x: (r.left - rr.left) / k, y: (r.top - rr.top) / k, l: cible.offsetWidth, h: cible.offsetHeight },
			lignes: type === 'deplacer' ? reperes(id) : null, texte: genreDe(id) === 'champ' || (genreDe(id) === 'libre' && !!blocDe(id) && blocDe(id).type === 'texte'),
		};
	}
	function pas(g, e) {
		if (genreDe(g.id) === 'libre') { pasLibre(g, e); return; }
		const k = etat.echelle, ddx = (e.clientX - g.x0) / k, ddy = (e.clientY - g.y0) / k;
		const a = Object.assign({}, g.a0);
		if (g.type === 'deplacer') {
			let mx = ddx, my = ddy, gx = null, gy = null;
			if (!e.altKey) {
				const bx = g.boite.x + ddx, by = g.boite.y + ddy, seuil = 6 / k;
				const sx = aimanter([bx, bx + g.boite.l / 2, bx + g.boite.l], g.lignes.xs, seuil);
				const sy = aimanter([by, by + g.boite.h / 2, by + g.boite.h], g.lignes.ys, seuil);
				if (sx) { mx += sx.d; gx = sx.c; }
				if (sy) { my += sy.d; gy = sy.c; }
			}
			a.dx = (g.a0.dx || 0) + mx;
			a.dy = (g.a0.dy || 0) + my;
			guides(gx, gy, g);
		} else {
			const p = g.pos;
			if (g.texte && p === 'se') {
				a.k = (g.a0.k || 1) * (1 + (ddx / g.boite.l + ddy / g.boite.h) / 2);
			} else {
				if (p.indexOf('e') !== -1) { a.l = g.boite.l + ddx; }
				if (p.indexOf('w') !== -1) { a.l = g.boite.l - ddx; a.dx = (g.a0.dx || 0) + ddx; }
				if (p.indexOf('s') !== -1) { a.h = g.boite.h + ddy; }
				if (p.indexOf('n') !== -1) { a.h = g.boite.h - ddy; a.dy = (g.a0.dy || 0) + ddy; }
				if (a.l != null && a.l < 20) { if (p.indexOf('w') !== -1) { a.dx -= 20 - a.l; } a.l = 20; }
				if (a.h != null && a.h < 20) { if (p.indexOf('n') !== -1) { a.dy -= 20 - a.h; } a.h = 20; }
			}
		}
		g.courant = a;
		poserMep(g.id, a);
	}
	/* porteur : l'element qui recoit la suite des mouvements (capture du pointeur) */
	function suivreGeste(g, porteur, e, corps) {
		/* Une poignee : le pointeur est capture des l'appui. L'element lui-meme : seulement quand le geste
		   commence vraiment. Capture a l'appui, le pointeur renvoyait le relachement et le CLIC au conteneur
		   du visuel : un simple clic sur un texte ne trouvait plus l'element (signale le 26/10/2026). */
		if (!corps) { porteur.setPointerCapture(e.pointerId); }
		const bouger = (ev) => {
			if (corps && !g.bouge && ev.buttons === 0) { fin(); return; }   // relache ailleurs avant d'avoir bouge
			if (!g.bouge && Math.hypot(ev.clientX - g.x0, ev.clientY - g.y0) < 4) { return; }
			if (!g.bouge && corps) {
				try { porteur.setPointerCapture(ev.pointerId); } catch (x) { /* pointeur deja relache */ }
				if (!sel || sel.id !== g.id) { selectionner(g.id, genreDe(g.id)); }
			}
			g.bouge = true;
			pas(g, ev);
		};
		const fin = () => {
			porteur.removeEventListener('pointermove', bouger);
			porteur.removeEventListener('pointerup', fin);
			porteur.removeEventListener('pointercancel', fin);
			effacerGuides();
			if (g.bouge) { garderMep(g.id, g.courant); if (corps) { etat.sansClic = true; } }
		};
		porteur.addEventListener('pointermove', bouger);
		porteur.addEventListener('pointerup', fin);
		porteur.addEventListener('pointercancel', fin);
	}
	/* les poignees de la selection, comme dans Figma : on deplace en glissant l'element lui-meme
	   (ou aux fleches du clavier) ; un texte a ses cotes (largeur) et son coin (taille), une image
	   ou une forme ses huit points ; un intitule garde sa place dans la grille */
	function poignees(o) {
		o.replaceChildren();
		if (!sel || etat.lecture || sel.genre === 'etiquette') { return; }
		const image = sel && (sel.genre === 'image' || (sel.genre === 'libre' && blocDe(sel.id) && blocDe(sel.id).type !== 'texte'));
		(image ? ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'] : ['w', 'e', 'se']).forEach((pos) => {
			const p = el('span', 'st-poignee st-poignee--' + pos);
			p.dataset.pos = pos;
			p.addEventListener('pointerdown', (e) => {
				if (e.button !== 0 || etat.lecture || !sel) { return; }
				e.preventDefault();
				e.stopPropagation();
				const x = geste('taille', e, sel.id, pos);
				if (x) { suivreGeste(x, p, e, false); }
			});
			o.appendChild(p);
		});
	}
	/* fleches : 1 px, Maj : 10 px */
	function pousser(e) {
		if (!sel || etat.lecture || sel.genre === 'etiquette') { return false; }
		const d = (e.shiftKey ? 10 : 1);
		const id = sel.id;
		const libre = genreDe(id) === 'libre';
		const a = etat.fleches && etat.fleches.id === id ? etat.fleches.a : (libre ? geoLibre(id) : mepDe(id));
		const v = { ArrowLeft: [-d, 0], ArrowRight: [d, 0], ArrowUp: [0, -d], ArrowDown: [0, d] }[e.key];
		if (!v || !a) { return false; }
		e.preventDefault();
		if (libre) { a.x += v[0]; a.y += v[1]; } else { a.dx = (a.dx || 0) + v[0]; a.dy = (a.dy || 0) + v[1]; }
		etat.fleches = { id, a };
		poserMep(id, a);
		clearTimeout(etat.minuterieFleches);
		etat.minuterieFleches = setTimeout(() => { etat.fleches = null; garderMep(id, a); }, 250);
		return true;
	}

	/* =========================================================================
	   Blocs ajoutes (jalon 4 de « modifier comme dans Canva ») : texte libre, image,
	   forme, poses par-dessus le visuel, avec leurs calques. Couleurs de la charte et
	   police Archivo seulement. Chaque bloc a sa position par format (geo) ; le moteur
	   les dessine a l'apercu et a l'export (studio-rendu.js, ajouterBlocs).
	   ====================================================================== */
	const COULEURS_BLOC = [['navy', '#232A5F', 'Marine'], ['gold', '#8B7448', 'Or'], ['goldDeep', '#85703F', 'Or profond'], ['goldLight', '#C8AE72', 'Or clair'],
		['bronze', '#7A6535', 'Bronze'], ['cream', '#F4F2ED', 'Crème'], ['paper', '#FBFAF7', 'Papier'], ['sand', '#E4DDCB', 'Sable'],
		['ink', '#1D2233', 'Encre'], ['rule', '#8C93B8', 'Filet'], ['navySoft', '#3A4380', 'Marine doux'], ['white', '#FFFFFF', 'Blanc']];
	const GRAISSES_LIBRES = [[400, 'Normal'], [500, 'Moyen'], [600, 'Demi-gras'], [700, 'Gras'], [800, 'Extra-gras']];
	const estLibre = (id) => /^b\d+$/.test(String(id));
	const blocDe = (id) => etat.blocs.find((b) => b.id === id);
	const cleImage = (id) => 'libre' + id;
	/* les images utiles : celles du preset et celles des blocs image qui existent encore */
	function clesImages() {
		const ok = new Set((etat.preset.images || []).map((i) => i.id));
		etat.blocs.forEach((b) => { if (b.type === 'image') { ok.add(cleImage(b.id)); } });
		return Object.keys(etat.images).filter((k) => ok.has(k));
	}
	function libelleBloc(b) {
		if (b.type === 'texte') { const t = String(b.texte || '').replace(/\s+/g, ' ').trim(); return t ? (t.length > 26 ? t.slice(0, 25) + '…' : t) : 'Texte'; }
		if (b.type === 'forme') { return b.forme === 'cercle' ? 'Cercle' : (b.geo && Object.values(b.geo).every((g) => g.h <= 12 || g.l <= 12) ? 'Ligne' : 'Rectangle'); }
		const x = etat.images[cleImage(b.id)];
		return x && x.alt ? x.alt : 'Image';
	}
	const ICONES_BLOC = { texte: 'bi-fonts', forme: 'bi-square', image: 'bi-image' };
	const prochainId = () => 'b' + (etat.blocs.reduce((m, b) => Math.max(m, Number(b.id.slice(1))), 0) + 1);
	const arrondir1 = (v) => Math.round(v * 10) / 10;

	/* ------------------------------------------------ creer, copier, supprimer, ordonner */
	/* place : rien (au centre du visuel), { cx, cy } (un clic d'outil : centre la, a la taille par defaut)
	   ou { x, y, l, h } (un outil tire sur le visuel) */
	function ajouterBloc(type, variante, place) {
		if (etat.lecture || etat.blocs.length >= 30) { if (etat.blocs.length >= 30) { annoncer('30 blocs au plus par visuel.', 'erreur'); } return; }
		const fmt = etat.preset.formats[etat.format];
		const L = fmt.largeur, H = fmt.hauteur;
		const b = { id: prochainId(), type, geo: {} };
		let l = L * 0.4, h = L * 0.2;
		if (type === 'texte') { Object.assign(b, { texte: '', taille: Math.round(L * 0.05), graisse: 700, couleur: 'navy', align: 'left', maj: false }); h = L * 0.08; }
		else if (type === 'forme') {
			Object.assign(b, { forme: variante === 'cercle' ? 'cercle' : 'rect', couleur: 'gold', opacite: 1, rayon: 0 });
			if (variante === 'cercle') { l = h = L * 0.25; } else if (variante === 'ligne') { l = L * 0.4; h = 6; } else { l = L * 0.35; h = L * 0.2; }
		} else { l = L * 0.4; h = L * 0.3; }
		let x = (L - l) / 2, y = (H - h) / 2;
		if (place && place.l != null) {
			x = place.x; y = place.y;
			l = type === 'texte' ? Math.max(40, place.l) : place.l;
			if (type !== 'texte') { h = place.h; }
		} else if (place && place.cx != null) {
			x = type === 'texte' ? place.cx : place.cx - l / 2;   // un texte commence la ou l'on clique
			y = place.cy - h / 2;
		}
		b.geo[etat.format] = { x: arrondir1(x), y: arrondir1(y), l: arrondir1(Math.max(4, l)), h: arrondir1(Math.max(2, h)) };
		etat.blocs.push(b);
		etat.aSelectionner = { id: b.id, ouvrir: type === 'texte' };   // un texte s'ecrit tout de suite (Figma)
		planifierRendu();
	}
	function dupliquerBloc(id) {
		const b = blocDe(id);
		if (!b || etat.lecture || etat.blocs.length >= 30) { return; }
		const c = clone(b);
		c.id = prochainId();
		Object.keys(c.geo || {}).forEach((f) => { c.geo[f].x = arrondir1(c.geo[f].x + 30); c.geo[f].y = arrondir1(c.geo[f].y + 30); });
		const x = etat.images[cleImage(id)];
		if (b.type === 'image' && x) {
			etat.images[cleImage(c.id)] = Object.assign({}, x, { url: URL.createObjectURL(x.blob), fichier: null, cadrage: clone(x.cadrage), reglages: clone(x.reglages) });
		}
		etat.blocs.splice(etat.blocs.indexOf(b) + 1, 0, c);
		etat.aSelectionner = { id: c.id, ouvrir: false };
		planifierRendu();
	}
	function supprimerBloc(id) {
		const i = etat.blocs.findIndex((b) => b.id === id);
		if (i === -1 || etat.lecture) { return; }
		if (sel && sel.id === id) { deselectionner(); }
		etat.blocs.splice(i, 1);   // l'image reste en memoire : annuler la rend
		planifierRendu();
	}
	/* sens : 1 avancer, -1 reculer ; 'haut' premier plan, 'bas' arriere-plan */
	function deplacerBloc(id, sens) {
		const i = etat.blocs.findIndex((b) => b.id === id);
		const j = sens === 'haut' ? etat.blocs.length - 1 : (sens === 'bas' ? 0 : i + sens);
		if (i === -1 || j < 0 || j >= etat.blocs.length || i === j || etat.lecture) { return; }
		const [b] = etat.blocs.splice(i, 1);
		etat.blocs.splice(j, 0, b);
		planifierRendu();
	}
	function majBloc(id, props) {
		const b = blocDe(id);
		if (!b || etat.lecture) { return; }
		Object.assign(b, props);
		planifierRendu();
	}

	/* ------------------------------------------------ la position (comme les autres elements) */
	function geoLibre(id) {
		const e = elementDe(id, 'libre');
		if (!e) { return null; }
		const racine = e.closest('.uaz-visuel'), rr = racine.getBoundingClientRect(), r = e.getBoundingClientRect(), k = etat.echelle;
		const b = blocDe(id) || {};
		return { x: (r.left - rr.left) / k, y: (r.top - rr.top) / k, l: e.offsetWidth, h: e.offsetHeight, taille: b.taille };
	}
	function poserLibre(id, a) {
		const e = elementDe(id, 'libre'), b = blocDe(id);
		if (!e || !b) { return; }
		e.style.left = a.x + 'px';
		e.style.top = a.y + 'px';
		e.style.width = a.l + 'px';
		if (b.type !== 'texte') { e.style.height = a.h + 'px'; }
		if (b.type === 'texte' && a.taille) { e.style.fontSize = a.taille + 'px'; }
		placerSelection();
	}
	function garderLibre(id, a) {
		const b = blocDe(id), e = elementDe(id, 'libre');
		if (!b) { return; }
		b.geo = b.geo || {};
		b.geo[etat.format] = { x: arrondir1(a.x), y: arrondir1(a.y), l: arrondir1(Math.max(4, a.l)), h: arrondir1(Math.max(2, b.type === 'texte' && e ? e.offsetHeight : a.h)) };
		if (b.type === 'texte' && a.taille) { b.taille = Math.max(8, Math.min(600, Math.round(a.taille))); }
		planifierRendu();
	}
	function pasLibre(g, e) {
		const k = etat.echelle, ddx = (e.clientX - g.x0) / k, ddy = (e.clientY - g.y0) / k;
		const a = Object.assign({}, g.a0);
		if (g.type === 'deplacer') {
			let mx = ddx, my = ddy, gx = null, gy = null;
			if (!e.altKey) {
				const bx = g.boite.x + ddx, by = g.boite.y + ddy, seuil = 6 / k;
				const sx = aimanter([bx, bx + g.boite.l / 2, bx + g.boite.l], g.lignes.xs, seuil);
				const sy = aimanter([by, by + g.boite.h / 2, by + g.boite.h], g.lignes.ys, seuil);
				if (sx) { mx += sx.d; gx = sx.c; }
				if (sy) { my += sy.d; gy = sy.c; }
			}
			a.x = g.a0.x + mx;
			a.y = g.a0.y + my;
			guides(gx, gy, g);
		} else {
			const p = g.pos;
			if (g.texte && p === 'se') {
				a.taille = Math.max(8, Math.min(600, g.a0.taille * (1 + (ddx / g.boite.l + ddy / g.boite.h) / 2)));
			} else {
				if (p.indexOf('e') !== -1) { a.l = g.a0.l + ddx; }
				if (p.indexOf('w') !== -1) { a.l = g.a0.l - ddx; a.x = g.a0.x + ddx; }
				if (p.indexOf('s') !== -1) { a.h = g.a0.h + ddy; }
				if (p.indexOf('n') !== -1) { a.h = g.a0.h - ddy; a.y = g.a0.y + ddy; }
				if (a.l < 4) { if (p.indexOf('w') !== -1) { a.x -= 4 - a.l; } a.l = 4; }
				if (a.h < 2) { if (p.indexOf('n') !== -1) { a.y -= 2 - a.h; } a.h = 2; }
			}
		}
		g.courant = a;
		poserLibre(g.id, a);
	}

	/* ------------------------------------------------ la saisie sur le visuel (texte libre) */
	function editerLibre(id) {
		const b = blocDe(id);
		if (!b || b.type !== 'texte' || etat.lecture) { return false; }
		if (edition && edition.id === id) { edition.zone.focus(); return true; }
		fermerEdition();
		const zone = el('textarea', 'st-edition');
		zone.value = b.texte || '';
		zone.maxLength = 600;
		zone.setAttribute('aria-label', 'Texte du bloc');
		const compteur = el('span', 'st-edition__compteur');
		const maj = () => { compteur.textContent = 'Texte · ' + zone.value.length + ' / 600'; compteur.classList.toggle('st-compteur--plein', zone.value.length >= 600); };
		edition = { id, zone, compteur, selecteur: '[data-libre="' + id + '"]' };
		maj();
		$('stCadre').append(zone, compteur);
		placerEdition();
		zone.addEventListener('input', () => { b.texte = zone.value; maj(); planifierRendu(); });
		zone.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); fermerEdition(); }
			else if (e.key === 'Tab') { fermerEdition(); }
		});
		zone.addEventListener('blur', () => setTimeout(() => { if (edition && edition.zone === zone) { fermerEdition(); } }, 120));
		focaliser(zone);
		zone.setSelectionRange(zone.value.length, zone.value.length);
		return true;
	}

	/* ------------------------------------------------ les intitules (« Lieu », « Heure »…) */
	const etiquettes = () => (etat.donnees._etiquettes && typeof etat.donnees._etiquettes === 'object' && !Array.isArray(etat.donnees._etiquettes) ? etat.donnees._etiquettes : {});
	function poserEtiquette(cle, texte) {
		const e = Object.assign({}, etiquettes());
		if (texte === null || texte === cle) { delete e[cle]; } else { e[cle] = texte; }
		changer('_etiquettes', e);
	}
	function editerEtiquette(cle) {
		if (etat.lecture) { return false; }
		if (edition && edition.id === cle) { edition.zone.focus(); return true; }
		fermerEdition();
		const zone = el('textarea', 'st-edition');
		const actuel = etiquettes();
		zone.value = Object.prototype.hasOwnProperty.call(actuel, cle) ? actuel[cle] : cle;
		zone.maxLength = 60;
		zone.setAttribute('aria-label', 'Intitulé « ' + cle + ' »');
		const compteur = el('span', 'st-edition__compteur');
		const maj = () => { compteur.textContent = 'Intitulé · ' + zone.value.length + ' / 60'; };
		edition = { id: cle, zone, compteur, selecteur: '[data-etiquette="' + CSS.escape(cle) + '"]' };
		maj();
		$('stCadre').append(zone, compteur);
		placerEdition();
		zone.addEventListener('input', () => { poserEtiquette(cle, zone.value.replace(/[{}\n]/g, '')); maj(); });
		zone.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); fermerEdition(); }
			else if (e.key === 'Enter') { e.preventDefault(); fermerEdition(); }
			else if (e.key === 'Tab') { fermerEdition(); }
		});
		zone.addEventListener('blur', () => setTimeout(() => { if (edition && edition.zone === zone) { fermerEdition(); } }, 120));
		focaliser(zone);
		zone.select();
		return true;
	}
	function selectionnerEtiquette(cle, editer) {
		const cible = elementDe(cle, 'etiquette');
		if (!cible || etat.lecture) { return false; }
		if (sel && sel.id === cle && sel.genre === 'etiquette') { if (editer) { editerEtiquette(cle); } return true; }
		deselectionner();
		sel = { id: cle, genre: 'etiquette', hote: null };
		const s = section('Intitulé');
		const corps = el('div', 'st-flottant__corps');
		corps.append(
			boutonOutil('Écrire', 'bi-cursor-text', 'Réécrire cet intitulé sur le visuel (Entrée, ou double-clic)', () => editerEtiquette(cle)),
			boutonOutil('Texte d\'origine', 'bi-arrow-counterclockwise', 'Remet « ' + cle + ' »', () => { poserEtiquette(cle, null); if (edition && edition.id === cle) { edition.zone.value = cle; } }),
			boutonOutil('Masquer', 'bi-eye-slash', 'Retire cet intitulé du visuel (il reste en filigrane à l\'écran pour le retrouver)', () => { poserEtiquette(cle, ''); if (edition && edition.id === cle) { edition.zone.value = ''; } }));
		s.append(corps, el('p', 'st-aide', 'Vaut pour tous les formats de ce visuel. Masqué, il disparaît de l\'export et reste en filigrane à l\'écran.'));
		ouvrirProprietes(entete('bi-tag', 'Intitulé « ' + cle + ' »'), [sectionPosition(), s]);
		placerSelection();
		if (editer) { editerEtiquette(cle); }
		return true;
	}

	/* ------------------------------------------------ les proprietes d'un bloc ajoute */
	function pastilles(valeur, choisir) {
		const g = el('div', 'st-pastilles');
		g.setAttribute('role', 'radiogroup');
		g.setAttribute('aria-label', 'Couleur');
		COULEURS_BLOC.forEach(([cle, hex, nom]) => {
			const b = el('button', 'st-pastille-couleur');
			b.type = 'button';
			b.style.background = hex;
			b.title = nom;
			b.setAttribute('role', 'radio');
			b.setAttribute('aria-label', nom);
			b.setAttribute('aria-checked', String(cle === valeur));
			b.addEventListener('mousedown', (e) => e.preventDefault());
			b.addEventListener('click', () => { choisir(cle); g.querySelectorAll('button').forEach((x) => x.setAttribute('aria-checked', String(x === b))); });
			g.appendChild(b);
		});
		return g;
	}
	function rangee(libelle, controle, idControle) {
		const r = el('label', 'st-rangee');
		r.appendChild(el('span', 'st-rangee__libelle', libelle));
		r.appendChild(controle);
		if (idControle) { controle.id = idControle; }
		return r;
	}
	function curseur(min, max, pas, valeur, change) {
		const c = el('input', 'st-curseur');
		c.type = 'range'; c.min = String(min); c.max = String(max); c.step = String(pas); c.value = String(valeur);
		c.addEventListener('input', () => change(Number(c.value)));
		return c;
	}
	/* les sections du panneau pour un bloc : texte ou forme ou image, remplissage, apparence, calque */
	function outilsLibre(id) {
		const b = blocDe(id);
		const sections = [];
		if (b.type === 'texte') {
			const s = section('Texte');
			const haut = el('div', 'st-flottant__groupe');
			const taille = el('input', 'st-saisie st-saisie--court');
			taille.type = 'number'; taille.min = '8'; taille.max = '600'; taille.value = String(b.taille);
			taille.setAttribute('aria-label', 'Taille du texte en pixels');
			taille.addEventListener('input', () => { const v = Math.max(8, Math.min(600, Number(taille.value) || 0)); if (v >= 8) { majBloc(id, { taille: v }); } });
			const moins = boutonOutil('', 'bi-dash-lg', 'Plus petit', () => { const v = Math.max(8, b.taille - Math.max(1, Math.round(b.taille * 0.1))); taille.value = String(v); majBloc(id, { taille: v }); });
			const plus = boutonOutil('', 'bi-plus-lg', 'Plus grand', () => { const v = Math.min(600, b.taille + Math.max(1, Math.round(b.taille * 0.1))); taille.value = String(v); majBloc(id, { taille: v }); });
			const graisse = el('select', 'st-saisie st-saisie--court');
			graisse.setAttribute('aria-label', 'Graisse');
			GRAISSES_LIBRES.forEach(([v, n]) => graisse.appendChild(new Option(n, String(v))));
			graisse.value = String(b.graisse);
			graisse.addEventListener('change', () => majBloc(id, { graisse: Number(graisse.value) }));
			haut.append(moins, taille, plus, graisse);
			const al = el('div', 'st-flottant__groupe');
			[['left', 'bi-text-left', 'À gauche'], ['center', 'bi-text-center', 'Centré'], ['right', 'bi-text-right', 'À droite']].forEach(([v, ic, n]) => {
				const x = boutonOutil('', ic, n, () => { majBloc(id, { align: v }); al.querySelectorAll('button[data-align]').forEach((y) => y.setAttribute('aria-pressed', String(y === x))); });
				x.dataset.align = v;
				x.setAttribute('aria-pressed', String(b.align === v));
				al.appendChild(x);
			});
			const maj = boutonOutil('', 'bi-type', 'Majuscules', () => { majBloc(id, { maj: !blocDe(id).maj }); maj.setAttribute('aria-pressed', String(blocDe(id).maj)); });
			maj.setAttribute('aria-pressed', String(!!b.maj));
			al.appendChild(maj);
			s.append(el('p', 'st-typo', 'Archivo, la police de la charte'), haut, al);
			const r = section('Remplissage');
			r.appendChild(pastilles(b.couleur, (c) => majBloc(id, { couleur: c })));
			sections.push(s, r);
		} else if (b.type === 'forme') {
			const s = section('Forme');
			const type = el('select', 'st-saisie st-saisie--compacte');
			type.setAttribute('aria-label', 'Forme');
			type.append(new Option('Rectangle', 'rect'), new Option('Cercle', 'cercle'));
			type.value = b.forme;
			type.addEventListener('change', () => majBloc(id, { forme: type.value }));
			s.appendChild(type);
			const r = section('Remplissage');
			r.appendChild(pastilles(b.couleur, (c) => majBloc(id, { couleur: c })));
			const ap = section('Apparence');
			ap.append(rangee('Opacité', curseur(5, 100, 5, Math.round(b.opacite * 100), (v) => majBloc(id, { opacite: v / 100 }))),
				rangee('Coins arrondis', curseur(0, 200, 2, b.rayon, (v) => majBloc(id, { rayon: v }))));
			sections.push(s, r, ap);
		} else {
			const s = section('Image');
			const cle = cleImage(id);
			const fichier = el('input', 'st-fichier-cache');
			fichier.type = 'file'; fichier.accept = TYPES_IMAGE.join(','); fichier.id = 'st-libre-fichier';
			const choisir = el('label', 'st-bouton', etat.images[cle] ? 'Remplacer l\'image' : 'Choisir une image');
			choisir.htmlFor = fichier.id;
			fichier.addEventListener('change', async () => {
				const f = fichier.files && fichier.files[0];
				fichier.value = '';
				if (!f) { return; }
				annoncer('Préparation de l\'image…');
				try {
					const garde = etat.images[cle];
					poserImage({ id: cle, fondre: false, ajustement: 'remplir' }, await preparerImage(f), f.name, f, false);
					if (garde && garde.alt) { etat.images[cle].alt = garde.alt; }
					annoncer('');
					etat.aSelectionner = { id, ouvrir: false, reouvrir: true };
					deselectionner();
					planifierRendu();
				} catch (e) { annoncer(e.message, 'erreur'); }
			});
			const rappel = async (f, alt) => {
				try {
					poserImage({ id: cle, fondre: false, ajustement: 'remplir' }, await preparerImage(f), f.name, f, false);
					if (etat.images[cle]) { etat.images[cle].alt = alt || ''; }
					etat.aSelectionner = { id, ouvrir: false };
					deselectionner();
					planifierRendu();
				} catch (e) { annoncer(e.message, 'erreur'); }
			};
			const g = el('div', 'st-bloc__boutons');
			g.append(choisir, boutonMediatheque(rappel));
			s.append(fichier, g);
			const x = etat.images[cle];
			if (x) {
				const alt = el('input', 'st-saisie');
				alt.type = 'text'; alt.maxLength = 150; alt.value = x.alt; alt.placeholder = 'Ce que montre l\'image, en une phrase';
				alt.setAttribute('aria-required', 'true');
				alt.addEventListener('input', () => { x.alt = alt.value; majAlertes(); majCalques(); });
				const ajust = el('select', 'st-saisie');
				ajust.append(new Option('Remplir le cadre', 'remplir'), new Option('Image entière', 'contenir'));
				ajust.value = x.ajustement;
				ajust.addEventListener('change', () => { x.ajustement = ajust.value; planifierRendu(); });
				s.append(rangee('Cadrage', ajust), rangee('Zoom', curseur(100, 400, 5, Math.round(x.cadrage.zoom * 100), (v) => { x.cadrage.zoom = v / 100; planifierRendu(); })), rangee('Description (obligatoire)', alt));
			}
			sections.push(s);
		}
		const c = section('Calque');
		const g = el('div', 'st-flottant__groupe');
		const supprimer = boutonOutil('Supprimer', 'bi-trash', 'Supprimer ce bloc (Suppr)', () => supprimerBloc(id));
		supprimer.classList.add('st-outil--danger');
		g.append(
			boutonOutil('Dupliquer', 'bi-copy', 'Dupliquer ce bloc (Ctrl+D)', () => dupliquerBloc(id)),
			boutonOutil('', 'bi-layer-forward', 'Passer devant', () => deplacerBloc(id, 1)),
			boutonOutil('', 'bi-layer-backward', 'Passer derrière', () => deplacerBloc(id, -1)),
			supprimer);
		c.appendChild(g);
		sections.push(c);
		return sections;
	}
	/* Choisir un bloc ajoute ; « ouvrir » (true) : ecrire son texte tout de suite */
	function selectionnerLibre(id, ouvrir) {
		const b = blocDe(id), cible = elementDe(id, 'libre');
		if (!b || !cible || etat.lecture) { return false; }
		if (sel && sel.id === id && sel.genre === 'libre') {
			if (ouvrir === true && b.type === 'texte' && !edition) { editerLibre(id); }
			return true;
		}
		deselectionner();
		sel = { id, genre: 'libre', hote: null };
		ouvrirProprietes(entete(iconeDe('libre', id), { texte: 'Texte libre', forme: 'Forme', image: 'Image ajoutée' }[b.type]), [sectionPosition()].concat(outilsLibre(id)));
		if (b.type === 'texte' && ouvrir === true) { editerLibre(id); }
		placerSelection();
		return true;
	}

	/* ------------------------------------------------ les calques (panneau de gauche) */
	/* les elements de la charte affiches, dans l'ordre de lecture du visuel */
	function elementsVisuel() {
		const vus = new Set(), liste = [];
		$('stVisuel').querySelectorAll(SELECTEUR).forEach((e) => {
			if (e.closest('[data-libre]')) { return; }
			const c = cibleDe(e);
			const k = c.genre + ':' + c.id;
			if (vus.has(k)) { return; }
			vus.add(k);
			c.vide = e.classList.contains('uaz-a-remplir');
			liste.push(c);
		});
		return liste;
	}
	function nomElement(x) {
		if (x.genre === 'etiquette') {
			const t = etiquettes()[x.id];
			return t === undefined ? 'Intitulé « ' + x.id + ' »' : (t === '' ? 'Intitulé « ' + x.id + ' » (masqué)' : 'Intitulé « ' + t + ' »');
		}
		return libelleDe(x.id);
	}
	function iconeCalque(icone, titre, etiquette, action) {
		const b = el('button', 'st-icone st-icone--petit');
		b.type = 'button';
		b.title = titre;
		b.setAttribute('aria-label', etiquette);
		b.innerHTML = '<i class="bi ' + icone + '" aria-hidden="true"></i>';
		b.addEventListener('click', (e) => { e.stopPropagation(); action(); });
		return b;
	}
	/* glisser un bloc dans la liste : il passe au-dessus (ou au-dessous) de celui qu'on survole */
	function ordonnerBloc(id, cibleId, dessus) {
		if (etat.lecture || id === cibleId) { return; }
		const i = etat.blocs.findIndex((b) => b.id === id);
		if (i === -1) { return; }
		const [b] = etat.blocs.splice(i, 1);
		const j = etat.blocs.findIndex((x) => x.id === cibleId);
		etat.blocs.splice(j === -1 ? etat.blocs.length : (dessus ? j + 1 : j), 0, b);
		planifierRendu();
	}
	function ligneCalque(x, nom, icone) {
		const actif = !!sel && sel.id === x.id && sel.genre === x.genre;
		const li = el('li', 'st-calque' + (actif ? ' st-calque--actif' : '') + (x.vide ? ' st-calque--vide' : ''));
		const b = el('button', 'st-calque__nom');
		b.type = 'button';
		b.innerHTML = '<i class="bi ' + icone + '" aria-hidden="true"></i>';
		b.appendChild(el('span', '', nom));
		b.title = nom;
		if (actif) { b.setAttribute('aria-current', 'true'); }
		const trouver = () => elementDe(x.id, x.genre);
		b.addEventListener('click', () => { const e = trouver(); if (!e) { annoncer('Cet élément n\'est pas affiché dans ce format.'); return; } choisirElement(e, false); });
		b.addEventListener('dblclick', () => { const e = trouver(); if (e) { choisirElement(e, true); } });
		li.addEventListener('pointerenter', () => { if (!etat.glisseCalque) { survoler(trouver()); } });
		li.addEventListener('pointerleave', () => survoler(null));
		li.addEventListener('contextmenu', (ev) => {
			const e = trouver();
			if (!e || etat.lecture) { return; }
			ev.preventDefault();
			choisirElement(e, false);
			remplirMenu($('stMenuContexte'), elementsMenu());
			ouvrirMenu($('stMenuContexte'), { x: ev.clientX, y: ev.clientY });
		});
		li.appendChild(b);
		const actions = el('span', 'st-calque__actions');
		if (!etat.lecture) {
			if (x.genre === 'libre') {
				[['bi-arrow-up', 'Monter', 1], ['bi-arrow-down', 'Descendre', -1]].forEach(([ic, n, sens]) => actions.appendChild(iconeCalque(ic, n, n + ' « ' + nom + ' »', () => deplacerBloc(x.id, sens))));
				actions.appendChild(iconeCalque('bi-trash', 'Supprimer', 'Supprimer « ' + nom + ' »', () => supprimerBloc(x.id)));
				li.draggable = true;
				const effacer = () => li.classList.remove('st-calque--cible', 'st-calque--cible-bas');
				li.addEventListener('dragstart', (ev) => { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', x.id); etat.glisseCalque = x.id; survoler(null); });
				li.addEventListener('dragover', (ev) => {
					if (!etat.glisseCalque || etat.glisseCalque === x.id) { return; }
					ev.preventDefault();
					const r = li.getBoundingClientRect(), dessus = ev.clientY < r.top + r.height / 2;
					li.classList.toggle('st-calque--cible', dessus);
					li.classList.toggle('st-calque--cible-bas', !dessus);
				});
				li.addEventListener('dragleave', effacer);
				li.addEventListener('drop', (ev) => {
					ev.preventDefault();
					const dessus = li.classList.contains('st-calque--cible');
					effacer();
					const id = etat.glisseCalque;
					etat.glisseCalque = null;
					if (id) { ordonnerBloc(id, x.id, dessus); }
				});
				li.addEventListener('dragend', () => { etat.glisseCalque = null; effacer(); });
			} else if (x.genre === 'etiquette') {
				const masque = etiquettes()[x.id] === '';
				actions.appendChild(iconeCalque(masque ? 'bi-eye-slash' : 'bi-eye', masque ? 'Afficher' : 'Masquer', (masque ? 'Afficher' : 'Masquer') + ' l\'intitulé « ' + x.id + ' »', () => poserEtiquette(x.id, masque ? null : '')));
			} else if (mepFormat()[x.id]) {
				actions.appendChild(iconeCalque('bi-arrow-counterclockwise', 'Remettre à sa place', 'Remettre « ' + nom + ' » à sa place', () => remettre(x.id)));
			}
		}
		li.appendChild(actions);
		if (x.genre !== 'libre' && mepFormat()[x.id]) { const m = el('span', 'st-calque__marque', '●'); m.title = 'Déplacé ou redimensionné'; li.insertBefore(m, actions); }
		return li;
	}
	function majCalques() {
		const ul = $('stCalques'), ulV = $('stCalquesVisuel');
		if (!ul || !ulV || !etat.preset) { return; }
		$('stCalquesBloc').hidden = !etat.blocs.length;
		const elements = elementsVisuel();
		const mep = mepFormat(), etq = etiquettes();
		const cle = JSON.stringify([etat.blocs.map((b) => [b.id, libelleBloc(b), iconeDe('libre', b.id)]), elements.map((x) => [x.genre, x.id, x.vide, !!mep[x.id], x.genre === 'etiquette' ? etq[x.id] : 0]), sel ? [sel.genre, sel.id] : 0, etat.lecture]);
		if (cle === etat.sigCalques) { return; }
		etat.sigCalques = cle;
		ul.replaceChildren();
		/* le dernier bloc est au-dessus : on le montre en premier, comme un panneau de calques */
		etat.blocs.slice().reverse().forEach((b) => ul.appendChild(ligneCalque({ id: b.id, genre: 'libre' }, libelleBloc(b), iconeDe('libre', b.id))));
		ulV.replaceChildren();
		elements.forEach((x) => ulV.appendChild(ligneCalque(x, nomElement(x), iconeDe(x.genre, x.id))));
		const actif = $('stPanneauCalques').querySelector('.st-calque--actif');
		if (actif && !$('stPanneauCalques').hidden) { montrer(actif, false); }
	}

	/* =========================================================================
	   L'interface de Figma : onglets, menus, barre d'outils, plan de travail, clavier
	   ====================================================================== */
	const CREATION = { rect: ['forme', 'rect'], cercle: ['forme', 'cercle'], ligne: ['forme', 'ligne'], texte: ['texte', null] };
	const OUTILS_CLAVIER = { v: 'deplacer', h: 'main', r: 'rect', o: 'cercle', l: 'ligne', t: 'texte' };
	const estSaisie = (t) => !!t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
	const enColonne = () => window.matchMedia('(max-width: 767px)').matches;

	function ongletGauche(nom) {
		const champs = nom === 'champs';
		$('stOngletCalques').setAttribute('aria-selected', String(!champs));
		$('stOngletCalques').tabIndex = champs ? -1 : 0;
		$('stChamps').setAttribute('aria-selected', String(champs));
		$('stChamps').tabIndex = champs ? 0 : -1;
		$('stPanneauCalques').hidden = champs;
		$('stPanneauChamps').hidden = !champs;
		try { localStorage.setItem('studio-uaz.onglet', champs ? 'champs' : 'calques'); } catch (e) { /* stockage indisponible */ }
	}
	function ongletDroit(nom) {
		const pub = nom === 'publication';
		$('stOngletDesign').setAttribute('aria-selected', String(!pub));
		$('stOngletDesign').tabIndex = pub ? -1 : 0;
		$('stOngletPublication').setAttribute('aria-selected', String(pub));
		$('stOngletPublication').tabIndex = pub ? 0 : -1;
		$('stPanneauDesign').hidden = pub;
		$('stPanneauPublication').hidden = !pub;
	}
	/* une liste d'onglets au clavier : fleches (gauche et droite, ou haut et bas) */
	function ongletsClavier(liste, suivant, precedent) {
		liste.addEventListener('keydown', (e) => {
			if (e.key !== suivant && e.key !== precedent) { return; }
			const onglets = Array.from(liste.querySelectorAll('[role="tab"]:not(:disabled)'));
			const i = onglets.indexOf(document.activeElement);
			if (i === -1) { return; }
			e.preventDefault();
			const n = onglets[(i + (e.key === suivant ? 1 : -1) + onglets.length) % onglets.length];
			n.focus();
			n.click();
		});
	}
	function basculerPanneaux() {
		app.classList.toggle('st--sans-ui');
		requestAnimationFrame(() => { ajusterEchelle(); placerSelection(); });
	}

	/* --- les menus (Studio, zoom, clic droit) */
	let menu = null;   // { m, declencheur }
	function ouvrirMenu(m, ancre, declencheur) {
		fermerMenu();
		m.hidden = false;
		const L = m.offsetWidth, H = m.offsetHeight;
		let x, y;
		if (ancre instanceof Element) {
			const r = ancre.getBoundingClientRect();
			x = ancre.closest('.st-droite') ? r.right - L : r.left;
			y = r.bottom + 6;
		} else { x = ancre.x; y = ancre.y; }
		m.style.left = Math.max(8, Math.min(x, innerWidth - L - 8)) + 'px';
		m.style.top = Math.max(8, Math.min(y, innerHeight - H - 8)) + 'px';
		menu = { m, declencheur: declencheur || null };
		if (declencheur) { declencheur.setAttribute('aria-expanded', 'true'); }
		const premier = m.querySelector('.st-menu__item:not(:disabled):not([hidden])');
		if (premier) { premier.focus({ preventScroll: true }); }
	}
	function fermerMenu(rendreFocus) {
		if (!menu) { return; }
		const { m, declencheur } = menu;
		menu = null;
		m.hidden = true;
		if (declencheur) {
			declencheur.setAttribute('aria-expanded', 'false');
			if (rendreFocus) { declencheur.focus({ preventScroll: true }); }
		}
	}
	/* un menu d'actions : [libelle, raccourci, action, { icone, desactive, coche }] ou '-' */
	function remplirMenu(m, items) {
		m.replaceChildren();
		items.forEach((it) => {
			if (it === '-') { const s = el('div', 'st-menu__sep'); s.setAttribute('role', 'separator'); m.appendChild(s); return; }
			const [libelle, raccourci, action, o] = it;
			const b = el('button', 'st-menu__item');
			b.type = 'button';
			if (o && o.coche != null) { b.setAttribute('role', 'menuitemcheckbox'); b.setAttribute('aria-checked', String(!!o.coche)); } else { b.setAttribute('role', 'menuitem'); }
			const i = el('i', 'bi ' + ((o && o.icone) || ''));
			i.setAttribute('aria-hidden', 'true');
			b.append(i, el('span', '', libelle));
			if (raccourci) { b.appendChild(el('span', 'st-menu__raccourci', raccourci)); }
			b.disabled = !!(o && o.desactive);
			b.addEventListener('click', () => { fermerMenu(); action(); });
			m.appendChild(b);
		});
	}
	function menuZoom() {
		remplirMenu($('stMenuZoom'), [
			['Zoom avant', 'Ctrl +', () => zoomer(etat.echelle * 2), { icone: 'bi-zoom-in' }],
			['Zoom arrière', 'Ctrl −', () => zoomer(etat.echelle / 2), { icone: 'bi-zoom-out' }],
			'-',
			['Tout voir', 'Maj+1', toutVoir, { icone: 'bi-arrows-fullscreen' }],
			['Zoom sur la sélection', 'Maj+2', zoomSelection, { icone: 'bi-bounding-box', desactive: !sel }],
			['Taille réelle (100 %)', 'Maj+0', tailleReelle, { icone: 'bi-aspect-ratio' }],
			'-',
			['Marges de 40 px', '', () => $('stMarges').click(), { coche: $('stMarges').checked }],
			['Masquer les panneaux', 'Ctrl+\\', basculerPanneaux, { coche: app.classList.contains('st--sans-ui') }],
		]);
	}
	/* le clic droit : les actions de l'element choisi, ou celles de la vue */
	function elementsMenu() {
		const items = [];
		if (sel && !etat.lecture) {
			const b = sel.genre === 'libre' ? blocDe(sel.id) : null;
			const texte = (b && b.type === 'texte') || sel.genre === 'etiquette' || (sel.genre === 'champ' && !sel.hote);
			if (texte) { items.push(['Écrire le texte', 'Entrée', editerSelection, { icone: 'bi-cursor-text' }]); } else if (sel.hote) { items.push(['Modifier', 'Entrée', editerSelection, { icone: 'bi-pencil' }]); }
			if (b) {
				const id = sel.id, i = etat.blocs.indexOf(b), n = etat.blocs.length;
				items.push(['Dupliquer', 'Ctrl+D', () => dupliquerBloc(id), { icone: 'bi-copy' }], '-',
					['Premier plan', 'Ctrl+Maj+]', () => deplacerBloc(id, 'haut'), { icone: 'bi-layer-forward', desactive: i === n - 1 }],
					['Avancer', 'Ctrl+]', () => deplacerBloc(id, 1), { icone: 'bi-arrow-up', desactive: i === n - 1 }],
					['Reculer', 'Ctrl+[', () => deplacerBloc(id, -1), { icone: 'bi-arrow-down', desactive: i === 0 }],
					['Arrière-plan', 'Ctrl+Maj+[', () => deplacerBloc(id, 'bas'), { icone: 'bi-layer-backward', desactive: i === 0 }], '-',
					['Supprimer', 'Suppr', () => supprimerBloc(id), { icone: 'bi-trash' }]);
			} else if (sel.genre === 'etiquette') {
				const cle = sel.id;
				items.push(['Texte d\'origine', '', () => poserEtiquette(cle, null), { icone: 'bi-arrow-counterclockwise', desactive: etiquettes()[cle] === undefined }],
					['Masquer', '', () => poserEtiquette(cle, ''), { icone: 'bi-eye-slash', desactive: etiquettes()[cle] === '' }]);
			} else {
				const id = sel.id;
				items.push(['Remettre à sa place', '', () => remettre(id), { icone: 'bi-arrow-counterclockwise', desactive: !mepFormat()[id] }]);
			}
			items.push('-', ['Zoom sur la sélection', 'Maj+2', zoomSelection, { icone: 'bi-bounding-box' }]);
		} else {
			items.push(['Tout voir', 'Maj+1', toutVoir, { icone: 'bi-arrows-fullscreen' }], ['Taille réelle (100 %)', 'Maj+0', tailleReelle, { icone: 'bi-aspect-ratio' }], '-',
				['Marges de 40 px', '', () => $('stMarges').click(), { coche: $('stMarges').checked }],
				['Remettre la mise en page d\'origine', '', remettreTout, { icone: 'bi-arrow-counterclockwise', desactive: !nbDeplaces() || etat.lecture }]);
		}
		return items;
	}

	/* --- la barre d'outils : un outil de creation se tire sur le visuel, puis rend la main a « Deplacer » */
	function choisirOutil(o) {
		etat.outil = o;
		app.dataset.outil = o;
		app.classList.toggle('st--creer', !!CREATION[o]);
		document.querySelectorAll('#stOutils [data-outil]').forEach((b) => { if (b.dataset.outil !== 'image') { b.setAttribute('aria-pressed', String(b.dataset.outil === o)); } });
		survoler(null);
	}
	function outilImage() {
		if (etat.lecture) { return; }
		ajouterBloc('image');
		choisirOutil('deplacer');
	}
	function avalerLeClic() {   // le clic qui suit un geste sur le plan n'est pas un choix
		etat.avalerClic = true;
		setTimeout(() => { etat.avalerClic = false; }, 0);
	}
	function glisserPlan(e) {
		const scene = $('stScene'), x0 = e.clientX, y0 = e.clientY, px = etat.panX, py = etat.panY;
		try { scene.setPointerCapture(e.pointerId); } catch (x) { /* deja relache */ }
		app.classList.add('st--glisse');
		const bouger = (ev) => { etat.panX = px + ev.clientX - x0; etat.panY = py + ev.clientY - y0; ajusterEchelle(); placerSelection(); };
		const fin = () => {
			scene.removeEventListener('pointermove', bouger);
			scene.removeEventListener('pointerup', fin);
			scene.removeEventListener('pointercancel', fin);
			app.classList.remove('st--glisse');
			avalerLeClic();
		};
		scene.addEventListener('pointermove', bouger);
		scene.addEventListener('pointerup', fin);
		scene.addEventListener('pointercancel', fin);
	}
	/* tirer un rectangle, une ellipse, une ligne ou un cadre de texte ; un simple clic : taille par defaut */
	function tracer(e) {
		const racine = $('stVisuel').querySelector('.uaz-visuel');
		if (!racine) { return; }
		const [type, variante] = CREATION[etat.outil];
		const scene = $('stScene'), s = scene.getBoundingClientRect(), k = etat.echelle, rr = racine.getBoundingClientRect();
		const x0 = e.clientX, y0 = e.clientY;
		const trace = el('div', 'st-trace' + (variante === 'cercle' ? ' st-trace--cercle' : ''));
		trace.hidden = true;
		scene.appendChild(trace);
		try { scene.setPointerCapture(e.pointerId); } catch (x) { /* deja relache */ }
		let bouge = false, x1 = x0, y1 = y0;
		const dessiner = (ev) => {
			if (Math.hypot(ev.clientX - x0, ev.clientY - y0) >= 4) { bouge = true; }
			x1 = ev.clientX;
			y1 = ev.clientY;
			if (ev.shiftKey && type === 'forme' && variante !== 'ligne') {   // Maj : un carre, un cercle
				const d = Math.max(Math.abs(x1 - x0), Math.abs(y1 - y0));
				x1 = x0 + (x1 < x0 ? -d : d);
				y1 = y0 + (y1 < y0 ? -d : d);
			}
			if (variante === 'ligne') { if (Math.abs(x1 - x0) >= Math.abs(y1 - y0)) { y1 = y0 + 6 * k; } else { x1 = x0 + 6 * k; } }   // horizontale ou verticale
			if (type === 'texte') { y1 = y0 + Math.max(24, Math.abs(y1 - y0)); }
			trace.style.left = (Math.min(x0, x1) - s.left) + 'px';
			trace.style.top = (Math.min(y0, y1) - s.top) + 'px';
			trace.style.width = Math.abs(x1 - x0) + 'px';
			trace.style.height = Math.abs(y1 - y0) + 'px';
			trace.hidden = !bouge;
		};
		const finir = (creer) => {
			scene.removeEventListener('pointermove', dessiner);
			scene.removeEventListener('pointerup', lacher);
			scene.removeEventListener('pointercancel', annuler);
			trace.remove();
			avalerLeClic();
			if (creer) {
				const vx = (cx) => (cx - rr.left) / k, vy = (cy) => (cy - rr.top) / k;
				if (bouge) {
					let l = Math.abs(x1 - x0) / k, h = Math.abs(y1 - y0) / k;
					if (variante === 'ligne') { if (l >= h) { h = 6; } else { l = 6; } }
					ajouterBloc(type, variante, { x: vx(Math.min(x0, x1)), y: vy(Math.min(y0, y1)), l, h });
				} else {
					ajouterBloc(type, variante, { cx: vx(x0), cy: vy(y0) });
				}
			}
			choisirOutil('deplacer');
		};
		const lacher = (ev) => { dessiner(ev); finir(true); };
		const annuler = () => finir(false);
		scene.addEventListener('pointermove', dessiner);
		scene.addEventListener('pointerup', lacher);
		scene.addEventListener('pointercancel', annuler);
	}

	function initialiserInterface() {
		/* les onglets */
		$('stOngletCalques').addEventListener('click', () => ongletGauche('calques'));
		$('stChamps').addEventListener('click', () => ongletGauche('champs'));
		$('stOngletDesign').addEventListener('click', () => ongletDroit('design'));
		$('stOngletPublication').addEventListener('click', () => ongletDroit('publication'));
		ongletsClavier($('stOngletCalques').parentElement, 'ArrowRight', 'ArrowLeft');
		ongletsClavier($('stOngletDesign').parentElement, 'ArrowRight', 'ArrowLeft');
		ongletsClavier($('stFormats'), 'ArrowDown', 'ArrowUp');
		let onglet = null;
		try { onglet = localStorage.getItem('studio-uaz.onglet'); } catch (e) { /* stockage indisponible */ }
		ongletGauche(onglet === 'champs' ? 'champs' : 'calques');

		/* les menus */
		$('stMenuPrincipal').addEventListener('click', () => { if (menu && menu.m === $('stMenuFichier')) { fermerMenu(); } else { ouvrirMenu($('stMenuFichier'), $('stMenuPrincipal'), $('stMenuPrincipal')); } });
		$('stMenuFichier').addEventListener('click', (e) => { if (e.target.closest('.st-menu__item')) { fermerMenu(); } });
		$('stZoom').addEventListener('click', () => { if (menu && menu.m === $('stMenuZoom')) { fermerMenu(); } else { menuZoom(); ouvrirMenu($('stMenuZoom'), $('stZoom'), $('stZoom')); } });
		$('stAideRaccourcis').addEventListener('click', () => $('stDlgRaccourcis').showModal());
		document.addEventListener('pointerdown', (e) => { if (menu && !menu.m.contains(e.target) && !(menu.declencheur && menu.declencheur.contains(e.target))) { fermerMenu(); } }, true);
		document.addEventListener('keydown', (e) => {
			if (!menu) { return; }
			const items = Array.from(menu.m.querySelectorAll('.st-menu__item:not(:disabled):not([hidden])'));
			const i = items.indexOf(document.activeElement);
			if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); fermerMenu(true); }
			else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); e.stopPropagation(); const n = items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]; if (n) { n.focus(); } }
			else if (e.key === 'Home' || e.key === 'End') { e.preventDefault(); const n = e.key === 'Home' ? items[0] : items[items.length - 1]; if (n) { n.focus(); } }
			else if (e.key === 'Tab') { fermerMenu(); }
		}, true);
		window.addEventListener('resize', () => fermerMenu());

		/* l'en-tete de droite, le cadre, les messages */
		$('stAllerExport').addEventListener('click', () => {
			deselectionner();
			ongletDroit('design');
			const s = $('stExport');
			montrer(s, false);
			const b = s.querySelector('[data-sortie]:not(:disabled)');
			if (b) { b.focus({ preventScroll: true }); } else { const x = s.querySelector('[data-sortie]'); annoncer(x && x.title ? x.title : 'Complétez le visuel d\'abord.'); }
		});
		$('stAllerValidation').addEventListener('click', () => ongletDroit('publication'));
		$('stCadreNom').addEventListener('click', deselectionner);
		$('stToastFermer').addEventListener('click', () => $('stToast').classList.remove('st-toast--visible'));
		$('stMepReset').addEventListener('click', remettreTout);

		/* la barre d'outils */
		$('stOutils').addEventListener('mousedown', (e) => { if (e.target.closest('[data-outil]')) { e.preventDefault(); } });   // Espace reste a la main
		$('stOutils').addEventListener('click', (e) => {
			const b = e.target.closest('[data-outil]');
			if (!b || b.disabled) { return; }
			const o = b.dataset.outil;
			if (o === 'image') { outilImage(); return; }
			if (CREATION[o] && e.detail === 0) { ajouterBloc(CREATION[o][0], CREATION[o][1]); choisirOutil('deplacer'); return; }   // au clavier : au centre du visuel
			choisirOutil(etat.outil === o && o !== 'deplacer' ? 'deplacer' : o);
		});
		choisirOutil('deplacer');

		/* le plan de travail : main (outil, Espace, bouton du milieu), outils de creation, molette, clic droit */
		const scene = $('stScene');
		scene.addEventListener('pointerdown', (e) => {
			if (e.target.closest('.st-outils, .st-toast, .st-lecture, .st-edition, .st-cadre__nom')) { return; }
			if (e.button === 1 || (e.button === 0 && (etat.outil === 'main' || etat.espace))) { e.preventDefault(); e.stopPropagation(); fermerMenu(); glisserPlan(e); return; }
			if (e.button === 0 && CREATION[etat.outil] && !etat.lecture) { e.preventDefault(); e.stopPropagation(); fermerEdition(); tracer(e); }
		}, true);
		scene.addEventListener('mousedown', (e) => { if (e.button === 1) { e.preventDefault(); } });   // pas de defilement automatique
		scene.addEventListener('click', (e) => { if (etat.avalerClic) { etat.avalerClic = false; e.stopPropagation(); e.preventDefault(); } }, true);
		scene.addEventListener('click', (e) => { if (e.target === scene) { deselectionner(); } });
		scene.addEventListener('contextmenu', (e) => {
			if (e.target.closest('.st-outils, .st-toast, .st-edition')) { return; }
			e.preventDefault();
			const t = e.target.closest(SELECTEUR);
			if (t && $('stVisuel').contains(t) && !etat.lecture) { const c = cibleDe(t); if (!sel || sel.id !== c.id || sel.genre !== c.genre) { choisirElement(t, false); } } else if (!t) { deselectionner(); }
			remplirMenu($('stMenuContexte'), elementsMenu());
			ouvrirMenu($('stMenuContexte'), { x: e.clientX, y: e.clientY });
		});
		scene.addEventListener('wheel', (e) => {
			if (e.ctrlKey || e.metaKey) { e.preventDefault(); zoomer(etat.echelle * Math.exp(-e.deltaY * (e.deltaMode === 1 ? 0.05 : 0.0025)), e.clientX, e.clientY); return; }
			if (enColonne()) { return; }   // telephone : la page defile
			e.preventDefault();
			const f = e.deltaMode === 1 ? 20 : 1;
			deplacerVue(-(e.shiftKey ? e.deltaY : e.deltaX) * f, -(e.shiftKey ? 0 : e.deltaY) * f);
		}, { passive: false });
		/* le survol des elements */
		$('stVisuel').addEventListener('pointermove', (e) => {
			if (e.buttons || CREATION[etat.outil] || etat.outil === 'main' || etat.espace) { survoler(null); return; }
			const c = e.target.closest(SELECTEUR);
			survoler(c && $('stVisuel').contains(c) ? c : null);
		});
		$('stVisuel').addEventListener('pointerleave', () => survoler(null));

		/* le clavier, comme dans Figma (jamais pendant qu'on ecrit dans une case) */
		document.addEventListener('keydown', (e) => {
			if (e.defaultPrevented || document.querySelector('dialog[open]')) { return; }
			const mod = e.ctrlKey || e.metaKey, touche = String(e.key).toLowerCase();
			if (mod && e.shiftKey && (e.key === '?' || e.code === 'Slash' || e.code === 'Comma')) { e.preventDefault(); $('stDlgRaccourcis').showModal(); return; }
			if (mod && (e.key === '\\' || e.code === 'Backslash' || e.code === 'IntlBackslash')) { e.preventDefault(); basculerPanneaux(); return; }
			if (estSaisie(e.target)) { return; }
			if (mod && !e.altKey && (e.key === '=' || e.key === '+' || e.code === 'NumpadAdd')) { e.preventDefault(); zoomer(etat.echelle * 2); return; }
			if (mod && !e.altKey && (e.key === '-' || e.code === 'NumpadSubtract')) { e.preventDefault(); zoomer(etat.echelle / 2); return; }
			if (e.shiftKey && !mod && !e.altKey && /^(Digit|Numpad)[012]$/.test(e.code)) { e.preventDefault(); ({ 0: tailleReelle, 1: toutVoir, 2: zoomSelection })[e.code.slice(-1)](); return; }
			if (mod && e.shiftKey && touche === 'k') { e.preventDefault(); outilImage(); return; }
			if (mod && !e.shiftKey && touche === 'd') { e.preventDefault(); if (sel && sel.genre === 'libre') { dupliquerBloc(sel.id); } return; }   // jamais le marque-page
			if (mod && (e.code === 'BracketRight' || e.code === 'BracketLeft')) {
				if (!sel || sel.genre !== 'libre') { return; }
				e.preventDefault();
				const avant = e.code === 'BracketRight';
				deplacerBloc(sel.id, e.shiftKey ? (avant ? 'haut' : 'bas') : (avant ? 1 : -1));
				return;
			}
			if (mod || e.altKey) { return; }
			const surBouton = !!(e.target && e.target.closest && e.target.closest('button, a, summary, [role="tab"]'));
			if (e.key === 'Enter') { if (sel && !surBouton && editerSelection()) { e.preventDefault(); } return; }
			if (e.key === ' ') {
				if (surBouton) { return; }   // Espace active ce bouton
				e.preventDefault();
				if (!etat.espace) { etat.espace = true; app.classList.add('st--espace'); survoler(null); }
				return;
			}
			if (e.key === 'Escape') {
				if (edition) { return; }   // la zone d'ecriture s'en charge
				if (etat.outil !== 'deplacer') { choisirOutil('deplacer'); return; }
				if (sel) { deselectionner(); }
				return;
			}
			const outil = OUTILS_CLAVIER[touche];
			if (outil && !e.repeat) {
				if (etat.lecture && CREATION[outil]) { return; }
				e.preventDefault();
				choisirOutil(outil);
			}
		});
		document.addEventListener('keyup', (e) => { if (e.key === ' ' && etat.espace) { etat.espace = false; app.classList.remove('st--espace'); } });
		window.addEventListener('blur', () => { etat.espace = false; app.classList.remove('st--espace'); });
		/* Suppr : le bloc choisi ; fleches : 1 px (Maj : 10 px) */
		document.addEventListener('keydown', (e) => {
			if ((e.key !== 'Delete' && e.key !== 'Backspace') || !sel || sel.genre !== 'libre' || edition || e.defaultPrevented || estSaisie(e.target)) { return; }
			e.preventDefault();
			supprimerBloc(sel.id);
		});
		document.addEventListener('keydown', (e) => {
			if (e.key.indexOf('Arrow') !== 0 || !sel || edition || e.defaultPrevented || estSaisie(e.target) || (e.target.closest && e.target.closest('[role="tablist"], .st-menu'))) { return; }
			pousser(e);
		});
		window.addEventListener('resize', placerSelection);
		window.addEventListener('scroll', placerSelection, true);
	}

	/* =========================================================================
	   Etape 6 : les reglages du superadmin (listes, valeurs par defaut, types
	   proposes), les validateurs, la mediatheque. Les editeurs LISENT les reglages
	   (etat.reglages, venus de app/studio.visuels.php) et piochent dans la
	   mediatheque ; seul le superadmin les ecrit (app/studio.admin.php).
	   ====================================================================== */
	const CHAMPS_SERVICE = ['service', 'organisateur'];
	const LISTES_SUGGEREES = { anneeUniversitaire: 'annees', filiere: 'filieres', filieres: 'filieres' };
	const serviceStrict = (c) => CHAMPS_SERVICE.indexOf(c.id) !== -1 && !!(etat.reglages && etat.reglages.listes.services.length);
	function suggestions(c, saisie, bloc) {
		const l = LISTES_SUGGEREES[c.id];
		const valeurs = l && etat.reglages ? etat.reglages.listes[l] : [];
		if (!valeurs.length) { return; }
		let dl = bloc.querySelector('#st-sug-' + c.id);   // une seule liste de suggestions par champ
		if (!dl) {
			dl = el('datalist');
			dl.id = 'st-sug-' + c.id;
			valeurs.forEach((v) => dl.appendChild(new Option(v)));
			bloc.appendChild(dl);
		}
		saisie.setAttribute('list', dl.id);
	}
	/* Les types proposes a la creation : les autres restent ouvrables (visuels deja faits) */
	function remplirTypes(choisi) {
		const choix = $('stPreset');
		const inactifs = etat.reglages ? etat.reglages.inactifs : [];
		choix.replaceChildren();
		PRESETS.filter((id) => inactifs.indexOf(id) === -1 || id === choisi).forEach((id) => choix.appendChild(new Option(etat.presets[id].code + ' — ' + etat.presets[id].nom + (inactifs.indexOf(id) !== -1 ? ' (n\'est plus proposé)' : ''), id)));
		choix.disabled = choix.options.length < 2;
		if (choisi) { choix.value = choisi; }
	}

	async function apiAdmin(params, corps) {
		const entetes = { 'X-Requested-With': 'XMLHttpRequest' };
		const r = corps
			? await fetch(ADMIN, { method: 'POST', body: corps, credentials: 'same-origin', headers: entetes })
			: await fetch(ADMIN + '?' + new URLSearchParams(params), { credentials: 'same-origin', headers: entetes });
		const j = await r.json().catch(() => ({ ok: false, message: 'Réponse illisible (' + r.status + ').' }));
		if (!r.ok || !j.ok) { throw new Error(j.message || 'Erreur ' + r.status + '.'); }
		return j;
	}
	function postAdmin(action, champs) {
		const fd = new FormData();
		fd.append('action', action);
		Object.keys(champs || {}).forEach((k) => fd.append(k, champs[k]));
		return apiAdmin(null, fd);
	}

	const adm = { reg: null, onglet: 'types' };
	/* PHP envoie [] pour « rien » : un objet pour les valeurs par defaut, sinon elles se perdraient a l'envoi */
	const normaliserReglages = (r) => { if (r && Array.isArray(r.defauts)) { r.defauts = {}; } return r; };
	function enteteAdmin(titre, aide) {
		const f = document.createDocumentFragment();
		f.appendChild(el('h3', 'st-adm__titre', titre));
		if (aide) { f.appendChild(el('p', 'st-aide', aide)); }
		return f;
	}
	function champAdmin(libelle, saisie, aide) {
		const b = el('div', 'st-champ');
		const lab = el('label', 'st-libelle', libelle);
		saisie.id = saisie.id || 'st-adm-' + Math.random().toString(36).slice(2, 8);
		lab.htmlFor = saisie.id;
		b.append(lab, saisie);
		if (aide) { b.appendChild(el('p', 'st-aide', aide)); }
		return b;
	}
	function boutonAdmin(libelle, icone, action, principal) {
		const b = el('button', 'st-bouton' + (principal ? ' st-bouton--principal' : ''));
		b.type = 'button';
		if (icone) { b.innerHTML = '<i class="bi ' + icone + '" aria-hidden="true"></i> '; }
		b.appendChild(document.createTextNode(libelle));
		b.addEventListener('click', action);
		return b;
	}
	function messageAdmin(texte, genre) {
		const m = $('stAdmMessage');
		m.textContent = texte || '';
		m.className = 'st-etat' + (genre ? ' st-etat--' + genre : '');
	}
	async function enregistrerReglages() {
		try {
			const fd = new FormData();
			fd.append('action', 'sauver_reglages');
			fd.append('reglages', JSON.stringify(adm.reg));
			const j = await apiAdmin(null, fd);
			adm.reg = normaliserReglages(j.reglages);
			etat.reglages = j.reglages;
			remplirTypes(etat.preset ? etat.preset.id : null);
			messageAdmin('Réglages enregistrés. Ils s\'appliquent aux prochains visuels (et aux services émetteurs dès maintenant).', 'ok');
			majAlertes();
		} catch (e) { messageAdmin(e.message, 'erreur'); }
	}
	function ongletTypes(zone) {
		zone.appendChild(enteteAdmin('Types de visuels', 'Un type retiré n\'est plus proposé pour un nouveau visuel ; ceux qui existent restent ouvrables et exportables. Au moins un type reste proposé.'));
		PRESETS.forEach((id) => {
			const p = etat.presets[id];
			const boite = el('details', 'st-adm__type');
			const resume = el('summary');
			const caseActif = el('input');
			caseActif.type = 'checkbox';
			caseActif.checked = adm.reg.inactifs.indexOf(id) === -1;
			caseActif.setAttribute('aria-label', 'Proposer le type ' + p.nom);
			caseActif.addEventListener('click', (e) => e.stopPropagation());
			caseActif.addEventListener('change', () => {
				adm.reg.inactifs = adm.reg.inactifs.filter((x) => x !== id);
				if (!caseActif.checked) { adm.reg.inactifs.push(id); }
			});
			resume.append(caseActif, el('b', '', ' ' + p.code + ' — ' + p.nom));
			boite.appendChild(resume);
			boite.appendChild(el('p', 'st-aide', 'Valeurs par défaut : elles remplissent le formulaire d\'un nouveau visuel de ce type.'));
			p.champs.filter((c) => c.type !== 'liste').forEach((c) => {
				const s = el('input', 'st-saisie');
				s.type = 'text';
				s.maxLength = c.max || 80;
				s.value = ((adm.reg.defauts[id] || {})[c.id]) || '';
				s.placeholder = c.defaut != null ? String(c.defaut) + ' (d\'origine)' : '';
				s.addEventListener('input', () => {
					adm.reg.defauts[id] = adm.reg.defauts[id] || {};
					if (s.value.trim()) { adm.reg.defauts[id][c.id] = s.value; } else { delete adm.reg.defauts[id][c.id]; }
				});
				boite.appendChild(champAdmin(c.libelle, s));
			});
			zone.appendChild(boite);
		});
		zone.appendChild(boutonAdmin('Enregistrer', 'bi-check2', enregistrerReglages, true));
	}
	function ongletListes(zone) {
		zone.appendChild(enteteAdmin('Listes', 'Une valeur par ligne, 100 au plus.'));
		[['services', 'Services émetteurs', 'Quand cette liste n\'est pas vide, le champ « Service émetteur » se choisit dans la liste : pas de saisie libre. Vide : saisie libre.'],
			['annees', 'Années universitaires', 'Proposées à la saisie (la saisie libre reste possible).'],
			['filieres', 'Filières', 'Proposées à la saisie (la saisie libre reste possible).']].forEach(([cle, libelle, aide]) => {
			const t = el('textarea', 'st-saisie');
			t.rows = 7;
			t.value = (adm.reg.listes[cle] || []).join('\n');
			t.addEventListener('input', () => { adm.reg.listes[cle] = t.value.split('\n'); });
			zone.appendChild(champAdmin(libelle, t, aide));
		});
		zone.appendChild(boutonAdmin('Enregistrer', 'bi-check2', enregistrerReglages, true));
	}
	async function ongletValidateurs(zone) {
		zone.appendChild(enteteAdmin('Validateurs', 'Le superadmin valide toujours. Un validateur voit « À valider », approuve ou refuse (jamais son propre visuel), et lit le journal des exports. Le communiqué et le recrutement ne s\'exportent qu\'une fois validés.'));
		const liste = el('ul', 'st-lignes');
		zone.appendChild(liste);
		const dessiner = (rows) => {
			liste.replaceChildren();
			if (!rows.length) { liste.appendChild(el('li', 'st-vide', 'Aucun validateur désigné : seul le superadmin valide.')); }
			rows.forEach((v) => {
				const li = el('li', 'st-ligne');
				const t = el('span', 'st-ligne__texte');
				t.append(el('b', '', ((v.prenom || '') + ' ' + (v.nom || '')).trim() || v.pseudo || ('compte ' + v.user_id)), el('span', '', (v.pseudo || '') + (v.etat === 0 || v.etat === '0' ? ' · compte désactivé' : '')));
				const r = boutonAdmin('Retirer', 'bi-x-lg', async () => { try { dessiner((await postAdmin('role_retirer', { user: v.user_id })).validateurs); } catch (e) { messageAdmin(e.message, 'erreur'); } });
				li.append(t, r);
				liste.appendChild(li);
			});
		};
		try { dessiner((await apiAdmin({ action: 'validateurs' })).validateurs); } catch (e) { messageAdmin(e.message, 'erreur'); }
		const q = el('input', 'st-saisie');
		q.type = 'search';
		q.placeholder = 'Nom, prénom ou identifiant…';
		const res = el('ul', 'st-lignes');
		let minuteur = 0;
		q.addEventListener('input', () => {
			clearTimeout(minuteur);
			minuteur = setTimeout(async () => {
				res.replaceChildren();
				if (q.value.trim().length < 2) { return; }
				try {
					const j = await apiAdmin({ action: 'comptes', q: q.value });
					if (!j.comptes.length) { res.appendChild(el('li', 'st-vide', 'Aucun compte du personnel ne correspond.')); }
					j.comptes.forEach((c) => {
						const li = el('li', 'st-ligne');
						const t = el('span', 'st-ligne__texte');
						t.append(el('b', '', ((c.prenom || '') + ' ' + (c.nom || '')).trim() || c.pseudo), el('span', '', c.pseudo));
						li.append(t, boutonAdmin('Désigner', 'bi-plus-lg', async () => { try { const r = await postAdmin('role_ajouter', { user: c.id }); dessiner(r.validateurs); res.replaceChildren(); q.value = ''; } catch (e) { messageAdmin(e.message, 'erreur'); } }));
						res.appendChild(li);
					});
				} catch (e) { messageAdmin(e.message, 'erreur'); }
			}, 250);
		});
		zone.append(champAdmin('Désigner un validateur', q), res);
	}
	async function ongletMediatheque(zone) {
		zone.appendChild(enteteAdmin('Médiathèque', 'Les images mises à disposition de tous les éditeurs (photos du campus, illustrations). Le texte alternatif est repris dans chaque visuel qui l\'utilise.'));
		const grille = el('ul', 'st-grille');
		const dessiner = (media) => {
			grille.replaceChildren();
			if (!media.length) { grille.appendChild(el('li', 'st-vide', 'La médiathèque est vide.')); }
			media.forEach((m) => {
				const li = el('li', 'st-vignette');
				const img = el('img');
				img.src = VISUELS + '?action=media_image&id=' + m.id;
				img.alt = m.alt;
				img.loading = 'lazy';
				const nom = el('span', 'st-vignette__nom', m.titre);
				const s = el('button', 'st-icone st-icone--petit');
				s.type = 'button'; s.title = 'Retirer de la médiathèque'; s.setAttribute('aria-label', 'Retirer « ' + m.titre + ' »');
				s.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
				s.addEventListener('click', async () => { if (!window.confirm('Retirer « ' + m.titre + ' » de la médiathèque ? Les visuels qui l\'ont déjà utilisée la gardent.')) { return; } try { dessiner((await postAdmin('media_supprimer', { id: m.id })).media); } catch (e) { messageAdmin(e.message, 'erreur'); } });
				li.append(img, nom, s);
				grille.appendChild(li);
			});
		};
		try { dessiner((await apiAdmin({ action: 'media' })).media); } catch (e) { messageAdmin(e.message, 'erreur'); }
		const f = el('input', 'st-saisie'); f.type = 'file'; f.accept = TYPES_IMAGE.join(',');
		const titre = el('input', 'st-saisie'); titre.type = 'text'; titre.maxLength = 120;
		const alt = el('input', 'st-saisie'); alt.type = 'text'; alt.maxLength = 150;
		const ajouter = boutonAdmin('Ajouter à la médiathèque', 'bi-upload', async () => {
			if (!f.files || !f.files[0]) { messageAdmin('Choisissez une image.', 'erreur'); return; }
			try {
				messageAdmin('Préparation de l\'image…');
				const p = await preparerImage(f.files[0]);
				const fd = new FormData();
				fd.append('action', 'media_ajouter');
				fd.append('titre', titre.value);
				fd.append('alt', alt.value);
				fd.append('image', p.blob, 'media.jpg');
				dessiner((await apiAdmin(null, fd)).media);
				f.value = ''; titre.value = ''; alt.value = '';
				messageAdmin('Image ajoutée.', 'ok');
			} catch (e) { messageAdmin(e.message, 'erreur'); }
		}, true);
		zone.append(champAdmin('Image (JPEG, PNG ou WebP)', f), champAdmin('Titre', titre), champAdmin('Description (texte alternatif)', alt, 'Ce que montre l\'image, en une phrase.'), ajouter, grille);
	}
	async function ouvrirAdmin(onglet) {
		adm.onglet = onglet || adm.onglet;
		const dlg = $('stDlgAdmin');
		if (!dlg.open) { dlg.showModal(); }
		messageAdmin('');
		const barre = $('stAdmOnglets');
		barre.replaceChildren();
		[['types', 'Types'], ['listes', 'Listes'], ['validateurs', 'Validateurs'], ['media', 'Médiathèque']].forEach(([cle, libelle]) => {
			const b = el('button', 'st-format', libelle);
			b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', String(cle === adm.onglet));
			b.addEventListener('click', () => ouvrirAdmin(cle));
			barre.appendChild(b);
		});
		const zone = $('stAdmZone');
		zone.replaceChildren(el('p', 'st-vide', 'Chargement…'));
		try {
			adm.reg = normaliserReglages((await apiAdmin({ action: 'reglages' })).reglages);
			zone.replaceChildren();
			if (adm.onglet === 'types') { ongletTypes(zone); }
			else if (adm.onglet === 'listes') { ongletListes(zone); }
			else if (adm.onglet === 'validateurs') { await ongletValidateurs(zone); }
			else { await ongletMediatheque(zone); }
		} catch (e) { zone.replaceChildren(el('p', 'st-vide', e.message)); }
	}

	/* ------------------------------------------------ la mediatheque, pour les editeurs */
	let pourMedia = null;   // (fichier, texte alternatif) -> void
	function boutonMediatheque(rappel, classe) {
		const b = el('button', classe || 'st-bouton', '');
		b.type = 'button';
		b.innerHTML = '<i class="bi bi-images" aria-hidden="true"></i> ';
		b.appendChild(document.createTextNode('Médiathèque'));
		b.title = 'Une image de la médiathèque, ou une image déjà utilisée dans vos visuels';
		b.addEventListener('click', () => ouvrirMediatheque(rappel));
		return b;
	}
	async function ouvrirMediatheque(rappel) {
		pourMedia = rappel;
		const dlg = $('stDlgMedia');
		dlg.showModal();
		const zone = $('stMediaZone');
		zone.replaceChildren(el('p', 'st-vide', 'Chargement…'));
		const prendre = async (url, nom, alt) => {
			try {
				const r = await fetch(url, { credentials: 'same-origin' });
				if (!r.ok) { throw new Error('image introuvable (' + r.status + ')'); }
				const blob = await r.blob();
				dlg.close();
				await pourMedia(new File([blob], nom, { type: blob.type || 'image/jpeg' }), alt);
			} catch (e) { annoncer('Cette image ne se charge pas : ' + e.message, 'erreur'); }
		};
		const section = (titre, items, vide) => {
			const s = el('section');
			s.appendChild(el('h3', 'st-adm__titre', titre));
			const g = el('ul', 'st-grille');
			if (!items.length) { g.appendChild(el('li', 'st-vide', vide)); }
			items.forEach((it) => {
				const li = el('li', 'st-vignette');
				const b = el('button', 'st-vignette__choix');
				b.type = 'button';
				const img = el('img');
				img.src = it.url; img.alt = ''; img.loading = 'lazy';
				b.append(img, el('span', 'st-vignette__nom', it.titre));
				b.setAttribute('aria-label', 'Utiliser « ' + it.titre + ' »');
				b.addEventListener('click', () => prendre(it.url, it.nom, it.alt));
				li.appendChild(b);
				g.appendChild(li);
			});
			s.appendChild(g);
			return s;
		};
		try {
			const j = await api({ action: 'media' });
			zone.replaceChildren(
				section('Médiathèque', j.media.map((m) => ({ url: VISUELS + '?action=media_image&id=' + m.id, nom: 'mediatheque-' + m.id + '.jpg', titre: m.titre, alt: m.alt })), 'La médiathèque est vide pour le moment.'),
				section('Déjà utilisées dans vos visuels', j.utilisees.map((u) => ({ url: VISUELS + '?action=image&id=' + u.visuel + '&f=' + encodeURIComponent(u.fichier), nom: u.fichier, titre: u.alt || u.titre || 'Image', alt: u.alt })), 'Aucune image dans vos visuels pour le moment.'));
		} catch (e) { zone.replaceChildren(el('p', 'st-vide', e.message)); }
	}

	/* ------------------------------------------------ les fenetres */
	function fermable(dlg) {
		dlg.querySelectorAll('[data-fermer]').forEach((b) => b.addEventListener('click', () => dlg.close()));
		dlg.addEventListener('click', (e) => { if (e.target === dlg) { dlg.close(); } });   // un clic sur le fond
	}
	let vueListe = 'mes';
	async function ouvrirListe(vue) {
		vueListe = vue || vueListe;
		const dlg = $('stDlgVisuels');
		if (!dlg.open) { dlg.showModal(); }
		const onglets = [['mes', 'Mes visuels'], ['tous', 'Tous les visuels']];
		if (etat.roles && etat.roles.validateur) { onglets.push(['a_valider', 'À valider' + (etat.aValider ? ' (' + etat.aValider + ')' : '')]); }
		const barre = $('stVues');
		barre.replaceChildren();
		onglets.forEach(([cle, libelle]) => {
			const b = el('button', 'st-format', libelle);
			b.type = 'button';
			b.setAttribute('role', 'tab');
			b.setAttribute('aria-selected', String(cle === vueListe));
			b.addEventListener('click', () => ouvrirListe(cle));
			barre.appendChild(b);
		});
		const ul = $('stListe');
		ul.replaceChildren(el('li', 'st-vide', 'Chargement…'));
		try {
			const j = await api({ action: 'liste', vue: vueListe });
			etat.aValider = j.aValider;
			majVisuelUI();
			ul.replaceChildren();
			if (!j.visuels.length) { ul.appendChild(el('li', 'st-vide', vueListe === 'mes' ? 'Aucun visuel pour le moment : remplissez le formulaire, il s\'enregistre tout seul.' : 'Aucun visuel.')); }
			j.visuels.forEach((x) => {
				const li = el('li', 'st-ligne' + (etat.visuel && etat.visuel.id === x.id ? ' st-ligne--courant' : ''));
				const ouvrir = el('button', 'st-ligne__ouvrir');
				ouvrir.type = 'button';
				const code = (etat.presets[x.preset] || {}).code || x.preset;
				const texte = el('span', 'st-ligne__texte');
				texte.append(el('b', '', x.titre || 'Sans titre'), el('span', '', x.auteur_nom + ' · ' + dateCourte(x.modifie_le)));
				ouvrir.append(el('span', 'st-ligne__code', code), texte, el('span', 'st-badge st-badge--' + x.statut, STATUTS[x.statut]));
				ouvrir.addEventListener('click', async () => { if (await ouvrirVisuel(x.id)) { dlg.close(); } });
				li.appendChild(ouvrir);
				if (String(x.auteur_id) === String(COMPTE) || (etat.roles && etat.roles.admin)) {
					const s = el('button', 'st-icone');
					s.type = 'button';
					s.title = 'Supprimer';
					s.setAttribute('aria-label', 'Supprimer « ' + (x.titre || 'Sans titre') + ' »');
					s.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
					s.addEventListener('click', async () => { if (await supprimer(x.id)) { ouvrirListe(); } });
					li.appendChild(s);
				}
				ul.appendChild(li);
			});
		} catch (e) {
			ul.replaceChildren(el('li', 'st-vide', e.message));
		}
	}
	async function ouvrirHistorique() {
		if (!etat.visuel) { return; }
		await liberer();
		const dlg = $('stDlgHistorique');
		dlg.showModal();
		const ul = $('stVersions');
		ul.replaceChildren(el('li', 'st-vide', 'Chargement…'));
		const MOTIFS = { auto: 'enregistrement automatique', manuel: 'version enregistrée', restauration: 'restauration', duplication: 'copie' };
		try {
			const j = await api({ action: 'historique', id: String(etat.visuel.id) });
			ul.replaceChildren();
			j.versions.forEach((x, i) => {
				const li = el('li', 'st-ligne' + (i === 0 ? ' st-ligne--courant' : ''));
				const texte = el('span', 'st-ligne__texte');
				texte.append(el('b', '', (i === 0 ? 'Version actuelle · ' : '') + dateCourte(x.cree_le)), el('span', '', x.auteur_nom + ' · ' + (MOTIFS[x.motif] || x.motif) + ' · ' + (NOM_FORMAT[x.format] || x.format)));
				li.appendChild(texte);
				if (i > 0 && !etat.lecture) {
					const b = el('button', 'st-bouton', 'Restaurer');
					b.type = 'button';
					b.addEventListener('click', async () => {
						try {
							const r = await post('restaurer', { id: etat.visuel.id, version: x.numero });
							dlg.close();
							await ouvrirPreset(r.visuel.preset, r.visuel);
							annoncer('Version du ' + dateCourte(x.cree_le) + ' restaurée.', 'ok');
						} catch (e) { annoncer(e.message, 'erreur'); dlg.close(); }
					});
					li.appendChild(b);
				}
				ul.appendChild(li);
			});
		} catch (e) {
			ul.replaceChildren(el('li', 'st-vide', e.message));
		}
	}
	async function ouvrirJournal() {
		const dlg = $('stDlgJournal');
		dlg.showModal();
		const corps = $('stJournal');
		corps.replaceChildren();
		try {
			const j = await api({ action: 'journal' });
			if (!j.journal.length) { const tr = el('tr'); const td = el('td', 'st-vide', 'Aucun export pour le moment.'); td.colSpan = 5; tr.appendChild(td); corps.appendChild(tr); }
			j.journal.forEach((x) => {
				const tr = el('tr');
				const code = (etat.presets[x.preset] || {}).code || x.preset;
				[dateCourte(x.cree_le), x.auteur_nom, code + ' · ' + (x.titre || 'Sans titre'), (NOMS_SORTIES[x.sortie] || x.sortie) + (x.format ? ' · ' + (NOM_FORMAT[x.format] || x.format) : ''), x.statut_visuel ? STATUTS[x.statut_visuel] : 'Non enregistré'].forEach((t) => tr.appendChild(el('td', '', t)));
				corps.appendChild(tr);
			});
		} catch (e) {
			const tr = el('tr'); const td = el('td', 'st-vide', e.message); td.colSpan = 5; tr.appendChild(td); corps.appendChild(tr);
		}
	}

	/* ------------------------------------------------ la validation */
	async function soumettre() {
		if (etat.occupe) { return; }
		for (let i = 0; i < 30 && !etat.rapport; i++) { await new Promise((ok) => setTimeout(ok, 100)); }   // le rendu du visuel ouvert
		const erreurs = problemes();
		if (erreurs.bloquant) { annoncer('Complétez le visuel avant de le soumettre : ' + erreurs.liste.filter((x) => x.genre !== 'avertit').map((x) => x.texte).join(' '), 'erreur'); return; }
		const reserves = erreurs.liste.filter((x) => x.genre === 'avertit');
		if (reserves.length && !window.confirm('Ce visuel a ' + reserves.length + ' avertissement' + (reserves.length > 1 ? 's' : '') + ' :\n\n- ' + reserves.slice(0, 6).map((x) => x.texte).join('\n- ') + '\n\nLe soumettre quand même ?')) { return; }
		etat.occupe = true;
		majVisuelUI();
		try {
			const v = await enregistrer('manuel');
			if (!v) { throw new Error(etat.messageSauve || 'Le visuel n\'est pas enregistré.'); }
			const j = await post('soumettre', { id: v.id });
			etat.visuel = j.visuel;
			annoncer('Soumis pour validation. Vous serez prévenu ici de la décision.', 'ok');
		} catch (e) {
			annoncer(e.message, 'erreur');
		} finally {
			etat.occupe = false;
			majVisuelUI();
			majAlertes();
		}
	}
	async function decider(action, motif) {
		try {
			const champs = { id: etat.visuel.id };
			if (motif) { champs.motif = motif; }
			const j = await post(action, champs);
			etat.visuel = j.visuel;
			annoncer(action === 'valider' ? 'Visuel validé.' : 'Visuel refusé : l\'auteur verra le motif.', 'ok');
			const l = await api({ action: 'liste', vue: 'a_valider' });
			etat.aValider = l.aValider;
		} catch (e) {
			annoncer(e.message, 'erreur');
		}
		majVisuelUI();
		majAlertes();
	}

	/* ------------------------------------------------ le branchement (appele par demarrer) */
	function brancherVisuels() {
		['stDlgVisuels', 'stDlgHistorique', 'stDlgJournal', 'stDlgRefus', 'stDlgAdmin', 'stDlgMedia', 'stDlgRaccourcis'].forEach((id) => fermable($(id)));
		$('stAdmin').addEventListener('click', () => ouvrirAdmin('types'));
		$('stOuvrir').addEventListener('click', () => ouvrirListe('mes'));
		$('stNouveau').addEventListener('click', () => nouveauVisuel());
		$('stDupliquer').addEventListener('click', dupliquer);
		$('stHistorique').addEventListener('click', ouvrirHistorique);
		$('stJournalOuvrir').addEventListener('click', ouvrirJournal);
		$('stAnnuler').addEventListener('click', () => allerA(etat.pose - 1));
		$('stRetablir').addEventListener('click', () => allerA(etat.pose + 1));
		$('stSoumettre').addEventListener('click', soumettre);
		$('stValider').addEventListener('click', () => { if (etat.visuel) { decider('valider'); } });
		$('stRefuser').addEventListener('click', () => { $('stMotifRefus').value = ''; $('stDlgRefus').showModal(); focaliser($('stMotifRefus')); });
		$('stFormRefus').addEventListener('submit', () => { const m = $('stMotifRefus').value.trim(); if (m && etat.visuel) { decider('refuser', m); } });
		$('stLegende').addEventListener('input', () => { etat.legende = $('stLegende').value; etat.legendeManuelle = true; $('stLegendeRefaire').hidden = false; suivreModifications(); });
		$('stLegendeRefaire').addEventListener('click', () => { majLegende(true); suivreModifications(); });
		$('stLegendeCopier').addEventListener('click', async () => {
			const z = $('stLegende');
			try { await navigator.clipboard.writeText(z.value); } catch (e) { z.select(); document.execCommand('copy'); }
			annoncer('Légende copiée.', 'ok');
		});
		document.addEventListener('keydown', (e) => {
			if (!(e.ctrlKey || e.metaKey) || e.altKey || String(e.key).toLowerCase() !== 'z' && String(e.key).toLowerCase() !== 'y') { return; }
			const cible = e.target;
			if (cible && (cible.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(cible.tagName))) { return; }   // le texte garde son propre annuler
			e.preventDefault();
			if (String(e.key).toLowerCase() === 'y' || e.shiftKey) { allerA(etat.pose + 1); } else { allerA(etat.pose - 1); }
		});
		window.addEventListener('beforeunload', (e) => { if (etat.serveur === true && !etat.lecture && modifie()) { e.preventDefault(); e.returnValue = ''; } });
		document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden' && etat.serveur === true && !etat.lecture && modifie()) { enregistrer('auto'); } });
	}
	async function initialiserVisuels() {
		try {
			const j = await api({ action: 'moi' });
			etat.serveur = true;
			etat.roles = j.roles;
			etat.obligatoire = j.obligatoire || [];
			etat.reglages = j.reglages || null;
			if (j.roles.validateur) { const l = await api({ action: 'liste', vue: 'a_valider' }); etat.aValider = l.aValider; }
		} catch (e) {
			etat.serveur = false;
			etat.messageSauve = '';
			console.warn('Visuels enregistrés indisponibles :', e.message);
		}
	}

	async function demarrer() {
		try {
			etat.schema = await lireJson(BASE + 'presets/preset.schema.json');
			for (const id of PRESETS) { etat.presets[id] = await lireJson(BASE + 'presets/' + id + '.json'); }
		} catch (e) {
			annoncer('Les presets ne se chargent pas : ' + e.message, 'erreur');
			return;
		}
		await initialiserVisuels();
		brancherVisuels();
		initialiserInterface();
		const choix = $('stPreset');
		remplirTypes();
		/* le dernier type de visuel ouvert, retrouve a la visite suivante */
		const cleDernier = 'studio-uaz.' + COMPTE + '.preset';
		choix.addEventListener('change', async () => {
			try { localStorage.setItem(cleDernier, choix.value); } catch (e) { /* stockage indisponible */ }
			await nouveauVisuel(choix.value);
		});
		if (!PRESETS.length) { annoncer('Aucun preset n\'est disponible.', 'erreur'); return; }
		let dernier = null;
		try { dernier = localStorage.getItem(cleDernier); } catch (e) { /* stockage indisponible */ }
		const proposes = Array.from(choix.options).map((o) => o.value);
		choix.value = proposes.indexOf(dernier) !== -1 ? dernier : proposes[0];
		/* #v=12 : un visuel enregistre (le lien se partage au service) */
		const demande = /^#v=(\d+)$/.exec(location.hash);
		if (!(demande && etat.serveur === true && await ouvrirVisuel(Number(demande[1])))) { await ouvrirPreset(choix.value, null); }

		/* Glisser la photo d'un cadre plein dans l'apercu : le recadrage suit (un simple clic ouvre son champ) */
		let glisse = null;
		$('stVisuel').addEventListener('pointerdown', (e) => {
			const cadre = e.button === 0 && !etat.lecture && e.target.closest('.uaz-image--remplir[data-image]');
			const x = cadre && etat.images[cadre.dataset.image];
			const info = cadre && etat.rapport && etat.rapport.images[cadre.dataset.image];
			if (!x || !info) {
				/* tout autre element : on le deplace (un simple clic le selectionne toujours) */
				const cible = e.button === 0 && !etat.lecture && !(edition && edition.zone === e.target) && e.target.closest('[data-champ], [data-image], [data-libre]');
				if (cible && !cible.classList.contains('uaz-image--remplir')) {
					const id = cible.dataset.champ || cible.dataset.image || cible.dataset.libre;
					const g = geste('deplacer', e, id);
					if (g) { suivreGeste(g, $('stVisuel'), e, true); }
				}
				return;
			}
			e.preventDefault();
			glisse = { x, info, x0: e.clientX, y0: e.clientY, c: Object.assign({}, x.cadrage), bouge: false };
			/* pas de capture a l'appui : un simple clic doit garder sa cible (l'image, qui se selectionne) */
		});
		$('stVisuel').addEventListener('pointermove', (e) => {
			if (!glisse) { return; }
			if (!glisse.bouge && e.buttons === 0) { glisse = null; return; }
			const dx = (e.clientX - glisse.x0) / etat.echelle, dy = (e.clientY - glisse.y0) / etat.echelle;
			if (!glisse.bouge && Math.hypot(dx, dy) * etat.echelle < 4) { return; }
			if (!glisse.bouge) { try { $('stVisuel').setPointerCapture(e.pointerId); } catch (x) { /* deja relache */ } }   // le conteneur, que le rendu ne remplace pas
			glisse.bouge = true;
			const v = glisse.info.visible, l = glisse.info.source[0] * glisse.info.echelle, h = glisse.info.source[1] * glisse.info.echelle;
			glisse.x.cadrage.x = borner(glisse.c.x - dx / l, v[2] / 2, 1 - v[2] / 2);
			glisse.x.cadrage.y = borner(glisse.c.y - dy / h, v[3] / 2, 1 - v[3] / 2);
			planifierRendu();
		});
		const lacher = () => { if (glisse && glisse.bouge) { etat.sansClic = true; } glisse = null; };
		$('stVisuel').addEventListener('pointerup', lacher);
		$('stVisuel').addEventListener('pointercancel', lacher);
		/* Comme dans Figma : un clic choisit (ses proprietes a droite), un double-clic ecrit le texte sur le visuel */
		$('stVisuel').addEventListener('click', (e) => {
			if (etat.sansClic) { etat.sansClic = false; return; }
			if (edition && edition.zone === e.target) { return; }
			const cible = e.target.closest(SELECTEUR);
			if (!cible) { deselectionner(); return; }
			choisirElement(cible, false);
		});
		$('stVisuel').addEventListener('dblclick', (e) => {
			if (etat.lecture || CREATION[etat.outil] || (edition && edition.zone === e.target)) { return; }
			const cible = e.target.closest(SELECTEUR);
			if (!cible) { return; }
			e.preventDefault();
			choisirElement(cible, true);
		});
		$('stToutExporter').addEventListener('click', () => exporter('zip', 1, false));
		$('stZones').addEventListener('change', majZones);
		$('stMarges').addEventListener('change', () => { try { localStorage.setItem('studio-uaz.marges', $('stMarges').checked ? '1' : '0'); } catch (e) { /* stockage indisponible */ } majMarges(); });
		try { $('stMarges').checked = localStorage.getItem('studio-uaz.marges') === '1'; } catch (e) { /* stockage indisponible */ }
		$('stVider').addEventListener('click', () => {
			if (etat.lecture) { return; }
			if (!window.confirm('Vider tous les champs de ce visuel ? Les images sont retirées aussi (l\'historique les garde).')) { return; }
			try { localStorage.removeItem(cleBrouillon()); } catch (e) { /* rien a effacer */ }
			Object.values(etat.images).forEach((x) => URL.revokeObjectURL(x.url));
			etat.images = {};
			etat.miseEnPage = {};
			etat.blocs = [];
			etat.donnees = valeursInitiales(etat.preset);
			etat.legendeManuelle = false;
			construireFormulaire();
			formats();
			planifierRendu();
		});
		const recadrer = () => { ajusterEchelle(); placerSelection(); };
		if (window.ResizeObserver) { new ResizeObserver(recadrer).observe($('stScene')); } else { window.addEventListener('resize', recadrer); }

		try {
			const r = await fetch(EXPORT + '?action=sante', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const j = await r.json();
			etat.service = !!j.ok;
		} catch (e) {
			etat.service = false;
		}
		$('stService').textContent = etat.service ? 'Service de rendu prêt.' : 'Le service de rendu ne répond pas : l\'export est impossible pour le moment.';
		$('stService').className = 'st-service ' + (etat.service ? 'st-service--ok' : 'st-service--ko');
		majAlertes();
	}
	demarrer();
})();
