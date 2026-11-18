<?php
/**
 * Autorisation de reinscription : statistiques, reinitialisation et annulation.
 *
 * DEUX DRAPEAUX, DEUX RESPONSABLES
 * --------------------------------
 * Un etudiant ne peut se reinscrire que si deux conditions sont reunies
 * (voir les modales de blocage de inscription/inscription.php) :
 *
 *   authorized_reinscription  la caisse l'autorise       niveaux 1 et 4
 *   dossier_ok                le registraire valide      niveaux 1 et 3
 *
 * Chaque role ne touche que sa colonne, case par case. La reinitialisation
 * globale, elle, est reservee au superadmin (decision du 14/09/2026) : il est
 * le seul a la voir, a l'enclencher et a l'annuler — voir
 * app/reinscription.save.php. Le filtre de colonnes par role est conserve dans
 * les fonctions ci-dessous en defense en profondeur, si la regle d'acces venait
 * a etre elargie.
 *
 * UNE ACTION DE MASSE DOIT ETRE REVERSIBLE
 * ----------------------------------------
 * Remettre ~800 drapeaux a zero bloque d'un coup la reinscription de plusieurs
 * centaines d'etudiants. Chaque lot est donc :
 *   - photographie ligne par ligne dans t_reinscription_reinitialisation
 *     (valeur, auteur, date d'origine), ce qui permet une annulation exacte ;
 *   - trace dans l'historique de chaque etudiant
 *     (t_student_modification_history), lisible depuis sa fiche.
 *
 * L'annulation ne restaure une ligne que si personne ne l'a modifiee depuis :
 * si un caissier re-autorise un etudiant apres la reinitialisation, annuler le
 * lot ne doit pas ecraser ce geste. La marque laissee par le lot (valeur 0,
 * meme auteur, meme horodatage a la seconde) sert de preuve.
 */

/**
 * Colonnes reinitialisables et leur perimetre.
 *
 * @return array<string, array{libelle:string, historique:string, par:string, date:string, niveaux:int[]}>
 */
function rsnColonnes(): array
{
    return [
        'authorized_reinscription' => [
            'libelle'    => 'Autorisation de la caisse',
            'historique' => 'Autorisation de réinscription',
            'par'        => 'authorized_by',
            'date'       => 'authorized_date',
            'niveaux'    => [1, 4],
        ],
        'dossier_ok' => [
            'libelle'    => 'Dossier validé par le registraire',
            'historique' => 'Dossier vérifié par le registraire',
            'par'        => 'dossier_checked_by',
            'date'       => 'dossier_checked_date',
            'niveaux'    => [1, 3],
        ],
    ];
}

/**
 * Colonnes qu'un niveau d'utilisateur peut modifier.
 *
 * @return array<int, string>
 */
function rsnColonnesPermises(int $niveau): array
{
    $permises = [];
    foreach (rsnColonnes() as $col => $def) {
        if (in_array($niveau, $def['niveaux'], true)) {
            $permises[] = $col;
        }
    }

    return $permises;
}

/**
 * Ne garde que des colonnes connues ET permises a ce niveau.
 *
 * Aucun nom de colonne ne part dans une requete sans passer par ce filtre.
 *
 * @param mixed $demandees
 * @return array<int, string>
 */
function rsnFiltrerColonnes($demandees, int $niveau): array
{
    if (!is_array($demandees)) {
        return [];
    }

    return array_values(array_intersect(
        rsnColonnesPermises($niveau),
        array_map('strval', $demandees)
    ));
}

/**
 * La table des lots existe-t-elle ?
 *
 * Sans elle la reinitialisation est refusee — une action de masse sans
 * annulation possible ne doit pas partir — mais le reste de la page
 * fonctionne. C'est ce qui permet de deployer les fichiers avant la migration.
 */
function rsnTableDisponible(PDO $dtb): bool
{
    static $dispo = null;
    if ($dispo !== null) {
        return $dispo;
    }

    try {
        $dispo = (bool) $dtb->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 't_reinscription_reinitialisation'"
        )->fetchColumn();
    } catch (Throwable $e) {
        $dispo = false;
    }

    return $dispo;
}

