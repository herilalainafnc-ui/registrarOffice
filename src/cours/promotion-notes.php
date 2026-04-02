<div class="mt-2 p-2 overflow-auto" style="max-height: calc(100vh - 246px);" id="promotionNotesContainer">
<?php
$canEditPromotionNotes = ($privilege == "registrar" OR $privilege == "administrator" OR $privilege == "superadmin");

$stmtPromotionRows = $dtb->prepare(
    'SELECT
        n.student_id,
        n.session_id,
        n.annee_scolaire,
        n.yearlevel,
        n.semester,
        MAX(COALESCE(e.student_nom, "")) AS student_nom,
        MAX(COALESCE(e.student_prenom, "")) AS student_prenom,
        MAX(p.grade_work_educ) AS grade_work_educ,
        MAX(p.grade_chapel_part) AS grade_chapel_part
     FROM t_2023_notes n
     LEFT JOIN tbl_2024_etudiant e ON e.student_id = n.student_id
     LEFT JOIN t_2023_promotion_notes p ON p.student_id = n.student_id AND p.session_id = n.session_id
     WHERE n.id_cours = :course_id
       AND n.ajout = 1
       AND n.remove != 1
     GROUP BY n.student_id, n.session_id, n.annee_scolaire, n.yearlevel, n.semester
     ORDER BY n.annee_scolaire DESC, n.semester ASC, n.student_id ASC'
);
$stmtPromotionRows->execute(['course_id' => $id]);
$promotionRows = $stmtPromotionRows->fetchAll(PDO::FETCH_ASSOC);

$rowsByYear = [];
foreach ($promotionRows as $row) {
    $yearKey = (string)($row['annee_scolaire'] ?? 'Année non définie');
    if (!isset($rowsByYear[$yearKey])) {
        $rowsByYear[$yearKey] = [];
    }
    $rowsByYear[$yearKey][] = $row;
}
?>

<?php if (empty($promotionRows)): ?>
<div class="p-4 rounded-md border border-slate-600 bg-slate-800 text-center text-slate-300">
    Aucun étudiant trouvé pour ce cours.
</div>
<?php else: ?>

<?php foreach ($rowsByYear as $yearLabel => $yearRows): ?>
<div class="p-1 <?=$bg_two_color?> hover:<?=$bg_three_color?> mb-4 rounded-md border-2 border-slate-700 hover:border-cyan-500 transition-all">
    <div class="text-center bg-gradient-to-r from-cyan-500">
        <b>Notes de promotion - <?=$yearLabel?></b>
    </div>

    <div>
        <table class="simpleTbl mb-1">
            <thead class="<?=$bg_one_color?> text-white">
                <tr>
                    <td class="w-24">ID</td>
                    <td>Nom et Prénoms</td>
                    <td class="w-20">Niveau</td>
                    <td class="w-20">Sem</td>
                    <td class="w-40">Work Education</td>
                    <td class="w-56">Participation chapelle/prière</td>
                    <td class="w-16">Etat</td>
                </tr>
            </thead>
            <tbody class="<?=$bg_four_color?>">
            <?php foreach ($yearRows as $row):
                $studentId = (string)$row['student_id'];
                $sessionId = (int)$row['session_id'];
                $studentName = trim(($row['student_nom'] ?? '') . ' ' . ($row['student_prenom'] ?? ''));
                if ($studentName === '') {
                    $studentName = 'Etudiant non défini';
                }
                $workVal = isset($row['grade_work_educ']) ? (string)$row['grade_work_educ'] : '';
                $chapelVal = isset($row['grade_chapel_part']) ? (string)$row['grade_chapel_part'] : '';
                $inputIdBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $studentId . '_' . $sessionId);
            ?>
                <tr class="hover:transition-all duration-75 hover:<?=$bg_five_color?> hover:text-black" data-row-key="<?=$inputIdBase?>">
                    <td class="bg-gradient-to-r from-orange-800 to-orange-400"><?=htmlspecialchars($studentId)?></td>
                    <td><?=htmlspecialchars($studentName)?></td>
                    <td><?php
                        $yl = (int)($row['yearlevel'] ?? 0);
                        if ($yl <= 0) {
                            echo '-';
                        } elseif ($yl <= 3) {
                            echo 'L' . $yl;
                        } else {
                            echo 'M' . ($yl - 3);
                        }
                    ?></td>
                    <td><?=htmlspecialchars((string)($row['semester'] ?? '-'))?></td>

                    <td class="<?=$bg_six_color?> text-slate-800 px-0">
                        <?php if ($canEditPromotionNotes): ?>
                        <input
                            id="we_<?=$inputIdBase?>"
                            class="insimple text-sm bg-transparent px-2 promotion-input"
                            type="text"
                            name="grade_work_educ"
                            value="<?=htmlspecialchars($workVal)?>"
                            data-group="promo-<?=$inputIdBase?>"
                            data-row-key="<?=$inputIdBase?>"
                            data-action="<?=$app_base?>/app/.cours/update-promotion-notes.php?course_id=<?=$id?>&student_id=<?=urlencode($studentId)?>&session_id=<?=$sessionId?>&yearlevel=<?=urlencode((string)$row['yearlevel'])?>&semester=<?=urlencode((string)$row['semester'])?>&annee_scolaire=<?=urlencode((string)$row['annee_scolaire'])?>&user_id=<?=$rg_id?>">
                        <?php else: ?>
                        <span class="px-2"><?=htmlspecialchars($workVal)?></span>
                        <?php endif; ?>
                    </td>

                    <td class="<?=$bg_six_color?> text-slate-800 px-0">
                        <?php if ($canEditPromotionNotes): ?>
                        <input
                            id="cp_<?=$inputIdBase?>"
                            class="insimple text-sm bg-transparent px-2 promotion-input"
                            type="text"
                            name="grade_chapel_part"
                            value="<?=htmlspecialchars($chapelVal)?>"
                            data-group="promo-<?=$inputIdBase?>"
                            data-row-key="<?=$inputIdBase?>"
                            data-action="<?=$app_base?>/app/.cours/update-promotion-notes.php?course_id=<?=$id?>&student_id=<?=urlencode($studentId)?>&session_id=<?=$sessionId?>&yearlevel=<?=urlencode((string)$row['yearlevel'])?>&semester=<?=urlencode((string)$row['semester'])?>&annee_scolaire=<?=urlencode((string)$row['annee_scolaire'])?>&user_id=<?=$rg_id?>">
                        <?php else: ?>
                        <span class="px-2"><?=htmlspecialchars($chapelVal)?></span>
                        <?php endif; ?>
                    </td>

                    <td class="text-center"><span class="save-state text-xs text-slate-300">-</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="text-center text-white">
            <b><p style="margin: 0px;">Nombre : <?=count($yearRows) <= 1 ? count($yearRows)." étudiant" : count($yearRows)." étudiants"?></p></b>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div>

