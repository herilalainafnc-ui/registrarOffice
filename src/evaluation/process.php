<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

function ensureRespondentEmailColumn(PDO $dtb): void {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $existsStmt = $dtb->query("SHOW COLUMNS FROM rg_eval_responses LIKE 'respondent_email'");
    $exists = $existsStmt ? $existsStmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$exists) {
        $dtb->exec("ALTER TABLE rg_eval_responses ADD COLUMN respondent_email VARCHAR(255) NULL AFTER user_id");
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Méthode non autorisée.");
}

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = Middleware::getCurrentUser();
$userId = $user['id'] ?? 0;

if ($formId <= 0) {
    die("Formulaire invalide.");
}

$stmtForm = $dtb->prepare("SELECT id, is_published FROM rg_eval_forms WHERE id = ? LIMIT 1");
$stmtForm->execute([$formId]);
$form = $stmtForm->fetch(PDO::FETCH_ASSOC);
if (!$form || (int)$form['is_published'] !== 1) {
    die("Formulaire indisponible.");
}

$respondentEmail = null;
$isGuest = empty($userId);

if ($isGuest) {
    try {
        ensureRespondentEmailColumn($dtb);
    } catch (Throwable $th) {
        die("Configuration incomplète: impossible d'activer la réponse invité par e-mail.");
    }

    $respondentEmail = strtolower(trim($_POST['respondent_email'] ?? ''));
    if ($respondentEmail === '' || !filter_var($respondentEmail, FILTER_VALIDATE_EMAIL)) {
        die("Veuillez saisir une adresse email valide.");
    }
}

// Check if already submitted & limit is 1
$alreadySubmitted = false;
if ($isGuest) {
    $stmtCheck = $dtb->prepare("SELECT id FROM rg_eval_responses WHERE form_id = ? AND LOWER(respondent_email) = ? LIMIT 1");
    $stmtCheck->execute([$formId, $respondentEmail]);
    $alreadySubmitted = (bool)$stmtCheck->fetchColumn();
} else {
    $stmtCheck = $dtb->prepare("SELECT id FROM rg_eval_responses WHERE form_id = ? AND user_id = ? LIMIT 1");
    $stmtCheck->execute([$formId, $userId]);
    $alreadySubmitted = (bool)$stmtCheck->fetchColumn();
}

$saveOk = false;
if (!$alreadySubmitted) {
    // Insert response
    $dtb->beginTransaction();
    try {
        $effectiveUserId = $isGuest ? 0 : (int)$userId;
        $stmtResp = $dtb->prepare("INSERT INTO rg_eval_responses (form_id, user_id, respondent_email) VALUES (?, ?, ?)");
        $stmtResp->execute([$formId, $effectiveUserId, $respondentEmail]);
        $responseId = $dtb->lastInsertId();

        // Extraire toutes les questions du form pour associer
        $stmtQ = $dtb->prepare("SELECT id, question_type, is_required FROM rg_eval_questions WHERE form_id = ?");
        $stmtQ->execute([$formId]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        $stmtAns = $dtb->prepare("INSERT INTO rg_eval_answers (response_id, question_id, answer_text, answer_number) VALUES (?, ?, ?, ?)");
        $stmtAnsOpt = null;
        try {
            $stmtAnsOpt = $dtb->prepare("INSERT INTO rg_eval_answer_options (answer_id, option_id) VALUES (?, ?)");
        } catch (Throwable $th) {
            $stmtAnsOpt = null;
        }
        
        foreach ($questions as $q) {
            $qid = $q['id'];
            $postKey = 'q_' . $qid;
            $isRequired = !empty($q['is_required']);

            if ($q['question_type'] === 'checkbox') {
                $selected = $_POST[$postKey] ?? [];
                if (!is_array($selected)) {
                    $selected = [$selected];
                }

                $selected = array_values(array_unique(array_filter(array_map('intval', $selected), function ($id) {
                    return $id > 0;
                })));

                if ($isRequired && empty($selected)) {
                    throw new RuntimeException('Question obligatoire non remplie.');
                }

                if (!empty($selected)) {
                    $stmtAns->execute([$responseId, $qid, implode(',', $selected), null]);
                    $answerId = (int)$dtb->lastInsertId();

                    if ($stmtAnsOpt) {
                        foreach ($selected as $optionId) {
                            $stmtAnsOpt->execute([$answerId, $optionId]);
                        }
                    }
                }
                continue;
            }

            if ($isRequired && !isset($_POST[$postKey])) {
                throw new RuntimeException('Question obligatoire non remplie.');
            }

            if (isset($_POST[$postKey])) {
                $val = $_POST[$postKey];

                $ansText = null;
                $ansNum = null;

                if ($q['question_type'] == 'multiple_choice' || $q['question_type'] == 'rating') {
                    $ansNum = (int)$val;
                } else {
                    $ansText = $val;
                }

                $stmtAns->execute([$responseId, $qid, $ansText, $ansNum]);
            }
        }

        $dtb->commit();
        $saveOk = true;
    } catch (\Throwable $th) {
        $dtb->rollBack();
        die("Erreur interne lors de la sauvegarde.");
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $allow_public_page = true; ?>
    <?php require('../init/head.php');?>
    <title><?=$saveOk ? 'Sondage terminé' : 'Déjà répondu'?></title>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-gray-800 p-8 rounded-lg border border-gray-700 shadow-xl text-center">
        <?php if ($saveOk): ?>
            <i class="bi bi-check-circle-fill text-6xl text-green-500 mb-4 block"></i>
            <h1 class="text-2xl font-bold mb-2">Merci pour votre participation !</h1>
            <p class="text-gray-400 mb-6">Votre réponse a bien été enregistrée.</p>
        <?php else: ?>
            <i class="bi bi-exclamation-triangle-fill text-6xl text-yellow-500 mb-4 block"></i>
            <h1 class="text-2xl font-bold mb-2">Réponse déjà enregistrée</h1>
            <p class="text-gray-400 mb-6">
                <?php if ($isGuest): ?>
                    Cette adresse email a déjà répondu à ce questionnaire.
                <?php else: ?>
                    Votre compte a déjà répondu à ce questionnaire.
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <a href="<?=$app_base?>/evaluation" class="text-blue-400 hover:underline">Retour à la liste des évaluations</a>
    </div>
</body>
</html>