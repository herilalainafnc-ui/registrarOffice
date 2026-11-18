/* =============================================================================
   Studio UAZ — outils communs des tests (tests/studio/*.mjs)

   Les tests rendent dans le meme Chromium que le service (outils/studio-rendu/
   moteur.js). En local : le Chrome installe (variable CHROME pour un autre
   chemin) ; playwright-core vient de outils/studio-rendu/node_modules
   (npm install dans ce dossier).
   ========================================================================== */
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const RACINE = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
export const STUDIO = path.join(RACINE, 'src/studio');
export const DONNEES = path.join(RACINE, 'tests/studio/donnees');
export const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const moteur = await import(pathToFileURL(path.join(RACINE, 'outils/studio-rendu/moteur.js')).href);
export const { lancerNavigateur, rendreVisuel, ouvrirPage, ErreurRendu, ORIGINE } = moteur;

export async function lireJson(chemin) { return JSON.parse(await readFile(chemin, 'utf8')); }
export async function preset(id) { return lireJson(path.join(STUDIO, 'presets', id + '.json')); }
export async function schema() { return lireJson(path.join(STUDIO, 'presets/preset.schema.json')); }

/*/* Les images d'un jeu de donnees, pretes pour le service (base64) : un fichier
   (chemin depuis la racine du depot), ou une image unie (« unie » : sa couleur,
   jamais la photo d'une vraie personne dans les donnees de test ; 800 x 1000 px, ou
   « largeur » et « hauteur »). */
export async function imagesDe(jeu, navigateur = null) {
	const sortie = {};
	for (const [id, im] of Object.entries(jeu.images || {})) {
		const base64 = im.unie ? await imageUnie(navigateur, im.unie, im.largeur || 800, im.hauteur || 1000) : (await readFile(path.join(RACINE, im.fichier))).toString('base64');
		sortie[id] = Object.assign({}, im, { base64 });
		delete sortie[id].fichier;
		delete sortie[id].unie;
	}
	return sortie;
}

/* Une maquette du kit (docs/studio-uaz/reference/...), sans l'outil de maquette : <x-dc>,
   <helmet>, support.js et le script DC partent ; police et images locales. A ouvrir avec
   ouvrirPage(..., { dossier: RACINE, enMemoire: { '/__maquette.html': ... } }). */
export async function maquetteHtml(chemin) {
	const brut = await readFile(path.join(RACINE, 'docs/studio-uaz/reference', chemin), 'utf8');
	const dc = brut.slice(brut.indexOf('<x-dc>') + 6, brut.indexOf('</x-dc>'));
	const helmet = dc.slice(dc.indexOf('<helmet>'), dc.indexOf('</helmet>') + 9);
	const styles = (helmet.match(/<style>[\s\S]*?<\/style>/g) || []).join('\n');
	const corps = dc.replace(helmet, '')
		.replace(/\/_blob\/108135284b2c3a3354f5c6300374c69e/g, '/src/studio/images/logo-uaz.jpg')
		.replace(/\/_blob\/2b8b17447f81058229b40e34ac59937a/g, '/docs/studio-uaz/assets/illustration-trs.jpg');
	return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><link rel="stylesheet" href="/src/studio/charte.css">' + styles + '</head><body>' + corps + '</body></html>';
}

/* Photographie une maquette du kit a sa taille (1080 x 1350 par defaut). */
export async function photographierMaquette(navigateur, chemin, largeur = 1080, hauteur = 1350) {
	const { contexte, page } = await ouvrirPage(navigateur, {
		dossier: RACINE, chemin: '/__maquette.html', largeur, hauteur,
		enMemoire: { '/__maquette.html': { type: 'text/html; charset=utf-8', corps: Buffer.from(await maquetteHtml(chemin)) } },
	});
	try {
		await page.evaluate(async () => { await document.fonts.ready; await Promise.all(Array.from(document.images).map((i) => i.decode().catch(() => null))); });
		return await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width: largeur, height: hauteur } });
	} finally {
		await contexte.close();
	}
}

