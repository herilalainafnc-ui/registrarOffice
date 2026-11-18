<?php
/**
 * Bareme financier par session.
 *
 * LE MODELE
 * ---------
 * t_2024_finance_detail_licence portait deja une colonne session_id, mais
 * personne ne s'en servait : les 192 lignes valaient toutes 0, la page
 * /finance ne la lisait pas, et les neuf lecteurs du bareme l'ignoraient. Une
 * modification de tarif ecrasait donc l'historique et s'appliquait
 * retroactivement a toutes les annees.
 *
 * La colonne devient signifiante, avec deux etages :
 *
 *   session_id = 0    BAREME DE BASE. Les 192 lignes existantes. Sert de repli
 *                     a toute session qui n'a pas de tarif propre.
 *   session_id = 490  SURCHARGE de la session « Premier semestre 2026-2027 ».
 *                     N'existe que si le registrariat a ajuste cette session.
 *
 * Une lecture cherche d'abord la session demandee, puis retombe sur la base.
 * Consequence voulue : aucune session ne peut se retrouver sans bareme, ni les
 * anciennes ni celles qu'on vient de creer.
 *
 * LA CLE
 * ------
 * Une ligne de bareme est identifiee par
 *     (session_id, std_mention, level, std_status, semester)
 * categorie (L / M) se deduit du niveau, elle n'entre pas dans la cle.
 *
 * Les appelants ne connaissent pas tous les cinq composantes : addCours ne
 * connait pas le statut de l'etudiant, addStd ne connait pas le semestre. Les
 * fonctions acceptent donc des criteres partiels, et ne filtrent que sur ce
 * qui est fourni — c'est deja ce que faisaient les requetes d'origine.
 */

require_once __DIR__ . '/inscription.session.php'; // inscAnneeCourante()

/** Identifiant conventionnel du bareme de base. */
const BAREME_BASE = 0;

/**
 * Colonnes de montants, dans l'ordre d'affichage de la page.
 *
 * @return array<string, string> colonne SQL => libelle
 */
function baremeChamps(): array
{
    return [
        'frais_generaux'    => 'Frais généraux/semestre',
        'laboratory_info'   => 'Labo INFO/semestre',
        'laboratory_lang'   => 'Labo LANGUE/semestre',
        'dortoir'           => 'Dortoir/jour',
        'cafeteria'         => 'Cantine/jour',
        'fond_depot'        => 'Fond de dépôt',
        'nb_jours_semestre' => 'Nombre du jour - interne',
        'ecolage'           => 'Ecolage <em>(Multiplier par nombre de crédit)</em>',
        'frais_graduation'  => 'Frais de graduation',
        'frais_costume'     => 'Frais de Costume',
        'frais_voyage'      => "Voyage d'étude",
    ];
}

/**
 * Niveaux geres, du plus bas au plus haut.
 *
 * Le niveau 0 (remise a niveau) existait en base — 32 lignes — mais les
 * boucles de la page allaient de 1 a 5 : il n'etait ni affiche, ni modifiable,
 * ni mis a jour a l'enregistrement, alors que 66 etudiants y sont inscrits.
 *
 * @return array<int, array{niveau:int, libelle:string, categorie:string}>
 */
function baremeNiveaux(): array
{
    return [
        ['niveau' => 0, 'libelle' => 'Remise à niveau', 'categorie' => 'L'],
        ['niveau' => 1, 'libelle' => 'Licence 1',       'categorie' => 'L'],
        ['niveau' => 2, 'libelle' => 'Licence 2',       'categorie' => 'L'],
        ['niveau' => 3, 'libelle' => 'Licence 3',       'categorie' => 'L'],
        ['niveau' => 4, 'libelle' => 'Master 1',        'categorie' => 'M'],
        ['niveau' => 5, 'libelle' => 'Master 2',        'categorie' => 'M'],
    ];
}

/** Categorie attendue pour un niveau : L jusqu'a 3, M au-dela. */
function baremeCategorie(int $niveau): string
{
    return $niveau < 4 ? 'L' : 'M';
}

