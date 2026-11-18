/* =============================================================================
   Studio UAZ — regression visuelle

   1. Le critere de l'etape 1 : la reference est l'affiche d'origine,
      docs/studio-uaz/reference/table-ronde/Main.dc.html, nettoyee a la volee
      (sans l'outil de maquette, police et images locales). Le preset EVT,
      rempli avec les memes donnees (tests/studio/donnees/evt-trs.json), doit
      lui ressembler a 1 % pres des pixels. Ecarts voulus (decisions du
      20/10/2026 : le preset fait foi) : code TRS en sous-code, « LIEU » et
      « MODÉRATION », zurcher.edu.mg dans le pied, pastille sans halo, 40 px de
      marge au bord droit.
   2. Chaque preset, rempli avec ses donnees de test (tests/studio/donnees/
      <id>-test.json, fictives), dans chacun de ses formats (4:5, 1:1, 9:16, A4),
      face a son image de reference enregistree (tests/studio/references/
      <id>-<format>.png) : a 1 % pres. Une reference absente est creee ; --maj
      les recree toutes (apres un changement voulu).

   node tests/studio/test-regression.mjs [--maj]     (images dans tests/studio/sorties/)
   ========================================================================== */
import { readFile, writeFile, mkdir, access } from 'node:fs/promises';
import path from 'node:path';
import { RACINE, STUDIO, DONNEES, CHROME, lancerNavigateur, rendreVisuel, preset, lireJson, imagesDe, comparer, photographierMaquette, verifier, bilan } from './outils.mjs';

const TOLERANCE = 0.01;
const SORTIES = path.join(RACINE, 'tests/studio/sorties');
const REFERENCES = path.join(RACINE, 'tests/studio/references');
const MAJ = process.argv.includes('--maj');
await mkdir(SORTIES, { recursive: true });
await mkdir(REFERENCES, { recursive: true });
const existe = (f) => access(f).then(() => true, () => false);

const nav = await lancerNavigateur({ executable: CHROME });
try {
	/* 1. EVT face a l'affiche de la table ronde */
	const pngReference = await photographierMaquette(nav, 'table-ronde/Main.dc.html');
	const jeu = await lireJson(path.join(DONNEES, 'evt-trs.json'));
	const r = await rendreVisuel(nav, { preset: await preset('evt'), format: '4:5', donnees: jeu.donnees, images: await imagesDe(jeu, nav), sortie: 'png', echelle: 1 }, { dossier: STUDIO });
	await writeFile(path.join(SORTIES, 'reference-evt-4x5.png'), pngReference);
	await writeFile(path.join(SORTIES, 'studio-evt-4x5.png'), r.corps);
	const c = await comparer(nav, pngReference, r.corps, { sortieDiff: path.join(SORTIES, 'differences-evt-4x5.png') });
	verifier('EVT 4:5 rempli comme la table ronde ≈ Main.dc.html (' + (c.part * 100).toFixed(2) + ' % des pixels, tolérance ' + TOLERANCE * 100 + ' %)', c.part <= TOLERANCE,
		c.pixels + ' pixels différents ; voir tests/studio/sorties/differences-evt-4x5.png');
	verifier('aucun avertissement du moteur', r.rapport.avertissements.length === 0, JSON.stringify(r.rapport.avertissements));

	/* 2. Chaque preset face a sa reference enregistree */
	const jeux = [['evt', 'evt-trs.json'], ['ann', 'ann-test.json'], ['com', 'com-test.json'], ['fel', 'fel-test.json'], ['ins', 'ins-test.json'], ['rec', 'rec-test.json']];
	for (const [id, fichier] of jeux) for (const format of Object.keys((await preset(id)).formats)) {
		const j = await lireJson(path.join(DONNEES, fichier));
		const nom = id + '-' + format.replace(':', 'x');
		let rendu;
		try {
			rendu = await rendreVisuel(nav, { preset: await preset(id), format, donnees: j.donnees, images: await imagesDe(j, nav), sortie: 'png', echelle: 1 }, { dossier: STUDIO });
		} catch (e) {
			verifier(nom + ' : rendu', false, e.message + ' ' + JSON.stringify(e.rapport));
			continue;
		}
		await writeFile(path.join(SORTIES, 'studio-' + nom + '.png'), rendu.corps);
		const ref = path.join(REFERENCES, nom + '.png');
		if (MAJ || !(await existe(ref))) {
			await writeFile(ref, rendu.corps);
			console.log('ref    ' + nom + ' : référence ' + (MAJ ? 'recréée' : 'créée') + ' (tests/studio/references/' + nom + '.png)');
			continue;
		}
		const d = await comparer(nav, await readFile(ref), rendu.corps, { sortieDiff: path.join(SORTIES, 'differences-' + nom + '.png') });
		verifier(nom + ' ≈ sa référence (' + (d.part * 100).toFixed(2) + ' %)', d.part <= TOLERANCE, 'voir tests/studio/sorties/differences-' + nom + '.png');
	}
} finally {
	await nav.close();
}
bilan();
