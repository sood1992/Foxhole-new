<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/gamification-functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();

try {
    // POST: Update task status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'update_status') {
            $taskId = $input['task_id'] ?? null;
            $newStatus = $input['status'] ?? null;

            if (!$taskId || !$newStatus) {
                throw new Exception('Task ID and status are required');
            }

            // Get task details
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new Exception('Task not found');
            }

            // Verify user has permission (own task or admin/manager)
            if ($task['assigned_to'] != $currentUser['id'] && !hasRole(['admin', 'manager'])) {
                throw new Exception('Insufficient permissions');
            }

            $oldStatus = $task['status'];

            // Update task status
            $stmt = $db->prepare("
                UPDATE tasks
                SET status = ?,
                    completed_date = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_date END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $newStatus, $taskId]);

            // Award points if task is completed
            $pointsAwarded = 0;
            $newBadges = [];

            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $pointsAwarded = awardTaskCompletionPoints($db, $task['assigned_to'], $taskId, $task);
                $newBadges = checkAndAwardBadges($db, $task['assigned_to']);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Task status updated',
                'points_awarded' => $pointsAwarded,
                'new_badges' => $newBadges
            ]);
            exit;
        }
    }

    // GET: Get tasks list
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $exclude = isset($_GET['exclude']) ? intval($_GET['exclude']) : 0;

        $sql = "
            SELECT
                t.id,
                t.task_name,
                t.status,
                t.priority,
                p.project_name
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE 1=1
        ";

        if ($exclude) {
            $sql .= " AND t.id != ?";
        }

        $sql .= " ORDER BY p.project_name, t.task_name LIMIT 200";

        $stmt = $db->prepare($sql);
        if ($exclude) {
            $stmt->execute([$exclude]);
        } else {
            $stmt->execute();
        }

        $tasks = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'tasks' => $tasks
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
