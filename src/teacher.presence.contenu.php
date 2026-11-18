<?php
/**
 * Registre de presence — le contenu de la page, sans la coque.
 *
 * Rendu dans src/teacher.presence.php au premier affichage, puis seul
 * (?fragment=1) a chaque action, sans recharger la page :
 * src/js/teacher-presence.js remplace toute la vue (#prVue) quand on change
 * d'annee, de cours ou de seance, et seulement les zones touchees apres une
 * ecriture (#prEntete, #prAlerte, #absencesPermises, #prSeances,
 * #etatEnseignant, #listeAppel, #bilan).
 *
 * Deux vues, selon la presence de cours_id :
 *   - sans : les cours enseignes et l'etat de leur registre ;
 *   - avec : le registre d'un cours — seances prevues, seances tenues, appel
 *            de la seance choisie, et bilan de chaque etudiant au regard des
 *            15 % d'absences du Bulletin academique (§476).
 *
 * Attend $dtb, $app_base, le middleware initialise et data/presence.php.
 */
$e = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$teacherUid = getTeacherUid();
$anneeCourante = remiseAnneeCourante();
$coursId = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : 0;
$semestreParam = isset($_GET['semestre']) && $_GET['semestre'] !== '' ? (int)$_GET['semestre'] : null;

$anneesAvecCours = [];
$anneesDisponibles = [$anneeCourante];
if ($teacherUid) {
	$stmt = $dtb->prepare('SELECT DISTINCT n.annee_scolaire FROM t_2023_notes n
		JOIN t_2023_cours c ON c.id = n.id_cours
		WHERE c.id_teacher = :uid AND c.remove != 1 AND n.remove = 0
		ORDER BY n.annee_scolaire DESC');
	$stmt->execute(['uid' => $teacherUid]);
	$anneesAvecCours = $stmt->fetchAll(PDO::FETCH_COLUMN);
	foreach ($anneesAvecCours as $a) {
		if (!in_array($a, $anneesDisponibles, true)) { $anneesDisponibles[] = $a; }
	}
}
rsort($anneesDisponibles);
$anneeDefaut = in_array($anneeCourante, $anneesAvecCours, true) || !$anneesAvecCours ? $anneeCourante : $anneesAvecCours[0];
$annee = trim((string)($_GET['annee'] ?? $anneeDefaut));
?>
<?php if (!$teacherUid && aEspaceEnseignant()): ?>
<div id="prVue" data-vue="erreur">
	<div class="pr-bandeau pr-bandeau-alerte">
		<i class="bi bi-exclamation-triangle-fill"></i>
		<div><b>Votre compte n'est relié à aucune fiche d'enseignant.</b> Sans ce lien, l'application ne sait
		pas quels cours sont les vôtres. Demandez au registrariat de relier votre compte à votre fiche.</div>
	</div>
</div>
<?php
	return;
endif;

/* =========================================================================
   VUE 1 — LES COURS ENSEIGNES
   ====================================================================== */
if (!$coursId):

	require_once(__DIR__ . '/../data/cours.sigle.php');
	coursSigleAliasInstaller($dtb);

	$stmt = $dtb->prepare('SELECT c.id, c.Sigle, c.sigle_affiche, c.title, c.yearlevel,
			' . REMISE_SQL_SEMESTRE . ' AS semestre, COUNT(DISTINCT n.student_id) AS nb_etudiants
		FROM t_2023_cours c
		JOIN t_2023_notes n ON n.id_cours = c.id
		LEFT JOIN t_2023_session s ON s.session_id = n.session_id
		LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
		WHERE c.id_teacher = :uid AND c.remove != 1 AND n.remove = 0 AND n.annee_scolaire = :annee
		  AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
		GROUP BY c.id, c.Sigle, c.sigle_affiche, c.title, c.yearlevel, semestre
		ORDER BY semestre ASC, COALESCE(NULLIF(TRIM(c.sigle_affiche), ""), c.Sigle) ASC');
	$stmt->execute(['uid' => $teacherUid, 'annee' => $annee]);
	$cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

	/* L'etat des registres deja ouverts, en une requete. */
	$registres = [];
	$stmt = $dtb->prepare('SELECT f.*,
			(SELECT COUNT(*) FROM t_presence_seance s WHERE s.feuille_id = f.id) AS nb_seances,
			(SELECT COUNT(*) FROM t_presence_signalement g WHERE g.feuille_id = f.id) AS nb_signalements
		FROM t_presence_feuille f
		JOIN t_2023_cours c ON c.id = f.cours_id
		WHERE c.id_teacher = :uid AND f.annee_scolaire = :annee');
	$stmt->execute(['uid' => $teacherUid, 'annee' => $annee]);
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
		$registres[$r['cours_id'] . '|' . (string)$r['semestre']] = $r;
	}
?>
<div id="prVue" data-vue="liste">
	<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
		<div>
			<h1 class="pr-titre"><i class="bi bi-person-check mr-2"></i>Présences</h1>
			<p class="pr-sous"><?= count($cours) ?> cours en <?= $e($annee) ?> — un registre par cours et par semestre.</p>
		</div>
		<form method="get" class="flex items-center gap-2">
			<label for="annee" class="pr-sous">Année</label>
			<select id="annee" name="annee" class="pr-champ" data-nav-annee>
				<?php foreach ($anneesDisponibles as $a): ?>
					<option value="<?= $e($a) ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $e($a) ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="pr-bouton pr-secondaire">Afficher</button></noscript>
		</form>
	</div>

	<div class="pr-bandeau pr-bandeau-info mb-4">
		<i class="bi bi-journal-text"></i>
		<div>
			Faites l'appel à chaque séance (<a href="<?= $e($app_base) ?>/reglement#p472">Bulletin académique, §472</a>).
			Deux retards de 5 minutes comptent une absence, un retard de 10 minutes est une absence, et au-delà
			de 15 % des séances prévues l'étudiant doit quitter le cours : le registrariat en est alors informé
			automatiquement (<a href="<?= $e($app_base) ?>/reglement#p476">§476</a>).
		</div>
	</div>

<?php if (!$cours): ?>
	<div class="pr-carte p-10 text-center pr-sous">
		<i class="bi bi-inbox text-3xl block mb-3"></i>
		Aucun étudiant inscrit à vos cours pour <?= $e($annee) ?>.
	</div>
<?php else: ?>
	<div class="pr-carte overflow-auto">
		<table class="pr-table pr-liste-cours">
			<thead><tr>
				<th>Sigle</th><th>Cours</th><th class="pr-num">Semestre</th><th class="pr-num">Étudiants</th>
				<th class="pr-num">Séances</th><th class="pr-num">Au-delà de 15 %</th><th></th>
			</tr></thead>
			<tbody>
<?php foreach ($cours as $c):
		$r = $registres[$c['id'] . '|' . (string)$c['semestre']] ?? null;
		$lien = '?cours_id=' . (int)$c['id'] . '&annee=' . rawurlencode($annee) . '&semestre=' . rawurlencode((string)$c['semestre']);
?>
				<tr>
					<td class="font-semibold whitespace-nowrap"><?= $e(coursSigleAffiche($c)) ?></td>
					<td><a href="<?= $e($lien) ?>" data-nav data-pt-skip="1" class="hover:underline"><?= $e($c['title']) ?></a></td>
					<td class="pr-num"><?= $e(remiseLibelleSemestre($c['semestre'])) ?></td>
					<td class="pr-num"><?= $nbEtu = (int)$c['nb_etudiants'] ?><span class="pr-mobile"> étudiant<?= $nbEtu > 1 ? 's' : '' ?></span></td>
					<td class="pr-num">
						<?= $r ? (int)$r['nb_seances'] : 0 ?> / <?= $r && $r['seances_prevues'] ? (int)$r['seances_prevues'] : '<span title="Séances prévues non indiquées">?</span>' ?><span class="pr-mobile"> séances</span>
					</td>
					<td class="pr-num<?= $r && (int)$r['nb_signalements'] > 0 ? '' : ' pr-vide' ?>">
						<?php if ($r && (int)$r['nb_signalements'] > 0): ?>
							<span class="pr-etiquette" style="background:rgba(239,68,68,.15);color:#f87171;border-color:rgba(248,113,113,.4)"><?= (int)$r['nb_signalements'] ?><span class="pr-mobile"> au-delà de 15 %</span></span>
						<?php else: ?>—<?php endif; ?>
					</td>
					<td class="pr-num"><a href="<?= $e($lien) ?>" data-nav data-pt-skip="1" class="pr-bouton pr-primaire" style="min-height:32px;padding:0 12px;font-size:12px"><i class="bi bi-list-check"></i>Registre</a></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
</div>

<?php
/* =========================================================================
   VUE 2 — LE REGISTRE D'UN COURS
   ====================================================================== */
else:

	if (!remiseEnseignantPossedeCours($dtb, $coursId)) {
		echo '<div id="prVue" data-vue="erreur"><div class="pr-bandeau pr-bandeau-erreur"><i class="bi bi-shield-exclamation"></i>'
		   . '<div>Ce cours ne fait pas partie de vos enseignements.</div></div></div>';
		return;
	}
	$feuille = presenceFeuilleOuvrir($dtb, $coursId, $annee, $semestreParam);
	if (!$feuille) {
		echo '<div id="prVue" data-vue="erreur"><div class="pr-bandeau pr-bandeau-erreur"><i class="bi bi-x-octagon"></i><div>Cours introuvable.</div></div></div>';
		return;
	}

	$inscrits = presenceInscrits($dtb, $coursId, $annee, $semestreParam);
	$seances  = presenceSeances($dtb, (int)$feuille['id']);
	$seanceId = isset($_GET['seance']) ? (int)$_GET['seance'] : (int)($seances[0]['id'] ?? 0);
	$seance   = null;
	foreach ($seances as $s) { if ((int)$s['id'] === $seanceId) { $seance = $s; } }
	$lignes = [];
	if ($seance) {
		/* Un etudiant inscrit apres l'ouverture de la seance doit pouvoir etre pointe. */
		if (presenceSeanceSynchroniser($dtb, (int)$seance['id'], $inscrits) > 0) {
			$seances = presenceSeances($dtb, (int)$feuille['id']);
			foreach ($seances as $s) { if ((int)$s['id'] === $seanceId) { $seance = $s; } }
		}
		$lignes = presenceLignesSeance($dtb, (int)$seance['id']);
	}
	$bilans       = presenceBilans($dtb, $feuille);
	$signalements = presenceSignalementsFeuille($dtb, (int)$feuille['id']);
	$permises     = presenceAbsencesPermises($feuille['seances_prevues']);
	$aujourdhui   = date('Y-m-d');
	$heureCreneau = presenceHeureCreneau($dtb, $coursId, $annee, $semestreParam, $aujourdhui);
	$base         = '?cours_id=' . $coursId . '&annee=' . rawurlencode($annee) . '&semestre=' . rawurlencode((string)$semestreParam);
	$nbDepasse    = count(array_filter($bilans, static fn ($b) => $b['etat'] === 'depasse'));
?>
<div id="prVue" data-vue="registre" data-feuille="<?= (int)$feuille['id'] ?>" data-seance="<?= $seance ? (int)$seance['id'] : 0 ?>" data-base="<?= $e($base) ?>">
	<div class="mb-4 flex flex-wrap items-center justify-between gap-3" id="prEntete">
		<div class="flex items-center gap-3 flex-wrap">
			<a href="?annee=<?= rawurlencode($annee) ?>" class="pr-bouton pr-secondaire" data-nav data-pt-skip="1"><i class="bi bi-arrow-left"></i>Mes cours</a>
			<div>
				<h1 class="pr-titre"><?= $e($feuille['sigle']) ?> — <?= $e($feuille['titre_cours']) ?></h1>
				<p class="pr-sous"><?= $e($annee) ?> · <?= $e(remiseLibelleSemestre($feuille['semestre'])) ?> · <?= count($inscrits) ?> étudiant(s)</p>
			</div>
		</div>
		<span class="pr-etiquette" style="background:rgb(var(--c-accent) / .14);color:rgb(var(--c-accent-text));border-color:rgb(var(--c-accent) / .35);font-size:12px;padding:6px 12px">
			<i class="bi bi-calendar-check"></i><?= count($seances) ?> séance(s) tenue(s)<?= $feuille['seances_prevues'] ? ' sur ' . (int)$feuille['seances_prevues'] . ' prévues' : '' ?>
		</span>
	</div>

	<div id="prAlerte">
	<?php if ($nbDepasse > 0): ?>
	<div class="pr-bandeau pr-bandeau-erreur mb-4">
		<i class="bi bi-x-octagon-fill" style="color:#f87171"></i>
		<div><b><?= $nbDepasse ?> étudiant(s) au-delà de 15 % d'absences</b> : ils doivent quitter le cours
		(<a href="<?= $e($app_base) ?>/reglement#p476">§476</a>). Le registrariat en a été informé ; voir le bilan plus bas.</div>
	</div>
	<?php endif; ?>
	</div>

	<div class="grid gap-4 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
		<!-- Seances prevues : la base des 15 % -->
		<div class="pr-carte">
			<div class="pr-entete"><span><i class="bi bi-sliders mr-1"></i>Base des 15 %</span></div>
			<div class="p-4">
				<label class="pr-label" for="seancesPrevues">Séances prévues ce semestre</label>
				<div class="flex items-center gap-3 flex-wrap">
					<input id="seancesPrevues" type="number" min="1" max="200" class="pr-champ" style="width:96px"
					       value="<?= $feuille['seances_prevues'] ? (int)$feuille['seances_prevues'] : '' ?>" placeholder="ex. 30">
					<span class="pr-sous" id="absencesPermises">
						<?= $permises === null ? 'Indiquez-les : sans elles, la règle des 15 % ne peut pas s\'appliquer.'
							: '<b>' . $permises . '</b> absence(s) permise(s) ; la ' . ($permises + 1) . ($permises === 0 ? '<sup>re</sup>' : '<sup>e</sup>') . ' fait dépasser 15 %.' ?>
					</span>
				</div>
			</div>
		</div>

		<!-- Nouvelle seance -->
		<div class="pr-carte">
			<div class="pr-entete"><span><i class="bi bi-plus-circle mr-1"></i>Nouvelle séance</span></div>
			<form id="formSeance" class="p-4 flex flex-wrap items-end gap-3">
				<div>
					<label class="pr-label" for="dateSeance">Date</label>
					<input id="dateSeance" name="date_seance" type="date" class="pr-champ" value="<?= $aujourdhui ?>" max="<?= $aujourdhui ?>" required>
				</div>
				<div>
					<label class="pr-label" for="heurePrevue">Début prévu</label>
					<input id="heurePrevue" name="heure_prevue" type="time" class="pr-champ" value="<?= $e($heureCreneau ?? '') ?>"
					       title="<?= $heureCreneau ? 'Créneau de l\'emploi du temps' : 'Aucun créneau à l\'emploi du temps ce jour-là' ?>">
				</div>
				<div>
					<label class="pr-label" for="arriveeEnseignant">Votre arrivée</label>
					<input id="arriveeEnseignant" name="arrivee_enseignant" type="time" class="pr-champ" value="<?= date('H:i') ?>">
				</div>
				<label class="flex items-center gap-2 pr-sous" style="min-height:36px" title="§474 : prévenir les étudiants d'un retard">
					<input type="checkbox" name="message_retard" value="1"> J'ai prévenu d'un retard
				</label>
				<button type="submit" class="pr-bouton pr-primaire"><i class="bi bi-play-fill"></i>Faire l'appel</button>
			</form>
		</div>
	</div>

	<div class="pr-deux mb-4" id="zoneSeances">
		<!-- Seances tenues -->
		<nav class="pr-carte overflow-hidden" aria-label="Séances tenues" id="prSeances">
			<div class="pr-entete"><span><i class="bi bi-calendar3 mr-1"></i>Séances</span><span class="pr-sous"><?= count($seances) ?></span></div>
			<?php if (!$seances): ?>
				<p class="p-4 pr-sous">Aucune séance pour l'instant. Ouvrez-en une avec « Faire l'appel ».</p>
			<?php else: foreach ($seances as $s):
				$sansPenalite = presenceSeanceSansPenalite($s);
				$aPointer = (int)$s['nb_lignes'] - (int)$s['nb_pointees'];
			?>
				<a class="pr-seance" data-nav data-pt-skip="1" href="<?= $e($base . '&seance=' . (int)$s['id']) ?>#appel" aria-current="<?= (int)$s['id'] === $seanceId ? 'true' : 'false' ?>">
					<div class="pr-seance__date"><?= $e(presenceDateLongue($s['date_seance'])) ?></div>
					<div class="pr-seance__detail">
						<?= $s['heure_prevue'] ? substr($s['heure_prevue'], 0, 5) . ' · ' : '' ?>
						<?= (int)$s['nb_absents'] ?> abs. · <?= (int)$s['nb_retards'] ?> ret.
						<?php if ($aPointer > 0): ?> · <span style="color:#fbbf24"><?= $aPointer ?> à pointer</span><?php endif; ?>
					</div>
					<?php if ($sansPenalite): ?>
						<div class="pr-seance__detail" style="color:#fbbf24"><i class="bi bi-hourglass-split"></i> Sans pénalité (§474)</div>
					<?php endif; ?>
				</a>
			<?php endforeach; endif; ?>
		</nav>

<?php require __DIR__ . '/teacher.presence.appel.php'; ?>
	</div>

	<!-- Bilan du cours -->
	<div class="pr-carte overflow-auto" id="bilan">
		<div class="pr-entete">
			<span><i class="bi bi-bar-chart-line mr-1"></i>Bilan des absences (§476)</span>
			<span class="pr-sous">Absences comptées = absences + 1 par paire de retards</span>
		</div>
		<table class="pr-table">
			<thead><tr>
				<th>Étudiant</th><th class="pr-num">Présences</th><th class="pr-num">Retards</th><th class="pr-num">Absences</th>
				<th class="pr-num">Comptées</th><th class="pr-num">Taux</th><th>État</th>
			</tr></thead>
			<tbody>
<?php foreach ($inscrits as $i):
			$b = $bilans[$i['student_id']] ?? presenceBilan([], $feuille['seances_prevues']);
			$info = presenceEtatInfo($b['etat']);
			$sig = $signalements[$i['student_id']] ?? null;
?>
				<tr data-bilan="<?= $e($i['student_id']) ?>">
					<td><?= $e(trim(($i['student_nom'] ?? '') . ' ' . ($i['student_prenom'] ?? ''))) ?> <span class="pr-appel__mat"><?= $e($i['student_id']) ?></span></td>
					<td class="pr-num js-b-presences"><?= (int)$b['presences'] ?></td>
					<td class="pr-num js-b-retards"><?= (int)$b['retards'] ?></td>
					<td class="pr-num js-b-absences"><?= (int)$b['absences'] ?></td>
					<td class="pr-num js-b-comptees"><b><?= (int)$b['comptees'] ?></b><?= $b['permises'] !== null ? ' / ' . (int)$b['permises'] : '' ?></td>
					<td class="pr-num js-b-taux"><?= $b['taux'] !== null ? str_replace('.', ',', (string)$b['taux']) . ' %' : '—' ?></td>
					<td class="js-b-etat">
						<span class="pr-etiquette" style="background:<?= $info['couleur'] ?>22;color:<?= $info['couleur'] ?>;border-color:<?= $info['couleur'] ?>55">
							<i class="<?= $info['icone'] ?>"></i><?= $e($info['libelle']) ?>
						</span>
						<?php if ($sig): ?>
							<div class="pr-sous" style="font-size:11px;margin-top:3px">Signalé au registrariat le <?= date('d/m/Y', strtotime($sig['signale_le'])) ?></div>
						<?php endif; ?>
					</td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>

<?php if (!$seance):
	/* Aucune seance ouverte : un modele de l'appel (les etudiants du cours, sans pointage) permet d'ouvrir une
	   seance hors ligne ; le serveur la cree au retour du reseau (src/js/teacher-presence.js). Quand une seance
	   est ouverte, c'est elle le modele : le script en reprend la page. */
	$seanceReelle = $seance; $lignesReelles = $lignes;
	$seance = ['id' => 0, 'date_seance' => $aujourdhui, 'heure_prevue' => null, 'arrivee_enseignant' => null, 'message_retard' => 0, 'commentaire' => null];
	$lignes = array_map(static fn ($i) => ['student_id' => $i['student_id'], 'student_nom' => $i['student_nom'] ?? '',
		'student_prenom' => $i['student_prenom'] ?? '', 'statut' => null, 'minutes_retard' => null, 'justificatif' => null], $inscrits);
?>
	<template id="prModeleAppel">
<?php require __DIR__ . '/teacher.presence.appel.php'; ?>
	</template>
<?php $seance = $seanceReelle; $lignes = $lignesReelles; endif; ?>
</div>

<?php endif; /* fin vue 2 */ ?>
