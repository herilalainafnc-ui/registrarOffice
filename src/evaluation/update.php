<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Méthode non autorisée.");
}

$user = Middleware::getCurrentUser();
if (($user['level'] ?? 10) > 3) {
    die("Accès refusé.");
}

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$targetRole = $_POST['target_role'] ?? 'all';
$endDateRaw = $_POST['end_date'] ?? '';
$endDate = !empty($endDateRaw) ? date('Y-m-d H:i:s', strtotime($endDateRaw)) : null;
$isPublished = !empty($_POST['is_published']) ? 1 : 0;
$questions = $_POST['questions'] ?? [];

if ($formId <= 0) {
    die("Formulaire invalide.");
}

if ($title === '') {
    die("Le titre est obligatoire.");
}

$stmtForm = $dtb->prepare("SELECT id FROM rg_eval_forms WHERE id = ?");
$stmtForm->execute([$formId]);
if (!$stmtForm->fetchColumn()) {
    die("Formulaire introuvable.");
}

$dtb->beginTransaction();
try {
    $stmtUpdate = $dtb->prepare("UPDATE rg_eval_forms SET title = ?, description = ?, is_published = ?, end_date = ?, target_role = ? WHERE id = ?");
    $stmtUpdate->execute([$title, $description, $isPublished, $endDate, $targetRole, $formId]);

    $stmtQuestionIds = $dtb->prepare("SELECT id FROM rg_eval_questions WHERE form_id = ?");
    $stmtQuestionIds->execute([$formId]);
    $questionIds = $stmtQuestionIds->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($questionIds)) {
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $stmtDeleteOptions = $dtb->prepare("DELETE FROM rg_eval_options WHERE question_id IN ($placeholders)");
        $stmtDeleteOptions->execute($questionIds);
    }

    $stmtDeleteQuestions = $dtb->prepare("DELETE FROM rg_eval_questions WHERE form_id = ?");
    $stmtDeleteQuestions->execute([$formId]);

    $stmtInsertQuestion = $dtb->prepare("INSERT INTO rg_eval_questions (form_id, question_text, question_type, is_required, display_order) VALUES (?, ?, ?, ?, ?)");
    $stmtInsertOption = $dtb->prepare("INSERT INTO rg_eval_options (question_id, option_text, display_order) VALUES (?, ?, ?)");

    $order = 1;
    foreach ($questions as $question) {
        $questionText = trim($question['text'] ?? '');
        $questionType = $question['type'] ?? 'short';
        $questionRequired = !empty($question['required']) ? 1 : 0;

        if ($questionText === '') {
            continue;
        }

        $stmtInsertQuestion->execute([$formId, $questionText, $questionType, $questionRequired, $order]);
        $questionId = (int)$dtb->lastInsertId();

        if (in_array($questionType, ['multiple_choice', 'checkbox', 'dropdown'], true)) {
            $optionOrder = 1;
            foreach (($question['options'] ?? []) as $optionText) {
                $optionText = trim($optionText);
                if ($optionText === '') {
                    continue;
                }

                $stmtInsertOption->execute([$questionId, $optionText, $optionOrder]);
                $optionOrder++;
            }
        }

        $order++;
    }

    $dtb->commit();
    header('Location: ' . $app_base . '/evaluation/admin');
    exit;
} catch (Throwable $th) {
    $dtb->rollBack();
    die("Erreur interne lors de la mise à jour : " . $th->getMessage());
}