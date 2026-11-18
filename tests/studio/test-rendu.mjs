/* =============================================================================
   Studio UAZ — le moteur de rendu (src/studio/studio-rendu.js) dans Chromium

   Ajustement du texte, champs facultatifs, echappement, typographie
   francaise, dates et heures, sous-code, ressources exterieures bloquees,
   vitesse de l'apercu.

   node tests/studio/test-rendu.mjs
   ========================================================================== */
import path from 'node:path';
import { STUDIO, DONNEES, CHROME, lancerNavigateur, rendreVisuel, ouvrirPage, ErreurRendu, preset, lireJson, imagesDe, verifier, bilan } from './outils.mjs';

const EVT = await preset('evt');
const JEU = await lireJson(path.join(DONNEES, 'evt-trs.json'));
const IMAGES = await imagesDe(JEU);
const NBSP = ' ';

const nav = await lancerNavigateur({ executable: CHROME });
const { contexte, page } = await ouvrirPage(nav, {
	dossier: STUDIO, chemin: '/rendu.html', largeur: 1080, hauteur: 1350,
	enMemoire: { '/__images/image': { type: 'image/jpeg', corps: Buffer.from(IMAGES.image.base64, 'base64') } },
});

/* Rend dans la page et releve ce que les tests regardent. */
async function rendre(donnees, { images = true, apercu = false } = {}) {
	return page.evaluate(async ({ preset, donnees, images, apercu }) => {
		const t0 = performance.now();
		const r = await StudioRendu.rendre(document.getElementById('visuel'), { preset, format: '4:5', donnees, images, base: '', apercu });
		const duree = performance.now() - t0;
		const q = (s) => document.querySelector(s);
		const titre = q('.uaz-titre');
		const rangees = {};
		document.querySelectorAll('[data-rangee]').forEach((e) => { rangees[e.dataset.rangee] = { h: e.offsetHeight, cellules: Array.from(e.children).map((c) => c.offsetWidth) }; });
		const texteDe = (champ) => { const e = q('[data-champ="' + champ + '"]'); return e ? e.textContent : null; };
		return {
			manquants: r.manquants, debordements: r.debordements, avertissements: r.avertissements, duree,
			tailleTitre: titre ? +titre.dataset.t : null, titreTexte: titre ? titre.textContent : null,
			imagesDansTitre: titre ? titre.querySelectorAll('img').length : null,
			gras: Array.from(document.querySelectorAll('[data-champ="moderation"] b')).map((b) => b.textContent),
			code: document.querySelector('[data-rangee="entete"] .uaz-cellule:last-child .uaz-texte').textContent,
			annee: document.querySelector('[data-rangee="entete"] .uaz-cellule:last-child .uaz-texte:last-child').textContent,
			jour: texteDe('date'), heure: texteDe('heure'),
			unite: Array.from(document.querySelectorAll('[data-rangee="infos"] .uaz-cellule:nth-child(2) .uaz-texte')).map((e) => e.textContent),
			rangees,
		};
	}, { preset: EVT, donnees, images: images ? { image: { src: 'http://studio.local/__images/image', alt: 'x', fondre: true } } : {}, apercu });
}
const avec = (modifs) => Object.assign({}, JEU.donnees, modifs);

