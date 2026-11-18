<?php
/**
 * Validation des remises de notes — registrariat et superadmin.
 *
 * Deux vues dans un seul fichier, selon la presence de remise_id :
 *   - sans : la file des feuilles transmises, la plus ancienne en tete ;
 *   - avec : le detail d'une feuille, pour en verifier la coherence, puis
 *            la valider ou la renvoyer a l'enseignant.
 *
 * La validation lancee ici est le seul chemin par lequel une note d'enseignant
 * entre dans t_2023_notes, donc dans l'historique et le bulletin.
 */
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Validation des remises de notes</title>
<?php
require_once('../data/middleware.php');
initMiddleware($dtb);
require_once('../data/remise.notes.php');

if (!remisePeutValider()) {
	header('Location: ' . $app_base . '/etudiants');
	exit;
}

$remiseId = isset($_GET['remise_id']) ? (int)$_GET['remise_id'] : 0;
$filtre   = $_GET['filtre'] ?? 'soumise';
?>
	<style>
		.remise-carte { background:rgb(var(--c-surface-2)); border:1px solid rgb(var(--c-line)); border-radius:12px; }
		[data-theme="light"] .remise-carte { background:rgb(var(--c-surface)); border-color:rgb(var(--c-line-strong)); }
		.remise-entete {
			background: linear-gradient(135deg, rgb(var(--c-accent) / 0.16), rgb(var(--c-accent) / 0.04));
			border-bottom: 1px solid rgb(var(--c-line));
		}
		[data-theme="light"] .remise-entete { border-bottom-color:rgb(var(--c-line-strong)); }

		.remise-etiquette {
			display:inline-flex; align-items:center; gap:6px;
			padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; white-space:nowrap;
		}

		.grille { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
		.grille thead th {
			position:sticky; top:0; z-index:3; background:rgb(var(--c-surface-2)); color:rgb(var(--c-text));
			padding:8px 6px; font-size:11px; font-weight:600;
			border-bottom:2px solid rgb(var(--c-line)); text-align:center; line-height:1.25;
		}
		[data-theme="light"] .grille thead th { background:rgb(var(--c-surface-2)); color:rgb(var(--c-text)); border-bottom-color:rgb(var(--c-line-strong)); }
		.grille tbody td {
			padding:5px 6px; border-bottom:1px solid rgb(var(--c-line) / .55);
			color:rgb(var(--c-text)); vertical-align:middle; font-variant-numeric:tabular-nums;
		}
		[data-theme="light"] .grille tbody td { color:rgb(var(--c-text)); border-bottom-color:rgb(var(--c-line)); }
		.grille tbody tr:hover td { background:rgb(var(--c-accent) / .10); }

		.note-s{color:#4ade80} .note-e{color:#f87171} .note-nn{color:rgb(var(--c-text-3))} .note-ko{color:#fbbf24}

		.bouton {
			display:inline-flex; align-items:center; gap:8px; padding:9px 18px;
			border-radius:8px; font-size:13px; font-weight:600; border:none; cursor:pointer;
			transition:filter .15s ease;
		}
		.bouton:hover:not(:disabled){filter:brightness(1.12)}
		.bouton:disabled{opacity:.5;cursor:not-allowed}
		.bouton-valider  { background:#16a34a; color:#fff; }
		.bouton-rejeter  { background:#b91c1c; color:#fff; }
		.bouton-secondaire{ background:rgb(var(--c-raised)); color:rgb(var(--c-text)); }
		[data-theme="light"] .bouton-secondaire { background:rgb(var(--c-raised)); color:rgb(var(--c-text)); }

		.onglet {
			padding:7px 16px; border-radius:8px; font-size:13px; font-weight:600;
			color:rgb(var(--c-text-2)); text-decoration:none; display:inline-block;
		}
		.onglet:hover { background:rgb(var(--c-accent) / .12); color:rgb(var(--c-text)); }
		.onglet.actif { background:rgb(var(--c-accent) / .2); color:rgb(var(--c-accent-text)); }

		.journal-ligne {
			display:grid; grid-template-columns:132px 1fr; gap:10px;
			padding:8px 12px; border-bottom:1px solid rgb(var(--c-line) / .5); font-size:12px;
		}
		[data-theme="light"] .journal-ligne { border-bottom-color:rgb(var(--c-line)); }
		.journal-heure { color:rgb(var(--c-text-3)); font-variant-numeric:tabular-nums; }

		.bandeau { padding:10px 14px; border-radius:8px; font-size:13px; display:flex; align-items:flex-start; gap:10px; line-height:1.5; }
		.bandeau-info   { background:rgb(var(--c-accent) / .14);  border:1px solid rgb(var(--c-accent) / .4);  color:rgb(var(--c-text-2)); }
		.bandeau-alerte { background:rgba(245,158,11,.14); border:1px solid rgba(245,158,11,.4); color:#fcd34d; }
		.bandeau-erreur { background:rgba(239,68,68,.14);  border:1px solid rgba(239,68,68,.4);  color:#fca5a5; }
		.bandeau-succes { background:rgba(34,197,94,.14);  border:1px solid rgba(34,197,94,.4);  color:#86efac; }
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="min-h-screen w-full <?=$bg_three_color?>">

	<!-- TOP BAR --><?php require('../init/topbar.php');?>

	<div class="w-full flex flex-col lg:flex-row">

		<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

		<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<?php /* Filtres, examen d'une remise et retour : sans recharger (src/js/zone-ajax.js). */ ?>
			<div class="w-full px-4 py-4 flex-1 overflow-auto" id="zoneRemises" data-zone-ajax>

<?php
/* =========================================================================
   VUE 1 — FILE DES REMISES
   ====================================================================== */
if (!$remiseId):

	$conditions = ['1=1'];
	$params     = [];
	if (in_array($filtre, ['soumise', 'validee', 'rejetee', 'brouillon'], true)) {
		$conditions[] = 'r.statut = :statut';
		$params['statut'] = $filtre;
	}

	$stmt = $dtb->prepare('SELECT r.*,
			t.name AS prof_prenom, t.lastName AS prof_nom,
			c.nb_crd, c.yearlevel,
			(SELECT COUNT(*) FROM t_remise_notes_ligne l WHERE l.remise_id = r.id) AS nb_lignes
		FROM t_remise_notes r
		LEFT JOIN teacher t ON t.uid = r.teacher_uid
		LEFT JOIN t_2023_cours c ON c.id = r.cours_id
		WHERE ' . implode(' AND ', $conditions) . '
		ORDER BY r.soumis_le ASC, r.modifie_le DESC');
	$stmt->execute($params);
	$remises = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$compteurs = $dtb->query('SELECT statut, COUNT(*) n FROM t_remise_notes GROUP BY statut')
	                 ->fetchAll(PDO::FETCH_KEY_PAIR);
?>
	<div class="mb-4" data-tuto="remises-entete">
		<h1 class="text-2xl font-bold text-white mb-1">
			<i class="bi bi-clipboard2-check mr-2"></i>Validation des remises de notes
		</h1>
		<p class="text-slate-400">
			Controlez la coherence des feuilles transmises par les enseignants, puis validez-les.
			La validation reporte les notes dans l'historique et le bulletin des etudiants.
		</p>
	</div>

	<div class="flex flex-wrap gap-1 mb-4" data-tuto="remises-onglets">
<?php foreach ([
		'soumise'   => 'En attente',
		'rejetee'   => 'Renvoyees',
		'validee'   => 'Validees',
		'brouillon' => 'Brouillons',
		'toutes'    => 'Toutes',
	] as $cle => $libelle): ?>
		<a href="?filtre=<?= $cle ?>" class="onglet <?= $filtre === $cle ? 'actif' : '' ?>">
			<?= $libelle ?>
			<?php if ($cle !== 'toutes'): ?>
				<span class="ml-1 opacity-70">(<?= (int)($compteurs[$cle] ?? 0) ?>)</span>
			<?php endif; ?>
		</a>
<?php endforeach; ?>
	</div>

<?php if (!$remises): ?>
	<div class="remise-carte p-10 text-center text-slate-400">
		<i class="bi bi-inbox text-3xl block mb-3"></i>
		Aucune remise dans cette categorie.
	</div>
<?php else: ?>
	<div class="remise-carte overflow-hidden" data-tuto="remises-liste">
		<table class="grille">
			<thead>
				<tr>
					<th style="text-align:left">Cours</th>
					<th style="text-align:left">Enseignant</th>
					<th>Annee</th>
					<th>Semestre</th>
					<th>Etudiants</th>
					<th>Transmise le</th>
					<th>Etat</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php foreach ($remises as $r):
		$info = remiseStatutInfo($r['statut']);
		$prof = trim(($r['prof_prenom'] ?? '') . ' ' . ($r['prof_nom'] ?? ''));
?>
				<tr>
					<td>
						<b><?= htmlspecialchars($r['sigle'] ?? '') ?></b>
						<div class="text-xs text-slate-400"><?= htmlspecialchars($r['titre_cours'] ?? '') ?></div>
					</td>
					<td><?= $prof !== '' ? htmlspecialchars($prof) : '<em class="text-slate-500">non renseigne</em>' ?></td>
					<td class="text-center text-slate-400"><?= htmlspecialchars($r['annee_scolaire']) ?></td>
					<td class="text-center text-slate-400"><?= htmlspecialchars(remiseLibelleSemestre($r['semestre'])) ?></td>
					<td class="text-center"><?= (int)$r['nb_lignes'] ?></td>
					<td class="text-center text-slate-400 text-xs">
						<?= !empty($r['soumis_le']) ? date('d/m/Y H:i', strtotime($r['soumis_le'])) : '—' ?>
					</td>
					<td class="text-center">
						<span class="remise-etiquette"
						      style="background:<?= $info['couleur'] ?>22;color:<?= $info['couleur'] ?>;border:1px solid <?= $info['couleur'] ?>55">
							<i class="<?= $info['icone'] ?>"></i><?= $info['libelle'] ?>
						</span>
					</td>
					<td class="text-center">
						<a href="?remise_id=<?= (int)$r['id'] ?>&filtre=<?= htmlspecialchars($filtre) ?>"
						   class="bouton bouton-secondaire" style="padding:6px 14px;font-size:12px">
							<i class="bi bi-search"></i>Examiner
						</a>
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<?php
/* =========================================================================
   VUE 2 — EXAMEN D'UNE REMISE
   ====================================================================== */
else:

	$remise = remiseCharger($dtb, $remiseId);
	if (!$remise) {
		echo '<div class="bandeau bandeau-erreur"><i class="bi bi-x-octagon"></i><div>Remise introuvable.</div></div>';
		echo '</div></div></div></div></body></html>';
		exit;
	}

	$lignes      = remiseLignes($dtb, $remiseId);
	/* Assiduite (§476) : un etudiant au-dela de 15 % d'absences doit quitter le cours. */
	require_once(__DIR__ . '/../data/presence.php');
	$assiduite   = presenceEtatsCours($dtb, (int)$remise['cours_id'], $remise['annee_scolaire'], $remise['semestre'] === null ? null : (int)$remise['semestre']);
	$controle    = remiseControler($dtb, $remise, $lignes);
	$journal     = remiseJournal($dtb, $remiseId, 200);
	$composantes = remiseComposantes();
	$info        = remiseStatutInfo($remise['statut']);

	$stmt = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id = :id LIMIT 1');
	$stmt->execute(['id' => $remise['cours_id']]);
	$cours = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

	$prof = null;
	if (!empty($remise['teacher_uid'])) {
		$stmt = $dtb->prepare('SELECT name, lastName, email FROM teacher WHERE uid = :uid LIMIT 1');
		$stmt->execute(['uid' => $remise['teacher_uid']]);
		$prof = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	$aTraiter = $remise['statut'] === 'soumise';
?>
	<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
		<div class="flex items-center gap-3">
			<a href="?filtre=<?= htmlspecialchars($filtre) ?>" class="bouton bouton-secondaire" style="padding:7px 12px">
				<i class="bi bi-arrow-left"></i>La file
			</a>
			<h1 class="text-xl font-bold text-white">
				<?= htmlspecialchars($remise['sigle'] ?? '') ?> — <?= htmlspecialchars($remise['titre_cours'] ?? '') ?>
			</h1>
		</div>
		<span class="remise-etiquette" style="font-size:12px;padding:6px 14px;background:<?= $info['couleur'] ?>22;color:<?= $info['couleur'] ?>;border:1px solid <?= $info['couleur'] ?>55">
			<i class="<?= $info['icone'] ?>"></i><?= $info['libelle'] ?>
		</span>
	</div>

	<div class="remise-carte mb-4" data-tuto="remise-feuille">
		<div class="remise-entete px-4 py-2 text-white font-semibold">
			<i class="bi bi-file-earmark-text mr-1"></i>Feuille transmise
		</div>
		<div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2 p-4 text-sm">
			<div><span class="text-slate-400">Enseignant</span><br>
				<b class="text-white"><?= $prof ? htmlspecialchars($prof['name'] . ' ' . $prof['lastName']) : '-' ?></b></div>
			<div><span class="text-slate-400">Annee</span><br><b class="text-white"><?= htmlspecialchars($remise['annee_scolaire']) ?></b></div>
			<div><span class="text-slate-400">Semestre</span><br><b class="text-white"><?= htmlspecialchars(remiseLibelleSemestre($remise['semestre'])) ?></b></div>
			<div><span class="text-slate-400">Niveau / credit</span><br>
				<b class="text-white"><?= remiseLibelleNiveau($cours['yearlevel'] ?? 0) ?> — <?= (int)($cours['nb_crd'] ?? 0) ?> cr.</b></div>
			<div><span class="text-slate-400">Transmise le</span><br>
				<b class="text-white"><?= !empty($remise['soumis_le']) ? date('d/m/Y a H:i', strtotime($remise['soumis_le'])) : '—' ?></b></div>
			<div><span class="text-slate-400">Derniere modification</span><br>
				<b class="text-white"><?= !empty($remise['modifie_le']) ? date('d/m/Y a H:i', strtotime($remise['modifie_le'])) : '—' ?></b></div>
			<div><span class="text-slate-400">Etudiants</span><br><b class="text-white"><?= count($lignes) ?></b></div>
			<div><span class="text-slate-400">Notes pretes</span><br><b class="text-white"><?= $controle['pretes'] ?> / <?= $controle['total'] ?></b></div>
		</div>
		<div class="px-4 pb-4">
			<div class="text-slate-400 text-xs mb-1">Ponderation appliquee par l'enseignant</div>
			<div class="flex flex-wrap gap-2">
<?php foreach ($composantes as $meta):
		$p = (float)$remise[$meta['poids']];
?>
				<span class="remise-etiquette" style="background:rgb(var(--c-accent) / .12);color:<?= $p > 0 ? 'rgb(var(--c-text-2))' : 'rgb(var(--c-text-3))' ?>">
					<?= $meta['libelle'] ?> : <?= rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') ?> %
				</span>
<?php endforeach; ?>
<?php foreach ($remise['extras'] ?? [] as $x):
		$p = (float)$x['poids'];
?>
					<span class="remise-etiquette" style="background:rgb(var(--c-accent) / .12);color:<?= $p > 0 ? 'rgb(var(--c-text-2))' : 'rgb(var(--c-text-3))' ?>">
						<?= htmlspecialchars($x['libelle']) ?> : <?= rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') ?> %
					</span>
<?php endforeach; ?>
				<?php $total = remiseTotalPoids($remise); $ok = abs($total - 100) < 0.01; ?>
				<span class="remise-etiquette" style="background:<?= $ok ? 'rgba(34,197,94,.15)' : 'rgba(239,68,68,.15)' ?>;color:<?= $ok ? '#4ade80' : '#f87171' ?>">
					Total : <?= rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') ?> %
				</span>
			</div>
		</div>
	</div>

	<div class="remise-carte mb-4" data-tuto="remise-coherence">
		<div class="remise-entete px-4 py-2 text-white font-semibold">
			<i class="bi bi-clipboard-check mr-1"></i>Controle de coherence
		</div>
		<div class="p-4"><?php require __DIR__ . '/remise/_controle.php'; ?></div>
	</div>

	<!-- Detail : chaque composante, la note proposee, et celle actuellement au dossier -->
	<div class="remise-carte mb-4" data-tuto="remise-grille">
		<div class="remise-entete px-4 py-2 text-white font-semibold">
			<i class="bi bi-table mr-1"></i>Notes proposees
		</div>
		<div style="max-height:calc(100vh - 420px);overflow:auto">
			<table class="grille">
				<thead>
					<tr>
						<th style="width:34px">No</th>
						<th style="width:80px">Matricule</th>
						<th style="text-align:left;min-width:190px">Noms</th>
<?php foreach ($composantes as $meta):
		$p = (float)$remise[$meta['poids']];
?>
						<th style="width:60px<?= $p > 0 ? '' : ';opacity:.4' ?>">
							<?= $meta['court'] ?><br>
							<span class="font-normal" style="color:rgb(var(--c-text-2))"><?= rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') ?>%</span>
						</th>
<?php endforeach; ?>
<?php foreach ($remise['extras'] ?? [] as $x):
		$p = (float)$x['poids'];
?>
						<th style="width:70px<?= $p > 0 ? '' : ';opacity:.4' ?>">
							<?= htmlspecialchars($x['libelle']) ?><br>
							<span class="font-normal" style="color:rgb(var(--c-text-2))"><?= rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') ?>%</span>
						</th>
<?php endforeach; ?>
						<th style="width:74px">Note<br>proposee</th>
						<th style="width:74px">Note<br>au dossier</th>
						<th style="width:90px">Effet</th>
					</tr>
				</thead>
				<tbody>
<?php if (!$lignes): ?>
					<tr><td colspan="<?= 6 + count($composantes) + count($remise['extras'] ?? []) ?>" class="text-center text-slate-400 py-6">
						Aucun etudiant dans cette remise.
					</td></tr>
<?php else: $numero = 1; foreach ($lignes as $ligne):
		$calcul   = remiseCalculerNote($ligne, $remise);
		$validCrs = noteEstCoursValidation($ligne);
		$grade    = remiseNoteVersGrade($ligne, $remise, $validCrs);
		$actuelle = noteStatut($ligne['grade_actuel'], $validCrs);
		$nom      = trim(($ligne['student_nom'] ?? '') . ' ' . ($ligne['student_prenom'] ?? ''));

		// Ce que la validation changerait, exprime en clair.
		if ($grade === null) {
			$effet = ['texte' => 'sera « NN »', 'classe' => 'note-nn'];
		} elseif ((string)$ligne['grade_actuel'] === (string)$grade) {
			$effet = ['texte' => 'inchangee', 'classe' => 'note-nn'];
		} elseif ($ligne['grade_actuel'] === null || $ligne['grade_actuel'] === '') {
			$effet = ['texte' => 'nouvelle note', 'classe' => 'note-s'];
		} else {
			$effet = ['texte' => 'remplacee', 'classe' => 'note-ko'];
		}

		$proposee = noteStatut($grade ?? '-4', $validCrs);
?>
					<tr>
						<td class="text-center text-slate-500"><?= $numero++ ?></td>
						<td class="text-center font-mono text-xs"><?= htmlspecialchars($ligne['student_id']) ?></td>
						<td><?= $nom !== '' ? htmlspecialchars($nom) : '<em class="text-red-400">Nom non defini</em>' ?>
							<?php if (($assiduite[$ligne['student_id']] ?? '') === 'depasse'): ?>
								<a href="<?= $app_base ?>/teacher/presence?cours_id=<?= (int)$remise['cours_id'] ?>&annee=<?= rawurlencode($remise['annee_scolaire']) ?>&semestre=<?= rawurlencode((string)$remise['semestre']) ?>#bilan"
								   class="remise-etiquette" style="margin-left:6px;background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(248,113,113,.4);text-decoration:none"
								   title="Au-delà de 15 % d'absences : doit quitter le cours (Bulletin académique, §476)"><i class="bi bi-person-x"></i>Absences &gt; 15 %</a>
							<?php endif; ?>
						</td>
<?php foreach ($composantes as $champ => $meta):
		$p = (float)$remise[$meta['poids']];
?>
						<td class="text-center <?= $p > 0 ? '' : 'opacity-40' ?>">
							<?= $ligne[$champ] === null ? '<span class="text-slate-600">—</span>'
							     : rtrim(rtrim(number_format((float)$ligne[$champ], 2, '.', ''), '0'), '.') ?>
						</td>
<?php endforeach; ?>
<?php foreach ($remise['extras'] ?? [] as $x):
		$v = $ligne['extras'][$x['cle']] ?? null;
?>
						<td class="text-center <?= (float)$x['poids'] > 0 ? '' : 'opacity-40' ?>">
							<?= $v === null ? '<span class="text-slate-600">—</span>' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') ?>
						</td>
<?php endforeach; ?>
						<td class="text-center font-bold <?= 'note-' . strtolower($proposee['code'] === 'S' ? 's' : ($proposee['code'] === 'NN' ? 'nn' : ($proposee['code'] === 'V' ? 's' : 'e'))) ?>"
						    title="<?= htmlspecialchars($calcul['detail']) ?>">
							<?= htmlspecialchars($proposee['libelle']) ?>
						</td>
						<td class="text-center text-slate-400"><?= htmlspecialchars($actuelle['libelle']) ?></td>
						<td class="text-center text-xs <?= $effet['classe'] ?>"><?= $effet['texte'] ?></td>
					</tr>
<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>

<?php if ($aTraiter): ?>
	<div class="remise-carte mb-4" data-tuto="remise-decision">
		<div class="remise-entete px-4 py-2 text-white font-semibold">
			<i class="bi bi-check2-square mr-1"></i>Decision
		</div>
		<div class="p-4">
			<?php if ($controle['bloquants']): ?>
			<div class="bandeau bandeau-erreur mb-3">
				<i class="bi bi-slash-circle"></i>
				<div>La validation est bloquee tant que les incoherences ci-dessus subsistent.
					Renvoyez la feuille a l'enseignant en lui indiquant ce qui doit etre corrige.</div>
			</div>
			<?php endif; ?>

			<div class="flex flex-wrap items-center gap-3">
				<button type="button" id="btnValider" class="bouton bouton-valider"
				        <?= $controle['bloquants'] ? 'disabled' : '' ?>>
					<i class="bi bi-check-circle-fill"></i>Valider et reporter les notes
				</button>
				<button type="button" id="btnRejeter" class="bouton bouton-rejeter">
					<i class="bi bi-arrow-counterclockwise"></i>Renvoyer a l'enseignant
				</button>
			</div>

			<div id="zoneMotif" class="mt-4" style="display:none">
				<label class="block text-slate-400 text-xs mb-1">
					Ce que l'enseignant doit corriger (il verra ce texte sur sa feuille)
				</label>
				<textarea id="motif" rows="3" class="w-full p-3 rounded-lg text-sm"
				          style="background:rgb(var(--c-canvas) / .6);border:1px solid rgb(var(--c-line));color:rgb(var(--c-text))"
				          placeholder="Exemple : les notes d'examen final de trois etudiants depassent le bareme."></textarea>
				<div class="mt-2 flex gap-2">
					<button type="button" id="btnConfirmerRejet" class="bouton bouton-rejeter">
						<i class="bi bi-send"></i>Renvoyer
					</button>
					<button type="button" id="btnAnnulerRejet" class="bouton bouton-secondaire">Annuler</button>
				</div>
			</div>
		</div>
	</div>
<?php elseif ($remise['statut'] === 'validee'): ?>
	<div class="bandeau bandeau-succes mb-4">
		<i class="bi bi-check-circle-fill"></i>
		<div>Remise validee le <?= !empty($remise['traite_le']) ? date('d/m/Y a H:i', strtotime($remise['traite_le'])) : '' ?>.
			Les notes figurent dans l'historique et le bulletin des etudiants.</div>
	</div>
<?php elseif ($remise['statut'] === 'rejetee'): ?>
	<div class="bandeau bandeau-erreur mb-4">
		<i class="bi bi-arrow-counterclockwise"></i>
		<div><b>Renvoyee a l'enseignant</b>
			<?php if (!empty($remise['traite_le'])): ?>le <?= date('d/m/Y a H:i', strtotime($remise['traite_le'])) ?><?php endif; ?> :
			<?= nl2br(htmlspecialchars($remise['motif_rejet'] ?? '')) ?></div>
	</div>
<?php else: ?>
	<div class="bandeau bandeau-info mb-4">
		<i class="bi bi-pencil-square"></i>
		<div>Feuille encore en brouillon chez l'enseignant : elle ne vous a pas ete transmise.</div>
	</div>
<?php endif; ?>

	<!-- Journal complet : qui a pose quelle note, et a quelle heure -->
	<div class="remise-carte">
		<div class="remise-entete px-4 py-2 flex items-center justify-between cursor-pointer" id="basculeJournal">
			<span class="text-white font-semibold">
				<i class="bi bi-clock-history mr-1"></i>Historique des modifications
				<span class="text-slate-400 font-normal">(<?= count($journal) ?>)</span>
			</span>
			<i class="bi bi-chevron-down text-slate-400" id="chevronJournal"></i>
		</div>
		<div id="corpsJournal" style="display:none;max-height:360px;overflow:auto">
<?php if (!$journal): ?>
			<p class="p-5 text-center text-slate-400 text-sm">Aucune modification enregistree.</p>
<?php else: foreach ($journal as $j): ?>
			<div class="journal-ligne">
				<div class="journal-heure"><?= date('d/m/Y H:i:s', strtotime($j['date_action'])) ?></div>
				<div class="text-slate-300">
					<b class="text-white"><?= htmlspecialchars($j['auteur_nom'] ?: 'Compte supprime') ?></b>
					<?php if (!empty($j['auteur_role'])): ?>
						<span class="text-slate-500">(<?= htmlspecialchars($j['auteur_role']) ?>)</span>
					<?php endif; ?>
					<?php
					switch ($j['action']) {
						case 'creation':    echo ' a ouvert la feuille'; break;
						case 'ponderation': echo ' a change la ponderation <b>' . htmlspecialchars((string)$j['champ']) . '</b>'; break;
						case 'soumission':  echo ' a transmis la feuille'; break;
						case 'validation':  echo ' a <b style="color:#4ade80">valide</b> la feuille'; break;
						case 'rejet':       echo ' a <b style="color:#f87171">renvoye</b> la feuille'; break;
						default:
							echo ' a saisi <b>' . htmlspecialchars((string)$j['champ']) . '</b>';
							if (!empty($j['student_id'])) {
								echo ' pour <span class="font-mono text-xs">' . htmlspecialchars($j['student_id']) . '</span>';
							}
					}
					?>
					<?php if ($j['ancienne_valeur'] !== null || $j['nouvelle_valeur'] !== null): ?>
						<span class="ml-1">
							<?php if ($j['ancienne_valeur'] !== null): ?>
								<span style="color:#f87171;text-decoration:line-through"><?= htmlspecialchars($j['ancienne_valeur']) ?></span>
								<span class="text-slate-500 mx-1">→</span>
							<?php endif; ?>
							<span style="color:#4ade80"><?= htmlspecialchars($j['nouvelle_valeur'] ?? 'vide') ?></span>
						</span>
					<?php endif; ?>
					<?php if (!empty($j['ip'])): ?>
						<span class="text-slate-600 text-[10px] ml-2"><?= htmlspecialchars($j['ip']) ?></span>
					<?php endif; ?>
				</div>
			</div>
<?php endforeach; endif; ?>
		</div>
	</div>

	<script>
	(function () {
		const REMISE_ID = <?= (int)$remise['id'] ?>;
		const URL = APP_BASE + '/app/.remise/traiter.php';

		function traiter(action, motif) {
			const corps = new FormData();
			corps.append('csrf_token', CSRF_TOKEN);
			corps.append('remise_id', REMISE_ID);
			corps.append('action', action);
			if (motif) { corps.append('motif', motif); }
			return fetch(URL, {
				method: 'POST', body: corps,
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			}).then(r => r.json());
		}

		/* Le resultat en toast ; la page se recharge ensuite pour montrer le
		   nouvel etat de la remise, le temps de lire le message. */
		function resultat(d) {
			const texte = String((d && d.message) || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
			if (d && d.ok) {
				Toast.success(texte);
				(window.ZoneAjax ? ZoneAjax.recharger() : location.reload());   // l'etat a jour, sans recharger la page
			} else {
				Toast.error(texte || 'Opération refusée.', { duration: 8000 });
			}
		}

		const btnValider = document.getElementById('btnValider');
		if (btnValider) {
			btnValider.addEventListener('click', () => {
				Toast.confirm('Les notes seront reportées dans l\'historique et le bulletin des '
				            + 'étudiants. Cette opération est tracée à votre nom.',
				              { title: 'Valider cette remise ?', confirmText: 'Valider la remise', danger: false })
				.then(ok => {
					if (!ok) { return; }
					btnValider.disabled = true;
					traiter('valider').then(d => {
						resultat(d);
						if (!d.ok) { btnValider.disabled = false; }
					}).catch(() => { btnValider.disabled = false; Toast.error('Connexion perdue : la remise n\'a pas été validée.'); });
				});
			});
		}

		const zone = document.getElementById('zoneMotif');
		const btnRejeter = document.getElementById('btnRejeter');
		if (btnRejeter) {
			btnRejeter.addEventListener('click', () => {
				zone.style.display = 'block';
				document.getElementById('motif').focus();
			});
			document.getElementById('btnAnnulerRejet').addEventListener('click', () => {
				zone.style.display = 'none';
			});
			document.getElementById('btnConfirmerRejet').addEventListener('click', function () {
				const motif = document.getElementById('motif').value.trim();
				if (!motif) {
					Toast.warning('Indiquez à l\'enseignant ce qui doit être corrigé.');
					document.getElementById('motif').focus();
					return;
				}
				this.disabled = true;
				traiter('rejeter', motif).then(d => {
					resultat(d);
					if (!d.ok) { this.disabled = false; }
				}).catch(() => { this.disabled = false; Toast.error('Connexion perdue : la remise n\'a pas été renvoyée.'); });
			});
		}

		document.getElementById('basculeJournal').addEventListener('click', () => {
			const corps = document.getElementById('corpsJournal');
			const ouvert = corps.style.display !== 'none';
			corps.style.display = ouvert ? 'none' : 'block';
			document.getElementById('chevronJournal').className =
				'bi text-slate-400 ' + (ouvert ? 'bi-chevron-down' : 'bi-chevron-up');
		});
	})();
	</script>

<?php endif; /* fin vue 2 */ ?>

			</div>
			<?php require('../init/footer.php'); ?>
		</div>
	</div>
</div>
</body>
</html>
