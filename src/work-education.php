<?php
/* =============================================================================
   Work Education (/work-education) — la tache de chaque etudiant inscrit,
   session par session. Responsable du Work Education (niveau 9, dont c'est la
   seule page) et niveaux 1 a 3 (WorkEducationController::index).

   La tache s'imprime sur la fiche d'inscription, dans le champ « Work
   education » (src/extenssionPrint/fiche_inscription.php). Les etudiants de
   Master n'en ont pas : ils ne sont pas listes.
   Donnees : data/work.education.php. Prefixe « we- ».
   Styles : src/css/work-education.css ; comportement : src/js/work-education.js
   (liste, tri, filtres, saisie ligne par ligne ou pour une selection,
   enregistrement sans recharger). Par defaut, les derniers inscrits d abord,
   par jour d inscription.
   ========================================================================== */
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/work.education.php');

/* ---------------------------------------------------------------- enregistrer */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    /* Jeton refuse en 400 et non en 403 : src/js/session.js afficherait
       « Acces refuse » ; src/js/work-education.js reprend un jeton frais et renvoie. */
    if (function_exists('verify_csrf') && !verify_csrf()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'csrf', 'message' => 'Jeton de sécurité expiré : rechargez la page.']);
        exit;
    }
    try {
        $weMatricules = is_array($_POST['etudiants'] ?? null) ? $_POST['etudiants'] : [];
        $weResultat = weAttribuer($dtb, (int)($_POST['session_id'] ?? 0), $weMatricules, (string)($_POST['tache'] ?? ''),
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        /* (object) : des matricules numeriques deviendraient sinon une liste JSON. */
        /* signature : l'empreinte apres l'enregistrement, pour que la page ne
           recharge pas sa liste a cause de ses propres taches. */
        echo json_encode(['success' => true, 'changes' => $weResultat['changes'], 'taches' => (object)$weResultat['taches'],
            'signature' => weSignature($dtb, (int)($_POST['session_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
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

/* Les sessions, de la plus ancienne a la plus recente (pour « precedente » et
   « suivante ») ; dans la liste, par annee, la plus recente d'abord. */
$weRang = [1 => 0, 3 => 1, 2 => 2, 4 => 3];
$weSessions = $dtb->query('SELECT session_id, session_name, session_year, session_semester FROM t_2023_session')->fetchAll(PDO::FETCH_ASSOC);
usort($weSessions, static function ($a, $b) use ($weRang) {
    return [$a['session_year'], $weRang[(int)$a['session_semester']] ?? 9, (int)$a['session_id']]
       <=> [$b['session_year'], $weRang[(int)$b['session_semester']] ?? 9, (int)$b['session_id']];
});
$weParId = array_column($weSessions, null, 'session_id');

/** Ce que la page montre d'une session : elle, ses voisines, ses inscrits de Licence. */
$weEtat = static function (int $id) use ($dtb, $weSessions, $weParId): array {
    $s = $weParId[$id];
    $rang = array_search($id, array_map('intval', array_column($weSessions, 'session_id')), true);
    $voisine = static fn ($v) => $v ? ['id' => (int)$v['session_id'], 'libelle' => $v['session_name'] . ' ' . $v['session_year']] : null;
    $liste = weEtudiants($dtb, $id);
    return [
        'session'    => ['id' => $id, 'nom' => $s['session_name'], 'annee' => $s['session_year'], 'libelle' => $s['session_name'] . ' ' . $s['session_year']],
        'precedente' => $voisine($rang !== false && $rang > 0 ? $weSessions[$rang - 1] : null),
        'suivante'   => $voisine($rang !== false && $rang < count($weSessions) - 1 ? $weSessions[$rang + 1] : null),
        'etudiants'  => $liste['etudiants'],
        'master'     => $liste['master'],
        'signature'  => weSignature($dtb, $id),
    ];
};

/* L'empreinte de la session, demandee toutes les 15 secondes par la page :
   elle ne recharge sa liste que si un etudiant s'est inscrit (ou a ete
   retire, ou a recu une tache ailleurs) depuis. */
if (isset($_GET['signature'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $weDemandee = (int)($_GET['session_id'] ?? 0);
    if (!isset($weParId[$weDemandee])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session inconnue.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'signature' => weSignature($dtb, $weDemandee)]);
    exit;
}

/* Une autre session, demandee par la page sans la recharger
   (src/js/work-education.js : fleches, liste des sessions, retour arriere). */
if (isset($_GET['donnees'])) {
    header('Content-Type: application/json; charset=utf-8');
    $weDemandee = (int)($_GET['session_id'] ?? 0);
    if (!isset($weParId[$weDemandee])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session inconnue.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true] + $weEtat($weDemandee), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$weInscrits = $dtb->query('SELECT session_id, COUNT(DISTINCT student_id) FROM t_2024_inscription_session WHERE COALESCE(`remove`, 0) != 1 GROUP BY session_id')->fetchAll(PDO::FETCH_KEY_PAIR);

/* La session affichee : celle demandee, sinon celle ou l'on inscrit en ce
   moment (la derniere inscription enregistree), sinon la session en cours. */
$weSessionId = (int)($_GET['session_id'] ?? 0);
if (!isset($weParId[$weSessionId])) {
    $weSessionId = (int)$dtb->query('SELECT session_id FROM t_2024_inscription_session WHERE COALESCE(`remove`, 0) != 1 AND session_id > 0 ORDER BY id DESC LIMIT 1')->fetchColumn();
}
if (!isset($weParId[$weSessionId])) {
    $weSessionId = (int)$dtb->query('SELECT session_id FROM t_2023_session WHERE selected = 1 LIMIT 1')->fetchColumn();
}
if (!isset($weParId[$weSessionId]) && $weSessions) {
    $weSessionId = (int)end($weSessions)['session_id'];
}
$weTable = weTablePresente($dtb);
$weDepart = isset($weParId[$weSessionId]) ? $weEtat($weSessionId) : null;

/* Le premier affichage ; le script le refait a chaque changement de session. */
$weTotal = $weDepart ? count($weDepart['etudiants']) : 0;
$weAvec = $weDepart ? count(array_filter($weDepart['etudiants'], static fn ($e) => $e['tache'] !== '')) : 0;
$weFleche = static function (?array $v, string $sens): string {
    $nom = $sens === 'precedente' ? 'Session précédente' : 'Session suivante';
    $icone = $sens === 'precedente' ? 'bi-chevron-left' : 'bi-chevron-right';
    return '<a class="we-pas' . ($v ? '' : ' is-inactif') . '" data-sens="' . $sens . '" data-pt-skip="1"'
        . ($v ? ' href="?session_id=' . $v['id'] . '" data-session="' . $v['id'] . '"' : ' aria-disabled="true"')
        . ' aria-label="' . htmlspecialchars($nom . ($v ? ' : ' . $v['libelle'] : '')) . '"><i class="bi ' . $icone . '" aria-hidden="true"></i></a>';
};
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Work Education</title>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/work-education.css?v=<?=@filemtime(__DIR__ . '/css/work-education.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php'); ?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back flex-1 overflow-y-auto">
                <div class="we we-entree" id="wePage">

                    <header class="we-tete" style="--i: 0">
                        <span class="we-tete__icone" aria-hidden="true"><i class="bi bi-tools"></i></span>
                        <div class="we-tete__texte">
                            <h1>Work Education</h1>
                            <p>La tâche de chaque étudiant inscrit, session par session. Elle s'imprime sur sa fiche d'inscription, dans le champ «&nbsp;Work education&nbsp;».</p>
                        </div>
                        <?php if ($weTable && $weDepart): ?>
                        <button type="button" class="we-bouton we-bouton--discret we-export" id="weExport" aria-label="Exporter la liste en PDF" title="La liste en PDF : par service, mention, niveau, session ou année" aria-haspopup="dialog">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span class="we-libelle">Exporter en PDF</span>
                        </button>
                        <?php endif; ?>
                    </header>

                    <?php if (!$weTable): ?>
                    <p class="we-avis we-avis--erreur" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><span>La table du Work Education n'existe pas encore sur ce serveur&nbsp;: appliquez <code>data/migration_work_education.sql</code>.</span></p>
                    <?php endif; ?>

                    <?php if ($weDepart): ?>
                    <section class="we-session" aria-label="Session" style="--i: 1">
                        <div class="we-session__ligne">
                            <?= $weFleche($weDepart['precedente'], 'precedente') ?>
                            <label class="we-choix">
                                <span class="sr-only">Session</span>
                                <?php /* Le libelle visible, sur deux lignes si l'ecran est etroit ;
                                         la liste, transparente, est posee dessus et recoit le clic. */ ?>
                                <span class="we-choix__texte" id="weSessionTexte" aria-hidden="true"><?= htmlspecialchars($weDepart['session']['nom']) ?> <span><?= htmlspecialchars($weDepart['session']['annee']) ?></span></span>
                                <select id="weSession">
                                    <?php foreach (array_reverse(array_unique(array_column($weSessions, 'session_year'))) as $annee): ?>
                                    <optgroup label="<?= htmlspecialchars($annee) ?>">
                                        <?php foreach (array_reverse($weSessions) as $s): if ($s['session_year'] !== $annee) { continue; } $n = (int)($weInscrits[$s['session_id']] ?? 0); ?>
                                        <option value="<?= (int)$s['session_id'] ?>"<?= (int)$s['session_id'] === $weSessionId ? ' selected' : '' ?>><?= htmlspecialchars($s['session_name'] . ' ' . $s['session_year']) ?><?= $n ? '' : ' (aucun inscrit)' ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <i class="bi bi-chevron-down we-choix__chevron" aria-hidden="true"></i>
                            </label>
                            <?= $weFleche($weDepart['suivante'], 'suivante') ?>
                        </div>
                        <div class="we-avancement" id="weAvancement"<?= $weTotal ? '' : ' hidden' ?>>
                            <div class="we-avancement__texte">
                                <span><b id="weAvec"><?= $weAvec ?></b> sur <b id="weTotal"><?= $weTotal ?></b> étudiants ont une tâche</span>
                                <span class="we-avancement__reste" id="weReste"><?= $weTotal - $weAvec ?> sans tâche</span>
                            </div>
                            <div class="we-barre" role="progressbar" aria-label="Étudiants avec une tâche" aria-valuemin="0" aria-valuemax="<?= $weTotal ?>" aria-valuenow="<?= $weAvec ?>" id="weBarre">
                                <span style="width: <?= $weTotal ? round($weAvec * 100 / $weTotal, 1) : 0 ?>%"></span>
                            </div>
                        </div>
                    </section>

                    <div class="we-contenu" id="weContenu">
                        <section class="we-outils" id="weOutils" aria-label="Filtres" style="--i: 2"<?= $weTotal ? '' : ' hidden' ?>>
                            <label class="we-recherche">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <span class="sr-only">Chercher un étudiant</span>
                                <input type="search" id="weRecherche" placeholder="Nom, prénom ou matricule" autocomplete="off" spellcheck="false">
                            </label>
                            <div class="we-etats" role="group" aria-label="Afficher" id="weEtats">
                                <button type="button" class="we-etat is-actif" data-etat="tout" aria-pressed="true">Tous <b><?= $weTotal ?></b></button>
                                <button type="button" class="we-etat" data-etat="sans" aria-pressed="false">Sans tâche <b><?= $weTotal - $weAvec ?></b></button>
                                <button type="button" class="we-etat" data-etat="avec" aria-pressed="false">Avec tâche <b><?= $weAvec ?></b></button>
                            </div>
                            <?php /* Tri et filtres : remplis par src/js/work-education.js d'apres
                                     les etudiants de la session ; un filtre a une seule valeur
                                     possible est cache. */ ?>
                            <div class="we-filtres" id="weFiltres" role="group" aria-label="Trier et filtrer">
                                <label class="we-filtre we-filtre--tri">
                                    <i class="bi bi-sort-down" aria-hidden="true"></i>
                                    <span class="sr-only">Trier</span>
                                    <select id="weTri">
                                        <option value="recent">Derniers inscrits d'abord</option>
                                        <option value="nom">Nom, de A à Z</option>
                                        <option value="mention">Par mention</option>
                                        <option value="niveau">Par niveau</option>
                                    </select>
                                </label>
                                <label class="we-filtre">
                                    <span class="sr-only">Mention</span>
                                    <select id="weMention"><option value="">Toutes les mentions</option></select>
                                </label>
                                <label class="we-filtre" hidden>
                                    <span class="sr-only">Parcours</span>
                                    <select id="weParcours"><option value="">Tous les parcours</option></select>
                                </label>
                                <label class="we-filtre">
                                    <span class="sr-only">Niveau</span>
                                    <select id="weNiveau"><option value="">Tous les niveaux</option></select>
                                </label>
                                <label class="we-filtre">
                                    <span class="sr-only">Nouveaux ou anciens étudiants</span>
                                    <select id="weStatut">
                                        <option value="">Nouveaux et anciens</option>
                                        <option value="nouveau">Nouveaux</option>
                                        <option value="ancien">Anciens</option>
                                    </select>
                                </label>
                                <button type="button" class="we-effacer" id="weEffacer" hidden><i class="bi bi-x-circle" aria-hidden="true"></i>Effacer les filtres</button>
                            </div>
                        </section>

                        <section class="we-taches" id="weTaches" aria-label="Tâches de la session" style="--i: 3" hidden>
                            <h2>Tâches de la session</h2>
                            <div class="we-taches__liste" id="weTachesListe"></div>
                        </section>

                        <div class="we-liste" id="weListeBloc" style="--i: 4"<?= $weTotal ? '' : ' hidden' ?>>
                            <div class="we-liste__tete">
                                <label class="we-coche">
                                    <input type="checkbox" id="weToutCocher">
                                    <span>Tout sélectionner</span>
                                </label>
                                <span class="we-liste__compte" id="weCompte" aria-live="polite"></span>
                                <span class="we-liste__astuce"><kbd>Entrée</kbd> enregistre et passe à l'étudiant suivant</span>
                            </div>
                            <ul id="weListe" class="we-lignes"></ul>
                            <div class="we-vide" id="weAucun" hidden>
                                <span class="we-vide__icone" aria-hidden="true"><i class="bi bi-funnel"></i></span>
                                <h2>Aucun étudiant ne correspond</h2>
                                <p>Changez la recherche ou les filtres.</p>
                            </div>
                        </div>

                        <div class="we-vide we-vide--grand" id="weVideSession" style="--i: 2"<?= $weTotal ? ' hidden' : '' ?>>
                            <span class="we-vide__icone" aria-hidden="true"><i class="bi bi-people"></i></span>
                            <h2>Aucun étudiant de Licence inscrit à cette session</h2>
                            <p id="weVideTexte"><?= $weDepart['master'] ? 'Les ' . (int)$weDepart['master'] . ' inscrits sont en Master, sans Work Education. ' : '' ?>Choisissez une autre session.</p>
                        </div>

                        <p class="we-note" id="weNote"<?= $weDepart['master'] && $weTotal ? '' : ' hidden' ?>><i class="bi bi-info-circle" aria-hidden="true"></i><span id="weNoteTexte"></span></p>
                    </div>

                    <div class="we-selection" id="weSelection" hidden>
                        <span class="we-selection__nombre"><b id="weSelectionNombre"></b><span class="we-libelle" id="weSelectionTexte"></span></span>
                        <label class="we-selection__champ">
                            <span class="sr-only">Tâche pour la sélection</span>
                            <input type="text" id="weSelectionTache" list="weSuggestions" maxlength="<?= WE_TACHE_MAX ?>" placeholder="Tâche à attribuer" autocomplete="off">
                        </label>
                        <button type="button" class="we-bouton" id="weAttribuer" aria-label="Attribuer"><i class="bi bi-check2" aria-hidden="true"></i><span class="we-libelle">Attribuer</span></button>
                        <button type="button" class="we-bouton we-bouton--discret" id="weRetirer" aria-label="Retirer la tâche" title="Retirer la tâche"><i class="bi bi-eraser" aria-hidden="true"></i><span class="we-libelle">Retirer la tâche</span></button>
                        <button type="button" class="we-fermer" id="weDeselectionner" aria-label="Désélectionner"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                    </div>

                    <?php /* La liste en PDF (app/work-education.pdf.php) : les choix, et combien d etudiants y seront. */ ?>
                    <dialog class="we-dialogue" id="weDlgExport" aria-labelledby="weDlgExportTitre">
                        <form method="dialog" id="weFormExport" novalidate>
                            <header class="we-dialogue__tete">
                                <h2 id="weDlgExportTitre"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Exporter la liste en PDF</h2>
                                <button type="button" class="we-fermer" data-fermer aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </header>
                            <p class="we-dialogue__aide">Choisissez ce que la liste doit contenir. Tout laisser sur «&nbsp;Tous&nbsp;» donne la liste complète.</p>
                            <div class="we-dialogue__grille">
                                <label class="we-champ-export we-champ-export--large">
                                    <span>Période</span>
                                    <select id="weExPortee">
                                        <option value="session">Cette session</option>
                                        <option value="annee">Toute l'année universitaire</option>
                                        <option value="tout">Toutes les sessions</option>
                                    </select>
                                </label>
                                <label class="we-champ-export we-champ-export--large">
                                    <span>Service</span>
                                    <select id="weExTache"><option value="">Tous les services</option></select>
                                </label>
                                <label class="we-champ-export">
                                    <span>Mention</span>
                                    <select id="weExMention"><option value="">Toutes les mentions</option></select>
                                </label>
                                <label class="we-champ-export">
                                    <span>Niveau</span>
                                    <select id="weExNiveau"><option value="">Tous les niveaux</option></select>
                                </label>
                                <label class="we-champ-export">
                                    <span>Nouveaux ou anciens</span>
                                    <select id="weExStatut">
                                        <option value="">Nouveaux et anciens</option>
                                        <option value="nouveau">Nouveaux</option>
                                        <option value="ancien">Anciens</option>
                                    </select>
                                </label>
                                <label class="we-champ-export">
                                    <span>Classer par</span>
                                    <select id="weExGroupe">
                                        <option value="service">Service</option>
                                        <option value="mention">Mention</option>
                                        <option value="niveau">Niveau</option>
                                        <option value="session">Session</option>
                                        <option value="aucun">Aucun (ordre alphabétique)</option>
                                    </select>
                                </label>
                            </div>
                            <p class="we-dialogue__compte" id="weExCompte" role="status" aria-live="polite">Calcul…</p>
                            <p class="we-dialogue__erreur" id="weExErreur" role="alert" hidden></p>
                            <footer class="we-dialogue__pied">
                                <button type="button" class="we-lien" id="weExTout">Tout (sans filtre)</button>
                                <span class="we-dialogue__boutons">
                                    <button type="button" class="we-bouton we-bouton--discret" data-fermer>Annuler</button>
                                    <button type="submit" class="we-bouton" id="weExTelecharger"><i class="bi bi-download" aria-hidden="true"></i><span>Télécharger le PDF</span></button>
                                </span>
                            </footer>
                        </form>
                    </dialog>

                    <datalist id="weSuggestions"></datalist>
                    <script type="application/json" id="weDonnees"><?= json_encode($weDepart + [
                        'suggestions' => weSuggestions($dtb),
                        'photos' => $app_base . '/app/photosetudiants/',
                        'pdf' => $app_base . '/app/work-education.pdf.php',
                        'max' => WE_TACHE_MAX,
                        'table' => $weTable,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

                    <?php else: ?>
                    <div class="we-vide we-vide--grand">
                        <span class="we-vide__icone" aria-hidden="true"><i class="bi bi-calendar-x"></i></span>
                        <h2>Aucune session</h2>
                        <p>Les sessions se créent dans Paramètres › Sessions.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>
<script src="<?=$app_base?>/src/js/work-education.js?v=<?=@filemtime(__DIR__ . '/js/work-education.js')?>"></script>
</body>
</html>
