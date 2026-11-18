<?php
/*
 * Les trois listes du back-office — etudiants, cours, enseignants — decrites
 * une seule fois : colonnes, recherche, filtres, tris et rendu d une ligne.
 *
 * Avant, chaque liste avait trois fichiers (la page, le tri, la recherche)
 * qui se remplacaient l un l autre : trier effacait le filtre, filtrer
 * effacait la recherche, et un seul filtre a la fois. Ici, l ETAT d une liste
 * reunit tout, et chaque requete l applique en entier :
 *
 *     q        recherche, plusieurs mots (chacun doit se retrouver)
 *     filtres  plusieurs valeurs par filtre (OU), plusieurs filtres (ET)
 *     tri      une colonne et un sens
 *     page     pour « Afficher la suite »
 *
 * Utilise par src/accueil.php, src/accueil.cours.php, src/accueil.prof.php
 * (premier affichage) et init/.listes/liste.php (mises a jour en direct,
 * pilotees par src/js/listes.js).
 *
 * Aucune valeur venue de la requete n entre dans le SQL autrement que liee :
 * les colonnes et les tris viennent de cette description, jamais du client.
 */

if (!function_exists('listesDefinition')) {

/* Perimetre du compte (data/perimetre.php) : il entre dans la condition de
   base des listes d'etudiants et de cours, donc dans les lignes, les
   compteurs, les filtres proposes et la recherche. */
require_once __DIR__ . '/perimetre.php';

if (!defined('LISTES_PAR_PAGE')) { define('LISTES_PAR_PAGE', 200); }

/* ---------------------------------------------------------- libelles */

function listesNiveauLibelle($n): string
{
	if ($n === null || $n === '') { return 'Non renseigné'; }
	$n = (int) $n;
	if ($n === 0) { return 'Remise à niveau'; }
	if ($n <= 3) { return 'Licence ' . $n; }
	return 'Master ' . ($n - 3);
}

function listesCategorieLibelle($c): string
{
	$c = (int) $c;
	return [0 => 'Général', 1 => 'Majeur', 2 => 'Sélectif', -1 => 'Sélectif', 3 => 'Additionnel'][$c] ?? 'Autre';
}

function listesE($v): string
{
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------- definitions */

/**
 * Description d une liste. Un filtre est :
 *   - soit un CHAMP : ses valeurs distinctes deviennent les options ;
 *   - soit des CAS : des options nommees, chacune une condition SQL fixe
 *     (utile quand les donnees sont heterogenes : « Campus UAZ », « UAZ »…).
 */
function listesDefinition(string $nom, ?PDO $dtb = null): ?array
{
	switch ($nom) {

	case 'etudiants':
		$def = [
			'nom'       => 'etudiants',
			'singulier' => 'étudiant',
			'pluriel'   => 'étudiants',
			'from'      => 'tbl_2024_etudiant e',
			'select'    => 'e.*',
			'base'      => 'e.remove != 1' . perimetreSqlEtudiant('e') . perimetreSqlListeMention($dtb, 'e'),
			'cle'       => 'e.id',
			'recherche' => ['e.student_id', 'e.student_nom', 'e.student_prenom', 'e.student_email', 'e.student_tel'],
			'tris' => [
				'recent'    => ['libelle' => 'Plus récents',  'sql' => 'e.id',              'sens' => 'desc'],
				'matricule' => ['libelle' => 'Matricule',     'sql' => 'e.student_id',       'sens' => 'asc'],
				'nom'       => ['libelle' => 'Nom',           'sql' => 'e.student_nom',      'sens' => 'asc', 'puis' => 'e.student_prenom'],
				'prenom'    => ['libelle' => 'Prénom',        'sql' => 'e.student_prenom',   'sens' => 'asc', 'puis' => 'e.student_nom'],
				'mention'   => ['libelle' => 'Mention',       'sql' => 'e.etude_envisage',   'sens' => 'asc', 'puis' => 'e.student_nom'],
				'parcours'  => ['libelle' => 'Parcours',      'sql' => 'e.etude_option',     'sens' => 'asc', 'puis' => 'e.student_nom', 'vides' => true],
				'annee'     => ['libelle' => 'Année scolaire','sql' => 'e.annee_scolaire',   'sens' => 'desc', 'puis' => 'e.student_nom'],
				'niveau'    => ['libelle' => 'Niveau',        'sql' => 'e.annee_etude',      'sens' => 'asc', 'puis' => 'e.student_nom'],
			],
			'triDefaut' => 'recent',
			'colonnes'  => [
				['ID', 'matricule'], ['Nom', 'nom'], ['Prénom', 'prenom'], ['Mention', 'mention'],
				['Parcours', 'parcours'], ['Année', 'annee'], ['Niveau', 'niveau'],
			],
			'filtres' => [
				'mention'  => ['libelle' => 'Mention',  'champ' => 'e.etude_envisage'],
				'parcours' => ['libelle' => 'Parcours', 'champ' => 'e.etude_option', 'apres' => 'mention',
				               'aide' => 'Choisissez d’abord une mention pour voir ses parcours.'],
				'annee'    => ['libelle' => 'Année scolaire', 'champ' => 'e.annee_scolaire', 'ordre' => 'desc'],
				'niveau'   => ['libelle' => 'Niveau',   'champ' => 'e.annee_etude', 'libelles' => 'listesNiveauLibelle', 'ordre' => 'num'],
				'statut'   => ['libelle' => 'Inscription', 'cas' => [
					'nouveau' => ['Nouveaux étudiants', 'e.new_student = 1'],
					'ancien'  => ['Réinscrits',         'COALESCE(e.new_student, 0) <> 1'],
				]],
			],
			'ligne' => 'listesLigneEtudiant',
		];
		/* Chef de mention : sa mention seulement (data/perimetre.php). Le
		   filtre Mention n'aurait qu'une valeur ; le Parcours s'ouvre donc
		   sans attendre qu'on la choisisse. */
		$mention = perimetreMention();
		if ($mention !== false) {
			unset($def['filtres']['mention'], $def['filtres']['parcours']['apres'], $def['filtres']['parcours']['aide']);
			$def['avis'] = $mention === null
				? 'Aucune mention n’est attribuée à votre compte : la liste reste vide. Demandez au registrariat d’indiquer votre mention dans votre compte.'
				: 'Étudiants de la mention ' . $mention . '.';
		}
		return $def;

	case 'cours':
		/* Le code affiche (alias) doit exister pour la recherche et le tri. */
		if ($dtb && function_exists('coursSigleAliasInstaller')) { coursSigleAliasInstaller($dtb); }
		/* Libelles des mentions : sigle -> nom, depuis les reglages. */
		$mentions = [];
		if ($dtb) {
			foreach ($dtb->query('SELECT filiere_sigle, filiere_description FROM filiere') as $r) {
				$mentions[(string) $r['filiere_sigle']] = (string) $r['filiere_description'];
			}
		}
		$parcours = ['all' => 'Tronc commun'];
		if ($dtb) {
			foreach ($dtb->query('SELECT shortcode, description FROM filiere_parcours') as $r) {
				$parcours[(string) $r['shortcode']] = (string) $r['description'];
			}
		}
		/* Un enseignant sans nom (la fiche vide d identifiant 0, a laquelle
		   des cours sont rattaches) n en est pas un. */
		$nomEnseignant = "TRIM(CONCAT_WS(' ', CONVERT(t.name USING utf8mb4), CONVERT(t.lastName USING utf8mb4))) COLLATE utf8mb4_general_ci";
		$avecEnseignant = "EXISTS (SELECT 1 FROM teacher t WHERE t.uid = c.id_teacher AND $nomEnseignant <> '')";
		return [
			'nom'       => 'cours',
			'singulier' => 'cours',
			'pluriel'   => 'cours',
			'from'      => 't_2023_cours c',
			/* Parcours et enseignant par sous-requete : un seul aller-retour,
			   au lieu de deux requetes PAR LIGNE auparavant. */
			'select'    => "c.*,
				CASE WHEN c.parcours = 'all' THEN 'Tronc commun'
				     ELSE (SELECT fp.description FROM filiere_parcours fp WHERE fp.shortcode = c.parcours LIMIT 1) END AS parcours_nom,
				(SELECT NULLIF(TRIM(CONCAT(CONVERT(UPPER(t.name) USING utf8mb4), ' ', CONVERT(t.lastName USING utf8mb4))), '') COLLATE utf8mb4_general_ci
				   FROM teacher t WHERE t.uid = c.id_teacher LIMIT 1) AS enseignant",
			'base'      => 'c.remove != 1' . perimetreSqlNiveau('c.yearlevel'),
			'cle'       => 'c.id',
			'recherche' => [
				'c.Sigle', 'c.sigle_affiche', 'c.title', 'c.title_english', 'c.dep_desc',
				/* Le nom de l enseignant aussi : « rakoto » trouve ses cours. */
				"EXISTS (SELECT 1 FROM teacher t WHERE t.uid = c.id_teacher
				   AND CONCAT_WS(' ', CONVERT(t.name USING utf8mb4), CONVERT(t.lastName USING utf8mb4)) COLLATE utf8mb4_general_ci LIKE ?)",
			],
			'tris' => [
				'titre'      => ['libelle' => 'Intitulé',   'sql' => 'c.title',   'sens' => 'asc'],
				'sigle'      => ['libelle' => 'Sigle',      'sql' => "COALESCE(NULLIF(TRIM(c.sigle_affiche), ''), c.Sigle)", 'sens' => 'asc'],
				'mention'    => ['libelle' => 'Mention',    'sql' => 'c.dep_desc', 'sens' => 'asc', 'puis' => 'c.title'],
				'parcours'   => ['libelle' => 'Parcours',   'sql' => 'parcours_nom', 'sens' => 'asc', 'puis' => 'c.title', 'vides' => true],
				'credits'    => ['libelle' => 'Crédits',    'sql' => 'c.nb_crd + 0', 'sens' => 'desc', 'puis' => 'c.title'],
				'categorie'  => ['libelle' => 'Catégorie',  'sql' => 'c.category', 'sens' => 'asc', 'puis' => 'c.title'],
				'niveau'     => ['libelle' => 'Niveau',     'sql' => 'c.yearlevel', 'sens' => 'asc', 'puis' => 'c.semester, c.title'],
				'semestre'   => ['libelle' => 'Semestre',   'sql' => 'c.semester', 'sens' => 'asc', 'puis' => 'c.yearlevel, c.title'],
				/* Par NOM d enseignant (c etait par identifiant), les cours sans
				   enseignant a la fin. */
				'enseignant' => ['libelle' => 'Enseignant', 'sql' => 'enseignant', 'sens' => 'asc', 'puis' => 'c.title', 'vides' => true],
			],
			'triDefaut' => 'titre',
			'colonnes'  => [
				['Sigle', 'sigle'], ['Cours', 'titre'], ['Mention', 'mention'], ['Parcours', 'parcours'],
				['Crédit', 'credits'], ['Catégorie', 'categorie'], ['Niveau', 'niveau'],
				['Semestre', 'semestre'], ['Enseignant', 'enseignant'],
			],
			'filtres' => [
				'mention'    => ['libelle' => 'Mention', 'champ' => 'c.dep_desc', 'libelles' => $mentions],
				'parcours'   => ['libelle' => 'Parcours', 'champ' => 'c.parcours', 'libelles' => $parcours, 'apres' => 'mention',
				                 'aide' => 'Choisissez d’abord une mention pour voir ses parcours.'],
				'niveau'     => ['libelle' => 'Niveau', 'champ' => 'c.yearlevel', 'libelles' => 'listesNiveauLibelle', 'ordre' => 'num'],
				'semestre'   => ['libelle' => 'Semestre', 'champ' => 'c.semester', 'libelles' => static fn ($v) => 'Semestre ' . $v, 'ordre' => 'num'],
				'categorie'  => ['libelle' => 'Catégorie', 'champ' => 'c.category', 'libelles' => 'listesCategorieLibelle', 'ordre' => 'num'],
				'enseignant' => ['libelle' => 'Enseignant', 'cas' => [
					'avec' => ['Enseignant désigné', $avecEnseignant],
					'sans' => ['Sans enseignant',    'NOT ' . $avecEnseignant],
				]],
			],
			'ligne' => 'listesLigneCours',
		];

	case 'enseignants':
		/* Donnees heterogenes : on regroupe par sens, pas par graphie. Le
		   filtre « Autre » d avant cherchait LIKE '%%' et renvoyait tout le
		   monde ; « adventiste » attrapait aussi « non Adventiste ». */
		$campus = "(t.address LIKE '%campus%' OR t.address LIKE '%UAZ%' OR t.address LIKE '%Zurcher%')";
		$adventiste = "((t.religion LIKE '%advent%' AND t.religion NOT LIKE '%non%') OR t.religion = 'SDA')";
		return [
			'nom'       => 'enseignants',
			'singulier' => 'enseignant',
			'pluriel'   => 'enseignants',
			'from'      => 'teacher t',
			'select'    => 't.*',
			'base'      => 't.remove != 1',
			'cle'       => 't.teacher_id',
			'recherche' => ['t.teacher_id', 't.name', 't.lastName', 't.email', 't.phone'],
			'tris' => [
				'recent'    => ['libelle' => 'Plus récents', 'sql' => 't.teacher_id + 0', 'sens' => 'desc'],
				'id'        => ['libelle' => 'Identifiant',  'sql' => 't.teacher_id + 0', 'sens' => 'asc'],
				'nom'       => ['libelle' => 'Nom',          'sql' => 't.name',     'sens' => 'asc', 'puis' => 't.lastName'],
				'prenom'    => ['libelle' => 'Prénom',       'sql' => 't.lastName', 'sens' => 'asc', 'puis' => 't.name'],
				'adresse'   => ['libelle' => 'Adresse',      'sql' => 't.address',  'sens' => 'asc', 'puis' => 't.name', 'vides' => true],
				'telephone' => ['libelle' => 'Téléphone',    'sql' => 't.phone',    'sens' => 'asc', 'vides' => true],
				'email'     => ['libelle' => 'Email',        'sql' => 't.email',    'sens' => 'asc', 'vides' => true],
			],
			'triDefaut' => 'recent',
			'colonnes'  => [
				['ID', 'id'], ['Nom', 'nom'], ['Prénom', 'prenom'], ['Adresse', 'adresse'],
				['Téléphone', 'telephone'], ['Email', 'email'],
			],
			'filtres' => [
				'lieu' => ['libelle' => 'Lieu', 'cas' => [
					'campus'  => ['Sur le campus', $campus],
					'hors'    => ['Hors campus',   "(TRIM(COALESCE(t.address, '')) <> '' AND NOT $campus)"],
					'inconnu' => ['Adresse non renseignée', "TRIM(COALESCE(t.address, '')) = ''"],
				]],
				'religion' => ['libelle' => 'Religion', 'cas' => [
					'adventiste' => ['Adventiste',        $adventiste],
					'autre'      => ['Non adventiste',    "(TRIM(COALESCE(t.religion, '')) <> '' AND NOT $adventiste)"],
					'inconnu'    => ['Non renseignée',    "TRIM(COALESCE(t.religion, '')) = ''"],
				]],
			],
			'ligne' => 'listesLigneEnseignant',
		];
	}
	return null;
}

/* ------------------------------------------------------------- etat */

/** L etat d une liste, lu depuis la requete et verifie contre la definition. */
function listesEtat(array $def, array $source): array
{
	$q = trim(preg_replace('~\s+~u', ' ', (string) ($source['q'] ?? $source['search'] ?? '')));
	$q = mb_substr($q, 0, 120);

	$filtres = [];
	foreach ($def['filtres'] as $cle => $f) {
		if (!isset($source[$cle])) { continue; }
		$valeurs = is_array($source[$cle]) ? $source[$cle] : [$source[$cle]];
		$valeurs = array_values(array_unique(array_filter(array_map(
			static fn ($v) => mb_substr(trim((string) $v), 0, 120),
			$valeurs
		), static fn ($v) => $v !== '' || !isset($f['cas']))));
		if (isset($f['cas'])) {
			$valeurs = array_values(array_intersect($valeurs, array_keys($f['cas'])));
		}
		if ($valeurs) { $filtres[$cle] = array_slice($valeurs, 0, 40); }
	}

	$tri = (string) ($source['tri'] ?? '');
	if (!isset($def['tris'][$tri])) { $tri = $def['triDefaut']; }
	$sens = strtolower((string) ($source['sens'] ?? ''));
	if ($sens !== 'asc' && $sens !== 'desc') { $sens = $def['tris'][$tri]['sens']; }

	$page = max(0, min(200, (int) ($source['page'] ?? 0)));

	return ['q' => $q, 'filtres' => $filtres, 'tri' => $tri, 'sens' => $sens, 'page' => $page];
}

/* ---------------------------------------------------------- requetes */

/**
 * La clause WHERE et ses parametres. $sauf retire un filtre : c est ainsi
 * qu on compte les options d un filtre sans qu il se restreigne lui-meme.
 */
function listesConditions(array $def, array $etat, ?string $sauf = null): array
{
	$parties = [$def['base']];
	$params = [];

	/* Chaque mot doit se retrouver dans au moins un champ. */
	$mots = $etat['q'] === '' ? [] : array_slice(explode(' ', $etat['q']), 0, 6);
	foreach ($mots as $mot) {
		$ou = [];
		foreach ($def['recherche'] as $champ) {
			$ou[] = strpos($champ, '?') !== false ? $champ : "$champ LIKE ?";
			$params[] = '%' . $mot . '%';
		}
		$parties[] = '(' . implode(' OR ', $ou) . ')';
	}

	foreach ($etat['filtres'] as $cle => $valeurs) {
		if ($cle === $sauf || !isset($def['filtres'][$cle])) { continue; }
		$f = $def['filtres'][$cle];
		if (isset($f['cas'])) {
			$ou = [];
			foreach ($valeurs as $v) { $ou[] = $f['cas'][$v][1]; }
			$parties[] = '(' . implode(' OR ', $ou) . ')';
		} else {
			$parties[] = $f['champ'] . ' IN (' . implode(', ', array_fill(0, count($valeurs), '?')) . ')';
			foreach ($valeurs as $v) { $params[] = $v; }
		}
	}

	return [implode(' AND ', $parties), $params];
}

function listesOrdre(array $def, array $etat): string
{
	$t = $def['tris'][$etat['tri']];
	$sens = strtoupper($etat['sens']);
	$ordre = [];
	/* Les valeurs vides a la fin, quel que soit le sens. */
	if (!empty($t['vides'])) { $ordre[] = "({$t['sql']} IS NULL OR {$t['sql']} = '') ASC"; }
	$ordre[] = "{$t['sql']} $sens";
	if (!empty($t['puis'])) {
		foreach (explode(',', $t['puis']) as $p) { $ordre[] = trim($p) . ' ASC'; }
	}
	$ordre[] = $def['cle'] . ' DESC';   // ordre stable d une page a l autre
	return implode(', ', $ordre);
}

/** Les lignes demandees et le total. */
function listesLignes(PDO $dtb, array $def, array $etat): array
{
	[$where, $params] = listesConditions($def, $etat);

	$st = $dtb->prepare("SELECT COUNT(*) FROM {$def['from']} WHERE $where");
	$st->execute($params);
	$total = (int) $st->fetchColumn();

	$offset = $etat['page'] * LISTES_PAR_PAGE;
	$sql = "SELECT {$def['select']} FROM {$def['from']} WHERE $where ORDER BY " . listesOrdre($def, $etat)
	     . ' LIMIT ' . LISTES_PAR_PAGE . ' OFFSET ' . $offset;
	$st = $dtb->prepare($sql);
	$st->execute($params);

	return [$st->fetchAll(PDO::FETCH_ASSOC), $total];
}

/**
 * Les options de chaque filtre, avec le nombre de lignes qu elles donneraient
 * compte tenu des AUTRES filtres et de la recherche.
 */
function listesFacettes(PDO $dtb, array $def, array $etat): array
{
	$sortie = [];
	foreach ($def['filtres'] as $cle => $f) {
		$actives = $etat['filtres'][$cle] ?? [];
		[$where, $params] = listesConditions($def, $etat, $cle);
		$options = [];

		if (isset($f['cas'])) {
			$sommes = [];
			foreach ($f['cas'] as $v => [$lib, $cond]) { $sommes[] = "SUM(CASE WHEN $cond THEN 1 ELSE 0 END)"; }
			$st = $dtb->prepare('SELECT ' . implode(', ', $sommes) . " FROM {$def['from']} WHERE $where");
			$st->execute($params);
			$n = $st->fetch(PDO::FETCH_NUM) ?: [];
			$i = 0;
			foreach ($f['cas'] as $v => [$lib, $cond]) {
				$options[] = ['v' => (string) $v, 'l' => $lib, 'n' => (int) ($n[$i++] ?? 0), 'on' => in_array($v, $actives, true)];
			}
		} else {
			/* Un filtre qui depend d un autre (parcours apres mention) ne
			   propose rien tant que l autre n est pas choisi : sinon, une
			   longue liste melant toutes les mentions. */
			if (!empty($f['apres']) && empty($etat['filtres'][$f['apres']]) && !$actives) {
				$sortie[$cle] = ['libelle' => $f['libelle'], 'options' => [], 'aide' => $f['aide'] ?? ''];
				continue;
			}
			$st = $dtb->prepare("SELECT {$f['champ']} AS v, COUNT(*) AS n FROM {$def['from']} WHERE $where GROUP BY {$f['champ']}");
			$st->execute($params);
			$vus = [];
			foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
				$v = (string) $r['v'];
				if ($v === '' && ($f['ordre'] ?? '') !== 'num') { continue; }
				$vus[$v] = true;
				$options[] = ['v' => $v, 'l' => listesLibelleOption($f, $v), 'n' => (int) $r['n'], 'on' => in_array($v, $actives, true)];
			}
			/* Une valeur choisie reste proposee meme a zero, pour la retirer. */
			foreach ($actives as $v) {
				if (!isset($vus[$v])) { $options[] = ['v' => $v, 'l' => listesLibelleOption($f, $v), 'n' => 0, 'on' => true]; }
			}
			$ordre = $f['ordre'] ?? 'alpha';
			usort($options, static function ($a, $b) use ($ordre) {
				if ($ordre === 'num') { return (float) $a['v'] <=> (float) $b['v']; }
				if ($ordre === 'desc') { return strcmp($b['v'], $a['v']); }
				return strcmp(listesCleAlpha($a['l']), listesCleAlpha($b['l']));
			});
		}
		$sortie[$cle] = ['libelle' => $f['libelle'], 'options' => $options];
	}
	return $sortie;
}

