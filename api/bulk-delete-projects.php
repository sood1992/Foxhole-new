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
            // Use try-catch for each operation to handle missing tables gracefully

            // 1. Get all task IDs for this project
            $taskIds = [];
            try {
                $taskIdsStmt = $db->prepare("SELECT id FROM tasks WHERE project_id = ?");
                $taskIdsStmt->execute([$projectId]);
                $taskIds = $taskIdsStmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                error_log("Skipping task ID fetch: " . $e->getMessage());
            }

            if (!empty($taskIds)) {
                $placeholders = str_repeat('?,', count($taskIds) - 1) . '?';

                // 2. Delete time logs for these tasks
                try {
                    $db->prepare("DELETE FROM time_logs WHERE task_id IN ($placeholders)")->execute($taskIds);
                } catch (PDOException $e) {
                    error_log("Skipping time_logs deletion: " . $e->getMessage());
                }

                // 3. Delete task comments (if table exists)
                try {
                    $db->prepare("DELETE FROM comments WHERE task_id IN ($placeholders)")->execute($taskIds);
                } catch (PDOException $e) {
                    error_log("Skipping comments deletion: " . $e->getMessage());
                }

                // 4. Delete task attachments (if table exists)
                try {
                    $db->prepare("DELETE FROM task_attachments WHERE task_id IN ($placeholders)")->execute($taskIds);
                } catch (PDOException $e) {
                    error_log("Skipping task_attachments deletion: " . $e->getMessage());
                }
            }

            // 5. Delete all tasks for this project
            try {
                $db->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping tasks deletion: " . $e->getMessage());
            }

            // 6. Delete project-related data (if tables exist)
            try {
                $db->prepare("DELETE FROM project_budgets WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping project_budgets deletion: " . $e->getMessage());
            }

            try {
                $db->prepare("DELETE FROM project_expenses WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping project_expenses deletion: " . $e->getMessage());
            }

            try {
                $db->prepare("DELETE FROM project_deliverables WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping project_deliverables deletion: " . $e->getMessage());
            }

            try {
                $db->prepare("DELETE FROM calendar_events WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping calendar_events deletion: " . $e->getMessage());
            }

            // 7. Delete client feedback for this project (if table exists)
            try {
                $feedbackStmt = $db->prepare("SELECT id FROM client_feedback WHERE project_id = ?");
                $feedbackStmt->execute([$projectId]);
                $feedbackIds = $feedbackStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($feedbackIds)) {
                    $placeholders = str_repeat('?,', count($feedbackIds) - 1) . '?';
                    try {
                        $db->prepare("DELETE FROM feedback_responses WHERE feedback_id IN ($placeholders)")->execute($feedbackIds);
                    } catch (PDOException $e) {
                        error_log("Skipping feedback_responses deletion: " . $e->getMessage());
                    }
                }

                $db->prepare("DELETE FROM client_feedback WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping client_feedback deletion: " . $e->getMessage());
            }

            // 8. Delete notifications related to this project (check if column exists)
            try {
                $db->prepare("DELETE FROM notifications WHERE project_id = ?")->execute([$projectId]);
            } catch (PDOException $e) {
                error_log("Skipping notifications deletion (project_id column may not exist): " . $e->getMessage());
            }

            // 9. Finally, delete the project itself
            $deleteStmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $deleteStmt->execute([$projectId]);

            // 10. Log the activity (if function exists and table exists)
            try {
                if (function_exists('logActivity')) {
                    logActivity(
                        'delete',
                        'project',
                        $projectId,
                        "Deleted project: {$project['project_name']}",
                        [
                            'bulk_delete' => true
                        ]
                    );
                }
            } catch (Exception $e) {
                error_log("Skipping activity log: " . $e->getMessage());
            }

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
