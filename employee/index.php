<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get all active tasks being worked on (background tracking)
$stmt = $db->prepare("
    SELECT DISTINCT t.*, p.project_name,
           COUNT(tl.id) as active_sessions,
           MIN(tl.start_time) as first_started
    FROM time_logs tl
    JOIN tasks t ON tl.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    WHERE tl.user_id = ? AND tl.is_active = 1
    GROUP BY t.id
    ORDER BY first_started DESC
");
$stmt->execute([$currentUser['id']]);
$activeTasks = $stmt->fetchAll();

// Get statistics
$stats = [];

// My active tasks
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status IN ('todo', 'in_progress', 'review')");
$stmt->execute([$currentUser['id']]);
$stats['my_tasks'] = $stmt->fetch()['count'];

// Completed this week
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed' AND WEEK(completed_date) = WEEK(CURRENT_DATE())");
$stmt->execute([$currentUser['id']]);
$stats['completed_week'] = $stmt->fetch()['count'];

// Hours this week
$stmt = $db->prepare("SELECT SUM(duration_minutes) as total FROM time_logs WHERE user_id = ? AND WEEK(start_time) = WEEK(CURRENT_DATE())");
$stmt->execute([$currentUser['id']]);
$totalMinutes = $stmt->fetch()['total'] ?? 0;
$stats['hours_week'] = formatHours($totalMinutes);

// Projects involved
$stmt = $db->prepare("SELECT COUNT(DISTINCT project_id) as count FROM tasks WHERE assigned_to = ?");
$stmt->execute([$currentUser['id']]);
$stats['projects'] = $stmt->fetch()['count'];

// Get my tasks
$myTasks = $db->prepare("
    SELECT t.*, p.project_name, p.client_name, p.status as project_status
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
        AND t.status != 'completed'
    ORDER BY
        FIELD(t.status, 'in_progress', 'review', 'todo', 'blocked'),
        t.priority DESC,
        t.due_date ASC
");
$myTasks->execute([$currentUser['id']]);
$tasksData = $myTasks->fetchAll();

// Get today's time logs
$todayLogs = $db->prepare("
    SELECT tl.*, p.project_name, t.task_name
    FROM time_logs tl
    JOIN projects p ON tl.project_id = p.id
    JOIN tasks t ON tl.task_id = t.id
    WHERE tl.user_id = ?
        AND DATE(tl.start_time) = CURDATE()
    ORDER BY tl.start_time DESC
");
$todayLogs->execute([$currentUser['id']]);
$todayLogsData = $todayLogs->fetchAll();

// Get this week's summary
$weekSummary = $db->prepare("
    SELECT
        p.project_name,
        SUM(tl.duration_minutes) as total_minutes,
        COUNT(DISTINCT tl.task_id) as tasks_count
    FROM time_logs tl
    JOIN projects p ON tl.project_id = p.id
    WHERE tl.user_id = ?
        AND WEEK(tl.start_time) = WEEK(CURRENT_DATE())
        AND tl.end_time IS NOT NULL
    GROUP BY p.id
    ORDER BY total_minutes DESC
");
$weekSummary->execute([$currentUser['id']]);
$weekSummaryData = $weekSummary->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Active Tasks (Background Tracking - No Visible Timer) -->
                <?php if (!empty($activeTasks)): ?>
                <div class="dashboard-card" style="margin-bottom: 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.2); padding: 16px 20px;">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: white;">
                            <i class="fas fa-briefcase"></i>
                            Currently Working On (<?php echo count($activeTasks); ?> <?php echo count($activeTasks) == 1 ? 'Task' : 'Tasks'; ?>)
                        </h3>
                        <p style="margin: 8px 0 0; opacity: 0.9; font-size: 13px;">Time is being tracked automatically in the background</p>
                    </div>
                    <div class="card-body" style="padding: 12px;">
                        <?php foreach ($activeTasks as $task): ?>
                        <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 8px; padding: 12px 16px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">
                                    <?php echo e($task['task_name']); ?>
                                </div>
                                <div style="font-size: 13px; opacity: 0.9;">
                                    📁 <?php echo e($task['project_name']); ?>
                                    <span style="margin-left: 12px;">⏱️ Started <?php echo timeAgo($task['first_started']); ?></span>
                                </div>
                            </div>
                            <button onclick="stopTask(<?php echo $task['id']; ?>)" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 6px 16px;">
                                ✓ Stop Working
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="dashboard-card">
                        <div class="card-icon gradient-blue">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">My Tasks</div>
                            <div class="card-value"><?php echo $stats['my_tasks']; ?></div>
                            <div class="card-change">Active</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Completed</div>
                            <div class="card-value"><?php echo $stats['completed_week']; ?></div>
                            <div class="card-change">This Week</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-orange">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Hours Logged</div>
                            <div class="card-value"><?php echo $stats['hours_week']; ?></div>
                            <div class="card-change">This Week</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-purple">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Projects</div>
                            <div class="card-value"><?php echo $stats['projects']; ?></div>
                            <div class="card-change">Involved</div>
                        </div>
                    </div>
                </div>

                <!-- My Tasks -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>My Tasks</h3>
                    </div>
                    <div class="card-body">
                        <div class="task-list">
                            <?php foreach ($tasksData as $task): ?>
                            <?php $canStartTimer = !$activeTimeLog || $activeTimeLog['task_id'] != $task['id']; ?>
                            <div class="task-item <?php echo isOverdue($task['due_date'], $task['status']) ? 'overdue' : ''; ?>">
                                <div class="task-item-header">
                                    <div>
                                        <div class="task-item-title"><?php echo e($task['task_name']); ?></div>
                                        <div class="task-item-meta">
                                            <span>📁 <?php echo e($task['project_name']); ?></span>
                                            <?php if ($task['client_name']): ?>
                                                <span>👤 <?php echo e($task['client_name']); ?></span>
                                            <?php endif; ?>
                                            <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                            <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="task-item-actions">
                                        <?php if ($canStartTimer): ?>
                                            <button onclick="startTimer(<?php echo $task['id']; ?>, <?php echo $task['project_id']; ?>)"
                                                    class="btn btn-success btn-sm">
                                                ▶️ Start
                                            </button>
                                        <?php else: ?>
                                            <span class="badge status-progress">⏱️ Active</span>
                                        <?php endif; ?>
                                        <a href="tasks.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                    </div>
                                </div>
                                <?php if ($task['description']): ?>
                                <div style="margin-top: 12px; font-size: 14px; color: var(--text-secondary);">
                                    <?php echo nl2br(e($task['description'])); ?>
                                </div>
                                <?php endif; ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 16px;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Estimated</div>
                                        <div style="font-size: 16px; font-weight: 600;">
                                            <?php echo $task['estimated_hours'] ? number_format($task['estimated_hours'], 1) . 'h' : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Actual</div>
                                        <div style="font-size: 16px; font-weight: 600;">
                                            <?php echo formatHours($task['actual_hours'] ?? 0); ?>h
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Due Date</div>
                                        <div style="font-size: 14px; font-weight: 500;">
                                            <?php
                                            if ($task['due_date']) {
                                                echo date('M d, Y', strtotime($task['due_date']));
                                                if (isOverdue($task['due_date'], $task['status'])) {
                                                    echo ' <span style="color: var(--status-blocked);">⚠️</span>';
                                                }
                                            } else {
                                                echo 'No deadline';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (empty($tasksData)): ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <div style="font-size: 48px; margin-bottom: 16px;">✓</div>
                                    <h3>All Caught Up!</h3>
                                    <p>You have no active tasks at the moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Today's Time Logs -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Today's Time Logs</h3>
                        <span style="color: var(--text-secondary); font-size: 14px;">
                            Total: <?php
                                $todayTotal = 0;
                                foreach ($todayLogsData as $log) {
                                    if ($log['end_time']) {
                                        $todayTotal += $log['duration_minutes'];
                                    }
                                }
                                echo formatHours($todayTotal);
                            ?>h
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayLogsData as $log): ?>
                                    <tr>
                                        <td><?php echo e($log['project_name']); ?></td>
                                        <td><?php echo e($log['task_name']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($log['start_time'])); ?></td>
                                        <td>
                                            <?php echo $log['end_time'] ? date('h:i A', strtotime($log['end_time'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php
                                                if ($log['end_time']) {
                                                    echo formatDuration($log['duration_minutes']);
                                                } else {
                                                    echo 'In Progress';
                                                }
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($log['is_active']): ?>
                                                <span class="badge status-progress">⏱️ Active</span>
                                            <?php else: ?>
                                                <span class="badge status-completed">✓ Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($todayLogsData)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                            No time logs for today. Start a task to begin tracking time!
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- This Week's Summary -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>This Week's Summary by Project</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Tasks Worked</th>
                                        <th>Total Hours</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weekSummaryData as $summary): ?>
                                    <?php
                                        $percentage = $totalMinutes > 0 ? ($summary['total_minutes'] / $totalMinutes * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo e($summary['project_name']); ?></strong></td>
                                        <td><?php echo $summary['tasks_count']; ?> tasks</td>
                                        <td><strong><?php echo formatHours($summary['total_minutes']); ?>h</strong></td>
                                        <td>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar high" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <div style="font-size: 13px; margin-top: 4px;">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($weekSummaryData)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                            No time logs this week yet.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Background task tracking functions (no visible timer)
        function startTimer(taskId, projectId) {
            fetch('../api/time-tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'start',
                    task_id: taskId,
                    project_id: projectId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const message = document.createElement('div');
                    message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; animation: slideIn 0.3s ease;';
                    message.innerHTML = '<i class="fas fa-check-circle"></i> Task started - tracking in background';
                    document.body.appendChild(message);
                    setTimeout(() => {
                        message.remove();
                        window.location.reload();
                    }, 1500);
                } else {
                    alert(data.message || 'Failed to start task');
                }
            })
            .catch(error => {
                alert('Error starting task');
                console.error(error);
            });
        }

        function stopTask(taskId) {
            if (confirm('Stop working on this task? Time will be automatically calculated.')) {
                const notes = prompt('Add notes for this work session (optional):');

                fetch('../api/time-tracking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'stop_by_task',
                        task_id: taskId,
                        notes: notes || ''
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const minutes = data.total_duration_minutes || 0;
                        const hours = Math.floor(minutes / 60);
                        const mins = minutes % 60;
                        const timeStr = hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;

                        alert(`Task stopped! Total time: ${timeStr}`);
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to stop task');
                    }
                })
                .catch(error => {
                    alert('Error stopping task');
                    console.error(error);
                });
            }
        }

        // Legacy function for compatibility
        function stopTimer(logId) {
            const notes = prompt('Add notes for this work session (optional):');
            fetch('../api/time-tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'stop',
                    log_id: logId,
                    notes: notes || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to stop');
                }
            })
            .catch(error => {
                alert('Error stopping');
                console.error(error);
            });
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
