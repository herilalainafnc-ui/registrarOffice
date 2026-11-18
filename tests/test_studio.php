<?php
/* Tests de data/studio.php (Studio UAZ) — sans base, sans service de rendu.
   php tests/test_studio.php
   Les tests du rendu lui-meme sont dans tests/studio/ (Node + Chromium). */

require __DIR__ . '/../data/studio.php';

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

/* --- presets */
$presets = studioPresets();
verifier('preset evt présent', isset($presets['evt']), true);
verifier('le schéma n\'est pas un preset', isset($presets['preset.schema']), false);
$evt = studioPreset('evt');
verifier('preset inconnu', studioPreset('../config'), null);

/* --- donnees de la table ronde : propres, sans erreur */
$jeu = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/evt-trs.json'), true);
[$propres, $erreurs] = studioValiderDonnees($evt, $jeu['donnees']);
verifier('table ronde : aucune erreur', $erreurs, []);
verifier('liste gardée', $propres['intervenants'], ['Dr Daniel Kancel', 'Dr Rabezanaka Pamphile', 'Dr Ratsimbason Jacques']);
verifier('gras ** gardé', $propres['moderation'], 'Pasteurs **Ranala Marc Arthur** et **Irina Andriamandranto**');

/* --- controles */
$d = $jeu['donnees'];
[, $e] = studioValiderDonnees($evt, array_merge($d, ['titre' => str_repeat('a', 91)]));
verifier('titre de 91 caractères refusé', $e, ['« Titre » : au plus 90 caractères.']);
[, $e] = studioValiderDonnees($evt, array_merge($d, ['titre' => str_repeat('é', 90)]));
verifier('90 caractères accentués acceptés (mb_strlen)', $e, []);
[, $e] = studioValiderDonnees($evt, array_merge($d, ['titre' => "  \t "]));
verifier('titre vide : à remplir', $e, ['« Titre » est à remplir.']);
[, $e] = studioValiderDonnees($evt, array_merge($d, ['intervenants' => ['A', 'B', 'C', 'D', 'E']]));
verifier('5 intervenants refusés', $e, ['« Intervenants » : au plus 4 éléments.']);
[$p, $e] = studioValiderDonnees($evt, array_merge($d, ['intervenants' => ['', ' X ', '']]));
verifier('éléments vides retirés', [$p['intervenants'], $e], [['X'], []]);
[, $e] = studioValiderDonnees($evt, array_merge($d, ['date' => '2026-02-30']));
verifier('date impossible', in_array('« Date » : date invalide.', $e, true), true);
[$p] = studioValiderDonnees($evt, array_merge($d, ['heure' => '9:05']));
verifier('heure 9:05 -> 9h05', $p['heure'], '9h05');
[, $e] = studioValiderDonnees($evt, array_merge($d, ['heure' => '25h']));
verifier('heure 25h refusée', in_array("« Heure » : une heure s'écrit 15 ou 15h30.", $e, true), true);
[$p, $e] = studioValiderDonnees($evt, array_merge($d, ['sousCode' => 'XYZ']));
verifier('sous-code inconnu refusé', [$p['sousCode'], $e], ['', ['« Code de la catégorie » : code inconnu.']]);
[$p] = studioValiderDonnees($evt, array_merge($d, ['lieu' => "Zur\x07cher\r\n  Hall", 'inconnu' => 'x']));
verifier('caractères de contrôle et espaces nettoyés', $p['lieu'], 'Zurcher Hall');
verifier('champ inconnu ignoré', isset($p['inconnu']), false);
[$p] = studioValiderDonnees($evt, array_merge($d, ['titre' => ['tableau']]));
verifier('valeur non textuelle -> vide', $p['titre'], '');

/* --- nom des fichiers : UAZ_{CODE}_{AAAA-MM-JJ}_{titre}_{format}.{ext} */
verifier('nom du fichier (sous-code, date, titre coupé au mot)', studioNomFichier($evt, $propres, '4:5', 'png'),
    'UAZ_TRS_2026-10-22_la-preconnaissance-et-la-predestination-divines-dans-la_4x5.png');
verifier('sans sous-code : EVT ; story', studioNomFichier($evt, array_merge($propres, ['sousCode' => '']), '9:16', 'jpg'),
    'UAZ_EVT_2026-10-22_la-preconnaissance-et-la-predestination-divines-dans-la_9x16.jpg');
verifier('sans date : le jour de l\'export', studioNomFichier($evt, array_merge($propres, ['date' => '']), 'A4', 'png'),
    'UAZ_TRS_' . date('Y-m-d') . '_la-preconnaissance-et-la-predestination-divines-dans-la_A4.png');
verifier('sans titre : l\'id du preset', studioNomFichier($evt, array_merge($propres, ['titre' => '']), '1:1', 'png'), 'UAZ_TRS_2026-10-22_evt_1x1.png');
verifier('slug : accents, apostrophes, ligatures', studioSlug("L'Œuvre « Ça marche ! » d’Élie"), 'l-oeuvre-ca-marche-d-elie');

