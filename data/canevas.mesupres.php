<?php
/**
 * Canevas de collecte de la MESupReS (liste des etudiants inscrits d'une annee), en Excel.
 *
 * Le ministere envoie chaque annee un classeur a remplir (« CANEVAS DE COLLECTE_2025-2026_IES PRIVE »,
 * feuille ETUDIANTS : une ligne par etudiant inscrit, 18 colonnes). Ce fichier produit ce classeur,
 * rempli avec les inscrits de l'annee choisie : data/modeles/canevas-mesupres.xlsx est le classeur du
 * ministere (mise en forme, logos, listes deroulantes, autres feuilles) sans aucune donnee ; seule la
 * feuille ETUDIANTS est ecrite ici. Si le ministere change de canevas, on remplace le modele (et on
 * ajuste les colonnes ci-dessous).
 *
 * D'ou vient chaque colonne :
 *   INSTITUTION, DOMAINE, MENTION, PARCOURS, TYPE DE FORMATION, NIVEAU : l'inscription de l'annee (mention,
 *       parcours et niveau de la session ; la fiche de l'etudiant quand l'inscription ne les porte pas),
 *       ecrits comme dans le canevas de l'an passe (voir DOMAINES et canevasParcours) ;
 *   NOM, PRENOMS, SEXE, DATE DE NAISSANCE (JJ-MM-AAAA), CIN, NATIONALITE, ADRESSE, TELEPHONE : la fiche ;
 *   ANNEE et SERIE DU BACC : t_2024_bacc (a defaut, l'annee du diplome precedent pour un master) ;
 *   CODE DE REDOUBLEMENT : N (nouveau, ou qui passe a l'annee suivante), R (redoublant : meme niveau deja
 *       suivi une annee passee), T+ (deux fois ou plus).
 * Les etudiants des inscriptions de l'annee, une fiche active par matricule : etudiantsDeLAnnee().
 */

require_once(__DIR__ . '/etudiants.annee.php');

const CANEVAS_MESUPRES_MODELE = __DIR__ . '/modeles/canevas-mesupres.xlsx';
const CANEVAS_INSTITUTION     = 'Université Adventiste Zurcher';
const CANEVAS_FORMATION_FI    = 'Formation Initial (FI)';
const CANEVAS_FORMATION_FOAD  = 'Formation à Distance (FOAD)';

/* Le domaine de chaque mention, comme le ministere l'a recu l'an passe (la theologie y figurait sous « Art et lettre »). */
const CANEVAS_DOMAINES = [
    'THEO' => 'Art et lettre',
    'COMM' => 'ALSH',
    'LANG' => 'ALSH',
    'NURS' => 'SSANTE',
    'DROI' => 'SSTE',
    'GEST' => 'SSTE',
    'INFO' => 'STECH',
    'EDUC' => 'SE',
];

