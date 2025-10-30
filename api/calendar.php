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
        $userId = $_GET['user_id'] ?? null; // Filter by user ID

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
                    t.assigned_to,
                    p.project_name,
                    u.full_name as assigned_to_name
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.due_date IS NOT NULL
            ";

            $params = [];

            if ($start && $end) {
                $taskSql .= " AND t.due_date BETWEEN ? AND ?";
                $params[] = $start;
                $params[] = $end;
            }

            // Filter by user if specified
            if ($userId) {
                $taskSql .= " AND t.assigned_to = ?";
                $params[] = $userId;
            }

            $stmt = $db->prepare($taskSql);
            $stmt->execute($params);

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
