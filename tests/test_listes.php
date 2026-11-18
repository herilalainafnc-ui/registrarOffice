<?php
/*
 * Listes du back-office (data/listes.php) : recherche, filtres et tri
 * combines. LECTURE SEULE.
 *
 *   php tests/test_listes.php
 */
$base = dirname(__DIR__);
require "$base/data/config.php";
require "$base/data/cours.sigle.php";
require "$base/data/listes.php";
$dtb = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

$ok = 0; $ko = 0;
function verifier(string $nom, bool $cond, string $detail = ''): void
{
	global $ok, $ko;
	if ($cond) { $ok++; echo "  ok  $nom\n"; }
	else { $ko++; echo "  KO  $nom" . ($detail !== '' ? "  ($detail)" : '') . "\n"; }
}
function executer(PDO $dtb, string $liste, array $source): array
{
	$def = listesDefinition($liste, $dtb);
	return listesExecuter($dtb, $def, listesEtat($def, $source), '');
}
function option(array $r, string $filtre, string $valeur): ?array
{
	foreach ($r['facettes'][$filtre]['options'] ?? [] as $o) { if ($o['v'] === $valeur) { return $o; } }
	return null;
}

echo "Etudiants\n";
$tous = executer($dtb, 'etudiants', []);
verifier('la liste complete a des lignes', $tous['total'] > 0);
verifier('premiere page limitee', substr_count($tous['html'], '<tr') === min($tous['total'], LISTES_PAR_PAGE));

/* Le nombre annonce par une option est celui qu on obtient en la cochant. */
$mention = $tous['facettes']['mention']['options'][0] ?? null;
if ($mention) {
	$filtre = executer($dtb, 'etudiants', ['mention' => $mention['v']]);
	verifier('compteur de l option = total une fois cochee', $filtre['total'] === $mention['n'], "{$mention['n']} annonces, {$filtre['total']} obtenus");
	$niveau = $filtre['facettes']['niveau']['options'][0] ?? null;
	if ($niveau) {
		$deux = executer($dtb, 'etudiants', ['mention' => $mention['v'], 'niveau' => $niveau['v']]);
		verifier('deux filtres se combinent (ET)', $deux['total'] === $niveau['n'] && $deux['total'] <= $filtre['total']);
		/* Plusieurs valeurs d un meme filtre : OU. */
		$autre = $tous['facettes']['mention']['options'][1] ?? null;
		if ($autre) {
			$ou = executer($dtb, 'etudiants', ['mention' => [$mention['v'], $autre['v']]]);
			verifier('deux valeurs d un filtre s additionnent (OU)', $ou['total'] === $mention['n'] + $autre['n']);
		}
		/* Trier ne change pas le nombre, et garde les filtres. */
		$trie = executer($dtb, 'etudiants', ['mention' => $mention['v'], 'niveau' => $niveau['v'], 'tri' => 'nom', 'sens' => 'desc']);
		verifier('trier garde les filtres', $trie['total'] === $deux['total'] && $trie['etat']['tri'] === 'nom');
	}
	verifier('le parcours ne se propose qu apres une mention', count($tous['facettes']['parcours']['options']) === 0
		&& count($filtre['facettes']['parcours']['options']) > 0);
}

/* Valeurs hostiles : tri inconnu -> tri par defaut, sens inconnu -> defaut. */
$hostile = executer($dtb, 'etudiants', ['tri' => 'student_id; DROP TABLE x', 'sens' => 'sideways', 'statut' => 'n_importe']);
verifier('tri inconnu remplace par le tri par defaut', $hostile['etat']['tri'] === 'recent' && $hostile['etat']['sens'] === 'desc');
verifier('option de cas inconnue ignoree', !isset($hostile['etat']['filtres']['statut']) && $hostile['total'] === $tous['total']);
$quote = executer($dtb, 'etudiants', ['q' => "' OR 1=1 -- "]);
verifier('recherche avec apostrophe : liee, pas interpretee', $quote['total'] < $tous['total']);

echo "Cours\n";
$cours = executer($dtb, 'cours', []);
$avec = option($cours, 'enseignant', 'avec');
$sans = option($cours, 'enseignant', 'sans');
verifier('avec + sans enseignant = tous les cours', $avec && $sans && $avec['n'] + $sans['n'] === $cours['total']);
/* Une fiche enseignant sans nom (identifiant 0) ne compte pas comme enseignant. */
$vides = (int) $dtb->query("SELECT COUNT(*) FROM t_2023_cours c JOIN teacher t ON t.uid = c.id_teacher
	WHERE c.remove != 1 AND TRIM(CONCAT_WS(' ', CONVERT(t.name USING utf8mb4), CONVERT(t.lastName USING utf8mb4))) = ''")->fetchColumn();
$sansListe = executer($dtb, 'cours', ['enseignant' => 'sans']);
verifier('un enseignant sans nom compte comme absent', $sansListe['total'] >= $vides, "$vides fiches vides");
verifier('aucune cellule enseignant vide sans tiret', preg_match_all('~data-label="Enseignant"><a[^>]*><div class="w-full">\s*</div>~', $sansListe['html']) === 0);
$parEnseignant = executer($dtb, 'cours', ['tri' => 'enseignant']);
preg_match_all('~data-label="Enseignant"><a[^>]*><div class="w-full">(.*?)</div>~', $parEnseignant['html'], $m);
$noms = array_map('strip_tags', $m[1]);
$premierVide = array_search('—', $noms, true);
verifier('tri par enseignant : les cours sans enseignant a la fin',
	$premierVide === false || count(array_filter(array_slice($noms, $premierVide), static fn ($n) => $n !== '—')) === 0);

echo "Enseignants\n";
$ens = executer($dtb, 'enseignants', []);
$lieux = array_sum(array_column($ens['facettes']['lieu']['options'], 'n'));
$religions = array_sum(array_column($ens['facettes']['religion']['options'], 'n'));
verifier('les cas du lieu partagent tous les enseignants', $lieux === $ens['total'], "$lieux / {$ens['total']}");
verifier('les cas de la religion partagent tous les enseignants', $religions === $ens['total'], "$religions / {$ens['total']}");
/* Les lignes elles-memes : aucune religion « non … » parmi les adventistes. */
$defEns = listesDefinition('enseignants', $dtb);
[$lignesAdv] = listesLignes($dtb, $defEns, listesEtat($defEns, ['religion' => 'adventiste']));
$intrus = array_filter($lignesAdv, static fn ($r) => stripos((string) $r['religion'], 'non') !== false);
verifier('« non Adventiste » n est pas compte adventiste', count($lignesAdv) > 0 && !$intrus, count($intrus) . ' intrus');

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko ? 1 : 0);
