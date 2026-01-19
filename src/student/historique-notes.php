<div class="mt-2 p-4 overflow-auto" style="max-height: calc(100vh - 246px);">
<?php
/**
 * Historique des modifications de notes d'un étudiant
 */

// Récupérer l'historique des modifications pour cet étudiant
$searchHistory = $dtb->prepare("
    SELECT h.*, s.session_name, s.session_year, u.prenom as user_prenom, u.nom as user_nom
    FROM t_notes_modification_history h
    LEFT JOIN t_2023_session s ON h.session_id = s.session_id
    LEFT JOIN compt_utilisateur u ON h.action_by = u.id
    WHERE h.student_id = :student_id
    ORDER BY h.action_date DESC
");
$searchHistory->execute(['student_id' => $student_id]);
$historyCount = $searchHistory->rowCount();
?>

<style>
.history-container {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 12px;
}
.history-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
    border-bottom: 1px solid rgba(51, 65, 85, 0.4);
    padding: 16px 20px;
    border-radius: 12px 12px 0 0;
}
.history-item {
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(51, 65, 85, 0.3);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.15s ease;
}
.history-item:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(59, 130, 246, 0.4);
}
.action-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.action-modification {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}
.action-suppression {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.action-restauration {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}
.action-creation {
    background: rgba(168, 85, 247, 0.2);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
}
.grade-change {
    display: flex;
    align-items: center;
    gap: 8px;
}
.grade-old {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    padding: 2px 8px;
    border-radius: 4px;
    text-decoration: line-through;
}
.grade-new {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    padding: 2px 8px;
    border-radius: 4px;
}
.grade-arrow {
    color: #64748b;
}
.empty-history {
    background: rgba(30, 41, 59, 0.3);
    border: 1px dashed rgba(51, 65, 85, 0.4);
    border-radius: 10px;
    padding: 40px;
    text-align: center;
}

/* Light mode */
[data-theme="light"] .history-container {
    background: rgba(255, 255, 255, 0.8);
    border-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .history-header {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(37, 99, 235, 0.03));
    border-bottom-color: rgba(203, 213, 225, 0.6);
}
[data-theme="light"] .history-item {
    background: rgba(248, 250, 252, 0.8);
    border-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .history-item:hover {
    background: rgba(241, 245, 249, 0.9);
}
</style>

<div class="history-container">
    <div class="history-header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-500/15 border border-blue-500/25 flex items-center justify-center">
                    <i class="bi bi-clock-history text-blue-400"></i>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-100">Historique des modifications de notes</h2>
                    <p class="text-xs text-slate-500"><?= $historyCount ?> modification<?= $historyCount > 1 ? 's' : '' ?> enregistrée<?= $historyCount > 1 ? 's' : '' ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="p-4">
        <?php if ($historyCount > 0): ?>
            <?php while ($history = $searchHistory->fetch()): ?>
                <div class="history-item">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-grow">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="action-badge action-<?= $history['action_type'] ?>">
                                    <?php 
                                    switch($history['action_type']) {
                                        case 'modification': echo '<i class="bi bi-pencil-fill mr-1"></i>Modification'; break;
                                        case 'suppression': echo '<i class="bi bi-trash-fill mr-1"></i>Suppression'; break;
                                        case 'restauration': echo '<i class="bi bi-arrow-counterclockwise mr-1"></i>Restauration'; break;
                                        case 'creation': echo '<i class="bi bi-plus-circle-fill mr-1"></i>Création'; break;
                                    }
                                    ?>
                                </span>
                                <span class="text-sm font-medium text-slate-300">
                                    <?= htmlspecialchars($history['cours_sigle']) ?> - <?= htmlspecialchars($history['cours_titre']) ?>
                                </span>
                            </div>
                            
                            <?php if ($history['action_type'] == 'modification'): ?>
                            <div class="grade-change mb-2">
                                <span class="text-xs text-slate-500">Note:</span>
                                <span class="grade-old"><?= $history['old_grade'] == -2 ? 'OK' : number_format($history['old_grade'], 2) ?></span>
                                <i class="bi bi-arrow-right grade-arrow"></i>
                                <span class="grade-new"><?= $history['new_grade'] == -2 ? 'OK' : number_format($history['new_grade'], 2) ?></span>
                            </div>
                            <?php elseif ($history['action_type'] == 'suppression'): ?>
                            <div class="text-xs text-slate-400 mb-2">
                                Note au moment de la suppression: <span class="grade-old"><?= $history['old_grade'] == -2 ? 'OK' : number_format($history['old_grade'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center gap-4 text-xs text-slate-500">
                                <span>
                                    <i class="bi bi-calendar3 mr-1"></i>
                                    <?= date('d/m/Y à H:i', strtotime($history['action_date'])) ?>
                                </span>
                                <span>
                                    <i class="bi bi-person-fill mr-1"></i>
                                    <?= htmlspecialchars($history['user_prenom'] ?? 'Inconnu') ?> <?= htmlspecialchars($history['user_nom'] ?? '') ?>
                                </span>
                                <?php if (!empty($history['session_name'])): ?>
                                <span>
                                    <i class="bi bi-collection mr-1"></i>
                                    <?= htmlspecialchars($history['session_name']) ?> (<?= $history['session_year'] ?>)
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($history['ip_address'])): ?>
                                <span title="Adresse IP">
                                    <i class="bi bi-globe mr-1"></i>
                                    <?= htmlspecialchars($history['ip_address']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($history['commentaire'])): ?>
                            <div class="mt-2 text-xs text-slate-400 italic">
                                <i class="bi bi-chat-left-text mr-1"></i>
                                <?= htmlspecialchars($history['commentaire']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-history">
                <i class="bi bi-inbox text-slate-600 text-4xl"></i>
                <p class="text-slate-500 mt-3">Aucune modification de note enregistrée</p>
                <p class="text-slate-600 text-xs mt-1">Les modifications futures seront affichées ici</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