/**
 * Frais de graduation reellement du, a partir du montant du bareme.
 *
 * DEUX conditions, jusqu'ici dispersees et contradictoires :
 *   - deuxieme semestre seulement : c'est la regle du registrariat, et c'est
 *     aussi la seule ou la page rende la ligne (src/finance/bareme.grille.php) ;
 *   - etudiant diplomant (colonne graduated de tbl_2024_etudiant).
 *
 * Releve au 16/09/2026 : updateStd.php n'appliquait NI l'une ni l'autre, et
 * l'etat financier imprimable (src/extenssionPrint/finance.student.php) les
 * ignorait dans son total. 339 dossiers portaient donc un frais de graduation
 * indu — 51 050 000 Ar — que la fiche d'inscription n'affichait pas mais que
 * l'etat financier facturait. Un seul endroit decide desormais, et les deux
 * documents s'accordent, y compris sur les dossiers deja enregistres.
 *
 * @param mixed $montant   montant du bareme, ou montant fige sur le dossier
 * @param mixed $semestre  numero de semestre de la session
 * @param mixed $diplomant valeur de graduated
 */
function baremeFraisGraduation($montant, $semestre, $diplomant): float
{
    if ((int) $semestre !== 2 || (int) $diplomant !== 1) {
        return 0.0;
    }

    return (float) $montant;
}
/** Statuts de residence factures. */
function baremeStatuts(): array
{
    return ['Interne', 'Externe'];
}

/**
 * Annees scolaires du catalogue, la plus recente d'abord.
 *
 * @return array<int, string>
 */
function baremeAnnees(PDO $dtb): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = $dtb->query(
        'SELECT DISTINCT session_year FROM t_2023_session
          WHERE session_year <> "" ORDER BY session_year DESC'
    )->fetchAll(PDO::FETCH_COLUMN);

    return $cache;
}

/**
 * Annee a afficher : celle demandee si elle existe, sinon l'annee en cours,
 * sinon la plus recente du catalogue.
 */
function baremeAnneeChoisie(PDO $dtb, ?string $demandee = null): string
{
    $annees = baremeAnnees($dtb);
    if ($annees === []) {
        return inscAnneeCourante();
    }

    $demandee = $demandee !== null ? trim($demandee) : '';
    if ($demandee !== '' && in_array($demandee, $annees, true)) {
        return $demandee;
    }

    $courante = inscAnneeCourante();

    return in_array($courante, $annees, true) ? $courante : $annees[0];
}

/**
 * Sessions d'une annee scolaire, indexees par numero de semestre.
 *
 * @return array<int, array<string, mixed>>
 */
function baremeSessionsAnnee(PDO $dtb, string $annee): array
{
    $req = $dtb->prepare(
        'SELECT session_id, session_name, session_year, session_semester
           FROM t_2023_session
          WHERE session_year = :annee
          ORDER BY session_semester'
    );
    $req->execute(['annee' => $annee]);

    $parSemestre = [];
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $parSemestre[(int) $s['session_semester']] = $s;
    }

    return $parSemestre;
}

/**
 * Session correspondant a un semestre de l'annee en cours.
 *
 * Utilisee par les appelants qui n'ont pas de session sous la main — la
 * creation d'un cours, par exemple, qui calcule un cout a partir de l'ecolage.
 * Ils prenaient auparavant n'importe quelle ligne du bareme.
 */
function baremeSessionCourante(PDO $dtb, int $semestre): int
{
    $sessions = baremeSessionsAnnee($dtb, inscAnneeCourante());

    return isset($sessions[$semestre]) ? (int) $sessions[$semestre]['session_id'] : BAREME_BASE;
}

/**
 * Cette session porte-t-elle un bareme propre ?
 *
 * Sert a distinguer, dans la page, « tarif ajuste pour cette session » de
 * « utilise le bareme de base ».
 */
