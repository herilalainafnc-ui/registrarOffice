<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$user = Middleware::getCurrentUser();
if (($user['level'] ?? 10) > 3) {
    die("Accès refusé.");
}

$formId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmtForm = $dtb->prepare("SELECT * FROM rg_eval_forms WHERE id = ?");
$stmtForm->execute([$formId]);
$form = $stmtForm->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("Formulaire introuvable.");
}

$courseTitle = '';
$teacherFullName = '';
if (!empty($form['cours_id'])) {
    $stmtCourse = $dtb->prepare("SELECT c.title, t.name, t.lastName FROM t_2023_cours c LEFT JOIN teacher t ON t.uid = c.id_teacher WHERE c.id = ? LIMIT 1");
    $stmtCourse->execute([(int)$form['cours_id']]);
    $courseRow = $stmtCourse->fetch(PDO::FETCH_ASSOC) ?: [];
    $courseTitle = trim((string)($courseRow['title'] ?? ''));
    $teacherFullName = trim((string)($courseRow['name'] ?? '') . ' ' . (string)($courseRow['lastName'] ?? ''));
}

$evaluationTitle = trim((string)($form['title'] ?? ''));
$evaluationSubtitle = trim(implode(' | ', array_filter([
    $courseTitle !== '' ? $courseTitle : null,
    $teacherFullName !== '' ? 'Prof. ' . $teacherFullName : null,
])));

$stmtQuestions = $dtb->prepare("SELECT * FROM rg_eval_questions WHERE form_id = ? ORDER BY display_order ASC, id ASC");
$stmtQuestions->execute([$formId]);
$questions = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

$optionsByQuestion = [];
$questionIds = array_column($questions, 'id');
if (!empty($questionIds)) {
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmtOptions = $dtb->prepare("SELECT * FROM rg_eval_options WHERE question_id IN ($placeholders) ORDER BY display_order ASC, id ASC");
    $stmtOptions->execute($questionIds);
    foreach ($stmtOptions->fetchAll(PDO::FETCH_ASSOC) as $option) {
        $optionsByQuestion[$option['question_id']][] = $option;
    }
}

$stmtSummary = $dtb->prepare("SELECT COUNT(*) AS total_responses, MIN(submitted_at) AS first_response, MAX(submitted_at) AS last_response FROM rg_eval_responses WHERE form_id = ?");
$stmtSummary->execute([$formId]);
$summary = $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: [];
$responsesCount = (int)($summary['total_responses'] ?? 0);

$stmtTimeline = $dtb->prepare("SELECT DATE(submitted_at) AS response_day, COUNT(*) AS total FROM rg_eval_responses WHERE form_id = ? GROUP BY DATE(submitted_at) ORDER BY response_day ASC");
$stmtTimeline->execute([$formId]);
$timeline = [];
foreach ($stmtTimeline->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $timeline[] = [
        'label' => $row['response_day'],
        'value' => (int)$row['total'],
    ];
}

$questionStats = [];
$ratingAverage = null;

