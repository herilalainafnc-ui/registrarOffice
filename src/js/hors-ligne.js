/* Mode hors ligne — charge par init/pwa.php sur chaque page d'une personne connectee.
 *
 * Le socle commun des modules « hors ligne d'abord » (premier module : le registre de presence,
 * src/js/teacher-presence.js) :
 *
 *   - l'etat du reseau, et une pastille qui n'apparait que quand elle sert : « Hors ligne »,
 *     « 3 en attente », « 2 a verifier », « Tout est envoye » ;
 *   - une FILE D'ATTENTE locale (IndexedDB) : HorsLigne.envoyer() essaie le reseau ; sans reseau,
 *     le changement est range sur l'appareil et part tout seul au retour du reseau, dans l'ordre,
 *     avec un jeton de securite frais (app/session.jeton.php) et l'instant ou il a ete fait ;
 *   - les conflits : le serveur fait foi. Un changement devenu obsolete (409) n'ecrase rien ; la
 *     pastille le propose a la verification : « Appliquer quand meme » ou « Ignorer » ;
 *   - la session : si elle a expire, la file attend la reconnexion au lieu d'envoyer dans le vide ;
 *   - la confidentialite : la file et la liste des pages gardees sont propres au compte ; la
 *     deconnexion les efface (et previent s'il reste des changements non envoyes) ; /sw.js efface
 *     les copies de pages.
 *
 * L'envoi se fait quand l'application est ouverte (retour du reseau, retour sur l'onglet, toutes les
 * 30 s). Les pages gardees et la politique de cache sont dans /sw.js.
 *
 * API : window.HorsLigne = { actif, envoyer(options), enAttente(), enLigne(), retirer(predicat),
 *   alias(provisoire), rejouer(), noterPage(), purger(), maintenant() } ; evenements sur document :
 *   'horsligne' (detail.type : etat | mis-en-file | envoye | refuse | produit | fini).
 */
