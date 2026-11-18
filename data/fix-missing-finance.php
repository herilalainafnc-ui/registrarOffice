<?php
/**
 * Rattrapage : cree la ligne de frais (t_2024_etudiant_finace) des
 * inscriptions qui n'en ont pas.
 *
 * Sans ligne de frais, la fiche d'inscription imprime « Frais de scolarité non
 * disponibles », l'espace etudiant previent que la fiche ne vaut pas
 * justificatif de paiement, et le guichet ne pouvait pas la creer : 675
 * inscriptions au 07/10/2026, dont 500 de 2024-2025 et 165 de 2025-2026.
 *
 * Calcul : celui du guichet (data/inscription.frais.php). Pour une session
 * passee, la mention, le niveau et le statut de l'inscription d'alors, et le
 * tarif de l'annee (ce qui etait facture cette annee-la, a defaut le bareme) :
 * regle du registrariat du 07/10/2026.
 *
 * Usage, en ligne de commande seulement :
 *   php fix-missing-finance.php                      decompte, n'ecrit rien
 *   php fix-missing-finance.php --appliquer --sauvegarde=DOSSIER [--auteur=ID]
 * Avec --appliquer, tout est calcule avant la premiere ecriture (une ligne
 * creee ne doit pas peser sur le tarif des suivantes), puis ecrit en une
 * transaction. DOSSIER recoit prevus.json, apres.json et annuler.php, qui
 * retire les lignes creees tant que la caisse n'y a pas pose de mode de
 * paiement.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(__DIR__ . '/../src');
require_once __DIR__ . '/backdb.php';
require_once __DIR__ . '/inscription.frais.php';

$options    = getopt('', ['appliquer', 'sauvegarde:', 'auteur:']);
$appliquer  = isset($options['appliquer']);
$dossier    = (string) ($options['sauvegarde'] ?? '');
$auteur     = isset($options['auteur']) ? (int) $options['auteur'] : null;
if ($appliquer && ($dossier === '' || !is_dir($dossier) || !is_writable($dossier))) {
    fwrite(STDERR, "--appliquer demande --sauvegarde=DOSSIER (existant, accessible en ecriture)\n");
    exit(1);
}

$manquantes = $dtb->query(
    'SELECT DISTINCT ins.student_id, ins.session_id, s.session_year
       FROM t_2024_inscription_session ins
       JOIN t_2023_session s ON s.session_id = ins.session_id
      WHERE COALESCE(ins.`remove`, 0) != 1
        AND NOT EXISTS (SELECT 1 FROM t_2024_etudiant_finace f
                         WHERE f.student_id = ins.student_id AND f.session_id = ins.session_id AND f.`remove` != 1)
      ORDER BY s.session_year DESC, ins.session_id, ins.student_id'
)->fetchAll(PDO::FETCH_ASSOC);

/* --- 1. Tout calculer, sans rien ecrire -------------------------------- */
$prevues = [];
$sansFiche = [];
$sansTarif = [];
$niveauInconnu = [];
$origines = [];
$parAnnee = [];
$parSource = [];
foreach ($manquantes as $m) {
    $ctx = inscriptionFraisContexte($dtb, (string) $m['student_id'], (int) $m['session_id']);
    if (!$ctx) {
        $sansFiche[] = $m['student_id'] . ' / s' . $m['session_id'];
        continue;
    }
    if ($ctx['niveau'] === null) {
        $niveauInconnu[] = "$ctx[student_id] / s$ctx[session_id]";
        continue;
    }
    $prevus = inscriptionFraisPrevus($dtb, $ctx);
    if (!$prevus) {
        $sansTarif[] = "$ctx[student_id] / s$ctx[session_id] ($ctx[statut] $ctx[mention] L$ctx[niveau] semestre $ctx[semestre])";
        continue;
    }
    $prevues[] = ['ctx' => $ctx, 'prevus' => ['montants' => $prevus['montants'], 'sources' => $prevus['sources']]];
    $parAnnee[$ctx['annee_scolaire']] = ($parAnnee[$ctx['annee_scolaire']] ?? 0) + 1;
    $origines[$ctx['origine_niveau']] = ($origines[$ctx['origine_niveau']] ?? 0) + 1;
    foreach ($prevus['montants'] as $colonne => $valeur) {
        if ($valeur > 0) {
            $cle = $colonne . ' : ' . $prevus['sources'][$colonne];
            $parSource[$cle] = ($parSource[$cle] ?? 0) + 1;
        }
    }
}

