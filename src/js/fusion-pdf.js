/* =============================================================================
   Fusion de PDF — src/fusion-pdf.php

   Tout se passe dans le navigateur, avec pdf-lib : les fichiers sont lus,
   recopies page a page dans un nouveau document, puis telecharges. Rien ne
   part au serveur.
   ========================================================================== */
(function () {
	'use strict';

	const $ = (id) => document.getElementById(id);
	const depot = $('fpDepot'), entree = $('fpFichier'), bloc = $('fpBloc'), liste = $('fpListe');
	const total = $('fpTotal'), etat = $('fpEtat'), champNom = $('fpNom');
	const btnFusion = $('fpFusionner'), btnVider = $('fpVider');
	if (!depot || !liste) { return; }

	const libelleFusion = btnFusion.innerHTML;
	let fichiers = [];          // { id, nom, taille, pages, octets }
	let suivant = 0;            // identifiant du prochain fichier
	let tire = null;            // identifiant de la ligne glissee
	let occupe = false;         // une fusion est en cours

	/* --- Messages --------------------------------------------------------- */
	function dire(texte, type) {
		etat.textContent = texte || '';
		etat.className = 'fp-etat' + (type ? ' fp-etat--' + type : '');
	}
	// Le module de notifications de l'application (init/toast.php), s'il est la.
	function notifier(texte, type) {
		if (window.Toast && typeof window.Toast.show === 'function') { window.Toast.show(texte, type); }
	}

	const PDF = window.PDFLib;
	if (!PDF || !PDF.PDFDocument) {
		dire("La bibliothèque PDF n'a pas pu être chargée. Vérifiez la connexion internet, puis rechargez la page.", 'erreur');
		depot.disabled = true;
		return;
	}
	const { PDFDocument } = PDF;

	/* --- Mise en forme ---------------------------------------------------- */
	function taille(octets) {
		return octets >= 1048576
			? (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo'
			: Math.max(1, Math.round(octets / 1024)) + ' Ko';
	}
	function pluriel(n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); }

	/** Nom du fichier produit : sans caractere interdit, toujours en .pdf. */
	function nomDeSortie() {
		let n = (champNom.value || '')
			.replace(/[\\/:*?"<>|\u0000-\u001f]+/g, ' ')
			.replace(/\s+/g, ' ')
			.trim()
			.replace(/\.pdf$/i, '');
		if (!n) { n = 'fusion'; }
		return n + '.pdf';
	}

	/* --- Ajout de fichiers ------------------------------------------------ */
	async function ajouter(recus) {
		if (occupe || !recus.length) { return; }
		const refus = [];
		dire('Lecture des fichiers…');
		for (const f of recus) {
			if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') {
				refus.push(f.name + ' (pas un PDF)');
				continue;
			}
			try {
				const octets = await f.arrayBuffer();
				const doc = await PDFDocument.load(octets);
				fichiers.push({ id: ++suivant, nom: f.name, taille: f.size, pages: doc.getPageCount(), octets: octets });
			} catch (e) {
				refus.push(f.name + (/encrypt/i.test(String(e && e.message)) ? ' (protégé par mot de passe)' : ' (fichier illisible)'));
			}
		}
		afficher();
		dire(refus.length ? 'Non ajouté : ' + refus.join(', ') + '.' : '', refus.length ? 'erreur' : '');
	}

	/* --- Liste ------------------------------------------------------------ */
	function afficher(focaliser) {
		liste.textContent = '';
		fichiers.forEach(function (f, i) {
			const li = document.createElement('li');
			li.className = 'fp-ligne';
			li.draggable = !occupe;
			li.dataset.id = String(f.id);
			li.innerHTML =
				'<i class="bi bi-grip-vertical fp-ligne__poignee" aria-hidden="true"></i>' +
				'<span class="fp-ligne__rang" aria-hidden="true"></span>' +
				'<i class="bi bi-file-earmark-pdf-fill fp-ligne__icone" aria-hidden="true"></i>' +
				'<span class="fp-ligne__texte"><b></b><small></small></span>' +
				'<span class="fp-ligne__boutons">' +
				'<button type="button" class="fp-icone" data-action="monter"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>' +
				'<button type="button" class="fp-icone" data-action="descendre"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>' +
				'<button type="button" class="fp-icone fp-icone--retirer" data-action="retirer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>' +
				'</span>';
			li.querySelector('.fp-ligne__rang').textContent = String(i + 1);
			const titre = li.querySelector('b');
			titre.textContent = f.nom;
			titre.title = f.nom;
			li.querySelector('small').textContent = pluriel(f.pages, 'page') + ' · ' + taille(f.taille);

			const boutons = li.querySelectorAll('.fp-icone');
			[['monter', 'Monter'], ['descendre', 'Descendre'], ['retirer', 'Retirer']].forEach(function (b, k) {
				boutons[k].title = b[1];
				boutons[k].setAttribute('aria-label', b[1] + ' « ' + f.nom + ' »');
				boutons[k].disabled = occupe;
			});
			if (i === 0) { boutons[0].disabled = true; }
			if (i === fichiers.length - 1) { boutons[1].disabled = true; }
			liste.appendChild(li);
		});

		bloc.hidden = fichiers.length === 0;
		const pages = fichiers.reduce(function (s, f) { return s + f.pages; }, 0);
		total.textContent = pluriel(fichiers.length, 'fichier') + ' · ' + pluriel(pages, 'page');
		btnFusion.disabled = occupe || fichiers.length < 2;
		btnFusion.title = fichiers.length < 2 ? 'Ajoutez au moins deux fichiers' : '';
		btnVider.disabled = occupe;
		depot.disabled = occupe;
		champNom.disabled = occupe;

		// Le clavier reste sur le fichier deplace, pas en haut de la page.
		if (focaliser) {
			const li = liste.querySelector('[data-id="' + focaliser.id + '"]');
			const cible = li && (li.querySelector('[data-action="' + focaliser.action + '"]:not(:disabled)')
				|| li.querySelector('.fp-icone:not(:disabled)'));
			if (cible) { cible.focus(); }
		}
	}

	function deplacer(de, vers) {
		if (vers < 0 || vers >= fichiers.length || de === vers) { return; }
		fichiers.splice(vers, 0, fichiers.splice(de, 1)[0]);
	}
	const rangDe = (id) => fichiers.findIndex(function (f) { return f.id === id; });

	liste.addEventListener('click', function (e) {
		const bouton = e.target.closest('[data-action]');
		if (!bouton || occupe) { return; }
		const id = Number(bouton.closest('.fp-ligne').dataset.id);
		const i = rangDe(id);
		const action = bouton.dataset.action;
		if (action === 'monter') { deplacer(i, i - 1); }
		if (action === 'descendre') { deplacer(i, i + 1); }
		if (action === 'retirer') {
			const nom = fichiers[i].nom;
			fichiers.splice(i, 1);
			afficher();
			dire('« ' + nom + ' » retiré.');
			// Focus sur la ligne qui prend sa place, sinon sur la zone de depot.
			const voisin = liste.children[Math.min(i, fichiers.length - 1)];
			(voisin ? voisin.querySelector('[data-action="retirer"]') : depot).focus();
			return;
		}
		afficher({ id: id, action: action });
	});

	/* Reordonner en glissant une ligne. */
	liste.addEventListener('dragstart', function (e) {
		const li = e.target.closest('.fp-ligne');
		if (!li || occupe) { return; }
		tire = Number(li.dataset.id);
		li.classList.add('fp-ligne--tiree');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', li.dataset.id);
	});
	liste.addEventListener('dragover', function (e) {
		if (tire === null) { return; }
		const li = e.target.closest('.fp-ligne');
		if (!li) { return; }
		e.preventDefault();
		liste.querySelectorAll('.fp-ligne--cible').forEach(function (x) { if (x !== li) { x.classList.remove('fp-ligne--cible'); } });
		li.classList.add('fp-ligne--cible');
	});
	liste.addEventListener('drop', function (e) {
		if (tire === null) { return; }
		e.preventDefault();
		e.stopPropagation();
		const li = e.target.closest('.fp-ligne');
		if (li) { deplacer(rangDe(tire), rangDe(Number(li.dataset.id))); }
		tire = null;
		afficher();
	});
	liste.addEventListener('dragend', function () {
		tire = null;
		liste.querySelectorAll('.fp-ligne--tiree, .fp-ligne--cible').forEach(function (x) {
			x.classList.remove('fp-ligne--tiree', 'fp-ligne--cible');
		});
	});

	/* --- Depot de fichiers --------------------------------------------------- */
	const avecFichiers = (e) => !!(e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1);

	depot.addEventListener('click', function () { entree.click(); });
	entree.addEventListener('change', function () {
		ajouter(Array.from(entree.files));
		entree.value = '';
	});
	['dragenter', 'dragover'].forEach(function (ev) {
		depot.addEventListener(ev, function (e) { if (avecFichiers(e)) { e.preventDefault(); depot.classList.add('fp-depot--survol'); } });
	});
	['dragleave', 'drop'].forEach(function (ev) {
		depot.addEventListener(ev, function () { depot.classList.remove('fp-depot--survol'); });
	});
	/* Un PDF lache n'importe ou sur la page est ajoute : le navigateur l'aurait
	   sinon ouvert a la place de l'application. */
	window.addEventListener('dragover', function (e) { if (avecFichiers(e)) { e.preventDefault(); } });
	window.addEventListener('drop', function (e) {
		if (!avecFichiers(e)) { return; }
		e.preventDefault();
		depot.classList.remove('fp-depot--survol');
		ajouter(Array.from(e.dataTransfer.files));
	});

	/* --- Fusion --------------------------------------------------------------- */
	function telecharger(octets, nom) {
		const url = URL.createObjectURL(new Blob([octets], { type: 'application/pdf' }));
		const a = document.createElement('a');
		a.href = url;
		a.download = nom;
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
	}

	btnFusion.addEventListener('click', async function () {
		if (occupe || fichiers.length < 2) { return; }
		occupe = true;
		afficher();
		btnFusion.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Fusion en cours…';
		dire('Fusion en cours…');
		try {
			// updateMetadata: false, sinon pdf-lib remplace a l'enregistrement
			// le producteur par le sien : les metadonnees sont posees ci-dessous.
			const sortie = await PDFDocument.create({ updateMetadata: false });
			for (const f of fichiers) {
				const source = await PDFDocument.load(f.octets);
				const pages = await sortie.copyPages(source, source.getPageIndices());
				pages.forEach(function (p) { sortie.addPage(p); });
			}
			const nom = nomDeSortie();
			sortie.setTitle(nom.replace(/\.pdf$/i, ''));
			sortie.setCreator('Infinit Registrar — Fusion de PDF');
			sortie.setProducer('Infinit Registrar');
			sortie.setCreationDate(new Date());
			sortie.setModificationDate(new Date());
			const octets = await sortie.save();
			telecharger(octets, nom);
			const message = 'Fusion terminée : ' + pluriel(sortie.getPageCount(), 'page') + ', ' + taille(octets.length)
				+ '. Fichier « ' + nom + ' » téléchargé.';
			dire(message, 'ok');
			notifier(message, 'success');
		} catch (e) {
			dire("La fusion n'a pas abouti : " + ((e && e.message) || e), 'erreur');
			notifier("La fusion n'a pas abouti.", 'error');
		} finally {
			occupe = false;
			btnFusion.innerHTML = libelleFusion;
			afficher();
		}
	});

	btnVider.addEventListener('click', function () {
		if (occupe || !fichiers.length) { return; }
		fichiers = [];
		afficher();
		dire('Liste vidée.');
		depot.focus();
	});

	afficher();
})();
