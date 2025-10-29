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
    <title>Analytics - <?php echo SITE_NAME; ?> V3</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Title -->
                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h1 style="margin-bottom: 8px;">Analytics & Insights</h1>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                            Visual analytics and performance metrics
                        </p>
                    </div>
                    <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="form-control" style="width: auto;">
                        <span style="color: var(--text-secondary);">to</span>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="form-control" style="width: auto;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </form>
                </div>

                <!-- Chart Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">

                    <!-- Daily Hours Trend -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-chart-area"></i> Daily Hours Tracked</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Time logged per day
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 250px;">
                                <canvas id="dailyHoursChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Productivity -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-users"></i> Employee Productivity</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Top 10 employees this month
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 250px;">
                                <canvas id="employeeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Project Status Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-folder"></i> Project Status Distribution</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Projects by status
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 250px;">
                                <canvas id="projectStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Task Completion Trend -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-chart-line"></i> Task Completion Trend</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Tasks completed over last 12 weeks
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 250px;">
                                <canvas id="taskTrendChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Priority Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-exclamation-circle"></i> Active Tasks by Priority</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Distribution of incomplete tasks
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 250px;">
                                <canvas id="priorityChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Peak Working Hours -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 style="margin: 0;"><i class="fas fa-clock"></i> Peak Working Hours</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                                    Session starts by hour (last 30 days)
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 250px;">
                                <canvas id="hourlyChart"></canvas>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
    // Chart.js defaults - V3 colors
    Chart.defaults.font.family = "'Nunito', sans-serif";
    Chart.defaults.color = '#6b7280';

    // V3 Color palette
    const v3Colors = {
        primary: '#145388',
        success: '#17b06b',
        warning: '#f8b739',
        danger: '#ec4561',
        info: '#0dcaf0',
        purple: '#9b59b6'
    };

    // Daily Hours Chart
    const dailyHoursCtx = document.getElementById('dailyHoursChart').getContext('2d');
    new Chart(dailyHoursCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($dailyHoursData, 'date')); ?>,
            datasets: [{
                label: 'Hours',
                data: <?php echo json_encode(array_column($dailyHoursData, 'hours')); ?>,
                borderColor: v3Colors.primary,
                backgroundColor: 'rgba(20, 83, 136, 0.1)',
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
                backgroundColor: v3Colors.success
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
                backgroundColor: [v3Colors.primary, v3Colors.success, v3Colors.warning, v3Colors.danger, v3Colors.purple, v3Colors.info]
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
                borderColor: v3Colors.success,
                backgroundColor: 'rgba(23, 176, 107, 0.1)',
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
                backgroundColor: [v3Colors.success, v3Colors.primary, v3Colors.warning, v3Colors.danger]
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
                backgroundColor: v3Colors.primary
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
</body>
</html>
