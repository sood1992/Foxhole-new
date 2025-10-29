<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('manager'))) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get date range (default last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Get daily hours data for chart
$dailyHours = $db->prepare("
    SELECT
        DATE(start_time) as date,
        SUM(duration_minutes)/60 as hours
    FROM time_logs
    WHERE DATE(start_time) BETWEEN ? AND ?
        AND end_time IS NOT NULL
    GROUP BY DATE(start_time)
    ORDER BY date ASC
");
$dailyHours->execute([$startDate, $endDate]);
$dailyHoursData = $dailyHours->fetchAll();

// Get employee productivity comparison
$employeeProductivity = $db->query("
    SELECT
        u.full_name,
        SUM(tl.duration_minutes)/60 as total_hours,
        COUNT(DISTINCT tl.task_id) as tasks_completed
    FROM users u
    LEFT JOIN time_logs tl ON u.id = tl.user_id
        AND tl.end_time IS NOT NULL
        AND MONTH(tl.start_time) = MONTH(CURRENT_DATE())
    WHERE u.role IN ('manager', 'employee') AND u.is_active = 1
    GROUP BY u.id
    ORDER BY total_hours DESC
    LIMIT 10
")->fetchAll();

// Get project status distribution
$projectStatus = $db->query("
    SELECT
        status,
        COUNT(*) as count
    FROM projects
    GROUP BY status
")->fetchAll();

// Get task completion trend (last 12 weeks)
$taskTrend = $db->query("
    SELECT
        YEARWEEK(completed_date) as week,
        COUNT(*) as completed
    FROM tasks
    WHERE completed_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
        AND status = 'completed'
    GROUP BY YEARWEEK(completed_date)
    ORDER BY week ASC
")->fetchAll();

// Get priority distribution
$priorityDist = $db->query("
    SELECT
        priority,
        COUNT(*) as count
    FROM tasks
    WHERE status != 'completed'
    GROUP BY priority
")->fetchAll();

// Get hourly distribution (peak working hours)
$hourlyDist = $db->query("
    SELECT
        HOUR(start_time) as hour,
        COUNT(*) as sessions
    FROM time_logs
    WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY HOUR(start_time)
    ORDER BY hour ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>📈 Analytics & Insights</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                    <form method="GET" style="display: flex; gap: 8px; margin-left: 12px;">
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="form-control" style="width: auto; padding: 6px 12px;">
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="form-control" style="width: auto; padding: 6px 12px;">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    </form>
                </div>
            </div>

            <div class="content">
                <!-- Chart Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">

                    <!-- Daily Hours Trend -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📊 Daily Hours Tracked</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyHoursChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Employee Productivity -->
                    <div class="card">
                        <div class="card-header">
                            <h3>👥 Employee Productivity (This Month)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="employeeChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Project Status Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📁 Project Status Distribution</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="projectStatusChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Task Completion Trend -->
                    <div class="card">
                        <div class="card-header">
                            <h3>✓ Task Completion Trend (12 Weeks)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="taskTrendChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Priority Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3>⚡ Active Tasks by Priority</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="priorityChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Peak Working Hours -->
                    <div class="card">
                        <div class="card-header">
                            <h3>⏰ Peak Working Hours (Last 30 Days)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart" height="250"></canvas>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
    // Chart.js defaults
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    // Daily Hours Chart
    const dailyHoursCtx = document.getElementById('dailyHoursChart').getContext('2d');
    new Chart(dailyHoursCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($dailyHoursData, 'date')); ?>,
            datasets: [{
                label: 'Hours',
                data: <?php echo json_encode(array_column($dailyHoursData, 'hours')); ?>,
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

    // Employee Productivity Chart
    const employeeCtx = document.getElementById('employeeChart').getContext('2d');
    new Chart(employeeCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($employeeProductivity, 'full_name')); ?>,
            datasets: [{
                label: 'Hours Worked',
                data: <?php echo json_encode(array_column($employeeProductivity, 'total_hours')); ?>,
                backgroundColor: '#10b981'
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

    // Project Status Chart
    const projectStatusCtx = document.getElementById('projectStatusChart').getContext('2d');
    new Chart(projectStatusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($projectStatus, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($projectStatus, 'count')); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Task Trend Chart
    const taskTrendCtx = document.getElementById('taskTrendChart').getContext('2d');
    new Chart(taskTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($taskTrend, 'week')); ?>,
            datasets: [{
                label: 'Tasks Completed',
                data: <?php echo json_encode(array_column($taskTrend, 'completed')); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
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

    // Priority Distribution Chart
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    new Chart(priorityCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($priorityDist, 'priority')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($priorityDist, 'count')); ?>,
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Hourly Distribution Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($h) { return $h['hour'] . ':00'; }, $hourlyDist)); ?>,
            datasets: [{
                label: 'Sessions Started',
                data: <?php echo json_encode(array_column($hourlyDist, 'sessions')); ?>,
                backgroundColor: '#3b82f6'
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
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
