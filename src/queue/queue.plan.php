<?php
/* =============================================================================
   Plan du bureau (/queue/plan) — superadmin seul (QueueController::plan ;
   le fichier verifie aussi lui-meme, car il est joignable par son adresse).

   Le plan que les etudiants ouvrent sur l'ecran public de la file d'attente
   (/queue/display, bouton « Plan du bureau ») : le batiment, ses pieces et
   leurs portes, l'entree, et les etapes du parcours dans l'ordre. Tout se
   regle ici : dimensions en metres, portes, nombre et ordre des etapes. Le
   parcours se recalcule a chaque changement.
   Donnees : data/plan.bureau.php (t_queue_plan). Dessin : src/js/plan-bureau.js
   et src/css/plan-bureau.css ; edition : src/js/plan-bureau-editeur.js et
   src/css/queue.plan.css. Prefixe « pe- ».
   ========================================================================== */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(); // connecte, sinon /login (401 en AJAX)
/* Superadmin seul, meme par l'adresse du fichier. Le niveau se lit dans la
   session comme le fait QueueController : par le routeur, le middleware n'a
   pas encore sa base ici et hasLevel() repondrait non a tout le monde. */
if ((int) ($_SESSION['user_level'] ?? 99) !== ROLE_SUPERADMIN) {
    http_response_code(403);
    if (estRequeteAjax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Le plan du bureau ne se modifie que par le superadmin.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<h1>403 - Accès refusé</h1><p>Le plan du bureau ne se modifie que par le superadmin.</p>';
    }
    exit;
}
require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/plan.bureau.php');

$peUser = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

