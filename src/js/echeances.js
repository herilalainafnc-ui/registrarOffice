/* =============================================================================
   Echeances de paiement (/finance/echeances) — page : src/finance/echeances.php,
   styles : src/css/echeances.css.

   - Une echelle commune a toutes les cartes (la session et toutes les dates
     saisies, au mois pres) : les frises restent alignees d'un type a l'autre.
   - Pendant la saisie, le jalon glisse a sa date et le repere dit ou elle
     tombe (« Vendredi, 3e semaine de la session ») ; chaque tranche doit
     suivre la precedente.
   - La barre d'enregistrement monte des qu'une date change et redescend une
     fois enregistre ; rien ne part sans « Enregistrer les echeances ».
   ========================================================================== */
(function () {
	'use strict';

	const page = document.getElementById('ecPage');
	const form = document.getElementById('ecForm');
	const cfgEl = document.getElementById('ecConfig');
	if (!page || !form || !cfgEl) { return; }
	const cfg = JSON.parse(cfgEl.textContent);
	const barre = document.getElementById('ecBarre');
	const texteBarre = document.getElementById('ecModifs');
	const boutonEnregistrer = document.getElementById('ecEnregistrer');

	const JOUR = 86400000;
	const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
	const INITIALES = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
	const NBSP = ' ';
	const fmtJour = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', timeZone: 'UTC' });
	const reduit = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function jour(ymd) {
		const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || '');
		if (!m) { return null; }
		const t = Date.UTC(+m[1], +m[2] - 1, +m[3]);
		return new Date(t).getUTCMonth() === +m[2] - 1 ? Math.round(t / JOUR) : null;
	}
	const enDate = (j) => new Date(j * JOUR);
	const versYmd = (j) => enDate(j).toISOString().slice(0, 10);
	const debutDeMois = (j) => { const d = enDate(j); return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1) / JOUR; };
	const moisSuivant = (j) => { const d = enDate(j); return Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 1) / JOUR; };
	const dateCourte = (j) => { const d = enDate(j); return d.getUTCDate() + NBSP + MOIS[d.getUTCMonth()] + ' ' + d.getUTCFullYear(); };
	const ordinal = (n) => (n === 1 ? '1re' : n + 'e');
	const jours = (n) => n + NBSP + 'jour' + (n > 1 ? 's' : '');

	const auj = jour(cfg.aujourdhui);
	const s0 = jour(cfg.session.debut);
	const s1 = jour(cfg.session.fin);
	let enregistrees = cfg.enregistrees || {};
	let enCours = false;

	function notifier(type, message) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); }
	}
	function confirmer(message, options) {
		if (window.Toast && typeof window.Toast.confirm === 'function') { return window.Toast.confirm(message, options); }
		return Promise.resolve(window.confirm(message));
	}

	const champs = () => Array.prototype.slice.call(form.querySelectorAll('input[type="date"][data-type]'));
	const enregistree = (c) => ((enregistrees[c.dataset.type] || {})[c.dataset.tranche]) || '';
	const jalonDe = (c) => form.querySelector('.ec-type[data-type="' + c.dataset.type + '"] .ec-jalon[data-tranche="' + c.dataset.tranche + '"]');

	/* --- La frise : une echelle pour toutes les cartes ------------------------------ */

	function disposer() {
		const dates = champs().map((c) => jour(c.value)).filter((j) => j !== null);
		const bornes = dates.concat([s0, s1].filter((j) => j !== null));
		page.classList.toggle('ec--sans-frise', !bornes.length);
		if (!bornes.length) { return; }

		const d0 = debutDeMois(Math.min.apply(null, bornes));
		const d1 = moisSuivant(Math.max.apply(null, bornes));
		const total = d1 - d0;
		const pc = (j) => ((j - d0) / total * 100).toFixed(3) + '%';

		const traits = [];
		const etiquettes = [];
		for (let m = d0; m < d1; m = moisSuivant(m)) {
			const dm = enDate(m);
			const janvier = dm.getUTCMonth() === 0;
			if (m > d0) {
				const v = janvier ? 'var(--ec-trait-fort)' : 'var(--ec-trait)';
				traits.push('linear-gradient(' + v + ', ' + v + ') ' + pc(m) + ' 66% / 1px 34% no-repeat');
			}
			const an = (m === d0 || janvier) ? '<b>' + NBSP + String(dm.getUTCFullYear()).slice(2) + '</b>' : '';
			etiquettes.push('<span style="left:' + pc((m + moisSuivant(m)) / 2) + '"><em class="ec-l">' + MOIS[dm.getUTCMonth()] + an
				+ '</em><em class="ec-c' + (janvier ? ' ec-jan' : '') + '">' + INITIALES[dm.getUTCMonth()] + '</em></span>');
		}
		page.style.setProperty('--ec-grille', traits.length ? traits.join(', ') : 'none');
		const html = etiquettes.join('');
		page.querySelectorAll('.ec-rail__mois').forEach((z) => { z.innerHTML = html; z.dataset.mois = String(etiquettes.length); });

		if (s0 !== null && s1 !== null) {
			page.style.setProperty('--ec-s0', pc(s0));
			page.style.setProperty('--ec-sl', ((s1 + 1 - s0) / total * 100).toFixed(3) + '%');
		}
		if (auj >= d0 && auj < d1) { page.style.setProperty('--ec-auj', pc(auj + 0.5)); page.setAttribute('data-auj', ''); }
		else { page.removeAttribute('data-auj'); }

		champs().forEach((c) => {
			const jalon = jalonDe(c);
			if (!jalon) { return; }
			const j = jour(c.value);
			jalon.classList.toggle('is-vide', j === null);
			if (j === null) { return; }
			jalon.style.setProperty('--x', pc(j + 0.5));
			jalon.classList.toggle('is-dehors', s0 !== null && s1 !== null && (j < s0 || j > s1));
		});
		densite();
	}

	/** Les etiquettes des mois selon la place : mois entiers, initiales, ou une sur trois. */
	function densite() {
		page.querySelectorAll('.ec-rail__mois').forEach((z) => {
			const largeur = z.getBoundingClientRect().width;
			if (!largeur) { return; }
			const parMois = largeur / (+z.dataset.mois || 1);
			z.dataset.densite = parMois >= 46 ? 'pleine' : (parMois >= 15 ? 'courte' : 'minime');
		});
	}

	/* --- Reperes et controle ---------------------------------------------------------- */

	function reperer(c) {
		const r = c.closest('.ec-tranche').querySelector('.ec-tranche__repere');
		const j = jour(c.value);
		r.className = 'ec-tranche__repere';
		if (j === null) {
			r.textContent = c.value ? 'Date illisible' : 'À définir';
			r.classList.add(c.value ? 'is-erreur' : 'is-vide');
			return;
		}
		const nom = fmtJour.format(enDate(j));
		let ou = '';
		if (s0 !== null && j < s0) { ou = jours(s0 - j) + ' avant le début de la session'; r.classList.add('is-alerte'); }
		else if (s1 !== null && j > s1) { ou = jours(j - s1) + ' après la fin de la session'; r.classList.add('is-alerte'); }
		else if (s0 !== null) { ou = ordinal(Math.floor((j - s0) / 7) + 1) + ' semaine de la session'; }
		r.textContent = nom.charAt(0).toUpperCase() + nom.slice(1) + (ou ? ', ' + ou : '');
	}

	/** Chaque tranche apres la precedente du meme type. Renvoie le nombre d'erreurs. */
	function controler() {
		let erreurs = 0;
		form.querySelectorAll('.ec-type').forEach((carte) => {
			let precedente = null;
			carte.querySelectorAll('input[type="date"]').forEach((c) => {
				const tranche = c.closest('.ec-tranche');
				const r = tranche.querySelector('.ec-tranche__repere');
				const j = jour(c.value);
				const avant = j !== null && precedente !== null && j <= precedente.j;
				const illisible = c.value !== '' && j === null;
				tranche.classList.toggle('is-erreur', avant || illisible);
				c.setAttribute('aria-invalid', avant || illisible ? 'true' : 'false');
				if (avant) {
					r.className = 'ec-tranche__repere is-erreur';
					r.textContent = 'Après la ' + ordinal(precedente.rang) + ' tranche (' + dateCourte(precedente.j) + '), pas avant.';
				}
				if (avant || illisible) { erreurs++; }
				if (j !== null) { precedente = { j: j, rang: +c.dataset.tranche }; }
			});
		});
		return erreurs;
	}

	/** Les changements par rapport a ce qui est enregistre : barre, marques, bouton. */
	function etat() {
		let modifs = 0;
		champs().forEach((c) => {
			const change = c.value !== enregistree(c);
			c.closest('.ec-tranche').classList.toggle('is-modifiee', change);
			if (change) { modifs++; }
		});
		const erreurs = controler();
		barre.classList.toggle('is-visible', modifs > 0);
		texteBarre.innerHTML = !modifs ? '' : '<b>' + modifs + ' date' + (modifs > 1 ? 's' : '') + ' modifiée' + (modifs > 1 ? 's' : '') + '</b>'
			+ (erreurs ? ' · <span class="is-erreur">' + erreurs + ' à corriger</span>' : ' · pas encore enregistrée' + (modifs > 1 ? 's' : ''));
		boutonEnregistrer.disabled = erreurs > 0 || enCours;
		return modifs;
	}

	function toutRafraichir() {
		champs().forEach(reperer);
		disposer();
		etat();
	}

	/** La pastille de la session et son libelle dans la liste, apres un enregistrement. */
	function majSession() {
		const n = Object.keys(enregistrees).reduce((s, t) => s + Object.keys(enregistrees[t] || {}).length, 0);
		const pastille = document.getElementById('ecEtat');
		if (pastille) {
			pastille.className = 'ec-etat ' + (n === 0 ? 'ec-etat--alerte' : (n < cfg.attendues ? 'ec-etat--partiel' : 'ec-etat--ok'));
			pastille.textContent = n === 0 ? 'Échéances à définir' : (n < cfg.attendues ? n + ' dates sur ' + cfg.attendues : 'Échéances définies');
		}
		const option = document.querySelector('#ecSession option[value="' + cfg.session.id + '"]');
		if (option) {
			option.textContent = cfg.session.libelle; // la session affichee : son etat est dans la pastille
			option.dataset.reglee = n === 0 ? '0' : '1';
		}
	}

	/* --- Serveur ------------------------------------------------------------------------- */

	async function jetonFrais() {
		const rep = await fetch(location.pathname + location.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
		const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(await rep.text());
		if (m) { window.CSRF_TOKEN = m[1]; }
	}

	async function poster(donnees, deuxieme) {
		donnees.set('ajax', '1');
		donnees.set('csrf_token', window.CSRF_TOKEN || '');
		const rep = await fetch(location.pathname + location.search, {
			method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
		});
		let json = null;
		try { json = await rep.json(); } catch (x) { /* reponse sans JSON */ }
		if (json && json.code === 'csrf' && !deuxieme) { await jetonFrais(); return poster(donnees, true); }
		if (!rep.ok || !json || !json.success) {
			throw new Error((json && json.message) || 'Le serveur n’a pas répondu comme prévu : réessayez.');
		}
		return json;
	}

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		if (enCours) { return; }
		if (controler() > 0) {
			const err = form.querySelector('.ec-tranche.is-erreur input');
			if (err) { err.focus(); }
			return;
		}
		const changees = champs().filter((c) => c.value !== enregistree(c));
		enCours = true;
		boutonEnregistrer.disabled = true;
		boutonEnregistrer.classList.add('is-charge');
		const icone = boutonEnregistrer.querySelector('i');
		if (icone) { icone.className = 'bi bi-arrow-repeat'; }
		try {
			const r = await poster(new FormData(form));
			const garder = {};
			Object.keys(cfg.types).forEach((t) => { if (r.dates && r.dates[t]) { garder[t] = r.dates[t]; } });
			enregistrees = garder;
			notifier('success', r.message);
			majSession();
			if (!reduit()) {
				changees.forEach((c) => {
					const jalon = jalonDe(c);
					if (!jalon) { return; }
					jalon.classList.remove('is-eclat');
					void jalon.offsetWidth;
					jalon.classList.add('is-eclat');
					setTimeout(() => jalon.classList.remove('is-eclat'), 1200);
				});
			}
		} catch (x) {
			notifier('error', x.message);
		} finally {
			enCours = false;
			boutonEnregistrer.classList.remove('is-charge');
			if (icone) { icone.className = 'bi bi-check-lg'; }
			etat();
		}
	});

	/* --- Saisie, annulation, reprise de l'annee precedente ------------------------------- */

	form.addEventListener('input', (ev) => {
		const c = ev.target;
		if (!c.matches('input[type="date"][data-type]')) { return; }
		reperer(c);
		disposer();
		etat();
	});

	document.getElementById('ecAnnuler').addEventListener('click', () => {
		champs().forEach((c) => { c.value = enregistree(c); });
		toutRafraichir();
	});

	const copier = document.getElementById('ecCopier');
	if (copier && cfg.copie) {
		copier.addEventListener('click', async () => {
			const remplies = champs().filter((c) => c.value !== '');
			if (remplies.length && !(await confirmer('Les dates déjà saisies pour cette session seront remplacées par celles de ' + cfg.copie.libelle + ', 52 semaines plus tard.', {
				title: 'Remplacer les dates ?', confirmText: 'Remplacer', cancelText: 'Garder les miennes', danger: false,
			}))) { return; }
			champs().forEach((c) => {
				const source = ((cfg.copie.dates[c.dataset.type] || {})[c.dataset.tranche]) || '';
				const j = jour(source);
				if (j !== null) { c.value = versYmd(j + 364); }
			});
			toutRafraichir();
			notifier('info', 'Dates reprises : vérifiez-les, puis enregistrez.');
		});
	}

	/* --- Changer de session ----------------------------------------------------------------- */

	const modifiee = () => champs().some((c) => c.value !== enregistree(c));

	async function allerA(sessionId) {
		if (modifiee() && !(await confirmer('Les dates modifiées de ' + cfg.session.libelle + ' ne sont pas enregistrées.', {
			title: 'Changer de session ?', confirmText: 'Changer sans enregistrer', cancelText: 'Rester', danger: true,
		}))) { return false; }
		window.removeEventListener('beforeunload', avantDePartir);
		location.href = location.pathname + '?session_id=' + encodeURIComponent(sessionId);
		return true;
	}

	const choix = document.getElementById('ecSession');
	/** La liste prend la largeur de son option la plus longue : on la ramene au texte affiche. */
	function ajusterChoix() {
		if (!choix || !choix.selectedOptions[0]) { return; }
		const mesure = document.createElement('span');
		const style = getComputedStyle(choix);
		mesure.textContent = choix.selectedOptions[0].textContent;
		mesure.style.cssText = 'position:absolute;visibility:hidden;white-space:pre;font:' + style.font + ';letter-spacing:' + style.letterSpacing;
		document.body.appendChild(mesure);
		choix.style.width = Math.ceil(mesure.getBoundingClientRect().width + parseFloat(style.paddingLeft) + parseFloat(style.paddingRight) + 2) + 'px';
		mesure.remove();
	}
	if (choix) {
		choix.addEventListener('change', async () => {
			if (!(await allerA(choix.value))) { choix.value = String(cfg.session.id); }
		});
	}
	page.querySelectorAll('.ec-pas[data-session]').forEach((a) => {
		a.addEventListener('click', (ev) => { ev.preventDefault(); allerA(a.dataset.session); });
	});

	function avantDePartir(ev) {
		if (modifiee()) { ev.preventDefault(); ev.returnValue = ''; }
	}
	window.addEventListener('beforeunload', avantDePartir);

	if (window.ResizeObserver) {
		let attente = 0;
		new ResizeObserver(() => { cancelAnimationFrame(attente); attente = requestAnimationFrame(densite); }).observe(page);
	}

	/* --- Depart ---------------------------------------------------------------------------------- */

	toutRafraichir();
	ajusterChoix();
	if (document.fonts && document.fonts.ready) { document.fonts.ready.then(ajusterChoix); }
	setTimeout(() => {
		page.classList.remove('ec-entree');
		page.querySelectorAll('.ec-jalon').forEach((j) => j.classList.add('is-fluide'));
	}, reduit() ? 0 : 1700);
})();
