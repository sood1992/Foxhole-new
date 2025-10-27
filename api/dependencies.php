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
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET: Get dependencies for a task
    if ($method === 'GET') {
        $taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

        if (!$taskId) {
            throw new Exception('Task ID is required');
        }

        // Get tasks that this task depends on
        $stmt = $db->prepare("
            SELECT
                td.id as dependency_id,
                td.dependency_type,
                t.id as task_id,
                t.task_name,
                t.status,
                p.project_name
            FROM task_dependencies td
            JOIN tasks t ON td.depends_on_task_id = t.id
            JOIN projects p ON t.project_id = p.id
            WHERE td.task_id = ?
        ");
        $stmt->execute([$taskId]);
        $dependsOn = $stmt->fetchAll();

        // Get tasks that depend on this task
        $stmt = $db->prepare("
            SELECT
                td.id as dependency_id,
                td.dependency_type,
                t.id as task_id,
                t.task_name,
                t.status,
                p.project_name
            FROM task_dependencies td
            JOIN tasks t ON td.task_id = t.id
            JOIN projects p ON t.project_id = p.id
            WHERE td.depends_on_task_id = ?
        ");
        $stmt->execute([$taskId]);
        $blockedBy = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'depends_on' => $dependsOn,
            'blocks' => $blockedBy
        ]);
    }

    // POST: Add dependency
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $taskId = $data['task_id'] ?? 0;
        $dependsOnTaskId = $data['depends_on_task_id'] ?? 0;
        $dependencyType = $data['dependency_type'] ?? 'finish_to_start';

        if (!$taskId || !$dependsOnTaskId) {
            throw new Exception('Both task IDs are required');
        }

        if ($taskId === $dependsOnTaskId) {
            throw new Exception('A task cannot depend on itself');
        }

        // Check for circular dependency
        if (hasCircularDependency($db, $taskId, $dependsOnTaskId)) {
            throw new Exception('This would create a circular dependency');
        }

        $stmt = $db->prepare("
            INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$taskId, $dependsOnTaskId, $dependencyType]);

        echo json_encode(['success' => true, 'message' => 'Dependency added']);
    }

    // DELETE: Remove dependency
    elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $dependencyId = $data['dependency_id'] ?? 0;

        if (!$dependencyId) {
            throw new Exception('Dependency ID is required');
        }

        $stmt = $db->prepare("DELETE FROM task_dependencies WHERE id = ?");
        $stmt->execute([$dependencyId]);

        echo json_encode(['success' => true, 'message' => 'Dependency removed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Check for circular dependencies
function hasCircularDependency($db, $taskId, $dependsOnTaskId, $visited = []) {
    if (in_array($dependsOnTaskId, $visited)) {
        return true; // Circular dependency detected
    }

    $visited[] = $dependsOnTaskId;

    // Get all tasks that $dependsOnTaskId depends on
    $stmt = $db->prepare("SELECT depends_on_task_id FROM task_dependencies WHERE task_id = ?");
    $stmt->execute([$dependsOnTaskId]);
    $dependencies = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($dependencies as $depId) {
        if ($depId == $taskId) {
            return true; // Would create circular dependency
        }
        if (hasCircularDependency($db, $taskId, $depId, $visited)) {
            return true;
        }
    }

    return false;
}
