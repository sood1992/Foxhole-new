<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get filter parameters
$reportType = $_GET['type'] ?? 'weekly';
$employeeId = $_GET['employee'] ?? 'all';

// Calculate date ranges
$today = date('Y-m-d');
switch ($reportType) {
    case 'daily':
        $startDate = $today;
        $endDate = $today;
        $label = 'Today';
        break;
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $label = 'This Week';
        break;
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $label = 'This Month';
        break;
    case 'custom':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? $today;
        $label = 'Custom Range';
        break;
    default:
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $label = 'This Week';
}

// Get all employees for filter
$employees = $db->query("SELECT id, full_name, job_title FROM users WHERE role IN ('manager', 'employee') AND is_active = 1 ORDER BY full_name")->fetchAll();

// Build employee filter
$employeeFilter = "";
$reportParams = [$startDate, $endDate];
if ($employeeId !== 'all') {
    $employeeFilter = "AND tl.user_id = ?";
    $reportParams[] = $employeeId;
}

// Get detailed time logs
$timeLogs = $db->prepare("
    SELECT
        tl.*,
        u.full_name,
        u.job_title,
        p.project_name,
        t.task_name
    FROM time_logs tl
    JOIN users u ON tl.user_id = u.id
    JOIN projects p ON tl.project_id = p.id
    JOIN tasks t ON tl.task_id = t.id
    WHERE DATE(tl.start_time) BETWEEN ? AND ?
        AND tl.end_time IS NOT NULL
        $employeeFilter
    ORDER BY tl.start_time DESC
");
$timeLogs->execute($reportParams);
$timeLogsData = $timeLogs->fetchAll();

// Get employee summary
$employeeSummary = $db->prepare("
    SELECT
        u.id,
        u.full_name,
        u.job_title,
        COUNT(DISTINCT tl.project_id) as projects_count,
        COUNT(DISTINCT tl.task_id) as tasks_count,
        SUM(tl.duration_minutes) as total_minutes,
        COUNT(tl.id) as sessions_count,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed' AND DATE(completed_date) BETWEEN ? AND ?) as completed_tasks
    FROM users u
    LEFT JOIN time_logs tl ON u.id = tl.user_id
        AND DATE(tl.start_time) BETWEEN ? AND ?
        AND tl.end_time IS NOT NULL
    WHERE u.role IN ('manager', 'employee')
        AND u.is_active = 1
        $employeeFilter
    GROUP BY u.id
    ORDER BY total_minutes DESC
");
$employeeSummary->execute(array_merge([$startDate, $endDate], $reportParams));
$employeeSummaryData = $employeeSummary->fetchAll();

// Get project summary
$projectSummary = $db->prepare("
    SELECT
        p.id,
        p.project_name,
        p.client_name,
        p.status,
        p.priority,
        COUNT(DISTINCT tl.user_id) as team_members,
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(tl.duration_minutes) as total_minutes,
        p.estimated_hours
    FROM projects p
    LEFT JOIN time_logs tl ON p.id = tl.project_id
        AND DATE(tl.start_time) BETWEEN ? AND ?
        AND tl.end_time IS NOT NULL
    LEFT JOIN tasks t ON p.id = t.project_id
    WHERE p.status IN ('planning', 'in_progress', 'review', 'completed')
    GROUP BY p.id
    HAVING total_minutes > 0
    ORDER BY total_minutes DESC
");
$projectSummary->execute([$startDate, $endDate]);
$projectSummaryData = $projectSummary->fetchAll();

// Calculate totals
$totalHours = 0;
$totalProjects = 0;
$totalTasks = 0;
foreach ($employeeSummaryData as $emp) {
    $totalHours += $emp['total_minutes'] ?? 0;
    $totalProjects += $emp['projects_count'];
    $totalTasks += $emp['completed_tasks'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Foxhole</h2>
                <div class="user-role">Admin Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="reports.php" class="active">
                    <span class="icon">📈</span>
                    Reports
                </a>
                <a href="projects.php">
                    <span class="icon">📁</span>
                    Projects
                </a>
                <a href="team.php">
                    <span class="icon">👥</span>
                    Team Management
                </a>
                <a href="users.php">
                    <span class="icon">⚙️</span>
                    User Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'Administrator'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Reports & Analytics</h1>
                <div class="topbar-actions">
                    <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Print Report</button>
                </div>
            </div>

            <div class="content">
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Report Period</label>
                                <select name="type" onchange="toggleCustomDates(this.value)">
                                    <option value="daily" <?php echo $reportType === 'daily' ? 'selected' : ''; ?>>Today</option>
                                    <option value="weekly" <?php echo $reportType === 'weekly' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="monthly" <?php echo $reportType === 'monthly' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="custom" <?php echo $reportType === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;" id="customStartDate" <?php echo $reportType !== 'custom' ? 'style="display:none;"' : ''; ?>>
                                <label>Start Date</label>
                                <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;" id="customEndDate" <?php echo $reportType !== 'custom' ? 'style="display:none;"' : ''; ?>>
                                <label>End Date</label>
                                <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Employee</label>
                                <select name="employee">
                                    <option value="all">All Employees</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo $employeeId == $emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($emp['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Generate Report</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Header -->
                <div style="background: white; padding: 24px; border-radius: var(--radius-md); margin-bottom: 24px; text-align: center; border: 2px solid var(--primary);">
                    <h2 style="margin-bottom: 8px;"><?php echo $label; ?> Report</h2>
                    <p style="color: var(--text-secondary);">
                        <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                    </p>
                </div>

                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Hours</div>
                                <div class="stat-value"><?php echo formatHours($totalHours); ?></div>
                                <div class="stat-change">Logged</div>
                            </div>
                            <div class="stat-icon">⏱️</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Tasks Completed</div>
                                <div class="stat-value"><?php echo $totalTasks; ?></div>
                                <div class="stat-change">Done</div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Projects Active</div>
                                <div class="stat-value"><?php echo count($projectSummaryData); ?></div>
                                <div class="stat-change">In Period</div>
                            </div>
                            <div class="stat-icon">📁</div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Avg per Employee</div>
                                <div class="stat-value"><?php echo count($employeeSummaryData) > 0 ? formatHours($totalHours / count($employeeSummaryData)) : '0.00'; ?></div>
                                <div class="stat-change">Hours</div>
                            </div>
                            <div class="stat-icon">📊</div>
                        </div>
                    </div>
                </div>

                <!-- Employee Summary -->
                <div class="card">
                    <div class="card-header">
                        <h3>Employee Performance</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Role</th>
                                        <th>Projects</th>
                                        <th>Tasks Done</th>
                                        <th>Work Sessions</th>
                                        <th>Total Hours</th>
                                        <th>Avg/Day</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeeSummaryData as $emp): ?>
                                    <?php
                                        $empHours = formatHours($emp['total_minutes'] ?? 0);
                                        $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
                                        $avgPerDay = $days > 0 ? formatHours(($emp['total_minutes'] ?? 0) / $days) : '0.00';
                                        $hoursNum = ($emp['total_minutes'] ?? 0) / 60;

                                        if ($hoursNum >= 30) {
                                            $performance = '<span class="badge status-completed">Excellent</span>';
                                        } elseif ($hoursNum >= 20) {
                                            $performance = '<span class="badge status-progress">Good</span>';
                                        } elseif ($hoursNum >= 10) {
                                            $performance = '<span class="badge status-review">Average</span>';
                                        } elseif ($hoursNum > 0) {
                                            $performance = '<span class="badge status-blocked">Low</span>';
                                        } else {
                                            $performance = '<span class="badge status-todo">No Activity</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo e($emp['full_name']); ?></strong></td>
                                        <td><?php echo e($emp['job_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo $emp['projects_count']; ?></td>
                                        <td><?php echo $emp['completed_tasks']; ?></td>
                                        <td><?php echo $emp['sessions_count']; ?></td>
                                        <td><strong><?php echo $empHours; ?>h</strong></td>
                                        <td><?php echo $avgPerDay; ?>h</td>
                                        <td><?php echo $performance; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Project Summary -->
                <div class="card">
                    <div class="card-header">
                        <h3>Project Time Breakdown</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Client</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Team Size</th>
                                        <th>Tasks</th>
                                        <th>Hours Logged</th>
                                        <th>Estimated</th>
                                        <th>Variance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projectSummaryData as $proj): ?>
                                    <?php
                                        $loggedHours = formatHours($proj['total_minutes'] ?? 0);
                                        $estimated = $proj['estimated_hours'] ?? 0;
                                        $variance = 0;
                                        $varianceClass = '';
                                        if ($estimated > 0) {
                                            $variance = (($proj['total_minutes'] / 60) - $estimated) / $estimated * 100;
                                            $varianceClass = $variance > 10 ? 'status-blocked' : ($variance < -10 ? 'status-completed' : 'status-progress');
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo e($proj['project_name']); ?></strong></td>
                                        <td><?php echo e($proj['client_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusClass($proj['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $proj['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPriorityClass($proj['priority']); ?>">
                                                <?php echo ucfirst($proj['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $proj['team_members']; ?> members</td>
                                        <td><?php echo $proj['completed_tasks']; ?>/<?php echo $proj['total_tasks']; ?></td>
                                        <td><strong><?php echo $loggedHours; ?>h</strong></td>
                                        <td><?php echo number_format($estimated, 1); ?>h</td>
                                        <td>
                                            <?php if ($estimated > 0): ?>
                                                <span class="badge <?php echo $varianceClass; ?>">
                                                    <?php echo $variance > 0 ? '+' : ''; ?><?php echo number_format($variance, 1); ?>%
                                                </span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detailed Time Logs -->
                <div class="card">
                    <div class="card-header">
                        <h3>Detailed Time Logs</h3>
                        <span style="color: var(--text-secondary); font-size: 14px;">
                            <?php echo count($timeLogsData); ?> entries
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeLogsData as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($log['start_time'])); ?></td>
                                        <td><?php echo e($log['full_name']); ?></td>
                                        <td><?php echo e($log['project_name']); ?></td>
                                        <td><?php echo e($log['task_name']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($log['start_time'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($log['end_time'])); ?></td>
                                        <td><strong><?php echo formatDuration($log['duration_minutes']); ?></strong></td>
                                        <td><?php echo e($log['notes'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleCustomDates(value) {
            const startDateDiv = document.getElementById('customStartDate');
            const endDateDiv = document.getElementById('customEndDate');
            if (value === 'custom') {
                startDateDiv.style.display = 'block';
                endDateDiv.style.display = 'block';
            } else {
                startDateDiv.style.display = 'none';
                endDateDiv.style.display = 'none';
            }
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
