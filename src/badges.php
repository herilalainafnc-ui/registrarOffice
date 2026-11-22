<?php
require('../data/session.php');
require('../data/backdb.php');

/*
 * EXPORT DE BADGES EN MASSE
 * -------------------------
 * Moteur partagé avec le badge individuel : js/badge-pdf.js.
 * Le PDF est construit directement avec pdf-lib (vectoriel) au lieu de
 * html2pdf.js / html2canvas (capture d'écran du DOM) :
 *  - les photos JPEG/PNG sont intégrées telles quelles, sans recompression
 *    ni redimensionnement : la résolution d'origine est conservée ;
 *  - les textes et le code-barres sont vectoriels (nets à toute échelle) ;
 *  - l'image de fond et le logo ne sont intégrés qu'une seule fois puis
 *    réutilisés sur chaque badge (fichier léger, pas de limite de canvas).
 */

$req = $dtb->query('SELECT id, student_id, student_nom, student_prenom, etude_envisage, etude_option,
                           annee_etude, annee_scolaire, image_student, lookup_code, status
                    FROM etudiant_second_semester_23
                    ORDER BY student_nom, student_prenom');
$students = $req->fetchAll(PDO::FETCH_ASSOC);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php require('../init/head.php'); ?>
	<title>Badges en masse</title>
	<style>
		#stdTable th, #stdTable td { padding: 3px 8px; white-space: nowrap; }
		#stdTable tbody tr:hover { background: #cbd5e1; color: #1e293b; }
		.layout-card { cursor: pointer; }
		.layout-card input:checked + div { outline: 3px solid #0891b2; background: #e0f2fe; }
		.mini-page { background: white; border: 1px solid #94a3b8; position: relative; width: 70px; height: 99px; margin: auto; }
		.mini-page span { position: absolute; background: #d9d9d9; border: 1px solid #6b7280; }
	</style>
</head>
<body class="bg-slate-600 text-sm">
<div class="h-screen flex flex-col">

	<div class="bg-slate-400 flex items-center h-10 px-2 gap-3 shrink-0">
		<a href="./accueil.php" class="px-2 py-1 rounded-md border border-black"><span class="bi-house-door-fill"></span> Accueil</a>
		<b class="flex-1 text-center">Badges étudiants — export en masse</b>
	</div>

	<div class="flex flex-1 min-h-0">

		<!-- ============ LISTE DES ÉTUDIANTS ============ -->
		<div class="flex-1 flex flex-col min-w-0 bg-slate-800 text-slate-100 m-1 p-2">
			<div class="flex flex-wrap gap-2 mb-2 text-black">
				<input id="fText" type="search" placeholder="Rechercher (ID, nom, prénom)" class="rounded px-2 py-1 flex-1 min-w-[180px]">
				<select id="fMention" class="rounded px-2 py-1"><option value="">Toutes les mentions</option></select>
				<select id="fNiveau" class="rounded px-2 py-1"><option value="">Tous les niveaux</option></select>
				<select id="fStatus" class="rounded px-2 py-1"><option value="">Tous les statuts</option></select>
				<select id="fAnnee" class="rounded px-2 py-1"><option value="">Toutes les années</option></select>
			</div>
			<div class="flex items-center gap-3 mb-2">
				<button id="selAll" class="px-2 py-1 rounded bg-slate-600 hover:bg-slate-500">Tout cocher (filtre)</button>
				<button id="selNone" class="px-2 py-1 rounded bg-slate-600 hover:bg-slate-500">Tout décocher</button>
				<span id="countInfo" class="text-slate-300"></span>
			</div>
			<div class="overflow-auto flex-1">
				<table id="stdTable" class="w-full">
					<thead class="bg-slate-500 text-white sticky top-0">
						<tr><th></th><th>ID</th><th>Nom</th><th>Prénom</th><th>Mention</th><th>Niveau</th><th>Statut</th><th>Photo</th></tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>

		<!-- ============ OPTIONS D'EXPORT ============ -->
		<div class="w-[300px] shrink-0 bg-slate-200 m-1 p-3 flex flex-col gap-3 overflow-auto">
			<b>Format d'impression (A4)</b>

			<label class="layout-card">
				<input type="radio" name="layout" value="6" class="hidden" checked>
				<div class="flex gap-3 items-center p-2 rounded bg-white">
					<div class="mini-page">
						<span style="left:7px;top:5px;width:25px;height:28px"></span><span style="left:38px;top:5px;width:25px;height:28px"></span>
						<span style="left:7px;top:35px;width:25px;height:28px"></span><span style="left:38px;top:35px;width:25px;height:28px"></span>
						<span style="left:7px;top:65px;width:25px;height:28px"></span><span style="left:38px;top:65px;width:25px;height:28px"></span>
					</div>
					<div><b>6 par page</b><br>2 × 3 badges<br><span class="text-slate-500">75 × 84 mm</span></div>
				</div>
			</label>

			<label class="layout-card">
				<input type="radio" name="layout" value="9" class="hidden">
				<div class="flex gap-3 items-center p-2 rounded bg-white">
					<div class="mini-page">
						<span style="left:5px;top:5px;width:18px;height:28px"></span><span style="left:26px;top:5px;width:18px;height:28px"></span><span style="left:47px;top:5px;width:18px;height:28px"></span>
						<span style="left:5px;top:35px;width:18px;height:28px"></span><span style="left:26px;top:35px;width:18px;height:28px"></span><span style="left:47px;top:35px;width:18px;height:28px"></span>
						<span style="left:5px;top:65px;width:18px;height:28px"></span><span style="left:26px;top:65px;width:18px;height:28px"></span><span style="left:47px;top:65px;width:18px;height:28px"></span>
					</div>
					<div><b>9 par page</b><br>3 × 3 badges<br><span class="text-slate-500">54 × 85,6 mm (carte CR80)</span></div>
				</div>
			</label>

			<label class="flex items-center gap-2"><input type="checkbox" id="optBorder" checked> Contour de découpe</label>

			<button id="btnExport" class="px-3 py-2 rounded-md text-white bg-red-800 hover:bg-red-700 disabled:opacity-50">
				<span class="bi-filetype-pdf"></span> Générer le PDF
			</button>

			<div id="progressWrap" class="hidden">
				<div class="w-full bg-slate-300 rounded h-3 overflow-hidden"><div id="progressBar" class="bg-cyan-700 h-3" style="width:0%"></div></div>
				<div id="progressText" class="text-xs mt-1"></div>
			</div>
			<div id="resultBox" class="text-xs"></div>
		</div>
	</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="./js/badge-pdf.js"></script>
<script>
const STUDENTS = <?= json_encode($students, $jsonFlags) ?>;

/* =====================================================================
 *  LISTE / SÉLECTION
 * ===================================================================== */
const selected = new Set();
const $ = (id) => document.getElementById(id);
const niveauLabel = (n) => (+n <= 3 ? 'Licence ' + n : 'Master ' + (n - 3));
const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function fillSelect(id, values, label = (v) => v) {
	const sel = $(id);
	[...new Set(values.filter((v) => v !== null && v !== ''))].sort().forEach((v) => sel.add(new Option(label(v), v)));
}
fillSelect('fMention', STUDENTS.map((s) => s.etude_envisage));
fillSelect('fNiveau', STUDENTS.map((s) => s.annee_etude), niveauLabel);
fillSelect('fStatus', STUDENTS.map((s) => s.status));
fillSelect('fAnnee', STUDENTS.map((s) => s.annee_scolaire));

// Pré-sélection via ?ids=1,2,3
new URLSearchParams(location.search).get('ids')?.split(',').forEach((i) => i && selected.add(String(i)));

function filtered() {
	const q = $('fText').value.trim().toLowerCase();
	const m = $('fMention').value, n = $('fNiveau').value, st = $('fStatus').value, a = $('fAnnee').value;
	return STUDENTS.filter((s) =>
		(!m || s.etude_envisage === m) && (!n || String(s.annee_etude) === n) &&
		(!st || s.status === st) && (!a || s.annee_scolaire === a) &&
		(!q || `${s.student_id} ${s.student_nom} ${s.student_prenom}`.toLowerCase().includes(q)));
}

function render() {
	const rows = filtered();
	$('stdTable').tBodies[0].innerHTML = rows.map((s) => `
		<tr>
			<td><input type="checkbox" data-id="${esc(s.id)}" ${selected.has(String(s.id)) ? 'checked' : ''}></td>
			<td>${esc(s.student_id)}</td><td>${esc(String(s.student_nom).toUpperCase())}</td><td>${esc(s.student_prenom)}</td>
			<td>${esc(s.etude_envisage)}</td><td>${esc(niveauLabel(s.annee_etude))}</td><td>${esc(s.status)}</td>
			<td>${BadgePDF.hasPhoto(s) ? '<i class="bi-image text-green-400"></i>' : '<i class="bi-x-circle text-red-400"></i>'}</td>
		</tr>`).join('');
	updateCount(rows.length);
}
function updateCount(shown) {
	$('countInfo').textContent = `${shown ?? filtered().length} affiché(s) — ${selected.size} sélectionné(s)`;
}

$('stdTable').addEventListener('change', (e) => {
	const id = e.target.dataset.id; if (!id) return;
	e.target.checked ? selected.add(id) : selected.delete(id);
	updateCount();
});
$('selAll').onclick = () => { filtered().forEach((s) => selected.add(String(s.id))); render(); };
$('selNone').onclick = () => { selected.clear(); render(); };
['fText', 'fMention', 'fNiveau', 'fStatus', 'fAnnee'].forEach((id) => $(id).addEventListener('input', render));
render();

/* =====================================================================
 *  GÉNÉRATION PDF (moteur partagé : js/badge-pdf.js)
 * ===================================================================== */
function setProgress(done, total, msg) {
	$('progressBar').style.width = (total ? (100 * done / total) : 0) + '%';
	$('progressText').textContent = msg;
}

$('btnExport').onclick = async () => {
	const list = STUDENTS.filter((s) => selected.has(String(s.id)));
	if (!list.length) { alert('Sélectionnez au moins un étudiant.'); return; }
	const btn = $('btnExport'); btn.disabled = true;
	$('progressWrap').classList.remove('hidden'); $('resultBox').innerHTML = '';

	try {
		const r = await BadgePDF.generate(list, {
			layout: document.querySelector('input[name=layout]:checked').value,
			border: $('optBorder').checked,
			onProgress: setProgress
		});
		const name = `BADGES-ETUDIANTS_${r.layout.name}_${list.length}_${BadgePDF.stamp()}.pdf`;
		const url = BadgePDF.download(r.bytes, name);

		setProgress(1, 1, `Terminé : ${list.length} badge(s), ${r.pages} page(s), ${(r.bytes.length / 1048576).toFixed(1)} Mo`);
		$('resultBox').innerHTML = `<a href="${url}" target="_blank" class="text-cyan-800 underline">Ouvrir l'aperçu</a> · <a href="${url}" download="${esc(name)}" class="text-cyan-800 underline">Télécharger à nouveau</a>` +
			(r.warnings.length ? `<div class="mt-2 text-amber-800"><b>${r.warnings.length} avertissement(s) :</b><ul class="list-disc ml-4">${r.warnings.map((w) => `<li>${esc(w)}</li>`).join('')}</ul></div>` : '');
	} catch (e) {
		console.error(e);
		$('resultBox').innerHTML = `<div class="text-red-700"><b>Erreur :</b> ${esc(e.message || e)}</div>`;
	} finally {
		btn.disabled = false;
	}
};
</script>
</body>
</html>
