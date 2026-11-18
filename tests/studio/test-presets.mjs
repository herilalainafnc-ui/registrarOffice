/* =============================================================================
   Studio UAZ — le comportement des presets de l'etape 2 (ANN, COM, FEL, INS, REC)

   Champs facultatifs (la cellule ou la rangee disparait, la voisine prend la
   place), grille des filieres (lignes equilibrees, colonnes egales), objet de
   la candidature repris du poste, minimums des listes, images obligatoires.

   node tests/studio/test-presets.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, preset, lireJson, verifier, bilan } from './outils.mjs';

const nav = await lancerNavigateur({ executable: CHROME });
const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1080, hauteur: 1350 });

/* Une image unie, faite dans la page, pour les emplacements. */
await page.evaluate(async () => {
	const c = new OffscreenCanvas(400, 500);
	const g = c.getContext('2d');
	g.fillStyle = '#3A4380';
	g.fillRect(0, 0, 400, 500);
	window.__image = URL.createObjectURL(await c.convertToBlob({ type: 'image/png' }));
});

async function rendre(id, donnees, emplacements = [], apercu = false) {
	return page.evaluate(async ({ p, donnees, emplacements, apercu }) => {
		const images = {};
		emplacements.forEach((e) => { images[e] = { src: window.__image, alt: 'test', fondre: false, ajustement: 'remplir' }; });
		const r = await StudioRendu.rendre(document.getElementById('visuel'), { preset: p, format: '4:5', donnees, images, base: '', apercu });
		const rangees = {};
		document.querySelectorAll('.uaz-visuel > [data-rangee]').forEach((e) => {
			rangees[e.dataset.rangee] = {
				h: e.offsetHeight,
				cellules: Array.from(e.children).filter((c) => c.classList.contains('uaz-cellule')).map((c) => c.offsetWidth),
				lignes: Array.from(e.children).filter((c) => !c.classList.contains('uaz-cellule')).map((l) => Array.from(l.children).map((c) => ({ w: c.offsetWidth, g: parseFloat(getComputedStyle(c).paddingLeft), d: parseFloat(getComputedStyle(c).paddingRight) }))),
			};
		});
		const textes = Array.from(document.querySelectorAll('.uaz-texte')).map((e) => e.textContent);
		const motCle = document.querySelector('.uaz-mot-cle');
		const marque = Array.from(document.querySelectorAll('.uaz-texte')).find((e) => e.textContent === 'zurcher.edu.mg');
		return { manquants: r.manquants, debordements: r.debordements, rangees, textes, motCle: motCle ? motCle.textContent : null,
			marqueDroite: marque ? Math.round(1080 - marque.getBoundingClientRect().right) : null };
	}, { p: await preset(id), donnees, emplacements, apercu });
}
const jeu = async (id) => (await lireJson(path.join(DONNEES, id + '-test.json'))).donnees;
const sans = (o, ...cles) => { const c = Object.assign({}, o); cles.forEach((k) => { c[k] = Array.isArray(c[k]) ? [] : ''; }); return c; };
const ok = (r) => !r.manquants.length && !r.debordements.length;

