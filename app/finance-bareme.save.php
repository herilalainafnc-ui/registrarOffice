<?php
/* =============================================================================
   Enregistrement du bareme financier d'une session — reponse JSON

   REMPLACE src/finance/save_finance.php ET src/finance/insert_finance.php,
   qui presentaient trois defauts graves :

   1. AUCUNE AUTHENTIFICATION. save_finance.php se contentait d'un
      require backdb.php puis executait son UPDATE. Un POST anonyme sur
      /src/finance/save_finance.php?filiere=THEO&semester=1 remettait donc a
      zero le bareme de toute une mention. Verifie : la requete repondait 302,
      c'est-a-dire qu'elle avait deja ecrit.
   2. ECRASEMENT PAR ZERO. Les onze montants etaient initialises a 0 puis
      seuls ceux presents dans le POST etaient repris. Comme la page ne rend
      pas d'input pour frais_graduation au semestre 1, ni pour frais_costume
      hors Theologie, chaque enregistrement remettait ces montants a zero.
   3. AUCUN CSRF, et une reponse par redirection HTTP inexploitable en AJAX.

   Place dans app/ : convention du projet pour ce qui repond des donnees
   plutot qu'une page, et src/js/transitions.js s'appuie dessus pour ne pas
   jouer de transition sur ces adresses.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/finance.bareme.php');
require_once(__DIR__ . '/../data/finance.frais.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bmRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bmRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

/* Meme exigence d'acces que FinanceController : niveau <= 3, soit superadmin,
   administrateur et registraire. La garde de la page ne protegeait pas ce
   point d'entree, qui vit hors du routeur. */
$niveau = (int) ($_SESSION['user_level'] ?? 99);
if ($niveau > 3) {
    bmRepondre(['ok' => false, 'message' => 'Accès refusé : privilèges insuffisants.'], 403);
}

/* 400 plutot que 403 : src/js/session.js intercepte les 403 pour annoncer un
   refus de privileges et rediriger, ce qui serait faux pour un jeton expire.
   Et pas 419 : ce code n'est pas enregistre a l'IANA et ce serveur le
   transforme en 500. Le motif voyage dans la charge utile. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    bmRepondre(['ok' => false, 'csrf_expire' => true,
                'message' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.'], 400);
}

$action = (string) ($_POST['action'] ?? 'enregistrer');

/* --- Session ciblee ----------------------------------------------------- */
$session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : -1;
if ($session_id < 0) {
    bmRepondre(['ok' => false, 'message' => 'Session manquante.'], 400);
}

/* La session doit exister au catalogue. 0 est admis : c'est le bareme de base. */
if ($session_id !== BAREME_BASE) {
    $verif = $dtb->prepare('SELECT session_name, session_year, session_semester
        FROM t_2023_session WHERE session_id = :s LIMIT 1');
    $verif->execute(['s' => $session_id]);
    $session = $verif->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        bmRepondre(['ok' => false, 'message' => 'Session inconnue.'], 422);
    }
} else {
    $session = ['session_name' => 'Barème de base', 'session_year' => '', 'session_semester' => null];
}

/* --- Mentions ----------------------------------------------------------- */
/* Chaque sigle est verifie contre filiere : il entre dans la cle des lignes
   ecrites, il ne doit pas etre invente par le client. */
$verifierMention = $dtb->prepare('SELECT 1 FROM filiere WHERE filiere_sigle = :m LIMIT 1');

$mention = trim((string) ($_POST['mention'] ?? ''));
if ($mention !== '') {
    $verifierMention->execute(['m' => $mention]);
    if (!$verifierMention->fetchColumn()) {
        bmRepondre(['ok' => false, 'message' => 'Mention inconnue : ' . $mention], 422);
    }
}

/* Filieres supplementaires recevant les MEMES montants.
   Plusieurs mentions partagent souvent un bareme identique : releve en base,
   les huit mentions ne forment que cinq profils distincts par semestre, et
   chaque case ne prend que deux a quatre valeurs differentes. Sans cette
   liste, il fallait retaper les memes montants trois ou quatre fois.

   Le regroupement n'est pas fige : THEO partage le bareme de COMM, DROI et
   EDUC au semestre 2 mais est seule au semestre 1. Les cibles sont donc
   choisies a chaque enregistrement, pas definies une fois pour toutes. */
