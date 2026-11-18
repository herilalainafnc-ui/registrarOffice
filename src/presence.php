<?php
/**
 * Assiduite — vue du registrariat (Bulletin academique, §472 a §476).
 *
 * Ce que le registraire doit savoir, sans ouvrir chaque registre :
 *   1. les etudiants au-dela de 15 % d'absences dans un cours (§476),
 *      signales automatiquement au pointage ; il les marque traites ;
 *   2. les retards des enseignants (§474) : arrivee plus de 10 minutes apres
 *      l'heure, avec ou sans message aux etudiants ;
 *   3. les registres dont la base des 15 % n'est pas indiquee : la regle ne
 *      peut pas s'y appliquer.
 *
 * Acces : superadmin et registrariat (AdminController::presence).
 */
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Assiduité</title>
<?php
require_once('../data/presence.php');

$e = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$annee = trim((string)($_GET['annee'] ?? remiseAnneeCourante()));
$voirTraites = !empty($_GET['traites']);

/* Noms d'enseignant : teacher.name et teacher.lastName n'ont pas la meme
   collation, on les ramene a une seule avant de les assembler. */
$nomEnseignant = "TRIM(CONCAT_WS(' ', CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                       CONVERT(t.lastName USING utf8mb4) COLLATE utf8mb4_general_ci))";

$annees = $dtb->query('SELECT DISTINCT annee_scolaire FROM t_presence_feuille ORDER BY annee_scolaire DESC')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($annee, $annees, true)) { array_unshift($annees, $annee); }

