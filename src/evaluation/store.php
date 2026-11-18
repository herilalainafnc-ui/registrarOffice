<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Méthode non autorisée.");
}

$user = Middleware::getCurrentUser();
if ($user['level'] > 3) {
    die("Accès refusé.");
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$target_role = $_POST['target_role'] ?? 'all';
$end_date_raw = $_POST['end_date'] ?? '';
$end_date = !empty($end_date_raw) ? date('Y-m-d H:i:s', strtotime($end_date_raw)) : null;
$is_published = !empty($_POST['is_published']) ? 1 : 0;
$creator_id = $user['id'];

$questions = $_POST['questions'] ?? [];

if (empty($title)) {
    die("Le titre est obligatoire.");
}

$dtb->beginTransaction();
try {
    // 1. Insert form
    $stmt = $dtb->prepare("
        INSERT INTO rg_eval_forms (title, description, is_published, end_date, target_role, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description, $is_published, $end_date, $target_role, $creator_id]);
    $formId = $dtb->lastInsertId();

    // 2. Insert questions
    $order = 1;
    $stmtQ = $dtb->prepare("
        INSERT INTO rg_eval_questions (form_id, question_text, question_type, is_required, display_order)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtO = $dtb->prepare("
        INSERT INTO rg_eval_options (question_id, option_text, display_order)
        VALUES (?, ?, ?)
    ");

    foreach ($questions as $q) {
        $qText = trim($q['text'] ?? '');
        $qType = $q['type'] ?? 'short';
        $isRequired = !empty($q['required']) ? 1 : 0;
        
        if (empty($qText)) continue;

        $stmtQ->execute([$formId, $qText, $qType, $isRequired, $order]);
        $questionId = $dtb->lastInsertId();

        // 3. Insert options if applicable
        if (in_array($qType, ['multiple_choice', 'checkbox', 'dropdown'])) {
            $options = $q['options'] ?? [];
            $optOrder = 1;
            foreach ($options as $optText) {
                $optText = trim($optText);
                if (!empty($optText)) {
                    $stmtO->execute([$questionId, $optText, $optOrder]);
                    $optOrder++;
                }
            }
        }
        $order++;
    }

    $dtb->commit();
    header("Location: " . $app_base . "/evaluation/admin");
    exit;

} catch (\Throwable $th) {
    $dtb->rollBack();
    die("Erreur interne lors de la création : " . $th->getMessage());
}