(function () {
	'use strict';

	var meta = document.querySelector('meta[name="registrar-utilisateur"]');
	var UTIL = meta ? parseInt(meta.getAttribute('content'), 10) || 0 : 0;
	var script = document.currentScript;
	var BASE = (script && script.getAttribute('data-base')) || window.APP_BASE || '';
	var URL_JETON = BASE + '/app/session.jeton.php';
	var PAGES_HORS_LIGNE = ['/teacher/presence', '/teacher/remise-notes'];   // les pages dont /sw.js garde une copie
	var DELAI_RENVOI = 30000;
	var ESSAIS_MAX = 5;

	var actif = !!(UTIL && window.indexedDB && window.fetch && window.Promise);
	var enLigneSur = navigator.onLine !== false;   // faux des qu'une requete echoue faute de reseau
	var decalage = 0;                              // horloge du serveur - horloge de l'appareil, en ms
	var sessionOuverte = true;
	var envoiEnCours = null;
	var minuterie = 0;
	var base = null;

	function maintenant() { return Date.now() + decalage; }
	function paires(champs) {
		if (!champs) { return []; }
		if (Array.isArray(champs)) { return champs.map(function (p) { return [String(p[0]), String(p[1])]; }); }
		return Object.keys(champs).map(function (k) { return [k, String(champs[k])]; });
	}
	function evenement(type, detail) {
		var d = detail || {};
		d.type = type;
		try { document.dispatchEvent(new CustomEvent('horsligne', { detail: d })); } catch (e) { /* ancien navigateur */ }
	}

	/* ------------------------------------------------ envoi direct, sans file (navigateur sans IndexedDB) */
	function envoiSimple(o) {
		var fd = new FormData();
		paires(o.champs).forEach(function (p) { fd.append(p[0], p[1]); });
		fd.append('csrf_token', window.CSRF_TOKEN || '');
		return fetch(o.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok && d.ok !== false, status: r.status, data: d }; }); })
			.catch(function () { return { ok: false, status: 0, data: { message: 'Connexion perdue : rien n\'a ete enregistre.' }, reseau: true }; });
	}

	if (!actif) {
		window.HorsLigne = {
			actif: false, utilisateur: UTIL, envoyer: envoiSimple, enAttente: function () { return Promise.resolve(0); },
			enLigne: function () { return navigator.onLine !== false; }, retirer: function () { return Promise.resolve(0); },
			alias: function (x) { return x; }, rejouer: function () { return Promise.resolve(); }, noterPage: function () {},
			purger: function () { return Promise.resolve(); }, maintenant: Date.now,
		};
		return;
	}

	/* ------------------------------------------------ IndexedDB */
	function ouvrir() {
		return new Promise(function (ok, ko) {
			var r = indexedDB.open('registrar-hors-ligne', 1);
			r.onupgradeneeded = function () {
				var d = r.result;
				if (!d.objectStoreNames.contains('file')) { d.createObjectStore('file', { keyPath: 'id', autoIncrement: true }).createIndex('utilisateur', 'utilisateur'); }
				if (!d.objectStoreNames.contains('pages')) { d.createObjectStore('pages', { keyPath: 'cle' }); }
				if (!d.objectStoreNames.contains('meta')) { d.createObjectStore('meta', { keyPath: 'cle' }); }
			};
			r.onsuccess = function () { ok(r.result); };
			r.onerror = function () { ko(r.error); };
		});
	}
	function bd() { return base || (base = ouvrir()); }
	/* fn(store) retourne une requete ; la promesse rend son resultat une fois la transaction finie. */
	function tx(magasin, mode, fn) {
		return bd().then(function (db) {
			return new Promise(function (ok, ko) {
				var t = db.transaction(magasin, mode), res;
				try { res = fn(t.objectStore(magasin)); } catch (e) { ko(e); return; }
				t.oncomplete = function () { ok(res && 'result' in res ? res.result : res); };
				t.onerror = t.onabort = function () { ko(t.error); };
			});
		});
	}
	function noter(cle, valeur) { return tx('meta', 'readwrite', function (s) { return s.put({ cle: cle, valeur: valeur }); }).catch(function () {}); }
	function lire(cle) { return tx('meta', 'readonly', function (s) { return s.get(cle); }).then(function (r) { return r ? r.valeur : undefined; }).catch(function () { return undefined; }); }

	function lister() {
		return tx('file', 'readonly', function (s) { return s.index('utilisateur').getAll(IDBKeyRange.only(UTIL)); })
			.then(function (l) { return l.sort(function (a, b) { return a.id - b.id; }); }).catch(function () { return []; });
	}
	function ajouter(item) { return tx('file', 'readwrite', function (s) { return s.add(item); }).then(function (id) { item.id = id; return item; }); }
	function maj(item) { return tx('file', 'readwrite', function (s) { return s.put(item); }); }
	function supprimer(id) { return tx('file', 'readwrite', function (s) { return s.delete(id); }); }

	/* ------------------------------------------------ reseau, jeton */
	function reseauRevenu() { enLigneSur = true; }
	/* Le jeton frais, la session, l'heure du serveur. Toute reponse HTTP prouve le reseau. */
	function jeton() {
		return fetch(URL_JETON, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) {
				var revenu = !enLigneSur;
				reseauRevenu();
				if (revenu) { evenement('etat', { enLigne: true }); }
				if (r.status === 401 || r.status === 403 || r.redirected) { return { session: false }; }
				if (!r.ok) { return { erreur: r.status }; }
				return r.json().then(function (d) {
					if (d.serveur_le) { decalage = d.serveur_le - Date.now(); noter('decalage', decalage); }
					if (d.csrf_token) { window.CSRF_TOKEN = d.csrf_token; }
					return d;
				});
			})
			.catch(function () { enLigneSur = false; return { reseau: false }; });
	}

	/* Une requete d'ecriture. Rend { reseau:true } si elle n'est pas partie, sinon { status, data }. */
	function poster(item, horsLigne, forcer) {
		var fd = new FormData();
		item.champs.forEach(function (p) { fd.append(p[0], p[1]); });
		fd.append('csrf_token', window.CSRF_TOKEN || '');
		if (horsLigne && !forcer) { fd.append('client_le', String(Math.round(item.creeLe))); }
		if (forcer) { fd.append('forcer', '1'); }
		return fetch(item.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) {
				var revenu = !enLigneSur;
				reseauRevenu();
				if (revenu) { evenement('etat', { enLigne: true }); }
				if (r.redirected || r.status === 401) { return { session: false, status: r.status, data: {} }; }
				return r.json().then(function (d) { return { status: r.status, data: d || {} }; }, function () { return { status: r.status, data: {} }; });
			})
			.catch(function (e) { enLigneSur = false; (window.__hlErreurs = window.__hlErreurs || []).push(item.url.split('/').pop() + ' : ' + (e && e.message || e)); return { reseau: true }; });
	}

	/* ------------------------------------------------ file d'attente */
	function miseEnFile(item) {
		return ajouter(item).then(function () {
			evenement('mis-en-file', { item: item });
			majUi();
			/* Une requete isolee a pu echouer sans que le reseau soit perdu : premier nouvel essai vite, puis la cadence normale. */
			planifier(2500);
			return { ok: true, enFile: true, item: item, data: {}, status: 0 };
		});
	}

	/**
	 * options : module, url, champs ({k:v} ou [[k,v]]), libelle (le changement, pour la liste),
	 *   fileSeulement (ne pas tenter le reseau : le changement vise un identifiant provisoire),
	 *   produit ({ champ, provisoire, reponse } : la reponse donne l'identifiant reel qui remplace
	 *   l'identifiant provisoire dans les changements suivants), acquis404 (un 404 vaut « deja fait » :
	 *   suppression), horsLigneSeulement.
	 * Rend { ok, status, data } ; { ok:true, enFile:true } si le changement est range sur l'appareil.
	 */
	function envoyer(o) {
		o = o || {};
		var item = { utilisateur: UTIL, module: o.module || '', url: o.url, champs: paires(o.champs), libelle: o.libelle || '',
			creeLe: maintenant(), statut: 'attente', essais: 0, produit: o.produit || null, acquis404: !!o.acquis404 };
		return lister().then(function (liste) {
			/* L'ordre compte : derriere un changement deja en attente, on ne double pas la file. */
			var derriere = liste.some(function (i) { return i.statut === 'attente' && i.module === item.module; });
			if (o.fileSeulement || derriere || navigator.onLine === false || !enLigneSur) { return miseEnFile(item); }
			/* Session expiree : le changement est garde, il partira apres la reconnexion. */
			var sessionPerdue = function () { sessionOuverte = false; return miseEnFile(item); };
			return poster(item, false).then(function (r) {
				if (r.reseau) { return miseEnFile(item); }
				if (r.data && r.data.csrf_expire) {
					return jeton().then(function (j) {
						if (j.session === false) { return sessionPerdue(); }
						if (j.reseau === false) { return miseEnFile(item); }
						return poster(item, false).then(function (r2) {
							if (r2.reseau) { return miseEnFile(item); }
							if (r2.session === false) { return sessionPerdue(); }
							return { ok: r2.status >= 200 && r2.status < 300 && r2.data.ok !== false, status: r2.status, data: r2.data };
						});
					});
				}
				if (r.session === false) { return sessionPerdue(); }
				var fait = r.status >= 200 && r.status < 300 && r.data.ok !== false;
				if (!fait && o.acquis404 && r.status === 404) { return { ok: true, status: 200, data: {} }; }
				return { ok: fait, status: r.status, data: r.data };
			});
		});
	}

	function enAttente() { return lister().then(function (l) { return l.length; }); }

	/* Les changements d'un module qu'un predicat designe, retires de la file (ex. : la seance ouverte hors ligne puis supprimee). */
	function retirer(predicat) {
		return lister().then(function (l) {
			var cibles = l.filter(predicat);
			return Promise.all(cibles.map(function (i) { return supprimer(i.id); })).then(function () { majUi(); return cibles.length; });
		});
	}

	/* ------------------------------------------------ envoi au retour du reseau */
	function planifier(delai) {
		clearTimeout(minuterie);
		minuterie = setTimeout(function () { rejouer(); }, delai);
	}

	function rejouer() {
		if (envoiEnCours) { return envoiEnCours; }
		var tourner = function () { return boucle(); };
		envoiEnCours = (navigator.locks ? navigator.locks.request('registrar-hors-ligne', tourner) : tourner())
			.catch(function () {})
			.then(function () { envoiEnCours = null; majUi(); });
		majUi();
		return envoiEnCours;
	}

	var essaisJeton = {};
	var essaisReseau = {};

	function pauseMs(ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); }

	/* Le premier essai apres une coupure tombe parfois sur une connexion devenue morte : un second, tout de suite, passe. */
	function jetonAvecReprise() {
		return jeton().then(function (j) { return j.reseau === false ? pauseMs(1500).then(jeton) : j; });
	}

	function boucle() {
		essaisJeton = {};
		essaisReseau = {};
		return lister().then(function (liste) {
			if (!liste.some(function (i) { return i.statut === 'attente'; })) { return; }
			return jetonAvecReprise().then(function (j) {
				if (j.reseau === false || j.erreur) { planifier(DELAI_RENVOI); return; }
				if (j.session === false) { sessionOuverte = false; return; }
				if (j.utilisateur !== UTIL) { return; }
				sessionOuverte = true;
				return suite();
			});
		});
	}

	/* Le premier changement en attente, envoye ; puis le suivant. La file est relue a chaque tour :
	   l'identifiant reel d'une seance creee a ete reporte dans les changements qui la suivent. Un
	   changement traite quitte l'etat « attente » (envoye, a verifier ou refuse) : le tour avance toujours. */
	function suite() {
		return lister().then(function (liste) {
			var it = liste.filter(function (i) { return i.statut === 'attente'; })[0];
			if (!it) { evenement('fini', { restants: liste.length }); return; }
			return poster(it, true).then(function (r) {
				if (r.reseau) {
					if (essaisReseau[it.id]) { planifier(DELAI_RENVOI); return; }
					essaisReseau[it.id] = true;   // une reprise rapide, puis l'attente normale
					return pauseMs(1500).then(suite);
				}
				if (r.session === false) { sessionOuverte = false; return; }
				if (r.data && r.data.csrf_expire) {
					if (essaisJeton[it.id]) { planifier(DELAI_RENVOI); return; }   // une seule relance par changement
					essaisJeton[it.id] = true;
					return jeton().then(function (j) { if (j.csrf_token) { return suite(); } });
				}
				var st = r.status, d = r.data || {};
				var fait = st >= 200 && st < 300 && d.ok !== false;
				if (!fait && it.acquis404 && st === 404) { fait = true; }
				if (fait) {
					return supprimer(it.id)
						.then(function () { return appliquerProduit(it, d); })
						.then(function () { evenement('envoye', { item: it, reponse: d }); return suite(); });
				}
				if (st >= 500) {   // le serveur a un souci : on reessaie plus tard, sans perdre le changement
					it.essais = (it.essais || 0) + 1;
					if (it.essais >= ESSAIS_MAX) { it.statut = 'refuse'; it.message = 'Le serveur n\'a pas pu l\'enregistrer (erreur ' + st + ').'; }
					return maj(it).then(function () {
						if (it.statut === 'refuse') { evenement('refuse', { item: it }); return suite(); }
						planifier(DELAI_RENVOI);
					});
				}
				it.statut = (st === 409 || d.conflit) && !d.fige ? 'conflit' : 'refuse';   // « fige » : lecture seule, forcer n'y change rien
				it.message = d.message || ('Refuse par le serveur (' + st + ').');
				return maj(it).then(function () { return refuserDependants(it); })
					.then(function () { evenement('refuse', { item: it }); return suite(); });
			});
		});
	}

	/* Une creation refusee : les changements qui visaient l'objet cree ne peuvent plus partir, ils sont a verifier. */
	function refuserDependants(it) {
		if (!it.produit) { return Promise.resolve(); }
		var champ = it.produit.champ, prov = it.produit.provisoire;
		return lister().then(function (liste) {
			return Promise.all(liste.filter(function (x) {
				return x.statut === 'attente' && x.champs.some(function (p) { return p[0] === champ && p[1] === prov; });
			}).map(function (x) {
				x.statut = 'refuse';
				x.message = 'Ce changement visait un element qui n\'a pas pu etre cree.';
				return maj(x);
			}));
		});
	}

	/* Un identifiant provisoire (seance ouverte hors ligne) devient le reel dans les changements qui le citent. */
	function appliquerProduit(it, d) {
		if (!it.produit || d[it.produit.reponse] == null) { return Promise.resolve(); }
		var reel = String(d[it.produit.reponse]), prov = it.produit.provisoire, champ = it.produit.champ;
		return lister().then(function (liste) {
			return Promise.all(liste.filter(function (x) {
				return x.champs.some(function (p) { return p[0] === champ && p[1] === prov; });
			}).map(function (x) {
				x.champs = x.champs.map(function (p) { return p[0] === champ && p[1] === prov ? [champ, reel] : p; });
				return maj(x);
			}));
		}).then(function () {
			return lire('alias').then(function (a) {
				a = a || {};
				a[prov] = reel;
				return noter('alias', a);
			});
		}).then(function () { evenement('produit', { provisoire: prov, reel: reel }); });
	}
	function alias(prov) { return lire('alias').then(function (a) { return (a && a[prov]) || prov; }); }

	/* « Appliquer quand meme » : le changement part a l'heure actuelle et ecrase la valeur du serveur. */
	function forcer(item) {
		return poster(item, true, true).then(function (r) {
			if (r.reseau) { return { ok: false, message: 'Pas de reseau : reessayez plus tard.' }; }
			var fait = r.status >= 200 && r.status < 300 && r.data.ok !== false;
			if (!fait) { return { ok: false, message: (r.data && r.data.message) || 'Refuse par le serveur.' }; }
			return supprimer(item.id).then(function () { evenement('envoye', { item: item, reponse: r.data }); majUi(); return { ok: true }; });
		});
	}

	/* ------------------------------------------------ pages gardees (liste pour la page « hors connexion ») */
	/* Le nom d'une page dans la liste « disponible sans connexion » : son titre, l'annee et le semestre de l'adresse. */
	function titreDe(racine, chemin) {
		var h1 = racine.querySelector('h1');
		var t = (h1 ? h1.textContent : (racine.title || chemin)).replace(/\s+/g, ' ').trim();
		try {
			var p = new URL(chemin, location.href).searchParams;
			if (p.get('annee')) { t += ' — ' + p.get('annee'); }
			if (p.get('semestre')) { t += ' · S' + p.get('semestre'); }
		} catch (e) { /* adresse illisible : le titre seul */ }
		return t;
	}

	/* Sans argument : la page ouverte (si le module la garde hors ligne). Avec : une page prechargee. */
	function noterPage(adresse, titre) {
		var url = adresse;
		if (!url) {
			if (PAGES_HORS_LIGNE.indexOf(location.pathname.slice(BASE.length)) === -1) { return; }
			url = location.pathname + location.search;
			titre = titreDe(document, url);
		}
		titre = String(titre || url).replace(/\s+/g, ' ').trim().slice(0, 120);
		tx('pages', 'readwrite', function (s) {
			return s.put({ cle: UTIL + '|' + url, utilisateur: UTIL, url: url, titre: titre, le: Date.now() });
		}).catch(function () {});
	}

	/**
	 * Garde sur l'appareil des pages que le compte n'a pas encore ouvertes (les feuilles d'un module),
	 * pour qu'elles s'ouvrent sans reseau. Reseau present seulement, deux a la fois, au plus toutes les
	 * 20 min par module. options : cle (le module), titre(doc, chemin), suivantes(doc) -> adresses de plus
	 * (un niveau).
	 */
	function garder(adresses, o) {
		o = o || {};
		if (!enLigneSur || navigator.onLine === false) { return Promise.resolve(); }
		var cle = 'registrar-garder-' + (o.cle || '') + '-' + UTIL;
		try {
			if (Date.now() - parseInt(localStorage.getItem(cle) || '0', 10) < 20 * 60 * 1000) { return Promise.resolve(); }
			localStorage.setItem(cle, String(Date.now()));
		} catch (e) { /* stockage refuse : on garde a chaque ouverture */ }
		var file = (adresses || []).map(function (a) { return { a: a, niveau: 0 }; });
		var vus = {};
		function suivant() {
			var tache = file.shift();
			if (!tache) { return Promise.resolve(); }
			var u;
			try { u = new URL(tache.a, location.href); u.hash = ''; } catch (e) { return suivant(); }
			if (u.origin !== location.origin) { return suivant(); }
			var chemin = u.pathname + u.search;
			if (vus[chemin]) { return suivant(); }
			vus[chemin] = true;
			return fetch(chemin, { credentials: 'same-origin' }).then(function (r) {
				if (!r.ok || r.redirected) { throw new Error('page'); }
				return r.text();
			}).then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				noterPage(chemin, o.titre ? o.titre(doc, chemin) : titreDe(doc, chemin));
				if (tache.niveau === 0 && o.suivantes) {
					o.suivantes(doc).forEach(function (a) { file.push({ a: a, niveau: 1 }); });
				}
			}).catch(function () { /* cette page-la ne sera pas disponible hors ligne */ }).then(suivant);
		}
		return Promise.all([suivant(), suivant()]);
	}

	/* ------------------------------------------------ confidentialite */
	function messageSW(m) {
		if (!('serviceWorker' in navigator)) { return; }
		var envoyerMessage = function (w) { if (w) { try { w.postMessage(m); } catch (e) { /* worker arrete */ } } };
		if (navigator.serviceWorker.controller) { envoyerMessage(navigator.serviceWorker.controller); }
		else { navigator.serviceWorker.ready.then(function (reg) { envoyerMessage(reg.active); }).catch(function () {}); }
	}
	function purger() {
		return lister().then(function (l) {
			return Promise.all(l.map(function (i) { return supprimer(i.id); }));
		}).then(function () {
			return Promise.all([
				tx('pages', 'readwrite', function (s) { return s.clear(); }).catch(function () {}),
				tx('meta', 'readwrite', function (s) { return s.clear(); }).catch(function () {}),
			]);
		}).then(function () { messageSW({ type: 'purger' }); majUi(); });
	}
	/* Un autre compte sur le meme navigateur : les pages de l'ancien sont oubliees (sa file reste, a lui). */
	function nettoyerAutres() {
		lire('utilisateur').then(function (ancien) {
			if (ancien && ancien !== UTIL) { tx('pages', 'readwrite', function (s) { return s.clear(); }).catch(function () {}); }
			noter('utilisateur', UTIL);
		});
		messageSW({ type: 'utilisateur', id: UTIL });
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || e.defaultPrevented || e.button !== 0) { return; }
		var u;
		try { u = new URL(a.href, location.href); } catch (x) { return; }
		if (u.origin !== location.origin || !/\/logout$/.test(u.pathname)) { return; }
		e.preventDefault();
		var href = a.href;
		(enLigneSur && sessionOuverte ? rejouer() : Promise.resolve()).then(lister).then(function (l) {
			var n = l.length;
			var ok = !n || window.confirm(n + ' changement' + (n > 1 ? 's' : '') + ' fait' + (n > 1 ? 's' : '') + ' hors ligne ne ' + (n > 1 ? 'sont' : 'est') + ' pas encore envoye' + (n > 1 ? 's' : '')
				+ ' et ' + (n > 1 ? 'seront perdus' : 'sera perdu') + '. Se deconnecter quand meme ?');
			if (!ok) { return; }
			return purger().then(function () { location.assign(href); });
		});
	}, true);

	/* ------------------------------------------------ la pastille d'etat */
	var etatEl, panneauEl, annonceEl, finMessage = 0, messageTransitoire = '', avaitDesChangements = false;

	var CSS = '#hlEtat{position:fixed;left:14px;bottom:14px;z-index:9000;display:none;align-items:center;gap:8px;max-width:calc(100vw - 28px);padding:8px 14px;border-radius:999px;border:1px solid rgb(var(--c-line-strong,58 66 108));background:rgb(var(--c-surface,26 31 60));color:rgb(var(--c-text,236 238 246));font:600 13px/1.2 var(--font-sans,Inter,system-ui,sans-serif);box-shadow:0 6px 22px rgb(0 0 0 /.35);cursor:pointer}'
		+ '#hlEtat.hl-visible{display:inline-flex}#hlEtat:focus-visible{outline:none;box-shadow:0 0 0 3px rgb(var(--c-accent,90 111 218)/.5)}'
		+ '#hlEtat i{font-size:15px}#hlEtat.hl-hors{border-color:rgb(var(--c-warning,251 191 36)/.7)}#hlEtat.hl-hors i{color:rgb(var(--c-warning,251 191 36))}'
		+ '#hlEtat.hl-verifier{border-color:rgb(var(--c-danger,248 113 113)/.75)}#hlEtat.hl-verifier i{color:rgb(var(--c-danger,248 113 113))}'
		+ '#hlEtat.hl-ok{border-color:rgb(var(--c-success,52 211 153)/.7)}#hlEtat.hl-ok i{color:rgb(var(--c-success,52 211 153))}'
		+ '#hlEtat.hl-envoi i{animation:hltourne 1s linear infinite;display:inline-block}@keyframes hltourne{to{transform:rotate(360deg)}}'
		+ '#hlPanneau{position:fixed;left:14px;bottom:62px;z-index:9001;width:min(420px,calc(100vw - 28px));max-height:min(70vh,520px);overflow:auto;padding:14px;border-radius:14px;border:1px solid rgb(var(--c-line-strong,58 66 108));background:rgb(var(--c-surface,26 31 60));color:rgb(var(--c-text,236 238 246));font:13px/1.45 var(--font-sans,Inter,system-ui,sans-serif);box-shadow:0 16px 40px rgb(0 0 0 /.5)}'
		+ '#hlPanneau[hidden]{display:none}#hlPanneau h2{margin:0 0 4px;font-size:15px}#hlPanneau p{margin:0 0 10px;color:rgb(var(--c-text-2,179 185 214))}'
		+ '#hlPanneau ul{list-style:none;margin:0 0 10px;padding:0;display:flex;flex-direction:column;gap:6px}'
		+ '#hlPanneau li{padding:8px 10px;border-radius:10px;border:1px solid rgb(var(--c-line,40 47 86));background:rgb(var(--c-inset,15 19 39))}'
		+ '#hlPanneau li small{display:block;color:rgb(var(--c-text-3,136 144 181))}#hlPanneau li.hl-conflit,#hlPanneau li.hl-refuse{border-color:rgb(var(--c-danger,248 113 113)/.6)}'
		+ '#hlPanneau li .hl-msg{color:rgb(var(--c-danger,248 113 113));margin-top:3px}#hlPanneau .hl-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}'
		+ '#hlPanneau button{min-height:34px;padding:0 12px;border-radius:9px;border:1px solid rgb(var(--c-line-strong,58 66 108));background:rgb(var(--c-raised,38 45 82));color:inherit;font:600 12.5px var(--font-sans,Inter,system-ui,sans-serif);cursor:pointer}'
		+ '#hlPanneau button.hl-principal{background:rgb(var(--c-accent,90 111 218));border-color:rgb(var(--c-accent,90 111 218));color:#fff}'
		+ '#hlPanneau button:focus-visible{outline:none;box-shadow:0 0 0 3px rgb(var(--c-accent,90 111 218)/.5)}'
		+ '.hl-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
		+ '@media(max-width:1023px){#hlEtat{bottom:calc(14px + env(safe-area-inset-bottom,0px))}#hlPanneau{bottom:calc(62px + env(safe-area-inset-bottom,0px))}}'
		+ '@media(prefers-reduced-motion:reduce){#hlEtat.hl-envoi i{animation:none}}';

	function construireUi() {
		if (etatEl) { return; }
		var st = document.createElement('style');
		st.textContent = CSS;
		document.head.appendChild(st);
		etatEl = document.createElement('button');
		etatEl.type = 'button';
		etatEl.id = 'hlEtat';
		etatEl.setAttribute('aria-haspopup', 'dialog');
		etatEl.addEventListener('click', function () { basculerPanneau(); });
		panneauEl = document.createElement('div');
		panneauEl.id = 'hlPanneau';
		panneauEl.hidden = true;
		panneauEl.setAttribute('role', 'dialog');
		panneauEl.setAttribute('aria-label', 'Changements en attente d\'envoi');
		annonceEl = document.createElement('div');
		annonceEl.className = 'hl-sr';
		annonceEl.setAttribute('role', 'status');
		annonceEl.setAttribute('aria-live', 'polite');
		document.body.append(etatEl, panneauEl, annonceEl);
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panneauEl.hidden) { panneauEl.hidden = true; etatEl.focus(); } });
		document.addEventListener('click', function (e) { if (!panneauEl.hidden && !panneauEl.contains(e.target) && !etatEl.contains(e.target)) { panneauEl.hidden = true; } });
	}

	function heure(ms) { var d = new Date(ms); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
	function icone(nom) { return '<i class="bi ' + nom + '" aria-hidden="true"></i>'; }
	function echap(t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	function majUi() {
		if (!document.body) { return; }
		lister().then(function (liste) {
			construireUi();
			var attente = liste.filter(function (i) { return i.statut === 'attente'; }).length;
			var aVerifier = liste.length - attente;
			var horsLigne = !enLigneSur || navigator.onLine === false;
			var envoi = !!envoiEnCours && attente > 0;
			var texte = '', classe = '', ic = '';
			if (aVerifier > 0) { texte = aVerifier + (aVerifier > 1 ? ' changements à vérifier' : ' changement à vérifier'); classe = 'hl-verifier'; ic = 'bi-exclamation-triangle-fill'; }
			else if (attente > 0 && !sessionOuverte) { texte = 'Reconnectez-vous pour envoyer (' + attente + ')'; classe = 'hl-verifier'; ic = 'bi-person-lock'; }
			else if (horsLigne) { texte = 'Hors ligne' + (attente ? ' · ' + attente + ' en attente' : ''); classe = 'hl-hors'; ic = 'bi-wifi-off'; }
			else if (envoi) { texte = 'Envoi de ' + attente + (attente > 1 ? ' changements…' : ' changement…'); classe = 'hl-envoi'; ic = 'bi-arrow-repeat'; }
			else if (attente > 0) { texte = attente + (attente > 1 ? ' changements en attente' : ' changement en attente'); classe = 'hl-hors'; ic = 'bi-cloud-arrow-up'; }
			else if (messageTransitoire) { texte = messageTransitoire; classe = 'hl-ok'; ic = 'bi-check-circle-fill'; }
			if (liste.length) { avaitDesChangements = true; }
			etatEl.className = texte ? 'hl-visible ' + classe : '';
			etatEl.innerHTML = texte ? icone(ic) + '<span>' + echap(texte) + '</span>' : '';
			document.documentElement.classList.toggle('hl-hors-ligne', horsLigne);
			if (texte && annonceEl.textContent !== texte) { annonceEl.textContent = texte; }
			if (!panneauEl.hidden) { dessinerPanneau(liste); }
		});
	}

	function basculerPanneau() {
		lister().then(function (liste) {
			panneauEl.hidden = !panneauEl.hidden;
			if (!panneauEl.hidden) { dessinerPanneau(liste); }
		});
	}

	function dessinerPanneau(liste) {
		var h = '<h2>Changements faits hors ligne</h2>';
		if (!liste.length) { h += '<p>Rien en attente : tout est envoyé.</p>'; }
		else {
			h += '<p>' + (!enLigneSur || navigator.onLine === false ? 'Ils partiront dès que le réseau revient.' : (sessionOuverte ? 'Ils partent automatiquement.' : 'Votre session a expiré : reconnectez-vous, ils partiront ensuite.')) + '</p><ul>';
			liste.forEach(function (i) {
				h += '<li class="hl-' + i.statut + '"><b>' + echap(i.libelle || 'Changement') + '</b><small>Fait à ' + heure(i.creeLe) + (i.statut === 'attente' ? ' · en attente' : '') + '</small>';
				if (i.message) { h += '<div class="hl-msg">' + echap(i.message) + '</div>'; }
				if (i.statut !== 'attente') {
					h += '<div class="hl-actions">' + (i.statut === 'conflit' ? '<button type="button" data-hl="forcer" data-id="' + i.id + '">Appliquer quand même</button>' : '')
						+ '<button type="button" data-hl="ignorer" data-id="' + i.id + '">Ignorer</button></div>';
				}
				h += '</li>';
			});
			h += '</ul>';
		}
		h += '<div class="hl-actions"><button type="button" class="hl-principal" data-hl="envoyer">Envoyer maintenant</button><button type="button" data-hl="fermer">Fermer</button></div>';
		panneauEl.innerHTML = h;
		panneauEl.onclick = function (e) {
			var b = e.target.closest('button[data-hl]');
			if (!b) { return; }
			var act = b.getAttribute('data-hl'), id = parseInt(b.getAttribute('data-id'), 10);
			if (act === 'fermer') { panneauEl.hidden = true; etatEl.focus(); }
			else if (act === 'envoyer') { jeton().then(function () { rejouer(); majUi(); }); }
			else if (act === 'ignorer') { supprimer(id).then(majUi); }
			else if (act === 'forcer') { lister().then(function (l) { var it = l.filter(function (x) { return x.id === id; })[0]; if (it) { forcer(it).then(function (r) { if (!r.ok) { it.message = r.message; maj(it).then(majUi); } }); } }); }
		};
	}

	/* ------------------------------------------------ branchements */
	window.addEventListener('online', function () { enLigneSur = true; evenement('etat', { enLigne: true }); majUi(); rejouer(); });
	window.addEventListener('offline', function () { enLigneSur = false; evenement('etat', { enLigne: false }); majUi(); });
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { rejouer(); majUi(); } });
	setInterval(function () { lister().then(function (l) { if (l.some(function (i) { return i.statut === 'attente' && !envoiEnCours; })) { rejouer(); } }); }, DELAI_RENVOI);

	document.addEventListener('horsligne', function (e) {
		var t = e.detail && e.detail.type;
		if (t === 'fini' && avaitDesChangements) {
			lister().then(function (l) {
				if (l.length) { return; }
				messageTransitoire = 'Tout est envoyé';
				majUi();
				clearTimeout(finMessage);
				finMessage = setTimeout(function () { messageTransitoire = ''; avaitDesChangements = false; majUi(); }, 3500);
			});
		} else if (t === 'etat') { majUi(); }
	});

	window.HorsLigne = {
		actif: true, utilisateur: UTIL, envoyer: envoyer, enAttente: enAttente, enLigne: function () { return enLigneSur && navigator.onLine !== false; },
		retirer: retirer, alias: alias, rejouer: rejouer, noterPage: noterPage, purger: purger, maintenant: maintenant, lister: lister,
		session: function () { return sessionOuverte; }, garder: garder,
	};

	function demarrer() {
		lire('decalage').then(function (d) { if (typeof d === 'number') { decalage = d; } });
		nettoyerAutres();
		noterPage();
		majUi();
		/* Un changement resté dans la file d'une visite precedente part des l'ouverture. */
		lister().then(function (l) { if (l.some(function (i) { return i.statut === 'attente'; })) { jeton().then(function () { rejouer(); }); } });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', demarrer); } else { demarrer(); }
})();
