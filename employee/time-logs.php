<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get all time logs for this user
$timeLogs = $db->prepare("
    SELECT
        tl.*,
        p.project_name,
        p.client_name,
        t.task_name
    FROM time_logs tl
    JOIN projects p ON tl.project_id = p.id
    JOIN tasks t ON tl.task_id = t.id
    WHERE tl.user_id = ?
        AND DATE(tl.start_time) BETWEEN ? AND ?
    ORDER BY tl.start_time DESC
");
$timeLogs->execute([$currentUser['id'], $startDate, $endDate]);
$logsData = $timeLogs->fetchAll();

// Calculate totals
$totalMinutes = 0;
$totalSessions = count($logsData);
foreach ($logsData as $log) {
    $totalMinutes += $log['duration_minutes'];
}

// Get summary by project
$projectSummary = $db->prepare("
    SELECT
        p.project_name,
        p.client_name,
        SUM(tl.duration_minutes) as total_minutes,
        COUNT(*) as sessions
    FROM time_logs tl
    JOIN projects p ON tl.project_id = p.id
    WHERE tl.user_id = ?
        AND DATE(tl.start_time) BETWEEN ? AND ?
        AND tl.end_time IS NOT NULL
    GROUP BY p.id
    ORDER BY total_minutes DESC
");
$projectSummary->execute([$currentUser['id'], $startDate, $endDate]);
$projectData = $projectSummary->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Time Logs - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
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
                <a href="index.php">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="tasks.php">
                    <span class="icon">✓</span>
                    My Tasks
                </a>
                <a href="calendar.php">
                    <span class="icon">📅</span>
                    Calendar
                </a>
                <a href="chat.php">
                    <span class="icon">💬</span>
                    Team Chat
                </a>
                <a href="time-logs.php" class="active">
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
                <h1>⏱️ My Time Logs</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <!-- Filter Form -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <form method="GET" style="display: flex; gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                                <label>Start Date</label>
                                <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="form-control">
                            </div>
                            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                                <label>End Date</label>
                                <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </form>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Hours</div>
                                <div class="stat-value"><?php echo formatHours($totalMinutes); ?></div>
                                <div class="stat-change">In selected period</div>
                            </div>
                            <div class="stat-icon">⏰</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Sessions</div>
                                <div class="stat-value"><?php echo $totalSessions; ?></div>
                                <div class="stat-change">Total work sessions</div>
                            </div>
                            <div class="stat-icon">📝</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Avg Session</div>
                                <div class="stat-value">
                                    <?php echo $totalSessions > 0 ? formatHours($totalMinutes / $totalSessions) : '0.00'; ?>
                                </div>
                                <div class="stat-change">Hours per session</div>
                            </div>
                            <div class="stat-icon">📊</div>
                        </div>
                    </div>
                </div>

                <!-- Project Summary -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3>📁 Time by Project</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projectData)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No time logged in this period
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Client</th>
                                        <th>Sessions</th>
                                        <th>Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projectData as $project): ?>
                                    <tr>
                                        <td><strong><?php echo e($project['project_name']); ?></strong></td>
                                        <td><?php echo e($project['client_name']); ?></td>
                                        <td><?php echo $project['sessions']; ?></td>
                                        <td><strong><?php echo formatHours($project['total_minutes']); ?> hrs</strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detailed Time Logs -->
                <div class="card">
                    <div class="card-header">
                        <h3>📋 Detailed Time Logs</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logsData)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No time logs found for this period
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logsData as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($log['start_time'])); ?></td>
                                        <td>
                                            <strong><?php echo e($log['project_name']); ?></strong><br>
                                            <small style="color: var(--text-secondary);"><?php echo e($log['client_name']); ?></small>
                                        </td>
                                        <td><?php echo e($log['task_name']); ?></td>
                                        <td><?php echo date('g:i A', strtotime($log['start_time'])); ?></td>
                                        <td>
                                            <?php if ($log['end_time']): ?>
                                                <?php echo date('g:i A', strtotime($log['end_time'])); ?>
                                            <?php else: ?>
                                                <span class="badge status-progress">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo formatDuration($log['duration_minutes']); ?></strong></td>
                                        <td>
                                            <?php if ($log['notes']): ?>
                                                <small><?php echo e($log['notes']); ?></small>
                                            <?php else: ?>
                                                <small style="color: var(--text-tertiary);">-</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
