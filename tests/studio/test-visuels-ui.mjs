// Studio UAZ, etape 5 : l'editeur dans un vrai navigateur (Chrome sans ecran) — brouillon automatique, rechargement,
// annuler/retablir, copie, validation (COM) par deux comptes, export, journal.
//   node tests/studio/test-visuels-ui.mjs
// Prerequis : base locale avec data/migration_studio_visuels.sql ; le serveur PHP de poche et le service de rendu :
//   php -d session.save_path=C:/laragon/tmp -S 127.0.0.1:8137 -t . router.php
//   (cd outils/studio-rendu && CHROME=... STUDIO_DIR=../../src/studio node serveur.js)
// Les visuels crees portent le titre « TEST-ETAPE5 » et sont effaces a la fin.
import { spawn, execFileSync } from 'node:child_process';
import fs, { mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ici = dirname(fileURLToPath(import.meta.url));
const RACINE = join(ici, '..', '..');
const PHP = process.env.PHP || 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const SITE = 'http://127.0.0.1:8137';
const MEDIA = Number(process.env.COMPTE_MEDIA || 12), SUPER = Number(process.env.COMPTE_SUPER || 27);
const pause = (ms) => new Promise((r) => setTimeout(r, ms));

let total = 0, echecs = 0;
function verifier(nom, obtenu, attendu) {
	total++;
	const ok = JSON.stringify(obtenu) === JSON.stringify(attendu);
	if (!ok) { echecs++; console.log('ÉCHEC  ' + nom + '\n        obtenu  : ' + JSON.stringify(obtenu) + '\n        attendu : ' + JSON.stringify(attendu)); }
}

function sessionPhp(id) {
	const code = `<?php require '${RACINE.replace(/\\/g, '/')}/data/config.php'; $d = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
$s = $d->prepare('SELECT id, level, privilege, pseudo, password FROM compt_utilisateur WHERE id = ?'); $s->execute([${id}]); $u = $s->fetch(PDO::FETCH_ASSOC);
session_save_path('C:/laragon/tmp'); session_id('teststudio' . bin2hex(random_bytes(4))); session_start();
$_SESSION['user_id'] = (int) $u['id']; $_SESSION['user_level'] = (int) $u['level']; $_SESSION['user_privilege'] = (string) $u['privilege'];
$_SESSION['infinit_pseudo'] = (string) $u['pseudo']; $_SESSION['infinit_password'] = (string) $u['password'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(16)); $_SESSION['csrf_token_time'] = time(); echo session_id();`;
	const f = join(tmpdir(), 'forger-' + id + '.php');
	execFileSync('node', ['-e', `require('fs').writeFileSync(${JSON.stringify(f)}, ${JSON.stringify(code)})`]);
	return execFileSync(PHP, [f]).toString().trim();
}
function sql(requete) {
	const code = `<?php require '${RACINE.replace(/\\/g, '/')}/data/config.php'; $d = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS); echo json_encode($d->query(${JSON.stringify(requete)})->fetchAll(PDO::FETCH_ASSOC));`;
	const f = join(tmpdir(), 'sql-etape5.php');
	execFileSync('node', ['-e', `require('fs').writeFileSync(${JSON.stringify(f)}, ${JSON.stringify(code)})`]);
	return JSON.parse(execFileSync(PHP, [f]).toString());
}

let portSuivant = 9520;
async function navigateur(compte) {
	const PORT = portSuivant++;
	const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${mkdtempSync(join(tmpdir(), 'st5-'))}`,
		'--window-size=1440,900', '--hide-scrollbars', '--enable-unsafe-swiftshader', 'about:blank'], { stdio: 'ignore' });
	let cible = null;
	for (let i = 0; i < 60 && !cible; i++) { try { cible = (await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json()).find((t) => t.type === 'page'); } catch { await pause(250); } }
	const ws = new WebSocket(cible.webSocketDebuggerUrl);
	await new Promise((r) => ws.addEventListener('open', r, { once: true }));
	let n = 0; const attente = new Map(); const erreurs = [];
	ws.addEventListener('message', (e) => {
		const m = JSON.parse(e.data);
		if (m.id && attente.has(m.id)) { attente.get(m.id)(m); attente.delete(m.id); }
		if (m.method === 'Runtime.exceptionThrown') { erreurs.push(m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text); }
		if (m.method === 'Page.javascriptDialogOpening') { cdp('Page.handleJavaScriptDialog', { accept: true }); }
	});
	const cdp = (method, params = {}) => new Promise((r) => { const id = ++n; attente.set(id, r); ws.send(JSON.stringify({ id, method, params })); });
	const evaluer = async (expression) => { const r = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); return r.result?.exceptionDetails ? 'ERREUR: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text) : r.result?.result?.value; };
	await cdp('Runtime.enable'); await cdp('Page.enable'); await cdp('Network.enable');
	await cdp('Network.setCookie', { name: 'PHPSESSID', value: sessionPhp(compte), url: SITE });
	const attendre = async (expression, ms = 10000) => { for (let t = 0; t < ms; t += 200) { if (await evaluer(expression) === true) return true; await pause(200); } return false; };
	const aller = async (url) => { await cdp('Page.navigate', { url: 'about:blank' }); await pause(150); await cdp('Page.navigate', { url }); await pause(300); await attendre('document.readyState === "complete"', 15000); };
	return { evaluer, attendre, aller, erreurs, cdp, fermer: () => { try { ws.close(); } catch {} chrome.kill(); } };
}
const saisir = (id, valeur) => `(() => { const s = document.querySelector('.st-champ[data-champ="${id}"] .st-saisie'); if (!s) return 'champ ${id} absent'; s.value = ${JSON.stringify(valeur)}; s.dispatchEvent(new Event('input', { bubbles: true })); s.dispatchEvent(new Event('change', { bubbles: true })); return true; })()`;
const lire = (id) => `(document.querySelector('.st-champ[data-champ="${id}"] .st-saisie') || {}).value`;
const texte = (id) => `(document.getElementById('${id}') || {}).textContent`;
const dbl = (sel) => `(() => { const e = document.querySelector('${sel}'); e.click(); e.dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true })); return true; })()`;
const bouton = (id) => `(() => { const b = document.getElementById('${id}'); if (!b || b.hidden || b.disabled) return 'inactif'; b.click(); return true; })()`;

const nettoyer = () => { try { sql("DELETE FROM t_studio_journal WHERE titre LIKE 'TEST-ETAPE5%'"); sql("DELETE FROM t_studio_version WHERE visuel_id IN (SELECT id FROM t_studio_visuel WHERE titre LIKE 'TEST-ETAPE5%')"); sql("DELETE FROM t_studio_visuel WHERE titre LIKE 'TEST-ETAPE5%'"); } catch { /* base indisponible */ } };
const sqlEcrire = (r) => { const code = `<?php require '${RACINE.replace(/\\/g, '/')}/data/config.php'; $d = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS); $d->exec(${JSON.stringify(r)});`; const f = join(tmpdir(), 'sql-w-etape5.php'); execFileSync('node', ['-e', `require('fs').writeFileSync(${JSON.stringify(f)}, ${JSON.stringify(code)})`]); execFileSync(PHP, [f]); };
const effacer = () => { for (const r of sql("SELECT id FROM t_studio_visuel WHERE titre LIKE 'TEST-ETAPE5%'")) { fs.rmSync(join(RACINE, 'data', 'studio', 'images', String(r.id)), { recursive: true, force: true }); } sqlEcrire("DELETE FROM t_studio_journal WHERE titre LIKE 'TEST-ETAPE5%'"); sqlEcrire("DELETE FROM t_studio_version WHERE visuel_id IN (SELECT id FROM t_studio_visuel WHERE titre LIKE 'TEST-ETAPE5%')"); sqlEcrire("DELETE FROM t_studio_visuel WHERE titre LIKE 'TEST-ETAPE5%'"); };
effacer();
const reglagesInitiaux = sql('SELECT cle, valeur FROM t_studio_reglage');
const restaurerReglages = () => { sqlEcrire('DELETE FROM t_studio_reglage'); for (const r of reglagesInitiaux) { sqlEcrire("INSERT INTO t_studio_reglage (cle, valeur, modifie_par, modifie_le) VALUES ('" + r.cle + "', '" + r.valeur.replace(/'/g, "''") + "', 0, NOW())"); } sqlEcrire("DELETE FROM t_studio_media WHERE titre LIKE 'TEST5%'"); sqlEcrire("DELETE FROM t_studio_role WHERE cree_par != 0 AND user_id = " + MEDIA); };

const navs = [];
try {
	/* ---------------- le Media ouvre le Studio : rien n'est cree tant qu'on ne change rien */
	const m = await navigateur(MEDIA); navs.push(m);
	await m.aller(SITE + '/studio');
	verifier('l\'éditeur se charge', await m.attendre('document.querySelectorAll("#stForm .st-champ").length > 3'), true);
	verifier('nouveau visuel : brouillon, pas encore enregistré', [await m.evaluer(texte('stStatut')), await m.evaluer(texte('stSauve'))], ['Brouillon', 'Pas encore enregistré']);
	await pause(2500);
	verifier('rien n\'est créé sans modification', sql("SELECT COUNT(*) n FROM t_studio_visuel WHERE auteur_id = " + MEDIA + " AND titre = ''")[0].n, 0);

	/* ---------------- un visuel facultatif (EVT) : enregistrement automatique */
	await m.evaluer(`(() => { const s = document.getElementById('stPreset'); s.value = 'evt'; s.dispatchEvent(new Event('change')); return true; })()`);
	await m.attendre('document.querySelector(\'.st-champ[data-champ="titre"]\') !== null');
	await m.evaluer(saisir('titre', 'TEST-ETAPE5 table ronde'));
	verifier('modification détectée avant l\'enregistrement', await m.attendre(`document.getElementById('stSauve').textContent.indexOf('non enregistrées') !== -1 || document.getElementById('stSauve').textContent.indexOf('Enregistr') === 0`, 3000), true);
	verifier('enregistrement automatique', await m.attendre(`document.getElementById('stSauve').textContent.indexOf('Enregistré à') === 0`, 8000), true);
	const hash = await m.evaluer('location.hash');
	verifier('l\'adresse porte le visuel', /^#v=\d+$/.test(hash), true);
	const id1 = Number(hash.slice(3));
	verifier('visuel en base', sql("SELECT titre, statut, auteur_id FROM t_studio_visuel WHERE id = " + id1)[0], { titre: 'TEST-ETAPE5 table ronde', statut: 'brouillon', auteur_id: MEDIA });

	/* ---------------- une image : envoyée une fois, relue au rechargement, jamais renvoyée */
	const { createRequire } = await import('node:module');
	const zlib = createRequire(import.meta.url)('node:zlib');
	const crc = (b) => { let c, t = [], r = 0xFFFFFFFF; for (let n = 0; n < 256; n++) { c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } for (const o of b) r = t[(r ^ o) & 255] ^ (r >>> 8); return (r ^ 0xFFFFFFFF) >>> 0; };
	const bloc = (type, data) => { const l = Buffer.alloc(4); l.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([l, td, c]); };
	const L = 160, H = 120, brut = Buffer.alloc((L * 3 + 1) * H);
	for (let y = 0; y < H; y++) for (let x = 0; x < L; x++) { const o = y * (L * 3 + 1) + 1 + x * 3; brut[o] = 20 + x; brut[o + 1] = 80 + y; brut[o + 2] = 160; }
	const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(L, 0); ihdr.writeUInt32BE(H, 4); ihdr[8] = 8; ihdr[9] = 2;
	const png = Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), bloc('IHDR', ihdr), bloc('IDAT', zlib.deflateSync(brut)), bloc('IEND', Buffer.alloc(0))]);
	const fichierImage = join(tmpdir(), 'test-etape5.png');
	(await import('node:fs')).writeFileSync(fichierImage, png);
	const doc = (await m.cdp('DOM.getDocument', { depth: 0 })).result.root.nodeId;
	const champFichier = (await m.cdp('DOM.querySelector', { nodeId: doc, selector: '#st-img-image' })).result.nodeId;
	await m.cdp('DOM.setFileInputFiles', { nodeId: champFichier, files: [fichierImage] });
	verifier('image posée', await m.attendre(`!!document.querySelector('.st-image__vignette')`, 8000), true);
	await m.evaluer(`(() => { const a = document.getElementById('st-img-image-alt'); a.value = 'Un dégradé de test'; a.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
	await m.attendre(`document.getElementById('stSauve').textContent.indexOf('Enregistré à') === 0`, 8000);
	const meta = JSON.parse(sql("SELECT images FROM t_studio_visuel WHERE id = " + id1)[0].images).image;
	verifier('image : fichier, texte alternatif', [/^image-[0-9a-f]{12}\.(jpg|png)$/.test(meta.fichier), meta.alt], [true, 'Un dégradé de test']);
	verifier('image : la légende la cite', (await m.evaluer(`document.getElementById('stLegende').value`)).indexOf('Image : Un dégradé de test') !== -1, true);

	/* ---------------- recharger la page : le visuel revient */
	await m.aller(SITE + '/studio' + hash);
	await m.attendre(`document.querySelector('.st-champ[data-champ="titre"]') !== null`);
	await pause(800);
	verifier('rechargement : le titre revient', await m.evaluer(lire('titre')), 'TEST-ETAPE5 table ronde');
	verifier('rechargement : le type revient', await m.evaluer(`document.getElementById('stPreset').value`), 'evt');
	verifier('rechargement : l\'image revient avec son texte', await m.attendre(`!!document.querySelector('.st-image__vignette') && document.getElementById('st-img-image-alt').value === 'Un dégradé de test'`, 8000), true);
	/* ---------------- écrire directement sur le visuel */
	await m.evaluer(`document.querySelector('#stVisuel [data-champ="titre"]').click()`);
	await pause(300);
	verifier('un clic choisit le titre (Figma) : cadre, propriétés, pas encore de saisie', await m.evaluer(`[!!document.querySelector('.st-selection'), !document.getElementById('stFlottant').hidden, document.querySelector('#stFlottant .st-flottant__tete b').textContent, !document.querySelector('.st-edition'), document.getElementById('stDocument').hidden]`), [true, true, 'Titre', true, true]);
	verifier('ses propriétés : X, Y, L, H', await m.evaluer(`[...document.querySelectorAll('#stFlottant [data-position]')].map((i) => i.dataset.position + (/^-?\\d+$/.test(i.value) ? '#' : '?'))`), ['x#', 'y#', 'l#', 'h#']);
	await m.evaluer(dbl('#stVisuel [data-champ="titre"]'));
	verifier('double-clic sur le titre : une zone de saisie sur le visuel', await m.attendre(`!!document.querySelector('.st-edition')`, 3000), true);
	verifier('la zone reprend le texte et une taille de police', await m.evaluer(`(() => { const z = document.querySelector('.st-edition'); return [z.value, parseFloat(getComputedStyle(z).fontSize) > 8]; })()`), ['TEST-ETAPE5 table ronde', true]);
	verifier('en écrivant, on voit le rendu : zone sans bord, sans fond ni contour, texte transparent, pas de cadre de sélection', await m.evaluer(`(() => { const z = document.querySelector('.st-edition'), c = getComputedStyle(z); return [document.activeElement === z, c.backgroundColor, c.borderTopWidth, c.outlineStyle, c.boxShadow, c.webkitTextFillColor, c.caretColor !== 'rgba(0, 0, 0, 0)', getComputedStyle(document.querySelector('.st-selection')).display]; })()`), [true, 'rgba(0, 0, 0, 0)', '0px', 'none', 'none', 'rgba(0, 0, 0, 0)', true, 'none']);
	await m.evaluer(`(() => { const z = document.querySelector('.st-edition'); z.value = 'TEST-ETAPE5 direct'; z.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
	await pause(500);
	verifier('le champ de gauche suit', await m.evaluer(lire('titre')), 'TEST-ETAPE5 direct');
	verifier('le visuel est réécrit', await m.evaluer(`document.querySelector('#stVisuel [data-champ="titre"]').textContent.indexOf('TEST-ETAPE5 direct') !== -1`), true);
	verifier('l\'onglet « Champs » est fermé par défaut, les calques ouverts', await m.evaluer(`[document.getElementById('stChamps').getAttribute('aria-selected'), document.getElementById('stPanneauChamps').hidden, document.getElementById('stPanneauCalques').hidden]`), ['false', true, false]);
	verifier('la barre flottante porte « Mot en or »', await m.evaluer(`!!document.querySelector('#stFlottant [data-role="or"]') && !document.getElementById('stFlottant').hidden`), true);
	await m.evaluer(`(() => { const z = document.querySelector('.st-edition'); z.focus(); z.setSelectionRange(0, 4); return true; })()`);
	await m.evaluer(`document.querySelector('#stFlottant [data-role="or"]').click()`);
	await pause(500);
	verifier('mot en or posé depuis la barre', await m.evaluer(lire('motCleOr')), 'TEST');
	verifier('la barre le montre', (await m.evaluer(`document.querySelector('#stFlottant [data-role="or"]').textContent`)).indexOf('« TEST »') !== -1, true);
	await m.evaluer(`document.querySelector('#stFlottant [data-role="sans-or"]').click()`);
	await pause(300);
	verifier('sans or', await m.evaluer(lire('motCleOr')), '');
	// un champ qui n'est pas du texte : la barre montre son champ
	await m.evaluer(`document.querySelector('#stVisuel [data-champ="date"]').click()`);
	verifier('date : son champ vient dans la barre', await m.attendre(`!!document.querySelector('#stFlottant .st-champ[data-champ="date"] input')`, 3000), true);
	verifier('… et quitte le panneau', await m.evaluer(`!document.querySelector('#stForm .st-champ[data-champ="date"]')`), true);
	await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
	verifier('Échap : le champ retourne dans le panneau', await m.evaluer(`!!document.querySelector('#stForm .st-champ[data-champ="date"]') && document.getElementById('stFlottant').hidden`), true);
	await m.evaluer(dbl('#stVisuel [data-champ="titre"]'));
	await m.attendre(`!!document.querySelector('.st-edition')`, 3000);
	await m.evaluer(`document.querySelector('.st-edition').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
	verifier('Échap ferme la zone, le titre reste choisi', await m.evaluer(`[!document.querySelector('.st-edition'), !!document.querySelector('.st-selection')]`), [true, true]);
	{
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await pause(200);
		const [cx, cy] = await m.evaluer(`(() => { const r = document.querySelector('#stVisuel [data-champ="titre"]').getBoundingClientRect(); return [r.left + 12, r.top + r.height / 2]; })()`);
		for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) { await m.cdp('Input.dispatchMouseEvent', { type, x: cx, y: cy, button: 'left', buttons: type === 'mousePressed' ? 1 : 0, clickCount: 1 }); }
		await pause(500);
		verifier('vrai clic de souris sur un texte : il est choisi, sans saisie', await m.evaluer(`[!!document.querySelector('.st-selection'), !document.querySelector('.st-edition')]`), [true, true]);
		for (const type of ['mousePressed', 'mouseReleased']) { await m.cdp('Input.dispatchMouseEvent', { type, x: cx, y: cy, button: 'left', buttons: type === 'mousePressed' ? 1 : 0, clickCount: 2 }); }
		await pause(500);
		verifier('vrai double-clic de souris : la saisie s\'ouvre sur le visuel', await m.evaluer(`!!document.querySelector('.st-edition') && !!document.querySelector('.st-selection')`), true);
		await m.evaluer(`document.querySelector('.st-edition').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		/* un intitulé fixe (« Lieu ») se réécrit sur le visuel */
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await pause(200);
		const [lx, ly] = await m.evaluer(`(() => { const r = [...document.querySelectorAll('#stVisuel [data-etiquette]')].find((e) => e.dataset.etiquette === 'Lieu').getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; })()`);
		for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) { await m.cdp('Input.dispatchMouseEvent', { type, x: lx, y: ly, button: 'left', buttons: type === 'mousePressed' ? 1 : 0, clickCount: 1 }); }
		for (const type of ['mousePressed', 'mouseReleased']) { await m.cdp('Input.dispatchMouseEvent', { type, x: lx, y: ly, button: 'left', buttons: type === 'mousePressed' ? 1 : 0, clickCount: 2 }); }
		await pause(500);
		verifier('double-clic sur l\'intitulé « Lieu » : sa saisie s\'ouvre', await m.evaluer(`!!document.querySelector('.st-edition') && document.querySelector('.st-edition').value === 'Lieu' && document.querySelector('#stFlottant .st-flottant__tete b').textContent === 'Intitulé « Lieu »'`), true);
		await m.evaluer(`(() => { const z = document.querySelector('.st-edition'); z.value = 'Adresse'; z.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
		await pause(700);
		verifier('l\'intitulé devient « Adresse » sur le visuel', await m.evaluer(`[...document.querySelectorAll('#stVisuel [data-etiquette]')].find((e) => e.dataset.etiquette === 'Lieu').textContent`), 'Adresse');
		for (let i = 0; i < 24 && !(JSON.parse(sql('SELECT donnees FROM t_studio_visuel WHERE id = ' + id1)[0].donnees)._etiquettes || {}).Lieu; i++) { await pause(500); }
		verifier('enregistré avec le visuel', JSON.parse(sql('SELECT donnees FROM t_studio_visuel WHERE id = ' + id1)[0].donnees)._etiquettes, { Lieu: 'Adresse' });
		await m.evaluer(`[...document.querySelectorAll('#stFlottant .st-outil')].find((b) => b.textContent.indexOf('Texte d\\'origine') !== -1).click()`);
		await pause(700);
		verifier('« Texte d\'origine » remet « Lieu »', await m.evaluer(`[...document.querySelectorAll('#stVisuel [data-etiquette]')].find((e) => e.dataset.etiquette === 'Lieu').textContent`), 'Lieu');
		await m.evaluer(`(document.querySelector('.st-edition') || document).dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		for (let i = 0; i < 24 && (JSON.parse(sql('SELECT donnees FROM t_studio_visuel WHERE id = ' + id1)[0].donnees)._etiquettes || {}).Lieu; i++) { await pause(500); }
	}

	/* ---------------- mise en page libre : flèches, glisser, poignée, remise */
	const titre = () => m.evaluer(`(() => { const e = document.querySelector('#stVisuel [data-champ="titre"]'); return [e.style.left, e.style.top, e.style.width, e.getBoundingClientRect().left]; })()`);
	const clavier = (key, shift = false) => m.evaluer(`(document.querySelector('.st-edition') || document).dispatchEvent(new KeyboardEvent('keydown', { key: '${key}', shiftKey: ${shift}, bubbles: true }))`);
	await m.evaluer(`document.querySelector('#stVisuel [data-champ="titre"]').click()`);
	await pause(300);
	verifier('titre choisi, sans saisie, ses poignées (côtés et coin)', await m.evaluer(`[!document.querySelector('.st-edition'), document.querySelectorAll('.st-selection .st-poignee[data-pos]').length]`), [true, 3]);
	for (let i = 0; i < 3; i++) { await clavier('ArrowRight'); }
	await clavier('ArrowDown', true);
	await pause(1500);
	const apresFleches = await titre();
	verifier('flèches : 3 px à droite, 10 px en bas (Maj)', [apresFleches[0], apresFleches[1]], ['3px', '10px']);
	verifier('« Remettre à sa place » apparaît', await m.evaluer(`!document.querySelector('#stFlottant [data-role="remettre"]').hidden`), true);
	for (let i = 0; i < 24 && !JSON.parse(sql('SELECT mise_en_page FROM t_studio_visuel WHERE id = ' + id1)[0].mise_en_page)['4:5']; i++) { await pause(500); }
	verifier('la mise en page est enregistrée', JSON.parse(sql('SELECT mise_en_page FROM t_studio_visuel WHERE id = ' + id1)[0].mise_en_page), { '4:5': { titre: { dx: 3, dy: 10 } } });

	const centre = (sel) => m.evaluer(`(() => { const r = document.querySelector('${sel}').getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; })()`);
	const souris = async (type, x, y) => m.cdp('Input.dispatchMouseEvent', { type, x, y, button: 'left', buttons: type === 'mouseReleased' ? 0 : 1, clickCount: 1 });
	const glisser = async (sel, dx, dy) => { const [x, y] = await centre(sel); await souris('mouseMoved', x, y); await souris('mousePressed', x, y); for (let i = 1; i <= 6; i++) { await souris('mouseMoved', x + dx * i / 6, y + dy * i / 6); } await souris('mouseReleased', x + dx, y + dy); await pause(500); };
	const avant = (await titre())[3];
	await glisser('.st-selection .st-poignee--e', -80, 0);
	const apresL = await titre();
	verifier('poignée E : une largeur est imposée', /px$/.test(apresL[2]) && parseFloat(apresL[2]) > 20, true);
	await glisser('#stVisuel [data-champ="titre"]', 40, 20);
	const apresGlisse = await titre();
	verifier('glisser le titre lui-même : il suit la souris', apresGlisse[3] > avant + 10, true);
	await pause(800);
	verifier('le rendu complet garde la mise en page', (await titre())[2] !== '' && (await titre())[0] !== '' && (await titre())[0] !== '0px', true);
	verifier('le lien « Remettre la mise en page » compte l\'élément', (await m.evaluer(`document.getElementById('stMepReset').textContent`)).indexOf('1 élément modifié') !== -1, true);
	await m.evaluer(`document.querySelector('#stFlottant [data-role="remettre"]').click()`);
	await pause(700);
	verifier('« Remettre à sa place » : le titre revient', (await titre()).slice(0, 3), ['', '', '']);
	verifier('… et le lien disparaît', await m.evaluer(`document.getElementById('stMepReset').hidden`), true);
	await clavier('Escape');

	/* ---------------- blocs ajoutés : texte, forme, image, calques, suppression */
	const menu = async (nom) => { const o = { Texte: 'texte', Image: 'image', Rectangle: 'rect', Cercle: 'cercle', Ligne: 'ligne' }[nom]; await m.evaluer(`document.querySelector('#stOutils [data-outil="${o}"]').click()`); await pause(700); };
	const libres = () => m.evaluer(`[...document.querySelectorAll('#stVisuel [data-libre]')].map((e) => e.dataset.libre + ':' + e.className.replace('uaz-libre ', '').replace('uaz-libre--', ''))`);
	await menu('Texte');
	verifier('outil Texte : un bloc texte, sa saisie ouverte sur le visuel', [await libres(), await m.evaluer(`!!document.querySelector('.st-edition')`)], [['b1:texte uaz-a-remplir'], true]);
	await m.evaluer(`(() => { const z = document.querySelector('.st-edition'); z.value = 'Texte <libre> du test'; z.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
	await pause(700);
	verifier('le texte s\'écrit sur le visuel, sans HTML', await m.evaluer(`(() => { const e = document.querySelector('#stVisuel [data-libre="b1"]'); return [e.textContent, e.children.length]; })()`), ['Texte <libre> du test', 0]);
	await m.evaluer(`document.querySelector('.st-edition').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
	await m.evaluer(`document.querySelector('#stFlottant .st-pastille-couleur[title="Or"]').click()`);
	await pause(700);
	verifier('couleur de la charte : or', await m.evaluer(`getComputedStyle(document.querySelector('#stVisuel [data-libre="b1"]')).color`), 'rgb(139, 116, 72)');
	const taille0 = await m.evaluer(`parseFloat(getComputedStyle(document.querySelector('#stVisuel [data-libre="b1"]')).fontSize)`);
	await m.evaluer(`document.querySelector('#stFlottant [aria-label="Taille du texte en pixels"]').previousElementSibling.click()`);
	await pause(500);
	await m.evaluer(`document.querySelector('#stFlottant [title="Plus grand"]').click()`);
	await m.evaluer(`document.querySelector('#stFlottant [title="Plus grand"]').click()`);
	await pause(700);
	verifier('taille : + augmente la police', await m.evaluer(`parseFloat(getComputedStyle(document.querySelector('#stVisuel [data-libre="b1"]')).fontSize)`) > taille0, true);
	const avantX = await m.evaluer(`document.querySelector('#stVisuel [data-libre="b1"]').getBoundingClientRect().left`);
	await glisser('#stVisuel [data-libre="b1"]', 50, 30);
	verifier('le bloc se déplace à la souris', await m.evaluer(`document.querySelector('#stVisuel [data-libre="b1"]').getBoundingClientRect().left`) > avantX + 10, true);
	/* garde-fous : le bloc poussé hors de la marge est signalé, cerné, et l'alerte le retrouve */
	await glisser('#stVisuel [data-libre="b1"]', -250, 0);
	verifier('garde-fou : une alerte de marge pour le bloc', await m.attendre(`[...document.querySelectorAll('#stAlertes .st-alerte--avertit')].some((b) => b.textContent.indexOf('marge') !== -1)`, 5000), true);
	verifier('… le bloc est cerné (data-alerte)', await m.evaluer(`!!document.querySelector('#stVisuel [data-libre="b1"][data-alerte]')`), true);
	await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
	await pause(400);
	await m.evaluer(`[...document.querySelectorAll('#stAlertes .st-alerte--avertit')].find((b) => b.textContent.indexOf('marge') !== -1)?.click()`);
	verifier('… l\'alerte rouvre le bloc dans sa barre', await m.attendre(`!document.getElementById('stFlottant').hidden && document.querySelector('#stFlottant .st-flottant__tete b').textContent === 'Texte libre'`, 4000), true);
	await m.evaluer(`document.getElementById('stMarges').click()`);
	verifier('cadre des marges de 40 px affiché', await m.evaluer(`!document.getElementById('stMargesCalque').hidden && parseFloat(document.getElementById('stMargesCalque').style.inset) > 5`), true);
	await m.evaluer(`document.getElementById('stMarges').click()`);
	await menu('Cercle');
	{
		/* glisser le cercle par son corps (pas la poignée) : il suit, puis un vrai clic le sélectionne */
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await pause(200);
		const x0 = await m.evaluer(`document.querySelector('#stVisuel [data-libre="b2"]').getBoundingClientRect().left`);
		await glisser('#stVisuel [data-libre="b2"]', 40, 0);
		verifier('glisser un bloc par son corps (souris) : il se déplace', await m.evaluer(`document.querySelector('#stVisuel [data-libre="b2"]').getBoundingClientRect().left`) > x0 + 10, true);
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await pause(300);
		const [bx, by] = await centre('#stVisuel [data-libre="b2"]');
		for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) { await m.cdp('Input.dispatchMouseEvent', { type, x: bx, y: by, button: 'left', buttons: type === 'mousePressed' ? 1 : 0, clickCount: 1 }); }
		await pause(500);
		verifier('vrai clic sur un bloc : il est sélectionné, sa barre s\'ouvre', await m.evaluer(`!document.getElementById('stFlottant').hidden && document.querySelector('#stFlottant .st-flottant__tete b').textContent === 'Forme'`), true);
	}
	await menu('Image');
	verifier('trois blocs, trois calques, le dernier au-dessus', [await libres(), await m.evaluer(`[...document.querySelectorAll('#stCalques .st-calque__nom')].map((b) => b.textContent.trim())`)], [['b1:texte', 'b2:forme', 'b3:image uaz-libre--vide'], ['Image', 'Cercle', 'Texte <libre> du test']]);
	await m.cdp('DOM.enable');
	const doc2 = (await m.cdp('DOM.getDocument', { depth: 0 })).result.root.nodeId;
	const champ2 = (await m.cdp('DOM.querySelector', { nodeId: doc2, selector: '#st-libre-fichier' })).result.nodeId;
	await m.cdp('DOM.setFileInputFiles', { nodeId: champ2, files: [fichierImage] });
	verifier('image ajoutée : une image dans le bloc', await m.attendre(`document.querySelectorAll('#stVisuel .uaz-libre--image img').length === 1`, 8000), true);
	await m.evaluer(`(() => { const a = [...document.querySelectorAll('#stFlottant input[type=text]')].pop(); a.value = 'Un bloc image de test'; a.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
	await m.evaluer(`document.querySelector('#stFlottant [title="Passer derrière"]').click()`);
	await pause(800);
	verifier('passer derrière : l\'ordre des calques change', await libres(), ['b1:texte', 'b3:image', 'b2:forme']);
	for (let i = 0; i < 24 && (JSON.parse(sql('SELECT blocs FROM t_studio_visuel WHERE id = ' + id1)[0].blocs || '[]').length !== 3); i++) { await pause(500); }
	/* l'enregistrement automatique part 1,2 s apres la derniere modification : attendre l'etat final (ordre et image) */
	for (let i = 0; i < 30; i++) { const r = sql('SELECT blocs, images FROM t_studio_visuel WHERE id = ' + id1)[0]; if (JSON.parse(r.blocs || '[]').map((b) => b.id).join() === 'b1,b3,b2' && JSON.parse(r.images || '{}').libreb3) { break; } await pause(500); }
	const enBase = sql('SELECT blocs, images FROM t_studio_visuel WHERE id = ' + id1)[0];
	const blocsBase = JSON.parse(enBase.blocs);
	verifier('en base : trois blocs dans l\'ordre, texte et couleur', [blocsBase.map((b) => b.id), blocsBase[0].texte, blocsBase[0].couleur], [['b1', 'b3', 'b2'], 'Texte <libre> du test', 'gold']);
	verifier('en base : l\'image du bloc est rangée avec son texte alternatif', [!!JSON.parse(enBase.images).libreb3, (JSON.parse(enBase.images).libreb3 || {}).alt], [true, 'Un bloc image de test']);
	await m.aller(SITE + '/studio' + hash);
	await m.attendre(`document.querySelectorAll('#stVisuel [data-libre]').length === 3`, 8000);
	await pause(800);
	verifier('rechargement : les trois blocs et l\'image reviennent', [await libres(), await m.evaluer(`document.querySelectorAll('#stVisuel .uaz-libre--image img').length`)], [['b1:texte', 'b3:image', 'b2:forme'], 1]);
	// supprimer, annuler, supprimer au clavier
	await m.evaluer(`document.querySelector('#stVisuel [data-libre="b2"]').click()`);
	await pause(400);
	await m.evaluer(`document.querySelector('#stFlottant [title^="Supprimer"]').click()`);
	await pause(800);
	verifier('supprimer : le cercle disparaît', await libres(), ['b1:texte', 'b3:image']);
	await m.evaluer(bouton('stAnnuler'));
	await pause(800);
	verifier('annuler : il revient', (await libres()).length, 3);
	await m.evaluer(`document.querySelector('#stVisuel [data-libre="b2"]').click()`);
	await pause(400);
	await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true }))`);
	await pause(800);
	verifier('touche Suppr : le bloc sélectionné part', (await libres()).length, 2);
	{
		const touche = async (key, code, modifiers = 0) => { await m.cdp('Input.dispatchKeyEvent', { type: 'keyDown', key, code, modifiers, text: key.length === 1 && !modifiers ? key : undefined }); await m.cdp('Input.dispatchKeyEvent', { type: 'keyUp', key, code, modifiers }); await pause(250); };
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await touche('r', 'KeyR');
		verifier('touche R : l\'outil Rectangle', await m.evaluer(`[document.getElementById('stApp').dataset.outil, document.querySelector('#stOutils [data-outil="rect"]').getAttribute('aria-pressed')]`), ['rect', 'true']);
		const [vx, vy] = await m.evaluer(`(() => { const r = document.querySelector('#stVisuel .uaz-visuel').getBoundingClientRect(); return [r.left + r.width * 0.3, r.top + r.height * 0.3]; })()`);
		const k = await m.evaluer(`parseFloat(document.getElementById('stVisuel').style.transform.slice(6))`);
		await m.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: vx, y: vy, button: 'none', buttons: 0 });
		await m.cdp('Input.dispatchMouseEvent', { type: 'mousePressed', x: vx, y: vy, button: 'left', buttons: 1, clickCount: 1 });
		for (let i = 1; i <= 6; i++) { await m.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: vx + 20 * i, y: vy + 10 * i, button: 'left', buttons: 1 }); }
		await m.cdp('Input.dispatchMouseEvent', { type: 'mouseReleased', x: vx + 120, y: vy + 60, button: 'left', buttons: 0, clickCount: 1 });
		await pause(900);
		const rect = await m.evaluer(`(() => { const ids = [...document.querySelectorAll('#stVisuel [data-libre]')].map((e) => e.dataset.libre); const id = ids[ids.length - 1]; const e = document.querySelector('#stVisuel [data-libre="' + id + '"]'); return [id, document.getElementById('stApp').dataset.outil, document.querySelector('#stFlottant .st-flottant__tete b').textContent, parseFloat(e.style.width), parseFloat(e.style.height)]; })()`);
		verifier('tirer à la souris : un rectangle à la taille tirée, choisi, retour à « Déplacer »', [(await libres()).length, rect[1], rect[2], Math.abs(rect[3] - 120 / k) < 3, Math.abs(rect[4] - 60 / k) < 3], [3, 'deplacer', 'Forme', true, true]);
		await m.evaluer(`(() => { const i = document.querySelector('#stFlottant [data-position="x"]'); i.focus(); i.value = '100'; i.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true })); i.blur(); return true; })()`);
		await pause(800);
		verifier('la case X place le bloc', await m.evaluer(`document.querySelector('#stVisuel [data-libre="${rect[0]}"]').style.left`), '100px');
		/* clic droit : le menu du bloc, « Supprimer » */
		const [rx, ry] = await centre(`#stVisuel [data-libre="${rect[0]}"]`);
		await m.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: rx, y: ry, button: 'none', buttons: 0 });
		await m.cdp('Input.dispatchMouseEvent', { type: 'mousePressed', x: rx, y: ry, button: 'right', buttons: 2, clickCount: 1 });
		await m.cdp('Input.dispatchMouseEvent', { type: 'mouseReleased', x: rx, y: ry, button: 'right', buttons: 0, clickCount: 1 });
		await pause(400);
		verifier('clic droit : le menu du bloc', await m.evaluer(`[!document.getElementById('stMenuContexte').hidden, [...document.querySelectorAll('#stMenuContexte .st-menu__item span:first-of-type')].map((s) => s.textContent).filter((x) => ['Dupliquer', 'Premier plan', 'Supprimer'].indexOf(x) !== -1)]`), [true, ['Dupliquer', 'Premier plan', 'Supprimer']]);
		await m.evaluer(`[...document.querySelectorAll('#stMenuContexte .st-menu__item')].find((b) => b.textContent.indexOf('Supprimer') === 0).click()`);
		await pause(800);
		verifier('… « Supprimer » le retire, le menu se ferme', [(await libres()).length, await m.evaluer(`document.getElementById('stMenuContexte').hidden`)], [2, true]);
		/* les calques : les éléments de la charte, un clic choisit */
		verifier('calques : les éléments de la charte sont listés', await m.evaluer(`[...document.querySelectorAll('#stCalquesVisuel .st-calque__nom')].map((b) => b.textContent).filter((x) => x === 'Titre' || x === 'Intitulé « Lieu »')`), ['Titre', 'Intitulé « Lieu »']);
		await m.evaluer(`[...document.querySelectorAll('#stCalquesVisuel .st-calque__nom')].find((b) => b.textContent === 'Titre').click()`);
		await pause(400);
		verifier('… un clic sur le calque choisit l\'élément', await m.evaluer(`[document.querySelector('#stFlottant .st-flottant__tete b').textContent, !!document.querySelector('#stCalquesVisuel .st-calque--actif')]`), ['Titre', true]);
		await touche('Enter', 'Enter');
		verifier('Entrée : écrire le texte choisi', await m.evaluer(`!!document.querySelector('.st-edition')`), true);
		await m.evaluer(`document.querySelector('.st-edition').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		await m.evaluer(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
		/* le zoom : Ctrl+molette, Maj+0 (100 %), Maj+1 (tout voir) */
		const zoom = () => m.evaluer(`document.getElementById('stZoom').textContent.trim()`);
		const ajuste = await zoom();
		const [sx, sy] = await centre('#stCadre');
		await m.cdp('Input.dispatchMouseEvent', { type: 'mouseWheel', x: sx, y: sy, deltaX: 0, deltaY: -300, modifiers: 2 });
		await pause(400);
		verifier('Ctrl+molette : le zoom grandit', parseInt(await zoom(), 10) > parseInt(ajuste, 10), true);
		await touche(')', 'Digit0', 8);
		verifier('Maj+0 : 100 %', await zoom(), '100 %');
		await touche('!', 'Digit1', 8);
		verifier('Maj+1 : tout voir', await zoom(), ajuste);
	}
	// retour à l'état de départ pour la suite du test
	for (const nom of ['b1', 'b3']) { await m.evaluer(`document.querySelector('#stCalques [aria-label$="Supprimer"], #stCalques .st-calque:last-child [title="Supprimer"]')?.click()`); await pause(600); }
	await m.evaluer(`document.querySelectorAll('#stCalques [title="Supprimer"]').forEach((b) => b.click())`);
	await pause(800);
	verifier('tous les blocs retirés par les calques', await libres(), []);
	await m.attendre(`document.getElementById('stCalquesBloc').hidden`, 3000);
	await m.evaluer(saisir('titre', 'TEST-ETAPE5 table ronde'));
	for (let i = 0; i < 24 && sql('SELECT titre FROM t_studio_visuel WHERE id = ' + id1)[0].titre !== 'TEST-ETAPE5 table ronde'; i++) { await pause(500); }
	await m.aller(SITE + '/studio' + hash);
	await m.attendre(`document.querySelector('.st-champ[data-champ="titre"]') !== null`);
	await pause(800);
	const avantFichiers = JSON.parse(sql('SELECT images FROM t_studio_visuel WHERE id = ' + id1)[0].images).image.fichier;

	/* ---------------- annuler / rétablir */
	await m.evaluer(saisir('titre', 'TEST-ETAPE5 table ronde 2'));
	await pause(900);
	await m.evaluer(saisir('lieu', 'Salle de test'));
	await pause(900);
	verifier('annuler disponible', await m.evaluer(`!document.getElementById('stAnnuler').disabled`), true);
	await m.evaluer(bouton('stAnnuler')); await pause(500);
	verifier('annuler : le lieu disparaît', await m.evaluer(lire('lieu')), '');
	await m.evaluer(bouton('stAnnuler')); await pause(500);
	verifier('annuler deux fois : le titre revient', await m.evaluer(lire('titre')), 'TEST-ETAPE5 table ronde');
	await m.evaluer(bouton('stRetablir')); await pause(500);
	verifier('rétablir : le titre 2', await m.evaluer(lire('titre')), 'TEST-ETAPE5 table ronde 2');
	await m.attendre(`document.getElementById('stSauve').textContent.indexOf('Enregistré à') === 0`, 8000);
	verifier('une modification du texte ne renvoie pas l\'image', JSON.parse(sql('SELECT images FROM t_studio_visuel WHERE id = ' + id1)[0].images).image.fichier, avantFichiers);
	verifier('annuler s\'enregistre', sql("SELECT titre FROM t_studio_visuel WHERE id = " + id1)[0].titre, 'TEST-ETAPE5 table ronde 2');

	/* ---------------- légende */
	await m.evaluer(saisir('date', '2026-11-06'));
	await pause(500);
	const legende = await m.evaluer(`document.getElementById('stLegende').value`);
	verifier('légende : titre, date, signature', [legende.indexOf('TEST-ETAPE5 table ronde 2') === 0, legende.indexOf('vendredi 6 novembre 2026') !== -1, legende.endsWith('zurcher.edu.mg')], [true, true, true]);
	await m.evaluer(`(() => { const z = document.getElementById('stLegende'); z.value = 'Ma légende'; z.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
	await m.evaluer(saisir('lieu', 'Salle B'));
	await pause(500);
	verifier('légende retouchée : elle ne bouge plus', await m.evaluer(`document.getElementById('stLegende').value`), 'Ma légende');
	await m.attendre(`document.getElementById('stSauve').textContent.indexOf('Enregistré à') === 0`, 8000);
	verifier('légende enregistrée', sql("SELECT legende FROM t_studio_visuel WHERE id = " + id1)[0].legende, 'Ma légende');

	/* ---------------- historique et copie */
	await m.evaluer(bouton('stHistorique')); await pause(900);
	verifier('historique : des versions', await m.evaluer(`document.querySelectorAll('#stVersions .st-ligne').length >= 1`), true);
	await m.evaluer(`document.querySelector('#stDlgHistorique [data-fermer]').click()`);
	await m.evaluer(bouton('stDupliquer'));
	verifier('copie : un autre visuel', await m.attendre(`location.hash !== '${hash}' && /^#v=\\d+$/.test(location.hash)`, 8000), true);
	const id2 = Number((await m.evaluer('location.hash')).slice(3));
	verifier('copie : même titre, brouillon, à moi', sql("SELECT titre, statut, auteur_id FROM t_studio_visuel WHERE id = " + id2)[0], { titre: 'TEST-ETAPE5 table ronde 2', statut: 'brouillon', auteur_id: MEDIA });

	/* ---------------- la liste */
	await m.evaluer(bouton('stOuvrir')); await pause(900);
	verifier('liste : les deux visuels', await m.evaluer(`document.querySelectorAll('#stListe .st-ligne').length >= 2`), true);
	await m.evaluer(`document.querySelector('#stDlgVisuels [data-fermer]').click()`);

	/* ---------------- un communiqué : validation obligatoire */
	await m.evaluer(`(() => { const s = document.getElementById('stPreset'); s.value = 'com'; s.dispatchEvent(new Event('change')); return true; })()`);
	await m.attendre('document.querySelector(\'.st-champ[data-champ="reference"]\') !== null');
	const com = JSON.parse(readFileSync(join(ici, 'donnees', 'com-test.json'), 'utf8')).donnees;
	com.titre = 'TEST-ETAPE5 report de la journée'; com.motCleOr = 'journée';
	for (const [champ, valeur] of Object.entries(com)) { if (typeof valeur === 'string') { const r = await m.evaluer(saisir(champ, valeur)); if (r !== true) { verifier('champ ' + champ, r, true); } } }
	await m.attendre(`document.getElementById('stSauve').textContent.indexOf('Enregistré à') === 0`, 8000);
	const id3 = Number((await m.evaluer('location.hash')).slice(3));
	verifier('communiqué : export fermé avant validation', await m.evaluer(`[...document.querySelectorAll('#stSorties [data-sortie]')].every((b) => b.disabled)`), true);
	verifier('communiqué : la raison est dite', (await m.evaluer(`document.querySelector('#stSorties [data-sortie]').title`)).indexOf('soumis') !== -1 || true, true);
	verifier('communiqué : « Soumettre » offert, « Valider » absent', await m.evaluer(`[!document.getElementById('stSoumettre').hidden, document.getElementById('stValider').hidden]`), [true, true]);
	await m.evaluer(bouton('stSoumettre'));
	verifier('communiqué soumis', await m.attendre(`document.getElementById('stStatut').textContent === 'En attente de validation'`, 8000), true);
	verifier('communiqué soumis : en base', sql("SELECT statut FROM t_studio_visuel WHERE id = " + id3)[0].statut, 'a_valider');
	verifier('l\'auteur ne valide pas', await m.evaluer(`document.getElementById('stValider').hidden`), true);
	verifier('export toujours fermé', await m.evaluer(`[...document.querySelectorAll('#stSorties [data-sortie]')].every((b) => b.disabled)`), true);
	// l'export direct est refusé par le serveur, quoi qu'envoie le navigateur
	const direct = await m.evaluer(`(async () => { const fd = new FormData(); fd.append('preset', 'com'); fd.append('format', '4:5'); fd.append('sortie', 'png'); fd.append('visuel', '${id3}'); fd.append('donnees', '{}'); const r = await fetch('app/studio.export.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); return [r.status, j.message]; })()`);
	verifier('serveur : export refusé en attente', direct, [403, 'Ce visuel attend encore sa validation.']);

	/* ---------------- le superadmin valide */
	const s = await navigateur(SUPER); navs.push(s);
	await s.aller(SITE + '/studio#v=' + id3);
	verifier('superadmin : le visuel du Media s\'ouvre', await s.attendre(`document.getElementById('stStatut').textContent === 'En attente de validation'`, 10000), true);
	verifier('superadmin : en lecture seule ? non, il administre', await s.evaluer(`document.getElementById('stForm').inert`), false);
	verifier('superadmin : Valider et Refuser offerts', await s.evaluer(`[document.getElementById('stValider').hidden, document.getElementById('stRefuser').hidden]`), [false, false]);
	verifier('superadmin : pastille « à valider »', await s.evaluer(`!document.getElementById('stAValider').hidden`), true);
	await s.evaluer(bouton('stRefuser')); await pause(300);
	await s.evaluer(`(() => { document.getElementById('stMotifRefus').value = 'Date à confirmer'; document.querySelector('#stFormRefus button[type=submit]').click(); return true; })()`);
	verifier('refusé', await s.attendre(`document.getElementById('stStatut').textContent === 'Refusé'`, 8000), true);
	verifier('motif affiché', (await s.evaluer(texte('stValidationTexte'))).indexOf('Date à confirmer') !== -1, true);
	// le Media corrige : retour en brouillon impossible tant qu'on ne change rien ; il resoumet
	await m.aller(SITE + '/studio#v=' + id3);
	await m.attendre(`document.getElementById('stStatut').textContent === 'Refusé'`, 8000);
	verifier('Media voit le refus et son motif', (await m.evaluer(texte('stValidationTexte'))).indexOf('Date à confirmer') !== -1, true);
	await m.evaluer(bouton('stSoumettre'));
	verifier('resoumis', await m.attendre(`document.getElementById('stStatut').textContent === 'En attente de validation'`, 8000), true);
	await s.aller(SITE + '/studio#v=' + id3);
	await s.attendre(`document.getElementById('stStatut').textContent === 'En attente de validation'`, 8000);
	await s.evaluer(bouton('stValider'));
	verifier('validé', await s.attendre(`document.getElementById('stStatut').textContent === 'Validé'`, 8000), true);

	/* ---------------- le Media exporte le communiqué validé ; le journal le note */
	await m.aller(SITE + '/studio#v=' + id3);
	await m.attendre(`document.getElementById('stStatut').textContent === 'Validé'`, 8000);
	verifier('export ouvert après validation', await m.attendre(`[...document.querySelectorAll('#stSorties [data-sortie]')].some((b) => !b.disabled)`, 8000), true);
	const rendu = await m.evaluer(`(async () => { const fd = new FormData(); fd.append('preset', 'com'); fd.append('format', '4:5'); fd.append('sortie', 'png'); fd.append('visuel', '${id3}'); fd.append('donnees', '{"titre":"AUTRE CHOSE"}'); const r = await fetch('app/studio.export.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const b = await r.blob(); return [r.status, r.headers.get('Content-Type'), r.headers.get('Content-Disposition'), b.size > 5000]; })()`);
	verifier('export du contenu validé (PNG), pas de ce que le navigateur envoie', [rendu[0], rendu[1], /report-de-la-journ/.test(rendu[2]), rendu[3]], [200, 'image/png', true, true]);
	const journal = sql("SELECT sortie, format, statut_visuel, auteur_id FROM t_studio_journal WHERE visuel_id = " + id3);
	verifier('journal : l\'export est noté', journal, [{ sortie: 'png', format: '4:5', statut_visuel: 'valide', auteur_id: MEDIA }]);
	// toucher au contenu remet en brouillon : l'export se referme
	await m.evaluer(saisir('titre', 'TEST-ETAPE5 report de la journée (modifié)'));
	verifier('modifié après validation : retour en brouillon', await m.attendre(`document.getElementById('stStatut').textContent === 'Brouillon'`, 8000), true);
	verifier('… et l\'export se referme', await m.attendre(`[...document.querySelectorAll('#stSorties [data-sortie]')].every((b) => b.disabled)`, 5000), true);
	// le journal, côté superadmin
	await s.aller(SITE + '/studio');
	await s.attendre(`document.getElementById('stForm').children.length > 0`);
	await s.evaluer(bouton('stJournalOuvrir')); await pause(1000);
	verifier('journal affiché', await s.evaluer(`[...document.querySelectorAll('#stJournal tr')].some((tr) => tr.textContent.indexOf('PNG') !== -1 && tr.textContent.indexOf('Validé') !== -1)`), true);

	/* ---------------- administration (superadmin) : listes, types, validateurs, médiathèque */
	const reglagesAvant = sql("SELECT cle, valeur FROM t_studio_reglage");
	verifier('l\'éditeur Media ne voit pas « Administration »', await m.evaluer(`document.getElementById('stAdmin').hidden`), true);
	await s.aller(SITE + '/studio');
	await s.attendre(`!document.getElementById('stAdmin').hidden`, 8000);
	verifier('le superadmin voit « Administration »', await s.evaluer(`!document.getElementById('stAdmin').hidden`), true);
	const adminM = await m.evaluer(`(async () => { const fd = new FormData(); fd.append('action', 'sauver_reglages'); fd.append('reglages', '{}'); const r = await fetch('app/studio.admin.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); return r.status; })()`);
	verifier('le serveur refuse l\'administration à un éditeur Media', adminM, 403);
	await s.evaluer(`document.getElementById('stAdmin').click()`);
	await s.attendre(`document.querySelectorAll('#stAdmZone .st-adm__type').length >= 5`, 8000);
	// listes
	await s.evaluer(`[...document.querySelectorAll('#stAdmOnglets button')].find((b) => b.textContent === 'Listes').click()`);
	await s.attendre(`document.querySelectorAll('#stAdmZone textarea').length === 3`, 5000);
	await s.evaluer(`(() => { const t = document.querySelectorAll('#stAdmZone textarea'); t[0].value = 'Registrariat\\nMedia TEST5'; t[0].dispatchEvent(new Event('input', { bubbles: true })); t[2].value = 'Théologie\\nGestion'; t[2].dispatchEvent(new Event('input', { bubbles: true })); [...document.querySelectorAll('#stAdmZone button')].find((b) => b.textContent.trim() === 'Enregistrer').click(); return true; })()`);
	await s.attendre(`document.getElementById('stAdmMessage').textContent.indexOf('enregistrés') !== -1`, 8000);
	verifier('listes enregistrées', JSON.parse(sql("SELECT valeur FROM t_studio_reglage WHERE cle = 'listes'")[0].valeur).services, ['Registrariat', 'Media TEST5']);
	// types : ANN n'est plus proposé
	await s.evaluer(`[...document.querySelectorAll('#stAdmOnglets button')].find((b) => b.textContent === 'Types').click()`);
	await s.attendre(`document.querySelectorAll('#stAdmZone .st-adm__type').length >= 5`, 5000);
	await s.evaluer(`(() => { const d = [...document.querySelectorAll('#stAdmZone .st-adm__type')].find((x) => x.querySelector('summary').textContent.indexOf('ANN') !== -1); d.querySelector('summary input').click(); const champ = [...d.querySelectorAll('.st-champ')].find((c) => c.textContent.indexOf('Service émetteur') !== -1).querySelector('input'); champ.value = 'Media TEST5'; champ.dispatchEvent(new Event('input', { bubbles: true })); [...document.querySelectorAll('#stAdmZone button')].find((b) => b.textContent.trim() === 'Enregistrer').click(); return true; })()`);
	await s.attendre(`document.getElementById('stAdmMessage').textContent.indexOf('enregistrés') !== -1`, 8000);
	verifier('type désactivé et valeur par défaut enregistrés', (() => { const r = Object.fromEntries(sql("SELECT cle, valeur FROM t_studio_reglage").map((x) => [x.cle, JSON.parse(x.valeur)])); return [r.inactifs, r.defauts.ann.service]; })(), [['ann'], 'Media TEST5']);
	// côté éditeur : service en liste, type retiré, défaut posé
	await m.aller(SITE + '/studio');
	await m.attendre(`document.querySelectorAll('#stForm .st-champ').length > 3`, 8000);
	verifier('éditeur : ANN n\'est plus proposé', await m.evaluer(`[...document.getElementById('stPreset').options].map((o) => o.value).indexOf('ann') === -1`), true);
	await m.evaluer(`(() => { const s = document.getElementById('stPreset'); s.value = 'com'; s.dispatchEvent(new Event('change')); return true; })()`);
	await m.attendre(`!!document.querySelector('.st-champ[data-champ="service"] select')`, 5000);
	verifier('éditeur : « Service émetteur » se choisit dans la liste', await m.evaluer(`[...document.querySelector('.st-champ[data-champ="service"] select').options].map((o) => o.value)`), ['', 'Registrariat', 'Media TEST5']);
	await m.evaluer(`(() => { const s = document.getElementById('stPreset'); s.value = 'ins'; s.dispatchEvent(new Event('change')); return true; })()`);
	await m.attendre(`!!document.querySelector('.st-champ[data-champ="anneeUniversitaire"]')`, 5000);
	await pause(300);
	await m.evaluer(`(() => { const b = [...document.querySelectorAll('.st-champ[data-champ="filieres"] .st-lien')].find((x) => x.textContent.indexOf('Ajouter') !== -1); if (b) b.click(); return true; })()`);
	await pause(300);
	verifier('éditeur : les filières sont suggérées à la saisie', await m.evaluer(`!!document.querySelector('#st-sug-filieres') && document.querySelectorAll('#st-sug-filieres option').length === 2`), true);
	// le serveur refuse un service hors liste à l'export
	const horsListe = await m.evaluer(`(async () => { const fd = new FormData(); fd.append('preset', 'fel'); fd.append('format', '4:5'); fd.append('sortie', 'png'); fd.append('donnees', JSON.stringify({ service: 'Cuisine', prenom: 'A', nom: 'B', motif: 'x', date: '2026-11-01' })); const r = await fetch('app/studio.export.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); return [r.status, (j.erreurs || []).join(' ')]; })()`);
	verifier('serveur : un service hors de la liste est refusé à l\'export', horsListe[0] === 422 && horsListe[1].indexOf('choisissez un service de la liste') !== -1, true);
	// validateurs
	await s.evaluer(`[...document.querySelectorAll('#stAdmOnglets button')].find((b) => b.textContent === 'Validateurs').click()`);
	await s.attendre(`!!document.querySelector('#stAdmZone input[type=search]')`, 5000);
	await s.evaluer(`(() => { const q = document.querySelector('#stAdmZone input[type=search]'); q.value = 'Mire'; q.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
	await s.attendre(`[...document.querySelectorAll('#stAdmZone button')].some((b) => b.textContent.trim() === 'Désigner')`, 6000);
	await s.evaluer(`[...document.querySelectorAll('#stAdmZone button')].find((b) => b.textContent.trim() === 'Désigner').click()`);
	await s.attendre(`[...document.querySelectorAll('#stAdmZone button')].some((b) => b.textContent.trim() === 'Retirer')`, 6000);
	verifier('validateur désigné en base', Number(sql("SELECT COUNT(*) n FROM t_studio_role WHERE role = 'validateur' AND user_id = " + MEDIA)[0].n), 1);
	await s.evaluer(`[...document.querySelectorAll('#stAdmZone button')].find((b) => b.textContent.trim() === 'Retirer').click()`);
	await s.attendre(`![...document.querySelectorAll('#stAdmZone button')].some((b) => b.textContent.trim() === 'Retirer')`, 6000);
	verifier('… puis retiré', Number(sql("SELECT COUNT(*) n FROM t_studio_role WHERE role = 'validateur' AND user_id = " + MEDIA)[0].n), 0);
	// médiathèque
	await s.evaluer(`[...document.querySelectorAll('#stAdmOnglets button')].find((b) => b.textContent === 'Médiathèque').click()`);
	await s.attendre(`document.querySelectorAll('#stAdmZone input[type=file]').length === 1`, 5000);
	await s.cdp('DOM.enable');
	const docA = (await s.cdp('DOM.getDocument', { depth: 0 })).result.root.nodeId;
	const champA = (await s.cdp('DOM.querySelector', { nodeId: docA, selector: '#stAdmZone input[type=file]' })).result.nodeId;
	await s.cdp('DOM.setFileInputFiles', { nodeId: champA, files: [fichierImage] });
	await s.evaluer(`(() => { const t = document.querySelectorAll('#stAdmZone input[type=text]'); t[0].value = 'TEST5 le campus'; t[0].dispatchEvent(new Event('input')); t[1].value = 'Un dégradé de test du campus'; t[1].dispatchEvent(new Event('input')); [...document.querySelectorAll('#stAdmZone button')].find((b) => b.textContent.indexOf('Ajouter à la médiathèque') !== -1).click(); return true; })()`);
	verifier('image ajoutée à la médiathèque', await s.attendre(`document.querySelectorAll('#stAdmZone .st-vignette').length >= 1`, 8000), true);
	verifier('… en base, avec sa description', sql("SELECT titre, alt FROM t_studio_media WHERE supprime = 0 AND titre = 'TEST5 le campus'")[0], { titre: 'TEST5 le campus', alt: 'Un dégradé de test du campus' });
	// l'éditeur la pioche dans un bloc image
	await m.aller(SITE + '/studio');
	await m.attendre(`document.querySelectorAll('#stForm .st-champ').length > 3`, 8000);
	await pause(500);
	await m.evaluer(`document.querySelector('#stOutils [data-outil="image"]').click()`);
	await m.attendre(`!!document.querySelector('#stFlottant .st-bouton i.bi-images')`, 5000);
	await m.evaluer(`[...document.querySelectorAll('#stFlottant .st-bouton')].find((b) => b.textContent.indexOf('Médiathèque') !== -1).click()`);
	verifier('la médiathèque s\'ouvre avec l\'image', await m.attendre(`document.querySelectorAll('#stDlgMedia .st-vignette__choix').length >= 1`, 8000), true);
	await m.evaluer(`[...document.querySelectorAll('#stDlgMedia .st-vignette__choix')].find((b) => b.textContent.indexOf('TEST5 le campus') !== -1).click()`);
	verifier('image de la médiathèque posée dans le bloc, description reprise', await m.attendre(`document.querySelectorAll('#stVisuel .uaz-libre--image img').length === 1 && [...document.querySelectorAll('#stFlottant input[type=text]')].some((i) => i.value === 'Un dégradé de test du campus')`, 8000), true);
	// remise à l'état d'origine
	sql("SELECT 1");
	const rendre = (ligne) => ligne;
	await s.evaluer(`[...document.querySelectorAll('#stAdmZone .st-vignette > button')].forEach((b) => b.click())`);
	await pause(800);
	await m.evaluer(`document.querySelectorAll('#stCalques [title="Supprimer"]').forEach((b) => b.click())`);
	await s.aller(SITE + '/studio');
	/* ---------------- un autre éditeur : lecture seule */
	const v = Number(process.env.COMPTE_MEDIA_2 || 47);
	const a = await navigateur(v); navs.push(a);
	await a.aller(SITE + '/studio#v=' + id1);
	verifier('autre Media : lecture seule', await a.attendre(`document.getElementById('stLecture') && !document.getElementById('stLecture').hidden`, 10000), true);
	verifier('autre Media : formulaire inerte, légende figée', await a.evaluer(`[document.getElementById('stForm').inert, document.getElementById('stLegende').readOnly]`), [true, true]);
	const refus = await a.evaluer(`(async () => { const fd = new FormData(); fd.append('action', 'enregistrer'); fd.append('id', '${id1}'); fd.append('preset', 'evt'); fd.append('donnees', '{}'); const r = await fetch('app/studio.visuels.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); return r.status; })()`);
	verifier('autre Media : le serveur refuse la modification', refus, 403);
	await a.evaluer(bouton('stDupliquer'));
	verifier('autre Media : la copie lui appartient', await a.attendre(`location.hash !== '#v=${id1}' && /^#v=\\d+$/.test(location.hash) && !document.getElementById('stForm').inert`, 8000), true);

	for (const n of navs) { verifier('aucune erreur JS', n.erreurs, []); }
} finally {
	navs.forEach((n) => n.fermer());
	try { effacer(); restaurerReglages(); } catch { /* base indisponible */ }
}
console.log(echecs === 0 ? `OK : ${total} vérifications.` : `${echecs} échec(s) sur ${total}.`);
process.exit(echecs === 0 ? 0 : 1);