foreach ($questions as $question) {
    $item = [
        'id' => (int)$question['id'],
        'text' => $question['question_text'],
        'type' => $question['question_type'],
        'chart' => null,
        'labels' => [],
        'values' => [],
        'average' => null,
        'answers' => [],
    ];

    if ($question['question_type'] === 'rating') {
        $stmtRating = $dtb->prepare("SELECT a.answer_number AS rating_value, COUNT(*) AS total FROM rg_eval_answers a INNER JOIN rg_eval_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.question_id = ? AND a.answer_number IS NOT NULL GROUP BY a.answer_number ORDER BY a.answer_number ASC");
        $stmtRating->execute([$formId, $question['id']]);
        $counts = array_fill(1, 5, 0);
        foreach ($stmtRating->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int)$row['rating_value']] = (int)$row['total'];
        }

        $item['chart'] = 'bar';
        $item['labels'] = ['1', '2', '3', '4', '5'];
        $item['values'] = array_values($counts);

        $totalVotes = array_sum($item['values']);
        if ($totalVotes > 0) {
            $weighted = 0;
            foreach ($counts as $rating => $count) {
                $weighted += $rating * $count;
            }
            $item['average'] = round($weighted / $totalVotes, 2);
            if ($ratingAverage === null) {
                $ratingAverage = $item['average'];
            }
        }
    } elseif (in_array($question['question_type'], ['multiple_choice', 'checkbox', 'dropdown'], true)) {
        $counts = [];

        if ($question['question_type'] === 'checkbox') {
            // Source principale: table de liaison multi-sélection
            try {
                $stmtChoice = $dtb->prepare("SELECT ao.option_id AS option_id, COUNT(*) AS total FROM rg_eval_answer_options ao INNER JOIN rg_eval_answers a ON a.id = ao.answer_id INNER JOIN rg_eval_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.question_id = ? GROUP BY ao.option_id ORDER BY total DESC, ao.option_id ASC");
                $stmtChoice->execute([$formId, $question['id']]);
                foreach ($stmtChoice->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $counts[(int)$row['option_id']] = (int)$row['total'];
                }
            } catch (Throwable $th) {
                $counts = [];
            }

            // Fallback legacy: IDs séparés par des virgules dans answer_text
            if (empty($counts)) {
                $stmtLegacy = $dtb->prepare("SELECT a.answer_text FROM rg_eval_answers a INNER JOIN rg_eval_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.question_id = ? AND a.answer_text IS NOT NULL AND a.answer_text != ''");
                $stmtLegacy->execute([$formId, $question['id']]);
                foreach ($stmtLegacy->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $parts = explode(',', (string)$row['answer_text']);
                    foreach ($parts as $rawId) {
                        $optionId = (int)trim($rawId);
                        if ($optionId > 0) {
                            if (!isset($counts[$optionId])) {
                                $counts[$optionId] = 0;
                            }
                            $counts[$optionId]++;
                        }
                    }
                }
            }
        } else {
            $stmtChoice = $dtb->prepare("SELECT a.answer_number AS option_id, COUNT(*) AS total FROM rg_eval_answers a INNER JOIN rg_eval_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.question_id = ? AND a.answer_number IS NOT NULL GROUP BY a.answer_number ORDER BY total DESC, option_id ASC");
            $stmtChoice->execute([$formId, $question['id']]);
            foreach ($stmtChoice->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $counts[(int)$row['option_id']] = (int)$row['total'];
            }
        }

        foreach ($optionsByQuestion[$question['id']] ?? [] as $option) {
            $optionId = (int)$option['id'];
            $item['labels'][] = $option['option_text'];
            $item['values'][] = $counts[$optionId] ?? 0;
        }

        $item['chart'] = 'bar';
    } else {
        $stmtText = $dtb->prepare("SELECT a.answer_text, r.submitted_at FROM rg_eval_answers a INNER JOIN rg_eval_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.question_id = ? ORDER BY r.submitted_at DESC, a.id DESC LIMIT 8");
        $stmtText->execute([$formId, $question['id']]);
        $item['answers'] = $stmtText->fetchAll(PDO::FETCH_ASSOC);
    }

    $questionStats[] = $item;
}

$chartPayload = [
    'timeline' => $timeline,
    'questions' => $questionStats,
];

function evalStatsPdfSafeDate($value): string {
    if (empty($value)) {
        return 'Aucune';
    }
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$value;
}