/** Cle de tri alphabetique sans accents : « Éducation » avant « Gestion ». */
function listesCleAlpha(string $s): string
{
	$s = mb_strtolower($s);
	return strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
		'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe']);
}

function listesLibelleOption(array $f, string $v): string
{
	$l = $f['libelles'] ?? null;
	if (is_array($l)) { return $l[$v] ?? $v; }
	if (is_callable($l)) { return (string) $l($v); }
	return $v;
}

/* ------------------------------------------------------------ rendu */

function listesLigneEtudiant(array $r, string $base): string
{
	$lien = listesE($base . '/student?id=' . $r['id'] . '&page=information');
	$c = static fn (string $label, string $html) => '<td data-label="' . $label . '"><a href="' . $lien . '"><div class="w-full">' . $html . '</div></a></td>';
	$nouveau = ((int) ($r['new_student'] ?? 0) === 1) ? ' <span class="badge nouveau-badge">Nouveau</span>' : '';
	return '<tr class="hover:bg-slate-600 text-slate-100">'
		. '<td data-label="ID" class="col-code"><a href="' . $lien . '"><div class="w-full">' . listesE($r['student_id']) . '</div></a></td>'
		. $c('Nom', listesE(mb_strtoupper((string) $r['student_nom'])) . $nouveau)
		. $c('Prénom', listesE($r['student_prenom']))
		. $c('Mention', listesE($r['etude_envisage']))
		. $c('Parcours', listesE($r['etude_option']))
		. $c('Année', listesE($r['annee_scolaire']))
		. $c('Niveau', listesE(listesNiveauLibelle($r['annee_etude'])))
		. '</tr>';
}

