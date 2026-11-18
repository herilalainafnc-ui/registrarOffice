<?php
/* =============================================================================
   Cours offerts par session

   La table `t_2023_course_sessions` dit, pour une annee scolaire et une
   session donnees, quels cours sont REELLEMENT proposes. C'est ce que la page
   « Cours par session » alimente.

   ---------------------------------------------------------------------------
   LE DEFAUT QUE CE FICHIER CORRIGE

   Les deux lecteurs — app/.student/get-cours-list.php et
   src/student/new.cours.php — ajoutaient a leur requete :

       AND c.semester = :sem

   c'est-a-dire le semestre NATIF du cours dans le programme, en plus de la
   session offerte. Les deux conditions se contredisent des qu'elles diffèrent,
   et c'est precisement le cas que la page « Cours par session » existe pour
   traiter : un cours de premier semestre exceptionnellement offert au second.

   Constate sur DRTJ 211 (« Vie de Jésus », semestre natif 1) coche pour le
   deuxieme semestre 2025-2026 : la ligne offered = 1 etait bien en base, et le
   cours n'apparaissait dans aucun des deux semestres du formulaire
   d'inscription. Le premier le rejetait parce qu'il n'est pas offert, le second
   parce que son semestre natif vaut 1.

   La session offerte fait donc seule autorite ici. Le semestre natif reste ce
   qu'il est dans le programme — il sert au cursus, pas a l'inscription.
   ---------------------------------------------------------------------------
   ========================================================================== */

