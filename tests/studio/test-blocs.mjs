/* =============================================================================
   Studio UAZ — les blocs ajoutés (jalon 4 de « modifier comme dans Canva »)

   Texte libre, image et forme, posés par-dessus le visuel : position exacte,
   ordre des calques, couleurs et graisses de la charte seulement, texte jamais
   interprété comme du HTML, position reprise à l'échelle dans un autre format,
   bloc vide absent de l'export, et PNG exporté par le service.

   node tests/studio/test-blocs.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, rendreVisuel, preset, lireJson, verifier, bilan, imageUnie } from './outils.mjs';

const EVT = await preset('evt');
const JEU = (await lireJson(path.join(DONNEES, 'evt-trs.json'))).donnees;
const nav = await lancerNavigateur({ executable: CHROME });
const BLEU = await imageUnie(nav, '#2040C0', 120, 80);
const enMemoire = { '/__images/libreb3': { type: 'image/png', corps: Buffer.from(BLEU, 'base64') } };
const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1100, hauteur: 1400, enMemoire });

const BLOCS = [
	{ id: 'b1', type: 'texte', texte: 'Bonjour <b>le monde</b>', taille: 64, graisse: 800, couleur: 'gold', align: 'center', maj: true, geo: { '4:5': { x: 100, y: 200, l: 600, h: 80 } } },
	{ id: 'b2', type: 'forme', forme: 'cercle', couleur: 'goldLight', opacite: 0.5, geo: { '4:5': { x: 300, y: 400, l: 150, h: 150 } } },
	{ id: 'b3', type: 'image', geo: { '4:5': { x: 500, y: 600, l: 240, h: 120 } } },
	{ id: 'b4', type: 'texte', texte: '', taille: 40, geo: { '4:5': { x: 50, y: 50, l: 300, h: 50 } } },
];
const IMAGES = { libreb3: { src: 'http://studio.local/__images/libreb3', alt: 'Un rectangle bleu', fondre: false, ajustement: 'remplir', cadrage: { x: 0.5, y: 0.5, zoom: 1 } } };

async function rendre(format, blocs, apercu = false) {
	return page.evaluate(async ({ preset, donnees, format, blocs, images, apercu }) => {
		const rapport = await StudioRendu.rendre(document.getElementById('visuel'), { preset, format, donnees, images, base: '', apercu, blocs });
		const racine = rapport.racine.getBoundingClientRect();
		const boite = (id) => { const e = document.querySelector('[data-libre="' + id + '"]'); if (!e) { return null; } const r = e.getBoundingClientRect(); const cs = getComputedStyle(e); return { x: r.left - racine.left, y: r.top - racine.top, l: r.width, h: r.height, couleur: cs.color, fond: cs.backgroundColor, graisse: cs.fontWeight, taille: parseFloat(cs.fontSize), z: cs.zIndex, texte: e.textContent, enfants: e.children.length, opacite: cs.opacity, rayon: cs.borderRadius, maj: cs.textTransform, align: cs.textAlign }; };
		return { b: Object.fromEntries(['b1', 'b2', 'b3', 'b4'].map((id) => [id, boite(id)])), ordre: Array.from(document.querySelectorAll('[data-libre]')).map((e) => e.dataset.libre), debordements: rapport.debordements.length, manquants: rapport.manquants.length, imgs: document.querySelectorAll('.uaz-libre--image img').length, balisesB: document.querySelectorAll('.uaz-libre--texte b').length };
	}, { preset: EVT, donnees: JEU, format, blocs, images: IMAGES, apercu });
}
const proche = (a, b, t = 0.6) => Math.abs(a - b) <= t;

try {
	const r = await rendre('4:5', BLOCS);
	verifier('le visuel reste exportable avec des blocs', r.debordements === 0 && r.manquants === 0, JSON.stringify(r));
	verifier('texte : position exacte (100 ; 200) et largeur 600', proche(r.b.b1.x, 100) && proche(r.b.b1.y, 200) && proche(r.b.b1.l, 600), JSON.stringify(r.b.b1));
	verifier('texte : taille, graisse, majuscules, centré', r.b.b1.taille === 64 && r.b.b1.graisse === '800' && r.b.b1.maj === 'uppercase' && r.b.b1.align === 'center', JSON.stringify(r.b.b1));
	verifier('texte : couleur de la charte (or #8B7448)', r.b.b1.couleur === 'rgb(139, 116, 72)', r.b.b1.couleur);
	verifier('texte : jamais du HTML (la balise reste du texte)', r.b.b1.texte.indexOf('<b>le monde</b>') !== -1 && r.balisesB === 0, r.b.b1.texte);
	verifier('forme cercle : 150 × 150, rond, couleur, opacité', proche(r.b.b2.l, 150) && proche(r.b.b2.h, 150) && r.b.b2.rayon.indexOf('50%') !== -1 && r.b.b2.fond === 'rgb(200, 174, 114)' && r.b.b2.opacite === '0.5', JSON.stringify(r.b.b2));
	verifier('image : cadre 240 × 120 et une image dedans', proche(r.b.b3.l, 240) && proche(r.b.b3.h, 120) && r.imgs === 1, JSON.stringify([r.b.b3, r.imgs]));
	verifier('ordre des calques : b1 sous b2 sous b3', r.ordre.slice(0, 3).join() === 'b1,b2,b3' && Number(r.b.b1.z) < Number(r.b.b2.z) && Number(r.b.b2.z) < Number(r.b.b3.z), JSON.stringify([r.ordre, r.b.b1.z, r.b.b2.z, r.b.b3.z]));
	verifier('export : un texte vide n\'est pas rendu', r.b.b4 === null, JSON.stringify(r.b.b4));
	const ap = await rendre('4:5', BLOCS, true);
	verifier('aperçu : un texte vide montre « Votre texte »', ap.b.b4 && ap.b.b4.texte === 'Votre texte', JSON.stringify(ap.b.b4));

	/* --- valeurs absurdes : bornées, couleur inconnue ramenée à la charte */
	const fou = await rendre('4:5', [{ id: 'b1', type: 'texte', texte: 'x', taille: 99999, graisse: 123, couleur: '#ff0000;background:red', align: 'justify', geo: { '4:5': { x: 1e9, y: 'abc', l: -4, h: 0 } } }, { id: 'zz', type: 'texte', texte: 'ignoré' }]);
	verifier('taille bornée à 600, graisse et couleur ramenées à la charte', fou.b.b1.taille === 600 && fou.b.b1.graisse === '600' && fou.b.b1.couleur === 'rgb(35, 42, 95)' && fou.b.b1.align === 'left', JSON.stringify(fou.b.b1));
	verifier('identifiant invalide ignoré', fou.ordre.join() === 'b1', JSON.stringify(fou.ordre));

	/* --- un autre format : la position est reprise à l'échelle de la largeur (1080 -> 1080 en 1:1, 1588 en A4) */
	const carre = await rendre('1:1', BLOCS);
	verifier('format 1:1 : même largeur, même position reprise', proche(carre.b.b1.x, 100) && proche(carre.b.b1.l, 600), JSON.stringify(carre.b.b1));
	const a4 = await rendre('A4', BLOCS);
	const k = EVT.formats.A4.largeur / EVT.formats['4:5'].largeur;
	verifier('format A4 : position mise à l\'échelle de la largeur (' + k.toFixed(3) + ')', proche(a4.b.b1.x, 100 * k, 1.5) && proche(a4.b.b1.l, 600 * k, 1.5), JSON.stringify([a4.b.b1, k]));
	const propre = await rendre('A4', [{ id: 'b1', type: 'texte', texte: 'propre', taille: 40, geo: { '4:5': { x: 100, y: 200, l: 600, h: 80 }, A4: { x: 20, y: 30, l: 300, h: 50 } } }]);
	verifier('format A4 : sa propre position l\'emporte', proche(propre.b.b1.x, 20) && proche(propre.b.b1.y, 30) && proche(propre.b.b1.l, 300), JSON.stringify(propre.b.b1));

	/* --- l'export par le service */
	const png = async (blocs) => (await rendreVisuel(nav, { preset: EVT, format: '4:5', donnees: JEU, images: { libreb3: { type: 'image/png', base64: BLEU, alt: 'Un rectangle bleu', ajustement: 'remplir' } }, sortie: 'png', blocs }, { dossier: STUDIO })).corps;
	const p0 = await png([]), p1 = await png(BLOCS);
	const ecart = await page.evaluate(async ([a, b]) => {
		const lire = async (x) => { const bmp = await createImageBitmap(new Blob([Uint8Array.from(atob(x), (c) => c.charCodeAt(0))], { type: 'image/png' })); const c = new OffscreenCanvas(bmp.width, bmp.height); const g = c.getContext('2d'); g.drawImage(bmp, 0, 0); return g.getImageData(0, 0, bmp.width, bmp.height).data; };
		const A = await lire(a), B = await lire(b); let n = 0;
		for (let i = 0; i < A.length; i += 4) { if (Math.abs(A[i] - B[i]) > 32 || Math.abs(A[i + 1] - B[i + 1]) > 32 || Math.abs(A[i + 2] - B[i + 2]) > 32) { n++; } }
		return n / (A.length / 4);
	}, [p0.toString('base64'), p1.toString('base64')]);
	verifier('export PNG : les blocs changent l\'image (' + (ecart * 100).toFixed(1) + ' % des pixels)', ecart > 0.01 && ecart < 0.5, String(ecart));
	const centreBleu = await page.evaluate(async (b64) => { const bmp = await createImageBitmap(new Blob([Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))], { type: 'image/png' })); const c = new OffscreenCanvas(bmp.width, bmp.height); const g = c.getContext('2d'); g.drawImage(bmp, 0, 0); return Array.from(g.getImageData(620, 660, 1, 1).data.slice(0, 3)); }, p1.toString('base64'));
	verifier('export PNG : le bloc image est bleu à sa place', Math.abs(centreBleu[0] - 0x20) < 16 && Math.abs(centreBleu[2] - 0xC0) < 16, JSON.stringify(centreBleu));
	const p2 = await png([]);
	verifier('export PNG sans bloc : identique à la référence', Buffer.compare(p0, p2) === 0);
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
