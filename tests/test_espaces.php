<?php
/* Les deux espaces d'une personne du personnel qui est aussi enseignante (Middleware::espacesDeLaPersonne,
   aDeuxEspaces, basculerEspace) : le compte du personnel, la fiche enseignant, le compte enseignant créé
   au premier passage. Sur la base locale ; les comptes et fiches du test sont effacés à la fin.
     php tests/test_espaces.php */
ob_start();   // session_regenerate_id() de la connexion ne doit pas buter sur une sortie déjà partie
require __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/middleware.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
initMiddleware($dtb);

$total = 0; $echecs = 0;
function verifier(string $nom, $obtenu, $attendu): void
{
    global $total, $echecs;
    $total++;
    if ($obtenu !== $attendu) {
        $echecs++;
        fwrite(STDOUT, "ÉCHEC  $nom\n        obtenu  : " . json_encode($obtenu, JSON_UNESCAPED_UNICODE) . "\n        attendu : " . json_encode($attendu, JSON_UNESCAPED_UNICODE) . "\n");
    }
}

$MARQUE = 'zz-espaces';
$nettoyer = function () use ($dtb, $MARQUE) {
    $dtb->exec("DELETE FROM compt_utilisateur WHERE pseudo LIKE '$MARQUE%' OR mail LIKE '$MARQUE%'");
    $dtb->exec("DELETE FROM teacher WHERE email LIKE '$MARQUE%'");
};
$nettoyer();

