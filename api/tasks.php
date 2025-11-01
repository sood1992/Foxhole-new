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

    // PUT/PATCH: Quick update task fields (assigned_to, priority)
    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true);

        $taskId = intval($input['task_id'] ?? 0);
        $field = $input['field'] ?? '';
        $value = $input['value'] ?? '';

        if (!$taskId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Task ID is required']);
            exit;
        }

        // Check if user is manager and owns the project
        $checkStmt = $db->prepare("
            SELECT p.assigned_manager
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
        ");
        $checkStmt->execute([$taskId]);
        $task = $checkStmt->fetch();

        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            exit;
        }

        if ($task['assigned_manager'] != $currentUser['id'] && !hasRole('admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task']);
            exit;
        }

        // Validate and update allowed fields
        $allowedFields = ['assigned_to', 'priority'];

        if (!in_array($field, $allowedFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid field']);
            exit;
        }

        // Additional validation
        if ($field === 'priority' && !in_array($value, ['low', 'medium', 'high', 'urgent'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid priority value']);
            exit;
        }

        if ($field === 'assigned_to') {
            $value = intval($value);
            // Verify user exists
            $userCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
            $userCheck->execute([$value]);
            if (!$userCheck->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid user']);
                exit;
            }
        }

        // Update the field
        $updateStmt = $db->prepare("UPDATE tasks SET $field = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$value, $taskId]);

        echo json_encode([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $field)) . ' updated successfully'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
