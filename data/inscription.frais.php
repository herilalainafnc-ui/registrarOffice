<?php
/**
 * Frais d'un dossier d'inscription : un seul calcul, pour tous les appelants.
 *
 * generate.student.php calculait ces montants en ligne. Le recalcul d'une
 * session deja inscrite demande exactement le meme calcul : deux copies
 * auraient divergé, comme l'ont fait les conditions du frais de graduation —
 * six variantes pour une seule regle, et 339 dossiers faux releves le
 * 16/09/2026. Une seule fonction, donc, et les appelants n'ont plus qu'a
 * fournir le contexte du dossier.
 *
 * Ce qui N'EST PAS calcule ici : cout_totalCours et cout_totalLab, qui
 * viennent des cours choisis (app/.student/checkCours.php), et les frais
 * supplementaires du bareme, qui vivent dans leurs propres lignes
 * (data/finance.frais.php).
 */

require_once __DIR__ . '/finance.bareme.php'; // baremeFraisGraduation(), baremeLigne()
require_once __DIR__ . '/finance.frais.php';  // fraisPersoAppliquer()
require_once __DIR__ . '/cours.offerts.php';  // coursAnneePassee()

/**
 * Montants dus pour un dossier, d'apres une ligne de bareme.
 *
 * @param  array<string, mixed> $bareme ligne rendue par baremeLigne()
 * @param  array<string, mixed> $ctx    student_id, annee_scolaire, semestre,
 *                                      statut_dossier, abonnement, diplomant
 * @return array<string, float> colonnes cout_* de t_2024_etudiant_finace
 */
function inscriptionFraisCalcules(PDO $dtb, array $bareme, array $ctx): array
{
    $jours    = (int) ($bareme['nb_jours_semestre'] ?? 0);
    $semestre = (int) ($ctx['semestre'] ?? 0);

    /* Voyage d'etude : double pour qui arrive au deuxieme semestre sans avoir
       ete inscrit au premier — il n'a pas paye sa part a la rentree. */
    $voyage = (float) ($bareme['frais_voyage'] ?? 0);
    if ($semestre !== 1) {
        $req = $dtb->prepare(
            'SELECT COUNT(*) FROM t_2024_inscription_session ins
               INNER JOIN t_2023_session ses ON ins.session_id = ses.session_id
              WHERE ins.student_id = :student_id
                AND ses.session_year = :annee
                AND ses.session_semester = 1'
        );
        $req->execute([
            'student_id' => (string) ($ctx['student_id'] ?? ''),
            'annee'      => (string) ($ctx['annee_scolaire'] ?? ''),
        ]);
        if ((int) $req->fetchColumn() === 0) {
            $voyage *= 2;
        }
    }

    /* Fond de depot : demande a qui ne loge pas au dortoir. La regle est celle
       appliquee a l'inscription ; app/.student/updateFinance.php en applique
       une autre (L1 interne), divergence signalee le 16/09/2026 et laissee en
       l'etat faute de decision. */
    $statutDossier = (string) ($ctx['statut_dossier'] ?? '');
    $fondDepot = ($statutDossier === 'Externe' || $statutDossier === 'Bungalow')
        ? (float) ($bareme['fond_depot'] ?? 0)
        : 0.0;

    return [
        'cout_fraix_generaux'    => (float) ($bareme['frais_generaux'] ?? 0),
        'cout_logement'          => (float) ($bareme['dortoir'] ?? 0) * $jours,
        'cout_abonment'          => ((int) ($ctx['abonnement'] ?? 0) === 1)
            ? (float) ($bareme['cafeteria'] ?? 0) * $jours
            : 0.0,
        'cout_fondDepot_dortoir' => $fondDepot,
        'cout_frais_graduation'  => baremeFraisGraduation(
            $bareme['frais_graduation'] ?? 0,
            $semestre,
            $ctx['diplomant'] ?? 0
        ),
        'cout_costume'           => (float) ($bareme['frais_costume'] ?? 0),
        'cout_voyage'            => $voyage,
    ];
}