$uidFiche = 990000 + random_int(1, 9000);
$fiche = function (string $mail, int $remove = 0) use ($dtb, $MARQUE, &$uidFiche) {
    $uidFiche++;
    $dtb->prepare("INSERT INTO teacher (teacher_id, uid, name, lastName, sex, email, position, remove) VALUES (?, ?, 'Zz-Fiche', 'Prénom Test', 1, ?, 'Professeur', ?)")
        ->execute([$uidFiche, $uidFiche, $mail, $remove]);
    return $uidFiche;
};
$compte = function (string $pseudo, int $niveau, string $mail, ?int $teacherUid = null) use ($dtb) {
    $privilege = [1 => 'superadmin', 2 => 'admin', 3 => 'registrar', 4 => 'compta', 5 => 'media', 6 => 'chef_mention', 7 => 'teacher', 8 => 'student', 9 => 'work_education'][$niveau];
    $dtb->prepare("INSERT INTO compt_utilisateur (pseudo, password, nom, prenom, post, mail, level, privilege, user_type, teacher_uid, etat, photos, theme, update_user, date_entry)
                   VALUES (?, 'x', 'Zz-Nom', 'Zz-Prénom', 'Test', ?, ?, ?, ?, ?, 1, '', '', 0, CURDATE())")
        ->execute([$pseudo, $mail, $niveau, $privilege, $privilege, $teacherUid]);
    return $dtb->query("SELECT * FROM compt_utilisateur WHERE pseudo = " . $dtb->quote($pseudo) . " ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
};
$lire = fn (string $pseudo) => $dtb->query("SELECT * FROM compt_utilisateur WHERE pseudo = " . $dtb->quote($pseudo))->fetchAll(PDO::FETCH_ASSOC);
$comptes = fn (int $uid) => (int) $dtb->query("SELECT COUNT(*) FROM compt_utilisateur WHERE teacher_uid = $uid AND level = 7")->fetchColumn();
$ouvrir = function (array $u) { $_SESSION = ['user_id' => $u['id'], 'user_level' => $u['level']]; Middleware::class; (new ReflectionProperty(Middleware::class, 'currentUser'))->setValue(null, null); };

try {
    /* 1. personne du personnel, aucune fiche : un seul espace */
    $seul = $compte("$MARQUE-seul", 5, "$MARQUE-seul@zurcher.edu.mg");
    verifier('sans fiche : un seul espace', Middleware::espacesDeLaPersonne($seul), null);

    /* 2. le courriel du compte est celui de la fiche : deux espaces, compte enseignant pas encore créé */
    $uid = $fiche("$MARQUE-media@zurcher.edu.mg");
    $media = $compte("$MARQUE-media", 5, "$MARQUE-media@zurcher.edu.mg");
    $e = Middleware::espacesDeLaPersonne($media);
    verifier('média + fiche (courriel) : deux espaces', [$e['actuel'], (int) $e['personnel']['id'] === (int) $media['id'], $e['enseignant'], (int) $e['fiche']['uid'] === $uid], ['personnel', true, null, true]);

    /* 3. l'adresse s'écrit autrement (majuscules, espaces) : la fiche est retrouvée */
    $uid2 = $fiche("$MARQUE-super@zurcher.edu.mg");
    $super = $compte("$MARQUE-super", 1, "  $MARQUE-Super@Zurcher.edu.mg ");
    verifier('superadmin + fiche (casse, espaces)', Middleware::espacesDeLaPersonne($super)['actuel'] ?? null, 'personnel');

    /* 4. un compte relié à la fiche par teacher_uid, sans courriel commun */
    $uid3 = $fiche("$MARQUE-autre@zurcher.edu.mg");
    $compta = $compte("$MARQUE-compta", 4, "$MARQUE-compta@zurcher.edu.mg", $uid3);
    verifier('comptabilité reliée par teacher_uid', Middleware::espacesDeLaPersonne($compta)['actuel'] ?? null, 'personnel');

    /* 5. les chefs de mention, enseignants et étudiants n'ont pas de choix à faire */
    $chef = $compte("$MARQUE-chef", 6, "$MARQUE-chef@zurcher.edu.mg", $uid3);
    verifier('chef de mention : pas de choix (son espace enseignant est déjà dans son compte)', Middleware::espacesDeLaPersonne($chef), null);
    $etu = $compte("$MARQUE-etu", 8, "$MARQUE-etu@zurcher.edu.mg", $uid3);
    verifier('étudiant : pas de choix', Middleware::espacesDeLaPersonne($etu), null);

    /* 6. fiche retirée ou courriel vide : pas de choix */
    $uid4 = $fiche("$MARQUE-parti@zurcher.edu.mg", 1);
    $parti = $compte("$MARQUE-parti", 3, "$MARQUE-parti@zurcher.edu.mg");
    verifier('fiche retirée : pas de choix', Middleware::espacesDeLaPersonne($parti), null);
    $vide = $compte("$MARQUE-vide", 3, "");
    verifier('compte sans courriel : pas de choix', Middleware::espacesDeLaPersonne($vide), null);

    /* 7. la bascule vers l'espace enseignant crée le compte (niveau 7, relié à la fiche, sans courriel) */
    $ouvrir($media);
    verifier('aDeuxEspaces() pour le média', Middleware::aDeuxEspaces(), true);
    $cible = Middleware::basculerEspace('enseignant');
    verifier('bascule : le compte enseignant est créé et devient courant', [(int) $cible['level'], (int) $cible['teacher_uid'] === $uid, $cible['mail'], (int) $_SESSION['user_id'] === (int) $cible['id'], $_SESSION['espace_choisi'] ?? null], [7, true, '', true, 'enseignant']);
    verifier('un seul compte enseignant pour la fiche', $comptes($uid), 1);

    /* 8. depuis l'espace enseignant, on retrouve l'autre espace, et on y revient */
    $e2 = Middleware::espacesDeLaPersonne($cible);
    verifier('depuis le compte enseignant : l\'autre espace est le compte du média', [$e2['actuel'], (int) $e2['personnel']['id'] === (int) $media['id']], ['enseignant', true]);
    $retour = Middleware::basculerEspace('personnel');
    verifier('retour à l\'espace du personnel', [(int) $retour['id'] === (int) $media['id'], (int) $_SESSION['user_id'] === (int) $media['id'], $_SESSION['espace_choisi'] ?? null], [true, true, 'personnel']);
    Middleware::basculerEspace('enseignant');
    verifier('le compte enseignant n\'est pas recréé', $comptes($uid), 1);

    /* 9. un compte enseignant ordinaire (sans compte du personnel) n'a qu'un espace */
    $prof = $compte("$MARQUE-prof", 7, "$MARQUE-prof@zurcher.edu.mg", $uid4);
    verifier('enseignant seul : pas de choix', Middleware::espacesDeLaPersonne($prof), null);

    /* 10. la bascule est refusée à qui n'a pas deux espaces, ou pour un espace inconnu */
    $ouvrir($seul);
    verifier('bascule refusée sans fiche', Middleware::basculerEspace('enseignant'), false);
    $ouvrir($media);
    verifier('espace inconnu refusé', Middleware::basculerEspace('administrateur'), false);

    /* 11. une connexion neuve redemande l'espace */
    $_SESSION['espace_choisi'] = 'enseignant';
    $m = new ReflectionMethod(Middleware::class, 'loginUser');
    @$m->invoke(null, $media, false);
    verifier('connexion neuve : le choix est oublié', isset($_SESSION['espace_choisi']), false);
} finally {
    $nettoyer();
}
ob_end_clean();
fwrite(STDOUT, $echecs === 0 ? "OK : $total vérifications.\n" : "$echecs échec(s) sur $total.\n");
exit($echecs === 0 ? 0 : 1);
