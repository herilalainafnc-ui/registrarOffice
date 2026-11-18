<?php
/**
 * Enregistrement d'une saisie de remise de notes (enseignant).
 *
 * Appele a chaque cellule quittee dans la grille : la feuille se sauvegarde
 * seule, sans bouton. Chaque changement passe par remiseJournaliser(), qui
 * garde l'ancienne valeur, la nouvelle, l'auteur et l'heure — c'est la trace
 * que le registrariat consulte avant de valider.
 *
 * Rien n'est ecrit dans t_2023_notes ici : seule la validation du registrariat
 * y touche (voir data/remise.notes.php).
 *
 * Mode hors ligne (src/js/hors-ligne.js) : une saisie faite sans reseau arrive plus
 * tard avec client_le (l'instant ou l'enseignant l'a faite, en millisecondes). Elle est
 * alors datee de cet instant (ligne, journal), et refusee en 409 si la cellule — ou la
 * ponderation — a ete modifiee depuis : le serveur fait foi. « forcer=1 » l'applique
 * quand meme, a l'heure actuelle. Une feuille devenue lecture seule reste refusee
 * (409 « fige »), sans « forcer » possible. Chaque saisie est un « etat voulu » : la
 * rejouer est sans effet de plus.
 *
 * Colonnes : les cinq composantes de base sont des champs de t_remise_notes_ligne ; l'enseignant peut en
 * ajouter autant qu'il veut (data/remise.notes.php, remiseColonnesInstaller). Une colonne ajoutee a une cle
 * choisie par le navigateur (« x:ab12cd34 ») : cible=colonne_creer (idempotent), colonne_renommer,
 * ponderation (cle « px_<cle> »), saisie d'une cellule (champ = « x:<cle> »), vider_colonne (la supprime).
 */
require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion();
refuserEtudiant();

require_once(__DIR__ . '/../../data/backdb.php');
require_once(__DIR__ . '/../../data/remise.notes.php');

header('Content-Type: application/json; charset=utf-8');

function remiseReponse(array $charge, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    remiseReponse(['ok' => false, 'message' => 'Methode non autorisee.'], 405);
}

/* Sans jeton, une page tierce ouverte par un enseignant connecte pourrait
   modifier ses notes a son insu. 400 et non 403 : src/js/session.js intercepte
   les 403 pour rediriger vers la connexion. */
if (!function_exists('verify_csrf') || !verify_csrf($_POST['csrf_token'] ?? null)) {
    remiseReponse(['ok' => false, 'csrf_expire' => true,
                   'message' => 'Jeton de securite expire. Rechargez la page.'], 400);
}

$remiseId = (int) ($_POST['remise_id'] ?? 0);
$remise   = $remiseId ? remiseCharger($dtb, $remiseId) : null;
if (!$remise) {
    remiseReponse(['ok' => false, 'message' => 'Remise introuvable.'], 404);
}

if (!remiseEnseignantPossedeCours($dtb, (int) $remise['cours_id'])) {
    remiseReponse(['ok' => false, 'message' => 'Ce cours ne fait pas partie de vos enseignements.'], 403);
}

if (!remisePeutSaisir($remise)) {
    remiseReponse([
        'ok'      => false,
        'fige'    => true,
        'message' => 'Cette remise a ete transmise au registrariat : elle est en lecture seule.',
    ], 409);
}

/* L'instant de la saisie : celui de l'enseignant s'il etait hors ligne (au plus 14 jours
   en arriere, jamais dans le futur), sinon maintenant. */
$clientLe = null;
if (empty($_POST['forcer']) && isset($_POST['client_le']) && ctype_digit((string) $_POST['client_le'])) {
    $t = (int) round(((float) $_POST['client_le']) / 1000);
    if ($t <= time() + 120 && $t >= time() - 14 * 86400) { $clientLe = date('Y-m-d H:i:s', min($t, time())); }
}
$maintenant = $clientLe ?? date('Y-m-d H:i:s');