function listesLigneCours(array $r, string $base): string
{
	$lien = listesE($base . '/cours?id=' . $r['id'] . '&page=information');
	$c = static fn (string $label, string $html) => '<td data-label="' . $label . '"><a href="' . $lien . '"><div class="w-full">' . $html . '</div></a></td>';
	$sigle = function_exists('coursSigleAffiche') ? coursSigleAffiche($r) : (string) $r['Sigle'];
	return '<tr class="hover:bg-slate-600 text-slate-100">'
		. '<td data-label="Sigle" class="col-code"><a href="' . $lien . '"><div class="w-full">' . listesE($sigle) . '</div></a></td>'
		. $c('Cours', listesE($r['title']))
		. $c('Mention', listesE($r['dep_desc']))
		. $c('Parcours', listesE($r['parcours_nom'] ?? ''))
		. $c('Crédit', listesE($r['nb_crd']))
		. $c('Catégorie', listesE(listesCategorieLibelle($r['category'])))
		. $c('Niveau', listesE(listesNiveauLibelle($r['yearlevel'])))
		. $c('Semestre', listesE($r['semester']))
		. $c('Enseignant', trim((string) ($r['enseignant'] ?? '')) !== '' ? listesE($r['enseignant']) : '<span class="text-slate-500">—</span>')
		. '</tr>';
}

