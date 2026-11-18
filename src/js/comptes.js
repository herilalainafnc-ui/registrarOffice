/* =============================================================================
   Utilisateurs (src/creat.account.php) — interactions

   - recherche dans le tableau ;
   - une seule fenetre d'edition, remplie a l'ouverture depuis #cptDonnees :
     ses champs prennent le nom attendu par app/.user/updateUser (nom44,
     level44...). La page en ecrivait une par compte ;
   - fenetre de creation (verification des mots de passe, enseignant ou
     etudiant lie) ;
   - enregistrements en AJAX, puis tableau et chiffres relus sans recharger.
   ========================================================================== */

(function () {
	'use strict';

	var BASE = window.APP_BASE || '';
	var CFG = JSON.parse((document.getElementById('cptConfig') || {}).textContent || '{}');
	var donnees = lireDonnees(document);

	function $(id) { return document.getElementById(id); }

	function lireDonnees(doc) {
		var el = doc.getElementById('cptDonnees');
		try { return el ? JSON.parse(el.textContent) : {}; } catch (e) { return {}; }
	}

	function notifier(type, message) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); }
		else if (window.console) { console.warn(message); }
	}

	/* ---- Recherche --------------------------------------------------------- */

	var recherche = $('searchUsers');
	function filtrer() {
		var q = recherche ? recherche.value.trim().toLowerCase() : '';
		var lignes = document.querySelectorAll('#usersTable tbody tr');
		var n = 0;
		Array.prototype.forEach.call(lignes, function (tr) {
			var ok = !q || (tr.dataset.name || '').indexOf(q) !== -1;
			tr.hidden = !ok;
			if (ok) { n++; }
		});
		var compte = $('cptCompte');
		if (compte) {
			compte.textContent = q ? n + ' sur ' + lignes.length + ' comptes' : lignes.length + ' comptes';
		}
		var vide = $('cptVide');
		if (vide) { vide.hidden = n !== 0; }
	}
	if (recherche) { recherche.addEventListener('input', filtrer); }

	/* ---- Fenetres ------------------------------------------------------------ */

	var ouvreur = null;

	function ouvrir(voile) {
		ouvreur = document.activeElement;
		voile.hidden = false;
		var premier = voile.querySelector('input:not([type=hidden]):not([type=file]), select');
		if (premier) { premier.focus(); }
	}

	function fermer(voile) {
		voile.hidden = true;
		if (ouvreur && typeof ouvreur.focus === 'function') { ouvreur.focus(); }
	}

	Array.prototype.forEach.call(document.querySelectorAll('.cpt-voile'), function (voile) {
		voile.addEventListener('click', function (e) {
			if (e.target === voile || e.target.closest('.js-fermer')) { fermer(voile); }
		});
		voile.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { e.preventDefault(); fermer(voile); return; }
			if (e.key !== 'Tab') { return; }
			// Le focus reste dans la fenetre tant qu'elle est ouverte.
			var f = Array.prototype.filter.call(
				voile.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled])'),
				function (el) { return el.offsetParent !== null || el.type === 'file'; });
			if (!f.length) { return; }
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
	});

	/** Apercu d'une photo choisie, dans le cadre de la fenetre. */
	function apercuPhoto(input) {
		var cadre = input.closest('.cpt-photo');
		if (!cadre || !input.files || !input.files[0]) { return; }
		var lecteur = new FileReader();
		lecteur.onload = function (ev) { montrerPhoto(cadre, ev.target.result); };
		lecteur.readAsDataURL(input.files[0]);
	}

	function montrerPhoto(cadre, src) {
		var img = cadre.querySelector('img');
		var vide = cadre.querySelector('.js-photo-vide');
		if (src) { img.src = src; img.hidden = false; if (vide) { vide.hidden = true; } }
		else { img.removeAttribute('src'); img.hidden = true; if (vide) { vide.hidden = false; } }
	}

	document.addEventListener('change', function (e) {
		if (e.target.matches && e.target.matches('.cpt-photo input[type=file]')) { apercuPhoto(e.target); }
	});

	/* ---- Recherche d'un etudiant a lier ------------------------------------- */

	function echapper(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	/**
	 * Branche une recherche d'etudiant : saisie, resultats, champ cache, et
	 * rappel a la selection.
	 */
	function rechercheEtudiant(saisie, resultats, cache, choisi) {
		var minuterie = null;
		saisie.addEventListener('input', function () {
			clearTimeout(minuterie);
			var q = saisie.value.trim();
			if (q.length < 2) { resultats.hidden = true; resultats.innerHTML = ''; return; }
			minuterie = setTimeout(function () {
				fetch(BASE + '/app/.user/search.student.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (liste) {
						if (!Array.isArray(liste) || liste.length === 0) {
							resultats.innerHTML = '<div class="cpt-vide">Aucun étudiant trouvé.</div>';
							resultats.hidden = false;
							return;
						}
						resultats.innerHTML = liste.map(function (s) {
							var nom = (s.student_nom || '') + ' ' + (s.student_prenom || '');
							return '<button type="button" class="cpt-resultat" data-id="' + echapper(s.student_id) + '" data-nom="' + echapper(nom) + '">'
								+ '<b>' + echapper(s.student_id) + '</b> — ' + echapper(nom)
								+ (s.etude_envisage ? ' <small>(' + echapper(s.etude_envisage) + ')</small>' : '') + '</button>';
						}).join('');
						resultats.hidden = false;
					})
					.catch(function () { resultats.hidden = true; });
			}, 300);
		});
		resultats.addEventListener('click', function (e) {
			var b = e.target.closest('.cpt-resultat');
			if (!b) { return; }
			cache.value = b.dataset.id;
			saisie.value = '';
			resultats.hidden = true;
			choisi(b.dataset.id, b.dataset.nom);
		});
	}

	/* ---- Edition --------------------------------------------------------------- */

	var dlgEdition = $('dialogueEdition');
	var formEdition = $('formEdition');
	var editionNiveau = $('editionNiveau');
	var lienEtudiant = $('editionLienEtudiant');
	var lienTexte = $('editionLien');

	function majLienEtudiant(sid, nom) {
		if (!lienTexte) { return; }
		var span = lienTexte.querySelector('span');
		lienTexte.classList.toggle('cpt-lien--aucun', !sid);
		span.textContent = sid
			? 'Lié à ' + sid + (nom ? ' — ' + nom : '')
			: 'Aucun matricule lié : recherchez l\'étudiant ci-dessus.';
	}

	function majSectionEtudiant() {
		if (lienEtudiant && editionNiveau) { lienEtudiant.hidden = editionNiveau.value !== '8'; }
		/* Chef de mention : la mention de sa liste des etudiants. */
		var mention = $('editionMention');
		if (mention && editionNiveau) { mention.hidden = editionNiveau.value !== '6'; }
	}

	function ouvrirEdition(id) {
		var u = donnees[id];
		if (!u || !formEdition) { return; }
		formEdition.action = BASE + '/app/.user/updateUser?rg_id=' + encodeURIComponent(CFG.rgId || '') + '&id=' + encodeURIComponent(id);
		formEdition.dataset.id = id;
		formEdition.reset();

		Array.prototype.forEach.call(formEdition.querySelectorAll('[data-champ]'), function (champ) {
			var cle = champ.dataset.champ;
			champ.name = cle + id;
			if (champ.type === 'file' || champ.type === 'password') { champ.value = ''; return; }
			if (champ.type === 'checkbox') { champ.checked = !!u[cle]; return; }
			if (cle === 'oldPhotos') { champ.value = u.photos || ''; return; }
			champ.value = u[cle] == null ? '' : String(u[cle]);
		});

		var etat = formEdition.querySelector('[data-champ=etat]');
		formEdition.querySelector('.js-etat-libelle').textContent = etat.checked ? 'Actif' : 'Inactif';
		montrerPhoto($('editionPhotoCadre'), u.photo || '');
		majLienEtudiant(u.student_id, u.etudiant);
		majSectionEtudiant();
		ouvrir(dlgEdition);
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('.updateUserBtn');
		if (b) { ouvrirEdition(b.dataset.userId); }
	});

	if (formEdition) {
		formEdition.querySelector('[data-champ=etat]').addEventListener('change', function () {
			formEdition.querySelector('.js-etat-libelle').textContent = this.checked ? 'Actif' : 'Inactif';
		});
		if (editionNiveau) { editionNiveau.addEventListener('change', majSectionEtudiant); }
		rechercheEtudiant($('editionRechercheEtudiant'), $('editionResultats'),
			formEdition.querySelector('[data-champ=student_id]'), majLienEtudiant);
	}

	/* ---- Creation ---------------------------------------------------------------- */

	var dlgCreation = $('addUserDialog');
	var formCreation = $('formToAddUser');

	var ajout = $('addUserBtn');
	if (ajout && dlgCreation) { ajout.addEventListener('click', function () { ouvrir(dlgCreation); }); }

	function validerMotsDePasse() {
		var mdp = $('password').value, conf = $('confirmpass').value;
		var complet = $('nom').value && $('prenom').value && $('pseudo').value && mdp && conf;
		var note = $('passwordMatch');
		var ok = false;
		$('password').classList.remove('est-valide', 'est-invalide');
		$('confirmpass').classList.remove('est-valide', 'est-invalide');
		if (complet) {
			ok = mdp === conf;
			var cls = ok ? 'est-valide' : 'est-invalide';
			$('password').classList.add(cls);
			$('confirmpass').classList.add(cls);
			note.hidden = false;
			note.className = 'cpt-aide ' + (ok ? 'cpt-aide--ok' : 'cpt-aide--erreur');
			note.textContent = ok ? 'Les mots de passe sont identiques.' : 'Les mots de passe sont différents.';
		} else {
			note.hidden = true;
		}
		$('btnAddUser').disabled = !ok;
	}
	['password', 'confirmpass', 'nom', 'prenom', 'pseudo'].forEach(function (id) {
		if ($(id)) { $(id).addEventListener('input', validerMotsDePasse); }
	});

	var niveau = $('levelSelect');
	if (niveau) {
		niveau.addEventListener('change', function () {
			/* Chef de mention (6) comme professeur (7) : c'est un professeur, et
			   le lien a sa fiche ouvre son espace enseignant (ses cours). */
			var enseigne = niveau.value === '6' || niveau.value === '7';
			$('teacherLinkSection').hidden = !enseigne;
			$('studentLinkSection').hidden = niveau.value !== '8';
			/* Chef de mention : la mention de sa liste des etudiants. */
			if ($('mentionSection')) {
				$('mentionSection').hidden = niveau.value !== '6';
				if (niveau.value !== '6') { $('mentionChoix').value = ''; }
			}
			/* Un lien choisi puis rendu sans objet par le changement de role ne
			   doit pas partir avec le compte. */
			if (niveau.value !== '8') { effacerEtudiantChoisi(); }
			if (!enseigne && $('teacherUidSelect')) { $('teacherUidSelect').value = ''; $('teacherMailNote').textContent = ''; }
		});
	}

	/* Choisir l'enseignant remplit l'adresse : c'est par elle qu'il se connecte,
	   et l'oublier condamne le compte au seul identifiant. Un champ deja rempli a
	   la main n'est pas ecrase. */
	var enseignant = $('teacherUidSelect');
	if (enseignant) {
		enseignant.addEventListener('change', function () {
			var opt = enseignant.options[enseignant.selectedIndex];
			var mail = (opt && opt.dataset.mail) || '';
			var note = $('teacherMailNote');
			var champ = $('mail');
			if (!mail) {
				note.className = 'cpt-aide cpt-aide--alerte';
				note.textContent = "Cette fiche n'a pas d'adresse : saisissez-la ci-dessus, sinon l'enseignant ne pourra se connecter qu'avec son identifiant.";
				return;
			}
			if (champ.value.trim() === '' || champ.dataset.prefillPar === 'enseignant') {
				champ.value = mail;
				champ.dataset.prefillPar = 'enseignant';
				note.className = 'cpt-aide cpt-aide--ok';
				note.textContent = 'Adresse reprise de la fiche : elle servira à la connexion.';
			} else {
				note.className = 'cpt-aide';
				note.textContent = 'Adresse de la fiche : ' + mail + ' (l\'e-mail saisi à la main est conservé).';
			}
		});
	}

	function effacerEtudiantChoisi() {
		$('selectedStudentId').value = '';
		$('selectedStudentBadge').hidden = true;
		$('selectedStudentText').textContent = '';
		$('studentSearchAdd').value = '';
	}
	if ($('clearStudentSelection')) { $('clearStudentSelection').addEventListener('click', effacerEtudiantChoisi); }
	if ($('studentSearchAdd')) {
		rechercheEtudiant($('studentSearchAdd'), $('studentSearchResults'), $('selectedStudentId'), function (sid, nom) {
			$('selectedStudentText').textContent = 'Lié à ' + sid + ' — ' + nom;
			$('selectedStudentBadge').hidden = false;
		});
	}

	/* ---- Enregistrements ---------------------------------------------------------- */

	function envoyer(form) {
		return fetch(form.action, {
			method: 'POST',
			body: new FormData(form),
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (r) {
			return r.json().catch(function () { return { ok: false, message: 'Réponse illisible du serveur (' + r.status + ').' }; });
		});
	}

	function occupe(form, oui, texte) {
		var b = form.querySelector('button[type=submit]');
		if (!b) { return; }
		if (oui) { b.dataset.libelle = b.innerHTML; b.disabled = true; b.textContent = texte; }
		else { b.disabled = false; if (b.dataset.libelle) { b.innerHTML = b.dataset.libelle; } }
	}

	/* Relit la page : chiffres, tableau et donnees d'edition, sans recharger. */
	function rafraichir() {
		return fetch(window.location.pathname, { credentials: 'same-origin' })
			.then(function (r) { return r.text(); })
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var stats = doc.getElementById('cptStats');
				var corps = doc.querySelector('#usersTable tbody');
				if (stats) { $('cptStats').replaceWith(document.importNode(stats, true)); }
				if (corps) { document.querySelector('#usersTable tbody').replaceWith(document.importNode(corps, true)); }
				donnees = lireDonnees(doc);
				filtrer();
			})
			.catch(function () { window.location.reload(); });
	}

	if (formEdition) {
		formEdition.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!formEdition.reportValidity()) { return; }
			occupe(formEdition, true, 'Enregistrement…');
			envoyer(formEdition).then(function (r) {
				occupe(formEdition, false);
				if (!r || !r.ok) { notifier('error', (r && r.message) || 'La mise à jour a échoué.'); return; }
				fermer(dlgEdition);
				notifier('success', r.message || 'Utilisateur enregistré.');
				rafraichir();
			}).catch(function () {
				occupe(formEdition, false);
				notifier('error', 'La mise à jour a échoué. Vérifiez la connexion.');
			});
		});
	}

	if (formCreation) {
		formCreation.addEventListener('submit', function (e) {
			e.preventDefault();
			if ($('password').value !== $('confirmpass').value) { notifier('error', 'Les mots de passe ne correspondent pas.'); return; }
			occupe(formCreation, true, 'Création…');
			envoyer(formCreation).then(function (r) {
				occupe(formCreation, false);
				if (!r || !r.ok) { notifier('error', (r && r.message) || 'La création a échoué.'); return; }
				fermer(dlgCreation);
				formCreation.reset();
				effacerEtudiantChoisi();
				montrerPhoto(formCreation.querySelector('.cpt-photo'), '');
				$('teacherLinkSection').hidden = true;
				$('studentLinkSection').hidden = true;
				if ($('mentionSection')) { $('mentionSection').hidden = true; }
				validerMotsDePasse();
				notifier('success', r.message || 'Utilisateur créé.');
				rafraichir();
			}).catch(function () {
				occupe(formCreation, false);
				notifier('error', 'La création a échoué. Vérifiez la connexion.');
			});
		});
	}

	/* Messages portes par l'adresse (anciens retours du serveur). */
	var params = new URLSearchParams(window.location.search);
	if (params.get('success') === '1') {
		notifier('success', 'Opération effectuée.');
		window.history.replaceState({}, document.title, window.location.pathname);
	}
	if (params.get('error')) {
		notifier('error', params.get('error'));
		window.history.replaceState({}, document.title, window.location.pathname);
	}
})();
