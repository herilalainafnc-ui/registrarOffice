/* =============================================================================
   Journal d'activité — src/settings.logs.php

   Toutes les valeurs venues du journal sont insérées par textContent : une
   URL, un nom ou une donnée envoyée ne peut jamais devenir du HTML.
   Les filtres sont recopiés dans l'adresse de la page, pour pouvoir partager
   ou recharger une recherche.

   En direct par défaut (demande du 08/10/2026) : toutes les 5 secondes, les
   nouvelles actions et les chiffres du jour arrivent seuls, sans bouton. Plus
   bas dans la liste, la lecture ne bouge pas : une bulle annonce les
   nouveautés. Onglet caché : une interrogation toutes les 30 secondes, et le
   nombre de nouveautés dans le titre. Animations coupées si le système le
   demande (prefers-reduced-motion).
   ========================================================================== */
(function () {
	'use strict';

	const CFG = window.JOURNAL || { types: {}, icones: {} };
	const BASE = typeof APP_BASE !== 'undefined' ? APP_BASE : '';
	const CHAMPS = ['q', 'user', 'du', 'au', 'table', 'ip'];

	const $ = (s) => document.querySelector(s);
	const form = $('#jlFiltres'), liste = $('#jlListe'), plus = $('#jlPlus'), resume = $('#jlResume');
	const tiroir = $('#jlTiroir'), voile = $('#jlVoile'), corps = $('#jlDetail');
	const btnTout = $('#jlTout'), btnDirect = $('#jlDirect'), nouveau = $('#jlNouveau'), nouveauBouton = $('#jlNouveauBouton');
	const defile = document.getElementById('jlDefilement'), page = document.querySelector('.jl-page');
	if (!form || !liste) return;

	const REDUIT = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
	const TITRE = document.title;
	let suivant = null, premierId = 0, affiches = 0, dernierJour = null;
	let enCours = null, ligneOrigine = null, generation = 0;

	/* ---------------------------------------------------------------- outils */

	function el(tag, attrs, ...enfants) {
		const n = document.createElement(tag);
		if (attrs) {
			for (const [k, v] of Object.entries(attrs)) {
				if (v === null || v === undefined || v === false) continue;
				if (k === 'class') n.className = v;
				else n.setAttribute(k, v === true ? '' : v);
			}
		}
		for (const e of enfants.flat()) {
			if (e === null || e === undefined || e === false) continue;
			n.append(e instanceof Node ? e : document.createTextNode(String(e)));
		}
		return n;
	}
	const icone = (classe) => el('i', { class: 'bi ' + classe, 'aria-hidden': 'true' });
	const initiales = (nom) => (nom || '?').trim().split(/\s+/).slice(0, 2).map((m) => m[0]).join('').toUpperCase();
	const pluriel = (n, s, p) => n + ' ' + (n > 1 ? p : s);

	function formaterJour(jour) {
		const d = new Date(jour + 'T00:00:00');
		const auj = new Date(); auj.setHours(0, 0, 0, 0);
		const ecart = Math.round((auj - d) / 86400000);
		const texte = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
		return (ecart === 0 ? 'Aujourd\'hui — ' : ecart === 1 ? 'Hier — ' : '') + texte;
	}
	function formaterDate(date) {
		const [j, h] = String(date).split(' ');
		return new Date(j + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
			+ ' à ' + (h || '').replace('.', ',');
	}
	function valeur(v) {
		if (v === null || v === undefined || v === '') return el('span', { class: 'jl-vide-valeur' }, v === '' ? 'vide' : 'NULL');
		if (typeof v === 'object') return el('pre', null, JSON.stringify(v, null, 2));
		return String(v);
	}

	/* ------------------------------------------------------------- filtres */

	function parametres() {
		const p = new URLSearchParams();
		for (const k of CHAMPS) {
			const v = (form.elements[k].value || '').trim();
			if (v !== '') p.set(k, v);
		}
		const types = [...form.querySelectorAll('[data-type][aria-pressed="true"]')].map((b) => b.dataset.type);
		if (types.length) p.set('types', types.join(','));
		return p;
	}
	function lireAdresse() {
		const p = new URLSearchParams(location.search);
		for (const k of CHAMPS) if (p.has(k)) form.elements[k].value = p.get(k);
		const types = (p.get('types') || '').split(',').filter(Boolean);
		form.querySelectorAll('[data-type]').forEach((b) => b.setAttribute('aria-pressed', types.includes(b.dataset.type) ? 'true' : 'false'));
		majTout();
	}
	function majTout() {
		const aucun = !form.querySelector('[data-type][aria-pressed="true"]');
		btnTout.setAttribute('aria-pressed', aucun ? 'true' : 'false');
	}

	let delaiSaisie = null;
	form.addEventListener('input', (e) => {
		if (e.target.name === 'q' || e.target.name === 'ip') {
			clearTimeout(delaiSaisie);
			delaiSaisie = setTimeout(() => charger(true), 350);
		}
	});
	form.addEventListener('change', (e) => {
		if (['user', 'du', 'au', 'table'].includes(e.target.name)) charger(true);
	});
	form.addEventListener('click', (e) => {
		const puce = e.target.closest('.jl-puce');
		if (!puce) return;
		if (puce === btnTout) {
			form.querySelectorAll('[data-type]').forEach((b) => b.setAttribute('aria-pressed', 'false'));
		} else {
			puce.setAttribute('aria-pressed', puce.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
		}
		majTout();
		charger(true);
	});
	$('#jlEffacer').addEventListener('click', () => {
		CHAMPS.forEach((k) => { form.elements[k].value = ''; });
		form.querySelectorAll('[data-type]').forEach((b) => b.setAttribute('aria-pressed', 'false'));
		majTout();
		charger(true);
	});

	/* ---------------------------------------------------------------- liste */

	async function lireJson(url) {
		const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
		let d = null;
		try { d = await r.json(); } catch (e) { /* réponse non JSON */ }
		if (r.status === 401) throw new Error('Votre session a expiré. Rechargez la page pour vous reconnecter.');
		if (!r.ok || !d || !d.ok) throw new Error((d && d.message) || 'Le journal n\'a pas pu être chargé (erreur ' + r.status + ').');
		return d;
	}

	function squelettes() {
		const f = document.createDocumentFragment();
		for (let i = 0; i < 6; i++) f.append(el('div', { class: 'jl-squelette', 'aria-hidden': 'true' }));
		return f;
	}

	const enteteJour = (jour) => el('div', { class: 'jl-jour', 'data-jour': jour }, formaterJour(jour));

	/* Pour la suite de la liste : l'en-tête du jour quand le jour change. */
	function ligne(it) {
		const fragments = [];
		if (it.jour !== dernierJour) {
			dernierJour = it.jour;
			fragments.push(enteteJour(it.jour));
		}
		fragments.push(ligneAction(it));
		return fragments;
	}

	function ligneAction(it) {
		const u = it.utilisateur;
		const technique = [it.methode, it.chemin, it.statut, it.duree !== null ? it.duree + ' ms' : null].filter((x) => x !== null && x !== '').join(' · ');
		const compte = it.nb_modifs > 0
			? el('span', { class: 'jl-compte' }, pluriel(it.nb_modifs, 'écriture', 'écritures'))
			: (it.erreur ? el('span', { class: 'jl-compte jl-compte--erreur' }, 'Erreur') : el('span'));

		const b = el('button', {
			type: 'button',
			class: 'jl-ligne jl-ligne--' + it.type + (it.erreur ? ' jl-ligne--erreur' : ''),
			'data-id': it.id,
			'aria-label': it.heure + ', ' + it.type_libelle + ', ' + (u ? (u.nom || u.pseudo) : 'visiteur non connecté') + ', ' + it.libelle,
		},
			el('span', { class: 'jl-heure' }, it.heure),
			el('span', { class: 'jl-type jl-type--' + it.type }, icone(CFG.icones[it.type] || 'bi-dot'), it.type_libelle),
			el('span', { class: 'jl-qui' },
				el('span', { class: 'jl-avatar' + (u ? '' : ' jl-avatar--anonyme'), 'aria-hidden': 'true' }, u ? initiales(u.nom || u.pseudo) : '?'),
				el('span', { class: 'jl-empile' },
					el('b', null, u ? (u.nom || u.pseudo) : 'Visiteur non connecté'),
					el('small', null, u ? [u.role, u.pseudo].filter(Boolean).join(' · ') : 'aucune session'))),
			el('span', { class: 'jl-empile jl-quoi' },
				el('b', null, it.libelle),
				el('small', null, technique)),
			el('span', { class: 'jl-empile jl-ligne__meta' },
				el('small', null, it.ip || '—'),
				el('small', null, it.appareil || '')),
			compte);
		b.title = 'Voir le détail de cette action';
		return b;
	}

	function etat(message, detail, erreur) {
		return el('div', { class: 'jl-vide' + (erreur ? ' jl-vide--erreur' : '') },
			icone(erreur ? 'bi-exclamation-octagon' : 'bi-journal-x'),
			el('p', null, el('b', null, message)),
			detail ? el('p', null, detail) : null);
	}

	/* Les chiffres montent vers leur nouvelle valeur ; une hausse fait sauter la carte. */
	function animerNombre(n, vers) {
		const de = n.dataset.valeur === undefined ? 0 : Number(n.dataset.valeur);
		n.dataset.valeur = String(vers);
		if (de === vers || REDUIT.matches) { n.textContent = vers.toLocaleString('fr-FR'); return; }
		const t0 = performance.now(), duree = 750;
		const pas = (t) => {
			const k = Math.min(1, (t - t0) / duree), e = 1 - Math.pow(1 - k, 3);
			n.textContent = Math.round(de + (vers - de) * e).toLocaleString('fr-FR');
			if (k < 1) requestAnimationFrame(pas);
		};
		requestAnimationFrame(pas);
		if (vers > de && de > 0) {
			const carte = n.closest('.jl-stat');
			carte.classList.remove('jl-stat--hausse');
			void carte.offsetWidth;
			carte.classList.add('jl-stat--hausse');
		}
	}
	function afficherStats(s) {
		if (!s) return;
		for (const cle of ['actions', 'utilisateurs', 'connexions', 'echecs', 'modifications']) {
			const n = document.querySelector('[data-stat="' + cle + '"]');
			if (n) animerNombre(n, Number(s[cle] ?? 0));
		}
	}

	function majResume() {
		resume.textContent = affiches
			? pluriel(affiches, 'action affichée', 'actions affichées') + (suivant ? ', les plus récentes d\'abord' : '')
			: '';
	}

	async function charger(depuisLeDebut, options) {
		const silencieux = !!(options && options.silencieux);
		if (enCours) enCours.abandon = true;
		const jeton = { abandon: false };
		enCours = jeton;

		const p = parametres();
		if (depuisLeDebut) {
			generation++;
			/* Le hash reste : « #didacticiel=journal » lance la visite apres ce premier chargement */
			history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
			if (!silencieux) {
				liste.replaceChildren(squelettes());
				liste.setAttribute('aria-busy', 'true');
			}
			cacherBulle();
			majCartes();
		} else if (suivant) {
			p.set('avant', suivant);
		}
		plus.disabled = true;

		try {
			const d = await lireJson(BASE + '/app/audit.liste.php?' + p);
			if (jeton.abandon) return;
			if (depuisLeDebut) {
				liste.replaceChildren();
				dernierJour = null;
				affiches = 0;
				premierId = d.items.length ? d.items[0].id : 0;
				afficherStats(d.stats);
				derniereMaj = Date.now();
				majEtat();
			}
			const avantAjout = liste.querySelectorAll('.jl-ligne').length;
			d.items.forEach((it) => liste.append(...ligne(it)));
			/* Les premières lignes arrivent en cascade, la suite aussi quand elle se charge. */
			if (!REDUIT.matches) {
				const cibles = depuisLeDebut
					? [...liste.querySelectorAll('.jl-ligne, .jl-jour')].slice(0, 16)
					: [...liste.querySelectorAll('.jl-ligne')].slice(avantAjout, avantAjout + 12);
				cibles.forEach((n, i) => { n.style.setProperty('--i', String(i)); n.classList.add('jl-apparait'); });
			}
			affiches += d.items.length;
			suivant = d.suivant;
			plus.hidden = !suivant;

			if (depuisLeDebut && !d.items.length) {
				const filtre = p.toString() !== '';
				liste.append(d.indisponible
					? etat('Le journal démarre à la première action enregistrée.', 'Rechargez la page dans un instant.')
					: etat(filtre ? 'Aucune action ne correspond à ces filtres.' : 'Aucune action enregistrée pour l\'instant.',
						filtre ? 'Élargissez la période ou effacez les filtres.' : null));
			}
			majResume();
		} catch (e) {
			if (jeton.abandon) return;
			if (depuisLeDebut) liste.replaceChildren(etat('Le journal n\'a pas pu être chargé.', e.message, true));
			else if (window.Toast) Toast.show(e.message, 'error');
		} finally {
			if (!jeton.abandon) {
				liste.removeAttribute('aria-busy');
				plus.disabled = false;
				enCours = null;
				if (depuisLeDebut) planifier();
			}
		}
	}

	plus.addEventListener('click', () => charger(false));

	/* Défilement infini doux : charge la suite quand le bouton devient visible. */
	if ('IntersectionObserver' in window) {
		new IntersectionObserver((entrees) => {
			if (entrees.some((e) => e.isIntersecting) && !plus.hidden && !enCours) charger(false);
		}, { root: document.getElementById('jlDefilement'), rootMargin: '300px' }).observe(plus);
	}

	/* --------------------------------------------------------------- direct */

	const RYTHME = 5000, RYTHME_CACHE = 30000;
	let direct = true, minuterie = null, derniereMaj = 0, echecs = 0, horsVue = 0, pendantAbsence = 0;
	const directTitre = $('#jlDirectTitre'), directMaj = $('#jlDirectMaj'), directIcone = $('#jlDirectIcone');

	function depuisQuand() {
		if (!derniereMaj) return 'connexion…';
		const s = Math.max(0, Math.round((Date.now() - derniereMaj) / 1000));
		return s < 5 ? 'à jour à l\'instant' : s < 60 ? 'à jour il y a ' + s + ' s' : 'à jour il y a ' + Math.floor(s / 60) + ' min';
	}
	/* direct, pause, erreur (réseau), session (déconnecté) */
	function majEtat(etat) {
		etat = etat || (direct ? 'direct' : 'pause');
		btnDirect.dataset.etat = etat;
		btnDirect.setAttribute('aria-pressed', etat === 'pause' ? 'false' : 'true');
		const textes = {
			direct: ['En direct', depuisQuand(), 'bi-pause-fill', 'Mise à jour automatique : cliquez pour la mettre en pause'],
			pause: ['En pause', 'cliquez pour reprendre', 'bi-play-fill', 'Reprendre la mise à jour automatique'],
			erreur: ['Connexion perdue', 'nouvel essai automatique…', 'bi-arrow-repeat', 'Le serveur ne répond pas : nouvel essai automatique (cliquez pour réessayer tout de suite)'],
			session: ['Session expirée', 'cliquez pour recharger', 'bi-arrow-clockwise', 'Rechargez la page pour vous reconnecter'],
		}[etat];
		directTitre.textContent = textes[0];
		directMaj.textContent = textes[1];
		directIcone.className = 'bi ' + textes[2] + ' jl-direct__icone';
		btnDirect.title = textes[3];
	}
	setInterval(() => { if (btnDirect.dataset.etat === 'direct') directMaj.textContent = depuisQuand(); }, 1000);

	function planifier(delai) {
		clearTimeout(minuterie);
		if (!direct || btnDirect.dataset.etat === 'session') return;
		minuterie = setTimeout(sonder, delai ?? (document.hidden ? RYTHME_CACHE : RYTHME));
	}

	async function sonder() {
		if (!direct) return;
		if (enCours) { planifier(1500); return; }   // un chargement complet est en cours
		const gen = generation;
		const p = parametres();
		p.set('stats', '1');
		if (premierId) p.set('apres', String(premierId));
		try {
			const d = await lireJson(BASE + '/app/audit.liste.php?' + p);
			if (gen !== generation || !direct) return;   // filtres changés ou pause entre-temps
			echecs = 0;
			afficherStats(d.stats);
			if (!premierId || d.items.length >= 200) {
				/* Liste vide jusqu'ici, ou trop de nouveautés : on la redessine. */
				if (d.items.length) { await charger(true, { silencieux: true }); return; }
			} else if (d.items.length) {
				inserer(d.items);
			}
			derniereMaj = Date.now();
			majEtat();
		} catch (e) {
			if (gen !== generation) return;
			if (/session a expiré/i.test(e.message)) { majEtat('session'); return; }
			echecs++;
			majEtat('erreur');
		}
		planifier(echecs ? Math.min(60000, RYTHME * Math.pow(2, echecs)) : undefined);
	}

	/* Les nouvelles actions en tête : les lignes en place glissent vers le bas
	   (FLIP), les nouvelles entrent et s'éclairent de la couleur de leur type.
	   Plus bas dans la liste, la position de lecture est gardée. */
	/* Ce qui défile : le cadre de la liste sur ordinateur ; sur téléphone,
	   src/css/responsive.css libère les hauteurs et c'est la page entière. */
	const defilant = () => (defile.scrollHeight > defile.clientHeight + 1 ? defile : (document.scrollingElement || document.documentElement));

	function inserer(items) {
		premierId = items[0].id;
		const cadre = defilant();
		const enHaut = cadre.scrollTop < 120;
		const hauteurAvant = cadre.scrollHeight;
		const anime = enHaut && !REDUIT.matches;
		const anciennes = anime ? [...liste.querySelectorAll('.jl-ligne, .jl-jour')].slice(0, 18) : [];
		const positions = anciennes.map((n) => n.getBoundingClientRect().top);

		const enTete = liste.querySelector('.jl-jour');
		const frag = [];
		let jour = null;
		items.forEach((it) => {
			if (it.jour !== jour) { jour = it.jour; frag.push(enteteJour(jour)); }
			const b = ligneAction(it);
			b.classList.add('jl-ligne--neuve');
			frag.push(b);
		});
		/* Le jour le plus ancien des nouveautés est celui du haut de la liste : son en-tête remonte. */
		if (enTete && enTete.dataset.jour === jour) enTete.remove();
		const vide = liste.querySelector(':scope > .jl-vide');
		if (vide) vide.remove();
		liste.prepend(...frag);
		affiches += items.length;
		majResume();

		if (anime) {
			anciennes.forEach((n, k) => {
				if (!n.isConnected) return;
				const dy = positions[k] - n.getBoundingClientRect().top;
				if (dy) n.animate([{ transform: 'translateY(' + dy + 'px)' }, { transform: 'none' }], { duration: 460, easing: 'cubic-bezier(.2,.8,.2,1)' });
			});
		} else if (!enHaut) {
			cadre.scrollTop += cadre.scrollHeight - hauteurAvant;   // la lecture ne bouge pas
			horsVue += items.length;
			montrerBulle();
		}
		if (document.hidden) { pendantAbsence += items.length; document.title = '(' + pendantAbsence + ') ' + TITRE; }
		setTimeout(() => frag.forEach((n) => n.classList.remove('jl-ligne--neuve')), 3400);
	}

	function montrerBulle() {
		nouveauBouton.replaceChildren(icone('bi-arrow-up'), ' ' + pluriel(horsVue, 'nouvelle action', 'nouvelles actions'));
		nouveau.hidden = false;
	}
	function cacherBulle() { horsVue = 0; nouveau.hidden = true; }
	nouveauBouton.addEventListener('click', () => {
		defilant().scrollTo({ top: 0, behavior: REDUIT.matches ? 'auto' : 'smooth' });
		cacherBulle();
	});
	const siRemonte = () => { if (!nouveau.hidden && defilant().scrollTop < 120) cacherBulle(); };
	defile.addEventListener('scroll', siRemonte, { passive: true });
	window.addEventListener('scroll', siRemonte, { passive: true });

	btnDirect.addEventListener('click', () => {
		const etat = btnDirect.dataset.etat;
		if (etat === 'session') { location.reload(); return; }
		direct = etat === 'erreur' ? true : !direct;   // en erreur, un clic réessaie tout de suite
		majEtat();
		if (direct) { echecs = 0; clearTimeout(minuterie); sonder(); } else { clearTimeout(minuterie); }
	});
	document.addEventListener('visibilitychange', () => {
		if (!document.hidden) {
			pendantAbsence = 0;
			document.title = TITRE;
			if (direct && btnDirect.dataset.etat !== 'session') { clearTimeout(minuterie); sonder(); }
		}
	});

	/* La bulle et les en-têtes de jour se calent sous la barre des filtres, qui reste en haut. */
	if ('ResizeObserver' in window && page) {
		new ResizeObserver(() => page.style.setProperty('--jl-filtres-h', form.offsetHeight + 'px')).observe(form);
	}

	/* ----------------------------------------------------- cartes-filtres */

	const aujourdhui = () => {
		const d = new Date();
		return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
	};
	function majCartes() {
		document.querySelectorAll('.jl-stat[data-filtre]').forEach((c) => {
			const f = c.dataset.filtre;
			const puce = form.querySelector('[data-type="' + f + '"]');
			const actif = f === 'aujourdhui'
				? form.elements.du.value === aujourdhui() && form.elements.au.value === aujourdhui()
				: !!puce && puce.getAttribute('aria-pressed') === 'true';
			c.setAttribute('aria-pressed', actif ? 'true' : 'false');
		});
	}
	document.getElementById('jlStats').addEventListener('click', (e) => {
		const c = e.target.closest('.jl-stat[data-filtre]');
		if (!c) return;
		const f = c.dataset.filtre;
		if (f === 'aujourdhui') {
			const deja = c.getAttribute('aria-pressed') === 'true';
			form.elements.du.value = deja ? '' : aujourdhui();
			form.elements.au.value = deja ? '' : aujourdhui();
			charger(true);
		} else {
			const puce = form.querySelector('[data-type="' + f + '"]');
			if (puce) puce.click();   // même effet qu'un clic sur la puce du type
		}
	});

	/* --------------------------------------------------------------- détail */

	const OPERATIONS = {
		INSERT: 'Ajout', UPDATE: 'Modification', DELETE: 'Suppression', REPLACE: 'Remplacement',
		TRUNCATE: 'Table vidée', ALTER: 'Structure modifiée', CREATE: 'Création', DROP: 'Suppression de table', RENAME: 'Renommage',
	};
	const TRANSACTIONS = {
		validee: 'Transaction validée',
		annulee: 'Transaction annulée — rien n\'a été enregistré',
		inachevee: 'Transaction interrompue — rien n\'a été enregistré',
	};

	function section(titre, ...contenu) {
		return el('section', { class: 'jl-section' }, el('h3', null, titre), ...contenu);
	}
	function paires(liste) {
		const dl = el('dl', { class: 'jl-paires' });
		for (const [nom, v] of liste) {
			if (v === undefined) continue;
			dl.append(el('dt', null, nom), el('dd', null, v instanceof Node ? v : valeur(v)));
		}
		return dl;
	}
	function tableValeurs(objet, entete) {
		const t = el('table', { class: 'jl-diff' }, el('thead', null, el('tr', null, el('th', null, 'Champ'), el('th', null, entete))));
		const tb = el('tbody');
		for (const [k, v] of Object.entries(objet || {})) tb.append(el('tr', null, el('td', null, k), el('td', null, valeur(v))));
		t.append(tb);
		return el('div', { class: 'jl-defile' }, t);
	}
	function blocLigne(l) {
		const cle = l.cle ? Object.entries(l.cle).map(([k, v]) => k + ' = ' + v).join(', ') : null;
		const bloc = el('div', { class: 'jl-diff-bloc' });
		if (cle) bloc.append(el('p', { class: 'jl-diff-cle' }, 'Ligne ', el('code', null, cle)));
		if (l.avant && l.apres) {
			const t = el('table', { class: 'jl-diff' },
				el('thead', null, el('tr', null, el('th', null, 'Champ'), el('th', null, 'Avant'), el('th', null, 'Après'))));
			const tb = el('tbody');
			for (const k of Object.keys(l.apres)) {
				tb.append(el('tr', null, el('td', null, k),
					el('td', null, el('del', null, valeur(l.avant[k]))),
					el('td', null, el('ins', null, valeur(l.apres[k])))));
			}
			t.append(tb);
			bloc.append(el('div', { class: 'jl-defile' }, t));
		} else if (l.apres) {
			bloc.append(tableValeurs(l.apres, 'Valeur enregistrée'));
		} else if (l.avant) {
			bloc.append(tableValeurs(l.avant, 'Valeur supprimée'));
		}
		return bloc;
	}
	function carteModification(m) {
		const c = m.changements || {};
		const entete = el('div', { class: 'jl-modif__entete' },
			el('span', { class: 'jl-op jl-op--' + m.operation }, OPERATIONS[m.operation] || m.operation),
			el('b', null, m.table_libelle || m.table || 'Base de données'),
			m.table && m.table_libelle !== m.table ? el('code', { class: 'jl-etiquette' }, m.table) : null,
			m.lignes !== null ? el('span', { class: 'jl-etiquette' }, pluriel(m.lignes, 'ligne', 'lignes')) : null,
			TRANSACTIONS[m.transaction] ? el('span', { class: 'jl-etiquette jl-etiquette--' + m.transaction }, TRANSACTIONS[m.transaction]) : null);

		const contenu = el('div', { class: 'jl-modif__corps' });
		if (m.erreur) contenu.append(el('p', { class: 'jl-alerte-texte' }, 'La requête a échoué : ', m.erreur));
		if (c.non_capture) contenu.append(el('p', { class: 'jl-doux-texte' }, 'Valeurs avant et après non détaillées pour cette requête (plusieurs tables ou condition complexe). La requête et ses paramètres restent ci-dessous.'));
		if (Array.isArray(c.lignes)) {
			if (!c.lignes.length && m.operation === 'UPDATE') contenu.append(el('p', { class: 'jl-doux-texte' }, 'Aucune valeur n\'a réellement changé.'));
			c.lignes.forEach((l) => contenu.append(blocLigne(l)));
		}
		if (c.valeurs) contenu.append(tableValeurs(c.valeurs, 'Valeur envoyée'));
		if (c.tronque) contenu.append(el('p', { class: 'jl-doux-texte' }, 'Seules les 50 premières lignes sont détaillées.'));

		const sql = el('details', null, el('summary', null, 'Requête SQL et paramètres'), el('pre', null, m.sql));
		if (m.parametres && Object.keys(m.parametres).length) sql.append(el('pre', null, JSON.stringify(m.parametres, null, 2)));
		contenu.append(sql);

		return el('article', { class: 'jl-modif' }, entete, contenu);
	}

	function rendreDetail(a) {
		const u = a.utilisateur;
		$('#jlTiroirTitre').textContent = a.libelle;
		$('#jlTiroirSur').replaceChildren(
			el('span', { class: 'jl-type jl-type--' + a.type }, icone(CFG.icones[a.type] || 'bi-dot'), a.type_libelle),
			el('span', null, formaterDate(a.date)));

		const blocs = [];
		blocs.push(section('Qui', paires([
			['Utilisateur', u ? (u.nom || u.pseudo) : el('span', { class: 'jl-vide-valeur' }, 'Visiteur non connecté')],
			['Identifiant', u ? u.pseudo + ' (compte n° ' + u.id + ')' : undefined],
			['Rôle', u ? u.role : undefined],
			['Session', a.session ? el('code', null, a.session) : undefined],
		])));

		blocs.push(section('Où et comment', paires([
			['Adresse', el('code', null, a.methode + ' ' + a.url)],
			['Venant de', a.referent ? el('code', null, a.referent) : undefined],
			['Réponse', a.statut !== null ? String(a.statut) + (a.redirection ? ' → ' + a.redirection : '') : undefined],
			['Durée', a.duree !== null ? a.duree + ' ms' : undefined],
			['Appel en arrière-plan', a.ajax ? 'oui (AJAX)' : 'non'],
			['Adresse IP', a.ip],
			['Appareil', a.appareil],
			['Navigateur complet', a.user_agent ? el('code', null, a.user_agent) : undefined],
		])));

		if (a.evenements && a.evenements.length) {
			blocs.push(section('Événements', ...a.evenements.map((e) => el('div', { class: 'jl-evenement' },
				el('b', null, e.libelle), ' ', el('span', { class: 'jl-etiquette' }, (e.moment || '').slice(11)),
				e.details && Object.keys(e.details).length ? paires(Object.entries(e.details)) : null))));
		}
		if (a.donnees && Object.keys(a.donnees).length) {
			blocs.push(section('Données envoyées', paires(Object.entries(a.donnees))));
		}
		if (a.fichiers && a.fichiers.length) {
			blocs.push(section('Fichiers envoyés', ...a.fichiers.map((f) => paires([
				['Fichier', f.nom], ['Champ', f.champ], ['Taille', f.taille !== null ? Math.round(f.taille / 1024) + ' Ko' : null], ['Type', f.type],
			]))));
		}
		if (a.erreur) {
			blocs.push(section('Erreur PHP', el('pre', null, a.erreur)));
		}
		blocs.push(section(
			a.modifications.length ? 'Écritures en base (' + a.modifications.length + ')' : 'Écritures en base',
			a.modifications.length
				? a.modifications.map(carteModification)
				: el('p', { class: 'jl-doux-texte' }, 'Aucune écriture en base pendant cette action.'),
			a.modifications_ignorees ? el('p', { class: 'jl-doux-texte' }, pluriel(a.modifications_ignorees, 'écriture supplémentaire non détaillée', 'écritures supplémentaires non détaillées') + ' (plus de 300 dans la même action).') : null));

		corps.replaceChildren(...blocs);
	}

	async function ouvrir(id, origine) {
		ligneOrigine = origine;
		tiroir.hidden = false;
		voile.hidden = false;
		document.body.classList.add('jl-verrou');
		$('#jlTiroirTitre').textContent = 'Chargement…';
		$('#jlTiroirSur').replaceChildren();
		corps.replaceChildren(squelettes());
		$('#jlFermer').focus();
		try {
			const d = await lireJson(BASE + '/app/audit.detail.php?id=' + encodeURIComponent(id));
			rendreDetail(d.action);
		} catch (e) {
			$('#jlTiroirTitre').textContent = 'Détail indisponible';
			corps.replaceChildren(etat('Le détail n\'a pas pu être chargé.', e.message, true));
		}
	}
	function fermer() {
		if (tiroir.hidden) return;
		tiroir.hidden = true;
		voile.hidden = true;
		document.body.classList.remove('jl-verrou');
		if (ligneOrigine && document.contains(ligneOrigine)) ligneOrigine.focus();
	}

	liste.addEventListener('click', (e) => {
		const b = e.target.closest('.jl-ligne');
		if (b) ouvrir(b.dataset.id, b);
	});
	$('#jlFermer').addEventListener('click', fermer);
	voile.addEventListener('click', fermer);
	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape') fermer();
	});

	lireAdresse();
	majEtat();
	if (CFG.pret) charger(true);
	else planifier();
})();
