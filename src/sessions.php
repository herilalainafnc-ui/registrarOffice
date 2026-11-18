<?php
/* =============================================================================
   Sessions (/sessions) — les semestres de chaque annee universitaire.

   Superadmin (AdminController::sessions). Chaque chargement recale la session
   en cours (selected = 1) sur la date du jour : c'est elle que lisent les
   autres pages.

   Affichage : une frise par annee (une ligne par session, sa barre posee sur
   les mois de l'annee, le trait dore d'aujourd'hui), la session en cours en
   tete, les annees terminees repliees. Prefixe « sn- ».
   Styles : src/css/sessions.css. Comportement : src/js/sessions.js (frises,
   edition sur place avec apercu en direct, enregistrement sans recharger).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_app_root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$app_base = substr($_app_root, strlen($_doc_root));
if ($app_base === false || $app_base === '/' || $app_base === '.') {
    $app_base = '';
}

// Compatibilite: le projet peut stocker l'authentification sous plusieurs cles session.
$isAuthenticated = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['infinit_pseudo']);
if (!$isAuthenticated) {
    // Memorise la page demandee pour y revenir apres reconnexion.
    if (function_exists('memoriserUrlDemandee')) { memoriserUrlDemandee(); }
    header('Location: ' . $app_base . '/login');
    exit;
}

require('../data/backdb.php');

function isValidDateValue($value) {
    if ($value === null || $value === '') {
        return true;
    }

    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

function ensureGraduationDateColumn(PDO $dtb) {
    try {
        $checkStmt = $dtb->query("SHOW COLUMNS FROM t_2023_session LIKE 'graduation_date'");
        $exists = (bool)$checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $dtb->exec("ALTER TABLE t_2023_session ADD COLUMN graduation_date DATE NULL AFTER date_entry");
        }
    } catch (PDOException $e) {
        throw new RuntimeException("Erreur lors de la verification de la colonne graduation_date: " . $e->getMessage());
    }
}

function generateCurrentYearSessions(PDO $dtb) {
    $currentYear = (int)date('Y');
    $sessionYear = $currentYear . ' - ' . ($currentYear + 1);

    $existsStmt = $dtb->prepare('SELECT COUNT(*) FROM t_2023_session WHERE session_year = :session_year');
    $existsStmt->execute(['session_year' => $sessionYear]);
    $existingCount = (int)$existsStmt->fetchColumn();

    if ($existingCount > 0) {
        throw new RuntimeException("L'année " . $sessionYear . ' a déjà des sessions.');
    }

    $definitions = [
        ['code_prefix' => 'PREM', 'name' => 'Premier semestre', 'semester' => 1],
        ['code_prefix' => 'ETE', 'name' => "Semestre d'été", 'semester' => 3],
        ['code_prefix' => 'DEUX', 'name' => 'Deuxième semestre', 'semester' => 2],
        ['code_prefix' => 'HIVER', 'name' => "Semestre d'hiver", 'semester' => 4],
    ];

    /* Les dates proposees : celles du meme semestre l'annee precedente, 52
       semaines plus tard. Le jour de la semaine est garde (un semestre qui
       commencait un lundi recommence un lundi) ; il ne reste qu'a ajuster.
       Sans modele, l'ancien repere : aujourd'hui, et quatre mois. */
    $modelesStmt = $dtb->prepare(
        'SELECT session_semester, date_entry, graduation_date
         FROM t_2023_session
         WHERE session_year = :session_year AND date_entry IS NOT NULL AND graduation_date IS NOT NULL
         ORDER BY session_id DESC'
    );
    $modelesStmt->execute(['session_year' => ($currentYear - 1) . ' - ' . $currentYear]);
    $modeles = [];
    foreach ($modelesStmt->fetchAll(PDO::FETCH_ASSOC) as $modele) {
        $modeles[(int)$modele['session_semester']] ??= $modele;
    }

    /* Sans modele : apres la derniere session connue, et au plus tot demain.
       Des dates qui couvriraient aujourd'hui feraient du nouveau Premier
       semestre la session en cours de toute l'application, avant meme que
       ses vraies dates soient saisies. */
    $derniereFin = (string)$dtb->query('SELECT MAX(graduation_date) FROM t_2023_session')->fetchColumn();
    $dateEntry = date('Y-m-d', strtotime('+1 day'));
    if (isValidDateValue($derniereFin) && $derniereFin !== '' && $derniereFin >= $dateEntry) {
        $dateEntry = date('Y-m-d', strtotime($derniereFin . ' +1 day'));
    }
    $graduationDate = date('Y-m-d', strtotime('+4 months', strtotime($dateEntry)));

    $insertStmt = $dtb->prepare(
        'INSERT INTO t_2023_session
         (session_code, session_name, session_year, session_semester, selected, date_entry, graduation_date)
         VALUES
         (:session_code, :session_name, :session_year, :session_semester, :selected, :date_entry, :graduation_date)'
    );

    foreach ($definitions as $def) {
        $modele = $modeles[$def['semester']] ?? null;
        $debut = $dateEntry;
        $fin = $graduationDate;
        if ($modele && isValidDateValue((string)$modele['date_entry']) && isValidDateValue((string)$modele['graduation_date'])) {
            $debut = date('Y-m-d', strtotime($modele['date_entry'] . ' +364 days'));
            $fin = date('Y-m-d', strtotime($modele['graduation_date'] . ' +364 days'));
        }
        $insertStmt->execute([
            'session_code' => $def['code_prefix'] . $currentYear . ($currentYear + 1),
            'session_name' => $def['name'],
            'session_year' => $sessionYear,
            'session_semester' => $def['semester'],
            'selected' => 0,
            'date_entry' => $debut,
            'graduation_date' => $fin,
        ]);
    }

    return $sessionYear;
}

