<?php
/* =============================================================================
   Recherche rapide (Ctrl+K) : étudiants, cours, enseignants. JSON, lecture seule.
   GET q=…  — appelé par src/js/recherche.js ; les pages du menu sont
   cherchées dans le navigateur, sans passer par ici.

   Chaque groupe suit la règle de sa liste :
     étudiants    tout le personnel (comme /etudiants), périmètre de cycle compris
     cours        superadmin et registrariat (comme /courses), périmètre compris
     enseignants  superadmin et registrariat (comme /professors)
   Recherche et périmètre viennent de data/listes.php : un nom trouvé ici l'est
   aussi dans la liste, et inversement.
   ========================================================================== */
require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserEtudiant();
refuserEnseignant("La recherche rapide des dossiers est réservée au personnel.");
refuserRequeteCroisee();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Chemin de base des liens (l'appli peut vivre dans un sous-dossier), comme app/.cours/addCours.php
$_dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require_once(__DIR__ . '/../data/listes.php');
require_once(__DIR__ . '/../data/cours.sigle.php');

$q = trim(preg_replace('~\s+~u', ' ', mb_substr((string) ($_GET['q'] ?? ''), 0, 80)));
if (mb_strlen($q) < 2) {
	echo json_encode(['ok' => true, 'q' => $q, 'groupes' => []], JSON_UNESCAPED_UNICODE);
	exit;
}

$registrariat = function_exists('hasAnyPrivilege') && hasAnyPrivilege(['superadmin', 'registrar']);
$limite = 6;

/** Lignes d'une liste (data/listes.php) qui contiennent tous les mots cherchés. */
$chercher = function (string $liste, string $ordre) use ($dtb, $q, $limite): array {
	$def = listesDefinition($liste, $dtb);
	[$where, $params] = listesConditions($def, ['q' => $q, 'filtres' => []]);
	$st = $dtb->prepare('SELECT ' . $def['select'] . ' FROM ' . $def['from'] . ' WHERE ' . $where . ' ORDER BY ' . $ordre . ' LIMIT ' . ($limite + 1));
	$st->execute($params);
	return $st->fetchAll(PDO::FETCH_ASSOC);
};

$groupes = [];
try {
	/* Étudiants : le matricule exact d'abord */
	$lignes = $chercher('etudiants', '(e.student_id = ' . $dtb->quote($q) . ') DESC, e.student_nom, e.student_prenom');
	$plus = count($lignes) > $limite;
	$groupes[] = [
		'cle' => 'etudiants', 'titre' => 'Étudiants', 'icone' => 'bi-person',
		'tout' => $plus ? $app_base . '/etudiants?q=' . rawurlencode($q) : '',
		'resultats' => array_map(fn ($e) => [
			'titre' => trim(mb_strtoupper((string) $e['student_nom']) . ' ' . (string) $e['student_prenom']),
			'detail' => implode(' · ', array_filter([(string) $e['student_id'], (string) $e['etude_envisage'],
				$e['annee_etude'] !== null && $e['annee_etude'] !== '' ? coursNiveauLibelle((int) $e['annee_etude']) : ''])),
			'lien' => $app_base . '/student?id=' . (int) $e['id'] . '&page=information',
		], array_slice($lignes, 0, $limite)),
	];

	if ($registrariat) {
		/* Cours : le sigle exact d'abord */
		$code = "UPPER(COALESCE(NULLIF(TRIM(c.sigle_affiche), ''), c.Sigle))";
		$lignes = $chercher('cours', "($code = " . $dtb->quote(mb_strtoupper($q)) . ') DESC, c.title');
		$plus = count($lignes) > $limite;
		$groupes[] = [
			'cle' => 'cours', 'titre' => 'Cours', 'icone' => 'bi-journal-text',
			'tout' => $plus ? $app_base . '/courses?q=' . rawurlencode($q) : '',
			'resultats' => array_map(fn ($c) => [
				'titre' => coursSigleAffiche($c) . ' · ' . trim((string) $c['title']),
				'detail' => implode(' · ', array_filter([(string) $c['dep_desc'], coursNiveauLibelle((int) $c['yearlevel']) . ' S' . (int) $c['semester'],
					trim((string) ($c['enseignant'] ?? ''))])),
				'lien' => $app_base . '/cours?id=' . (int) $c['id'] . '&page=information',
			], array_slice($lignes, 0, $limite)),
		];

		$lignes = $chercher('enseignants', 't.name, t.lastName');
		$plus = count($lignes) > $limite;
		$groupes[] = [
			'cle' => 'enseignants', 'titre' => 'Enseignants', 'icone' => 'bi-person-badge',
			'tout' => $plus ? $app_base . '/professors?q=' . rawurlencode($q) : '',
			'resultats' => array_map(fn ($t) => [
				'titre' => trim(mb_strtoupper((string) $t['name']) . ' ' . (string) $t['lastName']),
				'detail' => implode(' · ', array_filter([trim((string) $t['position']), trim((string) $t['email'])])),
				'lien' => $app_base . '/professor?id=' . (int) $t['teacher_id'] . '&page=information',
			], array_slice($lignes, 0, $limite)),
		];
	}
} catch (Throwable $e) {
	error_log('recherche rapide : ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => 'La recherche est indisponible pour le moment.'], JSON_UNESCAPED_UNICODE);
	exit;
}

echo json_encode(['ok' => true, 'q' => $q,
	'groupes' => array_values(array_filter($groupes, fn ($g) => $g['resultats']))], JSON_UNESCAPED_UNICODE);
