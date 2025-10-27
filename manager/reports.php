<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get date range
$startDate = $_GET['start'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end'] ?? date('Y-m-t'); // Last day of current month

// Get project statistics
$stmt = $db->prepare("
    SELECT
        p.id,
        p.project_name,
        p.client_name,
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE project_id = p.id AND DATE(start_time) BETWEEN ? AND ?) as total_minutes
    FROM projects p
    LEFT JOIN tasks t ON p.id = t.project_id
    WHERE p.assigned_manager = ?
    GROUP BY p.id
    ORDER BY p.project_name
");
$stmt->execute([$startDate, $endDate, $currentUser['id']]);
$projectReports = $stmt->fetchAll();

// Get team member statistics
$stmt = $db->prepare("
    SELECT
        u.id,
        u.full_name,
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' AND DATE(t.completed_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed_tasks,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id AND DATE(start_time) BETWEEN ? AND ?) as total_minutes,
        (SELECT COUNT(*) FROM time_logs WHERE user_id = u.id AND DATE(start_time) BETWEEN ? AND ?) as sessions
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE p.assigned_manager = ?
        AND u.role IN ('employee', 'manager')
        AND u.is_active = 1
    GROUP BY u.id
    ORDER BY completed_tasks DESC
");
$stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $currentUser['id']]);
$teamReports = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/manager-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>📋 Reports</h1>
                <div class="topbar-actions">
                    <?php include '../includes/global-search-assets.php'; ?>
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <!-- Date Filter -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-body">
                        <form method="GET" style="display: flex; gap: var(--space-4); align-items: end;">
                            <div class="form-group" style="margin: 0;">
                                <label for="start">Start Date</label>
                                <input type="date" id="start" name="start" value="<?php echo e($startDate); ?>" class="form-control">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="end">End Date</label>
                                <input type="date" id="end" name="end" value="<?php echo e($endDate); ?>" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </form>
                    </div>
                </div>

                <!-- Project Reports -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-header">
                        <h3>📁 Project Reports</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projectReports)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-6);">
                                No project data for selected period.
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Client</th>
                                        <th>Total Tasks</th>
                                        <th>Completed</th>
                                        <th>Active</th>
                                        <th>Hours Logged</th>
                                        <th>Completion Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projectReports as $project): ?>
                                    <?php
                                    $completionRate = $project['total_tasks'] > 0 ?
                                        round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo e($project['project_name']); ?></strong></td>
                                        <td><?php echo e($project['client_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $project['total_tasks']; ?></td>
                                        <td><?php echo $project['completed_tasks']; ?></td>
                                        <td><?php echo $project['active_tasks']; ?></td>
                                        <td><?php echo formatHours($project['total_minutes'] ?? 0); ?>h</td>
                                        <td>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?php echo $completionRate; ?>%"></div>
                                            </div>
                                            <small style="color: var(--text-secondary);"><?php echo $completionRate; ?>%</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Team Member Reports -->
                <div class="card">
                    <div class="card-header">
                        <h3>👥 Team Performance</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teamReports)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-6);">
                                No team data for selected period.
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Team Member</th>
                                        <th>Tasks Completed</th>
                                        <th>Hours Logged</th>
                                        <th>Sessions</th>
                                        <th>Avg Session Length</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamReports as $member): ?>
                                    <?php
                                    $avgSession = $member['sessions'] > 0 ?
                                        ($member['total_minutes'] ?? 0) / $member['sessions'] : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo e($member['full_name']); ?></strong></td>
                                        <td>
                                            <span class="badge status-completed">
                                                <?php echo $member['completed_tasks']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatHours($member['total_minutes'] ?? 0); ?>h</td>
                                        <td><?php echo $member['sessions']; ?></td>
                                        <td><?php echo formatDuration($avgSession); ?></td>
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
