/* =============================================================================
   Studio UAZ — les formats de l'etape 3 : carre, story, A4, PDF

   - Chaque preset, rempli avec ses donnees de test, dans chacun des quatre
     formats : rien ne manque, rien ne deborde, aucun avertissement (en story,
     aucune information cle sous l'interface d'Instagram).
   - Story : une information cle dans les 250 px du haut est signalee.
   - Fond perdu (PDF pour l'imprimeur) : le visuel grandit de 3 mm de chaque
     cote, et rien ne bouge a l'interieur du format fini.
   - QR code des inscriptions : a la place de l'adresse ecrite, et seulement
     quand une adresse est saisie.
   - Communique en A4 au texte long : deux pages, en-tete et pied repetes,
     aucun mot perdu, pas de ligne seule de part et d'autre de la coupe.
   - Export (moteur du service) : PDF a la taille du format, polices
     incorporees en vraies polices (pas de « Type 3 »), PDF de l'imprimeur,
     PNG refuse pour deux pages, « Tout exporter » (8 fichiers).

   node tests/studio/test-formats.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, rendreVisuel, ErreurRendu, preset, lireJson, imagesDe, verifier, bilan } from './outils.mjs';
import { rendreLot, FOND_PERDU, MM } from '../../outils/studio-rendu/moteur.js';

const FORMATS = ['4:5', '1:1', '9:16', 'A4'];
const PRESETS = ['ann', 'com', 'evt', 'fel', 'ins', 'rec'];
const jeuDe = async (id) => lireJson(path.join(DONNEES, id === 'evt' ? 'evt-trs.json' : id + '-test.json'));
/* Les images de test (illustration ou unie) servies en memoire */
async function chargerImages(jeu, nav) {
	const brutes = await imagesDe(jeu, nav);
	const enMemoire = {}, images = {};
	for (const [k, im] of Object.entries(brutes)) {
		enMemoire['/__images/' + k] = { type: im.type, corps: Buffer.from(im.base64, 'base64') };
		images[k] = { src: 'http://studio.local/__images/' + k, alt: im.alt, fondre: im.fondre, ajustement: im.ajustement };
	}
	return { brutes, enMemoire, images };
}
/* Un texte fictif de n paragraphes */
const paragraphes = (n) => Array.from({ length: n }, (_, i) => 'Paragraphe de test numéro ' + (i + 1) + ' : ce texte fictif éprouve la mise en page du communiqué imprimé. Il ne contient aucune donnée réelle ; il décrit des modalités d\'exemple, des dates d\'exemple et des services d\'exemple, comme le ferait un vrai communiqué.').join('\n');
const pdfTexte = (octets) => octets.toString('latin1');
const compter = (s, motif) => (s.match(motif) || []).length;
const boite = (s) => ((s.match(/\/MediaBox\s*\[\s*0 0 ([\d.]+) ([\d.]+)\s*\]/) || []).slice(1).map(Number));

