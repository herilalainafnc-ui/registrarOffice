/*
 * BADGE PDF — moteur de génération des badges étudiants (pdf-lib)
 * ---------------------------------------------------------------
 * Utilisé par l'export en masse (badges.php) et par le badge individuel
 * (data.topdf.php?ptype=Badge).
 *
 * Le PDF est construit directement, sans capture d'écran du DOM :
 *  - photos JPEG/PNG intégrées telles quelles (résolution d'origine) ;
 *  - textes et code-barres vectoriels ;
 *  - fond et logo intégrés une seule fois puis réutilisés.
 *
 * Dépendances (à charger avant ce fichier) :
 *   https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js
 *   https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js
 *
 * Chemins relatifs à une page située dans src/.
 */
(function (global) {
	'use strict';

	const { PDFDocument, StandardFonts, rgb, pushGraphicsState, popGraphicsState, moveTo, lineTo,
		appendBezierCurve, closePath, clip, endPath, fill, setFillingRgbColor } = PDFLib;

	const ASSETS = {
		background: '../file/fond-badge.jpg',
		logo: '../file/' + encodeURIComponent('UAZ Official.png'),
		photoDir: '../app/photosetudiants/'
	};

	const MM = 72 / 25.4;
	const A4 = { w: 595.28, h: 841.89 };
	// Dimensions relevées sur les maquettes A4
	const LAYOUTS = {
		'6': { cols: 2, rows: 3, w: 75 * MM, h: 84 * MM, gx: 18 * MM, gy: 6 * MM, name: '6-par-page', label: '6 par page — 75 × 84 mm' },
		'9': { cols: 3, rows: 3, w: 54 * MM, h: 85.6 * MM, gx: 9 * MM, gy: 5 * MM, name: '9-par-page', label: '9 par page — 54 × 85,6 mm' }
	};
	const hex = (h) => rgb(parseInt(h.slice(1, 3), 16) / 255, parseInt(h.slice(3, 5), 16) / 255, parseInt(h.slice(5, 7), 16) / 255);
	const COLORS = { navy: hex('#07325a'), blue: hex('#0054a5'), sky: hex('#31bef3'), white: rgb(1, 1, 1), black: rgb(0, 0, 0), cut: rgb(0.45, 0.45, 0.45) };

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
		const res = await fetch(url);
		if (!res.ok) throw new Error('HTTP ' + res.status);
		const bytes = new Uint8Array(await res.arrayBuffer());
		const isJpg = bytes[0] === 0xFF && bytes[1] === 0xD8;
		const isPng = bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4E && bytes[3] === 0x47;
		try {
			// Intégration directe des octets d'origine : aucune perte de qualité
			if (isJpg && jpegOrientation(bytes) <= 1) return await pdf.embedJpg(bytes);
			if (isPng) return await pdf.embedPng(bytes);
		} catch (e) { /* format exotique : on passe par le décodage navigateur */ }
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
	// Texte centré, taille réduite automatiquement pour tenir dans maxW
	function centerText(page, font, text, cx, baseline, maxSize, maxW, color) {
		text = safeText(font, text); if (!text) return;
		const w1 = font.widthOfTextAtSize(text, 1);
		const size = Math.min(maxSize, maxW / w1);
		page.drawText(text, { x: cx - (w1 * size) / 2, y: baseline, size, font, color });
	}

	function drawBarcode(page, font, code, x, y, w, h) {
		fillRoundRect(page, x, y, w, h, h * 0.08, COLORS.white);
		code = String(code ?? '').trim(); if (!code) return;
		const enc = {};
		try { JsBarcode(enc, code, { format: 'CODE128' }); } catch { return; }
		const bits = (enc.encodings || []).map((e) => e.data).join('');
		if (!bits) return;
		const padX = w * 0.06, textH = h * 0.2, barsH = h - textH - h * 0.14;
		const mw = (w - 2 * padX) / bits.length, by = y + textH + h * 0.04;
		for (let i = 0; i < bits.length;) {
			if (bits[i] !== '1') { i++; continue; }
			let j = i; while (bits[j] === '1') j++;
			page.drawRectangle({ x: x + padX + i * mw, y: by, width: (j - i) * mw, height: barsH, color: COLORS.black });
			i = j;
		}
		centerText(page, font, code, x + w / 2, y + h * 0.05, textH * 0.85, w - 2 * padX, COLORS.black);
	}

	/* ---------- un badge ---------- */
	function drawBadge(page, f, assets, s, x, y, w, h, photo, withBorder) {
		const T = (t) => y + h - t * h; // position mesurée depuis le haut (fraction de h)
		const cx = x + w / 2;

		// Fond
		if (assets.bg) drawCover(page, assets.bg, x, y, w, h);
		else { page.drawRectangle({ x, y, width: w, height: h, color: COLORS.white }); page.drawRectangle({ x, y: T(0.16), width: w, height: 0.16 * h, color: COLORS.blue }); }

		// En-tête : logo + établissement
		const pad = w * 0.045;
		let tx0 = x + pad;
		if (assets.logo) {
			const lh = Math.min(0.12 * h, 0.22 * w), lw = lh * assets.logo.width / assets.logo.height;
			page.drawImage(assets.logo, { x: x + pad, y: T(0.025) - lh, width: lw, height: lh });
			tx0 = x + pad + lw + pad * 0.5;
		}
		const tw = x + w - pad - tx0, tcx = tx0 + tw / 2;
		centerText(page, f.bold, 'Université Adventiste Zurcher', tcx, T(0.068), 0.042 * h, tw, COLORS.white);
		centerText(page, f.bold, 'BP.325 Antsirabe (110)', tcx, T(0.103), 0.024 * h, tw, COLORS.white);
		centerText(page, f.bold, 'Tel : 034 08 722 95 / 034 08 722 95', tcx, T(0.133), 0.024 * h, tw, COLORS.white);

		// Photo
		const ph = 0.33 * h, pw = Math.min(ph * 0.8, 0.62 * w), px = cx - pw / 2, py = T(0.175) - ph;
		if (photo) {
			const b = 0.008 * h, r = pw * 0.1;
			fillRoundRect(page, px - b, py - b, pw + 2 * b, ph + 2 * b, r + b, COLORS.white);
			drawCover(page, photo, px, py, pw, ph, r);
		} else {
			page.drawRectangle({ x: px, y: py, width: pw, height: ph, color: COLORS.white, borderColor: COLORS.black, borderWidth: 0.006 * h });
		}

		// Identité
		const tmax = 0.9 * w;
		centerText(page, f.bold, String(s.student_nom ?? '').toUpperCase(), cx, T(0.565), 0.05 * h, tmax, COLORS.navy);
		centerText(page, f.bold, s.student_prenom, cx, T(0.612), 0.042 * h, tmax, COLORS.navy);
		centerText(page, f.bold, s.status, cx, T(0.652), 0.027 * h, tmax, COLORS.black);
		centerText(page, f.bold, s.student_id, cx, T(0.722), 0.066 * h, tmax, COLORS.navy);

		// Mention
		const mw = 0.82 * w, mh = 0.045 * h;
		fillRoundRect(page, cx - mw / 2, T(0.738) - mh, mw, mh, mh * 0.3, COLORS.blue);
		centerText(page, f.bold, s.etude_envisage, cx, T(0.738) - mh * 0.72, 0.026 * h, mw * 0.92, COLORS.white);

		// Bande basse + code-barres
		page.drawRectangle({ x, y, width: w, height: 0.195 * h, color: s.status === 'Interne' ? COLORS.blue : COLORS.sky });
		const bw = 0.8 * w, bh = 0.15 * h;
		drawBarcode(page, f.regular, s.lookup_code, cx - bw / 2, y + 0.022 * h, bw, bh);

		if (withBorder) page.drawRectangle({ x, y, width: w, height: h, borderColor: COLORS.cut, borderWidth: 0.4 });
	}

	async function mapLimit(items, limit, fn) {
		const out = new Array(items.length); let next = 0;
		await Promise.all(Array.from({ length: Math.min(limit, items.length) }, async () => {
			while (next < items.length) { const i = next++; out[i] = await fn(items[i], i); }
		}));
		return out;
	}

	/**
	 * Génère le PDF.
	 * @param {Array}  list  étudiants {student_id, student_nom, student_prenom, etude_envisage, status, lookup_code, image_student}
	 * @param {Object} opts  { layout: '6'|'9', border: bool, onProgress(done, total, message) }
	 * @returns {Promise<{bytes: Uint8Array, warnings: string[], pages: number, layout: Object}>}
	 */
	async function generate(list, opts = {}) {
		const L = LAYOUTS[opts.layout] || LAYOUTS['6'];
		const progress = opts.onProgress || (() => {});
		const warnings = [];

		const pdf = await PDFDocument.create();
		pdf.setTitle('Badges étudiants'); pdf.setCreator('Registrar App');
		const f = { bold: await pdf.embedFont(StandardFonts.HelveticaBold), regular: await pdf.embedFont(StandardFonts.Helvetica) };

		progress(0, list.length, 'Chargement du fond et du logo…');
		const assets = {};
		try { assets.bg = await embedImage(pdf, ASSETS.background); } catch { warnings.push('Image de fond introuvable (' + ASSETS.background + ')'); }
		try { assets.logo = await embedImage(pdf, ASSETS.logo); } catch { warnings.push('Logo introuvable'); }

		// Photos : téléchargées en parallèle (4 à la fois), intégrées en résolution d'origine
		let loaded = 0;
		const photoCache = new Map(); // une même photo n'est intégrée qu'une fois
		const photos = await mapLimit(list, 4, async (s) => {
			let img = null;
			if (hasPhoto(s)) {
				const url = ASSETS.photoDir + encodeURIComponent(s.image_student);
				if (!photoCache.has(url)) photoCache.set(url, embedImage(pdf, url));
				try { img = await photoCache.get(url); }
				catch (e) { warnings.push(`${s.student_id} : photo illisible (${s.image_student})`); }
			} else warnings.push(`${s.student_id} : aucune photo`);
			progress(++loaded, list.length * 2, `Photos ${loaded}/${list.length}`);
			return img;
		});

		const perPage = L.cols * L.rows;
		const ox = (A4.w - (L.cols * L.w + (L.cols - 1) * L.gx)) / 2;
		const oy = (A4.h - (L.rows * L.h + (L.rows - 1) * L.gy)) / 2;
		let page;
		list.forEach((s, i) => {
			const slot = i % perPage;
			if (slot === 0) page = pdf.addPage([A4.w, A4.h]);
			const col = slot % L.cols, row = Math.floor(slot / L.cols);
			const x = ox + col * (L.w + L.gx), y = A4.h - oy - (row + 1) * L.h - row * L.gy;
			drawBadge(page, f, assets, s, x, y, L.w, L.h, photos[i], opts.border !== false);
			progress(list.length + i + 1, list.length * 2, `Badges ${i + 1}/${list.length}`);
		});

		progress(1, 1, 'Enregistrement du PDF…');
		const bytes = await pdf.save();
		return { bytes, warnings, pages: Math.ceil(list.length / perPage), layout: L };
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
