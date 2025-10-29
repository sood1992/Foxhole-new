<!-- Premium Task Card Component -->
<div class="task-card">
    <div class="task-card-header">
        <div class="task-priority <?php echo $task['priority']; ?>">
            <?php
            $priorityIcons = ['low' => '↓', 'medium' => '→', 'high' => '↑', 'urgent' => '⚡'];
            echo $priorityIcons[$task['priority']] ?? '→';
            ?>
        </div>
    </div>

    <div class="task-title">
        <?php echo e($task['task_name']); ?>
    </div>

    <div class="task-project">
        📁 <?php echo e($task['project_name']); ?>
    </div>

    <?php if ($task['description']): ?>
    <div class="task-description" style="font-size: var(--font-xs); color: var(--text-secondary); margin-bottom: var(--space-3); line-height: 1.5;">
        <?php echo e(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?>
    </div>
    <?php endif; ?>

    <div class="task-meta">
        <?php if ($task['estimated_hours']): ?>
        <div class="task-meta-item">
            <span>⏱️</span>
            <span><?php echo $task['estimated_hours']; ?>h est.</span>
        </div>
        <?php endif; ?>

        <?php if ($task['time_spent']): ?>
        <div class="task-meta-item">
            <span>⏰</span>
            <span><?php echo formatHours($task['time_spent']); ?>h logged</span>
        </div>
        <?php endif; ?>

        <?php if ($task['dependencies_count'] > 0): ?>
        <div class="task-meta-item">
            <span>🔗</span>
            <span><?php echo $task['dependencies_count']; ?> deps</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="task-footer">
        <div class="task-actions">
            <button class="task-action-btn" onclick="startTimer(<?php echo $task['id']; ?>, <?php echo $task['project_id']; ?>)">
                ▶ Start
            </button>
        </div>

        <?php if ($task['due_date']): ?>
        <div class="task-due-date <?php echo isOverdue($task['due_date'], $task['status']) ? 'overdue' : ''; ?>">
            <?php
            if (isOverdue($task['due_date'], $task['status'])) {
                echo '🔴 ' . date('M d', strtotime($task['due_date']));
            } else {
                $daysUntil = ceil((strtotime($task['due_date']) - time()) / 86400);
                if ($daysUntil == 0) {
                    echo '⚠️ Today';
                } elseif ($daysUntil == 1) {
                    echo '📅 Tomorrow';
                } elseif ($daysUntil <= 7) {
                    echo '📅 ' . $daysUntil . 'd';
                } else {
                    echo '📅 ' . date('M d', strtotime($task['due_date']));
                }
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>