echo count($manquantes), " inscription(s) sans ligne de frais\n";
echo count($prevues), " ligne(s) a creer :\n";
foreach ($parAnnee as $annee => $n) {
    echo "  $annee : $n\n";
}
echo "Origine des montants dus :\n";
ksort($parSource);
foreach ($parSource as $cle => $n) {
    echo "  $cle : $n\n";
}
echo count($sansFiche), " sans fiche etudiant", $sansFiche ? ' : ' . implode(', ', array_slice($sansFiche, 0, 10)) : '', "\n";
echo count($sansTarif), " sans tarif au bareme", $sansTarif ? ' : ' . implode(', ', array_slice($sansTarif, 0, 10)) : '', "\n";
echo count($niveauInconnu), " au niveau introuvable (session passee, niveau 0 sans autre indice)", $niveauInconnu ? ' : ' . implode(', ', array_slice($niveauInconnu, 0, 12)) : '', "\n";
echo "Origine du niveau retenu : ", json_encode($origines, JSON_UNESCAPED_UNICODE), "\n";
echo "Exemples :\n";
foreach (array_slice($prevues, 0, 5) as $p) {
    $c = $p['ctx'];
    echo "  $c[student_id] $c[session_name] $c[annee_scolaire] ($c[mention] L$c[niveau] $c[statut]) : ",
        implode(', ', array_map(static fn ($k, $v) => substr($k, 5) . " $v", array_keys($p['prevus']['montants']), $p['prevus']['montants'])), "\n";
}

if (!$appliquer) {
    echo "Decompte seulement : rien n'a ete ecrit (ajouter --appliquer --sauvegarde=DOSSIER).\n";
    exit(0);
}

/* --- 2. Ecrire, en une transaction ------------------------------------- */
file_put_contents("$dossier/prevus.json", json_encode($prevues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$crees = [];
$dtb->beginTransaction();
try {
    foreach ($prevues as $p) {
        $id = inscriptionFraisCreer($dtb, $p['ctx'], $p['prevus'], $auteur);
        if ($id) {
            $crees[] = (int) $id;
        }
    }
    $dtb->commit();
} catch (Throwable $e) {
    $dtb->rollBack();
    fwrite(STDERR, 'ANNULE, rien ecrit : ' . $e->getMessage() . "\n");
    exit(1);
}

file_put_contents("$dossier/apres.json", json_encode(['ids' => $crees, 'le' => date('Y-m-d H:i:s')]));
file_put_contents("$dossier/annuler.php", '<?php
/* Retire les lignes de frais creees par le rattrapage, sauf celles ou la
   caisse a depuis pose un mode de paiement (elles sont alors en usage). */
chdir("/var/www/registrar/src"); require "../data/backdb.php";
$ids = json_decode(file_get_contents(__DIR__ . "/apres.json"), true)["ids"];
$n = $ids ? $dtb->exec("DELETE FROM t_2024_etudiant_finace WHERE id IN (" . implode(",", array_map("intval", $ids)) . ") AND (mode_payement IS NULL OR mode_payement = \'\')") : 0;
echo "$n ligne(s) retiree(s) sur " . count($ids) . "\n";
');
echo count($crees), ' ligne(s) creee(s)', $crees ? ' (id ' . min($crees) . ' a ' . max($crees) . ')' : '', "\n";
