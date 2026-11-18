<?php
/* =============================================================================
   Emploi du temps — socle commun

   Partage par la page (src/accueil.emploi-temps.php) et par le point d'entree
   AJAX (src/emploi-temps.api.php). Ces deux fichiers portaient chacun leur
   propre lecture des horaires et leur propre detection de conflits, avec des
   regles qui avaient fini par diverger : on pouvait creer une seance que l'on
   ne pouvait ensuite plus deplacer, parce que l'ajout ne bloquait que sur les
   conflits graves alors que le deplacement bloquait sur tout.

   Les horaires sont manipules en MINUTES depuis minuit, jamais en heures
   entieres. L'ancien code faisait « intval(substr($h, 0, 2)) » un peu partout :
   une seance de 08:30 etait traitee comme 08:00, et un deplacement recalculait
   l'heure de fin a partir de la seule heure, ce qui changeait sa duree.
   ========================================================================== */

if (!function_exists('edtJours')) {

/** Les jours ouvres, dans l'ordre de la colonne enum en base. */
function edtJours() : array
{
    return ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
}

/** '08:30' ou '08:30:00' -> 510. Renvoie null si ce n'est pas une heure. */
function edtMinutes(?string $h) : ?int
{
    if ($h === null) { return null; }
    if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($h), $m)) { return null; }
    $heures = (int) $m[1];
    $minutes = (int) $m[2];
    if ($heures > 23 || $minutes > 59) { return null; }
    return $heures * 60 + $minutes;
}

/** 510 -> '08:30'. */
function edtHeure(int $minutes) : string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

/**
 * Niveau lisible : L1 a L3, M1 et M2, RàN pour la remise a niveau. Le code
 * ecrivait « L » devant tout niveau : un Master 1 devenait « L4 ».
 */
function edtNiveauCourt($niveau) : string
{
    $n = is_numeric($niveau) ? (int) $niveau : -1;
    if ($n === 0) { return 'RàN'; }
    if ($n >= 1 && $n <= 3) { return 'L' . $n; }
    if ($n >= 4 && $n <= 5) { return 'M' . ($n - 3); }
    return (string) $niveau;
}

/* Modifier l'emploi du temps (seances et salles) : superadmin, administration
   et registrariat, niveaux 1 a 3. Les autres personnels le consultent. Releve
   en production le 28/09/2026 : toutes les seances ont ete creees par un
   superadmin, et seuls les niveaux 1 et 3 ont ouvert la page. */
if (!defined('EDT_NIVEAU_ECRITURE')) { define('EDT_NIVEAU_ECRITURE', 3); }

/* Heure de Madagascar. En production, le systeme, PHP et MySQL tournent en
   UTC (verifie le 28/09/2026 : date.timezone non renseigne) ; date() et NOW()
   y retardaient de trois heures : la colonne du jour ne changeait qu'a 3 h du
   matin, le trait de l'heure courante etait trois heures trop haut, et les
   dates d'ajout ou de modification etaient fausses d'autant. Madagascar n'a
   pas d'heure d'ete : UTC+3 toute l'annee. */
if (!defined('EDT_FUSEAU')) { define('EDT_FUSEAU', 'Indian/Antananarivo'); }

/** Maintenant, a l'heure de Madagascar, quel que soit le fuseau du serveur. */
function edtMaintenant() : DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(EDT_FUSEAU));
}

/** Horodatage a enregistrer (created_at, updated_at), a l'heure de Madagascar. */
function edtHorodatage() : string
{
    return edtMaintenant()->format('Y-m-d H:i:s');
}

/** Le compte connecte peut-il modifier l'emploi du temps ? */
function edtPeutModifier() : bool
{
    $niveau = (int) ($_SESSION['user_level'] ?? 99);
    return $niveau >= 1 && $niveau <= EDT_NIVEAU_ECRITURE;
}

/**
 * Nombre d'etudiants inscrits au cours pour l'annee (lignes de notes). Sert a
 * comparer a la capacite de la salle. Les annees s'ecrivent « 2025-2026 » ici
 * et « 2025 - 2026 » dans les notes : on compare sans les espaces.
 */
