/* =============================================================================
   Studio UAZ — rendre un visuel dans Chromium sans interface

   Partage par le service (serveur.js) et les tests (tests/studio/).
   La page est src/studio/rendu.html, servie depuis le disque sous une origine
   fictive (http://studio.local) : meme charte, meme moteur que l'apercu de
   l'editeur. Toute autre requete est coupee : le visuel ne charge rien de
   l'exterieur, et les images envoyees ne vont nulle part ailleurs.
   ========================================================================== */
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

export const ORIGINE = 'http://studio.local';
const TYPES = {
	'.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
	'.json': 'application/json; charset=utf-8', '.woff2': 'font/woff2', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
	'.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.txt': 'text/plain; charset=utf-8',
};
const TYPES_IMAGE = ['image/jpeg', 'image/png', 'image/webp'];
const ID = /^[a-zA-Z][a-zA-Z0-9]{0,30}$/;

export class ErreurRendu extends Error {
	constructor(code, message, rapport) { super(message); this.code = code; this.rapport = rapport || null; }
}

export function lancerNavigateur({ executable } = {}) {
	return chromium.launch({
		executablePath: executable || undefined,
		args: ['--disable-gpu', '--font-render-hinting=none', '--disable-lcd-text', '--disable-dev-shm-usage'],
	});
}

/* Sert un fichier du dossier (jamais au-dessus), ou un fichier fourni en memoire. */
async function servir(route, dossier, enMemoire) {
	const url = new URL(route.request().url());
	if (url.origin !== ORIGINE) { return route.abort('blockedbyclient'); }
	const chemin = decodeURIComponent(url.pathname);
	if (enMemoire[chemin]) { return route.fulfill({ status: 200, contentType: enMemoire[chemin].type, body: enMemoire[chemin].corps }); }
	const base = path.resolve(dossier);
	const absolu = path.resolve(base, '.' + chemin);
	if (absolu !== base && !absolu.startsWith(base + path.sep)) { return route.fulfill({ status: 404, body: '' }); }
	try {
		return route.fulfill({ status: 200, contentType: TYPES[path.extname(absolu).toLowerCase()] || 'application/octet-stream', body: await readFile(absolu) });
	} catch {
		return route.fulfill({ status: 404, body: '' });
	}
}

/* Une page isolee (son propre contexte) qui ne voit que « dossier » et les fichiers en memoire. */
export async function ouvrirPage(navigateur, { dossier, chemin, largeur, hauteur, echelle = 1, enMemoire = {}, delai = 15000 }) {
	const contexte = await navigateur.newContext({
		viewport: { width: largeur, height: hauteur }, deviceScaleFactor: echelle,
		javaScriptEnabled: true, serviceWorkers: 'block', acceptDownloads: false, locale: 'fr-FR', timezoneId: 'Indian/Antananarivo',
	});
	await contexte.route('**/*', (route) => servir(route, dossier, enMemoire));
	const page = await contexte.newPage();
	page.setDefaultTimeout(delai);
	await page.goto(ORIGINE + chemin, { waitUntil: 'load', timeout: delai });
	return { contexte, page };
}

export const MM = 96 / 25.4;              // px par millimetre
export const FOND_PERDU = 3 * MM;          // 3 mm, pour l'imprimeur (A4)
const SORTIES = ['png', 'jpg', 'pdf'];

/* Le cadrage et les reglages d'une image, en nombres bornes (le moteur borne aussi) */
const nombre = (v, min, max, defaut) => (Number.isFinite(Number(v)) ? Math.max(min, Math.min(max, Number(v))) : defaut);
function cadrageDe(c) {
	return c && typeof c === 'object' ? { x: nombre(c.x, 0, 1, 0.5), y: nombre(c.y, 0, 1, 0.5), zoom: nombre(c.zoom, 1, 4, 1) } : null;
}
function reglagesDe(r) {
	if (!r || typeof r !== 'object') { return null; }
	return {
		lumiere: nombre(r.lumiere, -100, 100, 0), contraste: nombre(r.contraste, -100, 100, 0), saturation: nombre(r.saturation, -100, 100, 0),
		mode: ['couleur', 'nb', 'duotone'].includes(r.mode) ? r.mode : 'couleur',
	};
}