/* Une image d'une seule couleur (PNG, base64) : la zone image vide des maquettes. */
export async function imageUnie(navigateur, couleur, largeur = 600, hauteur = 600) {
	const { contexte, page } = await ouvrirPage(navigateur, {
		dossier: STUDIO, chemin: '/__vide.html', largeur: 100, hauteur: 100,
		enMemoire: { '/__vide.html': { type: 'text/html; charset=utf-8', corps: Buffer.from('<!doctype html><meta charset="utf-8">') } },
	});
	try {
		return await page.evaluate(async ({ couleur, largeur, hauteur }) => {
			const c = new OffscreenCanvas(largeur, hauteur);
			const g = c.getContext('2d');
			g.fillStyle = couleur;
			g.fillRect(0, 0, largeur, hauteur);
			const o = new Uint8Array(await (await c.convertToBlob({ type: 'image/png' })).arrayBuffer());
			let b = '';
			for (let i = 0; i < o.length; i += 0x8000) { b += String.fromCharCode.apply(null, o.subarray(i, i + 0x8000)); }
			return btoa(b);
		}, { couleur, largeur, hauteur });
	} finally {
		await contexte.close();
	}
}

/* Petit compteur de verifications, comme les tests PHP (tests/test_*.php). */
let total = 0, echecs = 0;
export function verifier(nom, ok, detail) {
	total++;
	if (!ok) { echecs++; console.log('ÉCHEC  ' + nom + (detail !== undefined ? '\n        ' + detail : '')); } else { console.log('ok     ' + nom); }
}
export function bilan() {
	console.log('\n' + (total - echecs) + ' / ' + total + ' vérifications réussies.');
	process.exitCode = echecs ? 1 : 0;
}

/* Compare deux PNG de meme taille dans le navigateur : part des pixels qui
   different (un canal ecarte de plus de « seuil ») et image des differences. */
export async function comparer(navigateur, pngA, pngB, { seuil = 32, sortieDiff = null } = {}) {
	const { contexte, page } = await ouvrirPage(navigateur, {
		dossier: STUDIO, chemin: '/__comparer.html', largeur: 200, hauteur: 200,
		enMemoire: { '/__comparer.html': { type: 'text/html; charset=utf-8', corps: Buffer.from('<!doctype html><meta charset="utf-8"><body></body>') } },
	});
	try {
		const r = await page.evaluate(async ({ a, b, seuil }) => {
			const charger = async (b64) => createImageBitmap(await (await fetch('data:image/png;base64,' + b64)).blob());
			const [ia, ib] = await Promise.all([charger(a), charger(b)]);
			if (ia.width !== ib.width || ia.height !== ib.height) { return { erreur: 'tailles ' + ia.width + 'x' + ia.height + ' et ' + ib.width + 'x' + ib.height }; }
			const toile = (img) => { const c = new OffscreenCanvas(img.width, img.height); const g = c.getContext('2d'); g.drawImage(img, 0, 0); return g.getImageData(0, 0, img.width, img.height); };
			const da = toile(ia), db = toile(ib);
			const diff = new OffscreenCanvas(ia.width, ia.height);
			const gd = diff.getContext('2d');
			const out = gd.createImageData(ia.width, ia.height);
			let n = 0;
			for (let i = 0; i < da.data.length; i += 4) {
				const ecart = Math.max(Math.abs(da.data[i] - db.data[i]), Math.abs(da.data[i + 1] - db.data[i + 1]), Math.abs(da.data[i + 2] - db.data[i + 2]));
				if (ecart > seuil) { n++; out.data[i] = 217; out.data[i + 1] = 45; out.data[i + 2] = 32; out.data[i + 3] = 255; }
				else { const gris = (da.data[i] + da.data[i + 1] + da.data[i + 2]) / 3; out.data[i] = out.data[i + 1] = out.data[i + 2] = 255 - (255 - gris) * 0.25; out.data[i + 3] = 255; }
			}
			gd.putImageData(out, 0, 0);
			const blob = await diff.convertToBlob({ type: 'image/png' });
			const octets = new Uint8Array(await blob.arrayBuffer());
			let bin = '';
			for (let i = 0; i < octets.length; i += 0x8000) { bin += String.fromCharCode.apply(null, octets.subarray(i, i + 0x8000)); }
			return { part: n / (da.data.length / 4), pixels: n, diff: btoa(bin) };
		}, { a: pngA.toString('base64'), b: pngB.toString('base64'), seuil });
		if (r.erreur) { throw new Error(r.erreur); }
		if (sortieDiff) { await writeFile(sortieDiff, Buffer.from(r.diff, 'base64')); }
		return { part: r.part, pixels: r.pixels };
	} finally {
		await contexte.close();
	}
}
