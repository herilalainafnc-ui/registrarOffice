/* =============================================================================
   Studio UAZ — les emplacements d'image (etape 4)

   - Recadrage d'un cadre plein : le point focal (x, y) au centre du cadre, le
     zoom au-dessus du plein cadre, les bords tenus (jamais de vide), et le meme
     point garde d'un format a l'autre.
   - Reglages rapides : lumiere, noir et blanc, duotone marine/creme, verifies
     sur les pixels rendus.
   - Resolution : une image agrandie au-dela de 100 % (ecran) ou sous 150 points
     par pouce (A4) est signalee.
   - Export par le moteur du service : recadrage et reglages appliques.

   node tests/studio/test-images.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, rendreVisuel, preset, lireJson, verifier, bilan } from './outils.mjs';

const FEL = await preset('fel');
const JEU = (await lireJson(path.join(DONNEES, 'fel-test.json'))).donnees;
const nav = await lancerNavigateur({ executable: CHROME });
/* Des images de test faites dans une premiere page (quatre quarts de couleur, un gris, un rouge, noir et
   blanc...), puis servies sous /__images/<nom> comme par le service : la page de rendu n'accepte que ses
   propres images (CSP), pas les blob: */
const atelier = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 200, hauteur: 200 });
const IMAGES = await atelier.page.evaluate(async () => {
	const faire = async (l, h, dessin) => {
		const c = new OffscreenCanvas(l, h);
		dessin(c.getContext('2d'), l, h);
		const blob = await c.convertToBlob({ type: 'image/png' });
		const b64 = btoa(Array.from(new Uint8Array(await blob.arrayBuffer()), (o) => String.fromCharCode(o)).join(''));
		return b64;
	};
	return {
		quarts: await faire(1600, 1200, (g, l, h) => {
			g.fillStyle = '#E00000'; g.fillRect(0, 0, l / 2, h / 2);
			g.fillStyle = '#00C000'; g.fillRect(l / 2, 0, l / 2, h / 2);
			g.fillStyle = '#0000E0'; g.fillRect(0, h / 2, l / 2, h / 2);
			g.fillStyle = '#E0E000'; g.fillRect(l / 2, h / 2, l / 2, h / 2);
		}),
		gris: await faire(1600, 1600, (g, l, h) => { g.fillStyle = '#808080'; g.fillRect(0, 0, l, h); }),
		rouge: await faire(1600, 1600, (g, l, h) => { g.fillStyle = '#D02020'; g.fillRect(0, 0, l, h); }),
		noirBlanc: await faire(1600, 1600, (g, l, h) => { g.fillStyle = '#000000'; g.fillRect(0, 0, l / 2, h); g.fillStyle = '#FFFFFF'; g.fillRect(l / 2, 0, l / 2, h); }),
		petite: await faire(300, 300, (g, l, h) => { g.fillStyle = '#808080'; g.fillRect(0, 0, l, h); }),
		a4moyenne: await faire(600, 750, (g, l, h) => { g.fillStyle = '#808080'; g.fillRect(0, 0, l, h); }),
		a4grande: await faire(1300, 1625, (g, l, h) => { g.fillStyle = '#808080'; g.fillRect(0, 0, l, h); }),
	};
});
await atelier.contexte.close();
const enMemoire = {};
Object.entries(IMAGES).forEach(([nom, b64]) => { enMemoire['/__images/' + nom] = { type: 'image/png', corps: Buffer.from(b64, 'base64') }; });
const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1100, hauteur: 2000, enMemoire });

