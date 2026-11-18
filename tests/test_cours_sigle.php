<?php
/* Tests de data/cours.sigle.php — base locale, lecture seule.
   php tests/test_cours_sigle.php */

require __DIR__ . '/../data/config.php';
require __DIR__ . '/../data/cours.sigle.php';

$echecs = 0;
$total  = 0;
function verifier(string $nom, $obtenu, $attendu): void
{
    global $echecs, $total;
    $total++;
    if ($obtenu !== $attendu) {
        $echecs++;
        echo "ÉCHEC  $nom\n        obtenu  : " . var_export($obtenu, true) . "\n        attendu : " . var_export($attendu, true) . "\n";
    }
}

verifier('normalisation', coursSigleNormalise("  info \t 101 "), 'INFO 101');
verifier('normalisation barres', coursSigleNormalise('adaf/burm/pro9 '), 'ADAF/BURM/PRO9');
verifier('niveaux', [coursNiveauLibelle(0), coursNiveauLibelle(3), coursNiveauLibelle(5)], ['Remise à niveau', 'Licence 3', 'Master 2']);
verifier('sigle vide', coursSigleDoublons(new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS), '   '), []);

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
$cours = $pdo->query("SELECT id, Sigle FROM t_2023_cours WHERE TRIM(Sigle) <> '' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($cours) {
    $variante = '  ' . strtolower(str_replace(' ', '   ', trim($cours['Sigle']))) . ' ';
    $trouves = coursSigleDoublons($pdo, $variante);
    verifier('trouvé malgré casse et espaces', in_array((int) $cours['id'], array_column($trouves, 'id'), true), true);
    verifier('exclu quand on modifie ce cours', in_array((int) $cours['id'], array_column(coursSigleDoublons($pdo, $variante, (int) $cours['id']), 'id'), true), false);
    verifier('message', str_starts_with(coursSigleMessage($trouves), 'Le sigle « '), true);
}
verifier('sigle libre', coursSigleDoublons($pdo, 'ZZZ-SIGLE-INEXISTANT-' . bin2hex(random_bytes(3))), []);

echo ($echecs === 0 ? 'OK' : "$echecs ÉCHEC(S)") . " — $total tests\n";
exit($echecs === 0 ? 0 : 1);
