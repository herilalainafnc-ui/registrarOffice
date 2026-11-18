<?php
/* =============================================================================
   Inscriptions tardives (bulletin academique, § 334).

   « Une periode supplementaire de deux semaines a compter du commencement des
   cours est prevue pour les inscriptions tardives. Durant cette periode,
   l'etudiant(e) est soumis(e) a une penalite de 10 000 Ariary par jour. »

   LE CALENDRIER (t_inscription_calendrier), par session : dernier jour des
   inscriptions normales, date limite des tardives, penalite par jour, jours
   non comptes. Les jours de retard sont des jours OUVRABLES (lundi a vendredi,
   jours feries exclus), comptes du lendemain de la fin des inscriptions
   normales jusqu'au jour de l'inscription compris : lundi = 1 jour.

   LA TRACE (t_inscription_trace), par inscription : posee par le guichet a la
   creation de l'inscription (inscription/app/generate.student.php), jamais
   reecrite par lui. Apres la date limite, l'inscription reste possible (choix
   du registrariat, 11/10/2026) : elle est marquee « hors delai » et le
   decompte continue.

   LA PENALITE est aussi une ligne des frais de l'etudiant, hors bareme
   (t_2024_etudiant_frais_perso, frais_id = IT_FRAIS_PENALITE) : la fiche
   d'inscription l'imprime dans les frais divers et ses totaux la comptent.
   fraisPersoAppliquer() (data/finance.frais.php) la laisse en place. Une
   exoneration (motif obligatoire) en retire tout ou partie.

   LES CALENDRIERS PARTICULIERS (t_inscription_calendrier_cas, 17/10/2026) :
   une mention, un niveau, les deux, ou un etudiant qui commence plus tard
   (Sciences infirmieres L2 et L3 en stage jusqu'au 1er novembre 2026,
   quelques L2 d'autres mentions) ont leurs propres dates : dernier jour
   sans penalite et date limite des tardives, avec un motif. La penalite par
   jour et les jours non comptes restent ceux de la session. Le plus precis
   l'emporte : l'etudiant, puis mention et niveau, mention seule, niveau seul.

   LES NOUVEAUX ETUDIANTS (badge « Nouveau » du guichet) ne sont jamais
   penalises (18/10/2026) : voir itProfils et itEvaluer.

   Page : /inscriptions/tardives (src/inscriptions.tardives.php).
   ========================================================================== */

require_once __DIR__ . '/finance.frais.php'; // fraisPersoInstaller()

if (!defined('IT_FRAIS_PENALITE')) define('IT_FRAIS_PENALITE', -1);
if (!defined('IT_PENALITE_DEFAUT')) define('IT_PENALITE_DEFAUT', 10000);