/* Rend FEL avec un portrait ; renvoie le rapport de l'image et la couleur au centre (et a gauche/droite) du cadre */
async function rendre(format, image, cadrage, reglages) {
	const r = await page.evaluate(async ({ preset, format, donnees, image, cadrage, reglages }) => {
		const x = await StudioRendu.rendre(document.getElementById('visuel'), {
			preset, format, donnees, base: '', apercu: false,
			images: { portrait: { src: 'http://studio.local/__images/' + image, alt: 'test', fondre: false, ajustement: 'remplir', cadrage, reglages } },
		});
		const cadre = document.querySelector('.uaz-image[data-image="portrait"]').getBoundingClientRect();
		return { info: x.images.portrait, avertissements: x.avertissements, cadre: [cadre.left, cadre.top, cadre.width, cadre.height] };
	}, { preset: FEL, format, donnees: JEU, image, cadrage, reglages });
	const [cx, cy, cw, ch] = r.cadre;
	const png = await page.screenshot({ clip: { x: Math.floor(cx), y: Math.floor(cy), width: Math.floor(cw), height: Math.floor(ch) } });
	r.pixels = await page.evaluate(async (b64) => {
		const bmp = await createImageBitmap(new Blob([Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))], { type: 'image/png' }));
		const c = new OffscreenCanvas(bmp.width, bmp.height);
		const g = c.getContext('2d');
		g.drawImage(bmp, 0, 0);
		const lire = (fx, fy) => Array.from(g.getImageData(Math.floor(bmp.width * fx), Math.floor(bmp.height * fy), 1, 1).data.slice(0, 3));
		return { centre: lire(0.5, 0.5), gauche: lire(0.1, 0.5), droite: lire(0.9, 0.5) };
	}, png.toString('base64'));
	return r;
}
const proche = (a, b, t = 0.002) => Math.abs(a - b) <= t;
const couleur = (p, c, t = 24) => p.every((v, i) => Math.abs(v - c[i]) <= t);

