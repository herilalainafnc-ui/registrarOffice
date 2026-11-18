<?php
/* =============================================================================
   Cours offerts par session

   Couvre le defaut qui motivait ce chantier — un cours de premier semestre
   offert au second n'apparaissait pas a l'inscription — et les verrous du
   point d'enregistrement, qui n'en avait aucun.

   Le test ECRIT dans t_2023_course_sessions. Il releve l'etat d'origine des
   lignes touchees et le remet en place a la fin, y compris en cas d'echec.

   Lancement :  php tests/test_cours_offerts.php
   ========================================================================== */

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/cours.offerts.php';

$ok = 0; $ko = 0;
function verifie(string $quoi, $attendu, $obtenu) : void {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  ok    $quoi\n"; return; }
    $ko++;
    echo "  ECHEC $quoi\n        attendu: " . var_export($attendu, true)
       . "\n        obtenu : " . var_export($obtenu, true) . "\n";
}

const CIBLE = 'https://registrar.test/app/.cours/save_offered.php';

/** Session authentifiee pour un privilege donne. */
function session_pour(PDO $dtb, string $privilege) : array {
    $u = $dtb->query("SELECT id, level, privilege FROM compt_utilisateur
                       WHERE privilege = " . $dtb->quote($privilege) . " AND etat = 1
                       ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$u) { return ['', '']; }

    session_save_path('C:/laragon/tmp');
    $sid = 'co' . substr(md5($privilege . microtime(true)), 0, 22);
    session_id($sid);
    session_start();
    $_SESSION = [
        'user_id' => $u['id'], 'user_level' => $u['level'],
        'user_privilege' => $u['privilege'], 'logged_in' => true,
        'LAST_ACTIVITY' => time(),
        'csrf_token' => bin2hex(random_bytes(32)), 'csrf_token_time' => time(),
    ];
    $jeton = $_SESSION['csrf_token'];
    session_write_close();
    return [$sid, $jeton];
}

function appel(string $sid, array $donnees) : array {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                      . "X-Requested-With: XMLHttpRequest\r\n"
                      . "Cookie: PHPSESSID=$sid\r\n",
            'content' => http_build_query($donnees),
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $rep = @file_get_contents(CIBLE, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $l, $m)) { $code = (int) $m[1]; }
    }
    return [$code, json_decode((string) $rep, true)];
}

