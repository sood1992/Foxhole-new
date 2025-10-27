<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Date range
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// === EMPLOYEE PERFORMANCE ANALYSIS ===

try {
// Get all employees with detailed metrics
$employeeMetrics = $db->prepare("
    SELECT
        u.id,
        u.full_name,
        u.job_title,

        -- Task metrics
        COUNT(DISTINCT t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks,
        SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END) as blocked_tasks,

        -- Time metrics
        SUM(tl.duration_minutes) / 60 as total_hours,
        AVG(tl.duration_minutes) / 60 as avg_session_hours,

        -- Completion speed
        AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.completed_date)) as avg_completion_hours,
        AVG(CASE
            WHEN t.due_date IS NOT NULL AND t.completed_date IS NOT NULL
            THEN DATEDIFF(t.due_date, t.completed_date)
        END) as avg_days_before_deadline,

        -- Early completion bonus
        SUM(CASE
            WHEN t.status = 'completed' AND t.completed_date < t.due_date
            THEN 1 ELSE 0
        END) as early_completions,

        -- Late completions (bottleneck indicator)
        SUM(CASE
            WHEN t.status = 'completed' AND t.completed_date > t.due_date
            THEN 1 ELSE 0
        END) as late_completions,

        -- Overdue tasks (major bottleneck)
        SUM(CASE
            WHEN t.status != 'completed' AND t.due_date < CURDATE()
            THEN 1 ELSE 0
        END) as overdue_tasks,

        -- Quality indicators
        COUNT(DISTINCT p.id) as projects_count,
        AVG(CASE WHEN t.priority = 'urgent' THEN 4 WHEN t.priority = 'high' THEN 3 WHEN t.priority = 'medium' THEN 2 ELSE 1 END) as avg_priority_handled

    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
        AND DATE(t.created_at) BETWEEN ? AND ?
    LEFT JOIN time_logs tl ON u.id = tl.user_id
        AND tl.end_time IS NOT NULL
        AND DATE(tl.start_time) BETWEEN ? AND ?
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE u.role IN ('employee', 'manager') AND u.is_active = 1
    GROUP BY u.id
    HAVING total_tasks > 0
    ORDER BY completed_tasks DESC
");
$employeeMetrics->execute([$startDate, $endDate, $startDate, $endDate]);
$employees = $employeeMetrics->fetchAll();
} catch (PDOException $e) {
    die("Database Error in employee metrics: " . $e->getMessage());
}

// Calculate scores and rankings
foreach ($employees as &$emp) {
    // Completion rate
    $emp['completion_rate'] = $emp['total_tasks'] > 0 ?
        round(($emp['completed_tasks'] / $emp['total_tasks']) * 100, 1) : 0;

    // Speed score (faster is better)
    $emp['speed_score'] = $emp['avg_completion_hours'] > 0 ?
        round(max(0, 100 - ($emp['avg_completion_hours'] / 24) * 10), 1) : 0;

    // Quality score (early completion, no late/overdue)
    $totalCompleted = $emp['completed_tasks'] ?: 1;
    $qualityPenalty = ($emp['late_completions'] * 10) + ($emp['overdue_tasks'] * 20);
    $qualityBonus = ($emp['early_completions'] * 5);
    $emp['quality_score'] = round(max(0, min(100, 100 + $qualityBonus - $qualityPenalty)), 1);

    // Overall productivity score
    $emp['productivity_score'] = round(
        ($emp['completion_rate'] * 0.4) +
        ($emp['speed_score'] * 0.3) +
        ($emp['quality_score'] * 0.3)
    , 1);

    // Bottleneck indicator
    $emp['is_bottleneck'] = (
        $emp['overdue_tasks'] > 3 ||
        $emp['blocked_tasks'] > 2 ||
        $emp['completion_rate'] < 50 ||
        ($emp['avg_days_before_deadline'] ?? 0) < -2
    );

    // Performance category
    if ($emp['productivity_score'] >= 90) {
        $emp['category'] = 'Excellent';
        $emp['category_color'] = 'green';
    } elseif ($emp['productivity_score'] >= 75) {
        $emp['category'] = 'Good';
        $emp['category_color'] = 'blue';
    } elseif ($emp['productivity_score'] >= 60) {
        $emp['category'] = 'Average';
        $emp['category_color'] = 'orange';
    } else {
        $emp['category'] = 'Needs Improvement';
        $emp['category_color'] = 'red';
    }
}

// Sort by productivity score
usort($employees, function($a, $b) {
    return $b['productivity_score'] <=> $a['productivity_score'];
});

// === BOTTLENECK ANALYSIS ===
$bottlenecks = array_filter($employees, function($emp) {
    return $emp['is_bottleneck'];
});