/* Les images envoyees, servies en memoire sous /__images/<id> */
function imagesEnMemoire(d) {
	const enMemoire = {};
	const images = {};
	for (const [id, im] of Object.entries(d.images || {})) {
		if (!ID.test(id) || !im || !TYPES_IMAGE.includes(im.type) || typeof im.base64 !== 'string') { continue; }
		enMemoire['/__images/' + id] = { type: im.type, corps: Buffer.from(im.base64, 'base64') };
		images[id] = { src: ORIGINE + '/__images/' + id, alt: String(im.alt || ''), fondre: im.fondre, ajustement: im.ajustement, cadrage: cadrageDe(im.cadrage), reglages: reglagesDe(im.reglages) };
	}
	return { enMemoire, images };
}

/* Une page rendue pour un format (avec ou sans fond perdu) ; leve ErreurRendu (422) si le visuel n'est pas exportable. */
async function pageRendue(navigateur, d, format, impression, { dossier, delai }) {
	const fmt = d && d.preset && d.preset.formats ? d.preset.formats[format] : null;
	if (!fmt || !Number.isInteger(fmt.largeur) || !Number.isInteger(fmt.hauteur)) { throw new ErreurRendu(422, 'Format inconnu pour ce preset.'); }
	const fondPerdu = impression ? FOND_PERDU : 0;
	const { enMemoire, images } = imagesEnMemoire(d);
	const { contexte, page } = await ouvrirPage(navigateur, {
		dossier, chemin: '/rendu.html', largeur: Math.ceil(fmt.largeur + 2 * fondPerdu), hauteur: Math.ceil(fmt.hauteur + 2 * fondPerdu),
		echelle: d.echelle === 2 ? 2 : 1, enMemoire, delai,
	});
	try {
		const rapport = await page.evaluate((x) => window.StudioPage.rendre(x), {
			preset: d.preset, format, donnees: d.donnees || {}, images, fondPerdu, miseEnPage: (d.miseEnPage && d.miseEnPage[format]) || d.miseEnPageFormat || {}, blocs: Array.isArray(d.blocs) ? d.blocs.slice(0, 40) : [], titre: typeof d.titre === 'string' ? d.titre.slice(0, 120) : '',
		});
		if (!rapport.ok) {
			throw new ErreurRendu(422, rapport.erreurs && rapport.erreurs.length ? 'Le preset ne respecte pas la charte.' : "Le visuel n'est pas prêt à être exporté.", rapport);
		}
		return { contexte, page, fmt, rapport };
	} catch (e) {
		await contexte.close();
		throw e;
	}
}

/* La taille de la premiere page d'un PDF, en px (MediaBox en points), ou null */
export function taillePdf(corps) {
	const m = /\/MediaBox\s*\[\s*0 0 ([\d.]+) ([\d.]+)\s*\]/.exec(corps.toString('latin1'));
	return m ? [Number(m[1]) / 0.75, Number(m[2]) / 0.75] : null;
}

/* Une sortie d'une page deja rendue : PNG ou JPG (une seule page), ou PDF (vectoriel, toutes les pages). */
async function sortir(page, fmt, rapport, sortie, { impression = false, qualite = 90 } = {}) {
	if (sortie === 'pdf') {
		let taille = await page.evaluate((o) => window.StudioPage.imprimer(o), { traits: impression, fondPerdu: impression ? FOND_PERDU : 0 });
		const pdf = () => page.pdf({ width: taille.largeur + 'px', height: taille.hauteur + 'px', printBackground: true, preferCSSPageSize: true, tagged: true });
		let corps = await pdf();
		/* Chrome a cale la page sur sa grille : la page reprend cette taille, le visuel la couvre (rendu.html) */
		const reelle = taillePdf(corps);
		if (reelle && (Math.abs(reelle[0] - taille.largeur) > 0.01 || Math.abs(reelle[1] - taille.hauteur) > 0.01)) {
			taille = await page.evaluate((t) => window.StudioPage.taillePage(t[0], t[1]), reelle);
			corps = await pdf();
		}
		if (!taille.couvre) { throw new ErreurRendu(500, 'La page du PDF ne serait pas couverte entière.'); }
		return { corps, type: 'application/pdf' };
	}
	if (rapport.pages > 1) { throw new ErreurRendu(422, 'Ce document fait ' + rapport.pages + ' pages : exportez-le en PDF.', rapport); }
	const jpeg = sortie === 'jpg';
	const corps = await page.screenshot({
		type: jpeg ? 'jpeg' : 'png', quality: jpeg ? Math.min(100, Math.max(50, qualite || 90)) : undefined,
		clip: { x: 0, y: 0, width: fmt.largeur, height: fmt.hauteur }, animations: 'disabled', caret: 'hide', scale: 'device',
	});
	return { corps, type: jpeg ? 'image/jpeg' : 'image/png' };
}

