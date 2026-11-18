<?php
/* =============================================================================
   Tableau de bord du personnel (src/tableau-de-bord.php)

   Deux blocs :
     « À traiter »    ce qui attend quelqu'un aujourd'hui, en compteurs qui
                      ouvrent chacun la page où le traiter ;
     « Chiffres clés » l'état de l'université pour une année scolaire, comparé
                      à l'année précédente.

   Deux règles :
   - un compteur n'apparaît que si la page qu'il ouvre est accessible au
     compte : ses droits sont ceux des contrôleurs (AdmissionController,
     AdminController, FinanceController, InscriptionController, QueueController) ;
   - le périmètre de cycle d'un compte (data/perimetre.php) s'applique là où la
     page d'arrivée l'applique (étudiants, inscriptions, cours, notes), et pas
     ailleurs (candidatures, remises, présences, réinscriptions) : le chiffre du
     tableau de bord est celui que la page affichera.

   Chaque bloc est lu dans un try : une table absente ou une colonne pas encore
   migrée retire un compteur, pas la page.
   ========================================================================== */

require_once __DIR__ . '/inscription.session.php';   // inscAnneeCourante()
require_once __DIR__ . '/perimetre.php';
require_once __DIR__ . '/cours.sigle.php';            // coursSigleAffiche(), coursNiveauLibelle()

/* Une note « non saisie » : vide ou code -4 (« pas encore noté »), comme dans
   l'espace étudiant (data/espace.etudiant.php). -2 (validé) et -3 (échec) sont
   des résultats saisis. Le grade est un VARCHAR, d'où les chaînes. */
const TDB_NON_NOTEE = "(n.grade IS NULL OR n.grade IN ('', '-1', '-4'))";

/* Les inscriptions avec leur ANNEE EFFECTIVE : celle de leur session.
   t_2024_inscription_session.annee_scolaire n'en est qu'une copie, qui a
   diverge (data/inscription.session.php : « le catalogue fait foi »). Le
   06/10/2026, cinq inscrits du premier semestre 2026-2027 portaient « 2025 -
   2026 » : le tableau de bord en comptait 2 la ou l'export Statistique, qui
   lit la session, en comptait 7. La copie ne sert que si la session manque. */
const TDB_INSCRIPTIONS = "(SELECT ins.*, COALESCE(NULLIF(se.session_year, ''), TRIM(ins.annee_scolaire)) AS annee_effective
	FROM t_2024_inscription_session ins LEFT JOIN t_2023_session se ON se.session_id = ins.session_id)";

/** Droits du compte connecté, lus comme les contrôleurs les lisent. */
function tdbContexte(): array
{
	$niveau = (int) ($_SESSION['user_level'] ?? 99);
	$registrariat = function_exists('hasAnyPrivilege')
		? hasAnyPrivilege(['superadmin', 'registrar'])
		: ($niveau === 1 || ($_SESSION['user_privilege'] ?? '') === 'registrar');
	return [
		'niveau'       => $niveau,
		'superadmin'   => $niveau === 1,
		'registrariat' => $registrariat,          // candidatures, remises, présences, cours par session
		'inscription'  => $niveau <= 6,           // InscriptionController : niveau 6 et moins
		'reinscription'=> in_array($niveau, [1, 3, 4], true),
		'cycle'        => perimetreCycle(),
	];
}

/** L'année scolaire précédente : « 2025 - 2026 » -> « 2024 - 2025 ». */
function tdbAnneePrecedente(string $annee): string
{
	$debut = (int) substr($annee, 0, 4);
	return ($debut - 1) . ' - ' . $debut;
}

/** Années proposées : celles qui ont des inscriptions, et l'année en cours. */
function tdbAnnees(PDO $dtb): array
{
	$annees = [inscAnneeCourante()];
	try {
		foreach ($dtb->query('SELECT DISTINCT i.annee_effective FROM ' . TDB_INSCRIPTIONS . ' i WHERE i.`remove` = 0') as $r) {
			$a = trim((string) $r['annee_effective']);
			if (preg_match('~^\d{4} - \d{4}$~', $a)) { $annees[] = $a; }
		}
	} catch (Throwable $e) {
		error_log('tableau-de-bord annees : ' . $e->getMessage());
	}
	$annees = array_values(array_unique($annees));
	rsort($annees);
	return $annees;
}

/** Exécute une lecture ; null (et une trace) si elle échoue. */
function tdbLire(callable $lecture, string $quoi)
{
	try {
		return $lecture();
	} catch (Throwable $e) {
		error_log("tableau-de-bord $quoi : " . $e->getMessage());
		return null;
	}
}

/* ============================================================== À TRAITER == */

/**
 * Éléments à traiter, du plus pressant au moins pressant. Seuls ceux dont le
 * nombre est positif sont rendus.
 *
 * @return list<array{cle:string, icone:string, titre:string, nombre:int, detail:string, lien:string, ton:string, liste?:list<array{texte:string, lien:string, valeur:string}>}>
 */
