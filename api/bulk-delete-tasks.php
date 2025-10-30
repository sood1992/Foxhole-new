<?php
/**
 * Bulk Delete Tasks API
 * Admin and Manager - Delete multiple tasks at once
 */

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication and authorization
if (!isLoggedIn() || !hasRole(['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = getDBConnection();
    $currentUser = getCurrentUser();
    $userRole = $currentUser['role'];

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $taskIds = $input['task_ids'] ?? [];

    // Validate input
    if (empty($taskIds) || !is_array($taskIds)) {
        echo json_encode(['success' => false, 'message' => 'No tasks selected']);
        exit;
    }

    // Start transaction
    $db->beginTransaction();

    $deletedCount = 0;
    $errors = [];

    foreach ($taskIds as $taskId) {
        // Validate task ID
        if (!is_numeric($taskId)) {
            $errors[] = "Invalid task ID: $taskId";
            continue;
        }

        // Check if task exists and get project info
        $stmt = $db->prepare("
            SELECT t.id, t.task_name, t.project_id, p.assigned_manager
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $errors[] = "Task ID $taskId not found";
            continue;
        }

        // If user is a manager (not admin), check if they're assigned to the project
        if ($userRole === 'manager' && $task['assigned_manager'] != $currentUser['id']) {
            $errors[] = "You don't have permission to delete task: {$task['task_name']}";
            continue;
        }

        try {
            // Delete all related data in proper order

            // 1. Delete time logs for this task
            $db->prepare("DELETE FROM time_logs WHERE task_id = ?")->execute([$taskId]);

            // 2. Delete task comments
            $db->prepare("DELETE FROM comments WHERE task_id = ?")->execute([$taskId]);

            // 3. Delete task attachments
            $db->prepare("DELETE FROM task_attachments WHERE task_id = ?")->execute([$taskId]);

            // 4. Delete notifications related to this task
            $db->prepare("DELETE FROM notifications WHERE task_id = ?")->execute([$taskId]);

            // 5. Finally, delete the task itself
            $deleteStmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $deleteStmt->execute([$taskId]);

            // 6. Log the activity
            logActivity(
                'delete',
                'task',
                $taskId,
                "Deleted task: {$task['task_name']}",
                [
                    'project_id' => $task['project_id'],
                    'bulk_delete' => true
                ]
            );

            $deletedCount++;

        } catch (Exception $e) {
            $errors[] = "Failed to delete task {$task['task_name']}: " . $e->getMessage();
        }
    }

    // Commit transaction
    $db->commit();

    if ($deletedCount > 0) {
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "$deletedCount task(s) deleted successfully",
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No tasks were deleted',
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