try {
	/* ANN */
	const ann = await jeu('ann');
	let r = await rendre('ann', ann);
	verifier('ANN : données de test, rien ne manque ni ne déborde', ok(r), JSON.stringify(r.manquants) + JSON.stringify(r.debordements));
	verifier('ANN sans photo : le titre prend toute la largeur', r.rangees.titre.cellules.length === 1 && r.rangees.titre.cellules[0] === 1080, JSON.stringify(r.rangees.titre));
	r = await rendre('ann', ann, ['photo']);
	verifier('ANN avec photo : titre et photo (380 px)', JSON.stringify(r.rangees.titre.cellules) === JSON.stringify([696, 380]), JSON.stringify(r.rangees.titre.cellules));
	r = await rendre('ann', sans(ann, 'retenir', 'mention'));
	verifier('ANN sans « À retenir » : la rangée disparaît, le titre grandit (790 + 130 + 4)', !r.rangees.retenir && r.rangees.titre.h === 924, JSON.stringify(r.rangees.titre));
	verifier('ANN sans mention : zurcher.edu.mg reste à droite (40 px du bord)', r.marqueDroite === 40, r.marqueDroite);

	/* COM */
	const com = await jeu('com');
	r = await rendre('com', com);
	verifier('COM : données de test, rien ne manque ni ne déborde', ok(r), JSON.stringify(r.manquants) + JSON.stringify(r.debordements));
	verifier('COM : référence et date « 02/11/2026 »', r.textes.indexOf('Réf. TEST-001 · 02/11/2026') !== -1 || r.textes.indexOf('Réf. TEST-001 · 02/11/2026') !== -1, JSON.stringify(r.textes.filter((t) => t.indexOf('Réf') === 0)));
	verifier('COM : la barre d\'or (90 px) et l\'objet', JSON.stringify(r.rangees.objet.cellules) === JSON.stringify([90, 986]), JSON.stringify(r.rangees.objet.cellules));
	r = await rendre('com', sans(com, 'renseignements', 'paragraphe2', 'paragraphe3'));
	verifier('COM sans renseignements : la rangée disparaît, le corps grandit (700 + 110 + 4)', !r.rangees.renseignements && r.rangees.corps.h === 814, JSON.stringify(r.rangees.corps));
	verifier('COM : paragraphes 2 et 3 facultatifs', ok(r), JSON.stringify(r.manquants));

	/* FEL */
	const fel = await jeu('fel');
	r = await rendre('fel', fel, ['portrait']);
	verifier('FEL : données de test, rien ne manque ni ne déborde', ok(r), JSON.stringify(r.manquants) + JSON.stringify(r.debordements));
	verifier('FEL : le NOM en or', r.motCle === 'TEST', r.motCle);
	r = await rendre('fel', fel);
	verifier('FEL sans portrait : il manque (obligatoire)', r.manquants.indexOf('portrait') !== -1, JSON.stringify(r.manquants));
	r = await rendre('fel', sans(fel, 'filiere'), ['portrait']);
	verifier('FEL sans filière : la date prend toute la largeur', JSON.stringify(r.rangees.infos.cellules) === JSON.stringify([1080]), JSON.stringify(r.rangees.infos.cellules));

	/* INS : la grille des filieres */
	const ins = await jeu('ins');
	for (const [n, attendu] of [[3, [3]], [4, [2, 2]], [5, [3, 2]], [6, [3, 3]]]) {
		r = await rendre('ins', Object.assign({}, ins, { filieres: ins.filieres.slice(0, n) }));
		const lignes = r.rangees.filieres.lignes;
		const egales = lignes.every((l) => Math.max(...l.map((c) => c.w)) - Math.min(...l.map((c) => c.w)) <= 1);
		const bords = lignes.every((l) => l[0].g === 40 && l[l.length - 1].d === 40);
		verifier('INS, ' + n + ' filières : lignes ' + JSON.stringify(lignes.map((l) => l.length)) + ', colonnes égales, 40 px au bord',
			JSON.stringify(lignes.map((l) => l.length)) === JSON.stringify(attendu) && egales && bords && ok(r), JSON.stringify(lignes));
	}
	r = await rendre('ins', Object.assign({}, ins, { filieres: ins.filieres.slice(0, 2) }));
	verifier('INS, 2 filières : il en faut 3', r.manquants.indexOf('filieres') !== -1, JSON.stringify(r.manquants));
	r = await rendre('ins', Object.assign({}, ins, { pieces: ['Une seule pièce'] }));
	verifier('INS, 1 pièce : il en faut 2', r.manquants.indexOf('pieces') !== -1, JSON.stringify(r.manquants));

	/* REC */
	const rec = await jeu('rec');
	r = await rendre('rec', rec, ['photo']);
	verifier('REC : données de test, rien ne manque ni ne déborde', ok(r), JSON.stringify(r.manquants) + JSON.stringify(r.debordements));
	verifier('REC : objet vide -> « Objet : Poste de test »', r.textes.indexOf('Objet : Poste de test') !== -1, JSON.stringify(r.textes.filter((t) => t.indexOf('Objet') === 0)));
	r = await rendre('rec', Object.assign({}, rec, { objet: 'Candidature de test' }), ['photo']);
	verifier('REC : objet écrit -> repris tel quel', r.textes.indexOf('Objet : Candidature de test') !== -1, JSON.stringify(r.textes.filter((t) => t.indexOf('Objet') === 0)));
	r = await rendre('rec', rec);
	verifier('REC sans photo du campus : elle manque (obligatoire, sinon le titre marine touche l\'en-tête)', r.manquants.indexOf('photo') !== -1, JSON.stringify(r.manquants));
	r = await rendre('rec', Object.assign({}, rec, { profil: ['Un seul critère'] }), ['photo']);
	verifier('REC, 1 critère de profil : il en faut 2', r.manquants.indexOf('profil') !== -1, JSON.stringify(r.manquants));

	/* Apercu d'un visuel vide : les textes a remplir (« [Date limite] ») ne sont pas « trop longs » */
	for (const id of ['ann', 'com', 'evt', 'fel', 'ins', 'rec']) {
		r = await rendre(id, {}, [], true);
		verifier(id.toUpperCase() + ' vide en aperçu : rien de trop long, seulement à remplir', !r.debordements.length && r.manquants.length > 0, JSON.stringify(r.debordements));
	}
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
