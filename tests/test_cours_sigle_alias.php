<?php
/*
 * Le sigle affiche est un alias : poser ou changer un alias ne doit deplacer
 * AUCUNE note. C est la garantie que « les etudiants restent dans leurs cours ».
 *
 * Mesure trois quantites avant / pendant / apres, et exige qu elles soient
 * identiques :
 *   retrouvees     notes dont le sigle est celui de la fiche que id_cours designe
 *   par-sigle-seul notes SANS id_cours qu un cours vivant retrouve par le sigle
 *   perdues        notes rattachees a une fiche mais portant un autre sigle
 *
 * Les alias d essai sont retires a la fin. Usage : php tests/test_cours_sigle_alias.php
 */
$base = 'C:/laragon/www/registrar';
require $base . '/data/config.php';
require $base . '/data/cours.sigle.php';
$p = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

$echecs = 0;
$ok = function (string $quoi, bool $vrai) use (&$echecs) {
    printf("  [%s] %s\n", $vrai ? ' OK ' : 'ECHEC', $quoi);
    if (!$vrai) { $echecs++; }
};

/* ---- 1. L installateur ---- */
coursSigleAliasInstaller($p);
$col = (int) $p->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_2023_cours'
      AND COLUMN_NAME = 'sigle_affiche'")->fetchColumn();
$ok('la colonne sigle_affiche existe', $col === 1);
coursSigleAliasInstaller($p);   // idempotent
$ok('poser la colonne deux fois ne casse rien', true);

/* ---- 2. L invariant, mesure de reference ---- */
$C = 'COLLATE utf8mb4_unicode_ci';
$invariant = function () use ($p, $C): array {
    $q = fn(string $sql) => (int) $p->query($sql)->fetchColumn();
    return [
        'retrouvees' => $q("SELECT COUNT(*) FROM t_2023_notes n JOIN t_2023_cours c ON c.id = n.id_cours
                             WHERE n.Sigle $C = c.Sigle $C"),
        'par_sigle'  => $q("SELECT COUNT(*) FROM t_2023_notes n
                             WHERE TRIM(COALESCE(n.id_cours,'')) = ''
                               AND EXISTS (SELECT 1 FROM t_2023_cours c
                                            WHERE c.`remove` != 1 AND c.Sigle $C = n.Sigle $C)"),
        'perdues'    => $q("SELECT COUNT(*) FROM t_2023_notes n JOIN t_2023_cours c ON c.id = n.id_cours
                             WHERE n.Sigle $C <> c.Sigle $C"),
    ];
};
$avant = $invariant();
printf("\n  invariant AVANT : retrouvees=%d  par-sigle-seul=%d  perdues=%d\n",
    $avant['retrouvees'], $avant['par_sigle'], $avant['perdues']);

/* ---- 3. On pose des alias, comme le ferait le bulletin ---- */
$essais = $p->query("SELECT id, Sigle, sigle_affiche FROM t_2023_cours WHERE `remove` != 1 ORDER BY id LIMIT 12")
            ->fetchAll(PDO::FETCH_ASSOC);
/* Les alias reels de ces cours, remis a la fin : la base porte les codes du
   bulletin, le test ne doit rien en effacer. */
$aliasReels = array_column($essais, 'sigle_affiche', 'id');
$pose = $p->prepare('UPDATE t_2023_cours SET sigle_affiche = ? WHERE id = ?');
foreach ($essais as $i => $c) {
    $pose->execute(['ZZTEST ' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), $c['id']]);
}
printf("  %d alias poses\n", count($essais));

$apres = $invariant();
printf("  invariant APRES : retrouvees=%d  par-sigle-seul=%d  perdues=%d\n\n",
    $apres['retrouvees'], $apres['par_sigle'], $apres['perdues']);

$ok('aucune note retrouvee n\'est perdue', $avant['retrouvees'] === $apres['retrouvees']);
$ok('les notes sans id_cours restent atteignables', $avant['par_sigle'] === $apres['par_sigle']);
$ok('le nombre de notes mal appariees ne bouge pas', $avant['perdues'] === $apres['perdues']);

/* ---- 4. L affichage suit l alias ---- */
$l = $p->query("SELECT Sigle, sigle_affiche FROM t_2023_cours WHERE id = " . (int) $essais[0]['id'])
       ->fetch(PDO::FETCH_ASSOC);
$ok('coursSigleAffiche rend l\'alias', coursSigleAffiche($l) === 'ZZTEST 001');
$ok('la cle technique est intacte', $l['Sigle'] === $essais[0]['Sigle']);
$ok('sans alias, on retombe sur la cle', coursSigleAffiche(['Sigle' => 'ABC 123', 'sigle_affiche' => null]) === 'ABC 123');
$ok('colonne absente de la requete : repli sur la cle', coursSigleAffiche(['Sigle' => 'ABC 123']) === 'ABC 123');

/* ---- 5. L unicite du code affiche ---- */
$d = coursSigleAliasDoublons($p, 'ZZTEST 001', null);
$ok('un alias deja pris est refuse', $d !== []);
$d = coursSigleAliasDoublons($p, 'ZZTEST 001', (int) $essais[0]['id']);
$ok('son propre alias reste permis', $d === []);
$sansAlias = $p->query("SELECT id, Sigle FROM t_2023_cours WHERE `remove` != 1
        AND TRIM(COALESCE(sigle_affiche, '')) = '' AND id NOT IN (" . implode(',', array_column($essais, 'id')) . ")
      ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$d = coursSigleAliasDoublons($p, (string) $sansAlias['Sigle'], null);
$ok('un code affiche par un autre cours (sa cle, sans alias) est refuse', in_array((int) $sansAlias['id'], array_column($d, 'id'), true));
/* Renumerotation en chaine : la cle d'un cours qui affiche un AUTRE code ne
   bloque plus (les pages retrouvent les etudiants par la cle, jamais par le
   code affiche) ; a la creation d'un cours, elle reste refusee. */
$d = coursSigleAliasDoublons($p, (string) $essais[5]['Sigle'], null);
$ok('la cle d\'un cours qui affiche un autre code ne bloque plus un alias', !in_array((int) $essais[5]['id'], array_column($d, 'id'), true));
$d = coursSigleCreationDoublons($p, (string) $essais[5]['Sigle']);
$ok('a la creation d\'un cours, cette cle reste refusee', in_array((int) $essais[5]['id'], array_column($d, 'id'), true));
$d = coursSigleAliasDoublons($p, 'ZZLIBRE 999', null);
$ok('un alias libre est accepte', $d === []);

/* ---- 6. Retour arriere : chaque cours reprend son alias reel ---- */
foreach ($aliasReels as $id => $alias) {
    $pose->execute([$alias, $id]);
}
$reste = (int) $p->query("SELECT COUNT(*) FROM t_2023_cours WHERE sigle_affiche LIKE 'ZZTEST %'")->fetchColumn();
$ok('tous les alias d\'essai sont retires', $reste === 0);
$remis = $p->query("SELECT id, sigle_affiche FROM t_2023_cours WHERE id IN (" . implode(',', array_keys($aliasReels)) . ")")
           ->fetchAll(PDO::FETCH_KEY_PAIR);
$ok('les alias reels sont remis tels quels', array_map('strval', $remis) == array_map('strval', $aliasReels));
$final = $invariant();
$ok('invariant identique a l\'etat initial', $final === $avant);

printf("\n  %s\n", $echecs === 0 ? 'TOUT PASSE.' : "$echecs ECHEC(S).");
exit($echecs === 0 ? 0 : 1);
