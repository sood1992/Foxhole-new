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
$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'start':
            // Start time tracking for a task
            $taskId = $input['task_id'] ?? null;
            $projectId = $input['project_id'] ?? null;

            if (!$taskId || !$projectId) {
                throw new Exception('Task ID and Project ID are required');
            }

            // Check if user already has an active timer for THIS specific task
            $stmt = $db->prepare("SELECT id FROM time_logs WHERE user_id = ? AND task_id = ? AND is_active = 1");
            $stmt->execute([$userId, $taskId]);
            if ($stmt->fetch()) {
                throw new Exception('You already have an active timer for this task.');
            }

            // Verify task is assigned to user
            $stmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$taskId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Task not found or not assigned to you');
            }

            // Create new time log
            $stmt = $db->prepare("
                INSERT INTO time_logs (user_id, task_id, project_id, start_time, is_active)
                VALUES (?, ?, ?, NOW(), 1)
            ");
            $stmt->execute([$userId, $taskId, $projectId]);

            // Update task status to in_progress if it's todo
            $db->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ? AND status = 'todo'")->execute([$taskId]);

            echo json_encode([
                'success' => true,
                'message' => 'Timer started successfully',
                'log_id' => $db->lastInsertId()
            ]);
            break;

        case 'stop':
            // Stop time tracking
            $logId = $input['log_id'] ?? null;
            $notes = $input['notes'] ?? '';

            if (!$logId) {
                throw new Exception('Log ID is required');
            }

            // Get the time log
            $stmt = $db->prepare("SELECT * FROM time_logs WHERE id = ? AND user_id = ? AND is_active = 1");
            $stmt->execute([$logId, $userId]);
            $timeLog = $stmt->fetch();

            if (!$timeLog) {
                throw new Exception('Active time log not found');
            }

            // Calculate duration
            $startTime = strtotime($timeLog['start_time']);
            $endTime = time();
            $durationMinutes = round(($endTime - $startTime) / 60);

            // Update time log
            $stmt = $db->prepare("
                UPDATE time_logs
                SET end_time = NOW(),
                    duration_minutes = ?,
                    notes = ?,
                    is_active = 0
                WHERE id = ?
            ");
            $stmt->execute([$durationMinutes, $notes, $logId]);

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
            $stmt->execute([$timeLog['task_id'], $timeLog['task_id']]);

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
            $stmt->execute([$timeLog['project_id'], $timeLog['project_id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Timer stopped successfully',
                'duration_minutes' => $durationMinutes
            ]);
            break;

        case 'get_active':
            // Get all active time logs for current user
            $stmt = $db->prepare("
                SELECT tl.*, t.task_name, p.project_name
                FROM time_logs tl
                JOIN tasks t ON tl.task_id = t.id
                JOIN projects p ON tl.project_id = p.id
                WHERE tl.user_id = ? AND tl.is_active = 1
                ORDER BY tl.start_time DESC
            ");
            $stmt->execute([$userId]);
            $activeLogs = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'active_logs' => $activeLogs,
                'active_log' => !empty($activeLogs) ? $activeLogs[0] : null // For backwards compatibility
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
