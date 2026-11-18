/* Application installable (PWA) — charge par init/pwa.php sur chaque page.
 *
 * 1. Enregistre le service worker (/sw.js) : page « hors connexion » et
 *    feuilles de style gardees sur le telephone.
 * 2. Le bouton « Installer l'application » : tout element portant
 *    [data-pwa-installer] s'affiche quand l'installation est possible
 *    (Android, ordinateur : la proposition du navigateur ; iPhone : la
 *    marche a suivre, Safari n'ayant pas de bouton a declencher) et
 *    disparait une fois l'application installee.
 *
 * La classe html.pwa-app est posee quand la page tourne dans l'application
 * installee (sans barre d'adresse).
 */
(function () {
	'use strict';

	var script = document.currentScript;
	var BASE = (script && script.getAttribute('data-base')) || (window.APP_BASE || '');
	var proposition = null;

	var nav = window.navigator;
	var enApp = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || nav.standalone === true;
	var iOS = /iPad|iPhone|iPod/.test(nav.userAgent) || (nav.platform === 'MacIntel' && nav.maxTouchPoints > 1);

	if (enApp) document.documentElement.classList.add('pwa-app');

	/* ------------------------------------------------ service worker */

	if ('serviceWorker' in nav && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
		window.addEventListener('load', function () {
			nav.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).catch(function () {});
		});
		/* Notification touchee (/sw.js) : la messagerie de la page ouvre la
		   discussion ; une page sans messagerie va a la page Messagerie. */
		nav.serviceWorker.addEventListener('message', function (e) {
			var d = e.data || {};
			if (d.type !== 'messagerie:ouvrir') return;
			if (window.Messagerie && window.Messagerie.ouvrir && d.conversation) window.Messagerie.ouvrir(d.conversation);
			else if (d.url) location.assign(d.url);
		});
	}

	/* ------------------------------------------------ installation */

	function installable() {
		if (enApp) return false;
		return !!proposition || iOS;
	}

	function majBoutons() {
		var montrer = installable();
		document.querySelectorAll('[data-pwa-installer]').forEach(function (b) {
			b.hidden = !montrer;
		});
	}

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault(); // pas de bandeau du navigateur : nos boutons le proposent
		proposition = e;
		majBoutons();
	});

	window.addEventListener('appinstalled', function () {
		proposition = null;
		majBoutons();
	});

	function fermerAide(fond) {
		fond.remove();
		document.removeEventListener('keydown', fond._echap);
	}

	/* Safari ne propose pas d'installer depuis la page : on montre ou
	   toucher. Les couleurs suivent les jetons du theme quand la page les a. */
	function aideIOS() {
		var fond = document.createElement('div');
		fond.id = 'pwaAide';
		fond.setAttribute('role', 'dialog');
		fond.setAttribute('aria-modal', 'true');
		fond.setAttribute('aria-labelledby', 'pwaAideTitre');
		fond.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:flex;align-items:flex-end;justify-content:center;'
			+ 'background:rgb(4 6 18 / .6);padding:16px;padding-bottom:calc(16px + env(safe-area-inset-bottom,0px));';
		fond.innerHTML =
			'<div style="width:100%;max-width:420px;border-radius:16px;padding:20px 20px 16px;'
			+ 'background:rgb(var(--c-surface,26 31 60));color:rgb(var(--c-text,236 238 246));'
			+ 'font:15px/1.5 var(--font-sans,system-ui,-apple-system,sans-serif);box-shadow:0 12px 32px -8px rgb(4 6 18 / .6);">'
			+ '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">'
			+ '<img src="' + BASE + '/src/pwa/apple-touch-icon.png" alt="" width="44" height="44" style="border-radius:10px;">'
			+ '<strong id="pwaAideTitre" style="font-size:17px;">Installer Infinit Registrar</strong></div>'
			+ '<ol style="margin:0 0 16px;padding-left:20px;">'
			+ '<li>Touchez <b>Partager</b> <svg width="16" height="18" viewBox="0 0 16 20" aria-label="(carré et flèche vers le haut)" style="vertical-align:-3px;">'
			+ '<path d="M8 1v12M4 5l4-4 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
			+ '<path d="M5 8H2v11h12V8h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>'
			+ ' (en bas dans Safari, en haut dans Chrome).</li>'
			+ '<li>Choisissez <b>Sur l’écran d’accueil</b>.</li>'
			+ '<li>Touchez <b>Ajouter</b>.</li></ol>'
			+ '<button type="button" style="width:100%;min-height:44px;border:0;border-radius:10px;font:inherit;font-weight:600;'
			+ 'background:rgb(var(--c-accent,90 111 218));color:#fff;cursor:pointer;">J’ai compris</button></div>';
		fond.addEventListener('click', function (e) {
			if (e.target === fond || e.target.tagName === 'BUTTON') fermerAide(fond);
		});
		fond._echap = function (e) { if (e.key === 'Escape') fermerAide(fond); };
		document.addEventListener('keydown', fond._echap);
		document.body.appendChild(fond);
		fond.querySelector('button').focus();
	}

	function installer() {
		if (proposition) {
			var p = proposition;
			proposition = null; // une proposition ne sert qu'une fois
			p.prompt();
			p.userChoice.then(majBoutons, majBoutons);
			return;
		}
		if (iOS && !enApp) aideIOS();
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-pwa-installer]');
		if (!b) return;
		e.preventDefault();
		installer();
	});

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', majBoutons);
	else majBoutons();

	window.PWA = { base: BASE, enApp: enApp, iOS: iOS, installable: installable, installer: installer };
})();
