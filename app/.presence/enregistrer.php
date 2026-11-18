<?php
/**
 * Registre de presence : les ecritures de l'enseignant (src/teacher.presence.php).
 *
 * Une action par appel, en POST, reponse JSON :
 *   reglage          seances prevues du cours (la base des 15 %)
 *   seance_creer     nouvelle seance : date, heure prevue, arrivee, message
 *   seance_modifier  heures et message d'une seance (§474)
 *   seance_supprimer une seance saisie par erreur, et ses pointages
 *   pointer          un etudiant : present, retard (minutes), absent
 *   justificatif     justificatif presente au retour (§476)
 *   tous_presents    les etudiants non encore pointes sont presents
 *
 * Chaque pointage recalcule le bilan de l'etudiant et, s'il vient de depasser
 * 15 % des seances prevues, le signale au registrariat (§476). Les ecritures
 * passent par la connexion auditee (data/audit.php) : qui, quoi, quand.
 *
 * Mode hors ligne (src/js/hors-ligne.js) : une ecriture faite sans reseau part au
 * retour du reseau, avec client_le (l'instant ou l'enseignant l'a faite, en
 * millisecondes, corrige du decalage de l'horloge de l'appareil). Elle est alors
 * datee de cet instant, et refusee en 409 (conflit) si la donnee visee a ete modifiee
 * depuis : le serveur fait foi. « forcer=1 » l'applique quand meme, a l'heure
 * actuelle. Chaque action est un « etat voulu » (pointer, justificatif, reglage...)
 * ou deja idempotente (seance_creer, tous_presents) : l'envoyer deux fois est sans
 * effet de plus.
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();
refuserEtudiant();

require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/presence.php');

header('Content-Type: application/json; charset=utf-8');

function presenceReponse(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    presenceReponse(['ok' => false, 'message' => 'Methode non autorisee.'], 405);
}
/* 400 et non 403 : src/js/session.js intercepte les 403 pour renvoyer a la
   connexion, alors qu'il suffit ici de recharger la page. */
if (!function_exists('verify_csrf') || !verify_csrf($_POST['csrf_token'] ?? null)) {
    presenceReponse(['ok' => false, 'csrf_expire' => true,
                     'message' => 'Jeton de securite expire. Rechargez la page.'], 400);
}

$action = (string) ($_POST['action'] ?? '');
$auteur = $_SESSION['user_id'] ?? null;

/* L'instant de l'action : celui de l'enseignant s'il etait hors ligne (au plus 14 jours
   en arriere, jamais dans le futur), sinon maintenant. */
$clientLe = null;
if (empty($_POST['forcer']) && isset($_POST['client_le']) && ctype_digit((string) $_POST['client_le'])) {
    $t = (int) round(((float) $_POST['client_le']) / 1000);
    if ($t <= time() + 120 && $t >= time() - 14 * 86400) { $clientLe = date('Y-m-d H:i:s', min($t, time())); }
}
$maintenant = $clientLe ?? date('Y-m-d H:i:s');

/** Refuse (409) une ecriture hors ligne dont la donnee a bouge depuis qu'elle a ete faite. */
function presenceConflit(?string $modifieLe, ?string $clientLe, string $quoi): void
{
    if ($clientLe !== null && $modifieLe !== null && $modifieLe > $clientLe) {
        presenceReponse(['ok' => false, 'conflit' => true,
                         'message' => $quoi . ' a ete modifie depuis (le ' . date('d/m \a H:i', strtotime($modifieLe)) . ') : ce changement fait hors ligne n\'a pas ete applique.'], 409);
    }
}