if (!function_exists('itTablesPresentes')) {

/** Les deux tables existent-elles (migration appliquee) ? */
function itTablesPresentes(PDO $dtb): bool
{
    static $presentes = null;
    if ($presentes === null) {
        try {
            $dtb->query('SELECT 1 FROM t_inscription_calendrier LIMIT 1');
            $dtb->query('SELECT 1 FROM t_inscription_trace LIMIT 1');
            $presentes = true;
        } catch (PDOException $e) {
            $presentes = false;
        }
    }
    return $presentes;
}

/** Une date AAAA-MM-JJ valable, ou null. */
function itDate($v): ?string
{
    $v = trim((string) $v);
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/** Les jours non comptes, depuis le champ du calendrier. @return string[] */
function itJoursExclus(?string $champ): array
{
    $jours = [];
    foreach (preg_split('/[\s,;]+/', (string) $champ, -1, PREG_SPLIT_NO_EMPTY) as $j) {
        if (($d = itDate($j)) !== null) { $jours[$d] = true; }
    }
    ksort($jours);
    return array_keys($jours);
}

/** Le calendrier d'une session, ou null s'il n'y en a pas. */
function itCalendrier(PDO $dtb, int $sessionId): ?array
{
    if (!itTablesPresentes($dtb)) { return null; }
    $req = $dtb->prepare('SELECT * FROM t_inscription_calendrier WHERE session_id = :s');
    $req->execute(['s' => $sessionId]);
    $c = $req->fetch(PDO::FETCH_ASSOC);
    if (!$c) { return null; }
    $c['penalite_jour'] = (int) $c['penalite_jour'];
    $c['exclus'] = itJoursExclus($c['jours_exclus'] ?? '');
    return $c;
}

/* ------------------------------------------------- calendriers particuliers */

/** La table des calendriers particuliers existe-t-elle (migration appliquee) ? */
function itCasPresents(PDO $dtb): bool
{
    static $presente = null;
    if ($presente === null) {
        try {
            $dtb->query('SELECT 1 FROM t_inscription_calendrier_cas LIMIT 1');
            $presente = true;
        } catch (PDOException $e) {
            $presente = false;
        }
    }
    return $presente;
}

/** Libelles des niveaux : 0 => « Remise a niveau », 2 => « Licence 2 »... */
function itNiveaux(): array
{
    return array_column(baremeNiveaux(), 'libelle', 'niveau');
}

/** Qui suit le calendrier : « Sciences Infirmieres · Licence 2 », « Licence 2 · toutes mentions »... */
function itCasLibelle(array $c): string
{
    if ($c['etudiant'] !== null) {
        $nom = trim(mb_strtoupper((string) $c['nom']) . ' ' . (string) $c['prenom']);
        return ($nom !== '' ? $nom : 'Étudiant') . ' (' . $c['etudiant'] . ')';
    }
    $niveau = $c['niveau'] !== null ? (itNiveaux()[$c['niveau']] ?? 'Niveau ' . $c['niveau']) : null;
    $mention = $c['mention'] !== null ? ($c['mention_nom'] !== '' ? $c['mention_nom'] : $c['mention']) : null;
    if ($mention !== null && $niveau !== null) { return $mention . ' · ' . $niveau; }
    return $mention !== null ? $mention . ' · tous niveaux' : $niveau . ' · toutes mentions';
}

/**
 * Les calendriers particuliers d'une session, du plus precis au plus large.
 * rang : 0 etudiant, 1 mention et niveau, 2 mention, 3 niveau.
 */
function itCas(PDO $dtb, int $sessionId): array
{
    if (!itCasPresents($dtb)) { return []; }
    $req = $dtb->prepare(
        'SELECT c.*, COALESCE(f.filiere_description, \'\') AS mention_nom,
                TRIM(CONCAT(COALESCE(u.prenom, \'\'), \' \', COALESCE(u.nom, \'\'))) AS modifie_par_nom,
                e.student_nom, e.student_prenom
           FROM t_inscription_calendrier_cas c
           LEFT JOIN filiere f ON f.filiere_sigle = c.mention
           LEFT JOIN compt_utilisateur u ON u.id = c.modifie_par
           LEFT JOIN tbl_2024_etudiant e ON c.student_id IS NOT NULL AND e.id = (
                SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = c.student_id
                 ORDER BY e2.`remove` ASC, e2.id ASC LIMIT 1)
          WHERE c.session_id = :s'
    );
    $req->execute(['s' => $sessionId]);
    $cas = [];
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $c = [
            'id'             => (int) $l['id'],
            'etudiant'       => $l['student_id'] !== null && $l['student_id'] !== '' ? (string) $l['student_id'] : null,
            'mention'        => $l['mention'] !== null && $l['mention'] !== '' ? (string) $l['mention'] : null,
            'mention_nom'    => (string) $l['mention_nom'],
            'niveau'         => $l['niveau'] !== null ? (int) $l['niveau'] : null,
            'nom'            => trim((string) ($l['student_nom'] ?? '')),
            'prenom'         => trim((string) ($l['student_prenom'] ?? '')),
            'fin_normale'    => (string) $l['fin_normale'],
            'limite_tardive' => (string) $l['limite_tardive'],
            'motif'          => (string) $l['motif'],
            'modifie_par'    => (string) $l['modifie_par_nom'],
            'modifie_le'     => $l['modifie_le'],
        ];
        $c['rang'] = $c['etudiant'] !== null ? 0 : ($c['mention'] !== null && $c['niveau'] !== null ? 1 : ($c['mention'] !== null ? 2 : 3));
        $c['libelle'] = itCasLibelle($c);
        $cas[] = $c;
    }
    usort($cas, static fn ($a, $b) => [$a['rang'], $a['libelle']] <=> [$b['rang'], $b['libelle']]);
    return $cas;
}

/** Le calendrier particulier d'un etudiant (le plus precis), ou null. */
function itCasPour(array $cas, string $studentId, ?string $mention, ?int $niveau): ?array
{
    $retenu = null;
    foreach ($cas as $c) {
        if ($c['etudiant'] !== null) {
            if ($c['etudiant'] !== $studentId) { continue; }
        } elseif (($c['mention'] !== null && $c['mention'] !== $mention) || ($c['niveau'] !== null && $c['niveau'] !== $niveau)) {
            continue;
        }
        if ($retenu === null || $c['rang'] < $retenu['rang']) { $retenu = $c; }
    }
    return $retenu;
}

/**
 * Mention (sigle), niveau et « nouveau » d'etudiants pour une session.
 *
 * Niveau : celui de leur inscription a cette session, sinon celui de leur
 * fiche (celui que le guichet va inscrire).
 * Nouveau (18/10/2026 : « pour les nouveaux etudiants, il n'y a pas de
 * penalisation ») : la regle du guichet (inscNouvelEtudiant,
 * data/inscription.session.php) — aucune autre session suivie, inscriptions
 * ET notes, et pas marque « ancien » (drapeau new_student de l'inscription a
 * cette session, sinon de la fiche). Jusqu'au 20/10/2026 : moins de deux
 * inscriptions, sans les notes des annees d'avant la table des inscriptions —
 * un ancien comme 10852 passait pour nouveau, donc sans penalite.
 *
 * @return array<string, array{mention:?string, niveau:?int, nouveau:bool}>
 */
function itProfils(PDO $dtb, int $sessionId, array $studentIds): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => trim((string) $v), $studentIds), 'strlen')));
    if (!$ids) { return []; }
    $profils = [];
    foreach (array_chunk($ids, 400) as $lot) {
        $req = $dtb->prepare(
            'SELECT e.student_id, f.filiere_sigle AS mention, e.annee_etude,
                    (SELECT ins.niveau_std FROM t_2024_inscription_session ins
                      WHERE ins.student_id = e.student_id AND ins.session_id = ? AND COALESCE(ins.`remove`, 0) != 1
                      ORDER BY ins.id LIMIT 1) AS niveau_session,
                    (SELECT ins.new_student FROM t_2024_inscription_session ins
                      WHERE ins.student_id = e.student_id AND ins.session_id = ? AND COALESCE(ins.`remove`, 0) != 1
                      ORDER BY ins.id LIMIT 1) AS nouveau_session,
                    e.new_student AS nouveau_fiche,
                    (EXISTS (SELECT 1 FROM t_2024_inscription_session c WHERE c.student_id = e.student_id AND c.session_id != ?)
                     OR EXISTS (SELECT 1 FROM t_2023_notes n WHERE n.student_id = e.student_id AND n.session_id != ?)) AS historique
               FROM tbl_2024_etudiant e
               LEFT JOIN filiere f ON f.filiere_description = e.etude_envisage
              WHERE e.student_id IN (' . implode(',', array_fill(0, count($lot), '?')) . ')
              ORDER BY e.`remove` ASC, e.id ASC'
        );
        $req->execute(array_merge([$sessionId, $sessionId, $sessionId, $sessionId], $lot));
        foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $id = (string) $l['student_id'];
            if (isset($profils[$id])) { continue; } // la fiche active, comme le guichet
            $niveau = $l['niveau_session'] !== null && $l['niveau_session'] !== '' ? $l['niveau_session'] : $l['annee_etude'];
            $profils[$id] = [
                'mention' => $l['mention'] !== null && $l['mention'] !== '' ? (string) $l['mention'] : null,
                'niveau'  => $niveau !== null && $niveau !== '' ? (int) $niveau : null,
                'nouveau' => !(int) $l['historique'] && (int) ($l['nouveau_session'] ?? $l['nouveau_fiche'] ?? 1) !== 0,
            ];
        }
    }
    return $profils;
}