function baremeSessionAUnBareme(PDO $dtb, int $session_id): bool
{
    if ($session_id === BAREME_BASE) {
        return true;
    }

    $req = $dtb->prepare(
        'SELECT 1 FROM t_2024_finance_detail_licence
          WHERE session_id = :s LIMIT 1'
    );
    $req->execute(['s' => $session_id]);

    return (bool) $req->fetchColumn();
}

/**
 * Mentions couvertes par une session : celles qui y ont un bareme propre.
 *
 * @return array<int, string>
 */
function baremeMentionsSession(PDO $dtb, int $session_id): array
{
    $req = $dtb->prepare(
        'SELECT DISTINCT std_mention FROM t_2024_finance_detail_licence
          WHERE session_id = :s ORDER BY std_mention'
    );
    $req->execute(['s' => $session_id]);

    return $req->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Construit la clause WHERE a partir des seuls criteres fournis.
 *
 * Liste blanche de colonnes : aucun nom de colonne ne vient de l'appelant sans
 * etre verifie ici.
 *
 * @param  array<string, mixed> $criteres
 * @return array{0:string, 1:array<string, mixed>}
 */
function baremeClauses(array $criteres): array
{
    $autorisees = ['std_mention', 'level', 'std_status', 'semester', 'categorie'];

    $sql = [];
    $params = [];
    foreach ($autorisees as $col) {
        if (array_key_exists($col, $criteres) && $criteres[$col] !== null && $criteres[$col] !== '') {
            $sql[] = "$col = :$col";
            $params[$col] = $criteres[$col];
        }
    }

    return [$sql === [] ? '1' : implode(' AND ', $sql), $params];
}

/**
 * Une ligne de bareme, avec repli sur le bareme de base.
 *
 * Cherche d'abord dans la session demandee ; si elle n'y a pas de tarif pour
 * ces criteres, retombe sur session_id = 0. La ligne renvoyee porte deux
 * indications supplementaires :
 *   bareme_session_id  la session d'ou elle vient reellement
 *   bareme_surcharge   true si elle vient d'une surcharge, false si de la base
 *
 * @param  array<string, mixed> $criteres std_mention, level, std_status, semester
 * @return array<string, mixed>|false
 */
function baremeLigne(PDO $dtb, $session_id, array $criteres)
{
    [$clause, $params] = baremeClauses($criteres);
    $session_id = (int) $session_id;

    // 1. Surcharge de la session.
    if ($session_id !== BAREME_BASE) {
        $req = $dtb->prepare(
            "SELECT * FROM t_2024_finance_detail_licence
              WHERE session_id = :session_id AND $clause LIMIT 1"
        );
        $req->execute($params + ['session_id' => $session_id]);
        $ligne = $req->fetch(PDO::FETCH_ASSOC);
        if ($ligne) {
            $ligne['bareme_session_id'] = $session_id;
            $ligne['bareme_surcharge'] = true;

            return $ligne;
        }
    }

    // 2. Bareme de base.
    $req = $dtb->prepare(
        "SELECT * FROM t_2024_finance_detail_licence
          WHERE session_id = :base AND $clause LIMIT 1"
    );
    $req->execute($params + ['base' => BAREME_BASE]);
    $ligne = $req->fetch(PDO::FETCH_ASSOC);
    if ($ligne) {
        $ligne['bareme_session_id'] = BAREME_BASE;
        $ligne['bareme_surcharge'] = false;
    }

    return $ligne;
}

/**
 * Ecolage par credit reellement facture une annee scolaire passee, pour un
 * cycle (Licence : niveaux 0 a 3, Master : 4 et 5). Null sans historique.
 *
 * Regle du registrariat (06/10/2026) : un cours ajoute a une annee passee
 * est facture au tarif de CETTE annee. Or seule 2026-2027 a un bareme de
 * session ; pour les annees d'avant, baremeLigne() retombe sur le bareme de
 * base, c'est-a-dire le tarif d'aujourd'hui. Le Master y vaut 25 000 Ar par
 * credit, alors qu'il etait facture 19 000 Ar jusqu'en 2024-2025.
 *
 * Le tarif de l'annee se lit donc dans ce qui a ete facture alors
 * (t_2024_cours_finance) : la valeur la plus frequente par credit, hors
 * cours a 0 (dispenses, validations).
 */
function baremeEcolageHistorique(PDO $dtb, string $anneeScolaire, int $niveauCours): ?float
{
    static $cache = [];
    $master = $niveauCours >= 4;
    $cle = $anneeScolaire . '|' . ($master ? 'M' : 'L');
    if (array_key_exists($cle, $cache)) {
        return $cache[$cle];
    }
    $req = $dtb->prepare(
        'SELECT ROUND(cf.cours_cout / cf.cours_credit) AS par_credit, COUNT(*) AS n
           FROM t_2024_cours_finance cf
           JOIN t_2023_session s ON s.session_id = cf.session_id
           JOIN t_2023_cours c ON c.id = cf.cours_id
          WHERE s.session_year = :annee
            AND cf.cours_credit > 0 AND cf.cours_cout > 0
            AND c.yearlevel BETWEEN :min AND :max
          GROUP BY par_credit
          ORDER BY n DESC
          LIMIT 1'
    );
    $req->execute(['annee' => $anneeScolaire, 'min' => $master ? 4 : 0, 'max' => $master ? 5 : 3]);
    $tarif = $req->fetchColumn();

    return $cache[$cle] = ($tarif !== false && (float) $tarif > 0) ? (float) $tarif : null;
}

/**
 * Grille complete d'une mention et d'un semestre, pour l'affichage.
 *
 * @return array<string, array<string, mixed>> cle « niveau|statut » => ligne
 */
function baremeGrille(PDO $dtb, $session_id, string $mention, int $semestre): array
{
    /* Deux requetes, pas vingt-quatre.
       La version par cellule appelait baremeLigne() pour chacune des douze
       cases, soit jusqu'a 24 requetes par filiere et pres de 200 par
       affichage de la page. On lit ici la surcharge et la base d'un bloc, puis
       on applique le repli en memoire. */
    $session_id = (int) $session_id;

    $lire = static function (PDO $dtb, int $sid) use ($mention, $semestre): array {
        $req = $dtb->prepare(
            'SELECT * FROM t_2024_finance_detail_licence
              WHERE session_id = :s AND std_mention = :m AND semester = :sem'
        );
        $req->execute(['s' => $sid, 'm' => $mention, 'sem' => $semestre]);

        $parCle = [];
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
            // En cas de doublon, la derniere lue l'emporte : l'ordre importe
            // peu ici, la cle etant censee etre unique.
            $parCle[$l['level'] . '|' . $l['std_status']] = $l;
        }

        return $parCle;
    };

    $base = $lire($dtb, BAREME_BASE);
    $surcharge = $session_id !== BAREME_BASE ? $lire($dtb, $session_id) : [];

    $grille = [];
    foreach (baremeNiveaux() as $n) {
        foreach (baremeStatuts() as $statut) {
            $cle = $n['niveau'] . '|' . $statut;

            if (isset($surcharge[$cle])) {
                $ligne = $surcharge[$cle];
                $ligne['bareme_session_id'] = $session_id;
                $ligne['bareme_surcharge'] = true;
            } elseif (isset($base[$cle])) {
                $ligne = $base[$cle];
                $ligne['bareme_session_id'] = BAREME_BASE;
                $ligne['bareme_surcharge'] = false;
            } else {
                $ligne = [];
            }

            $grille[$cle] = $ligne;
        }
    }

    return $grille;
}

