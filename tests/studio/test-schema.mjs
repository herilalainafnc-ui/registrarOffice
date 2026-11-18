/* =============================================================================
   Studio UAZ — validation des presets (src/studio/studio-schema.js)

   Chaque preset de src/studio/presets/ est valide ; un preset casse est
   refuse avec un message qui dit ou et pourquoi.

   node tests/studio/test-schema.mjs
   ========================================================================== */
import { readdir } from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { STUDIO, lireJson, schema, verifier, bilan } from './outils.mjs';

const Schema = createRequire(import.meta.url)(path.join(STUDIO, 'studio-schema.js'));
const SCHEMA = await schema();

for (const f of (await readdir(path.join(STUDIO, 'presets'))).filter((x) => x.endsWith('.json') && x !== 'preset.schema.json')) {
	const p = await lireJson(path.join(STUDIO, 'presets', f));
	const r = Schema.valider(p, SCHEMA);
	verifier('preset ' + f + ' valide', r.ok, r.erreurs.join('\n        '));
	verifier('preset ' + f + ' : id = nom du fichier', p.id + '.json' === f);
}

/* Un preset casse a la fois : la copie de EVT, une seule chose changee. */
const evt = await lireJson(path.join(STUDIO, 'presets/evt.json'));
const copie = () => JSON.parse(JSON.stringify(evt));
const cellule = (p, ir, ic) => p.formats['4:5'].rangees[ir].cellules[ic];
function refuse(nom, modifier, attendu) {
	const p = copie();
	modifier(p);
	const r = Schema.valider(p, SCHEMA);
	const trouve = r.erreurs.some((e) => e.indexOf(attendu) !== -1);
	verifier('refusé : ' + nom, !r.ok && trouve, 'erreurs : ' + JSON.stringify(r.erreurs));
}

refuse('couleur hors charte', (p) => { cellule(p, 1, 0).fond = 'violet'; }, 'non permis');
refuse('deux cellules pleines dans une rangée', (p) => { cellule(p, 2, 0).fond = 'goldDeep'; }, 'une seule cellule pleine');
refuse('marge de 20 px au bord gauche', (p) => { cellule(p, 2, 0).marges = [30, 40, 30, 20]; }, 'marge gauche de 40 px');
refuse('texte de 10 px', (p) => { cellule(p, 2, 0).blocs[0].taille = 10; }, 'jamais sous 12 px');
refuse('mot-clé en or qui descend à 20 px', (p) => { cellule(p, 1, 0).blocs[1].min = 20; }, "l'or sur crème");
refuse('blanc sur crème', (p) => { cellule(p, 2, 0).blocs[2].couleur = 'white'; }, "n'est pas une paire de la charte");
refuse('champ inconnu dans un texte', (p) => { cellule(p, 2, 2).blocs[2].valeur = '{salle}'; }, "« salle » n'existe pas");
refuse('date sans dérivé', (p) => { cellule(p, 2, 0).blocs[2].valeur = '{date}'; }, "s'écrit {date:jour}");
refuse('« si » qui ne nomme rien', (p) => { p.formats['4:5'].rangees[3].si = 'personne'; }, 'ni champ ni image');
refuse('en-tête sans logo', (p) => { cellule(p, 0, 0).blocs.shift(); }, "l'en-tête commun");
refuse('pied sans zurcher.edu.mg', (p) => { cellule(p, 5, 0).blocs.pop(); }, 'le pied commun');
refuse('rangées plus hautes que le visuel', (p) => { p.formats['4:5'].rangees[1].hauteur = 900; }, 'dépassent la hauteur');
refuse('format aux mauvaises dimensions', (p) => { p.formats['4:5'].hauteur = 1300; }, '1080 x 1350 attendu');
refuse('propriété inconnue', (p) => { cellule(p, 1, 0).ombre = true; }, '« ombre » est inconnu');
refuse('emplacement d\'image inconnu', (p) => { cellule(p, 1, 1).blocs[0].emplacement = 'photo'; }, "l'emplacement « photo »");
refuse('champ en double', (p) => { p.champs.push(Object.assign({}, p.champs[0])); }, 'est déjà pris');
refuse('code de catégorie en minuscules', (p) => { p.code = 'evt'; }, 'ne suit pas la forme attendue');
refuse('JSON qui n\'est pas un objet', (p) => { Object.keys(p).forEach((k) => delete p[k]); }, '« id » manque');