$mentionsSupp = $_POST['mentions'] ?? [];
if (!is_array($mentionsSupp)) { $mentionsSupp = []; }

$cibles = [$mention];
foreach ($mentionsSupp as $sigle) {
    $sigle = trim((string) $sigle);
    if ($sigle === '' || in_array($sigle, $cibles, true)) {
        continue; // vide, ou deja dans la liste
    }
    $verifierMention->execute(['m' => $sigle]);
    if (!$verifierMention->fetchColumn()) {
        bmRepondre(['ok' => false, 'message' => 'Mention inconnue : ' . $sigle], 422);
    }
    $cibles[] = $sigle;
}

try {
    /* ===================== RECOPIE D'UN BAREME ========================== */
    if ($action === 'copier') {
        $source = isset($_POST['source_session_id']) ? (int) $_POST['source_session_id'] : BAREME_BASE;
        if ($session_id === BAREME_BASE) {
            bmRepondre(['ok' => false,
                        'message' => 'Le barème de base ne peut pas être remplacé par une recopie.'], 422);
        }

        $dtb->beginTransaction();
        $bilan = baremeCopier($dtb, $source, $session_id, $mention !== '' ? $mention : null);
        $dtb->commit();

        bmRepondre([
            'ok' => true,
            'action' => 'copier',
            'copiees' => $bilan['copiees'],
            'ignorees' => $bilan['ignorees'],
            'message' => $bilan['copiees'] > 0
                ? $bilan['copiees'] . ' ligne(s) reprise(s)'
                    . ($bilan['ignorees'] > 0
                        ? ', ' . $bilan['ignorees'] . ' conservée(s) car déjà ajustée(s).'
                        : '.')
                : 'Rien à reprendre : toutes les lignes sont déjà ajustées pour cette session.',
        ]);
    }

    /* ============ RETOUR AU BAREME DE BASE (suppression) ================ */
    if ($action === 'reinitialiser') {
        if ($session_id === BAREME_BASE) {
            bmRepondre(['ok' => false,
                        'message' => 'Le barème de base ne peut pas être supprimé.'], 422);
        }

        $dtb->beginTransaction();
        $supprimees = baremeSupprimerSurcharge($dtb, $session_id, $mention !== '' ? $mention : null);
        $dtb->commit();

        bmRepondre([
            'ok' => true,
            'action' => 'reinitialiser',
            'supprimees' => $supprimees,
            'message' => $supprimees > 0
                ? $supprimees . ' ligne(s) supprimée(s) : cette session revient au barème de base.'
                : 'Cette session utilisait déjà le barème de base.',
        ]);
    }

    /* ==================== ENREGISTREMENT DES MONTANTS =================== */
    if ($mention === '') {
        bmRepondre(['ok' => false, 'message' => 'Mention manquante.'], 400);
    }

    $semestre = isset($_POST['semestre']) ? (int) $_POST['semestre'] : 0;
    if ($semestre < 1 || $semestre > 4) {
        bmRepondre(['ok' => false, 'message' => 'Semestre invalide.'], 400);
    }

    /* Coherence entre la session et le semestre annonce : ecrire des montants
       de semestre 2 sur la session « Premier semestre » produirait des lignes
       que personne ne relirait jamais. */
    if ($session_id !== BAREME_BASE && (int) $session['session_semester'] !== $semestre) {
        bmRepondre(['ok' => false,
                    'message' => 'La session « ' . $session['session_name'] . ' » ne correspond pas au semestre '
                        . $semestre . '.'], 422);
    }

    /* Montants attendus sous la forme montants[niveau|statut][colonne].
       Le tri et la validation sont faits par baremeEnregistrer(), qui
       n'accepte que des colonnes connues et des valeurs numeriques. */
    $montants = $_POST['montants'] ?? null;
    if (!is_array($montants)) { $montants = []; }

    /* Frais supplementaires : montants_perso[frais_id][niveau|statut].
       Une case vide y est transmise, contrairement aux colonnes fixes :
       c'est ainsi qu'un montant s'efface pour revenir au bareme de base
       (data/finance.frais.php). */
    $montantsPerso = $_POST['montants_perso'] ?? [];
    if (!is_array($montantsPerso)) { $montantsPerso = []; }

    if ($montants === [] && $montantsPerso === []) {
        bmRepondre(['ok' => false, 'message' => 'Aucun montant transmis.'], 400);
    }

    /* Une seule transaction pour toutes les filieres ciblees : soit elles
       recoivent toutes le bareme, soit aucune. Un etat mixte laisserait le
       registrariat sans moyen de savoir ou il en est. */
    $dtb->beginTransaction();

    $parMention = [];
    $totalEcrites = 0;
    $totalCreees = 0;
    $totalPerso = 0;
    $totalPersoEffacees = 0;
    foreach ($cibles as $cible) {
        $bilan = $montants === []
            ? ['ecrites' => 0, 'creees' => 0]
            : baremeEnregistrer($dtb, $session_id, $cible, $semestre, $montants);

        if ($montantsPerso !== []) {
            $bilanPerso = fraisPersoEnregistrer($dtb, $session_id, $cible, $semestre, $montantsPerso);
            $bilan['perso_ecrites'] = $bilanPerso['ecrites'];
            $bilan['perso_effacees'] = $bilanPerso['supprimees'];
            $totalPerso += $bilanPerso['ecrites'];
            $totalPersoEffacees += $bilanPerso['supprimees'];
        }

        $parMention[$cible] = $bilan;
        $totalEcrites += $bilan['ecrites'];
        $totalCreees += $bilan['creees'];
    }

    if ($totalEcrites === 0 && $totalPerso === 0 && $totalPersoEffacees === 0) {
        $dtb->rollBack();
        bmRepondre(['ok' => false,
                    'message' => 'Aucune valeur exploitable dans les montants transmis.'], 422);
    }

    $dtb->commit();

    $nbCibles = count($cibles);
    /* Les frais supplementaires sont comptes a part : « 3 lignes » ne dirait
       pas si l'Alliance francaise a bien ete enregistree. */
    $motPerso = '';
    if ($totalPerso > 0 || $totalPersoEffacees > 0) {
        $bouts = [];
        if ($totalPerso > 0) { $bouts[] = $totalPerso . ' montant(s) de frais supplémentaires'; }
        if ($totalPersoEffacees > 0) { $bouts[] = $totalPersoEffacees . ' revenu(s) au barème de base'; }
        $motPerso = ' ' . implode(', ', $bouts) . '.';
    }
    $periode = $session_id === BAREME_BASE
        ? 'Barème de base'
        : 'Barème de ' . $session['session_name'] . ' ' . $session['session_year'];

    bmRepondre([
        'ok' => true,
        'action' => 'enregistrer',
        'session_id' => $session_id,
        'mention' => $mention,
        'mentions' => $cibles,
        'semestre' => $semestre,
        'ecrites' => $totalEcrites,
        'creees' => $totalCreees,
        'perso_ecrites' => $totalPerso,
        'perso_effacees' => $totalPersoEffacees,
        'par_mention' => $parMention,
        'surcharge' => $session_id !== BAREME_BASE,
        'message' => $nbCibles === 1
            ? $periode . ' enregistré (' . $totalEcrites . ' ligne(s)'
                . ($totalCreees > 0 ? ', dont ' . $totalCreees . ' nouvelle(s)' : '') . ').'
            : $periode . ' appliqué à ' . $nbCibles . ' filières ('
                . $totalEcrites . ' ligne(s)'
                . ($totalCreees > 0 ? ', dont ' . $totalCreees . ' nouvelle(s)' : '') . ').' . $motPerso,
    ]);

} catch (Throwable $e) {
    if ($dtb->inTransaction()) {
        $dtb->rollBack();
    }
    error_log('finance-bareme.save: ' . $e->getMessage());
    /* Message generique : le detail PDO peut reveler la structure de la base. */
    bmRepondre(['ok' => false, 'message' => "L'enregistrement a échoué."], 500);
}