function tdbATraiter(PDO $dtb, array $ctx, string $app_base): array
{
	$annee = inscAnneeCourante();
	$ecoulee = tdbAnneePrecedente($annee);
	$court = fn (string $a) => str_replace(' - ', '-', $a);   // « 2025-2026 » dans les textes
	$items = [];

	if ($ctx['registrariat']) {
		/* Remises de notes transmises par les enseignants, à valider */
		$r = tdbLire(fn () => $dtb->query("SELECT COUNT(*) n, MIN(soumis_le) depuis FROM t_remise_notes WHERE statut = 'soumise'")
			->fetch(PDO::FETCH_ASSOC), 'remises');
		if ($r && (int) $r['n'] > 0) {
			$items[] = ['cle' => 'remises', 'icone' => 'bi-clipboard2-check', 'ton' => 'attention',
				'titre' => tdbPluriel((int) $r['n'], 'remise de notes', 'remises de notes') . ' à valider',
				'nombre' => (int) $r['n'],
				'detail' => $r['depuis'] ? 'la plus ancienne transmise ' . tdbDepuis((string) $r['depuis']) : '',
				'lien' => $app_base . '/remise-notes'];
		}

		/* Étudiants au-delà des 15 % d'absences, signalés par un enseignant */
		$n = tdbLire(fn () => (int) $dtb->query('SELECT COUNT(*) FROM t_presence_signalement WHERE traite_le IS NULL')->fetchColumn(), 'signalements');
		if ($n) {
			$items[] = ['cle' => 'signalements', 'icone' => 'bi-person-exclamation', 'ton' => 'attention',
				'titre' => tdbPluriel($n, 'signalement', 'signalements') . ' d\'absences (15 %)',
				'nombre' => $n, 'detail' => 'à examiner avant les examens', 'lien' => $app_base . '/presence'];
		}

		/* Candidatures reçues par le site, pas encore instruites */
		$r = tdbLire(fn () => $dtb->query("SELECT SUM(statut = 'nouveau') nouveaux, SUM(statut = 'en_cours') en_cours
			FROM candidat WHERE archive_le IS NULL")->fetch(PDO::FETCH_ASSOC), 'candidatures');
		if ($r && (int) $r['nouveaux'] > 0) {
			$items[] = ['cle' => 'candidatures', 'icone' => 'bi-inbox', 'ton' => 'attention',
				'titre' => tdbPluriel((int) $r['nouveaux'], 'nouvelle candidature', 'nouvelles candidatures'),
				'nombre' => (int) $r['nouveaux'],
				'detail' => (int) $r['en_cours'] > 0 ? tdbPluriel((int) $r['en_cours'], 'autre', 'autres') . ' en cours d\'instruction' : 'à instruire',
				'lien' => $app_base . '/candidatures'];
		}
	}

	if ($ctx['reinscription']) {
		/* Réinscription : chacun voit la moitié qui le concerne (data/reinscription.php) */
		require_once __DIR__ . '/reinscription.php';
		$s = tdbLire(fn () => rsnStatistiques($dtb), 'reinscription');
		if ($s) {
			$caisse = in_array($ctx['niveau'], [1, 4], true) ? $s['attente_caisse'] : 0;
			$dossier = in_array($ctx['niveau'], [1, 3], true) ? $s['attente_dossier'] : 0;
			if ($caisse + $dossier > 0) {
				$parts = [];
				if ($caisse) { $parts[] = $caisse . ' en attente de la caisse'; }
				if ($dossier) { $parts[] = $dossier . ' en attente du dossier'; }
				$items[] = ['cle' => 'reinscription', 'icone' => 'bi-check2-circle', 'ton' => 'info',
					'titre' => tdbPluriel($caisse + $dossier, 'réinscription', 'réinscriptions') . ' à autoriser',
					'nombre' => $caisse + $dossier,
					'detail' => implode(' · ', $parts) . ' — ' . $s['prets'] . ' déjà prêts',
					'lien' => $app_base . '/finance/authorized-reinscription'];
			}
		}
	}

	if ($ctx['inscription']) {
		/* Dossiers d'inscription de l'année pas encore complets, selon la barre
		   du dossier (tdbGuichet) : le bloc « Dossiers au guichet » les détaille */
		$g = tdbGuichet($dtb, $annee);
		$enCours = $g ? $g['total'] - $g['complets'] : 0;
		if ($enCours > 0) {
			$items[] = ['cle' => 'dossiers', 'icone' => 'bi-folder2-open', 'ton' => 'info',
				'titre' => tdbPluriel($enCours, 'dossier d\'inscription', 'dossiers d\'inscription') . ' en cours',
				'nombre' => $enCours,
				'detail' => $court($annee) . ' · ' . $g['complets'] . ' complet' . ($g['complets'] > 1 ? 's' : '') . ' sur ' . $g['total'],
				'lien' => $app_base . '/inscription'];
		}
	}

	if ($ctx['registrariat']) {
		/* Notes encore « non notées » de l'année écoulée : pendant l'année en
		   cours, une note manque normalement tant que l'examen n'a pas eu lieu. */
		$nn = tdbLire(function () use ($dtb, $ecoulee) {
			$st = $dtb->prepare("SELECT n.id_cours, COUNT(*) nn, c.Sigle, c.sigle_affiche, c.title, t.name, t.lastName
				FROM t_2023_notes n
				LEFT JOIN t_2023_cours c ON c.id = n.id_cours
				LEFT JOIN teacher t ON t.uid = c.id_teacher
				WHERE n.`remove` = 0 AND " . TDB_NON_NOTEE . " AND n.annee_scolaire = ?" . perimetreSqlNiveau('n.yearlevel') . '
				GROUP BY n.id_cours, c.Sigle, c.sigle_affiche, c.title, t.name, t.lastName
				ORDER BY nn DESC');
			$st->execute([$ecoulee]);
			return $st->fetchAll(PDO::FETCH_ASSOC);
		}, 'notes non saisies');
		if ($nn) {
			$total = array_sum(array_column($nn, 'nn'));
			$liste = [];
			foreach (array_slice($nn, 0, 8) as $c) {
				$prof = trim(ucfirst(mb_strtolower((string) $c['name'])) . ' ' . (string) $c['lastName']);
				$liste[] = [
					'texte' => coursSigleAffiche($c) . ' · ' . trim((string) $c['title']) . ($prof !== '' ? ' — ' . $prof : ''),
					'lien' => $c['id_cours'] ? $app_base . '/cours?id=' . (int) $c['id_cours'] . '&page=notes' : '',
					'valeur' => (string) (int) $c['nn'],
				];
			}
			$items[] = ['cle' => 'notes', 'icone' => 'bi-pencil-square', 'ton' => 'attention',
				'titre' => tdbPluriel($total, 'note non saisie', 'notes non saisies') . ' en ' . $court($ecoulee),
				'nombre' => $total,
				'detail' => tdbPluriel(count($nn), 'cours concerné', 'cours concernés') . ($nn && count($nn) > 8 ? ' · les 8 plus en retard ci-dessous' : ''),
				'lien' => '', 'liste' => $liste];
		}

		/* Cours offerts cette année sans enseignant (ou avec un enseignant supprimé) */
		$sans = tdbLire(function () use ($dtb, $annee) {
			$st = $dtb->prepare("SELECT DISTINCT c.id, c.Sigle, c.sigle_affiche, c.title, cs.session_label
				FROM t_2023_course_sessions cs
				JOIN t_2023_cours c ON c.id = cs.course_id
				LEFT JOIN teacher t ON t.uid = c.id_teacher
				WHERE cs.academic_year = ? AND cs.offered = 1 AND c.`remove` != 1
				  AND (t.uid IS NULL OR t.`remove` = 1)" . perimetreSqlNiveau('c.yearlevel') . '
				ORDER BY cs.session_label, c.Sigle');
			$st->execute([$annee]);
			return $st->fetchAll(PDO::FETCH_ASSOC);
		}, 'cours sans enseignant');
		if ($sans) {
			$items[] = ['cle' => 'sans-enseignant', 'icone' => 'bi-person-video3', 'ton' => 'info',
				'titre' => tdbPluriel(count($sans), 'cours offert', 'cours offerts') . ' sans enseignant',
				'nombre' => count($sans), 'detail' => $court($annee), 'lien' => $app_base . '/courses-sessions',
				'liste' => array_map(fn ($c) => ['texte' => coursSigleAffiche($c) . ' · ' . trim((string) $c['title']) . ' (' . $c['session_label'] . ')',
					'lien' => $app_base . '/cours?id=' . (int) $c['id'] . '&page=information', 'valeur' => ''], array_slice($sans, 0, 8))];
		}

		/* Deux cours actifs montrés sous le même sigle */
		$doubles = tdbLire(function () use ($dtb) {
			return $dtb->query("SELECT UPPER(TRIM(REGEXP_REPLACE(COALESCE(NULLIF(TRIM(sigle_affiche), ''), Sigle), '[[:space:]]+', ' '))) AS code,
					GROUP_CONCAT(id ORDER BY id) ids
				FROM t_2023_cours WHERE `remove` != 1" . perimetreSqlNiveau('yearlevel') . '
				GROUP BY code HAVING COUNT(*) > 1 ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
		}, 'sigles en double');
		if ($doubles) {
			$items[] = ['cle' => 'sigles', 'icone' => 'bi-files', 'ton' => 'info',
				'titre' => tdbPluriel(count($doubles), 'sigle affiché', 'sigles affichés') . ' par deux cours',
				'nombre' => count($doubles), 'detail' => 'renommez l\'un des cours pour les distinguer', 'lien' => '',
				'liste' => array_map(function ($d) use ($app_base) {
					$ids = explode(',', $d['ids']);
					return ['texte' => $d['code'] . ' · cours ' . implode(', ', $ids),
						'lien' => $app_base . '/cours?id=' . (int) $ids[0] . '&page=information', 'valeur' => (string) count($ids)];
				}, $doubles)];
		}
	}

	if ($ctx['superadmin']) {
		/* File d'attente restée ouverte bien après sa journée */
		$f = tdbLire(fn () => $dtb->query("SELECT session_name, session_date FROM t_queue_sessions
			WHERE status = 'active' AND session_date < CURDATE() - INTERVAL 2 DAY ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC), 'file');
		if ($f) {
			$items[] = ['cle' => 'file', 'icone' => 'bi-people', 'ton' => 'info',
				'titre' => 'File d\'attente toujours ouverte', 'nombre' => 1,
				'detail' => '« ' . $f['session_name'] . ' », ouverte le ' . tdbDate((string) $f['session_date']) . ' : fermez-la si la journée est terminée',
				'lien' => $app_base . '/queue'];
		}
	}

	/* Le plus pressant d'abord, puis le plus nombreux */
	usort($items, fn ($a, $b) => [$a['ton'] !== 'attention', -$a['nombre']] <=> [$b['ton'] !== 'attention', -$b['nombre']]);
	return $items;
}

/* ========================================================== CHIFFRES CLÉS == */

/**
 * Inscriptions et candidatures d'une année scolaire, comparées à l'année
 * précédente.
 *
 * Un étudiant compte une fois par année, sur sa DERNIÈRE inscription de
 * l'année (le second semestre s'il s'est inscrit aux deux) : chaque
 * répartition — mention, cycle, sexe, hébergement — totalise ainsi exactement
 * le nombre d'inscrits.
 *
 * Pas de répartition par année d'études : niveau_std vaut 0 par défaut dans
 * les anciennes données (412 des 424 inscrits du premier semestre 2024-2025,
 * qui suivaient pourtant des cours de L1 à L3). Seuls 4 et 5 (master) sont
 * fiables ; le reste est compté en licence.
 */
function tdbChiffres(PDO $dtb, array $ctx, string $annee): array
{
	$prec = tdbAnneePrecedente($annee);
	$perim = perimetreSqlNiveau('i.niveau_std');
	$res = ['annee' => $annee, 'precedente' => $prec, 'courante' => $annee === inscAnneeCourante()];

	/* Dernière inscription de chaque étudiant pour les deux années */
	$dernieres = 'SELECT MAX(i.id) id FROM ' . TDB_INSCRIPTIONS . ' i
		WHERE i.`remove` = 0 AND i.annee_effective IN (?, ?) GROUP BY i.annee_effective, i.student_id';
	$inscriptions = TDB_INSCRIPTIONS;
	/* Fiches étudiantes dédoublonnées (quelques identifiants y sont en double) */
	$fiches = 'SELECT student_id, MIN(sex) sex FROM tbl_2024_etudiant GROUP BY student_id';

	$vide = ['inscrits' => 0, 'nouveaux' => 0, 'licence' => 0, 'master' => 0, 'femmes' => 0, 'hommes' => 0,
	         'interne' => 0, 'bungalow' => 0, 'externe' => 0];
	$totaux = tdbLire(function () use ($dtb, $annee, $prec, $perim, $dernieres, $fiches, $inscriptions) {
		$st = $dtb->prepare("SELECT i.annee_effective a,
				COUNT(*) inscrits,
				SUM(i.new_student = 1) nouveaux,
				SUM(i.niveau_std IN ('4', '5')) master,
				SUM(NOT (i.niveau_std IN ('4', '5')) OR i.niveau_std IS NULL) licence,
				SUM(e.sex = '0') femmes,
				SUM(e.sex = '1') hommes,
				SUM(i.status = 'Interne') interne,
				SUM(i.status = 'Bungalow') bungalow,
				SUM(i.status IS NULL OR i.status NOT IN ('Interne', 'Bungalow')) externe
			FROM $inscriptions i
			JOIN ($dernieres) d ON d.id = i.id
			LEFT JOIN ($fiches) e ON e.student_id = i.student_id
			WHERE 1 = 1$perim
			GROUP BY i.annee_effective");
		$st->execute([$annee, $prec]);
		$t = [];
		foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $t[trim($r['a'])] = array_map('intval', $r); }
		return $t;
	}, 'totaux') ?? [];
	$res['actuel'] = array_intersect_key(($totaux[$annee] ?? []) + $vide, $vide);
	$res['avant'] = array_intersect_key(($totaux[$prec] ?? []) + $vide, $vide);

	/* Pendant la rentrée, la comparaison qui compte est « à la même date » */
	$res['meme_date'] = null;
	if ($res['courante']) {
		$res['meme_date'] = tdbLire(function () use ($dtb, $prec, $perim, $inscriptions) {
			$st = $dtb->prepare("SELECT COUNT(DISTINCT i.student_id) FROM $inscriptions i
				WHERE i.`remove` = 0 AND i.annee_effective = ? AND i.date_entry <= CURDATE() - INTERVAL 1 YEAR$perim");
			$st->execute([$prec]);
			return (int) $st->fetchColumn();
		}, 'meme date');
	}

	/* Par mention, avec le libellé des filières */
	$res['mentions'] = tdbLire(function () use ($dtb, $annee, $prec, $perim, $dernieres, $inscriptions) {
		$libelles = $dtb->query('SELECT filiere_sigle, filiere_description FROM filiere')->fetchAll(PDO::FETCH_KEY_PAIR);
		$st = $dtb->prepare("SELECT i.annee_effective a, UPPER(TRIM(COALESCE(i.etude_mention, ''))) m, COUNT(*) n
			FROM $inscriptions i
			JOIN ($dernieres) d ON d.id = i.id
			WHERE 1 = 1$perim
			GROUP BY i.annee_effective, m");
		$st->execute([$annee, $prec]);
		$m = [];
		foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
			$code = $r['m'] !== '' ? $r['m'] : '?';
			$m[$code] = $m[$code] ?? ['code' => $code, 'libelle' => $libelles[$code] ?? ($code === '?' ? 'Mention non renseignée' : $code), 'n' => 0, 'avant' => 0];
			$m[$code][trim($r['a']) === $annee ? 'n' : 'avant'] = (int) $r['n'];
		}
		uasort($m, fn ($x, $y) => [$y['n'], $y['avant']] <=> [$x['n'], $x['avant']]);
		return array_values($m);
	}, 'mentions') ?? [];

	/* Candidatures reçues sur l'année scolaire (1er juillet - 30 juin) */
	$res['candidatures'] = null;
	if ($ctx['registrariat']) {
		$res['candidatures'] = tdbLire(function () use ($dtb, $annee) {
			$d = (int) substr($annee, 0, 4);
			$st = $dtb->prepare("SELECT
					COALESCE(SUM(date_creation >= ? AND date_creation < ?), 0) recues,
					COALESCE(SUM(date_creation >= ? AND date_creation < ? AND statut = 'accepte'), 0) acceptees,
					COALESCE(SUM(date_creation >= ? AND date_creation < ?), 0) avant
				FROM candidat");
			$st->execute([$d . '-07-01', ($d + 1) . '-07-01', $d . '-07-01', ($d + 1) . '-07-01', ($d - 1) . '-07-01', $d . '-07-01']);
			return array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);
		}, 'candidatures annee');
	}

	return $res;
}

/* ===================================================== RENTRÉE EN DIRECT == */

/**
 * Étudiants inscrits cumulés jour après jour depuis le 1er juillet, pour
 * l'année choisie et la précédente. Un étudiant entre dans la courbe le jour
 * de sa première inscription de l'année (rentrée, puis second semestre).
 *
 * @return array{jours:int, serie:list<int>, serie_prec:list<int>, limite:?int, max:int,
 *               total:int, total_prec:int, au_jour_prec:?int, debut:string}
 */
function tdbCourbe(PDO $dtb, string $annee): array
{
	$prec = tdbAnneePrecedente($annee);
	$debut = (int) substr($annee, 0, 4) . '-07-01';
	$debutPrec = ((int) substr($annee, 0, 4) - 1) . '-07-01';
	$jours = 365;

	$lignes = tdbLire(function () use ($dtb, $annee, $prec) {
		$st = $dtb->prepare('SELECT i.annee_effective a, MIN(i.date_entry) premiere
			FROM ' . TDB_INSCRIPTIONS . ' i
			WHERE i.`remove` = 0 AND i.annee_effective IN (?, ?) AND i.date_entry IS NOT NULL' . perimetreSqlNiveau('i.niveau_std') . '
			GROUP BY i.annee_effective, i.student_id');
		$st->execute([$annee, $prec]);
		return $st->fetchAll(PDO::FETCH_ASSOC);
	}, 'courbe') ?? [];

	$parJour = [$annee => array_fill(0, $jours, 0), $prec => array_fill(0, $jours, 0)];
	foreach ($lignes as $l) {
		$a = trim((string) $l['a']);
		$origine = $a === $annee ? $debut : $debutPrec;
		$j = (int) floor((strtotime((string) $l['premiere']) - strtotime($origine)) / 86400);
		$parJour[$a][max(0, min($jours - 1, $j))]++;   // dates hors de l'année ramenées aux bords
	}
	$cumuler = function (array $h): array {
		$s = 0;
		return array_map(function ($n) use (&$s) { return $s += $n; }, $h);
	};
	$serie = $cumuler($parJour[$annee]);
	$seriePrec = $cumuler($parJour[$prec]);

	/* Année en cours : la courbe s'arrête à aujourd'hui */
	$limite = null;
	if ($annee === inscAnneeCourante()) {
		$limite = max(0, min($jours - 1, (int) floor((strtotime(date('Y-m-d')) - strtotime($debut)) / 86400)));
		$serie = array_slice($serie, 0, $limite + 1);
	}

	/* La vue « jour par jour » : six semaines. Jusqu'à aujourd'hui pendant
	   l'année en cours ; sinon autour du démarrage de la rentrée, premier
	   jour où cinq étudiants au moins se sont inscrits. */
	$largeur = 42;
	if ($limite !== null) {
		$fenetre = max(0, $limite - $largeur + 1);
	} else {
		$fenetre = 0;
		foreach ($parJour[$annee] as $j => $n) { if ($n >= 5) { $fenetre = max(0, $j - 3); break; } }
		$fenetre = min($fenetre, $jours - $largeur);
	}

	return [
		'jours' => $jours, 'debut' => $debut, 'serie' => $serie, 'serie_prec' => $seriePrec, 'limite' => $limite,
		'max' => max(1, end($seriePrec) ?: 0, $serie ? end($serie) : 0),
		'total' => $serie ? (int) end($serie) : 0, 'total_prec' => (int) end($seriePrec),
		'au_jour_prec' => $limite !== null ? $seriePrec[$limite] : null,
		'fenetre' => [$fenetre, $largeur],
	];
}

/** Le rythme des jours récents (année en cours) : aujourd'hui et sept jours, comparés à l'an dernier aux mêmes dates. */
function tdbRythme(array $courbe): ?array
{
	$l = $courbe['limite'];
	if ($l === null) { return null; }
	$s = $courbe['serie'];
	$p = $courbe['serie_prec'];
	$sur = fn (array $x, int $fin, int $n) => (int) $x[$fin] - ($fin - $n >= 0 ? (int) $x[$fin - $n] : 0);
	return [
		'jour' => $sur($s, $l, 1), 'jour_prec' => $sur($p, $l, 1),
		'semaine' => $sur($s, $l, 7), 'semaine_prec' => $sur($p, $l, 7),
		'cumul' => (int) $s[$l], 'cumul_prec' => (int) $p[$l],
	];
}

/* ===================================================== DOSSIERS AU GUICHET == */

/* Les six étapes de la barre du dossier, dans l'ordre du guichet. */
const TDB_ETAPES_GUICHET = [
	['Informations enregistrées', 'session enregistrée, frais calculés'],
	['Informations vérifiées', 'contrôle par le registrariat'],
	['Cours ajoutés', 'cours de la session'],
	['Mode de paiement', 'tranche choisie'],
	['Impression, signatures', 'fiche imprimée et signée'],
	['Dépôt de la liste', 'liste déposée au registrariat'],
];

/**
 * Les dossiers d'inscription d'une année, chacun à son étape courante : la
 * première qui reste à faire. Même règle que la barre du dossier
 * (inscription/stages/top.stages.php) : les cours et le mode de paiement se
 * lisent dans les données (cours pris, ligne de frais portant une tranche) ;
 * les quatre autres étapes, gestes humains, sur leur case.
 *
 * Les cases étaient mal tenues avant la refonte du guichet (518 des 903
 * dossiers de 2025-2026 n'ont que la première) : le tableau de bord ne montre
 * ce bloc que pour l'année en cours.
 *
 * « dossiers » : ceux qui attendent a l'etape (les plus anciens d'abord) ;
 * « faits » : ceux qui l'ont passee (les derniers venus d'abord). Trente au plus.
 *
 * @return array{total:int, complets:int, etapes:list<array{nom:string, aide:string, faites:int, attente:int, dossiers:list<array{matricule:string, nom:string, depuis:string}>, faits:list<array{matricule:string, nom:string, depuis:string}>}>}|null
 */
function tdbGuichet(PDO $dtb, string $annee): ?array
{
	/* Lu une fois par page : le compteur « À traiter » et le bloc s'en servent */
	static $memo = [];
	if (array_key_exists($annee, $memo)) { return $memo[$annee]; }
	$parEtape = 30;
	$lignes = tdbLire(function () use ($dtb, $annee) {
		$st = $dtb->prepare("SELECT i.student_id, i.date_entry,
				COALESCE(i.data_completion, 0) = 1 e1,
				COALESCE(i.data_verification, 0) = 1 e2,
				EXISTS (SELECT 1 FROM t_2023_notes n WHERE n.student_id = i.student_id AND n.session_id = i.session_id AND n.`remove` != 1) e3,
				EXISTS (SELECT 1 FROM t_2024_etudiant_finace f WHERE f.student_id = i.student_id AND f.session_id = i.session_id
					AND f.`remove` != 1 AND f.mode_payement IS NOT NULL AND TRIM(f.mode_payement) <> '') e4,
				COALESCE(i.verification_signatures, 0) = 1 e5,
				COALESCE(i.depot_list, 0) = 1 e6,
				e.nom, e.prenom
			FROM " . TDB_INSCRIPTIONS . " i
			LEFT JOIN (SELECT student_id, MIN(student_nom) nom, MIN(student_prenom) prenom FROM tbl_2024_etudiant GROUP BY student_id) e
				ON e.student_id = i.student_id
			WHERE i.`remove` = 0 AND i.annee_effective = ?" . perimetreSqlNiveau('i.niveau_std') . '
			ORDER BY i.date_entry, i.id');
		$st->execute([$annee]);
		return $st->fetchAll(PDO::FETCH_ASSOC);
	}, 'guichet');
	if ($lignes === null) { return $memo[$annee] = null; }

	$etapes = array_map(fn ($e) => ['nom' => $e[0], 'aide' => $e[1], 'faites' => 0, 'attente' => 0, 'dossiers' => [], 'faits' => []], TDB_ETAPES_GUICHET);
	$complets = 0;
	/* Les dossiers sont lus du plus ancien au plus recent : « faits » garde les
	   derniers venus (bulle de chaque etape, src/tableau-de-bord.php). */
	$faits = array_fill(0, 6, []);
	foreach ($lignes as $l) {
		$dossier = [
			'matricule' => (string) $l['student_id'],
			'nom' => trim(mb_strtoupper((string) $l['nom']) . ' ' . (string) $l['prenom']),
			'depuis' => (string) $l['date_entry'],
		];
		$courante = null;
		for ($k = 0; $k < 6; $k++) {
			if ((int) $l['e' . ($k + 1)] === 1) {
				$etapes[$k]['faites']++;
				$faits[$k][] = $dossier;
				if (count($faits[$k]) > $parEtape) { array_shift($faits[$k]); }
			}
			elseif ($courante === null) { $courante = $k; }
		}
		if ($courante === null) { $complets++; continue; }
		$etapes[$courante]['attente']++;
		if (count($etapes[$courante]['dossiers']) < $parEtape) { $etapes[$courante]['dossiers'][] = $dossier; }
	}
	foreach ($faits as $k => $liste) { $etapes[$k]['faits'] = array_reverse($liste); }   // le plus recent d'abord
	return $memo[$annee] = ['total' => count($lignes), 'complets' => $complets, 'etapes' => $etapes];
}

/* ========================================== DES CANDIDATURES AUX INSCRIPTIONS == */

/**
 * Les candidatures reçues pour une année scolaire (1er juillet - 30 juin),
 * jusqu'à l'inscription : acceptées, fiche étudiante créée (« converti »),
 * puis inscrites dans l'année. Avec ceux qui attendent l'étape suivante.
 *
 * @return array{recues:int, acceptees:int, fiches:int, inscrites:int, sans_fiche:list<array>, sans_inscription:list<array>}|null
 */
function tdbAdmissions(PDO $dtb, string $annee, int $parListe = 20): ?array
{
	$d = (int) substr($annee, 0, 4);
	$bornes = [$d . '-07-01', ($d + 1) . '-07-01'];
	return tdbLire(function () use ($dtb, $annee, $bornes, $parListe) {
		$inscrite/* Les deux colonnes n'ont pas la même collation : la comparaison la fixe */
		 = 'EXISTS (SELECT 1 FROM ' . TDB_INSCRIPTIONS . ' i WHERE i.student_id = c.student_id COLLATE utf8mb4_general_ci AND i.annee_effective = ? AND i.`remove` = 0)';
		$fiche = "c.statut = 'converti' AND c.student_id IS NOT NULL AND c.student_id <> ''";
		$st = $dtb->prepare("SELECT COUNT(*) recues,
				COALESCE(SUM(c.statut IN ('accepte', 'converti')), 0) acceptees,
				COALESCE(SUM($fiche), 0) fiches,
				COALESCE(SUM($fiche AND $inscrite), 0) inscrites
			FROM candidat c WHERE c.date_creation >= ? AND c.date_creation < ?");
		$st->execute([$annee, $bornes[0], $bornes[1]]);
		$r = array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);

		$st = $dtb->prepare("SELECT c.id, c.nom_complet, c.mention_libelle, c.date_traitement
			FROM candidat c WHERE c.statut = 'accepte' AND c.date_creation >= ? AND c.date_creation < ?
			ORDER BY c.date_traitement, c.id LIMIT $parListe");
		$st->execute($bornes);
		$r['sans_fiche'] = $st->fetchAll(PDO::FETCH_ASSOC);

		$st = $dtb->prepare("SELECT c.student_id, c.nom_complet, c.mention_libelle, c.date_traitement
			FROM candidat c WHERE $fiche AND NOT $inscrite AND c.date_creation >= ? AND c.date_creation < ?
			ORDER BY c.date_traitement, c.id LIMIT $parListe");
		$st->execute([$annee, $bornes[0], $bornes[1]]);
		$r['sans_inscription'] = $st->fetchAll(PDO::FETCH_ASSOC);
		return $r;
	}, 'admissions');
}

/* ======================================================= SAISIE DES NOTES == */

/**
 * Avancement de la saisie des notes d'une année terminée, par mention (celle
 * du cours). Réservé au registrariat, comme les pages de notes.
 *
 * @return array{annee:string, total:int, saisies:int, mentions:list<array{libelle:string,total:int,saisies:int}>}|null
 */
function tdbSaisieNotes(PDO $dtb, string $annee): ?array
{
	$lignes = tdbLire(function () use ($dtb, $annee) {
		$libelles = $dtb->query('SELECT filiere_sigle, filiere_description FROM filiere')->fetchAll(PDO::FETCH_KEY_PAIR);
		$st = $dtb->prepare('SELECT UPPER(TRIM(COALESCE(c.dep_desc, \'\'))) m, COUNT(*) total, SUM(' . TDB_NON_NOTEE . ') manquantes
			FROM t_2023_notes n LEFT JOIN t_2023_cours c ON c.id = n.id_cours
			WHERE n.`remove` = 0 AND n.annee_scolaire = ?' . perimetreSqlNiveau('n.yearlevel') . '
			GROUP BY m');
		$st->execute([$annee]);
		return array_map(fn ($r) => ['libelle' => $libelles[$r['m']] ?? ($r['m'] !== '' ? $r['m'] : 'Cours introuvable'),
			'total' => (int) $r['total'], 'saisies' => (int) $r['total'] - (int) $r['manquantes']], $st->fetchAll(PDO::FETCH_ASSOC));
	}, 'saisie des notes');
	if (!$lignes) { return null; }
	/* Les plus en retard d'abord */
	usort($lignes, fn ($a, $b) => ($a['saisies'] / max(1, $a['total'])) <=> ($b['saisies'] / max(1, $b['total'])));
	return ['annee' => $annee, 'total' => array_sum(array_column($lignes, 'total')),
		'saisies' => array_sum(array_column($lignes, 'saisies')), 'mentions' => $lignes];
}

/* ================================================================ ACTIVITÉ == */

/**
 * Qui utilise l'application, d'après le journal (t_audit_requete) : comptes
 * actifs jour après jour sur 14 jours, en trois groupes (personnel,
 * enseignants, étudiants), et les signaux de la semaine (enregistrements,
 * échecs de connexion, erreurs serveur). Réservé au superadmin, comme le
 * journal lui-même (AdminController::logsSettings).
 *
 * Un compte est actif un jour s'il y a fait au moins une requête ; le
 * journal tient l'heure de Madagascar, comme la connexion de l'application.
 */
function tdbActivite(PDO $dtb): ?array
{
	return tdbLire(function () use ($dtb) {
		$groupe = "CASE WHEN user_niveau = 8 THEN 'etudiants' WHEN user_niveau = 7 THEN 'enseignants' ELSE 'personnel' END";
		$compte = 'user_id IS NOT NULL AND user_id > 0';
		$jour = fn (int $decalage) => date('Y-m-d', strtotime(($decalage >= 0 ? '+' : '') . $decalage . ' day'));

		$jours = [];
		for ($k = -13; $k <= 0; $k++) {
			$jours[$jour($k)] = ['date' => $jour($k), 'personnel' => 0, 'enseignants' => 0, 'etudiants' => 0, 'requetes' => 0, 'ecritures' => 0];
		}
		$st = $dtb->prepare("SELECT DATE(date_action) j, $groupe g, COUNT(DISTINCT user_id) n
			FROM t_audit_requete WHERE date_action >= ? AND $compte GROUP BY j, g");
		$st->execute([$jour(-13)]);
		foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
			if (isset($jours[$r['j']])) { $jours[$r['j']][$r['g']] = (int) $r['n']; }
		}
		$st = $dtb->prepare('SELECT DATE(date_action) j, COUNT(*) requetes, COALESCE(SUM(nb_modifications > 0), 0) ecritures
			FROM t_audit_requete WHERE date_action >= ? GROUP BY j');
		$st->execute([$jour(-13)]);
		foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
			if (isset($jours[$r['j']])) { $jours[$r['j']]['requetes'] = (int) $r['requetes']; $jours[$r['j']]['ecritures'] = (int) $r['ecritures']; }
		}

		/* Deux semaines : les comptes distincts de chacune, par groupe */
		$semaine = function (string $du, string $au) use ($dtb, $groupe, $compte): array {
			$st = $dtb->prepare("SELECT $groupe g, COUNT(DISTINCT user_id) n FROM t_audit_requete
				WHERE date_action >= ? AND date_action < ? AND $compte GROUP BY g");
			$st->execute([$du, $au]);
			$s = ['personnel' => 0, 'enseignants' => 0, 'etudiants' => 0];
			foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $g => $n) { $s[$g] = (int) $n; }
			$st = $dtb->prepare("SELECT COUNT(DISTINCT user_id) FROM t_audit_requete WHERE date_action >= ? AND date_action < ? AND $compte");
			$st->execute([$du, $au]);
			return $s + ['total' => (int) $st->fetchColumn()];
		};

		$st = $dtb->prepare("SELECT COALESCE(SUM(nb_modifications > 0), 0) ecritures, COALESCE(SUM(type_action = 'echec_connexion'), 0) echecs,
				COALESCE(SUM(statut_http >= 500), 0) erreurs
			FROM t_audit_requete WHERE date_action >= ? AND date_action < ?");
		$st->execute([$jour(-6), $jour(1)]);
		$signaux = array_map('intval', $st->fetch(PDO::FETCH_ASSOC));
		$st->execute([$jour(-13), $jour(-6)]);
		$signauxAvant = array_map('intval', $st->fetch(PDO::FETCH_ASSOC));

		return [
			'jours' => array_values($jours),
			'semaine' => $semaine($jour(-6), $jour(1)),
			'semaine_avant' => $semaine($jour(-13), $jour(-6)),
			'maintenant' => (int) $dtb->query("SELECT COUNT(DISTINCT user_id) FROM t_audit_requete
				WHERE date_action >= NOW() - INTERVAL 15 MINUTE AND $compte")->fetchColumn(),
			'signaux' => $signaux,
			'signaux_avant' => $signauxAvant,
			'depuis' => (string) $dtb->query('SELECT DATE(MIN(date_action)) FROM t_audit_requete')->fetchColumn(),
			'du' => $jour(-6),
		];
	}, 'activite');
}

/* ========================================================= ACCÈS RAPIDES == */

/** Raccourcis vers les pages les plus utilisées, selon les droits du compte. */
function tdbAccesRapides(array $ctx, string $app_base): array
{
	$n = $ctx['niveau'];
	$liens = [
		[$n <= 3, 'bi-person-plus', 'Créer un étudiant', '/students/create'],
		[$ctx['inscription'], 'bi-pencil-square', 'Inscription', '/inscription'],
		[$ctx['registrariat'], 'bi-inbox', 'Candidatures', '/candidatures'],
		[$ctx['registrariat'], 'bi-clipboard2-check', 'Remises de notes', '/remise-notes'],
		[$ctx['reinscription'], 'bi-check2-circle', 'Réinscriptions', '/finance/authorized-reinscription'],
		[true, 'bi-people', 'Liste des étudiants', '/etudiants'],
		/* Les exports n'ont pas de page : ils sont dans le menu « Exports » de la
		   barre d'outils de la liste, qui s'ouvre seul avec ?exports=1
		   (init/toolbar.php). Niveaux 1 a 3, comme ExportController. */
		[$n <= 3, 'bi-download', 'Exports', '/etudiants?exports=1'],
		[true, 'bi-calendar3-week', 'Emplois du temps', '/schedule'],
		[$n <= 3, 'bi-cash-coin', 'Finance', '/finance'],
	];
	$retenus = [];
	foreach ($liens as [$permis, $icone, $libelle, $chemin]) {
		if ($permis) { $retenus[] = ['icone' => $icone, 'libelle' => $libelle, 'lien' => $app_base . $chemin]; }
	}
	return array_slice($retenus, 0, 7);
}

/* ================================================================ OUTILS == */

function tdbPluriel(int $n, string $singulier, string $pluriel): string
{
	return number_format($n, 0, ',', "\u{00A0}") . ' ' . ($n > 1 ? $pluriel : $singulier);
}

function tdbDate(string $date): string
{
	static $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
	$t = strtotime($date);
	return $t ? (int) date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : $date;
}

function tdbDepuis(string $date): string
{
	$jours = (int) floor((time() - strtotime($date)) / 86400);
	return $jours <= 0 ? 'aujourd\'hui' : ($jours === 1 ? 'hier' : 'il y a ' . $jours . ' jours');
}

/** « Samedi 4 octobre 2026 » */
function tdbDateDuJour(): string
{
	static $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
	return ucfirst($jours[(int) date('w')]) . ' ' . tdbDate(date('Y-m-d'));
}

/** Écart avec l'année précédente, en texte : « +14 par rapport à 2024-2025 ». */
function tdbEcart(int $actuel, int $avant): array
{
	$e = $actuel - $avant;
	return ['valeur' => $e, 'signe' => $e > 0 ? 'hausse' : ($e < 0 ? 'baisse' : 'egal'),
		'texte' => ($e > 0 ? '+' : ($e < 0 ? '−' : '')) . number_format(abs($e), 0, ',', "\u{00A0}")];
}
