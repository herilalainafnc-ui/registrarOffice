<?php
/* =============================================================================
   Inscriptions tardives (/inscriptions/tardives) — bulletin academique, § 334.
   Niveaux 1 a 4 (InscriptionController::tardives) ; le calendrier et les
   exonerations : niveaux 1 a 3.

   Pour chaque session : le calendrier des inscriptions (fin des inscriptions
   normales, date limite des tardives, penalite par jour ouvrable, jours non
   comptes), et chaque inscription avec sa trace : date et heure, agent du
   guichet, jours ouvrables de retard, penalite, exoneration.
   Donnees : data/inscriptions.tardives.php. Prefixe « it- ».
   Styles : src/css/inscriptions.tardives.css ; comportement :
   src/js/inscriptions.tardives.js (frise, filtres, export, exoneration,
   changement de session sans recharger).
   ========================================================================== */
require_once(__DIR__ . '/../data/backdb.php');
require_once(__DIR__ . '/../data/inscriptions.tardives.php');

$itNiveau = (int)($_SESSION['user_level'] ?? 99);
$itPeutModifier = $itNiveau >= 1 && $itNiveau <= 3;
$itUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

/* Les sessions, de la plus ancienne a la plus recente. */
$itRang = [1 => 0, 3 => 1, 2 => 2, 4 => 3];
$itSessions = $dtb->query('SELECT session_id, session_name, session_year, session_semester, date_entry, graduation_date FROM t_2023_session')->fetchAll(PDO::FETCH_ASSOC);
usort($itSessions, static function ($a, $b) use ($itRang) {
    return [$a['session_year'], $itRang[(int)$a['session_semester']] ?? 9, (int)$a['session_id']]
       <=> [$b['session_year'], $itRang[(int)$b['session_semester']] ?? 9, (int)$b['session_id']];
});
$itParId = array_column($itSessions, null, 'session_id');

/** Ce que la page montre d'une session. */
$itEtat = static function (int $id) use ($dtb, $itSessions, $itParId, $itPeutModifier): array {
    $s = $itParId[$id];
    $rang = array_search($id, array_map('intval', array_column($itSessions, 'session_id')), true);
    $voisine = static fn ($v) => $v ? ['id' => (int)$v['session_id'], 'libelle' => $v['session_name'] . ' ' . $v['session_year']] : null;
    $c = itCalendrier($dtb, $id);
    $modifiePar = '';
    if ($c && !empty($c['modifie_par'])) {
        $u = $dtb->prepare('SELECT TRIM(CONCAT(prenom, \' \', nom)) FROM compt_utilisateur WHERE id = :id');
        $u->execute(['id' => (int)$c['modifie_par']]);
        $modifiePar = (string)$u->fetchColumn();
    }
    /* Sans calendrier : une proposition, le premier vendredi de la session et
       deux semaines de plus. */
    $debut = itDate($s['date_entry']) ?? date('Y-m-d');
    $vendredi = new DateTime($debut);
    while ((int)$vendredi->format('N') !== 5) { $vendredi->modify('+1 day'); }
    /* Calendriers particuliers, avec le nombre d'inscrits qui les suivent. */
    $inscriptions = itInscriptions($dtb, $id);
    $suivent = array_count_values(array_map(static fn ($e) => $e['cas']['id'] ?? 0, $inscriptions));
    $cas = array_map(static fn ($c) => [
        'id' => $c['id'], 'libelle' => $c['libelle'], 'pour' => $c['etudiant'] !== null ? 'etudiant' : 'groupe',
        'etudiant' => $c['etudiant'] !== null ? ['id' => $c['etudiant'], 'nom' => $c['nom'], 'prenom' => $c['prenom']] : null,
        'mention' => $c['mention'], 'niveau' => $c['niveau'], 'fin_normale' => $c['fin_normale'], 'limite_tardive' => $c['limite_tardive'],
        'motif' => $c['motif'], 'modifie_par' => $c['modifie_par'], 'modifie_le' => $c['modifie_le'], 'inscrits' => $suivent[$c['id']] ?? 0,
    ], itCas($dtb, $id));
    return [
        'session'    => ['id' => $id, 'nom' => $s['session_name'], 'annee' => $s['session_year'], 'libelle' => $s['session_name'] . ' ' . $s['session_year'],
                         'debut' => itDate($s['date_entry']), 'fin' => itDate($s['graduation_date'])],
        'precedente' => $voisine($rang !== false && $rang > 0 ? $itSessions[$rang - 1] : null),
        'suivante'   => $voisine($rang !== false && $rang < count($itSessions) - 1 ? $itSessions[$rang + 1] : null),
        'calendrier' => $c ? ['fin_normale' => $c['fin_normale'], 'limite_tardive' => $c['limite_tardive'], 'penalite_jour' => (int)$c['penalite_jour'],
                              'exclus' => $c['exclus'], 'modifie_le' => $c['modifie_le'], 'modifie_par' => $modifiePar] : null,
        'proposition' => ['fin_normale' => $vendredi->format('Y-m-d'), 'limite_tardive' => (clone $vendredi)->modify('+14 days')->format('Y-m-d'), 'penalite_jour' => IT_PENALITE_DEFAUT],
        'aujourdhui' => date('Y-m-d'),
        'apercu'     => $c ? itEvaluer($c, date('Y-m-d H:i:s')) : null,
        'inscriptions' => $inscriptions,
        'cas'        => $cas,
        'casTable'   => itCasPresents($dtb),
        'modifier'   => $itPeutModifier,
    ];
};