/**
 * Rend un visuel.
 * d : { preset, format, donnees, images: { id: { type, base64, alt, fondre, ajustement, cadrage, reglages } }, sortie: png|jpg|pdf,
 *       echelle: 1|2, qualite, impression (PDF A4 pour l'imprimeur : fond perdu 3 mm et traits de coupe), titre (du PDF) }
 * Renvoie { corps (Buffer), type (mime), rapport } ; leve ErreurRendu (422) si le visuel n'est pas exportable.
 */
export async function rendreVisuel(navigateur, d, { dossier, delai = 15000 }) {
	if (!SORTIES.includes(d && d.sortie)) { throw new ErreurRendu(422, 'Sortie inconnue (png, jpg ou pdf).'); }
	const impression = !!d.impression;
	if (impression && (d.sortie !== 'pdf' || d.format !== 'A4')) { throw new ErreurRendu(422, 'Le fond perdu et les traits de coupe sont pour le PDF A4.'); }
	const { contexte, page, fmt, rapport } = await pageRendue(navigateur, d, d.format, impression, { dossier, delai });
	try {
		const r = await sortir(page, fmt, rapport, d.sortie, { impression, qualite: d.qualite });
		return Object.assign(r, { rapport });
	} finally {
		await contexte.close();
	}
}

/**
 * Tout exporter : plusieurs sorties d'un meme visuel, une page rendue par format
 * (et une de plus pour le PDF de l'imprimeur).
 * d : { preset, donnees, images, titre, elements: [{ format, sortie, impression }] }
 * Renvoie [{ format, sortie, impression, corps, type }] ; leve ErreurRendu (422, avec le format) au premier format pas pret.
 */
export async function rendreLot(navigateur, d, { dossier, delai = 15000 }) {
	const elements = Array.isArray(d && d.elements) ? d.elements.slice(0, 12) : [];
	if (!elements.length) { throw new ErreurRendu(422, 'Rien à exporter.'); }
	const groupes = new Map();
	for (const x of elements) {
		if (!SORTIES.includes(x.sortie)) { throw new ErreurRendu(422, 'Sortie inconnue (png, jpg ou pdf).'); }
		if (x.impression && (x.sortie !== 'pdf' || x.format !== 'A4')) { throw new ErreurRendu(422, 'Le fond perdu et les traits de coupe sont pour le PDF A4.'); }
		const cle = x.format + (x.impression ? '|impression' : '');
		if (!groupes.has(cle)) { groupes.set(cle, []); }
		groupes.get(cle).push(x);
	}
	const fichiers = [];
	for (const liste of groupes.values()) {
		const { format, impression } = liste[0];
		let rendu;
		try {
			rendu = await pageRendue(navigateur, Object.assign({}, d, { echelle: 1 }), format, !!impression, { dossier, delai });
		} catch (e) {
			if (e instanceof ErreurRendu) { e.format = format; }
			throw e;
		}
		try {
			/* les images avant le PDF : imprimer() deplace les pages dans leurs planches */
			const ordre = liste.slice().sort((a, b) => (a.sortie === 'pdf') - (b.sortie === 'pdf'));
			for (const x of ordre) {
				if (x.sortie !== 'pdf' && rendu.rapport.pages > 1) { continue; }   // deux pages : le PDF seulement
				const r = await sortir(rendu.page, rendu.fmt, rendu.rapport, x.sortie, { impression: !!x.impression });
				fichiers.push({ format, sortie: x.sortie, impression: !!x.impression, corps: r.corps, type: r.type });
			}
		} finally {
			await rendu.contexte.close();
		}
	}
	return fichiers;
}
