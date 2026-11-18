<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php');?>
    <title>Autorisation de réinscription</title>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/reinscription.css?v=<?= @filemtime(__DIR__ . '/../css/reinscription.css') ?>">
</head>

<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <!-- TOP BAR --><?php require('../init/topbar.php');?>
<?php
/* =============================================================================
   Autorisation de réinscription

   Un étudiant ne peut se réinscrire que si deux conditions sont réunies : la
   caisse l'autorise, le registraire valide son dossier. Chaque rôle ne touche
   que sa colonne — voir data/reinscription.php.

   Le contrôle d'accès est fait par FinanceController (niveaux 1, 3 et 4).
   L'ancienne page le refaisait ici par un header('Location') placé APRÈS
   l'inclusion de la barre du haut : les en-têtes étaient déjà partis, la
   redirection ne pouvait pas avoir lieu.
   ========================================================================== */

require_once(__DIR__ . '/../../data/reinscription.php');

$niveau    = (int) ($rg_user['level'] ?? 99);
$colonnes  = rsnColonnes();
$permises  = rsnColonnesPermises($niveau);
$peutDossier = in_array('dossier_ok', $permises, true);
$peutAuth    = in_array('authorized_reinscription', $permises, true);

$stats       = rsnStatistiques($dtb);
$tableDispo  = rsnTableDisponible($dtb);

/* Réinitialisation globale : superadmin UNIQUEMENT. Il est le seul à voir le
   bouton, la fenêtre de confirmation et le bandeau d'annulation — les autres
   rôles gardent leurs interrupteurs, case par case. Le point d'entrée
   app/reinscription.save.php applique la même règle : masquer le bouton ne
   suffirait pas, la requête peut être forgée. */
$estSuperadmin = $niveau === 1;
$dernierLot    = $estSuperadmin ? rsnDernierLot($dtb) : null;

