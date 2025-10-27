<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get statistics
$stats = [];

// Total employees
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role IN ('manager', 'employee') AND is_active = 1");
$stats['employees'] = $stmt->fetch()['count'];

// Active projects
$stmt = $db->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('planning', 'in_progress', 'review')");
$stats['active_projects'] = $stmt->fetch()['count'];

// Completed this month
$stmt = $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'completed' AND MONTH(completed_date) = MONTH(CURRENT_DATE()) AND YEAR(completed_date) = YEAR(CURRENT_DATE())");
$stats['completed_month'] = $stmt->fetch()['count'];

// Total hours this week
$stmt = $db->query("SELECT SUM(duration_minutes) as total FROM time_logs WHERE WEEK(start_time) = WEEK(CURRENT_DATE()) AND YEAR(start_time) = YEAR(CURRENT_DATE())");
$totalMinutes = $stmt->fetch()['total'] ?? 0;
$stats['hours_week'] = formatHours($totalMinutes);

// Get recent projects
$recentProjects = $db->query("
    SELECT p.*, u.full_name as manager_name,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
    FROM projects p
    LEFT JOIN users u ON p.assigned_manager = u.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll();

// Get team productivity
$teamProductivity = $db->query("
    SELECT
        u.id, u.full_name, u.job_title,
        COUNT(DISTINCT tl.project_id) as projects,
        COUNT(DISTINCT tl.task_id) as tasks,
        SUM(tl.duration_minutes) as total_minutes,
        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed' AND WEEK(completed_date) = WEEK(CURRENT_DATE())) as completed_this_week
    FROM users u
    LEFT JOIN time_logs tl ON u.id = tl.user_id
        AND WEEK(tl.start_time) = WEEK(CURRENT_DATE())
        AND YEAR(tl.start_time) = YEAR(CURRENT_DATE())
    WHERE u.role IN ('manager', 'employee') AND u.is_active = 1
    GROUP BY u.id
    ORDER BY total_minutes DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/premium-theme.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Neofox</h2>
                <div class="user-role">Admin Panel</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="active">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="calendar.php">
                    <span class="icon">📅</span>
                    Calendar
                </a>
                <a href="analytics.php">
                    <span class="icon">📈</span>
                    Analytics
                </a>
                <a href="chat.php">
                    <span class="icon">💬</span>
                    Team Chat
                </a>
                <a href="reports.php">
                    <span class="icon">📋</span>
                    Reports
                </a>
                <a href="projects.php">
                    <span class="icon">📁</span>
                    Projects
                </a>
                <a href="budget.php">
                    <span class="icon">💰</span>
                    Budget & Costs
                </a>
                <a href="team.php">
                    <span class="icon">👥</span>
                    Team Management
                </a>
                <a href="bulk-import.php">
                    <span class="icon">📥</span>
                    Bulk Import
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
                <h1>Dashboard Overview</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                    <span style="color: var(--text-secondary); font-size: 14px; margin-left: 12px;">
                        <?php echo date('l, F j, Y'); ?>
                    </span>
                </div>
            </div>

            <div class="content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Active Team</div>
                                <div class="stat-value"><?php echo $stats['employees']; ?></div>
                                <div class="stat-change">Members</div>
                            </div>
                            <div class="stat-icon">👥</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Active Projects</div>
                                <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
                                <div class="stat-change">In Progress</div>
                            </div>
                            <div class="stat-icon">📁</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Completed</div>
                                <div class="stat-value"><?php echo $stats['completed_month']; ?></div>
                                <div class="stat-change">This Month</div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Hours Logged</div>
                                <div class="stat-value"><?php echo $stats['hours_week']; ?></div>
                                <div class="stat-change">This Week</div>
                            </div>
                            <div class="stat-icon">⏱️</div>
                        </div>
                    </div>
                </div>

                <!-- Team Productivity -->
                <div class="card">
                    <div class="card-header">
                        <h3>Team Productivity (This Week)</h3>
                        <a href="reports.php" class="btn btn-secondary btn-sm">View All Reports</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Team Member</th>
                                        <th>Role</th>
                                        <th>Projects</th>
                                        <th>Tasks</th>
                                        <th>Completed</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamProductivity as $member): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($member['full_name']); ?></strong>
                                        </td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo $member['projects']; ?></td>
                                        <td><?php echo $member['tasks']; ?></td>
                                        <td>
                                            <span class="badge status-completed">
                                                <?php echo $member['completed_this_week']; ?> Done
                                            </span>
                                        </td>
                                        <td><?php echo formatHours($member['total_minutes'] ?? 0); ?>h</td>
                                        <td>
                                            <?php
                                            $hours = ($member['total_minutes'] ?? 0) / 60;
                                            if ($hours >= 30) {
                                                echo '<span class="badge status-completed">Active</span>';
                                            } elseif ($hours >= 15) {
                                                echo '<span class="badge status-progress">Moderate</span>';
                                            } elseif ($hours > 0) {
                                                echo '<span class="badge status-review">Low</span>';
                                            } else {
                                                echo '<span class="badge status-todo">Inactive</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Projects</h3>
                        <a href="projects.php" class="btn btn-primary btn-sm">+ New Project</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Client</th>
                                        <th>Manager</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Progress</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentProjects as $project): ?>
                                    <tr class="<?php echo isOverdue($project['due_date'], $project['status']) ? 'overdue' : ''; ?>">
                                        <td>
                                            <strong><?php echo e($project['project_name']); ?></strong>
                                        </td>
                                        <td><?php echo e($project['client_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo e($project['manager_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusClass($project['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPriorityClass($project['priority']); ?>">
                                                <?php echo ucfirst($project['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $completion = getProjectCompletionRate($project['id']);
                                            $progressClass = $completion == 100 ? 'complete' : ($completion > 50 ? 'high' : 'medium');
                                            ?>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo $completion; ?>%"></div>
                                            </div>
                                            <small style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
                                                <?php echo $completion; ?>% (<?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?> tasks)
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            if ($project['due_date']) {
                                                echo date('M d, Y', strtotime($project['due_date']));
                                                if (isOverdue($project['due_date'], $project['status'])) {
                                                    echo ' <span style="color: var(--status-blocked);">⚠️ Overdue</span>';
                                                }
                                            } else {
                                                echo 'No deadline';
                                            }
                                            ?>
                                        </td>
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

    <script src="../assets/js/theme.js"></script>
</body>
</html>
