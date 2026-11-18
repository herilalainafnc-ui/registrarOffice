/* =============================================================================
   Work Education (/work-education) — page : src/work-education.php,
   styles : src/css/work-education.css.

   Une ligne par etudiant, construite une fois ; les filtres ne font que la
   montrer ou la cacher (une saisie en cours n est jamais perdue). Tri par
   defaut : les derniers inscrits d abord, par jour d inscription (en-tetes) ;
   aussi par nom, par mention, par niveau (retenu par le navigateur). Filtres :
   recherche, avec ou sans tache, mention, parcours, niveau, nouveaux ou anciens. La tache
   s'enregistre quand on quitte le champ, ou sur Entree, qui passe a
   l'etudiant suivant. Echap rend la valeur enregistree. La barre du bas donne
   la meme tache a toute une selection, ou la retire.

   Changer de session (fleches, liste) ne recharge pas la page : ses donnees
   arrivent en JSON (?donnees=1) et la liste est reconstruite ; l'adresse suit
   (?session_id=), et Precedent / Suivant du navigateur y reviennent.

   La liste se tient a jour seule : toutes les 15 secondes (onglet visible),
   l'empreinte de la session (?signature=1) ; si un etudiant s'est inscrit, a
   ete retire ou a recu une tache ailleurs, la liste est redemandee et
   fusionnee (nouveaux a leur place, marques « Nouveau »).
   ========================================================================== */