/**
 * Repartition des etudiants par etat de reinscription.
 *
 * Les quatre etats s'excluent et leur somme vaut le total : c'est ce qui
 * permet de les presenter comme des filtres sans chevauchement.
 *
 * @return array<string, int>
 */
function rsnStatistiques(PDO $dtb): array
{
    $r = $dtb->query(
        'SELECT COUNT(*) AS total,
                SUM(COALESCE(authorized_reinscription,0) = 1 AND COALESCE(dossier_ok,0) = 1) AS prets,
                SUM(COALESCE(authorized_reinscription,0) = 0 AND COALESCE(dossier_ok,0) = 1) AS attente_caisse,
                SUM(COALESCE(authorized_reinscription,0) = 1 AND COALESCE(dossier_ok,0) = 0) AS attente_dossier,
                SUM(COALESCE(authorized_reinscription,0) = 0 AND COALESCE(dossier_ok,0) = 0) AS aucun,
                SUM(COALESCE(authorized_reinscription,0) = 1) AS autorises,
                SUM(COALESCE(dossier_ok,0) = 1) AS dossiers_ok
           FROM tbl_2024_etudiant
          WHERE `remove` != 1'
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $stats = [];
    foreach (['total', 'prets', 'attente_caisse', 'attente_dossier', 'aucun', 'autorises', 'dossiers_ok'] as $k) {
        $stats[$k] = (int) ($r[$k] ?? 0);
    }

    return $stats;
}

/**
 * Ce qu'une reinitialisation changerait, sans rien ecrire.
 *
 * @param array<int, string> $colonnes deja filtrees
 * @return array{par_colonne: array<string,int>, etudiants:int, bloques:int}
 */
function rsnApercu(PDO $dtb, array $colonnes): array
{
    $parColonne = [];
    foreach ($colonnes as $col) {
        $parColonne[$col] = (int) $dtb->query(
            "SELECT COUNT(*) FROM tbl_2024_etudiant
              WHERE `remove` != 1 AND COALESCE(`$col`, 0) = 1"
        )->fetchColumn();
    }

    $etudiants = 0;
    $bloques = 0;
    if ($colonnes !== []) {
        $ou = implode(' OR ', array_map(static fn ($c) => "COALESCE(`$c`,0) = 1", $colonnes));
        $etudiants = (int) $dtb->query(
            "SELECT COUNT(*) FROM tbl_2024_etudiant WHERE `remove` != 1 AND ($ou)"
        )->fetchColumn();

        /* « Bloques » : ceux qui pouvaient se reinscrire et ne le pourront plus.
           C'est le chiffre qui mesure la portee reelle du geste. */
        $bloques = (int) $dtb->query(
            "SELECT COUNT(*) FROM tbl_2024_etudiant
              WHERE `remove` != 1
                AND COALESCE(authorized_reinscription,0) = 1
                AND COALESCE(dossier_ok,0) = 1"
        )->fetchColumn();
    }

    return ['par_colonne' => $parColonne, 'etudiants' => $etudiants, 'bloques' => $bloques];
}

/** Nom lisible d'un compte, ou chaine vide. */
function rsnNomUtilisateur(PDO $dtb, int $userId): string
{
    $req = $dtb->prepare('SELECT prenom, nom FROM compt_utilisateur WHERE id = :id LIMIT 1');
    $req->execute(['id' => $userId]);
    $u = $req->fetch(PDO::FETCH_ASSOC);

    return $u ? trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) : '';
}

/**
 * Remet a « non coche » les colonnes demandees pour tous les etudiants.
 *
 * Tout se fait dans une transaction, dans cet ordre :
 *   1. photographie des lignes cochees (pour l'annulation) ;
 *   2. trace dans l'historique de chaque etudiant ;
 *   3. remise a zero, avec l'auteur et un horodatage UNIQUE pour tout le lot.
 * Les deux premieres etapes selectionnent les lignes encore cochees : elles
 * doivent donc preceder la troisieme.
 *
 * @param array<int, string> $colonnes deja filtrees par rsnFiltrerColonnes()
 * @return array<string, mixed>
 */
