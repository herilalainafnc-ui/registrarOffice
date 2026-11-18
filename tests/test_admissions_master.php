<?php
/* =============================================================================
   Pre-inscription : mentions et parcours viennent de /settings/mentions

   Le formulaire portait ses mentions en dur a trois endroits, et servait les
   parcours cycles confondus. Un candidat pouvait donc demander un Master de
   Theologie, qui n'existe pas, ou choisir un parcours de licence pour un
   Master ; une mention creee par le registrariat n'apparaissait pas, et une
   mention fermee continuait d'etre proposee.

   LES ATTENTES SE DEDUISENT DE LA BASE
   ------------------------------------
   Ecrites en dur, elles devenaient fausses des que le registrariat reglait un
   cycle ou fermait une mention — le test echouait alors sans que le code ait
   bouge. La regle verifiee ici est donc la regle, pas son resultat du jour :
   une mention prepare un diplome si et seulement si l'un de ses parcours porte
   le cycle correspondant.

   Le test n'ecrit rien : il n'envoie que des candidatures que la validation
   refuse avant l'enregistrement.

   Lancement :  php tests/test_admissions_master.php
   ========================================================================== */

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/data/parcours.php';

$ok = 0; $ko = 0;
function verifie(string $quoi, $attendu, $obtenu) : void {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  ok    $quoi\n"; return; }
    $ko++;
    echo "  ECHEC $quoi\n        attendu: " . var_export($attendu, true)
       . "\n        obtenu : " . var_export($obtenu, true) . "\n";
}

const BASE = 'https://registrar.test';

/** Envoie une candidature volontairement incomplete et rend [code, JSON]. */
function candidature(string $mention, string $diplome, string $parcours = 'X', bool $avecMotivation = true) : array {
    $donnees = [
        'nom' => 'T', 'prenom' => 'T', 'email' => 't@example.invalid',
        'phone' => '034', 'genre' => 'M', 'annee_naissance' => '2000',
        'mention' => $mention, 'diplome_vise' => $diplome, 'parcours' => $parcours,
    ];
    // La motivation est obligatoire : sans elle, le refus arrive avant les
    // controles que ces essais visent.
    if ($avecMotivation) { $donnees['message'] = 'Motivation de test.'; }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                      . "X-Requested-With: XMLHttpRequest\r\n",
            'content' => http_build_query($donnees),
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $rep = @file_get_contents(BASE . '/admissions', false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $l, $m)) { $code = (int) $m[1]; }
    }
    return [$code, json_decode((string) $rep, true)];
}

/** La page, telle qu'un visiteur la recoit. */
function page(string $url) : string {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    return (string) @file_get_contents(BASE . $url, false, $ctx);
}

$publiques = parcoursMentionsPubliques();
if ($publiques === []) {
    echo "Aucune mention ouverte en base : rien a verifier.\n";
    exit(1);
}

$avecMaster  = parcoursMentionsMaster();
$sansMaster  = array_values(array_diff(array_keys($publiques), $avecMaster));
$avecLicence = [];
foreach ($publiques as $cle => $m) {
    if ($m['parcours']['L'] !== []) { $avecLicence[] = $cle; }
}

echo "mentions ouvertes : " . count($publiques) . "\n";
echo "  avec Master  : " . (implode(', ', $avecMaster) ?: '(aucune)') . "\n";
echo "  sans Master  : " . (implode(', ', $sansMaster) ?: '(aucune)') . "\n\n";

/* -------------------------------------------------------------------------- */
echo "== La regle, en PHP\n";

foreach ($publiques as $cle => $m) {
    verifie("$cle : Master accepte si et seulement s'il a un parcours de master",
        $m['parcours']['M'] !== [], parcoursMentionAccepteDiplome($cle, 'master'));
    verifie("$cle : Licence acceptee si et seulement s'il a un parcours de licence",
        $m['parcours']['L'] !== [], parcoursMentionAccepteDiplome($cle, 'licence'));
}

verifie('une mention inconnue est refusee', false, parcoursMentionAccepteDiplome('astrologie', 'licence'));

/* Les parcours servis pour un diplome sont ceux de son cycle, pas les autres. */
foreach ($publiques as $cle => $m) {
    verifie("$cle : les parcours du Master sont ceux marques master",
        $m['parcours']['M'], parcoursDeMentionPourDiplome($cle, 'master'));
    verifie("$cle : les parcours de la Licence sont ceux marques licence",
        $m['parcours']['L'], parcoursDeMentionPourDiplome($cle, 'licence'));
}

/* Un couple valide existe et serait accepte. Verifie ici plutot que par HTTP :
   une candidature valide s'enregistrerait, et ce test n'ecrit rien. */
$couple = null;
foreach ($publiques as $cle => $m) {
    if ($m['parcours']['L'] !== []) { $couple = [$cle, 'licence', $m['parcours']['L'][0]]; break; }
}
verifie('un couple mention/diplome/parcours valide existe', true, $couple !== null);
if ($couple !== null) {
    [$cle, $dip, $parc] = $couple;
    verifie("« $parc » est accepte pour $cle en $dip", true,
        in_array($parc, parcoursDeMentionPourDiplome($cle, $dip), true));
}

/* -------------------------------------------------------------------------- */
echo "\n== Les mentions fermees sont hors du formulaire\n";

/* La liste publique se limite aux mentions ouvertes : une fermeture doit les
   en retirer, alors qu'auparavant seul un controle serveur les refusait apres
   que le candidat avait tout saisi. */
