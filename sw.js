/* Service worker — Infinit Registrar, application installable.
 *
 * Place a la racine pour couvrir tout le site (un service worker ne voit
 * que son dossier et en dessous). Enregistre par src/js/pwa.js.
 *
 * Regle de confidentialite : les pages portent des notes, des soldes, des
 * messages ; elles viennent toujours du serveur, SAUF celles de la liste
 * PAGES_HORS_LIGNE (les modules qui marchent sans reseau : registre de
 * presence, remise des notes). Pour celles-la, la derniere version vue est gardee SUR CET
 * APPAREIL, au nom du compte qui l'a vue (en-tete X-Utilisateur envoye par
 * le serveur) :
 *   - le reseau d'abord (4 s), la copie seulement s'il est absent ou trop lent ;
 *   - jamais montree a un autre compte, effacee a la deconnexion (adresse
 *     /logout) et au bout de 14 jours.
 * Sont aussi gardes :
 *   - la page « hors connexion » et son icone (src/pwa/) ;
 *   - les feuilles de style, scripts, polices et images de src/ appeles
 *     avec un numero de version (?v=...) : le meme fichier ne change jamais
 *     sous la meme adresse, on peut donc le reprendre sans redemander.
 * Tout le reste (autres pages, /espace, /data, API, messagerie, photos)
 * passe tel quel au reseau, sans copie.
 * Les changements faits hors ligne sont dans la file de src/js/hors-ligne.js.
 */
'use strict';

/* A changer quand la page hors connexion change : le nouveau service worker
   la recharge et efface l'ancienne copie. */
const BASE_CACHE = 'registrar-base-2';
const STATIQUE = 'registrar-statique';
const STATIQUE_MAX = 200;
const PAGES = 'registrar-pages-';          // + numero du compte : une boite par compte
const META = 'registrar-meta';             // quel compte est connecte sur cet appareil
const PAGES_MAX = 80;
const COPIE_DUREE = 14 * 24 * 3600 * 1000; // une copie plus vieille n'est plus servie
const DELAI_RESEAU = 4000;                 // au-dela, on prend la copie

const BASE = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const PAGES_HORS_LIGNE = [BASE + '/teacher/presence', BASE + '/teacher/remise-notes'];
const HORS_LIGNE = BASE + '/src/pwa/hors-ligne.html';
const A_PRECHARGER = [HORS_LIGNE, BASE + '/src/pwa/icone-192.png'];

self.addEventListener('install', (e) => {
	e.waitUntil(
		caches.open(BASE_CACHE)
			.then((c) => c.addAll(A_PRECHARGER.map((u) => new Request(u, { cache: 'reload' }))))
			.then(() => self.skipWaiting())
	);
});

self.addEventListener('activate', (e) => {
	e.waitUntil((async () => {
		const garder = [BASE_CACHE, STATIQUE, META];
		for (const nom of await caches.keys()) {
			if (nom.startsWith('registrar-') && !garder.includes(nom) && !nom.startsWith(PAGES)) await caches.delete(nom);
		}
		/* La page part vers le serveur pendant que le service worker
		   demarre, au lieu d'attendre qu'il soit pret. */
		if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
		await self.clients.claim();
	})());
});

self.addEventListener('fetch', (e) => {
	const req = e.request;
	if (req.method !== 'GET') return;
	const url = new URL(req.url);
	if (url.origin === self.location.origin && PAGES_HORS_LIGNE.includes(url.pathname)) {
		/* La page et ses morceaux (?fragment=1) : reseau d'abord, copie du compte sinon. */
		e.respondWith(pageHorsLigne(e, req.mode === 'navigate'));
		return;
	}
	if (req.mode === 'navigate') {
		if (url.origin === self.location.origin && url.pathname === BASE + '/logout') e.waitUntil(purger());
		e.respondWith(naviguer(e));
		return;
	}
	if (url.origin === self.location.origin && estStatique(url)) {
		e.respondWith(statique(e, url));
	}
});

/* ------------------------------------------------ pages gardees hors ligne */

async function compteCourant() {
	const c = await caches.open(META);
	const r = await c.match('/__compte');
	return r ? (await r.text()).trim() : '';
}
async function noterCompte(id) {
	const c = await caches.open(META);
	await c.put('/__compte', new Response(String(id)));
}

/* Tout effacer : deconnexion. */
async function purger() {
	for (const nom of await caches.keys()) {
		if (nom.startsWith(PAGES) || nom === META) await caches.delete(nom);
	}
}

/* Un autre compte se connecte sur cet appareil : les copies des autres comptes partent. */
async function nouveauCompte(id) {
	await noterCompte(id);
	for (const nom of await caches.keys()) {
		if (nom.startsWith(PAGES) && nom !== PAGES + id) await caches.delete(nom);
	}
}

self.addEventListener('message', (e) => {
	const d = e.data || {};
	if (d.type === 'purger') e.waitUntil(purger());
	else if (d.type === 'utilisateur' && d.id) e.waitUntil(nouveauCompte(d.id));
});

async function pageHorsLigne(e, navigation) {
	const req = e.request;
	try {
		let rep = navigation ? await e.preloadResponse : null;
		if (!rep) {
			rep = await Promise.race([
				fetch(req),
				new Promise((_, ko) => setTimeout(() => ko(new Error('lent')), DELAI_RESEAU)),
			]);
		}
		const compte = rep.headers.get('X-Utilisateur');
		if (rep.ok && !rep.redirected && compte && /^[1-9]\d*$/.test(compte)) {
			const aRanger = rep.clone();
			e.waitUntil(ranger_page(req, compte, aRanger));
		}
		return rep;
	} catch (err) {
		const copie = await copieDuCompte(req);
		if (copie) return copie;
		if (navigation) return naviguerSansReseau();
		return new Response('', { status: 503, statusText: 'Hors ligne' });
	}
}

