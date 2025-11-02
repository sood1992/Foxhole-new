<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('employee')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$period = $_GET['period'] ?? 'week';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .analytics-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .analytics-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        }

        .analytics-value {
            font-size: 48px;
            font-weight: 700;
            margin: 16px 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytics-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .analytics-sublabel {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 8px;
        }

        .chart-container {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
        }

        .period-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
        }

        .period-btn {
            padding: 10px 20px;
            border: 2px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .period-btn:hover {
            border-color: var(--primary);
        }

        .period-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .project-breakdown-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }

        .project-breakdown-bar {
            flex: 1;
            height: 8px;
            background: var(--bg-primary);
            border-radius: 4px;
            margin: 0 16px;
            overflow: hidden;
        }

        .project-breakdown-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .efficiency-indicator {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .efficiency-accurate {
            background: var(--green);
            color: white;
        }

        .efficiency-over {
            background: var(--orange);
            color: white;
        }

        .efficiency-under {
            background: var(--blue);
            color: white;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/v3-employee-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div>
                        <h1>📊 Time Analytics</h1>
                        <p class="page-subtitle">Insights into your productivity and time usage</p>
                    </div>
                </div>

                <!-- Period Selector -->
                <div class="period-selector">
                    <button class="period-btn <?php echo $period === 'today' ? 'active' : ''; ?>"
                            onclick="window.location.href='?period=today'">
                        Today
                    </button>
                    <button class="period-btn <?php echo $period === 'week' ? 'active' : ''; ?>"
                            onclick="window.location.href='?period=week'">
                        This Week
                    </button>
                    <button class="period-btn <?php echo $period === 'month' ? 'active' : ''; ?>"
                            onclick="window.location.href='?period=month'">
                        This Month
                    </button>
                    <button class="period-btn <?php echo $period === 'year' ? 'active' : ''; ?>"
                            onclick="window.location.href='?period=year'">
                        This Year
                    </button>
                </div>

                <!-- Overview Stats -->
                <div id="overviewStats" class="analytics-grid">
                    <div class="analytics-card">
                        <div class="analytics-label">Total Hours</div>
                        <div class="analytics-value" id="totalHours">-</div>
                        <div class="analytics-sublabel" id="totalMinutes">-</div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-label">Days Active</div>
                        <div class="analytics-value" id="daysActive">-</div>
                        <div class="analytics-sublabel" id="avgHoursPerDay">-</div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-label">Tasks Completed</div>
                        <div class="analytics-value" id="tasksCompleted">-</div>
                        <div class="analytics-sublabel" id="priorityTasks">-</div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-label">Productivity Score</div>
                        <div class="analytics-value" id="productivityScore">-</div>
                        <div class="analytics-sublabel">Based on time logged</div>
                    </div>
                </div>

                <!-- Daily Time Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">📈 Daily Time Tracking</div>
                    </div>
                    <canvas id="dailyTimeChart" height="80"></canvas>
                </div>

                <!-- Two Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                    <!-- Project Breakdown -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <div class="chart-title">🎯 Time by Project</div>
                        </div>
                        <div id="projectBreakdown"></div>
                    </div>

                    <!-- Project Distribution Pie Chart -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <div class="chart-title">📊 Project Distribution</div>
                        </div>
                        <canvas id="projectPieChart"></canvas>
                    </div>
                </div>

                <!-- Task Efficiency Analysis -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">🎓 Task Efficiency (Estimated vs Actual)</div>
                        <div id="efficiencySummary"></div>
                    </div>
                    <div id="efficiencyAnalysis"></div>
                </div>

                <!-- Productivity Trends -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">📅 Weekly Productivity Trends (Last 12 Weeks)</div>
                    </div>
                    <canvas id="trendsChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        const period = '<?php echo $period; ?>';
        let dailyTimeChart, projectPieChart, trendsChart;

        // Load analytics data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadOverviewData();
            loadEfficiencyData();
            loadTrendsData();
        });

        async function loadOverviewData() {
            try {
                const response = await fetch(`../api/time-analytics.php?action=overview&period=${period}`);
                const data = await response.json();

                if (data.success) {
                    displayOverview(data);
                    displayDailyChart(data.daily_time);
                    displayProjectBreakdown(data.project_breakdown);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function displayOverview(data) {
            const overview = data.overview;

            document.getElementById('totalHours').textContent = overview.total_hours;
            document.getElementById('totalMinutes').textContent = `${overview.total_minutes.toLocaleString()} minutes`;

            document.getElementById('daysActive').textContent = overview.days_active;
            document.getElementById('avgHoursPerDay').textContent = `${overview.avg_hours_per_day}h avg per day`;

            document.getElementById('tasksCompleted').textContent = overview.tasks_completed;
            document.getElementById('priorityTasks').textContent =
                `${overview.urgent_completed} urgent · ${overview.high_completed} high`;

            document.getElementById('productivityScore').textContent = overview.productivity_score + '%';
        }

        function displayDailyChart(dailyTime) {
            const ctx = document.getElementById('dailyTimeChart');

            if (dailyTimeChart) {
                dailyTimeChart.destroy();
            }

            dailyTimeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dailyTime.map(d => {
                        const date = new Date(d.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Hours Tracked',
                        data: dailyTime.map(d => d.hours),
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y.toFixed(2) + ' hours';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: {
                                callback: function(value) {
                                    return value + 'h';
                                }
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function displayProjectBreakdown(projects) {
            const container = document.getElementById('projectBreakdown');

            if (projects.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 32px;">No project data available</p>';
                return;
            }

            const maxMinutes = Math.max(...projects.map(p => parseFloat(p.total_minutes)));

            container.innerHTML = projects.map(project => {
                const hours = (parseFloat(project.total_minutes) / 60).toFixed(1);
                const percentage = (parseFloat(project.total_minutes) / maxMinutes * 100).toFixed(0);

                return `
                    <div class="project-breakdown-item">
                        <div style="min-width: 150px; text-align: left;">
                            <strong>${escapeHtml(project.project_name)}</strong>
                        </div>
                        <div class="project-breakdown-bar">
                            <div class="project-breakdown-fill" style="width: ${percentage}%"></div>
                        </div>
                        <div style="min-width: 80px; text-align: right; font-weight: 600;">
                            ${hours}h
                        </div>
                    </div>
                `;
            }).join('');

            // Create pie chart
            displayProjectPieChart(projects);
        }

        function displayProjectPieChart(projects) {
            const ctx = document.getElementById('projectPieChart');

            if (projectPieChart) {
                projectPieChart.destroy();
            }

            const colors = [
                'rgba(102, 126, 234, 0.8)',
                'rgba(118, 75, 162, 0.8)',
                'rgba(237, 100, 166, 0.8)',
                'rgba(255, 154, 158, 0.8)',
                'rgba(250, 208, 196, 0.8)',
                'rgba(165, 177, 194, 0.8)',
                'rgba(52, 172, 224, 0.8)',
                'rgba(72, 219, 251, 0.8)',
                'rgba(29, 233, 182, 0.8)',
                'rgba(253, 203, 110, 0.8)'
            ];

            projectPieChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: projects.map(p => p.project_name),
                    datasets: [{
                        data: projects.map(p => (parseFloat(p.total_minutes) / 60).toFixed(2)),
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: 'var(--bg-primary)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' hours';
                                }
                            }
                        }
                    }
                }
            });
        }

        async function loadEfficiencyData() {
            try {
                const response = await fetch(`../api/time-analytics.php?action=task_efficiency&period=${period}`);
                const data = await response.json();

                if (data.success) {
                    displayEfficiency(data);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function displayEfficiency(data) {
            const summary = data.summary;
            const tasks = data.efficiency_data;

            // Display summary
            document.getElementById('efficiencySummary').innerHTML = `
                <div style="text-align: right; font-size: 14px;">
                    <span class="efficiency-accurate">${summary.accurate_count} Accurate (±10%)</span>
                    <span class="efficiency-over" style="margin-left: 8px;">${summary.over_count} Over</span>
                    <span class="efficiency-under" style="margin-left: 8px;">${summary.under_count} Under</span>
                    <div style="margin-top: 8px; color: var(--text-secondary);">
                        Avg Variance: ${summary.avg_variance}% · Accuracy Rate: ${summary.accuracy_rate}%
                    </div>
                </div>
            `;

            // Display tasks
            const container = document.getElementById('efficiencyAnalysis');

            if (tasks.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 32px;">No task efficiency data available</p>';
                return;
            }

            container.innerHTML = tasks.slice(0, 10).map(task => {
                const variance = parseFloat(task.variance_percentage);
                let badge = '';

                if (Math.abs(variance) <= 10) {
                    badge = '<span class="efficiency-indicator efficiency-accurate">✓ Accurate</span>';
                } else if (variance > 0) {
                    badge = `<span class="efficiency-indicator efficiency-over">+${variance.toFixed(0)}% Over</span>`;
                } else {
                    badge = `<span class="efficiency-indicator efficiency-under">${variance.toFixed(0)}% Under</span>`;
                }

                return `
                    <div style="padding: 16px; background: var(--bg-tertiary); border-radius: var(--radius-md); margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; margin-bottom: 4px;">${escapeHtml(task.task_name)}</div>
                                <div style="font-size: 13px; color: var(--text-secondary);">
                                    ${escapeHtml(task.project_name)} · Completed ${new Date(task.completed_date).toLocaleDateString()}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                ${badge}
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                    Est: ${task.estimated_hours}h · Actual: ${parseFloat(task.actual_hours).toFixed(1)}h
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async function loadTrendsData() {
            try {
                const response = await fetch('../api/time-analytics.php?action=productivity_trends');
                const data = await response.json();

                if (data.success) {
                    displayTrends(data.trends);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function displayTrends(trends) {
            const ctx = document.getElementById('trendsChart');

            if (trendsChart) {
                trendsChart.destroy();
            }

            trendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trends.map(t => {
                        const date = new Date(t.week_start);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Hours per Week',
                        data: trends.map(t => parseFloat(t.hours)),
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y.toFixed(1) + ' hours';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: {
                                callback: function(value) {
                                    return value + 'h';
                                }
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
