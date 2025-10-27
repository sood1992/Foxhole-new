<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../login.php');
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
");
$myProjects->execute([$currentUser['id']]);
$projectsData = $myProjects->fetchAll();

// Get team activity
$teamActivity = $db->prepare("
    SELECT
        u.id, u.full_name, u.job_title,
        COUNT(DISTINCT t.id) as active_tasks,
        COUNT(DISTINCT CASE WHEN t.status = 'completed' AND DATE(t.completed_date) = CURDATE() THEN t.id END) as completed_today,
        (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id AND DATE(start_time) = CURDATE()) as today_minutes,
        (SELECT task_name FROM tasks WHERE id = (SELECT task_id FROM time_logs WHERE user_id = u.id AND is_active = 1 LIMIT 1)) as current_task
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

// Get recent updates/comments
$recentUpdates = $db->prepare("
    SELECT
        'project' as type,
        pc.id,
        pc.comment,
        pc.created_at,
        u.full_name,
        p.project_name as reference
    FROM project_comments pc
    JOIN users u ON pc.user_id = u.id
    JOIN projects p ON pc.project_id = p.id
    WHERE p.assigned_manager = ?
    UNION ALL
    SELECT
        'task' as type,
        tc.id,
        tc.comment,
        tc.created_at,
        u.full_name,
        t.task_name as reference
    FROM task_comments tc
    JOIN users u ON tc.user_id = u.id
    JOIN tasks t ON tc.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    WHERE p.assigned_manager = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$recentUpdates->execute([$currentUser['id'], $currentUser['id']]);
$updatesData = $recentUpdates->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Foxhole</h2>
                <div class="user-role">Project Manager</div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="active">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
                <a href="projects.php">
                    <span class="icon">📁</span>
                    My Projects
                </a>
                <a href="tasks.php">
                    <span class="icon">✓</span>
                    Task Management
                </a>
                <a href="team.php">
                    <span class="icon">👥</span>
                    Team Activity
                </a>
                <a href="reports.php">
                    <span class="icon">📈</span>
                    Reports
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo e($currentUser['full_name']); ?></h4>
                        <p><?php echo e($currentUser['job_title'] ?? 'Project Manager'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-block btn-sm">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>Project Manager Dashboard</h1>
                <div class="topbar-actions">
                    <span style="color: var(--text-secondary); font-size: 14px;">
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
                                <div class="stat-label">My Projects</div>
                                <div class="stat-value"><?php echo $stats['my_projects']; ?></div>
                                <div class="stat-change">Active</div>
                            </div>
                            <div class="stat-icon">📁</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Active Tasks</div>
                                <div class="stat-value"><?php echo $stats['active_tasks']; ?></div>
                                <div class="stat-change">In Progress</div>
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

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Team Members</div>
                                <div class="stat-value"><?php echo $stats['team_members']; ?></div>
                                <div class="stat-change">Working</div>
                            </div>
                            <div class="stat-icon">👥</div>
                        </div>
                    </div>
                </div>

                <!-- Team Activity Today -->
                <div class="card">
                    <div class="card-header">
                        <h3>Team Activity Today</h3>
                        <a href="team.php" class="btn btn-secondary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Team Member</th>
                                        <th>Role</th>
                                        <th>Active Tasks</th>
                                        <th>Completed Today</th>
                                        <th>Hours Today</th>
                                        <th>Current Activity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamData as $member): ?>
                                    <tr>
                                        <td><strong><?php echo e($member['full_name']); ?></strong></td>
                                        <td><?php echo e($member['job_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo $member['active_tasks']; ?></td>
                                        <td>
                                            <?php if ($member['completed_today'] > 0): ?>
                                                <span class="badge status-completed"><?php echo $member['completed_today']; ?></span>
                                            <?php else: ?>
                                                0
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatHours($member['today_minutes'] ?? 0); ?>h</td>
                                        <td>
                                            <?php if ($member['current_task']): ?>
                                                <span class="badge status-progress">⏱️ <?php echo e($member['current_task']); ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">Idle</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($member['current_task']) {
                                                echo '<span class="badge status-progress">Working</span>';
                                            } elseif (($member['today_minutes'] ?? 0) > 0) {
                                                echo '<span class="badge status-completed">Active</span>';
                                            } else {
                                                echo '<span class="badge status-todo">No Activity</span>';
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
                        <h3>My Projects</h3>
                        <a href="projects.php?action=new" class="btn btn-primary btn-sm">+ New Project</a>
                    </div>
                    <div class="card-body">
                        <div class="task-list">
                            <?php foreach ($projectsData as $project): ?>
                            <?php
                                $completion = getProjectCompletionRate($project['id']);
                                $totalHours = formatHours($project['total_minutes'] ?? 0);
                            ?>
                            <div class="task-item <?php echo isOverdue($project['due_date'], $project['status']) ? 'overdue' : ''; ?>">
                                <div class="task-item-header">
                                    <div>
                                        <div class="task-item-title"><?php echo e($project['project_name']); ?></div>
                                        <div class="task-item-meta">
                                            <span>📁 <?php echo e($project['client_name'] ?? 'Internal'); ?></span>
                                            <span class="badge <?php echo getStatusClass($project['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                            <span class="badge <?php echo getPriorityClass($project['priority']); ?>">
                                                <?php echo ucfirst($project['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="task-item-actions">
                                        <a href="projects.php?id=<?php echo $project['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 16px;">
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Progress</div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar <?php echo $completion == 100 ? 'complete' : ($completion > 50 ? 'high' : 'medium'); ?>"
                                                 style="width: <?php echo $completion; ?>%"></div>
                                        </div>
                                        <div style="font-size: 13px; margin-top: 4px;"><?php echo $completion; ?>% (<?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?>)</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Total Hours</div>
                                        <div style="font-size: 20px; font-weight: 600;"><?php echo $totalHours; ?>h</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Due Date</div>
                                        <div style="font-size: 14px; font-weight: 500;">
                                            <?php
                                            if ($project['due_date']) {
                                                echo date('M d, Y', strtotime($project['due_date']));
                                                if (isOverdue($project['due_date'], $project['status'])) {
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

                            <?php if (empty($projectsData)): ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    <div style="font-size: 48px; margin-bottom: 16px;">📁</div>
                                    <h3>No Projects Assigned</h3>
                                    <p>Contact admin to get projects assigned to you.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Updates -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Updates & Comments</h3>
                    </div>
                    <div class="card-body">
                        <div class="comments-section">
                            <?php foreach ($updatesData as $update): ?>
                            <div class="comment">
                                <div class="comment-header">
                                    <div>
                                        <span class="comment-author"><?php echo e($update['full_name']); ?></span>
                                        <span style="color: var(--text-secondary); font-size: 13px; margin-left: 8px;">
                                            on <?php echo e($update['reference']); ?>
                                        </span>
                                    </div>
                                    <span class="comment-time"><?php echo timeAgo($update['created_at']); ?></span>
                                </div>
                                <div class="comment-body">
                                    <?php echo nl2br(e($update['comment'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (empty($updatesData)): ?>
                                <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                    No recent updates
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
