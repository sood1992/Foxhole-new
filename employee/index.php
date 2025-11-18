<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Timer removed - automatic time tracking based on task status

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
                                        <?php if ($task['status'] === 'todo'): ?>
                                            <button onclick="startWorking(<?php echo $task['id']; ?>)"
                                                    class="btn btn-success btn-sm">
                                                🚀 Start Working
                                            </button>
                                        <?php elseif ($task['status'] === 'in_progress'): ?>
                                            <button onclick="submitForReview(<?php echo $task['id']; ?>, '<?php echo addslashes($task['task_name']); ?>')"
                                                    class="btn btn-primary btn-sm">
                                                ✅ Submit
                                            </button>
                                        <?php elseif ($task['status'] === 'review'): ?>
                                            <span class="badge status-review">👀 In Review</span>
                                        <?php endif; ?>
                                        <a href="tasks.php" class="btn btn-secondary btn-sm">View All</a>
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
        // Task status management functions

        // Start working on a task
        function startWorking(taskId) {
            if (confirm('Start working on this task? Time tracking will begin automatically.')) {
                fetch('../api/task-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'start_working',
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to start working');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        // Submit for review with optional comment
        function submitForReview(taskId, taskName) {
            const comment = prompt(`Submit "${taskName}" for review?\n\nOptional: Add a completion note or summary:`, '');

            if (comment !== null) { // null means cancelled
                fetch('../api/task-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'submit_for_review',
                        task_id: taskId,
                        comment: comment
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to submit for review');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
