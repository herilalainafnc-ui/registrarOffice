<?php
/**
 * Perimetre des comptes (data/perimetre.php) : un compte limite a un cycle ne
 * voit que ce cycle ; l'autre lui est invisible.
 *
 * Trois comptes : la secretaire academique de Licence (perimetre L), celle de
 * Master (M), et un superadmin sans perimetre, temoin que rien ne change
 * pour les autres. Pour chacun : listes et recherches, fiches ouvertes par
 * leur adresse, cours d'un enseignant, etudiants d'un cours, menu.
 *
 * Livraison 1. Les livraisons suivantes ajoutent leurs pages ici.
 *
 *   php tests/test_perimetre.php
 *
 * Il faut data/migration_perimetre_cycle.sql joue en local et Apache lance
 * (https://registrar.test). Lecture seule : aucune donnee n'est modifiee.
 */

chdir(dirname(__DIR__));
require 'data/config.php';
$dtb = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
$dtb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ok = 0; $ko = 0;
function verifie(string $quoi, $attendu, $obtenu) : void {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  ok    $quoi\n"; return; }
    $ko++;
    echo "  ECHEC $quoi\n        attendu: " . var_export($attendu, true)
       . "\n        obtenu : " . var_export($obtenu, true) . "\n";
}

const BASE = 'https://registrar.test';

/** Session pour un compte donne (par son identifiant). */
function session_compte(PDO $dtb, int $id) : string {
    $u = $dtb->query('SELECT id, level, privilege FROM compt_utilisateur WHERE id = ' . $id . ' AND etat = 1')
             ->fetch(PDO::FETCH_ASSOC);
    if (!$u) { return ''; }
    session_save_path('C:/laragon/tmp');
    $sid = 'pe' . substr(md5($id . microtime(true)), 0, 22);
    session_id($sid);
    session_start();
    $_SESSION = [
        'user_id' => $u['id'], 'user_level' => $u['level'],
        'user_privilege' => $u['privilege'], 'logged_in' => true,
        'LAST_ACTIVITY' => time(),
        'csrf_token' => bin2hex(random_bytes(32)), 'csrf_token_time' => time(),
    ];
    session_write_close();
    return $sid;
}

