<?php
/* =============================================================================
   Presences aux cours — regles communes

   Le Bulletin academique (ASSIDUITE, §472 a §476) demande aux enseignants de
   tenir un registre de presence regulier, et fixe ce que valent retards et
   absences. Ce fichier porte ces regles, et ce que la page enseignant, la vue
   du registrariat, l'espace etudiant et le point d'entree AJAX partagent :
   les ecrire quatre fois aurait laisse quatre versions du meme reglement.

   §476, pour un etudiant et un cours :
     - moins de 5 minutes de retard : a l'heure ;
     - de 5 a 9 minutes : un retard, et deux retards comptent une absence ;
     - 10 minutes ou plus : une absence ;
     - au-dela de 15 % des seances prevues, il doit quitter le cours, et le
       registraire en est informe. Ni une inscription tardive ni une maladie
       n'excusent l'absence ; un certificat medical permet seulement de
       refaire les tests et examens manques.
   §474 : si l'enseignant arrive plus de 10 minutes apres l'heure sans avoir
   prevenu, les etudiants peuvent partir sans penalite. Les absences et les
   retards de cette seance ne comptent donc pas.

   Tables : data/migration_presence.sql.

   Usage :
       require_once __DIR__ . '/presence.php';
   ========================================================================== */

require_once __DIR__ . '/remise.notes.php';   // REMISE_SQL_SEMESTRE, acces au cours, libelles

