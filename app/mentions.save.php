<?php
/* =============================================================================
   Écriture des mentions (table filiere) — réponse JSON

   Point d'entrée de src/settings.mentions.php.

   SUPERADMIN UNIQUEMENT. Le sigle d'une mention sert de clé dans cinq tables
   et son libellé rattache les fiches étudiantes : plus de quatre mille lignes
   en dépendent, sans aucune contrainte de clé étrangère pour les protéger. Un
   renommage maladroit ne casse rien visiblement — il rend seulement des
   inscriptions impossibles, des semaines plus tard.

   Placé dans app/ : convention du projet pour ce qui répond des données plutôt
   qu'une page, et src/js/transitions.js s'appuie dessus pour ne pas jouer de
   transition sur ces adresses.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/mentions.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function mnRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    mnRepondre(['ok' => false, 'message' => 'Méthode non autorisée.'], 405);
}

/* Même verrou que le contrôleur : niveau 1. Ce point d'entrée vit hors du
   routeur, la garde de la page ne le couvrirait pas. */
if ((int) ($_SESSION['user_level'] ?? 99) !== 1) {
    mnRepondre(['ok' => false,
                'message' => 'Accès refusé : seul un super administrateur peut modifier les mentions.'], 403);
}

/* 400 plutôt que 403 : src/js/session.js intercepte les 403 pour annoncer un
   refus de privilèges et rediriger, ce qui serait faux pour un jeton expiré.
   Et pas 419 : ce code n'est pas enregistré à l'IANA et ce serveur le
   transforme en 500. Le motif voyage dans la charge utile. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    mnRepondre(['ok' => false, 'csrf_expire' => true,
                'message' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.'], 400);
}

$action = (string) ($_POST['action'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);

/* Seules les colonnes déclarées par mentionsChamps() et
   mentionParcoursChamps() sont reprises : aucun nom de colonne ne vient du
   client. Les deux jeux sont séparés — filiere et filiere_parcours n'ont pas
   les mêmes colonnes, et « description » existe des deux côtés avec un sens
   différent. */
$valeurs = [];
foreach (array_keys(mentionsChamps()) as $col) {
    if (isset($_POST[$col])) {
        $valeurs[$col] = (string) $_POST[$col];
    }
}

$valeursParcours = [];
foreach (array_keys(mentionParcoursChamps()) as $col) {
    if (isset($_POST[$col])) {
        $valeursParcours[$col] = (string) $_POST[$col];
    }
}

/* Le cycle n'est pas un libelle : il ne figure pas dans mentionParcoursChamps(),
   qui decrit les champs texte du formulaire. Sa valeur est bornee par
   mentionParcoursCyclesValides(). */
if (isset($_POST['cycles'])) {
    $valeursParcours['cycles'] = (string) $_POST['cycles'];
}

try {
    switch ($action) {

        case 'creer':
            $resultat = mentionsCreer($dtb, (string) ($_POST['sigle'] ?? ''), $valeurs, $userId);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        case 'enregistrer':
            $sigle = strtoupper(trim((string) ($_POST['sigle'] ?? '')));
            if ($sigle === '') {
                mnRepondre(['ok' => false, 'message' => 'Mention manquante.'], 400);
            }
            $resultat = mentionsEnregistrer($dtb, $sigle, $valeurs, $userId);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        case 'supprimer':
            $sigle = strtoupper(trim((string) ($_POST['sigle'] ?? '')));
            if ($sigle === '') {
                mnRepondre(['ok' => false, 'message' => 'Mention manquante.'], 400);
            }
            $resultat = mentionsSupprimer($dtb, $sigle);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 409);

        case 'ouverture':
            /* Ouvre ou ferme une mention aux nouvelles inscriptions.
               Reversible et sans effet sur les donnees : c'est la seule action
               possible sur une mention qui porte des inscriptions, la
               suppression restant fermee tant qu'une donnee s'y rattache. */
            $sigle = strtoupper(trim((string) ($_POST['sigle'] ?? '')));
            if ($sigle === '') {
                mnRepondre(['ok' => false, 'message' => 'Mention manquante.'], 400);
            }
            $ouvrir = (string) ($_POST['ouverte'] ?? '') === '1';
            $resultat = mentionsBasculerOuverture($dtb, $sigle, $ouvrir, $userId);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        /* ================= PARCOURS D'UNE MENTION ===================== */

        case 'parcours_creer':
            $sigle = strtoupper(trim((string) ($_POST['sigle'] ?? '')));
            $code = strtoupper(trim((string) ($_POST['shortcode'] ?? '')));
            if ($sigle === '') {
                mnRepondre(['ok' => false, 'message' => 'Mention manquante.'], 400);
            }
            $resultat = mentionParcoursCreer($dtb, $sigle, $code, $valeursParcours, $userId);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        case 'parcours_enregistrer':
            $code = strtoupper(trim((string) ($_POST['shortcode'] ?? '')));
            if ($code === '') {
                mnRepondre(['ok' => false, 'message' => 'Parcours manquant.'], 400);
            }
            $resultat = mentionParcoursEnregistrer($dtb, $code, $valeursParcours, $userId);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 422);

        case 'parcours_supprimer':
            $code = strtoupper(trim((string) ($_POST['shortcode'] ?? '')));
            if ($code === '') {
                mnRepondre(['ok' => false, 'message' => 'Parcours manquant.'], 400);
            }
            $resultat = mentionParcoursSupprimer($dtb, $code);
            mnRepondre($resultat, $resultat['ok'] ? 200 : 409);

        default:
            mnRepondre(['ok' => false, 'message' => 'Action inconnue.'], 400);
    }

} catch (Throwable $e) {
    if ($dtb->inTransaction()) {
        $dtb->rollBack();
    }
    error_log('mentions.save: ' . $e->getMessage());
    /* Message générique : le détail PDO peut révéler la structure de la base. */
    mnRepondre(['ok' => false, 'message' => "L'enregistrement a échoué."], 500);
}