/* ---------------------------------------------------------------- enregistrer */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    /* Jeton refuse en 400 et non en 403 : src/js/session.js afficherait
       « Acces refuse » ; le script reprend un jeton frais et renvoie. */
    if (function_exists('verify_csrf') && !verify_csrf()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'csrf', 'message' => 'Jeton de sécurité expiré : rechargez la page.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'enregistrer') {
            $etat = planEnregistrer($dtb, json_decode((string) ($_POST['plan'] ?? ''), true), $peUser);
            $message = 'Plan enregistré : il est déjà sur l’écran public.';
        } elseif ($action === 'restaurer') {
            $etat = planRestaurer($dtb, $peUser);
            $message = 'Version précédente remise en service.';
        } else {
            throw new InvalidArgumentException('Action inconnue.');
        }
        echo json_encode(['success' => true, 'message' => $message] + $etat, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Enregistrement impossible : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* ------------------------------------------------------------------ afficher */
$peTable = planTablePresente($dtb);
$peEtat = planLire($dtb);
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Plan du bureau</title>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/plan-bureau.css?v=<?=@filemtime(__DIR__ . '/../css/plan-bureau.css')?>">
    <link rel="stylesheet" href="<?=$app_base?>/src/css/queue.plan.css?v=<?=@filemtime(__DIR__ . '/../css/queue.plan.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php'); ?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back flex-1 overflow-y-auto">
                <div class="pe" id="pePage">

                    <header class="pe-tete">
                        <span class="pe-tete__icone" aria-hidden="true"><i class="bi bi-map"></i></span>
                        <div class="pe-tete__texte">
                            <h1>Plan du bureau</h1>
                            <p>Les pièces, l'entrée et les étapes du parcours des étudiants. Le plan s'ouvre sur l'écran public de la file d'attente, avec le bouton « Plan du bureau ». Les mesures sont en mètres ; le parcours se recalcule à chaque changement.</p>
                        </div>
                        <a class="pe-bouton pe-bouton--discret" href="<?=$app_base?>/queue/display#plan" target="_blank" rel="noopener" id="peVoir">
                            <i class="bi bi-display" aria-hidden="true"></i><span>Voir sur l'écran public</span><i class="bi bi-box-arrow-up-right pe-petit" aria-hidden="true"></i>
                        </a>
                    </header>

                    <?php if (!$peTable): ?>
                    <p class="pe-avis pe-avis--erreur" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><span>La table du plan n'existe pas encore sur ce serveur&nbsp;: l'écran public montre le plan par défaut et rien ne peut être enregistré. Appliquez <code>data/migration_plan_bureau.sql</code>.</span></p>
                    <?php endif; ?>

                    <div class="pe-barre" role="toolbar" aria-label="Plan">
                        <button type="button" class="pe-bouton pe-bouton--discret" id="peAjouter"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Pièce</span></button>
                        <span class="pe-barre__sep" aria-hidden="true"></span>
                        <button type="button" class="pe-icone" id="peAnnuler" aria-label="Annuler (Ctrl+Z)" title="Annuler (Ctrl+Z)" disabled><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                        <button type="button" class="pe-icone" id="peRetablir" aria-label="Rétablir (Ctrl+Y)" title="Rétablir (Ctrl+Y)" disabled><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                        <span class="pe-etat" id="peEtat" role="status"></span>
                        <button type="button" class="pe-bouton pe-bouton--discret" id="peRestaurer"<?= $peEtat['precedent'] ? '' : ' hidden' ?>><i class="bi bi-clock-history" aria-hidden="true"></i><span>Version précédente</span></button>
                        <button type="button" class="pe-bouton" id="peEnregistrer"<?= $peTable ? '' : ' disabled' ?>><i class="bi bi-check2" aria-hidden="true"></i><span>Enregistrer</span></button>
                    </div>

                    <ul class="pe-alertes" id="peAlertes" hidden></ul>

                    <div class="pe-corps">
                        <section class="pe-dessin" aria-label="Plan">
                            <div class="pe-dessin__cadre" id="peCadre">
                                <svg id="peSvg" role="application" aria-label="Plan du bureau : cliquez une pièce pour la modifier"></svg>
                            </div>
                            <p class="pe-aide">Glissez une pièce pour la déplacer, ses poignées pour la redimensionner ; elle s'aligne sur les murs voisins. Pièce choisie&nbsp;: flèches 10&nbsp;cm (Maj&nbsp;: 1&nbsp;m), Suppr pour la retirer.</p>
                        </section>

                        <aside class="pe-panneau">
                            <div class="pe-onglets" role="tablist" aria-label="Réglages">
                                <button type="button" role="tab" id="peOngletEtapes" aria-controls="pePanEtapes" aria-selected="true">Étapes</button>
                                <button type="button" role="tab" id="peOngletPiece" aria-controls="pePanPiece" aria-selected="false" tabindex="-1">Pièce</button>
                                <button type="button" role="tab" id="peOngletBatiment" aria-controls="pePanBatiment" aria-selected="false" tabindex="-1">Bâtiment</button>
                            </div>

                            <div class="pe-pan" role="tabpanel" id="pePanEtapes" aria-labelledby="peOngletEtapes">
                                <p class="pe-note">Dans l'ordre du parcours. Le numéro suit l'ordre ; le nom et la précision s'affichent aux étudiants.</p>
                                <ol class="pe-etapes" id="peEtapes"></ol>
                                <button type="button" class="pe-bouton pe-bouton--discret pe-bouton--plein" id="peAjouterEtape"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Ajouter une étape</span></button>
                            </div>

                            <div class="pe-pan" role="tabpanel" id="pePanPiece" aria-labelledby="peOngletPiece" hidden>
                                <div id="pePieceVide">
                                    <p class="pe-note">Cliquez une pièce sur le plan, ou choisissez-la ici.</p>
                                    <ul class="pe-liste-pieces" id="peListePieces"></ul>
                                </div>
                                <form id="pePiece" class="pe-form" hidden autocomplete="off">
                                    <div class="pe-ligne">
                                        <label class="pe-champ pe-champ--large"><span>Nom</span><input type="text" id="pePieceNom" maxlength="60" placeholder="Ex. : Caisse"></label>
                                        <label class="pe-champ pe-champ--code"><span>Code</span><input type="text" id="pePieceCode" maxlength="6" placeholder="A"></label>
                                    </div>
                                    <fieldset class="pe-choix">
                                        <legend>Type</legend>
                                        <label><input type="radio" name="peType" value="salle"> Salle <small>murs et portes</small></label>
                                        <label><input type="radio" name="peType" value="espace"> Espace ouvert <small>hall, couloir</small></label>
                                    </fieldset>
                                    <div class="pe-grille4">
                                        <label class="pe-champ"><span>X</span><input type="number" id="pePieceX" step="0.1" min="0" inputmode="decimal"></label>
                                        <label class="pe-champ"><span>Y</span><input type="number" id="pePieceY" step="0.1" min="0" inputmode="decimal"></label>
                                        <label class="pe-champ"><span>Largeur</span><input type="number" id="pePieceL" step="0.1" min="0.5" inputmode="decimal"></label>
                                        <label class="pe-champ"><span>Profondeur</span><input type="number" id="pePieceP" step="0.1" min="0.5" inputmode="decimal"></label>
                                    </div>
                                    <p class="pe-note pe-note--petite">En mètres. X et Y : le coin en haut à gauche, depuis le coin en haut à gauche du bâtiment.</p>
                                    <div id="pePortesBloc">
                                        <h3 class="pe-sous-titre">Portes</h3>
                                        <ul class="pe-portes" id="pePortes"></ul>
                                        <button type="button" class="pe-lien" id="peAjouterPorte"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Ajouter une porte</span></button>
                                    </div>
                                    <div class="pe-actions">
                                        <button type="button" class="pe-bouton pe-bouton--discret pe-bouton--petit" id="peDupliquer"><i class="bi bi-copy" aria-hidden="true"></i><span>Dupliquer</span></button>
                                        <button type="button" class="pe-bouton pe-bouton--danger pe-bouton--petit" id="peSupprimer"><i class="bi bi-trash3" aria-hidden="true"></i><span>Supprimer</span></button>
                                        <button type="button" class="pe-lien pe-lien--fin" id="peDeselectionner">Toutes les pièces</button>
                                    </div>
                                </form>
                            </div>

                            <div class="pe-pan" role="tabpanel" id="pePanBatiment" aria-labelledby="peOngletBatiment" hidden>
                                <form class="pe-form" id="peBatiment" autocomplete="off">
                                    <h3 class="pe-sous-titre">Bâtiment</h3>
                                    <div class="pe-grille2">
                                        <label class="pe-champ"><span>Largeur</span><input type="number" id="peBatL" step="0.1" min="3" max="200" inputmode="decimal"></label>
                                        <label class="pe-champ"><span>Profondeur</span><input type="number" id="peBatP" step="0.1" min="3" max="200" inputmode="decimal"></label>
                                    </div>
                                    <h3 class="pe-sous-titre">Entrée</h3>
                                    <div class="pe-grille3">
                                        <label class="pe-champ"><span>Côté</span>
                                            <select id="peEntreeCote"><option value="bas">Bas</option><option value="haut">Haut</option><option value="gauche">Gauche</option><option value="droite">Droite</option></select>
                                        </label>
                                        <label class="pe-champ"><span>Milieu à</span><input type="number" id="peEntreePos" step="0.1" min="0" inputmode="decimal"></label>
                                        <label class="pe-champ"><span>Largeur</span><input type="number" id="peEntreeL" step="0.1" min="0.6" max="12" inputmode="decimal"></label>
                                    </div>
                                    <p class="pe-note pe-note--petite">En mètres. « Milieu à » : depuis la gauche du côté (le haut, pour un côté gauche ou droit).</p>
                                </form>
                            </div>

                            <p class="pe-trace" id="peTrace"></p>
                        </aside>
                    </div>
                </div>
            </div>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>

<script type="application/json" id="peDonnees"><?= json_encode($peEtat + ['table' => $peTable], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?=$app_base?>/src/js/plan-bureau.js?v=<?=@filemtime(__DIR__ . '/../js/plan-bureau.js')?>"></script>
<script src="<?=$app_base?>/src/js/plan-bureau-editeur.js?v=<?=@filemtime(__DIR__ . '/../js/plan-bureau-editeur.js')?>"></script>
</body>
</html>
