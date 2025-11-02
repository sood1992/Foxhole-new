<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$today = date('Y-m-d');

// Get today's planned tasks
$plannedTasks = $db->prepare("
    SELECT t.*, p.project_name, dp.completed, dp.id as plan_id
    FROM daily_plans dp
    JOIN tasks t ON dp.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    WHERE dp.user_id = ? AND dp.plan_date = ?
    ORDER BY dp.completed ASC, t.priority DESC
");
$plannedTasks->execute([$currentUser['id'], $today]);
$todayTasks = $plannedTasks->fetchAll();

// Get available tasks (not planned for today yet)
$availableTasks = $db->prepare("
    SELECT t.*, p.project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
        AND t.status IN ('todo', 'in_progress', 'review')
        AND t.id NOT IN (
            SELECT task_id FROM daily_plans
            WHERE user_id = ? AND plan_date = ?
        )
    ORDER BY
        FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
        t.due_date ASC
");
$availableTasks->execute([$currentUser['id'], $currentUser['id'], $today]);
$availableTasksList = $availableTasks->fetchAll();

// Calculate stats
$totalPlanned = count($todayTasks);
$completed = count(array_filter($todayTasks, fn($t) => $t['completed']));
$progressPercent = $totalPlanned > 0 ? round(($completed / $totalPlanned) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Plan - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .daily-plan-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
        }

        .plan-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .plan-column {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
        }

        .task-item-draggable {
            background: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            padding: 15px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.2s;
        }

        .task-item-draggable:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .task-item-draggable.completed {
            opacity: 0.6;
            background: var(--success-light);
        }

        .drag-over {
            border-color: var(--primary);
            background: var(--primary-light);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php
        if (hasAnyRole(['employee'])) {
            include '../includes/v3-employee-sidebar.php';
        } elseif (hasAnyRole(['manager'])) {
            include '../includes/v3-manager-sidebar.php';
        }
        ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Daily Plan Header -->
                <div class="daily-plan-header">
                    <h1 style="font-size: 32px; margin-bottom: 10px;">
                        <i class="fas fa-calendar-day"></i> Today's Plan
                    </h1>
                    <p style="font-size: 16px; opacity: 0.9;"><?php echo date('l, F j, Y'); ?></p>

                    <div style="margin-top: 20px; display: flex; gap: 30px;">
                        <div>
                            <div style="font-size: 36px; font-weight: 700;"><?php echo $totalPlanned; ?></div>
                            <div style="font-size: 14px; opacity: 0.8;">Tasks Planned</div>
                        </div>
                        <div>
                            <div style="font-size: 36px; font-weight: 700;"><?php echo $completed; ?></div>
                            <div style="font-size: 14px; opacity: 0.8;">Completed</div>
                        </div>
                        <div>
                            <div style="font-size: 36px; font-weight: 700;"><?php echo $progressPercent; ?>%</div>
                            <div style="font-size: 14px; opacity: 0.8;">Progress</div>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <div class="progress-bar-container" style="height: 8px; background: rgba(255,255,255,0.2);">
                            <div class="progress-bar" style="width: <?php echo $progressPercent; ?>%; background: #fff;"></div>
                        </div>
                    </div>
                </div>

                <div class="plan-columns">
                    <!-- Today's Tasks -->
                    <div class="plan-column">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-star" style="color: #f8b739;"></i> Today's Tasks
                        </h3>

                        <div id="todayTasks" class="dropzone">
                            <?php if (empty($todayTasks)): ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <i class="fas fa-arrow-left" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                                    <p>Drag tasks here to plan your day</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($todayTasks as $task): ?>
                                    <div class="task-item-draggable <?php echo $task['completed'] ? 'completed' : ''; ?>"
                                         data-task-id="<?php echo $task['id']; ?>"
                                         data-plan-id="<?php echo $task['plan_id']; ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="flex: 1;">
                                                <input type="checkbox"
                                                       <?php echo $task['completed'] ? 'checked' : ''; ?>
                                                       onchange="toggleTaskComplete(<?php echo $task['plan_id']; ?>, this.checked)"
                                                       style="margin-right: 10px; transform: scale(1.2);">
                                                <strong><?php echo e($task['task_name']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                                    📁 <?php echo e($task['project_name']); ?>
                                                </div>
                                                <div style="margin-top: 5px;">
                                                    <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                                        <?php echo ucfirst($task['priority']); ?>
                                                    </span>
                                                    <?php if ($task['estimated_hours']): ?>
                                                        <span style="font-size: 12px; color: var(--text-secondary); margin-left: 10px;">
                                                            <i class="fas fa-clock"></i> <?php echo $task['estimated_hours']; ?>h
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button onclick="removeFromPlan(<?php echo $task['plan_id']; ?>)"
                                                    class="btn btn-sm"
                                                    style="background: var(--danger); color: white;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Available Tasks -->
                    <div class="plan-column">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-list"></i> Available Tasks
                        </h3>

                        <div id="availableTasks">
                            <?php if (empty($availableTasksList)): ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                                    <p>All tasks are planned! 🎉</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableTasksList as $task): ?>
                                    <div class="task-item-draggable"
                                         data-task-id="<?php echo $task['id']; ?>"
                                         draggable="true">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="flex: 1;">
                                                <strong><?php echo e($task['task_name']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                                    📁 <?php echo e($task['project_name']); ?>
                                                </div>
                                                <div style="margin-top: 5px;">
                                                    <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                                        <?php echo ucfirst($task['priority']); ?>
                                                    </span>
                                                    <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                    </span>
                                                    <?php if ($task['estimated_hours']): ?>
                                                        <span style="font-size: 12px; color: var(--text-secondary); margin-left: 10px;">
                                                            <i class="fas fa-clock"></i> <?php echo $task['estimated_hours']; ?>h
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button onclick="addToPlan(<?php echo $task['id']; ?>)"
                                                    class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add task to today's plan
        async function addToPlan(taskId) {
            try {
                const response = await fetch('../api/daily-plan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', task_id: taskId })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to add task');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to add task to plan');
            }
        }

        // Remove task from plan
        async function removeFromPlan(planId) {
            try {
                const response = await fetch('../api/daily-plan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'remove', plan_id: planId })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to remove task');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to remove task from plan');
            }
        }

        // Toggle task completion
        async function toggleTaskComplete(planId, completed) {
            try {
                const response = await fetch('../api/daily-plan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_complete',
                        plan_id: planId,
                        completed: completed
                    })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to update task');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to update task');
            }
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
