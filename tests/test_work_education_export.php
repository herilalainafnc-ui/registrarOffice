<?php
/* Work Education : les choix de l'export PDF (data/work.education.php : weExportFiltres,
   weExportFiltrer, weExportOptions). Sans base : des etudiants fictifs.
     php tests/test_work_education_export.php */
require_once __DIR__ . '/../data/work.education.php';

$total = 0; $echecs = 0;
function verifier(string $nom, $obtenu, $attendu): void
{
    global $total, $echecs;
    $total++;
    if ($obtenu !== $attendu) {
        $echecs++;
        echo "ÉCHEC  $nom\n        obtenu  : " . json_encode($obtenu, JSON_UNESCAPED_UNICODE) . "\n        attendu : " . json_encode($attendu, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
$e = static fn (string $id, string $mention, ?int $niveau, bool $nouveau, string $tache) => [
    'id' => $id, 'nom' => 'N' . $id, 'prenom' => 'P', 'mention' => $mention, 'niveau' => $niveau,
    'niveau_libelle' => $niveau === null ? '' : ($niveau === 0 ? 'Remise à niveau' : 'Licence ' . $niveau), 'nouveau' => $nouveau, 'tache' => $tache,
];
$lignes = [
    $e('1', 'Informatique', 1, true,  'Bibliothèque'),
    $e('2', 'Informatique', 2, false, ''),
    $e('3', 'Théologie',    1, false, 'Bibliothèque'),
    $e('4', 'Gestion',      3, true,  'Cafétéria'),
    $e('5', 'Gestion',      null, false, ''),
    $e('6', 'Théologie',    0, false, '_Sous-tirets'),
];
$ids = static fn (array $l) => array_column($l, 'id');

verifier('sans filtre : tout le monde', $ids(weExportFiltrer($lignes, weExportFiltres([]))), ['1', '2', '3', '4', '5', '6']);
verifier('mention', $ids(weExportFiltrer($lignes, weExportFiltres(['mention' => 'Gestion']))), ['4', '5']);
verifier('niveau', $ids(weExportFiltrer($lignes, weExportFiltres(['niveau' => '1']))), ['1', '3']);
verifier('niveau 0 (remise à niveau) distinct d\'un niveau absent', $ids(weExportFiltrer($lignes, weExportFiltres(['niveau' => '0']))), ['6']);
verifier('nouveaux', $ids(weExportFiltrer($lignes, weExportFiltres(['statut' => 'nouveau']))), ['1', '4']);
verifier('anciens', $ids(weExportFiltrer($lignes, weExportFiltres(['statut' => 'ancien']))), ['2', '3', '5', '6']);
verifier('service exact', $ids(weExportFiltrer($lignes, weExportFiltres(['tache' => 'Bibliothèque']))), ['1', '3']);
verifier('service dont le nom commence par un tiret bas', $ids(weExportFiltrer($lignes, weExportFiltres(['tache' => '_Sous-tirets']))), ['6']);
verifier('sans tâche', $ids(weExportFiltrer($lignes, weExportFiltres(['tache' => '__sans']))), ['2', '5']);
verifier('avec une tâche', $ids(weExportFiltrer($lignes, weExportFiltres(['tache' => '__avec']))), ['1', '3', '4', '6']);
verifier('filtres combinés : mention + service', $ids(weExportFiltrer($lignes, weExportFiltres(['mention' => 'Informatique', 'tache' => 'Bibliothèque']))), ['1']);
verifier('filtres combinés sans résultat', $ids(weExportFiltrer($lignes, weExportFiltres(['mention' => 'Gestion', 'statut' => 'ancien', 'tache' => '__avec']))), []);
verifier('valeurs inconnues ignorées (statut, niveau)', weExportFiltres(['statut' => 'x', 'niveau' => 'abc', 'mention' => ' Droit ']), ['mention' => 'Droit', 'niveau' => '', 'statut' => '', 'tache' => '']);

$o = weExportOptions($lignes);
verifier('options : mentions triées', $o['mentions'], ['Gestion', 'Informatique', 'Théologie']);
verifier('options : niveaux dans l\'ordre', array_column($o['niveaux'], 'valeur'), ['0', '1', '2', '3']);
verifier('options : services par effectif', $o['services'], [['tache' => 'Bibliothèque', 'n' => 2], ['tache' => '_Sous-tirets', 'n' => 1], ['tache' => 'Cafétéria', 'n' => 1]]);
verifier('options : sans tâche et total', [$o['sans'], $o['total']], [2, 6]);

$r = weExportAffichage($e('7', 'Droit', 0, false, ''));
verifier('remise à niveau affichée Licence 1', [$r['niveau'], $r['niveau_libelle']], [1, 'Licence 1']);
$r = weExportAffichage($e('8', 'Droit', 2, false, ''));
verifier('Licence 2 inchangée', [$r['niveau'], $r['niveau_libelle']], [2, 'Licence 2']);

echo $echecs === 0 ? "OK : $total vérifications.\n" : "$echecs échec(s) sur $total.\n";
exit($echecs === 0 ? 0 : 1);
