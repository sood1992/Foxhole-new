<?php
/**
 * Bulk Delete Projects API
 * Admin and Manager - Delete multiple projects at once
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
    $projectIds = $input['project_ids'] ?? [];

    // Validate input
    if (empty($projectIds) || !is_array($projectIds)) {
        echo json_encode(['success' => false, 'message' => 'No projects selected']);
        exit;
    }

    // Start transaction
    $db->beginTransaction();

    $deletedCount = 0;
    $errors = [];

    foreach ($projectIds as $projectId) {
        // Validate project ID
        if (!is_numeric($projectId)) {
            $errors[] = "Invalid project ID: $projectId";
            continue;
        }

        // Check if project exists
        $stmt = $db->prepare("SELECT id, project_name, assigned_manager FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            $errors[] = "Project ID $projectId not found";
            continue;
        }

        // If user is a manager (not admin), check if they're assigned to this project
        if ($userRole === 'manager' && $project['assigned_manager'] != $currentUser['id']) {
            $errors[] = "You don't have permission to delete project: {$project['project_name']}";
            continue;
        }

        try {
            // Delete all related data in proper order to maintain referential integrity

            // 1. Get all task IDs for this project
            $taskIdsStmt = $db->prepare("SELECT id FROM tasks WHERE project_id = ?");
            $taskIdsStmt->execute([$projectId]);
            $taskIds = $taskIdsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($taskIds)) {
                $placeholders = str_repeat('?,', count($taskIds) - 1) . '?';

                // 2. Delete time logs for these tasks
                $db->prepare("DELETE FROM time_logs WHERE task_id IN ($placeholders)")->execute($taskIds);

                // 3. Delete task comments
                $db->prepare("DELETE FROM comments WHERE task_id IN ($placeholders)")->execute($taskIds);

                // 4. Delete task attachments
                $db->prepare("DELETE FROM task_attachments WHERE task_id IN ($placeholders)")->execute($taskIds);
            }

            // 5. Delete all tasks for this project
            $db->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$projectId]);

            // 6. Delete project-related data
            $db->prepare("DELETE FROM project_budgets WHERE project_id = ?")->execute([$projectId]);
            $db->prepare("DELETE FROM project_expenses WHERE project_id = ?")->execute([$projectId]);
            $db->prepare("DELETE FROM project_deliverables WHERE project_id = ?")->execute([$projectId]);
            $db->prepare("DELETE FROM calendar_events WHERE project_id = ?")->execute([$projectId]);

            // 7. Delete client feedback for this project
            $feedbackStmt = $db->prepare("SELECT id FROM client_feedback WHERE project_id = ?");
            $feedbackStmt->execute([$projectId]);
            $feedbackIds = $feedbackStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($feedbackIds)) {
                $placeholders = str_repeat('?,', count($feedbackIds) - 1) . '?';
                $db->prepare("DELETE FROM feedback_responses WHERE feedback_id IN ($placeholders)")->execute($feedbackIds);
            }

            $db->prepare("DELETE FROM client_feedback WHERE project_id = ?")->execute([$projectId]);

            // 8. Delete notifications related to this project
            $db->prepare("DELETE FROM notifications WHERE project_id = ?")->execute([$projectId]);

            // 9. Finally, delete the project itself
            $deleteStmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $deleteStmt->execute([$projectId]);

            // 10. Log the activity
            logActivity(
                'delete',
                'project',
                $projectId,
                "Deleted project: {$project['project_name']}",
                [
                    'bulk_delete' => true
                ]
            );

            $deletedCount++;

        } catch (Exception $e) {
            $errors[] = "Failed to delete project {$project['project_name']}: " . $e->getMessage();
        }
    }

    // Commit transaction
    $db->commit();

    if ($deletedCount > 0) {
        echo json_encode([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "$deletedCount project(s) deleted successfully",
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No projects were deleted',
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
