<?php
/**
 * Frais supplementaires du bareme — definis par le registrariat, appliques
 * automatiquement aux etudiants que le bareme designe.
 *
 * POURQUOI DES LIGNES ET NON DES COLONNES
 * t_2024_finance_detail_licence porte une colonne par frais (frais_generaux,
 * dortoir, cafeteria...) et t_2024_etudiant_finace une colonne cout_* par
 * frais. Ajouter un frais — « Alliance francaise », par exemple — demandait
 * donc une migration de schema, une retouche des cinq points d'ecriture des
 * frais et une ligne de plus dans la fiche d'inscription. Les frais
 * supplementaires sont donc des lignes :
 *
 *   t_frais_perso                le frais lui-meme : libelle, ordre, actif
 *   t_frais_perso_montant        son montant, avec la MEME cle que le bareme
 *                                (session, filiere, niveau, statut, semestre)
 *   t_2024_etudiant_frais_perso  ce qui a ete facture a un etudiant
 *
 * REPLI SUR LE BAREME DE BASE
 * Comme baremeLigne(), le montant defini pour la session l'emporte, sinon
 * celui de la session 0 s'applique. Une case vide dans une session signifie
 * « herite de la base » ; un montant a 0 signifie « pas facture ici », ce qui
 * est la maniere d'exempter une session, un niveau ou un statut.
 *
 * MONTANTS FIGES A L'INSCRIPTION
 * fraisPersoAppliquer() recopie le libelle et le montant dans la ligne de
 * l'etudiant, comme le font les colonnes cout_* : la fiche d'un dossier ancien
 * ne change pas quand le bareme evolue, et le libelle survit a la
 * desactivation du frais.
 */

require_once __DIR__ . '/finance.bareme.php'; // BAREME_BASE, baremeNiveaux(), baremeClauses()

/** Colonnes de cle communes au bareme et aux frais supplementaires. */
const FRAIS_PERSO_CRITERES = ['std_mention', 'level', 'std_status', 'semester'];

/**
 * A qui un frais s'applique, au-dela du couple filiere / niveau / statut.
 *
 * Ces conditions existaient en dur dans le code des frais fixes : la
 * graduation ne se facture qu'aux diplomants, le costume qu'aux nouveaux
 * etudiants, la cantine qu'aux abonnes. Les exprimer ici permet a un frais du
 * tableau de les reprendre, sans nouvelle ligne de code.
 *
 * @return array<string, string> code => libelle affiche
 */
function fraisPersoConditions(): array
{
    return [
        'aucune'          => 'Tous ceux que le barème désigne',
        'diplomant'       => 'Étudiants diplômants',
        'nouvel_etudiant' => 'Nouveaux étudiants',
        'abonne'          => 'Abonnés à la cafétéria',
        'interne'         => 'Étudiants internes',
    ];
}

/**
 * La condition est-elle remplie pour ce dossier ?
 *
 * @param array<string, mixed> $contexte diplomant, nouvel_etudiant, abonne, statut
 */
function fraisPersoConditionRemplie(string $condition, array $contexte): bool
{
    switch ($condition) {
        case 'diplomant':
            return (int) ($contexte['diplomant'] ?? 0) === 1;
        case 'nouvel_etudiant':
            return (int) ($contexte['nouvel_etudiant'] ?? 0) === 1;
        case 'abonne':
            return (int) ($contexte['abonne'] ?? 0) === 1;
        case 'interne':
            return strcasecmp(trim((string) ($contexte['statut'] ?? '')), 'Interne') === 0;
        case 'aucune':
        default:
            /* Condition inconnue — champ ecrit a la main en base, par exemple :
               on facture, comme pour « aucune ». Ne pas facturer silencieusement
               serait plus difficile a comprendre qu'un montant en trop. */
            return true;
    }
}

/**
 * Semestres ou le frais s'applique.
 *
 * Chaine vide = tous les semestres. Sinon une liste : « 2 », « 1,2 ».
 * C'est la regle du frais de graduation (deuxieme semestre seulement), rendue
 * reglable au lieu d'etre ecrite dans le code.
 */
function fraisPersoSemestreApplique($semestres, $semestre): bool
{
    $semestres = trim((string) $semestres);
    if ($semestres === '') {
        return true;
    }

    return in_array((string) (int) $semestre, array_map('trim', explode(',', $semestres)), true);
}

