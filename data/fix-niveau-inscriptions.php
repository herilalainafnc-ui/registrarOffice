<?php
/**
 * Rattrapage : renseigne le niveau (niveau_std) des inscriptions restees a 0.
 *
 * L'etape 1 du guichet n'ecrivait pas le niveau : il restait a 0 jusqu'a un
 * ajout de cours. Toute la session 2024-2025 S1 l'est restee, et la
 * Statistique la classait entiere en « remise a niveau » (07/10/2026).
 *
 * Le niveau retenu est celui du calcul des frais (inscriptionFraisContexte,
 * data/inscription.frais.php) : pour une session passee, une autre
 * inscription de la meme annee, sinon 0 si la fiche est encore en L0 (vraie
 * remise a niveau, rien a changer), sinon le niveau de ses cours ; pour
 * l'annee en cours, celui de la fiche. Niveau introuvable : rien n'est ecrit.
 *
 * Usage, en ligne de commande seulement :
 *   php fix-niveau-inscriptions.php                      decompte, n'ecrit rien
 *   php fix-niveau-inscriptions.php --appliquer --sauvegarde=DOSSIER
 * DOSSIER recoit avant.json et annuler.php (remet 0 la ou le niveau n'a pas
 * change depuis).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(__DIR__ . '/../src');
require_once __DIR__ . '/backdb.php';
require_once __DIR__ . '/inscription.frais.php';

$options   = getopt('', ['appliquer', 'sauvegarde:']);
$appliquer = isset($options['appliquer']);
$dossier   = (string) ($options['sauvegarde'] ?? '');
if ($appliquer && ($dossier === '' || !is_dir($dossier) || !is_writable($dossier))) {
    fwrite(STDERR, "--appliquer demande --sauvegarde=DOSSIER (existant, accessible en ecriture)\n");
    exit(1);
}

$aZero = $dtb->query(
    'SELECT ins.id, ins.student_id, ins.session_id, s.session_name, s.session_year
       FROM t_2024_inscription_session ins
       JOIN t_2023_session s ON s.session_id = ins.session_id
      WHERE COALESCE(ins.`remove`, 0) != 1 AND COALESCE(ins.niveau_std, 0) = 0
      ORDER BY s.session_year DESC, ins.session_id, ins.student_id'
)->fetchAll(PDO::FETCH_ASSOC);

$corrections = [];
$parSession = [];
$origines = [];
$restent = ['remise a niveau' => 0, 'inconnu' => 0, 'sans fiche' => 0];
foreach ($aZero as $i) {
    $ctx = inscriptionFraisContexte($dtb, (string) $i['student_id'], (int) $i['session_id']);
    if (!$ctx) {
        $restent['sans fiche']++;
        continue;
    }
    if ($ctx['niveau'] === null) {
        $restent['inconnu']++;
        continue;
    }
    if ((int) $ctx['niveau'] === 0) {
        $restent['remise a niveau']++;
        continue;
    }
    $corrections[] = ['id' => (int) $i['id'], 'student_id' => $i['student_id'], 'session_id' => (int) $i['session_id'], 'niveau' => (int) $ctx['niveau'], 'origine' => $ctx['origine_niveau']];
    $cle = "s$i[session_id] $i[session_name] $i[session_year]";
    $parSession[$cle][(int) $ctx['niveau']] = ($parSession[$cle][(int) $ctx['niveau']] ?? 0) + 1;
    $origines[$ctx['origine_niveau']] = ($origines[$ctx['origine_niveau']] ?? 0) + 1;
}

echo count($aZero), " inscription(s) au niveau 0\n";
echo count($corrections), " a renseigner, par session et niveau :\n";
foreach ($parSession as $session => $niveaux) {
    ksort($niveaux);
    echo "  $session : ", implode(', ', array_map(static fn ($n, $c) => "L$n=$c", array_keys($niveaux), $niveaux)), "\n";
}
echo "Origine du niveau : ", json_encode($origines, JSON_UNESCAPED_UNICODE), "\n";
echo "Restent a 0 : ", json_encode($restent, JSON_UNESCAPED_UNICODE), "\n";

if (!$appliquer) {
    echo "Decompte seulement : rien n'a ete ecrit (ajouter --appliquer --sauvegarde=DOSSIER).\n";
    exit(0);
}

file_put_contents("$dossier/avant.json", json_encode($corrections, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$maj = $dtb->prepare('UPDATE t_2024_inscription_session SET niveau_std = :n WHERE id = :id AND COALESCE(niveau_std, 0) = 0');
$faites = 0;
$dtb->beginTransaction();
try {
    foreach ($corrections as $c) {
        $maj->execute(['n' => $c['niveau'], 'id' => $c['id']]);
        $faites += $maj->rowCount();
    }
    $dtb->commit();
} catch (Throwable $e) {
    $dtb->rollBack();
    fwrite(STDERR, 'ANNULE, rien ecrit : ' . $e->getMessage() . "\n");
    exit(1);
}
file_put_contents("$dossier/annuler.php", '<?php
/* Remet le niveau a 0 sur les inscriptions renseignees par le rattrapage,
   la ou il n\'a pas change depuis. */
chdir("/var/www/registrar/src"); require "../data/backdb.php";
$st = $dtb->prepare("UPDATE t_2024_inscription_session SET niveau_std = 0 WHERE id = :id AND niveau_std = :n");
$n = 0;
foreach (json_decode(file_get_contents(__DIR__ . "/avant.json"), true) as $c) { $st->execute(["id" => $c["id"], "n" => $c["niveau"]]); $n += $st->rowCount(); }
echo "$n inscription(s) remise(s) a 0\n";
');
echo "$faites inscription(s) renseignee(s)\n";