function evalStatsPdfHtml(array $form, array $summary, int $responsesCount, array $timeline, array $questionStats, $ratingAverage, string $evaluationTitle, string $evaluationSubtitle): string {
    $timelineRows = '';
    foreach ($timeline as $row) {
        $timelineRows .= '<tr>'
            . '<td>' . htmlspecialchars((string)$row['label']) . '</td>'
            . '<td style="text-align:right;">' . (int)$row['value'] . '</td>'
            . '</tr>';
    }
    if ($timelineRows === '') {
        $timelineRows = '<tr><td colspan="2" style="text-align:center;color:#64748b;">Aucune réponse enregistrée.</td></tr>';
    }

    $questionSections = '';
    foreach ($questionStats as $questionData) {
        $questionTitle = htmlspecialchars((string)$questionData['text']);
        $questionType = htmlspecialchars((string)$questionData['type']);
        $averageText = $questionData['average'] !== null ? htmlspecialchars((string)$questionData['average']) . ' / 5' : 'N/A';

        $questionSections .= '<div class="section">'
            . '<h3>' . $questionTitle . '</h3>'
            . '<div class="meta">Type: ' . $questionType . ($questionData['average'] !== null ? ' | Moyenne: ' . $averageText : '') . '</div>';

        if ($questionData['type'] === 'rating') {
            $questionSections .= '<table>'
                . '<thead><tr><th>Note</th><th>Total</th></tr></thead><tbody>';
            foreach ($questionData['labels'] as $idx => $label) {
                $value = (int)($questionData['values'][$idx] ?? 0);
                $questionSections .= '<tr><td>' . htmlspecialchars((string)$label) . '</td><td style="text-align:right;">' . $value . '</td></tr>';
            }
            $questionSections .= '</tbody></table>';
        } elseif (!empty($questionData['labels'])) {
            $questionSections .= '<table>'
                . '<thead><tr><th>Option</th><th>Total</th></tr></thead><tbody>';
            foreach ($questionData['labels'] as $idx => $label) {
                $value = (int)($questionData['values'][$idx] ?? 0);
                $questionSections .= '<tr><td>' . htmlspecialchars((string)$label) . '</td><td style="text-align:right;">' . $value . '</td></tr>';
            }
            $questionSections .= '</tbody></table>';
        } elseif (!empty($questionData['answers'])) {
            $questionSections .= '<table>'
                . '<thead><tr><th>Date</th><th>Réponse</th></tr></thead><tbody>';
            foreach ($questionData['answers'] as $answer) {
                $questionSections .= '<tr><td style="width: 140px;">' . htmlspecialchars(evalStatsPdfSafeDate($answer['submitted_at'] ?? '')) . '</td><td>' . nl2br(htmlspecialchars((string)($answer['answer_text'] ?? ''))) . '</td></tr>';
            }
            $questionSections .= '</tbody></table>';
        } else {
            $questionSections .= '<div class="empty">Aucune donnée disponible.</div>';
        }

        $questionSections .= '</div>';
    }

    $statusLabel = !empty($form['is_published']) ? 'Publié' : 'Brouillon';
    $createdAt = evalStatsPdfSafeDate($form['created_at'] ?? '');
    $firstResponse = evalStatsPdfSafeDate($summary['first_response'] ?? '');
    $lastResponse = evalStatsPdfSafeDate($summary['last_response'] ?? '');
    $targetRole = htmlspecialchars((string)($form['target_role'] ?? 'all'));

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 11px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #1d4ed8; }
        h2 { margin: 0 0 10px; font-size: 14px; color: #0f172a; }
        h3 { margin: 0 0 6px; font-size: 12px; color: #0f172a; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 14px; }
        .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-grid td { width: 25%; vertical-align: top; padding: 6px; }
        .card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; background: #f8fafc; }
        .card .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .card .value { margin-top: 4px; font-size: 16px; font-weight: 700; color: #0f172a; }
        .section { margin-top: 14px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; page-break-inside: avoid; }
        .meta { font-size: 10px; color: #64748b; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; }
        th { background: #e2e8f0; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        .empty { color: #64748b; font-style: italic; padding: 8px 0; }
        .footer { margin-top: 12px; font-size: 10px; color: #64748b; text-align: center; }
        .two-col { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .two-col td { width: 50%; vertical-align: top; padding-right: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Analyse des résultats d'évaluation</h1>
        <h2><?= htmlspecialchars($evaluationTitle) ?></h2>
        <?php if ($evaluationSubtitle !== ''): ?>
        <div class="muted"><?= htmlspecialchars($evaluationSubtitle) ?></div>
        <?php endif; ?>
        <div class="muted"><?= htmlspecialchars((string)($form['description'] ?? '')) ?></div>
    </div>

    <table class="summary-grid">
        <tr>
            <td><div class="card"><div class="label">Réponses</div><div class="value"><?= (int)$responsesCount ?></div></div></td>
            <td><div class="card"><div class="label">Questions</div><div class="value"><?= count($questionStats) ?></div></div></td>
            <td><div class="card"><div class="label">Statut</div><div class="value"><?= htmlspecialchars($statusLabel) ?></div></div></td>
            <td><div class="card"><div class="label">Moyenne globale</div><div class="value"><?= $ratingAverage !== null ? htmlspecialchars((string)$ratingAverage) . ' / 5' : 'N/A' ?></div></div></td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="section">
                    <h3>Informations générales</h3>
                    <table>
                        <tbody>
                            <tr><th>Créé le</th><td><?= htmlspecialchars($createdAt) ?></td></tr>
                            <tr><th>Dernière réponse</th><td><?= htmlspecialchars($lastResponse) ?></td></tr>
                            <tr><th>Première réponse</th><td><?= htmlspecialchars($firstResponse) ?></td></tr>
                            <tr><th>Cible</th><td><?= $targetRole === 'all' ? 'Tous' : $targetRole ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <h3>Réponses par jour</h3>
                    <table>
                        <thead><tr><th>Date</th><th>Total</th></tr></thead>
                        <tbody><?= $timelineRows ?></tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <?= $questionSections ?>

    <div class="footer">Export généré le <?= date('d/m/Y H:i') ?></div>
</body>
</html>
    <?php
    return ob_get_clean();
}

$exportPdf = isset($_GET['export']) && $_GET['export'] === 'pdf';
if ($exportPdf) {
    $autoload = dirname(__DIR__) . '/genPDF/dompdf/vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        die('Bibliothèque PDF introuvable.');
    }

    require_once $autoload;

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml(evalStatsPdfHtml($form, $summary, $responsesCount, $timeline, $questionStats, $ratingAverage, $evaluationTitle, $evaluationSubtitle));
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('evaluation-stats-' . $formId . '.pdf', ['Attachment' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Statistiques d'évaluation</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .stat-card { backdrop-filter: blur(8px); }
        .chart-box { min-height: 280px; }
    </style>
</head>
<body class="<?=$bg_three_color?> <?=$txt_one_color?> sm:text-xs lg:text-sm">

<div class="h-screen w-full <?=$bg_three_color?>">
    <?php require('../init/topbar.php');?>

    <div class="w-full flex flex-col lg:flex-row">
        <?php require('../init/menubar.php'); ?>

        <div class="w-full lg:w-10/12 flex flex-col overflow-y-auto" style="height: calc(100vh - 56px);">
            <div class="p-6 space-y-6">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <a href="<?=$app_base?>/evaluation/admin" class="<?=$txt_two_color?> hover:<?=$txt_one_color?> text-sm inline-flex items-center gap-2 mb-2">
                            <i class="bi bi-arrow-left"></i> Retour à l'administration
                        </a>
                        <h1 class="text-3xl font-bold text-blue-400">Analyse des résultats</h1>
                        <p class="<?=$txt_two_color?> mt-1">Évaluation : <?=htmlspecialchars($evaluationTitle)?></p>
                        <?php if ($evaluationSubtitle !== ''): ?>
                        <p class="<?=$txt_two_color?> mt-1"><?=htmlspecialchars($evaluationSubtitle)?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="<?=$app_base?>/evaluation/stats/<?=$form['id']?>?export=pdf" target="_blank" class="px-4 py-2 rounded bg-red-700 hover:bg-red-600 text-white font-medium inline-flex items-center gap-2">
                            <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
                        </a>
                        <a href="<?=$app_base?>/evaluation/edit/<?=$form['id']?>" class="px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-white font-medium inline-flex items-center gap-2">
                            <i class="bi bi-pencil"></i> Modifier
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="stat-card <?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                        <div class="text-xs uppercase tracking-wider <?=$txt_two_color?>">Réponses</div>
                        <div class="text-3xl font-bold text-blue-400 mt-2"><?=$responsesCount?></div>
                    </div>
                    <div class="stat-card <?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                        <div class="text-xs uppercase tracking-wider <?=$txt_two_color?>">Questions</div>
                        <div class="text-3xl font-bold text-emerald-400 mt-2"><?=count($questions)?></div>
                    </div>
                    <div class="stat-card <?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                        <div class="text-xs uppercase tracking-wider <?=$txt_two_color?>">Statut</div>
                        <div class="text-2xl font-bold mt-2 <?=$form['is_published'] ? 'text-green-400' : 'text-gray-400'?>"><?=$form['is_published'] ? 'Publié' : 'Brouillon'?></div>
                    </div>
                    <div class="stat-card <?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                        <div class="text-xs uppercase tracking-wider <?=$txt_two_color?>">Moyenne notation</div>
                        <div class="text-3xl font-bold text-purple-400 mt-2"><?=$ratingAverage !== null ? htmlspecialchars((string)$ratingAverage) : 'N/A'?></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 <?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold">Volume des réponses</h2>
                            <span class="text-xs <?=$txt_two_color?>">Répartition par jour</span>
                        </div>
                        <div class="chart-box">
                            <canvas id="timelineChart"></canvas>
                        </div>
                    </div>

                    <div class="<?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                        <h2 class="text-xl font-bold mb-4">Résumé</h2>
                        <div class="space-y-4 text-sm <?=$txt_two_color?>">
                            <div class="flex justify-between gap-4"><span>Créé le</span><span class="<?=$txt_one_color?>"><?=date('d/m/Y H:i', strtotime($form['created_at']))?></span></div>
                            <div class="flex justify-between gap-4"><span>Dernière réponse</span><span class="<?=$txt_one_color?>"><?=$summary['last_response'] ? date('d/m/Y H:i', strtotime($summary['last_response'])) : 'Aucune'?></span></div>
                            <div class="flex justify-between gap-4"><span>Cible</span><span class="<?=$txt_one_color?>"><?=htmlspecialchars($form['target_role'] ?? 'all')?></span></div>
                            <div class="flex justify-between gap-4"><span>Fin</span><span class="<?=$txt_one_color?>"><?=$form['end_date'] ? date('d/m/Y H:i', strtotime($form['end_date'])) : 'Sans limite'?></span></div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <?php foreach ($questionStats as $index => $questionData): ?>
                        <div class="<?=$bg_two_color?> rounded-xl border <?=$br_two_color?> p-5 shadow">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <h3 class="text-lg font-bold"><?=htmlspecialchars($questionData['text'])?></h3>
                                    <p class="text-xs <?=$txt_two_color?> mt-1">Type: <?=htmlspecialchars($questionData['type'])?></p>
                                </div>
                                <?php if ($questionData['average'] !== null): ?>
                                    <div class="px-3 py-1 rounded-full bg-purple-900 text-purple-200 text-sm font-semibold">
                                        Moyenne <?=htmlspecialchars((string)$questionData['average'])?> / 5
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($questionData['chart'])): ?>
                                <div class="chart-box mb-4">
                                    <canvas id="questionChart<?=$index?>"></canvas>
                                </div>
                            <?php elseif (!empty($questionData['answers'])): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <?php foreach ($questionData['answers'] as $answer): ?>
                                        <div class="rounded-lg border <?=$br_two_color?> p-3 <?=$bg_three_color?>">
                                            <div class="text-sm <?=$txt_one_color?> whitespace-pre-wrap"><?=htmlspecialchars((string)($answer['answer_text'] ?? ''))?></div>
                                            <div class="text-xs <?=$txt_two_color?> mt-2"><?=date('d/m/Y H:i', strtotime($answer['submitted_at']))?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-sm <?=$txt_two_color?>">Aucune donnée disponible pour cette question.</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php require('../init/footer.php'); ?>
        </div>
    </div>
</div>

<script>
const chartPayload = <?=json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;

function chartContext(canvasId) {
    const canvas = document.getElementById(canvasId);
    return canvas ? canvas.getContext('2d') : null;
}

if (window.Chart) {
    if (chartPayload.timeline.length) {
        const timelineCtx = chartContext('timelineChart');
        if (timelineCtx) {
            new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: chartPayload.timeline.map(item => item.label),
                    datasets: [{
                        label: 'Réponses',
                        data: chartPayload.timeline.map(item => item.value),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.18)',
                        tension: 0.35,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } },
                        y: { beginAtZero: true, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.12)' } }
                    }
                }
            });
        }
    }

    chartPayload.questions.forEach((question, index) => {
        if (!question.chart || !question.labels.length) {
            return;
        }

        const ctx = chartContext('questionChart' + index);
        if (!ctx) {
            return;
        }

        new Chart(ctx, {
            type: question.chart,
            data: {
                labels: question.labels,
                datasets: [{
                    label: 'Réponses',
                    data: question.values,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.25)',
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } },
                    y: { beginAtZero: true, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.12)' } }
                }
            }
        });
    });
}
</script>

</body>
</html>