function syncActiveSessionByCurrentDate(PDO $dtb) {
    $today = date('Y-m-d');

    /* Deux marqueurs distincts pour une meme valeur, et non « :today » repete.
       La connexion desactive l'emulation des requetes preparees
       (PDO::ATTR_EMULATE_PREPARES => false dans data/config.php) : PDO transmet
       alors la requete telle quelle a MySQL, qui ne connait que des marqueurs
       positionnels. Chaque occurrence devient un « ? » distinct et attend sa
       propre valeur — un nom reutilise produit donc
       « SQLSTATE[HY093]: Invalid parameter number ».
       Avec l'emulation active, PDO substitue lui-meme et le nom repete passe :
       c'est ce qui rend ce defaut invisible sur d'autres configurations. */
    $activeStmt = $dtb->prepare(
        'SELECT session_id
         FROM t_2023_session
         WHERE date_entry IS NOT NULL
           AND graduation_date IS NOT NULL
           AND date_entry <= :debut
           AND graduation_date >= :fin
         ORDER BY date_entry DESC, graduation_date ASC, session_semester ASC, session_id DESC
         LIMIT 1'
    );
    $activeStmt->execute(['debut' => $today, 'fin' => $today]);
    $activeId = (int)$activeStmt->fetchColumn();

    $dtb->beginTransaction();
    try {
        $dtb->exec('UPDATE t_2023_session SET selected = 0');

        if ($activeId > 0) {
            $setActiveStmt = $dtb->prepare('UPDATE t_2023_session SET selected = 1 WHERE session_id = :session_id');
            $setActiveStmt->execute(['session_id' => $activeId]);
        }

        $dtb->commit();
    } catch (Throwable $e) {
        if ($dtb->inTransaction()) {
            $dtb->rollBack();
        }
        throw $e;
    }

    return $activeId;
}

/** Les champs communs a la creation et a la modification, controles. */
function snChampsPostes(): array {
    $champs = [
        'session_code' => trim((string)($_POST['session_code'] ?? '')),
        'session_name' => trim((string)($_POST['session_name'] ?? '')),
        'session_year' => trim((string)($_POST['session_year'] ?? '')),
        'session_semester' => (int)($_POST['session_semester'] ?? 0),
        'date_entry' => trim((string)($_POST['date_entry'] ?? '')),
        'graduation_date' => trim((string)($_POST['graduation_date'] ?? '')),
    ];

    if ($champs['session_name'] === '' || $champs['session_year'] === '') {
        throw new RuntimeException("Le nom et l'année de la session sont obligatoires.");
    }
    if (!in_array($champs['session_semester'], [1, 2, 3, 4], true)) {
        throw new RuntimeException('Choisissez le semestre.');
    }
    if ($champs['date_entry'] === '' || $champs['graduation_date'] === '') {
        throw new RuntimeException('Choisissez les dates de début et de fin.');
    }
    if (!isValidDateValue($champs['date_entry']) || !isValidDateValue($champs['graduation_date'])) {
        throw new RuntimeException('Date illisible : choisissez-la dans le calendrier.');
    }
    if ($champs['date_entry'] !== '' && $champs['graduation_date'] !== '' && $champs['graduation_date'] < $champs['date_entry']) {
        throw new RuntimeException('La fin doit venir après le début.');
    }

    return $champs;
}

