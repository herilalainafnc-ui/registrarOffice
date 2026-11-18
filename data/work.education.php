<?php
/* =============================================================================
   Work Education : la tache de chaque etudiant, session par session.

   Le responsable du Work Education (compte de niveau 9, ROLE_WORK_EDUCATION)
   l'attribue sur /work-education (src/work-education.php) ; elle s'imprime dans
   le champ « Work education » de la fiche d'inscription
   (src/extenssionPrint/fiche_inscription.php), qui etait une case vide a
   remplir a la main.

   Table t_work_education (data/migration_work_education.sql) : une ligne par
   session et matricule. Les etudiants de Master n'ont pas de Work Education
   (leur fiche n'a pas le champ) : ils ne sont pas proposes.

   Suggestions : les taches deja attribuees, puis le catalogue de l'ancien
   systeme (table work_education : BIBLIOTHEQUE, PARK, MAINTENANCE...).
   ========================================================================== */

require_once __DIR__ . '/finance.bareme.php'; // baremeNiveaux(), baremeCategorie()

if (!defined('WE_TACHE_MAX')) define('WE_TACHE_MAX', 200);

if (!function_exists('weTablePresente')) {

/** La table existe-t-elle (migration appliquee) ? */
function weTablePresente(PDO $dtb): bool
{
    static $presente = null;
    if ($presente === null) {
        try {
            $dtb->query('SELECT 1 FROM t_work_education LIMIT 1');
            $presente = true;
        } catch (PDOException $e) {
            $presente = false;
        }
    }
    return $presente;
}

/** La tache d'un etudiant pour une session, '' s'il n'en a pas. */
function weTache(PDO $dtb, string $studentId, $sessionId): string
{
    if ($studentId === '' || (int) $sessionId <= 0 || !weTablePresente($dtb)) {
        return '';
    }
    $req = $dtb->prepare('SELECT tache FROM t_work_education WHERE session_id = :s AND student_id = :e LIMIT 1');
    $req->execute(['s' => (int) $sessionId, 'e' => $studentId]);
    return (string) ($req->fetchColumn() ?: '');
}

/** Texte d'une tache tel qu'on l'enregistre : espaces resserres, 200 caracteres au plus. */
function weNettoyer(string $tache): string
{
    $tache = trim((string) preg_replace('/\s+/u', ' ', $tache));
    return mb_substr($tache, 0, WE_TACHE_MAX);
}

/**
 * Les inscrits d'une session (une fiche active par matricule, comme les
 * exports : data/etudiants.annee.php), avec leur tache.
 *
 * @return array{etudiants: array<int, array>, master: int}
 *   etudiants : matricule, nom, prenom, photo, mention, parcours, niveau,
 *   niveau_libelle, inscrit_le (date de l inscription), nouveau (premiere
 *   inscription a l universite), tache, modifie_le ; master : inscrits de
 *   Master ecartes. Ordre : les derniers inscrits d abord (date, puis ordre
 *   d enregistrement) ; la page propose aussi le nom, la mention, le niveau.
 */
function weEtudiants(PDO $dtb, int $sessionId): array
{
    $avecTable = weTablePresente($dtb);
    $sql = 'SELECT ins.student_id, ins.niveau_std, ins.etude_mention, ins.parcours_std, ins.date_entry, ins.new_student,
                   e.student_nom, e.student_prenom, e.image_student, e.etude_envisage, e.etude_option, e.annee_etude'
         . ($avecTable ? ', w.tache, w.modifie_le' : ", '' AS tache, NULL AS modifie_le") . '
              FROM t_2024_inscription_session ins
              JOIN tbl_2024_etudiant e ON e.id = (
                   SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id
                    ORDER BY e2.`remove` ASC, e2.id ASC LIMIT 1)'
         . ($avecTable ? ' LEFT JOIN t_work_education w ON w.session_id = ins.session_id AND w.student_id = ins.student_id' : '') . '
             WHERE ins.session_id = :s AND COALESCE(ins.`remove`, 0) != 1
             ORDER BY ins.date_entry DESC, ins.id DESC';
    $req = $dtb->prepare($sql);
    $req->execute(['s' => $sessionId]);

    $libelles = array_column(baremeNiveaux(), 'libelle', 'niveau');
    $etudiants = [];
    $master = [];
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $matricule = (string) $l['student_id'];
        if (isset($etudiants[$matricule]) || isset($master[$matricule])) {
            continue; // deux inscriptions a la meme session : une seule ligne, la plus recente
        }
        /* Le niveau de l'inscription (celui de cette session) ; a defaut,
           celui de la fiche. */
        $niveau = $l['niveau_std'] !== null && $l['niveau_std'] !== '' ? (int) $l['niveau_std']
                : ($l['annee_etude'] !== null && $l['annee_etude'] !== '' ? (int) $l['annee_etude'] : null);
        if ($niveau !== null && baremeCategorie($niveau) === 'M') {
            $master[$matricule] = true;
            continue;
        }
        $etudiants[$matricule] = [
            'id'       => $matricule,
            'nom'      => trim((string) $l['student_nom']),
            'prenom'   => trim((string) $l['student_prenom']),
            'photo'    => trim((string) $l['image_student']),
            /* Le nom de la mention de la fiche (« Informatique ») ; l'inscription
               n'en garde que le code (INFO, THEO...). */
            'mention'  => trim((string) ($l['etude_envisage'] ?: $l['etude_mention'])),
            'parcours' => trim((string) ($l['etude_option'] ?: $l['parcours_std'])),
            'niveau'   => $niveau,
            'niveau_libelle' => $niveau !== null ? (string) ($libelles[$niveau] ?? '') : '',
            'inscrit_le' => $l['date_entry'] ?: null,
            'nouveau'  => (int) $l['new_student'] === 1,
            'tache'    => (string) ($l['tache'] ?? ''),
            'modifie_le' => $l['modifie_le'] ?? null,
        ];
    }
    return ['etudiants' => array_values($etudiants), 'master' => count($master)];
}

