/* =============================================================================
   Studio UAZ — la mise en page libre (jalon 3 de « modifier comme dans Canva »)

   Un element se deplace et se redimensionne par-dessus la grille (miseEnPage :
   { id: { dx, dy, l, h, k } }) : son decalage est exact, sa cellule ne le rogne
   plus, le reste du visuel ne bouge pas, les valeurs sont bornees, et l'export
   par le service (PNG) montre l'element la ou il a ete mis.

   node tests/studio/test-miseenpage.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, rendreVisuel, preset, lireJson, verifier, bilan } from './outils.mjs';

const EVT = await preset('evt');
const JEU = (await lireJson(path.join(DONNEES, 'evt-trs.json'))).donnees;
const nav = await lancerNavigateur({ executable: CHROME });
const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1100, hauteur: 1400 });

/* Rend EVT 4:5 avec une mise en page ; renvoie les boites des elements demandes */
async function rendre(miseEnPage, ids) {
	return page.evaluate(async ({ preset, donnees, miseEnPage, ids }) => {
		const rapport = await StudioRendu.rendre(document.getElementById('visuel'), { preset, format: '4:5', donnees, images: {}, base: '', apercu: false, miseEnPage });
		const racine = rapport.racine.getBoundingClientRect();
		const boite = (id) => {
			const e = document.querySelector('[data-champ="' + id + '"]');
			if (!e) { return null; }
			const r = e.getBoundingClientRect();
			return { x: r.left - racine.left, y: r.top - racine.top, l: r.width, h: r.height, taille: parseFloat(getComputedStyle(e).fontSize), z: getComputedStyle(e).zIndex };
		};
		return { boites: Object.fromEntries(ids.map((id) => [id, boite(id)])), debordements: rapport.debordements.length, manquants: rapport.manquants.length };
	}, { preset: EVT, donnees: JEU, miseEnPage, ids });
}
const proche = (a, b, t = 0.6) => Math.abs(a - b) <= t;

try {
	const ids = ['titre', 'lieu', 'sousTitre'];
	const base = await rendre({}, ids);
	verifier('le visuel de base est exportable', base.debordements === 0 && base.manquants === 0, JSON.stringify([base.debordements, base.manquants]));

	/* --- deplacer : decalage exact, le reste ne bouge pas */
	const d = await rendre({ titre: { dx: 40, dy: 120 } }, ids);
	verifier('titre : décalé de (40 ; 120) exactement', proche(d.boites.titre.x - base.boites.titre.x, 40) && proche(d.boites.titre.y - base.boites.titre.y, 120), JSON.stringify([base.boites.titre, d.boites.titre]));
	verifier('titre : même taille', proche(d.boites.titre.l, base.boites.titre.l) && proche(d.boites.titre.h, base.boites.titre.h), JSON.stringify([base.boites.titre, d.boites.titre]));
	verifier('le lieu et le sous-titre ne bougent pas', proche(d.boites.lieu.x, base.boites.lieu.x) && proche(d.boites.lieu.y, base.boites.lieu.y) && proche(d.boites.sousTitre.y, base.boites.sousTitre.y), JSON.stringify([base.boites.lieu, d.boites.lieu]));
	verifier('l\'élément déplacé passe au-dessus (z-index)', d.boites.titre.z === '6', d.boites.titre.z);

	/* --- sortir de sa cellule : plus rogné (le lieu est dans une cellule du pied) */
	const loin = await rendre({ lieu: { dx: 0, dy: -500 } }, ids);
	const visible = await page.evaluate(() => { const e = document.querySelector('[data-champ="lieu"]'); const r = e.getBoundingClientRect(); const p = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return !!p && (p === e || e.contains(p)); });
	verifier('le lieu remonté de 500 px sort de sa cellule et reste visible', loin.boites.lieu.y < base.boites.lieu.y - 400 && visible, JSON.stringify([base.boites.lieu, loin.boites.lieu, visible]));

	/* --- redimensionner : largeur imposée, échelle du texte */
	const l = await rendre({ titre: { l: 300 } }, ids);
	verifier('largeur imposée : 300 px, le titre se replie (plus haut)', proche(l.boites.titre.l, 300) && l.boites.titre.h > base.boites.titre.h, JSON.stringify([base.boites.titre, l.boites.titre]));
	const k = await rendre({ titre: { k: 0.5 } }, ids);
	verifier('échelle 0,5 : la police est divisée par deux', proche(k.boites.titre.taille, base.boites.titre.taille / 2, 0.3), JSON.stringify([base.boites.titre.taille, k.boites.titre.taille]));
	const k2 = await rendre({ titre: { k: 1.5 } }, ids);
	verifier('échelle 1,5 : la police grandit de moitié', proche(k2.boites.titre.taille, base.boites.titre.taille * 1.5, 0.3), JSON.stringify([base.boites.titre.taille, k2.boites.titre.taille]));

	/* --- valeurs bornées ou ignorées */
	const fou = await rendre({ titre: { dx: 1e9, k: 99, l: -5 }, inconnu: { dx: 10 }, lieu: { dx: 'abc', dy: null } }, ids);
	verifier('valeurs absurdes bornées (dx 3000 au plus), inconnu ignoré, texte non numérique ignoré',
		fou.boites.titre.x - base.boites.titre.x <= 3000.6 && fou.boites.titre.taille <= base.boites.titre.taille * 4.01 && proche(fou.boites.lieu.x, base.boites.lieu.x) && fou.boites.lieu.y === base.boites.lieu.y, JSON.stringify(fou.boites.titre));

	/* --- rien de demandé : strictement le même visuel */
	const vide = await rendre({}, ids);
	verifier('sans mise en page : identique', JSON.stringify(vide.boites) === JSON.stringify(base.boites));

	/* --- l'export par le service montre le titre là où il a été mis */
	const png = async (miseEnPage) => (await rendreVisuel(nav, { preset: EVT, format: '4:5', donnees: JEU, images: {}, sortie: 'png', miseEnPage: { '4:5': miseEnPage } }, { dossier: STUDIO })).corps;
	const p0 = await png({}), p1 = await png({ titre: { dx: 0, dy: 200 } });
	const ecart = await page.evaluate(async ([a, b]) => {
		const lire = async (x) => { const bmp = await createImageBitmap(new Blob([Uint8Array.from(atob(x), (c) => c.charCodeAt(0))], { type: 'image/png' })); const c = new OffscreenCanvas(bmp.width, bmp.height); const g = c.getContext('2d'); g.drawImage(bmp, 0, 0); return g.getImageData(0, 0, bmp.width, bmp.height).data; };
		const A = await lire(a), B = await lire(b);
		let n = 0;
		for (let i = 0; i < A.length; i += 4) { if (Math.abs(A[i] - B[i]) > 32 || Math.abs(A[i + 1] - B[i + 1]) > 32 || Math.abs(A[i + 2] - B[i + 2]) > 32) { n++; } }
		return n / (A.length / 4);
	}, [p0.toString('base64'), p1.toString('base64')]);
	verifier('export PNG : le titre déplacé change l\'image (' + (ecart * 100).toFixed(1) + ' % des pixels)', ecart > 0.01 && ecart < 0.5, String(ecart));
	const p2 = await png({});
	verifier('export PNG sans mise en page : identique à la référence', Buffer.compare(p0, p2) === 0);
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
