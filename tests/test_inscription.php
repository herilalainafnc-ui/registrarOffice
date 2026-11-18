<?php
/**
 * Tests de inscription/inscription.php
 *
 * POURQUOI CE FICHIER N'EST PAS UN TEST UNITAIRE CLASSIQUE
 * --------------------------------------------------------
 * La page ne definit aucune fonction PHP : ses 9 fonctions sont du
 * JavaScript, et sa logique serveur est ecrite en ligne, entremelee au HTML.
 * Il n'y a donc rien a isoler au sens strict d'un test unitaire.
 *
 * Ce fichier fait deux choses a la place :
 *   1. il REJOUE le bloc de recherche d'etudiant tel qu'il est ecrit dans la
 *      page, extrait du fichier source — pas recopie. Si quelqu'un modifie la
 *      requete, le test suit la modification au lieu de valider une copie
 *      perimee ;
 *   2. il verifie par HTTP le garde d'acces, qui est la propriete la plus
 *      sensible de cette page : elle affiche des donnees personnelles
 *      d'etudiants.
 *
 * Le projet n'a ni PHPUnit ni autoload : ce script est autonome.
 *
 * Lancement :
 *   php tests/test_inscription.php
 *   php tests/test_inscription.php --url=https://registrar.test
 */

chdir(__DIR__ . '/..');

$base = 'https://registrar.test';
foreach ($argv as $a) {
    if (strpos($a, '--url=') === 0) { $base = rtrim(substr($a, 6), '/'); }
}

$reussis = 0;
$echoues = 0;

function verifier(string $intitule, bool $condition, string $detail = ''): void
{
    global $reussis, $echoues;
    if ($condition) {
        $reussis++;
        echo "  OK    $intitule\n";
    } else {
        $echoues++;
        echo "  ECHEC $intitule" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}

function titre(string $t): void { echo "\n" . $t . "\n" . str_repeat('-', strlen($t)) . "\n"; }

// ---------------------------------------------------------------------------
titre('1. Integrite du fichier');
// ---------------------------------------------------------------------------
$fichier = 'inscription/inscription.php';
verifier('le fichier existe', is_file($fichier));

$sortie = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($fichier) . ' 2>&1', $sortie, $code);
verifier('syntaxe PHP valide', $code === 0, implode(' ', $sortie));

$source = file_get_contents($fichier);

// Regression : la recherche a deja ete une concatenation de chaine. Si elle le
// redevenait, l'injection SQL serait de retour sans que rien ne la signale.
verifier(
    'la recherche utilise des requetes preparees',
    strpos($source, "prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :exact") !== false,
    'le motif de requete preparee est introuvable'
);
verifier(
    'aucune interpolation de $search dans une requete',
    !preg_match('/query\s*\(\s*["\'][^"\']*\$search/', $source),
    'une variable est concatenee dans une requete'
);

// ---------------------------------------------------------------------------
titre('2. Recherche d etudiant (bloc extrait de la page)');
// ---------------------------------------------------------------------------
require 'data/config.php';
try {
    $dtb = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    echo "  base injoignable : " . $e->getMessage() . "\n";
    exit(1);
}

/* Le bloc est decoupe dans le fichier source entre deux reperes stables, puis
   evalue. On teste donc le code reellement en production, pas une copie. */
$debut = strpos($source, '$recherche = trim(');
$fin   = $debut === false ? false : strpos($source, '$recupsdt = $stmt;', $debut);
if ($debut === false || $fin === false) {
    echo "  ECHEC : bloc de recherche introuvable dans le fichier (repere modifie ?)\n";
    exit(1);
}
/* L'accolade finale est ajoutee ici : le repere de fin tombe avant la
   fermeture du « if ($recherche !== '') », que la page ferme plus loin. */
$bloc = substr($source, $debut, $fin - $debut) . '$recupsdt = $stmt; }';

