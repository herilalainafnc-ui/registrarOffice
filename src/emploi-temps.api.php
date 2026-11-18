<?php
/* =============================================================================
   Emploi du temps — point d'entree AJAX

   IMPORTANT — ce fichier repondait sans aucun controle d'acces. Un simple
   « curl » anonyme obtenait la liste complete des enseignants et des salles, et
   surtout pouvait AJOUTER, MODIFIER, DEPLACER ou SUPPRIMER n'importe quelle
   seance. Il ne chargeait que backdb.php et session_start(), jamais la garde
   commune. C'est corrige ci-dessous : garde sur toute la surface, et jeton
   anti-CSRF sur les ecritures, comme dans app/.annonces et app/.user.

   La detection des conflits et la lecture des horaires vivent desormais dans
   data/edt.php, partagees avec la page. Elles etaient ici en double, avec des
   regles divergentes selon l'action appelee.
   ========================================================================== */

require_once(__DIR__ . '/../data/guard.ajax.php');
exigerConnexion();
// Comme la page /schedule, seule a appeler cette API : pas d'acces enseignant.
refuserEnseignant("L'emploi du temps ne fait pas partie de l'espace enseignant.");
/* Ni l'espace etudiant : la page et cette API etaient ouvertes a un compte
   etudiant, qui pouvait ajouter, deplacer ou supprimer des seances (verifie
   en local le 28/09/2026). */
refuserEtudiant();

require_once(__DIR__ . '/../data/edt.php');
require_once(__DIR__ . '/../data/cours.sigle.php');
require_once(__DIR__ . '/../data/vitrine.config.php');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
/* La reponse depend de la session et de l'etat de la base : un intermediaire
   qui la garderait servirait un emploi du temps perime. */
header('Cache-Control: no-store');