function listesLigneEnseignant(array $r, string $base): string
{
	$lien = listesE($base . '/professor?id=' . $r['teacher_id'] . '&page=information');
	$c = static fn (string $label, string $html) => '<td data-label="' . $label . '"><a href="' . $lien . '"><div class="w-full">' . $html . '</div></a></td>';
	return '<tr class="hover:bg-slate-600 text-slate-100">'
		. '<td data-label="ID" class="col-code"><a href="' . $lien . '"><div class="w-full">' . listesE($r['teacher_id']) . '</div></a></td>'
		. $c('Nom', listesE(mb_strtoupper((string) $r['name'])))
		. $c('Prénom', listesE($r['lastName']))
		. $c('Adresse', listesE($r['address']))
		. $c('Téléphone', listesE($r['phone']))
		. $c('Email', listesE($r['email']))
		. '</tr>';
}

/** Execute l etat : lignes rendues, total, options des filtres. */
function listesExecuter(PDO $dtb, array $def, array $etat, string $base): array
{
	[$lignes, $total] = listesLignes($dtb, $def, $etat);
	$html = '';
	foreach ($lignes as $r) { $html .= $def['ligne']($r, $base); }
	$affiches = min($total, ($etat['page'] + 1) * LISTES_PAR_PAGE);
	return [
		'total'    => $total,
		'affiches' => $affiches,
		'reste'    => max(0, $total - $affiches),
		'html'     => $html,
		'facettes' => listesFacettes($dtb, $def, $etat),
		'etat'     => $etat,
	];
}

