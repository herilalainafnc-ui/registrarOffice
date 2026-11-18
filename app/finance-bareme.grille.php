<?php
/* =============================================================================
   Grille du bareme pour une annee et un semestre — reponse JSON

   Sert le changement d'annee et le changement de semestre sans recharger la
   page. Le rechargement complet renvoyait en haut d'une page de plus de neuf
   cents champs et faisait perdre l'endroit ou l'on travaillait — le meme motif
   qui avait justifie le passage en AJAX de /schedule et des reglages de la
   vitrine.

   Renvoie deux fragments :
     barre    les onglets de semestre et la pastille d'etat
     grille   les cartes de filieres, ou le message d'annee vide

   Les deux sont rendus par les memes fichiers que la page
   (src/finance/bareme.barre.php et bareme.grille.php) : un seul rendu a
   maintenir, et aucun risque de divergence entre le premier affichage et les
   suivants.

   Place dans app/ : convention du projet pour ce qui repond des donnees
   plutot qu'une page.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/finance.bareme.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bgRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Meme exigence d'acces que FinanceController::index() : niveau <= 3. La
   grille expose la totalite du bareme de l'etablissement. */
$niveau = (int) ($_SESSION['user_level'] ?? 99);
if ($niveau > 3) {
    bgRepondre(['ok' => false, 'message' => 'Accès refusé : privilèges insuffisants.'], 403);
}

/* Lecture seule : pas de jeton CSRF exige, conformement a la regle du projet
   pour les fragments qui n'ecrivent rien (cf. inscriptionGuardFragment). */

/* --- Chemin de base, comme le calculent les autres points d'entree ------- */
if (!isset($app_base)) {
    $_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $_ar = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $app_base = ($_dr !== '' && strpos($_ar, $_dr) === 0) ? (substr($_ar, strlen($_dr)) ?: '') : '';
}

/* --- Parametres, valides par le socle ----------------------------------- */
$anneeChoisie  = baremeAnneeChoisie($dtb, $_REQUEST['annee'] ?? null);
$anneesDispo   = baremeAnnees($dtb);
$sessionsAnnee = baremeSessionsAnnee($dtb, $anneeChoisie);

$semestresDispo = array_keys($sessionsAnnee);
$semestreChoisi = isset($_REQUEST['semestre']) ? (int) $_REQUEST['semestre'] : 0;
if (!in_array($semestreChoisi, $semestresDispo, true)) {
    $semestreChoisi = $semestresDispo[0] ?? 1;
}

$sessionCourante = $sessionsAnnee[$semestreChoisi] ?? null;
$sessionId       = $sessionCourante ? (int) $sessionCourante['session_id'] : BAREME_BASE;

$editeBase = (($_REQUEST['base'] ?? '') === '1');
$cibleId   = $editeBase ? BAREME_BASE : $sessionId;

/* Même calcul que src/gestion_finance.php : mentions ouvertes seulement. */
require_once(__DIR__ . '/../data/mentions.php');
$mentionsOuvertes  = mentionsSiglesOuverts($dtb);
$nbMentions        = count($mentionsOuvertes);
$mentionsAjusteesN = $cibleId === BAREME_BASE ? 0 : count(array_intersect(baremeMentionsSession($dtb, $cibleId), $mentionsOuvertes));

/* Les fragments s'appuient sur cette fonction, definie par la page. Elle est
   donc fournie ici aussi, avec la meme signature. */
if (!function_exists('baremeLien')) {
    function baremeLien(string $annee, int $semestre, bool $base = false): string
    {
        global $app_base;

        return $app_base . '/finance?annee=' . urlencode($annee)
            . '&semestre=' . $semestre . ($base ? '&base=1' : '');
    }
}

/* --- Rendu --------------------------------------------------------------- */
try {
    ob_start();
    require(__DIR__ . '/../src/finance/bareme.barre.php');
    $barre = ob_get_clean();

    ob_start();
    if (!$sessionCourante && !$editeBase) {
        echo '<div class="bareme-vide">Aucune session au catalogue pour '
            . htmlspecialchars($anneeChoisie, ENT_QUOTES, 'UTF-8') . '.</div>';
    } else {
        require(__DIR__ . '/../src/finance/bareme.grille.php');
    }
    $grille = ob_get_clean();

} catch (Throwable $e) {
    /* Un tampon reste ouvert si l'erreur survient en plein rendu : on le vide
       pour que la reponse JSON ne soit pas precedee de HTML partiel. */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('finance-bareme.grille: ' . $e->getMessage());
    bgRepondre(['ok' => false, 'message' => "La grille n'a pas pu être chargée."], 500);
}

bgRepondre([
    'ok' => true,
    'annee' => $anneeChoisie,
    'semestre' => $semestreChoisi,
    'base' => $editeBase,
    'session_id' => $cibleId,
    /* Titre de fenetre et adresse, pour que l'historique reste coherent. */
    'url' => baremeLien($anneeChoisie, $semestreChoisi, $editeBase),
    'barre' => $barre,
    'grille' => $grille,
]);