function appel(string $sid, string $chemin, ?array $post = null) : string {
    $ctx = stream_context_create([
        'http' => [
            'method' => $post === null ? 'GET' : 'POST',
            'header' => "Cookie: PHPSESSID=$sid\r\nX-Requested-With: XMLHttpRequest\r\n"
                      . ($post === null ? '' : "Content-Type: application/x-www-form-urlencoded\r\n"),
            'content' => $post === null ? '' : http_build_query($post),
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return (string) @file_get_contents(BASE . $chemin, false, $ctx);
}

$un = fn (string $q) => $dtb->query($q)->fetch(PDO::FETCH_ASSOC);
$perimetres = $dtb->query('SELECT id, pseudo, perimetre_cycle FROM compt_utilisateur WHERE perimetre_cycle IS NOT NULL AND etat = 1')
                  ->fetchAll(PDO::FETCH_ASSOC);
$idL = null; $idM = null;
foreach ($perimetres as $p) { if ($p['perimetre_cycle'] === 'L') { $idL = (int) $p['id']; } if ($p['perimetre_cycle'] === 'M') { $idM = (int) $p['id']; } }
if ($idL === null || $idM === null) {
    echo "Il faut un compte actif de perimetre L et un de perimetre M (data/migration_perimetre_cycle.sql).\n";
    exit(1);
}
$idAdmin = (int) $un("SELECT id FROM compt_utilisateur WHERE privilege = 'superadmin' AND etat = 1 AND perimetre_cycle IS NULL ORDER BY id LIMIT 1")['id'];

/* Jeux d'essai, lus dans la base : un de chaque cycle. */
$eL = $un("SELECT id, student_id, student_nom FROM tbl_2024_etudiant WHERE remove != 1 AND annee_etude = '3' AND student_nom <> '' ORDER BY id DESC LIMIT 1");
$eM = $un("SELECT id, student_id, student_nom FROM tbl_2024_etudiant WHERE remove != 1 AND annee_etude = '4' AND student_nom <> '' ORDER BY id DESC LIMIT 1");
$cL = $un("SELECT id, Sigle, title FROM t_2023_cours WHERE remove != 1 AND yearlevel = 2 AND title <> '' ORDER BY id DESC LIMIT 1");
$cM = $un("SELECT id, Sigle, title FROM t_2023_cours WHERE remove != 1 AND yearlevel = 4 AND title <> '' ORDER BY id DESC LIMIT 1");
$ens = $un("SELECT t.teacher_id FROM teacher t JOIN t_2023_cours c ON c.id_teacher = t.uid AND c.remove != 1
            GROUP BY t.teacher_id HAVING SUM(c.yearlevel IN (0,1,2,3)) > 0 AND SUM(c.yearlevel IN (4,5)) > 0 LIMIT 1");
/* Un cours de Licence ou un etudiant de Master a une note (sur les quatre annees affichees). */
$croise = $un("SELECT c.id AS cours_id, e.student_id
               FROM t_2023_notes n
               JOIN tbl_2024_etudiant e ON e.student_id = n.student_id AND e.remove != 1
                    AND e.annee_etude IN ('4','5') AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
               JOIN t_2023_cours c ON c.Sigle = n.sigle AND c.remove != 1 AND c.yearlevel IN (0,1,2,3)
               WHERE n.remove = 0 AND n.annee_scolaire IN ('" . implode("','", array_map(fn ($i) => (date('Y') - $i) . ' - ' . (date('Y') + 1 - $i), [0, 1, 2, 3])) . "')
               LIMIT 1");
$attendus = $un("SELECT SUM(annee_etude IN ('0','1','2','3')) AS L, SUM(annee_etude IN ('4','5')) AS M, COUNT(*) AS T
                 FROM tbl_2024_etudiant WHERE remove != 1");
$attendusCours = $un("SELECT SUM(yearlevel IN (0,1,2,3)) AS L, SUM(yearlevel IN (4,5)) AS M, COUNT(*) AS T
                      FROM t_2023_cours WHERE remove != 1");

/* Sessions AVANT toute sortie : un echo empeche session_id() de changer. */
$comptes = ['L' => session_compte($dtb, $idL), 'M' => session_compte($dtb, $idM), 'tout' => session_compte($dtb, $idAdmin)];
$libelles = ['L' => 'Licence', 'M' => 'Master', 'tout' => 'sans perimetre'];
$niveaux = ['L' => [0, 1, 2, 3], 'M' => [4, 5]];

echo "Comptes : Licence #$idL, Master #$idM, temoin #$idAdmin\n";
echo "Essais : etudiant L {$eL['student_id']}, M {$eM['student_id']} ; cours L #{$cL['id']}, M #{$cM['id']}"
   . ($ens ? " ; enseignant #{$ens['teacher_id']}" : '') . ($croise ? " ; cours L #{$croise['cours_id']} avec l'etudiant M {$croise['student_id']}" : '') . "\n";

foreach ($comptes as $cle => $sid) {
    echo "\n== Compte " . $libelles[$cle] . "\n";
    $autre = $cle === 'L' ? 'M' : 'L';

    /* 1. Listes : totaux et filtre Niveau */
    $r = json_decode(appel($sid, '/init/.listes/liste?liste=etudiants'), true);
    verifie('liste des etudiants : total', (int) $attendus[$cle === 'tout' ? 'T' : $cle], (int) ($r['total'] ?? -1));
    if ($cle !== 'tout') {
        $vals = array_map(fn ($o) => (int) $o['v'], $r['facettes']['niveau']['options'] ?? []);
        verifie('liste des etudiants : filtre Niveau limite au cycle', [], array_values(array_diff($vals, $niveaux[$cle])));
    }
    $r = json_decode(appel($sid, '/init/.listes/liste?liste=cours'), true);
    verifie('liste des cours : total', (int) $attendusCours[$cle === 'tout' ? 'T' : $cle], (int) ($r['total'] ?? -1));

    /* 2. Recherche (liste) sur un matricule de chaque cycle */
    foreach (['L' => $eL, 'M' => $eM] as $cy => $e) {
        $r = json_decode(appel($sid, '/init/.listes/liste?liste=etudiants&q=' . rawurlencode($e['student_id'])), true);
        $voit = strpos((string) ($r['html'] ?? ''), '>' . htmlspecialchars($e['student_id']) . '<') !== false
             || strpos((string) ($r['html'] ?? ''), htmlspecialchars($e['student_id'])) !== false;
        verifie("recherche du matricule $cy {$e['student_id']}", $cle === 'tout' || $cle === $cy, $voit);
    }

    /* 3. Recherche rapide (barre du haut) */
    foreach (['L' => $eL, 'M' => $eM] as $cy => $e) {
        $h = appel($sid, '/init/.student/std-livesearch', ['input' => $e['student_id']]);
        verifie("recherche rapide d'etudiant $cy", $cle === 'tout' || $cle === $cy, strpos($h, $e['student_id']) !== false);
    }
    foreach (['L' => $cL, 'M' => $cM] as $cy => $c) {
        $h = appel($sid, '/init/.cours/cours-livesearch', ['input' => $c['Sigle']]);
        verifie("recherche rapide de cours $cy", $cle === 'tout' || $cle === $cy, strpos($h, 'id=' . $c['id'] . '&') !== false);
    }

    /* 4. Fiches ouvertes par leur adresse */
    foreach (['L' => $eL, 'M' => $eM] as $cy => $e) {
        $h = appel($sid, '/student?id=' . $e['id'] . '&page=information');
        $visible = $cle === 'tout' || $cle === $cy;
        verifie("fiche etudiant $cy : " . ($visible ? 'affichee' : 'introuvable'),
            $visible ? [true, false] : [false, true],
            [strpos($h, $e['student_id']) !== false, strpos($h, 'Fiche introuvable') !== false]);
    }
    foreach (['L' => $cL, 'M' => $cM] as $cy => $c) {
        $h = appel($sid, '/course?id=' . $c['id'] . '&page=information');
        $visible = $cle === 'tout' || $cle === $cy;
        verifie("fiche cours $cy : " . ($visible ? 'affichee' : 'introuvable'),
            $visible ? [true, false] : [false, true],
            [strpos($h, htmlspecialchars($c['title'])) !== false || strpos($h, $c['title']) !== false, strpos($h, 'Cours introuvable') !== false]);
    }

    /* 5. Les cours d'un enseignant commun aux deux cycles */
    if ($ens) {
        $h = appel($sid, '/professor?id=' . $ens['teacher_id'] . '&page=cours');
        preg_match_all('~/cours\?id=(\d+)&~', $h, $m);
        $ids = array_values(array_unique(array_map('intval', $m[1])));
        $cycles = [];
        if ($ids) {
            foreach ($dtb->query('SELECT yearlevel FROM t_2023_cours WHERE id IN (' . implode(',', $ids) . ')') as $l) {
                $cycles[in_array((int) $l['yearlevel'], [4, 5], true) ? 'M' : 'L'] = true;
            }
        }
        ksort($cycles);
        verifie("cours de l'enseignant commun", $cle === 'tout' ? ['L', 'M'] : [$cle], array_keys($cycles));
    }

    /* 6. Les etudiants d'un cours de Licence : l'etudiant de Master n'y parait que pour les comptes qui le voient */
    if ($croise && $cle !== 'M') {
        $h = appel($sid, '/course?id=' . $croise['cours_id'] . '&page=etudiants');
        verifie("etudiant de Master dans un cours de Licence", $cle === 'tout', strpos($h, $croise['student_id']) !== false);
    }

    /* 7. Menu : le cycle a cote du role */
    $h = appel($sid, '/etudiants');
    verifie('menu : cycle affiche', $cle === 'tout' ? '' : ' · ' . $libelles[$cle],
        preg_match('~Registrariat( · (Licence|Master))~u', $h, $mm) ? $mm[1] : (strpos($h, 'side-profil__role') !== false ? '' : 'menu absent'));

    /* 8. Aucune alerte PHP dans les pages. Les deux alertes connues de
       init/.cours/cours.menubar.php (fenetres de photo d'etudiant recopiees
       dans le menu du cours, anterieures au perimetre) sont mises a part. */
    $pages = appel($sid, '/student?id=' . $eL['id'] . '&page=information') . appel($sid, '/course?id=' . $cM['id'] . '&page=information');
    preg_match_all('~<b>(?:Warning|Notice|Deprecated|Fatal error)</b>:\s*(.*?)<br~s', $pages, $alertes);
    $alertes = array_values(array_filter($alertes[1], fn ($a) => strpos($a, 'cours.menubar.php') === false));
    verifie('aucune alerte PHP (fiches)', [], array_map('strip_tags', $alertes));
}

echo "\n====================================================\n";
echo "  $ok reussis, $ko echoues\n";
echo "====================================================\n";
exit($ko === 0 ? 0 : 1);
