<?php
/* =============================================================================
   Echeances de paiement (/finance/echeances) — la date limite de chaque
   tranche, session par session. Niveaux 1 a 3 (FinanceController::echeances),
   comme le bareme des frais.

   Les dates s'impriment sur la fiche d'inscription
   (src/extenssionPrint/fiche_inscription.php) et s'affichent au guichet, a
   l'etape « Mode de paiement » (inscription/stages/mode.payement.php).
   Donnees : data/paiement.echeances.php. Prefixe « ec- ».
   Styles : src/css/echeances.css ; comportement : src/js/echeances.js (frise
   de chaque type, reperes et controle pendant la saisie, enregistrement sans
   recharger).
   ========================================================================== */
require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/paiement.echeances.php');

$ecTypes = echeancesTypes();

/* ---------------------------------------------------------------- enregistrer */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    /* Jeton refuse en 400 et non en 403 : src/js/session.js afficherait
       « Acces refuse » ; src/js/echeances.js reprend un jeton frais et renvoie. */
    if (function_exists('verify_csrf') && !verify_csrf()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'csrf', 'message' => 'Jeton de sécurité expiré : rechargez la page.']);
        exit;
    }
    try {
        $sessionPostee = (int)($_POST['session_id'] ?? 0);
        $datesPostees = is_array($_POST['dates'] ?? null) ? $_POST['dates'] : [];
        $changees = echeancesEnregistrer($dtb, $sessionPostee, $datesPostees, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        echo json_encode([
            'success' => true,
            'message' => $changees > 0 ? 'Échéances enregistrées.' : 'Rien n’avait changé.',
            'changees' => $changees,
            'dates' => echeancesSession($dtb, $sessionPostee),
        ]);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Enregistrement impossible : ' . $e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------------------------------ afficher */

/** « 12 oct. 2026 » */
function ecDateCourte(string $ymd, bool $annee = true): string {
    static $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $t = strtotime($ymd . ' 12:00:00');
    return (int)date('j', $t) . "\u{00A0}" . $mois[(int)date('n', $t) - 1] . ($annee ? ' ' . date('Y', $t) : '');
}

$ecDateValide = static function ($v): ?string {
    $v = trim((string)$v);
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
};

/* Les sessions, de la plus ancienne a la plus recente (pour « precedente » et
   « suivante ») ; dans la liste, par annee, la plus recente d'abord. */
$ecRang = [1 => 0, 3 => 1, 2 => 2, 4 => 3];
$ecSessions = $dtb->query('SELECT session_id, session_name, session_year, session_semester, date_entry, graduation_date FROM t_2023_session')->fetchAll(PDO::FETCH_ASSOC);
usort($ecSessions, static function ($a, $b) use ($ecRang) {
    return [$a['session_year'], $ecRang[(int)$a['session_semester']] ?? 9, (int)$a['session_id']]
       <=> [$b['session_year'], $ecRang[(int)$b['session_semester']] ?? 9, (int)$b['session_id']];
});
$ecParId = array_column($ecSessions, null, 'session_id');

try {
    $ecReglees = array_map('intval', $dtb->query('SELECT DISTINCT session_id FROM t_paiement_echeance')->fetchAll(PDO::FETCH_COLUMN));
    $ecTablePresente = true;
} catch (PDOException $e) {
    $ecReglees = [];
    $ecTablePresente = false;
}

/* La session affichee : celle demandee, sinon celle ou l'on inscrit en ce
   moment (la derniere ligne de frais creee), sinon la session en cours. */
$ecSessionId = (int)($_GET['session_id'] ?? 0);
if (!isset($ecParId[$ecSessionId])) {
    $ecSessionId = (int)$dtb->query('SELECT session_id FROM t_2024_etudiant_finace WHERE `remove` != 1 AND session_id > 0 ORDER BY id DESC LIMIT 1')->fetchColumn();
}
if (!isset($ecParId[$ecSessionId])) {
    $ecSessionId = (int)$dtb->query('SELECT session_id FROM t_2023_session WHERE selected = 1 LIMIT 1')->fetchColumn();
}
if (!isset($ecParId[$ecSessionId]) && $ecSessions) {
    $ecSessionId = (int)end($ecSessions)['session_id'];
}
$ecSession = $ecParId[$ecSessionId] ?? null;

$ecIndex = $ecSession ? array_search($ecSessionId, array_map('intval', array_column($ecSessions, 'session_id')), true) : false;
$ecPrecedente = $ecIndex !== false && $ecIndex > 0 ? $ecSessions[$ecIndex - 1] : null;
$ecSuivante = $ecIndex !== false && $ecIndex < count($ecSessions) - 1 ? $ecSessions[$ecIndex + 1] : null;

$ecDates = $ecSession ? echeancesSession($dtb, $ecSessionId) : [];

/* Les etudiants de la session par type choisi (une personne une fois) */
$ecEffectifs = [];
$ecSansType = 0;
if ($ecSession) {
    $req = $dtb->prepare('SELECT COALESCE(mode_payement, "") AS type, COUNT(DISTINCT student_id) AS n FROM t_2024_etudiant_finace WHERE session_id = :s AND `remove` != 1 GROUP BY type');
    $req->execute(['s' => $ecSessionId]);
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
        if (isset($ecTypes[$l['type']])) { $ecEffectifs[$l['type']] = (int)$l['n']; } else { $ecSansType += (int)$l['n']; }
    }
}
$ecInscrits = array_sum($ecEffectifs) + $ecSansType;

/* Les types montres : ceux du guichet, et les anciens encore portes par un
   dossier de la session ou deja dates. */
$ecMontres = array_filter($ecTypes, static fn ($t, $c) => $t['propose'] || !empty($ecEffectifs[$c]) || !empty($ecDates[$c]), ARRAY_FILTER_USE_BOTH);

/* « Reprendre » : le meme semestre l'annee precedente, s'il a des dates pour
   un des types montres ; elles reviennent 52 semaines plus tard (meme jour). */
$ecCopie = null;
if ($ecSession && preg_match('~^(\d{4}) - (\d{4})$~', (string)$ecSession['session_year'], $m)) {
    $anneeAvant = ((int)$m[1] - 1) . ' - ' . ((int)$m[2] - 1);
    foreach ($ecSessions as $s) {
        if ($s['session_year'] === $anneeAvant && (int)$s['session_semester'] === (int)$ecSession['session_semester']) {
            $datesAvant = array_intersect_key(echeancesSession($dtb, (int)$s['session_id']), $ecMontres);
            if ($datesAvant) {
                $ecCopie = ['session' => (int)$s['session_id'], 'libelle' => $s['session_name'] . ' ' . $s['session_year'], 'dates' => $datesAvant];
            }
        }
    }
}

$ecDebut = $ecSession ? $ecDateValide($ecSession['date_entry']) : null;
$ecFin = $ecSession ? $ecDateValide($ecSession['graduation_date']) : null;
$ecNbDates = array_sum(array_map('count', array_intersect_key($ecDates, $ecMontres)));
$ecNbAttendues = array_sum(array_map(static fn ($t) => count($t['parts']), $ecMontres));
$ecOrdinal = static fn (int $n) => $n === 1 ? '1re' : $n . 'e';
?>
<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php'); ?>
    <title>Échéances de paiement</title>
    <link rel="stylesheet" href="<?=$app_base?>/src/css/echeances.css?v=<?=@filemtime(__DIR__ . '/../css/echeances.css')?>">
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">

    <?php require('../init/topbar.php'); ?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
            <div class="back flex-1 overflow-y-auto">
                <div class="ec ec-entree" id="ecPage">

                    <header class="ec-tete" style="--i: 0">
                        <span class="ec-tete__icone" aria-hidden="true"><i class="bi bi-calendar-check"></i></span>
                        <div class="ec-tete__texte">
                            <h1>Échéances de paiement</h1>
                            <p>La date limite de chaque tranche, session par session. Elle s'imprime sur la fiche d'inscription et s'affiche au guichet, à l'étape «&nbsp;Mode de paiement&nbsp;».</p>
                        </div>
                    </header>

                    <?php if (!$ecTablePresente): ?>
                    <p class="ec-avis ec-avis--erreur" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><span>La table des échéances n'existe pas encore sur ce serveur&nbsp;: appliquez <code>data/migration_paiement_echeances.sql</code>.</span></p>
                    <?php endif; ?>

                    <?php if ($ecSession): ?>
                    <section class="ec-session" aria-label="Session" style="--i: 1">
                        <div class="ec-session__ligne">
                            <a class="ec-pas<?= $ecPrecedente ? '' : ' is-inactif' ?>" <?= $ecPrecedente ? 'href="?session_id=' . (int)$ecPrecedente['session_id'] . '" data-session="' . (int)$ecPrecedente['session_id'] . '"' : 'aria-disabled="true"' ?>
                               aria-label="Session précédente<?= $ecPrecedente ? ' : ' . htmlspecialchars($ecPrecedente['session_name'] . ' ' . $ecPrecedente['session_year']) : '' ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                            <label class="ec-choix">
                                <span class="sr-only">Session</span>
                                <select id="ecSession">
                                    <?php foreach (array_reverse(array_unique(array_column($ecSessions, 'session_year'))) as $annee): ?>
                                    <optgroup label="<?= htmlspecialchars($annee !== '' ? $annee : 'Année non précisée') ?>">
                                        <?php foreach ($ecSessions as $s): if ($s['session_year'] !== $annee) { continue; } ?>
                                        <option value="<?= (int)$s['session_id'] ?>"<?= (int)$s['session_id'] === $ecSessionId ? ' selected' : '' ?> data-reglee="<?= in_array((int)$s['session_id'], $ecReglees, true) ? '1' : '0' ?>"><?= htmlspecialchars($s['session_name'] . ' ' . $s['session_year']) ?><?= in_array((int)$s['session_id'], $ecReglees, true) || (int)$s['session_id'] === $ecSessionId ? '' : ' — à définir' ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <i class="bi bi-chevron-down ec-choix__chevron" aria-hidden="true"></i>
                            </label>
                            <a class="ec-pas<?= $ecSuivante ? '' : ' is-inactif' ?>" <?= $ecSuivante ? 'href="?session_id=' . (int)$ecSuivante['session_id'] . '" data-session="' . (int)$ecSuivante['session_id'] . '"' : 'aria-disabled="true"' ?>
                               aria-label="Session suivante<?= $ecSuivante ? ' : ' . htmlspecialchars($ecSuivante['session_name'] . ' ' . $ecSuivante['session_year']) : '' ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        </div>
                        <p class="ec-session__infos">
                            <?php if ($ecDebut && $ecFin): ?><span><i class="bi bi-calendar3" aria-hidden="true"></i><?= ecDateCourte($ecDebut, substr($ecDebut, 0, 4) !== substr($ecFin, 0, 4)) ?> → <?= ecDateCourte($ecFin) ?></span><?php else: ?><span><i class="bi bi-calendar3" aria-hidden="true"></i>Dates de la session à fixer (Paramètres › Sessions)</span><?php endif; ?>
                            <span><i class="bi bi-people" aria-hidden="true"></i><?php if ($ecInscrits === 0): ?>Aucune inscription pour l'instant<?php else: ?><?= $ecInscrits ?> inscription<?= $ecInscrits > 1 ? 's' : '' ?><?php if ($ecSansType > 0): ?>, dont <?= $ecSansType ?> sans type de paiement choisi<?php endif; ?><?php endif; ?></span>
                            <span class="ec-etat <?= $ecNbDates === 0 ? 'ec-etat--alerte' : ($ecNbDates < $ecNbAttendues ? 'ec-etat--partiel' : 'ec-etat--ok') ?>" id="ecEtat"><?= $ecNbDates === 0 ? 'Échéances à définir' : ($ecNbDates < $ecNbAttendues ? $ecNbDates . ' dates sur ' . $ecNbAttendues : 'Échéances définies') ?></span>
                        </p>
                        <?php if ($ecCopie): ?>
                        <button type="button" class="ec-bouton ec-bouton--discret ec-copier" id="ecCopier">
                            <i class="bi bi-calendar2-plus" aria-hidden="true"></i><span>Reprendre les dates de <?= htmlspecialchars($ecCopie['libelle']) ?>, 52 semaines plus tard</span>
                        </button>
                        <?php endif; ?>
                    </section>

                    <form class="ec-types" id="ecForm" novalidate>
                        <input type="hidden" name="session_id" value="<?= $ecSessionId ?>">
                        <?php $i = 2; foreach ($ecMontres as $code => $type):
                            $n = count($type['parts']);
                            $effectif = $ecEffectifs[$code] ?? 0; ?>
                        <article class="ec-type<?= $type['propose'] ? '' : ' ec-type--ancien' ?>" data-type="<?= $code ?>" style="--i: <?= $i++ ?>" aria-labelledby="ecType<?= $code ?>">
                            <header class="ec-type__tete">
                                <span class="ec-type__lettre" aria-hidden="true"><?= $code ?></span>
                                <div class="ec-type__nom">
                                    <h2 id="ecType<?= $code ?>">Type <?= $code ?></h2>
                                    <p><?= htmlspecialchars($type['libelle']) ?><?= $type['propose'] ? '' : ' · plus proposé au guichet' ?></p>
                                </div>
                                <span class="ec-type__effectif<?= $effectif ? '' : ' is-vide' ?>"><?= $effectif ? $effectif . ' étudiant' . ($effectif > 1 ? 's' : '') : 'Aucun étudiant' ?></span>
                            </header>
                            <div class="ec-rail" aria-hidden="true">
                                <div class="ec-rail__mois"></div>
                                <div class="ec-rail__piste">
                                    <span class="ec-rail__session"></span>
                                    <?php foreach ($type['parts'] as $k => $part): ?>
                                    <span class="ec-jalon" data-tranche="<?= $k + 1 ?>" style="--part: <?= $part ?>; --k: <?= $k ?>"><b><?= $part ?>&nbsp;%</b></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="ec-tranches" style="--n: <?= $n ?>">
                                <?php foreach ($type['parts'] as $k => $part):
                                    $rang = $k + 1;
                                    $valeur = $ecDates[$code][$rang] ?? ''; ?>
                                <label class="ec-tranche">
                                    <span class="ec-tranche__titre"><?= $n === 1 ? 'En une fois' : $ecOrdinal($rang) . ' tranche' ?> <em><?= $part ?>&nbsp;%</em></span>
                                    <input type="date" name="dates[<?= $code ?>][<?= $rang ?>]" value="<?= htmlspecialchars($valeur) ?>" data-type="<?= $code ?>" data-tranche="<?= $rang ?>">
                                    <small class="ec-tranche__repere" aria-live="polite"></small>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </form>

                    <div class="ec-barre" id="ecBarre" aria-live="polite">
                        <span class="ec-barre__texte" id="ecModifs"></span>
                        <button type="button" class="ec-bouton ec-bouton--discret" id="ecAnnuler">Annuler les changements</button>
                        <button type="submit" form="ecForm" class="ec-bouton" id="ecEnregistrer"><i class="bi bi-check-lg" aria-hidden="true"></i><span>Enregistrer les échéances</span></button>
                    </div>

                    <script type="application/json" id="ecConfig"><?= json_encode([
                        'aujourdhui' => date('Y-m-d'),
                        'session' => ['id' => $ecSessionId, 'debut' => $ecDebut, 'fin' => $ecFin, 'libelle' => $ecSession['session_name'] . ' ' . $ecSession['session_year']],
                        'types' => $ecMontres,
                        'enregistrees' => array_intersect_key($ecDates, $ecMontres),
                        'copie' => $ecCopie,
                        'attendues' => $ecNbAttendues,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                    <?php else: ?>
                    <div class="ec-vide">
                        <span class="ec-vide__icone" aria-hidden="true"><i class="bi bi-calendar-x"></i></span>
                        <h2>Aucune session</h2>
                        <p>Créez d'abord les sessions de l'année dans Paramètres › Sessions.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>
<script src="<?=$app_base?>/src/js/echeances.js?v=<?=@filemtime(__DIR__ . '/../js/echeances.js')?>"></script>
</body>
</html>