$flashType = '';
$flashMessage = '';
$isAjaxRequest = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || (isset($_POST['ajax']) && (string)$_POST['ajax'] === '1');

try {
    ensureGraduationDateColumn($dtb);
    syncActiveSessionByCurrentDate($dtb);
} catch (Throwable $e) {
    $flashType = 'error';
    $flashMessage = "La session en cours n'a pas pu être recalée sur la date du jour : " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Jeton de securite (window.CSRF_TOKEN, init/head.php). Refuse en 400 et
       non en 403 : src/js/session.js afficherait « Acces refuse », alors que
       src/js/sessions.js reprend simplement un jeton frais et renvoie. */
    $csrfRefuse = function_exists('verify_csrf') && !verify_csrf();
    if ($csrfRefuse && $isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'code' => 'csrf',
            'message' => 'Jeton de sécurité expiré : rechargez la page.',
        ]);
        exit;
    }

    try {
        if ($csrfRefuse) {
            throw new RuntimeException('Jeton de sécurité expiré : rechargez la page.');
        }
        ensureGraduationDateColumn($dtb);

        if ($action === 'generate_four_sessions') {
            $generatedYear = generateCurrentYearSessions($dtb);
            syncActiveSessionByCurrentDate($dtb);

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => '4 sessions créées pour ' . $generatedYear . ' : vérifiez leurs dates.',
                    'year' => $generatedYear,
                ]);
                exit;
            }

            header('Location: ' . $app_base . '/sessions?status=generated&year=' . urlencode($generatedYear));
            exit;
        }

        if ($action === 'update') {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            if ($sessionId <= 0) {
                throw new RuntimeException('Session introuvable : rechargez la page.');
            }
            $champs = snChampsPostes();

            $stmt = $dtb->prepare(
                'UPDATE t_2023_session
                 SET session_code = :session_code,
                     session_name = :session_name,
                     session_year = :session_year,
                     session_semester = :session_semester,
                     date_entry = :date_entry,
                     graduation_date = :graduation_date
                 WHERE session_id = :session_id'
            );

            $stmt->execute([
                'session_code' => $champs['session_code'], // NOT NULL en production : vide, pas NULL
                'session_name' => $champs['session_name'],
                'session_year' => $champs['session_year'],
                'session_semester' => $champs['session_semester'],
                'date_entry' => $champs['date_entry'] !== '' ? $champs['date_entry'] : null,
                'graduation_date' => $champs['graduation_date'] !== '' ? $champs['graduation_date'] : null,
                'session_id' => $sessionId,
            ]);

            syncActiveSessionByCurrentDate($dtb);

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Session enregistrée.',
                    'session_id' => $sessionId,
                ]);
                exit;
            }

            header('Location: ' . $app_base . '/sessions?status=updated');
            exit;
        }

        if ($action === 'create') {
            $champs = snChampsPostes();

            $stmt = $dtb->prepare(
                'INSERT INTO t_2023_session
                 (session_code, session_name, session_year, session_semester, selected, date_entry, graduation_date)
                 VALUES
                 (:session_code, :session_name, :session_year, :session_semester, :selected, :date_entry, :graduation_date)'
            );

            $stmt->execute([
                'session_code' => $champs['session_code'], // NOT NULL en production : vide, pas NULL
                'session_name' => $champs['session_name'],
                'session_year' => $champs['session_year'],
                'session_semester' => $champs['session_semester'],
                'selected' => 0,
                'date_entry' => $champs['date_entry'] !== '' ? $champs['date_entry'] : null,
                'graduation_date' => $champs['graduation_date'] !== '' ? $champs['graduation_date'] : null,
            ]);
            $nouvelId = (int)$dtb->lastInsertId();

            syncActiveSessionByCurrentDate($dtb);

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Session créée.',
                    'session_id' => $nouvelId,
                ]);
                exit;
            }

            header('Location: ' . $app_base . '/sessions?status=created');
            exit;
        }

        throw new RuntimeException('Action inconnue.');
    } catch (Throwable $e) {
        if ($isAjaxRequest) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }

        $flashType = 'error';
        $flashMessage = $e->getMessage();
    }
}

$status = $_GET['status'] ?? '';
if ($status === 'updated') {
    $flashType = 'success';
    $flashMessage = 'Session enregistrée.';
} elseif ($status === 'created') {
    $flashType = 'success';
    $flashMessage = 'Session créée.';
} elseif ($status === 'generated') {
    $flashType = 'success';
    $generatedYear = trim((string)($_GET['year'] ?? ''));
    $flashMessage = $generatedYear !== ''
        ? '4 sessions créées pour ' . $generatedYear . ' : vérifiez leurs dates.'
        : '4 sessions créées : vérifiez leurs dates.';
}

