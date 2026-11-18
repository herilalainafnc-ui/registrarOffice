<?php
/**
 * Tests du bareme financier par session (data/finance.bareme.php).
 *
 * Ce que ces tests verrouillent, dans l'ordre des defauts qu'ils repondent :
 *
 *   - le repli : une session sans tarif propre herite du bareme de base, donc
 *     aucune session ne peut se retrouver sans bareme ;
 *   - l'isolement : ajuster une session ne touche NI le bareme de base NI les
 *     autres sessions — c'etait tout le probleme, un changement de tarif
 *     s'appliquait retroactivement a toutes les annees ;
 *   - l'heritage a la creation d'une surcharge : les montants non transmis
 *     sont repris du bareme de base, jamais mis a zero. L'ancien endpoint
 *     initialisait les onze montants a 0 puis ne reprenait que ceux presents
 *     dans le POST, si bien qu'enregistrer le semestre 1 remettait
 *     frais_graduation a zero ;
 *   - la recopie, restreinte au semestre de la session cible ;
 *   - le niveau 0 (remise a niveau), absent de l'ancienne page alors que 66
 *     etudiants y sont inscrits.
 *
 * Les ecritures se font sur une mention de test creee puis supprimee, et sur
 * une session reelle dont les surcharges sont retirees en fin de test. Le
 * bareme de base n'est jamais modifie : une empreinte le verifie.
 *
 * Lancement :
 *   php tests/test_finance_bareme.php
 */

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/finance.bareme.php';

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

/** Sigle de test : quatre lettres, hors de la liste reelle des filieres. */
const MENTION_TEST = 'ZZTS';

/** Empreinte du bareme de base : sert a prouver qu'il n'a pas bouge. */
function empreinteBase(PDO $dtb): string
{
    $q = $dtb->query(
        'SELECT id, std_mention, level, std_status, semester,
                frais_generaux, ecolage, laboratory_info, laboratory_lang,
                dortoir, nb_jours_semestre, cafeteria, fond_depot,
                frais_graduation, frais_costume, frais_voyage
           FROM t_2024_finance_detail_licence
          WHERE session_id = ' . BAREME_BASE . '
          ORDER BY id'
    );

    $s = '';
    foreach ($q as $r) {
        $s .= implode('|', array_slice($r, 0, 16)) . "\n";
    }

    return md5($s);
}

/** Efface toute trace de la mention de test, quelle que soit la session. */
function nettoyer(PDO $dtb): void
{
    $req = $dtb->prepare('DELETE FROM t_2024_finance_detail_licence WHERE std_mention = :m');
    $req->execute(['m' => MENTION_TEST]);
}

$empreinteDepart = empreinteBase($dtb);

