<?php
/**
 * Tests des mentions (data/mentions.php) et de leur page de reglage.
 *
 * CE QUE CES TESTS PROTEGENT
 * --------------------------
 * La table filiere n'a aucune contrainte de cle etrangere, mais son sigle sert
 * de cle dans cinq tables et son libelle rattache les fiches etudiantes. Plus
 * de quatre mille lignes en dependent. Deux proprietes comptent donc :
 *
 *   1. le sigle est immuable des qu'il est utilise, et une mention utilisee ne
 *      peut pas etre supprimee — sinon ces lignes pointeraient vers une
 *      mention inexistante ;
 *   2. renommer un libelle propage la nouvelle valeur aux fiches etudiantes.
 *      Plusieurs requetes comparent « filiere_description = etude_envisage »
 *      (generate.student.php, updateFinance.php) : sans propagation, la
 *      recherche du bareme echoue et l'inscription annonce « Configuration
 *      financiere introuvable ».
 *
 * Les fixtures sont une mention et un etudiant jetables, supprimes en fin de
 * test. Une empreinte verifie que les neuf mentions reelles n'ont pas bouge.
 *
 * Lancement :
 *   php tests/test_mentions.php
 *   php tests/test_mentions.php --url=https://registrar.test
 */

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/mentions.php';

$base = 'https://registrar.test';
foreach ($argv as $a) {
    if (strpos($a, '--url=') === 0) { $base = rtrim(substr($a, 6), '/'); }
}

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

/* Fixtures, hors des plages reelles. */
const SIGLE_TEST = 'ZZMN';
const ETU_TEST   = 'ZZMN0001';
const LIB_AVANT  = 'Mention de test ZZMN';
const LIB_APRES  = 'Mention de test ZZMN renommee';

/** Empreinte des mentions reelles : prouve qu'aucune n'a ete touchee. */
function empreinteMentions(PDO $dtb): string
{
    $q = $dtb->query(
        'SELECT filiere_sigle, filiere_description, filiere_description_ang,
                domaine_signe, domaine_name, ouverte
           FROM filiere
          WHERE filiere_sigle NOT LIKE "ZZ%"
          ORDER BY filiere_sigle'
    );
    $s = '';
    /* Six colonnes, ouverte comprise : une mention laissée fermée par erreur
       doit faire échouer l'empreinte, pas passer inaperçue. */
    foreach ($q as $r) { $s .= implode('|', array_slice($r, 0, 6)) . "\n"; }

    return md5($s);
}

function nettoyer(PDO $dtb): void
{
    $dtb->prepare('DELETE FROM filiere WHERE filiere_sigle LIKE "ZZ%"')->execute();
    $dtb->prepare('DELETE FROM tbl_2024_etudiant WHERE student_id = ?')->execute([ETU_TEST]);
    $dtb->prepare("DELETE FROM t_2023_cours WHERE dep_desc = ? OR parcours LIKE 'ZZ%'")->execute([SIGLE_TEST]);
    $dtb->prepare("DELETE FROM filiere_parcours WHERE shortcode LIKE 'ZZ%'")->execute();
    $dtb->prepare("DELETE FROM t_2024_inscription_session WHERE student_id LIKE 'ZZMN%'")->execute();
}

$empreinteDepart = empreinteMentions($dtb);