function edtEffectifCours(PDO $dtb, int $coursId, string $annee) : int
{
    static $cache = [];
    $cle = $coursId . '|' . $annee;
    if (isset($cache[$cle])) { return $cache[$cle]; }
    if ($coursId <= 0 || $annee === '') { return $cache[$cle] = 0; }
    try {
        $st = $dtb->prepare(
            "SELECT COUNT(DISTINCT n.student_id)
               FROM t_2023_notes n
               JOIN t_2023_cours c ON c.Sigle = n.Sigle
              WHERE c.id = :cours AND n.remove = 0
                AND REPLACE(n.annee_scolaire, ' ', '') = REPLACE(:annee, ' ', '')");
        $st->execute([':cours' => $coursId, ':annee' => $annee]);
        return $cache[$cle] = (int) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('Emploi du temps, effectif du cours: ' . $e->getMessage());
        return $cache[$cle] = 0;
    }
}

/* -----------------------------------------------------------------------------
   Annee scolaire

   Elle etait ecrite en dur a trois endroits, avec trois valeurs differentes :
   « 2025-2026 » a l'insertion, « 2025-2026 » dans le titre de la page, et
   « 2026-2027 » dans les reglages de la vitrine. On ne pouvait donc pas savoir
   quelle annee on regardait.

   Plutot que de trancher pour l'une d'elles — ce qui aurait fait disparaitre
   les seances des autres — la page propose desormais un choix, et ces deux
   fonctions le nourrissent.
   -------------------------------------------------------------------------- */

/** Les annees presentes en base, plus celle des reglages, la plus recente d'abord. */
function edtAnnees(PDO $dtb) : array
{
    $annees = [];
    try {
        $annees = $dtb->query(
            "SELECT DISTINCT annee_scolaire FROM t_2024_emploi_du_temps
             WHERE annee_scolaire <> '' ORDER BY annee_scolaire DESC"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        error_log('Emploi du temps, lecture des annees: ' . $e->getMessage());
    }

    if (function_exists('vitrineVal')) {
        $reglee = trim((string) vitrineVal('annee.academique', ''));
        if ($reglee !== '' && !in_array($reglee, $annees, true)) {
            $annees[] = $reglee;
        }
    }

    if (!$annees) {
        $n = edtMaintenant();
        $a = (int) $n->format('Y');
        $annees[] = ((int) $n->format('n') >= 8) ? ($a . '-' . ($a + 1)) : (($a - 1) . '-' . $a);
    }

    rsort($annees);
    return $annees;
}

/**
 * Annee ouverte par defaut.
 *
 * Celle des reglages si elle contient des seances, sinon la plus recente qui
 * en contient : la page ne doit jamais s'ouvrir sur une grille vide alors que
 * des seances existent ailleurs.
 */
function edtAnneeParDefaut(PDO $dtb) : string
{
    $annees = edtAnnees($dtb);

    $reglee = function_exists('vitrineVal') ? trim((string) vitrineVal('annee.academique', '')) : '';
    if ($reglee !== '' && edtCompteSeances($dtb, $reglee) > 0) {
        return $reglee;
    }
    foreach ($annees as $a) {
        if (edtCompteSeances($dtb, $a) > 0) { return $a; }
    }
    return $reglee !== '' ? $reglee : ($annees[0] ?? '');
}

/** Nombre de seances confirmees d'une annee. */
function edtCompteSeances(PDO $dtb, string $annee) : int
{
    static $cache = [];
    if (isset($cache[$annee])) { return $cache[$annee]; }
    try {
        $st = $dtb->prepare(
            "SELECT COUNT(*) FROM t_2024_emploi_du_temps
             WHERE annee_scolaire = ? AND statut = 'confirme'");
        $st->execute([$annee]);
        return $cache[$annee] = (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return $cache[$annee] = 0;
    }
}

/**
 * Verifie un creneau avant enregistrement.
 *
 * @return string|null Le motif du refus, ou null si le creneau est valide.
 */
function edtValiderCreneau(string $jour, ?string $debut, ?string $fin) : ?string
{
    if (!in_array($jour, edtJours(), true)) {
        return 'Jour invalide.';
    }
    $d = edtMinutes($debut);
    $f = edtMinutes($fin);
    if ($d === null || $f === null) {
        return 'Horaire invalide.';
    }
    /* Cette verification manquait : rien n'empechait d'enregistrer une seance
       de 14:00 a 10:00. Elle ne provoquait pas d'erreur visible, mais elle
       echappait a toute detection de conflit — les comparaisons de
       chevauchement supposent toutes debut < fin — et devenait donc une
       seance fantome, occupant une salle sans que rien ne le signale. */
    if ($f <= $d) {
        return "L'heure de fin doit suivre l'heure de début.";
    }
    if ($f - $d > 8 * 60) {
        return 'Une séance ne peut pas durer plus de huit heures.';
    }
    return null;
}


/* -----------------------------------------------------------------------------
   Detection des conflits

   Une seule fonction, appelee par l'ajout, la modification et le deplacement.
   Chaque conflit porte une severite :

     error    materiellement impossible — une salle ou un enseignant ne peut
              pas etre a deux endroits a la fois. Toujours bloquant.
     warning  genant mais parfois voulu — deux cours proposes a la meme
              promotion. Le registrariat peut passer outre en connaissance de
              cause (parametre « force »).

   Auparavant la severite n'existait que pour l'ajout ; le deplacement renvoyait
   une liste plate et bloquait sur son simple comptage. D'ou des seances
   creables mais impossibles a deplacer ensuite.
   -------------------------------------------------------------------------- */

/**
 * @param array    $s         Creneau examine : jour, debut, fin, salle_id,
 *                            id_teacher, mention, niveau, parcours,
 *                            type_seance, annee_scolaire.
 * @param int|null $exclureId Seance a ignorer (celle qu'on modifie ou deplace).
 */
function edtConflits(PDO $dtb, array $s, ?int $exclureId = null) : array
{
    $conflits = [];

    $jour  = (string) ($s['jour'] ?? '');
    $debut = (string) ($s['debut'] ?? '');
    $fin   = (string) ($s['fin'] ?? '');
    $annee = (string) ($s['annee_scolaire'] ?? '');
    $type  = (string) ($s['type_seance'] ?? 'cours');

    /* Toutes les requetes ci-dessous partagent le meme cadre : meme jour, meme
       annee scolaire, seances confirmees, chevauchement strict, et la seance
       examinee mise de cote. Le filtre sur l'annee manquait : les conflits
       etaient cherches dans tout l'historique de la table. */
    $cadre = "e.jour_semaine = :jour
              AND e.statut = 'confirme'
              AND e.annee_scolaire = :annee
              AND e.heure_debut < :fin AND e.heure_fin > :debut";
    $base = [':jour' => $jour, ':annee' => $annee, ':debut' => $debut, ':fin' => $fin];

    if ($exclureId !== null && $exclureId > 0) {
        $cadre .= ' AND e.id <> :exclure';
        $base[':exclure'] = $exclureId;
    }

    /* Meme semestre : un cours du semestre 1 et un cours du semestre 2 au meme
       creneau ne se croisent jamais, ils ont lieu a des mois d'ecart. Sans ce
       filtre, planifier le second semestre aurait bute sur toutes les salles et
       tous les enseignants du premier. Une seance sans semestre connu reste
       comparee a tout, par prudence. */
    $semestre = (int) ($s['semester'] ?? 0);
    if ($semestre > 0) {
        $cadre .= ' AND (e.semester = :semestre OR e.semester IS NULL OR e.semester = 0)';
        $base[':semestre'] = $semestre;
    }

    /* --- 1. La salle ------------------------------------------------------
       Deux examens peuvent partager une salle : on y surveille plusieurs
       promotions a la fois. Toute autre combinaison est un conflit.

       L'ancienne requete ecartait « e.type_seance != 'examen' » cote resultats,
       ce qui creait un angle mort : poser un COURS dans une salle deja retenue
       pour un EXAMEN ne declenchait rien du tout. Le filtre est desormais dans
       la comparaison des deux types, pas dans la requete. */
    $salleId = (int) ($s['salle_id'] ?? 0);
    if ($salleId > 0) {
        /* La salle elle-meme : indisponible (travaux, fermeture), elle ne peut
           rien accueillir ; trop petite pour les inscrits du cours, on le
           signale sans bloquer (une partie du groupe peut etre ailleurs). */
        $st = $dtb->prepare('SELECT salle_code, salle_nom, capacite, disponible FROM t_2024_salles WHERE id = ?');
        $st->execute([$salleId]);
        $salle = $st->fetch(PDO::FETCH_ASSOC);
        if ($salle) {
            $nomSalle = (string) ($salle['salle_code'] ?: $salle['salle_nom']);
            if ((int) $salle['disponible'] !== 1) {
                $conflits[] = [
                    'type' => 'salle',
                    'severity' => 'error',
                    'message' => 'Salle ' . $nomSalle . ' indisponible : elle est marquée hors service dans la liste des salles.',
                ];
            }
            $capacite = (int) $salle['capacite'];
            $effectif = edtEffectifCours($dtb, (int) ($s['cours_id'] ?? 0), $annee);
            if ($capacite > 0 && $effectif > $capacite) {
                $conflits[] = [
                    'type' => 'capacite',
                    'severity' => 'warning',
                    'message' => 'Salle ' . $nomSalle . ' : ' . $capacite . ' places pour ' . $effectif . ' inscrits au cours.',
                ];
            }
        }

        $sql = "SELECT e.cours_sigle, e.type_seance, e.heure_debut, e.heure_fin,
                       COALESCE(sa.salle_code, e.salle_code) AS salle_code
                FROM t_2024_emploi_du_temps e
                LEFT JOIN t_2024_salles sa ON e.salle_id = sa.id
                WHERE $cadre AND e.salle_id = :salle";
        $st = $dtb->prepare($sql);
        $st->execute($base + [':salle' => $salleId]);

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if ($type === 'examen' && $c['type_seance'] === 'examen') { continue; }
            $conflits[] = [
                'type' => 'salle',
                'severity' => 'error',
                'message' => 'Salle ' . ($c['salle_code'] ?: '?') . ' déjà occupée par '
                    . $c['cours_sigle'] . ' (' . substr($c['heure_debut'], 0, 5)
                    . ' – ' . substr($c['heure_fin'], 0, 5) . ')',
            ];
        }
    }

    /* --- 2. L'enseignant -------------------------------------------------- */
    $prof = (int) ($s['id_teacher'] ?? 0);
    if ($prof > 0) {
        $sql = "SELECT e.cours_sigle, e.heure_debut, e.heure_fin, t.name, t.lastName
                FROM t_2024_emploi_du_temps e
                LEFT JOIN teacher t ON e.id_teacher = t.uid
                WHERE $cadre AND e.id_teacher = :prof";
        $st = $dtb->prepare($sql);
        $st->execute($base + [':prof' => $prof]);

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $nom = trim(($c['name'] ?? '') . ' ' . ($c['lastName'] ?? ''));
            $conflits[] = [
                'type' => 'enseignant',
                'severity' => 'error',
                'message' => ($nom !== '' ? $nom : 'Cet enseignant') . ' assure déjà '
                    . $c['cours_sigle'] . ' (' . substr($c['heure_debut'], 0, 5)
                    . ' – ' . substr($c['heure_fin'], 0, 5) . ')',
            ];
        }
    }

    /* --- 3. Les etudiants -------------------------------------------------
       Meme mention, meme niveau, et parcours compatibles :
         - une seance ouverte a TOUS les parcours entre en conflit avec tout ;
         - une seance d'un parcours precis n'entre en conflit qu'avec le meme
           parcours, ou avec une seance ouverte a tous.
       Deux parcours differents et precis ne se genent pas : leurs etudiants ne
       sont pas les memes. */
    $mention = (string) ($s['mention'] ?? '');
    $niveau  = (string) ($s['niveau'] ?? '');
    if ($mention !== '' && $niveau !== '') {
        $parcours = trim((string) ($s['parcours'] ?? ''));
        $ouvertATous = ($parcours === '' || strtolower($parcours) === 'all');

        $sql = "SELECT e.cours_sigle, e.parcours, e.niveau, e.mention,
                       e.heure_debut, e.heure_fin
                FROM t_2024_emploi_du_temps e
                WHERE $cadre AND e.mention = :mention AND e.niveau = :niveau";
        $p = $base + [':mention' => $mention, ':niveau' => $niveau];

        if (!$ouvertATous) {
            $sql .= " AND (e.parcours = :parcours OR e.parcours = '' OR e.parcours = 'all'
                           OR e.parcours IS NULL)";
            $p[':parcours'] = $parcours;
        }

        $st = $dtb->prepare($sql);
        $st->execute($p);

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $precision = '';
            if (!empty($c['parcours']) && strtolower($c['parcours']) !== 'all') {
                $precision = ' (' . $c['parcours'] . ')';
            }
            $conflits[] = [
                'type' => 'etudiants',
                'severity' => 'warning',
                'message' => 'Les ' . edtNiveauCourt($c['niveau']) . ' ' . $c['mention'] . $precision
                    . ' ont déjà ' . $c['cours_sigle'] . ' (' . substr($c['heure_debut'], 0, 5)
                    . ' – ' . substr($c['heure_fin'], 0, 5) . ')',
            ];
        }
    }

    return $conflits;
}