/**
 * Enregistre les montants d'une mention / semestre / session.
 *
 * Deux differences importantes avec l'ancien save_finance.php :
 *
 *   1. SEULS les champs transmis sont ecrits. L'ancienne version initialisait
 *      les onze montants a 0 puis n'ecrasait que ceux presents dans le POST :
 *      comme la page ne rend pas d'input pour frais_graduation au semestre 1,
 *      ni pour frais_costume hors Theologie, chaque enregistrement remettait
 *      ces montants a zero. 12 lignes en portaient encore un au moment du
 *      correctif.
 *   2. L'ecriture cible la session demandee. Si la session n'a pas encore de
 *      ligne pour cette combinaison, elle est CREEE en partant du bareme de
 *      base — la surcharge nait donc complete, jamais avec des trous.
 *
 * @param  array<string, array<string, mixed>> $valeurs cle « niveau|statut » => [colonne => montant]
 * @return array{ecrites:int, creees:int}
 */
function baremeEnregistrer(PDO $dtb, int $session_id, string $mention, int $semestre, array $valeurs): array
{
    $champs = array_keys(baremeChamps());
    $ecrites = 0;
    $creees = 0;

    $chercher = $dtb->prepare(
        'SELECT id FROM t_2024_finance_detail_licence
          WHERE session_id = :s AND std_mention = :m AND level = :l
            AND std_status = :st AND semester = :sem LIMIT 1'
    );

    foreach (baremeNiveaux() as $n) {
        foreach (baremeStatuts() as $statut) {
            $cle = $n['niveau'] . '|' . $statut;
            if (!isset($valeurs[$cle]) || !is_array($valeurs[$cle]) || $valeurs[$cle] === []) {
                continue; // rien de transmis pour cette case : on n'y touche pas
            }

            // Ne retenir que des colonnes connues et des nombres.
            $montants = [];
            foreach ($valeurs[$cle] as $col => $valeur) {
                if (in_array($col, $champs, true) && is_numeric($valeur)) {
                    $montants[$col] = (float) $valeur;
                }
            }
            if ($montants === []) {
                continue;
            }

            $chercher->execute([
                's' => $session_id, 'm' => $mention, 'l' => $n['niveau'],
                'st' => $statut, 'sem' => $semestre,
            ]);
            $id = $chercher->fetchColumn();

            if ($id === false) {
                // Creation de la surcharge : on part du bareme de base pour que
                // les montants non transmis soient herites, et non a zero.
                $base = baremeLigne($dtb, BAREME_BASE, [
                    'std_mention' => $mention,
                    'level'       => $n['niveau'],
                    'std_status'  => $statut,
                    'semester'    => $semestre,
                ]) ?: [];

                $depart = [];
                foreach ($champs as $col) {
                    $depart[$col] = isset($base[$col]) ? (float) $base[$col] : 0.0;
                }
                $depart = array_merge($depart, $montants);

                $colonnes = array_merge(
                    ['session_id', 'categorie', 'std_status', 'std_mention', 'level', 'semester'],
                    $champs
                );
                $marques = array_map(static fn ($c) => ':' . $c, $colonnes);

                $inserer = $dtb->prepare(
                    'INSERT INTO t_2024_finance_detail_licence (' . implode(', ', $colonnes) . ')
                     VALUES (' . implode(', ', $marques) . ')'
                );
                $inserer->execute($depart + [
                    'session_id'  => $session_id,
                    'categorie'   => baremeCategorie($n['niveau']),
                    'std_status'  => $statut,
                    'std_mention' => $mention,
                    'level'       => $n['niveau'],
                    'semester'    => $semestre,
                ]);
                $creees++;
                $ecrites++;
                continue;
            }

            $sets = [];
            foreach (array_keys($montants) as $col) {
                $sets[] = "$col = :$col";
            }
            $maj = $dtb->prepare(
                'UPDATE t_2024_finance_detail_licence
                    SET ' . implode(', ', $sets) . ', categorie = :categorie
                  WHERE id = :id'
            );
            $maj->execute($montants + [
                'categorie' => baremeCategorie($n['niveau']),
                'id'        => $id,
            ]);
            $ecrites++;
        }
    }

    return ['ecrites' => $ecrites, 'creees' => $creees];
}

