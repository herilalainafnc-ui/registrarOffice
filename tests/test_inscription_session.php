<?php
/**
 * Tests du socle data/inscription.session.php.
 *
 * Ce fichier verifie les proprietes qui ont cause les pannes signalees sur le
 * module inscription :
 *   - « la session courante » n'est plus « l'id le plus grand » mais la session
 *     de l'annee scolaire en cours ;
 *   - la fenetre d'annee est la meme partout, et n'est plus inversee avant
 *     juillet ;
 *   - un session_id transmis n'est honore que s'il appartient a l'etudiant ;
 *   - la ligne financiere retenue parmi des doublons est deterministe et ne
 *     perd pas un mode de paiement deja enregistre.
 *
 * Les cas nominaux sont joues sur des donnees creees puis supprimees par le
 * test. Deux assertions supplementaires interrogent les donnees reelles, sans
 * rien y ecrire.
 *
 * Lancement :
 *   php tests/test_inscription_session.php
 */

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../data/backdb.php';
require_once __DIR__ . '/../data/inscription.session.php';

$reussis = 0;
$echoues = 0;

function verifier(string $intitule, bool $condition, string $detail = ''): void
{
    global $reussis, $echoues;
    if ($condition) {
        $reussis++;
        echo "  OK    $intitule\n";
    } else {
        $echoues++;
        echo "  ECHEC $intitule" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}

function titre(string $t): void { echo "\n" . $t . "\n" . str_repeat('-', strlen($t)) . "\n"; }

// Matricule de travail, volontairement hors de la plage des vrais matricules.
$ETU = 'ZZTEST9001';

/** Supprime toute trace du matricule de test. */
function nettoyer(PDO $dtb, string $etu): void
{
    foreach (['t_2024_inscription_session', 't_2024_etudiant_finace'] as $table) {
        $req = $dtb->prepare("DELETE FROM $table WHERE student_id = :e");
        $req->execute(['e' => $etu]);
    }
}

try {
    nettoyer($dtb, $ETU); // residus d'une execution interrompue

    titre('Annee scolaire courante');

    verifier('juillet ouvre la nouvelle annee', inscAnneeCourante(7, 2026) === '2026 - 2027',
        inscAnneeCourante(7, 2026));
    verifier('juin appartient encore a l\'annee precedente', inscAnneeCourante(6, 2026) === '2025 - 2026',
        inscAnneeCourante(6, 2026));
    verifier('decembre reste dans l\'annee ouverte', inscAnneeCourante(12, 2026) === '2026 - 2027',
        inscAnneeCourante(12, 2026));
    verifier('format identique a celui de la base (espaces autour du tiret)',
        preg_match('/^\d{4} - \d{4}$/', inscAnneeCourante()) === 1, inscAnneeCourante());

    titre('Fenetre d\'annee — le bug inverse de generate.stage.php');

    // Avant juillet, l'ancienne condition etait « annee != annee precedente » :
    // elle refusait donc l'annee courante et acceptait les perimees.
    verifier('en mars, le dossier de l\'annee en cours est accepte',
        inscAnneeEstCourante('2025 - 2026', 3, 2026) === true);
    verifier('en mars, un dossier perime est refuse',
        inscAnneeEstCourante('2024 - 2025', 3, 2026) === false);
    verifier('en septembre, le dossier de l\'annee en cours est accepte',
        inscAnneeEstCourante('2026 - 2027', 9, 2026) === true);
    verifier('en septembre, le dossier de l\'annee passee est refuse',
        inscAnneeEstCourante('2025 - 2026', 9, 2026) === false);
    verifier('une annee vide n\'est jamais courante', inscAnneeEstCourante('') === false);
    verifier('une annee nulle n\'est jamais courante', inscAnneeEstCourante(null) === false);

    titre('Choix de la session — cas qui reproduit le dossier 30188');

    // Sessions reelles du catalogue, pour disposer d'annees connues.
    $cat = $dtb->query("SELECT session_id, session_year, session_name
        FROM t_2023_session WHERE session_year IN ('2025 - 2026','2026 - 2027')
          AND session_name = 'Premier semestre' ORDER BY session_year")->fetchAll(PDO::FETCH_ASSOC);

    if (count($cat) < 2) {
        verifier('catalogue : deux annees disponibles pour le scenario', false,
            'sessions Premier semestre 2025-2026 / 2026-2027 introuvables');
    } else {
        $ancienne = $cat[0]; // 2025 - 2026
        $recente  = $cat[1]; // 2026 - 2027

        $insere = $dtb->prepare('INSERT INTO t_2024_inscription_session
            (student_id, etude_mention, status, new_student, data_completion, session_id,
             nbr_semester, annee_scolaire, date_entry)
            VALUES (:e, "INFO", "Externe", 0, 1, :s, 1, :a, CURDATE())');

        // L'annee RECENTE est inseree d'abord : elle recoit donc l'id le PLUS
        // PETIT. C'est exactement la configuration du dossier 30188.
        $insere->execute(['e' => $ETU, 's' => $recente['session_id'],  'a' => $recente['session_year']]);
        $idRecente = (int) $dtb->lastInsertId();
        $insere->execute(['e' => $ETU, 's' => $ancienne['session_id'], 'a' => $ancienne['session_year']]);
        $idAncienne = (int) $dtb->lastInsertId();

        verifier('mise en place : la session recente porte bien l\'id le plus petit',
            $idRecente < $idAncienne, "recente=$idRecente ancienne=$idAncienne");

        $sessions = inscSessionsEtudiant($dtb, $ETU);
        verifier('les deux sessions sont retrouvees', count($sessions) === 2, (string) count($sessions));
        verifier('le tri place l\'annee la plus recente en tete',
            ($sessions[0]['annee_effective'] ?? '') === $recente['session_year'],
            $sessions[0]['annee_effective'] ?? 'aucune');

        // Le coeur du correctif : en septembre 2026 c'est 2026-2027 qui doit
        // etre choisie, alors que « ORDER BY id DESC » designait 2025-2026.
        $choisie = inscSessionCourante($dtb, $ETU);
        $attendue = inscAnneeEstCourante($recente['session_year'])
            ? $recente['session_year']
            : $recente['session_year']; // l'annee la plus recente dans les deux cas
        verifier('la session choisie n\'est PAS celle de l\'id le plus grand',
            (int) $choisie['id'] !== $idAncienne,
            'id choisi=' . $choisie['id'] . ' (id le plus grand=' . $idAncienne . ')');
        verifier('la session choisie est celle de l\'annee la plus recente',
            $choisie['annee_effective'] === $attendue, $choisie['annee_effective']);

        titre('Un session_id transmis ne doit pas contourner l\'etudiant');

        $force = inscSessionCourante($dtb, $ETU, $ancienne['session_id']);
        verifier('un session_id appartenant a l\'etudiant est honore',
            (int) $force['session_id'] === (int) $ancienne['session_id'],
            (string) $force['session_id']);

        $etranger = inscSessionCourante($dtb, $ETU, 999999);
        verifier('un session_id inconnu retombe sur la resolution par annee',
            (int) $etranger['session_id'] === (int) $recente['session_id'],
            (string) $etranger['session_id']);

        verifier('inscSessionIdCourant renvoie une chaine non vide',
            inscSessionIdCourant($dtb, $ETU) !== '');

        titre('Entre deux semestres d\'une meme annee, la plus recente gagne');

        /* Regression verrouillee ici.
           Un premier correctif departageait par avancement decroissant dans le
           tri general : un semestre 2 tout juste cree (avancement 1) passait
           alors derriere un semestre 1 termine (avancement 7), et le module
           revenait sur le semestre precedent. Mesure a l'epoque : 403
           etudiants changeaient de session, au lieu des 14 attendus.
           Le departage par avancement ne vaut qu'entre doublons d'une MEME
           session ; entre sessions differentes, c'est l'id le plus grand. */

        $deux = $dtb->query("SELECT session_id, session_name, session_year
            FROM t_2023_session WHERE session_year = '2025 - 2026'
              AND session_name IN ('Premier semestre','Deuxième semestre')
            ORDER BY session_semester")->fetchAll(PDO::FETCH_ASSOC);

        if (count($deux) < 2) {
            echo "  (ignore) les deux semestres de 2025-2026 ne sont pas au catalogue\n";
        } else {
            $ETU2 = 'ZZTEST9002';
            nettoyer($dtb, $ETU2);

            // Semestre 1 TERMINE (toutes les etapes franchies), insere d'abord.
            $dtb->prepare('INSERT INTO t_2024_inscription_session
                (student_id, etude_mention, status, new_student, data_completion,
                 data_verification, cours_selected, mode_payement, verification_signatures,
                 depot_list, caisse_verification, session_id, nbr_semester, annee_scolaire, date_entry)
                VALUES (:e, "INFO", "Externe", 0, 1,1,1,1,1,1,1, :s, 1, :a, CURDATE())')
                ->execute(['e' => $ETU2, 's' => $deux[0]['session_id'], 'a' => $deux[0]['session_year']]);

            // Semestre 2 TOUT JUSTE CREE, insere ensuite : id plus grand,
            // avancement minimal. C'est celui sur lequel le registraire
            // travaille, donc celui que le module doit retenir.
            $dtb->prepare('INSERT INTO t_2024_inscription_session
                (student_id, etude_mention, status, new_student, data_completion,
                 session_id, nbr_semester, annee_scolaire, date_entry)
                VALUES (:e, "INFO", "Externe", 0, 1, :s, 2, :a, CURDATE())')
                ->execute(['e' => $ETU2, 's' => $deux[1]['session_id'], 'a' => $deux[1]['session_year']]);

            $choix = inscSessionCourante($dtb, $ETU2);
            verifier('le semestre le plus recemment cree est retenu, malgre son avancement moindre',
                (int) $choix['session_id'] === (int) $deux[1]['session_id'],
                'retenu=' . $choix['session_id'] . ' attendu=' . $deux[1]['session_id']
                . ' (le termine=' . $deux[0]['session_id'] . ')');
            verifier('les deux semestres restent listes, pas fusionnes',
                count(inscSessionsEtudiant($dtb, $ETU2)) === 2,
                (string) count(inscSessionsEtudiant($dtb, $ETU2)));

            // En revanche, deux lignes de la MEME session : la plus avancee.
            $dtb->prepare('INSERT INTO t_2024_inscription_session
                (student_id, etude_mention, status, new_student, data_completion,
                 data_verification, cours_selected, session_id, nbr_semester, annee_scolaire, date_entry)
                VALUES (:e, "INFO", "Externe", 0, 1,1,1, :s, 2, :a, CURDATE())')
                ->execute(['e' => $ETU2, 's' => $deux[1]['session_id'], 'a' => $deux[1]['session_year']]);

            $choix2 = inscSessionCourante($dtb, $ETU2);
            verifier('entre doublons d\'une meme session, le plus avance est retenu',
                (int) $choix2['avancement'] === 3, (string) $choix2['avancement']);
            verifier('le doublon ne cree pas une entree supplementaire',
                count(inscSessionsEtudiant($dtb, $ETU2)) === 2,
                (string) count(inscSessionsEtudiant($dtb, $ETU2)));

            nettoyer($dtb, $ETU2);
        }

        titre('Ligne financiere : doublons et mode de paiement');

        // Deux lignes pour le meme couple : la plus recente SANS mode de
        // paiement, l'ancienne AVEC. C'est le cas de 56 couples en base.
        $insF = $dtb->prepare('INSERT INTO t_2024_etudiant_finace
            (student_id, session_id, mention, level, status, cout_logement, cout_fraix_generaux,
             cout_fondDepot_dortoir, cout_abonment, cout_frais_graduation, cout_costume,
             cout_voyage, cout_totalCours, cout_totalLab, mode_payement, `remove`, date_entry,
             last_change_user_id, last_change_datetime)
            VALUES (:e, :s, "INFO", 1, "Externe", 0, 165000, 0, 0, 0, 0, 0, 0, 0, :m, 0, CURDATE(), 0, CURDATE())');

        $insF->execute(['e' => $ETU, 's' => $recente['session_id'], 'm' => 'E']);   // id plus petit, a un mode
        $insF->execute(['e' => $ETU, 's' => $recente['session_id'], 'm' => '']);    // id plus grand, vide

        $fin = inscFinanceSession($dtb, $ETU, $recente['session_id']);
        verifier('une ligne financiere est trouvee', $fin !== false);
        verifier('la ligne portant un mode de paiement est preferee a la plus recente vide',
            ($fin['mode_payement'] ?? '') === 'E', var_export($fin['mode_payement'] ?? null, true));

        verifier('couple sans ligne financiere : false, pas une erreur',
            inscFinanceSession($dtb, $ETU, 999999) === false);
        verifier('session vide : false', inscFinanceSession($dtb, $ETU, '') === false);
    }

    titre('Etudiant sans inscription');

    verifier('aucune session : null', inscSessionCourante($dtb, 'ZZINEXISTANT0') === null);
    verifier('aucune session : id vide', inscSessionIdCourant($dtb, 'ZZINEXISTANT0') === '');
    verifier('matricule vide : tableau vide', inscSessionsEtudiant($dtb, '') === []);

    titre('Donnees reelles (lecture seule)');

    // Le dossier signale par le registraire.
    $reel = inscSessionCourante($dtb, '30188');
    if ($reel === null) {
        echo "  (ignore) le dossier 30188 n'existe pas sur cette base\n";
    } else {
        $parId = $dtb->query("SELECT annee_scolaire FROM t_2024_inscription_session
            WHERE student_id = '30188' AND remove != 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
        verifier('30188 : le resolveur ne retient plus l\'annee de l\'id le plus grand',
            $reel['annee_effective'] !== $parId,
            'resolveur=' . $reel['annee_effective'] . ' ancien=' . $parId);
        verifier('30188 : l\'annee retenue est la plus recente de son dossier',
            $reel['annee_effective'] === '2026 - 2027', $reel['annee_effective']);
    }

    // Propriete generale : pour tout etudiant, l'annee retenue est >= celle que
    // donnait l'ancienne selection par id.
    $ecarts = 0;
    $etudiants = $dtb->query("SELECT DISTINCT student_id FROM t_2024_inscription_session
        WHERE remove != 1 LIMIT 400")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($etudiants as $e) {
        $s = inscSessionCourante($dtb, (string) $e);
        $parId = $dtb->prepare("SELECT COALESCE(NULLIF(s.session_year,''), i.annee_scolaire)
            FROM t_2024_inscription_session i
            LEFT JOIN t_2023_session s ON s.session_id = i.session_id
            WHERE i.student_id = :e AND i.remove != 1 ORDER BY i.id DESC LIMIT 1");
        $parId->execute(['e' => $e]);
        $ancien = (string) $parId->fetchColumn();
        if ($s && $ancien !== '' && strcmp((string) $s['annee_effective'], $ancien) < 0) {
            $ecarts++;
        }
    }
    verifier('sur ' . count($etudiants) . ' etudiants reels, le resolveur ne recule jamais d\'annee',
        $ecarts === 0, "$ecarts regression(s)");

} finally {
    nettoyer($dtb, $ETU);
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Reussis : $reussis   Echoues : $echoues\n";
    exit($echoues === 0 ? 0 : 1);
}
