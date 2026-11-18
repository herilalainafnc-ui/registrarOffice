<?php
/* =============================================================================
   Journal d'activité — toutes les actions des utilisateurs

   CE QUI EST CAPTÉ
   ----------------
   - Chaque écriture en base (INSERT, UPDATE, DELETE, REPLACE, TRUNCATE et
     changements de structure), avec la table, le nombre de lignes, les
     paramètres et les valeurs AVANT et APRÈS, champ par champ.
   - Chaque action HTTP qui écrit, envoie un formulaire, exporte un document
     ou ouvre une page : utilisateur, date à la milliseconde, URL, IP,
     appareil, statut, durée, données envoyées.
   - Les événements de sécurité : connexion (mot de passe, Google,
     « se souvenir de moi »), échec de connexion, déconnexion — tout ce qui
     passe par Middleware::logSecurityEvent().

   POURQUOI UNE CONNEXION PDO D'AUDIT
   ----------------------------------
   Plus de 150 fichiers écrivent en base, chacun à sa façon. Les modifier un à
   un en oublierait. data/backdb.php et data/connectdb.php, par lesquels passent
   presque tous les points d'entrée, créent la connexion via auditConnexion() :
   AuditPDO observe exec(), query() et chaque requête préparée. Rien à changer
   dans le reste du code.

   POURQUOI UNE ÉCRITURE DIFFÉRÉE, SUR UNE AUTRE CONNEXION
   -------------------------------------------------------
   Écrire le journal sur la connexion de l'application fausserait
   lastInsertId() — l'id de la ligne de journal remplacerait celui que le code
   attend — et lierait le journal aux transactions en cours. Tout est donc gardé
   en mémoire et écrit en fin de requête (register_shutdown_function) sur une
   connexion séparée. Une transaction annulée est marquée « annulee » au lieu
   de disparaître : on voit aussi ce qui a été tenté.

   CE QUI N'EST PAS JOURNALISÉ, VOLONTAIREMENT
   -------------------------------------------
   - Les appels AJAX qui ne modifient rien (recherche instantanée, listes) :
     chaque frappe au clavier ferait une ligne.
   - Les pages vues par un visiteur non connecté (site public).
   - Les écritures dans le journal lui-même et dans security_logs, dont chaque
     ligne est déjà un événement du journal.
   - Les tables de la messagerie (msg_*) : une correspondance privée n'a pas
     à être recopiée dans un journal que d'autres consultent. Ses appels sont
     en AJAX : sans écriture journalisée, ils ne laissent pas de ligne non plus.
     Les décisions de modération y sont notées comme événements.
   - Un UPDATE ou DELETE qui ne touche aucune ligne, et un CREATE … IF NOT
     EXISTS : rien n'a changé.
   - La ligne de commande, sauf AUDIT_CLI=1 (tests).

   SÉCURITÉ
   --------
   Mots de passe, jetons, secrets et CSRF sont masqués partout : données
   envoyées, paramètres SQL, valeurs avant/après, adresse. Une erreur du journal
   n'interrompt jamais l'application : elle part dans logs/audit-secours.log.
   ========================================================================== */

if (defined('AUDIT_CHARGE')) {
    return;
}
define('AUDIT_CHARGE', true);

final class Audit
{
    /** Tables jamais journalisées. */
    private const TABLES_IGNOREES = ['t_audit_requete', 't_audit_modification', 'security_logs'];

    /** Messagerie (data/messagerie.php) : correspondance privée, jamais copiée. */
    private const PREFIXE_PRIVE = 'msg_';

    /** Nom de champ sensible : sa valeur n'est jamais enregistrée. */
    private const SENSIBLE = '~pass|pwd|mdp|secret|token|csrf|otp|remember|api_?key|signature_cle~i';

    private const MASQUE = '••••';
    private const MAX_MODIFICATIONS = 300;
    private const MAX_LIGNES_IMAGE  = 50;
    private const MAX_TEXTE         = 2000;
    private const FUSEAU            = 'Indian/Antananarivo';

    private const EVENEMENTS_CONNEXION = [
        'login_success' => 'connexion', 'google_login_success' => 'connexion', 'login_remember' => 'connexion',
        'login_failed' => 'echec_connexion', 'google_login_rejected' => 'echec_connexion',
        'google_login_user_not_found' => 'echec_connexion', 'google_token_invalid_audience' => 'echec_connexion',
        'logout' => 'deconnexion',
    ];

    private static bool $demarre = false;
    private static bool $desactive = false;
    private static bool $enregistre = false;
    private static array $connexion = [];
    private static float $debut = 0.0;
    private static array $modifications = [];
    private static int $ignorees = 0;
    private static array $evenements = [];
    private static ?int $utilisateurId = null;
    private static ?string $session = null;
    private static array $clesPrimaires = [];

    /* ------------------------------------------------------------ cycle de vie */

    public static function demarrer(string $dsn, ?string $utilisateur, ?string $motDePasse): void
    {
        if (self::$demarre) {
            return;
        }
        self::$demarre   = true;
        self::$debut     = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        self::$connexion = [$dsn, $utilisateur, $motDePasse];

        if (PHP_SAPI === 'cli' && getenv('AUDIT_CLI') !== '1') {
            self::$desactive = true;
            return;
        }
        register_shutdown_function([self::class, 'enregistrer']);
    }

    public static function actif(): bool
    {
        return self::$demarre && !self::$desactive;
    }