/* Pour le formulaire des calendriers particuliers : mentions et niveaux. */
$itMentions = array_map(static fn ($m) => ['sigle' => (string)$m['filiere_sigle'], 'nom' => (string)$m['filiere_description']],
    $dtb->query('SELECT filiere_sigle, filiere_description FROM filiere ORDER BY filiere_description')->fetchAll(PDO::FETCH_ASSOC));

/* ---------------------------------------------------------------- enregistrer */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    /* Jeton refuse en 400 et non en 403 : src/js/session.js afficherait
       « Acces refuse » ; le script reprend un jeton frais et renvoie. */
    if (function_exists('verify_csrf') && !verify_csrf()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'csrf', 'message' => 'Jeton de sécurité expiré : rechargez la page.']);
        exit;
    }
    if (!$itPeutModifier) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Le calendrier et les exonérations sont réservés au registrariat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $itSession = (int)($_POST['session_id'] ?? 0);
        if (!isset($itParId[$itSession])) { throw new InvalidArgumentException('Session inconnue.'); }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'calendrier') {
            $changes = itEnregistrerCalendrier($dtb, $itSession, $_POST['fin_normale'] ?? '', $_POST['limite_tardive'] ?? '',
                $_POST['penalite_jour'] ?? '', $_POST['exclus'] ?? '', $itUser);
            $message = 'Calendrier enregistré.' . ($changes ? ' ' . $changes . ' pénalité' . ($changes > 1 ? 's recalculées' : ' recalculée') . '.' : '');
        } elseif ($action === 'exonerer') {
            $t = itExonerer($dtb, (int)($_POST['trace'] ?? 0), $_POST['montant'] ?? 0, (string)($_POST['motif'] ?? ''), $itUser);
            if ((int)$t['session_id'] !== $itSession) { throw new InvalidArgumentException('Cette inscription n’est pas de cette session.'); }
            $message = (int)$t['exoneration'] > 0 ? 'Exonération enregistrée.' : 'Exonération retirée : la pénalité est due en entier.';
        } elseif ($action === 'cas') {
            $changes = itEnregistrerCas($dtb, $itSession, $_POST, $itUser);
            $message = 'Calendrier particulier enregistré.' . ($changes ? ' ' . $changes . ' pénalité' . ($changes > 1 ? 's recalculées' : ' recalculée') . '.' : '');
        } elseif ($action === 'cas-retirer') {
            $changes = itRetirerCas($dtb, $itSession, (int)($_POST['id'] ?? 0), $itUser);
            $message = 'Calendrier particulier retiré : ses étudiants suivent celui de la session.' . ($changes ? ' ' . $changes . ' pénalité' . ($changes > 1 ? 's recalculées' : ' recalculée') . '.' : '');
        } else {
            throw new InvalidArgumentException('Action inconnue.');
        }
        echo json_encode(['success' => true, 'message' => $message] + $itEtat($itSession), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Enregistrement impossible : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* Un etudiant pour un calendrier personnel (registrariat). */
if (isset($_GET['chercher'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$itPeutModifier) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Réservé au registrariat.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'etudiants' => itChercherEtudiants($dtb, (string)$_GET['chercher'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Une autre session, demandee par la page sans la recharger. */
if (isset($_GET['donnees'])) {
    header('Content-Type: application/json; charset=utf-8');
    $itDemandee = (int)($_GET['session_id'] ?? 0);
    if (!isset($itParId[$itDemandee])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session inconnue.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true] + $itEtat($itDemandee), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------ afficher */

/* La session affichee : celle demandee, sinon la plus recente qui a un
   calendrier, sinon celle des dernieres inscriptions. */
$itSessionId = (int)($_GET['session_id'] ?? 0);
if (!isset($itParId[$itSessionId]) && itTablesPresentes($dtb)) {
    $itSessionId = (int)$dtb->query('SELECT session_id FROM t_inscription_calendrier ORDER BY limite_tardive DESC LIMIT 1')->fetchColumn();
}
if (!isset($itParId[$itSessionId])) {
    $itSessionId = (int)$dtb->query('SELECT session_id FROM t_2024_inscription_session WHERE COALESCE(`remove`, 0) != 1 AND session_id > 0 ORDER BY id DESC LIMIT 1')->fetchColumn();
}
if (!isset($itParId[$itSessionId]) && $itSessions) {
    $itSessionId = (int)end($itSessions)['session_id'];
}
$itTables = itTablesPresentes($dtb);
$itDepart = isset($itParId[$itSessionId]) ? $itEtat($itSessionId) : null;
$itFleche = static function (?array $v, string $sens): string {
    $nom = $sens === 'precedente' ? 'Session précédente' : 'Session suivante';
    $icone = $sens === 'precedente' ? 'bi-chevron-left' : 'bi-chevron-right';
    return '<a class="it-pas' . ($v ? '' : ' is-inactif') . '" data-sens="' . $sens . '" data-pt-skip="1"'
        . ($v ? ' href="?session_id=' . $v['id'] . '" data-session="' . $v['id'] . '"' : ' aria-disabled="true"')
        . ' aria-label="' . htmlspecialchars($nom . ($v ? ' : ' . $v['libelle'] : '')) . '"><i class="bi ' . $icone . '" aria-hidden="true"></i></a>';
};
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Inscriptions tardives</title>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/inscriptions.tardives.css?v=<?=@filemtime(__DIR__ . '/css/inscriptions.tardives.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php'); ?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back flex-1 overflow-y-auto">
                <div class="it it-entree" id="itPage">

                    <header class="it-tete" style="--i: 0">
                        <span class="it-tete__icone" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                        <div class="it-tete__texte">
                            <h1>Inscriptions tardives</h1>
                            <p>Le calendrier des inscriptions de chaque session, et les étudiants inscrits après lui : jours ouvrables de retard, pénalité (bulletin académique, §&nbsp;334), exonérations. Chaque inscription garde sa date, son heure et l'agent du guichet.</p>
                        </div>
                    </header>

                    <?php if (!$itTables): ?>
                    <p class="it-avis it-avis--erreur" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><span>Les tables des inscriptions tardives n'existent pas encore sur ce serveur&nbsp;: appliquez <code>data/migration_inscriptions_tardives.sql</code>.</span></p>
                    <?php endif; ?>

                    <?php if ($itDepart): ?>
                    <section class="it-session" aria-label="Session" style="--i: 1">
                        <div class="it-session__ligne">
                            <?= $itFleche($itDepart['precedente'], 'precedente') ?>
                            <label class="it-choix">
                                <span class="sr-only">Session</span>
                                <span class="it-choix__texte" id="itSessionTexte" aria-hidden="true"><?= htmlspecialchars($itDepart['session']['nom']) ?> <span><?= htmlspecialchars($itDepart['session']['annee']) ?></span></span>
                                <select id="itSession">
                                    <?php foreach (array_reverse(array_unique(array_column($itSessions, 'session_year'))) as $annee): ?>
                                    <optgroup label="<?= htmlspecialchars($annee) ?>">
                                        <?php foreach (array_reverse($itSessions) as $s): if ($s['session_year'] !== $annee) { continue; } ?>
                                        <option value="<?= (int)$s['session_id'] ?>"<?= (int)$s['session_id'] === $itSessionId ? ' selected' : '' ?>><?= htmlspecialchars($s['session_name'] . ' ' . $s['session_year']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <i class="bi bi-chevron-down it-choix__chevron" aria-hidden="true"></i>
                            </label>
                            <?= $itFleche($itDepart['suivante'], 'suivante') ?>
                        </div>

                        <?php /* La frise : inscriptions normales, tardives, hors delai ; le trait dore d'aujourd'hui. */ ?>
                        <div class="it-frise" id="itFrise" aria-hidden="true"></div>
                        <p class="it-aujourdhui" id="itAujourdhui" role="status"></p>

                        <div class="it-calendrier" id="itCalendrierBloc">
                            <button type="button" class="it-lien" id="itReglerOuvrir" aria-expanded="false" aria-controls="itCalendrier"><i class="bi bi-sliders" aria-hidden="true"></i><span>Régler le calendrier</span></button>
                            <span class="it-calendrier__trace" id="itCalendrierTrace"></span>
                            <form class="it-reglage" id="itCalendrier" hidden novalidate>
                                <div class="it-reglage__champs">
                                    <label class="it-champ">
                                        <span>Dernier jour des inscriptions normales</span>
                                        <input type="date" name="fin_normale" required>
                                    </label>
                                    <label class="it-champ">
                                        <span>Date limite des inscriptions tardives</span>
                                        <input type="date" name="limite_tardive" required>
                                    </label>
                                    <label class="it-champ it-champ--court">
                                        <span>Pénalité par jour ouvrable</span>
                                        <span class="it-montant"><input type="text" inputmode="numeric" name="penalite_jour" autocomplete="off"><b>Ar</b></span>
                                    </label>
                                </div>
                                <div class="it-exclus">
                                    <span class="it-exclus__titre">Jours non comptés <small>(jours fériés, fermeture)</small></span>
                                    <div class="it-exclus__liste" id="itExclus"></div>
                                    <span class="it-exclus__ajout"><input type="date" id="itExclusDate" aria-label="Jour à ne pas compter"><button type="button" class="it-bouton it-bouton--discret" id="itExclusAjouter"><i class="bi bi-plus-lg" aria-hidden="true"></i>Ajouter</button></span>
                                </div>
                                <p class="it-reglage__aide">Les jours de retard sont des jours ouvrables (lundi à vendredi), comptés à partir du lendemain de la fin des inscriptions normales. Les pénalités déjà calculées suivent le nouveau calendrier ; les exonérations restent.</p>
                                <p class="it-reglage__erreur" id="itCalendrierErreur" role="alert" hidden></p>
                                <div class="it-reglage__pied">
                                    <button type="button" class="it-bouton it-bouton--discret" id="itReglerFermer">Annuler</button>
                                    <button type="submit" class="it-bouton" id="itCalendrierValider"><i class="bi bi-check2" aria-hidden="true"></i>Enregistrer le calendrier</button>
                                </div>
                            </form>
                        </div>

                        <?php /* Calendriers particuliers (17/10/2026) : une mention, un niveau ou un
                           etudiant qui commence plus tard a ses propres dates. */ ?>
                        <div class="it-cas" id="itCasBloc">
                            <div class="it-cas__tete">
                                <h2 class="it-cas__titre" id="itCasTitre">Calendriers particuliers <b id="itCasNombre"></b></h2>
                                <button type="button" class="it-lien" id="itCasAjouter" aria-expanded="false" aria-controls="itCasForm"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Ajouter</span></button>
                            </div>
                            <p class="it-cas__aide" id="itCasAide">Pour une mention, un niveau ou un étudiant qui commence plus tard (stage…)&nbsp;: ses dates remplacent celles de la session. La pénalité par jour et les jours non comptés restent ceux de la session. Les nouveaux étudiants (badge «&nbsp;Nouveau&nbsp;» au guichet) ne sont jamais pénalisés.</p>
                            <ul class="it-cas__liste" id="itCasListe" aria-labelledby="itCasTitre"></ul>

                            <form class="it-reglage it-cas__form" id="itCasForm" hidden novalidate>
                                <fieldset class="it-exo__part it-cas__pour">
                                    <legend class="sr-only">Pour qui</legend>
                                    <label class="it-radio"><input type="radio" name="pour" value="groupe" checked><span><i class="bi bi-people" aria-hidden="true"></i>Une mention ou un niveau</span></label>
                                    <label class="it-radio"><input type="radio" name="pour" value="etudiant"><span><i class="bi bi-person" aria-hidden="true"></i>Un étudiant</span></label>
                                </fieldset>
                                <div class="it-reglage__champs it-reglage__champs--deux" data-pour="groupe">
                                    <label class="it-champ">
                                        <span>Mention</span>
                                        <select name="mention">
                                            <option value="">Toutes les mentions</option>
                                            <?php foreach ($itMentions as $m): ?>
                                            <option value="<?= htmlspecialchars($m['sigle']) ?>"><?= htmlspecialchars($m['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="it-champ">
                                        <span>Niveau</span>
                                        <select name="niveau">
                                            <option value="">Tous les niveaux</option>
                                            <?php foreach (itNiveaux() as $n => $libelle): ?>
                                            <option value="<?= (int)$n ?>"><?= htmlspecialchars($libelle) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div class="it-cas__etudiant" data-pour="etudiant" hidden>
                                    <label class="it-champ" id="itCasRechercheBloc">
                                        <span>Étudiant</span>
                                        <input type="search" id="itCasRecherche" placeholder="Nom, prénom ou matricule" autocomplete="off" spellcheck="false"
                                               role="combobox" aria-expanded="false" aria-controls="itCasResultats" aria-autocomplete="list">
                                    </label>
                                    <ul class="it-cas__resultats" id="itCasResultats" role="listbox" aria-label="Étudiants trouvés" hidden></ul>
                                    <p class="it-cas__choisi" id="itCasChoisi" hidden></p>
                                    <input type="hidden" name="student_id">
                                </div>
                                <div class="it-reglage__champs it-reglage__champs--deux">
                                    <label class="it-champ">
                                        <span>Dernier jour sans pénalité</span>
                                        <input type="date" name="fin_normale" required>
                                    </label>
                                    <label class="it-champ">
                                        <span>Date limite des inscriptions tardives</span>
                                        <input type="date" name="limite_tardive" required>
                                    </label>
                                </div>
                                <p class="it-cas__calcul" id="itCasCalcul" aria-live="polite"></p>
                                <label class="it-champ">
                                    <span>Motif <em>(obligatoire)</em></span>
                                    <input type="text" name="motif" maxlength="255" autocomplete="off" placeholder="Ex. : stage jusqu’au 1er novembre">
                                </label>
                                <p class="it-reglage__erreur" id="itCasErreur" role="alert" hidden></p>
                                <div class="it-reglage__pied">
                                    <button type="button" class="it-bouton it-bouton--danger-discret" id="itCasRetirer" hidden>Retirer ce calendrier</button>
                                    <span class="it-espace"></span>
                                    <button type="button" class="it-bouton it-bouton--discret" id="itCasAnnuler">Annuler</button>
                                    <button type="submit" class="it-bouton" id="itCasValider"><i class="bi bi-check2" aria-hidden="true"></i>Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section class="it-chiffres" id="itChiffres" aria-label="En chiffres" style="--i: 2"></section>

                    <section class="it-outils" aria-label="Filtres" style="--i: 3">
                        <label class="it-recherche">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="sr-only">Chercher</span>
                            <input type="search" id="itRecherche" placeholder="Nom, matricule ou agent" autocomplete="off" spellcheck="false">
                        </label>
                        <div class="it-etats" role="group" aria-label="Afficher" id="itEtats">
                            <button type="button" class="it-etat is-actif" data-etat="retard" aria-pressed="true">En retard <b>0</b></button>
                            <button type="button" class="it-etat" data-etat="hors" aria-pressed="false">Hors délai <b>0</b></button>
                            <button type="button" class="it-etat" data-etat="exoneres" aria-pressed="false">Exonérées <b>0</b></button>
                            <button type="button" class="it-etat" data-etat="tout" aria-pressed="false">Toutes <b>0</b></button>
                        </div>
                        <button type="button" class="it-bouton it-bouton--discret" id="itExporter"><i class="bi bi-download" aria-hidden="true"></i>Exporter</button>
                    </section>

                    <div class="it-liste" id="itListeBloc" style="--i: 4">
                        <ul class="it-lignes" id="itListe"></ul>
                        <div class="it-vide" id="itAucun" hidden>
                            <span class="it-vide__icone" aria-hidden="true"><i class="bi bi-calendar-check"></i></span>
                            <h2 id="itAucunTitre">Aucune inscription en retard</h2>
                            <p id="itAucunTexte"></p>
                        </div>
                    </div>

                    <dialog class="it-dialogue" id="itExoneration" aria-labelledby="itExoTitre">
                        <form method="dialog" id="itExoForm" novalidate>
                            <h2 id="itExoTitre">Exonérer de la pénalité</h2>
                            <p class="it-dialogue__qui" id="itExoQui"></p>
                            <fieldset class="it-exo__part">
                                <legend class="sr-only">Exonération</legend>
                                <label class="it-radio"><input type="radio" name="part" value="totale" checked><span>Totale</span></label>
                                <label class="it-radio"><input type="radio" name="part" value="partielle"><span>Partielle</span></label>
                            </fieldset>
                            <label class="it-champ" id="itExoMontantBloc" hidden>
                                <span>Montant exonéré</span>
                                <span class="it-montant"><input type="text" inputmode="numeric" name="montant" autocomplete="off"><b>Ar</b></span>
                            </label>
                            <label class="it-champ">
                                <span>Motif <em>(obligatoire)</em></span>
                                <textarea name="motif" rows="3" maxlength="255" placeholder="Ex. : retard du guichet, dossier médical, décision du vice-recteur…"></textarea>
                            </label>
                            <p class="it-dialogue__erreur" id="itExoErreur" role="alert" hidden></p>
                            <div class="it-dialogue__pied">
                                <button type="button" class="it-bouton it-bouton--danger-discret" id="itExoRetirer" hidden>Retirer l'exonération</button>
                                <span class="it-espace"></span>
                                <button type="button" class="it-bouton it-bouton--discret" value="annuler" id="itExoAnnuler">Annuler</button>
                                <button type="submit" class="it-bouton" id="itExoValider"><i class="bi bi-check2" aria-hidden="true"></i>Exonérer</button>
                            </div>
                        </form>
                    </dialog>

                    <script type="application/json" id="itDonnees"><?= json_encode($itDepart + ['table' => $itTables, 'mentions' => $itMentions, 'niveaux' => itNiveaux()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

                    <?php else: ?>
                    <div class="it-vide it-vide--grand">
                        <span class="it-vide__icone" aria-hidden="true"><i class="bi bi-calendar-x"></i></span>
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
<script src="<?=$app_base?>/src/js/inscriptions.tardives.js?v=<?=@filemtime(__DIR__ . '/js/inscriptions.tardives.js')?>"></script>
</body>
</html>