/** Refuse (409) une saisie hors ligne dont la donnee a ete modifiee depuis. */
function remiseConflit(?string $modifieLe, ?string $clientLe, string $quoi): void
{
    if ($clientLe !== null && $modifieLe !== null && $modifieLe > $clientLe) {
        remiseReponse(['ok' => false, 'conflit' => true,
                       'message' => $quoi . ' a ete modifie depuis (le ' . date('d/m \a H:i', strtotime($modifieLe))
                                  . ') : cette saisie faite hors ligne n\'a pas ete appliquee.'], 409);
    }
}

/** Le nom d'une colonne : espaces compactes, 60 caracteres, jamais vide. */
function remiseLibelleColonne($brut): string
{
    $l = trim((string) preg_replace('/\s+/u', ' ', (string) $brut));
    return $l === '' ? 'Colonne' : mb_substr($l, 0, 60);
}

/** Ce que le journal (40 caracteres) retient d'une colonne ajoutee. */
function remiseChampJournal(array $extra): string
{
    return mb_substr('Col. ' . $extra['libelle'], 0, 40);
}

/** La note sur 20 de chaque etudiant, d'apres la ponderation et les composantes en base. */
function remiseRecalcul(PDO $dtb, int $remiseId): array
{
    $remise = remiseCharger($dtb, $remiseId);
    $recalcul = [];
    foreach (remiseLignes($dtb, $remiseId) as $ligne) {
        $calcul = remiseCalculerNote($ligne, $remise);
        $recalcul[$ligne['student_id']] = ['valeur' => $calcul['valeur'], 'etat' => $calcul['etat'], 'detail' => $calcul['detail']];
    }
    return [$remise, $recalcul];
}

/* -------------------------------------------------------------------------
   Cas 1 : ponderation du cours
   ---------------------------------------------------------------------- */
