<?php
/**
 * Admin Dashboard - Foxhole V3
 * Vien Admin Panel Design
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../login.php');
    exit;
}

// Check if user has admin role - if not, redirect to their correct dashboard
if (!hasRole('admin')) {
    switch ($_SESSION['role']) {
        case 'manager':
            redirect('../manager/index.php');
            break;
        case 'employee':
            redirect('../employee/index.php');
            break;
        default:
            // Unknown role - clear session and redirect to login
            session_destroy();
            redirect('../login.php');
            break;
    }
    exit;
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

// Get last month's completed for comparison
$stmt = $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'completed' AND MONTH(completed_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(completed_date) = YEAR(CURRENT_DATE())");
$stats['completed_last_month'] = $stmt->fetch()['count'];

// Calculate trend
$stats['completed_trend'] = 0;
if ($stats['completed_last_month'] > 0) {
    $stats['completed_trend'] = round((($stats['completed_month'] - $stats['completed_last_month']) / $stats['completed_last_month']) * 100);
}

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
    LIMIT 10
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
<body>
    <div class="app-container">
        <!-- V3 Sidebar -->
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- V3 Header -->
            <?php include '../includes/v3-header.php'; ?>

            <!-- Page Content -->
            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;">Dashboard Overview</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Welcome back, <?php echo e($currentUser['full_name']); ?>! Here's what's happening today.
                    </p>
                </div>

                <!-- Stats Grid -->
                <div class="row">
                    <!-- Active Team -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['employees']; ?></div>
                            <div class="card-label">Active Team Members</div>
                            <div class="card-trend up">All Active</div>
                        </div>
                    </div>

                    <!-- Active Projects -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['active_projects']; ?></div>
                            <div class="card-label">Active Projects</div>
                            <div class="card-trend up">In Progress</div>
                        </div>
                    </div>

                    <!-- Completed This Month -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['completed_month']; ?></div>
                            <div class="card-label">Completed This Month</div>
                            <?php if ($stats['completed_trend'] > 0): ?>
                                <div class="card-trend up"><?php echo $stats['completed_trend']; ?>%</div>
                            <?php elseif ($stats['completed_trend'] < 0): ?>
                                <div class="card-trend down"><?php echo abs($stats['completed_trend']); ?>%</div>
                            <?php else: ?>
                                <div class="card-trend up">No Change</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hours Logged -->
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['hours_week']; ?>h</div>
                            <div class="card-label">Hours This Week</div>
                            <div class="card-trend up">Total Logged</div>
                        </div>
                    </div>
                </div>

                <!-- Team Productivity -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">Team Productivity</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Performance overview for this week
                            </p>
                        </div>
                        <a href="reports.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-chart-bar"></i> View All Reports
                        </a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="sortable">Team Member</th>
                                        <th class="sortable">Role</th>
                                        <th class="sortable">Projects</th>
                                        <th class="sortable">Tasks</th>
                                        <th class="sortable">Completed</th>
                                        <th class="sortable">Hours</th>
                                        <th>Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($teamProductivity)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                                <div>No activity recorded this week</div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($teamProductivity as $member): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                                                    </div>
                                                    <strong><?php echo e($member['full_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo $member['projects']; ?></td>
                                            <td><?php echo $member['tasks']; ?></td>
                                            <td>
                                                <span class="badge badge-success">
                                                    <?php echo $member['completed_this_week']; ?> completed
                                                </span>
                                            </td>
                                            <td><strong><?php echo formatHours($member['total_minutes'] ?? 0); ?>h</strong></td>
                                            <td>
                                                <?php
                                                $hours = ($member['total_minutes'] ?? 0) / 60;
                                                if ($hours >= 30) {
                                                    echo '<span class="badge badge-success"><i class="fas fa-fire"></i> Highly Active</span>';
                                                } elseif ($hours >= 15) {
                                                    echo '<span class="badge badge-info">Active</span>';
                                                } elseif ($hours > 0) {
                                                    echo '<span class="badge badge-warning">Moderate</span>';
                                                } else {
                                                    echo '<span class="badge badge-secondary">Inactive</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">Recent Projects</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Latest projects in the system
                            </p>
                        </div>
                        <a href="projects.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Project
                        </a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="sortable">Project Name</th>
                                        <th class="sortable">Client</th>
                                        <th class="sortable">Manager</th>
                                        <th class="sortable">Progress</th>
                                        <th class="sortable">Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentProjects)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                                <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                                <div>No projects found</div>
                                                <a href="projects.php" class="btn btn-primary btn-sm" style="margin-top: 16px;">
                                                    Create Your First Project
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentProjects as $project): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($project['project_name']); ?></strong>
                                            </td>
                                            <td><?php echo e($project['client_name']); ?></td>
                                            <td><?php echo e($project['manager_name'] ?? 'Unassigned'); ?></td>
                                            <td>
                                                <?php
                                                $progress = $project['task_count'] > 0
                                                    ? round(($project['completed_tasks'] / $project['task_count']) * 100)
                                                    : 0;
                                                ?>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div style="flex: 1; height: 6px; background: var(--border-light); border-radius: 3px; overflow: hidden;">
                                                        <div style="width: <?php echo $progress; ?>%; height: 100%; background: linear-gradient(90deg, #17b06b 0%, #14d48f 100%); transition: width 0.3s;"></div>
                                                    </div>
                                                    <span style="font-size: 12px; font-weight: 600; color: var(--text-secondary);">
                                                        <?php echo $progress; ?>%
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'planning' => 'info',
                                                    'in_progress' => 'warning',
                                                    'review' => 'primary',
                                                    'completed' => 'success',
                                                    'on_hold' => 'secondary'
                                                ];
                                                $badgeClass = 'badge-' . ($statusColors[$project['status']] ?? 'secondary');
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-outline btn-sm btn-icon" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row" style="margin-top: 30px;">
                    <div class="col-lg-4">
                        <a href="projects.php" style="text-decoration: none;">
                            <div class="card hover-lift" style="cursor: pointer;">
                                <div class="card-body" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-folder-plus" style="font-size: 48px; color: #667eea; margin-bottom: 16px;"></i>
                                    <h4 style="margin-bottom: 8px;">Create Project</h4>
                                    <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                                        Start a new project and assign team members
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-4">
                        <a href="users.php?action=add" style="text-decoration: none;">
                            <div class="card hover-lift" style="cursor: pointer;">
                                <div class="card-body" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-user-plus" style="font-size: 48px; color: #17b06b; margin-bottom: 16px;"></i>
                                    <h4 style="margin-bottom: 8px;">Add Team Member</h4>
                                    <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                                        Invite new users to join your team
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-4">
                        <a href="analytics.php" style="text-decoration: none;">
                            <div class="card hover-lift" style="cursor: pointer;">
                                <div class="card-body" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-chart-line" style="font-size: 48px; color: #f8b739; margin-bottom: 16px;"></i>
                                    <h4 style="margin-bottom: 8px;">View Analytics</h4>
                                    <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                                        Analyze team performance and metrics
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Table sorting functionality
    document.querySelectorAll('.sortable').forEach(header => {
        header.addEventListener('click', function() {
            // Remove sorted classes from all headers
            document.querySelectorAll('.sortable').forEach(h => {
                h.classList.remove('sorted-asc', 'sorted-desc');
            });

            // Toggle sort direction
            if (this.dataset.sortDir === 'asc') {
                this.dataset.sortDir = 'desc';
                this.classList.add('sorted-desc');
            } else {
                this.dataset.sortDir = 'asc';
                this.classList.add('sorted-asc');
            }

            // Here you would implement actual sorting logic
            // For now, it's just visual feedback
        });
    });

    // Add animation on page load
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.dashboard-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100 * index);
        });
    });
    </script>
</body>
</html>
