<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get period filter and set date range accordingly
$period = isset($_GET['period']) ? $_GET['period'] : 'month';

switch ($period) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01'); // First day of current month
        $endDate = date('Y-m-d'); // Today
        break;
    case 'custom':
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        break;
    default:
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
}

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
    <title>My Time Logs V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Filter Form -->
                <div class="dashboard-card" style="margin-bottom: 24px;">
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

                <!-- Tab Navigation -->
                <div class="tab-nav" style="margin-bottom: 30px;">
                    <a href="time-logs.php?period=today" class="tab-link <?php echo ($period === 'today') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-day"></i> Today
                    </a>
                    <a href="time-logs.php?period=week" class="tab-link <?php echo ($period === 'week') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i> This Week
                    </a>
                    <a href="time-logs.php?period=month" class="tab-link <?php echo ($period === 'month') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> This Month
                    </a>
                </div>

                <!-- Summary Stats -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-4 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-value"><?php echo formatHours($totalMinutes); ?>h</div>
                            <div class="card-label">Total Hours</div>
                            <div class="card-trend up">In selected period</div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="card-value"><?php echo $totalSessions; ?></div>
                            <div class="card-label">Sessions</div>
                            <div class="card-trend up">Total work sessions</div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Avg Session</div>
                            <div class="card-value">
                                <?php echo $totalSessions > 0 ? formatHours($totalMinutes / $totalSessions) : '0.00'; ?>
                            </div>
                            <div class="card-change">Hours per session</div>
                        </div>
                    </div>
                </div>

                <!-- Project Summary -->
                <div class="dashboard-card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3><i class="fas fa-folder"></i> Time by Project</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projectData)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No time logged in this period
                            </p>
                        <?php else: ?>
                            <table class="data-table">
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
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-list"></i> Detailed Time Logs</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logsData)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No time logs found for this period
                            </p>
                        <?php else: ?>
                            <table class="data-table">
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
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