async function ranger_page(req, compte, rep) {
	const cache = await caches.open(PAGES + compte);
	const entetes = new Headers(rep.headers);
	entetes.set('X-Copie-Le', String(Date.now()));
	const corps = await rep.blob();
	await cache.put(req.url, new Response(corps, { status: rep.status, statusText: rep.statusText, headers: entetes }));
	await noterCompte(compte);
	const cles = await cache.keys();
	for (let i = 0; i < cles.length - PAGES_MAX; i++) await cache.delete(cles[i]);
}

async function copieDuCompte(req) {
	const compte = await compteCourant();
	if (!compte) return null;
	const cache = await caches.open(PAGES + compte);
	const rep = await cache.match(req.url);
	if (!rep) return null;
	const le = parseInt(rep.headers.get('X-Copie-Le') || '0', 10);
	if (!le || Date.now() - le > COPIE_DUREE) { await cache.delete(req.url); return null; }
	return rep;
}

/* La page « hors connexion » (aucune copie de la page demandee). */
async function naviguerSansReseau() {
	const page = await caches.match(HORS_LIGNE, { cacheName: BASE_CACHE });
	if (!page) return Response.error();
	if (!BASE) return page;
	const html = (await page.text()).replaceAll('"/src/pwa/', '"' + BASE + '/src/pwa/');
	return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

/* Une page : toujours le reseau. Sans reseau, la page hors connexion, a
   l'adresse demandee (« Reessayer » la redemande). */
async function naviguer(e) {
	try {
		const avance = await e.preloadResponse;
		if (avance) return avance;
		return await fetch(e.request);
	} catch (err) {
		const page = await caches.match(HORS_LIGNE, { cacheName: BASE_CACHE });
		if (!page) return Response.error();
		if (!BASE) return page;
		/* Application installee dans un sous-dossier : l'icone de la page
		   est ecrite depuis la racine. */
		const html = (await page.text()).replaceAll('"/src/pwa/', '"' + BASE + '/src/pwa/');
		return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
	}
}

function estStatique(url) {
	const p = url.pathname;
	if (!p.startsWith(BASE + '/src/')) return false;
	if (p.startsWith(BASE + '/src/pwa/')) return true;
	return /\.(css|js|woff2?|png|svg|webp|jpe?g|gif)$/i.test(p) && /(^|[?&])v=/.test(url.search.slice(1));
}

async function statique(e, url) {
	const copie = await caches.match(e.request); // y compris la page hors connexion et son icone
	if (copie) return copie;
	const rep = await fetch(e.request);
	if (rep.ok && rep.type === 'basic') {
		/* clone() tout de suite : une fois la reponse rendue a la page, son corps est lu et le clone echoue */
		const copieARanger = rep.clone();
		e.waitUntil(caches.open(STATIQUE).then((cache) => ranger(cache, e.request, url, copieARanger)));
	}
	return rep;
}

/* ------------------------------------------------ notifications push
   Envoyees par data/push.php a chaque message, a qui n'a pas la messagerie
   sous les yeux. Le contenu arrive chiffre pour ce seul navigateur. */

self.addEventListener('push', (e) => {
	let d = {};
	try { d = e.data ? e.data.json() : {}; } catch (err) { d = { corps: e.data ? e.data.text() : '' }; }
	e.waitUntil((async () => {
		await self.registration.showNotification(d.titre || 'Infinit Registrar', {
			body: d.corps || 'Nouveau message',
			icon: BASE + '/src/pwa/icone-192.png',
			badge: BASE + '/src/pwa/badge-96.png',
			tag: d.tag || 'messagerie',   // une notification par discussion, remplacee au message suivant
			renotify: true,
			lang: 'fr',
			data: { url: d.url || BASE + '/messagerie', conversation: d.conversation || 0 },
		});
		/* Pastille sur l'icone de l'application installee (la page la corrige). */
		if (self.navigator.setAppBadge) { try { await self.navigator.setAppBadge(); } catch (err) { /* non gere */ } }
	})());
});

/* Toucher la notification : la discussion s'ouvre dans une fenetre deja
   ouverte de l'application (sans la recharger : une saisie en cours n'est
   pas perdue, src/js/pwa.js), sinon dans une nouvelle. */
self.addEventListener('notificationclick', (e) => {
	e.notification.close();
	const d = e.notification.data || {};
	const url = new URL(d.url || BASE + '/messagerie', self.location.origin).href;
	e.waitUntil((async () => {
		const fenetres = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
		const surMessagerie = (c) => new URL(c.url).pathname === BASE + '/messagerie';
		const cible = fenetres.find(surMessagerie) || fenetres.find((c) => c.focused)
			|| fenetres.find((c) => c.visibilityState === 'visible') || fenetres[0];
		if (cible) {
			try { await cible.focus(); } catch (err) { /* focus refuse */ }
			cible.postMessage({ type: 'messagerie:ouvrir', conversation: d.conversation || 0, url });
			return;
		}
		await self.clients.openWindow(url);
	})());
});

/* Une seule version de chaque fichier : la nouvelle chasse l'ancienne. */
async function ranger(cache, req, url, rep) {
	const cles = await cache.keys();
	for (const k of cles) {
		const u = new URL(k.url);
		if (u.pathname === url.pathname && u.search !== url.search) await cache.delete(k);
	}
	await cache.put(req, rep);
	const reste = await cache.keys();
	for (let i = 0; i < reste.length - STATIQUE_MAX; i++) await cache.delete(reste[i]);
}
