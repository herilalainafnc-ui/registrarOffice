/* =============================================================================
   Sessions (/sessions) — page : src/sessions.php, styles : src/css/sessions.css.

   - La frise de chaque annee : les barres posees sur les mois (positions en
     jours), le trait d'aujourd'hui, les etiquettes des mois selon la place
     (mois entiers, initiales, ou une sur trois).
   - L'edition sur place : l'editeur s'ouvre sous la ligne ; pendant la saisie,
     la barre, la duree, les chevauchements et la session en cours suivent.
   - L'enregistrement sans recharger : la liste est relue chez le serveur, puis
     chaque ligne glisse de son ancienne place a la nouvelle (FLIP).
   ========================================================================== */
(function () {
	'use strict';

	const page = document.getElementById('snPage');
	const gabarit = document.getElementById('snGabarit');
	const cfgEl = document.getElementById('snConfig');
	if (!page || !gabarit || !cfgEl) { return; }
	const cfg = JSON.parse(cfgEl.textContent);

	const JOUR = 86400000;
	const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
	const INITIALES = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
	const NBSP = ' ';
	const EASE = 'cubic-bezier(.22, 1, .36, 1)';
	const fmtLong = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
	const reduit = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	/* --- Dates : des jours entiers depuis 1970 (UTC), sans fuseau ni heure ------ */

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
	const auj = jour(cfg.aujourdhui);

	function dateCourte(j, sansAnnee) {
		const d = enDate(j);
		return d.getUTCDate() + NBSP + MOIS[d.getUTCMonth()] + (sansAnnee ? '' : ' ' + d.getUTCFullYear());
	}
	function periode(j0, j1) {
		return dateCourte(j0, enDate(j0).getUTCFullYear() === enDate(j1).getUTCFullYear()) + ' → ' + dateCourte(j1);
	}
	const duree = (n) => (n < 14 ? n + NBSP + 'jour' + (n > 1 ? 's' : '') : Math.round(n / 7) + NBSP + 'sem.');
	const dureeLongue = (n) => (n < 14 ? n + ' jour' + (n > 1 ? 's' : '') : Math.round(n / 7) + ' semaines');

	/* --- Notifications ----------------------------------------------------------- */

	function notifier(type, message) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); }
	}
	function confirmer(message, options) {
		if (window.Toast && typeof window.Toast.confirm === 'function') { return window.Toast.confirm(message, options); }
		return Promise.resolve(window.confirm(message));
	}

	/* --- La frise d'une annee ------------------------------------------------------ */

	/** Les dates d'une ligne : celles de l'editeur pendant la saisie, sinon celles de la base. */
	function datesDe(ligne) {
		const a = ligne._apercu;
		const d = jour(a ? a.debut : ligne.dataset.debut);
		const f = jour(a ? a.fin : ligne.dataset.fin);
		return d !== null && f !== null && f >= d ? { d: d, f: f } : null;
	}

	const lignesDe = (sec) => Array.prototype.filter.call(sec.querySelectorAll('.sn-ligne'), (l) => l.closest('.sn-annee') === sec);

	function disposer(sec) {
		const mesures = lignesDe(sec).map((l) => ({ l: l, x: datesDe(l) }));
		const datees = mesures.filter((m) => m.x);
		const zone = sec.querySelector('.sn-regle__mois');
		sec.classList.toggle('sn-annee--sans-frise', !datees.length);
		if (!datees.length) { return; }

		const d0 = debutDeMois(Math.min.apply(null, datees.map((m) => m.x.d)));
		const d1 = moisSuivant(Math.max.apply(null, datees.map((m) => m.x.f)));
		const total = d1 - d0;
		const pc = (j) => ((j - d0) / total * 100).toFixed(3) + '%';

		const traits = [];
		const etiquettes = [];
		for (let m = d0; m < d1; m = moisSuivant(m)) {
			const dm = enDate(m);
			const janvier = dm.getUTCMonth() === 0;
			if (m > d0) {
				const v = janvier ? 'var(--sn-trait-fort)' : 'var(--sn-trait)';
				traits.push('linear-gradient(' + v + ', ' + v + ') ' + pc(m) + ' 0 / 1px 100% no-repeat');
			}
			const an = (m === d0 || janvier) ? '<b>' + NBSP + String(dm.getUTCFullYear()).slice(2) + '</b>' : '';
			etiquettes.push('<span style="left:' + pc((m + moisSuivant(m)) / 2) + '"><em class="sn-l">' + MOIS[dm.getUTCMonth()] + an
				+ '</em><em class="sn-c' + (janvier ? ' sn-jan' : '') + '">' + INITIALES[dm.getUTCMonth()] + '</em></span>');
		}
		sec.style.setProperty('--sn-grille', traits.length ? traits.join(', ') : 'none');

		const dedans = auj >= d0 && auj < d1;
		if (zone) {
			zone.innerHTML = etiquettes.join('') + (dedans ? '<i class="sn-regle__auj" title="Aujourd’hui, ' + esc(dateCourte(auj)) + '"></i>' : '');
			zone.dataset.mois = String(etiquettes.length);
		}
		if (dedans) { sec.style.setProperty('--sn-auj', pc(auj + 0.5)); sec.setAttribute('data-auj', ''); }
		else { sec.style.removeProperty('--sn-auj'); sec.removeAttribute('data-auj'); }

		mesures.forEach((m) => {
			const b = m.l.querySelector('.sn-barre');
			m.l.classList.toggle('sn-ligne--sans-barre', !m.x);
			if (!b || !m.x) { return; }
			b.style.left = pc(m.x.d);
			b.style.width = ((m.x.f + 1 - m.x.d) / total * 100).toFixed(3) + '%';
			if (auj >= m.x.d && auj <= m.x.f) { b.style.setProperty('--avance', ((auj + 1 - m.x.d) / (m.x.f + 1 - m.x.d) * 100).toFixed(2) + '%'); }
			else { b.style.removeProperty('--avance'); }
		});
		densite(sec);
	}

	/** Les etiquettes des mois selon la place : mois entiers, initiales, ou une sur trois. */
	function densite(sec) {
		const zone = sec.querySelector('.sn-regle__mois');
		if (!zone) { return; }
		const largeur = zone.getBoundingClientRect().width;
		if (!largeur) { return; }
		const parMois = largeur / (+zone.dataset.mois || 1);
		zone.dataset.densite = parMois >= 46 ? 'pleine' : (parMois >= 15 ? 'courte' : 'minime');
	}

	const annees = () => Array.prototype.slice.call(page.querySelectorAll('.sn-annee'));
	const toutDisposer = () => annees().forEach(disposer);

	/* --- Ouvrir, fermer ------------------------------------------------------------ */

	function poser(sec, ouvrir) {
		sec.classList.toggle('is-ouverte', ouvrir);
		const b = sec.querySelector(':scope > .sn-annee__tete > .sn-annee__bascule, :scope > .sn-passees__bascule');
		if (b) { b.setAttribute('aria-expanded', String(ouvrir)); }
	}

	/** Les barres se deroulent de nouveau, a l'ouverture d'une annee. */
	function rejouer(sec) {
		if (reduit()) { return; }
		sec.classList.remove('sn-revele');
		void sec.offsetWidth;
		sec.classList.add('sn-revele');
		clearTimeout(sec._revele);
		sec._revele = setTimeout(() => sec.classList.remove('sn-revele'), 1400);
	}

	async function basculer(sec, ouvrir) {
		const ouverte = sec.classList.contains('is-ouverte');
		if (ouvrir === undefined) { ouvrir = !ouverte; }
		if (ouvrir === ouverte) { return; }
		if (!ouvrir && edition && sec.contains(edition.ligne) && !(await fermerEditeur({ sansFocus: true }))) { return; }
		poser(sec, ouvrir);
		if (!ouvrir) { return; }
		if (sec.classList.contains('sn-annee')) { disposer(sec); rejouer(sec); }
		else { lignesAnnees(sec).forEach(densite); }
	}
	const lignesAnnees = (conteneur) => Array.prototype.slice.call(conteneur.querySelectorAll('.sn-annee'));

	/* --- L'editeur ------------------------------------------------------------------ */

	let edition = null; // { ligne, tiroir, form, sec, nouvelle, depart, texte }

	const NOMS_TYPES = cfg.ordre.map((n) => cfg.semestres[n].nom);
	const CODE_TYPE = /^(PREM|ETE|DEUX|HIVER)\d{8}$/;
	const anneeValide = (a) => { const m = /^(\d{4}) - (\d{4})$/.exec(a || ''); return !!m && +m[2] === +m[1] + 1; };
	const codeType = (sem, annee) => (cfg.semestres[sem] && anneeValide(annee) ? cfg.semestres[sem].prefixe + annee.replace(' - ', '') : '');
	const serialiser = (form) => new URLSearchParams(new FormData(form)).toString();

	function valeurs(form) {
		const r = form.querySelector('input[name="session_semester"]:checked');
		return {
			semestre: r ? r.value : '',
			nom: form.elements.session_name.value.trim(),
			annee: form.elements.session_year.value.trim(),
			code: form.elements.session_code.value.trim(),
			debut: form.elements.date_entry.value,
			fin: form.elements.graduation_date.value,
		};
	}

	/** La pastille glisse vers le semestre choisi (positions mesurees : 4 colonnes ou 2 x 2). */
	function placerPouce(form) {
		const seg = form.querySelector('.sn-segments');
		const pouce = seg && seg.querySelector('.sn-segments__pouce');
		if (!pouce) { return; }
		const r = seg.querySelector('input:checked');
		if (!r) { pouce.style.opacity = '0'; return; }
		const lab = r.closest('label');
		pouce.style.opacity = '1';
		pouce.style.setProperty('--x', lab.offsetLeft + 'px');
		pouce.style.setProperty('--y', lab.offsetTop + 'px');
		pouce.style.setProperty('--w', lab.offsetWidth + 'px');
		pouce.style.setProperty('--h', lab.offsetHeight + 'px');
	}

	async function ouvrirEditeur(ligne) {
		if (edition) {
			if (edition.ligne === ligne) { return; }
			if (!(await fermerEditeur({ sansFocus: true }))) { return; }
		}
		if (!ligne.isConnected) { return; }
		const tiroir = gabarit.content.firstElementChild.cloneNode(true);
		const form = tiroir.querySelector('form');
		const d = ligne.dataset;
		const id = d.id || '';
		const sufixe = id || 'n' + Date.now();

		form.elements.action.value = id ? 'update' : 'create';
		form.elements.session_id.value = id;
		form.elements.session_name.value = d.nom || '';
		form.elements.session_year.value = d.annee || '';
		form.elements.session_code.value = d.code || '';
		form.elements.date_entry.value = d.debut || '';
		form.elements.graduation_date.value = d.fin || '';
		const radio = form.querySelector('input[name="session_semester"][value="' + d.semestre + '"]');
		if (radio) { radio.checked = true; }
		form.querySelector('.sn-editeur__id').textContent = id ? 'Session n°' + NBSP + id : 'Nouvelle session';
		form.querySelector('.sn-editeur__valider span').textContent = id ? 'Enregistrer' : 'Créer la session';
		form.setAttribute('aria-label', id ? 'Modifier ' + (d.nom || 'la session') : 'Nouvelle session');
		tiroir.id = 'snEditeur-' + sufixe;

		ligne.after(tiroir);
		ligne.classList.add('is-edition');
		const bouton = ligne.querySelector('.sn-modifier');
		if (bouton) { bouton.setAttribute('aria-expanded', 'true'); bouton.setAttribute('aria-controls', tiroir.id); }
		const barre = ligne.querySelector('.sn-barre');
		if (barre) { barre.classList.add('is-fluide'); }

		const titre = ligne.querySelector('.sn-ligne__titre');
		const dates = ligne.querySelector('.sn-ligne__dates');
		edition = {
			ligne: ligne, tiroir: tiroir, form: form, sec: ligne.closest('.sn-annee'), nouvelle: !id,
			depart: serialiser(form), texte: { titre: titre ? titre.textContent : '', dates: dates ? dates.innerHTML : '' },
		};
		placerPouce(form);
		apercu();

		requestAnimationFrame(() => requestAnimationFrame(() => {
			tiroir.classList.add('is-ouvert');
			const seg = form.querySelector('.sn-segments');
			if (seg) { seg.classList.add('is-pret'); }
		}));
		/* Au clavier ou a la souris : le curseur dans le premier champ utile. Au
		   doigt, rien : le clavier virtuel cacherait la moitie de l'ecran. */
		if (window.matchMedia('(pointer: fine)').matches) {
			const cible = id ? form.elements.date_entry : form.elements.session_name;
			setTimeout(() => { if (edition && edition.form === form) { cible.focus({ preventScroll: true }); } }, 80);
		}
		setTimeout(() => {
			if (edition && edition.tiroir === tiroir) { tiroir.scrollIntoView({ block: 'nearest', behavior: reduit() ? 'auto' : 'smooth' }); }
		}, reduit() ? 0 : 360);
	}

	async function fermerEditeur(opts) {
		opts = opts || {};
		if (!edition) { return true; }
		const e = edition;
		if (!opts.force && serialiser(e.form) !== e.depart) {
			const ok = await confirmer('Les modifications de « ' + (e.ligne.dataset.nom || 'cette session') + ' » ne sont pas enregistrées.', {
				title: 'Abandonner les modifications ?', confirmText: 'Abandonner', cancelText: 'Continuer la saisie', danger: true,
			});
			if (!ok || edition !== e) { return false; }
		}
		edition = null;
		e.ligne._apercu = null;
		e.ligne.classList.remove('is-edition', 'sn-ligne--croise');
		annees().forEach((s) => lignesDe(s).forEach((l) => l.classList.remove('sn-ligne--croise')));
		const titre = e.ligne.querySelector('.sn-ligne__titre');
		const dates = e.ligne.querySelector('.sn-ligne__dates');
		if (titre) { titre.textContent = e.texte.titre; }
		if (dates) { dates.innerHTML = e.texte.dates; }
		const bouton = e.ligne.querySelector('.sn-modifier');
		if (bouton) { bouton.setAttribute('aria-expanded', 'false'); bouton.removeAttribute('aria-controls'); }
		if (e.sec) { disposer(e.sec); }
		e.tiroir.classList.remove('is-ouvert');
		if (e.nouvelle && !reduit()) {
			e.ligne.animate([{ opacity: 1 }, { opacity: 0, transform: 'translateY(-4px)' }], { duration: 200, easing: 'ease-in', fill: 'forwards' });
		}
		const barre = e.ligne.querySelector('.sn-barre');
		setTimeout(() => {
			e.tiroir.remove();
			if (e.nouvelle) { e.ligne.remove(); if (e.sec) { disposer(e.sec); } }
			if (barre) { barre.classList.remove('is-fluide'); }
		}, reduit() ? 0 : 330);
		if (!opts.sansFocus && bouton && !e.nouvelle) { bouton.focus({ preventScroll: true }); }
		return true;
	}

	/** La session que l'application retiendra aujourd'hui (la regle de syncActiveSessionByCurrentDate). */
	function courantePrevue() {
		let retenue = null;
		page.querySelectorAll('.sn-ligne').forEach((l) => {
			const x = datesDe(l);
			if (!x || auj < x.d || auj > x.f) { return; }
			const cle = [x.d, -x.f, -(+l.dataset.semestre || 9), +(l.dataset.id || 1e9)];
			if (!retenue || comparer(cle, retenue.cle) > 0) { retenue = { l: l, cle: cle }; }
		});
		return retenue ? retenue.l : null;
	}
	function comparer(a, b) {
		for (let i = 0; i < a.length; i++) { if (a[i] !== b[i]) { return a[i] > b[i] ? 1 : -1; } }
		return 0;
	}

	/** Pendant la saisie : la ligne, sa barre, la duree et ce que ces dates changent. */
	function apercu() {
		if (!edition) { return; }
		const e = edition;
		const v = valeurs(e.form);
		e.ligne._apercu = { debut: v.debut, fin: v.fin };
		const x = datesDe(e.ligne);

		const titre = e.ligne.querySelector('.sn-ligne__titre');
		if (titre) { titre.textContent = v.nom || (e.nouvelle ? 'Nouvelle session' : e.texte.titre); }
		const dates = e.ligne.querySelector('.sn-ligne__dates');
		if (dates) {
			dates.innerHTML = x ? esc(periode(x.d, x.f)) + '<span class="sn-ligne__duree"> · ' + duree(x.f - x.d + 1) + '</span>' : 'Dates à choisir';
		}
		e.form.querySelector('.sn-editeur__duree').innerHTML = x
			? '<b>' + dureeLongue(x.f - x.d + 1) + '</b><span>du ' + esc(fmtLong.format(enDate(x.d))) + ' au ' + esc(fmtLong.format(enDate(x.f))) + '</span>'
			: '';

		if (e.sec) { disposer(e.sec); }

		const notes = [];
		const autres = e.sec ? lignesDe(e.sec).filter((l) => l !== e.ligne) : [];
		const croisees = x ? autres.filter((l) => { const y = datesDe(l); return y && x.d <= y.f && y.d <= x.f; }) : [];
		autres.forEach((l) => l.classList.toggle('sn-ligne--croise', croisees.indexOf(l) >= 0));
		e.ligne.classList.toggle('sn-ligne--croise', croisees.length > 0);
		if (croisees.length) {
			notes.push(['alerte', 'bi-exclamation-triangle', 'Chevauche ' + croisees.map((l) => {
				const y = datesDe(l);
				return '« ' + l.dataset.nom + ' » (' + periode(y.d, y.f) + ')';
			}).join(', ') + '.']);
		}

		if (x) {
			const prevue = courantePrevue();
			const actuelle = page.querySelector('.sn-ligne--cours');
			if (prevue === e.ligne && actuelle !== e.ligne) {
				notes.push(['info', 'bi-broadcast', 'Ces dates couvrent aujourd’hui : elle deviendra la session en cours.']);
			} else if (actuelle === e.ligne && prevue !== e.ligne) {
				notes.push(['info', 'bi-broadcast', prevue
					? 'Elle cessera d’être la session en cours, au profit de « ' + prevue.dataset.nom + ' ».'
					: 'Ces dates ne couvrent plus aujourd’hui : il n’y aura plus de session en cours.']);
			}
		}
		if (e.sec && anneeValide(v.annee) && v.annee !== e.sec.dataset.annee) {
			notes.push(['info', 'bi-arrow-left-right', 'Elle rejoindra l’année ' + v.annee + ' à l’enregistrement.']);
		}
		e.form.querySelector('.sn-editeur__notes').innerHTML = notes.map((n) =>
			'<p class="sn-note sn-note--' + n[0] + '"><i class="bi ' + n[1] + '" aria-hidden="true"></i><span>' + esc(n[2]) + '</span></p>').join('');
	}

	/** Le semestre propose son nom et son code, tant qu'ils n'ont pas ete changes a la main. */
	function suivreSemestre(form) {
		const v = valeurs(form);
		const sem = cfg.semestres[v.semestre];
		if (!sem) { return; }
		if (!v.nom || NOMS_TYPES.indexOf(v.nom) >= 0) { form.elements.session_name.value = sem.nom; }
		if (!v.code || CODE_TYPE.test(v.code.toUpperCase())) { form.elements.session_code.value = codeType(v.semestre, v.annee) || v.code; }
		placerPouce(form);
	}
	function suivreAnnee(form) {
		const champ = form.elements.session_year;
		const m = /^\s*(\d{4})\s*(?:[-–—\/]\s*(\d{4}))?\s*$/.exec(champ.value);
		if (m) { champ.value = m[1] + ' - ' + (m[2] || +m[1] + 1); }
		const v = valeurs(form);
		if (!v.code || CODE_TYPE.test(v.code.toUpperCase())) { form.elements.session_code.value = codeType(v.semestre, v.annee) || v.code; }
	}

	/* --- Controle ---------------------------------------------------------------------- */

	function effacerErreurs(form) {
		form.querySelectorAll('.sn-erreur').forEach((x) => x.remove());
		form.querySelectorAll('.is-erreur').forEach((c) => c.classList.remove('is-erreur'));
		form.querySelectorAll('[aria-invalid]').forEach((i) => { i.removeAttribute('aria-invalid'); i.removeAttribute('aria-describedby'); });
	}
	function controler(form) {
		effacerErreurs(form);
		const v = valeurs(form);
		const champ = (nom) => form.elements[nom].closest('.sn-champ');
		const erreurs = [];
		if (!v.semestre) { erreurs.push([form.querySelector('.sn-champ--semestre'), 'Choisissez le semestre.']); }
		if (!v.nom) { erreurs.push([champ('session_name'), 'Donnez un nom à la session.']); }
		if (!anneeValide(v.annee)) { erreurs.push([champ('session_year'), 'Deux années qui se suivent, par exemple ' + cfg.anneeParDefaut + '.']); }
		const d = jour(v.debut);
		const f = jour(v.fin);
		if (d === null) { erreurs.push([champ('date_entry'), 'Choisissez la date de début.']); }
		if (f === null) { erreurs.push([champ('graduation_date'), 'Choisissez la date de fin.']); }
		else if (d !== null && f < d) { erreurs.push([champ('graduation_date'), 'La fin doit venir après le début.']); }
		erreurs.forEach((x, i) => {
			const bloc = x[0];
			const id = (edition ? edition.tiroir.id : 'sn') + '-err-' + i;
			bloc.classList.add('is-erreur');
			const p = document.createElement('small');
			p.className = 'sn-erreur'; p.id = id; p.textContent = x[1];
			bloc.appendChild(p);
			bloc.querySelectorAll('input').forEach((input) => { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', id); });
		});
		return erreurs;
	}

	/* --- Serveur ------------------------------------------------------------------------- */

	/** Un jeton de securite frais : celui de la page relue (init/head.php). */
	async function jetonFrais() {
		const rep = await fetch(location.pathname, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
		const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(await rep.text());
		if (m) { window.CSRF_TOKEN = m[1]; }
	}

	async function poster(donnees, deuxieme) {
		donnees.set('ajax', '1');
		donnees.set('csrf_token', window.CSRF_TOKEN || '');
		const rep = await fetch(location.pathname, {
			method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
		});
		let json = null;
		try { json = await rep.json(); } catch (x) { /* reponse sans JSON */ }
		if (json && json.code === 'csrf' && !deuxieme) {
			await jetonFrais();
			return poster(donnees, true);
		}
		if (!rep.ok || !json || !json.success) {
			throw new Error((json && json.message) || 'Le serveur n’a pas répondu comme prévu : réessayez.');
		}
		return json;
	}

	function occupe(bouton, oui) {
		if (!bouton) { return; }
		const i = bouton.querySelector('i');
		if (oui) {
			bouton.disabled = true;
			bouton.classList.add('is-charge');
			if (i) { i.dataset.classe = i.className; i.className = 'bi bi-arrow-repeat'; }
		} else {
			bouton.disabled = false;
			bouton.classList.remove('is-charge');
			if (i && i.dataset.classe) { i.className = i.dataset.classe; }
		}
	}

	async function enregistrer(form) {
		form._tente = true;
		const erreurs = controler(form);
		if (erreurs.length) {
			const premier = erreurs[0][0].querySelector('input:checked, input');
			if (premier) { premier.focus(); }
			return;
		}
		const bouton = form.querySelector('.sn-editeur__valider');
		occupe(bouton, true);
		try {
			const r = await poster(new FormData(form));
			const id = String(r.session_id || (edition && edition.ligne.dataset.id) || '');
			const annee = valeurs(form).annee;
			notifier('success', r.message);
			edition = null;
			await rafraichir({ eclat: id, annee: annee });
		} catch (x) {
			notifier('error', x.message);
			occupe(bouton, false);
		}
	}

	async function generer(bouton) {
		const annee = bouton.dataset.annee;
		const modele = bouton.dataset.modele;
		const noms = cfg.ordre.map((n) => cfg.semestres[n].nom);
		const liste = noms.slice(0, -1).join(', ') + ' et ' + noms[noms.length - 1];
		const ok = await confirmer(liste + '.\n' + (modele
			? 'Leurs dates reprennent celles de ' + modele + ', 52 semaines plus tard (mêmes jours de la semaine) : vous pourrez les ajuster.'
			: 'Leurs dates seront à fixer ensuite.'), {
			title: 'Créer les 4 sessions de ' + annee + ' ?', confirmText: 'Créer les 4 sessions', cancelText: 'Annuler', danger: false,
		});
		if (!ok) { return; }
		if (edition && !(await fermerEditeur({ sansFocus: true }))) { return; }
		occupe(bouton, true);
		try {
			const donnees = new FormData();
			donnees.set('action', 'generate_four_sessions');
			const r = await poster(donnees);
			notifier('success', r.message);
			await rafraichir({ annee: r.year || annee });
		} catch (x) {
			notifier('error', x.message);
			occupe(bouton, false);
		}
	}

	/* --- Relire la liste, et faire glisser ce qui a bouge (FLIP) ------------------------- */

	function photographier() {
		const carte = new Map();
		page.querySelectorAll('.sn-ligne[data-id]').forEach((l) => {
			if (!l.dataset.id) { return; }
			const r = l.getBoundingClientRect();
			if (!r.height) { return; }
			const b = l.querySelector('.sn-barre');
			const rb = b ? b.getBoundingClientRect() : null;
			const rp = b ? b.parentElement.getBoundingClientRect() : null;
			carte.set(l.dataset.id, { top: r.top, gauche: rb && rb.width ? rb.left - rp.left : null, largeur: rb ? rb.width : 0 });
		});
		return carte;
	}

	async function rafraichir(opts) {
		opts = opts || {};
		page.classList.remove('sn-entree'); // l'entree ne se rejoue pas sur la liste relue
		const avant = photographier();
		const connues = new Set(annees().map((s) => s.dataset.annee));
		const ouvertes = new Set(annees().filter((s) => s.classList.contains('is-ouverte')).map((s) => s.dataset.annee));
		const passeesOuvertes = !!page.querySelector('#snPassees.is-ouverte');

		let doc = null;
		try {
			const rep = await fetch(location.pathname + location.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
			const html = await rep.text();
			const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(html);
			if (m) { window.CSRF_TOKEN = m[1]; }
			doc = new DOMParser().parseFromString(html, 'text/html');
		} catch (x) { /* relu plus bas */ }
		const contenu = doc && doc.getElementById('snContenu');
		if (!contenu) { location.reload(); return; }
		document.getElementById('snContenu').replaceWith(document.adoptNode(contenu));
		const actions = doc.getElementById('snActions');
		if (actions) { document.getElementById('snActions').replaceWith(document.adoptNode(actions)); }

		annees().forEach((sec) => {
			const a = sec.dataset.annee;
			poser(sec, (opts.annee && a === opts.annee) || (connues.has(a) ? ouvertes.has(a) : sec.classList.contains('is-ouverte')));
		});
		const passees = page.querySelector('#snPassees');
		if (passees) {
			const cible = opts.annee && Array.prototype.some.call(passees.querySelectorAll('.sn-annee'), (s) => s.dataset.annee === opts.annee);
			poser(passees, passeesOuvertes || !!cible);
		}
		toutDisposer();

		const ligneEclat = opts.eclat ? page.querySelector('.sn-ligne[data-id="' + CSS.escape(opts.eclat) + '"]') : null;
		if (!reduit()) {
			page.querySelectorAll('.sn-ligne[data-id]').forEach((l) => {
				const r = l.getBoundingClientRect();
				if (!r.height) { return; }
				const a = avant.get(l.dataset.id);
				if (!a) { l.classList.add('sn-arrive'); return; }
				const dy = a.top - r.top;
				if (Math.abs(dy) > 1) {
					l.animate([{ transform: 'translateY(' + dy + 'px)' }, { transform: 'none' }], { duration: 440, easing: EASE });
				}
				const b = l.querySelector('.sn-barre');
				if (b && a.gauche !== null && b.style.width) {
					const rb = b.getBoundingClientRect();
					const gauche = rb.left - b.parentElement.getBoundingClientRect().left;
					if (Math.abs(gauche - a.gauche) > 1 || Math.abs(rb.width - a.largeur) > 1) {
						b.animate([{ left: a.gauche + 'px', width: a.largeur + 'px' }, { left: gauche + 'px', width: rb.width + 'px' }], { duration: 520, easing: EASE });
					}
				}
			});
			annees().forEach((sec) => { if (!connues.has(sec.dataset.annee)) { sec.classList.add('sn-arrive'); rejouer(sec); } });
		}
		if (ligneEclat) {
			ligneEclat.classList.add('sn-eclat');
			setTimeout(() => ligneEclat.classList.remove('sn-eclat', 'sn-arrive'), 1900);
			setTimeout(() => ligneEclat.scrollIntoView({ block: 'nearest', behavior: reduit() ? 'auto' : 'smooth' }), reduit() ? 0 : 120);
		} else if (opts.annee) {
			const sec = annees().find((s) => s.dataset.annee === opts.annee);
			if (sec) { setTimeout(() => sec.scrollIntoView({ block: 'nearest', behavior: reduit() ? 'auto' : 'smooth' }), 120); }
		}
	}

	/* --- Nouvelle session ------------------------------------------------------------------ */

	function anneeCible() {
		const liste = document.getElementById('snListe');
		return (liste && liste.querySelector(':scope > .sn-annee')) || page.querySelector('.sn-annee');
	}

	async function nouvelle(sec) {
		if (edition && !(await fermerEditeur({ sansFocus: true }))) { return; }
		sec = sec || anneeCible();
		if (!sec) { sec = anneeVide(cfg.anneeParDefaut); }
		const passees = sec.closest('.sn-passees');
		if (passees) { poser(passees, true); }
		if (!sec.classList.contains('is-ouverte')) { poser(sec, true); disposer(sec); }

		const annee = anneeValide(sec.dataset.annee) ? sec.dataset.annee : cfg.anneeParDefaut;
		const lignes = lignesDe(sec);
		const pris = new Set(lignes.map((l) => l.dataset.semestre));
		const sem = String(cfg.ordre.find((n) => !pris.has(String(n))) || cfg.ordre[0]);
		const fins = lignes.map((l) => jour(l.dataset.fin)).filter((j) => j !== null);

		const ligne = document.createElement('div');
		ligne.className = 'sn-ligne sn-ligne--nouvelle sn-arrive';
		ligne.dataset.id = '';
		ligne.dataset.nom = cfg.semestres[sem].nom;
		ligne.dataset.code = codeType(sem, annee);
		ligne.dataset.annee = annee;
		ligne.dataset.semestre = sem;
		ligne.dataset.debut = fins.length ? versYmd(Math.max.apply(null, fins) + 1) : '';
		ligne.dataset.fin = '';
		ligne.innerHTML = '<div class="sn-ligne__info"><div class="sn-ligne__nom"><span class="sn-ligne__titre"></span>'
			+ '<span class="sn-etat sn-etat--brouillon">Nouvelle</span></div><div class="sn-ligne__dates">Dates à choisir</div></div>'
			+ '<div class="sn-piste" aria-hidden="true"><span class="sn-barre"></span></div><div class="sn-ligne__actions"></div>';
		ligne.querySelector('.sn-ligne__titre').textContent = ligne.dataset.nom;
		const interieur = sec.querySelector('.sn-annee__interieur');
		const derniere = lignes[lignes.length - 1];
		if (derniere) { (derniere.nextElementSibling && derniere.nextElementSibling.classList.contains('sn-tiroir') ? derniere.nextElementSibling : derniere).after(ligne); }
		else { interieur.appendChild(ligne); }
		await ouvrirEditeur(ligne);
	}

	/** Sans aucune session : une annee vide pour y poser la premiere. */
	function anneeVide(annee) {
		const sec = document.createElement('section');
		sec.className = 'sn-annee sn-annee--avenir is-ouverte sn-arrive';
		sec.dataset.annee = annee;
		sec.innerHTML = '<div class="sn-annee__tete"><span class="sn-annee__bascule"><span class="sn-annee__titre"></span></span></div>'
			+ '<div class="sn-annee__corps"><div class="sn-annee__interieur"><div class="sn-regle" aria-hidden="true">'
			+ '<span class="sn-regle__vide"></span><div class="sn-regle__mois"></div><span class="sn-regle__vide"></span></div></div></div>';
		sec.querySelector('.sn-annee__titre').textContent = annee;
		const vide = page.querySelector('.sn-vide');
		if (vide) { vide.remove(); }
		document.getElementById('snListe').prepend(sec);
		return sec;
	}

	/* --- Evenements --------------------------------------------------------------------------- */

	page.addEventListener('click', (ev) => {
		const t = ev.target;
		if (t.closest('#snNouvelle')) { nouvelle(null); return; }
		const gen = t.closest('#snGenerer');
		if (gen) { generer(gen); return; }
		const ajouter = t.closest('.sn-annee__ajouter');
		if (ajouter) { nouvelle(ajouter.closest('.sn-annee')); return; }
		const bascule = t.closest('.sn-annee__bascule, .sn-passees__bascule');
		if (bascule) { basculer(bascule.closest('.sn-annee, .sn-passees')); return; }
		if (t.closest('[data-sn="annuler"]')) { fermerEditeur(); return; }
		if (t.closest('.sn-tiroir')) { return; }
		const modifier = t.closest('.sn-modifier');
		const ligne = t.closest('.sn-ligne');
		if (!ligne) { return; }
		if (ligne.classList.contains('is-edition')) { if (modifier) { fermerEditeur(); } return; }
		if (!modifier && String(window.getSelection ? window.getSelection() : '').length) { return; } // une selection de texte, pas un clic
		ouvrirEditeur(ligne);
	});

	page.addEventListener('input', (ev) => {
		const form = ev.target.closest('.sn-editeur');
		if (!form || !edition || edition.form !== form) { return; }
		if (ev.target.name === 'session_semester') { suivreSemestre(form); }
		apercu();
		if (form._tente) { controler(form); }
	});

	page.addEventListener('change', (ev) => {
		const form = ev.target.closest('.sn-editeur');
		if (!form || !edition || edition.form !== form) { return; }
		if (ev.target.name === 'session_year') { suivreAnnee(form); apercu(); }
		if (ev.target.name === 'session_code') { ev.target.value = ev.target.value.trim().toUpperCase(); }
		/* Une fin avant le debut se signale des la date choisie, sans attendre l'envoi. */
		const d = jour(form.elements.date_entry.value);
		const f = jour(form.elements.graduation_date.value);
		if (form._tente || (ev.target.type === 'date' && d !== null && f !== null && f < d)) { controler(form); }
	});

	page.addEventListener('submit', (ev) => {
		const form = ev.target.closest('.sn-editeur');
		if (!form) { return; }
		ev.preventDefault();
		enregistrer(form);
	});

	document.addEventListener('keydown', (ev) => {
		if (ev.key !== 'Escape' || !edition || document.querySelector('[id^="tconf-"]')) { return; }
		ev.preventDefault();
		fermerEditeur();
	});

	window.addEventListener('beforeunload', (ev) => {
		if (edition && serialiser(edition.form) !== edition.depart) { ev.preventDefault(); ev.returnValue = ''; }
	});

	if (window.ResizeObserver) {
		let attente = 0;
		new ResizeObserver(() => {
			cancelAnimationFrame(attente);
			attente = requestAnimationFrame(() => {
				annees().forEach(densite);
				if (edition) { placerPouce(edition.form); }
			});
		}).observe(page);
	}

	/* --- Depart --------------------------------------------------------------------------------- */

	toutDisposer();
	setTimeout(() => page.classList.remove('sn-entree'), 1900);
	if (cfg.flash && cfg.flash.message) {
		notifier(cfg.flash.type === 'success' ? 'success' : 'error', cfg.flash.message);
	}
})();