const nav = await lancerNavigateur({ executable: CHROME });
try {
	/* --- 1. Les 24 visuels de test, et les variantes sans image en story */
	for (const id of PRESETS) {
		const p = await preset(id);
		const jeu = await jeuDe(id);
		const { enMemoire, images } = await chargerImages(jeu, nav);
		const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1200, hauteur: 2000, enMemoire });
		try {
			for (const format of FORMATS) {
				const r = await page.evaluate((x) => window.StudioPage.rendre(x), { preset: p, format, donnees: jeu.donnees, images });
				verifier(id.toUpperCase() + ' ' + format + ' : données de test, rien ne manque ni ne déborde, aucun avertissement',
					r.ok && !r.avertissements.length && r.pages === 1, JSON.stringify({ m: r.manquants, d: r.debordements, a: r.avertissements, p: r.pages }));
			}
			/* story sans image facultative : le titre grandit, mais reste hors des 250 px du haut */
			if (id === 'evt' || id === 'ann') {
				const r = await page.evaluate((x) => window.StudioPage.rendre(x), { preset: p, format: '9:16', donnees: Object.assign({}, jeu.donnees, { retenir: '' }), images: {} });
				verifier(id.toUpperCase() + ' 9:16 sans image : le titre descend hors de la zone d\'Instagram', r.ok && !r.avertissements.length, JSON.stringify(r.avertissements));
			}
		} finally {
			await contexte.close();
		}
	}

	const evt = await preset('evt');
	const trs = await jeuDe('evt');
	const imTrs = await chargerImages(trs, nav);
	const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1200, hauteur: 2400, enMemoire: imTrs.enMemoire });
	try {
		/* --- 2. Story : une information cle sous l'interface d'Instagram */
		const casse = JSON.parse(JSON.stringify(evt));
		const titreStory = casse.formats['9:16'].rangees.find((r) => r.id === 'titre').cellules[0];
		titreStory.blocs = titreStory.blocs.filter((b) => !(b.type === 'ressort' && b.sauf));
		let r = await page.evaluate((x) => window.StudioPage.rendre(x), { preset: casse, format: '9:16', donnees: trs.donnees, images: {} });
		verifier('story : un titre collé à l\'en-tête (sans le ressort « sauf image ») est signalé', r.avertissements.some((a) => a.message.indexOf('Instagram') !== -1), JSON.stringify(r.avertissements));

		/* --- 3. Fond perdu : chaque texte de l'A4 reste a sa place dans le format fini */
		for (const id of PRESETS) {
			const p = await preset(id);
			const jeu = await jeuDe(id);
			const ims = await chargerImages(jeu, nav);
			const pg = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1000, hauteur: 1300, enMemoire: ims.enMemoire });
			try {
				const mesure = (fp) => pg.page.evaluate(async ({ x, fp }) => {
					await window.StudioPage.rendre(Object.assign({}, x, { fondPerdu: fp }));
					const v = document.querySelector('.uaz-visuel');
					const o = v.getBoundingClientRect();
					return {
						l: v.offsetWidth, h: v.offsetHeight,
						textes: Array.from(v.querySelectorAll('.uaz-texte, .uaz-image, .uaz-logo')).map((e) => { const b = e.getBoundingClientRect(); return [b.left - o.left - fp, b.top - o.top - fp, b.width, b.height]; }),
					};
				}, { x: { preset: p, format: 'A4', donnees: jeu.donnees, images: ims.images }, fp });
				const sans = await mesure(0), avecFp = await mesure(FOND_PERDU);
				const ecart = Math.max(...sans.textes.map((t, i) => Math.max(...t.map((v, k) => Math.abs(v - avecFp.textes[i][k])))));
				verifier(id.toUpperCase() + ' A4 avec fond perdu : ' + (794 + 2 * FOND_PERDU).toFixed(1) + ' x ' + (1123 + 2 * FOND_PERDU).toFixed(1) + ' px, rien ne bouge dans le format fini (écart ' + ecart.toFixed(3) + ' px)',
					Math.abs(avecFp.l - (794 + 2 * FOND_PERDU)) < 1 && Math.abs(avecFp.h - (1123 + 2 * FOND_PERDU)) < 1 && sans.textes.length === avecFp.textes.length && ecart < 0.02,
					JSON.stringify({ l: avecFp.l, h: avecFp.h, n: [sans.textes.length, avecFp.textes.length], ecart }));
			} finally {
				await pg.contexte.close();
			}
		}

		/* --- 4. QR code des inscriptions */
		const ins = await preset('ins');
		const jeuIns = await jeuDe('ins');
		const qr = (donnees, format) => page.evaluate(async (x) => {
			await window.StudioPage.rendre(x);
			const svg = document.querySelector('.uaz-qr svg');
			const textes = Array.from(document.querySelectorAll('.uaz-texte')).map((e) => e.textContent.replace(/​/g, ''));
			return { svg: !!svg, cote: svg ? +svg.getAttribute('width') : 0, vue: svg ? svg.getAttribute('viewBox') : '', adresseEcrite: textes.indexOf('registrar.zurcher.edu.mg') !== -1 };
		}, { preset: ins, format, donnees, images: {} });
		r = await qr(Object.assign({}, jeuIns.donnees, { qrUrl: 'https://exemple.mg/inscription-test' }), 'A4');
		const modules = +r.vue.split(' ')[2];
		verifier('INS A4 avec adresse : le QR code (modules entiers, zone de silence) remplace l\'adresse écrite', r.svg && !r.adresseEcrite && r.cote <= 150 && r.cote % modules === 0, JSON.stringify(r));
		r = await qr(Object.assign({}, jeuIns.donnees, { qrUrl: 'https://exemple.mg/inscription-test' }), '9:16');
		verifier('INS 9:16 avec adresse : le QR code', r.svg && !r.adresseEcrite, JSON.stringify(r));
		r = await qr(jeuIns.donnees, 'A4');
		verifier('INS A4 sans adresse : l\'adresse écrite, pas de QR code', !r.svg && r.adresseEcrite, JSON.stringify(r));
		r = await qr(Object.assign({}, jeuIns.donnees, { qrUrl: 'https://exemple.mg/inscription-test' }), '4:5');
		verifier('INS 4:5 : pas de QR code (Story et A4 seulement)', !r.svg && r.adresseEcrite, JSON.stringify(r));

		/* --- 5. Le communique en A4 sur deux pages */
		const com = await preset('com');
		const jeuCom = await jeuDe('com');
		const document2 = (suite) => page.evaluate(async (x) => {
			const r = await window.StudioPage.rendre(x);
			const pages = Array.from(document.querySelectorAll('#visuel .uaz-visuel'));
			const mots = (e) => (e ? e.textContent.split(/\s+/).filter(Boolean).length : 0);
			return {
				ok: r.ok, pages: r.pages, debordements: r.debordements,
				parPage: pages.map((v) => {
					const f = v.querySelector('[data-flux]');
					const ps = f ? Array.from(f.querySelectorAll('p')) : [];
					return {
						h: v.offsetHeight, pied: v.querySelector('[data-rangee="pied"]').textContent, signature: v.textContent.indexOf('Signataire') !== -1,
						renseignements: !!v.querySelector('[data-rangee="renseignements"]'), entete: !!v.querySelector('[data-rangee="entete"] .uaz-logo'),
						motsFlux: ps.reduce((n, x) => n + mots(x), 0), premier: ps.length ? mots(ps[0]) : 0, dernier: ps.length ? mots(ps[ps.length - 1]) : 0,
					};
				}),
			};
		}, { preset: com, format: 'A4', donnees: Object.assign({}, jeuCom.donnees, { suite }), images: {} });
		const long = paragraphes(8);
		const totalMots = long.split(/\s+/).filter(Boolean).length;
		r = await document2(long);
		const [p1, p2] = r.parPage;
		verifier('COM A4, texte long : deux pages, exportable', r.ok && r.pages === 2 && r.parPage.length === 2, JSON.stringify(r));
		if (p1 && p2) {
			verifier('les deux pages : en-tête et pied répétés, « Page 1 sur 2 » et « Page 2 sur 2 »', p1.entete && p2.entete && p1.pied.indexOf('Page 1 sur 2') !== -1 && p2.pied.indexOf('Page 2 sur 2') !== -1, p1.pied + ' | ' + p2.pied);
			verifier('aucun mot perdu ni répété entre les pages (' + totalMots + ' mots)', p1.motsFlux + p2.motsFlux === totalMots, p1.motsFlux + ' + ' + p2.motsFlux);
			verifier('signature et renseignements sur la dernière page seulement', !p1.signature && p2.signature && !p1.renseignements && p2.renseignements, JSON.stringify([p1, p2]));
			verifier('pas de ligne seule à la coupe (10 mots au moins de chaque côté)', p1.dernier >= 10 && p2.premier >= 10, p1.dernier + ' / ' + p2.premier);
		}
		r = await document2('Un seul paragraphe court de test.');
		verifier('COM A4, suite courte : une seule page, sans « Page 1 sur 2 »', r.ok && r.pages === 1 && r.parPage[0].pied.indexOf('Page') === -1, JSON.stringify(r));
		r = await document2(paragraphes(30));
		verifier('COM A4, texte trop long même sur deux pages : « Texte trop long » (la suite)', !r.ok && r.debordements.some((d) => d.champs.indexOf('suite') !== -1), JSON.stringify(r.debordements));
	} finally {
		await contexte.close();
	}

	/* --- 6. L'export par le moteur du service */
	const tailles = { '4:5': [1080, 1350], '1:1': [1080, 1080], '9:16': [1080, 1920], 'A4': [794, 1123] };
	/* Chrome cale la page sur sa grille (moins d'1 px d'ecart) ; le visuel la couvre, sinon le moteur refuse */
	const proche = (pt, px) => Math.abs(pt / 0.75 - px) < 1;
	for (const format of FORMATS) {
		const x = await rendreVisuel(nav, { preset: evt, format, donnees: trs.donnees, images: imTrs.brutes, sortie: 'pdf' }, { dossier: STUDIO });
		const s = pdfTexte(x.corps);
		const [l, h] = boite(s);
		verifier('PDF ' + format + ' : ' + tailles[format].join(' x ') + ' px (' + l + ' x ' + h + ' pt), polices incorporées (TrueType, aucun Type 3), texte sélectionnable',
			proche(l, tailles[format][0]) && proche(h, tailles[format][1]) && compter(s, /\/FontFile2/g) > 0 && compter(s, /\/Type3/g) === 0 && compter(s, /\/ToUnicode/g) > 0,
			JSON.stringify({ l, h, polices: compter(s, /\/FontFile2/g), type3: compter(s, /\/Type3/g) }));
	}
	let x = await rendreVisuel(nav, { preset: evt, format: 'A4', donnees: trs.donnees, images: imTrs.brutes, sortie: 'pdf', impression: true }, { dossier: STUDIO });
	let [l, h] = boite(pdfTexte(x.corps));
	const attendu = [794 + 20 * MM, 1123 + 20 * MM];
	verifier('PDF de l\'imprimeur : la feuille fait le format fini + 10 mm de chaque côté (fond perdu 3 mm, traits de coupe) : ' + (l / 72 * 25.4).toFixed(1) + ' x ' + (h / 72 * 25.4).toFixed(1) + ' mm',
		proche(l, attendu[0]) && proche(h, attendu[1]), JSON.stringify({ l, h, attendu }));
	try {
		await rendreVisuel(nav, { preset: evt, format: '4:5', donnees: trs.donnees, images: imTrs.brutes, sortie: 'pdf', impression: true }, { dossier: STUDIO });
		verifier('fond perdu refusé hors A4', false, 'accepté');
	} catch (e) {
		verifier('fond perdu refusé hors A4', e instanceof ErreurRendu && e.code === 422, e.message);
	}
	const com = await preset('com');
	const jeuCom = await jeuDe('com');
	const deuxPages = { preset: com, format: 'A4', donnees: Object.assign({}, jeuCom.donnees, { suite: paragraphes(8) }), images: {} };
	x = await rendreVisuel(nav, Object.assign({ sortie: 'pdf' }, deuxPages), { dossier: STUDIO });
	verifier('PDF du communiqué au texte long : deux pages', compter(pdfTexte(x.corps), /\/Type\s*\/Page[^s]/g) === 2, compter(pdfTexte(x.corps), /\/Type\s*\/Page[^s]/g));
	try {
		await rendreVisuel(nav, Object.assign({ sortie: 'png' }, deuxPages), { dossier: STUDIO });
		verifier('PNG d\'un document de deux pages refusé', false, 'accepté');
	} catch (e) {
		verifier('PNG d\'un document de deux pages refusé (« exportez-le en PDF »)', e instanceof ErreurRendu && e.code === 422 && e.message.indexOf('PDF') !== -1, e.message);
	}
	const t0 = Date.now();
	const lot = await rendreLot(nav, {
		preset: evt, donnees: trs.donnees, images: imTrs.brutes,
		elements: [['4:5', 'png'], ['4:5', 'pdf'], ['1:1', 'png'], ['1:1', 'pdf'], ['9:16', 'png'], ['9:16', 'pdf'], ['A4', 'pdf'], ['A4', 'pdf', true]].map(([format, sortie, impression]) => ({ format, sortie, impression: !!impression })),
	}, { dossier: STUDIO });
	const duree = Date.now() - t0;
	verifier('« Tout exporter » : 8 fichiers (PNG et PDF par format, PDF de l\'imprimeur), en ' + (duree / 1000).toFixed(1) + ' s (moins de 20 s)',
		lot.length === 8 && lot.filter((f) => f.type === 'image/png').length === 3 && lot.filter((f) => f.impression).length === 1 && lot.every((f) => f.corps.length > 1000) && duree < 20000,
		JSON.stringify(lot.map((f) => [f.format, f.sortie, f.impression, f.corps.length])));
	try {
		await rendreLot(nav, { preset: evt, donnees: Object.assign({}, trs.donnees, { titre: 'Un titre de test bien trop long '.repeat(30) }), images: imTrs.brutes, elements: [{ format: '1:1', sortie: 'png' }] }, { dossier: STUDIO });
		verifier('« Tout exporter » refusé quand un format déborde, avec le format nommé', false, 'accepté');
	} catch (e) {
		verifier('« Tout exporter » refusé quand un format déborde, avec le format nommé', e instanceof ErreurRendu && e.code === 422 && e.format === '1:1', e.message + ' ' + e.format);
	}
} finally {
	await nav.close();
}
bilan();