/* La feuille visee, directement ou par sa seance. */
$seance = null;
if (!empty($_POST['seance_id'])) {
    $seance = presenceSeanceCharger($dtb, (int) $_POST['seance_id']);
    if (!$seance) {
        presenceReponse(['ok' => false, 'message' => 'Seance introuvable.'], 404);
    }
    $feuille = presenceFeuilleCharger($dtb, (int) $seance['feuille_id']);
} else {
    $feuille = presenceFeuilleCharger($dtb, (int) ($_POST['feuille_id'] ?? 0));
}
if (!$feuille) {
    presenceReponse(['ok' => false, 'message' => 'Registre introuvable.'], 404);
}
/* Les identifiants se devinent : chaque ecriture verifie que le cours est
   bien celui de l'enseignant (le registrariat passe, pour corriger). */
if (!remiseEnseignantPossedeCours($dtb, (int) $feuille['cours_id'])) {
    presenceReponse(['ok' => false, 'message' => 'Ce cours ne fait pas partie de vos enseignements.'], 403);
}

/** Une heure « HH:MM » valide, ou null. */
function presenceHeure($brut): ?string
{
    $brut = trim((string) $brut);
    if ($brut === '') { return null; }
    if (!preg_match('~^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$~', $brut, $m)) {
        presenceReponse(['ok' => false, 'message' => 'Heure attendue au format HH:MM.'], 400);
    }
    return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
}

/** Le bilan a jour d'un etudiant, et le signalement s'il vient de depasser. */
function presenceBilanApres(PDO $dtb, array $feuille, string $studentId): array
{
    $bilan = presenceBilanEtudiant($dtb, $feuille, $studentId);
    $signale = presenceSignalerSiDepasse($dtb, $feuille, $studentId, $bilan);
    $info = presenceEtatInfo($bilan['etat']);
    return $bilan + ['vient_detre_signale' => $signale, 'libelle' => $info['libelle'],
                     'court' => $info['court'], 'couleur' => $info['couleur'],
                     'icone' => $info['icone']];
}

