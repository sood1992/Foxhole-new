<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/gamification-functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

try {
    $db = getDBConnection();
    $currentUser = getCurrentUser();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Get gamification data
$stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$userPoints = $stmt->fetch();

if (!$userPoints) {
    // Initialize points for new user
    $stmt = $db->prepare("
        INSERT INTO user_points (user_id, total_points, streak_days, last_activity_date)
        VALUES (?, 0, 0, CURDATE())
    ");
    $stmt->execute([$currentUser['id']]);

    $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $userPoints = $stmt->fetch();
}

// Get earned badges
$stmt = $db->prepare("
    SELECT b.*, ub.earned_at
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
");
$stmt->execute([$currentUser['id']]);
$earnedBadges = $stmt->fetchAll();

// Get leaderboard
$stmt = $db->prepare("
    SELECT
        u.id,
        u.full_name,
        up.total_points,
        up.streak_days,
        COUNT(DISTINCT ub.badge_id) as badge_count,
        ROW_NUMBER() OVER (ORDER BY up.total_points DESC) as rank
    FROM users u
    LEFT JOIN user_points up ON u.id = up.user_id
    LEFT JOIN user_badges ub ON u.id = ub.user_id
    WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
    GROUP BY u.id
    ORDER BY up.total_points DESC
    LIMIT 10
");
$stmt->execute();
$leaderboard = $stmt->fetchAll();

// Calculate productivity score
$productivityScore = calculateProductivityScore($db, $currentUser['id'], 'week');

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

    <!-- Synto Dashboard Template Design -->
    <link rel="stylesheet" href="../assets/css/synto-design.css">
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
                <!-- Gamification Stats -->
                <div class="stats-grid" id="achievements">
                    <div class="dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <div class="card-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas ri-star-line"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label" style="color: rgba(255,255,255,0.9);">Total Points</div>
                            <div class="card-value" style="color: white;"><?php echo number_format($userPoints['total_points']); ?></div>
                            <div class="card-change" style="color: rgba(255,255,255,0.8);">🏆 Keep earning!</div>
                        </div>
                    </div>

                    <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                        <div class="card-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label" style="color: rgba(255,255,255,0.9);">Current Streak</div>
                            <div class="card-value" style="color: white;"><?php echo $userPoints['streak_days']; ?> days</div>
                            <div class="card-change" style="color: rgba(255,255,255,0.8);">🔥 Keep it up!</div>
                        </div>
                    </div>

                    <div class="dashboard-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                        <div class="card-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas ri-trophy-line"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label" style="color: rgba(255,255,255,0.9);">Badges Earned</div>
                            <div class="card-value" style="color: white;"><?php echo count($earnedBadges); ?></div>
                            <div class="card-change" style="color: rgba(255,255,255,0.8);">🎯 Collect more!</div>
                        </div>
                    </div>

                    <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                        <div class="card-icon" style="background: rgba(255,255,255,0.2);">
                            <i class="fas ri-line-chart-line"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label" style="color: rgba(255,255,255,0.9);">Productivity Score</div>
                            <div class="card-value" style="color: white;"><?php echo round($productivityScore); ?>/100</div>
                            <div class="card-change" style="color: rgba(255,255,255,0.8);">📈 This week</div>
                        </div>
                    </div>
                </div>

                <!-- Time Period Stats -->
                <h3 style="margin: 32px 0 16px; color: var(--text-primary);"><i class="fas ri-time-line"></i> Time Tracking</h3>
                <div class="stats-grid">
                    <div class="dashboard-card">
                        <div class="card-icon gradient-blue">
                            <i class="fas ri-calendar-line-day"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">Today</div>
                            <div class="card-value"><?php echo formatHours($today_data['minutes'] ?? 0); ?></div>
                            <div class="card-change"><?php echo $today_data['tasks'] ?? 0; ?> tasks</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-green">
                            <i class="fas ri-calendar-line-week"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">This Week</div>
                            <div class="card-value"><?php echo formatHours($week_data['minutes'] ?? 0); ?></div>
                            <div class="card-change"><?php echo $week_data['projects'] ?? 0; ?> projects</div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-icon gradient-orange">
                            <i class="fas ri-calendar-line-alt"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-label">This Month</div>
                            <div class="card-value"><?php echo formatHours($month_data['minutes'] ?? 0); ?></div>
                            <div class="card-change"><?php echo $month_data['tasks'] ?? 0; ?> tasks worked</div>
                        </div>
                    </div>
                </div>

                <!-- Performance Stats -->
                <h3 style="margin: 32px 0 16px; color: var(--text-primary);"><i class="fas ri-line-chart-line"></i> Performance</h3>
                <div class="stats-grid">
                    <div class="dashboard-card">
                        <div class="card-icon gradient-purple">
                            <i class="fas ri-checkbox-circle-line"></i>
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
                            <h3><i class="fas ri-bar-chart-box-line"></i> 7-Day Productivity Trend</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Task Breakdown -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas ri-task-line"></i> Task Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="taskChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Achievements -->
                <div class="dashboard-card" style="margin-top: 32px;">
                    <div class="card-header">
                        <h3><i class="fas ri-trophy-line"></i> Recent Achievements</h3>
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

                <!-- Badges Section -->
                <div class="dashboard-card" style="margin-top: 32px;" id="badges">
                    <div class="card-header">
                        <h3><i class="fas ri-medal-line"></i> My Badges (<?php echo count($earnedBadges); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($earnedBadges)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                <div style="font-size: 48px; margin-bottom: 16px;">🏆</div>
                                <h3>No badges yet!</h3>
                                <p>Complete tasks to earn your first badge</p>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                                <?php foreach ($earnedBadges as $badge): ?>
                                <div style="background: var(--bg-secondary); border-radius: 12px; padding: 20px; text-align: center; border: 2px solid var(--border-color);">
                                    <div style="font-size: 48px; margin-bottom: 12px;"><?php echo $badge['icon']; ?></div>
                                    <h4 style="margin: 0 0 8px; color: var(--text-primary);"><?php echo e($badge['name']); ?></h4>
                                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0 0 8px;"><?php echo e($badge['description']); ?></p>
                                    <span class="badge" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                                        <?php echo ucfirst($badge['type']); ?>
                                    </span>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                        Earned <?php echo timeAgo($badge['earned_at']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Leaderboard Section -->
                <div class="dashboard-card" style="margin-top: 32px;" id="leaderboard">
                    <div class="card-header">
                        <h3><i class="fas ri-vip-crown-line"></i> Leaderboard - Top Performers</h3>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Rank</th>
                                    <th>Name</th>
                                    <th>Points</th>
                                    <th>Streak</th>
                                    <th>Badges</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rank = 1;
                                foreach ($leaderboard as $user):
                                    $isCurrentUser = ($user['id'] == $currentUser['id']);
                                    $rowStyle = $isCurrentUser ? 'background: var(--primary-light); font-weight: 600;' : '';
                                    $medalEmoji = '';
                                    if ($rank === 1) $medalEmoji = '🥇';
                                    elseif ($rank === 2) $medalEmoji = '🥈';
                                    elseif ($rank === 3) $medalEmoji = '🥉';
                                ?>
                                <tr style="<?php echo $rowStyle; ?>">
                                    <td style="text-align: center;">
                                        <span style="font-size: 20px;"><?php echo $medalEmoji; ?></span>
                                        <?php if (!$medalEmoji): ?>#<?php echo $rank; ?><?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo e($user['full_name']); ?></strong>
                                        <?php if ($isCurrentUser): ?><span style="color: var(--primary); margin-left: 8px;">(You)</span><?php endif; ?>
                                    </td>
                                    <td><strong><?php echo number_format($user['total_points'] ?? 0); ?></strong> pts</td>
                                    <td>🔥 <?php echo $user['streak_days'] ?? 0; ?> days</td>
                                    <td>🏆 <?php echo $user['badge_count'] ?? 0; ?> badges</td>
                                </tr>
                                <?php
                                $rank++;
                                endforeach;
                                ?>
                            </tbody>
                        </table>
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

    <!-- Synto Dashboard Interactions -->
    <script src="../assets/js/synto-interactions.js"></script>
</body>
</html>
