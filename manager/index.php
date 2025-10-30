<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../login.php');
    exit;
}

// Check if user has manager role - if not, redirect to their correct dashboard
if (!hasRole('manager')) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('../admin/index.php');
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

// Get statistics for manager
$stats = [];

// Projects assigned to manager
$stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE assigned_manager = ? AND status IN ('planning', 'in_progress', 'review')");
$stmt->execute([$currentUser['id']]);
$stats['my_projects'] = $stmt->fetch()['count'];

// Tasks assigned by manager
$stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status != 'completed'");
$stmt->execute([$currentUser['id']]);
$stats['active_tasks'] = $stmt->fetch()['count'];

// Team members working on manager's projects
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT t.assigned_to) as count
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE p.assigned_manager = ?
");
$stmt->execute([$currentUser['id']]);
$stats['team_members'] = $stmt->fetch()['count'];

// Completed this week
$stmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE p.assigned_manager = ?
        AND t.status = 'completed'
        AND WEEK(t.completed_date) = WEEK(CURRENT_DATE())
");
$stmt->execute([$currentUser['id']]);
$stats['completed_week'] = $stmt->fetch()['count'];

// Get manager's projects
$myProjects = $db->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks,
           (SELECT SUM(duration_minutes) FROM time_logs WHERE project_id = p.id) as total_minutes
    FROM projects p
    WHERE p.assigned_manager = ?
    ORDER BY
        FIELD(p.status, 'in_progress', 'review', 'planning', 'on_hold', 'completed'),
        p.priority DESC,
        p.due_date ASC
    LIMIT 5
");
$myProjects->execute([$currentUser['id']]);
$projectsData = $myProjects->fetchAll();

// Get team activity
$teamActivity = $db->prepare("
    SELECT
        u.id, u.full_name, u.job_title,
        COUNT(DISTINCT t.id) as active_tasks,
        COUNT(DISTINCT CASE WHEN t.status = 'completed' AND DATE(t.completed_date) = CURDATE() THEN t.id END) as completed_today,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id AND DATE(start_time) = CURDATE()) as today_minutes
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE p.assigned_manager = ?
        AND u.role IN ('employee', 'manager')
        AND u.is_active = 1
    GROUP BY u.id
    ORDER BY u.full_name
");
$teamActivity->execute([$currentUser['id']]);
$teamData = $teamActivity->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-manager-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;">Project Manager Dashboard</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Overview of your projects and team activity
                    </p>
                </div>

                <!-- Stats Grid -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['my_projects']; ?></div>
                            <div class="card-label">My Projects</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['active_tasks']; ?></div>
                            <div class="card-label">Active Tasks</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['completed_week']; ?></div>
                            <div class="card-label">Completed This Week</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-value"><?php echo $stats['team_members']; ?></div>
                            <div class="card-label">Team Members</div>
                        </div>
                    </div>
                </div>

                <!-- Team Activity Today -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">Team Activity Today</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Real-time overview of your team's productivity
                            </p>
                        </div>
                        <a href="team.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container" style="border: none; box-shadow: none;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Team Member</th>
                                        <th>Role</th>
                                        <th>Active Tasks</th>
                                        <th>Completed Today</th>
                                        <th>Hours Today</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamData as $member): ?>
                                    <tr>
                                        <td><strong style="color: var(--heading-color);"><?php echo e($member['full_name']); ?></strong></td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo $member['active_tasks']; ?></strong></td>
                                        <td>
                                            <?php if ($member['completed_today'] > 0): ?>
                                                <span class="badge badge-success"><?php echo $member['completed_today']; ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong style="color: var(--primary);"><?php echo formatHours($member['today_minutes'] ?? 0); ?>h</strong></td>
                                        <td>
                                            <?php
                                            if (($member['today_minutes'] ?? 0) > 240) {
                                                echo '<span class="badge badge-success">Very Active</span>';
                                            } elseif (($member['today_minutes'] ?? 0) > 0) {
                                                echo '<span class="badge badge-primary">Active</span>';
                                            } else {
                                                echo '<span class="badge badge-info">No Activity</span>';
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

                <!-- My Projects -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">My Projects</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                Projects you're currently managing
                            </p>
                        </div>
                        <a href="projects.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Project
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projectsData)): ?>
                            <div style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-folder-open" style="font-size: 48px; color: var(--text-tertiary); margin-bottom: 16px;"></i>
                                <h3 style="color: var(--heading-color); margin-bottom: 8px;">No Projects Assigned</h3>
                                <p style="color: var(--text-secondary); margin: 0;">Contact admin to get projects assigned to you.</p>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; gap: 20px;">
                                <?php foreach ($projectsData as $project): ?>
                                <?php
                                    $completion = $project['task_count'] > 0 ? round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                                    $isOverdue = isOverdue($project['due_date'], $project['status']);
                                ?>
                                <div style="padding: 20px; border: 2px solid var(--border-light); border-radius: var(--radius-md); transition: border-color 200ms ease;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                                        <div>
                                            <h4 style="margin: 0 0 8px 0; color: var(--heading-color);"><?php echo e($project['project_name']); ?></h4>
                                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <span style="color: var(--text-secondary); font-size: 13px;">
                                                    <i class="fas fa-building"></i> <?php echo e($project['client_name'] ?? 'Internal'); ?>
                                                </span>
                                                <?php
                                                $statusClass = 'badge-info';
                                                if ($project['status'] === 'completed') $statusClass = 'badge-success';
                                                elseif ($project['status'] === 'in_progress') $statusClass = 'badge-primary';
                                                elseif ($project['status'] === 'on_hold') $statusClass = 'badge-warning';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                </span>
                                                <?php
                                                $priorityClass = 'badge-info';
                                                if ($project['priority'] === 'urgent') $priorityClass = 'badge-danger';
                                                elseif ($project['priority'] === 'high') $priorityClass = 'badge-warning';
                                                ?>
                                                <span class="badge <?php echo $priorityClass; ?>">
                                                    <?php echo ucfirst($project['priority']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <a href="projects.php?id=<?php echo $project['id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Progress</div>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; height: 6px; background: var(--border-light); border-radius: 3px; overflow: hidden;">
                                                    <div style="width: <?php echo $completion; ?>%; height: 100%;
                                                                background: linear-gradient(90deg, #17b06b 0%, #14d48f 100%); transition: width 300ms ease;"></div>
                                                </div>
                                                <span style="font-size: 13px; font-weight: 600;"><?php echo $completion; ?>%</span>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 4px;">
                                                <?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?> tasks
                                            </div>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">Total Hours</div>
                                            <div style="font-size: 20px; font-weight: 600; color: var(--primary);">
                                                <?php echo formatHours($project['total_minutes'] ?? 0); ?>h
                                            </div>
                                        </div>
                                        <div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">Due Date</div>
                                            <div style="font-size: 14px; font-weight: 500; <?php echo $isOverdue ? 'color: var(--danger);' : ''; ?>">
                                                <?php
                                                if ($project['due_date']) {
                                                    echo date('M d, Y', strtotime($project['due_date']));
                                                    if ($isOverdue) {
                                                        echo ' <i class="fas fa-exclamation-triangle"></i>';
                                                    }
                                                } else {
                                                    echo '<span style="color: var(--text-tertiary);">No deadline</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top: 20px; text-align: center;">
                                <a href="projects.php" class="btn btn-outline">
                                    View All Projects
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
