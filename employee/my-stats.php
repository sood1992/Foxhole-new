<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get stats for different time periods
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');

// Today's stats
$todayStats = $db->prepare("
    SELECT
        COUNT(DISTINCT task_id) as tasks,
        SUM(duration_minutes) as minutes
    FROM time_logs
    WHERE user_id = ? AND DATE(start_time) = ?
");
$todayStats->execute([$currentUser['id'], $today]);
$today_data = $todayStats->fetch();

// This week's stats
$weekStats = $db->prepare("
    SELECT
        COUNT(DISTINCT task_id) as tasks,
        COUNT(DISTINCT project_id) as projects,
        SUM(duration_minutes) as minutes
    FROM time_logs
    WHERE user_id = ? AND DATE(start_time) >= ?
");
$weekStats->execute([$currentUser['id'], $weekStart]);
$week_data = $weekStats->fetch();

// This month's stats
$monthStats = $db->prepare("
    SELECT
        COUNT(DISTINCT task_id) as tasks,
        COUNT(DISTINCT project_id) as projects,
        SUM(duration_minutes) as minutes
    FROM time_logs
    WHERE user_id = ? AND DATE(start_time) >= ?
");
$monthStats->execute([$currentUser['id'], $monthStart]);
$month_data = $monthStats->fetch();

// Task completion stats
$taskStats = $db->prepare("
    SELECT
        status,
        COUNT(*) as count
    FROM tasks
    WHERE assigned_to = ?
    GROUP BY status
");
$taskStats->execute([$currentUser['id']]);
$task_breakdown = $taskStats->fetchAll(PDO::FETCH_KEY_PAIR);

// Get completed tasks this month
$completedThisMonth = $db->prepare("
    SELECT COUNT(*) as count
    FROM tasks
    WHERE assigned_to = ?
        AND status = 'completed'
        AND DATE(completed_date) >= ?
");
$completedThisMonth->execute([$currentUser['id'], $monthStart]);
$completed_count = $completedThisMonth->fetch()['count'];

// Get average task completion time
$avgCompletionTime = $db->prepare("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_date)) as avg_hours
    FROM tasks
    WHERE assigned_to = ?
        AND status = 'completed'
        AND completed_date IS NOT NULL
");
$avgCompletionTime->execute([$currentUser['id']]);
$avg_time = $avgCompletionTime->fetch()['avg_hours'] ?? 0;

// Recent achievements
$recentAchievements = $db->prepare("
    SELECT
        t.task_name,
        t.completed_date,
        t.priority,
        p.project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
        AND t.status = 'completed'
    ORDER BY t.completed_date DESC
    LIMIT 10
");
$recentAchievements->execute([$currentUser['id']]);
$achievements = $recentAchievements->fetchAll();

// Get productivity trend (last 7 days)
$productivityTrend = $db->prepare("
    SELECT
        DATE(start_time) as date,
        SUM(duration_minutes)/60 as hours
    FROM time_logs
    WHERE user_id = ?
        AND DATE(start_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(start_time)
    ORDER BY date ASC
");
$productivityTrend->execute([$currentUser['id']]);
$trend_data = $productivityTrend->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Statistics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/premium-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                <a href="time-logs.php">
                    <span class="icon">⏱️</span>
                    Time Logs
                </a>
                <a href="my-stats.php" class="active">
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
                <h1>📈 My Statistics</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <!-- Time Period Stats -->
                <h3 style="margin-bottom: 16px; color: var(--text-primary);">⏰ Time Tracking</h3>
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Today</div>
                                <div class="stat-value"><?php echo formatHours($today_data['minutes'] ?? 0); ?></div>
                                <div class="stat-change"><?php echo $today_data['tasks'] ?? 0; ?> tasks</div>
                            </div>
                            <div class="stat-icon">📅</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">This Week</div>
                                <div class="stat-value"><?php echo formatHours($week_data['minutes'] ?? 0); ?></div>
                                <div class="stat-change"><?php echo $week_data['projects'] ?? 0; ?> projects</div>
                            </div>
                            <div class="stat-icon">📊</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">This Month</div>
                                <div class="stat-value"><?php echo formatHours($month_data['minutes'] ?? 0); ?></div>
                                <div class="stat-change"><?php echo $month_data['tasks'] ?? 0; ?> tasks worked</div>
                            </div>
                            <div class="stat-icon">📈</div>
                        </div>
                    </div>
                </div>

                <!-- Performance Stats -->
                <h3 style="margin: 32px 0 16px; color: var(--text-primary);">🎯 Performance</h3>
                <div class="stats-grid">
                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Completed This Month</div>
                                <div class="stat-value"><?php echo $completed_count; ?></div>
                                <div class="stat-change">Tasks finished</div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Avg Completion Time</div>
                                <div class="stat-value"><?php echo round($avg_time); ?></div>
                                <div class="stat-change">Hours per task</div>
                            </div>
                            <div class="stat-icon">⚡</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Active Tasks</div>
                                <div class="stat-value"><?php echo $task_breakdown['in_progress'] ?? 0; ?></div>
                                <div class="stat-change">Currently working on</div>
                            </div>
                            <div class="stat-icon">🔄</div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-top: 32px;">
                    <!-- Productivity Trend -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📊 7-Day Productivity Trend</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Task Breakdown -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📋 Task Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="taskChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Achievements -->
                <div class="card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3>🏆 Recent Achievements</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($achievements)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No completed tasks yet. Keep going!
                            </p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Priority</th>
                                        <th>Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($achievements as $task): ?>
                                    <tr>
                                        <td><strong><?php echo e($task['task_name']); ?></strong></td>
                                        <td><?php echo e($task['project_name']); ?></td>
                                        <td><span class="badge priority-<?php echo $task['priority']; ?>"><?php echo $task['priority']; ?></span></td>
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
    <script>
    // Productivity Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($trend_data, 'date')); ?>,
            datasets: [{
                label: 'Hours',
                data: <?php echo json_encode(array_column($trend_data, 'hours')); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Task Breakdown Chart
    const taskCtx = document.getElementById('taskChart').getContext('2d');
    new Chart(taskCtx, {
        type: 'doughnut',
        data: {
            labels: ['Todo', 'In Progress', 'Review', 'Completed', 'Blocked'],
            datasets: [{
                data: [
                    <?php echo $task_breakdown['todo'] ?? 0; ?>,
                    <?php echo $task_breakdown['in_progress'] ?? 0; ?>,
                    <?php echo $task_breakdown['review'] ?? 0; ?>,
                    <?php echo $task_breakdown['completed'] ?? 0; ?>,
                    <?php echo $task_breakdown['blocked'] ?? 0; ?>
                ],
                backgroundColor: ['#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#ef4444']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    </script>
</body>
</html>