/**
 * Conflits DEJA presents entre des seances enregistrees : memes regles que
 * edtConflits (meme jour, meme semestre ou semestre inconnu, chevauchement
 * strict ; salle et enseignant bloquants, promotion en avertissement), mais
 * calculees sur un tableau deja lu, sans une requete par seance. La page s'en
 * sert pour signaler ce que l'on a enregistre avant que les regles ne soient
 * completes (deux seances dans la meme salle au meme moment, par exemple).
 *
 * @return array<int, array> id de seance => liste de conflits (severity, message)
 */
function edtConflitsEntre(array $seances) : array
{
    $parJour = [];
    foreach ($seances as $s) {
        $d = edtMinutes($s['heure_debut'] ?? null);
        $f = edtMinutes($s['heure_fin'] ?? null);
        if ($d === null || $f === null || $f <= $d) { continue; }
        $s['_d'] = $d; $s['_f'] = $f;
        $parJour[(string) $s['jour_semaine']][] = $s;
    }

    $ouvert = static fn ($p) => trim((string) $p) === '' || strtolower(trim((string) $p)) === 'all';
    $h = static fn (array $s) => substr((string) $s['heure_debut'], 0, 5) . ' – ' . substr((string) $s['heure_fin'], 0, 5);
    $resultat = [];

    foreach ($parJour as $liste) {
        $n = count($liste);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $liste[$i]; $b = $liste[$j];
                if (!($a['_d'] < $b['_f'] && $b['_d'] < $a['_f'])) { continue; }
                $sa = (int) ($a['semester'] ?? 0); $sb = (int) ($b['semester'] ?? 0);
                if ($sa > 0 && $sb > 0 && $sa !== $sb) { continue; }

                $trouves = [];
                if ((int) $a['salle_id'] > 0 && (int) $a['salle_id'] === (int) $b['salle_id']
                    && !($a['type_seance'] === 'examen' && $b['type_seance'] === 'examen')) {
                    $salle = (string) (($a['salle_code'] ?? '') ?: ($a['salle_nom'] ?? '') ?: '?');
                    $trouves[] = ['error', 'Salle ' . $salle . ' partagée'];
                }
                if ((int) $a['id_teacher'] > 0 && (int) $a['id_teacher'] === (int) $b['id_teacher']) {
                    $trouves[] = ['error', 'Même enseignant'];
                }
                if ((string) $a['mention'] !== '' && $a['mention'] === $b['mention']
                    && (string) $a['niveau'] === (string) $b['niveau']
                    && ($ouvert($a['parcours'] ?? '') || $ouvert($b['parcours'] ?? '')
                        || trim((string) $a['parcours']) === trim((string) $b['parcours']))) {
                    $trouves[] = ['warning', 'Même promotion (' . edtNiveauCourt($a['niveau']) . ' ' . $a['mention'] . ')'];
                }
                foreach ($trouves as [$severite, $motif]) {
                    $resultat[(int) $a['id']][] = ['severity' => $severite,
                        'message' => $motif . ' avec ' . $b['cours_sigle'] . ' (' . $h($b) . ')'];
                    $resultat[(int) $b['id']][] = ['severity' => $severite,
                        'message' => $motif . ' avec ' . $a['cours_sigle'] . ' (' . $h($a) . ')'];
                }
            }
        }
    }
    return $resultat;
}

