<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

$action = $_GET['action'] ?? 'overview';
$period = $_GET['period'] ?? 'week'; // day, week, month, year
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Calculate date range based on period
if (!$startDate || !$endDate) {
    switch ($period) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            break;
        case 'week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            break;
        case 'year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
    }
}

try {
    switch ($action) {
        case 'overview':
            // Total time tracked
            $totalTimeStmt = $db->prepare("
                SELECT
                    SUM(duration_minutes) as total_minutes,
                    COUNT(DISTINCT DATE(start_time)) as days_active
                FROM time_logs
                WHERE user_id = ?
                    AND DATE(start_time) BETWEEN ? AND ?
            ");
            $totalTimeStmt->execute([$currentUser['id'], $startDate, $endDate]);
            $totalTime = $totalTimeStmt->fetch();

            // Task completion stats
            $tasksStmt = $db->prepare("
                SELECT
                    COUNT(*) as total_completed,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_completed,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_completed,
                    AVG(actual_hours) as avg_hours_per_task
                FROM tasks
                WHERE assigned_to = ?
                    AND status = 'completed'
                    AND DATE(completed_date) BETWEEN ? AND ?
            ");
            $tasksStmt->execute([$currentUser['id'], $startDate, $endDate]);
            $tasks = $tasksStmt->fetch();

            // Productivity score (based on time logged and tasks completed)
            $expectedHoursPerDay = 8;
            $daysInPeriod = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400 + 1);
            $expectedTotalHours = $expectedHoursPerDay * $daysInPeriod;
            $actualHours = ($totalTime['total_minutes'] ?? 0) / 60;
            $productivityScore = min(100, round(($actualHours / $expectedTotalHours) * 100));

            // Time by project
            $projectTimeStmt = $db->prepare("
                SELECT
                    p.project_name,
                    p.id as project_id,
                    SUM(tl.duration_minutes) as total_minutes,
                    COUNT(DISTINCT tl.id) as log_count
                FROM time_logs tl
                JOIN projects p ON tl.project_id = p.id
                WHERE tl.user_id = ?
                    AND DATE(tl.start_time) BETWEEN ? AND ?
                GROUP BY p.id, p.project_name
                ORDER BY total_minutes DESC
                LIMIT 10
            ");
            $projectTimeStmt->execute([$currentUser['id'], $startDate, $endDate]);
            $projectTime = $projectTimeStmt->fetchAll();

            // Time by day (for chart)
            $dailyTimeStmt = $db->prepare("
                SELECT
                    DATE(start_time) as date,
                    SUM(duration_minutes) / 60 as hours
                FROM time_logs
                WHERE user_id = ?
                    AND DATE(start_time) BETWEEN ? AND ?
                GROUP BY DATE(start_time)
                ORDER BY date ASC
            ");
            $dailyTimeStmt->execute([$currentUser['id'], $startDate, $endDate]);
            $dailyTime = $dailyTimeStmt->fetchAll();

            // Fill in missing dates with 0 hours
            $dailyTimeMap = [];
            foreach ($dailyTime as $day) {
                $dailyTimeMap[$day['date']] = floatval($day['hours']);
            }

            $currentDate = strtotime($startDate);
            $endTimestamp = strtotime($endDate);
            $dailyTimeComplete = [];

            while ($currentDate <= $endTimestamp) {
                $dateStr = date('Y-m-d', $currentDate);
                $dailyTimeComplete[] = [
                    'date' => $dateStr,
                    'hours' => $dailyTimeMap[$dateStr] ?? 0
                ];
                $currentDate = strtotime('+1 day', $currentDate);
            }

            echo json_encode([
                'success' => true,
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'overview' => [
                    'total_hours' => round($actualHours, 2),
                    'total_minutes' => $totalTime['total_minutes'] ?? 0,
                    'days_active' => $totalTime['days_active'] ?? 0,
                    'avg_hours_per_day' => $totalTime['days_active'] > 0 ? round($actualHours / $totalTime['days_active'], 2) : 0,
                    'productivity_score' => $productivityScore,
                    'tasks_completed' => $tasks['total_completed'] ?? 0,
                    'urgent_completed' => $tasks['urgent_completed'] ?? 0,
                    'high_completed' => $tasks['high_completed'] ?? 0,
                    'avg_hours_per_task' => round($tasks['avg_hours_per_task'] ?? 0, 2)
                ],
                'project_breakdown' => $projectTime,
                'daily_time' => $dailyTimeComplete
            ]);
            break;

        case 'heatmap':
            // Get time tracked for each day in the last year for heatmap
            $heatmapStmt = $db->prepare("
                SELECT
                    DATE(start_time) as date,
                    SUM(duration_minutes) / 60 as hours
                FROM time_logs
                WHERE user_id = ?
                    AND DATE(start_time) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                GROUP BY DATE(start_time)
            ");
            $heatmapStmt->execute([$currentUser['id']]);
            $heatmapData = $heatmapStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'heatmap_data' => $heatmapData
            ]);
            break;

        case 'task_efficiency':
            // Compare estimated vs actual hours
            $efficiencyStmt = $db->prepare("
                SELECT
                    t.id,
                    t.task_name,
                    p.project_name,
                    t.estimated_hours,
                    t.actual_hours,
                    t.completed_date,
                    CASE
                        WHEN t.estimated_hours > 0 THEN
                            ((t.actual_hours - t.estimated_hours) / t.estimated_hours) * 100
                        ELSE 0
                    END as variance_percentage
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                WHERE t.assigned_to = ?
                    AND t.status = 'completed'
                    AND t.estimated_hours IS NOT NULL
                    AND t.estimated_hours > 0
                    AND DATE(t.completed_date) BETWEEN ? AND ?
                ORDER BY t.completed_date DESC
                LIMIT 50
            ");
            $efficiencyStmt->execute([$currentUser['id'], $startDate, $endDate]);
            $efficiency = $efficiencyStmt->fetchAll();

            // Calculate average accuracy
            $avgVariance = 0;
            $accurateCount = 0; // Within 10% of estimate
            $overCount = 0;
            $underCount = 0;

            foreach ($efficiency as $task) {
                $variance = floatval($task['variance_percentage']);
                $avgVariance += abs($variance);

                if (abs($variance) <= 10) {
                    $accurateCount++;
                } elseif ($variance > 0) {
                    $overCount++;
                } else {
                    $underCount++;
                }
            }

            $totalTasks = count($efficiency);
            $avgVariance = $totalTasks > 0 ? $avgVariance / $totalTasks : 0;
            $accuracyRate = $totalTasks > 0 ? ($accurateCount / $totalTasks) * 100 : 0;

            echo json_encode([
                'success' => true,
                'efficiency_data' => $efficiency,
                'summary' => [
                    'avg_variance' => round($avgVariance, 1),
                    'accuracy_rate' => round($accuracyRate, 1),
                    'accurate_count' => $accurateCount,
                    'over_count' => $overCount,
                    'under_count' => $underCount,
                    'total_tasks' => $totalTasks
                ]
            ]);
            break;

        case 'productivity_trends':
            // Weekly productivity comparison
            $trendsStmt = $db->prepare("
                SELECT
                    YEARWEEK(start_time, 1) as week,
                    DATE(DATE_SUB(start_time, INTERVAL WEEKDAY(start_time) DAY)) as week_start,
                    SUM(duration_minutes) / 60 as hours,
                    COUNT(DISTINCT DATE(start_time)) as active_days
                FROM time_logs
                WHERE user_id = ?
                    AND start_time >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                GROUP BY YEARWEEK(start_time, 1)
                ORDER BY week ASC
            ");
            $trendsStmt->execute([$currentUser['id']]);
            $trends = $trendsStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'trends' => $trends
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