    /** Remet le journal à zéro. Réservé aux tests. */
    public static function reinitialiser(): void
    {
        self::$enregistre    = false;
        self::$modifications = [];
        self::$evenements    = [];
        self::$ignorees      = 0;
        self::$utilisateurId = null;
        self::$session       = null;
        self::$debut         = microtime(true);
    }

    /** Événement de sécurité ou métier (connexion, déconnexion, etc.). */
    public static function evenement(string $type, array $details = []): void
    {
        if (!self::actif()) {
            return;
        }
        self::utilisateurCourant();
        self::$evenements[] = [
            'type'    => $type,
            'moment'  => self::maintenant(),
            'details' => self::masquerTableau($details),
        ];
    }

    /** Fin de requête : écrit la ligne d'action et ses modifications. */
    public static function enregistrer(): void
    {
        if (!self::actif() || self::$enregistre) {
            return;
        }
        self::$enregistre = true;
        $ligne = null;
        try {
            $ligne = self::construireAction();
            if ($ligne !== null) {
                self::ecrire($ligne);
            }
        } catch (\Throwable $e) {
            self::secours($ligne, $e);
        }
    }

    /* ---------------------------------------------------------- écritures SQL */

    /** INSERT, UPDATE… ou null pour une lecture. */
    public static function operation(string $sql): ?string
    {
        $s = self::sansPreambule($sql);
        if (!preg_match('~^(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|CREATE|DROP|RENAME)\b~i', $s, $m)) {
            return null;
        }
        return strtoupper($m[1]);
    }

    /** Image des lignes visées, lue AVANT un UPDATE ou un DELETE. */
    public static function imageAvant(AuditPDO $pdo, string $operation, string $sql, array $params): ?array
    {
        if ($operation !== 'UPDATE' && $operation !== 'DELETE') {
            return null;
        }
        try {
            $analyse = self::analyserEcriture($operation, $sql);
            if ($analyse === null || self::tableIgnoree($analyse['table'])) {
                return null;
            }
            $args   = self::parametresWhere($analyse, $params);
            $lignes = self::lireInterne($pdo,
                'SELECT * FROM `' . $analyse['table'] . '` WHERE ' . $analyse['where'] . ' LIMIT ' . (self::MAX_LIGNES_IMAGE + 1),
                $args);
            return $analyse + ['args' => $args, 'lignes' => $lignes];
        } catch (\Throwable $e) {
            return ['non_capture' => $e->getMessage()];
        }
    }

    public static function noterEcriture(
        AuditPDO $pdo, string $operation, string $sql, array $params,
        ?array $avant, ?int $lignes, ?string $idAvant, ?\Throwable $erreur
    ): void {
        if (!self::actif()) {
            return;
        }
        $table = self::tableDe($operation, $sql);
        if ($table !== null && self::tableIgnoree($table)) {
            return;
        }
        if ($erreur === null) {
            if (($operation === 'UPDATE' || $operation === 'DELETE') && $lignes === 0) {
                return;
            }
            if (preg_match('~\bIF\s+NOT\s+EXISTS\b~i', $sql)) {
                return;
            }
        }
        self::utilisateurCourant();
        if (count(self::$modifications) >= self::MAX_MODIFICATIONS) {
            self::$ignorees++;
            return;
        }

        $changements = null;
        if ($erreur === null) {
            try {
                $changements = self::changements($pdo, $operation, $table, $params, $avant, (int) $lignes, $idAvant);
            } catch (\Throwable $e) {
                $changements = ['non_capture' => $e->getMessage()];
            }
        }

        self::$modifications[] = [
            'moment'      => self::maintenant(),
            'operation'   => $operation,
            'table'       => $table,
            'lignes'      => $lignes,
            'sql'         => self::tronquer(preg_replace('~\s+~', ' ', trim($sql)), 8000),
            'parametres'  => self::masquerParametres($params),
            'changements' => $changements,
            'transaction' => $pdo->inTransaction() ? 'en_cours' : 'hors',
            'erreur'      => $erreur ? self::tronquer($erreur->getMessage(), 500) : null,
        ];
    }

    /** Fin de transaction : les écritures en cours deviennent validées ou annulées. */
    public static function finTransaction(string $statut): void
    {
        foreach (self::$modifications as &$m) {
            if ($m['transaction'] === 'en_cours') {
                $m['transaction'] = $statut;
            }
        }
        unset($m);
    }

    /* -------------------------------------------------------------- analyse */

    private static function sansPreambule(string $sql): string
    {
        return (string) preg_replace('~^(?:\s+|/\*.*?\*/|--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\()+~s', '', $sql);
    }

    private static function tableIgnoree(?string $table): bool
    {
        if ($table === null) {
            return false;
        }
        $table = strtolower($table);
        return in_array($table, self::TABLES_IGNOREES, true)
            || str_starts_with($table, self::PREFIXE_PRIVE);
    }