if (!function_exists('coursSessionLabel')) {

/**
 * Libelle de session correspondant a un numero de semestre.
 *
 * Ces libelles sont des chaines en base, pas des codes : les ecrire a la main
 * dans chaque lecteur, c'est attendre qu'une apostrophe ou un accent diverge.
 */
function coursSessionLabel(int $semestre) : string
{
    return $semestre === 1 ? 'Premier semestre' : 'Deuxième semestre';
}

/** Les quatre sessions, dans l'ordre de l'annee. */
function coursSessions() : array
{
    return ['Premier semestre', "Semestre d'été", 'Deuxième semestre', "Semestre d'hiver"];
}

/**
 * Annee scolaire deduite de la date.
 *
 * Bascule en septembre. C'est la regle qu'appliquaient deja les deux pages —
 * on la garde, mais en un seul endroit.
 */
function coursAnneeParDate() : string
{
    $z = (int) date('n') < 9 ? (int) date('Y') : (int) date('Y') + 1;
    return ($z - 1) . ' - ' . $z;
}

/**
 * Annee scolaire a utiliser.
 *
 * @param string|null $demandee Valeur venue de la requete, si l'utilisateur en
 *                              a choisi une.
 *
 * On ne se contente pas de la date. Le 10 septembre 2026, la regle ci-dessus
 * donne « 2026 - 2027 » — une annee pour laquelle AUCUNE ligne n'existait
 * encore, alors que « 2025 - 2026 » en comptait 663 par session. Le formulaire
 * d'inscription affichait donc une liste vide, sans rien expliquer, et le
 * registrariat cochait des cases dans une annee que le formulaire ne regardait
 * pas.
 *
 * On retombe donc sur la derniere annee REELLEMENT renseignee. Une annee vide
 * n'est pas une annee ouverte : c'est une annee qu'on n'a pas encore preparee.
 */
function coursAnneeScolaire(PDO $dtb, ?string $demandee = null) : string
{
    $demandee = trim((string) $demandee);
    if ($demandee !== '') { return $demandee; }

    $parDate = coursAnneeParDate();

    try {
        // L'annee de la date convient si elle porte au moins un cours offert.
        $st = $dtb->prepare(
            'SELECT COUNT(*) FROM t_2023_course_sessions
              WHERE academic_year = ? AND offered = 1');
        $st->execute([$parDate]);
        if ((int) $st->fetchColumn() > 0) { return $parDate; }

        $derniere = $dtb->query(
            'SELECT academic_year FROM t_2023_course_sessions
              WHERE offered = 1
              ORDER BY academic_year DESC LIMIT 1')->fetchColumn();
        if ($derniere !== false && $derniere !== null) { return (string) $derniere; }
    } catch (Throwable $e) {
        error_log('Cours offerts, choix de l\'annee : ' . $e->getMessage());
    }

    return $parDate;
}

/**
 * Les cours offerts pour une promotion, une session et une annee.
 *
 * @param array $c dep_desc, yearlevel, parcours (code court), session_label,
 *                 academic_year.
 * @return array Lignes de t_2023_cours.
 */
function coursOfferts(PDO $dtb, array $c) : array
{
    /* Pas de « AND c.semester = ... » : voir l'en-tete de ce fichier. La
       session offerte fait autorite. */
    $st = $dtb->prepare(
        'SELECT c.* FROM t_2023_cours c
         INNER JOIN t_2023_course_sessions cs ON cs.course_id = c.id
         WHERE c.dep_desc = :dep
           AND c.yearlevel = :level
           AND (c.parcours = :parcours OR c.parcours = :tout)
           AND cs.session_label = :session_label
           AND cs.academic_year = :academic_year
           AND cs.offered = 1
           AND c.remove != 1
         ORDER BY c.title');

    $st->execute([
        'dep' => $c['dep_desc'] ?? '',
        'level' => $c['yearlevel'] ?? 0,
        'parcours' => $c['parcours'] ?? '',
        'tout' => 'all',
        'session_label' => $c['session_label'] ?? '',
        'academic_year' => $c['academic_year'] ?? '',
    ]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------------------------------------------------------
   Annees passees (volet « Ajout de cours » d'une fiche etudiant)

   Le registrariat complete parfois le cursus d'une annee deja close : un
   cours suivi mais jamais saisi. Le volet n'affichait que l'annee en cours.
   On choisit maintenant l'annee, et une seule a la fois : ses cours
   s'inscrivent dans ses sessions, jamais melanges a ceux d'une autre.

   La liste « cours offerts » (page « Cours par session ») n'existe que depuis
   2025-2026. Pour une session qui n'en a pas, on montre le programme du
   niveau, en le disant.
   -------------------------------------------------------------------------- */

/** « 2025 - 2026 » bien forme, sinon null. */
function coursAnneeValide(?string $annee) : ?string
{
    $annee = trim((string) $annee);
    if (!preg_match('~^(\d{4}) - (\d{4})$~', $annee, $m) || (int) $m[2] !== (int) $m[1] + 1) {
        return null;
    }
    return $annee;
}

/** L'annee est-elle anterieure a l'annee scolaire en cours (bascule en septembre) ? */
function coursAnneePassee(string $annee) : bool
{
    return (int) substr($annee, 0, 4) < (int) substr(coursAnneeParDate(), 0, 4);
}

/**
 * Les annees scolaires proposees, LES MEMES sur les deux pages qui en
 * offrent le choix : « Cours par session » (src/manage-courses-sessions.php)
 * et le volet « Ajout de cours » d'une fiche (src/student/new.cours.php).
 * Elles divergeaient : l'une ajoutait les annees voisines de la date, l'autre
 * suivait le calendrier ; on ne retrouvait pas d'une page a l'autre l'annee
 * qu'on venait de preparer.
 *
 * Regle : les annees du calendrier officiel (t_2023_session, page
 * « Sessions »), les $nombre plus recentes, plus toute annee ou des cours
 * offerts ont deja ete coches (rien de renseigne ne disparait), plus
 * $courante. Une annee sans sessions n'est pas proposee : un cours ajoute a
 * une fiche n'y trouverait pas de session. L'annee suivante apparait des
 * que ses sessions sont creees.
 */
function coursAnneesChoix(PDO $dtb, string $courante, int $nombre = 6) : array
{
    $annees = [$courante];
    try {
        $calendrier = array_filter(array_map('coursAnneeValide',
            $dtb->query('SELECT DISTINCT session_year FROM t_2023_session')->fetchAll(PDO::FETCH_COLUMN)));
        rsort($calendrier);
        $renseignees = array_filter(array_map('coursAnneeValide',
            $dtb->query('SELECT DISTINCT academic_year FROM t_2023_course_sessions')->fetchAll(PDO::FETCH_COLUMN)));
        $annees = array_merge($annees, array_slice($calendrier, 0, $nombre), $renseignees);
    } catch (Throwable $e) {
        error_log('Cours offerts, annees : ' . $e->getMessage());
    }
    $annees = array_values(array_unique($annees));
    rsort($annees);
    return $annees;
}

/** La session de cette annee a-t-elle une liste de cours offerts ? */
function coursSessionAOffres(PDO $dtb, string $annee, string $sessionLabel) : bool
{
    static $cache = [];
    $cle = $annee . '|' . $sessionLabel;
    if (!isset($cache[$cle])) {
        $st = $dtb->prepare('SELECT COUNT(*) FROM t_2023_course_sessions WHERE academic_year = ? AND session_label = ? AND offered = 1');
        $st->execute([$annee, $sessionLabel]);
        $cache[$cle] = (int) $st->fetchColumn() > 0;
    }
    return $cache[$cle];
}

/**
 * Le programme du niveau pour un semestre (semestre NATIF du cours), sans
 * regarder ce qui a ete offert. Pour les annees d'avant « Cours par session ».
 */
function coursProgramme(PDO $dtb, array $c) : array
{
    $st = $dtb->prepare(
        'SELECT c.* FROM t_2023_cours c
         WHERE c.dep_desc = :dep
           AND c.yearlevel = :level
           AND (c.parcours = :parcours OR c.parcours = :tout)
           AND c.semester = :semestre
           AND c.remove != 1
         ORDER BY c.title');
    $st->execute([
        'dep' => $c['dep_desc'] ?? '',
        'level' => $c['yearlevel'] ?? 0,
        'parcours' => $c['parcours'] ?? '',
        'tout' => 'all',
        'semestre' => $c['semestre'] ?? 0,
    ]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

}
