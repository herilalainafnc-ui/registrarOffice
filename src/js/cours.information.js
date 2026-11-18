/* Fiche d'information d'un cours (src/cours/information.php).
 *
 * Une seule fiche, toujours modifiable :
 *   - chaque champ modifie se signale (filet or, valeur d'avant, « Rétablir ») ;
 *   - la barre d'enregistrement n'apparait qu'a la premiere modification, avec
 *     le nombre de changements et les notes deja saisies qui suivront ;
 *   - le parcours suit la mention, le cout se recalcule en direct ;
 *   - Ctrl+S enregistre ; quitter la fiche avec des changements demande
 *     confirmation (lien du menu, rechargement, fermeture de l'onglet).
 *
 * Le script est charge une fois (src/js/zone-ajax.js ne recharge pas une
 * bibliotheque deja presente) ; chaque affichage de la fiche appelle
 * CoursInformation.monter(), et le chargement du script monte la fiche
 * presente. Enregistrement : app/.cours/updateCours.php, reponse JSON.
 */
(function () {
	'use strict';
	if (window.CoursInformation) { return; }

	var CHAMPS = ['sigle', 'title', 'title_english', 'dep_desc', 'parcours', 'id_teacher', 'yearlevel', 'semester', 'nb_crd', 'category', 'lab'];
	/* Ce que app/.cours/updateCours.php recopie dans les notes deja saisies. */
	var SUIVIS_PAR_LES_NOTES = ['title', 'title_english', 'nb_crd', 'id_teacher', 'semester', 'category', 'lab', 'yearlevel'];
	var TEXTES = ['sigle', 'title', 'title_english'];

	var courante = null;   // la fiche affichee

	function espaces(v) { return String(v == null ? '' : v).replace(/\s+/g, ' ').trim(); }
	function ariary(n) { return Math.round(n).toLocaleString('fr-FR').replace(/\s/g, ' ') + ' Ar'; }
	function pluriel(n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); }
	/* Liste deroulante ouverte : Echap la ferme, sans rien retablir. */
	function ouverte(c) { try { return c.matches(':open'); } catch (x) { return false; } }

	function notifier(type, message) {
		if (window.Toast && typeof Toast[type] === 'function') { Toast[type](message); }
		else if (window.Toast) { Toast.show(message, type); }
	}

	function confirmerSortie() {
		var message = 'Les modifications de cette fiche ne sont pas enregistrées.';
		if (window.Toast && typeof Toast.confirm === 'function') {
			return Toast.confirm(message, { title: 'Quitter sans enregistrer ?', confirmText: 'Quitter sans enregistrer', cancelText: 'Rester sur la fiche', danger: true });
		}
		return Promise.resolve(window.confirm(message + '\nQuitter quand même ?'));
	}

	function monter(racine) {
		racine.setAttribute('data-ci-monte', '');
		var config = JSON.parse(racine.querySelector('[data-ci-config]').textContent);
		var form = racine.querySelector('form');
		var champs = form.querySelector('.ci-fiche__champs');
		var el = form.elements;
		var barre = racine.querySelector('[data-ci-barre]');
		var boutonEnregistrer = racine.querySelector('[data-ci-enregistrer]');
		var boutonAnnuler = racine.querySelector('[data-ci-annuler]');
		var avisParcours = racine.querySelector('#ciParcoursAvis');
		var lignes = {};
		racine.querySelectorAll('[data-ci-champ]').forEach(function (l) { lignes[l.getAttribute('data-ci-champ')] = l; });
		var occupe = false;

		/* ------------------------------------------------ lire, ecrire */

		function lire(nom) { var c = el[nom]; return c ? String(c.value) : ''; }

		function comparable(nom, v) {
			if (nom === 'nb_crd') { return String(parseInt(v, 10) || ''); }
			return TEXTES.indexOf(nom) >= 0 ? espaces(v) : String(v);
		}

		var nomsEnseignants = {};
		config.enseignants.forEach(function (e) { nomsEnseignants[e[0]] = e[1]; });
		function nomEnseignant(uid) {
			if (uid === '') { return 'Aucun enseignant'; }
			return nomsEnseignants[uid] || (uid === config.enseignant.uid ? config.enseignant.nom : 'Enseignant n° ' + uid);
		}

		function libelle(nom, v) {
			var c = el[nom];
			if (!c) { return v; }
			if (nom === 'id_teacher') { return nomEnseignant(v); }
			if (typeof RadioNodeList !== 'undefined' && c instanceof RadioNodeList) {
				for (var i = 0; i < c.length; i++) { if (c[i].value === v) { return c[i].getAttribute('data-ci-libelle') || v; } }
				return v;
			}
			if (c.tagName === 'SELECT') {
				for (var j = 0; j < c.options.length; j++) { if (c.options[j].value === v) { return c.options[j].textContent.trim(); } }
				return depart && depart[nom] && depart[nom].v === v ? depart[nom].l : v;
			}
			if (nom === 'nb_crd') { return pluriel(parseInt(v, 10) || 0, 'crédit'); }
			return espaces(v) === '' ? '(vide)' : espaces(v);
		}

		function instantane() {
			var etat = {};
			CHAMPS.forEach(function (nom) { var v = lire(nom); etat[nom] = { v: v, l: libelle(nom, v) }; });
			return etat;
		}

		/* Ecrit une valeur et previent les ecouteurs (avis du sigle, cout, etc.). */
		function ecrire(nom, v) {
			var c = el[nom];
			if (!c) { return; }
			c.value = v;
			var cible = (typeof RadioNodeList !== 'undefined' && c instanceof RadioNodeList)
				? Array.prototype.find.call(c, function (r) { return r.checked; }) || c[0]
				: c;
			cible.dispatchEvent(new Event('input', { bubbles: true }));
		}

		var depart = null;
		depart = instantane();

		/* ------------------------------------------- parcours et mention */

		var mentionAffichee = lire('dep_desc');

		/* La liste des parcours de la mention, en gardant le choix s'il y existe.
		   Le parcours d'origine, s'il n'est pas dans la liste de sa mention, y
		   reste tant que la mention est celle d'origine. */
		function remplirParcours(mention, souhait) {
			var liste = el.parcours;
			var voulu = souhait !== undefined ? souhait : liste.value;
			var parcours = config.parcours[mention] || [];
			var connus = parcours.map(function (p) { return p.code; });
			liste.replaceChildren(new Option('Tronc commun', 'all'));
			if (mention === depart.dep_desc.v && depart.parcours.v !== 'all' && connus.indexOf(depart.parcours.v) < 0) {
				liste.add(new Option(depart.parcours.l, depart.parcours.v));
			}
			Object.keys(config.cycles).forEach(function (cycle) {
				var groupe = parcours.filter(function (p) { return p.cycle === cycle; });
				if (!groupe.length) { return; }
				var og = document.createElement('optgroup');
				og.label = config.cycles[cycle];
				groupe.forEach(function (p) { og.appendChild(new Option(p.nom, p.code)); });
				liste.appendChild(og);
			});
			parcours.filter(function (p) { return !config.cycles[p.cycle]; }).forEach(function (p) { liste.add(new Option(p.nom, p.code)); });
			var existe = Array.prototype.some.call(liste.options, function (o) { return o.value === voulu; });
			liste.value = existe ? voulu : 'all';
			mentionAffichee = mention;
			return existe ? null : voulu;
		}

		function surMention() {
			var mention = lire('dep_desc');
			if (mention === mentionAffichee) { return; }
			var choixPerdu = remplirParcours(mention);
			if (choixPerdu && choixPerdu !== 'all') {
				avisParcours.className = 'ci-avis is-alerte';
				avisParcours.textContent = '« ' + libelle('parcours', choixPerdu) + ' » n\'appartient pas à la mention ' + mention + ' : le cours passe en tronc commun. Choisissez un parcours si besoin.';
			} else {
				avisParcours.className = 'ci-avis';
				avisParcours.textContent = '';
			}
		}

		/* ------------------------------------------------------- credits */

		var credits = el.nb_crd;
		var unite = racine.querySelector('.ci-pas__unite');
		var pas = racine.querySelectorAll('[data-ci-pas]');
		var sansCredit = racine.querySelector('#ciCreditsAide');
		// 0 : cours sans credit (soutien de francais...), rien a payer
		function bornerCredits(n) { return Math.min(20, Math.max(0, n)); }
		pas.forEach(function (b) {
			b.addEventListener('click', function () {
				var n = bornerCredits((parseInt(credits.value, 10) || 0) + parseInt(b.getAttribute('data-ci-pas'), 10));
				credits.value = n;
				credits.dispatchEvent(new Event('input', { bubbles: true }));
			});
		});
		credits.addEventListener('change', function () {
			var n = parseInt(credits.value, 10);
			credits.value = isNaN(n) ? depart.nb_crd.v : bornerCredits(n);
			credits.dispatchEvent(new Event('input', { bubbles: true }));
		});
		function majCredits() {
			var n = parseInt(credits.value, 10) || 0;
			unite.textContent = n > 1 ? 'crédits' : 'crédit';
			sansCredit.hidden = n !== 0;
			pas[0].disabled = n <= 0;
			pas[1].disabled = n >= 20;
		}

		/* ---------------------------------------------------- enseignant */

		/* Plus de 150 enseignants : un champ de recherche plutot qu'une liste a
		   faire defiler. Quelques lettres du nom ou du prenom suffisent (accents,
		   majuscules et ordre des mots ignores) ; on choisit a la souris, ou avec
		   les fleches et Entree. Echap ferme la liste, puis retablit. */
		var choixEnseignant = (function () {
			var boite = racine.querySelector('[data-ci-enseignant]');
			var saisie = boite.querySelector('input');
			var liste = boite.querySelector('[role="listbox"]');
			var cache = el.id_teacher;
			var resultats = [];
			var actif = -1;

			function simple(s) { return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
			var index = config.enseignants.map(function (e) { return { uid: e[0], nom: e[1], cle: simple(e[1]) }; });

			function afficher() { saisie.value = nomEnseignant(cache.value); }

			/* Le nom, les lettres trouvees surlignees. */
			function ecrireNom(li, nom, mots) {
				var plat = '';
				var origine = [];
				for (var i = 0; i < nom.length; i++) {
					var s = simple(nom[i]);
					for (var j = 0; j < s.length; j++) { plat += s[j]; origine.push(i); }
				}
				var marque = [];
				mots.forEach(function (m) {
					var p = plat.indexOf(m);
					for (var k = p; p >= 0 && k < p + m.length; k++) { marque[origine[k]] = true; }
				});
				for (var a = 0; a < nom.length;) {
					var b = a;
					while (b < nom.length && !!marque[b] === !!marque[a]) { b++; }
					var morceau = document.createTextNode(nom.slice(a, b));
					if (marque[a]) { var mk = document.createElement('mark'); mk.appendChild(morceau); li.appendChild(mk); } else { li.appendChild(morceau); }
					a = b;
				}
			}

			function activer(i) {
				var options = liste.querySelectorAll('[role="option"]');
				if (!options.length) { actif = -1; saisie.removeAttribute('aria-activedescendant'); return; }
				actif = Math.max(0, Math.min(options.length - 1, i));
				options.forEach(function (o, n) { o.classList.toggle('is-actif', n === actif); });
				saisie.setAttribute('aria-activedescendant', options[actif].id);
				options[actif].scrollIntoView({ block: 'nearest' });
			}

			function rendre(texte) {
				var mots = simple(texte).split(/\s+/).filter(Boolean);
				resultats = index.filter(function (e) { return mots.every(function (m) { return e.cle.indexOf(m) >= 0; }); });
				if (mots.length) {
					/* Un mot qui commence par les lettres tapees passe devant. */
					var debut = function (e) { return (' ' + e.cle).indexOf(' ' + mots[0]) >= 0 ? 0 : 1; };
					resultats.sort(function (x, y) { return debut(x) - debut(y); });
				} else {
					resultats = [{ uid: '', nom: 'Aucun enseignant' }].concat(resultats);
				}
				liste.replaceChildren();
				resultats.forEach(function (e, i) {
					var li = document.createElement('li');
					li.id = 'ciEnseignant-' + i;
					li.setAttribute('role', 'option');
					li.className = 'ci-cherche__option' + (e.uid === '' ? ' is-aucun' : '');
					var choisi = e.uid === cache.value;
					li.setAttribute('aria-selected', choisi ? 'true' : 'false');
					var nom = document.createElement('span');
					ecrireNom(nom, e.nom, mots);
					li.appendChild(nom);
					if (choisi) { li.insertAdjacentHTML('beforeend', '<i class="bi bi-check2" aria-hidden="true"></i>'); }
					li.addEventListener('mousedown', function (ev) { ev.preventDefault(); });   // le champ garde le focus
					li.addEventListener('click', function () { choisir(e.uid); });
					liste.appendChild(li);
				});
				if (!resultats.length) {
					var vide = document.createElement('li');
					vide.className = 'ci-cherche__vide';
					vide.textContent = 'Aucun enseignant ne correspond à « ' + texte.trim() + ' ».';
					liste.appendChild(vide);
				}
				var courant = resultats.findIndex(function (e) { return e.uid === cache.value; });
				activer(mots.length || courant < 0 ? 0 : courant);
			}

			function ouvrir(texte) {
				rendre(texte || '');
				liste.hidden = false;
				boite.classList.add('is-ouverte');
				saisie.setAttribute('aria-expanded', 'true');
			}

			function fermer() {
				liste.hidden = true;
				boite.classList.remove('is-ouverte');
				saisie.setAttribute('aria-expanded', 'false');
				saisie.removeAttribute('aria-activedescendant');
				afficher();
			}

			function choisir(uid) {
				cache.value = uid;
				fermer();
				cache.dispatchEvent(new Event('input', { bubbles: true }));
			}

			var sansListe = false;   // focus rendu par « Rétablir » : la liste reste fermee
			saisie.addEventListener('focus', function () {
				if (sansListe) { sansListe = false; return; }
				saisie.select();
				ouvrir('');
			});
			saisie.addEventListener('click', function () { if (liste.hidden) { ouvrir(''); } });
			saisie.addEventListener('input', function () { ouvrir(saisie.value); });
			saisie.addEventListener('blur', function () { if (!liste.hidden) { fermer(); } });
			saisie.addEventListener('keydown', function (e) {
				var ouverte = !liste.hidden;
				if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
					e.preventDefault();
					if (!ouverte) { ouvrir(''); return; }
					activer(actif + (e.key === 'ArrowDown' ? 1 : -1));
				} else if (e.key === 'Enter' && ouverte) {
					e.preventDefault();
					if (actif >= 0 && resultats[actif]) { choisir(resultats[actif].uid); }
				} else if (e.key === 'Escape') {
					e.preventDefault();
					if (ouverte) { fermer(); saisie.select(); } else if (lignes.id_teacher.classList.contains('is-modifie')) { retablir('id_teacher'); }
				}
			});
			/* Valeur remise par « Rétablir » : le nom suit. */
			cache.addEventListener('input', function () { if (liste.hidden) { afficher(); } });

			return {
				afficher: afficher,
				focaliser: function () { if (document.activeElement !== saisie) { sansListe = true; saisie.focus(); } }
			};
		})();

		/* ---------------------------------------------------------- cout */

		var coutCalcul = racine.querySelector('[data-ci-cout="calcul"]');
		var coutEcolage = racine.querySelector('[data-ci-cout="ecolage"]');
		var coutLabo = racine.querySelector('[data-ci-cout="labo"]');
		var coutNote = racine.querySelector('[data-ci-cout="note"]');

		function majCout(modifiee) {
			var mention = lire('dep_desc');
			var niveau = lire('yearlevel');
			var semestre = lire('semester');
			var n = parseInt(lire('nb_crd'), 10) || 0;
			var labo = lire('lab');
			var ligne = config.bareme[mention + '|' + niveau + '|' + semestre];
			var ecolage = ligne ? ligne[0] * n : 0;
			var laboratoire = ligne && labo !== '0' && labo !== '' ? ligne[1] : 0;

			coutCalcul.textContent = ligne ? pluriel(n, 'crédit') + ' × ' + ariary(ligne[0]) + ' =' : '';
			coutEcolage.textContent = ariary(ecolage);
			coutEcolage.classList.toggle('is-nul', !ecolage);
			coutLabo.textContent = labo === '0' || labo === '' ? 'Sans laboratoire' : ariary(laboratoire);
			coutLabo.classList.toggle('is-nul', !laboratoire);

			var note;
			if (!ligne) {
				note = 'Aucun tarif au barème pour ' + mention + ', ' + libelle('yearlevel', niveau) + ', semestre ' + semestre + ' : le coût sera de 0 Ar.';
			} else {
				var session = config.sessions[semestre];
				note = 'Calculé d\'après le barème ' + (session ? '« ' + session + ' »' : 'de base') + ', mis à jour à chaque enregistrement.';
				if (!modifiee && (Math.round(ecolage) !== Math.round(config.enregistre.cout) || Math.round(laboratoire) !== Math.round(config.enregistre.labo))) {
					note += ' Montant actuellement enregistré : ' + ariary(config.enregistre.cout) + (config.enregistre.labo ? ' et ' + ariary(config.enregistre.labo) + ' de laboratoire' : '') + '.';
				}
			}
			coutNote.textContent = note;
			coutNote.classList.toggle('is-alerte', !ligne);
		}

		/* --------------------------------------- marques et barre d'etat */

		function modifies() {
			return CHAMPS.filter(function (nom) { return lignes[nom] && comparable(nom, lire(nom)) !== comparable(nom, depart[nom].v); });
		}

		function marquer(nom, modifie) {
			var ligne = lignes[nom];
			var avant = ligne.querySelector('.ci-avant');
			ligne.classList.toggle('is-modifie', modifie);
			if (!modifie) { avant.hidden = true; return; }
			if (!avant.hidden) { return; }
			var bouton = document.createElement('button');
			bouton.type = 'button';
			bouton.className = 'ci-retablir';
			bouton.innerHTML = '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>';
			bouton.appendChild(document.createTextNode('Rétablir'));
			bouton.addEventListener('click', function () { retablir(nom); });
			var valeur = document.createElement('b');
			valeur.textContent = depart[nom].l;
			var texte = document.createElement('span');
			texte.append('Avant : ', valeur);
			avant.replaceChildren(texte, bouton);
			avant.hidden = false;
		}

		var compte = barre.querySelector('[data-ci-compte]');
		var notes = barre.querySelector('[data-ci-notes]');

		function maj() {
			var liste = modifies();
			CHAMPS.forEach(function (nom) { if (lignes[nom]) { marquer(nom, liste.indexOf(nom) >= 0); } });
			majCredits();
			majCout(liste.length > 0);
			barre.classList.toggle('is-visible', liste.length > 0);
			compte.textContent = liste.length === 1 ? '1 modification non enregistrée' : liste.length + ' modifications non enregistrées';
			var n = config.notes;
			var touchees = (n.suivent || n.gardees) && liste.some(function (nom) { return SUIVIS_PAR_LES_NOTES.indexOf(nom) >= 0; });
			notes.hidden = !touchees;
			notes.textContent = messageNotes(n);
		}

		/* Ce que deviennent les notes deja saisies : celles de l'annee en cours
		   suivent, celles des annees passees gardent le cours tel qu'il etait. */
		function messageNotes(n) {
			var phrases = [];
			if (n.suivent) {
				phrases.push(n.suivent === 1
					? 'La note de ' + n.annee + ' sera mise à jour'
					: 'Les ' + n.suivent + ' notes de ' + n.annee + ' seront mises à jour');
			}
			if (n.gardees) {
				phrases.push(n.gardees === 1
					? 'la note des années précédentes ne change pas'
					: 'les ' + n.gardees + ' notes des années précédentes ne changent pas');
			}
			var texte = phrases.join(' ; ');
			return texte.charAt(0).toUpperCase() + texte.slice(1) + '.';
		}

		function retablir(nom) {
			if (nom === 'dep_desc') {
				ecrire('dep_desc', depart.dep_desc.v);
				var garde = lire('parcours');
				remplirParcours(depart.dep_desc.v, garde === 'all' ? depart.parcours.v : garde);
				avisParcours.className = 'ci-avis';
				avisParcours.textContent = '';
			} else {
				ecrire(nom, depart[nom].v);
				if (nom === 'parcours') { avisParcours.className = 'ci-avis'; avisParcours.textContent = ''; }
			}
			maj();
			if (nom === 'id_teacher') { choixEnseignant.focaliser(); return; }
			var c = el[nom];
			var cible = (typeof RadioNodeList !== 'undefined' && c instanceof RadioNodeList)
				? Array.prototype.find.call(c, function (r) { return r.checked; })
				: c;
			if (cible) { cible.focus(); }
		}

		function toutRetablir() {
			CHAMPS.forEach(function (nom) { if (nom !== 'parcours') { el[nom].value = depart[nom].v; } });
			choixEnseignant.afficher();
			remplirParcours(depart.dep_desc.v, depart.parcours.v);
			avisParcours.className = 'ci-avis';
			avisParcours.textContent = '';
			el.sigle.dispatchEvent(new Event('input', { bubbles: true }));
			maj();
		}

		/* -------------------------------------------- sigle deja utilise */

		var veille = window.CoursSigle ? CoursSigle.surveiller(el.sigle, {
			avis: racine.querySelector('#ciSigleAvis'),
			sauf: config.id,
			initiale: el.sigle.defaultValue,
			classeAvis: 'ci-avis',
			classes: { ok: 'is-ok', doublon: 'is-doublon', attente: 'is-attente' },
			surChangement: function (etat) {
				boutonEnregistrer.classList.toggle('is-bloque', !etat.libre);
			}
		}) : null;

		/* --------------------------------------------------- ecouteurs */

		form.addEventListener('input', function (e) {
			if (e.target === el.dep_desc) { surMention(); }
			if (e.target === el.parcours) { avisParcours.className = 'ci-avis'; avisParcours.textContent = ''; }
			maj();
		});
		form.addEventListener('change', function (e) {
			var c = e.target;
			if (c === el.dep_desc) { surMention(); }
			/* Espaces en trop : retires en quittant le champ, comme le serveur le fait pour le sigle. */
			if (TEXTES.indexOf(c.name) >= 0) {
				var propre = c.name === 'sigle' ? espaces(c.value) : c.value.trim();
				if (propre !== c.value) { c.value = propre; }
			}
			maj();
		});
		form.addEventListener('keydown', function (e) {
			/* Echap dans un champ modifie : sa valeur d'avant. */
			if (e.key !== 'Escape' || !e.target.name || !lignes[e.target.name]) { return; }
			if (lignes[e.target.name].classList.contains('is-modifie') && !ouverte(e.target)) {
				e.preventDefault();
				retablir(e.target.name);
			}
		});

		boutonAnnuler.addEventListener('click', function () { toutRetablir(); });

		/* ------------------------------------------------- enregistrer */

		function signalerChamp(nom, message) {
			notifier('error', message);
			var c = el[nom];
			c.setAttribute('aria-invalid', 'true');
			c.addEventListener('input', function retirer() { c.removeAttribute('aria-invalid'); c.removeEventListener('input', retirer); });
			c.focus();
		}

		function attendre(oui) {
			occupe = oui;
			champs.disabled = oui;
			boutonEnregistrer.disabled = oui;
			boutonAnnuler.disabled = oui;
			boutonEnregistrer.classList.toggle('is-occupe', oui);
			boutonEnregistrer.querySelector('span').textContent = oui ? 'Enregistrement…' : 'Enregistrer';
		}

		/* Le panneau de gauche reprend les nouvelles valeurs. */
		function majPanneau() {
			var valeurs = {
				sigle: espaces(lire('sigle')),
				titre: lire('title').trim(),
				mention: lire('dep_desc'),
				enseignant: lire('id_teacher') === '' ? '' : libelle('id_teacher', lire('id_teacher')),
				niveau: libelle('yearlevel', lire('yearlevel')),
				semestre: libelle('semester', lire('semester')),
				credits: lire('nb_crd'),
				categorie: libelle('category', lire('category'))
			};
			Object.keys(valeurs).forEach(function (cle) {
				document.querySelectorAll('[data-cours="' + cle + '"]').forEach(function (n) { n.textContent = valeurs[cle]; });
			});
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (occupe || !modifies().length) { return; }
			if (espaces(lire('sigle')) === '') { signalerChamp('sigle', 'Le sigle est obligatoire.'); return; }
			if (lire('title').trim() === '') { signalerChamp('title', 'L\'intitulé est obligatoire.'); return; }
			var etat = veille ? veille.etat() : { libre: true, enCours: false };
			if (etat.enCours) { notifier('info', 'Vérification du sigle en cours : réessayez dans un instant.'); return; }
			if (!etat.libre) { notifier('error', 'Ce sigle est déjà utilisé par un autre cours : choisissez-en un autre.'); el.sigle.focus(); return; }

			var donnees = new URLSearchParams(new FormData(form));
			attendre(true);
			fetch(config.urlEnregistrer, {
				method: 'POST',
				body: donnees,
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
			})
				.then(function (r) {
					return r.json().catch(function () { return {}; }).then(function (d) {
						if (!r.ok || !d.ok) { throw d; }
						return d;
					});
				})
				.then(function (d) {
					notifier('success', d.message || 'Cours enregistré.');
					majPanneau();
					depart = instantane();
					maj();
					/* La fiche se reprend du serveur (date de modification, cout
					   enregistre), a la meme hauteur de defilement. */
					if (window.ZoneAjax && racine.isConnected) {
						var hauteur = racine.scrollTop;
						document.addEventListener('zone:chargee', function reprendre() {
							document.removeEventListener('zone:chargee', reprendre);
							var neuve = document.querySelector('[data-ci]');
							if (neuve) { neuve.scrollTop = hauteur; }
						});
						ZoneAjax.recharger();
					} else {
						attendre(false);
					}
				})
				.catch(function (d) {
					attendre(false);
					d = d || {};
					if (veille && d.doublons && d.doublons.length) { veille.forcerDoublons(d); el.sigle.focus(); }
					notifier('error', d.message || 'L\'enregistrement a échoué. Vérifiez la connexion, puis réessayez.');
				});
		});

		maj();

		courante = {
			racine: racine,
			modifiee: function () { return racine.isConnected && modifies().length > 0; },
			oublier: function () { depart = instantane(); maj(); },
			enregistrer: function () { if (form.requestSubmit) { form.requestSubmit(); } else { form.dispatchEvent(new Event('submit', { cancelable: true })); } }
		};
	}

	/* ------------------------------------------ quitter avec des changements */

	/* En capture : passe avant zone-ajax et le rideau de transition. */
	document.addEventListener('click', function (e) {
		if (!courante || !courante.modifiee()) { return; }
		var lien = e.target.closest && e.target.closest('a[href]');
		if (!lien || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
		if ((lien.target && lien.target !== '_self') || lien.hasAttribute('download')) { return; }
		var href = lien.getAttribute('href') || '';
		if (href === '' || href.charAt(0) === '#' || /^javascript:/i.test(href)) { return; }
		e.preventDefault();
		e.stopImmediatePropagation();
		confirmerSortie().then(function (oui) {
			if (!oui) { return; }
			courante.oublier();
			lien.click();
		});
	}, true);

	window.addEventListener('beforeunload', function (e) {
		if (courante && courante.modifiee()) { e.preventDefault(); e.returnValue = ''; }
	});

	document.addEventListener('keydown', function (e) {
		if (!courante || !courante.racine.isConnected || !(e.ctrlKey || e.metaKey) || e.altKey || String(e.key).toLowerCase() !== 's') { return; }
		e.preventDefault();
		if (courante.modifiee()) { courante.enregistrer(); }
	});

	window.CoursInformation = {
		monter: function () {
			var racine = document.querySelector('[data-ci]:not([data-ci-monte])');
			if (racine) { monter(racine); }
		}
	};
	window.CoursInformation.monter();
})();