    public static function tableDe(string $operation, string $sql): ?string
    {
        $s = self::sansPreambule($sql);
        $id = '`?(?:\w+`?\.`?)?(\w+)`?';
        $motifs = [
            'INSERT'   => '~^INSERT\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*(?:INTO\s+)?' . $id . '~i',
            'REPLACE'  => '~^REPLACE\s+(?:(?:LOW_PRIORITY|DELAYED)\s+)*(?:INTO\s+)?' . $id . '~i',
            'UPDATE'   => '~^UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE)\s+)*' . $id . '~i',
            'DELETE'   => '~^DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*(?:\w+\s+)?FROM\s+' . $id . '~i',
            'TRUNCATE' => '~^TRUNCATE\s+(?:TABLE\s+)?' . $id . '~i',
            'ALTER'    => '~^ALTER\s+(?:IGNORE\s+)?TABLE\s+' . $id . '~i',
            'CREATE'   => '~^CREATE\s+(?:TEMPORARY\s+)?(?:TABLE|VIEW|(?:UNIQUE\s+)?INDEX\s+\w+\s+ON)\s+(?:IF\s+NOT\s+EXISTS\s+)?' . $id . '~i',
            'DROP'     => '~^DROP\s+(?:TEMPORARY\s+)?(?:TABLE|VIEW)\s+(?:IF\s+EXISTS\s+)?' . $id . '~i',
            'RENAME'   => '~^RENAME\s+TABLE\s+' . $id . '~i',
        ];
        if (isset($motifs[$operation]) && preg_match($motifs[$operation], $s, $m)) {
            return $m[1];
        }
        return null;
    }

    /** UPDATE ou DELETE mono-table avec WHERE ; null sinon (image non capturée). */
    private static function analyserEcriture(string $operation, string $sql): ?array
    {
        $s = rtrim(trim(self::sansPreambule($sql)), "; \t\n\r");
        if (preg_match('~\bJOIN\b~i', $s)) {
            return null;
        }
        $fin = '(?:\s+ORDER\s+BY\s+[\w`., ]+?)?(?:\s+LIMIT\s+\d+)?$~is';
        if ($operation === 'UPDATE'
            && preg_match('~^UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE)\s+)*`?(\w+)`?\s+SET\s+(.+?)\s+WHERE\s+(.+?)' . $fin, $s, $m)) {
            return ['table' => $m[1], 'set' => $m[2], 'where' => $m[3]];
        }
        if ($operation === 'DELETE'
            && preg_match('~^DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*FROM\s+`?(\w+)`?\s+WHERE\s+(.+?)' . $fin, $s, $m)) {
            return ['table' => $m[1], 'set' => '', 'where' => $m[2]];
        }
        return null;
    }

    /** Les seuls paramètres du WHERE, pour relire les lignes visées. */
    private static function parametresWhere(array $analyse, array $params): array
    {
        $nommes = false;
        foreach (array_keys($params) as $cle) {
            if (is_string($cle)) {
                $nommes = true;
                break;
            }
        }
        if (!$nommes) {
            return array_slice(array_values($params), substr_count($analyse['set'], '?'));
        }
        preg_match_all('~(?<!:):(\w+)~', $analyse['where'], $m);
        $args = [];
        foreach (array_unique($m[1]) as $nom) {
            if (array_key_exists($nom, $params)) {
                $args[$nom] = $params[$nom];
            } elseif (array_key_exists(':' . $nom, $params)) {
                $args[$nom] = $params[':' . $nom];
            }
        }
        return $args;
    }

    private static function lireInterne(AuditPDO $pdo, string $sql, array $args): array
    {
        $pdo->interne = true;
        try {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $pdo->interne = false;
        }
    }