if (($_POST['cible'] ?? '') === 'ponderation') {
    $colonnes = [];
    $params   = ['id' => $remise['id'], 'le' => $maintenant];

    if ($clientLe !== null) {
        $q = $dtb->prepare("SELECT MAX(date_action) FROM t_remise_notes_journal WHERE remise_id = :r AND action = 'ponderation'");
        $q->execute(['r' => $remiseId]);
        remiseConflit($q->fetchColumn() ?: null, $clientLe, 'La ponderation');
    }

    /* La somme des ponderations (cinq composantes et colonnes ajoutees) ne depasse JAMAIS 100 % : on la calcule
       avant d'ecrire quoi que ce soit, et on refuse en bloc ce qui la ferait depasser — y compris un changement
       fait hors ligne qui arrive apres une autre modification. Une feuille deja au-dela (anciennes donnees) peut
       encore etre corrigee : un changement qui n'augmente pas la somme passe. */
    $lirePoids = static function ($brut): float {
        $v = str_replace(',', '.', trim((string) $brut));
        if ($v === '' || !is_numeric($v)) {
            $v = 0;
        }
        return round(max(0, min(100, (float) $v)), 2);
    };
    $totalApres = 0.0;
    foreach (remiseComposantes() as $meta) {
        $totalApres += array_key_exists($meta['poids'], $_POST) ? $lirePoids($_POST[$meta['poids']]) : (float) $remise[$meta['poids']];
    }
    foreach ($remise['extras'] as $x) {
        $totalApres += array_key_exists('px_' . $x['cle'], $_POST) ? $lirePoids($_POST['px_' . $x['cle']]) : (float) $x['poids'];
    }
    $totalApres = round($totalApres, 2);
    if ($totalApres - 100.0 > 0.001 && $totalApres >= remiseTotalPoids($remise)) {
        remiseReponse(['ok' => false, 'plafond' => true,
                       'message' => 'La somme des ponderations ferait ' . rtrim(rtrim(number_format($totalApres, 2, ',', ''), '0'), ',')
                                  . ' % : elle ne peut pas depasser 100 %. Rien n\'a ete modifie.'], 422);
    }

    foreach (remiseComposantes() as $champ => $meta) {
        if (!array_key_exists($meta['poids'], $_POST)) {
            continue;
        }
        $valeur = str_replace(',', '.', trim((string) $_POST[$meta['poids']]));
        if ($valeur === '' || !is_numeric($valeur)) {
            $valeur = 0;
        }
        $valeur = round(max(0, min(100, (float) $valeur)), 2);

        if ((float) $remise[$meta['poids']] !== $valeur) {
            remiseJournaliser($dtb, $remiseId, 'ponderation', $meta['poids'],
                $remise[$meta['poids']], $valeur, null, null, $maintenant);
        }
        $colonnes[] = '`' . $meta['poids'] . '` = :' . $meta['poids'];
        $params[$meta['poids']] = $valeur;
    }

    /* Les colonnes ajoutees : « px_<cle> ». */
    $extrasMaj = false;
    foreach ($remise['extras'] as $x) {
        $k = 'px_' . $x['cle'];
        if (!array_key_exists($k, $_POST)) {
            continue;
        }
        $valeur = str_replace(',', '.', trim((string) $_POST[$k]));
        if ($valeur === '' || !is_numeric($valeur)) {
            $valeur = 0;
        }
        $valeur = round(max(0, min(100, (float) $valeur)), 2);
        if ((float) $x['poids'] !== $valeur) {
            remiseJournaliser($dtb, $remiseId, 'ponderation', remiseChampJournal($x), $x['poids'], $valeur, null, null, $maintenant);
            $dtb->prepare('UPDATE t_remise_notes_colonne SET poids = :p WHERE id = :id')->execute(['p' => $valeur, 'id' => $x['id']]);
            $extrasMaj = true;
        }
    }

    if ($colonnes) {
        $dtb->prepare('UPDATE t_remise_notes SET ' . implode(', ', $colonnes)
                    . ', modifie_le = :le WHERE id = :id')->execute($params);
    } elseif ($extrasMaj) {
        $dtb->prepare('UPDATE t_remise_notes SET modifie_le = :le WHERE id = :id')->execute(['le' => $maintenant, 'id' => $remiseId]);
    }

    /* Changer une ponderation recalcule toutes les notes : le client remplace
       la colonne « /20 » entiere plutot que de recharger la page. */
    [$remise, $recalcul] = remiseRecalcul($dtb, $remiseId);

    remiseReponse([
        'ok'           => true,
        'total_poids'  => remiseTotalPoids($remise),
        'recalcul'     => $recalcul,
        'modifie_le'   => $maintenant,
        'message'      => 'Ponderation enregistree.',
    ]);
}

/* -------------------------------------------------------------------------
   Cas 1 ter : creer / renommer une colonne ajoutee
   ---------------------------------------------------------------------- */