/** Vrai si la liste contient au moins un conflit bloquant. */
function edtBloquant(array $conflits) : bool
{
    foreach ($conflits as $c) {
        if (($c['severity'] ?? '') === 'error') { return true; }
    }
    return false;
}

/**
 * Colonnes de t_2024_salles, lues dans la base : type, longueur maximale,
 * valeurs permises d'une ENUM, colonne nullable. L'enregistrement d'une salle
 * valide ainsi contre la table reelle, et une colonne absente d'une
 * installation n'est simplement pas ecrite.
 *
 * @return array<string, array{type: string, max: ?int, enum: ?array, null: bool}>
 */
function edtColonnesSalles(PDO $dtb) : array
{
    $st = $dtb->query(
        "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't_2024_salles'");
    $colonnes = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $c = array_change_key_case($c, CASE_UPPER);
        $enum = null;
        if (strtolower((string) $c['DATA_TYPE']) === 'enum'
            && preg_match_all("/'((?:[^']|'')*)'/", (string) $c['COLUMN_TYPE'], $m)) {
            $enum = array_map(fn ($v) => str_replace("''", "'", $v), $m[1]);
        }
        $colonnes[(string) $c['COLUMN_NAME']] = [
            'type' => strtolower((string) $c['DATA_TYPE']),
            'max'  => $c['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int) $c['CHARACTER_MAXIMUM_LENGTH'] : null,
            'enum' => $enum,
            'null' => strtoupper((string) $c['IS_NULLABLE']) === 'YES',
        ];
    }
    return $colonnes;
}

