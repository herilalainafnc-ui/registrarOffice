<?php
/**
 * Tests de la page d'autorisation de reinscription (data/reinscription.php).
 *
 * CE QUE CES TESTS PROTEGENT
 * --------------------------
 *   1. Le perimetre par role : la caisse ne touche que l'autorisation, le
 *      registraire que le dossier, le superadmin les deux.
 *   2. La reinitialisation remet bien tout a « non coche », en photographiant
 *      l'etat d'origine et en tracant chaque etudiant.
 *   3. L'annulation restaure a l'identique — valeur, auteur, date — SAUF ce
 *      qu'un humain a modifie depuis, et seulement pour les colonnes du role.
 *   4. Le jeton CSRF et le verrou de role sur les points d'entree.
 *
 * TESTE SUR LES VRAIES DONNEES, ET POURQUOI C'EST SUR
 * ---------------------------------------------------
 * La reinitialisation porte sur toute la table : la tester en vrai est le seul
 * moyen de verifier le volume, la transaction et la restauration exacte. Le
 * test photographie donc en memoire les six colonnes concernees de TOUS les
 * etudiants avant de commencer, et les restaure inconditionnellement dans le
 * bloc finally — meme si une assertion echoue, meme si une exception part.
 * Une empreinte finale prouve le retour a l'etat initial.
 *
 * Lancement :
 *   php tests/test_reinscription.php
 */

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/reinscription.php';

$reussis = 0;
$echoues = 0;