/**
 * Le calendrier de la session tel qu'il vaut pour un etudiant : ses dates
 * particulieres s'il en a. « cas » : le calendrier particulier applique, ou
 * null ; « nouveau » : nouvel etudiant, jamais penalise (itEvaluer).
 */
function itAppliquerCas(?array $calendrier, array $cas, string $studentId, ?array $profil): ?array
{
    if (!$calendrier) { return null; }
    $calendrier['nouveau'] = !empty($profil['nouveau']);
    $calendrier['cas'] = null;
    $regle = $cas ? itCasPour($cas, $studentId, $profil['mention'] ?? null, $profil['niveau'] ?? null) : null;
    if ($regle) {
        $calendrier['fin_normale'] = $regle['fin_normale'];
        $calendrier['limite_tardive'] = $regle['limite_tardive'];
        $calendrier['cas'] = ['id' => $regle['id'], 'libelle' => $regle['libelle'], 'motif' => $regle['motif']];
    }
    return $calendrier;
}

/** Le calendrier d'une session pour un etudiant (voir itAppliquerCas). */
function itCalendrierPour(PDO $dtb, int $sessionId, string $studentId): ?array
{
    $c = itCalendrier($dtb, $sessionId);
    if (!$c) { return null; }
    $cas = itCas($dtb, $sessionId);
    $profil = itProfils($dtb, $sessionId, [$studentId])[$studentId] ?? null;
    return itAppliquerCas($c, $cas, $studentId, $profil);
}

/**
 * Jours ouvrables (lundi a vendredi, hors jours exclus) apres $apres, jusqu'a
 * $jusqua compris. 0 si $jusqua n'est pas apres $apres.
 */
function itJoursOuvrables(string $apres, string $jusqua, array $exclus = []): int
{
    $exclus = array_flip($exclus);
    $jour = new DateTime($apres . ' 12:00:00');
    $fin = new DateTime($jusqua . ' 12:00:00');
    $n = 0;
    for ($i = 0; $i < 800 && $jour < $fin; $i++) {
        $jour->modify('+1 day');
        if ((int) $jour->format('N') <= 5 && !isset($exclus[$jour->format('Y-m-d')])) { $n++; }
    }
    return $n;
}

/**
 * Le retard d'une inscription faite le $quand (date ou date et heure).
 *
 * @return array{jours:int, hors_delai:bool, penalite:int, etat:string}
 *   etat : sans-calendrier, a-l-heure, tardive, hors-delai.
 */
function itEvaluer(?array $calendrier, string $quand): array
{
    if (!$calendrier) {
        return ['jours' => 0, 'hors_delai' => false, 'penalite' => 0, 'etat' => 'sans-calendrier'];
    }
    if (!empty($calendrier['nouveau'])) {
        // nouvel etudiant : jamais penalise (decision du 18/10/2026)
        return ['jours' => 0, 'hors_delai' => false, 'penalite' => 0, 'etat' => 'nouveau'];
    }
    $jour = substr($quand, 0, 10);
    $jours = itJoursOuvrables($calendrier['fin_normale'], $jour, $calendrier['exclus']);
    $horsDelai = $jour > $calendrier['limite_tardive'];
    return [
        'jours'      => $jours,
        'hors_delai' => $horsDelai,
        'penalite'   => $jours * (int) $calendrier['penalite_jour'],
        'etat'       => $horsDelai ? 'hors-delai' : ($jours > 0 ? 'tardive' : 'a-l-heure'),
    ];
}