    private static function clePrimaire(AuditPDO $pdo, string $table): array
    {
        if (!preg_match('~^\w+$~', $table)) {
            return [];
        }
        if (!isset(self::$clesPrimaires[$table])) {
            try {
                $lignes = self::lireInterne($pdo, "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'", []);
                usort($lignes, fn($a, $b) => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']);
                self::$clesPrimaires[$table] = array_column($lignes, 'Column_name');
            } catch (\Throwable $e) {
                self::$clesPrimaires[$table] = [];
            }
        }
        return self::$clesPrimaires[$table];
    }

    /** Valeurs avant / après, ligne par ligne. */
    private static function changements(
        AuditPDO $pdo, string $operation, ?string $table, array $params, ?array $avant, int $lignes, ?string $idAvant
    ): ?array {
        if ($table === null || !preg_match('~^\w+$~', $table)) {
            return null;
        }
        $cle = self::clePrimaire($pdo, $table);

        if ($operation === 'INSERT' || $operation === 'REPLACE') {
            $idApres = (string) $pdo->lastInsertId();
            if (count($cle) === 1 && $lignes > 0 && $idApres !== '' && $idApres !== '0' && $idApres !== $idAvant && ctype_digit($idApres)) {
                $n = min($lignes, self::MAX_LIGNES_IMAGE);
                $rangees = self::lireInterne($pdo,
                    "SELECT * FROM `$table` WHERE `{$cle[0]}` >= ? ORDER BY `{$cle[0]}` LIMIT $n", [(int) $idApres]);
                return ['cle' => $cle, 'lignes' => array_map(fn($r) => [
                    'cle' => self::valeursCle($r, $cle), 'apres' => self::masquerLigne($r),
                ], $rangees), 'tronque' => $lignes > self::MAX_LIGNES_IMAGE];
            }
            return ['cle' => $cle, 'valeurs' => self::masquerParametres($params)];
        }

        if ($avant === null) {
            return null;
        }
        if (isset($avant['non_capture'])) {
            return ['non_capture' => $avant['non_capture']];
        }
        $avantLignes = array_slice($avant['lignes'], 0, self::MAX_LIGNES_IMAGE);
        $tronque = count($avant['lignes']) > self::MAX_LIGNES_IMAGE;

        if ($operation === 'DELETE') {
            return ['cle' => $cle, 'lignes' => array_map(fn($r) => [
                'cle' => self::valeursCle($r, $cle), 'avant' => self::masquerLigne($r),
            ], $avantLignes), 'tronque' => $tronque];
        }

        /* UPDATE : relire les mêmes lignes par leur clé primaire. Relire par le
           WHERE manquerait les lignes que la modification a fait sortir du filtre. */
        $apresParCle = [];
        if ($cle && $avantLignes) {
            $conditions = [];
            $args = [];
            foreach ($avantLignes as $r) {
                $conditions[] = '(' . implode(' AND ', array_map(fn($c) => "`$c` <=> ?", $cle)) . ')';
                foreach ($cle as $c) {
                    $args[] = $r[$c];
                }
            }
            foreach (self::lireInterne($pdo, "SELECT * FROM `$table` WHERE " . implode(' OR ', $conditions), $args) as $r) {
                $apresParCle[json_encode(self::valeursCle($r, $cle))] = $r;
            }
        } elseif ($avantLignes) {
            $relues = self::lireInterne($pdo,
                'SELECT * FROM `' . $table . '` WHERE ' . $avant['where'] . ' LIMIT ' . self::MAX_LIGNES_IMAGE, $avant['args']);
            foreach ($relues as $i => $r) {
                $apresParCle['#' . $i] = $r;
            }
        }

        $resultat = [];
        foreach ($avantLignes as $i => $r) {
            $idCle = $cle ? json_encode(self::valeursCle($r, $cle)) : '#' . $i;
            $apres = $apresParCle[$idCle] ?? null;
            if ($apres === null) {
                continue;
            }
            $a = [];
            $b = [];
            foreach ($r as $colonne => $valeur) {
                $nouvelle = $apres[$colonne] ?? null;
                if ((string) $valeur !== (string) $nouvelle || ($valeur === null) !== ($nouvelle === null)) {
                    $sensible = preg_match(self::SENSIBLE, (string) $colonne);
                    $a[$colonne] = $sensible ? self::MASQUE : self::tronquerValeur($valeur);
                    $b[$colonne] = $sensible ? self::MASQUE : self::tronquerValeur($nouvelle);
                }
            }
            if ($a) {
                $resultat[] = ['cle' => $cle ? self::valeursCle($r, $cle) : null, 'avant' => $a, 'apres' => $b];
            }
        }
        return ['cle' => $cle, 'lignes' => $resultat, 'tronque' => $tronque];
    }

    private static function valeursCle(array $ligne, array $cle): array
    {
        $v = [];
        foreach ($cle as $c) {
            $v[$c] = $ligne[$c] ?? null;
        }
        return $v;
    }

    /* ------------------------------------------------------------- masquage */

    private static function masquerLigne(array $ligne): array
    {
        foreach ($ligne as $c => $v) {
            $ligne[$c] = preg_match(self::SENSIBLE, (string) $c) ? self::MASQUE : self::tronquerValeur($v);
        }
        return $ligne;
    }

    public static function masquerParametres(array $params): array
    {
        $sortie = [];
        $n = 0;
        foreach ($params as $cle => $valeur) {
            if (++$n > 100) {
                $sortie['…'] = (count($params) - 100) . ' paramètre(s) de plus';
                break;
            }
            $sensible = is_string($cle) && preg_match(self::SENSIBLE, $cle);
            if (is_string($valeur) && (preg_match('~^\$2[aby]\$\d\d\$~', $valeur) || preg_match('~^[a-f0-9]{40,}$~i', $valeur))) {
                $sensible = true;
            }
            $sortie[$cle] = $sensible ? self::MASQUE : self::tronquerValeur($valeur);
        }
        return $sortie;
    }

    public static function masquerTableau($donnees, int $profondeur = 0)
    {
        if (!is_array($donnees)) {
            return self::tronquerValeur($donnees);
        }
        if ($profondeur > 6) {
            return '…';
        }
        $sortie = [];
        foreach ($donnees as $cle => $valeur) {
            $sortie[$cle] = (is_string($cle) && preg_match(self::SENSIBLE, $cle))
                ? self::MASQUE
                : self::masquerTableau($valeur, $profondeur + 1);
        }
        return $sortie;
    }

    private static function tronquerValeur($valeur)
    {
        if (is_string($valeur)) {
            return self::tronquer($valeur, self::MAX_TEXTE);
        }
        if (is_scalar($valeur) || $valeur === null) {
            return $valeur;
        }
        return '(' . gettype($valeur) . ')';
    }

    private static function tronquer(string $texte, int $max): string
    {
        return mb_strlen($texte) > $max ? mb_substr($texte, 0, $max) . '… (tronqué)' : $texte;
    }

    /* -------------------------------------------------------- action HTTP */

    private static function utilisateurCourant(): ?int
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $id = (int) ($_SESSION['user_id'] ?? 0);
            if ($id > 0) {
                self::$utilisateurId = $id;
            }
            if (session_id() !== '') {
                self::$session = substr(hash('sha256', session_id()), 0, 16);
            }
        }
        return self::$utilisateurId;
    }

    private static function maintenant(): string
    {
        $d = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $d->setTimezone(new \DateTimeZone(self::FUSEAU));
        return $d->format('Y-m-d H:i:s.v');
    }

    private static function estAjax(): bool
    {
        if (function_exists('estRequeteAjax')) {
            return estRequeteAjax();
        }
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return true;
        }
        $mode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
        return $mode !== '' && $mode !== 'navigate';
    }

    private static function typeAction(string $methode, string $chemin, bool $ajax, ?int $utilisateur): ?string
    {
        $priorite = ['echec_connexion' => 3, 'connexion' => 2, 'deconnexion' => 1];
        $type = null;
        foreach (self::$evenements as $e) {
            $t = self::EVENEMENTS_CONNEXION[$e['type']] ?? null;
            if ($t !== null && ($type === null || $priorite[$t] > $priorite[$type])) {
                $type = $t;
            }
        }
        if ($type !== null) {
            return $type;
        }
        if (self::$modifications) {
            return 'modification';
        }
        if (self::$evenements) {
            return 'evenement';
        }
        $export = (bool) preg_match('~(data\.topdf|topdf_paysage|gen\.pdf|gen\.excel|data\.toxlsx|/export)~i', $chemin);
        if ($export) {
            return $utilisateur !== null ? 'export' : null;
        }
        if ($ajax) {
            return null;
        }
        if ($methode !== 'GET' && $methode !== 'HEAD') {
            return 'action';
        }
        if ($utilisateur === null || preg_match('~^/(loading|goodbye|favicon)~', $chemin)) {
            return null;
        }
        return 'consultation';
    }

    private static function construireAction(): ?array
    {
        $utilisateur = self::utilisateurCourant();
        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
        $uri     = (string) ($_SERVER['REQUEST_URI'] ?? ('cli:' . ($_SERVER['argv'][0] ?? '')));
        $chemin  = (string) (parse_url($uri, PHP_URL_PATH) ?: $uri);
        $ajax    = self::estAjax();

        $type = self::typeAction($methode, $chemin, $ajax, $utilisateur);
        if ($type === null) {
            return null;
        }

        $redirection = null;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') === 0) {
                $redirection = trim(substr($h, 9));
            }
        }
        $erreur = null;
        $derniere = error_get_last();
        if ($derniere && in_array($derniere['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $erreur = $derniere['message'] . ' (' . basename($derniere['file']) . ':' . $derniere['line'] . ')';
        }

        $donnees = self::masquerTableau($_POST);
        $brut = '';
        if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
            $brut = (string) @file_get_contents('php://input', false, null, 0, 65536);
            $json = json_decode($brut, true);
            if (is_array($json)) {
                $donnees = ['json' => self::masquerTableau($json)];
            }
        }
        $fichiers = [];
        foreach ($_FILES as $champ => $f) {
            foreach ((array) ($f['name'] ?? []) as $i => $nom) {
                $fichiers[] = [
                    'champ' => $champ,
                    'nom'   => self::tronquer((string) $nom, 255),
                    'taille'=> is_array($f['size'] ?? null) ? ($f['size'][$i] ?? null) : ($f['size'] ?? null),
                    'type'  => is_array($f['type'] ?? null) ? ($f['type'][$i] ?? null) : ($f['type'] ?? null),
                ];
            }
        }

        $tables = array_values(array_unique(array_filter(array_column(self::$modifications, 'table'))));

        return [
            'date_action'   => self::depuisDebut(),
            'duree_ms'      => (int) round((microtime(true) - self::$debut) * 1000),
            'type_action'   => $type,
            'libelle'       => self::tronquer(self::libelle($type, $chemin, $tables), 250),
            'user_id'       => $utilisateur,
            'methode'       => substr($methode, 0, 10),
            'url'           => self::tronquer(self::masquerUrl($uri), 1990),
            'chemin'        => self::tronquer($chemin, 490),
            'referent'      => isset($_SERVER['HTTP_REFERER']) ? self::tronquer(self::masquerUrl((string) $_SERVER['HTTP_REFERER']), 990) : null,
            'statut_http'   => http_response_code() ?: null,
            'redirection'   => $redirection !== null ? self::tronquer(self::masquerUrl($redirection), 990) : null,
            'ajax'          => $ajax ? 1 : 0,
            'ip'            => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'appareil'      => self::appareil((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'user_agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? self::tronquer((string) $_SERVER['HTTP_USER_AGENT'], 490) : null,
            'session_empreinte' => self::$session,
            'donnees'       => $donnees ? self::json($donnees, 60000) : null,
            'fichiers'      => $fichiers ? self::json($fichiers, 60000) : null,
            'evenements'    => self::$evenements ? self::json(self::$evenements, 60000) : null,
            'nb_modifications' => count(self::$modifications),
            'modifications_ignorees' => self::$ignorees,
            'tables_touchees' => $tables ? self::tronquer(',' . implode(',', $tables) . ',', 990) : null,
            'erreur'        => $erreur,
        ];
    }

    private static function depuisDebut(): string
    {
        $d = \DateTime::createFromFormat('U.u', sprintf('%.6F', self::$debut));
        $d->setTimezone(new \DateTimeZone(self::FUSEAU));
        return $d->format('Y-m-d H:i:s.v');
    }

    private static function masquerUrl(string $url): string
    {
        return (string) preg_replace_callback('~([?&])([^=&#]+)=([^&#]*)~', function ($m) {
            return $m[1] . $m[2] . '=' . (preg_match(self::SENSIBLE, urldecode($m[2])) ? self::MASQUE : $m[3]);
        }, $url);
    }

    private static function json($valeur, int $max): string
    {
        $j = json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($j === false) {
            return '{"erreur":"non encodable"}';
        }
        return strlen($j) > $max ? json_encode(['tronque' => true, 'debut' => mb_strcut($j, 0, $max)], JSON_UNESCAPED_UNICODE) : $j;
    }

    public static function appareil(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }
        $systeme = match (true) {
            (bool) preg_match('~iPhone~', $ua)   => 'iPhone',
            (bool) preg_match('~iPad~', $ua)     => 'iPad',
            (bool) preg_match('~Android~', $ua)  => 'Android',
            (bool) preg_match('~Windows~', $ua)  => 'Windows',
            (bool) preg_match('~Mac OS X|Macintosh~', $ua) => 'macOS',
            (bool) preg_match('~Linux~', $ua)    => 'Linux',
            default => null,
        };
        $navigateur = match (true) {
            (bool) preg_match('~Edg/~', $ua)      => 'Edge',
            (bool) preg_match('~OPR/|Opera~', $ua) => 'Opera',
            (bool) preg_match('~Firefox/~', $ua)  => 'Firefox',
            (bool) preg_match('~Chrome/|CriOS/~', $ua) => 'Chrome',
            (bool) preg_match('~Safari/~', $ua)   => 'Safari',
            (bool) preg_match('~curl|bot|spider|crawl~i', $ua) => 'Robot',
            default => null,
        };
        $texte = implode(' · ', array_filter([$navigateur, $systeme]));
        return $texte !== '' ? $texte : self::tronquer($ua, 110);
    }

    /** Libellé lisible d'une action : « Connexion », « Modification · Notes », « Fiche étudiant ». */
    public static function libelle(string $type, string $chemin, array $tables): string
    {
        switch ($type) {
            case 'connexion':       return 'Connexion';
            case 'echec_connexion': return 'Échec de connexion';
            case 'deconnexion':     return 'Déconnexion';
        }
        $page = self::libellePage($chemin);
        if ($type === 'modification' && $tables) {
            $noms = array_map([self::class, 'libelleTable'], array_slice($tables, 0, 3));
            return $page . ' · ' . implode(', ', $noms) . (count($tables) > 3 ? '…' : '');
        }
        return $page;
    }

    public static function libellePage(string $chemin): string
    {
        $pages = [
            '/tableau-de-bord' => 'Tableau de bord', '/etudiants' => 'Liste des étudiants', '/dashboard' => 'Liste des étudiants', '/student' => 'Fiche étudiant', '/students/create' => 'Création d\'étudiant',
            '/cours' => 'Cours', '/course' => 'Cours', '/courses' => 'Liste des cours', '/professor' => 'Fiche enseignant',
            '/professors' => 'Enseignants', '/finance' => 'Barème financier', '/settings' => 'Réglages',
            '/settings/logs' => 'Journal d\'activité', '/settings/espace-etudiant' => 'Espace étudiant', '/settings/mentions' => 'Mentions', '/settings/vitrine' => 'Page vitrine',
            '/settings/diplome' => 'Diplôme', '/settings/bulk-email' => 'Boîte mail', '/settings/adresses-google' => 'Adresses Google', '/sessions' => 'Sessions',
            '/courses-sessions' => 'Cours par session', '/accounts/create' => 'Utilisateurs', '/my-account' => 'Mon compte',
            '/login' => 'Page de connexion', '/queue' => 'File d\'attente', '/queue/plan' => 'Plan du bureau', '/retouche-photo' => 'Retouche photo', '/studio' => 'Studio UAZ', '/schedule' => 'Emploi du temps',
            '/classrooms' => 'Salles', '/news' => 'Actualités', '/session-rankings' => 'Classement par session',
            '/deans-list' => 'Liste du doyen', '/top-students' => 'Meilleurs étudiants', '/login-locations' => 'Localisations GPS',
            '/finance/authorized-reinscription' => 'Autorisation de réinscription', '/finance/echeances' => 'Échéances de paiement', '/work-education' => 'Work Education', '/inscriptions/tardives' => 'Inscriptions tardives', '/finance/account-balance' => 'Solde de compte',
            '/inscription/inscription' => 'Inscription', '/pre-inscription' => 'Pré-inscription', '/admissions' => 'Admissions',
            // Points d'enregistrement : le nom du fichier ne parle pas à un lecteur du journal.
            '/app/mentions.save' => 'Réglage des mentions', '/app/espace-etudiant.save' => 'Publication des notes aux étudiants', '/app/reinscription.save' => 'Réinitialisation des réinscriptions',
            '/app/finance-bareme.save' => 'Barème financier', '/app/.student/updatenote' => 'Saisie d\'une note',
            '/app/.cours/bulksavenotes' => 'Saisie des notes en lot', '/app/.cours/completnotes' => 'Saisie d\'une note',
            '/app/.student/updatestd' => 'Modification de fiche étudiant', '/app/.student/addstd' => 'Création d\'étudiant',
            '/app/.student/checkcours' => 'Ajout de cours à un étudiant', '/app/.student/del-cours.momentanee' => 'Retrait d\'un cours',
            '/app/.student/del-cours.definitive' => 'Suppression définitive d\'un cours', '/app/.student/recup-cours' => 'Restauration d\'un cours',
            '/app/.student/updtateimgstd' => 'Photo d\'étudiant', '/app/retouche-photo.profil' => 'Photo de profil (retouche photo)', '/app/studio.export' => 'Export d\'un visuel (Studio UAZ)','/app/.student/suspendre' => 'Suspension d\'étudiant',
            '/app/.student/lever-suspension' => 'Levée de suspension', '/app/.student/retrait-universite' => 'Retrait de l\'université',
            '/app/.user/updateuser' => 'Modification de compte utilisateur', '/app/.user/add.user' => 'Création de compte utilisateur',
            '/api/update-authorization' => 'Autorisation de réinscription', '/api/update-dossier' => 'Dossier registraire',
            '/app/.annonces/add.annonce' => 'Création d\'annonce', '/app/.annonces/update.annonce' => 'Modification d\'annonce',
            '/app/.annonces/delete.annonce' => 'Suppression d\'annonce', '/logout' => 'Déconnexion',
        ];
        $c = preg_replace('~\.php$~', '', rtrim(strtolower($chemin), '/')) ?: '/';
        if (isset($pages[$c])) {
            return $pages[$c];
        }
        if (preg_match('~(data\.topdf|gen\.pdf|topdf_paysage)~', $c)) {
            $type = (string) ($_GET['ptype'] ?? '');
            return 'Export PDF' . ($type !== '' ? ' · ' . self::tronquer($type, 60) : '')
                . (($_GET['export_format'] ?? '') === 'excel' ? ' (Excel)' : '');
        }
        $base = preg_replace('~\.php$~', '', basename($c));
        return $base !== '' ? ucfirst(str_replace(['.', '-', '_'], ' ', $base)) : $chemin;
    }

    public static function libelleTable(string $table): string
    {
        $tables = [
            't_2023_notes' => 'Notes', 't_2023_promotion_notes' => 'Notes de promotion', 'tbl_2024_etudiant' => 'Étudiants',
            'compt_utilisateur' => 'Comptes utilisateurs', 't_2024_inscription_session' => 'Inscriptions',
            't_2024_etudiant_finace' => 'Finances étudiant', 't_2024_finance_detail_licence' => 'Barème financier',
            't_2023_cours' => 'Cours', 't_2023_session' => 'Sessions', 't_2023_course_sessions' => 'Cours par session',
            't_notes_modification_history' => 'Historique des notes', 't_student_modification_history' => 'Historique étudiant',
            'filiere' => 'Mentions', 'filiere_parcours' => 'Parcours', 'candidat' => 'Candidatures', 'vitrine_config' => 'Page vitrine',
            'login_locations' => 'Localisations de connexion', 'remember_tokens' => 'Jetons « se souvenir de moi »',
            't_document_verification' => 'Documents émis', 't_annonces' => 'Annonces', 't_queue_tickets' => 'File d\'attente',
            't_2024_bacc' => 'Baccalauréat', 't_2024_diplome_preced' => 'Diplômes précédents', 'diplome_arretes' => 'Arrêtés de diplôme',
            't_reinscription_reinitialisation' => 'Réinitialisations de réinscription', 't_2024_emploi_du_temps' => 'Emploi du temps',
            't_2024_salles' => 'Salles', 'settings' => 'Paramètres', 'teacher' => 'Enseignants',
        ];
        return $tables[strtolower($table)] ?? $table;
    }

    /* ------------------------------------------------------------- écriture */

    private static function ecrire(array $ligne): void
    {
        [$dsn, $u, $p] = self::$connexion;
        $cnx = new PDO($dsn, $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $cnx->exec("SET SESSION sql_mode = ''");
        if (function_exists('fuseauConnexion')) { fuseauConnexion($cnx); }

        if ($ligne['user_id'] !== null) {
            $st = $cnx->prepare('SELECT pseudo, nom, prenom, level FROM compt_utilisateur WHERE id = ?');
            $st->execute([$ligne['user_id']]);
            if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
                $ligne['user_pseudo'] = self::tronquer((string) $u['pseudo'], 95);
                $ligne['user_nom']    = self::tronquer(trim($u['nom'] . ' ' . $u['prenom']), 195);
                $ligne['user_niveau'] = (int) $u['level'];
            }
        }

        try {
            self::inserer($cnx, $ligne);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
            self::creerTables($cnx);
            self::inserer($cnx, $ligne);
        }
    }

    private static function inserer(PDO $cnx, array $ligne): void
    {
        $colonnes = array_keys($ligne);
        $cnx->beginTransaction();
        try {
            $st = $cnx->prepare('INSERT INTO t_audit_requete (`' . implode('`,`', $colonnes) . '`) VALUES (:' . implode(',:', $colonnes) . ')');
            $st->execute($ligne);
            $id = (int) $cnx->lastInsertId();

            $st = $cnx->prepare('INSERT INTO t_audit_modification
                (requete_id, ordre, date_action, operation, table_nom, lignes, requete_sql, parametres, changements, transaction_statut, erreur)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            foreach (self::$modifications as $i => $m) {
                $st->execute([
                    $id, $i + 1, $m['moment'], $m['operation'], $m['table'], $m['lignes'], $m['sql'],
                    $m['parametres'] ? self::json($m['parametres'], 60000) : null,
                    $m['changements'] !== null ? self::json($m['changements'], 900000) : null,
                    $m['transaction'] === 'en_cours' ? 'inachevee' : $m['transaction'],
                    $m['erreur'],
                ]);
            }
            $cnx->commit();
        } catch (\Throwable $e) {
            if ($cnx->inTransaction()) {
                $cnx->rollBack();
            }
            throw $e;
        }
    }

    private static function creerTables(PDO $cnx): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/migration_audit.sql');
        $sql = (string) preg_replace('~^--[^\n]*\n~m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $instruction) {
            $cnx->exec($instruction);
        }
    }

    private static function secours(?array $ligne, \Throwable $e): void
    {
        try {
            $dossier = dirname(__DIR__) . '/logs';
            if (!is_dir($dossier)) {
                @mkdir($dossier, 0775, true);
            }
            @file_put_contents($dossier . '/audit-secours.log', json_encode([
                'moment' => date('c'), 'erreur' => $e->getMessage(), 'action' => $ligne,
                'modifications' => self::$modifications,
            ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $ignore) {
        }
    }
}

/* =============================================================== connexion */

class AuditPDO extends PDO
{
    /** Vrai pendant les lectures du journal lui-même : elles ne sont pas observées. */
    public bool $interne = false;

    /** Id inséré par la dernière écriture de l'APPLICATION (voir lastInsertId). */
    private ?string $dernierId = null;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AuditStatement::class, [$this]]);
    }

    /* MySQL remet l'id inséré à 0 après CHAQUE requête, lectures comprises.
       Les relectures du journal (lignes après modification, clé primaire)
       feraient donc renvoyer 0 au code qui vient d'insérer : on restitue l'id
       mémorisé juste après son écriture. */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->dernierId ?? parent::lastInsertId($name);
    }

    public function memoriserId(): void
    {
        $this->dernierId = (string) parent::lastInsertId();
    }

    public function exec(string $statement): int|false
    {
        $operation = $this->interne ? null : Audit::operation($statement);
        if ($operation === null || !Audit::actif()) {
            return parent::exec($statement);
        }
        $avant = Audit::imageAvant($this, $operation, $statement, []);
        $idAvant = (string) $this->lastInsertId();
        try {
            $resultat = parent::exec($statement);
        } catch (\Throwable $e) {
            Audit::noterEcriture($this, $operation, $statement, [], $avant, null, $idAvant, $e);
            throw $e;
        }
        $this->memoriserId();
        Audit::noterEcriture($this, $operation, $statement, [], $avant, $resultat === false ? null : $resultat, $idAvant, null);
        return $resultat;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $operation = $this->interne ? null : Audit::operation($query);
        $appel = fn() => $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
        if ($operation === null || !Audit::actif()) {
            return $appel();
        }
        $avant = Audit::imageAvant($this, $operation, $query, []);
        $idAvant = (string) $this->lastInsertId();
        try {
            $resultat = $appel();
        } catch (\Throwable $e) {
            Audit::noterEcriture($this, $operation, $query, [], $avant, null, $idAvant, $e);
            throw $e;
        }
        $this->memoriserId();
        Audit::noterEcriture($this, $operation, $query, [], $avant, $resultat ? $resultat->rowCount() : null, $idAvant, null);
        return $resultat;
    }

    public function commit(): bool
    {
        $r = parent::commit();
        Audit::finTransaction('validee');
        return $r;
    }

    public function rollBack(): bool
    {
        $r = parent::rollBack();
        Audit::finTransaction('annulee');
        return $r;
    }
}