try {
    nettoyer($dtb); // residus d'une execution interrompue

    titre('Validation du sigle');

    verifier('un sigle vide est refusé', mentionsValiderSigle('') !== null);
    verifier('une seule lettre est refusée', mentionsValiderSigle('X') !== null);
    verifier('plus de quatre lettres est refusé', mentionsValiderSigle('TROPLONG') !== null);
    verifier('un chiffre est refusé', mentionsValiderSigle('AG1') !== null);
    verifier('les minuscules sont refusées', mentionsValiderSigle('agro') !== null);
    verifier('un accent est refusé', mentionsValiderSigle('AGRÔ') !== null);
    verifier('deux à quatre majuscules sont acceptées',
        mentionsValiderSigle('AG') === null && mentionsValiderSigle('AGRO') === null);

    titre('Création');

    $r = mentionsCreer($dtb, SIGLE_TEST, [
        'filiere_description' => LIB_AVANT,
        'filiere_description_ang' => 'ZZMN test',
        'domaine_signe' => 'ZZD',
        'domaine_name' => 'Domaine de test',
    ], 1);
    verifier('la mention est créée', $r['ok'] === true, $r['message']);

    $m = mentionParSigle($dtb, SIGLE_TEST);
    verifier('elle est relue depuis la base', $m !== false);
    verifier('les libellés sont enregistrés',
        $m && $m['filiere_description'] === LIB_AVANT && $m['domaine_signe'] === 'ZZD');

    $r = mentionsCreer($dtb, SIGLE_TEST, ['filiere_description' => 'Autre'], 1);
    verifier('un sigle en doublon est refusé', $r['ok'] === false, $r['message']);

    $r = mentionsCreer($dtb, 'ZZMO', ['filiere_description' => LIB_AVANT], 1);
    verifier('un libellé en doublon est refusé', $r['ok'] === false, $r['message']);
    verifier('le refus explique pourquoi (rattachement par le libellé)',
        strpos($r['message'], 'libellé') !== false);

    $r = mentionsCreer($dtb, 'ZZMO', ['filiere_description' => ''], 1);
    verifier('un libellé vide est refusé', $r['ok'] === false);

    titre('Une mention neuve est modifiable et supprimable');

    $liste = mentionsListe($dtb);
    $test = null;
    foreach ($liste as $l) { if ($l['filiere_sigle'] === SIGLE_TEST) { $test = $l; } }
    verifier('elle apparaît dans la liste', $test !== null);
    verifier('son sigle est encore modifiable (aucun usage)',
        $test && $test['sigle_modifiable'] === true);
    verifier('elle est supprimable (aucun usage)', $test && $test['supprimable'] === true);
    verifier('elle est signalée absente du formulaire public',
        $test && $test['publique'] === false);

    titre('Un usage verrouille le sigle et ferme la suppression');

    /* Un cours rattache au sigle : c'est l'une des cinq dependances. */
    $dtb->prepare('INSERT INTO t_2023_cours
        (sigle, dep_desc, title, yearlevel, nb_crd, semester, category, lab, cout, cout_lab)
        VALUES (?, ?, ?, 1, 3, 1, 0, 0, 0, 0)')
        ->execute(['ZZ001', SIGLE_TEST, 'Cours de test ZZMN']);

    $usage = mentionsUsage($dtb, SIGLE_TEST, LIB_AVANT);
    verifier('l\'usage est compté', $usage['t_2023_cours'] === 1, (string) $usage['t_2023_cours']);

    $liste = mentionsListe($dtb);
    foreach ($liste as $l) { if ($l['filiere_sigle'] === SIGLE_TEST) { $test = $l; } }
    verifier('le sigle est désormais verrouillé', $test['sigle_modifiable'] === false);
    verifier('la suppression est fermée', $test['supprimable'] === false);

    $r = mentionsSupprimer($dtb, SIGLE_TEST);
    verifier('la suppression est refusée', $r['ok'] === false);
    verifier('le refus chiffre ce qui s\'y rattache',
        strpos($r['message'], '1 cours') !== false, $r['message']);
    verifier('la mention existe toujours', mentionParSigle($dtb, SIGLE_TEST) !== false);

    titre('Renommer le libellé propage aux fiches étudiantes');

    /* C'est la propriete la plus importante : sans propagation, la recherche
       du bareme echoue pour ces fiches. */
    $dtb->prepare('INSERT INTO tbl_2024_etudiant
        (student_id, student_nom, student_prenom, etude_envisage, annee_etude,
         annee_scolaire, status, abonment, graduated, new_student, `remove`, date_entry)
        VALUES (?, ?, ?, ?, 1, "2026 - 2027", "Externe", 0, 0, 1, 0, CURDATE())')
        ->execute([ETU_TEST, 'TEST', 'Mention', LIB_AVANT]);

    $usage = mentionsUsage($dtb, SIGLE_TEST, LIB_AVANT);
    verifier('la fiche étudiante est comptée sur le libellé',
        $usage['tbl_2024_etudiant'] === 1, (string) $usage['tbl_2024_etudiant']);

    $r = mentionsEnregistrer($dtb, SIGLE_TEST, ['filiere_description' => LIB_APRES], 1);
    verifier('le renommage réussit', $r['ok'] === true, $r['message']);
    verifier('une fiche étudiante a suivi',
        ($r['fiches_mises_a_jour'] ?? 0) === 1, (string) ($r['fiches_mises_a_jour'] ?? 0));
    verifier('le message annonce le nombre de fiches',
        strpos($r['message'], 'fiche') !== false, $r['message']);

    $req = $dtb->prepare('SELECT etude_envisage FROM tbl_2024_etudiant WHERE student_id = ?');
    $req->execute([ETU_TEST]);
    verifier('la fiche porte le nouveau libellé',
        $req->fetchColumn() === LIB_APRES);

    /* La requete reelle du module inscription doit retrouver la mention. */
    $recherche = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = ?');
    $recherche->execute([LIB_APRES]);
    verifier('la recherche « filiere_description = etude_envisage » retrouve la mention',
        $recherche->fetchColumn() === SIGLE_TEST);

    titre('Le renommage peut aussi ne PAS propager');

    $r = mentionsEnregistrer($dtb, SIGLE_TEST,
        ['filiere_description' => LIB_AVANT], 1, false);
    verifier('le libellé de la mention change', $r['ok'] === true);
    verifier('aucune fiche n\'est touchée quand on ne propage pas',
        ($r['fiches_mises_a_jour'] ?? -1) === 0, (string) ($r['fiches_mises_a_jour'] ?? -1));

    $req->execute([ETU_TEST]);
    verifier('la fiche garde donc l\'ancien libellé — c\'est l\'état à éviter',
        $req->fetchColumn() === LIB_APRES);

    /* Et cet etat casse bien la recherche : la preuve que la propagation
       compte. */
    $recherche->execute([LIB_APRES]);
    verifier('sans propagation, la recherche du barème ne trouve plus rien',
        $recherche->fetchColumn() === false);

    titre('Anomalies relevées');

    $libOrphelins = mentionsLibellesOrphelins($dtb);
    $trouve = false;
    foreach ($libOrphelins as $o) { if ($o['libelle'] === LIB_APRES) { $trouve = true; } }
    verifier('un libellé étudiant sans mention est détecté', $trouve,
        'la page ne signalerait pas la fiche orpheline');

    verifier('les sigles orphelins sont listés sans erreur',
        is_array(mentionsOrphelines($dtb)));

    titre('Suppression une fois les usages retirés');

    $dtb->prepare('DELETE FROM t_2023_cours WHERE dep_desc = ?')->execute([SIGLE_TEST]);
    $dtb->prepare('DELETE FROM tbl_2024_etudiant WHERE student_id = ?')->execute([ETU_TEST]);

    $r = mentionsSupprimer($dtb, SIGLE_TEST);
    verifier('la suppression passe quand plus rien ne s\'y rattache',
        $r['ok'] === true, $r['message']);
    verifier('la mention a disparu', mentionParSigle($dtb, SIGLE_TEST) === false);

    $r = mentionsSupprimer($dtb, 'ZZXX');
    verifier('supprimer une mention inconnue est refusé', $r['ok'] === false);

    titre('Fermer une mention : ce que ça change');

    /* « Fermee » et non « archivee » : la bascule se reprend a tout moment, et
       rien n'est supprime. Ce que la fermeture change, c'est la presence de la
       mention dans les listes ou l'on CHOISIT une mention pour une donnee
       nouvelle. Ce qu'elle ne change pas, c'est tout le reste — sinon les 597
       etudiants de Theologie deviendraient impossibles a lister. */

    verifier('la colonne d\'ouverture existe sur cette base',
        mentionsOuvertureDisponible($dtb) === true,
        'appliquer data/migration_filiere_ouverte.sql');
    verifier('le filtre SQL cible la colonne',
        mentionsFiltreOuvertes() === 'ouverte = 1', mentionsFiltreOuvertes());

    /* La mention de test a ete recreee plus haut par le bloc parcours ; on
       s'assure qu'elle existe pour ce bloc-ci. */
    if (mentionParSigle($dtb, SIGLE_TEST) === false) {
        mentionsCreer($dtb, SIGLE_TEST, ['filiere_description' => LIB_AVANT], 1);
    }

    verifier('une mention neuve est ouverte par défaut',
        mentionEstOuverte($dtb, SIGLE_TEST) === true);
    verifier('elle figure parmi les sigles ouverts',
        in_array(SIGLE_TEST, mentionsSiglesOuverts($dtb), true));

    $r = mentionsBasculerOuverture($dtb, SIGLE_TEST, false, 1);
    verifier('la fermeture réussit', $r['ok'] === true, $r['message']);
    verifier('le message dit ce qui ne change pas',
        strpos($r['message'], 'existantes ne changent pas') !== false, $r['message']);
    verifier('la mention est fermée', mentionEstOuverte($dtb, SIGLE_TEST) === false);
    verifier('elle a quitté les sigles ouverts',
        !in_array(SIGLE_TEST, mentionsSiglesOuverts($dtb), true));

    /* Rien ne doit avoir bougé : c'est toute la différence avec la
       suppression. */
    $m = mentionParSigle($dtb, SIGLE_TEST);
    verifier('la mention existe toujours', $m !== false);
    verifier('son libellé est intact', $m && $m['filiere_description'] === LIB_AVANT);
    verifier('le lookup par sigle la retrouve encore',
        mentionParSigle($dtb, SIGLE_TEST) !== false,
        'addStd.php ne pourrait plus résoudre la mention d\'un étudiant existant');

    $parLibelle = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = ?');
    $parLibelle->execute([LIB_AVANT]);
    verifier('le lookup par libellé la retrouve encore',
        $parLibelle->fetchColumn() === SIGLE_TEST,
        'generate.student.php ne trouverait plus le barème');

    verifier('elle reste dans la liste de la page de réglage',
        count(array_filter(mentionsListe($dtb),
            static fn ($x) => $x['filiere_sigle'] === SIGLE_TEST)) === 1);

    $r = mentionsBasculerOuverture($dtb, SIGLE_TEST, true, 1);
    verifier('la réouverture réussit', $r['ok'] === true, $r['message']);
    verifier('la mention est de nouveau ouverte', mentionEstOuverte($dtb, SIGLE_TEST) === true);

    $r = mentionsBasculerOuverture($dtb, 'ZZINCONNU', false, 1);
    verifier('fermer une mention inconnue est refusé', $r['ok'] === false);

    titre('Fermer : les listes de choix filtrent, les autres non');

    /* Seules deux listes de choix doivent filtrer. Les filtres d'impression et
       de statistiques doivent au contraire garder les mentions fermées. */

    $creat = (string) @file_get_contents(__DIR__ . '/../src/creat.student.php');
    verifier('la création d\'étudiant filtre les mentions fermées',
        strpos($creat, 'mentionsFiltreOuvertes()') !== false);
    verifier('elle conserve la mention issue d\'une candidature',
        strpos($creat, 'filiere_sigle = :pre') !== false,
        'le formulaire perdrait le choix du candidat');

    $coursInfo = (string) @file_get_contents(__DIR__ . '/../src/cours/information.php');
    verifier('le formulaire de cours filtre les mentions fermées',
        strpos($coursInfo, 'mentionsFiltreOuvertes()') !== false);
    verifier('il conserve la mention déjà portée par le cours',
        strpos($coursInfo, 'filiere_sigle = :actuelle') !== false,
        'le premier enregistrement muterait le cours vers une autre mention');
    /* L'option de mention doit porter un value explicite. Sans lui, la valeur
       soumise est le TEXTE de l'option, suffixe « (fermée) » compris, et
       dep_desc recevrait « THEO (fermée) ». L'attribut manquait déjà avant ce
       suffixe : c'est le suffixe qui a rendu le défaut visible.
       On cherche la ligne, pas un motif sur la balise entière — celle-ci
       contient « <?=$bg_one_color?> », dont le « > » arrête tout [^>]*. */
    $ligneOption = '';
    foreach (explode("\n", $coursInfo) as $ligne) {
        if (strpos($ligne, "\$showMent['filiere_sigle']") !== false
            && strpos($ligne, '<option') !== false) {
            $ligneOption = $ligne;
            break;
        }
    }
    verifier('ses options portent un value explicite',
        $ligneOption !== ''
        && strpos($ligneOption, 'value="') !== false
        && strpos($ligneOption, 'htmlspecialchars') !== false,
        'la valeur soumise serait le texte, suffixe « (fermée) » compris');

    $toolbar = (string) @file_get_contents(__DIR__ . '/../init/toolbar.php');
    verifier('les filtres de la barre d\'outils ne filtrent PAS',
        strpos($toolbar, 'mentionsFiltreOuvertes()') === false,
        'on ne pourrait plus lister les étudiants d\'une mention fermée');

    $adm = (string) @file_get_contents(__DIR__ . '/../landing/pages/admissions.php');
    verifier('la pré-inscription publique refuse une mention fermée',
        strpos($adm, 'preadmMentionOuverte') !== false);
    verifier('elle reste ouverte en cas de doute technique',
        preg_match('/catch \(Throwable \$e\) \{[^}]*return true;/s', $adm) === 1,
        'une base injoignable refuserait toutes les candidatures');

    $endpointOuv = (string) @file_get_contents(__DIR__ . '/../app/mentions.save.php');
    verifier('le point d\'entrée traite « ouverture »',
        strpos($endpointOuv, "case 'ouverture'") !== false);

    $jsOuv = (string) @file_get_contents(__DIR__ . '/../src/js/settings.mentions.js');
    verifier('la confirmation distingue fermer de supprimer',
        strpos($jsOuv, "Rien n'est supprimé") !== false
        || strpos($jsOuv, 'Rien n\\\'est supprimé') !== false,
        'le libellé pourrait faire craindre une perte de données');

    titre('Parcours : validation du code');

    /* Le code sert de clé aux cours (324 lignes), le libellé rattache les
       fiches étudiantes (1 714) et les inscriptions (1 362). */

    verifier('un code vide est refusé', mentionParcoursValiderCode('') !== null);
    verifier('une seule lettre est refusée', mentionParcoursValiderCode('X') !== null);
    verifier('un espace est refusé', mentionParcoursValiderCode('A B') !== null);
    verifier('un accent est refusé', mentionParcoursValiderCode('ACCENTÉ') !== null);
    verifier('plus de vingt caractères est refusé',
        mentionParcoursValiderCode('CODE_BEAUCOUP_TROP_LONG_ICI') !== null);
    verifier('les formes existantes sont acceptées',
        mentionParcoursValiderCode('TC') === null
        && mentionParcoursValiderCode('C_COM_ENTR') === null);

    /* Valeur réservée : 335 cours portent parcours = 'all', qui signifie
       « tous les parcours de la mention ». Un parcours nommé ainsi les
       basculerait silencieusement dans un parcours unique. */
    verifier('le code réservé « all » est refusé',
        mentionParcoursValiderCode('all') !== null);
    verifier('« ALL » aussi, quelle que soit la casse',
        mentionParcoursValiderCode('ALL') !== null);
    verifier('le refus explique la valeur réservée',
        strpos((string) mentionParcoursValiderCode('all'), 'réservé') !== false);

    titre('Parcours : création');

    /* La mention de test a été supprimée plus haut : on la recrée pour y
       rattacher des parcours. */
    mentionsCreer($dtb, SIGLE_TEST, ['filiere_description' => LIB_AVANT], 1);

    $r = mentionParcoursCreer($dtb, SIGLE_TEST, 'ZZP1', [
        'description' => 'Parcours de test 1',
        'description_ang' => 'Test track 1',
    ], 1);
    verifier('le parcours est créé', $r['ok'] === true, $r['message']);

    $p = mentionParcoursParCode($dtb, 'ZZP1');
    verifier('il est relu depuis la base', $p !== false);
    verifier('il est rattaché à la mention', $p && $p['departement'] === SIGLE_TEST);
    verifier('les deux libellés sont enregistrés',
        $p && $p['description'] === 'Parcours de test 1'
        && $p['description_ang'] === 'Test track 1');

    /* La colonne id est NOT NULL sans auto-increment : elle doit être
       fournie. La clé primaire, elle, est le shortcode. */
    verifier('un id a été attribué', $p && $p['id'] !== null && $p['id'] !== '');

    $r = mentionParcoursCreer($dtb, SIGLE_TEST, 'ZZP1', ['description' => 'Autre'], 1);
    verifier('un code en doublon est refusé', $r['ok'] === false, $r['message']);

    $r = mentionParcoursCreer($dtb, SIGLE_TEST, 'ZZP2',
        ['description' => 'Parcours de test 1'], 1);
    verifier('un libellé en doublon est refusé', $r['ok'] === false, $r['message']);

    $r = mentionParcoursCreer($dtb, 'ZZXX', 'ZZP2', ['description' => 'Ailleurs'], 1);
    verifier('une mention inconnue est refusée', $r['ok'] === false, $r['message']);

    verifier('le parcours apparaît dans la liste de sa mention',
        count(mentionParcoursListe($dtb, SIGLE_TEST)) === 1);

    titre('Parcours : le code est verrouillé par les cours');

    $dtb->prepare('INSERT INTO t_2023_cours
        (sigle, dep_desc, title, yearlevel, nb_crd, semester, category, lab, cout, cout_lab, parcours)
        VALUES (?, ?, ?, 1, 3, 1, 0, 0, 0, 0, ?)')
        ->execute(['ZZ002', SIGLE_TEST, 'Cours de test parcours', 'ZZP1']);

    $u = mentionParcoursUsage($dtb, 'ZZP1', 'Parcours de test 1');
    verifier('le cours est compté', $u['t_2023_cours'] === 1, (string) $u['t_2023_cours']);
    verifier('le code est signalé verrouillé', $u['shortcode_verrouille'] === true);

    $r = mentionParcoursSupprimer($dtb, 'ZZP1');
    verifier('la suppression est refusée', $r['ok'] === false);
    verifier('le refus chiffre les cours rattachés',
        strpos($r['message'], '1 cours') !== false, $r['message']);

    titre('Parcours : renommer propage aux fiches ET aux inscriptions');

    /* Deux tables suivent le libellé, pas une seule. */
    $dtb->prepare('INSERT INTO tbl_2024_etudiant
        (student_id, student_nom, student_prenom, etude_envisage, etude_option, annee_etude,
         annee_scolaire, status, abonment, graduated, new_student, `remove`, date_entry)
        VALUES (?, ?, ?, ?, ?, 1, "2026 - 2027", "Externe", 0, 0, 1, 0, CURDATE())')
        ->execute([ETU_TEST, 'TEST', 'Parcours', LIB_AVANT, 'Parcours de test 1']);

    $dtb->prepare('INSERT INTO t_2024_inscription_session
        (student_id, etude_mention, status, new_student, data_completion, session_id,
         nbr_semester, annee_scolaire, parcours_std, date_entry)
        VALUES (?, ?, "Externe", 0, 1, 490, 1, "2026 - 2027", ?, CURDATE())')
        ->execute([ETU_TEST, SIGLE_TEST, 'Parcours de test 1']);

    $u = mentionParcoursUsage($dtb, 'ZZP1', 'Parcours de test 1');
    verifier('la fiche et l\'inscription sont comptées',
        $u['tbl_2024_etudiant'] === 1 && $u['t_2024_inscription_session'] === 1,
        $u['tbl_2024_etudiant'] . '/' . $u['t_2024_inscription_session']);

    $r = mentionParcoursEnregistrer($dtb, 'ZZP1',
        ['description' => 'Parcours de test renomme'], 1);
    verifier('le renommage réussit', $r['ok'] === true, $r['message']);
    verifier('une fiche étudiante a suivi', ($r['fiches'] ?? 0) === 1);
    verifier('une inscription a suivi', ($r['inscriptions'] ?? 0) === 1);
    verifier('le message annonce les deux', strpos($r['message'], 'inscription') !== false);

    /* La jointure réelle de src/student/new.cours.php et src/student.php. */
    $jointure = $dtb->prepare('SELECT p.shortcode
        FROM tbl_2024_etudiant e
        JOIN filiere_parcours p ON p.description = e.etude_option
        WHERE e.student_id = ?');
    $jointure->execute([ETU_TEST]);
    verifier('la jointure « description = etude_option » retrouve le parcours',
        $jointure->fetchColumn() === 'ZZP1');

    $jointureI = $dtb->prepare('SELECT p.shortcode
        FROM t_2024_inscription_session i
        JOIN filiere_parcours p ON p.description = i.parcours_std
        WHERE i.student_id = ?');
    $jointureI->execute([ETU_TEST]);
    verifier('la jointure sur parcours_std aussi',
        $jointureI->fetchColumn() === 'ZZP1');

    titre('Parcours : sans propagation, les jointures cassent');

    $r = mentionParcoursEnregistrer($dtb, 'ZZP1',
        ['description' => 'Parcours de test 1'], 1, false);
    verifier('le libellé du parcours change', $r['ok'] === true);
    verifier('aucune fiche ni inscription touchée',
        ($r['fiches'] ?? -1) === 0 && ($r['inscriptions'] ?? -1) === 0);

    $jointure->execute([ETU_TEST]);
    verifier('la jointure ne trouve plus rien — c\'est l\'état à éviter',
        $jointure->fetchColumn() === false);

    $orphelins = mentionParcoursLibellesOrphelins($dtb);
    $trouve = false;
    foreach ($orphelins as $o) {
        if ($o['libelle'] === 'Parcours de test renomme') { $trouve = true; }
    }
    verifier('la page signalerait ce libellé orphelin', $trouve);

    titre('Parcours : suppression une fois libéré');

    $dtb->prepare('DELETE FROM t_2023_cours WHERE parcours = ?')->execute(['ZZP1']);
    $dtb->prepare('DELETE FROM tbl_2024_etudiant WHERE student_id = ?')->execute([ETU_TEST]);
    $dtb->prepare('DELETE FROM t_2024_inscription_session WHERE student_id = ?')->execute([ETU_TEST]);

    $r = mentionParcoursSupprimer($dtb, 'ZZP1');
    verifier('la suppression passe quand plus rien ne s\'y rattache',
        $r['ok'] === true, $r['message']);
    verifier('le parcours a disparu', mentionParcoursParCode($dtb, 'ZZP1') === false);

    $r = mentionParcoursSupprimer($dtb, 'ZZINCONNU');
    verifier('supprimer un parcours inconnu est refusé', $r['ok'] === false);

    titre('Parcours : la page en rend la configuration');

    $pageSrc = (string) @file_get_contents(__DIR__ . '/../src/settings.mentions.php');
    verifier('chaque mention porte un bloc parcours',
        strpos($pageSrc, 'js-mn-parcours-bascule') !== false);
    verifier('le bloc est replié par défaut',
        strpos($pageSrc, 'class="mn-parcours__corps" hidden') !== false);
    verifier('un formulaire d\'ajout par mention',
        strpos($pageSrc, 'js-mn-pc-creer') !== false);
    verifier('modification et suppression par parcours',
        strpos($pageSrc, 'js-mn-pc-modifier') !== false
        && strpos($pageSrc, 'js-mn-pc-supprimer') !== false);
    verifier('les libellés orphelins de parcours sont signalés',
        strpos($pageSrc, 'mentionParcoursLibellesOrphelins') !== false);

    $endpoint = (string) @file_get_contents(__DIR__ . '/../app/mentions.save.php');
    foreach (['parcours_creer', 'parcours_enregistrer', 'parcours_supprimer'] as $action) {
        verifier("le point d'entrée traite « $action »",
            strpos($endpoint, "case '$action'") !== false);
    }
    verifier('les colonnes de parcours sont filtrées séparément',
        strpos($endpoint, 'mentionParcoursChamps()') !== false,
        'un nom de colonne pourrait venir du client');

    $jsSrc = (string) @file_get_contents(__DIR__ . '/../src/js/settings.mentions.js');
    verifier('le client confirme avant de propager un renommage de parcours',
        strpos($jsSrc, 'dataset.inscriptions') !== false
        && strpos($jsSrc, 'parcours_enregistrer') !== false);

    /* La mention de test doit repartir : la suite en crée d'autres. */
    mentionsSupprimer($dtb, SIGLE_TEST);

    titre('Accès à la page et au point d\'entrée');

    /** Code HTTP d'une requête anonyme. */
    $code = static function (string $chemin, string $methode) use ($base): int {
        $ctx = stream_context_create([
            'http' => [
                'method' => $methode,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => 'action=creer&sigle=ZZPI&filiere_description=Pirate',
                'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10,
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

    $c1 = $code('/settings/mentions', 'GET');
    $c2 = $code('/app/mentions.save.php', 'POST');
    if ($c1 === 0 || $c2 === 0) {
        echo "  (ignoré) serveur injoignable\n";
    } else {
        verifier('la page refuse un visiteur anonyme',
            in_array($c1, [302, 401, 403], true), "HTTP $c1");
        verifier('le point d\'entrée refuse un visiteur anonyme',
            in_array($c2, [302, 401, 403], true), "HTTP $c2");
        verifier('aucune mention n\'a été créée par la requête anonyme',
            mentionParSigle($dtb, 'ZZPI') === false);
    }

    titre('Verrou d\'accès déclaré dans le code');

    $ctrl = (string) @file_get_contents(__DIR__ . '/../controllers/AdminController.php');
    verifier('le contrôleur réserve la page au niveau 1',
        preg_match('/function mentionsSettings.*?requireLevelAccess\(1\)/s', $ctrl) === 1,
        'le verrou a changé de niveau');

    $save = (string) @file_get_contents(__DIR__ . '/../app/mentions.save.php');
    verifier('le point d\'entrée vérifie aussi le niveau 1',
        strpos($save, "\$_SESSION['user_level'] ?? 99) !== 1") !== false,
        'la garde du point d\'entrée a disparu');
    verifier('le point d\'entrée exige un jeton CSRF',
        strpos($save, 'verify_csrf()') !== false);

    $settings = (string) @file_get_contents(__DIR__ . '/../src/settings.php');
    verifier('la carte est présente sur /settings',
        strpos($settings, '/settings/mentions') !== false);
    verifier('la carte est grisée hors niveau 1',
        preg_match('#settings/mentions.*?level.*?!== 1.*?toolInactive#s', $settings) === 1,
        'une tuile active menant à un 403');

} finally {
    nettoyer($dtb);

    $empreinteFin = empreinteMentions($dtb);
    if ($empreinteFin === $empreinteDepart) {
        $reussis++;
        echo "\n  OK    les mentions réelles sont identiques à l'état de départ\n";
    } else {
        $echoues++;
        echo "\n  ECHEC les mentions réelles ont changé -> $empreinteDepart vs $empreinteFin\n";
    }

    $residus = (int) $dtb->query('SELECT COUNT(*) FROM filiere WHERE filiere_sigle LIKE "ZZ%"')->fetchColumn()
        + (int) $dtb->query('SELECT COUNT(*) FROM tbl_2024_etudiant WHERE student_id LIKE "ZZMN%"')->fetchColumn()
        + (int) $dtb->query('SELECT COUNT(*) FROM t_2023_cours WHERE dep_desc LIKE "ZZ%"')->fetchColumn();
    if ($residus === 0) {
        $reussis++;
        echo "  OK    aucun résidu de test\n";
    } else {
        $echoues++;
        echo "  ECHEC $residus ligne(s) de test subsistent\n";
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Reussis : $reussis   Echoues : $echoues\n";
    exit($echoues === 0 ? 0 : 1);
}
