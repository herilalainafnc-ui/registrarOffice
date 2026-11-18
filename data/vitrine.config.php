<?php
/**
 * Lecture des reglages de la vitrine.
 *
 * Une seule requete par page, quel que soit le nombre d'appels : les fragments
 * en font des dizaines, un aller-retour par cle serait insoutenable.
 *
 * TOUT APPEL DOIT FOURNIR UN DEFAUT. La vitrine est publique : si la base est
 * injoignable ou la table absente, la page doit s'afficher avec son contenu
 * d'origine plutot que de se vider ou de tomber en erreur. Le reglage
 * ameliore le texte, il ne le conditionne pas.
 */

if (!function_exists('vitrineReglages')) {

    function vitrineReglages(): array
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $cache = [];

        try {
            if (!defined('DB_DSN')) { require_once __DIR__ . '/config.php'; }
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach ($pdo->query('SELECT cle, valeur FROM vitrine_config') as $r) {
                $cache[$r['cle']] = $r['valeur'];
            }
        } catch (Throwable $e) {
            // Silence volontaire : un incident de configuration ne doit pas
            // faire tomber la page publique. La trace suffit au diagnostic.
            error_log('Vitrine: lecture des reglages impossible: ' . $e->getMessage());
        }
        return $cache;
    }

    /** Valeur brute d'un reglage, ou le defaut s'il est vide ou absent. */
    function vitrineVal(string $cle, string $defaut = ''): string
    {
        $t = vitrineReglages();
        $v = isset($t[$cle]) ? trim((string) $t[$cle]) : '';
        return $v !== '' ? $v : $defaut;
    }

    /** Valeur echappee, prete a etre inseree dans du HTML. */
    function vitrineTxt(string $cle, string $defaut = ''): string
    {
        return htmlspecialchars(vitrineVal($cle, $defaut), ENT_QUOTES, 'UTF-8');
    }

    /** Reglage de type « liste » : une entree par ligne, vides ecartees. */
    function vitrineListe(string $cle, array $defaut = []): array
    {
        $v = vitrineVal($cle, '');
        if ($v === '') { return $defaut; }
        $lignes = array_filter(array_map('trim', preg_split('/\R/', $v)), 'strlen');
        return $lignes ? array_values($lignes) : $defaut;
    }

    /**
     * Date formatee en francais, ou le defaut si le reglage est vide ou
     * illisible. On ne veut jamais afficher « 1970 » a un candidat.
     */
    function vitrineDate(string $cle, string $defaut = ''): string
    {
        $v = vitrineVal($cle, '');
        if ($v === '') { return $defaut; }
        $t = strtotime($v);
        if ($t === false) { return $defaut; }

        $mois = ['', 'janvier','février','mars','avril','mai','juin',
                 'juillet','août','septembre','octobre','novembre','décembre'];
        return (int) date('j', $t) . ' ' . $mois[(int) date('n', $t)] . ' ' . date('Y', $t);
    }

    /**
     * Chemin web d'une image reglee, ou le defaut si aucune n'a ete televersee.
     * Le defaut permet de garder les visuels d'origine tant que personne n'en
     * a depose de nouveaux.
     */
    function vitrineImage(string $cle, string $defaut = ''): string
    {
        $v = vitrineVal($cle, '');
        if ($v === '') { return $defaut; }
        // Chemin absolu ou URL : on le rend tel quel.
        if (preg_match('#^(https?:)?/#', $v)) { return $v; }
        return '/file/vitrine/' . ltrim($v, '/');
    }
}
