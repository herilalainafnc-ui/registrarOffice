/* =============================================================================
   Studio UAZ — les intitulés réécrits (« Lieu », « Heure », « Date »…)

   donnees._etiquettes : { texte d'origine : texte voulu } ; vide, l'intitulé
   disparaît de l'export (à l'écran, il reste en filigrane) ; l'identité de
   l'université ne se réécrit pas ; jamais de gabarit injecté.

   node tests/studio/test-etiquettes.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, preset, lireJson, verifier, bilan } from './outils.mjs';

const EVT = await preset('evt');
const JEU = (await lireJson(path.join(DONNEES, 'evt-trs.json'))).donnees;
const nav = await lancerNavigateur({ executable: CHROME });
const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1100, hauteur: 1400 });

async function rendre(etiquettes, apercu = false, format = '4:5') {
	return page.evaluate(async ({ preset, donnees, apercu, format }) => {
		const r = await StudioRendu.rendre(document.getElementById('visuel'), { preset, format, donnees, images: {}, base: '', apercu });
		const lire = (cle) => Array.from(document.querySelectorAll('[data-etiquette]')).filter((e) => e.dataset.etiquette === cle).map((e) => [e.textContent, e.classList.contains('uaz-a-remplir')]);
		return {
			lieu: lire('Lieu'), heure: lire('Heure'), invite: lire('Invité'), date: lire('Date'),
			universite: Array.from(document.querySelectorAll('.uaz-texte')).filter((e) => e.textContent.indexOf('Adventiste Zurcher') !== -1).map((e) => [e.textContent, e.dataset.etiquette || null]),
			etiquetables: Array.from(new Set(Array.from(document.querySelectorAll('[data-etiquette]')).map((e) => e.dataset.etiquette))).sort(),
			debordements: r.debordements.length, manquants: r.manquants.length,
		};
	}, { preset: EVT, donnees: Object.assign({}, JEU, { _etiquettes: etiquettes }), apercu, format });
}

try {
	const base = await rendre({});
	verifier('EVT : les intitulés fixes sont repérés (Date, Heure, Invité, Lieu, Modération)', JSON.stringify(base.etiquetables) === JSON.stringify(['Date', 'Heure', 'Invité', 'Lieu', 'Modération']), JSON.stringify(base.etiquetables));
	verifier('sans réécriture : « Lieu » tel quel', JSON.stringify(base.lieu) === JSON.stringify([['Lieu', false]]), JSON.stringify(base.lieu));
	verifier('l\'identité de l\'université n\'est pas modifiable', base.universite.length >= 1 && base.universite.every((x) => x[1] === null), JSON.stringify(base.universite));

	const r = await rendre({ Lieu: 'Adresse', 'Invité': 'Intervenant', 'Université Adventiste Zurcher': 'Autre école' });
	verifier('« Lieu » devient « Adresse » (la clé reste « Lieu »)', JSON.stringify(r.lieu) === JSON.stringify([['Adresse', false]]), JSON.stringify(r.lieu));
	verifier('un badge fixe aussi : « Invité » devient « Intervenant »', JSON.stringify(r.invite) === JSON.stringify([['Intervenant', false]]), JSON.stringify(r.invite));
	verifier('le nom de l\'université ne change pas', r.universite.every((x) => x[0].indexOf('Autre école') === -1), JSON.stringify(r.universite));
	verifier('… et l\'export reste possible', r.debordements === 0 && r.manquants === 0);

	const vide = await rendre({ Heure: '' });
	verifier('vide : « Heure » absent de l\'export', vide.heure.length === 0, JSON.stringify(vide.heure));
	const vap = await rendre({ Heure: '' }, true);
	verifier('vide, à l\'écran : en filigrane, cliquable', JSON.stringify(vap.heure) === JSON.stringify([['Heure', true]]), JSON.stringify(vap.heure));

	const gab = await rendre({ Date: 'Le {lieu} {annee}' });
	verifier('un gabarit glissé dans un intitulé reste du texte (accolades ôtées)', gab.date.length === 1 && gab.date[0][0] === 'Le lieu annee', JSON.stringify(gab.date));

	const story = await rendre({ Lieu: 'Adresse' }, false, '9:16');
	verifier('la réécriture vaut pour tous les formats', story.lieu.length >= 1 && story.lieu.every((x) => x[0] === 'Adresse'), JSON.stringify(story.lieu));
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
