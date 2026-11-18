<?php
/* =============================================================================
   Work Education — la liste en PDF (src/work-education.php, bouton « Exporter en PDF »)
   (demande du 27/10/2026 : « un bouton d'export en pdf de liste, qu'on puisse filtrer
   pour chaque service, mention, année ou bien tout »)

   GET  (sans action)        le PDF : session_id, portee = session | annee | tout,
                             mention, niveau, statut = nouveau | ancien,
                             tache = (vide) | __sans | __avec | texte exact du service,
                             groupe = service | mention | niveau | session | aucun
   GET  ?action=options      JSON : ce qu'on peut choisir dans la portee (mentions,
                             niveaux, services avec leur effectif) et le nombre
                             d'etudiants qui repondent aux filtres

   Le responsable du Work Education (niveau 9) et les niveaux 1 a 3, comme la page
   (WorkEducationController::index). Lecture seule.
   ========================================================================== */

use Dompdf\Dompdf;
use Dompdf\Options;

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
refuserRequeteCroisee(); // jamais depuis un autre site
if (!in_array((int) ($_SESSION['user_level'] ?? 99), [1, 2, 3, ROLE_WORK_EDUCATION], true)) {
    http_response_code(403);
    if (estRequeteAjax() || ($_GET['action'] ?? '') === 'options') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Accès refusé : privilèges insuffisants.']);
    } else {
        echo '<h1>403 - Accès refusé</h1><p>Cette liste est réservée au responsable du Work Education et au registrariat.</p>';
    }
    exit;
}
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/work.education.php');

const WE_PDF_MAX = 6000; // lignes au plus (la memoire de dompdf)