function rechercher(string $terme, PDO $dtb, string $bloc): array
{
    $_GET = ['student_id' => $terme];
    $_POST = [];
    $recupsdt = null;
    eval($bloc);
    return $recupsdt ? $recupsdt->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Un etudiant reel sert de reference, plutot qu'un matricule invente.
$ref = $dtb->query('SELECT student_id, student_nom FROM tbl_2024_etudiant WHERE remove != 1 LIMIT 1')
           ->fetch(PDO::FETCH_ASSOC);

$r = rechercher((string) $ref['student_id'], $dtb, $bloc);
verifier('matricule exact -> 1 resultat', count($r) === 1, 'obtenu : ' . count($r));
verifier('matricule exact -> le bon etudiant',
    count($r) === 1 && $r[0]['student_id'] === $ref['student_id']);

$r = rechercher(substr((string) $ref['student_nom'], 0, 5), $dtb, $bloc);
verifier('nom partiel -> repli LIKE trouve quelqu un', count($r) === 1, 'obtenu : ' . count($r));

$r = rechercher('MATRICULE-QUI-N-EXISTE-PAS-42', $dtb, $bloc);
verifier('matricule inconnu -> aucun resultat', count($r) === 0, 'obtenu : ' . count($r));

// Une recherche vide n'ouvre rien : le LIKE '%%' renvoyait le premier venu.
$r = rechercher('   ', $dtb, $bloc);
verifier('recherche vide -> aucun dossier ouvert', count($r) === 0, 'obtenu : ' . count($r));

// Un etudiant retire ne doit jamais ressortir : sa fiche a ete volontairement
// sortie des listes.
$retire = $dtb->query('SELECT student_id FROM tbl_2024_etudiant WHERE remove = 1 LIMIT 1')
              ->fetch(PDO::FETCH_ASSOC);
if ($retire) {
    $r = rechercher((string) $retire['student_id'], $dtb, $bloc);
    $trouve = array_filter($r, fn($l) => $l['student_id'] === $retire['student_id']);
    verifier('etudiant retire -> exclu des resultats', count($trouve) === 0);
} else {
    echo "  (aucun etudiant retire en base : cas non verifiable)\n";
}

// ---------------------------------------------------------------------------
titre('3. Resistance a l injection SQL');
// ---------------------------------------------------------------------------
$charges = [
    "' OR '1'='1",
    "'; DROP TABLE tbl_2024_etudiant; --",
    "1' UNION SELECT * FROM compt_utilisateur --",
    "\\' OR 1=1 --",
];
foreach ($charges as $charge) {
    try {
        $r = rechercher($charge, $dtb, $bloc);
        // Une charge traitee comme une simple chaine ne ramene rien : c'est la
        // preuve que la requete preparee tient.
        verifier('charge neutralisee : ' . substr($charge, 0, 26), count($r) === 0, 'a ramene ' . count($r) . ' ligne(s)');
    } catch (Throwable $e) {
        verifier('charge neutralisee : ' . substr($charge, 0, 26), false, 'exception : ' . $e->getMessage());
    }
}
$survit = (int) $dtb->query('SELECT COUNT(*) FROM tbl_2024_etudiant')->fetchColumn();
verifier('la table est intacte apres les charges', $survit > 0, "lignes restantes : $survit");

// ---------------------------------------------------------------------------
titre('4. Garde d acces (HTTP)');
// ---------------------------------------------------------------------------
function requete(string $url): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,   // certificat local
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $rep = curl_exec($c);
    $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
    $lieu = (string) curl_getinfo($c, CURLINFO_REDIRECT_URL);
    curl_close($c);
    return ['code' => $code, 'lieu' => $lieu, 'corps' => (string) $rep];
}

$cas = [
    ''                                  => 'sans parametre',
    '?student_id='                      => 'parametre vide',
    '?student_id=' . urlencode((string) $ref['student_id']) => 'matricule reel',
    "?student_id=" . urlencode("' OR 1=1--")                => 'charge SQL',
];
foreach ($cas as $suffixe => $libelle) {
    $rep = requete($base . '/inscription/inscription' . $suffixe);
    verifier("visiteur anonyme redirige ($libelle)",
        $rep['code'] === 302 || $rep['code'] === 301,
        'code ' . $rep['code']);
    // Le garde ne doit pas seulement rediriger : le corps ne doit rien livrer.
    verifier("aucune donnee etudiante livree ($libelle)",
        stripos($rep['corps'], (string) $ref['student_nom']) === false);
}

$rep = requete($base . '/inscription/inscription');
verifier('la redirection mene bien a la connexion',
    stripos($rep['lieu'], '/login') !== false, $rep['lieu']);

// Une page PHP ne doit jamais laisser fuir d'avertissement : ils revelent
// chemins et noms de variables, et signalent souvent un vrai defaut.
verifier('aucun avertissement PHP dans la reponse',
    !preg_match('/(Warning|Notice|Deprecated|Fatal error)\s*:/i', $rep['corps']));

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 52) . "\n";
printf("  %d reussis, %d echoues\n", $reussis, $echoues);
echo str_repeat('=', 52) . "\n";
exit($echoues > 0 ? 1 : 0);