/**
 * Tarif de l'annee, pour une session passee : ce qui etait facture alors, non
 * le bareme d'aujourd'hui. Regle du registrariat (07/10/2026), la meme que
 * pour l'ecolage d'un cours ajoute a une annee passee
 * (baremeEcolageHistorique()).
 *
 * Le bareme dit quels montants sont dus (logement d'un interne, cafeteria
 * d'un abonne, graduation d'un diplomant...). Pour chacun, on retient le
 * montant le plus frequent parmi les lignes deja facturees au meme niveau,
 * du plus proche au plus large : meme session et mention ; meme session ;
 * meme annee et mention ; meme annee. Le niveau compte plus que la mention :
 * en 2024-2025, 210 000 Ar de frais generaux en L1 et 165 000 en L2 et L3,
 * toutes mentions confondues. Logement, cafeteria et fond de depot ne se
 * comparent qu'au meme statut (un interne n'est pas un bungalow), et
 * logement et cafeteria a la meme session seulement : ils dependent de sa
 * duree. Il faut au moins trois lignes concordantes, sinon le bareme reste.
 *
 * @param  array<string, mixed> $ctx session_id, mention, niveau, annee_scolaire, statut
 * @return array{montants: array<string, float>, sources: array<string, string>}
 */
function inscriptionFraisDeLAnnee(PDO $dtb, array $bareme, array $montants, array $ctx): array
{
    $session = (int) $ctx['session_id'];
    $mention = (string) $ctx['mention'];
    $niveau  = (int) $ctx['niveau'];
    $annee   = (string) $ctx['annee_scolaire'];
    /* A l'echelle de l'annee, le meme semestre seulement : en 2025-2026, 250 000
       Ar de frais generaux a l'entree (S1) en L0 et L1, 165 000 le plus souvent
       au S2 pour qui continue. */
    $semestre = (int) $ctx['semestre'];

    $parNiveau = [
        'session, mention et niveau' => ['f.session_id = :s AND f.mention = :m AND f.level = :n', ['s' => $session, 'm' => $mention, 'n' => $niveau], true],
        'session et niveau'          => ['f.session_id = :s AND f.level = :n', ['s' => $session, 'n' => $niveau], true],
        'annee, mention et niveau'   => ['s.session_year = :a AND s.session_semester = :sem AND f.mention = :m AND f.level = :n', ['a' => $annee, 'sem' => $semestre, 'm' => $mention, 'n' => $niveau], false],
        'annee et niveau'            => ['s.session_year = :a AND s.session_semester = :sem AND f.level = :n', ['a' => $annee, 'sem' => $semestre, 'n' => $niveau], false],
    ];
    /* Voyage et costume suivent la mention : la Theologie paie un colloque
       (50 000 Ar en 2024-2025) ou d'autres paient un voyage d'etude. */
    $parMention = [
        'session, mention et niveau' => $parNiveau['session, mention et niveau'],
        'session et mention'         => ['f.session_id = :s AND f.mention = :m', ['s' => $session, 'm' => $mention], true],
        'annee, mention et niveau'   => $parNiveau['annee, mention et niveau'],
        'annee et mention'           => ['s.session_year = :a AND s.session_semester = :sem AND f.mention = :m', ['a' => $annee, 'sem' => $semestre, 'm' => $mention], false],
    ];
    $suitLaMention = ['cout_voyage', 'cout_costume'];
    $aLaJournee    = ['cout_logement', 'cout_abonment'];
    $selonStatut   = ['cout_logement', 'cout_abonment', 'cout_fondDepot_dortoir'];

    /* Le voyage double (deuxieme semestre sans le premier) se double aussi au
       tarif de l'annee : le montant le plus frequent est la part simple. */
    $voyageBareme = (float) ($bareme['frais_voyage'] ?? 0);
    $voyageDouble = $voyageBareme > 0 && abs((float) $montants['cout_voyage'] - 2 * $voyageBareme) < 0.01;

    $sources = [];
    foreach ($montants as $colonne => $valeur) {
        $sources[$colonne] = 'bareme';
        if ((float) $valeur <= 0) {
            continue;
        }
        $paliers = in_array($colonne, $suitLaMention, true) ? $parMention : $parNiveau;
        foreach ($paliers as $nom => [$condition, $params, $journalier]) {
            if (!$journalier && in_array($colonne, $aLaJournee, true)) {
                continue;
            }
            $statut = in_array($colonne, $selonStatut, true);
            $req = $dtb->prepare("SELECT f.$colonne AS v, COUNT(*) AS n
                FROM t_2024_etudiant_finace f
                JOIN t_2023_session s ON s.session_id = f.session_id
                WHERE f.`remove` != 1 AND f.$colonne > 0 AND $condition"
                . ($statut ? ' AND f.status = :st' : '') . "
                GROUP BY f.$colonne ORDER BY n DESC, v DESC LIMIT 1");
            $req->execute($params + ($statut ? ['st' => (string) $ctx['statut']] : []));
            $mode = $req->fetch(PDO::FETCH_ASSOC);
            if ($mode && (int) $mode['n'] >= 3) {
                $montants[$colonne] = (float) $mode['v'] * ($colonne === 'cout_voyage' && $voyageDouble ? 2 : 1);
                $sources[$colonne] = $nom;
                break;
            }
        }
    }

    return ['montants' => $montants, 'sources' => $sources];
}

/**
 * Contexte de calcul des frais d'une inscription existante, ou null.
 *
 * Pour une session passee, mention, niveau, statut, abonnement et diplomant
 * sont ceux de l'inscription d'alors : la fiche porte ceux d'aujourd'hui (un
 * L3 qui etait en L1). Pour l'annee en cours, ceux de la fiche, comme a
 * l'inscription (generate.student.php).
 */
function inscriptionFraisContexte(PDO $dtb, string $student_id, int $session_id): ?array
{
    $req = $dtb->prepare('SELECT i.etude_mention, i.niveau_std, i.status, i.abonment_std, i.graduated, i.new_student,
            s.session_name, s.session_year, s.session_semester
        FROM t_2024_inscription_session i
        JOIN t_2023_session s ON s.session_id = i.session_id
        WHERE i.student_id = :s AND i.session_id = :ses AND COALESCE(i.`remove`, 0) != 1
        ORDER BY i.id DESC LIMIT 1');
    $req->execute(['s' => $student_id, 'ses' => $session_id]);
    $ins = $req->fetch(PDO::FETCH_ASSOC);

    $req = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :s ORDER BY `remove` ASC, id ASC LIMIT 1');
    $req->execute(['s' => $student_id]);
    $fiche = $req->fetch(PDO::FETCH_ASSOC);
    if (!$ins || !$fiche) {
        return null;
    }

    $req = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = :d LIMIT 1');
    $req->execute(['d' => $fiche['etude_envisage']]);
    $sigleFiche = (string) $req->fetchColumn();

    $passee = coursAnneePassee((string) $ins['session_year']);
    $alors  = static fn ($v) => $passee && $v !== null && trim((string) $v) !== '';
    $statutDossier = $alors($ins['status']) ? (string) $ins['status'] : (string) ($fiche['status'] ?? '');

    /* Niveau d'une session passee. Un 0 est souvent un niveau jamais saisi :
       les 395 inscriptions de 2024-2025 S1 sans frais sont toutes a 0 (le 07/10/2026),
       d'un L3 comme d'un L1. Mais la Theologie a de vraies remises a niveau
       (L0), dont les cours sont de niveau 1. D'ou, pour un 0 : le niveau d'une
       autre inscription de la meme annee ; sinon 0 si la fiche est encore en
       L0 ; sinon le niveau le plus frequent de ses cours de la session ;
       sinon inconnu (null), et pas de frais calcules. */
    $niveau = (int) $fiche['annee_etude'];
    $origineNiveau = 'fiche';
    if ($passee) {
        if ((int) $ins['niveau_std'] > 0) {
            $niveau = (int) $ins['niveau_std'];
            $origineNiveau = 'inscription';
        } else {
            $req = $dtb->prepare('SELECT i.niveau_std FROM t_2024_inscription_session i
                JOIN t_2023_session s ON s.session_id = i.session_id
                WHERE i.student_id = :s AND s.session_year = :a AND i.niveau_std > 0 AND COALESCE(i.`remove`, 0) != 1
                ORDER BY i.session_id DESC LIMIT 1');
            $req->execute(['s' => $student_id, 'a' => $ins['session_year']]);
            $memeAnnee = $req->fetchColumn();

            $req = $dtb->prepare('SELECT yearlevel FROM t_2023_notes
                WHERE student_id = :s AND session_id = :ses AND `remove` = 0 AND ajout = 1
                GROUP BY yearlevel ORDER BY COUNT(*) DESC LIMIT 1');
            $req->execute(['s' => $student_id, 'ses' => $session_id]);
            $niveauCours = $req->fetchColumn();

            if ($memeAnnee !== false) {
                [$niveau, $origineNiveau] = [(int) $memeAnnee, 'meme annee'];
            } elseif ((int) $fiche['annee_etude'] === 0) {
                [$niveau, $origineNiveau] = [0, 'remise a niveau'];
            } elseif ($niveauCours !== false) {
                [$niveau, $origineNiveau] = [(int) $niveauCours, 'cours de la session'];
            } else {
                [$niveau, $origineNiveau] = [null, 'inconnu'];
            }
        }
    }

    return [
        'student_id'      => $student_id,
        'session_id'      => $session_id,
        'session_name'    => (string) $ins['session_name'],
        'annee_scolaire'  => (string) $ins['session_year'],
        'semestre'        => (int) $ins['session_semester'],
        'passee'          => $passee,
        'mention'         => $alors($ins['etude_mention']) ? (string) $ins['etude_mention'] : $sigleFiche,
        'niveau'          => $niveau,
        'origine_niveau'  => $origineNiveau,
        'statut_dossier'  => $statutDossier,
        'statut'          => inscriptionStatutBareme($statutDossier),
        'abonnement'      => $alors($ins['abonment_std']) ? (int) $ins['abonment_std'] : (int) ($fiche['abonment'] ?? 0),
        'diplomant'       => $alors($ins['graduated']) ? (int) $ins['graduated'] : (int) ($fiche['graduated'] ?? 0),
        'nouvel_etudiant' => $alors($ins['new_student']) ? (int) $ins['new_student'] : (int) ($fiche['new_student'] ?? 0),
    ];
}

/**
 * Frais prevus pour un contexte : le bareme, puis le tarif de l'annee si la
 * session est passee et n'a pas de bareme propre. Null sans tarif.
 *
 * @return array{bareme: array, montants: array<string, float>, sources: array<string, string>}|null
 */
function inscriptionFraisPrevus(PDO $dtb, array $ctx): ?array
{
    if ($ctx['niveau'] === null) {
        return null; // niveau d'une session passee introuvable : rien a calculer
    }
    $bareme = baremeLigne($dtb, $ctx['session_id'], [
        'std_status'  => $ctx['statut'],
        'std_mention' => $ctx['mention'],
        'level'       => $ctx['niveau'],
        'semester'    => $ctx['semestre'],
    ]);
    if (!$bareme) {
        return null;
    }

    $montants = inscriptionFraisCalcules($dtb, $bareme, $ctx);
    $sources  = array_fill_keys(array_keys($montants), 'bareme');
    if ($ctx['passee'] && empty($bareme['bareme_surcharge'])) {
        ['montants' => $montants, 'sources' => $sources] = inscriptionFraisDeLAnnee($dtb, $bareme, $montants, $ctx);
    }

    return ['bareme' => $bareme, 'montants' => $montants, 'sources' => $sources];
}

/**
 * Totaux des cours et des laboratoires d'une session, comme la fiche
 * d'inscription les affiche : deux laboratoires factures au plus.
 *
 * @return array{cout_totalCours: float, cout_totalLab: float}
 */
function inscriptionFraisCours(PDO $dtb, string $student_id, int $session_id): array
{
    $req = $dtb->prepare('SELECT cf.cours_cout, c.lab, c.cout_lab
        FROM t_2024_cours_finance cf
        LEFT JOIN t_2023_cours c ON c.id = cf.cours_id
        WHERE cf.student_id = :s AND cf.session_id = :ses AND cf.`remove` != 1
        ORDER BY cf.id');
    $req->execute(['s' => $student_id, 'ses' => $session_id]);

    $cours = 0.0;
    $labo = 0.0;
    $nbLabos = 0;
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $cours += (float) $l['cours_cout'];
        if ((int) $l['lab'] !== 0 && ++$nbLabos <= 2) {
            $labo += (float) $l['cout_lab'];
        }
    }

    return ['cout_totalCours' => $cours, 'cout_totalLab' => $labo];
}

/**
 * Un cours retire du releve, remis ou efface : sa ligne de facturation suit, pour
 * CET etudiant et CETTE session seulement, et les totaux de la session sont
 * recalcules depuis les lignes (inscriptionFraisCours, ce qu'affiche la fiche).
 *
 * Corrige le 20/10/2026 : retire depuis la page des notes (transcriptSS), le cours
 * gardait sa ligne de facturation et la fiche d'inscription le montrait encore ;
 * la restauration (recup-cours) et l'effacement (del-cours.definitive) visaient la
 * ligne du cours chez TOUS les etudiants (WHERE cours_id seul), et la restauration
 * ajoutait le cout aux totaux de toutes les sessions de l'etudiant.
 *
 * @param array<string, mixed> $note   la ligne de t_2023_notes (student_id, session_id, id_cours)
 * @param string               $action 'retirer' | 'remettre' | 'effacer'
 */
function inscriptionCoursFinanceSuivre(PDO $dtb, array $note, string $action, int $userId): void
{
    $cle = ['c' => (int) ($note['id_cours'] ?? 0), 's' => (string) ($note['student_id'] ?? ''), 'ses' => (int) ($note['session_id'] ?? 0)];
    if ($cle['c'] <= 0 || $cle['s'] === '' || $cle['ses'] <= 0) { return; }
    if ($action === 'effacer') {
        $dtb->prepare('DELETE FROM t_2024_cours_finance WHERE cours_id = :c AND student_id = :s AND session_id = :ses')->execute($cle);
    } else {
        $dtb->prepare('UPDATE t_2024_cours_finance SET `remove` = :r, last_change_user_id = :u, last_change_datetime = :d
            WHERE cours_id = :c AND student_id = :s AND session_id = :ses')
            ->execute($cle + ['r' => $action === 'retirer' ? 1 : 0, 'u' => $userId, 'd' => date('Y-m-d H:i:s')]);
    }
    $t = inscriptionFraisCours($dtb, $cle['s'], $cle['ses']);
    $dtb->prepare('UPDATE t_2024_etudiant_finace SET cout_totalCours = :cours, cout_totalLab = :labo WHERE student_id = :s AND session_id = :ses')
        ->execute(['cours' => $t['cout_totalCours'], 'labo' => $t['cout_totalLab'], 's' => $cle['s'], 'ses' => $cle['ses']]);
}

/**
 * Cree la ligne de frais d'une inscription qui n'en a pas.
 *
 * Rend l'id cree, ou false si une ligne existait deja. Les frais
 * supplementaires du bareme ne suivent que pour l'annee en cours : ceux
 * d'aujourd'hui n'etaient pas factures les annees passees.
 *
 * @return int|false
 */
function inscriptionFraisCreer(PDO $dtb, array $ctx, array $prevus, ?int $userId)
{
    if (inscFinanceSession($dtb, $ctx['student_id'], $ctx['session_id'])) {
        return false;
    }

    $aujourdhui = date('Y-m-d');
    $ins = $dtb->prepare(
        'INSERT INTO t_2024_etudiant_finace (
            student_id, session_id, mention, level, status,
            cout_fraix_generaux, cout_logement, cout_abonment,
            cout_fondDepot_dortoir, cout_frais_graduation, cout_costume, cout_voyage,
            cout_totalCours, cout_totalLab, mode_payement, `remove`,
            date_entry, last_change_user_id, last_change_datetime
         ) VALUES (
            :student_id, :session_id, :mention, :level, :status,
            :cout_fraix_generaux, :cout_logement, :cout_abonment,
            :cout_fondDepot_dortoir, :cout_frais_graduation, :cout_costume, :cout_voyage,
            :cout_totalCours, :cout_totalLab, "", 0,
            :le, :user_id, :le2
         )'
    );
    $ins->execute($prevus['montants'] + inscriptionFraisCours($dtb, $ctx['student_id'], $ctx['session_id']) + [
        'student_id' => $ctx['student_id'], 'session_id' => $ctx['session_id'],
        'mention' => $ctx['mention'], 'level' => $ctx['niveau'], 'status' => $ctx['statut'],
        'le' => $aujourdhui, 'le2' => $aujourdhui, 'user_id' => (int) $userId,
    ]);
    $id = (int) $dtb->lastInsertId();

    if (!$ctx['passee']) {
        fraisPersoAppliquer($dtb, $ctx['student_id'], $ctx['session_id'], [
            'std_status'  => $ctx['statut'],
            'std_mention' => $ctx['mention'],
            'level'       => $ctx['niveau'],
            'semester'    => $ctx['semestre'],
        ], $userId ?: null, [
            'diplomant'       => $ctx['diplomant'],
            'nouvel_etudiant' => $ctx['nouvel_etudiant'],
            'abonne'          => $ctx['abonnement'],
            'statut'          => $ctx['statut'],
        ]);
    }

    return $id;
}

/** Libelles des montants, pour l'avant / apres d'un recalcul. */
function inscriptionFraisLibelles(): array
{
    return [
        'cout_fraix_generaux'    => 'Frais généraux',
        'cout_logement'          => 'Logement',
        'cout_abonment'          => 'Cafétéria',
        'cout_fondDepot_dortoir' => 'Fond de dépôt',
        'cout_frais_graduation'  => 'Graduation',
        'cout_costume'           => 'Costume',
        'cout_voyage'            => "Voyage d'étude",
    ];
}

/**
 * Statut retenu pour lire le bareme.
 *
 * Meme normalisation qu'a l'inscription : un statut vide ou « Bungalow » est
 * facture comme un externe, le bareme ne connaissant que Interne et Externe.
 */
function inscriptionStatutBareme(?string $statut): string
{
    $statut = trim((string) $statut);

    return ($statut === '' || $statut === 'Bungalow') ? 'Externe' : $statut;
}