/**
 * Types de salle a proposer : les valeurs de l'ENUM si la colonne en est une,
 * sinon celles deja employees.
 */
function edtTypesSalle(PDO $dtb, array $colonnes) : array
{
    if (!empty($colonnes['type_salle']['enum'])) { return $colonnes['type_salle']['enum']; }
    if (!isset($colonnes['type_salle'])) { return []; }
    return $dtb->query("SELECT DISTINCT type_salle FROM t_2024_salles
                        WHERE type_salle IS NOT NULL AND type_salle <> '' ORDER BY type_salle")->fetchAll(PDO::FETCH_COLUMN);
}


/* -----------------------------------------------------------------------------
   Disposition d'une journee

   L'ancienne grille etait un tableau HTML a rowspan. Le rowspan d'une cellule
   valait la duree LA PLUS LONGUE parmi les seances commencant a cette heure, et
   les lignes couvertes n'emettaient plus de cellule du tout. Consequence
   verifiee sur un cas melange : avec un TP de 3 h et un TD de 1 h a 8 h, les
   seances de 9 h et de 10 h disparaissaient purement et simplement de la
   grille — elles n'apparaissaient nulle part, sans le moindre avertissement.

   On remplace par un placement au pixel : chaque seance est positionnee selon
   son debut et sa duree reels. Les seances qui se chevauchent sont reparties en
   colonnes, comme dans un agenda. Rien ne disparait, et les minutes comptent.
   -------------------------------------------------------------------------- */

/**
 * Repartit les seances d'une journee en colonnes de chevauchement.
 *
 * Ajoute a chaque seance : debut_min, fin_min, colonne, nb_colonnes.
 */
function edtDisposerJournee(array $seances) : array
{
    foreach ($seances as $i => $s) {
        $seances[$i]['debut_min'] = edtMinutes($s['heure_debut']) ?? 0;
        $seances[$i]['fin_min']   = edtMinutes($s['heure_fin']) ?? 0;
    }

    usort($seances, static function ($a, $b) {
        return ($a['debut_min'] <=> $b['debut_min'])
            ?: ($b['fin_min'] <=> $a['fin_min']);
    });

    /* On traite les seances par grappes : une grappe est un ensemble relie de
       proche en proche par un chevauchement. Toutes les seances d'une meme
       grappe partagent le meme nombre de colonnes, sinon leurs largeurs ne
       s'aligneraient pas d'une seance a l'autre. */
    $resultat = [];

    $ecouler = static function (array $grappe) use (&$resultat) {
        if (!$grappe) { return; }

        $colonnes = [];               // colonne => minute de fin de la derniere posee
        foreach ($grappe as $i => $s) {
            $place = null;
            foreach ($colonnes as $c => $finColonne) {
                if ($s['debut_min'] >= $finColonne) { $place = $c; break; }
            }
            if ($place === null) { $place = count($colonnes); }
            $colonnes[$place] = $s['fin_min'];
            $grappe[$i]['colonne'] = $place;
        }

        $nb = count($colonnes);
        foreach ($grappe as $s) {
            $s['nb_colonnes'] = $nb;
            $resultat[] = $s;
        }
    };

    $grappe = [];
    $finGrappe = -1;
    foreach ($seances as $s) {
        if ($grappe && $s['debut_min'] >= $finGrappe) {
            $ecouler($grappe);
            $grappe = [];
            $finGrappe = -1;
        }
        $grappe[] = $s;
        $finGrappe = max($finGrappe, $s['fin_min']);
    }
    $ecouler($grappe);

    return $resultat;
}

/**
 * Bornes horaires a afficher, deduites des seances puis elargies aux heures
 * pleines. Une plage 08–17 etait ecrite en dur : une seance a 07:30 ou a 18:00
 * existait en base sans jamais apparaitre a l'ecran.
 */
function edtPlage(array $seances, int $defautDebut = 480, int $defautFin = 1020) : array
{
    $min = $defautDebut;
    $max = $defautFin;
    foreach ($seances as $s) {
        $d = edtMinutes($s['heure_debut']);
        $f = edtMinutes($s['heure_fin']);
        if ($d !== null) { $min = min($min, $d); }
        if ($f !== null) { $max = max($max, $f); }
    }
    return [intdiv($min, 60) * 60, (int) (ceil($max / 60) * 60)];
}

}