<?php if ($canEditPromotionNotes): ?>
<script>
$(function() {
    function validatePromotionNote(value) {
        var v = (value || '').trim().replace(',', '.');
        if (v === '') {
            return { valid: true, value: '' };
        }

        var n = parseFloat(v);
        if (isNaN(n)) {
            return { valid: false, message: 'La note doit etre un nombre valide.' };
        }
        if (n < 0) {
            return { valid: false, message: 'La note ne peut pas etre negative.' };
        }
        if (n > 20) {
            return { valid: false, message: 'La note ne peut pas depasser 20.' };
        }

        var rounded = Math.round(n * 100) / 100;
        return { valid: true, value: String(rounded) };
    }

    var saveTimeout;

    $('#promotionNotesContainer').on('change', '.promotion-input', function() {
        var input = $(this);
        var group = input.data('group');
        var rowKey = input.data('row-key');
        var allInputs = $('#promotionNotesContainer .promotion-input[data-group="' + group + '"]');

        var payload = {};
        var actionUrl = '';
        var hasError = false;

        allInputs.each(function() {
            var el = $(this);
            var checked = validatePromotionNote(el.val());
            if (!checked.valid) {
                hasError = true;
                if (typeof Toast !== 'undefined') {
                    Toast.error(checked.message);
                }
                el.css('border', '2px solid #ef4444');
                setTimeout(function() { el.css('border', ''); }, 2000);
                return false;
            }

            if (checked.value !== el.val()) {
                el.val(checked.value);
            }

            payload[el.attr('name')] = checked.value;
            actionUrl = el.data('action');
        });

        if (hasError || !actionUrl) {
            return;
        }

        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(function() {
            var row = $('#promotionNotesContainer tr[data-row-key="' + rowKey + '"]');
            var state = row.find('.save-state');

            $.ajax({
                url: actionUrl,
                method: 'POST',
                data: payload,
                dataType: 'json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    allInputs.css('opacity', '0.5');
                    state.removeClass('text-emerald-300 text-red-300').addClass('text-amber-300').text('...');
                },
                success: function(response) {
                    allInputs.css('opacity', '1');
                    if (response && response.success) {
                        state.removeClass('text-amber-300 text-red-300').addClass('text-emerald-300').text('OK');
                        if (typeof Toast !== 'undefined') {
                            Toast.success(response.message || 'Notes enregistrees.');
                        }
                    } else {
                        state.removeClass('text-amber-300 text-emerald-300').addClass('text-red-300').text('ERR');
                        if (typeof Toast !== 'undefined') {
                            Toast.error((response && response.message) ? response.message : 'Erreur lors de la sauvegarde.');
                        }
                    }
                },
                error: function(xhr) {
                    allInputs.css('opacity', '1');
                    state.removeClass('text-amber-300 text-emerald-300').addClass('text-red-300').text('ERR');
                    var msg = 'Erreur serveur.';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed && parsed.message) msg = parsed.message;
                    } catch (e) {}

                    if (typeof Toast !== 'undefined') {
                        Toast.error(msg);
                    }
                }
            });
        }, 250);
    });
});
</script>
<?php endif; ?>
