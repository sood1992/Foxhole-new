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
    // UPDATE: Update a project
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $projectId = $input['id'] ?? 0;
        $projectName = trim($input['project_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $clientName = trim($input['client_name'] ?? '');
        $status = $input['status'] ?? 'planning';
        $priority = $input['priority'] ?? 'medium';
        $startDate = $input['start_date'] ?? null;
        $dueDate = $input['due_date'] ?? null;
        $estimatedHours = floatval($input['estimated_hours'] ?? 0);

        if (empty($projectName)) {
            throw new Exception('Project name is required');
        }

        // Check if user is the manager of this project
        if (!hasRole('admin') && !hasRole('manager')) {
            throw new Exception('Only managers can update projects');
        }

        $stmt = $db->prepare("SELECT assigned_manager FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            throw new Exception('Project not found');
        }

        if (!hasRole('admin') && $project['assigned_manager'] != $currentUser['id']) {
            throw new Exception('You can only update your own projects');
        }

        // Update project
        $updateStmt = $db->prepare("
            UPDATE projects
            SET project_name = ?,
                description = ?,
                client_name = ?,
                status = ?,
                priority = ?,
                start_date = ?,
                due_date = ?,
                estimated_hours = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $result = $updateStmt->execute([
            $projectName,
            $description,
            $clientName,
            $status,
            $priority,
            !empty($startDate) ? $startDate : null,
            !empty($dueDate) ? $dueDate : null,
            $estimatedHours > 0 ? $estimatedHours : null,
            $projectId
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Project updated successfully'
            ]);
        } else {
            throw new Exception('Failed to update project');
        }
    }
    // DELETE: Delete a project
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $projectId = $input['id'] ?? 0;

        if (!$projectId) {
            throw new Exception('Project ID is required');
        }

        // Check if user is the manager of this project
        if (!hasRole('admin') && !hasRole('manager')) {
            throw new Exception('Only managers can delete projects');
        }

        $stmt = $db->prepare("SELECT assigned_manager FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            throw new Exception('Project not found');
        }

        if (!hasRole('admin') && $project['assigned_manager'] != $currentUser['id']) {
            throw new Exception('You can only delete your own projects');
        }

        // Check if project has tasks
        $stmt = $db->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $taskCount = $stmt->fetch()['task_count'];

        if ($taskCount > 0) {
            throw new Exception("Cannot delete project with {$taskCount} tasks. Please delete or reassign tasks first.");
        }

        // Delete project
        $deleteStmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        $result = $deleteStmt->execute([$projectId]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete project');
        }
    }
    // GET: Get project details
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $projectId = $_GET['id'] ?? 0;

        if (!$projectId) {
            throw new Exception('Project ID is required');
        }

        $stmt = $db->prepare("
            SELECT p.*,
                   (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
                   (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
            FROM projects p
            WHERE p.id = ?
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            throw new Exception('Project not found');
        }

        echo json_encode([
            'success' => true,
            'project' => $project
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
