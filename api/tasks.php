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

// Get JSON input for POST/PUT/DELETE requests
$input = json_decode(file_get_contents('php://input'), true);

try {
    // GET: Get task(s)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check if fetching a specific task
        $taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($taskId) {
            // Fetch single task details
            $stmt = $db->prepare("
                SELECT t.*, p.project_name, p.assigned_manager
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new Exception('Task not found');
            }

            // Check if user has permission to view this task
            if (!hasRole('admin') && !hasRole('manager')) {
                // Employees can only view their own tasks
                if ($task['assigned_to'] != $currentUser['id']) {
                    throw new Exception('You can only view tasks assigned to you');
                }
            } else {
                // Managers can only view tasks in their projects (unless admin)
                if (!hasRole('admin') && $task['assigned_manager'] != $currentUser['id']) {
                    throw new Exception('You can only view tasks in your own projects');
                }
            }

            echo json_encode([
                'success' => true,
                'task' => $task
            ]);
        } else {
            // Fetch tasks list
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
    }
    // PUT: Update a task
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $taskId = $input['id'] ?? 0;
        $taskName = trim($input['task_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $assignedTo = intval($input['assigned_to'] ?? 0);
        $status = $input['status'] ?? 'todo';
        $priority = $input['priority'] ?? 'medium';
        $estimatedHours = floatval($input['estimated_hours'] ?? 0);
        $dueDate = $input['due_date'] ?? null;

        if (empty($taskName)) {
            throw new Exception('Task name is required');
        }

        if (!$taskId) {
            throw new Exception('Task ID is required');
        }

        // Check if user has permission to update this task
        if (!hasRole('admin') && !hasRole('manager')) {
            throw new Exception('Only managers and admins can update tasks');
        }

        // Get task's project to verify manager
        $stmt = $db->prepare("
            SELECT p.assigned_manager, t.project_id
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $taskProject = $stmt->fetch();

        if (!$taskProject) {
            throw new Exception('Task not found');
        }

        if (!hasRole('admin') && $taskProject['assigned_manager'] != $currentUser['id']) {
            throw new Exception('You can only update tasks in your own projects');
        }

        // Update task
        $updateStmt = $db->prepare("
            UPDATE tasks
            SET task_name = ?,
                description = ?,
                assigned_to = ?,
                status = ?,
                priority = ?,
                estimated_hours = ?,
                due_date = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $result = $updateStmt->execute([
            $taskName,
            $description,
            $assignedTo > 0 ? $assignedTo : null,
            $status,
            $priority,
            $estimatedHours > 0 ? $estimatedHours : null,
            !empty($dueDate) ? $dueDate : null,
            $taskId
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Task updated successfully'
            ]);
        } else {
            throw new Exception('Failed to update task');
        }
    }
    // DELETE: Delete a task
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $taskId = $input['id'] ?? 0;

        if (!$taskId) {
            throw new Exception('Task ID is required');
        }

        // Check if user has permission to delete this task
        if (!hasRole('admin') && !hasRole('manager')) {
            throw new Exception('Only managers and admins can delete tasks');
        }

        // Get task's project to verify manager
        $stmt = $db->prepare("
            SELECT p.assigned_manager, t.id
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $taskProject = $stmt->fetch();

        if (!$taskProject) {
            throw new Exception('Task not found');
        }

        if (!hasRole('admin') && $taskProject['assigned_manager'] != $currentUser['id']) {
            throw new Exception('You can only delete tasks in your own projects');
        }

        // Check for dependencies
        $stmt = $db->prepare("SELECT COUNT(*) as dep_count FROM task_dependencies WHERE depends_on_task_id = ?");
        $stmt->execute([$taskId]);
        $depCount = $stmt->fetch()['dep_count'];

        if ($depCount > 0) {
            throw new Exception("Cannot delete task with {$depCount} dependent tasks. Please remove dependencies first.");
        }

        // Delete task dependencies first
        $db->prepare("DELETE FROM task_dependencies WHERE task_id = ?")->execute([$taskId]);

        // Delete task time logs
        $db->prepare("DELETE FROM time_logs WHERE task_id = ?")->execute([$taskId]);

        // Delete task comments
        $db->prepare("DELETE FROM comments WHERE task_id = ?")->execute([$taskId]);

        // Delete the task
        $deleteStmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $result = $deleteStmt->execute([$taskId]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete task');
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
