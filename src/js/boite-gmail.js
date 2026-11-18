/* =============================================================================
   Boite mail (/settings/bulk-email) : la boite no-reply@zurcher.edu.mg avec
   l'ergonomie de Gmail, dans la charte de l'application.

   Donnees : points d'acces ?ajax=gmail-* de src/bulk-email.php
   (data/boite.gmail.php). L'ancien script de la page lui passe la main pour
   tout affichage (window.BoiteGmail.afficher) et garde l'envoi lui-meme :
   ce module pilote ses champs (memes identifiants) sans le remplacer.

   La redaction s'ouvre en fenetre en bas a droite, par-dessus la boite, comme
   dans Gmail : elle se reduit, s'agrandit, se ferme en gardant le brouillon.
   ========================================================================== */
(function () {
	'use strict';

	var BASE = (typeof window.APP_BASE === 'string' ? window.APP_BASE : '');
	var PAGE = BASE + '/settings/bulk-email';
	/* Un enseignant n'a ici que la redaction et « Mes envois » : ses messages
	   partent de sa propre adresse Google, la boite no-reply ne le concerne pas. */
	var ENSEIGNANT = window.BOITE_MODE === 'enseignant';
	var NOM_PAGE = ENSEIGNANT ? 'E-mails' : 'Boîte mail';
	var ACCUEIL = ENSEIGNANT ? 'groupes' : 'inbox';
	var LIBELLES = {
		inbox: 'Boîte de réception', starred: 'Messages suivis', important: 'Importants', sent: 'Messages envoyés',
		drafts: 'Brouillons', all: 'Tous les messages', spam: 'Spam', trash: 'Corbeille', groupes: ENSEIGNANT ? 'Mes envois' : 'Envois groupés'
	};
	var VIDES = {
		inbox: ['bi-inbox', 'Aucun message', 'Les réponses des étudiants, des candidats et des enseignants arrivent ici.'],
		starred: ['bi-star', 'Aucun message suivi', 'Cliquez sur l’étoile d’un message pour le retrouver ici.'],
		important: ['bi-bookmark', 'Aucun message important', 'Gmail range ici les messages qu’il juge importants.'],
		sent: ['bi-send', 'Aucun message envoyé', 'Les messages envoyés depuis cette boîte apparaîtront ici.'],
		drafts: ['bi-file-earmark', 'Aucun brouillon', 'Les brouillons enregistrés dans Gmail apparaissent ici.'],
		all: ['bi-envelope', 'Aucun message', 'Cette boîte ne contient encore aucun message.'],
		spam: ['bi-shield-check', 'Aucun spam', 'Gmail n’a mis aucun message de côté.'],
		trash: ['bi-trash', 'La corbeille est vide', 'Les messages supprimés y restent 30 jours avant d’être effacés.'],
		groupes: ENSEIGNANT
			? ['bi-send', 'Aucun envoi', 'Les messages que vous envoyez d’ici sont listés ici, et dans les « Messages envoyés » de votre Gmail. Les réponses arrivent dans votre boîte Gmail.']
			: ['bi-people', 'Aucun envoi groupé', 'Les envois à des groupes (étudiants, enseignants…) faits depuis l’application sont listés ici.']
	};
	var GROUPES = ENSEIGNANT
		? { mes_cours: 'Mes étudiants' }
		: { students: 'Tous les étudiants', students_unregularized: 'Étudiants avec un solde à payer', teachers: 'Tous les enseignants', users: 'Tous les comptes de l’application' };
	var COULEURS = ['#5a6fda', '#a88d5c', '#2f9e77', '#c2410c', '#7c3aed', '#be123c', '#0e7490', '#b45309', '#4658c2', '#64748b'];

	var etat = { dossier: ACCUEIL, page: 0, q: '', total: 0, parPage: 50, items: [], message: null, numero: 0, defilement: 0, charge: false };

	function $(id) { return document.getElementById(id); }
	function noeud(balise, classe, texte) {
		var n = document.createElement(balise);
		if (classe) { n.className = classe; }
		if (texte !== undefined && texte !== null) { n.textContent = String(texte); }
		return n;
	}
	function icone(nom) { var i = noeud('i', 'bi ' + nom); i.setAttribute('aria-hidden', 'true'); return i; }
	function bouton(classe, nomIcone, titre, action, texte) {
		var b = noeud('button', classe);
		b.type = 'button';
		b.title = titre;
		if (!texte) { b.setAttribute('aria-label', titre); }
		if (nomIcone) { b.appendChild(icone(nomIcone)); }
		if (texte) { b.appendChild(noeud('span', '', texte)); }
		if (action) { b.dataset.action = action; }
		return b;
	}
	function jeton() {
		var champ = document.querySelector('#bulkMailForm input[name="csrf_token"]');
		return champ ? champ.value : '';
	}
	function pluriel(n, un, plusieurs) { return n.toLocaleString('fr-FR') + ' ' + (n > 1 ? plusieurs : un); }

	/* ---------------------------------------------------------------- dates */
	function dateCourte(iso) {
		if (!iso) { return ''; }
		var d = new Date(iso), maintenant = new Date();
		if (isNaN(d)) { return ''; }
		if (d.toDateString() === maintenant.toDateString()) { return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }); }
		if (d.getFullYear() === maintenant.getFullYear()) { return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }); }
		return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
	}
	function dateLongue(iso, sansEcart) {
		if (!iso) { return ''; }
		var d = new Date(iso);
		if (isNaN(d)) { return ''; }
		var texte = d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
			+ ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
		if (sansEcart) { return texte; }
		var ecart = Math.round((Date.now() - d.getTime()) / 60000);
		if (ecart >= 0 && ecart < 60) { texte += ' (il y a ' + Math.max(1, ecart) + ' min)'; }
		else if (ecart >= 60 && ecart < 1440) { var h = Math.round(ecart / 60); texte += ' (il y a ' + h + ' h)'; }
		else if (ecart >= 1440 && ecart < 10080) { var j = Math.round(ecart / 1440); texte += ' (il y a ' + j + ' jour' + (j > 1 ? 's' : '') + ')'; }
		return texte;
	}
	function taille(octets) {
		octets = Number(octets || 0);
		if (octets < 1024) { return octets + ' o'; }
		if (octets < 1048576) { return Math.round(octets / 1024) + ' Ko'; }
		return (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo';
	}
	function nomAffiche(adresse) { return adresse ? (adresse.nom || adresse.email || '') : ''; }
	function couleur(texte) {
		var h = 0;
		for (var k = 0; k < texte.length; k++) { h = (h * 31 + texte.charCodeAt(k)) % 997; }
		return COULEURS[h % COULEURS.length];
	}

	/* ----------------------------------------------------- retours visuels */
	var minuterieAnnonce = null;
	function annoncer(texte) {
		var a = $('gmAnnonce');
		if (!a) { return; }
		a.textContent = texte;
		a.classList.add('gm-visible');
		clearTimeout(minuterieAnnonce);
		minuterieAnnonce = setTimeout(function () { a.classList.remove('gm-visible'); }, 4500);
	}
	function chargement(oui) { var p = $('gmProgres'); if (p) { p.hidden = !oui; } }

	/** Petite fenetre de confirmation, aux couleurs de l'application. */
	function confirmer(titre, texteHtml, libelleOui, danger) {
		return new Promise(function (resoudre) {
			var d = $('bmDialogue');
			$('bmDialogueTitre').textContent = titre;
			$('bmDialogueTexte').innerHTML = texteHtml;
			var oui = $('bmDialogueOui');
			oui.textContent = libelleOui;
			oui.className = 'bm-bouton' + (danger ? ' bm-bouton--danger' : '');
			d.hidden = false;
			oui.focus();
			function fin(reponse) {
				d.hidden = true;
				d.removeEventListener('click', clic);
				document.removeEventListener('keydown', touche, true);
				resoudre(reponse);
			}
			function clic(e) {
				var b = e.target.closest('[data-reponse]');
				if (b) { fin(b.dataset.reponse === 'oui'); }
				else if (e.target === d) { fin(false); }
			}
			function touche(e) { if (e.key === 'Escape') { e.stopPropagation(); fin(false); } }
			d.addEventListener('click', clic);
			document.addEventListener('keydown', touche, true);
		});
	}
	function echapper(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }

	function demander(params, options) {
		var url = PAGE + '?' + new URLSearchParams(params).toString();
		return fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }, options || {}))
			.then(function (r) {
				return r.json().catch(function () { throw new Error('Réponse illisible du serveur.'); }).then(function (j) {
					if (!r.ok || !j || (j.ok === false) || (j.success === false)) {
						throw new Error((j && (j.erreur || j.message || j.error)) || 'La boîte mail n’a pas répondu.');
					}
					return j;
				});
			});
	}

	/* ===================================================== boite : liste */
	function vue(nom) {
		$('gmOutilsListe').hidden = nom !== 'liste';
		$('mailboxList').hidden = nom !== 'liste';
		$('gmLecture').hidden = nom !== 'lecture';
	}
	function activerMenu(dossier) {
		document.querySelectorAll('[data-mailbox-nav]').forEach(function (lien) {
			if (lien.classList.contains('bm-nouveau')) { return; }
			lien.classList.toggle('active', lien.getAttribute('data-mailbox-nav') === dossier);
		});
	}
	function majAdresse(box, remplacer) {
		try {
			var p = new URLSearchParams();
			p.set('box', box);
			if (etat.q && box === 'all') { p.set('q', etat.q); }
			window.history[remplacer ? 'replaceState' : 'pushState']({ box: box }, '', PAGE + '?' + p.toString());
		} catch (e) { /* adresse inchangee */ }
	}

	/** Point d'entree : un dossier, ou « compose ». Appele aussi par l'ancien script. */
	function afficher(box, majUrl) {
		box = String(box || 'inbox');
		if (box === 'favorites') { box = 'starred'; }
		if (box === 'compose') {
			ouvrirRedaction();
			if (!etat.charge) { charger(); }
			return;
		}
		if (!LIBELLES[box] || ENSEIGNANT) { box = ACCUEIL; } // un enseignant n'a que « Mes envois »
		/* Autre dossier : son squelette tout de suite, pas la liste du precedent. */
		if (box !== etat.dossier || etat.q) { etat.items = []; }
		etat.q = '';
		viderRecherche();
		etat.dossier = box;
		etat.page = 0;
		if (majUrl) { majAdresse(box); }
		charger();
	}

	function squelette() {
		var liste = $('mailboxList');
		liste.textContent = '';
		for (var k = 0; k < 8; k++) {
			var l = noeud('div', 'bm-squelette');
			l.appendChild(noeud('span')); l.appendChild(noeud('span')); l.appendChild(noeud('span'));
			liste.appendChild(l);
		}
	}

	function charger() {
		var numero = ++etat.numero;
		etat.charge = true;
		vue('liste');
		activerMenu(etat.q ? '' : etat.dossier);
		chargement(true);
		if (!etat.items.length || $('mailboxList').querySelector('.gm-vide, .gm-erreur')) { squelette(); }
		majTitre();
		var requete = etat.dossier === 'groupes'
			? demander({ ajax: 'mailbox-view', box: 'sent' }).then(adapterGroupes)
			: demander({ ajax: 'gmail-liste', dossier: etat.dossier, page: etat.page, q: etat.q });
		requete.then(function (donnees) {
			if (numero !== etat.numero) { return; }
			etat.items = donnees.items || [];
			etat.total = Number(donnees.total || 0);
			etat.parPage = Number(donnees.parPage || 50);
			rendreListe();
			if (etat.dossier !== 'groupes') { completer(numero); }
		}).catch(function (err) {
			if (numero !== etat.numero) { return; }
			var liste = $('mailboxList');
			liste.textContent = '';
			var e = noeud('div', 'gm-erreur', err.message + ' ');
			var reessayer = noeud('button', 'bm-lien', 'Réessayer');
			reessayer.type = 'button';
			reessayer.addEventListener('click', charger);
			e.appendChild(reessayer);
			liste.appendChild(e);
		}).then(function () { if (numero === etat.numero) { chargement(false); } });
	}

	function majTitre() {
		var n = Number(($('countInbox') || {}).textContent || 0);
		document.title = (n > 0 ? '(' + n + ') ' : '') + (etat.q ? 'Recherche' : LIBELLES[etat.dossier]) + ' · ' + NOM_PAGE;
	}

	/** Les envois groupes : l'historique de l'application. */
	function adapterGroupes(donnees) {
		var items = (donnees.items || []).map(function (m) {
			var stats = Number(m.recipient_total || 0) > 0
				? ' — ' + Number(m.sent_total || 0) + ' envoyés sur ' + Number(m.recipient_total || 0) + (Number(m.failed_total || 0) ? ', ' + Number(m.failed_total) + ' échecs' : '')
				: '';
			/* Ses propres envois : a combien, plutot que son nom sur chaque ligne. */
			var qui = ENSEIGNANT ? 'À ' + pluriel(Number(m.recipient_total || 0), 'destinataire', 'destinataires') : String(m.created_by || 'Application');
			return {
				uid: Number(m.message_id || m.mailbox_id || 0), lu: true, suivi: false, pieces: [], groupe: true,
				de: { nom: qui, email: '' }, a: [],
				objet: String(m.subject || ''), extrait: String(m.preview_text || '') + stats, date: String(m.created_at || '').replace(' ', 'T')
			};
		});
		return { items: items, total: items.length, parPage: items.length || 50 };
	}

	function etatVide() {
		var v = etat.q
			? ['bi-search', 'Aucun résultat', 'Rien ne correspond à « ' + etat.q + ' ». Essayez un nom, une adresse ou un mot de l’objet.']
			: (VIDES[etat.dossier] || ['bi-envelope', 'Aucun message', '']);
		var bloc = noeud('div', 'gm-vide');
		var rond = noeud('span', 'gm-vide__icone');
		rond.appendChild(icone(v[0]));
		bloc.appendChild(rond);
		bloc.appendChild(noeud('h3', '', v[1]));
		bloc.appendChild(noeud('p', '', v[2]));
		if (!etat.q && (etat.dossier === 'inbox' || etat.dossier === 'sent' || etat.dossier === 'groupes')) {
			var b = bouton('bm-bouton', 'bi-pencil-square', 'Écrire un nouveau message', 'nouveau', 'Nouveau message');
			bloc.appendChild(b);
		}
		return bloc;
	}

	function rendreListe() {
		var liste = $('mailboxList');
		liste.textContent = '';
		majPagination();
		$('gmToutCocher').checked = false;
		majSelection();
		if (!etat.items.length) { liste.appendChild(etatVide()); return; }
		var envoyes = etat.dossier === 'sent' || etat.dossier === 'drafts';
		etat.items.forEach(function (m) {
			var ligne = noeud('div', 'gm-ligne' + (m.lu ? '' : ' gm-non-lu'));
			ligne.tabIndex = 0;
			ligne.setAttribute('role', 'listitem');
			ligne.dataset.uid = m.uid;
			ligne.title = m.lu ? 'Ouvrir le message' : 'Message non lu : cliquez pour l’ouvrir';

			var c = noeud('span', 'gm-ligne__case');
			var coche = noeud('input', 'gm-case');
			coche.type = 'checkbox';
			coche.setAttribute('aria-label', 'Sélectionner ce message');
			coche.title = 'Sélectionner';
			c.appendChild(coche);
			ligne.appendChild(c);

			var e = noeud('span', 'gm-ligne__etoile');
			if (!m.groupe) {
				e.appendChild(bouton('', m.suivi ? 'bi-star-fill' : 'bi-star', m.suivi ? 'Ne plus suivre' : 'Suivre ce message', m.suivi ? 'nesuivre' : 'suivre'));
			}
			ligne.appendChild(e);

			var corps = noeud('div', 'gm-ligne__corps');
			var haut = noeud('div', 'gm-ligne__haut');
			var qui = envoyes
				? 'À : ' + ((m.a || []).map(nomAffiche).filter(Boolean).join(', ') || '(aucun destinataire)')
				: (nomAffiche(m.de) || '(expéditeur inconnu)');
			haut.appendChild(noeud('span', 'gm-ligne__de', qui));
			var texte = noeud('span', 'gm-ligne__texte');
			texte.appendChild(noeud('span', 'gm-ligne__objet', m.objet || '(sans objet)'));
			texte.appendChild(noeud('span', 'gm-ligne__extrait' + (m.extrait === null ? ' gm-attente' : ''), m.extrait ? ' — ' + m.extrait : (m.extrait === null ? ' — ' : '')));
			haut.appendChild(texte);
			var date = noeud('span', 'gm-ligne__date', dateCourte(m.date));
			haut.appendChild(date);
			corps.appendChild(haut);
			if (m.pieces && m.pieces.length) {
				var pieces = noeud('div', 'gm-ligne__pieces');
				m.pieces.slice(0, 2).forEach(function (nom) {
					var puce = noeud('span', 'gm-puce-piece');
					puce.appendChild(icone(/\.pdf$/i.test(nom) ? 'bi-file-earmark-pdf-fill' : 'bi-paperclip'));
					puce.appendChild(noeud('span', '', nom));
					pieces.appendChild(puce);
				});
				if (m.pieces.length > 2) { pieces.appendChild(noeud('span', 'gm-puce-piece', '+' + (m.pieces.length - 2))); }
				corps.appendChild(pieces);
			}
			ligne.appendChild(corps);

			if (!m.groupe) {
				var actions = noeud('span', 'gm-ligne__actions');
				actionsPour(m).forEach(function (a) { actions.appendChild(bouton('bm-icone', a[0], a[1], a[2])); });
				ligne.appendChild(actions);
			}
			liste.appendChild(ligne);
		});
		liste.scrollTop = 0;
	}

	function actionsPour(m) {
		var sortie = [];
		if (etat.dossier === 'inbox') { sortie.push(['bi-archive', 'Archiver (retirer de la boîte de réception)', 'archiver']); }
		if (etat.dossier === 'trash') { sortie.push(['bi-arrow-counterclockwise', 'Remettre dans la boîte de réception', 'restaurer']); }
		else { sortie.push(['bi-trash', 'Mettre à la corbeille', 'corbeille']); }
		sortie.push(m.lu ? ['bi-envelope', 'Marquer comme non lu', 'nonlu'] : ['bi-envelope-open', 'Marquer comme lu', 'lu']);
		return sortie;
	}

	function majPagination() {
		var pag = $('gmPagination');
		var debut = etat.total ? etat.page * etat.parPage + 1 : 0;
		var fin = Math.min(etat.total, (etat.page + 1) * etat.parPage);
		pag.querySelector('span').textContent = etat.total ? (debut + '–' + fin + ' sur ' + etat.total.toLocaleString('fr-FR')) : '';
		$('gmPrecedent').disabled = etat.page <= 0;
		$('gmSuivant').disabled = fin >= etat.total;
		$('gmTitreBoite').textContent = etat.q ? 'Résultats pour « ' + etat.q + ' »' : '';
	}

	/** Extraits manquants et compteurs du menu, apres l'affichage. */
	function completer(numero) {
		var uids = etat.items.filter(function (m) { return m.extrait === null; }).map(function (m) { return m.uid; });
		demander({ ajax: 'gmail-complements', dossier: etat.q ? 'all' : etat.dossier, uids: uids.join(',') }).then(function (r) {
			majCompteurs(r.compteurs || {});
			if (numero !== etat.numero) { return; }
			var extraits = r.extraits || {};
			etat.items.forEach(function (m) {
				if (m.extrait !== null || !(m.uid in extraits)) { return; }
				m.extrait = extraits[m.uid] || '';
				var zone = document.querySelector('.gm-ligne[data-uid="' + m.uid + '"] .gm-ligne__extrait');
				if (zone) { zone.classList.remove('gm-attente'); zone.textContent = m.extrait ? ' — ' + m.extrait : ''; }
			});
		}).catch(function () { /* la liste reste affichee, sans extraits */ });
	}

	function majCompteurs(c) {
		[['inbox', 'countInbox'], ['drafts', 'gmCompteDrafts'], ['spam', 'gmCompteSpam']].forEach(function (p) {
			var zone = $(p[1]);
			if (!zone) { return; }
			var n = Number(c[p[0]] || 0);
			zone.textContent = n > 0 ? n.toLocaleString('fr-FR') : '';
			var lien = zone.closest('a');
			if (lien && p[0] !== 'drafts') {
				lien.classList.toggle('gm-non-lus', n > 0);
				lien.title = n > 0 ? pluriel(n, 'message non lu', 'messages non lus') : '';
			}
		});
		majTitre();
	}

	/* ------------------------------------------------------------- actions */
	var ANNONCES = {
		archiver: 'Message archivé : il reste dans « Tous les messages ».', corbeille: 'Message mis à la corbeille.',
		restaurer: 'Message remis dans la boîte de réception.', lu: 'Marqué comme lu.', nonlu: 'Marqué comme non lu.',
		suivre: 'Message suivi : retrouvez-le dans « Messages suivis ».', nesuivre: 'Message plus suivi.'
	};
	function agir(uid, action, silencieux) {
		var corps = new URLSearchParams({ dossier: etat.q ? 'all' : etat.dossier, uid: uid, action: action, csrf_token: jeton() });
		return demander({ ajax: 'gmail-action' }, {
			method: 'POST', body: corps,
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }
		}).then(function () {
			var m = etat.items.find(function (x) { return x.uid === Number(uid); });
			var retire = action === 'archiver' || action === 'corbeille' || action === 'restaurer';
			if (m) {
				if (action === 'suivre' || action === 'nesuivre') { m.suivi = action === 'suivre'; }
				if (action === 'lu' || action === 'nonlu') { m.lu = action === 'lu'; }
				if (retire || (etat.dossier === 'starred' && action === 'nesuivre')) {
					etat.items = etat.items.filter(function (x) { return x !== m; });
					etat.total = Math.max(0, etat.total - 1);
				}
			}
			if (!silencieux) { annoncer(ANNONCES[action] || 'C’est fait.'); }
		});
	}

	function selection() {
		return Array.prototype.map.call(document.querySelectorAll('#mailboxList .gm-ligne .gm-case:checked'), function (c) {
			return Number(c.closest('.gm-ligne').dataset.uid);
		});
	}
	function majSelection() {
		var n = selection().length;
		var zone = $('gmActionsSelection');
		if (zone) { zone.hidden = n === 0 || etat.dossier === 'groupes'; }
		document.querySelectorAll('#mailboxList .gm-ligne').forEach(function (l) {
			l.classList.toggle('gm-coche', l.querySelector('.gm-case').checked);
		});
		var archiver = document.querySelector('#gmActionsSelection [data-action="archiver"]');
		if (archiver) { archiver.hidden = etat.dossier !== 'inbox'; }
		$('gmTitreBoite').textContent = n ? pluriel(n, 'message sélectionné', 'messages sélectionnés') : (etat.q ? 'Résultats pour « ' + etat.q + ' »' : '');
	}
	function agirSurSelection(action) {
		var uids = selection();
		if (!uids.length) { return; }
		chargement(true);
		uids.reduce(function (p, uid) { return p.then(function () { return agir(uid, action, true); }); }, Promise.resolve())
			.then(function () { annoncer(pluriel(uids.length, 'message', 'messages') + ' : ' + (ANNONCES[action] || 'c’est fait.').toLowerCase()); charger(); })
			.catch(function (err) { chargement(false); annoncer(err.message); charger(); });
	}

	/* ===================================================== boite : lecture */
	function ouvrir(uid) {
		var m = etat.items.find(function (x) { return x.uid === Number(uid); });
		if (!m) { return; }
		etat.defilement = $('mailboxList').scrollTop;
		var numero = ++etat.numero;
		chargement(true);
		var requete = m.groupe
			? demander({ ajax: 'mailbox-message', box: 'sent', id: m.uid }).then(function (d) {
				return { uid: m.uid, groupe: true, objet: d.subject || m.objet, de: { nom: d.from || 'Application', email: '' }, a: [], cc: [],
					date: m.date, suivi: false, pieces: [], texte: '',
					document: '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{margin:0;font:14px/1.5 Arial,sans-serif;color:#222}img{max-width:100%;height:auto}</style></head><body>' + (d.html || '') + '</body></html>' };
			})
			: demander({ ajax: 'gmail-message', dossier: etat.q ? 'all' : etat.dossier, uid: m.uid });
		requete.then(function (msg) {
			if (numero !== etat.numero) { return; }
			etat.message = msg;
			if (!m.lu) {
				m.lu = true;
				var c = $('countInbox');
				var n = Number((c && c.textContent || '').replace(/\D/g, '')) || 0;
				if (etat.dossier === 'inbox' && n > 0) {
					majCompteurs({ inbox: n - 1, drafts: Number(($('gmCompteDrafts') || {}).textContent || 0), spam: Number(($('gmCompteSpam') || {}).textContent || 0) });
				}
			}
			rendreLecture(msg);
		}).catch(function (err) {
			if (numero === etat.numero) { annoncer(err.message); }
		}).then(function () { if (numero === etat.numero) { chargement(false); } });
	}

	function rendreLecture(msg) {
		vue('lecture');
		var zone = $('gmLecture');
		zone.textContent = '';

		var outils = noeud('div', 'gm-outils');
		outils.appendChild(bouton('bm-retour', 'bi-arrow-left', 'Revenir à la liste (Échap)', 'retour', etat.q ? 'Résultats' : LIBELLES[etat.dossier]));
		if (!msg.groupe) {
			if (etat.dossier === 'inbox') { outils.appendChild(bouton('bm-icone', 'bi-archive', 'Archiver (retirer de la boîte de réception)', 'archiver')); }
			if (etat.dossier === 'trash') { outils.appendChild(bouton('bm-icone', 'bi-arrow-counterclockwise', 'Remettre dans la boîte de réception', 'restaurer')); }
			else { outils.appendChild(bouton('bm-icone', 'bi-trash', 'Mettre à la corbeille', 'corbeille')); }
			outils.appendChild(bouton('bm-icone', 'bi-envelope', 'Marquer comme non lu', 'nonlu'));
		}
		zone.appendChild(outils);

		var defile = noeud('div', 'gm-lecture__defile');
		var titre = noeud('div', 'gm-lecture__objet');
		titre.appendChild(noeud('h2', '', msg.objet || '(sans objet)'));
		titre.appendChild(noeud('span', 'gm-etiquette', etat.q ? 'Recherche' : LIBELLES[etat.dossier]));
		defile.appendChild(titre);

		var entete = noeud('div', 'gm-entete');
		var nom = nomAffiche(msg.de) || '?';
		var avatar = noeud('span', 'gm-entete__avatar', nom.trim().charAt(0).toUpperCase());
		avatar.style.background = couleur(nom);
		avatar.setAttribute('aria-hidden', 'true');
		entete.appendChild(avatar);
		var qui = noeud('div', 'gm-entete__qui');
		var ligne = noeud('div', '');
		ligne.appendChild(noeud('span', 'gm-entete__nom', msg.de.nom || msg.de.email || 'Expéditeur inconnu'));
		if (msg.de.email && msg.de.nom) { ligne.appendChild(noeud('span', 'gm-entete__email', msg.de.email)); }
		qui.appendChild(ligne);
		var dest = (msg.a || []).concat(msg.cc || []).map(function (a) { return a.email === (window.BOITE_GMAIL_ADRESSE || '') ? 'moi' : nomAffiche(a); }).filter(Boolean);
		if (dest.length) { qui.appendChild(noeud('div', 'gm-entete__a', 'à ' + dest.join(', '))); }
		entete.appendChild(qui);
		var droite = noeud('div', 'gm-entete__droite');
		var quand = noeud('time', '', dateLongue(msg.date));
		quand.dateTime = msg.date || '';
		droite.appendChild(quand);
		if (!msg.groupe) {
			droite.appendChild(bouton('bm-icone', msg.suivi ? 'bi-star-fill' : 'bi-star', msg.suivi ? 'Ne plus suivre' : 'Suivre ce message', msg.suivi ? 'nesuivre' : 'suivre'));
			if (msg.de.email) { droite.appendChild(bouton('bm-icone', 'bi-reply', 'Répondre', 'repondre')); }
		}
		entete.appendChild(droite);
		defile.appendChild(entete);

		/* Le message sur sa feuille blanche, dans une iframe isolee : ni script,
		   ni acces a la page ; allow-same-origin seulement pour sa hauteur. */
		var papier = noeud('div', 'bm-papier');
		var cadre = noeud('iframe', 'gm-document');
		cadre.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
		cadre.setAttribute('referrerpolicy', 'no-referrer');
		cadre.title = 'Contenu du message';
		cadre.srcdoc = msg.document || '';
		cadre.addEventListener('load', function () {
			try {
				var doc = cadre.contentDocument;
				var hauteur = function () { cadre.style.height = Math.max(60, doc.documentElement.scrollHeight + 4) + 'px'; };
				hauteur();
				Array.prototype.forEach.call(doc.images, function (img) { img.addEventListener('load', hauteur); });
			} catch (e) { cadre.style.height = '600px'; }
		});
		papier.appendChild(cadre);
		defile.appendChild(papier);

		if (msg.pieces && msg.pieces.length) {
			var bloc = noeud('div', 'gm-pieces');
			bloc.appendChild(noeud('div', 'gm-pieces__titre', pluriel(msg.pieces.length, 'pièce jointe', 'pièces jointes') + ' — cliquez pour télécharger'));
			var liste = noeud('div', 'gm-pieces__liste');
			msg.pieces.forEach(function (p) {
				var carte = noeud('a', 'gm-carte-piece');
				carte.href = PAGE + '?' + new URLSearchParams({ ajax: 'gmail-piece', dossier: etat.q ? 'all' : etat.dossier, uid: msg.uid, section: p.section }).toString();
				carte.setAttribute('download', p.nom);
				carte.title = 'Télécharger ' + p.nom;
				carte.appendChild(icone(/pdf/.test(p.type) ? 'bi-file-earmark-pdf-fill' : (/^image\//.test(p.type) ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-fill')));
				var t = noeud('div', '');
				t.appendChild(noeud('b', '', p.nom));
				t.appendChild(noeud('small', '', taille(p.taille)));
				carte.appendChild(t);
				carte.appendChild(icone('bi-download'));
				liste.appendChild(carte);
			});
			bloc.appendChild(liste);
			defile.appendChild(bloc);
		}

		if (!msg.groupe && msg.de.email) {
			var rep = noeud('div', 'gm-repondre');
			rep.appendChild(bouton('bm-pilule bm-pilule--principale', 'bi-reply', 'Répondre à ' + (msg.de.nom || msg.de.email), 'repondre', 'Répondre'));
			rep.appendChild(bouton('bm-pilule', 'bi-forward', 'Transférer ce message à quelqu’un', 'transferer', 'Transférer'));
			defile.appendChild(rep);
		}
		zone.appendChild(defile);
		document.title = (msg.objet || '(sans objet)') + ' · ' + NOM_PAGE;
		outils.querySelector('[data-action="retour"]').focus();
	}

	function retourListe() {
		vue('liste');
		rendreListe();
		$('mailboxList').scrollTop = etat.defilement;
		majTitre();
	}

	/* ===================================================== redaction */
	var R = {};   // elements de la fenetre, relus a la premiere ouverture

	function elementsRedaction() {
		if (R.form) { return R; }
		R.form = $('bulkMailForm');
		R.voile = $('bmVoile');
		R.cible = $('targetType');
		R.groupe = $('bmGroupe');
		R.mentions = $('mentionFilters');
		R.niveau = $('levelFilter');
		R.manuels = $('customRecipients');
		R.objet = $('subjectInput');
		R.editeur = $('messageEditor');
		R.recherche = $('recipientSearch');
		R.puces = $('selectedRecipients');
		R.fichiers = $('attachmentsInput');
		R.compte = $('recipientPreviewCount');
		return R;
	}

	/** ouvert, reduit (la barre de titre seule) ou agrandi (au centre, la boite voilee). */
	function etatFenetre(nouvel) {
		var r = elementsRedaction();
		r.form.dataset.etat = nouvel;
		r.voile.hidden = nouvel !== 'agrandi';
		var reduire = r.form.querySelector('[data-redaction="reduire"]');
		var agrandir = r.form.querySelector('[data-redaction="agrandir"]');
		var titreR = nouvel === 'reduit' ? 'Rouvrir la fenêtre' : 'Réduire (garder sous la main)';
		reduire.title = titreR;
		reduire.setAttribute('aria-label', titreR);
		reduire.querySelector('i').className = 'bi ' + (nouvel === 'reduit' ? 'bi-chevron-up' : 'bi-dash-lg');
		var titreA = nouvel === 'agrandi' ? 'Revenir à la petite fenêtre (Échap)' : 'Agrandir';
		agrandir.title = titreA;
		agrandir.setAttribute('aria-label', titreA);
		agrandir.querySelector('i').className = 'bi ' + (nouvel === 'agrandi' ? 'bi-arrows-angle-contract' : 'bi-arrows-angle-expand');
	}
	function majTitreRedaction() {
		var r = elementsRedaction();
		$('bmRedactionTitre').textContent = r.objet.value.trim() || 'Nouveau message';
	}

	function ouvrirRedaction(etatVoulu) {
		var r = elementsRedaction();
		if (!r.form) { return; }
		r.form.style.display = '';
		etatFenetre(etatVoulu || 'ouvert');
		/* Sans nouvelle entree d'historique : « Retour » ne fait pas jouer la fenetre. */
		majAdresse('compose', true);
		document.title = 'Nouveau message · ' + NOM_PAGE;
		setTimeout(function () {
			if (r.form.dataset.etat === 'reduit') { return; }
			var vide = !r.puces.children.length && r.cible.value === 'custom';
			(vide ? r.recherche : (r.objet.value ? r.editeur : r.objet)).focus();
		}, 50);
	}

	function brouillonVide() {
		var r = elementsRedaction();
		return !r.objet.value.trim() && !r.editeur.textContent.trim() && !r.editeur.querySelector('img') && !r.manuels.value.trim()
			&& !(r.fichiers.files && r.fichiers.files.length);
	}

	function fermerRedaction(silencieux) {
		var r = elementsRedaction();
		if (!r.form) { return; }
		r.form.style.display = 'none';
		r.voile.hidden = true;
		majAdresse(etat.q ? 'all' : etat.dossier, true);
		majTitre();
		if (!silencieux && !brouillonVide()) { annoncer('Brouillon gardé : cliquez sur « Nouveau message » pour le reprendre.'); }
	}

	function viderBrouillon() {
		var r = elementsRedaction();
		r.objet.value = '';
		r.editeur.innerHTML = '';
		r.editeur.dispatchEvent(new Event('input', { bubbles: true }));
		r.manuels.value = '';
		r.manuels.dispatchEvent(new Event('input', { bubbles: true }));
		r.manuels.dispatchEvent(new Event('blur'));
		r.fichiers.value = '';
		r.fichiers.dispatchEvent(new Event('change', { bubbles: true }));
		var statut = $('mailStatus');
		if (statut) { statut.className = 'gmail-status'; statut.textContent = ''; }
		var progression = $('sendProgressWrap');
		if (progression) { progression.classList.add('hidden'); }
		var barre = $('bmBarreEnvoi');
		if (barre) { barre.style.width = '0'; }
		choisirMode('personnes');
		majTitreRedaction();
	}

	/** Appele par l'ancien script quand l'envoi a reussi. */
	function apresEnvoi(resultat) {
		var total = Number(resultat && resultat.total || 0), envoyes = Number(resultat && resultat.sent || 0), echecs = Number(resultat && resultat.failed || 0);
		var texte = total > 1
			? 'Message envoyé à ' + pluriel(envoyes, 'destinataire', 'destinataires') + (echecs ? ' (' + pluriel(echecs, 'échec', 'échecs') + ')' : '') + '.'
			: 'Message envoyé.';
		viderBrouillon();
		fermerRedaction(true);
		annoncer(texte);
		if (etat.dossier === 'sent' || etat.dossier === 'groupes') { setTimeout(charger, 900); }
	}

	/* Des personnes, ou un groupe : un seul reglage visible a la fois. */
	function modeCourant() { return elementsRedaction().cible.value === 'custom' ? 'personnes' : 'groupe'; }
	function choisirMode(mode, sansEvenement) {
		var r = elementsRedaction();
		if (mode === 'groupe') {
			if (r.cible.value === 'custom') { r.cible.value = GROUPES[r.groupe.value] ? r.groupe.value : 'students'; }
		} else {
			r.cible.value = 'custom';
		}
		if (!sansEvenement) { r.cible.dispatchEvent(new Event('change', { bubbles: true })); }
		majMode();
	}
	function majMode() {
		var r = elementsRedaction();
		var groupe = r.cible.value !== 'custom';
		$('bmModePersonnes').setAttribute('aria-selected', String(!groupe));
		$('bmModeGroupe').setAttribute('aria-selected', String(groupe));
		$('bmZonePersonnes').hidden = groupe;
		$('bmZoneGroupe').hidden = !groupe;
		if ($('bmAidePersonnes')) { $('bmAidePersonnes').hidden = groupe; }
		if (groupe) { $('bmZoneListe').hidden = true; } else if (r.manuels.value.trim() && !r.puces.children.length) { $('bmZoneListe').hidden = false; }
		if (groupe && GROUPES[r.cible.value]) { r.groupe.value = r.cible.value; }
		majPortee();
	}

	function mentionsChoisies() {
		return Array.prototype.filter.call(elementsRedaction().mentions.options, function (o) { return o.selected; });
	}
	function majPastilles() {
		var choisies = mentionsChoisies().map(function (o) { return o.value; });
		document.querySelectorAll('#bmMentions .bm-pastille').forEach(function (p) {
			var v = p.dataset.mention;
			p.setAttribute('aria-pressed', String(v === '' ? choisies.length === 0 : choisies.indexOf(v) >= 0));
		});
	}

	/** « 426 destinataires · Tous les étudiants » : toujours sous les yeux avant d'envoyer. */
	function porteeTexte() {
		var r = elementsRedaction();
		var n = Number((r.compte && r.compte.textContent || '0').replace(/\D/g, '')) || 0;
		var detail = [];
		if (r.cible.value !== 'custom') {
			detail.push(GROUPES[r.cible.value] || 'Groupe');
			if (r.cible.value === 'students' || r.cible.value === 'students_unregularized') {
				var m = mentionsChoisies().map(function (o) {
					var p = document.querySelector('#bmMentions .bm-pastille[data-mention="' + o.value + '"]');
					return p ? p.textContent : o.value;
				});
				if (m.length) { detail.push(m.join(', ')); }
				if (r.niveau.value) { detail.push(r.niveau.options[r.niveau.selectedIndex].text); }
			}
			var cours = $('bmCours');
			if (r.cible.value === 'mes_cours' && cours && cours.selectedIndex >= 0) {
				detail.push(cours.options[cours.selectedIndex].dataset.libelle || cours.options[cours.selectedIndex].text);
			}
		}
		return { n: n, detail: detail };
	}
	function majPortee() {
		var p = porteeTexte();
		var zone = $('bmPortee');
		if (!zone) { return; }
		zone.textContent = '';
		if (!p.n) { zone.textContent = 'Aucun destinataire pour l’instant'; return; }
		zone.appendChild(noeud('b', '', pluriel(p.n, 'destinataire', 'destinataires')));
		if (p.detail.length) { zone.appendChild(document.createTextNode(' · ' + p.detail.join(' · '))); }
		zone.title = pluriel(p.n, 'destinataire', 'destinataires') + (p.detail.length ? ' : ' + p.detail.join(', ') : '');
	}

	/* Pieces jointes : une etiquette par fichier, chacune retirable. */
	function rendrePiecesJointes() {
		var r = elementsRedaction();
		var zone = $('attachmentsList');
		if (!zone) { return; }
		zone.textContent = '';
		var fichiers = r.fichiers.files ? Array.prototype.slice.call(r.fichiers.files) : [];
		fichiers.forEach(function (f, k) {
			var puce = noeud('span', 'bm-piece-jointe');
			puce.appendChild(icone('bi-paperclip'));
			puce.appendChild(noeud('span', '', f.name));
			puce.appendChild(noeud('small', '', taille(f.size)));
			var x = bouton('', 'bi-x', 'Retirer ' + f.name);
			x.addEventListener('click', function () {
				try {
					var dt = new DataTransfer();
					fichiers.forEach(function (g, j) { if (j !== k) { dt.items.add(g); } });
					r.fichiers.files = dt.files;
				} catch (e) { r.fichiers.value = ''; }
				r.fichiers.dispatchEvent(new Event('change', { bubbles: true }));
			});
			puce.appendChild(x);
			zone.appendChild(puce);
		});
	}

	/* Confirmation avant tout envoi a plusieurs : on dit a qui, combien, et
	   que l'envoi ne se rattrape pas. L'ancien script fait ensuite l'envoi. */
	function brancherConfirmation() {
		var r = elementsRedaction();
		r.form.addEventListener('submit', function (e) {
			if (r.form.dataset.confirme === '1') { return; }
			e.preventDefault();
			e.stopImmediatePropagation();
			var p = porteeTexte();
			if (!p.n) {
				annoncer('Ajoutez au moins un destinataire avant d’envoyer.');
				if (modeCourant() === 'personnes') { r.recherche.focus(); }
				return;
			}
			if (!r.editeur.textContent.trim() && !r.editeur.querySelector('img')) {
				annoncer('Le message est vide : écrivez quelques mots avant d’envoyer.');
				r.editeur.focus();
				return;
			}
			var envoyer = function () {
				r.form.dataset.confirme = '1';
				if (typeof r.form.requestSubmit === 'function') { r.form.requestSubmit(); } else { r.form.dispatchEvent(new Event('submit', { cancelable: true })); }
				setTimeout(function () { delete r.form.dataset.confirme; }, 0);
			};
			if (p.n === 1 && modeCourant() === 'personnes') { envoyer(); return; }
			/* Un enseignant : qui verra quoi (Gmail, avec son adresse). */
			var visibilite = !ENSEIGNANT ? ''
				: (modeCourant() === 'personnes'
					? '<br>Un seul message : chaque destinataire verra les adresses des autres.'
					: '<br>En copie cachée : aucun étudiant ne verra l’adresse des autres.');
			confirmer(
				'Envoyer à ' + pluriel(p.n, 'destinataire', 'destinataires') + ' ?',
				'« <b>' + echapper(r.objet.value || '(sans objet)') + '</b> » partira de <b>' + echapper(window.BOITE_GMAIL_ADRESSE || 'no-reply@zurcher.edu.mg') + '</b>'
					+ (p.detail.length ? ' vers : <b>' + echapper(p.detail.join(' · ')) + '</b>' : '') + '.' + visibilite + '<br>Une fois parti, un message ne peut pas être rappelé.',
				'Envoyer à ' + pluriel(p.n, 'destinataire', 'destinataires')
			).then(function (oui) { if (oui) { envoyer(); } });
		}, true);
	}

	/* L'ancien script ecrit la progression dans #sendProgressSummary
	   (« 3 / 10 (OK: 3, KO: 0) ») : on en tire la barre. */
	function suivreEnvoi() {
		var resume = $('sendProgressSummary'), barre = $('bmBarreEnvoi');
		if (resume && barre) {
			new MutationObserver(function () {
				var m = resume.textContent.match(/(\d+)\s*\/\s*(\d+)/);
				barre.style.width = m && Number(m[2]) ? Math.min(100, Math.round(Number(m[1]) * 100 / Number(m[2]))) + '%' : '0';
			}).observe(resume, { childList: true, characterData: true, subtree: true });
		}
		if (elementsRedaction().compte) {
			new MutationObserver(majPortee).observe(elementsRedaction().compte, { childList: true, characterData: true, subtree: true });
		}
	}

	/* Repondre ou transferer : la fenetre de redaction, en « Des personnes » a
	   une seule adresse, objet et citation preremplis. Les pieces jointes ne
	   suivent pas un transfert. */
	function repondre(transferer) {
		var msg = etat.message;
		var r = elementsRedaction();
		if (!msg || !r.form) { return; }
		var continuer = function () {
			choisirMode('personnes');
			r.manuels.value = transferer ? '' : (msg.repondreA || msg.de.email || '');
			r.manuels.dispatchEvent(new Event('input', { bubbles: true }));
			r.manuels.dispatchEvent(new Event('blur'));
			$('bmZoneListe').hidden = true;
			var base = msg.objet || '';
			r.objet.value = transferer ? (/^(tr|fwd?)\s*:/i.test(base) ? base : 'Tr : ' + base) : (/^re\s*:/i.test(base) ? base : 'Re : ' + base);
			var texte = echapper(msg.texte || '').replace(/\n/g, '<br>');
			var de = (msg.de.nom ? echapper(msg.de.nom) + ' ' : '') + '&lt;' + echapper(msg.de.email) + '&gt;';
			r.editeur.innerHTML = transferer
				? '<br><br>---------- Message transféré ---------<br>De : ' + de + '<br>Date : ' + echapper(dateLongue(msg.date, true)) + '<br>Objet : ' + echapper(base) + '<br><br>' + texte
				: '<br><br>Le ' + echapper(dateLongue(msg.date, true)) + ', ' + de + ' a écrit :<br><blockquote>' + texte + '</blockquote>';
			r.editeur.dispatchEvent(new Event('input', { bubbles: true }));
			majTitreRedaction();
			ouvrirRedaction('ouvert');
			setTimeout(function () {
				if (transferer) { r.recherche.focus(); return; }
				r.editeur.focus();
				try { var s = window.getSelection(), g = document.createRange(); g.setStart(r.editeur, 0); g.collapse(true); s.removeAllRanges(); s.addRange(g); } catch (e) { /* curseur par defaut */ }
			}, 80);
		};
		if (!brouillonVide()) {
			confirmer('Remplacer le brouillon en cours ?', 'Un message est déjà en cours de rédaction. Il sera remplacé par cette réponse.', 'Remplacer').then(function (oui) { if (oui) { continuer(); } });
		} else { continuer(); }
	}

	function brancherRedaction() {
		var r = elementsRedaction();
		if (!r.form) { return; }

		/* Comme dans Gmail : un clic sur la barre de titre reduit ou rouvre. */
		$('bmRedactionTete').addEventListener('click', function (e) {
			var b = e.target.closest('[data-redaction]');
			var actuel = r.form.dataset.etat;
			if (!b) { etatFenetre(actuel === 'reduit' ? 'ouvert' : 'reduit'); return; }
			var quoi = b.dataset.redaction;
			if (quoi === 'reduire') { etatFenetre(actuel === 'reduit' ? 'ouvert' : 'reduit'); }
			if (quoi === 'agrandir') { etatFenetre(actuel === 'agrandi' ? 'ouvert' : 'agrandi'); }
			if (quoi === 'fermer') { fermerRedaction(); }
		});
		r.voile.addEventListener('click', function () { etatFenetre('ouvert'); });
		r.objet.addEventListener('input', majTitreRedaction);
		majTitreRedaction();
		r.form.querySelector('[data-redaction="supprimer"]').addEventListener('click', function () {
			if (brouillonVide()) { fermerRedaction(true); return; }
			confirmer('Supprimer ce brouillon ?', 'L’objet, le texte, les destinataires et les pièces jointes seront effacés.', 'Supprimer', true)
				.then(function (oui) { if (oui) { viderBrouillon(); fermerRedaction(true); annoncer('Brouillon supprimé.'); } });
		});

		document.querySelectorAll('.bm-mode__choix').forEach(function (b) {
			b.addEventListener('click', function () { choisirMode(b.dataset.mode); });
		});
		r.groupe.addEventListener('change', function () {
			r.cible.value = r.groupe.value;
			r.cible.dispatchEvent(new Event('change', { bubbles: true }));
			majPortee();
		});
		$('bmMentions').addEventListener('click', function (e) {
			var p = e.target.closest('.bm-pastille');
			if (!p) { return; }
			var v = p.dataset.mention;
			Array.prototype.forEach.call(r.mentions.options, function (o) {
				if (v === '') { o.selected = false; } else if (o.value === v) { o.selected = !o.selected; }
			});
			majPastilles();
			r.mentions.dispatchEvent(new Event('change', { bubbles: true }));
			majPortee();
		});
		r.niveau.addEventListener('change', majPortee);
		$('bmCollerListe').addEventListener('click', function () {
			var zone = $('bmZoneListe');
			zone.hidden = !zone.hidden;
			this.setAttribute('aria-expanded', String(!zone.hidden));
			if (!zone.hidden) { r.manuels.focus(); }
		});
		r.form.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); $('sendBtn').click(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || !$('bmDialogue').hidden) { return; }
			var suggestions = $('recipientResults');
			if (suggestions && !suggestions.classList.contains('hidden')) { suggestions.classList.add('hidden'); return; }
			if (r.form.style.display !== 'none' && r.form.dataset.etat === 'agrandi') { etatFenetre('ouvert'); return; }
			if (r.form.contains(document.activeElement)) { return; }
			if (!$('gmLecture').hidden) { retourListe(); }
		});
		/* Le brouillon ne vit que dans la page : on previent avant de la quitter. */
		window.addEventListener('beforeunload', function (e) {
			if (brouillonVide()) { return; }
			e.preventDefault();
			e.returnValue = '';
		});

		brancherConfirmation();
		suivreEnvoi();
		majPastilles();
		majMode();
		rendrePiecesJointes();
	}

	/* ------------------------------------------------------------- recherche */
	function viderRecherche() {
		var champ = $('gmRechercheChamp');
		if (champ) { champ.value = ''; }
		var x = $('gmRechercheEffacer');
		if (x) { x.hidden = true; }
	}

	function brancher() {
		var principal = $('mailboxPanel');
		if (!principal || principal.dataset.branche) { return; }
		principal.dataset.branche = '1';

		$('gmRecherche').addEventListener('submit', function (e) {
			e.preventDefault();
			var q = $('gmRechercheChamp').value.trim();
			if (!q) { afficher('inbox', true); return; }
			etat.q = q;
			etat.dossier = 'all';
			etat.page = 0;
			etat.items = [];
			$('gmRechercheEffacer').hidden = false;
			majAdresse('all');
			charger();
		});
		$('gmRechercheChamp').addEventListener('input', function () { $('gmRechercheEffacer').hidden = this.value === ''; });
		$('gmRechercheEffacer').addEventListener('click', function () { viderRecherche(); afficher('inbox', true); });

		$('gmPrecedent').addEventListener('click', function () { if (etat.page > 0) { etat.page--; charger(); } });
		$('gmSuivant').addEventListener('click', function () { etat.page++; charger(); });
		$('gmActualiser').addEventListener('click', function () { charger(); });
		$('gmToutCocher').addEventListener('change', function () {
			var oui = this.checked;
			document.querySelectorAll('#mailboxList .gm-case').forEach(function (c) { c.checked = oui; });
			majSelection();
		});
		$('gmActionsSelection').addEventListener('click', function (e) {
			var b = e.target.closest('[data-action]');
			if (b) { agirSurSelection(b.dataset.action); }
		});

		var liste = $('mailboxList');
		liste.addEventListener('click', function (e) {
			var nouveau = e.target.closest('[data-action="nouveau"]');
			if (nouveau) { afficher('compose', true); return; }
			var ligne = e.target.closest('.gm-ligne');
			if (!ligne) { return; }
			if (e.target.closest('.gm-ligne__case')) { setTimeout(majSelection, 0); return; }
			var b = e.target.closest('button[data-action]');
			if (b) {
				e.stopPropagation();
				agir(ligne.dataset.uid, b.dataset.action).then(rendreListe).catch(function (err) { annoncer(err.message); });
				return;
			}
			ouvrir(ligne.dataset.uid);
		});
		liste.addEventListener('keydown', function (e) {
			var ligne = e.target.closest('.gm-ligne');
			if (!ligne || e.target !== ligne) { return; }
			if (e.key === 'Enter') { ouvrir(ligne.dataset.uid); }
			if (e.key === 'ArrowDown' && ligne.nextElementSibling) { e.preventDefault(); ligne.nextElementSibling.focus(); }
			if (e.key === 'ArrowUp' && ligne.previousElementSibling) { e.preventDefault(); ligne.previousElementSibling.focus(); }
		});

		$('gmLecture').addEventListener('click', function (e) {
			var b = e.target.closest('[data-action]');
			if (!b || !etat.message) { return; }
			var action = b.dataset.action;
			if (action === 'retour') { retourListe(); return; }
			if (action === 'repondre' || action === 'transferer') { repondre(action === 'transferer'); return; }
			agir(etat.message.uid, action).then(function () {
				if (action === 'suivre' || action === 'nesuivre') {
					etat.message.suivi = action === 'suivre';
					b.querySelector('i').className = 'bi ' + (etat.message.suivi ? 'bi-star-fill' : 'bi-star');
					b.dataset.action = etat.message.suivi ? 'nesuivre' : 'suivre';
					b.title = etat.message.suivi ? 'Ne plus suivre' : 'Suivre ce message';
					b.setAttribute('aria-label', b.title);
					return;
				}
				retourListe();
			}).catch(function (err) { annoncer(err.message); });
		});

		brancherRedaction();

		/* Arrivee directe sur ?box=compose : la boite derriere, la fenetre devant. */
		if (window.BOITE_DEPART === 'compose') {
			etat.dossier = ACCUEIL;
			activerMenu(ACCUEIL);
			charger();
			ouvrirRedaction('ouvert');
		}
	}

	window.BoiteGmail = {
		piecesJointes: rendrePiecesJointes,
		apresEnvoi: apresEnvoi,
		afficher: function (box, majUrl) {
			brancher();
			var params = new URLSearchParams(window.location.search || '');
			if (box === 'all' && params.get('q') && !etat.q) {
				etat.q = params.get('q');
				$('gmRechercheChamp').value = etat.q;
				$('gmRechercheEffacer').hidden = false;
				etat.dossier = 'all';
				etat.page = 0;
				charger();
				return;
			}
			afficher(box, majUrl !== false);
		}
	};
	document.addEventListener('DOMContentLoaded', brancher);
})();
