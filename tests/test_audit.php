<?php
/* Tests du journal d'activité (data/audit.php) — base locale.
   php tests/test_audit.php

   Crée une table jetable, y écrit par tous les chemins observés (prepare +
   execute, bindValue, exec, query, transaction annulée), écrit le journal et
   relit ce qu'il contient. Supprime ensuite la table et ses lignes de journal. */

putenv('AUDIT_CLI=1');
require __DIR__ . '/../data/config.php';
require __DIR__ . '/../data/audit.php';

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

/* ---- analyse SQL, sans base */
verifier('lecture', Audit::operation('SELECT * FROM x'), null);
verifier('commentaire + insert', Audit::operation("/* c */ -- l\n  insert into t VALUES (1)"), 'INSERT');
verifier('table insert', Audit::tableDe('INSERT', 'INSERT IGNORE INTO `registrar_db`.`t_2023_notes` (a) VALUES (1)'), 't_2023_notes');
verifier('table update', Audit::tableDe('UPDATE', 'UPDATE t_2023_notes SET grade = ? WHERE id = ?'), 't_2023_notes');
verifier('table delete', Audit::tableDe('DELETE', 'DELETE FROM `candidat` WHERE id = 3'), 'candidat');
verifier('masquage paramètres', Audit::masquerParametres(['pseudo' => 'x', 'password' => 'y', 'h' => '$2y$10$abcdefghijklmnopqrstuv']),
         ['pseudo' => 'x', 'password' => '••••', 'h' => '••••']);
verifier('appareil', Audit::appareil('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'), 'Chrome · Windows');
verifier('libellé page', Audit::libelle('modification', '/app/.student/updatenote', ['t_2023_notes']), 'Saisie d\'une note · Notes');

/* ---- écritures réelles */
$pdo = auditConnexion(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
$brut = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$brut->exec('DROP TABLE IF EXISTS t_audit_test_jetable');
$brut->exec('CREATE TABLE t_audit_test_jetable (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(50), secret_token VARCHAR(80), valeur INT)');

Audit::reinitialiser();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/app/test/audit?token=abc&x=1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 14) Chrome/126.0 Mobile Safari/537.36';
$_SERVER['REMOTE_ADDR'] = '10.1.2.3';
$_POST = ['nom' => 'Rabe', 'infinit_password' => 'motdepasse'];

$st = $pdo->prepare('INSERT INTO t_audit_test_jetable (nom, secret_token, valeur) VALUES (:nom, :secret_token, :v)');
$st->execute(['nom' => 'alpha', 'secret_token' => 'jeton-secret', 'v' => 10]);
$pdo->query('SELECT 1')->fetchAll(); // une lecture de l'application ne doit pas perdre l'id non plus
$idAlpha = (int) $pdo->lastInsertId();
verifier('lastInsertId intact', $idAlpha > 0, true);

$pdo->prepare('UPDATE t_audit_test_jetable SET valeur = ?, secret_token = ? WHERE id = ?')->execute([11, 'autre-secret', $idAlpha]);
$pdo->prepare('UPDATE t_audit_test_jetable SET valeur = 99 WHERE id = ?')->execute([999999]); // 0 ligne : ignoré

$pdo->exec("INSERT INTO t_audit_test_jetable (nom, valeur) VALUES ('beta', 20), ('gamma', 30)");
$st = $pdo->prepare('UPDATE t_audit_test_jetable SET nom = :n WHERE valeur >= :min');
$st->bindValue(':n', 'renomme');
$st->bindValue(':min', 20, PDO::PARAM_INT);
$st->execute();

$pdo->beginTransaction();
$pdo->prepare('DELETE FROM t_audit_test_jetable WHERE id = ?')->execute([$idAlpha]);
$pdo->rollBack();

$pdo->query("DELETE FROM t_audit_test_jetable WHERE nom = 'renomme'");
Audit::evenement('login_failed', ['pseudo' => 'rabe', 'password' => 'essai']);

try {
    $pdo->exec('INSERT INTO table_qui_nexiste_pas VALUES (1)');
} catch (PDOException $e) {
}

Audit::enregistrer();

/* ---- relecture */
$action = $brut->query("SELECT * FROM t_audit_requete WHERE url LIKE '/app/test/audit%' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
verifier('action écrite', is_array($action), true);
if (is_array($action)) {
    verifier('type : échec de connexion prime', $action['type_action'], 'echec_connexion');
    verifier('url masquée', $action['url'], '/app/test/audit?token=••••&x=1');
    verifier('données masquées', json_decode($action['donnees'], true), ['nom' => 'Rabe', 'infinit_password' => '••••']);
    verifier('événement masqué', json_decode($action['evenements'], true)[0]['details'], ['pseudo' => 'rabe', 'password' => '••••']);
    verifier('appareil', $action['appareil'], 'Chrome · Android');
    verifier('ip', $action['ip'], '10.1.2.3');
    verifier('tables touchées', $action['tables_touchees'], ',t_audit_test_jetable,table_qui_nexiste_pas,');

    $modifs = $brut->query('SELECT * FROM t_audit_modification WHERE requete_id = ' . (int) $action['id'] . ' ORDER BY ordre')->fetchAll(PDO::FETCH_ASSOC);
    verifier('nombre de modifications', count($modifs), 7);
    verifier('opérations', array_column($modifs, 'operation'), ['INSERT', 'UPDATE', 'INSERT', 'UPDATE', 'DELETE', 'DELETE', 'INSERT']);

    $c = json_decode($modifs[0]['changements'], true);
    verifier('insert : ligne relue', [$c['lignes'][0]['apres']['nom'], $c['lignes'][0]['apres']['secret_token']], ['alpha', '••••']);
    verifier('insert : paramètre masqué', json_decode($modifs[0]['parametres'], true)['secret_token'], '••••');

    $c = json_decode($modifs[1]['changements'], true);
    // MySQL 8 renvoie les colonnes INT en entiers : on compare en texte.
    verifier('update : avant/après', [(string) $c['lignes'][0]['avant']['valeur'], (string) $c['lignes'][0]['apres']['valeur']], ['10', '11']);
    verifier('update : secret masqué', [$c['lignes'][0]['avant']['secret_token'], $c['lignes'][0]['apres']['secret_token']], ['••••', '••••']);
    verifier('update : clé', array_map('strval', $c['lignes'][0]['cle']), ['id' => (string) $idAlpha]);

    $c = json_decode($modifs[3]['changements'], true);
    verifier('bindValue : 2 lignes renommées', array_map(fn($l) => $l['apres']['nom'], $c['lignes']), ['renomme', 'renomme']);

    verifier('transaction annulée', $modifs[4]['transaction_statut'], 'annulee');
    $c = json_decode($modifs[5]['changements'], true);
    verifier('query delete : image avant', count($c['lignes']), 2);
    verifier('erreur SQL notée', $modifs[6]['erreur'] !== null && $modifs[6]['transaction_statut'] === 'hors', true);

    $brut->exec('DELETE FROM t_audit_requete WHERE id = ' . (int) $action['id']);
    verifier('cascade sur les modifications',
             (int) $brut->query('SELECT COUNT(*) FROM t_audit_modification WHERE requete_id = ' . (int) $action['id'])->fetchColumn(), 0);
}

$brut->exec('DROP TABLE IF EXISTS t_audit_test_jetable');

echo ($echecs === 0 ? 'OK' : "$echecs ÉCHEC(S)") . " — $total tests\n";
exit($echecs === 0 ? 0 : 1);
