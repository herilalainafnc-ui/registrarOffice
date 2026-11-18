<?php
/**
 * Recalcul des frais d'une session deja inscrite.
 *
 * Les montants sont figes a l'inscription, comme les colonnes cout_* : la
 * fiche d'un dossier ancien ne bouge donc pas quand le bareme evolue. Mais
 * rien ne permettait de les remettre a jour apres un ajustement de tarif — le
 * bouton « Enregistrer » de l'etape 0 se desactive des que la session existe.
 * Le dossier 30188 l'a montre le 15/09/2026 : frais generaux a 165 000 alors
 * que le bareme de sa session porte 213 000, six jours plus tard.
 *
 * Le calcul est celui de l'inscription, sans copie : data/inscription.frais.php.
 * Les frais supplementaires du bareme sont reappliques au passage
 * (data/finance.frais.php).
 *
 * Reserve au niveau 3 et moins : c'est une ecriture d'argent, comme /finance.
 * Le module, lui, s'ouvre jusqu'au niveau 6.
 */

require_once(__DIR__ . '/_guard.php');
inscriptionGuard('POST');

require_once(__DIR__ . '/../../data/inscription.frais.php');
require_once(__DIR__ . '/../../data/finance.frais.php');

header('Content-Type: application/json; charset=utf-8');

if ((int) ($_SESSION['user_level'] ?? 99) > 3) {
    inscriptionRefus('Recalcul des frais réservé au registrariat.', 403);
}

$student_id = trim((string) ($_POST['student_id'] ?? $_GET['student_id'] ?? ''));
$session_id = (int) ($_POST['session_id'] ?? $_GET['session_id'] ?? 0);

if ($student_id === '' || $session_id <= 0) {
    inscriptionRefus('Étudiant ou session manquant.', 400);
}

