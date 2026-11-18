/* =============================================================================
   Actualites (/news) — page : src/admin.actus.php, styles : src/css/actus.css.

   - La liste vient du JSON de la page : etat de chaque annonce (en ligne,
     programmee, masquee, expiree), filtres, recherche sans accents.
   - Epingler et masquer repondent tout de suite ; la liste se reordonne en
     glissant (FLIP). En cas d'echec, tout revient en place.
   - L'editeur s'ouvre sans aller-retour au serveur et montre la carte telle
     que la verront les etudiants. L'image est reduite dans le navigateur
     (1600 px) avant l'envoi : une photo de telephone ne part plus entiere.
   Ecritures : app/.annonces/ (add, update, toggle, delete).
   ========================================================================== */
(function () {
	'use strict';

	const page = document.getElementById('acPage');
	const donnees = document.getElementById('acDonnees');
	if (!page || !donnees) { return; }
	const D = JSON.parse(donnees.textContent);
	const CATS = D.categories;
	const AUJ = D.aujourdhui;

	const liste = document.getElementById('acListe');
	const recherche = document.getElementById('acRecherche');
	const choixCategorie = document.getElementById('acCategorie');
	const etats = document.getElementById('acEtats');
	const dialog = document.getElementById('acEditeur');
	const form = document.getElementById('acForm');

	const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
	const fmtLong = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });
	const LIBELLES = { en_ligne: 'En ligne', programmee: 'Programmée', masquee: 'Masquée', expiree: 'Expirée' };
	/* Les degrades des cartes sans image : ceux de la page des etudiants. */
	const DEGRADES = {
		info: 'linear-gradient(135deg, #0c2d4a 0%, #0ea5e9 100%)',
		event: 'linear-gradient(135deg, #1a1040 0%, #a78bfa 100%)',
		academic: 'linear-gradient(135deg, #0a2e1f 0%, #34d399 100%)',
		urgent: 'linear-gradient(135deg, #3b0a0a 0%, #f87171 100%)',
		sport: 'linear-gradient(135deg, #2a1e00 0%, #fbbf24 100%)',
		culture: 'linear-gradient(135deg, #2a0a20 0%, #f472b6 100%)',
	};

	const reduit = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const sansAccents = (s) => String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/\p{M}/gu, '');
	const jour = (ymd) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd || ''); return m ? Date.UTC(+m[1], +m[2] - 1, +m[3]) / 86400000 : null; };
	const dateCourte = (ymd) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd || ''); return m ? (+m[3]) + ' ' + MOIS[+m[2] - 1] + ' ' + m[1] : ''; };
	const dateLongue = (ymd) => { const j = jour(ymd); return j === null ? '' : fmtLong.format(new Date(j * 86400000)); };

	function notifier(type, message) {
		if (window.Toast && typeof window.Toast[type] === 'function') { window.Toast[type](message); }
	}
	function confirmer(message, options) {
		if (window.Toast && typeof window.Toast.confirm === 'function') { return window.Toast.confirm(message, options); }
		return Promise.resolve(window.confirm(message));
	}

	const normaliser = (a) => Object.assign({}, a, { id: +a.id, is_pinned: +a.is_pinned ? 1 : 0, is_active: +a.is_active ? 1 : 0 });
	let annonces = (D.annonces || []).map(normaliser);
	let filtreEtat = 'tout';

	/** Ce que voient les etudiants (la requete de src/student.actus.php). */
	function etatDe(a) {
		if (!a.is_active) { return 'masquee'; }
		if (a.expire_date && a.expire_date < AUJ) { return 'expiree'; }
		if (a.publish_date && a.publish_date > AUJ) { return 'programmee'; }
		return 'en_ligne';
	}
	function texteStatut(a) {
		const e = etatDe(a);
		if (e === 'programmee') { return 'Programmée · ' + dateCourte(a.publish_date); }
		if (e === 'expiree') { return 'Expirée le ' + dateCourte(a.expire_date); }
		return LIBELLES[e];
	}
	function meta(a) {
		const e = etatDe(a);
		const morceaux = [];
		if (a.publish_date) { morceaux.push((e === 'programmee' ? 'Publication le ' : 'Publiée le ') + dateCourte(a.publish_date)); }
		morceaux.push(a.expire_date ? (e === 'expiree' ? 'fin le ' : 'jusqu’au ') + dateCourte(a.expire_date) : 'sans date de fin');
		if (a.author) { morceaux.push('par ' + a.author); }
		return morceaux.join(' · ');
	}
	/** Le texte de la carte des etudiants : le resume, sinon le debut du contenu. */
	const resumeDe = (excerpt, content) => excerpt || (content ? String(content).substring(0, 140) + '…' : '');

	/* Comme la page des etudiants : epinglees d'abord, puis la publication la plus recente. */
	function trier() {
		annonces.sort((x, y) => (y.is_pinned - x.is_pinned)
			|| String(y.publish_date || '').localeCompare(String(x.publish_date || ''))
			|| String(y.created_at || '').localeCompare(String(x.created_at || '')));
	}

	/* --- La liste ------------------------------------------------------------------- */

	function correspond(a, q, cat) {
		if (cat && a.category !== cat) { return false; }
		return !q || sansAccents([a.title, a.excerpt, a.content, a.author].join(' ')).indexOf(q) >= 0;
	}

	function vignette(a) {
		const cat = CATS[a.category] || CATS.info;
		if (a.image) {
			return '<div class="ac-vignette"><img src="' + esc(D.images + a.image) + '" alt="" width="112" height="76" loading="lazy" decoding="async"></div>';
		}
		return '<div class="ac-vignette" style="--ac-degrade:' + (DEGRADES[a.category] || DEGRADES.info) + '"><i class="bi ' + cat.icon + '" aria-hidden="true"></i></div>';
	}

	function ligneHTML(a, i) {
		const cat = CATS[a.category] || CATS.info;
		const e = etatDe(a);
		const resume = resumeDe(a.excerpt, a.content);
		const titre = esc(a.title);
		return '<article class="ac-ligne" data-id="' + a.id + '" style="--i:' + i + '" aria-labelledby="acT' + a.id + '">'
			+ vignette(a)
			+ '<div class="ac-ligne__corps">'
			+ '<div class="ac-ligne__haut"><span class="ac-cat-libelle" style="--cat:' + cat.color + '"><i class="bi ' + cat.icon + '" aria-hidden="true"></i>' + esc(cat.label) + '</span>'
			+ (a.is_pinned ? '<span class="ac-epingle"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Épinglée</span>' : '') + '</div>'
			+ '<h2 class="ac-ligne__titre" id="acT' + a.id + '">' + titre + '</h2>'
			+ (resume ? '<p class="ac-ligne__resume">' + esc(resume) + '</p>' : '')
			+ '<p class="ac-ligne__meta">' + esc(meta(a)) + '</p>'
			+ '</div>'
			+ '<div class="ac-ligne__droite">'
			+ '<span class="ac-statut ac-statut--' + e + '">' + esc(texteStatut(a)) + '</span>'
			+ '<div class="ac-ligne__actions">'
			+ '<button type="button" class="ac-bouton ac-bouton--discret ac-bouton--petit" data-ac="modifier"><i class="bi bi-pencil" aria-hidden="true"></i>Modifier</button>'
			+ '<button type="button" class="ac-icone" data-ac="epingler" aria-pressed="' + (a.is_pinned ? 'true' : 'false') + '" aria-label="' + (a.is_pinned ? 'Désépingler' : 'Épingler en tête') + ' : ' + titre + '" title="' + (a.is_pinned ? 'Désépingler' : 'Épingler en tête') + '"><i class="bi ' + (a.is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle') + '" aria-hidden="true"></i></button>'
			+ '<button type="button" class="ac-icone" data-ac="visibilite" aria-label="' + (a.is_active ? 'Masquer aux étudiants' : 'Rendre visible aux étudiants') + ' : ' + titre + '" title="' + (a.is_active ? 'Masquer aux étudiants' : 'Rendre visible aux étudiants') + '"><i class="bi ' + (a.is_active ? 'bi-eye' : 'bi-eye-slash') + '" aria-hidden="true"></i></button>'
			+ '<button type="button" class="ac-icone ac-icone--danger" data-ac="supprimer" aria-label="Supprimer : ' + titre + '" title="Supprimer"><i class="bi bi-trash3" aria-hidden="true"></i></button>'
			+ '</div></div></article>';
	}

	function videHTML(q) {
		let titre = 'Aucune annonce pour l’instant';
		let texte = '« Nouvelle annonce » publie la première dans l’espace étudiant.';
		if (annonces.length) {
			if (q || choixCategorie.value) {
				titre = 'Aucune annonce ne correspond';
				texte = q ? 'Rien pour « ' + recherche.value.trim() + ' » avec ces filtres.' : 'Aucune annonce dans cette catégorie avec ce filtre.';
			} else {
				titre = {
					en_ligne: 'Aucune annonce en ligne',
					programmee: 'Aucune annonce programmée',
					masquee: 'Aucune annonce masquée',
					expiree: 'Aucune annonce expirée',
				}[filtreEtat] || titre;
				texte = filtreEtat === 'en_ligne' ? 'La rubrique Actualités des étudiants est vide en ce moment.' : '';
			}
		}
		return '<div class="ac-liste__vide"><i class="bi bi-megaphone" aria-hidden="true"></i><b>' + esc(titre) + '</b>' + (texte ? '<p>' + esc(texte) + '</p>' : '') + '</div>';
	}

	/** Les compteurs des etats, sur les annonces de la recherche et de la categorie en cours. */
	function compter(q, cat) {
		const n = { tout: 0, en_ligne: 0, programmee: 0, masquee: 0, expiree: 0 };
		annonces.forEach((a) => { if (correspond(a, q, cat)) { n.tout++; n[etatDe(a)]++; } });
		etats.querySelectorAll('.ac-etat').forEach((b) => {
			const v = n[b.dataset.etat] || 0;
			b.querySelector('b').textContent = String(v);
			b.classList.toggle('is-vide', v === 0);
		});
	}

	function positions() {
		const p = new Map();
		liste.querySelectorAll('.ac-ligne').forEach((l) => p.set(l.dataset.id, l.getBoundingClientRect().top));
		return p;
	}

	/**
	 * Redessine la liste. opts.flip : les lignes glissent de leur ancienne
	 * place a la nouvelle ; opts.arrivee : elles montent l'une apres l'autre ;
	 * opts.eclat : l'id d'une ligne a eclairer.
	 */
	function rendre(opts) {
		opts = opts || {};
		const avant = opts.flip && !reduit() ? positions() : null;
		trier();
		const q = sansAccents(recherche.value.trim());
		const cat = choixCategorie.value;
		const vues = annonces.filter((a) => correspond(a, q, cat) && (filtreEtat === 'tout' || etatDe(a) === filtreEtat));
		liste.innerHTML = vues.length ? vues.map(ligneHTML).join('') : videHTML(q);
		compter(q, cat);
		if (opts.arrivee && !reduit()) {
			liste.querySelectorAll('.ac-ligne').forEach((l) => l.classList.add('is-arrivee'));
		}
		if (avant) {
			liste.querySelectorAll('.ac-ligne').forEach((l) => {
				const t0 = avant.get(l.dataset.id);
				if (t0 === undefined) { l.classList.add('is-arrivee'); return; }
				const dy = t0 - l.getBoundingClientRect().top;
				if (Math.abs(dy) > 1) {
					l.animate([{ transform: 'translateY(' + dy + 'px)' }, { transform: 'none' }], { duration: 420, easing: 'cubic-bezier(.22, 1, .36, 1)' });
				}
			});
		}
		if (opts.eclat) {
			const l = liste.querySelector('.ac-ligne[data-id="' + opts.eclat + '"]');
			if (l) {
				l.classList.add('is-eclat');
				if (opts.statut) { const s = l.querySelector('.ac-statut'); if (s) { s.classList.add('is-change'); } }
				setTimeout(() => l.classList.remove('is-eclat'), 1700);
				l.scrollIntoView({ block: 'nearest', behavior: reduit() ? 'auto' : 'smooth' });
			}
		}
	}

	/* --- Serveur --------------------------------------------------------------------- */

	async function jetonFrais() {
		const rep = await fetch(location.pathname, { credentials: 'same-origin', cache: 'no-store' });
		const m = /var CSRF_TOKEN = '([0-9a-f]+)'/.exec(await rep.text());
		if (m) { window.CSRF_TOKEN = m[1]; }
	}

	/** POST vers un point d'ecriture ; un jeton expire est renouvele une fois. */
	async function poster(url, corps, deuxieme) {
		corps.set('csrf_token', window.CSRF_TOKEN || '');
		const rep = await fetch(url, {
			method: 'POST', body: corps, credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
		});
		let json = null;
		try { json = await rep.clone().json(); } catch (x) { /* reponse HTML */ }
		const jetonRefuse = (!json && rep.status === 403) || (json && json.success === false && /csrf/i.test(json.message || ''));
		if (jetonRefuse && !deuxieme) { await jetonFrais(); return poster(url, corps, true); }
		if (!rep.ok || !json || !json.success) {
			throw new Error((json && json.message) || 'Le serveur n’a pas répondu comme prévu : réessayez.');
		}
		return json;
	}

	/* --- Epingler, masquer, supprimer ------------------------------------------------ */

	async function basculer(a, champ) {
		const avant = a[champ];
		a[champ] = avant ? 0 : 1;
		rendre({ flip: true, eclat: a.id, statut: champ === 'is_active' });
		try {
			const corps = new FormData();
			corps.set('id', a.id);
			corps.set('field', champ);
			corps.set('value', a[champ]);
			await poster(D.points.basculer, corps);
			notifier('success', champ === 'is_pinned'
				? (a.is_pinned ? 'Annonce épinglée en tête.' : 'Annonce désépinglée.')
				: (a.is_active ? 'Annonce visible par les étudiants.' : 'Annonce masquée aux étudiants.'));
		} catch (x) {
			a[champ] = avant;
			rendre({ flip: true });
			notifier('error', x.message);
		}
	}

	async function supprimer(a) {
		const ok = await confirmer('« ' + a.title + ' » disparaît de l’espace étudiant et de cette liste. On ne peut pas l’annuler.', {
			title: 'Supprimer cette annonce ?', confirmText: 'Supprimer', cancelText: 'Garder', danger: true,
		});
		if (!ok) { return; }
		try {
			const corps = new FormData();
			corps.set('id', a.id);
			await poster(D.points.supprimer, corps);
			const ligne = liste.querySelector('.ac-ligne[data-id="' + a.id + '"]');
			const fin = () => { annonces = annonces.filter((x) => x.id !== a.id); rendre({ flip: true }); };
			if (ligne && !reduit()) {
				ligne.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateX(16px)' }], { duration: 220, easing: 'ease-in', fill: 'forwards' }).onfinish = fin;
			} else {
				fin();
			}
			notifier('success', 'Annonce supprimée.');
		} catch (x) {
			notifier('error', x.message);
		}
	}

	/* --- L'editeur --------------------------------------------------------------------- */

	const champ = (nom) => form.elements[nom];
	const depot = document.getElementById('acDepot');
	const choisie = form.querySelector('.ac-image__choisie');
	const apercuImage = document.getElementById('acImageApercu');
	const infoImage = document.getElementById('acImageInfo');
	const carte = document.getElementById('acCarte');
	const pied = document.getElementById('acPied');
	const question = document.getElementById('acQuestion');
	const message = document.getElementById('acMessage');
	let edite = null;          // l'annonce modifiee, ou null pour une nouvelle
	let fichierImage = null;   // l'image choisie, deja reduite
	let retirerImage = false;
	let urlObjet = null;
	let depart = '';
	let reponse = null;        // la question « Abandonner ? » en attente

	/* La fenetre est modale : elle passe au-dessus des notifications de la
	   page. Ses questions et ses erreurs s'affichent donc dans son pied. */
	function messageEditeur(texte) {
		message.textContent = texte || '';
		message.hidden = !texte;
	}
	function demander() {
		return new Promise((ok) => {
			reponse = ok;
			pied.classList.add('is-question');
			question.hidden = false;
			question.querySelector('[data-ac="continuer"]').focus();
		});
	}
	function repondre(abandonner) {
		pied.classList.remove('is-question');
		question.hidden = true;
		const r = reponse;
		reponse = null;
		if (r) { r(abandonner); }
	}

	function valeurs() {
		const cat = form.querySelector('input[name="category"]:checked');
		return {
			title: champ('title').value.trim(),
			category: cat ? cat.value : 'info',
			excerpt: champ('excerpt').value.trim(),
			content: champ('content').value.trim(),
			publish_date: champ('publish_date').value,
			expire_date: champ('expire_date').value,
			is_active: champ('is_active').checked ? 1 : 0,
			is_pinned: champ('is_pinned').checked ? 1 : 0,
			author: champ('author').value.trim(),
		};
	}
	const instantane = () => JSON.stringify(valeurs()) + '|' + (fichierImage ? fichierImage.size : '') + '|' + retirerImage;

	function imageCourante() {
		if (fichierImage && urlObjet) { return urlObjet; }
		if (edite && edite.image && !retirerImage) { return D.images + edite.image; }
		return null;
	}

	function montrerImage() {
		const url = imageCourante();
		choisie.hidden = !url;
		depot.hidden = !!url;
		if (url) {
			apercuImage.src = url;
			infoImage.textContent = fichierImage
				? 'Nouvelle image, ' + Math.max(1, Math.round(fichierImage.size / 1024)) + ' Ko' + (fichierImage.dimensions ? ' · ' + fichierImage.dimensions : '')
				: 'Image actuelle';
		}
	}

	/** Reduit l'image a 1600 px au plus (JPEG 85) dans le navigateur, avant l'envoi. */
	async function reduire(fichier) {
		if (fichier.type === 'image/gif') { return fichier; }
		const url = URL.createObjectURL(fichier);
		try {
			const img = await new Promise((ok, ko) => { const i = new Image(); i.onload = () => ok(i); i.onerror = ko; i.src = url; });
			const echelle = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight, 1));
			const l = Math.max(1, Math.round(img.naturalWidth * echelle));
			const h = Math.max(1, Math.round(img.naturalHeight * echelle));
			if (echelle === 1 && fichier.type === 'image/jpeg' && fichier.size < 700 * 1024) {
				fichier.dimensions = l + ' × ' + h;
				return fichier;
			}
			const toile = document.createElement('canvas');
			toile.width = l;
			toile.height = h;
			const ctx = toile.getContext('2d');
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, l, h);
			ctx.drawImage(img, 0, 0, l, h);
			const blob = await new Promise((ok) => toile.toBlob(ok, 'image/jpeg', 0.85));
			if (!blob) { return fichier; }
			const reduite = new File([blob], (fichier.name.replace(/\.[^.]+$/, '') || 'image') + '.jpg', { type: 'image/jpeg' });
			reduite.dimensions = l + ' × ' + h;
			return reduite;
		} finally {
			URL.revokeObjectURL(url);
		}
	}

	async function choisirImage(fichier) {
		if (!fichier) { return; }
		if (!/^image\/(jpeg|png|webp|gif)$/.test(fichier.type)) {
			messageEditeur('Ce fichier n’est pas une image JPG, PNG, WEBP ou GIF.');
			return;
		}
		messageEditeur('');
		try { fichierImage = await reduire(fichier); } catch (x) { fichierImage = fichier; }
		retirerImage = false;
		if (urlObjet) { URL.revokeObjectURL(urlObjet); }
		urlObjet = URL.createObjectURL(fichierImage);
		montrerImage();
		majApercu();
	}

	function compteurs() {
		form.querySelectorAll('.ac-compte').forEach((c) => {
			const el = champ(c.dataset.pour);
			const max = +el.getAttribute('maxlength');
			const n = el.value.length;
			c.textContent = n ? n + ' / ' + max : '';
			c.classList.toggle('is-presque', n > max * 0.9);
		});
	}

	function agrandir(zone) {
		zone.style.height = 'auto';
		zone.style.height = Math.min(zone.scrollHeight + 2, 420) + 'px';
	}

	/** La phrase sous les dates : ce que verront les etudiants, et quand. */
	function majDates() {
		const v = valeurs();
		const p = document.getElementById('acDatesEtat');
		p.classList.remove('is-erreur');
		const fin = v.expire_date ? ', jusqu’au ' + dateLongue(v.expire_date) : ', sans date de fin';
		let html;
		if (v.publish_date && v.expire_date && v.expire_date < v.publish_date) {
			p.classList.add('is-erreur');
			html = 'La fin d’affichage vient avant la publication : corrigez une des deux dates.';
		} else if (!v.is_active) {
			html = '<b>Masquée</b> : les étudiants ne la verront pas tant que « Visible » reste décoché.';
		} else if (v.expire_date && v.expire_date < AUJ) {
			html = '<b>Expirée</b> : sa date de fin est passée, elle n’est plus affichée.';
		} else if (v.publish_date && v.publish_date > AUJ) {
			html = '<b>Programmée</b> : visible à partir du ' + dateLongue(v.publish_date) + fin + '.';
		} else {
			const jours = v.expire_date ? jour(v.expire_date) - jour(AUJ) + 1 : null;
			html = '<b>En ligne</b> dès l’enregistrement' + fin + (jours ? ' (' + jours + ' jour' + (jours > 1 ? 's' : '') + ')' : '') + '.';
		}
		p.innerHTML = html;
	}

	/** La carte de la page des etudiants (src/student.actus.php, createCard). */
	function majApercu() {
		const v = valeurs();
		const cat = CATS[v.category] || CATS.info;
		const url = imageCourante();
		const date = v.publish_date ? new Date(v.publish_date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
		carte.innerHTML = '<div class="ac-carte__visuel" style="background:' + (url ? '#0a1628' : (DEGRADES[v.category] || DEGRADES.info)) + '">'
			+ (url ? '<img src="' + esc(url) + '" alt="">' : '<i class="bi ' + cat.icon + '" aria-hidden="true"></i>') + '</div>'
			+ '<div class="ac-carte__corps">'
			+ '<span class="ac-carte__cat" style="color:' + cat.color + '"><i class="bi ' + cat.icon + '" aria-hidden="true"></i>' + esc(cat.label)
			+ (v.is_pinned ? ' <i class="bi bi-pin-fill" aria-hidden="true"></i>' : '') + '</span>'
			+ '<p class="ac-carte__titre' + (v.title ? '' : ' is-vide') + '">' + esc(v.title || 'Le titre de l’annonce') + '</p>'
			+ '<p class="ac-carte__resume">' + esc(resumeDe(v.excerpt, v.content) || 'Le résumé, ou le début du contenu.') + '</p>'
			+ '</div>'
			+ '<div class="ac-carte__pied"><span>01 • ' + esc(date) + '</span><span>' + esc(v.author) + '</span></div>';
		const etat = etatDe(Object.assign({}, v));
		document.getElementById('acApercuNote').textContent = {
			en_ligne: v.is_pinned ? 'Épinglée : elle passera avant toutes les autres.' : 'Elle prendra sa place selon sa date de publication.',
			programmee: 'Programmée : elle n’apparaîtra qu’à sa date de publication.',
			masquee: 'Masquée : les étudiants ne la verront pas.',
			expiree: 'Expirée : les étudiants ne la voient plus.',
		}[etat];
	}

	function effacerErreurs() {
		form.querySelectorAll('.ac-erreur').forEach((x) => x.remove());
		form.querySelectorAll('.is-erreur').forEach((x) => { if (x.classList.contains('ac-champ')) { x.classList.remove('is-erreur'); } });
	}
	function erreur(nom, message) {
		const el = champ(nom);
		const bloc = el.closest('.ac-champ');
		bloc.classList.add('is-erreur');
		const p = document.createElement('small');
		p.className = 'ac-erreur';
		p.id = 'acErr-' + nom;
		p.textContent = message;
		bloc.appendChild(p);
		el.setAttribute('aria-describedby', p.id);
		el.setAttribute('aria-invalid', 'true');
		return el;
	}
	function controler() {
		effacerErreurs();
		form.querySelectorAll('[aria-invalid]').forEach((x) => { x.removeAttribute('aria-invalid'); x.removeAttribute('aria-describedby'); });
		const v = valeurs();
		const fautifs = [];
		if (!v.title) { fautifs.push(erreur('title', 'Donnez un titre à l’annonce.')); }
		if (!v.content) { fautifs.push(erreur('content', 'Écrivez le texte de l’annonce.')); }
		if (v.publish_date && v.expire_date && v.expire_date < v.publish_date) {
			fautifs.push(erreur('expire_date', 'La fin d’affichage doit venir après la publication.'));
		}
		return fautifs;
	}

	function remplir(a) {
		edite = a || null;
		fichierImage = null;
		retirerImage = false;
		if (urlObjet) { URL.revokeObjectURL(urlObjet); urlObjet = null; }
		form.reset();
		effacerErreurs();
		messageEditeur('');
		repondre(false);
		champ('title').value = a ? a.title : '';
		const cat = form.querySelector('input[name="category"][value="' + (a ? a.category : 'info') + '"]');
		if (cat) { cat.checked = true; }
		champ('excerpt').value = a ? (a.excerpt || '') : '';
		champ('content').value = a ? a.content : '';
		champ('publish_date').value = a ? (a.publish_date || '') : AUJ;
		champ('expire_date').value = a ? (a.expire_date || '') : '';
		champ('is_active').checked = a ? !!a.is_active : true;
		champ('is_pinned').checked = a ? !!a.is_pinned : false;
		champ('author').value = a ? (a.author || '') : (D.auteur || '');
		document.getElementById('acEditeurTitre').textContent = a ? 'Modifier l’annonce' : 'Nouvelle annonce';
		document.getElementById('acValider').querySelector('span').textContent = a ? 'Enregistrer' : 'Publier';
		document.getElementById('acEditeurId').textContent = a ? 'Annonce n° ' + a.id : '';
		montrerImage();
		compteurs();
		majDates();
		majApercu();
		depart = instantane();
	}

	function ouvrir(a) {
		remplir(a);
		dialog.classList.remove('is-fermeture');
		dialog.showModal();
		agrandir(champ('content'));
		form.querySelector('.ac-editeur__corps').scrollTop = 0;
		if (window.matchMedia('(pointer: fine)').matches) { champ('title').focus(); }
	}

	async function fermer(force) {
		if (!dialog.open) { return; }
		if (!force && instantane() !== depart) {
			if (reponse) { return; } // la question est deja posee
			if (!(await demander())) { return; }
		}
		const finir = () => { dialog.classList.remove('is-fermeture'); dialog.close(); };
		if (reduit()) { finir(); return; }
		dialog.classList.add('is-fermeture');
		setTimeout(finir, 160);
	}

	async function enregistrer() {
		const fautifs = controler();
		if (fautifs.length) { fautifs[0].focus(); return; }
		const bouton = document.getElementById('acValider');
		const icone = bouton.querySelector('i');
		bouton.disabled = true;
		bouton.classList.add('is-charge');
		icone.className = 'bi bi-arrow-repeat';
		try {
			const corps = new FormData(form);
			corps.delete('image');
			if (fichierImage) { corps.append('image', fichierImage, fichierImage.name); }
			if (edite) {
				corps.set('id', edite.id);
				if (retirerImage) { corps.set('remove_image', '1'); }
			}
			const r = await poster(edite ? D.points.modifier : D.points.ajouter, corps);
			if (r.annonce) {
				const a = normaliser(r.annonce);
				annonces = annonces.filter((x) => x.id !== a.id).concat([a]);
				const nouveau = !edite;
				await fermer(true);
				if (filtreEtat !== 'tout' && etatDe(a) !== filtreEtat) { choisirEtat('tout'); }
				rendre({ flip: true, eclat: a.id });
				notifier('success', nouveau ? 'Annonce publiée.' : 'Annonce enregistrée.');
			}
		} catch (x) {
			messageEditeur(x.message);
		} finally {
			bouton.disabled = false;
			bouton.classList.remove('is-charge');
			icone.className = 'bi bi-check-lg';
		}
	}

	/* --- Evenements ---------------------------------------------------------------------- */

	function choisirEtat(etat) {
		filtreEtat = etat;
		etats.querySelectorAll('.ac-etat').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.etat === etat)));
	}

	etats.addEventListener('click', (ev) => {
		const b = ev.target.closest('.ac-etat');
		if (!b) { return; }
		choisirEtat(b.dataset.etat);
		rendre({ arrivee: true });
	});
	let attente = 0;
	recherche.addEventListener('input', () => { clearTimeout(attente); attente = setTimeout(() => rendre(), 120); });
	choixCategorie.addEventListener('change', () => rendre({ arrivee: true }));

	document.getElementById('acNouvelle').addEventListener('click', () => ouvrir(null));

	/* Une image introuvable (fichier retire du serveur) : le degrade de sa categorie. */
	liste.addEventListener('error', (ev) => {
		const img = ev.target;
		if (!img || img.tagName !== 'IMG' || !img.closest('.ac-vignette')) { return; }
		const ligne = img.closest('.ac-ligne');
		const a = ligne && annonces.find((x) => x.id === +ligne.dataset.id);
		const cat = (a && CATS[a.category]) || CATS.info;
		const v = img.closest('.ac-vignette');
		v.style.setProperty('--ac-degrade', DEGRADES[a ? a.category : 'info'] || DEGRADES.info);
		v.innerHTML = '<i class="bi ' + cat.icon + '" aria-hidden="true"></i>';
	}, true);

	liste.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-ac]');
		if (!b) { return; }
		const ligne = b.closest('.ac-ligne');
		const a = ligne && annonces.find((x) => x.id === +ligne.dataset.id);
		if (!a) { return; }
		const action = b.dataset.ac;
		if (action === 'modifier') { ouvrir(a); }
		else if (action === 'epingler') { basculer(a, 'is_pinned'); }
		else if (action === 'visibilite') { basculer(a, 'is_active'); }
		else if (action === 'supprimer') { supprimer(a); }
	});

	form.addEventListener('input', (ev) => {
		if (ev.target.name === 'content') { agrandir(ev.target); }
		if (ev.target.type === 'file') { return; }
		messageEditeur('');
		compteurs();
		majDates();
		majApercu();
		if (ev.target.closest('.ac-champ.is-erreur')) { controler(); }
	});
	form.addEventListener('change', (ev) => {
		if (ev.target.name === 'image') { choisirImage(ev.target.files && ev.target.files[0]); ev.target.value = ''; return; }
		majDates();
		majApercu();
	});
	form.addEventListener('submit', (ev) => { ev.preventDefault(); enregistrer(); });
	form.addEventListener('keydown', (ev) => {
		if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); enregistrer(); }
	});
	form.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-ac]');
		if (!b) { return; }
		if (b.dataset.ac === 'fermer') { fermer(); }
		else if (b.dataset.ac === 'continuer') { repondre(false); champ('title').focus(); }
		else if (b.dataset.ac === 'abandonner') { repondre(true); }
		else if (b.dataset.ac === 'changer-image') { depot.querySelector('input').click(); }
		else if (b.dataset.ac === 'retirer-image') {
			fichierImage = null;
			if (urlObjet) { URL.revokeObjectURL(urlObjet); urlObjet = null; }
			retirerImage = !!(edite && edite.image);
			montrerImage();
			majApercu();
		}
	});
	/* Echap et le clic hors de la fenetre passent par la meme question. */
	dialog.addEventListener('cancel', (ev) => {
		ev.preventDefault();
		if (reponse) { repondre(false); return; } // Echap sur la question : on continue
		fermer();
	});
	dialog.addEventListener('click', (ev) => { if (ev.target === dialog) { fermer(); } });

	['dragenter', 'dragover'].forEach((t) => depot.addEventListener(t, (ev) => { ev.preventDefault(); depot.classList.add('is-survol'); }));
	['dragleave', 'drop'].forEach((t) => depot.addEventListener(t, () => depot.classList.remove('is-survol')));
	depot.addEventListener('drop', (ev) => {
		ev.preventDefault();
		const f = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
		choisirImage(f);
	});

	window.addEventListener('beforeunload', (ev) => {
		if (dialog.open && instantane() !== depart) { ev.preventDefault(); ev.returnValue = ''; }
	});

	/* --- Depart ---------------------------------------------------------------------------- */

	rendre({ arrivee: true });
	setTimeout(() => page.classList.remove('ac-entree'), 900);
	/* Les anciens liens de retour (?success=1&msg=...) : un message, puis une adresse propre. */
	const params = new URLSearchParams(location.search);
	if (params.get('success') === '1' || params.get('error')) {
		notifier(params.get('error') ? 'error' : 'success', params.get('error') || params.get('msg') || 'Opération effectuée.');
		history.replaceState(null, '', location.pathname + location.hash);
	}
})();
