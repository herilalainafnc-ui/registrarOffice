<?php
/* =============================================================================
   Remise des notes — regles communes

   Une remise est la feuille de notes d'un cours pour une annee et un semestre.
   L'enseignant la remplit, la soumet ; le registrariat la controle et la valide.
   La validation est le SEUL chemin qui ecrit dans t_2023_notes : avant elle,
   rien n'atteint l'historique des notes ni le bulletin de l'etudiant.

   Ce fichier rassemble ce que la page enseignant, la page registrariat et les
   points d'entree AJAX doivent partager : le calcul de la note, la creation des
   lignes, le journal, et les transitions d'etat. Les repartir aurait laisse
   trois versions de la meme regle de calcul.

   Cycle de vie :

       brouillon  --soumettre-->  soumise  --valider-->  validee
            ^                        |
            +-------- rejeter -------+   (avec motif, l'enseignant reprend)

   Usage :
       require_once __DIR__ . '/remise.notes.php';
   ========================================================================== */

require_once __DIR__ . '/notes.statut.php';

if (!defined('REMISE_NOTES_LOADED')) {
define('REMISE_NOTES_LOADED', true);

/**
 * Semestre effectif d'une note, en SQL.
 *
 * Deux sources coexistent en base : le semestre de la session d'inscription et
 * celui porte par la note. La session fait foi quand elle en donne un ;
 * session_semester = 0 veut dire « non renseigne ». Suppose les alias n
 * (t_2023_notes) et s (t_2023_session) dans la requete.
 */
define('REMISE_SQL_SEMESTRE', 'COALESCE(NULLIF(s.session_semester, 0), n.semester)');

/* -----------------------------------------------------------------------------
   Composantes de la note
   -------------------------------------------------------------------------- */

/**
 * Les cinq composantes, dans l'ordre de la feuille de remise imprimee
 * (src/extenssionPrint/remiseNotes.php) : l'enseignant retrouve la meme
 * sequence a l'ecran et sur le papier.
 *
 * @return array<string, array{libelle:string, poids:string, court:string}>
 */
function remiseComposantes(): array
{
    return [
        'quiz'         => ['libelle' => 'Quiz',            'poids' => 'poids_quiz',        'court' => 'Quiz'],
        'recherche'    => ['libelle' => 'Recherche',       'poids' => 'poids_recherche',   'court' => 'Rech.'],
        'autres'       => ['libelle' => 'Autres',          'poids' => 'poids_autres',      'court' => 'Autres'],
        'mi_semestre'  => ['libelle' => 'Examen mi-sem.',  'poids' => 'poids_mi_semestre', 'court' => 'Mi-sem'],
        'examen_final' => ['libelle' => 'Examen final',    'poids' => 'poids_final',       'court' => 'Final'],
    ];
}

/* -----------------------------------------------------------------------------
   Colonnes ajoutees par l'enseignant

   Les cinq composantes ci-dessus restent dans t_remise_notes / t_remise_notes_ligne.
   Au-dela, l'enseignant ajoute autant de colonnes qu'il veut (« TP 1 », « Presentation »...) :
   chacune a un nom, une ponderation (t_remise_notes_colonne) et une note par etudiant
   (t_remise_notes_valeur). Une colonne est designee par sa cle (« x:ab12cd34 »), choisie par
   le navigateur : elle existe donc avant que le serveur ne la connaisse, ce qui permet de
   la creer et de la remplir sans reseau (src/js/hors-ligne.js).

   remiseCharger() joint la liste a la remise ($remise['extras']), remiseLignes() les notes
   a chaque ligne ($ligne['extras'][cle]) : le calcul, le controle, la validation et la vue du
   registrariat les prennent en compte sans rien savoir de leur stockage.
   -------------------------------------------------------------------------- */

function remiseColonnesInstaller(PDO $dtb): void
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;
    $dtb->exec('CREATE TABLE IF NOT EXISTS t_remise_notes_colonne (
        id INT NOT NULL AUTO_INCREMENT,
        remise_id INT NOT NULL,
        cle VARCHAR(16) NOT NULL,
        rang INT NOT NULL DEFAULT 0,
        libelle VARCHAR(60) NOT NULL,
        poids DECIMAL(5,2) NOT NULL DEFAULT 0,
        cree_le DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_remise_cle (remise_id, cle)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $dtb->exec('CREATE TABLE IF NOT EXISTS t_remise_notes_valeur (
        id INT NOT NULL AUTO_INCREMENT,
        ligne_id INT NOT NULL,
        colonne_id INT NOT NULL,
        valeur DECIMAL(5,2) NULL,
        modifie_le DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ligne_colonne (ligne_id, colonne_id),
        KEY idx_colonne (colonne_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/** Une cle de colonne ajoutee : « x: » puis 4 a 12 lettres minuscules ou chiffres. */
function remiseCleExtraValide(string $cle): bool
{
    return (bool) preg_match('/^[a-z0-9]{4,12}$/', $cle);
}

/** Les colonnes ajoutees d'une remise, dans l'ordre de la feuille. */
function remiseExtras(PDO $dtb, int $remiseId): array
{
    remiseColonnesInstaller($dtb);
    $stmt = $dtb->prepare('SELECT id, cle, rang, libelle, poids FROM t_remise_notes_colonne
        WHERE remise_id = :r ORDER BY rang ASC, id ASC');
    $stmt->execute(['r' => $remiseId]);
    $extras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($extras as &$x) {
        $x['id']    = (int) $x['id'];
        $x['poids'] = (float) $x['poids'];
    }
    return $extras;
}

/** Une colonne ajoutee, par sa cle. */
function remiseExtraTrouver(PDO $dtb, int $remiseId, string $cle): ?array
{
    remiseColonnesInstaller($dtb);
    $stmt = $dtb->prepare('SELECT id, cle, rang, libelle, poids FROM t_remise_notes_colonne WHERE remise_id = :r AND cle = :c LIMIT 1');
    $stmt->execute(['r' => $remiseId, 'c' => $cle]);
    $x = $stmt->fetch(PDO::FETCH_ASSOC);
    return $x ?: null;
}

/** Somme des ponderations (cinq composantes et colonnes ajoutees), qui doit valoir 100 pour qu'une remise parte au controle. */
function remiseTotalPoids(array $remise): float
{
    $total = 0.0;
    foreach (remiseComposantes() as $meta) {
        $total += (float) ($remise[$meta['poids']] ?? 0);
    }
    foreach ($remise['extras'] ?? [] as $x) {
        $total += (float) $x['poids'];
    }
    return round($total, 2);
}

/**
 * Note finale d'une ligne, telle qu'elle sera proposee a la validation.
 *
 * Deux cas, dans cet ordre :
 *   1. statut_special (V / E / NN) : le cours ou l'etudiant sort du bareme.
 *   2. moyenne ponderee des composantes, selon la ponderation du cours.
 *
 * La note sur 20 ne se saisit pas : elle est toujours calculee. L'enseignant
 * pouvait autrefois l'imposer a la main (note_forcee), ce qui laissait passer
 * une note sans rapport avec les composantes saisies ; les colonnes
 * note_forcee et note_finale restent en base mais ne sont plus lues
 * (data/migration_note_calculee.sql).
 *
 * Une composante ponderee mais NON saisie rend la ligne incomplete : la note
 * reste nulle et l'etat vaut 'incomplete'. On ne calcule PAS au prorata des
 * seules composantes remplies — cela donnerait une note d'apparence normale a
 * une evaluation a moitie faite, et ni l'enseignant ni le registraire ne le
 * verraient.
 *
 * @return array{valeur:?float, etat:string, detail:string}
 *   etat : 'calculee' | 'special' | 'incomplete' | 'vide'
 */
function remiseCalculerNote(array $ligne, array $remise): array
{
    $special = trim((string) ($ligne['statut_special'] ?? ''));
    if ($special !== '') {
        return ['valeur' => null, 'etat' => 'special', 'detail' => strtoupper($special)];
    }

    $somme       = 0.0;
    $poidsUtiles = 0.0;
    $manquantes  = [];
    $saisies     = 0;

    /* Les composantes de base, puis les colonnes ajoutees : (libelle, poids, valeur). */
    $colonnes = [];
    foreach (remiseComposantes() as $champ => $meta) {
        $colonnes[] = [$meta['libelle'], (float) ($remise[$meta['poids']] ?? 0), $ligne[$champ] ?? null];
    }
    foreach ($remise['extras'] ?? [] as $x) {
        $colonnes[] = [$x['libelle'], (float) $x['poids'], $ligne['extras'][$x['cle']] ?? null];
    }

    foreach ($colonnes as [$libelle, $poids, $valeur]) {
        if ($poids <= 0) {
            continue; // colonne non utilisee dans ce cours
        }
        $poidsUtiles += $poids;

        if ($valeur === null || $valeur === '') {
            $manquantes[] = $libelle;
            continue;
        }
        $saisies++;
        $somme += ((float) $valeur) * $poids;
    }

    if ($poidsUtiles <= 0) {
        return ['valeur' => null, 'etat' => 'vide', 'detail' => 'Aucune ponderation definie'];
    }
    if ($saisies === 0) {
        return ['valeur' => null, 'etat' => 'vide', 'detail' => 'Aucune note saisie'];
    }
    if ($manquantes) {
        return [
            'valeur' => null,
            'etat'   => 'incomplete',
            'detail' => 'Manque : ' . implode(', ', $manquantes),
        ];
    }

    return [
        'valeur' => round($somme / $poidsUtiles, 2),
        'etat'   => 'calculee',
        'detail' => 'Moyenne ponderee des composantes',
    ];
}

/**
 * Traduit une note de remise vers la valeur stockee dans t_2023_notes.grade,
 * qui est un VARCHAR melant notes chiffrees et codes (voir notes.statut.php) :
 * -2 = valide, -3 = echec, -4 = pas encore note.
 *
 * @return string|null null si la ligne n'est pas transferable en l'etat.
 */
function remiseNoteVersGrade(array $ligne, array $remise, bool $coursValidation = false): ?string
{
    $calcul = remiseCalculerNote($ligne, $remise);

    if ($calcul['etat'] === 'special') {
        switch ($calcul['detail']) {
            case 'V':  return '-2';
            case 'E':  return '-3';
            case 'NN': return '-4';
            default:   return null;
        }
    }

    if ($calcul['valeur'] === null) {
        return null;
    }

    /* Un cours de validation ne s'exprime jamais en chiffres : la note sert
       seulement a decider entre valide et echec. */
    if ($coursValidation) {
        return $calcul['valeur'] >= 10 ? '-2' : '-3';
    }

    return (string) $calcul['valeur'];
}

/* -----------------------------------------------------------------------------
   Etats
   -------------------------------------------------------------------------- */

/** @return array{libelle:string, couleur:string, icone:string} */
function remiseStatutInfo(string $statut): array
{
    switch ($statut) {
        case 'soumise':
            return ['libelle' => 'En attente de validation', 'couleur' => '#f59e0b', 'icone' => 'bi-hourglass-split'];
        case 'validee':
            return ['libelle' => 'Validee',                  'couleur' => '#22c55e', 'icone' => 'bi-check-circle-fill'];
        case 'rejetee':
            return ['libelle' => 'Renvoyee pour correction', 'couleur' => '#ef4444', 'icone' => 'bi-arrow-counterclockwise'];
        default:
            return ['libelle' => 'Brouillon',                'couleur' => '#64748b', 'icone' => 'bi-pencil-square'];
    }
}

/** L'enseignant peut-il encore ecrire ? Une remise soumise ou validee est figee. */
function remisePeutSaisir(array $remise): bool
{
    return in_array($remise['statut'] ?? 'brouillon', ['brouillon', 'rejetee'], true);
}

/* -----------------------------------------------------------------------------
   Acces
   -------------------------------------------------------------------------- */

/**
 * L'enseignant connecte enseigne-t-il ce cours ?
 *
 * Seul garde-fou entre un enseignant et la feuille de notes d'un collegue : les
 * identifiants de cours sont sequentiels et se devinent a la main dans l'URL.
 */
function remiseEnseignantPossedeCours(PDO $dtb, int $coursId): bool
{
    if (function_exists('isAdmin') && (isAdmin() || isRegistrar())) {
        return true;
    }
    if (!function_exists('getTeacherUid')) {
        return false;
    }
    $uid = getTeacherUid();
    if (!$uid) {
        return false;
    }
    $stmt = $dtb->prepare('SELECT 1 FROM t_2023_cours WHERE id = :id AND id_teacher = :uid AND remove != 1 LIMIT 1');
    $stmt->execute(['id' => $coursId, 'uid' => $uid]);
    return (bool) $stmt->fetchColumn();
}

/** Le registrariat et le superadmin valident ; personne d'autre. */
function remisePeutValider(): bool
{
    if (function_exists('hasAnyPrivilege') && hasAnyPrivilege(['superadmin', 'registrar', 'administrator'])) {
        return true;
    }
    return (int) ($_SESSION['user_level'] ?? 99) <= 3;
}

/* -----------------------------------------------------------------------------
   Journal
   -------------------------------------------------------------------------- */

/**
 * Consigne un changement. Appele a chaque ecriture, y compris les saisies de
 * cellule : c'est la trace qui permet de dire qui a pose quelle note et quand.
 *
 * Les valeurs identiques ne sont pas journalisees — sans ce filtre, un
 * enregistrement automatique remplirait le journal de lignes sans changement.
 *
 * $quand : l'instant du changement quand il a ete fait hors ligne et arrive plus
 * tard (app/.remise/enregistrer.php, client_le) ; sinon maintenant.
 */
function remiseJournaliser(
    PDO $dtb,
    int $remiseId,
    string $action,
    ?string $champ = null,
    $ancienne = null,
    $nouvelle = null,
    ?int $ligneId = null,
    ?string $studentId = null,
    ?string $quand = null
): void {
    $ancienneTxt = ($ancienne === null || $ancienne === '') ? null : (string) $ancienne;
    $nouvelleTxt = ($nouvelle === null || $nouvelle === '') ? null : (string) $nouvelle;

    if ($action === 'saisie' && $ancienneTxt === $nouvelleTxt) {
        return;
    }

    $user = function_exists('currentUser') ? currentUser() : null;
    $nom  = $user ? trim(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? '')) : null;
    if ($nom === '') { $nom = $user['pseudo'] ?? null; }

    $stmt = $dtb->prepare('INSERT INTO t_remise_notes_journal
        (remise_id, ligne_id, student_id, action, champ, ancienne_valeur, nouvelle_valeur,
         auteur_id, auteur_nom, auteur_role, date_action, ip)
        VALUES (:remise_id, :ligne_id, :student_id, :action, :champ, :ancienne, :nouvelle,
                :auteur_id, :auteur_nom, :auteur_role, :date_action, :ip)');

    $stmt->execute([
        'remise_id'   => $remiseId,
        'ligne_id'    => $ligneId,
        'student_id'  => $studentId,
        'action'      => $action,
        'champ'       => $champ,
        'ancienne'    => $ancienneTxt,
        'nouvelle'    => $nouvelleTxt,
        'auteur_id'   => $user['id'] ?? ($_SESSION['user_id'] ?? null),
        'auteur_nom'  => $nom,
        'auteur_role' => $user['privilege'] ?? ($_SESSION['user_privilege'] ?? null),
        'date_action' => $quand ?? date('Y-m-d H:i:s'),
        'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Marque la remise comme touchee : la liste affiche « derniere modification ». */
function remiseToucher(PDO $dtb, int $remiseId): void
{
    $dtb->prepare('UPDATE t_remise_notes SET modifie_le = :d WHERE id = :id')
        ->execute(['d' => date('Y-m-d H:i:s'), 'id' => $remiseId]);
}

/* -----------------------------------------------------------------------------
   Chargement et creation
   -------------------------------------------------------------------------- */

function remiseCharger(PDO $dtb, int $remiseId): ?array
{
    $stmt = $dtb->prepare('SELECT * FROM t_remise_notes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $remiseId]);
    $remise = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$remise) {
        return null;
    }
    $remise['extras'] = remiseExtras($dtb, $remiseId);
    return $remise;
}

function remiseTrouver(PDO $dtb, int $coursId, string $annee, ?int $semestre): ?array
{
    $stmt = $dtb->prepare('SELECT * FROM t_remise_notes
        WHERE cours_id = :c AND annee_scolaire = :a AND semestre <=> :s LIMIT 1');
    $stmt->execute(['c' => $coursId, 'a' => $annee, 's' => $semestre]);
    $remise = $stmt->fetch(PDO::FETCH_ASSOC);
    return $remise ?: null;
}

/**
 * Ouvre la remise du cours, en la creant au premier acces.
 *
 * La creation reprend les ponderations par defaut de la table : l'enseignant
 * les ajuste ensuite. Les lignes sont synchronisees dans la foulee pour que la
 * feuille s'ouvre deja peuplee des etudiants inscrits.
 */
function remiseOuvrir(PDO $dtb, int $coursId, string $annee, ?int $semestre): ?array
{
    $remise = remiseTrouver($dtb, $coursId, $annee, $semestre);
    if ($remise) {
        remiseSynchroniserLignes($dtb, $remise);
        return remiseCharger($dtb, (int) $remise['id']);
    }

    $stmt = $dtb->prepare('SELECT * FROM t_2023_cours WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $coursId]);
    $cours = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cours) {
        return null;
    }

    $maintenant = date('Y-m-d H:i:s');
    /* cree_le et modifie_le recoivent la meme date, mais chacun son parametre :
       PDO en prepared statements natifs refuse qu'un nom serve deux fois. */
    $insert = $dtb->prepare('INSERT INTO t_remise_notes
        (cours_id, sigle, titre_cours, teacher_uid, annee_scolaire, semestre,
         statut, cree_par, cree_le, modifie_le)
        VALUES (:cours_id, :sigle, :titre, :teacher, :annee, :semestre,
                "brouillon", :par, :cree_le, :modifie_le)');
    $insert->execute([
        'cours_id'   => $coursId,
        'sigle'      => $cours['Sigle'] ?? null,
        'titre'      => $cours['title'] ?? null,
        'teacher'    => $cours['id_teacher'] ?? null,
        'annee'      => $annee,
        'semestre'   => $semestre,
        'par'        => $_SESSION['user_id'] ?? null,
        'cree_le'    => $maintenant,
        'modifie_le' => $maintenant,
    ]);

    $remiseId = (int) $dtb->lastInsertId();
    remiseJournaliser($dtb, $remiseId, 'creation', null, null,
        ($cours['Sigle'] ?? '') . ' — ' . $annee . ($semestre ? ' / S' . $semestre : ''));

    $remise = remiseCharger($dtb, $remiseId);
    if ($remise) {
        remiseSynchroniserLignes($dtb, $remise);
        $remise = remiseCharger($dtb, $remiseId);
    }
    return $remise;
}

/**
 * Cree les lignes manquantes a partir des inscriptions au cours.
 *
 * Rejoue a chaque ouverture : un etudiant inscrit apres la creation de la
 * remise doit apparaitre, sinon sa note ne serait jamais saisie. On n'efface
 * jamais une ligne existante ici — une note deja posee ne doit pas disparaitre
 * parce qu'une inscription a bouge.
 *
 * La jointure se fait sur id_cours : contrairement au sigle, il n'a aucun
 * orphelin dans t_2023_notes et ne confond pas deux cours homonymes.
 */
function remiseSynchroniserLignes(PDO $dtb, array $remise): int
{
    if (!remisePeutSaisir($remise)) {
        return 0; // une remise soumise ou validee ne bouge plus
    }

    $params = [
        'cours'  => $remise['cours_id'],
        'annee'  => $remise['annee_scolaire'],
    ];
    $clauseSemestre = '';
    if ($remise['semestre'] !== null && $remise['semestre'] !== '') {
        /* Le semestre vit soit sur la session, soit sur la note elle-meme :
           les deux sources coexistent en base (voir remiseNotes.php). La session
           fait foi quand elle en porte un ; session_semester valant 0 signifie
           « non renseigne » et non « semestre zero », d'ou le NULLIF. */
        $clauseSemestre = ' AND ' . REMISE_SQL_SEMESTRE . ' = :sem';
        $params['sem'] = (int) $remise['semestre'];
    }

    $sql = 'SELECT n.id, n.student_id
            FROM t_2023_notes n
            LEFT JOIN t_2023_session s ON s.session_id = n.session_id
            LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = n.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
            WHERE n.id_cours = :cours AND n.annee_scolaire = :annee AND n.remove = 0'
          . $clauseSemestre .
          ' AND COALESCE(e.suspended, 0) = 0 AND COALESCE(e.retrait_universite, 0) = 0
            ORDER BY n.student_id ASC';

    $stmt = $dtb->prepare($sql);
    $stmt->execute($params);

    $insert = $dtb->prepare('INSERT IGNORE INTO t_remise_notes_ligne
        (remise_id, note_id, student_id, modifie_le)
        VALUES (:remise, :note, :student, :le)');
    $majNoteId = $dtb->prepare('UPDATE t_remise_notes_ligne SET note_id = :note
        WHERE remise_id = :remise AND student_id = :student AND note_id IS NULL');

    $ajoutees   = 0;
    $maintenant = date('Y-m-d H:i:s');
    while ($ligne = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $insert->execute([
            'remise'  => $remise['id'],
            'note'    => $ligne['id'],
            'student' => $ligne['student_id'],
            'le'      => $maintenant,
        ]);
        if ($insert->rowCount() > 0) {
            $ajoutees++;
        } else {
            $majNoteId->execute([
                'note'    => $ligne['id'],
                'remise'  => $remise['id'],
                'student' => $ligne['student_id'],
            ]);
        }
    }

    return $ajoutees;
}

/**
 * Lignes d'une remise, enrichies du nom de l'etudiant et de la note
 * actuellement en base — celle que la validation remplacerait.
 */
function remiseLignes(PDO $dtb, int $remiseId): array
{
    $stmt = $dtb->prepare('SELECT l.*,
            e.student_nom, e.student_prenom, e.annee_etude,
            n.grade AS grade_actuel, n.cours_category, n.Sigle AS note_sigle, n.title_cours
        FROM t_remise_notes_ligne l
        LEFT JOIN tbl_2024_etudiant e ON e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = l.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)
        LEFT JOIN t_2023_notes n ON n.id = l.note_id
        WHERE l.remise_id = :id
        ORDER BY e.student_nom ASC, e.student_prenom ASC, l.student_id ASC');
    $stmt->execute(['id' => $remiseId]);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    remiseJoindreExtras($dtb, $remiseId, $lignes);
    return $lignes;
}

/** Joint a chaque ligne les notes des colonnes ajoutees : $ligne['extras'][cle] = valeur (ou null). */
function remiseJoindreExtras(PDO $dtb, int $remiseId, array &$lignes, ?int $ligneId = null): void
{
    remiseColonnesInstaller($dtb);
    $parLigne = [];
    $stmt = $dtb->prepare('SELECT v.ligne_id, c.cle, v.valeur
        FROM t_remise_notes_valeur v JOIN t_remise_notes_colonne c ON c.id = v.colonne_id
        WHERE c.remise_id = :r' . ($ligneId !== null ? ' AND v.ligne_id = :l' : ''));
    $stmt->execute(['r' => $remiseId] + ($ligneId !== null ? ['l' => $ligneId] : []));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $parLigne[(int) $v['ligne_id']][$v['cle']] = $v['valeur'] === null ? null : (float) $v['valeur'];
    }
    foreach ($lignes as &$l) {
        $l['extras'] = $parLigne[(int) $l['id']] ?? [];
    }
}

/** Une ligne (un etudiant) avec ses notes ajoutees, sans recharger toute la feuille. */
function remiseLigne(PDO $dtb, int $remiseId, string $studentId): ?array
{
    $stmt = $dtb->prepare('SELECT * FROM t_remise_notes_ligne WHERE remise_id = :r AND student_id = :s LIMIT 1');
    $stmt->execute(['r' => $remiseId, 's' => $studentId]);
    $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ligne) {
        return null;
    }
    $lignes = [$ligne];
    remiseJoindreExtras($dtb, $remiseId, $lignes, (int) $ligne['id']);
    return $lignes[0];
}

/** Journal d'une remise, du plus recent au plus ancien. */
function remiseJournal(PDO $dtb, int $remiseId, int $limite = 200): array
{
    $stmt = $dtb->prepare('SELECT * FROM t_remise_notes_journal
        WHERE remise_id = :id ORDER BY date_action DESC, id DESC LIMIT ' . (int) $limite);
    $stmt->execute(['id' => $remiseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------------------------------------------------------
   Controle de coherence
   -------------------------------------------------------------------------- */

/**
 * Ce que le registraire doit regarder avant de valider, et ce qui empeche
 * l'enseignant de soumettre.
 *
 * @return array{bloquants:string[], avertissements:string[], pretes:int, total:int}
 */
function remiseControler(PDO $dtb, array $remise, ?array $lignes = null): array
{
    $lignes ??= remiseLignes($dtb, (int) $remise['id']);

    $bloquants       = [];
    $avertissements  = [];
    $pretes          = 0;
    $incompletes     = [];
    $horsBareme      = [];
    $nonNotees       = [];

    $totalPoids = remiseTotalPoids($remise);
    if (abs($totalPoids - 100.0) > 0.01) {
        $bloquants[] = 'La ponderation totalise ' . rtrim(rtrim(number_format($totalPoids, 2, ',', ' '), '0'), ',')
                     . ' % au lieu de 100 %.';
    }

    if (!$lignes) {
        $bloquants[] = 'Aucun etudiant inscrit a ce cours pour cette annee et ce semestre.';
    }

    foreach ($lignes as $ligne) {
        $coursValidation = noteEstCoursValidation($ligne);
        $calcul = remiseCalculerNote($ligne, $remise);
        $nom    = trim(($ligne['student_nom'] ?? '') . ' ' . ($ligne['student_prenom'] ?? '')) ?: $ligne['student_id'];

        if ($calcul['etat'] === 'incomplete') {
            $incompletes[] = $nom;
            continue;
        }
        if ($calcul['etat'] === 'vide') {
            $nonNotees[] = $nom;
            continue;
        }
        if ($calcul['valeur'] !== null && ($calcul['valeur'] < 0 || $calcul['valeur'] > 20)) {
            $horsBareme[] = $nom . ' (' . $calcul['valeur'] . ')';
            continue;
        }
        if (remiseNoteVersGrade($ligne, $remise, $coursValidation) !== null) {
            $pretes++;
        }
    }

    if ($horsBareme) {
        $bloquants[] = 'Note hors bareme 0-20 : ' . remiseListeCourte($horsBareme) . '.';
    }
    if ($incompletes) {
        $bloquants[] = count($incompletes) . ' etudiant(s) avec une composante ponderee non saisie : '
                     . remiseListeCourte($incompletes) . '.';
    }
    if ($nonNotees) {
        $avertissements[] = count($nonNotees) . ' etudiant(s) sans aucune note : ' . remiseListeCourte($nonNotees)
                          . '. Ils seront enregistres « pas encore note » (NN).';
    }

    return [
        'bloquants'      => $bloquants,
        'avertissements' => $avertissements,
        'pretes'         => $pretes,
        'total'          => count($lignes),
    ];
}

/** Tronque une enumeration pour qu'un message reste lisible. */
function remiseListeCourte(array $noms, int $max = 5): string
{
    if (count($noms) <= $max) {
        return implode(', ', $noms);
    }
    return implode(', ', array_slice($noms, 0, $max)) . ' et ' . (count($noms) - $max) . ' autre(s)';
}

/* -----------------------------------------------------------------------------
   Transitions
   -------------------------------------------------------------------------- */

/**
 * L'enseignant envoie sa feuille au controle. Elle se fige : il ne peut plus
 * la modifier tant que le registrariat ne l'a pas renvoyee.
 *
 * @return array{ok:bool, message:string, controle?:array}
 */
function remiseSoumettre(PDO $dtb, array $remise): array
{
    if (!remisePeutSaisir($remise)) {
        return ['ok' => false, 'message' => 'Cette remise a deja ete transmise.'];
    }

    $controle = remiseControler($dtb, $remise);
    if ($controle['bloquants']) {
        return [
            'ok'       => false,
            'message'  => 'La remise ne peut pas etre transmise en l\'etat.',
            'controle' => $controle,
        ];
    }

    $maintenant = date('Y-m-d H:i:s');
    $dtb->prepare('UPDATE t_remise_notes
            SET statut = "soumise", soumis_par = :par, soumis_le = :soumis_le,
                modifie_le = :modifie_le, motif_rejet = NULL
            WHERE id = :id')
        ->execute([
            'par'        => $_SESSION['user_id'] ?? null,
            'soumis_le'  => $maintenant,
            'modifie_le' => $maintenant,
            'id'         => $remise['id'],
        ]);

    remiseJournaliser($dtb, (int) $remise['id'], 'soumission', 'statut', $remise['statut'], 'soumise');

    return [
        'ok'       => true,
        'message'  => 'Remise transmise au registrariat. Elle est desormais en lecture seule.',
        'controle' => $controle,
    ];
}

/**
 * Le registrariat valide : c'est ici, et nulle part ailleurs, que les notes
 * entrent dans t_2023_notes et deviennent visibles dans l'historique et le
 * bulletin. Chaque ecriture est doublee dans t_notes_modification_history,
 * pour que la note apparaisse aussi dans l'historique de l'etudiant.
 *
 * Tout se joue dans une transaction : une validation a moitie ecrite laisserait
 * un bulletin incoherent, sans moyen de savoir ou elle s'est arretee.
 *
 * @return array{ok:bool, message:string, ecrites?:int, controle?:array}
 */
function remiseValider(PDO $dtb, array $remise): array
{
    if (($remise['statut'] ?? '') !== 'soumise') {
        return ['ok' => false, 'message' => 'Seule une remise transmise peut etre validee.'];
    }

    $lignes   = remiseLignes($dtb, (int) $remise['id']);
    $controle = remiseControler($dtb, $remise, $lignes);
    if ($controle['bloquants']) {
        return [
            'ok'       => false,
            'message'  => 'Incoherences a lever avant validation.',
            'controle' => $controle,
        ];
    }

    $maintenant = date('Y-m-d H:i:s');
    $auteurId   = $_SESSION['user_id'] ?? null;
    $ip         = $_SERVER['REMOTE_ADDR'] ?? null;

    $majNote = $dtb->prepare('UPDATE t_2023_notes
        SET grade = :grade, last_change_user_id = :user, last_change_datetime = :date
        WHERE id = :id');

    $histo = $dtb->prepare('INSERT INTO t_notes_modification_history
        (note_id, student_id, session_id, cours_sigle, cours_titre, old_grade, new_grade,
         action_type, action_by, action_date, ip_address, commentaire)
        VALUES (:note_id, :student_id, :session_id, :sigle, :titre, :old, :new,
                :action, :by, :date, :ip, :commentaire)');

    $sessionParNote = $dtb->prepare('SELECT session_id FROM t_2023_notes WHERE id = :id LIMIT 1');

    $dtb->beginTransaction();
    try {
        $ecrites = 0;
        foreach ($lignes as $ligne) {
            if (empty($ligne['note_id'])) {
                continue; // ligne orpheline : aucune inscription a mettre a jour
            }
            $grade = remiseNoteVersGrade($ligne, $remise, noteEstCoursValidation($ligne));
            if ($grade === null) {
                $grade = '-4'; // pas encore note : l'etudiant reste visible, sans note
            }

            $ancienne = $ligne['grade_actuel'];
            if ((string) $ancienne === (string) $grade) {
                continue; // rien a ecrire, rien a historiser
            }

            $majNote->execute([
                'grade' => $grade,
                'user'  => $auteurId,
                'date'  => $maintenant,
                'id'    => $ligne['note_id'],
            ]);

            $sessionParNote->execute(['id' => $ligne['note_id']]);
            $sessionId = $sessionParNote->fetchColumn();

            /* old_grade et new_grade sont des DECIMAL : les codes -2/-3/-4 y
               tiennent, mais une valeur non numerique (ancienne saisie libre)
               ferait echouer l'insertion. */
            $histo->execute([
                'note_id'     => $ligne['note_id'],
                'student_id'  => $ligne['student_id'],
                'session_id'  => $sessionId ?: null,
                'sigle'       => $ligne['note_sigle'] ?? $remise['sigle'],
                'titre'       => $ligne['title_cours'] ?? $remise['titre_cours'],
                'old'         => is_numeric($ancienne) ? $ancienne : null,
                'new'         => is_numeric($grade) ? $grade : null,
                'action'      => ($ancienne === null || $ancienne === '') ? 'creation' : 'modification',
                'by'          => $auteurId,
                'date'        => $maintenant,
                'ip'          => $ip,
                'commentaire' => 'Validation de la remise #' . $remise['id']
                                 . ' de ' . ($remise['sigle'] ?? '') . ' (' . $remise['annee_scolaire'] . ')',
            ]);

            $ecrites++;
        }

        $dtb->prepare('UPDATE t_remise_notes
                SET statut = "validee", traite_par = :par, traite_le = :traite_le,
                    modifie_le = :modifie_le, motif_rejet = NULL
                WHERE id = :id')
            ->execute([
                'par'        => $auteurId,
                'traite_le'  => $maintenant,
                'modifie_le' => $maintenant,
                'id'         => $remise['id'],
            ]);

        remiseJournaliser($dtb, (int) $remise['id'], 'validation', 'statut', 'soumise',
            'validee — ' . $ecrites . ' note(s) transferee(s)');

        $dtb->commit();
    } catch (Throwable $e) {
        $dtb->rollBack();
        return ['ok' => false, 'message' => 'Echec de la validation : ' . $e->getMessage()];
    }

    return [
        'ok'       => true,
        'ecrites'  => $ecrites,
        'message'  => $ecrites . ' note(s) integree(s) a l\'historique et au bulletin.',
        'controle' => $controle,
    ];
}

/** Renvoi a l'enseignant, avec le motif. Il redevient libre de corriger. */
function remiseRejeter(PDO $dtb, array $remise, string $motif): array
{
    if (($remise['statut'] ?? '') !== 'soumise') {
        return ['ok' => false, 'message' => 'Seule une remise transmise peut etre renvoyee.'];
    }
    $motif = trim($motif);
    if ($motif === '') {
        return ['ok' => false, 'message' => 'Indiquez a l\'enseignant ce qui doit etre corrige.'];
    }

    $maintenant = date('Y-m-d H:i:s');
    $dtb->prepare('UPDATE t_remise_notes
            SET statut = "rejetee", motif_rejet = :motif,
                traite_par = :par, traite_le = :traite_le, modifie_le = :modifie_le
            WHERE id = :id')
        ->execute([
            'motif'      => $motif,
            'par'        => $_SESSION['user_id'] ?? null,
            'traite_le'  => $maintenant,
            'modifie_le' => $maintenant,
            'id'         => $remise['id'],
        ]);

    remiseJournaliser($dtb, (int) $remise['id'], 'rejet', 'statut', 'soumise', 'rejetee — ' . $motif);

    return ['ok' => true, 'message' => 'Remise renvoyee a l\'enseignant.'];
}

/* -----------------------------------------------------------------------------
   Affichage
   -------------------------------------------------------------------------- */

/** Libelle du semestre, avec les deux semestres particuliers de l'etablissement. */
function remiseLibelleSemestre($semestre): string
{
    $s = (string) $semestre;
    if ($s === '3') { return 'Semestre d\'ete'; }
    if ($s === '4') { return 'Semestre d\'hiver'; }
    if ($s === '')  { return 'Tous semestres'; }
    return 'Semestre ' . $s;
}

/** Libelle du niveau, licence ou master, comme partout ailleurs dans l'application. */
function remiseLibelleNiveau($yearlevel): string
{
    $n = (int) $yearlevel;
    if ($n <= 0) { return '-'; }
    return $n <= 3 ? 'Licence ' . $n : 'Master ' . ($n - 3);
}

/** Annee scolaire courante, au format de t_2023_notes.annee_scolaire. */
function remiseAnneeCourante(): string
{
    return date('m') >= 7
        ? date('Y') . ' - ' . (date('Y') + 1)
        : (date('Y') - 1) . ' - ' . date('Y');
}

} // REMISE_NOTES_LOADED
