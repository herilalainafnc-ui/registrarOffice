<?php
// MVC base path
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_ar = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
$app_base = substr($_ar, strlen($_dr)) ?: '';

require('../../data/backdb.php');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$student_id = isset($_GET['student_id']) ? trim((string)$_GET['student_id']) : '';
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$yearlevel = isset($_GET['yearlevel']) ? trim((string)$_GET['yearlevel']) : '';
$semester = isset($_GET['semester']) ? trim((string)$_GET['semester']) : '';
$annee_scolaire = isset($_GET['annee_scolaire']) ? trim((string)$_GET['annee_scolaire']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$grade_work_educ = isset($_POST['grade_work_educ']) ? trim((string)$_POST['grade_work_educ']) : '';
$grade_chapel_part = isset($_POST['grade_chapel_part']) ? trim((string)$_POST['grade_chapel_part']) : '';

function respondPromotion(array $payload, bool $isAjax, string $app_base, int $course_id): void {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($payload);
    } else {
        $target = $app_base . '/cours?id=' . $course_id . '&page=promotion-notes';
        if (!empty($payload['success'])) {
            header('Location: ' . $target . '&status=ok');
        } else {
            header('Location: ' . $target . '&error=1');
        }
    }
    exit;
}

function normalizePromotionNote(string $value): array {
    $v = str_replace(',', '.', trim($value));
    if ($v === '') {
        return ['ok' => true, 'value' => ''];
    }

    if (!is_numeric($v)) {
        return ['ok' => false, 'message' => 'La note doit etre un nombre valide.'];
    }

    $n = (float)$v;
    if ($n < 0) {
        return ['ok' => false, 'message' => 'La note ne peut pas etre negative.'];
    }
    if ($n > 20) {
        return ['ok' => false, 'message' => 'La note ne peut pas depasser 20.'];
    }

    return ['ok' => true, 'value' => (string)(round($n, 2))];
}

if ($course_id <= 0 || $session_id <= 0 || $student_id === '') {
    respondPromotion(['success' => false, 'message' => 'Parametres invalides.'], $isAjax, $app_base, $course_id);
}

$checkWork = normalizePromotionNote($grade_work_educ);
if (!$checkWork['ok']) {
    respondPromotion(['success' => false, 'message' => $checkWork['message']], $isAjax, $app_base, $course_id);
}

$checkChapel = normalizePromotionNote($grade_chapel_part);
if (!$checkChapel['ok']) {
    respondPromotion(['success' => false, 'message' => $checkChapel['message']], $isAjax, $app_base, $course_id);
}

$grade_work_educ = $checkWork['value'];
$grade_chapel_part = $checkChapel['value'];
$today = date('Y-m-d');

$search = $dtb->prepare('SELECT id, grade_remark_acad FROM t_2023_promotion_notes WHERE student_id = :student_id AND session_id = :session_id LIMIT 1');
$search->execute([
    'student_id' => $student_id,
    'session_id' => $session_id
]);
$existing = $search->fetch(PDO::FETCH_ASSOC);

if (!empty($existing)) {
    $update = $dtb->prepare('UPDATE t_2023_promotion_notes
        SET grade_work_educ = :grade_work_educ,
            grade_chapel_part = :grade_chapel_part,
            last_change_user_id = :last_change_user_id,
            last_change_datetime = :last_change_datetime
        WHERE id = :id');

    $ok = $update->execute([
        'grade_work_educ' => $grade_work_educ,
        'grade_chapel_part' => $grade_chapel_part,
        'last_change_user_id' => $user_id,
        'last_change_datetime' => $today,
        'id' => (int)$existing['id']
    ]);

    if (!$ok) {
        respondPromotion(['success' => false, 'message' => 'Echec lors de la mise a jour.'], $isAjax, $app_base, $course_id);
    }
} else {
    $insert = $dtb->prepare('INSERT INTO t_2023_promotion_notes(
        student_id,
        session_id,
        semester,
        yearlevel,
        annee_scolaire,
        grade_work_educ,
        grade_chapel_part,
        grade_remark_acad,
        date_entry,
        last_change_user_id
    ) VALUES (
        :student_id,
        :session_id,
        :semester,
        :yearlevel,
        :annee_scolaire,
        :grade_work_educ,
        :grade_chapel_part,
        :grade_remark_acad,
        :date_entry,
        :last_change_user_id
    )');

    $ok = $insert->execute([
        'student_id' => $student_id,
        'session_id' => $session_id,
        'semester' => $semester,
        'yearlevel' => $yearlevel,
        'annee_scolaire' => $annee_scolaire,
        'grade_work_educ' => $grade_work_educ,
        'grade_chapel_part' => $grade_chapel_part,
        'grade_remark_acad' => '',
        'date_entry' => $today,
        'last_change_user_id' => $user_id
    ]);

    if (!$ok) {
        respondPromotion(['success' => false, 'message' => 'Echec lors de linsertion.'], $isAjax, $app_base, $course_id);
    }
}

respondPromotion(['success' => true, 'message' => 'Notes de promotion enregistrees.'], $isAjax, $app_base, $course_id);