// === PROJECT BOTTLENECKS ===
try {
$projectBottlenecks = $db->prepare("
    SELECT
        p.id,
        p.project_name,
        p.client_name,
        p.status,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END) as blocked_tasks,
        SUM(CASE WHEN t.status != 'completed' AND t.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks,
        AVG(TIMESTAMPDIFF(DAY, t.start_date, COALESCE(t.completed_date, CURDATE()))) as avg_days_to_complete,
        GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') as team_members
    FROM projects p
    LEFT JOIN tasks t ON p.id = t.project_id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE p.status IN ('in_progress', 'planning', 'review')
    GROUP BY p.id
    HAVING blocked_tasks > 0 OR overdue_tasks > 2
    ORDER BY (blocked_tasks + overdue_tasks) DESC
    LIMIT 10
");
$projectBottlenecks->execute();
$projectIssues = $projectBottlenecks->fetchAll();
} catch (PDOException $e) {
    die("Database Error in project bottlenecks: " . $e->getMessage());
}

// === TEAM VELOCITY ===
try {
$teamVelocity = $db->prepare("
    SELECT
        DATE(t.completed_date) as date,
        COUNT(*) as tasks_completed,
        SUM(t.estimated_hours) as estimated_hours,
        SUM(tl.duration_minutes) / 60 as actual_hours
    FROM tasks t
    LEFT JOIN time_logs tl ON t.id = tl.task_id AND tl.end_time IS NOT NULL
    WHERE t.status = 'completed'
        AND DATE(t.completed_date) BETWEEN ? AND ?
    GROUP BY DATE(t.completed_date)
    ORDER BY date ASC
");
$teamVelocity->execute([$startDate, $endDate]);
$velocityData = $teamVelocity->fetchAll();
} catch (PDOException $e) {
    die("Database Error in team velocity: " . $e->getMessage());
}

// === CALCULATE SUMMARY STATS ===
$totalEmployees = count($employees);
$averageProductivity = $totalEmployees > 0 ?
    array_sum(array_column($employees, 'productivity_score')) / $totalEmployees : 0;
$totalBottlenecks = count($bottlenecks);
$topPerformer = $employees[0] ?? null;
$needsImprovement = array_filter($employees, function($emp) {
    return $emp['productivity_score'] < 60;
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: var(--space-6);
        margin-bottom: var(--space-10);
    }

    .performance-table {
        width: 100%;
        border-collapse: collapse;
    }

    .performance-table th {
        text-align: left;
        padding: var(--space-3);
        font-size: var(--font-xs);
        font-weight: 700;
        color: var(--text-tertiary);
        text-transform: uppercase;
        border-bottom: 2px solid var(--border);
        background: var(--bg-tertiary);
    }

    .performance-table td {
        padding: var(--space-4);
        border-bottom: 1px solid var(--border-light);
        font-size: var(--font-sm);
    }

    .performance-table tr:hover {
        background: var(--bg-tertiary);
    }

    .score-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-tertiary);
        border-radius: var(--radius-full);
        overflow: hidden;
    }

    .score-fill {
        height: 100%;
        border-radius: var(--radius-full);
        transition: width 0.3s ease;
    }

    .score-fill.excellent { background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); }
    .score-fill.good { background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%); }
    .score-fill.average { background: linear-gradient(90deg, #fa709a 0%, #fee140 100%); }
    .score-fill.poor { background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%); }

    .employee-avatar {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-md);
        background: var(--primary-gradient);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: var(--font-sm);
        margin-right: var(--space-2);
    }

    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: var(--radius-sm);
        font-weight: 700;
        font-size: var(--font-xs);
    }

    .rank-badge.gold {
        background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
        color: #000;
    }

    .rank-badge.silver {
        background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
        color: #000;
    }

    .rank-badge.bronze {
        background: linear-gradient(135deg, #cd7f32 0%, #e8a674 100%);
        color: #fff;
    }

    .bottleneck-alert {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
        border: 1px solid rgba(239, 68, 68, 0.3);
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-6);
    }

    .metric-mini {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: var(--space-1) var(--space-2);
        background: var(--bg-tertiary);
        border-radius: var(--radius-sm);
        font-size: var(--font-xs);
        color: var(--text-secondary);
    }

    .warning-badge {
        display: inline-flex;
        padding: var(--space-1) var(--space-2);
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        border-radius: var(--radius-sm);
        font-size: var(--font-xs);
        font-weight: 600;
    }
    </style>
    <?php include '../includes/quick-actions-assets.php'; ?>
