<?php
/* Tests de data/notes.statut.php — php tests/test_notes_statut.php */

require __DIR__ . '/../data/notes.statut.php';

$echecs = 0;
$total  = 0;
function verifier(string $nom, $obtenu, $attendu): void
{
    global $echecs, $total;
    $total++;
    if ($obtenu !== $attendu) {
        $echecs++;
        echo "ÉCHEC  $nom : obtenu " . var_export($obtenu, true) . ", attendu " . var_export($attendu, true) . "\n";
    }
}

/* Codes de statut */
foreach (['-4', -4, '-4.00', '-1', '', null, 'abc', '11-13', '-7'] as $g) {
    $s = noteStatut($g);
    verifier("NN pour " . var_export($g, true), [$s['code'], $s['libelle'], $s['compte'], $s['chiffree']], ['NN', 'NN', false, false]);
}
verifier('-2 → V', [noteStatut('-2')['code'], noteLibelle(-2), noteCompteDansMoyenne('-2')], ['V', 'V', false]);
verifier('-3 → E', [noteStatut('-3.00')['code'], noteLibelle('-3'), noteCompteDansMoyenne(-3)], ['E', 'E', false]);

/* Notes chiffrées */
verifier('0 est une vraie note', [noteStatut('0')['code'], noteLibelle('0'), noteCompteDansMoyenne('0'), noteStatut('0')['valeur']], ['E', '0', true, 0.0]);
verifier('9.99 échec', [noteStatut('9.99')['code'], noteCompteDansMoyenne('9.99')], ['E', true]);
verifier('10 succès', [noteStatut('10')['code'], noteStatut('10')['titre']], ['S', 'Succès']);
verifier('virgule décimale', [noteLibelle('12,5'), noteStatut('12,5')['valeur']], ['12.5', 12.5]);
verifier('espaces', noteLibelle(' 14 '), '14');

/* Cours de validation */
verifier('validation 15 → V', [noteStatut('15', true)['code'], noteLibelle('15', true), noteCompteDansMoyenne('15', true)], ['V', 'V', false]);
verifier('validation 7 → E', noteStatut('7', true)['code'], 'E');
verifier('validation -4 → NN', noteStatut('-4', true)['code'], 'NN');
verifier('catégorie 5', noteEstCoursValidation(['cours_category' => '5', 'Sigle' => 'X', 'title_cours' => 'Y']), true);
verifier('RELP 291', noteEstCoursValidation(['cours_category' => 1, 'Sigle' => 'RELP 291']), true);
verifier('formation spirituelle (t_2023_cours)', noteEstCoursValidation(['category' => 0, 'title' => 'Formation spirituelle I']), true);
verifier('cours normal', noteEstCoursValidation(['cours_category' => 1, 'Sigle' => 'INFO 101', 'title_cours' => 'Algo']), false);

/* Pastilles */
verifier('classes', [noteClasseEtat('S'), noteClasseEtat('V'), noteClasseEtat('E'), noteClasseEtat('NN'), noteClasseEtat('')],
         ['bg-green-500', 'bg-green-500', 'bg-red-500', 'bg-slate-500', '']);

echo ($echecs === 0 ? "OK" : "$echecs ÉCHEC(S)") . " — $total tests\n";
exit($echecs === 0 ? 0 : 1);
