<?php
/**
 * ApiController - Points d'entrée API (AJAX, services)
 * Routes: /api/google-auth, /api/schedule, /api/services/*, /api/data, /api/update-authorization
 */
class ApiController extends BaseController {

    public function googleAuth() {
        $this->renderSrc('api/google-auth.php');
    }

    public function schedule() {
        $this->renderSrc('emploi-temps.api.php');
    }

    public function documentVerification() {
        $this->renderSrc('services/DocumentVerification.php');
    }

    public function matriculeLive() {
        $this->renderSrc('services/matricule.live.php');
    }

    public function parcoursLive() {
        $this->renderSrc('services/parcours.live.php');
    }

    public function parcoursAddCours() {
        $this->renderSrc('services/parcours.live.addCours.php');
    }

    public function data() {
        $this->render('data/data.php', ROOT_DIR . '/data');
    }

    public function updateAuthorization() {
        // Logique API directe (pas de renderSrc pour éviter les conflits d'output)
        error_reporting(0);
        ini_set('display_errors', '0');
        while (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        try {
            require_once ROOT_DIR . '/data/backdb.php';
            require_once ROOT_DIR . '/data/middleware.php';
            initMiddleware($dtb);

            if (!isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Non authentifié']);
                exit;
            }

            $user = currentUser();
            if (!$user || empty($user['id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
                exit;
            }

            // Level 1=superadmin, 2=admin, 4=comptabilité (caissier)
            $user_level = (int)($user['level'] ?? 99);
            if (!in_array($user_level, [1, 2, 4])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès refusé']);
                exit;
            }

            $student_id = $_POST['student_id'] ?? null;
            $authorized = isset($_POST['authorized_reinscription']) ? (int)$_POST['authorized_reinscription'] : 0;

            if (empty($student_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID étudiant manquant']);
                exit;
            }

            $stmt = $dtb->prepare("SELECT id FROM tbl_2024_etudiant WHERE id = :id");
            $stmt->execute(['id' => $student_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
                exit;
            }

            $updateStmt = $dtb->prepare("
                UPDATE tbl_2024_etudiant 
                SET authorized_reinscription = :authorized,
                    authorized_by = :authorized_by,
                    authorized_date = NOW()
                WHERE id = :id
            ");
            $result = $updateStmt->execute([
                'authorized' => $authorized,
                'authorized_by' => $user['id'],
                'id' => $student_id
            ]);

            // Récupérer le nom du modificateur et la date pour mise à jour UI
            $modifier_name = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
            $now = date('d/m/Y H:i');

            echo json_encode([
                'success' => $result,
                'message' => $result 
                    ? ($authorized ? 'Étudiant autorisé pour la réinscription' : 'Autorisation retirée')
                    : 'Erreur lors de la mise à jour',
                'authorized' => $authorized,
                'authorized_by_name' => $modifier_name,
                'authorized_date' => $now
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
        exit;
    }

    public function updateDossier() {
        error_reporting(0);
        ini_set('display_errors', '0');
        while (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        try {
            require_once ROOT_DIR . '/data/backdb.php';
            require_once ROOT_DIR . '/data/middleware.php';
            initMiddleware($dtb);

            if (!isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Non authentifié']);
                exit;
            }

            $user = currentUser();
            if (!$user || empty($user['id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
                exit;
            }

            // Seuls superadmin (1) et registraire (3) peuvent modifier le dossier
            $user_level = (int)($user['level'] ?? 99);
            if (!in_array($user_level, [1, 3])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès refusé. Seuls le superadmin et le registraire peuvent modifier le statut du dossier.']);
                exit;
            }

            $student_id = $_POST['student_id'] ?? null;
            $dossier_ok = isset($_POST['dossier_ok']) ? (int)$_POST['dossier_ok'] : 0;

            if (empty($student_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID étudiant manquant']);
                exit;
            }

            $stmt = $dtb->prepare("SELECT id FROM tbl_2024_etudiant WHERE id = :id");
            $stmt->execute(['id' => $student_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
                exit;
            }

            $updateStmt = $dtb->prepare("
                UPDATE tbl_2024_etudiant 
                SET dossier_ok = :dossier_ok,
                    dossier_checked_by = :checked_by,
                    dossier_checked_date = NOW()
                WHERE id = :id
            ");
            $result = $updateStmt->execute([
                'dossier_ok' => $dossier_ok,
                'checked_by' => $user['id'],
                'id' => $student_id
            ]);

            $modifier_name = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
            $now = date('d/m/Y H:i');

            echo json_encode([
                'success' => $result,
                'message' => $result 
                    ? ($dossier_ok ? 'Dossier validé par le registraire' : 'Validation du dossier retirée')
                    : 'Erreur lors de la mise à jour',
                'dossier_ok' => $dossier_ok,
                'dossier_checked_by_name' => $modifier_name,
                'dossier_checked_date' => $now
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
        exit;
    }
}