try {
	/* --- donnees de la table ronde : rien ne manque, rien ne deborde */
	let r = await rendre(JEU.donnees);
	verifier('table ronde : rien ne manque, rien ne déborde', !r.manquants.length && !r.debordements.length, JSON.stringify(r));
	verifier('titre à sa taille (58 px)', r.tailleTitre === 58, r.tailleTitre);
	verifier('sous-code TRS dans l\'en-tête, année de la date', r.code === 'TRS' && r.annee === '2026', r.code + ' ' + r.annee);
	verifier('date en toutes lettres', r.jour === 'Jeudi 22', r.jour);
	verifier('« 15 » + « heures »', r.heure === '15' && r.unite[2] === 'heures', JSON.stringify(r.unite));
	verifier('gras de la modération', JSON.stringify(r.gras) === JSON.stringify(['Ranala Marc Arthur', 'Irina Andriamandranto']), JSON.stringify(r.gras));
	verifier('pas de veuve : insécable avant « Salut »', r.titreTexte.endsWith('du' + NBSP + 'Salut'), JSON.stringify(r.titreTexte.slice(-12)));

	/* --- vitesse de l'apercu (polices deja chargees) */
	r = await rendre(JEU.donnees, { apercu: true });
	verifier('apercu rendu en moins de 150 ms (' + Math.round(r.duree) + ' ms)', r.duree < 150);

	/* --- ajustement : 2 px a la fois, jusqu'au minimum */
	/* 90 caracteres tiennent a 58 px : le moteur, lui, doit savoir reduire au-dela */
	r = await rendre(avec({ titre: 'Anthropologie biblique et responsabilité pastorale dans les communautés universitaires malgaches : héritages, ruptures et perspectives', motCleOr: 'responsabilité pastorale' }));
	verifier('titre long : réduit par pas de 2 px (' + r.tailleTitre + ' px), sans déborder', r.tailleTitre < 58 && r.tailleTitre >= 36 && (58 - r.tailleTitre) % 2 === 0 && !r.debordements.length, JSON.stringify(r.debordements));
	r = await rendre(avec({ titre: 'Un titre beaucoup trop long '.repeat(12), motCleOr: '' }));
	verifier('titre démesuré : au minimum, « Texte trop long »', r.tailleTitre === 36 && r.debordements.some((d) => d.champs.indexOf('titre') !== -1), JSON.stringify(r.debordements));
	let refus = null;
	try { await rendreVisuel(nav, { preset: EVT, format: '4:5', donnees: avec({ titre: 'Un titre beaucoup trop long '.repeat(12) }), images: IMAGES, sortie: 'png' }, { dossier: STUDIO }); } catch (e) { refus = e; }
	verifier('export refusé quand un texte déborde (422)', refus instanceof ErreurRendu && refus.code === 422 && refus.rapport.debordements.length > 0, refus && refus.message);

	/* --- champs facultatifs */
	r = await rendre(avec({ inviteNom: '', inviteFonction: '' }));
	verifier('sans invité : la liste prend toute la largeur', r.rangees.intervenants && r.rangees.intervenants.cellules.length === 1 && r.rangees.intervenants.cellules[0] === 1080, JSON.stringify(r.rangees.intervenants));
	r = await rendre(avec({ inviteNom: '', intervenants: [] }));
	verifier('sans intervenants ni invité : la rangée disparaît, le titre grandit (570 + 290 + 4)', !r.rangees.intervenants && r.rangees.titre.h === 864, JSON.stringify(r.rangees.titre));
	r = await rendre(avec({ moderation: '' }));
	verifier('sans modération : la rangée disparaît, le titre grandit (570 + 92 + 4)', !r.rangees.moderation && r.rangees.titre.h === 666, JSON.stringify(r.rangees.titre));
	r = await rendre(JEU.donnees, { images: false });
	verifier('sans image : le titre prend toute la largeur', r.rangees.titre.cellules.length === 1 && r.rangees.titre.cellules[0] === 1080, JSON.stringify(r.rangees.titre));

	/* --- obligatoires, echappement, typographie, heures, sous-codes */
	r = await rendre(avec({ titre: '', lieu: '  ' }));
	verifier('titre et lieu vides : manquants', r.manquants.indexOf('titre') !== -1 && r.manquants.indexOf('lieu') !== -1, JSON.stringify(r.manquants));
	r = await rendre(avec({ titre: '' }), { apercu: true });
	verifier('apercu : « [Titre] » à remplir', r.titreTexte === '[Titre]', r.titreTexte);
	r = await rendre(avec({ titre: '<img src=x onerror="window.pirate=1"> & <b>gras</b>', motCleOr: '' }));
	verifier('HTML saisi affiché comme du texte, jamais interprété', r.imagesDansTitre === 0 && r.titreTexte.indexOf('<img src=x') === 0 && !(await page.evaluate(() => window.pirate)), r.titreTexte);
	r = await rendre(avec({ titre: 'Pourquoi croire ? Une réponse : « la foi »', motCleOr: 'la foi' }));
	verifier('espaces insécables : « ? », « : », guillemets, dernier mot', r.titreTexte === 'Pourquoi croire' + NBSP + '? Une réponse' + NBSP + ': «' + NBSP + 'la' + NBSP + 'foi' + NBSP + '»', JSON.stringify(r.titreTexte));
	r = await rendre(avec({ heure: '15h30' }));
	verifier('« 15h30 » sans « heures »', r.heure === '15h30' && r.unite.length === 2, JSON.stringify(r.unite));
	r = await rendre(avec({ sousCode: 'XYZ', date: '2027-01-05' }));
	verifier('sous-code inconnu : EVT ; année 2027', r.code === 'EVT' && r.annee === '2027', r.code + ' ' + r.annee);
	r = await rendre(avec({ motCleOr: 'absent du titre' }));
	verifier('mot-clé absent du titre : avertissement', r.avertissements.some((a) => a.champ === 'motCleOr'), JSON.stringify(r.avertissements));

	/* --- rien ne sort vers l'exterieur */
	const sortie = await page.evaluate(async () => { try { await fetch('https://example.com/'); return 'joint'; } catch (e) { return 'bloqué'; } });
	verifier('requête vers l\'extérieur bloquée', sortie === 'bloqué', sortie);
} finally {
	await contexte.close();
	await nav.close();
}
bilan();