/**
 * Empreinte d'une session : change des qu'un etudiant s'y inscrit, en est
 * retire ou change de niveau, ou qu'une tache est donnee, modifiee ou retiree.
 * La page la demande toutes les 15 secondes (quelques octets) et ne recharge
 * la liste que si elle a change.
 */
function weSignature(PDO $dtb, int $sessionId): string
{
    $ins = $dtb->prepare('SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT(student_id, \':\', COALESCE(niveau_std, \'\')))), 0)
                            FROM t_2024_inscription_session WHERE session_id = :s AND COALESCE(`remove`, 0) != 1');
    $ins->execute(['s' => $sessionId]);
    $empreinte = implode('.', $ins->fetch(PDO::FETCH_NUM));
    if (weTablePresente($dtb)) {
        $taches = $dtb->prepare('SELECT COUNT(*), COALESCE(SUM(CRC32(CONCAT(student_id, \':\', tache))), 0) FROM t_work_education WHERE session_id = :s');
        $taches->execute(['s' => $sessionId]);
        $empreinte .= '.' . implode('.', $taches->fetch(PDO::FETCH_NUM));
    }
    return $sessionId . ':' . $empreinte;
}

/**
 * Taches a proposer pendant la saisie : celles deja attribuees (les plus
 * frequentes d'abord), puis le catalogue de l'ancien systeme. Sans doublon,
 * a la casse pres.
 *
 * @return string[]
 */
function weSuggestions(PDO $dtb): array
{
    $vues = [];
    $liste = [];
    $ajouter = static function (string $t) use (&$vues, &$liste) {
        $t = weNettoyer($t);
        $cle = mb_strtolower($t);
        if ($t === '' || isset($vues[$cle])) { return; }
        $vues[$cle] = true;
        $liste[] = $t;
    };
    if (weTablePresente($dtb)) {
        foreach ($dtb->query('SELECT tache FROM t_work_education GROUP BY tache ORDER BY COUNT(*) DESC, tache LIMIT 300')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $ajouter((string) $t);
        }
    }
    try {
        $catalogue = $dtb->query("SELECT worked_desc FROM work_education WHERE short_code <> 'N/A' AND worked_desc IS NOT NULL AND worked_desc <> '' ORDER BY worked_desc")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($catalogue as $t) { $ajouter((string) $t); }
    } catch (PDOException $e) {
        // pas de catalogue sur ce serveur : les taches deja saisies suffisent
    }
    return $liste;
}

/**
 * Attribue (ou retire, si $tache est vide) une tache a un ou plusieurs inscrits
 * de la session. Seuls les inscrits de la session (hors Master) sont touches.
 *
 * @param string[] $matricules
 * @return array{changes: int, taches: array<string, string>}
 *   taches : matricule => tache apres enregistrement ('' = aucune).
 * @throws InvalidArgumentException session inconnue, aucun etudiant valable.
 */
function weAttribuer(PDO $dtb, int $sessionId, array $matricules, string $tache, ?int $userId): array
{
    if (!weTablePresente($dtb)) {
        throw new InvalidArgumentException('La table du Work Education n’existe pas encore sur ce serveur : appliquez data/migration_work_education.sql.');
    }
    $existe = $dtb->prepare('SELECT 1 FROM t_2023_session WHERE session_id = :s');
    $existe->execute(['s' => $sessionId]);
    if (!$existe->fetchColumn()) {
        throw new InvalidArgumentException('Session inconnue.');
    }

    $tache = weNettoyer($tache);
    $demandes = array_values(array_unique(array_filter(array_map(static fn ($m) => trim((string) $m), $matricules), 'strlen')));
    if (!$demandes || count($demandes) > 1000) {
        throw new InvalidArgumentException('Choisissez au moins un étudiant.');
    }

    /* Les inscrits de la session parmi les matricules demandes, hors Master. */
    $admis = [];
    foreach (weEtudiants($dtb, $sessionId)['etudiants'] as $e) {
        $admis[$e['id']] = $e['tache'];
    }
    $cibles = array_values(array_filter($demandes, static fn ($m) => array_key_exists($m, $admis)));
    if (!$cibles) {
        throw new InvalidArgumentException('Ces étudiants ne sont pas inscrits à cette session.');
    }

    $maintenant = date('Y-m-d H:i:s');
    $changes = 0;
    $apres = [];
    $dtb->beginTransaction();
    try {
        $inserer = $dtb->prepare('INSERT INTO t_work_education (session_id, student_id, tache, attribue_par, attribue_le, modifie_par, modifie_le)
                                  VALUES (:s, :e, :t, :u1, :d1, :u2, :d2)');
        $modifier = $dtb->prepare('UPDATE t_work_education SET tache = :t, modifie_par = :u, modifie_le = :d WHERE session_id = :s AND student_id = :e');
        $retirer = $dtb->prepare('DELETE FROM t_work_education WHERE session_id = :s AND student_id = :e');
        foreach ($cibles as $m) {
            $avant = (string) $admis[$m];
            $apres[$m] = $tache;
            if ($avant === $tache) {
                continue;
            }
            if ($tache === '') {
                $retirer->execute(['s' => $sessionId, 'e' => $m]);
            } elseif ($avant === '') {
                $inserer->execute(['s' => $sessionId, 'e' => $m, 't' => $tache, 'u1' => $userId, 'd1' => $maintenant, 'u2' => $userId, 'd2' => $maintenant]);
            } else {
                $modifier->execute(['t' => $tache, 'u' => $userId, 'd' => $maintenant, 's' => $sessionId, 'e' => $m]);
            }
            $changes++;
        }
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    return ['changes' => $changes, 'taches' => $apres];
}

}

/* =============================================================================
   Export PDF de la liste (demande du 27/10/2026 : « un bouton d'export en pdf de
   liste, qu'on puisse filtrer pour chaque service, mention, année ou bien tout »).

   Portee : « session » (celle de la page), « annee » (toutes les sessions de la
   meme annee universitaire) ou « tout » (toutes les sessions). Filtres : mention,
   niveau, nouveaux / anciens, service (la tache : « __sans », « __avec » ou
   son texte exact). Les memes regles que la page : Licence seulement, une ligne
   par etudiant et par session.
   ========================================================================== */
if (!function_exists('weExportSessions')) {

/** Les sessions de la portee, de la plus recente a la plus ancienne. */
function weExportSessions(PDO $dtb, int $sessionId, string $portee): array
{
    $rang = [1 => 0, 3 => 1, 2 => 2, 4 => 3];
    $toutes = $dtb->query('SELECT session_id, session_name, session_year, session_semester FROM t_2023_session')->fetchAll(PDO::FETCH_ASSOC);
    usort($toutes, static function ($a, $b) use ($rang) {
        return [$b['session_year'], $rang[(int) $b['session_semester']] ?? 9, (int) $b['session_id']]
           <=> [$a['session_year'], $rang[(int) $a['session_semester']] ?? 9, (int) $a['session_id']];
    });
    $courante = null;
    foreach ($toutes as $s) { if ((int) $s['session_id'] === $sessionId) { $courante = $s; } }
    if ($courante === null) { return []; }
    if ($portee === 'tout') { return $toutes; }
    if ($portee === 'annee') {
        return array_values(array_filter($toutes, static fn ($s) => $s['session_year'] === $courante['session_year']));
    }
    return [$courante];
}

/**
 * Tous les etudiants de la portee, sans filtre, avec leur session.
 *
 * @return array{lignes: array<int, array>, sessions: array<int, array>}
 */
function weExportLignes(PDO $dtb, int $sessionId, string $portee): array
{
    $sessions = weExportSessions($dtb, $sessionId, $portee);
    $lignes = [];
    foreach ($sessions as $s) {
        foreach (weEtudiants($dtb, (int) $s['session_id'])['etudiants'] as $e) {
            $e = weExportAffichage($e);
            $e['session_id'] = (int) $s['session_id'];
            $e['session'] = $s['session_name'] . ' ' . $s['session_year'];
            $e['annee'] = $s['session_year'];
            $lignes[] = $e;
        }
    }
    return ['lignes' => $lignes, 'sessions' => $sessions];
}

/** Dans la liste exportee, la remise a niveau (niveau 0) s'affiche « Licence 1 » : le libelle pourrait deranger l'etudiant (demande du 27/10/2026). */
function weExportAffichage(array $e): array
{
    if ($e['niveau'] === 0) {
        $e['niveau'] = 1;
        $e['niveau_libelle'] = 'Licence 1';
    }
    return $e;
}

/** Les filtres recus, ramenes a des valeurs connues. */
function weExportFiltres(array $source): array
{
    $texte = static fn ($v) => trim(mb_substr((string) ($v ?? ''), 0, WE_TACHE_MAX));
    $statut = $texte($source['statut'] ?? '');
    $niveau = $texte($source['niveau'] ?? '');
    return [
        'mention' => $texte($source['mention'] ?? ''),
        'niveau'  => ctype_digit($niveau) ? $niveau : '',
        'statut'  => in_array($statut, ['nouveau', 'ancien'], true) ? $statut : '',
        'tache'   => $texte($source['tache'] ?? ''),
    ];
}

/** Ne garde que les etudiants qui repondent aux filtres. */
function weExportFiltrer(array $lignes, array $f): array
{
    return array_values(array_filter($lignes, static function ($e) use ($f) {
        if ($f['mention'] !== '' && $e['mention'] !== $f['mention']) { return false; }
        if ($f['niveau'] !== '' && (string) $e['niveau'] !== $f['niveau']) { return false; }
        if ($f['statut'] !== '' && $e['nouveau'] !== ($f['statut'] === 'nouveau')) { return false; }
        if ($f['tache'] === '__sans' && $e['tache'] !== '') { return false; }
        if ($f['tache'] === '__avec' && $e['tache'] === '') { return false; }
        if (!in_array($f['tache'], ['', '__sans', '__avec'], true) && $e['tache'] !== $f['tache']) { return false; }
        return true;
    }));
}

/**
 * Ce qu'on peut choisir dans la portee : mentions, niveaux, services (avec leur effectif).
 *
 * @return array{mentions: string[], niveaux: array<int, array{valeur: string, libelle: string}>, services: array<int, array{tache: string, n: int}>, sans: int, total: int}
 */
function weExportOptions(array $lignes): array
{
    $mentions = [];
    $niveaux = [];
    $services = [];
    $sans = 0;
    foreach ($lignes as $e) {
        if ($e['mention'] !== '') { $mentions[$e['mention']] = true; }
        if ($e['niveau'] !== null) { $niveaux[(string) $e['niveau']] = $e['niveau_libelle'] !== '' ? $e['niveau_libelle'] : 'Niveau ' . $e['niveau']; }
        if ($e['tache'] === '') { $sans++; } else { $services[$e['tache']] = ($services[$e['tache']] ?? 0) + 1; }
    }
    $mentions = array_keys($mentions);
    sort($mentions, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($niveaux, SORT_NUMERIC);
    arsort($services);
    $liste = [];
    foreach ($services as $t => $n) { $liste[] = ['tache' => (string) $t, 'n' => $n]; }
    usort($liste, static fn ($a, $b) => $b['n'] <=> $a['n'] ?: strcasecmp($a['tache'], $b['tache']));
    $nv = [];
    foreach ($niveaux as $v => $l) { $nv[] = ['valeur' => (string) $v, 'libelle' => (string) $l]; }
    return ['mentions' => $mentions, 'niveaux' => $nv, 'services' => $liste, 'sans' => $sans, 'total' => count($lignes)];
}

}
