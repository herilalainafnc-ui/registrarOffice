<?php
/* Verrous et enregistrements de « Mon compte », en HTTP reel.

   Le test opere sur un COMPTE JETABLE cree pour l'occasion, jamais sur un
   compte existant : il modifie nom, email, identifiant, mot de passe et role.
   Le compte est supprime a la fin, y compris si une assertion echoue.

   Usage : php tests/_mc_test.php */

require __DIR__ . '/../data/backdb.php';

$ok = 0; $ko = 0;
function verifie(string $quoi, $attendu, $obtenu) : void {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  ok    $quoi\n"; return; }
    $ko++;
    echo "  ECHEC $quoi\n        attendu: " . var_export($attendu, true)
       . "\n        obtenu : " . var_export($obtenu, true) . "\n";
}

const BASE = 'https://registrar.test';
const CIBLE = BASE . '/app/.user/mon-compte.save.php';

/** Cree une session authentifiee pour un compte, renvoie [sid, jeton]. */
function session_pour(int $id) : array {
    session_save_path('C:/laragon/tmp');
    $sid = 'mc' . substr(md5($id . microtime(true)), 0, 22);
    session_id($sid);
    session_start();
    $_SESSION = [
        'user_id' => $id, 'logged_in' => true, 'LAST_ACTIVITY' => time(),
        'csrf_token' => bin2hex(random_bytes(32)), 'csrf_token_time' => time(),
    ];
    $jeton = $_SESSION['csrf_token'];
    session_write_close();
    return [$sid, $jeton];
}