/* 1. Signalements, les non traites d'abord. */
$stmt = $dtb->prepare("SELECT g.*, f.id AS feuille_id, f.cours_id, f.sigle, f.titre_cours, f.semestre, f.seances_prevues AS prevues_actuelles,
		(SELECT MAX(student_nom) FROM tbl_2024_etudiant x WHERE x.student_id = g.student_id) AS student_nom,
		(SELECT MAX(student_prenom) FROM tbl_2024_etudiant x WHERE x.student_id = g.student_id) AS student_prenom,
		$nomEnseignant AS enseignant
	FROM t_presence_signalement g
	JOIN t_presence_feuille f ON f.id = g.feuille_id
	LEFT JOIN t_2023_cours c ON c.id = f.cours_id
	LEFT JOIN teacher t ON t.uid = c.id_teacher
	WHERE f.annee_scolaire = :a" . ($voirTraites ? '' : ' AND g.traite_le IS NULL') . "
	ORDER BY g.traite_le IS NOT NULL, g.signale_le DESC");
$stmt->execute(['a' => $annee]);
$signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Le compte a jour de chaque etudiant signale : une absence corrigee depuis
   peut l'avoir ramene sous le seuil. */
$feuilles = [];
foreach ($signalements as &$s) {
	$fid = (int)$s['feuille_id'];
	$feuilles[$fid] = $feuilles[$fid] ?? presenceFeuilleCharger($dtb, $fid);
	$s['bilan'] = presenceBilanEtudiant($dtb, $feuilles[$fid], $s['student_id']);
}
unset($s);
$enAttente = presenceSignalementsEnAttente($dtb);

/* 2. Retards des enseignants (§474) : plus de 10 minutes apres l'heure. */
$stmt = $dtb->prepare("SELECT s.*, f.cours_id, f.sigle, f.titre_cours, $nomEnseignant AS enseignant
	FROM t_presence_seance s
	JOIN t_presence_feuille f ON f.id = s.feuille_id
	LEFT JOIN t_2023_cours c ON c.id = f.cours_id
	LEFT JOIN teacher t ON t.uid = c.id_teacher
	WHERE f.annee_scolaire = :a AND s.heure_prevue IS NOT NULL AND s.arrivee_enseignant IS NOT NULL
	  AND TIMESTAMPDIFF(MINUTE, CONCAT(s.date_seance, ' ', s.heure_prevue), CONCAT(s.date_seance, ' ', s.arrivee_enseignant)) > :attente
	ORDER BY s.date_seance DESC, s.heure_prevue DESC");
$stmt->execute(['a' => $annee, 'attente' => PRESENCE_ATTENTE]);
$retards = $stmt->fetchAll(PDO::FETCH_ASSOC);
$parEnseignant = [];
foreach ($retards as $r) {
	$cle = $r['enseignant'] ?: 'Enseignant non renseigné';
	$parEnseignant[$cle] = ($parEnseignant[$cle] ?? 0) + 1;
}
arsort($parEnseignant);

/* 3. Registres tenus sans base des 15 %. */
$stmt = $dtb->prepare("SELECT f.*, $nomEnseignant AS enseignant,
		(SELECT COUNT(*) FROM t_presence_seance s WHERE s.feuille_id = f.id) AS nb_seances
	FROM t_presence_feuille f
	LEFT JOIN t_2023_cours c ON c.id = f.cours_id
	LEFT JOIN teacher t ON t.uid = c.id_teacher
	WHERE f.annee_scolaire = :a AND f.seances_prevues IS NULL
	  AND EXISTS (SELECT 1 FROM t_presence_seance s WHERE s.feuille_id = f.id)
	ORDER BY f.sigle");
$stmt->execute(['a' => $annee]);
$sansBase = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lienRegistre = static function (array $f) use ($app_base, $annee): string {
	return $app_base . '/teacher/presence?cours_id=' . (int)$f['cours_id'] . '&annee=' . rawurlencode($annee)
	     . '&semestre=' . rawurlencode((string)($f['semestre'] ?? ''));
};
?>
	<style>
		.as-carte { background: rgb(var(--c-surface-2)); border: 1px solid rgb(var(--c-line)); border-radius: 12px; overflow: hidden; }
		[data-theme="light"] .as-carte { background: rgb(var(--c-surface)); border-color: rgb(var(--c-line-strong)); }
		.as-entete { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
			padding: 10px 16px; border-bottom: 1px solid rgb(var(--c-line)); font-weight: 600; color: rgb(var(--c-text)); }
		.as-titre { font-size: 22px; font-weight: 700; color: rgb(var(--c-text)); }
		.as-sous { color: rgb(var(--c-text-2)); font-size: 13px; }
		.as-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
		.as-table th { padding: 8px 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
			color: rgb(var(--c-text-3)); border-bottom: 1px solid rgb(var(--c-line)); text-align: left; background: rgb(var(--c-surface-2)); }
		.as-table td { padding: 9px 10px; border-bottom: 1px solid rgb(var(--c-line) / .6); color: rgb(var(--c-text)); vertical-align: middle; }
		.as-table tr.est-traite td { opacity: .6; }
		.as-num { text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
		.as-mat { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: rgb(var(--c-text-3)); }
		.as-etiquette { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px;
			font-size: 11px; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
		.as-bouton { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; padding: 0 12px; border-radius: 8px;
			font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid rgb(var(--c-line-strong));
			background: rgb(var(--c-raised)); color: rgb(var(--c-text)); text-decoration: none; }
		.as-bouton:focus-visible { outline: 2px solid rgb(var(--c-accent)); outline-offset: 2px; }
		.as-champ { min-height: 34px; padding: 0 10px; border-radius: 8px; background: rgb(var(--c-sunken));
			border: 1px solid rgb(var(--c-line-strong)); color: rgb(var(--c-text)); font-size: 13px; }
		.as-vide { padding: 22px; text-align: center; color: rgb(var(--c-text-3)); font-size: 13px; }
		.as-regle { padding: 10px 14px; border-radius: 10px; font-size: 13px; line-height: 1.55; color: rgb(var(--c-text-2));
			background: rgb(var(--c-accent) / .1); border: 1px solid rgb(var(--c-accent) / .35); }
		.as-regle a { color: rgb(var(--c-accent-text)); text-decoration: underline; }
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="min-h-screen w-full <?=$bg_three_color?>">
	<!-- TOP BAR --><?php require('../init/topbar.php');?>
	<div class="w-full flex flex-col lg:flex-row">
		<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>
		<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
			<?php /* Annee et signalements traites : sans recharger (src/js/zone-ajax.js). */ ?>
			<div class="w-full px-4 py-4 flex-1 overflow-auto" id="zoneAssiduite" data-zone-ajax>

	<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
		<div>
			<h1 class="as-titre"><i class="bi bi-person-check mr-2"></i>Assiduité</h1>
			<p class="as-sous"><?= $enAttente ?> signalement(s) à traiter, toutes années confondues.</p>
		</div>
		<form method="get" class="flex items-center gap-2 flex-wrap">
			<label class="as-sous" for="annee">Année</label>
			<select id="annee" name="annee" class="as-champ" data-zone-auto>
				<?php foreach ($annees as $a): ?><option value="<?= $e($a) ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $e($a) ?></option><?php endforeach; ?>
			</select>
			<label class="as-sous flex items-center gap-2"><input type="checkbox" id="voirTraites" name="traites" value="1" <?= $voirTraites ? 'checked' : '' ?> data-zone-auto> Afficher les traités</label>
			<noscript><button type="submit" class="as-bouton">Afficher</button></noscript>
		</form>
	</div>

	<p class="as-regle mb-4">
		Un étudiant qui dépasse 15 % d'absences dans un cours doit le quitter ; l'enseignant doit en informer le
		registraire (<a href="<?= $e($app_base) ?>/reglement#p476">§476</a>). L'application le fait au pointage de l'absence de trop.
		Deux retards de 5 minutes comptent une absence, un retard de 10 minutes est une absence.
	</p>

	<!-- 1. Signalements -->
	<section class="as-carte mb-4" aria-labelledby="titreSignalements">
		<div class="as-entete"><span id="titreSignalements"><i class="bi bi-x-octagon mr-1" style="color:#f87171"></i>Au-delà de 15 % d'absences</span>
			<span class="as-sous"><?= count($signalements) ?> étudiant(s)</span></div>
<?php if (!$signalements): ?>
		<p class="as-vide">Aucun signalement <?= $voirTraites ? '' : 'à traiter ' ?>pour <?= $e($annee) ?>.</p>
<?php else: ?>
		<div class="overflow-auto">
		<table class="as-table">
			<thead><tr><th>Étudiant</th><th>Cours</th><th>Enseignant</th><th class="as-num">Absences comptées</th><th>Signalé</th><th>Aujourd'hui</th><th></th></tr></thead>
			<tbody>
<?php foreach ($signalements as $s):
		$b = $s['bilan'];
		$info = presenceEtatInfo($b['etat']);
?>
				<tr class="<?= $s['traite_le'] ? 'est-traite' : '' ?>" data-signalement="<?= (int)$s['id'] ?>">
					<td><?= $e(trim(($s['student_nom'] ?? '') . ' ' . ($s['student_prenom'] ?? ''))) ?> <div class="as-mat"><?= $e($s['student_id']) ?></div></td>
					<td><b><?= $e($s['sigle']) ?></b> <span class="as-sous"><?= $e($s['titre_cours']) ?></span> <div class="as-sous" style="font-size:11px"><?= $e(remiseLibelleSemestre($s['semestre'])) ?></div></td>
					<td><?= $e($s['enseignant'] ?: '—') ?></td>
					<td class="as-num"><b><?= (int)$b['comptees'] ?></b><?= $b['permises'] !== null ? ' / ' . (int)$b['permises'] . ' permises' : '' ?>
						<div class="as-sous" style="font-size:11px"><?= (int)$b['absences'] ?> abs. · <?= (int)$b['retards'] ?> ret. · <?= $b['prevues'] ? (int)$b['prevues'] . ' séances prévues' : 'base non indiquée' ?></div></td>
					<td class="as-num"><?= date('d/m/Y H:i', strtotime($s['signale_le'])) ?></td>
					<td><span class="as-etiquette" style="background:<?= $info['couleur'] ?>22;color:<?= $info['couleur'] ?>;border-color:<?= $info['couleur'] ?>55"><i class="<?= $info['icone'] ?>"></i><?= $b['etat'] === 'depasse' ? 'Toujours au-delà' : 'Revenu sous le seuil' ?></span></td>
					<td class="as-num">
						<a class="as-bouton" href="<?= $e($lienRegistre($s)) ?>#bilan" title="Ouvrir le registre du cours"><i class="bi bi-list-check"></i>Registre</a>
						<?php if (!$s['traite_le']): ?>
							<button type="button" class="as-bouton js-traiter"><i class="bi bi-check2"></i>Traité</button>
						<?php else: ?>
							<span class="as-sous" style="font-size:11px">Traité le <?= date('d/m/Y', strtotime($s['traite_le'])) ?></span>
						<?php endif; ?>
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
		</div>
<?php endif; ?>
	</section>

	<!-- 2. Retards des enseignants -->
	<section class="as-carte mb-4" aria-labelledby="titreRetards">
		<div class="as-entete"><span id="titreRetards"><i class="bi bi-hourglass-split mr-1" style="color:#fbbf24"></i>Retards des enseignants (plus de <?= PRESENCE_ATTENTE ?> min)</span>
			<a class="as-sous" href="<?= $e($app_base) ?>/reglement#p474" style="text-decoration:underline">§474</a></div>
<?php if (!$retards): ?>
		<p class="as-vide">Aucun retard de plus de <?= PRESENCE_ATTENTE ?> minutes enregistré pour <?= $e($annee) ?>.</p>
<?php else: ?>
		<div class="px-4 py-3 as-sous" style="border-bottom:1px solid rgb(var(--c-line))">
			<?php foreach ($parEnseignant as $nom => $nb): ?>
				<span class="as-etiquette" style="background:rgba(245,158,11,.12);color:rgb(var(--c-text));border-color:rgba(245,158,11,.35);margin:2px"><?= $e($nom) ?> : <?= $nb ?></span>
			<?php endforeach; ?>
		</div>
		<div class="overflow-auto">
		<table class="as-table">
			<thead><tr><th>Séance</th><th>Cours</th><th>Enseignant</th><th class="as-num">Retard</th><th>Message aux étudiants</th></tr></thead>
			<tbody>
<?php foreach ($retards as $r): $min = presenceRetardEnseignant($r); ?>
				<tr>
					<td><?= $e(presenceDateLongue($r['date_seance'])) ?> <div class="as-sous" style="font-size:11px">prévue à <?= substr($r['heure_prevue'], 0, 5) ?>, arrivée à <?= substr($r['arrivee_enseignant'], 0, 5) ?></div></td>
					<td><b><?= $e($r['sigle']) ?></b> <span class="as-sous"><?= $e($r['titre_cours']) ?></span></td>
					<td><?= $e($r['enseignant'] ?: '—') ?></td>
					<td class="as-num"><?= (int)$min ?> min</td>
					<td><?= $r['message_retard'] ? 'Oui' : '<span style="color:#fbbf24">Non : séance sans pénalité pour les étudiants</span>' ?></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
		</div>
<?php endif; ?>
	</section>

	<!-- 3. Registres sans base -->
	<section class="as-carte" aria-labelledby="titreSansBase">
		<div class="as-entete"><span id="titreSansBase"><i class="bi bi-question-circle mr-1"></i>Registres sans séances prévues</span>
			<span class="as-sous">La règle des 15 % ne peut pas s'y appliquer</span></div>
<?php if (!$sansBase): ?>
		<p class="as-vide">Tous les registres tenus en <?= $e($annee) ?> indiquent leurs séances prévues.</p>
<?php else: ?>
		<table class="as-table">
			<thead><tr><th>Cours</th><th>Enseignant</th><th class="as-num">Séances tenues</th><th></th></tr></thead>
			<tbody>
<?php foreach ($sansBase as $f): ?>
				<tr>
					<td><b><?= $e($f['sigle']) ?></b> <span class="as-sous"><?= $e($f['titre_cours']) ?> · <?= $e(remiseLibelleSemestre($f['semestre'])) ?></span></td>
					<td><?= $e($f['enseignant'] ?: '—') ?></td>
					<td class="as-num"><?= (int)$f['nb_seances'] ?></td>
					<td class="as-num"><a class="as-bouton" href="<?= $e($lienRegistre($f)) ?>"><i class="bi bi-list-check"></i>Registre</a></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
<?php endif; ?>
	</section>

	<script>
	document.querySelectorAll('.js-traiter').forEach(function (bouton) {
		bouton.addEventListener('click', function () {
			var ligne = bouton.closest('tr');
			var corps = new FormData();
			corps.append('csrf_token', CSRF_TOKEN);
			corps.append('signalement_id', ligne.dataset.signalement);
			bouton.disabled = true;
			fetch(APP_BASE + '/app/.presence/traiter.php', { method: 'POST', body: corps, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (!d.ok) { bouton.disabled = false; Toast.error(d.message || 'Enregistrement refusé.'); return; }
					Toast.success('Signalement marqué comme traité.');
					ligne.classList.add('est-traite');
					bouton.outerHTML = '<span class="as-sous" style="font-size:11px">Traité à l\'instant</span>';
				})
				.catch(function () { bouton.disabled = false; Toast.error('Connexion perdue : rien n\'a été enregistré.'); });
		});
	});
	</script>

			</div>
			<?php require('../init/footer.php'); ?>
		</div>
	</div>
</div>
</body>
</html>