function rsnReinitialiser(PDO $dtb, array $colonnes, int $userId, string $ip = '', string $ua = ''): array
{
    if ($colonnes === []) {
        return ['ok' => false, 'message' => 'Aucune colonne à réinitialiser, ou aucune que vous soyez autorisé à modifier.'];
    }
    if (!rsnTableDisponible($dtb)) {
        return ['ok' => false,
                'message' => "La réinitialisation n'est pas encore disponible sur cette base : "
                    . "appliquez data/migration_reinscription_reinitialisation.sql. "
                    . "Elle reste bloquée tant qu'elle ne peut pas être annulée."];
    }

    $defs = rsnColonnes();
    $lot = bin2hex(random_bytes(8));
    /* Un seul horodatage pour tout le lot, fixe en PHP : il sert de marque a
       l'annulation, qui compare a la seconde pres. NOW() evalue par ligne
       pourrait changer de seconde en cours de lot. */
    $maintenant = date('Y-m-d H:i:s');
    $ip = mb_substr($ip, 0, 45);
    $ua = mb_substr($ua, 0, 255);

    $dtb->beginTransaction();
    try {
        $parColonne = [];
        foreach ($colonnes as $col) {
            $par = $defs[$col]['par'];
            $date = $defs[$col]['date'];

            // 1. Photographie de l'etat d'origine.
            $photo = $dtb->prepare(
                "INSERT INTO t_reinscription_reinitialisation
                    (lot, student_db_id, student_id, colonne, ancienne_valeur,
                     ancien_par, ancienne_date, fait_par, fait_le)
                 SELECT :lot, e.id, e.student_id, :col, 1, e.`$par`, e.`$date`, :uid, :maintenant
                   FROM tbl_2024_etudiant e
                  WHERE e.`remove` != 1 AND COALESCE(e.`$col`, 0) = 1"
            );
            $photo->execute(['lot' => $lot, 'col' => $col, 'uid' => $userId, 'maintenant' => $maintenant]);
            $parColonne[$col] = $photo->rowCount();

            // 2. Trace dans l'historique de chaque etudiant. « Oui » / « Non » en
            //    toutes lettres : formatValueForDisplay() affiche « Non
            //    renseigne » pour toute valeur vide, donc pour « 0 ».
            $trace = $dtb->prepare(
                "INSERT INTO t_student_modification_history
                    (student_id, student_db_id, field_name, field_label, old_value, new_value,
                     action_type, action_by, action_date, ip_address, user_agent, commentaire)
                 SELECT e.student_id, e.id, :col, :libelle, 'Oui', 'Non',
                        'modification', :uid, :maintenant, :ip, :ua, :commentaire
                   FROM tbl_2024_etudiant e
                  WHERE e.`remove` != 1 AND COALESCE(e.`$col`, 0) = 1"
            );
            $trace->execute([
                'col' => $col, 'libelle' => $defs[$col]['historique'],
                'uid' => $userId, 'maintenant' => $maintenant, 'ip' => $ip, 'ua' => $ua,
                'commentaire' => 'Réinitialisation globale (lot ' . $lot . ')',
            ]);

            // 3. Remise a zero.
            $raz = $dtb->prepare(
                "UPDATE tbl_2024_etudiant
                    SET `$col` = 0, `$par` = :uid, `$date` = :maintenant
                  WHERE `remove` != 1 AND COALESCE(`$col`, 0) = 1"
            );
            $raz->execute(['uid' => $userId, 'maintenant' => $maintenant]);
        }

        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }

    $total = array_sum($parColonne);

    return [
        'ok'          => true,
        'lot'         => $lot,
        'par_colonne' => $parColonne,
        'total'       => $total,
        'par_nom'     => rsnNomUtilisateur($dtb, $userId),
        'date'        => date('d/m/Y', strtotime($maintenant)),
        'stats'       => rsnStatistiques($dtb),
        'message'     => $total === 0
            ? 'Rien à réinitialiser : aucun étudiant n\'était coché.'
            : $total . ' case(s) remise(s) à « non coché ». Vous pouvez annuler depuis le bandeau.',
    ];
}

