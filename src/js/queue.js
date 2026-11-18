/* =============================================================================
   File d'attente — ecran agent (src/queue/queue.agent.php)

   Un seul appel, agent_state, rafraichit toute la page toutes les 4 secondes
   (et aussitot apres chaque action) : session, comptes, numeros appeles,
   liste filtree, annonce et videos. Le rafraichissement s'arrete quand
   l'onglet est cache. Les listes ne sont redessinees que si elles ont change,
   pour ne pas voler le focus a qui les parcourt au clavier.

   Toute ecriture part en POST avec le jeton CSRF de la page (CSRF_TOKEN,
   pose par init/head.php).
   ========================================================================== */
(function () {
	'use strict';

	const page = document.getElementById('fqPage');
	if (!page) { return; }
	const API = page.dataset.api;
	const $ = (id) => document.getElementById(id);

	const PERIODE = 4000;
	const LIBELLES = { waiting: 'En attente', called: 'Appelé', serving: 'Au guichet', done: 'Terminé', skipped: 'Passé', absent: 'Absent' };
	/* Actions proposees selon l'etat : [action, libelle, icone, variante] */
	const ACTIONS = {
		waiting: [['call_specific', 'Appeler', 'bi-megaphone'], ['mark_skipped', 'Passer', 'bi-skip-forward']],
		called:  [['recall', 'Rappeler', 'bi-arrow-repeat'], ['mark_done', 'Terminé', 'bi-check-lg', 'ok'], ['mark_absent', 'Absent', 'bi-person-x', 'danger']],
		serving: [['mark_done', 'Terminé', 'bi-check-lg', 'ok'], ['mark_absent', 'Absent', 'bi-person-x', 'danger']],
		skipped: [['call_specific', 'Rappeler', 'bi-megaphone']],
		absent:  [['call_specific', 'Rappeler', 'bi-megaphone']],
		done:    []
	};

	let etat = null;              // derniere reponse d'agent_state
	let filtre = 'waiting';
	let recherche = '';
	let enCours = false, relancer = false, minuterie = 0;
	const signatures = {};        // dernier rendu de chaque liste

	/* --- Utilitaires --------------------------------------------------------- */
	const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const numero = (n) => '#' + String(n).padStart(3, '0');
	const pluriel = (n, mot, motPluriel) => n + ' ' + (n > 1 ? (motPluriel || mot + 's') : mot);

	function duree(min) {
		if (min == null || min < 0) { return ''; }
		if (min < 1) { return '< 1 min'; }
		if (min < 60) { return min + ' min'; }
		if (min < 1440) { return Math.floor(min / 60) + ' h ' + String(min % 60).padStart(2, '0'); }
		return pluriel(Math.floor(min / 1440), 'jour');
	}
	// « 2026-03-09 » -> « lundi 9 mars 2026 » ; « 2026-03-09 07:14:11 » -> « 9 mars à 07:14 »
	function dateLongue(iso) {
		const [a, m, j] = String(iso || '').slice(0, 10).split('-').map(Number);
		if (!a) { return ''; }
		return new Date(a, m - 1, j).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
	}
	function dateHeure(iso) {
		const t = String(iso || '');
		if (t.length < 16) { return ''; }
		const [a, m, j] = t.slice(0, 10).split('-').map(Number);
		return new Date(a, m - 1, j).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }) + ' à ' + t.slice(11, 16);
	}

	function notifier(texte, type) {
		if (window.Toast && typeof window.Toast.show === 'function') { window.Toast.show(texte, type); }
	}
	function confirmer(message, titre, bouton, danger) {
		if (window.Toast && typeof window.Toast.confirm === 'function') {
			return window.Toast.confirm(message, { title: titre, confirmText: bouton, danger: !!danger });
		}
		return Promise.resolve(window.confirm(message));
	}

	// Petit signal sonore sur le poste de l'agent quand un appel part
	function bip() {
		try {
			const ctx = new (window.AudioContext || window.webkitAudioContext)();
			const osc = ctx.createOscillator(), gain = ctx.createGain();
			osc.connect(gain); gain.connect(ctx.destination);
			osc.frequency.value = 880;
			gain.gain.setValueAtTime(0.2, ctx.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
			osc.start(); osc.stop(ctx.currentTime + 0.2);
		} catch (e) { /* pas de son : sans gravite */ }
	}

	/* --- Serveur -------------------------------------------------------------- */
	async function api(action, donnees, methode) {
		const params = Object.assign({ action: action }, donnees || {});
		let url = API, options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
		if (methode === 'POST') {
			params.csrf_token = window.CSRF_TOKEN || '';
			options.method = 'POST';
			options.body = new URLSearchParams(params);
		} else {
			url += '?' + new URLSearchParams(params);
		}
		let rep;
		try { rep = await fetch(url, options); } catch (e) { throw new Error('Le serveur ne répond pas. Vérifiez la connexion, puis réessayez.'); }
		let json = null;
		try { json = await rep.json(); } catch (e) { /* page d'erreur HTML */ }
		if (!json) {
			throw new Error(rep.status === 403 || rep.status === 401
				? 'Votre session a expiré : rechargez la page pour vous reconnecter.'
				: 'Réponse inattendue du serveur (' + rep.status + ').');
		}
		return json;
	}

	/** Une action d'ecriture : message d'echec ou de reussite, puis rafraichissement. */
	async function agir(action, donnees, opts) {
		opts = opts || {};
		if (opts.bouton) { opts.bouton.disabled = true; }
		try {
			const r = await api(action, donnees, 'POST');
			if (!r.success) { notifier(r.message || 'Action impossible.', 'error'); return null; }
			if (opts.succes !== false) { notifier(opts.succes || r.message, 'success'); }
			return r;
		} catch (e) {
			notifier(e.message, 'error');
			return null;
		} finally {
			if (opts.bouton) { opts.bouton.disabled = false; }
			rafraichir();
		}
	}

	async function rafraichir() {
		if (enCours) { relancer = true; return; }
		enCours = true;
		try {
			const r = await api('agent_state', { filtre: filtre, q: recherche }, 'GET');
			if (r.success) { afficher(r); } else { horsLigne(r.message); }
		} catch (e) {
			horsLigne(e.message);
		} finally {
			enCours = false;
			if (relancer) { relancer = false; rafraichir(); }
		}
	}
	function demarrer() {
		clearInterval(minuterie);
		minuterie = setInterval(function () { if (!document.hidden) { rafraichir(); } }, PERIODE);
	}
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { rafraichir(); } });

	/* --- Affichage ------------------------------------------------------------ */
	function pastille(texte, ouverte) {
		const p = $('fqEtat');
		p.className = 'fq-pastille-etat' + (ouverte ? ' fq-pastille-etat--ouverte' : '');
		p.querySelector('b').textContent = texte;
	}
	function horsLigne(message) {
		pastille('Connexion perdue', false);
		$('fqSessionMeta').textContent = message || 'Le serveur ne répond pas : nouvel essai dans quelques secondes.';
	}

	function afficher(r) {
		etat = r;
		afficherPlan(r.plan);   // session ouverte ou non
		$('fqSession').removeAttribute('aria-busy');
		const s = r.session;

		$('fqTravail').hidden = !s;
		$('fqFermer').hidden = !s;
		$('fqCreationAide').hidden = !s;
		const nouvelle = $('fqNouvelle');
		nouvelle.classList.toggle('fq-btn--primaire', !s);
		nouvelle.classList.toggle('fq-btn--second', !!s);
		nouvelle.querySelector('span').textContent = s ? 'Nouvelle session' : 'Ouvrir une session';

		if (!s) {
			pastille('Aucune session ouverte', false);
			$('fqSessionNom').textContent = 'La file d’attente est fermée';
			$('fqSessionMeta').textContent = 'Ouvrez une session pour distribuer des tickets et appeler les numéros.';
			return;
		}

		const c = r.comptes, ind = r.indicateurs;
		pastille('Session ouverte', true);
		$('fqSessionNom').textContent = s.name;
		$('fqSessionMeta').textContent = [dateLongue(s.date), pluriel(c.total, 'ticket'), 'ouverte le ' + dateHeure(s.created_at)]
			.filter(Boolean).join(' · ');

		// Indicateurs
		$('fqIndAttente').textContent = c.waiting;
		$('fqIndAttenteDetail').textContent = c.waiting ? 'le plus ancien depuis ' + duree(ind.plus_ancienne) : 'personne n’attend';
		$('fqIndAppeles').textContent = c.called;
		$('fqIndAppelesDetail').textContent = ind.dernier_appel ? 'dernier appel à ' + ind.dernier_appel : 'aucun appel';
		$('fqIndServis').textContent = c.serving + c.done;
		$('fqIndServisDetail').textContent = 'dont ' + pluriel(c.done, 'terminé');
		$('fqIndMoyenne').textContent = ind.attente_moyenne == null ? '—' : duree(ind.attente_moyenne);

		// Appel
		$('fqProchain').innerHTML = ind.prochain != null ? 'Prochain : <b>' + numero(ind.prochain) + '</b>' : 'Personne en attente';
		majBoutonAppel();
		afficherAppeles(r.appeles || []);

		// Onglets et liste
		page.querySelectorAll('[data-compte]').forEach(function (el) { el.textContent = c[el.dataset.compte] || 0; });
		$('fqTotal').textContent = pluriel(c.total, 'ticket');
		if (r.filtre === filtre) { afficherListe(r.tickets || [], !!r.tronque); }

		// Annonce
		const a = r.annonce || {};
		$('fqAnnonceEtat').textContent = a.message
			? 'Dernière annonce' + (a.timestamp ? ', à ' + String(a.timestamp).slice(11, 16) : '') + ' : « ' + a.message + ' »'
			: 'Aucune annonce diffusée.';

		afficherMusique(r.musique);
	}

	function boutonsActions(t) {
		return (ACTIONS[t.status] || []).map(function (a) {
			const libelle = a[1] + ' : ticket ' + numero(t.ticket_number);
			return '<button type="button" class="fq-icone' + (a[3] ? ' fq-icone--' + a[3] : '') + '" data-action="' + a[0] + '"'
				+ ' data-id="' + t.id + '" data-numero="' + t.ticket_number + '" title="' + esc(a[1]) + '" aria-label="' + esc(libelle) + '">'
				+ '<i class="bi ' + a[2] + '" aria-hidden="true"></i></button>';
		}).join('');
	}

	function afficherAppeles(liste) {
		const sig = JSON.stringify(liste);
		$('fqAucunAppel').hidden = liste.length > 0;
		$('fqRappelerTous').hidden = liste.length < 2;
		if (signatures.appeles === sig) { return; }
		signatures.appeles = sig;
		$('fqAppeles').innerHTML = liste.map(function (t) {
			const detail = [t.mention, t.heure ? 'appelé à ' + t.heure : ''].filter(Boolean).join(' · ');
			return '<li class="fq-appele">'
				+ '<b class="fq-num fq-num--grand">' + numero(t.ticket_number) + '</b>'
				+ '<span class="fq-appele__qui"><b>' + (t.student_name ? esc(t.student_name) : 'Sans nom') + '</b>'
				+ (detail ? '<small>' + esc(detail) + '</small>' : '') + '</span>'
				+ '<span class="fq-ligne__actions">' + boutonsActions(Object.assign({ status: 'called' }, t)) + '</span>'
				+ '</li>';
		}).join('');
	}

	function afficherListe(tickets, tronque) {
		const sig = filtre + '|' + recherche + '|' + JSON.stringify(tickets);
		$('fqListeTronque').hidden = !tronque;
		const vide = $('fqListeVide');
		vide.hidden = tickets.length > 0;
		vide.textContent = recherche
			? 'Aucun ticket ne correspond à « ' + recherche + ' ».'
			: (filtre === 'waiting' ? 'Personne n’attend.' : 'Aucun ticket dans cette liste.');
		if (signatures.liste === sig) { return; }
		signatures.liste = sig;
		$('fqListe').innerHTML = tickets.map(function (t) {
			let attente = '';
			if (t.attente != null) {
				attente = t.status === 'waiting' ? 'attend depuis ' + duree(t.attente) : 'a attendu ' + duree(t.attente);
			}
			const detail = [t.mention, t.heure ? 'arrivé à ' + t.heure : '', attente].filter(Boolean).join(' · ');
			return '<li class="fq-ligne">'
				+ '<b class="fq-num">' + numero(t.ticket_number) + '</b>'
				+ '<span class="fq-ligne__qui">'
				+ (t.student_name ? '<b>' + esc(t.student_name) + '</b>' : '<b class="fq-anonyme">Sans nom</b>')
				+ (detail ? '<small>' + esc(detail) + '</small>' : '') + '</span>'
				// L'etat n'est rappele que dans « Tous » : ailleurs, l'onglet le dit deja
				+ (filtre === 'all' ? '<span class="fq-statut fq-statut--' + t.status + '">' + LIBELLES[t.status] + '</span>' : '')
				+ '<span class="fq-ligne__actions">' + boutonsActions(t) + '</span>'
				+ '</li>';
		}).join('');
	}

	/* --- Session -------------------------------------------------------------- */
	const formCreation = $('fqCreation');
	function basculerCreation(ouvrir) {
		formCreation.hidden = !ouvrir;
		$('fqNouvelle').setAttribute('aria-expanded', String(ouvrir));
		if (ouvrir) { $('fqNomSession').focus(); }
	}
	$('fqNouvelle').addEventListener('click', function () { basculerCreation(formCreation.hidden); });
	$('fqAnnulerCreation').addEventListener('click', function () { basculerCreation(false); $('fqNouvelle').focus(); });

	formCreation.addEventListener('submit', async function (e) {
		e.preventDefault();
		const nom = $('fqNomSession').value.trim();
		if (!nom) { notifier('Donnez un nom à la session.', 'error'); $('fqNomSession').focus(); return; }
		if (etat && etat.session) {
			const ok = await confirmer('Ouvrir « ' + nom + ' » ferme la session « ' + etat.session.name + ' » : '
				+ 'ses tickets en attente ne seront plus appelés.', 'Ouvrir une nouvelle session', 'Ouvrir la session', true);
			if (!ok) { return; }
		}
		const r = await agir('create_session', { session_name: nom, session_date: $('fqDateSession').value },
			{ bouton: formCreation.querySelector('[type=submit]'), succes: 'Session « ' + nom + ' » ouverte' });
		if (r) { $('fqNomSession').value = ''; basculerCreation(false); chargerHistorique(); }
	});

	$('fqFermer').addEventListener('click', async function () {
		if (!etat || !etat.session) { return; }
		const attente = etat.comptes.waiting;
		const ok = await confirmer('Fermer « ' + etat.session.name + ' » ?'
			+ (attente ? '\n' + pluriel(attente, 'ticket') + ' en attente ne ' + (attente > 1 ? 'seront' : 'sera') + ' plus ' + (attente > 1 ? 'appelés.' : 'appelé.') : ''),
			'Fermer la session', 'Fermer la session', true);
		if (!ok) { return; }
		const r = await agir('close_session', { session_id: etat.session.id }, { bouton: this, succes: 'Session fermée' });
		if (r) { chargerHistorique(); }
	});

	/* --- Appel ---------------------------------------------------------------- */
	const champNombre = $('fqNombre');
	function nombre() { return Math.min(10, Math.max(1, parseInt(champNombre.value, 10) || 1)); }
	function majBoutonAppel() {
		const n = nombre();
		$('fqAppelerTexte').textContent = n === 1 ? 'Appeler le suivant' : 'Appeler les ' + n + ' suivants';
		$('fqMoins').disabled = n <= 1;
		$('fqPlus').disabled = n >= 10;
		$('fqAppeler').disabled = !etat || !etat.session || !etat.comptes.waiting;
	}
	$('fqMoins').addEventListener('click', function () { champNombre.value = nombre() - 1; majBoutonAppel(); });
	$('fqPlus').addEventListener('click', function () { champNombre.value = nombre() + 1; majBoutonAppel(); });
	champNombre.addEventListener('input', majBoutonAppel);
	champNombre.addEventListener('change', function () { champNombre.value = nombre(); majBoutonAppel(); });

	$('fqAppeler').addEventListener('click', async function () {
		if (!etat || !etat.session) { return; }
		const r = await agir('call_next', { session_id: etat.session.id, batch_size: nombre() }, { bouton: this, succes: false });
		if (r) {
			bip();
			notifier('Appelé' + (r.called_tickets.length > 1 ? 's' : '') + ' : ' + r.called_tickets.map((t) => numero(t.ticket_number)).join(', '), 'success');
		}
	});
	$('fqRappelerTous').addEventListener('click', async function () {
		if (!etat || !etat.session) { return; }
		if (await agir('recall_all', { session_id: etat.session.id }, { bouton: this })) { bip(); }
	});

	// Boutons d'action des listes (appeles et tickets), par delegation
	async function surAction(e) {
		const b = e.target.closest('button[data-action]');
		if (!b || !etat || !etat.session) { return; }
		const action = b.dataset.action, n = b.dataset.numero;
		const donnees = (action === 'call_specific' || action === 'recall')
			? { session_id: etat.session.id, ticket_number: n }
			: { ticket_id: b.dataset.id };
		const r = await agir(action, donnees, { bouton: b });
		if (r && (action === 'call_specific' || action === 'recall')) { bip(); }
	}
	$('fqAppeles').addEventListener('click', surAction);
	$('fqListe').addEventListener('click', surAction);

	/* --- Nouveau ticket --------------------------------------------------------- */
	$('fqTicketForm').addEventListener('submit', async function (e) {
		e.preventDefault();
		if (!etat || !etat.session) { return; }
		const nom = $('fqTicketNom').value.trim(), mention = $('fqTicketMention').value;
		const r = await agir('generate_ticket', { session_id: etat.session.id, student_name: nom, mention: mention },
			{ bouton: $('fqCreer'), succes: false });
		if (!r) { return; }
		$('fqDernierNum').textContent = numero(r.ticket.number);
		$('fqDernierQui').textContent = [nom, mention].filter(Boolean).join(' · ');
		$('fqDernier').hidden = false;
		notifier('Ticket ' + numero(r.ticket.number) + ' créé', 'success');
		$('fqTicketNom').value = '';
		$('fqTicketNom').focus();
	});

	/* --- Liste : onglets et recherche ------------------------------------------- */
	const onglets = $('fqOnglets');
	onglets.addEventListener('click', function (e) {
		const b = e.target.closest('[data-filtre]');
		if (!b) { return; }
		filtre = b.dataset.filtre;
		onglets.querySelectorAll('[data-filtre]').forEach(function (o) { o.setAttribute('aria-selected', String(o === b)); });
		rafraichir();
	});
	// Fleches gauche / droite entre les onglets
	onglets.addEventListener('keydown', function (e) {
		if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
		const tous = Array.from(onglets.querySelectorAll('[data-filtre]'));
		const i = tous.indexOf(document.activeElement);
		if (i < 0) { return; }
		const suivant = tous[(i + (e.key === 'ArrowRight' ? 1 : tous.length - 1)) % tous.length];
		suivant.focus();
		suivant.click();
		e.preventDefault();
	});
	let minuterieRecherche = 0;
	$('fqRecherche').addEventListener('input', function () {
		clearTimeout(minuterieRecherche);
		minuterieRecherche = setTimeout(() => { recherche = this.value.trim(); rafraichir(); }, 250);
	});

	/* --- Annonce vocale ----------------------------------------------------------- */
	const texteAnnonce = $('fqAnnonceTexte');
	function majCompte() { $('fqAnnonceCompte').textContent = texteAnnonce.value.length + ' / 500'; }
	texteAnnonce.addEventListener('input', majCompte);
	page.querySelectorAll('.fq-modele').forEach(function (b) {
		b.addEventListener('click', function () { texteAnnonce.value = b.dataset.texte; majCompte(); texteAnnonce.focus(); });
	});
	$('fqDiffuser').addEventListener('click', function () {
		const message = texteAnnonce.value.trim();
		if (!message) { notifier('Écrivez le message à diffuser, ou choisissez une annonce toute prête.', 'error'); texteAnnonce.focus(); return; }
		agir('set_tts_message', { message: message }, { bouton: this, succes: 'Annonce envoyée à l’écran public' });
	});
	$('fqEffacerAnnonce').addEventListener('click', async function () {
		if (await agir('clear_tts_message', {}, { bouton: this })) { texteAnnonce.value = ''; majCompte(); }
	});
	$('fqEcouter').addEventListener('click', function () {
		const message = texteAnnonce.value.trim();
		if (!message) { notifier('Écrivez d’abord le message à écouter.', 'error'); return; }
		if (!('speechSynthesis' in window)) { notifier('Ce navigateur ne sait pas lire un texte à voix haute.', 'error'); return; }
		speechSynthesis.cancel();
		const voix = new SpeechSynthesisUtterance(message);
		voix.lang = 'fr-FR'; voix.rate = 0.9; voix.pitch = 1.05;
		const fr = speechSynthesis.getVoices().find((v) => v.lang.startsWith('fr'));
		if (fr) { voix.voice = fr; }
		speechSynthesis.speak(voix);
	});

	/* --- Videos de l'ecran public -------------------------------------------------- */
	let musique = { playlist: [], current_index: 0, playing: 0, volume: 50, video_fullscreen: 0 };
	let tire = null;               // index de la video glissee
	let volumeTouche = 0;          // horodatage du dernier reglage local du volume

	function afficherMusique(m) {
		if (!m) { return; }
		musique = m;
		const liste = m.playlist || [];
		const actuel = liste[m.current_index];

		const lecture = $('fqLecture');
		lecture.querySelector('i').className = 'bi ' + (m.playing ? 'bi-pause-fill' : 'bi-play-fill');
		lecture.querySelector('span').textContent = m.playing ? 'Pause' : 'Lecture';
		lecture.disabled = !liste.length;
		$('fqPrec').disabled = $('fqSuiv').disabled = liste.length < 2;
		$('fqPleinEcran').checked = !!m.video_fullscreen;
		if (Date.now() - volumeTouche > 3000 && document.activeElement !== $('fqVolume')) {
			$('fqVolume').value = m.volume;
			$('fqVolumeV').textContent = m.volume + ' %';
		}
		$('fqVideoEtat').textContent = !liste.length ? ''
			: (m.playing && actuel ? 'En lecture : ' + (actuel.title || actuel.video_id) + ' (' + (m.current_index + 1) + '/' + liste.length + ')'
			                       : 'En pause · ' + pluriel(liste.length, 'vidéo'));
		$('fqAucuneVideo').hidden = liste.length > 0;

		if (tire !== null) { return; }   // pas de redessin pendant un glisser
		const sig = JSON.stringify([liste, m.current_index, m.playing]);
		if (signatures.videos === sig) { return; }
		signatures.videos = sig;
		$('fqVideos').innerHTML = liste.map(function (v, i) {
			const active = i === m.current_index;
			const titre = v.title || v.video_id;
			return '<li class="fq-video' + (active ? ' fq-video--active' : '') + '" draggable="true" data-index="' + i + '">'
				+ '<span class="fq-video__rang">' + (active && m.playing ? '<i class="bi bi-soundwave" aria-label="En lecture"></i>' : (i + 1)) + '</span>'
				+ '<img src="https://i.ytimg.com/vi/' + encodeURIComponent(v.video_id) + '/default.jpg" alt="" loading="lazy">'
				+ '<span class="fq-video__titre" title="' + esc(titre) + '">' + esc(titre) + '</span>'
				+ '<span class="fq-video__boutons">'
				+ '<button type="button" class="fq-icone" data-video="jouer" aria-label="Lire « ' + esc(titre) + ' »" title="Lire"><i class="bi bi-play-fill" aria-hidden="true"></i></button>'
				+ '<button type="button" class="fq-icone" data-video="monter" aria-label="Monter « ' + esc(titre) + ' »" title="Monter"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up" aria-hidden="true"></i></button>'
				+ '<button type="button" class="fq-icone" data-video="descendre" aria-label="Descendre « ' + esc(titre) + ' »" title="Descendre"' + (i === liste.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down" aria-hidden="true"></i></button>'
				+ '<button type="button" class="fq-icone fq-icone--danger" data-video="retirer" aria-label="Retirer « ' + esc(titre) + ' »" title="Retirer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
				+ '</span></li>';
		}).join('');
	}

	const listeVideos = $('fqVideos');
	listeVideos.addEventListener('click', function (e) {
		const b = e.target.closest('[data-video]');
		if (!b) { return; }
		const i = +b.closest('[data-index]').dataset.index;
		const quoi = b.dataset.video;
		if (quoi === 'jouer') { agir('set_playlist_index', { index: i }, { bouton: b, succes: false }); }
		if (quoi === 'retirer') { agir('remove_from_playlist', { index: i }, { bouton: b }); }
		if (quoi === 'monter') { agir('reorder_playlist', { from_index: i, to_index: i - 1 }, { bouton: b, succes: false }); }
		if (quoi === 'descendre') { agir('reorder_playlist', { from_index: i, to_index: i + 1 }, { bouton: b, succes: false }); }
	});
	listeVideos.addEventListener('dragstart', function (e) {
		const li = e.target.closest('[data-index]');
		if (!li) { return; }
		tire = +li.dataset.index;
		li.classList.add('fq-video--tiree');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', String(tire));
	});
	listeVideos.addEventListener('dragover', function (e) {
		const li = e.target.closest('[data-index]');
		if (tire === null || !li) { return; }
		e.preventDefault();
		listeVideos.querySelectorAll('.fq-video--cible').forEach((x) => x.classList.remove('fq-video--cible'));
		li.classList.add('fq-video--cible');
	});
	listeVideos.addEventListener('drop', function (e) {
		const li = e.target.closest('[data-index]');
		if (tire === null || !li) { return; }
		e.preventDefault();
		const de = tire, vers = +li.dataset.index;
		tire = null;
		if (de !== vers) { signatures.videos = ''; agir('reorder_playlist', { from_index: de, to_index: vers }, { succes: false }); }
	});
	listeVideos.addEventListener('dragend', function () {
		tire = null;
		listeVideos.querySelectorAll('.fq-video--tiree, .fq-video--cible').forEach((x) => x.classList.remove('fq-video--tiree', 'fq-video--cible'));
	});

	$('fqAjoutForm').addEventListener('submit', async function (e) {
		e.preventDefault();
		const champ = $('fqAjoutUrl'), url = champ.value.trim();
		if (!url) { notifier('Collez le lien d’une vidéo YouTube.', 'error'); champ.focus(); return; }
		if (await agir('add_to_playlist', { youtube_url: url }, { bouton: this.querySelector('[type=submit]') })) { champ.value = ''; }
	});
	$('fqTitres').addEventListener('click', function () {
		if (!(musique.playlist || []).length) { notifier('La liste de lecture est vide.', 'info'); return; }
		agir('refresh_playlist_titles', {}, { bouton: this });
	});
	$('fqVider').addEventListener('click', async function () {
		if (!(musique.playlist || []).length) { return; }
		if (await confirmer('Retirer les ' + pluriel(musique.playlist.length, 'vidéo') + ' de la liste de lecture ?', 'Vider la liste de lecture', 'Vider', true)) {
			agir('clear_playlist', {}, { bouton: this });
		}
	});
	$('fqPrec').addEventListener('click', function () { agir('playlist_prev', {}, { bouton: this, succes: false }); });
	$('fqSuiv').addEventListener('click', function () { agir('playlist_next', {}, { bouton: this, succes: false }); });

	function envoyerMusique(lecture, bouton) {
		const actuel = (musique.playlist || [])[musique.current_index];
		return agir('set_music', { youtube_url: actuel ? actuel.url : '', playing: lecture ? 1 : 0, volume: $('fqVolume').value },
			{ bouton: bouton, succes: false });
	}
	$('fqLecture').addEventListener('click', function () { envoyerMusique(!musique.playing, this); });
	let minuterieVolume = 0;
	$('fqVolume').addEventListener('input', function () {
		volumeTouche = Date.now();
		$('fqVolumeV').textContent = this.value + ' %';
		clearTimeout(minuterieVolume);
		minuterieVolume = setTimeout(function () { envoyerMusique(!!musique.playing); }, 300);
	});
	$('fqPleinEcran').addEventListener('change', function () {
		agir('set_video_fullscreen', { fullscreen: this.checked ? 1 : 0 },
			{ succes: this.checked ? 'Vidéo en plein écran sur l’écran public' : 'Vidéo en fenêtre sur l’écran public' });
	});

	/* --- Plan du bureau sur l'ecran public -------------------------------------------- */
	/* Le bouton de l'en-tete montre ou retire le plan (/queue/plan) sur l'ecran public ;
	   l'ecran suit a son prochain rafraichissement, et le laisse passer devant un appel. */
	let planAffiche = false;
	function afficherPlan(p) {
		if (!p) { return; }
		planAffiche = !!p.affiche;
		const b = $('fqPlan');
		b.disabled = false;
		b.setAttribute('aria-pressed', String(planAffiche));
		b.querySelector('span').textContent = planAffiche ? 'Masquer le plan' : 'Afficher le plan';
		b.title = planAffiche ? 'Le plan du bureau est sur l’écran public : cliquez pour le retirer' : 'Montrer le plan du bureau sur l’écran public';
	}
	$('fqPlan').addEventListener('click', async function () {
		const r = await agir('set_plan_affiche', { affiche: planAffiche ? 0 : 1 }, { bouton: this });
		if (r && r.plan) { afficherPlan(r.plan); }
	});

	/* --- Sessions precedentes -------------------------------------------------------- */
	const historique = $('fqHistorique');
	async function chargerHistorique() {
		if (!historique.open) { return; }
		let r;
		try { r = await api('get_all_sessions', {}, 'GET'); } catch (e) { notifier(e.message, 'error'); return; }
		if (!r.success) { notifier(r.message || 'Liste des sessions indisponible.', 'error'); return; }
		const liste = (r.sessions || []);
		$('fqHistoriqueListe').innerHTML = liste.length ? liste.map(function (s) {
			const active = s.status === 'active';
			const detail = [dateLongue(s.session_date), pluriel(+s.total_tickets, 'ticket'), pluriel(+s.servis, 'servi')]
				.concat(+s.waiting ? [s.waiting + ' en attente'] : []).join(' · ');
			return '<li class="fq-session-passee" data-id="' + s.id + '" data-nom="' + esc(s.session_name) + '" data-total="' + (+s.total_tickets) + '">'
				+ '<span class="fq-session-passee__qui"><b>' + esc(s.session_name) + '</b><small>' + esc(detail) + '</small></span>'
				+ (active
					? '<span class="fq-statut fq-statut--done">En cours</span>'
					: '<span class="fq-session-passee__boutons">'
						+ '<button type="button" class="fq-btn fq-btn--second" data-session="rouvrir"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Rouvrir</button>'
						+ '<button type="button" class="fq-icone fq-icone--danger" data-session="supprimer" title="Supprimer" aria-label="Supprimer « ' + esc(s.session_name) + ' »"><i class="bi bi-trash3" aria-hidden="true"></i></button>'
						+ '</span>')
				+ '</li>';
		}).join('') : '<li class="fq-vide-court">Aucune session.</li>';
	}
	historique.addEventListener('toggle', chargerHistorique);
	$('fqHistoriqueListe').addEventListener('click', async function (e) {
		const b = e.target.closest('[data-session]');
		if (!b) { return; }
		const li = b.closest('[data-id]'), nom = li.dataset.nom, id = li.dataset.id;
		if (b.dataset.session === 'rouvrir') {
			if (etat && etat.session && !(await confirmer('Rouvrir « ' + nom + ' » ferme la session « ' + etat.session.name + ' ».',
				'Rouvrir une session', 'Rouvrir', false))) { return; }
			if (await agir('reactivate_session', { session_id: id }, { bouton: b, succes: 'Session « ' + nom + ' » rouverte' })) { chargerHistorique(); }
		} else {
			const total = +li.dataset.total;
			if (!(await confirmer('Supprimer « ' + nom + ' »' + (total ? ' et ses ' + pluriel(total, 'ticket') : '') + ' ? Cette suppression est définitive.',
				'Supprimer la session', 'Supprimer', true))) { return; }
			if (await agir('delete_session', { session_id: id }, { bouton: b, succes: 'Session supprimée' })) { chargerHistorique(); }
		}
	});

	/* --- Depart ------------------------------------------------------------------------ */
	majCompte();
	majBoutonAppel();
	rafraichir();
	demarrer();
})();