/** POST vers le point d'entree. Renvoie [code, tableau decode]. */
function appel(string $sid, array $donnees) : array {
    $corps = http_build_query($donnees);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                      . "X-Requested-With: XMLHttpRequest\r\n"
                      . "Cookie: PHPSESSID=$sid\r\n",
            'content' => $corps,
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

/* --- Comptes jetables --------------------------------------------------- */
/* Balayage prealable : une execution interrompue avant son finally laisse
   des comptes derriere elle. C'est arrive une fois, avec un superadmin. */
$dtb->exec("DELETE FROM compt_utilisateur WHERE mail LIKE 'zz.%@example.invalid'");

$suffixe = substr(md5((string) microtime(true)), 0, 6);
$pseudoTest = 'zz_test_' . $suffixe;
$mailTest = 'zz.test.' . $suffixe . '@example.invalid';
$mdpInitial = 'MotDePasse#2026';

$ins = $dtb->prepare(
    "INSERT INTO compt_utilisateur
       (nom, prenom, post, pseudo, mail, level, password, privilege, photos,
        etat, theme, update_user, date_entry, user_type)
     VALUES (?, ?, 'Poste test', ?, ?, ?, ?, ?, '', 0, '', 0, CURDATE(), ?)");
/* etat = 0 : ces comptes ne doivent JAMAIS pouvoir servir a se connecter.
   Middleware::getUserById filtre sur etat = 1, donc un compte oublie ici
   est inerte — ce qui compte, l'un des deux etant superadmin. Le point
   d'entree, lui, lit la session et fonctionne quand meme. */

/* Un compte NON superadmin (niveau 4) pour les verrous, et un superadmin pour
   le changement de role. */
$ins->execute(['TESTNOM', 'Testprenom', $pseudoTest, $mailTest, 4,
               password_hash($mdpInitial, PASSWORD_DEFAULT), 'comptabilite', 'comptabilite']);
$idSimple = (int) $dtb->lastInsertId();

$ins->execute(['TESTSUPER', 'Testsuper', $pseudoTest . '_s', 'zz.super.' . $suffixe . '@example.invalid',
               1, password_hash($mdpInitial, PASSWORD_DEFAULT), 'superadmin', 'superadmin']);
$idSuper = (int) $dtb->lastInsertId();

/* Les sessions AVANT toute sortie : un echo compte comme des en-tetes envoyes,
   et session_id() refuse alors de changer d'identifiant — les appels
   repartaient tous en 401. */
[$sid, $jeton]   = session_pour($idSimple);
[$sidS, $jetonS] = session_pour($idSuper);

echo "Comptes jetables : #$idSimple (comptabilité) et #$idSuper (superadmin)\n\n";

try {
    echo "== Verrous\n";
    [$c, $r] = appel($sid, ['action' => 'identite', 'nom' => 'X']);
    verifie('sans jeton CSRF -> refus', 400, $c);
    verifie('sans jeton -> drapeau csrf_expire', true, !empty($r['csrf_expire']));

    [$c, $r] = appel($sid, ['action' => 'inconnue', 'csrf_token' => $jeton]);
    verifie('action inconnue -> 400', 400, $c);

    echo "\n== Role : reserve au superadmin\n";
    [$c, $r] = appel($sid, ['action' => 'role', 'csrf_token' => $jeton, 'level' => 1]);
    verifie('compte niveau 4 -> 403', 403, $c);
    $apres = $dtb->query("SELECT level FROM compt_utilisateur WHERE id = $idSimple")->fetchColumn();
    verifie('le niveau n a PAS change', '4', (string) $apres);

    /* AVANT la retrogradation : une fois passe registraire, ce compte n'est
       plus superadmin et repondrait 403 a tout — l'ordre compte. */
    [$c, $r] = appel($sidS, ['action' => 'role', 'csrf_token' => $jetonS, 'level' => 99]);
    verifie('role inconnu -> 422', 422, $c);

    [$c, $r] = appel($sidS, ['action' => 'role', 'csrf_token' => $jetonS, 'level' => 3]);
    verifie('superadmin -> 200', 200, $c);
    $l = $dtb->query("SELECT level FROM compt_utilisateur WHERE id = $idSuper")->fetchColumn();
    $p = $dtb->query("SELECT privilege FROM compt_utilisateur WHERE id = $idSuper")->fetchColumn();
    $t = $dtb->query("SELECT user_type FROM compt_utilisateur WHERE id = $idSuper")->fetchColumn();
    verifie('niveau ecrit', '3', (string) $l);
    verifie('privilege coherent avec le niveau', 'registrar', $p);
    verifie('user_type coherent avec le niveau', 'registrar', $t);

    /* Une fois retrograde, le compte ne peut plus toucher aux roles : c'est le
       sens du verrou, et c'est aussi l'irreversibilite dont la page avertit. */
    [$c, $r] = appel($sidS, ['action' => 'role', 'csrf_token' => $jetonS, 'level' => 1]);
    verifie('apres retrogradation, ne peut plus se re-promouvoir -> 403', 403, $c);

    echo "\n== Identite\n";
    [$c, $r] = appel($sid, ['action' => 'identite', 'csrf_token' => $jeton,
                            'nom' => '', 'prenom' => '', 'pseudo' => 'a', 'mail' => 'pas-un-email']);
    verifie('champs invalides -> 422', 422, $c);
    verifie('le nom est signale', true, isset($r['erreurs']['nom']));
    verifie('le prenom est signale', true, isset($r['erreurs']['prenom']));
    verifie('l identifiant trop court est signale', true, isset($r['erreurs']['pseudo']));
    verifie('l email invalide est signale', true, isset($r['erreurs']['mail']));

    // Unicite : on tente de prendre l'identifiant du compte superadmin.
    [$c, $r] = appel($sid, ['action' => 'identite', 'csrf_token' => $jeton,
                            'nom' => 'A', 'prenom' => 'B', 'post' => 'P',
                            'pseudo' => $pseudoTest . '_s', 'mail' => $mailTest]);
    verifie('identifiant deja pris -> 422', 422, $c);
    verifie('l identifiant est signale', true, isset($r['erreurs']['pseudo']));

    [$c, $r] = appel($sid, ['action' => 'identite', 'csrf_token' => $jeton,
                            'nom' => 'NOUVEAUNOM', 'prenom' => 'Nouveauprenom',
                            'post' => 'Nouvelle fonction',
                            'pseudo' => $pseudoTest . '_v2', 'mail' => 'zz.v2.' . $suffixe . '@example.invalid']);
    verifie('identite valide -> 200', 200, $c);
    $row = $dtb->query("SELECT nom, prenom, post, pseudo, mail FROM compt_utilisateur WHERE id = $idSimple")
               ->fetch(PDO::FETCH_ASSOC);
    verifie('nom enregistre', 'NOUVEAUNOM', $row['nom']);
    verifie('fonction enregistree', 'Nouvelle fonction', $row['post']);
    verifie('identifiant enregistre', $pseudoTest . '_v2', $row['pseudo']);

    echo "\n== Mot de passe\n";
    [$c, $r] = appel($sid, ['action' => 'motdepasse', 'csrf_token' => $jeton,
                            'actuel' => 'faux', 'nouveau' => 'AutreMdp#2026', 'confirmation' => 'AutreMdp#2026']);
    verifie('mot de passe actuel faux -> 422', 422, $c);
    verifie('le champ actuel est signale', true, isset($r['erreurs']['actuel']));

    [$c, $r] = appel($sid, ['action' => 'motdepasse', 'csrf_token' => $jeton,
                            'actuel' => $mdpInitial, 'nouveau' => 'court', 'confirmation' => 'court']);
    verifie('trop court -> 422', 422, $c);

    [$c, $r] = appel($sid, ['action' => 'motdepasse', 'csrf_token' => $jeton,
                            'actuel' => $mdpInitial, 'nouveau' => 'AutreMdp#2026', 'confirmation' => 'PasPareil#2026']);
    verifie('confirmation differente -> 422', 422, $c);
    verifie('la confirmation est signalee', true, isset($r['erreurs']['confirmation']));

    [$c, $r] = appel($sid, ['action' => 'motdepasse', 'csrf_token' => $jeton,
                            'actuel' => $mdpInitial, 'nouveau' => $mdpInitial, 'confirmation' => $mdpInitial]);
    verifie('identique a l actuel -> 422', 422, $c);

    [$c, $r] = appel($sid, ['action' => 'motdepasse', 'csrf_token' => $jeton,
                            'actuel' => $mdpInitial, 'nouveau' => 'AutreMdp#2026', 'confirmation' => 'AutreMdp#2026']);
    verifie('changement valide -> 200', 200, $c);
    $hash = $dtb->query("SELECT password FROM compt_utilisateur WHERE id = $idSimple")->fetchColumn();
    verifie('le nouveau mot de passe verifie', true, password_verify('AutreMdp#2026', $hash));
    verifie('stocke en bcrypt', true, str_starts_with((string) $hash, '$2y$'));

    /* L'ancien format sha256 doit encore etre accepte a la verification :
       les comptes anciens n'ont pas ete migres. */
    $ancien = hash('sha256', 'AncienMdp' . 'fixing_password');
    $dtb->prepare('UPDATE compt_utilisateur SET password = ? WHERE id = ?')->execute([$ancien, $idSimple]);
    [$c, $r] = appel($sid, ['action' => 'motdepasse', 'csrf_token' => $jeton,
                            'actuel' => 'AncienMdp', 'nouveau' => 'MdpMigre#2026', 'confirmation' => 'MdpMigre#2026']);
    verifie('ancien format sha256 accepte -> 200', 200, $c);
    $hash2 = $dtb->query("SELECT password FROM compt_utilisateur WHERE id = $idSimple")->fetchColumn();
    verifie('migre vers bcrypt', true, str_starts_with((string) $hash2, '$2y$'));

    echo "\n== Aucun identifiant d'utilisateur n'est accepte\n";
    /* On tente d'ecrire sur le compte superadmin depuis la session du compte
       simple, par tous les noms de champ plausibles. */
    [$c, $r] = appel($sid, ['action' => 'identite', 'csrf_token' => $jeton,
                            'id' => $idSuper, 'user_id' => $idSuper, 'idUser' => $idSuper,
                            'nom' => 'PIRATE', 'prenom' => 'Pirate', 'post' => 'x',
                            'pseudo' => $pseudoTest . '_v3', 'mail' => 'zz.v3.' . $suffixe . '@example.invalid']);
    verifie('la requete aboutit sur SON compte', 200, $c);
    $nomSuper = $dtb->query("SELECT nom FROM compt_utilisateur WHERE id = $idSuper")->fetchColumn();
    verifie('le compte superadmin est INTACT', 'TESTSUPER', $nomSuper);
    $nomSimple = $dtb->query("SELECT nom FROM compt_utilisateur WHERE id = $idSimple")->fetchColumn();
    verifie('seul son propre compte a change', 'PIRATE', $nomSimple);

} finally {
    $dtb->prepare('DELETE FROM compt_utilisateur WHERE id IN (?, ?)')->execute([$idSimple, $idSuper]);
    // Filet : par motif aussi, au cas ou un identifiant aurait ete perdu.
    $dtb->exec("DELETE FROM compt_utilisateur WHERE mail LIKE 'zz.%@example.invalid'");
    $reste = (int) $dtb->query("SELECT COUNT(*) FROM compt_utilisateur WHERE id IN ($idSimple, $idSuper)")->fetchColumn();
    echo "\nnettoyage : $reste compte(s) de test restant(s)\n";
}

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko === 0 ? 0 : 1);
