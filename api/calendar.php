<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

try {
    // GET: Retrieve calendar events
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $type = $_GET['type'] ?? 'all'; // all, tasks, milestones

        $events = [];

        // Get tasks
        if ($type === 'all' || $type === 'tasks') {
            $taskSql = "
                SELECT
                    t.id,
                    t.task_name as title,
                    t.due_date as date,
                    t.status,
                    t.priority,
                    p.project_name,
                    u.full_name as assigned_to_name
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.due_date IS NOT NULL
            ";

            if ($start && $end) {
                $taskSql .= " AND t.due_date BETWEEN ? AND ?";
            }

            $stmt = $db->prepare($taskSql);
            if ($start && $end) {
                $stmt->execute([$start, $end]);
            } else {
                $stmt->execute();
            }

            $tasks = $stmt->fetchAll();

            foreach ($tasks as $task) {
                $color = '#3b82f6'; // default blue
                if ($task['status'] === 'completed') $color = '#10b981'; // green
                elseif ($task['priority'] === 'urgent') $color = '#ef4444'; // red
                elseif ($task['priority'] === 'high') $color = '#f59e0b'; // orange

                $events[] = [
                    'id' => 'task_' . $task['id'],
                    'title' => $task['title'],
                    'start' => $task['date'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'type' => 'task',
                        'taskId' => $task['id'],
                        'status' => $task['status'],
                        'priority' => $task['priority'],
                        'project' => $task['project_name'],
                        'assignedTo' => $task['assigned_to_name']
                    ]
                ];
            }
        }

        // Get milestones
        if ($type === 'all' || $type === 'milestones') {
            $milestoneSql = "
                SELECT
                    m.id,
                    m.milestone_name as title,
                    m.due_date as date,
                    m.is_completed,
                    p.project_name
                FROM project_milestones m
                JOIN projects p ON m.project_id = p.id
                WHERE 1=1
            ";

            if ($start && $end) {
                $milestoneSql .= " AND m.due_date BETWEEN ? AND ?";
            }

            $stmt = $db->prepare($milestoneSql);
            if ($start && $end) {
                $stmt->execute([$start, $end]);
            } else {
                $stmt->execute();
            }

            $milestones = $stmt->fetchAll();

            foreach ($milestones as $milestone) {
                $color = $milestone['is_completed'] ? '#10b981' : '#8b5cf6'; // green if completed, purple otherwise

                $events[] = [
                    'id' => 'milestone_' . $milestone['id'],
                    'title' => '🎯 ' . $milestone['title'],
                    'start' => $milestone['date'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'allDay' => true,
                    'extendedProps' => [
                        'type' => 'milestone',
                        'milestoneId' => $milestone['id'],
                        'completed' => $milestone['is_completed'],
                        'project' => $milestone['project_name']
                    ]
                ];
            }
        }

        // Get employee work sessions (time logs) - FOR ADMIN VIEW
        if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager') {
            $workSessionSql = "
                SELECT
                    tl.id,
                    tl.start_time,
                    tl.end_time,
                    tl.duration_minutes,
                    tl.is_active,
                    t.task_name,
                    p.project_name,
                    u.full_name as employee_name,
                    u.id as employee_id
                FROM time_logs tl
                JOIN tasks t ON tl.task_id = t.id
                JOIN projects p ON tl.project_id = p.id
                JOIN users u ON tl.user_id = u.id
                WHERE 1=1
            ";

            if ($start && $end) {
                $workSessionSql .= " AND DATE(tl.start_time) BETWEEN ? AND ?";
            }

            $workSessionSql .= " ORDER BY tl.start_time DESC";

            $stmt = $db->prepare($workSessionSql);
            if ($start && $end) {
                $stmt->execute([$start, $end]);
            } else {
                $stmt->execute();
            }

            $workSessions = $stmt->fetchAll();

            foreach ($workSessions as $session) {
                $color = '#06b6d4'; // cyan for work sessions
                if ($session['is_active']) {
                    $color = '#f97316'; // orange for active sessions
                }

                // Create event with time range
                $eventData = [
                    'id' => 'work_' . $session['id'],
                    'title' => '👤 ' . $session['employee_name'] . ': ' . $session['task_name'],
                    'start' => $session['start_time'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'type' => 'work_session',
                        'sessionId' => $session['id'],
                        'employee' => $session['employee_name'],
                        'employeeId' => $session['employee_id'],
                        'task' => $session['task_name'],
                        'project' => $session['project_name'],
                        'duration' => $session['duration_minutes'],
                        'isActive' => $session['is_active']
                    ]
                ];

                // Add end time if session is completed
                if ($session['end_time']) {
                    $eventData['end'] = $session['end_time'];
                } else {
                    // Active session - show as ongoing
                    $eventData['title'] = '🔴 ' . $session['employee_name'] . ': ' . $session['task_name'] . ' (Working Now)';
                }

                $events[] = $eventData;
            }
        }

        echo json_encode($events);
    }

    // POST: Update task due date
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $taskId = $data['task_id'] ?? 0;
        $newDate = $data['new_date'] ?? null;

        if (!$newDate) {
            throw new Exception('New date is required');
        }

        $stmt = $db->prepare("UPDATE tasks SET due_date = ? WHERE id = ?");
        $stmt->execute([$newDate, $taskId]);

        echo json_encode(['success' => true, 'message' => 'Task date updated']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
