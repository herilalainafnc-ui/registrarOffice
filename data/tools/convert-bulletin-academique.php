<?php
/**
 * Convertit « Bulletin académique - LICENCE.docx » en HTML structuré.
 *
 * - Les styles Word (Titre1/2/3, Titremention, Sous-titre) deviennent des <h2>/<h3>/<h4>
 * - Les listes (w:numPr ou Paragraphedeliste) deviennent des <ul><li>
 * - Les tableaux sont repris tels quels
 * - Les entrées de table des matières auto (TM1/TM2/TM3) sont ignorées :
 *   la page reconstruit son propre sommaire à partir des titres
 * - Chaque paragraphe du corps reçoit un id "p{n}" correspondant au § cité
 *   dans le code (permet de lier directement une règle depuis l'application)
 */

// Usage : php data/tools/convert-bulletin-academique.php "chemin/vers/Bulletin académique - LICENCE.docx"
// A relancer a chaque nouvelle version du reglement, puis redeployer le HTML genere.
// Attention : les numeros de paragraphe (§) servent d'ancres depuis l'application
// (ex. §383 depuis la page diplome). Toute reecriture du .docx peut les decaler :
// verifier les liens profonds apres regeneration.

$src = $argv[1] ?? (__DIR__ . '/bulletin-academique-licence.docx');
$out = dirname(__DIR__, 2) . '/src/assets/bulletin-academique.html';

if (!is_file($src)) { exit("ERREUR : introuvable — $src\n"); }

$z = new ZipArchive();
if ($z->open($src) !== true) { exit("ERREUR : docx illisible\n"); }
$doc = new DOMDocument();
$doc->loadXML($z->getFromName('word/document.xml'));
$xp = new DOMXPath($doc);
$W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
$xp->registerNamespace('w', $W);

/** Texte d'un noeud, avec gras/italique conservés. */
function inline(DOMXPath $xp, DOMNode $p) {
    $html = '';
    foreach ($xp->query('./w:r', $p) as $r) {
        $t = '';
        foreach ($xp->query('.//w:t', $r) as $x) { $t .= $x->nodeValue; }
        foreach ($xp->query('.//w:br', $r) as $b) { $t .= "\n"; }
        if ($t === '') { continue; }
        $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        if ($xp->query('./w:rPr/w:b', $r)->length) { $t = '<strong>' . $t . '</strong>'; }
        if ($xp->query('./w:rPr/w:i', $r)->length) { $t = '<em>' . $t . '</em>'; }
        $html .= $t;
    }
    return trim(preg_replace('/[ \t]+/u', ' ', $html));
}

function styleDe(DOMXPath $xp, DOMNode $p) {
    $s = $xp->query('./w:pPr/w:pStyle/@w:val', $p);
    return $s->length ? $s->item(0)->nodeValue : '';
}

$html   = [];
$titres = [];   // sommaire : [niveau, id, texte]
$numP   = 0;    // numérotation des paragraphes du corps (= les § cités)
$dansListe = false;

foreach ($xp->query('/w:document/w:body/*') as $el) {

    // ------------------------------------------------------------- tableaux
    if ($el->localName === 'tbl') {
        if ($dansListe) { $html[] = '</ul>'; $dansListe = false; }
        $t = '<table>';
        $premiere = true;
        foreach ($xp->query('.//w:tr', $el) as $tr) {
            $cells = [];
            foreach ($xp->query('./w:tc', $tr) as $tc) {
                $txt = [];
                foreach ($xp->query('.//w:p', $tc) as $cp) {
                    $v = inline($xp, $cp);
                    if ($v !== '') { $txt[] = $v; }
                }
                $cells[] = implode('<br>', $txt);
            }
            if (!array_filter($cells, 'strlen')) { continue; }
            $tag = $premiere ? 'th' : 'td';
            $t .= '<tr><' . $tag . '>' . implode('</' . $tag . '><' . $tag . '>', $cells) . '</' . $tag . '></tr>';
            $premiere = false;
        }
        $t .= '</table>';
        if (strpos($t, '<tr>') !== false) { $html[] = '<div class="tbl-wrap">' . $t . '</div>'; }
        continue;
    }

    if ($el->localName !== 'p') { continue; }

    $numP++;
    $style = styleDe($xp, $el);

    // Table des matières auto de Word : ignorée, on reconstruit la nôtre
    if (in_array($style, ['TM1', 'TM2', 'TM3', 'En-ttedetabledesmatires'], true)) { continue; }

    $txt = inline($xp, $el);
    if ($txt === '') { continue; }

    // ------------------------------------------------------------- titres
    $niveaux = ['Titre1' => 2, 'Titremention' => 2, 'Titre2' => 3, 'Titrenormal' => 3, 'Sous-titre' => 3, 'Titre3' => 4];
    if (isset($niveaux[$style])) {
        if ($dansListe) { $html[] = '</ul>'; $dansListe = false; }
        $n  = $niveaux[$style];
        $id = 'p' . $numP;
        $titres[] = [$n, $id, strip_tags($txt)];
        $html[] = '<h' . $n . ' id="' . $id . '" data-par="' . $numP . '">' . $txt
                . ' <a class="ancre" href="#' . $id . '" title="Lien vers cette section">§' . $numP . '</a></h' . $n . '>';
        continue;
    }

    // ------------------------------------------------------------- listes
    $estListe = $xp->query('./w:pPr/w:numPr', $el)->length > 0 || $style === 'Paragraphedeliste';
    if ($estListe) {
        if (!$dansListe) { $html[] = '<ul>'; $dansListe = true; }
        $html[] = '<li id="p' . $numP . '">' . $txt . '</li>';
        continue;
    }
    if ($dansListe) { $html[] = '</ul>'; $dansListe = false; }

    if ($style === 'Citation') {
        $html[] = '<blockquote id="p' . $numP . '">' . $txt . '</blockquote>';
        continue;
    }

    $html[] = '<p id="p' . $numP . '">' . $txt . '</p>';
}
if ($dansListe) { $html[] = '</ul>'; }

// -------------------------------------------------------------- sommaire
$toc = '<nav class="sommaire" id="sommaire"><h2>Sommaire</h2><ul>';
foreach ($titres as [$n, $id, $t]) {
    $toc .= '<li class="n' . $n . '"><a href="#' . $id . '">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</a></li>';
}
$toc .= '</ul></nav>';

@mkdir(dirname($out), 0775, true);
file_put_contents($out, $toc . "\n<article id=\"contenu\">\n" . implode("\n", $html) . "\n</article>\n");

printf("  genere : %s\n  %d octets · %d titres · %d blocs\n", $out, filesize($out), count($titres), count($html));