/** Liste de semestres propre : chiffres 1 a 4, sans doublon, ordonnes. */
function fraisPersoSemestresValides($semestres): string
{
    $vus = [];
    foreach (is_array($semestres) ? $semestres : explode(',', (string) $semestres) as $s) {
        $n = (int) trim((string) $s);
        if ($n >= 1 && $n <= 4) { $vus[$n] = true; }
    }
    ksort($vus);

    return implode(',', array_keys($vus));
}

/**
 * Cree les trois tables si elles manquent.
 *
 * Meme principe que espaceEtudiantInstaller() : la fonctionnalite s'installe a
 * la premiere ouverture de la page, sans intervention en base. Le fichier
 * data/migration_frais_perso.sql porte le meme schema, pour qui prefere
 * l'appliquer a la main.
 */
function fraisPersoInstaller(PDO $dtb): void
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;

    $presentes = $dtb->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('t_frais_perso', 't_frais_perso_montant', 't_2024_etudiant_frais_perso')"
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('t_frais_perso', $presentes, true)) {
        $dtb->exec(
            "CREATE TABLE IF NOT EXISTS t_frais_perso (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                libelle         VARCHAR(120) NOT NULL,
                actif           TINYINT(1)   NOT NULL DEFAULT 1,
                ordre           INT          NOT NULL DEFAULT 0,
                condition_frais VARCHAR(20)  NOT NULL DEFAULT 'aucune',
                semestres       VARCHAR(20)  NOT NULL DEFAULT '',
                date_entry      DATETIME     DEFAULT NULL,
                user_id         INT          DEFAULT NULL,
                UNIQUE KEY uk_frais_perso_libelle (libelle)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        /* Table creee avant l'etape 2 : les deux colonnes lui sont ajoutees.
           « condition » est un mot reserve de MySQL, d'ou condition_frais. */
        $colonnes = $dtb->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_frais_perso'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('condition_frais', $colonnes, true)) {
            $dtb->exec("ALTER TABLE t_frais_perso
                ADD COLUMN condition_frais VARCHAR(20) NOT NULL DEFAULT 'aucune' AFTER ordre");
        }
        if (!in_array('semestres', $colonnes, true)) {
            $dtb->exec("ALTER TABLE t_frais_perso
                ADD COLUMN semestres VARCHAR(20) NOT NULL DEFAULT '' AFTER condition_frais");
        }
    }

    if (!in_array('t_frais_perso_montant', $presentes, true)) {
        /* La cle unique porte les cinq composantes du bareme : une seule ligne
           par frais et par cas, donc pas les doublons que connaissent
           t_2024_finance_detail_licence et t_2024_etudiant_finace. */
        $dtb->exec(
            "CREATE TABLE IF NOT EXISTS t_frais_perso_montant (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                frais_id    INT         NOT NULL,
                session_id  INT         NOT NULL DEFAULT 0,
                std_mention VARCHAR(20) NOT NULL,
                level       INT         NOT NULL,
                std_status  VARCHAR(20) NOT NULL,
                semester    INT         NOT NULL,
                montant     DOUBLE      NOT NULL DEFAULT 0,
                UNIQUE KEY uk_frais_perso_cle (frais_id, session_id, std_mention, level, std_status, semester),
                KEY idx_frais_perso_session (session_id, std_mention, semester)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!in_array('t_2024_etudiant_frais_perso', $presentes, true)) {
        $dtb->exec(
            "CREATE TABLE IF NOT EXISTS t_2024_etudiant_frais_perso (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                student_id          VARCHAR(50)  NOT NULL,
                session_id          INT          NOT NULL,
                frais_id            INT          NOT NULL,
                libelle             VARCHAR(120) NOT NULL,
                montant             DOUBLE       NOT NULL DEFAULT 0,
                `remove`            TINYINT(1)   NOT NULL DEFAULT 0,
                date_entry          DATETIME     DEFAULT NULL,
                last_change_user_id INT          DEFAULT NULL,
                UNIQUE KEY uk_etudiant_frais (student_id, session_id, frais_id),
                KEY idx_etudiant_dossier (student_id, session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

/**
 * Les frais supplementaires definis, dans leur ordre d'affichage.
 *
 * @return array<int, array{id:int, libelle:string, actif:int, ordre:int}>
 */
function fraisPersoDefinitions(PDO $dtb, bool $actifsSeulement = true): array
{
    fraisPersoInstaller($dtb);

    $lignes = $dtb->query(
        'SELECT id, libelle, actif, ordre, condition_frais, semestres FROM t_frais_perso'
        . ($actifsSeulement ? ' WHERE actif = 1' : '')
        . ' ORDER BY ordre, libelle'
    )->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function (array $l): array {
        return [
            'id'        => (int) $l['id'],
            'libelle'   => (string) $l['libelle'],
            'actif'     => (int) $l['actif'],
            'ordre'     => (int) $l['ordre'],
            'condition' => (string) $l['condition_frais'],
            'semestres' => (string) $l['semestres'],
        ];
    }, $lignes);
}

/** Libelle propre : espaces normalises, longueur bornee par la colonne. */
function fraisPersoLibelleValide(string $libelle): string
{
    $libelle = trim((string) preg_replace('~\s+~u', ' ', $libelle));
    if ($libelle === '' || mb_strlen($libelle) > 120) {
        throw new InvalidArgumentException('Le libellé doit faire de 1 à 120 caractères.');
    }

    return $libelle;
}

/**
 * Cree un frais, ou reactive celui qui porte deja ce libelle.
 *
 * Reactiver plutot que dupliquer : deux frais de meme nom seraient
 * indistinguables sur la fiche de l'etudiant, et la cle unique du libelle
 * refuserait l'insertion de toute facon.
 */
function fraisPersoCreer(PDO $dtb, string $libelle, ?int $userId = null, string $condition = 'aucune', $semestres = ''): int
{
    fraisPersoInstaller($dtb);
    $libelle = fraisPersoLibelleValide($libelle);
    if (!array_key_exists($condition, fraisPersoConditions())) {
        throw new InvalidArgumentException('Condition inconnue : ' . $condition);
    }
    $semestres = fraisPersoSemestresValides($semestres);

    $req = $dtb->prepare('SELECT id, actif FROM t_frais_perso WHERE libelle = :l LIMIT 1');
    $req->execute(['l' => $libelle]);
    if ($deja = $req->fetch(PDO::FETCH_ASSOC)) {
        if ((int) $deja['actif'] === 0) {
            $dtb->prepare('UPDATE t_frais_perso SET actif = 1 WHERE id = :id')->execute(['id' => $deja['id']]);
        }

        return (int) $deja['id'];
    }

    $ordre = (int) $dtb->query('SELECT COALESCE(MAX(ordre), 0) + 1 FROM t_frais_perso')->fetchColumn();
    $ins = $dtb->prepare(
        'INSERT INTO t_frais_perso (libelle, actif, ordre, condition_frais, semestres, date_entry, user_id)
         VALUES (:l, 1, :o, :c, :sem, :d, :u)'
    );
    $ins->execute([
        'l' => $libelle, 'o' => $ordre, 'c' => $condition, 'sem' => $semestres,
        'd' => date('Y-m-d H:i:s'), 'u' => $userId,
    ]);

    return (int) $dtb->lastInsertId();
}

/**
 * Regle a qui et quand un frais s'applique.
 *
 * Ne touche ni aux montants ni a ce qui a deja ete facture : le reglage vaut
 * pour les prochaines inscriptions, comme le bareme lui-meme.
 */
function fraisPersoRegler(PDO $dtb, int $fraisId, string $condition, $semestres): bool
{
    fraisPersoInstaller($dtb);

    if (!array_key_exists($condition, fraisPersoConditions())) {
        throw new InvalidArgumentException('Condition inconnue : ' . $condition);
    }

    $req = $dtb->prepare(
        'UPDATE t_frais_perso SET condition_frais = :c, semestres = :s WHERE id = :id'
    );
    $req->execute([
        'c' => $condition,
        's' => fraisPersoSemestresValides($semestres),
        'id' => $fraisId,
    ]);

    return $req->rowCount() > 0;
}

/** Renomme un frais. Les lignes deja facturees gardent l'ancien libelle. */
function fraisPersoRenommer(PDO $dtb, int $fraisId, string $libelle): bool
{
    fraisPersoInstaller($dtb);
    $libelle = fraisPersoLibelleValide($libelle);

    $req = $dtb->prepare('UPDATE t_frais_perso SET libelle = :l WHERE id = :id');
    $req->execute(['l' => $libelle, 'id' => $fraisId]);

    return $req->rowCount() > 0;
}

/**
 * Active ou desactive un frais.
 *
 * La desactivation ne supprime ni les montants du bareme ni ce qui a ete
 * facture : le frais cesse d'etre propose et applique, et cela se defait.
 */
function fraisPersoActiver(PDO $dtb, int $fraisId, bool $actif): bool
{
    fraisPersoInstaller($dtb);

    $req = $dtb->prepare('UPDATE t_frais_perso SET actif = :a WHERE id = :id');
    $req->execute(['a' => $actif ? 1 : 0, 'id' => $fraisId]);

    return $req->rowCount() > 0;
}

/**
 * Montants des frais supplementaires d'une filiere et d'un semestre.
 *
 * Meme forme que baremeGrille() : une case par niveau et statut, avec le repli
 * sur le bareme de base et l'indication de provenance.
 *
 * @return array<int, array<string, array{montant:float|string, herite:bool}>>
 */
function fraisPersoGrille(PDO $dtb, $session_id, string $mention, int $semestre): array
{
    fraisPersoInstaller($dtb);
    $session_id = (int) $session_id;

    $lire = static function (int $sid) use ($dtb, $mention, $semestre): array {
        $req = $dtb->prepare(
            'SELECT frais_id, level, std_status, montant
               FROM t_frais_perso_montant
              WHERE session_id = :s AND std_mention = :m AND semester = :sem'
        );
        $req->execute(['s' => $sid, 'm' => $mention, 'sem' => $semestre]);

        $parCle = [];
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $parCle[(int) $l['frais_id']][$l['level'] . '|' . $l['std_status']] = (float) $l['montant'];
        }

        return $parCle;
    };

    $base      = $lire(BAREME_BASE);
    $surcharge = $session_id !== BAREME_BASE ? $lire($session_id) : [];

    $grille = [];
    foreach (fraisPersoDefinitions($dtb) as $f) {
        $id = $f['id'];
        foreach (baremeNiveaux() as $n) {
            foreach (baremeStatuts() as $statut) {
                $cle = $n['niveau'] . '|' . $statut;

                if (isset($surcharge[$id][$cle])) {
                    $grille[$id][$cle] = ['montant' => $surcharge[$id][$cle], 'herite' => false];
                } elseif (isset($base[$id][$cle])) {
                    $grille[$id][$cle] = ['montant' => $base[$id][$cle], 'herite' => $session_id !== BAREME_BASE];
                } else {
                    $grille[$id][$cle] = ['montant' => '', 'herite' => false];
                }
            }
        }
    }

    return $grille;
}

/**
 * Enregistre les montants des frais supplementaires d'une filiere.
 *
 * Une case VIDE efface la ligne : la session revient alors au bareme de base
 * pour ce frais et ce cas. Un 0 est une valeur, qui signifie « rien a payer »
 * et empeche donc le repli sur la base.
 *
 * @param  array<int|string, array<string, mixed>> $valeurs $valeurs[frais_id]['niveau|statut'] = montant
 * @return array{ecrites:int, supprimees:int}
 */
function fraisPersoEnregistrer(PDO $dtb, int $session_id, string $mention, int $semestre, array $valeurs): array
{
    fraisPersoInstaller($dtb);

    $connus = [];
    foreach (fraisPersoDefinitions($dtb, false) as $f) {
        $connus[$f['id']] = true;
    }
    $niveaux = array_column(baremeNiveaux(), 'niveau');
    $statuts = baremeStatuts();

    $ecrire = $dtb->prepare(
        'INSERT INTO t_frais_perso_montant
            (frais_id, session_id, std_mention, level, std_status, semester, montant)
         VALUES (:f, :s, :m, :n, :st, :sem, :montant)
         ON DUPLICATE KEY UPDATE montant = VALUES(montant)'
    );
    $supprimer = $dtb->prepare(
        'DELETE FROM t_frais_perso_montant
          WHERE frais_id = :f AND session_id = :s AND std_mention = :m
            AND level = :n AND std_status = :st AND semester = :sem'
    );

    $ecrites = 0;
    $supprimees = 0;
    foreach ($valeurs as $fraisId => $cases) {
        $fraisId = (int) $fraisId;
        if (!isset($connus[$fraisId]) || !is_array($cases)) {
            continue; // frais inconnu : rien ne vient du client sans verification
        }

        foreach ($cases as $cle => $montant) {
            $morceaux = array_pad(explode('|', (string) $cle, 2), 2, '');
            $niveau = (int) $morceaux[0];
            $statut = $morceaux[1];
            if (!in_array($niveau, $niveaux, true) || !in_array($statut, $statuts, true)) {
                continue;
            }

            $cible = [
                'f' => $fraisId, 's' => $session_id, 'm' => $mention,
                'n' => $niveau, 'st' => $statut, 'sem' => $semestre,
            ];

            $brut = is_string($montant) ? trim($montant) : $montant;
            if ($brut === '' || $brut === null) {
                $supprimer->execute($cible);
                $supprimees += $supprimer->rowCount();
                continue;
            }
            if (!is_numeric($brut) || (float) $brut < 0) {
                continue;
            }

            $ecrire->execute($cible + ['montant' => (float) $brut]);
            $ecrites++;
        }
    }

    return ['ecrites' => $ecrites, 'supprimees' => $supprimees];
}

/**
 * Frais supplementaires dus par un etudiant d'apres le bareme de sa session.
 *
 * @param  array<string, mixed> $criteres std_mention, level, std_status, semester
 * @param  array<string, mixed> $contexte diplomant, nouvel_etudiant, abonne, statut
 * @return array<int, array{frais_id:int, libelle:string, montant:float}>
 */
function fraisPersoDus(PDO $dtb, $session_id, array $criteres, array $contexte = []): array
{
    fraisPersoInstaller($dtb);
    $session_id = (int) $session_id;

    /* Le statut sert aussi de contexte : la condition « interne » n'a pas a
       etre renseignee deux fois par l'appelant. */
    if (!isset($contexte['statut']) && isset($criteres['std_status'])) {
        $contexte['statut'] = $criteres['std_status'];
    }

    /* Seules les quatre composantes presentes dans cette table : « categorie »,
       admise par baremeClauses(), n'y existe pas. */
    $criteres = array_intersect_key($criteres, array_flip(FRAIS_PERSO_CRITERES));
    [$clause, $params] = baremeClauses($criteres);

    $lire = static function (int $sid) use ($dtb, $clause, $params): array {
        $req = $dtb->prepare(
            'SELECT m.frais_id, f.libelle, m.montant, f.condition_frais, f.semestres
               FROM t_frais_perso_montant m
               JOIN t_frais_perso f ON f.id = m.frais_id
              WHERE f.actif = 1 AND m.session_id = :session_id AND ' . $clause . '
              ORDER BY f.ordre, f.libelle'
        );
        $req->execute($params + ['session_id' => $sid]);

        $parFrais = [];
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $parFrais[(int) $l['frais_id']] = [
                'frais_id'  => (int) $l['frais_id'],
                'libelle'   => (string) $l['libelle'],
                'montant'   => (float) $l['montant'],
                'condition' => (string) $l['condition_frais'],
                'semestres' => (string) $l['semestres'],
            ];
        }

        return $parFrais;
    };

    $dus = $lire(BAREME_BASE);
    if ($session_id !== BAREME_BASE) {
        foreach ($lire($session_id) as $id => $ligne) {
            $dus[$id] = $ligne; // la surcharge de session l'emporte, frais par frais
        }
    }

    /* Trois raisons de ne pas facturer :
         - montant nul : c'est ainsi qu'un cas est exempte ;
         - semestre hors de ceux ou le frais s'applique ;
         - condition non remplie (diplomant, nouvel etudiant, abonne, interne). */
    $semestre = $criteres['semester'] ?? null;

    $retenus = [];
    foreach ($dus as $l) {
        if ($l['montant'] <= 0) { continue; }
        if ($semestre !== null && !fraisPersoSemestreApplique($l['semestres'], $semestre)) { continue; }
        if (!fraisPersoConditionRemplie($l['condition'], $contexte)) { continue; }

        $retenus[] = [
            'frais_id' => $l['frais_id'],
            'libelle'  => $l['libelle'],
            'montant'  => $l['montant'],
        ];
    }

    return $retenus;
}

/**
 * Applique a un etudiant les frais supplementaires de sa session.
 *
 * Les montants sont figes, comme les colonnes cout_* de t_2024_etudiant_finace.
 * Un frais qui ne s'applique plus — changement de statut ou de niveau, frais
 * desactive, montant retire — est marque retire plutot que supprime : le
 * dossier garde la trace de ce qui avait ete facture.
 *
 * @param  array<string, mixed> $criteres std_mention, level, std_status, semester
 * @param  array<string, mixed> $contexte diplomant, nouvel_etudiant, abonne, statut
 * @return array{ecrits:int, retires:int}
 */
function fraisPersoAppliquer(PDO $dtb, string $student_id, $session_id, array $criteres, ?int $userId = null, array $contexte = []): array
{
    fraisPersoInstaller($dtb);
    $session_id = (int) $session_id;
    if (trim($student_id) === '' || $session_id <= 0) {
        return ['ecrits' => 0, 'retires' => 0];
    }

    $dus = fraisPersoDus($dtb, $session_id, $criteres, $contexte);

    $ecrire = $dtb->prepare(
        'INSERT INTO t_2024_etudiant_frais_perso
            (student_id, session_id, frais_id, libelle, montant, `remove`, date_entry, last_change_user_id)
         VALUES (:student_id, :session_id, :frais_id, :libelle, :montant, 0, :date_entry, :user_id)
         ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), montant = VALUES(montant),
                                 `remove` = 0, last_change_user_id = VALUES(last_change_user_id)'
    );

    $maintenant = date('Y-m-d H:i:s');
    $gardes = [];
    foreach ($dus as $f) {
        $ecrire->execute([
            'student_id' => $student_id,
            'session_id' => $session_id,
            'frais_id'   => $f['frais_id'],
            'libelle'    => $f['libelle'],
            'montant'    => $f['montant'],
            'date_entry' => $maintenant,
            'user_id'    => $userId,
        ]);
        $gardes[] = $f['frais_id'];
    }

    /* Les lignes hors bareme (frais_id negatif : la penalite d'inscription
       tardive, data/inscriptions.tardives.php) ne viennent pas du bareme : il
       ne les retire pas. */
    $sql = 'UPDATE t_2024_etudiant_frais_perso
               SET `remove` = 1, last_change_user_id = :user_id
             WHERE student_id = :student_id AND session_id = :session_id AND `remove` = 0 AND frais_id >= 0';
    if ($gardes !== []) {
        $sql .= ' AND frais_id NOT IN (' . implode(',', array_map('intval', $gardes)) . ')';
    }
    $retrait = $dtb->prepare($sql);
    $retrait->execute([
        'user_id' => $userId, 'student_id' => $student_id, 'session_id' => $session_id,
    ]);

    return ['ecrits' => count($gardes), 'retires' => $retrait->rowCount()];
}

/**
 * Frais supplementaires factures a un etudiant, pour affichage.
 *
 * Le libelle vient de la ligne facturee, pas de la definition : il reste juste
 * meme si le frais a ete renomme ou desactive depuis.
 *
 * @return array<int, array{libelle:string, montant:float}>
 */
function fraisPersoEtudiant(PDO $dtb, string $student_id, $session_id): array
{
    if (trim($student_id) === '' || (int) $session_id <= 0) {
        return [];
    }

    /* Tables absentes — base pas encore installee, ou compte sans droit de
       creation : la fiche doit s'imprimer quand meme, simplement sans frais
       supplementaires. */
    try {
        $req = $dtb->prepare(
            'SELECT p.libelle, p.montant
               FROM t_2024_etudiant_frais_perso p
               LEFT JOIN t_frais_perso f ON f.id = p.frais_id
              WHERE p.student_id = :s AND p.session_id = :ses AND p.`remove` != 1
              ORDER BY COALESCE(f.ordre, 0), p.libelle'
        );
        $req->execute(['s' => $student_id, 'ses' => (int) $session_id]);
    } catch (PDOException $e) {
        return [];
    }

    return array_map(static function (array $l): array {
        return ['libelle' => (string) $l['libelle'], 'montant' => (float) $l['montant']];
    }, $req->fetchAll(PDO::FETCH_ASSOC));
}

/** Total d'une liste rendue par fraisPersoEtudiant(). */
function fraisPersoTotal(array $frais): float
{
    return (float) array_sum(array_column($frais, 'montant'));
}