class AuditStatement extends PDOStatement
{
    private AuditPDO $connexionAudit;
    private array $liaisons = [];

    protected function __construct(AuditPDO $connexion)
    {
        $this->connexionAudit = $connexion;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->liaisons[$param] = $value;
        return parent::bindValue($param, $value, $type);
    }

    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        $this->liaisons[$param] = &$var;
        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function execute(?array $params = null): bool
    {
        $pdo = $this->connexionAudit;
        $operation = $pdo->interne ? null : Audit::operation($this->queryString);
        if ($operation === null || !Audit::actif()) {
            return parent::execute($params);
        }
        $valeurs = $params ?? $this->liaisonsNormalisees();
        $avant = Audit::imageAvant($pdo, $operation, $this->queryString, $valeurs);
        $idAvant = (string) $pdo->lastInsertId();
        try {
            $ok = parent::execute($params);
        } catch (\Throwable $e) {
            Audit::noterEcriture($pdo, $operation, $this->queryString, $valeurs, $avant, null, $idAvant, $e);
            throw $e;
        }
        $pdo->memoriserId();
        Audit::noterEcriture($pdo, $operation, $this->queryString, $valeurs, $avant, $ok ? $this->rowCount() : null, $idAvant, null);
        return $ok;
    }

    /** bindValue(1, …) → liste ; bindValue(':nom', …) → ['nom' => …]. */
    private function liaisonsNormalisees(): array
    {
        $nommes = [];
        $positions = [];
        foreach ($this->liaisons as $cle => $valeur) {
            if (is_int($cle)) {
                $positions[$cle] = $valeur;
            } else {
                $nommes[ltrim($cle, ':')] = $valeur;
            }
        }
        ksort($positions);
        return $nommes ?: array_values($positions);
    }
}

/** Connexion de l'application, observée par le journal d'activité. */
function auditConnexion(string $dsn, ?string $utilisateur, ?string $motDePasse, array $options = []): PDO
{
    Audit::demarrer($dsn, $utilisateur, $motDePasse);
    return new AuditPDO($dsn, $utilisateur, $motDePasse, $options);
}