/** Texte sans accents ni majuscules, pour comparer des libelles ecrits a la main. */
function canevasNorm($t): string
{
    $t = mb_strtolower(trim((string) $t), 'UTF-8');
    $t = strtr($t, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
                    'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'"]);
    return trim((string) preg_replace('/\s+/', ' ', $t));
}

/** Le parcours tel que le canevas l'ecrit (les fiches portent des variantes : « Communication des entreprises »...). */
function canevasParcours(string $sigle, string $brut): string
{
    $n = canevasNorm($brut);
    if ($n === '' || $n === 'none' || $n === 'null' || $n === '-') {
        return '';
    }
    $regles = [
        'THEO' => ['pastoral' => 'Théologie pastorale', 'biblique' => 'Théologie biblique'],
        'COMM' => ['entreprise' => 'Communication en entreprise', 'media' => 'Média et journalisme', 'journalisme' => 'Média et journalisme'],
        'LANG' => ['traduction' => 'Traduction et interprétation', 'socio' => 'Socio-linguistique'],
        'NURS' => ['infirmerie' => 'Infirmerie générale', 'maieutique' => 'Maïeutique'],
        'DROI' => ['priv' => 'Droit Privé', 'public' => 'Droit Public'],
        'GEST' => ['financ' => 'Finances et comptabilité', 'compta' => 'Finances et comptabilité', 'management' => 'Management des affaires'],
        'INFO' => ['systeme' => "Systèmes d'information et génie logiciel", 'genie logiciel' => "Systèmes d'information et génie logiciel",
                   'reseau' => 'Administration et réseaux informatique'],
    ];
    foreach ($regles[$sigle] ?? [] as $motif => $officiel) {
        if (strpos($n, $motif) !== false) {
            return $officiel;
        }
    }
    return trim($brut);   // un parcours que le canevas ne connait pas : tel quel
}

/** La nationalite parmi celles de la liste deroulante du canevas. */
function canevasNationalite($brut): string
{
    $n = canevasNorm($brut);
    if ($n === '') {
        return '';
    }
    if (preg_match('/malag|malgach|madagas/', $n)) { return 'Malagasy'; }
    if (strpos($n, 'comor') !== false) { return 'Comorien'; }
    if (strpos($n, 'camer') !== false) { return 'Camerounais'; }
    if (preg_match('/maur|kenya|burundi|congo|senegal|togo|benin|ivoir|ghana|nigeri|rwand|tanzan|ethiop|afri|zambi|zimbab|malawi|mozamb|gabon|tchad|mali|burkina|niger|maroc|alger|tunis|egypt/', $n)) { return 'Africain'; }
    if (preg_match('/fran|reunion|norv|norw|suis|swiss|belg|allem|german|ital|espagn|spain|portug|angl|british|brit|ameri|usa|canad|danois|suedo|suede|finland|europ|russe|polon|roumain|grec|irland|neerland|hollan|autrich/', $n)) { return 'Européen'; }
    if (preg_match('/chin|japon|indi|corea|coree|philip|viet|thai|asia|pakistan|banglad|nepal|sri lanka|indones|malais/', $n)) { return 'Asiatique'; }
    return 'Autre';
}

/** Les inscrits de l'annee, ligne par ligne, dans l'ordre du canevas. */
function canevasMesupresLignes(PDO $dtb, string $annee, string $mention = 'TOUT'): array
{
    /* mentions : sigle -> [description, ordre] */
    $filieres = [];
    $ordre = 0;
    foreach ($dtb->query('SELECT filiere_sigle, filiere_description, domaine_signe FROM filiere ORDER BY filiere_id') as $f) {
        $filieres[$f['filiere_sigle']] = ['description' => $f['filiere_description'], 'domaine' => (string) $f['domaine_signe'], 'ordre' => $ordre++];
    }
    $parDescription = [];
    foreach ($filieres as $sigle => $f) { $parDescription[canevasNorm($f['description'])] = $sigle; }
    $resoudre = static function ($brut) use ($filieres, $parDescription): string {
        $b = trim((string) $brut);
        if (isset($filieres[strtoupper($b)])) { return strtoupper($b); }
        return $parDescription[canevasNorm($b)] ?? '';
    };

    /* les fiches (une par matricule) */
    $fiches = [];
    foreach ($dtb->query('SELECT * FROM tbl_2024_etudiant WHERE ' . etudiantsDeLAnnee($dtb, $annee)) as $e) {
        $fiches[$e['student_id']] = $e;
    }
    if (!$fiches) {
        return [];
    }

    /* l'inscription de l'annee : la derniere session fait foi, le niveau en revient a la fiche s'il manque */
    $ins = [];
    $q = $dtb->prepare('SELECT i.student_id, i.etude_mention, i.parcours_std, i.niveau_std, i.adresse_actuel_std
        FROM t_2024_inscription_session i JOIN t_2023_session s ON s.session_id = i.session_id
        WHERE s.session_year = :a AND COALESCE(i.`remove`, 0) != 1 ORDER BY i.session_id ASC');
    $q->execute(['a' => $annee]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cur = $ins[$r['student_id']] ?? ['mention' => '', 'parcours' => '', 'niveau' => 0, 'adresse' => ''];
        if (trim((string) $r['etude_mention']) !== '') { $cur['mention'] = trim((string) $r['etude_mention']); }
        if (trim((string) $r['parcours_std']) !== '') { $cur['parcours'] = trim((string) $r['parcours_std']); }
        if ((int) $r['niveau_std'] > 0) { $cur['niveau'] = (int) $r['niveau_std']; }
        if (trim((string) $r['adresse_actuel_std']) !== '') { $cur['adresse'] = trim((string) $r['adresse_actuel_std']); }
        $ins[$r['student_id']] = $cur;
    }

    /* les niveaux suivis les annees passees : un meme niveau deja suivi = redoublement */
    $passes = [];
    $q = $dtb->prepare('SELECT i.student_id, s.session_year, MAX(i.niveau_std) AS niv
        FROM t_2024_inscription_session i JOIN t_2023_session s ON s.session_id = i.session_id
        WHERE s.session_year < :a AND COALESCE(i.`remove`, 0) != 1 AND i.niveau_std > 0
        GROUP BY i.student_id, s.session_year');
    $q->execute(['a' => $annee]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $passes[$r['student_id']][(int) $r['niv']] = ($passes[$r['student_id']][(int) $r['niv']] ?? 0) + 1;
    }

    $bacc = [];
    foreach ($dtb->query('SELECT student_id, date_obtent, bacc_serie FROM t_2024_bacc ORDER BY id ASC') as $b) { $bacc[$b['student_id']] = $b; }
    $diplomes = [];
    foreach ($dtb->query('SELECT student_id, date_obtent FROM t_2024_diplome_preced ORDER BY id ASC') as $d) { $diplomes[$d['student_id']] = $d; }

    $debut = (int) substr($annee, 0, 4);
    $lignes = [];
    foreach ($fiches as $sid => $e) {
        $i = $ins[$sid] ?? ['mention' => '', 'parcours' => '', 'niveau' => 0, 'adresse' => ''];
        $sigle = $resoudre($i['mention']) ?: $resoudre($e['etude_envisage']);
        if ($mention !== 'TOUT' && $mention !== '' && $sigle !== $mention) {
            continue;
        }
        $niveau = $i['niveau'] > 0 ? $i['niveau'] : (int) $e['annee_etude'];
        $niveauTexte = $niveau === 0 ? 'L1 (RN)' : ($niveau <= 3 ? 'L' . $niveau : ($niveau === 4 ? 'M1' : ($niveau === 5 ? 'M2' : '')));
        $master = $niveau >= 4;

        $parcours = canevasParcours($sigle, $i['parcours'] !== '' ? $i['parcours'] : (string) $e['etude_option']);

        $naissance = '';
        $ageMineur = false;
        if (!empty($e['dateNaissance']) && $e['dateNaissance'] !== '0000-00-00') {
            $t = strtotime($e['dateNaissance']);
            if ($t) {
                $naissance = date('d-m-Y', $t);
                $ageMineur = $t > strtotime(($debut - 18) . '-10-01');   // « Ne pas remplir si l'etudiant est mineur »
            }
        }
        $cin = preg_replace('/\D+/', '', (string) $e['num_cin']);
        if ($cin === '' || (int) $cin === 0 || $ageMineur) { $cin = ''; }

        $anneeBacc = '';
        $serie = '';
        if (isset($bacc[$sid])) {
            $anneeBacc = substr((string) $bacc[$sid]['date_obtent'], 0, 4);
            $serie = trim((string) $bacc[$sid]['bacc_serie']);
        } elseif (isset($diplomes[$sid])) {
            $anneeBacc = substr((string) $diplomes[$sid]['date_obtent'], 0, 4);
        }
        if ($serie === '' && trim((string) ($e['bacc_serie'] ?? '')) !== '') { $serie = trim((string) $e['bacc_serie']); }
        if ($anneeBacc === '0000') { $anneeBacc = ''; }

        $fois = $niveau > 0 ? (int) ($passes[$sid][$niveau] ?? 0) : 0;
        $code = $fois >= 2 ? 'T+ (Triplant ou plus)' : ($fois === 1 ? 'R (Redoublant)' : 'N (Nouveau)');

        $adresse = trim((string) preg_replace('/\s*\(\s*\)\s*$/', '', $i['adresse'] !== '' ? $i['adresse'] : (string) $e['student_adresse']));

        $lignes[] = [
            'institution' => CANEVAS_INSTITUTION,
            'domaine'     => CANEVAS_DOMAINES[$sigle] ?? ($filieres[$sigle]['domaine'] ?? ''),
            'mention'     => $filieres[$sigle]['description'] ?? trim((string) ($i['mention'] !== '' ? $i['mention'] : $e['etude_envisage'])),
            'parcours'    => $parcours,
            'formation'   => $master ? CANEVAS_FORMATION_FOAD : CANEVAS_FORMATION_FI,
            'niveau'      => $niveauTexte,
            'nom'         => mb_strtoupper(trim((string) $e['student_nom']), 'UTF-8'),
            'prenoms'     => trim((string) $e['student_prenom']),
            'sexe'        => ((string) $e['sex'] === '1' || canevasNorm($e['sex']) === 'masculin') ? 'M' : 'F',
            'naissance'   => $naissance,
            'cin'         => $cin,
            'nationalite' => canevasNationalite($e['nationalite']),
            'bacc_annee'  => $anneeBacc,
            'bacc_serie'  => $serie,
            'code'        => $code,
            'adresse'     => $adresse,
            'telephone'   => trim((string) $e['student_tel']),
            '_ordre'      => [$filieres[$sigle]['ordre'] ?? 99, canevasNorm($parcours), $niveau === 0 ? 0 : $niveau, canevasNorm($e['student_nom']), canevasNorm($e['student_prenom'])],
        ];
    }

    usort($lignes, static fn ($a, $b) => $a['_ordre'] <=> $b['_ordre']);
    foreach ($lignes as &$l) { unset($l['_ordre']); }
    return $lignes;
}

/** Du texte sans caractere interdit en XML, echappe. */
function canevasXml($t): string
{
    $t = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $t);
    return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Ecrit le classeur : le modele du ministere, feuille ETUDIANTS remplie. Rend le chemin d'un fichier
 * temporaire (a effacer par l'appelant).
 */
function canevasMesupresXlsx(array $lignes, string $annee): string
{
    if (!class_exists('ZipArchive') || !is_file(CANEVAS_MESUPRES_MODELE)) {
        throw new RuntimeException('Le modele du canevas est introuvable ou l\'extension zip manque.');
    }
    $fichier = tempnam(sys_get_temp_dir(), 'canevas');
    copy(CANEVAS_MESUPRES_MODELE, $fichier);
    $zip = new ZipArchive();
    if ($zip->open($fichier) !== true) {
        throw new RuntimeException('Le modele du canevas ne s\'ouvre pas.');
    }

    /* les colonnes A a R, avec le style du canevas pour chacune */
    $colonnes = [
        'A' => [null, 71], 'B' => ['institution', 72], 'C' => ['domaine', 75], 'D' => ['mention', 76], 'E' => ['parcours', 76],
        'F' => ['formation', 75], 'G' => ['niveau', 75], 'H' => ['nom', 80], 'I' => ['prenoms', 80], 'J' => ['sexe', 81],
        'K' => ['naissance', 85], 'L' => ['cin', 85], 'M' => ['nationalite', 75], 'N' => ['bacc_annee', 75], 'O' => ['bacc_serie', 75],
        'P' => ['code', 75], 'Q' => ['adresse', 88], 'R' => ['telephone', 88],
    ];
    $xml = '';
    $n = 0;
    foreach ($lignes as $l) {
        $n++;
        $r = 15 + $n;
        $xml .= '<row r="' . $r . '" ht="14.25" customHeight="1">';
        foreach ($colonnes as $col => [$champ, $style]) {
            if ($champ === null) {
                $xml .= '<c r="A' . $r . '" s="' . $style . '"><v>' . $n . '</v></c>';
                continue;
            }
            $v = (string) $l[$champ];
            $xml .= $v === '' ? '<c r="' . $col . $r . '" s="' . $style . '"/>'
                : '<c r="' . $col . $r . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . canevasXml($v) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $derniere = max(514, 15 + $n);

    $feuille = $zip->getFromName('xl/worksheets/sheet1.xml');
    $feuille = preg_replace('#</sheetData>#', $xml . '</sheetData>', $feuille, 1);
    $feuille = preg_replace('#(sqref="[A-Z]+16:[A-Z]+)514"#', '${1}' . $derniere . '"', $feuille);
    $zip->addFromString('xl/worksheets/sheet1.xml', $feuille);

    /* les titres du classeur portent l'annee du modele : on met celle de l'export */
    $debut = (int) substr($annee, 0, 4);
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    $ss = str_replace(['2025-2026', '2024-2025'], [$debut . '-' . ($debut + 1), ($debut - 1) . '-' . $debut], $ss);
    $ss = preg_replace('/(ANNEE|Diplômés) 2025(?!-)/u', '${1} ' . $debut, $ss);
    $zip->addFromString('xl/sharedStrings.xml', $ss);

    $zip->close();
    return $fichier;
}