try {
	/* (la page de rendu interdit fetch hors du site : les captures se decodent depuis leurs octets) */
	/* --- 1. Recadrage */
	let r = await rendre('4:5', 'quarts', { x: 0.5, y: 0.5, zoom: 1 });
	const [v0, v1, v2, v3] = r.info.visible;
	verifier('cadre plein, centré : la partie visible est centrée (' + r.info.visible.map((v) => v.toFixed(3)).join(', ') + ')', proche(v0 + v2 / 2, 0.5) && proche(v1 + v3 / 2, 0.5) && (proche(v2, 1) || proche(v3, 1)), JSON.stringify(r.info));
	r = await rendre('4:5', 'quarts', { x: 0.25, y: 0.25, zoom: 3 });
	verifier('point focal (0,25 ; 0,25), zoom 300 % : ce point au centre du cadre', proche(r.info.visible[0] + r.info.visible[2] / 2, 0.25) && proche(r.info.visible[1] + r.info.visible[3] / 2, 0.25), JSON.stringify(r.info.visible));
	verifier('… et le cadre ne montre que le quart rouge (pixels)', couleur(r.pixels.centre, [224, 0, 0]) && couleur(r.pixels.gauche, [224, 0, 0]) && couleur(r.pixels.droite, [224, 0, 0]), JSON.stringify(r.pixels));
	verifier('zoom 300 % : la partie visible est trois fois plus petite', proche(r.info.visible[2] * 3, v2, 0.01) && proche(r.info.visible[3] * 3, v3, 0.01), JSON.stringify([r.info.visible, [v2, v3]]));
	r = await rendre('4:5', 'quarts', { x: 0, y: 1, zoom: 2 });
	verifier('point focal au coin : l\'image reste calée au bord (jamais de vide)', proche(r.info.visible[0], 0) && proche(r.info.visible[1] + r.info.visible[3], 1), JSON.stringify(r.info.visible));
	for (const format of ['1:1', '9:16', 'A4']) {
		r = await rendre(format, 'quarts', { x: 0.3, y: 0.35, zoom: 2.5 });
		verifier('format ' + format + ' : le même point focal reste au centre du cadre', proche(r.info.visible[0] + r.info.visible[2] / 2, 0.3) && proche(r.info.visible[1] + r.info.visible[3] / 2, 0.35), JSON.stringify(r.info.visible));
	}

	/* --- 2. Reglages rapides */
	const base = (await rendre('4:5', 'gris', null, null)).pixels.centre;
	r = await rendre('4:5', 'gris', null, { lumiere: 40, contraste: 0, saturation: 0, mode: 'couleur' });
	verifier('lumière +40 : plus clair (' + base[0] + ' -> ' + r.pixels.centre[0] + ')', r.pixels.centre[0] > base[0] + 30, JSON.stringify([base, r.pixels.centre]));
	r = await rendre('4:5', 'gris', null, { lumiere: 999, contraste: 0, saturation: 0, mode: 'couleur' });
	verifier('lumière bornée à +40 (une valeur de 999 ne brûle pas l\'image)', r.pixels.centre[0] < 200, JSON.stringify(r.pixels.centre));
	r = await rendre('4:5', 'rouge', null, { lumiere: 0, contraste: 0, saturation: 0, mode: 'nb' });
	const [rr, gg, bb] = r.pixels.centre;
	verifier('noir et blanc : le rouge devient un gris', Math.abs(rr - gg) < 6 && Math.abs(gg - bb) < 6, JSON.stringify(r.pixels.centre));
	r = await rendre('4:5', 'noirBlanc', { x: 0.5, y: 0.5, zoom: 1 }, { lumiere: 0, contraste: 0, saturation: 0, mode: 'duotone' });
	verifier('duotone : le noir devient marine (#232A5F), le blanc crème (#F4F2ED)', couleur(r.pixels.gauche, [0x23, 0x2A, 0x5F], 8) && couleur(r.pixels.droite, [0xF4, 0xF2, 0xED], 8), JSON.stringify(r.pixels));

	/* --- 3. Resolution */
	r = await rendre('4:5', 'petite', null, null);
	verifier('image de 300 px dans un grand cadre : « agrandie à … % : elle sera floue »', r.avertissements.some((a) => a.resolution && a.champ === 'portrait' && a.message.indexOf('floue') !== -1), JSON.stringify(r.avertissements));
	r = await rendre('4:5', 'gris', { x: 0.5, y: 0.5, zoom: 1 }, null);
	verifier('image de 1 600 px au plein cadre : pas d\'alerte', !r.avertissements.some((a) => a.resolution), JSON.stringify(r.avertissements));
	r = await rendre('4:5', 'gris', { x: 0.5, y: 0.5, zoom: 4 }, null);
	verifier('la même, zoomée à 400 % : agrandie, signalée', r.avertissements.some((a) => a.resolution), JSON.stringify(r.avertissements));
	r = await rendre('A4', 'a4moyenne', null, null);
	verifier('A4, image de 600 px : sous 150 points par pouce, signalée', r.avertissements.some((a) => a.resolution && a.message.indexOf('points par pouce') !== -1), JSON.stringify(r.avertissements));
	r = await rendre('A4', 'a4grande', null, null);
	verifier('A4, image de 1 300 px : 150 points par pouce au moins, pas d\'alerte', !r.avertissements.some((a) => a.resolution), JSON.stringify(r.avertissements));

	/* --- 4. Export par le moteur du service : recadrage et reglages transmis */
	const b64 = IMAGES.quarts;
	const x = await rendreVisuel(nav, {
		preset: FEL, format: '4:5', donnees: JEU, sortie: 'png',
		images: { portrait: { type: 'image/png', base64: b64, alt: 'test', fondre: false, ajustement: 'remplir', cadrage: { x: 0.75, y: 0.75, zoom: 3 }, reglages: { mode: 'nb' } } },
	}, { dossier: STUDIO });
	const p = await page.evaluate(async ({ b64, x, y }) => {
		const bmp = await createImageBitmap(new Blob([Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))], { type: 'image/png' }));
		const c = new OffscreenCanvas(bmp.width, bmp.height);
		const g = c.getContext('2d');
		g.drawImage(bmp, 0, 0);
		return Array.from(g.getImageData(x, y, 1, 1).data.slice(0, 3));
	}, { b64: x.corps.toString('base64'), x: 12 + 260, y: 114 + 380 });
	verifier('export (service) : quart jaune recadré, en noir et blanc (gris clair)', Math.abs(p[0] - p[1]) < 6 && Math.abs(p[1] - p[2]) < 6 && p[0] > 150, JSON.stringify(p));
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
