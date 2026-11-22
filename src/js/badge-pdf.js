/*
 * BADGE PDF — génération des badges étudiants avec pdf-lib
 * ---------------------------------------------------------
 * Utilisé par le badge individuel (data.topdf?ptype=Badge) et par les badges
 * en groupe (data.topdf?ptype=BadgeGr).
 *
 * Remplace html2pdf.js (capture d'écran du DOM via html2canvas) :
 *  - les photos JPEG/PNG sont intégrées telles quelles, sans recompression ni
 *    redimensionnement : la résolution d'origine est conservée ;
 *  - textes et code-barres sont vectoriels (nets à toute échelle) ;
 *  - le fond, le verso et le logo sont intégrés une seule fois puis réutilisés ;
 *  - aucune limite de taille de canvas : fiable même pour des centaines de badges.
 *
 * Design : reprise du badge 2025 (badge_2025_2.jpg / dos-badge_2025_2.jpg).
 *
 * Dépendances (à charger avant ce fichier) :
 *   https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js
 *   https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js
 *
 * Chemins d'images relatifs à une page située dans src/.
 */
(function (global) {
	'use strict';

	const { PDFDocument, StandardFonts, rgb, degrees, pushGraphicsState, popGraphicsState, moveTo, lineTo,
		appendBezierCurve, closePath, clip, endPath, fill, setFillingRgbColor } = PDFLib;

	const ASSETS = {
		front: '../file/badge_2025_2.jpg',
		back: '../file/dos-badge_2025_2.jpg',
		logo: '../file/' + encodeURIComponent('UAZ Official.png'),
		photoDir: '../app/photosetudiants/'
	};

	const MM = 72 / 25.4;
	const A4 = { w: 595.28, h: 841.89 };
	// Formats d'impression sur A4 (mesures des maquettes)
	const LAYOUTS = {
		'6': { cols: 2, rows: 3, w: 75 * MM, h: 85 * MM, gx: 18 * MM, gy: 6 * MM, name: '6-par-page', label: 'A4 · 6 par page (75 × 85 mm)' },
		'9': { cols: 3, rows: 3, w: 54 * MM, h: 85.6 * MM, gx: 9 * MM, gy: 5 * MM, name: '9-par-page', label: 'A4 · 9 par page (54 × 85,6 mm)' }
	};
	const COLORS = {
		white: rgb(1, 1, 1), black: rgb(0, 0, 0), grey: rgb(0.86, 0.86, 0.86),
		navy: rgb(7 / 255, 50 / 255, 90 / 255), cut: rgb(0.5, 0.5, 0.5)
	};

	const hasPhoto = (s) => String(s.image_student || '').length > 7;

	/* ---------- chargement d'images sans perte ---------- */
	function jpegOrientation(b) {
		if (b[0] !== 0xFF || b[1] !== 0xD8) return 1;
		let p = 2;
		while (p + 4 < b.length) {
			if (b[p] !== 0xFF) return 1;
			const marker = b[p + 1], len = (b[p + 2] << 8) | b[p + 3];
			if (marker === 0xE1 && b[p + 4] === 0x45 && b[p + 5] === 0x78) { // "Exif"
				const t = p + 10, le = b[t] === 0x49;
				const u16 = (o) => le ? b[o] | (b[o + 1] << 8) : (b[o] << 8) | b[o + 1];
				const u32 = (o) => le ? (b[o] | (b[o + 1] << 8) | (b[o + 2] << 16) | (b[o + 3] << 24)) >>> 0 : ((b[o] << 24) | (b[o + 1] << 16) | (b[o + 2] << 8) | b[o + 3]) >>> 0;
				const ifd = t + u32(t + 4), n = u16(ifd);
				for (let i = 0; i < n; i++) { const e = ifd + 2 + i * 12; if (u16(e) === 0x0112) return u16(e + 8); }
				return 1;
			}
			if (marker === 0xDA) return 1;
			p += 2 + len;
		}
		return 1;
	}

	async function embedImage(pdf, url) {
		const res = await fetch(url, { credentials: 'same-origin' });
		if (!res.ok) throw new Error('HTTP ' + res.status);
		const bytes = new Uint8Array(await res.arrayBuffer());
		const isJpg = bytes[0] === 0xFF && bytes[1] === 0xD8;
		const isPng = bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4E && bytes[3] === 0x47;
		try {
			// Intégration directe des octets d'origine : aucune perte de qualité
			if (isJpg && jpegOrientation(bytes) <= 1) return await pdf.embedJpg(bytes);
			if (isPng) return await pdf.embedPng(bytes);
		} catch (e) { /* format exotique : décodage par le navigateur ci-dessous */ }
		// Repli (WebP, JPEG pivoté par EXIF…) : décodage à la taille native, JPEG qualité 0,95
		const bmp = await createImageBitmap(new Blob([bytes]), { imageOrientation: 'from-image' });
		const c = document.createElement('canvas'); c.width = bmp.width; c.height = bmp.height;
		c.getContext('2d').drawImage(bmp, 0, 0);
		const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.95));
		return pdf.embedJpg(new Uint8Array(await blob.arrayBuffer()));
	}

	/* ---------- primitives de dessin ---------- */
	function roundRectOps(x, y, w, h, r) {
		r = Math.min(r, w / 2, h / 2); const k = 0.5523 * r;
		return [moveTo(x + r, y), lineTo(x + w - r, y), appendBezierCurve(x + w - r + k, y, x + w, y + r - k, x + w, y + r),
			lineTo(x + w, y + h - r), appendBezierCurve(x + w, y + h - r + k, x + w - r + k, y + h, x + w - r, y + h),
			lineTo(x + r, y + h), appendBezierCurve(x + r - k, y + h, x, y + h - r + k, x, y + h - r),
			lineTo(x, y + r), appendBezierCurve(x, y + r - k, x + r - k, y, x + r, y), closePath()];
	}
	function fillRoundRect(page, x, y, w, h, r, color) {
		page.pushOperators(pushGraphicsState(), setFillingRgbColor(color.red, color.green, color.blue), ...roundRectOps(x, y, w, h, r), fill(), popGraphicsState());
	}
	// Image en mode « cover » (comme background-size: cover), découpée dans un rectangle arrondi
	function drawCover(page, img, x, y, w, h, r = 0) {
		const s = Math.max(w / img.width, h / img.height), dw = img.width * s, dh = img.height * s;
		page.pushOperators(pushGraphicsState(), ...roundRectOps(x, y, w, h, r), clip(), endPath());
		page.drawImage(img, { x: x + (w - dw) / 2, y: y + (h - dh) / 2, width: dw, height: dh });
		page.pushOperators(popGraphicsState());
	}

	// Les polices standard PDF ne couvrent que WinAnsi : on remplace le reste proprement
	const safeCache = new Map();
	function safeText(font, t) {
		t = String(t ?? '').replace(/\s+/g, ' ').trim();
		return [...t].map((ch) => {
			if (!safeCache.has(ch)) {
				let ok = ch;
				try { font.encodeText(ch); } catch { ok = ch.normalize('NFD').replace(/[̀-ͯ]/g, ''); try { font.encodeText(ok); } catch { ok = '?'; } }
				safeCache.set(ch, ok);
			}
			return safeCache.get(ch);
		}).join('');
	}
	// Taille de police réduite si besoin pour tenir dans maxW
	function fitSize(font, text, maxSize, maxW) {
		const w1 = font.widthOfTextAtSize(text, 1);
		return w1 ? Math.min(maxSize, maxW / w1) : maxSize;
	}
	function centerText(page, font, text, cx, baseline, maxSize, maxW, color) {
		text = safeText(font, text); if (!text) return;
		const size = fitSize(font, text, maxSize, maxW);
		page.drawText(text, { x: cx - font.widthOfTextAtSize(text, size) / 2, y: baseline, size, font, color });
	}
	// Texte vertical lu de bas en haut, centré sur la colonne cx, commençant à yStart
	function verticalText(page, font, text, cx, yStart, maxSize, maxLen, color) {
		text = safeText(font, text); if (!text) return;
		const size = fitSize(font, text, maxSize, maxLen);
		page.drawText(text, { x: cx + size * 0.36, y: yStart, size, font, color, rotate: degrees(90) });
	}

	function drawBarcode(page, font, code, x, y, w, h) {
		page.drawRectangle({ x, y, width: w, height: h, color: COLORS.white });
		code = String(code ?? '').trim(); if (!code) return;
		const enc = {};
		try { JsBarcode(enc, code, { format: 'CODE128' }); } catch { return; }
		const bits = (enc.encodings || []).map((e) => e.data).join('');
		if (!bits) return;
		// Zone de silence de 10 modules de chaque côté (norme Code 128) : sans elle,
		// le fond foncé du badge est pris pour une barre et la lecture échoue.
		const QUIET = 10, mw = w / (bits.length + 2 * QUIET), padX = QUIET * mw;
		const textH = h * 0.22, barsH = h - textH - h * 0.12, by = y + textH + h * 0.04;
		for (let i = 0; i < bits.length;) {
			if (bits[i] !== '1') { i++; continue; }
			let j = i; while (bits[j] === '1') j++;
			page.drawRectangle({ x: x + padX + i * mw, y: by, width: (j - i) * mw, height: barsH, color: COLORS.black });
			i = j;
		}
		centerText(page, font, code, x + w / 2, y + h * 0.05, textH * 0.85, w - 2 * padX, COLORS.black);
	}

	/* ---------- recto d'un badge ---------- */
	function drawFront(page, f, assets, s, x, y, w, h, photo, opts) {
		const T = (t) => y + h - t * h; // position mesurée depuis le haut (fraction de h)
		const cx = x + w / 2;

		// Fond
		if (assets.front) drawCover(page, assets.front, x, y, w, h);
		else page.drawRectangle({ x, y, width: w, height: h, color: COLORS.navy });

		// En-tête : logo + établissement
		const pad = 0.053 * w;
		let logoW = 0;
		if (assets.logo) {
			const lh = 0.093 * h; logoW = lh * assets.logo.width / assets.logo.height;
			page.drawImage(assets.logo, { x: x + pad, y: T(0.047) - lh, width: logoW, height: lh });
		}
		const titleW = w - 2 * (pad + logoW + 0.02 * w);
		centerText(page, f.bold, 'Université Adventiste Zurcher', cx, T(0.08), 0.031 * h, titleW, COLORS.white);
		centerText(page, f.bold, 'BP.325 Antsirabe (110)', cx, T(0.108), 0.022 * h, titleW, COLORS.white);
		centerText(page, f.bold, 'Tel : ' + (opts.phone || ''), cx, T(0.135), 0.022 * h, titleW, COLORS.white);

		// Photo (bordure blanche arrondie)
		const ph = 0.374 * h, pw = Math.min(1.05 * ph, 0.56 * w);
		const px = cx - pw / 2, py = T(0.178) - ph, b = 0.0095 * h, r = 0.031 * h;
		fillRoundRect(page, px - b, py - b, pw + 2 * b, ph + 2 * b, r + b, COLORS.white);
		if (photo) drawCover(page, photo, px, py, pw, ph, r);
		else fillRoundRect(page, px, py, pw, ph, r, COLORS.grey);

		// Matricule vertical (à gauche) et statut / abonnement (à droite)
		const yStart = T(0.49), colLen = 0.33 * h;
		verticalText(page, f.bold, s.student_id, (px - x - b) / 2 + x, yStart, 0.062 * h, colLen, COLORS.white);
		const ss = 0.031 * h, sx = px + pw + b + (x + w - (px + pw + b)) * 0.35;
		verticalText(page, f.bold, s.status, sx, yStart, ss, colLen, COLORS.white);
		if (String(s.abonment) === '1') verticalText(page, f.bold, 'Abonné(e)', sx + ss * 1.2, yStart, ss, colLen, COLORS.white);

		// Nom et prénom
		const nameW = 0.9 * w;
		centerText(page, f.regular, String(s.student_nom ?? '').toUpperCase(), cx, T(0.623), 0.037 * h, nameW, COLORS.white);
		centerText(page, f.regular, s.student_prenom, cx, T(0.67), 0.037 * h, nameW, COLORS.white);

		// Mention (filet blanc au-dessus)
		const mw = Math.min(0.715 * w, 0.9 * w);
		page.drawLine({ start: { x: cx - mw / 2, y: T(0.70) }, end: { x: cx + mw / 2, y: T(0.70) }, thickness: 0.5, color: COLORS.white });
		centerText(page, f.bold, String(s.etude_envisage ?? '').toUpperCase(), cx, T(0.745), 0.037 * h, mw, COLORS.white);

		// Code-barres : largeur mini 36 mm, zones de silence comprises, soit des barres
		// d'environ 0,25 mm (minimum recommandé pour les lecteurs courants)
		const bw = Math.min(Math.max(0.48 * w, 36 * MM), 0.8 * w), bh = 0.115 * h;
		drawBarcode(page, f.regular, s.lookup_code, cx - bw / 2, T(0.815) - bh, bw, bh);

		if (opts.border) page.drawRectangle({ x, y, width: w, height: h, borderColor: COLORS.cut, borderWidth: 0.4, borderDashArray: [3, 2] });
	}

	/* ---------- verso ---------- */
	function drawBack(page, assets, x, y, w, h, opts) {
		const img = assets.back;
		if (img) {
			// Le verso contient du texte : en « cover », une carte étroite (format 9 par
			// page) en rognerait les côtés (28 % pour le verso 75 × 85 mm). Au-delà de
			// 10 % de rognage, on affiche l'image entière, centrée sur fond blanc.
			const s = Math.max(w / img.width, h / img.height);
			const lost = 1 - Math.min(w / (img.width * s), h / (img.height * s));
			if (lost <= 0.1) drawCover(page, img, x, y, w, h);
			else {
				const c = Math.min(w / img.width, h / img.height), dw = img.width * c, dh = img.height * c;
				page.drawRectangle({ x, y, width: w, height: h, color: COLORS.white });
				page.drawImage(img, { x: x + (w - dw) / 2, y: y + (h - dh) / 2, width: dw, height: dh });
			}
		}
		if (opts.border) page.drawRectangle({ x, y, width: w, height: h, borderColor: COLORS.cut, borderWidth: 0.4, borderDashArray: [3, 2] });
	}

	async function mapLimit(items, limit, fn) {
		const out = new Array(items.length); let next = 0;
		await Promise.all(Array.from({ length: Math.min(limit, items.length) }, async () => {
			while (next < items.length) { const i = next++; out[i] = await fn(items[i], i); }
		}));
		return out;
	}

	function slots(L) {
		const ox = (A4.w - (L.cols * L.w + (L.cols - 1) * L.gx)) / 2;
		const oy = (A4.h - (L.rows * L.h + (L.rows - 1) * L.gy)) / 2;
		const out = [];
		for (let row = 0; row < L.rows; row++)
			for (let col = 0; col < L.cols; col++)
				out.push({ x: ox + col * (L.w + L.gx), y: A4.h - oy - (row + 1) * L.h - row * L.gy });
		return out;
	}

	/**
	 * Génère le PDF.
	 * @param {Array}  list  étudiants : {student_id, student_nom, student_prenom, etude_envisage,
	 *                       status, abonment, lookup_code, image_student}
	 * @param {Object} opts  { layout: '6'|'9', back: bool (page de verso en tête, comme l'export
	 *                       HTML), border: bool (contour de découpe), phone: string,
	 *                       onProgress(done, total, message) }
	 * @returns {Promise<{bytes: Uint8Array, warnings: string[], pages: number, layout: Object}>}
	 */
	async function generate(list, opts = {}) {
		const L = LAYOUTS[opts.layout] || LAYOUTS['6'];
		const o = { border: opts.border !== false, phone: opts.phone || '' };
		const progress = opts.onProgress || (() => {});
		const warnings = [];

		const pdf = await PDFDocument.create();
		pdf.setTitle('Badges étudiants'); pdf.setCreator('Infinit Registrar');
		const f = { bold: await pdf.embedFont(StandardFonts.HelveticaBold), regular: await pdf.embedFont(StandardFonts.Helvetica) };

		progress(0, 1, 'Chargement du fond et du logo…');
		const assets = {};
		try { assets.front = await embedImage(pdf, ASSETS.front); } catch { warnings.push('Fond du badge introuvable (' + ASSETS.front + ')'); }
		try { assets.logo = await embedImage(pdf, ASSETS.logo); } catch { warnings.push('Logo introuvable'); }
		if (opts.back) { try { assets.back = await embedImage(pdf, ASSETS.back); } catch { warnings.push('Verso introuvable (' + ASSETS.back + ')'); } }

		// Photos : téléchargées 4 par 4, intégrées en résolution d'origine (une seule fois chacune)
		let loaded = 0;
		const cache = new Map();
		const photos = await mapLimit(list, 4, async (s) => {
			let img = null;
			if (hasPhoto(s)) {
				const url = ASSETS.photoDir + encodeURIComponent(s.image_student);
				if (!cache.has(url)) cache.set(url, embedImage(pdf, url));
				try { img = await cache.get(url); }
				catch { warnings.push(`${s.student_id} : photo illisible (${s.image_student})`); }
			} else warnings.push(`${s.student_id} : aucune photo`);
			progress(++loaded, list.length * 2, `Photos ${loaded}/${list.length}`);
			return img;
		});

		const pos = slots(L), perPage = pos.length;
		let pages = 0;
		if (opts.back) { // une planche de verso, comme l'export HTML d'origine
			const p = pdf.addPage([A4.w, A4.h]); pages++;
			pos.forEach((q) => drawBack(p, assets, q.x, q.y, L.w, L.h, o));
		}
		let page;
		list.forEach((s, i) => {
			if (i % perPage === 0) { page = pdf.addPage([A4.w, A4.h]); pages++; }
			const q = pos[i % perPage];
			drawFront(page, f, assets, s, q.x, q.y, L.w, L.h, photos[i], o);
			progress(list.length + i + 1, list.length * 2, `Badges ${i + 1}/${list.length}`);
		});

		progress(1, 1, 'Enregistrement du PDF…');
		const bytes = await pdf.save();
		return { bytes, warnings, pages, layout: L };
	}

	// Déclenche le téléchargement ; renvoie l'URL blob (réutilisable pour un aperçu)
	function download(bytes, filename) {
		const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
		const a = document.createElement('a'); a.href = url; a.download = filename;
		document.body.appendChild(a); a.click(); a.remove();
		return url;
	}

	function stamp(d = new Date()) {
		const p = (n) => String(n).padStart(2, '0');
		return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}_${p(d.getHours())}h${p(d.getMinutes())}`;
	}

	global.BadgePDF = { generate, download, stamp, hasPhoto, LAYOUTS, ASSETS };
})(window);
