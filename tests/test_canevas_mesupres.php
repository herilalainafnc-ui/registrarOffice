<?php
/*
 * Canevas de collecte de la MESupReS en Excel (data/canevas.mesupres.php, app/canevas-mesupres.php).
 *
 *   - les libelles que le canevas attend (parcours, nationalite) sortent des variantes ecrites sur les fiches ;
 *   - le classeur produit est un vrai .xlsx : zip, XML valide, feuille ETUDIANTS remplie (une ligne par inscrit
 *     de l'annee, colonnes A a R), les autres feuilles et les listes deroulantes du ministere gardees, le modele
 *     du depot sans aucune donnee personnelle ;
 *   - l'acces : refuse sans connexion, a un enseignant, avec une methode ou une annee invalide (serveur local
 *     requis pour cette partie ; sinon elle est passee).
 *
 *   php tests/test_canevas_mesupres.php
 */
$base = dirname(__DIR__);
require "$base/data/config.php";
require "$base/data/canevas.mesupres.php";

ob_start();   // la sortie attend la fin : les sessions de l'essai se creent en cours de route
$ok = 0; $ko = 0;
function verifier(string $nom, bool $cond, string $detail = ''): void
{
    global $ok, $ko;
    if ($cond) { $ok++; echo "  ok  $nom\n"; }
    else { $ko++; echo "  KO  $nom" . ($detail !== '' ? "  ($detail)" : '') . "\n"; }
}

echo "Libelles du canevas\n";
verifier('parcours : « Communication des entreprises » -> Communication en entreprise', canevasParcours('COMM', 'Communication des entreprises') === 'Communication en entreprise');
verifier('parcours : « Socio-Linguistique » (casse) -> Socio-linguistique', canevasParcours('LANG', 'Socio-Linguistique') === 'Socio-linguistique');
verifier('parcours : « Maintenance et administration de réseaux » -> Administration et réseaux informatique', canevasParcours('INFO', 'Maintenance et administration de réseaux') === 'Administration et réseaux informatique');
verifier('parcours : « None » ou vide -> vide', canevasParcours('THEO', 'None') === '' && canevasParcours('INFO', '') === '');
verifier('parcours inconnu : garde tel quel', canevasParcours('COMM', 'Leadership et communication') === 'Leadership et communication');
verifier('nationalite : Malgache -> Malagasy', canevasNationalite('Malgache') === 'Malagasy' && canevasNationalite('Malagasy') === 'Malagasy');
verifier('nationalite : Norwegian, Français -> Européen', canevasNationalite('Norwegian') === 'Européen' && canevasNationalite('Français') === 'Européen');
verifier('nationalite : Mauricien -> Africain, inconnue -> Autre, vide -> vide', canevasNationalite('Mauricien') === 'Africain' && canevasNationalite('Martien') === 'Autre' && canevasNationalite('') === '');

echo "Le modele du depot\n";
verifier('le modele existe et s\'ouvre', is_file(CANEVAS_MESUPRES_MODELE));
$zip = new ZipArchive();
verifier('...en zip', $zip->open(CANEVAS_MESUPRES_MODELE) === true);
$contenu = '';
for ($i = 0; $i < $zip->numFiles; $i++) { $n = $zip->getNameIndex($i); if (substr($n, -4) === '.xml') { $contenu .= $zip->getFromIndex($i); } }
verifier('...sans nom, matricule ni telephone d\'une annee passee', !preg_match('/\b0[0-9]{2} [0-9]{2} [0-9]{3} [0-9]{2}\b|\b[0-9]{12}\b/', $contenu));
verifier('...avec ses 7 feuilles', substr_count($zip->getFromName('xl/workbook.xml'), '<sheet ') === 7);
$zip->close();

$dtb = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
$annee = '2025 - 2026';

