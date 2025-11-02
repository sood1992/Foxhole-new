<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$currentUser = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $taskId = intval($input['task_id'] ?? 0);
            $planDate = $input['plan_date'] ?? date('Y-m-d');

            if (!$taskId) {
                throw new Exception('Task ID is required');
            }

            // Check if already planned
            $check = $db->prepare("SELECT id FROM daily_plans WHERE user_id = ? AND task_id = ? AND plan_date = ?");
            $check->execute([$currentUser['id'], $taskId, $planDate]);

            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Task already planned for this date']);
                exit;
            }

            // Add to plan
            $stmt = $db->prepare("INSERT INTO daily_plans (user_id, task_id, plan_date) VALUES (?, ?, ?)");
            $stmt->execute([$currentUser['id'], $taskId, $planDate]);

            echo json_encode(['success' => true, 'message' => 'Task added to plan']);
            break;

        case 'remove':
            $planId = intval($input['plan_id'] ?? 0);

            if (!$planId) {
                throw new Exception('Plan ID is required');
            }

            $stmt = $db->prepare("DELETE FROM daily_plans WHERE id = ? AND user_id = ?");
            $stmt->execute([$planId, $currentUser['id']]);

            echo json_encode(['success' => true, 'message' => 'Task removed from plan']);
            break;

        case 'toggle_complete':
            $planId = intval($input['plan_id'] ?? 0);
            $completed = $input['completed'] ? 1 : 0;

            if (!$planId) {
                throw new Exception('Plan ID is required');
            }

            $stmt = $db->prepare("UPDATE daily_plans SET completed = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$completed, $planId, $currentUser['id']]);

            echo json_encode(['success' => true, 'message' => 'Task updated']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