/**
 * Le premier affichage d une liste dans sa page : barre d etat, tableau aux
 * en-tetes triables, bouton « Afficher la suite », et la configuration que
 * src/js/listes.js reprend pour la suite.
 */
function listesAfficher(PDO $dtb, string $nom, array $source, string $base): void
{
	$def = listesDefinition($nom, $dtb);
	$etat = listesEtat($def, $source);
	$etat['page'] = 0;
	try {
		$r = listesExecuter($dtb, $def, $etat, $base);
	} catch (PDOException $e) {
		echo '<div class="liste-erreur"><i class="bi bi-exclamation-triangle"></i> La liste n’a pas pu être chargée. Rechargez la page.</div>';
		return;
	}

	if (!empty($def['avis'])) {
		echo '<p class="liste-avis"><i class="bi bi-info-circle" aria-hidden="true"></i> ' . listesE($def['avis']) . '</p>';
	}
	echo '<div class="liste-barre" id="liste-barre" aria-live="polite"></div>';
	echo '<table class="simpleTbl stack-cards liste-table" id="liste-table" data-liste="' . listesE($nom) . '">';
	echo '<thead><tr>';
	foreach ($def['colonnes'] as [$libelle, $tri]) {
		$actif = $tri === $etat['tri'];
		$ariaSort = $actif ? ($etat['sens'] === 'asc' ? 'ascending' : 'descending') : 'none';
		echo '<th aria-sort="' . $ariaSort . '"><button type="button" class="liste-tri' . ($actif ? ' actif' : '') . '" data-tri="' . listesE($tri) . '"'
			. ' title="Trier par ' . listesE(mb_strtolower($libelle)) . '">'
			. listesE($libelle) . '<i class="bi ' . ($actif ? ($etat['sens'] === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up') . '" aria-hidden="true"></i></button></th>';
	}
	echo '</tr></thead>';
	echo '<tbody id="liste-corps">' . $r['html'] . '</tbody>';
	echo '</table>';
	echo '<div class="liste-suite" id="liste-suite"' . ($r['reste'] ? '' : ' hidden') . '>'
		. '<button type="button" class="liste-suite__bouton" id="liste-suite-bouton"></button></div>';

	$config = [
		'liste'     => $nom,
		'singulier' => $def['singulier'],
		'pluriel'   => $def['pluriel'],
		'parPage'   => LISTES_PAR_PAGE,
		'url'       => $base . '/init/.listes/liste.php',
		'tris'      => array_map(static fn ($t) => ['libelle' => $t['libelle'], 'sens' => $t['sens']], $def['tris']),
		'triDefaut' => $def['triDefaut'],
		'filtres'   => array_map(static fn ($f) => $f['libelle'], $def['filtres']),
		'initial'   => ['total' => $r['total'], 'affiches' => $r['affiches'], 'reste' => $r['reste'], 'facettes' => $r['facettes'], 'etat' => $r['etat']],
	];
	echo '<script type="application/json" id="liste-config">'
		. json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
		. '</script>';
	echo '<script src="' . listesE($base) . '/src/js/listes.js?v=' . @filemtime(__DIR__ . '/../src/js/listes.js') . '" defer></script>';
}

}
