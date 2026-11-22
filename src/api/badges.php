<?php
/**
 * API : étudiants pour l'aperçu des badges en groupe (lecture seule)
 * Endpoint : GET /src/api/badges.php
 *
 * Paramètres :
 *   session     identifiant de session (t_2023_session.session_id), obligatoire
 *               sauf avec matricules
 *   mention     sigle de mention (filiere_sigle) ; vide = toutes
 *   niveau      1 à 5 ; vide ou TOUT = tous. Niveau de l'inscription à la
 *               session, à défaut celui de la fiche (même règle que deans-list)
 *   inscrits    « nouveaux » (défaut : nouveaux inscrits seulement, comme
 *               l'ancien export) ou « tous »
 *   matricules  un ou plusieurs matricules (espaces, virgules, retours à la
 *               ligne) : remplace les autres filtres
 *
 * Accès : utilisateur connecté de niveau 3 ou moins (comme le bouton Badges).
 */

error_reporting(0);
ini_set('display_errors', 0);
while (ob_get_level()) ob_end_clean();

require_once(__DIR__ . '/../../data/guard.ajax.php');
exigerConnexion(3);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$reponse = function ($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

/* Prénom limité aux deux premiers mots, comme sur les badges imprimés. */
$prenomCourt = function ($p) {
    $mots = preg_split('/\s+/', trim((string) $p), -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', array_slice($mots, 0, 2));
};
$formater = function (array $r) use ($prenomCourt) {
    return [
        'student_id'     => (string) $r['student_id'],
        'student_nom'    => (string) $r['student_nom'],
        'student_prenom' => $prenomCourt($r['student_prenom']),
        'etude_envisage' => (string) $r['etude_envisage'],
        'status'         => (string) $r['status'],
        'abonment'       => (string) $r['abonment'],
        'lookup_code'    => (string) $r['lookup_code'],
        'image_student'  => (string) $r['image_student'],
        'niveau'         => (int) $r['niveau'],
        'nouveau'        => (int) ($r['nouveau'] ?? 0),
    ];
};

$colonnes = 'e.student_id, e.student_nom, e.student_prenom, e.etude_envisage, e.status, e.abonment, e.lookup_code, e.image_student';

try {
    /* ---- Matricules saisis : la fiche de chaque étudiant, dans l'ordre saisi ---- */
    $saisie = trim((string) ($_GET['matricules'] ?? ''));
    if ($saisie !== '') {
        $ids = array_values(array_unique(preg_split('/[\s,;]+/', $saisie, -1, PREG_SPLIT_NO_EMPTY)));
        if (count($ids) > 500) {
            $reponse(400, ['success' => false, 'message' => '500 matricules au maximum.']);
        }
        $st = $dtb->prepare("SELECT $colonnes, e.annee_etude AS niveau, 0 AS nouveau
                             FROM tbl_2024_etudiant e WHERE e.student_id = ?
                             ORDER BY e.remove ASC, e.id ASC LIMIT 1");
        $etudiants = []; $introuvables = [];
        foreach ($ids as $id) {
            $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) { $etudiants[] = $formater($r); } else { $introuvables[] = $id; }
        }
        $reponse(200, ['success' => true, 'mode' => 'matricules', 'etudiants' => $etudiants, 'introuvables' => $introuvables]);
    }

    /* ---- Filtres : inscrits à une session ---- */
    $session = (int) ($_GET['session'] ?? 0);
    if ($session <= 0) {
        $reponse(400, ['success' => false, 'message' => 'Session manquante.']);
    }
    $where = ['i.session_id = :session', 'COALESCE(i.`remove`, 0) != 1'];
    $params = [':session' => $session];

    $mention = trim((string) ($_GET['mention'] ?? ''));
    if ($mention !== '') { $where[] = 'i.etude_mention = :mention'; $params[':mention'] = $mention; }

    $niveau = (string) ($_GET['niveau'] ?? '');
    if (in_array($niveau, ['1', '2', '3', '4', '5'], true)) {
        $where[] = 'COALESCE(NULLIF(i.niveau_std, 0), e.annee_etude) = :niveau';
        $params[':niveau'] = (int) $niveau;
    }
    if (($_GET['inscrits'] ?? 'nouveaux') !== 'tous') { $where[] = 'i.new_student = 1'; }

    // Une seule fiche par matricule (la première non supprimée, comme ailleurs)
    $where[] = 'e.id = (SELECT e2.id FROM tbl_2024_etudiant e2 WHERE e2.student_id = e.student_id ORDER BY e2.remove ASC, e2.id ASC LIMIT 1)';

    $sql = "SELECT $colonnes,
                   COALESCE(NULLIF(i.niveau_std, 0), e.annee_etude) AS niveau,
                   i.new_student AS nouveau
            FROM tbl_2024_etudiant e
            INNER JOIN t_2024_inscription_session i ON i.student_id = e.student_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.student_nom, e.student_prenom, e.student_id";
    $st = $dtb->prepare($sql);
    $st->execute($params);

    $etudiants = []; $vus = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        if (isset($vus[$r['student_id']])) continue; // plusieurs inscriptions à la même session
        $vus[$r['student_id']] = true;
        $etudiants[] = $formater($r);
    }
    $reponse(200, ['success' => true, 'mode' => 'filtres', 'etudiants' => $etudiants, 'introuvables' => []]);
} catch (Throwable $e) {
    error_log('api/badges : ' . $e->getMessage());
    $reponse(500, ['success' => false, 'message' => 'Erreur lors de la lecture des étudiants.']);
}