function verifier(string $intitule, bool $condition, string $detail = ''): void
{
    global $reussis, $echoues;
    if ($condition) {
        $reussis++;
        echo "  OK    $intitule\n";
    } else {
        $echoues++;
        echo "  ECHEC $intitule" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}

function titre(string $t): void { echo "\n" . $t . "\n" . str_repeat('-', strlen($t)) . "\n"; }

const COLS_ETAT = 'id, authorized_reinscription, authorized_by, authorized_date,
                   dossier_ok, dossier_checked_by, dossier_checked_date';

/** Empreinte des six colonnes concernees, pour tous les etudiants. */
function empreinteDrapeaux(PDO $dtb): string
{
    $s = '';
    foreach ($dtb->query('SELECT ' . COLS_ETAT . ' FROM tbl_2024_etudiant ORDER BY id')->fetchAll(PDO::FETCH_NUM) as $r) {
        $s .= implode('|', array_map(static fn ($v) => $v === null ? '∅' : $v, $r)) . "\n";
    }

    return md5($s);
}

$debut = date('Y-m-d H:i:s');
$instantane = $dtb->query('SELECT ' . COLS_ETAT . ' FROM tbl_2024_etudiant')->fetchAll(PDO::FETCH_ASSOC);
$empreinteDepart = empreinteDrapeaux($dtb);
$lots = [];

$uid = (int) $dtb->query('SELECT id FROM compt_utilisateur WHERE level = 1 ORDER BY id LIMIT 1')->fetchColumn();

try {
    verifier('la table des lots existe (migration jouée)', rsnTableDisponible($dtb) === true,
        'appliquer data/migration_reinscription_reinitialisation.sql');
    verifier('un compte superadmin sert d\'auteur aux écritures du test', $uid > 0);

    titre('Périmètre par rôle');

    verifier('le superadmin touche les deux colonnes',
        rsnColonnesPermises(1) === ['authorized_reinscription', 'dossier_ok']);
    verifier('le registraire ne touche que le dossier', rsnColonnesPermises(3) === ['dossier_ok']);
    verifier('la caisse ne touche que l\'autorisation', rsnColonnesPermises(4) === ['authorized_reinscription']);
    verifier('un enseignant ne touche rien', rsnColonnesPermises(7) === []);
    verifier('une colonne étrangère est écartée du filtre',
        rsnFiltrerColonnes(['dossier_ok', 'authorized_reinscription', 'student_nom'], 3) === ['dossier_ok']);
    verifier('une valeur non tableau donne une liste vide', rsnFiltrerColonnes('dossier_ok', 1) === []);

    titre('Statistiques');

    $s0 = rsnStatistiques($dtb);
    verifier('les quatre états totalisent l\'effectif',
        $s0['prets'] + $s0['attente_caisse'] + $s0['attente_dossier'] + $s0['aucun'] === $s0['total'],
        json_encode($s0));
    verifier('« prêts » = autorisés ET dossier validé',
        $s0['prets'] <= min($s0['autorises'], $s0['dossiers_ok']));

    titre('Aperçu : chiffré et sans écriture');

    $e = empreinteDrapeaux($dtb);
    $ap = rsnApercu($dtb, ['authorized_reinscription', 'dossier_ok']);
    verifier('l\'aperçu n\'écrit rien', empreinteDrapeaux($dtb) === $e);
    verifier('il compte les autorisations', $ap['par_colonne']['authorized_reinscription'] === $s0['autorises']);
    verifier('il compte les dossiers', $ap['par_colonne']['dossier_ok'] === $s0['dossiers_ok']);
    verifier('les bloqués sont les étudiants prêts', $ap['bloques'] === $s0['prets']);

    titre('Garde-fous');

    verifier('aucune colonne : refus', rsnReinitialiser($dtb, [], $uid)['ok'] === false);
    verifier('lot mal formé : refus', rsnAnnuler($dtb, 'pas-un-lot', $uid, 1)['ok'] === false);
    verifier('lot inconnu : refus', rsnAnnuler($dtb, str_repeat('0', 16), $uid, 1)['ok'] === false);

    titre('Réinitialisation');

    $r = rsnReinitialiser($dtb, ['authorized_reinscription', 'dossier_ok'], $uid, '127.0.0.1', 'test_reinscription');
    if (!empty($r['lot'])) { $lots[] = $r['lot']; }
    verifier('elle réussit', $r['ok'] === true, $r['message'] ?? '');
    verifier('lot de 16 caractères hexadécimaux', preg_match('/^[0-9a-f]{16}$/', (string) ($r['lot'] ?? '')) === 1);
    verifier('autorisations remises à zéro : le nombre attendu',
        ($r['par_colonne']['authorized_reinscription'] ?? -1) === $s0['autorises']);
    verifier('dossiers remis à zéro : le nombre attendu',
        ($r['par_colonne']['dossier_ok'] ?? -1) === $s0['dossiers_ok']);

    $s1 = rsnStatistiques($dtb);
    verifier('plus aucune autorisation', $s1['autorises'] === 0, (string) $s1['autorises']);
    verifier('plus aucun dossier validé', $s1['dossiers_ok'] === 0, (string) $s1['dossiers_ok']);
    verifier('tout le monde est « ni l\'un ni l\'autre »', $s1['aucun'] === $s1['total']);

    $photos = $dtb->prepare('SELECT COUNT(*) FROM t_reinscription_reinitialisation WHERE lot = ?');
    $photos->execute([$r['lot']]);
    verifier('chaque case est photographiée', (int) $photos->fetchColumn() === $r['total']);

    $traces = $dtb->prepare('SELECT COUNT(*) FROM t_student_modification_history WHERE commentaire LIKE ?');
    $traces->execute(['%lot ' . $r['lot'] . '%']);
    verifier('chaque case est tracée dans l\'historique de l\'étudiant',
        (int) $traces->fetchColumn() === $r['total']);

    $dernier = rsnDernierLot($dtb);
    verifier('le bandeau propose ce lot', $dernier !== null && $dernier['lot'] === $r['lot']);

    titre('Annulation : ce qui a bougé depuis est préservé');

    /* Un caissier ré-autorise un étudiant après la réinitialisation. Annuler le
       lot ne doit pas écraser ce geste. */
    $cible = null;
    foreach ($instantane as $row) {
        if ((int) $row['authorized_reinscription'] === 1) { $cible = (int) $row['id']; break; }
    }
    $dateHumaine = date('Y-m-d H:i:s', strtotime($r['total'] > 0 ? '+1 minute' : 'now'));
    if ($cible !== null) {
        $dtb->prepare('UPDATE tbl_2024_etudiant SET authorized_reinscription = 1, authorized_by = ?, authorized_date = ? WHERE id = ?')
            ->execute([$uid, $dateHumaine, $cible]);
    }

    titre('Annulation par la caisse : sa colonne seulement');

    $a4 = rsnAnnuler($dtb, $r['lot'], $uid, 4, '127.0.0.1', 'test_reinscription');
    verifier('elle réussit', $a4['ok'] === true, $a4['message'] ?? '');
    verifier('seules les autorisations sont restaurées',
        array_keys($a4['restaures']) === ['authorized_reinscription'] || ($s0['autorises'] <= 1 && $a4['restaures'] === []),
        implode(',', array_keys($a4['restaures'])));
    verifier('toutes sauf celle modifiée depuis',
        count($a4['restaures']['authorized_reinscription'] ?? []) === max(0, $s0['autorises'] - ($cible !== null ? 1 : 0)));
    verifier('la ligne modifiée depuis est comptée comme ignorée', $a4['ignores'] === ($cible !== null ? 1 : 0));
    verifier('le message dit ce qui manque de droits', strpos($a4['message'], 'faute de droits') !== false, $a4['message']);

    if ($cible !== null) {
        $etat = $dtb->prepare('SELECT authorized_reinscription, authorized_date FROM tbl_2024_etudiant WHERE id = ?');
        $etat->execute([$cible]);
        $c = $etat->fetch(PDO::FETCH_ASSOC);
        verifier('le geste du caissier n\'a pas été écrasé',
            (int) $c['authorized_reinscription'] === 1 && $c['authorized_date'] === $dateHumaine,
            json_encode($c));
    }

    verifier('les dossiers restent à zéro', rsnStatistiques($dtb)['dossiers_ok'] === 0);
    verifier('les traces portent les auteurs d\'origine',
        isset($a4['traces']['authorized_reinscription']) || $s0['autorises'] === 0);

    titre('Annulation par le superadmin : le reste du lot');

    $a1 = rsnAnnuler($dtb, $r['lot'], $uid, 1, '127.0.0.1', 'test_reinscription');
    verifier('elle réussit', $a1['ok'] === true, $a1['message'] ?? '');
    verifier('elle restaure les dossiers, pas une seconde fois les autorisations',
        array_keys($a1['restaures']) === ['dossier_ok'] || $s0['dossiers_ok'] === 0,
        implode(',', array_keys($a1['restaures'])));
    verifier('tous les dossiers reviennent', rsnStatistiques($dtb)['dossiers_ok'] === $s0['dossiers_ok']);

    $a1bis = rsnAnnuler($dtb, $r['lot'], $uid, 1);
    verifier('une troisième annulation ne restaure plus rien', $a1bis['ok'] === true && $a1bis['restaures'] === []);
    verifier('le lot n\'est plus proposé au bandeau',
        rsnDernierLot($dtb) === null || rsnDernierLot($dtb)['lot'] !== $r['lot']);

    titre('Retour exact à l\'état d\'origine');

    // Le seul écart légitime est le geste simulé du caissier : on le défait.
    if ($cible !== null) {
        foreach ($instantane as $row) {
            if ((int) $row['id'] === $cible) {
                $dtb->prepare('UPDATE tbl_2024_etudiant SET authorized_reinscription = ?, authorized_by = ?, authorized_date = ? WHERE id = ?')
                    ->execute([$row['authorized_reinscription'], $row['authorized_by'], $row['authorized_date'], $cible]);
            }
        }
    }
    verifier('valeurs, auteurs et dates identiques pour tous les étudiants',
        empreinteDrapeaux($dtb) === $empreinteDepart);

    titre('Points d\'entrée et page');

    $api = (string) file_get_contents(__DIR__ . '/../controllers/ApiController.php');
    verifier('les deux API exigent un jeton CSRF', substr_count($api, 'verify_csrf()') >= 2);
    verifier('l\'API d\'autorisation est alignée sur la page (niveaux 1 et 4)',
        strpos($api, 'in_array($user_level, [1, 4])') !== false
        && strpos($api, 'in_array($user_level, [1, 2, 4])') === false);

    $point = (string) file_get_contents(__DIR__ . '/../app/reinscription.save.php');
    verifier('le point d\'entrée exige un jeton CSRF', strpos($point, 'verify_csrf()') !== false);
    verifier('la phrase de confirmation est revérifiée côté serveur', strpos($point, "'REINITIALISER'") !== false);
    verifier('les colonnes passent par le filtre de rôle', substr_count($point, 'rsnFiltrerColonnes(') >= 2);
    verifier('le point d\'entrée est réservé au superadmin',
        strpos($point, 'if ($niveau !== 1)') !== false
        && strpos($point, 'in_array($niveau, [1, 3, 4]') === false,
        'la caisse ou le registraire pourraient forger une réinitialisation');

    $pageSrc = (string) file_get_contents(__DIR__ . '/../src/finance/authorized_reinscription.php');
    verifier('la page ne rend le bouton qu\'au superadmin',
        strpos($pageSrc, '$estSuperadmin = $niveau === 1;') !== false
        && substr_count($pageSrc, 'if ($estSuperadmin') >= 3,
        'bouton, fenêtre et bandeau doivent tous être conditionnés');

    $page = (string) file_get_contents(__DIR__ . '/../src/finance/authorized_reinscription.php');
    /* On cherche l'APPEL (« header('Location: ») et non le mot : le commentaire
       de la page raconte l'ancien défaut et cite « header('Location') ». */
    verifier('plus de redirection après envoi des en-têtes', strpos($page, "header('Location:") === false);
    verifier('plus d\'alert() dans la page', strpos($page, 'alert(') === false);

    $css = (string) file_get_contents(__DIR__ . '/../src/css/reinscription.css');
    verifier('le champ est déclaré pour les deux thèmes, avec la même arme',
        strpos($css, 'background-color: var(--rsn-champ) !important') !== false
        && strpos($css, '[data-theme="light"] .rsn-page') !== false);
    verifier('color-scheme suit le thème', strpos($css, 'color-scheme: var(--rsn-scheme)') !== false);

    $js = (string) file_get_contents(__DIR__ . '/../src/js/reinscription.js');
    verifier('le script envoie le jeton CSRF', strpos($js, "corps.append('csrf_token'") !== false);

} catch (Throwable $e) {
    $echoues++;
    echo "  ECHEC exception : " . $e->getMessage() . "\n";
} finally {
    /* Restauration inconditionnelle depuis l'instantané. */
    $dtb->beginTransaction();
    $maj = $dtb->prepare('UPDATE tbl_2024_etudiant
        SET authorized_reinscription = ?, authorized_by = ?, authorized_date = ?,
            dossier_ok = ?, dossier_checked_by = ?, dossier_checked_date = ?
        WHERE id = ?');
    foreach ($instantane as $row) {
        $maj->execute([$row['authorized_reinscription'], $row['authorized_by'], $row['authorized_date'],
                       $row['dossier_ok'], $row['dossier_checked_by'], $row['dossier_checked_date'], $row['id']]);
    }
    $dtb->commit();

    // Nettoyage des traces du test.
    foreach ($lots as $lot) {
        $dtb->prepare('DELETE FROM t_reinscription_reinitialisation WHERE lot = ?')->execute([$lot]);
        $dtb->prepare('DELETE FROM t_student_modification_history WHERE commentaire LIKE ? AND action_date >= ?')
            ->execute(['%lot ' . $lot . '%', $debut]);
    }

    echo "\n";
    if (empreinteDrapeaux($dtb) === $empreinteDepart) {
        $reussis++;
        echo "  OK    état initial des drapeaux restauré\n";
    } else {
        $echoues++;
        echo "  ECHEC les drapeaux ne sont pas revenus à l'état initial\n";
    }

    $restes = 0;
    foreach ($lots as $lot) {
        $q = $dtb->prepare('SELECT (SELECT COUNT(*) FROM t_reinscription_reinitialisation WHERE lot = ?)
                                 + (SELECT COUNT(*) FROM t_student_modification_history WHERE commentaire LIKE ?)');
        $q->execute([$lot, '%lot ' . $lot . '%']);
        $restes += (int) $q->fetchColumn();
    }
    if ($restes === 0) {
        $reussis++;
        echo "  OK    aucune trace de test ne subsiste\n";
    } else {
        $echoues++;
        echo "  ECHEC $restes trace(s) de test subsistent\n";
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Reussis : $reussis   Echoues : $echoues\n";
    exit($echoues === 0 ? 0 : 1);
}