if (($_POST['cible'] ?? '') === 'colonne_creer') {
    $cle = strtolower(trim((string) ($_POST['cle'] ?? '')));
    if (!remiseCleExtraValide($cle)) {
        remiseReponse(['ok' => false, 'message' => 'Cle de colonne invalide.'], 400);
    }
    $libelle = remiseLibelleColonne($_POST['libelle'] ?? '');
    $existe  = remiseExtraTrouver($dtb, $remiseId, $cle);
    if (!$existe) {
        if (count($remise['extras']) >= 60) {
            remiseReponse(['ok' => false, 'message' => 'Une feuille ne peut pas depasser 60 colonnes ajoutees.'], 422);
        }
        $rang = (int) $dtb->query('SELECT COALESCE(MAX(rang), 0) + 1 FROM t_remise_notes_colonne WHERE remise_id = ' . $remiseId)->fetchColumn();
        $dtb->prepare('INSERT IGNORE INTO t_remise_notes_colonne (remise_id, cle, rang, libelle, poids, cree_le)
            VALUES (:r, :c, :rang, :l, 0, :le)')->execute(['r' => $remiseId, 'c' => $cle, 'rang' => $rang, 'l' => $libelle, 'le' => $maintenant]);
        remiseJournaliser($dtb, $remiseId, 'ponderation', remiseChampJournal(['libelle' => $libelle]), null, 0, null, null, $maintenant);
        remiseToucher($dtb, $remiseId);
    }
    remiseReponse(['ok' => true, 'cle' => $cle, 'libelle' => $existe['libelle'] ?? $libelle, 'existait' => (bool) $existe,
                   'modifie_le' => $maintenant, 'message' => 'Colonne ajoutee.']);
}

if (($_POST['cible'] ?? '') === 'colonne_renommer') {
    $cle   = strtolower(trim((string) ($_POST['cle'] ?? '')));
    $extra = remiseCleExtraValide($cle) ? remiseExtraTrouver($dtb, $remiseId, $cle) : null;
    if (!$extra) {
        remiseReponse(['ok' => false, 'message' => 'Cette colonne n\'existe plus : rechargez la feuille.'], 404);
    }
    $libelle = remiseLibelleColonne($_POST['libelle'] ?? '');
    $dtb->prepare('UPDATE t_remise_notes_colonne SET libelle = :l WHERE id = :id')->execute(['l' => $libelle, 'id' => $extra['id']]);
    remiseToucher($dtb, $remiseId);
    remiseReponse(['ok' => true, 'cle' => $cle, 'libelle' => $libelle, 'modifie_le' => $maintenant, 'message' => 'Colonne renommee.']);
}

/* -------------------------------------------------------------------------
   Cas 1 bis : retirer une colonne de la feuille (ponderation a 0, notes effacees)

   La feuille de calcul n'affiche que les colonnes utilisees : une colonne retiree
   n'a plus de ponderation ni de notes. Chaque note effacee est journalisee.
   ---------------------------------------------------------------------- */
if (($_POST['cible'] ?? '') === 'vider_colonne') {
    $colonne     = trim((string) ($_POST['champ'] ?? ''));

    /* Une colonne ajoutee : elle disparait avec ses notes (chacune est journalisee). */
    if (strncmp($colonne, 'x:', 2) === 0) {
        $cle   = substr($colonne, 2);
        $extra = remiseCleExtraValide($cle) ? remiseExtraTrouver($dtb, $remiseId, $cle) : null;
        if (!$extra) {
            remiseReponse(['ok' => true, 'deja' => true, 'total_poids' => remiseTotalPoids($remise), 'recalcul' => remiseRecalcul($dtb, $remiseId)[1],
                           'message' => 'Colonne deja retiree.']);
        }
        if ($clientLe !== null) {
            $q = $dtb->prepare('SELECT MAX(modifie_le) FROM t_remise_notes_valeur WHERE colonne_id = :c AND valeur IS NOT NULL');
            $q->execute(['c' => $extra['id']]);
            remiseConflit($q->fetchColumn() ?: null, $clientLe, 'Cette colonne');
        }
        $q = $dtb->prepare('SELECT l.id, l.student_id, v.valeur FROM t_remise_notes_valeur v
            JOIN t_remise_notes_ligne l ON l.id = v.ligne_id WHERE v.colonne_id = :c AND v.valeur IS NOT NULL');
        $q->execute(['c' => $extra['id']]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            remiseJournaliser($dtb, $remiseId, 'saisie', remiseChampJournal($extra), $l['valeur'], null, (int) $l['id'], $l['student_id'], $maintenant);
        }
        if ((float) $extra['poids'] !== 0.0) {
            remiseJournaliser($dtb, $remiseId, 'ponderation', remiseChampJournal($extra), $extra['poids'], 0, null, null, $maintenant);
        }
        $dtb->prepare('DELETE FROM t_remise_notes_valeur WHERE colonne_id = :c')->execute(['c' => $extra['id']]);
        $dtb->prepare('DELETE FROM t_remise_notes_colonne WHERE id = :c')->execute(['c' => $extra['id']]);
        remiseToucher($dtb, $remiseId);
        [$remise, $recalcul] = remiseRecalcul($dtb, $remiseId);
        remiseReponse(['ok' => true, 'total_poids' => remiseTotalPoids($remise), 'recalcul' => $recalcul,
                       'modifie_le' => $maintenant, 'message' => 'Colonne retiree.']);
    }

    $composantes = remiseComposantes();
    if (!isset($composantes[$colonne])) {
        remiseReponse(['ok' => false, 'message' => 'Colonne inconnue.'], 400);
    }
    $meta = $composantes[$colonne];

    if ($clientLe !== null) {
        $q = $dtb->prepare('SELECT MAX(modifie_le) FROM t_remise_notes_ligne WHERE remise_id = :r AND `' . $colonne . '` IS NOT NULL');
        $q->execute(['r' => $remiseId]);
        remiseConflit($q->fetchColumn() ?: null, $clientLe, 'Cette colonne');
    }

    $q = $dtb->prepare('SELECT id, student_id, `' . $colonne . '` AS valeur FROM t_remise_notes_ligne
        WHERE remise_id = :r AND `' . $colonne . '` IS NOT NULL');
    $q->execute(['r' => $remiseId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
        remiseJournaliser($dtb, $remiseId, 'saisie', $colonne, $l['valeur'], null, (int) $l['id'], $l['student_id'], $maintenant);
    }
    $dtb->prepare('UPDATE t_remise_notes_ligne SET `' . $colonne . '` = NULL, modifie_le = :le
        WHERE remise_id = :r AND `' . $colonne . '` IS NOT NULL')->execute(['le' => $maintenant, 'r' => $remiseId]);

    if ((float) $remise[$meta['poids']] !== 0.0) {
        remiseJournaliser($dtb, $remiseId, 'ponderation', $meta['poids'], $remise[$meta['poids']], 0, null, null, $maintenant);
    }
    $dtb->prepare('UPDATE t_remise_notes SET `' . $meta['poids'] . '` = 0, modifie_le = :le WHERE id = :id')
        ->execute(['le' => $maintenant, 'id' => $remiseId]);

    [$remise, $recalcul] = remiseRecalcul($dtb, $remiseId);
    remiseReponse([
        'ok'          => true,
        'total_poids' => remiseTotalPoids($remise),
        'recalcul'    => $recalcul,
        'modifie_le'  => $maintenant,
        'message'     => 'Colonne retiree.',
    ]);
}

/* -------------------------------------------------------------------------
   Cas 2 : une cellule de la grille
   ---------------------------------------------------------------------- */
$studentId = trim((string) ($_POST['student_id'] ?? ''));
$champ     = trim((string) ($_POST['champ'] ?? ''));
$brut      = trim((string) ($_POST['valeur'] ?? ''));

/* Pas de note_finale : la note sur 20 est calculee depuis les composantes
   et la ponderation du cours, elle ne se saisit pas (remiseCalculerNote). */
$champsAutorises = array_merge(
    array_keys(remiseComposantes()),
    ['statut_special', 'commentaire']
);
if ($champ === 'note_finale') {
    remiseReponse(['ok' => false, 'message' => 'La note sur 20 se calcule a partir des composantes : '
                 . 'saisissez-les, elle se mettra a jour. Rechargez la page si la colonne est encore modifiable.'], 400);
}
/* Une colonne ajoutee : champ « x:<cle> ». */
$extra = null;
if (strncmp($champ, 'x:', 2) === 0) {
    $cleExtra = substr($champ, 2);
    $extra = remiseCleExtraValide($cleExtra) ? remiseExtraTrouver($dtb, $remiseId, $cleExtra) : null;
    if (!$extra) {
        remiseReponse(['ok' => false, 'message' => 'Cette colonne n\'existe plus : rechargez la feuille.'], 404);
    }
}
if ($studentId === '' || ($extra === null && !in_array($champ, $champsAutorises, true))) {
    remiseReponse(['ok' => false, 'message' => 'Champ inconnu.'], 400);
}

$stmt = $dtb->prepare('SELECT * FROM t_remise_notes_ligne
    WHERE remise_id = :r AND student_id = :s LIMIT 1');
$stmt->execute(['r' => $remiseId, 's' => $studentId]);
$ligne = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ligne) {
    remiseReponse(['ok' => false, 'message' => 'Cet etudiant n\'est pas inscrit a ce cours.'], 404);
}

if ($champ === 'commentaire') {
    $valeur = $brut === '' ? null : mb_substr($brut, 0, 255);

} elseif ($champ === 'statut_special') {
    /* V (valide sans note), E (echec sans note), NN (pas encore note) :
       les codes de data/notes.statut.php. Vide = retour au calcul normal. */
    $code = strtoupper($brut);
    if ($code !== '' && !in_array($code, ['V', 'E', 'NN'], true)) {
        remiseReponse(['ok' => false, 'message' => 'Statut attendu : V, E ou NN.'], 400);
    }
    $valeur = $code === '' ? null : $code;

} else {
    // Une composante : une note sur 20, ou rien.
    if ($brut === '') {
        $valeur = null;
    } else {
        $normalise = str_replace(',', '.', $brut);
        if (!is_numeric($normalise)) {
            remiseReponse(['ok' => false, 'message' => 'Note attendue entre 0 et 20.'], 400);
        }
        $valeur = round((float) $normalise, 2);
        if ($valeur < 0 || $valeur > 20) {
            remiseReponse(['ok' => false,
                           'message' => 'Note hors bareme : ' . $valeur . ' (attendu 0 a 20).'], 400);
        }
    }
}

/* L'ancienne valeur de la cellule et l'instant de sa derniere modification. */
if ($extra) {
    $q = $dtb->prepare('SELECT valeur, modifie_le FROM t_remise_notes_valeur WHERE ligne_id = :l AND colonne_id = :c');
    $q->execute(['l' => $ligne['id'], 'c' => $extra['id']]);
    $v = $q->fetch(PDO::FETCH_ASSOC);
    $actuelle  = $v ? $v['valeur'] : null;
    $modifieLe = $v['modifie_le'] ?? null;
} else {
    $actuelle  = $ligne[$champ];
    $modifieLe = $ligne['modifie_le'] ?? null;
}

/* Hors ligne : rien a refuser si la cellule a deja cette valeur (la saisie est un etat voulu). */
if ($clientLe !== null) {
    $identique = $actuelle === null || $actuelle === ''
        ? $valeur === null
        : ($valeur !== null && (is_numeric($valeur) && is_numeric($actuelle)
            ? abs((float) $actuelle - (float) $valeur) < 0.001
            : (string) $actuelle === (string) $valeur));
    if (!$identique) { remiseConflit($modifieLe, $clientLe, 'Cette cellule'); }
}

remiseJournaliser($dtb, $remiseId, 'saisie', $extra ? remiseChampJournal($extra) : $champ, $actuelle, $valeur,
    (int) $ligne['id'], $studentId, $maintenant);

if ($extra) {
    $dtb->prepare('INSERT INTO t_remise_notes_valeur (ligne_id, colonne_id, valeur, modifie_le) VALUES (:l, :c, :v, :le)
        ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), modifie_le = VALUES(modifie_le)')
        ->execute(['l' => $ligne['id'], 'c' => $extra['id'], 'v' => $valeur, 'le' => $maintenant]);
    $dtb->prepare('UPDATE t_remise_notes_ligne SET modifie_le = :le WHERE id = :id')->execute(['le' => $maintenant, 'id' => $ligne['id']]);
} else {
    $sets   = ['`' . $champ . '` = :valeur', 'modifie_le = :le'];
    $params = ['valeur' => $valeur, 'le' => $maintenant, 'id' => $ligne['id']];
    $dtb->prepare('UPDATE t_remise_notes_ligne SET ' . implode(', ', $sets) . ' WHERE id = :id')
        ->execute($params);
}

remiseToucher($dtb, $remiseId);

// Renvoyer la note recalculee : la colonne « /20 » se met a jour sans rechargement.
$ligne  = remiseLigne($dtb, $remiseId, $studentId);
$calcul = remiseCalculerNote($ligne, $remise);

remiseReponse([
    'ok'         => true,
    'student_id' => $studentId,
    'champ'      => $champ,
    'valeur'     => $valeur,
    'note'       => ['valeur' => $calcul['valeur'], 'etat' => $calcul['etat'], 'detail' => $calcul['detail']],
    'modifie_le' => $maintenant,
    'heure'      => date('H:i:s', strtotime($maintenant)),
]);