/* Etape 3 : les quatre formats partout, et les regles des nouveaux blocs */
for (const f of (await readdir(path.join(STUDIO, 'presets'))).filter((x) => x.endsWith('.json') && x !== 'preset.schema.json')) {
	const p = await lireJson(path.join(STUDIO, 'presets', f));
	verifier('preset ' + f + ' : les formats 4:5, 1:1, 9:16 et A4', JSON.stringify(Object.keys(p.formats)) === JSON.stringify(['4:5', '1:1', '9:16', 'A4']), Object.keys(p.formats).join(', '));
}
const ins = await lireJson(path.join(STUDIO, 'presets/ins.json'));
const com = await lireJson(path.join(STUDIO, 'presets/com.json'));
function refuseSur(base, nom, modifier, attendu) {
	const p = JSON.parse(JSON.stringify(base));
	modifier(p);
	const r = Schema.valider(p, SCHEMA);
	verifier('refusé : ' + nom, !r.ok && r.erreurs.some((e) => e.indexOf(attendu) !== -1), 'erreurs : ' + JSON.stringify(r.erreurs));
}
const blocQr = (p) => p.formats.A4.rangees[3].cellules[1].blocs.find((b) => b.type === 'qr');
refuseSur(ins, 'QR code vers un champ qui n\'est pas une adresse', (p) => { blocQr(p).valeur = '{horaires}'; }, 'de type url');
refuseSur(ins, 'QR code de 100 px', (p) => { blocQr(p).taille = 100; }, '120 px de côté');
refuseSur(ins, 'champ adresse sans longueur maximale', (p) => { delete p.champs.find((c) => c.id === 'qrUrl').max; }, '« max » (longueur) manque');
refuseSur(ins, '« sauf » qui ne nomme rien', (p) => { p.formats.A4.rangees[3].cellules[1].blocs.find((b) => b.sauf).sauf = 'personne'; }, '« sauf » nomme');
refuseSur(com, 'texte long sans « paragraphes » dans un flux', (p) => { p.champs.find((c) => c.id === 'suite').paragraphes = false; }, 'texte long à « paragraphes »');
refuseSur(com, 'page suivante sans le flux', (p) => { const c = p.formats.A4.pageSuivante.rangees[1].cellules[0]; c.blocs = c.blocs.filter((b) => b.type !== 'flux'); }, 'chacune un bloc « flux »');
refuseSur(com, 'page suivante sans pied', (p) => { p.formats.A4.pageSuivante.rangees.pop(); }, 'pageSuivante : la dernière rangée est le pied commun');
refuseSur(com, 'texte court de 500 caractères', (p) => { p.champs.find((c) => c.id === 'reference').max = 500; }, '400 caractères au plus');
refuseSur(com, 'champ d\'un format inconnu', (p) => { p.champs.find((c) => c.id === 'suite').formats = ['A3']; }, 'non permis');
refuse('« derniere » sans page suivante', (p) => { p.formats['4:5'].rangees[4].derniere = true; }, "ne sert qu'avec « pageSuivante »");
refuse('deux blocs « flux » sur une page', (p) => {
	p.champs.push({ id: 'texteLibre', libelle: 'Texte libre', type: 'texteLong', max: 500, paragraphes: true });
	cellule(p, 1, 0).blocs.push({ type: 'flux', valeur: '{texteLibre}' }, { type: 'flux', valeur: '{texteLibre}' });
}, 'un seul bloc « flux » par page');
{
	/* la pastille du pied (10 px en 1:1, 9 px en A4) n'est pas un texte : pas de plancher a 12 px */
	const r = Schema.valider(evt, SCHEMA);
	verifier('pastille de 9 et 10 px acceptée (ce n\'est pas un texte)', r.ok && evt.formats.A4.rangees[5].cellules[0].blocs[0].blocs[0].taille === 9, JSON.stringify(r.erreurs));
}

bilan();
