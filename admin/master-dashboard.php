<?php
/**
 * Admin Master Dashboard - Complete Office Overview
 * Shows all projects, team activities, bottlenecks, and metrics in one comprehensive view
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

try {
    // ==================== KEY METRICS ====================
    $metrics = [];

    // Total Active Projects
    $stmt = $db->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('planning', 'in_progress', 'review')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['active_projects'] = $result['count'] ?? 0;

    // Total Team Members
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role IN ('manager', 'employee') AND is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['team_members'] = $result['count'] ?? 0;

    // Tasks In Progress
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'in_progress'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['tasks_in_progress'] = $result['count'] ?? 0;

    // Blocked/Overdue Tasks
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'blocked' OR (due_date < CURDATE() AND status NOT IN ('completed', 'cancelled'))");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $metrics['bottlenecks'] = $result['count'] ?? 0;

    // Hours This Week
    $stmt = $db->query("SELECT SUM(duration_minutes) as total FROM time_logs WHERE WEEK(start_time) = WEEK(CURRENT_DATE()) AND YEAR(start_time) = YEAR(CURRENT_DATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalMinutes = $result['total'] ?? 0;
    $metrics['hours_week'] = round($totalMinutes / 60, 1);

    // Completion Rate This Month
    $stmt = $db->query("SELECT COUNT(*) as total FROM tasks WHERE MONTH(created_at) = MONTH(CURRENT_DATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTasks = $result['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as completed FROM tasks WHERE MONTH(completed_date) = MONTH(CURRENT_DATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $completedTasks = $result['completed'] ?? 0;
    $metrics['completion_rate'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

    // ==================== ALL PROJECTS WITH DETAILS ====================
    $projects = $db->query("
        SELECT
            p.*,
            u.full_name as manager_name,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'in_progress') as active_tasks,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'blocked') as blocked_tasks,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND due_date < CURDATE() AND status NOT IN ('completed', 'cancelled')) as overdue_tasks,
            (SELECT SUM(duration_minutes) FROM time_logs tl JOIN tasks t ON tl.task_id = t.id WHERE t.project_id = p.id) as total_minutes,
            DATEDIFF(p.due_date, CURDATE()) as days_remaining
        FROM projects p
        LEFT JOIN users u ON p.assigned_manager = u.id
        WHERE p.status != 'cancelled'
        ORDER BY
            FIELD(p.status, 'in_progress', 'planning', 'review', 'on_hold', 'completed'),
            p.due_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== TEAM ACTIVITY ====================
    $teamActivity = $db->query("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.role,
            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status != 'completed') as active_tasks,
            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'in_progress') as in_progress_tasks,
            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed' AND MONTH(completed_date) = MONTH(CURRENT_DATE())) as completed_this_month,
            (SELECT SUM(duration_minutes) FROM time_logs WHERE user_id = u.id AND WEEK(start_time) = WEEK(CURRENT_DATE())) as minutes_this_week,
            (SELECT task_name FROM tasks WHERE assigned_to = u.id AND status = 'in_progress' ORDER BY due_date ASC LIMIT 1) as current_task,
            (SELECT project_name FROM projects p JOIN tasks t ON p.id = t.project_id WHERE t.assigned_to = u.id AND t.status = 'in_progress' ORDER BY t.due_date ASC LIMIT 1) as current_project
        FROM users u
        WHERE u.role IN ('manager', 'employee') AND u.is_active = 1
        ORDER BY active_tasks DESC, minutes_this_week DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== BOTTLENECKS & CRITICAL ISSUES ====================
    $bottlenecks = $db->query("
        SELECT
            t.*,
            p.project_name,
            p.client_name,
            u.full_name as assigned_name,
            DATEDIFF(CURDATE(), t.due_date) as days_overdue
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.status = 'blocked'
           OR (t.due_date < CURDATE() AND t.status NOT IN ('completed', 'cancelled'))
        ORDER BY
            FIELD(t.status, 'blocked', 'in_progress', 'todo'),
            t.due_date ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== UPCOMING DEADLINES ====================
    $upcomingDeadlines = $db->query("
        SELECT
            t.*,
            p.project_name,
            p.client_name,
            u.full_name as assigned_name,
            DATEDIFF(t.due_date, CURDATE()) as days_until_due
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.due_date >= CURDATE()
          AND t.status NOT IN ('completed', 'cancelled')
        ORDER BY t.due_date ASC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== RECENT ACTIVITY FEED ====================
    $recentActivity = $db->query("
        (SELECT
            'task_completed' as activity_type,
            t.task_name as title,
            p.project_name as subtitle,
            u.full_name as user_name,
            t.completed_date as activity_time
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.completed_date IS NOT NULL
        ORDER BY t.completed_date DESC
        LIMIT 5)

        UNION ALL

        (SELECT
            'project_created' as activity_type,
            p.project_name as title,
            p.client_name as subtitle,
            u.full_name as user_name,
            p.created_at as activity_time
        FROM projects p
        LEFT JOIN users u ON p.created_by = u.id
        ORDER BY p.created_at DESC
        LIMIT 5)

        ORDER BY activity_time DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== FINANCIAL OVERVIEW ====================
    $financial = [];

    // Total Revenue (sum of all active project budgets)
    $stmt = $db->query("
        SELECT
            COALESCE(SUM(pb.client_quote), 0) as total_revenue,
            COALESCE(SUM(pb.total_budget), 0) as total_budgets
        FROM projects p
        LEFT JOIN project_budgets pb ON p.id = pb.project_id
        WHERE p.status IN ('planning', 'in_progress', 'review')
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $financial['total_revenue'] = $result['total_revenue'] ?? 0;
    $financial['total_budgets'] = $result['total_budgets'] ?? 0;

    // Total spent across all projects
    $stmt = $db->query("
        SELECT
            COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) as labor_cost,
            COALESCE((SELECT SUM(amount) FROM project_expenses WHERE approval_status = 'approved'), 0) as expenses_total
        FROM time_logs tl
        JOIN users u ON tl.user_id = u.id
        WHERE tl.end_time IS NOT NULL
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $financial['labor_cost'] = $result['labor_cost'] ?? 0;
    $financial['expenses_total'] = $result['expenses_total'] ?? 0;
    $financial['total_spent'] = $financial['labor_cost'] + $financial['expenses_total'];
    $financial['profit_margin'] = $financial['total_revenue'] > 0
        ? round((($financial['total_revenue'] - $financial['total_spent']) / $financial['total_revenue']) * 100, 1)
        : 0;

    // Over-budget projects
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM projects p
        JOIN project_budgets pb ON p.id = pb.project_id
        WHERE p.status IN ('planning', 'in_progress', 'review')
        AND (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0) + COALESCE((SELECT SUM(amount) FROM project_expenses pe WHERE pe.project_id = p.id AND pe.approval_status = 'approved'), 0)
             FROM time_logs tl
             JOIN users u ON tl.user_id = u.id
             JOIN tasks t ON tl.task_id = t.id
             WHERE t.project_id = p.id) > pb.total_budget
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $financial['over_budget_count'] = $result['count'] ?? 0;

    // Most profitable project this month
    $stmt = $db->query("
        SELECT p.project_name, pb.client_quote,
            (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0)
             FROM time_logs tl
             JOIN users u ON tl.user_id = u.id
             JOIN tasks t ON tl.task_id = t.id
             WHERE t.project_id = p.id) as cost,
            pb.client_quote - (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0)
                               FROM time_logs tl
                               JOIN users u ON tl.user_id = u.id
                               JOIN tasks t ON tl.task_id = t.id
                               WHERE t.project_id = p.id) as profit
        FROM projects p
        JOIN project_budgets pb ON p.id = pb.project_id
        WHERE p.status = 'completed'
        AND MONTH(p.completed_date) = MONTH(CURRENT_DATE())
        AND pb.client_quote > 0
        ORDER BY profit DESC
        LIMIT 1
    ");
    $financial['top_project'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // ==================== CLIENT HAPPINESS METRICS ====================
    $clientMetrics = [];

    // Total feedback and approval stats
    $stmt = $db->query("
        SELECT
            COUNT(*) as total_feedback,
            SUM(CASE WHEN client_approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN client_approval_status = 'revision_requested' THEN 1 ELSE 0 END) as revision_count,
            AVG(revision_count) as avg_revisions
        FROM client_feedback
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientMetrics['total_feedback'] = $result['total_feedback'] ?? 0;
    $clientMetrics['approved_count'] = $result['approved_count'] ?? 0;
    $clientMetrics['revision_count'] = $result['revision_count'] ?? 0;
    $clientMetrics['avg_revisions'] = round($result['avg_revisions'] ?? 0, 1);
    $clientMetrics['approval_rate'] = $clientMetrics['total_feedback'] > 0
        ? round(($clientMetrics['approved_count'] / $clientMetrics['total_feedback']) * 100, 1)
        : 0;

    // Pending feedback count
    $stmt = $db->query("SELECT COUNT(*) as count FROM client_feedback WHERE status != 'completed'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientMetrics['pending_feedback'] = $result['count'] ?? 0;

    // Projects with 0 revisions
    $stmt = $db->query("
        SELECT COUNT(DISTINCT project_id) as count
        FROM client_feedback
        WHERE revision_count = 0
        AND client_approval_status = 'approved'
        AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientMetrics['perfect_projects'] = $result['count'] ?? 0;

    // ==================== PROJECT TYPE BREAKDOWN ====================
    // Note: You'll need to add a 'project_type' column to projects table or categorize by description
    $projectTypes = $db->query("
        SELECT
            CASE
                WHEN LOWER(project_name) LIKE '%video%' OR LOWER(project_name) LIKE '%edit%' THEN 'Video Editing'
                WHEN LOWER(project_name) LIKE '%photo%' THEN 'Photography'
                WHEN LOWER(project_name) LIKE '%shoot%' OR LOWER(project_name) LIKE '%videography%' THEN 'Videography'
                WHEN LOWER(project_name) LIKE '%strategy%' OR LOWER(project_name) LIKE '%creative%' THEN 'Creative Strategy'
                ELSE 'Other'
            END as project_type,
            COUNT(*) as count
        FROM projects
        WHERE status IN ('planning', 'in_progress', 'review')
        GROUP BY project_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== TEAM CAPACITY & UTILIZATION ====================
    $teamCapacity = [];

    // Calculate team utilization (40 hours = 100% capacity)
    $stmt = $db->query("
        SELECT
            COUNT(*) as total_team,
            SUM(CASE WHEN weekly_hours >= 40 THEN 1 ELSE 0 END) as overloaded,
            SUM(CASE WHEN weekly_hours < 30 THEN 1 ELSE 0 END) as available,
            AVG(weekly_hours) as avg_hours
        FROM (
            SELECT
                u.id,
                COALESCE(SUM(tl.duration_minutes) / 60, 0) as weekly_hours
            FROM users u
            LEFT JOIN time_logs tl ON u.id = tl.user_id
                AND WEEK(tl.start_time) = WEEK(CURRENT_DATE())
            WHERE u.role IN ('manager', 'employee') AND u.is_active = 1
            GROUP BY u.id
        ) as user_hours
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $teamCapacity['total_team'] = $result['total_team'] ?? 0;
    $teamCapacity['overloaded'] = $result['overloaded'] ?? 0;
    $teamCapacity['available'] = $result['available'] ?? 0;
    $teamCapacity['avg_hours'] = round($result['avg_hours'] ?? 0, 1);
    $teamCapacity['utilization'] = $teamCapacity['total_team'] > 0
        ? round(($teamCapacity['avg_hours'] / 40) * 100, 1)
        : 0;

    // Overloaded team members
    $overloadedTeam = $db->query("
        SELECT
            u.full_name,
            COALESCE(SUM(tl.duration_minutes) / 60, 0) as weekly_hours,
            COUNT(DISTINCT t.id) as active_tasks
        FROM users u
        LEFT JOIN time_logs tl ON u.id = tl.user_id
            AND WEEK(tl.start_time) = WEEK(CURRENT_DATE())
        LEFT JOIN tasks t ON u.id = t.assigned_to
            AND t.status IN ('in_progress', 'todo')
        WHERE u.role IN ('manager', 'employee') AND u.is_active = 1
        GROUP BY u.id
        HAVING weekly_hours >= 40
        ORDER BY weekly_hours DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ==================== THIS WEEK'S SHOOTS & SESSIONS ====================
    $weekEvents = $db->query("
        SELECT
            ce.*,
            p.project_name,
            p.client_name
        FROM calendar_events ce
        LEFT JOIN projects p ON ce.project_id = p.id
        WHERE ce.start_datetime >= CURDATE()
        AND ce.start_datetime <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND ce.status != 'cancelled'
        ORDER BY ce.start_datetime ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Count by type
    $eventCounts = [];
    foreach ($weekEvents as $event) {
        $type = $event['event_type'];
        $eventCounts[$type] = ($eventCounts[$type] ?? 0) + 1;
    }

    // ==================== TOP PERFORMERS ====================
    $topPerformers = [];

    // Most tasks completed this month
    $stmt = $db->query("
        SELECT u.full_name, COUNT(*) as completed_tasks
        FROM users u
        JOIN tasks t ON u.id = t.assigned_to
        WHERE t.status = 'completed'
        AND MONTH(t.completed_date) = MONTH(CURRENT_DATE())
        GROUP BY u.id
        ORDER BY completed_tasks DESC
        LIMIT 1
    ");
    $topPerformers['most_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Most hours logged this week
    $stmt = $db->query("
        SELECT u.full_name, SUM(tl.duration_minutes) / 60 as hours
        FROM users u
        JOIN time_logs tl ON u.id = tl.user_id
        WHERE WEEK(tl.start_time) = WEEK(CURRENT_DATE())
        GROUP BY u.id
        ORDER BY hours DESC
        LIMIT 1
    ");
    $topPerformers['most_hours'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Best on-time delivery
    $stmt = $db->query("
        SELECT u.full_name,
            COUNT(*) as total_tasks,
            SUM(CASE WHEN t.completed_date <= t.due_date THEN 1 ELSE 0 END) as on_time,
            ROUND((SUM(CASE WHEN t.completed_date <= t.due_date THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as on_time_rate
        FROM users u
        JOIN tasks t ON u.id = t.assigned_to
        WHERE t.status = 'completed'
        AND t.completed_date IS NOT NULL
        AND t.due_date IS NOT NULL
        AND MONTH(t.completed_date) = MONTH(CURRENT_DATE())
        GROUP BY u.id
        HAVING total_tasks >= 5
        ORDER BY on_time_rate DESC, total_tasks DESC
        LIMIT 1
    ");
    $topPerformers['best_delivery'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // ==================== RISK INDICATORS ====================
    $risks = [];

    // Projects at risk (>90% budget, <50% complete)
    $riskyProjects = $db->query("
        SELECT
            p.project_name,
            p.client_name,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
            pb.total_budget,
            (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0)
             FROM time_logs tl
             JOIN users u ON tl.user_id = u.id
             JOIN tasks t ON tl.task_id = t.id
             WHERE t.project_id = p.id) as spent,
            DATEDIFF(p.due_date, CURDATE()) as days_remaining
        FROM projects p
        LEFT JOIN project_budgets pb ON p.id = pb.project_id
        WHERE p.status IN ('in_progress', 'review')
        HAVING (spent / NULLIF(total_budget, 0) > 0.9 AND completed_tasks / NULLIF(total_tasks, 0) < 0.5)
            OR (days_remaining < 7 AND completed_tasks / NULLIF(total_tasks, 0) < 0.7)
            OR spent > total_budget
        ORDER BY days_remaining ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Projects with long-blocked tasks
    $stmt = $db->query("
        SELECT DISTINCT p.project_name, COUNT(DISTINCT t.id) as blocked_count
        FROM projects p
        JOIN tasks t ON p.id = t.project_id
        WHERE t.status = 'blocked'
        AND t.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
        GROUP BY p.id
        ORDER BY blocked_count DESC
    ");
    $risks['blocked_projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==================== PENDING DELIVERABLES ====================
    $deliverables = [];

    // Awaiting client approval
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM client_feedback
        WHERE client_approval_status = 'pending'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $deliverables['pending_approval'] = $result['count'] ?? 0;

    // By version
    $stmt = $db->query("
        SELECT
            SUM(CASE WHEN revision_count = 0 THEN 1 ELSE 0 END) as v1,
            SUM(CASE WHEN revision_count = 1 THEN 1 ELSE 0 END) as v2,
            SUM(CASE WHEN revision_count >= 2 THEN 1 ELSE 0 END) as v3_plus
        FROM client_feedback
        WHERE client_approval_status = 'pending'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $deliverables['by_version'] = $result;

    // Longest pending
    $stmt = $db->query("
        SELECT p.project_name, cf.feedback_title,
            DATEDIFF(CURDATE(), cf.created_at) as days_pending
        FROM client_feedback cf
        JOIN projects p ON cf.project_id = p.id
        WHERE cf.client_approval_status = 'pending'
        ORDER BY cf.created_at ASC
        LIMIT 1
    ");
    $deliverables['longest_pending'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // ==================== PROJECT HEALTH SCORE ====================
    $healthScores = $db->query("
        SELECT
            p.id,
            p.project_name,
            p.status,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'blocked') as blocked_count,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND due_date < CURDATE() AND status != 'completed') as overdue_count,
            pb.total_budget,
            (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0)
             FROM time_logs tl
             JOIN users u ON tl.user_id = u.id
             JOIN tasks t ON tl.task_id = t.id
             WHERE t.project_id = p.id) as spent,
            DATEDIFF(p.due_date, CURDATE()) as days_remaining,
            CASE
                WHEN (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'blocked') > 0 THEN 'critical'
                WHEN (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND due_date < CURDATE() AND status != 'completed') > 2 THEN 'critical'
                WHEN (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0)
                      FROM time_logs tl
                      JOIN users u ON tl.user_id = u.id
                      JOIN tasks t ON tl.task_id = t.id
                      WHERE t.project_id = p.id) > pb.total_budget THEN 'critical'
                WHEN DATEDIFF(p.due_date, CURDATE()) < 7 THEN 'warning'
                WHEN (SELECT COALESCE(SUM(tl.duration_minutes * u.hourly_rate / 60), 0)
                      FROM time_logs tl
                      JOIN users u ON tl.user_id = u.id
                      JOIN tasks t ON tl.task_id = t.id
                      WHERE t.project_id = p.id) > (pb.total_budget * 0.8) THEN 'warning'
                ELSE 'healthy'
            END as health_status
        FROM projects p
        LEFT JOIN project_budgets pb ON p.id = pb.project_id
        WHERE p.status IN ('planning', 'in_progress', 'review')
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Count by health
    $healthCount = ['healthy' => 0, 'warning' => 0, 'critical' => 0];
    foreach ($healthScores as $project) {
        $healthCount[$project['health_status']]++;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine());
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "<br>File: " . $e->getFile() . "<br>Line: " . $e->getLine());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/vien-v3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .master-grid {
            display: grid;
            gap: 20px;
        }

        .progress-bar-container {
            background: var(--border-light);
            border-radius: 8px;
            height: 8px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--success) 0%, #14d48f 100%);
            transition: width 0.3s ease;
            border-radius: 8px;
        }

        .progress-bar.warning {
            background: linear-gradient(90deg, var(--warning) 0%, #ffce54 100%);
        }

        .progress-bar.danger {
            background: linear-gradient(90deg, var(--danger) 0%, #ff6b9d 100%);
        }

        .project-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 20px;
            border: 1px solid var(--border-light);
            transition: all 0.2s ease;
        }

        .project-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .project-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--heading-color);
            margin-bottom: 5px;
        }

        .project-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .project-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .stat-item {
            text-align: center;
            padding: 8px;
            background: var(--light);
            border-radius: 6px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--heading-color);
        }

        .stat-label {
            font-size: 10px;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .team-member-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-light);
            margin-bottom: 10px;
        }

        .member-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: var(--heading-color);
            margin-bottom: 3px;
        }

        .member-current-task {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .member-stats {
            display: flex;
            gap: 15px;
            font-size: 13px;
        }

        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: rgba(23,176,107,0.1);
            color: var(--success);
        }

        .activity-icon.primary {
            background: rgba(102,126,234,0.1);
            color: var(--primary);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--heading-color);
            font-size: 14px;
        }

        .activity-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .bottleneck-item {
            padding: 12px;
            background: rgba(236,69,97,0.05);
            border-left: 3px solid var(--danger);
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .bottleneck-item.blocked {
            background: rgba(248,183,57,0.05);
            border-left-color: var(--warning);
        }

        .deadline-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--border-light);
        }

        .days-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .days-badge.urgent {
            background: rgba(236,69,97,0.1);
            color: var(--danger);
        }

        .days-badge.warning {
            background: rgba(248,183,57,0.1);
            color: var(--warning);
        }

        .days-badge.ok {
            background: rgba(23,176,107,0.1);
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/v3-admin-sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/v3-header.php'; ?>

            <div class="content-wrapper">
                <!-- Page Header -->
                <div style="margin-bottom: 30px;">
                    <h1 style="margin-bottom: 8px;"><i class="fas fa-chart-line"></i> Master Dashboard</h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                        Complete overview of all office activities, projects, and team performance
                    </p>
                </div>

                <!-- Key Metrics -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="dashboard-card">
                            <div class="card-icon primary">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="card-value"><?php echo $metrics['active_projects']; ?></div>
                            <div class="card-label">Active Projects</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="dashboard-card">
                            <div class="card-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-value"><?php echo $metrics['team_members']; ?></div>
                            <div class="card-label">Team Members</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="dashboard-card">
                            <div class="card-icon warning">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="card-value"><?php echo $metrics['tasks_in_progress']; ?></div>
                            <div class="card-label">Tasks In Progress</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="dashboard-card">
                            <div class="card-icon danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="card-value"><?php echo $metrics['bottlenecks']; ?></div>
                            <div class="card-label">Bottlenecks</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-value"><?php echo $metrics['hours_week']; ?>h</div>
                            <div class="card-label">Hours This Week</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="dashboard-card">
                            <div class="card-icon success">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="card-value"><?php echo $metrics['completion_rate']; ?>%</div>
                            <div class="card-label">Completion Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Financial Overview -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-dollar-sign"></i> Financial Overview</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon success">
                                                <i class="fas fa-hand-holding-usd"></i>
                                            </div>
                                            <div class="card-value">$<?php echo number_format($financial['total_revenue'], 0); ?></div>
                                            <div class="card-label">Total Revenue</div>
                                            <div class="card-trend">Active Projects</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon danger">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <div class="card-value">$<?php echo number_format($financial['total_spent'], 0); ?></div>
                                            <div class="card-label">Total Spent</div>
                                            <div class="card-trend">Labor + Expenses</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon <?php echo $financial['profit_margin'] > 20 ? 'success' : ($financial['profit_margin'] > 10 ? 'warning' : 'danger'); ?>">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <div class="card-value"><?php echo $financial['profit_margin']; ?>%</div>
                                            <div class="card-label">Profit Margin</div>
                                            <div class="card-trend">Overall</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon <?php echo $financial['over_budget_count'] > 0 ? 'danger' : 'success'; ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="card-value"><?php echo $financial['over_budget_count']; ?></div>
                                            <div class="card-label">Over Budget</div>
                                            <div class="card-trend">Projects</div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($financial['top_project']): ?>
                                <div style="margin-top: 20px; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                                    <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 5px;">
                                        <i class="fas fa-trophy"></i> Most Profitable This Month
                                    </div>
                                    <div style="font-size: 16px; font-weight: 600; color: var(--success);">
                                        <?php echo e($financial['top_project']['project_name']); ?> -
                                        $<?php echo number_format($financial['top_project']['profit'], 0); ?> profit
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Happiness -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-smile"></i> Client Happiness Metrics</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon <?php echo $clientMetrics['approval_rate'] >= 80 ? 'success' : 'warning'; ?>">
                                                <i class="fas fa-thumbs-up"></i>
                                            </div>
                                            <div class="card-value"><?php echo $clientMetrics['approval_rate']; ?>%</div>
                                            <div class="card-label">Approval Rate</div>
                                            <div class="card-trend">Last 30 Days</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon info">
                                                <i class="fas fa-redo"></i>
                                            </div>
                                            <div class="card-value"><?php echo $clientMetrics['avg_revisions']; ?></div>
                                            <div class="card-label">Avg Revisions</div>
                                            <div class="card-trend">Per Project</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon warning">
                                                <i class="fas fa-hourglass-half"></i>
                                            </div>
                                            <div class="card-value"><?php echo $clientMetrics['pending_feedback']; ?></div>
                                            <div class="card-label">Pending Feedback</div>
                                            <div class="card-trend">Awaiting Response</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon success">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div class="card-value"><?php echo $clientMetrics['perfect_projects']; ?></div>
                                            <div class="card-label">Perfect Deliveries</div>
                                            <div class="card-trend">0 Revisions</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Types & Team Capacity -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-pie"></i> Project Type Breakdown</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($projectTypes)): ?>
                                    <?php
                                    $icons = [
                                        'Video Editing' => ['icon' => 'fa-film', 'color' => '#FF6B6B'],
                                        'Photography' => ['icon' => 'fa-camera', 'color' => '#4ECDC4'],
                                        'Videography' => ['icon' => 'fa-video', 'color' => '#FFE66D'],
                                        'Creative Strategy' => ['icon' => 'fa-lightbulb', 'color' => '#95E1D3'],
                                        'Other' => ['icon' => 'fa-folder', 'color' => '#CCCCCC']
                                    ];
                                    ?>
                                    <?php foreach ($projectTypes as $type): ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: 8px; margin-bottom: 10px;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 40px; height: 40px; border-radius: 8px; background: <?php echo $icons[$type['project_type']]['color'] ?? '#CCCCCC'; ?>20; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas <?php echo $icons[$type['project_type']]['icon'] ?? 'fa-folder'; ?>" style="color: <?php echo $icons[$type['project_type']]['color'] ?? '#CCCCCC'; ?>; font-size: 18px;"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--heading-color);"><?php echo $type['project_type']; ?></div>
                                                    <div style="font-size: 12px; color: var(--text-secondary);">Active Projects</div>
                                                </div>
                                            </div>
                                            <div style="font-size: 24px; font-weight: 700; color: <?php echo $icons[$type['project_type']]['color'] ?? '#CCCCCC'; ?>;">
                                                <?php echo $type['count']; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: var(--text-secondary); padding: 20px;">No active projects</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Team Capacity -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-users-cog"></i> Team Capacity</h3>
                            </div>
                            <div class="card-body">
                                <div style="margin-bottom: 20px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="font-weight: 600;">Team Utilization</span>
                                        <span style="font-weight: 700; color: var(--primary);"><?php echo $teamCapacity['utilization']; ?>%</span>
                                    </div>
                                    <div class="progress-bar-container" style="height: 12px;">
                                        <div class="progress-bar <?php echo $teamCapacity['utilization'] > 90 ? 'danger' : ($teamCapacity['utilization'] > 75 ? 'warning' : ''); ?>"
                                             style="width: <?php echo min($teamCapacity['utilization'], 100); ?>%;"></div>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                        Average <?php echo $teamCapacity['avg_hours']; ?>h per person this week (40h = 100%)
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-4">
                                        <div style="text-align: center; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="font-size: 24px; font-weight: 700; color: var(--success);"><?php echo $teamCapacity['available']; ?></div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">Available</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div style="text-align: center; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="font-size: 24px; font-weight: 700; color: var(--primary);"><?php echo $teamCapacity['total_team']; ?></div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">Total Team</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div style="text-align: center; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="font-size: 24px; font-weight: 700; color: var(--danger);"><?php echo $teamCapacity['overloaded']; ?></div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">Overloaded</div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($overloadedTeam)): ?>
                                    <div style="margin-top: 15px; padding: 12px; background: var(--danger)10; border-left: 3px solid var(--danger); border-radius: 4px;">
                                        <div style="font-weight: 600; color: var(--danger); margin-bottom: 8px;">
                                            <i class="fas fa-exclamation-triangle"></i> Overloaded Team Members
                                        </div>
                                        <?php foreach ($overloadedTeam as $member): ?>
                                            <div style="font-size: 13px; margin-bottom: 4px;">
                                                • <?php echo e($member['full_name']); ?>: <strong><?php echo round($member['weekly_hours'], 1); ?>h</strong> this week
                                                (<?php echo $member['active_tasks']; ?> active tasks)
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- This Week's Events -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-week"></i> This Week's Shoots & Sessions</h3>
                            </div>
                            <div class="card-body">
                                <!-- Event Count Summary -->
                                <div class="row" style="margin-bottom: 20px;">
                                    <?php
                                    $eventTypeIcons = [
                                        'shoot' => ['icon' => 'fa-video', 'label' => 'Shoots', 'color' => '#FF6B6B'],
                                        'edit' => ['icon' => 'fa-cut', 'label' => 'Edit Sessions', 'color' => '#4ECDC4'],
                                        'review' => ['icon' => 'fa-eye', 'label' => 'Reviews', 'color' => '#FFE66D'],
                                        'meeting' => ['icon' => 'fa-users', 'label' => 'Meetings', 'color' => '#95E1D3'],
                                        'deadline' => ['icon' => 'fa-flag', 'label' => 'Deadlines', 'color' => '#F38181'],
                                        'delivery' => ['icon' => 'fa-truck', 'label' => 'Deliveries', 'color' => '#AA96DA']
                                    ];

                                    foreach ($eventTypeIcons as $type => $data):
                                        $count = $eventCounts[$type] ?? 0;
                                        if ($count > 0):
                                    ?>
                                        <div class="col-lg-2 col-md-4 col-6">
                                            <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                                                <i class="fas <?php echo $data['icon']; ?>" style="font-size: 24px; color: <?php echo $data['color']; ?>; margin-bottom: 8px;"></i>
                                                <div style="font-size: 20px; font-weight: 700;"><?php echo $count; ?></div>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?php echo $data['label']; ?></div>
                                            </div>
                                        </div>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>

                                <!-- Event List -->
                                <?php if (!empty($weekEvents)): ?>
                                    <div style="display: grid; gap: 10px;">
                                        <?php foreach ($weekEvents as $event): ?>
                                            <?php
                                            $eventColor = $eventTypeIcons[$event['event_type']]['color'] ?? '#CCCCCC';
                                            $eventIcon = $eventTypeIcons[$event['event_type']]['icon'] ?? 'fa-calendar';
                                            ?>
                                            <div style="display: flex; gap: 15px; padding: 12px; background: var(--bg-secondary); border-left: 4px solid <?php echo $eventColor; ?>; border-radius: 4px;">
                                                <div style="flex-shrink: 0;">
                                                    <div style="width: 50px; text-align: center;">
                                                        <div style="font-size: 20px; font-weight: 700; color: var(--heading-color);">
                                                            <?php echo date('d', strtotime($event['start_datetime'])); ?>
                                                        </div>
                                                        <div style="font-size: 11px; color: var(--text-secondary);">
                                                            <?php echo date('M', strtotime($event['start_datetime'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style="flex: 1;">
                                                    <div style="font-weight: 600; color: var(--heading-color); margin-bottom: 4px;">
                                                        <i class="fas <?php echo $eventIcon; ?>" style="color: <?php echo $eventColor; ?>;"></i>
                                                        <?php echo e($event['event_title']); ?>
                                                    </div>
                                                    <div style="font-size: 13px; color: var(--text-secondary);">
                                                        <?php if ($event['project_name']): ?>
                                                            📁 <?php echo e($event['project_name']); ?> •
                                                        <?php endif; ?>
                                                        🕐 <?php echo date('g:i A', strtotime($event['start_datetime'])); ?>
                                                        <?php if ($event['location']): ?>
                                                            • 📍 <?php echo e($event['location']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($event['equipment_needed']): ?>
                                                        <div style="font-size: 12px; color: var(--warning); margin-top: 4px;">
                                                            🎬 Equipment: <?php echo e($event['equipment_needed']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                        No events scheduled for this week
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-trophy"></i> Top Performers This Month</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <?php if ($topPerformers['most_tasks']): ?>
                                            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); border-radius: 12px; color: white;">
                                                <i class="fas fa-tasks" style="font-size: 32px; margin-bottom: 10px;"></i>
                                                <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">Most Tasks Completed</div>
                                                <div style="font-size: 24px; font-weight: 700;"><?php echo e($topPerformers['most_tasks']['full_name']); ?></div>
                                                <div style="font-size: 32px; font-weight: 900; margin-top: 10px;"><?php echo $topPerformers['most_tasks']['completed_tasks']; ?></div>
                                                <div style="font-size: 12px; opacity: 0.8;">tasks completed</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-lg-4">
                                        <?php if ($topPerformers['most_hours']): ?>
                                            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); border-radius: 12px; color: white;">
                                                <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 10px;"></i>
                                                <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">Most Hours Logged</div>
                                                <div style="font-size: 24px; font-weight: 700;"><?php echo e($topPerformers['most_hours']['full_name']); ?></div>
                                                <div style="font-size: 32px; font-weight: 900; margin-top: 10px;"><?php echo round($topPerformers['most_hours']['hours'], 1); ?>h</div>
                                                <div style="font-size: 12px; opacity: 0.8;">this week</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-lg-4">
                                        <?php if ($topPerformers['best_delivery']): ?>
                                            <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); border-radius: 12px; color: white;">
                                                <i class="fas fa-rocket" style="font-size: 32px; margin-bottom: 10px;"></i>
                                                <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">Best On-Time Delivery</div>
                                                <div style="font-size: 24px; font-weight: 700;"><?php echo e($topPerformers['best_delivery']['full_name']); ?></div>
                                                <div style="font-size: 32px; font-weight: 900; margin-top: 10px;"><?php echo $topPerformers['best_delivery']['on_time_rate']; ?>%</div>
                                                <div style="font-size: 12px; opacity: 0.8;"><?php echo $topPerformers['best_delivery']['on_time']; ?>/<?php echo $topPerformers['best_delivery']['total_tasks']; ?> on time</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Risk Indicators & Project Health -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-exclamation-triangle"></i> Risk Indicators</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($riskyProjects)): ?>
                                    <div style="margin-bottom: 20px;">
                                        <div style="font-weight: 600; margin-bottom: 10px; color: var(--danger);">
                                            <i class="fas fa-fire"></i> Projects At Risk
                                        </div>
                                        <?php foreach ($riskyProjects as $risk): ?>
                                            <?php
                                            $budgetPercent = $risk['total_budget'] > 0 ? round(($risk['spent'] / $risk['total_budget']) * 100) : 0;
                                            $taskPercent = $risk['total_tasks'] > 0 ? round(($risk['completed_tasks'] / $risk['total_tasks']) * 100) : 0;
                                            ?>
                                            <div style="padding: 12px; background: var(--danger)10; border-left: 3px solid var(--danger); border-radius: 4px; margin-bottom: 10px;">
                                                <div style="font-weight: 600; margin-bottom: 5px;"><?php echo e($risk['project_name']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">
                                                    <?php if ($risk['client_name']): ?>
                                                        Client: <?php echo e($risk['client_name']); ?> •
                                                    <?php endif; ?>
                                                    <?php echo abs($risk['days_remaining']); ?> days <?php echo $risk['days_remaining'] < 0 ? 'overdue' : 'remaining'; ?>
                                                </div>
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                                                    <div>
                                                        <div style="color: var(--text-secondary);">Budget Used</div>
                                                        <div style="font-weight: 700; color: <?php echo $budgetPercent > 100 ? 'var(--danger)' : 'var(--warning)'; ?>;">
                                                            <?php echo $budgetPercent; ?>%
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div style="color: var(--text-secondary);">Tasks Complete</div>
                                                        <div style="font-weight: 700; color: <?php echo $taskPercent < 50 ? 'var(--danger)' : 'var(--warning)'; ?>;">
                                                            <?php echo $taskPercent; ?>%
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($risks['blocked_projects'])): ?>
                                    <div>
                                        <div style="font-weight: 600; margin-bottom: 10px; color: var(--warning);">
                                            <i class="fas fa-hand-paper"></i> Long-Blocked Tasks
                                        </div>
                                        <?php foreach ($risks['blocked_projects'] as $blocked): ?>
                                            <div style="padding: 10px; background: var(--warning)10; border-left: 3px solid var(--warning); border-radius: 4px; margin-bottom: 8px;">
                                                <div style="font-weight: 600;"><?php echo e($blocked['project_name']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);">
                                                    <?php echo $blocked['blocked_count']; ?> task<?php echo $blocked['blocked_count'] > 1 ? 's' : ''; ?> blocked >3 days
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($riskyProjects) && empty($risks['blocked_projects'])): ?>
                                    <div style="text-align: center; padding: 40px; color: var(--success);">
                                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                                        <div style="font-size: 18px; font-weight: 600;">All Projects Looking Good!</div>
                                        <div style="font-size: 14px; margin-top: 5px;">No critical risks detected</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Project Health Score -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-heartbeat"></i> Project Health Overview</h3>
                            </div>
                            <div class="card-body">
                                <div class="row" style="margin-bottom: 20px;">
                                    <div class="col-4">
                                        <div style="text-align: center; padding: 20px; background: var(--success)20; border-radius: 8px;">
                                            <div style="font-size: 36px; font-weight: 900; color: var(--success);"><?php echo $healthCount['healthy']; ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                                <i class="fas fa-check-circle" style="color: var(--success);"></i> Healthy
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div style="text-align: center; padding: 20px; background: var(--warning)20; border-radius: 8px;">
                                            <div style="font-size: 36px; font-weight: 900; color: var(--warning);"><?php echo $healthCount['warning']; ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Warning
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div style="text-align: center; padding: 20px; background: var(--danger)20; border-radius: 8px;">
                                            <div style="font-size: 36px; font-weight: 900; color: var(--danger);"><?php echo $healthCount['critical']; ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                                                <i class="fas fa-times-circle" style="color: var(--danger);"></i> Critical
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Health Details -->
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($healthScores as $project): ?>
                                        <?php
                                        $healthColors = [
                                            'healthy' => 'var(--success)',
                                            'warning' => 'var(--warning)',
                                            'critical' => 'var(--danger)'
                                        ];
                                        $healthIcons = [
                                            'healthy' => 'fa-check-circle',
                                            'warning' => 'fa-exclamation-triangle',
                                            'critical' => 'fa-times-circle'
                                        ];
                                        ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border-bottom: 1px solid var(--border-light);">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; font-size: 14px;"><?php echo e($project['project_name']); ?></div>
                                                <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">
                                                    <?php if ($project['blocked_count'] > 0): ?>
                                                        🚫 <?php echo $project['blocked_count']; ?> blocked •
                                                    <?php endif; ?>
                                                    <?php if ($project['overdue_count'] > 0): ?>
                                                        ⏰ <?php echo $project['overdue_count']; ?> overdue •
                                                    <?php endif; ?>
                                                    <?php if ($project['days_remaining'] !== null): ?>
                                                        📅 <?php echo abs($project['days_remaining']); ?> days <?php echo $project['days_remaining'] < 0 ? 'late' : 'left'; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <i class="fas <?php echo $healthIcons[$project['health_status']]; ?>"
                                                   style="font-size: 20px; color: <?php echo $healthColors[$project['health_status']]; ?>;"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Deliverables -->
                <div class="row" style="margin-bottom: 30px;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-file-video"></i> Pending Deliverables</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon warning">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="card-value"><?php echo $deliverables['pending_approval']; ?></div>
                                            <div class="card-label">Awaiting Approval</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon success">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <div class="card-value"><?php echo $deliverables['by_version']['v1'] ?? 0; ?></div>
                                            <div class="card-label">Version 1</div>
                                            <div class="card-trend">First Submission</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon info">
                                                <i class="fas fa-redo"></i>
                                            </div>
                                            <div class="card-value"><?php echo $deliverables['by_version']['v2'] ?? 0; ?></div>
                                            <div class="card-label">Version 2</div>
                                            <div class="card-trend">1 Revision</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <div class="dashboard-card">
                                            <div class="card-icon danger">
                                                <i class="fas fa-sync-alt"></i>
                                            </div>
                                            <div class="card-value"><?php echo $deliverables['by_version']['v3_plus'] ?? 0; ?></div>
                                            <div class="card-label">Version 3+</div>
                                            <div class="card-trend">Multiple Revisions</div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($deliverables['longest_pending']): ?>
                                    <div style="margin-top: 20px; padding: 15px; background: var(--warning)10; border-left: 3px solid var(--warning); border-radius: 4px;">
                                        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 5px;">
                                            <i class="fas fa-hourglass-half"></i> Longest Pending Approval
                                        </div>
                                        <div style="font-weight: 600; font-size: 15px; color: var(--heading-color);">
                                            <?php echo e($deliverables['longest_pending']['project_name']); ?> -
                                            <?php echo e($deliverables['longest_pending']['feedback_title']); ?>
                                        </div>
                                        <div style="font-size: 13px; color: var(--warning); margin-top: 5px;">
                                            ⏱️ <?php echo $deliverables['longest_pending']['days_pending']; ?> days pending
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Grid Layout -->
                <div class="row">
                    <!-- Left Column: Projects & Team -->
                    <div class="col-lg-8">
                        <!-- All Projects Overview -->
                        <div class="card" style="margin-bottom: 30px;">
                            <div class="card-header">
                                <h3><i class="fas fa-folder-open"></i> All Projects Overview</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($projects)): ?>
                                    <p style="text-align: center; color: var(--text-secondary); padding: 40px 0;">
                                        No projects found
                                    </p>
                                <?php else: ?>
                                    <div style="display: grid; gap: 15px;">
                                        <?php foreach ($projects as $project):
                                            $progress = $project['total_tasks'] > 0
                                                ? round(($project['completed_tasks'] / $project['total_tasks']) * 100)
                                                : 0;
                                            $hours = round(($project['total_minutes'] ?? 0) / 60, 1);

                                            $progressClass = '';
                                            if ($progress >= 80) $progressClass = '';
                                            elseif ($progress >= 50) $progressClass = 'warning';
                                            else $progressClass = 'danger';
                                        ?>
                                        <div class="project-card">
                                            <div class="project-header">
                                                <div style="flex: 1;">
                                                    <div class="project-title">
                                                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" style="color: inherit;">
                                                            <?php echo e($project['project_name']); ?>
                                                        </a>
                                                    </div>
                                                    <div class="project-meta">
                                                        <?php if ($project['client_name']): ?>
                                                            <span><i class="fas fa-building"></i> <?php echo e($project['client_name']); ?></span>
                                                        <?php endif; ?>
                                                        <span><i class="fas fa-user"></i> <?php echo e($project['manager_name'] ?? 'Unassigned'); ?></span>
                                                        <?php if ($project['due_date']): ?>
                                                            <span>
                                                                <i class="fas fa-calendar"></i>
                                                                <?php echo date('M d, Y', strtotime($project['due_date'])); ?>
                                                                <?php if ($project['days_remaining'] !== null): ?>
                                                                    <?php if ($project['days_remaining'] < 0): ?>
                                                                        <span style="color: var(--danger);">(<?php echo abs($project['days_remaining']); ?> days overdue)</span>
                                                                    <?php elseif ($project['days_remaining'] <= 7): ?>
                                                                        <span style="color: var(--warning);">(<?php echo $project['days_remaining']; ?> days left)</span>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge <?php echo getStatusClass($project['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                    </span>
                                                    <?php if ($project['priority']): ?>
                                                        <span class="badge <?php echo getPriorityClass($project['priority']); ?>" style="margin-left: 5px;">
                                                            <?php echo ucfirst($project['priority']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div style="margin-bottom: 8px;">
                                                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                                    <span style="font-weight: 600;"><?php echo $progress; ?>% Complete</span>
                                                    <span style="color: var(--text-secondary);">
                                                        <?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> tasks
                                                    </span>
                                                </div>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo $progress; ?>%;"></div>
                                                </div>
                                            </div>

                                            <div class="project-stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $project['active_tasks']; ?></div>
                                                    <div class="stat-label">Active</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value" style="color: var(--success);"><?php echo $project['completed_tasks']; ?></div>
                                                    <div class="stat-label">Done</div>
                                                </div>
                                                <?php if ($project['blocked_tasks'] > 0): ?>
                                                    <div class="stat-item" style="background: rgba(236,69,97,0.1);">
                                                        <div class="stat-value" style="color: var(--danger);"><?php echo $project['blocked_tasks']; ?></div>
                                                        <div class="stat-label">Blocked</div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($project['overdue_tasks'] > 0): ?>
                                                    <div class="stat-item" style="background: rgba(248,183,57,0.1);">
                                                        <div class="stat-value" style="color: var(--warning);"><?php echo $project['overdue_tasks']; ?></div>
                                                        <div class="stat-label">Overdue</div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo $hours; ?>h</div>
                                                    <div class="stat-label">Logged</div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Activity & Assignments -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-users-cog"></i> Team Activity & Workload</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($teamActivity as $member):
                                    $initials = '';
                                    $nameParts = explode(' ', $member['full_name']);
                                    foreach ($nameParts as $part) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                    $hoursThisWeek = round(($member['minutes_this_week'] ?? 0) / 60, 1);
                                ?>
                                <div class="team-member-row">
                                    <div class="member-avatar">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div class="member-info">
                                        <div class="member-name"><?php echo e($member['full_name']); ?></div>
                                        <?php if ($member['current_task']): ?>
                                            <div class="member-current-task">
                                                <i class="fas fa-spinner"></i>
                                                Working on: <strong><?php echo e($member['current_task']); ?></strong>
                                                <?php if ($member['current_project']): ?>
                                                    in <?php echo e($member['current_project']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="member-current-task" style="color: var(--text-muted);">
                                                <i class="fas fa-check"></i> No active tasks
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="member-stats">
                                        <div style="text-align: center;">
                                            <div style="font-size: 18px; font-weight: 700; color: var(--warning);">
                                                <?php echo $member['active_tasks']; ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">Active</div>
                                        </div>
                                        <div style="text-align: center;">
                                            <div style="font-size: 18px; font-weight: 700; color: var(--success);">
                                                <?php echo $member['completed_this_month']; ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">Done/Mo</div>
                                        </div>
                                        <div style="text-align: center;">
                                            <div style="font-size: 18px; font-weight: 700; color: var(--primary);">
                                                <?php echo $hoursThisWeek; ?>h
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">This Week</div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Bottlenecks, Deadlines, Activity -->
                    <div class="col-lg-4">
                        <!-- Bottlenecks & Critical Issues -->
                        <div class="card" style="margin-bottom: 30px;">
                            <div class="card-header">
                                <h3><i class="fas fa-exclamation-circle"></i> Bottlenecks & Issues</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($bottlenecks)): ?>
                                    <div style="text-align: center; padding: 40px 20px; color: var(--success);">
                                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 10px;"></i>
                                        <p style="font-weight: 600;">All Clear!</p>
                                        <p style="font-size: 13px; color: var(--text-secondary);">No blocked or overdue tasks</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($bottlenecks as $task): ?>
                                        <div class="bottleneck-item <?php echo $task['status'] === 'blocked' ? 'blocked' : ''; ?>">
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                                                <div style="font-weight: 600; font-size: 14px;">
                                                    <?php echo e($task['task_name']); ?>
                                                </div>
                                                <span class="badge <?php echo $task['status'] === 'blocked' ? 'badge-warning' : 'badge-danger'; ?>">
                                                    <?php echo $task['status'] === 'blocked' ? 'Blocked' : 'Overdue'; ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                                                <i class="fas fa-folder"></i> <?php echo e($task['project_name']); ?>
                                                <?php if ($task['assigned_name']): ?>
                                                    <span style="margin-left: 10px;">
                                                        <i class="fas fa-user"></i> <?php echo e($task['assigned_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($task['days_overdue'] > 0): ?>
                                                <div style="font-size: 12px; color: var(--danger); font-weight: 600;">
                                                    <i class="fas fa-clock"></i> <?php echo $task['days_overdue']; ?> days overdue
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upcoming Deadlines -->
                        <div class="card" style="margin-bottom: 30px;">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-alt"></i> Upcoming Deadlines</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingDeadlines)): ?>
                                    <p style="text-align: center; color: var(--text-secondary); padding: 20px 0;">
                                        No upcoming deadlines
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($upcomingDeadlines as $task):
                                        $badgeClass = 'ok';
                                        if ($task['days_until_due'] <= 2) $badgeClass = 'urgent';
                                        elseif ($task['days_until_due'] <= 7) $badgeClass = 'warning';
                                    ?>
                                    <div class="deadline-item">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; font-size: 13px; margin-bottom: 3px;">
                                                <?php echo e($task['task_name']); ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                <?php echo e($task['project_name']); ?>
                                                <?php if ($task['assigned_name']): ?>
                                                    • <?php echo e($task['assigned_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="days-badge <?php echo $badgeClass; ?>">
                                            <?php echo $task['days_until_due']; ?> days
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Activity Feed -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-bell"></i> Recent Activity</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo $activity['activity_type'] === 'task_completed' ? 'success' : 'primary'; ?>">
                                            <i class="fas fa-<?php echo $activity['activity_type'] === 'task_completed' ? 'check' : 'plus'; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?php echo e($activity['title']); ?></div>
                                            <div class="activity-subtitle">
                                                <?php echo e($activity['subtitle'] ?? ''); ?>
                                                <?php if ($activity['user_name']): ?>
                                                    by <?php echo e($activity['user_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="activity-time">
                                            <?php echo timeAgo($activity['activity_time']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>
</html>