/* Deux comptes de l'essai : un du registrariat (#27) et un enseignant (#190), sessions locales. */
$session = static function (int $id) use ($dtb): string {
    $u = $dtb->query('SELECT id, level, privilege, pseudo, password FROM compt_utilisateur WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    session_save_path('C:/laragon/tmp');
    $sid = 'testcnv' . bin2hex(random_bytes(4));
    session_id($sid);
    session_start();
    $_SESSION['user_id'] = (int) $u['id']; $_SESSION['user_level'] = (int) $u['level']; $_SESSION['user_privilege'] = (string) $u['privilege'];
    $_SESSION['infinit_pseudo'] = (string) $u['pseudo']; $_SESSION['infinit_password'] = (string) $u['password'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); $_SESSION['csrf_token_time'] = time();
    session_write_close();
    return $sid;
};
$admin = $session(27);
$enseignant = $session(190);
$attendus = (int) $dtb->query('SELECT COUNT(*) FROM tbl_2024_etudiant WHERE ' . etudiantsDeLAnnee($dtb, $annee))->fetchColumn();
echo "Le classeur de $annee ($attendus inscrits)\n";
if ($attendus === 0) {
    echo "  (aucun inscrit en base locale pour cette annee : le reste est passe)\n";
} else {
    $lignes = canevasMesupresLignes($dtb, $annee);
    verifier('une ligne par inscrit', count($lignes) === $attendus, count($lignes) . ' / ' . $attendus);
    verifier('niveau parmi L1 (RN), L1, L2, L3, M1, M2', !array_diff(array_unique(array_column($lignes, 'niveau')), ['L1 (RN)', 'L1', 'L2', 'L3', 'M1', 'M2']));
    verifier('code de redoublement parmi les trois du canevas', !array_diff(array_unique(array_column($lignes, 'code')), ['N (Nouveau)', 'R (Redoublant)', 'T+ (Triplant ou plus)']));
    verifier('sexe F ou M, nom en majuscules, date JJ-MM-AAAA', !array_diff(array_unique(array_column($lignes, 'sexe')), ['F', 'M'])
        && !array_filter($lignes, static fn ($l) => $l['nom'] !== mb_strtoupper($l['nom'], 'UTF-8'))
        && !array_filter($lignes, static fn ($l) => $l['naissance'] !== '' && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $l['naissance'])));
    verifier('masters en formation a distance, licences en formation initiale', !array_filter($lignes, static fn ($l) => in_array($l['niveau'], ['M1', 'M2'], true) !== ($l['formation'] === CANEVAS_FORMATION_FOAD)));
    verifier('CIN en chiffres seulement', !array_filter($lignes, static fn ($l) => $l['cin'] !== '' && !ctype_digit($l['cin'])));
    $mention = $lignes[0]['mention'];
    $sigle = (string) $dtb->query('SELECT filiere_sigle FROM filiere WHERE filiere_description = ' . $dtb->quote($mention))->fetchColumn();
    $une = canevasMesupresLignes($dtb, $annee, $sigle);
    verifier('une seule mention : seulement ses etudiants', $une && !array_filter($une, static fn ($l) => $l['mention'] !== $mention) && count($une) < count($lignes), count($une) . ' en ' . $mention);

    $f = canevasMesupresXlsx($lignes, $annee);
    $z = new ZipArchive();
    verifier('classeur : un zip', $z->open($f) === true);
    libxml_use_internal_errors(true);
    $valide = true;
    for ($i = 1; $i <= 7; $i++) { if (!simplexml_load_string($z->getFromName("xl/worksheets/sheet$i.xml"))) { $valide = false; } }
    verifier('classeur : les 7 feuilles sont du XML valide', $valide && simplexml_load_string($z->getFromName('xl/sharedStrings.xml')) !== false);
    $feuille = $z->getFromName('xl/worksheets/sheet1.xml');
    verifier('classeur : une ligne de feuille par inscrit (16 a ' . (15 + count($lignes)) . ')', substr_count($feuille, '<row r="') === 15 + count($lignes) - 0 || preg_match('/<row r="' . (15 + count($lignes)) . '"/', $feuille) === 1);
    verifier('classeur : les listes deroulantes couvrent toutes les lignes', strpos($feuille, 'sqref="M16:M' . max(514, 15 + count($lignes)) . '"') !== false);
    verifier('classeur : le titre porte l\'annee de l\'export', strpos($z->getFromName('xl/sharedStrings.xml'), 'UNIVERSITAIRE 2025-2026') !== false);
    $z->close();
    $autre = canevasMesupresXlsx([], '2030 - 2031');
    $z2 = new ZipArchive(); $z2->open($autre);
    verifier('autre annee : les titres suivent (2030-2031)', strpos($z2->getFromName('xl/sharedStrings.xml'), 'UNIVERSITAIRE 2030-2031') !== false);
    $z2->close();
    @unlink($f); @unlink($autre);
}

echo "Acces (serveur local)\n";
$hote = 'http://127.0.0.1:8137';
$sonde = @file_get_contents($hote . '/login', false, stream_context_create(['http' => ['timeout' => 3]]));
if ($sonde === false) {
    echo "  (serveur local absent : cette partie est passee)\n";
} else {
    $requete = static function (string $methode, ?string $sid, array $champs = [], array $entetes = []) use ($hote): array {
        $h = ($sid ? "Cookie: PHPSESSID=$sid\r\n" : '') . "Origin: $hote\r\n";
        foreach ($entetes as $e) { $h .= $e . "\r\n"; }
        $o = ['http' => ['method' => $methode, 'header' => $h . ($methode === 'POST' ? "Content-Type: application/x-www-form-urlencoded\r\n" : ''), 'ignore_errors' => true, 'timeout' => 30, 'follow_location' => 0]];
        if ($methode === 'POST') { $o['http']['content'] = http_build_query($champs); }
        $corps = @file_get_contents($hote . '/app/canevas-mesupres', false, stream_context_create($o));
        $statut = 0;
        foreach ($http_response_header ?? [] as $x) { if (preg_match('#^HTTP/\S+\s+(\d+)#', $x, $m)) { $statut = (int) $m[1]; } }
        return [$statut, (string) $corps, $http_response_header ?? []];
    };
    [$s] = $requete('POST', null, ['annee' => $annee]);
    verifier('sans connexion : redirige vers la connexion', in_array($s, [302, 401], true), (string) $s);
    [$s] = $requete('POST', $enseignant, ['annee' => $annee, 'mention' => 'TOUT']);
    verifier('enseignant : refuse (403)', $s === 403, (string) $s);
    [$s] = $requete('GET', $admin);
    verifier('GET : refuse (405)', $s === 405, (string) $s);
    [$s] = $requete('POST', $admin, ['annee' => 'abc']);
    verifier('annee invalide : 400', $s === 400, (string) $s);
    [$s] = $requete('POST', $admin, ['annee' => $annee], ['Origin: https://autre.example']);
    verifier('depuis un autre site : refuse', $s === 403, (string) $s);
    [$s, $corps, $entetes] = $requete('POST', $admin, ['annee' => $annee, 'mention' => 'TOUT']);
    $type = ''; foreach ($entetes as $x) { if (stripos($x, 'Content-Type:') === 0) { $type = $x; } }
    verifier('registrariat : un .xlsx se telecharge', $s === 200 && strpos($type, 'spreadsheetml.sheet') !== false && substr($corps, 0, 2) === 'PK', $s . ' ' . $type);
    @unlink('C:/laragon/tmp/sess_' . $admin); @unlink('C:/laragon/tmp/sess_' . $enseignant);
}

echo "\n$ok reussite(s), $ko echec(s)\n";
ob_end_flush();
exit($ko ? 1 : 0);