try {
    $checkStmt = $dtb->query("SHOW COLUMNS FROM t_2023_session LIKE 'graduation_date'");
    $hasGraduationDate = (bool)$checkStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $hasGraduationDate = false;
}

$sessions = $dtb->query(
    'SELECT session_id, session_code, session_name, session_year, session_semester, selected, date_entry, '
    . ($hasGraduationDate ? 'graduation_date' : 'NULL AS graduation_date') . '
     FROM t_2023_session
     ORDER BY session_year DESC, session_semester ASC, session_id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------------------------
   Ce que la page montre : chaque annee, ses sessions dans l'ordre des dates,
   l'etat de chacune par rapport a aujourd'hui, les dates qui se chevauchent.
   --------------------------------------------------------------------------- */

/** Les semestres, dans leur ordre de l'annee (un, ete, deux, hiver). */
$snSemestres = [
    1 => ['nom' => 'Premier semestre', 'court' => 'Premier', 'prefixe' => 'PREM'],
    3 => ['nom' => "Semestre d'été", 'court' => 'Été', 'prefixe' => 'ETE'],
    2 => ['nom' => 'Deuxième semestre', 'court' => 'Deuxième', 'prefixe' => 'DEUX'],
    4 => ['nom' => "Semestre d'hiver", 'court' => 'Hiver', 'prefixe' => 'HIVER'],
];
$snRangSemestre = array_flip(array_keys($snSemestres));

$snAujourdhui = date('Y-m-d');

/** Jours depuis le 1er janvier 1970 : les ecarts de dates se comptent en jours entiers. */
function snJour(string $ymd): int {
    return intdiv((int)strtotime($ymd . ' 00:00:00 UTC'), 86400);
}

/** Une date AAAA-MM-JJ lisible, ou null. */
function snDateValide($valeur): ?string {
    $valeur = trim((string)$valeur);
    return ($valeur !== '' && $valeur !== '0000-00-00' && isValidDateValue($valeur)) ? $valeur : null;
}

/**
 * Une date en francais : « 12 oct. 2026 » (court) ou « lundi 12 octobre 2026 »
 * (long). L'annee est omise quand $sansAnnee vaut cette annee-la.
 */
function snDate(string $ymd, bool $long = false, ?int $sansAnnee = null): string {
    static $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    static $moisLong = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    static $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $t = strtotime($ymd . ' 12:00:00');
    $annee = (int)date('Y', $t);
    $texte = (int)date('j', $t) . ($long ? '' : "\u{00A0}") . ($long ? ' ' . $moisLong[(int)date('n', $t) - 1] : $mois[(int)date('n', $t) - 1]);
    if ($long) {
        $texte = $jours[(int)date('w', $t)] . ' ' . $texte;
    }
    return $annee === $sansAnnee ? $texte : $texte . ' ' . $annee;
}

/** « 12 oct. 2026 → 19 févr. 2027 », la premiere annee omise si c'est la meme. */
function snPeriode(string $debut, string $fin): string {
    $memeAnnee = substr($debut, 0, 4) === substr($fin, 0, 4);
    return snDate($debut, false, $memeAnnee ? (int)substr($debut, 0, 4) : null) . " \u{2192} " . snDate($fin);
}

/** « 19 sem. », ou en jours sous deux semaines. */
function snDuree(int $jours): string {
    return $jours < 14 ? $jours . "\u{00A0}jour" . ($jours > 1 ? 's' : '') : (int)round($jours / 7) . "\u{00A0}sem.";
}

/** « aujourd'hui », « demain », « dans 3 jours ». */
function snDans(int $jours): string {
    return $jours === 0 ? "aujourd'hui" : ($jours === 1 ? 'demain' : "dans\u{00A0}" . $jours . "\u{00A0}jours");
}

$auj = snJour($snAujourdhui);
$snAnneeEnCours = (int)date('Y');
$snCourante = null;
$snProchaine = null;
$snAnnees = [];

foreach ($sessions as $s) {
    $debut = snDateValide($s['date_entry']);
    $fin = snDateValide($s['graduation_date']);
    $dateOk = $debut !== null && $fin !== null && $fin >= $debut;
    $j0 = $dateOk ? snJour($debut) : null;
    $j1 = $dateOk ? snJour($fin) : null;

    if (!$dateOk) {
        $etat = 'afixer';
    } elseif ((int)$s['selected'] === 1) {
        $etat = 'cours';
    } elseif ($j1 < $auj) {
        $etat = 'fini';
    } elseif ($j0 > $auj) {
        $etat = 'avenir';
    } else {
        $etat = 'aussi'; // couvre aujourd'hui sans etre retenue : un chevauchement
    }

    $ligne = [
        'id' => (int)$s['session_id'],
        'code' => (string)($s['session_code'] ?? ''),
        'nom' => (string)$s['session_name'],
        'annee' => trim((string)($s['session_year'] ?? '')),
        'semestre' => (int)$s['session_semester'],
        'debut' => $dateOk ? $debut : (string)($debut ?? ''),
        'fin' => $dateOk ? $fin : (string)($fin ?? ''),
        'j0' => $j0,
        'j1' => $j1,
        'etat' => $etat,
        'chevauche' => [],
    ];

    if ($etat === 'cours') {
        $snCourante = $ligne;
    }
    if ($dateOk && $j0 > $auj && ($snProchaine === null || $j0 < $snProchaine['j0'])) {
        $snProchaine = $ligne;
    }

    $cle = $ligne['annee'] !== '' ? $ligne['annee'] : 'Année non précisée';
    $snAnnees[$cle]['sessions'][] = $ligne;
}

foreach ($snAnnees as $cle => &$a) {
    $a['label'] = $cle;
    usort($a['sessions'], static function ($x, $y) use ($snRangSemestre) {
        return [$x['j0'] ?? PHP_INT_MAX, $snRangSemestre[$x['semestre']] ?? 9, $x['id']]
           <=> [$y['j0'] ?? PHP_INT_MAX, $snRangSemestre[$y['semestre']] ?? 9, $y['id']];
    });
    $datees = array_filter($a['sessions'], static fn ($l) => $l['j0'] !== null);
    $a['j0'] = $datees ? min(array_column($datees, 'j0')) : null;
    $a['j1'] = $datees ? max(array_column($datees, 'j1')) : null;
    $a['debut'] = $datees ? min(array_column($datees, 'debut')) : null;
    $a['fin'] = $datees ? max(array_column($datees, 'fin')) : null;
    $a['etat'] = !$datees ? 'afixer' : ($auj < $a['j0'] ? 'avenir' : ($auj > $a['j1'] ? 'fini' : 'cours'));

    /* Les chevauchements ne sont signales que sur les annees pas encore
       terminees : ce sont eux qui decident encore de la session en cours. */
    $a['chevauchements'] = 0;
    if ($a['etat'] !== 'fini') {
        foreach ($a['sessions'] as $i => &$l1) {
            foreach ($a['sessions'] as $k => $l2) {
                if ($i !== $k && $l1['j0'] !== null && $l2['j0'] !== null && $l1['j0'] <= $l2['j1'] && $l2['j0'] <= $l1['j1']) {
                    $l1['chevauche'][] = $l2['nom'];
                }
            }
            $a['chevauchements'] += $l1['chevauche'] ? 1 : 0;
        }
        unset($l1);
    }
}
unset($a);

uksort($snAnnees, static function ($x, $y) {
    $vx = preg_match('~^\d{4}~', $x) ? $x : '0';
    $vy = preg_match('~^\d{4}~', $y) ? $y : '0';
    return strcmp($vy, $vx);
});

$snActives = array_filter($snAnnees, static fn ($a) => $a['etat'] !== 'fini');
$snPassees = array_filter($snAnnees, static fn ($a) => $a['etat'] === 'fini');

/* L'annee que « Creer les 4 sessions » preparerait (generateCurrentYearSessions) */
$snAGenerer = $snAnneeEnCours . ' - ' . ($snAnneeEnCours + 1);
$snPeutGenerer = !isset($snAnnees[$snAGenerer]);
$snModeleGeneration = ($snAnneeEnCours - 1) . ' - ' . $snAnneeEnCours;
$snAModele = !empty(array_filter($snAnnees[$snModeleGeneration]['sessions'] ?? [], static fn ($l) => $l['j0'] !== null));

$snEtatsAnnee = ['cours' => 'En cours', 'avenir' => 'À venir', 'fini' => 'Terminée', 'afixer' => 'Dates à fixer'];

/** La pastille d'etat d'une session. */
function snPastille(array $l, int $auj): array {
    switch ($l['etat']) {
        case 'cours':
            return ['cours', 'En cours'];
        case 'aussi':
            return ['alerte', 'Couvre aussi aujourd’hui'];
        case 'fini':
            return ['fini', 'Terminée'];
        case 'afixer':
            return ['alerte', 'Dates à fixer'];
    }
    $dans = $l['j0'] - $auj;
    return ['avenir', $dans <= 60 ? ucfirst(snDans($dans)) : 'À venir'];
}

/** Une annee : son en-tete repliable et sa frise. */
function snAnnee(array $a, bool $ouverte, int $index, int $auj, array $etats): void {
    $id = 'snA-' . preg_replace('~[^0-9a-z]+~i', '', $a['label']);
    $n = count($a['sessions']);
    ?>
    <section class="sn-annee sn-annee--<?= $a['etat'] ?><?= $ouverte ? ' is-ouverte' : '' ?>" data-annee="<?= htmlspecialchars($a['label']) ?>" style="--i: <?= $index ?>">
        <div class="sn-annee__tete">
            <button type="button" class="sn-annee__bascule" aria-expanded="<?= $ouverte ? 'true' : 'false' ?>" aria-controls="<?= $id ?>">
                <i class="bi bi-chevron-right sn-annee__chevron" aria-hidden="true"></i>
                <span class="sn-annee__titre"><?= htmlspecialchars($a['label']) ?></span>
                <span class="sn-etat sn-etat--<?= $a['etat'] === 'afixer' ? 'alerte' : $a['etat'] ?>"><?php if ($a['etat'] === 'cours'): ?><i aria-hidden="true"></i><?php endif; ?><?= $etats[$a['etat']] ?></span>
                <?php if ($a['chevauchements'] > 0): ?>
                <span class="sn-etat sn-etat--alerte"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Dates qui se chevauchent</span>
                <?php endif; ?>
                <span class="sn-annee__infos"><?= $n ?> session<?= $n > 1 ? 's' : '' ?><?php if ($a['debut'] !== null): ?> · <?= snPeriode($a['debut'], $a['fin']) ?><?php endif; ?></span>
            </button>
            <button type="button" class="sn-bouton sn-bouton--fantome sn-annee__ajouter" data-annee="<?= htmlspecialchars($a['label']) ?>" aria-label="Ajouter une session à <?= htmlspecialchars($a['label']) ?>">
                <i class="bi bi-plus-lg" aria-hidden="true"></i><span>Ajouter</span>
            </button>
        </div>
        <div class="sn-annee__corps" id="<?= $id ?>">
            <div class="sn-annee__interieur">
                <div class="sn-regle" aria-hidden="true">
                    <span class="sn-regle__vide"></span>
                    <div class="sn-regle__mois"></div>
                    <span class="sn-regle__vide"></span>
                </div>
                <?php foreach ($a['sessions'] as $j => $l):
                    [$pastille, $libelle] = snPastille($l, $auj);
                    $dateOk = $l['j0'] !== null; ?>
                <div class="sn-ligne sn-ligne--<?= $l['etat'] ?><?= $l['chevauche'] ? ' sn-ligne--chevauche' : '' ?>" style="--j: <?= $j ?>"
                     data-id="<?= $l['id'] ?>" data-nom="<?= htmlspecialchars($l['nom']) ?>" data-code="<?= htmlspecialchars($l['code']) ?>"
                     data-annee="<?= htmlspecialchars($l['annee']) ?>" data-semestre="<?= $l['semestre'] ?>"
                     data-debut="<?= htmlspecialchars($l['debut']) ?>" data-fin="<?= htmlspecialchars($l['fin']) ?>">
                    <div class="sn-ligne__info">
                        <div class="sn-ligne__nom">
                            <span class="sn-ligne__titre"><?= htmlspecialchars($l['nom']) ?></span>
                            <span class="sn-etat sn-etat--<?= $pastille ?>"><?php if ($pastille === 'cours'): ?><i aria-hidden="true"></i><?php endif; ?><?= $libelle ?></span>
                        </div>
                        <div class="sn-ligne__dates"><?php if ($dateOk): ?><?= snPeriode($l['debut'], $l['fin']) ?><span class="sn-ligne__duree"> · <?= snDuree($l['j1'] - $l['j0'] + 1) ?></span><?php else: ?>Dates à fixer<?php endif; ?></div>
                        <?php if ($l['code'] !== ''): ?><div class="sn-ligne__code"><?= htmlspecialchars($l['code']) ?></div><?php endif; ?>
                        <?php if ($l['chevauche']): ?>
                        <div class="sn-ligne__alerte"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Chevauche <?= htmlspecialchars(implode(', ', array_unique($l['chevauche']))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="sn-piste" aria-hidden="true"><span class="sn-barre"></span></div>
                    <div class="sn-ligne__actions">
                        <button type="button" class="sn-modifier" aria-expanded="false" aria-label="Modifier <?= htmlspecialchars($l['nom'] . ' ' . $l['annee']) ?>">
                            <i class="bi bi-pencil" aria-hidden="true"></i><span>Modifier</span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Sessions</title>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/sessions.css?v=<?=@filemtime(__DIR__ . '/css/sessions.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php'); ?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back flex-1 overflow-y-auto">
                <div class="sn sn-entree" id="snPage">

                    <header class="sn-tete" style="--i: 0">
                        <span class="sn-tete__icone" aria-hidden="true"><i class="bi bi-calendar2-week"></i></span>
                        <div class="sn-tete__texte">
                            <h1>Sessions</h1>
                            <p>Les semestres de chaque année universitaire. La session en cours se choisit seule, chaque jour&nbsp;: c'est celle dont les dates encadrent la date du jour.</p>
                        </div>
                        <div class="sn-tete__actions" id="snActions">
                            <?php if ($snPeutGenerer): ?>
                            <button type="button" class="sn-bouton sn-bouton--discret" id="snGenerer" data-annee="<?= htmlspecialchars($snAGenerer) ?>" data-modele="<?= $snAModele ? htmlspecialchars($snModeleGeneration) : '' ?>">
                                <i class="bi bi-calendar-plus" aria-hidden="true"></i><span>Créer les 4 sessions de <?= htmlspecialchars($snAGenerer) ?></span>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="sn-bouton" id="snNouvelle">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nouvelle session</span>
                            </button>
                        </div>
                    </header>

                    <div id="snContenu">
                    <?php /* La session en cours : celle que lisent les autres pages (selected = 1). */ ?>
                    <?php if ($snCourante):
                        $jour = $auj - $snCourante['j0'] + 1;
                        $total = $snCourante['j1'] - $snCourante['j0'] + 1;
                        $reste = $snCourante['j1'] - $auj;
                        $part = max(0, min(1, $jour / max(1, $total))); ?>
                    <section class="sn-maintenant" id="snMaintenant" aria-labelledby="snMaintenantTitre" style="--i: 1">
                        <div class="sn-maintenant__ligne">
                            <span class="sn-etat sn-etat--cours"><i aria-hidden="true"></i>En cours</span>
                            <h2 id="snMaintenantTitre"><?= htmlspecialchars($snCourante['nom']) ?> <span><?= htmlspecialchars($snCourante['annee']) ?></span></h2>
                            <span class="sn-maintenant__compte">Jour <b><?= $jour ?></b> sur <?= $total ?></span>
                        </div>
                        <div class="sn-progres" role="progressbar" aria-label="Avancement de la session" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)round($part * 100) ?>">
                            <span class="sn-progres__fait" style="--p: <?= round($part, 4) ?>"></span>
                        </div>
                        <p class="sn-maintenant__texte">
                            <?= $reste === 0 ? "Se termine aujourd'hui." : ($reste === 1 ? 'Se termine demain, ' . snDate($snCourante['fin'], true, $snAnneeEnCours) . '.' : 'Se termine le ' . snDate($snCourante['fin'], true, $snAnneeEnCours) . ', ' . snDans($reste) . '.') ?>
                            <?php if ($snProchaine): ?>
                            Ensuite&nbsp;: <b><?= htmlspecialchars($snProchaine['nom'] . ' ' . $snProchaine['annee']) ?></b>, à partir du <?= snDate($snProchaine['debut'], true, $snAnneeEnCours) ?><?= ($snProchaine['j0'] - $auj) <= 60 ? ', ' . snDans($snProchaine['j0'] - $auj) : '' ?>.
                            <?php endif; ?>
                        </p>
                    </section>
                    <?php else: ?>
                    <section class="sn-maintenant sn-maintenant--aucune" id="snMaintenant" aria-labelledby="snMaintenantTitre" style="--i: 1">
                        <div class="sn-maintenant__ligne">
                            <span class="sn-etat sn-etat--alerte"><i class="bi bi-pause-circle" aria-hidden="true"></i>Aucune session en cours</span>
                            <h2 id="snMaintenantTitre">Aucune session ne couvre <?= snDate($snAujourdhui, true) ?></h2>
                        </div>
                        <p class="sn-maintenant__texte">
                            <?php if ($snProchaine): ?>
                            <b><?= htmlspecialchars($snProchaine['nom'] . ' ' . $snProchaine['annee']) ?></b> commence le <?= snDate($snProchaine['debut'], true, $snAnneeEnCours) ?>, <?= snDans($snProchaine['j0'] - $auj) ?>. D'ici là, l'application n'a pas de session en cours.
                            <?php else: ?>
                            Aucune session ne vient ensuite non plus&nbsp;: créez celles de la nouvelle année.
                            <?php endif; ?>
                        </p>
                    </section>
                    <?php endif; ?>

                    <div class="sn-liste" id="snListe">
                        <?php if (!$snAnnees): ?>
                        <div class="sn-vide">
                            <span class="sn-vide__icone" aria-hidden="true"><i class="bi bi-calendar2-week"></i></span>
                            <h2>Aucune session pour l'instant</h2>
                            <p>Créez les 4 sessions de <?= htmlspecialchars($snAGenerer) ?>, ou une session à la fois avec «&nbsp;Nouvelle session&nbsp;».</p>
                        </div>
                        <?php endif; ?>

                        <?php $i = 2; foreach ($snActives as $a) { snAnnee($a, true, $i++, $auj, $snEtatsAnnee); } ?>

                        <?php if ($snPassees):
                            $libelles = array_keys($snPassees); ?>
                        <section class="sn-passees" id="snPassees" style="--i: <?= $i ?>">
                            <button type="button" class="sn-passees__bascule" aria-expanded="false" aria-controls="snPasseesCorps">
                                <i class="bi bi-chevron-right sn-annee__chevron" aria-hidden="true"></i>
                                <span class="sn-passees__titre">Années précédentes</span>
                                <span class="sn-annee__infos"><?= count($snPassees) ?> année<?= count($snPassees) > 1 ? 's' : '' ?>, de <?= htmlspecialchars(end($libelles)) ?> à <?= htmlspecialchars(reset($libelles)) ?></span>
                            </button>
                            <div class="sn-annee__corps" id="snPasseesCorps">
                                <div class="sn-annee__interieur sn-passees__liste">
                                    <?php $k = 0; foreach ($snPassees as $a) { snAnnee($a, false, $k++, $auj, $snEtatsAnnee); } ?>
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>
                    </div>
                    </div>

                    <?php /* L'editeur d'une session, ouvert sous sa ligne par src/js/sessions.js */ ?>
                    <template id="snGabarit">
                        <div class="sn-tiroir">
                            <div class="sn-tiroir__interieur">
                                <form class="sn-editeur" novalidate>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="session_id" value="">
                                    <fieldset class="sn-champ sn-champ--semestre">
                                        <legend>Semestre</legend>
                                        <div class="sn-segments">
                                            <span class="sn-segments__pouce" aria-hidden="true"></span>
                                            <?php foreach ($snSemestres as $num => $sem): ?>
                                            <label><input type="radio" name="session_semester" value="<?= $num ?>"><span><?= htmlspecialchars($sem['court']) ?></span></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <label class="sn-champ sn-champ--nom"><span>Nom</span><input type="text" name="session_name" maxlength="70" autocomplete="off" required></label>
                                    <label class="sn-champ sn-champ--annee"><span>Année universitaire</span><input type="text" name="session_year" maxlength="20" placeholder="<?= htmlspecialchars($snAGenerer) ?>" autocomplete="off" required></label>
                                    <label class="sn-champ sn-champ--code"><span>Code <em>facultatif</em></span><input type="text" name="session_code" maxlength="50" autocomplete="off" spellcheck="false"></label>
                                    <label class="sn-champ sn-champ--debut"><span>Début</span><input type="date" name="date_entry" required></label>
                                    <label class="sn-champ sn-champ--fin"><span>Fin</span><input type="date" name="graduation_date" required></label>
                                    <p class="sn-editeur__duree" aria-live="polite"></p>
                                    <div class="sn-editeur__notes" aria-live="polite"></div>
                                    <div class="sn-editeur__pied">
                                        <span class="sn-editeur__id"></span>
                                        <button type="button" class="sn-bouton sn-bouton--discret" data-sn="annuler">Annuler</button>
                                        <button type="submit" class="sn-bouton sn-editeur__valider"><i class="bi bi-check-lg" aria-hidden="true"></i><span>Enregistrer</span></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>

                    <script type="application/json" id="snConfig"><?= json_encode([
                        'aujourdhui' => $snAujourdhui,
                        'semestres' => $snSemestres,
                        'ordre' => array_keys($snSemestres), // un objet JSON reclasserait 1, 2, 3, 4
                        'anneeParDefaut' => $snAGenerer,
                        'flash' => ['type' => $flashType, 'message' => $flashMessage],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                </div>
            </div>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>
<script src="<?=$app_base?>/src/js/sessions.js?v=<?=@filemtime(__DIR__ . '/js/sessions.js')?>"></script>
</body>
</html>
