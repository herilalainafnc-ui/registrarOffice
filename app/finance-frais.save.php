<?php
/* =============================================================================
   Frais supplementaires — creation, renommage, activation

   Seules les DEFINITIONS passent ici. Les montants suivent le meme chemin que
   le reste du bareme (app/finance-bareme.save.php) : un frais supplementaire
   est une ligne du tableau comme les autres, et s'enregistre avec sa filiere.

   Place dans app/ : convention du projet pour ce qui repond des donnees plutot
   qu'une page.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();

require_once(__DIR__ . '/../data/finance.frais.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ffRepondre(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Meme exigence d'acces que FinanceController : niveau <= 3, soit superadmin,
   administrateur et registrariat. */
$niveau = (int) ($_SESSION['user_level'] ?? 99);
if ($niveau > 3) {
    ffRepondre(['ok' => false, 'message' => 'Accès refusé : privilèges insuffisants.'], 403);
}

/* Ces actions touchent le barème de tout l'établissement : le jeton protège
   contre une action déclenchée depuis un autre site. */
if (!function_exists('verify_csrf') || !verify_csrf()) {
    ffRepondre(['ok' => false, 'csrf_expire' => true,
                'message' => 'Jeton de sécurité invalide. Rechargez la page et réessayez.'], 403);
}

$action  = (string) ($_POST['action'] ?? '');
$libelle = (string) ($_POST['libelle'] ?? '');
$fraisId = isset($_POST['frais_id']) ? (int) $_POST['frais_id'] : 0;
$userId  = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

/* Reglages : a qui le frais s'applique, et a quels semestres. Les valeurs sont
   verifiees par le socle (fraisPersoConditions, fraisPersoSemestresValides). */
$condition = (string) ($_POST['condition'] ?? 'aucune');
$semestres = $_POST['semestres'] ?? '';

if (in_array($action, ['renommer', 'desactiver', 'reactiver', 'regler'], true) && $fraisId <= 0) {
    ffRepondre(['ok' => false, 'message' => 'Frais manquant.'], 400);
}

try {
    switch ($action) {
        case 'creer':
            /* fraisPersoCreer() reactive le frais qui porte deja ce libelle au
               lieu d'en creer un deuxieme : deux frais de meme nom seraient
               indistinguables sur la fiche de l'etudiant. */
            $id = fraisPersoCreer($dtb, $libelle, $userId, $condition, $semestres);
            ffRepondre([
                'ok' => true,
                'action' => 'creer',
                'frais_id' => $id,
                'message' => 'Frais ajouté. Saisissez son montant dans la ligne, filière par filière.',
            ]);
            // no break : ffRepondre() termine la requete

        case 'regler':
            if (!fraisPersoRegler($dtb, $fraisId, $condition, $semestres)) {
                ffRepondre(['ok' => false, 'message' => 'Frais introuvable, ou réglage inchangé.'], 422);
            }
            $libelles = fraisPersoConditions();
            $sem = fraisPersoSemestresValides($semestres);
            ffRepondre([
                'ok' => true,
                'action' => 'regler',
                'frais_id' => $fraisId,
                'message' => 'Réglage enregistré : ' . mb_strtolower($libelles[$condition])
                    . ', ' . ($sem === '' ? 'tous les semestres' : 'semestre(s) ' . $sem)
                    . '. Les inscriptions déjà enregistrées ne changent pas.',
            ]);

        case 'renommer':
            if (!fraisPersoRenommer($dtb, $fraisId, $libelle)) {
                ffRepondre(['ok' => false,
                            'message' => 'Frais introuvable, ou nom inchangé.'], 422);
            }
            /* Les lignes deja facturees gardent l'ancien libelle : la fiche
               d'un dossier ancien reste conforme a ce qui a ete signe. */
            ffRepondre([
                'ok' => true,
                'action' => 'renommer',
                'frais_id' => $fraisId,
                'message' => 'Frais renommé. Les inscriptions déjà enregistrées gardent l’ancien nom.',
            ]);

        case 'desactiver':
            if (!fraisPersoActiver($dtb, $fraisId, false)) {
                ffRepondre(['ok' => false, 'message' => 'Frais introuvable.'], 422);
            }
            ffRepondre([
                'ok' => true,
                'action' => 'desactiver',
                'frais_id' => $fraisId,
                'message' => 'Frais désactivé : plus facturé aux prochaines inscriptions. '
                    . 'Les montants et l’historique sont conservés.',
            ]);

        case 'reactiver':
            if (!fraisPersoActiver($dtb, $fraisId, true)) {
                ffRepondre(['ok' => false, 'message' => 'Frais introuvable.'], 422);
            }
            ffRepondre([
                'ok' => true,
                'action' => 'reactiver',
                'frais_id' => $fraisId,
                'message' => 'Frais réactivé, avec ses montants.',
            ]);

        default:
            ffRepondre(['ok' => false, 'message' => 'Action inconnue : ' . $action], 400);
    }
} catch (InvalidArgumentException $e) {
    // Libelle vide ou trop long : le message vient de fraisPersoLibelleValide().
    ffRepondre(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('finance-frais.save: ' . $e->getMessage());
    // Message generique : le detail PDO peut reveler la structure de la base.
    ffRepondre(['ok' => false, 'message' => "L'opération a échoué."], 500);
}
