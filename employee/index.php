<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Check if user has active time tracking
$activeTimeLog = getActiveTimeLog($currentUser['id']);

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
    <title>My Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/clean-style.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Neofox</h2>
                <div class="user-role">Employee Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="active">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="tasks.php">
                    <span class="icon">✓</span>
                    My Tasks
                </a>
                <a href="time-logs.php">
                    <span class="icon">⏱️</span>
                    Time Logs
                </a>
                <a href="my-stats.php">
                    <span class="icon">📈</span>
                    My Statistics
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'Employee'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>My Dashboard</h1>
                <div class="topbar-actions">
                    <span style="color: var(--text-secondary); font-size: 14px;">
                        <?php echo date('l, F j, Y'); ?>
                    </span>
                </div>
            </div>

            <div class="content">
                <!-- Active Time Tracker -->
                <?php if ($activeTimeLog): ?>
                <?php
                    $startTime = strtotime($activeTimeLog['start_time']);
                    $elapsed = time() - $startTime;
                    $hours = floor($elapsed / 3600);
                    $minutes = floor(($elapsed % 3600) / 60);
                    $seconds = $elapsed % 60;
                ?>
                <div class="time-tracker">
                    <div class="time-tracker-active">
                        <div>
                            <div style="font-size: 14px; margin-bottom: 8px; opacity: 0.9;">Currently Working On:</div>
                            <div class="timer-task">
                                <strong><?php echo e($activeTimeLog['task_name'] ?? 'Task'); ?></strong>
                                <span style="opacity: 0.8; margin-left: 8px;">
                                    (<?php echo e($activeTimeLog['project_name'] ?? 'Project'); ?>)
                                </span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="timer-display" id="timer">
                                <?php printf('%02d:%02d:%02d', $hours, $minutes, $seconds); ?>
                            </div>
                            <button onclick="stopTimer(<?php echo $activeTimeLog['id']; ?>)" class="btn btn-danger" style="margin-top: 12px;">
                                ⏹ Stop Timer
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">My Tasks</div>
                                <div class="stat-value"><?php echo $stats['my_tasks']; ?></div>
                                <div class="stat-change">Active</div>
                            </div>
                            <div class="stat-icon">📋</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Completed</div>
                                <div class="stat-value"><?php echo $stats['completed_week']; ?></div>
                                <div class="stat-change">This Week</div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Hours Logged</div>
                                <div class="stat-value"><?php echo $stats['hours_week']; ?></div>
                                <div class="stat-change">This Week</div>
                            </div>
                            <div class="stat-icon">⏱️</div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Projects</div>
                                <div class="stat-value"><?php echo $stats['projects']; ?></div>
                                <div class="stat-change">Involved</div>
                            </div>
                            <div class="stat-icon">📁</div>
                        </div>
                    </div>
                </div>

                <!-- My Tasks -->
                <div class="card">
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
                <div class="card">
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
                            <table>
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
                <div class="card">
                    <div class="card-header">
                        <h3>This Week's Summary by Project</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
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
        </main>
    </div>

    <script>
        // Timer functions
        <?php if ($activeTimeLog): ?>
        let startTime = <?php echo $startTime; ?>;

        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - startTime;
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;

            document.getElementById('timer').textContent =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
        }

        setInterval(updateTimer, 1000);
        <?php endif; ?>

        function startTimer(taskId, projectId) {
            if (confirm('Start tracking time for this task?')) {
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
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to start timer');
                    }
                })
                .catch(error => {
                    alert('Error starting timer');
                    console.error(error);
                });
            }
        }

        function stopTimer(logId) {
            const notes = prompt('Add notes for this work session (optional):');

            fetch('../api/time-tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'stop',
                    log_id: logId,
                    notes: notes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to stop timer');
                }
            })
            .catch(error => {
                alert('Error stopping timer');
                console.error(error);
            });
        }
    </script>
</body>
</html>