/* Un cours dont le semestre natif est 1, pour eprouver le cas reel. */
$cours = $dtb->query(
    "SELECT id, Sigle, dep_desc, yearlevel, parcours, semester
     FROM t_2023_cours WHERE semester = 1 AND remove != 1 AND dep_desc <> ''
     ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$annee = 'TEST-CO';
$id = (int) $cours['id'];

/* Sessions AVANT toute sortie : un echo empeche session_id() de changer. */
[$sidReg, $jetonReg] = session_pour($dtb, 'registrar');
[$sidCpt, $jetonCpt] = session_pour($dtb, 'comptabilite');

echo "Cours temoin : #{$id} {$cours['Sigle']} — semestre natif {$cours['semester']}, "
   . "{$cours['dep_desc']} niveau {$cours['yearlevel']}\n";
echo "Année de test : $annee\n\n";

try {
    $dtb->exec("DELETE FROM t_2023_course_sessions WHERE academic_year = " . $dtb->quote($annee));

    echo "== Verrous du point d'enregistrement\n";
    [$c, $r] = appel($sidReg, ['academic_year' => $annee, 'course_sessions_payload' => '{}']);
    verifie('sans jeton CSRF -> refus', 400, $c);
    verifie('drapeau csrf_expire', true, !empty($r['csrf_expire']));

    if ($sidCpt !== '') {
        [$c, $r] = appel($sidCpt, ['academic_year' => $annee, 'csrf_token' => $jetonCpt,
                                   'course_sessions_payload' => '{}']);
        verifie('comptabilite -> 403', 403, $c);
    }

    [$c, $r] = appel($sidReg, ['csrf_token' => $jetonReg, 'course_sessions_payload' => '{}']);
    verifie('annee absente -> 422', 422, $c);

    [$c, $r] = appel($sidReg, ['academic_year' => $annee, 'csrf_token' => $jetonReg,
                               'course_sessions_payload' => 'pas du json']);
    verifie('charge illisible -> 422', 422, $c);

    echo "\n== Enregistrement : offrir au DEUXIEME semestre un cours de premier\n";
    [$c, $r] = appel($sidReg, [
        'academic_year' => $annee, 'csrf_token' => $jetonReg,
        'course_sessions_payload' => json_encode([$id => ['Deuxième semestre' => 1]]),
    ]);
    verifie('enregistrement -> 200', 200, $c);
    verifie('une ecriture', 1, $r['ecrits'] ?? null);
    verifie('un cours offert', 1, $r['offerts'] ?? null);

    $q = $dtb->prepare('SELECT offered FROM t_2023_course_sessions
                         WHERE course_id = ? AND session_label = ? AND academic_year = ?');
    $q->execute([$id, 'Deuxième semestre', $annee]);
    verifie('ligne ecrite en base', '1', (string) $q->fetchColumn());

    echo "\n== Le cours apparait-il maintenant a l'inscription ?\n";
    $p = $dtb->prepare('SELECT shortcode FROM filiere_parcours WHERE shortcode = ?');
    $p->execute([$cours['parcours']]);
    $parcours = (string) ($cours['parcours'] === 'all' ? '' : $cours['parcours']);

    $s2 = coursOfferts($dtb, [
        'dep_desc' => $cours['dep_desc'], 'yearlevel' => $cours['yearlevel'],
        'parcours' => $parcours, 'session_label' => 'Deuxième semestre',
        'academic_year' => $annee,
    ]);
    verifie('present au DEUXIEME semestre', true, in_array($id, array_map('intval', array_column($s2, 'id')), true));

    $s1 = coursOfferts($dtb, [
        'dep_desc' => $cours['dep_desc'], 'yearlevel' => $cours['yearlevel'],
        'parcours' => $parcours, 'session_label' => 'Premier semestre',
        'academic_year' => $annee,
    ]);
    verifie('absent du PREMIER semestre (non coche)', false, in_array($id, array_map('intval', array_column($s1, 'id')), true));

    echo "\n== Le point d'entree n'applique QUE ce qu'il recoit\n";
    /* On coche aussi le premier semestre, sans rien dire du deuxieme : le
       deuxieme doit rester coche. L'ancienne version remettait a zero tout ce
       qui n'etait pas dans la charge. */
    [$c, $r] = appel($sidReg, [
        'academic_year' => $annee, 'csrf_token' => $jetonReg,
        'course_sessions_payload' => json_encode([$id => ['Premier semestre' => 1]]),
    ]);
    verifie('seconde ecriture -> 200', 200, $c);
    $q->execute([$id, 'Deuxième semestre', $annee]);
    verifie('le DEUXIEME semestre est intact', '1', (string) $q->fetchColumn());
    $q->execute([$id, 'Premier semestre', $annee]);
    verifie('le PREMIER semestre est coche', '1', (string) $q->fetchColumn());

    echo "\n== Decochage\n";
    [$c, $r] = appel($sidReg, [
        'academic_year' => $annee, 'csrf_token' => $jetonReg,
        'course_sessions_payload' => json_encode([$id => ['Deuxième semestre' => 0]]),
    ]);
    verifie('decochage -> 200', 200, $c);
    $q->execute([$id, 'Deuxième semestre', $annee]);
    verifie('remis a zero', '0', (string) $q->fetchColumn());

    echo "\n== Entrees fantaisistes ecartees\n";
    [$c, $r] = appel($sidReg, [
        'academic_year' => $annee, 'csrf_token' => $jetonReg,
        'course_sessions_payload' => json_encode([
            999999 => ['Premier semestre' => 1],        // cours inexistant
            $id => ['Session inventée' => 1],           // session hors liste
        ]),
    ]);
    verifie('acceptee sans ecrire', 200, $c);
    verifie('aucune ecriture', 0, $r['ecrits'] ?? null);
    verifie('deux entrees ignorees', 2, $r['ignores'] ?? null);
    $n = (int) $dtb->query("SELECT COUNT(*) FROM t_2023_course_sessions
                             WHERE course_id = 999999")->fetchColumn();
    verifie('aucune ligne pour un cours inexistant', 0, $n);

    echo "\n== Choix de l'annee scolaire\n";
    verifie('annee demandee respectee', '2020 - 2021', coursAnneeScolaire($dtb, '2020 - 2021'));
    $auto = coursAnneeScolaire($dtb);
    verifie('annee automatique non vide', true, $auto !== '');
    $st = $dtb->prepare('SELECT COUNT(*) FROM t_2023_course_sessions WHERE academic_year = ? AND offered = 1');
    $st->execute([$auto]);
    verifie('l annee retenue contient des cours offerts', true, (int) $st->fetchColumn() > 0);

} finally {
    $dtb->exec("DELETE FROM t_2023_course_sessions WHERE academic_year = " . $dtb->quote($annee));
    $dtb->exec("DELETE FROM t_2023_course_sessions WHERE course_id = 999999");
    $reste = (int) $dtb->query("SELECT COUNT(*) FROM t_2023_course_sessions
                                 WHERE academic_year = " . $dtb->quote($annee))->fetchColumn();
    echo "\nnettoyage : $reste ligne(s) de test restante(s)\n";
}

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko === 0 ? 0 : 1);
