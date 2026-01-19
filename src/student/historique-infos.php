<div class="mt-2 p-4 overflow-auto" style="max-height: calc(100vh - 246px);">
<?php
/**
 * Historique des modifications d'informations étudiant
 */
require_once('../app/.student/student_history_helper.php');

// Récupérer l'historique des modifications pour cet étudiant
$searchHistory = $dtb->prepare("
    SELECT h.*, u.prenom as user_prenom, u.nom as user_nom
    FROM t_student_modification_history h
    LEFT JOIN compt_utilisateur u ON h.action_by = u.id
    WHERE h.student_id = :student_id
    ORDER BY h.action_date DESC
");
$searchHistory->execute(['student_id' => $student_id]);
$historyCount = $searchHistory->rowCount();

// Grouper par date pour un affichage plus clair
$historyByDate = [];
while ($row = $searchHistory->fetch(PDO::FETCH_ASSOC)) {
    $date = date('Y-m-d', strtotime($row['action_date']));
    if (!isset($historyByDate[$date])) {
        $historyByDate[$date] = [];
    }
    $historyByDate[$date][] = $row;
}
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
.date-group {
    background: rgba(30, 41, 59, 0.3);
    border: 1px solid rgba(51, 65, 85, 0.3);
    border-radius: 10px;
    margin-bottom: 12px;
    overflow: hidden;
}
.date-header {
    background: rgba(51, 65, 85, 0.4);
    padding: 10px 16px;
    border-bottom: 1px solid rgba(51, 65, 85, 0.3);
}
.history-item {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(51, 65, 85, 0.2);
    transition: all 0.15s ease;
}
.history-item:last-child {
    border-bottom: none;
}
.history-item:hover {
    background: rgba(30, 41, 59, 0.5);
}
.action-badge {
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}
.action-modification {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}
.action-creation {
    background: rgba(168, 85, 247, 0.2);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
}
.action-suspension {
    background: rgba(249, 115, 22, 0.2);
    color: #fb923c;
    border: 1px solid rgba(249, 115, 22, 0.3);
}
.action-levee_suspension {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}
.action-retrait {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.action-annulation_retrait {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}
.action-image {
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.3);
}
.action-suppression {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.value-change {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.value-old {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: line-through;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.value-new {
    background: rgba(34, 197, 94, 0.1);
    color: #4ade80;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.value-arrow {
    color: #64748b;
}
.empty-history {
    background: rgba(30, 41, 59, 0.3);
    border: 1px dashed rgba(51, 65, 85, 0.4);
    border-radius: 10px;
    padding: 40px;
    text-align: center;
}
.filter-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid rgba(51, 65, 85, 0.4);
    background: rgba(30, 41, 59, 0.5);
    color: #94a3b8;
}
.filter-btn:hover, .filter-btn.active {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.4);
    color: #60a5fa;
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
[data-theme="light"] .date-group {
    background: rgba(248, 250, 252, 0.8);
    border-color: rgba(203, 213, 225, 0.5);
}
[data-theme="light"] .date-header {
    background: rgba(241, 245, 249, 0.9);
}
</style>

<div class="history-container">
    <div class="history-header">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-500/15 border border-blue-500/25 flex items-center justify-center">
                    <i class="bi bi-person-lines-fill text-blue-400"></i>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-100">Historique des modifications</h2>
                    <p class="text-xs text-slate-500"><?= $historyCount ?> modification<?= $historyCount > 1 ? 's' : '' ?> enregistrée<?= $historyCount > 1 ? 's' : '' ?></p>
                </div>
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">Tous</button>
                <button class="filter-btn" data-filter="modification">Infos</button>
                <button class="filter-btn" data-filter="suspension">Suspension</button>
                <button class="filter-btn" data-filter="retrait">Retrait</button>
                <button class="filter-btn" data-filter="image">Photo</button>
            </div>
        </div>
    </div>
    
    <div class="p-4" id="historyContent">
        <?php if (count($historyByDate) > 0): ?>
            <?php foreach ($historyByDate as $date => $items): ?>
                <div class="date-group">
                    <div class="date-header">
                        <span class="text-sm font-medium text-slate-300">
                            <i class="bi bi-calendar3 mr-2"></i>
                            <?= date('d F Y', strtotime($date)) ?>
                        </span>
                        <span class="text-xs text-slate-500 ml-2">(<?= count($items) ?> modification<?= count($items) > 1 ? 's' : '' ?>)</span>
                    </div>
                    
                    <?php foreach ($items as $history): ?>
                        <div class="history-item" data-action="<?= $history['action_type'] ?>">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-grow">
                                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                                        <span class="action-badge action-<?= $history['action_type'] ?>">
                                            <?php 
                                            switch($history['action_type']) {
                                                case 'modification': echo '<i class="bi bi-pencil-fill mr-1"></i>Modification'; break;
                                                case 'creation': echo '<i class="bi bi-plus-circle-fill mr-1"></i>Création'; break;
                                                case 'suspension': echo '<i class="bi bi-person-dash-fill mr-1"></i>Suspension'; break;
                                                case 'levee_suspension': echo '<i class="bi bi-person-check-fill mr-1"></i>Levée suspension'; break;
                                                case 'retrait': echo '<i class="bi bi-door-open-fill mr-1"></i>Retrait'; break;
                                                case 'annulation_retrait': echo '<i class="bi bi-arrow-return-left mr-1"></i>Annul. retrait'; break;
                                                case 'image': echo '<i class="bi bi-image mr-1"></i>Photo'; break;
                                                case 'suppression': echo '<i class="bi bi-trash-fill mr-1"></i>Suppression'; break;
                                            }
                                            ?>
                                        </span>
                                        <span class="text-sm font-medium text-slate-300">
                                            <?= htmlspecialchars($history['field_label']) ?>
                                        </span>
                                        <span class="text-xs text-slate-500">
                                            à <?= date('H:i', strtotime($history['action_date'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="value-change mb-2">
                                        <?php if (!empty($history['old_value']) && $history['old_value'] != '0000-00-00'): ?>
                                            <span class="value-old" title="<?= htmlspecialchars($history['old_value']) ?>">
                                                <?= formatValueForDisplay($history['field_name'], $history['old_value']) ?>
                                            </span>
                                            <i class="bi bi-arrow-right value-arrow"></i>
                                        <?php endif; ?>
                                        <span class="value-new" title="<?= htmlspecialchars($history['new_value']) ?>">
                                            <?= formatValueForDisplay($history['field_name'], $history['new_value']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center gap-4 text-xs text-slate-500 flex-wrap">
                                        <span>
                                            <i class="bi bi-person-fill mr-1"></i>
                                            <?= htmlspecialchars($history['user_prenom'] ?? 'Inconnu') ?> <?= htmlspecialchars($history['user_nom'] ?? '') ?>
                                        </span>
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
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-history">
                <i class="bi bi-inbox text-slate-600 text-4xl"></i>
                <p class="text-slate-500 mt-3">Aucune modification enregistrée</p>
                <p class="text-slate-600 text-xs mt-1">Les modifications futures seront affichées ici</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        document.querySelectorAll('.history-item').forEach(item => {
            if (filter === 'all') {
                item.style.display = '';
            } else {
                const action = item.dataset.action;
                if (filter === 'suspension' && (action === 'suspension' || action === 'levee_suspension')) {
                    item.style.display = '';
                } else if (filter === 'retrait' && (action === 'retrait' || action === 'annulation_retrait')) {
                    item.style.display = '';
                } else if (action === filter) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            }
        });
        
        // Cacher les groupes vides
        document.querySelectorAll('.date-group').forEach(group => {
            const visibleItems = group.querySelectorAll('.history-item[style=""], .history-item:not([style])');
            group.style.display = visibleItems.length > 0 ? '' : 'none';
        });
    });
});
</script>
</div>