/**
 * Annule un lot : restaure les drapeaux que personne n'a touches depuis.
 *
 * @return array<string, mixed>
 */
function rsnAnnuler(PDO $dtb, string $lot, int $userId, int $niveau, string $ip = '', string $ua = ''): array
{
    if (!preg_match('/^[0-9a-f]{16}$/', $lot)) {
        return ['ok' => false, 'message' => 'Lot invalide.'];
    }
    if (!rsnTableDisponible($dtb)) {
        return ['ok' => false, 'message' => "La table des lots n'existe pas sur cette base."];
    }

    $info = $dtb->prepare(
        'SELECT colonne, COUNT(*) AS n, MIN(annule_le) AS deja, MAX(fait_le) AS fait_le
           FROM t_reinscription_reinitialisation WHERE lot = :lot GROUP BY colonne'
    );
    $info->execute(['lot' => $lot]);
    $colonnesLot = $info->fetchAll(PDO::FETCH_ASSOC);

    if ($colonnesLot === []) {
        return ['ok' => false, 'message' => 'Lot introuvable.'];
    }

    /* On ne restaure que ce que ce niveau aurait pu reinitialiser : un
       registraire n'annule pas la partie « caisse » d'un lot du superadmin. */
    $permises = rsnColonnesPermises($niveau);
    $defs = rsnColonnes();
    $maintenant = date('Y-m-d H:i:s');
    $ip = mb_substr($ip, 0, 45);
    $ua = mb_substr($ua, 0, 255);

    $restaures = [];
    $ignores = 0;
    $refuses = [];

    $dtb->beginTransaction();
    try {
        foreach ($colonnesLot as $c) {
            $col = (string) $c['colonne'];
            if (!isset($defs[$col])) {
                continue;
            }
            if (!in_array($col, $permises, true)) {
                $refuses[] = $defs[$col]['libelle'];
                continue;
            }
            if ($c['deja'] !== null) {
                continue; // cette partie du lot est deja annulee
            }

            $par = $defs[$col]['par'];
            $date = $defs[$col]['date'];

            /* Lignes restaurables : encore a 0, avec l'auteur et l'horodatage
               poses par le lot. Toute autre valeur signifie qu'un humain est
               repasse depuis — on ne l'ecrase pas. */
            $cibles = $dtb->prepare(
                "SELECT s.id AS photo_id, e.id AS db_id
                   FROM t_reinscription_reinitialisation s
                   JOIN tbl_2024_etudiant e ON e.id = s.student_db_id
                  WHERE s.lot = :lot AND s.colonne = :col AND s.annule_le IS NULL
                    AND COALESCE(e.`$col`, 0) = 0
                    AND e.`$par` <=> s.fait_par
                    AND e.`$date` <=> s.fait_le"
            );
            $cibles->execute(['lot' => $lot, 'col' => $col]);
            $lignes = $cibles->fetchAll(PDO::FETCH_ASSOC);

            $ignores += (int) $c['n'] - count($lignes);

            if ($lignes !== []) {
                $ids = array_map('intval', array_column($lignes, 'db_id'));
                $marques = implode(',', array_fill(0, count($ids), '?'));

                $dtb->prepare(
                    "INSERT INTO t_student_modification_history
                        (student_id, student_db_id, field_name, field_label, old_value, new_value,
                         action_type, action_by, action_date, ip_address, user_agent, commentaire)
                     SELECT e.student_id, e.id, ?, ?, 'Non', 'Oui', 'modification', ?, ?, ?, ?, ?
                       FROM tbl_2024_etudiant e WHERE e.id IN ($marques)"
                )->execute(array_merge(
                    [$col, $defs[$col]['historique'], $userId, $maintenant, $ip, $ua,
                     'Annulation de la réinitialisation globale (lot ' . $lot . ')'],
                    $ids
                ));

                $dtb->prepare(
                    "UPDATE tbl_2024_etudiant e
                       JOIN t_reinscription_reinitialisation s
                         ON s.student_db_id = e.id AND s.lot = ? AND s.colonne = ?
                        SET e.`$col` = s.ancienne_valeur,
                            e.`$par` = s.ancien_par,
                            e.`$date` = s.ancienne_date
                      WHERE e.id IN ($marques)"
                )->execute(array_merge([$lot, $col], $ids));

                $restaures[$col] = $ids;
            }

            // La partie du lot est close, restauree ou non ligne a ligne.
            $dtb->prepare(
                'UPDATE t_reinscription_reinitialisation
                    SET annule_par = :uid, annule_le = :maintenant
                  WHERE lot = :lot AND colonne = :col AND annule_le IS NULL'
            )->execute(['uid' => $userId, 'maintenant' => $maintenant, 'lot' => $lot, 'col' => $col]);
        }

        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }

    /* Auteur et date restaures, pour que la page les reaffiche sous chaque
       interrupteur sans rechargement. */
    $traces = [];
    foreach ($restaures as $col => $ids) {
        $par = $defs[$col]['par'];
        $date = $defs[$col]['date'];
        $marques = implode(',', array_fill(0, count($ids), '?'));
        $req = $dtb->prepare(
            "SELECT e.id, e.`$date` AS le, TRIM(CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,''))) AS nom
               FROM tbl_2024_etudiant e
               LEFT JOIN compt_utilisateur u ON u.id = e.`$par`
              WHERE e.id IN ($marques)"
        );
        $req->execute($ids);
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $traces[$col][(int) $t['id']] = $t['nom'] !== ''
                ? $t['nom'] . ($t['le'] ? ' · ' . date('d/m/Y', strtotime($t['le'])) : '')
                : '';
        }
    }

    $nb = array_sum(array_map('count', $restaures));
    $message = $nb . ' case(s) restaurée(s) à leur état d\'avant la réinitialisation.';
    if ($ignores > 0) {
        $message .= ' ' . $ignores . ' laissée(s) telle(s) quelle(s) : elles avaient été modifiées depuis.';
    }
    if ($refuses !== []) {
        $message .= ' Non restauré, faute de droits : ' . implode(', ', $refuses) . '.';
    }

    return [
        'ok'        => true,
        'restaures' => $restaures,
        'traces'    => $traces,
        'ignores'   => $ignores,
        'stats'     => rsnStatistiques($dtb),
        'message'   => $message,
    ];
}

