/* =============================================================================
   Studio UAZ — le service de rendu

   Un petit serveur HTTP, a l'ecoute de 127.0.0.1 seulement : PHP
   (app/studio.export.php) lui envoie le preset, le format, les donnees et les
   images ; il rend le visuel dans Chromium (moteur.js, src/studio/rendu.html)
   et renvoie le PNG, le JPG ou le PDF. Deux rendus a la fois, les autres
   attendent ; un rendu qui depasse le delai est abandonne.

     GET  /sante    { ok, version, enCours, enAttente }
     POST /rendre   corps JSON -> image ou PDF, ou 422 { erreur, manquants, debordements, erreurs }
     POST /lot      corps JSON (elements : [{ format, sortie, impression }]) -> { fichiers : [{ format,
                    sortie, impression, type, base64 }] } pour « Tout exporter », ou 422 { erreur, format, … }

   Reglages (variables d'environnement) :
     PORT (3310), HOTE (127.0.0.1), STUDIO_DIR (dossier src/studio, en lecture),
     CHROME (chemin d'un Chrome local ; dans le conteneur, celui de Playwright).
   Lancement local : CHROME="C:/Program Files/Google/Chrome/Application/chrome.exe" \
                     STUDIO_DIR=../../src/studio node serveur.js
   Sur le serveur : un conteneur Docker (Dockerfile, installer.sh).
   ========================================================================== */
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { lancerNavigateur, rendreVisuel, rendreLot, ErreurRendu } from './moteur.js';

const ICI = path.dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.PORT || 3310);
const HOTE = process.env.HOTE || '127.0.0.1';
const DOSSIER = path.resolve(ICI, process.env.STUDIO_DIR || '/studio');
const CHROME = process.env.CHROME || '';
const VERSION = '1.1.0';
const MAX_CORPS = 40 * 1024 * 1024;   // le preset, les textes et jusqu'a 4 images
const PARALLELE = 2;
const DELAI = 20000;
const DELAI_LOT = 60000;   // tous les formats d'un visuel

let navigateur = null;
let enCours = 0;
const attente = [];

async function navigateurPret() {
	if (navigateur && navigateur.isConnected()) { return navigateur; }
	navigateur = await lancerNavigateur({ executable: CHROME });
	navigateur.on('disconnected', () => { navigateur = null; });
	return navigateur;
}
function prendreTour() {
	if (enCours < PARALLELE) { enCours++; return Promise.resolve(); }
	return new Promise((ok) => attente.push(ok));
}
function rendreTour() {
	const suivant = attente.shift();
	if (suivant) { suivant(); } else { enCours--; }
}
function avecDelai(promesse, ms) {
	let minuteur;
	return Promise.race([
		promesse,
		new Promise((_, ko) => { minuteur = setTimeout(() => ko(new ErreurRendu(504, 'Le rendu a pris trop de temps.')), ms); }),
	]).finally(() => clearTimeout(minuteur));
}
function lireCorps(req) {
	return new Promise((ok, ko) => {
		const morceaux = [];
		let taille = 0;
		req.on('data', (m) => {
			taille += m.length;
			if (taille > MAX_CORPS) { ko(new ErreurRendu(413, 'Demande trop lourde.')); req.destroy(); return; }
			morceaux.push(m);
		});
		req.on('end', () => ok(Buffer.concat(morceaux)));
		req.on('error', ko);
	});
}
function repondreJson(res, code, objet) {
	const corps = Buffer.from(JSON.stringify(objet));
	res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': corps.length, 'Cache-Control': 'no-store' });
	res.end(corps);
}
function journal(...morceaux) {
	console.log(new Date().toISOString(), ...morceaux);
}

const serveur = http.createServer(async (req, res) => {
	const url = new URL(req.url, 'http://localhost');
	if (req.method === 'GET' && url.pathname === '/sante') {
		return repondreJson(res, 200, { ok: true, version: VERSION, enCours, enAttente: attente.length });
	}
	const lot = url.pathname === '/lot';
	if (req.method !== 'POST' || (url.pathname !== '/rendre' && !lot)) { return repondreJson(res, 404, { erreur: 'Inconnu.' }); }

	const debut = Date.now();
	let d = null;
	try {
		d = JSON.parse((await lireCorps(req)).toString('utf8'));
	} catch (e) {
		return repondreJson(res, e.code || 400, { erreur: e instanceof ErreurRendu ? e.message : 'Demande illisible (JSON attendu).' });
	}
	await prendreTour();
	try {
		const nav = await navigateurPret();
		if (lot) {
			const fichiers = await avecDelai(rendreLot(nav, d, { dossier: DOSSIER, delai: DELAI }), DELAI_LOT);
			repondreJson(res, 200, { fichiers: fichiers.map((x) => ({ format: x.format, sortie: x.sortie, impression: x.impression, type: x.type, base64: x.corps.toString('base64') })) });
			journal('lot', (d.preset && d.preset.id) || '?', fichiers.length + ' fichiers', Date.now() - debut + ' ms');
			return;
		}
		const r = await avecDelai(rendreVisuel(nav, d, { dossier: DOSSIER, delai: DELAI }), DELAI);
		res.writeHead(200, { 'Content-Type': r.type, 'Content-Length': r.corps.length, 'Cache-Control': 'no-store',
			'X-Studio-Avertissements': encodeURIComponent(JSON.stringify((r.rapport.avertissements || []).slice(0, 10))),
			'X-Studio-Pages': String(r.rapport.pages || 1) });
		res.end(r.corps);
		journal('rendu', (d.preset && d.preset.id) || '?', d.format, d.sortie + (d.impression ? ' (imprimeur)' : ''), 'x' + (d.echelle === 2 ? 2 : 1), Date.now() - debut + ' ms');
	} catch (e) {
		const code = e instanceof ErreurRendu ? e.code : 500;
		journal('refus', code, (d && d.preset && d.preset.id) || '?', d && d.format, e.message);
		repondreJson(res, code, Object.assign({ erreur: e instanceof ErreurRendu ? e.message : 'Le rendu a échoué.' }, e.format ? { format: e.format } : {}, e.rapport ? {
			manquants: e.rapport.manquants || [], debordements: e.rapport.debordements || [], erreurs: e.rapport.erreurs || [],
		} : {}));
	} finally {
		rendreTour();
	}
});

serveur.requestTimeout = 60000;
serveur.listen(PORT, HOTE, () => journal('Studio UAZ : rendu sur http://' + HOTE + ':' + PORT + ' (fichiers : ' + DOSSIER + ')'));
navigateurPret().catch((e) => journal('Chromium ne demarre pas :', e.message));
for (const signal of ['SIGINT', 'SIGTERM']) {
	process.on(signal, async () => { serveur.close(); if (navigateur) { await navigateur.close().catch(() => {}); } process.exit(0); });
}