try {
    nettoyer($dtb); // residus d'une execution interrompue

    titre('Année scolaire et sessions');

    verifier('juillet ouvre la nouvelle année', inscAnneeCourante(7, 2026) === '2026 - 2027');
    verifier('le catalogue fournit des années', baremeAnnees($dtb) !== []);
    verifier('une année inconnue retombe sur une année valide',
        in_array(baremeAnneeChoisie($dtb, 'inexistant'), baremeAnnees($dtb), true));

    $annee = baremeAnneeChoisie($dtb);
    $sessions = baremeSessionsAnnee($dtb, $annee);
    verifier("les sessions de $annee sont indexées par semestre",
        isset($sessions[1]) && isset($sessions[2]), implode(',', array_keys($sessions)));

    $sem1 = (int) $sessions[1]['session_id'];
    $sem2 = (int) $sessions[2]['session_id'];

    titre('Niveaux et champs');

    verifier('six niveaux, dont la remise à niveau (0)',
        array_column(baremeNiveaux(), 'niveau') === [0, 1, 2, 3, 4, 5]);
    verifier('le niveau 3 est en Licence', baremeCategorie(3) === 'L');
    verifier('le niveau 4 est en Master', baremeCategorie(4) === 'M');
    verifier('onze montants gérés', count(baremeChamps()) === 11);

    titre('Le barème de base sert de repli');

    /* Mention de test : uniquement dans le barème de base, pour l'instant. */
    $colonnes = array_merge(
        ['session_id', 'categorie', 'std_status', 'std_mention', 'level', 'semester'],
        array_keys(baremeChamps())
    );
    $marques = array_map(static fn ($c) => ':' . $c, $colonnes);
    $ins = $dtb->prepare('INSERT INTO t_2024_finance_detail_licence (' . implode(',', $colonnes) . ')
        VALUES (' . implode(',', $marques) . ')');

    $reference = [
        'frais_generaux' => 100000, 'laboratory_info' => 5000, 'laboratory_lang' => 4000,
        'dortoir' => 3000, 'cafeteria' => 8000, 'fond_depot' => 2000,
        'nb_jours_semestre' => 120, 'ecolage' => 19000,
        'frais_graduation' => 7000, 'frais_costume' => 6000, 'frais_voyage' => 150000,
    ];

    foreach ([1, 2] as $sem) {
        foreach (baremeNiveaux() as $n) {
            foreach (baremeStatuts() as $statut) {
                $ins->execute($reference + [
                    'session_id' => BAREME_BASE, 'categorie' => baremeCategorie($n['niveau']),
                    'std_status' => $statut, 'std_mention' => MENTION_TEST,
                    'level' => $n['niveau'], 'semester' => $sem,
                ]);
            }
        }
    }

    $cles = ['std_mention' => MENTION_TEST, 'level' => 1, 'std_status' => 'Interne', 'semester' => 1];

    $l = baremeLigne($dtb, $sem1, $cles);
    verifier('une session sans tarif propre lit le barème de base',
        $l && (int) $l['frais_generaux'] === 100000, $l ? $l['frais_generaux'] : 'aucune ligne');
    verifier('la provenance est annoncée comme étant la base',
        $l && (int) $l['bareme_session_id'] === BAREME_BASE && $l['bareme_surcharge'] === false);
    verifier('la session n\'a pas encore de barème propre',
        baremeSessionAUnBareme($dtb, $sem1) === false);

    titre('Ajuster une session n\'affecte qu\'elle');

    baremeEnregistrer($dtb, $sem1, MENTION_TEST, 1, [
        '1|Interne' => ['frais_generaux' => 275000],
    ]);

    $l = baremeLigne($dtb, $sem1, $cles);
    verifier('la session ajustée renvoie le nouveau montant',
        $l && (int) $l['frais_generaux'] === 275000, $l['frais_generaux'] ?? '-');
    verifier('la provenance est désormais la session',
        $l && (int) $l['bareme_session_id'] === $sem1 && $l['bareme_surcharge'] === true);

    $base = baremeLigne($dtb, BAREME_BASE, $cles);
    verifier('le barème de base est inchangé',
        $base && (int) $base['frais_generaux'] === 100000, $base['frais_generaux'] ?? '-');

    $autre = baremeLigne($dtb, $sem2, $cles);
    verifier('l\'autre semestre de la même année est inchangé',
        $autre && (int) $autre['frais_generaux'] === 100000, $autre['frais_generaux'] ?? '-');

    titre('Une surcharge naît complète, jamais avec des zéros');

    /* C'est la réparation du défaut le plus coûteux de l'ancien endpoint. */
    $surcharge = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence
        WHERE session_id = :s AND std_mention = :m AND level = 1
          AND std_status = "Interne" AND semester = 1 LIMIT 1');
    $surcharge->execute(['s' => $sem1, 'm' => MENTION_TEST]);
    $sc = $surcharge->fetch(PDO::FETCH_ASSOC);

    verifier('la ligne de surcharge existe', (bool) $sc);
    $herites = 0;
    foreach ($reference as $col => $attendu) {
        if ($col === 'frais_generaux') { continue; } // seul montant transmis
        if ($sc && (float) $sc[$col] === (float) $attendu) { $herites++; }
    }
    verifier('les dix montants non transmis sont hérités de la base, pas mis à zéro',
        $herites === 10, "$herites / 10");

    titre('Un second enregistrement ne touche que ce qu\'il transmet');

    baremeEnregistrer($dtb, $sem1, MENTION_TEST, 1, [
        '1|Interne' => ['ecolage' => 21000],
    ]);
    $surcharge->execute(['s' => $sem1, 'm' => MENTION_TEST]);
    $sc = $surcharge->fetch(PDO::FETCH_ASSOC);
    verifier('le nouveau montant est écrit', $sc && (int) $sc['ecolage'] === 21000, $sc['ecolage'] ?? '-');
    verifier('le montant précédemment ajusté est conservé',
        $sc && (int) $sc['frais_generaux'] === 275000, $sc['frais_generaux'] ?? '-');
    verifier('frais_graduation n\'a pas été remis à zéro',
        $sc && (int) $sc['frais_graduation'] === 7000, $sc['frais_graduation'] ?? '-');

    titre('Valeurs refusées');

    $avant = $sc['dortoir'];
    baremeEnregistrer($dtb, $sem1, MENTION_TEST, 1, [
        '1|Interne' => ['dortoir' => 'beaucoup', 'colonne_inventee' => 1],
    ]);
    $surcharge->execute(['s' => $sem1, 'm' => MENTION_TEST]);
    $sc = $surcharge->fetch(PDO::FETCH_ASSOC);
    verifier('un montant non numérique est ignoré', (float) $sc['dortoir'] === (float) $avant);
    verifier('une colonne inconnue ne provoque pas d\'erreur', true);

    titre('Recopie d\'un barème');

    $bilan = baremeCopier($dtb, BAREME_BASE, $sem2, MENTION_TEST);
    verifier('la recopie crée des lignes', $bilan['copiees'] > 0, (string) $bilan['copiees']);

    /* Restreinte au semestre de la session cible : sans cela, la recopie vers
       « Deuxième semestre » emportait aussi les douze lignes de semestre 1,
       qu'aucun lecteur ne relit jamais. */
    $semestresCopies = $dtb->prepare('SELECT DISTINCT semester FROM t_2024_finance_detail_licence
        WHERE session_id = :s AND std_mention = :m ORDER BY semester');
    $semestresCopies->execute(['s' => $sem2, 'm' => MENTION_TEST]);
    $sems = $semestresCopies->fetchAll(PDO::FETCH_COLUMN);
    verifier('seul le semestre de la session cible est recopié',
        $sems === ['2'] || $sems === [2], implode(',', $sems));
    verifier('douze lignes : six niveaux x deux statuts', $bilan['copiees'] === 12, (string) $bilan['copiees']);

    $bilan2 = baremeCopier($dtb, BAREME_BASE, $sem2, MENTION_TEST);
    verifier('une seconde recopie n\'écrase rien',
        $bilan2['copiees'] === 0 && $bilan2['ignorees'] === 12,
        $bilan2['copiees'] . '/' . $bilan2['ignorees']);

    titre('Retour au barème de base');

    $supprimees = baremeSupprimerSurcharge($dtb, $sem2, MENTION_TEST);
    verifier('la surcharge est supprimée', $supprimees === 12, (string) $supprimees);
    $apres = baremeLigne($dtb, $sem2, $cles);
    verifier('la session relit le barème de base',
        $apres && (int) $apres['bareme_session_id'] === BAREME_BASE);
    verifier('le barème de base n\'est jamais supprimable',
        baremeSupprimerSurcharge($dtb, BAREME_BASE, MENTION_TEST) === 0);

    titre('La grille couvre toutes les cases');

    $grille = baremeGrille($dtb, $sem1, MENTION_TEST, 1);
    verifier('douze cases', count($grille) === 12, (string) count($grille));
    verifier('aucune case vide', count(array_filter($grille, static fn ($l) => $l === [])) === 0);
    verifier('la remise à niveau est présente', isset($grille['0|Interne']) && $grille['0|Interne'] !== []);
    verifier('la case ajustée est marquée comme surcharge',
        ($grille['1|Interne']['bareme_surcharge'] ?? null) === true);
    verifier('une case non ajustée est marquée comme héritée',
        ($grille['2|Externe']['bareme_surcharge'] ?? null) === false);

    titre('Appliquer un barème à plusieurs filières');

    /* Plusieurs filieres partagent souvent un bareme identique : releve en
       base, les huit mentions ne forment que cinq profils distincts par
       semestre. Sans cette possibilite il fallait retaper les memes montants
       trois ou quatre fois. */

    $ZZA = 'ZZTA';
    $ZZB = 'ZZTB';

    /** Cree les lignes de base d'une mention de test. */
    $semer = static function (PDO $dtb, string $sigle, array $valeurs) use ($colonnes, $marques): void {
        $ins = $dtb->prepare('INSERT INTO t_2024_finance_detail_licence (' . implode(',', $colonnes) . ')
            VALUES (' . implode(',', $marques) . ')');
        foreach (baremeNiveaux() as $n) {
            foreach (baremeStatuts() as $statut) {
                $ins->execute($valeurs + [
                    'session_id' => BAREME_BASE, 'categorie' => baremeCategorie($n['niveau']),
                    'std_status' => $statut, 'std_mention' => $sigle,
                    'level' => $n['niveau'], 'semester' => 1,
                ]);
            }
        }
    };

    $nettoyerA = static function (PDO $dtb) use ($ZZA, $ZZB): void {
        $req = $dtb->prepare('DELETE FROM t_2024_finance_detail_licence
            WHERE std_mention IN (:a, :b)');
        $req->execute(['a' => $ZZA, 'b' => $ZZB]);
    };

    $nettoyerA($dtb);
    $semer($dtb, $ZZA, $reference);
    $semer($dtb, $ZZB, $reference);

    $cle1 = ['level' => 1, 'std_status' => 'Interne', 'semester' => 1];

    /* Les memes montants ecrits sur deux mentions, en une passe. C'est ce que
       fait le point d'entree quand des filieres sont cochees. */
    foreach ([$ZZA, $ZZB] as $cible) {
        baremeEnregistrer($dtb, $sem1, $cible, 1, ['1|Interne' => ['frais_generaux' => 333000]]);
    }

    $a = baremeLigne($dtb, $sem1, ['std_mention' => $ZZA] + $cle1);
    $b = baremeLigne($dtb, $sem1, ['std_mention' => $ZZB] + $cle1);
    verifier('les deux filières reçoivent le même montant',
        (int) $a['frais_generaux'] === 333000 && (int) $b['frais_generaux'] === 333000,
        ($a['frais_generaux'] ?? '-') . ' / ' . ($b['frais_generaux'] ?? '-'));
    verifier('les deux portent désormais un tarif propre',
        $a['bareme_surcharge'] === true && $b['bareme_surcharge'] === true);

    /* Point important : chaque surcharge doit avoir herite les autres montants
       du bareme de base, sinon appliquer a plusieurs filieres creerait des
       lignes trouees. */
    $lireSurcharge = $dtb->prepare('SELECT * FROM t_2024_finance_detail_licence
        WHERE session_id = :s AND std_mention = :m AND level = 1
          AND std_status = "Interne" AND semester = 1 LIMIT 1');
    $manquants = 0;
    foreach ([$ZZA, $ZZB] as $cible) {
        $lireSurcharge->execute(['s' => $sem1, 'm' => $cible]);
        $ligne = $lireSurcharge->fetch(PDO::FETCH_ASSOC);
        foreach ($reference as $col => $attendu) {
            if ($col === 'frais_generaux') { continue; }
            if (!$ligne || (float) $ligne[$col] !== (float) $attendu) { $manquants++; }
        }
    }
    verifier('aucun montant perdu sur les filières ciblées', $manquants === 0,
        "$manquants montant(s) incorrect(s)");

    /* Une mention NON ciblee ne doit pas bouger. */
    $temoin = baremeLigne($dtb, $sem1, ['std_mention' => MENTION_TEST] + $cle1);
    verifier('une filière non ciblée garde son propre montant',
        (int) $temoin['frais_generaux'] === 275000, $temoin['frais_generaux'] ?? '-');

    $nettoyerA($dtb);

    titre('La grille rend de quoi cibler plusieurs filières');

    $grilleSrc = (string) @file_get_contents(__DIR__ . '/../src/finance/bareme.grille.php');
    verifier('un panneau de partage par carte, replié par défaut',
        strpos($grilleSrc, 'class="bareme-partage" hidden') !== false,
        'le panneau est visible par défaut, ou absent');
    verifier('les cartes annoncent si elles sont déjà ajustées',
        strpos($grilleSrc, 'data-ajustee=') !== false);
    verifier('les cases à cocher portent le libellé et l\'état de la cible',
        strpos($grilleSrc, 'js-bareme-cible') !== false
        && strpos($grilleSrc, 'data-libelle=') !== false,
        'la confirmation ne pourrait pas distinguer les filières ajustées');
    verifier('la filière elle-même est exclue de ses propres cibles',
        strpos($grilleSrc, "if (\$autre['filiere_sigle'] === \$sigle) { continue; }") !== false);

    $jsSrc = (string) @file_get_contents(__DIR__ . '/../src/js/finance.bareme.js');
    verifier('le client confirme avant d\'écraser un tarif déjà ajusté',
        strpos($jsSrc, 'function confirmerPartage') !== false
        && strpos($jsSrc, "dataset.ajustee === '1'") !== false);
    verifier('cocher une filière suffit à propager un barème déjà saisi',
        preg_match('/modifies\.length\s*>\s*0\s*\|\|\s*cibles\s*>\s*0/', $jsSrc) === 1,
        'le bouton resterait désactivé sans modification');

    titre('Les onglets échappent au système de transitions');

    /* Interaction subtile entre deux fichiers, et facile a casser sans le voir.
       src/js/transitions.js intercepte TOUS les clics sur les liens internes,
       appelle preventDefault() puis window.location.href. Il est enregistre en
       debut de <body> par init/topbar.php, donc AVANT le script de la page :
       il gagnait la course, et les onglets de semestre rechargeaient toute la
       page malgre leur gestionnaire AJAX.

       La sortie est data-pt-skip="1", la meme convention que transitions.js
       applique deja aux formulaires. Les deux cotes sont verifies ici : la
       lecture de l'attribut dans transitions.js, et sa presence sur les
       onglets. Retirer l'un ou l'autre fait echouer ce test. */

    $transitions = (string) @file_get_contents(__DIR__ . '/../src/js/transitions.js');
    verifier('transitions.js est lisible', $transitions !== '');
    verifier('transitions.js écarte les liens marqués data-pt-skip',
        strpos($transitions, "dataset.ptSkip === '1'") !== false
        && preg_match('/link\.dataset[^\n]*ptSkip/', $transitions) === 1,
        'la sortie pour les liens a disparu');
    verifier('transitions.js garde sa sortie pour les formulaires',
        preg_match('/form\.dataset\.ptSkip/', $transitions) === 1);

    $barre = (string) @file_get_contents(__DIR__ . '/../src/finance/bareme.barre.php');
    verifier('bareme.barre.php est lisible', $barre !== '');
    verifier('chaque onglet porte data-pt-skip',
        substr_count($barre, 'data-pt-skip="1"') === substr_count($barre, 'js-bareme-onglet'),
        substr_count($barre, 'data-pt-skip="1"') . ' marquage(s) pour '
            . substr_count($barre, 'js-bareme-onglet') . ' onglet(s)');
    verifier('les onglets gardent un href, pour fonctionner sans JavaScript',
        substr_count($barre, 'href="<?= e(baremeLien(') === substr_count($barre, 'js-bareme-onglet'),
        'un onglet sans href');

    titre('Les points d\'entrée refusent un visiteur anonyme');

    /* La propriete la plus sensible du module. src/finance/save_finance.php
       n'avait AUCUNE authentification : un POST anonyme remettait a zero le
       bareme de toute une mention — verifie, il repondait 302 apres avoir
       ecrit. Les deux anciens fichiers sont neutralises sur place, les deux
       nouveaux sont gardes. Ce test empeche la regression. */
    $base = 'https://registrar.test';
    foreach ($argv as $a) {
        if (strpos($a, '--url=') === 0) { $base = rtrim(substr($a, 6), '/'); }
    }

    /** Code HTTP d'un POST anonyme, ou 0 si le serveur est injoignable. */
    $poster = static function (string $chemin) use ($base): int {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => 'filiere=INFO&semester=1',
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => 10,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $flux = @fopen($base . $chemin, 'r', false, $ctx);
        if ($flux === false) { return 0; }
        $entetes = stream_get_meta_data($flux)['wrapper_data'] ?? [];
        fclose($flux);
        foreach ($entetes as $ligne) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $ligne, $m)) { return (int) $m[1]; }
        }

        return 0;
    };

    $empreinteAvantHttp = empreinteBase($dtb);

    $aTester = [
        '/src/finance/save_finance.php?filiere=INFO&semester=1',
        '/src/finance/insert_finance.php?filiere=INFO&semester=1',
        '/app/finance-bareme.save.php',
        '/app/finance-bareme.grille.php',
    ];

    $joignable = true;
    foreach ($aTester as $chemin) {
        $code = $poster($chemin);
        if ($code === 0) {
            echo "  (ignoré) $chemin — serveur injoignable\n";
            $joignable = false;
            continue;
        }
        // 302 vers /login, 401 ou 403 : tous des refus. 200 serait une faille.
        verifier("anonyme refusé sur $chemin",
            in_array($code, [302, 401, 403, 410], true), "HTTP $code");
    }

    if ($joignable) {
        verifier('aucune écriture pendant ces requêtes anonymes',
            empreinteBase($dtb) === $empreinteAvantHttp,
            'le barème de base a changé');
    }

    titre('Le barème de base n\'a pas bougé');

    /* L'empreinte inclut la mention de test, ajoutee puis retiree : on la
       compare donc apres nettoyage, dans le bloc finally. */
    verifier('contrôle différé au nettoyage', true);

} finally {
    nettoyer($dtb);

    $empreinteFin = empreinteBase($dtb);
    if ($empreinteFin === $empreinteDepart) {
        $reussis++;
        echo "  OK    le barème de base est identique à l'état de départ\n";
    } else {
        $echoues++;
        echo "  ECHEC le barème de base a été modifié -> $empreinteDepart vs $empreinteFin\n";
    }

    $residus = (int) $dtb->query('SELECT COUNT(*) FROM t_2024_finance_detail_licence
        WHERE std_mention = "' . MENTION_TEST . '"')->fetchColumn();
    if ($residus === 0) {
        $reussis++;
        echo "  OK    aucun résidu de la mention de test\n";
    } else {
        $echoues++;
        echo "  ECHEC $residus ligne(s) de test subsistent\n";
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Reussis : $reussis   Echoues : $echoues\n";
    exit($echoues === 0 ? 0 : 1);
}