/** Libelle de la ligne de frais : « Penalite d'inscription tardive (3 jours ouvrables) ». */
function itLibellePenalite(int $jours, bool $horsDelai, int $exoneration = 0): string
{
    $l = 'Pénalité d\'inscription tardive (' . $jours . ' jour' . ($jours > 1 ? 's' : '') . ' ouvrable' . ($jours > 1 ? 's' : '')
       . ($horsDelai ? ', hors délai' : '')
       . ($exoneration > 0 ? ', ' . number_format($exoneration, 0, '', ' ') . ' Ar exonérés' : '') . ')';
    return mb_substr($l, 0, 120);
}

/**
 * Ecrit (ou retire) la ligne de penalite dans les frais de l'etudiant : le
 * montant du apres exoneration. Une ligne a 0 est retiree.
 */
function itEcrirePenalite(PDO $dtb, string $studentId, int $sessionId, array $trace, ?int $userId): void
{
    fraisPersoInstaller($dtb);
    $net = max(0, (int) $trace['penalite'] - (int) $trace['exoneration']);
    $existe = $dtb->prepare('SELECT id FROM t_2024_etudiant_frais_perso WHERE student_id = :e AND session_id = :s AND frais_id = :f LIMIT 1');
    $existe->execute(['e' => $studentId, 's' => $sessionId, 'f' => IT_FRAIS_PENALITE]);
    $id = $existe->fetchColumn();
    if ($net <= 0) {
        if ($id !== false) {
            $dtb->prepare('UPDATE t_2024_etudiant_frais_perso SET `remove` = 1, last_change_user_id = :u WHERE student_id = :e AND session_id = :s AND frais_id = :f')
                ->execute(['u' => $userId, 'e' => $studentId, 's' => $sessionId, 'f' => IT_FRAIS_PENALITE]);
        }
        return;
    }
    $libelle = itLibellePenalite((int) $trace['jours_retard'], (bool) $trace['hors_delai'], (int) $trace['exoneration']);
    if ($id !== false) {
        $dtb->prepare('UPDATE t_2024_etudiant_frais_perso SET libelle = :l, montant = :m, `remove` = 0, last_change_user_id = :u
                        WHERE student_id = :e AND session_id = :s AND frais_id = :f')
            ->execute(['l' => $libelle, 'm' => $net, 'u' => $userId, 'e' => $studentId, 's' => $sessionId, 'f' => IT_FRAIS_PENALITE]);
    } else {
        $dtb->prepare('INSERT INTO t_2024_etudiant_frais_perso (student_id, session_id, frais_id, libelle, montant, `remove`, date_entry, last_change_user_id)
                        VALUES (:e, :s, :f, :l, :m, 0, :d, :u)')
            ->execute(['e' => $studentId, 's' => $sessionId, 'f' => IT_FRAIS_PENALITE, 'l' => $libelle, 'm' => $net, 'd' => date('Y-m-d H:i:s'), 'u' => $userId]);
    }
}

/**
 * Le guichet cree une inscription : sa trace (date, heure, agent, retard) et,
 * en retard, sa penalite. Une trace deja posee n'est pas reecrite.
 *
 * @return array{jours:int, hors_delai:bool, penalite:int, etat:string}|null
 *   null sans les tables (migration non appliquee) ou trace deja posee.
 */
function itTracer(PDO $dtb, int $sessionId, string $studentId, ?int $userId): ?array
{
    if (!itTablesPresentes($dtb) || $sessionId <= 0 || trim($studentId) === '') { return null; }
    $deja = $dtb->prepare('SELECT 1 FROM t_inscription_trace WHERE session_id = :s AND student_id = :e');
    $deja->execute(['s' => $sessionId, 'e' => $studentId]);
    if ($deja->fetchColumn()) { return null; }

    $maintenant = date('Y-m-d H:i:s');
    $c = itCalendrierPour($dtb, $sessionId, $studentId); // ses dates particulieres s'il en a
    $retard = itEvaluer($c, $maintenant);
    $dtb->prepare('INSERT INTO t_inscription_trace (session_id, student_id, inscrit_le, inscrit_par, origine, jours_retard, hors_delai, penalite)
                    VALUES (:s, :e, :d, :u, \'guichet\', :j, :h, :p)')
        ->execute(['s' => $sessionId, 'e' => $studentId, 'd' => $maintenant, 'u' => $userId,
                   'j' => $retard['jours'], 'h' => $retard['hors_delai'] ? 1 : 0, 'p' => $retard['penalite']]);
    if ($retard['penalite'] > 0) {
        itEcrirePenalite($dtb, $studentId, $sessionId,
            ['penalite' => $retard['penalite'], 'exoneration' => 0, 'jours_retard' => $retard['jours'], 'hors_delai' => $retard['hors_delai']], $userId);
    }
    return $retard + itApercuDates($dtb, $sessionId, $c);
}

/** Les dates d'un calendrier pour le guichet, et celles de la session quand il est particulier. */
function itApercuDates(PDO $dtb, int $sessionId, ?array $c): array
{
    $dates = ['fin_normale' => $c['fin_normale'] ?? null, 'limite_tardive' => $c['limite_tardive'] ?? null,
              'penalite_jour' => $c['penalite_jour'] ?? null, 'cas' => $c['cas'] ?? null, 'nouveau' => !empty($c['nouveau'])];
    if (!empty($c['cas'])) {
        $s = itCalendrier($dtb, $sessionId);
        $dates['session_fin_normale'] = $s['fin_normale'] ?? null;
    } else {
        $dates['session_fin_normale'] = $c['fin_normale'] ?? null;
    }
    return $dates;
}

/**
 * Ce que coute une inscription faite maintenant (apercu du guichet) ; pour un
 * etudiant donne, avec ses dates particulieres s'il en a.
 */
function itApercu(PDO $dtb, int $sessionId, ?string $studentId = null): ?array
{
    $c = $studentId !== null && trim($studentId) !== '' ? itCalendrierPour($dtb, $sessionId, trim($studentId)) : itCalendrier($dtb, $sessionId);
    if (!$c) { return null; }
    return itEvaluer($c, date('Y-m-d H:i:s')) + itApercuDates($dtb, $sessionId, $c);
}

/**
 * Enregistre le calendrier d'une session, puis recalcule les penalites des
 * inscriptions deja faites au guichet (exonerations gardees).
 *
 * @return int nombre d'inscriptions dont la penalite a change
 * @throws InvalidArgumentException dates absentes ou dans le desordre, penalite hors bornes
 */
function itEnregistrerCalendrier(PDO $dtb, int $sessionId, $finNormale, $limite, $penalite, $exclus, ?int $userId): int
{
    if (!itTablesPresentes($dtb)) {
        throw new InvalidArgumentException('Les tables des inscriptions tardives n’existent pas encore : appliquez data/migration_inscriptions_tardives.sql.');
    }
    $fin = itDate($finNormale);
    $lim = itDate($limite);
    if ($fin === null) { throw new InvalidArgumentException('Indiquez le dernier jour des inscriptions normales.'); }
    if ($lim === null) { throw new InvalidArgumentException('Indiquez la date limite des inscriptions tardives.'); }
    if ($lim < $fin) { throw new InvalidArgumentException('La date limite des inscriptions tardives doit suivre la fin des inscriptions normales.'); }
    $montant = (int) preg_replace('/\D/', '', (string) $penalite);
    if ($montant < 0 || $montant > 1000000) { throw new InvalidArgumentException('La pénalité par jour doit être comprise entre 0 et 1 000 000 Ar.'); }
    $jours = implode(',', itJoursExclus(is_array($exclus) ? implode(',', $exclus) : (string) $exclus));

    $dtb->beginTransaction();
    try {
        $dtb->prepare('INSERT INTO t_inscription_calendrier (session_id, fin_normale, limite_tardive, penalite_jour, jours_exclus, modifie_par, modifie_le)
                        VALUES (:s, :f, :l, :p, :j, :u, :d)
                        ON DUPLICATE KEY UPDATE fin_normale = VALUES(fin_normale), limite_tardive = VALUES(limite_tardive),
                            penalite_jour = VALUES(penalite_jour), jours_exclus = VALUES(jours_exclus), modifie_par = VALUES(modifie_par), modifie_le = VALUES(modifie_le)')
            ->execute(['s' => $sessionId, 'f' => $fin, 'l' => $lim, 'p' => $montant, 'j' => $jours !== '' ? $jours : null, 'u' => $userId, 'd' => date('Y-m-d H:i:s')]);
        $changes = itRecalculer($dtb, $sessionId, $userId);
        $dtb->commit();
        return $changes;
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
}

/**
 * Recalcule jours, hors delai et penalite des inscriptions faites au guichet,
 * chacune sur son calendrier (celui de la session, ou ses dates particulieres).
 */
function itRecalculer(PDO $dtb, int $sessionId, ?int $userId): int
{
    $c = itCalendrier($dtb, $sessionId);
    $cas = itCas($dtb, $sessionId);
    $req = $dtb->prepare('SELECT * FROM t_inscription_trace WHERE session_id = :s AND origine = \'guichet\'');
    $req->execute(['s' => $sessionId]);
    $traces = $req->fetchAll(PDO::FETCH_ASSOC);
    $profils = itProfils($dtb, $sessionId, array_column($traces, 'student_id')); // calendrier particulier, nouvel etudiant
    $maj = $dtb->prepare('UPDATE t_inscription_trace SET jours_retard = :j, hors_delai = :h, penalite = :p, exoneration = :x WHERE id = :id');
    $changes = 0;
    foreach ($traces as $t) {
        $sid = (string) $t['student_id'];
        $r = itEvaluer(itAppliquerCas($c, $cas, $sid, $profils[$sid] ?? null), $t['inscrit_le']);
        $exoneration = min((int) $t['exoneration'], $r['penalite']);
        if ($r['jours'] === (int) $t['jours_retard'] && (int) $r['hors_delai'] === (int) $t['hors_delai']
            && $r['penalite'] === (int) $t['penalite'] && $exoneration === (int) $t['exoneration']) {
            continue;
        }
        $maj->execute(['j' => $r['jours'], 'h' => $r['hors_delai'] ? 1 : 0, 'p' => $r['penalite'], 'x' => $exoneration, 'id' => $t['id']]);
        itEcrirePenalite($dtb, (string) $t['student_id'], $sessionId,
            ['penalite' => $r['penalite'], 'exoneration' => $exoneration, 'jours_retard' => $r['jours'], 'hors_delai' => $r['hors_delai']], $userId);
        $changes++;
    }
    return $changes;
}

/**
 * Enregistre un calendrier particulier (nouveau, ou $d['id'] pour le
 * modifier), puis recalcule les penalites de la session.
 *
 * $d : pour (groupe | etudiant), mention, niveau, student_id, fin_normale,
 * limite_tardive, motif.
 *
 * @return int nombre d'inscriptions dont la penalite a change
 * @throws InvalidArgumentException
 */
function itEnregistrerCas(PDO $dtb, int $sessionId, array $d, ?int $userId): int
{
    if (!itCasPresents($dtb)) {
        throw new InvalidArgumentException('La table des calendriers particuliers n’existe pas encore : appliquez data/migration_inscriptions_tardives_cas.sql.');
    }
    if (!itCalendrier($dtb, $sessionId)) { throw new InvalidArgumentException('Réglez d’abord le calendrier de la session.'); }
    $id = (int) ($d['id'] ?? 0);
    $etudiant = $mention = $niveau = null;
    if (($d['pour'] ?? '') === 'etudiant') {
        $etudiant = trim((string) ($d['student_id'] ?? ''));
        if ($etudiant === '') { throw new InvalidArgumentException('Choisissez l’étudiant.'); }
        $existe = $dtb->prepare('SELECT 1 FROM tbl_2024_etudiant WHERE student_id = :e LIMIT 1');
        $existe->execute(['e' => $etudiant]);
        if (!$existe->fetchColumn()) { throw new InvalidArgumentException('Étudiant introuvable (n° ' . $etudiant . ').'); }
    } else {
        $mention = trim((string) ($d['mention'] ?? ''));
        $mention = $mention === '' ? null : $mention;
        if ($mention !== null) {
            $connue = $dtb->prepare('SELECT 1 FROM filiere WHERE filiere_sigle = :m LIMIT 1');
            $connue->execute(['m' => $mention]);
            if (!$connue->fetchColumn()) { throw new InvalidArgumentException('Mention inconnue.'); }
        }
        $niveau = trim((string) ($d['niveau'] ?? '')) === '' ? null : (int) $d['niveau'];
        if ($niveau !== null && !isset(itNiveaux()[$niveau])) { throw new InvalidArgumentException('Niveau inconnu.'); }
        if ($mention === null && $niveau === null) { throw new InvalidArgumentException('Choisissez une mention, un niveau, ou les deux.'); }
    }
    $fin = itDate($d['fin_normale'] ?? '');
    $lim = itDate($d['limite_tardive'] ?? '');
    if ($fin === null) { throw new InvalidArgumentException('Indiquez le dernier jour sans pénalité.'); }
    if ($lim === null) { throw new InvalidArgumentException('Indiquez la date limite des inscriptions tardives.'); }
    if ($lim < $fin) { throw new InvalidArgumentException('La date limite des inscriptions tardives doit suivre le dernier jour sans pénalité.'); }
    $motif = trim((string) preg_replace('/\s+/u', ' ', (string) ($d['motif'] ?? '')));
    if (mb_strlen($motif) < 5) { throw new InvalidArgumentException('Donnez le motif (ex. : stage jusqu’au 1er novembre).'); }

    /* Une cible, un calendrier : le doublon serait ambigu. */
    $double = $dtb->prepare('SELECT id FROM t_inscription_calendrier_cas
                              WHERE session_id = :s AND id != :id AND student_id <=> :e AND mention <=> :m AND niveau <=> :n LIMIT 1');
    $double->execute(['s' => $sessionId, 'id' => $id, 'e' => $etudiant, 'm' => $mention, 'n' => $niveau]);
    if ($double->fetchColumn()) {
        throw new InvalidArgumentException($etudiant !== null ? 'Cet étudiant a déjà son calendrier particulier : modifiez-le.' : 'Ce groupe a déjà son calendrier particulier : modifiez-le.');
    }

    $valeurs = ['e' => $etudiant, 'm' => $mention, 'n' => $niveau, 'f' => $fin, 'l' => $lim, 'x' => mb_substr($motif, 0, 255), 'u' => $userId, 'd' => date('Y-m-d H:i:s'), 's' => $sessionId];
    $dtb->beginTransaction();
    try {
        if ($id > 0) {
            $maj = $dtb->prepare('UPDATE t_inscription_calendrier_cas SET student_id = :e, mention = :m, niveau = :n, fin_normale = :f, limite_tardive = :l,
                                         motif = :x, modifie_par = :u, modifie_le = :d WHERE id = :id AND session_id = :s');
            $maj->execute($valeurs + ['id' => $id]);
            $verif = $dtb->prepare('SELECT 1 FROM t_inscription_calendrier_cas WHERE id = :id AND session_id = :s');
            $verif->execute(['id' => $id, 's' => $sessionId]);
            if (!$verif->fetchColumn()) { throw new InvalidArgumentException('Ce calendrier particulier n’existe plus : rechargez la page.'); }
        } else {
            $dtb->prepare('INSERT INTO t_inscription_calendrier_cas (session_id, student_id, mention, niveau, fin_normale, limite_tardive, motif, modifie_par, modifie_le)
                            VALUES (:s, :e, :m, :n, :f, :l, :x, :u, :d)')->execute($valeurs);
        }
        $changes = itRecalculer($dtb, $sessionId, $userId);
        $dtb->commit();
        return $changes;
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
}

/** Retire un calendrier particulier : ses etudiants reprennent celui de la session. */
function itRetirerCas(PDO $dtb, int $sessionId, int $id, ?int $userId): int
{
    if (!itCasPresents($dtb)) { throw new InvalidArgumentException('Table absente.'); }
    $dtb->beginTransaction();
    try {
        $sup = $dtb->prepare('DELETE FROM t_inscription_calendrier_cas WHERE id = :id AND session_id = :s');
        $sup->execute(['id' => $id, 's' => $sessionId]);
        if (!$sup->rowCount()) { throw new InvalidArgumentException('Ce calendrier particulier n’existe plus : rechargez la page.'); }
        $changes = itRecalculer($dtb, $sessionId, $userId);
        $dtb->commit();
        return $changes;
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
}

/**
 * Etudiants pour un calendrier personnel : chaque mot cherche dans le nom,
 * le prenom ou le matricule. Douze au plus, les fiches actives d'abord.
 */
function itChercherEtudiants(PDO $dtb, string $q): array
{
    $mots = array_slice(preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY), 0, 4);
    if (!$mots || mb_strlen(implode('', $mots)) < 2) { return []; }
    $conditions = [];
    $params = [];
    foreach ($mots as $i => $mot) {
        // un nom de parametre par emploi : les requetes preparees natives ne les repetent pas
        $conditions[] = "(e.student_nom LIKE :n$i OR e.student_prenom LIKE :p$i OR e.student_id LIKE :i$i)";
        $motif = '%' . addcslashes($mot, '%_\\') . '%';
        $params["n$i"] = $params["p$i"] = $params["i$i"] = $motif;
    }
    $req = $dtb->prepare('SELECT e.student_id, e.student_nom, e.student_prenom, e.etude_envisage, e.annee_etude
                            FROM tbl_2024_etudiant e
                           WHERE ' . implode(' AND ', $conditions) . '
                           ORDER BY e.`remove` ASC, e.student_nom, e.student_prenom, e.id
                           LIMIT 60');
    $req->execute($params);
    $niveaux = itNiveaux();
    $vus = [];
    foreach ($req->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $id = (string) $l['student_id'];
        if ($id === '' || isset($vus[$id])) { continue; }
        $vus[$id] = [
            'id'      => $id,
            'nom'     => trim((string) $l['student_nom']),
            'prenom'  => trim((string) $l['student_prenom']),
            'mention' => trim((string) $l['etude_envisage']),
            'niveau'  => $l['annee_etude'] !== null && $l['annee_etude'] !== '' ? (string) ($niveaux[(int) $l['annee_etude']] ?? '') : '',
        ];
        if (count($vus) >= 12) { break; }
    }
    return array_values($vus);
}

/**
 * Exonere une inscription de tout ou partie de sa penalite (0 = annuler
 * l'exoneration). Le motif est obligatoire pour exonerer.
 *
 * @return array la trace apres modification
 * @throws InvalidArgumentException
 */
function itExonerer(PDO $dtb, int $traceId, $montant, string $motif, ?int $userId): array
{
    if (!itTablesPresentes($dtb)) { throw new InvalidArgumentException('Tables absentes.'); }
    $req = $dtb->prepare('SELECT * FROM t_inscription_trace WHERE id = :id');
    $req->execute(['id' => $traceId]);
    $t = $req->fetch(PDO::FETCH_ASSOC);
    if (!$t) { throw new InvalidArgumentException('Inscription introuvable.'); }
    $penalite = (int) $t['penalite'];
    if ($penalite <= 0) { throw new InvalidArgumentException('Cette inscription n’a pas de pénalité.'); }
    $x = (int) preg_replace('/\D/', '', (string) $montant);
    $motif = trim((string) preg_replace('/\s+/u', ' ', $motif));
    if ($x > $penalite) { throw new InvalidArgumentException('L’exonération ne peut pas dépasser la pénalité (' . number_format($penalite, 0, '', ' ') . ' Ar).'); }
    if ($x > 0 && mb_strlen($motif) < 5) { throw new InvalidArgumentException('Donnez le motif de l’exonération.'); }

    $dtb->beginTransaction();
    try {
        $dtb->prepare('UPDATE t_inscription_trace SET exoneration = :x, exoneration_motif = :m, exonere_par = :u, exonere_le = :d WHERE id = :id')
            ->execute(['x' => $x, 'm' => $x > 0 ? mb_substr($motif, 0, 255) : null, 'u' => $x > 0 ? $userId : null, 'd' => $x > 0 ? date('Y-m-d H:i:s') : null, 'id' => $traceId]);
        $t['exoneration'] = $x;
        itEcrirePenalite($dtb, (string) $t['student_id'], (int) $t['session_id'], $t, $userId);
        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        throw $e;
    }
    $req->execute(['id' => $traceId]);
    return $req->fetch(PDO::FETCH_ASSOC);
}

/**
 * Les inscriptions d'une session avec leur trace : date et heure, agent,
 * retard, penalite, exoneration. Une inscription sans trace (anterieure au
 * suivi) apparait avec sa date d'inscription, sans heure.
 */
function itInscriptions(PDO $dtb, int $sessionId): array
{
    $avecTrace = itTablesPresentes($dtb);
    $sql = 'SELECT ins.student_id, ins.niveau_std, ins.date_entry,
                   e.student_nom, e.student_prenom, e.etude_envisage'
         . ($avecTrace ? ', t.id AS trace_id, t.inscrit_le, t.inscrit_par, t.origine, t.jours_retard, t.hors_delai, t.penalite,
                   t.exoneration, t.exoneration_motif, t.exonere_par, t.exonere_le,
                   TRIM(CONCAT(COALESCE(ua.prenom, \'\'), \' \', COALESCE(ua.nom, \'\'))) AS agent,
                   TRIM(CONCAT(COALESCE(ux.prenom, \'\'), \' \', COALESCE(ux.nom, \'\'))) AS exonere_par_nom' : '') . '
              FROM t_2024_inscription_session ins
              JOIN tbl_2024_etudiant e ON e.id = (
                   SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = ins.student_id
                    ORDER BY e2.`remove` ASC, e2.id ASC LIMIT 1)'
         . ($avecTrace ? '
              LEFT JOIN t_inscription_trace t ON t.session_id = ins.session_id AND t.student_id = ins.student_id
              LEFT JOIN compt_utilisateur ua ON ua.id = t.inscrit_par
              LEFT JOIN compt_utilisateur ux ON ux.id = t.exonere_par' : '') . '
             WHERE ins.session_id = :s AND COALESCE(ins.`remove`, 0) != 1
             ORDER BY ' . ($avecTrace ? 'COALESCE(t.inscrit_le, ins.date_entry)' : 'ins.date_entry') . ' DESC, ins.student_id, ins.id';
    $req = $dtb->prepare($sql);
    $req->execute(['s' => $sessionId]);

    $libelles = array_column(baremeNiveaux(), 'libelle', 'niveau');
    $cas = itCas($dtb, $sessionId);
    $resultats = $req->fetchAll(PDO::FETCH_ASSOC);
    $profils = itProfils($dtb, $sessionId, array_column($resultats, 'student_id')); // calendrier particulier, nouvel etudiant
    $lignes = [];
    foreach ($resultats as $l) {
        if (isset($lignes[(string) $l['student_id']])) { continue; } // deux inscriptions a la meme session : une ligne
        $niveau = $l['niveau_std'] !== null && $l['niveau_std'] !== '' ? (int) $l['niveau_std'] : null;
        $penalite = (int) ($l['penalite'] ?? 0);
        $exoneration = (int) ($l['exoneration'] ?? 0);
        /* Son calendrier particulier, s'il en a un (meme choix que le guichet et le recalcul). */
        $profil = $profils[(string) $l['student_id']] ?? null;
        $regle = $cas ? itCasPour($cas, (string) $l['student_id'], $profil['mention'] ?? null, $profil['niveau'] ?? null) : null;
        $lignes[(string) $l['student_id']] = [
            'trace'       => isset($l['trace_id']) ? (int) $l['trace_id'] : null,
            'id'          => (string) $l['student_id'],
            'nom'         => trim((string) $l['student_nom']),
            'prenom'      => trim((string) $l['student_prenom']),
            'mention'     => trim((string) $l['etude_envisage']),
            'niveau'      => $niveau !== null ? (string) ($libelles[$niveau] ?? '') : '',
            'le'          => $l['inscrit_le'] ?? ($l['date_entry'] ? $l['date_entry'] . ' 00:00:00' : null),
            'heure'       => !empty($l['inscrit_le']) && ($l['origine'] ?? '') === 'guichet',
            'origine'     => $l['origine'] ?? 'sans-trace',
            'agent'       => trim((string) ($l['agent'] ?? '')),
            'jours'       => (int) ($l['jours_retard'] ?? 0),
            'hors_delai'  => !empty($l['hors_delai']),
            'penalite'    => $penalite,
            'exoneration' => $exoneration,
            'motif'       => (string) ($l['exoneration_motif'] ?? ''),
            'exonere_par' => trim((string) ($l['exonere_par_nom'] ?? '')),
            'exonere_le'  => $l['exonere_le'] ?? null,
            'du'          => max(0, $penalite - $exoneration),
            'cas'         => $regle ? ['id' => $regle['id'], 'libelle' => $regle['libelle'], 'personnel' => $regle['etudiant'] !== null, 'fin_normale' => $regle['fin_normale']] : null,
            'nouveau'     => !empty($profil['nouveau']), // jamais penalise
        ];
    }
    return array_values($lignes);
}

}