$fermees = [];
try {
    if (!defined('DB_DSN')) { require_once ROOT_DIR . '/data/config.php'; }
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    require_once ROOT_DIR . '/data/mentions.php';
    mentionsOuvertureDisponible($pdo);
    $req = $pdo->query('SELECT filiere_sigle, filiere_description FROM filiere
                         WHERE ' . mentionsColonneOuverte() . ' <> 1');
    $fermees = $req->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "  (base injoignable : controle des fermetures saute)\n";
}

if ($fermees === []) {
    echo "  (aucune mention fermee en base : rien a verifier)\n";
}
foreach ($fermees as $f) {
    $cle = parcoursCleDeSigle((string) $f['filiere_sigle']);
    verifie("« {$f['filiere_description']} » (fermee) est absente de la liste publique",
        false, array_key_exists($cle, $publiques));

    [$c, $r] = candidature($cle, 'licence');
    $dit = isset($r['message']) && mb_strpos($r['message'], "n'est pas proposée") !== false;
    verifie("« {$f['filiere_description']} » (fermee) est refusee par le serveur", true, $dit);
}

/* -------------------------------------------------------------------------- */
echo "\n== La validation, par HTTP\n";

foreach ($sansMaster as $m) {
    [$c, $r] = candidature($m, 'master');
    $dit = isset($r['message']) && mb_strpos($r['message'], 'ne propose pas de Master') !== false;
    verifie("$m + Master -> refus explicite", true, $dit);
    $champs = $r['champs'] ?? [];
    verifie("$m + Master -> signale mention et diplome", true,
        in_array('mention', $champs, true) && in_array('diplome_vise', $champs, true));
}

/* Les mentions autorisees passent ce controle : l'erreur suivante porte sur le
   parcours, ce qui prouve que le couple diplome/mention a ete accepte. */
foreach ($avecMaster as $m) {
    [$c, $r] = candidature($m, 'master');
    verifie("$m + Master -> passe au controle du parcours", ['parcours'], $r['champs'] ?? []);
}
foreach ($avecLicence as $m) {
    [$c, $r] = candidature($m, 'licence');
    verifie("$m + Licence -> passe au controle du parcours", ['parcours'], $r['champs'] ?? []);
}

/* Le cycle tient aussi cote serveur : un parcours de licence envoye avec un
   Master doit etre refuse, sinon le reglage ne vaudrait que pour l'affichage. */
$croise = null;
foreach ($publiques as $cle => $m) {
    $seulementL = array_values(array_diff($m['parcours']['L'], $m['parcours']['M']));
    if ($m['parcours']['M'] !== [] && $seulementL !== []) { $croise = [$cle, $seulementL[0]]; break; }
}
if ($croise === null) {
    echo "  (aucune mention n'a de parcours propre a la licence ET un Master :\n"
       . "   le croisement n'est pas verifiable sur ces donnees)\n";
} else {
    [$cle, $parcLicence] = $croise;
    [$c, $r] = candidature($cle, 'master', $parcLicence);
    verifie("$cle : « $parcLicence » (licence) refuse pour un Master",
        ['parcours'], $r['champs'] ?? []);
    $dit = isset($r['message']) && mb_strpos($r['message'], 'diplôme') !== false;
    verifie("$cle : le refus nomme le diplome", true, $dit);
}

/* La motivation est obligatoire cote serveur aussi : le required du
   formulaire ne protege pas d'un envoi fait sans le navigateur. */
$uneMention = array_keys($publiques)[0];
[$c, $r] = candidature($uneMention, 'licence', 'X', false);
verifie('sans motivation -> refuse', false, (bool) ($r['ok'] ?? true));
verifie('sans motivation -> le refus nomme la motivation', true, in_array('message', $r['champs'] ?? [], true));

/* -------------------------------------------------------------------------- */
echo "\n== Le formulaire, sur les deux chemins\n";

foreach (['/' => 'page unique', '/admissions' => 'page autonome'] as $url => $nom) {
    $h = page($url);

    verifie("$nom : la page repond", true, $h !== '');
    verifie("$nom : aucune erreur PHP", 0,
        preg_match('#(Fatal error|Parse error|Warning:|Notice:|Deprecated:)#', $h));

    /* Les parcours sont ranges par cycle : c'est ce que le script attend. */
    $liste = [];
    if (preg_match('#var PARCOURS = (\{.*?\});#s', $h, $m)) {
        $liste = json_decode($m[1], true) ?: [];
    }
    verifie("$nom : les parcours sont servis par cycle", parcoursParMentionEtCycle(), $liste);

    // Chaque mention ouverte est proposee, avec son libelle de la base.
    foreach ($publiques as $cle => $mention) {
        $attendu = 'value="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '"';
        verifie("$nom : « {$mention['libelle']} » est proposee", true, mb_strpos($h, $attendu) !== false);
    }

    // Les mentions fermees ne le sont pas.
    foreach ($fermees as $f) {
        $cle = parcoursCleDeSigle((string) $f['filiere_sigle']);
        verifie("$nom : « {$f['filiere_description']} » (fermee) n'est pas proposee",
            false, mb_strpos($h, 'value="' . $cle . '"') !== false);
    }

    /* Le suffixe porte seul l'explication, au plus pres du selecteur. On
       verifie qu'aucune phrase d'aide redondante n'est revenue au-dessus. */
    verifie("$nom : le suffixe explicatif est present", true,
        mb_strpos($h, 'Licence seulement') !== false
        || mb_strpos($h, 'Master seulement') !== false);
    verifie("$nom : aucune phrase d'aide redondante", 0, substr_count($h, 'master-aide'));
}

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko === 0 ? 0 : 1);