if (!defined('PRESENCE_LOADED')) {
define('PRESENCE_LOADED', true);

define('PRESENCE_RETARD_TOLERE', 5);    // §476 : en dessous, l'etudiant est a l'heure
define('PRESENCE_RETARD_ABSENCE', 10);  // §476 : a partir de, le retard est une absence
define('PRESENCE_TAUX_MAX', 15);        // §476 : pourcentage des seances prevues
define('PRESENCE_ATTENTE', 10);         // §474 : minutes d'attente de l'enseignant

/* -----------------------------------------------------------------------------
   Regles
   -------------------------------------------------------------------------- */

/**
 * Ce que compte un pointage au sens du §476.
 *
 * C'est la duree du retard qui decide, pas le bouton choisi : un « retard »
 * de 12 minutes est une absence, un « retard » de 3 minutes n'en est pas un.
 * Un retard sans duree compte comme un retard de 5 minutes.
 *
 * @return string|null 'present' | 'retard' | 'absence', null si non pointe
 */
function presenceCategorie(?string $statut, $minutes = null): ?string
{
    switch ($statut) {
        case 'present':
            return 'present';
        case 'absent':
            return 'absence';
        case 'retard':
            $m = ($minutes === null || $minutes === '') ? PRESENCE_RETARD_TOLERE : (int) $minutes;
            if ($m >= PRESENCE_RETARD_ABSENCE) { return 'absence'; }
            if ($m >= PRESENCE_RETARD_TOLERE) { return 'retard'; }
            return 'present';
        default:
            return null;
    }
}

/** Minutes de retard de l'enseignant, ou null si une des deux heures manque. */
function presenceRetardEnseignant(array $seance): ?int
{
    if (empty($seance['heure_prevue']) || empty($seance['arrivee_enseignant'])) {
        return null;
    }
    $prevue  = strtotime('1970-01-01 ' . $seance['heure_prevue']);
    $arrivee = strtotime('1970-01-01 ' . $seance['arrivee_enseignant']);
    if ($prevue === false || $arrivee === false) {
        return null;
    }
    return max(0, (int) floor(($arrivee - $prevue) / 60));
}

/** §474 : plus de 10 minutes de retard de l'enseignant, sans message. */
function presenceSeanceSansPenalite(array $seance): bool
{
    $retard = presenceRetardEnseignant($seance);
    return $retard !== null && $retard > PRESENCE_ATTENTE && empty($seance['message_retard']);
}

/**
 * Absences permises : 15 % des seances prevues, arrondi vers le bas. Au-dela,
 * l'etudiant a « depasse le maximum » (§476). 30 seances : 4 permises, la 5e
 * depasse (16,7 %) ; 20 seances : 3 permises (15 % pile), la 4e depasse.
 */
function presenceAbsencesPermises($prevues): ?int
{
    $n = (int) $prevues;
    if ($n <= 0) {
        return null;
    }
    return (int) floor($n * PRESENCE_TAUX_MAX / 100 + 1e-9);
}

/**
 * Bilan d'un etudiant dans un cours.
 *
 * @param array $pointages [['statut' => ..., 'minutes_retard' => ..., 'sans_penalite' => bool], ...]
 * @param int|null $prevues seances prevues du cours
 * @return array{pointees:int, presences:int, retards:int, absences:int,
 *               sans_penalite:int, retards_convertis:int, comptees:int,
 *               retard_en_attente:bool, prevues:?int, permises:?int,
 *               taux:?float, etat:string}
 *   etat : 'ok' | 'limite' (une absence de plus et il depasse) | 'depasse'
 *          | 'inconnu' (seances prevues non indiquees)
 */
function presenceBilan(array $pointages, $prevues): array
{
    $b = ['pointees' => 0, 'presences' => 0, 'retards' => 0, 'absences' => 0, 'sans_penalite' => 0];

    foreach ($pointages as $p) {
        $categorie = presenceCategorie($p['statut'] ?? null, $p['minutes_retard'] ?? null);
        if ($categorie === null) {
            continue;
        }
        $b['pointees']++;
        if ($categorie !== 'present' && !empty($p['sans_penalite'])) {
            $b['sans_penalite']++;   // §474 : l'enseignant n'est pas venu a l'heure
            continue;
        }
        if ($categorie === 'present') {
            $b['presences']++;
        } elseif ($categorie === 'retard') {
            $b['retards']++;
        } else {
            $b['absences']++;
        }
    }

    $b['retards_convertis'] = intdiv($b['retards'], 2);
    $b['comptees']          = $b['absences'] + $b['retards_convertis'];
    $b['retard_en_attente'] = $b['retards'] % 2 === 1;   // le prochain retard fera une absence
    $b['prevues']           = (int) $prevues > 0 ? (int) $prevues : null;
    $b['permises']          = presenceAbsencesPermises($prevues);
    $b['taux']              = $b['prevues'] ? round($b['comptees'] * 100 / $b['prevues'], 1) : null;

    if ($b['permises'] === null) {
        $b['etat'] = 'inconnu';
    } elseif ($b['comptees'] > $b['permises']) {
        $b['etat'] = 'depasse';
    } elseif ($b['permises'] > 0 && $b['comptees'] === $b['permises']) {
        $b['etat'] = 'limite';
    } else {
        $b['etat'] = 'ok';
    }
    return $b;
}

/** @return array{libelle:string, court:string, couleur:string, icone:string} */
function presenceEtatInfo(string $etat): array
{
    switch ($etat) {
        case 'depasse':
            return ['libelle' => 'Au-delà de 15 % : doit quitter le cours (§476)', 'court' => '> 15 %',
                    'couleur' => '#f87171', 'icone' => 'bi bi-x-octagon-fill'];
        case 'limite':
            return ['libelle' => 'À la limite : une absence de plus dépasse 15 %', 'court' => 'Limite',
                    'couleur' => '#fbbf24', 'icone' => 'bi bi-exclamation-triangle-fill'];
        case 'inconnu':
            return ['libelle' => 'Séances prévues non indiquées', 'court' => '—',
                    'couleur' => '#94a3b8', 'icone' => 'bi bi-question-circle'];
        default:
            return ['libelle' => 'Dans la limite des 15 %', 'court' => 'OK',
                    'couleur' => '#4ade80', 'icone' => 'bi bi-check-circle-fill'];
    }
}

/** Libelle court d'un pointage, pour les listes (« Retard 7 min »). */
function presenceLibellePointage(?string $statut, $minutes = null): string
{
    $categorie = presenceCategorie($statut, $minutes);
    if ($categorie === null) {
        return 'Non pointé';
    }
    if ($statut === 'retard') {
        $m = ($minutes === null || $minutes === '') ? PRESENCE_RETARD_TOLERE : (int) $minutes;
        $suffixe = $categorie === 'absence' ? ' (compte absence)' : ($categorie === 'present' ? ' (toléré)' : '');
        return 'Retard ' . $m . ' min' . $suffixe;
    }
    return $categorie === 'present' ? 'Présent' : 'Absent';
}

/** « Lundi 22 septembre 2025 », sans dependre de l'extension intl. */
function presenceDateLongue(string $date, bool $avecAnnee = true): string
{
    $t = strtotime($date);
    if ($t === false) {
        return $date;
    }
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois  = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
              'septembre', 'octobre', 'novembre', 'décembre'];
    return $jours[(int) date('w', $t)] . ' ' . date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1]
         . ($avecAnnee ? ' ' . date('Y', $t) : '');
}

