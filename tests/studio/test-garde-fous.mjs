/* =============================================================================
   Studio UAZ — les garde-fous (jalon 5 de « modifier comme dans Canva »)

   Un élément déplacé ou un bloc ajouté qui dépasse la marge de 40 px, sort du
   visuel, tombe dans les zones d'Instagram (story) ou a un texte sous 20 px est
   signalé (avertissement, jamais un refus) ; un visuel sans rien de tel n'a aucun
   avertissement de plus ; le moteur marque l'élément (data-alerte).

   node tests/studio/test-garde-fous.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, ouvrirPage, preset, lireJson, verifier, bilan } from './outils.mjs';

const EVT = await preset('evt');
const JEU = (await lireJson(path.join(DONNEES, 'evt-trs.json'))).donnees;
const nav = await lancerNavigateur({ executable: CHROME });
const { contexte, page } = await ouvrirPage(nav, { dossier: STUDIO, chemin: '/rendu.html', largeur: 1100, hauteur: 2000 });

async function rendre(format, miseEnPage, blocs, fondPerdu = 0) {
	return page.evaluate(async ({ preset, donnees, format, miseEnPage, blocs, fondPerdu }) => {
		const r = await StudioRendu.rendre(document.getElementById('visuel'), { preset, format, donnees, images: {}, base: '', apercu: false, miseEnPage, blocs, fondPerdu });
		return { av: r.avertissements.map((a) => (a.champ || '') + ' : ' + a.message), debordements: r.debordements.length, manquants: r.manquants.length, marques: Array.from(document.querySelectorAll('[data-alerte]')).map((e) => (e.dataset.libre || e.dataset.champ || '') + '=' + e.dataset.alerte) };
	}, { preset: EVT, donnees: JEU, format, miseEnPage, blocs, fondPerdu });
}
const texte = (id, x, y, taille = 48, l = 400) => ({ id, type: 'texte', texte: 'Un texte de test', taille, graisse: 700, couleur: 'navy', geo: { '4:5': { x, y, l, h: 60 }, '9:16': { x, y, l, h: 60 } } });
const a = (r, mot) => r.av.filter((x) => x.indexOf(mot) !== -1);

try {
	const base = await rendre('4:5', {}, []);
	verifier('visuel de base : aucun avertissement de mise en page', base.marques.length === 0, JSON.stringify(base));
	const ref = base.av.length;

	/* --- marge */
	const marge = await rendre('4:5', {}, [texte('b1', 10, 300), texte('b2', 100, 300)]);
	verifier('texte à 10 px du bord : dépasse la marge de 40 px', a(marge, 'marge').length === 1 && a(marge, 'marge')[0].indexOf('b1') === 0, JSON.stringify(marge.av));
	verifier('… marqué data-alerte, l\'autre non', marge.marques.join() === 'b1=marge', JSON.stringify(marge.marques));
	verifier('… avertissement seulement : l\'export n\'est pas refusé', marge.debordements === 0 && marge.manquants === 0);
	const droite = await rendre('4:5', {}, [texte('b1', 1080 - 400 - 20, 300)]);
	verifier('bord droit : même règle', a(droite, 'marge').length === 1, JSON.stringify(droite.av));
	const juste = await rendre('4:5', {}, [texte('b1', 40, 40, 48, 1000)]);
	verifier('exactement sur la marge : rien à dire', a(juste, 'marge').length === 0, JSON.stringify(juste.av));

	/* --- un aplat peut aller au bord */
	const aplat = await rendre('4:5', {}, [{ id: 'b1', type: 'forme', forme: 'rect', couleur: 'gold', geo: { '4:5': { x: 0, y: 0, l: 1080, h: 30 } } }]);
	verifier('un aplat au bord du visuel : permis', aplat.av.length === ref && aplat.marques.length === 0, JSON.stringify(aplat));

	/* --- hors du visuel */
	const hors = await rendre('4:5', {}, [texte('b1', 2000, 300)]);
	verifier('hors du visuel : signalé', a(hors, 'hors du visuel').length === 1, JSON.stringify(hors.av));

	/* --- texte trop petit */
	const petit = await rendre('4:5', {}, [texte('b1', 100, 300, 12)]);
	verifier('texte de 12 px : trop petit', a(petit, 'trop petit').length === 1, JSON.stringify(petit.av));
	const ok20 = await rendre('4:5', {}, [texte('b1', 100, 300, 20)]);
	verifier('texte de 20 px : pas signalé', a(ok20, 'trop petit').length === 0, JSON.stringify(ok20.av));
	const reduit = await rendre('4:5', { lieu: { k: 0.3 } }, []);
	verifier('un champ de la charte réduit à 30 % : trop petit aussi', a(reduit, 'lieu').some((x) => x.indexOf('trop petit') !== -1), JSON.stringify(reduit.av));

	/* --- un champ déplacé hors des marges */
	const deplace = await rendre('4:5', { titre: { dx: -200 } }, []);
	verifier('un champ de la charte poussé à gauche : marge', a(deplace, 'titre').some((x) => x.indexOf('marge') !== -1) && deplace.marques.some((x) => x.indexOf('titre=') === 0), JSON.stringify(deplace));

	/* --- story : zones d'Instagram */
	const story = await rendre('9:16', {}, [texte('b1', 100, 100), texte('b2', 100, 900), texte('b3', 100, 1750)]);
	verifier('story : haut (y = 100) et bas (y = 1750) signalés, le milieu non', a(story, 'Instagram').map((x) => x.split(' ')[0]).join() === 'b1,b3', JSON.stringify(story.av));
	const carre = await rendre('4:5', {}, [texte('b1', 100, 100)]);
	verifier('hors story : pas de zone d\'Instagram', a(carre, 'Instagram').length === 0, JSON.stringify(carre.av));

	/* --- fond perdu (PDF imprimeur) : les mêmes constats, à la même place */
	const fp = await rendre('4:5', {}, [texte('b1', 10, 300), texte('b2', 100, 300)], 11.34);
	verifier('avec fond perdu : b1 toujours signalé, b2 non', a(fp, 'marge').length === 1 && a(fp, 'marge')[0].indexOf('b1') === 0, JSON.stringify(fp.av));
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