$etudiants = $dtb->query("
    SELECT e.id, e.student_id, e.student_nom, e.student_prenom, e.etude_envisage, e.etude_option,
           e.annee_etude, e.annee_scolaire, e.status,
           COALESCE(e.authorized_reinscription, 0) AS autorise, e.authorized_date,
           COALESCE(e.dossier_ok, 0) AS dossier, e.dossier_checked_date,
           TRIM(CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')))   AS auth_par,
           TRIM(CONCAT(COALESCE(ud.prenom,''), ' ', COALESCE(ud.nom,''))) AS dossier_par
      FROM tbl_2024_etudiant e
      LEFT JOIN compt_utilisateur u  ON u.id  = e.authorized_by
      LEFT JOIN compt_utilisateur ud ON ud.id = e.dossier_checked_by
     WHERE e.remove != 1
     ORDER BY e.student_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$filieres = [];
$annees = [];
foreach ($etudiants as $et) {
    if (trim((string) $et['etude_envisage']) !== '') { $filieres[(string) $et['etude_envisage']] = true; }
    if (trim((string) $et['annee_scolaire']) !== '') { $annees[(string) $et['annee_scolaire']] = true; }
}
$filieres = array_keys($filieres);
sort($filieres, SORT_NATURAL | SORT_FLAG_CASE);
$annees = array_keys($annees);
rsort($annees);

/** Niveau lisible : L1-L3, M1-M2, remise à niveau. */
function rsnNiveau($n): string
{
    $n = (int) $n;
    if ($n === 0) { return 'Remise à niveau'; }

    return $n <= 3 ? 'Licence ' . $n : 'Master ' . ($n - 3);
}

/** « Prénom Nom · 12/09/2026 », ou chaîne vide. */
function rsnTrace(string $par, ?string $le): string
{
    if ($par === '') { return ''; }

    return $par . ($le ? ' · ' . date('d/m/Y', strtotime($le)) : '');
}

$etatLibelles = [
    'prets'           => 'Prêts à se réinscrire',
    'attente_caisse'  => 'En attente de la caisse',
    'attente_dossier' => 'En attente du dossier',
    'aucun'           => 'Ni l\'un ni l\'autre',
];
?>
    <!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

    <div class="w-full flex flex-col lg:flex-row">

        <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back flex-1 overflow-y-auto">
                <div class="rsn-page">

                    <div class="rsn-entete">
                        <div class="rsn-entete__icone"><i class="bi bi-person-check-fill" aria-hidden="true"></i></div>
                        <div>
                            <h1 class="rsn-titre">Autorisation de réinscription</h1>
                            <p class="rsn-sous">
                                Un étudiant peut se réinscrire quand la caisse l'autorise
                                <em>et</em> que le registraire a validé son dossier.
                            </p>
                        </div>
                        <?php /* Ce que CE compte peut cocher, dit une fois en tête
                                 plutôt que découvert case par case. */ ?>
                        <div class="rsn-roles" aria-label="Vos droits sur cette page">
                            <span class="rsn-role<?= $peutDossier ? ' rsn-role--oui' : '' ?>">
                                <i class="bi bi-<?= $peutDossier ? 'pencil-fill' : 'eye' ?>" aria-hidden="true"></i>
                                Dossier : <?= $peutDossier ? 'modifiable' : 'lecture seule' ?>
                            </span>
                            <span class="rsn-role<?= $peutAuth ? ' rsn-role--oui' : '' ?>">
                                <i class="bi bi-<?= $peutAuth ? 'pencil-fill' : 'eye' ?>" aria-hidden="true"></i>
                                Caisse : <?= $peutAuth ? 'modifiable' : 'lecture seule' ?>
                            </span>
                        </div>
                    </div>

                    <?php /* Bandeau d'annulation. Rendu côté serveur s'il existe un lot
                             récent : une réinitialisation faite par erreur reste
                             rattrapable après un rechargement, ou depuis un autre poste. */ ?>
<?php if ($estSuperadmin) { ?>
                    <div class="rsn-annulation" id="rsnBandeau" role="status" <?= $dernierLot === null ? 'hidden' : '' ?>>
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        <span id="rsnBandeauTexte">
<?php if ($dernierLot !== null) { ?>
                            <b>Dernière réinitialisation</b> —
                            <?= (int) $dernierLot['lignes'] ?> case(s) remise(s) à « non coché »
                            <?= $dernierLot['par_nom'] !== '' ? 'par ' . e($dernierLot['par_nom']) : '' ?>
                            le <?= e(date('d/m/Y à H:i', strtotime((string) $dernierLot['fait_le']))) ?>.
<?php } ?>
                        </span>
                        <button type="button" class="rsn-btn" id="rsnAnnulerLot"
                                data-lot="<?= $dernierLot !== null ? e((string) $dernierLot['lot']) : '' ?>">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Annuler cette réinitialisation
                        </button>
                    </div>
<?php } ?>

                    <?php /* Les quatre états s'excluent et totalisent l'effectif : ils
                             servent à la fois de tableau de bord et de filtre. */ ?>
                    <div class="rsn-etats" role="group" aria-label="Filtrer par état">
                        <button type="button" class="rsn-etat" data-filtre="tous" aria-pressed="true">
                            <span class="rsn-etat__chiffre" data-stat="total"><?= number_format($stats['total'], 0, ',', ' ') ?></span>
                            <span class="rsn-etat__libelle">Tous les étudiants</span>
                        </button>
<?php
$points = ['prets' => 'prets', 'attente_caisse' => 'caisse', 'attente_dossier' => 'dossier', 'aucun' => 'aucun'];
foreach ($etatLibelles as $cle => $libelle) { ?>
                        <button type="button" class="rsn-etat" data-filtre="<?= $cle ?>" aria-pressed="false">
                            <span class="rsn-etat__chiffre" data-stat="<?= $cle ?>"><?= number_format($stats[$cle], 0, ',', ' ') ?></span>
                            <span class="rsn-etat__libelle">
                                <span class="rsn-point rsn-point--<?= $points[$cle] ?>" aria-hidden="true"></span>
                                <?= e($libelle) ?>
                            </span>
                        </button>
<?php } ?>
                    </div>

                    <div class="rsn-outils">
                        <div class="rsn-champ rsn-champ--recherche">
                            <label for="rsnRecherche">Rechercher</label>
                            <div class="rsn-recherche">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input type="search" id="rsnRecherche" class="rsn-input"
                                       placeholder="Matricule, nom, prénom ou filière" autocomplete="off">
                            </div>
                        </div>
                        <div class="rsn-champ">
                            <label for="rsnFiliere">Filière</label>
                            <select id="rsnFiliere" class="rsn-input">
                                <option value="">Toutes</option>
                                <?php foreach ($filieres as $f) { ?>
                                    <option value="<?= e($f) ?>"><?= e($f) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="rsn-champ">
                            <label for="rsnAnnee">Année scolaire</label>
                            <select id="rsnAnnee" class="rsn-input">
                                <option value="">Toutes</option>
                                <?php foreach ($annees as $an) { ?>
                                    <option value="<?= e($an) ?>"><?= e($an) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <button type="button" class="rsn-btn" id="rsnEffacer">
                            <i class="bi bi-x-lg" aria-hidden="true"></i> Effacer les filtres
                        </button>
                        <span class="rsn-compte" id="rsnCompte" aria-live="polite"></span>

<?php if ($estSuperadmin) { ?>
                        <button type="button" class="rsn-btn rsn-btn--danger" id="rsnOuvrirReinit"
                                <?= $tableDispo ? '' : 'disabled' ?>
                                title="<?= $tableDispo
                                    ? 'Remettre tous les étudiants à « non coché », par exemple au changement d\'année'
                                    : 'Indisponible : la table d\'annulation n\'existe pas encore sur cette base' ?>">
                            <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Tout réinitialiser
                        </button>
<?php } ?>
                    </div>

                    <div class="rsn-cadre">
                        <table class="rsn-table">
                            <thead>
                                <tr>
                                    <th scope="col">Étudiant</th>
                                    <th scope="col">Filière</th>
                                    <th scope="col">Résidence</th>
                                    <th scope="col">Année</th>
                                    <th scope="col" class="rsn-centre">Dossier <span class="sr-only">(registraire)</span></th>
                                    <th scope="col" class="rsn-centre">Autorisation <span class="sr-only">(caisse)</span></th>
                                </tr>
                            </thead>
                            <?php /* Les lignes sont dessinées par src/js/reinscription.js,
                                     à partir des données en bas de page, par tranches de 100. */ ?>
                            <tbody id="rsnCorps"></tbody>
                        </table>
                        <div class="rsn-suite" id="rsnSuite" hidden>
                            <button type="button" class="rsn-btn" id="rsnSuiteBtn">Afficher la suite</button>
                        </div>
                        <noscript>
                            <div class="rsn-vide">La liste a besoin de JavaScript pour s'afficher.</div>
                        </noscript>
                        <div class="rsn-vide" id="rsnVide" hidden>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Aucun étudiant ne correspond à ces filtres.
                        </div>
                    </div>

                </div>
            </div>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>

<?php if ($estSuperadmin && $tableDispo) { ?>
<div class="rsn-voile rsn-page" id="rsnVoile" hidden>
    <div class="rsn-dialogue" role="dialog" aria-modal="true" aria-labelledby="rsnDialogueTitre">
        <h2 id="rsnDialogueTitre">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            Tout réinitialiser
        </h2>
        <p>
            Les colonnes choisies seront remises à « non coché » pour <b>tous</b> les étudiants.
            Chaque changement est inscrit dans l'historique de l'étudiant, et la réinitialisation
            reste annulable depuis le bandeau pendant sept jours.
        </p>

        <div class="rsn-choix">
<?php foreach ($colonnes as $col => $def) {
    $ok = in_array($col, $permises, true); ?>
            <label class="rsn-option<?= $ok ? '' : ' rsn-option--ferme' ?>">
                <input type="checkbox" class="js-rsn-colonne" value="<?= e($col) ?>"
                       <?= $ok ? 'checked' : 'disabled' ?>>
                <span>
                    <?= e($def['libelle']) ?>
                    <?php if (!$ok) { ?><br><small>réservé à un autre rôle</small><?php } ?>
                </span>
                <span class="rsn-option__nombre" data-apercu="<?= e($col) ?>">…</span>
            </label>
<?php } ?>
        </div>

        <div class="rsn-portee" id="rsnPortee" aria-live="polite">Calcul de la portée…</div>

        <div class="rsn-confirmation">
            <label for="rsnConfirmation">Pour confirmer, saisissez <code>RÉINITIALISER</code></label>
            <input type="text" id="rsnConfirmation" class="rsn-input" autocomplete="off"
                   spellcheck="false" autocapitalize="characters">
        </div>

        <div class="rsn-dialogue__actions">
            <button type="button" class="rsn-btn" id="rsnFermer">Annuler</button>
            <button type="button" class="rsn-btn rsn-btn--plein-danger" id="rsnConfirmer" disabled>
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Réinitialiser
            </button>
        </div>
    </div>
</div>
<?php } ?>

<script type="application/json" id="rsnConfig"><?= json_encode(
    ['permises' => $permises],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<?php
/* Les étudiants, en données plutôt qu'en lignes HTML : la page en écrivait
   2 120 d'un coup (2,7 Mo, 42 000 nœuds, plus de 4 s de calcul de styles et de
   mise en page avant de pouvoir s'en servir). Le script filtre ces données et
   ne dessine que les lignes retenues, par tranches.
   Une ligne : [id, matricule, nom, filière, parcours, niveau, résidence,
   année, autorisé, dossier, trace dossier, trace autorisation]. */
$donnees = [];
foreach ($etudiants as $et) {
    $donnees[] = [
        (int) $et['id'],
        (string) $et['student_id'],
        strtoupper((string) $et['student_nom']) . ' ' . $et['student_prenom'],
        (string) $et['etude_envisage'],
        (string) $et['etude_option'],
        rsnNiveau($et['annee_etude']),
        (string) $et['status'],
        (string) $et['annee_scolaire'],
        (int) $et['autorise'] === 1 ? 1 : 0,
        (int) $et['dossier'] === 1 ? 1 : 0,
        rsnTrace((string) $et['dossier_par'], $et['dossier_checked_date']),
        rsnTrace((string) $et['auth_par'], $et['authorized_date']),
    ];
}
?>
<script type="application/json" id="rsnDonnees"><?= json_encode(
    $donnees,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
) ?></script>
<script src="<?=$app_base?>/src/js/reinscription.js?v=<?= @filemtime(__DIR__ . '/../js/reinscription.js') ?>"></script>
</body>
</html>
