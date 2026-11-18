/* =============================================================================
   Generateur de QR Code — src/qr-generator.php

   Le QR est dessine par qr-code-styling (src/js/qr-code-styling.min.js), dans
   le navigateur : ni le contenu ni le logo ne partent au serveur.

   L'apercu est le SVG du QR a la taille d'export, ramene par le CSS a une
   taille d'ecran fixe : il montre exactement ce qui sera telecharge. Le cadre
   et le bandeau de texte, eux, sont dessines deux fois — en CSS pour l'apercu,
   en canevas ou en SVG pour l'export — sur une seule geometrie,
   geometrieCadre(), que reprend aussi src/css/qr-generator.css.
   ========================================================================== */
(function () {
	'use strict';

	const $ = (id) => document.getElementById(id);
	const boiteQr = $('qgQr');
	if (!boiteQr) { return; }

	const scene = $('qgScene'), cadre = $('qgCadre'), bandeau = $('qgCta'), vide = $('qgVide');
	const alertes = $('qgAlertes'), dimensions = $('qgDimensions'), champNom = $('qgNom');
	const boutons = [$('qgPng'), $('qgSvg'), $('qgJpg')];

	const LOGO_MAX = 5 * 1024 * 1024;
	const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];

	let qr = null;            // instance qr-code-styling de l'apercu, reutilisee a l'export
	let logo = null;          // { donnees: data URL, nom }
	let minuterie = 0;
	let occupe = false;

	/* --- Messages --------------------------------------------------------- */
	function afficherAlertes(liste) {
		alertes.textContent = '';
		liste.forEach(function (a) {
			const li = document.createElement('li');
			if (a.erreur) { li.className = 'qg-alerte--erreur'; }
			const i = document.createElement('i');
			i.className = 'bi ' + (a.erreur ? 'bi-x-octagon' : 'bi-exclamation-triangle');
			i.setAttribute('aria-hidden', 'true');
			const span = document.createElement('span');
			span.textContent = a.texte;
			li.append(i, span);
			alertes.appendChild(li);
		});
	}
	function notifier(texte, type) {
		if (window.Toast && typeof window.Toast.show === 'function') { window.Toast.show(texte, type); }
	}

	if (typeof window.QRCodeStyling !== 'function') {
		afficherAlertes([{ erreur: true, texte: "Le générateur n'a pas pu être chargé. Rechargez la page." }]);
		boutons.forEach(function (b) { b.disabled = true; });
		return;
	}

	/* --- Lecture des reglages ---------------------------------------------- */
	const val = (id) => $(id).value;
	const coche = (id) => $(id).checked;
	const taille = () => +val('qgTaille');

	function options(type) {
		const t = taille();
		const points = val('qgPoints');
		const marge = Math.round(t * 0.02);
		const dots = { type: val('qgFormePoints') };
		if (coche('qgDegrade')) {
			dots.gradient = {
				type: val('qgDegradeType'),
				rotation: (+val('qgRotation')) * Math.PI / 180,
				colorStops: [{ offset: 0, color: points }, { offset: 1, color: val('qgPoints2') }]
			};
		} else {
			dots.color = points;
		}
		return {
			type: type,
			width: t,
			height: t,
			data: val('qgDonnees'),
			margin: marge,
			qrOptions: { errorCorrectionLevel: val('qgCorrection') },
			image: logo ? logo.donnees : undefined,
			dotsOptions: dots,
			backgroundOptions: { color: coche('qgTransparent') ? 'rgba(0,0,0,0)' : val('qgFond') },
			cornersSquareOptions: { type: val('qgFormeCoins'), color: points },
			cornersDotOptions: { type: val('qgFormeCentres'), color: points },
			imageOptions: {
				crossOrigin: 'anonymous',
				margin: marge,
				imageSize: (+val('qgLogoTaille')) / 100,
				hideBackgroundDots: coche('qgLogoMasque')
			}
		};
	}

	/* --- Geometrie du cadre exporte ------------------------------------------
	   Proportions de l'apercu rapportees a la taille du QR. Reference : QR de
	   256 px, padding 22, bordure 6, espace 14, police 16, pastille 9/22,
	   rayon 18. Le CSS (.qg-cadre, .qg-cta) reprend les memes coefficients. */
	function geometrieCadre(t) {
		const pad = Math.round(t * 0.086);
		const bord = Math.round(t * 0.023);
		const espace = Math.round(t * 0.055);
		const police = Math.round(t * 0.0625);
		const ctaPadY = Math.round(police * 0.5625);
		const ctaPadX = Math.round(police * 1.375);
		const ctaH = police + ctaPadY * 2;
		return {
			pad: pad, bord: bord, espace: espace, police: police, ctaPadX: ctaPadX, ctaH: ctaH,
			rExt: Math.round(t * 0.094),
			rInt: Math.round(t * 0.070),
			W: t + pad * 2 + bord * 2,
			H: bord * 2 + pad * 2 + t + espace + ctaH
		};
	}

	const POLICE = '"Segoe UI", sans-serif';
	const mesureur = document.createElement('canvas').getContext('2d');
	function largeurTexte(texte, police) {
		mesureur.font = '800 ' + police + 'px ' + POLICE;
		return mesureur.measureText(texte).width;
	}
	const texteBandeau = () => (val('qgTexte') || '').trim().toUpperCase();

	/** Taille de police du bandeau : reduite si le texte depasserait du cadre. */
	function policeBandeau(texte, g) {
		const place = g.W - 2 * g.bord - g.pad - 2 * g.ctaPadX;
		const larg = largeurTexte(texte, g.police);
		return larg > place ? Math.max(6, Math.floor(g.police * place / larg)) : g.police;
	}

	/* --- Lisibilite ------------------------------------------------------- */
	function luminance(hex) {
		const n = parseInt(hex.slice(1), 16);
		const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function misesEnGarde() {
		const liste = [];
		const avecCadre = coche('qgCadreActif');
		const transparent = coche('qgTransparent');
		// Sous un cadre, le fond transparent laisse voir le blanc du cadre.
		const fond = transparent ? (avecCadre ? '#ffffff' : null) : val('qgFond');
		const points = [val('qgPoints')].concat(coche('qgDegrade') ? [val('qgPoints2')] : []);

		if (fond === null) {
			liste.push({ texte: 'Fond transparent : posez ce QR Code sur un fond clair et uni pour qu’il reste lisible.' });
		} else {
			const lf = luminance(fond);
			if (points.some(function (p) { return luminance(p) > lf; })) {
				liste.push({ texte: 'Points plus clairs que le fond : beaucoup de téléphones ne lisent pas les QR Codes inversés.' });
			} else {
				const pire = Math.min.apply(null, points.map(function (p) { return (lf + 0.05) / (luminance(p) + 0.05); }));
				if (pire < 3) {
					liste.push({ texte: 'Contraste faible entre les points et le fond : foncez les points ou éclaircissez le fond.' });
				}
			}
		}
		if (logo && (val('qgCorrection') === 'L' || val('qgCorrection') === 'M')) {
			liste.push({ texte: 'Le logo cache une partie du code : choisissez une correction Élevée (Q) ou Maximale (H).' });
		}
		return liste;
	}

	/* --- Apercu ----------------------------------------------------------- */
	function majCadre() {
		const avecCadre = coche('qgCadreActif');
		cadre.classList.toggle('qg-cadre--nu', !avecCadre);
		scene.classList.toggle('qg-scene--damier', !avecCadre && coche('qgTransparent'));
		cadre.style.setProperty('--qg-cadre', val('qgCadreCouleur'));
		cadre.style.setProperty('--qg-texte', val('qgTexteCouleur'));

		const g = geometrieCadre(taille());
		const texte = texteBandeau();
		bandeau.textContent = texte;
		cadre.style.setProperty('--qg-echelle', texte ? String(policeBandeau(texte, g) / g.police) : '1');

		const l = avecCadre ? g.W : taille(), h = avecCadre ? g.H : taille();
		dimensions.textContent = 'Image exportée : ' + l + ' × ' + h + ' px';
	}

	function dessiner() {
		clearTimeout(minuterie);
		const donnees = val('qgDonnees');
		boiteQr.textContent = '';
		qr = null;

		if (!donnees.trim()) {
			cadre.hidden = true; vide.hidden = false;
			scene.classList.remove('qg-scene--damier');
			afficherAlertes([]);
			majBoutons();
			return;
		}

		/* Une nouvelle instance a chaque changement, plutot que update() : la
		   bibliotheque fusionne alors les options sans retirer les anciennes,
		   et la couleur unie survivait au passage en degrade. */
		try {
			qr = new window.QRCodeStyling(options('svg'));
		} catch (e) {
			cadre.hidden = true; vide.hidden = true;
			afficherAlertes([{ erreur: true, texte: /overflow/i.test(String(e))
				? 'Contenu trop long pour un QR Code : raccourcissez-le, ou choisissez une correction plus faible.'
				: 'Ce contenu ne peut pas être converti en QR Code.' }]);
			majBoutons();
			return;
		}
		qr.append(boiteQr);
		const svg = boiteQr.querySelector('svg');
		if (svg) { svg.setAttribute('viewBox', '0 0 ' + taille() + ' ' + taille()); svg.setAttribute('aria-hidden', 'true'); }

		cadre.hidden = false; vide.hidden = true;
		majCadre();
		afficherAlertes(misesEnGarde());
		majBoutons();
	}
	function plusTard() {
		clearTimeout(minuterie);
		minuterie = setTimeout(dessiner, 90);
	}
	function majBoutons() {
		boutons.forEach(function (b) { b.disabled = occupe || !qr; });
	}

	/* --- Export ----------------------------------------------------------- */
	/** Nom du fichier telecharge : sans caractere interdit ni extension. */
	function nomDeSortie(ext) {
		let n = (champNom.value || '')
			.replace(/[\\/:*?"<>|\u0000-\u001f]+/g, ' ')
			.replace(/\s+/g, ' ')
			.trim()
			.replace(/\.(png|jpe?g|svg)$/i, '');
		if (!n) { n = 'qr-code'; }
		return n + '.' + ext;
	}
	function telecharger(blob, nom) {
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = nom;
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
	}
	function blobEnImage(blob) {
		return new Promise(function (ok, ko) {
			const url = URL.createObjectURL(blob);
			const img = new Image();
			img.onload = function () { URL.revokeObjectURL(url); ok(img); };
			img.onerror = function () { URL.revokeObjectURL(url); ko(new Error('image')); };
			img.src = url;
		});
	}
	function canevasEnBlob(c, type) {
		return new Promise(function (ok) { c.toBlob(ok, type === 'jpg' ? 'image/jpeg' : 'image/png', 0.95); });
	}
	function rectArrondi(ctx, x, y, l, h, r) {
		ctx.beginPath();
		ctx.moveTo(x + r, y);
		ctx.arcTo(x + l, y, x + l, y + h, r);
		ctx.arcTo(x + l, y + h, x, y + h, r);
		ctx.arcTo(x, y + h, x, y, r);
		ctx.arcTo(x, y, x + l, y, r);
		ctx.closePath();
	}

	async function exportImage(type) {
		const t = taille();
		const img = await blobEnImage(await qr.getRawData('png'));
		const c = document.createElement('canvas');
		const ctx = c.getContext('2d');

		if (!coche('qgCadreActif')) {
			c.width = t; c.height = t;
			if (type === 'jpg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, t, t); }  // le JPG n'a pas de transparence
			ctx.drawImage(img, 0, 0, t, t);
			return canevasEnBlob(c, type);
		}

		const g = geometrieCadre(t);
		const couleur = val('qgCadreCouleur');
		c.width = g.W; c.height = g.H;

		// Bordure coloree, puis interieur blanc sur toute la hauteur : le
		// bandeau flotte sur le blanc, comme dans l'apercu.
		rectArrondi(ctx, 0, 0, g.W, g.H, g.rExt); ctx.fillStyle = couleur; ctx.fill();
		rectArrondi(ctx, g.bord, g.bord, g.W - g.bord * 2, g.H - g.bord * 2, g.rInt); ctx.fillStyle = '#fff'; ctx.fill();
		ctx.drawImage(img, g.bord + g.pad, g.bord + g.pad, t, t);

		const texte = texteBandeau();
		if (texte) {
			const police = policeBandeau(texte, g);
			const larg = Math.round(largeurTexte(texte, police) + g.ctaPadX * 2);
			const x = Math.round((g.W - larg) / 2);
			const y = g.H - g.bord - g.pad - g.ctaH;
			rectArrondi(ctx, x, y, larg, g.ctaH, g.ctaH / 2); ctx.fillStyle = couleur; ctx.fill();
			ctx.fillStyle = val('qgTexteCouleur');
			ctx.font = '800 ' + police + 'px ' + POLICE;
			ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
			ctx.fillText(texte, g.W / 2, y + g.ctaH / 2);
		}
		return canevasEnBlob(c, type);
	}

	const echapper = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

	async function exportSvg() {
		const t = taille();
		const brut = await (await qr.getRawData('svg')).text();
		// Contenu du <svg> du QR, sans sa declaration XML ni sa balise
		const interieur = brut.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
		const entete = '<?xml version="1.0" encoding="UTF-8"?>\n';
		let svg;

		if (!coche('qgCadreActif')) {
			svg = entete + '<svg xmlns="http://www.w3.org/2000/svg" width="' + t + '" height="' + t + '" viewBox="0 0 ' + t + ' ' + t + '">'
				+ interieur + '</svg>';
		} else {
			const g = geometrieCadre(t);
			const couleur = val('qgCadreCouleur');
			const texte = texteBandeau();
			let bandeauSvg = '';
			if (texte) {
				const police = policeBandeau(texte, g);
				const larg = Math.round(largeurTexte(texte, police) + g.ctaPadX * 2);
				const x = Math.round((g.W - larg) / 2);
				const y = g.H - g.bord - g.pad - g.ctaH;
				bandeauSvg = '<rect x="' + x + '" y="' + y + '" width="' + larg + '" height="' + g.ctaH + '" rx="' + (g.ctaH / 2) + '" fill="' + couleur + '"/>'
					+ '<text x="' + (g.W / 2) + '" y="' + (y + g.ctaH / 2) + '" fill="' + val('qgTexteCouleur') + '" font-family="Segoe UI, Arial, sans-serif"'
					+ ' font-weight="800" font-size="' + police + '" text-anchor="middle" dominant-baseline="central">' + echapper(texte) + '</text>';
			}
			svg = entete + '<svg xmlns="http://www.w3.org/2000/svg" width="' + g.W + '" height="' + g.H + '" viewBox="0 0 ' + g.W + ' ' + g.H + '">'
				+ '<rect x="0" y="0" width="' + g.W + '" height="' + g.H + '" rx="' + g.rExt + '" fill="' + couleur + '"/>'
				+ '<rect x="' + g.bord + '" y="' + g.bord + '" width="' + (g.W - g.bord * 2) + '" height="' + (g.H - g.bord * 2) + '" rx="' + g.rInt + '" fill="#ffffff"/>'
				+ '<svg x="' + (g.bord + g.pad) + '" y="' + (g.bord + g.pad) + '" width="' + t + '" height="' + t + '" viewBox="0 0 ' + t + ' ' + t + '">' + interieur + '</svg>'
				+ bandeauSvg
				+ '</svg>';
		}
		return new Blob([svg], { type: 'image/svg+xml' });
	}

	async function exporter(type) {
		if (!qr || occupe) { return; }
		occupe = true; majBoutons();
		try {
			const blob = type === 'svg' ? await exportSvg() : await exportImage(type);
			if (!blob) { throw new Error('vide'); }
			telecharger(blob, nomDeSortie(type));
		} catch (e) {
			notifier("Le QR Code n'a pas pu être exporté. Réessayez, ou retirez le logo s'il en a un.", 'error');
		} finally {
			occupe = false; majBoutons();
		}
	}
	$('qgPng').addEventListener('click', function () { exporter('png'); });
	$('qgSvg').addEventListener('click', function () { exporter('svg'); });
	$('qgJpg').addEventListener('click', function () { exporter('jpg'); });

	/* --- Logo ------------------------------------------------------------- */
	const depot = $('qgLogoDepot'), entree = $('qgLogoFichier'), erreurLogo = $('qgLogoErreur');
	const libelleDepot = depot.querySelector('span b');

	function majLogo() {
		$('qgLogoBloc').hidden = !logo;
		libelleDepot.textContent = logo ? 'Changer de logo' : 'Choisissez un logo';
		if (logo) {
			$('qgLogoImage').src = logo.donnees;
			$('qgLogoNom').textContent = logo.nom;
		} else {
			$('qgLogoImage').removeAttribute('src');
		}
	}
	function lireLogo(f) {
		erreurLogo.textContent = '';
		if (!f) { return; }
		if (LOGO_TYPES.indexOf(f.type) === -1) {
			erreurLogo.textContent = '« ' + f.name + ' » n’est pas une image PNG, JPG, SVG ou WebP.';
			return;
		}
		if (f.size > LOGO_MAX) {
			erreurLogo.textContent = '« ' + f.name + ' » dépasse 5 Mo : choisissez une image plus légère.';
			return;
		}
		const lecteur = new FileReader();
		lecteur.onload = function () {
			const donnees = String(lecteur.result);
			const essai = new Image();
			essai.onload = function () { logo = { donnees: donnees, nom: f.name }; majLogo(); dessiner(); };
			essai.onerror = function () { erreurLogo.textContent = '« ' + f.name + ' » ne peut pas être lu comme une image.'; };
			essai.src = donnees;
		};
		lecteur.onerror = function () { erreurLogo.textContent = '« ' + f.name + ' » ne peut pas être lu.'; };
		lecteur.readAsDataURL(f);
	}

	depot.addEventListener('click', function () { entree.click(); });
	entree.addEventListener('change', function () { lireLogo(entree.files[0]); entree.value = ''; });
	depot.addEventListener('dragover', function (e) { e.preventDefault(); depot.classList.add('qg-depot--survol'); });
	depot.addEventListener('dragleave', function () { depot.classList.remove('qg-depot--survol'); });
	depot.addEventListener('drop', function (e) {
		e.preventDefault();
		depot.classList.remove('qg-depot--survol');
		lireLogo(e.dataTransfer && e.dataTransfer.files[0]);
	});
	// Une image lachee a cote de la zone ouvrirait le fichier a la place de la page.
	window.addEventListener('dragover', function (e) { e.preventDefault(); });
	window.addEventListener('drop', function (e) { e.preventDefault(); });

	$('qgLogoRetirer').addEventListener('click', function () {
		logo = null; erreurLogo.textContent = '';
		majLogo(); dessiner();
		depot.focus();
	});

	/* --- Couleurs --------------------------------------------------------- */
	function majPastilles() {
		document.querySelectorAll('.qg-pastille').forEach(function (p) {
			p.setAttribute('aria-pressed', String(val(p.dataset.cible).toLowerCase() === p.dataset.couleur));
		});
	}
	document.querySelectorAll('input[type="color"]').forEach(function (champ) {
		const code = document.querySelector('output[data-hex="' + champ.id + '"]');
		champ.addEventListener('input', function () {
			if (code) { code.textContent = champ.value; }
			majPastilles();
		});
	});
	document.querySelectorAll('.qg-pastille').forEach(function (p) {
		p.addEventListener('click', function () {
			const champ = $(p.dataset.cible);
			champ.value = p.dataset.couleur;
			champ.dispatchEvent(new Event('input', { bubbles: true }));
		});
	});

	/* --- Liaisons --------------------------------------------------------- */
	// Ce qui change le QR lui-meme : on le redessine (en differe pendant la frappe).
	['qgDonnees', 'qgPoints', 'qgFond', 'qgPoints2', 'qgDegradeType', 'qgRotation', 'qgFormePoints',
	 'qgFormeCoins', 'qgFormeCentres', 'qgLogoTaille', 'qgLogoMasque', 'qgTaille', 'qgCorrection']
		.forEach(function (id) { $(id).addEventListener('input', plusTard); });

	// Ce qui ne touche que le cadre : pas besoin de redessiner le QR.
	['qgCadreCouleur', 'qgTexteCouleur', 'qgTexte'].forEach(function (id) {
		$(id).addEventListener('input', function () { if (qr) { majCadre(); } });
	});

	// Fond transparent : la couleur de fond ne sert plus, on la grise.
	function majFond() {
		$('qgFond').disabled = coche('qgTransparent');
		$('qgFond').closest('.qg-champ').classList.toggle('qg-eteint', coche('qgTransparent'));
	}
	$('qgTransparent').addEventListener('change', function () { majFond(); plusTard(); });
	$('qgDegrade').addEventListener('change', function () {
		$('qgDegradeBloc').hidden = !coche('qgDegrade');
		plusTard();
	});
	$('qgCadreActif').addEventListener('change', function () {
		$('qgCadreBloc').hidden = !coche('qgCadreActif');
		if (qr) { majCadre(); afficherAlertes(misesEnGarde()); }
	});

	const sorties = { qgRotation: ['qgRotationV', '°'], qgLogoTaille: ['qgLogoTailleV', ' %'], qgTaille: ['qgTailleV', ' px'] };
	Object.keys(sorties).forEach(function (id) {
		$(id).addEventListener('input', function () { $(sorties[id][0]).textContent = val(id) + sorties[id][1]; });
	});

	/* --- Depart --------------------------------------------------------------
	   Le navigateur peut restaurer des valeurs saisies avant un rechargement :
	   on aligne l'affichage sur l'etat reel des champs avant le premier dessin. */
	document.querySelectorAll('input[type="color"]').forEach(function (c) { c.dispatchEvent(new Event('input')); });
	Object.keys(sorties).forEach(function (id) { $(sorties[id][0]).textContent = val(id) + sorties[id][1]; });
	$('qgDegradeBloc').hidden = !coche('qgDegrade');
	$('qgCadreBloc').hidden = !coche('qgCadreActif');
	majFond();
	majPastilles();
	dessiner();
})();
