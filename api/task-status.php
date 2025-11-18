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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'start_working':
            // Employee starts working on a task
            $taskId = $input['task_id'] ?? 0;

            if (!$taskId) {
                throw new Exception('Task ID is required');
            }

            // Verify task is assigned to user
            $stmt = $db->prepare("SELECT id, status, project_id FROM tasks WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$taskId, $currentUser['id']]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new Exception('Task not found or not assigned to you');
            }

            // Check if there's already an active time log for this task
            $stmt = $db->prepare("SELECT id FROM time_logs WHERE user_id = ? AND task_id = ? AND is_active = 1");
            $stmt->execute([$currentUser['id'], $taskId]);
            $existingLog = $stmt->fetch();

            if (!$existingLog) {
                // Automatically start time tracking in background
                $stmt = $db->prepare("
                    INSERT INTO time_logs (user_id, task_id, project_id, start_time, is_active, notes)
                    VALUES (?, ?, ?, NOW(), 1, 'Auto-tracked: Employee started working')
                ");
                $stmt->execute([$currentUser['id'], $taskId, $task['project_id']]);
            }

            // Update status to in_progress
            $stmt = $db->prepare("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$taskId]);

            // Add a comment
            $stmt = $db->prepare("
                INSERT INTO task_comments (task_id, user_id, comment, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $currentUser['id'], 'Started working on this task']);

            echo json_encode([
                'success' => true,
                'message' => 'Task status updated to In Progress. Time tracking started automatically.'
            ]);
            break;

        case 'submit_for_review':
            // Employee submits task for review
            $taskId = $input['task_id'] ?? 0;
            $comment = trim($input['comment'] ?? '');

            if (!$taskId) {
                throw new Exception('Task ID is required');
            }

            // Verify task is assigned to user
            $stmt = $db->prepare("SELECT id, status, project_id FROM tasks WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$taskId, $currentUser['id']]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new Exception('Task not found or not assigned to you');
            }

            // Automatically stop any active time tracking for this task
            $stmt = $db->prepare("SELECT id, start_time FROM time_logs WHERE user_id = ? AND task_id = ? AND is_active = 1");
            $stmt->execute([$currentUser['id'], $taskId]);
            $activeLog = $stmt->fetch();

            if ($activeLog) {
                // Calculate duration
                $startTime = strtotime($activeLog['start_time']);
                $endTime = time();
                $durationMinutes = round(($endTime - $startTime) / 60);

                // Update time log - stop the timer
                $stmt = $db->prepare("
                    UPDATE time_logs
                    SET end_time = NOW(),
                        duration_minutes = ?,
                        is_active = 0,
                        notes = CONCAT(notes, ' | Auto-stopped: Task submitted for review')
                    WHERE id = ?
                ");
                $stmt->execute([$durationMinutes, $activeLog['id']]);

                // Update task actual hours
                $stmt = $db->prepare("
                    UPDATE tasks
                    SET actual_hours = (
                        SELECT SUM(duration_minutes) / 60
                        FROM time_logs
                        WHERE task_id = ? AND end_time IS NOT NULL
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$taskId, $taskId]);

                // Update project actual hours
                $stmt = $db->prepare("
                    UPDATE projects
                    SET actual_hours = (
                        SELECT SUM(duration_minutes) / 60
                        FROM time_logs
                        WHERE project_id = ? AND end_time IS NOT NULL
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$task['project_id'], $task['project_id']]);
            }

            // Update status to review
            $stmt = $db->prepare("UPDATE tasks SET status = 'review', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$taskId]);

            // Add a comment with the submission note
            $submissionComment = !empty($comment) ? $comment : 'Submitted task for review';
            $stmt = $db->prepare("
                INSERT INTO task_comments (task_id, user_id, comment, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $currentUser['id'], $submissionComment]);

            // TODO: Send notification to project manager
            // $notificationStmt = $db->prepare("INSERT INTO notifications ...");

            // Calculate total time spent on this task
            $stmt = $db->prepare("
                SELECT SUM(duration_minutes) as total_minutes
                FROM time_logs
                WHERE task_id = ? AND user_id = ? AND end_time IS NOT NULL
            ");
            $stmt->execute([$taskId, $currentUser['id']]);
            $totalMinutes = $stmt->fetch()['total_minutes'] ?? 0;
            $totalHours = round($totalMinutes / 60, 1);

            $timeMessage = '';
            if ($totalMinutes > 0) {
                if ($totalHours < 1) {
                    $timeMessage = " You spent " . round($totalMinutes) . " minutes on this task.";
                } else {
                    $timeMessage = " You spent " . $totalHours . " hours on this task.";
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Task submitted for review successfully!' . $timeMessage,
                'time_spent_minutes' => $totalMinutes,
                'time_spent_hours' => $totalHours
            ]);
            break;

        case 'add_progress_comment':
            // Employee adds a progress comment
            $taskId = $input['task_id'] ?? 0;
            $comment = trim($input['comment'] ?? '');

            if (!$taskId) {
                throw new Exception('Task ID is required');
            }

            if (empty($comment)) {
                throw new Exception('Comment is required');
            }

            // Verify task is assigned to user
            $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$taskId, $currentUser['id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Task not found or not assigned to you');
            }

            // Add the comment
            $stmt = $db->prepare("
                INSERT INTO task_comments (task_id, user_id, comment, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $currentUser['id'], $comment]);

            echo json_encode([
                'success' => true,
                'message' => 'Progress comment added successfully'
            ]);
            break;

        case 'update_status':
            // General status update (for other status changes)
            $taskId = $input['task_id'] ?? 0;
            $newStatus = $input['status'] ?? '';

            if (!$taskId) {
                throw new Exception('Task ID is required');
            }

            if (!in_array($newStatus, ['todo', 'in_progress', 'review', 'blocked'])) {
                throw new Exception('Invalid status');
            }

            // Verify task is assigned to user
            $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$taskId, $currentUser['id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Task not found or not assigned to you');
            }

            // Update status
            $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $taskId]);

            echo json_encode([
                'success' => true,
                'message' => 'Task status updated successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