/**
 * Recopie le bareme d'une session vers une autre, pour ajustement.
 *
 * C'est le geste attendu a chaque nouvelle session : on repart des tarifs
 * precedents plutot que d'une grille vide. La source peut etre le bareme de
 * base (session 0).
 *
 * Les lignes deja presentes dans la cible ne sont PAS ecrasees : une recopie
 * ne doit pas effacer un ajustement deja saisi.
 *
 * @return array{copiees:int, ignorees:int}
 */
function baremeCopier(PDO $dtb, int $sessionSource, int $sessionCible, ?string $mention = null): array
{
    if ($sessionCible === BAREME_BASE || $sessionSource === $sessionCible) {
        return ['copiees' => 0, 'ignorees' => 0];
    }

    $champs = array_keys(baremeChamps());
    $criteres = $mention !== null ? ['std_mention' => $mention] : [];

    /* Restriction au semestre de la session cible.
       Sans elle, une recopie vers « Deuxieme semestre » emportait aussi les
       lignes de semestre 1 : douze lignes par mention qu'aucun lecteur ne
       relisait jamais, puisque la cle de lecture inclut le semestre. Elles
       encombraient la table et faisaient croire a un ajustement complet. */
    $semCible = $dtb->prepare('SELECT session_semester FROM t_2023_session WHERE session_id = :s LIMIT 1');
    $semCible->execute(['s' => $sessionCible]);
    $semestre = $semCible->fetchColumn();
    if ($semestre !== false && $semestre !== null) {
        $criteres['semester'] = (int) $semestre;
    }

    [$clause, $params] = baremeClauses($criteres);

    $lire = $dtb->prepare(
        "SELECT * FROM t_2024_finance_detail_licence
          WHERE session_id = :s AND $clause"
    );
    $lire->execute($params + ['s' => $sessionSource]);
    $sources = $lire->fetchAll(PDO::FETCH_ASSOC);

    $existe = $dtb->prepare(
        'SELECT 1 FROM t_2024_finance_detail_licence
          WHERE session_id = :s AND std_mention = :m AND level = :l
            AND std_status = :st AND semester = :sem LIMIT 1'
    );

    $colonnes = array_merge(
        ['session_id', 'categorie', 'std_status', 'std_mention', 'level', 'semester'],
        $champs
    );
    $marques = array_map(static fn ($c) => ':' . $c, $colonnes);
    $inserer = $dtb->prepare(
        'INSERT INTO t_2024_finance_detail_licence (' . implode(', ', $colonnes) . ')
         VALUES (' . implode(', ', $marques) . ')'
    );

    $copiees = 0;
    $ignorees = 0;
    foreach ($sources as $src) {
        $existe->execute([
            's' => $sessionCible, 'm' => $src['std_mention'], 'l' => $src['level'],
            'st' => $src['std_status'], 'sem' => $src['semester'],
        ]);
        if ($existe->fetchColumn()) {
            $ignorees++;
            continue;
        }

        $valeurs = [];
        foreach ($champs as $col) {
            $valeurs[$col] = (float) ($src[$col] ?? 0);
        }
        $inserer->execute($valeurs + [
            'session_id'  => $sessionCible,
            'categorie'   => baremeCategorie((int) $src['level']),
            'std_status'  => $src['std_status'],
            'std_mention' => $src['std_mention'],
            'level'       => $src['level'],
            'semester'    => $src['semester'],
        ]);
        $copiees++;
    }

    return ['copiees' => $copiees, 'ignorees' => $ignorees];
}

/**
 * Supprime la surcharge d'une session : elle revient au bareme de base.
 * Le bareme de base lui-meme n'est jamais supprimable.
 */
function baremeSupprimerSurcharge(PDO $dtb, int $session_id, ?string $mention = null): int
{
    if ($session_id === BAREME_BASE) {
        return 0;
    }

    $criteres = $mention !== null ? ['std_mention' => $mention] : [];
    [$clause, $params] = baremeClauses($criteres);

    $req = $dtb->prepare(
        "DELETE FROM t_2024_finance_detail_licence
          WHERE session_id = :s AND $clause"
    );
    $req->execute($params + ['s' => $session_id]);

    return $req->rowCount();
}