/** Repond en JSON et s'arrete. */
function edtRepondre(array $charge, int $code = 200) : void
{
    http_response_code($code);
    echo json_encode($charge, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* Les actions qui ecrivent exigent la methode POST et un jeton valide. Les
   lectures restent accessibles en GET a un utilisateur connecte. */
const EDT_ECRITURES = ['add_seance', 'update_seance', 'delete_seance', 'move_seance', 'add_salle', 'update_salle'];

if (in_array($action, EDT_ECRITURES, true)) {
    /* Modifier : niveaux 1 a 3 seulement (data/edt.php, EDT_NIVEAU_ECRITURE). Les
       autres personnels consultent ; la page ne leur montre aucune commande
       d'ecriture. */
    exigerConnexion(EDT_NIVEAU_ECRITURE);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        edtRepondre(['success' => false, 'error' => 'Méthode non autorisée.'], 405);
    }
    if (!function_exists('verify_csrf') || !verify_csrf()) {
        /* Pas 403 : src/js/session.js l'intercepte pour annoncer un refus de
           privileges et rediriger vers la connexion, ce qui serait faux — il
           s'agit d'un jeton perime, pas des droits de l'utilisateur. Pas 419
           non plus : ce code n'est pas enregistre a l'IANA et ce serveur le
           transforme en 500 — verifie. Le motif voyage donc dans la charge
           utile, ou le client le reconnait a « csrf_expire ». */
        edtRepondre([
            'success' => false,
            'csrf_expire' => true,
            'error' => 'Jeton de sécurité expiré. Rechargez la page et réessayez.',
        ], 400);
    }
}

/* Annee scolaire courante : passee par l'appelant, sinon celle par defaut.
   Elle etait ecrite en dur a l'insertion et absente de toutes les requetes de
   conflit, qui cherchaient donc dans tout l'historique de la table. */
$annee = trim((string) ($_POST['annee_scolaire'] ?? $_GET['annee_scolaire'] ?? ''));
if ($annee === '') { $annee = edtAnneeParDefaut($dtb); }

try {
    switch ($action) {
        case 'get_cours':      edtGetCours($dtb);            break;
        case 'get_salles':     edtGetSalles($dtb);           break;
        case 'get_teachers':   edtGetTeachers($dtb);         break;
        case 'get_parcours':   edtGetParcours($dtb);         break;
        case 'get_seance':     edtGetSeance($dtb);           break;
        case 'get_seances':    edtGetSeances($dtb, $annee);  break;
        case 'check_conflicts':edtCheckConflicts($dtb, $annee); break;
        case 'add_seance':     edtAddSeance($dtb, $annee);   break;
        case 'update_seance':  edtUpdateSeance($dtb, $annee);break;
        case 'move_seance':    edtMoveSeance($dtb, $annee);  break;
        case 'delete_seance':  edtDeleteSeance($dtb);        break;
        case 'add_salle':      edtEnregistrerSalle($dtb, true, $annee);  break;
        case 'update_salle':   edtEnregistrerSalle($dtb, false, $annee); break;
        default:
            edtRepondre(['error' => 'Action non reconnue'], 400);
    }
} catch (Throwable $e) {
    /* Le detail part au journal, jamais au client : les messages d'exception
       PDO citent les requetes et les noms de colonnes. */
    error_log('Emploi du temps API [' . $action . '] : ' . $e->getMessage());
    edtRepondre(['success' => false, 'error' => "L'opération n'a pas abouti."], 500);
}


/* =============================================================================
   Lectures
   ========================================================================== */

function edtGetCours(PDO $dtb) : void
{
    /* Le code affiche remplit « Sigle » : le JS filtre et affiche ce champ,
       il n'a donc rien a changer. La cle technique n'a pas a sortir d'ici. */
    coursSigleAliasInstaller($dtb);
    $sql = "SELECT id, COALESCE(NULLIF(TRIM(sigle_affiche), ''), Sigle) AS Sigle,
                   title, dep_desc, parcours, yearlevel, semester, id_teacher
            FROM t_2023_cours WHERE remove != 1";
    $p = [];

    /* Requetes preparees : l'ancienne version concatenait les filtres dans le
       SQL, protegee seulement par quote() et intval(). */
    if (($m = trim((string) ($_GET['mention'] ?? ''))) !== '') { $sql .= ' AND dep_desc = ?'; $p[] = $m; }
    if (($n = trim((string) ($_GET['niveau'] ?? ''))) !== '')  { $sql .= ' AND yearlevel = ?'; $p[] = (int) $n; }
    if (($s = trim((string) ($_GET['semester'] ?? ''))) !== '') { $sql .= ' AND semester = ?'; $p[] = (int) $s; }

    $sql .= " ORDER BY COALESCE(NULLIF(TRIM(sigle_affiche), ''), Sigle)";
    $st = $dtb->prepare($sql);
    $st->execute($p);
    edtRepondre($st->fetchAll(PDO::FETCH_ASSOC));
}

function edtGetSalles(PDO $dtb) : void
{
    $st = $dtb->query(
        "SELECT id, salle_code, salle_nom, capacite, type_salle, batiment, disponible
         FROM t_2024_salles ORDER BY salle_code");
    edtRepondre($st->fetchAll(PDO::FETCH_ASSOC));
}

function edtGetTeachers(PDO $dtb) : void
{
    /* « retire » voyage avec chaque enseignant : la page les ecarte des choix,
       sauf celui deja affecte a la seance qu'on modifie. */
    $st = $dtb->query("SELECT uid, name, lastName, (COALESCE(remove, 0) = 1) AS retire FROM teacher WHERE uid > 0 ORDER BY name, lastName");
    edtRepondre($st->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Correspondance mention -> identifiant dans la table parcours.
 *
 * Il en existait DEUX versions dans ce fichier, et elles se contredisaient :
 * un tableau PHP disait 6=COMM, 7=LANG, 8=DROI, tandis qu'un CASE SQL disait
 * 6=DROI, 7=COMM, 8=LANG. Filtrer par mention ne donnait donc pas le meme
 * resultat que lire la liste complete. Verification faite en base — les
 * parcours de mention_id 6 sont « Communication en entreprise » et « Média et
 * journalisme » — c'est le tableau PHP qui avait raison.
 */
function edtMentions() : array
{
    return ['THEO' => 1, 'GEST' => 2, 'INFO' => 3, 'NURS' => 4,
            'EDUC' => 5, 'COMM' => 6, 'LANG' => 7, 'DROI' => 8];
}

function edtGetParcours(PDO $dtb) : void
{
    $mentions = edtMentions();
    $mention = trim((string) ($_GET['mention'] ?? ''));

    if ($mention !== '' && isset($mentions[$mention])) {
        $st = $dtb->prepare("SELECT id, nom FROM parcours WHERE mention_id = ? ORDER BY nom");
        $st->execute([$mentions[$mention]]);
        edtRepondre($st->fetchAll(PDO::FETCH_ASSOC));
    }

    // Liste complete : le code de mention est ajoute en PHP, depuis la meme
    // source que ci-dessus, pour qu'il n'y ait plus qu'une correspondance.
    $versCode = array_flip($mentions);
    $lignes = $dtb->query("SELECT id, nom, mention_id FROM parcours ORDER BY mention_id, nom")
                  ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lignes as $i => $l) {
        $lignes[$i]['mention_code'] = $versCode[(int) $l['mention_id']] ?? null;
    }
    edtRepondre($lignes);
}

function edtGetSeance(PDO $dtb) : void
{
    $st = $dtb->prepare("SELECT * FROM t_2024_emploi_du_temps WHERE id = ?");
    $st->execute([(int) ($_GET['id'] ?? 0)]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { edtRepondre(['error' => 'Séance non trouvée'], 404); }
    edtRepondre($r);
}

/**
 * Toutes les seances d'une annee.
 *
 * Nouvelle action : elle permet a la page de se remettre a jour apres un
 * ajout, un deplacement ou une suppression sans recharger. L'ancienne version
 * appelait location.reload() apres chaque operation.
 */
function edtGetSeances(PDO $dtb, string $annee) : void
{
    $st = $dtb->prepare(
        "SELECT e.*, sa.salle_nom, t.name AS prof_nom, t.lastName AS prof_prenom
         FROM t_2024_emploi_du_temps e
         LEFT JOIN t_2024_salles sa ON e.salle_id = sa.id
         LEFT JOIN teacher t ON e.id_teacher = t.uid
         WHERE e.statut = 'confirme' AND e.annee_scolaire = ?
         ORDER BY FIELD(e.jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'),
                  e.heure_debut");
    $st->execute([$annee]);
    edtRepondre(['annee' => $annee, 'seances' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}


/* =============================================================================
   Conflits
   ========================================================================== */

/** Assemble le creneau examine a partir de la requete. */
function edtCreneauDepuisRequete(array $src, string $annee, array $defauts = []) : array
{
    return [
        'jour'           => $src['jour_semaine']  ?? ($defauts['jour_semaine'] ?? ''),
        'debut'          => $src['heure_debut']   ?? ($defauts['heure_debut'] ?? ''),
        'fin'            => $src['heure_fin']     ?? ($defauts['heure_fin'] ?? ''),
        'salle_id'       => (int) ($src['salle_id']   ?? ($defauts['salle_id'] ?? 0)),
        'id_teacher'     => (int) ($src['id_teacher'] ?? ($defauts['id_teacher'] ?? 0)),
        'mention'        => $src['mention']  ?? ($defauts['mention'] ?? ''),
        'niveau'         => (string) ($src['niveau'] ?? ($defauts['niveau'] ?? '')),
        'parcours'       => $src['parcours'] ?? ($defauts['parcours'] ?? ''),
        'type_seance'    => $src['type_seance'] ?? ($defauts['type_seance'] ?? 'cours'),
        'semester'       => (int) ($src['semester'] ?? ($defauts['semester'] ?? 0)),
        'cours_id'       => (int) ($src['cours_id'] ?? ($defauts['cours_id'] ?? 0)),
        'annee_scolaire' => $annee,
    ];
}

function edtCheckConflicts(PDO $dtb, string $annee) : void
{
    $creneau = edtCreneauDepuisRequete($_POST, $annee);
    $exclure = (int) ($_POST['exclude_id'] ?? 0);

    /* Le cours fait foi pour la promotion et le semestre : le client les
       envoyait lui-meme, et le semestre n'y figurait pas. */
    if ($creneau['cours_id'] > 0) {
        $st = $dtb->prepare('SELECT dep_desc, yearlevel, semester, parcours FROM t_2023_cours WHERE id = ?');
        $st->execute([$creneau['cours_id']]);
        if ($c = $st->fetch(PDO::FETCH_ASSOC)) {
            $creneau['mention']  = (string) $c['dep_desc'];
            $creneau['niveau']   = (string) $c['yearlevel'];
            $creneau['semester'] = (int) $c['semester'];
            if (trim((string) $creneau['parcours']) === '') { $creneau['parcours'] = (string) $c['parcours']; }
        }
    }

    $motif = edtValiderCreneau($creneau['jour'], $creneau['debut'], $creneau['fin']);
    if ($motif !== null) {
        edtRepondre(['hasConflicts' => true, 'hasErrors' => true,
                     'conflicts' => [['type' => 'creneau', 'severity' => 'error', 'message' => $motif]]]);
    }

    $c = edtConflits($dtb, $creneau, $exclure ?: null);
    edtRepondre([
        'hasConflicts' => count($c) > 0,
        'hasErrors'    => edtBloquant($c),
        'conflicts'    => $c,
    ]);
}


/* =============================================================================
   Ecritures

   Politique unique, appliquee a l'ajout comme a la modification et au
   deplacement :
     - un conflit « error » (salle ou enseignant) bloque toujours ;
     - un conflit « warning » (etudiants) bloque, sauf si force=1.

   Auparavant l'ajout ne bloquait que sur les « error » tandis que la
   modification et le deplacement bloquaient sur le simple nombre de conflits.
   On pouvait donc creer une seance qu'il devenait ensuite impossible de
   deplacer, sans que rien n'explique pourquoi.
   ========================================================================== */

/** @return array{0:bool,1:array} [on peut ecrire, conflits] */
function edtAutorise(PDO $dtb, array $creneau, ?int $exclure, bool $force) : array
{
    $c = edtConflits($dtb, $creneau, $exclure);
    if (edtBloquant($c)) { return [false, $c]; }
    if ($c && !$force)   { return [false, $c]; }
    return [true, $c];
}

function edtAddSeance(PDO $dtb, string $annee) : void
{
    $coursId = (int) ($_POST['cours_id'] ?? 0);
    $st = $dtb->prepare("SELECT * FROM t_2023_cours WHERE id = ?");
    $st->execute([$coursId]);
    $cours = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cours) { edtRepondre(['success' => false, 'error' => 'Cours non trouvé'], 404); }

    $jour  = (string) ($_POST['jour_semaine'] ?? '');
    $debut = (string) ($_POST['heure_debut'] ?? '');
    $fin   = (string) ($_POST['heure_fin'] ?? '');

    $motif = edtValiderCreneau($jour, $debut, $fin);
    if ($motif !== null) { edtRepondre(['success' => false, 'error' => $motif], 422); }

    $parcours = trim((string) ($_POST['parcours'] ?? ''));
    if ($parcours === '') { $parcours = (string) ($cours['parcours'] ?? ''); }

    $salleId = (int) ($_POST['salle_id'] ?? 0);
    $prof    = (int) ($_POST['id_teacher'] ?? 0) ?: (int) ($cours['id_teacher'] ?? 0);

    $creneau = [
        'jour' => $jour, 'debut' => $debut, 'fin' => $fin,
        'salle_id' => $salleId, 'id_teacher' => $prof,
        'mention' => $cours['dep_desc'], 'niveau' => (string) $cours['yearlevel'],
        'parcours' => $parcours,
        'type_seance' => (string) ($_POST['type_seance'] ?? 'cours'),
        'semester' => (int) $cours['semester'], 'cours_id' => $coursId,
        'annee_scolaire' => $annee,
    ];

    [$permis, $conflits] = edtAutorise($dtb, $creneau, null, ($_POST['force'] ?? '0') === '1');
    if (!$permis) {
        edtRepondre(['success' => false, 'error' => 'Conflits détectés',
                     'conflicts' => $conflits, 'forcable' => !edtBloquant($conflits)], 409);
    }

    $salleCode = '';
    if ($salleId > 0) {
        $s = $dtb->prepare("SELECT salle_code FROM t_2024_salles WHERE id = ?");
        $s->execute([$salleId]);
        $salleCode = (string) ($s->fetchColumn() ?: '');
    }

    $ins = $dtb->prepare(
        "INSERT INTO t_2024_emploi_du_temps
         (cours_id, cours_sigle, cours_title, type_seance, niveau, mention, parcours,
          semester, annee_scolaire, jour_semaine, heure_debut, heure_fin, duree_minutes,
          salle_id, salle_code, id_teacher, remarque, statut, created_by, created_at, updated_at)
         VALUES (:cours_id, :sigle, :titre, :type, :niveau, :mention, :parcours,
                 :semester, :annee, :jour, :debut, :fin, :duree,
                 :salle_id, :salle_code, :prof, :remarque, 'confirme', :par, :cree, :maj)");

    /* Heure de Madagascar, pas NOW() : MySQL est en UTC en production. Deux
       noms pour une meme valeur : les requetes preparees natives refusent un
       nom repete. */
    $maintenant = edtHorodatage();
    $ins->execute([
        ':cours_id' => $coursId,
        ':sigle'    => coursSigleAffiche($cours),   // etiquette montree sur la carte
        ':titre'    => $cours['title'],
        ':type'     => $creneau['type_seance'],
        ':niveau'   => $cours['yearlevel'],
        ':mention'  => $cours['dep_desc'],
        ':parcours' => $parcours,
        ':semester' => $cours['semester'],
        ':annee'    => $annee,
        ':jour'     => $jour,
        ':debut'    => $debut,
        ':fin'      => $fin,
        // Colonne presente au schema mais jamais renseignee jusqu'ici.
        ':duree'    => edtMinutes($fin) - edtMinutes($debut),
        ':salle_id' => $salleId ?: null,
        ':salle_code' => $salleCode,
        ':prof'     => $prof ?: null,
        ':remarque' => (string) ($_POST['remarque'] ?? ''),
        /* La traçabilite retombait sur « ?? 1 » : toute seance creee hors
           session etait attribuee a l'utilisateur numero 1. La garde en tete
           de fichier garantit maintenant une session, donc un auteur reel. */
        ':par'      => $_SESSION['user_id'] ?? null,
        ':cree'     => $maintenant,
        ':maj'      => $maintenant,
    ]);

    edtRepondre(['success' => true, 'id' => (int) $dtb->lastInsertId(),
                 'conflicts' => $conflits]);
}

function edtUpdateSeance(PDO $dtb, string $annee) : void
{
    $id = (int) ($_POST['id'] ?? 0);
    $st = $dtb->prepare("SELECT * FROM t_2024_emploi_du_temps WHERE id = ?");
    $st->execute([$id]);
    $seance = $st->fetch(PDO::FETCH_ASSOC);
    if (!$seance) { edtRepondre(['success' => false, 'error' => 'Séance non trouvée'], 404); }

    $jour  = (string) ($_POST['jour_semaine'] ?? $seance['jour_semaine']);
    $debut = (string) ($_POST['heure_debut'] ?? $seance['heure_debut']);
    $fin   = (string) ($_POST['heure_fin'] ?? $seance['heure_fin']);

    $motif = edtValiderCreneau($jour, $debut, $fin);
    if ($motif !== null) { edtRepondre(['success' => false, 'error' => $motif], 422); }

    $salleId = (int) ($_POST['salle_id'] ?? 0);
    $prof    = (int) ($_POST['id_teacher'] ?? 0);
    $parcours = (string) ($_POST['parcours'] ?? ($seance['parcours'] ?? ''));

    $creneau = [
        'jour' => $jour, 'debut' => $debut, 'fin' => $fin,
        'salle_id' => $salleId, 'id_teacher' => $prof,
        'mention' => $seance['mention'], 'niveau' => (string) $seance['niveau'],
        'parcours' => $parcours,
        'type_seance' => (string) ($_POST['type_seance'] ?? $seance['type_seance']),
        'semester' => (int) $seance['semester'], 'cours_id' => (int) $seance['cours_id'],
        'annee_scolaire' => (string) $seance['annee_scolaire'],
    ];

    [$permis, $conflits] = edtAutorise($dtb, $creneau, $id, ($_POST['force'] ?? '0') === '1');
    if (!$permis) {
        edtRepondre(['success' => false, 'error' => 'Conflits détectés',
                     'conflicts' => $conflits, 'forcable' => !edtBloquant($conflits)], 409);
    }

    $salleCode = '';
    if ($salleId > 0) {
        $s = $dtb->prepare("SELECT salle_code FROM t_2024_salles WHERE id = ?");
        $s->execute([$salleId]);
        $salleCode = (string) ($s->fetchColumn() ?: '');
    }

    $up = $dtb->prepare(
        "UPDATE t_2024_emploi_du_temps SET
            type_seance = :type, jour_semaine = :jour,
            heure_debut = :debut, heure_fin = :fin, duree_minutes = :duree,
            salle_id = :salle_id, salle_code = :salle_code, id_teacher = :prof,
            parcours = :parcours, remarque = :remarque, updated_at = :maj
         WHERE id = :id");
    $up->execute([
        ':type' => $creneau['type_seance'], ':jour' => $jour,
        ':debut' => $debut, ':fin' => $fin,
        ':duree' => edtMinutes($fin) - edtMinutes($debut),
        ':salle_id' => $salleId ?: null, ':salle_code' => $salleCode,
        ':prof' => $prof ?: null, ':parcours' => $parcours,
        ':remarque' => (string) ($_POST['remarque'] ?? ''),
        ':maj' => edtHorodatage(),
        ':id' => $id,
    ]);

    edtRepondre(['success' => true, 'conflicts' => $conflits]);
}

/**
 * Deplacement par glisser-deposer.
 *
 * L'ancienne version recalculait l'heure de fin ainsi :
 *     intval(substr($debut, 0, 2)) + duree  ->  sprintf('%02d:00')
 * Une seance de 08:30 a 10:30 deposee ailleurs devenait 08:30 – 10:00 : elle
 * perdait trente minutes a chaque deplacement. On conserve desormais la duree
 * reelle, en minutes.
 */
function edtMoveSeance(PDO $dtb, string $annee) : void
{
    $id = (int) ($_POST['id'] ?? 0);
    $st = $dtb->prepare("SELECT * FROM t_2024_emploi_du_temps WHERE id = ?");
    $st->execute([$id]);
    $seance = $st->fetch(PDO::FETCH_ASSOC);
    if (!$seance) { edtRepondre(['success' => false, 'error' => 'Séance non trouvée'], 404); }

    $jour  = (string) ($_POST['jour_semaine'] ?? '');
    $debut = (string) ($_POST['heure_debut'] ?? '');

    $debutMin = edtMinutes($debut);
    if ($debutMin === null) { edtRepondre(['success' => false, 'error' => 'Horaire invalide.'], 422); }

    $duree = (int) ($seance['duree_minutes'] ?? 0);
    if ($duree <= 0) {
        $duree = (edtMinutes($seance['heure_fin']) ?? 0) - (edtMinutes($seance['heure_debut']) ?? 0);
    }
    if ($duree <= 0) { $duree = 120; }

    $fin = edtHeure($debutMin + $duree);

    $motif = edtValiderCreneau($jour, $debut, $fin);
    if ($motif !== null) { edtRepondre(['success' => false, 'error' => $motif], 422); }

    $creneau = [
        'jour' => $jour, 'debut' => $debut, 'fin' => $fin,
        'salle_id' => (int) ($seance['salle_id'] ?? 0),
        'id_teacher' => (int) ($seance['id_teacher'] ?? 0),
        'mention' => $seance['mention'], 'niveau' => (string) $seance['niveau'],
        'parcours' => (string) ($seance['parcours'] ?? ''),
        'type_seance' => (string) $seance['type_seance'],
        'semester' => (int) $seance['semester'], 'cours_id' => (int) $seance['cours_id'],
        'annee_scolaire' => (string) $seance['annee_scolaire'],
    ];

    [$permis, $conflits] = edtAutorise($dtb, $creneau, $id, ($_POST['force'] ?? '0') === '1');
    if (!$permis) {
        edtRepondre(['success' => false, 'error' => 'Conflits détectés',
                     'conflicts' => $conflits, 'forcable' => !edtBloquant($conflits)], 409);
    }

    $up = $dtb->prepare(
        "UPDATE t_2024_emploi_du_temps
         SET jour_semaine = :jour, heure_debut = :debut, heure_fin = :fin,
             duree_minutes = :duree, updated_at = :maj
         WHERE id = :id");
    $up->execute([':jour' => $jour, ':debut' => $debut, ':fin' => $fin,
                  ':duree' => $duree, ':maj' => edtHorodatage(), ':id' => $id]);

    edtRepondre(['success' => true, 'heure_debut' => $debut, 'heure_fin' => $fin,
                 'conflicts' => $conflits]);
}

function edtDeleteSeance(PDO $dtb) : void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) { edtRepondre(['success' => false, 'error' => 'Séance non précisée.'], 422); }

    $st = $dtb->prepare("DELETE FROM t_2024_emploi_du_temps WHERE id = ?");
    $st->execute([$id]);

    if ($st->rowCount() === 0) {
        edtRepondre(['success' => false, 'error' => 'Séance non trouvée'], 404);
    }
    edtRepondre(['success' => true]);
}


/* =============================================================================
   Salles

   Rien ne permettait de les gerer depuis l'application : ajouter une salle,
   corriger sa capacite ou la mettre hors service passait par la base. Or la
   capacite et la disponibilite commandent desormais la detection des conflits
   (data/edt.php). Pas de suppression : des seances, y compris d'annees
   passees, pointent vers la salle ; « indisponible » la retire des choix sans
   rien casser. La description des colonnes (edtColonnesSalles) est dans
   data/edt.php : la page s'en sert aussi pour proposer les types de salle.
   ========================================================================== */

function edtEnregistrerSalle(PDO $dtb, bool $creation, string $annee) : void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$creation && $id <= 0) {
        edtRepondre(['success' => false, 'error' => 'Salle non précisée.'], 422);
    }

    $colonnes = edtColonnesSalles($dtb);
    $libelles = ['salle_code' => 'Code', 'salle_nom' => 'Nom', 'type_salle' => 'Type',
                 'batiment' => 'Bâtiment', 'etage' => 'Étage', 'equipements' => 'Équipements'];
    $refus = static fn (string $message) => edtRepondre(['success' => false, 'error' => $message], 422);

    $valeurs = [];
    foreach ($libelles as $champ => $libelle) {
        if (!isset($colonnes[$champ])) { continue; }
        // Espaces repetes et retours a la ligne ramenes a un espace.
        $v = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST[$champ] ?? '')));
        $col = $colonnes[$champ];
        if (in_array($col['type'], ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'], true)) {
            if ($v !== '' && !preg_match('/^-?\d{1,4}$/', $v)) { $refus($libelle . ' : un nombre entier est attendu.'); }
            $valeurs[$champ] = $v === '' ? null : (int) $v;
            continue;
        }
        // Pour une ENUM, la « longueur maximale » est celle de la plus longue valeur.
        if ($col['enum'] !== null && $v !== '' && !in_array($v, $col['enum'], true)) {
            $refus($libelle . ' : valeur inconnue.');
        }
        if ($col['enum'] === null && $col['max'] !== null && mb_strlen($v) > $col['max']) {
            $refus($libelle . ' : ' . $col['max'] . ' caractères au plus.');
        }
        if ($v === '' && $col['null']) { $valeurs[$champ] = null; continue; }
        if ($v === '' && $col['enum'] !== null) { $refus($libelle . ' : choisissez une valeur.'); }
        $valeurs[$champ] = $v;
    }

    if (($valeurs['salle_code'] ?? '') === '') { $refus('Le code de la salle est obligatoire.'); }
    if (array_key_exists('salle_nom', $valeurs) && ($valeurs['salle_nom'] ?? '') === '') { $valeurs['salle_nom'] = $valeurs['salle_code']; }

    $capacite = trim((string) ($_POST['capacite'] ?? ''));
    if (!preg_match('/^\d{1,4}$/', $capacite) || (int) $capacite > 2000) {
        $refus('Capacité : un nombre de places entre 0 et 2000.');
    }
    $valeurs['capacite'] = (int) $capacite;
    $valeurs['disponible'] = ((string) ($_POST['disponible'] ?? '0')) === '1' ? 1 : 0;

    /* Deux salles de meme code seraient indiscernables dans les listes et
       dans les messages de conflit. */
    $st = $dtb->prepare('SELECT id, salle_nom FROM t_2024_salles WHERE LOWER(salle_code) = LOWER(?) AND id <> ? LIMIT 1');
    $st->execute([$valeurs['salle_code'], $creation ? 0 : $id]);
    if ($autre = $st->fetch(PDO::FETCH_ASSOC)) {
        edtRepondre(['success' => false, 'error' => 'Le code « ' . $valeurs['salle_code'] . ' » est déjà pris par la salle ' . $autre['salle_nom'] . '.'], 409);
    }

    if (!$creation) {
        $st = $dtb->prepare('SELECT disponible FROM t_2024_salles WHERE id = ?');
        $st->execute([$id]);
        $avant = $st->fetchColumn();
        if ($avant === false) { edtRepondre(['success' => false, 'error' => 'Salle introuvable.'], 404); }
    }

    /* Horodatage explicite, a l'heure de Madagascar : les valeurs par defaut
       de la table (CURRENT_TIMESTAMP) suivraient MySQL, en UTC en production. */
    $maintenant = edtHorodatage();
    if ($creation && isset($colonnes['created_at'])) { $valeurs['created_at'] = $maintenant; }
    if (isset($colonnes['updated_at'])) { $valeurs['updated_at'] = $maintenant; }

    if ($creation) {
        $noms = array_keys($valeurs);
        $sql = 'INSERT INTO t_2024_salles (' . implode(', ', $noms) . ') VALUES ('
             . implode(', ', array_fill(0, count($noms), '?')) . ')';
        $dtb->prepare($sql)->execute(array_values($valeurs));
        $id = (int) $dtb->lastInsertId();
    } else {
        $sets = array_map(fn ($c) => $c . ' = ?', array_keys($valeurs));
        $dtb->prepare('UPDATE t_2024_salles SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute(array_merge(array_values($valeurs), [$id]));
    }

    /* Mettre hors service une salle ou des seances sont encore placees : on
       le permet (travaux imprevus), mais on dit combien sont a deplacer. */
    $aDeplacer = 0;
    if ($valeurs['disponible'] === 0) {
        $st = $dtb->prepare("SELECT COUNT(*) FROM t_2024_emploi_du_temps
                             WHERE salle_id = ? AND statut = 'confirme' AND REPLACE(annee_scolaire, ' ', '') = REPLACE(?, ' ', '')");
        $st->execute([$id, $annee]);
        $aDeplacer = (int) $st->fetchColumn();
    }

    edtRepondre(['success' => true, 'id' => $id, 'a_deplacer' => $aDeplacer]);
}