</head>
<body>
    <div class="dashboard">
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="topbar">
                <h1>📊 Advanced Analytics</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                    <form method="GET" style="display: flex; gap: var(--space-2);">
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="form-control" style="width: auto;">
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="form-control" style="width: auto;">
                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    </form>
                </div>
            </div>

            <div class="content">
                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Team Avg Score</div>
                                <div class="stat-value"><?php echo round($averageProductivity); ?></div>
                                <div class="stat-change">Productivity rating</div>
                            </div>
                            <div class="stat-icon">📊</div>
                        </div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Top Performer</div>
                                <div class="stat-value" style="font-size: var(--font-xl);">
                                    <?php echo $topPerformer ? e($topPerformer['full_name']) : 'N/A'; ?>
                                </div>
                                <div class="stat-change">
                                    <?php echo $topPerformer ? round($topPerformer['productivity_score']) . ' score' : ''; ?>
                                </div>
                            </div>
                            <div class="stat-icon">🏆</div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Bottlenecks</div>
                                <div class="stat-value"><?php echo $totalBottlenecks; ?></div>
                                <div class="stat-change">Require attention</div>
                            </div>
                            <div class="stat-icon">⚠️</div>
                        </div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-label">Team Size</div>
                                <div class="stat-value"><?php echo $totalEmployees; ?></div>
                                <div class="stat-change">Active members</div>
                            </div>
                            <div class="stat-icon">👥</div>
                        </div>
                    </div>
                </div>

                <!-- Bottleneck Alerts -->
                <?php if (!empty($bottlenecks)): ?>
                <div class="bottleneck-alert">
                    <h3 style="color: #dc2626; font-size: var(--font-lg); margin-bottom: var(--space-2);">
                        🚨 Bottlenecks Detected
                    </h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--space-4);">
                        The following employees need immediate attention:
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                        <?php foreach ($bottlenecks as $emp): ?>
                        <div class="warning-badge">
                            <?php echo e($emp['full_name']); ?>
                            (<?php echo $emp['overdue_tasks']; ?> overdue)
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Employee Performance Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Employee Performance Analysis</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Employee</th>
                                    <th>Score</th>
                                    <th>Tasks</th>
                                    <th>Speed</th>
                                    <th>Quality</th>
                                    <th>Early</th>
                                    <th>Late</th>
                                    <th>Overdue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $index => $emp): ?>
                                <tr>
                                    <td>
                                        <?php if ($index === 0): ?>
                                            <span class="rank-badge gold">🥇</span>
                                        <?php elseif ($index === 1): ?>
                                            <span class="rank-badge silver">🥈</span>
                                        <?php elseif ($index === 2): ?>
                                            <span class="rank-badge bronze">🥉</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-tertiary); font-weight: 600;">#<?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div class="employee-avatar">
                                                <?php echo strtoupper(substr($emp['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo e($emp['full_name']); ?></strong><br>
                                                <small style="color: var(--text-tertiary);"><?php echo e($emp['job_title']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="min-width: 120px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-1);">
                                                <strong><?php echo $emp['productivity_score']; ?></strong>
                                                <span class="badge status-<?php echo $emp['category_color']; ?>">
                                                    <?php echo $emp['category']; ?>
                                                </span>
                                            </div>
                                            <div class="score-bar">
                                                <div class="score-fill <?php echo strtolower($emp['category']); ?>"
                                                     style="width: <?php echo $emp['productivity_score']; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="metric-mini">
                                            ✓ <?php echo $emp['completed_tasks']; ?> / <?php echo $emp['total_tasks']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-mini">
                                            ⚡ <?php echo $emp['speed_score']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-mini">
                                            ✨ <?php echo $emp['quality_score']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-mini" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                            🎯 <?php echo $emp['early_completions']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($emp['late_completions'] > 0): ?>
                                        <span class="metric-mini" style="background: rgba(245, 158, 11, 0.1); color: #d97706;">
                                            ⏰ <?php echo $emp['late_completions']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="color: var(--text-tertiary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp['overdue_tasks'] > 0): ?>
                                        <span class="metric-mini" style="background: rgba(239, 68, 68, 0.1); color: #dc2626;">
                                            🚨 <?php echo $emp['overdue_tasks']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="color: var(--text-tertiary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp['is_bottleneck']): ?>
                                        <span class="warning-badge">⚠️ Bottleneck</span>
                                        <?php else: ?>
                                        <span class="badge status-completed">✓ Good</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Project Bottlenecks -->
                <?php if (!empty($projectIssues)): ?>
                <div class="card" style="margin-top: var(--space-8);">
                    <div class="card-header">
                        <h3>🚨 Project Bottlenecks</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Blocked Tasks</th>
                                    <th>Overdue Tasks</th>
                                    <th>Avg Days</th>
                                    <th>Team</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projectIssues as $proj): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($proj['project_name']); ?></strong><br>
                                        <small style="color: var(--text-tertiary);"><?php echo e($proj['client_name']); ?></small>
                                    </td>
                                    <td><span class="badge status-progress"><?php echo $proj['status']; ?></span></td>
                                    <td>
                                        <?php if ($proj['blocked_tasks'] > 0): ?>
                                        <span class="warning-badge"><?php echo $proj['blocked_tasks']; ?> blocked</span>
                                        <?php else: ?>
                                        <span style="color: var(--text-tertiary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($proj['overdue_tasks'] > 0): ?>
                                        <span class="warning-badge"><?php echo $proj['overdue_tasks']; ?> overdue</span>
                                        <?php else: ?>
                                        <span style="color: var(--text-tertiary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo round($proj['avg_days_to_complete']); ?> days</td>
                                    <td><small><?php echo e($proj['team_members']); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