function wePdfJson(array $charge, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Une chaine comparable : sans accents, en minuscules (l'ordre alphabetique d'une liste de noms). */
function wePdfCle(string $s): string
{
    return strtr(mb_strtolower($s), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ÿ' => 'y', 'œ' => 'oe']);
}
function wePdfH($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$portee = in_array($_GET['portee'] ?? '', ['annee', 'tout'], true) ? (string) $_GET['portee'] : 'session';
$sessionId = (int) ($_GET['session_id'] ?? 0);
$filtres = weExportFiltres($_GET);
$groupe = in_array($_GET['groupe'] ?? '', ['service', 'mention', 'niveau', 'session', 'aucun'], true) ? (string) $_GET['groupe'] : 'service';

$collecte = weExportLignes($dtb, $sessionId, $portee);
if (!$collecte['sessions']) {
    wePdfJson(['success' => false, 'message' => 'Session inconnue.'], 404);
}
$lignes = weExportFiltrer($collecte['lignes'], $filtres);

if (($_GET['action'] ?? '') === 'options') {
    wePdfJson(['success' => true, 'options' => weExportOptions($collecte['lignes']), 'total' => count($lignes), 'max' => WE_PDF_MAX,
        'sessions' => count($collecte['sessions'])]);
}

if (!$lignes) {
    http_response_code(404);
    echo '<h1>Aucun étudiant</h1><p>Aucun étudiant ne correspond à ces filtres.</p>';
    exit;
}
if (count($lignes) > WE_PDF_MAX) {
    http_response_code(413);
    echo '<h1>Liste trop longue</h1><p>' . count($lignes) . ' étudiants : choisissez une portée plus étroite ou un filtre (au plus ' . WE_PDF_MAX . ').</p>';
    exit;
}

/* ------------------------------------------------------------ ce que dit l'en-tete */
$courante = $collecte['sessions'][0];
foreach ($collecte['sessions'] as $s) { if ((int) $s['session_id'] === $sessionId) { $courante = $s; } }
$libPortee = $portee === 'tout' ? 'Toutes les sessions'
    : ($portee === 'annee' ? 'Année universitaire ' . $courante['session_year'] : $courante['session_name'] . ' ' . $courante['session_year']);
$criteres = [];
if ($filtres['mention'] !== '') { $criteres[] = 'Mention : ' . $filtres['mention']; }
if ($filtres['niveau'] !== '') {
    $niv = array_column(baremeNiveaux(), 'libelle', 'niveau');
    $criteres[] = 'Niveau : ' . ($niv[(int) $filtres['niveau']] ?? $filtres['niveau']);
}
if ($filtres['statut'] !== '') { $criteres[] = $filtres['statut'] === 'nouveau' ? 'Nouveaux étudiants' : 'Anciens étudiants'; }
if ($filtres['tache'] === '__sans') { $criteres[] = 'Sans tâche'; }
elseif ($filtres['tache'] === '__avec') { $criteres[] = 'Avec une tâche'; }
elseif ($filtres['tache'] !== '') { $criteres[] = 'Service : ' . $filtres['tache']; }
$avec = count(array_filter($lignes, static fn ($e) => $e['tache'] !== ''));
$multi = count($collecte['sessions']) > 1;

/* ------------------------------------------------------------ groupes et tri */
$cleGroupe = static function (array $e) use ($groupe): string {
    switch ($groupe) {
        case 'service': return $e['tache'] !== '' ? $e['tache'] : "\u{FFFF}";   // « Sans tâche » en dernier
        case 'mention': return $e['mention'] !== '' ? $e['mention'] : "\u{FFFF}";
        case 'niveau':  return $e['niveau'] !== null ? str_pad((string) $e['niveau'], 3, '0', STR_PAD_LEFT) : "\u{FFFF}";
        case 'session': return str_pad((string) (99999 - $e['session_id']), 6, '0', STR_PAD_LEFT);
        default:        return '';
    }
};
$titreGroupe = static function (array $e) use ($groupe): string {
    switch ($groupe) {
        case 'service': return $e['tache'] !== '' ? $e['tache'] : 'Sans tâche';
        case 'mention': return $e['mention'] !== '' ? $e['mention'] : 'Mention non renseignée';
        case 'niveau':  return $e['niveau'] !== null ? ($e['niveau_libelle'] !== '' ? $e['niveau_libelle'] : 'Niveau ' . $e['niveau']) : 'Niveau non renseigné';
        case 'session': return $e['session'];
        default:        return '';
    }
};
usort($lignes, static function ($a, $b) use ($cleGroupe) {
    return [wePdfCle($cleGroupe($a)), wePdfCle($a['nom']), wePdfCle($a['prenom']), $a['id']]
       <=> [wePdfCle($cleGroupe($b)), wePdfCle($b['nom']), wePdfCle($b['prenom']), $b['id']];
});
$groupes = [];
foreach ($lignes as $e) {
    $groupes[$cleGroupe($e)]['titre'] = $titreGroupe($e);
    $groupes[$cleGroupe($e)]['lignes'][] = $e;
}

/* ------------------------------------------------------------ le document */
/* Le logo, reduit (l'original fait 980 Ko : chaque PDF pesait 1 Mo) et integre au document. */
$logo = null;
$logoFichier = realpath(__DIR__ . '/../file/UAZ Official Black Logo.jpg');
if ($logoFichier && function_exists('imagecreatefromjpeg')) {
    $src = @imagecreatefromjpeg($logoFichier);
    if ($src) {
        $h = 150; $l = (int) round(imagesx($src) * $h / imagesy($src));
        $petit = imagecreatetruecolor($l, $h);
        imagecopyresampled($petit, $src, 0, 0, 0, 0, $l, $h, imagesx($src), imagesy($src));
        ob_start(); imagejpeg($petit, null, 82); $logo = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
    }
}
if ($logo === null && $logoFichier) { $logo = $logoFichier; }
$colonnes = [['N°', 4], ['Matricule', 8], ['Nom et prénom', $multi ? 31 : 36], ['Mention', 15], ['Niveau', 9], ['Statut', 6]];
if ($multi) { $colonnes[] = ['Session', 17]; }
$colonnes[] = ['Tâche (service)', $multi ? 20 : 30];
$somme = array_sum(array_column($colonnes, 1));

ob_start();
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8">
<style>
    @page { margin: 22mm 12mm 16mm 12mm; }
    body { font-family: helvetica, sans-serif; font-size: 8.6pt; color: #111; }
    .entete { width: 100%; border-bottom: 1px solid #8e9bb2; margin-bottom: 8px; }
    .entete td { vertical-align: middle; }
    .entete .centre { text-align: center; }
    .entete b { display: block; }
    h1 { font-size: 14pt; margin: 6px 0 2px; text-align: center; color: #232A5F; }
    .sous { text-align: center; margin: 0 0 8px; color: #333; font-size: 9pt; }
    .resume { width: 100%; margin: 0 0 8px; border: 1px solid #c9cfe0; background: #f4f6fb; }
    .resume td { padding: 4px 8px; font-size: 8.6pt; }
    table.liste { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.liste th { background: #232A5F; color: #fff; font-size: 8pt; text-align: left; padding: 4px 5px; border: 1px solid #232A5F; }
    table.liste td { padding: 3px 5px; border: 1px solid #c9cfe0; vertical-align: top; white-space: nowrap; overflow: hidden; }
    table.liste tr.groupe td { background: #e4e8f4; font-weight: bold; font-size: 9pt; color: #232A5F; border-color: #b8c0da; }
    table.liste tr.vide td.tache { color: #a05a00; font-style: italic; }
    table.liste tr:nth-child(even) td { background: #fafbfd; }
    table.liste tr.groupe td { background: #e4e8f4; }
    .num { text-align: right; color: #555; }
    .pied { margin-top: 10px; font-size: 8pt; color: #555; }
</style></head><body>
<table class="entete"><tr>
    <td style="width: 18%;"><?php if ($logo): ?><img src="<?= wePdfH($logo) ?>" style="height: 62px;"><?php endif; ?></td>
    <td class="centre" style="width: 64%;">
        <b style="font-size: 12pt;">UNIVERSITÉ ADVENTISTE ZURCHER</b>
        <b style="font-size: 9.5pt; margin-top: 2px;">BUREAU DE LA REGISTRAIRE</b>
        <span style="font-size: 8pt;">BP 325, Antsirabe 110, MADAGASCAR · Tél : 034 38 180 81</span>
    </td>
    <td style="width: 18%; text-align: right; font-size: 8pt; color: #555;">Édité le<br><?= date('d/m/Y') ?></td>
</tr></table>

<h1>Work Education — liste des étudiants</h1>
<p class="sous"><?= wePdfH($libPortee) ?><?= $criteres ? ' · ' . wePdfH(implode(' · ', $criteres)) : ' · tous les étudiants' ?></p>

<table class="liste">
    <thead><tr>
        <?php foreach ($colonnes as [$nom, $largeur]): ?><th style="width: <?= round($largeur * 100 / $somme, 2) ?>%;"><?= wePdfH($nom) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php $n = 0; foreach ($groupes as $g): ?>
        <?php if ($groupe !== 'aucun'): ?>
        <tr class="groupe"><td colspan="<?= count($colonnes) ?>"><?= wePdfH($g['titre']) ?> — <?= count($g['lignes']) ?> étudiant<?= count($g['lignes']) > 1 ? 's' : '' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($g['lignes'] as $e): $n++; ?>
        <tr class="<?= $e['tache'] === '' ? 'vide' : '' ?>">
            <td class="num"><?= $n ?></td>
            <td><?= wePdfH($e['id']) ?></td>
            <td><b><?= wePdfH(mb_strtoupper($e['nom'])) ?></b> <?= wePdfH($e['prenom']) ?></td>
            <td><?= wePdfH($e['mention']) ?></td>
            <td><?= wePdfH($e['niveau_libelle'] !== '' ? $e['niveau_libelle'] : ($e['niveau'] !== null ? 'Niveau ' . $e['niveau'] : '')) ?></td>
            <td><?= $e['nouveau'] ? 'Nouveau' : 'Ancien' ?></td>
            <?php if ($multi): ?><td><?= wePdfH($e['session']) ?></td><?php endif; ?>
            <td class="tache"><?= $e['tache'] !== '' ? wePdfH($e['tache']) : 'Sans tâche' ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</body></html>
<?php
$html = ob_get_clean();

require_once(__DIR__ . '/../src/dompdf/vendor/autoload.php');
require_once(__DIR__ . '/../src/dompdf/autoload.inc.php');
@set_time_limit(180);
@ini_set('memory_limit', '512M');

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('chroot', [realpath(__DIR__ . '/../file')]);
$options->set('defaultFont', 'helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');   // une ligne par etudiant, sans retour a la ligne
$dompdf->render();

/* « Page 1 sur 3 » en bas de chaque page */
$canvas = $dompdf->getCanvas();
$police = $dompdf->getFontMetrics()->getFont('helvetica');
$canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} sur {PAGE_COUNT}', $police, 8, [0.35, 0.35, 0.35]);

$nomFichier = 'WorkEducation_' . preg_replace('/[^A-Za-z0-9]+/', '-', wePdfCle($libPortee . ($criteres ? '-' . implode('-', $criteres) : ''))) . '_' . date('Ymd') . '.pdf';
header('X-Content-Type-Options: nosniff');
$dompdf->stream(trim(preg_replace('/-+/', '-', $nomFichier), '-'), ['Attachment' => true]);
