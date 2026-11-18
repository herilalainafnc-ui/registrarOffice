/* =============================================================================
   Inscriptions tardives (/inscriptions/tardives) — page :
   src/inscriptions.tardives.php, styles : src/css/inscriptions.tardives.css.

   La frise de la session (inscriptions normales, tardives, hors delai,
   aujourd'hui), la phrase du jour, les chiffres, la liste filtrable, l'export.
   Registrariat : le reglage du calendrier et les exonerations (motif
   obligatoire), enregistres sans recharger. Changer de session ne recharge
   pas la page non plus (?donnees=1) ; l'adresse suit (?session_id=).
   ========================================================================== */
(function () {
	'use strict';

	const source = document.getElementById('itDonnees');
	if (!source) { return; }
	let D = JSON.parse(source.textContent);
	const page = document.getElementById('itPage');
	const $ = (id) => document.getElementById(id);
	const liste = $('itListe');
	const etats = $('itEtats');
	const formCal = $('itCalendrier');
	const dialogue = $('itExoneration');
	const formExo = $('itExoForm');
	const choixSession = $('itSession');
	const fleches = [...page.querySelectorAll('.it-pas[data-sens]')];
	const formCas = $('itCasForm');
	const listeCas = $('itCasListe');
	const rechercheCas = $('itCasRecherche');
	const resultatsCas = $('itCasResultats');
	const filtre = { etat: 'retard', q: '' };
	let exclus = [];
	let exoTrace = null;
	let casEdite = null;     // id du calendrier particulier modifie, null : nouveau
	let casEtudiant = null;  // l'etudiant choisi pour un calendrier personnel

	const echapper = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const sansAccents = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
	const ar = (n) => Number(n || 0).toLocaleString('fr-FR') + ' Ar';
	const pluriel = (n, un, plusieurs) => n + ' ' + (n > 1 ? plusieurs : un);
	const chiffre = (v) => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;

	/* ------------------------------------------------------------- dates */

	const versDate = (ymd) => { const p = String(ymd || '').slice(0, 10).split('-'); return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : null; };
	const versYmd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
	const decaler = (ymd, n) => { const d = versDate(ymd); d.setDate(d.getDate() + n); return versYmd(d); };
	const annee = new Date().getFullYear();
	const jourLong = (ymd) => { const d = versDate(ymd); return d ? d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: d.getFullYear() !== annee ? 'numeric' : undefined }) : ''; };
	const jourCourt = (ymd) => { const d = versDate(ymd); return d ? d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', year: d.getFullYear() !== annee ? 'numeric' : undefined }) : ''; };
	const jourMois = (ymd) => { const d = versDate(ymd); return d ? d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) : ''; };
	const heure = (dt) => String(dt || '').slice(11, 16);

	/** Jours ouvrables apres « apres », jusqu'a « jusqua » compris (comme data/inscriptions.tardives.php). */
	function joursOuvrables(apres, jusqua, sauf) {
		const fin = versDate(jusqua);
		const d = versDate(apres);
		const exclusSet = new Set(sauf || []);
		let n = 0;
		for (let i = 0; i < 800 && d < fin; i++) {
			d.setDate(d.getDate() + 1);
			const j = d.getDay();
			if (j !== 0 && j !== 6 && !exclusSet.has(versYmd(d))) { n++; }
		}
		return n;
	}

	/* ------------------------------------------------------------- serveur */

	async function jetonFrais() {
		const rep = await fetch(location.pathname + location.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
		const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(await rep.text());
		if (m) { window.CSRF_TOKEN = m[1]; }
	}

	/* Une requete qui n'a pas pu partir (connexion qui flanche, ou premiere
	   requete pendant l'installation de l'application) est renvoyee une fois.
	   Sans danger : chaque enregistrement fixe une valeur, il ne s'ajoute pas. */
	async function envoyer(url, options) {
		try {
			return await fetch(url, options);
		} catch (err) {
			if (err.name === 'AbortError') { throw err; }
			await new Promise((r) => setTimeout(r, 700));
			return fetch(url, options);
		}
	}

	async function poster(donnees, deuxieme) {
		donnees.set('ajax', '1');
		donnees.set('csrf_token', window.CSRF_TOKEN || '');
		donnees.set('session_id', String(D.session.id));
		const rep = await envoyer(location.pathname + location.search, {
			method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
		});
		let json = null;
		try { json = await rep.json(); } catch (x) { /* reponse sans JSON */ }
		if (json && json.code === 'csrf' && !deuxieme) { await jetonFrais(); return poster(donnees, true); }
		if (!rep.ok || !json || !json.success) {
			throw new Error((json && json.message) || 'Le serveur n’a pas répondu : vérifiez la connexion, puis réessayez.');
		}
		return json;
	}

	function notifier(message, type) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); return; }
		if (type === 'error') { window.alert(message); }
	}

	/* ------------------------------------------------------------- frise */

	function dessinerFrise() {
		const frise = $('itFrise');
		const c = D.calendrier;
		frise.hidden = !c;
		if (!c) { frise.innerHTML = ''; return; }
		const debutSession = D.session.debut && D.session.debut <= c.fin_normale ? D.session.debut : decaler(c.fin_normale, -4);
		const debut = debutSession;
		const fin = decaler(c.limite_tardive, 7);
		const total = Math.max(1, (versDate(fin) - versDate(debut)) / 864e5 + 1);
		const x = (ymd) => Math.max(0, Math.min(100, ((versDate(ymd) - versDate(debut)) / 864e5) / total * 100));
		const xFin = (ymd) => Math.max(0, Math.min(100, ((versDate(ymd) - versDate(debut)) / 864e5 + 1) / total * 100));
		const tardifs = joursOuvrables(c.fin_normale, c.limite_tardive, c.exclus);
		const seg = (cls, a, b) => '<span class="it-frise__seg it-frise__seg--' + cls + '" style="left:' + x(a).toFixed(2) + '%;width:' + (xFin(b) - x(a)).toFixed(2) + '%"></span>';
		const libelle = (cls, titre, texte, a, b) => '<span class="it-frise__libelle it-frise__libelle--' + cls + '" style="left:' + ((x(a) + xFin(b)) / 2).toFixed(2) + '%"><b>' + titre + '</b>' + texte + '</span>';
		const date = (ymd, xp) => '<span class="it-frise__date" style="left:' + xp.toFixed(2) + '%">' + jourMois(ymd) + '</span>';
		let html = '<span class="it-frise__piste"></span>'
			+ seg('normale', debut, c.fin_normale)
			+ seg('tardive', decaler(c.fin_normale, 1), c.limite_tardive)
			+ seg('hors', decaler(c.limite_tardive, 1), fin)
			+ libelle('normale', 'Inscriptions', 'sans pénalité', debut, c.fin_normale)
			+ libelle('tardive', 'Tardives', pluriel(tardifs, 'jour ouvrable', 'jours ouvrables'), decaler(c.fin_normale, 1), c.limite_tardive)
			+ libelle('hors', 'Hors délai', '', decaler(c.limite_tardive, 1), fin)
			+ date(debut, 0.5) + date(c.fin_normale, xFin(c.fin_normale)) + date(c.limite_tardive, xFin(c.limite_tardive));
		if (D.aujourdhui >= debut && D.aujourdhui <= fin) {
			html += '<span class="it-frise__auj" style="left:' + ((x(D.aujourdhui) + xFin(D.aujourdhui)) / 2).toFixed(2) + '%" title="Aujourd’hui"></span>';
		}
		frise.innerHTML = html;
	}

	/* La phrase du jour : ou en est-on, et ce que coute une inscription aujourd'hui. */
	function phraseDuJour() {
		const zone = $('itAujourdhui');
		const c = D.calendrier;
		const a = D.apercu;
		if (!c) {
			zone.innerHTML = 'Pas encore de calendrier des inscriptions pour cette session : '
				+ (D.modifier ? 'réglez-le pour que le guichet calcule les retards.' : 'le registrariat doit le régler.');
			return;
		}
		const auj = '<b>Aujourd’hui, ' + echapper(jourLong(D.aujourdhui)) + '</b> : ';
		if (D.aujourdhui > c.limite_tardive) {
			zone.innerHTML = auj + '<span class="is-danger">hors délai</span> depuis le ' + echapper(jourLong(decaler(c.limite_tardive, 1)))
				+ '. Une inscription aujourd’hui est marquée hors délai, avec <b>' + ar(a.penalite) + '</b> de pénalité (' + pluriel(a.jours, 'jour ouvrable', 'jours ouvrables') + ').';
		} else if (D.aujourdhui > c.fin_normale && a.jours === 0) {
			// fin de semaine (ou jour non compte) apres la cloture : le retard ne compte pas encore
			zone.innerHTML = auj + 'inscriptions closes depuis le ' + echapper(jourLong(c.fin_normale)) + '. Une inscription aujourd’hui est encore sans pénalité ; '
				+ '<span class="is-alerte">le retard compte à partir du ' + echapper(jourLong(premierJourCompte(c.fin_normale, c.exclus))) + '</span>.';
		} else if (D.aujourdhui > c.fin_normale) {
			const total = joursOuvrables(c.fin_normale, c.limite_tardive, c.exclus);
			zone.innerHTML = auj + '<span class="is-alerte">inscriptions tardives, jour ' + a.jours + ' sur ' + total + '</span>. '
				+ 'Une inscription aujourd’hui : <b>' + ar(a.penalite) + '</b> de pénalité (aucune pour un nouvel étudiant). Dernier jour : ' + echapper(jourLong(c.limite_tardive)) + '.';
		} else if (D.session.debut && D.aujourdhui < D.session.debut && D.session.debut <= c.fin_normale) {
			zone.innerHTML = 'Les inscriptions ouvrent le <b>' + echapper(jourLong(D.session.debut)) + '</b> et se font sans pénalité jusqu’au <b>' + echapper(jourLong(c.fin_normale)) + '</b>.';
		} else {
			const reste = joursOuvrables(decaler(D.aujourdhui, -1), c.fin_normale, c.exclus);
			zone.innerHTML = auj + 'inscriptions sans pénalité jusqu’au <b>' + echapper(jourLong(c.fin_normale)) + '</b>'
				+ (reste ? ' (encore ' + pluriel(reste, 'jour ouvrable', 'jours ouvrables') + ', aujourd’hui compris)' : '') + '.';
		}
	}

	/* ------------------------------------------------------- calendrier */

	function remplirCalendrier() {
		const c = D.calendrier || D.proposition;
		formCal.elements.fin_normale.value = c.fin_normale || '';
		formCal.elements.limite_tardive.value = c.limite_tardive || '';
		formCal.elements.penalite_jour.value = Number(c.penalite_jour || 0).toLocaleString('fr-FR');
		exclus = (D.calendrier && D.calendrier.exclus) ? D.calendrier.exclus.slice() : [];
		dessinerExclus();
		erreurCalendrier('');
		$('itCalendrierTrace').textContent = D.calendrier && D.calendrier.modifie_le
			? 'Réglé' + (D.calendrier.modifie_par ? ' par ' + D.calendrier.modifie_par : '') + ' le ' + jourCourt(D.calendrier.modifie_le) + ' à ' + heure(D.calendrier.modifie_le) + '.'
			: (D.calendrier ? '' : 'Proposition : la première semaine de la session, puis deux semaines de retard.');
		const ouvrir = $('itReglerOuvrir');
		ouvrir.hidden = !D.modifier;
		ouvrir.querySelector('span').textContent = D.calendrier ? 'Régler le calendrier' : 'Créer le calendrier';
	}

	function dessinerExclus() {
		$('itExclus').innerHTML = exclus.map((j) => '<span class="it-exclus__puce">' + echapper(jourCourt(j))
			+ '<button type="button" data-retirer="' + j + '" aria-label="Compter le ' + echapper(jourLong(j)) + '"><i class="bi bi-x" aria-hidden="true"></i></button></span>').join('')
			|| '<span class="it-calendrier__trace">Aucun : tous les jours ouvrables comptent.</span>';
	}

	function erreurCalendrier(message, champ) {
		const z = $('itCalendrierErreur');
		z.hidden = !message;
		z.textContent = message || '';
		formCal.querySelectorAll('.it-champ').forEach((c) => c.classList.remove('is-erreur'));
		if (champ) { formCal.elements[champ].closest('.it-champ').classList.add('is-erreur'); formCal.elements[champ].focus(); }
	}

	function ouvrirReglage(oui) {
		formCal.hidden = !oui;
		$('itReglerOuvrir').setAttribute('aria-expanded', String(oui));
		if (oui) { remplirCalendrier(); formCal.elements.fin_normale.focus(); }
	}

	/* ------------------------------------------- calendriers particuliers */

	/* Une mention, un niveau ou un etudiant qui commence plus tard (stage...)
	   a ses propres dates ; la penalite par jour et les jours non comptes
	   restent ceux de la session (data/inscriptions.tardives.php, itCas). */

	const niveauLibelle = (n) => (D.niveaux && D.niveaux[n] != null ? D.niveaux[n] : 'Niveau ' + n);
	const nomEtudiant = (e) => '<em>' + echapper((e.nom || '').toUpperCase()) + '</em> ' + echapper(e.prenom || '');

	/** Le premier jour ouvrable apres « ymd » : celui ou le retard commence. */
	function premierJourCompte(ymd, sauf) {
		const s = new Set(sauf || []);
		let j = ymd;
		for (let i = 0; i < 30; i++) {
			j = decaler(j, 1);
			const d = versDate(j).getDay();
			if (d !== 0 && d !== 6 && !s.has(j)) { return j; }
		}
		return decaler(ymd, 1);
	}

	function itemCas(c) {
		const qui = c.pour === 'etudiant' && c.etudiant
			? nomEtudiant(c.etudiant) + ' <code>' + echapper(c.etudiant.id) + '</code>'
			: echapper(c.libelle);
		const suivi = c.inscrits > 0 ? pluriel(c.inscrits, 'inscrit le suit', 'inscrits le suivent') : 'aucun inscrit pour l’instant';
		const regle = c.modifie_le ? 'réglé' + (c.modifie_par ? ' par ' + echapper(c.modifie_par) : '') + ' le ' + echapper(jourCourt(c.modifie_le)) : '';
		return '<li class="it-cas__item" data-cas="' + c.id + '">'
			+ '<span class="it-cas__icone" aria-hidden="true"><i class="bi ' + (c.pour === 'etudiant' ? 'bi-person' : 'bi-people') + '"></i></span>'
			+ '<div class="it-cas__texte"><span class="it-cas__qui">' + qui + '</span>'
			+ '<span class="it-cas__dates">Sans pénalité jusqu’au <b>' + echapper(jourCourt(c.fin_normale)) + '</b>'
			+ '<span aria-hidden="true"> · </span>tardives jusqu’au <b>' + echapper(jourCourt(c.limite_tardive)) + '</b></span>'
			+ '<span class="it-cas__meta">« ' + echapper(c.motif) + ' » · ' + suivi + (regle ? ' · ' + regle : '') + '</span></div>'
			+ (D.modifier ? '<button type="button" class="it-bouton it-bouton--discret it-bouton--petit" data-cas-modifier="' + c.id + '">Modifier</button>' : '')
			+ '</li>';
	}

	function dessinerCas() {
		const cas = D.cas || [];
		$('itCasBloc').hidden = !D.calendrier && !cas.length;
		$('itCasNombre').textContent = cas.length ? String(cas.length) : '';
		$('itCasAjouter').hidden = !D.modifier || !D.casTable || !D.calendrier;
		if (!D.casTable) {
			listeCas.innerHTML = '<li class="it-cas__vide">La table des calendriers particuliers n’existe pas encore sur ce serveur : appliquez <code>data/migration_inscriptions_tardives_cas.sql</code>.</li>';
			return;
		}
		listeCas.innerHTML = cas.length ? cas.map(itemCas).join('')
			: '<li class="it-cas__vide">Aucun : toute la session suit le calendrier ci-dessus.</li>';
	}

	function erreurCas(message, champ) {
		const z = $('itCasErreur');
		z.hidden = !message;
		z.textContent = message || '';
		formCas.querySelectorAll('.it-champ').forEach((c) => c.classList.remove('is-erreur'));
		if (champ) {
			const el = champ === 'recherche' ? rechercheCas : formCas.elements[champ];
			el.closest('.it-champ').classList.add('is-erreur');
			el.focus();
		}
	}

	function majPour() {
		const pour = formCas.elements.pour.value;
		formCas.querySelectorAll('[data-pour]').forEach((z) => { z.hidden = z.dataset.pour !== pour; });
	}

	function majChoisi() {
		const zone = $('itCasChoisi');
		formCas.elements.student_id.value = casEtudiant ? casEtudiant.id : '';
		$('itCasRechercheBloc').hidden = !!casEtudiant;
		zone.hidden = !casEtudiant;
		if (casEtudiant) {
			const precision = [casEtudiant.mention, casEtudiant.niveau].filter(Boolean).map(echapper).join(' · ');
			zone.innerHTML = '<i class="bi bi-person-check" aria-hidden="true"></i><span>' + nomEtudiant(casEtudiant) + ' <code>' + echapper(casEtudiant.id) + '</code>'
				+ (precision ? '<small>' + precision + '</small>' : '') + '</span>'
				+ '<button type="button" class="it-lien" id="itCasChanger"><span>Changer</span></button>';
		}
	}

	/** Ce que donnent les dates saisies : le premier jour de retard, la duree des tardives. */
	function calculCas() {
		const f = formCas.elements;
		const zone = $('itCasCalcul');
		const c = D.calendrier;
		if (!c || !f.fin_normale.value || !f.limite_tardive.value || f.limite_tardive.value < f.fin_normale.value) { zone.textContent = ''; return; }
		const n = joursOuvrables(f.fin_normale.value, f.limite_tardive.value, c.exclus);
		zone.innerHTML = 'Retard compté à partir du <b>' + echapper(jourLong(premierJourCompte(f.fin_normale.value, c.exclus))) + '</b>&nbsp;: '
			+ pluriel(n, 'jour ouvrable', 'jours ouvrables') + ' d’inscriptions tardives, jusqu’à <b>' + ar(n * c.penalite_jour) + '</b> de pénalité le dernier jour.';
	}

	function ouvrirCas(c) {
		const f = formCas.elements;
		casEdite = c ? c.id : null;
		casEtudiant = c && c.etudiant ? Object.assign({}, c.etudiant) : null;
		f.pour.value = c ? c.pour : 'groupe';
		f.mention.value = c && c.mention ? c.mention : '';
		f.niveau.value = c && c.niveau != null ? String(c.niveau) : '';
		f.fin_normale.value = c ? c.fin_normale : (D.calendrier ? D.calendrier.fin_normale : '');
		f.limite_tardive.value = c ? c.limite_tardive : (D.calendrier ? D.calendrier.limite_tardive : '');
		f.motif.value = c ? c.motif : '';
		rechercheCas.value = '';
		fermerResultats();
		majPour();
		majChoisi();
		calculCas();
		erreurCas('');
		$('itCasRetirer').hidden = !c;
		$('itCasValider').lastChild.textContent = c ? 'Enregistrer' : 'Ajouter';
		formCas.hidden = false;
		$('itCasAjouter').setAttribute('aria-expanded', 'true');
		listeCas.querySelectorAll('.it-cas__item').forEach((li) => li.classList.toggle('is-edite', !!c && li.dataset.cas === String(c.id)));
		(c ? f.fin_normale : f.mention).focus();
	}

	function fermerCas() {
		formCas.hidden = true;
		casEdite = null;
		casEtudiant = null;
		fermerResultats();
		$('itCasAjouter').setAttribute('aria-expanded', 'false');
		listeCas.querySelectorAll('.is-edite').forEach((li) => li.classList.remove('is-edite'));
	}

	/* --- l'etudiant d'un calendrier personnel : chercher par le nom ou le matricule */

	let trouves = [];
	let actif = -1;
	let attenteCas = 0;
	let demandeCas = null;

	function fermerResultats() {
		resultatsCas.hidden = true;
		resultatsCas.innerHTML = '';
		rechercheCas.setAttribute('aria-expanded', 'false');
		rechercheCas.removeAttribute('aria-activedescendant');
		trouves = [];
		actif = -1;
	}

	function montrerResultats() {
		if (!trouves.length) {
			resultatsCas.innerHTML = '<li class="it-cas__aucun" role="presentation">Aucun étudiant trouvé.</li>';
		} else {
			resultatsCas.innerHTML = trouves.map((e, i) => '<li role="option" id="itCasR' + i + '" data-i="' + i + '" aria-selected="' + (i === actif) + '"' + (i === actif ? ' class="is-actif"' : '') + '>'
				+ '<span>' + nomEtudiant(e) + ' <code>' + echapper(e.id) + '</code></span>'
				+ '<small>' + [e.mention, e.niveau].filter(Boolean).map(echapper).join(' · ') + '</small></li>').join('');
		}
		resultatsCas.hidden = false;
		rechercheCas.setAttribute('aria-expanded', 'true');
		if (actif >= 0) { rechercheCas.setAttribute('aria-activedescendant', 'itCasR' + actif); } else { rechercheCas.removeAttribute('aria-activedescendant'); }
	}

	async function chercherEtudiants(q) {
		if (demandeCas) { demandeCas.abort(); }
		if (q.length < 2) { fermerResultats(); return; }
		const controle = new AbortController();
		demandeCas = controle;
		try {
			const rep = await fetch(location.pathname + '?chercher=' + encodeURIComponent(q), {
				credentials: 'same-origin', cache: 'no-store', signal: controle.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
			});
			const json = await rep.json();
			if (!rep.ok || !json.success) { throw new Error(json.message || 'Recherche impossible.'); }
			trouves = json.etudiants || [];
			actif = trouves.length ? 0 : -1;
			montrerResultats();
		} catch (err) {
			if (err.name !== 'AbortError') { erreurCas(err.message); }
		} finally {
			if (demandeCas === controle) { demandeCas = null; }
		}
	}

	function choisirEtudiant(e) {
		casEtudiant = e;
		fermerResultats();
		majChoisi();
		erreurCas('');
		formCas.elements.fin_normale.focus();
	}

	/* ---------------------------------------------------------- chiffres */

	function dessinerChiffres() {
		const ins = D.inscriptions;
		const retard = ins.filter((e) => e.jours > 0 && !e.hors_delai).length;
		const hors = ins.filter((e) => e.hors_delai).length;
		const somme = (cle) => ins.reduce((t, e) => t + (e[cle] || 0), 0);
		const carte = (cls, valeur, libelle) => '<div class="it-chiffre' + (cls ? ' it-chiffre--' + cls : '') + '"><b>' + valeur + '</b><span>' + libelle + '</span></div>';
		$('itChiffres').innerHTML = carte('', ins.length, 'inscrits')
			+ carte('', ins.length - retard - hors, 'sans retard')
			+ carte('retard', retard, 'en retard')
			+ carte('hors', hors, 'hors délai')
			+ carte('argent', ar(somme('penalite')), 'pénalités')
			+ carte('argent', ar(somme('exoneration')), 'exonérées')
			+ carte('argent', ar(somme('du')), 'dues');
	}

	/* -------------------------------------------------------------- liste */

	function correspond(e) {
		if (filtre.etat === 'retard' && !(e.jours > 0)) { return false; }
		if (filtre.etat === 'hors' && !e.hors_delai) { return false; }
		if (filtre.etat === 'exoneres' && !(e.exoneration > 0)) { return false; }
		if (filtre.q) {
			const texte = sansAccents([e.nom, e.prenom, e.id, e.agent, e.mention].join(' '));
			if (!filtre.q.split(' ').every((m) => texte.includes(m))) { return false; }
		}
		return true;
	}

	function ligne(e) {
		const nom = '<em>' + echapper((e.nom || '').toUpperCase()) + '</em> ' + echapper(e.prenom);
		const meta = ['<code>' + echapper(e.id) + '</code>', echapper(e.niveau), echapper(e.mention), e.agent ? 'par ' + echapper(e.agent) : ''].filter(Boolean).join(' · ')
			+ (e.nouveau ? ' <span class="it-tag-nouveau" title="Nouvel étudiant : jamais pénalisé pour une inscription tardive">nouveau</span>' : '')
			+ (e.cas ? ' <span class="it-tag-cas" title="Calendrier particulier : sans pénalité jusqu’au ' + echapper(jourLong(e.cas.fin_normale)) + '">'
				+ '<i class="bi bi-calendar2-range" aria-hidden="true"></i>' + (e.cas.personnel ? 'calendrier personnel' : echapper(e.cas.libelle)) + '</span>' : '');
		// nouvel etudiant inscrit apres la cloture : sans penalite, et on dit pourquoi
		const cloture = e.cas ? e.cas.fin_normale : (D.calendrier ? D.calendrier.fin_normale : null);
		const nouveauApres = e.nouveau && !(e.jours > 0) && cloture && String(e.le || '').slice(0, 10) > cloture;
		const quand = e.origine === 'sans-trace' || e.origine === 'reprise'
			? '<b>' + echapper(jourCourt(e.le)) + '</b><em>avant le suivi</em>'
			: '<b>' + echapper(jourCourt(e.le)) + '</b><span>' + echapper(heure(e.le)) + '</span>';
		const badge = e.hors_delai ? '<span class="it-badge it-badge--hors">hors délai · ' + e.jours + ' j</span>'
			: e.jours > 0 ? '<span class="it-badge it-badge--tardive">' + pluriel(e.jours, 'jour', 'jours') + ' de retard</span>'
			: nouveauApres ? '<span class="it-badge it-badge--nouveau">nouveau, sans pénalité</span>'
			: e.origine === 'guichet' ? '<span class="it-badge it-badge--ok">dans les temps</span>'
			: '<span class="it-badge it-badge--neutre">dans les temps</span>';
		const argent = e.penalite > 0
			? (e.exoneration > 0 ? '<s>' + ar(e.penalite) + '</s><b>' + ar(e.du) + '</b><span>− ' + ar(e.exoneration) + ' exonérés</span>' : '<b>' + ar(e.penalite) + '</b>')
			: '<b>—</b>';
		const action = D.modifier && e.penalite > 0 && e.trace
			? '<button type="button" class="it-bouton it-bouton--discret it-bouton--petit" data-exonerer="' + e.trace + '">' + (e.exoneration > 0 ? 'Modifier' : 'Exonérer') + '</button>' : '';
		const exo = e.exoneration > 0
			? '<p class="it-exo"><i class="bi bi-patch-check" aria-hidden="true"></i>' + (e.exoneration >= e.penalite ? 'Exonéré en entier' : 'Exonéré de ' + ar(e.exoneration))
				+ (e.exonere_par ? ' par ' + echapper(e.exonere_par) : '') + (e.exonere_le ? ' le ' + echapper(jourCourt(e.exonere_le)) + ' à ' + echapper(heure(e.exonere_le)) : '')
				+ (e.motif ? ' : « ' + echapper(e.motif) + ' »' : '') + '</p>' : '';
		return '<li class="it-ligne" data-trace="' + (e.trace || '') + '"><div class="it-quand">' + quand + '</div>'
			+ '<div class="it-qui"><span class="it-qui__nom">' + nom + '</span><span class="it-qui__meta">' + meta + '</span></div>'
			+ badge + '<div class="it-argent">' + argent + '</div><div class="it-action">' + action + '</div>' + exo + '</li>';
	}

	function dessinerListe() {
		const ins = D.inscriptions;
		const compte = { retard: ins.filter((e) => e.jours > 0).length, hors: ins.filter((e) => e.hors_delai).length,
			exoneres: ins.filter((e) => e.exoneration > 0).length, tout: ins.length };
		etats.querySelectorAll('.it-etat').forEach((b) => {
			b.querySelector('b').textContent = compte[b.dataset.etat];
			const oui = b.dataset.etat === filtre.etat;
			b.classList.toggle('is-actif', oui);
			b.setAttribute('aria-pressed', String(oui));
		});
		const vus = ins.filter(correspond);
		liste.innerHTML = vus.map(ligne).join('');
		const aucun = $('itAucun');
		aucun.hidden = vus.length > 0;
		if (!vus.length) {
			const c = D.calendrier;
			const titre = filtre.q ? 'Aucune inscription ne correspond' : filtre.etat === 'retard' ? 'Aucune inscription en retard'
				: filtre.etat === 'hors' ? 'Aucune inscription hors délai' : filtre.etat === 'exoneres' ? 'Aucune exonération' : 'Aucune inscription à cette session';
			let texte = filtre.q ? 'Changez la recherche.' : '';
			if (!filtre.q && filtre.etat === 'retard' && c) {
				texte = D.aujourdhui <= c.fin_normale
					? 'Les inscriptions se font sans pénalité jusqu’au ' + jourLong(c.fin_normale) + '. Les inscriptions faites ensuite apparaîtront ici, avec leur retard et leur pénalité.'
					: 'Toutes les inscriptions de la session ont été faites dans les temps.';
			}
			$('itAucunTitre').textContent = titre;
			$('itAucunTexte').textContent = texte;
		}
	}

	/* --------------------------------------------------------- l'ensemble */

	function afficher(etat) {
		D = Object.assign(D, etat);
		$('itSessionTexte').innerHTML = echapper(D.session.nom) + ' <span>' + echapper(D.session.annee) + '</span>';
		if (choixSession) { choixSession.value = String(D.session.id); }
		fleches.forEach((a) => {
			const v = a.dataset.sens === 'precedente' ? D.precedente : D.suivante;
			a.classList.toggle('is-inactif', !v);
			if (v) { a.href = '?session_id=' + v.id; a.dataset.session = v.id; a.removeAttribute('aria-disabled'); }
			else { a.removeAttribute('href'); delete a.dataset.session; a.setAttribute('aria-disabled', 'true'); }
			a.setAttribute('aria-label', (a.dataset.sens === 'precedente' ? 'Session précédente' : 'Session suivante') + (v ? ' : ' + v.libelle : ''));
		});
		dessinerFrise();
		phraseDuJour();
		remplirCalendrier();
		dessinerCas();
		dessinerChiffres();
		dessinerListe();
		if (!formCal.hidden) { ouvrirReglage(true); }
	}

	/* ----------------------------------------------------- changer de session */

	let demande = null;
	async function charger(id, pousser) {
		id = String(id || '');
		if (!id || id === String(D.session.id)) { if (choixSession) { choixSession.value = String(D.session.id); } return; }
		if (demande) { demande.abort(); }
		const controle = new AbortController();
		demande = controle;
		page.classList.add('is-chargement');
		page.setAttribute('aria-busy', 'true');
		try {
			const rep = await fetch(location.pathname + '?session_id=' + encodeURIComponent(id) + '&donnees=1', {
				credentials: 'same-origin', cache: 'no-store', signal: controle.signal, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
			});
			let json = null;
			try { json = await rep.json(); } catch (x) { /* reponse sans JSON */ }
			if (!rep.ok || !json || !json.success) { throw new Error((json && json.message) || 'La session n’a pas pu être chargée : réessayez.'); }
			formCal.hidden = true;
			fermerCas();
			afficher(json);
			if (pousser) { history.pushState({ itSession: json.session.id }, '', location.pathname + '?session_id=' + json.session.id); }
		} catch (err) {
			if (err.name === 'AbortError') { return; }
			notifier(err.message, 'error');
			if (choixSession) { choixSession.value = String(D.session.id); }
		} finally {
			if (demande === controle) { demande = null; page.classList.remove('is-chargement'); page.removeAttribute('aria-busy'); }
		}
	}

	/* ------------------------------------------------------- exoneration */

	function ouvrirExoneration(traceId) {
		const e = D.inscriptions.find((x) => String(x.trace) === String(traceId));
		if (!e) { return; }
		exoTrace = e;
		$('itExoQui').innerHTML = '<b>' + echapper((e.nom || '').toUpperCase() + ' ' + e.prenom) + '</b> · ' + pluriel(e.jours, 'jour', 'jours') + ' de retard · pénalité de ' + ar(e.penalite);
		const partielle = e.exoneration > 0 && e.exoneration < e.penalite;
		formExo.elements.part.value = partielle ? 'partielle' : 'totale';
		formExo.elements.montant.value = partielle ? Number(e.exoneration).toLocaleString('fr-FR') : '';
		formExo.elements.motif.value = e.motif || '';
		$('itExoMontantBloc').hidden = !partielle;
		$('itExoRetirer').hidden = !(e.exoneration > 0);
		$('itExoValider').lastChild.textContent = e.exoneration > 0 ? 'Enregistrer' : 'Exonérer';
		erreurExo('');
		dialogue.showModal();
		formExo.elements.motif.focus();
	}

	function erreurExo(message) {
		const z = $('itExoErreur');
		z.hidden = !message;
		z.textContent = message || '';
	}

	async function exonerer(montant, motif) {
		const boutons = dialogue.querySelectorAll('button');
		boutons.forEach((b) => { b.disabled = true; });
		try {
			const donnees = new FormData();
			donnees.set('action', 'exonerer');
			donnees.set('trace', String(exoTrace.trace));
			donnees.set('montant', String(montant));
			donnees.set('motif', motif);
			const rep = await poster(donnees);
			const trace = exoTrace.trace;
			dialogue.close();
			afficher(rep);
			const li = liste.querySelector('.it-ligne[data-trace="' + trace + '"]');
			if (li) { li.classList.add('is-eclat'); }
			notifier(rep.message, 'success');
		} catch (err) {
			erreurExo(err.message);
		} finally {
			boutons.forEach((b) => { b.disabled = false; });
		}
	}

	/* ------------------------------------------------------------- export */

	function exporter() {
		const vus = D.inscriptions.filter(correspond);
		const cellule = (v) => { const s = String(v == null ? '' : v); return /[";\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
		const lignes = [['Matricule', 'Nom', 'Prénom', 'Niveau', 'Mention', 'Inscrit le', 'Heure', 'Agent', 'Nouvel étudiant', 'Calendrier', 'Jours ouvrables de retard', 'Hors délai', 'Pénalité (Ar)', 'Exonération (Ar)', 'Motif', 'Exonéré par', 'Exonéré le', 'Dû (Ar)']]
			.concat(vus.map((e) => [e.id, e.nom, e.prenom, e.niveau, e.mention, String(e.le || '').slice(0, 10), e.origine === 'guichet' ? heure(e.le) : '', e.agent,
				e.nouveau ? 'oui' : 'non', e.cas ? (e.cas.personnel ? 'personnel' : e.cas.libelle) : 'session',
				e.jours, e.hors_delai ? 'oui' : 'non', e.penalite, e.exoneration, e.motif, e.exonere_par, e.exonere_le || '', e.du]));
		const csv = '﻿' + lignes.map((l) => l.map(cellule).join(';')).join('\r\n');
		const a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
		a.download = 'inscriptions-tardives-' + sansAccents(D.session.libelle).replace(/[^a-z0-9]+/g, '-') + '.csv';
		document.body.appendChild(a);
		a.click();
		setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
		notifier(pluriel(vus.length, 'ligne exportée', 'lignes exportées') + '.', 'success');
	}

	/* ---------------------------------------------------------- ecouteurs */

	etats.addEventListener('click', (ev) => {
		const b = ev.target.closest('.it-etat');
		if (!b) { return; }
		filtre.etat = b.dataset.etat;
		dessinerListe();
	});

	let attente = 0;
	$('itRecherche').addEventListener('input', (ev) => {
		clearTimeout(attente);
		attente = setTimeout(() => { filtre.q = sansAccents(ev.target.value).trim().replace(/\s+/g, ' '); dessinerListe(); }, 120);
	});

	$('itExporter').addEventListener('click', exporter);

	liste.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-exonerer]');
		if (b) { ouvrirExoneration(b.dataset.exonerer); }
	});

	formExo.addEventListener('change', (ev) => {
		if (ev.target.name === 'part') {
			$('itExoMontantBloc').hidden = ev.target.value !== 'partielle';
			if (ev.target.value === 'partielle') { formExo.elements.montant.focus(); }
		}
	});
	formExo.elements.montant.addEventListener('input', (ev) => {
		const n = chiffre(ev.target.value);
		ev.target.value = n ? n.toLocaleString('fr-FR') : '';
	});
	formExo.addEventListener('submit', (ev) => {
		ev.preventDefault();
		const partielle = formExo.elements.part.value === 'partielle';
		const montant = partielle ? chiffre(formExo.elements.montant.value) : exoTrace.penalite;
		const motif = formExo.elements.motif.value.trim();
		if (partielle && (montant <= 0 || montant > exoTrace.penalite)) {
			erreurExo('Le montant exonéré doit être compris entre 1 et ' + ar(exoTrace.penalite) + '.');
			formExo.elements.montant.focus();
			return;
		}
		if (motif.length < 5) {
			erreurExo('Donnez le motif de l’exonération : il reste visible sur cette page.');
			formExo.elements.motif.focus();
			return;
		}
		exonerer(montant, motif);
	});
	$('itExoRetirer').addEventListener('click', () => exonerer(0, ''));
	$('itExoAnnuler').addEventListener('click', () => dialogue.close());

	$('itReglerOuvrir').addEventListener('click', () => ouvrirReglage(formCal.hidden));
	$('itReglerFermer').addEventListener('click', () => ouvrirReglage(false));
	formCal.elements.penalite_jour.addEventListener('input', (ev) => {
		const n = chiffre(ev.target.value);
		ev.target.value = ev.target.value.trim() === '' ? '' : n.toLocaleString('fr-FR');
	});
	$('itExclusAjouter').addEventListener('click', () => {
		const v = $('itExclusDate').value;
		if (!v) { $('itExclusDate').focus(); return; }
		if (!exclus.includes(v)) { exclus.push(v); exclus.sort(); }
		$('itExclusDate').value = '';
		dessinerExclus();
	});
	$('itExclus').addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-retirer]');
		if (!b) { return; }
		exclus = exclus.filter((j) => j !== b.dataset.retirer);
		dessinerExclus();
	});
	formCal.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		const f = formCal.elements;
		if (!f.fin_normale.value) { erreurCalendrier('Indiquez le dernier jour des inscriptions normales.', 'fin_normale'); return; }
		if (!f.limite_tardive.value) { erreurCalendrier('Indiquez la date limite des inscriptions tardives.', 'limite_tardive'); return; }
		if (f.limite_tardive.value < f.fin_normale.value) { erreurCalendrier('La date limite des inscriptions tardives doit suivre la fin des inscriptions normales.', 'limite_tardive'); return; }
		const bouton = $('itCalendrierValider');
		bouton.disabled = true;
		try {
			const donnees = new FormData();
			donnees.set('action', 'calendrier');
			donnees.set('fin_normale', f.fin_normale.value);
			donnees.set('limite_tardive', f.limite_tardive.value);
			donnees.set('penalite_jour', String(chiffre(f.penalite_jour.value)));
			donnees.set('exclus', exclus.join(','));
			const rep = await poster(donnees);
			formCal.hidden = true;
			$('itReglerOuvrir').setAttribute('aria-expanded', 'false');
			afficher(rep);
			notifier(rep.message, 'success');
		} catch (err) {
			erreurCalendrier(err.message);
		} finally {
			bouton.disabled = false;
		}
	});

	/* Calendriers particuliers */
	$('itCasAjouter').addEventListener('click', () => { if (formCas.hidden || casEdite) { ouvrirCas(null); } else { fermerCas(); } });
	listeCas.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-cas-modifier]');
		if (!b) { return; }
		const c = (D.cas || []).find((x) => String(x.id) === b.dataset.casModifier);
		if (c) { ouvrirCas(c); }
	});
	formCas.addEventListener('change', (ev) => {
		if (ev.target.name === 'pour') { majPour(); erreurCas(''); (ev.target.value === 'etudiant' && !casEtudiant ? rechercheCas : formCas.elements.mention).focus(); }
		if (ev.target.name === 'fin_normale' || ev.target.name === 'limite_tardive') { calculCas(); }
	});
	formCas.elements.fin_normale.addEventListener('input', calculCas);
	formCas.elements.limite_tardive.addEventListener('input', calculCas);
	rechercheCas.addEventListener('input', () => {
		clearTimeout(attenteCas);
		const q = rechercheCas.value.trim();
		attenteCas = setTimeout(() => chercherEtudiants(q), 220);
	});
	rechercheCas.addEventListener('keydown', (ev) => {
		if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
			if (!trouves.length) { return; }
			ev.preventDefault();
			actif = (actif + (ev.key === 'ArrowDown' ? 1 : -1) + trouves.length) % trouves.length;
			montrerResultats();
		} else if (ev.key === 'Enter') {
			ev.preventDefault();
			if (actif >= 0 && trouves[actif]) { choisirEtudiant(trouves[actif]); }
		} else if (ev.key === 'Escape' && !resultatsCas.hidden) {
			ev.preventDefault();
			fermerResultats();
		}
	});
	rechercheCas.addEventListener('blur', () => setTimeout(fermerResultats, 150));
	resultatsCas.addEventListener('mousedown', (ev) => {
		const li = ev.target.closest('[data-i]');
		if (!li) { return; }
		ev.preventDefault();
		choisirEtudiant(trouves[+li.dataset.i]);
	});
	$('itCasChoisi').addEventListener('click', (ev) => {
		if (!ev.target.closest('#itCasChanger')) { return; }
		casEtudiant = null;
		majChoisi();
		rechercheCas.focus();
	});
	$('itCasAnnuler').addEventListener('click', fermerCas);
	formCas.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		const f = formCas.elements;
		const pour = f.pour.value;
		if (pour === 'etudiant' && !casEtudiant) { erreurCas('Choisissez l’étudiant : tapez quelques lettres de son nom ou son matricule.', 'recherche'); return; }
		if (pour === 'groupe' && !f.mention.value && f.niveau.value === '') { erreurCas('Choisissez une mention, un niveau, ou les deux.', 'mention'); return; }
		if (!f.fin_normale.value) { erreurCas('Indiquez le dernier jour sans pénalité.', 'fin_normale'); return; }
		if (!f.limite_tardive.value) { erreurCas('Indiquez la date limite des inscriptions tardives.', 'limite_tardive'); return; }
		if (f.limite_tardive.value < f.fin_normale.value) { erreurCas('La date limite des inscriptions tardives doit suivre le dernier jour sans pénalité.', 'limite_tardive'); return; }
		if (f.motif.value.trim().length < 5) { erreurCas('Donnez le motif : il reste visible sur cette page.', 'motif'); return; }
		const bouton = $('itCasValider');
		bouton.disabled = true;
		try {
			const donnees = new FormData();
			donnees.set('action', 'cas');
			donnees.set('id', casEdite ? String(casEdite) : '');
			donnees.set('pour', pour);
			donnees.set('mention', pour === 'groupe' ? f.mention.value : '');
			donnees.set('niveau', pour === 'groupe' ? f.niveau.value : '');
			donnees.set('student_id', pour === 'etudiant' ? casEtudiant.id : '');
			donnees.set('fin_normale', f.fin_normale.value);
			donnees.set('limite_tardive', f.limite_tardive.value);
			donnees.set('motif', f.motif.value.trim());
			const avant = new Set((D.cas || []).map((c) => c.id));
			const edite = casEdite;
			const rep = await poster(donnees);
			fermerCas();
			afficher(rep);
			const nouveau = (D.cas || []).find((c) => (edite ? c.id === edite : !avant.has(c.id)));
			const li = nouveau && listeCas.querySelector('[data-cas="' + nouveau.id + '"]');
			if (li) { li.classList.add('is-eclat'); }
			notifier(rep.message, 'success');
		} catch (err) {
			erreurCas(err.message);
		} finally {
			bouton.disabled = false;
		}
	});
	$('itCasRetirer').addEventListener('click', async () => {
		const c = (D.cas || []).find((x) => x.id === casEdite);
		if (!c) { return; }
		const ok = window.Toast && window.Toast.confirm
			? await window.Toast.confirm('Retirer le calendrier particulier « ' + c.libelle + ' » ? Ses étudiants suivront celui de la session, et leurs pénalités seront recalculées.', { confirmText: 'Retirer', danger: true })
			: window.confirm('Retirer ce calendrier particulier ?');
		if (!ok) { return; }
		const bouton = $('itCasRetirer');
		bouton.disabled = true;
		try {
			const donnees = new FormData();
			donnees.set('action', 'cas-retirer');
			donnees.set('id', String(c.id));
			const rep = await poster(donnees);
			fermerCas();
			afficher(rep);
			notifier(rep.message, 'success');
		} catch (err) {
			erreurCas(err.message);
		} finally {
			bouton.disabled = false;
		}
	});

	if (choixSession) { choixSession.addEventListener('change', () => charger(choixSession.value, true)); }
	fleches.forEach((a) => {
		a.addEventListener('click', (ev) => {
			if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) { return; }
			ev.preventDefault();
			if (a.dataset.session) { charger(a.dataset.session, true); }
		});
	});
	const sessionDeDepart = D.session.id;
	history.replaceState({ itSession: sessionDeDepart }, '', location.href);
	window.addEventListener('popstate', (ev) => {
		charger((ev.state && ev.state.itSession) || new URLSearchParams(location.search).get('session_id') || sessionDeDepart, false);
	});

	/* ------------------------------------------------------------ demarrage */

	/* Sans retard pour l'instant : on montre toutes les inscriptions (la trace). */
	if (!D.inscriptions.some((e) => e.jours > 0)) { filtre.etat = 'tout'; }
	afficher(D);
	if (!D.calendrier && D.modifier) { ouvrirReglage(true); }
	page.dataset.pret = '1';
})();
