<?php
/* =============================================================================
   Lecture du journal d'activité

   Pour src/settings.logs.php (la page) et app/audit.liste.php,
   app/audit.detail.php (les données). L'écriture vit dans data/audit.php.
   ========================================================================== */

require_once __DIR__ . '/audit.php';

function journalTypes(): array
{
    return [
        'connexion'       => ['libelle' => 'Connexion',          'icone' => 'bi-box-arrow-in-right'],
        'echec_connexion' => ['libelle' => 'Échec de connexion', 'icone' => 'bi-shield-exclamation'],
        'deconnexion'     => ['libelle' => 'Déconnexion',        'icone' => 'bi-box-arrow-right'],
        'modification'    => ['libelle' => 'Modification',       'icone' => 'bi-pencil-square'],
        'action'          => ['libelle' => 'Action',             'icone' => 'bi-lightning-charge'],
        'export'          => ['libelle' => 'Export',             'icone' => 'bi-file-earmark-arrow-down'],
        'consultation'    => ['libelle' => 'Consultation',       'icone' => 'bi-eye'],
        'evenement'       => ['libelle' => 'Événement',          'icone' => 'bi-info-circle'],
    ];
}

function journalRole(?int $niveau): ?string
{
    $roles = defined('ROLE_LABELS') ? ROLE_LABELS : [
        1 => 'Superadmin', 2 => 'Administrateur', 3 => 'Registraire', 4 => 'Comptabilité',
        5 => 'Média', 6 => 'Chef de mention', 7 => 'Professeur', 8 => 'Étudiant', 9 => 'Work Education',
    ];
    return $niveau !== null ? ($roles[$niveau] ?? 'Niveau ' . $niveau) : null;
}

