<?php
/**
 * Classement des étudiants par session
 * Affiche la moyenne générale de chaque étudiant inscrit pour une session donnée.
 * Cliquer sur un étudiant redirige vers son transcriptSS.
 */

// ── 1. Sessions disponibles (historique + en cours, sans futur) ─────────────
$currentYear = (int)date('Y');
$currentMonth = (int)date('m');
$currentSessionStartYear = ($currentMonth >= 7) ? $currentYear : ($currentYear - 1);

$allSessionsQ = $dtb->prepare(
    "SELECT *
     FROM t_2023_session
     WHERE CAST(TRIM(SUBSTRING_INDEX(session_year, '-', 1)) AS UNSIGNED) <= :current_start_year
     ORDER BY
         CAST(TRIM(SUBSTRING_INDEX(session_year, '-', 1)) AS UNSIGNED) DESC,
         session_semester ASC"
);
$allSessionsQ->execute(['current_start_year' => $currentSessionStartYear]);
$sessions = [];
while ($s = $allSessionsQ->fetch()) {
    $sessions[] = $s;
}

// ── 2. Session sélectionnée (par défaut la plus récente) ────────────────────
$selectedSessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$selectedSession   = null;

// Filtres
$mentionFilter    = isset($_GET['mention']) ? trim((string)$_GET['mention']) : 'all';
$niveauFilter     = isset($_GET['niveau']) ? trim((string)$_GET['niveau']) : 'all';
$incompleteFilter = isset($_GET['incomplet']) ? trim((string)$_GET['incomplet']) : 'all';

if ($selectedSessionId) {
    foreach ($sessions as $s) {
        if ((int)$s['session_id'] === $selectedSessionId) {
            $selectedSession = $s;
            break;
        }
    }
}
if (!$selectedSession && !empty($sessions)) {
    $selectedSession   = $sessions[0];
    $selectedSessionId = (int)$selectedSession['session_id'];
}