/**
 * Dernier lot non annule, s'il est recent.
 *
 * Alimente le bandeau d'annulation de la page : une reinitialisation faite par
 * erreur doit rester rattrapable meme apres un rechargement ou par un autre
 * poste, pas seulement pendant les quelques secondes d'une notification.
 *
 * @return array<string, mixed>|null
 */
function rsnDernierLot(PDO $dtb, int $jours = 7): ?array
{
    if (!rsnTableDisponible($dtb)) {
        return null;
    }

    $req = $dtb->prepare(
        "SELECT s.lot, MAX(s.fait_le) AS fait_le, MAX(s.fait_par) AS fait_par,
                COUNT(*) AS lignes,
                GROUP_CONCAT(DISTINCT s.colonne ORDER BY s.colonne) AS colonnes
           FROM t_reinscription_reinitialisation s
          WHERE s.annule_le IS NULL
            AND s.fait_le >= DATE_SUB(NOW(), INTERVAL :jours DAY)
          GROUP BY s.lot
          ORDER BY fait_le DESC
          LIMIT 1"
    );
    $req->bindValue('jours', $jours, PDO::PARAM_INT);
    $req->execute();
    $lot = $req->fetch(PDO::FETCH_ASSOC);

    if (!$lot) {
        return null;
    }

    $lot['par_nom'] = rsnNomUtilisateur($dtb, (int) $lot['fait_par']);
    $lot['colonnes'] = $lot['colonnes'] !== null ? explode(',', $lot['colonnes']) : [];
    $lot['lignes'] = (int) $lot['lignes'];

    return $lot;
}