function journalDisponible(PDO $dtb): bool
{
    try {
        return (bool) $dtb->query("SHOW TABLES LIKE 't_audit_requete'")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** Filtres reçus en GET, nettoyés. Un filtre invalide est ignoré, jamais interprété. */
function journalFiltres(array $source): array
{
    $date = static function ($v): ?string {
        $v = trim((string) $v);
        return preg_match('~^\d{4}-\d{2}-\d{2}$~', $v) ? $v : null;
    };
    $types = array_values(array_intersect(
        array_filter(explode(',', (string) ($source['types'] ?? ''))),
        array_keys(journalTypes())
    ));
    $user = trim((string) ($source['user'] ?? ''));

    return [
        'q'     => mb_substr(trim((string) ($source['q'] ?? '')), 0, 100),
        'user'  => ctype_digit($user) ? (int) $user : null,   // 0 = anonyme
        'types' => $types,
        'du'    => $date($source['du'] ?? ''),
        'au'    => $date($source['au'] ?? ''),
        'table' => preg_match('~^\w{1,64}$~', (string) ($source['table'] ?? '')) ? (string) $source['table'] : null,
        'ip'    => mb_substr(preg_replace('~[^0-9a-fA-F:.]~', '', (string) ($source['ip'] ?? '')), 0, 45),
        'avant' => ctype_digit((string) ($source['avant'] ?? '')) ? (int) $source['avant'] : null,
        'apres' => ctype_digit((string) ($source['apres'] ?? '')) ? (int) $source['apres'] : null,
    ];
}

/** Liste paginée par id décroissant. */
function journalListe(PDO $dtb, array $f, int $limite = 50): array
{
    $where = [];
    $args  = [];

    if ($f['q'] !== '') {
        $colonnes = ['libelle', 'url', 'user_pseudo', 'user_nom', 'ip', 'tables_touchees', 'donnees'];
        $parts = [];
        foreach ($colonnes as $i => $c) {
            $parts[] = "r.`$c` LIKE :q$i";
            $args["q$i"] = '%' . $f['q'] . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($f['user'] !== null) {
        if ($f['user'] === 0) {
            $where[] = 'r.user_id IS NULL';
        } else {
            $where[] = 'r.user_id = :user';
            $args['user'] = $f['user'];
        }
    }
    if ($f['types']) {
        $marques = [];
        foreach ($f['types'] as $i => $t) {
            $marques[] = ":t$i";
            $args["t$i"] = $t;
        }
        $where[] = 'r.type_action IN (' . implode(',', $marques) . ')';
    }
    if ($f['du']) {
        $where[] = 'r.date_action >= :du';
        $args['du'] = $f['du'] . ' 00:00:00';
    }
    if ($f['au']) {
        $where[] = 'r.date_action < DATE_ADD(:au, INTERVAL 1 DAY)';
        $args['au'] = $f['au'];
    }
    if ($f['table']) {
        $where[] = 'r.tables_touchees LIKE :table';
        $args['table'] = '%,' . $f['table'] . ',%';
    }
    if ($f['ip'] !== '') {
        $where[] = 'r.ip LIKE :ip';
        $args['ip'] = $f['ip'] . '%';
    }
    if ($f['avant']) {
        $where[] = 'r.id < :avant';
        $args['avant'] = $f['avant'];
    }
    if ($f['apres']) {
        $where[] = 'r.id > :apres';
        $args['apres'] = $f['apres'];
    }

    $sql = 'SELECT r.id, r.date_action, r.duree_ms, r.type_action, r.libelle, r.user_id, r.user_pseudo, r.user_nom,
                   r.user_niveau, r.methode, r.chemin, r.statut_http, r.ajax, r.ip, r.appareil, r.nb_modifications,
                   r.tables_touchees, (r.erreur IS NOT NULL) AS a_erreur
              FROM t_audit_requete r'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY r.id DESC LIMIT ' . ($limite + 1);
    $st = $dtb->prepare($sql);
    $st->execute($args);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);

    $suivant = null;
    if (count($lignes) > $limite) {
        array_pop($lignes);
        $suivant = (int) end($lignes)['id'];
    }
    return ['items' => array_map('journalFormaterLigne', $lignes), 'suivant' => $suivant];
}

function journalFormaterLigne(array $r): array
{
    $types  = journalTypes();
    $tables = array_values(array_filter(explode(',', (string) $r['tables_touchees'])));
    return [
        'id'        => (int) $r['id'],
        'date'      => $r['date_action'],
        'jour'      => substr($r['date_action'], 0, 10),
        'heure'     => substr($r['date_action'], 11, 8),
        'type'      => $r['type_action'],
        'type_libelle' => $types[$r['type_action']]['libelle'] ?? $r['type_action'],
        'libelle'   => $r['libelle'],
        'utilisateur' => $r['user_id'] === null ? null : [
            'id'     => (int) $r['user_id'],
            'pseudo' => $r['user_pseudo'],
            'nom'    => $r['user_nom'],
            'role'   => journalRole($r['user_niveau'] !== null ? (int) $r['user_niveau'] : null),
        ],
        'methode'   => $r['methode'],
        'chemin'    => $r['chemin'],
        'statut'    => $r['statut_http'] !== null ? (int) $r['statut_http'] : null,
        'duree'     => $r['duree_ms'] !== null ? (int) $r['duree_ms'] : null,
        'ip'        => $r['ip'],
        'appareil'  => $r['appareil'],
        'ajax'      => (bool) $r['ajax'],
        'nb_modifs' => (int) $r['nb_modifications'],
        'tables'    => array_map([Audit::class, 'libelleTable'], $tables),
        'erreur'    => (bool) $r['a_erreur'],
    ];
}

/** Chiffres du jour, à l'heure de Madagascar comme les dates du journal. */
function journalStats(PDO $dtb): array
{
    $jour = (new DateTime('now', new DateTimeZone('Indian/Antananarivo')))->format('Y-m-d');
    $st = $dtb->prepare('SELECT type_action, COUNT(*) n, SUM(nb_modifications) m, COUNT(DISTINCT user_id) u
                           FROM t_audit_requete WHERE date_action >= :j GROUP BY type_action');
    $st->execute(['j' => $jour . ' 00:00:00']);
    $parType = [];
    $actions = 0;
    $modifs  = 0;
    foreach ($st as $r) {
        $parType[$r['type_action']] = (int) $r['n'];
        $actions += (int) $r['n'];
        $modifs  += (int) $r['m'];
    }
    $st = $dtb->prepare('SELECT COUNT(DISTINCT user_id) FROM t_audit_requete WHERE date_action >= :j AND user_id IS NOT NULL');
    $st->execute(['j' => $jour . ' 00:00:00']);

    return [
        'jour'          => $jour,
        'actions'       => $actions,
        'connexions'    => $parType['connexion'] ?? 0,
        'echecs'        => $parType['echec_connexion'] ?? 0,
        'modifications' => $modifs,
        'utilisateurs'  => (int) $st->fetchColumn(),
    ];
}

function journalDetail(PDO $dtb, int $id): ?array
{
    $st = $dtb->prepare('SELECT * FROM t_audit_requete WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    $json = static fn($v) => $v === null ? null : json_decode((string) $v, true);
    $types = journalTypes();

    $st = $dtb->prepare('SELECT * FROM t_audit_modification WHERE requete_id = ? ORDER BY ordre');
    $st->execute([$id]);
    $modifications = array_map(static function (array $m) use ($json) {
        return [
            'ordre'         => (int) $m['ordre'],
            'date'          => $m['date_action'],
            'operation'     => $m['operation'],
            'table'         => $m['table_nom'],
            'table_libelle' => $m['table_nom'] !== null ? Audit::libelleTable($m['table_nom']) : null,
            'lignes'        => $m['lignes'] !== null ? (int) $m['lignes'] : null,
            'sql'           => $m['requete_sql'],
            'parametres'    => $json($m['parametres']),
            'changements'   => $json($m['changements']),
            'transaction'   => $m['transaction_statut'],
            'erreur'        => $m['erreur'],
        ];
    }, $st->fetchAll(PDO::FETCH_ASSOC));

    $evenements = $json($r['evenements']) ?: [];
    $libellesEvenements = [
        'login_success' => 'Connexion par mot de passe', 'login_failed' => 'Mot de passe ou identifiant refusé',
        'logout' => 'Déconnexion', 'login_remember' => 'Reconnexion automatique (« se souvenir de moi »)',
        'google_login_success' => 'Connexion avec Google', 'google_login_rejected' => 'Connexion Google refusée',
        'google_login_user_not_found' => 'Connexion Google : aucun compte pour cet email',
        'google_token_invalid_audience' => 'Connexion Google : jeton invalide',
        'user_create_attempt' => 'Création de compte', 'user_update_attempt' => 'Modification de compte',
        'annonce_created' => 'Annonce créée', 'annonce_updated' => 'Annonce modifiée',
        'annonce_deleted' => 'Annonce supprimée', 'annonce_toggled' => 'Annonce activée ou désactivée',
    ];
    foreach ($evenements as &$e) {
        $e['libelle'] = $libellesEvenements[$e['type'] ?? ''] ?? ($e['type'] ?? '');
    }
    unset($e);

    return [
        'id'           => (int) $r['id'],
        'date'         => $r['date_action'],
        'duree'        => $r['duree_ms'] !== null ? (int) $r['duree_ms'] : null,
        'type'         => $r['type_action'],
        'type_libelle' => $types[$r['type_action']]['libelle'] ?? $r['type_action'],
        'libelle'      => $r['libelle'],
        'utilisateur'  => $r['user_id'] === null ? null : [
            'id' => (int) $r['user_id'], 'pseudo' => $r['user_pseudo'], 'nom' => $r['user_nom'],
            'role' => journalRole($r['user_niveau'] !== null ? (int) $r['user_niveau'] : null),
        ],
        'methode'      => $r['methode'],
        'url'          => $r['url'],
        'referent'     => $r['referent'],
        'statut'       => $r['statut_http'] !== null ? (int) $r['statut_http'] : null,
        'redirection'  => $r['redirection'],
        'ajax'         => (bool) $r['ajax'],
        'ip'           => $r['ip'],
        'appareil'     => $r['appareil'],
        'user_agent'   => $r['user_agent'],
        'session'      => $r['session_empreinte'],
        'donnees'      => $json($r['donnees']),
        'fichiers'     => $json($r['fichiers']),
        'evenements'   => $evenements,
        'erreur'       => $r['erreur'],
        'modifications_ignorees' => (int) $r['modifications_ignorees'],
        'modifications' => $modifications,
    ];
}

function journalUtilisateurs(PDO $dtb): array
{
    try {
        return $dtb->query("SELECT id, pseudo, TRIM(CONCAT(IFNULL(nom,''), ' ', IFNULL(prenom,''))) AS nom, level
                              FROM compt_utilisateur ORDER BY nom, pseudo")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function journalTablesConnues(PDO $dtb): array
{
    try {
        $tables = $dtb->query('SELECT DISTINCT table_nom FROM t_audit_modification WHERE table_nom IS NOT NULL ORDER BY table_nom LIMIT 300')
                      ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
    $sortie = [];
    foreach ($tables as $t) {
        $sortie[$t] = Audit::libelleTable($t);
    }
    asort($sortie);
    return $sortie;
}