switch ($action) {

/* ------------------------------------------------------------------ reglage */
case 'reglage':
    $brut = trim((string) ($_POST['seances_prevues'] ?? ''));
    $prevues = $brut === '' ? null : (int) $brut;
    if ($prevues !== null && ($prevues < 1 || $prevues > 200)) {
        presenceReponse(['ok' => false, 'message' => 'Nombre de seances attendu entre 1 et 200.'], 400);
    }
    presenceConflit($feuille['modifie_le'] ?? null, $clientLe, 'Le nombre de seances prevues');
    $dtb->prepare('UPDATE t_presence_feuille SET seances_prevues = :p, modifie_le = :le WHERE id = :id')
        ->execute(['p' => $prevues, 'le' => $maintenant, 'id' => $feuille['id']]);
    $feuille['seances_prevues'] = $prevues;

    /* La base change : chaque bilan change, et un etudiant peut franchir le
       seuil sans nouvelle absence. */
    $bilans = presenceBilans($dtb, $feuille);
    foreach ($bilans as $sid => $b) { presenceSignalerSiDepasse($dtb, $feuille, (string) $sid, $b); }
    presenceReponse(['ok' => true, 'permises' => presenceAbsencesPermises($prevues),
                     'message' => 'Seances prevues enregistrees.']);

/* ------------------------------------------------------------ seance_creer */
case 'seance_creer':
    $date = trim((string) ($_POST['date_seance'] ?? ''));
    $t = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$t || $t->format('Y-m-d') !== $date) {
        presenceReponse(['ok' => false, 'message' => 'Date de seance invalide.'], 400);
    }
    if ($date > date('Y-m-d')) {
        presenceReponse(['ok' => false, 'message' => 'On ne fait pas l\'appel d\'une seance a venir.'], 400);
    }
    $prevue  = presenceHeure($_POST['heure_prevue'] ?? '');
    $arrivee = presenceHeure($_POST['arrivee_enseignant'] ?? '');

    /* Deux fois la meme seance fausserait les comptes : meme date et meme heure. */
    $doublon = $dtb->prepare('SELECT id FROM t_presence_seance
        WHERE feuille_id = :f AND date_seance = :d AND heure_prevue <=> :h LIMIT 1');
    $doublon->execute(['f' => $feuille['id'], 'd' => $date, 'h' => $prevue]);
    if ($id = $doublon->fetchColumn()) {
        presenceReponse(['ok' => true, 'seance_id' => (int) $id, 'existait' => true,
                         'message' => 'Cette seance existe deja : elle est ouverte.']);
    }

    $dtb->prepare('INSERT INTO t_presence_seance
            (feuille_id, date_seance, heure_prevue, arrivee_enseignant, message_retard, cree_par, cree_le, modifie_par, modifie_le)
            VALUES (:f, :d, :h, :a, :m, :par, :cree, :par2, :modif)')
        ->execute(['f' => $feuille['id'], 'd' => $date, 'h' => $prevue, 'a' => $arrivee,
                   'm' => !empty($_POST['message_retard']) ? 1 : 0,
                   'par' => $auteur, 'cree' => $maintenant, 'par2' => $auteur, 'modif' => $maintenant]);
    $seanceId = (int) $dtb->lastInsertId();
    presenceSeanceSynchroniser($dtb, $seanceId,
        presenceInscrits($dtb, (int) $feuille['cours_id'], $feuille['annee_scolaire'],
                         $feuille['semestre'] === null ? null : (int) $feuille['semestre']));
    presenceReponse(['ok' => true, 'seance_id' => $seanceId, 'message' => 'Seance ouverte.']);

/* --------------------------------------------------------- seance_modifier */
case 'seance_modifier':
    if (!$seance) { presenceReponse(['ok' => false, 'message' => 'Seance manquante.'], 400); }
    $prevue  = presenceHeure($_POST['heure_prevue'] ?? '');
    $arrivee = presenceHeure($_POST['arrivee_enseignant'] ?? '');
    $message = !empty($_POST['message_retard']) ? 1 : 0;
    $commentaire = trim((string) ($_POST['commentaire'] ?? ''));
    presenceConflit($seance['modifie_le'] ?? null, $clientLe, 'Cette seance');
    $dtb->prepare('UPDATE t_presence_seance SET heure_prevue = :h, arrivee_enseignant = :a,
            message_retard = :m, commentaire = :c, modifie_par = :par, modifie_le = :le WHERE id = :id')
        ->execute(['h' => $prevue, 'a' => $arrivee, 'm' => $message,
                   'c' => $commentaire === '' ? null : mb_substr($commentaire, 0, 255),
                   'par' => $auteur, 'le' => $maintenant, 'id' => $seance['id']]);

    /* Le §474 peut rendre la seance sans penalite, ou l'inverse : tous les
       bilans bougent. */
    $bilans = presenceBilans($dtb, $feuille);
    foreach ($bilans as $sid => $b) { presenceSignalerSiDepasse($dtb, $feuille, (string) $sid, $b); }
    $maj = presenceSeanceCharger($dtb, (int) $seance['id']);
    presenceReponse(['ok' => true, 'retard_enseignant' => presenceRetardEnseignant($maj),
                     'sans_penalite' => presenceSeanceSansPenalite($maj),
                     'message' => 'Seance enregistree.']);

/* -------------------------------------------------------- seance_supprimer */
case 'seance_supprimer':
    if (!$seance) { presenceReponse(['ok' => false, 'message' => 'Seance manquante.'], 400); }
    $dtb->prepare('DELETE FROM t_presence_seance WHERE id = :id')->execute(['id' => $seance['id']]);
    presenceReponse(['ok' => true, 'message' => 'Seance supprimee.']);

/* ------------------------------------------------------------------ pointer */
case 'pointer':
    if (!$seance) { presenceReponse(['ok' => false, 'message' => 'Seance manquante.'], 400); }
    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $statut = (string) ($_POST['statut'] ?? '');
    if (!in_array($statut, ['present', 'retard', 'absent', ''], true)) {
        presenceReponse(['ok' => false, 'message' => 'Statut attendu : present, retard ou absent.'], 400);
    }
    $minutes = null;
    if ($statut === 'retard') {
        $brut = trim((string) ($_POST['minutes_retard'] ?? ''));
        $minutes = $brut === '' ? PRESENCE_RETARD_TOLERE : (int) $brut;
        if ($minutes < 1 || $minutes > 240) {
            presenceReponse(['ok' => false, 'message' => 'Retard attendu en minutes (1 a 240).'], 400);
        }
    }
    if ($clientLe !== null) {
        $avant = $dtb->prepare('SELECT modifie_le FROM t_presence_ligne WHERE seance_id = :s AND student_id = :e');
        $avant->execute(['s' => $seance['id'], 'e' => $studentId]);
        presenceConflit($avant->fetchColumn() ?: null, $clientLe, 'Le pointage de cet etudiant');
    }
    $maj = $dtb->prepare('UPDATE t_presence_ligne SET statut = :s, minutes_retard = :m,
            modifie_par = :par, modifie_le = :le WHERE seance_id = :seance AND student_id = :e');
    $maj->execute(['s' => $statut === '' ? null : $statut, 'm' => $minutes,
                   'par' => $auteur, 'le' => $maintenant, 'seance' => $seance['id'], 'e' => $studentId]);
    $existe = $dtb->prepare('SELECT 1 FROM t_presence_ligne WHERE seance_id = :s AND student_id = :e');
    $existe->execute(['s' => $seance['id'], 'e' => $studentId]);
    if (!$existe->fetchColumn()) {
        presenceReponse(['ok' => false, 'message' => 'Cet etudiant n\'est pas sur la liste de cette seance.'], 404);
    }
    presenceReponse(['ok' => true, 'student_id' => $studentId,
                     'libelle_pointage' => presenceLibellePointage($statut === '' ? null : $statut, $minutes),
                     'categorie' => presenceCategorie($statut === '' ? null : $statut, $minutes),
                     'bilan' => presenceBilanApres($dtb, $feuille, $studentId),
                     'heure' => date('H:i')]);

/* ------------------------------------------------------------- justificatif */
case 'justificatif':
    if (!$seance) { presenceReponse(['ok' => false, 'message' => 'Seance manquante.'], 400); }
    $valeur = (string) ($_POST['justificatif'] ?? '');
    if (!in_array($valeur, ['', 'medical', 'autre'], true)) {
        presenceReponse(['ok' => false, 'message' => 'Justificatif inconnu.'], 400);
    }
    if ($clientLe !== null) {
        $avant = $dtb->prepare('SELECT modifie_le FROM t_presence_ligne WHERE seance_id = :s AND student_id = :e');
        $avant->execute(['s' => $seance['id'], 'e' => trim((string) ($_POST['student_id'] ?? ''))]);
        presenceConflit($avant->fetchColumn() ?: null, $clientLe, 'Le justificatif de cet etudiant');
    }
    $dtb->prepare('UPDATE t_presence_ligne SET justificatif = :j, modifie_par = :par, modifie_le = :le
            WHERE seance_id = :s AND student_id = :e')
        ->execute(['j' => $valeur === '' ? null : $valeur, 'par' => $auteur, 'le' => $maintenant,
                   's' => $seance['id'], 'e' => trim((string) ($_POST['student_id'] ?? ''))]);
    presenceReponse(['ok' => true, 'message' => 'Justificatif enregistre.']);

/* ------------------------------------------------------------ tous_presents */
case 'tous_presents':
    if (!$seance) { presenceReponse(['ok' => false, 'message' => 'Seance manquante.'], 400); }
    $maj = $dtb->prepare('UPDATE t_presence_ligne SET statut = "present", minutes_retard = NULL,
            modifie_par = :par, modifie_le = :le WHERE seance_id = :s AND statut IS NULL');
    $maj->execute(['par' => $auteur, 'le' => $maintenant, 's' => $seance['id']]);
    presenceReponse(['ok' => true, 'pointes' => $maj->rowCount(),
                     'message' => $maj->rowCount() . ' etudiant(s) marque(s) present(s).']);

default:
    presenceReponse(['ok' => false, 'message' => 'Action inconnue.'], 400);
}
