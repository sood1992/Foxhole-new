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
    <title>My Statistics V3 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                <!-- Time Period Stats -->
                <h3 style="margin-bottom: 16px; color: var(--text-primary);"><i class="fas fa-clock"></i> Time Tracking</h3>
                <div class="stats-grid">
                    <div class="dashboard-card">
                        <div class="card-icon gradient-blue">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Today</div>
                            <div class="card-value"><?php echo formatHours($today_data['minutes'] ?? 0); ?></div>
                            <div class="card-change"><?php echo $today_data['tasks'] ?? 0; ?> tasks</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-green">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">This Week</div>
                            <div class="card-value"><?php echo formatHours($week_data['minutes'] ?? 0); ?></div>
                            <div class="card-change"><?php echo $week_data['projects'] ?? 0; ?> projects</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-orange">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">This Month</div>
                            <div class="card-value"><?php echo formatHours($month_data['minutes'] ?? 0); ?></div>
                            <div class="card-change"><?php echo $month_data['tasks'] ?? 0; ?> tasks worked</div>
                        </div>
                    </div>
                </div>

                <!-- Performance Stats -->
                <h3 style="margin: 32px 0 16px; color: var(--text-primary);"><i class="fas fa-chart-line"></i> Performance</h3>
                <div class="stats-grid">
                    <div class="dashboard-card">
                        <div class="card-icon gradient-purple">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Completed This Month</div>
                            <div class="card-value"><?php echo $completed_count; ?></div>
                            <div class="card-change">Tasks finished</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-blue">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Avg Completion Time</div>
                            <div class="card-value"><?php echo round($avg_time); ?></div>
                            <div class="card-change">Hours per task</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-green">
                            <i class="fas fa-sync"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Active Tasks</div>
                            <div class="card-value"><?php echo $task_breakdown['in_progress'] ?? 0; ?></div>
                            <div class="card-change">Currently working on</div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-top: 32px;">
                    <!-- Productivity Trend -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> 7-Day Productivity Trend</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Task Breakdown -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tasks"></i> Task Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="taskChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Achievements -->
                <div class="dashboard-card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Recent Achievements</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($achievements)): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                No completed tasks yet. Keep going!
                            </p>
                        <?php else: ?>
                            <table class="data-table">
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
        </div>
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