(function () {
	'use strict';

	const source = document.getElementById('weDonnees');
	if (!source) { return; }
	const D = JSON.parse(source.textContent);
	const page = document.getElementById('wePage');
	const liste = document.getElementById('weListe');
	const suggestions = document.getElementById('weSuggestions');
	const recherche = document.getElementById('weRecherche');
	const mention = document.getElementById('weMention');
	const parcours = document.getElementById('weParcours');
	const niveau = document.getElementById('weNiveau');
	const statut = document.getElementById('weStatut');
	const tri = document.getElementById('weTri');
	const effacer = document.getElementById('weEffacer');
	const etats = document.getElementById('weEtats');
	const tachesBloc = document.getElementById('weTaches');
	const tachesListe = document.getElementById('weTachesListe');
	const toutCocher = document.getElementById('weToutCocher');
	const compte = document.getElementById('weCompte');
	const aucun = document.getElementById('weAucun');
	const selectionBarre = document.getElementById('weSelection');
	const selectionNombre = document.getElementById('weSelectionNombre');
	const selectionTexte = document.getElementById('weSelectionTexte');
	const selectionTache = document.getElementById('weSelectionTache');
	const boutonAttribuer = document.getElementById('weAttribuer');
	const boutonRetirer = document.getElementById('weRetirer');
	const choixSession = document.getElementById('weSession');
	const sessionTexte = document.getElementById('weSessionTexte');
	const fleches = [...page.querySelectorAll('.we-pas[data-sens]')];
	const avancement = document.getElementById('weAvancement');
	const contenu = document.getElementById('weContenu');
	const outils = document.getElementById('weOutils');
	const listeBloc = document.getElementById('weListeBloc');
	const videSession = document.getElementById('weVideSession');
	const note = document.getElementById('weNote');

	/* Les etudiants de la session affichee : remplaces a chaque changement. */
	let etudiants = [];
	let parId = new Map();
	const lignes = new Map();
	const selection = new Set();
	const filtre = { q: '', etat: 'tout', mention: '', parcours: '', niveau: '', statut: '', tache: '', tri: 'recent' };
	const CLE_TRI = 'work-education.tri'; // le tri choisi, retenu par ce navigateur
	try { const t = localStorage.getItem(CLE_TRI); if (t) { filtre.tri = t; } } catch (x) { /* stockage indisponible */ }

	const sansAccents = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
	const nettoyer = (s) => String(s || '').replace(/\s+/g, ' ').trim().slice(0, D.max || 200);
	const pluriel = (n, un, plusieurs) => n + ' ' + (n > 1 ? plusieurs : un);
	const echapper = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	/* ------------------------------------------------------------- serveur */

	async function jetonFrais() {
		const rep = await fetch(location.pathname + location.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
		const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(await rep.text());
		if (m) { window.CSRF_TOKEN = m[1]; }
	}

	/* keepalive : un enregistrement parti au moment ou l'on quitte la page
	   (le champ perd le focus sur le clic d'un lien) va jusqu'au bout. */
	async function attribuer(matricules, tache, deuxieme) {
		const donnees = new FormData();
		donnees.set('ajax', '1');
		donnees.set('csrf_token', window.CSRF_TOKEN || '');
		donnees.set('session_id', String(D.session.id));
		donnees.set('tache', tache);
		matricules.forEach((m) => donnees.append('etudiants[]', m));
		const options = { method: 'POST', body: donnees, credentials: 'same-origin', keepalive: true, headers: { 'X-Requested-With': 'XMLHttpRequest' } };
		let rep;
		try {
			rep = await fetch(location.pathname + location.search, options);
		} catch (err) {
			/* Une requete qui n'a pas pu partir (connexion qui flanche, ou premiere
			   requete pendant l'installation de l'application) est renvoyee une
			   fois : sans danger, la tache est fixee, elle ne s'ajoute pas. */
			await new Promise((r) => setTimeout(r, 700));
			rep = await fetch(location.pathname + location.search, options);
		}
		let json = null;
		try { json = await rep.json(); } catch (x) { /* reponse sans JSON */ }
		if (json && json.code === 'csrf' && !deuxieme) { await jetonFrais(); return attribuer(matricules, tache, true); }
		if (!rep.ok || !json || !json.success) {
			throw new Error((json && json.message) || 'Le serveur n’a pas répondu : vérifiez la connexion, puis réessayez.');
		}
		return json;
	}

	/* ---------------------------------------------------------------- tri */

	/* Le jour d'inscription, lu en date locale : « Aujourd'hui », « Hier »,
	   puis « Samedi 11 octobre » (l'annee si ce n'est pas la courante). */
	function jour(iso) {
		const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
		return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
	}
	function libelleJour(iso) {
		const j = jour(iso);
		if (!j) { return 'Date d’inscription inconnue'; }
		const auj = new Date(); auj.setHours(0, 0, 0, 0);
		const ecart = Math.round((auj - j) / 86400000);
		if (ecart === 0) { return 'Aujourd’hui'; }
		if (ecart === 1) { return 'Hier'; }
		const options = { weekday: 'long', day: 'numeric', month: 'long' };
		if (j.getFullYear() !== auj.getFullYear()) { options.year = 'numeric'; }
		const t = j.toLocaleDateString('fr-FR', options);
		return t.charAt(0).toUpperCase() + t.slice(1);
	}
	function dateCourte(iso) {
		const j = jour(iso);
		if (!j) { return ''; }
		const jj = String(j.getDate()).padStart(2, '0'), mm = String(j.getMonth() + 1).padStart(2, '0');
		return 'inscrit le ' + jj + '/' + mm + (j.getFullYear() !== new Date().getFullYear() ? '/' + j.getFullYear() : '');
	}

	const ordreAlpha = new Intl.Collator('fr', { sensitivity: 'base', numeric: true });
	const parNom = (a, b) => ordreAlpha.compare(a.nom || '', b.nom || '') || ordreAlpha.compare(a.prenom || '', b.prenom || '') || a.ordre - b.ordre;
	/* Une valeur absente se range en dernier. */
	const parTexte = (x, y) => (!x - !y) || ordreAlpha.compare(x || '', y || '');
	/* ordre : le rang donne par le serveur, les derniers inscrits d'abord.
	   groupe : la cle des en-tetes de la liste ; libelle : leur texte. */
	const TRIS = {
		recent:  { cmp: (a, b) => a.ordre - b.ordre, groupe: (e) => e.inscrit_le || '', libelle: (e) => libelleJour(e.inscrit_le) },
		nom:     { cmp: parNom, groupe: null },
		mention: { cmp: (a, b) => parTexte(a.mention, b.mention) || parNom(a, b), groupe: (e) => e.mention || '', libelle: (e) => e.mention || 'Mention non précisée' },
		niveau:  { cmp: (a, b) => ((a.niveau == null ? 99 : a.niveau) - (b.niveau == null ? 99 : b.niveau)) || parNom(a, b),
		           groupe: (e) => (e.niveau == null ? '' : String(e.niveau)), libelle: (e) => e.niveau_libelle || (e.niveau == null ? 'Niveau non précisé' : 'Niveau ' + e.niveau) },
	};
	if (!TRIS[filtre.tri]) { filtre.tri = 'recent'; }
	const trier = () => etudiants.slice().sort(TRIS[filtre.tri].cmp);

	/* La liste dans l'ordre du tri, avec ses en-tetes. Reconstruite au
	   changement de session ou de tri seulement (la saisie n'y est pas). */
	function ordonner() {
		const fragment = document.createDocumentFragment();
		trier().forEach((e) => fragment.appendChild(lignes.get(e.id)));
		liste.replaceChildren(fragment);
		liste.classList.toggle('is-tri-recent', filtre.tri === 'recent');
		poserEntetes();
	}

	/* Un en-tete avant chaque groupe de lignes consecutives (jour, mention ou
	   niveau). Les lignes ne bougent pas : une saisie en cours est gardee. */
	const groupes = [];
	function poserEntetes() {
		liste.querySelectorAll('.we-groupe').forEach((h) => h.remove());
		groupes.length = 0;
		const t = TRIS[filtre.tri];
		if (!t.groupe) { return; }
		let courant = null;
		[...liste.children].forEach((li) => {
			const e = parId.get(li.dataset.id);
			if (!e) { return; }
			const cle = t.groupe(e);
			if (!courant || courant.cle !== cle) {
				const h = document.createElement('li');
				h.className = 'we-groupe';
				h.innerHTML = '<span>' + echapper(t.libelle(e)) + '</span><b></b>';
				liste.insertBefore(h, li);
				courant = { h, cle, lignes: [] };
				groupes.push(courant);
			}
			courant.lignes.push(li);
		});
	}

	/* -------------------------------------------------------------- lignes */

	function initiales(e) {
		return ((e.nom || '').charAt(0) + (e.prenom || '').charAt(0)).toUpperCase() || '?';
	}

	function construireLigne(e) {
		const li = document.createElement('li');
		li.className = 'we-ligne';
		li.dataset.id = e.id;
		const nomComplet = (e.nom || '').toUpperCase() + ' ' + (e.prenom || '');
		const meta = [e.niveau_libelle, e.mention].filter(Boolean).join(' · ');
		li.innerHTML =
			'<label class="we-coche"><input type="checkbox" aria-label="Sélectionner ' + echapper(nomComplet) + '"></label>'
			+ '<span class="we-avatar" aria-hidden="true">' + echapper(initiales(e)) + '</span>'
			+ '<div class="we-ident"><span class="we-nom"><em>' + echapper((e.nom || '').toUpperCase()) + '</em> ' + echapper(e.prenom || '') + '</span>'
			+ '<span class="we-meta"' + (e.parcours ? ' title="' + echapper(e.parcours) + '"' : '') + '><code>' + echapper(e.id) + '</code>' + (meta ? ' · ' + echapper(meta) : '')
			/* la date, sauf quand la liste est deja rangee par jour (CSS : .is-tri-recent) */
			+ (e.inscrit_le ? '<span class="we-date"> · ' + echapper(dateCourte(e.inscrit_le)) + '</span>' : '') + '</span></div>'
			+ '<div class="we-champ"><input type="text" list="weSuggestions" maxlength="' + (D.max || 200) + '" autocomplete="off" spellcheck="false"'
			+ ' placeholder="Tâche de Work Education" aria-label="Tâche de ' + echapper(nomComplet) + '"' + (D.table ? '' : ' disabled') + '>'
			+ '<span class="we-etat-saisie" aria-hidden="true"></span></div>';
		if (e.photo) {
			const img = new Image();
			img.alt = '';
			img.loading = 'lazy';
			img.decoding = 'async';
			img.addEventListener('load', () => { const a = li.querySelector('.we-avatar'); a.textContent = ''; a.appendChild(img); }, { once: true });
			img.src = D.photos + encodeURIComponent(e.photo);
		}
		const champ = li.querySelector('.we-champ input');
		champ.value = e.tache;
		champ.dataset.enregistre = e.tache;
		li.classList.toggle('is-avec', e.tache !== '');
		li._cherche = sansAccents([e.nom, e.prenom, e.prenom, e.nom, e.id].join(' '));
		return li;
	}

	function etatSaisie(li, etat, message) {
		const icone = li.querySelector('.we-etat-saisie');
		clearTimeout(li._minuterie);
		icone.dataset.etat = etat || '';
		icone.innerHTML = etat === 'encours' ? '<i class="bi bi-arrow-repeat"></i>'
			: etat === 'ok' ? '<i class="bi bi-check-circle-fill"></i>'
			: etat === 'erreur' ? '<i class="bi bi-exclamation-circle-fill"></i>' : '';
		li.classList.toggle('is-erreur', etat === 'erreur');
		let erreur = li.querySelector('.we-erreur');
		if (etat === 'erreur') {
			if (!erreur) {
				erreur = document.createElement('p');
				erreur.className = 'we-erreur';
				erreur.setAttribute('role', 'alert');
				li.appendChild(erreur);
			}
			erreur.innerHTML = echapper(message) + '<button type="button" data-we="reessayer">Réessayer</button>';
		} else if (erreur) {
			erreur.remove();
		}
		if (etat === 'ok') {
			li._minuterie = setTimeout(() => { icone.dataset.etat = ''; icone.innerHTML = ''; }, 2400);
		}
	}

	/* Enregistre la tache d'une ligne si elle a change. Une seule requete a
	   la fois par ligne : la derniere valeur saisie part ensuite. */
	async function enregistrerLigne(li) {
		const champ = li.querySelector('.we-champ input');
		const valeur = nettoyer(champ.value);
		if (li._enCours) { li._aRefaire = true; return; }
		if (valeur === champ.dataset.enregistre) {
			if (champ.value !== valeur) { champ.value = valeur; }
			if (li.classList.contains('is-erreur')) { etatSaisie(li, ''); }
			return;
		}
		li._enCours = true;
		etatSaisie(li, 'encours');
		try {
			const rep = await attribuer([li.dataset.id], valeur);
			appliquer(rep.taches);
			adopterSignature(rep.signature);
			etatSaisie(li, 'ok');
			eclat(li);
		} catch (err) {
			etatSaisie(li, 'erreur', err.message);
		} finally {
			li._enCours = false;
			if (li._aRefaire) { li._aRefaire = false; enregistrerLigne(li); }
		}
	}

	/* Les taches renvoyees par le serveur : donnees, champs, compteurs. */
	function appliquer(taches) {
		Object.keys(taches || {}).forEach((id) => {
			const e = parId.get(id);
			const li = lignes.get(id);
			if (!e || !li) { return; }
			const champ = li.querySelector('.we-champ input');
			/* Le champ prend la nouvelle valeur, sauf s'il porte une saisie en
			   cours (curseur dedans, texte different de l'ancienne valeur). */
			const saisieEnCours = document.activeElement === champ && nettoyer(champ.value) !== champ.dataset.enregistre;
			e.tache = taches[id];
			champ.dataset.enregistre = e.tache;
			if (!saisieEnCours || nettoyer(champ.value) === e.tache) { champ.value = e.tache; }
			li.classList.toggle('is-avec', e.tache !== '');
			if (e.tache) { ajouterSuggestion(e.tache); }
		});
		compter();
		filtrer();
	}

	function eclat(li) {
		li.classList.remove('is-eclat');
		void li.offsetWidth;
		li.classList.add('is-eclat');
		setTimeout(() => li.classList.remove('is-eclat'), 1300);
	}

	/* ------------------------------------------------------ suggestions */

	const suggestionsVues = new Set();
	function ajouterSuggestion(t, enTete) {
		const cle = t.toLowerCase();
		if (suggestionsVues.has(cle)) { return; }
		suggestionsVues.add(cle);
		const option = document.createElement('option');
		option.value = t;
		if (enTete && suggestions.firstChild) { suggestions.insertBefore(option, suggestions.firstChild); } else { suggestions.appendChild(option); }
	}

	/* ------------------------------------------------- filtres et compteurs */

	function correspond(e, li) {
		if (filtre.etat === 'sans' && e.tache !== '') { return false; }
		if (filtre.etat === 'avec' && e.tache === '') { return false; }
		if (filtre.mention && e.mention !== filtre.mention) { return false; }
		if (filtre.parcours && e.parcours !== filtre.parcours) { return false; }
		if (filtre.niveau && String(e.niveau) !== filtre.niveau) { return false; }
		if (filtre.statut && e.nouveau !== (filtre.statut === 'nouveau')) { return false; }
		if (filtre.tache && e.tache !== filtre.tache) { return false; }
		if (filtre.q && !filtre.q.split(' ').every((mot) => li._cherche.includes(mot))) { return false; }
		return true;
	}

	/* Une ligne en cours de saisie reste visible, meme si elle ne repond plus
	   au filtre (« Sans tache » : elle vient d'en recevoir une). */
	function filtrer() {
		let visibles = 0;
		etudiants.forEach((e) => {
			const li = lignes.get(e.id);
			const montre = correspond(e, li) || li.contains(document.activeElement);
			li.hidden = !montre;
			if (montre) { visibles++; }
		});
		aucun.hidden = visibles > 0;
		compte.textContent = visibles === etudiants.length ? pluriel(visibles, 'étudiant', 'étudiants')
			: visibles + ' sur ' + etudiants.length + ' affichés';
		/* Chaque en-tete compte ses lignes affichees ; sans ligne, il se cache. */
		groupes.forEach((g) => {
			const n = g.lignes.filter((li) => !li.hidden && li.isConnected).length;
			g.h.hidden = n === 0;
			g.h.lastElementChild.textContent = pluriel(n, 'étudiant', 'étudiants');
		});
		/* Les filtres choisis prennent l'accent ; « Effacer » s'il y en a un. */
		[[mention, filtre.mention], [parcours, filtre.parcours], [niveau, filtre.niveau], [statut, filtre.statut]]
			.forEach(([s, v]) => s.classList.toggle('is-actif', !!v));
		effacer.hidden = !(filtre.q || filtre.etat !== 'tout' || filtre.mention || filtre.parcours || filtre.niveau || filtre.statut || filtre.tache);
		majToutCocher();
	}

	function compter() {
		const avec = etudiants.filter((e) => e.tache !== '').length;
		const total = etudiants.length;
		document.getElementById('weAvec').textContent = avec;
		document.getElementById('weReste').textContent = total - avec === 0 ? 'Tous ont une tâche' : (total - avec) + ' sans tâche';
		document.getElementById('weTotal').textContent = total;
		const barre = document.getElementById('weBarre');
		barre.setAttribute('aria-valuemax', String(total));
		barre.setAttribute('aria-valuenow', String(avec));
		barre.firstElementChild.style.width = (total ? (avec * 100 / total) : 0).toFixed(1) + '%';
		document.getElementById('weAvancement').classList.toggle('is-complet', total > 0 && avec === total);
		etats.querySelector('[data-etat="tout"] b').textContent = total;
		etats.querySelector('[data-etat="sans"] b').textContent = total - avec;
		etats.querySelector('[data-etat="avec"] b').textContent = avec;

		/* Les taches de la session, les plus donnees d'abord. */
		const effectifs = new Map();
		etudiants.forEach((e) => { if (e.tache) { effectifs.set(e.tache, (effectifs.get(e.tache) || 0) + 1); } });
		if (filtre.tache && !effectifs.has(filtre.tache)) { filtre.tache = ''; }
		const triees = [...effectifs.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0], 'fr'));
		tachesBloc.hidden = triees.length === 0;
		tachesListe.innerHTML = triees.map(([t, n]) =>
			'<button type="button" class="we-puce' + (filtre.tache === t ? ' is-actif' : '') + '" data-tache="' + echapper(t) + '" aria-pressed="' + (filtre.tache === t) + '"'
			+ ' title="' + echapper(pluriel(n, 'étudiant', 'étudiants') + ' : ' + t) + '"><span>' + echapper(t) + '</span><b>' + n + '</b></button>').join('');
		triees.forEach(([t]) => ajouterSuggestion(t, true));
	}

	/* ------------------------------------------------------------ selection */

	function majSelection() {
		const n = selection.size;
		selectionBarre.hidden = n === 0;
		selectionNombre.textContent = n;
		selectionTexte.textContent = n > 1 ? ' étudiants sélectionnés' : ' étudiant sélectionné';
		boutonRetirer.disabled = ![...selection].some((id) => (parId.get(id) || {}).tache);
		majToutCocher();
	}

	function majToutCocher() {
		const visibles = [...lignes.values()].filter((li) => !li.hidden);
		const cochees = visibles.filter((li) => selection.has(li.dataset.id)).length;
		toutCocher.checked = visibles.length > 0 && cochees === visibles.length;
		toutCocher.indeterminate = cochees > 0 && cochees < visibles.length;
	}

	function cocher(li, oui) {
		const id = li.dataset.id;
		if (oui) { selection.add(id); } else { selection.delete(id); }
		li.classList.toggle('is-cochee', oui);
		li.querySelector('.we-coche input').checked = oui;
	}

	async function attribuerSelection(tache) {
		const ids = [...selection];
		if (!ids.length) { return; }
		const boutons = [boutonAttribuer, boutonRetirer];
		boutons.forEach((b) => { b.disabled = true; });
		ids.forEach((id) => etatSaisie(lignes.get(id), 'encours'));
		try {
			const rep = await attribuer(ids, tache);
			appliquer(rep.taches);
			adopterSignature(rep.signature);
			ids.forEach((id) => { const li = lignes.get(id); etatSaisie(li, 'ok'); eclat(li); cocher(li, false); });
			selection.clear();
			selectionTache.value = '';
			majSelection();
			notifier(tache ? 'Tâche attribuée à ' + pluriel(ids.length, 'étudiant', 'étudiants') + '.' : 'Tâche retirée de ' + pluriel(ids.length, 'étudiant', 'étudiants') + '.', 'success');
		} catch (err) {
			ids.forEach((id) => etatSaisie(lignes.get(id), ''));
			notifier(err.message, 'error');
		} finally {
			boutons.forEach((b) => { b.disabled = false; });
			majSelection();
		}
	}

	function notifier(message, type) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); return; }
		if (type === 'error') { window.alert(message); }
	}

	/* ------------------------------------------------------------ session */

	/* Montre une session : sa liste, ses mentions, ses fleches, ses textes.
	   La recherche et le filtre « sans / avec tache » sont gardes. */
	function afficherSession(etat) {
		D.session = etat.session;
		etudiants = etat.etudiants || [];
		etudiants.forEach((e, i) => { e.ordre = i; }); // l'ordre du serveur : les derniers inscrits d'abord
		parId = new Map(etudiants.map((e) => [e.id, e]));
		lignes.clear();
		selection.clear();
		filtre.tache = '';
		etudiants.forEach((e) => { lignes.set(e.id, construireLigne(e)); });
		ordonner();
		D.signature = etat.signature || '';
		majChoix();

		/* La session et ses voisines. */
		sessionTexte.innerHTML = echapper(etat.session.nom) + ' <span>' + echapper(etat.session.annee) + '</span>';
		if (choixSession) { choixSession.value = String(etat.session.id); }
		fleches.forEach((a) => {
			const v = a.dataset.sens === 'precedente' ? etat.precedente : etat.suivante;
			a.classList.toggle('is-inactif', !v);
			if (v) {
				a.href = '?session_id=' + v.id;
				a.dataset.session = v.id;
				a.removeAttribute('aria-disabled');
			} else {
				a.removeAttribute('href');
				delete a.dataset.session;
				a.setAttribute('aria-disabled', 'true');
			}
			a.setAttribute('aria-label', (a.dataset.sens === 'precedente' ? 'Session précédente' : 'Session suivante') + (v ? ' : ' + v.libelle : ''));
		});

		majBlocs(etat.master || 0);
		compter();
		filtrer();
		majSelection();
	}

	/* Les choix des filtres d'apres les etudiants de la session, avec leur
	   effectif ; un choix qui n'existe plus est abandonne, un filtre a une
	   seule valeur possible est cache. Les parcours suivent la mention choisie. */
	function remplir(select, valeurs, cle) {
		const effectifs = new Map();
		valeurs.forEach(([v, libelle, rang]) => {
			if (v === '' || v == null) { return; }
			const x = effectifs.get(v) || { libelle, rang, n: 0 };
			x.n++;
			effectifs.set(v, x);
		});
		select.length = 1;
		[...effectifs.entries()].sort((a, b) => (a[1].rang - b[1].rang) || ordreAlpha.compare(a[1].libelle, b[1].libelle)).forEach(([v, x]) => {
			const o = document.createElement('option');
			o.value = v;
			o.textContent = x.libelle + ' (' + x.n + ')';
			select.appendChild(o);
		});
		filtre[cle] = effectifs.has(filtre[cle]) ? filtre[cle] : '';
		select.value = filtre[cle];
		select.closest('.we-filtre').hidden = effectifs.size < 2 && !filtre[cle];
	}
	function majChoix() {
		remplir(mention, etudiants.map((e) => [e.mention, e.mention, 0]), 'mention');
		remplir(parcours, etudiants.filter((e) => !filtre.mention || e.mention === filtre.mention).map((e) => [e.parcours, e.parcours, 0]), 'parcours');
		remplir(niveau, etudiants.map((e) => [e.niveau == null ? '' : String(e.niveau), e.niveau_libelle || 'Niveau ' + e.niveau, e.niveau == null ? 99 : e.niveau]), 'niveau');
		remplir(statut, etudiants.map((e) => (e.nouveau ? ['nouveau', 'Nouveaux', 0] : ['ancien', 'Anciens', 1])), 'statut');
		statut.options[0].textContent = 'Nouveaux et anciens';
	}

	/* Pas d'etudiant de Licence : le message remplace la liste. */
	function majBlocs(master) {
		const total = etudiants.length;
		avancement.hidden = outils.hidden = listeBloc.hidden = total === 0;
		videSession.hidden = total > 0;
		page.toggleAttribute('data-vide', total === 0); /* lu par la visite guidee (data/didacticiel.pages.php) */
		document.getElementById('weVideTexte').textContent = (master ? 'Les ' + master + ' inscrits sont en Master, sans Work Education. ' : '') + 'Choisissez une autre session.';
		note.hidden = !(master && total);
		document.getElementById('weNoteTexte').textContent = master > 1
			? 'Les ' + master + ' étudiants de Master inscrits à cette session n’ont pas de Work Education : ils ne figurent pas ici.'
			: 'L’étudiant de Master inscrit à cette session n’a pas de Work Education : il ne figure pas ici.';
	}

	/* Une autre session, sans recharger la page. Un second clic avant la
	   reponse annule la premiere demande. */
	let demande = null;
	async function charger(id, pousser) {
		id = String(id || '');
		if (!id || id === String(D.session.id)) {
			if (choixSession) { choixSession.value = String(D.session.id); }
			return;
		}
		if (demande) { demande.abort(); }
		const controle = new AbortController();
		demande = controle;
		page.classList.add('is-chargement');
		page.setAttribute('aria-busy', 'true');
		try {
			const rep = await fetch(location.pathname + '?session_id=' + encodeURIComponent(id) + '&donnees=1', {
				credentials: 'same-origin', cache: 'no-store', signal: controle.signal,
				headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
			});
			let json = null;
			try { json = await rep.json(); } catch (x) { /* reponse sans JSON (session expiree...) */ }
			if (!rep.ok || !json || !json.success) {
				throw new Error((json && json.message) || 'La session n’a pas pu être chargée : vérifiez la connexion, puis réessayez.');
			}
			afficherSession(json);
			if (pousser) { history.pushState({ weSession: json.session.id }, '', location.pathname + '?session_id=' + json.session.id); }
			contenu.classList.remove('is-arrivee');
			void contenu.offsetWidth;
			contenu.classList.add('is-arrivee');
		} catch (err) {
			if (err.name === 'AbortError') { return; }
			notifier(err.message, 'error');
			if (choixSession) { choixSession.value = String(D.session.id); }
		} finally {
			if (demande === controle) {
				demande = null;
				page.classList.remove('is-chargement');
				page.removeAttribute('aria-busy');
			}
		}
	}

	/* ------------------------------------------------------- mise a jour */

	/* Une liste rafraichie (nouvel inscrit, retrait, tache donnee ailleurs) :
	   fusionnee dans l'ecran tel qu'il est, sans perdre la saisie en cours,
	   la selection ni la position dans la page. */
	function fusionner(etat) {
		const recus = etat.etudiants || [];
		const recusIds = new Set(recus.map((e) => e.id));
		const nouveaux = [];

		/* Retires de la session (ou passes en Master) : leur ligne s'en va,
		   sauf celle ou l'on ecrit, qui partira au rafraichissement suivant. */
		const gardes = [];
		etudiants.forEach((e) => {
			if (recusIds.has(e.id)) { return; }
			const li = lignes.get(e.id);
			if (li && li.contains(document.activeElement)) { gardes.push(e); return; }
			if (li) { li.remove(); }
			lignes.delete(e.id);
			selection.delete(e.id);
		});

		/* Les taches donnees ailleurs ; les nouveaux inscrits, places ensuite. */
		recus.forEach((r, i) => { r.ordre = i; });
		const fusion = recus.map((r) => {
			const e = parId.get(r.id);
			if (!e) {
				const li = construireLigne(r);
				li.classList.add('is-nouveau');
				li.querySelector('.we-nom').insertAdjacentHTML('beforeend', ' <span class="we-badge">Nouveau</span>');
				lignes.set(r.id, li);
				nouveaux.push(r);
				return r;
			}
			if (r.tache !== e.tache) {
				const li = lignes.get(r.id);
				const champ = li.querySelector('.we-champ input');
				const enSaisie = li._enCours || (document.activeElement === champ && nettoyer(champ.value) !== champ.dataset.enregistre);
				champ.dataset.enregistre = r.tache;
				if (!enSaisie) { champ.value = r.tache; }
				li.classList.toggle('is-avec', r.tache !== '');
				if (r.tache) { ajouterSuggestion(r.tache); }
			}
			return Object.assign(e, r);
		});
		etudiants = fusion.concat(gardes);
		parId = new Map(etudiants.map((e) => [e.id, e]));
		D.signature = etat.signature || D.signature;

		/* Chaque nouveau a sa place dans le tri choisi (en tete, avec le tri par
		   defaut), avant la ligne qui le suit ; les autres lignes ne bougent pas. */
		const rang = trier();
		rang.forEach((e, k) => {
			const li = lignes.get(e.id);
			if (li.isConnected) { return; }
			let suivant = null;
			for (let j = k + 1; j < rang.length && !suivant; j++) {
				const l = lignes.get(rang[j].id);
				if (l && l.isConnected) { suivant = l; }
			}
			liste.insertBefore(li, suivant);
		});
		poserEntetes();
		majChoix();
		majBlocs(etat.master || 0);
		compter();
		filtrer();
		majSelection();
		if (choixSession && etudiants.length) {
			const option = choixSession.querySelector('option[value="' + D.session.id + '"]');
			if (option) { option.textContent = D.session.libelle; } /* plus « (aucun inscrit) » */
		}
		if (nouveaux.length) {
			const noms = nouveaux.slice(0, 3).map((e) => (e.nom || '').toUpperCase() + ' ' + (e.prenom || '')).join(', ') + (nouveaux.length > 3 ? '…' : '');
			notifier((nouveaux.length > 1 ? nouveaux.length + ' nouveaux inscrits ajoutés' : 'Nouvel inscrit ajouté') + ' : ' + noms + '.', 'info');
		}
	}

	/* L'empreinte renvoyee apres un enregistrement : adoptee si aucune
	   inscription n'a bouge depuis la derniere verification, pour que ses
	   propres taches ne fassent pas recharger la liste. */
	const partInscriptions = (s) => String(s || '').split('.').slice(0, 2).join('.');
	function adopterSignature(s) {
		if (s && partInscriptions(s) === partInscriptions(D.signature)) { D.signature = s; }
	}

	/* Toutes les 15 secondes, onglet visible : l'empreinte de la session
	   (quelques octets) ; la liste n'est redemandee que si elle a change. */
	const PERIODE = 15000;
	let minuterie = 0;
	let verification = false;
	let derniereVerification = Date.now();
	async function verifier() {
		clearTimeout(minuterie);
		if (document.hidden) { return; }
		if (!verification && !demande && D.session) {
			verification = true;
			derniereVerification = Date.now();
			const id = D.session.id;
			const options = { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } };
			try {
				const rep = await fetch(location.pathname + '?session_id=' + id + '&signature=1', options);
				const json = rep.ok ? await rep.json() : null;
				if (json && json.success && json.signature !== D.signature && id === D.session.id && !demande) {
					const complet = await fetch(location.pathname + '?session_id=' + id + '&donnees=1', options);
					const etat = complet.ok ? await complet.json() : null;
					if (etat && etat.success && id === D.session.id && !demande) { fusionner(etat); }
				}
			} catch (x) {
				/* reseau coupe ou session expiree : nouvel essai au tour suivant */
			} finally {
				verification = false;
			}
		}
		minuterie = setTimeout(verifier, PERIODE);
	}
	document.addEventListener('visibilitychange', () => {
		clearTimeout(minuterie);
		if (document.hidden) { return; }
		/* De retour sur l'onglet : on verifie tout de suite. */
		if (Date.now() - derniereVerification > 4000) { verifier(); } else { minuterie = setTimeout(verifier, PERIODE); }
	});

	/* ------------------------------------------------------------ demarrage */

	(D.suggestions || []).forEach((t) => ajouterSuggestion(t));
	afficherSession(D);
	const sessionDeDepart = D.session.id;
	minuterie = setTimeout(verifier, PERIODE);
	history.replaceState({ weSession: sessionDeDepart }, '', location.href);

	/* ------------------------------------------------------------- ecouteurs */

	liste.addEventListener('change', (ev) => {
		const li = ev.target.closest('.we-ligne');
		if (!li) { return; }
		if (ev.target.matches('.we-coche input')) { cocher(li, ev.target.checked); majSelection(); return; }
		if (ev.target.matches('.we-champ input')) { enregistrerLigne(li); }
	});

	liste.addEventListener('keydown', (ev) => {
		if (!ev.target.matches('.we-champ input')) { return; }
		const li = ev.target.closest('.we-ligne');
		if (ev.key === 'Enter') {
			ev.preventDefault();
			enregistrerLigne(li);
			/* L'etudiant suivant dans la liste affichee. */
			let suivant = li.nextElementSibling;
			while (suivant && (suivant.hidden || !suivant.classList.contains('we-ligne'))) { suivant = suivant.nextElementSibling; } // ni cachee ni en-tete
			if (suivant) {
				const champ = suivant.querySelector('.we-champ input');
				champ.focus();
				champ.select();
			}
		} else if (ev.key === 'Escape') {
			ev.target.value = ev.target.dataset.enregistre;
			etatSaisie(li, '');
		}
	});

	liste.addEventListener('focusout', (ev) => {
		/* Une ligne gardee visible pendant la saisie se range a sa place. */
		if (ev.target.matches('.we-champ input')) { setTimeout(filtrer, 0); }
	});

	liste.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-we="reessayer"]');
		if (b) { enregistrerLigne(b.closest('.we-ligne')); }
	});

	let attente = 0;
	recherche.addEventListener('input', () => {
		clearTimeout(attente);
		attente = setTimeout(() => { filtre.q = sansAccents(recherche.value).trim().replace(/\s+/g, ' '); filtrer(); }, 120);
	});

	etats.addEventListener('click', (ev) => {
		const b = ev.target.closest('.we-etat');
		if (!b) { return; }
		filtre.etat = b.dataset.etat;
		etats.querySelectorAll('.we-etat').forEach((x) => { const oui = x === b; x.classList.toggle('is-actif', oui); x.setAttribute('aria-pressed', String(oui)); });
		filtrer();
	});

	mention.addEventListener('change', () => { filtre.mention = mention.value; majChoix(); filtrer(); }); // les parcours suivent la mention
	parcours.addEventListener('change', () => { filtre.parcours = parcours.value; filtrer(); });
	niveau.addEventListener('change', () => { filtre.niveau = niveau.value; filtrer(); });
	statut.addEventListener('change', () => { filtre.statut = statut.value; filtrer(); });

	tri.value = filtre.tri;
	tri.addEventListener('change', () => {
		filtre.tri = TRIS[tri.value] ? tri.value : 'recent';
		try { localStorage.setItem(CLE_TRI, filtre.tri); } catch (x) { /* stockage indisponible */ }
		ordonner();
		filtrer();
	});

	/* Tout ce qui reduit la liste revient a zero : recherche, etat, filtres, tache. */
	effacer.addEventListener('click', () => {
		recherche.value = '';
		Object.assign(filtre, { q: '', etat: 'tout', mention: '', parcours: '', niveau: '', statut: '', tache: '' });
		etats.querySelectorAll('.we-etat').forEach((x) => { const oui = x.dataset.etat === 'tout'; x.classList.toggle('is-actif', oui); x.setAttribute('aria-pressed', String(oui)); });
		tachesListe.querySelectorAll('.we-puce').forEach((x) => { x.classList.remove('is-actif'); x.setAttribute('aria-pressed', 'false'); });
		majChoix();
		filtrer();
		recherche.focus();
	});

	tachesListe.addEventListener('click', (ev) => {
		const b = ev.target.closest('.we-puce');
		if (!b) { return; }
		filtre.tache = filtre.tache === b.dataset.tache ? '' : b.dataset.tache;
		tachesListe.querySelectorAll('.we-puce').forEach((x) => { const oui = x.dataset.tache === filtre.tache; x.classList.toggle('is-actif', oui); x.setAttribute('aria-pressed', String(oui)); });
		filtrer();
	});

	toutCocher.addEventListener('change', () => {
		const oui = toutCocher.checked;
		lignes.forEach((li) => { if (!li.hidden) { cocher(li, oui); } });
		majSelection();
	});

	boutonAttribuer.addEventListener('click', () => {
		const t = nettoyer(selectionTache.value);
		if (!t) {
			selectionTache.focus();
			selectionTache.placeholder = 'Écrivez ou choisissez la tâche';
			return;
		}
		attribuerSelection(t);
	});
	selectionTache.addEventListener('keydown', (ev) => {
		if (ev.key === 'Enter') { ev.preventDefault(); boutonAttribuer.click(); }
	});

	boutonRetirer.addEventListener('click', async () => {
		const n = [...selection].filter((id) => (parId.get(id) || {}).tache).length;
		const question = 'La tâche de ' + pluriel(n, 'étudiant', 'étudiants') + ' sera effacée de sa fiche d’inscription.';
		const ok = window.Toast && typeof window.Toast.confirm === 'function'
			? await window.Toast.confirm(question, { title: 'Retirer la tâche ?', confirmText: 'Retirer', cancelText: 'Annuler', danger: true })
			: window.confirm(question);
		if (ok) { attribuerSelection(''); }
	});

	document.getElementById('weDeselectionner').addEventListener('click', () => {
		lignes.forEach((li) => cocher(li, false));
		selection.clear();
		majSelection();
	});

	if (choixSession) {
		choixSession.addEventListener('change', () => charger(choixSession.value, true));
	}
	/* Les fleches gardent leur adresse : Ctrl + clic ou clic molette ouvre
	   la session dans un autre onglet. */
	fleches.forEach((a) => {
		a.addEventListener('click', (ev) => {
			if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) { return; }
			ev.preventDefault();
			if (a.dataset.session) { charger(a.dataset.session, true); }
		});
	});
	window.addEventListener('popstate', (ev) => {
		const id = (ev.state && ev.state.weSession) || new URLSearchParams(location.search).get('session_id') || sessionDeDepart;
		charger(id, false);
	});

	/* ------------------------------------------------------- export PDF
	   La liste en PDF (app/work-education.pdf.php) : la fenetre s'ouvre avec les filtres de la page
	   (mention, niveau, nouveaux / anciens, service, avec ou sans tache) ; la periode (cette session,
	   toute l'annee universitaire, toutes les sessions) change ce qu'on peut choisir, et le nombre
	   d'etudiants du PDF se met a jour a chaque choix. Le PDF est telecharge sans quitter la page. */
	const dlg = document.getElementById('weDlgExport');
	if (dlg && D.pdf) {
		const ex = {
			portee: document.getElementById('weExPortee'), tache: document.getElementById('weExTache'), mention: document.getElementById('weExMention'),
			niveau: document.getElementById('weExNiveau'), statut: document.getElementById('weExStatut'), groupe: document.getElementById('weExGroupe'),
		};
		const exCompte = document.getElementById('weExCompte');
		const exErreur = document.getElementById('weExErreur');
		const exBouton = document.getElementById('weExTelecharger');
		let exSerie = 0;      // la reponse la plus recente seule compte
		let exMinuterie = 0;
		let exOccupe = false;

		const exParametres = () => {
			const p = new URLSearchParams({ session_id: String(D.session.id), portee: ex.portee.value, groupe: ex.groupe.value });
			['tache', 'mention', 'niveau', 'statut'].forEach((c) => { if (ex[c].value) { p.set(c, ex[c].value); } });
			return p;
		};
		const exMessage = (texte) => { exErreur.hidden = !texte; exErreur.textContent = texte || ''; };
		/* Les choix d'une liste, la valeur deja choisie gardee si elle existe encore. */
		function exRemplir(select, premier, choix) {
			const avant = select.value;
			select.replaceChildren(new Option(premier, ''));
			choix.forEach(([valeur, libelle]) => select.appendChild(new Option(libelle, valeur)));
			select.value = [...select.options].some((o) => o.value === avant) ? avant : '';
		}
		async function exActualiser(listes) {
			const n = ++exSerie;
			exCompte.textContent = 'Calcul…';
			exBouton.disabled = true;
			try {
				const p = exParametres();
				p.set('action', 'options');
				const rep = await fetch(D.pdf + '?' + p.toString(), { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				const json = await rep.json();
				if (n !== exSerie) { return; }
				if (!rep.ok || !json.success) { throw new Error(json.message || 'Le serveur n’a pas répondu.'); }
				if (listes) {
					const o = json.options;
					exRemplir(ex.tache, 'Tous les services', [['__sans', 'Sans tâche (' + o.sans + ')'], ['__avec', 'Avec une tâche']].concat(o.services.map((t) => [t.tache, t.tache + ' (' + t.n + ')'])));
					exRemplir(ex.mention, 'Toutes les mentions', o.mentions.map((m) => [m, m]));
					exRemplir(ex.niveau, 'Tous les niveaux', o.niveaux.map((v) => [v.valeur, v.libelle]));
				}
				const total = json.total;
				exMessage('');
				exCompte.textContent = total === 0 ? 'Aucun étudiant ne correspond à ces choix.'
					: total > json.max ? total + ' étudiants : trop pour un seul PDF (' + json.max + ' au plus). Choisissez une période plus courte ou un filtre.'
					: pluriel(total, 'étudiant', 'étudiants') + ' dans le PDF' + (json.sessions > 1 ? ', sur ' + json.sessions + ' sessions' : '') + '.';
				exBouton.disabled = total === 0 || total > json.max;
			} catch (err) {
				if (n !== exSerie) { return; }
				exCompte.textContent = '';
				exMessage(err.message);
			}
		}
		const exPlanifier = (listes) => { clearTimeout(exMinuterie); exMinuterie = setTimeout(() => exActualiser(listes), 150); };

		function exOuvrir() {
			ex.portee.options[0].textContent = 'Cette session (' + (D.session.libelle || '') + ')';
			ex.portee.options[1].textContent = 'Toute l’année universitaire ' + (D.session.annee || '');
			/* Les filtres de la page : ce qu'on voit est ce qu'on exporte. */
			ex.portee.value = 'session';
			ex.statut.value = filtre.statut || '';
			ex.groupe.value = 'service';
			exRemplir(ex.mention, 'Toutes les mentions', filtre.mention ? [[filtre.mention, filtre.mention]] : []);
			exRemplir(ex.niveau, 'Tous les niveaux', filtre.niveau ? [[filtre.niveau, 'Niveau ' + filtre.niveau]] : []);
			ex.mention.value = filtre.mention || '';
			ex.niveau.value = filtre.niveau || '';
			if (filtre.tache) { exRemplir(ex.tache, 'Tous les services', [[filtre.tache, filtre.tache]]); ex.tache.value = filtre.tache; }
			else if (filtre.etat === 'sans' || filtre.etat === 'avec') { exRemplir(ex.tache, 'Tous les services', [['__' + filtre.etat, filtre.etat === 'sans' ? 'Sans tâche' : 'Avec une tâche']]); ex.tache.value = '__' + filtre.etat; }
			else { exRemplir(ex.tache, 'Tous les services', []); }
			exMessage('');
			dlg.showModal();
			exActualiser(true);
		}
		async function exTelecharger(ev) {
			ev.preventDefault();
			if (exOccupe || exBouton.disabled) { return; }
			exOccupe = true;
			exMessage('');
			const libelle = exBouton.querySelector('span');
			const avant = libelle.textContent;
			libelle.textContent = 'Préparation du PDF…';
			exBouton.disabled = true;
			try {
				const rep = await fetch(D.pdf + '?' + exParametres().toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				if (!rep.ok || !/pdf/i.test(rep.headers.get('Content-Type') || '')) {
					const texte = await rep.text();
					const message = (() => { try { return JSON.parse(texte).message; } catch (x) { return ''; } })() || texte.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
					throw new Error(message || 'Le PDF n’a pas pu être créé (' + rep.status + ').');
				}
				const blob = await rep.blob();
				const m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(rep.headers.get('Content-Disposition') || '');
				const a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = m ? decodeURIComponent(m[1]) : 'work-education.pdf';
				document.body.appendChild(a);
				a.click();
				a.remove();
				setTimeout(() => URL.revokeObjectURL(a.href), 4000);
				dlg.close();
			} catch (err) {
				exMessage(err.message);
			} finally {
				exOccupe = false;
				libelle.textContent = avant;
				exBouton.disabled = false;
			}
		}

		document.getElementById('weExport').addEventListener('click', exOuvrir);
		document.getElementById('weFormExport').addEventListener('submit', exTelecharger);
		ex.portee.addEventListener('change', () => exPlanifier(true));
		[ex.tache, ex.mention, ex.niveau, ex.statut].forEach((s) => s.addEventListener('change', () => exPlanifier(false)));
		document.getElementById('weExTout').addEventListener('click', () => {
			[ex.tache, ex.mention, ex.niveau, ex.statut].forEach((s) => { s.value = ''; });
			exPlanifier(false);
		});
		dlg.querySelectorAll('[data-fermer]').forEach((b) => b.addEventListener('click', () => dlg.close()));
		dlg.addEventListener('click', (e) => { if (e.target === dlg) { dlg.close(); } });   // un clic sur le fond
	}

	page.dataset.pret = '1';
})();
