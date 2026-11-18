/* =============================================================================
   Studio UAZ — les presets face aux maquettes du kit (etapes 2 et 3)

   1. Le preset, rempli avec les textes entre crochets de sa maquette
   (tests/studio/donnees/maquettes.json) et une zone image unie couleur sable,
   est compare a la maquette (docs/studio-uaz/reference/presets/). Les ecarts
   voulus restent : 40 px au bord droit, dates et heure ecrites (pas « [JJ] »),
   espaces insecables, pictogramme de la zone image vide.
   2. EVT rempli comme la table ronde (tests/studio/donnees/evt-trs.json), dans
      les trois autres formats, face a la serie docs/studio-uaz/reference/
      table-ronde/ (Carre, Story, A4) : le modele d'adaptation des formats.
      Ecarts voulus, comme en 4:5 (decisions du 20/10/2026) : « LIEU » et
      « MODÉRATION », zurcher.edu.mg dans le pied, pastille sans halo, 40 px
      au bord du carre (36 dans la maquette) : toute la colonne de gauche y
      est decalee de 4 px, d'ou une tolerance de 7 % pour le carre seul.

   node tests/studio/test-maquettes.mjs     (images dans tests/studio/sorties/)
   ========================================================================== */
import { writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { RACINE, STUDIO, DONNEES, CHROME, lancerNavigateur, rendreVisuel, preset, lireJson, imagesDe, comparer, photographierMaquette, imageUnie, verifier, bilan } from './outils.mjs';

const TOLERANCE = 0.03;
const SORTIES = path.join(RACINE, 'tests/studio/sorties');
await mkdir(SORTIES, { recursive: true });
const jeux = await lireJson(path.join(DONNEES, 'maquettes.json'));

const nav = await lancerNavigateur({ executable: CHROME });
try {
	const sable = await imageUnie(nav, '#E4DDCB');
	for (const id of Object.keys(jeux).filter((k) => !k.startsWith('_'))) {
		const jeu = jeux[id];
		const maquette = await photographierMaquette(nav, 'presets/' + jeu.maquette);
		const images = jeu.image ? { [jeu.image]: { type: 'image/png', base64: sable, alt: 'Zone image', fondre: false, ajustement: 'remplir' } } : {};
		let r;
		try {
			r = await rendreVisuel(nav, { preset: await preset(id), format: '4:5', donnees: jeu.donnees, images, sortie: 'png', echelle: 1 }, { dossier: STUDIO });
		} catch (e) {
			verifier(id.toUpperCase() + ' : rendu', false, e.message + ' ' + JSON.stringify(e.rapport));
			continue;
		}
		await writeFile(path.join(SORTIES, 'maquette-' + id + '.png'), maquette);
		await writeFile(path.join(SORTIES, 'studio-' + id + '-maquette.png'), r.corps);
		const c = await comparer(nav, maquette, r.corps, { sortieDiff: path.join(SORTIES, 'differences-' + id + '-maquette.png') });
		verifier(id.toUpperCase() + ' 4:5 ≈ ' + jeu.maquette + ' (' + (c.part * 100).toFixed(2) + ' % des pixels)', c.part <= TOLERANCE, 'voir tests/studio/sorties/differences-' + id + '-maquette.png');
	}

	/* 2. La table ronde dans les autres formats */
	const trs = await lireJson(path.join(DONNEES, 'evt-trs.json'));
	const evt = await preset('evt');
	for (const [format, maquette, l, h, tolerance] of [['1:1', 'Carre.dc.html', 1080, 1080, 0.07], ['9:16', 'Story.dc.html', 1080, 1920, TOLERANCE], ['A4', 'A4.dc.html', 794, 1123, TOLERANCE]]) {
		const nom = 'evt-' + format.replace(':', 'x');
		const ref = await photographierMaquette(nav, 'table-ronde/' + maquette, l, h);
		let r;
		try {
			r = await rendreVisuel(nav, { preset: evt, format, donnees: trs.donnees, images: await imagesDe(trs, nav), sortie: 'png', echelle: 1 }, { dossier: STUDIO });
		} catch (e) {
			verifier('EVT ' + format + ' : rendu', false, e.message + ' ' + JSON.stringify(e.rapport));
			continue;
		}
		await writeFile(path.join(SORTIES, 'maquette-' + nom + '.png'), ref);
		await writeFile(path.join(SORTIES, 'studio-' + nom + '-maquette.png'), r.corps);
		const c = await comparer(nav, ref, r.corps, { sortieDiff: path.join(SORTIES, 'differences-' + nom + '-maquette.png') });
		verifier('EVT ' + format + ' (table ronde) ≈ table-ronde/' + maquette + ' (' + (c.part * 100).toFixed(2) + ' % des pixels, tolérance ' + tolerance * 100 + ' %)', c.part <= tolerance, 'voir tests/studio/sorties/differences-' + nom + '-maquette.png');
	}
} finally {
	await nav.close();
}
bilan();
