/* =============================================================================
   Adresses Google (/settings/adresses-google) : le rapport et la creation des
   comptes manquants. Donnees : app/adresses-google.api.php.
   ========================================================================== */
(function () {
	'use strict';

	var racine = document.getElementById('ag');
	if (!racine) { return; }
	var API = racine.dataset.api;
	var CSRF = racine.dataset.csrf;
	var PAR_PAGE = 100;

	var ETATS = {
		active: ['Active', 'ag-badge--active', 'Le compte existe et la personne s’y est déjà connectée.'],
		jamais: ['Jamais connectée', 'ag-badge--jamais', 'Le compte existe, mais personne ne s’y est encore connecté.'],
		suspendue: ['Suspendue', 'ag-badge--suspendue', 'Compte suspendu dans Google Admin : la personne ne peut pas s’y connecter.'],
		archivee: ['Archivée', 'ag-badge--suspendue', 'Compte archivé dans Google Admin.'],
		absente: ['Absente de Google', 'ag-badge--absente', 'Aucun compte Google ne porte cette adresse : les mails qui y sont envoyés reviennent.'],
		a_corriger: ['À corriger', 'ag-badge--corriger', 'L’adresse contient un accent ou un espace : Google la refuse. Corrigez la fiche.'],
		google_seul: ['Sans fiche', 'ag-badge--google', 'Compte Google qu’aucune fiche, aucun enseignant ni aucun compte de l’application ne porte.']
	};
	var SOURCES = { etudiant: 'Étudiant', enseignant: 'Enseignant', compte: 'Compte', google: 'Google' };

	var etat = { lignes: [], statut: '', source: '', q: '', annee: false, montrees: PAR_PAGE, choisies: {}, enCours: false };

	function $(id) { return document.getElementById(id); }
	function noeud(balise, classe, texte) {
		var n = document.createElement(balise);
		if (classe) { n.className = classe; }
		if (texte !== undefined && texte !== null) { n.textContent = String(texte); }
		return n;
	}
	function icone(nom) { var i = noeud('i', 'bi ' + nom); i.setAttribute('aria-hidden', 'true'); return i; }
	function pluriel(n, un, plusieurs) { return n.toLocaleString('fr-FR') + ' ' + (n > 1 ? plusieurs : un); }
	function echapper(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
	function normaliser(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	function annoncer(type, texte, titre) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](texte, { title: titre, duration: 9000 }); }
	}

	function depuis(iso) {
		if (!iso) { return ''; }
		var d = new Date(iso);
		if (isNaN(d)) { return ''; }
		var jours = Math.floor((Date.now() - d.getTime()) / 86400000);
		if (jours <= 0) { return 'aujourd’hui'; }
		if (jours === 1) { return 'hier'; }
		if (jours < 31) { return 'il y a ' + jours + ' jours'; }
		if (jours < 365) { var m = Math.round(jours / 30); return 'il y a ' + m + ' mois'; }
		var a = Math.floor(jours / 365); return 'il y a ' + a + ' an' + (a > 1 ? 's' : '');
	}
	function dateLongue(iso) {
		var d = new Date(iso);
		return isNaN(d) ? '' : d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
	}

	/* Petite fenetre de confirmation, aux couleurs de l'application. */
	function confirmer(titre, texteHtml, libelleOui) {
		return new Promise(function (resoudre) {
			var d = $('agDialogue');
			$('agDialogueTitre').textContent = titre;
			$('agDialogueTexte').innerHTML = texteHtml;
			$('agDialogueOui').textContent = libelleOui;
			d.hidden = false;
			$('agDialogueOui').focus();
			function fin(r) { d.hidden = true; d.removeEventListener('click', clic); document.removeEventListener('keydown', touche, true); resoudre(r); }
			function clic(e) { var b = e.target.closest('[data-reponse]'); if (b) { fin(b.dataset.reponse === 'oui'); } else if (e.target === d) { fin(false); } }
			function touche(e) { if (e.key === 'Escape') { e.stopPropagation(); fin(false); } }
			d.addEventListener('click', clic);
			document.addEventListener('keydown', touche, true);
		});
	}

	/* ------------------------------------------------------------- donnees */
	function charger(forcer) {
		var tableau = $('agTableau');
		if (!etat.lignes.length) {
			tableau.innerHTML = '<div class="ag-chargement"><span class="ag-chargement__rond" aria-hidden="true"></span><p>Lecture des comptes Google du domaine…</p></div>';
		}
		$('agActualiser').disabled = true;
		racine.classList.add('ag--charge');
		return fetch(API + '?action=rapport' + (forcer ? '&forcer=1' : ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { throw new Error('Réponse illisible du serveur.'); }); })
			.then(function (j) {
				if (!j.ok) { throw new Error(j.message || 'Google n’a pas répondu.'); }
				etat.lignes = j.lignes || [];
				etat.choisies = {};
				majCompteurs(j.compteurs || {});
				$('agLuLe').textContent = 'Lu dans Google ' + (j.lu_le ? 'à ' + new Date(j.lu_le).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '');
				$('agAnneeLibelle').textContent = j.annee ? 'Étudiants de ' + j.annee : 'Étudiants de l’année';
				$('agFraicheur').hidden = false;
				$('agEtats').hidden = false;
				$('agOutils').hidden = false;
				$('agNote').hidden = false;
				rendre();
			})
			.catch(function (err) {
				tableau.innerHTML = '';
				var e = noeud('div', 'ag-erreur');
				e.appendChild(icone('bi-exclamation-triangle'));
				var t = noeud('div');
				t.appendChild(noeud('b', '', 'Google n’a pas pu être lu'));
				t.appendChild(noeud('p', '', err.message));
				e.appendChild(t);
				var b = noeud('button', 'ag-bouton ag-bouton--discret', 'Réessayer');
				b.type = 'button';
				b.addEventListener('click', function () { charger(true); });
				e.appendChild(b);
				tableau.appendChild(e);
			})
			.then(function () { $('agActualiser').disabled = false; racine.classList.remove('ag--charge'); });
	}

	function majCompteurs(c) {
		document.querySelectorAll('[data-compte]').forEach(function (b) {
			var n = Number(c[b.dataset.compte] || 0);
			b.textContent = n.toLocaleString('fr-FR');
			var bouton = b.closest('.ag-etat');
			if (bouton.dataset.statut) { bouton.hidden = n === 0 && bouton.dataset.statut !== 'absente'; }
		});
	}

	/* ------------------------------------------------------------- filtres */
	function correspond(l) {
		if (etat.statut) {
			if (etat.statut === 'suspendue') { if (l.statut !== 'suspendue' && l.statut !== 'archivee') { return false; } }
			else if (l.statut !== etat.statut) { return false; }
		}
		if (etat.source && l.sources.indexOf(etat.source) < 0) { return false; }
		if (etat.annee && !l.annee) { return false; }
		if (etat.q) {
			var texte = normaliser(l.adresse + ' ' + l.personnes.map(function (p) { return p.nom + ' ' + p.detail; }).join(' '));
			if (texte.indexOf(etat.q) < 0) { return false; }
		}
		return true;
	}

	function rendre() {
		var tableau = $('agTableau');
		var visibles = etat.lignes.filter(correspond);
		tableau.textContent = '';
		majSelection();
		if (!visibles.length) {
			var vide = noeud('div', 'ag-vide');
			var rond = noeud('span', 'ag-vide__icone');
			rond.appendChild(icone(etat.statut === 'absente' && !etat.q ? 'bi-check2-circle' : 'bi-search'));
			vide.appendChild(rond);
			if (etat.statut === 'absente' && !etat.q) {
				vide.appendChild(noeud('h3', '', 'Aucune adresse absente de Google'));
				vide.appendChild(noeud('p', '', 'Chaque adresse de ces fiches a son compte Google.'));
			} else {
				vide.appendChild(noeud('h3', '', 'Aucune adresse ne correspond'));
				vide.appendChild(noeud('p', '', 'Changez l’état, la recherche ou le type de fiche.'));
			}
			tableau.appendChild(vide);
			return;
		}

		var table = noeud('table', 'ag-table');
		var tete = noeud('thead');
		var tr = noeud('tr');
		var creables = visibles.filter(function (l) { return l.creable; });
		var thCase = noeud('th', 'ag-col-case');
		if (creables.length) {
			var tout = noeud('input');
			tout.type = 'checkbox';
			tout.title = 'Sélectionner les ' + creables.length + ' adresses à créer';
			tout.setAttribute('aria-label', tout.title);
			tout.checked = creables.every(function (l) { return etat.choisies[l.adresse]; });
			tout.addEventListener('change', function () {
				creables.forEach(function (l) { if (tout.checked) { etat.choisies[l.adresse] = true; } else { delete etat.choisies[l.adresse]; } });
				rendre();
			});
			thCase.appendChild(tout);
		}
		tr.appendChild(thCase);
		['Adresse', 'Personne', 'État dans Google', 'Dernière connexion', ''].forEach(function (t) { tr.appendChild(noeud('th', '', t)); });
		tete.appendChild(tr);
		table.appendChild(tete);

		var corps = noeud('tbody');
		visibles.slice(0, etat.montrees).forEach(function (l) { corps.appendChild(ligne(l)); });
		table.appendChild(corps);
		tableau.appendChild(table);

		var bas = noeud('div', 'ag-bas');
		bas.appendChild(noeud('span', '', 'Affichées : ' + Math.min(etat.montrees, visibles.length).toLocaleString('fr-FR') + ' sur ' + pluriel(visibles.length, 'adresse', 'adresses')));
		if (visibles.length > etat.montrees) {
			var plus = noeud('button', 'ag-bouton ag-bouton--discret', 'Afficher ' + Math.min(PAR_PAGE, visibles.length - etat.montrees) + ' de plus');
			plus.type = 'button';
			plus.addEventListener('click', function () { etat.montrees += PAR_PAGE; rendre(); });
			bas.appendChild(plus);
		}
		tableau.appendChild(bas);
	}

	function ligne(l) {
		var tr = noeud('tr', l.statut === 'absente' ? 'ag-ligne--absente' : '');
		tr.dataset.adresse = l.adresse;

		var tdCase = noeud('td', 'ag-col-case');
		if (l.creable) {
			var c = noeud('input');
			c.type = 'checkbox';
			c.checked = !!etat.choisies[l.adresse];
			c.setAttribute('aria-label', 'Sélectionner ' + l.adresse);
			c.addEventListener('change', function () { if (c.checked) { etat.choisies[l.adresse] = true; } else { delete etat.choisies[l.adresse]; } majSelection(); });
			tdCase.appendChild(c);
		}
		tr.appendChild(tdCase);

		var tdAdresse = noeud('td', 'ag-adresse');
		tdAdresse.appendChild(noeud('span', '', l.adresse));
		if (l.alias_de) { tdAdresse.appendChild(noeud('small', '', 'alias de ' + l.alias_de)); }
		tr.appendChild(tdAdresse);

		var tdPersonne = noeud('td', 'ag-personne');
		l.personnes.slice(0, 2).forEach(function (p) {
			var bloc = noeud('div');
			bloc.appendChild(noeud('b', '', p.nom || '(sans nom)'));
			bloc.appendChild(noeud('small', '', p.detail));
			tdPersonne.appendChild(bloc);
		});
		if (l.nb_personnes > 2) { tdPersonne.appendChild(noeud('small', 'ag-plus', '+ ' + (l.nb_personnes - 2) + ' autre' + (l.nb_personnes > 3 ? 's' : '') + ' fiche' + (l.nb_personnes > 3 ? 's' : ''))); }
		if (l.partagee) {
			var avis = noeud('small', 'ag-avis');
			avis.appendChild(icone('bi-exclamation-triangle'));
			avis.appendChild(document.createTextNode(' Portée par des étudiants différents'));
			avis.title = 'Un seul peut avoir le compte Google : corrigez les fiches avant de le créer.';
			tdPersonne.appendChild(avis);
		}
		tr.appendChild(tdPersonne);

		var tdEtat = noeud('td');
		var e = ETATS[l.statut] || ETATS.absente;
		var badge = noeud('span', 'ag-badge ' + e[1], e[0]);
		badge.title = e[2];
		tdEtat.appendChild(badge);
		if (l.statut === 'google_seul' && l.etat_google && ETATS[l.etat_google]) {
			tdEtat.appendChild(noeud('small', 'ag-sous-etat', ETATS[l.etat_google][0]));
		}
		tr.appendChild(tdEtat);

		var tdConnexion = noeud('td', 'ag-connexion');
		if (l.connexion) {
			tdConnexion.textContent = depuis(l.connexion);
			tdConnexion.title = 'Dernière connexion le ' + dateLongue(l.connexion);
		} else if (l.cree_le) {
			tdConnexion.appendChild(noeud('span', 'ag-faible', 'jamais'));
			tdConnexion.title = 'Compte créé le ' + dateLongue(l.cree_le);
		} else {
			tdConnexion.appendChild(noeud('span', 'ag-faible', '—'));
		}
		tr.appendChild(tdConnexion);

		var tdAction = noeud('td', 'ag-action');
		if (l.creable) {
			var b = noeud('button', 'ag-bouton ag-bouton--petit');
			b.type = 'button';
			b.appendChild(icone('bi-person-plus'));
			b.appendChild(noeud('span', '', 'Créer'));
			b.title = 'Créer le compte Google ' + l.adresse + ' avec le mot de passe de la fiche';
			b.addEventListener('click', function () { creerUne(l, b); });
			tdAction.appendChild(b);
		}
		tr.appendChild(tdAction);
		return tr;
	}

	function majSelection() {
		var n = Object.keys(etat.choisies).length;
		var b = $('agCreerSelection');
		b.hidden = n === 0;
		if (!etat.enCours) { b.querySelector('span').textContent = 'Créer ' + pluriel(n, 'compte Google', 'comptes Google'); }
	}

	/* ------------------------------------------------------------- creation */
	function poster(adresse) {
		var corps = new URLSearchParams({ action: 'creer', adresse: adresse, csrf_token: CSRF });
		return fetch(API, { method: 'POST', body: corps, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Réponse illisible du serveur.' }; }); })
			.catch(function () { return { ok: false, message: 'Le serveur ne répond pas.' }; });
	}

	function marquerCree(adresse) {
		etat.lignes.forEach(function (l) {
			if (l.adresse === adresse) { l.statut = 'jamais'; l.creable = false; l.cree_le = new Date().toISOString(); }
		});
		delete etat.choisies[adresse];
	}

	function creerUne(l, bouton) {
		var qui = l.personnes[0] ? l.personnes[0].nom : l.adresse;
		confirmer('Créer le compte Google de ' + qui + ' ?',
			'<b>' + echapper(l.adresse) + '</b> sera ouvert avec le mot de passe de sa fiche. ' + echapper(qui) + ' pourra se connecter tout de suite sur mail.google.com.',
			'Créer le compte'
		).then(function (oui) {
			if (!oui) { return; }
			bouton.disabled = true;
			bouton.querySelector('span').textContent = 'Création…';
			poster(l.adresse).then(function (r) {
				if (r.ok) {
					marquerCree(l.adresse);
					annoncer('success', r.message, 'Compte Google créé');
				} else {
					if (r.existait) { marquerCree(l.adresse); }
					annoncer('warning', r.message, 'Compte non créé');
				}
				rendreAvecCompteurs();
			});
		});
	}

	function creerSelection() {
		var adresses = Object.keys(etat.choisies);
		if (!adresses.length || etat.enCours) { return; }
		confirmer('Créer ' + pluriel(adresses.length, 'compte Google', 'comptes Google') + ' ?',
			'Chaque adresse sera ouverte avec le mot de passe de sa fiche ; les étudiants pourront se connecter tout de suite sur mail.google.com.',
			'Créer ' + pluriel(adresses.length, 'compte', 'comptes')
		).then(function (oui) {
			if (!oui) { return; }
			etat.enCours = true;
			var b = $('agCreerSelection');
			b.disabled = true;
			var faits = 0, crees = 0, echecs = [];
			/* Un par un : une erreur ne bloque pas les suivants, et la progression se lit. */
			adresses.reduce(function (p, adresse) {
				return p.then(function () {
					b.querySelector('span').textContent = 'Création… ' + (faits + 1) + ' / ' + adresses.length;
					return poster(adresse).then(function (r) {
						faits++;
						if (r.ok) { crees++; marquerCree(adresse); }
						else { if (r.existait) { marquerCree(adresse); } echecs.push(adresse + ' : ' + r.message); }
					});
				});
			}, Promise.resolve()).then(function () {
				etat.enCours = false;
				b.disabled = false;
				if (crees) { annoncer('success', pluriel(crees, 'compte Google créé', 'comptes Google créés') + '.', 'Comptes créés'); }
				if (echecs.length) { annoncer('warning', echecs.slice(0, 3).join(' — ') + (echecs.length > 3 ? ' (et ' + (echecs.length - 3) + ' autres)' : ''), pluriel(echecs.length, 'compte non créé', 'comptes non créés')); }
				rendreAvecCompteurs();
			});
		});
	}

	function rendreAvecCompteurs() {
		var c = { toutes: 0, active: 0, jamais: 0, suspendue: 0, absente: 0, a_corriger: 0, google_seul: 0 };
		etat.lignes.forEach(function (l) {
			if (l.statut === 'google_seul') { c.google_seul++; return; }
			c.toutes++;
			c[l.statut === 'archivee' ? 'suspendue' : l.statut]++;
		});
		majCompteurs(c);
		rendre();
	}

	/* ------------------------------------------------------------- branchements */
	$('agEtats').addEventListener('click', function (e) {
		var b = e.target.closest('.ag-etat');
		if (!b) { return; }
		etat.statut = b.dataset.statut;
		etat.montrees = PAR_PAGE;
		document.querySelectorAll('.ag-etat').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
		rendre();
	});
	var minuterie = null;
	$('agRecherche').addEventListener('input', function () {
		var champ = this;
		clearTimeout(minuterie);
		minuterie = setTimeout(function () { etat.q = normaliser(champ.value.trim()); etat.montrees = PAR_PAGE; rendre(); }, 150);
	});
	$('agSource').addEventListener('change', function () { etat.source = this.value; etat.montrees = PAR_PAGE; rendre(); });
	$('agAnnee').addEventListener('change', function () { etat.annee = this.checked; etat.montrees = PAR_PAGE; rendre(); });
	$('agActualiser').addEventListener('click', function () { charger(true); });
	$('agCreerSelection').addEventListener('click', creerSelection);

	charger(false);
})();