/* --- les presets de l'etape 2 : leurs donnees de test passent, leurs noms de fichiers */
verifier('six presets', array_keys(studioPresets()), ['ann', 'com', 'evt', 'fel', 'ins', 'rec']);
$attendus = [
    'ann' => 'UAZ_ANN_' . date('Y-m-d') . '_fermeture-exceptionnelle-de-la-bibliotheque-de-test_4x5.png',
    'com' => 'UAZ_COM_2026-11-02_report-de-la-journee-d-exemple-du-campus_4x5.png',
    'fel' => 'UAZ_FEL_2026-11-20_prenom-test_4x5.png',
    'ins' => 'UAZ_INS_2027-08-31_inscriptions-ouvertes_4x5.png',
    'rec' => 'UAZ_REC_2026-12-15_poste-de-test_4x5.png',
];
foreach ($attendus as $id => $nom) {
    $p = studioPreset($id);
    $j = json_decode((string) file_get_contents(__DIR__ . "/studio/donnees/$id-test.json"), true);
    [$propres, $e] = studioValiderDonnees($p, $j['donnees']);
    verifier("$id : données de test sans erreur", $e, []);
    verifier("$id : nom du fichier", studioNomFichier($p, $propres, '4:5', 'png'), $nom);
}
[, $e] = studioValiderDonnees(studioPreset('ins'), array_merge(json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/ins-test.json'), true)['donnees'], ['filieres' => ['A', 'B']]));
verifier('INS : 2 filières refusées', $e, ['« Facultés ou filières » : au moins 3 éléments.']);
[, $e] = studioValiderDonnees(studioPreset('rec'), array_merge(json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/rec-test.json'), true)['donnees'], ['email' => str_repeat('a', 40) . '@exemple.mg']));
verifier('REC : adresse de plus de 45 caractères refusée', $e, ['« Adresse e-mail pour postuler » : au plus 45 caractères.']);
verifier('slug : 60 caractères au plus', strlen(studioSlug(str_repeat('mot ', 40))) <= 60, true);

/* --- etape 3 : adresse du QR code, texte long a paragraphes, PDF de l'imprimeur, ZIP */
$ins = studioPreset('ins');
$dIns = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/ins-test.json'), true)['donnees'];
[$p, $e] = studioValiderDonnees($ins, array_merge($dIns, ['qrUrl' => ' https://exemple.mg/inscription-test ']));
verifier('QR : adresse https acceptée (espaces retirés)', [$p['qrUrl'], $e], ['https://exemple.mg/inscription-test', []]);
foreach (['exemple.mg/inscription', 'javascript:alert(1)', 'ftp://exemple.mg/f', 'https://'] as $mauvaise) {
	[$p, $e] = studioValiderDonnees($ins, array_merge($dIns, ['qrUrl' => $mauvaise]));
	verifier("QR : « $mauvaise » refusée", [$p['qrUrl'], $e], ['', ['« Adresse du QR code » : une adresse complète, qui commence par https://']]);
}
$com = studioPreset('com');
$dCom = json_decode((string) file_get_contents(__DIR__ . '/studio/donnees/com-test.json'), true)['donnees'];
[$p, $e] = studioValiderDonnees($com, array_merge($dCom, ['suite' => "  Premier   paragraphe.\r\n\r\n\x07Deuxième\tparagraphe.  \n \n"]));
verifier('texte long : un paragraphe par ligne, lignes vides et caractères de contrôle retirés', [$p['suite'], $e], ["Premier paragraphe.\nDeuxième paragraphe.", []]);
[, $e] = studioValiderDonnees($com, array_merge($dCom, ['suite' => str_repeat('a', 2401)]));
verifier('texte long : 2 400 caractères au plus', $e, ['« Suite du texte (A4) » : au plus 2400 caractères.']);
[$p] = studioValiderDonnees($com, array_merge($dCom, ['paragraphe1' => "Une ligne\navec retour"]));
verifier('texte court : les retours à la ligne deviennent des espaces', $p['paragraphe1'], 'Une ligne avec retour');
[$pEvt] = studioValiderDonnees($evt, $jeu['donnees']);
verifier('nom du PDF de l\'imprimeur', studioNomFichier($evt, $pEvt, 'A4', 'pdf', 'imprimeur'),
	'UAZ_TRS_2026-10-22_la-preconnaissance-et-la-predestination-divines-dans-la_A4-imprimeur.pdf');
verifier('« Tout exporter » : PNG et PDF par format, les deux PDF en A4',
	array_map(static fn ($x) => $x['format'] . ' ' . $x['sortie'] . ($x['impression'] ? ' imprimeur' : ''), studioElementsLot($evt)),
	['4:5 png', '4:5 pdf', '1:1 png', '1:1 pdf', '9:16 png', '9:16 pdf', 'A4 pdf', 'A4 pdf imprimeur']);
$zip = studioZip(['a.png' => "\x89PNG-test", 'b.pdf' => '%PDF-test']);
$tmpZip = tempnam(sys_get_temp_dir(), 'zt');
file_put_contents($tmpZip, $zip);
$z = new ZipArchive();
$lus = [];
if ($z->open($tmpZip) === true) {
	for ($i = 0; $i < $z->numFiles; $i++) { $s = $z->statIndex($i); $lus[$s['name']] = [$z->getFromIndex($i), $s['comp_method'] === ZipArchive::CM_STORE]; }
	$z->close();
}
@unlink($tmpZip);
verifier('ZIP : les fichiers, tels quels (rangés sans recompression)', $lus, ['a.png' => ["\x89PNG-test", true], 'b.pdf' => ['%PDF-test', true]]);

/* --- etape 4 : options d'une image (recadrage, reglages), bornees */
$portrait = studioPreset('fel')['images'][0];
verifier('options d\'image par défaut', studioOptionsImage([], $portrait), [
	'fondre' => false, 'ajustement' => 'remplir',
	'cadrage' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0],
	'reglages' => ['lumiere' => 0, 'contraste' => 0, 'saturation' => 0, 'mode' => 'couleur'],
]);
verifier('options d\'image bornées (point focal hors de l\'image, zoom 10, réglages extrêmes, mode inconnu)', studioOptionsImage([
	'fondre' => 1, 'ajustement' => 'etirer',
	'cadrage' => ['x' => -2, 'y' => '0.25', 'zoom' => 10],
	'reglages' => ['lumiere' => 500, 'contraste' => '-12.6', 'saturation' => 'abc', 'mode' => '<script>'],
], $portrait), [
	'fondre' => true, 'ajustement' => 'remplir',
	'cadrage' => ['x' => 0.0, 'y' => 0.25, 'zoom' => 4.0],
	'reglages' => ['lumiere' => 100, 'contraste' => -13, 'saturation' => 0, 'mode' => 'couleur'],
]);
verifier('options d\'image : duotone gardé', studioOptionsImage(['reglages' => ['mode' => 'duotone']], $portrait)['reglages']['mode'], 'duotone');

/* --- images : vrai type, sans metadonnees, 4 000 px au plus, transparence gardee */
$tmp = sys_get_temp_dir() . '/studio-test-' . getmypid();
@mkdir($tmp);
$jpg = imagecreatetruecolor(300, 200);
imagefill($jpg, 0, 0, imagecolorallocate($jpg, 200, 100, 50));
imagejpeg($jpg, "$tmp/a.jpg", 90);
/* un segment APP1 « Exif » glisse dans le JPEG : il ne doit pas ressortir */
$brut = file_get_contents("$tmp/a.jpg");
$exif = "Exif\0\0" . 'MM' . "\0\x2A\0\0\0\x08\0\0" . 'GPS-LATITUDE-SECRET';
file_put_contents("$tmp/a.jpg", substr($brut, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($brut, 2));
$im = studioPreparerImage("$tmp/a.jpg");
verifier('JPEG reencodé', [$im['type'], $im['largeur'], $im['hauteur']], ['image/jpeg', 300, 200]);
verifier('métadonnées retirées', strpos(base64_decode($im['base64']), 'GPS-LATITUDE-SECRET'), false);

$png = imagecreatetruecolor(5000, 100);
imagesavealpha($png, true);
imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
imagepng($png, "$tmp/b.png");
$im = studioPreparerImage("$tmp/b.png");
verifier('PNG transparent : gardé en PNG, réduit à 4 000 px', [$im['type'], $im['largeur'], $im['hauteur']], ['image/png', 4000, 80]);

file_put_contents("$tmp/c.jpg", "<?php echo 'pas une image'; ?>");
try { studioPreparerImage("$tmp/c.jpg"); $refus = ''; } catch (StudioErreur $x) { $refus = $x->getMessage(); }
verifier('faux JPEG refusé (vrai type lu)', $refus, 'Seules les images JPEG, PNG et WebP sont acceptées.');
array_map('unlink', glob("$tmp/*"));
@rmdir($tmp);

/* --- intitulés réécrits (« Lieu », « Heure »…) */
verifier('EVT : intitulés réécrivables', studioEtiquettes($evt), ['Date', 'Heure', 'Lieu', 'Invité', 'Modération']);
[$p] = studioValiderDonnees($evt, $jeu['donnees'] + ['_etiquettes' => ['Lieu' => '  Adresse  ', 'Heure' => '', 'Date' => 'Date', 'Université Adventiste Zurcher' => 'X', 'Inconnu' => 'Y', 'Modération' => 'A {lieu} B', 'Invité' => ['x']]]);
verifier('intitulés : nettoyés, vide permis, inchangé ôté, identité et inconnus ignorés, accolades ôtées', $p['_etiquettes'], ['Lieu' => 'Adresse', 'Heure' => '', 'Modération' => 'A lieu B']);
[$p2] = studioValiderDonnees($evt, $jeu['donnees']);
verifier('sans réécriture : pas de clé _etiquettes', array_key_exists('_etiquettes', $p2), false);
verifier('intitulés coupés à 60 caractères', mb_strlen(studioEtiquettesPropres($evt, ['Lieu' => str_repeat('a', 200)])['Lieu']), 60);

echo ($total - $echecs) . " / $total vérifications réussies.\n";
exit($echecs ? 1 : 0);