try {
    /* --- Le dossier doit exister : on ne recalcule pas dans le vide -------- */
    $req = $dtb->prepare(
        'SELECT i.id, s.session_name, s.session_year, s.session_semester
           FROM t_2024_inscription_session i
           JOIN t_2023_session s ON s.session_id = i.session_id
          WHERE i.student_id = :s AND i.session_id = :ses AND i.remove != 1
          LIMIT 1'
    );
    $req->execute(['s' => $student_id, 'ses' => $session_id]);
    $inscription = $req->fetch(PDO::FETCH_ASSOC);
    if (!$inscription) {
        inscriptionRefus("Aucune inscription pour cette session : enregistrez-la d'abord à l'étape 1.", 422);
    }

    $req = $dtb->prepare('SELECT * FROM tbl_2024_etudiant WHERE student_id = :s ORDER BY remove ASC, id ASC LIMIT 1');
    $req->execute(['s' => $student_id]);
    $etudiant = $req->fetch(PDO::FETCH_ASSOC);
    if (!$etudiant) {
        inscriptionRefus('Étudiant introuvable.', 422);
    }

    /* Sigle de la filiere : le bareme est indexe par sigle, la fiche par
       libelle. Meme lecture qu'a l'inscription. */
    $req = $dtb->prepare('SELECT filiere_sigle FROM filiere WHERE filiere_description = :d LIMIT 1');
    $req->execute(['d' => $etudiant['etude_envisage']]);
    $sigle = (string) $req->fetchColumn();
    if ($sigle === '') {
        inscriptionRefus('Filière « ' . $etudiant['etude_envisage'] . ' » introuvable dans le catalogue.', 422);
    }

    /* Le calcul commun (data/inscription.frais.php) : pour une session passee,
       la mention, le niveau et le statut de l'inscription d'alors, et le tarif
       de l'annee plutot que le bareme d'aujourd'hui. */
    $ctx = inscriptionFraisContexte($dtb, $student_id, $session_id);
    $prevus = $ctx ? inscriptionFraisPrevus($dtb, $ctx) : null;
    if (!$prevus) {
        inscriptionRefus(
            'Aucun tarif pour ' . ($ctx['statut'] ?? '') . ' / ' . ($ctx['mention'] ?? $sigle) . ' / L' . ($ctx['niveau'] ?? '')
            . ' / semestre ' . ($ctx['semestre'] ?? '') . '. Complétez le barème dans Gestion Finance.',
            422
        );
    }
    $calcules = $prevus['montants'];
    $semestre = $ctx['semestre'];
    $statut   = $ctx['statut'];
    $niveau   = $ctx['niveau'];
    $sigle    = $ctx['mention'];

    /* Ligne financiere visee : celle que la fiche d'inscription retient aussi,
       pour que les deux parlent du meme enregistrement quand la table porte
       des doublons (133 couples au 16/09/2026). */
    $existante = inscFinanceSession($dtb, $student_id, $session_id);
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $aujourdhui = date('Y-m-d');

    $dtb->beginTransaction();

    if ($existante) {
        /* cout_totalCours et cout_totalLab ne sont pas touches : ils viennent
           des cours choisis, pas du bareme. */
        $maj = $dtb->prepare(
            'UPDATE t_2024_etudiant_finace SET
                mention = :mention, level = :level, status = :status,
                cout_fraix_generaux = :cout_fraix_generaux,
                cout_logement = :cout_logement,
                cout_abonment = :cout_abonment,
                cout_fondDepot_dortoir = :cout_fondDepot_dortoir,
                cout_frais_graduation = :cout_frais_graduation,
                cout_costume = :cout_costume,
                cout_voyage = :cout_voyage,
                last_change_user_id = :user_id,
                last_change_datetime = :le
              WHERE id = :id'
        );
        $maj->execute($calcules + [
            'mention' => $sigle, 'level' => $niveau, 'status' => $statut,
            'user_id' => $userId, 'le' => $aujourdhui, 'id' => (int) $existante['id'],
        ]);
        $creee = false;
    } else {
        /* Dossier inscrit sans ligne financiere — 675 cas au 16/09/2026, dont
           la fiche imprime « Frais de scolarité non disponibles ». Le recalcul
           la cree, avec les totaux des cours deja choisis et, pour l'annee en
           cours, les frais supplementaires du bareme. */
        inscriptionFraisCreer($dtb, $ctx, $prevus, $userId);
        $creee = true;
    }

    /* Frais supplementaires du bareme : memes criteres, meme contexte qu'a
       l'inscription. Pas pour une session passee : ceux d'aujourd'hui
       n'etaient pas factures alors. A la creation, inscriptionFraisCreer()
       s'en est charge. */
    $bilanPerso = ['ecrits' => 0, 'retires' => 0];
    if ($existante && !$ctx['passee']) {
        $bilanPerso = fraisPersoAppliquer($dtb, $student_id, $session_id, [
            'std_status'  => $statut,
            'std_mention' => $sigle,
            'level'       => $niveau,
            'semester'    => $semestre,
        ], $userId ?: null, [
            'diplomant'       => $ctx['diplomant'],
            'nouvel_etudiant' => $ctx['nouvel_etudiant'],
            'abonne'          => $ctx['abonnement'],
            'statut'          => $statut,
        ]);
    }

    $dtb->commit();

    /* --- Compte rendu : ce qui a change, ligne par ligne ------------------ */
    $lignes = [];
    $ecart = 0.0;
    foreach (inscriptionFraisLibelles() as $colonne => $libelle) {
        $avant = $existante ? (float) ($existante[$colonne] ?? 0) : 0.0;
        $apres = (float) $calcules[$colonne];
        if (abs($avant - $apres) < 0.01) {
            continue;
        }
        $lignes[] = ['libelle' => $libelle, 'avant' => $avant, 'apres' => $apres];
        $ecart += $apres - $avant;
    }

    $message = $creee
        ? 'Frais créés pour ' . $inscription['session_name'] . ' ' . $inscription['session_year'] . '.'
        : ($lignes === []
            ? 'Frais déjà à jour : rien à changer.'
            : count($lignes) . ' montant(s) mis à jour, écart de '
                . ($ecart > 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') . ' Ar.');
    if ($ctx['passee'] && array_diff($prevus['sources'], ['bareme'])) {
        $message .= ' Montants au tarif de l\'année ' . $ctx['annee_scolaire'] . ' (ce qui était facturé alors).';
    }

    echo json_encode([
        'success' => true,
        'creee' => $creee,
        'lignes' => $lignes,
        'ecart' => $ecart,
        'frais_supplementaires' => $bilanPerso,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($dtb->inTransaction()) {
        $dtb->rollBack();
    }
    error_log('recalculer-frais: ' . $e->getMessage());
    inscriptionRefus('Le recalcul a échoué. Aucun montant n\'a été modifié.', 500);
}
