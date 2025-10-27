<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    redirect('team.php');
}

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('team.php');
}

// Get user points and gamification stats
$stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
$stmt->execute([$userId]);
$points = $stmt->fetch();

if (!$points) {
    // Initialize gamification data if not exists
    $stmt = $db->prepare("INSERT INTO user_points (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    $points = [
        'total_points' => 0,
        'streak_days' => 0,
        'tasks_completed_early' => 0,
        'tasks_completed_on_time' => 0,
        'tasks_completed_late' => 0
    ];
}

// Get earned badges
$stmt = $db->prepare("
    SELECT b.*, ub.earned_at
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
");
$stmt->execute([$userId]);
$badges = $stmt->fetchAll();

// Get task statistics
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
        SUM(CASE WHEN status != 'completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM tasks
    WHERE assigned_to = ?
");
$stmt->execute([$userId]);
$taskStats = $stmt->fetch();

// Get time tracking stats
$stmt = $db->prepare("
    SELECT
        SUM(duration_minutes) as total_minutes,
        COUNT(*) as sessions,
        AVG(duration_minutes) as avg_session
    FROM time_logs
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$timeStats = $stmt->fetch();

// Get recent activity
$stmt = $db->prepare("
    SELECT
        t.task_name,
        t.status,
        t.completed_date,
        p.project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
        AND t.status = 'completed'
    ORDER BY t.completed_date DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$recentTasks = $stmt->fetchAll();

// Get projects worked on
$stmt = $db->prepare("
    SELECT DISTINCT
        p.id,
        p.project_name,
        p.client_name,
        COUNT(DISTINCT t.id) as task_count
    FROM projects p
    JOIN tasks t ON p.id = t.project_id
    WHERE t.assigned_to = ?
    GROUP BY p.id
    ORDER BY task_count DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$projects = $stmt->fetchAll();

// Calculate completion rate
$completionRate = $taskStats['total_tasks'] > 0 ?
    round(($taskStats['completed'] / $taskStats['total_tasks']) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($user['full_name']); ?> - Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>👤 User Profile</h1>
                <div class="topbar-actions">
                    <?php include '../includes/global-search-assets.php'; ?>
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <!-- User Header Card -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-body">
                        <div style="display: flex; gap: var(--space-6); align-items: start;">
                            <!-- Avatar -->
                            <div style="width: 120px; height: 120px; border-radius: var(--radius-xl); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 900; color: white; flex-shrink: 0;">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>

                            <!-- User Info -->
                            <div style="flex: 1;">
                                <h2 style="font-size: var(--font-3xl); font-weight: 900; margin-bottom: var(--space-2); color: var(--text-primary);">
                                    <?php echo e($user['full_name']); ?>
                                </h2>
                                <p style="font-size: var(--font-lg); color: var(--text-secondary); margin-bottom: var(--space-4);">
                                    <?php echo e($user['job_title'] ?? 'Team Member'); ?>
                                </p>

                                <div style="display: flex; gap: var(--space-6); flex-wrap: wrap; margin-bottom: var(--space-4);">
                                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                                        <span style="font-size: 18px;">📧</span>
                                        <span style="color: var(--text-secondary);"><?php echo e($user['email']); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                                        <span style="font-size: 18px;">🎯</span>
                                        <span class="badge" style="background: var(--primary-gradient); color: white; font-weight: 700;">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                    <?php if ($user['hourly_rate']): ?>
                                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                                        <span style="font-size: 18px;">💰</span>
                                        <span style="color: var(--text-secondary);">$<?php echo number_format($user['hourly_rate']); ?>/hr</span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Gamification Quick Stats -->
                                <div style="display: flex; gap: var(--space-4); padding: var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-lg);">
                                    <div style="text-align: center;">
                                        <div style="font-size: var(--font-2xl); font-weight: 900; color: var(--primary);">
                                            <?php echo number_format($points['total_points']); ?>
                                        </div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); text-transform: uppercase;">
                                            Points
                                        </div>
                                    </div>
                                    <div style="width: 1px; background: var(--border);"></div>
                                    <div style="text-align: center;">
                                        <div style="font-size: var(--font-2xl); font-weight: 900; color: #f59e0b;">
                                            <?php echo $points['streak_days']; ?>
                                        </div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); text-transform: uppercase;">
                                            Day Streak
                                        </div>
                                    </div>
                                    <div style="width: 1px; background: var(--border);"></div>
                                    <div style="text-align: center;">
                                        <div style="font-size: var(--font-2xl); font-weight: 900; color: #10b981;">
                                            <?php echo count($badges); ?>
                                        </div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); text-transform: uppercase;">
                                            Badges
                                        </div>
                                    </div>
                                    <div style="width: 1px; background: var(--border);"></div>
                                    <div style="text-align: center;">
                                        <div style="font-size: var(--font-2xl); font-weight: 900; color: #8b5cf6;">
                                            <?php echo $completionRate; ?>%
                                        </div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary); text-transform: uppercase;">
                                            Completion
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Total Tasks</div>
                                <div class="stat-value"><?php echo $taskStats['total_tasks']; ?></div>
                                <div class="stat-change"><?php echo $taskStats['completed']; ?> completed</div>
                            </div>
                            <div class="stat-icon">✓</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Hours Tracked</div>
                                <div class="stat-value"><?php echo formatHours($timeStats['total_minutes'] ?? 0); ?></div>
                                <div class="stat-change"><?php echo $timeStats['sessions'] ?? 0; ?> sessions</div>
                            </div>
                            <div class="stat-icon">⏱️</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Early Completions</div>
                                <div class="stat-value"><?php echo $points['tasks_completed_early']; ?></div>
                                <div class="stat-change">Before deadline</div>
                            </div>
                            <div class="stat-icon">🎁</div>
                        </div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Active Projects</div>
                                <div class="stat-value"><?php echo count($projects); ?></div>
                                <div class="stat-change">Currently working</div>
                            </div>
                            <div class="stat-icon">📁</div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--space-6); margin-top: var(--space-6);">
                    <!-- Badges -->
                    <div class="card">
                        <div class="card-header">
                            <h3>🏆 Earned Badges</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($badges)): ?>
                                <p style="text-align: center; color: var(--text-secondary); padding: var(--space-6);">
                                    No badges earned yet
                                </p>
                            <?php else: ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: var(--space-4);">
                                    <?php foreach ($badges as $badge): ?>
                                    <div style="text-align: center; padding: var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-lg); border: 2px solid var(--border);">
                                        <div style="font-size: 40px; margin-bottom: var(--space-2);">
                                            <?php echo $badge['icon']; ?>
                                        </div>
                                        <div style="font-size: var(--font-sm); font-weight: 700; color: var(--text-primary); margin-bottom: var(--space-1);">
                                            <?php echo e($badge['name']); ?>
                                        </div>
                                        <div style="font-size: var(--font-xs); color: var(--text-tertiary);">
                                            <?php echo e($badge['description']); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Projects -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📁 Active Projects</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projects)): ?>
                                <p style="text-align: center; color: var(--text-secondary); padding: var(--space-6);">
                                    No projects assigned
                                </p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                    <?php foreach ($projects as $project): ?>
                                    <div style="padding: var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-md); border-left: 3px solid var(--primary);">
                                        <div style="font-weight: 700; color: var(--text-primary); margin-bottom: var(--space-1);">
                                            <?php echo e($project['project_name']); ?>
                                        </div>
                                        <div style="font-size: var(--font-xs); color: var(--text-secondary);">
                                            <?php echo e($project['client_name']); ?> • <?php echo $project['task_count']; ?> tasks
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card" style="margin-top: var(--space-6);">
                    <div class="card-header">
                        <h3>📋 Recent Completions</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentTasks)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: var(--space-6);">
                                No completed tasks yet
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTasks as $task): ?>
                                    <tr>
                                        <td><strong><?php echo e($task['task_name']); ?></strong></td>
                                        <td><?php echo e($task['project_name']); ?></td>
                                        <td><?php echo timeAgo($task['completed_date']); ?></td>
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