// ── 3. Moyennes des étudiants pour la session ────────────────────────────────
$students = [];
$allSessionStudents = [];
if ($selectedSessionId) {
    $stmt = $dtb->prepare("
        SELECT
            e.id            AS student_db_id,
            e.student_id,
            e.student_nom,
            e.student_prenom,
            e.annee_etude,
            e.etude_envisage,
            e.etude_option,
            e.image_student,
            SUM(CASE WHEN n.cours_category != 5 THEN n.credit                ELSE 0 END) AS total_credit,
            SUM(CASE WHEN n.cours_category != 5 THEN n.credit * n.grade      ELSE 0 END) AS total_credit_note,
            SUM(CASE WHEN n.cours_category != 5 AND (n.grade = 0 OR n.grade IS NULL) THEN 1 ELSE 0 END) AS incomplete_notes,
            COUNT(n.id) AS nb_cours
        FROM t_2023_notes n
        INNER JOIN tbl_2024_etudiant e ON n.student_id = e.student_id
        WHERE n.session_id = :sid
          AND n.ajout  = 1
          AND n.remove != 1
        GROUP BY
            e.id, e.student_id, e.student_nom, e.student_prenom,
            e.annee_etude, e.etude_envisage, e.etude_option, e.image_student
    ");
    $stmt->execute(['sid' => $selectedSessionId]);
    $rawStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allSessionStudents = $rawStudents;

    foreach ($rawStudents as &$std) {
        $std['moyenne'] = ($std['total_credit'] > 0)
            ? round($std['total_credit_note'] / $std['total_credit'], 2)
            : 0;

        $std['mention_key'] = srMentionKey((float)$std['moyenne']);
        $std['has_incomplete'] = ((int)$std['incomplete_notes'] > 0);
    }
    unset($std);

    // Appliquer filtres (mention, niveau, notes incomplètes)
    $rawStudents = array_values(array_filter($rawStudents, function (array $std) use ($mentionFilter, $niveauFilter, $incompleteFilter): bool {
        if ($mentionFilter !== 'all' && ($std['etude_envisage'] ?? '') !== $mentionFilter) {
            return false;
        }

        if ($niveauFilter !== 'all' && (string)$std['annee_etude'] !== (string)$niveauFilter) {
            return false;
        }

        if ($incompleteFilter === 'yes' && !$std['has_incomplete']) {
            return false;
        }
        if ($incompleteFilter === 'no' && $std['has_incomplete']) {
            return false;
        }

        return true;
    }));

    usort($rawStudents, fn($a, $b) => $b['moyenne'] <=> $a['moyenne']);
    $students = $rawStudents;
}

// ── 4. Helpers ───────────────────────────────────────────────────────────────
function srGetMention(float $moy): array {
    if ($moy >= 17) return ['Très Bien',  '#fbbf24', 'rgba(251,191,36,.18)', 'rgba(251,191,36,.35)'];
    if ($moy >= 14) return ['Bien',       '#60a5fa', 'rgba(96,165,250,.18)', 'rgba(96,165,250,.35)'];
    if ($moy >= 12) return ['Assez Bien', '#34d399', 'rgba(52,211,153,.18)', 'rgba(52,211,153,.35)'];
    if ($moy >= 10) return ['Passable',   '#94a3b8', 'rgba(148,163,184,.15)','rgba(148,163,184,.3)'];
    return                 ['Insuffisant','#f87171', 'rgba(248,113,113,.18)','rgba(248,113,113,.35)'];
}

function srMentionKey(float $moy): string {
    if ($moy >= 17) return 'tres-bien';
    if ($moy >= 14) return 'bien';
    if ($moy >= 12) return 'assez-bien';
    if ($moy >= 10) return 'passable';
    return 'insuffisant';
}

function srNiveauLabel(int $n): string {
    if ($n == 0)       return 'Remise à niveau';
    if ($n <= 3)       return 'Licence ' . $n;
    return 'Master ' . ($n - 3);
}
?>
<style>
/* ── container ── */
.sr-container { scrollbar-width: thin; scrollbar-color: rgba(6,182,212,.3) transparent; }
.sr-container::-webkit-scrollbar { width: 6px; }
.sr-container::-webkit-scrollbar-thumb { background: rgba(6,182,212,.3); border-radius: 3px; }

/* ── header / selector ── */
.sr-header {
    background: rgba(15,23,42,.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(51,65,85,.5);
}
.sr-session-select {
    background: rgba(30,41,59,.9);
    border: 1px solid rgba(6,182,212,.35);
    color: #e2e8f0;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    cursor: pointer;
    min-width: 260px;
    outline: none;
    transition: border-color .2s;
}
.sr-session-select:focus { border-color: rgba(6,182,212,.7); }
.sr-session-select option { background: #1e293b; color: #e2e8f0; }
.sr-filter-select {
    background: rgba(30,41,59,.9);
    border: 1px solid rgba(100,116,139,.45);
    color: #e2e8f0;
    padding: 8px 10px;
    border-radius: 9px;
    font-size: 12px;
    cursor: pointer;
    min-width: 160px;
    outline: none;
}
.sr-filter-select:focus { border-color: rgba(6,182,212,.6); }
.sr-filter-select option { background: #1e293b; color: #e2e8f0; }

/* ── student card ── */
.sr-student-card {
    background: rgba(15,23,42,.55);
    border: 1px solid rgba(51,65,85,.4);
    border-radius: 12px;
    transition: all .15s ease;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
}
.sr-student-card:hover {
    background: rgba(6,182,212,.08);
    border-color: rgba(6,182,212,.4);
    transform: translateX(4px);
    box-shadow: 0 4px 18px rgba(0,0,0,.2);
}

/* ── rank badge ── */
.sr-rank { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.sr-rank-1 { background: linear-gradient(135deg,#fbbf24,#f59e0b); color:#1e293b; }
.sr-rank-2 { background: linear-gradient(135deg,#94a3b8,#64748b); color:#1e293b; }
.sr-rank-3 { background: linear-gradient(135deg,#cd7f32,#b45309); color:#fff; }
.sr-rank-other { background: rgba(100,116,139,.3); color:#94a3b8; }

/* ── moyenne box ── */
.sr-moy-box {
    border-radius: 10px;
    padding: 6px 14px;
    min-width: 70px;
    text-align: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}

/* ── tags ── */
.sr-niveau-tag {
    background: rgba(100,116,139,.22);
    color: #94a3b8;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}
.sr-mention-tag {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .02em;
}

/* ── stats strip ── */
.sr-stats { background: rgba(6,182,212,.07); border: 1px solid rgba(6,182,212,.2); border-radius: 10px; }

/* ── light mode ── */
[data-theme="light"] .sr-student-card {
    background: rgba(248,250,252,.85);
    border-color: rgba(203,213,225,.6);
    color: #1e293b;
}
[data-theme="light"] .sr-student-card:hover {
    background: rgba(224,242,254,.9);
    border-color: rgba(6,182,212,.5);
}
[data-theme="light"] .sr-header {
    background: rgba(255,255,255,.92);
    border-bottom-color: rgba(203,213,225,.8);
}
[data-theme="light"] .sr-session-select {
    background: rgba(241,245,249,.95);
    border-color: rgba(6,182,212,.4);
    color: #1e293b;
}
[data-theme="light"] .sr-session-select option { background: #f1f5f9; color: #1e293b; }
[data-theme="light"] .sr-filter-select {
    background: rgba(241,245,249,.95);
    border-color: rgba(148,163,184,.55);
    color: #1e293b;
}
[data-theme="light"] .sr-filter-select option { background: #f1f5f9; color: #1e293b; }
[data-theme="light"] .sr-stats { background: rgba(6,182,212,.04); }

/* ── empty state ── */
.sr-empty {
    background: rgba(30,41,59,.35);
    border: 1px dashed rgba(51,65,85,.5);
    border-radius: 12px;
}
</style>

<?php
// Groupement par filière pour affichage optionnel
$totalStudents = count($students);
$aboveAvg      = 0;
$sumMoy        = 0;
foreach ($students as $std) {
    $sumMoy += $std['moyenne'];
    if ($std['moyenne'] >= 10) $aboveAvg++;
}
$classMoy = ($totalStudents > 0) ? round($sumMoy / $totalStudents, 2) : 0;

$availableNiveaux = [];
$availableMentions = [];
foreach ($allSessionStudents as $std) {
    $lv = (int)$std['annee_etude'];
    $availableNiveaux[$lv] = $lv;
    if (!empty($std['etude_envisage'])) {
        $availableMentions[$std['etude_envisage']] = $std['etude_envisage'];
    }
}
ksort($availableNiveaux);

// Prioriser certaines mentions puis afficher toutes les autres
$priorityMentions = ['Gestion', 'Communication', 'Droit'];
$orderedMentions = [];
foreach ($priorityMentions as $priorityMention) {
    foreach ($availableMentions as $mentionValue) {
        if (strcasecmp($mentionValue, $priorityMention) === 0) {
            $orderedMentions[$mentionValue] = $mentionValue;
        }
    }
}

$otherMentions = [];
foreach ($availableMentions as $mentionValue) {
    if (!isset($orderedMentions[$mentionValue])) {
        $otherMentions[$mentionValue] = $mentionValue;
    }
}
asort($otherMentions);
$orderedMentions = $orderedMentions + $otherMentions;
?>
<div class="sr-container overflow-auto w-full" style="height: calc(100vh - 80px);">

    <!-- ═══ En-tête ═══════════════════════════════════════════════════════════ -->
    <div class="sr-header sticky top-0 z-10 px-4 py-3 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-3 flex-1">
            <i class="bi bi-bar-chart-line-fill text-cyan-400 text-xl"></i>
            <div>
                <h1 class="text-base font-bold text-slate-100 leading-tight">Classement par session</h1>
                <p class="text-slate-400 text-[11px]">Moyenne générale des étudiants inscrits</p>
            </div>
        </div>

        <!-- Sélecteur de session + filtres -->
        <form method="get" id="sessionForm" class="flex items-center gap-2">
            <label class="text-slate-400 text-xs font-medium">Session&nbsp;:</label>
            <select name="session_id" class="sr-session-select" onchange="document.getElementById('sessionForm').submit()">
                <?php foreach ($sessions as $s): ?>
                    <option value="<?=(int)$s['session_id']?>"
                        <?= ((int)$s['session_id'] === $selectedSessionId) ? 'selected' : '' ?>>
                        <?=htmlspecialchars($s['session_name'])?> — S<?=(int)$s['session_semester']?> / <?=htmlspecialchars($s['session_year'])?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="mention" class="sr-filter-select" onchange="document.getElementById('sessionForm').submit()" title="Filtre par spécialisation">
                <option value="all" <?=($mentionFilter === 'all') ? 'selected' : ''?>>Mention: toutes</option>
                <?php foreach ($orderedMentions as $mention): ?>
                    <option value="<?=htmlspecialchars($mention)?>" <?=($mentionFilter === $mention) ? 'selected' : ''?>><?=htmlspecialchars($mention)?></option>
                <?php endforeach; ?>
            </select>

            <select name="niveau" class="sr-filter-select" onchange="document.getElementById('sessionForm').submit()" title="Filtre par niveau">
                <option value="all" <?=($niveauFilter === 'all') ? 'selected' : ''?>>Niveau: tous</option>
                <?php foreach ($availableNiveaux as $lv): ?>
                    <option value="<?=$lv?>" <?=((string)$niveauFilter === (string)$lv) ? 'selected' : ''?>><?=srNiveauLabel($lv)?></option>
                <?php endforeach; ?>
            </select>

            <select name="incomplet" class="sr-filter-select" onchange="document.getElementById('sessionForm').submit()" title="Filtre notes incomplètes">
                <option value="all" <?=($incompleteFilter === 'all') ? 'selected' : ''?>>Notes: toutes</option>
                <option value="yes" <?=($incompleteFilter === 'yes') ? 'selected' : ''?>>Avec notes incomplètes</option>
                <option value="no" <?=($incompleteFilter === 'no') ? 'selected' : ''?>>Sans notes incomplètes</option>
            </select>

            <!-- Bouton export groupé -->
            <?php if (!empty($students)): ?>
            <button type="button" onclick="exportAllTranscripts()" title="Exporter un relevé PDF par étudiant (un onglet par étudiant)" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-white bg-red-700 hover:bg-red-600 transition-colors whitespace-nowrap">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                Exporter les relevés (<?=count($students)?>)
            </button>
            <script>
            function exportAllTranscripts() {
                var ids = <?=json_encode(array_column($students, 'student_id'))?>;
                var sessionId = <?=(int)$selectedSessionId?>;
                var base = '<?=$app_base?>/src/data.topdf';
                ids.forEach(function(id, i) {
                    setTimeout(function() {
                        window.open(base + '?ptype=TranscriptSS&student_id=' + encodeURIComponent(id) + '&session_id=' + sessionId, '_blank');
                    }, i * 400);
                });
            }
            </script>
            <?php endif; ?>
        </form>

        <!-- Formulaire POST caché vers le bulk export -->
        <?php if (!empty($students)): ?>
        <form id="exportBulkForm" method="post" action="<?=$app_base?>/src/data.topdf?ptype=TranscriptSSBulk" target="_blank" style="display:none">
            <input type="hidden" name="session_id" value="<?=$selectedSessionId?>">
            <?php foreach ($students as $std): ?>
            <input type="hidden" name="student_ids[]" value="<?=htmlspecialchars($std['student_id'])?>">
            <?php endforeach; ?>
        </form>
        <?php endif; ?>
    </div>

    <div class="p-4">

    <?php if ($selectedSession): ?>

        <!-- ═══ Bande résumé ══════════════════════════════════════════════════ -->
        <div class="sr-stats flex flex-wrap gap-4 p-3 mb-5 items-center">
            <div class="text-center px-3">
                <div class="text-cyan-300 text-xl font-bold"><?=$totalStudents?></div>
                <div class="text-slate-400 text-[10px]">Étudiant<?=($totalStudents>1)?'s':''?></div>
            </div>
            <div class="w-px h-8 bg-slate-600"></div>
            <div class="text-center px-3">
                <div class="text-slate-100 text-xl font-bold"><?=$classMoy?>/20</div>
                <div class="text-slate-400 text-[10px]">Moy. de classe</div>
            </div>
            <div class="w-px h-8 bg-slate-600"></div>
            <div class="text-center px-3">
                <div class="text-emerald-400 text-xl font-bold"><?=$aboveAvg?></div>
                <div class="text-slate-400 text-[10px]">Admis (≥10)</div>
            </div>
            <div class="w-px h-8 bg-slate-600"></div>
            <div class="text-center px-3">
                <div class="text-red-400 text-xl font-bold"><?=($totalStudents-$aboveAvg)?></div>
                <div class="text-slate-400 text-[10px]">En échec (&lt;10)</div>
            </div>
            <div class="ml-auto text-slate-400 text-xs">
                <i class="bi bi-info-circle mr-1"></i>
                <?=htmlspecialchars($selectedSession['session_name'])?>
                &nbsp;·&nbsp; S<?=(int)$selectedSession['session_semester']?>
                &nbsp;·&nbsp; <?=htmlspecialchars($selectedSession['session_year'])?>
            </div>
        </div>

        <!-- ═══ Liste des étudiants ═══════════════════════════════════════════ -->
        <?php if (empty($students)): ?>
            <div class="sr-empty flex flex-col items-center justify-center py-16 text-center">
                <i class="bi bi-people text-slate-500 text-4xl mb-3"></i>
                <p class="text-slate-400 font-medium">Aucun étudiant inscrit pour cette session.</p>
                <p class="text-slate-500 text-xs mt-1">Vérifiez que des notes ont été ajoutées.</p>
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-2">
            <?php foreach ($students as $rank => $std):
                $rankNum          = $rank + 1;
                $rankClass        = match(true) { $rankNum===1=>'sr-rank-1', $rankNum===2=>'sr-rank-2', $rankNum===3=>'sr-rank-3', default=>'sr-rank-other' };
                [$mention, $color, $bgColor, $bdColor] = srGetMention((float)$std['moyenne']);
                $niveauLabel      = srNiveauLabel((int)$std['annee_etude']);
                $studentUrl       = $app_base . '/student?id=' . (int)$std['student_db_id'] . '&page=transcriptSS';
                $nom              = htmlspecialchars(strtoupper($std['student_nom']) . ' ' . $std['student_prenom']);
                $matricule        = htmlspecialchars($std['student_id']);
                $mention_label    = htmlspecialchars($std['etude_envisage'] . (($std['etude_option'] ?? '') ? ' – '.$std['etude_option'] : ''));
                $hasIncomplete    = ((int)$std['incomplete_notes'] > 0);
            ?>
                <a href="<?=$studentUrl?>" class="sr-student-card" title="Voir le transcriptSS de <?=$nom?>">

                    <!-- Rang -->
                    <div class="sr-rank <?=$rankClass?>"><?=$rankNum?></div>

                    <!-- Infos étudiant -->
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-0.5">
                            <span class="text-slate-100 font-semibold text-sm truncate"><?=$nom?></span>
                            <span class="sr-niveau-tag"><?=$niveauLabel?></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                            <span><i class="bi-person-badge mr-1 opacity-60"></i><?=$matricule?></span>
                            <?php if ($mention_label): ?>
                                <span class="opacity-70">·</span>
                                <span class="truncate"><?=$mention_label?></span>
                            <?php endif; ?>
                            <span class="opacity-70">·</span>
                            <span><?=(int)$std['nb_cours']?> cours</span>
                            <?php if ($hasIncomplete): ?>
                                <span class="opacity-70">·</span>
                                <span class="text-amber-400">Notes incomplètes: <?=(int)$std['incomplete_notes']?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Mention -->
                    <span class="sr-mention-tag hidden sm:inline-block"
                        style="color:<?=$color?>;background:<?=$bgColor?>;border:1px solid <?=$bdColor?>">
                        <?=$mention?>
                    </span>

                    <!-- Moyenne -->
                    <div class="sr-moy-box" style="color:<?=$color?>;background:<?=$bgColor?>;border:1px solid <?=$bdColor?>">
                        <?=number_format((float)$std['moyenne'], 2, '.', '')?>
                    </div>

                    <!-- Flèche -->
                    <i class="bi bi-chevron-right text-slate-500 text-xs flex-shrink-0"></i>

                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="sr-empty flex flex-col items-center justify-center py-20 text-center">
            <i class="bi bi-calendar-x text-slate-500 text-4xl mb-3"></i>
            <p class="text-slate-400 font-medium">Aucune session disponible.</p>
        </div>
    <?php endif; ?>

    </div><!-- /p-4 -->
</div>