/* -----------------------------------------------------------------------------
   Feuilles
   -------------------------------------------------------------------------- */

function presenceFeuilleCharger(PDO $dtb, int $id): ?array
{
    $stmt = $dtb->prepare('SELECT * FROM t_presence_feuille WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function presenceFeuilleTrouver(PDO $dtb, int $coursId, string $annee, ?int $semestre): ?array
{
    $stmt = $dtb->prepare('SELECT * FROM t_presence_feuille
        WHERE cours_id = :c AND annee_scolaire = :a AND semestre <=> :s LIMIT 1');
    $stmt->execute(['c' => $coursId, 'a' => $annee, 's' => $semestre]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** La feuille du cours, creee au premier acces. */
function presenceFeuilleOuvrir(PDO $dtb, int $coursId, string $annee, ?int $semestre): ?array
{
    $feuille = presenceFeuilleTrouver($dtb, $coursId, $annee, $semestre);
    if ($feuille) {
        return $feuille;
    }
    $stmt = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $coursId]);
    $cours = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cours) {
        return null;
    }
    $maintenant = date('Y-m-d H:i:s');
    $dtb->prepare('INSERT IGNORE INTO t_presence_feuille
            (cours_id, sigle, titre_cours, teacher_uid, annee_scolaire, semestre, cree_par, cree_le, modifie_le)
            VALUES (:c, :sigle, :titre, :teacher, :a, :s, :par, :cree, :modif)')
        ->execute([
            'c' => $coursId, 'sigle' => $cours['Sigle'] ?? null, 'titre' => $cours['title'] ?? null,
            'teacher' => $cours['id_teacher'] ?? null, 'a' => $annee, 's' => $semestre,
            'par' => $_SESSION['user_id'] ?? null, 'cree' => $maintenant, 'modif' => $maintenant,
        ]);
    return presenceFeuilleTrouver($dtb, $coursId, $annee, $semestre);
}

/**
 * Etudiants inscrits au cours : la meme regle que la feuille de notes
 * (remiseSynchroniserLignes), pour que l'appel et la remise portent sur les
 * memes etudiants. Ni suspendus, ni retires de l'universite.
 */
function presenceInscrits(PDO $dtb, int $coursId, string $annee, ?int $semestre): array
{
    $params = ['cours' => $coursId, 'annee' => $annee];
    $clauseSemestre = '';
    if ($semestre !== null) {
        $clauseSemestre = ' AND ' . REMISE_SQL_SEMESTRE . ' = :sem';
        $params['sem'] = $semestre;
    }
    $stmt = $dtb->prepare('SELECT n.student_id, MAX(e.student_nom) AS student_nom,
            MAX(e.student_prenom) AS student_prenom, MAX(e.annee_etude) AS annee_etude
        FROM t_2023_notes n
        LEFT JOIN t_2023_session s ON s.session_id = n.session_id
        LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        WHERE n.id_cours = :cours AND n.annee_scolaire = :annee AND n.remove = 0' . $clauseSemestre . '
          AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
        GROUP BY n.student_id
        ORDER BY student_nom ASC, student_prenom ASC, n.student_id ASC');
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------------------------------------------------------
   Seances
   -------------------------------------------------------------------------- */

/** Seances d'une feuille, de la plus recente a la plus ancienne, avec leurs comptes. */
function presenceSeances(PDO $dtb, int $feuilleId): array
{
    $stmt = $dtb->prepare('SELECT s.*,
            COUNT(l.id) AS nb_lignes,
            SUM(l.statut IS NOT NULL) AS nb_pointees,
            SUM(l.statut = "absent") AS nb_absents,
            SUM(l.statut = "retard") AS nb_retards
        FROM t_presence_seance s
        LEFT JOIN t_presence_ligne l ON l.seance_id = s.id
        WHERE s.feuille_id = :f
        GROUP BY s.id
        ORDER BY s.date_seance DESC, s.heure_prevue DESC, s.id DESC');
    $stmt->execute(['f' => $feuilleId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function presenceSeanceCharger(PDO $dtb, int $seanceId): ?array
{
    $stmt = $dtb->prepare('SELECT s.*, f.cours_id, f.annee_scolaire, f.semestre, f.seances_prevues
        FROM t_presence_seance s JOIN t_presence_feuille f ON f.id = s.feuille_id
        WHERE s.id = :id LIMIT 1');
    $stmt->execute(['id' => $seanceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Heure de debut prevue par l'emploi du temps pour ce cours ce jour-la, s'il
 * y a un creneau (une seance datee l'emporte sur le creneau hebdomadaire).
 * L'emploi du temps ecrit l'annee « 2025-2026 », les notes « 2025 - 2026 ».
 */
function presenceHeureCreneau(PDO $dtb, int $coursId, string $annee, ?int $semestre, string $date): ?string
{
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $t = strtotime($date);
    if ($t === false) {
        return null;
    }
    $stmt = $dtb->prepare('SELECT heure_debut FROM t_2024_emploi_du_temps
        WHERE cours_id = :c AND REPLACE(annee_scolaire, " ", "") = REPLACE(:a, " ", "")
          AND (:s IS NULL OR semester = :s2)
          AND COALESCE(statut, "planifie") <> "annule"
          AND (date_seance = :d OR (date_seance IS NULL AND jour_semaine = :j))
        ORDER BY date_seance IS NULL, heure_debut
        LIMIT 1');
    $stmt->execute(['c' => $coursId, 'a' => $annee, 's' => $semestre, 's2' => $semestre,
                    'd' => date('Y-m-d', $t), 'j' => $jours[(int) date('w', $t)]]);
    $h = $stmt->fetchColumn();
    return $h ? substr((string) $h, 0, 5) : null;
}

/** Cree les lignes des etudiants inscrits qui manquent a la seance. Ne supprime rien. */
function presenceSeanceSynchroniser(PDO $dtb, int $seanceId, array $inscrits): int
{
    $insert = $dtb->prepare('INSERT IGNORE INTO t_presence_ligne (seance_id, student_id) VALUES (:s, :e)');
    $ajoutees = 0;
    foreach ($inscrits as $i) {
        $insert->execute(['s' => $seanceId, 'e' => $i['student_id']]);
        $ajoutees += $insert->rowCount();
    }
    return $ajoutees;
}

/**
 * Pointages d'une seance, avec le nom des etudiants. tbl_2024_etudiant garde
 * une ligne par annee d'inscription : on agrege pour n'avoir qu'une ligne
 * par pointage.
 */
function presenceLignesSeance(PDO $dtb, int $seanceId): array
{
    $stmt = $dtb->prepare('SELECT l.*, MAX(e.student_nom) AS student_nom,
            MAX(e.student_prenom) AS student_prenom, MAX(e.annee_etude) AS annee_etude
        FROM t_presence_ligne l
        LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = l.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        WHERE l.seance_id = :s
        GROUP BY l.id
        ORDER BY student_nom ASC, student_prenom ASC, l.student_id ASC');
    $stmt->execute(['s' => $seanceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------------------------------------------------------
   Bilans
   -------------------------------------------------------------------------- */

/**
 * Pointages d'une feuille, seance par seance, avec ce qu'il faut pour le
 * bilan (§474 compris). Filtres possibles sur un etudiant.
 */
function presencePointages(PDO $dtb, int $feuilleId, ?string $studentId = null): array
{
    $sql = 'SELECT l.student_id, l.statut, l.minutes_retard, l.justificatif,
                   s.id AS seance_id, s.date_seance, s.heure_prevue, s.arrivee_enseignant, s.message_retard
            FROM t_presence_ligne l
            JOIN t_presence_seance s ON s.id = l.seance_id
            WHERE s.feuille_id = :f' . ($studentId !== null ? ' AND l.student_id = :e' : '') . '
            ORDER BY s.date_seance ASC, s.heure_prevue ASC, s.id ASC';
    $params = ['f' => $feuilleId];
    if ($studentId !== null) { $params['e'] = $studentId; }
    $stmt = $dtb->prepare($sql);
    $stmt->execute($params);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lignes as &$l) {
        $l['sans_penalite'] = presenceSeanceSansPenalite($l);
    }
    unset($l);
    return $lignes;
}

/** Bilan de chaque etudiant d'une feuille : student_id => bilan. */
function presenceBilans(PDO $dtb, array $feuille): array
{
    $parEtudiant = [];
    foreach (presencePointages($dtb, (int) $feuille['id']) as $p) {
        $parEtudiant[$p['student_id']][] = $p;
    }
    $bilans = [];
    foreach ($parEtudiant as $sid => $pointages) {
        $bilans[$sid] = presenceBilan($pointages, $feuille['seances_prevues']);
    }
    return $bilans;
}

function presenceBilanEtudiant(PDO $dtb, array $feuille, string $studentId): array
{
    return presenceBilan(presencePointages($dtb, (int) $feuille['id'], $studentId), $feuille['seances_prevues']);
}

/**
 * Etat d'assiduite des etudiants d'un cours, pour la feuille de notes :
 * student_id => etat. Vide tant que le cours n'a pas de registre.
 */
function presenceEtatsCours(PDO $dtb, int $coursId, string $annee, ?int $semestre): array
{
    $feuille = presenceFeuilleTrouver($dtb, $coursId, $annee, $semestre);
    if (!$feuille) {
        return [];
    }
    return array_map(static fn ($b) => $b['etat'], presenceBilans($dtb, $feuille));
}

/* -----------------------------------------------------------------------------
   Signalements (§476 : « l'enseignant doit informer le registraire »)
   -------------------------------------------------------------------------- */

/**
 * Signale au registrariat l'etudiant qui vient de depasser 15 %. Un seul
 * signalement par etudiant et par cours : les absences suivantes ne le
 * repetent pas, le registrariat voit le compte a jour dans sa vue.
 */
function presenceSignalerSiDepasse(PDO $dtb, array $feuille, string $studentId, array $bilan): bool
{
    if ($bilan['etat'] !== 'depasse') {
        return false;
    }
    $stmt = $dtb->prepare('INSERT IGNORE INTO t_presence_signalement
        (feuille_id, student_id, absences, seances_prevues, signale_le, signale_par)
        VALUES (:f, :e, :a, :p, :le, :par)');
    $stmt->execute([
        'f' => $feuille['id'], 'e' => $studentId, 'a' => $bilan['comptees'],
        'p' => $bilan['prevues'], 'le' => date('Y-m-d H:i:s'), 'par' => $_SESSION['user_id'] ?? null,
    ]);
    return $stmt->rowCount() > 0;
}

/** Signalements d'une feuille : student_id => ligne. */
function presenceSignalementsFeuille(PDO $dtb, int $feuilleId): array
{
    $stmt = $dtb->prepare('SELECT * FROM t_presence_signalement WHERE feuille_id = :f');
    $stmt->execute(['f' => $feuilleId]);
    $sortie = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $sortie[$s['student_id']] = $s;
    }
    return $sortie;
}

/** Nombre de signalements que le registrariat n'a pas encore traites (pastille du menu). */
function presenceSignalementsEnAttente(PDO $dtb): int
{
    try {
        return (int) $dtb->query('SELECT COUNT(*) FROM t_presence_signalement WHERE traite_le IS NULL')->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // table pas encore migree : le menu reste utilisable
    }
}

} // PRESENCE_LOADED
